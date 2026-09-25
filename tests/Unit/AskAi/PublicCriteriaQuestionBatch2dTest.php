<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use App\Support\OfferListing\CriteriaPrivacyPolicy;
use Tests\TestCase;

/**
 * Batch 2d — deterministic Ask AI for buyer and tenant SEARCH CRITERIA.
 *
 * WHAT IS DIFFERENT ABOUT THESE TWO ROLES. A seller or landlord listing describes a
 * property, and SnapshotFactVisibility publishes an approved tier of its facts. A buyer or
 * tenant listing describes a PERSON'S SEARCH, and decision D2 makes every one of its facts
 * owner-only — deliberately, because the same context carries that person's income, credit,
 * eviction and felony answers, service and support animal status, accessibility needs,
 * household size, home address and workplace.
 *
 * So these roles have no public tier to draw on, and Batch 2d does not give them one.
 * PUBLIC_BUYER_CRITERIA and PUBLIC_TENANT_CRITERIA are explicit, hand-written allowlists of
 * the context keys this one surface may restate, each naming the row the listing page
 * already publishes. D2 is untouched and this file asserts that directly.
 *
 * EVERY TEST HAS TWO HALVES where it can: the criterion is answered exactly, AND the
 * qualification data beside it is unreachable. A suite that only proved the first would pass
 * against a surface that published everything; one that only proved the second would pass
 * against a surface that published nothing.
 */
class PublicCriteriaQuestionBatch2dTest extends TestCase
{
    private AskAiPublicPropertyQuestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiPublicPropertyQuestionService();
    }

    /** @return array<string,string> question id => answer */
    private function ask(string $role, array $listing, array $meta = []): array
    {
        $out = [];
        foreach ($this->service->forListing($role, ['listing' => $listing + ['property_type' => 'Residential']], $meta) as $q) {
            $out[$q['id']] = $q['answer'];
        }

        return $out;
    }

    private function answer(string $role, string $id, array $listing, array $meta = []): ?string
    {
        return $this->ask($role, $listing, $meta)[$id] ?? null;
    }

    /* ================================================================== */
    /* BUYER — each public criterion                                       */
    /* ================================================================== */

    public function test_buyer_budget_states_what_they_intend_to_spend(): void
    {
        $this->assertSame(
            'The buyer is looking for a purchase price up to $450,000.',
            $this->answer('buyer', 'buyer_budget', ['max_price' => '450000'], ['maximum_budget' => '450000'])
        );
    }

    public function test_buyer_budget_hides_when_the_legacy_budget_fields_disagree(): void
    {
        // The page prints Max Purchase Budget and Max Purchase Price as separate rows from
        // four keys. When they disagree it shows both and lets a reader judge; one sentence
        // cannot, and choosing one would publish a budget the listing does not claim.
        $this->assertNull($this->answer('buyer', 'buyer_budget',
            ['max_price' => '450000'],
            ['maximum_budget' => '450000', 'max_purchase_price' => '525000']
        ));
        $this->assertNull($this->answer('buyer', 'buyer_budget',
            ['max_price' => '450000'],
            ['maximum_budget' => '450000', 'buyer_budget' => '400000']
        ));
    }

    public function test_buyer_budget_tolerates_formatting_but_not_an_unreadable_figure(): void
    {
        // "$450,000" and "450000" are one amount; hiding over a comma would be a formatting
        // bug wearing a safety rule. A legacy value that is not a number is a real doubt.
        $this->assertNotNull($this->answer('buyer', 'buyer_budget',
            ['max_price' => '450000'],
            ['maximum_budget' => '450000', 'max_purchase_price' => '$450,000']
        ));
        $this->assertNull($this->answer('buyer', 'buyer_budget',
            ['max_price' => '450000'],
            ['maximum_budget' => '450000', 'max_purchase_price' => 'negotiable']
        ));
    }

    public function test_buyer_areas_name_only_published_area_lists(): void
    {
        $this->assertSame(
            'The buyer is looking in Seminole and St. Petersburg, and in Pinellas County.',
            $this->answer('buyer', 'buyer_search_areas', [
                'cities'   => 'Seminole, St. Petersburg',
                'counties' => 'Pinellas',
            ])
        );
    }

    public function test_buyer_areas_fall_back_to_counties_without_duplicating_the_question(): void
    {
        $answers = $this->ask('buyer', ['counties' => 'Pinellas']);

        $this->assertSame('The buyer is looking in Pinellas County.', $answers['buyer_search_areas_counties'] ?? null);
        $this->assertArrayNotHasKey('buyer_search_areas', $answers);

        // And the narrower one steps aside once cities exist, so one question, one answer.
        $both = $this->ask('buyer', ['cities' => 'Seminole', 'counties' => 'Pinellas']);
        $this->assertArrayHasKey('buyer_search_areas', $both);
        $this->assertArrayNotHasKey('buyer_search_areas_counties', $both);
    }

    public function test_buyer_property_type_beds_baths_sqft_and_acreage(): void
    {
        $answers = $this->ask('buyer', [
            'property_type' => 'Residential',
            'bedrooms'      => '3',
            'bathrooms'     => '2.5',
            'square_feet'   => '1800',
            'total_acreage' => '1/4 to less than 1/2 acre',
        ], ['bedrooms' => '3', 'bathrooms' => '2.5']);

        $this->assertSame('The buyer is looking for this property type: Residential.', $answers['buyer_property_type']);
        $this->assertSame('The buyer is looking for at least 3 bedrooms.', $answers['buyer_bedrooms']);
        $this->assertSame('The buyer is looking for at least 2.5 bathrooms.', $answers['buyer_bathrooms']);
        $this->assertSame('The buyer is looking for at least 1,800 heated square feet.', $answers['buyer_square_feet']);
        $this->assertSame('The buyer is looking for a lot of 1/4 to less than 1/2 acre.', $answers['buyer_acreage']);
    }

    public function test_buyer_acreage_hides_for_a_value_outside_the_forms_own_bands(): void
    {
        $this->assertNull($this->answer('buyer', 'buyer_acreage', ['total_acreage' => 'Non-Applicable']));
        $this->assertNull($this->answer('buyer', 'buyer_acreage', ['total_acreage' => 'big']));
    }

    public function test_buyer_pool_and_garage_stay_yes_no_and_never_become_counts(): void
    {
        $yes = $this->ask('buyer', ['pool' => 'Yes', 'garage' => 'Yes'], ['garage_needed' => 'Yes']);
        $this->assertSame('The buyer is looking for a property with a pool.', $yes['buyer_pool']);
        $this->assertSame('The buyer is looking for a property with a garage.', $yes['buyer_garage']);

        $no = $this->ask('buyer', ['pool' => 'No', 'garage' => 'No'], ['garage_needed' => 'No']);
        $this->assertSame('The buyer has not listed a pool as a requirement.', $no['buyer_pool']);
        $this->assertSame('The buyer has not listed a garage as a requirement.', $no['buyer_garage']);

        // The context cascades garage_needed -> other_garage -> other_garage_needed, and the
        // last two are free text that can hold a COUNT. Without the Yes/No field having
        // answered, a "2" would otherwise be read as a flag.
        $this->assertNull($this->answer('buyer', 'buyer_garage', ['garage' => '2'], ['other_garage_needed' => '2']));
    }

    public function test_buyer_timeframe_uses_the_forms_own_closed_option_list(): void
    {
        $this->assertSame(
            'The buyer is looking to close within 3 months.',
            $this->answer('buyer', 'buyer_timeframe', ['closing_date' => 'Within 3 Months'])
        );
        $this->assertSame(
            'The buyer is looking to close as soon as possible.',
            $this->answer('buyer', 'buyer_timeframe', ['closing_date' => 'ASAP (Ready Now)'])
        );
        // A legacy free-text or date value is not restated as a timeframe it may not mean.
        $this->assertNull($this->answer('buyer', 'buyer_timeframe', ['closing_date' => '2027-03-01']));
        $this->assertNull($this->answer('buyer', 'buyer_timeframe', ['closing_date' => 'soonish']));
    }

    public function test_buyer_features_come_from_structured_selections_only(): void
    {
        $answer = $this->answer('buyer', 'buyer_property_features',
            ['non_negotiable_amenities' => '["Fenced Yard"]'],
            [
                'non_negotiable_amenities'       => json_encode(['Fenced Yard', 'Other']),
                'other_non_negotiable_amenities' => 'Impact windows',
                'view_preference'                => json_encode(['Water']),
            ]
        );

        $this->assertSame('The buyer is looking for these features: Fenced Yard, Impact windows, Water.', $answer);
    }

    public function test_buyer_missing_values_hide_their_questions(): void
    {
        // property_type is explicitly NULL: these assertions are about a listing with NO
        // stored values, and the shared ask() helper supplies a default type for every other
        // test in this file. Leaving that default here would make the property-type question
        // itself available and the premise would no longer hold.
        $this->assertSame([], $this->ask('buyer', ['property_type' => null]));
        $this->assertSame([], $this->ask('buyer', ['property_type' => null, 'max_price' => '', 'bedrooms' => null, 'cities' => '[]']));
    }

    /* ================================================================== */
    /* TENANT — each public criterion                                      */
    /* ================================================================== */

    public function test_tenant_max_rent_comes_from_the_published_rent_budget(): void
    {
        $this->assertSame(
            'The tenant is looking for rent up to $2,500.',
            $this->answer('tenant', 'tenant_max_rent', [], ['budget' => '2500'])
        );
    }

    public function test_tenant_max_rent_is_never_derived_from_income_or_move_in_funds(): void
    {
        // Income and deposit capacity are not in the tenant criteria catalog, so no amount
        // of them produces a rent answer.
        $answers = $this->ask('tenant', [
            // No property type: this assertion is about which criteria can produce a rent
            // answer, and the property-type question would otherwise appear alongside them.
            'property_type'           => null,
            'monthly_income'          => '9000',
            'security_deposit_budget' => '5000',
            'move_in_funds_available' => '8000',
        ], [
            'monthly_income' => '9000', 'security_deposit_budget' => '5000', 'move_in_funds_available' => '8000',
        ]);


        $this->assertSame([], $answers);
    }

    public function test_tenant_max_rent_hides_when_the_rent_fallback_keys_disagree(): void
    {
        $this->assertNull($this->answer('tenant', 'tenant_max_rent',
            [],
            ['budget' => '2500', 'desired_rental_amount' => '3200']
        ));

        // But a listing that stored only a later key in the page's own fallback order is
        // still answerable — the page publishes it, so hiding it would be wrong.
        $this->assertSame(
            'The tenant is looking for rent up to $2,500.',
            $this->answer('tenant', 'tenant_max_rent', [], ['desired_rental_amount' => '2500'])
        );
    }

    public function test_tenant_areas_include_zip_codes_and_never_a_private_address(): void
    {
        $this->assertSame(
            'The tenant is looking in Seminole, in Pinellas County, and in ZIP codes 33772 and 33776.',
            $this->answer('tenant', 'tenant_search_areas', [
                'cities'    => 'Seminole',
                'counties'  => 'Pinellas',
                'zip_codes' => '33772, 33776',
                // In the context and never readable: the tenant's own address and workplace.
                'address'                 => '12 Private Street',
                'commute_destination_zip' => '33701',
            ])
        );
    }

    public function test_tenant_zip_codes_must_look_like_zip_codes(): void
    {
        $this->assertSame(
            'The tenant is looking in Seminole.',
            $this->answer('tenant', 'tenant_search_areas', ['cities' => 'Seminole', 'zip_codes' => 'somewhere nice'])
        );
    }

    public function test_tenant_property_type_beds_baths_sqft_and_acreage(): void
    {
        $answers = $this->ask('tenant', [
            'property_type' => 'Residential Property',
            'bedrooms'      => '2',
            'bathrooms'     => '1',
            'square_feet'   => '900',
            'total_acreage' => '0 to less than 1/4 acre',
        ], ['bedrooms' => '2', 'bathrooms' => '1']);

        $this->assertSame('The tenant is looking for this property type: Residential Property.', $answers['tenant_property_type']);
        $this->assertSame('The tenant is looking for at least 2 bedrooms.', $answers['tenant_bedrooms']);
        $this->assertSame('The tenant is looking for at least 1 bathroom.', $answers['tenant_bathrooms']);
        $this->assertSame('The tenant is looking for at least 900 heated square feet.', $answers['tenant_square_feet']);
        $this->assertSame('The tenant is looking for a lot of 0 to less than 1/4 acre.', $answers['tenant_acreage']);
    }

    public function test_tenant_lease_term_uses_the_desired_term_and_not_a_property_restriction(): void
    {
        $this->assertSame(
            'The tenant is looking for a lease term of 12 Months or 24 Months.',
            $this->answer('tenant', 'tenant_lease_term',
                ['desired_lease_length' => '12 Months, 24 Months'],
                ['desired_lease_length' => json_encode(['12 Months', '24 Months'])]
            )
        );

        // min_lease_period is a SELLER / HOA restriction on how briefly a property may be
        // let — another party's rule about a property, not this tenant's preference. It is
        // not a tenant context key at all, and supplying it answers nothing.
        $this->assertArrayNotHasKey('min_lease_period', AskAiContextBuilderService::CANONICAL_SOURCE_MAP['tenant']);
        $this->assertNull($this->answer('tenant', 'tenant_lease_term',
            ['min_lease_period' => '7 Months'],
            ['min_lease_period' => '7 Months']
        ));
    }

    public function test_tenant_lease_term_requires_the_desired_lease_length_field_itself(): void
    {
        // The context cascades desired_lease_length -> lease_for, and "Leasing For" answers
        // a different question ("Residential", "Commercial").
        $this->assertNull($this->answer('tenant', 'tenant_lease_term',
            ['desired_lease_length' => 'Residential'],
            ['lease_for' => json_encode(['Residential'])]
        ));
    }

    public function test_tenant_move_in_window_reads_both_dates(): void
    {
        $this->assertSame(
            'The tenant is looking to move in between January 15, 2027 and March 1, 2027.',
            $this->answer('tenant', 'tenant_move_in', [
                'move_in_date_earliest' => '2027-01-15',
                'move_in_date_latest'   => '2027-03-01',
            ])
        );

        $this->assertSame(
            'The tenant is looking to move in on or after January 15, 2027.',
            $this->answer('tenant', 'tenant_move_in', ['move_in_date_earliest' => '2027-01-15'])
        );

        $this->assertSame(
            'The tenant is looking to move in on January 15, 2027.',
            $this->answer('tenant', 'tenant_move_in', [
                'move_in_date_earliest' => '2027-01-15',
                'move_in_date_latest'   => '2027-01-15',
            ])
        );
    }

    public function test_tenant_move_in_window_refuses_a_backwards_or_unparseable_window(): void
    {
        // A window whose end precedes its start is a data problem; reversing it silently
        // would publish a window the tenant never stated.
        $this->assertNull($this->answer('tenant', 'tenant_move_in', [
            'move_in_date_earliest' => '2027-03-01',
            'move_in_date_latest'   => '2027-01-15',
        ]));
        $this->assertNull($this->answer('tenant', 'tenant_move_in', ['move_in_date_earliest' => 'whenever']));
    }

    public function test_tenant_pets_answers_the_housing_requirement_and_nothing_else(): void
    {
        $answers = $this->ask('tenant', [
            // Present in the context and unreachable: these are accommodation disclosures,
            // not pets, and are not part of the pet-friendly housing requirement.
            'service_animal'             => 'Yes',
            'emotional_support_animal'   => 'Yes',
            'accessibility_requirements' => 'Ground floor required',
        ], ['pets' => 'Yes']);

        $this->assertSame('The tenant is looking for a property that allows pets.', $answers['tenant_pets']);
        foreach ($answers as $answer) {
            $this->assertStringNotContainsStringIgnoringCase('service', $answer);
            $this->assertStringNotContainsStringIgnoringCase('support animal', $answer);
            $this->assertStringNotContainsStringIgnoringCase('ground floor', $answer);
        }

        $this->assertSame(
            'The tenant has not listed a need for pets to be allowed.',
            $this->answer('tenant', 'tenant_pets', [], ['pets' => 'No'])
        );
    }

    public function test_tenant_furnishings_reads_the_furnishings_field_despite_its_key_name(): void
    {
        // `tenant_require` holds "Furnished" / "Unfurnished" / "Turnkey" from the form's own
        // "Furnishings Needed" select. Its name reads like an occupant requirement and is
        // not one — the landlord page published it as "Tenant Type Required" until Fair
        // Housing Phase 3 relabelled it.
        $this->assertSame(
            ['tenant_require'],
            AskAiPublicPropertyQuestionService::publicCriteriaMetaSources()['tenant']['furnishings']['keys']
        );

        // Stored as a JSON array (it is a multi-select), and tolerated as a bare string for
        // rows that were never JSON.
        $this->assertSame('The tenant is looking for a furnished property.', $this->answer('tenant', 'tenant_furnishings', [], ['tenant_require' => '["Furnished"]']));
        $this->assertSame('The tenant is looking for a furnished property.', $this->answer('tenant', 'tenant_furnishings', [], ['tenant_require' => 'Furnished']));
        $this->assertSame('The tenant is looking for an unfurnished property.', $this->answer('tenant', 'tenant_furnishings', [], ['tenant_require' => '["Unfurnished"]']));
        $this->assertSame('The tenant is looking for a turnkey property.', $this->answer('tenant', 'tenant_furnishings', [], ['tenant_require' => '["Turnkey"]']));
        $this->assertSame('The tenant is looking for a furnished or turnkey property.', $this->answer('tenant', 'tenant_furnishings', [], ['tenant_require' => '["Furnished","Turnkey"]']));

        // Anything outside that vocabulary says nothing — and one unrecognised item hides
        // the whole answer. This key's name reads like an occupant requirement, and a
        // historical occupant-category value is exactly what must never be restated here as
        // a housing preference.
        $this->assertNull($this->answer('tenant', 'tenant_furnishings', [], ['tenant_require' => '["Young Professionals"]']));
        $this->assertNull($this->answer('tenant', 'tenant_furnishings', [], ['tenant_require' => '["Furnished","Students"]']));
    }

    public function test_tenant_features_come_from_structured_selections_only(): void
    {
        $this->assertSame(
            'The tenant is looking for these features: In-unit Laundry, Dishwasher, Fenced Yard.',
            $this->answer('tenant', 'tenant_property_features',
                ['non_negotiable_amenities' => '["In-unit Laundry"]'],
                [
                    'non_negotiable_amenities' => json_encode(['In-unit Laundry']),
                    'appliances'               => json_encode(['Dishwasher']),
                    'property_items'           => json_encode(['Fenced Yard']),
                ]
            )
        );
    }

    public function test_tenant_missing_values_hide_their_questions(): void
    {
        $this->assertSame([], $this->ask('tenant', ['property_type' => null]));
        $this->assertSame([], $this->ask('tenant', ['property_type' => null, 'rent_budget' => '', 'bedrooms' => null, 'cities' => '[]']));
    }

    /* ================================================================== */
    /* The privacy boundary itself                                         */
    /* ================================================================== */

    /**
     * @dataProvider privateBuyerKeys
     */
    public function test_no_private_buyer_key_is_readable(string $key): void
    {
        $this->assertArrayNotHasKey($key, AskAiPublicPropertyQuestionService::publicCriteria()['buyer'],
            "'{$key}' must never be in PUBLIC_BUYER_CRITERIA.");

        $entry = [
            'role' => 'buyer', 'question' => 'probe', 'source_kind' => 'listing',
            'source_path' => 'listing.' . $key, 'supporting_paths' => [],
            'formatter' => 'criteria_property_type', 'guards' => [],
        ];
        $result = $this->service->evaluate($entry, 'buyer', ['listing' => ['property_type' => 'Residential', $key => 'PROBE']], []);

        $this->assertFalse($result['available'], "'{$key}' resolved to an answer.");
        $this->assertContains($result['reason'], ['not_public_allowed', 'source_not_in_context_map']);
    }

    public static function privateBuyerKeys(): array
    {
        return array_map(static fn ($k) => [$k], [
            'loan_pre_approved', 'credit_score_range', 'commute_destination_zip',
            'max_commute_minutes', 'commute_mode', 'address', 'number_of_units',
            // minimum_cap_rate was listed here; the universal coverage audit (2026-09-24) made it
            // public — config/offer_listing_private_criteria.php already names it the buyer's
            // investment criterion (beside the public minimum_annual_net_income), and the buyer
            // page prints it as "Min. Cap Rate".
            'purchase_purpose', 'additional_preferences', 'description',
        ]);
    }

    /**
     * @dataProvider privateTenantKeys
     */
    public function test_no_private_tenant_key_is_readable(string $key): void
    {
        $this->assertArrayNotHasKey($key, AskAiPublicPropertyQuestionService::publicCriteria()['tenant'],
            "'{$key}' must never be in PUBLIC_TENANT_CRITERIA.");

        $entry = [
            'role' => 'tenant', 'question' => 'probe', 'source_kind' => 'listing',
            'source_path' => 'listing.' . $key, 'supporting_paths' => [],
            'formatter' => 'criteria_property_type', 'guards' => [],
        ];
        $result = $this->service->evaluate($entry, 'tenant', ['listing' => ['property_type' => 'Residential', $key => 'PROBE']], []);

        $this->assertFalse($result['available'], "'{$key}' resolved to an answer.");
        $this->assertContains($result['reason'], ['not_public_allowed', 'source_not_in_context_map']);
    }

    public static function privateTenantKeys(): array
    {
        return array_map(static fn ($k) => [$k], [
            'monthly_income', 'credit_score_range', 'prior_eviction', 'prior_felony',
            'service_animal', 'emotional_support_animal', 'accessibility_requirements',
            'number_of_occupants', 'current_status', 'address', 'commute_destination_zip',
            'max_commute_minutes', 'commute_mode', 'guests_allowed',
            'security_deposit_budget', 'move_in_funds_available',
            'first_month_rent_available', 'last_month_rent_available',
            'tenant_conditions', 'renewal_option_details', 'description',
        ]);
    }

    public function test_a_private_key_is_refused_as_a_supporting_path_too(): void
    {
        // The property roles' admission mechanism applies to the SOURCE only. For a criteria
        // role the catalog is the whole tier, so it must govern supporting paths identically
        // — otherwise an approved source would be a doorway for any second value.
        $entry = [
            'role' => 'tenant', 'question' => 'probe', 'source_kind' => 'listing',
            'source_path' => 'listing.cities',
            'supporting_paths' => ['listing.monthly_income'],
            'formatter' => 'criteria_search_areas', 'guards' => [],
        ];
        $result = $this->service->evaluate($entry, 'tenant',
            ['listing' => ['property_type' => 'Residential', 'cities' => 'Seminole', 'monthly_income' => '9000']], []);

        $this->assertFalse($result['available']);
        $this->assertSame('supporting_not_public_allowed', $result['reason']);
    }

    public function test_the_property_roles_admission_mechanism_is_refused_for_a_criteria_role(): void
    {
        // A catalog entry that tried to borrow the seller mechanism must fail visibly rather
        // than quietly resolving some other way.
        $entry = [
            'role' => 'buyer', 'question' => 'probe', 'source_kind' => 'admitted_listing',
            'source_path' => 'listing.max_price', 'supporting_paths' => [],
            'formatter' => 'criteria_max_purchase_budget', 'guards' => [],
        ];
        $result = $this->service->evaluate($entry, 'buyer', ['listing' => ['property_type' => 'Residential', 'max_price' => '450000']], []);

        $this->assertFalse($result['available']);
        $this->assertSame('source_kind_not_available_for_criteria_role', $result['reason']);
    }

    public function test_a_compliance_restricted_key_is_refused_even_though_the_page_publishes_it(): void
    {
        // max_rent is in SnapshotFactVisibility::RESTRICTED_KEYS — a landlord's advertised
        // rent range. The tenant map aliased the tenant's own ceiling onto that name; the
        // tenant question therefore sources `rent_budget` and max_rent stays refused.
        $this->assertSame(SnapshotFactVisibility::RESTRICTED, SnapshotFactVisibility::classify('max_rent', 'tenant'));
        $this->assertArrayNotHasKey('max_rent', AskAiPublicPropertyQuestionService::publicCriteria()['tenant']);

        $entry = [
            'role' => 'tenant', 'question' => 'probe', 'source_kind' => 'listing',
            'source_path' => 'listing.max_rent', 'supporting_paths' => [],
            'formatter' => 'criteria_max_rent', 'guards' => [],
        ];
        $result = $this->service->evaluate($entry, 'tenant', ['listing' => ['property_type' => 'Residential', 'max_rent' => '2500']], []);

        $this->assertFalse($result['available']);
        $this->assertSame('not_public_allowed', $result['reason']);
    }

    public function test_every_catalog_entry_sources_only_approved_criteria_keys(): void
    {
        // The guarantee stated as a property of the catalog rather than of any one entry:
        // no buyer or tenant question, present or future, names a key outside its allowlist.
        $catalogs = AskAiPublicPropertyQuestionService::publicCriteria();

        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $role = $entry['role'] ?? '';
            if (!isset($catalogs[$role])) {
                continue;
            }

            $metaSources = AskAiPublicPropertyQuestionService::publicCriteriaMetaSources()[$role] ?? [];

            $source = (string) ($entry['source_path'] ?? '');
            if (str_starts_with($source, 'criteria_meta.')) {
                $this->assertSame('criteria_meta', $entry['source_kind'] ?? null, $id);
                $this->assertArrayHasKey(str_replace('criteria_meta.', '', $source), $metaSources,
                    "Catalog entry '{$id}' reads an undeclared page-meta source.");
            } else {
                $this->assertArrayHasKey(str_replace('listing.', '', $source), $catalogs[$role],
                    "Catalog entry '{$id}' reads '{$source}', which is not in PUBLIC_" . strtoupper($role) . '_CRITERIA.');
            }

            // Supporting paths are always shared-context keys and always allowlisted.
            foreach ($entry['supporting_paths'] ?? [] as $path) {
                $key = str_replace('listing.', '', (string) $path);
                $this->assertArrayHasKey($key, $catalogs[$role],
                    "Catalog entry '{$id}' supports on '{$key}', which is not in PUBLIC_" . strtoupper($role) . '_CRITERIA.');
            }
        }
    }

    /* ================================================================== */
    /* The restricted rent fields, and the generic-context boundary        */
    /* ================================================================== */

    /**
     * The three landlord advertised-rent fields cannot SUPPLY, ALTER or OVERRIDE the
     * tenant's public rent-budget answer.
     *
     * `max_rent`, `min_rent` and `rental_price` are SnapshotFactVisibility RESTRICTED keys —
     * a landlord's advertised rent range, with disclosure obligations attached. The tenant
     * context map aliases the tenant's own ceiling onto `max_rent`, which is precisely the
     * same-name-different-meaning trap; the fix is that the tenant question does not read
     * the shared context for this fact at all.
     *
     * Values are planted in BOTH the shared context AND the page meta, at three amounts
     * that are impossible to confuse with the real one.
     */
    public function test_restricted_rent_fields_cannot_supply_alter_or_override_the_rent_answer(): void
    {
        $planted = ['max_rent' => '9999', 'min_rent' => '8888', 'rental_price' => '7777'];

        // 1. SUPPLY — with no tenant budget stored, they produce no rent answer at all.
        $answers = $this->ask('tenant', $planted, $planted);
        $this->assertArrayNotHasKey('tenant_max_rent', $answers);
        foreach ($answers as $id => $answer) {
            foreach (['9,999', '9999', '8,888', '8888', '7,777', '7777'] as $figure) {
                $this->assertStringNotContainsString($figure, $answer, "{$id} published a restricted rent figure.");
            }
        }

        // 2. ALTER / OVERRIDE — with a real budget stored, the answer is the budget, exactly,
        //    and none of the three changes it.
        $this->assertSame(
            'The tenant is looking for rent up to $2,500.',
            $this->answer('tenant', 'tenant_max_rent', $planted, $planted + ['budget' => '2500'])
        );

        // 3. They are still RESTRICTED, for every role, which is what makes (1) and (2)
        //    structural rather than incidental.
        foreach (['max_rent', 'min_rent', 'rental_price'] as $key) {
            foreach (['tenant', 'buyer', 'seller', 'landlord'] as $role) {
                $this->assertSame(SnapshotFactVisibility::RESTRICTED, SnapshotFactVisibility::classify($key, $role),
                    "{$key} must remain RESTRICTED for {$role}.");
            }
        }

        // 4. None of them is named as a page-meta source, and naming one would not help:
        //    criteriaMetaValue() re-checks every key against RESTRICTED at read time.
        $declared = [];
        foreach (AskAiPublicPropertyQuestionService::publicCriteriaMetaSources() as $role => $sources) {
            foreach ($sources as $source) {
                $declared = array_merge($declared, $source['keys']);
            }
        }
        foreach (['max_rent', 'min_rent', 'rental_price'] as $key) {
            $this->assertNotContains($key, $declared, "{$key} must not be a declared page-meta source.");
        }
    }

    /**
     * A page-meta source is refused when any of its keys is restricted or owner-only — the
     * check is at READ time against the constant as it actually is, not a review-time
     * promise about how it was written.
     */
    public function test_a_page_meta_source_is_read_only_through_keys_that_are_public_and_unrestricted(): void
    {
        $service = new class extends AskAiPublicPropertyQuestionService {
            public function probe(string $name, string $role, array $meta): mixed
            {
                $m = new \ReflectionMethod(AskAiPublicPropertyQuestionService::class, 'criteriaMetaValue');
                $m->setAccessible(true);

                return $m->invoke($this, $name, $role, $meta);
            }
        };

        // The real source reads.
        $this->assertSame('2500', $service->probe('rent_budget', 'tenant', ['budget' => '2500']));

        // An undeclared name reads nothing.
        $this->assertNull($service->probe('monthly_income', 'tenant', ['monthly_income' => '9000']));

        // And every declared key is one Phase A treats as public on this page, so this card
        // can never publish a value the page itself redacts for a non-owner.
        foreach (AskAiPublicPropertyQuestionService::publicCriteriaMetaSources() as $role => $sources) {
            foreach ($sources as $name => $source) {
                foreach ($source['keys'] as $key) {
                    $this->assertFalse(CriteriaPrivacyPolicy::isPrivate($role, $key),
                        "{$role}.{$name} reads '{$key}', which CriteriaPrivacyPolicy makes owner-only.");
                    $this->assertNotSame(SnapshotFactVisibility::RESTRICTED, SnapshotFactVisibility::classify($key, $role),
                        "{$role}.{$name} reads '{$key}', which is RESTRICTED.");
                }
            }
        }
    }

    /**
     * Batch 2d widened no AI context.
     *
     * The three criteria the page publishes but the shared context did not carry — the rent
     * budget, the pets Yes/No and the furnishings select — are read from the page's own meta.
     * Adding them to CANONICAL_SOURCE_MAP instead would have pushed them into
     * extractListingFields(), and from there into the generic Ask AI context, Agent AI and
     * the snapshot path, to make one deterministic card work.
     */
    public function test_no_batch_2d_field_was_added_to_the_generic_ai_context(): void
    {
        $map = AskAiContextBuilderService::CANONICAL_SOURCE_MAP;

        foreach (['rent_budget', 'pets_allowed', 'furnishings'] as $name) {
            $this->assertArrayNotHasKey($name, $map['tenant'],
                "'{$name}' is in the tenant context map — Batch 2d plumbing leaked into the generic AI context.");
        }

        // Every page-meta source exists precisely because the shared context has no key for
        // it; one that the context DOES carry should be sourced from the context instead.
        foreach (AskAiPublicPropertyQuestionService::publicCriteriaMetaSources() as $role => $sources) {
            foreach (array_keys($sources) as $name) {
                $this->assertArrayNotHasKey($name, $map[$role] ?? [],
                    "'{$role}.{$name}' is both a page-meta source and a shared-context key.");
            }
        }
    }

    public function test_the_page_meta_mechanism_is_refused_for_a_property_role(): void
    {
        $entry = [
            'role' => 'seller', 'question' => 'probe', 'source_kind' => 'criteria_meta',
            'source_path' => 'criteria_meta.rent_budget', 'supporting_paths' => [],
            'formatter' => 'criteria_max_rent', 'guards' => [],
        ];
        $result = $this->service->evaluate($entry, 'seller', ['listing' => ['property_type' => 'Residential']], ['budget' => '2500']);

        $this->assertFalse($result['available']);
        $this->assertSame('source_kind_not_available_for_property_role', $result['reason']);
    }

    public function test_decision_d2_is_unchanged(): void
    {
        // Batch 2d adds a surface-local allowlist; it does not make any buyer or tenant fact
        // public anywhere else. Every key this surface may read still classifies OWNER_ONLY
        // (or RESTRICTED), which is exactly why the allowlist had to exist.
        foreach (AskAiPublicPropertyQuestionService::publicCriteria() as $role => $keys) {
            foreach (array_keys($keys) as $key) {
                $this->assertNotSame(
                    SnapshotFactVisibility::PUBLIC_ALLOWED,
                    SnapshotFactVisibility::classify($key, $role),
                    "SnapshotFactVisibility now publishes {$role}.{$key} — D2 has been loosened."
                );
            }
            $this->assertSame([], SnapshotFactVisibility::publicKeysForRole($role),
                "SnapshotFactVisibility must publish no {$role} key at all (D2).");
        }
    }

    public function test_an_unknown_role_answers_nothing(): void
    {
        $this->assertSame([], $this->service->forListing('agent', ['listing' => ['property_type' => 'Residential', 'max_price' => '450000']], []));
        $this->assertSame([], $this->service->forListing('', ['listing' => ['property_type' => 'Residential', 'max_price' => '450000']], []));
    }
}
