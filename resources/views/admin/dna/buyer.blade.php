@extends('layouts.admin')
@section('content')
<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <a href="{{ route('admin.dna.demand.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Demand DNA Index</a>
    @if($profile)
    <a href="{{ route('admin.dna.demand.show', $profile->id) }}" class="btn btn-sm btn-outline-secondary">Raw Inspector Record</a>
    @endif
    <span class="text-muted small">/ Buyer DNA Profile — Listing {{ $listingId }}</span>
</div>

@if(!$profile)
<div class="alert alert-info d-flex align-items-start gap-2 mb-0">
    <i class="fa-solid fa-circle-info mt-1"></i>
    <div>
        <strong>No profile generated yet.</strong><br>
        No active (non-archived) Buyer DNA profile exists for listing ID <strong>{{ $listingId }}</strong>. A profile will appear here once the DNA computation has been run for this listing.
    </div>
</div>
@else

{{-- Profile Header --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <h5 class="mb-0">Buyer DNA Profile</h5>
        <span class="badge badge-success">Active</span>
        <span class="text-muted small ms-auto">Listing ID: {{ $profile->listing_id }} &mdash; v{{ $profile->version }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3" style="font-size:.85rem;">
            <div class="col-md-3"><strong>Listing ID:</strong> {{ $profile->listing_id }}</div>
            <div class="col-md-3"><strong>DNA Version:</strong> v{{ $profile->version }}</div>
            <div class="col-md-3"><strong>Computed At:</strong> {{ $profile->computed_at ? $profile->computed_at->format('Y-m-d H:i:s') : '—' }}</div>
            <div class="col-md-3"><strong>Source Updated At:</strong> {{ $profile->source_listing_updated_at ? $profile->source_listing_updated_at->format('Y-m-d H:i:s') : '—' }}</div>
            <div class="col-md-3">
                <strong>Preference Completeness:</strong>
                <div class="mt-1">
                    @if(!is_null($profile->preference_completeness))
                        <span class="badge badge-primary" style="font-size:.9rem;">{{ $profile->preference_completeness }}</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <strong>Archetype Label:</strong>
                <div class="mt-1">
                    @if($profile->archetype_label)
                        <span class="badge badge-info">{{ $profile->archetype_label }}</span>
                    @else
                        <span class="text-muted small">—</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Buyer Avatar --}}
@if($avatarResult)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <h6 class="mb-0">Buyer Avatar</h6>
        @if($avatarResult['status'] === 'generated')
            <span class="badge badge-success">Generated</span>
        @elseif($avatarResult['status'] === 'insufficient_data')
            <span class="badge badge-warning">Insufficient Data</span>
        @elseif($avatarResult['status'] === 'failed')
            <span class="badge badge-danger">Failed</span>
        @endif
    </div>
    <div class="card-body" style="font-size:.85rem;">
        @if($avatarResult['status'] === 'failed')
            <div class="alert alert-danger mb-0">
                <strong>Classification error:</strong> {{ $avatarResult['error'] }}
            </div>
        @elseif($avatarResult['status'] === 'insufficient_data')
            <p class="text-muted mb-2">Profile does not have enough data to classify a buyer avatar. Missing inputs:</p>
            @if(!empty($avatarResult['missing_inputs']))
                <ul class="mb-0 ps-3">
                    @foreach($avatarResult['missing_inputs'] as $item)
                        <li class="text-muted">{{ $item }}</li>
                    @endforeach
                </ul>
            @else
                <span class="text-muted small">No missing input details available.</span>
            @endif
        @elseif($avatarResult['status'] === 'generated')
            {{-- Primary Avatar --}}
            <div class="mb-3">
                <strong>Primary Avatar:</strong>
                <span class="badge badge-primary ms-2" style="font-size:.95rem;">{{ $avatarResult['primary_avatar'] }}</span>
            </div>
            {{-- Secondary Avatars --}}
            @if(!empty($avatarResult['secondary_avatars']))
            <div class="mb-3">
                <strong>Secondary Avatars:</strong>
                <span class="ms-1">
                    @foreach($avatarResult['secondary_avatars'] as $secondary)
                        <span class="badge badge-secondary me-1">{{ $secondary }}</span>
                    @endforeach
                </span>
            </div>
            @endif
            {{-- Signals --}}
            @if(!empty($avatarResult['signals']))
            <div class="mb-3">
                <strong>Signals:</strong>
                <table class="table table-sm table-hover mt-2 mb-0" style="font-size:.83rem;">
                    <thead class="thead-light">
                        <tr><th style="width:45%;">Signal</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        @foreach($avatarResult['signals'] as $key => $value)
                        <tr>
                            <td class="text-muted"><code>{{ $key }}</code></td>
                            <td>
                                @if(is_bool($value))
                                    {{ $value ? 'true' : 'false' }}
                                @elseif(is_null($value))
                                    <span class="text-muted">—</span>
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            {{-- Missing Inputs --}}
            @if(!empty($avatarResult['missing_inputs']))
            <div>
                <strong>Missing Inputs (for fuller classification):</strong>
                <ul class="mb-0 ps-3 mt-1">
                    @foreach($avatarResult['missing_inputs'] as $item)
                        <li class="text-muted">{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        @endif
    </div>
</div>
@endif

{{-- Lifestyle Tags --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Lifestyle Tags</h6>
        @if(!empty($profile->lifestyle_tags))
        <span class="badge badge-secondary">{{ count($profile->lifestyle_tags) }} tag(s)</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if(empty($profile->lifestyle_tags))
            <div class="p-3 text-muted small">No lifestyle tags recorded for this profile.</div>
        @else
            <table class="table table-sm table-hover mb-0" style="font-size:.84rem;">
                <thead class="thead-light">
                    <tr>
                        <th style="width:35%;">Tag (verbatim)</th>
                        <th>Dimension Explanation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($explanations['lifestyle_tag_explanations'] ?? []) as $row)
                    <tr>
                        <td><code>{{ $row['tag'] }}</code></td>
                        <td class="text-muted">{{ $row['explanation'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- Deal-Breaker Flags --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">
            Conflict Dimensions (Deal-Breaker Flags)
            <span data-toggle="tooltip" title="These are structural metadata flags only. They record whether a deterministic field conflict was detected during compatibility computation. They do not constitute a recommendation, disqualification, or decision of any kind." style="cursor:help;">&#9432;</span>
        </h6>
        @if(!empty($profile->deal_breaker_flags))
        <span class="badge badge-secondary">{{ count($profile->deal_breaker_flags) }} flag(s)</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if(empty($profile->deal_breaker_flags))
            <div class="p-3 text-muted small">No conflict dimension flags recorded for this profile.</div>
        @else
            <table class="table table-sm table-hover mb-0" style="font-size:.84rem;">
                <thead class="thead-light">
                    <tr>
                        <th style="width:25%;">Flag (verbatim)</th>
                        <th style="width:20%;">Source Field (verbatim)</th>
                        <th style="width:15%;">Value (verbatim)</th>
                        <th>Dimension Explanation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($explanations['deal_breaker_explanations'] ?? []) as $row)
                    <tr>
                        <td><code>{{ $row['flag'] }}</code></td>
                        <td><code>{{ $row['source_field'] }}</code></td>
                        <td>{{ $row['value'] ?? '—' }}</td>
                        <td class="text-muted">{{ $row['explanation'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- All Populated Preference Dimension Values --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">All Populated Preference Dimension Values</h6></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.84rem;">
            <thead class="thead-light">
                <tr><th style="width:35%;">Dimension</th><th>Persisted Value</th></tr>
            </thead>
            <tbody>
                @php
                $scalarFields = [
                    'id'                        => 'Profile ID',
                    'listing_type'              => 'Listing Type',
                    'listing_id'                => 'Listing ID',
                    'version'                   => 'Version',
                    'preference_completeness'   => 'Preference Completeness',
                    'archetype_label'           => 'Archetype Label',
                    'computed_at'               => 'Computed At',
                    'source_listing_updated_at' => 'Source Listing Updated At',
                    'archived_at'               => 'Archived At',
                ];
                @endphp
                @foreach($scalarFields as $field => $label)
                @php $val = $profile->$field; @endphp
                @if(!is_null($val))
                <tr>
                    <td class="text-muted">{{ $label }}</td>
                    <td>
                        @if($val instanceof \Carbon\Carbon)
                            {{ $val->format('Y-m-d H:i:s') }}
                        @else
                            {{ $val }}
                        @endif
                    </td>
                </tr>
                @endif
                @endforeach
                @if(!empty($profile->lifestyle_tags))
                <tr>
                    <td class="text-muted">Lifestyle Tags (JSON)</td>
                    <td><code style="font-size:.78rem;white-space:pre-wrap;">{{ json_encode($profile->lifestyle_tags, JSON_PRETTY_PRINT) }}</code></td>
                </tr>
                @endif
                @if(!empty($profile->deal_breaker_flags))
                <tr>
                    <td class="text-muted">Conflict Dimension Flags (JSON)</td>
                    <td><code style="font-size:.78rem;white-space:pre-wrap;">{{ json_encode($profile->deal_breaker_flags, JSON_PRETTY_PRINT) }}</code></td>
                </tr>
                @endif
                @if($profile->commute_polygon_cache)
                <tr>
                    <td class="text-muted">Commute Polygon Cache</td>
                    <td><span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Geospatial / Future Phase</span></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@endif
@endsection
