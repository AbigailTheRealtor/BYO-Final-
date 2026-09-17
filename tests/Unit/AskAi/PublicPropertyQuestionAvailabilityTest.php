<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use Tests\TestCase;

/**
 * "Questions About This Property" — the availability rule, the formatters and the
 * privacy boundary, exercised without a database.
 *
 * The listing-page rendering is pinned separately by
 * Tests\Feature\AskAi\PublicPropertyQuestionsListingPageTest.
 */
class PublicPropertyQuestionAvailabilityTest extends TestCase
{
    private AskAiPublicPropertyQuestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiPublicPropertyQuestionService();
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function entry(string $id): array
    {
        return AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()[$id];
    }

    private function context(array $listing): array
    {
        return ['listing' => $listing, 'faq_answers' => []];
    }

    /** A seller listing whose every catalog question (Batch 1 + 2b) is answerable. */
    private function fullSellerContext(): array
    {
        return $this->context([
            // Batch 2e: a complete listing now carries a FEMA designation too.
            'flood_zone_code'       => 'AE',
            'asking_price'          => '500000',
            'bedrooms'              => '3',
            'bathrooms'             => '2.5',
            'square_feet'           => '1,850',
            'year_built'            => '1998',
            'annual_property_taxes' => '1856',
            'tax_year'              => '2025',
            'hoa_association'       => 'Yes',
            'hoa_fee'               => '250',
            'hoa_payment_schedule'  => 'Monthly',
            'total_acreage'         => '1/4 to less than 1/2 acre',
            'appliances'            => 'Dishwasher, Range, Refrigerator',
            'utilities'             => 'Electricity Connected, Water Available',
            // Batch 2b
            'pets_allowed'          => 'Yes',
            'pool'                  => 'Yes',
            'garage'                => 'No',
            'zoning'                => 'RS-60',
            'roof_type'             => 'Shingle',
            'rental_restrictions'   => 'Yes',
            'offered_financing'     => 'Conventional, FHA, VA, Cash',
            // Batch 2c
            'association_fee_includes' => 'Water, Trash',
            'has_cdd'               => 'Yes',
            'annual_cdd_fee'        => '1200',
        ]);
    }

    private function fullSellerMeta(): array
    {
        return [
            'bedrooms'                  => '3',
            'bathrooms'                 => '2.5',
            'auction_type'              => 'Traditional',
            'association_fee_frequency' => 'Monthly',
            'roof_type'                 => json_encode(['Shingle']),
            'offered_financing'         => json_encode(['Conventional', 'FHA', 'VA', 'Cash']),
            'association_fee_includes'  => json_encode(['Water', 'Trash']),
        ];
    }

    private function fullLandlordContext(): array
    {
        return $this->context([
            'flood_zone_code' => 'VE',
            'bedrooms'    => '2',
            'bathrooms'   => '1',
            'square_feet' => '950',
            'appliances'  => 'Washer, Dryer',
            'pet_policy'  => 'No',
            // Batch 2b
            'year_built'                => '2004',
            'annual_property_taxes'     => '3120',
            'tax_year'                  => '2024',
            'has_hoa'                   => 'Yes',
            'association_fee_amount'    => '175',
            'association_fee_frequency' => 'Quarterly',
            'zoning'                    => 'RM-15',
            'roof_type'                 => 'Tile, Metal',
            'leasing_restrictions'      => 'No',
            'association_amenities'     => 'Clubhouse, Fitness Center',
            'association_fee_includes'  => 'Grounds Maintenance',
        ]);
    }

    private function fullLandlordMeta(): array
    {
        return [
            'association_fee_frequency' => 'Quarterly',
            'roof_type'                 => json_encode(['Tile', 'Metal']),
            'association_amenities'     => json_encode(['Clubhouse', 'Fitness Center']),
            'association_fee_includes'  => json_encode(['Grounds Maintenance']),
        ];
    }

    /** Catalog ids for a role, in display order. */
    private function catalogIds(string $role): array
    {
        $catalog = array_filter(
            AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry(),
            fn (array $e) => $e['role'] === $role
        );
        $ids = array_keys($catalog);
        usort($ids, fn ($a, $b) => $catalog[$a]['order'] <=> $catalog[$b]['order']);

        return $ids;
    }

    /** @return array<string,string> id => answer */
    private function answers(string $role, array $context, array $meta): array
    {
        $out = [];
        foreach ($this->service->forListing($role, $context, $meta) as $q) {
            $out[$q['id']] = $q['answer'];
        }

        return $out;
    }

    // ── 1. A public-safe question appears when its value exists ─────────────

    public function test_public_safe_question_appears_when_value_exists(): void
    {
        $result = $this->service->evaluate(
            $this->entry('seller_bedrooms'), 'seller', $this->context(['bedrooms' => '3']), ['bedrooms' => '3']
        );

        $this->assertTrue($result['available']);
        $this->assertSame('This property has 3 bedrooms.', $result['answer']);
    }

    public function test_every_catalog_question_is_answerable_from_a_complete_listing(): void
    {
        // Batch 2c: a composite and its narrower fallback never render together, so a listing
        // complete enough for every composite answers every question EXCEPT the narrower
        // entries those composites replace.
        $narrower = array_keys(array_filter(
            AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry(),
            fn (array $e) => isset($e['narrower_of'])
        ));

        $seller = $this->answers('seller', $this->fullSellerContext(), $this->fullSellerMeta());
        $this->assertSame(array_values(array_diff($this->catalogIds('seller'), $narrower)), array_keys($seller));

        $landlord = $this->answers('landlord', $this->fullLandlordContext(), $this->fullLandlordMeta());
        $this->assertSame(array_values(array_diff($this->catalogIds('landlord'), $narrower)), array_keys($landlord));
    }

    // ── 2 + 3. Null and blank values hide the question ──────────────────────

    public function test_question_disappears_when_value_is_null(): void
    {
        $context = $this->fullSellerContext();
        $context['listing']['year_built'] = null;

        $this->assertArrayNotHasKey('seller_year_built', $this->answers('seller', $context, $this->fullSellerMeta()));

        unset($context['listing']['year_built']);
        $this->assertArrayNotHasKey('seller_year_built', $this->answers('seller', $context, $this->fullSellerMeta()));
    }

    public function test_question_disappears_when_value_is_blank(): void
    {
        foreach (['', '   ', '[]', '{}'] as $blank) {
            $context = $this->fullLandlordContext();
            $context['listing']['appliances'] = $blank;

            $result = $this->service->evaluate($this->entry('landlord_appliances'), 'landlord', $context, []);

            $this->assertFalse($result['available'], "A blank value (" . json_encode($blank) . ") must hide the question.");
            $this->assertSame('value_missing', $result['reason']);
        }
    }

    public function test_empty_context_produces_no_questions(): void
    {
        $this->assertSame([], $this->service->forListing('seller', [], []));
        $this->assertSame([], $this->service->forListing('landlord', ['listing' => []], []));
    }

    // ── 4. Owner-only and restricted sources never produce public questions ─

    public function test_owner_only_and_restricted_sources_never_produce_public_questions(): void
    {
        $cases = [
            // role, key, a meaningful value — every key is defined in the context map
            ['landlord', 'lease_amount_frequency',  'Monthly'],   // owner_only
            ['landlord', 'rent_includes',           'Water'],     // owner_only
            ['landlord', 'security_deposit_amount', '1500'],      // restricted
            ['landlord', 'rental_price',            '2000'],      // restricted (not in map)
            // flood_zone_code was here as "restricted" and has MOVED: it is a public
            // seller/landlord fact as of Batch 2e (owner decision). The three flood fields
            // that stayed restricted are probed instead, so this case still covers the
            // category it was written for.
            ['seller',   'flood_zone_designation',  'Zone AE'],   // restricted
            ['seller',   'flood_zone_description',  'High risk'], // restricted
            ['seller',   'is_in_flood_zone',        'Yes'],       // restricted
            ['seller',   'sale_provision',          'Short Sale'],// owner_only
            ['seller',   'offered_financing',       'Cash'],      // owner_only
        ];

        foreach ($cases as [$role, $key, $value]) {
            $entry = [
                'role'             => $role,
                'question'         => 'Probe?',
                'source_path'      => 'listing.' . $key,
                'supporting_paths' => [],
                'formatter'        => 'appliance_list',
                'guards'           => [],
            ];

            $result = $this->service->evaluate($entry, $role, $this->context([$key => $value]), []);

            $this->assertFalse($result['available'], "{$role}.{$key} must never be answerable publicly.");
            $this->assertContains($result['reason'], ['not_public_allowed', 'source_not_in_context_map'], "{$role}.{$key}");
            $this->assertNotSame(SnapshotFactVisibility::PUBLIC_ALLOWED, SnapshotFactVisibility::classify($key, $role));
        }
    }

    public function test_an_owner_only_supporting_path_hides_the_whole_question(): void
    {
        $entry = $this->entry('seller_property_taxes');
        $entry['supporting_paths'] = ['listing.minimum_cap_rate'];

        $result = $this->service->evaluate($entry, 'seller', $this->fullSellerContext(), $this->fullSellerMeta());

        $this->assertFalse($result['available']);
        $this->assertSame('supporting_not_public_allowed', $result['reason']);
    }

    public function test_knowledge_base_answers_are_not_a_public_source(): void
    {
        $entry = $this->entry('seller_year_built');
        $entry['source_path'] = 'faq_answers.roof_age_and_condition';

        $context = $this->fullSellerContext();
        $context['faq_answers']['roof_age_and_condition'] = ['answer_text' => 'Replaced in 2020.'];

        $result = $this->service->evaluate($entry, 'seller', $context, $this->fullSellerMeta());

        $this->assertFalse($result['available']);
        $this->assertSame('source_path_invalid', $result['reason']);

        foreach ($this->service->forListing('seller', $context, $this->fullSellerMeta()) as $q) {
            $this->assertStringNotContainsString('Replaced in 2020', $q['answer']);
            $this->assertStringStartsWith('listing.', $q['source_path']);
        }
    }

    // ── 5. Unknown sources, formatters and guards fail closed ───────────────

    public function test_unknown_sources_fail_closed(): void
    {
        $base = $this->entry('seller_year_built');

        $probes = [
            'listing.totally_unknown_key' => 'source_not_in_context_map',
            'listing.'                    => 'source_path_invalid',
            'listing.year_built.nested'   => 'source_path_invalid',
            'context.listing.year_built'  => 'source_path_invalid',
            'year_built'                  => 'source_path_invalid',
            ''                            => 'source_path_invalid',
        ];

        foreach ($probes as $path => $reason) {
            $entry = $base;
            $entry['source_path'] = $path;
            $context = $this->context(['year_built' => '1998', 'totally_unknown_key' => '1998']);

            $result = $this->service->evaluate($entry, 'seller', $context, []);

            $this->assertFalse($result['available'], "'{$path}' must fail closed.");
            $this->assertSame($reason, $result['reason'], "'{$path}'");
        }

        $entry = $base;
        unset($entry['source_path']);
        $this->assertFalse($this->service->evaluate($entry, 'seller', $this->context(['year_built' => '1998']), [])['available']);
    }

    public function test_unknown_formatter_or_guard_fails_closed(): void
    {
        $context = $this->context(['year_built' => '1998']);

        $entry = $this->entry('seller_year_built');
        $entry['formatter'] = 'generate_with_ai';
        $this->assertSame('formatter_missing', $this->service->evaluate($entry, 'seller', $context, [])['reason']);

        $entry = $this->entry('seller_year_built');
        $entry['guards'] = ['trust_me'];
        $this->assertSame('guard_unknown:trust_me', $this->service->evaluate($entry, 'seller', $context, [])['reason']);
    }

    public function test_every_catalog_source_is_defined_and_public_for_its_role(): void
    {
        $catalog = AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry();
        $this->assertNotEmpty($catalog);

        $admissions = AskAiPublicPropertyQuestionService::publicQuestionAdmissions();

        $criteria = AskAiPublicPropertyQuestionService::publicCriteria();

        foreach ($catalog as $id => $entry) {
            $this->assertContains($entry['role'], ['seller', 'landlord', 'buyer', 'tenant'], $id);
            $this->assertContains($entry['source_kind'], ['listing', 'admitted_listing', 'criteria_meta'], $id);

            // Batch 2d — the CRITERIA roles are governed by their own explicit catalogs
            // instead of SnapshotFactVisibility's public tier, because D2 gives them no
            // public tier at all. They have two admissions and no others: the context-key
            // catalog, and the narrow page-meta allowlist for criteria the shared context
            // does not carry. The property roles' mechanism is not available to them, and
            // theirs is not available to the property roles.
            $isCriteria = isset($criteria[$entry['role']]);
            if ($isCriteria) {
                $this->assertContains($entry['source_kind'], ['listing', 'criteria_meta'],
                    "{$id}: a criteria entry may not use admitted_listing.");
            } else {
                $this->assertNotSame('criteria_meta', $entry['source_kind'],
                    "{$id}: the page-meta mechanism belongs to the criteria roles.");
            }

            // A page-meta source is checked against its own allowlist; every key it reads is
            // re-verified as public-and-unrestricted at read time by the service itself.
            if ($entry['source_kind'] === 'criteria_meta') {
                $sources = AskAiPublicPropertyQuestionService::publicCriteriaMetaSources()[$entry['role']] ?? [];
                $this->assertMatchesRegularExpression('/^criteria_meta\.[a-z0-9_]+$/', $entry['source_path'], $id);
                $this->assertArrayHasKey(str_replace('criteria_meta.', '', $entry['source_path']), $sources, $id);
                // Supporting paths remain ordinary context keys, checked by the loop below.
                foreach ($entry['supporting_paths'] as $path) {
                    $key = substr($path, strlen('listing.'));
                    $this->assertArrayHasKey($key, $criteria[$entry['role']], "{$id}: {$path}");
                }
                continue;
            }

            foreach (array_merge([$entry['source_path']], $entry['supporting_paths']) as $i => $path) {
                $this->assertMatchesRegularExpression('/^listing\.[a-z0-9_]+$/', $path, $id);
                $key = substr($path, strlen('listing.'));

                if ($isCriteria) {
                    $this->assertArrayHasKey($key, $criteria[$entry['role']],
                        "{$id}: {$path} is not in PUBLIC_" . strtoupper($entry['role']) . '_CRITERIA.');
                    $this->assertNotSame(SnapshotFactVisibility::RESTRICTED, SnapshotFactVisibility::classify($key, $entry['role']),
                        "{$id}: {$path} is compliance-restricted and must never be read.");
                    continue;
                }

                $this->assertArrayHasKey($key, AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$entry['role']], "{$id}: {$path}");

                // Batch 2b: an admitted SOURCE is owner_only for the AI context (never
                // restricted) and named in the public question layer's admission list.
                // Supporting paths are never admitted.
                if ($i === 0 && $entry['source_kind'] === 'admitted_listing') {
                    $this->assertArrayHasKey($key, $admissions[$entry['role']] ?? [], "{$id}: {$path} must be explicitly admitted.");
                    $this->assertSame(SnapshotFactVisibility::OWNER_ONLY, SnapshotFactVisibility::classify($key, $entry['role']), "{$id}: {$path}");
                    continue;
                }

                $this->assertSame(
                    SnapshotFactVisibility::PUBLIC_ALLOWED,
                    SnapshotFactVisibility::classify($key, $entry['role']),
                    "{$id}: {$path} must be public_allowed for {$entry['role']}."
                );
            }
        }

        // The admission list stays exactly as narrow as decided.
        $this->assertSame(['seller' => ['offered_financing']], array_map('array_keys', $admissions));
    }

    // ── 6. Seller minimums never create public questions ────────────────────

    public function test_seller_minimum_fields_never_create_public_questions(): void
    {
        foreach (['minimum_cap_rate', 'minimum_annual_net_income'] as $key) {
            $this->assertArrayHasKey($key, AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller']);

            $entry = $this->entry('seller_property_taxes');
            $entry['source_path'] = 'listing.' . $key;
            $entry['supporting_paths'] = [];

            $result = $this->service->evaluate($entry, 'seller', $this->context([$key => '125000']), []);

            $this->assertFalse($result['available'], "{$key} must never be answered publicly.");
            $this->assertSame('not_public_allowed', $result['reason']);
        }

        // A listing carrying real minimums: nothing published mentions or derives from them.
        $context = $this->fullSellerContext();
        $context['listing']['minimum_cap_rate']          = '7.25';
        $context['listing']['minimum_annual_net_income'] = '987654';

        foreach ($this->service->forListing('seller', $context, $this->fullSellerMeta()) as $q) {
            $this->assertStringNotContainsString('7.25', $q['answer']);
            $this->assertStringNotContainsString('987,654', $q['answer']);
            $this->assertStringNotContainsString('987654', $q['answer']);
            $this->assertStringNotContainsStringIgnoringCase('cap rate', $q['question'] . $q['answer']);
            $this->assertStringNotContainsStringIgnoringCase('net income', $q['question'] . $q['answer']);
        }

        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $paths = implode(' ', array_merge(
                [$entry['source_path']],
                $entry['supporting_paths'],
                array_values($entry['other_companion'] ?? [])
            ));
            foreach (['minimum', 'cap_rate', 'noi', 'net_income', 'walk_away', 'reserve'] as $needle) {
                $this->assertStringNotContainsString($needle, $paths, "{$id} must not read {$needle}.");
            }
        }
    }

    // ── 7. Only an approved criteria key is readable for a criteria role ────

    /**
     * SUPERSEDED AND REPLACED by Batch 2d.
     *
     * This was "buyer and tenant produce no public questions at all", which was the right
     * rule while those roles had no approved surface. They have one now, so the blanket
     * emptiness claim is false by design — but the reason it existed is not, and it is
     * restated here in the stronger form the new design actually supports:
     *
     *   - a role name that is not exactly 'buyer' or 'tenant' still produces nothing, so an
     *     alias, a casing variant or an empty string cannot reach the surface sideways;
     *   - for the two real criteria roles, only a key named in their own catalog resolves;
     *   - a qualification key is refused even when the listing carries a value for it.
     */
    public function test_only_an_approved_criteria_key_is_readable_for_a_criteria_role(): void
    {
        $criteria = $this->context([
            'bedrooms'    => '3',
            'bathrooms'   => '2',
            'square_feet' => '1500',
            'max_price'   => '450000',
            'max_rent'    => '2400',
            'appliances'  => 'Washer, Dryer',
        ]);

        // An ALIAS role name answers nothing. forListing() has lowercased its argument since
        // Batch 1, so 'BUYER' does resolve to 'buyer' — casing is normalised, identity is
        // not, and a listing-type alias is not a role.
        foreach (['buyer_agent_auction', 'tenant_criteria_auction', '', 'agent_profile', 'buyer_criteria'] as $role) {
            $this->assertSame([], $this->service->forListing($role, $criteria, []), "'{$role}' must produce no public questions.");
        }
        // Casing tolerance must not become key tolerance: the uppercase spelling reaches the
        // same catalog, never a wider one.
        $this->assertSame(
            $this->service->forListing('buyer', $criteria, []),
            $this->service->forListing('BUYER', $criteria, [])
        );

        // For the two real criteria roles, the catalog is the whole permission.
        $catalogs = AskAiPublicPropertyQuestionService::publicCriteria();
        foreach (['buyer', 'tenant'] as $role) {
            foreach ($this->service->forListing($role, $criteria, []) as $question) {
                $key = substr($question['source_path'], strlen('listing.'));
                $this->assertArrayHasKey($key, $catalogs[$role], "{$question['id']} read an unapproved key.");
            }

            // A qualification key present on the listing is still refused.
            $probe = $role === 'tenant' ? 'monthly_income' : 'pre_approval_amount';
            $entry = [
                'role' => $role, 'question' => 'probe', 'source_kind' => 'listing',
                'source_path' => 'listing.' . $probe, 'supporting_paths' => [],
                'formatter' => 'criteria_property_type', 'guards' => [],
            ];
            $result = $this->service->evaluate($entry, $role, $this->context([$probe => '99999']), []);
            $this->assertFalse($result['available'], "{$role}.{$probe} became available.");
        }
    }

    public function test_an_entry_cannot_answer_for_another_role(): void
    {
        $result = $this->service->evaluate(
            $this->entry('landlord_pets_allowed'), 'seller', $this->context(['pet_policy' => 'Yes']), []
        );

        $this->assertFalse($result['available']);
        $this->assertSame('not_in_catalog_for_role', $result['reason']);
    }

    // ── 8. Formatter output is deterministic and correct ────────────────────

    public function test_formatter_output_is_correct(): void
    {
        $expected = [
            'seller_asking_price'       => 'The asking price is $500,000.',
            'seller_bedrooms'           => 'This property has 3 bedrooms.',
            'seller_bathrooms'          => 'This property has 2.5 bathrooms.',
            'seller_heated_square_feet' => 'The heated square footage is 1,850 square feet.',
            'seller_year_built'         => 'This property was built in 1998.',
            'seller_property_taxes'     => 'Annual property taxes are $1,856 for tax year 2025.',
            'seller_hoa_fee_coverage'   => 'The HOA fee is $250 per month and includes water and trash.',
            'seller_cdd_fee'            => 'The annual CDD fee is $1,200.',
            'seller_total_acreage'      => 'The total acreage is 1/4 to less than 1/2 acre.',
            'seller_appliances'         => 'Appliances listed for this property: Dishwasher, Range, Refrigerator.',
            'seller_utilities'          => 'Utilities listed for this property: Electricity Connected, Water Available.',
            'seller_pets_allowed'       => 'Pets are allowed at this property.',
            'seller_pool'               => 'This property has a pool.',
            'seller_garage'             => 'This property does not have a garage.',
            'seller_zoning'             => 'The zoning is listed as RS-60.',
            'seller_roof_type'          => 'Roof type listed for this property: Shingle.',
            'seller_leasing_restrictions' => 'The listing indicates there are leasing restrictions.',
            'seller_offered_financing'  => 'The seller has indicated they will consider the following financing types: Conventional, FHA, VA and Cash.',
            'seller_flood_zone'         => 'This property is in FEMA Flood Zone AE, which is within a Special Flood Hazard Area.',
        ];
        $this->assertSame($expected, $this->answers('seller', $this->fullSellerContext(), $this->fullSellerMeta()));

        $this->assertSame([
            'landlord_bedrooms'              => 'This property has 2 bedrooms.',
            'landlord_bathrooms'             => 'This property has 1 bathroom.',
            'landlord_heated_square_feet'    => 'The heated square footage is 950 square feet.',
            'landlord_year_built'            => 'This property was built in 2004.',
            'landlord_property_taxes'        => 'Annual property taxes are $3,120 for tax year 2024.',
            'landlord_hoa_fee_coverage'      => 'The HOA fee is $175 per quarter and includes grounds maintenance.',
            'landlord_appliances'            => 'Appliances listed for this property: Washer, Dryer.',
            'landlord_pets_allowed'          => "Pets are not allowed under the property's pet policy. Assistance animals are handled separately under applicable law.",
            'landlord_zoning'                => 'The zoning is listed as RM-15.',
            'landlord_roof_type'             => 'Roof types listed for this property: Tile, Metal.',
            'landlord_leasing_restrictions'  => 'The listing indicates there are no leasing restrictions.',
            'landlord_association_amenities' => 'Community amenities listed for this property: Clubhouse, Fitness Center.',
            'landlord_flood_zone'            => 'This property is in FEMA Flood Zone VE, a coastal high-hazard Special Flood Hazard Area.',
        ], $this->answers('landlord', $this->fullLandlordContext(), $this->fullLandlordMeta()));
    }

    public function test_formatter_output_is_deterministic(): void
    {
        $first  = $this->service->forListing('seller', $this->fullSellerContext(), $this->fullSellerMeta());
        $second = (new AskAiPublicPropertyQuestionService())->forListing('seller', $this->fullSellerContext(), $this->fullSellerMeta());

        $this->assertSame($first, $second);
    }

    public function test_formatters_state_only_what_the_value_supports(): void
    {
        $cases = [
            // id, listing overrides, meta overrides, expected answer (null = hidden)
            ['seller_bedrooms', ['bedrooms' => '1'], ['bedrooms' => '1'], 'This property has 1 bedroom.'],
            ['seller_bedrooms', ['bedrooms' => 'Studio'], ['bedrooms' => 'Studio'], null],
            ['seller_bedrooms', ['bedrooms' => '0'], ['bedrooms' => '0'], null],
            ['seller_bedrooms', ['bedrooms' => '2.5'], ['bedrooms' => '2.5'], null],
            ['seller_bathrooms', ['bathrooms' => '3.0'], ['bathrooms' => '3.0'], 'This property has 3 bathrooms.'],
            ['seller_bathrooms', ['bathrooms' => 'Two'], ['bathrooms' => 'Two'], null],
            ['seller_heated_square_feet', ['square_feet' => 'about 1800'], [], null],
            ['seller_year_built', ['year_built' => '98'], [], null],
            ['seller_year_built', ['year_built' => '3021'], [], null],
            ['seller_asking_price', ['asking_price' => '-5000'], [], null],
            ['seller_asking_price', ['asking_price' => '0'], [], null],
            ['seller_asking_price', ['asking_price' => '$499,999.50'], [], 'The asking price is $499,999.50.'],
            ['seller_property_taxes', ['annual_property_taxes' => '1856.4', 'tax_year' => null], [], 'Annual property taxes are $1,856.40.'],
            ['seller_property_taxes', ['tax_year' => 'last year'], [], 'Annual property taxes are $1,856.'],
            // Frequency is never invented: unknown / ambiguous / absent → no period at all.
            ['seller_hoa_fee', ['hoa_payment_schedule' => 'Bi-Monthly'], [], 'The HOA fee is $250.'],
            ['seller_hoa_fee', ['hoa_payment_schedule' => 'Other'], [], 'The HOA fee is $250.'],
            ['seller_hoa_fee', ['hoa_payment_schedule' => null], [], 'The HOA fee is $250.'],
            ['seller_hoa_fee', ['hoa_payment_schedule' => 'Annually'], [], 'The HOA fee is $250 per year.'],
            // A fee left behind after the seller answered No / Unknown is not published.
            ['seller_hoa_fee', ['hoa_association' => 'No'], [], null],
            ['seller_hoa_fee', ['hoa_association' => 'Unknown'], [], null],
            ['seller_hoa_fee', ['hoa_association' => null], [], null],
            ['seller_total_acreage', ['total_acreage' => 'Non-Applicable'], [], null],
            ['seller_total_acreage', ['total_acreage' => '0.23'], [], null],
            ['seller_appliances', ['appliances' => '["Dishwasher"]'], [], null],
            ['landlord_pets_allowed', ['pet_policy' => 'Yes'], [], 'Pets are allowed at this property.'],
            ['landlord_pets_allowed', ['pet_policy' => 'Cats only'], [], null],
        ];

        foreach ($cases as [$id, $listing, $meta, $expected]) {
            $entry   = $this->entry($id);
            $role    = $entry['role'];
            $context = $role === 'seller' ? $this->fullSellerContext() : $this->fullLandlordContext();
            $context['listing'] = array_merge($context['listing'], $listing);
            $metaAll = array_merge($role === 'seller' ? $this->fullSellerMeta() : [], $meta);

            $result = $this->service->evaluate($entry, $role, $context, $metaAll);

            $this->assertSame($expected, $result['answer'], "{$id} with " . json_encode($listing));
            $this->assertSame($expected !== null, $result['available'], "{$id} with " . json_encode($listing));
        }
    }

    // ── Guards: ambiguity with what the page publishes hides the question ───

    public function test_asking_price_is_hidden_where_the_page_does_not_state_one_price(): void
    {
        $entry   = $this->entry('seller_asking_price');
        $context = $this->fullSellerContext();

        // Bidding Period: the page hides Desired Sale Price entirely.
        $bidding = $this->service->evaluate($entry, 'seller', $context, ['auction_type' => 'Bidding Period']);
        $this->assertSame('guard_failed:not_bidding_period', $bidding['reason']);

        // MLS-linked with a DIFFERENT Stellar list price: two labelled prices on the page.
        $divergent = $this->service->evaluate($entry, 'seller', $context, [
            Meta::META_LISTING_KEY => 'KEY-1',
            Meta::META_LIST_PRICE  => '520000',
            'maximum_budget'       => '500000',
        ]);
        $this->assertSame('guard_failed:mls_price_not_divergent', $divergent['reason']);

        // MLS-linked with the SAME figure: one price, consistently stated.
        $agrees = $this->service->evaluate($entry, 'seller', $context, [
            Meta::META_LISTING_KEY => 'KEY-1',
            Meta::META_LIST_PRICE  => '500000',
            'maximum_budget'       => '500000',
        ]);
        $this->assertSame('The asking price is $500,000.', $agrees['answer']);
    }

    public function test_seller_room_counts_require_the_meta_row_the_page_renders(): void
    {
        // Without a `bedrooms` meta row the context falls back to the native bedroom_id
        // foreign key, which is not a count.
        $result = $this->service->evaluate($this->entry('seller_bedrooms'), 'seller', $this->context(['bedrooms' => '4']), []);

        $this->assertFalse($result['available']);
        $this->assertSame('guard_failed:meta_present:bedrooms', $result['reason']);
    }

    public function test_acreage_is_hidden_when_a_legacy_min_acreage_would_contradict_it(): void
    {
        $entry   = $this->entry('seller_total_acreage');
        $context = $this->fullSellerContext();

        $this->assertFalse($this->service->evaluate($entry, 'seller', $context, ['min_acreage' => '5 to less than 10 acres'])['available']);
        $this->assertTrue($this->service->evaluate($entry, 'seller', $context, ['min_acreage' => '1/4 to less than 1/2 acre'])['available']);
        $this->assertTrue($this->service->evaluate($entry, 'seller', $context, ['min_acreage' => ''])['available']);
    }

    // ── 10. Zero language-model calls, structurally ─────────────────────────

    public function test_surface_has_no_path_to_a_language_model_or_network_call(): void
    {
        $service = file_get_contents(base_path('app/Services/AskAi/AskAiPublicPropertyQuestionService.php'));
        // Batch 2a moved the surface into the Ask AI card; Blade comments are documentation,
        // not markup, so they are removed before the scan.
        $partial = preg_replace(
            '/\{\{--.*?--\}\}/s',
            '',
            file_get_contents(base_path('resources/views/offer-listing/partials/_ask-ai-property-card.blade.php'))
        );

        foreach ([$service, $partial] as $source) {
            foreach ([
                'OpenAi', 'openai', 'AskAiOpenAiAdapterService', 'OpenAiClientService',
                'AskAiQuestionClassifierService', 'AskAiIntentNormalizerService', 'AskAiRunnerV2Service',
                'AskAiKnowledgeSearchService', 'AskAiInternalRunnerService', 'Http::', 'Guzzle', 'curl_',
                'DB::', 'file_get_contents',
            ] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $source, "Found '{$forbidden}'.");
            }
        }

        $reflection = new \ReflectionClass(AskAiPublicPropertyQuestionService::class);
        $this->assertNull($reflection->getConstructor(), 'The service must have no injected dependencies.');

        // The partial is inert markup: no script, no form, no Livewire, no Ask AI textbox,
        // no link or handler that could turn revealing an answer into a request.
        foreach (['<script', '<form', 'wire:', 'fetch(', 'XMLHttpRequest', 'solAi', 'lolAi', 'textarea', '<input', 'href=', 'onclick', '/ask-ai', '/agent-ai'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $partial, "Partial contains '{$forbidden}'.");
        }
    }
}
