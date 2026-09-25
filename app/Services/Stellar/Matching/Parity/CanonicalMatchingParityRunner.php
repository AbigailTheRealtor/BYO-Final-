<?php

namespace App\Services\Stellar\Matching\Parity;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeListingMatchFactsBuilder;
use App\Services\Bridge\BridgeResidualMatchFactsReader;
use App\Services\Bridge\MlsCanonicalListingResolver;
use App\Services\Canonical\Adapters\MlsListingAdapter;
use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\SmartTags\Seeker\ListingSmartTagIndex;
use App\Services\Stellar\MatchCheck\ListingVisibilityGate;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\CanonicalListingMatchFactsBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Services\Stellar\Matching\ListingMatchResidualFacts;
use App\Support\Listing\MlsProvider;
use App\Support\SmartTags\SmartTagSeekerPreferenceGate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * P1-B2 — the OFFLINE canonical matching parity runner.
 *
 *   facts A  BridgeProperty → BridgeListingMatchFactsBuilder                        (the live path)
 *   facts B  BridgeProperty → MlsCanonicalListingResolver::forBridgeProperty() → CanonicalListing ┐
 *            BridgeProperty → BridgeResidualMatchFactsReader → ListingMatchResidualFacts ─────────┴→ CanonicalListingMatchFactsBuilder
 *
 * over locally stored `bridge_properties` rows, compared at four levels — facts, scores,
 * explanations (plus exceptions), and scores after attaching the SAME resolved Smart Tags —
 * with a ranking comparison over cohort criteria. It OBSERVES and REPORTS; nothing it
 * computes reaches a user, a live result, a table or a provider.
 *
 * READ-ONLY, LOCAL, EXPLICIT:
 *  - reads `bridge_properties` (one query per chunk) and, only when seeker Smart Tag
 *    matching is already on, `smart_tag_assignments` through the live batched index;
 *  - writes nothing, dispatches nothing, calls no provider/HTTP client, sets no config;
 *  - never consults CanonicalListingResolver (whose supports() gates the DNA chain) — the
 *    explicit MLS resolver is called directly;
 *  - invoked only by `php artisan matching:canonical-parity`, which refuses production.
 * Guard tests assert every one of those (CanonicalParityArchitectureGuardTest,
 * CanonicalMatchingParityReadOnlyTest).
 */
final class CanonicalMatchingParityRunner
{
    public const STATUS_EXACT          = 'EXACT';
    public const STATUS_ALLOWED        = 'ALLOWED_DIFFERENCE';
    public const STATUS_UNDECLARED     = 'UNDECLARED_DIFFERENCE';
    public const STATUS_UNRESOLVABLE   = 'CANONICAL_UNRESOLVABLE';
    public const STATUS_ERROR_PARITY   = 'ERROR_PARITY';
    public const STATUS_ERROR_MISMATCH = 'ERROR_MISMATCH';

    /** Worst wins. CANONICAL_UNRESOLVABLE is listing-level only (such a listing has no cases). */
    public const SEVERITY = [
        self::STATUS_EXACT          => 0,
        self::STATUS_ALLOWED        => 1,
        self::STATUS_ERROR_PARITY   => 2,
        self::STATUS_UNRESOLVABLE   => 3,
        self::STATUS_UNDECLARED     => 4,
        self::STATUS_ERROR_MISMATCH => 5,
    ];

    /**
     * The seven reporting strata: the primary platform types, exactly as
     * PropertyTypeVocabulary::recognisedTypeFor() returns them (a test pins each as its
     * fixed point, so this is the governed table's own answer, not a parallel vocabulary).
     */
    public const STRATA = [
        'Residential', 'Residential Lease', 'Income', 'Commercial Sale',
        'Commercial Lease', 'Business Opportunity', 'Vacant Land',
    ];
    public const OTHER_TYPE = 'other_type';

    public const CHUNK                = 200;
    public const DEFAULT_MAX_LISTINGS = 2000;
    public const HARD_MAX_LISTINGS    = 5000;
    public const MAX_STRIDE           = 1000;
    public const MAX_EXAMPLES         = 50;
    public const MAX_LISTING_KEY_LEN  = 64;

    /** The diagnostic budget: canonical path cost ≤ legacy cost + 50% (plan §22). */
    public const COST_BUDGET_RATIO = 1.5;

    /** Reported by value SHAPE only — never the value (plan §31.12). */
    public const LOCATION_FIELDS = ['listingKey', 'latitude', 'longitude', 'city', 'stateOrProvince', 'postalCode', 'countyOrParish'];

    public const EXPLANATION_KEYS = ['why_this_matches', 'tradeoffs', 'caution_flags', 'missing_data', 'why_not', 'confidence', 'recommendations'];

    private const SMART_TAG_CASE = 'smart_tags_picks';

    /** Seeker Smart Tag picks for the post-attachment comparison (the P1-B test's own pair). */
    public const SMART_TAG_PICKS = ['updated_kitchen', 'quartz_countertops'];

    // ── per-run state (reset by run()) ─────────────────────────────────────────
    private array $sel = [];
    private array $resolution = [];
    private array $facts = [];
    private array $outcomes = [];
    private array $listings = [];
    private array $smart = [];
    private array $diagnostics = [];
    private array $criteria = [];
    private array $examples = [];
    private array $cohortRows = [];
    private array $cost = [];
    private int $exampleCap = 5;
    private array $tagKeys = [];
    private bool $stoppedAtMax = false;

    public function __construct(
        private readonly ListingVisibilityGate $gate = new ListingVisibilityGate(),
        private readonly MlsCanonicalListingResolver $resolver = new MlsCanonicalListingResolver(),
        private readonly CanonicalParityCriteriaMatrix $matrix = new CanonicalParityCriteriaMatrix(),
    ) {}

    // ── the shared P1-B harness (the test trait delegates here) ────────────────

    public static function legacyFacts(BridgeProperty $row): ListingMatchFacts
    {
        return BridgeListingMatchFactsBuilder::build($row);
    }

    /** Facts B, or null when the row has no canonical listing (not comparable). */
    public static function canonicalFacts(BridgeProperty $row): ?ListingMatchFacts
    {
        $canonical = (new MlsCanonicalListingResolver())->forBridgeProperty($row);

        if ($canonical === null) {
            return null;
        }

        return CanonicalListingMatchFactsBuilder::build($canonical, BridgeResidualMatchFactsReader::read($row));
    }

    /**
     * Everything the live paths expose about one facts object scored against one criteria
     * set, through the UNCHANGED scorer and result builder (the P1-B outcome, verbatim).
     *
     * @return array<string,mixed>
     */
    public static function outcome(ListingMatchFacts $facts, BridgeProperty $row, BuyerCriteriaPayload $criteria): array
    {
        try {
            return self::project($facts, $row, $criteria);
        } catch (Throwable $e) {
            return ['exception' => get_class($e)];
        }
    }

    /** @return list<string> the outcome keys whose values differ. */
    public static function outcomeDifferences(array $a, array $b): array
    {
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));

        return array_values(array_filter($keys, static fn (string $k): bool => ($a[$k] ?? null) !== ($b[$k] ?? null)));
    }

    /** The criteria for the post-attachment comparison. @return array<string,mixed> */
    public static function smartTagCase(ListingMatchFacts $a): array
    {
        return ['property_types' => [$a->propertyType], 'preferred_cities' => [$a->city], 'seeker_smart_tags' => self::SMART_TAG_PICKS];
    }

    /** One line describing whether the post-attachment level ran, for the console. */
    public static function postAttachmentPosture(array $result): string
    {
        $s = $result['smart_tags'] ?? [];

        return ($s['state'] ?? 'NOT_EXERCISED') . (empty($s['reason']) ? '' : ' — ' . $s['reason']);
    }

    /** The row's reporting stratum: its stored type when that is a primary type, else other_type. */
    public static function stratumFor(?string $propertyType): string
    {
        return in_array($propertyType, self::STRATA, true) ? $propertyType : self::OTHER_TYPE;
    }

    // ── the offline run ────────────────────────────────────────────────────────

    /**
     * @param list<string> $listingKeys exact ListingKeys (current provider); non-empty = targeted mode
     */
    public function run(
        array $listingKeys = [],
        int $maxListings = self::DEFAULT_MAX_LISTINGS,
        int $perType = 0,
        int $stride = 1,
        int $fromId = 0,
        int $examplesPerBucket = 5,
    ): CanonicalParityReport {
        $listingKeys = self::validateOptions($listingKeys, $maxListings, $perType, $stride, $fromId, $examplesPerBucket);

        $this->reset($examplesPerBucket);

        $queries  = 0;
        $counting = true;
        DB::listen(static function () use (&$queries, &$counting): void {
            if ($counting) {
                $queries++;
            }
        });

        $started = hrtime(true);

        try {
            $this->sel['mode'] = $listingKeys === [] ? 'population' : 'targeted';
            $this->sel['options'] = [
                'max_listings'   => $maxListings,
                'per_type'       => $perType,
                'stride'         => $stride,
                'from_id'        => $fromId,
                'listing_keys'   => count($listingKeys),
                'examples'       => $examplesPerBucket,
            ];

            $this->prepareSmartTags();

            if ($listingKeys === []) {
                BridgeProperty::query()
                    ->where('standard_status', 'Active')
                    ->where('id', '>', $fromId)
                    ->chunkById(self::CHUNK, function (Collection $rows) use ($maxListings, $perType, $stride): bool {
                        return $this->processChunk($rows, $maxListings, $perType, $stride);
                    });
            } else {
                $found = [];
                foreach (array_chunk($listingKeys, 500) as $keys) {
                    BridgeProperty::query()
                        ->where('provider', MlsProvider::current()->value)
                        ->whereIn('listing_key', $keys)
                        ->orderBy('id')
                        ->get()
                        ->each(function (BridgeProperty $r) use (&$found): void {
                            $found[$r->id] = $r;
                        });
                }
                ksort($found);

                $this->sel['not_found'] = count(array_diff($listingKeys, array_map(static fn ($r) => (string) $r->listing_key, $found)));

                foreach (array_chunk(array_values($found), self::CHUNK) as $chunk) {
                    if (!$this->processChunk(collect($chunk), $maxListings, 0, 1)) {
                        break;
                    }
                }
            }

            $this->runRanking();
        } finally {
            $counting = false;
        }

        $elapsedMs = (hrtime(true) - $started) / 1e6;

        return new CanonicalParityReport($this->result(), $this->costBlock($elapsedMs, $queries), [
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'connection'   => (string) config('database.default'),
            'driver'       => (string) DB::connection()->getDriverName(),
            'php'          => PHP_VERSION,
        ]);
    }

    /**
     * Validate options without touching the database. Over-ceiling is REFUSED, never clamped.
     *
     * @return list<string> the normalised, de-duplicated listing keys
     */
    public static function validateOptions(array $listingKeys, int $maxListings, int $perType, int $stride, int $fromId, int $examples): array
    {
        if ($maxListings < 1 || $maxListings > self::HARD_MAX_LISTINGS) {
            throw new InvalidArgumentException(sprintf('max-listings must be between 1 and %d (got %d); larger runs are refused, not clamped.', self::HARD_MAX_LISTINGS, $maxListings));
        }
        if ($perType < 0 || $perType > $maxListings) {
            throw new InvalidArgumentException("per-type must be between 0 (no cap) and max-listings ({$maxListings}) (got {$perType}).");
        }
        if ($stride < 1 || $stride > self::MAX_STRIDE) {
            throw new InvalidArgumentException(sprintf('stride must be between 1 and %d (got %d).', self::MAX_STRIDE, $stride));
        }
        if ($fromId < 0) {
            throw new InvalidArgumentException("from-id must be 0 or a positive row id (got {$fromId}).");
        }
        if ($examples < 0 || $examples > self::MAX_EXAMPLES) {
            throw new InvalidArgumentException(sprintf('examples must be between 0 and %d (got %d).', self::MAX_EXAMPLES, $examples));
        }

        $keys = [];
        foreach ($listingKeys as $key) {
            $key = is_string($key) ? trim($key) : '';
            if ($key === '' || strlen($key) > self::MAX_LISTING_KEY_LEN || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $key)) {
                throw new InvalidArgumentException('listing-key values must be 1-' . self::MAX_LISTING_KEY_LEN . ' characters of letters, digits, ".", "_" or "-".');
            }
            $keys[$key] = $key;
        }

        if (count($keys) > $maxListings) {
            throw new InvalidArgumentException(sprintf('%d listing keys exceed max-listings (%d).', count($keys), $maxListings));
        }
        if ($keys !== [] && ($perType !== 0 || $stride !== 1 || $fromId !== 0)) {
            throw new InvalidArgumentException('listing-key targets exact rows; it cannot be combined with per-type, stride or from-id.');
        }

        return array_values($keys);
    }

    // ── selection ──────────────────────────────────────────────────────────────

    /** @return bool false to stop the scan */
    private function processChunk(Collection $rows, int $maxListings, int $perType, int $stride): bool
    {
        $selected = [];
        $continue = true;

        foreach ($rows as $row) {
            $this->sel['rows_scanned']++;
            $stratum = self::stratumFor($row->property_type);

            if ($row->standard_status !== 'Active') {
                $this->bump($this->sel['excluded'], 'status_not_active');
                continue;
            }

            $decision = $this->gate->decide($row);
            if (!$decision->visible) {
                $this->bump($this->sel['excluded'], 'idx_gate:' . $decision->reason);
                continue;
            }

            $this->sel['eligible']++;
            $seen = $this->sel['eligible_by_stratum'][$stratum] = ($this->sel['eligible_by_stratum'][$stratum] ?? 0) + 1;

            if ($this->sel['examined'] >= $maxListings) {
                $this->stoppedAtMax = true;
                $continue = false;
                break;
            }
            if ($stride > 1 && ($seen - 1) % $stride !== 0) {
                $this->bump($this->sel['excluded'], 'stride_skipped');
                continue;
            }
            if ($perType > 0 && ($this->sel['examined_by_stratum'][$stratum] ?? 0) >= $perType) {
                $this->bump($this->sel['excluded'], 'per_type_cap');
                continue;
            }

            $this->sel['examined']++;
            $this->bump($this->sel['examined_by_stratum'], $stratum);
            $selected[] = [$row, $stratum];
        }

        if ($selected !== []) {
            // One batched Smart Tags read per chunk, only when the comparison is exercised.
            $index = $this->tagKeys === []
                ? null
                : ListingSmartTagIndex::forBridgeRows(array_column($selected, 0), $this->tagKeys);

            foreach ($selected as [$row, $stratum]) {
                $this->examine($row, $stratum, $index);
            }
        }

        return $continue;
    }

    // ── one listing ────────────────────────────────────────────────────────────

    private function examine(BridgeProperty $row, string $stratum, ?ListingSmartTagIndex $index): void
    {
        $id = ['provider' => (string) $row->provider, 'listing_key' => trim((string) $row->listing_key), 'property_type' => (string) $row->property_type, 'stratum' => $stratum];

        // Facts A — the live path.
        $t = hrtime(true);
        [$a, $errA] = self::attempt(static fn () => self::legacyFacts($row));
        $this->cost['legacy_facts_ns'] += hrtime(true) - $t;

        // Facts B — the canonical path, with the reason when it cannot be built.
        $t = hrtime(true);
        $unresolvable = null;
        $residual     = null;
        [$b, $errB] = self::attempt(function () use ($row, &$unresolvable, &$residual) {
            $canonical = $this->resolver->forBridgeProperty($row);
            if ($canonical === null) {
                $unresolvable = $row->mlsProvider() === null ? 'unrecognised_provider'
                    : (trim((string) $row->listing_key) === '' ? 'no_listing_key' : 'resolver_returned_null');

                return null;
            }
            $residual = BridgeResidualMatchFactsReader::read($row);
            $facts    = CanonicalListingMatchFactsBuilder::build($canonical, $residual);
            if ($facts === null) {
                $unresolvable = 'no_native_identity';
            }

            return $facts;
        });
        $this->cost['canonical_facts_ns'] += hrtime(true) - $t;

        $this->recordDiagnostics($stratum, $a, $b, $residual, $index?->factsFor($row));

        if ($errA === null && $errB === null && $unresolvable !== null) {
            $this->resolution['unresolvable']++;
            $this->bump($this->resolution['unresolvable_by_reason'], $unresolvable);
            $this->recordListing($stratum, self::STATUS_UNRESOLVABLE);
            $this->example('canonical_unresolvable', $id + ['reason' => $unresolvable]);

            return;
        }

        if ($errA !== null || $errB !== null) {
            $status = self::errorStatus($errA, $errB);
            $this->recordError('facts', $status, $errA, $errB);
            $this->recordListing($stratum, $status);
            $this->example($status === self::STATUS_ERROR_PARITY ? 'error_parity' : 'error_mismatch', $id + ['level' => 'facts'] + self::errorShape($errA, $errB));

            return;
        }

        $this->resolution['resolved']++;

        // ── L1 facts ──
        [$fired, $factsUndeclared] = $this->compareFacts($a, $b, $stratum, $id);
        $allowedKeys = array_values(array_unique(array_merge([], ...array_map([CanonicalParityAllowedDifferences::class, 'propagates'], array_keys($fired)))));
        $factsStatus = $factsUndeclared ? self::STATUS_UNDECLARED : ($fired !== [] ? self::STATUS_ALLOWED : self::STATUS_EXACT);
        $worst = $factsStatus;

        // ── L2 / L3 over the criteria matrix ──
        foreach ($this->matrix->casesFor($a, $row, $stratum) as $case => $overrides) {
            $status = $this->compareCase($case, $overrides, $a, $b, $row, $stratum, $id, $fired, $allowedKeys, $factsUndeclared);
            if ($status !== null && self::SEVERITY[$status] > self::SEVERITY[$worst]) {
                $worst = $status;
            }
        }

        // ── L4 Smart Tags, after attaching the SAME resolved tags to both ──
        if ($index !== null) {
            $tags = $index->factsFor($row);
            $status = $this->compareCase(self::SMART_TAG_CASE, self::smartTagCase($a), $a->withSmartTags($tags), $b->withSmartTags($tags), $row, $stratum, $id, $fired, $allowedKeys, $factsUndeclared, true);
            if ($status !== null && self::SEVERITY[$status] > self::SEVERITY[$worst]) {
                $worst = $status;
            }
        }

        $this->recordListing($stratum, $worst);
        $this->cohortRows[$stratum][] = ['key' => $id['listing_key'], 'a' => $a, 'b' => $b];
    }

    /** @return array{0: array<string,list<string>>, 1: bool} fired AD id => fields, and whether any difference is undeclared */
    private function compareFacts(ListingMatchFacts $a, ListingMatchFacts $b, string $stratum, array $id): array
    {
        $this->facts['listings_compared']++;
        $this->facts['by_stratum'][$stratum]['compared'] = ($this->facts['by_stratum'][$stratum]['compared'] ?? 0) + 1;

        $fired      = [];
        $undeclared = false;

        // Smart Tags are attached AFTER facts are built; before that both must be null.
        if ($a->smartTags !== null || $b->smartTags !== null) {
            $undeclared = true;
            $this->facts['smart_tags_attached_before_stage']++;
            $this->recordField('smartTags', null, $stratum);
            $this->example('undeclared_facts', $id + ['field' => 'smartTags', 'origin' => self::origin('smartTags'), 'shape' => 'attached_before_stage']);
        }

        foreach (CanonicalParityAllowedDifferences::factsDifferences($a, $b) as $field => $values) {
            $ad = CanonicalParityAllowedDifferences::allowedDifference($field, $values['legacy'], $values['canonical'], $a);
            $this->recordField($field, $ad, $stratum);

            $detail = $id + ['field' => $field, 'origin' => self::origin($field)] + self::valueRepresentation($field, $values['legacy'], $values['canonical'], $a);

            if ($ad === null) {
                $undeclared = true;
                $this->example('undeclared_facts', $detail);
            } else {
                $fired[$ad][] = $field;
                $this->example('allowed_difference:' . $ad, $detail + ['ad' => $ad]);
            }
        }

        foreach ($fired as $ad => $fields) {
            $this->facts['by_ad'][$ad]['listings'] = ($this->facts['by_ad'][$ad]['listings'] ?? 0) + 1;
            $this->bump($this->facts['by_stratum'][$stratum]['ad'], $ad);
            foreach ($fields as $f) {
                $this->bump($this->facts['by_ad'][$ad]['fields'], $f);
            }
        }

        $status = $undeclared ? self::STATUS_UNDECLARED : ($fired !== [] ? self::STATUS_ALLOWED : self::STATUS_EXACT);
        $this->bump($this->facts['status'], $status);
        $this->bump($this->facts['by_stratum'][$stratum]['status'], $status);

        return [$fired, $undeclared];
    }

    /** @return string|null the case status, or null when the criteria cannot be constructed */
    private function compareCase(
        string $case, array $overrides, ListingMatchFacts $a, ListingMatchFacts $b, BridgeProperty $row,
        string $stratum, array $id, array $fired, array $allowedKeys, bool $factsUndeclared, bool $smartTags = false,
    ): ?string {
        try {
            $payload = new BuyerCriteriaPayload(array_merge(['is_55_plus_eligible' => false], $overrides));
        } catch (Throwable) {
            $this->criteria['unbuildable']++;

            return null;
        }

        $this->criteria['cases_by_stratum'][$stratum][$case] = true;

        $t  = hrtime(true);
        $oa = self::evaluate($a, $row, $payload);
        $this->cost['legacy_outcome_ns'] += hrtime(true) - $t;
        $t  = hrtime(true);
        $ob = self::evaluate($b, $row, $payload);
        $this->cost['canonical_outcome_ns'] += hrtime(true) - $t;

        $diff       = [];
        $undeclared = [];

        if (isset($oa['exception']) || isset($ob['exception'])) {
            $status = self::errorStatus(
                isset($oa['exception']) ? [$oa['exception'], $oa['exception_digest']] : null,
                isset($ob['exception']) ? [$ob['exception'], $ob['exception_digest']] : null,
            );
            $this->recordError($smartTags ? 'smart_tags' : 'outcome', $status,
                isset($oa['exception']) ? [$oa['exception'], $oa['exception_digest']] : null,
                isset($ob['exception']) ? [$ob['exception'], $ob['exception_digest']] : null);
            $this->example($status === self::STATUS_ERROR_PARITY ? 'error_parity' : 'error_mismatch', $id + ['level' => $smartTags ? 'smart_tags' : 'outcome', 'case' => $case]
                + self::errorShape(isset($oa['exception']) ? [$oa['exception'], $oa['exception_digest']] : null, isset($ob['exception']) ? [$ob['exception'], $ob['exception_digest']] : null));
        } else {
            $diff       = self::outcomeDifferences($oa, $ob);
            $undeclared = array_values(array_diff($diff, $allowedKeys));

            $status = match (true) {
                $undeclared !== [] || $factsUndeclared => self::STATUS_UNDECLARED,
                $fired !== [] || $diff !== []          => self::STATUS_ALLOWED,
                default                                => self::STATUS_EXACT,
            };

            $this->recordOutcomeDiffs($smartTags, $oa, $ob, $diff, $undeclared);

            if ($undeclared !== []) {
                $this->example($smartTags ? 'post_attachment_undeclared' : 'undeclared_outcomes', $id + [
                    'case'            => $case,
                    'outcome_keys'    => $undeclared,
                    'fired_ad'        => array_keys($fired),
                    'explanation'     => self::explanationShape($oa, $ob, $undeclared),
                ]);
            }
        }

        if ($smartTags) {
            $this->smart['cases']++;
            $this->bump($this->smart['status'], $status);
        } else {
            $this->outcomes['cases_evaluated']++;
            $this->bump($this->outcomes['status'], $status);
            $this->bump($this->outcomes['by_stratum'][$stratum], $status);
        }

        return $status;
    }

    // ── L2b ranking over cohort criteria ───────────────────────────────────────

    private function runRanking(): void
    {
        $scorer = new BuyerMatchScorer();
        $ranking = ['cohorts' => 0, 'exact_order' => 0, 'by_stratum' => []];

        foreach (array_merge(self::STRATA, [self::OTHER_TYPE]) as $stratum) {
            $rows = $this->cohortRows[$stratum] ?? [];
            if (count($rows) < 2) {
                continue;
            }

            $type = $stratum === self::OTHER_TYPE
                ? CanonicalParityCriteriaMatrix::mostCommon(array_map(static fn ($r) => $r['a']->propertyType, $rows))
                : $stratum;
            if ($type === null) {
                continue;
            }

            foreach ($this->matrix->cohortsFor($type, array_column($rows, 'a')) as $name => $overrides) {
                try {
                    $payload = new BuyerCriteriaPayload(array_merge(['is_55_plus_eligible' => false], $overrides));
                } catch (Throwable) {
                    continue;
                }

                $orderA = self::rank($scorer, $rows, 'a', $payload, $errorsA);
                $orderB = self::rank($scorer, $rows, 'b', $payload, $errorsB);

                $moves = 0;
                foreach ($orderA as $pos => $key) {
                    $moves += ($orderB[$pos] ?? null) === $key ? 0 : 1;
                }

                $exact = $orderA === $orderB;
                $ranking['cohorts']++;
                $ranking['exact_order'] += $exact ? 1 : 0;
                $ranking['by_stratum'][$stratum][$name] = [
                    'rows'            => count($rows),
                    'exact_order'     => $exact,
                    'positions_moved' => $moves,
                    'top10_overlap'   => self::overlap($orderA, $orderB, 10),
                    'top20_overlap'   => self::overlap($orderA, $orderB, 20),
                    'top20_only_legacy'    => array_slice(array_values(array_diff(array_slice($orderA, 0, 20), array_slice($orderB, 0, 20))), 0, $this->exampleCap),
                    'top20_only_canonical' => array_slice(array_values(array_diff(array_slice($orderB, 0, 20), array_slice($orderA, 0, 20))), 0, $this->exampleCap),
                    'scoring_errors'  => ['legacy' => $errorsA, 'canonical' => $errorsB],
                ];
            }
        }

        $this->outcomes['ranking'] = $ranking;
    }

    /** @return list<string> listing keys by (score DESC, errors last, key ASC) */
    private static function rank(BuyerMatchScorer $scorer, array $rows, string $side, BuyerCriteriaPayload $payload, ?int &$errors): array
    {
        $errors = 0;
        $scored = [];
        foreach ($rows as $r) {
            try {
                $scored[] = [$r['key'], $scorer->scoreFacts($r[$side], $payload)->totalScore];
            } catch (Throwable) {
                $errors++;
                $scored[] = [$r['key'], null];
            }
        }

        usort($scored, static fn ($x, $y) => [$x[1] === null ? 1 : 0, -($x[1] ?? 0), $x[0]] <=> [$y[1] === null ? 1 : 0, -($y[1] ?? 0), $y[0]]);

        return array_column($scored, 0);
    }

    /** @return array{shared: int, of: int} */
    private static function overlap(array $a, array $b, int $n): array
    {
        $of = min($n, count($a));

        return ['shared' => count(array_intersect(array_slice($a, 0, $of), array_slice($b, 0, $of))), 'of' => $of];
    }

    // ── outcome evaluation ─────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private static function project(ListingMatchFacts $facts, BridgeProperty $row, BuyerCriteriaPayload $criteria): array
    {
        $scorer  = new BuyerMatchScorer();
        $builder = new BuyerMatchResultBuilder();
        $score   = $scorer->scoreFacts($facts, $criteria);

        $result = static function () use ($facts, $score, $row): BuyerMatchResult {
            $r = new BuyerMatchResult($facts->listingKey, $score->totalScore, $score->categoryScores, $row);
            $r->importantPlaceMatches = $score->importantPlaceMatches;
            $r->seekerFeatureMatch    = $score->seekerFeatureMatch;
            $r->facts                 = $facts;

            return $r;
        };

        $batch    = $builder->build($result(), $criteria);
        $detailed = $builder->buildDetailed($result(), $criteria);

        return [
            'exception'        => null,
            'listing_key'      => $batch->listingKey,
            'total_score'      => $batch->totalScore,
            'category_scores'  => $batch->categoryScores,
            'important_places' => $batch->importantPlaceMatches,
            'why_this_matches' => $batch->whyThisMatches,
            'tradeoffs'        => $batch->tradeoffs,
            'caution_flags'    => $batch->cautionFlags,
            'missing_data'     => $batch->missingData,
            'why_not'          => $detailed->whyNot,
            'confidence'       => $detailed->confidence,
            'recommendations'  => $detailed->recommendations,
        ];
    }

    /** outcome(), plus a digest of the exception message so identical failures can be told apart from different ones. */
    private static function evaluate(ListingMatchFacts $facts, BridgeProperty $row, BuyerCriteriaPayload $criteria): array
    {
        try {
            return self::project($facts, $row, $criteria);
        } catch (Throwable $e) {
            return ['exception' => get_class($e), 'exception_digest' => self::digest(get_class($e) . '|' . $e->getMessage())];
        }
    }

    /** @return array{0: mixed, 1: array{0: string, 1: string}|null} the value, or the exception's class and message digest */
    private static function attempt(callable $fn): array
    {
        try {
            return [$fn(), null];
        } catch (Throwable $e) {
            return [null, [get_class($e), self::digest(get_class($e) . '|' . $e->getMessage())]];
        }
    }

    /** Same class AND same message → ERROR_PARITY; anything else (one side, other class, other message) → ERROR_MISMATCH. */
    private static function errorStatus(?array $a, ?array $b): string
    {
        return $a !== null && $b !== null && $a === $b ? self::STATUS_ERROR_PARITY : self::STATUS_ERROR_MISMATCH;
    }

    // ── recording ──────────────────────────────────────────────────────────────

    private function recordField(string $field, ?string $ad, string $stratum): void
    {
        $this->facts['by_field'][$field]['origin'] ??= self::origin($field);
        if ($ad === null) {
            $this->facts['by_field'][$field]['undeclared'] = ($this->facts['by_field'][$field]['undeclared'] ?? 0) + 1;
            $this->bump($this->facts['by_stratum'][$stratum]['undeclared_fields'], $field);
        } else {
            $this->bump($this->facts['by_field'][$field]['allowed'], $ad);
        }
    }

    private function recordOutcomeDiffs(bool $smartTags, array $oa, array $ob, array $diff, array $undeclared): void
    {
        foreach ($diff as $key) {
            $bucket = in_array($key, $undeclared, true) ? 'undeclared' : 'allowed';

            if ($smartTags) {
                $this->bump($this->smart['post_attachment_mismatches'], $bucket);
                continue;
            }

            $group = match (true) {
                $key === 'total_score'                         => 'total_score',
                $key === 'category_scores'                     => 'category_scores',
                $key === 'important_places'                    => 'important_places',
                in_array($key, self::EXPLANATION_KEYS, true)   => 'explanations',
                default                                        => $key,
            };
            $this->bump($this->outcomes['mismatches'][$group], $bucket);

            if ($key === 'category_scores') {
                $cats = array_unique(array_merge(array_keys((array) $oa[$key]), array_keys((array) $ob[$key])));
                sort($cats);
                foreach ($cats as $cat) {
                    if (($oa[$key][$cat] ?? null) !== ($ob[$key][$cat] ?? null)) {
                        $this->bump($this->outcomes['category_mismatches'][$cat], $bucket);
                    }
                }
            }
            if (in_array($key, self::EXPLANATION_KEYS, true)) {
                $this->bump($this->outcomes['explanation_mismatches'][$key], $bucket);
            }
        }
    }

    private function recordError(string $level, string $status, ?array $a, ?array $b): void
    {
        $label = $status === self::STATUS_ERROR_PARITY
            ? $a[0]
            : ($a[0] ?? 'none') . ' | ' . ($b[0] ?? 'none') . (($a !== null && $b !== null && $a[0] === $b[0]) ? ' (message differs)' : '');

        $this->bump($this->outcomes['errors'][$status === self::STATUS_ERROR_PARITY ? 'parity' : 'mismatch'][$level], $label);
    }

    private function recordListing(string $stratum, string $status): void
    {
        $this->bump($this->listings['status'], $status);
        $this->bump($this->listings['by_stratum'][$stratum], $status);
    }

    private function recordDiagnostics(string $stratum, ?ListingMatchFacts $a, ?ListingMatchFacts $b, ?ListingMatchResidualFacts $residual, mixed $tags): void
    {
        $d = &$this->diagnostics[$stratum];

        foreach (['poolPrivate' => 'pool', 'garage' => 'garage', 'waterfront' => 'waterfront'] as $field => $dim) {
            $v = $a?->{$field};
            $this->bump($d[$dim], $v === true ? 'true' : ($v === false ? 'false' : 'null'));
        }

        $hoa = $a !== null && (((float) ($a->associationFee ?? 0)) > 0 || $a->association === true);
        $this->bump($d['hoa'], $hoa ? 'present' : 'absent');

        $this->bump($d['lease_frequency'], MlsListingAdapter::leasePeriodToken(is_string($a?->leaseFrequency) ? $a->leaseFrequency : null) ?? 'none');

        if ($residual !== null) {
            $n = 0;
            foreach (get_object_vars($residual) as $v) {
                $n += ($v !== null && $v !== false && $v !== '' && $v !== []) ? 1 : 0;
            }
            $this->bump($d['residual_populated'], match (true) { $n <= 5 => '0-5', $n <= 10 => '6-10', $n <= 15 => '11-15', default => '16-20' });
        } else {
            $this->bump($d['residual_populated'], 'not_built');
        }

        if ($b !== null) {
            $missing = 0;
            foreach (CanonicalListingMatchFactsBuilder::ORIGIN as $field => $origin) {
                if (!in_array($origin, [CanonicalListingMatchFactsBuilder::ORIGIN_IDENTITY, CanonicalListingMatchFactsBuilder::ORIGIN_RESIDUAL, CanonicalListingMatchFactsBuilder::ORIGIN_ATTACHED_SMART_TAGS], true)) {
                    $missing += $b->{$field} === null ? 1 : 0;
                }
            }
            $this->bump($d['canonical_missing'], $missing >= 3 ? '3+' : (string) $missing);
        } else {
            $this->bump($d['canonical_missing'], 'not_built');
        }

        $lat = $a?->latitude;
        $lng = $a?->longitude;
        $this->bump($d['coordinates'], $lat === null || $lng === null ? 'missing'
            : (CoordinateValidator::isValidPair((float) $lat, (float) $lng) ? 'valid' : 'invalid'));

        $this->bump($d['smart_tags'], $tags === null ? 'not_read' : ($tags->hasAnyResolvedTag ? 'present' : 'absent'));
    }

    private function example(string $bucket, array $entry): void
    {
        $this->examples[$bucket] ??= ['count' => 0, 'shown' => []];
        $this->examples[$bucket]['count']++;

        if (count($this->examples[$bucket]['shown']) < $this->exampleCap) {
            $this->examples[$bucket]['shown'][] = $entry;
        }
    }

    // ── privacy-safe representations ───────────────────────────────────────────

    /** Location fields by SHAPE; others by a bounded scalar representation. Never raw location. */
    private static function valueRepresentation(string $field, mixed $legacy, mixed $canonical, ListingMatchFacts $a): array
    {
        if (!in_array($field, self::LOCATION_FIELDS, true)) {
            return ['legacy' => self::repr($legacy), 'canonical' => self::repr($canonical)];
        }

        $shape = match (true) {
            $legacy === null && $canonical !== null => 'null_vs_value',
            $legacy !== null && $canonical === null && in_array($field, ['latitude', 'longitude'], true)
                && !CoordinateValidator::isValidPair($a->latitude === null ? null : (float) $a->latitude, $a->longitude === null ? null : (float) $a->longitude)
                                                    => 'invalid_pair',
            $legacy !== null && $canonical === null && is_string($legacy) && trim($legacy) === ''
                                                    => 'blank_vs_null',
            $legacy !== null && $canonical === null => 'value_vs_null',
            is_string($legacy) && is_string($canonical) && trim($legacy) === $canonical => 'trimmed_whitespace',
            is_numeric($legacy) && is_numeric($canonical) && (float) $legacy === (float) $canonical => 'coordinate_normalized',
            is_string($legacy) && is_string($canonical) && strcasecmp(trim($legacy), trim($canonical)) === 0 => 'case_differs',
            default                                 => 'value_differs',
        };

        return ['shape' => $shape];
    }

    private static function repr(mixed $v): mixed
    {
        return match (true) {
            $v === null           => null,
            is_bool($v), is_int($v), is_float($v) => $v,
            is_string($v)         => mb_strlen($v) > 64 ? mb_substr($v, 0, 64) . '…' : $v,
            is_array($v)          => 'list(' . count($v) . ')',
            is_object($v)         => 'object(' . class_basename($v) . ')',
            default               => gettype($v),
        };
    }

    /** Explanation blocks by line DIGEST, never text. */
    private static function explanationShape(array $oa, array $ob, array $keys): array
    {
        $out = [];
        foreach (array_intersect($keys, self::EXPLANATION_KEYS) as $key) {
            $la = self::lines($oa[$key] ?? null);
            $lb = self::lines($ob[$key] ?? null);
            $out[$key] = [
                'legacy_only'    => array_values(array_map([self::class, 'digest'], array_diff($la, $lb))),
                'canonical_only' => array_values(array_map([self::class, 'digest'], array_diff($lb, $la))),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    private static function lines(mixed $block): array
    {
        if (!is_array($block)) {
            return [json_encode($block)];
        }

        return array_values(array_map(static fn ($l) => is_string($l) ? $l : json_encode($l), $block));
    }

    private static function errorShape(?array $a, ?array $b): array
    {
        return [
            'legacy_exception'    => $a[0] ?? null,
            'canonical_exception' => $b[0] ?? null,
            'message_digests'     => [$a[1] ?? null, $b[1] ?? null],
        ];
    }

    private static function digest(string $text): string
    {
        return substr(hash('sha256', $text), 0, 16);
    }

    private static function origin(string $field): string
    {
        $o = CanonicalListingMatchFactsBuilder::ORIGIN[$field] ?? 'unknown';

        return is_array($o) ? implode('+', $o) : $o;
    }

    // ── assembly ───────────────────────────────────────────────────────────────

    private function prepareSmartTags(): void
    {
        if (!SmartTagSeekerPreferenceGate::matchingEnabled()) {
            $this->smart['state']  = 'NOT_EXERCISED';
            $this->smart['reason'] = 'seeker_smart_tag_matching_gate_off';

            return;
        }

        $this->tagKeys = BuyerMatchScorer::scoredSeekerTags(new BuyerCriteriaPayload([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
            'seeker_smart_tags'   => self::SMART_TAG_PICKS,
        ]));

        $this->smart['state']  = $this->tagKeys === [] ? 'NOT_EXERCISED' : 'EXERCISED';
        $this->smart['reason'] = $this->tagKeys === [] ? 'no_scored_picks' : null;
        $this->smart['tag_keys'] = $this->tagKeys;
    }

    private function reset(int $examples): void
    {
        $this->exampleCap   = $examples;
        $this->tagKeys      = [];
        $this->stoppedAtMax = false;
        $this->sel          = ['rows_scanned' => 0, 'eligible' => 0, 'examined' => 0, 'excluded' => [], 'eligible_by_stratum' => [], 'examined_by_stratum' => []];
        $this->resolution   = ['resolved' => 0, 'unresolvable' => 0, 'unresolvable_by_reason' => []];
        $this->facts        = ['listings_compared' => 0, 'status' => [], 'smart_tags_attached_before_stage' => 0, 'by_field' => [], 'by_ad' => [], 'by_stratum' => []];
        $this->outcomes     = ['cases_evaluated' => 0, 'status' => [], 'mismatches' => [], 'category_mismatches' => [], 'explanation_mismatches' => [], 'errors' => ['parity' => [], 'mismatch' => []], 'by_stratum' => [], 'ranking' => []];
        $this->listings     = ['status' => [], 'by_stratum' => []];
        $this->smart        = ['state' => 'NOT_EXERCISED', 'reason' => null, 'tag_keys' => [], 'cases' => 0, 'status' => [], 'post_attachment_mismatches' => []];
        $this->diagnostics  = [];
        $this->criteria     = ['cases_by_stratum' => [], 'unbuildable' => 0];
        $this->examples     = [];
        $this->cohortRows   = [];
        $this->cost         = ['legacy_facts_ns' => 0, 'canonical_facts_ns' => 0, 'legacy_outcome_ns' => 0, 'canonical_outcome_ns' => 0];
    }

    private function result(): array
    {
        $partial = [];
        if ($this->sel['mode'] === 'population') {
            if ($this->stoppedAtMax) {
                $partial[] = 'max_listings_reached';
            }
            if (($this->sel['excluded']['per_type_cap'] ?? 0) > 0) {
                $partial[] = 'per_type_cap';
            }
            if ($this->sel['options']['stride'] > 1) {
                $partial[] = 'stride';
            }
            if ($this->sel['options']['from_id'] > 0) {
                $partial[] = 'resumed_from_id';
            }
        }

        $cases = [];
        foreach ($this->criteria['cases_by_stratum'] as $stratum => $names) {
            $names = array_keys($names);
            sort($names);
            $cases[$stratum] = $names;
        }

        return [
            'selection' => $this->sel + [
                'coverage'         => $this->sel['mode'] === 'targeted' ? 'targeted' : ($partial === [] ? 'census' : 'partial'),
                'partial_reasons'  => $partial,
                'strata'           => array_merge(self::STRATA, [self::OTHER_TYPE]),
            ],
            'resolution'  => $this->resolution + ['examined' => $this->sel['examined']],
            'facts'       => $this->facts,
            'outcomes'    => $this->outcomes,
            'listings'    => $this->listings,
            'smart_tags'  => $this->smart,
            'diagnostics' => $this->diagnostics,
            'criteria'    => ['case_names_by_stratum' => $cases, 'unbuildable' => $this->criteria['unbuildable'], 'smart_tag_picks' => self::SMART_TAG_PICKS],
            'examples'    => $this->examples,
            'registry'    => ['ids' => array_keys(CanonicalParityAllowedDifferences::ENTRIES)],
        ];
    }

    private function costBlock(float $elapsedMs, int $queries): array
    {
        $ms = static fn (int $ns): float => round($ns / 1e6, 3);
        $legacy    = $this->cost['legacy_facts_ns'] + $this->cost['legacy_outcome_ns'];
        $canonical = $this->cost['canonical_facts_ns'] + $this->cost['canonical_outcome_ns'];
        $ratio     = static fn (int $num, int $den): ?float => $den > 0 ? round($num / $den, 3) : null;

        $factsRatio = $ratio($this->cost['canonical_facts_ns'], $this->cost['legacy_facts_ns']);
        $pathRatio  = $ratio($canonical, $legacy);

        return [
            'elapsed_ms'              => round($elapsedMs, 3),
            'queries'                 => $queries,
            'rows_per_second'         => $elapsedMs > 0 ? round($this->sel['examined'] / ($elapsedMs / 1000), 1) : null,
            'legacy_facts_ms'         => $ms($this->cost['legacy_facts_ns']),
            'canonical_facts_ms'      => $ms($this->cost['canonical_facts_ns']),
            'legacy_outcome_ms'       => $ms($this->cost['legacy_outcome_ns']),
            'canonical_outcome_ms'    => $ms($this->cost['canonical_outcome_ns']),
            'facts_cost_ratio'        => $factsRatio,
            'path_cost_ratio'         => $pathRatio,
            'budget_ratio'            => self::COST_BUDGET_RATIO,
            'within_budget'           => $pathRatio === null ? null : $pathRatio <= self::COST_BUDGET_RATIO,
        ];
    }

    private function bump(?array &$map, string $key, int $by = 1): void
    {
        $map ??= [];
        $map[$key] = ($map[$key] ?? 0) + $by;
    }
}
