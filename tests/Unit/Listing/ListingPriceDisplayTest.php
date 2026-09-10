<?php

namespace Tests\Unit\Listing;

use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Support\Listing\ListingPriceDisplay;
use PHPUnit\Framework\TestCase;

/**
 * THE PRICE-RESOLUTION RULE, PINNED WITHOUT A DATABASE OR A TEMPLATE.
 *
 * ListingPriceDisplay is a pure array-to-values transformer, so it is asserted
 * directly. The rendering of these values is pinned separately, on the real
 * pages, by Tests\Feature\ListingImport\MlsListPriceDisplayTest — the two halves
 * fail for different reasons and a merged test would hide which one broke.
 *
 * Extends PHPUnit's TestCase rather than the application one deliberately: this
 * class must not need a booted container, for the same reason LandlordScreeningPolicy
 * must not. It is called from Blade during a render.
 */
class ListingPriceDisplayTest extends TestCase
{
    private const LINKED = [Meta::META_LISTING_KEY => 'STONES-KEY'];

    // ── Seller ───────────────────────────────────────────────────────────────

    /** @test */
    public function the_mls_list_price_is_the_asking_price_of_record_on_an_mls_linked_listing(): void
    {
        $d = ListingPriceDisplay::forSeller(self::LINKED + [
            Meta::META_LIST_PRICE => '184900',
            'maximum_budget'      => '180000',
            'desired_sale_price'  => '175000',
        ]);

        $this->assertSame(184900.0, $d->mlsListPrice());
        $this->assertSame(
            184900.0,
            $d->askingPrice(),
            'Asking must be Stellar\'s figure on an MLS-linked listing, never the seller\'s own term',
        );
    }

    /**
     * @test
     *
     * The seller's term survives intact beside it. This is the whole point: one
     * value must never silently stand in for the other.
     */
    public function the_sellers_own_term_is_resolved_separately_and_is_not_replaced(): void
    {
        $d = ListingPriceDisplay::forSeller(self::LINKED + [
            Meta::META_LIST_PRICE => '184900',
            'maximum_budget'      => '180000',
        ]);

        $this->assertSame(180000.0, $d->yourTermsPrice());
        $this->assertTrue($d->showsSeparateTerms());
    }

    /**
     * @test
     *
     * `maximum_budget` is what the Seller wizard's "Desired Sale Price" input
     * writes, and the old hero chain did not read it — the same defect
     * buildCalcData() documents for the payment calculator. A Traditional seller
     * who filled in the one required price field had no price on their page.
     */
    public function the_sellers_term_comes_from_the_key_the_wizard_actually_writes(): void
    {
        $d = ListingPriceDisplay::forSeller(['maximum_budget' => '500000']);

        $this->assertSame(500000.0, $d->yourTermsPrice());
        $this->assertSame(500000.0, $d->askingPrice());
    }

    /** @test */
    public function the_legacy_seller_price_keys_still_resolve_in_their_established_order(): void
    {
        $this->assertSame(250000.0, ListingPriceDisplay::forSeller(['desired_sale_price' => '250000'])->yourTermsPrice());
        $this->assertSame(275000.0, ListingPriceDisplay::forSeller(['purchase_price' => '275000'])->yourTermsPrice());
        $this->assertSame(300000.0, ListingPriceDisplay::forSeller(['buy_now_price' => '300000'])->yourTermsPrice());
        $this->assertSame(120000.0, ListingPriceDisplay::forSeller(['starting_price' => '120000'])->yourTermsPrice());
        $this->assertSame(130000.0, ListingPriceDisplay::forSeller(['reserve_price' => '130000'])->yourTermsPrice());

        // Precedence: the canonical key wins over a legacy one.
        $this->assertSame(
            500000.0,
            ListingPriceDisplay::forSeller(['maximum_budget' => '500000', 'purchase_price' => '275000'])->yourTermsPrice(),
        );
    }

    /**
     * @test
     *
     * The refresh contract. Nothing is cached, copied or persisted by the
     * display, so a re-synced figure is simply the next render's figure.
     */
    public function a_refreshed_mls_price_is_what_the_next_render_resolves(): void
    {
        $before = ListingPriceDisplay::forSeller(self::LINKED + [Meta::META_LIST_PRICE => '184900', 'maximum_budget' => '180000']);
        $after  = ListingPriceDisplay::forSeller(self::LINKED + [Meta::META_LIST_PRICE => '179900', 'maximum_budget' => '180000']);

        $this->assertSame(184900.0, $before->askingPrice());
        $this->assertSame(179900.0, $after->askingPrice());
        $this->assertSame(180000.0, $after->yourTermsPrice(), 'Your Terms is untouched by an MLS price change');
    }

    // ── Manual listings ──────────────────────────────────────────────────────

    /** @test */
    public function a_manual_listing_has_no_mls_price_whatever_its_meta_happens_to_hold(): void
    {
        // Not MLS-linked: no listing key, no MLS number. A stray price row must
        // not manufacture an MLS claim.
        $d = ListingPriceDisplay::forSeller([Meta::META_LIST_PRICE => '184900', 'maximum_budget' => '180000']);

        $this->assertNull($d->mlsListPrice());
        $this->assertFalse($d->mlsIsAuthoritative());
        $this->assertFalse($d->showsSeparateTerms());
        $this->assertSame(180000.0, $d->askingPrice(), 'a manual listing asks its own price, exactly as before');
    }

    /** @test */
    public function an_mls_linked_listing_that_has_never_synced_a_price_presents_as_a_manual_one(): void
    {
        // The quick import writes the mapped price field; only a sync writes
        // `mls_list_price`. Between the two there is no authoritative figure to
        // label, and inventing one would assert something Stellar has not said.
        $d = ListingPriceDisplay::forSeller(self::LINKED + ['maximum_budget' => '180000']);

        $this->assertNull($d->mlsListPrice());
        $this->assertFalse($d->showsSeparateTerms());
        $this->assertSame(180000.0, $d->askingPrice());
    }

    // ── Zero / null / invalid ────────────────────────────────────────────────

    /**
     * @test
     *
     * @dataProvider unusablePrices
     *
     * No source value may become "$0". Every one of these falls through to the
     * listing's own presentation instead.
     */
    public function an_empty_or_invalid_mls_price_is_never_published(mixed $stored): void
    {
        $d = ListingPriceDisplay::forSeller(self::LINKED + [
            Meta::META_LIST_PRICE => $stored,
            'maximum_budget'      => '180000',
        ]);

        $this->assertNull($d->mlsListPrice(), 'an unusable stored value must not become a displayed MLS price');
        $this->assertFalse($d->mlsIsAuthoritative());
        $this->assertSame(180000.0, $d->askingPrice());
    }

    /** @return array<string,array{0:mixed}> */
    public static function unusablePrices(): array
    {
        return [
            'null'       => [null],
            'zero'       => ['0'],
            'zero float' => ['0.00'],
            'blank'      => [''],
            'whitespace' => ['   '],
            'text'       => ['Call for price'],
            'negative'   => ['-5000'],
            'array'      => [['184900']],
            'false'      => [false],
        ];
    }

    /** @test */
    public function money_renders_nothing_for_nothing_and_never_a_zero(): void
    {
        $this->assertNull(ListingPriceDisplay::money(null));
        $this->assertSame('$184,900', ListingPriceDisplay::money(184900.0));
    }

    /**
     * @test
     *
     * A quick-imported listing seeds the MLS price into the seller's own input,
     * so the two figures are usually the same one. Printing it twice under two
     * headings would invent a distinction the listing does not have.
     */
    public function identical_prices_are_not_presented_as_two_different_claims(): void
    {
        $d = ListingPriceDisplay::forSeller(self::LINKED + [
            Meta::META_LIST_PRICE => '184900',
            'maximum_budget'      => '184900',
        ]);

        $this->assertFalse($d->showsSeparateTerms());
        $this->assertSame(184900.0, $d->askingPrice());
    }

    // ── Landlord ─────────────────────────────────────────────────────────────

    /** @test */
    public function a_landlord_lease_record_may_publish_its_mls_asking_rent(): void
    {
        $d = ListingPriceDisplay::forLandlord(self::LINKED + [
            Meta::META_SOURCE_PTYPE => 'Residential Lease',
            Meta::META_LIST_PRICE   => '2400',
            'desired_rental_amount' => '2500',
        ]);

        $this->assertSame(2400.0, $d->mlsListPrice());
        $this->assertSame(2500.0, $d->yourTermsPrice(), 'desired_rental_amount is untouched');
        $this->assertTrue($d->showsSeparateTerms());
    }

    /**
     * @test
     *
     * @dataProvider nonLeaseSources
     *
     * THE ONE THAT MATTERS. A sale record's ListPrice is a purchase price;
     * printed under "/ mo" it advertises a $184,900 monthly rent. The rule is
     * asked of MlsSyncFieldPolicy — the same method that decides whether sync
     * may write the value — so the page cannot disagree with the writer, and the
     * re-check still applies when a stored row outlives the source type that
     * produced it.
     */
    public function a_sale_source_price_can_never_be_displayed_as_a_monthly_rent(?string $sourceType): void
    {
        $d = ListingPriceDisplay::forLandlord(self::LINKED + array_filter([
            Meta::META_SOURCE_PTYPE => $sourceType,
            Meta::META_LIST_PRICE   => '184900',
            'desired_rental_amount' => '2500',
        ], static fn ($v) => $v !== null));

        $this->assertNull($d->mlsListPrice(), 'a non-lease source price must not reach a rent field');
        $this->assertFalse($d->mlsIsAuthoritative());
        $this->assertFalse($d->showsSeparateTerms());
        $this->assertSame(2500.0, $d->askingPrice(), 'the landlord\'s own rent is what the page asks');
    }

    /** @return array<string,array{0:?string}> */
    public static function nonLeaseSources(): array
    {
        return [
            'residential sale' => ['Residential'],
            'commercial sale'  => ['Commercial Sale'],
            'land'             => ['Land'],
            'blank'            => [''],
            'absent'           => [null],
        ];
    }

    /** @test */
    public function a_manual_landlord_listing_resolves_its_rent_keys_exactly_as_before(): void
    {
        $this->assertSame(2500.0, ListingPriceDisplay::forLandlord(['desired_rental_amount' => '2500'])->askingPrice());
        $this->assertSame(1800.0, ListingPriceDisplay::forLandlord(['starting_rent' => '1800'])->askingPrice());
        $this->assertSame(1900.0, ListingPriceDisplay::forLandlord(['reserve_rent' => '1900'])->askingPrice());
        $this->assertSame(2100.0, ListingPriceDisplay::forLandlord(['lease_now_price' => '2100'])->askingPrice());

        $none = ListingPriceDisplay::forLandlord([]);
        $this->assertNull($none->askingPrice());
        $this->assertNull($none->mlsListPrice());
    }

    // ── The presentation writes nothing ──────────────────────────────────────

    /**
     * @test
     *
     * Presentation must consume the stored value, never mutate it. Asserted
     * structurally: the class holds no writer at all — no saveMeta, no update,
     * no assignment to the meta it was handed.
     */
    public function the_display_class_contains_no_write_path(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Support/Listing/ListingPriceDisplay.php',
        );

        $this->assertIsString($source);

        foreach (['saveMeta', 'setMeta', '->save(', '::update(', '->update(', 'DB::'] as $writer) {
            $this->assertStringNotContainsString(
                $writer,
                $source,
                "ListingPriceDisplay must not write; found '{$writer}'",
            );
        }
    }
}
