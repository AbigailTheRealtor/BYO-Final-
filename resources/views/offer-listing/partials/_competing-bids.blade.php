{{--
    Anonymous competing-bid feed for public Seller / Landlord listing pages.

    PRIVACY CONTRACT
    ----------------
    $bidFeed is built by PublicOfferFeedService only after canView() has passed.
    For a guest or an ineligible viewer the controller passes an EMPTY array —
    no bid data is queried, serialized, or rendered and then hidden. What this
    partial receives is all a viewer could ever extract from the page.

    Every field rendered here comes from the role's allow-list in
    PublicOfferFeedService. Do not add a field to this template — add it to the
    allow-list, where it can be reviewed against the exclusions.

    Expects: $role, $canViewBidFeed, $bidFeed, $biddingWindow
--}}
@php
    $bidLabels = [
        // Seller — audited scalar bidding terms
        'offer_price'                  => 'Offer Price',
        'earnest_deposit'              => 'Earnest Deposit',
        'earnest_deposit_unit'         => 'Earnest Deposit Unit',
        'financing_type'               => 'Financing',
        'financing_contingency'        => 'Financing Contingency',
        'financing_contingency_days'   => 'Financing Contingency (days)',
        'down_payment_value'           => 'Down Payment',
        'down_payment_unit'            => 'Down Payment Unit',
        'inspection_contingency'       => 'Inspection Contingency',
        'inspection_contingency_days'  => 'Inspection Contingency (days)',
        'appraisal_contingency'        => 'Appraisal Contingency',
        'appraisal_contingency_days'   => 'Appraisal Contingency (days)',
        'sale_of_buyer_property_contingency'      => 'Sale of Buyer Property Contingency',
        'sale_of_buyer_property_contingency_days' => 'Sale of Buyer Property (days)',
        'closing_date'                 => 'Closing Date',
        'possession_date'              => 'Possession Date',
        'home_warranty_requested'      => 'Home Warranty Requested',
        'seller_contribution_requested' => 'Seller Contribution Requested',
        // Landlord — narrow rental terms
        'monthly_rent'                 => 'Monthly Rent',
        'lease_term_months'            => 'Lease Term (months)',
        'security_deposit'             => 'Security Deposit',
        'last_month_rent_offered'      => 'Last Month Rent Offered',
        'move_in_date'                 => 'Move-In Date',
        'move_in_funds'                => 'Move-In Funds',
        'maintenance_responsibility'   => 'Maintenance Responsibility',
    ];

    $allowedKeys = app(\App\Services\Offers\PublicOfferFeedService::class)->allowedTermsFor($role);

    // Single shared formatter for every term cell. Storage conventions and the
    // $/% unit pairing live there, not in this template.
    $terms = app(\App\Presenters\OfferTermPresenter::class);

    // Only render columns at least one bid actually filled in; unit companions
    // ($ / %) fold into the value they qualify rather than taking a column.
    $activeKeys = $terms->columnKeys($allowedKeys, $bidFeed);

    // Finalized bids stay visible but must read as inactive. Only Submitted and
    // Countered are live; everything below them is history.
    //
    // 'Expired' means this bidder's own response deadline lapsed — it is NOT the
    // listing's bidding window, which has its own badge in the card header.
    $statusBadge = [
        'Submitted'      => 'bg-success',
        'Countered'      => 'bg-warning text-dark',
        'Accepted'       => 'bg-primary',
        'Expired'        => 'bg-secondary',
        'Rejected'       => 'bg-secondary',
        'Withdrawn'      => 'bg-secondary',
        'Closed'         => 'bg-secondary',
    ];

    $finalizedLabels = ['Expired', 'Rejected', 'Withdrawn', 'Closed'];
@endphp

<div class="card section-card" id="section-competing-bids">
    <div class="card-header">
        <i class="fa-solid fa-gavel me-2"></i>Competing Bids
        @if($biddingWindow->isBiddingPeriod && $biddingWindow->isClosed())
            <span class="badge bg-secondary ms-2" style="font-size:.75rem;">Bidding Closed</span>
        @endif
    </div>
    <div class="card-body">

        @if(!$canViewBidFeed)
            {{-- Guests and ineligible viewers: callout only, zero bid data. --}}
            <div class="text-center py-4">
                <i class="fa-solid fa-lock text-muted mb-2" style="font-size:1.5rem;"></i>
                <p class="text-muted mb-3" style="max-width:34rem;margin-inline:auto;">
                    Bids on this listing are visible to signed-in marketplace participants.
                    Bidder identities are never shown.
                </p>
                @guest
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-right-to-bracket me-1"></i>Log In to View Bids
                    </a>
                @else
                    <span class="text-muted" style="font-size:.85rem;">
                        Your account type is not eligible to view bids on this listing.
                    </span>
                @endguest
            </div>

        @elseif(empty($bidFeed))
            <p class="text-muted mb-0">No bids have been submitted on this listing yet.</p>

        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Bidder</th>
                            <th scope="col">Status</th>
                            <th scope="col">Submitted</th>
                            @foreach($activeKeys as $k)
                                <th scope="col">{{ $bidLabels[$k] ?? $k }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bidFeed as $bid)
                            @php $isFinalized = in_array($bid['status'], $finalizedLabels, true); @endphp
                            <tr @class(['sol-bid-finalized' => $isFinalized]) @if($isFinalized) style="opacity:.65;" @endif>
                                <th scope="row" class="fw-semibold">Bidder #{{ $bid['bidder_number'] }}</th>
                                <td>
                                    <span class="badge {{ $statusBadge[$bid['status']] ?? 'bg-secondary' }}">
                                        {{ $bid['status'] }}
                                    </span>
                                </td>
                                <td>
                                    @if($bid['submitted_at'])
                                        {{ $bid['submitted_at']->setTimezone(\App\Services\Offers\BiddingWindowService::DISPLAY_TIMEZONE)->format('M j, Y g:i A') }} ET
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                @foreach($activeKeys as $k)
                                    <td>{{ $terms->display($k, $bid['terms'] ?? []) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted mt-3 mb-0" style="font-size:.8rem;">
                Bidder numbers are stable for the life of this listing. Identities, contact
                details, and any information outside the terms shown are never disclosed.
            </p>
        @endif

    </div>
</div>
