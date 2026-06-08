@php
use App\Presenters\LocationDnaPresenter;
@endphp

<div class="card mb-4 mt-4">
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <h6 class="mb-0"><i class="fa-solid fa-dna me-1"></i> Location DNA</h6>

        @if($locationDna)
            @if($locationDna->generated_at)
                <span class="badge badge-success" style="font-size:.78rem;">Generated</span>
            @elseif($locationDna->geocode_status === 'geocoded')
                <span class="badge badge-warning" style="font-size:.78rem;">Geocoded — Scores Pending</span>
            @elseif($locationDna->geocode_status === 'generating')
                <span class="badge badge-info" style="font-size:.78rem;">Generating</span>
            @elseif($locationDna->geocode_status === 'failed')
                <span class="badge badge-danger" style="font-size:.78rem;">Failed</span>
            @elseif($locationDna->geocode_status === 'skipped')
                <span class="badge badge-secondary" style="font-size:.78rem;">Skipped</span>
            @else
                <span class="badge badge-secondary" style="font-size:.78rem;">{{ $locationDna->geocode_status ?? 'Unknown' }}</span>
            @endif
            <span class="text-muted small ms-auto">Generated: {{ $locationDna->generated_at ? $locationDna->generated_at->format('Y-m-d H:i:s') : '—' }}</span>
        @else
            <span class="badge badge-secondary" style="font-size:.78rem;">Not Generated</span>
        @endif
    </div>

    <div class="card-body" style="font-size:.85rem;">

        {{-- Flash success message --}}
        @if(session('dna_success'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3" style="font-size:.85rem;">
            <i class="fa-solid fa-circle-check mt-1"></i>
            <span>{{ session('dna_success') }}</span>
        </div>
        @endif

        {{-- Validation error --}}
        @php $dnaErrors = $errors ?? null; @endphp
        @if($dnaErrors && ($dnaErrors->has('address') || $dnaErrors->has('dna')))
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="font-size:.85rem;">
            <i class="fa-solid fa-circle-exclamation mt-1"></i>
            <span>{{ $dnaErrors->first('address') ?: $dnaErrors->first('dna') }}</span>
        </div>
        @endif

        @if($canGenerateLocationDna)
            <div class="mb-3">
                <form method="POST" action="{{ route('agent.location-dna.generate', [$listingType, $listingId]) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm {{ $locationDna ? 'btn-outline-primary' : 'btn-primary' }}">
                        <i class="fa-solid fa-arrow-rotate-right me-1"></i>
                        {{ $locationDna ? 'Refresh Location DNA' : 'Generate Location DNA' }}
                    </button>
                </form>
            </div>
        @elseif(!$locationDna)
            <div class="alert alert-secondary mb-3 d-flex align-items-start gap-2" style="font-size:.85rem;">
                <i class="fa-solid fa-circle-info mt-1"></i>
                <span>Complete the listing address before generating Location DNA.</span>
            </div>
        @endif

        @if($locationDna)
        @php
            $narrative   = LocationDnaPresenter::locationNarrative($locationDna);
            $labels      = LocationDnaPresenter::lifestyleLabels($locationDna);
            $scores      = LocationDnaPresenter::lifestyleScores($locationDna);
            $poisGrouped = LocationDnaPresenter::poisByCategory($locationPois);
        @endphp

        {{-- Summary Narrative --}}
        @if($narrative)
        <div class="mb-3">
            <strong>Location Narrative:</strong>
            <p class="mt-1 mb-0 text-muted">{{ $narrative }}</p>
        </div>
        @endif

        {{-- Lifestyle Labels --}}
        @if(!empty($labels))
        <div class="mb-3">
            <strong>Lifestyle Labels:</strong>
            <div class="mt-1">
                @foreach($labels as $label)
                    <span class="badge badge-info mr-1" style="font-size:.8rem;">{{ $label }}</span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Lifestyle Scores --}}
        @if(!empty($scores))
        <div class="mb-3">
            <strong>Lifestyle Scores <span class="text-muted small">(sorted highest to lowest)</span>:</strong>
            <table class="table table-sm table-bordered mt-2 mb-0" style="font-size:.83rem; max-width:480px;">
                <thead class="thead-light">
                    <tr>
                        <th>Category</th>
                        <th style="width:100px; text-align:right;">Score (0–100)</th>
                        <th style="width:160px;">Bar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scores as $label => $value)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="text-right"><strong>{{ $value }}</strong></td>
                        <td>
                            <div class="progress" style="height:10px; margin-top:3px;">
                                <div class="progress-bar @if($value >= 70) bg-success @elseif($value >= 40) bg-warning @else bg-danger @endif"
                                     role="progressbar"
                                     style="width:{{ $value }}%;"
                                     aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @elseif($locationDna->generated_at)
        <div class="text-muted small mb-3">No lifestyle scores recorded.</div>
        @endif

        {{-- POIs grouped by category (top 3 per category) --}}
        @if(!empty($poisGrouped))
        <div class="mb-0">
            <strong>Nearby Points of Interest <span class="text-muted small">(top 3 per category)</span>:</strong>
            <div class="row g-2 mt-1">
                @foreach($poisGrouped as $category => $categoryPois)
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-2" style="font-size:.82rem;">
                        <div class="font-weight-bold text-capitalize mb-1" style="font-size:.8rem; color:#555;">
                            {{ str_replace('_', ' ', $category) }}
                        </div>
                        @foreach($categoryPois as $poi)
                        <div class="d-flex justify-content-between align-items-baseline py-1 border-top" style="font-size:.8rem;">
                            <span class="text-truncate mr-2" title="{{ $poi->poi_name }}">{{ $poi->poi_name }}</span>
                            <span class="text-muted text-nowrap small">
                                @if($poi->distance_miles !== null)
                                    {{ number_format((float)$poi->distance_miles, 2) }} mi
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @elseif($locationDna->geocode_status === 'geocoded')
        <div class="text-muted small">No nearby POIs recorded for this listing.</div>
        @endif

        @endif {{-- end if $locationDna --}}

    </div>
</div>
