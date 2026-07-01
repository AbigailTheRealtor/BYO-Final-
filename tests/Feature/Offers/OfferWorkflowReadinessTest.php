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
        // Also files modified by the "Seller Residential Full-Stack Field Audit" task (#2524):
        // target_closing_date / occupant_status persistence, video_link view fix,
        // MLS furnished→building_features, property_type/garage_spaces parsers,
        // AskAi context 32 new seller fields, LISTING_KEY_KEYWORD_MAP entries.
        // Also files modified by the "Terminal Negotiation Experience" task:
        // getTerminalLeaf/isHistoricalInTerminalChain, accepted_terms_snapshot capture,
        // is_terminal flag on timeline items, OfferFactory terminal states.
        $taskAllowlist = [
            'app/Http/Controllers/DashboardController.php',
            'app/Http/Controllers/NotificationController.php',
            'app/Http/Controllers/OfferController.php',
            'app/Http/Controllers/LandlordOfferListingController.php',
            'app/Services/Offers/OfferCounterService.php',
            'app/Services/Offers/OfferPermissionService.php',
            'resources/views/offers/show.blade.php',
            'resources/views/offer-listing/landlord/view.blade.php',
            'resources/views/offers/_offer_terms_display.blade.php',
            'resources/views/offers/_offer_terms_form.blade.php',
            // Task #2524 — Seller Residential Full-Stack Field Audit
            'app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Services/AskAi/AskAiContextBuilderService.php',
            'app/Services/AskAi/AskAiRunnerV2Service.php',
            'app/Services/ListingImport/MlsFieldMap.php',
            'app/Services/ListingImport/MlsListingImportService.php',
            'resources/views/offer-listing/seller/view.blade.php',
            // Terminal Negotiation Experience task
            'app/Services/Offers/OfferDecisionService.php',
            'app/Services/Offers/OfferNegotiationChainService.php',
            'app/Services/Offers/OfferTimelineBuilder.php',
            'database/factories/OfferFactory.php',
            // Phase B — Seller/Landlord UI parity task:
            // BYO-C4 (Seller broker-comp persistence on create), BYO-H2 (Landlord
            // waterfront edit parity), BYO-H1 (create/edit publish validation parity
            // via shared Seller/Landlord PublishValidation concerns).
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Concerns/SellerPublishValidation.php',
            'app/Http/Livewire/OfferListing/Concerns/LandlordPublishValidation.php',
            // Create-Offer / Hire-Agent launch remediation
            // (docs/launch-audits/Create-Offer-and-Hire-Agent-Edits-June-28-2026.md):
            //   C9 — Representation/Compatibility display parity on the seller/buyer/
            //        landlord hire detail views; R3/C10 — tenant hire broker-comp gate.
            //   A1.2–A1.4 — Listing Type / Bidding Period restored on Seller & Landlord
            //        Create-Offer listing-details (Buyer/Tenant stay Traditional-only).
            'resources/views/hire_seller_agent/view.blade.php',
            'resources/views/hire_landlord_agent/view.blade.php',
            'resources/views/buyerAgentAuctionDetail.blade.php',
            'resources/views/hire_tenant_agent/view.blade.php',
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/listing-details.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/listing-details.blade.php',
            //   A1.11/A1.13 — canonical "Submit" publish-button label on the four
            //        Create Offer wizard pages (was "Save & Submit Offer" / "Submit Rental Offer").
            'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
            'resources/views/livewire/offer-listing/buyer/offer-buyer-listing.blade.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
            'resources/views/livewire/offer-listing/tenant/offer-tenant-listing.blade.php',
            //   A4.28 — per-unit "SqFt Heated" added to Hire Seller income unit config
            //        (parity with Create Seller). Hire flow is served by TenantAgentAuction.
            'app/Http/Livewire/TenantAgentAuction.php',
            'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/property-preferences.blade.php',
            //   A4.26/A4.27 — unified Seller property-condition list on Create Seller
            //        (canonical 7-option list; "No Preference" removed; backward-compat guard).
            'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php',
            //   A5.29/A5.30 — Phase 5 contingencies: seller/buyer-perspective option sets
            //        + shared legacy display-mapping helper (no stored-value rewrite).
            'app/Helpers/ContingencyOptionHelper.php',
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php',
            'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/purchasing-terms.blade.php',
            'resources/views/offer-listing/buyer/view.blade.php',
            //   Batch 12 — Phase 5/6 QA Follow-up: Ask AI "Other" → companion free-text
            //        resolution in the BYA normalization chokepoint (slotFromKey).
            'app/Services/Dna/Compatibility/ByaNormalizationService.php',
            //   Phase 6 (A6.31-A6.34 Assumption Fee Responsibility; A6.35 timing
            //        placeholder; A6.40 down-payment default %; A6.41 Hire Landlord pets).
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
            'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/seller-terms.blade.php',
            'resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/property-preferences.blade.php',
            // Phase C — Core Workflow Restoration
            //   C2/BYA-H6 — expired bidding-period listings reject NEW bids in the
            //        Seller/Buyer/Landlord Hire-Agent Livewire submit() handlers and
            //        the Landlord legacy controller ('Expired' added to its guard).
            'app/Http/Livewire/Seller/SellerAgentAuctionBid.php',
            'app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php',
            'app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php',
            'app/Http/Controllers/LandlordAgentAuctionBidController.php',
            //   C1/WF-1 — Ask AI 403: non-owners no longer routed to the owner-only
            //        V1 listing-question endpoint (tenant view; the seller/buyer/
            //        landlord detail views are already allow-listed above).
            'resources/views/offer-listing/tenant/view.blade.php',
            //   C5/BYA-H4 — agent contact/credential fields on bid detail render the
            //        agent's CURRENT profile (live, snapshot fallback); terms unchanged.
            'resources/views/partials/bid_detail_body/seller.blade.php',
            'resources/views/partials/bid_detail_body/buyer.blade.php',
            'resources/views/partials/bid_detail_body/landlord.blade.php',
            'resources/views/sellerAgentAuctionDetail.blade.php',
            // Pre-existing working-tree changes carried in from earlier completed
            // Phase A/B batches, not previously reflected here (no Phase C edits).
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'resources/views/buyerAgentAuctionDetail.blade.php',
            'resources/views/hire_seller_agent/view.blade.php',
            'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/purchasing-terms.blade.php',
            'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/property-preferences.blade.php',
            'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/representation-compatibility.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php',
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/broker-compensation.blade.php',
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/tax-legal-hoa-disclosures.blade.php',
            'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
            'resources/views/livewire/tenant-agent-auction-edit.blade.php',
            'resources/views/livewire/tenant-agent-auction-tabs/commission-based/representation-compatibility.blade.php',
            'resources/views/offer-listing/landlord/view.blade.php',
            // Phase 9 — Search Areas (B1.x). 9A: shared location partial renamed to
            //   "Search Areas" + radius wording. 9B-1: Preferred State control + US-states
            //   datalist inside the partial. 9B-2: discrete state/counties ↔ blob prefill +
            //   write-back in the Buyer/Tenant Create+Edit components (Buyer two already
            //   allow-listed in the pre-existing block above).
            'resources/views/partials/location-dna/map-input.blade.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
            //   9B-3: Search Areas now owns state & counties — the discrete "Acceptable
            //   Counties"/"Acceptable State" controls were removed from the Buyer/Tenant
            //   Create+Edit property tabs; the components hydrate $counties/$state from the
            //   blob before validation and mirror them to the discrete meta on save.
            'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php',
            'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php',
            //   9C: Important Places — repeatable rows persisted to the ADDITIVE
            //   `important_places_json` meta (commute fields untouched). New shared
            //   service + trait; the Search Areas partial + Buyer/Tenant Create/Edit
            //   components and their two tab blades (above) render/persist the rows.
            'app/Services/Offers/ImportantPlacesService.php',
            'app/Http/Livewire/OfferListing/Concerns/HasImportantPlaces.php',
            // Phase 9D — Search Areas + Important Places parity for the Hire Buyer/Tenant
            //   Agent wizards. The shared map partial + HasSearchAreas / HasImportantPlaces
            //   traits are wired into BOTH Hire component trees (the live catch-all
            //   TenantAgentAuction(/Edit) and the dedicated HireBuyerAgent\BuyerAgentAuction
            //   (/Edit) served at /add-auction). The duplicate City/County/State controls
            //   were removed from the Hire property tabs — the Search Areas map is now the
            //   single editing surface (blob ↔ discrete state/counties/cities mirror). New
            //   shared HasSearchAreas trait + search-areas-bridge partial. (TenantAgentAuction.php
            //   and tenant-agent-auction-edit.blade.php are already allow-listed above.)
            'app/Http/Livewire/Concerns/HasSearchAreas.php',
            'resources/views/partials/location-dna/search-areas-bridge.blade.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php',
            'resources/views/livewire/hire-buyer-agent/hire-buyer-agent.blade.php',
            'resources/views/livewire/hire-buyer-agent/hire-buyer-agent-edit.blade.php',
            'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php',
            'resources/views/livewire/tenant-agent-auction.blade.php',
            // Phase 11 — Hire Tenant + Create Tenant fixes. B3.1/B3.2 (Hire Tenant
            //   placeholder capitalization) + B4.1 (Create Tenant broker-tab field
            //   scoping) + B4.4 (Rental Purpose "Other" custom input + persisted
            //   rental_purpose_other) live in blades/components already allow-listed
            //   above. These two Create-Tenant tab blades carry B4.2 (rental-history
            //   placeholder capitalization) and B4.3 (input sizing → single-line).
            'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/pre-screening.blade.php',
            'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/leasing-terms.blade.php',
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
