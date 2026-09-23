<?php

namespace Tests\Feature\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The owner's own listing facts on the free-text Ask AI path, exhaustively and with no model.
 *
 * For every canonical listing field of every role, a value is stored, the owner's real context
 * is built to confirm it carries the value, and the owner asks the runner's OWN keyword for
 * that field. Measured before this work, the owner was told "This information was not provided
 * in the listing." about a value they HAD provided for dozens of fields per role — by the
 * synthesis gate, by the contract filter, by routing to a key their role cannot hold, and by
 * first-match keyword order sending the question to a broader field.
 *
 * Two properties are asserted for every such field, rather than a per-field answer, so the test
 * holds as keyword maps evolve: the owner is never told a provided value was not provided, and
 * never receives another field's value.
 */
class AskAiOwnerListingFactsTest extends TestCase
{
    use DatabaseTransactions;

    private const MODELS = [
        'seller'   => SellerAgentAuction::class,
        'landlord' => LandlordAgentAuction::class,
        'buyer'    => BuyerAgentAuction::class,
        'tenant'   => TenantAgentAuction::class,
    ];

    /**
     * Genuine concept overlaps: the field's keyword also names a Knowledge Base question of the
     * same concept, which is detected first. The owner is told that Knowledge Base question was
     * not answered — true of the question routed to. Listed so the set cannot grow silently.
     */
    private const KNOWLEDGE_BASE_OVERLAPS = [
        'seller.foundation',                  // faq_answers.foundation_type_and_issues
        'landlord.maintenance_response_time', // faq_answers.maintenance_request_response_time
    ];

    public function test_the_owner_is_never_told_a_provided_value_was_not_provided_or_given_another_fields_value(): void
    {
        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
        config(['ask_ai.enable_openai_intent_normalization' => true]);

        $owner    = User::factory()->create();
        $runner   = app(AskAiRunnerV2Service::class);
        $keywords = (new \ReflectionClass(AskAiRunnerV2Service::class))->getConstant('LISTING_KEY_KEYWORD_MAP');

        $failures = [];
        $checked  = 0;

        foreach (self::MODELS as $role => $model) {
            foreach (AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role] as $field => $sources) {
                $phrase  = $keywords["listing.{$field}"][0] ?? null;
                $metaKey = $this->firstMetaKey($sources);
                if ($phrase === null || $metaKey === null) {
                    continue;
                }

                [$listingId, $stored] = $this->listingCarrying($model, $role, $owner, $field, $metaKey);
                if ($listingId === null) {
                    continue; // the context builder does not carry this value — nothing to answer
                }
                $checked++;

                $result = $runner->run($role, $listingId, $phrase, [
                    'viewer_scope'      => AskAiViewerAuthorizationService::SCOPE_OWNER,
                    'requester_user_id' => $owner->id,
                ]);
                $answer = (string) ($result['final_response']['answer'] ?? '');

                if (str_contains($answer, 'Zqx') && !str_contains($answer, $stored)) {
                    $failures[] = "{$role}.{$field}: answered with ANOTHER field's value — {$answer}";
                }
                if (str_contains($answer, 'not provided') && !in_array("{$role}.{$field}", self::KNOWLEDGE_BASE_OVERLAPS, true)) {
                    $failures[] = "{$role}.{$field}: owner told a provided value was not provided — {$answer}";
                }
            }
        }

        $this->assertGreaterThan(200, $checked, 'Too few fields reached the runner — the proof would be hollow.');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_a_restricted_field_is_refused_without_claiming_it_was_not_provided(): void
    {
        $owner   = User::factory()->create();
        $listing = LandlordAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => 'R', 'is_draft' => false]);
        $listing->saveMeta('property_type', 'Residential Property');
        $listing->saveMeta('security_deposit_amount', '2500');

        $result = app(AskAiRunnerV2Service::class)->run('landlord', $listing->id, 'what is the security deposit', [
            'viewer_scope' => AskAiViewerAuthorizationService::SCOPE_OWNER, 'requester_user_id' => $owner->id,
        ]);
        $answer = (string) ($result['final_response']['answer'] ?? '');

        $this->assertStringNotContainsString('2500', $answer);
        $this->assertStringNotContainsString('not provided', $answer);
        $this->assertStringContainsString('not available through Ask AI', $answer);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function firstMetaKey(string|array $sources): ?string
    {
        foreach ((array) $sources as $source) {
            if (is_string($source) && !str_starts_with($source, 'native:')) {
                return $source;
            }
        }

        return null;
    }

    /**
     * A listing whose OWNER context carries a value for this field: a distinctive string first,
     * then a number or "Yes" for fields the builder normalises. [listing id, stored value] or
     * [null, null] when the builder carries none of them.
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function listingCarrying(string $model, string $role, User $owner, string $field, string $metaKey): array
    {
        foreach (["Zqx{$field}", '98765', 'Yes'] as $value) {
            $listing = $model::forceCreate(['user_id' => $owner->id, 'title' => 'Owner facts', 'is_draft' => false]);
            $listing->saveMeta('property_type', in_array($role, ['landlord', 'tenant'], true) ? 'Residential Property' : 'Residential');
            $listing->saveMeta($metaKey, $value);

            $context = app(AskAiContextBuilderService::class)->buildForListing($role, $listing->id, ['viewer_scope' => 'owner']);
            $carried = $context['listing'][$field] ?? null;
            if ($carried !== null && $carried !== '' && $carried !== []) {
                return [$listing->id, $value];
            }
        }

        return [null, null];
    }
}
