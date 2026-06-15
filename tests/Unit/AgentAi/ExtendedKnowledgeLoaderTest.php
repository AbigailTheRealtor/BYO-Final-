<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\AiFaqAnswer;
use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\AgentAi\Loaders\ExtendedKnowledgeLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * ExtendedKnowledgeLoaderTest
 *
 * Verifies:
 *   (a) Only loads knowledge for the requested listing (not other listings).
 *   (b) Never includes private/restricted facts (public_allowed=false).
 *   (c) Never includes bid, offer, or competing-agent data.
 *   (d) Token estimate does not exceed the seller scope budget (3,000 tokens).
 *   (e) Returns null when no knowledge sources exist.
 */
class ExtendedKnowledgeLoaderTest extends TestCase
{
    use DatabaseTransactions;

    private ExtendedKnowledgeLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new ExtendedKnowledgeLoader();
    }

    private function makeScopeContext(string $listingType, int $listingId): array
    {
        $scope = match ($listingType) {
            'seller'   => AgentAiContextScope::PublicListingSeller,
            'landlord' => AgentAiContextScope::PublicListingLandlord,
            'buyer'    => AgentAiContextScope::BuyerCriteria,
            'tenant'   => AgentAiContextScope::TenantCriteria,
            default    => AgentAiContextScope::PublicListingSeller,
        };

        return [
            'scope'        => $scope,
            'agent_id'     => 1,
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
        ];
    }

    public function test_returns_null_when_listing_type_is_missing(): void
    {
        $result = ($this->loader)(['scope' => AgentAiContextScope::PublicListingSeller, 'agent_id' => 1, 'listing_type' => null, 'listing_id' => 10]);
        $this->assertNull($result);
    }

    public function test_returns_null_when_listing_id_is_zero(): void
    {
        $result = ($this->loader)(['scope' => AgentAiContextScope::PublicListingSeller, 'agent_id' => 1, 'listing_type' => 'seller', 'listing_id' => 0]);
        $this->assertNull($result);
    }

    public function test_returns_null_when_no_knowledge_sources_exist(): void
    {
        $result = ($this->loader)($this->makeScopeContext('seller', 999999));
        $this->assertNull($result);
    }

    public function test_returns_fragment_with_faq_answers(): void
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        AiFaqAnswer::create([
            'listing_type'  => 'seller',
            'listing_id'    => $listing->id,
            'question_key'  => 'roof_age_and_condition',
            'question_group'=> 'property',
            'answer_text'   => 'Roof replaced in 2019, excellent condition.',
        ]);

        $result = ($this->loader)($this->makeScopeContext('seller', $listing->id));

        $this->assertNotNull($result);
        $this->assertEquals(ExtendedKnowledgeLoader::SOURCE_KEY, $result['source_key']);
        $this->assertEquals(ExtendedKnowledgeLoader::PRIORITY, $result['priority']);
        $this->assertArrayHasKey('faq_answers', $result['content']);
        $this->assertArrayHasKey('roof_age_and_condition', $result['content']['faq_answers']);
    }

    public function test_only_includes_public_allowed_snapshot_facts(): void
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $snapshot = AskAiKnowledgeSnapshot::create([
            'listing_type'      => 'seller',
            'listing_id'        => $listing->id,
            'status'            => 'ready',
            'version'           => 1,
            'snapshot_uuid'     => \Illuminate\Support\Str::uuid(),
            'source_model'      => 'App\\Models\\SellerAgentAuction',
            'source_updated_at' => now(),
            'built_at'          => now(),
        ]);

        AskAiFact::create([
            'snapshot_id'    => $snapshot->id,
            'listing_type'   => 'seller',
            'listing_id'     => $listing->id,
            'canonical_key'  => 'listing.bedrooms',
            'value'          => '4',
            'visibility'     => 'public_allowed',
            'public_allowed' => true,
            'restricted'     => false,
            'classification' => 'public',
            'sort_order'     => 1,
        ]);

        AskAiFact::create([
            'snapshot_id'    => $snapshot->id,
            'listing_type'   => 'seller',
            'listing_id'     => $listing->id,
            'canonical_key'  => 'listing.user_id',
            'value'          => (string) $user->id,
            'visibility'     => 'private',
            'public_allowed' => false,
            'restricted'     => true,
            'classification' => 'private',
            'sort_order'     => 2,
        ]);

        $result = ($this->loader)($this->makeScopeContext('seller', $listing->id));

        $this->assertNotNull($result);
        $facts = $result['content']['snapshot_facts'] ?? [];

        $this->assertArrayHasKey('bedrooms', $facts, 'Public fact should be included.');
        $this->assertArrayNotHasKey('user_id', $facts, 'Private fact must never be included.');
    }

    public function test_never_includes_bid_or_offer_data(): void
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        AiFaqAnswer::create([
            'listing_type'  => 'seller',
            'listing_id'    => $listing->id,
            'question_key'  => 'unique_selling_points',
            'answer_text'   => 'Corner lot, updated kitchen.',
        ]);

        $result = ($this->loader)($this->makeScopeContext('seller', $listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $bidKeys = ['bids', 'bid_amount', 'accepted_bid', 'counteroffer', 'competing_bids', 'commission_rate', 'accepted_bid_summary'];
        foreach ($bidKeys as $key) {
            $this->assertArrayNotHasKey($key, $content,
                "Bid/offer field '{$key}' must never appear in extended knowledge context.");
        }

        $faqAnswers = $content['faq_answers'] ?? [];
        foreach ($bidKeys as $key) {
            $this->assertArrayNotHasKey($key, $faqAnswers,
                "Bid/offer field '{$key}' must never appear in FAQ answers.");
        }
    }

    public function test_only_returns_knowledge_for_requested_listing(): void
    {
        $user     = User::factory()->create();
        $listingA = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);
        $listingB = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        AiFaqAnswer::create(['listing_type' => 'seller', 'listing_id' => $listingA->id, 'question_key' => 'hvac_system_age', 'answer_text' => 'HVAC replaced 2021']);
        AiFaqAnswer::create(['listing_type' => 'seller', 'listing_id' => $listingB->id, 'question_key' => 'hvac_system_age', 'answer_text' => 'HVAC is original from 1990']);

        $resultA = ($this->loader)($this->makeScopeContext('seller', $listingA->id));
        $resultB = ($this->loader)($this->makeScopeContext('seller', $listingB->id));

        $this->assertNotNull($resultA);
        $this->assertNotNull($resultB);

        $faqA = $resultA['content']['faq_answers'] ?? [];
        $faqB = $resultB['content']['faq_answers'] ?? [];

        $this->assertArrayHasKey('hvac_system_age', $faqA);
        $this->assertArrayHasKey('hvac_system_age', $faqB);

        $this->assertStringContainsString('2021', $faqA['hvac_system_age']);
        $this->assertStringContainsString('1990', $faqB['hvac_system_age']);
    }

    public function test_token_estimate_within_seller_scope_budget(): void
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        for ($i = 0; $i < 10; $i++) {
            AiFaqAnswer::create([
                'listing_type'  => 'seller',
                'listing_id'    => $listing->id,
                'question_key'  => "faq_key_{$i}",
                'answer_text'   => str_repeat("FAQ answer text for question {$i}. ", 5),
            ]);
        }

        $result = ($this->loader)($this->makeScopeContext('seller', $listing->id));

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(3000, $result['token_estimate'],
            "ExtendedKnowledgeLoader token_estimate must not exceed 3,000 tokens for the seller scope.");
    }
}
