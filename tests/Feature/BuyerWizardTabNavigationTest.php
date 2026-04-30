<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for the Buyer Create Listing wizard tab navigation.
 *
 * Verifies that Task #218's fix (removing the dead @if guard that hid the
 * Services and Broker Compensation nav buttons) is in effect, and that all
 * six commission-based tab panes and their nav buttons are present in the DOM.
 *
 * Prerequisites (satisfied by the test-safe schema baseline):
 *   - Route::middleware('auth') wraps /offer-listing/buyer
 *   - BuyerOfferListing Livewire component renders offer-buyer-listing.blade.php
 *   - settings table exists (page layout reads get_setting())
 *   - notifications table exists (auth layout)
 */
class BuyerWizardTabNavigationTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a buyer user and return an authenticated response for
     * GET /offer-listing/buyer.
     */
    private function buyerResponse(): \Illuminate\Testing\TestResponse
    {
        $user = User::factory()->create(); // default user_type = 'buyer'

        return $this->actingAs($user)->get('/offer-listing/buyer');
    }

    // =========================================================================
    // Test 1 — Page loads with HTTP 200
    // =========================================================================

    public function test_buyer_offer_listing_page_loads_for_authenticated_user(): void
    {
        $this->buyerResponse()->assertStatus(200);
    }

    // =========================================================================
    // Test 2 — All six commission-based tab labels are present in the DOM
    //
    // The nav loop in offer-buyer-listing.blade.php iterates over:
    //   ['Listing Details', 'Property Preferences', 'Purchasing Terms',
    //    'Services', 'Additional Details', 'Broker Compensation']
    // All six must appear as visible text in the rendered page.
    // =========================================================================

    public function test_all_six_commission_based_tab_labels_are_present(): void
    {
        $response = $this->buyerResponse();

        $response->assertSee('Listing Details');
        $response->assertSee('Property Preferences');
        $response->assertSee('Purchasing Terms');
        $response->assertSee('Additional Details');
        $response->assertSee('Services');
        $response->assertSee('Broker Compensation');
    }

    // =========================================================================
    // Test 3 — Services tab nav button is rendered in the DOM
    //
    // The old guard `@if (!in_array($tab, ['Services', 'Broker Compensation']))`
    // prevented this <li> from rendering entirely. Asserting the id attribute
    // exists proves the guard has been removed.
    // =========================================================================

    public function test_services_tab_nav_button_is_present(): void
    {
        $this->buyerResponse()->assertSee('id="services-tab"', false);
    }

    // =========================================================================
    // Test 4 — Broker Compensation tab nav button is rendered in the DOM
    // =========================================================================

    public function test_broker_compensation_tab_nav_button_is_present(): void
    {
        $this->buyerResponse()->assertSee('id="broker-compensation-tab"', false);
    }

    // =========================================================================
    // Test 5 — Services tab pane is present in the DOM
    //
    // The tab-pane div with id="services" must exist so that clicking the nav
    // button has a target to show.
    // =========================================================================

    public function test_services_tab_pane_is_present_in_dom(): void
    {
        $this->buyerResponse()->assertSee('id="services"', false);
    }

    // =========================================================================
    // Test 6 — Broker Compensation tab pane is present in the DOM
    // =========================================================================

    public function test_broker_compensation_tab_pane_is_present_in_dom(): void
    {
        $this->buyerResponse()->assertSee('id="broker-compensation"', false);
    }

    // =========================================================================
    // Test 7 — Old hidden-tab filter is not applied
    //
    // Before Task #218 the nav loop had:
    //   @if (!in_array($tab, ['Services', 'Broker Compensation']))
    // which caused both nav buttons to be omitted from the rendered HTML.
    // We confirm its removal by asserting BOTH filtered-out buttons are
    // now present, and that neither button id is absent from the output.
    // =========================================================================

    public function test_old_hidden_tab_filter_is_not_present(): void
    {
        $response = $this->buyerResponse();

        // Both nav buttons that the old guard suppressed must now exist.
        $response->assertSee('id="services-tab"', false);
        $response->assertSee('id="broker-compensation-tab"', false);

        // Neither tab pane should be absent.
        $response->assertSee('id="services"', false);
        $response->assertSee('id="broker-compensation"', false);
    }
}
