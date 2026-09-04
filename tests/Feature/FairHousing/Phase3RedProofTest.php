<?php

namespace Tests\Feature\FairHousing;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\OfferAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use App\Services\AskAi\AskAiContextBuilderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fair Housing Phase 3 — the three audit defects, reproduced and closed.
 *
 * This file began as the RED PROOF: it was written against current main BEFORE any
 * Phase 3 code existed, and each case below was observed failing, with the exact
 * markers recorded here. It is kept rather than deleted because it is the provenance
 * record — it reproduces each defect the way the audit found it, from the defect's
 * point of view, and would fail again if the boundary were removed.
 *
 * OBSERVED RED on 517c9327e (pre-implementation):
 *
 *   1. `landlord_approval_conditions` = "Professionals only, no children. No Section 8."
 *      rendered on the anonymous public listing page.
 *   2. The same prose appeared in the Ask AI context as
 *      "landlord_approval_conditions":"Professionals only, no children."
 *   3. An unknown tenant meta key rendered in the Additional Information fallback as
 *      `Phase3 Synthetic Unknown Key … SYNTHETIC-LEAK-MARKER`.
 *   4. `custom_credit_score_requirement` persisted with `min_credit_score` set to
 *      "No requirement" — a parent that does not authorise it.
 *
 * ONE CORRECTION TO THE ORIGINAL HARNESS. Case 4 first asserted that the component
 * PROPERTY was cleared. That was the wrong contract and would have been the wrong fix:
 * Phase 2 established that the boundary belongs at the WRITE, not on the public Livewire
 * property, precisely because a property can be set by any crafted payload and clearing
 * it would fight the framework rather than the problem. The assertion now reads the
 * persisted meta row, which is what the boundary actually governs.
 */
class Phase3RedProofTest extends TestCase
{
    use DatabaseTransactions;

    private const UNSAFE = 'Professionals only, no children. No Section 8.';

    private function listing(array $meta = []): LandlordAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'agent']);

        $auction = LandlordAgentAuction::create([
            'user_id'     => $owner->id,
            'title'       => 'P3 audit closure',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        foreach (['workflow_type' => 'offer_listing'] + $meta as $key => $value) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => $key,
                'meta_value'                => $value,
            ]);
        }

        $offerAuction = OfferAuction::create(['user_id' => $owner->id]);
        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'linked_offer_auction_id',
            'meta_value'                => (string) $offerAuction->id,
        ]);

        return $auction;
    }

    /** @test Defect 1 — unsafe approval conditions on the anonymous public page. */
    public function defect_1_unsafe_approval_conditions_no_longer_reach_the_public_page(): void
    {
        $auction = $this->listing(['landlord_approval_conditions' => self::UNSAFE]);

        $this->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('Professionals only', false);
    }

    /** @test Defect 2 — the same prose in the Ask AI prompt context. */
    public function defect_2_unsafe_approval_conditions_no_longer_reach_ask_ai_context(): void
    {
        $auction = $this->listing(['landlord_approval_conditions' => self::UNSAFE]);

        $context = app(AskAiContextBuilderService::class)->buildForListing('landlord', (int) $auction->id);

        $this->assertStringNotContainsString('Professionals only', json_encode($context));
    }

    /** @test Defect 3 — unknown tenant metadata published by deny-list fallback. */
    public function defect_3_unknown_tenant_meta_no_longer_appears_in_additional_information(): void
    {
        $owner = User::factory()->create(['user_type' => 'user']);

        $t = new TenantAgentAuction();
        $t->user_id     = $owner->id;
        $t->title       = 'P3 tenant';
        $t->is_draft    = false;
        $t->is_approved = true;
        $t->save();

        foreach ([
            'workflow_type'                => 'offer_listing',
            'phase3_synthetic_unknown_key' => 'SYNTHETIC-LEAK-MARKER',
        ] as $key => $value) {
            $m = new TenantAgentAuctionMeta();
            $m->tenant_agent_auction_id = $t->id;
            $m->meta_key                = $key;
            $m->meta_value              = $value;
            $m->save();
        }

        $this->get(route('offer.listing.tenant.view', $t->id))
            ->assertStatus(200)
            ->assertDontSee('SYNTHETIC-LEAK-MARKER', false);
    }

    /** @test Defect 4 — custom text persisting without its parent condition. */
    public function defect_4_crafted_custom_text_no_longer_persists_without_its_parent(): void
    {
        $owner = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($owner);

        $component = Livewire::test(LandlordOfferListing::class)
            ->set('listing_title', 'P3 crafted closure')
            ->set('min_credit_score', 'No requirement')
            ->set('custom_credit_score_requirement', 'CRAFTED-BYPASS-MARKER')
            ->call('saveDraft');

        $stored = LandlordAgentAuctionMeta::query()
            ->where('landlord_agent_auction_id', $component->get('listingId'))
            ->where('meta_key', 'custom_credit_score_requirement')
            ->value('meta_value');

        $this->assertSame('', (string) $stored,
            'Crafted custom credit text persisted with a parent that does not authorise it.');
    }
}
