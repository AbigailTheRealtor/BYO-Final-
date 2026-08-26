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
     * NO ACTIVE CANONICAL FIELD IS SILENTLY UNPERSISTED.
     *
     * The defect this replaces: manual Create rendered fifteen canonical Sale
     * Terms fields it had no saveMeta line for, so a value typed while CREATING
     * a listing was silently discarded while the same value typed while EDITING
     * saved normally. They are repaired; this asserts the class of bug is gone
     * rather than that those particular fifteen are fixed.
     *
     * Every canonical field must be either persisted or on the deliberate
     * not-persisted list, and that list may only contain UI state.
     */
    public function no_active_canonical_field_is_silently_unpersisted(): void
    {
        $fields       = SellerOfferListing::sellerSaleTermsFields();
        $map          = SellerOfferListing::sellerSaleTermsMetaMap();
        $notPersisted = SellerOfferListing::sellerSaleTermsNotPersisted();

        $unaccounted = array_values(array_diff($fields, array_keys($map), $notPersisted));

        $this->assertSame(
            [],
            $unaccounted,
            'Canonical Sale Terms fields are neither persisted nor declared as UI state: '
            . implode(', ', $unaccounted)
        );

        // The escape hatch stays shut. Anything a seller can type into belongs in
        // the map; only view state may sit here.
        $this->assertSame(
            ['showPaymentAssumptions'],
            $notPersisted,
            'A field was added to the not-persisted list. If a seller can enter a '
            . 'value into it, it must be persisted instead.'
        );
    }

    /**
     * @test
     *
     * The fifteen repaired fields are persisted, with the transforms Edit
     * already used. Named explicitly because each was a live data-loss path.
     */
    public function the_repaired_create_gap_fields_are_persisted(): void
    {
        $map = SellerOfferListing::sellerSaleTermsMetaMap();

        $expected = [
            'occupant_tenant'                    => 'raw',
            'balloon_payment'                    => 'raw',
            'outstanding_balance'                => 'raw',
            'lease_option_fee_credit'            => 'raw',
            'lease_option_fee_credit_percentage' => 'raw',
            'lease_option_maintenance'           => 'raw',
            'lease_option_extension_terms'       => 'raw',
            'lease_purchase_rent_credit'         => 'raw',
            'lease_purchase_rent_credit_amount'  => 'money',
            'lease_purchase_deposit'             => 'money',
            'lease_purchase_maintenance'         => 'raw',
            'lease_purchase_extension_terms'     => 'raw',
            'nft_gas_fees'                       => 'raw',
            'nft_transfer_method'                => 'raw',
            'nft_valuation_method'               => 'raw',
        ];

        foreach ($expected as $field => $kind) {
            $this->assertArrayHasKey($field, $map, "{$field} is unpersisted again.");
            $this->assertSame($kind, $map[$field], "{$field} changed storage transform.");
        }
    }

    /**
     * @test
     *
     * All three flows write through the one routine. Create and Edit are checked
     * at the source, because a hand-written saveMeta run reappearing beside the
     * shared call is how the drift started.
     */
    public function create_edit_and_quick_import_all_persist_through_the_shared_routine(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/QuickImport/SellerMlsQuickImport.php',
        ] as $file) {
            $this->assertStringContainsString(
                'saveSellerSaleTermsMeta($auction)',
                file_get_contents(base_path($file)),
                $file . ' no longer persists Sale Terms through the shared routine.'
            );
        }

        // And none of them keeps a private saveMeta line for a canonical field.
        $map = array_keys(SellerOfferListing::sellerSaleTermsMetaMap());

        foreach ([
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            foreach ($map as $field) {
                $this->assertStringNotContainsString(
                    "saveMeta('{$field}'",
                    $source,
                    "{$file} has a private saveMeta line for the canonical field {$field}."
                );
            }
        }
    }

    /**
     * @test
     *
     * Persistence is save AND load. Create used to write twelve fields it never
     * read back, so resuming a draft showed them blank and the next save wrote
     * that blank over the stored answer — data loss on a delay.
     */
    public function create_rehydrates_canonical_sale_terms(): void
    {
        $this->assertStringContainsString(
            'loadSellerSaleTermsMeta($auction)',
            file_get_contents(base_path(
                'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php'
            )),
            'Create no longer rehydrates canonical Sale Terms when resuming a draft.'
        );
    }

    /**
     * @test
     *
     * Quick Import's asking-price rule is listing-data validation, not a sale
     * term. Pinned so it is not later mistaken for one and copied into the
     * canonical rules.
     */
    public function the_quick_import_price_rule_is_not_a_sale_terms_rule(): void
    {
        // Read through reflection rather than adding a test-only accessor to a
        // production component.
        $component = new SellerOfferListing();
        $method    = new \ReflectionMethod($component, 'getConditionalRules');
        $method->setAccessible(true);

        /** @var array<string, mixed> $rules */
        $rules = $method->invoke($component);

        foreach (SellerOfferListing::sellerSaleTermsFields() as $field) {
            $this->assertArrayNotHasKey(
                $field,
                $rules,
                "Canonical publish validation has grown a rule for the sale term {$field}."
            );
        }
    }
}
