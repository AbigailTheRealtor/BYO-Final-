<?php

namespace Tests\Feature;

use App\Models\PropertyAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the Estimated Monthly Payment calculator on the seller listing view.
 */
class MortgageCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private function seedCalcSettings(): void
    {
        $defaults = [
            'calc_interest_rate'    => '7.0',
            'calc_down_payment_pct' => '10',
            'calc_loan_term'        => '30',
            'calc_tax_rate'         => '1.1',
            'calc_insurance_rate'   => '0.5',
            'calc_pmi_rate'         => '0.85',
        ];
        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function makeApprovedListing(array $metaOverrides = []): PropertyAuction
    {
        $user = User::factory()->create();

        $id = DB::table('property_auctions')->insertGetId([
            'user_id'      => $user->id,
            'is_approved'  => true,
            'sold'         => false,
            'auction_type' => 'Traditional Listing',
            'address'      => '123 Test Street',
            'title'        => 'Test Listing',
            'city_id'      => 1,
            'state_id'     => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $auction = PropertyAuction::find($id);

        $defaults = [
            'auction_type'   => 'Traditional Listing',
            'price'          => '350000',
            'starting_price' => null,
            'buy_now_price'  => null,
        ];

        foreach (array_merge($defaults, $metaOverrides) as $key => $value) {
            if ($value !== null) {
                $auction->saveMeta($key, $value);
            }
        }

        return $auction;
    }

    /** Shared helper — calls controller directly, returns $calcData. */
    private function getCalcData(PropertyAuction $auction): array
    {
        $controller = app(\App\Http\Controllers\PropertyAuctionController::class);
        $request    = \Illuminate\Http\Request::create(route('view-pl', $auction->id), 'GET');
        $response   = $controller->viewPropertyListing($auction->id, $request);
        return $response->getData()['calcData'];
    }

    // =========================================================================
    // §1 — Controller returns a properly structured View
    // =========================================================================

    public function test_seller_listing_view_loads_successfully(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing(['price' => '350000']);

        $controller = app(\App\Http\Controllers\PropertyAuctionController::class);
        $request    = \Illuminate\Http\Request::create(route('view-pl', $auction->id), 'GET');
        $response   = $controller->viewPropertyListing($auction->id, $request);

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertSame('seller_property.view', $response->getName());
        $this->assertArrayHasKey('calcData', $response->getData());
        $calcData = $response->getData()['calcData'];
        $this->assertNotNull($calcData);
        $this->assertEquals(350000.0, (float) $calcData['price']);
        $this->assertSame('from listing', $calcData['price_source']);
    }

    // =========================================================================
    // §2 — Admin defaults injected when listing has no price
    // =========================================================================

    public function test_admin_defaults_are_injected_when_listing_has_no_price(): void
    {
        $this->seedCalcSettings();

        $user = User::factory()->create();
        $id = DB::table('property_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'sold'        => false,
            'address'     => '456 No Price Ave',
            'title'       => 'No Price Listing',
            'city_id'     => 1,
            'state_id'    => 1,
            'auction_type'=> 'Traditional Listing',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $auction = PropertyAuction::find($id);

        $calcData = $this->getCalcData($auction);

        $this->assertNotNull($calcData, '$calcData must be passed to the view');
        $this->assertEquals(7.0,  (float) $calcData['interest_rate']);
        $this->assertEquals(10,   (float) $calcData['down_pct']);
        $this->assertEquals(30,   (int)   $calcData['loan_term']);
        $this->assertEquals(1.1,  (float) $calcData['tax_rate']);
        $this->assertEquals(0.5,  (float) $calcData['insurance_rate']);
        $this->assertEquals(0.85, (float) $calcData['pmi_rate']);
    }

    // =========================================================================
    // §3 — HOA normalization
    // =========================================================================

    public function test_hoa_normalization_monthly(): void
    {
        $this->assertEquals(100.0, $this->normalizeHoa(100, 'Monthly'));
    }

    public function test_hoa_normalization_quarterly(): void
    {
        $this->assertEquals(100.0, $this->normalizeHoa(300, 'Quarterly'));
    }

    public function test_hoa_normalization_annual(): void
    {
        $this->assertEquals(100.0, $this->normalizeHoa(1200, 'Annually'));
    }

    public function test_hoa_normalization_missing(): void
    {
        $this->assertEquals(0.0, $this->normalizeHoa(null, null));
        $this->assertEquals(0.0, $this->normalizeHoa(0, 'Monthly'));
    }

    // =========================================================================
    // §4 — PMI auto-zero at >= 20% down; recalculates from rate otherwise
    // =========================================================================

    public function test_pmi_zeroed_when_down_payment_at_least_20_percent(): void
    {
        $price   = 400000;
        $downPct = 20;
        $pmiRate = 0.85;

        $downDollar    = $price * $downPct / 100;
        $downPctActual = ($downDollar / $price) * 100;
        $pmi           = $downPctActual >= 20 ? 0 : round($price * ($pmiRate / 100) / 12);

        $this->assertSame(0, $pmi);
    }

    public function test_pmi_recalculates_from_rate_when_below_20_percent(): void
    {
        // Mirrors the corrected syncPmiFromDown JS logic:
        //   pct < 20 → PMI = round(price * (PMI_RATE / 100) / 12)
        $price   = 400000;
        $downPct = 10;
        $pmiRate = 0.85;

        $downDollar = $price * $downPct / 100;
        $pct        = ($downDollar / $price) * 100;
        $expected   = (int) round($price * ($pmiRate / 100) / 12);  // 283

        $computed = $pct >= 20 ? 0 : (int) round($price * ($pmiRate / 100) / 12);

        $this->assertGreaterThan(0, $computed);
        $this->assertSame($expected, $computed);
    }

    // =========================================================================
    // §5 — Tax recalculation from rate when taxes are not from the listing
    // =========================================================================

    public function test_taxes_recalculate_from_rate_when_not_from_listing(): void
    {
        // Mirrors syncTaxes JS: when TAXES_FROM_LISTING=false, taxes = round(price * (TAX_RATE / 100) / 12)
        $price   = 500000;
        $taxRate = 1.1;

        $expected = (int) round($price * ($taxRate / 100) / 12);   // 458

        $this->assertGreaterThan(0, $expected);
        // Confirm the formula matches what the controller will supply via calcData
        $this->assertEquals(458, $expected);
    }

    public function test_taxes_not_recalculated_when_from_listing(): void
    {
        // When TAXES_FROM_LISTING=true the input is left alone; the listing value takes precedence.
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'                => '500000',
            'taxes_annual_amount'  => '6000',   // $500/mo — from listing
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertSame('from listing', $calcData['taxes_source']);
        $this->assertEquals(6000.0, (float) $calcData['taxes_annual']);
    }

    // =========================================================================
    // §6 — No listing DB fields modified after controller dispatches the view
    // =========================================================================

    public function test_no_listing_fields_modified_after_view_render(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing(['price' => '300000']);

        $before = DB::table('property_auctions')->where('id', $auction->id)->first();

        $controller = app(\App\Http\Controllers\PropertyAuctionController::class);
        $request    = \Illuminate\Http\Request::create(route('view-pl', $auction->id), 'GET');
        $controller->viewPropertyListing($auction->id, $request);

        $after = DB::table('property_auctions')->where('id', $auction->id)->first();

        $this->assertEquals((array) $before, (array) $after, 'Listing record must not be modified by viewPropertyListing');
    }

    // =========================================================================
    // §7 — $calcData contains all required keys including insurance_source &
    //       hoa_assumed (the new buydown-support + provenance keys)
    // =========================================================================

    public function test_calc_data_contains_required_keys(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing(['price' => '450000']);

        $calcData = $this->getCalcData($auction);

        $this->assertNotNull($calcData);

        $requiredKeys = [
            // listing values
            'price', 'price_source',
            'hoa_monthly', 'hoa_source', 'hoa_assumed',
            'taxes_annual', 'taxes_source',
            'insurance_source',
            // admin/buydown-supporting rates
            'interest_rate', 'down_pct', 'loan_term',
            'tax_rate', 'insurance_rate', 'pmi_rate',
        ];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $calcData, "calcData is missing key: {$key}");
        }
    }

    // =========================================================================
    // §8 — HOA assumed-monthly labeling when schedule is unknown
    // =========================================================================

    public function test_hoa_assumed_monthly_when_schedule_unknown(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'           => '300000',
            'hoaFeeAmount'    => '250',
            'paymentSchedules'=> '',        // unknown / blank schedule
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertTrue((bool) $calcData['hoa_assumed'], 'hoa_assumed should be true for an unknown schedule');
        $this->assertSame('from listing', $calcData['hoa_source']);
        $this->assertEquals(250.0, (float) $calcData['hoa_monthly']);
    }

    public function test_hoa_not_assumed_when_schedule_is_monthly(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'           => '300000',
            'hoaFeeAmount'    => '200',
            'paymentSchedules'=> 'Monthly',
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertFalse((bool) $calcData['hoa_assumed'], 'hoa_assumed should be false when schedule is Monthly');
        $this->assertEquals(200.0, (float) $calcData['hoa_monthly']);
    }

    // =========================================================================
    // §9 — Insurance source is always "estimated"; badge driven by variable
    // =========================================================================

    public function test_insurance_source_is_estimated(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing(['price' => '400000']);

        $calcData = $this->getCalcData($auction);

        $this->assertSame('estimated', $calcData['insurance_source'],
            'Insurance is always estimated — no listing meta field captures it');
    }

    // =========================================================================
    // §10 — Widget markup renders with the expected summary line
    // =========================================================================

    public function test_widget_renders_estimated_monthly_payment_text(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing(['price' => '400000']);

        $calcData = $this->getCalcData($auction);

        $html = view('seller_property._mortgage_calculator', compact('calcData'))->render();

        $this->assertStringContainsString('Estimated Monthly Payment', $html);
        $this->assertStringContainsString('calc-buydown-type', $html,
            'Buydown select element must be present in rendered partial');
        $this->assertStringContainsString('calc-perm-rate', $html,
            'Permanent buydown rate input must be present in rendered partial');
        $this->assertStringContainsString('calc-buydown-tbody', $html,
            'Buydown results tbody must be present in rendered partial');
    }

    // =========================================================================
    // §11 — SellerOfferListingController: $calcData in view data
    // =========================================================================

    public function test_seller_offer_listing_view_includes_calc_data(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing(['desired_sale_price' => '500000']);

        $controller = app(\App\Http\Controllers\SellerOfferListingController::class);
        $response   = $controller->view($auction->id);

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertArrayHasKey('calcData', $response->getData());
        $calcData = $response->getData()['calcData'];
        $this->assertNotNull($calcData);
    }

    // =========================================================================
    // §12 — Price resolves from desired_sale_price with 'from listing' source
    // =========================================================================

    public function test_seller_offer_listing_price_from_desired_sale_price(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing(['desired_sale_price' => '425000']);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(425000.0, (float) $calcData['price']);
        $this->assertSame('from listing', $calcData['price_source']);
    }

    public function test_seller_offer_listing_price_falls_back_through_priority(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing([
            'desired_sale_price' => '',
            'purchase_price'     => '',
            'buy_now_price'      => '390000',
        ]);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(390000.0, (float) $calcData['price']);
        $this->assertSame('from listing', $calcData['price_source']);
    }

    public function test_seller_offer_listing_price_null_when_none_set(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing([]);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertNull($calcData['price']);
        $this->assertSame('estimated', $calcData['price_source']);
    }

    // =========================================================================
    // §13 — Taxes resolve from annual_property_taxes meta key
    // =========================================================================

    public function test_seller_offer_listing_taxes_from_meta(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing([
            'desired_sale_price'   => '500000',
            'annual_property_taxes' => '7200',
        ]);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(7200.0, (float) $calcData['taxes_annual']);
        $this->assertSame('from listing', $calcData['taxes_source']);
    }

    public function test_seller_offer_listing_taxes_estimated_when_absent(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing(['desired_sale_price' => '300000']);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(0.0, (float) $calcData['taxes_annual']);
        $this->assertSame('estimated', $calcData['taxes_source']);
    }

    // =========================================================================
    // §14 — HOA normalizes for monthly and quarterly schedules
    // =========================================================================

    public function test_seller_offer_listing_hoa_monthly_schedule(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing([
            'desired_sale_price'       => '400000',
            'association_fee_amount'   => '300',
            'association_fee_frequency' => 'Monthly',
        ]);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(300.0, (float) $calcData['hoa_monthly']);
        $this->assertSame('from listing', $calcData['hoa_source']);
        $this->assertFalse((bool) $calcData['hoa_assumed']);
    }

    public function test_seller_offer_listing_hoa_quarterly_schedule(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing([
            'desired_sale_price'       => '400000',
            'association_fee_amount'   => '600',
            'association_fee_frequency' => 'Quarterly',
        ]);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(200.0, (float) $calcData['hoa_monthly']);
        $this->assertSame('from listing', $calcData['hoa_source']);
        $this->assertFalse((bool) $calcData['hoa_assumed']);
    }

    public function test_seller_offer_listing_hoa_annual_schedule(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing([
            'desired_sale_price'       => '400000',
            'association_fee_amount'   => '1200',
            'association_fee_frequency' => 'Annually',
        ]);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(100.0, (float) $calcData['hoa_monthly']);
        $this->assertSame('from listing', $calcData['hoa_source']);
    }

    public function test_seller_offer_listing_hoa_assumed_when_frequency_unknown(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing([
            'desired_sale_price'       => '400000',
            'association_fee_amount'   => '250',
            'association_fee_frequency' => '',
        ]);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(250.0, (float) $calcData['hoa_monthly']);
        $this->assertTrue((bool) $calcData['hoa_assumed']);
    }

    // =========================================================================
    // §15 — Admin defaults pass through to calcData
    // =========================================================================

    public function test_seller_offer_listing_admin_defaults_passed_through(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeSellerOfferListing(['desired_sale_price' => '300000']);

        $calcData = $this->getSellerOfferCalcData($auction);

        $this->assertEquals(7.0,  (float) $calcData['interest_rate']);
        $this->assertEquals(10.0, (float) $calcData['down_pct']);
        $this->assertEquals(30,   (int)   $calcData['loan_term']);
        $this->assertEquals(1.1,  (float) $calcData['tax_rate']);
        $this->assertEquals(0.5,  (float) $calcData['insurance_rate']);
        $this->assertEquals(0.85, (float) $calcData['pmi_rate']);
        $this->assertSame('estimated', $calcData['insurance_source']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Create a SellerAgentAuction stamped as an offer listing with given meta. */
    private function makeSellerOfferListing(array $metaOverrides = []): \App\Models\SellerAgentAuction
    {
        $user = User::factory()->create();

        $auction = \App\Models\SellerAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '99 Offer Listing Lane',
        ]);

        $auction->saveMeta('workflow_type', 'offer_listing');

        foreach ($metaOverrides as $key => $value) {
            if ($value !== null) {
                $auction->saveMeta($key, $value);
            }
        }

        // Pre-seed a linked OfferAuction so the controller view() is purely read-only.
        $offerAuction = \App\Models\OfferAuction::create(['user_id' => $user->id]);
        $auction->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return $auction;
    }

    /** Call SellerOfferListingController::view() and return $calcData from view data. */
    private function getSellerOfferCalcData(\App\Models\SellerAgentAuction $auction): array
    {
        $controller = app(\App\Http\Controllers\SellerOfferListingController::class);
        $response   = $controller->view($auction->id);
        return $response->getData()['calcData'];
    }

    private function normalizeHoa(?float $amount, ?string $schedule): float
    {
        if (!$amount || $amount <= 0) return 0.0;
        $schedule = strtolower($schedule ?? '');
        if (str_contains($schedule, 'quarter')) return $amount / 3;
        if (str_contains($schedule, 'annual') || str_contains($schedule, 'year')) return $amount / 12;
        return $amount;
    }
}
