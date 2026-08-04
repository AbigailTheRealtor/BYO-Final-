{{--
    Shared Landlord proposal card — one authorized proposal on a Hire Agent listing.

    M5.5. Extracted from hire_landlord_agent/view.blade.php, where it was 1,288 lines inlined in
    the console's @foreach. The move is MECHANICAL: the markup below is the markup that was there,
    so both flag treatments render the same bytes they did before. The redesign work in this
    milestone is the card CHROME (status badge) and the console guard in the parent — not this
    body.

    IT MAKES NO AUTHORIZATION DECISION, and must not start.
    ------------------------------------------------------
    Visibility is decided twice before this file is reached, and neither decision lives here:

      1. HireAgentProposalAccess::restrictLoadedProposals() narrows $auction->bids in
         LandlordAgentAuctionController::view(), server-side, before the view runs. A proposal this
         viewer may not see is not in the collection being looped.
      2. The parent loop re-checks `$isListingOwner || $isBidOwner` and `continue`s past anything
         that fails — defence in depth, and the reason the guard could not move in here: `continue`
         cannot cross a view boundary (Blade compiles each view to its own function), and skipping
         a row from inside the partial would be strictly weaker than never including it.

    The per-block `@if ($isListingOwner || $isBidOwner)` gates inside this file are the THIRD
    layer and are preserved verbatim. HireAgentBidCtaTest::test_the_view_is_handed_only_authorized
    _proposals is the test that proves layer 1 independently of layers 2 and 3 — mutation testing
    during M5.4 showed it is the only one that fails when the controller narrowing is removed.

    COMPENSATION IS UNCHANGED. The body renders compensation under the rule the page has always
    used, reproduced here exactly. Whether that rule is right is an OPEN owner decision — see
    docs/investigations/hire-agent-compensation-visibility-decision.md — and M5.5 is explicitly
    forbidden from answering it. Do not "tidy" a compensation gate in this file.

    Expected variables (all inherited from the parent scope via @include, which passes the
    caller's defined variables — no explicit array is needed and none should be added, because a
    partial list would silently drop the outer display closures below):

        $bid            – the LandlordAgentAuctionBid being rendered
        $auction        – the LandlordAgentAuction it belongs to
        $auth_id        – auth()->id() (0/null for a guest)
        $isListingOwner – HireAgentProposalAccess::isListingOwner(), via $hlaIsListingOwner
        $isBidOwner     – set by the parent loop, immediately before the guard
        $agentNumber    – stable per-agent alias from $agentNumberMap
        $isExpired / $isSold           – listing lifecycle, from expiration_date and status
        $landlordBaselineData / $auctionPropType / $getScoreColor – match-score baseline
        $canon / $fmtMoney / $fmtPercent / $rentalPeriodSuffix / $isResidential
                        – display closures defined at the top of the parent view
--}}

@php
    /*
     | M5.5 — DERIVED PROPOSAL STATE IS COMPUTED ONCE, HERE.
     |
     | Before this milestone the same facts were re-derived five times inside one card, and the
     | copies had drifted. Consolidated, with the divergences resolved as follows — every one of
     | them verified against its readers before it was touched:
     |
     |   · $counterBids           — the identical LandlordCounterTerm query ran TWICE per card
     |                              (once here, once again beside the counter history). One query
     |                              now. Same `with`, same order, same result.
     |   · $state                 — reassigned further down to a 'countered'-aware value that
     |                              NOTHING read. Dead on arrival, and removed. The card's visible
     |                              status still resolves 'Countered' through $bidStatusLabel,
     |                              which asks $hasCounterBids separately and is unchanged.
     |   · $isOwnerRow            — assigned twice and read nowhere. Removed. The second copy was
     |                              the last surviving `data_get($auction,'user_id') == $auth_id`
     |                              in this markup, i.e. the loose test M5.4 removed everywhere
     |                              else — see the $hlaIsListingOwner note in the parent view.
     |   · $hasAcceptedCounterBid — recomputed later as ->contains('status','accepted'), which is
     |                              what the line above already establishes. One derivation.
     |   · $isAgent               — declared here as a GLOBAL role test (user_type === 'agent'),
     |                              never read, then shadowed inside the counter history by a
     |                              same-named variable meaning "authored this bid". Two different
     |                              questions sharing one name is how the shadowing survived.
     |                              The dead global copy is removed; the local one is renamed at
     |                              its use site.
     |
     | $isBidOwner and $agentNumber are NOT set here — the parent loop sets them immediately
     | before the `continue` guard, because the guard needs $isBidOwner and cannot live in this
     | file. See the header comment.
     |
     | Nothing below changes what any viewer may see. These are the same values, derived once.
     */
    $rawState = data_get($bid, 'accepted', '0');
    // 'accepted' column stores 'no' for undecided bids. Treat anything non-terminal as '0'.
    $_isTerminalCard = in_array((string)$rawState, ['accepted', 'rejected'], true);
    $state = $_isTerminalCard ? (string) $rawState : '0';

    // Get counter bids for this bid — the single query for this card.
    $counterBids = \App\Models\LandlordCounterTerm::with('meta', 'user')
        ->where('landlord_agent_auction_id', data_get($bid, 'id'))
        ->orderBy('created_at', 'desc')
        ->get();

    // Check if this bid has any accepted counter bid
    $acceptedCounterBidForThisBid = $counterBids->where('status', 'accepted')->first();
    $hasAcceptedCounterBid = $acceptedCounterBidForThisBid ? true : false;
    $bidIsAccepted = $state === 'accepted' || $hasAcceptedCounterBid;

    // Parity vars
    $hasCounterBids = $counterBids->isNotEmpty();
    $bidStatusLabel = match($state) {
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'countered' => 'Countered',
        default => $hasCounterBids ? 'Countered' : 'Active',
    };
    $bidStatusColor = match($state) {
        'accepted' => '#28a745',
        'rejected' => '#dc3545',
        'countered' => '#ffc107',
        default => $hasCounterBids ? '#ffc107' : '#1a4a6e',
    };
    $servicesList = (array) data_get($bid,'get.services',[]);
    $additionalServices = (array) data_get($bid,'get.other_services',[]);
    $totalServicesCount = count(array_filter($servicesList, fn($s) => $s !== 'Other')) + count($additionalServices);
    $bidAccepted = data_get($bid, 'accepted');
    $canEditWithdraw = $isBidOwner && !$isExpired && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected';

    // ── Resolved Landlord Broker Lease Fee display (matching Tenant's commissionFeeDisplay) ──
    $landlordFeeType = data_get($bid, 'get.purchase_fee_type', '');
    $landlordFeeDisplay = '—';
    if ($landlordFeeType === 'Flat Fee' && data_get($bid,'get.purchase_fee_flat')) {
        $landlordFeeDisplay = $fmtMoney(data_get($bid,'get.purchase_fee_flat'));
    } elseif ($landlordFeeType === 'Percentage of the Rent Due Each Rental Period' && data_get($bid,'get.purchase_fee_rental_period')) {
        $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_rental_period')) . " $rentalPeriodSuffix";
    } elseif ($landlordFeeType === 'Percentage of the Gross Lease Value' && data_get($bid,'get.purchase_fee_percentage_combo')) {
        $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_percentage_combo')) . ' of Gross Lease Value';
    } elseif ($landlordFeeType === "Percentage of the First Month's Rent" && data_get($bid,'get.purchase_fee_flat_combo')) {
        $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_flat_combo')) . " of First Month's Rent";
    } elseif ($landlordFeeType === 'Percentage of the Net Aggregate Rent' && data_get($bid,'get.purchase_fee_net_aggregate')) {
        $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_net_aggregate')) . ' of Net Aggregate Rent';
    } elseif ($landlordFeeType === 'Percentage of the Gross Rent' && data_get($bid,'get.purchase_fee_gross_rent')) {
        $landlordFeeDisplay = $fmtPercent(data_get($bid,'get.purchase_fee_gross_rent')) . ' of Gross Rent';
    } elseif ($landlordFeeType === "Percentage of Month's Rent" && data_get($bid,'get.purchase_fee_monthly_percentage')) {
        $_d = $fmtPercent(data_get($bid,'get.purchase_fee_monthly_percentage')) . " of Month's Rent";
        if (data_get($bid,'get.purchase_fee_months')) $_d .= ' x ' . data_get($bid,'get.purchase_fee_months') . ' Months';
        $landlordFeeDisplay = $_d;
    } elseif (strtolower($landlordFeeType) === 'other') {
        $landlordFeeDisplay = data_get($bid,'get.purchase_fee_other') ?? data_get($bid,'get.purchase_fee_other_commercial') ?? '—';
    } elseif ($landlordFeeType) {
        $landlordFeeDisplay = $landlordFeeType;
    }

    // ── Tenant Broker structure preview (Residential only) ──────
    $bidTenantBrokerStructure = data_get($bid,'get.tenant_broker_commission_structure','');
    $bidTenantBrokerStructureDisplay = '';
    if ($isResidential && $bidTenantBrokerStructure
        && $bidTenantBrokerStructure !== 'no_compensation'
        && $bidTenantBrokerStructure !== "No Compensation Offered to the Tenant's Broker") {
        $bidTenantBrokerStructureDisplay = $bidTenantBrokerStructure;
        // Resolve fee sub-value
        $_tbs = data_get($bid,'get.tenant_broker_fee_structure','');
        if ($_tbs === 'Percentage of the Rent Due Each Rental Period' && data_get($bid,'get.tenant_broker_percentage')) {
            $bidTenantBrokerStructureDisplay .= ' – ' . $fmtPercent(data_get($bid,'get.tenant_broker_percentage')) . ' of Rent Due Each Rental Period';
        } elseif ($_tbs === 'Percentage of the Gross Lease Value' && data_get($bid,'get.tenant_broker_gross_lease')) {
            $bidTenantBrokerStructureDisplay .= ' – ' . $fmtPercent(data_get($bid,'get.tenant_broker_gross_lease')) . ' of Gross Lease Value';
        } elseif ($_tbs === "Percentage of the First Month's Rent" && data_get($bid,'get.tenant_broker_first_month_rent')) {
            $bidTenantBrokerStructureDisplay .= ' – ' . $fmtPercent(data_get($bid,'get.tenant_broker_first_month_rent')) . " of First Month's Rent";
        } elseif ($_tbs === 'Flat Fee' && data_get($bid,'get.tenant_broker_flat_fee')) {
            $bidTenantBrokerStructureDisplay .= ' – ' . $fmtMoney(data_get($bid,'get.tenant_broker_flat_fee')) . ' Flat Fee';
        } elseif ($_tbs === 'other' && data_get($bid,'get.tenant_broker_other')) {
            $bidTenantBrokerStructureDisplay .= ' – Other: ' . data_get($bid,'get.tenant_broker_other');
        }
    }

    // ── Match Score ────────────────────────────────────────────
    $currentBidData = json_decode(json_encode(data_get($bid, 'get', [])), true) ?: [];
    // Card score ALWAYS uses original listing baseline to ensure a consistent
    // denominator across all bids on the same listing.
    $originalScore = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
        $landlordBaselineData, $currentBidData, null, $auctionPropType
    );
    // Use the most recently submitted non-terminal counter as the active baseline.
    // Exclude accepted/rejected records so stale terminal counters are never used as baseline.
    $latestActiveCounter = $counterBids->filter(fn($c) => !in_array((string)$c->status, ['accepted', 'rejected'], true))->first();
    // Detect whether any counter exists for this bid (bid-scoped).
    // This is used exclusively by the footer state machine to determine the 'countered' state.
    $latestOwnerCounter = \App\Models\LandlordCounterTerm::where('landlord_agent_auction_id', data_get($bid, 'id'))
        ->orderBy('created_at', 'desc')
        ->first();
    if ($latestActiveCounter && $latestActiveCounter->meta->count()) {
        $counterBaselineData = $latestActiveCounter->meta->pluck('meta_value', 'meta_key')->toArray();
        $latestCounterScore = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
            $counterBaselineData, $currentBidData, null, $auctionPropType
        );
        $showDualScore = true;
    } else {
        $latestCounterScore = null;
        $showDualScore = false;
    }
    // Card display always uses original listing baseline score
    $matchScore = $originalScore;
    $totalScore       = $matchScore['overall_percent'];
    $totalScoreColor  = $getScoreColor($totalScore);
    $servicesScore    = $matchScore['services_match_percent'];
    $servicesMatched  = $matchScore['services_matched_count'];
    $servicesTotal    = $matchScore['services_baseline_total'];
    $servicesMissingCount = $matchScore['services_missing_count'];
    $servicesExtraCount   = $matchScore['services_extra_count'];
    $brokerScore      = $matchScore['terms_match_percent'];
    $brokerMatched    = $matchScore['terms_matched_count'];
    $brokerTotal      = $matchScore['terms_baseline_total'];
    $brokerMismatches = $matchScore['changed_terms'];
    $termsChangedCount = $matchScore['terms_changed_count'];
    $termsAddedCount   = $matchScore['terms_added_count'];
    $baselineLabel     = "Landlord's Original Listing";
    /**
     * ZERO-BASELINE / NO-DATA GUARD
     *
     * If there is no comparable baseline match data, do not display 100%.
     * Render "No match data available" instead.
     *
     * This behavior is locked by QA baseline documentation.
     * Reference: qa_reports/QA_LOCK_BidComparison_v1.md
     */
    $hasAnyBaseline    = ($brokerTotal > 0 || $servicesTotal > 0);
@endphp

<!-- Bid Card - Collapsible with custom JS toggle -->
<div class="card mb-3" style="border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
    <div class="card-header d-flex justify-content-between align-items-center hla-bid-accordion-header"
         style="cursor: pointer; background: #fff; border-bottom: 1px solid #e0e0e0; padding: 15px 20px;"
         data-target="bidCollapse-{{ data_get($bid, 'id') }}"
         aria-expanded="false">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-chevron-down bid-chevron" style="transition: transform 0.3s; color: #1a3a5c;"></i>
            <h5 class="mb-0" style="font-weight: 700; color: #1a3a5c; font-size: 1.4rem;">Agent {{ $agentNumber }}</h5>
        </div>
        {{--
            M5.5 — the status chip is the one piece of card chrome that moves onto a VIHO
            primitive in this milestone.

            IT IS A LIKE-FOR-LIKE SWAP. x-viho.badge is explicit that it does not infer status:
            the variant is the caller's to supply, because "Accepted is green" is product
            vocabulary and a shared primitive that owned it would own the vocabulary of Create
            Offer too. So the same four states map to the same four appearances the inline hex
            values produced, named rather than spelled out. $bidStatusLabel is untouched.

            The card CONTAINER deliberately does NOT become x-viho.card. The header is a click
            target with a JS contract — `.hla-bid-accordion-header`, `data-target`,
            `aria-expanded`, `.bid-chevron` — and x-viho.card renders its own title structure,
            so adopting it here would mean rebuilding the disclosure behaviour to suit a
            primitive rather than the other way round. There is no accordion primitive and this
            page is not enough evidence to add one; the M5.2b rule about retuning shared layers
            on a single consumer applies to adding them too.
        --}}
        @if ($hlaDetailRedesign)
        @php
            $hlaBidStatusVariant = match($bidStatusLabel) {
                'Accepted'  => 'success',
                'Rejected'  => 'danger',
                'Countered' => 'warning',
                default     => 'info',
            };
        @endphp
        <x-viho.badge :variant="$hlaBidStatusVariant" :pill="true">{{ $bidStatusLabel }}</x-viho.badge>
        @else
        <span style="font-weight: 600; color: {{ $bidStatusColor }}; font-size: 1.1rem;">{{ $bidStatusLabel }}</span>
        @endif
    </div>

    <!-- Collapsible Content - Default collapsed -->
    <div class="bid-collapse-content" id="bidCollapse-{{ data_get($bid, 'id') }}" style="display: none;">
    <div class="card-body" style="padding: 20px;">

        @if($isListingOwner || $isBidOwner)
        <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">

        {{-- Counter Offer Notice Banner — visible immediately on accordion expand (owner/agent only) --}}
        @if ($latestOwnerCounter && ($isListingOwner || $isBidOwner))
        @php $latestOwnerCounterFromLandlord = ($latestOwnerCounter->user_id == data_get($auction, 'user_id')); @endphp
        <div class="alert d-flex align-items-start gap-2 mb-3 py-2 px-3"
             style="background: #fff8e1; border: 1px solid #ffc107; border-left: 4px solid #ffc107; border-radius: 6px; font-size: 0.9rem;">
            <i class="fa-solid fa-right-left mt-1" style="color: #e6a800; flex-shrink: 0;"></i>
            <div>
                @if ($isListingOwner && $latestOwnerCounterFromLandlord)
                    <strong>Counter Offer Sent.</strong>
                @elseif ($isListingOwner && !$latestOwnerCounterFromLandlord)
                    <strong>Counter Offer Received.</strong>
                @elseif ($isBidOwner && $latestOwnerCounterFromLandlord)
                    <strong>Counter Offer Received.</strong>
                @elseif ($isBidOwner && !$latestOwnerCounterFromLandlord)
                    <strong>Counter Offer Sent.</strong>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Counter action row — directly on bid card ── --}}
        @if ($latestOwnerCounter && ($isListingOwner || $isBidOwner) && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected')
        @php $bidCardViewerSentLatestLandlord = ($isListingOwner && $latestOwnerCounterFromLandlord) || ($isBidOwner && !$latestOwnerCounterFromLandlord); @endphp
        @if ($bidCardViewerSentLatestLandlord)
        {{-- WAITING: single row — View CT + Edit CT --}}
        <div class="d-flex gap-2 align-items-center mb-2">
            <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                <i class="fa-solid fa-eye me-1"></i> View Counter Terms
            </a>
            @if ($isListingOwner)
            <a href="{{ route('landlord.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
            </a>
            @else
            <a href="{{ route('landlord.agent.auction.counter-bid', ['id' => $auction->id, 'bid_id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
            </a>
            @endif
        </div>
        @else
        {{-- RESPONSE: View CT only — Accept/Counter Back/Reject are on View Counter Terms page --}}
        <div class="d-flex align-items-center mb-2">
            <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                <i class="fa-solid fa-eye me-1"></i> View Counter Terms
            </a>
        </div>
        @endif
        @endif

        <!-- Offered Services Count Row -->
        <p class="mb-0" style="font-size: 1.1rem; color: #1a3a5c;">
            <span style="font-weight: 600;">Offered Services:</span>
            <span style="color: #28a745; font-weight: 600;">{{ $servicesTotal > 0 ? $servicesMatched.'/'.$servicesTotal : 'No services requested' }}</span>{{ $servicesTotal > 0 ? ' matched' : '' }}
            @if ($servicesTotal > 0 && $servicesExtraCount > 0)
            <span class="text-muted ms-2">&bull; {{ $servicesExtraCount }} extra</span>
            @endif
            @if ($servicesTotal > 0 && $servicesMissingCount > 0)
            <span class="ms-2" style="color: #dc3545;">&bull; {{ $servicesMissingCount }} missing</span>
            @endif
        </p>
        @if ($servicesExtraCount > 0)
        <div class="mt-2 d-flex align-items-center flex-wrap" style="gap: 4px 6px;">
            <span style="font-size: 0.9rem; line-height: 1.4;">&#11088;</span>
            <span style="font-weight: 500; color: #856404; font-size: 0.95rem;" title="Extra services were included by the Agent beyond the Landlord&#39;s original request. These do not increase the match score but may provide additional value.">Extra Value Added: {{ $servicesExtraCount }} {{ $servicesExtraCount === 1 ? 'Service' : 'Services' }}</span>
            <span class="text-muted" style="font-size: 0.78rem; font-style: italic;">&mdash; does not affect match score</span>
        </div>
        @endif

        <!-- Terms Match Row -->
        @if ($hasAnyBaseline && $brokerTotal > 0)
        <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
            <span style="font-weight: 600;">Terms Match:</span>
            <span style="color: #28a745; font-weight: 600;">{{ $brokerMatched }}/{{ $brokerTotal }} matched</span>
            @if ($termsChangedCount > 0)
            <span class="ms-2" style="color: #dc3545;">&bull; {{ $termsChangedCount }} changed</span>
            @endif
            @if ($termsAddedCount > 0)
            <span class="text-muted ms-2">&bull; {{ $termsAddedCount }} added</span>
            @endif
            @php $termsMissingCount = max(0, $brokerTotal - $brokerMatched - $termsChangedCount); @endphp
            @if ($termsMissingCount > 0)
            <span class="ms-2" style="color: #dc3545;">&bull; {{ $termsMissingCount }} missing</span>
            @endif
        </p>
        <div class="mt-1" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&mdash; affects match score</div>
        @elseif ($hasAnyBaseline && $brokerTotal === 0)
        <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
            <span style="font-weight: 600;">Terms Match:</span>
            <span class="text-muted">&mdash;</span>
        </p>
        @endif

        <hr style="margin: 15px 0; border-color: #e0e0e0;">

        <!-- Match Score Summary (Compact Display on Bid Card) -->
        {{-- Milestone 2: the third disjunct — Bidding Period + any agent who had
             bid — showed a competitor's match score. Owner review and the
             bidder's own score only. --}}
        @php $showMatchScoreOnCard = $isListingOwner || $isBidOwner; @endphp
        @if ($showMatchScoreOnCard && $hasAnyBaseline)
        <div class="match-score-summary mb-3 p-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6; font-size: 0.88rem;">
            @if ($showDualScore && $originalScore && $latestCounterScore)
            {{-- DUAL SCORE: Original Match + Latest Counter Match side-by-side --}}
            <div class="mb-2">
                <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                    <i class="fa-solid fa-chart-pie me-2"></i>Match Summary
                </span>
            </div>
            <div class="row g-2 mb-2">
                {{-- Original Match --}}
                @php
                    $osColor = $getScoreColor($originalScore['overall_percent']);
                @endphp
                <div class="col-6">
                    <div class="p-2 rounded" style="background: #fff; border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                            <span class="badge" style="background: {{ $osColor }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $originalScore['overall_percent'] }}%</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #6c757d;">vs. Landlord's Original Request</div>
                        <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                            <div class="col-6" style="color: {{ $getScoreColor($originalScore['services_match_percent']) }};">Services {{ $originalScore['services_match_percent'] }}%</div>
                            <div class="col-6" style="color: {{ $getScoreColor($originalScore['terms_match_percent']) }};">Terms {{ $originalScore['terms_match_percent'] }}%</div>
                        </div>
                    </div>
                </div>
                {{-- Latest Counter Match --}}
                @php
                    $lcColor2 = $getScoreColor($latestCounterScore['overall_percent']);
                @endphp
                <div class="col-6">
                    <div class="p-2 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColor2 }};">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                            <span class="badge" style="background: {{ $lcColor2 }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $latestCounterScore['overall_percent'] }}%</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #6c757d;">vs. Your Latest Counter</div>
                        <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                            <div class="col-6" style="color: {{ $getScoreColor($latestCounterScore['services_match_percent']) }};">Services {{ $latestCounterScore['services_match_percent'] }}%</div>
                            <div class="col-6" style="color: {{ $getScoreColor($latestCounterScore['terms_match_percent']) }};">Terms {{ $latestCounterScore['terms_match_percent'] }}%</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="small" style="color: #6c757d; font-style: italic; font-size: 0.76rem;">
                <i class="fa-solid fa-circle-info me-1"></i>Added services or terms do not increase either score.
            </div>
            @else
            {{-- SINGLE SCORE fallback --}}
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                    <i class="fa-solid fa-chart-pie me-2"></i>Match Score
                </span>
                <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1rem; padding: 6px 12px; color: white;">
                    {{ $totalScore }}%
                </span>
            </div>
            <div class="row g-2 small">
                <div class="col-6">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Services Match:</span>
                        <span style="color: {{ $getScoreColor($servicesScore) }}; font-weight: 600;">{{ $servicesScore }}%</span>
                    </div>
                    <div class="text-muted" style="font-size: 0.8rem;">
                        {{ $servicesTotal > 0 ? 'Matched: '.$servicesMatched.'/'.$servicesTotal : 'No services requested' }}
                        @if ($servicesTotal > 0 && $servicesExtraCount > 0) &bull; Extra: {{ $servicesExtraCount }}@endif
                        @if ($servicesTotal > 0 && $servicesMissingCount > 0) &bull; Missing: {{ $servicesMissingCount }}@endif
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Terms Match:</span>
                        <span style="color: {{ $getScoreColor($brokerScore) }}; font-weight: 600;">{{ $brokerScore }}%</span>
                    </div>
                    <div class="text-muted" style="font-size: 0.8rem;">
                        {{ $brokerTotal > 0 ? 'Matched: '.$brokerMatched.'/'.$brokerTotal : 'No terms provided' }}
                        @if ($brokerTotal > 0 && $termsChangedCount > 0) &bull; Changed: {{ $termsChangedCount }}@endif
                        @if ($brokerTotal > 0 && $termsAddedCount > 0) &bull; Added: {{ $termsAddedCount }}@endif
                    </div>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="fa-solid fa-circle-info me-1"></i>Compared to: {{ $baselineLabel }}
            </div>
            <div class="mt-1 small" style="color: #6c757d; font-style: italic; font-size: 0.78rem;">
                Match Score compares this bid only to the Landlord's original request. Added services or added terms are shown for transparency but do not increase the score.
            </div>
            @endif
        </div>
        @endif

        <!-- View Full Bid link -->
        @if ($isListingOwner || $isBidOwner)
        <a href="#" data-bs-toggle="modal" data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
           style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
            View Full Bid
        </a>
        @else
        <span style="color: #888; font-style: italic; font-size: 0.95rem;">
            <i class="fa-solid fa-lock me-1"></i> Full bid details are private
        </span>
        @endif
        <!-- Edit Bid button for bid owner -->
        @if ($canEditWithdraw)
        <div class="d-flex gap-2 mt-3 justify-content-end align-items-center">
            <a href="{{ route('agent.landlord.agent.auction.bid', $auction->id) }}?edit={{ data_get($bid, 'id') }}"
               class="btn btn-primary hla-bid-action-btn">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Bid
            </a>
        </div>
        @elseif ($isBidOwner && $isExpired)
        <div class="mt-3">
            <span class="text-muted small">
                <i class="fa-solid fa-clock me-1"></i> Bidding has ended - edit unavailable
            </span>
        </div>
        @elseif ($isBidOwner && ($bidAccepted === 'accepted' || $bidAccepted === 'rejected'))
        <div class="mt-3">
            <span class="text-muted small">
                <i class="fa-solid fa-lock me-1"></i> Bid {{ $bidAccepted }} - edit unavailable
            </span>
        </div>
        @endif
            <!-- Private Data Section - visible to listing owner or bid owner -->
            @if ($isListingOwner || $isBidOwner)
            <!-- Private Data Modal -->
            <div class="modal fade"
                id="privateDataModal{{ data_get($bid, 'id') }}"
                tabindex="-1"
                aria-labelledby="privateDataModalLabel{{ data_get($bid, 'id') }}"
                aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content"
                        style="border-radius: 10px; border: none;">
                        <div class="modal-header text-white"
                            style="background: #049399; border-bottom: none; padding: 20px;">
                            <h5 class="modal-title"
                                id="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                style="font-weight: 600;">
                                <i class="fa-solid fa-lock me-2"></i> Private
                                Compensation & Agreement Terms
                            </h5>
                        </div>
                        <div class="modal-body"
                            style="background: #fafafa; padding: 25px;">
                            @include('partials.bid_detail_body.landlord')
                        </div>
                        @php
                            // Compute modal-footer state — uses $latestOwnerCounter (owner-scoped) for countered detection
                            $_mfRawL    = data_get($bid, 'accepted', '0');
                            $_mfTermL   = in_array((string)$_mfRawL, ['accepted', 'rejected'], true);
                            $_mfActiveL = isset($latestOwnerCounter) && $latestOwnerCounter !== null;
                            // 'accepted' column stores 'no' for undecided bids (not false/'0'/null).
                            // Treat anything that is not a terminal state as '0' (undecided).
                            $mfStateL   = (!$_mfTermL && $_mfActiveL)
                                ? 'countered'
                                : ($_mfTermL ? (string)$_mfRawL : '0');
                            $mfOwnerIdL    = data_get($auction, 'user_id');
                            $mfOwnerFirstL = data_get($auction, 'user.first_name', '');
                            $mfOwnerLastL  = data_get($auction, 'user.last_name', '');
                            $mfAgentFirstL = data_get($bid, 'user.first_name', '');
                            $mfAgentLastL  = data_get($bid, 'user.last_name', '');
                            // M5.5: was a fourth ownership derivation — `(int)$auth_id === (int)$mfOwnerIdL`.
                            // Integer-safe, unlike the two M5.4 removed, but still wrong at the edge:
                            // a null viewer and a null owner both cast to 0 and compare equal. Asked
                            // of HireAgentProposalAccess instead, which refuses both.
                            $mfIsOwnerL    = $isListingOwner;
                        @endphp
                        <div class="modal-footer"
                            style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; flex-wrap: wrap; gap: 12px;">

                            {{-- Confidential notice --}}
                            <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                <i class="fa-solid fa-shield-halved me-2"></i>
                                <strong>Confidential:</strong> This information is private and only visible to you.
                            </div>

                            {{-- ── Bid action row (shared partial) ── --}}
                            @include('hire_landlord_agent.partials.bid_action_row', [
                                'bid'                  => $bid,
                                'auction'              => $auction,
                                'isOwner'              => $mfIsOwnerL,
                                'state'                => $mfStateL,
                                'isSold'               => in_array(data_get($auction, 'is_sold'), [true,'true',1,'1'], true),
                                'isExpired'            => $isExpired,
                                'latestOwnerCounter'   => $latestOwnerCounter,
                                'ownerFirst'           => $mfOwnerFirstL,
                                'ownerLast'            => $mfOwnerLastL,
                                'agentFirst'           => $mfAgentFirstL,
                                'agentLast'            => $mfAgentLastL,
                            ])

                            {{-- ── Close button ── --}}
                            <div class="w-100 d-flex justify-content-end mt-2">
                                <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal"
                                    style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Counter Bids -->

            @php
            /*
             | M5.5 — this block used to re-derive, for a second time, everything the card had
             | already established at the top of this file. What was here and why it is gone:
             |
             |   · a byte-identical second LandlordCounterTerm query — one query per card now;
             |   · a 'countered'-aware $state that no markup below ever read;
             |   · $isOwnerRow, assigned and never read, from the loose `==` ownership test;
             |   · $hasAcceptedCounterBid recomputed to the value it already held.
             |
             | What genuinely belongs here — the display names the counter history renders, and
             | the $showCounterBids gate — is kept, unchanged. $isListingOwner and $isBidOwner are
             | NOT reassigned: they already hold the authoritative values (HireAgentProposalAccess
             | via $hlaIsListingOwner, and the parent loop's guard variable), and re-deriving them
             | is exactly how the second, weaker copy got here.
             */
            $ownerFirst = data_get($auction, 'user.first_name', '');
            $ownerLast = data_get($auction, 'user.last_name', '');
            $agentFirst = data_get($bid, 'user.first_name', '');
            $agentLast = data_get($bid, 'user.last_name', '');

            $ownerId = data_get($auction, 'user_id');

            // Access control for the counter history — the same two-branch rule the rest of the
            // card uses, asked of the values already resolved above.
            $showCounterBids = $isListingOwner || $isBidOwner;
            @endphp

            {{-- Counter Bidding Section - Only visible to listing owner and bidding agent --}}
            @if ($showCounterBids && $counterBids->count() > 0)
            <div class="counter-bids-section mt-4" id="counter-section-{{ data_get($bid, 'id') }}">
                <!-- Counter Bids Toggle Header (plain JS, no Bootstrap collapse — avoids flash from outer accordion interference) -->
                <div class="counter-bids-toggle"
                    style="cursor: pointer;"
                    onclick="event.stopPropagation(); var target = document.getElementById('counterBids{{ data_get($bid, 'id') }}'); var arrow = this.querySelector('.counter-arrow'); if(target.style.display === 'none' || target.style.display === '') { target.style.display = 'block'; arrow.style.transform = 'rotate(180deg)'; } else { target.style.display = 'none'; arrow.style.transform = 'rotate(0deg)'; }">
                    <div
                        class="d-flex justify-content-between align-items-center flex-wrap p-2 border rounded">
                        <h5 class="mb-0" style="color: #2c3e50;">Counter
                            Bidding History</h5>
                        <div class="d-flex align-items-center">
                            <span
                                class="badge bg-secondary me-2">{{ $counterBids->count() }}
                                counter offers</span>
                            <span class="counter-arrow" style="transition: transform 0.3s;">↓</span>
                        </div>
                    </div>
                </div>

                <!-- Counter Bids Content -->
                <div id="counterBids{{ data_get($bid, 'id') }}"
                    class="counter-bids-content"
                    style="display: none;"
                    aria-labelledby="counterBidsHeading{{ data_get($bid, 'id') }}">
                    <div
                        class="accordion-body p-3 border border-top-0 rounded-bottom hla-counter-font">
                        @foreach ($counterBids as $counterBid)
                        @php
                        // Roles.
                        //
                        // M5.5: these were a fifth `data_get($auction,'user_id') == $auth_id` copy
                        // and a variable called $isAgent that did NOT mean what the identically
                        // named variable at the top of the card meant. That one was the global
                        // role test (user_type === 'agent'); this one asks "did the viewer author
                        // the bid this counter hangs off". Two questions, one name, one of them
                        // shadowing the other — so the local one is renamed to say what it asks,
                        // and both now reuse the values already resolved for this card.
                        $isCounterFromOwner =
                        $counterBid->user_id ==
                        data_get($auction, 'user_id');
                        $isCounterFromAgent =
                        $counterBid->user_id ==
                        data_get($bid, 'user_id');

                        // States
                        $rawBidState = data_get(
                        $bid,
                        'accepted',
                        '0',
                        );
                        $bidState = in_array(
                        $rawBidState,
                        [null, 0, '0', 'no', 'pending', false],
                        true,
                        )
                        ? '0'
                        : (string) $rawBidState;

                        $rawCounterState = data_get(
                        $counterBid,
                        'status',
                        '0',
                        );
                        $counterState = in_array(
                        $rawCounterState,
                        [null, 0, '0', 'pending'],
                        true,
                        )
                        ? '0'
                        : (string) $rawCounterState;

                        // Actions visibility (other party, both pending)
                        $showCounterActions = false;
                        if (
                        $bidState === '0' &&
                        $counterState === '0' &&
                        !$hasAcceptedCounterBid &&
                        !$bidIsAccepted &&
                        !$isSold &&
                        !$isExpired
                        ) {
                        if ($isListingOwner && $isCounterFromAgent) {
                        $showCounterActions = true;
                        }
                        if ($isBidOwner && $isCounterFromOwner) {
                        $showCounterActions = true;
                        }
                        }

                        // Names
                        $ownerFirst = data_get(
                        $auction,
                        'user.first_name',
                        '',
                        );
                        $ownerLast = data_get(
                        $auction,
                        'user.last_name',
                        '',
                        );
                        $agentFirst = data_get(
                        $bid,
                        'user.first_name',
                        '',
                        );
                        $agentLast = data_get(
                        $bid,
                        'user.last_name',
                        '',
                        );

                        // For counter accepted/rejected: actor is ALWAYS the other party (not the creator)
                        $actorUserId = $isCounterFromOwner
                        ? data_get($bid, 'user_id')
                        : data_get($auction, 'user_id');
                        $actorFirst = $isCounterFromOwner
                        ? $agentFirst
                        : $ownerFirst;
                        $actorLast = $isCounterFromOwner
                        ? $agentLast
                        : $ownerLast;

                        // Creator names (for "pending" other-party view)
                        $creatorFirst = data_get(
                        $counterBid,
                        'user.first_name',
                        '',
                        );
                        $creatorLast = data_get(
                        $counterBid,
                        'user.last_name',
                        '',
                        );
                        @endphp

                        <div
                            class="counter-bid-card mb-3 p-3 border rounded mt-2">
                            <div
                                class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                <h6 class="mb-0">
                                    @if ($counterBid->user_id == Auth::id())
                                    Your Counter Offer
                                    @else
                                    Counter Offer from
                                    {{ data_get($counterBid, 'user.first_name') }}
                                    {{ data_get($counterBid, 'user.last_name') }}
                                    @endif
                                </h6>
                                <small
                                    class="text-muted">{{ optional($counterBid->created_at)->format('M j, Y g:i A') }}</small>
                            </div>

                            @php
                                $allMeta = $counterBid->getAllMeta();

                                // ── A) Lease Fee composite display ──
                                $ctLeaseFeeType = $allMeta['purchase_fee_type'] ?? '';
                                $ctLeaseFeeDisplay = $ctLeaseFeeType;
                                if ($ctLeaseFeeType === 'Flat Fee') {
                                    $lf = $allMeta['purchase_fee_flat'] ?? ($allMeta['purchase_fee_flat_commercial'] ?? null);
                                    if ($lf) $ctLeaseFeeDisplay = '$'.number_format((float)$lf,2).' Flat Fee';
                                } elseif ($ctLeaseFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                    $pct = $allMeta['purchase_fee_rental_period'] ?? null;
                                    if ($pct) $ctLeaseFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                } elseif ($ctLeaseFeeType === 'Percentage of the Gross Lease Value') {
                                    $pct = $allMeta['purchase_fee_percentage_combo'] ?? null;
                                    if ($pct) $ctLeaseFeeDisplay = $pct.'% of Gross Lease Value';
                                } elseif ($canon($ctLeaseFeeType) === "Percentage of the First Month's Rent") {
                                    $pct = $allMeta['purchase_fee_flat_combo'] ?? null;
                                    if ($pct) $ctLeaseFeeDisplay = $pct."% of First Month's Rent";
                                } elseif ($ctLeaseFeeType === 'Percentage of the Net Aggregate Rent') {
                                    $pct = $allMeta['purchase_fee_net_aggregate'] ?? null;
                                    if ($pct) $ctLeaseFeeDisplay = $pct.'% of Net Aggregate Rent';
                                } elseif ($ctLeaseFeeType === 'Percentage of the Gross Rent') {
                                    $pct = $allMeta['purchase_fee_gross_rent'] ?? null;
                                    if ($pct) $ctLeaseFeeDisplay = $pct.'% of Gross Rent';
                                } elseif ($canon($ctLeaseFeeType) === "Percentage of Month's Rent") {
                                    $pct    = $allMeta['purchase_fee_monthly_percentage'] ?? null;
                                    $months = $allMeta['purchase_fee_months'] ?? null;
                                    if ($pct) $ctLeaseFeeDisplay = $pct."% of Month's Rent".($months ? " × $months months" : '');
                                } elseif ($ctLeaseFeeType === 'other') {
                                    $oth = $allMeta['purchase_fee_other'] ?? ($allMeta['purchase_fee_other_commercial'] ?? null);
                                    $ctLeaseFeeDisplay = 'Other: '.($oth ?: 'See details');
                                }

                                // ── A) Payment Timing composite display ──
                                $ctFeeTimingRaw = $allMeta['broker_fee_timing'] ?? '';
                                $ctFeeTimingDisplay = match($ctFeeTimingRaw) {
                                    'full_execution' => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
                                    default => $ctFeeTimingRaw,
                                };
                                if ($ctFeeTimingRaw === 'Deducted from Rent Collected') {
                                    $d = $allMeta['broker_fee_days_from_rent'] ?? null;
                                    if ($d) $ctFeeTimingDisplay .= " ($d calendar days)";
                                } elseif ($ctFeeTimingRaw === 'Paid Within Calendar Days After Executed Lease') {
                                    $d = $allMeta['broker_fee_days_after_lease'] ?? null;
                                    if ($d) $ctFeeTimingDisplay = "Within $d days after executed lease";
                                } elseif ($ctFeeTimingRaw === 'Paid Within Calendar Days of Tenant Rent Payment') {
                                    $d = $allMeta['broker_fee_days_after_rent'] ?? null;
                                    if ($d) $ctFeeTimingDisplay = "Within $d days of tenant rent payment";
                                } elseif ($ctFeeTimingRaw === 'other') {
                                    $oth = $allMeta['broker_fee_timing_other'] ?? null;
                                    $ctFeeTimingDisplay = $oth ?: 'Custom arrangement';
                                } elseif (in_array($ctFeeTimingRaw, ['50% due upon execution, 50% due upon commencement of agreement','50% due upon execution, 50% due upon occupancy of premises'])) {
                                    $d2 = $allMeta['broker_fee_days_after_due_event'] ?? null;
                                    if ($d2) $ctFeeTimingDisplay .= " (second installment within $d2 days)";
                                }

                                // ── A) Renewal Fee composite display ──
                                $ctRenewalFeeType = $allMeta['renewal_fee_type'] ?? '';
                                $ctRenewalFeeDisplay = $ctRenewalFeeType;
                                if ($ctRenewalFeeType === 'Flat Fee') {
                                    $flat = $allMeta['renewal_fee_flat_free'] ?? null;
                                    if ($flat) $ctRenewalFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                } elseif ($ctRenewalFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                    $pct = $allMeta['renewal_fee_percentage'] ?? null;
                                    if ($pct) $ctRenewalFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                } elseif ($ctRenewalFeeType === 'Percentage of the Gross Lease Value') {
                                    $pct = $allMeta['renewal_fee_lease_value'] ?? null;
                                    if ($pct) $ctRenewalFeeDisplay = $pct.'% of Gross Lease Value';
                                } elseif ($canon($ctRenewalFeeType) === "Percentage of the First Month's Rent") {
                                    $pct = $allMeta['renewal_fee_first_month'] ?? null;
                                    if ($pct) $ctRenewalFeeDisplay = $pct."% of First Month's Rent";
                                } elseif ($ctRenewalFeeType === 'Percentage of the Net Aggregate Rent') {
                                    $pct = $allMeta['renewal_fee_percentage'] ?? null;
                                    if ($pct) $ctRenewalFeeDisplay = $pct.'% of Net Aggregate Rent';
                                } elseif ($ctRenewalFeeType === 'Percentage of the Gross Rent') {
                                    $pct = $allMeta['renewal_fee_lease_value'] ?? null;
                                    if ($pct) $ctRenewalFeeDisplay = $pct.'% of Gross Rent';
                                } elseif ($canon($ctRenewalFeeType) === "Percentage of Month's Rent") {
                                    $pct    = $allMeta['renewal_fee_first_month'] ?? null;
                                    $months = $allMeta['renewal_fee_no_of_months'] ?? null;
                                    if ($pct) $ctRenewalFeeDisplay = $pct."% of Month's Rent".($months ? " × $months months" : '');
                                } elseif ($ctRenewalFeeType === 'other') {
                                    $oth = $allMeta['renewal_fee_custom'] ?? null;
                                    $ctRenewalFeeDisplay = 'Other: '.($oth ?: 'See details');
                                }

                                // ── B) Tenant Broker — structure and fee SEPARATELY ──
                                $ctTenantBrokerStructure  = $allMeta['tenant_broker_commission_structure'] ?? '';
                                $ctTenantBrokerFeeDisplay = '';
                                $ctTbs = $allMeta['tenant_broker_fee_structure'] ?? '';
                                if ($ctTenantBrokerStructure && $ctTbs) {
                                    if ($ctTbs === 'Percentage of the Rent Due Each Rental Period') {
                                        $pct = $allMeta['tenant_broker_percentage'] ?? null;
                                        if ($pct) $ctTenantBrokerFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                    } elseif ($ctTbs === 'Percentage of the Gross Lease Value') {
                                        $pct = $allMeta['tenant_broker_gross_lease'] ?? null;
                                        if ($pct) $ctTenantBrokerFeeDisplay = $pct.'% of Gross Lease Value';
                                    } elseif ($ctTbs === "Percentage of the First Month's Rent") {
                                        $pct = $allMeta['tenant_broker_first_month_rent'] ?? null;
                                        if ($pct) $ctTenantBrokerFeeDisplay = $pct."% of First Month's Rent";
                                    } elseif ($ctTbs === 'Flat Fee') {
                                        $flat = $allMeta['tenant_broker_flat_fee'] ?? null;
                                        if ($flat) $ctTenantBrokerFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                    } elseif ($ctTbs === 'other') {
                                        $oth = $allMeta['tenant_broker_other'] ?? null;
                                        if ($oth) $ctTenantBrokerFeeDisplay = 'Other: '.$oth;
                                    }
                                }
                                // Combined display for counter-term comparison
                                $ctTenantBrokerDisplay = $ctTenantBrokerStructure . ($ctTenantBrokerFeeDisplay ? ' – '.$ctTenantBrokerFeeDisplay : '');

                                // ── C) Lease-Option composite displays ──
                                $ctLeaseOptInterest = $allMeta['interested_lease_option_agreement'] ?? '';
                                $ctLeaseOptionCreatedDisplay   = '-';
                                $ctLeaseOptionExercisedDisplay = '-';
                                if ($ctLeaseOptInterest === 'Yes') {
                                    $lt = $allMeta['lease_type'] ?? null;
                                    $lv = $allMeta['lease_value'] ?? null;
                                    if ($lt && $lv) {
                                        $ctLeaseOptionCreatedDisplay = ($lt === 'percent')
                                            ? ($fmtPercent($lv) ? $fmtPercent($lv).' of Total Purchase Price' : '-')
                                            : ($fmtMoney($lv) ?? '-');
                                    }
                                    $pt = $allMeta['purchase_type'] ?? null;
                                    $pv = $allMeta['purchase_value'] ?? null;
                                    if ($pt && $pv) {
                                        $ctLeaseOptionExercisedDisplay = ($pt === 'percent')
                                            ? ($fmtPercent($pv) ? $fmtPercent($pv).' of Total Purchase Price' : '-')
                                            : ($fmtMoney($pv) ?? '-');
                                    }
                                }

                                // ── D) Purchase Fee composite display ──
                                $ctSellingInterest  = $allMeta['interested_in_selling'] ?? '';
                                $ctPurchaseFeeDisplay = '-';
                                if ($ctSellingInterest === 'Yes') {
                                    $ist = $allMeta['interested_in_selling_type'] ?? '';
                                    if ($ist === 'Percentage of the Total Purchase Price') {
                                        $pct = $allMeta['landlord_broker_purchase_price'] ?? null;
                                        $ctPurchaseFeeDisplay = $pct ? $fmtPercent($pct).' of Total Purchase Price' : $ist;
                                    } elseif ($ist === 'Percentage of the Total Purchase Price + Flat Fee') {
                                        $pct  = $allMeta['landlord_broker_percentage_price'] ?? null;
                                        $flat = $allMeta['landlord_broker_dollar_price'] ?? null;
                                        $ctPurchaseFeeDisplay = trim(($pct ? $fmtPercent($pct).' of Total Purchase Price' : '').($pct && $flat ? ' + ' : '').($flat ? $fmtMoney($flat) : ''));
                                        if (!$ctPurchaseFeeDisplay) $ctPurchaseFeeDisplay = $ist;
                                    } elseif ($ist === 'Flat Fee') {
                                        $flat = $allMeta['landlord_broker_flate_fee'] ?? null;
                                        $ctPurchaseFeeDisplay = $flat ? '$'.number_format((float)$flat,2).' Flat Fee' : $ist;
                                    } elseif ($ist === 'Other') {
                                        $oth = $allMeta['landlord_broker_other'] ?? null;
                                        $ctPurchaseFeeDisplay = $oth ? 'Other: '.$oth : 'Other';
                                    } else {
                                        $ctPurchaseFeeDisplay = $ist ?: '-';
                                    }
                                }

                                // ── E) Agency Agreement Timeframe display ──
                                $ctAgencyTimeframe = $allMeta['agency_agreement_timeframe'] ?? '';
                                $ctAgencyTimeframeDisplay = (strtolower(trim($ctAgencyTimeframe)) === 'other')
                                    ? ($allMeta['agency_agreement_custom'] ?? 'Other')
                                    : $ctAgencyTimeframe;

                                // ── E) Property Management Fee composite display ──
                                $ctPmFeeDisplay = '-';
                                if (($allMeta['interested_in_property_management'] ?? '') === 'yes') {
                                    $pmFeeType = $allMeta['interested_in_property_management_fee'] ?? '';
                                    $ctPmFeeDisplay = $pmFeeType;
                                    if ($pmFeeType === 'Percentage of the Gross Lease Value') {
                                        $pct = $allMeta['interested_in_property_management_fee_gross_lease'] ?? null;
                                        if ($pct) $ctPmFeeDisplay = $pct.'% of Gross Lease Value';
                                    } elseif ($pmFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                        $pct = $allMeta['interested_in_property_management_fee_rental_periord'] ?? null;
                                        if ($pct) $ctPmFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                    } elseif ($pmFeeType === 'Flat Fee') {
                                        $flat = $allMeta['interested_in_property_management_fee_flate_free'] ?? null;
                                        if ($flat) $ctPmFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                    } elseif ($pmFeeType === 'Other') {
                                        $oth = $allMeta['interested_in_property_management_fee_other'] ?? null;
                                        if ($oth) $ctPmFeeDisplay = 'Other: '.$oth;
                                    }
                                }

                                $ctHasBrokerComp = !empty($ctLeaseFeeType) || !empty($ctFeeTimingRaw) || !empty($ctRenewalFeeType)
                                    || !empty($allMeta['expansion_commission_percentage'])
                                    || !empty($ctTenantBrokerStructure)
                                    || !empty($ctLeaseOptInterest)
                                    || !empty($ctSellingInterest)
                                    || !empty($allMeta['protection_period'])
                                    || !empty($allMeta['early_termination_fee_option'])
                                    || !empty($ctAgencyTimeframe)
                                    || !empty($allMeta['interested_in_property_management'])
                                    || !empty($allMeta['brokerage_relationship'])
                                    || !empty($allMeta['additional_details_broker'])
                                    || !empty($allMeta['additional_details']);

                                // === Diff helpers: counter vs original bid ===
                                // Compare two composite display strings (normalized)
                                $ctCompositeChanged = function(string $cDisplay, string $oDisplay): bool {
                                    $norm = fn($v) => preg_replace('/[\s$,]/', '', strtolower(trim($v)));
                                    return $norm($cDisplay) !== $norm($oDisplay);
                                };
                                // Compare a single raw meta key to the original bid's stored value
                                $ctIsChanged = function($counterVal, string $origKey) use ($bid): bool {
                                    $origVal = data_get($bid, 'get.' . $origKey, null);
                                    $norm = fn($v) => preg_replace('/[\s$,%]/', '', strtolower(trim((string)($v ?? ''))));
                                    return $norm($counterVal) !== $norm($origVal);
                                };
                                $ctChangedStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                                $ctChangedBadge = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; vertical-align: middle;">Changed</span>';

                                // Services diff: counter services vs ORIGINAL BID services
                                $origBidSvcsRaw = data_get($bid, 'get.services', []);
                                if (is_string($origBidSvcsRaw)) $origBidSvcsRaw = json_decode($origBidSvcsRaw, true) ?: [];
                                $origBidSvcsNorm = array_values(array_map(
                                    fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::normalizeService((string)$s),
                                    array_filter((array)$origBidSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other')
                                ));
                                $origBidOtherRaw = data_get($bid, 'get.other_services', []);
                                if (is_string($origBidOtherRaw)) $origBidOtherRaw = json_decode($origBidOtherRaw, true) ?: [];
                                $origBidOtherNorm = array_values(array_filter(array_map(
                                    fn($s) => strtolower(trim((string)$s)),
                                    array_filter((array)$origBidOtherRaw, fn($s) => is_string($s) && trim($s) !== '')
                                )));
                            @endphp

                            @if ($ctHasBrokerComp)
                            <div class="mb-4">
                                <h6 class="mb-3" style="font-weight: 600; color: #049399; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                    <i class="fa-solid fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                                </h6>

                                {{-- A) Landlord's Broker Lease Fee --}}
                                @if (!empty($ctLeaseFeeType) || !empty($ctFeeTimingRaw) || !empty($ctRenewalFeeType) || !empty($allMeta['expansion_commission_percentage']))
                                <div class="mb-3">
                                    <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">A) Landlord's Broker Lease Fee</div>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        @if (!empty($ctLeaseFeeType))
                                        @php $ctLeaseFeeChg = $ctCompositeChanged($ctLeaseFeeDisplay, $leaseFeeDisplay ?? ''); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctLeaseFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Landlord's Broker Lease Fee:</span> {{ $ctLeaseFeeDisplay }}{!! $ctLeaseFeeChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                        @if (!empty($ctFeeTimingRaw))
                                        @php $ctFeeTimingChg = $ctCompositeChanged($ctFeeTimingDisplay, $feeTimingDisplay ?? ''); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctFeeTimingChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Payment Timing for Broker Fees:</span> {{ $ctFeeTimingDisplay }}{!! $ctFeeTimingChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                        @if (!empty($ctRenewalFeeType))
                                        @php $ctRenewalFeeChg = $ctCompositeChanged($ctRenewalFeeDisplay, $renewalFeeDisplay ?? ''); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctRenewalFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Lease Renewal/Extension Fee:</span> {{ $ctRenewalFeeDisplay }}{!! $ctRenewalFeeChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                        @if (!empty($allMeta['expansion_commission_percentage']))
                                        @php $ctExpChg = $ctIsChanged($allMeta['expansion_commission_percentage'], 'expansion_commission_percentage'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctExpChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Expansion Commission for Lease Amendment:</span> {{ $allMeta['expansion_commission_percentage'] }}% of original commission{!! $ctExpChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                    </ul>
                                </div>
                                @endif

                                {{-- B) Tenant's Broker Compensation --}}
                                @if (!empty($ctTenantBrokerStructure))
                                <div class="mb-3">
                                    <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">B) Tenant's Broker Compensation</div>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        @php $ctTenantBrokerStructureChg = $ctIsChanged($ctTenantBrokerStructure, 'tenant_broker_commission_structure'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctTenantBrokerStructureChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Structure:</span> {{ $ctTenantBrokerStructure }}{!! $ctTenantBrokerStructureChg ? $ctChangedBadge : '' !!}</li>
                                        @if ($ctTenantBrokerFeeDisplay)
                                        @php $ctTenantBrokerFeeChg = $ctCompositeChanged($ctTenantBrokerFeeDisplay, $tenantBrokerFeeDisplay ?? ''); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctTenantBrokerFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Fee:</span> {{ $ctTenantBrokerFeeDisplay }}{!! $ctTenantBrokerFeeChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                    </ul>
                                </div>
                                @endif

                                {{-- C) Lease-Option Details --}}
                                @if (!empty($ctLeaseOptInterest))
                                <div class="mb-3">
                                    <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">C) Lease-Option Details</div>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        @php $ctLeaseOptChg = $ctIsChanged($ctLeaseOptInterest, 'interested_lease_option_agreement'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctLeaseOptChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Interested in Offering a Lease-Option Agreement:</span> {{ $ctLeaseOptInterest }}{!! $ctLeaseOptChg ? $ctChangedBadge : '' !!}</li>
                                        @if ($ctLeaseOptInterest === 'Yes')
                                            @if ($ctLeaseOptionCreatedDisplay !== '-')
                                            @php $ctLeaseCreatedChg = $ctCompositeChanged($ctLeaseOptionCreatedDisplay, $leaseOptionCreatedDisplay ?? ''); @endphp
                                            <li class="mb-1" style="font-size: 12px; {{ $ctLeaseCreatedChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Compensation for Creating the Lease-Option Agreement:</span> {{ $ctLeaseOptionCreatedDisplay }}{!! $ctLeaseCreatedChg ? $ctChangedBadge : '' !!}</li>
                                            @endif
                                            @if ($ctLeaseOptionExercisedDisplay !== '-')
                                            @php $ctLeaseExercisedChg = $ctCompositeChanged($ctLeaseOptionExercisedDisplay, $leaseOptionExercisedDisplay ?? ''); @endphp
                                            <li class="mb-1" style="font-size: 12px; {{ $ctLeaseExercisedChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> {{ $ctLeaseOptionExercisedDisplay }}{!! $ctLeaseExercisedChg ? $ctChangedBadge : '' !!}</li>
                                            @endif
                                        @endif
                                    </ul>
                                </div>
                                @endif

                                {{-- D) Purchase Fee Details --}}
                                @if (!empty($ctSellingInterest))
                                <div class="mb-3">
                                    <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">D) Purchase Fee Details</div>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        @php $ctSellingChg = $ctIsChanged($ctSellingInterest, 'interested_in_selling'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctSellingChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Interested in Selling the Property:</span> {{ $ctSellingInterest }}{!! $ctSellingChg ? $ctChangedBadge : '' !!}</li>
                                        @if ($ctSellingInterest === 'Yes' && $ctPurchaseFeeDisplay !== '-')
                                        @php $ctPurchaseFeeChg = $ctCompositeChanged($ctPurchaseFeeDisplay, $purchaseFeeDisplay ?? ''); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctPurchaseFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Purchase Fee:</span> {{ $ctPurchaseFeeDisplay }}{!! $ctPurchaseFeeChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                    </ul>
                                </div>
                                @endif

                                {{-- E) Legal Terms --}}
                                @if (!empty($allMeta['protection_period']) || !empty($allMeta['early_termination_fee_option']) || !empty($ctAgencyTimeframe) || !empty($allMeta['interested_in_property_management']))
                                <div class="mb-3">
                                    <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">E) Legal Terms</div>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        @if (!empty($allMeta['protection_period']))
                                        @php $ctProtChg = $ctIsChanged($allMeta['protection_period'], 'protection_period'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctProtChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ $allMeta['protection_period'] }} days{!! $ctProtChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                        @if (!empty($allMeta['early_termination_fee_option']))
                                        @php $ctEtfChg = $ctIsChanged($allMeta['early_termination_fee_option'], 'early_termination_fee_option'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctEtfChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ $allMeta['early_termination_fee_option'] === 'yes' ? 'Yes' : 'No' }}{!! $ctEtfChg ? $ctChangedBadge : '' !!}</li>
                                        @if ($allMeta['early_termination_fee_option'] === 'yes' && !empty($allMeta['early_termination_fee_amount']))
                                        @php $ctEtfAmtChg = $ctIsChanged($allMeta['early_termination_fee_amount'], 'early_termination_fee_amount'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctEtfAmtChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney($allMeta['early_termination_fee_amount']) ?? ('$'.$allMeta['early_termination_fee_amount']) }}{!! $ctEtfAmtChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                        @endif
                                        @if (!empty($ctAgencyTimeframe))
                                        @php $ctAgencyTfChg = $ctCompositeChanged($ctAgencyTimeframeDisplay, $agencyTimeframeDisplay ?? ''); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctAgencyTfChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Landlord Agency Agreement Timeframe:</span> {{ $ctAgencyTimeframeDisplay }}{!! $ctAgencyTfChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                        @if (!empty($allMeta['interested_in_property_management']))
                                        @php $ctPmChg = $ctIsChanged($allMeta['interested_in_property_management'], 'interested_in_property_management'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctPmChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Interested in Property Management:</span> {{ ($allMeta['interested_in_property_management'] === 'yes') ? 'Yes' : 'No' }}{!! $ctPmChg ? $ctChangedBadge : '' !!}</li>
                                        @if (($allMeta['interested_in_property_management'] === 'yes') && $ctPmFeeDisplay !== '-')
                                        @php $ctPmFeeChg = $ctCompositeChanged($ctPmFeeDisplay, $pmFeeDisplay ?? ''); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctPmFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Property Management Fee:</span> {{ $ctPmFeeDisplay }}{!! $ctPmFeeChg ? $ctChangedBadge : '' !!}</li>
                                        @endif
                                        @endif
                                    </ul>
                                </div>
                                @endif

                                {{-- F) Brokerage Relationship --}}
                                @if (!empty($allMeta['brokerage_relationship']))
                                <div class="mb-3">
                                    <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">F) Brokerage Relationship</div>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        @php $ctBrokerRelChg = $ctIsChanged($allMeta['brokerage_relationship'], 'brokerage_relationship'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctBrokerRelChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $allMeta['brokerage_relationship'] }}{!! $ctBrokerRelChg ? $ctChangedBadge : '' !!}</li>
                                    </ul>
                                </div>
                                @endif

                                {{-- G) Additional Terms --}}
                                @if (!empty($allMeta['additional_details_broker']))
                                <div class="mb-3">
                                    <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">G) Additional Terms</div>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        @php $ctAddTermsChg = $ctIsChanged($allMeta['additional_details_broker'], 'additional_details_broker'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctAddTermsChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Additional Terms:</span> {{ $allMeta['additional_details_broker'] }}{!! $ctAddTermsChg ? $ctChangedBadge : '' !!}</li>
                                    </ul>
                                </div>
                                @endif

                                {{-- H) Referral Fee --}}
                                @if ($auction->isCreatedByAgent() && !empty($allMeta['referral_fee_percent']))
                                <div class="mb-3">
                                    <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">H) Referral Fee</div>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        @php $ctRefFeeChg = $ctIsChanged($allMeta['referral_fee_percent'], 'referral_fee_percent'); @endphp
                                        <li class="mb-1" style="font-size: 12px; {{ $ctRefFeeChg ? $ctChangedStyle : '' }}"><span class="fw-semibold">Referral Fee (%):</span> {{ $allMeta['referral_fee_percent'] }}%{!! $ctRefFeeChg ? $ctChangedBadge : '' !!}</li>
                                    </ul>
                                </div>
                                @endif

                            </div>
                            @endif

                            {{-- Additional Details --}}
                            @if (!empty($allMeta['additional_details']))
                            <div class="mb-3">
                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;"><i class="fa-solid fa-circle-info me-1"></i>Additional Details</div>
                                @php $ctAddDetailsChg = $ctIsChanged($allMeta['additional_details'], 'additional_details'); @endphp
                                <div class="ps-3" style="font-size: 12px; {{ $ctAddDetailsChg ? $ctChangedStyle : '' }}">{{ $allMeta['additional_details'] }}{!! $ctAddDetailsChg ? $ctChangedBadge : '' !!}</div>
                            </div>
                            @endif



                            <!-- Services Offered (diff: counter vs original bid) -->
                            @php
                            $ctSvcsRaw = $allMeta['services'] ?? [];
                            if (is_string($ctSvcsRaw) && !empty($ctSvcsRaw)) {
                                $ctSvcsParsed = json_decode($ctSvcsRaw, true) ?: [];
                            } else {
                                $ctSvcsParsed = is_array($ctSvcsRaw) ? $ctSvcsRaw : [];
                            }
                            $ctSvcsParsed = array_values(array_filter($ctSvcsParsed, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'));

                            $ctOtherRaw = $allMeta['other_services'] ?? [];
                            if (is_string($ctOtherRaw) && !empty($ctOtherRaw)) {
                                $ctOtherParsed = json_decode($ctOtherRaw, true) ?: [];
                            } else {
                                $ctOtherParsed = is_array($ctOtherRaw) ? $ctOtherRaw : [];
                            }
                            $ctOtherParsed = array_values(array_filter($ctOtherParsed, fn($s) => is_string($s) && trim($s) !== ''));

                            // Normalize counter services for diff
                            $ctSvcsNorm = array_map(
                                fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::normalizeService((string)$s),
                                $ctSvcsParsed
                            );

                            // Determine added services (in counter but not in original bid)
                            $ctSvcIsAdded = fn(string $svc): bool =>
                                !in_array(\App\Helpers\LandlordBidMatchScoreHelper::normalizeService($svc), $origBidSvcsNorm, true);

                            // Build removed services list (in original bid but not in counter)
                            $ctRemovedSvcs = array_filter($origBidSvcsNorm, fn($n) => !in_array($n, $ctSvcsNorm, true));
                            // Map back to display text from original bid raw
                            $origBidSvcsDisplay = array_values(array_filter(
                                is_string(data_get($bid, 'get.services', [])) ? json_decode(data_get($bid, 'get.services', '[]'), true) ?? [] : (array)data_get($bid, 'get.services', []),
                                fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'
                            ));
                            $ctRemovedDisplay = array_values(array_filter($origBidSvcsDisplay, fn($s) =>
                                in_array(\App\Helpers\LandlordBidMatchScoreHelper::normalizeService($s), $ctRemovedSvcs, true)
                            ));

                            // Other services diff
                            $ctOtherIsAdded = fn(string $s): bool =>
                                !in_array(strtolower(trim($s)), $origBidOtherNorm, true);
                            $ctOtherRemovedDisplay = array_values(array_filter(
                                is_string(data_get($bid, 'get.other_services', [])) ? json_decode(data_get($bid, 'get.other_services', '[]'), true) ?? [] : (array)data_get($bid, 'get.other_services', []),
                                fn($s) => is_string($s) && trim($s) !== '' && !in_array(strtolower(trim($s)), array_map(fn($x) => strtolower(trim($x)), $ctOtherParsed), true)
                            ));

                            $hasCtSvcs = !empty($ctSvcsParsed) || !empty($ctOtherParsed);

                            // Normalizer for category-membership matching (handles smart quotes / Unicode escapes)
                            $normForCat = function(string $s): string {
                                $s = mb_strtolower(trim($s));
                                $s = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], $s);
                                $s = str_replace(['\\u2019', '\\u2018', '\\u201c', '\\u201d', '\\u201C', '\\u201D'], ["'", "'", '"', '"', '"', '"'], $s);
                                $s = str_replace(["\u{2014}", '\\u2014'], ['-', '-'], $s);
                                $s = preg_replace('/\s+/', ' ', $s);
                                return trim($s);
                            };

                            // Category map for grouping services — mirrors the bid-detail partial
                            $modalCats = $isCommercial ? $landlordCommercialCategories : $landlordResidentialCategories;
                            @endphp

                            <div class="mb-4" style="margin-top: 20px;">
                                <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                    <i class="fa-solid fa-clipboard-list me-2"></i>Offered Services
                                </h6>

                                @if ($hasCtSvcs)
                                    @foreach ($modalCats as $catName => $catSvcs)
                                        @php
                                            $normCatKeys = array_map($normForCat, $catSvcs);
                                            $inCat = array_filter($ctSvcsParsed, fn($svc) => in_array($normForCat($svc), $normCatKeys));
                                        @endphp
                                        @if (!empty($inCat))
                                        <div class="mb-3">
                                            <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $catName }}</div>
                                            <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                @foreach ($inCat as $svc)
                                                    @php $svcAdded = $ctSvcIsAdded($svc); @endphp
                                                    @if ($svcAdded)
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                        <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $svc }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                    </li>
                                                    @else
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $svc }}</li>
                                                    @endif
                                                    @if (strtolower(trim($svc)) === 'provide digital photo enhancements')
                                                    @php
                                                        $ctPhotoEnhRaw = $allMeta['photo_enhancements'] ?? [];
                                                        if (is_string($ctPhotoEnhRaw)) $ctPhotoEnhRaw = json_decode($ctPhotoEnhRaw, true) ?: [];
                                                        $ctCustomEnh = $allMeta['custom_enhancement'] ?? '';
                                                        $ctEnhOrder = ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'];
                                                    @endphp
                                                    @if (!empty($ctPhotoEnhRaw))
                                                    <ul style="padding-left: 1.5rem; margin: 4px 0; list-style: disc;">
                                                        @foreach ($ctEnhOrder as $ctEnh)
                                                            @if (in_array($ctEnh, $ctPhotoEnhRaw))
                                                                @if ($ctEnh === 'Other' && !empty($ctCustomEnh))
                                                                    <li style="font-size: 0.85rem;">{{ $ctCustomEnh }}</li>
                                                                @elseif ($ctEnh !== 'Other')
                                                                    <li style="font-size: 0.85rem;">{{ $ctEnh }}</li>
                                                                @endif
                                                            @endif
                                                        @endforeach
                                                    </ul>
                                                    @endif
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </div>
                                        @endif
                                    @endforeach

                                    @if (!empty($ctOtherParsed))
                                    <div class="mb-3">
                                        <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                        <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                            @foreach ($ctOtherParsed as $otherSvc)
                                                @php $otherAdded = $ctOtherIsAdded($otherSvc); @endphp
                                                @if ($otherAdded)
                                                <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                    <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $otherSvc }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                </li>
                                                @else
                                                <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $otherSvc }}</li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif

                                    @if (!empty($ctRemovedDisplay) || !empty($ctOtherRemovedDisplay))
                                    <div class="mb-3 mt-3 p-3" style="background-color: #fff5f5; border-radius: 6px; border: 1px solid #f5c6cb;">
                                        <div class="fw-bold mb-1" style="color: #dc3545; font-size: 0.95rem;">
                                            <i class="fa-solid fa-minus-circle me-1"></i>Removed Services
                                        </div>
                                        <ul class="services mb-0" style="margin-top: 0.5rem; padding-left: 1.2rem; list-style: none;">
                                            @foreach ($ctRemovedDisplay as $rSvc)
                                            <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                <i class="fa-solid fa-circle-xmark me-1"></i>{{ $rSvc }}
                                            </li>
                                            @endforeach
                                            @foreach ($ctOtherRemovedDisplay as $rSvc)
                                            <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                <i class="fa-solid fa-circle-xmark me-1"></i>{{ $rSvc }}
                                            </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif
                                @else
                                <div class="text-muted" style="font-style: italic;">No services selected for this counter.</div>
                                @endif
                            </div>

                            <!-- Counter actions (only when both pending & viewer is the other party & no counter bid is accepted) -->
                            @inject('carbon', 'Carbon\Carbon')


                            @php
                                // Milestone 3: this inner block recomputed $expiration from auction_time
                                // (created_at + "10 Days"), SHADOWING the page-level value from inside the
                                // bid loop — so counter actions were gated by their own synthesised timer
                                // even after the page-level one was retired. expiration_date is the only
                                // source now, matching the page-level rule exactly.
                                $expirationDate = data_get($auction->get, 'expiration_date');
                                $expiration = !empty($expirationDate)
                                    ? $carbon::parse($expirationDate)
                                    : null;

                                $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;
                            @endphp

                            {{-- Step 6: Display Actions or Expired Message --}}
                            @if ($showCounterActions)
                            <div class="mt-3 pt-3 border-top">
                                {{-- Actions are on View Counter Terms page only --}}
                                <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                    <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                </a>
                            </div>
                            @endif

                            <!-- Counter footer status -->
                            <div class="mt-3 pt-3 border-top">
                                @if ($counterState === 'accepted')
                                @if (Auth::id() == $actorUserId)
                                <div class="alert alert-success mb-0 py-1 small">
                                    ✅ This counter bid has been accepted.
                                </div>
                                @else
                                <div class="alert alert-success mb-0 py-1 small">
                                    ✅ {{ trim($actorFirst . ' ' . $actorLast) }} accepted the counter bid.
                                </div>
                                @endif
                                @elseif ($counterState === 'rejected')
                                @if (Auth::id() == $actorUserId)
                                <div class="alert alert-danger mb-0 py-1 small">
                                    ❌ This counter bid has been rejected.
                                </div>
                                @else
                                <div class="alert alert-danger mb-0 py-1 hla-alert-font">
                                    ❌ {{ trim($actorFirst . ' ' . $actorLast) }} rejected the counter bid.
                                </div>
                                @endif
                                @elseif ($counterState === '0')
                                @if ($counterBid->user_id == Auth::id())
                                <div class="alert alert-secondary mb-0 py-1 small">
                                    ⏳ Waiting for response from
                                    {{ $isCounterFromOwner ? trim($agentFirst . ' ' . $agentLast) : trim($ownerFirst . ' ' . $ownerLast) }}...
                                </div>
                                @else
                                <div class="alert alert-light mb-0 py-1 small"
                                    style="font-size:13px;">
                                    ⏳ Counter bid from
                                    {{ trim($creatorFirst . ' ' . $creatorLast) }}
                                    is pending.
                                </div>
                                @endif
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

        {{--
            Milestone 2 — the COMPETITOR SUMMARY @else branch was removed
            here. It rendered a competing agent's Offered Services and
            Terms Match counts plus a full Original/Counter match-score
            breakdown to any other agent viewing that bid. With the bid
            set now narrowed server-side the branch was already
            unreachable, but an unreachable competitor-disclosure branch
            is exactly the fragility this milestone exists to remove.
        --}}
        @endif
        {{-- End 3-branch card body --}}

    </div>
    </div>
</div>
