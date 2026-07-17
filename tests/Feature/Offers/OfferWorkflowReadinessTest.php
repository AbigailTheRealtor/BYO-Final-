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
            // Phase 13 — A2.16 (S11): add "JPEG" to the on-screen Upload Document
            //   accepted-formats label (Seller + Landlord Create-Offer document
            //   upload) so visible copy matches the .jpeg the accept attribute allows.
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/documents-disclosures.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/documents-disclosures.blade.php',
            // Phase 8 — Tooltip / placeholder / UI-text audit (A8.50-A8.64).
            //   Text-only placeholder/label normalization: S2 sentence-case examples,
            //   Hire↔Create parity, real "Other" placeholders (no generic templates),
            //   and A8.57 removal of the phone-number example across all four Agent-Info
            //   tabs. No validation/persistence/behavior/JS changes. Create-Seller
            //   property-preferences/seller-terms/tax-legal + Hire-Seller
            //   property-preferences/seller-terms are already allow-listed above.
            'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/broker-compensation.blade.php',
            'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/services.blade.php',
            'resources/views/livewire/seller-agent-auction-bid-tabs/commission-based/agent-info.blade.php',
            'resources/views/livewire/buyer-agent-auction-bid-tabs/commission-based/agent-info.blade.php',
            'resources/views/livewire/landlord-agent-auction-bid-tabs/commission-based/agent-info.blade.php',
            'resources/views/livewire/tenant-agent-auction-bid-tabs/commission-based/agent-info.blade.php',
            // Browser QA #2 Part B — Landlord Pet Policy redesign. One canonical pet fee
            //   (pet_fee_type / pet_fee_amount / pet_fee_other) replaces the five retired
            //   legacy fee fields in the UI and the write path. The legacy EAV keys are
            //   never written, blanked or deleted; readers resolve them through the new
            //   PetFeeNormalizer precedence. ByoListingAdapter + PetFriendlinessScoreService
            //   carry the narrow DNA compatibility repair that keeps has_pet_fees true and
            //   preserves the recurring-vs-one-time distinction for the new schema (an
            //   "Other" fee is detected but deliberately NOT classified). The Landlord
            //   create/edit components, LandlordPublishValidation, the Lease Terms partial,
            //   the landlord detail view and AskAiContextBuilderService are already
            //   allow-listed above.
            'app/Services/Pets/PetFeeNormalizer.php',
            'app/Http/Livewire/OfferListing/Concerns/HasCanonicalPetFee.php',
            'app/Services/Canonical/Adapters/ByoListingAdapter.php',
            'app/Services/Dna/Scores/PetFriendlinessScoreService.php',
            'app/Services/AgentAi/Loaders/LandlordListingLoader.php',
            'app/Http/Controllers/AgentController.php',
            'resources/views/agent/offer-listing-view.blade.php',
            // Browser QA Batch 3 (#7) — friendly oversize upload error. A rejected upload was a
            //   SILENT no-op on three surfaces: the personal-photo input in all four *-info tabs
            //   had no listener at all; the Alpine-driven document rows discarded the failure in
            //   their @this.upload() error callback; and the two photo tabs bound
            //   `livewire-upload-error.window`, which caught errors from OTHER tabs' inputs and
            //   rendered the alert inside a hidden .tab-pane. The new shared
            //   <x-upload-error-boundary> wraps each input and listens on the wrapper (the
            //   Livewire event bubbles up from the input), so every alert is scoped to its own
            //   surface. The two documents-disclosures blades are already allow-listed above.
            //   #6 needed no code change — deploy/php/uploads.ini + PHP_INI_SCAN_DIR already
            //   raise the worker limits; only runtime/edge-proxy verification remains.
            'resources/views/components/upload-error-boundary.blade.php',
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-info.blade.php',
            'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/buyer-info.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/landlord-info.blade.php',
            'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/tenant-info.blade.php',
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/photos-tours-documents.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/photos-tours-documents.blade.php',
            // Browser QA Batch 4 (#26) — Agent Credentials placeholder capitalization. Three of the
            //   partial's seven placeholders were Title Case ("Enter Phone Number" / "Enter License
            //   Number" / "Enter NAR Member ID") while the other four were already sentence case.
            //   Text-only: three placeholder strings in the ONE shared partial, which fans out to 19
            //   include statements across the 8 Create + 8 Hire blades. No include site overrides or
            //   copies these strings, so the single edit is the whole fix — and
            //   BatchFourAgentCredentialsPlaceholderTest guards against a future copy. No validation,
            //   persistence, behavior or JS change.
            'resources/views/livewire/partials/agent-credentials.blade.php',
            // Browser QA Batch 5 (#1) — Landlord Commercial Broker Compensation. The Landlord's Broker
            //   Lease Fee partial was Residential-only, so Create/Edit Landlord + Commercial rendered no
            //   lease-fee control at all — even though all ten commercial props were already declared,
            //   persisted and hydrated in both components (bound in 0 create/edit blades vs 4 Hire/Bid
            //   blades). Restoring the Commercial branch is therefore MARKUP-ONLY: no new EAV keys, no
            //   renames, no migration. Option values are byte-identical to the Hire Landlord source and
            //   config/agent_preset_compensation.php — "Percentage of Month’s Rent" keeps its CURLY
            //   apostrophe (U+2019) and tenant_broker_fee_structure keeps its legacy lowercase 'Flat fee'.
            //   The two reader fixes are display-only: CompensationFormatter::tenantBrokerFee() had no
            //   branch for the Commercial options (amount silently dropped), and the accepted-bid PDF read
            //   purchase_fee_gross_rent for the Rent-Due-Each-Rental-Period option (wrong key AND label)
            //   while omitting the Gross Rent and Month's Rent rows entirely. LandlordPublishValidation and
            //   LandlordOfferListingEdit are already allow-listed above.
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/partials/landlord_broker_lease_fee.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/broker-compensation.blade.php',
            'app/Support/CompensationFormatter.php',
            'app/Services/LandlordAcceptedBidSummaryService.php',
            // Phase 1 — Batch B1.2 (Offer Safety):
            //   BLK-04 — production scheduler: withoutOverlapping() guard on the
            //     offers:expire-pending schedule (Kernel) + per-offer atomic, locked,
            //     re-checked expiry sweep in the command. (deploy/scheduler.sh +
            //     deploy/SCHEDULER.md live outside the checked production dirs.)
            //   BLK-05/BLK-06 — atomic, row-locked offer transitions with request-time
            //     expiry and competing-offer close. OfferController.php,
            //     OfferDecisionService.php and OfferCounterService.php are already
            //     allow-listed above; the new files are added here.
            'app/Console/Kernel.php',
            'app/Console/Commands/ExpireOffersCommand.php',
            'app/Services/Offers/Concerns/EnforcesRequestTimeExpiry.php',
            // Phase 1 — Batch B1.3 (Money Precision):
            //   Part 1 — convert the actively-written native float/double money
            //     columns on the bid/service auction tables to DECIMAL(15,2)
            //     (percentages to DECIMAL(5,2)) and add matching model `decimal:2`
            //     casts, so stored money is exact to the cent. One migration + the
            //     six affected bid/auction models.
            //   Part 2 — precision-safe Landlord/Tenant service-fee totals: the
            //     shared CalculatesServiceFeeTotals concern (comma/symbol
            //     normalisation + integer-cent accumulation, eliminating the
            //     truncation + float-drift defects) wired into the eight live
            //     Landlord/Tenant calculateTotals() sites. The six OfferListing/
            //     TenantAgentAuction Landlord/Tenant components are already
            //     allow-listed above; the two Hire-Landlord components and the new
            //     concern are added here.
            'database/migrations/2026_07_17_000001_convert_active_money_columns_to_decimal.php',
            'app/Models/PropertyAuctionBid.php',
            'app/Models/SellerAgentAuctionBid.php',
            'app/Models/BuyerAgentAuctionBid.php',
            'app/Models/AgentServiceAuctionBid.php',
            'app/Models/SellerServiceAuction.php',
            'app/Models/SellerServiceAuctionBid.php',
            'app/Http/Livewire/Concerns/CalculatesServiceFeeTotals.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
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
