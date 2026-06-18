@extends('layouts.main')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Offer Detail</h2>
                <button onclick="window.history.back()" class="btn btn-secondary btn-sm">Back</button>
            </div>

            @php
                $fmtDate = function ($v) {
                    if (!$v) return '—';
                    try { return \Carbon\Carbon::parse($v)->format('F j, Y'); }
                    catch (\Throwable $e) { return '—'; }
                };
                $fmtDateTime = function ($v) {
                    if (!$v) return '—';
                    try { return \Carbon\Carbon::parse($v)->format('F j, Y \a\t g:i A'); }
                    catch (\Throwable $e) { return '—'; }
                };
            @endphp

            {{-- Offer Summary Card --}}
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Offer Information</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Offer ID</dt>
                        <dd class="col-sm-9">{{ $offer->id }}</dd>

                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                            @php
                                $statusColors = [
                                    'draft'     => 'secondary',
                                    'submitted' => 'primary',
                                    'countered' => 'warning',
                                    'accepted'  => 'success',
                                    'rejected'  => 'danger',
                                    'withdrawn' => 'dark',
                                    'expired'   => 'secondary',
                                    'cancelled' => 'danger',
                                ];
                                $color = $statusColors[$offer->status] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $color }} text-capitalize">{{ $offer->status }}</span>
                        </dd>

                        @if($offer->parent_offer_id)
                        <dt class="col-sm-3">Parent Offer ID</dt>
                        <dd class="col-sm-9">{{ $offer->parent_offer_id }}</dd>
                        @endif

                        <dt class="col-sm-3">Created At</dt>
                        <dd class="col-sm-9">{{ $fmtDateTime($offer->created_at) }}</dd>

                        <dt class="col-sm-3">Submitted At</dt>
                        <dd class="col-sm-9">{{ $fmtDateTime($offer->submitted_at) }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                {{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            {{-- ── Historical Record Banner ─────────────────────────────────── --}}
            {{-- Shown when this offer is an earlier step in a completed chain.   --}}
            {{-- No hard redirect — the URL stays stable for audit/support use.   --}}
            @if($isHistorical && $terminalLeaf)
            @php
                $terminalStatusColors = [
                    'accepted'  => 'success',
                    'rejected'  => 'danger',
                    'withdrawn' => 'dark',
                    'expired'   => 'secondary',
                    'cancelled' => 'danger',
                ];
                $terminalBadgeColor = $terminalStatusColors[$terminalLeaf->status] ?? 'secondary';
            @endphp
            <div class="alert alert-warning border border-warning-subtle d-flex align-items-start gap-3 mb-4" role="alert" data-testid="historical-record-banner">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-archive flex-shrink-0 mt-1" viewBox="0 0 16 16"><path d="M0 2a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 1 12.5V5a1 1 0 0 1-1-1zm2 3v7.5A1.5 1.5 0 0 0 3.5 14h9a1.5 1.5 0 0 0 1.5-1.5V5zm13-3H1v2h14zM5 7.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5"/></svg>
                <div>
                    <strong>Viewing Historical Offer #{{ $offer->id }}</strong><br>
                    <span class="text-muted small">The terms shown below reflect the state of this offer at the time it was superseded — they are <em>not</em> the final agreed terms.</span><br>
                    <span class="mt-1 d-inline-block">
                        This offer is part of a completed negotiation. Latest outcome:
                        <span class="badge bg-{{ $terminalBadgeColor }} text-capitalize">{{ $terminalLeaf->status }}</span>
                        &nbsp;
                        <a href="{{ route('offers.show', $terminalLeaf) }}" class="alert-link fw-semibold">
                            View Final Outcome &rarr;
                        </a>
                    </span>
                </div>
            </div>
            @endif

            {{-- ── Active-state turn-taking banners (suppressed for terminal/historical) --}}
            @php
                $isSenderOfThisOffer = auth()->id() !== null && (int) auth()->id() === (int) $offer->user_id;
                $isActionableStatus  = in_array($offer->status, ['submitted', 'countered'], true);
            @endphp
            @if($isActionableStatus && !$isHistorical && !$isTerminal)
                @if($isSenderOfThisOffer)
                <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-hourglass-split flex-shrink-0" viewBox="0 0 16 16"><path d="M2.5 15a.5.5 0 1 1 0-1h1v-1a4.5 4.5 0 0 1 2.557-4.06c.29-.139.443-.377.443-.59v-.7c0-.213-.154-.451-.443-.59A4.5 4.5 0 0 1 3.5 3V2h-1a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-1v1a4.5 4.5 0 0 1-2.557 4.06c-.29.139-.443.377-.443.59v.7c0 .213.154.451.443.59A4.5 4.5 0 0 1 12.5 14v1h1a.5.5 0 0 1 0 1zm2-13v1a3.5 3.5 0 0 0 1.989 3.158c.533.256 1.011.791 1.011 1.342v.7c0 .551-.478 1.086-1.011 1.342A3.5 3.5 0 0 0 4.5 13v1h7v-1a3.5 3.5 0 0 0-1.989-3.158C9.978 9.586 9.5 9.051 9.5 8.5v-.7c0-.551.478-1.086 1.011-1.342A3.5 3.5 0 0 0 11.5 3V2z"/></svg>
                    <span><strong>Your counter offer is pending</strong> — waiting for the other party to respond.</span>
                </div>
                @else
                <div class="alert alert-primary d-flex align-items-center gap-2" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-bell flex-shrink-0" viewBox="0 0 16 16"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zm.995-14.901a1 1 0 1 0-1.99 0A5.002 5.002 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901z"/></svg>
                    <span><strong>This offer awaits your response.</strong> You may accept, reject, or counter.</span>
                </div>
                @endif
            @endif

            {{-- ── Terminal State UI ────────────────────────────────────────────── --}}
            {{-- Shown when this offer IS the terminal (final) leaf of the chain.   --}}
            @if($isTerminal && $terminalLeaf)
            @php
                $tLeafStatus = $terminalLeaf->status;

                $terminalBannerColors = [
                    'accepted'  => ['bg' => 'success',   'text' => 'text-white'],
                    'rejected'  => ['bg' => 'danger',    'text' => 'text-white'],
                    'withdrawn' => ['bg' => 'dark',      'text' => 'text-white'],
                    'expired'   => ['bg' => 'secondary', 'text' => 'text-white'],
                    'cancelled' => ['bg' => 'danger',    'text' => 'text-white'],
                ];
                $tBanner   = $terminalBannerColors[$tLeafStatus] ?? ['bg' => 'secondary', 'text' => 'text-white'];
                $tBannerBg = $tBanner['bg'];

                $terminalLabels = [
                    'accepted'  => 'Offer Accepted',
                    'rejected'  => 'Offer Rejected',
                    'withdrawn' => 'Offer Withdrawn',
                    'expired'   => 'Offer Expired',
                    'cancelled' => 'Offer Cancelled',
                ];
                $terminalLabel = $terminalLabels[$tLeafStatus] ?? ucfirst($tLeafStatus);

                $terminalOfferType = $finalTerms->get('offer_type') ?: $offerType;
                if (!in_array($terminalOfferType, ['sale', 'rental', 'lease'])) {
                    $terminalOfferType = $offerType;
                }
            @endphp

            {{-- Status Banner --}}
            <div class="card mb-4 border-{{ $tBannerBg }}">
                <div class="card-body bg-{{ $tBannerBg }} bg-opacity-10 d-flex align-items-center gap-3 py-4 rounded">
                    @if($tLeafStatus === 'accepted')
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-check-circle-fill text-success flex-shrink-0" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
                    @elseif($tLeafStatus === 'rejected')
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-x-circle-fill text-danger flex-shrink-0" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/></svg>
                    @elseif($tLeafStatus === 'withdrawn')
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-slash-circle-fill text-dark flex-shrink-0" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M4.646 4.646a.5.5 0 0 0 0 .708l6 6a.5.5 0 0 0 .708-.708l-6-6a.5.5 0 0 0-.708 0"/></svg>
                    @else
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-clock-fill text-secondary flex-shrink-0" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/></svg>
                    @endif
                    <div>
                        <h4 class="mb-1 fw-bold text-{{ $tBannerBg === 'secondary' ? 'muted' : $tBannerBg }}">{{ $terminalLabel }}</h4>
                        <p class="mb-0 text-muted" data-testid="terminal-outcome-timestamp">
                            {{ $fmtDateTime($terminalOutcomeAt) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Property Being Offered — terminal display (buyer/tenant role only) --}}
            @if(in_array($offer->role, ['buyer', 'tenant']))
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Property Being Offered</strong>
                </div>
                <div class="card-body">
                    @include('offers._property_being_offered_display', ['metas' => $rootMetas, 'offer' => $rootOffer])
                </div>
            </div>
            @endif

            {{-- Terms Snapshot Section (read-only for all four terminal outcomes) --}}
            @php
                $terminalHeadings = [
                    'accepted'  => 'Accepted Offer Terms',
                    'rejected'  => 'Rejected Offer Terms',
                    'withdrawn' => 'Withdrawn Offer Terms',
                    'expired'   => 'Expired Offer Terms',
                    'cancelled' => 'Cancelled Offer Terms',
                ];
                $terminalSectionHeading = $terminalHeadings[$tLeafStatus] ?? 'Offer Terms at Conclusion';
            @endphp
            <div class="card mb-4">
                <div class="card-header">
                    <strong>{{ $terminalSectionHeading }}</strong>
                </div>
                <div class="card-body">
                    @if($snapshotMissing)
                    <div class="alert alert-secondary mb-0" role="alert" data-testid="snapshot-unavailable-notice">
                        <strong>Terms not available.</strong>
                        No terms were recorded for this offer. This may occur for offers that were resolved before any terms were entered.
                    </div>
                    @else
                    @include('offers._offer_terms_display', ['metas' => $finalTerms, 'offerType' => $terminalOfferType])
                    @endif
                </div>
            </div>

            {{-- Negotiation Summary --}}
            <div class="card mb-4" id="offer-timeline">
                <div class="card-header">
                    <strong>Negotiation Summary</strong>
                </div>
                <div class="card-body p-0">
                    @if(count($chainSummary) > 0)
                    <ul class="list-group list-group-flush" data-testid="negotiation-summary">
                        @foreach($chainSummary as $step)
                        @php
                            $stepColor    = $statusColors[$step['status']] ?? 'secondary';
                            $isTerminalStep = isset($step['status']) && in_array($step['status'], ['accepted','rejected','withdrawn','expired','cancelled'], true)
                                && $terminalLeaf !== null && $step['offer_id'] === $terminalLeaf->id;
                        @endphp
                        <li class="list-group-item d-flex align-items-center gap-2 {{ $isTerminalStep ? 'list-group-item-' . $stepColor : '' }}"
                            @if($isTerminalStep) data-testid="terminal-summary-entry" @endif>
                            @if(!$loop->first)
                            <small class="text-muted me-1">↓</small>
                            @endif
                            <span class="fw-{{ $isTerminalStep ? 'bold' : 'normal' }}">
                                <a href="{{ route('offers.show', $step['offer_id']) }}" class="text-decoration-none">Offer #{{ $step['offer_id'] }}</a>
                                &mdash;
                                <span class="badge bg-{{ $stepColor }} text-capitalize">{{ $step['status'] }}</span>
                                &mdash;
                                <span class="text-muted" style="font-size:0.875rem;">{{ $step['created_at_formatted'] }}</span>
                            </span>
                        </li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-muted p-3 mb-0">No chain data available.</p>
                    @endif
                </div>
            </div>

            {{-- Terminal-state action buttons --}}
            <div class="card mb-4">
                <div class="card-header"><strong>Actions</strong></div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3">
                        <a href="{{ route('offers.pdf', $terminalLeaf) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                            Download PDF
                        </a>
                    </div>
                </div>
            </div>

            @else {{-- Not terminal: show the normal offer workspace (suppressed for historical) --}}

            {{-- Offer Terms Card --}}
            @php
                $offerStatus = $offer->status;
                $isOwner    = Auth::id() === $offer->user_id;
                $canEdit    = $isOwner && $offerStatus === 'draft';
                $safeDate   = function ($v) {
                    if (!$v) return '—';
                    try { return \Carbon\Carbon::parse($v)->format('Y-m-d'); }
                    catch (\Throwable $e) { return '—'; }
                };
            @endphp

            {{-- Property Being Offered (buyer/tenant role only) --}}
            @if(in_array($offer->role, ['buyer', 'tenant']))
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Property Being Offered</strong>
                    @if($canEdit && !$offer->parent_offer_id)
                        <span class="badge bg-success">Editable</span>
                    @else
                        <span class="badge bg-secondary">Locked</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($canEdit && !$offer->parent_offer_id)
                        @include('offers._property_being_offered_form', ['offer' => $offer, 'metas' => $metas])
                    @else
                        @include('offers._property_being_offered_display', ['metas' => $rootMetas, 'offer' => $rootOffer])
                    @endif
                </div>
            </div>
            @endif

            {{-- ── Buyer Criteria Summary (only when offer is linked to a Buyer Criteria listing) --}}
            @if($offer->role === 'buyer' && $buyerCriteriaAuction)
            @php
                $bcType        = $buyerCriteriaMetas->get('property_type');
                $bcStyles      = $buyerCriteriaMetas->get('propertyStyles');
                $bcMaxPrice    = $buyerCriteriaMetas->get('max_price') ?: $buyerCriteriaMetas->get('buyerBudget') ?: $buyerCriteriaMetas->get('maximum_budget');
                $bcBeds        = $buyerCriteriaMetas->get('bedrooms') ?: $buyerCriteriaMetas->get('custom_bedrooms');
                $bcBaths       = $buyerCriteriaMetas->get('bathroomsRes') ?: $buyerCriteriaMetas->get('bathrooms') ?: $buyerCriteriaMetas->get('custom_bathroomsRes');
                $bcMinSqft     = $buyerCriteriaMetas->get('minimum_heated_square') ?: $buyerCriteriaMetas->get('min_sqftRes') ?: $buyerCriteriaMetas->get('min_sqft');
                $bcCitiesRaw   = $buyerCriteriaMetas->get('cities');
                $bcCities      = $bcCitiesRaw ? implode(', ', array_filter((array) json_decode($bcCitiesRaw, true))) : null;
                $bcCountiesRaw = $buyerCriteriaMetas->get('counties');
                $bcCounties    = $bcCountiesRaw ? implode(', ', array_filter((array) json_decode($bcCountiesRaw, true))) : null;
                $bcState       = $buyerCriteriaMetas->get('state') ?: $buyerCriteriaMetas->get('states');
                $bcFinRaw      = $buyerCriteriaMetas->get('offered_financing') ?: $buyerCriteriaMetas->get('financings');
                $bcFinancings  = $bcFinRaw ? implode(', ', array_filter((array) json_decode($bcFinRaw, true))) : null;
                $bcTitle       = $buyerCriteriaMetas->get('titleListing') ?: ($buyerCriteriaAuction->title ?? ('Buyer Criteria #' . $buyerCriteriaAuction->id));
                $bcFmtMoney    = fn($v) => $v ? '$' . number_format((float) str_replace([',','$'], '', $v)) : null;
            @endphp
            <div class="card mb-4 border-primary border-opacity-25">
                <div class="card-header bg-primary bg-opacity-10 d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-check-fill text-primary flex-shrink-0" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 0 1 .708-.708L12.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/><path d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/></svg>
                    <strong class="text-primary" style="font-size:0.9rem;">Buyer Criteria Summary</strong>
                </div>
                <div class="card-body py-3">
                    <p class="text-muted mb-2" style="font-size:0.82rem;">Linked listing: <em>{{ $bcTitle }}</em></p>
                    <dl class="row mb-0" style="font-size:0.9rem;">
                        @if($bcType)
                        <dt class="col-sm-4 fw-semibold">Property Type</dt>
                        <dd class="col-sm-8">{{ $bcType }}{{ $bcStyles ? ' — ' . $bcStyles : '' }}</dd>
                        @endif
                        @if($bcMaxPrice)
                        <dt class="col-sm-4 fw-semibold">Max Budget</dt>
                        <dd class="col-sm-8">{{ $bcFmtMoney($bcMaxPrice) ?? $bcMaxPrice }}</dd>
                        @endif
                        @if($bcBeds)
                        <dt class="col-sm-4 fw-semibold">Bedrooms</dt>
                        <dd class="col-sm-8">{{ $bcBeds }}+</dd>
                        @endif
                        @if($bcBaths)
                        <dt class="col-sm-4 fw-semibold">Bathrooms</dt>
                        <dd class="col-sm-8">{{ $bcBaths }}+</dd>
                        @endif
                        @if($bcMinSqft)
                        <dt class="col-sm-4 fw-semibold">Min Sq Ft</dt>
                        <dd class="col-sm-8">{{ number_format((float) str_replace(',', '', $bcMinSqft)) }} sq ft</dd>
                        @endif
                        @if($bcCities)
                        <dt class="col-sm-4 fw-semibold">Cities</dt>
                        <dd class="col-sm-8">{{ $bcCities }}</dd>
                        @endif
                        @if($bcCounties)
                        <dt class="col-sm-4 fw-semibold">Counties</dt>
                        <dd class="col-sm-8">{{ $bcCounties }}</dd>
                        @endif
                        @if($bcState)
                        <dt class="col-sm-4 fw-semibold">State</dt>
                        <dd class="col-sm-8">{{ $bcState }}</dd>
                        @endif
                        @if($bcFinancings)
                        <dt class="col-sm-4 fw-semibold">Financing</dt>
                        <dd class="col-sm-8">{{ $bcFinancings }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
            @endif

            {{-- ── Location DNA Compatibility (buyer criteria) --}}
            @if($offer->role === 'buyer' && $buyerCriteriaAuction)
            @if($locationDna)
            @php
                $dnaJson       = $locationDna->summary_json ?? [];
                $dnaLifestyle  = $locationDna->lifestyle_json ?? [];
                $dnaCity       = $locationDna->source_city;
                $dnaState      = $locationDna->source_state;
                $dnaZip        = $locationDna->source_zip;
                $dnaCounty     = $locationDna->source_county;
                $dnaStatus     = $locationDna->geocode_status;
                $dnaPrefCities = data_get($dnaJson, 'preferred_cities', []);
                $dnaPrefZips   = data_get($dnaJson, 'preferred_zips', []);
                $dnaPrefNeigh  = data_get($dnaJson, 'preferred_neighborhoods', []);
                $dnaFlexible   = data_get($dnaJson, 'flexible_location');
                $dnaRadius     = data_get($dnaJson, 'radius_miles');
                $dnaNote       = data_get($dnaJson, 'location_notes');
            @endphp
            <div class="card mb-4 border-success border-opacity-25">
                <div class="card-header bg-success bg-opacity-10 d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill text-success flex-shrink-0" viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg>
                    <strong class="text-success" style="font-size:0.9rem;">Location DNA Compatibility</strong>
                </div>
                <div class="card-body py-3">
                    @if($dnaCity || $dnaState || $dnaZip)
                    <p class="text-muted mb-2" style="font-size:0.82rem;">
                        Source address:
                        {{ implode(', ', array_filter([$dnaCity, $dnaState, $dnaZip])) }}
                        @if($dnaStatus === 'ok') <span class="badge bg-success bg-opacity-25 text-success ms-1" style="font-size:0.7rem;">Geocoded</span> @endif
                    </p>
                    @endif
                    <dl class="row mb-0" style="font-size:0.9rem;">
                        @if(count($dnaPrefCities) > 0)
                        <dt class="col-sm-4 fw-semibold">Preferred Cities</dt>
                        <dd class="col-sm-8">{{ implode(', ', $dnaPrefCities) }}</dd>
                        @endif
                        @if(count($dnaPrefZips) > 0)
                        <dt class="col-sm-4 fw-semibold">Preferred ZIPs</dt>
                        <dd class="col-sm-8">{{ implode(', ', $dnaPrefZips) }}</dd>
                        @endif
                        @if(count($dnaPrefNeigh) > 0)
                        <dt class="col-sm-4 fw-semibold">Neighborhoods</dt>
                        <dd class="col-sm-8">{{ implode(', ', $dnaPrefNeigh) }}</dd>
                        @endif
                        @if($dnaRadius !== null)
                        <dt class="col-sm-4 fw-semibold">Search Radius</dt>
                        <dd class="col-sm-8">{{ $dnaRadius }} miles</dd>
                        @endif
                        @if($dnaFlexible !== null)
                        <dt class="col-sm-4 fw-semibold">Flexible Location</dt>
                        <dd class="col-sm-8">{{ $dnaFlexible ? 'Yes' : 'No' }}</dd>
                        @endif
                        @if($dnaNote)
                        <dt class="col-sm-4 fw-semibold">Notes</dt>
                        <dd class="col-sm-8">{{ $dnaNote }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
            @else
            <div class="card mb-4 border-success border-opacity-25">
                <div class="card-header bg-success bg-opacity-10 d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill text-success flex-shrink-0" viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg>
                    <strong class="text-success" style="font-size:0.9rem;">Location DNA Compatibility</strong>
                </div>
                <div class="card-body py-3">
                    <p class="text-muted mb-0" style="font-size:0.9rem;">No Location DNA preferences were provided.</p>
                </div>
            </div>
            @endif
            @endif

            {{-- ── Tenant Criteria Summary (only when offer is linked to a Tenant Criteria listing) --}}
            @if($offer->role === 'tenant' && $tenantCriteriaAuction)
            @php
                $tcType       = $tenantCriteriaMetas->get('property_type');
                $tcMaxRent    = $tenantCriteriaMetas->get('max_rent') ?: $tenantCriteriaMetas->get('rental_budget') ?: $tenantCriteriaMetas->get('maximum_budget');
                $tcBeds       = $tenantCriteriaMetas->get('bedrooms') ?: $tenantCriteriaMetas->get('custom_bedrooms');
                $tcBaths      = $tenantCriteriaMetas->get('bathroomsRes') ?: $tenantCriteriaMetas->get('bathrooms') ?: $tenantCriteriaMetas->get('custom_bathroomsRes');
                $_tcLeaseRaw  = $tenantCriteriaMetas->get('desired_lease_length') ?: $tenantCriteriaMetas->get('desired_lease_term');
                $_tcLeaseArr  = is_string($_tcLeaseRaw) ? json_decode($_tcLeaseRaw, true) : null;
                $tcLease      = is_array($_tcLeaseArr) ? implode(', ', array_filter($_tcLeaseArr)) : $_tcLeaseRaw;
                $tcCitiesRaw  = $tenantCriteriaMetas->get('cities');
                $tcCities     = $tcCitiesRaw ? implode(', ', array_filter((array) json_decode($tcCitiesRaw, true))) : null;
                $tcCountiesRaw = $tenantCriteriaMetas->get('counties');
                $tcCounties   = $tcCountiesRaw ? implode(', ', array_filter((array) json_decode($tcCountiesRaw, true))) : null;
                $tcState      = $tenantCriteriaMetas->get('state') ?: $tenantCriteriaMetas->get('states');
                $tcTitle      = $tenantCriteriaMetas->get('titleListing') ?: ($tenantCriteriaAuction->title ?? ('Tenant Criteria #' . $tenantCriteriaAuction->id));
                $tcFmtMoney   = fn($v) => $v ? '$' . number_format((float) str_replace([',','$'], '', $v)) : null;
            @endphp
            <div class="card mb-4 border-primary border-opacity-25">
                <div class="card-header bg-primary bg-opacity-10 d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house-check-fill text-primary flex-shrink-0" viewBox="0 0 16 16"><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.707l.547.547 1.17-1.951a.5.5 0 1 1 .858.514"/><path d="M8.707 1.5l.001.002.001.002 5.388 5.17A1.5 1.5 0 0 0 14 5.5v-.009l-.001-.004A1.5 1.5 0 0 0 12.5 4h-1V2.5A1.5 1.5 0 0 0 10 1H6a1.5 1.5 0 0 0-1.5 1.5V4H3.5A1.5 1.5 0 0 0 2 5.5v8A1.5 1.5 0 0 0 3.5 15H9.05a4.5 4.5 0 0 1-.05-.5V14H3.5V5.5l4.5-4.5z"/></svg>
                    <strong class="text-primary" style="font-size:0.9rem;">Tenant Criteria Summary</strong>
                </div>
                <div class="card-body py-3">
                    <p class="text-muted mb-2" style="font-size:0.82rem;">Linked listing: <em>{{ $tcTitle }}</em></p>
                    <dl class="row mb-0" style="font-size:0.9rem;">
                        @if($tcType)
                        <dt class="col-sm-4 fw-semibold">Property Type</dt>
                        <dd class="col-sm-8">{{ $tcType }}</dd>
                        @endif
                        @if($tcMaxRent)
                        <dt class="col-sm-4 fw-semibold">Max Monthly Rent</dt>
                        <dd class="col-sm-8">{{ $tcFmtMoney($tcMaxRent) ?? $tcMaxRent }}</dd>
                        @endif
                        @if($tcBeds)
                        <dt class="col-sm-4 fw-semibold">Bedrooms</dt>
                        <dd class="col-sm-8">{{ $tcBeds }}+</dd>
                        @endif
                        @if($tcBaths)
                        <dt class="col-sm-4 fw-semibold">Bathrooms</dt>
                        <dd class="col-sm-8">{{ $tcBaths }}+</dd>
                        @endif
                        @if($tcLease)
                        <dt class="col-sm-4 fw-semibold">Desired Lease Length</dt>
                        <dd class="col-sm-8">{{ $tcLease }}</dd>
                        @endif
                        @if($tcCities)
                        <dt class="col-sm-4 fw-semibold">Cities</dt>
                        <dd class="col-sm-8">{{ $tcCities }}</dd>
                        @endif
                        @if($tcCounties)
                        <dt class="col-sm-4 fw-semibold">Counties</dt>
                        <dd class="col-sm-8">{{ $tcCounties }}</dd>
                        @endif
                        @if($tcState)
                        <dt class="col-sm-4 fw-semibold">State</dt>
                        <dd class="col-sm-8">{{ $tcState }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
            @endif

            {{-- ── Location DNA Compatibility (tenant criteria) --}}
            @if($offer->role === 'tenant' && $tenantCriteriaAuction)
            @if($tenantLocationDna)
            @php
                $tcDnaJson       = $tenantLocationDna->summary_json ?? [];
                $tcDnaCity       = $tenantLocationDna->source_city;
                $tcDnaState      = $tenantLocationDna->source_state;
                $tcDnaZip        = $tenantLocationDna->source_zip;
                $tcDnaStatus     = $tenantLocationDna->geocode_status;
                $tcDnaPrefCities = data_get($tcDnaJson, 'preferred_cities', []);
                $tcDnaPrefZips   = data_get($tcDnaJson, 'preferred_zips', []);
                $tcDnaPrefNeigh  = data_get($tcDnaJson, 'preferred_neighborhoods', []);
                $tcDnaFlexible   = data_get($tcDnaJson, 'flexible_location');
                $tcDnaRadius     = data_get($tcDnaJson, 'radius_miles');
                $tcDnaNote       = data_get($tcDnaJson, 'location_notes');
            @endphp
            <div class="card mb-4 border-success border-opacity-25">
                <div class="card-header bg-success bg-opacity-10 d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill text-success flex-shrink-0" viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg>
                    <strong class="text-success" style="font-size:0.9rem;">Location DNA Compatibility</strong>
                </div>
                <div class="card-body py-3">
                    @if($tcDnaCity || $tcDnaState || $tcDnaZip)
                    <p class="text-muted mb-2" style="font-size:0.82rem;">
                        Source address:
                        {{ implode(', ', array_filter([$tcDnaCity, $tcDnaState, $tcDnaZip])) }}
                        @if($tcDnaStatus === 'ok') <span class="badge bg-success bg-opacity-25 text-success ms-1" style="font-size:0.7rem;">Geocoded</span> @endif
                    </p>
                    @endif
                    <dl class="row mb-0" style="font-size:0.9rem;">
                        @if(count($tcDnaPrefCities) > 0)
                        <dt class="col-sm-4 fw-semibold">Preferred Cities</dt>
                        <dd class="col-sm-8">{{ implode(', ', $tcDnaPrefCities) }}</dd>
                        @endif
                        @if(count($tcDnaPrefZips) > 0)
                        <dt class="col-sm-4 fw-semibold">Preferred ZIPs</dt>
                        <dd class="col-sm-8">{{ implode(', ', $tcDnaPrefZips) }}</dd>
                        @endif
                        @if(count($tcDnaPrefNeigh) > 0)
                        <dt class="col-sm-4 fw-semibold">Neighborhoods</dt>
                        <dd class="col-sm-8">{{ implode(', ', $tcDnaPrefNeigh) }}</dd>
                        @endif
                        @if($tcDnaRadius !== null)
                        <dt class="col-sm-4 fw-semibold">Search Radius</dt>
                        <dd class="col-sm-8">{{ $tcDnaRadius }} miles</dd>
                        @endif
                        @if($tcDnaFlexible !== null)
                        <dt class="col-sm-4 fw-semibold">Flexible Location</dt>
                        <dd class="col-sm-8">{{ $tcDnaFlexible ? 'Yes' : 'No' }}</dd>
                        @endif
                        @if($tcDnaNote)
                        <dt class="col-sm-4 fw-semibold">Notes</dt>
                        <dd class="col-sm-8">{{ $tcDnaNote }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
            @else
            <div class="card mb-4 border-success border-opacity-25">
                <div class="card-header bg-success bg-opacity-10 d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill text-success flex-shrink-0" viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg>
                    <strong class="text-success" style="font-size:0.9rem;">Location DNA Compatibility</strong>
                </div>
                <div class="card-body py-3">
                    <p class="text-muted mb-0" style="font-size:0.9rem;">No Location DNA preferences were provided.</p>
                </div>
            </div>
            @endif
            @endif

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Offer Terms</strong>
                    @if($canEdit)
                        <span class="badge bg-success">Editable</span>
                    @else
                        <span class="badge bg-secondary">Locked</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($canEdit)
                    @include('offers._offer_terms_form', ['mode' => 'draft_terms', 'formData' => $counterDefaults])
                    @else
                    {{-- Read-only display --}}
                    @include('offers._offer_terms_display', ['metas' => $metas, 'offerType' => $offerType])
                    @endif
                </div>
            </div>

            {{-- Negotiation Timeline --}}
            <div class="card mb-4" id="offer-timeline">
                <div class="card-header">
                    <strong>Negotiation Timeline</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Offer ID</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Submitted At</th>
                                    <th>Event Count</th>
                                    <th>Latest Event Type</th>
                                    <th>Latest Event At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($timeline as $item)
                                <tr @class([
                                    'table-active' => $item['offer_id'] === $offer->id,
                                    'fw-bold'      => !empty($item['is_terminal']),
                                ])>
                                    <td>
                                        {{ $item['offer_id'] }}
                                        @if(!empty($item['is_terminal']))
                                        <span class="badge bg-{{ $statusColors[$item['status']] ?? 'secondary' }} ms-1" style="font-size:0.7rem;">final</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $tColor = $statusColors[$item['status']] ?? 'secondary'; @endphp
                                        <span class="badge bg-{{ $tColor }} text-capitalize">{{ $item['status'] }}</span>
                                    </td>
                                    <td>{{ $fmtDateTime($item['created_at'] ?? null) }}</td>
                                    <td>{{ $fmtDateTime($item['submitted_at'] ?? null) }}</td>
                                    <td>{{ $item['event_count'] }}</td>
                                    <td>{{ $item['latest_event_type'] ?? '—' }}</td>
                                    <td>{{ $fmtDateTime($item['latest_event_at'] ?? null) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No timeline data available.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Available Actions — hidden for historical offers and for terminal offers --}}
            {{-- can_expire is intentionally not shown. Submit/Accept/Reject/Withdraw POST via named routes when enabled. --}}
            {{-- Counter has dedicated three-branch logic below the shared loop. Disabled actions render as bare disabled buttons. --}}
            @if(!$isHistorical)
            <style>
                #submit-offer-action-btn { background:#2563eb; border-color:#2563eb; color:#fff; font-weight:600; }
                #submit-offer-action-btn:hover { background:#1d4ed8; border-color:#1d4ed8; }
                #submit-offer-action-btn:disabled { background:#93c5fd; border-color:#93c5fd; color:#fff; }
                #accept-offer-action-btn { background:#198754; border-color:#198754; color:#fff; font-weight:600; }
                #accept-offer-action-btn:hover { background:#157347; border-color:#146c43; color:#fff; }
                #accept-offer-action-btn:disabled { background:#198754; border-color:#198754; color:#fff; opacity:.55; }
                #reject-offer-action-btn { background:#dc3545; border-color:#dc3545; color:#fff; font-weight:600; }
                #reject-offer-action-btn:hover { background:#bb2d3b; border-color:#b02a37; color:#fff; }
                #reject-offer-action-btn:disabled { background:#dc3545; border-color:#dc3545; color:#fff; opacity:.55; }
                #withdraw-offer-action-btn { background:transparent; border-color:#6c757d; color:#6c757d; font-weight:600; }
                #withdraw-offer-action-btn:hover { background:#6c757d; color:#fff; }
                #withdraw-offer-action-btn:disabled { background:transparent; color:#6c757d; border-color:#6c757d; opacity:.55; }
                #counter-offer-action-btn { background:#ffc107; border-color:#ffc107; color:#212529; font-weight:600; }
                #counter-offer-action-btn:hover { background:#ffca2c; border-color:#ffc720; color:#212529; }
                .btn:disabled, .btn[disabled], .btn[aria-disabled="true"] { opacity:.55; cursor:not-allowed; }
                .btn-success:disabled  { background-color:#198754; border-color:#198754; color:#fff; }
                .btn-danger:disabled   { background-color:#dc3545; border-color:#dc3545; color:#fff; }
                .btn-warning:disabled  { background-color:#ffc107; border-color:#ffc107; color:#212529; }
                .btn-outline-secondary:disabled { background-color:transparent; color:#6c757d; border-color:#6c757d; }
            </style>
            @if($offer->status !== 'draft')
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Available Actions</strong>
                </div>
                <div class="card-body">
                    @php
                        $actionButtons = [
                            'can_accept'   => ['label' => 'Accept',   'btn' => 'btn-success',           'reason_key' => 'accept',   'route' => 'offers.accept',   'hide_for_submitter' => true],
                            'can_reject'   => ['label' => 'Reject',   'btn' => 'btn-danger',            'reason_key' => 'reject',   'route' => 'offers.reject',   'hide_for_submitter' => true],
                            'can_withdraw' => ['label' => 'Withdraw', 'btn' => 'btn-outline-secondary', 'reason_key' => 'withdraw', 'route' => 'offers.withdraw', 'hide_for_submitter' => false],
                        ];
                        $counterReason    = $actions['reasons']['counter'] ?? '';
                        $actorIsSubmitter = auth()->id() !== null && (int) auth()->id() === (int) $offer->user_id;
                    @endphp
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        @foreach($actionButtons as $flag => $cfg)
                            @php
                                $allowed = !empty($actions[$flag]);
                                $reason  = $allowed ? '' : ($actions['reasons'][$cfg['reason_key']] ?? '');
                            @endphp
                            {{-- Accept and Reject are hidden entirely for the submitter — they belong to the other party. --}}
                            @if($actorIsSubmitter && $cfg['hide_for_submitter'])
                                @continue
                            @endif
                            {{-- can_submit=false is silently hidden — no disabled button, no reason text. --}}
                            @if(!$allowed && $flag === 'can_submit')
                                @continue
                            @endif
                            {{-- Disabled / not-permitted: render a disabled button with the reason if one is provided. --}}
                            @if(!$allowed)
                                @if($reason)
                                    <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                        <button type="button" class="btn {{ $cfg['btn'] }} btn-sm" disabled>{{ $cfg['label'] }}</button>
                                        <small class="text-muted mt-1">{{ $reason }}</small>
                                    </div>
                                @endif
                                @continue
                            @endif
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                <form method="POST" action="{{ route($cfg['route'], $offer) }}">
                                    @csrf
                                    <button type="submit" class="btn {{ $cfg['btn'] }} btn-sm"
                                        @if($flag === 'can_submit')    id="submit-offer-action-btn"
                                        @elseif($flag === 'can_accept')  id="accept-offer-action-btn"
                                        @elseif($flag === 'can_reject')  id="reject-offer-action-btn"
                                        @elseif($flag === 'can_withdraw') id="withdraw-offer-action-btn"
                                        @endif>{{ $cfg['label'] }}</button>
                                </form>
                            </div>
                        @endforeach

                        {{-- Counter: hidden entirely for the submitter. --}}
                        @if(!$actorIsSubmitter)
                            @if(!empty($actions['can_counter']))
                                {{-- can_counter=true: Counter button in actions row; form revealed on click --}}
                                <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                    <button type="button" class="btn btn-warning btn-sm" id="counter-offer-action-btn"
                                        onclick="document.getElementById('counter-form-panel').style.display='block';this.style.display='none';">Counter</button>
                                </div>
                                <div id="counter-form-panel" class="w-100 mt-3" style="display:none;">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning bg-opacity-10 fw-semibold">Submit Counter Offer</div>
                                        <div class="card-body">
                                            {{-- Read-only pre-screening context from the root (original) application --}}
                                            @if(in_array($offerType, ['rental', 'lease']))
                                            @php
                                                $_rsm = $rootMetas;
                                                $_hasScreening = $_rsm->get('num_occupants') || $_rsm->get('has_pets') ||
                                                    $_rsm->get('pet_details') || $_rsm->get('smoking_preference') ||
                                                    $_rsm->get('monthly_income') || $_rsm->get('credit_score_range') ||
                                                    $_rsm->get('screening_notes') || $_rsm->get('screening_concerns') ||
                                                    $_rsm->get('screening_concerns_details') || $_rsm->get('message_to_landlord');
                                                $_fmtMoneyRo = fn($v) => $v ? number_format((float) str_replace(',', '', $v)) : '—';
                                            @endphp
                                            @if($_hasScreening)
                                            <div class="card bg-light border mb-4">
                                                <div class="card-header fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;">
                                                    Original Application — Applicant Information
                                                </div>
                                                <div class="card-body py-2">
                                                    <dl class="row mb-0" style="font-size:0.9rem;">
                                                        @if($_rsm->get('num_occupants'))
                                                        <dt class="col-sm-4">Number of Occupants</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('num_occupants') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('has_pets'))
                                                        <dt class="col-sm-4">Pets</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('has_pets') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('pet_details'))
                                                        <dt class="col-sm-4">Pet Details</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('pet_details') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('smoking_preference'))
                                                        <dt class="col-sm-4">Smoking</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('smoking_preference') === 'No' ? 'Non-smoker' : 'Smoker' }}</dd>
                                                        @endif
                                                        @if($_rsm->get('monthly_income'))
                                                        <dt class="col-sm-4">Est. Monthly Income</dt>
                                                        <dd class="col-sm-8">${{ $_fmtMoneyRo($_rsm->get('monthly_income')) }}</dd>
                                                        @endif
                                                        @if($_rsm->get('credit_score_range'))
                                                        <dt class="col-sm-4">Credit Score Range</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('credit_score_range') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('screening_concerns'))
                                                        <dt class="col-sm-4">Rental History Disclosure</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('screening_concerns') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('screening_concerns') === 'Yes' && $_rsm->get('screening_concerns_details'))
                                                        <dt class="col-sm-4">Disclosure Details</dt>
                                                        <dd class="col-sm-8" style="white-space:pre-wrap;">{{ $_rsm->get('screening_concerns_details') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('screening_notes'))
                                                        <dt class="col-sm-4">About Applicant</dt>
                                                        <dd class="col-sm-8" style="white-space:pre-wrap;">{{ $_rsm->get('screening_notes') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('message_to_landlord'))
                                                        <dt class="col-sm-4">Message to Landlord</dt>
                                                        <dd class="col-sm-8" style="white-space:pre-wrap;">{{ $_rsm->get('message_to_landlord') }}</dd>
                                                        @endif
                                                    </dl>
                                                </div>
                                            </div>
                                            @endif
                                            @endif
                                            {{-- Read-only property context from the root offer (buyer/tenant role) --}}
                                            @if(in_array($offer->role, ['buyer', 'tenant']))
                                            @php
                                                $_hasProp = $rootMetas->get('prop_type') || $rootMetas->get('prop_street') || $rootMetas->get('prop_mls_number');
                                            @endphp
                                            @if($_hasProp)
                                            <div class="card bg-light border mb-4">
                                                <div class="card-header fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;">
                                                    Original Offer — Property Being Offered
                                                </div>
                                                <div class="card-body py-2" style="font-size:0.9rem;">
                                                    @include('offers._property_being_offered_display', ['metas' => $rootMetas, 'offer' => $rootOffer])
                                                </div>
                                            </div>
                                            @endif
                                            @endif
                                            @include('offers._offer_terms_form', ['mode' => 'counter_terms', 'formData' => $counterDefaults])
                                        </div>
                                    </div>
                                </div>
                            @elseif($counterReason)
                                {{-- can_counter=false with a reason: disabled button so the user knows why --}}
                                <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                    <button type="button" class="btn btn-warning btn-sm" disabled>Counter</button>
                                    <small class="text-muted mt-1">{{ $counterReason }}</small>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            @endif
            @endif {{-- end !$isHistorical --}}

            @endif {{-- end @else (not terminal) --}}

        </div>
    </div>
</div>
@endsection
