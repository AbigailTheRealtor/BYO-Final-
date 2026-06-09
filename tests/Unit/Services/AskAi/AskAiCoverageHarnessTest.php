<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiResponseContractService;
use App\Services\AskAi\AskAiRunnerV2Service;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * AskAiCoverageHarnessTest
 *
 * Automated coverage harness asserting structural completeness of Ask AI's
 * FAQ routing layer across all four listing roles (seller, buyer, landlord, tenant).
 *
 * Two test families:
 *
 * A. STATIC / STRUCTURAL — reflection-based checks (no pipeline execution):
 *   (1) Every pinned registry path has a FAQ_KEY_KEYWORD_MAP entry.
 *   (2) Every pinned registry path has a specific deriveFieldLabel entry (not fallback).
 *   (3) Every FAQ_KEY_KEYWORD_MAP key has a specific deriveFieldLabel entry.
 *   (4) listing_facts contract declares faq_answers as an allowed context path.
 *   (5) Critical natural-language phrases route to listing_facts (classifier regression guard).
 *   (6) No FAQ_KEY_KEYWORD_MAP keyword appears verbatim in competing intents.
 *   (7) Registry structural integrity (keyword_route_status field + value validity, including
 *       sample_question_2 and field_type presence on all FAQ entries).
 *   (8) FAQ_KEY_KEYWORD_MAP coverage count minimums.
 *   (9) match_criteria entries are NOT in FAQ_KEY_KEYWORD_MAP (governance check).
 *  (10) opaque_key and umbrella_only entries are NOT in FAQ_KEY_KEYWORD_MAP.
 *  (11) All four roles are represented in the registry.
 *  (12) AskAiContextBuilderService source statically contains every listing model field
 *       config_key declared in listingFieldRegistry() as a string literal.
 *  (13) AskAiResponseContractService source statically declares every listing.* path
 *       from listingFieldRegistry() in its allowed-paths list.
 *  (14) Every FAQ registry entry has a non-empty sample_question_2.
 *
 * Pure PHPUnit — no Laravel container, no DB.
 */
class AskAiCoverageHarnessTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getFaqKeyKeywordMap(): array
    {
        $rc = new ReflectionClass(AskAiRunnerV2Service::class);
        $c  = $rc->getConstant('FAQ_KEY_KEYWORD_MAP');
        $this->assertIsArray($c, 'FAQ_KEY_KEYWORD_MAP constant must be an array');
        return $c;
    }

    private function makeRunner(): AskAiRunnerV2Service
    {
        return new AskAiRunnerV2Service(
            $this->createMock(AskAiQuestionClassifierService::class),
            $this->createMock(AskAiInternalRunnerService::class),
            $this->createMock(AskAiOpenAiAdapterService::class),
            $this->createMock(AskAiFinalResponseBuilderService::class),
            $this->createMock(AskAiFollowUpQuestionService::class)
        );
    }

    private function callDeriveFieldLabel(string $canonicalPath): string
    {
        $runner = $this->makeRunner();
        $rc     = new ReflectionClass($runner);
        $method = $rc->getMethod('deriveFieldLabel');
        $method->setAccessible(true);
        return (string) $method->invoke($runner, $canonicalPath);
    }

    private function classifyQuestion(string $question): string
    {
        $result = (new AskAiQuestionClassifierService())->classify($question);
        return $result['question_type'] ?? 'unsupported';
    }

    private function getClassifierKeywordRules(): array
    {
        $rc = new ReflectionClass(AskAiQuestionClassifierService::class);
        $c  = $rc->getConstant('KEYWORD_RULES');
        $this->assertIsArray($c, 'AskAiQuestionClassifierService::KEYWORD_RULES must be an array');
        return $c;
    }

    private const GENERIC_LABEL_FALLBACK = 'The requested information';

    // -------------------------------------------------------------------------
    // (1) Every pinned registry path has a FAQ_KEY_KEYWORD_MAP entry
    // -------------------------------------------------------------------------

    public function test_every_pinned_registry_path_has_faq_keyword_map_entry(): void
    {
        $faqMap  = $this->getFaqKeyKeywordMap();
        $missing = [];

        foreach (AskAiFieldQuestionRegistryService::pinnedPaths() as $path) {
            if (!array_key_exists($path, $faqMap)) {
                $missing[] = $path;
            }
        }

        $this->assertEmpty(
            $missing,
            "Pinned registry paths missing from FAQ_KEY_KEYWORD_MAP:\n  - " . implode("\n  - ", $missing)
        );
    }

    // -------------------------------------------------------------------------
    // (2) Every pinned registry path has a specific deriveFieldLabel entry
    // -------------------------------------------------------------------------

    public function test_every_pinned_registry_path_has_derive_field_label_entry(): void
    {
        $missing = [];

        foreach (AskAiFieldQuestionRegistryService::pinnedPaths() as $path) {
            $label = $this->callDeriveFieldLabel($path);
            if ($label === self::GENERIC_LABEL_FALLBACK) {
                $missing[] = $path;
            }
        }

        $this->assertEmpty(
            $missing,
            "Pinned registry paths falling through to generic deriveFieldLabel fallback ('"
                . self::GENERIC_LABEL_FALLBACK . "'):\n  - "
                . implode("\n  - ", $missing)
        );
    }

    // -------------------------------------------------------------------------
    // (3) Every FAQ_KEY_KEYWORD_MAP entry resolves to a specific label
    // -------------------------------------------------------------------------

    public function test_every_faq_keyword_map_key_has_specific_label(): void
    {
        $genericFallback = [];

        foreach (array_keys($this->getFaqKeyKeywordMap()) as $canonicalPath) {
            $label = $this->callDeriveFieldLabel($canonicalPath);
            if ($label === self::GENERIC_LABEL_FALLBACK) {
                $genericFallback[] = $canonicalPath;
            }
        }

        $this->assertEmpty(
            $genericFallback,
            "FAQ_KEY_KEYWORD_MAP keys falling through to generic label fallback ('"
                . self::GENERIC_LABEL_FALLBACK . "'):\n  - "
                . implode("\n  - ", $genericFallback)
        );
    }

    // -------------------------------------------------------------------------
    // (4) listing_facts contract declares faq_answers as an allowed context path
    // -------------------------------------------------------------------------

    public function test_listing_facts_contract_includes_faq_answers_path(): void
    {
        $contract     = new AskAiResponseContractService();
        $allowedPaths = $contract->getListingFactsAllowedPaths();

        $hasFaqPath = false;
        foreach ($allowedPaths as $path) {
            if ($path === 'faq_answers' || str_starts_with((string) $path, 'faq_answers.')) {
                $hasFaqPath = true;
                break;
            }
        }

        $this->assertTrue(
            $hasFaqPath,
            "listing_facts contract must include 'faq_answers' in allowed paths.\n"
                . "allowed_paths: " . implode(', ', $allowedPaths)
        );
    }

    // -------------------------------------------------------------------------
    // (5) Critical natural-language phrases route to listing_facts
    // -------------------------------------------------------------------------

    /**
     * @dataProvider criticalListingFactsPhraseProvider
     */
    public function test_critical_listing_facts_phrase_routes_correctly(string $phrase): void
    {
        $result = $this->classifyQuestion($phrase);
        $this->assertSame(
            'listing_facts',
            $result,
            "Phrase [{$phrase}] should classify as listing_facts but got [{$result}]"
        );
    }

    public static function criticalListingFactsPhraseProvider(): array
    {
        return [
            // --- Existing coverage (regression guard) ---
            'roof age'                                    => ['how old is the roof?'],
            'hvac type'                                   => ['what is the hvac type?'],
            'in-unit laundry'                             => ['is there in-unit laundry?'],
            // --- Utility costs ---
            'average utility costs'                       => ['what are the average monthly utility costs?'],
            'how much are utilities'                      => ['how much are utilities?'],
            'utility costs bare'                          => ['utility costs'],
            // --- Renovations ---
            'what renovations'                            => ['what renovations have been made?'],
            'has it been renovated'                       => ['has it been renovated?'],
            // --- Defects ---
            'known defects'                               => ['are there any known defects?'],
            'known issues'                                => ['any known issues with this property?'],
            // --- Pest/termite ---
            'pest history'                                => ['any pest history?'],
            'have there been termites'                    => ['have there been termites?'],
            // --- Foundation ---
            'foundation type'                             => ['what type of foundation?'],
            'foundation problems'                         => ['any foundation problems?'],
            // --- Mold ---
            'mold history'                                => ['any mold history?'],
            'has there been mold'                         => ['has there been mold?'],
            // --- Flood/water damage ---
            'has the property flooded'                    => ['has the property flooded?'],
            'water damage history'                        => ['water damage history?'],
            // --- Seller concessions ---
            'seller concessions'                          => ['is the seller offering concessions?'],
            // --- Items excluded ---
            'what conveys'                                => ['what conveys with the property?'],
            'what does not convey'                        => ['what does not convey?'],
            // --- As-is ---
            'sold as-is'                                  => ['is it sold as-is?'],
            // --- HVAC age ---
            'how old is the hvac'                         => ['how old is the hvac?'],
            'when was the ac replaced'                    => ['when was the ac replaced?'],
            // --- Water heater ---
            'how old is the water heater'                 => ['how old is the water heater?'],
            // --- Maintenance ---
            'maintenance requests'                        => ['how are maintenance requests handled?'],
            'emergency maintenance'                       => ['is there emergency maintenance?'],
            // --- Lease renewal process ---
            'how does lease renewal work'                 => ['how does lease renewal work?'],
            // --- Security features ---
            'security system'                             => ['is there a security system?'],
            // --- Smoking / subletting ---
            'smoking policy'                              => ['is smoking allowed?'],
            'subletting allowed'                          => ['subletting allowed?'],
            'is subletting permitted'                     => ['is subletting permitted?'],
        ];
    }

    // -------------------------------------------------------------------------
    // (6) FAQ_KEY_KEYWORD_MAP keyword uniqueness across competing intents
    // -------------------------------------------------------------------------

    public function test_faq_keywords_are_not_duplicated_in_competing_intents(): void
    {
        $rules            = $this->getClassifierKeywordRules();
        $competingIntents = ['educational', 'property_standout', 'buyer_tenant_match'];

        $competingKeywords = [];
        foreach ($competingIntents as $intent) {
            foreach (($rules[$intent] ?? []) as $kw) {
                $competingKeywords[strtolower(trim($kw))] = $intent;
            }
        }

        $faqMap    = $this->getFaqKeyKeywordMap();
        $conflicts = [];

        foreach ($faqMap as $canonicalPath => $keywords) {
            foreach ($keywords as $kw) {
                $normalized = strtolower(trim($kw));
                if (isset($competingKeywords[$normalized])) {
                    $conflicts[] = sprintf(
                        '%s → keyword [%s] also in %s',
                        $canonicalPath,
                        $kw,
                        $competingKeywords[$normalized]
                    );
                }
            }
        }

        $this->assertEmpty(
            $conflicts,
            "FAQ_KEY_KEYWORD_MAP keywords that conflict with competing intents:\n  - "
                . implode("\n  - ", $conflicts)
        );
    }

    // -------------------------------------------------------------------------
    // (7) Registry structural integrity — keyword_route_status field
    // -------------------------------------------------------------------------

    private const VALID_ROUTE_STATUSES = ['pinned', 'umbrella_only', 'match_criteria', 'opaque_key', 'listing_native'];

    public function test_registry_entries_have_required_fields(): void
    {
        $required = [
            'roles', 'config_key', 'label', 'sample_question',
            'sample_question_2', 'field_type', 'keyword_route_status',
        ];
        $bad = [];

        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            foreach ($required as $field) {
                if (!array_key_exists($field, $entry) || ($entry[$field] === '' || $entry[$field] === [])) {
                    $bad[] = "{$path} missing or empty field: {$field}";
                }
            }

            if (isset($entry['roles']) && !is_array($entry['roles'])) {
                $bad[] = "{$path}: 'roles' must be an array";
            }
        }

        $this->assertEmpty(
            $bad,
            "Registry structural issues:\n  - " . implode("\n  - ", $bad)
        );
    }

    public function test_registry_keyword_route_status_values_are_valid(): void
    {
        $bad = [];

        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            $status = $entry['keyword_route_status'] ?? null;
            if (!in_array($status, self::VALID_ROUTE_STATUSES, true)) {
                $bad[] = "{$path}: invalid keyword_route_status '{$status}'";
            }
        }

        $this->assertEmpty(
            $bad,
            "Entries with invalid keyword_route_status values:\n  - " . implode("\n  - ", $bad)
        );
    }

    public function test_registry_canonical_paths_start_with_faq_answers_prefix(): void
    {
        $bad = [];
        foreach (AskAiFieldQuestionRegistryService::allCanonicalPaths() as $path) {
            if (!str_starts_with($path, 'faq_answers.')) {
                $bad[] = $path;
            }
        }

        $this->assertEmpty(
            $bad,
            "Registry canonical paths must start with 'faq_answers.':\n  - " . implode("\n  - ", $bad)
        );
    }

    public function test_registry_roles_are_valid(): void
    {
        $validRoles = ['seller', 'landlord', 'buyer', 'tenant'];
        $bad        = [];

        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            foreach (($entry['roles'] ?? []) as $role) {
                if (!in_array($role, $validRoles, true)) {
                    $bad[] = "{$path}: invalid role '{$role}'";
                }
            }
        }

        $this->assertEmpty(
            $bad,
            "Invalid roles in registry:\n  - " . implode("\n  - ", $bad)
        );
    }

    // -------------------------------------------------------------------------
    // (8) FAQ_KEY_KEYWORD_MAP coverage count
    // -------------------------------------------------------------------------

    public function test_faq_keyword_map_covers_at_least_expected_minimum_keys(): void
    {
        $faqMap = $this->getFaqKeyKeywordMap();

        $this->assertGreaterThanOrEqual(
            20,
            count($faqMap),
            'FAQ_KEY_KEYWORD_MAP should cover at least 20 distinct FAQ keys. Actual: ' . count($faqMap)
        );

        $totalKeywords = array_sum(array_map('count', $faqMap));
        $this->assertGreaterThanOrEqual(
            100,
            $totalKeywords,
            'FAQ_KEY_KEYWORD_MAP should contain at least 100 total keyword phrases. Actual: ' . $totalKeywords
        );
    }

    // -------------------------------------------------------------------------
    // (9) Governance: match_criteria entries are intentionally NOT pinned
    // -------------------------------------------------------------------------

    public function test_match_criteria_entries_are_not_in_faq_keyword_map(): void
    {
        $faqMap           = $this->getFaqKeyKeywordMap();
        $matchCriteria    = AskAiFieldQuestionRegistryService::byRouteStatus('match_criteria');
        $incorrectlyPinned = [];

        foreach (array_keys($matchCriteria) as $path) {
            if (array_key_exists($path, $faqMap)) {
                $incorrectlyPinned[] = $path;
            }
        }

        $this->assertEmpty(
            $incorrectlyPinned,
            "match_criteria entries must NOT appear in FAQ_KEY_KEYWORD_MAP "
                . "(they route via buyer_tenant_match, not listing_facts):\n  - "
                . implode("\n  - ", $incorrectlyPinned)
        );
    }

    // -------------------------------------------------------------------------
    // (10) Governance: opaque_key and umbrella_only — regression guards
    //
    // All former opaque_key (tenant) and umbrella_only (seller/landlord addon)
    // entries have been promoted to 'pinned'. These checks pass trivially now
    // (zero entries with those statuses) and remain as regression guards to
    // ensure no future entries are silently left un-pinned in the keyword map.
    // -------------------------------------------------------------------------

    public function test_opaque_key_entries_are_not_in_faq_keyword_map(): void
    {
        $faqMap  = $this->getFaqKeyKeywordMap();
        $opaque  = AskAiFieldQuestionRegistryService::byRouteStatus('opaque_key');
        $wrongly = [];

        foreach (array_keys($opaque) as $path) {
            if (array_key_exists($path, $faqMap)) {
                $wrongly[] = $path;
            }
        }

        $this->assertEmpty(
            $wrongly,
            "opaque_key entries must NOT appear in FAQ_KEY_KEYWORD_MAP "
                . "(any opaque_key entry added in future must be promoted to pinned first):\n  - "
                . implode("\n  - ", $wrongly)
        );
    }

    public function test_umbrella_only_entries_are_not_in_faq_keyword_map(): void
    {
        $faqMap   = $this->getFaqKeyKeywordMap();
        $umbrella = AskAiFieldQuestionRegistryService::byRouteStatus('umbrella_only');
        $wrongly  = [];

        foreach (array_keys($umbrella) as $path) {
            if (array_key_exists($path, $faqMap)) {
                $wrongly[] = $path;
            }
        }

        $this->assertEmpty(
            $wrongly,
            "umbrella_only entries must NOT appear in FAQ_KEY_KEYWORD_MAP "
                . "(any umbrella_only entry added in future must be promoted to pinned first):\n  - "
                . implode("\n  - ", $wrongly)
        );
    }

    // -------------------------------------------------------------------------
    // (11) All four roles are represented in the registry
    // -------------------------------------------------------------------------

    public function test_all_four_roles_present_in_registry(): void
    {
        $roles = AskAiFieldQuestionRegistryService::allRoles();

        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            $this->assertContains(
                $role,
                $roles,
                "Role '{$role}' must be present in the registry"
            );
        }
    }

    public function test_buyer_role_has_match_criteria_entries(): void
    {
        $buyerEntries = AskAiFieldQuestionRegistryService::forRoles('buyer');
        $this->assertNotEmpty($buyerEntries, 'Registry must contain buyer entries');

        $matchCriteria = array_filter(
            $buyerEntries,
            fn (array $e) => ($e['keyword_route_status'] ?? '') === 'match_criteria'
        );
        $this->assertNotEmpty($matchCriteria, 'Buyer entries must include match_criteria entries');
    }

    public function test_tenant_role_has_pinned_entries(): void
    {
        $tenantEntries = AskAiFieldQuestionRegistryService::forRoles('tenant');
        $this->assertNotEmpty($tenantEntries, 'Registry must contain tenant entries');

        $pinned = array_filter(
            $tenantEntries,
            fn (array $e) => ($e['keyword_route_status'] ?? '') === 'pinned'
        );
        $this->assertNotEmpty($pinned, 'Tenant entries must be pinned (faq_q1–faq_q27 all promoted)');
    }

    public function test_seller_and_landlord_have_pinned_addon_entries(): void
    {
        $pinned = AskAiFieldQuestionRegistryService::byRouteStatus('pinned');
        $this->assertNotEmpty($pinned, 'Registry must have pinned entries');

        $sellerAddonKeys   = ['annual_net_operating_income', 'annual_business_revenue', 'land_utilities_availability'];
        $landlordAddonKeys = ['commercial_cam_charges', 'commercial_lease_structure_type'];

        $allConfigKeys = array_map(fn (array $e) => $e['config_key'], $pinned);

        foreach ($sellerAddonKeys as $key) {
            $this->assertContains($key, $allConfigKeys,
                "Seller addon key '{$key}' must be pinned in the registry");
        }
        foreach ($landlordAddonKeys as $key) {
            $this->assertContains($key, $allConfigKeys,
                "Landlord addon key '{$key}' must be pinned in the registry");
        }
    }

    // -------------------------------------------------------------------------
    // (12) Context builder statically contains every listing model field config_key
    // -------------------------------------------------------------------------

    public function test_context_builder_source_contains_every_listing_model_field_key(): void
    {
        $source  = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php'
        );
        $this->assertNotEmpty($source, 'Could not read AskAiContextBuilderService.php');

        $missing = [];

        foreach (AskAiFieldQuestionRegistryService::listingFieldRegistry() as $path => $entry) {
            $key = $entry['config_key'];
            if (!str_contains($source, "'{$key}'") && !str_contains($source, "\"{$key}\"")) {
                $missing[] = "{$path} (config_key: '{$key}')";
            }
        }

        $this->assertEmpty(
            $missing,
            "Listing model fields whose config_key is absent from AskAiContextBuilderService source "
                . "(every listingFieldRegistry() entry must be extracted by the context builder):\n  - "
                . implode("\n  - ", $missing)
        );
    }

    // -------------------------------------------------------------------------
    // (13) Contract service statically declares every listing.* path
    // -------------------------------------------------------------------------

    public function test_contract_service_source_declares_every_listing_model_registry_path(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiResponseContractService.php'
        );
        $this->assertNotEmpty($source, 'Could not read AskAiResponseContractService.php');

        $missing = [];

        foreach (AskAiFieldQuestionRegistryService::allListingFieldPaths() as $path) {
            // $path is e.g. 'listing.bedrooms'
            if (!str_contains($source, "'{$path}'") && !str_contains($source, "\"{$path}\"")) {
                $missing[] = $path;
            }
        }

        $this->assertEmpty(
            $missing,
            "listing.* paths from listingFieldRegistry() not found in AskAiResponseContractService "
                . "(every path must appear as a string literal in the allowed-paths list):\n  - "
                . implode("\n  - ", $missing)
        );
    }

    // -------------------------------------------------------------------------
    // (14) Every FAQ registry entry has a non-empty sample_question_2
    // -------------------------------------------------------------------------

    public function test_every_faq_registry_entry_has_non_empty_sample_question_2(): void
    {
        $missing = [];

        foreach (AskAiFieldQuestionRegistryService::registry() as $path => $entry) {
            if (!isset($entry['sample_question_2']) || trim($entry['sample_question_2']) === '') {
                $missing[] = $path;
            }
        }

        $this->assertEmpty(
            $missing,
            "FAQ registry entries missing a non-empty sample_question_2 "
                . "(all 168 entries must have ≥2 natural-language questions):\n  - "
                . implode("\n  - ", $missing)
        );
    }

    // -------------------------------------------------------------------------
    // Listing field helpers (tests 15–17)
    // -------------------------------------------------------------------------

    private function getListingKeyKeywordMap(): array
    {
        $rc = new ReflectionClass(AskAiRunnerV2Service::class);
        $c  = $rc->getConstant('LISTING_KEY_KEYWORD_MAP');
        $this->assertIsArray($c, 'LISTING_KEY_KEYWORD_MAP constant must be an array');
        return $c;
    }

    // -------------------------------------------------------------------------
    // (15) Every listingFieldRegistry path has a LISTING_KEY_KEYWORD_MAP entry
    // -------------------------------------------------------------------------

    public function test_every_listing_field_registry_path_has_listing_keyword_map_entry(): void
    {
        $listingMap = $this->getListingKeyKeywordMap();
        $missing    = [];

        foreach (AskAiFieldQuestionRegistryService::allListingFieldPaths() as $path) {
            if (!array_key_exists($path, $listingMap)) {
                $missing[] = $path;
            }
        }

        $this->assertEmpty(
            $missing,
            "listing.* registry paths missing from LISTING_KEY_KEYWORD_MAP "
                . "(every field must have ≥1 natural-language keyword phrase):\n  - "
                . implode("\n  - ", $missing)
        );
    }

    // -------------------------------------------------------------------------
    // (16) Every LISTING_KEY_KEYWORD_MAP key resolves to a specific deriveFieldLabel
    // -------------------------------------------------------------------------

    public function test_every_listing_keyword_map_key_has_specific_derive_field_label(): void
    {
        $genericFallback = [];

        foreach (array_keys($this->getListingKeyKeywordMap()) as $canonicalPath) {
            $label = $this->callDeriveFieldLabel($canonicalPath);
            if ($label === self::GENERIC_LABEL_FALLBACK) {
                $genericFallback[] = $canonicalPath;
            }
        }

        $this->assertEmpty(
            $genericFallback,
            "LISTING_KEY_KEYWORD_MAP keys falling through to generic deriveFieldLabel fallback ('"
                . self::GENERIC_LABEL_FALLBACK . "'):\n  - "
                . implode("\n  - ", $genericFallback)
        );
    }

    // -------------------------------------------------------------------------
    // (17) LISTING_KEY_KEYWORD_MAP coverage count minimums
    // -------------------------------------------------------------------------

    public function test_listing_keyword_map_covers_at_least_expected_minimum_keys(): void
    {
        $listingMap = $this->getListingKeyKeywordMap();

        $this->assertGreaterThanOrEqual(
            40,
            count($listingMap),
            'LISTING_KEY_KEYWORD_MAP should cover at least 40 distinct listing.* keys. Actual: ' . count($listingMap)
        );

        $totalKeywords = array_sum(array_map('count', $listingMap));
        $this->assertGreaterThanOrEqual(
            150,
            $totalKeywords,
            'LISTING_KEY_KEYWORD_MAP should contain at least 150 total keyword phrases. Actual: ' . $totalKeywords
        );
    }

    // -------------------------------------------------------------------------
    // (18) LISTING_KEY_KEYWORD_MAP keys must all start with listing. prefix
    // -------------------------------------------------------------------------

    public function test_listing_keyword_map_keys_start_with_listing_prefix(): void
    {
        $bad = [];
        foreach (array_keys($this->getListingKeyKeywordMap()) as $path) {
            if (!str_starts_with($path, 'listing.')) {
                $bad[] = $path;
            }
        }

        $this->assertEmpty(
            $bad,
            "LISTING_KEY_KEYWORD_MAP keys must start with 'listing.':\n  - " . implode("\n  - ", $bad)
        );
    }
}
