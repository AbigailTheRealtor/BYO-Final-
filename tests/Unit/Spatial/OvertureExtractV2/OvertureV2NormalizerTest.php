<?php

namespace Tests\Unit\Spatial\OvertureExtractV2;

use App\Services\Spatial\ChainRegistry\ChainMatcher;
use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureExtractV2\InvalidOvertureExtractV2;
use App\Services\Spatial\OvertureExtractV2\OvertureExtractV2Config;
use App\Services\Spatial\OvertureExtractV2\OvertureV2ExtractIo;
use App\Services\Spatial\OvertureExtractV2\OvertureV2ExtractResult;
use App\Services\Spatial\OvertureExtractV2\OvertureV2MatcherCensus;
use App\Services\Spatial\OvertureExtractV2\OvertureV2Normalizer;
use App\Services\Spatial\OvertureExtractV2\OvertureV2Record;
use App\Services\Spatial\OvertureExtractV2\OvertureV2Rejection;
use PHPUnit\Framework\TestCase;

/**
 * overture-extract-v2 against a committed 26-row fixture that reaches every branch, plus
 * single-row probes. Pure; no container, no database, no network.
 */
class OvertureV2NormalizerTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../../fixtures/spatial/overture-v2/raw_bbox_sample.ndjson';

    private ChainRegistry $registry;
    private OvertureExtractV2Config $config;
    private OvertureV2Normalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = ChainRegistry::fromArray(require __DIR__ . '/../../../../config/poi_chain_registry.php');
        $this->config = OvertureExtractV2Config::fromArray(require __DIR__ . '/../../../../config/overture_extract_v2.php', $this->registry);
        $this->normalizer = new OvertureV2Normalizer($this->config);
    }

    /** @return list<array<string, mixed>> */
    private static function fixture(): array
    {
        $rows = [];
        foreach (file(self::FIXTURE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $rows[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        return $rows;
    }

    private function extract(?array $rows = null): OvertureV2ExtractResult
    {
        return $this->normalizer->run($rows ?? self::fixture());
    }

    /** @param array<string, mixed> $over */
    private static function row(array $over = []): array
    {
        return array_merge([
            'id' => 'r-1', 'name' => 'Diner', 'brand_name' => null, 'brand_wikidata' => null,
            'taxonomy_primary' => 'restaurant', 'categories_primary' => 'restaurant', 'basic_category' => 'restaurant',
            'operating_status' => 'open', 'confidence' => 0.95, 'geometry_type' => 'POINT', 'lon' => -82.64, 'lat' => 27.77,
            'address_freeform' => '1 Main St', 'address_locality' => 'Tampa', 'address_postcode' => '33602',
            'address_region' => 'FL', 'address_country' => 'US',
        ], $over);
    }

    private function classify(array $over): OvertureV2Record|string
    {
        return $this->normalizer->classify(self::row($over));
    }

    /** @return array<string, OvertureV2Record> */
    private static function byRef(array $records): array
    {
        $out = [];
        foreach ($records as $r) {
            $out[$r->sourceRef] = $r;
        }

        return $out;
    }

    // ── Base filtering ───────────────────────────────────────────────────────────────────────

    public function test_every_approved_token_is_base_and_nothing_else_is(): void
    {
        foreach ([
            'restaurant', 'gas_station', 'gym', 'grocery_store', 'coffee_shop', 'pharmacy', 'shopping_mall',
            'convenience_store', 'fast_food_restaurant', 'cafe', 'burger_restaurant', 'department_store',
            'chicken_restaurant', 'drugstore', 'taco_restaurant', 'superstore',
        ] as $token) {
            $r = $this->classify(['taxonomy_primary' => $token]);
            $this->assertInstanceOf(OvertureV2Record::class, $r, $token);
            $this->assertSame(OvertureV2Record::LANE_BASE, $r->lane, $token);
        }
        foreach (['shopping', 'doctors_office', 'atm', 'liquor_store', 'bakery', 'photography_store', 'eyewear_store', 'fitness_center'] as $token) {
            $this->assertSame(OvertureV2Rejection::TAXONOMY_NOT_ALLOWLISTED, $this->classify(['taxonomy_primary' => $token]), $token);
        }
    }

    public function test_shopping_mall_is_canonical_shopping_center_and_the_legacy_token_fails(): void
    {
        $r = $this->classify(['taxonomy_primary' => 'shopping_mall']);
        $this->assertSame('shopping_center', $r->categoryKey);
        $this->assertSame('shopping_mall', $r->sourceCategory);
        $this->assertSame(OvertureV2Rejection::TAXONOMY_NOT_ALLOWLISTED, $this->classify(['taxonomy_primary' => 'shopping_center']));
    }

    public function test_null_taxonomy_is_excluded(): void
    {
        $this->assertSame(OvertureV2Rejection::TAXONOMY_NULL, $this->classify(['taxonomy_primary' => null]));
        $this->assertSame(OvertureV2Rejection::TAXONOMY_NULL, $this->classify(['taxonomy_primary' => '  ']));
    }

    public function test_confidence_floor_is_inclusive(): void
    {
        $this->assertInstanceOf(OvertureV2Record::class, $this->classify(['confidence' => 0.90]));
        $this->assertInstanceOf(OvertureV2Record::class, $this->classify(['confidence' => 1]));
        $this->assertSame(OvertureV2Rejection::CONFIDENCE_BELOW_FLOOR, $this->classify(['confidence' => 0.8999999]));
        foreach (['0.95', null, NAN, 1.5, -0.1] as $bad) {
            $this->assertSame(OvertureV2Rejection::MALFORMED_CONFIDENCE, $this->classify(['confidence' => $bad]));
        }
    }

    public function test_operating_status_open_and_unknown_are_eligible_and_kept_apart(): void
    {
        $open = $this->classify(['operating_status' => 'open']);
        $this->assertTrue($open->operatingStatusKnown);
        $this->assertSame('open', $open->operatingStatus);

        $unknown = $this->classify(['operating_status' => null]);
        $this->assertFalse($unknown->operatingStatusKnown, 'NULL is unknown, never coerced to open');
        $this->assertNull($unknown->operatingStatus);
    }

    public function test_closed_and_unseen_statuses_are_refused(): void
    {
        $this->assertSame(OvertureV2Rejection::STATUS_PERMANENTLY_CLOSED, $this->classify(['operating_status' => 'permanently_closed']));
        foreach (['temporarily_closed', 'Open', 'OPEN', 'open ', 'closed', ''] as $status) {
            $this->assertSame(OvertureV2Rejection::STATUS_UNRECOGNISED, $this->classify(['operating_status' => $status]), var_export($status, true));
        }
    }

    public function test_geometry_and_box(): void
    {
        foreach ([['lon' => null], ['lat' => '27.7'], ['geometry_type' => 'POLYGON'], ['lon' => INF], ['lat' => 91.0]] as $bad) {
            $this->assertSame(OvertureV2Rejection::MALFORMED_GEOMETRY, $this->classify($bad), json_encode(array_keys($bad)));
        }
        $this->assertSame(OvertureV2Rejection::OUTSIDE_BOUNDING_BOX, $this->classify(['lon' => -90.0]));
        $this->assertSame(OvertureV2Rejection::OUTSIDE_BOUNDING_BOX, $this->classify(['lat' => 31.01]));
        // The box edge itself is inside (containment is inclusive).
        $this->assertInstanceOf(OvertureV2Record::class, $this->classify(['lon' => -87.63, 'lat' => 31.00]));
        // GA/AL spill-over inside the rectangle is kept, not trimmed to Florida.
        $this->assertSame('GA', $this->classify(['address_region' => 'GA', 'lat' => 30.9])->address['region']);
    }

    public function test_malformed_text_field_fails_closed(): void
    {
        $this->assertSame(OvertureV2Rejection::MALFORMED_FIELD, $this->classify(['name' => 123]));
        $this->assertSame(OvertureV2Rejection::MALFORMED_FIELD, $this->classify(['brand_wikidata' => ['Q1']]));
        $this->assertSame(OvertureV2Rejection::MALFORMED_FIELD, $this->classify(['taxonomy_primary' => true]));
    }

    public function test_a_row_that_does_not_match_the_projection_aborts_the_run(): void
    {
        $row = self::row();
        unset($row['basic_category']);
        $this->expectException(InvalidOvertureExtractV2::class);
        $this->normalizer->run([$row]);
    }

    public function test_an_extra_field_aborts_the_run(): void
    {
        $this->expectException(InvalidOvertureExtractV2::class);
        $this->normalizer->run([self::row(['categories_alternate' => 'x'])]);
    }

    // ── taxonomy.primary is the only classifier ──────────────────────────────────────────────

    public function test_no_categories_primary_or_basic_category_fallback(): void
    {
        $this->assertSame(OvertureV2Rejection::TAXONOMY_NULL, $this->classify(['taxonomy_primary' => null, 'categories_primary' => 'grocery_store', 'basic_category' => 'grocery_store']));
        $this->assertSame(OvertureV2Rejection::TAXONOMY_NOT_ALLOWLISTED, $this->classify(['taxonomy_primary' => 'bakery', 'categories_primary' => 'restaurant', 'basic_category' => 'restaurant']));

        // They are provenance only, preserved verbatim on a kept row.
        $r = $this->classify(['taxonomy_primary' => 'cafe', 'categories_primary' => 'coffee_shop', 'basic_category' => 'eat_and_drink']);
        $this->assertSame('cafe', $r->categoryKey);
        $this->assertSame('coffee_shop', $r->legacyCategory);
        $this->assertSame('eat_and_drink', $r->basicCategory);
    }

    public function test_source_token_is_kept_raw_while_the_map_normalises_case(): void
    {
        $r = $this->classify(['taxonomy_primary' => ' Grocery_Store ']);
        $this->assertSame('grocery_store', $r->categoryKey);
        $this->assertSame(' Grocery_Store ', $r->sourceCategory);
    }

    // ── Provenance ───────────────────────────────────────────────────────────────────────────

    public function test_records_carry_lane_release_versions_and_categories(): void
    {
        $base = $this->classify([]);
        $this->assertSame(OvertureV2Record::LANE_BASE, $base->lane);
        $this->assertNull($base->supplementaryRole);
        $this->assertSame(OvertureV2Record::POLICY_CORPUS, $base->materializationPolicy);
        $this->assertNull($base->rescueVerdict, 'a base row has no rescue verdict');
        $this->assertSame(OvertureV2Record::ELIGIBILITY_BASE, $base->eligibility);
        $this->assertSame('2026-08-19.0', $base->sourceRelease);
        $this->assertSame('overture-extract-v2', $base->extractRecipeVersion);
        $this->assertSame('overture-taxonomy-v2.0', $base->taxonomyMapVersion);

        $supp = $this->classify(['taxonomy_primary' => 'shopping', 'name' => 'CVS Pharmacy']);
        $this->assertSame(OvertureV2Record::LANE_SUPPLEMENTARY, $supp->lane);
        $this->assertSame(OvertureV2Record::ROLE_RESCUE_CANDIDATE, $supp->supplementaryRole);
        $this->assertSame(['cvs_shopping'], $supp->rescueLanes);
        $this->assertSame(OvertureV2Record::POLICY_PENDING_RESCUE_VERDICT, $supp->materializationPolicy);
        $this->assertSame(OvertureV2Record::RESCUE_PENDING, $supp->rescueVerdict, 'the normalizer never decides a rescue');
        $this->assertNull($supp->rescuedChain);
        $this->assertSame(OvertureV2Record::ELIGIBILITY_SUPPLEMENTARY, $supp->eligibility);
        $this->assertNull($supp->categoryKey, 'a supplementary row never carries a canonical category');
        $this->assertSame('shopping', $supp->sourceCategory);
    }

    public function test_wire_format_key_order(): void
    {
        $this->assertSame([
            'lane', 'supplementary_role', 'rescue_lanes', 'materialization_policy', 'rescue_verdict', 'rescued_lane',
            'rescued_chain', 'rescued_as_category', 'rescued_format', 'source', 'source_ref', 'source_release',
            'extract_recipe_version', 'taxonomy_map_version', 'source_category', 'category_key', 'legacy_category',
            'basic_category', 'name', 'brand_name', 'brand_wikidata', 'confidence', 'operating_status',
            'operating_status_known', 'lon', 'lat', 'geometry_type', 'address', 'eligibility',
        ], array_keys($this->classify([])->toArray()));
        $this->assertSame(['freeform', 'locality', 'postcode', 'region', 'country'], array_keys($this->classify([])->address));
    }

    // ── Supplementary lane and the CVS rescue ────────────────────────────────────────────────

    public function test_non_chain_non_base_rows_never_enter_the_supplementary_lane(): void
    {
        $this->assertSame(OvertureV2Rejection::TAXONOMY_NOT_ALLOWLISTED, $this->classify(['taxonomy_primary' => 'shopping', 'name' => 'Some Boutique']));
        $this->assertSame(OvertureV2Rejection::TAXONOMY_NULL, $this->classify(['taxonomy_primary' => null, 'name' => 'Unknown Thing']));
    }

    public function test_the_supplementary_lane_still_honours_confidence_and_status(): void
    {
        $this->assertSame(OvertureV2Rejection::CONFIDENCE_BELOW_FLOOR, $this->classify(['taxonomy_primary' => 'shopping', 'name' => 'CVS Pharmacy', 'confidence' => 0.5]));
        $this->assertSame(OvertureV2Rejection::STATUS_PERMANENTLY_CLOSED, $this->classify(['taxonomy_primary' => 'shopping', 'name' => 'CVS Pharmacy', 'operating_status' => 'permanently_closed']));
    }

    public function test_a_diagnostic_row_is_matcher_only(): void
    {
        $r = $this->classify(['taxonomy_primary' => 'photography_store', 'name' => 'Walmart Photo Center']);
        $this->assertSame(OvertureV2Record::ROLE_DIAGNOSTIC, $r->supplementaryRole);
        $this->assertSame([], $r->rescueLanes);
        $this->assertSame(OvertureV2Record::POLICY_MATCHER_ONLY, $r->materializationPolicy);
        $this->assertSame(OvertureV2Record::RESCUE_NOT_CANDIDATE, $r->rescueVerdict);

        $qid = $this->classify(['taxonomy_primary' => 'atm', 'name' => 'ATM', 'brand_wikidata' => 'Q857063']);
        $this->assertSame(OvertureV2Record::ROLE_DIAGNOSTIC, $qid->supplementaryRole, 'any brand QID selects, as in the census');
        $this->assertSame(OvertureV2Record::POLICY_MATCHER_ONLY, $qid->materializationPolicy, 'a QID selects for analysis only');
        $this->assertNull($qid->categoryKey);

        $this->assertSame(OvertureV2Rejection::TAXONOMY_NOT_ALLOWLISTED,
            $this->classify(['taxonomy_primary' => 'atm', 'name' => 'ATM', 'brand_wikidata' => '  ']), 'a blank QID is not a QID');
    }

    public function test_a_qid_never_makes_a_row_base_or_rescued(): void
    {
        // An arbitrary brand QID on a non-import token: selected, diagnostic, matcher-only.
        $row = $this->classify(['taxonomy_primary' => 'bank_credit_union', 'name' => 'First Bank', 'brand_wikidata' => 'Q12345']);
        $this->assertFalse($row->isBase());
        $this->assertSame(OvertureV2Record::POLICY_MATCHER_ONLY, $row->materializationPolicy);

        // The same QID on the rescue token is a candidate by token, and the matcher refuses it.
        $cand = $this->classify(['id' => 'q-2', 'taxonomy_primary' => 'shopping', 'name' => 'First Bank Shop', 'brand_wikidata' => 'Q12345']);
        $this->assertSame(OvertureV2Record::ROLE_RESCUE_CANDIDATE, $cand->supplementaryRole);
        $out = $this->census([$cand, $row]);
        foreach ($out['supplementary'] as $r) {
            $this->assertNotSame(OvertureV2Record::POLICY_RESCUED, $r->materializationPolicy, $r->sourceRef);
            $this->assertNull($r->categoryKey);
        }
        $this->assertSame([OvertureV2Record::RESCUE_ADMITTED => 0, OvertureV2Record::RESCUE_REFUSED => 1], $out['summary']['rescue_verdicts']);
    }

    /** @param list<OvertureV2Record> $records */
    private function census(array $records): array
    {
        $outcomes = [];
        foreach ($records as $r) {
            $outcomes[$r->sourceRef] = $r;
        }
        $result = OvertureV2ExtractResult::fromOutcomes($this->config, count($records), 0, 0, 0, $outcomes);

        return (new OvertureV2MatcherCensus(new ChainMatcher($this->registry), $this->config))->run($result);
    }

    public function test_cvs_shopping_rescue_is_decided_by_the_matcher_with_every_registry_rule(): void
    {
        $result = $this->extract();
        $census = (new OvertureV2MatcherCensus(new ChainMatcher($this->registry), $this->config))->run($result);
        $this->assertSame(['cvs_shopping' => 2], $census['summary']['rescued_by_lane'], 'CVS Pharmacy and CVS-inside-Target only');
        $this->assertSame([OvertureV2Record::RESCUE_ADMITTED => 2, OvertureV2Record::RESCUE_REFUSED => 2], $census['summary']['rescue_verdicts']);

        // The verdict is written on the row: only the admitted ones are `rescued`.
        $resolved = self::byRef($census['supplementary']);
        $this->assertSame(array_keys(self::byRef($result->supplementary())), array_keys($resolved), 'same rows, same order');
        foreach (['fx-09' => 'store', 'fx-22' => 'store_in_target'] as $ref => $format) {
            $r = $resolved[$ref];
            $this->assertSame(OvertureV2Record::RESCUE_ADMITTED, $r->rescueVerdict, $ref);
            $this->assertSame(OvertureV2Record::POLICY_RESCUED, $r->materializationPolicy, $ref);
            $this->assertSame(['cvs_shopping', 'cvs', 'drugstore', $format], [$r->rescuedLane, $r->rescuedChain, $r->rescuedAsCategory, $r->rescuedFormat], $ref);
            $this->assertNull($r->categoryKey, 'a rescue records its target category apart from category_key');
        }
        foreach (['fx-10', 'fx-11'] as $ref) {
            $this->assertSame(OvertureV2Record::RESCUE_REFUSED, $resolved[$ref]->rescueVerdict, $ref);
            $this->assertSame(OvertureV2Record::POLICY_MATCHER_ONLY, $resolved[$ref]->materializationPolicy, $ref);
            $this->assertNull($resolved[$ref]->rescuedChain, $ref);
        }
        foreach ($resolved as $ref => $r) {
            $this->assertNotSame(OvertureV2Record::RESCUE_PENDING, $r->rescueVerdict, $ref);
        }

        $matcher = new ChainMatcher($this->registry);
        $refs = self::byRef($result->supplementary());
        $input = static fn (OvertureV2Record $r) => new \App\Services\Spatial\ChainRegistry\ChainMatchInput($r->name, $r->brandName, $r->brandWikidata, $r->categoryKey, $r->operatingStatus, $r->sourceCategory);

        $cvs = $matcher->match($input($refs['fx-09']))->membership('cvs');
        $this->assertSame('store', $cvs->formatKey);
        $this->assertSame('shopping', $cvs->rescuedFromSourceCategory);

        // CVS inside Target keeps its host-store format through the rescue.
        $this->assertSame('store_in_target', $matcher->match($input($refs['fx-22']))->membership('cvs')->formatKey);

        // Beauty and MinuteClinic are candidates (the lane is reached by TOKEN) but never become a store.
        foreach (['fx-10', 'fx-11'] as $ref) {
            $this->assertSame(OvertureV2Record::ROLE_RESCUE_CANDIDATE, $refs[$ref]->supplementaryRole, $ref);
            $this->assertSame([], $matcher->match($input($refs[$ref]))->memberships, $ref);
        }
        // A specialty pharmacy is not promoted either.
        $specialty = $this->classify(['taxonomy_primary' => 'shopping', 'name' => 'CVS Specialty Pharmacy']);
        $this->assertSame([], $matcher->match($input($specialty))->memberships);
    }

    public function test_the_lane_contract_is_enforced_from_the_matcher_side(): void
    {
        // A supplementary row whose token has a rescue but whose record names no lane is a breach.
        $row = $this->classify(['taxonomy_primary' => 'shopping', 'name' => 'CVS Pharmacy']);
        $forged = new OvertureV2Record(
            OvertureV2Record::LANE_SUPPLEMENTARY, OvertureV2Record::ROLE_DIAGNOSTIC, [], OvertureV2Record::POLICY_MATCHER_ONLY,
            $row->sourceRef, $row->sourceRelease, $row->extractRecipeVersion, $row->taxonomyMapVersion, $row->sourceCategory,
            null, null, null, $row->name, null, null, $row->confidence, $row->operatingStatus, true, $row->lon, $row->lat,
            'POINT', $row->address, OvertureV2Record::ELIGIBILITY_SUPPLEMENTARY,
        );
        $result = OvertureV2ExtractResult::fromOutcomes($this->config, 1, 0, 0, 0, ['x' => $forged]);
        $this->expectException(InvalidOvertureExtractV2::class);
        (new OvertureV2MatcherCensus(new ChainMatcher($this->registry), $this->config))->run($result);
    }

    public function test_a_non_cvs_row_under_the_rescue_token_is_a_candidate_and_is_refused(): void
    {
        // Candidacy is by token: a Winn-Dixie filed under `shopping` is labelled a cvs_shopping
        // candidate exactly as a CVS is. The written verdict is what keeps it out of the corpus.
        $wd = $this->classify(['id' => 'wd-1', 'taxonomy_primary' => 'shopping', 'name' => 'Winn-Dixie']);
        $wm = $this->classify(['id' => 'wm-1', 'taxonomy_primary' => 'shopping', 'name' => 'Walmart Vision & Glasses']);
        foreach ([$wd, $wm] as $r) {
            $this->assertSame(OvertureV2Record::ROLE_RESCUE_CANDIDATE, $r->supplementaryRole);
            $this->assertSame(['cvs_shopping'], $r->rescueLanes);
        }
        $out = $this->census([$wd, $wm]);
        foreach ($out['supplementary'] as $r) {
            $this->assertSame(OvertureV2Record::RESCUE_REFUSED, $r->rescueVerdict, $r->sourceRef);
            $this->assertSame(OvertureV2Record::POLICY_MATCHER_ONLY, $r->materializationPolicy, $r->sourceRef);
            $this->assertNull($r->rescuedAsCategory, $r->sourceRef);
        }
        $this->assertSame(0, $out['summary']['memberships']);
    }

    public function test_a_candidate_whose_membership_is_not_one_of_its_own_lanes_aborts(): void
    {
        // A rescue candidate that names no lane cannot be admitted under one it does not name.
        $row = $this->classify(['taxonomy_primary' => 'shopping', 'name' => 'CVS Pharmacy']);
        $forged = new OvertureV2Record(
            OvertureV2Record::LANE_SUPPLEMENTARY, OvertureV2Record::ROLE_RESCUE_CANDIDATE, [], OvertureV2Record::POLICY_PENDING_RESCUE_VERDICT,
            $row->sourceRef, $row->sourceRelease, $row->extractRecipeVersion, $row->taxonomyMapVersion, $row->sourceCategory,
            null, null, null, $row->name, null, null, $row->confidence, $row->operatingStatus, true, $row->lon, $row->lat,
            'POINT', $row->address, OvertureV2Record::ELIGIBILITY_SUPPLEMENTARY, OvertureV2Record::RESCUE_PENDING,
        );
        $this->expectException(InvalidOvertureExtractV2::class);
        $this->expectExceptionMessage('is not one of its lanes');
        $this->census([$forged]);
    }

    public function test_only_a_pending_candidate_can_be_resolved_and_only_consistently(): void
    {
        $pending = $this->classify(['taxonomy_primary' => 'shopping', 'name' => 'CVS Pharmacy']);
        $refused = $pending->withRescueVerdict(OvertureV2Record::RESCUE_REFUSED);
        $this->assertSame(OvertureV2Record::POLICY_MATCHER_ONLY, $refused->materializationPolicy);

        $cases = [
            'already resolved' => fn () => $refused->withRescueVerdict(OvertureV2Record::RESCUE_REFUSED),
            'base row' => fn () => $this->classify([])->withRescueVerdict(OvertureV2Record::RESCUE_REFUSED),
            'diagnostic row' => fn () => $this->classify(['taxonomy_primary' => 'atm', 'name' => 'Walmart ATM'])->withRescueVerdict(OvertureV2Record::RESCUE_REFUSED),
            'admitted without fields' => fn () => $pending->withRescueVerdict(OvertureV2Record::RESCUE_ADMITTED),
            'refused with fields' => fn () => $pending->withRescueVerdict(OvertureV2Record::RESCUE_REFUSED, 'cvs_shopping', 'cvs', 'drugstore', 'store'),
            'unknown verdict' => fn () => $pending->withRescueVerdict('maybe'),
        ];
        foreach ($cases as $label => $call) {
            try {
                $call();
                $this->fail("{$label} was accepted");
            } catch (InvalidOvertureExtractV2) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_wire_order_is_byte_wise_for_numeric_looking_ids(): void
    {
        // PHP 8's <=> compares numeric strings as numbers ("10" > "9"); byte order puts "10" first.
        $rows = [];
        foreach (['9', '10', '1e1', '010'] as $id) {
            $rows[] = self::row(['id' => $id]);
        }
        $refs = array_map(static fn (OvertureV2Record $r) => $r->sourceRef, $this->extract($rows)->base());
        $this->assertSame(['010', '10', '1e1', '9'], $refs);
    }

    // ── Accounting ───────────────────────────────────────────────────────────────────────────

    public function test_fixture_accounting_reconciles_exactly(): void
    {
        $r = $this->extract();
        $this->assertSame(26, $r->inputRows());
        $this->assertSame(5, $r->baseCount());
        $this->assertSame(6, $r->supplementaryCount());
        $this->assertSame(11, $r->matcherAnalysisCount());
        $this->assertTrue($r->isFullyAccounted());
        $this->assertSame([
            OvertureV2Rejection::MISSING_SOURCE_ID => 1,
            OvertureV2Rejection::DUPLICATE_SOURCE_ID => 2,
            OvertureV2Rejection::MALFORMED_FIELD => 1,
            OvertureV2Rejection::MALFORMED_GEOMETRY => 1,
            OvertureV2Rejection::OUTSIDE_BOUNDING_BOX => 1,
            OvertureV2Rejection::MALFORMED_CONFIDENCE => 1,
            OvertureV2Rejection::CONFIDENCE_BELOW_FLOOR => 1,
            OvertureV2Rejection::STATUS_PERMANENTLY_CLOSED => 1,
            OvertureV2Rejection::STATUS_UNRECOGNISED => 2,
            OvertureV2Rejection::TAXONOMY_NULL => 2,
            OvertureV2Rejection::TAXONOMY_NOT_ALLOWLISTED => 2,
        ], $r->rejections());
        $this->assertSame([
            'bbox_input_rows' => 26,
            'valid_identity_geometry_in_box' => 20,
            'confidence_eligible' => 18,
            'confidence_and_status_eligible' => 15,
            'base_category_eligible' => 5,
            'supplementary_candidates' => 10,
            'supplementary_selected' => 6,
            'supplementary_rule_not_satisfied' => 4,
        ], $r->funnel());
    }

    public function test_the_three_counts_cannot_be_conflated(): void
    {
        $counts = $this->extract()->accounting()['counts'];
        $this->assertSame(5, $counts['base_corpus_rows']);
        $this->assertSame(6, $counts['supplementary_rows']);
        $this->assertSame(11, $counts['matcher_analysis_rows']);
        $this->assertStringContainsString('NOT a corpus count', $counts['matcher_analysis_rows_note']);
        $this->assertArrayNotHasKey('total_rows', $counts);
        foreach ($this->extract()->base() as $rec) {
            $this->assertSame(OvertureV2Record::LANE_BASE, $rec->lane);
        }
        foreach ($this->extract()->supplementary() as $rec) {
            $this->assertSame(OvertureV2Record::LANE_SUPPLEMENTARY, $rec->lane);
            $this->assertNull($rec->categoryKey);
        }
    }

    public function test_every_copy_of_a_duplicate_id_is_removed_whatever_the_order(): void
    {
        $a = self::row(['id' => 'dup', 'confidence' => 0.99]);
        $b = self::row(['id' => 'dup', 'confidence' => 0.10]);
        foreach ([[$a, $b], [$b, $a], [$a, $b, $a]] as $rows) {
            $r = $this->normalizer->run($rows);
            $this->assertSame(0, $r->baseCount());
            $this->assertSame(count($rows), $r->rejections()[OvertureV2Rejection::DUPLICATE_SOURCE_ID]);
            $this->assertTrue($r->isFullyAccounted());
        }
    }

    // ── Determinism ──────────────────────────────────────────────────────────────────────────

    public function test_reordered_input_gives_identical_bytes_and_accounting(): void
    {
        $rows = self::fixture();
        $a = $this->extract($rows);
        $reversed = $this->extract(array_reverse($rows));
        mt_srand(42);
        shuffle($rows);
        $shuffled = $this->extract($rows);

        foreach ([$reversed, $shuffled] as $other) {
            $this->assertSame(OvertureV2ExtractIo::toNdjson($a->base()), OvertureV2ExtractIo::toNdjson($other->base()));
            $this->assertSame(OvertureV2ExtractIo::toNdjson($a->supplementary()), OvertureV2ExtractIo::toNdjson($other->supplementary()));
            $this->assertSame($a->accounting(), $other->accounting());
        }
    }

    public function test_same_input_same_checksum(): void
    {
        $this->assertSame(
            OvertureV2ExtractIo::checksum(OvertureV2ExtractIo::toNdjson($this->extract()->base())),
            OvertureV2ExtractIo::checksum(OvertureV2ExtractIo::toNdjson($this->extract()->base())),
        );
    }

    public function test_wire_order_is_category_then_source_ref(): void
    {
        $keys = array_map(static fn (OvertureV2Record $r) => [$r->categoryKey, $r->sourceRef], $this->extract()->base());
        $sorted = $keys;
        sort($sorted);
        $this->assertSame($sorted, $keys);
    }
}
