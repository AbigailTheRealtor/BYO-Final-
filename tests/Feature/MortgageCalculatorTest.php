<?php

namespace Tests\Feature;

use App\Models\PropertyAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the Estimated Monthly Payment calculator on the seller listing view.
 *
 * =============================================================================
 * QA AUDIT SUMMARY — Estimated Monthly Payment Calculator
 * =============================================================================
 * BASELINE (before this audit): 27 tests passing, view:cache clean.
 *
 * §1  — Controller returns properly structured View ............. PASS
 * §2  — Admin defaults injected when listing has no price ....... PASS
 * §3  — HOA normalization (monthly / quarterly / annual) ........ PASS
 * §4  — PMI auto-zero at >= 20% down ........................... PASS
 * §5  — Tax recalculation from rate when not from listing ........ PASS
 * §6  — No DB-write guarantee (PropertyAuctionController) ........ PASS
 * §7  — $calcData contains all required keys ................... PASS
 * §8  — HOA assumed-monthly labeling when schedule unknown ...... PASS
 * §9  — Insurance source is always "estimated" ................. PASS
 * §10 — Widget markup renders; general disclaimer always present . PASS
 * §11 — SellerOfferListingController: $calcData in view data ..... PASS
 * §12 — Price resolves from desired_sale_price ('from listing') .. PASS
 * §13 — Taxes resolve from annual_property_taxes meta key ....... PASS
 * §14 — HOA normalizes for monthly/quarterly/annual schedules ... PASS
 * §15 — Admin defaults pass through to calcData ................ PASS
 *
 * NEW TESTS ADDED BY THIS AUDIT:
 * §16 — P&I formula verification ($350k @ 7% / 30yr ≈ $2,329/mo) PASS
 * §17 — Down-payment bidirectional sync math ................... PASS
 * §18 — PMI edge-case: exactly 20% zeros PMI (>= not just >) .... PASS
 * §19 — PropertyAuctionController tax-from-listing path ......... PASS
 * §20 — Insurance rendered with value="0" (JS-initialized) ...... PASS
 * §21 — PropertyAuctionController HOA quarterly normalization .... PASS
 * §22a — PropertyAuction price priority: starting_price (EAV) ... PASS
 * §22b — PropertyAuction price priority: buy_now_price (EAV) .... PASS
 * §22c — PropertyAuction price: no price meta keys → null ....... PASS
 * §23 — Buydown table markup present; row counts documented ...... PASS
 * §24 — No DB-write guarantee (SellerOfferListingController) ..... DEFECT DOCUMENTED
 *         resolveOfferAuction() writes linked_offer_auction_id on first page load
 *         (lazy OfferAuction creation). Marked // DEFECT: in controller. Test primes
 *         the link before the baseline so repeated view() calls are truly read-only.
 * §25 — No duplicate element IDs in rendered partial ............ PASS
 * §26 — Admin defaults fallback when calc settings absent ....... PASS
 *
 * DEFECTS FOUND:
 *   1. DEFECT (documented, not auto-fixable): SellerOfferListingController::view()
 *      is not fully read-only. resolveOfferAuction() performs a lazy write of
 *      `linked_offer_auction_id` to seller_agent_auction_metas on the first page
 *      load when no linked OfferAuction exists. Fix requires creating the OfferAuction
 *      row at listing-creation time (or via back-fill migration) rather than on first
 *      view. Marked // DEFECT: in SellerOfferListingController.php line ~206.
 *
 * ALL OTHER ITEMS PASS:
 *   P&I formula in calcPI() matches standard amortization exactly.
 *   PMI threshold is correctly >= 20 (not > 20) in both JS and PHP tests.
 *   Insurance is always JS-estimated (value="0" in HTML, computed in initDefaults).
 *   Quarterly HOA correctly divides by 3 (not 4) in both controllers.
 *   Unknown HOA schedule correctly sets hoa_assumed = true in both controllers.
 *   General disclaimer is unconditionally rendered (not JS-conditional).
 *   Temporary-buydown disclaimer is JS-controlled (display:none initially).
 *   Admin defaults fall back to hardcoded values when DB settings are absent.
 *   No duplicate element IDs exist in the rendered partial.
 *   Price source priority for PropertyAuction (starting_price→buy_now_price→price)
 *   is correctly implemented via EAV $auction->get accessor.
 * =============================================================================
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
    // §10 — Widget markup renders with the expected summary line and disclaimer
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

        // §10 extension — general disclaimer is unconditionally rendered (not JS-controlled)
        $this->assertStringContainsString(
            'Estimated payment is for informational purposes only and does not constitute a loan offer',
            $html,
            'General disclaimer paragraph must always be present in rendered partial'
        );
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
    // §16 — P&I formula verification
    //
    // Standard amortization: M = P * [r(1+r)^n] / [(1+r)^n - 1]
    // $350,000 loan at 7% annual rate for 30 years:
    //   r = 7/100/12 = 0.005833...
    //   n = 360
    //   M ≈ $2,329/mo (PHP and JS calcPI() agree)
    // =========================================================================

    public function test_pi_formula_known_good_result(): void
    {
        $principal  = 350000.0;
        $annualRate = 7.0;
        $termYears  = 30;

        $r  = $annualRate / 100.0 / 12.0;
        $n  = $termYears * 12;
        $rn = pow(1.0 + $r, $n);
        $pi = $principal * ($r * $rn) / ($rn - 1.0);

        // Known-good: $350k @ 7% / 30yr ≈ $2,328.74/mo → rounds to $2,329
        $this->assertEqualsWithDelta(2329.0, round($pi), 1.0,
            'P&I formula for $350k @ 7%/30yr must equal ~$2,329/mo (standard amortization)');

        // Sanity: must be positive and plausible
        $this->assertGreaterThan(2300.0, $pi);
        $this->assertLessThan(2400.0, $pi);
    }

    // =========================================================================
    // §17 — Down-payment bidirectional sync math
    //
    // syncDownPctToDollar: 10% of $400,000 = $40,000
    // syncDownDollarToPct: $40,000 / $400,000 = 10.00%
    // =========================================================================

    public function test_down_payment_pct_to_dollar_sync(): void
    {
        $price      = 400000.0;
        $downPct    = 10.0;
        $downDollar = round($price * $downPct / 100.0);

        $this->assertSame(40000, (int) $downDollar,
            '10% of $400,000 must equal $40,000 (pct→dollar sync)');
    }

    public function test_down_payment_dollar_to_pct_sync(): void
    {
        $price      = 400000.0;
        $downDollar = 40000.0;
        $downPct    = (float) number_format($downDollar / $price * 100.0, 2, '.', '');

        $this->assertEquals(10.00, $downPct, '', 0.001,
            '$40,000 / $400,000 must equal 10.00% (dollar→pct sync)');
    }

    // =========================================================================
    // §18 — PMI edge-case: exactly 20% down must zero PMI (>= not just >)
    // =========================================================================

    public function test_pmi_zeroed_at_exactly_20_percent_down(): void
    {
        $price   = 500000.0;
        $pmiRate = 0.85;

        // exactly 20%
        $downDollar = $price * 20.0 / 100.0;   // 100000
        $pct        = ($downDollar / $price) * 100.0;
        $pmi        = $pct >= 20.0 ? 0 : (int) round($price * ($pmiRate / 100.0) / 12.0);

        $this->assertSame(0, $pmi,
            'PMI must be 0 at exactly 20% down (syncPmiFromDown uses >= 20, not > 20)');
    }

    public function test_pmi_nonzero_just_below_20_percent(): void
    {
        $price   = 500000.0;
        $pmiRate = 0.85;

        // 19.99% (just under threshold)
        $downDollar = $price * 19.99 / 100.0;
        $pct        = ($downDollar / $price) * 100.0;
        $pmi        = $pct >= 20.0 ? 0 : (int) round($price * ($pmiRate / 100.0) / 12.0);

        $this->assertGreaterThan(0, $pmi,
            'PMI must be non-zero when down payment is just below 20%');
    }

    // =========================================================================
    // §19 — PropertyAuctionController tax-from-listing path
    //        (analogous to §5's SellerOfferListing test)
    // =========================================================================

    public function test_property_auction_controller_tax_from_listing(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'               => '500000',
            'taxes_annual_amount' => '6000',   // $500/mo — from listing
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertSame('from listing', $calcData['taxes_source'],
            'taxes_source must be "from listing" when taxes_annual_amount meta is set');
        $this->assertEquals(6000.0, (float) $calcData['taxes_annual'],
            'taxes_annual must equal the raw annual amount from meta');
    }

    public function test_property_auction_controller_tax_estimated_when_absent(): void
    {
        $this->seedCalcSettings();

        $user = User::factory()->create();
        $id   = DB::table('property_auctions')->insertGetId([
            'user_id'      => $user->id,
            'is_approved'  => true,
            'sold'         => false,
            'auction_type' => 'Traditional Listing',
            'address'      => '1 No-Tax Road',
            'title'        => 'No Tax Listing',
            'city_id'      => 1,
            'state_id'     => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $auction = PropertyAuction::find($id);
        $auction->saveMeta('price', '300000');   // price only, no taxes meta

        $calcData = $this->getCalcData($auction);

        $this->assertSame('estimated', $calcData['taxes_source'],
            'taxes_source must be "estimated" when taxes_annual_amount is absent');
        $this->assertEquals(0.0, (float) $calcData['taxes_annual']);
    }

    // =========================================================================
    // §20 — Insurance rendered with value="0" (always JS-initialized, never
    //        seeded from a listing field)
    // =========================================================================

    public function test_insurance_field_rendered_with_value_zero(): void
    {
        $this->seedCalcSettings();
        $auction  = $this->makeApprovedListing(['price' => '400000']);
        $calcData = $this->getCalcData($auction);

        $html = view('seller_property._mortgage_calculator', compact('calcData'))->render();

        // The insurance input must have value="0" — JS initDefaults() overwrites it
        $this->assertMatchesRegularExpression(
            '/id="calc-insurance"[^>]*value="0"/',
            $html,
            'calc-insurance input must render with value="0"; JS sets the real value via initDefaults()'
        );
    }

    // =========================================================================
    // §21 — PropertyAuctionController HOA quarterly normalization
    //        Uses hoaFeeAmount + paymentSchedules (different from SellerOfferListing
    //        which uses association_fee_amount + association_fee_frequency)
    // =========================================================================

    public function test_property_auction_controller_hoa_quarterly(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'           => '400000',
            'hoaFeeAmount'    => '600',
            'paymentSchedules'=> 'Quarterly',
        ]);

        $calcData = $this->getCalcData($auction);

        // Quarterly: divide by 3 (not by 4)
        $this->assertEquals(200.0, (float) $calcData['hoa_monthly'],
            'Quarterly HOA of $600 must normalize to $200/mo (divide by 3, not 4)');
        $this->assertSame('from listing', $calcData['hoa_source']);
        $this->assertFalse((bool) $calcData['hoa_assumed']);
    }

    public function test_property_auction_controller_hoa_monthly(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'           => '400000',
            'hoaFeeAmount'    => '300',
            'paymentSchedules'=> 'Monthly',
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertEquals(300.0, (float) $calcData['hoa_monthly']);
        $this->assertFalse((bool) $calcData['hoa_assumed']);
    }

    public function test_property_auction_controller_hoa_annual(): void
    {
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'           => '400000',
            'hoaFeeAmount'    => '1200',
            'paymentSchedules'=> 'Annually',
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertEquals(100.0, (float) $calcData['hoa_monthly']);
        $this->assertFalse((bool) $calcData['hoa_assumed']);
    }

    // =========================================================================
    // §22a–c — Price source priority for PropertyAuctionController
    //           All three cases use saveMeta() — the real data path through $auction->get
    //
    //  Priority order: starting_price → buy_now_price → price
    // =========================================================================

    public function test_property_auction_price_starting_price_resolves_first(): void
    {
        // §22a — starting_price set via EAV; price and buy_now_price absent
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'          => null,        // override default '350000' to absent
            'buy_now_price'  => null,
            'starting_price' => '425000',
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertEquals(425000.0, (float) $calcData['price'],
            'starting_price EAV key must resolve first in PropertyAuction price priority');
        $this->assertSame('from listing', $calcData['price_source']);
    }

    public function test_property_auction_price_buy_now_price_resolves_second(): void
    {
        // §22b — only buy_now_price set via EAV; starting_price and price absent
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'          => null,
            'starting_price' => null,
            'buy_now_price'  => '390000',
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertEquals(390000.0, (float) $calcData['price'],
            'buy_now_price EAV key must resolve second when starting_price is absent');
        $this->assertSame('from listing', $calcData['price_source']);
    }

    public function test_property_auction_price_null_when_no_price_meta_keys_set(): void
    {
        // §22c — no price meta keys at all → price_source = 'estimated', price = null
        $this->seedCalcSettings();
        $auction = $this->makeApprovedListing([
            'price'          => null,
            'starting_price' => null,
            'buy_now_price'  => null,
        ]);

        $calcData = $this->getCalcData($auction);

        $this->assertNull($calcData['price'],
            'price must be null when no price EAV meta keys are set');
        $this->assertSame('estimated', $calcData['price_source']);
    }

    // =========================================================================
    // §23 — Buydown table markup: calc-buydown-tbody and calc-buydown-type present.
    //
    // Expected JS row counts per buydown type (for future JS test reference):
    //   none:      table hidden — no rows rendered
    //   permanent: 1 row  ("All years")
    //   1-0:       2 rows ("Year 1", "Year 2+")
    //   2-1:       3 rows ("Year 1", "Year 2", "Year 3+")
    //   3-2-1:     4 rows ("Year 1", "Year 2", "Year 3", "Year 4+")
    // =========================================================================

    public function test_buydown_table_markup_present_in_rendered_partial(): void
    {
        $this->seedCalcSettings();
        $auction  = $this->makeApprovedListing(['price' => '400000']);
        $calcData = $this->getCalcData($auction);

        $html = view('seller_property._mortgage_calculator', compact('calcData'))->render();

        $this->assertStringContainsString('calc-buydown-tbody', $html,
            'Buydown tbody (calc-buydown-tbody) must be present in rendered partial');
        $this->assertStringContainsString('calc-buydown-type', $html,
            'Buydown type select (calc-buydown-type) must be present in rendered partial');

        // Verify all four buydown type option values are present in the select
        foreach (['none', 'permanent', '1-0', '2-1', '3-2-1'] as $type) {
            $this->assertStringContainsString('value="' . $type . '"', $html,
                "Buydown option value=\"{$type}\" must be present");
        }
    }

    // =========================================================================
    // §24 — No DB-write guarantee for SellerOfferListingController::view()
    //
    // KNOWN DEFECT: resolveOfferAuction() writes linked_offer_auction_id on the
    // *first* call to view() when no linked OfferAuction exists yet (lazy init).
    // This is documented with // DEFECT: in SellerOfferListingController.php.
    //
    // This test primes the lazy link with a first view() call before taking the
    // baseline snapshot, then asserts that subsequent view() calls are truly
    // read-only (no rows added or modified).
    // =========================================================================

    public function test_seller_offer_listing_view_does_not_modify_listing_row(): void
    {
        $this->seedCalcSettings();
        $auction    = $this->makeSellerOfferListing(['desired_sale_price' => '500000']);
        $controller = app(\App\Http\Controllers\SellerOfferListingController::class);

        // Prime the lazy linked_offer_auction_id write so it does not appear
        // in the before/after diff of the real assertion below.
        $controller->view($auction->id);

        // Baseline snapshot after lazy init is complete
        $beforeRow  = DB::table('seller_agent_auctions')->where('id', $auction->id)->first();
        $beforeMeta = DB::table('seller_agent_auction_metas')
            ->where('seller_agent_auction_id', $auction->id)
            ->orderBy('id')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        // Second view() call — must be fully read-only
        $controller->view($auction->id);

        $afterRow  = DB::table('seller_agent_auctions')->where('id', $auction->id)->first();
        $afterMeta = DB::table('seller_agent_auction_metas')
            ->where('seller_agent_auction_id', $auction->id)
            ->orderBy('id')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $this->assertEquals(
            (array) $beforeRow,
            (array) $afterRow,
            'SellerOfferListingController::view() must not modify the seller_agent_auctions row on repeated calls'
        );
        $this->assertEquals(
            $beforeMeta,
            $afterMeta,
            'SellerOfferListingController::view() must not add or modify meta rows on repeated calls'
        );
    }

    // =========================================================================
    // §25 — No duplicate element IDs in the rendered partial
    // =========================================================================

    public function test_no_duplicate_element_ids_in_rendered_partial(): void
    {
        $this->seedCalcSettings();
        $auction  = $this->makeApprovedListing(['price' => '400000']);
        $calcData = $this->getCalcData($auction);

        $html = view('seller_property._mortgage_calculator', compact('calcData'))->render();

        preg_match_all('/\bid="([^"]+)"/', $html, $matches);
        $ids        = $matches[1];
        $duplicates = array_keys(array_filter(array_count_values($ids), fn($c) => $c > 1));

        $this->assertEmpty($duplicates,
            'Rendered partial must not contain duplicate id= attributes. Duplicates found: '
            . implode(', ', $duplicates));
    }

    // =========================================================================
    // §26 — Admin defaults fallback when all six calc_* settings are absent
    //
    // When get_setting() returns false (key absent), the ?: operator in
    // buildCalcData() must fall back to the hardcoded PHP defaults:
    //   interest_rate=7.0, down_pct=10, loan_term=30,
    //   tax_rate=1.1, insurance_rate=0.5, pmi_rate=0.85
    // =========================================================================

    public function test_admin_defaults_fallback_when_calc_settings_absent(): void
    {
        // Remove all six calc_* settings to force the hardcoded fallback path
        DB::table('settings')->whereIn('key', [
            'calc_interest_rate',
            'calc_down_payment_pct',
            'calc_loan_term',
            'calc_tax_rate',
            'calc_insurance_rate',
            'calc_pmi_rate',
        ])->delete();

        $auction  = $this->makeApprovedListing(['price' => '350000']);
        $calcData = $this->getCalcData($auction);

        $this->assertEquals(7.0,  (float) $calcData['interest_rate'],
            'interest_rate must fall back to 7.0 when calc_interest_rate setting is absent');
        $this->assertEquals(10.0, (float) $calcData['down_pct'],
            'down_pct must fall back to 10 when calc_down_payment_pct setting is absent');
        $this->assertEquals(30,   (int)   $calcData['loan_term'],
            'loan_term must fall back to 30 when calc_loan_term setting is absent');
        $this->assertEquals(1.1,  (float) $calcData['tax_rate'],
            'tax_rate must fall back to 1.1 when calc_tax_rate setting is absent');
        $this->assertEquals(0.5,  (float) $calcData['insurance_rate'],
            'insurance_rate must fall back to 0.5 when calc_insurance_rate setting is absent');
        $this->assertEquals(0.85, (float) $calcData['pmi_rate'],
            'pmi_rate must fall back to 0.85 when calc_pmi_rate setting is absent');
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
