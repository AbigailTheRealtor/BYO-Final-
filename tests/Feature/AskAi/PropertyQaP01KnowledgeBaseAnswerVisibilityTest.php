<?php

namespace Tests\Feature\AskAi;

use App\Models\AskAiAnswer;
use App\Models\AskAiFact;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiKnowledgeSnapshotBuilderService;
use App\Services\AskAi\Snapshot\BuyerSnapshotBuilder;
use App\Services\AskAi\Snapshot\LandlordSnapshotBuilder;
use App\Services\AskAi\Snapshot\SellerSnapshotBuilder;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use App\Services\AskAi\Snapshot\TenantSnapshotBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PropertyQaP01KnowledgeBaseAnswerVisibilityTest — P0.1 defect 3.
 *
 * P0 changed all four snapshot builders so an owner-authored knowledge-base answer persists
 * with visibility = 'owner_only' instead of 'public_allowed'. The independent review found
 * NOTHING asserted it: every one of the four `persistAnswers()` methods could have been
 * reverted to 'public_allowed' and the entire suite would still have passed.
 *
 * These tests close that hole. They deliberately do NOT call
 * SnapshotFactVisibility::classify() and assert on its return value — that would test the
 * classifier, which is already covered, and would pass even if a builder ignored it. Instead
 * each test drives the REAL builder through AskAiKnowledgeSnapshotBuilderService::build()
 * and reads back the row that landed in `ask_ai_answers`.
 *
 * The context builder is mocked so the assertions are about persistence and nothing else:
 * a fixed context in, a stored visibility out. That also keeps the test independent of
 * whether any particular FAQ question happens to be present in config/ai_faq_*.php.
 *
 * WHY THIS MATTERS BEYOND THE FLAG: `ask_ai_answers.visibility` has a DATABASE DEFAULT of
 * 'public_allowed' (see 2026_06_11_000006_extend_ask_ai_snapshot_schema). A builder that
 * stopped passing the column explicitly would not write a null — it would write
 * 'public_allowed' and look deliberate. Asserting the stored string is the only way to
 * catch that.
 */
class PropertyQaP01KnowledgeBaseAnswerVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    /** Distinctive answer text so the row under test cannot be confused with another. */
    private const ANSWER_TEXT = 'The roof was replaced in 2019 and is under warranty.';

    /** A canonical FAQ key shape the builders persist verbatim. */
    private const FAQ_KEY = 'roof_age_and_condition';

    /**
     * @return array<string, array{0: string, 1: int}>  role => [role, listingId]
     */
    public static function roleProvider(): array
    {
        return [
            'seller'   => ['seller',   910101],
            'buyer'    => ['buyer',    910102],
            'landlord' => ['landlord', 910103],
            'tenant'   => ['tenant',   910104],
        ];
    }

    private function makeServiceReturning(array $context): AskAiKnowledgeSnapshotBuilderService
    {
        $cb = $this->createMock(AskAiContextBuilderService::class);
        $cb->method('buildForListing')->willReturn($context);

        return new AskAiKnowledgeSnapshotBuilderService(
            new SellerSnapshotBuilder($cb),
            new BuyerSnapshotBuilder($cb),
            new LandlordSnapshotBuilder($cb),
            new TenantSnapshotBuilder($cb),
        );
    }

    private function context(): array
    {
        return [
            // An ordinary property fact, present so each snapshot also exercises the fact
            // path and the fact/answer tiers can be compared within one build.
            'listing'     => ['bedrooms' => '4'],
            'faq_answers' => [self::FAQ_KEY => self::ANSWER_TEXT],
        ];
    }

    // =========================================================================
    // The core assertion, once per role
    // =========================================================================

    /**
     * @dataProvider roleProvider
     */
    public function test_builder_persists_kb_answers_as_owner_only(string $role, int $listingId): void
    {
        $snapshot = $this->makeServiceReturning($this->context())->build($role, $listingId);

        $answer = AskAiAnswer::where('snapshot_id', $snapshot->id)
            ->where('canonical_key', self::FAQ_KEY)
            ->first();

        $this->assertNotNull(
            $answer,
            "The {$role} builder must have persisted the KB answer — otherwise this test "
            . 'asserts nothing about its visibility.'
        );
        $this->assertSame(
            self::ANSWER_TEXT,
            $answer->answer_text,
            'Sanity: the row found is the one this test seeded.'
        );

        $this->assertSame(
            SnapshotFactVisibility::OWNER_ONLY,
            $answer->visibility,
            "The {$role} builder must persist an owner-authored KB answer as 'owner_only'. "
            . "Storing 'public_allowed' republishes the listing owner's own free-text answers "
            . 'to every viewer before the D3 per-key triage has approved any of them.'
        );

        // Stated explicitly so the failure message is unambiguous if the default returns.
        $this->assertNotSame(
            'public_allowed',
            $answer->visibility,
            "The {$role} builder regressed to 'public_allowed' for KB answers."
        );
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_stored_answer_visibility_is_never_left_to_the_database_default(
        string $role,
        int $listingId
    ): void {
        $snapshot = $this->makeServiceReturning($this->context())->build($role, $listingId);

        $visibilities = AskAiAnswer::where('snapshot_id', $snapshot->id)
            ->pluck('visibility')
            ->unique()
            ->values()
            ->all();

        $this->assertNotEmpty($visibilities, "The {$role} builder persisted no answers.");

        // `ask_ai_answers.visibility` defaults to 'public_allowed' in the schema, so "not
        // public_allowed" is simultaneously the product assertion and proof the builder
        // passed the column explicitly rather than omitting it.
        $this->assertSame(
            [SnapshotFactVisibility::OWNER_ONLY],
            $visibilities,
            "Every answer the {$role} builder persists must be 'owner_only'; found: "
            . implode(', ', array_map(fn ($v) => var_export($v, true), $visibilities))
        );
    }

    // =========================================================================
    // The answer tier is independent of the fact tier
    // =========================================================================

    /**
     * A seller listing is the one case where facts CAN be public, so it is the case that
     * proves the two tiers are decided separately: 'bedrooms' is public on a seller
     * listing, and the KB answer beside it is still owner_only.
     */
    public function test_seller_public_fact_and_owner_only_answer_coexist_in_one_snapshot(): void
    {
        $snapshot = $this->makeServiceReturning($this->context())->build('seller', 910201);

        $fact = AskAiFact::where('snapshot_id', $snapshot->id)
            ->where('canonical_key', 'bedrooms')
            ->first();

        $this->assertNotNull($fact);
        $this->assertSame(SnapshotFactVisibility::PUBLIC_ALLOWED, $fact->visibility);
        $this->assertTrue((bool) $fact->public_allowed);

        $answer = AskAiAnswer::where('snapshot_id', $snapshot->id)
            ->where('canonical_key', self::FAQ_KEY)
            ->first();

        $this->assertNotNull($answer);
        $this->assertSame(
            SnapshotFactVisibility::OWNER_ONLY,
            $answer->visibility,
            'A KB answer must stay owner_only even in a snapshot whose facts are public — '
            . 'the two tiers are decided independently and must not be conflated.'
        );
    }

    /**
     * Buyer and tenant snapshots must have no public row of either kind: their facts are
     * criteria (decision D2) and their answers are owner-authored.
     */
    public function test_buyer_and_tenant_snapshots_contain_no_public_rows_at_all(): void
    {
        foreach (['buyer' => 910301, 'tenant' => 910302] as $role => $listingId) {
            $snapshot = $this->makeServiceReturning($this->context())->build($role, $listingId);

            $publicFacts = AskAiFact::where('snapshot_id', $snapshot->id)
                ->where(function ($q) {
                    $q->where('public_allowed', true)
                      ->orWhere('visibility', 'public_allowed');
                })
                ->count();

            $this->assertSame(
                0,
                $publicFacts,
                "A {$role} snapshot must contain no public facts — {$role} listings are "
                . 'search criteria carrying budgets and negotiating positions.'
            );

            $publicAnswers = AskAiAnswer::where('snapshot_id', $snapshot->id)
                ->where('visibility', 'public_allowed')
                ->count();

            $this->assertSame(
                0,
                $publicAnswers,
                "A {$role} snapshot must contain no public KB answers."
            );
        }
    }
}
