<?php

namespace Tests\Feature\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiFaqConfigService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Knowledge Base coverage on the free-text Ask AI path, exhaustively and with no model.
 *
 * For EVERY Knowledge Base key configured for every role and every property type it is
 * gated to, an owned listing stores a distinct answer and the runner is asked the key's own
 * label. Measured before AskAiKnowledgeBaseQuestionMatcher, the owner got their own answer
 * for 16 of 148 Seller/Landlord/Tenant keys; one key returned a DIFFERENT key's answer.
 *
 * Every run happens with the model client bound to a mock that must never be called and
 * the normaliser's config flag forced ON, so a pass is also a zero-LLM proof for this path.
 */
class AskAiKnowledgeBaseOwnerPathTest extends TestCase
{
    use DatabaseTransactions;

    private const ROLES = [
        'seller'   => [SellerAgentAuction::class, ['Residential Property', 'Income Property', 'Commercial Property', 'Business Opportunity', 'Vacant Land']],
        'landlord' => [LandlordAgentAuction::class, ['Residential Property', 'Commercial Property']],
        'tenant'   => [TenantAgentAuction::class, ['Residential Property', 'Commercial Property']],
        'buyer'    => [BuyerAgentAuction::class, ['Residential Property', 'Income Property', 'Commercial Property', 'Business Opportunity', 'Vacant Land']],
    ];

    /**
     * Labels the compliance classifier refuses as PROHIBITED. The Knowledge Base step never
     * overrides a prohibited classification, so these are refused for everyone — including
     * the owner. Listed so the exception is visible and cannot grow silently.
     */
    private const PROHIBITED_LABEL_KEYS = [
        'buyer.buyer_school_district',
    ];

    private User $owner;
    private AskAiRunnerV2Service $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->getMockBuilder(OpenAiClientService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
        config(['ask_ai.enable_openai_intent_normalization' => true]);

        $this->owner  = User::factory()->create();
        $this->runner = app(AskAiRunnerV2Service::class);
    }

    public function test_the_owner_gets_their_own_answer_for_every_knowledge_base_question(): void
    {
        $failures = [];
        $asked    = 0;

        foreach ($this->cases() as [$role, $key, $label, $listingId]) {
            $result = $this->ask($role, $listingId, $label, AskAiViewerAuthorizationService::SCOPE_OWNER);
            $answer = $this->answer($result);

            if (in_array("{$role}.{$key}", self::PROHIBITED_LABEL_KEYS, true)) {
                if (str_contains($answer, 'SENTINEL-')) {
                    $failures[] = "{$role}.{$key}: prohibited label was answered";
                }
                continue;
            }

            $asked++;
            if (!str_contains($answer, "SENTINEL-{$role}-{$key}")) {
                $failures[] = "{$role}.{$key}: got '" . mb_substr($answer, 0, 80) . "'";
            }
        }

        $this->assertGreaterThan(190, $asked, 'The case generator reached too few keys — the proof would be hollow.');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_no_other_viewer_receives_knowledge_base_text_or_a_false_not_provided(): void
    {
        $failures = [];

        foreach ($this->cases() as [$role, $key, $label, $listingId]) {
            foreach (['public', 'authorized'] as $scope) {
                $result = $this->ask($role, $listingId, $label, $scope);
                if (str_contains((string) json_encode($result), 'SENTINEL-')) {
                    $failures[] = "{$scope} {$role}.{$key}: owner-only answer leaked";
                }
                if (str_contains($this->answer($result), 'has not been provided')) {
                    $failures[] = "{$scope} {$role}.{$key}: told the answer was not provided";
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_an_unanswered_knowledge_base_question_says_so_to_the_owner(): void
    {
        $listing = SellerAgentAuction::forceCreate(['user_id' => $this->owner->id, 'title' => 'KB', 'is_draft' => false]);
        $listing->saveMeta('property_type', 'Commercial Property');
        $listing->saveMeta('listing_ai_faq', json_encode(['commercial_restroom_count' => 'Two restrooms.']));

        $answered = $this->answer($this->ask('seller', $listing->id, 'How many restrooms are there?', AskAiViewerAuthorizationService::SCOPE_OWNER));
        $this->assertSame('Two restrooms.', $answered);

        $label  = $this->labelFor('seller', 'Commercial Property', 'commercial_ceiling_height');
        $result = $this->ask('seller', $listing->id, $label, AskAiViewerAuthorizationService::SCOPE_OWNER);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertStringContainsString('has not been provided', $this->answer($result));
        $this->assertStringNotContainsString('Two restrooms', $this->answer($result));

        // Anyone else is told the category is owner-only — true whether or not it was answered.
        $public = $this->answer($this->ask('seller', $listing->id, 'How many restrooms are there?', 'public'));
        $this->assertStringNotContainsString('Two restrooms', $public);
        $this->assertStringNotContainsString('has not been provided', $public);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** @return \Generator<array{0:string,1:string,2:string,3:int}> role, key, label, listing id */
    private function cases(): \Generator
    {
        $seen = [];
        foreach (self::ROLES as $role => [$model, $types]) {
            foreach ($types as $type) {
                $keys    = AskAiFaqConfigService::gatedKeys($role, $type);
                $listing = $model::forceCreate(['user_id' => $this->owner->id, 'title' => 'KB coverage', 'is_draft' => false]);
                $listing->saveMeta('property_type', $type);
                $listing->saveMeta('listing_ai_faq', json_encode(array_combine(
                    $keys,
                    array_map(static fn ($k) => "SENTINEL-{$role}-{$k}", $keys)
                )));

                foreach ($keys as $key) {
                    if (isset($seen["{$role}.{$key}"])) {
                        continue;
                    }
                    $seen["{$role}.{$key}"] = true;
                    yield [$role, $key, $this->labelFor($role, $type, $key), $listing->id];
                }
            }
        }
    }

    private function labelFor(string $role, string $type, string $key): string
    {
        foreach (AskAiFaqConfigService::gatedQuestions($role, $type) as $entries) {
            if (isset($entries[$key]['label'])) {
                return $entries[$key]['label'];
            }
        }
        $this->fail("No label for {$role}.{$key} on {$type}");
    }

    private function ask(string $role, int $listingId, string $question, string $scope): array
    {
        $options = ['viewer_scope' => $scope];
        if ($scope === AskAiViewerAuthorizationService::SCOPE_OWNER) {
            $options['requester_user_id'] = $this->owner->id;
        }

        return $this->runner->run($role, $listingId, $question, $options);
    }

    private function answer(array $result): string
    {
        return (string) ($result['final_response']['answer'] ?? $result['answer'] ?? '');
    }
}
