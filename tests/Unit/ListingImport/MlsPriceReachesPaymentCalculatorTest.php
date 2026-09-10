<?php

namespace Tests\Unit\ListingImport;

use App\Http\Controllers\SellerOfferListingController;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The Estimated Monthly Payment calculator's opening price.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * The calculator opened at $0 on MLS-imported listings, and the cause was not in
 * the calculator. `buildCalcData()` looked for the asking price under
 * `desired_sale_price`, `purchase_price`, `buy_now_price`, `starting_price` and
 * `reserve_price` — and the Seller Offer Listing wizard writes NONE of them. It
 * stores the asking price as `maximum_budget`, and the MLS quick import writes
 * the same key. So on a listing whose price the MLS had supplied all along, all
 * five lookups missed and the calculator opened at zero.
 *
 * `mls_list_price` is Stellar's own figure, written and refreshed by
 * MlsListingSyncService, and it now leads the chain.
 *
 * `buildCalcData()` is private and is exercised through reflection deliberately:
 * it is a pure array-to-array function, and asserting it directly pins the
 * price-resolution rule without rendering a 2,000-line Blade template that
 * another workstream is concurrently editing.
 */
class MlsPriceReachesPaymentCalculatorTest extends TestCase
{
    // buildCalcData() reads admin rate defaults through get_setting(), which
    // queries the settings table. A schema is required even though the price
    // rule under test is pure.
    use RefreshDatabase;

    private function calcData(array $meta): array
    {
        $method = new ReflectionMethod(SellerOfferListingController::class, 'buildCalcData');
        $method->setAccessible(true);

        return $method->invoke(app(SellerOfferListingController::class), $meta);
    }

    /** @test */
    public function the_authoritative_mls_price_opens_the_calculator(): void
    {
        $data = $this->calcData([Meta::META_LIST_PRICE => '184900']);

        $this->assertSame(184900.0, $data['price']);
        $this->assertSame('from listing', $data['price_source']);
    }

    /** @test */
    public function a_refreshed_mls_price_moves_the_calculator_default(): void
    {
        $this->assertSame(184900.0, $this->calcData([Meta::META_LIST_PRICE => '184900'])['price']);
        $this->assertSame(179900.0, $this->calcData([Meta::META_LIST_PRICE => '179900'])['price']);
    }

    /** @test */
    public function the_mls_price_outranks_the_other_price_keys_on_an_mls_linked_listing(): void
    {
        $data = $this->calcData([
            Meta::META_LIST_PRICE => '179900',
            'desired_sale_price'  => '250000',
            'buy_now_price'       => '300000',
        ]);

        $this->assertSame(179900.0, $data['price'], 'Stellar is authoritative for the price of an MLS-linked listing');
    }

    /**
     * @test
     *
     * The negative control. A manual listing has no `mls_list_price`, and its own
     * price keys must still resolve exactly as they did before this change.
     */
    public function a_manual_listing_resolves_its_price_exactly_as_before(): void
    {
        $this->assertSame(250000.0, $this->calcData(['desired_sale_price' => '250000'])['price']);
        $this->assertSame(300000.0, $this->calcData(['buy_now_price' => '300000'])['price']);
        $this->assertSame(275000.0, $this->calcData(['purchase_price' => '275000'])['price']);

        // And a listing with no price at all still reports no price, rather than
        // acquiring one from somewhere.
        $none = $this->calcData([]);
        $this->assertNull($none['price']);
        $this->assertSame('estimated', $none['price_source']);
    }

    /** @test */
    public function a_zero_or_blank_mls_price_falls_through_rather_than_pinning_the_calculator_to_nothing(): void
    {
        $this->assertSame(
            250000.0,
            $this->calcData([Meta::META_LIST_PRICE => '0', 'desired_sale_price' => '250000'])['price'],
            'A zero MLS price short-circuited the chain'
        );

        $this->assertSame(
            250000.0,
            $this->calcData([Meta::META_LIST_PRICE => '', 'desired_sale_price' => '250000'])['price']
        );
    }
}
