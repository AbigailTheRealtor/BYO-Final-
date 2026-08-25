<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The landlord's monthly rent is the number the landlord typed.
 *
 * THE BUG THIS PINS
 * -----------------
 * Runtime verification of the merged quick-import flow produced this:
 *
 *     landlord enters Monthly Rent = 4321
 *       meta maximum_budget        = 4321      ← where it was written
 *       meta desired_rental_amount = 100000    ← the MLS SALE price
 *       published page renders       $100,000 / mo
 *
 * Two independent mistakes lined up. The quick-import rent question stored to
 * `maximum_budget`, which the landlord view reads zero times — an existing
 * AskAi test already records that it "is always empty for landlords". And the
 * importer had filled `desired_rental_amount`, which the view DOES read, with
 * the record's ListPrice. The landlord's own figure was invisible and a condo's
 * sale price was advertised as its monthly rent.
 *
 * These tests assert the published OUTPUT, not just the stored meta, because a
 * meta assertion alone is what let this ship: every value was being saved
 * correctly somewhere, just not anywhere the page looked.
 */
class LandlordQuickImportRentTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'PHPUNIT-RENTFIX-KEY';
    private const MLS = 'PHPUNIT-RENTFIX-MLS';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->user = User::factory()->create(['user_type' => 'seller']);

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => false,
            'mls_media.license_acknowledged'         => false,
            'bridge.dataset'                         => 'phpunit_dataset',
            'bridge.token'                           => 'phpunit-token',
            'bya_beta.bidding_period_enabled'        => false,
        ]);
    }

    /** A SALE record whose list price is 100000 — the shape that caused the bug. */
    private function seedSaleRecord(string $propertyType = 'Residential'): void
    {
        BridgeProperty::create([
            'listing_key'             => self::KEY,
            'listing_id'              => self::MLS,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => $propertyType,
            'property_sub_type'       => 'Condominium',
            'list_price'              => 100000,
            'unparsed_address'        => '2142 BRADFORD STREET UNIT 308',
            'city'                    => 'CLEARWATER',
            'state_or_province'       => 'FL',
            'postal_code'             => '33760',
            'bedrooms_total'          => 1,
            'bathrooms_total_integer' => 1,
            'living_area'             => 480,
            'year_built'              => 1986,
            'raw_json'                => json_encode([
                'ListingKey' => self::KEY,
                'ListingId'  => self::MLS,
                'ListPrice'  => 100000,
            ]),
            'imported_at'             => now(),
        ]);
    }

    private function landlordThroughToTerms(): \Livewire\Testing\TestableLivewire
    {
        return Livewire::actingAs($this->user)
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');
    }

    // =====================================================================
    // The defect, end to end
    // =====================================================================

    public function test_the_rent_the_landlord_entered_is_what_the_published_page_shows(): void
    {
        $this->seedSaleRecord();

        $c = $this->landlordThroughToTerms()
            ->set('terms', ['desired_rental_amount' => '4321'])
            ->set('multiTerms', ['desired_lease_length' => ['1 Year']])
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $c->call('publish');

        $listing = LandlordAgentAuction::find($c->get('listingId'))->fresh();

        // Stored under the key the landlord vocabulary actually uses.
        $this->assertSame('4321', (string) $listing->info('desired_rental_amount'));

        $html = $this->actingAs($this->user)
            ->get(route('offer.listing.landlord.view', $listing->id))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString(
            '4,321',
            $html,
            'the rent the landlord entered must appear on the published page',
        );

        $this->assertStringNotContainsString(
            '$100,000',
            $html,
            'the MLS sale price must never be advertised as this listing rent',
        );
    }

    public function test_the_published_hero_does_not_advertise_the_sale_price_per_month(): void
    {
        $this->seedSaleRecord();

        $c = $this->landlordThroughToTerms()
            ->set('terms', ['desired_rental_amount' => '4321'])
            ->set('multiTerms', ['desired_lease_length' => ['1 Year']])
            ->call('continueToReview');
        $c->call('publish');

        $html = $this->actingAs($this->user)
            ->get(route('offer.listing.landlord.view', $c->get('listingId')))
            ->getContent();

        // The exact string the bug produced.
        $this->assertStringNotContainsString('$100,000<span', $html);
        $this->assertDoesNotMatchRegularExpression('/\$100,000[^<]*\/\s*mo/', $html);
    }

    // =====================================================================
    // The seed rule: a sale price may not pre-fill a rent box
    // =====================================================================

    public function test_a_sale_records_list_price_does_not_pre_fill_the_rent_input(): void
    {
        $this->seedSaleRecord('Residential');

        $c = Livewire::actingAs($this->user)
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $terms = $c->get('terms');

        $this->assertTrue(
            ! isset($terms['desired_rental_amount']) || $terms['desired_rental_amount'] === '',
            'a sale price must not be offered to the landlord as a monthly rent',
        );
    }

    public function test_a_lease_records_price_may_pre_fill_the_rent_input(): void
    {
        $this->seedSaleRecord('Residential Lease');

        $c = Livewire::actingAs($this->user)
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $this->assertSame('100000', (string) ($c->get('terms')['desired_rental_amount'] ?? ''));
    }

    public function test_publish_is_unreachable_until_the_landlord_states_a_rent(): void
    {
        $this->seedSaleRecord();

        // Rent left untouched — with no seed there is nothing to carry a sale price
        // through to publication.
        $c = $this->landlordThroughToTerms()
            ->set('multiTerms', ['desired_lease_length' => ['1 Year']])
            ->call('continueToReview');

        $this->assertSame('terms', $c->get('step'));
        $this->assertStringContainsString('Monthly Rent', $c->get('errorMessage'));
    }

    // =====================================================================
    // Seller is untouched
    // =====================================================================

    public function test_seller_price_behaviour_is_unchanged(): void
    {
        $this->seedSaleRecord();

        $c = Livewire::actingAs($this->user)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        // Seller still stores to maximum_budget, and the list price still seeds it —
        // for a sale record that IS the asking price.
        //
        // The seeded figure now lands on the canonical $maximum_budget property
        // rather than in the $terms bag, because Seller's terms step renders the
        // canonical Sale Terms tab and that tab binds the property directly. The
        // quantity, the source and the storage key are unchanged.
        $this->assertSame('maximum_budget', $c->instance()->priceField());
        $this->assertSame('100000', (string) $c->get('maximum_budget'));

        $c->call('chooseMethod', 'Traditional')->call('continueToTerms')
          ->set('maximum_budget', '777777')
          ->set('offered_financing', ['Cash'])
          ->call('continueToReview')
          ->assertSet('step', 'review');

        $c->call('publish');

        $listing = SellerAgentAuction::find($c->get('listingId'))->fresh();
        $this->assertSame('777777', (string) $listing->info('maximum_budget'));

        $html = $this->actingAs($this->user)
            ->get(route('offer.listing.seller.view', $listing->id))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('777,777', $html);
    }

    public function test_the_landlord_rent_key_matches_what_the_published_view_reads(): void
    {
        // A structural guard: the component's key and the view's key must agree.
        // They disagreed, silently, and that was the whole bug.
        $view = file_get_contents(base_path('resources/views/offer-listing/landlord/view.blade.php'));

        $c = Livewire::actingAs($this->user)->test(LandlordMlsQuickImport::class);
        $key = $c->instance()->priceField();

        $this->assertSame('desired_rental_amount', $key);
        $this->assertStringContainsString(
            $key,
            $view,
            "the landlord view must read the key the quick import writes ({$key})",
        );
        $this->assertArrayHasKey($key, $c->instance()->questionSchema());
    }
}
