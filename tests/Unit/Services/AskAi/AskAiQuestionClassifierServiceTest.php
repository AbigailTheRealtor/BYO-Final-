<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiQuestionClassifierService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiQuestionClassifierServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * AskAiQuestionClassifierService is stateless and deterministic; no mocking required.
 *
 * Test coverage (cases A–K):
 *   A. property_standout   — keyword questions are classified correctly.
 *   B. suited_audience     — keyword questions are classified correctly.
 *   C. buyer_tenant_match  — keyword questions are classified correctly.
 *   D. compatibility_signals — keyword questions are classified correctly.
 *   E. missing_data        — keyword questions are classified correctly.
 *   F. marketing_angles    — keyword questions are classified correctly.
 *   G. educational         — keyword questions are classified correctly.
 *   H. prohibited          — fair-housing-sensitive questions are classified correctly.
 *   I. unsupported         — unrecognised questions fall back to 'unsupported'.
 *   J. Return shape        — all three keys present; confidence is float in [0, 1].
 *   K. Governance grep     — no OpenAI, Http::, or write calls in the service file.
 */
class AskAiQuestionClassifierServiceTest extends TestCase
{
    private const REQUIRED_KEYS = ['question_type', 'confidence', 'reason'];

    private function makeService(): AskAiQuestionClassifierService
    {
        return new AskAiQuestionClassifierService();
    }

    /**
     * Absolute path to the service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiQuestionClassifierService.php';
    }

    private function loadCodeLines(): string
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Classifier service file does not exist at expected path');

        $content = file_get_contents($path);

        return implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));
    }

    // =========================================================================
    // Case A — property_standout
    // =========================================================================

    public function test_case_A_stand_out_phrase_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What makes this property stand out from others?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_A_best_feature_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What is the best feature of this home?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_A_selling_point_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What is the main selling point of this listing?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_A_highlight_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('Can you highlight what makes this listing special?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_A_strengths_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What are the strengths of this property?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_A_benefits_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What are the benefits of choosing this listing?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_A_bidding_process_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What is the bidding process for this property?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    // =========================================================================
    // Case B — suited_audience
    // =========================================================================

    public function test_case_B_ideal_for_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who is this property ideal for?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_B_ideal_buyer_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who is the ideal buyer for this home?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_B_suited_for_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who is this property suited for?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_B_who_would_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who would enjoy living in this home?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_B_type_of_buyer_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('What type of buyer would be interested in this?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_B_appeal_to_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who would this property appeal to the most?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_B_who_is_this_good_for_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who is this good for?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    // =========================================================================
    // Case C — buyer_tenant_match
    // =========================================================================

    public function test_case_C_good_match_for_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Is this a good match for a buyer with a large family?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_C_tenant_match_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Does this tenant match the listing criteria?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_C_buyer_match_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Would a buyer match these property requirements?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_C_fit_for_tenant_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Would this listing be a fit for tenant applicants?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_C_buyer_fit_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Does this home have a good buyer fit?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_C_how_well_does_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('How well does this listing suit the buyer?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    // =========================================================================
    // Case D — compatibility_signals
    // =========================================================================

    public function test_case_D_compatibility_keyword_classifies_as_compatibility_signals(): void
    {
        $result = $this->makeService()->classify('What are the compatibility signals for this listing?');
        $this->assertSame('compatibility_signals', $result['question_type']);
    }

    public function test_case_D_match_score_classifies_as_compatibility_signals(): void
    {
        $result = $this->makeService()->classify('What is the match score for this property?');
        $this->assertSame('compatibility_signals', $result['question_type']);
    }

    public function test_case_D_compatible_classifies_as_compatibility_signals(): void
    {
        $result = $this->makeService()->classify('Is this listing compatible with my search criteria?');
        $this->assertSame('compatibility_signals', $result['question_type']);
    }

    public function test_case_D_score_breakdown_classifies_as_compatibility_signals(): void
    {
        $result = $this->makeService()->classify('Can you show me the compatibility score breakdown?');
        $this->assertSame('compatibility_signals', $result['question_type']);
    }

    public function test_case_D_match_score_not_swallowed_by_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What is the match score for this listing?');
        $this->assertSame('compatibility_signals', $result['question_type'],
            '"match score" must route to compatibility_signals, not buyer_tenant_match');
    }

    // =========================================================================
    // Case C2 — buyer_tenant_match synonym expansions (rent budget / lease terms / move-in)
    // =========================================================================

    public function test_case_C2_rent_budget_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Does the rent budget align with this listing?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_C2_lease_length_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Does the preferred lease length match this property?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_C2_move_in_date_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Does the move-in date work for this listing?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_C2_move_in_date_no_hyphen_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Does the move in date work for this unit?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    // =========================================================================
    // Case C3 — Compliance regression: income and credit score must NOT route to buyer_tenant_match
    // =========================================================================

    public function test_case_C3_monthly_income_does_not_classify_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify("What is the tenant's monthly income?");
        $this->assertNotSame('buyer_tenant_match', $result['question_type'],
            'monthly income must not route to buyer_tenant_match — compliance-sensitive data');
    }

    public function test_case_C3_tenant_credit_score_does_not_classify_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify("What is the tenant's credit score?");
        $this->assertNotSame('buyer_tenant_match', $result['question_type'],
            'tenant credit score must not route to buyer_tenant_match — compliance-sensitive data');
    }

    public function test_case_C3_buyer_credit_score_does_not_classify_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify("What credit score does this buyer have?");
        $this->assertNotSame('buyer_tenant_match', $result['question_type'],
            'buyer credit score must not route to buyer_tenant_match — compliance-sensitive data');
    }

    public function test_case_C3_what_credit_score_does_not_classify_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What credit score is required for this listing?');
        $this->assertNotSame('buyer_tenant_match', $result['question_type'],
            'what credit score must not route to buyer_tenant_match — compliance-sensitive data');
    }

    // =========================================================================
    // Case E — missing_data
    // =========================================================================

    public function test_case_E_what_is_missing_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('What is missing from this listing?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_E_incomplete_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('This listing looks incomplete, what fields are blank?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_E_not_provided_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('What details are not provided in this listing?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_E_lacking_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('What information is lacking in this listing?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    // =========================================================================
    // Case F — marketing_angles
    // =========================================================================

    public function test_case_F_how_to_market_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('How to market this property effectively?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_F_marketing_strategy_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('What is the best marketing strategy for this home?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_F_marketing_angle_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('What marketing angle should we lead with?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_F_advertise_this_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('Where should we advertise this listing?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_F_listing_description_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('Can you write a listing description for this home?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_F_marketing_ideas_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('Do you have any marketing ideas for this property?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_F_marketing_idea_singular_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('Give me a marketing idea for this listing.');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    // =========================================================================
    // Case G — educational
    // =========================================================================

    public function test_case_G_what_is_a_cap_rate_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('What is a cap rate in real estate?');
        $this->assertSame('educational', $result['question_type']);
    }

    public function test_case_G_how_does_escrow_work_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('How does escrow work when buying a home?');
        $this->assertSame('educational', $result['question_type']);
    }

    public function test_case_G_explain_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('Explain what earnest money means.');
        $this->assertSame('educational', $result['question_type']);
    }

    public function test_case_G_define_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('Define contingency in a real estate contract.');
        $this->assertSame('educational', $result['question_type']);
    }

    public function test_case_G_what_is_earnest_money_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('What is earnest money in a real estate transaction?');
        $this->assertSame('educational', $result['question_type']);
    }

    public function test_case_G_how_does_the_appraisal_process_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('How does the appraisal process work?');
        $this->assertSame('educational', $result['question_type']);
    }

    // =========================================================================
    // Case H — prohibited
    // =========================================================================

    public function test_case_H_school_district_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('What is the school district rating for this area?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_best_schools_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('Which neighborhood has the best schools?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_crime_rate_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('What is the crime rate in this neighborhood?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_is_it_safe_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('Is it safe to live in this neighborhood?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_demographics_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('What are the demographics of this neighborhood?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_good_for_kids_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('Is this neighborhood good for kids?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_ethnicity_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('What is the ethnicity breakdown in this area?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_religion_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('Are there many religion centers near this property?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_race_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('What race of people live in this neighborhood?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_discrimination_classifies_as_prohibited(): void
    {
        $result = $this->makeService()->classify('Is there any discrimination in this area?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    public function test_case_H_prohibited_confidence_is_1_0(): void
    {
        $result = $this->makeService()->classify('What is the crime rate in this neighborhood?');
        $this->assertSame('prohibited', $result['question_type']);
        $this->assertSame(1.0, $result['confidence'], 'prohibited must always have confidence of 1.0');
    }

    public function test_case_H_prohibited_fires_first_over_other_types(): void
    {
        $result = $this->makeService()->classify('What race of buyers are typically interested in this style of home?');
        $this->assertSame('prohibited', $result['question_type'],
            'prohibited must take precedence over any other type when both keywords appear');
    }

    public function test_case_H_prohibited_fires_first_over_educational(): void
    {
        $result = $this->makeService()->classify('What is the religion of people in this neighborhood?');
        $this->assertSame('prohibited', $result['question_type'],
            'prohibited must take precedence over educational when both keywords appear');
    }

    // =========================================================================
    // Case I — unsupported fallback
    // =========================================================================

    public function test_case_I_unrecognised_question_returns_unsupported(): void
    {
        $result = $this->makeService()->classify('What is the meaning of life?');
        $this->assertSame('unsupported', $result['question_type']);
    }

    public function test_case_I_gibberish_returns_unsupported(): void
    {
        $result = $this->makeService()->classify('asdf qwerty zxcv1234');
        $this->assertSame('unsupported', $result['question_type']);
    }

    public function test_case_I_empty_string_returns_unsupported_with_zero_confidence(): void
    {
        $result = $this->makeService()->classify('');
        $this->assertSame('unsupported', $result['question_type']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_case_I_whitespace_only_returns_unsupported_with_zero_confidence(): void
    {
        $result = $this->makeService()->classify('   ');
        $this->assertSame('unsupported', $result['question_type']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_case_I_unsupported_confidence_is_0_0(): void
    {
        $result = $this->makeService()->classify('zxyq987 totally unrecognised gibberish question here');
        $this->assertSame(0.0, $result['confidence']);
    }

    // =========================================================================
    // Case J — Return shape: all three keys, confidence float in [0, 1]
    // =========================================================================

    public function test_case_J_result_contains_exactly_three_keys(): void
    {
        $result = $this->makeService()->classify('What makes this property stand out?');
        $this->assertCount(3, $result, 'classify() must return exactly three keys: question_type, confidence, reason');
    }

    public function test_case_J_all_three_keys_present_for_matched_type(): void
    {
        $result = $this->makeService()->classify('What makes this property stand out?');
        foreach (self::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "classify() result missing required key '{$key}'");
        }
    }

    public function test_case_J_all_three_keys_present_for_unsupported(): void
    {
        $result = $this->makeService()->classify('abcxyz no keyword match at all nope');
        foreach (self::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "unsupported result missing key '{$key}'");
        }
    }

    public function test_case_J_confidence_is_a_float(): void
    {
        $result = $this->makeService()->classify('What makes this property stand out?');
        $this->assertIsFloat($result['confidence']);
    }

    public function test_case_J_confidence_is_between_zero_and_one_for_all_types(): void
    {
        $service = $this->makeService();
        $questions = [
            'What makes this property stand out?',
            'Who would this appeal to?',
            'What is the match score?',
            'What is missing from this listing?',
            'How to market this home?',
            'What is a cap rate?',
            'Is it safe in this neighborhood?',
            'Is this a good match for a buyer?',
            'Completely unrelated question.',
        ];

        foreach ($questions as $question) {
            $result = $service->classify($question);
            $this->assertGreaterThanOrEqual(0.0, $result['confidence'], "Confidence < 0 for: {$question}");
            $this->assertLessThanOrEqual(1.0, $result['confidence'], "Confidence > 1 for: {$question}");
        }
    }

    public function test_case_J_reason_is_a_non_empty_string_for_all_outcomes(): void
    {
        $service = $this->makeService();
        foreach (['What makes this property stand out?', 'Who would this appeal to?', 'Random unmatched question.'] as $q) {
            $result = $service->classify($q);
            $this->assertIsString($result['reason'], "reason must be a string for: {$q}");
            $this->assertNotEmpty($result['reason'], "reason must not be empty for: {$q}");
        }
    }

    public function test_case_J_classification_is_case_insensitive(): void
    {
        $service = $this->makeService();
        $this->assertSame('property_standout', $service->classify('what makes this property stand out?')['question_type']);
        $this->assertSame('property_standout', $service->classify('WHAT MAKES THIS PROPERTY STAND OUT?')['question_type']);
        $this->assertSame('property_standout', $service->classify('What Makes This Property Stand Out?')['question_type']);
    }

    public function test_case_J_prohibited_is_case_insensitive(): void
    {
        $result = $this->makeService()->classify('WHAT IS THE CRIME RATE HERE?');
        $this->assertSame('prohibited', $result['question_type']);
    }

    // =========================================================================
    // Case K — Governance static grep: no OpenAI, Http::, or write calls
    //          (comment lines stripped before scan)
    // =========================================================================

    public function test_case_K_service_file_exists(): void
    {
        $this->assertFileExists(
            $this->serviceFilePath(),
            'AskAiQuestionClassifierService.php does not exist at expected path'
        );
    }

    public function test_case_K_service_file_contains_no_openai_calls(): void
    {
        $codeLines = $this->loadCodeLines();

        foreach (['use OpenAI\\', 'use OpenAi\\', 'use GuzzleHttp\\', 'OpenAI::', 'ChatGPT::'] as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Classifier service must not import or call '{$term}'"
            );
        }
    }

    public function test_case_K_service_file_contains_no_http_calls(): void
    {
        $codeLines = $this->loadCodeLines();

        foreach (['Http::', '\\Http', 'curl_exec', 'file_get_contents(\'http', 'file_get_contents("http'] as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Classifier service must not contain HTTP call '{$term}'"
            );
        }
    }

    public function test_case_K_service_file_contains_no_write_calls(): void
    {
        $codeLines = $this->loadCodeLines();

        foreach (['->save(', '->create(', '->update(', '->delete(', '->insert(', 'DB::statement(', 'DB::insert(', 'DB::update(', 'DB::delete('] as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Classifier service must not contain write call '{$term}'"
            );
        }
    }
}
