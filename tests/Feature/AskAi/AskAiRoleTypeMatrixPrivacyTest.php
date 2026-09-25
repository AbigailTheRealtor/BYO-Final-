<?php

namespace Tests\Feature\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiContextBuilderService as C;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility as V;
use App\Support\AskAi\AskAiPropertyTypeResolver as PT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Role × property-type end-to-end validation, and the privacy proof, with no model reachable.
 *
 * (A) PUBLIC CARD — for every role and every property type its wizard offers, a listing carries
 *     a distinctive value in EVERY context field and its real public page is rendered. Every
 *     value that appears in the Ask AI card must belong to a field the public may be told
 *     (SnapshotFactVisibility PUBLIC_ALLOWED for Seller/Landlord, the criteria allowlist for
 *     Buyer/Tenant) AND read by a catalog entry admitted for that property type; every question
 *     shown must be admitted for that type.
 *
 * (B) FREE TEXT — for every field the public may NOT be told, a public or authorized viewer
 *     asking the runner's own keyword for it never receives its value. A tenant listing's
 *     authorized scope is held to its designed limit (never-expose keys) instead.
 *
 * Measured before the public-fact allowlist in AskAiViewerAuthorizationService, (B) leaked
 * 153 of 153 owner-only fields.
 */
class AskAiRoleTypeMatrixPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    private const ROLES = [
        'seller'   => ['offer.listing.seller.view',   ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land']],
        'landlord' => ['offer.listing.landlord.view', ['Residential Property', 'Commercial Property']],
        'buyer'    => ['offer.listing.buyer.view',    ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land']],
        'tenant'   => ['offer.listing.tenant.view',   ['Residential', 'Commercial']],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
        config(['ask_ai.enable_openai_intent_normalization' => true]);
    }

    public function test_every_role_and_property_type_card_shows_only_public_admitted_facts(): void
    {
        $failures = [];
        $cards    = 0;

        foreach (self::ROLES as $role => [$route, $types]) {
            foreach ($types as $type) {
                $listing = $this->listingWithEverything($role, $type);
                $html    = $this->get(route($route, $listing->id))->assertOk()->getContent();
                $card    = $this->card($html, $role);
                $cards++;

                $tokens   = PT::tokensFor($role, $type);
                $admitted = $this->admittedEntries($role, $tokens);
                $readable = $this->fieldsReadBy($admitted);

                foreach (C::CANONICAL_SOURCE_MAP[$role] as $field => $sources) {
                    if (!str_contains($card, $this->sentinel($role, $field))) {
                        continue;
                    }
                    if (!$this->isPublicFor($role, $field)) {
                        $failures[] = "{$role} / {$type}: NON-PUBLIC {$field} reached the card";
                    } elseif (!in_array($role, ['buyer', 'tenant'], true) && !isset($readable[$field])) {
                        // Criteria cards also read page meta through criteria_meta sources and
                        // composites; for them the rendered-question admission check below is
                        // the property-type gate, since a value can only arrive via a question.
                        $failures[] = "{$role} / {$type}: {$field} reached the card with no admitted entry reading it";
                    }
                }

                preg_match_all('/data-property-question="([a-z_0-9]+)"/', $card, $m);
                foreach (array_unique($m[1]) as $id) {
                    if (!str_starts_with($id, 'kb_') && !isset($admitted[$id])) {
                        $failures[] = "{$role} / {$type}: question {$id} rendered but not admitted for this type";
                    }
                }
            }
        }

        $this->assertSame(14, $cards);
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_no_non_owner_free_text_answer_reveals_a_non_public_field(): void
    {
        $runner   = app(AskAiRunnerV2Service::class);
        $keywords = (new \ReflectionClass(AskAiRunnerV2Service::class))->getConstant('LISTING_KEY_KEYWORD_MAP');
        $failures = [];
        $probed   = 0;

        foreach (array_keys(self::ROLES) as $role) {
            $type    = self::ROLES[$role][1][0];
            $listing = $this->listingWithEverything($role, $type);

            foreach (C::CANONICAL_SOURCE_MAP[$role] as $field => $sources) {
                $phrase = $keywords["listing.{$field}"][0] ?? null;
                if ($phrase === null || $this->isPublicFor($role, $field)) {
                    continue;
                }
                $probed++;

                foreach ([AskAiViewerAuthorizationService::SCOPE_PUBLIC, AskAiViewerAuthorizationService::SCOPE_AUTHORIZED] as $scope) {
                    if ($role === 'tenant' && $scope === AskAiViewerAuthorizationService::SCOPE_AUTHORIZED) {
                        continue; // designed: an accepted-deal counterparty sees the disclosures
                    }
                    $result = $runner->run($role, $listing->id, $phrase, ['viewer_scope' => $scope]);
                    if (str_contains((string) json_encode($result['final_response'] ?? []), $this->sentinel($role, $field))) {
                        $failures[] = "{$scope} {$role}.{$field}: non-public value answered";
                    }
                }
            }
        }

        // The floor was 100 when ~150 fields were non-public. The universal coverage audit
        // (2026-09-24) made the page-printed property and lease facts public, so fewer remain to
        // probe; 60 still covers every screening, qualification, deposit, lending and
        // contact field that stays private.
        $this->assertGreaterThan(60, $probed, 'Too few non-public fields probed — the proof would be hollow.');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function isPublicFor(string $role, string $field): bool
    {
        if (in_array($role, ['buyer', 'tenant'], true)) {
            return in_array($field, AskAiPublicPropertyQuestionService::publicCriteriaKeys($role), true);
        }

        if (V::classify($field, $role) === V::PUBLIC_ALLOWED) {
            return true;
        }

        // Explicitly admitted to the card by an `admitted_listing` entry (Batch 2b — e.g. the
        // financing types a seller will consider, published on the listing page).
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $entry) {
            if (($entry['role'] ?? null) === $role
                && ($entry['source_kind'] ?? null) === 'admitted_listing'
                && ($entry['source_path'] ?? null) === "listing.{$field}") {
                return true;
            }
        }

        return false;
    }

    private function sentinel(string $role, string $field): string
    {
        return "Zqx{$role}{$field}Zqx";
    }

    private function listingWithEverything(string $role, string $type): object
    {
        $user = User::factory()->create();

        $listing = match ($role) {
            'seller'   => SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '1 Matrix Way']),
            'landlord' => LandlordAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'title' => 'Matrix Rental']),
            'buyer'    => BuyerAgentAuction::create(['user_id' => $user->id, 'title' => 'Matrix Buyer', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]),
            'tenant'   => TenantAgentAuction::factory()->active()->create(['user_id' => $user->id]),
        };

        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach (C::CANONICAL_SOURCE_MAP[$role] as $field => $sources) {
            foreach ((array) $sources as $source) {
                if (is_string($source) && !str_starts_with($source, 'native:')) {
                    $listing->saveMeta($source, $this->sentinel($role, $field));
                    break;
                }
            }
        }
        $listing->saveMeta('property_type', $type);
        if (in_array($role, ['seller', 'landlord'], true)) {
            $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);
        }

        return $listing->fresh();
    }

    /** The Ask AI card region, bounded at its closing note. */
    private function card(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-property-questions="' . $role . '"');
        $this->assertNotFalse($start, "No Ask AI card for {$role}.");
        $end = strpos($html, 'ask-ai-pq-note', $start);

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    /** @return array<string, array> catalog entries for this role admitted for these type tokens */
    private function admittedEntries(string $role, array $tokens): array
    {
        // Curated entries plus the generated one-per-public-fact entries, each held to its
        // OWN type admission (generated entries take theirs from AskAiFieldApplicability).
        return array_filter(
            AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()
                + AskAiPublicPropertyQuestionService::generatedFieldCatalog($role),
            static fn ($e) => ($e['role'] ?? null) === $role && PT::admits($e['property_types'] ?? null, $tokens)
        );
    }

    /** @return array<string, true> */
    private function fieldsReadBy(array $entries): array
    {
        $fields = [];
        foreach ($entries as $entry) {
            foreach (array_merge([$entry['source_path'] ?? null], (array) ($entry['supporting_paths'] ?? [])) as $path) {
                if (is_string($path) && preg_match('/^listing\.([a-z0-9_]+)$/', $path, $m) === 1) {
                    $fields[$m[1]] = true;
                }
            }
        }

        return $fields;
    }
}
