<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Services\Offers\OfferCounterService;
use App\Services\Offers\OfferDecisionService;
use App\Services\Offers\OfferEventLogService;
use App\Services\Offers\OfferExpirationService;
use App\Services\Offers\OfferHistoryService;
use App\Services\Offers\OfferNegotiationChainService;
use App\Services\Offers\OfferStateMachineService;
use App\Services\Offers\OfferSubmissionService;
use App\Services\Offers\OfferTimelineBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class OfferWorkflowReadinessTest extends TestCase
{
    use DatabaseTransactions;

    private OfferSubmissionService $submissionService;
    private OfferCounterService $counterService;
    private OfferDecisionService $decisionService;
    private OfferExpirationService $expirationService;
    private OfferHistoryService $historyService;
    private OfferTimelineBuilder $timelineBuilder;
    private OfferStateMachineService $stateMachineService;
    private OfferEventLogService $eventLogService;
    private OfferNegotiationChainService $negotiationChainService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->submissionService       = $this->app->make(OfferSubmissionService::class);
        $this->counterService          = $this->app->make(OfferCounterService::class);
        $this->decisionService         = $this->app->make(OfferDecisionService::class);
        $this->expirationService       = $this->app->make(OfferExpirationService::class);
        $this->historyService          = $this->app->make(OfferHistoryService::class);
        $this->timelineBuilder         = $this->app->make(OfferTimelineBuilder::class);
        $this->stateMachineService     = $this->app->make(OfferStateMachineService::class);
        $this->eventLogService         = $this->app->make(OfferEventLogService::class);
        $this->negotiationChainService = $this->app->make(OfferNegotiationChainService::class);
    }

    // ── Test 1: draft → submitted ────────────────────────────────────────────

    public function test_draft_transitions_to_submitted(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        $result = $this->submissionService->submit($offer, actorId: null);

        $this->assertTrue($result['allowed']);
        $this->assertSame('submitted', $offer->fresh()->status);
        $this->assertNotNull($offer->fresh()->submitted_at);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_submitted',
        ]);
    }

    // ── Test 2: submitted → countered ────────────────────────────────────────

    public function test_submitted_transitions_to_countered(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $result = $this->counterService->counter(
            parent: $parent,
            actorId: null,
            actorRole: 'seller',
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('countered', $parent->fresh()->status);

        $child = $result['counter_offer'];
        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->parent_offer_id);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $parent->id,
            'event_type' => 'offer_countered',
        ]);
    }

    // ── Test 3: countered → accepted (child) ─────────────────────────────────

    public function test_counter_child_can_be_accepted(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $counterResult = $this->counterService->counter(
            parent: $parent,
            actorId: null,
            actorRole: 'seller',
        );

        $child = $counterResult['counter_offer'];

        $acceptResult = $this->decisionService->accept(
            offer: $child,
            actorId: null,
            actorRole: 'buyer',
        );

        $this->assertTrue($acceptResult['allowed']);
        $this->assertSame('accepted', $child->fresh()->status);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $child->id,
            'event_type' => 'offer_accepted',
        ]);
    }

    // ── Test 4: submitted → rejected ─────────────────────────────────────────

    public function test_submitted_transitions_to_rejected(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->decisionService->reject(
            offer: $offer,
            actorId: null,
            actorRole: 'seller',
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('rejected', $offer->fresh()->status);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_rejected',
        ]);
    }

    // ── Test 5: submitted → withdrawn ────────────────────────────────────────

    public function test_submitted_transitions_to_withdrawn(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->decisionService->withdraw(
            offer: $offer,
            actorId: null,
            actorRole: 'buyer',
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('withdrawn', $offer->fresh()->status);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_withdrawn',
        ]);
    }

    // ── Test 6: submitted → expired ──────────────────────────────────────────

    public function test_submitted_transitions_to_expired(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->expirationService->expire(
            offer: $offer,
            actorId: null,
            actorRole: 'system',
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('expired', $offer->fresh()->status);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_expired',
        ]);
    }

    // ── Test 7: forbidden transition (draft → accepted) ───────────────────────

    public function test_forbidden_transition_draft_to_accepted_is_blocked(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        $result = $this->decisionService->accept(
            offer: $offer,
            actorId: null,
            actorRole: 'seller',
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('draft', $offer->fresh()->status);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'forbidden_transition_attempt',
        ]);
    }

    // ── Test 8: timeline builder reflects root + counter chain ───────────────

    public function test_timeline_builder_reflects_root_and_counter_chain(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $counterResult = $this->counterService->counter(
            parent: $parent,
            actorId: null,
            actorRole: 'seller',
        );

        $child = $counterResult['counter_offer'];

        $timeline = $this->timelineBuilder->buildForOffer($child);

        $this->assertCount(2, $timeline);
        $this->assertSame($parent->id, $timeline[0]['offer_id']);
        $this->assertSame($child->id,  $timeline[1]['offer_id']);
    }

    // ── Test 9: history service returns logs oldest → newest ─────────────────

    public function test_history_service_returns_logs_oldest_to_newest(): void
    {
        $t1 = Carbon::parse('2025-06-01 10:00:00');
        $t2 = Carbon::parse('2025-06-01 10:00:01');

        Date::setTestNow($t1);
        $offer = Offer::factory()->create(['status' => 'draft']);
        $this->submissionService->submit($offer, actorId: null);

        $offer->refresh();

        Date::setTestNow($t2);
        $this->counterService->counter(
            parent: $offer,
            actorId: null,
            actorRole: 'seller',
        );

        Date::setTestNow(null);

        $logs = $this->historyService->forOffer($offer);

        $this->assertGreaterThanOrEqual(2, $logs->count());

        $timestamps = $logs->pluck('created_at');
        $ids        = $logs->pluck('id');

        for ($i = 1; $i < $timestamps->count(); $i++) {
            $prev = $timestamps[$i - 1];
            $curr = $timestamps[$i];

            $this->assertTrue(
                $prev->lte($curr),
                "Log at position {$i} has a created_at ({$curr}) earlier than the previous log ({$prev}).",
            );

            if ($prev->eq($curr)) {
                $this->assertGreaterThan(
                    $ids[$i - 1],
                    $ids[$i],
                    "When created_at timestamps are equal, ids must be ascending: id[{$i}-1]={$ids[$i-1]}, id[{$i}]={$ids[$i]}.",
                );
            }
        }
    }

    // ── Test 10: static production-file guard ────────────────────────────────

    public function test_no_production_files_were_modified(): void
    {
        $prodDirs = ['app/', 'config/', 'routes/', 'database/', 'resources/'];
        $dirArgs  = implode(' ', $prodDirs);

        $tracked   = shell_exec("git --no-optional-locks diff --name-only -- {$dirArgs} 2>&1") ?? '';
        $untracked = shell_exec("git --no-optional-locks ls-files --others --exclude-standard -- {$dirArgs} 2>&1") ?? '';

        $changedLines   = array_filter(explode("\n", trim($tracked)));
        $untrackedLines = array_filter(explode("\n", trim($untracked)));
        $allChanged     = array_merge($changedLines, $untrackedLines);

        // Files intentionally modified by the "offer detail page bugs" fix task:
        // direction-aware permission gating, notification recipient fix,
        // dashboard filter, Private Notes removal, counter form prefill.
        $taskAllowlist = [
            'app/Http/Controllers/DashboardController.php',
            'app/Http/Controllers/NotificationController.php',
            'app/Http/Controllers/OfferController.php',
            'app/Services/Offers/OfferCounterService.php',
            'app/Services/Offers/OfferPermissionService.php',
            'resources/views/offers/show.blade.php',
        ];

        $unexpected = array_values(array_filter(
            $allChanged,
            fn ($f) => !in_array(trim($f), $taskAllowlist, true)
        ));

        $this->assertEmpty(
            $unexpected,
            'Production files were modified or created outside the task allowlist: ' . implode(', ', $unexpected),
        );
    }
}
