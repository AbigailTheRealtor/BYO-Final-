<?php

namespace App\Services\SmartTags\Coverage;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\BridgeStructuredTagDeriver;
use App\Services\SmartTags\Seeker\BridgeSmartTagCheckability;
use App\Services\SmartTags\SmartTagResolver;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;
use App\Support\SmartTags\SmartTagVersion;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * How much of the Bridge inventory carries governed Smart Tags — READ ONLY.
 *
 * The question seeker matching needs answered before it is switched on: of the
 * listings a seeker's picks would be compared against, how many have any resolved
 * tag at all? A listing with none reads as UNKNOWN in the scorer
 * ({@see \App\Services\SmartTags\Seeker\ListingSmartTagIndex}), so a mostly-untagged
 * inventory would score every pick as "no information" rather than as a signal.
 *
 * TWO MEASUREMENTS, never blended:
 *   • STORED — what `smart_tag_assignments` and `smart_tag_derivation_states` hold
 *     now. This is what the scorer would read today.
 *   • SIMULATED (opt-in) — what the governed Bridge derivation WOULD resolve, run in
 *     memory through the same deriver and resolver the backfill calls. Nothing is
 *     written: this class holds no writer, projector or state saver, and on
 *     PostgreSQL the whole audit runs inside a transaction declared READ ONLY, so
 *     the server refuses a write even if one were attempted.
 *
 * WHY A LISTING HAS NO TAG is reported, never assumed. Zero present tags is a
 * legitimate answer for a property whose structured facts assert nothing the
 * taxonomy names; it is a different finding from "never derived".
 *
 * BOUNDED. Walked by primary key with chunkById(); per batch it issues one row
 * query plus one assignment and one state query (none in simulate mode for rows
 * without a stored state), so the query count grows with batches, not listings.
 * Aggregates are counters and a histogram, so memory does not grow with the corpus.
 *
 * MLS PublicRemarks are not read: the deriver reads structured fields only.
 */
final class BridgeSmartTagCoverageAuditor
{
    public const REASON_UNSUPPORTED_PROPERTY_TYPE = 'unsupported_property_type';
    public const REASON_NEVER_DERIVED = 'never_derived';
    public const REASON_STALE_DERIVATION = 'stale_derivation';
    public const REASON_DERIVED_NO_PRESENT_TAG = 'derived_no_present_tag';
    public const REASON_SOURCE_UNAVAILABLE = 'source_unavailable';
    public const REASON_NO_DERIVABLE_EVIDENCE = 'no_derivable_evidence';
    public const REASON_ONLY_ABSENT_EVIDENCE = 'only_absent_evidence';
    public const REASON_DERIVATION_FAILED = 'derivation_failed';

    public function __construct(private readonly BridgeStructuredTagDeriver $deriver)
    {
    }

    /**
     * @param string[] $statuses standard_status values; empty = every status
     * @return array<string, mixed>
     */
    public function audit(?string $provider = null, array $statuses = [], bool $simulate = false, int $batchSize = 500): array
    {
        $started = microtime(true);
        $memoryBefore = memory_get_usage(true);
        $queries = 0;
        $counting = true;

        // Listeners cannot be removed in Laravel 8, so this one switches itself off.
        DB::listen(static function () use (&$queries, &$counting) {
            if ($counting) {
                $queries++;
            }
        });

        $connection = DB::connection((new BridgeProperty())->getConnectionName());

        try {
            $report = $connection->transaction(function () use ($connection, $provider, $statuses, $simulate, $batchSize) {
                if ($connection->getDriverName() === 'pgsql') {
                    $connection->statement('SET TRANSACTION READ ONLY');
                }

                return $this->walk($provider, $statuses, $simulate, max(1, $batchSize));
            });
        } finally {
            $counting = false;
        }

        $elapsed = microtime(true) - $started;

        $report['performance'] = [
            'batch_size'          => max(1, $batchSize),
            'batches'             => $report['performance']['batches'],
            'queries'             => $queries,
            'seconds'             => round($elapsed, 3),
            'listings_per_second' => $elapsed > 0 ? round($report['scope']['listings'] / $elapsed, 1) : null,
            'memory_growth_bytes' => max(0, memory_get_usage(true) - $memoryBefore),
            'peak_memory_bytes'   => memory_get_peak_usage(true),
        ];

        return $report;
    }

    /**
     * @param string[] $statuses
     * @return array<string, mixed>
     */
    private function walk(?string $provider, array $statuses, bool $simulate, int $batchSize): array
    {
        $version = SmartTagVersion::taggerVersion();
        $seekerByContext = $this->seekerDerivableByContext();
        $capability = $this->structuredCapabilityByContext();
        $checks = [];

        $stored = $this->emptyTally();
        $simulated = $simulate ? $this->emptyTally() : null;
        $contextCounts = [];
        $batches = 0;

        $query = BridgeProperty::query();

        if (! $simulate) {
            // raw_json is the bulk of a row and only the simulation needs it.
            $query->select(['id', 'provider', 'property_type', 'standard_status']);
        }

        if ($provider !== null) {
            $query->where('provider', $provider);
        }

        if ($statuses !== []) {
            $query->whereIn('standard_status', $statuses);
        }

        $query->orderBy('id')->chunkById($batchSize, function ($rows) use (
            $version, $seekerByContext, $capability, $simulate, &$stored, &$simulated, &$contextCounts, &$batches, &$checks
        ) {
            $batches++;
            $ids = $rows->modelKeys();

            $assignments = [];
            foreach (SmartTagAssignment::query()
                ->where('listing_type', SmartTagListingType::Bridge->value)
                ->whereIn('listing_id', $ids)
                ->get(['listing_id', 'tag_key', 'state']) as $row) {
                $assignments[(int) $row->listing_id][(string) $row->tag_key] = (string) $row->state;
            }

            $states = SmartTagDerivationState::query()
                ->where('listing_type', SmartTagListingType::Bridge->value)
                ->whereIn('listing_id', $ids)
                ->get(['listing_id', 'context', 'tagger_version'])
                ->keyBy('listing_id');

            foreach ($rows as $property) {
                $record = BridgeRecordAccessor::fromModel($property);
                $context = $this->deriver->contextFor($record);

                if ($context !== null) {
                    $contextCounts[$context->value] = ($contextCounts[$context->value] ?? 0) + 1;
                }

                $dimensions = [
                    'property_type' => (string) ($property->property_type ?? '(none)'),
                    'status'        => (string) ($property->standard_status ?? '(none)'),
                    'provider'      => (string) ($property->provider ?? '(none)'),
                ];

                // STORED
                $listingAssignments = $assignments[(int) $property->id] ?? [];
                $present = array_keys(array_filter($listingAssignments, static fn (string $s) => $s === SmartTagState::Present->value));
                $state = $states->get($property->id);

                $current = $context !== null && $state !== null
                    && $state->tagger_version === $version
                    && $state->context === $context->value;

                $reason = match (true) {
                    $context === null => self::REASON_UNSUPPORTED_PROPERTY_TYPE,
                    $present !== []   => null,
                    $state === null   => self::REASON_NEVER_DERIVED,
                    ! $current        => self::REASON_STALE_DERIVATION,
                    default           => self::REASON_DERIVED_NO_PRESENT_TAG,
                };

                // A listing whose tags predate the current tagger still HAS tags the
                // scorer would read: covered, and flagged separately — never uncovered.
                $this->record($stored, $dimensions, $context, $present, count($listingAssignments) - count($present),
                    $reason, $seekerByContext, $present !== [] && ! $current);

                if (! $simulate) {
                    continue;
                }

                // SIMULATED — the governed derivation, in memory.
                if ($context === null) {
                    $this->record($simulated, $dimensions, null, [], 0, self::REASON_UNSUPPORTED_PROPERTY_TYPE, $seekerByContext);

                    continue;
                }

                if ($record->raw() === []) {
                    $this->record($simulated, $dimensions, $context, [], 0, self::REASON_SOURCE_UNAVAILABLE, $seekerByContext);
                    $this->recordChecks($checks, $record, $context, $capability[$context->value]['checkable'], new \App\Services\SmartTags\SmartTagResolution([], []));

                    continue;
                }

                try {
                    $evidence = array_values($this->deriver->derive($record, $context));
                    $resolution = SmartTagResolver::resolve($evidence, $context);
                } catch (Throwable $e) {
                    $simulated['failure_classes'][$e::class] = ($simulated['failure_classes'][$e::class] ?? 0) + 1;
                    $this->record($simulated, $dimensions, $context, [], 0, self::REASON_DERIVATION_FAILED, $seekerByContext);

                    continue;
                }

                $simPresent = $resolution->presentKeys();
                $simAbsent = count($resolution->absentKeys());

                $simReason = match (true) {
                    $simPresent !== []  => null,
                    $evidence === []    => self::REASON_NO_DERIVABLE_EVIDENCE,
                    default             => self::REASON_ONLY_ABSENT_EVIDENCE,
                };

                $this->record($simulated, $dimensions, $context, $simPresent, $simAbsent, $simReason, $seekerByContext);

                // Per listing × structured-checkable seeker tag: what matching would answer.
                $this->recordChecks($checks, $record, $context, $capability[$context->value]['checkable'], $resolution);
            }
        });

        return [
            'scope' => [
                'listing_type'   => SmartTagListingType::Bridge->value,
                'provider'       => $provider,
                'statuses'       => $statuses,
                'listings'       => $stored['listings'],
                'tagger_version' => $version,
                'simulated'      => $simulate,
            ],
            'contexts'                      => $contextCounts,
            'seeker_derivable_tags_by_context' => array_map('count', $seekerByContext),
            'stored'                        => $this->finish($stored, $contextCounts, $seekerByContext),
            'simulated'                     => $simulated === null ? null : $this->finish($simulated, $contextCounts, $seekerByContext),
            'checkability'                  => $this->finishCheckability($capability, $checks, $simulate),
            'performance'                   => ['batches' => $batches],
        ];
    }

    /**
     * Per context, the active seeker-selectable tags a Bridge row can EVER carry:
     * mls_derivable, active, not pending review, applicable to that context.
     *
     * A seeker-selectable tag outside this set (natural_light, for instance) is
     * structurally invisible on MLS rows, whatever a backfill does.
     *
     * @return array<string, array<string, true>>
     */
    private function seekerDerivableByContext(): array
    {
        $out = [];

        foreach (SmartTagContext::cases() as $context) {
            $out[$context->value] = [];

            foreach (SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_SEEKER) as $key => $definition) {
                if ($definition->mlsDerivable) {
                    $out[$context->value][$key] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Per context, the seeker-selectable tags split by GOVERNED STRUCTURED CAPABILITY: whether a
     * `bridge.rules` entry can emit the tag there ({@see BridgeSmartTagCheckability::rulesFor()}).
     * Decided from the rules alone — never from how often a tag occurs — so a supported tag with
     * zero rows today is still checkable, and a tag with no rule is not, whatever its count.
     *
     * @return array<string, array{applicable: list<string>, checkable: list<string>, uncheckable: list<string>, presence_only: list<string>}>
     */
    private function structuredCapabilityByContext(): array
    {
        $out = [];

        foreach (SmartTagContext::cases() as $context) {
            $applicable = array_keys(SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_SEEKER));
            sort($applicable);
            $checkable = array_values(array_filter($applicable, static fn (string $key) => BridgeSmartTagCheckability::hasStructuredCapability($key, $context)));

            $out[$context->value] = [
                'applicable'  => $applicable,
                'checkable'   => $checkable,
                'uncheckable' => array_values(array_diff($applicable, $checkable)),
                // Structured, but no governed rule may ever say "no": present or unknown only.
                'presence_only' => array_values(array_filter($checkable, static fn (string $key) => ! BridgeSmartTagCheckability::hasNegativeCapability($key, $context))),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, array<string, int>> $checks
     * @param list<string>                      $keys
     */
    private function recordChecks(array &$checks, BridgeRecordAccessor $record, SmartTagContext $context, array $keys, \App\Services\SmartTags\SmartTagResolution $resolution): void
    {
        $resolved = [];
        foreach ($resolution->assignments as $key => $assignment) {
            $resolved[$key] = $assignment->state;
        }

        $dropped = array_fill_keys($resolution->droppedForConflict, true);
        $answers = BridgeSmartTagCheckability::classify($record, $context, $keys, $resolved, $dropped, true);

        $checks[$context->value] ??= [
            'counts'       => self::emptyChecks(),
            'present_tags' => [],
        ];
        $bucket = &$checks[$context->value]['counts'];

        foreach ($answers as $key => $answer) {
            if ($answer === BridgeSmartTagCheckability::PRESENT) {
                $bucket['present']++;
                $checks[$context->value]['present_tags'][$key] = true;
            } elseif ($answer === BridgeSmartTagCheckability::KNOWN_ABSENT) {
                $bucket['known_non_match']++;
            } else {
                // WHY it is unknown, from the same decision matching makes — so "field
                // unavailable" stays exact and a field that cannot say no is not filed as one.
                $why = BridgeSmartTagCheckability::explain($record, $context, $key, $resolved, $dropped, true);
                $bucket[self::UNKNOWN_BUCKETS[$why] ?? 'unknown_other']++;
            }
        }
    }

    /**
     * Unknown reasons ({@see BridgeSmartTagCheckability::explain()}) → report bucket.
     * Anything else (a conflict) is `unknown_other`.
     */
    private const UNKNOWN_BUCKETS = [
        BridgeSmartTagCheckability::WHY_FIELD_UNAVAILABLE    => 'source_field_unavailable',
        BridgeSmartTagCheckability::WHY_NO_NEGATIVE_EVIDENCE => 'field_cannot_say_no',
        BridgeSmartTagCheckability::WHY_UNINFORMATIVE        => 'uninformative_only',
        BridgeSmartTagCheckability::WHY_MASKED               => 'masked_by_generic_value',
    ];

    /** @return array<string, int> */
    private static function emptyChecks(): array
    {
        return [
            'present' => 0, 'known_non_match' => 0, 'source_field_unavailable' => 0,
            'field_cannot_say_no' => 0, 'uninformative_only' => 0, 'masked_by_generic_value' => 0, 'unknown_other' => 0,
        ];
    }

    /**
     * @param array<string, array{applicable: list<string>, checkable: list<string>, uncheckable: list<string>}> $capability
     * @param array<string, array{counts: array<string, int>, present_tags: array<string, true>}> $checks
     * @return array<string, mixed>
     */
    private function finishCheckability(array $capability, array $checks, bool $simulate): array
    {
        $byContext = [];

        foreach ($capability as $context => $tags) {
            $row = [
                'seeker_tags_applicable'         => count($tags['applicable']),
                'seeker_tags_structured'         => count($tags['checkable']),
                'seeker_tags_not_structured'     => count($tags['uncheckable']),
                'not_structured_tags'            => $tags['uncheckable'],
                'seeker_tags_negative_checkable' => count($tags['checkable']) - count($tags['presence_only']),
                'presence_only_tags'             => $tags['presence_only'],
            ];

            if ($simulate) {
                // Present on at least one listing IN THIS CONTEXT.
                $present = array_values(array_filter($tags['checkable'], static fn (string $key) => isset($checks[$context]['present_tags'][$key])));
                $row['structured_with_present']  = count($present);
                $row['structured_zero_present']  = count($tags['checkable']) - count($present);
                $row['listing_tag_checks'] = $checks[$context]['counts'] ?? self::emptyChecks();
            }

            $byContext[$context] = $row;
        }

        return ['simulated' => $simulate, 'by_context' => $byContext];
    }

    /** @return array<string, mixed> */
    private function emptyTally(): array
    {
        return [
            'listings'          => 0,
            'covered'           => 0,
            'seeker_covered'    => 0,
            'stale_but_covered' => 0,
            'present_total'     => 0,
            'absent_total'      => 0,
            'histogram'         => [],
            'reasons'           => [],
            'by'                => ['property_type' => [], 'status' => [], 'provider' => [], 'context' => []],
            'tags'              => [],
            'seeker_tags'       => [],
            'failure_classes'   => [],
        ];
    }

    /**
     * @param array<string, mixed>               $tally
     * @param array<string, string>              $dimensions
     * @param string[]                           $present
     * @param array<string, array<string, true>> $seekerByContext
     */
    private function record(
        array &$tally,
        array $dimensions,
        ?SmartTagContext $context,
        array $present,
        int $absent,
        ?string $reason,
        array $seekerByContext,
        bool $stale = false,
    ): void {
        $covered = $present !== [];
        $seekerPresent = $context === null ? [] : array_values(array_filter(
            $present,
            static fn (string $key) => isset($seekerByContext[$context->value][$key]),
        ));

        $tally['listings']++;
        $tally['covered'] += $covered ? 1 : 0;
        $tally['seeker_covered'] += $seekerPresent !== [] ? 1 : 0;
        $tally['stale_but_covered'] += $stale ? 1 : 0;
        $tally['present_total'] += count($present);
        $tally['absent_total'] += $absent;
        $tally['histogram'][count($present)] = ($tally['histogram'][count($present)] ?? 0) + 1;

        if ($reason !== null) {
            $tally['reasons'][$reason] = ($tally['reasons'][$reason] ?? 0) + 1;
        }

        $dimensions['context'] = $context?->value ?? '(unsupported)';

        foreach ($dimensions as $dimension => $value) {
            $bucket = &$tally['by'][$dimension][$value];
            $bucket ??= ['listings' => 0, 'covered' => 0, 'seeker_covered' => 0];
            $bucket['listings']++;
            $bucket['covered'] += $covered ? 1 : 0;
            $bucket['seeker_covered'] += $seekerPresent !== [] ? 1 : 0;
            unset($bucket);
        }

        foreach ($present as $key) {
            $tally['tags'][$key] = ($tally['tags'][$key] ?? 0) + 1;
        }

        foreach ($seekerPresent as $key) {
            $tally['seeker_tags'][$key] = ($tally['seeker_tags'][$key] ?? 0) + 1;
        }
    }

    /**
     * @param array<string, mixed>               $tally
     * @param array<string, int>                 $contextCounts
     * @param array<string, array<string, true>> $seekerByContext
     * @return array<string, mixed>
     */
    private function finish(array $tally, array $contextCounts, array $seekerByContext): array
    {
        $listings = $tally['listings'];

        foreach ($tally['by'] as $dimension => $buckets) {
            ksort($buckets);
            foreach ($buckets as $value => $bucket) {
                $buckets[$value]['coverage_pct'] = self::pct($bucket['covered'], $bucket['listings']);
                $buckets[$value]['seeker_coverage_pct'] = self::pct($bucket['seeker_covered'], $bucket['listings']);
            }
            $tally['by'][$dimension] = $buckets;
        }

        arsort($tally['tags']);
        arsort($tally['seeker_tags']);
        ksort($tally['histogram']);
        ksort($tally['reasons']);

        // Every seeker-derivable tag, with how many in-scope listings it COULD apply
        // to (its contexts) and how many carry it — zero-coverage tags included.
        $seekerTagCoverage = [];
        foreach ($seekerByContext as $context => $keys) {
            foreach (array_keys($keys) as $key) {
                $seekerTagCoverage[$key]['eligible_listings'] = ($seekerTagCoverage[$key]['eligible_listings'] ?? 0) + ($contextCounts[$context] ?? 0);
            }
        }
        foreach ($seekerTagCoverage as $key => $row) {
            $count = $tally['seeker_tags'][$key] ?? 0;
            $seekerTagCoverage[$key]['listings'] = $count;
            $seekerTagCoverage[$key]['pct_of_eligible'] = self::pct($count, $row['eligible_listings']);
        }
        uasort($seekerTagCoverage, static fn (array $a, array $b) => [$a['listings'], $b['eligible_listings']] <=> [$b['listings'], $a['eligible_listings']]);

        return [
            'listings'                 => $listings,
            'covered'                  => $tally['covered'],
            'uncovered'                => $listings - $tally['covered'],
            'coverage_pct'             => self::pct($tally['covered'], $listings),
            'seeker_covered'           => $tally['seeker_covered'],
            'seeker_coverage_pct'      => self::pct($tally['seeker_covered'], $listings),
            'stale_but_covered'        => $tally['stale_but_covered'],
            'present_tags_mean'        => $listings > 0 ? round($tally['present_total'] / $listings, 2) : 0.0,
            'present_tags_median'      => self::median($tally['histogram'], $listings),
            'absent_assertions_total'  => $tally['absent_total'],
            'present_tag_histogram'    => $tally['histogram'],
            'uncovered_reasons'        => $tally['reasons'],
            'by'                       => $tally['by'],
            'tag_counts'               => $tally['tags'],
            'seeker_tag_counts'        => $tally['seeker_tags'],
            'seeker_tag_coverage'      => $seekerTagCoverage,
            'failure_classes'          => $tally['failure_classes'],
        ];
    }

    private static function pct(int $part, int $whole): float
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) : 0.0;
    }

    /** @param array<int, int> $histogram present-tag count => listings */
    private static function median(array $histogram, int $listings): float
    {
        if ($listings === 0) {
            return 0.0;
        }

        ksort($histogram);
        $lowRank = intdiv($listings - 1, 2);
        $highRank = intdiv($listings, 2);
        $seen = 0;
        $low = $high = null;

        foreach ($histogram as $value => $count) {
            $seen += $count;
            if ($low === null && $seen > $lowRank) {
                $low = $value;
            }
            if ($seen > $highRank) {
                $high = $value;
                break;
            }
        }

        return ($low + $high) / 2;
    }
}
