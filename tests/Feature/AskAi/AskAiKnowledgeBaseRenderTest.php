<?php

namespace Tests\Feature\AskAi;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Phase B/C render verification — the Listing AI Knowledge Base input tab must render the
 * correct gated question set for every user type × property type, organised into
 * "Common Questions" and "AI Insights", with NO cross-property-type leakage.
 *
 * Renders resources/views/livewire/offer-listing/shared/ai-questions-input.blade.php
 * directly (it is a plain Blade partial, not a Livewire runtime component).
 */
class AskAiKnowledgeBaseRenderTest extends TestCase
{
    private function render(string $userType, string $propertyType): string
    {
        return View::make('livewire.offer-listing.shared.ai-questions-input', [
            'user_type'     => $userType,
            'property_type' => $propertyType,
            'listing_ai_faq' => [],
        ])->render();
    }

    /** @return array<string, array{0:string,1:string,2:string[],3:string[]}> */
    public static function matrix(): array
    {
        // [user_type, property_type, must-contain labels, must-NOT-contain labels]
        return [
            'Seller · Residential' => ['seller', 'Residential Property',
                ['How old is the roof', 'What is included in the sale'],
                ['current lease terms and escalations', 'clear/ceiling height', 'Why is the business being sold', 'soil, perc, or topography']],
            'Seller · Income' => ['seller', 'Income Property',
                ['current lease terms and escalations', 'What is included in the sale'],
                ['How old is the roof', 'Why is the business being sold', 'soil, perc, or topography']],
            'Seller · Commercial' => ['seller', 'Commercial Property',
                ['What is the zoning, and what uses does it permit', 'What is the clear/ceiling height'],
                ['How old is the roof', 'Why is the business being sold']],
            'Seller · Business' => ['seller', 'Business Opportunity',
                ['Why is the business being sold', 'How concentrated is the customer base'],
                ['How old is the roof', 'clear/ceiling height', 'soil, perc, or topography']],
            'Seller · Vacant Land' => ['seller', 'Vacant Land',
                ['soil, perc, or topography', 'prior use'],
                ['How old is the roof', 'Why is the business being sold', 'clear/ceiling height']],

            'Buyer · Residential' => ['buyer', 'Residential Property',
                ['architectural style', 'driving the buyer'],
                ['minimum occupancy', 'minimum revenue', 'intended use for the land']],
            'Buyer · Income' => ['buyer', 'Income Property',
                ['minimum occupancy', 'driving the buyer'],
                ['architectural style', 'minimum revenue', 'intended use for the land']],
            'Buyer · Business' => ['buyer', 'Business Opportunity',
                ['minimum revenue', 'driving the buyer'],
                ['architectural style', 'minimum occupancy', 'intended use for the land']],
            'Buyer · Vacant Land' => ['buyer', 'Vacant Land',
                ['intended use for the land', 'driving the buyer'],
                ['architectural style', 'minimum revenue']],

            'Landlord · Residential' => ['landlord', 'Residential Property',
                ['in-unit or shared laundry', 'How are maintenance requests handled'],
                ['loading dock or freight elevator', 'electrical capacity']],
            'Landlord · Commercial' => ['landlord', 'Commercial Property',
                ['loading dock or freight elevator', 'How are maintenance requests handled'],
                ['in-unit or shared laundry', "What's the noise level"]],

            'Tenant · Residential' => ['tenant', 'Residential Property',
                ['source and stability of the applicant', 'driving the applicant'],
                ['foot traffic', 'special equipment or power']],
            'Tenant · Commercial' => ['tenant', 'Commercial Property',
                ['foot traffic', 'driving the applicant'],
                ['source and stability of the applicant', 'furnished or unfurnished']],
        ];
    }

    /**
     * @dataProvider matrix
     */
    public function test_knowledge_base_renders_correct_gated_questions(string $userType, string $propertyType, array $mustContain, array $mustNotContain): void
    {
        $html = $this->render($userType, $propertyType);

        $this->assertStringContainsString('Common Questions', $html, "$userType/$propertyType missing Common Questions section");
        $this->assertStringContainsString('AI Insights', $html, "$userType/$propertyType missing AI Insights section");

        foreach ($mustContain as $needle) {
            $this->assertStringContainsString($needle, $html, "$userType/$propertyType should contain: $needle");
        }
        foreach ($mustNotContain as $needle) {
            $this->assertStringNotContainsString($needle, $html, "$userType/$propertyType must NOT contain (leakage): $needle");
        }
    }

    public function test_persistent_disclaimer_renders_on_the_tab(): void
    {
        $html = $this->render('seller', 'Residential Property');
        $this->assertStringContainsString('not legal, financial, tax', $html,
            'The persistent educational disclaimer must render on the KB tab.');
    }
}
