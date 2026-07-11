<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\User;
use App\Services\Canonical\Adapters\ByoListingAdapter;
use App\Services\Dna\Scores\PetFriendlinessScoreService;
use App\Services\Pets\PetFeeNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * #2 Part B — Landlord Pet Policy redesign.
 *
 * One canonical pet fee (pet_fee_type + pet_fee_amount + pet_fee_other) replaces the five
 * retired legacy fee fields in the UI and the write path, WITHOUT destroying historical
 * data. The five legacy keys are never written, never blanked and never deleted; readers
 * resolve them through PetFeeNormalizer's precedence:
 *
 *     canonical → structured legacy amounts → pet_deposit_fee_rent free text → nothing
 *
 * The separate pet POLICY / RESTRICTION fields (pet_policy, pet_max_weight_lbs,
 * pet_species_allowed, pet_policy_requirement) are NOT fee fields and must survive
 * untouched.
 */
class LandlordPetFeeRedesignTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::factory()->create(['user_type' => 'landlord']);
    }

    /** @param array<string,string> $meta */
    private function makeAuction(User $user, array $meta = []): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Pet Fee Redesign Listing',
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

    private function normalize(array $meta): array
    {
        return (new PetFeeNormalizer())->normalize($meta);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Canonical types
    // ─────────────────────────────────────────────────────────────────────────

    /** The approved dropdown vocabulary is exactly the checkpoint's five values. */
    public function test_the_approved_fee_types_are_the_checkpoint_vocabulary(): void
    {
        $this->assertSame([
            'One Time Fee Refundable',
            'Non Refundable',
            'Monthly Pet Fee',
            'No Pet Fee',
            'Other',
        ], PetFeeNormalizer::TYPES);
    }

    public function test_one_time_refundable_normalizes_with_its_amount(): void
    {
        $r = $this->normalize(['pet_fee_type' => 'One Time Fee Refundable', 'pet_fee_amount' => '300']);

        $this->assertSame('One Time Fee Refundable', $r['type']);
        $this->assertSame(300.0, $r['amount']);
        $this->assertFalse($r['recurring']);      // one-time
        $this->assertTrue($r['has_fee']);
        $this->assertSame('canonical', $r['source']);
    }

    public function test_non_refundable_normalizes_with_its_amount(): void
    {
        $r = $this->normalize(['pet_fee_type' => 'Non Refundable', 'pet_fee_amount' => '150']);

        $this->assertSame('Non Refundable', $r['type']);
        $this->assertSame(150.0, $r['amount']);
        $this->assertFalse($r['recurring']);
        $this->assertTrue($r['has_fee']);
    }

    public function test_monthly_pet_fee_normalizes_as_recurring(): void
    {
        $r = $this->normalize(['pet_fee_type' => 'Monthly Pet Fee', 'pet_fee_amount' => '50']);

        $this->assertSame('Monthly Pet Fee', $r['type']);
        $this->assertSame(50.0, $r['amount']);
        $this->assertTrue($r['recurring']);
        $this->assertTrue($r['has_fee']);
    }

    public function test_no_pet_fee_carries_no_amount_and_no_text(): void
    {
        $r = $this->normalize([
            'pet_fee_type'   => 'No Pet Fee',
            'pet_fee_amount' => '99',   // ignored — "No Pet Fee" means no charge
            'pet_fee_other'  => 'junk',
        ]);

        $this->assertSame('No Pet Fee', $r['type']);
        $this->assertNull($r['amount']);
        $this->assertNull($r['other_text']);
        $this->assertFalse($r['has_fee']);
    }

    /** "Other" with text only — no amount is invented. */
    public function test_other_with_text_only(): void
    {
        $r = $this->normalize([
            'pet_fee_type'  => 'Other',
            'pet_fee_other' => 'Pet fee determined based on number of pets',
        ]);

        $this->assertSame('Other', $r['type']);
        $this->assertNull($r['amount']);
        $this->assertSame('Pet fee determined based on number of pets', $r['other_text']);
        $this->assertTrue($r['has_fee']);
        $this->assertNull($r['recurring'], 'Other must NOT be classified recurring or one-time');
    }

    /** "Other" with the approved combined refundable + non-refundable example. */
    public function test_other_with_combined_refundable_and_non_refundable_example(): void
    {
        $r = $this->normalize([
            'pet_fee_type'   => 'Other',
            'pet_fee_amount' => '300',
            'pet_fee_other'  => '$100 refundable deposit and $200 non-refundable fee',
        ]);

        $this->assertSame('Other', $r['type']);
        $this->assertSame(300.0, $r['amount'], 'the optional amount under Other is preserved');
        $this->assertSame('$100 refundable deposit and $200 non-refundable fee', $r['other_text']);
        $this->assertTrue($r['has_fee']);
        $this->assertNull($r['recurring']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Legacy normalization
    // ─────────────────────────────────────────────────────────────────────────

    public function test_legacy_pet_deposit_amount_only(): void
    {
        $r = $this->normalize(['pet_deposit_amount' => '300']);

        $this->assertSame('One Time Fee Refundable', $r['type']);
        $this->assertSame(300.0, $r['amount']);
        $this->assertSame('legacy_structured', $r['source']);
    }

    public function test_legacy_pet_fee_only_is_non_refundable(): void
    {
        $r = $this->normalize(['pet_fee' => '150']);

        $this->assertSame('Non Refundable', $r['type']);
        $this->assertSame(150.0, $r['amount']);
    }

    public function test_legacy_pet_monthly_fee_only(): void
    {
        $r = $this->normalize(['pet_monthly_fee' => '50']);

        $this->assertSame('Monthly Pet Fee', $r['type']);
        $this->assertSame(50.0, $r['amount']);
        $this->assertTrue($r['recurring']);
    }

    /** Product decision: pet_rent alone IS a Monthly Pet Fee; its origin stays diagnosable. */
    public function test_legacy_pet_rent_only_becomes_monthly_pet_fee(): void
    {
        $r = $this->normalize(['pet_rent' => '75']);

        $this->assertSame('Monthly Pet Fee', $r['type']);
        $this->assertSame(75.0, $r['amount']);
        $this->assertTrue($r['recurring']);
        $this->assertSame(['pet_rent'], $r['legacy_source_fields'], 'source field must remain diagnosable');
    }

    /** Same recurring value recorded in both fields is ONE charge, not two. */
    public function test_equal_pet_rent_and_pet_monthly_fee_collapse_to_one_monthly_fee(): void
    {
        $r = $this->normalize(['pet_monthly_fee' => '50', 'pet_rent' => '50']);

        $this->assertSame('Monthly Pet Fee', $r['type']);
        $this->assertSame(50.0, $r['amount']);
        $this->assertContains('pet_monthly_fee', $r['legacy_source_fields']);
        $this->assertContains('pet_rent', $r['legacy_source_fields']);
    }

    /** Conflicting recurring values → Other, BOTH preserved, neither discarded. */
    public function test_conflicting_recurring_amounts_become_other_preserving_both(): void
    {
        $r = $this->normalize(['pet_monthly_fee' => '50', 'pet_rent' => '75']);

        $this->assertSame('Other', $r['type']);
        $this->assertStringContainsString('$50 monthly pet fee', $r['other_text']);
        $this->assertStringContainsString('$75 pet rent', $r['other_text']);
        $this->assertNull($r['recurring']);
    }

    /** The headline case: a refundable deposit AND a non-refundable fee. */
    public function test_refundable_deposit_plus_non_refundable_fee_becomes_other_preserving_both(): void
    {
        $r = $this->normalize(['pet_deposit_amount' => '100', 'pet_fee' => '200']);

        $this->assertSame('Other', $r['type']);
        $this->assertSame('$100 refundable deposit and $200 non-refundable fee', $r['other_text']);
        $this->assertTrue($r['has_fee']);
    }

    /** Every legacy amount survives when several are populated. */
    public function test_multiple_legacy_fee_values_are_all_preserved_as_other(): void
    {
        $r = $this->normalize([
            'pet_deposit_amount' => '100',
            'pet_fee'            => '200',
            'pet_monthly_fee'    => '50',
        ]);

        $this->assertSame('Other', $r['type']);
        foreach (['$100 refundable deposit', '$200 non-refundable fee', '$50 monthly pet fee'] as $fragment) {
            $this->assertStringContainsString($fragment, $r['other_text']);
        }
    }

    /** pet_deposit_fee_rent free text is surfaced verbatim and NEVER parsed. */
    public function test_legacy_free_text_pet_deposit_fee_rent_is_passed_through(): void
    {
        $r = $this->normalize(['pet_deposit_fee_rent' => 'Deposit equal to one month rent']);

        $this->assertSame('Other', $r['type']);
        $this->assertSame('Deposit equal to one month rent', $r['other_text']);
        $this->assertSame('legacy_free_text', $r['source']);
        $this->assertTrue($r['has_fee']);
    }

    /** Free text coexisting with structured amounts → Other, everything preserved. */
    public function test_free_text_coexisting_with_amounts_preserves_all_values(): void
    {
        $r = $this->normalize([
            'pet_deposit_amount'   => '100',
            'pet_deposit_fee_rent' => 'plus a cleaning charge at move-out',
        ]);

        $this->assertSame('Other', $r['type']);
        $this->assertStringContainsString('$100 refundable deposit', $r['other_text']);
        $this->assertStringContainsString('plus a cleaning charge at move-out', $r['other_text']);
    }

    /** Canonical outranks stale legacy rows left in storage. */
    public function test_canonical_wins_over_stale_legacy_values(): void
    {
        $r = $this->normalize([
            'pet_fee_type'       => 'Monthly Pet Fee',
            'pet_fee_amount'     => '60',
            'pet_deposit_amount' => '999',   // stale legacy row, still in storage
            'pet_fee'            => '888',
        ]);

        $this->assertSame('Monthly Pet Fee', $r['type']);
        $this->assertSame(60.0, $r['amount']);
        $this->assertSame('canonical', $r['source']);
    }

    public function test_no_pet_information_at_all(): void
    {
        $r = $this->normalize([]);

        $this->assertNull($r['type']);
        $this->assertFalse($r['has_fee']);
        $this->assertSame('none', $r['source']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Persistence — create + edit
    // ─────────────────────────────────────────────────────────────────────────

    private function saveMetadata($component, LandlordAgentAuction $auction): void
    {
        $save = new ReflectionMethod($component::class, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component, $auction);
    }

    public function test_create_persists_the_canonical_pet_fee(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeAuction($owner);

        $component = Livewire::actingAs($owner)
            ->test(LandlordOfferListing::class)
            ->set('pet_fee_type', 'Monthly Pet Fee')
            ->set('pet_fee_amount', '50');

        $this->saveMetadata($component->instance(), $auction);

        $fresh = $auction->fresh();
        $this->assertSame('Monthly Pet Fee', $fresh->info('pet_fee_type'));
        $this->assertSame('50', (string) $fresh->info('pet_fee_amount'));
    }

    /** "No Pet Fee" clears the amount and the explanatory text in the submitted values. */
    public function test_no_pet_fee_clears_amount_and_other_on_save(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeAuction($owner);

        $component = Livewire::actingAs($owner)
            ->test(LandlordOfferListing::class)
            ->set('pet_fee_type', 'Other')
            ->set('pet_fee_amount', '300')
            ->set('pet_fee_other', 'something')
            ->set('pet_fee_type', 'No Pet Fee');   // user changes their mind

        $this->saveMetadata($component->instance(), $auction);

        $fresh = $auction->fresh();
        $this->assertSame('No Pet Fee', $fresh->info('pet_fee_type'));
        $this->assertSame('', (string) $fresh->info('pet_fee_amount'));
        $this->assertSame('', (string) $fresh->info('pet_fee_other'));
    }

    public function test_other_persists_its_amount_and_explanatory_text(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeAuction($owner);

        $component = Livewire::actingAs($owner)
            ->test(LandlordOfferListing::class)
            ->set('pet_fee_type', 'Other')
            ->set('pet_fee_amount', '300')
            ->set('pet_fee_other', '$100 refundable deposit and $200 non-refundable fee');

        $this->saveMetadata($component->instance(), $auction);

        $fresh = $auction->fresh();
        $this->assertSame('Other', $fresh->info('pet_fee_type'));
        $this->assertSame('300', (string) $fresh->info('pet_fee_amount'));
        $this->assertSame('$100 refundable deposit and $200 non-refundable fee', $fresh->info('pet_fee_other'));
    }

    /** THE data-protection test: saving must not blank untouched legacy EAV values. */
    public function test_saving_does_not_erase_legacy_fee_values(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeAuction($owner, [
            'pet_deposit_amount'   => '100',
            'pet_monthly_fee'      => '50',
            'pet_rent'             => '75',
            'pet_fee'              => '200',
            'pet_deposit_fee_rent' => 'legacy free text',
        ]);

        $component = Livewire::actingAs($owner)
            ->test(LandlordOfferListing::class)
            ->set('pet_fee_type', 'Monthly Pet Fee')
            ->set('pet_fee_amount', '60');

        $this->saveMetadata($component->instance(), $auction);

        $fresh = $auction->fresh();
        $this->assertSame('100', (string) $fresh->info('pet_deposit_amount'));
        $this->assertSame('50', (string) $fresh->info('pet_monthly_fee'));
        $this->assertSame('75', (string) $fresh->info('pet_rent'));
        $this->assertSame('200', (string) $fresh->info('pet_fee'));
        $this->assertSame('legacy free text', $fresh->info('pet_deposit_fee_rent'));
    }

    /**
     * The pet POLICY / RESTRICTION fields are not fee fields and must survive an edit.
     *
     * Exercised through the EDIT component, which is the only path that loads an existing
     * record. (The create component always writes its own props to a NEW auction, so
     * pointing it at a pre-seeded one would prove nothing about this invariant.)
     */
    public function test_pet_policy_and_restriction_fields_are_not_removed(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeAuction($owner, [
            'pet_max_weight_lbs'     => '40',
            'pet_species_allowed'    => json_encode(['Dog', 'Cat']),
            'pet_policy_requirement' => json_encode(['Vaccination records']),
            'pet_restrictions'       => 'No aggressive breeds',
        ]);

        $component = Livewire::actingAs($owner)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('pet_fee_type', 'No Pet Fee');

        $this->saveMetadata($component->instance(), $auction);

        $fresh = $auction->fresh();
        $this->assertSame('40', (string) $fresh->info('pet_max_weight_lbs'));
        $this->assertSame(['Dog', 'Cat'], json_decode($fresh->info('pet_species_allowed'), true));
        $this->assertSame(['Vaccination records'], json_decode($fresh->info('pet_policy_requirement'), true));
        $this->assertSame('No aggressive breeds', $fresh->info('pet_restrictions'));
    }

    /** Editing an existing record must not blank its legacy fee values either. */
    public function test_editing_a_legacy_record_does_not_erase_its_legacy_fee_values(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeAuction($owner, [
            'pet_deposit_amount'   => '100',
            'pet_fee'              => '200',
            'pet_deposit_fee_rent' => 'legacy free text',
        ]);

        $component = Livewire::actingAs($owner)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('pet_fee_type', 'Monthly Pet Fee')
            ->set('pet_fee_amount', '60');

        $this->saveMetadata($component->instance(), $auction);

        $fresh = $auction->fresh();
        $this->assertSame('Monthly Pet Fee', $fresh->info('pet_fee_type'));
        $this->assertSame('100', (string) $fresh->info('pet_deposit_amount'));
        $this->assertSame('200', (string) $fresh->info('pet_fee'));
        $this->assertSame('legacy free text', $fresh->info('pet_deposit_fee_rent'));
    }

    /** A legacy-only record opens the EDIT form with a meaningful derived fee, not blank. */
    public function test_edit_hydrates_a_legacy_record_into_the_canonical_controls(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeAuction($owner, ['pet_deposit_amount' => '300']);

        $component = Livewire::actingAs($owner)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id]);

        $component->assertSet('pet_fee_type', 'One Time Fee Refundable')
            ->assertSet('pet_fee_amount', '300');
    }

    /** A legacy record with several amounts hydrates as Other, preserving all of them. */
    public function test_edit_hydrates_a_multi_amount_legacy_record_as_other(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeAuction($owner, [
            'pet_deposit_amount' => '100',
            'pet_fee'            => '200',
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->instance();

        $this->assertSame('Other', $instance->pet_fee_type);
        $this->assertSame('$100 refundable deposit and $200 non-refundable fee', $instance->pet_fee_other);
    }

    /** The four retired inputs are gone from the shared Lease Terms partial. */
    public function test_the_four_legacy_fee_inputs_are_removed_from_the_ui(): void
    {
        $partial = file_get_contents(base_path(
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php'
        ));

        foreach (['pet_deposit_amount', 'pet_monthly_fee', 'pet_rent', 'pet_fee'] as $legacy) {
            $this->assertStringNotContainsString(
                'wire:model="' . $legacy . '"',
                $partial,
                "the retired $legacy input must no longer be rendered"
            );
        }

        $this->assertStringContainsString('wire:model="pet_fee_type"', $partial);
        $this->assertStringContainsString('wire:model="pet_fee_amount"', $partial);
        $this->assertStringContainsString('wire:model="pet_fee_other"', $partial);
    }

    /** The "Other" placeholder shows a FEE example — never a breed restriction. */
    public function test_the_other_placeholder_is_a_fee_example_not_a_breed_restriction(): void
    {
        $partial = file_get_contents(base_path(
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php'
        ));

        $this->assertStringContainsString(
            'placeholder="e.g., $100 refundable deposit and $200 non-refundable fee"',
            $partial
        );
        $this->assertStringNotContainsString('breed', strtolower(
            substr($partial, strpos($partial, 'pet_fee_other'))
        ), 'breed restrictions must never be used as a pet-FEE example');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Canonical adapter + DNA scoring compatibility
    // ─────────────────────────────────────────────────────────────────────────

    private function canonical(LandlordAgentAuction $auction)
    {
        return (new ByoListingAdapter())->fromModel($auction->fresh(), 'landlord_agent', $auction->id);
    }

    public function test_adapter_maps_canonical_refundable_to_the_legacy_deposit_key(): void
    {
        $auction = $this->makeAuction($this->makeUser(), [
            'pet_fee_type'   => 'One Time Fee Refundable',
            'pet_fee_amount' => '300',
        ]);

        $listing = $this->canonical($auction);

        $this->assertSame(300.0, $listing->get('pet.policy.deposit_amount'));
        $this->assertTrue($listing->get('pet.policy.has_fee'));
        $this->assertFalse($listing->present('pet.policy.monthly_fee'));
    }

    public function test_adapter_maps_canonical_non_refundable_to_the_legacy_fee_key(): void
    {
        $auction = $this->makeAuction($this->makeUser(), [
            'pet_fee_type'   => 'Non Refundable',
            'pet_fee_amount' => '150',
        ]);

        $this->assertSame(150.0, $this->canonical($auction)->get('pet.policy.fee'));
    }

    /** Monthly maps to monthly_fee ONLY — never duplicated into pet.policy.rent. */
    public function test_adapter_maps_monthly_without_duplicating_into_rent(): void
    {
        $auction = $this->makeAuction($this->makeUser(), [
            'pet_fee_type'   => 'Monthly Pet Fee',
            'pet_fee_amount' => '50',
        ]);

        $listing = $this->canonical($auction);

        $this->assertSame(50.0, $listing->get('pet.policy.monthly_fee'));
        $this->assertFalse($listing->present('pet.policy.rent'), 'monthly must not be double-counted as rent');
    }

    public function test_adapter_no_pet_fee_populates_no_amounts_and_has_fee_false(): void
    {
        $auction = $this->makeAuction($this->makeUser(), ['pet_fee_type' => 'No Pet Fee']);

        $listing = $this->canonical($auction);

        foreach (['deposit_amount', 'monthly_fee', 'rent', 'fee'] as $k) {
            $this->assertFalse($listing->present("pet.policy.$k"));
        }
        $this->assertFalse($listing->get('pet.policy.has_fee'));
    }

    /** "Other" is never filed under a legacy bucket it may not belong to. */
    public function test_adapter_other_preserves_amount_and_text_without_inventing_a_cadence(): void
    {
        $auction = $this->makeAuction($this->makeUser(), [
            'pet_fee_type'   => 'Other',
            'pet_fee_amount' => '300',
            'pet_fee_other'  => '$100 refundable deposit and $200 non-refundable fee',
        ]);

        $listing = $this->canonical($auction);

        $this->assertSame(300.0, $listing->get('pet.policy.fee_other_amount'));
        $this->assertSame('$100 refundable deposit and $200 non-refundable fee', $listing->get('pet.policy.fee_other_text'));
        $this->assertTrue($listing->get('pet.policy.has_fee'));

        foreach (['deposit_amount', 'monthly_fee', 'rent', 'fee'] as $k) {
            $this->assertFalse(
                $listing->present("pet.policy.$k"),
                "Other must not be classified into pet.policy.$k"
            );
        }
    }

    /** Canonical outranks stale legacy rows in the adapter too. */
    public function test_adapter_canonical_overrides_stale_legacy_rows(): void
    {
        $auction = $this->makeAuction($this->makeUser(), [
            'pet_deposit_amount' => '999',
            'pet_fee_type'       => 'Monthly Pet Fee',
            'pet_fee_amount'     => '50',
        ]);

        $listing = $this->canonical($auction);

        $this->assertSame(50.0, $listing->get('pet.policy.monthly_fee'));
        $this->assertFalse(
            $listing->present('pet.policy.deposit_amount'),
            'the stale legacy deposit must not outrank the canonical answer'
        );
    }

    /** A legacy record's adapter output is unchanged by this work. */
    public function test_adapter_legacy_record_is_untouched(): void
    {
        $auction = $this->makeAuction($this->makeUser(), [
            'pet_deposit_amount' => '300',
            'pet_monthly_fee'    => '50',
        ]);

        $listing = $this->canonical($auction);

        $this->assertSame(300.0, $listing->get('pet.policy.deposit_amount'));
        $this->assertSame(50.0, $listing->get('pet.policy.monthly_fee'));
    }

    // ── Pet Friendliness scoring equivalence ─────────────────────────────────

    private function score(LandlordAgentAuction $auction): array
    {
        return (new PetFriendlinessScoreService())->scoreProperty($this->canonical($auction));
    }

    /** Legacy deposit and its canonical equivalent must score identically. */
    public function test_scoring_equivalence_refundable_deposit(): void
    {
        $legacy = $this->makeAuction($this->makeUser(), ['pets' => 'Yes', 'pet_deposit_amount' => '300']);
        $canon  = $this->makeAuction($this->makeUser(), [
            'pets' => 'Yes', 'pet_fee_type' => 'One Time Fee Refundable', 'pet_fee_amount' => '300',
        ]);

        $a = $this->score($legacy);
        $b = $this->score($canon);

        $this->assertSame($a['value'], $b['value']);
        $this->assertSame($a['data_completeness'], $b['data_completeness']);
        $this->assertSame($a['inputs']['has_pet_fees'], $b['inputs']['has_pet_fees']);
        $this->assertTrue($b['inputs']['has_pet_fees']);
    }

    /** Legacy monthly fee and its canonical equivalent must score identically (recurring). */
    public function test_scoring_equivalence_monthly_fee(): void
    {
        $legacy = $this->makeAuction($this->makeUser(), ['pets' => 'Yes', 'pet_monthly_fee' => '50']);
        $canon  = $this->makeAuction($this->makeUser(), [
            'pets' => 'Yes', 'pet_fee_type' => 'Monthly Pet Fee', 'pet_fee_amount' => '50',
        ]);

        $a = $this->score($legacy);
        $b = $this->score($canon);

        $this->assertSame($a['value'], $b['value'], 'the recurring-fee penalty must survive the migration');
        $this->assertStringContainsString('recurring pet fee', $b['explanation']);
    }

    /** Legacy non-refundable fee and its canonical equivalent must score identically. */
    public function test_scoring_equivalence_non_refundable_fee(): void
    {
        $legacy = $this->makeAuction($this->makeUser(), ['pets' => 'Yes', 'pet_fee' => '150']);
        $canon  = $this->makeAuction($this->makeUser(), [
            'pets' => 'Yes', 'pet_fee_type' => 'Non Refundable', 'pet_fee_amount' => '150',
        ]);

        $this->assertSame($this->score($legacy)['value'], $this->score($canon)['value']);
    }

    /**
     * "Other" is DETECTED as a fee without being misclassified — and, critically, is not
     * reported as "no pet fees", which is what the pre-existing branch chain would have
     * claimed for a listing that plainly charges for pets.
     */
    public function test_other_fee_is_detected_without_inventing_a_classification(): void
    {
        $auction = $this->makeAuction($this->makeUser(), [
            'pets'           => 'Yes',
            'pet_fee_type'   => 'Other',
            'pet_fee_amount' => '300',
            'pet_fee_other'  => '$100 refundable deposit and $200 non-refundable fee',
        ]);

        $result = $this->score($auction);

        $this->assertTrue($result['inputs']['has_pet_fees']);
        $this->assertStringContainsString('pet fee applies', $result['explanation']);
        $this->assertStringNotContainsString('no pet fees', $result['explanation']);
        $this->assertStringNotContainsString('recurring pet fee', $result['explanation']);
        $this->assertStringNotContainsString('one-time pet fee', $result['explanation']);
    }

    /** A "No Pet Fee" listing must not be reported as carrying a fee. */
    public function test_no_pet_fee_listing_reports_no_pet_fees(): void
    {
        $auction = $this->makeAuction($this->makeUser(), ['pets' => 'Yes', 'pet_fee_type' => 'No Pet Fee']);

        $this->assertFalse($this->score($auction)['inputs']['has_pet_fees']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Downstream context
    // ─────────────────────────────────────────────────────────────────────────

    /** AgentAi context exposes the normalized summary, for legacy and canonical alike. */
    public function test_agent_ai_loader_exposes_the_normalized_pet_fee_summary(): void
    {
        $legacy = $this->makeAuction($this->makeUser(), ['pet_deposit_amount' => '100', 'pet_fee' => '200']);
        $canon  = $this->makeAuction($this->makeUser(), [
            'pet_fee_type' => 'Monthly Pet Fee', 'pet_fee_amount' => '50',
        ]);

        $summary = new ReflectionMethod(\App\Services\AgentAi\Loaders\LandlordListingLoader::class, 'normalizedPetFee');
        $summary->setAccessible(true);

        $legacyGet = fn ($key) => ($v = $legacy->fresh()->info($key)) === false ? null : $v;
        $canonGet  = fn ($key) => ($v = $canon->fresh()->info($key)) === false ? null : $v;

        $this->assertSame(
            '$100 refundable deposit and $200 non-refundable fee',
            $summary->invoke(null, $legacyGet)
        );
        $this->assertSame('$50 monthly pet fee', $summary->invoke(null, $canonGet));
    }

    /** AskAi maps the canonical keys AND keeps every legacy key mapped. */
    public function test_ask_ai_context_maps_canonical_and_all_legacy_fee_keys(): void
    {
        $source = file_get_contents(base_path('app/Services/AskAi/AskAiContextBuilderService.php'));

        foreach (['pet_fee_type', 'pet_fee_amount', 'pet_fee_other'] as $canonical) {
            $this->assertStringContainsString("'$canonical'", $source);
        }
        foreach (PetFeeNormalizer::legacyKeys() as $legacy) {
            $this->assertStringContainsString("'$legacy'", $source, "legacy $legacy must stay in AI context");
        }
    }
}
