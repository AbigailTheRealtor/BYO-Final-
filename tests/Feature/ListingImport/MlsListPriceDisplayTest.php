<?php

namespace Tests\Feature\ListingImport;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WHAT THE SELLER AND LANDLORD LISTING PAGES SAY ABOUT PRICE.
 *
 * The page carries two prices that are not the same claim — Stellar's list
 * price, and the user's own BidYourOffer term — and it used to print one
 * unlabelled number resolved from whichever key a fallback chain reached first.
 * A reader could not tell which they were looking at.
 *
 * These tests render the real templates through the real controllers, because
 * the failure being guarded against is a label landing next to the wrong figure,
 * and that is not visible from the resolver alone. The resolver's own rules are
 * pinned separately by Tests\Unit\Listing\ListingPriceDisplayTest.
 *
 * The worked example throughout is the contract listing:
 *   6817 STONES THROW CIRCLE N UNIT 17208 — Stellar ListPrice $184,900.
 */
class MlsListPriceDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const STONES_THROW_PRICE = '184900';

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $meta */
    private function sellerListing(array $meta): SellerAgentAuction
    {
        $user = User::factory()->create();

        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '6817 STONES THROW CIRCLE N UNIT 17208',
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        $this->applyMeta($listing, $meta);

        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    /** @param array<string,mixed> $meta */
    private function landlordListing(array $meta): LandlordAgentAuction
    {
        $user = User::factory()->create();

        $listing = LandlordAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'title'       => 'Rental at Stones Throw',
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        $this->applyMeta($listing, $meta);

        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    /** @param array<string,mixed> $meta */
    private function applyMeta(object $listing, array $meta): void
    {
        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }
    }

    /** The MLS provenance an imported listing carries. */
    private function linked(array $extra = []): array
    {
        return array_merge([
            Meta::META_LISTING_KEY => 'STONES-THROW-KEY',
            Meta::META_MLS_NUMBER  => 'TB8300001',
            Meta::META_PROVIDER    => 'bridge',
        ], $extra);
    }

    private function sellerPage(SellerAgentAuction $listing): string
    {
        return $this->get(route('offer.listing.seller.view', ['id' => $listing->id]))
            ->assertStatus(200)
            ->getContent();
    }

    private function landlordPage(LandlordAgentAuction $listing): string
    {
        return $this->get(route('offer.listing.landlord.view', ['id' => $listing->id]))
            ->assertStatus(200)
            ->getContent();
    }

    // ── A. The MLS list price is displayed, and labelled as the MLS's ────────

    /** @test */
    public function an_mls_linked_seller_listing_displays_the_stellar_list_price(): void
    {
        $html = $this->sellerPage($this->sellerListing($this->linked([
            Meta::META_LIST_PRICE => self::STONES_THROW_PRICE,
        ])));

        $this->assertStringContainsString('MLS List Price', $html);
        $this->assertStringContainsString('$184,900', $html);
    }

    // ── B. Both prices visible, both labelled ────────────────────────────────

    /**
     * @test
     *
     * The seller's own asking price lives in `maximum_budget` — the key behind
     * the wizard's "Desired Sale Price" input. When it differs from Stellar's
     * figure BOTH appear, each under its own heading, and neither is presented
     * as the other.
     */
    public function both_the_mls_price_and_the_sellers_own_term_are_visible_and_labelled(): void
    {
        $html = $this->sellerPage($this->sellerListing($this->linked([
            Meta::META_LIST_PRICE => self::STONES_THROW_PRICE,
            'maximum_budget'      => '180000',
        ])));

        $this->assertStringContainsString('MLS List Price', $html);
        $this->assertStringContainsString('$184,900', $html);
        $this->assertStringContainsString('Your Terms', $html);
        $this->assertStringContainsString('$180,000', $html);

        // And the seller's own figure still reaches its canonical Your Terms row.
        $this->assertStringContainsString('Desired Sale Price', $html);
    }

    /**
     * @test
     *
     * The hero is the place a reader takes the headline number from, so the
     * label has to be adjacent to the figure there, not merely present
     * elsewhere on a 3,500-line page.
     */
    public function the_hero_number_carries_its_own_label(): void
    {
        $html = $this->sellerPage($this->sellerListing($this->linked([
            Meta::META_LIST_PRICE => self::STONES_THROW_PRICE,
            'maximum_budget'      => '180000',
        ])));

        $this->assertMatchesRegularExpression(
            '/MLS List Price<\/div>\s*<div class="sol-hero-price">\$184,900<\/div>/',
            $html,
            'the hero must name the figure it prints as the MLS list price',
        );

        $this->assertStringContainsString(
            'sol-price-secondary',
            $html,
            'the seller\'s own term must accompany the hero price, not replace it',
        );
    }

    // ── C. "Asking" resolves to the MLS figure ───────────────────────────────

    /**
     * @test
     *
     * Wherever the page states an asking price of record for an MLS-linked
     * listing, that is Stellar's figure. The seller's private target must never
     * be published as what the property is listed at.
     */
    public function the_asking_price_of_record_is_the_mls_figure_not_the_sellers_term(): void
    {
        $listing = $this->sellerListing($this->linked([
            Meta::META_LIST_PRICE => self::STONES_THROW_PRICE,
            'maximum_budget'      => '180000',
        ]));

        $html = $this->sellerPage($listing);

        // The sidebar price block is the labelled asking-price-of-record slot.
        $this->assertMatchesRegularExpression(
            '/MLS List Price<\/div>\s*<div style="font-size:1\.4rem[^"]*">\$184,900<\/div>/',
            $html,
            'the asking-price slot must show the MLS figure and say so',
        );

        $this->assertStringNotContainsString(
            'Asking Price</div>',
            $html,
            'an MLS-linked listing must not label a figure a bare "Asking Price"',
        );
    }

    // ── D. A refreshed price moves the page ──────────────────────────────────

    /**
     * @test
     *
     * The price-update contract. Nothing about the display persists or caches
     * `mls_list_price`, so a re-sync simply changes what the next render says —
     * and leaves Your Terms exactly where it was.
     */
    public function a_refreshed_mls_price_changes_the_page_and_leaves_your_terms_alone(): void
    {
        $listing = $this->sellerListing($this->linked([
            Meta::META_LIST_PRICE => self::STONES_THROW_PRICE,
            'maximum_budget'      => '180000',
        ]));

        $before = $this->sellerPage($listing);
        $this->assertStringContainsString('$184,900', $before);

        // What a successful sync does: rewrite the one authoritative row.
        $listing->saveMeta(Meta::META_LIST_PRICE, '179900');

        $after = $this->sellerPage($listing->fresh());

        $this->assertStringContainsString('$179,900', $after);
        $this->assertStringNotContainsString('$184,900', $after, 'the superseded MLS price must not survive the refresh');
        $this->assertStringContainsString('$180,000', $after, 'Your Terms is not touched by an MLS price change');

        // And the stored term is byte-for-byte what it was.
        $this->assertSame('180000', (string) $listing->fresh()->info('maximum_budget'));
    }

    // ── E. The payment calculator still initialises from the MLS price ───────

    /**
     * @test
     *
     * The listing-detail change must not regress PR #136's calculator default.
     * Asserted on the rendered page rather than on buildCalcData() alone —
     * MlsPriceReachesPaymentCalculatorTest already pins the resolver, and what
     * is at risk here is the value reaching the template.
     */
    public function the_payment_calculator_still_opens_at_the_current_mls_price(): void
    {
        $listing = $this->sellerListing($this->linked([
            Meta::META_LIST_PRICE => '179900',
            'maximum_budget'      => '180000',
        ]));

        $data = app(\App\Http\Controllers\SellerOfferListingController::class)
            ->view($listing->id)
            ->getData()['calcData'];

        $this->assertSame(179900.0, $data['price']);
        $this->assertSame('from listing', $data['price_source']);
        $this->assertNotSame(0.0, $data['price'], 'the calculator must not open at zero on an MLS listing');
    }

    // ── F. Manual seller listings ────────────────────────────────────────────

    /**
     * @test
     *
     * A manually created listing shows its own price with its own wording and
     * gains no MLS labelling, whatever happens to sit in its meta.
     */
    public function a_manual_seller_listing_shows_its_own_price_and_no_mls_label(): void
    {
        $html = $this->sellerPage($this->sellerListing([
            'maximum_budget' => '500000',
            // Deliberately present and deliberately ignored: without an MLS
            // identifier there is no MLS claim to make.
            Meta::META_LIST_PRICE => self::STONES_THROW_PRICE,
        ]));

        $this->assertStringContainsString('$500,000', $html);
        $this->assertStringNotContainsString('MLS List Price', $html);
        $this->assertStringNotContainsString('$184,900', $html);
        $this->assertStringContainsString('Asking Price', $html, 'the manual wording is unchanged');
    }

    /** @test */
    public function a_manual_seller_listings_legacy_price_keys_render_exactly_as_before(): void
    {
        $html = $this->sellerPage($this->sellerListing(['purchase_price' => '275000']));

        $this->assertStringContainsString('$275,000', $html);
        $this->assertStringNotContainsString('MLS List Price', $html);
    }

    // ── G. Zero / missing MLS price ──────────────────────────────────────────

    /**
     * @test
     *
     * @dataProvider unusableStoredPrices
     *
     * An MLS-linked listing whose authoritative price is empty, zero or invalid
     * must publish no MLS price at all — never "$0", never an empty MLS block.
     * It falls back to the manual presentation.
     */
    public function an_unusable_mls_price_publishes_no_mls_price_block(mixed $stored): void
    {
        $meta = $this->linked(['maximum_budget' => '180000']);

        if ($stored !== null) {
            $meta[Meta::META_LIST_PRICE] = $stored;
        }

        $html = $this->sellerPage($this->sellerListing($meta));

        $this->assertStringNotContainsString('MLS List Price', $html);
        $this->assertStringNotContainsString('>$0<', $html);
        $this->assertStringContainsString('$180,000', $html, 'the listing falls back to its own price');
    }

    /** @return array<string,array{0:mixed}> */
    public static function unusableStoredPrices(): array
    {
        return [
            'never synced' => [null],
            'zero'         => ['0'],
            'blank'        => [''],
            'non-numeric'  => ['Call for price'],
        ];
    }

    // ── H / I. Landlord ──────────────────────────────────────────────────────

    /** @test */
    public function an_mls_linked_lease_listing_shows_the_mls_asking_rent_beside_the_landlords_own(): void
    {
        $html = $this->landlordPage($this->landlordListing($this->linked([
            Meta::META_SOURCE_PTYPE => 'Residential Lease',
            Meta::META_LIST_PRICE   => '2400',
            'desired_rental_amount' => '2500',
        ])));

        $this->assertStringContainsString('MLS Asking Rent', $html);
        $this->assertStringContainsString('$2,400', $html);
        $this->assertStringContainsString('Your Terms', $html);
        $this->assertStringContainsString('$2,500', $html);
    }

    /** @test */
    public function the_landlords_own_rent_is_never_overwritten_by_the_display(): void
    {
        $listing = $this->landlordListing($this->linked([
            Meta::META_SOURCE_PTYPE => 'Residential Lease',
            Meta::META_LIST_PRICE   => '2400',
            'desired_rental_amount' => '2500',
        ]));

        $this->landlordPage($listing);

        $this->assertSame('2500', (string) $listing->fresh()->info('desired_rental_amount'));
        $this->assertSame('2400', (string) $listing->fresh()->info(Meta::META_LIST_PRICE));
    }

    /**
     * @test
     *
     * THE ONE THAT MATTERS ON THE LANDLORD SIDE. A sale record's ListPrice under
     * a "/ mo" suffix advertises a $184,900 monthly rent. It happened once with
     * a $100,000 sale price; the display re-checks the rule rather than trusting
     * that no such row can exist.
     */
    public function a_sale_source_price_is_never_rendered_as_a_monthly_rent(): void
    {
        $html = $this->landlordPage($this->landlordListing($this->linked([
            Meta::META_SOURCE_PTYPE => 'Residential',
            Meta::META_LIST_PRICE   => self::STONES_THROW_PRICE,
            'desired_rental_amount' => '2500',
        ])));

        $this->assertStringNotContainsString('$184,900', $html, 'a sale price must never appear on a rental page');
        $this->assertStringNotContainsString('MLS Asking Rent', $html);
        $this->assertStringContainsString('$2,500', $html, 'the landlord\'s own rent is what is published');
    }

    /** @test */
    public function a_manual_landlord_listing_shows_its_own_rent_with_the_original_wording(): void
    {
        $html = $this->landlordPage($this->landlordListing(['desired_rental_amount' => '3200']));

        $this->assertStringContainsString('$3,200', $html);
        $this->assertStringContainsString('Monthly Rent', $html, 'the manual wording is unchanged');
        $this->assertStringNotContainsString('MLS Asking Rent', $html);
    }

    // ── J. Presentation writes nothing ───────────────────────────────────────

    /**
     * @test
     *
     * Rendering a listing page must not create, change or remove a single meta
     * row. Counted and compared wholesale rather than spot-checked, because the
     * risk is a write nobody thought to look for.
     */
    public function rendering_a_listing_page_writes_no_meta(): void
    {
        $listing = $this->sellerListing($this->linked([
            Meta::META_LIST_PRICE => self::STONES_THROW_PRICE,
            'maximum_budget'      => '180000',
        ]));

        $before = $this->metaSnapshot($listing);

        $this->sellerPage($listing);
        $this->sellerPage($listing->fresh());

        $this->assertSame($before, $this->metaSnapshot($listing->fresh()), 'the listing page must be read-only');
    }

    /** @test */
    public function rendering_a_landlord_page_writes_no_meta(): void
    {
        $listing = $this->landlordListing($this->linked([
            Meta::META_SOURCE_PTYPE => 'Residential Lease',
            Meta::META_LIST_PRICE   => '2400',
            'desired_rental_amount' => '2500',
        ]));

        $before = $this->metaSnapshot($listing);

        $this->landlordPage($listing);

        $this->assertSame($before, $this->metaSnapshot($listing->fresh()));
    }

    /** @return array<string,mixed> */
    private function metaSnapshot(object $listing): array
    {
        $snapshot = [];

        foreach ($listing->fresh()->meta as $row) {
            $snapshot[$row->meta_key] = $row->meta_value;
        }

        ksort($snapshot);

        return $snapshot;
    }
}
