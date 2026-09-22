<?php

namespace Tests\Unit\Spatial\ChainRegistry;

use App\Services\Spatial\ChainRegistry\ChainMatcher;
use App\Services\Spatial\ChainRegistry\ChainMatchInput;
use App\Services\Spatial\ChainRegistry\ChainMatchReason;
use App\Services\Spatial\ChainRegistry\ChainMatchResult;
use App\Services\Spatial\ChainRegistry\ChainMembership;
use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\ChainRegistry\ChainRole;
use PHPUnit\Framework\TestCase;

/**
 * The matcher test matrix of design §16, plus the regressions for the locked decisions of
 * 2026-09-22. Every row is a literal {name, brand, brand QID, category, status}. Rows marked
 * "forced" put an imported category on a row PR 0 saw elsewhere, to prove the name / QID gate
 * holds even if a later Overture release re-classifies it. Pure; no container.
 */
class ChainMatcherTest extends TestCase
{
    private const WESTERN_UNION = 'Q861042';
    private const CITIBANK = 'Q857063';
    private const ELECTRIFY_AMERICA = 'Q59773555';
    private const QUEST = 'Q7271456';
    private const MOBIL = 'Q109676002';
    private const ARCO = 'Q304769';
    private const TARGET_OPTICAL = 'Q19903688';

    private ChainMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new ChainMatcher(ChainRegistry::fromArray(require __DIR__ . '/../../../../config/poi_chain_registry.php'));
    }

    private function match(?string $name, ?string $category, ?string $brand = null, ?string $qid = null, ?string $status = null): ChainMatchResult
    {
        return $this->matcher->match(new ChainMatchInput($name, $brand, $qid, $category, $status));
    }

    private function assertSingle(ChainMatchResult $r, string $brandKey, string $role, string $format, string $method): ChainMembership
    {
        $this->assertSame(ChainMatchResult::MATCHED, $r->outcome, json_encode($r->toArray()));
        $this->assertSame([$brandKey], $r->brandKeys());
        $m = $r->memberships[0];
        $this->assertSame($role, $m->role);
        $this->assertSame($format, $m->formatKey);
        $this->assertSame($method, $m->matchMethod);

        return $m;
    }

    private function assertRejected(ChainMatchResult $r, string $reason): void
    {
        $this->assertSame(ChainMatchResult::REJECTED, $r->outcome, json_encode($r->toArray()));
        $this->assertSame($reason, $r->reason);
        $this->assertSame([], $r->memberships);
    }

    private function assertCandidateDropped(ChainMatchResult $r, string $brandKey, string $reason): void
    {
        $this->assertNull($r->membership($brandKey), json_encode($r->toArray()));
        $this->assertSame($reason, $r->candidateRejections[$brandKey] ?? null, json_encode($r->toArray()));
    }

    // ── Identity ─────────────────────────────────────────────────────────────────────────────

    public function test_own_wikidata_id(): void
    {
        $this->assertSingle($this->match('Store 88', 'grocery_store', null, 'Q672170'), 'publix', ChainRole::STOREFRONT, 'supermarket', ChainMembership::METHOD_OWN_WIKIDATA);
    }

    public function test_own_wikidata_id_is_not_enough_in_a_disallowed_category(): void
    {
        $r = $this->match('Store 88', 'gym', null, 'Q672170');
        $this->assertSame(ChainMatchResult::NO_MATCH, $r->outcome);
        $this->assertCandidateDropped($r, 'publix', ChainMatchReason::CATEGORY_NOT_ALLOWED);
    }

    public function test_brand_alias_identifies_a_location_only_name(): void
    {
        $this->assertSingle($this->match('Polo', 'gas_station', 'RaceTrac'), 'racetrac', ChainRole::FUEL, 'fuel', ChainMembership::METHOD_BRAND_ALIAS);
        $this->assertSingle($this->match('Lake Mary', 'convenience_store', 'RaceTrac'), 'racetrac', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_BRAND_ALIAS);
    }

    public function test_name_alias_exact(): void
    {
        $this->assertSingle($this->match('Target', 'department_store'), 'target', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_name_alias_prefix(): void
    {
        $this->assertSingle($this->match('Publix Super Market at Shoppes of Oakleaf', 'grocery_store'), 'publix', ChainRole::STOREFRONT, 'supermarket', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_store_number_is_stripped(): void
    {
        $this->assertSingle($this->match('PUBLIX #1029', 'grocery_store'), 'publix', ChainRole::STOREFRONT, 'supermarket', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_sparse_chain_without_wikidata(): void
    {
        $this->assertSingle($this->match('Walmart Supercenter', 'superstore'), 'walmart', ChainRole::STOREFRONT, 'supercenter', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSingle($this->match('Whole Foods Market', 'grocery_store'), 'whole_foods', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_qid_shaped_brand_name_is_read_as_the_brand_qid(): void
    {
        // The v1 normaliser writes the QID into `brand` when brand.names is absent. It is not a
        // brand NAME (no alias hit), but it IS the brand QID.
        $this->assertSingle($this->match('Publix', 'grocery_store', 'Q672170'), 'publix', ChainRole::STOREFRONT, 'supermarket', ChainMembership::METHOD_OWN_WIKIDATA);
        $this->assertSingle($this->match('Store 88', 'grocery_store', 'Q672170'), 'publix', ChainRole::STOREFRONT, 'supermarket', ChainMembership::METHOD_OWN_WIKIDATA);
        $this->assertSingle($this->match('Publix', 'grocery_store', 'Q672170', 'Q672170'), 'publix', ChainRole::STOREFRONT, 'supermarket', ChainMembership::METHOD_OWN_WIKIDATA);
    }

    public function test_exclusion_qid_in_the_brand_name_field_is_still_an_exclusion(): void
    {
        // Reviewer D1: this used to match Publix.
        $this->assertRejected($this->match('PUBLIX #1029', 'grocery_store', 'Q861042'), ChainMatchReason::EXCLUSION_WIKIDATA);
        $this->assertRejected($this->match('7-Eleven', 'convenience_store', 'q857063'), ChainMatchReason::EXCLUSION_WIKIDATA);
    }

    public function test_foreign_store_qid_in_the_brand_name_field_is_still_a_conflict(): void
    {
        // Reviewer D1: Wawa's QID on a 7-Eleven row used to match 7-Eleven.
        $r = $this->match('7-Eleven', 'convenience_store', 'Q5936320');
        $this->assertCandidateDropped($r, 'seven_eleven', ChainMatchReason::BRAND_CONFLICT);
    }

    public function test_fuel_qid_in_the_brand_name_field_stays_diagnostic(): void
    {
        $r = $this->match('7-Eleven', 'gas_station', 'Q109676002');
        $this->assertSingle($r, 'seven_eleven', ChainRole::FUEL, 'fuel', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSame(ChainMatchReason::FUEL_BRAND_EXPECTED, $r->diagnostics[0]['code']);
    }

    public function test_a_fuel_qid_beside_a_store_qid_is_context_not_a_conflict(): void
    {
        // Decision 5: a fuel ID in either brand field never removes store identity.
        foreach ([['Q259340', self::MOBIL], [self::MOBIL, 'Q259340']] as [$brandField, $qidField]) {
            $r = $this->match('7-Eleven', 'gas_station', $brandField, $qidField);
            $this->assertSingle($r, 'seven_eleven', ChainRole::FUEL, 'fuel', ChainMembership::METHOD_OWN_WIKIDATA);
            $this->assertSame(ChainMatchReason::FUEL_BRAND_EXPECTED, $r->diagnostics[0]['code']);
        }
        // Two fuel IDs, or two store IDs, still contradict each other.
        $this->assertRejected($this->match('Shell', 'gas_station', self::ARCO, self::MOBIL), ChainMatchReason::CONFLICTING_WIKIDATA);
    }

    public function test_contradictory_brand_qids_are_refused(): void
    {
        $this->assertRejected($this->match('Publix', 'grocery_store', 'Q672170', 'Q41171672'), ChainMatchReason::CONFLICTING_WIKIDATA);
        $this->assertRejected($this->match('Publix', 'grocery_store', 'Q0'), ChainMatchReason::MALFORMED_WIKIDATA);
    }

    public function test_punctuation_variants(): void
    {
        $this->assertSame(['chick_fil_a'], $this->match('Chick-fil-A', 'fast_food_restaurant')->brandKeys());
        $this->assertSame(['wendys'], $this->match("Wendy's", 'burger_restaurant')->brandKeys());
        $this->assertSame(['seven_eleven'], $this->match('7ELEVEN', 'convenience_store')->brandKeys());
        $this->assertSame(['seven_eleven'], $this->match('7-Eleven', 'convenience_store')->brandKeys());
        $this->assertSame(['trader_joes'], $this->match("Trader Joe's", 'grocery_store')->brandKeys());
    }

    public function test_substring_is_never_a_match_route(): void
    {
        foreach (['Stop at Publix', 'The Walmart', 'Near Starbucks Plaza'] as $name) {
            $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match($name, 'grocery_store')->reason, $name);
        }
    }

    public function test_no_fuzzy_matching(): void
    {
        foreach (['Pubix', 'Wallmart', 'Starbuck', '7-Elevn'] as $name) {
            $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match($name, 'grocery_store')->reason, $name);
        }
    }

    // ── Exclusions ───────────────────────────────────────────────────────────────────────────

    public function test_western_union_row_named_for_publix(): void
    {
        $this->assertRejected($this->match('PUBLIX #1029', 'grocery_store', null, self::WESTERN_UNION), ChainMatchReason::EXCLUSION_WIKIDATA);
    }

    public function test_exclusion_beats_own_brand_name(): void
    {
        $this->assertRejected($this->match('Publix', 'grocery_store', 'Publix', self::WESTERN_UNION), ChainMatchReason::EXCLUSION_WIKIDATA);
    }

    public function test_atm_operator(): void
    {
        $this->assertRejected($this->match('ATM Walgreens', 'pharmacy', null, self::CITIBANK), ChainMatchReason::EXCLUSION_WIKIDATA);
        $this->assertRejected($this->match('ATM 7ELEVEN-FCTI', 'convenience_store'), ChainMatchReason::EXCLUSION_PATTERN);
    }

    public function test_ev_charger(): void
    {
        $this->assertRejected($this->match('Walmart', 'superstore', null, self::ELECTRIFY_AMERICA), ChainMatchReason::EXCLUSION_WIKIDATA);
        $this->assertRejected($this->match('Shell Recharge', 'gas_station'), ChainMatchReason::EXCLUSION_PATTERN);
    }

    public function test_quest_diagnostics(): void
    {
        $this->assertRejected($this->match('Quest Diagnostics at Walgreens', 'pharmacy', null, self::QUEST), ChainMatchReason::EXCLUSION_WIKIDATA);
    }

    public function test_wrong_category(): void
    {
        $r = $this->match('Publix', 'gym');
        $this->assertSame(ChainMatchReason::ALL_CANDIDATES_REJECTED, $r->reason);
        $this->assertCandidateDropped($r, 'publix', ChainMatchReason::CATEGORY_NOT_ALLOWED);
    }

    public function test_shopping_center_is_excluded_for_every_chain(): void
    {
        foreach (['Publix', 'Walmart', 'Target', 'Starbucks'] as $name) {
            $r = $this->match($name, 'shopping_center');
            $this->assertSame(ChainMatchResult::NO_MATCH, $r->outcome, $name);
            $this->assertSame([ChainMatchReason::CATEGORY_EXCLUDED], array_values($r->candidateRejections), $name);
        }
    }

    public function test_closed_name(): void
    {
        $this->assertRejected($this->match('7-Eleven - Closed', 'convenience_store'), ChainMatchReason::CLOSED_NAME);
    }

    public function test_chain_local_exclusion_after_identity(): void
    {
        $r = $this->match('Publix Liquors', 'grocery_store');
        $this->assertCandidateDropped($r, 'publix', ChainMatchReason::CHAIN_EXCLUSION);
        $r = $this->match('Walmart Bakery', 'grocery_store', null, null);
        $this->assertCandidateDropped($r, 'walmart', ChainMatchReason::CHAIN_EXCLUSION);
    }

    public function test_foreign_store_identity_is_a_conflict(): void
    {
        // A non-fuel foreign brand name or QID on the row drops that candidate.
        $r = $this->match('Walgreens', 'pharmacy', 'CVS Pharmacy');
        $this->assertSingle($r, 'cvs', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_BRAND_ALIAS);
        $this->assertCandidateDropped($r, 'walgreens', ChainMatchReason::BRAND_CONFLICT);

        $r = $this->match('Publix', 'grocery_store', null, 'Q41171672');
        $this->assertSingle($r, 'aldi', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_OWN_WIKIDATA);
        $this->assertCandidateDropped($r, 'publix', ChainMatchReason::BRAND_CONFLICT);

        // An unrecognised brand (a tenant PR 0 saw, e.g. Dutch Bros) is foreign too.
        $r = $this->match('Starbucks', 'coffee_shop', 'Dutch Bros');
        $this->assertCandidateDropped($r, 'starbucks', ChainMatchReason::BRAND_CONFLICT);
    }

    public function test_row_level_invalid_input_fails_closed(): void
    {
        $this->assertRejected($this->match('Publix', 'bakery'), ChainMatchReason::CATEGORY_NOT_IMPORTED);
        $this->assertRejected($this->match('Publix', null), ChainMatchReason::CATEGORY_NOT_IMPORTED);
        $this->assertRejected($this->match('Publix', 'grocery_store', null, null, 'permanently_closed'), ChainMatchReason::STATUS_EXCLUDED);
        $this->assertRejected($this->match('Publix', 'grocery_store', null, null, 'temporarily_closed'), ChainMatchReason::STATUS_EXCLUDED);
        $this->assertRejected($this->match('Publix', 'grocery_store', null, 'not-a-qid'), ChainMatchReason::MALFORMED_WIKIDATA);
        $this->assertTrue($this->match('Publix', 'grocery_store', null, null, 'open')->isMatch());
        $this->assertTrue($this->match('Publix', 'grocery_store', null, null, null)->isMatch());
    }

    // ── False positives ──────────────────────────────────────────────────────────────────────

    public function test_shell_island_defensive_fixture(): void
    {
        $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match('Shell Island', 'restaurant')->reason);
        $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match('Shell Island', 'gas_station')->reason, 'forced');
    }

    public function test_ronald_mcdonald_house(): void
    {
        $this->assertRejected($this->match('Ronald McDonald House Charities', 'fast_food_restaurant'), ChainMatchReason::EXCLUSION_PATTERN);
    }

    public function test_target_specialty_products_defensive_fixture(): void
    {
        $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match('Target Specialty Products', 'department_store')->reason);
    }

    public function test_target_optical(): void
    {
        $this->assertRejected($this->match('Target Optical', 'department_store', null, self::TARGET_OPTICAL), ChainMatchReason::EXCLUSION_PATTERN);

        // Even without the telling name, the department QID can never make a storefront.
        $r = $this->match('Store 1234', 'department_store', null, self::TARGET_OPTICAL);
        $this->assertSame(ChainMatchResult::NO_MATCH, $r->outcome);
        $this->assertCandidateDropped($r, 'target', ChainMatchReason::UNSUPPORTED_FORMAT);
        $r = $this->match('Target', 'department_store', null, self::TARGET_OPTICAL);
        $this->assertCandidateDropped($r, 'target', ChainMatchReason::UNSUPPORTED_FORMAT);
    }

    public function test_royal_shell_real_estate(): void
    {
        $this->assertRejected($this->match('Royal Shell Real Estate', 'gas_station'), ChainMatchReason::EXCLUSION_PATTERN);
    }

    public function test_labcorp_at_walgreens(): void
    {
        $this->assertRejected($this->match('Labcorp at Walgreens', 'pharmacy'), ChainMatchReason::EXCLUSION_PATTERN);
    }

    public function test_daytona_international_speedway(): void
    {
        $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match('Daytona International Speedway', 'convenience_store')->reason);
    }

    // ── Fuel-brand separation (decision 5) ───────────────────────────────────────────────────

    public function test_seven_eleven_with_mobil_still_matches_and_records_expected_fuel(): void
    {
        $r = $this->match('7-Eleven', 'gas_station', null, self::MOBIL);
        $this->assertSingle($r, 'seven_eleven', ChainRole::FUEL, 'fuel', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSame([['brand_key' => 'seven_eleven', 'code' => ChainMatchReason::FUEL_BRAND_EXPECTED, 'fuel_brand' => 'mobil']], $r->diagnostics);

        $r = $this->match('7-Eleven', 'convenience_store', 'Mobil');
        $this->assertSingle($r, 'seven_eleven', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_mobil_alone_creates_no_chain(): void
    {
        $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match('Mobil', 'gas_station', null, self::MOBIL)->reason);
        $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match('Mobil', 'gas_station', 'Mobil')->reason);
        $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match('Store 12', 'convenience_store', 'Mobil', self::MOBIL)->reason);
    }

    public function test_shell_with_a_foreign_fuel_brand_is_not_rejected_for_it(): void
    {
        $r = $this->match('Shell', 'gas_station', null, self::ARCO);
        $this->assertSingle($r, 'shell', ChainRole::STOREFRONT, 'station', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSame([['brand_key' => 'shell', 'code' => ChainMatchReason::FUEL_BRAND_CONFLICT, 'fuel_brand' => 'arco']], $r->diagnostics);
        $this->assertSame([], $r->candidateRejections);

        $r = $this->match('Shell', 'gas_station', 'Arco');
        $this->assertSingle($r, 'shell', ChainRole::STOREFRONT, 'station', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_fuel_brand_never_rescues_a_row_that_fails_other_rules(): void
    {
        // Other chain evidence and category rules still decide.
        $r = $this->match('Shell', 'fast_food_restaurant', null, self::ARCO);
        $this->assertCandidateDropped($r, 'shell', ChainMatchReason::CATEGORY_NOT_ALLOWED);
    }

    public function test_foreign_store_identity_remains_a_conflict_beside_fuel(): void
    {
        // Shell's own QID on a 7-Eleven row is a STORE/CHAIN identity, not a listed fuel brand.
        $r = $this->match('7-Eleven', 'gas_station', null, 'Q110716465');
        $this->assertCandidateDropped($r, 'seven_eleven', ChainMatchReason::BRAND_CONFLICT);
        $this->assertSingle($r, 'shell', ChainRole::STOREFRONT, 'station', ChainMembership::METHOD_OWN_WIKIDATA);
    }

    // ── Department vs storefront ─────────────────────────────────────────────────────────────

    public function test_walmart_grocery_is_a_department_not_a_neighborhood_market(): void
    {
        $m = $this->assertSingle($this->match('Walmart', 'grocery_store'), 'walmart', ChainRole::DEPARTMENT, 'grocery_department', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSame(ChainRole::STATUS_STOREFRONT_UNCONFIRMED, $m->storefrontStatus());
        $this->assertFalse($m->isStorefront());
    }

    public function test_walmart_neighborhood_market_is_a_storefront_only_by_name(): void
    {
        $m = $this->assertSingle($this->match('Walmart Neighborhood Market', 'grocery_store'), 'walmart', ChainRole::STOREFRONT, 'neighborhood_market', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSame(ChainRole::STATUS_STOREFRONT, $m->storefrontStatus());
    }

    public function test_publix_and_winn_dixie_pharmacy_are_departments(): void
    {
        foreach (['Publix Pharmacy' => 'publix', 'Publix' => 'publix', 'Winn-Dixie Pharmacy' => 'winn_dixie'] as $name => $key) {
            $m = $this->assertSingle($this->match($name, 'pharmacy'), $key, ChainRole::DEPARTMENT, 'pharmacy_department', ChainMembership::METHOD_NAME_ALIAS);
            $this->assertSame(ChainRole::STATUS_STOREFRONT_UNCONFIRMED, $m->storefrontStatus(), $name);
        }
    }

    public function test_walmart_pharmacy_is_a_department(): void
    {
        $this->assertSingle($this->match('Walmart Pharmacy', 'pharmacy'), 'walmart', ChainRole::DEPARTMENT, 'pharmacy_department', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_shell_station_shop_is_a_department(): void
    {
        $this->assertSingle($this->match('Shell', 'convenience_store'), 'shell', ChainRole::DEPARTMENT, 'station_store', ChainMembership::METHOD_NAME_ALIAS);
    }

    // ── Formats ──────────────────────────────────────────────────────────────────────────────

    public function test_walmart_formats(): void
    {
        $this->assertSingle($this->match('Walmart Supercenter', 'department_store'), 'walmart', ChainRole::STOREFRONT, 'supercenter', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSingle($this->match('Walmart', 'superstore'), 'walmart', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_walmart_fuel_is_not_a_location(): void
    {
        $r = $this->match('Walmart Fuel Station', 'gas_station');
        $this->assertCandidateDropped($r, 'walmart', ChainMatchReason::CATEGORY_NOT_ALLOWED);
    }

    public function test_seven_eleven_store_and_fuel_rows(): void
    {
        $this->assertSingle($this->match('7-Eleven', 'convenience_store'), 'seven_eleven', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSingle($this->match('7-Eleven Fuel', 'gas_station'), 'seven_eleven', ChainRole::FUEL, 'fuel', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_wawa_store_and_fuel_rows(): void
    {
        $this->assertSingle($this->match('Wawa', 'convenience_store'), 'wawa', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertSingle($this->match('Wawa', 'gas_station'), 'wawa', ChainRole::FUEL, 'fuel', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_cvs_inside_target_answers_cvs_only(): void
    {
        $r = $this->match('CVS Pharmacy inside Target', 'pharmacy');
        $this->assertSingle($r, 'cvs', ChainRole::STOREFRONT, 'store_in_target', ChainMembership::METHOD_NAME_ALIAS);
        $this->assertArrayNotHasKey('target', $r->candidateRejections, 'Target was never a candidate');
        $this->assertSingle($this->match('CVS Pharmacy', 'drugstore'), 'cvs', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_NAME_ALIAS);
    }

    public function test_target_pharmacy_is_not_target(): void
    {
        $r = $this->match('Target Pharmacy', 'pharmacy', 'Target');
        $this->assertSame(ChainMatchResult::NO_MATCH, $r->outcome);
        $this->assertCandidateDropped($r, 'target', ChainMatchReason::CHAIN_EXCLUSION);
    }

    // ── Co-branding ──────────────────────────────────────────────────────────────────────────

    public function test_compound_name_produces_both_memberships(): void
    {
        foreach (['7-Eleven / Speedway', '7-ELEVEN/SPEEDWAY #46807', 'Speedway 7-Eleven'] as $name) {
            $r = $this->match($name, 'convenience_store');
            $this->assertSame(['seven_eleven', 'speedway'], $r->brandKeys(), $name);
            $this->assertSame(['speedway'], $r->membership('seven_eleven')->coBrandWith);
            $this->assertSame(['seven_eleven'], $r->membership('speedway')->coBrandWith);
        }
    }

    public function test_name_alias_plus_partner_brand_produces_both(): void
    {
        $r = $this->match('7-Eleven', 'convenience_store', 'Speedway');
        $this->assertSame(['seven_eleven', 'speedway'], $r->brandKeys());
        $r = $this->match('Speedway', 'gas_station', '7-Eleven');
        $this->assertSame(['seven_eleven', 'speedway'], $r->brandKeys());
        $this->assertSame(ChainRole::FUEL, $r->membership('speedway')->role);
    }

    public function test_western_union_row_named_seven_eleven_speedway_produces_neither(): void
    {
        $this->assertRejected($this->match('7-ELEVEN/SPEEDWAY #46807', 'convenience_store', null, self::WESTERN_UNION), ChainMatchReason::EXCLUSION_WIKIDATA);
    }

    public function test_a_street_name_is_not_a_co_brand(): void
    {
        // Reviewer R1: compound names match the whole name, never a word prefix.
        $r = $this->match('7-Eleven Speedway Blvd', 'convenience_store');
        $this->assertSame(['seven_eleven'], $r->brandKeys());
        $this->assertSame([], $r->membership('seven_eleven')->coBrandWith);
        $this->assertSame(ChainMatchReason::NO_CHAIN, $this->match('Speedway 7-Eleven Plaza', 'convenience_store')->reason);
    }

    public function test_contradictory_brand_fields_are_not_co_brand_evidence(): void
    {
        // Reviewer R2: the brand name says Speedway, the brand QID says 7-Eleven. 7-Eleven's only
        // identity is contradicted by the row's own brand name, so it cannot evidence a co-brand.
        $r = $this->match('Speedway', 'convenience_store', 'Speedway', 'Q259340');
        $this->assertSame(ChainMatchResult::AMBIGUOUS, $r->outcome);
        $this->assertSame([], $r->memberships);
    }

    public function test_plain_speedway_is_not_a_co_brand(): void
    {
        $r = $this->match('Speedway', 'convenience_store');
        $this->assertSame(['speedway'], $r->brandKeys());
        $this->assertSame([], $r->membership('speedway')->coBrandWith);
    }

    public function test_co_branding_cannot_come_from_proximity(): void
    {
        // Two separate rows at one address are matched independently: the input carries no
        // coordinate or address, so no second membership can be inferred for either.
        $a = $this->match('7-Eleven', 'convenience_store');
        $b = $this->match('Speedway', 'gas_station');
        $this->assertSame([], $a->membership('seven_eleven')->coBrandWith);
        $this->assertSame([], $b->membership('speedway')->coBrandWith);
        $this->assertSame(
            ['name', 'brandName', 'brandWikidata', 'categoryKey', 'operatingStatus'],
            array_map(static fn (\ReflectionParameter $p): string => $p->getName(), (new \ReflectionMethod(ChainMatchInput::class, '__construct'))->getParameters())
        );
    }

    public function test_declared_pair_without_row_evidence_is_ambiguous(): void
    {
        // Speedway brand + 7-Eleven's own QID, no name: both chains have evidence, neither route
        // of co-brand evidence holds on this row, so nothing is guessed.
        $r = $this->match(null, 'convenience_store', 'Speedway', 'Q259340');
        $this->assertSame(ChainMatchResult::AMBIGUOUS, $r->outcome);
        $this->assertSame(['seven_eleven', 'speedway'], $r->ambiguousCandidates);
        $this->assertSame([], $r->memberships);
    }

    public function test_undeclared_pair_cannot_both_survive(): void
    {
        // A licensed Starbucks named for its host: the host's candidate meets a foreign brand.
        $r = $this->match('Publix', 'coffee_shop', 'Starbucks');
        $this->assertSingle($r, 'starbucks', ChainRole::STOREFRONT, 'store', ChainMembership::METHOD_BRAND_ALIAS);
        $this->assertCandidateDropped($r, 'publix', ChainMatchReason::BRAND_CONFLICT);

        $r = $this->match('Wawa', 'convenience_store', 'Speedway');
        $this->assertCandidateDropped($r, 'wawa', ChainMatchReason::BRAND_CONFLICT);
        $this->assertSame(['speedway'], $r->brandKeys());
    }

    // ── Determinism ──────────────────────────────────────────────────────────────────────────

    public function test_reordered_registry_gives_identical_results(): void
    {
        $config = require __DIR__ . '/../../../../config/poi_chain_registry.php';
        $reordered = $config;
        $reordered['chains'] = array_reverse($reordered['chains'], true);
        foreach ($reordered['chains'] as $key => $chain) {
            $reordered['chains'][$key]['aliases'] = array_reverse($chain['aliases']);
            $reordered['chains'][$key]['formats'] = array_reverse($chain['formats'], true);
            $reordered['chains'][$key]['allowed_categories'] = array_reverse($chain['allowed_categories'], true);
        }
        $reordered['global']['exclusion_name_patterns'] = array_reverse($config['global']['exclusion_name_patterns'], true);
        $other = new ChainMatcher(ChainRegistry::fromArray($reordered));

        foreach ($this->corpus() as $row) {
            $input = new ChainMatchInput(...$row);
            $this->assertSame(
                $this->matcher->match($input)->toArray(),
                $other->match($input)->toArray(),
                json_encode($row)
            );
        }
    }

    public function test_same_row_same_answer(): void
    {
        foreach ($this->corpus() as $row) {
            $input = new ChainMatchInput(...$row);
            $this->assertSame($this->matcher->match($input)->toArray(), $this->matcher->match($input)->toArray());
        }
    }

    /** @return list<array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: ?string}> */
    private function corpus(): array
    {
        return [
            ['PUBLIX #1029', null, null, 'grocery_store', null],
            ['PUBLIX #1029', null, self::WESTERN_UNION, 'grocery_store', null],
            ['Polo', 'RaceTrac', null, 'gas_station', 'open'],
            ['7-Eleven', null, self::MOBIL, 'gas_station', null],
            ['Shell', null, self::ARCO, 'gas_station', null],
            ['7-Eleven / Speedway', null, null, 'convenience_store', null],
            [null, 'Speedway', 'Q259340', 'convenience_store', null],
            ['Walmart', null, null, 'grocery_store', null],
            ['Walmart Neighborhood Market', null, null, 'grocery_store', null],
            ['Walmart Supercenter', null, null, 'superstore', null],
            ['CVS Pharmacy inside Target', null, null, 'pharmacy', null],
            ['Publix', 'Starbucks', null, 'coffee_shop', null],
            ['Target', null, self::TARGET_OPTICAL, 'department_store', null],
            ['Publix Starbucks', null, null, 'coffee_shop', null],
            ['Store 88', null, 'Q672170', 'gym', null],
        ];
    }
}
