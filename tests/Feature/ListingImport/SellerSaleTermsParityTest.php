<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Support\Listing\SellerSaleTermsOptions;
use ReflectionClass;
use Tests\TestCase;

/**
 * Seller Sale Terms is ONE definition, and these tests fail if it becomes two.
 *
 * THE REGRESSION BEING PINNED
 * ---------------------------
 * MLS Quick Import used to declare its own Sale Terms field list — nineteen
 * hand-copied entries in SellerMlsQuickImport::questionSchema(), with their own
 * labels and their own option vocabularies. It could not express a single
 * conditional section, so a seller arriving through quick import was never asked
 * about seller financing, an assumable loan, a lease option or purchase, an
 * exchange, a crypto or NFT split, a balloon schedule, a bidding-period reserve
 * or buy-now price, or any Estimated Payment Assumption. Those questions did not
 * exist on that path.
 *
 * Every test here is structural on purpose. A test that listed the expected
 * fields would itself be a fourth copy of the list, and would pass happily while
 * the product drifted underneath it. These compare the real definitions to each
 * other instead.
 */
class SellerSaleTermsParityTest extends TestCase
{
    /** Every consumer of the canonical Sale Terms definition. */
    private const CONSUMERS = [
        SellerOfferListing::class,
        SellerOfferListingEdit::class,
        SellerMlsQuickImport::class,
    ];

    /**
     * @test
     *
     * The three screens share one field list, not three that happen to agree.
     */
    public function every_consumer_uses_the_one_canonical_definition(): void
    {
        foreach (self::CONSUMERS as $class) {
            $this->assertContains(
                'App\Http\Livewire\OfferListing\Concerns\SellerSaleTerms',
                class_uses_recursive($class),
                $class . ' must take its Sale Terms fields from the SellerSaleTerms trait, '
                . 'not declare a list of its own.'
            );
        }
    }

    /**
     * @test
     *
     * Quick Import holds every canonical term as a real property, so the shared
     * partial can bind to it. A missing one is a control the seller would never
     * be shown on that path.
     */
    public function quick_import_exposes_every_canonical_sale_term(): void
    {
        $quick = new ReflectionClass(SellerMlsQuickImport::class);
        $have  = array_map(
            fn ($p) => $p->getName(),
            $quick->getProperties(\ReflectionProperty::IS_PUBLIC)
        );

        $missing = array_values(array_diff(SellerOfferListing::sellerSaleTermsFields(), $have));

        $this->assertSame(
            [],
            $missing,
            'MLS Quick Import is missing canonical Seller Sale Terms: ' . implode(', ', $missing)
        );
    }

    /**
     * @test
     *
     * The manual screens and Quick Import agree field-for-field. Stated as a set
     * comparison in both directions so neither side can quietly grow or shrink.
     */
    public function the_manual_flow_and_quick_import_expose_the_same_terms(): void
    {
        $fields = SellerOfferListing::sellerSaleTermsFields();

        foreach ([SellerOfferListingEdit::class, SellerMlsQuickImport::class] as $class) {
            $this->assertSame(
                $fields,
                $class::sellerSaleTermsFields(),
                $class . ' disagrees with manual Create about what the Sale Terms are.'
            );
        }
    }

    /**
     * @test
     *
     * Quick Import renders the canonical partial — the same file the manual
     * Create and Edit screens include — rather than markup of its own.
     */
    public function quick_import_renders_the_canonical_partial(): void
    {
        $partial = (new SellerMlsQuickImport())->canonicalTermsPartial();

        $this->assertSame(
            'livewire.offer-listing.offer-seller-tabs.commission-based.seller-terms',
            $partial
        );

        foreach ([
            'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
            'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
        ] as $manual) {
            $this->assertStringContainsString(
                'offer-seller-tabs.commission-based.seller-terms',
                file_get_contents(base_path($manual)),
                $manual . ' no longer includes the partial Quick Import mirrors.'
            );
        }
    }

    /**
     * @test
     *
     * Quick Import must not regrow a schema of its own. An empty questionSchema()
     * is how Seller declares it is canonical-driven; a non-empty one is the old
     * duplicate coming back.
     */
    public function quick_import_declares_no_private_terms_schema(): void
    {
        $component = new SellerMlsQuickImport();

        $this->assertTrue($component->usesCanonicalTerms());
        $this->assertSame(
            [],
            $component->questionSchema(),
            'SellerMlsQuickImport has grown a private Sale Terms schema again. '
            . 'Add the field to the canonical partial and SellerSaleTerms instead.'
        );
    }

    /**
     * @test
     *
     * The conditional sections the old schema could not express are present.
     * Named explicitly because their absence was the user-visible symptom, and
     * because each is a distinct branch of the canonical tab.
     */
    public function the_conditional_sale_terms_sections_are_all_present(): void
    {
        $fields = SellerOfferListing::sellerSaleTermsFields();

        $representatives = [
            'Seller Financing'              => 'seller_financing_type',
            'Assumable'                     => 'assumable_terms',
            'Lease Option'                  => 'lease_option_price',
            'Lease Purchase'                => 'lease_purchase_price',
            'Exchange / Trade'              => 'exchange_item',
            'Cryptocurrency'                => 'crypto_percentage',
            'NFT'                           => 'nft_percentage',
            'Balloon / amortization'        => 'balloon_payment_amount',
            'Bidding Period starting price' => 'starting_price',
            'Reserve price'                 => 'reserve_price',
            'Buy-now price'                 => 'buy_now_price',
            'Special Sale Provision'        => 'sale_provision',
            'Estimated Payment Assumptions' => 'payment_interest_rate',
        ];

        foreach ($representatives as $section => $field) {
            $this->assertContains(
                $field,
                $fields,
                "The {$section} section is missing from the canonical Sale Terms definition."
            );
        }
    }

    /**
     * @test
     *
     * The financing vocabulary is read from one place by the tab that offers it
     * and the code that validates it.
     */
    public function the_financing_vocabulary_has_a_single_source(): void
    {
        $names = SellerSaleTermsOptions::financingNames();

        $this->assertContains('Seller Financing', $names);
        $this->assertContains('Exchange/Trade', $names);
        $this->assertContains('Non-Fungible Token (NFT)', $names);

        $partial = file_get_contents(base_path(
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php'
        ));

        $this->assertStringContainsString(
            'SellerSaleTermsOptions::financing()',
            $partial,
            'The canonical tab has gone back to an inline financing list.'
        );
    }

    /**
     * @test
     *
     * Quick Import writes through the same routine manual Create writes through,
     * so the two cannot store the same answer under different keys or with
     * different transforms.
     */
    public function quick_import_persists_through_the_shared_routine(): void
    {
        $create = SellerOfferListing::sellerSaleTermsMetaMap();
        $quick  = SellerMlsQuickImport::sellerSaleTermsMetaMap();

        $this->assertSame($create, $quick);

        $source = file_get_contents(base_path(
            'app/Http/Livewire/OfferListing/QuickImport/SellerMlsQuickImport.php'
        ));

        $this->assertStringContainsString('saveSellerSaleTermsMeta($auction)', $source);
    }

    /**
     * @test
     *
     * Documents a PRE-EXISTING defect this refactor deliberately did not change:
     * manual Create renders these fields but has never persisted them, while
     * manual Edit does. Quick Import is a creation path and matches Create.
     *
     * This test exists so the gap is visible and counted rather than forgotten.
     * When Create is repaired the list empties and this assertion is what tells
     * you to delete the constant it guards.
     */
    public function the_known_create_persistence_gap_is_recorded(): void
    {
        $unpersisted = SellerOfferListing::sellerSaleTermsUnpersistedOnCreate();
        $map         = SellerOfferListing::sellerSaleTermsMetaMap();

        foreach ($unpersisted as $field) {
            $this->assertArrayNotHasKey(
                $field,
                $map,
                "{$field} is now persisted on Create — remove it from "
                . 'sellerSaleTermsUnpersistedOnCreate().'
            );
        }

        // Every canonical field is either persisted or explicitly listed as a
        // known gap. Nothing may fall between the two silently.
        $accounted = array_merge(array_keys($map), $unpersisted);

        $this->assertSame(
            [],
            array_values(array_diff(SellerOfferListing::sellerSaleTermsFields(), $accounted)),
            'A canonical Sale Terms field is neither persisted nor recorded as a known gap.'
        );
    }
}
