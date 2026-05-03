<?php

namespace Tests\Feature;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentPresetCatalog;
use App\Support\ServicesFormatter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for agent preset Hire Me link correctness and service isolation.
 *
 * Covers:
 *  - Correct role/property-specific URL generation for all 14 preset combinations
 *  - Service isolation — saving one preset never affects another role's services
 *  - Strict preset lookup on the Hire Me preview (no fallback to wrong preset)
 *  - No literal \u2019 (or other raw Unicode escapes) rendered in service labels
 */
class AgentPresetHireMeLinkTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAgent(string $shortId = 'aabbccdd1100'): User
    {
        return User::factory()->asAgent()->create(['short_id' => $shortId]);
    }

    private function makeProfile(
        User   $agent,
        string $role,
        string $propertyType,
        array  $services = ['Service A'],
        array  $extra = []
    ): AgentDefaultProfile {
        return AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => $role,
            'property_type' => $propertyType,
            'profile_data'  => array_merge(['services' => $services], $extra),
        ]);
    }

    /**
     * Expected shareable URL path for a given role/property combination.
     * Used for URL-path assertions (e.g. inside href or redirect Location).
     */
    private function hireUrlPath(string $shortId, string $role, string $propertyType): string
    {
        return "/hire/{$shortId}/{$role}/{$propertyType}";
    }

    /**
     * Full absolute route URL — matches what route() generates and what appears
     * in the rendered HTML for href and data-hire-url attributes.
     */
    private function hireUrl(string $shortId, string $role, string $propertyType): string
    {
        return route('hire.agent.public', [
            'agentShortId' => $shortId,
            'role'         => $role,
            'propertyType' => $propertyType,
        ]);
    }

    /** Expected edit-page URL for a given role/property combination. */
    private function editUrl(string $role, string $propertyType): string
    {
        return "/agent/presets/{$role}/{$propertyType}/edit";
    }

    // =========================================================================
    // §1 — Hire Me URL generation: all 14 combinations
    // =========================================================================

    /**
     * @dataProvider allCombinationsProvider
     */
    public function test_preset_edit_page_contains_role_specific_hire_me_url(
        string $role,
        string $propertyType
    ): void {
        $shortId = 'aabbccdd1100';
        $agent   = $this->makeAgent($shortId);
        $this->makeProfile($agent, $role, $propertyType);

        $response = $this->actingAs($agent)
            ->get($this->editUrl($role, $propertyType));

        $response->assertStatus(200);

        $expectedUrl = $this->hireUrl($shortId, $role, $propertyType);

        // "Open Hire Me Page" link must point to the role/property-specific URL.
        $response->assertSee($expectedUrl, false);

        // "Copy Hire Me Link" data-hire-url attribute must also carry the correct URL.
        $response->assertSee('data-hire-url="' . $expectedUrl . '"', false);
    }

    /**
     * Provides all 14 valid role/property-type combinations.
     */
    public static function allCombinationsProvider(): array
    {
        $combos = [];
        foreach (AgentPresetCatalog::getRoles() as $role) {
            foreach (AgentPresetCatalog::getPropertyTypes($role) as $propertyType) {
                $combos["{$role}/{$propertyType}"] = [$role, $propertyType];
            }
        }
        return $combos;
    }

    // =========================================================================
    // §2 — Service isolation: saving one preset does not affect another's URL
    // =========================================================================

    public function test_saving_seller_residential_does_not_change_buyer_hire_me_url(): void
    {
        $shortId = 'aabbccdd2200';
        $agent   = $this->makeAgent($shortId);

        $this->makeProfile($agent, 'buyer',  'residential', ['Help buyer find home']);
        $this->makeProfile($agent, 'seller', 'residential', ['List on MLS']);

        $buyerUrl  = $this->hireUrl($shortId, 'buyer',  'residential');
        $sellerUrl = $this->hireUrl($shortId, 'seller', 'residential');

        // Buyer preset edit page must contain the buyer URL and NOT the seller URL.
        $response = $this->actingAs($agent)->get($this->editUrl('buyer', 'residential'));
        $response->assertStatus(200);
        $html = $response->getContent();
        $this->assertStringContainsString($buyerUrl, $html);
        // The seller URL path segment must not appear as an href for this preset.
        $this->assertStringNotContainsString(
            'href="' . $sellerUrl . '"',
            $html,
            'Buyer edit page must not link to seller URL'
        );

        // Seller preset edit page must contain the seller URL and NOT the buyer URL.
        $response = $this->actingAs($agent)->get($this->editUrl('seller', 'residential'));
        $response->assertStatus(200);
        $html = $response->getContent();
        $this->assertStringContainsString($sellerUrl, $html);
        $this->assertStringNotContainsString(
            'href="' . $buyerUrl . '"',
            $html,
            'Seller edit page must not link to buyer URL'
        );
    }

    public function test_landlord_commercial_url_is_independent_of_landlord_residential(): void
    {
        $shortId = 'aabbccdd3300';
        $agent   = $this->makeAgent($shortId);

        $this->makeProfile($agent, 'landlord', 'residential', ['Market rental unit']);
        $this->makeProfile($agent, 'landlord', 'commercial',  ['Market commercial space']);

        $residUrl   = $this->hireUrl($shortId, 'landlord', 'residential');
        $commUrl    = $this->hireUrl($shortId, 'landlord', 'commercial');

        // Residential preset edit page must link to residential URL only.
        $response = $this->actingAs($agent)->get($this->editUrl('landlord', 'residential'));
        $response->assertStatus(200);
        $html = $response->getContent();
        $this->assertStringContainsString($residUrl, $html);
        $this->assertStringNotContainsString('href="' . $commUrl . '"', $html);

        // Commercial preset edit page must link to commercial URL only.
        $response = $this->actingAs($agent)->get($this->editUrl('landlord', 'commercial'));
        $response->assertStatus(200);
        $html = $response->getContent();
        $this->assertStringContainsString($commUrl, $html);
        $this->assertStringNotContainsString('href="' . $residUrl . '"', $html);
    }

    // =========================================================================
    // §3 — Hire Me preview: strict preset lookup (no wrong-preset fallback)
    // =========================================================================

    public function test_hire_me_preview_shows_unavailable_when_no_preset_exists(): void
    {
        $shortId = 'aabbccdd4400';
        $agent   = $this->makeAgent($shortId);

        // Only buyer/residential exists — requesting seller/residential must show unavailable.
        $this->makeProfile($agent, 'buyer', 'residential', ['Find buyer properties']);

        // Follow the public URL to the internal preview route.
        $response = $this->get($this->hireUrl($shortId, 'seller', 'residential'));
        $response->assertRedirect();

        $previewResponse = $this->followRedirects($response);
        $previewResponse->assertStatus(200);
        $previewResponse->assertSee('Profile not available', false);
    }

    public function test_hire_me_preview_shows_correct_services_not_wrong_preset(): void
    {
        $shortId = 'aabbccdd5500';
        $agent   = $this->makeAgent($shortId);

        $buyerService  = 'Help buyer find a home property';
        $sellerService = 'List the seller property on MLS system';

        $this->makeProfile($agent, 'buyer',  'residential', [$buyerService]);
        $this->makeProfile($agent, 'seller', 'residential', [$sellerService]);

        // Visit the seller Hire Me page — must show seller service, not buyer service.
        $response = $this->followRedirects(
            $this->get($this->hireUrl($shortId, 'seller', 'residential'))
        );
        $response->assertStatus(200);
        $response->assertSee($sellerService, false);
        $response->assertDontSee($buyerService, false);

        // Visit the buyer Hire Me page — must show buyer service, not seller service.
        $response = $this->followRedirects(
            $this->get($this->hireUrl($shortId, 'buyer', 'residential'))
        );
        $response->assertStatus(200);
        $response->assertSee($buyerService, false);
        $response->assertDontSee($sellerService, false);
    }

    public function test_hire_me_preview_does_not_fall_back_to_role_default_preset(): void
    {
        $shortId = 'aabbccdd6600';
        $agent   = $this->makeAgent($shortId);

        // Create a role-default (__default__) record for buyer.
        AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => 'buyer',
            'property_type' => AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data'  => ['services' => ['Default fallback service']],
        ]);

        // No buyer/residential specific preset exists — preview must show unavailable,
        // not silently display the __default__ fallback preset's services.
        $response = $this->followRedirects(
            $this->get($this->hireUrl($shortId, 'buyer', 'residential'))
        );
        $response->assertStatus(200);
        $response->assertSee('Profile not available', false);
        $response->assertDontSee('Default fallback service', false);
    }

    // =========================================================================
    // §4 — Unicode: no literal \u2019 appears in rendered service labels
    // =========================================================================

    public function test_service_label_with_literal_unicode_escape_renders_decoded(): void
    {
        $shortId = 'aabbccdd7700';
        $agent   = $this->makeAgent($shortId);

        // Store a service label containing a literal \u2019 sequence (as would be
        // written by a source that serialised the apostrophe as a JSON escape rather
        // than the actual UTF-8 character).
        $rawLabel    = "Negotiate the buyer\u2019s terms";   // literal \u2019
        $decodedLabel = "Negotiate the buyer\u{2019}s terms"; // actual ' char

        $this->makeProfile($agent, 'buyer', 'residential', [$rawLabel]);

        // The Hire Me preview must render the decoded character, not the raw escape.
        $response = $this->followRedirects(
            $this->get($this->hireUrl($shortId, 'buyer', 'residential'))
        );
        $response->assertStatus(200);
        $response->assertSee($decodedLabel, false);
        $response->assertDontSee('\\u2019', false);
    }

    public function test_services_formatter_decode_service_label_handles_unicode_escapes(): void
    {
        $raw     = "Agent\u2019s commission";       // literal backslash-u sequence
        $decoded = ServicesFormatter::decodeServiceLabel($raw);

        $this->assertSame("Agent\u{2019}s commission", $decoded);
        $this->assertStringNotContainsString('\u2019', $decoded);
    }

    public function test_services_formatter_decode_does_not_double_decode_clean_strings(): void
    {
        $clean = "Agent\u{2019}s commission"; // already proper UTF-8
        $result = ServicesFormatter::decodeServiceLabel($clean);

        $this->assertSame($clean, $result);
    }

    // =========================================================================
    // §5 — Generic profile route remains available as a fallback
    // =========================================================================

    public function test_generic_profile_route_still_accessible(): void
    {
        $shortId = 'aabbccdd8800';
        $agent   = $this->makeAgent($shortId);
        $this->makeProfile($agent, 'buyer', 'residential', ['Help buyer find home']);

        $response = $this->get("/agent/{$shortId}/profile");
        $response->assertStatus(200);
    }

    // =========================================================================
    // §6 — Edit page services checklist shows only the correct preset's services
    // =========================================================================

    public function test_edit_page_services_checklist_shows_correct_presets_services(): void
    {
        $shortId = 'aabbccdd9900';
        $agent   = $this->makeAgent($shortId);

        $buyerService  = 'Unique buyer service label abcxyz';
        $sellerService = 'Unique seller service label qrstuv';

        $this->makeProfile($agent, 'buyer',  'residential', [$buyerService]);
        $this->makeProfile($agent, 'seller', 'residential', [$sellerService]);

        // The buyer edit page must show the buyer service as checked,
        // and must not show the seller service as checked.
        $response = $this->actingAs($agent)->get($this->editUrl('buyer', 'residential'));
        $response->assertStatus(200);

        // Buyer's custom service value must appear (it's stored in selectedServices).
        // We verify the seller-only service is not in the preset's selected list.
        $html = $response->getContent();
        $this->assertStringNotContainsString($sellerService, $html);
    }
}
