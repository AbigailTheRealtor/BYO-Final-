<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\User;
use App\Support\CompensationFormatter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Browser QA #1 (Batch 5) — Landlord Commercial Broker Compensation.
 *
 * The Landlord's Broker Lease Fee partial was Residential-only, so on Create/Edit Landlord +
 * Commercial it rendered NOTHING — while every backing prop was already declared, persisted and
 * hydrated in both components. Thirteen existing Commercial listings therefore hold a
 * purchase_fee_type value that no Create/Edit control could display or edit.
 *
 * Product decision (Option A): restore the Commercial branch from the established Hire Landlord
 * implementation. Do NOT expand the Commercial Tenant's-Broker option set — only fix its display
 * defects. Preserve every stored value byte-for-byte.
 *
 * The two spellings below are load-bearing and must never be "tidied":
 *   • 'Flat fee'                    — lowercase f, the legacy tenant_broker_fee_structure value
 *   • 'Percentage of Month’s Rent'  — CURLY apostrophe (U+2019), the stored purchase_fee_type value
 */
class BatchFiveLandlordCommercialLeaseFeeTest extends TestCase
{
    use DatabaseTransactions;

    private const PARTIAL = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/partials/landlord_broker_lease_fee.blade.php';

    /** The canonical Commercial option set, byte-identical to Hire Landlord + the preset config. */
    private const COMMERCIAL_STRUCTURES = [
        'Percentage of the Net Aggregate Rent',
        'Percentage of the Gross Rent',
        "Percentage of Month\u{2019}s Rent", // curly
        'Flat Fee',
        'other',
    ];

    private function makeUser(): User
    {
        return User::factory()->create(['user_type' => 'landlord']);
    }

    /** @param array<string,string> $meta */
    private function makeAuction(User $user, array $meta = []): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Commercial Lease Fee Listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $rows = [['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type', 'meta_value' => 'offer_listing']];
        foreach ($meta as $key => $value) {
            $rows[] = ['landlord_agent_auction_id' => $auction->id, 'meta_key' => $key, 'meta_value' => $value];
        }
        LandlordAgentAuctionMeta::insert($rows);

        return $auction->fresh();
    }

    private function metaValue(LandlordAgentAuction $auction, string $key): ?string
    {
        return LandlordAgentAuctionMeta::where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', $key)
            ->value('meta_value');
    }

    private function partialSource(): string
    {
        return (string) file_get_contents(base_path(self::PARTIAL));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Commercial CREATE rendering
    // ─────────────────────────────────────────────────────────────────────────

    /** The Commercial branch exists at all — it previously did not. */
    public function test_partial_has_a_commercial_branch(): void
    {
        $this->assertStringContainsString(
            "@if (\$property_type === 'Commercial Property')",
            $this->partialSource(),
            'the Landlord broker lease fee partial must render a Commercial branch'
        );
    }

    /** All five Commercial fee structures are offered, byte-for-byte. */
    public function test_commercial_branch_offers_every_fee_structure(): void
    {
        $src = $this->partialSource();

        foreach (self::COMMERCIAL_STRUCTURES as $structure) {
            $this->assertStringContainsString(
                'value="' . $structure . '"',
                $src,
                "the Commercial branch must offer \"$structure\""
            );
        }
    }

    /** Each structure reveals its own already-existing amount field — no new EAV keys. */
    public function test_commercial_branch_binds_the_existing_amount_props(): void
    {
        $src = $this->partialSource();

        foreach ([
            'purchase_fee_net_aggregate',
            'purchase_fee_gross_rent',
            'purchase_fee_monthly_percentage',
            'purchase_fee_months',
            'purchase_fee_flat_commercial',
            'purchase_fee_other_commercial',
            'sales_tax_option_gross',
            'sales_tax_option_monthly',
            'sales_tax_option_flat',
        ] as $prop) {
            $this->assertStringContainsString(
                'wire:model.lazy="' . $prop . '"',
                $src,
                "the Commercial branch must bind the existing prop $prop"
            );
        }
    }

    /** The Month's Rent structure keeps its CURLY apostrophe everywhere it appears. */
    public function test_months_rent_keeps_its_curly_apostrophe(): void
    {
        $src = $this->partialSource();

        $this->assertStringContainsString("Percentage of Month\u{2019}s Rent", $src);
        $this->assertStringNotContainsString(
            "value=\"Percentage of Month's Rent\"",
            $src,
            'the straight-apostrophe spelling would orphan the 6 stored listings — do not normalise it'
        );
    }

    /** Every Commercial structure round-trips through create's write path with its amounts. */
    public function test_create_persists_every_commercial_fee_structure(): void
    {
        $cases = [
            ['Percentage of the Net Aggregate Rent', 'purchase_fee_net_aggregate', '6'],
            ['Percentage of the Gross Rent', 'purchase_fee_gross_rent', '5'],
            ["Percentage of Month\u{2019}s Rent", 'purchase_fee_monthly_percentage', '100'],
        ];

        foreach ($cases as [$structure, $amountKey, $amount]) {
            $user = $this->makeUser();

            Livewire::actingAs($user)
                ->test(LandlordOfferListing::class)
                ->set('property_type', 'Commercial Property')
                ->set('purchase_fee_type', $structure)
                ->set($amountKey, $amount)
                ->call('saveDraft');

            $auction = LandlordAgentAuction::where('user_id', $user->id)->latest('id')->first();
            $this->assertNotNull($auction, 'saveDraft must create the listing');

            $this->assertSame($structure, $this->metaValue($auction, 'purchase_fee_type'), "structure \"$structure\" must persist verbatim");
            $this->assertSame($amount, $this->metaValue($auction, $amountKey), "amount for \"$structure\" must persist");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Commercial EDIT hydration + existing-listing round trip
    // ─────────────────────────────────────────────────────────────────────────

    /** An existing Commercial listing hydrates its previously-uneditable fee into the edit form. */
    public function test_edit_hydrates_an_existing_commercial_listing(): void
    {
        $user    = $this->makeUser();
        $auction = $this->makeAuction($user, [
            'property_type'              => 'Commercial Property',
            'purchase_fee_type'          => 'Percentage of the Net Aggregate Rent',
            'purchase_fee_net_aggregate' => '7',
        ]);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->assertSet('property_type', 'Commercial Property')
            ->assertSet('purchase_fee_type', 'Percentage of the Net Aggregate Rent')
            ->assertSet('purchase_fee_net_aggregate', '7');
    }

    /** The 6 real Month's-Rent listings: curly value hydrates, and the amount is now collectable. */
    public function test_edit_hydrates_the_curly_months_rent_listing_and_can_now_capture_its_amount(): void
    {
        $user    = $this->makeUser();
        $auction = $this->makeAuction($user, [
            'property_type'     => 'Commercial Property',
            'purchase_fee_type' => "Percentage of Month\u{2019}s Rent",
            // Deliberately NO amount — this is the real shape of the 6 historical listings.
        ]);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->assertSet('purchase_fee_type', "Percentage of Month\u{2019}s Rent")
            ->set('purchase_fee_monthly_percentage', '100')
            ->set('purchase_fee_months', '2')
            ->set('sales_tax_option_monthly', 'excluding')
            ->call('saveDraftOnly');

        $this->assertSame("Percentage of Month\u{2019}s Rent", $this->metaValue($auction, 'purchase_fee_type'), 'the curly stored value must survive a re-save byte-identical');
        $this->assertSame('100', $this->metaValue($auction, 'purchase_fee_monthly_percentage'));
        $this->assertSame('2', $this->metaValue($auction, 'purchase_fee_months'));
        $this->assertSame('excluding', $this->metaValue($auction, 'sales_tax_option_monthly'));
    }

    /** Edit strips commas from the commercial flat fee, at parity with create. */
    public function test_edit_strips_commas_from_the_commercial_flat_fee(): void
    {
        $user    = $this->makeUser();
        $auction = $this->makeAuction($user, [
            'property_type'     => 'Commercial Property',
            'purchase_fee_type' => 'Flat Fee',
        ]);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('purchase_fee_flat_commercial', '3,000')
            ->call('saveDraftOnly');

        $this->assertSame('3000', $this->metaValue($auction, 'purchase_fee_flat_commercial'), 'edit must strip commas like create does');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Historical compatibility — no rewrite, no orphaning
    // ─────────────────────────────────────────────────────────────────────────

    /** A re-save must not rewrite, blank or re-case any historical value. */
    public function test_resaving_never_rewrites_historical_values(): void
    {
        $user    = $this->makeUser();
        $auction = $this->makeAuction($user, [
            'property_type'                      => 'Commercial Property',
            'purchase_fee_type'                  => "Percentage of Month\u{2019}s Rent",
            'tenant_broker_commission_structure' => "Landlord to Pay Tenant's Broker Separately",
            'tenant_broker_fee_structure'        => 'Flat fee', // lowercase f — legacy
            'tenant_broker_flat_fee'             => '1000.00',
        ]);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->call('saveDraftOnly');

        $this->assertSame("Percentage of Month\u{2019}s Rent", $this->metaValue($auction, 'purchase_fee_type'), 'curly apostrophe must survive');
        $this->assertSame('Flat fee', $this->metaValue($auction, 'tenant_broker_fee_structure'), "legacy lowercase 'Flat fee' must survive — re-casing would orphan 26 live rows");
        $this->assertSame('1000.00', $this->metaValue($auction, 'tenant_broker_flat_fee'));
    }

    /** Publish validation must not lock out a historical listing that has a structure but no amount. */
    public function test_a_commercial_listing_with_no_amount_still_validates(): void
    {
        $component = new LandlordOfferListing();
        $component->property_type     = 'Commercial Property';
        $component->purchase_fee_type = "Percentage of Month\u{2019}s Rent";
        $component->auction_type      = 'Traditional';

        $method = new ReflectionMethod($component, 'getConditionalRules');
        $method->setAccessible(true);
        $rules = $method->invoke($component);

        $this->assertSame('nullable|numeric|min:0', $rules['purchase_fee_monthly_percentage']);
        $this->assertStringStartsWith('nullable', $rules['purchase_fee_months']);
        $this->assertStringNotContainsString('required', $rules['purchase_fee_monthly_percentage'], 'requiring the amount would lock the 6 historical listings out of re-publishing');
    }

    /** "Other" is meaningless without its description — the one required field (pet-fee precedent). */
    public function test_other_requires_its_description_only_when_other_is_selected(): void
    {
        $component = new LandlordOfferListing();
        $component->property_type = 'Commercial Property';
        $component->auction_type  = 'Traditional';

        $method = new ReflectionMethod($component, 'getConditionalRules');
        $method->setAccessible(true);

        $component->purchase_fee_type = 'other';
        $this->assertStringContainsString('required', $method->invoke($component)['purchase_fee_other_commercial']);

        $component->purchase_fee_type = 'Flat Fee';
        $this->assertStringStartsWith('nullable', $method->invoke($component)['purchase_fee_other_commercial']);
    }

    /** Residential regression: no Commercial rule leaks onto a Residential listing. */
    public function test_residential_gains_no_commercial_validation(): void
    {
        $component = new LandlordOfferListing();
        $component->property_type = 'Residential Property';
        $component->auction_type  = 'Traditional';

        $method = new ReflectionMethod($component, 'getConditionalRules');
        $method->setAccessible(true);
        $rules = $method->invoke($component);

        foreach ([
            'purchase_fee_net_aggregate',
            'purchase_fee_gross_rent',
            'purchase_fee_monthly_percentage',
            'purchase_fee_months',
            'purchase_fee_flat_commercial',
            'purchase_fee_other_commercial',
        ] as $commercialOnly) {
            $this->assertArrayNotHasKey($commercialOnly, $rules, "$commercialOnly is Commercial-only and must not be validated on a Residential listing");
        }
    }

    /** Residential regression: the Residential branch and its props are untouched. */
    public function test_residential_branch_is_unchanged(): void
    {
        $src = $this->partialSource();

        $this->assertStringContainsString("@if (\$property_type === 'Residential Property')", $src);
        foreach ([
            'Percentage of the Rent Due Each Rental Period',
            'Percentage of the Gross Lease Value',
            "Percentage of the First Month\u{2019}s Rent",
        ] as $residentialOption) {
            $this->assertStringContainsString('value="' . $residentialOption . '"', $src);
        }
        $this->assertStringContainsString('wire:model.lazy="purchase_fee_rental_period"', $src);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formatter
    // ─────────────────────────────────────────────────────────────────────────

    private function formatted(array $meta, string $label): ?string
    {
        $rows = CompensationFormatter::formatPresetRows('landlord', $meta['property_type'] ?? '', $meta);
        foreach ($rows as $row) {
            if (($row['label'] ?? null) === $label) {
                return $row['value'] ?? null;
            }
        }

        return null;
    }

    /**
     * The Commercial tenant-broker options had NO formatter branch, so the amount was silently
     * dropped and only the bare structure label rendered.
     */
    public function test_formatter_renders_commercial_tenant_broker_amounts(): void
    {
        $base = [
            'property_type'                      => 'Commercial Property',
            'tenant_broker_commission_structure' => "Landlord to Pay Tenant's Broker Separately",
        ];

        $netAggregate = $this->formatted($base + [
            'tenant_broker_fee_structure' => 'Percentage of the Net Aggregate Rent',
            'tenant_broker_percentage'    => '6',
        ], "Tenant's Broker Commission Fee");
        $this->assertStringContainsString('6', (string) $netAggregate);
        $this->assertStringContainsString('Net Aggregate Rent', (string) $netAggregate);

        $grossRent = $this->formatted($base + [
            'tenant_broker_fee_structure' => 'Percentage of the Gross Rent',
            'tenant_broker_gross_lease'   => '5',
        ], "Tenant's Broker Commission Fee");
        $this->assertStringContainsString('5', (string) $grossRent);
        $this->assertStringContainsString('Gross Rent', (string) $grossRent);
    }

    /** The legacy lowercase 'Flat fee' still formats — the formatter must stay case-tolerant. */
    public function test_formatter_still_handles_legacy_flat_fee_spelling(): void
    {
        $value = $this->formatted([
            'property_type'                      => 'Commercial Property',
            'tenant_broker_commission_structure' => "Landlord to Pay Tenant's Broker Separately",
            'tenant_broker_fee_structure'        => 'Flat fee',
            'tenant_broker_flat_fee'             => '1000.00',
        ], "Tenant's Broker Commission Fee");

        $this->assertStringContainsString('1,000', (string) $value);
    }

    /** Residential tenant-broker formatting is unchanged. */
    public function test_formatter_residential_tenant_broker_is_unchanged(): void
    {
        $value = $this->formatted([
            'property_type'                      => 'Residential Property',
            'tenant_broker_commission_structure' => "Landlord to Pay Tenant's Broker Separately",
            'tenant_broker_fee_structure'        => 'Percentage of the Rent Due Each Rental Period',
            'tenant_broker_percentage'           => '10',
        ], "Tenant's Broker Commission Fee");

        $this->assertStringContainsString('10', (string) $value);
        $this->assertStringContainsString('Rent Due Each Rental Period', (string) $value);
    }

    /** The curly-apostrophe Month's Rent lease fee renders its percentage and month count. */
    public function test_formatter_renders_curly_months_rent_lease_fee(): void
    {
        $value = $this->formatted([
            'property_type'                   => 'Commercial Property',
            'purchase_fee_type'               => "Percentage of Month\u{2019}s Rent",
            'purchase_fee_monthly_percentage' => '100',
            'purchase_fee_months'             => '2',
        ], "Landlord's Broker Lease Fee");

        $this->assertStringContainsString('100', (string) $value);
        $this->assertStringContainsString('2', (string) $value);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accepted Bid summary / PDF
    // ─────────────────────────────────────────────────────────────────────────

    private function leaseFeeDisplay(array $meta): ?string
    {
        $service = app(\App\Services\LandlordAcceptedBidSummaryService::class);
        $method  = new ReflectionMethod($service, 'resolveLandlordListingFeeDisplay');
        $method->setAccessible(true);

        return $method->invoke($service, $meta);
    }

    /**
     * The Rental Period option read purchase_fee_gross_rent and labelled it "of Gross Rent" — the
     * wrong key AND the wrong label. Every other reader resolves it to purchase_fee_rental_period.
     */
    public function test_pdf_rental_period_reads_the_correct_field(): void
    {
        $display = $this->leaseFeeDisplay([
            'purchase_fee_type'          => 'Percentage of the Rent Due Each Rental Period',
            'purchase_fee_rental_period' => '5',
            'purchase_fee_gross_rent'    => '99', // the wrong field it used to read — must be ignored
        ]);

        $this->assertStringContainsString('5', (string) $display);
        $this->assertStringContainsString('Rent Due Each Rental Period', (string) $display);
        $this->assertStringNotContainsString('99', (string) $display, 'must no longer read purchase_fee_gross_rent for this option');
    }

    /** Gross Rent and Month's Rent had no branch, so their PDF rows silently vanished. */
    public function test_pdf_renders_the_commercial_lease_fee_structures(): void
    {
        $grossRent = $this->leaseFeeDisplay([
            'purchase_fee_type'       => 'Percentage of the Gross Rent',
            'purchase_fee_gross_rent' => '5',
        ]);
        $this->assertNotNull($grossRent, 'the Gross Rent row must no longer vanish from the PDF');
        $this->assertStringContainsString('Gross Rent', $grossRent);

        $monthsRent = $this->leaseFeeDisplay([
            'purchase_fee_type'               => "Percentage of Month\u{2019}s Rent", // curly, as stored
            'purchase_fee_monthly_percentage' => '100',
            'purchase_fee_months'             => '2',
        ]);
        $this->assertNotNull($monthsRent, 'the curly-apostrophe Month\'s Rent row must no longer vanish from the PDF');
        $this->assertStringContainsString('100', $monthsRent);
        $this->assertStringContainsString('2', $monthsRent);
    }

    /** Net Aggregate Rent (the only Commercial structure that already worked) still works. */
    public function test_pdf_net_aggregate_still_renders(): void
    {
        $display = $this->leaseFeeDisplay([
            'purchase_fee_type'          => 'Percentage of the Net Aggregate Rent',
            'purchase_fee_net_aggregate' => '3',
        ]);

        $this->assertStringContainsString('3', (string) $display);
        $this->assertStringContainsString('Net Aggregate Rent', (string) $display);
    }
}
