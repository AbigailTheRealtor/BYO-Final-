<?php

namespace Tests\Feature\Offers;

use App\Http\Controllers\SellerOfferListingController;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tests for the agent-editable Estimated Payment Assumptions feature.
 *
 * Covers buildCalcData() priority chain:
 *   agent payment_* override → listing fields → admin defaults → hardcoded fallback
 */
class SellerPaymentAssumptionsTest extends TestCase
{
    use DatabaseTransactions;

    private function callBuildCalcData(array $meta): array
    {
        $controller = new SellerOfferListingController();
        $method     = new ReflectionMethod($controller, 'buildCalcData');
        $method->setAccessible(true);

        return $method->invoke($controller, $meta);
    }

    private function makeAuction(User $user, array $metaRows = []): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        foreach ($metaRows as $key => $value) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => $key,
                'meta_value'              => (string) $value,
            ]);
        }

        return $auction;
    }

    // ── show_buydown_options ──────────────────────────────────────────────────

    public function test_show_buydown_options_defaults_to_true_when_not_set(): void
    {
        $calcData = $this->callBuildCalcData([]);

        $this->assertTrue($calcData['show_buydown_options'],
            'show_buydown_options must default to true when no agent override is present.');
    }

    public function test_show_buydown_options_is_false_when_override_set_to_zero(): void
    {
        $calcData = $this->callBuildCalcData(['payment_show_buydown_options' => '0']);

        $this->assertFalse($calcData['show_buydown_options'],
            'show_buydown_options must be false when payment_show_buydown_options is "0".');
    }

    public function test_show_buydown_options_is_true_when_override_set_to_one(): void
    {
        $calcData = $this->callBuildCalcData(['payment_show_buydown_options' => '1']);

        $this->assertTrue($calcData['show_buydown_options'],
            'show_buydown_options must be true when payment_show_buydown_options is "1".');
    }

    // ── interest rate ─────────────────────────────────────────────────────────

    public function test_agent_interest_rate_override_appears_in_calc_data(): void
    {
        $calcData = $this->callBuildCalcData(['payment_interest_rate' => '5.5']);

        $this->assertEquals(5.5, $calcData['interest_rate'],
            'Agent payment_interest_rate override must appear in $calcData[interest_rate].');
    }

    public function test_admin_interest_rate_applies_when_no_override(): void
    {
        // When no override, fallback is admin get_setting() or 6.5
        $calcData = $this->callBuildCalcData([]);

        $this->assertIsFloat($calcData['interest_rate']);
        $this->assertGreaterThan(0, $calcData['interest_rate'],
            'interest_rate must be a positive number from admin defaults when no override is present.');
    }

    // ── down payment ──────────────────────────────────────────────────────────

    public function test_agent_down_payment_pct_override_appears_in_calc_data(): void
    {
        $calcData = $this->callBuildCalcData(['payment_down_payment_pct' => '15']);

        $this->assertEquals(15.0, $calcData['down_pct'],
            'Agent payment_down_payment_pct override must appear in $calcData[down_pct].');
    }

    // ── PMI rate ──────────────────────────────────────────────────────────────

    public function test_agent_pmi_rate_override_appears_in_calc_data(): void
    {
        $calcData = $this->callBuildCalcData([
            'payment_pmi_rate'         => '0.5',
            'payment_down_payment_pct' => '10',
        ]);

        $this->assertEquals(0.5, $calcData['pmi_rate'],
            'Agent payment_pmi_rate override must appear in $calcData[pmi_rate].');
    }

    public function test_pmi_zeroes_when_down_payment_override_is_twenty_pct_or_more(): void
    {
        $calcData = $this->callBuildCalcData([
            'payment_down_payment_pct' => '20',
            'payment_pmi_rate'         => '0.85',
        ]);

        $this->assertEquals(0.0, $calcData['pmi_rate'],
            'PMI rate must be zeroed when down payment override is >= 20%.');
    }

    public function test_pmi_zeroes_when_down_payment_override_exceeds_twenty_pct(): void
    {
        $calcData = $this->callBuildCalcData([
            'payment_down_payment_pct' => '25',
            'payment_pmi_rate'         => '0.85',
        ]);

        $this->assertEquals(0.0, $calcData['pmi_rate'],
            'PMI rate must be zeroed when down payment override exceeds 20%.');
    }

    // ── annual property taxes ─────────────────────────────────────────────────

    public function test_agent_taxes_override_takes_priority_over_listing_annual_property_taxes(): void
    {
        $calcData = $this->callBuildCalcData([
            'payment_annual_property_taxes' => '6000',
            'annual_property_taxes'         => '3000',  // listing field — must be superseded
        ]);

        $this->assertEquals(6000.0, $calcData['taxes_annual'],
            'payment_annual_property_taxes agent override must take priority over annual_property_taxes listing field.');
        $this->assertEquals('agent override', $calcData['taxes_source']);
    }

    public function test_listing_annual_property_taxes_used_when_no_agent_override(): void
    {
        $calcData = $this->callBuildCalcData(['annual_property_taxes' => '4200']);

        $this->assertEquals(4200.0, $calcData['taxes_annual']);
        $this->assertEquals('from listing', $calcData['taxes_source']);
    }

    // ── HOA ───────────────────────────────────────────────────────────────────

    public function test_agent_hoa_amount_and_monthly_frequency_normalize_correctly(): void
    {
        $calcData = $this->callBuildCalcData([
            'payment_hoa_fee_amount'    => '300',
            'payment_hoa_fee_frequency' => 'Monthly',
        ]);

        $this->assertEquals(300.0, $calcData['hoa_monthly'],
            'Monthly HOA amount should pass through unchanged.');
        $this->assertEquals('agent override', $calcData['hoa_source']);
    }

    public function test_agent_hoa_amount_and_quarterly_frequency_normalize_correctly(): void
    {
        $calcData = $this->callBuildCalcData([
            'payment_hoa_fee_amount'    => '900',
            'payment_hoa_fee_frequency' => 'Quarterly',
        ]);

        $this->assertEquals(300.0, $calcData['hoa_monthly'],
            'Quarterly HOA amount of 900 should normalize to 300/month.');
    }

    public function test_agent_hoa_amount_and_annual_frequency_normalize_correctly(): void
    {
        $calcData = $this->callBuildCalcData([
            'payment_hoa_fee_amount'    => '1200',
            'payment_hoa_fee_frequency' => 'Annually',
        ]);

        $this->assertEquals(100.0, $calcData['hoa_monthly'],
            'Annual HOA amount of 1200 should normalize to 100/month.');
    }

    public function test_agent_hoa_override_takes_priority_over_association_fee(): void
    {
        $calcData = $this->callBuildCalcData([
            'payment_hoa_fee_amount'    => '200',
            'payment_hoa_fee_frequency' => 'Monthly',
            'association_fee_amount'    => '500',        // listing field — must be superseded
            'association_fee_frequency' => 'Monthly',
        ]);

        $this->assertEquals(200.0, $calcData['hoa_monthly'],
            'Agent HOA override must take priority over association_fee_amount listing field.');
    }

    // ── monthly insurance override ────────────────────────────────────────────

    public function test_agent_monthly_insurance_override_appears_in_calc_data(): void
    {
        $calcData = $this->callBuildCalcData(['payment_monthly_insurance' => '150']);

        $this->assertEquals(150.0, $calcData['insurance_monthly_override'],
            'Agent payment_monthly_insurance must appear as insurance_monthly_override in $calcData.');
    }

    public function test_insurance_monthly_override_is_null_when_not_set(): void
    {
        $calcData = $this->callBuildCalcData([]);

        $this->assertNull($calcData['insurance_monthly_override'],
            'insurance_monthly_override must be null when no agent override is set.');
    }

    // ── loan term ─────────────────────────────────────────────────────────────

    public function test_agent_loan_term_override_appears_in_calc_data(): void
    {
        $calcData = $this->callBuildCalcData(['payment_loan_term' => '15']);

        $this->assertEquals(15, $calcData['loan_term'],
            'Agent payment_loan_term override must appear in $calcData[loan_term].');
    }

    // ── admin defaults preserved when no overrides ───────────────────────────

    public function test_admin_defaults_apply_when_no_agent_overrides_exist(): void
    {
        $calcData = $this->callBuildCalcData([]);

        $this->assertArrayHasKey('interest_rate', $calcData);
        $this->assertArrayHasKey('down_pct', $calcData);
        $this->assertArrayHasKey('loan_term', $calcData);
        $this->assertArrayHasKey('pmi_rate', $calcData);
        $this->assertArrayHasKey('tax_rate', $calcData);
        $this->assertArrayHasKey('insurance_rate', $calcData);
        $this->assertArrayHasKey('show_buydown_options', $calcData);

        $this->assertIsFloat($calcData['interest_rate']);
        $this->assertIsFloat($calcData['down_pct']);
        $this->assertIsInt($calcData['loan_term']);
    }

    // ── insurance_source ──────────────────────────────────────────────────────

    public function test_insurance_source_is_agent_override_when_monthly_insurance_set(): void
    {
        $calcData = $this->callBuildCalcData(['payment_monthly_insurance' => '150']);

        $this->assertEquals('agent override', $calcData['insurance_source'],
            'insurance_source must be "agent override" when payment_monthly_insurance is set.');
    }

    public function test_insurance_source_is_estimated_when_no_override(): void
    {
        $calcData = $this->callBuildCalcData([]);

        $this->assertEquals('estimated', $calcData['insurance_source'],
            'insurance_source must be "estimated" when no agent insurance override is present.');
    }

    // ── public view page — targeted JS variable assertions ────────────────────

    public function test_seller_listing_view_renders_agent_overrides_as_js_variables(): void
    {
        $user = User::factory()->create();

        $auction = $this->makeAuction($user, [
            'payment_interest_rate'    => '5.75',
            'payment_down_payment_pct' => '15',
            'payment_loan_term'        => '20',
            'payment_show_buydown_options' => '0',
        ]);

        $response = $this->actingAs($user)
            ->get(route('offer.listing.seller.view', $auction->id));

        $response->assertStatus(200);

        $body = $response->getContent();

        // Assert the override values appear as specific JS variable initialisations,
        // not just as arbitrary substrings in the page.
        $this->assertStringContainsString('var RATE_INIT', $body,
            'Calculator JS must declare RATE_INIT.');
        $this->assertStringContainsString('= 5.75;', $body,
            'Agent interest rate override (5.75) must appear as a JS numeric literal in RATE_INIT.');

        $this->assertStringContainsString('var DOWN_PCT_INIT', $body,
            'Calculator JS must declare DOWN_PCT_INIT.');
        $this->assertStringContainsString('= 15;', $body,
            'Agent down payment override (15) must appear as a JS numeric literal in DOWN_PCT_INIT.');

        $this->assertStringContainsString('var TERM_INIT', $body,
            'Calculator JS must declare TERM_INIT.');
        $this->assertStringContainsString('= 20;', $body,
            'Agent loan term override (20) must appear as a JS numeric literal in TERM_INIT.');
    }

    public function test_seller_listing_view_hides_buydown_section_when_disabled(): void
    {
        $user = User::factory()->create();

        $auction = $this->makeAuction($user, [
            'payment_show_buydown_options' => '0',
        ]);

        $response = $this->actingAs($user)
            ->get(route('offer.listing.seller.view', $auction->id));

        $response->assertStatus(200);

        $this->assertStringNotContainsString('calc-adv-toggle', $response->getContent(),
            'When payment_show_buydown_options is 0, the Advanced Options toggle must not be rendered.');
    }

    public function test_seller_listing_view_shows_buydown_section_when_enabled(): void
    {
        $user = User::factory()->create();

        $auction = $this->makeAuction($user, [
            'payment_show_buydown_options' => '1',
        ]);

        $response = $this->actingAs($user)
            ->get(route('offer.listing.seller.view', $auction->id));

        $response->assertStatus(200);

        $this->assertStringContainsString('calc-adv-toggle', $response->getContent(),
            'When payment_show_buydown_options is 1, the Advanced Options toggle must be rendered.');
    }

    public function test_seller_listing_view_shows_agent_override_badge_for_insurance(): void
    {
        $user = User::factory()->create();

        $auction = $this->makeAuction($user, [
            'payment_monthly_insurance' => '175',
        ]);

        $response = $this->actingAs($user)
            ->get(route('offer.listing.seller.view', $auction->id));

        $response->assertStatus(200);

        $this->assertStringContainsString('agent override', $response->getContent(),
            'When payment_monthly_insurance is set, the "agent override" source badge must appear in the page.');
    }

    // ── persistence round-trip via Livewire edit component ───────────────────

    public function test_payment_assumptions_persist_and_reload_on_edit_form(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        $auction = $this->makeAuction($user, [
            'payment_down_payment_pct'      => '15',
            'payment_interest_rate'         => '5.75',
            'payment_loan_term'             => '20',
            'payment_annual_property_taxes' => '4800',
            'payment_monthly_insurance'     => '120',
            'payment_hoa_fee_amount'        => '300',
            'payment_hoa_fee_frequency'     => 'Monthly',
            'payment_pmi_rate'              => '0.5',
            'payment_show_buydown_options'  => '0',
        ]);

        $this->actingAs($user);

        Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->assertSet('payment_down_payment_pct',      '15')
            ->assertSet('payment_interest_rate',         '5.75')
            ->assertSet('payment_loan_term',             '20')
            ->assertSet('payment_annual_property_taxes', '4800')
            ->assertSet('payment_monthly_insurance',     '120')
            ->assertSet('payment_hoa_fee_amount',        '300')
            ->assertSet('payment_hoa_fee_frequency',     'Monthly')
            ->assertSet('payment_pmi_rate',              '0.5')
            ->assertSet('payment_show_buydown_options',  false);
    }
}
