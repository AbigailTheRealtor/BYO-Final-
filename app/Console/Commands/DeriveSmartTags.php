<?php

namespace App\Console\Commands;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Services\SmartTags\DerivationOutcome;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\SmartTags\SmartTagTelemetry;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagWiring;
use App\Support\Safeguards\ProductionDatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Derive Smart Tags for listings and MLS rows that already exist.
 *
 * THE COMPANION TO THE DEFERRED PATHS. Explore discovery, criteria search and the
 * bulk Bridge importer deliberately do not tag as they go, because each can upsert
 * hundreds of rows inside a request somebody is waiting on. Those rows are not
 * suppressed, only deferred: `smart_tag_derivation_states` is the staleness
 * ledger, a row with no state has never been derived, and this command is what
 * catches them up.
 *
 * IDEMPOTENT BY CONSTRUCTION, not by bookkeeping. It calls the same derivation
 * service every live path calls, and that service skips a source whose hash,
 * context and tagger version are all unchanged. Running this twice therefore does
 * real work once; the second run reports everything as skipped.
 *
 * RESUMABLE AND BOUNDED. Ordered by primary key and walked with chunkById(), so a
 * run holds one batch in memory rather than an inventory, and rows changing under
 * the cursor cannot make it skip or repeat (which plain chunk() does). `--from-id`
 * resumes; the final line prints where to resume from.
 *
 * ONE FAILURE IS ONE FAILURE. Every listing goes through SmartTagLifecycle, which
 * never throws, so a listing that cannot be derived is counted and the batch
 * continues.
 *
 * MLS PublicRemarks is not processed here and cannot be: there is no option for
 * it, the derivation service does not parse it, and the evidence writer refuses
 * to store it.
 */
class DeriveSmartTags extends Command
{
    protected $signature = 'smart-tags:derive
                            {--source=all : bridge|native|all}
                            {--listing-type=* : bridge|seller_agent|landlord_agent (repeatable; narrows within --source)}
                            {--context= : Only listings resolving to this context (e.g. residential.sale)}
                            {--id=* : Exact listing ids (repeatable; requires exactly one --listing-type)}
                            {--from-id= : Resume cursor — process ids greater than this}
                            {--only-stale : Skip listings whose derivation state is already current}
                            {--limit=0 : Maximum listings to process this run (0 = no limit)}
                            {--batch-size=200 : Rows fetched per chunk}
                            {--dry-run : Report what would change and write nothing}';

    protected $description = 'Derive Smart Tags for existing Bridge properties and native Offer Listings';

    /** Mutually exclusive; these sum to `considered`. */
    private const OUTCOMES = [
        'tagged', 'skipped_unchanged', 'skipped_no_context', 'skipped_not_offer_listing',
        'skipped_archived', 'skipped_wrong_context', 'failed',
    ];

    /** Independent tallies that ride alongside an outcome. */
    private const NOTES = ['remarks_blocked', 'description_suppressed'];

    /** @var array<string, int> */
    private array $counts = [];

    private ?int $lastId = null;

    public function handle(SmartTagLifecycle $lifecycle): int
    {
        $types = $this->resolveTypes();

        if ($types === null) {
            return self::FAILURE;
        }

        $context = $this->resolveContext();

        if ($context === false) {
            return self::FAILURE;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $this->option('id')), static fn (int $id) => $id > 0));

        if ($ids !== [] && count($types) !== 1) {
            $this->error('--id requires exactly one --listing-type, so an id cannot be applied to the wrong table.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $this->confirmWrites($dryRun)) {
            return self::FAILURE;
        }

        $this->reportPosture($types, $dryRun);

        $limit = max(0, (int) $this->option('limit'));
        $batchSize = max(1, (int) $this->option('batch-size'));
        $fromId = $this->option('from-id') === null ? null : (int) $this->option('from-id');

        // Two groups, and the split matters when reading a run.
        //
        // OUTCOMES are mutually exclusive and sum to `considered`: every listing
        // lands in exactly one. NOTES are independent tallies that ride alongside
        // — `remarks_blocked` is true of EVERY Bridge row (the gate is permanent),
        // so counting it as an outcome would hide whether that row was tagged.
        $this->counts = array_fill_keys(array_merge(self::OUTCOMES, self::NOTES), 0);
        $this->counts['considered'] = 0;

        $onlyStale = (bool) $this->option('only-stale');

        foreach ($types as $type) {
            $this->processType($lifecycle, $type, $ids, $fromId, $limit, $batchSize, $context, $dryRun, $onlyStale);

            if ($limit > 0 && $this->counts['considered'] >= $limit) {
                break;
            }
        }

        $this->summarise($dryRun);

        return self::SUCCESS;
    }

    /**
     * @param SmartTagContext|null $context
     * @param array<int>           $ids
     */
    private function processType(
        SmartTagLifecycle $lifecycle,
        SmartTagListingType $type,
        array $ids,
        ?int $fromId,
        int $limit,
        int $batchSize,
        ?SmartTagContext $context,
        bool $dryRun,
        bool $onlyStale,
    ): void {
        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();

        $query = $modelClass::query();

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if ($fromId !== null) {
            $query->where('id', '>', $fromId);
        }

        $stopped = false;

        $query->orderBy('id')->chunkById($batchSize, function ($rows) use (
            $lifecycle, $type, $limit, $context, $dryRun, $onlyStale, &$stopped
        ) {
            $batch = array_fill_keys(array_keys($this->counts), 0);

            foreach ($rows as $row) {
                if ($limit > 0 && $this->counts['considered'] >= $limit) {
                    $stopped = true;

                    break;
                }

                $this->counts['considered']++;
                $batch['considered']++;
                $this->lastId = (int) $row->getKey();

                // --only-stale answers from the state row alone, before the
                // derivers are constructed or any meta is loaded. That is the
                // point of the option: a current listing costs one indexed
                // lookup rather than a full derivation that would conclude
                // nothing changed.
                if ($onlyStale && $this->isCurrent($type, (int) $row->getKey())) {
                    $this->counts['skipped_unchanged']++;
                    $batch['skipped_unchanged']++;

                    continue;
                }

                [$outcome, $notes] = $dryRun
                    ? $this->planFor($type, $row, $context)
                    : $this->deriveOne($lifecycle, $type, $row, $context);

                $this->counts[$outcome]++;
                $batch[$outcome]++;

                foreach ($notes as $note) {
                    $this->counts[$note]++;
                    $batch[$note]++;
                }
            }

            SmartTagTelemetry::batch(SmartTagTelemetry::ENTRY_BACKFILL, $batch, [
                'listing_type' => $type->value,
                'last_id'      => $this->lastId,
                'dry_run'      => $dryRun,
            ]);

            $this->line(sprintf(
                '  %-15s batch: %d considered, %d tagged, %d skipped, %d failed (last id %d)',
                $type->value,
                $batch['considered'],
                $batch['tagged'],
                $batch['considered'] - $batch['tagged'] - $batch['failed'],
                $batch['failed'],
                $this->lastId ?? 0,
            ));

            return ! $stopped;
        });
    }

    /**
     * Derive one row.
     *
     * @return array{0: string, 1: string[]} the outcome, and any independent notes
     */
    private function deriveOne(SmartTagLifecycle $lifecycle, SmartTagListingType $type, Model $row, ?SmartTagContext $context): array
    {
        if ($context !== null && ! $this->matchesStoredContext($type, $row, $context)) {
            return ['skipped_wrong_context', []];
        }

        $outcome = $type === SmartTagListingType::Bridge
            ? $lifecycle->deriveForBridgeSilently($row, SmartTagTelemetry::ENTRY_BACKFILL)
            : $lifecycle->deriveForNativeSilently($row, SmartTagTelemetry::ENTRY_BACKFILL);

        if ($outcome === null) {
            // The lifecycle swallowed a fault, or a gate refused. The gates were
            // reported up front, so at this point it is a failure.
            return ['failed', []];
        }

        return [$this->classify($outcome), $this->notesFor($outcome)];
    }

    private function classify(DerivationOutcome $outcome): string
    {
        if (! $outcome->derived) {
            return match ($outcome->skippedReason) {
                DerivationOutcome::NO_CONTEXT           => 'skipped_no_context',
                DerivationOutcome::NOT_AN_OFFER_LISTING => 'skipped_not_offer_listing',
                DerivationOutcome::ARCHIVED             => 'skipped_archived',
                default                                 => 'skipped_unchanged',
            };
        }

        return ($outcome->structuredDerived || $outcome->descriptionParsed) ? 'tagged' : 'skipped_unchanged';
    }

    /**
     * Independent tallies, never an outcome.
     *
     * `remarks_blocked` is on EVERY Bridge derivation, because the gate is
     * permanent rather than situational — it records that the refusal happened,
     * which is what makes the posture observable instead of assumed.
     *
     * @return string[]
     */
    private function notesFor(DerivationOutcome $outcome): array
    {
        $notes = [];

        if (in_array(DerivationOutcome::MLS_REMARKS_NOT_APPROVED, $outcome->notes, true)) {
            $notes[] = 'remarks_blocked';
        }

        if (in_array(DerivationOutcome::DESCRIPTION_SUPPRESSED, $outcome->notes, true)) {
            $notes[] = 'description_suppressed';
        }

        return $notes;
    }

    /**
     * What a real run WOULD do to this row, computed without calling anything that writes.
     *
     * Dry-run safety is structural here: this method reaches no writer, no
     * projector and no state save, so there is no flag inside the write path that
     * could be wrong. It answers from the derivation state alone, which is the
     * same question `--only-stale` asks.
     *
     * @return array{0: string, 1: string[]}
     */
    private function planFor(SmartTagListingType $type, Model $row, ?SmartTagContext $context): array
    {
        if ($context !== null && ! $this->matchesStoredContext($type, $row, $context)) {
            return ['skipped_wrong_context', []];
        }

        $notes = $type === SmartTagListingType::Bridge ? ['remarks_blocked'] : [];

        return [$this->isCurrent($type, (int) $row->getKey()) ? 'skipped_unchanged' : 'tagged', $notes];
    }

    /**
     * True when this listing's recorded tagger version matches the current one.
     *
     * Deliberately coarse: it does NOT recompute the per-source hashes, because
     * doing so would mean running the derivers, and the point of --only-stale is
     * to avoid that work for rows nothing has changed. A row that passes this
     * check still goes through the service's own exact hash comparison, which is
     * what actually decides whether anything is rewritten.
     */
    private function isCurrent(SmartTagListingType $type, int $id): bool
    {
        $state = SmartTagDerivationState::query()
            ->where('listing_type', $type->value)
            ->where('listing_id', $id)
            ->first();

        return $state !== null
            && $state->tagger_version === \App\Support\SmartTags\SmartTagVersion::taggerVersion();
    }

    private function matchesStoredContext(SmartTagListingType $type, Model $row, SmartTagContext $context): bool
    {
        $state = SmartTagDerivationState::query()
            ->where('listing_type', $type->value)
            ->where('listing_id', (int) $row->getKey())
            ->first();

        if ($state !== null) {
            return $state->context === $context->value;
        }

        // Never derived, so the only way to know its context is to resolve it —
        // which the derivation service does anyway. Let it through; a mismatch
        // then shows up as the service deriving into a different context, and the
        // filter is a narrowing convenience rather than a correctness boundary.
        return true;
    }

    /**
     * @return SmartTagListingType[]|null null on a bad option
     */
    private function resolveTypes(): ?array
    {
        $source = strtolower(trim((string) $this->option('source')));

        if (! in_array($source, ['bridge', 'native', 'all'], true)) {
            $this->error("--source must be bridge, native or all (got '{$source}').");

            return null;
        }

        $fromSource = match ($source) {
            'bridge' => [SmartTagListingType::Bridge],
            'native' => [SmartTagListingType::SellerAgent, SmartTagListingType::LandlordAgent],
            default  => SmartTagListingType::cases(),
        };

        $named = array_filter((array) $this->option('listing-type'), static fn ($v) => trim((string) $v) !== '');

        if ($named === []) {
            return $fromSource;
        }

        $resolved = [];

        foreach ($named as $name) {
            $type = SmartTagListingType::tryFrom(trim((string) $name));

            if ($type === null) {
                $this->error("--listing-type must be bridge, seller_agent or landlord_agent (got '{$name}').");

                return null;
            }

            if (! in_array($type, $fromSource, true)) {
                $this->error("--listing-type={$type->value} is not part of --source={$source}.");

                return null;
            }

            $resolved[$type->value] = $type;
        }

        return array_values($resolved);
    }

    /**
     * @return SmartTagContext|null|false false on a bad option
     */
    private function resolveContext(): SmartTagContext|null|false
    {
        $raw = $this->option('context');

        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $context = SmartTagContext::tryFrom(trim((string) $raw));

        if ($context === null) {
            $this->error('--context must be one of: ' . implode(', ', array_map(
                static fn (SmartTagContext $c) => $c->value,
                SmartTagContext::cases(),
            )));

            return false;
        }

        return $context;
    }

    /**
     * A production write needs a person to say so, here and now.
     *
     * NO OVERRIDE TOKEN, and no RefusesProductionDatabase. A token can be pasted
     * into a cron line, a CI job or an agent's command string once and then means
     * nothing forever after; refusing production outright would make the backfill
     * unrunnable exactly where it is needed. So the rule is interactivity itself:
     * a human is at a terminal, or this does not write.
     *
     * A non-interactive invocation ABORTS rather than proceeding, and a declined
     * confirmation performs zero writes — the check runs before the first row is
     * read, not between batches.
     */
    private function confirmWrites(bool $dryRun): bool
    {
        if ($dryRun) {
            return true;
        }

        try {
            $assessment = ProductionDatabaseGuard::assessApplication($this->laravel);
        } catch (Throwable $e) {
            // A target we cannot assess is treated as production, per the house
            // rule that an unverifiable target counts as one.
            $this->error('Could not determine whether this database is production: ' . get_class($e));
            $this->error('Refusing to write. Re-run with --dry-run, or fix the environment.');

            return false;
        }

        if (! $assessment->isProduction()) {
            return true;
        }

        $this->warn('');
        $this->warn('  PRODUCTION DATABASE: ' . $assessment->resolvedTarget());
        foreach ($assessment->signals() as $signal) {
            $this->warn('    - ' . $signal);
        }
        $this->warn('');
        $this->warn('  This run WRITES Smart Tag evidence, assignments and derivation state.');
        $this->warn('  Manual owner selections are not touched.');
        $this->warn('');

        if (! $this->hasAHumanAtATerminal()) {
            $this->error('Refusing: a production write needs an interactive confirmation, and this invocation is not interactive.');
            $this->error('Nothing was written. Run it from a terminal, or use --dry-run.');

            return false;
        }

        if (! $this->confirm('Write Smart Tags to this production database?', false)) {
            $this->info('Cancelled. Nothing was written.');

            return false;
        }

        return true;
    }

    /**
     * Is there actually a person able to answer a prompt?
     *
     * `$this->input->isInteractive()` ALONE IS NOT ENOUGH, and finding that out is
     * why this method exists. Symfony reports an input as interactive unless
     * something explicitly said otherwise, so a programmatic `Artisan::call()` —
     * which is precisely how a scheduler, another command or an agent invokes
     * this — answers TRUE and sails into `confirm()`. What happens next is a
     * prompt read from a stream nobody is typing into.
     *
     * A terminal on STDIN is the fact that cannot be faked by a default. A cron
     * entry, a CI step, a queued job and an agent all lack one; a person running
     * the command has one. Both must agree, and an environment where the check is
     * unavailable counts as NOT interactive — the fail-closed direction, since the
     * decision being gated is a production write.
     */
    private function hasAHumanAtATerminal(): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDIN);
        }

        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDIN);
        }

        return false;
    }

    /**
     * @param SmartTagListingType[] $types
     */
    private function reportPosture(array $types, bool $dryRun): void
    {
        $this->info('Smart Tags derivation');
        $this->line('  mode           : ' . ($dryRun ? 'DRY RUN — nothing is written' : 'write'));
        $this->line('  listing types  : ' . implode(', ', array_map(static fn (SmartTagListingType $t) => $t->value, $types)));
        $this->line('  master gate    : ' . (SmartTagWiring::enabled() ? 'ON' : 'OFF'));
        $this->line('  bridge gate    : ' . (SmartTagWiring::bridgeEnabled() ? 'ON' : 'OFF'));
        $this->line('  MLS remarks    : NOT PROCESSED (hard-disabled in code)');

        foreach ($types as $type) {
            if (! SmartTagWiring::enabledFor($type)) {
                $this->warn("  {$type->value}: the activation gates are closed — every row will be reported as failed and nothing will be written.");
            }
        }

        $this->line('');
    }

    private function summarise(bool $dryRun): void
    {
        $this->line('');
        $this->info($dryRun ? 'Dry run complete — no writes were performed.' : 'Run complete.');

        $this->line(sprintf('  %-26s %d', 'considered', $this->counts['considered']));

        foreach (self::OUTCOMES as $key) {
            $this->line(sprintf('  %-26s %d', $key, $this->counts[$key]));
        }

        $this->line('  notes (independent):');

        foreach (self::NOTES as $key) {
            $this->line(sprintf('  %-26s %d', '  ' . $key, $this->counts[$key]));
        }

        if ($this->lastId !== null) {
            $this->line('');
            $this->line("  Resume with: --from-id={$this->lastId}");
        }

        if ($dryRun) {
            // Proof rather than assertion: the tables are counted after the run so
            // an operator can see that a dry run left them alone.
            $this->line('');
            $this->line(sprintf(
                '  Smart Tag rows now: %d evidence, %d assignments, %d states',
                SmartTagEvidence::query()->count(),
                SmartTagAssignment::query()->count(),
                SmartTagDerivationState::query()->count(),
            ));
        }
    }
}
