@php
use App\Presenters\LocationDnaPresenter;
@endphp

<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <h6 class="mb-0">Location DNA</h6>
        @if($dna)
            @if($dna->generated_at)
                <span class="badge badge-success" style="font-size:.78rem;">Generated</span>
            @elseif($dna->geocode_status === 'geocoded')
                <span class="badge badge-warning" style="font-size:.78rem;">Geocoded — Scores Pending</span>
            @else
                <span class="badge badge-secondary" style="font-size:.78rem;">{{ $dna->geocode_status ?? 'Unknown' }}</span>
            @endif
            <span class="text-muted small ms-auto">Generated: {{ $dna->generated_at ? $dna->generated_at->format('Y-m-d H:i:s') : '—' }}</span>
        @endif
    </div>

    @if(!$dna)
    <div class="card-body">
        <div class="alert alert-secondary mb-0 d-flex align-items-start gap-2" style="font-size:.85rem;">
            <i class="fa-solid fa-circle-info mt-1"></i>
            <span>Location DNA has not been generated.</span>
        </div>
    </div>
    @else
    <div class="card-body" style="font-size:.85rem;">

        {{-- Header: status / lat / lng --}}
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <strong>Geocode Status:</strong>
                <div class="mt-1">
                    @if($dna->geocode_status === 'geocoded')
                        <span class="badge badge-success">geocoded</span>
                    @else
                        <span class="badge badge-secondary">{{ $dna->geocode_status ?? '—' }}</span>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <strong>Latitude:</strong>
                <div class="mt-1 font-monospace">{{ $dna->geocoded_lat ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <strong>Longitude:</strong>
                <div class="mt-1 font-monospace">{{ $dna->geocoded_lng ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <strong>Geocode Source:</strong>
                <div class="mt-1">{{ $dna->geocode_source ?? '—' }}</div>
            </div>
        </div>

        @php
            $narrative   = LocationDnaPresenter::locationNarrative($dna);
            $labels      = LocationDnaPresenter::lifestyleLabels($dna);
            $scores      = LocationDnaPresenter::lifestyleScores($dna);
            $poisGrouped = LocationDnaPresenter::poisByCategory($pois);

            // Separate top_rated_dining from the regular POI grid
            $topRatedDining = $poisGrouped['top_rated_dining'] ?? collect();
            $regularPois    = array_filter($poisGrouped, fn($k) => $k !== 'top_rated_dining', ARRAY_FILTER_USE_KEY);
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

        {{-- Lifestyle Scores Table --}}
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
                        <td class="text-right">
                            <strong>{{ $value }}</strong>
                        </td>
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
        @elseif($dna->generated_at)
        <div class="text-muted small mb-3">No lifestyle scores recorded.</div>
        @endif

        {{-- Top Rated Dining block (admin inspector) --}}
        @if($topRatedDining->isNotEmpty())
        <div class="mb-3">
            <strong>Top Rated Dining <span class="text-muted small">(derived from restaurant candidates · min. 10 reviews · ranked by ranking engine)</span>:</strong>
            <table class="table table-sm table-bordered mt-2 mb-0" style="font-size:.82rem;">
                <thead class="thead-light">
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Name</th>
                        <th style="width:90px;">Rating</th>
                        <th style="width:90px;">Reviews</th>
                        <th style="width:90px;">Distance</th>
                        <th style="width:70px; text-align:right;">Match</th>
                        <th style="width:70px; text-align:right;">Confidence</th>
                        <th style="width:70px; text-align:right;">Relevance</th>
                        <th style="width:80px; text-align:right;">Ranking</th>
                        <th style="width:70px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topRatedDining as $poi)
                    <tr>
                        <td class="text-center text-muted">{{ $poi->rank ?? '—' }}</td>
                        <td>
                            {{ $poi->poi_name ?? '—' }}
                            @if($poi->poi_address)
                                <div class="text-muted" style="font-size:.78rem;">{{ $poi->poi_address }}</div>
                            @endif
                            {{-- Ranking reasons collapsible --}}
                            @if(!empty($poi->ranking_reasons_json))
                            <div class="mt-1">
                                <a data-toggle="collapse" href="#reasons-trd-{{ $poi->id }}" role="button"
                                   style="font-size:.72rem; color:#888;">signals ▾</a>
                                <div class="collapse" id="reasons-trd-{{ $poi->id }}">
                                    <ul class="mb-0 pl-3" style="font-size:.72rem; color:#555;">
                                        @foreach($poi->ranking_reasons_json as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            @endif
                        </td>
                        <td>
                            @if($poi->rating !== null)
                                <span style="color:#b8860b;">&#9733;</span> {{ number_format((float)$poi->rating, 1) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($poi->user_ratings_total !== null)
                                {{ number_format($poi->user_ratings_total) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($poi->distance_miles !== null)
                                {{ number_format((float)$poi->distance_miles, 2) }} mi
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($poi->category_match_score !== null)
                                <span title="category_match_score">{{ number_format((float)$poi->category_match_score, 1) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($poi->review_confidence_score !== null)
                                <span title="review_confidence_score">{{ number_format((float)$poi->review_confidence_score, 1) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($poi->consumer_relevance_score !== null)
                                <span title="consumer_relevance_score">{{ number_format((float)$poi->consumer_relevance_score, 1) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($poi->ranking_score !== null)
                                <strong title="ranking_score">{{ number_format((float)$poi->ranking_score, 1) }}</strong>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($poi->status === 'found')
                                <span class="badge badge-success">found</span>
                            @elseif($poi->status === 'not_found')
                                <span class="badge badge-secondary">not found</span>
                            @else
                                <span class="badge badge-danger">{{ $poi->status }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- POIs grouped by category (all candidates with rank, excluding top_rated_dining) --}}
        @if(!empty($regularPois))
        <div class="mb-0">
            <strong>Nearby Points of Interest <span class="text-muted small">(all candidates, ranked by consumer relevance · rank 1 = highest scoring)</span>:</strong>
            <div class="row g-2 mt-1">
                @foreach($regularPois as $category => $categoryPois)
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-2" style="font-size:.82rem;">
                        <div class="font-weight-bold text-capitalize mb-1" style="font-size:.8rem; color:#555;">
                            {{ str_replace('_', ' ', $category) }}
                            <span class="text-muted font-weight-normal">({{ $categoryPois->count() }})</span>
                        </div>
                        @foreach($categoryPois as $poi)
                        <div class="py-1 border-top" style="font-size:.79rem;">
                            {{-- Row 1: rank + name + distance --}}
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="text-truncate mr-2" title="{{ $poi->poi_name }}">
                                    <span class="text-muted">#{{ $poi->rank ?? '?' }}</span>
                                    @if(($poi->rank ?? 0) === 1)
                                        <strong>{{ $poi->poi_name ?? '—' }}</strong>
                                    @else
                                        {{ $poi->poi_name ?? '—' }}
                                    @endif
                                </span>
                                <span class="text-muted text-nowrap small">
                                    @if($poi->status === 'found' && $poi->distance_miles !== null)
                                        {{ number_format((float)$poi->distance_miles, 2) }} mi
                                    @elseif($poi->status === 'not_found')
                                        <span class="text-muted">not found</span>
                                    @elseif($poi->status === 'error')
                                        <span class="text-danger">error</span>
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            {{-- Row 2: rating + review count (only for found POIs) --}}
                            @if($poi->status === 'found')
                            <div class="d-flex align-items-center gap-2 mt-1" style="color:#666;">
                                @if($poi->rating !== null)
                                    <span><span style="color:#b8860b;">&#9733;</span> {{ number_format((float)$poi->rating, 1) }}</span>
                                @else
                                    <span class="text-muted">no rating</span>
                                @endif
                                @if($poi->user_ratings_total !== null)
                                    <span class="text-muted">({{ number_format($poi->user_ratings_total) }} reviews)</span>
                                @endif
                            </div>
                            {{-- Row 3: ranking scores --}}
                            @if($poi->ranking_score !== null)
                            <div class="d-flex align-items-center gap-2 mt-1" style="color:#888; font-size:.72rem;">
                                <span title="category_match_score">M:{{ number_format((float)$poi->category_match_score, 0) }}</span>
                                <span title="review_confidence_score">C:{{ number_format((float)$poi->review_confidence_score, 0) }}</span>
                                <span title="consumer_relevance_score">R:{{ number_format((float)$poi->consumer_relevance_score, 0) }}</span>
                                <strong title="ranking_score" style="color:#444;">∑{{ number_format((float)$poi->ranking_score, 0) }}</strong>
                            </div>
                            @endif
                            {{-- Row 4: types_json collapsed inline --}}
                            @if(!empty($poi->types_json))
                            <div class="mt-1" style="line-height:1.3;">
                                <code style="font-size:.72rem; color:#555; word-break:break-all; white-space:normal;">{{ implode(', ', (array)$poi->types_json) }}</code>
                            </div>
                            @endif
                            {{-- Row 5: ranking reasons collapsible --}}
                            @if(!empty($poi->ranking_reasons_json))
                            <div class="mt-1">
                                <a data-toggle="collapse" href="#reasons-{{ $poi->id }}" role="button"
                                   style="font-size:.70rem; color:#aaa;">signals ▾</a>
                                <div class="collapse" id="reasons-{{ $poi->id }}">
                                    <ul class="mb-0 pl-3" style="font-size:.70rem; color:#666;">
                                        @foreach($poi->ranking_reasons_json as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            @endif
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @elseif($dna->geocode_status === 'geocoded')
        <div class="text-muted small">No nearby POIs recorded for this listing.</div>
        @endif

    </div>
    @endif
</div>
