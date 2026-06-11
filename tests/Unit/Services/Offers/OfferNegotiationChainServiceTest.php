<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Services\Offers\OfferNegotiationChainService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferNegotiationChainServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OfferNegotiationChainService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OfferNegotiationChainService();
    }

    // ── Case 1: getRootOffer returns self when parent_offer_id is null ─────

    public function test_get_root_offer_returns_self_when_no_parent(): void
    {
        $root = Offer::factory()->create(['parent_offer_id' => null]);

        $result = $this->service->getRootOffer($root);

        $this->assertSame($root->id, $result->id);
    }

    // ── Case 2: getRootOffer returns root from a child counteroffer ────────

    public function test_get_root_offer_returns_root_from_child(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null]);
        $child = Offer::factory()->create(['parent_offer_id' => $root->id]);

        $result = $this->service->getRootOffer($child);

        $this->assertSame($root->id, $result->id);
    }

    // ── Case 3: getRootOffer returns root from a grandchild counteroffer ───

    public function test_get_root_offer_returns_root_from_grandchild(): void
    {
        $root       = Offer::factory()->create(['parent_offer_id' => null]);
        $child      = Offer::factory()->create(['parent_offer_id' => $root->id]);
        $grandchild = Offer::factory()->create(['parent_offer_id' => $child->id]);

        $result = $this->service->getRootOffer($grandchild);

        $this->assertSame($root->id, $result->id);
    }

    // ── Case 4: getChainFromRoot returns root + children oldest→newest ─────

    public function test_get_chain_from_root_returns_ordered_chain(): void
    {
        $root   = Offer::factory()->create(['parent_offer_id' => null, 'created_at' => now()->subSeconds(10)]);
        $child  = Offer::factory()->create(['parent_offer_id' => $root->id, 'created_at' => now()->subSeconds(5)]);
        $child2 = Offer::factory()->create(['parent_offer_id' => $root->id, 'created_at' => now()]);

        $chain = $this->service->getChainFromRoot($root);

        $this->assertCount(3, $chain);
        $this->assertSame($root->id, $chain->first()->id);
        $this->assertSame($child2->id, $chain->last()->id);
    }

    // ── Case 5: getChainForOffer returns full chain from the latest child ──

    public function test_get_chain_for_offer_returns_full_chain_from_latest_child(): void
    {
        $root       = Offer::factory()->create(['parent_offer_id' => null, 'created_at' => now()->subSeconds(20)]);
        $child      = Offer::factory()->create(['parent_offer_id' => $root->id, 'created_at' => now()->subSeconds(10)]);
        $grandchild = Offer::factory()->create(['parent_offer_id' => $child->id, 'created_at' => now()]);

        $chain = $this->service->getChainForOffer($grandchild);

        $ids = $chain->pluck('id')->all();
        $this->assertCount(3, $chain);
        $this->assertContains($root->id, $ids);
        $this->assertContains($child->id, $ids);
        $this->assertContains($grandchild->id, $ids);
        $this->assertSame($root->id, $chain->first()->id);
        $this->assertSame($grandchild->id, $chain->last()->id);
    }

    // ── Case 6: Unrelated offers are excluded from the chain ──────────────

    public function test_unrelated_offers_are_excluded_from_chain(): void
    {
        $root      = Offer::factory()->create(['parent_offer_id' => null]);
        $child     = Offer::factory()->create(['parent_offer_id' => $root->id]);
        $unrelated = Offer::factory()->create(['parent_offer_id' => null]);

        $chain = $this->service->getChainFromRoot($root);

        $ids = $chain->pluck('id')->all();
        $this->assertContains($root->id, $ids);
        $this->assertContains($child->id, $ids);
        $this->assertNotContains($unrelated->id, $ids);
    }

    // ── Case 7: Chain with no children returns only the root offer ─────────

    public function test_chain_with_no_children_returns_only_root(): void
    {
        $root = Offer::factory()->create(['parent_offer_id' => null]);

        $chain = $this->service->getChainFromRoot($root);

        $this->assertCount(1, $chain);
        $this->assertSame($root->id, $chain->first()->id);
    }

    // ── Case 8: Static scan — service contains no write operations ─────────

    public function test_service_file_contains_no_write_operations(): void
    {
        $source = file_get_contents(app_path('Services/Offers/OfferNegotiationChainService.php'));

        $this->assertStringNotContainsString('::create',  $source);
        $this->assertStringNotContainsString('->save(',   $source);
        $this->assertStringNotContainsString('->update(', $source);
        $this->assertStringNotContainsString('->delete(', $source);
        $this->assertStringNotContainsString('->insert(', $source);
    }

    // ── getActiveLeaf ──────────────────────────────────────────────────────

    // Case 9: single root with no children is its own active leaf.
    public function test_get_active_leaf_returns_root_when_no_children(): void
    {
        $root = Offer::factory()->create(['parent_offer_id' => null, 'status' => 'submitted']);

        $leaf = $this->service->getActiveLeaf($root);

        $this->assertSame($root->id, $leaf->id);
    }

    // Case 10: root with one non-final child — leaf is the child.
    public function test_get_active_leaf_returns_non_final_child(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null, 'status' => 'countered']);
        $child = Offer::factory()->create(['parent_offer_id' => $root->id, 'status' => 'countered']);

        $leaf = $this->service->getActiveLeaf($root);

        $this->assertSame($child->id, $leaf->id);
    }

    // Case 11: 3-node chain A → B → C; active leaf is C regardless of which node is passed.
    public function test_get_active_leaf_traverses_three_node_chain(): void
    {
        $a = Offer::factory()->create(['parent_offer_id' => null,  'status' => 'countered', 'created_at' => now()->subSeconds(20)]);
        $b = Offer::factory()->create(['parent_offer_id' => $a->id, 'status' => 'countered', 'created_at' => now()->subSeconds(10)]);
        $c = Offer::factory()->create(['parent_offer_id' => $b->id, 'status' => 'countered', 'created_at' => now()]);

        // Starting from root A
        $this->assertSame($c->id, $this->service->getActiveLeaf($a)->id, 'Leaf from root A must be C.');
        // Starting from mid-chain B
        $this->assertSame($c->id, $this->service->getActiveLeaf($b)->id, 'Leaf from B must also be C.');
        // Starting from leaf C itself — should return C unchanged
        $this->assertSame($c->id, $this->service->getActiveLeaf($c)->id, 'Leaf from C must be C itself.');
    }

    // Case 12: final child is skipped; root is the active leaf when the only child is final.
    public function test_get_active_leaf_skips_final_children(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null,   'status' => 'countered']);
        // Final child should NOT be descended into.
        Offer::factory()->create(['parent_offer_id' => $root->id, 'status' => 'accepted']);

        $leaf = $this->service->getActiveLeaf($root);

        $this->assertSame($root->id, $leaf->id, 'Root must be the active leaf when its only child is final.');
    }

    // Case 13: when multiple non-final children exist (data anomaly), most recent is chosen.
    public function test_get_active_leaf_picks_most_recent_when_multiple_non_final_children(): void
    {
        $root   = Offer::factory()->create(['parent_offer_id' => null, 'status' => 'countered', 'created_at' => now()->subSeconds(30)]);
        $older  = Offer::factory()->create(['parent_offer_id' => $root->id, 'status' => 'countered', 'created_at' => now()->subSeconds(10)]);
        $newer  = Offer::factory()->create(['parent_offer_id' => $root->id, 'status' => 'countered', 'created_at' => now()]);

        $leaf = $this->service->getActiveLeaf($root);

        $this->assertSame($newer->id, $leaf->id, 'Most recent non-final child must be chosen as the active leaf.');
    }

    // Case 14: getActiveLeaf called from mid-chain correctly finds the deepest leaf.
    public function test_get_active_leaf_called_from_middle_of_chain_finds_deepest_leaf(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null,   'status' => 'countered', 'created_at' => now()->subSeconds(30)]);
        $mid   = Offer::factory()->create(['parent_offer_id' => $root->id, 'status' => 'countered', 'created_at' => now()->subSeconds(15)]);
        $deep  = Offer::factory()->create(['parent_offer_id' => $mid->id,  'status' => 'countered', 'created_at' => now()->subSeconds(5)]);
        $leaf  = Offer::factory()->create(['parent_offer_id' => $deep->id, 'status' => 'countered', 'created_at' => now()]);

        $result = $this->service->getActiveLeaf($mid);

        $this->assertSame($leaf->id, $result->id, 'Starting from mid-chain must still reach the deepest leaf.');
    }
}
