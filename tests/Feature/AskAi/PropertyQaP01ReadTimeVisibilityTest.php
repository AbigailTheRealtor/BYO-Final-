<?php

namespace Tests\Feature\AskAi;

use App\Models\AskAiAnswer;
use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Models\AskAiQuestion;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PropertyQaP01ReadTimeVisibilityTest — P0.1 defect 2.
 *
 * P0 made SnapshotFactVisibility fail closed on the WRITE side. The independent review
 * found nothing enforced it on the READ side: AskAiKnowledgeSearchService gated on
 * `restricted` alone and ignored `public_allowed`, `visibility` and the viewer scope it was
 * already being handed in $options. 'owner_only' was therefore a classification with no
 * reader, and the database-first path would serve an owner-only fact to any authenticated
 * non-owner reaching POST /api/ask-ai/ask — which authenticates but, unlike
 * /ask-ai/listing-question, performs no ownership check of its own.
 *
 * These tests pin the three tiers at read time, per scope:
 *
 *   restricted     → blocked for EVERY scope, owner included (a compliance obligation).
 *   owner_only     → served to the owner, blocked for authorized and public.
 *   public_allowed → served to every scope.
 *
 * plus the fail-closed default: no viewer_scope means the guest's answer, not the owner's.
 */
class PropertyQaP01ReadTimeVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private const OWNER      = AskAiViewerAuthorizationService::SCOPE_OWNER;
    private const AUTHORIZED = AskAiViewerAuthorizationService::SCOPE_AUTHORIZED;
    private const PUBLIC     = AskAiViewerAuthorizationService::SCOPE_PUBLIC;

    /** Every scope that is not the owner. Both must be treated identically here. */
    private const NON_OWNER_SCOPES = [self::AUTHORIZED, self::PUBLIC];

    private function service(): AskAiKnowledgeSearchService
    {
        return new AskAiKnowledgeSearchService();
    }

    private function snapshot(string $listingType, int $listingId): AskAiKnowledgeSnapshot
    {
        return AskAiKnowledgeSnapshot::create([
            'listing_type'  => $listingType,
            'listing_id'    => $listingId,
            'version'       => 1,
            'status'        => 'ready',
            'snapshot_uuid' => (string) Str::uuid(),
            'built_at'      => now(),
        ]);
    }

    /**
     * Persist a fact in exactly the shape the four snapshot builders write, so the tiers
     * under test are the tiers production actually stores.
     */
    private function fact(
        AskAiKnowledgeSnapshot $snap,
        string $key,
        string $value,
        string $visibility
    ): AskAiFact {
        return AskAiFact::create([
            'snapshot_id'    => $snap->id,
            'canonical_key'  => $key,
            'value'          => $value,
            'visibility'     => $visibility,
            'listing_type'   => $snap->listing_type,
            'listing_id'     => $snap->listing_id,
            'label'          => SnapshotFactVisibility::deriveLabel($key),
            'value_type'     => 'string',
            'source_path'    => 'context.listing.' . $key,
            'classification' => match ($visibility) {
                SnapshotFactVisibility::RESTRICTED     => 'compliance_sensitive',
                SnapshotFactVisibility::PUBLIC_ALLOWED => 'public_factual',
                default                                => 'owner_only',
            },
            'public_allowed' => $visibility === SnapshotFactVisibility::PUBLIC_ALLOWED,
            'restricted'     => $visibility === SnapshotFactVisibility::RESTRICTED,
            'sort_order'     => 0,
        ]);
    }

    private function answer(
        AskAiKnowledgeSnapshot $snap,
        string $key,
        string $text,
        string $visibility
    ): AskAiAnswer {
        return AskAiAnswer::create([
            'snapshot_id'    => $snap->id,
            'canonical_key'  => $key,
            'answer_text'    => $text,
            'classification' => 'faq_answer',
            'visibility'     => $visibility,
            'source_path'    => 'context.faq_answers.' . $key,
            'sort_order'     => 0,
        ]);
    }

    private function question(AskAiKnowledgeSnapshot $snap, string $canonicalKey, string $text): AskAiQuestion
    {
        return AskAiQuestion::create([
            'snapshot_id'     => $snap->id,
            'canonical_key'   => $canonicalKey,
            'field_type'      => 'listing_model',
            'question_text'   => $text,
            'sample_question' => $text,
            'source_path'     => 'registry.' . $canonicalKey,
            'sort_order'      => 0,
        ]);
    }

    /** Search with an explicit scope, via the canonical-key path. */
    private function searchKey(
        AskAiKnowledgeSnapshot $snap,
        string $canonicalKey,
        ?string $scope
    ): array {
        $options = ['normalized_field_key' => $canonicalKey];
        if ($scope !== null) {
            $options['viewer_scope'] = $scope;
        }

        return $this->service()->search(
            $snap->listing_type,
            $snap->listing_id,
            'any question',
            $options
        );
    }

    // =========================================================================
    // owner_only — facts
    // =========================================================================

    public function test_owner_receives_an_owner_only_fact(): void
    {
        $snap = $this->snapshot('seller', 920101);
        $this->fact($snap, 'minimum_cap_rate', '7.25', SnapshotFactVisibility::OWNER_ONLY);

        $result = $this->searchKey($snap, 'listing.minimum_cap_rate', self::OWNER);

        $this->assertSame('database_hit', $result['outcome'], 'The owner must still get their own fact.');
        $this->assertSame('7.25', $result['answer']);
    }

    public function test_authenticated_non_owner_cannot_receive_the_same_owner_only_fact(): void
    {
        $snap = $this->snapshot('seller', 920102);
        $this->fact($snap, 'minimum_cap_rate', '7.25', SnapshotFactVisibility::OWNER_ONLY);

        foreach (self::NON_OWNER_SCOPES as $scope) {
            $result = $this->searchKey($snap, 'listing.minimum_cap_rate', $scope);

            $this->assertSame(
                'restricted',
                $result['outcome'],
                "Scope '{$scope}' must be blocked from an owner_only fact."
            );
            $this->assertNull(
                $result['answer'],
                "Scope '{$scope}' must receive no answer text for an owner_only fact."
            );
            $this->assertSame(
                'owner_only',
                $result['source']['visibility_block'] ?? null,
                'The block must be reported as an ownership block, not a compliance block.'
            );
        }
    }

    /**
     * The specific value the whole P0 effort is about: a seller's negotiating floor must not
     * reach a non-owner, and the stored value must not appear anywhere in the result.
     */
    public function test_seller_minimum_values_remain_unavailable_to_non_owners(): void
    {
        $snap = $this->snapshot('seller', 920103);
        $this->fact($snap, 'minimum_cap_rate', '7.25', SnapshotFactVisibility::OWNER_ONLY);
        $this->fact($snap, 'minimum_annual_net_income', '412000', SnapshotFactVisibility::OWNER_ONLY);

        foreach (['listing.minimum_cap_rate', 'listing.minimum_annual_net_income'] as $key) {
            foreach (self::NON_OWNER_SCOPES as $scope) {
                $result     = $this->searchKey($snap, $key, $scope);
                $serialized = json_encode($result);

                $this->assertSame('restricted', $result['outcome'], "{$key} / {$scope}");
                $this->assertStringNotContainsString('7.25', $serialized, "{$key} / {$scope}");
                $this->assertStringNotContainsString('412000', $serialized, "{$key} / {$scope}");
            }
        }
    }

    // =========================================================================
    // public_allowed — still served
    // =========================================================================

    public function test_public_allowed_fact_remains_available_to_every_scope(): void
    {
        $snap = $this->snapshot('seller', 920104);
        $this->fact($snap, 'bedrooms', '4', SnapshotFactVisibility::PUBLIC_ALLOWED);

        foreach ([self::OWNER, self::AUTHORIZED, self::PUBLIC] as $scope) {
            $result = $this->searchKey($snap, 'listing.bedrooms', $scope);

            $this->assertSame(
                'database_hit',
                $result['outcome'],
                "A public_allowed fact must still be served to scope '{$scope}'."
            );
            $this->assertSame('4', $result['answer']);
        }
    }

    /**
     * A row whose two columns disagree — visibility says public, the boolean says otherwise —
     * is withheld. Half-written rows must not be readable as public.
     */
    public function test_a_fact_whose_visibility_columns_disagree_is_withheld_from_non_owners(): void
    {
        $snap = $this->snapshot('seller', 920105);
        $fact = $this->fact($snap, 'bedrooms', '4', SnapshotFactVisibility::PUBLIC_ALLOWED);
        $fact->update(['public_allowed' => false]);

        $result = $this->searchKey($snap, 'listing.bedrooms', self::PUBLIC);
        $this->assertSame('restricted', $result['outcome']);

        // The owner is unaffected: the fact is not compliance-restricted.
        $this->assertSame('database_hit', $this->searchKey($snap, 'listing.bedrooms', self::OWNER)['outcome']);
    }

    // =========================================================================
    // restricted — unchanged, blocked for everyone
    // =========================================================================

    public function test_restricted_fact_remains_blocked_for_every_scope_including_the_owner(): void
    {
        $snap = $this->snapshot('landlord', 920106);
        $this->fact($snap, 'security_deposit', '3200', SnapshotFactVisibility::RESTRICTED);

        foreach ([self::OWNER, self::AUTHORIZED, self::PUBLIC] as $scope) {
            $result = $this->searchKey($snap, 'listing.security_deposit', $scope);

            $this->assertSame(
                'restricted',
                $result['outcome'],
                "A restricted fact must stay blocked for scope '{$scope}' — compliance is not "
                . 'an ownership question.'
            );
            $this->assertNull($result['answer']);
        }

        // A compliance block must NOT be mislabelled as an ownership block, so the two
        // remain distinguishable in traces.
        $ownerResult = $this->searchKey($snap, 'listing.security_deposit', self::OWNER);
        $this->assertArrayNotHasKey('visibility_block', $ownerResult['source']);
    }

    // =========================================================================
    // Fail-closed default
    // =========================================================================

    public function test_missing_viewer_scope_fails_closed_to_public(): void
    {
        $snap = $this->snapshot('seller', 920107);
        $this->fact($snap, 'minimum_cap_rate', '7.25', SnapshotFactVisibility::OWNER_ONLY);

        // No viewer_scope at all.
        $result = $this->searchKey($snap, 'listing.minimum_cap_rate', null);
        $this->assertSame(
            'restricted',
            $result['outcome'],
            'A caller that omits viewer_scope must get the guest answer, never the owner one.'
        );
    }

    public function test_unrecognised_viewer_scope_fails_closed_to_public(): void
    {
        $snap = $this->snapshot('seller', 920108);
        $this->fact($snap, 'minimum_cap_rate', '7.25', SnapshotFactVisibility::OWNER_ONLY);

        foreach (['', 'admin', 'OWNER_OF_SORTS', 'superuser', 'true'] as $bogus) {
            $result = $this->searchKey($snap, 'listing.minimum_cap_rate', $bogus);
            $this->assertSame(
                'restricted',
                $result['outcome'],
                "An unrecognised scope '{$bogus}' must fail closed to public."
            );
        }
    }

    /**
     * The scope string the controllers actually set must be the one that grants owner
     * access — if the constant and the consumer ever drift, this fails.
     */
    public function test_the_owner_scope_constant_is_what_grants_access(): void
    {
        $snap = $this->snapshot('seller', 920109);
        $this->fact($snap, 'minimum_cap_rate', '7.25', SnapshotFactVisibility::OWNER_ONLY);

        $this->assertSame('owner', AskAiViewerAuthorizationService::SCOPE_OWNER);
        $this->assertSame(
            'database_hit',
            $this->searchKey($snap, 'listing.minimum_cap_rate', 'owner')['outcome']
        );
    }

    // =========================================================================
    // owner_only — KB answers
    // =========================================================================

    public function test_owner_only_kb_answer_reaches_the_owner_and_no_one_else(): void
    {
        $snap = $this->snapshot('seller', 920201);
        $this->answer(
            $snap,
            'faq_answers.roof_age_and_condition',
            'Replaced in 2019, still under warranty.',
            SnapshotFactVisibility::OWNER_ONLY
        );

        $owner = $this->searchKey($snap, 'faq_answers.roof_age_and_condition', self::OWNER);
        $this->assertSame('database_hit', $owner['outcome']);
        $this->assertSame('Replaced in 2019, still under warranty.', $owner['answer']);

        foreach (self::NON_OWNER_SCOPES as $scope) {
            $result = $this->searchKey($snap, 'faq_answers.roof_age_and_condition', $scope);
            $this->assertSame('restricted', $result['outcome'], "scope {$scope}");
            $this->assertNull($result['answer'], "scope {$scope}");
            $this->assertStringNotContainsString('warranty', json_encode($result), "scope {$scope}");
        }
    }

    public function test_public_allowed_kb_answer_is_still_served_to_non_owners(): void
    {
        $snap = $this->snapshot('seller', 920202);
        $this->answer(
            $snap,
            'faq_answers.roof_age_and_condition',
            'Replaced in 2019.',
            SnapshotFactVisibility::PUBLIC_ALLOWED
        );

        foreach ([self::OWNER, self::AUTHORIZED, self::PUBLIC] as $scope) {
            $result = $this->searchKey($snap, 'faq_answers.roof_age_and_condition', $scope);
            $this->assertSame('database_hit', $result['outcome'], "scope {$scope}");
            $this->assertSame('Replaced in 2019.', $result['answer']);
        }
    }

    // =========================================================================
    // The gate covers every entry path, not just the canonical-key one
    // =========================================================================

    /**
     * search() has three ways in — exact question text, canonical key, and normalised
     * variant — and they converge on the same rows. A gate on one path only would leave the
     * others open, so the exact-question path is pinned separately.
     */
    public function test_the_exact_question_path_is_gated_too(): void
    {
        $snap = $this->snapshot('seller', 920301);
        $this->fact($snap, 'minimum_cap_rate', '7.25', SnapshotFactVisibility::OWNER_ONLY);
        $this->question($snap, 'listing.minimum_cap_rate', 'What is the minimum cap rate?');

        $blocked = $this->service()->search('seller', 920301, 'What is the minimum cap rate?', [
            'viewer_scope' => self::AUTHORIZED,
        ]);
        $this->assertSame('restricted', $blocked['outcome']);
        $this->assertStringNotContainsString('7.25', json_encode($blocked));

        $allowed = $this->service()->search('seller', 920301, 'What is the minimum cap rate?', [
            'viewer_scope' => self::OWNER,
        ]);
        $this->assertSame('database_hit', $allowed['outcome']);
        $this->assertSame('7.25', $allowed['answer']);
    }

    /**
     * The bare-key path (a canonical key with neither 'listing.' nor 'faq_answers.' prefix)
     * is a third way into the same tables and needed the same gate.
     */
    public function test_the_bare_key_path_is_gated_too(): void
    {
        $snap = $this->snapshot('seller', 920302);
        $this->fact($snap, 'minimum_cap_rate', '7.25', SnapshotFactVisibility::OWNER_ONLY);
        $this->question($snap, 'minimum_cap_rate', 'Bare key question?');

        $blocked = $this->service()->search('seller', 920302, 'Bare key question?', [
            'viewer_scope' => self::PUBLIC,
        ]);
        $this->assertSame('restricted', $blocked['outcome']);
        $this->assertStringNotContainsString('7.25', json_encode($blocked));

        $allowed = $this->service()->search('seller', 920302, 'Bare key question?', [
            'viewer_scope' => self::OWNER,
        ]);
        $this->assertSame('database_hit', $allowed['outcome']);
    }

    // =========================================================================
    // Buyer / tenant criteria
    // =========================================================================

    /**
     * Buyer and tenant facts are all owner_only by construction (decision D2). At read time
     * that must mean a non-owner gets nothing, for the budget fields especially.
     */
    public function test_buyer_and_tenant_criteria_are_never_readable_by_a_non_owner(): void
    {
        foreach (['buyer' => 920401, 'tenant' => 920402] as $role => $listingId) {
            $snap = $this->snapshot($role, $listingId);
            $this->fact($snap, 'bedrooms', '3', SnapshotFactVisibility::OWNER_ONLY);
            $this->fact($snap, 'max_purchase_price', '875000', SnapshotFactVisibility::OWNER_ONLY);

            foreach (['listing.bedrooms', 'listing.max_purchase_price'] as $key) {
                foreach (self::NON_OWNER_SCOPES as $scope) {
                    $result = $this->searchKey($snap, $key, $scope);
                    $this->assertSame(
                        'restricted',
                        $result['outcome'],
                        "{$role} {$key} must not be readable by scope '{$scope}'."
                    );
                    $this->assertStringNotContainsString('875000', json_encode($result));
                }
            }

            // The owner of the criteria listing still sees their own numbers.
            $this->assertSame(
                'database_hit',
                $this->searchKey($snap, 'listing.max_purchase_price', self::OWNER)['outcome'],
                "The {$role} must still be able to ask about their own criteria."
            );
        }
    }

    // =========================================================================
    // Blank and not-found behaviour is unchanged by the gate
    // =========================================================================

    public function test_a_blank_public_fact_still_reports_information_not_provided(): void
    {
        $snap = $this->snapshot('seller', 920501);
        $this->fact($snap, 'bedrooms', '', SnapshotFactVisibility::PUBLIC_ALLOWED);

        $result = $this->searchKey($snap, 'listing.bedrooms', self::PUBLIC);

        $this->assertSame('blank_information_not_provided', $result['outcome']);
        $this->assertSame(AskAiKnowledgeSearchService::INFORMATION_NOT_PROVIDED, $result['answer']);
    }

    public function test_an_unknown_key_is_still_not_found_for_every_scope(): void
    {
        $snap = $this->snapshot('seller', 920502);

        foreach ([self::OWNER, self::AUTHORIZED, self::PUBLIC] as $scope) {
            $result = $this->searchKey($snap, 'listing.no_such_key', $scope);
            $this->assertSame('not_found', $result['outcome'], "scope {$scope}");
        }
    }
}
