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

    /**
     * The change set is collected by Tests\Support\ProductionScopeGuard rather than by a bare
     * `git diff` here.
     *
     * This test previously asked git one question — `git diff --name-only` — which compares the
     * WORKING TREE to the INDEX and therefore sees only unstaged changes. It missed two whole
     * categories, and both were hit for real:
     *
     *   - STAGED changes. The Milestone 2 retirement checkpoint deleted four production files with
     *     `git rm`, which stages the deletion. Index and working tree agreed, so `git diff`
     *     returned nothing and this guard evaluated none of them.
     *   - COMMITTED changes. Once a checkpoint is committed the working tree is clean, so the
     *     guard's answer decays to the empty set and it passes trivially — strongest before the
     *     work landed, useless afterwards, which is backwards.
     *
     * The guard now unions committed / staged / unstaged / untracked, detects renames on both
     * sides, and grades identically whether a checkpoint is uncommitted, half-staged or committed.
     * Its own behaviour is proven in ProductionScopeGuardTest against a purpose-built repository,
     * including the staged-delete case that was missed here.
     *
     * The committed range defaults to the merge base with `main` — "everything this branch changed"
     * — and can be pointed at a specific checkpoint via PROD_SCOPE_GUARD_BASE_REF.
     */
    public function test_no_production_files_were_modified(): void
    {
        $guard   = new \Tests\Support\ProductionScopeGuard(base_path());
        $baseRef = $guard->resolveBaseRef();

        $this->assertNotNull(
            $baseRef,
            'The production-scope guard could not resolve a base ref, so committed changes would go '
            . 'unexamined — the exact blind spot this guard was hardened to close. Set '
            . \Tests\Support\ProductionScopeGuard::BASE_REF_ENV . ' to an explicit base commit.'
        );

        $collected  = $guard->collect($baseRef);
        $allChanged = $collected['paths'];

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
            'resources/views/hire_buyer_agent/view.blade.php',
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
            'resources/views/hire_buyer_agent/view.blade.php',
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
            // Phase 2 — Batch B2.1B (Accepted → Cancelled administrative flow):
            //   new cancel() surface reusing the B1.2 locked transition + event log.
            //   OfferController.php, OfferDecisionService.php and OfferPermissionService.php
            //   are already allow-listed above; the remaining touched files are added here.
            'app/Services/Offers/OfferWorkflowFacade.php',
            'app/Services/Offers/OfferAvailableActionsService.php',
            'app/Notifications/Offers/OfferCancelledNotification.php',
            'routes/web.php',
            // Bidding Period UI — canonical bidding window + anonymous bid feed.
            //   The bidding deadline was computed in the Blade views from
            //   expiration_date (a LISTING expiry) or created_at (when the DRAFT was
            //   first saved), so the countdown, the server, and the bidders disagreed
            //   about when bidding closed. A new native, nullable
            //   offer_auctions.bidding_started_at is stamped exactly once at publish
            //   and is now the only anchor: deadline = bidding_started_at +
            //   auction_time. The migration performs NO backfill — pre-existing rows
            //   stay NULL and take a documented, temporary legacy fallback inside
            //   BiddingWindowService.
            //   BiddingWindow/BiddingWindowService own every deadline decision (timer,
            //   submit guard, draft-creation guard, freeze); PublicOfferFeedService
            //   owns who may see bids and which fields they may see (strict per-role
            //   allow-lists — guests are never served bid data, hidden or otherwise).
            //   ListingOfferAuctionLinker gives publishing and the backfill command one
            //   listing↔OfferAuction creation path so they cannot produce differently-
            //   shaped rows; the Landlord public GET no longer creates anything, and the
            //   Seller components keep their own existing linking implementation.
            //   OfferController.php, OfferPermissionService.php, the four Seller/
            //   Landlord create+edit components, LandlordOfferListingController.php and
            //   the two public view blades are already allow-listed above.
            'app/Http/Controllers/SellerOfferListingController.php',
            'app/Http/Livewire/OfferListing/Concerns/StampsBiddingActivation.php',
            'app/Models/OfferAuction.php',
            'app/Services/Offers/BiddingWindow.php',
            'app/Services/Offers/BiddingWindowService.php',
            'app/Services/Offers/ListingOfferAuctionLinker.php',
            'app/Services/Offers/OfferSubmissionService.php',
            'app/Services/Offers/PublicOfferFeedService.php',
            'database/migrations/2026_07_27_000001_add_bidding_started_at_to_offer_auctions_table.php',
            //   Stage 0 amendment (Owner-Approved Decision A, 2026-07-27): the
            //   single-stamp architecture above was REJECTED. The deadline must be
            //   STORED, not recomputed, so bidding_started_at is renamed to
            //   bidding_starts_at and joined by a stored bidding_ends_at, written
            //   together at activation. Every legacy fallback — expiration_date and
            //   created_at alike — is deleted outright rather than deprecated: a
            //   listing with no stored window is UNINITIALIZED, renders no countdown
            //   and blocks no bidder. See TIMED_OFFER_RUNTIME_INVESTIGATION.md
            //   (deviation D-13) and CanonicalBiddingWindowTest.
            'database/migrations/2026_07_27_000002_replace_bidding_started_at_with_canonical_window.php',
            //   Stage 0 repository sweep: the canonical window is now the ONLY
            //   deadline source on every offer-listing surface, for all four roles.
            //   Seller/Landlord search cards and the ending_soon ordering read the
            //   stored bidding_ends_at through linked_offer_auction_id. Buyer and
            //   Tenant criteria listings have no OfferAuction linkage, so their
            //   countdowns, "Bidding Ends" sidebars and deadline-based ordering are
            //   REMOVED rather than synthesized — per owner direction, a role that
            //   cannot resolve a canonical deadline shows none at all.
            'app/Http/Controllers/BuyerOfferListingController.php',
            'app/Http/Controllers/TenantOfferListingController.php',
            'resources/views/offer-listing/buyer/search.blade.php',
            'resources/views/offer-listing/landlord/search.blade.php',
            'resources/views/offer-listing/seller/search.blade.php',
            'resources/views/offer-listing/tenant/search.blade.php',
            'resources/views/offer-listing/partials/_competing-bids.blade.php',
            // Permanent submitted-bid history (2026-07-29): a validly submitted bid
            // never disappears from bidding history, so the feed admits every
            // non-draft status. Visibility is not actionability — finalized bids
            // stay in FINAL_STATUSES and remain non-actionable. OfferTermPresenter
            // is the single shared formatter for the feed's term cells; it
            // delegates all number formatting to ListingDisplayHelper and owns the
            // documented $/% unit-pairing conventions.
            'app/Presenters/OfferTermPresenter.php',
            //   Post-audit correction: the Landlord public view no longer creates
            //   the listing<->OfferAuction link as a side effect of an
            //   unauthenticated GET. Publishing now establishes the link for every
            //   listing type, and this command backfills listings that went live
            //   before that existed (now covering Landlord as well as Seller,
            //   with --role and --dry-run).
            'app/Console/Commands/BackfillLinkedOfferAuction.php',
            // Create Offer Listing — submit-button publish gate.
            // All four Seller/Landlord views disabled #save-button whenever ANY DOM
            // [required] field was empty, and `#save-button.disabled` set
            // `pointer-events: none`, so the click was swallowed before wire:submit
            // could fire — Submit did nothing and no Livewire request was sent. The
            // legacy gates are removed; completeness is decided on click against
            // publishRequiredFieldNames(), and the two edit components gain the same
            // guided-correction contract create already had.
            //
            // GuidesPublishValidation is the gate's required-field source. It is the
            // ONLY file taken from 50ad98ee2 — that commit's address-validation work
            // (ValidStreetAddress, AddressShapeValidator, ZipCodeLookupService,
            // ValidatesPropertyAddress and the publish-rule edits) is deliberately
            // NOT on this branch, so the gate scopes to main's current publish rules.
            // See tests/Feature/Offers/PublishSubmitGateTest.php.
            'app/Http/Livewire/OfferListing/Concerns/GuidesPublishValidation.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
            'resources/views/partials/offer-listing/publish-submit-gate.blade.php',
            'tests/Feature/Offers/PublishSubmitGateTest.php',
            // Pending-banner copy fix: the offer-detail banner called every pending
            // offer a counteroffer. Offer::isCounterOffer() makes parent_offer_id the
            // single discriminator; show.blade.php (already allow-listed above) picks
            // the copy from it. See tests/Feature/Offers/PendingOfferBannerTest.php.
            'app/Models/Offer.php',
            // Hire Agent Listing Framework — Milestone 1 (structural only).
            //   The Buyer Hire detail view was the sole naming outlier: three of four
            //   roles live at resources/views/hire_<role>_agent/view.blade.php, while
            //   Buyer sat at the view root as buyerAgentAuctionDetail.blade.php. It was
            //   relocated with `git mv` to resources/views/hire_buyer_agent/view.blade.php
            //   (contents untouched, 0 insertions / 0 deletions), so this controller's
            //   view() call changed from 'buyerAgentAuctionDetail' to 'hire_buyer_agent.view'.
            //   The relocated view's own allow-list entries are updated in place above.
            //   No behaviour, markup, copy, routing or data change.
            //   See docs/investigations/hire-agent-listing-framework-implementation-plan.md.
            'app/Http/Controllers/BuyerAgentAuctionController.php',
            //   The VACATED path of that `git mv`. It was never allow-listed before, because the
            //   old `git diff --name-only` collection reported only a rename's destination — the
            //   source silently disappeared from the guard's view. The hardened guard reports both
            //   ends of a move (a rename is two production-path changes, and allow-listing only
            //   the destination would let a protected file be moved out from under the guard), so
            //   this entry is now required. It is a genuine pre-existing gap surfaced by the
            //   hardening, not a new change: nothing edited this path in this checkpoint.
            'resources/views/buyerAgentAuctionDetail.blade.php',
            // Hire Agent Listing Framework — Milestone 2, first checkpoint
            // (competing-agent proposal privacy).
            //   New central access service: the authoritative decision on who may see which
            //   Hire Agent proposal. Owner reviews all; a submitting agent sees only their own;
            //   competing-proposal access defaults to deny; no administrator path is added.
            //   The four Hire controllers narrow the loaded bid relation through it BEFORE the
            //   view runs, so no competing data is returned and then hidden in Blade — which is
            //   what the four detail views previously did.
            //   The four detail views lose the competing-proposal surfaces: the "Agent N was the
            //   last bidder" line (also mislabelled — it named the minimum brokerage bid), the
            //   "submit your bid to view competing bids" prompt, the Bidding Period competing-bid
            //   banner and inline CompetingBidsService block, the Buyer limited-bid modal, and
            //   the per-card COMPETITOR SUMMARY branch. The owner-only empty state is kept.
            //   BuyerAgentAuctionController.php and the four hire_*_agent/view.blade.php files
            //   are already allow-listed above.
            //   Deliberately NOT touched at that checkpoint, pending the separate deletion
            //   checkpoint: CompetingBidsController, CompetingBidsService, the two
            //   competing-bids routes, tenant_agent/competing_bids.blade.php,
            //   BiddingPeriodAgentMapping and its table, and Create Offer's
            //   offer-listing/partials/_competing-bids.blade.php.
            //   See docs/investigations/hire-agent-listing-framework-implementation-plan.md §2.
            'app/Services/HireAgent/HireAgentProposalAccess.php',
            'app/Http/Controllers/SellerAgentAuctionController.php',
            'app/Http/Controllers/LandlordAgentAuctionController.php',
            'app/Http/Controllers/TenantAgentAuctionController.php',
            // Hire Agent Listing Framework — Milestone 2, second checkpoint
            // (retirement of the legacy competing-bids surfaces).
            //   That deletion checkpoint is this one. The four files below are DELETED, not
            //   modified. Proposal privacy is now decided solely by HireAgentProposalAccess, so
            //   the legacy stack had no remaining caller: a full dependency inventory found the
            //   controller reachable only from its own two routes, the service only from that
            //   controller, the dedicated view only from that controller, and the model only
            //   from that service. Every other hit was documentation or the first checkpoint's
            //   own survival assertions.
            //   The two routes are removed from the already-allow-listed routes/web.php and the
            //   URLs are left to 404 rather than redirected — a redirect to another proposal
            //   surface would itself be a disclosure. See HireAgentCompetingBidsRetirementTest.
            //   NOT removed, deliberately: the bidding_period_agent_mappings TABLE and its
            //   migration (schema changes are out of scope for this checkpoint — only the
            //   Eloquent model is gone), the four *BidMatchScoreHelper classes and their
            //   `broker_comp_*` / `services_*` aliases (a stale comment in
            //   TenantBidMatchScoreHelper still names CompetingBidsService, but the helpers are
            //   protected and the aliases have many live consumers across the Hire and Buyer
            //   Criteria views), and Create Offer's competing-bids feature in full.
            'app/Http/Controllers/CompetingBidsController.php',
            'app/Services/CompetingBidsService.php',
            'app/Models/BiddingPeriodAgentMapping.php',
            'resources/views/tenant_agent/competing_bids.blade.php',
            // Hire Agent Listing Framework — Milestone 3
            // (retirement of the legacy listing countdown).
            //   The Hire Agent bidding timer is removed. It was wired to the listing expiration
            //   date in BOTH directions, which is what made it more than cosmetic: a Bidding
            //   Period listing SYNTHESISED its expiry from created_at + auction_time and fed that
            //   into $isExpired (so an elapsed countdown, not listing status, gated the Bid
            //   button, and onTimerEnd faded the button out client-side), while submitting a bid
            //   pushed expiration_date forward a day (so the owner's deadline moved with bidding
            //   activity). expiration_date is now the sole expiry source and is owner input only.
            //
            //   The four hire_*_agent/view.blade.php files and the four detail controllers are
            //   already allow-listed above. Newly touched, each for one reason:
            //     search views      — the live "3d 04:12:07" countdown badge on result cards and
            //                         the entire @push('scripts') block that drove it. Buyer has
            //                         no search view, hence three not four.
            //     bid_detail /      — $isBiddingPeriodListing was assigned and never read;
            //     view-bid            removed with the bidding period.
            //     bid_action_row    — dropped its $isTraditionalListing parameter, which existed
            //                         only to spare Bidding Period listings the expiry notice.
            //     Controller.php    — autoTransitionBpToPending() deleted from the base
            //                         controller. It once flipped a listing to Pending when the
            //                         countdown elapsed (timer completion mutating status); it
            //                         was already a neutralised no-op and its only four callers
            //                         were the Hire Agent detail controllers.
            //     TenantAgentAuctionBid (Livewire + view)
            //                       — the bid wizard's proposal guard read a non-existent
            //                         end_date/end_time pair; it is now status-based
            //                         (expiration_date / sold / Pending / Hired Agent). The
            //                         "Public Bid Notice" bidding-period label is removed — it
            //                         also stopped being true at Milestone 2.
            //     TenantAgentAuction — isBiddingPeriodType() / isBiddingPeriodActive() deleted
            //                         after losing their last callers. auction_ended and its use
            //                         in getStatusAttribute() are KEPT: that flag is set by the
            //                         owner ending the listing, not by a clock.
            //   No schema, no migration, no Create Offer path is touched.
            //   See HireAgentTimerRetirementTest and HireAgentTimerExpirationIsolationTest.
            'resources/views/hire_seller_agent/search.blade.php',
            'resources/views/hire_landlord_agent/search.blade.php',
            'resources/views/hire_tenant_agent/search.blade.php',
            'resources/views/hire_seller_agent/bid_detail.blade.php',
            'resources/views/hire_buyer_agent/bid_detail.blade.php',
            'resources/views/hire_landlord_agent/view-bid.blade.php',
            'resources/views/hire_landlord_agent/partials/bid_action_row.blade.php',
            'resources/views/livewire/tenant/tenant-agent-auction-bid.blade.php',
            'app/Http/Controllers/Controller.php',
            'app/Http/Livewire/Tenant/TenantAgentAuctionBid.php',
            'app/Models/TenantAgentAuction.php',
            // Hire Agent Listing Framework — Milestone 4
            // (shared listing-detail framework).
            //   Six NEW Hire Agent-owned files, plus the four detail views that adopt them. No
            //   Create Offer path is touched, and the framework cannot reach Create Offer: it
            //   declares its own .hla- CSS namespace, references no offer-listing view, and is
            //   asserted disjoint from Create Offer's .sol- namespace by
            //   HireAgentDetailFrameworkTest.
            //
            //   styles.blade.php — the thirty CSS rules that were BYTE-IDENTICAL in all four
            //     detail views, chosen by rule-level intersection so relocating them cannot
            //     change what renders, plus the new .hla-hero rules and mobile stacking. Rules
            //     that merely looked shared (Buyer's yellow .btn-counter, !important and comment
            //     differences) stay in each view's residual block, on purpose.
            //   hero.blade.php — the shared hero. There was none before; the title sat at the top
            //     of the right column. Renders no countdown, no remaining time and no competing-
            //     proposal data by construction.
            //   info-card / field / flash — the card shell, the 340-times-repeated label/value
            //     row, and the identical session-flash block, extracted verbatim.
            //   HireAgentHeroData — the hero's role-specific data contract. Pure, reads only
            //     already-loaded $auction->get meta, adds no query, computes no figure, and
            //     suppresses the retired "Bidding Period" listing-type label so a legacy row
            //     cannot reintroduce that vocabulary through new markup.
            //
            //   No controller, route, model, migration or schema change was needed: the views
            //   already had every value the hero shows.
            'resources/views/hire_agent/framework/styles.blade.php',
            'resources/views/components/hire-agent/hero.blade.php',
            'resources/views/components/hire-agent/info-card.blade.php',
            'resources/views/components/hire-agent/field.blade.php',
            'resources/views/components/hire-agent/flash.blade.php',
            'app/Support/HireAgent/HireAgentHeroData.php',
            // Hire Agent Listing Framework — Milestone 5A.3 (shared detail shell).
            //   The shell the four detail views had each been carrying inline: framework styles,
            //   flash, hero, listing container, the Bootstrap row and both column wrappers. All
            //   four views now name it instead, so each loses its own copy of those seven
            //   wrappers — that is the whole of the change to them.
            //   It owns page structure only: no authorization, no user id, no route resolution,
            //   no role branching. $role reaches the hero for label selection and a test marker;
            //   $auction reaches the hero for its display fields. The sidebar BODY stays with
            //   each role view — extracting that is Milestone 5B.
            //   Buyer additionally uses the afterGrid slot to keep its share block below the
            //   grid, the position 749ace982 established.
            //   This entry was missing when the component was committed on its own in 5c355846b;
            //   the guard caught it here, which is what it is for.
            'resources/views/components/hire-agent/detail-shell.blade.php',
            // Milestone 1 — the shared VIHO design-token foundation. One neutral stylesheet of
            //   CSS custom properties, extracted verbatim from the byte-identical :root block the
            //   four Create Offer views each already carry, plus the radius/shadow/spacing/
            //   typography values that occur in all four as literals.
            //   Additive and inert: nothing includes it, and no page reads a --viho token
            //   anywhere, so it cannot alter rendering. Adoption is M3 (Hire Agent) and M8
            //   (Create Offer). This is the ONLY production path M1 touches.
            'resources/views/viho/styles.blade.php',
            // Milestone 2 — the eight neutral VIHO presentation primitives, plus the component CSS
            //   they need appended to the M1 stylesheet above. Listed individually rather than as a
            //   directory wildcard: the point of the entry is that adding a NINTH component is a
            //   decision someone has to make explicitly, and a wildcard would wave the deferred
            //   composed components (hero, gallery, detail shell, quick actions…) straight through.
            //   Additive and inert: nothing renders any of them, and neither product reads a
            //   --viho token. Adoption is M3 (Hire Agent) and M8 (Create Offer).
            'resources/views/components/viho/action-tile.blade.php',
            'resources/views/components/viho/badge.blade.php',
            'resources/views/components/viho/button.blade.php',
            'resources/views/components/viho/card.blade.php',
            'resources/views/components/viho/empty-state.blade.php',
            'resources/views/components/viho/kv.blade.php',
            'resources/views/components/viho/section-header.blade.php',
            'resources/views/components/viho/stat.blade.php',
            // Milestone 4 — the Hire Agent hero redesign, landlord pilot. Two production paths:
            //   the feature-flag config that gates the redesign (nothing reads these keys except
            //   HireAgentHeroData::redesignEnabledFor(), and both the master switch and the role
            //   allowlist default to off/landlord), and the neutral VIHO hero primitive — the
            //   ninth component, promoted from the deferred composed list that the M2 entry above
            //   deliberately refused to wave through with a wildcard. Promoting it was an explicit
            //   decision, so it earns an explicit entry here.
            'config/hire_agent_hero.php',
            'resources/views/components/viho/hero.blade.php',
            // M5.0 — the detail-page redesign flag. Two production paths and no behaviour: a
            //   config file holding one default-false key, and the single class permitted to read
            //   it. Nothing consumes the flag yet; the first consumer arrives with the markup it
            //   gates. Kept separate from config/hire_agent_hero.php above on purpose — the hero
            //   flag is enabled in a live environment while this rebuild is still being written,
            //   so the two rollouts must be able to move independently.
            'config/hire_agent_detail.php',
            'app/Support/HireAgent/HireAgentDetailRedesign.php',
            // M5.2 — the VIHO section navigation primitive. The TENTH component, and the second
            //   release from the deferred composed list after the M4 hero.
            //   Registered as one exact path, deliberately. The M2 entry above refuses a directory
            //   wildcard precisely so that a new component is a decision somebody made and wrote
            //   down, and this line is that decision for `section-nav`. A wildcard here would also
            //   wave through the composed components still deferred — gallery, quick actions,
            //   modal, sidebar — which have not been reviewed.
            //   It qualifies as a primitive in the strict sense the guard tests enforce: no
            //   script, no business logic, no authorization, escaped output, and no `id` of its
            //   own. It renders the array it is handed and marks whichever entry the caller names
            //   as current. That matters most for a nav specifically — an entry for a section the
            //   viewer cannot see would leak both the section's existence and its name, and the
            //   primitive cannot make that mistake because it cannot see the viewer. Deciding
            //   which entries exist stays with the product.
            //   Inert until a caller passes it items. The only caller is the landlord detail view,
            //   and that call sits behind HIRE_AGENT_DETAIL_REDESIGN_ENABLED, which defaults false.
            'resources/views/components/viho/section-nav.blade.php',
            // M5.3 — the VIHO quick actions band. The eleventh component and the third release
            //   from the deferred composed list. One exact path again, for the reason the M2 entry
            //   gives: each component is a decision somebody made and wrote down.
            //   NOT `interaction-hub`, which stays deferred. Create Offer's hub bundles actions
            //   with activity counts and listing facts; this is only the action band, and the
            //   bundled data contract has not been mapped.
            //   It is a container: tiles arrive through its slot as x-viho.action-tile children,
            //   already built and ordered by the caller, so it holds no script, no business logic,
            //   no authorization and no route names. That matters most for an action band — a tile
            //   advertises that a workflow exists and what it is called, which is a disclosure
            //   even when the route behind it is protected. The component cannot make that mistake
            //   because it cannot see the viewer; the product classifies every tile as public,
            //   authenticated, agent-only or listing-owner-only, and the last two are kept out.
            //   Inert until a caller passes it tiles. The only caller is the landlord detail view,
            //   behind HIRE_AGENT_DETAIL_REDESIGN_ENABLED, which defaults false.
            'resources/views/components/viho/quick-actions.blade.php',
            // M5.5 — the landlord proposal card, extracted.
            //   NOT a new component and NOT a new shared surface. This is 1,288 lines that were
            //   already inside hire_landlord_agent/view.blade.php — a path this guard has covered
            //   since M3 — moved into a partial of that same role view. Verified as a faithful
            //   move: with the redesign flag off, the rendered page is identical for the owner, a
            //   submitting agent, an unrelated user and a guest.
            //   It is registered as its own line rather than covered by a directory wildcard for
            //   the reason the M2 entry gives: the next partial someone adds under this role
            //   should be a decision somebody made and wrote down. It is also why no VIHO
            //   component is registered here — M5.5 released none. The card's chrome reuses
            //   badge and empty-state, both already on this list since M2.
            'resources/views/hire_landlord_agent/partials/proposal_card.blade.php',
            // M6 — listing document delivery hardening. A SECURITY change, and the only reason a
            //   shared partial appears on this list.
            //   The partial linked the listing document with a raw public storage URL — the last
            //   entry in BladePublicMediaSeamTest::DEFERRED, deferred out of R2-E0b precisely
            //   because replacing it is an authorization change. It now points at
            //   route('listing.document.show', …) and renders the control only when
            //   ListingDocumentAccessService::canViewDownload() allows it. No rule is
            //   reimplemented here; the service is asked.
            //   It is shared by the landlord and seller Hire Agent detail views and by nothing
            //   else, which is why seller is in scope: fixing landlord alone would leave seller on
            //   the public URL. Buyer and tenant do not include it and are untouched.
            //   Registered as one exact path rather than a partials/ wildcard, for the reason the
            //   M2 entry gives — the next shared partial to change should be a decision somebody
            //   made and wrote down.
            'resources/views/partials/listing-photos-tours-documents.blade.php',
            // M7.2 — the reusable detail section framework. Two shared Blade components, and NO
            //   new VIHO primitive: both compose card and section-header, which have been on this
            //   list since M2.
            //   They exist because the detail page rendered ONE card spanning nine sub-sections
            //   with zero-height <span> anchors buried inside it, so a section-nav link landed
            //   mid-document rather than on a card header. The reference page (Offer Listing)
            //   renders discrete cards each carrying its own id. Decomposing to match needs a
            //   wrapper that disappears when the redesign is on and a section that becomes a card
            //   — neither of which can be expressed as a conditional around a tag pair 1,700 lines
            //   apart without leaving unbalanced markup in separate @if branches.
            //   These are the SECOND and THIRD shared Hire Agent files permitted to compose VIHO,
            //   after the M4 hero. That expansion was reviewed and approved for M7.2 and is
            //   recorded in VihoPresentationPrimitivesTest::APPROVED_SHARED_CONSUMERS, which stays
            //   an explicit named list — the detail shell, field, flash and info-card in the same
            //   directory remain banned, and a test proves the ban still bites for them.
            //   Neither component reads config, resolves a route, or reads a user. Each receives
            //   the resolved flag as a plain boolean from its caller, so there is still exactly one
            //   reader of HIRE_AGENT_DETAIL_REDESIGN_ENABLED, and each sits INSIDE the caller's
            //   guards — Auth::check() and $hasLandlordBrokerCompData for compensation — never
            //   around them, so neither can be the reason a section is visible.
            //   Inert until a caller renders them. The only caller is the landlord detail view,
            //   behind HIRE_AGENT_DETAIL_REDESIGN_ENABLED, which defaults false. With the flag off
            //   the rendered DOM is identical to the pre-change page for all four roles — verified
            //   against the tree at 4d9c74961, see
            //   docs/investigations/hire-agent-m7-2-flag-off-guarantee.md.
            //   Two exact paths rather than a components/hire-agent/ wildcard, for the reason the
            //   M2 entry gives.
            'resources/views/components/hire-agent/detail-body.blade.php',
            'resources/views/components/hire-agent/detail-section.blade.php',
            // M7.4 — ListingDisplayHelper::anyHasValue(), and the only reason a shared helper
            //   appears on this list.
            //   PURELY ADDITIVE: one new static method. No existing method was touched, so every
            //   current caller of hasValue(), fmtMoney() and the rest is byte-identical to the
            //   tree before it. That is what makes a shared helper safe to widen here — the risk
            //   a helper normally carries is that a behaviour change reaches consumers nobody
            //   enumerated, and an addition has no such reach.
            //   It exists because a section card must not render when every field inside it is
            //   blank, and that decision has to be made BEFORE the card opens. The card component
            //   cannot make it by its own contract — it "cannot hide a section and must never be
            //   the reason one is visible", the same rule the M7.2 entry above records — so the
            //   caller decides, using the same values it is about to render.
            //   It belongs in the helper rather than the view because hasValue() already owns
            //   what "blank" means, including the placeholder strings. A view looping the fields
            //   itself would be a second opinion about emptiness, and the first one is right.
            //   Inert until called, and with the flag off nothing calls it: all four call sites
            //   are the landlord detail view's section guards, and every one sits INSIDE that
            //   file's `if ($hlaDetailRedesign)` branch, behind
            //   HIRE_AGENT_DETAIL_REDESIGN_ENABLED, which defaults false.
            'app/Helpers/ListingDisplayHelper.php',
            // Tenant offer-listing ZIP resolution — a READ-PATH fix, no writer touched.
            //
            // ZIPs for this workflow live in two stores and nothing mirrors between them: the
            // Search Areas map writes `location_dna_preferences.zip_codes`, while the legacy
            // `zipCodes` meta is written by 11 components from a discrete property.
            // `HasSearchAreas::saveSearchAreas()` mirrors state / counties / cities and stops
            // short of ZIPs. This loader read only the legacy key, so a tenant who set ZIPs
            // through the map got an empty `preferred_zip_codes` and matched on everything.
            //
            // It now reads ZIPs from `location_dna_preferences.zip_codes` whenever the blob
            // carries that key, and falls back to legacy `zipCodes` only when the blob cannot
            // speak — absent, unparseable, or predating the key. Those are the older listings
            // the discrete input wrote before the widget existed; they keep working untouched.
            //
            // The presence of the KEY, not the emptiness of its value, transfers authority. An
            // empty `zip_codes: []` deliberately clears legacy ZIPs rather than falling back to
            // them: the map is this workflow's only ZIP editing surface, so an empty array is a
            // user who cleared their selection, and honouring it is what makes "Clear All" work
            // instead of resurrecting values the user has no way to remove.
            //
            // This is intentionally NARROWER than LocationMatchAuctionExtractor, which unions
            // the same two sources and continues to do so — unchanged by this commit. The
            // divergence is deliberate: the extractor feeds proximity scoring, where a superset
            // of ZIPs only widens a search, while this loader feeds a query filter, where a
            // stale ZIP silently re-adds inventory the user removed.
            //
            // NO WRITER CHANGED, deliberately. A write-path mirror would have touched 11
            // components and permanently altered stored meta; the two Hire Tenant components
            // even order their inline `zipCodes` write and `saveSearchAreas()` call oppositely,
            // so a trait-level mirror would resolve differently on create than on edit. A read
            // fix cannot corrupt stored data and is reversible by revert alone.
            'app/Services/Stellar/TenantOfferListingCriteriaLoader.php',
            // Phase 0 (Spatial UI Integration) — street-address shape validation
            // across all Seller/Landlord surfaces. The other six components this
            // phase touches are already allow-listed above; these are the ones it
            // reaches that no previous task did.
            'app/Http/Livewire/Concerns/ValidatesPropertyAddress.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
            //   The two services the rule and trait are built on. Both are new files and
            //   neither was registered when they were introduced — an omission in the
            //   original slice's bookkeeping, corrected here rather than carried forward.
            //   AddressShapeValidator is pure and DB-free; ZipCodeLookupService reads the
            //   `us_zip_codes` gazetteer we already own. Neither makes an outbound call.
            'app/Services/Location/AddressShapeValidator.php',
            'app/Services/Location/ZipCodeLookupService.php',
            // Phase 0 presentation layer — surfacing the address error on the field
            // at fault, the ZIP-moved notice, and honest map-unavailable messaging.
            // The two seller/landlord tab partials this phase also touches are
            // already allow-listed above.
            'resources/views/components/address-assist-notice.blade.php',
            'resources/views/components/byo-address-autocomplete.blade.php',
            'resources/views/components/google-maps-auth-telemetry.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php',
            // Phase 1 (Shared Components) — the four listing blades drop their
            // duplicated Google Places listener in favour of the shared
            // <x-byo-address-autocomplete>, invoked script-only from the two
            // property-preference partials. Every other file this commit touches is
            // already allow-listed above; this is the only one no previous task
            // reached.
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
            // Phase 1 (Shared Components) — provider-neutral rename of the shared
            // fill method (fillFromGooglePlaces -> fillFromResolvedAddress) so the
            // D1 geocoder swap becomes a one-file change. Naming only: no signature,
            // behaviour or payload handling changed. The trait carries the method;
            // ValidStreetAddress references it in a comment.
            'app/Http/Livewire/Concerns/HandlesResolvedPropertyAddress.php',
            'app/Rules/ValidStreetAddress.php',
            // Provider-neutral trait rename: HandlesGooglePlacesAddress ->
            // HandlesResolvedPropertyAddress. Both halves of the rename are listed —
            // the new path above, and the removed path here, which the guard still
            // sees as a deletion in the diff window. Naming only; the trait's method,
            // signature and behaviour are untouched, and no provider is selected,
            // called, or added by it.
            'app/Http/Livewire/Concerns/HandlesGooglePlacesAddress.php',
            // Seller/Landlord Hire Agent ownership hardening — a SECURITY change (S1/S2).
            //
            // Both Hire Agent wizards resolved the row to write with an unscoped
            // `Model::find($this->listingId)` and then assigned `user_id = Auth::id()`.
            // `listingId` is a public Livewire property, so any authenticated user could
            // point it at another user's listing and both overwrite it AND transfer
            // ownership of the row to themselves. The same unscoped-find-then-reassign
            // shape sat in the two legacy update endpoints.
            //
            // NO new path is added by this entry. All four files this change touches are
            // already on this list: SellerAgentAuction.php (Phase 0 address-validation
            // entry above — the two tasks reached the same file by different routes, and
            // one allowlist entry covers both), LandLordAgentAuction.php (money-precision
            // entry above), and SellerAgentAuctionController.php /
            // LandlordAgentAuctionController.php (Hire Agent Listing Framework M2 entry
            // above). This comment records the security rationale for the seller
            // component so the entry is not read as address-only bookkeeping.
            //
            // No new mechanism was invented: all four files use the existing
            // ResolvesOwnedAuction concern that the four Offer Listing components already
            // use, with the same owner-only rule and the same 403.
            //
            // Exact paths, not a HireSellerAgent/ wildcard, for the reason the M2 entry
            // gives — the next file under this role should be a decision somebody made and
            // wrote down.

            // Create Offer Fair Housing — Phase 1 (P0 launch blockers only).
            //
            // Five retirements, each removing a way for a protected-class-adjacent value to
            // be written, published, exported or fed to the LLM. The Livewire components,
            // the two public detail views and AskAiContextBuilderService are already listed
            // above; these four are the additions this task reaches.
            //
            //   LandlordFieldMap.php
            //     Drops the 'Service Animal' / 'Support Animal' export columns (P0-A) and
            //     the 'Occupant Types' / 'Occupant Types (Tenant)' columns (P0-E). Removing
            //     the reader is what makes an already-stored value inert ahead of any data
            //     remediation, which this phase deliberately does not perform.
            //
            //   BuyerCriteriaAuctionController.php
            //     Removes 'com_tenant_type' from the accepted-key list on BOTH storeAuction()
            //     and updateAuction() (P0-C). The blob is built by iterating that list, so a
            //     key absent from it has no path in even if the field is hand-posted.
            //
            //   buyer_criteria/add.blade.php, buyer_criteria/edit.blade.php
            //     Delete the "What type of tenants are preferred?" textarea (P0-C). The
            //     objective commercial questions it sat beside — com_property_use and
            //     com_zoning — are untouched, and no replacement occupant question is added.
            'app/Exports/ListingFieldMaps/LandlordFieldMap.php',
            'app/Http/Controllers/BuyerCriteriaAuctionController.php',
            'resources/views/buyer_criteria/add.blade.php',
            'resources/views/buyer_criteria/edit.blade.php',

            // Create Offer Fair Housing — Phase 1, P0-B completion.
            //
            // The pre-PR audit found that gating AskAiContextBuilderService::buildFaqAnswers()
            // closed Ask AI V1 only. A second, independent route to a prompt bypassed it:
            // AskAiFaqEnrichmentService::sync() wrote ANY key into ai_faq_answers using the
            // same `?? [nulls]` umbrella, and AgentAi\Loaders\ExtendedKnowledgeLoader read
            // that table verbatim into Agent AI V2 context. Both ends now enforce the same
            // config-SSOT membership check as the V1 gate.
            //
            // The reader guard is required in addition to the writer guard, not instead of
            // it: rows written before the guard existed remain, and retiring a question from
            // config leaves rows behind by definition.
            'app/Services/AskAi/AskAiFaqEnrichmentService.php',
            'app/Services/AgentAi/Loaders/ExtendedKnowledgeLoader.php',

            // Create Offer Fair Housing — Phase 2 (landlord applicant screening).
            //
            // Phase 2 retires the landlord employment gate (employment_requirement,
            // custom_employment_requirement, employment_verification_requirement), removes
            // the blanket lifetime "No criminal background" option, and renames the
            // standardless "Case-by-case review" / "Compensating factors considered"
            // options. The two Livewire components, AskAiContextBuilderService,
            // AskAiRunnerV2Service and the landlord public view are already listed above;
            // these are the files this task adds.
            //
            //   config/landlord_screening_options.php
            //     The option SSOT. It exists because the audit found NO validation rule for
            //     any screening key: they are public Livewire properties that saveMeta()
            //     wrote verbatim, so deleting an <option> changed the form and nothing else.
            //     Read by exactly two things — the form that renders and the policy that
            //     enforces — so the two cannot drift.
            //
            //   app/Support/OfferListing/LandlordScreeningPolicy.php
            //     The write boundary. An INTERSECTION against that SSOT: a value is stored
            //     because it is named there, never because it escaped a deny-list. Also the
            //     single resolver for stale values, which is why suppression (the blanket
            //     criminal ban) and normalization (the renamed options) cannot diverge
            //     between surfaces.
            //
            //   applicant-requirements.blade.php
            //     The one partial both the Create and the Edit wizard include, so the option
            //     lists cannot drift between them.
            //
            //   qualification/check.blade.php, qualification/review.blade.php
            //     The two other places the landlord's screening policy is published. check
            //     shows applicants what is required (a route with no auth middleware);
            //     review is the landlord's applicant scorecard, which not only displayed the
            //     retired employment requirement but SCORED applicants against it. Both now
            //     resolve through the policy, and review computes no verdict from criminal
            //     history at all — a page comparing two dropdown strings cannot conduct the
            //     individualized review the current option names.
            'config/landlord_screening_options.php',
            'app/Support/OfferListing/LandlordScreeningPolicy.php',
            'app/Services/AskAi/AskAiFieldQuestionRegistryService.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/applicant-requirements.blade.php',
            'resources/views/offer-listing/landlord/qualification/check.blade.php',
            'resources/views/offer-listing/landlord/qualification/review.blade.php',
        ];

        $unexpected = $guard->unexpected($collected['entries'], $taskAllowlist);

        $this->assertEmpty(
            $unexpected,
            'Production files were modified or created outside the task allowlist: '
            . implode(', ', $unexpected)
            . "\n\nFull change set for {$baseRef}...HEAD (path [status/origin]):\n  "
            . implode("\n  ", $guard->describe($collected['entries'], $allChanged)),
        );
    }
}
