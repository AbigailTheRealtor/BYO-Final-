<?php

namespace Tests\Feature\OfferListing;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * YOUR TERMS AND THE QUESTIONS THAT APPEAR UNDERNEATH THEM.
 *
 * The Sale Terms and Leasing Terms tabs are full of parent answers that reveal
 * follow-up questions: choose "Other" and a text box appears; choose
 * "Exchange/Trade" and eight more questions appear; choose "Assignment Contract"
 * and a fee structure appears. All of them persisted correctly. Several of them
 * were never rendered on the published listing at all, and several more were
 * rendered under conditions that did not match the question that produced them.
 *
 * THE RULE, IN TWO HALVES:
 *
 *   THE PARENT DECIDES WHETHER A BRANCH APPEARS. A child value left behind by a
 *   selection the user has since changed is data we still hold and deliberately
 *   do not publish.
 *
 *   THE CHILD DECIDES WHETHER ITS OWN ROW APPEARS. An applicable branch prints
 *   nothing for a question that was skipped.
 *
 * ONE RENDERER, BOTH ORIGINS. Every case here is asserted on a plain manually
 * created listing, because the seller and landlord listing views are the single
 * renderer for manual, MLS-prefilled and MLS-quick-imported listings alike —
 * they all write the same meta keys. MlsListingDetailPresentationTest covers the
 * imported side of the same page.
 */
class YourTermsConditionalDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string,mixed> $meta */
    private function sellerPage(array $meta): string
    {
        $listing = new SellerAgentAuction();
        $listing->user_id     = User::factory()->create()->id;
        $listing->is_draft    = 0;
        $listing->is_approved = true;
        $listing->title       = 'Terms fixture';
        $listing->address     = 'Terms fixture';
        $listing->save();

        foreach ($meta + ['auction_type' => 'Traditional'] as $key => $value) {
            $listing->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        ListingWorkflow::stamp($listing, ListingWorkflow::OFFER_LISTING);

        $response = $this->get(route('offer.listing.seller.view', ['id' => $listing->id]));
        $response->assertStatus(200);

        return $response->getContent();
    }

    /** @param array<string,mixed> $meta */
    private function landlordPage(array $meta): string
    {
        $listing = new LandlordAgentAuction();
        $listing->user_id     = User::factory()->create()->id;
        $listing->is_draft    = 0;
        $listing->is_approved = true;
        $listing->title       = 'Lease terms fixture';
        $listing->save();

        foreach ($meta + ['auction_type' => 'Traditional', 'address' => 'Lease terms fixture'] as $key => $value) {
            $listing->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        ListingWorkflow::stamp($listing, ListingWorkflow::OFFER_LISTING);

        $response = $this->get(route('offer.listing.landlord.view', ['id' => $listing->id]));
        $response->assertStatus(200);

        return $response->getContent();
    }

    // ══ Special Sale Provision ═══════════════════════════════════════════════

    /**
     * @test
     *
     * The parent answer itself, and its "Other" text — which the page used to
     * lose entirely whenever "Other" was chosen ALONGSIDE another provision,
     * because it compared the whole joined string against the word "Other".
     */
    public function the_special_sale_provision_other_text_renders_beside_another_provision(): void
    {
        $html = $this->sellerPage([
            'sale_provision'       => ['Short Sale', 'Other'],
            'sale_provision_other' => 'Divorce sale, third-party approval required',
        ]);

        $this->assertStringContainsString('Special Sale Provision', $html);
        $this->assertStringContainsString('Short Sale', $html);
        $this->assertStringContainsString('Divorce sale, third-party approval required', $html);
    }

    /**
     * @test
     *
     * Choosing "Assignment Contract" reveals whether the seller is under contract
     * and, only if they are, the fee structure. Each level is gated by the answer
     * above it.
     */
    public function the_assignment_contract_follow_ups_render_when_that_provision_is_chosen(): void
    {
        $html = $this->sellerPage([
            'sale_provision'            => ['Assignment Contract'],
            'sale_provision_assignment' => 'Yes',
            'assignment_fee_type'       => '%',
            'assignment_fee_amount'     => '3',
        ]);

        $this->assertStringContainsString('Seller Under Contract for Assignment', $html);
        $this->assertStringContainsString('Assignment Contract Fee to Broker', $html);
        $this->assertStringContainsString('Assignment Fee Amount', $html);
        // Formatted by the toggle beside it, not as dollars.
        $this->assertStringContainsString('3%', $html);
    }

    /**
     * @test
     *
     * "No" closes the fee branch. The page used to gate the fee rows on
     * `sale_provision_assignment` being SET, so a seller who answered No was
     * shown an assignment fee structure they had never entered.
     */
    public function answering_no_to_the_assignment_question_hides_the_fee_rows(): void
    {
        $html = $this->sellerPage([
            'sale_provision'            => ['Assignment Contract'],
            'sale_provision_assignment' => 'No',
            'assignment_fee_type'       => '%',
            'assignment_fee_amount'     => '3',
        ]);

        $this->assertStringContainsString('Seller Under Contract for Assignment', $html);
        $this->assertStringNotContainsString('Assignment Fee Amount', $html);
    }

    /**
     * @test
     *
     * A STALE CHILD. The seller once selected Assignment Contract, answered the
     * follow-ups, then deselected it. The answers are still stored — and must not
     * be published, because the seller's current answer is the one the page states.
     */
    public function assignment_answers_are_not_published_once_that_provision_is_deselected(): void
    {
        $html = $this->sellerPage([
            'sale_provision'            => ['Short Sale'],
            'sale_provision_assignment' => 'Yes',
            'assignment_fee_amount'     => '3',
        ]);

        $this->assertStringContainsString('Short Sale', $html);
        $this->assertStringNotContainsString('Seller Under Contract for Assignment', $html);
        $this->assertStringNotContainsString('Assignment Fee Amount', $html);
    }

    // ══ Offered Financing / Currency ═════════════════════════════════════════

    /**
     * @test
     *
     * Exchange/Trade opens eight follow-ups. THREE of them could never render:
     * the exchange items themselves (the page printed only the "Other" text box),
     * the liens disclosure (read under `exchange_liens`, a key no flow writes),
     * and how the item's value was determined (printed in the Property Details
     * card, where it read as a fact about the house).
     */
    public function the_exchange_trade_follow_ups_render_in_full(): void
    {
        $html = $this->sellerPage([
            'offered_financing'         => ['Exchange/Trade'],
            'exchange_item'             => ['Vehicle', 'Other'],
            'other_exchange_item'       => 'A 32ft sailing boat',
            'exchange_item_value'       => '85000',
            'exchange_item_condition'   => 'Excellent',
            'value_determination'       => 'Independent marine survey',
            'exchange_transfer_method'  => 'Title transfer at closing',
            'exchange_liens_disclosure' => 'Yes',
            'exchange_liens_details'    => 'Outstanding marine loan',
            'exchange_inspection_rights' => 'Buyer may inspect before closing',
        ]);

        $this->assertStringContainsString('Exchange / Trade', $html);
        $this->assertStringContainsString('Vehicle', $html);
        $this->assertStringContainsString('A 32ft sailing boat', $html);
        $this->assertStringContainsString('Independent marine survey', $html);
        $this->assertStringContainsString('Outstanding marine loan', $html);
        $this->assertStringContainsString('$85,000', $html);
    }

    /**
     * @test
     *
     * Assumable opens a fee, and beside it the question of who pays it. That
     * answer has been stored since A6.31 and rendered on no page until now.
     */
    public function the_assumption_fee_responsibility_renders(): void
    {
        $html = $this->sellerPage([
            'offered_financing'             => ['Assumable'],
            'assumable_loan_type'           => 'FHA',
            'assumable_fee_type'            => '$',
            'assumable_fee_amount'          => '1200',
            'assumption_fee_responsibility' => 'Split 50/50',
        ]);

        $this->assertStringContainsString('Assumption Fee Responsibility', $html);
        $this->assertStringContainsString('Split 50/50', $html);
    }

    /**
     * @test
     *
     * Seller Financing asks whether a prepayment penalty applies before asking
     * how much. The page printed only the amount, so "penalty applies, amount to
     * be negotiated" published as no penalty at all.
     */
    public function the_prepayment_penalty_answer_renders_before_its_amount(): void
    {
        $html = $this->sellerPage([
            'offered_financing'         => ['Seller Financing'],
            'prepayment_penalty'        => 'Yes',
            'prepayment_penalty_amount' => '5000',
        ]);

        $this->assertStringContainsString('Prepayment Penalty', $html);
        $this->assertStringContainsString('$5,000', $html);
    }

    /** @test */
    public function a_prepayment_amount_is_hidden_when_no_penalty_applies(): void
    {
        $html = $this->sellerPage([
            'offered_financing'         => ['Seller Financing'],
            'prepayment_penalty'        => 'No',
            'prepayment_penalty_amount' => '5000',
        ]);

        $this->assertStringContainsString('Prepayment Penalty', $html);
        $this->assertStringNotContainsString('$5,000', $html);
    }

    /**
     * @test
     *
     * A STALE BRANCH. The listing offers cash only, but an assumable loan type
     * survives from an earlier draft. The page used to re-open the entire
     * Assumable Mortgage section on the strength of that one leftover value.
     */
    public function a_financing_branch_is_not_reopened_by_a_leftover_child_value(): void
    {
        $html = $this->sellerPage([
            'offered_financing'    => ['Cash'],
            'assumable_loan_type'  => 'FHA',
            'assumable_terms'      => 'Assumable at 3.1%',
            'cryptocurrency_type'  => 'BTC',
            'lease_option_price'   => '250000',
            'nft_description'      => 'A leftover NFT description',
        ]);

        $this->assertStringNotContainsString('Assumable Mortgage', $html);
        $this->assertStringNotContainsString('Assumable at 3.1%', $html);
        $this->assertStringNotContainsString('BTC', $html);
        $this->assertStringNotContainsString('A leftover NFT description', $html);
    }

    /**
     * @test
     *
     * An applicable branch with no answers in it prints no heading — the branch
     * is open, the seller simply has not answered anything under it yet.
     */
    public function an_answered_nothing_branch_prints_no_heading(): void
    {
        $html = $this->sellerPage(['offered_financing' => ['Exchange/Trade']]);

        $this->assertStringNotContainsString('Exchange / Trade', $html);
    }

    // ══ Deposits and their $ / % control ════════════════════════════════════

    /**
     * @test
     *
     * Both deposits pair an amount with a $ / % control. The page formatted them
     * as dollars unconditionally, so a 3% initial deposit published as "$3".
     */
    public function a_percentage_deposit_is_not_published_as_dollars(): void
    {
        $html = $this->sellerPage([
            'initial_deposit_type'         => '%',
            'initial_deposit_requested'    => '3',
            'additional_deposit_type'      => '$',
            'additional_deposit_requested' => '10000',
        ]);

        $this->assertStringContainsString('3%', $html);
        $this->assertStringContainsString('$10,000', $html);
        $this->assertStringNotContainsString('>$3<', $html);
    }

    // ══ Landlord Leasing Terms ══════════════════════════════════════════════

    /**
     * @test
     *
     * Four landlord "Other" boxes were stored by every entry path and rendered by
     * none: the lease term, what is included in rent, and both utility splits.
     */
    public function the_landlord_other_follow_ups_render(): void
    {
        $html = $this->landlordPage([
            'desired_lease_length' => ['12 Months', 'Other'],
            'other_lease_term'     => '8 Months',
            'rent_includes'        => ['Water', 'Other'],
            'other_rent_include'   => 'Weekly landscaping',
            'tenant_pays'          => ['Electric', 'Other'],
            'other_tenant_pays'    => 'Pool service',
            'owner_pays'           => ['Water', 'Other'],
            'other_owner_pays'     => 'Pest control',
        ]);

        $this->assertStringContainsString('8 Months', $html);
        $this->assertStringContainsString('Weekly landscaping', $html);
        $this->assertStringContainsString('Pool service', $html);
        $this->assertStringContainsString('Pest control', $html);
    }

    /**
     * @test
     *
     * "Other" on Terms of Lease opens `custom_lease_term`, and the commercial
     * single-unit storage pair is the one storage variant of four the page never
     * consulted.
     */
    public function the_landlord_commercial_follow_ups_render(): void
    {
        $html = $this->landlordPage([
            'property_type'                     => 'Commercial Property',
            'terms_of_lease'                    => ['NNN', 'Other'],
            'custom_lease_term'                 => 'Modified gross with CAM cap',
            'included_storage_space_com_single' => 'Yes',
            'storage_space_com_single'          => '6x10 storage bay',
            'space_features'                    => 'Open floor plan, private offices',
            'neighboring_tenants'               => 'Target, Starbucks',
        ]);

        $this->assertStringContainsString('Modified gross with CAM cap', $html);
        $this->assertStringContainsString('6x10 storage bay', $html);
        $this->assertStringContainsString('Open floor plan, private offices', $html);
        $this->assertStringContainsString('Target, Starbucks', $html);
    }

    /**
     * @test
     *
     * An "Other" that was chosen but never described keeps the literal word: the
     * landlord did choose it, and dropping the row would state that nothing extra
     * is included.
     */
    public function an_undescribed_other_keeps_the_literal_option(): void
    {
        $html = $this->landlordPage([
            'rent_includes'      => ['Water', 'Other'],
            'other_rent_include' => '',
        ]);

        $this->assertStringContainsString('Rent Includes', $html);
        $this->assertStringContainsString('Other', $html);
    }

    // ══ Nothing blank ═══════════════════════════════════════════════════════

    /**
     * @test
     *
     * No row is ever printed with an empty value, whichever branch is open.
     */
    public function no_row_is_rendered_with_an_empty_value(): void
    {
        $html = $this->sellerPage([
            'offered_financing'   => ['Cash', 'Seller Financing'],
            'cash_budget'         => '',
            'pre_approved'        => '',
            'interest_rate'       => '5.5',
            'loan_duration'       => '',
            'sale_provision'      => [],
        ]);

        $this->assertSame(
            0,
            preg_match_all('/<div class="col-md-7"[^>]*>\s*<\/div>/', $html),
            'a listing page must never print a row with no value',
        );
    }
}
