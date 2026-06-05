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

                            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                <div>
                                    <h4 class="fw-bold mb-0" style="color:#1e293b;">Hire Agent Leads</h4>
                                    <p class="text-muted small mb-0">Requests from visitors who want an agent's help.</p>
                                </div>
                                <a href="{{ route('agent.hire-leads.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-arrows-rotate me-1"></i>Refresh
                                </a>
                            </div>

                            {{-- Summary cards --}}
                            <div class="row g-2 mb-4">
                                @foreach([
                                    ['label'=>'All',      'key'=>'all',      'color'=>'#334155', 'bg'=>'#f8fafc', 'icon'=>'fa-list'],
                                    ['label'=>'New',      'key'=>'new',      'color'=>'#2563eb', 'bg'=>'#eff6ff', 'icon'=>'fa-bell'],
                                    ['label'=>'Pending',  'key'=>'pending',  'color'=>'#d97706', 'bg'=>'#fffbeb', 'icon'=>'fa-hourglass-half'],
                                    ['label'=>'Accepted', 'key'=>'accepted', 'color'=>'#059669', 'bg'=>'#f0fdf4', 'icon'=>'fa-circle-check'],
                                    ['label'=>'Declined', 'key'=>'declined', 'color'=>'#94a3b8', 'bg'=>'#f8fafc', 'icon'=>'fa-circle-xmark'],
                                    ['label'=>'Closed',   'key'=>'closed',   'color'=>'#1e293b', 'bg'=>'#f1f5f9', 'icon'=>'fa-lock'],
                                ] as $card)
                                <div class="col-4 col-md-2">
                                    <a href="{{ route('agent.hire-leads.index', $card['key'] === 'all' ? [] : ['status' => $card['key']]) }}"
                                       class="text-decoration-none">
                                        <div class="card border-0 rounded-3 p-2 h-100 text-center"
                                             style="background:{{ $card['bg'] }};{{ $status === $card['key'] || ($card['key'] === 'all' && $status === '') ? 'box-shadow:0 0 0 2px '.$card['color'].';' : '' }}">
                                            <i class="fa-solid {{ $card['icon'] }} mb-1" style="color:{{ $card['color'] }};font-size:.85rem;"></i>
                                            <div style="font-size:1.3rem;font-weight:800;color:{{ $card['color'] }};">{{ $counts[$card['key']] ?? 0 }}</div>
                                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;">{{ $card['label'] }}</div>
                                        </div>
                                    </a>
                                </div>
                                @endforeach
                            </div>

                            {{-- Lead list --}}
                            @if($leads->isEmpty())
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-user-tie" style="font-size:2.5rem;opacity:.25;"></i>
                                    <p class="mt-3 mb-0">No leads found.</p>
                                    <p class="small">When visitors request an agent from a listing page, leads will appear here.</p>
                                </div>
                            @else
                                <div class="d-flex flex-column gap-3">
                                    @foreach($leads as $lead)
                                    <div class="card border-0 rounded-3 overflow-hidden"
                                         style="border-left:4px solid {{ $lead->status === 'new' ? '#2563eb' : ($lead->status === 'accepted' ? '#059669' : ($lead->status === 'declined' ? '#94a3b8' : ($lead->status === 'closed' ? '#1e293b' : '#d97706'))) }} !important;box-shadow:0 1px 4px rgba(0,0,0,.07);">
                                        <div class="card-body py-3 px-3">
                                            <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                                                <div style="flex:1;min-width:0;">
                                                    <div class="fw-bold mb-1" style="font-size:.95rem;color:#1e293b;">
                                                        <a href="{{ route('agent.hire-leads.show', $lead->id) }}" class="text-decoration-none" style="color:inherit;">
                                                            {{ $lead->requester_name }}
                                                        </a>
                                                        @if($lead->status === 'new')
                                                            <span class="badge bg-primary ms-1" style="font-size:.65rem;">New</span>
                                                        @endif
                                                    </div>
                                                    <div style="font-size:.8rem;color:#64748b;">
                                                        <i class="fa-solid fa-envelope me-1 opacity-40"></i>
                                                        <a href="mailto:{{ $lead->requester_email }}" style="color:inherit;text-decoration:none;">{{ $lead->requester_email }}</a>
                                                        @if($lead->requester_phone)
                                                            <span class="mx-1">·</span>
                                                            <a href="tel:{{ $lead->requester_phone }}" style="color:inherit;text-decoration:none;">{{ $lead->requester_phone }}</a>
                                                        @endif
                                                    </div>
                                                    <div style="font-size:.76rem;color:#94a3b8;margin-top:2px;">
                                                        {{ $lead->representationTypeLabel() }}
                                                        &middot; {{ $lead->selectedPropertyTypeLabel() }}
                                                        &middot; {{ $lead->sourceListingTypeLabel() }}
                                                        @if($lead->source_listing_title)
                                                            <span class="ms-1 text-muted" style="font-size:.72rem;">— {{ Str::limit($lead->source_listing_title, 40) }}</span>
                                                        @endif
                                                    </div>
                                                    <div style="font-size:.72rem;color:#94a3b8;margin-top:2px;">
                                                        {{ $lead->created_at->diffForHumans() }}
                                                        @if($lead->preset_match_status === 'matched')
                                                            <span class="ms-2 badge bg-success" style="font-size:.6rem;">Preset matched</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column align-items-end gap-2">
                                                    <span class="badge rounded-pill {{ $lead->statusBadgeClass() }} px-3"
                                                          style="font-size:.68rem;">{{ $lead->statusLabel() }}</span>

                                                    {{-- In-list quick actions --}}
                                                    @if(! in_array($lead->status, ['accepted', 'declined', 'closed']))
                                                    <div class="d-flex gap-1">
                                                        <form method="POST" action="{{ route('agent.hire-leads.accept', $lead->id) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-success btn-sm py-0 px-2"
                                                                    style="font-size:.7rem;"
                                                                    title="Accept">
                                                                <i class="fa-solid fa-circle-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('agent.hire-leads.decline', $lead->id) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2"
                                                                    style="font-size:.7rem;"
                                                                    title="Decline">
                                                                <i class="fa-solid fa-circle-xmark"></i>
                                                            </button>
                                                        </form>
                                                        <a href="mailto:{{ $lead->requester_email }}"
                                                           class="btn btn-outline-primary btn-sm py-0 px-2"
                                                           style="font-size:.7rem;" title="Respond via email">
                                                            <i class="fa-solid fa-reply"></i>
                                                        </a>
                                                        <a href="{{ route('agent.hire-leads.show', $lead->id) }}"
                                                           class="btn btn-outline-dark btn-sm py-0 px-2"
                                                           style="font-size:.7rem;" title="View details">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </a>
                                                    </div>
                                                    @else
                                                    <a href="{{ route('agent.hire-leads.show', $lead->id) }}"
                                                       class="btn btn-outline-secondary btn-sm py-0 px-2"
                                                       style="font-size:.7rem;">
                                                        <i class="fa-solid fa-eye me-1"></i>View
                                                    </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>

                                <div class="mt-4">
                                    {{ $leads->links() }}
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
