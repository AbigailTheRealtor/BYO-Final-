<?php

namespace Tests\Feature\AskAi;

use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Batch 4 in the OWNER-FACING knowledge-base form.
 *
 * Two additions, and both are disclosure rather than control: a per-question marker on the
 * questions whose answers are eligible for public restatement, and one listing-level
 * acknowledgement the owner must tick before any of them is.
 *
 * Renders the shared Blade partial directly, as AskAiKnowledgeBaseRenderTest does — it is a
 * plain partial, not a Livewire runtime component.
 */
class PublicKnowledgeBaseBatch4FormTest extends TestCase
{
    private const NOTICE = 'May appear in the public Ask AI section when answered.';
    private const ACK    = 'Selected answers from your AI Knowledge Base may appear publicly';

    private function render(string $userType, string $propertyType): string
    {
        return View::make('livewire.offer-listing.shared.ai-questions-input', [
            'user_type'      => $userType,
            'property_type'  => $propertyType,
            'listing_ai_faq' => [],
        ])->render();
    }

    /* ================================================================== */

    public function test_seller_and_landlord_forms_carry_the_acknowledgement_control(): void
    {
        foreach ([['seller', 'Residential'], ['landlord', 'Residential Property']] as [$role, $type]) {
            $html = html_entity_decode($this->render($role, $type), ENT_QUOTES);

            $this->assertStringContainsString(self::ACK, $html, "{$role}: acknowledgement copy missing.");
            $this->assertStringContainsString('wire:model.defer="listing_ai_faq_public_ack"', $html);
            $this->assertStringContainsString('data-ai-faq-public-ack="1"', $html);

            // Exactly one — a second control would be a second answer to the same question.
            $this->assertSame(1, substr_count($html, 'data-ai-faq-public-ack="1"'), "{$role}: not exactly one control.");

            // The promise made to the owner beside the box.
            $this->assertStringContainsString('Fair Housing-restricted', $html);
            $this->assertStringContainsString('personally identifying information will not be shown', $html);
        }
    }

    public function test_buyer_and_tenant_forms_have_no_acknowledgement_and_no_public_markers(): void
    {
        foreach ([['buyer', 'Residential'], ['tenant', 'Residential Property']] as [$role, $type]) {
            $html = html_entity_decode($this->render($role, $type), ENT_QUOTES);

            // No control, and critically no wire:model — these components do not declare the
            // property, and binding to a property that does not exist is a runtime error.
            $this->assertStringNotContainsString(self::ACK, $html, "{$role}: must have no acknowledgement.");
            $this->assertStringNotContainsString('listing_ai_faq_public_ack', $html);
            $this->assertStringNotContainsString('data-ai-faq-public-ack', $html);

            // And no question is ever marked publicly eligible.
            $this->assertStringNotContainsString(self::NOTICE, $html, "{$role}: must have no public markers.");
            $this->assertStringNotContainsString('data-ai-faq-public-safe', $html);
        }
    }

    public function test_the_public_marker_appears_on_allowlisted_questions_only(): void
    {
        $cases = [
            ['seller', 'Residential', 'roof_age_and_condition'],
            ['seller', 'Commercial', 'commercial_restroom_count'],
            ['seller', 'Vacant Land', 'land_survey_available'],
            ['landlord', 'Residential Property', 'notice_to_vacate_required'],
            ['landlord', 'Commercial Property', 'commercial_parking_ratio'],
        ];

        foreach ($cases as [$role, $type, $key]) {
            $html = html_entity_decode($this->render($role, $type), ENT_QUOTES);

            $this->assertTrue(
                AskAiPublicPropertyQuestionService::isPublicSafeKbKey($role, $key),
                "Fixture error: {$role}/{$key} is not allowlisted."
            );
            $this->assertStringContainsString(
                'data-ai-faq-public-safe="' . $key . '"',
                $html,
                "{$role}/{$type}: {$key} should be marked publicly eligible."
            );
        }
    }

    public function test_every_marked_question_is_actually_on_the_allowlist(): void
    {
        // The direction that matters for trust: the form must never tell an owner an answer
        // may be published when the read path would refuse it.
        $matrix = [
            ['seller', ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land']],
            ['landlord', ['Residential Property', 'Commercial Property']],
        ];

        foreach ($matrix as [$role, $types]) {
            $allowed = AskAiPublicPropertyQuestionService::publicSafeKbKeysForRole($role);

            foreach ($types as $type) {
                $html    = html_entity_decode($this->render($role, $type), ENT_QUOTES);
                $matches = [];
                preg_match_all('/data-ai-faq-public-safe="([^"]+)"/', $html, $matches);

                $this->assertNotEmpty($matches[1], "{$role}/{$type}: expected at least one marked question.");

                foreach ($matches[1] as $key) {
                    $this->assertContains(
                        $key,
                        $allowed,
                        "{$role}/{$type}: '{$key}' is marked public-safe in the form but is not allowlisted."
                    );
                }
            }
        }
    }

    public function test_a_non_allowlisted_question_is_not_marked(): void
    {
        $html = html_entity_decode($this->render('seller', 'Residential'), ENT_QUOTES);

        // A question that exists on this form but is deliberately excluded from the public set.
        foreach (['seller_motivation_timeline', 'known_issues_disclosure'] as $key) {
            $this->assertStringNotContainsString('data-ai-faq-public-safe="' . $key . '"', $html);
        }
    }

    public function test_the_marker_stores_nothing_and_offers_no_toggle(): void
    {
        $html = html_entity_decode($this->render('seller', 'Residential'), ENT_QUOTES);

        // The per-question marker is a disclosure line, not a control. The ONLY checkbox this
        // partial adds is the single listing-level acknowledgement.
        $this->assertSame(
            1,
            substr_count($html, 'type="checkbox"'),
            'The knowledge-base form should add exactly one checkbox — the acknowledgement.'
        );
        $this->assertStringNotContainsString('listing_ai_faq_public.', $html);
        $this->assertStringNotContainsString('public_safe"', $html, 'No per-question public_safe input may be bound.');
    }
}
