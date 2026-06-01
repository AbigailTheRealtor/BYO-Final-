@extends('layouts.admin')
@section('content')
<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <a href="{{ route('admin.dna.property.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Property DNA Index</a>
    @if($profile)
    <a href="{{ route('admin.dna.property.show', $profile->id) }}" class="btn btn-sm btn-outline-secondary">Raw Inspector Record</a>
    @endif
    <span class="text-muted small">/ Landlord DNA Profile — Listing {{ $listingId }}</span>
</div>

@if(!$profile)
<div class="alert alert-info d-flex align-items-start gap-2 mb-0">
    <i class="fa-solid fa-circle-info mt-1"></i>
    <div>
        <strong>No profile generated yet.</strong><br>
        No active (non-archived) Landlord DNA profile exists for listing ID <strong>{{ $listingId }}</strong>. A profile will appear here once the DNA computation has been run for this listing.
    </div>
</div>
@else

{{-- Profile Header --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <h5 class="mb-0">Landlord DNA Profile</h5>
        <span class="badge badge-success">Active</span>
        <span class="text-muted small ms-auto">Listing ID: {{ $profile->listing_id }} &mdash; v{{ $profile->version }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3" style="font-size:.85rem;">
            <div class="col-md-3"><strong>Listing ID:</strong> {{ $profile->listing_id }}</div>
            <div class="col-md-3"><strong>DNA Version:</strong> v{{ $profile->version }}</div>
            <div class="col-md-3"><strong>Computed At:</strong> {{ $profile->computed_at ? $profile->computed_at->format('Y-m-d H:i:s') : '—' }}</div>
            <div class="col-md-3"><strong>Source Updated At:</strong> {{ $profile->source_listing_updated_at ? $profile->source_listing_updated_at->format('Y-m-d H:i:s') : '—' }}</div>
        </div>
    </div>
</div>

{{-- Coverage Scores --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Coverage Scores</h6></div>
    <div class="card-body">
        <div class="row g-3" style="font-size:.85rem;">
            <div class="col-md-3">
                <strong>Overall DNA Completeness</strong>
                <div class="mt-1">
                    @if(!is_null($profile->overall_dna_completeness))
                        <span class="badge badge-primary" style="font-size:.9rem;">{{ $profile->overall_dna_completeness }}</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
            @php
            $scoreFields = [
                'physical_score'               => 'Physical',
                'financial_score'              => 'Financial',
                'location_score'               => 'Location',
                'condition_score'              => 'Condition',
                'legal_score'                  => 'Legal',
                'flexibility_score'            => 'Flexibility',
                'occupant_qualification_score' => 'Occupant Qualification',
                'marketing_score'              => 'Marketing',
                'compatibility_score'          => 'Compatibility',
                'commercial_score'             => 'Commercial',
            ];
            @endphp
            @foreach($scoreFields as $field => $label)
            <div class="col-md-3">
                <strong>{{ $label }}</strong>
                <div class="mt-1">
                    @if(!is_null($profile->$field))
                        <span class="badge badge-secondary">{{ $profile->$field }}</span>
                    @else
                        <span class="text-muted small">—</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <hr class="my-3">
        <p class="text-muted small mb-2">The following enrichment fields are reserved and not yet populated by any data source.</p>
        <div class="row g-3" style="font-size:.85rem;">
            @foreach(['Walk Score' => 'walk_score', 'Transit Score' => 'transit_score', 'Bike Score' => 'bike_score', 'School Rating' => 'school_rating', 'Flood Zone Verified' => 'flood_zone_verified', 'Est. Monthly Utilities' => 'estimated_monthly_utilities'] as $label => $field)
            <div class="col-md-3">
                <strong>{{ $label }}</strong><br>
                @if(!is_null($profile->$field))
                    <span class="badge badge-light border">{{ $profile->$field }}</span>
                @else
                    <span class="badge badge-light border text-muted" style="font-size:.78rem;">Not Yet Populated</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Property Personality --}}
@if($personalityResult !== null)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <h6 class="mb-0">Property Personality</h6>
        @if(($personalityResult['status'] ?? '') === 'generated')
            <span class="badge badge-success" style="font-size:.78rem;">Generated</span>
        @elseif(($personalityResult['status'] ?? '') === 'insufficient_data')
            <span class="badge badge-warning" style="font-size:.78rem;">Insufficient Data</span>
        @else
            <span class="badge badge-danger" style="font-size:.78rem;">Failed</span>
        @endif
    </div>
    <div class="card-body" style="font-size:.85rem;">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <strong>Status:</strong>
                <div class="mt-1">{{ $personalityResult['status'] ?? '—' }}</div>
            </div>
            <div class="col-md-6">
                <strong>Primary Personality:</strong>
                <div class="mt-1">
                    @if(!empty($personalityResult['primary_personality']))
                        <span class="badge badge-primary" style="font-size:.85rem;">{{ $personalityResult['primary_personality'] }}</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="mb-3">
            <strong>Secondary Personalities:</strong>
            <div class="mt-1">
                @if(!empty($personalityResult['secondary_personalities']))
                    {{ implode(', ', $personalityResult['secondary_personalities']) }}
                @else
                    <span class="text-muted">—</span>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <strong>Personality Signals:</strong>
            <div class="mt-1">
                @if(!empty($personalityResult['personality_signals']))
                    <ul class="mb-0 ps-3">
                        @foreach($personalityResult['personality_signals'] as $row)
                            @php $sigVal = $row['value']; @endphp
                            <li><code>{{ $row['signal'] }}</code>: {{ is_bool($sigVal) ? ($sigVal ? 'true' : 'false') : $sigVal }}</li>
                        @endforeach
                    </ul>
                @else
                    <span class="text-muted">—</span>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <strong>Missing Inputs:</strong>
            <div class="mt-1">
                @if(!empty($personalityResult['missing_inputs']))
                    <ul class="mb-0 ps-3">
                        @foreach($personalityResult['missing_inputs'] as $row)
                            <li class="text-muted">{{ $row['dimension'] }}</li>
                        @endforeach
                    </ul>
                @else
                    <span class="text-muted">—</span>
                @endif
            </div>
        </div>

        @if(!empty($personalityResult['error']))
        <div class="alert alert-warning mb-0 py-2 px-3" style="font-size:.83rem;">
            <strong>Error:</strong> {{ $personalityResult['error'] }}
        </div>
        @endif
    </div>
</div>
@endif

{{-- Buyer Archetype Tags --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Buyer Archetype Tags</h6>
        @if(!empty($profile->ai_buyer_archetype_tags))
        <span class="badge badge-secondary">{{ count($profile->ai_buyer_archetype_tags) }} tag(s)</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if(empty($profile->ai_buyer_archetype_tags))
            <div class="p-3 text-muted small">No archetype tags recorded for this profile.</div>
        @else
            <table class="table table-sm table-hover mb-0" style="font-size:.84rem;">
                <thead class="thead-light">
                    <tr>
                        <th style="width:35%;">Tag (verbatim)</th>
                        <th>Dimension Explanation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($explanations['archetype_tag_explanations'] ?? []) as $row)
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

{{-- Marketing Hooks --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Marketing Hooks</h6>
        @if(!empty($profile->ai_marketing_hooks))
        <span class="badge badge-secondary">{{ count($profile->ai_marketing_hooks) }} hook(s)</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if(empty($profile->ai_marketing_hooks))
            <div class="p-3 text-muted small">No marketing hooks recorded for this profile.</div>
        @else
            <table class="table table-sm table-hover mb-0" style="font-size:.84rem;">
                <thead class="thead-light">
                    <tr>
                        <th style="width:22%;">Trait (verbatim)</th>
                        <th style="width:28%;">Value (verbatim)</th>
                        <th>Dimension Explanation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($explanations['marketing_hook_explanations'] ?? []) as $row)
                    <tr>
                        <td><code>{{ $row['trait'] }}</code></td>
                        <td><code>{{ $row['value'] }}</code></td>
                        <td class="text-muted">{{ $row['explanation'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- All Populated DNA Dimension Values --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">All Populated DNA Dimension Values</h6></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.84rem;">
            <thead class="thead-light">
                <tr><th style="width:35%;">Dimension</th><th>Persisted Value</th></tr>
            </thead>
            <tbody>
                @php
                $allFields = [
                    'id'                           => 'Profile ID',
                    'listing_type'                 => 'Listing Type',
                    'listing_id'                   => 'Listing ID',
                    'version'                      => 'Version',
                    'overall_dna_completeness'     => 'Overall DNA Completeness',
                    'physical_score'               => 'Physical Score',
                    'financial_score'              => 'Financial Score',
                    'location_score'               => 'Location Score',
                    'condition_score'              => 'Condition Score',
                    'legal_score'                  => 'Legal Score',
                    'flexibility_score'            => 'Flexibility Score',
                    'occupant_qualification_score' => 'Occupant Qualification Score',
                    'marketing_score'              => 'Marketing Score',
                    'compatibility_score'          => 'Compatibility Score',
                    'commercial_score'             => 'Commercial Score',
                    'walk_score'                   => 'Walk Score',
                    'transit_score'                => 'Transit Score',
                    'bike_score'                   => 'Bike Score',
                    'school_rating'                => 'School Rating',
                    'flood_zone_verified'          => 'Flood Zone Verified',
                    'estimated_monthly_utilities'  => 'Estimated Monthly Utilities',
                    'computed_at'                  => 'Computed At',
                    'source_listing_updated_at'    => 'Source Listing Updated At',
                    'archived_at'                  => 'Archived At',
                ];
                @endphp
                @foreach($allFields as $field => $label)
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
                @if(!empty($profile->ai_buyer_archetype_tags))
                <tr>
                    <td class="text-muted">Buyer Archetype Tags (JSON)</td>
                    <td><code style="font-size:.78rem;white-space:pre-wrap;">{{ json_encode($profile->ai_buyer_archetype_tags, JSON_PRETTY_PRINT) }}</code></td>
                </tr>
                @endif
                @if(!empty($profile->ai_marketing_hooks))
                <tr>
                    <td class="text-muted">Marketing Hooks (JSON)</td>
                    <td><code style="font-size:.78rem;white-space:pre-wrap;">{{ json_encode($profile->ai_marketing_hooks, JSON_PRETTY_PRINT) }}</code></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@endif
@endsection
