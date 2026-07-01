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
        //
        // IMPORTANT: property_type here uses the EXACT values the role's <select> stores —
        // Seller/Buyer store SHORT forms (Income, Commercial, Business, Vacant Land);
        // Landlord/Tenant store LONG forms (Residential Property, Commercial Property).
        // Driving the blade with the real stored values (not the long-form gating keys)
        // is what guards against the select-vs-gating mismatch regression: before the
        // config/property_types.php alias map + blade normalization, the short forms fell
        // back to universal+residential and these non-residential assertions would fail.
        return [
            'Seller · Residential' => ['seller', 'Residential',
                ['How old is the roof', 'What is included in the sale', 'guests or visitors compliment'],
                ['current lease terms and escalations', 'ceiling height', 'Why is the business being sold', 'soil, perc, or topography']],
            'Seller · Income' => ['seller', 'Income',
                ['current lease terms and escalations', 'What is included in the sale', 'increase income here'],
                ['How old is the roof', 'Why is the business being sold', 'soil, perc, or topography', 'guests or visitors compliment']],
            'Seller · Commercial' => ['seller', 'Commercial',
                ['What are the building systems', 'variances, special-use permits', 'redevelopment, expansion, or change-of-use', 'clear height to the lowest obstruction'],
                ['How old is the roof', 'Why is the business being sold', 'guests or visitors compliment']],
            'Seller · Business' => ['seller', 'Business',
                ['Why is the business being sold', 'How concentrated is the customer base', 'keeps customers coming back'],
                ['How old is the roof', 'ceiling height', 'soil, perc, or topography', 'guests or visitors compliment']],
            'Seller · Vacant Land' => ['seller', 'Vacant Land',
                ['soil, perc, or topography', 'prior use', 'utilities are already available'],
                ['How old is the roof', 'Why is the business being sold', 'ceiling height', 'guests or visitors compliment']],

            'Buyer · Residential' => ['buyer', 'Residential',
                ['architectural style', 'driving the buyer', 'willing to compromise'],
                ['minimum occupancy', 'minimum revenue', 'intended use for the land']],
            'Buyer · Income' => ['buyer', 'Income',
                ['minimum in-place occupancy', 'driving the buyer'],
                ['architectural style', 'minimum revenue', 'intended use for the land']],
            'Buyer · Commercial' => ['buyer', 'Commercial',
                ['driving the buyer'],
                ['architectural style', 'minimum revenue', 'intended use for the land']],
            'Buyer · Business' => ['buyer', 'Business',
                ['minimum revenue', 'driving the buyer'],
                ['architectural style', 'minimum occupancy', 'intended use for the land']],
            'Buyer · Vacant Land' => ['buyer', 'Vacant Land',
                ['intended use for the land', 'driving the buyer'],
                ['architectural style', 'minimum revenue']],

            'Landlord · Residential' => ['landlord', 'Residential Property',
                ['furnished, unfurnished, or negotiable', 'How are maintenance requests handled', 'self-managed or professionally managed'],
                ['loading dock or freight elevator', 'electrical capacity', 'parking ratio']],
            'Landlord · Commercial' => ['landlord', 'Commercial Property',
                ['loading dock or freight elevator', 'How are maintenance requests handled', 'CAM / operating expenses', 'parking ratio'],
                ['in-unit or shared laundry', "What's the noise level", 'self-managed or professionally managed']],

            'Tenant · Residential' => ['tenant', 'Residential Property',
                ['source and stability of the applicant', 'driving the applicant', 'work remotely, on-site, hybrid'],
                ['foot traffic', 'special equipment or power']],
            'Tenant · Commercial' => ['tenant', 'Commercial Property',
                ['foot traffic', 'driving the applicant', 'build-out, layout, or improvements'],
                ['source and stability of the applicant', 'furnished or unfurnished', 'work remotely, on-site, hybrid']],
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

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function emptyPropertyTypeMatrix(): array
    {
        // [user_type, a universal question that MUST render, a property-type-specific
        //  question that must be ABSENT until a property type is selected]
        return [
            'seller'   => ['seller',   'Why is the owner selling the property', 'How old is the roof'],
            'buyer'    => ['buyer',    'driving the buyer',                     'architectural style'],
            'landlord' => ['landlord', 'How are maintenance requests handled',  'furnished, unfurnished, or negotiable'],
            'tenant'   => ['tenant',   'driving the applicant',                 'source and stability of the applicant'],
        ];
    }

    /**
     * Property-type behavior: before a property type is chosen, the tab shows ONLY the
     * universal questions — no property-type interview is revealed prematurely. The blade
     * resolves an empty property_type to ['universal'] only (not the residential fallback).
     *
     * @dataProvider emptyPropertyTypeMatrix
     */
    public function test_unselected_property_type_renders_universal_only(
        string $userType,
        string $universalNeedle,
        string $propertyTypeNeedle
    ): void {
        $html = $this->render($userType, '');

        $this->assertStringContainsString('Common Questions', $html,
            "$userType with no property type must still render its universal Common Questions.");
        $this->assertStringContainsString($universalNeedle, $html,
            "$userType with no property type must render universal questions.");
        $this->assertStringNotContainsString($propertyTypeNeedle, $html,
            "$userType with no property type must NOT reveal property-type-specific questions.");
    }

    /**
     * Regression guard for the select-vs-gating mismatch (docs/ask-ai-question-catalog §6.0).
     * The Seller/Buyer property_type <select> stores short values; the gating maps are keyed
     * by long values. The config/property_types.php alias map + blade normalization must make
     * the short value resolve to the intended non-residential group. Before the fix, each of
     * these short values fell back to universal+residential.
     *
     * @dataProvider shortFormMatrix
     */
    public function test_short_form_property_types_resolve_to_their_intended_group(
        string $userType,
        string $shortValue,
        string $groupOnlyNeedle,
        string $residentialLeakageNeedle
    ): void {
        $html = $this->render($userType, $shortValue);

        $this->assertStringContainsString($groupOnlyNeedle, $html,
            "$userType short value '$shortValue' must resolve to its own KB group (not fall back to residential).");
        $this->assertStringNotContainsString($residentialLeakageNeedle, $html,
            "$userType short value '$shortValue' must NOT fall back to the residential group.");
    }

    /** @return array<string, array{0:string,1:string,2:string,3:string}> */
    public static function shortFormMatrix(): array
    {
        return [
            // [user_type, short select value, needle unique to the intended group, residential-only needle that must be absent]
            'Seller Income'     => ['seller', 'Income',     'current lease terms and escalations', 'How old is the roof'],
            'Seller Commercial' => ['seller', 'Commercial', 'variances, special-use permits',      'How old is the roof'],
            'Seller Business'   => ['seller', 'Business',   'How concentrated is the customer base', 'How old is the roof'],
            'Buyer Income'      => ['buyer',  'Income',     'minimum in-place occupancy',          'architectural style'],
            'Buyer Business'    => ['buyer',  'Business',   'minimum revenue',                     'architectural style'],
        ];
    }

    /**
     * The alias map must be the single source of truth and cover exactly the Seller/Buyer
     * short forms that differ from the gating keys (Vacant Land is identical in both).
     */
    public function test_gating_alias_map_covers_seller_buyer_short_forms(): void
    {
        $aliases = config('property_types.ai_faq_gating_aliases');

        $this->assertSame('Residential Property', $aliases['Residential']);
        $this->assertSame('Income Property', $aliases['Income']);
        $this->assertSame('Commercial Property', $aliases['Commercial']);
        $this->assertSame('Business Opportunity', $aliases['Business']);
        $this->assertArrayNotHasKey('Vacant Land', $aliases,
            'Vacant Land is identical in both vocabularies and must not need an alias.');
    }
}
