@extends('layouts.main')
@section('content')
<div class="mainDashboard">
    <div class="container">
        <div class="dashboardContentDetails mt-3">
            <div class="card">
                <div class="row">
                    @include('layouts.partials.sidenav')
                    <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                        <div class="container mt-4 mb-5">

                            <div class="d-flex align-items-center gap-3 mb-4">
                                <a href="{{ route('agent.hire-leads.index') }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Leads
                                </a>
                                <span class="badge rounded-pill {{ $lead->statusBadgeClass() }} px-3"
                                      style="font-size:.72rem;">{{ $lead->statusLabel() }}</span>
                            </div>

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show py-2 mb-3" style="font-size:.85rem;">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            {{-- ── Main lead card ─────────────────────────────── --}}
                            <div class="card border-0 rounded-3 mb-4" style="box-shadow:0 2px 12px rgba(0,0,0,.09);">
                                <div class="card-header border-0 rounded-top-3 py-3 px-4"
                                     style="background:linear-gradient(135deg,#0f766e,#0369a1);color:#fff;">
                                    <h5 class="fw-bold mb-0" style="font-size:1rem;">
                                        <i class="fa-solid fa-user-tie me-2"></i>Lead Details
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">

                                        {{-- Contact --}}
                                        <div class="col-12">
                                            <div class="small text-uppercase text-muted fw-bold mb-2"
                                                 style="letter-spacing:.06em;font-size:.7rem;">Contact</div>
                                            <div class="fw-bold mb-1" style="font-size:1rem;color:#1e293b;">{{ $lead->requester_name }}</div>
                                            <div style="font-size:.84rem;color:#475569;">
                                                <a href="mailto:{{ $lead->requester_email }}" style="color:inherit;">
                                                    <i class="fa-solid fa-envelope me-1 opacity-50"></i>{{ $lead->requester_email }}
                                                </a>
                                            </div>
                                            @if($lead->requester_phone)
                                            <div style="font-size:.84rem;color:#475569;margin-top:2px;">
                                                <a href="tel:{{ $lead->requester_phone }}" style="color:inherit;">
                                                    <i class="fa-solid fa-phone me-1 opacity-50"></i>{{ $lead->requester_phone }}
                                                </a>
                                            </div>
                                            @endif
                                        </div>

                                        <div class="col-12"><hr class="my-0"></div>

                                        {{-- Representation + property type requested --}}
                                        <div class="col-md-6">
                                            <div class="small text-uppercase text-muted fw-bold mb-1"
                                                 style="letter-spacing:.06em;font-size:.7rem;">Representation Requested</div>
                                            <div class="fw-semibold" style="font-size:.9rem;color:#1e293b;">
                                                {{ $lead->representationTypeLabel() }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="small text-uppercase text-muted fw-bold mb-1"
                                                 style="letter-spacing:.06em;font-size:.7rem;">Property Type Selected</div>
                                            <div class="fw-semibold" style="font-size:.9rem;color:#1e293b;">
                                                {{ $lead->selectedPropertyTypeLabel() }}
                                            </div>
                                        </div>

                                        {{-- Source listing attribution --}}
                                        <div class="col-12">
                                            <div class="small text-uppercase text-muted fw-bold mb-1"
                                                 style="letter-spacing:.06em;font-size:.7rem;">Source Listing</div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-light text-dark border" style="font-size:.72rem;">
                                                    {{ ucfirst($lead->source_listing_role ?? '') }}
                                                </span>
                                                <span style="font-size:.85rem;color:#475569;">
                                                    {{ $lead->sourceListingTypeLabel() }}
                                                    <span style="font-family:monospace;font-size:.72rem;color:#94a3b8;">#{{ $lead->source_listing_id }}</span>
                                                </span>
                                                @if($lead->source_listing_title)
                                                    <span style="font-size:.82rem;color:#334155;">— {{ $lead->source_listing_title }}</span>
                                                @endif
                                                @php $listingUrl = $lead->resolvedListingUrl(); @endphp
                                                @if($listingUrl)
                                                    <a href="{{ $listingUrl }}" target="_blank"
                                                       class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                       style="font-size:.75rem;">
                                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Listing
                                                    </a>
                                                @endif
                                            </div>
                                            @if($lead->source_property_type)
                                            <div style="font-size:.76rem;color:#94a3b8;margin-top:3px;">
                                                Listing property type: {{ \App\Models\HireAgentLead::propertyLabel($lead->source_property_type) }}
                                            </div>
                                            @endif
                                        </div>

                                        {{-- Lead source + meta --}}
                                        <div class="col-md-6">
                                            <div class="small text-uppercase text-muted fw-bold mb-1"
                                                 style="letter-spacing:.06em;font-size:.7rem;">Lead Source</div>
                                            <div style="font-size:.85rem;color:#475569;">
                                                {{ ucwords(str_replace('_', ' ', $lead->lead_source ?? 'offer_listing')) }}
                                            </div>
                                        </div>

                                        {{-- Preset match context --}}
                                        <div class="col-md-6">
                                            <div class="small text-uppercase text-muted fw-bold mb-1"
                                                 style="letter-spacing:.06em;font-size:.7rem;">Preset Match</div>
                                            <div class="d-flex align-items-center gap-2">
                                                @if($lead->preset_match_status === 'matched')
                                                    <span class="badge bg-success" style="font-size:.68rem;">Matched</span>
                                                @elseif($lead->preset_match_status === 'multiple_matches')
                                                    <span class="badge bg-primary" style="font-size:.68rem;">Multiple</span>
                                                @else
                                                    <span class="badge bg-secondary" style="font-size:.68rem;">No match</span>
                                                @endif
                                                <span style="font-size:.82rem;color:#64748b;">{{ $lead->presetMatchStatusLabel() }}</span>
                                            </div>
                                            @if($lead->matchedPreset)
                                                <div style="font-size:.76rem;color:#94a3b8;margin-top:3px;">
                                                    Preset: {{ $lead->matchedPresetTitle() }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="col-12">
                                            <div class="small text-uppercase text-muted fw-bold mb-1"
                                                 style="letter-spacing:.06em;font-size:.7rem;">Received</div>
                                            <div style="font-size:.85rem;color:#475569;">
                                                {{ $lead->created_at->format('M j, Y g:i A') }}
                                                <span class="text-muted">({{ $lead->created_at->diffForHumans() }})</span>
                                            </div>
                                        </div>

                                        @if($lead->message)
                                        <div class="col-12">
                                            <div class="small text-uppercase text-muted fw-bold mb-1"
                                                 style="letter-spacing:.06em;font-size:.7rem;">Message from Requester</div>
                                            <div class="p-3 rounded-3" style="background:#f8fafc;font-size:.87rem;color:#334155;line-height:1.6;border:1px solid #e2e8f0;white-space:pre-line;">{{ $lead->message }}</div>
                                        </div>
                                        @endif

                                    </div>

                                    {{-- Action buttons --}}
                                    @if(! in_array($lead->status, ['accepted', 'declined', 'closed']))
                                    <div class="mt-4 pt-3 border-top d-flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('agent.hire-leads.accept', $lead->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-success fw-semibold px-4">
                                                <i class="fa-solid fa-circle-check me-1"></i>Accept Lead
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('agent.hire-leads.decline', $lead->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary px-4">
                                                <i class="fa-solid fa-circle-xmark me-1"></i>Decline
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('agent.hire-leads.respond', $lead->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary px-4"
                                                    {{ $lead->responded_at ? 'disabled' : '' }}
                                                    title="{{ $lead->responded_at ? 'Already marked responded on '.$lead->responded_at->format('M j, Y') : 'Mark this lead as responded' }}">
                                                <i class="fa-solid fa-reply me-1"></i>{{ $lead->responded_at ? 'Responded' : 'Mark Responded' }}
                                            </button>
                                        </form>
                                        <a href="mailto:{{ $lead->requester_email }}{{ $lead->requester_phone ? '?body=Phone: '.$lead->requester_phone : '' }}"
                                           class="btn btn-outline-dark px-4">
                                            <i class="fa-solid fa-envelope me-1"></i>Email Requester
                                        </a>
                                    </div>
                                    @else
                                    <div class="mt-4 pt-3 border-top d-flex flex-wrap gap-2">
                                        <a href="mailto:{{ $lead->requester_email }}" class="btn btn-outline-primary px-4">
                                            <i class="fa-solid fa-envelope me-1"></i>Contact {{ $lead->requester_name }}
                                        </a>
                                    </div>
                                    @endif

                                </div>
                            </div>

                            {{-- ── Event timeline ──────────────────────────────── --}}
                            <div class="card border-0 rounded-3" style="box-shadow:0 2px 8px rgba(0,0,0,.07);">
                                <div class="card-body p-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-3"
                                         style="letter-spacing:.06em;font-size:.7rem;">Lead Timeline</div>
                                    <div class="d-flex flex-column gap-0">
                                        @foreach($lead->eventTimeline() as $i => $evt)
                                        @php $isLast = $i === count($lead->eventTimeline()) - 1; @endphp
                                        <div class="d-flex gap-3 align-items-start">
                                            <div class="d-flex flex-column align-items-center" style="width:20px;">
                                                <div style="width:14px;height:14px;border-radius:50%;background:{{ $evt['done'] ? '#059669' : '#e2e8f0' }};border:2px solid {{ $evt['done'] ? '#059669' : '#cbd5e1' }};flex-shrink:0;margin-top:2px;"></div>
                                                @if(!$isLast)
                                                    <div style="width:2px;background:#e2e8f0;flex:1;min-height:24px;"></div>
                                                @endif
                                            </div>
                                            <div class="pb-3" style="flex:1;">
                                                <div style="font-size:.84rem;font-weight:{{ $evt['done'] ? '600' : '400' }};color:{{ $evt['done'] ? '#1e293b' : '#94a3b8' }};">
                                                    {{ $evt['label'] }}
                                                </div>
                                                @if($evt['at'])
                                                    <div style="font-size:.74rem;color:#94a3b8;">
                                                        {{ $evt['at']->format('M j, Y g:i A') }}
                                                        · {{ $evt['at']->diffForHumans() }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
