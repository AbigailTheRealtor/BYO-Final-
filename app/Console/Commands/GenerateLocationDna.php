<?php

namespace App\Console\Commands;

use App\Models\BridgeProperty;
use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use App\Support\LocationDna\LocationDataAttribution;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Run the Location DNA pipeline for exactly one listing.
 *
 * THE BRIDGE TYPE IS A CANARY, NOT A THIRD LISTING TYPE.
 * `LocationDnaPipelineRunner` has resolved `bridge` addresses since the Bridge
 * import shipped; this command simply refused to pass the string through. Adding
 * it here is what makes a single imported MLS record usable as the first real
 * property to run the pipeline under a new provider configuration — one address,
 * one operator, one observable result — before anything is run in bulk.
 *
 * Because that is its purpose, `bridge` is gated behind an explicit `--canary`
 * flag while `seller` and `landlord` are not. A canary that can be started by
 * typing the wrong word is not a canary, and the Bridge table is the one whose
 * ids are external MLS records rather than our users' own listings.
 */
class GenerateLocationDna extends Command
{
    /**
     * `--dry-run` reports what would happen and writes nothing. It exists because
     * the interesting question before a canary is not "what are this home's POIs"
     * but "which provider is about to answer, and are we allowed to publish what
     * it returns" — and that is answerable without spending a request or touching
     * a row.
     */
    protected $signature = 'location-dna:generate
                            {listing_type : seller, landlord, or bridge}
                            {listing_id : Primary key of the single listing to process}
                            {--canary : Required for listing_type=bridge. Acknowledges this is a single-listing canary run.}
                            {--dry-run : Report the resolved target and provider posture, then exit without running the pipeline or writing anything.}';

    protected $description = 'Run the full Location DNA pipeline for a single seller, landlord, or bridge listing';

    /** Listing types this command will dispatch. */
    private const TYPES = ['seller', 'landlord', 'bridge'];

    /** Types that require --canary before anything runs. */
    private const CANARY_TYPES = ['bridge'];

    public function handle(LocationDnaPipelineRunner $runner): int
    {
        $listingType = (string) $this->argument('listing_type');
        $listingIdRaw = (string) $this->argument('listing_id');

        if (! in_array($listingType, self::TYPES, true)) {
            $this->error("Invalid listing_type '{$listingType}'. Must be one of: " . implode(', ', self::TYPES) . '.');
            return Command::FAILURE;
        }

        // A non-numeric or non-positive id is rejected rather than cast, because
        // (int) 'all' is 0 and (int) '12,13' is 12 — both of which would look like
        // a successful single-listing run against the wrong record.
        if (! preg_match('/^[1-9][0-9]*$/', $listingIdRaw)) {
            $this->error("Invalid listing_id '{$listingIdRaw}'. Must be a single positive integer — this command processes exactly one listing.");
            return Command::FAILURE;
        }

        $listingId = (int) $listingIdRaw;

        if (in_array($listingType, self::CANARY_TYPES, true) && ! $this->option('canary')) {
            $this->error("listing_type '{$listingType}' requires --canary.");
            $this->line('  A bridge record is an imported MLS listing, and running the pipeline against one');
            $this->line('  is a deliberate single-listing canary. Re-run with --canary to confirm, or add');
            $this->line('  --dry-run to see what would happen without writing anything.');
            return Command::FAILURE;
        }

        // Posture BEFORE resolution, deliberately. It describes the environment, not
        // the record, and an operator who mistyped an id is still owed the answer to
        // "which provider would have answered" — that is the question a canary is run
        // to settle, and withholding it until a lookup succeeds helps no one.
        $this->reportPosture($listingType, $listingId);

        $listing = $this->resolveListing($listingType, $listingId);

        if ($listing === false) {
            return Command::FAILURE; // resolveListing already reported why
        }

        if ($listing === null) {
            $this->error("No {$listingType} listing found with ID {$listingId}.");
            return Command::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info("DRY RUN — nothing was written. Would run the Location DNA pipeline for {$listingType} listing #{$listingId}.");
            return Command::SUCCESS;
        }

        $this->info("Running Location DNA pipeline for {$listingType} listing #{$listingId}...");

        $result = $runner->run($listingType, $listingId);

        $this->line('Pipeline status: ' . $result['status']);

        foreach ($result['steps'] ?? [] as $step => $stepResult) {
            $status = $stepResult['status'] ?? 'unknown';
            $error  = $stepResult['error'] ?? null;
            $line   = "  [{$step}] {$status}";
            if ($error) {
                $line .= " — {$error}";
            }
            $this->line($line);
        }

        if (isset($result['error'])) {
            $this->error('Pipeline exception: ' . $result['error']);
        }

        return $result['status'] === 'success' ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Find the one listing this run is about.
     *
     * @return object|null|false  the model, null when absent, false when the
     *                            environment cannot answer (already reported).
     */
    private function resolveListing(string $listingType, int $listingId): object|null|false
    {
        if ($listingType === 'landlord') {
            if (! Schema::hasTable('landlord_auctions')) {
                $this->error('Landlord listing table not available in this environment.');
                return false;
            }

            try {
                return LandlordAuction::find($listingId);
            } catch (QueryException $e) {
                $this->error('Could not query landlord listing: ' . $e->getMessage());
                return false;
            }
        }

        if ($listingType === 'bridge') {
            if (! Schema::hasTable('bridge_properties')) {
                $this->error('Bridge property table not available in this environment.');
                return false;
            }

            try {
                return BridgeProperty::find($listingId);
            } catch (QueryException $e) {
                $this->error('Could not query bridge listing: ' . $e->getMessage());
                return false;
            }
        }

        return PropertyAuction::find($listingId);
    }

    /**
     * Print the provider and licensing posture this run would execute under.
     *
     * This is the part of the command a canary is actually for. Which adapter
     * answers is decided by two config files in two places, and whether we may
     * PUBLISH what it returns is decided by a third — so an operator staring at a
     * successful run has no way, from the result alone, to tell whether the rows
     * came from the corpus or from Google, or whether showing them is licensed.
     * Stating it before the run is what makes the outcome interpretable.
     *
     * Read-only: reports, never enforces. Nothing here can enable or disable a
     * provider, and an outstanding NOTICE obligation is a warning to a human
     * rather than a refusal, because this command does not publish anything —
     * the surfaces that render POIs do.
     */
    private function reportPosture(string $listingType, int $listingId): void
    {
        $corpusEnabled = (bool) config('overture_corpus_poi.enabled', false);
        $corpusVersion = config('overture_corpus_poi.corpus_version');
        $registryOn    = (bool) config('location_providers.providers.overture_corpus.enabled', false);

        $this->line('Target:          ' . $listingType . ' #' . $listingId . ' (exactly one listing)');
        $this->line('Overture corpus: adapter=' . ($corpusEnabled ? 'ENABLED' : 'disabled')
            . ', registry=' . ($registryOn ? 'ENABLED' : 'disabled')
            . ', version=' . ($corpusVersion ?: '(unpinned)'));

        $outstanding = LocationDataAttribution::noticeObligationsOutstanding();

        if ($outstanding !== []) {
            $names = implode(', ', array_map(static fn (array $s): string => (string) ($s['name'] ?? $s['id']), $outstanding));
            $this->warn('Attribution:     OUTSTANDING NOTICE OBLIGATION — ' . $names);
            $this->line('                 Publishing POIs from the affected source is blocked until the upstream');
            $this->line('                 NOTICE is committed and notice_verified is set in config/location_attribution.php.');
        } else {
            $this->line('Attribution:     all NOTICE obligations satisfied.');

            // Said out loud, because this is the moment the mistake gets made. An
            // operator who has just watched the licensing prerequisite clear is the
            // most likely person to read a green line as permission to serve.
            if (! $corpusEnabled || ! $registryOn) {
                $this->line('                 This is NOT activation authorization — the corpus provider is still');
                $this->line('                 disabled and is governed by its own two gates.');
            }
        }

        // The existing row count for THIS listing, so a re-run's idempotency is
        // observable rather than assumed. The pipeline replaces a listing's POI
        // rows in place; a second run should not grow this number.
        try {
            $existing = PropertyLocationPoi::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->count();
            $this->line('Existing POIs:   ' . $existing . ' row(s) for this listing (a re-run replaces them, it does not append)');
        } catch (QueryException) {
            // Reporting only. An unavailable table must not stop the run.
        }
    }
}
