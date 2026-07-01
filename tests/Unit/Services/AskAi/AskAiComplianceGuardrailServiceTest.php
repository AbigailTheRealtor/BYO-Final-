<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiComplianceGuardrailService;
use Tests\TestCase;

/**
 * Phase A guardrail tests — output sanitization (C-A), superlative restriction (C-I),
 * advice/steering/demographic neutralization, and the educational disclaimer (C-J).
 */
class AskAiComplianceGuardrailServiceTest extends TestCase
{
    private AskAiComplianceGuardrailService $guardrail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guardrail = new AskAiComplianceGuardrailService();
    }

    public function test_clean_factual_answer_is_left_unchanged(): void
    {
        $answer = 'The roof is 8 years old and was replaced in 2019. The home has central air conditioning and a two-car garage.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertSame($answer, $result['text']);
        $this->assertFalse($result['modified']);
        $this->assertFalse($result['withheld']);
        $this->assertSame([], $result['categories']);
    }

    public function test_demographic_steering_sentence_is_dropped_but_factual_kept(): void
    {
        $answer = 'This home is perfect for families with young children. The lot is half an acre and fully fenced.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringNotContainsString('perfect for families', $result['text']);
        $this->assertStringContainsString('half an acre', $result['text']);
        $this->assertTrue($result['modified']);
        $this->assertContains('demographic', $result['categories']);
    }

    public function test_neighborhood_quality_steering_is_dropped(): void
    {
        $answer = 'It is in the safest neighborhood in the city. The property includes a covered patio.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringNotContainsString('safest neighborhood', $result['text']);
        $this->assertStringContainsString('covered patio', $result['text']);
        $this->assertContains('steering', $result['categories']);
    }

    public function test_school_quality_steering_is_dropped(): void
    {
        $answer = 'The home is served by the best schools in the county. It is assigned to the Lincoln school district.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringNotContainsString('best schools', $result['text']);
        $this->assertStringContainsString('Lincoln school district', $result['text']);
    }

    public function test_negotiation_and_offer_advice_is_dropped(): void
    {
        $answer = 'You should offer 10% below asking to start. The seller disclosed the roof was replaced in 2020.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringNotContainsString('offer 10%', $result['text']);
        $this->assertStringContainsString('roof was replaced', $result['text']);
        $this->assertContains('advice', $result['categories']);
    }

    public function test_leverage_analysis_is_dropped(): void
    {
        $answer = 'The seller is in a weak negotiating position because they are relocating. The lot is 0.3 acres.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringNotContainsString('negotiating position', $result['text']);
        $this->assertStringContainsString('0.3 acres', $result['text']);
    }

    public function test_investment_advice_is_dropped(): void
    {
        $answer = 'This is a strong investment with a great cap rate. The current NOI is $85,000 according to the listing.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringNotContainsString('strong investment', $result['text']);
        $this->assertStringContainsString('NOI is $85,000', $result['text']);
    }

    public function test_superlatives_are_removed_when_not_quoted(): void
    {
        $answer = 'This is the perfect home with the finest finishes available.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringNotContainsString('perfect', $result['text']);
        $this->assertStringNotContainsString('finest', $result['text']);
        $this->assertContains('superlative', $result['categories']);
    }

    public function test_superlatives_inside_quotes_are_preserved(): void
    {
        $answer = 'The listing describes the kitchen as "the perfect space for entertaining" per the seller.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringContainsString('"the perfect space for entertaining"', $result['text']);
    }

    public function test_protected_class_reference_is_dropped(): void
    {
        $answer = 'This area is popular with a particular religious community. The home has four bedrooms.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertStringContainsString('four bedrooms', $result['text']);
        $this->assertContains('protected_class', $result['categories']);
    }

    public function test_all_offending_content_returns_safe_fallback(): void
    {
        $answer = 'You should offer well below asking. This is the perfect home for families.';
        $result = $this->guardrail->sanitizeAnswer($answer);

        $this->assertTrue($result['withheld']);
        $this->assertSame(AskAiComplianceGuardrailService::WITHHELD_FALLBACK, $result['text']);
    }

    public function test_empty_answer_is_handled(): void
    {
        $result = $this->guardrail->sanitizeAnswer('   ');
        $this->assertFalse($result['withheld']);
        $this->assertFalse($result['modified']);
    }

    public function test_educational_disclaimer_is_available_and_idempotent(): void
    {
        $disclaimer = $this->guardrail->educationalDisclaimer();
        $this->assertStringContainsString('not legal, financial, tax', $disclaimer);

        $with = $this->guardrail->withEducationalDisclaimer(['Existing.']);
        $this->assertCount(2, $with);

        $twice = $this->guardrail->withEducationalDisclaimer($with);
        $this->assertCount(2, $twice, 'Disclaimer should not be duplicated.');
    }
}
