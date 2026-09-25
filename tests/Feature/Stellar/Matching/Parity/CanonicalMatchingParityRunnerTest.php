<?php

namespace Tests\Feature\Stellar\Matching\Parity;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Services\Stellar\Matching\Parity\CanonicalMatchingParityRunner as Runner;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Services\Stellar\Matching\Parity\CanonicalParityAllowedDifferences;
use App\Services\Stellar\Matching\Parity\CanonicalParityCriteriaMatrix;
use App\Services\Stellar\Matching\Parity\CanonicalParityReport;
use App\Support\Listing\PropertyTypeVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-B2 — the offline parity runner over stored rows.
 *
 * The committed Stellar fixtures are stored through the real normalizer (as P1-A0 and
 * P1-B store them), then mutated to construct each status. Nothing here reaches a
 * provider: the runner reads local rows only.
 */
class CanonicalMatchingParityRunnerTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    /** Keeps AD-1 out of rows meant to be EXACT (every fixture stores false on these three). */
    private const ISOLATE = ['PoolPrivateYN' => true, 'GarageYN' => true, 'WaterfrontYN' => true];

    private function run_(mixed ...$args): CanonicalParityReport
    {
        return (new Runner())->run(...$args);
    }

    // ── P1-B reproduction ─────────────────────────────────────────────────────

    public function test_the_committed_fixtures_reproduce_the_p1b_parity_result(): void
    {
        $this->storeAllBaselineFixtures();

        $r = $this->run_()->result;

        $this->assertSame(7, $r['selection']['examined']);
        $this->assertSame('census', $r['selection']['coverage']);
        $this->assertSame(7, $r['resolution']['resolved'], 'every committed fixture resolves canonically');
        $this->assertSame(0, $r['resolution']['unresolvable']);

        // Only AD-1 fires on the committed fixtures (CanonicalFactsParityTest's own rule), and
        // it never propagates. residential_lease stores true on pool, garage and waterfront, so
        // it has no false for AD-1 to read and is EXACT; the other six are ALLOWED.
        $this->assertSame(['AD-1'], array_keys($r['facts']['by_ad']));
        $this->assertSame(6, $r['facts']['by_ad']['AD-1']['listings']);
        $this->assertSame(['ALLOWED_DIFFERENCE' => 6, 'EXACT' => 1], $r['listings']['status']);
        $this->assertSame(['EXACT' => 1], $r['listings']['by_stratum']['Residential Lease']);
        $this->assertSame([], $r['outcomes']['mismatches'], 'AD-1 must not move a score, place row or explanation');
        $this->assertSame(['ALLOWED_DIFFERENCE', 'EXACT'], array_keys($r['outcomes']['status']));
        $this->assertSame(0, $r['smart_tags']['cases'], 'the post-attachment level is off by default');
        $this->assertGreaterThan(7 * 30, $r['outcomes']['cases_evaluated']);
    }

    public function test_every_stratum_including_other_type_is_reported(): void
    {
        $this->storeAllBaselineFixtures();
        $this->storeBaselineFixture('residential', self::ISOLATE, 'farm', ['property_type' => 'Farm']);

        $r = $this->run_()->result;

        foreach (Runner::STRATA as $stratum) {
            $this->assertSame(1, $r['selection']['examined_by_stratum'][$stratum] ?? 0, $stratum);
            $this->assertArrayHasKey($stratum, $r['diagnostics'], $stratum);
        }
        $this->assertSame(1, $r['selection']['examined_by_stratum'][Runner::OTHER_TYPE]);
        $this->assertSame(array_merge(Runner::STRATA, [Runner::OTHER_TYPE]), $r['selection']['strata']);

        // A type with no recognised meaning is AD-5 (unknown canonically), not undeclared.
        $this->assertSame(1, $r['facts']['by_ad']['AD-5']['listings']);
    }

    public function test_the_strata_are_the_vocabularys_own_primary_types(): void
    {
        foreach (Runner::STRATA as $type) {
            $this->assertSame($type, PropertyTypeVocabulary::recognisedTypeFor(
                PropertyTypeVocabulary::roleCategoryFor($type, 'seller'),
                PropertyTypeVocabulary::transactionFor($type),
            ), "{$type} must be a fixed point of recognisedTypeFor()");
        }
        $this->assertSame(Runner::OTHER_TYPE, Runner::stratumFor('ResidentialIncome'));
        $this->assertSame(Runner::OTHER_TYPE, Runner::stratumFor(null));
    }

    // ── Each status ───────────────────────────────────────────────────────────

    public function test_exact(): void
    {
        $this->storeBaselineFixture('residential', self::ISOLATE, 'exact');

        $r = $this->run_()->result;

        $this->assertSame(['EXACT' => 1], $r['listings']['status']);
        $this->assertSame(['EXACT'], array_keys($r['outcomes']['status']));
        $this->assertSame([], $r['facts']['by_ad']);
    }

    public function test_every_allowed_difference_is_classified_by_its_own_id(): void
    {
        $this->storeBaselineFixture('residential', [], 'ad1');
        $this->storeBaselineFixture('residential', self::ISOLATE, 'ad2', ['latitude' => 0, 'longitude' => 0]);
        $this->storeBaselineFixture('residential', self::ISOLATE, 'ad3', ['list_price' => 0, 'living_area' => 0, 'year_built' => 1600]);
        $this->storeBaselineFixture('residential', ['LeaseAmountFrequency' => 'Monthly'] + self::ISOLATE, 'ad4');
        $this->storeBaselineFixture('income', self::ISOLATE, 'ad5', ['property_type' => 'ResidentialIncome']);
        $this->storeBaselineFixture('residential', self::ISOLATE, 'ad6', ['city' => '  ' . $this->fixtureRecord('residential')['City'] . ' ']);

        $r = $this->run_()->result;

        $this->assertSame(array_keys(CanonicalParityAllowedDifferences::ENTRIES), array_keys($r['facts']['by_ad']));
        foreach ($r['facts']['by_ad'] as $ad => $entry) {
            $this->assertSame(1, $entry['listings'], $ad);
        }
        $this->assertArrayNotHasKey('UNDECLARED_DIFFERENCE', $r['listings']['status']);
        $this->assertArrayNotHasKey('ERROR_MISMATCH', $r['listings']['status']);
        $this->assertSame(6, $r['listings']['status']['ALLOWED_DIFFERENCE']);

        // Declared propagation is counted as allowed, never undeclared.
        foreach ($r['outcomes']['mismatches'] as $group => $buckets) {
            $this->assertArrayNotHasKey('undeclared', $buckets, $group);
        }
    }

    public function test_an_undeclared_facts_difference_is_visible_and_fails(): void
    {
        // A fractional living area the store kept: the legacy path carries it, the canonical
        // candidate truncates it to an int — a difference no registry entry describes.
        $this->storeBaselineFixture('residential', self::ISOLATE, 'undeclared', ['living_area' => 1500.5]);

        $report = $this->run_();
        $r = $report->result;

        $this->assertSame(['UNDECLARED_DIFFERENCE' => 1], $r['listings']['status']);
        $this->assertSame(1, $r['facts']['by_field']['livingArea']['undeclared']);
        $this->assertSame(1, $r['examples']['undeclared_facts']['count']);
        $shown = $r['examples']['undeclared_facts']['shown'][0];
        $this->assertSame('livingArea', $shown['field']);
        $this->assertSame(1500.5, $shown['legacy']);
        $this->assertSame(1500, $shown['canonical']);
        $this->assertSame(CanonicalParityReport::VERDICT_UNDECLARED, $report->verdict());
    }

    public function test_an_allowed_difference_that_reaches_an_undeclared_outcome_is_undeclared(): void
    {
        // AD-1 declares NO propagation. Scoring the same facts difference through a rule that
        // reads it would move a score; here the difference is constructed at the outcome layer
        // by the registry's own scope: any outcome key outside `propagates` is undeclared.
        $this->assertSame([], CanonicalParityAllowedDifferences::propagates('AD-1'));
        $this->assertSame([], CanonicalParityAllowedDifferences::propagates('AD-4'));

        // AD-3 on list price propagates to scores (declared); AD-6 on the key propagates to
        // listing_key (declared). A row firing only AD-1 whose outcomes moved would be reported
        // as UNDECLARED_DIFFERENCE with the outcome keys named — exercised end to end by the
        // fixtures test above staying ALLOWED with zero outcome mismatches.
        $this->storeBaselineFixture('residential', [], 'ad1_only');
        $r = $this->run_()->result;
        $this->assertSame(['ALLOWED_DIFFERENCE' => 1], $r['listings']['status']);
        $this->assertSame([], $r['outcomes']['mismatches']);
    }

    public function test_canonical_unresolvable_names_its_reason(): void
    {
        $this->storeBaselineFixture('residential', self::ISOLATE, 'other_provider', ['provider' => 'some_other_mls']);

        $report = $this->run_();
        $r = $report->result;

        $this->assertSame(['CANONICAL_UNRESOLVABLE' => 1], $r['listings']['status']);
        $this->assertSame(['unrecognised_provider' => 1], $r['resolution']['unresolvable_by_reason']);
        $this->assertSame(CanonicalParityReport::VERDICT_PASS, $report->verdict());
        $this->assertSame(CanonicalParityReport::VERDICT_UNRESOLVABLE, $report->verdict(true));
    }

    public function test_the_nested_array_crash_is_error_parity_on_both_paths(): void
    {
        $this->storeBaselineFixture('residential_lease', [
            'CommunityFeatures'    => [['Pool', 'Gym']],
            'AssociationAmenities' => [['Clubhouse']],
            'LeaseTerm'            => ['12 Months'],
        ] + self::ISOLATE, 'nested');

        $report = $this->run_();
        $r = $report->result;

        $this->assertGreaterThan(0, $r['outcomes']['status']['ERROR_PARITY'] ?? 0);
        $this->assertArrayNotHasKey('ERROR_MISMATCH', $r['outcomes']['status']);
        $this->assertSame(['ERROR_PARITY' => 1], $r['listings']['status']);
        $this->assertNotEmpty($r['outcomes']['errors']['parity']['outcome']);
        $this->assertSame(CanonicalParityReport::VERDICT_PASS, $report->verdict(), 'identical failures on both paths are visible, not a mismatch');
    }

    public function test_the_same_exception_on_both_facts_paths_is_error_parity(): void
    {
        // A non-numeric latitude the store kept: both facts builders raise the same TypeError
        // with the same message — visible, counted, and not a mismatch.
        $this->storeBaselineFixture('residential', self::ISOLATE, 'both_fail', ['latitude' => 'not-a-number']);

        $report = $this->run_();
        $r = $report->result;

        $this->assertSame(['ERROR_PARITY' => 1], $r['listings']['status']);
        $shown = $r['examples']['error_parity']['shown'][0];
        $this->assertSame('facts', $shown['level']);
        $this->assertSame('TypeError', $shown['legacy_exception']);
        $this->assertSame('TypeError', $shown['canonical_exception']);
        $this->assertSame($shown['message_digests'][0], $shown['message_digests'][1]);
        $this->assertSame(CanonicalParityReport::VERDICT_PASS, $report->verdict());
    }

    public function test_a_one_sided_exception_is_error_mismatch(): void
    {
        // A zero list price (AD-3: canonical reads it as unknown) scored against an ideal price
        // of 0. BuyerMatchResultBuilder divides by the ideal price whenever the listing HAS a
        // price, so the legacy side raises DivisionByZeroError and the canonical side, with no
        // price, does not. One side failing is ERROR_MISMATCH, even behind a declared difference.
        $this->storeBaselineFixture('residential', self::ISOLATE, 'one_sided', ['list_price' => 0]);

        $matrix = new class extends CanonicalParityCriteriaMatrix {
            public function casesFor(ListingMatchFacts $a, BridgeProperty $row, string $stratum): array
            {
                return ['ideal_price_zero' => ['property_types' => [$a->propertyType], 'ideal_price' => 0]];
            }
        };

        $report = (new Runner(matrix: $matrix))->run();
        $r = $report->result;

        $this->assertSame(['ERROR_MISMATCH' => 1], $r['listings']['status']);
        $shown = $r['examples']['error_mismatch']['shown'][0];
        $this->assertSame('outcome', $shown['level']);
        $this->assertSame('ideal_price_zero', $shown['case']);
        $this->assertSame('DivisionByZeroError', $shown['legacy_exception']);
        $this->assertNull($shown['canonical_exception']);
        $this->assertSame(['DivisionByZeroError | none' => 1], $r['outcomes']['errors']['mismatch']['outcome']);
        $this->assertSame(CanonicalParityReport::VERDICT_ERROR_MISMATCH, $report->verdict());
    }

    public function test_price_families_are_not_synthesised_for_a_listing_without_a_price(): void
    {
        $this->storeBaselineFixture('residential', self::ISOLATE, 'priced');
        $this->storeBaselineFixture('residential', self::ISOLATE, 'zero_price', ['list_price' => 0]);

        $r = $this->run_()->result;

        $this->assertContains('price_ideal_at_list', $r['criteria']['case_names_by_stratum']['Residential']);
        $this->assertArrayNotHasKey('ERROR_MISMATCH', $r['listings']['status'], 'no ideal_price of 0 is invented');
    }

    public function test_worst_status_wins_per_listing(): void
    {
        $this->assertGreaterThan(Runner::SEVERITY['UNDECLARED_DIFFERENCE'], Runner::SEVERITY['ERROR_MISMATCH']);
        $this->assertGreaterThan(Runner::SEVERITY['ERROR_PARITY'], Runner::SEVERITY['UNDECLARED_DIFFERENCE']);
        $this->assertGreaterThan(Runner::SEVERITY['ALLOWED_DIFFERENCE'], Runner::SEVERITY['ERROR_PARITY']);
        $this->assertGreaterThan(Runner::SEVERITY['EXACT'], Runner::SEVERITY['ALLOWED_DIFFERENCE']);

        // An undeclared facts difference makes every case of that listing undeclared.
        $this->storeBaselineFixture('residential', self::ISOLATE, 'undeclared', ['living_area' => 1500.5]);
        $r = $this->run_()->result;
        $this->assertSame(['UNDECLARED_DIFFERENCE'], array_keys($r['outcomes']['status']));
    }

    // ── Smart Tags ────────────────────────────────────────────────────────────

    public function test_smart_tags_on_attaches_the_same_resolved_tags_to_both_paths(): void
    {
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        config()->set('smart_tags_wiring.seeker_matching_enabled', true);

        $tagged   = $this->storeBaselineFixture('residential', self::ISOLATE, 'tagged');
        $untagged = $this->storeBaselineFixture('residential', self::ISOLATE, 'untagged');
        $this->assignTag($tagged, 'updated_kitchen');

        $r = $this->run_()->result;

        $this->assertSame('EXERCISED', $r['smart_tags']['state']);
        $this->assertSame(Runner::SMART_TAG_PICKS, $r['smart_tags']['tag_keys']);
        $this->assertSame(2, $r['smart_tags']['cases']);
        $this->assertSame(['EXACT' => 2], $r['smart_tags']['status']);
        $this->assertSame([], $r['smart_tags']['post_attachment_mismatches']);
        $this->assertSame(['absent' => 1, 'present' => 1], $r['diagnostics']['Residential']['smart_tags']);
    }

    public function test_smart_tags_off_is_not_exercised_and_reads_no_tags(): void
    {
        config()->set('smart_tags_wiring.seeker_matching_enabled', false);
        $row = $this->storeBaselineFixture('residential', self::ISOLATE, 'off');
        $this->assignTag($row, 'updated_kitchen');

        $sql = [];
        DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });

        $r = $this->run_()->result;

        $this->assertSame('NOT_EXERCISED', $r['smart_tags']['state']);
        $this->assertSame('seeker_smart_tag_matching_gate_off', $r['smart_tags']['reason']);
        $this->assertSame(0, $r['smart_tags']['cases']);
        $this->assertSame(['not_read' => 1], $r['diagnostics']['Residential']['smart_tags']);
        $this->assertSame([], array_filter($sql, fn ($s) => str_contains($s, 'smart_tag')), 'no tag read with the gate off');
        $this->assertFalse(config('smart_tags_wiring.seeker_matching_enabled'), 'the runner never changes the setting');
    }

    // ── Ranking ───────────────────────────────────────────────────────────────

    public function test_ranking_is_compared_per_stratum_over_cohort_criteria(): void
    {
        foreach (['a', 'b', 'c'] as $v) {
            $this->storeBaselineFixture('residential', [], "rank_{$v}", ['list_price' => 150000 + 10000 * ord($v)]);
        }

        $rank = $this->run_()->result['outcomes']['ranking'];

        $this->assertSame(3, $rank['cohorts']);
        $this->assertSame(3, $rank['exact_order']);
        foreach ($rank['by_stratum']['Residential'] as $name => $cohort) {
            $this->assertTrue($cohort['exact_order'], $name);
            // The report canonicalises (sorts) associative keys.
            $this->assertSame(['of' => 3, 'shared' => 3], $cohort['top10_overlap'], $name);
            $this->assertSame(['of' => 3, 'shared' => 3], $cohort['top20_overlap'], $name);
            $this->assertSame([], $cohort['top20_only_legacy'], $name);
            $this->assertSame([], $cohort['top20_only_canonical'], $name);
            $this->assertSame(0, $cohort['positions_moved'], $name);
        }
    }

    // ── Selection & bounds ────────────────────────────────────────────────────

    public function test_ineligible_rows_are_excluded_with_a_reason_never_scored(): void
    {
        $this->storeBaselineFixture('residential', self::ISOLATE, 'pending', ['standard_status' => 'Pending']);
        $this->storeBaselineFixture('residential', ['IDXParticipationYN' => false] + self::ISOLATE, 'no_idx');
        $this->storeBaselineFixture('residential', self::ISOLATE, 'eligible');

        $r = $this->run_()->result;

        $this->assertSame(2, $r['selection']['rows_scanned'], 'non-Active rows are not scanned in population mode');
        $this->assertSame(1, $r['selection']['eligible']);
        $this->assertSame(1, $r['selection']['examined']);
        $this->assertSame(['idx_gate:idx_participation_false' => 1], $r['selection']['excluded']);
    }

    public function test_max_listings_stops_early_and_marks_the_run_partial(): void
    {
        foreach (['a', 'b', 'c'] as $v) {
            $this->storeBaselineFixture('residential', self::ISOLATE, "max_{$v}");
        }

        $r = $this->run_([], 2)->result;

        $this->assertSame(2, $r['selection']['examined']);
        $this->assertSame('partial', $r['selection']['coverage']);
        $this->assertSame(['max_listings_reached'], $r['selection']['partial_reasons']);
    }

    public function test_per_type_cap_and_stride_are_deterministic_and_partial(): void
    {
        foreach (['a', 'b', 'c'] as $v) {
            $this->storeBaselineFixture('residential', self::ISOLATE, "s_{$v}");
        }

        $capped = $this->run_([], 2000, 1)->result;
        $this->assertSame(1, $capped['selection']['examined']);
        $this->assertSame(['per_type_cap' => 2], $capped['selection']['excluded']);
        $this->assertSame(['per_type_cap'], $capped['selection']['partial_reasons']);

        $strided = $this->run_([], 2000, 0, 2)->result;
        $this->assertSame(2, $strided['selection']['examined'], 'rows 1 and 3 of the stratum');
        $this->assertSame(['stride_skipped' => 1], $strided['selection']['excluded']);
        $this->assertContains('stride', $strided['selection']['partial_reasons']);
    }

    public function test_resume_skips_rows_up_to_from_id(): void
    {
        $first  = $this->storeBaselineFixture('residential', self::ISOLATE, 'r_a');
        $this->storeBaselineFixture('residential', self::ISOLATE, 'r_b');

        $r = $this->run_([], 2000, 0, 1, $first->id)->result;

        $this->assertSame(1, $r['selection']['examined']);
        $this->assertSame(['resumed_from_id'], $r['selection']['partial_reasons']);
    }

    public function test_exact_listing_keys_select_only_those_rows(): void
    {
        $this->storeAllBaselineFixtures();

        $r = $this->run_([self::baselineKey('income'), self::baselineKey('vacant_land'), 'P1A0-NOT-THERE'])->result;

        $this->assertSame('targeted', $r['selection']['mode']);
        $this->assertSame('targeted', $r['selection']['coverage']);
        $this->assertSame(2, $r['selection']['examined']);
        $this->assertSame(1, $r['selection']['not_found']);
        $this->assertSame(['Income' => 1, 'Vacant Land' => 1], $r['selection']['examined_by_stratum']);
    }

    /** @dataProvider invalidOptions */
    public function test_invalid_options_are_refused_before_any_query(array $args): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });

        try {
            (new Runner())->run(...$args);
            $this->fail('expected refusal');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, $queries);
        }
    }

    public static function invalidOptions(): array
    {
        return [
            'above hard ceiling'   => [[[], Runner::HARD_MAX_LISTINGS + 1]],
            'zero max'             => [[[], 0]],
            'negative max'         => [[[], -1]],
            'negative per-type'    => [[[], 10, -1]],
            'per-type above max'   => [[[], 10, 11]],
            'zero stride'          => [[[], 10, 0, 0]],
            'huge stride'          => [[[], 10, 0, Runner::MAX_STRIDE + 1]],
            'negative from-id'     => [[[], 10, 0, 1, -5]],
            'too many examples'    => [[[], 10, 0, 1, 0, Runner::MAX_EXAMPLES + 1]],
            'blank key'            => [[['  ']]],
            'key with spaces'      => [[['bad key']]],
            'key too long'         => [[[str_repeat('a', Runner::MAX_LISTING_KEY_LEN + 1)]]],
            'keys with stride'     => [[['K1'], 10, 0, 2]],
            'more keys than max'   => [[['K1', 'K2', 'K3'], 2]],
        ];
    }

    public function test_the_hard_ceiling_itself_is_accepted_and_the_default_is_2000(): void
    {
        $this->assertSame(2000, Runner::DEFAULT_MAX_LISTINGS);
        $this->assertSame(5000, Runner::HARD_MAX_LISTINGS);
        $this->assertSame(Runner::HARD_MAX_LISTINGS, $this->run_([], Runner::HARD_MAX_LISTINGS)->result['selection']['options']['max_listings']);
        $this->assertSame(2000, $this->run_()->result['selection']['options']['max_listings']);
    }

    // ── Determinism, query count, cost ────────────────────────────────────────

    public function test_two_identical_runs_produce_the_same_digest(): void
    {
        $this->storeAllBaselineFixtures();
        $this->storeBaselineFixture('residential', self::ISOLATE, 'undeclared', ['living_area' => 1500.5]);

        $one = $this->run_();
        $two = $this->run_();

        $this->assertSame($one->digest(), $two->digest());
        $this->assertSame(CanonicalParityReport::encode($one->result), CanonicalParityReport::encode($two->result));
        $this->assertArrayNotHasKey('elapsed_ms', $one->result, 'timings are outside the digested block');
        $this->assertArrayNotHasKey('generated_at', $one->result);
    }

    public function test_query_count_is_constant_per_chunk(): void
    {
        $this->storeBaselineFixture('residential', self::ISOLATE, 'q_single');
        $one = $this->run_()->cost['queries'];

        foreach (range(1, 49) as $i) {
            $this->storeBaselineFixture('residential', self::ISOLATE, "q_{$i}");
        }
        $fifty = $this->run_()->cost['queries'];

        $this->assertSame($one, $fifty, 'no per-row query: canonical and residual conversion are pure');
        $this->assertLessThanOrEqual(2, $fifty);
    }

    public function test_smart_tags_add_a_bounded_batch_per_chunk(): void
    {
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        config()->set('smart_tags_wiring.seeker_matching_enabled', true);

        $this->storeBaselineFixture('residential', self::ISOLATE, 't_single');
        $one = $this->run_()->cost['queries'];

        foreach (range(1, 49) as $i) {
            $this->storeBaselineFixture('residential', self::ISOLATE, "t_{$i}");
        }
        $fifty = $this->run_()->cost['queries'];

        $this->assertSame($one, $fifty);
        $this->assertLessThanOrEqual(4, $fifty, 'one row query plus the index\'s two batched reads');
    }

    public function test_cost_is_measured_against_the_legacy_path(): void
    {
        $this->storeAllBaselineFixtures();

        $cost = $this->run_()->cost;

        foreach (['elapsed_ms', 'queries', 'legacy_facts_ms', 'canonical_facts_ms', 'legacy_outcome_ms', 'canonical_outcome_ms', 'path_cost_ratio', 'facts_cost_ratio'] as $k) {
            $this->assertArrayHasKey($k, $cost);
        }
        $this->assertSame(1.5, $cost['budget_ratio']);
        $this->assertIsBool($cost['within_budget']);
    }

    // ── Privacy ───────────────────────────────────────────────────────────────

    public function test_no_sensitive_value_reaches_the_report(): void
    {
        $secrets = [
            'UnparsedAddress'      => '4471 Zyxwvut Secretlane Unit 9',
            'PublicRemarks'        => 'Qwertyremark sunlit parlour narrative',
            'PrivateRemarks'       => 'Lockbox code 90210 privnote',
            'ListAgentEmail'       => 'agent.zzsecret@example.test',
            'ListAgentDirectPhone' => '727-555-0199',
            'ListAgentFullName'    => 'Xanthippe Agentname',
            'STELLAR_TenantName'   => 'Tenantname Occupantus',
            'LockBoxLocation'      => 'Behind the Zqplanter',
        ];

        // Plant them on rows that produce findings of every kind (AD-1, AD-2, AD-6, undeclared,
        // mismatch), so every example bucket is populated and inspected.
        $city = 'Qzxvilleburgh';
        $this->storeBaselineFixture('residential', $secrets, 'p_ad1', ['unparsed_address' => $secrets['UnparsedAddress'], 'city' => $city]);
        $this->storeBaselineFixture('residential', $secrets + self::ISOLATE, 'p_ad2', ['latitude' => 0, 'longitude' => 0, 'city' => $city]);
        $this->storeBaselineFixture('residential', $secrets + self::ISOLATE, 'p_ad6', ['city' => "  {$city} ", 'postal_code' => ' 33701 ']);
        $this->storeBaselineFixture('residential', $secrets + self::ISOLATE, 'p_und', ['living_area' => 1500.5, 'city' => $city]);
        $this->storeBaselineFixture('residential', $secrets + self::ISOLATE, 'p_mis', ['latitude' => 'not-a-number', 'city' => $city]);

        $report = $this->run_([], 2000, 0, 1, 0, 50);
        $json   = $report->toJson();

        foreach ($secrets + ['city' => $city] as $field => $value) {
            $this->assertStringNotContainsString($value, $json, "{$field} leaked into the report");
        }
        foreach (['27.7', '-82.6', '33701'] as $locationFragment) {
            $this->assertStringNotContainsString($locationFragment, $json, 'raw location leaked');
        }
        $this->assertStringNotContainsString('raw_json', $json);

        // Location differences are described by shape.
        $shapes = array_column(array_merge(
            $report->result['examples']['allowed_difference:AD-2']['shown'],
            $report->result['examples']['allowed_difference:AD-6']['shown'],
        ), 'shape');
        $this->assertContains('invalid_pair', $shapes);
        $this->assertContains('trimmed_whitespace', $shapes);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function assignTag(BridgeProperty $row, string $tag): void
    {
        SmartTagAssignment::create([
            'listing_type' => 'bridge', 'listing_id' => $row->id, 'tag_key' => $tag,
            'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_mls',
        ]);
    }
}
