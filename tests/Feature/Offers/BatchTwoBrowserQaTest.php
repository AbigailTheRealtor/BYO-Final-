<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\TenantAgentAuction as LiveHireCreate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch 2 — browser-QA remediation regression guards.
 *
 *   #15 Hire Buyer Garage/Parking "Yes" reveal. toggleGarageOptions() lives inline in
 *       the live create Blade and is shared by every role the component serves. It gated
 *       on property_type === 'Commercial Property', but that spelling only exists in the
 *       tenant/landlord vocabulary. Buyer uses 'Commercial'/'Business' and seller uses
 *       'Commercial'/'Business' too, so for both the gate was always false: the function
 *       force-hid the wrappers and returned before ever reading the "Yes" selection.
 *
 *   #14 Vacant Land "Property Style" → "Other" on Seller EDIT. The edit view initialised
 *       #property_style_select as a Select2 and persisted property_items via change.pss,
 *       but nothing toggled the .other_property_items_seller wrapper, so choosing "Other"
 *       saved the value while the free-text input stayed hidden. Create already had the
 *       delegated handler; edit had no delegate block at all.
 *
 * Both fixes are inline JS inside conditionally-rendered Blade, so — following the
 * convention set by BatchAUiRegressionTest — the handler wiring is asserted against the
 * Blade source. Behaviour in a real browser (Select2 synthetic events, actual reveal) is
 * still MANUAL BROWSER QA; these guards only prove the wiring cannot silently regress.
 */
class BatchTwoBrowserQaTest extends TestCase
{
    use DatabaseTransactions;

    private const LIVE_CREATE = 'resources/views/livewire/tenant-agent-auction.blade.php';
    private const SELLER_EDIT = 'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php';
    private const SELLER_CREATE = 'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php';

    private function source(string $relativePath): string
    {
        $path = base_path($relativePath);
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    /** #15: the garage gate accepts the buyer/seller vocabulary, which is what was broken. */
    public function test_garage_gate_accepts_the_buyer_and_seller_property_types(): void
    {
        $source = $this->source(self::LIVE_CREATE);

        $this->assertMatchesRegularExpression(
            '/var isCommercial = \[[^\]]*\'Commercial\'[^\]]*\]\.indexOf\(currentPropType\) !== -1;/',
            $source,
            'toggleGarageOptions() must treat buyer/seller "Commercial" as commercial'
        );
        $this->assertMatchesRegularExpression(
            '/var isCommercial = \[[^\]]*\'Business\'[^\]]*\]\.indexOf\(currentPropType\) !== -1;/',
            $source,
            'toggleGarageOptions() must treat buyer/seller "Business" as commercial'
        );
    }

    /** #15 regression: the tenant/landlord spelling that already worked must keep working. */
    public function test_garage_gate_still_accepts_the_tenant_landlord_property_type(): void
    {
        $this->assertMatchesRegularExpression(
            '/var isCommercial = \[[^\]]*\'Commercial Property\'[^\]]*\]\.indexOf\(currentPropType\) !== -1;/',
            $this->source(self::LIVE_CREATE),
            "'Commercial Property' must stay in the accepted set or the tenant/landlord reveal regresses"
        );
    }

    /** #15: the exact-equality gate that caused the dead reveal must be gone. */
    public function test_garage_gate_no_longer_uses_the_exact_commercial_property_match(): void
    {
        $this->assertStringNotContainsString(
            "var isCommercial = currentPropType === 'Commercial Property';",
            $this->source(self::LIVE_CREATE),
            'the exact-match gate is the #15 defect and must not return'
        );
    }

    /** #15 regression: non-commercial types must still force both wrappers hidden. */
    public function test_garage_gate_still_hides_the_wrappers_for_non_commercial_types(): void
    {
        $source = $this->source(self::LIVE_CREATE);

        $this->assertStringContainsString('if (!isCommercial) {', $source);
        $this->assertMatchesRegularExpression(
            '/if \(!isCommercial\) \{\s*if \(optionsWrapper\) optionsWrapper\.classList\.add\(\'d-none\'\);\s*if \(otherInputWrapper\) otherInputWrapper\.classList\.add\(\'d-none\'\);\s*return;/',
            $source,
            'Residential / Vacant Land / Income must still hide the garage wrappers'
        );
    }

    /** #15: the buyer garage select actually renders for a Commercial buyer (the dead case). */
    public function test_live_buyer_create_renders_the_garage_select_for_commercial(): void
    {
        $owner = User::factory()->create(['user_type' => 'buyer']);

        Livewire::actingAs($owner)
            ->test(LiveHireCreate::class, ['user_type' => 'buyer'])
            ->set('property_type', 'Commercial')
            ->assertHasNoErrors()
            ->assertSeeHtml('id="garage_parking_spaces"')
            ->assertSeeHtml('id="garage_parking_spaces_option_wrapper"')
            ->assertSeeHtml('id="other_parking_space_wrapper"');
    }

    /** #14: seller EDIT now delegates the property-style "Other" reveal. */
    public function test_seller_edit_delegates_the_property_style_other_reveal(): void
    {
        $source = $this->source(self::SELLER_EDIT);

        $this->assertStringContainsString(
            "\$(document).on('change', '#property_style_select', function() {",
            $source,
            '#14: the reveal must be delegated on document so Select2 synthetic events reach it'
        );
        $this->assertStringContainsString(
            "\$('.other_property_items_seller').toggleClass('d-none', val !== 'Other');",
            $source,
            '#14: selecting "Other" must reveal the free-text wrapper on the edit view'
        );
    }

    /** #14: the delegate is registered exactly once, so Livewire re-renders cannot stack handlers. */
    public function test_seller_edit_property_style_delegate_is_registered_once(): void
    {
        $source = $this->source(self::SELLER_EDIT);

        $this->assertStringContainsString('if (!document._sellerEditPropertyStyleOtherDelegateAdded) {', $source);
        $this->assertSame(
            1,
            substr_count($source, "\$(document).on('change', '#property_style_select'"),
            'the property-style delegate must be bound exactly once in the edit view'
        );
    }

    /** #14 regression: edit must keep persisting property_items via change.pss, not replace it. */
    public function test_seller_edit_still_persists_property_items(): void
    {
        $this->assertStringContainsString(
            "\$pss.off('change.pss').on('change.pss', function() {",
            $this->source(self::SELLER_EDIT),
            'the reveal fix must not displace the existing property_items persistence handler'
        );
    }

    /** #14 regression: the create view's already-working delegated reveal is untouched. */
    public function test_seller_create_delegated_reveal_is_preserved(): void
    {
        $source = $this->source(self::SELLER_CREATE);

        $this->assertStringContainsString('if (!document._otherVisibilityDelegateAdded) {', $source);
        $this->assertStringContainsString(
            "\$('.other_property_items_seller').toggleClass('d-none', val !== 'Other');",
            $source,
            'the seller create reveal (#14, already verified) must remain in place'
        );
    }
}
