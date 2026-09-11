<?php

namespace Tests\Feature\Product;

use App\Support\Product\ProductContext;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * How the product is chosen, and what cannot choose it.
 *
 * The whole isolation rests on this answer being deterministic and out of a
 * visitor's reach. If a request could talk the application into `combined`, every
 * refusal downstream is theatre.
 */
class ProductContextTest extends TestCase
{
    /** @test */
    public function the_default_is_the_combined_platform(): void
    {
        config(['products.active' => null, 'products.hosts' => []]);

        $this->assertSame(ProductContext::COMBINED, ProductContext::current());
        $this->assertTrue(ProductContext::servesBidYourOffer());
        $this->assertFalse(ProductContext::isBidYourAgent());
    }

    /** @test */
    public function the_shipped_configuration_defaults_to_combined(): void
    {
        // Read from the file rather than from this test's overrides: a default that
        // shipped as `bidyouragent` would silently strip BidYourOffer from production.
        $shipped = require config_path('products.php');

        $this->assertSame('combined', $shipped['default']);
        $this->assertSame([], $shipped['hosts'], 'No host may be mapped by default — this change configures no domain.');
    }

    /** @test */
    public function app_product_selects_bidyouragent(): void
    {
        config(['products.active' => 'bidyouragent']);

        $this->assertSame(ProductContext::BIDYOURAGENT, ProductContext::current());
        $this->assertTrue(ProductContext::isBidYourAgent());
        $this->assertFalse(ProductContext::servesBidYourOffer());
    }

    /** @test */
    public function resolution_is_deterministic_and_holds_no_state(): void
    {
        config(['products.active' => 'bidyouragent']);
        $this->assertSame('bidyouragent', ProductContext::current());
        $this->assertSame('bidyouragent', ProductContext::current());

        // Flipping configuration flips the answer immediately: nothing is memoised,
        // so nothing can survive into the next test or the next request.
        config(['products.active' => 'combined']);
        $this->assertSame('combined', ProductContext::current());
    }

    /** @test */
    public function an_unrecognised_product_throws_rather_than_falling_back(): void
    {
        config(['products.active' => 'bidyouragnet']); // a typo, not a product

        $this->expectException(InvalidArgumentException::class);

        ProductContext::current();
    }

    /** @test */
    public function a_host_header_cannot_override_an_explicit_app_product(): void
    {
        config([
            'products.active' => 'bidyouragent',
            'products.hosts'  => ['attacker.example' => 'combined'],
        ]);

        // An absolute URL, not a Host header: Laravel's test client rebuilds the
        // request from the URI, so a bare header would never reach getHost() and the
        // assertion would pass without proving anything.
        $response = $this->get('http://attacker.example/offer-listing/seller');

        $response->assertNotFound();
    }

    /** @test */
    public function a_query_string_a_cookie_or_a_header_cannot_switch_the_product(): void
    {
        config(['products.active' => 'bidyouragent', 'products.hosts' => []]);

        // Every user-supplied channel someone might reach for. None of them is read.
        $this->get('/offer-listing/seller?product=combined')->assertNotFound();
        $this->withHeader('X-Product', 'combined')->get('/offer-listing/seller')->assertNotFound();
        $this->withUnencryptedCookie('product', 'combined')->get('/offer-listing/seller')->assertNotFound();
        $this->get('http://bidyouroffer.example/offer-listing/seller')->assertNotFound();
    }

    /** @test */
    public function the_host_map_is_consulted_only_when_app_product_is_unset(): void
    {
        config([
            'products.active' => null,
            'products.hosts'  => ['agents.example' => 'bidyouragent'],
        ]);

        $this->get('http://agents.example/offer-listing/seller')->assertNotFound();

        // An unmapped host falls through to the default, which is the combined
        // platform — so BidYourOffer keeps working for every host nobody named.
        $this->get('http://something.example/offer-listing/seller')->assertStatus(302);
    }
}
