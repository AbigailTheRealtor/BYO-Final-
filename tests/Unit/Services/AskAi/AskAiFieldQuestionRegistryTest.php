<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiFieldQuestionRegistryTest
 *
 * Unit tests for AskAiFieldQuestionRegistryService.
 *
 * Verifies the structure, role coverage, utility methods, and governance
 * documentation of the full registry (all four roles: seller, buyer,
 * landlord, tenant) without touching the classifier or runner.
 */
class AskAiFieldQuestionRegistryTest extends TestCase
{
    private const VALID_ROUTE_STATUSES = ['pinned', 'umbrella_only', 'match_criteria', 'opaque_key', 'listing_native'];

    // -------------------------------------------------------------------------
    // Registry shape
    // -------------------------------------------------------------------------

    public function test_registry_returns_array(): void
    {
        $this->assertIsArray(AskAiFieldQuestionRegistryService::registry());
    }

    public function test_registry_is_not_empty(): void
    {
        $this->assertNotEmpty(AskAiFieldQuestionRegistryService::registry());
    }

    public function test_all_canonical_paths_match_registry_keys(): void
    {
        $paths    = AskAiFieldQuestionRegistryService::allCanonicalPaths();
        $expected = array_keys(AskAiFieldQuestionRegistryService::registry());

        $this->assertSame($expected, $paths);
    }

    // -------------------------------------------------------------------------
    // Required fields — all entries
    // -------------------------------------------------------------------------

    public function test_every_entry_has_roles_array(): void
    {
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $this->assertArrayHasKey('roles', $entry, "Missing 'roles' in {$path}");
            $this->assertIsArray($entry['roles'], "'roles' must be array in {$path}");
            $this->assertNotEmpty($entry['roles'], "'roles' must not be empty in {$path}");
        }
    }

    public function test_every_entry_has_config_key(): void
    {
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $this->assertArrayHasKey('config_key', $entry, "Missing 'config_key' in {$path}");
            $this->assertNotEmpty($entry['config_key'], "'config_key' must not be empty in {$path}");
        }
    }

    public function test_every_entry_has_label(): void
    {
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $this->assertArrayHasKey('label', $entry, "Missing 'label' in {$path}");
            $this->assertNotEmpty($entry['label'], "'label' must not be empty in {$path}");
        }
    }

    public function test_every_entry_has_sample_question(): void
    {
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $this->assertArrayHasKey('sample_question', $entry, "Missing 'sample_question' in {$path}");
            $this->assertNotEmpty($entry['sample_question'], "'sample_question' must not be empty in {$path}");
        }
    }

    public function test_every_faq_entry_has_non_empty_sample_question_2(): void
    {
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $this->assertArrayHasKey('sample_question_2', $entry, "Missing 'sample_question_2' in {$path}");
            $this->assertNotEmpty(
                trim($entry['sample_question_2'] ?? ''),
                "'sample_question_2' must not be empty in {$path} — all FAQ entries need ≥2 sample questions"
            );
        }
    }

    public function test_every_faq_entry_has_field_type_faq(): void
    {
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $this->assertArrayHasKey('field_type', $entry, "Missing 'field_type' in {$path}");
            $this->assertSame(
                'faq',
                $entry['field_type'],
                "Every registry() entry must have field_type='faq' — got '{$entry['field_type']}' in {$path}"
            );
        }
    }

    public function test_sample_question_2_is_distinct_from_sample_question_1(): void
    {
        $duplicates = [];
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $q1 = strtolower(trim($entry['sample_question']  ?? ''));
            $q2 = strtolower(trim($entry['sample_question_2'] ?? ''));
            if ($q1 !== '' && $q1 === $q2) {
                $duplicates[] = $path;
            }
        }
        $this->assertEmpty(
            $duplicates,
            "sample_question and sample_question_2 must be distinct questions:\n  - "
                . implode("\n  - ", $duplicates)
        );
    }

    public function test_every_entry_has_keyword_route_status(): void
    {
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $this->assertArrayHasKey('keyword_route_status', $entry, "Missing 'keyword_route_status' in {$path}");
            $this->assertNotEmpty($entry['keyword_route_status'], "'keyword_route_status' must not be empty in {$path}");
        }
    }

    public function test_every_entry_has_valid_keyword_route_status(): void
    {
        $bad = [];
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            if (!in_array($entry['keyword_route_status'] ?? '', self::VALID_ROUTE_STATUSES, true)) {
                $bad[] = "{$path}: '{$entry['keyword_route_status']}'";
            }
        }
        $this->assertEmpty($bad, "Invalid keyword_route_status values:\n  - " . implode("\n  - ", $bad));
    }

    // -------------------------------------------------------------------------
    // Canonical path format
    // -------------------------------------------------------------------------

    public function test_all_canonical_paths_start_with_faq_answers(): void
    {
        foreach (AskAiFieldQuestionRegistryService::allCanonicalPaths() as $path) {
            $this->assertStringStartsWith(
                'faq_answers.',
                $path,
                "Canonical path must start with 'faq_answers.': {$path}"
            );
        }
    }

    public function test_canonical_path_config_key_matches_suffix(): void
    {
        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $suffix = substr($path, strlen('faq_answers.'));
            $this->assertSame(
                $entry['config_key'],
                $suffix,
                "Canonical path suffix [{$suffix}] must match config_key [{$entry['config_key']}] for {$path}"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Role validity
    // -------------------------------------------------------------------------

    public function test_all_roles_are_valid(): void
    {
        $validRoles = ['seller', 'landlord', 'buyer', 'tenant'];

        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            foreach ($entry['roles'] as $role) {
                $this->assertContains(
                    $role,
                    $validRoles,
                    "Invalid role '{$role}' in {$path}"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // pinnedRegistry() and pinnedPaths()
    // -------------------------------------------------------------------------

    public function test_pinned_registry_returns_only_pinned_entries(): void
    {
        foreach (AskAiFieldQuestionRegistryService::pinnedRegistry() as $path => $entry) {
            $this->assertSame(
                'pinned',
                $entry['keyword_route_status'],
                "pinnedRegistry() returned a non-pinned entry: {$path}"
            );
        }
    }

    public function test_pinned_paths_are_subset_of_all_canonical_paths(): void
    {
        $all    = AskAiFieldQuestionRegistryService::allCanonicalPaths();
        $pinned = AskAiFieldQuestionRegistryService::pinnedPaths();

        foreach ($pinned as $path) {
            $this->assertContains($path, $all, "pinnedPaths() returned a path not in allCanonicalPaths(): {$path}");
        }
    }

    public function test_pinned_paths_count_is_less_than_all_paths(): void
    {
        $this->assertLessThan(
            count(AskAiFieldQuestionRegistryService::allCanonicalPaths()),
            count(AskAiFieldQuestionRegistryService::pinnedPaths()),
            'pinnedPaths() must be a strict subset of allCanonicalPaths() since non-pinned entries exist'
        );
    }

    // -------------------------------------------------------------------------
    // byRouteStatus()
    // -------------------------------------------------------------------------

    public function test_by_route_status_returns_only_matching_entries(): void
    {
        foreach (self::VALID_ROUTE_STATUSES as $status) {
            foreach (AskAiFieldQuestionRegistryService::byRouteStatus($status) as $path => $entry) {
                $this->assertSame(
                    $status,
                    $entry['keyword_route_status'],
                    "byRouteStatus('{$status}') returned entry with wrong status: {$path}"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // forRoles()
    // -------------------------------------------------------------------------

    public function test_for_roles_returns_only_matching_entries(): void
    {
        $sellerEntries = AskAiFieldQuestionRegistryService::forRoles('seller');

        $this->assertNotEmpty($sellerEntries);

        foreach ($sellerEntries as $path => $entry) {
            $this->assertContains(
                'seller',
                $entry['roles'],
                "Entry {$path} returned by forRoles('seller') but does not have 'seller' role"
            );
        }
    }

    public function test_for_roles_with_landlord_returns_landlord_entries(): void
    {
        $landlordEntries = AskAiFieldQuestionRegistryService::forRoles('landlord');

        $this->assertNotEmpty($landlordEntries);

        foreach ($landlordEntries as $path => $entry) {
            $this->assertContains(
                'landlord',
                $entry['roles'],
                "Entry {$path} returned by forRoles('landlord') but does not have 'landlord' role"
            );
        }
    }

    public function test_for_roles_with_buyer_returns_buyer_entries(): void
    {
        $buyerEntries = AskAiFieldQuestionRegistryService::forRoles('buyer');

        $this->assertNotEmpty($buyerEntries, 'Registry must contain buyer entries');

        foreach ($buyerEntries as $path => $entry) {
            $this->assertContains(
                'buyer',
                $entry['roles'],
                "Entry {$path} returned by forRoles('buyer') but does not have 'buyer' role"
            );
        }
    }

    public function test_for_roles_with_tenant_returns_tenant_entries(): void
    {
        $tenantEntries = AskAiFieldQuestionRegistryService::forRoles('tenant');

        $this->assertNotEmpty($tenantEntries, 'Registry must contain tenant entries');

        foreach ($tenantEntries as $path => $entry) {
            $this->assertContains(
                'tenant',
                $entry['roles'],
                "Entry {$path} returned by forRoles('tenant') but does not have 'tenant' role"
            );
        }
    }

    public function test_for_roles_with_multiple_roles(): void
    {
        $combined     = AskAiFieldQuestionRegistryService::forRoles(['seller', 'landlord']);
        $sellerOnly   = AskAiFieldQuestionRegistryService::forRoles('seller');
        $landlordOnly = AskAiFieldQuestionRegistryService::forRoles('landlord');

        $this->assertGreaterThanOrEqual(
            max(count($sellerOnly), count($landlordOnly)),
            count($combined),
            'Combined roles must return at least as many entries as either role alone'
        );
    }

    public function test_for_roles_with_unknown_role_returns_empty(): void
    {
        $result = AskAiFieldQuestionRegistryService::forRoles('unknown_role');
        $this->assertEmpty($result);
    }

    // -------------------------------------------------------------------------
    // allConfigKeys()
    // -------------------------------------------------------------------------

    public function test_all_config_keys_are_unique(): void
    {
        $keys = AskAiFieldQuestionRegistryService::allConfigKeys();

        $this->assertSame(
            count($keys),
            count(array_unique($keys)),
            'allConfigKeys() returned duplicate config keys'
        );
    }

    public function test_all_config_keys_are_snake_case(): void
    {
        foreach (AskAiFieldQuestionRegistryService::allConfigKeys() as $key) {
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $key,
                "Config key '{$key}' must be snake_case"
            );
        }
    }

    // -------------------------------------------------------------------------
    // sampleQuestions()
    // -------------------------------------------------------------------------

    public function test_sample_questions_map_has_same_count_as_registry(): void
    {
        $this->assertCount(
            count(AskAiFieldQuestionRegistryService::registry()),
            AskAiFieldQuestionRegistryService::sampleQuestions()
        );
    }

    public function test_sample_questions_are_strings(): void
    {
        foreach (AskAiFieldQuestionRegistryService::sampleQuestions() as $path => $question) {
            $this->assertIsString($question, "Sample question for {$path} must be a string");
            $this->assertNotEmpty($question, "Sample question for {$path} must not be empty");
        }
    }

    // -------------------------------------------------------------------------
    // allRoles()
    // -------------------------------------------------------------------------

    public function test_all_roles_returns_all_four_roles(): void
    {
        $roles = AskAiFieldQuestionRegistryService::allRoles();

        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            $this->assertContains($role, $roles, "allRoles() must include '{$role}'");
        }
    }

    // -------------------------------------------------------------------------
    // Coverage counts — documents expected minimums per role and status
    // -------------------------------------------------------------------------

    public function test_registry_has_at_least_one_hundred_entries(): void
    {
        $this->assertGreaterThanOrEqual(
            100,
            count(AskAiFieldQuestionRegistryService::registry()),
            'Registry should have at least 100 FAQ field entries across all four roles'
        );
    }

    public function test_pinned_entries_count_is_at_least_fifty(): void
    {
        $this->assertGreaterThanOrEqual(
            50,
            count(AskAiFieldQuestionRegistryService::pinnedRegistry()),
            'Registry should have at least 50 pinned (keyword-routed) entries'
        );
    }

    public function test_seller_entries_count_is_at_least_twenty(): void
    {
        $this->assertGreaterThanOrEqual(
            20,
            count(AskAiFieldQuestionRegistryService::forRoles('seller'))
        );
    }

    public function test_landlord_entries_count_is_at_least_fifteen(): void
    {
        $this->assertGreaterThanOrEqual(
            15,
            count(AskAiFieldQuestionRegistryService::forRoles('landlord'))
        );
    }

    public function test_buyer_entries_count_is_at_least_twenty(): void
    {
        $this->assertGreaterThanOrEqual(
            20,
            count(AskAiFieldQuestionRegistryService::forRoles('buyer')),
            'Buyer registry should have at least 20 match_criteria entries'
        );
    }

    public function test_tenant_entries_count_is_at_least_twenty(): void
    {
        $this->assertGreaterThanOrEqual(
            20,
            count(AskAiFieldQuestionRegistryService::forRoles('tenant')),
            'Tenant registry should have at least 20 pinned entries (faq_q1–faq_q27)'
        );
    }

    public function test_seller_and_landlord_addon_entries_are_pinned(): void
    {
        $pinned          = AskAiFieldQuestionRegistryService::byRouteStatus('pinned');
        $pinnedConfigKeys = array_map(fn (array $e) => $e['config_key'], $pinned);

        $sellerAddonSamples = [
            'annual_net_operating_income', 'current_cap_rate', 'land_utilities_availability',
            'annual_business_revenue', 'land_zoning_permitted_uses',
        ];
        $landlordAddonSamples = [
            'commercial_cam_charges', 'commercial_lease_structure_type',
            'commercial_tenant_improvement_allowance', 'commercial_parking_ratio',
        ];

        foreach (array_merge($sellerAddonSamples, $landlordAddonSamples) as $key) {
            $this->assertContains(
                $key,
                $pinnedConfigKeys,
                "Addon entry '{$key}' must be pinned (keyword-routed) in the registry"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Governance: role-status consistency assertions
    // -------------------------------------------------------------------------

    public function test_all_buyer_entries_are_match_criteria_or_umbrella_only(): void
    {
        $allowedForBuyer = ['match_criteria', 'umbrella_only'];
        $bad             = [];

        foreach (AskAiFieldQuestionRegistryService::forRoles('buyer') as $path => $entry) {
            if (!in_array($entry['keyword_route_status'], $allowedForBuyer, true)) {
                $bad[] = "{$path}: unexpected status '{$entry['keyword_route_status']}'";
            }
        }

        $this->assertEmpty(
            $bad,
            "Buyer entries should only have match_criteria or umbrella_only status:\n  - "
                . implode("\n  - ", $bad)
        );
    }

    public function test_all_tenant_entries_are_pinned(): void
    {
        $bad = [];

        foreach (AskAiFieldQuestionRegistryService::forRoles('tenant') as $path => $entry) {
            if ($entry['keyword_route_status'] !== 'pinned') {
                $bad[] = "{$path}: unexpected status '{$entry['keyword_route_status']}'";
            }
        }

        $this->assertEmpty(
            $bad,
            "Tenant entries should all have pinned status (faq_q1–faq_q27 all keyword-routed):\n  - "
                . implode("\n  - ", $bad)
        );
    }

    public function test_tenant_includes_all_27_opaque_keys(): void
    {
        $tenantPaths = array_keys(AskAiFieldQuestionRegistryService::forRoles('tenant'));

        for ($i = 1; $i <= 27; $i++) {
            $this->assertContains(
                "faq_answers.faq_q{$i}",
                $tenantPaths,
                "Tenant registry must include faq_answers.faq_q{$i}"
            );
        }
    }

    // -------------------------------------------------------------------------
    // listingFieldRegistry() — shape, paths, and utility methods
    // -------------------------------------------------------------------------

    public function test_listing_field_registry_returns_array(): void
    {
        $this->assertIsArray(AskAiFieldQuestionRegistryService::listingFieldRegistry());
    }

    public function test_listing_field_registry_count_is_at_least_forty(): void
    {
        $this->assertGreaterThanOrEqual(
            40,
            count(AskAiFieldQuestionRegistryService::listingFieldRegistry()),
            'listingFieldRegistry() must contain at least 40 native listing model entries'
        );
    }

    public function test_all_listing_field_paths_start_with_listing_prefix(): void
    {
        foreach (AskAiFieldQuestionRegistryService::allListingFieldPaths() as $path) {
            $this->assertStringStartsWith(
                'listing.',
                $path,
                "All listingFieldRegistry() canonical paths must start with 'listing.': {$path}"
            );
        }
    }

    public function test_listing_field_entries_have_required_fields(): void
    {
        $required = [
            'roles', 'field_type', 'config_key', 'label',
            'sample_question', 'sample_question_2', 'keyword_route_status',
        ];
        $bad = [];

        foreach (AskAiFieldQuestionRegistryService::listingFieldRegistry() as $path => $entry) {
            foreach ($required as $field) {
                if (!array_key_exists($field, $entry) || $entry[$field] === '' || $entry[$field] === []) {
                    $bad[] = "{$path} missing or empty: {$field}";
                }
            }
        }

        $this->assertEmpty(
            $bad,
            "listingFieldRegistry() structural issues:\n  - " . implode("\n  - ", $bad)
        );
    }

    public function test_every_listing_field_entry_has_field_type_listing_model(): void
    {
        foreach (AskAiFieldQuestionRegistryService::listingFieldRegistry() as $path => $entry) {
            $this->assertSame(
                'listing_model',
                $entry['field_type'] ?? '',
                "Every listingFieldRegistry() entry must have field_type='listing_model': {$path}"
            );
        }
    }

    public function test_every_listing_field_entry_has_keyword_route_status_listing_native(): void
    {
        foreach (AskAiFieldQuestionRegistryService::listingFieldRegistry() as $path => $entry) {
            $this->assertSame(
                'listing_native',
                $entry['keyword_route_status'] ?? '',
                "Every listingFieldRegistry() entry must have keyword_route_status='listing_native': {$path}"
            );
        }
    }

    public function test_listing_field_canonical_path_config_key_matches_suffix(): void
    {
        foreach (AskAiFieldQuestionRegistryService::listingFieldRegistry() as $path => $entry) {
            $suffix = substr($path, strlen('listing.'));
            $this->assertSame(
                $entry['config_key'],
                $suffix,
                "Listing path suffix [{$suffix}] must match config_key [{$entry['config_key']}] in {$path}"
            );
        }
    }

    public function test_listing_field_sample_questions_are_distinct(): void
    {
        $duplicates = [];
        foreach (AskAiFieldQuestionRegistryService::listingFieldRegistry() as $path => $entry) {
            $q1 = strtolower(trim($entry['sample_question']   ?? ''));
            $q2 = strtolower(trim($entry['sample_question_2'] ?? ''));
            if ($q1 !== '' && $q1 === $q2) {
                $duplicates[] = $path;
            }
        }
        $this->assertEmpty(
            $duplicates,
            "listingFieldRegistry() entries with identical sample_question and sample_question_2:\n  - "
                . implode("\n  - ", $duplicates)
        );
    }

    public function test_all_listing_field_paths_returns_only_listing_prefixed_keys(): void
    {
        $paths = AskAiFieldQuestionRegistryService::allListingFieldPaths();
        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $this->assertStringStartsWith('listing.', $path);
        }
    }

    public function test_listing_fields_by_role_returns_only_matching_entries(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $entries = AskAiFieldQuestionRegistryService::listingFieldsByRole($role);
            foreach ($entries as $path => $entry) {
                $this->assertContains(
                    $role,
                    $entry['roles'],
                    "listingFieldsByRole('{$role}') returned entry without role '{$role}': {$path}"
                );
            }
        }
    }

    public function test_listing_fields_cover_all_four_roles(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $entries = AskAiFieldQuestionRegistryService::listingFieldsByRole($role);
            $this->assertNotEmpty(
                $entries,
                "listingFieldsByRole('{$role}') must return at least one entry — "
                    . "all four roles should have native listing model fields"
            );
        }
    }

    public function test_listing_field_registry_does_not_overlap_with_faq_registry(): void
    {
        $faqPaths     = array_flip(AskAiFieldQuestionRegistryService::allCanonicalPaths());
        $listingPaths = AskAiFieldQuestionRegistryService::allListingFieldPaths();
        $overlap      = [];

        foreach ($listingPaths as $path) {
            if (isset($faqPaths[$path])) {
                $overlap[] = $path;
            }
        }

        $this->assertEmpty(
            $overlap,
            "FAQ registry (faq_answers.*) and listing field registry (listing.*) must not share paths:\n  - "
                . implode("\n  - ", $overlap)
        );
    }

    // -------------------------------------------------------------------------
    // Specific key existence checks (regression guards for key FAQ fields)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider criticalFaqKeyProvider
     */
    public function test_critical_faq_key_exists_in_registry(string $canonicalPath): void
    {
        $this->assertArrayHasKey(
            $canonicalPath,
            AskAiFieldQuestionRegistryService::registry(),
            "Critical FAQ path [{$canonicalPath}] is missing from the registry"
        );
    }

    public static function criticalFaqKeyProvider(): array
    {
        return [
            // Seller condition keys
            'roof'                           => ['faq_answers.roof_age_and_condition'],
            'hvac age'                       => ['faq_answers.hvac_system_age'],
            'water heater'                   => ['faq_answers.water_heater_age_type'],
            'renovations'                    => ['faq_answers.recent_renovations_list'],
            'defects'                        => ['faq_answers.known_defects_issues'],
            'foundation'                     => ['faq_answers.foundation_type_and_issues'],
            'pest termite'                   => ['faq_answers.pest_termite_history'],
            'flood damage'                   => ['faq_answers.flood_damage_history'],
            'mold history'                   => ['faq_answers.mold_issues_history'],
            // Seller financial
            'utility costs'                  => ['faq_answers.average_utility_costs'],
            'seller concessions'             => ['faq_answers.seller_concessions_offered'],
            // Seller negotiation
            'items excluded'                 => ['faq_answers.items_excluded_from_sale'],
            'as-is'                          => ['faq_answers.as_is_condition'],
            'closing timeline'               => ['faq_answers.closing_timeline_flexibility'],
            // Landlord condition
            'maintenance requests'           => ['faq_answers.maintenance_request_response_time'],
            'emergency maintenance'          => ['faq_answers.emergency_maintenance_available'],
            'heating cooling'                => ['faq_answers.heating_cooling_system'],
            'laundry'                        => ['faq_answers.laundry_situation'],
            'security features'              => ['faq_answers.security_features'],
            'pest mold landlord'             => ['faq_answers.pest_or_mold_history'],
            // Landlord lifestyle
            'lease renewal process'          => ['faq_answers.lease_renewal_process'],
            'subletting'                     => ['faq_answers.subletting_allowed'],
            'smoking policy'                 => ['faq_answers.smoking_policy'],
            // Buyer criteria (match_criteria)
            'buyer motivation'               => ['faq_answers.buyer_motivation'],
            'buyer deal breakers'            => ['faq_answers.buyer_deal_breakers'],
            'buyer must have features'       => ['faq_answers.buyer_must_have_features'],
            // Seller addons (umbrella_only)
            'seller commercial NOI'          => ['faq_answers.annual_net_operating_income'],
            'seller business reason selling' => ['faq_answers.business_reason_for_selling'],
            'seller land zoning'             => ['faq_answers.land_zoning_permitted_uses'],
            // Landlord addons (umbrella_only)
            'landlord commercial cam'        => ['faq_answers.commercial_cam_charges'],
            'landlord commercial lease type' => ['faq_answers.commercial_lease_structure_type'],
            // Tenant opaque keys
            'tenant faq_q1'                  => ['faq_answers.faq_q1'],
            'tenant faq_q14'                 => ['faq_answers.faq_q14'],
            'tenant faq_q27'                 => ['faq_answers.faq_q27'],
        ];
    }
}
