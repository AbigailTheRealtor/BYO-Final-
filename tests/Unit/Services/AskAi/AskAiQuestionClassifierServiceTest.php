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

    public function test_case_A_bidding_process_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('What is the bidding process for this property?');
        $this->assertSame('educational', $result['question_type']);
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

    // =========================================================================
    // Case L — New synonym / intent keyword expansions
    // =========================================================================

    // --- property_standout new keywords ---

    public function test_case_L_strengths_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What are the strengths of this listing?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_L_benefits_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What are the benefits of this property?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_L_good_about_this_listing_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What is good about this listing overall?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    public function test_case_L_what_is_good_about_classifies_as_property_standout(): void
    {
        $result = $this->makeService()->classify('What is good about this home compared to others?');
        $this->assertSame('property_standout', $result['question_type']);
    }

    // --- suited_audience new keywords ---

    public function test_case_L_who_is_this_good_for_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who is this good for?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_L_who_would_like_this_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who would like this property?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_L_best_suited_for_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who is this listing best suited for?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    public function test_case_L_who_would_enjoy_classifies_as_suited_audience(): void
    {
        $result = $this->makeService()->classify('Who would enjoy living here?');
        $this->assertSame('suited_audience', $result['question_type']);
    }

    // --- buyer_tenant_match new keywords ---

    public function test_case_L_does_this_tenant_have_pets_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Does this tenant have pets?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_L_rent_budget_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What is their rent budget?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_L_purchase_budget_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What is the buyer\'s purchase budget?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_L_lease_length_now_classifies_as_listing_facts(): void
    {
        // "lease length" questions are factual queries about the listing — migrated from
        // buyer_tenant_match to listing_facts. Retained buyer-criteria phrases
        // ('desired lease length', 'preferred lease length') remain in buyer_tenant_match.
        $result = $this->makeService()->classify('What is the lease length they are looking for?');
        $this->assertSame('listing_facts', $result['question_type'],
            '"what is the lease" phrase must now route to listing_facts');
    }

    public function test_case_L_is_lease_length_listed_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('Is lease length listed on this profile?');
        $this->assertSame('missing_data', $result['question_type'],
            '"is lease length listed" must route to missing_data, not buyer_tenant_match');
    }

    public function test_case_L_move_in_date_classifies_as_buyer_tenant_match(): void
    {
        // "What is their preferred move-in date?" uses the bare 'move-in date' keyword which
        // stays in buyer_tenant_match. Only specific factual-retrieval phrases like
        // "what is the move-in date" and "when is the move-in date" route to listing_facts.
        $result = $this->makeService()->classify('What is their preferred move-in date?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_L_amenities_required_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What are the amenities required by this tenant?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_L_bedrooms_now_classifies_as_listing_facts(): void
    {
        // 'bedrooms' was migrated from buyer_tenant_match to listing_facts so that
        // structural listing questions route to the factual data path.
        $result = $this->makeService()->classify('How many bedrooms does the buyer need?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_L_bathrooms_now_classifies_as_listing_facts(): void
    {
        // 'bathrooms' was migrated from buyer_tenant_match to listing_facts.
        $result = $this->makeService()->classify('How many bathrooms are they looking for?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_L_location_preference_now_classifies_as_listing_facts(): void
    {
        // 'location preference' has been added to listing_facts so that buyer/tenant
        // preferred-area questions route to the factual data path.
        $result = $this->makeService()->classify('What is their location preference?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_L_monthly_income_does_not_route_to_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What is the tenant\'s monthly income?');
        $this->assertNotSame('buyer_tenant_match', $result['question_type'],
            'Income is a compliance-sensitive screening field and must not auto-route as a match question');
    }

    public function test_case_L_credit_score_does_not_route_to_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What credit score does this buyer have?');
        $this->assertNotSame('buyer_tenant_match', $result['question_type'],
            'Credit score is a compliance-sensitive screening field and must not auto-route as a match question');
    }

    public function test_case_L_is_credit_score_listed_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('Is credit score listed for this applicant?');
        $this->assertSame('missing_data', $result['question_type'],
            '"is credit score listed" must route to missing_data, not buyer_tenant_match');
    }

    public function test_case_L_is_parking_listed_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('Is parking listed as a requirement?');
        $this->assertSame('missing_data', $result['question_type'],
            '"is parking listed" must route to missing_data, not buyer_tenant_match');
    }

    public function test_case_L_what_does_this_tenant_want_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What does this tenant want in a property?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_L_what_does_this_buyer_want_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What does this buyer want in a home?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    public function test_case_L_qualification_judgment_does_not_route_to_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Does this buyer qualify for this listing?');
        $this->assertNotSame('buyer_tenant_match', $result['question_type'],
            'Qualification-judgment prompts must not be routed to buyer_tenant_match');
    }

    public function test_case_L_tenant_qualification_judgment_does_not_route_to_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Is this tenant qualified?');
        $this->assertNotSame('buyer_tenant_match', $result['question_type'],
            'Qualification-judgment prompts must not be routed to buyer_tenant_match');
    }

    // --- missing_data new keywords ---

    public function test_case_L_how_complete_is_this_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('How complete is this listing?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_L_what_should_be_added_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('What should be added to this listing?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_L_do_we_know_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('Do we know anything about the deposit amount?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_L_is_there_information_about_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('Is there information about the move-in fees?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_L_is_income_listed_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('Is income listed for this applicant?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_L_is_pet_information_listed_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('Is pet information listed on this profile?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    public function test_case_L_is_budget_listed_classifies_as_missing_data(): void
    {
        $result = $this->makeService()->classify('Is budget listed anywhere in this listing?');
        $this->assertSame('missing_data', $result['question_type']);
    }

    // --- marketing_angles new keywords ---

    public function test_case_L_marketing_ideas_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('What marketing ideas do you have for this listing?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_L_listing_description_ideas_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('Any listing description ideas for this home?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    public function test_case_L_ad_ideas_classifies_as_marketing_angles(): void
    {
        $result = $this->makeService()->classify('Do you have any ad ideas for this property?');
        $this->assertSame('marketing_angles', $result['question_type']);
    }

    // --- educational new keywords ---

    public function test_case_L_auction_process_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('Can you explain the auction process?');
        $this->assertSame('educational', $result['question_type']);
    }

    public function test_case_L_bidding_process_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('How does the bidding process work?');
        $this->assertSame('educational', $result['question_type']);
    }

    public function test_case_L_platform_process_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('What is the platform process for submitting an offer?');
        $this->assertSame('educational', $result['question_type']);
    }

    public function test_case_L_how_does_this_platform_classifies_as_educational(): void
    {
        $result = $this->makeService()->classify('How does this platform handle offers?');
        $this->assertSame('educational', $result['question_type']);
    }

    // =========================================================================
    // Case M — listing_facts: keyword routing, confidence, and migration tests
    //
    // Covers: (1) listing_facts keyword set routes correctly,
    //         (2) keywords migrated from buyer_tenant_match now route to listing_facts,
    //         (3) non-migrated buyer_tenant_match keywords are unaffected,
    //         (4) confidence is 0.90.
    // =========================================================================

    // --- Core listing_facts questions ---

    public function test_case_M_how_many_bedrooms_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('How many bedrooms does this listing have?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_how_many_bathrooms_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('How many bathrooms does this property have?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_asking_price_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the asking price for this home?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_monthly_rent_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the monthly rent?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_rent_amount_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the rent amount for this unit?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_are_pets_allowed_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Are pets allowed in this property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_pet_policy_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the pet policy for this unit?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_hoa_fee_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the HOA fee for this property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_is_there_an_hoa_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is there an HOA for this home?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_is_there_a_pool_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is there a pool at this property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_parking_spaces_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('How many parking spaces does this unit have?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_appliances_included_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What appliances are included with this rental?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_utilities_included_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What utilities are included in the rent?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_showing_instructions_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What are the showing instructions for this listing?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_square_footage_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the square footage of this home?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_year_built_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('When was this home built?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_flood_zone_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is it in a flood zone?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_available_date_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the available date for this unit?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_smoking_policy_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the smoking policy for this rental?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_M_listing_facts_is_case_insensitive(): void
    {
        $service = $this->makeService();
        $this->assertSame('listing_facts', $service->classify('HOW MANY BEDROOMS?')['question_type']);
        $this->assertSame('listing_facts', $service->classify('What Is The Asking Price?')['question_type']);
    }

    // --- Keywords migrated out of buyer_tenant_match → now listing_facts ---

    public function test_case_M_bare_bedrooms_now_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('bedrooms');
        $this->assertSame('listing_facts', $result['question_type'],
            "'bedrooms' must route to listing_facts, not buyer_tenant_match');");
    }

    public function test_case_M_bare_bathrooms_now_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('bathrooms');
        $this->assertSame('listing_facts', $result['question_type'],
            "'bathrooms' must route to listing_facts, not buyer_tenant_match';");
    }

    public function test_case_M_lease_length_now_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the lease length for this property?');
        $this->assertSame('listing_facts', $result['question_type'],
            "'lease length' must route to listing_facts, not buyer_tenant_match');");
    }

    public function test_case_M_lease_term_now_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the lease term for this rental?');
        $this->assertSame('listing_facts', $result['question_type'],
            "'lease term' must route to listing_facts, not buyer_tenant_match');");
    }

    // --- Non-migrated buyer_tenant_match phrases must remain in buyer_tenant_match ---

    public function test_case_M_desired_lease_length_still_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What is the desired lease length for this buyer?');
        $this->assertSame('buyer_tenant_match', $result['question_type'],
            "'desired lease length' must remain in buyer_tenant_match');");
    }

    public function test_case_M_preferred_lease_length_still_classifies_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('What is the preferred lease length for this tenant?');
        $this->assertSame('buyer_tenant_match', $result['question_type'],
            "'preferred lease length' must remain in buyer_tenant_match');");
    }

    public function test_case_M_move_in_date_now_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the move-in date for this tenant?');
        $this->assertSame('listing_facts', $result['question_type'],
            "'move-in date' was migrated from buyer_tenant_match to listing_facts');");
    }

    public function test_case_M_buyer_match_phrases_still_classify_as_buyer_tenant_match(): void
    {
        $result = $this->makeService()->classify('Is this a good match for a buyer?');
        $this->assertSame('buyer_tenant_match', $result['question_type']);
    }

    // --- listing_facts confidence ---

    public function test_case_M_listing_facts_confidence_is_0_90(): void
    {
        $result = $this->makeService()->classify('How many bedrooms does this listing have?');
        $this->assertSame('listing_facts', $result['question_type']);
        $this->assertEqualsWithDelta(0.90, $result['confidence'], 0.001,
            "listing_facts confidence must be 0.90");
    }

    public function test_case_M_listing_facts_confidence_within_valid_range(): void
    {
        $result = $this->makeService()->classify('What is the asking price?');
        $this->assertGreaterThanOrEqual(0.0, $result['confidence']);
        $this->assertLessThanOrEqual(1.0, $result['confidence']);
    }

    // =========================================================================
    // Case N — Phase 1 QA defect remediation: garage, move-in, preferred area
    // =========================================================================

    // --- Garage keywords (listing_facts) ---

    public function test_case_N_garage_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Does this property have a garage?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_is_there_a_garage_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is there a garage?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_does_it_have_a_garage_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Does it have a garage?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_does_the_property_have_a_garage_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Does the property have a garage?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_attached_garage_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Does this home have an attached garage?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_detached_garage_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is there a detached garage on the property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_garage_parking_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is garage parking available with this unit?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_how_many_garage_spaces_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('How many garage spaces does this property have?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    // --- Move-in date / timeframe migrated to listing_facts ---

    public function test_case_N_move_in_date_hyphen_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('When is the move-in date for this unit?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_move_in_date_no_hyphen_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the move in date for this property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_move_in_timeframe_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the move-in timeframe for this listing?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_move_in_timeframe_no_hyphen_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Can you tell me the move in timeframe?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_move_in_schedule_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the move-in schedule for this rental?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_available_move_in_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('When is the available move-in for this unit?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    // --- Move-in timeframe synonyms (listing_facts) ---

    public function test_case_N_when_do_they_want_to_move_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('When do they want to move into the property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_when_can_they_move_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('When can they move?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_target_move_date_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the target move date for this tenant?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_desired_move_date_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the desired move date?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_move_in_timeline_hyphen_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the move-in timeline for this applicant?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_move_in_timeline_no_hyphen_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the move in timeline they are expecting?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    // --- Buyer preferred-area keywords (listing_facts) ---

    public function test_case_N_preferred_areas_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What are the preferred areas for this buyer?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_preferred_neighborhoods_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What preferred neighborhoods has the buyer listed?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_preferred_cities_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What are the preferred cities for this buyer?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_preferred_locations_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What preferred locations has the buyer specified?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_where_does_the_buyer_want_to_live_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Where does the buyer want to live?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_location_preferences_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What are the buyer\'s location preferences?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_N_location_preference_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is their location preference?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    // =========================================================================
    // Case O — Address and laundry keywords must route to listing_facts
    // =========================================================================

    // --- Property address keywords ---

    public function test_case_O_address_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the address of this property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_bare_address_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('address');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_property_address_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('property address');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_what_is_the_address_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the address?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_whats_the_address_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify("What's the address?");
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_where_is_this_property_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Where is this property located?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_where_is_the_property_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Where is the property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_property_location_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the property location?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_location_of_this_property_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Tell me the location of this property.');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_location_of_the_property_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('What is the location of the property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    // --- In-unit laundry keywords ---

    public function test_case_O_laundry_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is there laundry available?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_in_unit_laundry_hyphen_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is there in-unit laundry at this rental property?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_in_unit_laundry_no_hyphen_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Does the unit have in unit laundry?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_washer_dryer_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Is there a washer dryer in the unit?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_O_washer_and_dryer_classifies_as_listing_facts(): void
    {
        $result = $this->makeService()->classify('Does the property have a washer and dryer?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    // =========================================================================
    // Case P — Tax colloquial/grammatically-variant phrasings → listing_facts
    //
    // Regression guard for "what is the taxes" and related colloquial forms
    // that the classifier previously missed, returning 'unsupported'.
    // =========================================================================

    /**
     * @dataProvider taxColloquialPhrasesProvider
     */
    public function test_case_P_tax_colloquial_phrase_classifies_as_listing_facts(string $phrase): void
    {
        $result = $this->makeService()->classify($phrase);
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Colloquial tax phrase \"{$phrase}\" should classify as listing_facts, not unsupported."
        );
        $this->assertGreaterThanOrEqual(
            0.8,
            $result['confidence'],
            "Colloquial tax phrase \"{$phrase}\" should have confidence >= 0.8."
        );
    }

    public static function taxColloquialPhrasesProvider(): array
    {
        return [
            'what is the taxes'          => ['What is the taxes on this property?'],
            'what is the tax'            => ['What is the tax on this home?'],
            "what's the taxes"           => ["What's the taxes for this listing?"],
            "what's the tax"             => ["What's the tax on this property?"],
            'taxes on this property'     => ['What are taxes on this property?'],
            'tax on this property'       => ['How much is the tax on this property?'],
        ];
    }
}
