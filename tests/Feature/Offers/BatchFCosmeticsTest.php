<?php

namespace Tests\Feature\Offers;

use App\Helpers\PropertyTypePlaceholderHelper;
use Tests\TestCase;

/**
 * Batch F-1 — UI/UX cosmetic consistency (issues #23, #24, #25, #26, #27, #30, #31).
 *
 * Cosmetic-only: placeholder wording/capitalization, tooltip format, and the two
 * helper-driven title maps. No functional logic changes. Following the Batch A / D / E
 * convention, Blade-facing items assert against source strings (the markup is
 * conditionally rendered / requires heavy fixtures to mount); the helper items assert
 * the real PropertyTypePlaceholderHelper::placeholder() output.
 *
 * #28 / #29 (map-input.blade.php) are intentionally NOT covered here — deferred to
 * Batch F-2 because that file overlaps active 9D Location DNA work.
 *
 * Every Batch F-1 item stays "CODE COMPLETE — HUMAN BROWSER QA REQUIRED" until a human
 * browser QA pass visually confirms the strings across the affected Create + Hire flows.
 */
class BatchFCosmeticsTest extends TestCase
{
    private const AGENT_CREDENTIALS   = 'resources/views/livewire/partials/agent-credentials.blade.php';
    private const HIRE_SELLER_PREFS   = 'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/property-preferences.blade.php';
    private const HIRE_LANDLORD_PREFS = 'resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/property-preferences.blade.php';
    private const TENANT_DETAILS      = 'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php';
    private const SELLER_PREFS        = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php';
    private const LANDLORD_PREFS      = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php';
    private const SELLER_TERMS        = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php';

    private function source(string $relativePath): string
    {
        $full = base_path($relativePath);
        $this->assertFileExists($full, "Expected file missing: {$relativePath}");

        return (string) file_get_contents($full);
    }

    /** #26 — Agent Credentials: remove the "(e.g., …)" example text from the 3 placeholders (one shared partial → all 19 sites). */
    public function test_issue_26_agent_credentials_placeholders_have_no_examples(): void
    {
        $src = $this->source(self::AGENT_CREDENTIALS);

        $this->assertStringContainsString('placeholder="Enter Phone Number"', $src);
        $this->assertStringContainsString('placeholder="Enter License Number"', $src);
        $this->assertStringContainsString('placeholder="Enter NAR Member ID"', $src);

        $this->assertStringNotContainsString('Enter Phone Number (e.g.', $src);
        $this->assertStringNotContainsString('Enter License Number (e.g.', $src);
        $this->assertStringNotContainsString('Enter NAR Member ID (e.g.', $src);
    }

    /** #23 — Hire address tooltips: "<br>" replaced with a space to match the Create continuous-sentence format. */
    public function test_issue_23_hire_address_tooltips_use_a_space_not_br(): void
    {
        foreach ([self::HIRE_SELLER_PREFS, self::HIRE_LANDLORD_PREFS] as $path) {
            $src = $this->source($path);

            // No address tooltip should still break with <br>.
            $this->assertStringNotContainsString('is located.<br>', $src, "Stale <br> in address tooltip: {$path}");

            // Space-joined sentence form is present for city / state / county.
            $this->assertStringContainsString('is located. Selecting a city will automatically populate the county and state.', $src);
            $this->assertStringContainsString('is located. This will be automatically populated when a city or county is selected.', $src);
            $this->assertStringContainsString('is located. This may be automatically populated when a city is selected.', $src);
        }
    }

    /** #24 — Appliance placeholders normalized to sentence-style examples. */
    public function test_issue_24_appliance_placeholders_are_sentence_case(): void
    {
        $landlord = $this->source(self::HIRE_LANDLORD_PREFS);
        $this->assertStringContainsString('Enter appliances (e.g., Air fryer oven, Induction cooktop, Double oven)', $landlord);
        $this->assertStringNotContainsString('Air Fryer Oven', $landlord);
        $this->assertStringNotContainsString('Induction Cooktop', $landlord);
        $this->assertStringNotContainsString('Double Oven', $landlord);

        $tenant = $this->source(self::TENANT_DETAILS);
        $this->assertStringContainsString('Enter other appliances (e.g., Warming drawer)', $tenant);
        $this->assertStringNotContainsString('e.g., warming drawer', $tenant);
    }

    /** #25 — Water Frontage / Waterfront Feet placeholders prepend the field title (Create Seller + Create Landlord only). */
    public function test_issue_25_water_frontage_placeholders_include_field_title(): void
    {
        foreach ([self::SELLER_PREFS, self::LANDLORD_PREFS] as $path) {
            $src = $this->source($path);

            $this->assertStringContainsString('placeholder="Enter Water Frontage (e.g., Intracoastal Waterway, Gulf/Ocean, Lake)"', $src);
            $this->assertStringContainsString('placeholder="Enter Waterfront Feet (e.g., 75)"', $src);

            // The old title-less placeholders are gone.
            $this->assertStringNotContainsString('placeholder="e.g., Intracoastal Waterway, Gulf/Ocean, Lake"', $src);
            $this->assertStringNotContainsString('placeholder="e.g., 75"', $src);
        }
    }

    /** #27 — Additional HOA / Association Notes placeholder: capitalize each comma-separated example (Pending / New). */
    public function test_issue_27_hoa_notes_placeholder_capitalization(): void
    {
        $src = $this->source(self::SELLER_TERMS);

        $this->assertStringContainsString('Pending special assessment, New rules effective Jan 2026', $src);
        $this->assertStringNotContainsString('pending special assessment', $src);
        $this->assertStringNotContainsString('new rules effective', $src);
    }

    /** #30 — Hire "Additional Details" placeholder lowercased across all four roles. */
    public function test_issue_30_hire_additional_details_placeholder_is_lowercase(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $result = PropertyTypePlaceholderHelper::placeholder($role, 'hire', 'Residential');
            $this->assertStringStartsWith('Enter additional details (e.g., ', $result, "hire/{$role}");
            $this->assertStringNotContainsString('Enter Additional Details', $result, "hire/{$role}");
        }
    }

    /** #31 — Create Description placeholders per role (Tenant = "tenant description", owner decision). */
    public function test_issue_31_create_description_placeholders_per_role(): void
    {
        $expected = [
            'seller'   => 'Enter property description (e.g., ',
            'buyer'    => 'Enter buyer description (e.g., ',
            'landlord' => 'Enter rental description (e.g., ',
            'tenant'   => 'Enter tenant description (e.g., ',
        ];

        foreach ($expected as $role => $prefix) {
            $result = PropertyTypePlaceholderHelper::placeholder($role, 'create', 'Residential');
            $this->assertStringStartsWith($prefix, $result, "create/{$role}");
        }

        // No title-cased leftovers.
        foreach (['Property Description', 'Buyer Description', 'Rental Description', 'Tenant Description'] as $titleCase) {
            foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
                $this->assertStringNotContainsString(
                    "Enter {$titleCase}",
                    PropertyTypePlaceholderHelper::placeholder($role, 'create', 'Residential')
                );
            }
        }
    }
}
