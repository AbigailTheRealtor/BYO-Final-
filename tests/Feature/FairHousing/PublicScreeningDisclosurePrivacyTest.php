<?php

namespace Tests\Feature\FairHousing;

use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Fair Housing P0-D — screening disclosures must not render to anonymous visitors.
 *
 * WHY THIS IS AN ANONYMOUS-ACCESS PROBLEM, NOT A PERMISSIONS ONE. routes/web.php places
 * the four Offer Listing detail views deliberately outside the auth group, with a comment
 * saying so, because a visitor must be able to open a card from the public search pages.
 * TenantOfferListingController::view() then gates only two things: archived listings, and
 * draft / not-yet-approved listings. An approved, non-draft listing is served to the open
 * internet in full. Everything the Blade renders is therefore public by default.
 *
 * WHAT WAS LEAKING. The Pre-Screening card rendered the tenant's own answers about their
 * rental, credit, criminal and background history with no viewer gate at all:
 *   - "Rental History Disclosure" (screening_concerns) — asked with the tooltip "…that you
 *     would like THE LANDLORD to be aware of", a promise about audience the page did not keep
 *   - "Prior Eviction" (prior_eviction)
 *   - "Prior Felony" (prior_felony)
 *   - "Credit Score Range" (credit_score_range)
 * On the landlord page the same shape leaked prior_eviction / prior_felony and, worse, the
 * two free-text "Circumstances" explanations.
 *
 * THE GATE IS OWNERSHIP AND NOTHING WIDER. No "all agents" tier is introduced here: that
 * would be a new audience wearing the word "authorized", and choosing it is a product
 * decision. The owner already had a gate in these files, and that is the one reused. The
 * data itself is untouched and still renders for the person who supplied it.
 *
 * NOT A BLADE-TEXT ASSERTION. These drive the real public route over HTTP as a guest, as
 * an unrelated logged-in user, and as the owner.
 */
class PublicScreeningDisclosurePrivacyTest extends TestCase
{
    use DatabaseTransactions;

    /** The distinctive stored values; finding any of them in guest HTML is the failure. */
    private const SENTINELS = [
        'screening_concerns'  => 'SENTINEL-RENTAL-HISTORY-YES',
        'prior_eviction'      => 'SENTINEL-EVICTION-YES',
        'prior_felony'        => 'SENTINEL-FELONY-YES',
        'credit_score_range'  => 'SENTINEL-CREDIT-500-549',
    ];

    /** The row labels that must not appear for a non-owner. */
    private const LABELS = [
        'Rental History Disclosure',
        'Prior Eviction',
        'Prior Felony',
        'Credit Score Range',
    ];

    private function makePublishedTenantListing(User $owner): TenantAgentAuction
    {
        $auction = TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);

        // Make it resolve as an Offer Listing rather than a Hire Agent record.
        $auction->saveMeta('workflow_type', 'offer_listing');

        foreach (self::SENTINELS as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        // A benign field in the same card, so the card itself still has a reason to render
        // and the test distinguishes "gated the sensitive rows" from "hid the whole page".
        $auction->saveMeta('rental_purpose', 'SENTINEL-PUBLIC-RENTAL-PURPOSE');

        return $auction;
    }

    // =====================================================================
    // Anonymous
    // =====================================================================

    /** @test */
    public function an_anonymous_visitor_cannot_see_any_tenant_screening_disclosure(): void
    {
        $owner   = User::factory()->create();
        $auction = $this->makePublishedTenantListing($owner);

        $response = $this->get(route('offer.listing.tenant.view', $auction->id));
        $response->assertOk();

        foreach (self::SENTINELS as $key => $value) {
            $response->assertDontSee($value, false);
        }
        foreach (self::LABELS as $label) {
            $response->assertDontSee($label, false);
        }
    }

    /** @test */
    public function the_rest_of_the_public_listing_still_renders_for_an_anonymous_visitor(): void
    {
        // The fix must suppress four rows, not privatise the listing.
        $owner   = User::factory()->create();
        $auction = $this->makePublishedTenantListing($owner);

        $response = $this->get(route('offer.listing.tenant.view', $auction->id));

        $response->assertOk();
        $response->assertSee('SENTINEL-PUBLIC-RENTAL-PURPOSE', false);
    }

    /** @test */
    public function a_suppressed_value_does_not_reappear_in_the_additional_information_fallback(): void
    {
        // The tenant view dumps every populated meta key that is not listed in $knownKeys
        // into a public "Additional Information" section. Gating the named rows would have
        // been pointless — worse than pointless — if these keys were not in that list.
        $owner   = User::factory()->create();
        $auction = $this->makePublishedTenantListing($owner);

        $response = $this->get(route('offer.listing.tenant.view', $auction->id));

        $response->assertOk();
        $response->assertDontSee('SENTINEL-RENTAL-HISTORY-YES', false);
        $response->assertDontSee('SENTINEL-FELONY-YES', false);
    }

    // =====================================================================
    // Logged in, but not the owner
    // =====================================================================

    /** @test */
    public function merely_logging_in_does_not_grant_access_to_another_persons_disclosures(): void
    {
        $owner     = User::factory()->create();
        $stranger  = User::factory()->create();
        $auction   = $this->makePublishedTenantListing($owner);

        $response = $this->actingAs($stranger)->get(route('offer.listing.tenant.view', $auction->id));
        $response->assertOk();

        foreach (self::SENTINELS as $key => $value) {
            $response->assertDontSee($value, false);
        }
    }

    // =====================================================================
    // The owner keeps their own data
    // =====================================================================

    /** @test */
    public function the_owner_still_sees_their_own_screening_disclosures(): void
    {
        $owner   = User::factory()->create();
        $auction = $this->makePublishedTenantListing($owner);

        $response = $this->actingAs($owner)->get(route('offer.listing.tenant.view', $auction->id));
        $response->assertOk();

        foreach (self::SENTINELS as $key => $value) {
            $response->assertSee($value, false);
        }
        foreach (self::LABELS as $label) {
            $response->assertSee($label, false);
        }
    }

    // =====================================================================
    // Landlord detail page — criminal-history values and their free text
    // =====================================================================

    /** @test */
    public function the_landlord_detail_view_gates_criminal_and_eviction_history_behind_ownership(): void
    {
        $blade = file_get_contents(base_path('resources/views/offer-listing/landlord/view.blade.php'));

        // The four rows must sit inside the file's existing owner conditional.
        $gatedBlock = "@if(auth()->check() && auth()->id() == \$auction->user_id)\n"
            . "                        {!! \$row('Prior Eviction', \$yesNo(\$str('prior_eviction'))) !!}";

        $this->assertStringContainsString(
            $gatedBlock,
            $blade,
            'Landlord Prior Eviction / Prior Felony rows must be owner-gated.'
        );

        foreach (['eviction_explanation', 'prior_felony_explanation'] as $freeText) {
            $this->assertStringContainsString($freeText, $blade);
        }
    }

    /** @test */
    public function the_landlord_free_text_explanations_are_only_reachable_inside_the_owner_gate(): void
    {
        $blade = file_get_contents(base_path('resources/views/offer-listing/landlord/view.blade.php'));

        // Every occurrence of the two explanation rows must appear after the owner gate
        // opens and before it closes. Locating them by offset keeps this honest if the
        // block is reordered.
        $gateAt = strpos($blade, "@if(auth()->check() && auth()->id() == \$auction->user_id)\n                        {!! \$row('Prior Eviction'");
        $this->assertNotFalse($gateAt, 'Owner gate for the criminal-history block was not found.');

        foreach (['Eviction Explanation / Circumstances', 'Felony Explanation / Circumstances'] as $label) {
            $labelAt = strpos($blade, $label);
            $this->assertNotFalse($labelAt, "{$label} row not found.");
            $this->assertGreaterThan($gateAt, $labelAt, "{$label} renders before the owner gate opens.");
        }
    }

    // =====================================================================
    // Structural: the tenant gate exists and is ownership-based
    // =====================================================================

    /** @test */
    public function the_tenant_gate_is_ownership_and_not_a_broader_new_permission_tier(): void
    {
        $blade = file_get_contents(base_path('resources/views/offer-listing/tenant/view.blade.php'));

        $this->assertStringContainsString(
            '$viewerOwnsListing = auth()->check() && (int) auth()->id() === (int) $ownerId;',
            $blade
        );

        // No "any authenticated user" or "any agent" shortcut was introduced.
        $this->assertStringNotContainsString('auth()->check() && $hasPrescreening', $blade);
        $this->assertStringNotContainsString("user_type === 'agent'", $blade);
    }
}
