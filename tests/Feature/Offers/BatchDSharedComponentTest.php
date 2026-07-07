<?php

namespace Tests\Feature\Offers;

use Tests\TestCase;

/**
 * Batch D — shared-component (SC1/SC2/SC3) launch-audit remediation guards.
 *
 * Batch D targeted the copy-pasted JS/Blade shared components behind the Seller
 * Exchange/Trade and Property-Style flows. Most of the audited items were found
 * ALREADY COMPLIANT in the committed code during the Batch D diagnosis pass and
 * are therefore verify-only (no code change). The code changes this test guards
 * are the three minimal, additive fixes actually applied:
 *
 *   #8  (SC2) — the exchange_item change handler refreshes the select's
 *       data-selected attribute after committing, so the re-init pass on the
 *       next Livewire message.processed never re-applies a stale selection.
 *       Applied to Hire Seller + Create Seller wrappers.
 *   #10 (SC2) — the "Estimated Value" and "Acceptable Condition" exchange
 *       form-groups carry a stable wire:key so Livewire morphdom keeps them
 *       across live re-renders (they use live wire:model). Hire + Create Seller.
 *   #14 (SC3) — Create Seller registers #property_style_select in the delegated
 *       $(document).on('change', …) block so Select2's synthetic change events
 *       reveal the .other_property_items_seller "Other" input (Vacant Land style).
 *
 * The test also pins the verify-only items whose wording Batch D must NOT change:
 * the Purchase Purpose (#17) and Flood Zone Preference (#18) "Other" placeholders,
 * and the Vacant Land Property Style (#14) placeholder.
 *
 * NOTE: Like the Batch A guards, these assert against Blade/JS source (the reveal
 * markup is conditionally rendered). Every Batch D item remains
 * "CODE COMPLETE — HUMAN BROWSER QA REQUIRED" until a human browser QA pass runs.
 */
class BatchDSharedComponentTest extends TestCase
{
    private const VIEW_ROOT = 'resources/views/livewire';

    private function viewSource(string $relativePath): string
    {
        $full = base_path(self::VIEW_ROOT . '/' . $relativePath);
        $this->assertFileExists($full, "Expected view partial missing: {$relativePath}");

        return (string) file_get_contents($full);
    }

    // ─── #8 (SC2) · exchange_item data-selected refresh ───────────────────────

    /** @test */
    public function hire_seller_exchange_handler_refreshes_data_selected(): void
    {
        $src = $this->viewSource('hire-seller-agent/hire-seller-agent.blade.php');

        $this->assertStringContainsString(
            "\$exEl.attr('data-selected', JSON.stringify(selectedValues));",
            $src,
            '#8: Hire Seller exchange_item change handler must refresh data-selected after commit.'
        );
    }

    /** @test */
    public function create_seller_exchange_handler_refreshes_data_selected(): void
    {
        $src = $this->viewSource('offer-listing/seller/offer-seller-listing.blade.php');

        $this->assertStringContainsString(
            "\$exEl.attr('data-selected', JSON.stringify(selectedValues));",
            $src,
            '#8: Create Seller exchange_item change handler must refresh data-selected after commit.'
        );
    }

    // ─── #10 (SC2) · wire:key on the two exchange form-groups ──────────────────

    /** @test */
    public function hire_seller_exchange_value_and_condition_have_wire_key(): void
    {
        $src = $this->viewSource(
            'hire-seller-agent/seller-agent-auction-tabs/commission-based/seller-terms.blade.php'
        );

        $this->assertStringContainsString('wire:key="hire-seller-exchange-item-value"', $src,
            '#10: Hire Seller "Estimated Value" form-group must carry a stable wire:key.');
        $this->assertStringContainsString('wire:key="hire-seller-exchange-item-condition"', $src,
            '#10: Hire Seller "Acceptable Condition" form-group must carry a stable wire:key.');
    }

    /** @test */
    public function create_seller_exchange_value_and_condition_have_wire_key(): void
    {
        $src = $this->viewSource(
            'offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php'
        );

        $this->assertStringContainsString('wire:key="create-seller-exchange-item-value"', $src,
            '#10: Create Seller "Estimated Value" form-group must carry a stable wire:key.');
        $this->assertStringContainsString('wire:key="create-seller-exchange-item-condition"', $src,
            '#10: Create Seller "Acceptable Condition" form-group must carry a stable wire:key.');
    }

    // ─── #14 (SC3) · property_style_select "Other" reveal registration ─────────

    /** @test */
    public function create_seller_registers_property_style_select_in_delegated_handler(): void
    {
        $src = $this->viewSource('offer-listing/seller/offer-seller-listing.blade.php');

        // Registered in the SAME delegated block as the other "Other" reveals so it
        // catches Select2's synthetic jQuery change events (native listeners miss them).
        $this->assertStringContainsString(
            "\$(document).on('change', '#property_style_select', function() {",
            $src,
            '#14: Create Seller must register #property_style_select in the delegated change handler.'
        );
        $this->assertStringContainsString(
            "\$('.other_property_items_seller').toggleClass('d-none', val !== 'Other');",
            $src,
            '#14: Create Seller must toggle the .other_property_items_seller wrapper on Property Style change.'
        );
    }

    /** @test */
    public function create_seller_property_style_other_wrapper_class_matches_handler(): void
    {
        // Guards the class-name mismatch called out in the audit: the reveal wrapper is
        // .other_property_items_seller, which the handler above must target.
        $src = $this->viewSource(
            'offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php'
        );

        $this->assertStringContainsString('other_property_items_seller', $src,
            '#14: Create Seller Property Style "Other" wrapper must use the other_property_items_seller class.');
    }

    // ─── Verify-only placeholder pins (wording must NOT change) ────────────────

    /** @test */
    public function vacant_land_property_style_placeholder_unchanged(): void
    {
        $src = $this->viewSource(
            'offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php'
        );

        $this->assertStringContainsString(
            'placeholder="Enter property style (e.g., Solar farm, RV park, Conservation easement)"',
            $src,
            '#14: Vacant Land Property Style "Other" placeholder wording must be preserved.'
        );
    }

    /** @test */
    public function purchase_purpose_other_placeholder_unchanged_both_flows(): void
    {
        $needle = 'placeholder="Enter purchase purpose (e.g., Business expansion, House flipping, Estate planning)"';

        $hire = $this->viewSource(
            'hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php'
        );
        $create = $this->viewSource(
            'offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php'
        );

        $this->assertStringContainsString($needle, $hire,
            '#17: Hire Buyer Purchase Purpose "Other" placeholder wording must be preserved.');
        $this->assertStringContainsString($needle, $create,
            '#17: Create Buyer Purchase Purpose "Other" placeholder wording must be preserved.');
    }

    /** @test */
    public function flood_zone_other_placeholder_unchanged_both_flows(): void
    {
        $needle = 'placeholder="Enter flood zone preference (e.g., Open to coastal properties if insurance costs are reasonable)"';

        $hire = $this->viewSource(
            'hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php'
        );
        $create = $this->viewSource(
            'offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php'
        );

        $this->assertStringContainsString($needle, $hire,
            '#18: Hire Buyer Flood Zone "Other" placeholder wording must be preserved.');
        $this->assertStringContainsString($needle, $create,
            '#18: Create Buyer Flood Zone "Other" placeholder wording must be preserved.');
    }
}
