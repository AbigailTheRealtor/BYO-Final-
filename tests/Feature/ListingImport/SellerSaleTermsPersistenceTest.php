<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Canonical Seller Sale Terms survive a save on EVERY flow that offers them.
 *
 * THE BUG THIS CLOSES
 * -------------------
 * Manual Create rendered fifteen canonical Sale Terms fields it had no saveMeta
 * line for. A seller who filled in "Lease Option Maintenance", "Outstanding
 * Balance" or "NFT Transfer Method" while CREATING a listing lost the answer at
 * save; the same seller filling the same field while EDITING kept it. Create was
 * also never reading twenty-seven of these fields back, so resuming a draft
 * showed them blank and the next save wrote that blank over the stored answer.
 *
 * These tests drive the real components through the real save path and read the
 * real meta rows. They are behavioural on purpose — SellerSaleTermsParityTest
 * proves the definitions agree, this proves an answer actually survives.
 */
class SellerSaleTermsPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    /**
     * One representative answer per conditional section, plus the repaired
     * fields. Money values carry commas so the stripping transform is exercised.
     *
     * @var array<string, string>
     */
    private const ANSWERS = [
        // ── Seller Financing ────────────────────────────────────────────────
        'seller_financing_type'          => '%',
        'seller_down_payment_amount'     => '50,000',
        'seller_late_fee_amount'         => '125',
        'interest_rate'                  => '6.5',
        'loan_duration'                  => '30',
        'seller_amortization_type'       => 'Fully Amortized',
        'seller_amortization_other'      => 'custom schedule',
        'seller_payment_frequency'       => 'Monthly',
        'seller_payment_frequency_other' => 'fortnightly',
        'prepayment_penalty'             => 'Yes',
        'prepayment_penalty_amount'      => '2,500',

        // ── Balloon ─────────────────────────────────────────────────────────
        'balloon_payment'        => 'Yes',
        'balloon_payment_amount' => '120,000',
        'balloon_payment_date'   => '2030-01-01',

        // ── Assumable ───────────────────────────────────────────────────────
        'assumable_terms'                 => 'Yes',
        'assumable_loan_type'             => 'FHA',
        'outstanding_balance'             => '245000',
        'max_assumable_rate'              => '5.25',
        'assumable_monthly_escrow'        => '410',
        'assumable_loan_term_remaining'   => '22',
        'assumable_loan_origination_date' => '2018-04-02',
        'assumable_loan_servicer'         => 'Acme Servicing',
        'assumable_fee_amount'            => '1,200',
        'assumption_fee_responsibility'   => 'Buyer',
        'assumable_occupancy_requirement' => 'Owner Occupied',

        // ── Lease Option ────────────────────────────────────────────────────
        'lease_option_price'                 => '480,000',
        'lease_option_terms'                 => '24 months',
        'lease_option_duration'              => '24',
        'lease_option_payment'               => '3,200',
        'lease_option_conditions'            => 'Tenant maintains landscaping',
        'has_option_fee'                     => 'Yes',
        'option_fee_amount'                  => '9,500',
        'lease_option_fee_credit'            => 'Yes',
        'lease_option_fee_credit_percentage' => '50',
        'lease_option_maintenance'           => 'Buyer',
        'lease_option_extension_terms'       => 'One 6-month extension',

        // ── Lease Purchase ──────────────────────────────────────────────────
        'lease_purchase_price'              => '505,000',
        'lease_purchase_terms'              => '36 months',
        'lease_purchase_duration'           => '36',
        'lease_purchase_payment'            => '3,400',
        'lease_purchase_conditions'         => 'Buyer insures the property',
        'lease_purchase_rent_credit'        => 'Yes',
        'lease_purchase_rent_credit_amount' => '1,000',
        'lease_purchase_deposit'            => '15,000',
        'lease_purchase_maintenance'        => 'Seller',
        'lease_purchase_extension_terms'    => 'Month to month thereafter',

        // ── Exchange / Trade ────────────────────────────────────────────────
        'other_exchange_item'        => 'Vintage tractor',
        'exchange_item_value'        => '18,500',
        'exchange_item_condition'    => 'Good',
        'additional_cash'            => '25,000',
        'value_determination'        => 'Independent appraisal',
        'exchange_transfer_method'   => 'Bill of sale',
        'exchange_liens_disclosure'  => 'No',
        'exchange_inspection_rights' => 'Yes',

        // ── Cryptocurrency ──────────────────────────────────────────────────
        'cryptocurrency_type'          => 'Bitcoin',
        'crypto_percentage'            => '25',
        'cash_percentage_crypto'       => '75',
        'crypto_transfer_timing'       => 'At Closing',
        'crypto_exchange_method'       => 'Coinbase',
        'crypto_custodian_wallet'      => 'Custodial',
        'crypto_transaction_fees'      => 'Buyer',

        // ── NFT ─────────────────────────────────────────────────────────────
        'nft_description'       => 'Deed-linked token',
        'nft_percentage'        => '10',
        'cash_percentage_nft'   => '90',
        'nft_gas_fees'          => 'Buyer',
        'nft_transfer_method'   => 'Direct wallet transfer',
        'nft_valuation_method'  => 'Third-party appraisal',

        // ── Occupancy / deposits / contingencies ────────────────────────────
        'occupant_status'                    => 'Tenant',
        'occupant_tenant'                    => 'Lease expires March 2027',
        'initial_deposit_requested'          => '15,000',
        'additional_deposit_requested'       => '10,000',
        'escrow_agent_preference'            => 'Sunrise Title',
        'preferred_inspection_period'        => '10',
        'inspection_contingency_preference'  => 'Required',
        'sale_of_buyer_property_contingency' => 'No',
        'possession_preference'              => 'At Closing',
        'included_personal_property'         => 'Washer and dryer',
        'excluded_items'                     => 'Dining room chandelier',
        'home_warranty_offered'              => 'Yes',
        'hoa_condo_association_terms'        => 'Board approval required',
        'additional_seller_sale_terms'       => 'Seller prefers a 30-day close',

        // ── Estimated Payment Assumptions ───────────────────────────────────
        'payment_down_payment_pct'      => '20',
        'payment_interest_rate'         => '6.75',
        'payment_loan_term'             => '30',
        'payment_annual_property_taxes' => '7,200',
        'payment_monthly_insurance'     => '150',
        'payment_hoa_fee_amount'        => '325',
        'payment_hoa_fee_frequency'     => 'Monthly',
        'payment_pmi_rate'              => '0.5',
    ];

    /**
     * Every financing type whose conditional section this test fills in. All of
     * them must be selected or the section's answers are cleared on entry.
     *
     * @var list<string>
     */
    private const FINANCING = [
        'Cash',
        'Conventional',
        'Assumable',
        'Seller Financing',
        'Lease Option',
        'Lease Purchase',
        'Exchange/Trade',
        'Cryptocurrency',
        'Non-Fungible Token (NFT)',
        'Other',
    ];

    /**
     * Money fields, whose commas must be stripped on the way in.
     *
     * @var array<string, string>
     */
    private const EXPECTED_MONEY = [
        'seller_down_payment_amount'        => '50000',
        'prepayment_penalty_amount'         => '2500',
        'balloon_payment_amount'            => '120000',
        'assumable_fee_amount'              => '1200',
        'lease_option_price'                => '480000',
        'lease_option_payment'              => '3200',
        'option_fee_amount'                 => '9500',
        'lease_purchase_price'              => '505000',
        'lease_purchase_payment'            => '3400',
        'lease_purchase_rent_credit_amount' => '1000',
        'lease_purchase_deposit'            => '15000',
        'exchange_item_value'               => '18500',
        'additional_cash'                   => '25000',
        'payment_annual_property_taxes'     => '7200',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['user_type' => 'seller']);
    }

    private function makeAuction(array $meta = []): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Sale Terms Persistence',
            'is_draft'    => true,
            'is_approved' => false,
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        foreach ($meta as $key => $value) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => $key,
                'meta_value'              => (string) $value,
            ]);
        }

        return $auction;
    }

    /** Set every answer on a live component and run the real save path. */
    private function saveThrough(string $componentClass, SellerAgentAuction $auction): void
    {
        $test = Livewire::actingAs($this->user)->test($componentClass);

        // Financing types FIRST, exactly as a seller picks them: choosing them
        // is what opens the conditional sections below. updatedOfferedFinancing()
        // clears the section belonging to any type that is NOT selected, so
        // setting this afterwards would wipe answers that were just entered —
        // correct product behaviour, and the reason the order here matters.
        $test->set('offered_financing', self::FINANCING);

        foreach (self::ANSWERS as $field => $value) {
            $test->set($field, $value);
        }

        // Remaining array-valued canonical fields.
        $test->set('sale_provision', ['Short Sale'])
            ->set('exchange_item', ['Vehicle', '']);

        $component = $test->instance();

        $save = new ReflectionMethod($component, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component, $auction);
    }

    private function assertEverythingStored(SellerAgentAuction $auction, string $context): void
    {
        $meta = $auction->fresh()->get;

        foreach (self::ANSWERS as $field => $value) {
            $expected = self::EXPECTED_MONEY[$field] ?? $value;

            $this->assertSame(
                $expected,
                (string) ($meta->{$field} ?? ''),
                "{$context}: canonical Sale Term '{$field}' did not survive the save."
            );
        }

        // Arrays are stored as JSON and filtered/reindexed on the way in. The
        // meta accessor may hand them back already decoded, so normalise before
        // comparing rather than assuming one shape.
        $this->assertSame(self::FINANCING, $this->asList($meta->offered_financing), $context);
        $this->assertSame(['Short Sale'], $this->asList($meta->sale_provision), $context);

        // The empty member is filtered out — this is the exchange_item transform.
        $this->assertSame(['Vehicle'], $this->asList($meta->exchange_item), $context);
    }

    /** @return list<string> */
    private function asList(mixed $stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return array_values((array) ($stored ?? []));
    }

    /**
     * @test
     *
     * Manual Create — the flow that used to drop fifteen of these.
     */
    public function manual_create_persists_every_canonical_sale_term(): void
    {
        $auction = $this->makeAuction();

        $this->saveThrough(SellerOfferListing::class, $auction);
        $this->assertEverythingStored($auction, 'manual Create');
    }

    /**
     * @test
     *
     * Manual Edit — the flow that was already correct, and must stay correct now
     * that it writes through the shared routine.
     */
    public function manual_edit_persists_every_canonical_sale_term(): void
    {
        $auction = $this->makeAuction();

        $this->saveThrough(SellerOfferListingEdit::class, $auction);
        $this->assertEverythingStored($auction, 'manual Edit');
    }

    /**
     * @test
     *
     * Create and Edit produce byte-identical meta from identical answers. This
     * is the parity claim stated as an experiment rather than an assertion about
     * source code.
     */
    public function create_and_edit_store_identical_values(): void
    {
        $viaCreate = $this->makeAuction();
        $viaEdit   = $this->makeAuction();

        $this->saveThrough(SellerOfferListing::class, $viaCreate);
        $this->saveThrough(SellerOfferListingEdit::class, $viaEdit);

        $fields = SellerOfferListing::sellerSaleTermsFields();
        $create  = $viaCreate->fresh()->get;
        $edit    = $viaEdit->fresh()->get;

        foreach ($fields as $field) {
            if ($field === 'showPaymentAssumptions') {
                continue; // view state, deliberately not stored
            }

            if ($field === 'assignment_fee_type') {
                // The one documented divergence: Create defaults it to '' and
                // Edit to '$', which is why SellerSaleTerms cannot declare it.
                // Neither flow was touched, so the difference is untouched too.
                continue;
            }

            // Array-valued fields come back decoded; compare them as lists.
            $a = $create->{$field} ?? '';
            $b = $edit->{$field} ?? '';

            if (is_array($a) || is_array($b)) {
                $this->assertSame(
                    $this->asList($a),
                    $this->asList($b),
                    "Create and Edit disagree about how to store '{$field}'."
                );

                continue;
            }

            $this->assertSame(
                (string) $a,
                (string) $b,
                "Create and Edit disagree about how to store '{$field}'."
            );
        }
    }

    /**
     * @test
     *
     * Resuming a draft rehydrates the terms. Create used to write twelve of these
     * and never read them back, so the form came up blank and the next save
     * overwrote the stored answer with that blank.
     */
    public function create_rehydrates_the_terms_when_a_draft_is_resumed(): void
    {
        $auction = $this->makeAuction([
            'lease_option_maintenance'  => 'Buyer',
            'outstanding_balance'       => '245000',
            'nft_transfer_method'       => 'Direct wallet transfer',
            'occupant_tenant'           => 'Lease expires March 2027',
            'balloon_payment'           => 'Yes',
            'assumable_loan_type'       => 'FHA',
            'crypto_custodian_wallet'   => 'Custodial',
            'seller_amortization_type'  => 'Fully Amortized',
            'seller_payment_frequency'  => 'Monthly',
            'seller_late_fee_amount'    => '125',
            'lease_purchase_deposit'    => '15000',
            'offered_financing'         => json_encode(['Cash', 'Seller Financing']),
            'exchange_item'             => json_encode(['Vehicle']),
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->call('loadDraft', $auction->id);

        foreach ([
            'lease_option_maintenance' => 'Buyer',
            'outstanding_balance'      => '245000',
            'nft_transfer_method'      => 'Direct wallet transfer',
            'occupant_tenant'          => 'Lease expires March 2027',
            'balloon_payment'          => 'Yes',
            'assumable_loan_type'      => 'FHA',
            'crypto_custodian_wallet'  => 'Custodial',
            'seller_amortization_type' => 'Fully Amortized',
            'seller_payment_frequency' => 'Monthly',
            'seller_late_fee_amount'   => '125',
            'lease_purchase_deposit'   => '15000',
        ] as $field => $expected) {
            $test->assertSet($field, $expected);
        }

        $this->assertSame(['Cash', 'Seller Financing'], $test->get('offered_financing'));
        $this->assertSame(['Vehicle'], $test->get('exchange_item'));
    }

    /**
     * @test
     *
     * A resumed draft that is saved again keeps its terms. This is the delayed
     * data loss stated end-to-end: load, save without touching anything, and the
     * stored answers must be unchanged rather than blanked.
     */
    public function resuming_and_resaving_a_draft_does_not_blank_the_terms(): void
    {
        $seed = [
            'lease_option_maintenance' => 'Buyer',
            'outstanding_balance'      => '245000',
            'nft_valuation_method'     => 'Third-party appraisal',
            'occupant_tenant'          => 'Lease expires March 2027',
            'assumable_loan_type'      => 'FHA',
            'seller_payment_frequency' => 'Monthly',
        ];

        $auction = $this->makeAuction($seed);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->call('loadDraft', $auction->id)
            ->instance();

        $save = new ReflectionMethod($component, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component, $auction);

        $meta = $auction->fresh()->get;

        foreach ($seed as $field => $expected) {
            $this->assertSame(
                $expected,
                (string) ($meta->{$field} ?? ''),
                "Resaving a resumed draft blanked '{$field}'."
            );
        }
    }

    /**
     * @test
     *
     * Deselecting a financing type still clears its conditional section.
     *
     * updatedOfferedFinancing() is the behaviour that makes the conditional
     * sections meaningful, and it was deliberately NOT moved into the shared
     * trait because Create and Edit implement it differently. Pinned here so the
     * persistence unification cannot be read as having flattened it: the shared
     * save routine must store what the component holds AFTER the clearing has
     * run, not resurrect an abandoned section.
     */
    public function deselecting_a_financing_type_still_clears_its_section(): void
    {
        $auction = $this->makeAuction();

        $test = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->set('offered_financing', ['Seller Financing'])
            ->set('interest_rate', '6.5')
            ->set('loan_duration', '30')
            ->set('seller_down_payment_amount', '50,000');

        // Still held while the section is selected.
        $test->assertSet('interest_rate', '6.5');

        // Deselecting drops the section's answers…
        $test->set('offered_financing', ['Cash'])
            ->assertSet('interest_rate', '')
            ->assertSet('loan_duration', '')
            ->assertSet('seller_down_payment_amount', '');

        // …and the save stores the cleared state, not the abandoned one.
        $component = $test->instance();
        $save      = new ReflectionMethod($component, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component, $auction);

        $meta = $auction->fresh()->get;

        $this->assertSame('', (string) ($meta->interest_rate ?? ''));
        $this->assertSame('', (string) ($meta->loan_duration ?? ''));
        $this->assertSame('', (string) ($meta->seller_down_payment_amount ?? ''));
    }

    /**
     * @test
     *
     * Existing records stay readable. A listing stored before this change has
     * meta rows for some canonical fields and none for the rest; loading it must
     * not fail and must not invent values.
     */
    public function a_record_predating_the_repair_still_loads(): void
    {
        $auction = $this->makeAuction([
            'maximum_budget'       => '525000',
            'possession_preference' => 'At Closing',
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->call('loadDraft', $auction->id);

        $test->assertSet('maximum_budget', '525000')
            ->assertSet('possession_preference', 'At Closing')
            // Never-stored fields stay at their declared defaults, not null.
            ->assertSet('lease_option_maintenance', '')
            ->assertSet('nft_gas_fees', '');

        $this->assertSame([], $test->get('exchange_item'));
        $this->assertSame([], $test->get('offered_financing'));
    }
}
