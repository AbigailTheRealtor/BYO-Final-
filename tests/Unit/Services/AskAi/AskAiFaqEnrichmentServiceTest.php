<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFaqEnrichmentService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiFaqEnrichmentServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * Tests cover the static config-reading and normalization logic that has no DB dependency.
 * The sync() method (which writes to DB) is tested via feature tests in AskAiFaqEnrichmentFeatureTest.
 *
 * Test coverage:
 *   A. buildConfigIndex returns empty array for unknown listing type
 *   B. buildConfigIndex returns non-empty array for seller
 *   C. buildConfigIndex returns non-empty array for landlord
 *   D. buildConfigIndex returns non-empty array for buyer
 *   E. buildConfigIndex returns non-empty array for tenant
 *   F. Seller config index contains known key with correct shape
 *   G. Landlord config index contains known key with correct shape
 *   H. Buyer config index contains known key with correct shape
 *   I. Tenant config index contains known key with correct shape
 *   J. Seller config index resolves addon questions
 *   K. Each index entry has question_group, question_label, intelligence_category keys
 *   L. groupToCategory normalizes group names with & correctly
 *   M. groupToCategory normalizes group names with – and — correctly
 *   N. groupToCategory normalizes group names with slashes and apostrophes correctly
 *   O. groupToCategory produces lowercase snake_case output
 *   P. groupToCategory collapses multiple spaces/underscores
 *   Q. intelligence_category for seller question matches groupToCategory of its group
 *   R. intelligence_category for tenant question matches groupToCategory of its category
 *   S. Service file contains no write-gating violations (static grep on non-comment lines)
 */
class AskAiFaqEnrichmentServiceTest extends TestCase
{
    /**
     * Absolute path to the service file.
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiFaqEnrichmentService.php';
    }

    // =========================================================================
    // Case A — unknown listing type
    // =========================================================================

    public function test_case_A_buildConfigIndex_returns_empty_for_unknown_type(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('unknown_type');

        $this->assertIsArray($index);
        $this->assertEmpty($index, 'Unknown listing type must produce an empty config index');
    }

    // =========================================================================
    // Cases B-E — all four listing types return non-empty indices
    // =========================================================================

    public function test_case_B_buildConfigIndex_non_empty_for_seller(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('seller');

        $this->assertIsArray($index);
        $this->assertNotEmpty($index, 'Seller config index must not be empty');
    }

    public function test_case_C_buildConfigIndex_non_empty_for_landlord(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('landlord');

        $this->assertIsArray($index);
        $this->assertNotEmpty($index, 'Landlord config index must not be empty');
    }

    public function test_case_D_buildConfigIndex_non_empty_for_buyer(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('buyer');

        $this->assertIsArray($index);
        $this->assertNotEmpty($index, 'Buyer config index must not be empty');
    }

    public function test_case_E_buildConfigIndex_non_empty_for_tenant(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('tenant');

        $this->assertIsArray($index);
        $this->assertNotEmpty($index, 'Tenant config index must not be empty');
    }

    // =========================================================================
    // Cases F-I — known keys resolve with correct shape per listing type
    // =========================================================================

    public function test_case_F_seller_index_contains_known_key_with_correct_shape(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('seller');

        $this->assertArrayHasKey('roof_age_and_condition', $index,
            "Seller config index must contain 'roof_age_and_condition'");

        $entry = $index['roof_age_and_condition'];
        $this->assertArrayHasKey('question_group',        $entry);
        $this->assertArrayHasKey('question_label',        $entry);
        $this->assertArrayHasKey('intelligence_category', $entry);
        $this->assertSame('Property Condition & Systems', $entry['question_group']);
        $this->assertNotEmpty($entry['question_label']);
        $this->assertNotEmpty($entry['intelligence_category']);
    }

    public function test_case_G_landlord_index_contains_known_key_with_correct_shape(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('landlord');

        $this->assertArrayHasKey('maintenance_request_response_time', $index,
            "Landlord config index must contain 'maintenance_request_response_time'");

        $entry = $index['maintenance_request_response_time'];
        $this->assertArrayHasKey('question_group',        $entry);
        $this->assertArrayHasKey('question_label',        $entry);
        $this->assertArrayHasKey('intelligence_category', $entry);
        $this->assertSame('Tenancy & Maintenance', $entry['question_group']);
        $this->assertNotEmpty($entry['question_label']);
        $this->assertNotEmpty($entry['intelligence_category']);
    }

    public function test_case_H_buyer_index_contains_known_key_with_correct_shape(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('buyer');

        $this->assertArrayHasKey('buyer_motivation', $index,
            "Buyer config index must contain 'buyer_motivation'");

        $entry = $index['buyer_motivation'];
        $this->assertArrayHasKey('question_group',        $entry);
        $this->assertArrayHasKey('question_label',        $entry);
        $this->assertArrayHasKey('intelligence_category', $entry);
        $this->assertSame('Buyer Background', $entry['question_group']);
        $this->assertNotEmpty($entry['question_label']);
        $this->assertNotEmpty($entry['intelligence_category']);
    }

    public function test_case_I_tenant_index_contains_known_key_with_correct_shape(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('tenant');

        $this->assertArrayHasKey('faq_q14', $index,
            "Tenant config index must contain 'faq_q14'");

        $entry = $index['faq_q14'];
        $this->assertArrayHasKey('question_group',        $entry);
        $this->assertArrayHasKey('question_label',        $entry);
        $this->assertArrayHasKey('intelligence_category', $entry);
        $this->assertSame('Applicant Background', $entry['question_group']);
        $this->assertNotEmpty($entry['question_label']);
        $this->assertNotEmpty($entry['intelligence_category']);
    }

    // =========================================================================
    // Case J — seller non-universal (property-type) group questions are indexed
    // =========================================================================

    public function test_case_J_seller_property_type_group_questions_included_in_index(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('seller');

        $this->assertArrayHasKey('annual_operating_expenses_detail', $index,
            "Seller config index must include income-group question 'annual_operating_expenses_detail'");

        $this->assertArrayHasKey('business_reason_for_selling', $index,
            "Seller config index must include business-group question 'business_reason_for_selling'");

        $this->assertArrayHasKey('land_soil_and_topography', $index,
            "Seller config index must include land-group question 'land_soil_and_topography'");
    }

    // =========================================================================
    // Case K — every entry has all three required keys
    // =========================================================================

    public function test_case_K_all_seller_entries_have_required_keys(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('seller');

        foreach ($index as $key => $entry) {
            $this->assertArrayHasKey('question_group',        $entry, "Key '{$key}' missing question_group");
            $this->assertArrayHasKey('question_label',        $entry, "Key '{$key}' missing question_label");
            $this->assertArrayHasKey('intelligence_category', $entry, "Key '{$key}' missing intelligence_category");
        }
    }

    public function test_case_K_all_tenant_entries_have_required_keys(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('tenant');

        foreach ($index as $key => $entry) {
            $this->assertArrayHasKey('question_group',        $entry, "Key '{$key}' missing question_group");
            $this->assertArrayHasKey('question_label',        $entry, "Key '{$key}' missing question_label");
            $this->assertArrayHasKey('intelligence_category', $entry, "Key '{$key}' missing intelligence_category");
        }
    }

    // =========================================================================
    // Cases L-P — groupToCategory normalization
    // =========================================================================

    public function test_case_L_groupToCategory_handles_ampersand(): void
    {
        $result = AskAiFaqEnrichmentService::groupToCategory('Property Condition & Maintenance');

        $this->assertSame('property_condition_maintenance', $result);
    }

    public function test_case_L_groupToCategory_handles_and_in_financial(): void
    {
        $result = AskAiFaqEnrichmentService::groupToCategory('Financial & Utility Insights');

        $this->assertSame('financial_utility_insights', $result);
    }

    public function test_case_M_groupToCategory_handles_en_dash(): void
    {
        $result = AskAiFaqEnrichmentService::groupToCategory('Commercial – Business Use');

        $this->assertSame('commercial_business_use', $result);
    }

    public function test_case_M_groupToCategory_handles_hyphen(): void
    {
        $result = AskAiFaqEnrichmentService::groupToCategory('High-Intent Tenant Questions');

        $this->assertSame('high_intent_tenant_questions', $result);
    }

    public function test_case_N_groupToCategory_handles_slashes(): void
    {
        $result = AskAiFaqEnrichmentService::groupToCategory('Tax/Legal/HOA');

        $this->assertSame('tax_legal_hoa', $result);
    }

    public function test_case_O_groupToCategory_produces_lowercase_snake_case(): void
    {
        $result = AskAiFaqEnrichmentService::groupToCategory('Hidden Selling Points');

        $this->assertSame(strtolower($result), $result, 'Output must be lowercase');
        $this->assertStringNotContainsString(' ', $result, 'Output must not contain spaces');
    }

    public function test_case_P_groupToCategory_collapses_extra_spaces(): void
    {
        $result = AskAiFaqEnrichmentService::groupToCategory('Buyer  Intent   Lifestyle');

        $this->assertStringNotContainsString('__', $result,
            'Multiple spaces must not produce doubled underscores');
    }

    // =========================================================================
    // Case Q — intelligence_category matches groupToCategory of the group
    // =========================================================================

    public function test_case_Q_seller_intelligence_category_matches_group_normalization(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('seller');

        foreach ($index as $key => $entry) {
            if ($entry['question_group'] !== null) {
                $expected = AskAiFaqEnrichmentService::groupToCategory($entry['question_group']);
                $this->assertSame(
                    $expected,
                    $entry['intelligence_category'],
                    "intelligence_category for '{$key}' must equal groupToCategory(question_group)"
                );
            }
        }
    }

    public function test_case_R_tenant_intelligence_category_matches_group_normalization(): void
    {
        $index = AskAiFaqEnrichmentService::buildConfigIndex('tenant');

        foreach ($index as $key => $entry) {
            if ($entry['question_group'] !== null) {
                $expected = AskAiFaqEnrichmentService::groupToCategory($entry['question_group']);
                $this->assertSame(
                    $expected,
                    $entry['intelligence_category'],
                    "intelligence_category for '{$key}' must equal groupToCategory(question_group)"
                );
            }
        }
    }

    // =========================================================================
    // Case S — no forbidden patterns in the service file
    // =========================================================================

    public function test_case_S_service_file_has_no_openai_or_http_calls(): void
    {
        $source   = file_get_contents($this->serviceFilePath());
        $lines    = explode("\n", $source);
        $offences = [];

        $forbidden = ['OpenAI', 'openai', 'Http::get', 'Http::post', 'guzzle', 'curl_'];

        foreach ($lines as $i => $line) {
            $stripped = preg_replace('/\/\/.*$/', '', $line);
            $stripped = preg_replace('/\*.*$/', '', $stripped);

            foreach ($forbidden as $pattern) {
                if (str_contains($stripped, $pattern)) {
                    $offences[] = sprintf('Line %d: %s', $i + 1, trim($line));
                }
            }
        }

        $this->assertEmpty($offences,
            "Service file must not contain OpenAI or HTTP calls:\n" . implode("\n", $offences));
    }
}
