{{--
  Match Check — result body (Phase 4 · git-C14).

  Renders a single MatchCheckAnalysis. Layout-free by design so it can be rendered
  directly in tests. Reads ONLY the data-only MatchCheckAnalysis / MatchReport value
  objects — never a BridgeProperty or a raw source record.

  COMPLIANCE (enforced at this template boundary):
    F8 — narrative AI: the MatchReport->narrative slot is NEVER referenced here. Match
         Check ships without any AI narrative surface this slice.
    F7 — restricted fields: the analysis carries only scalars/arrays (PropertyCandidate
         ::toArray() excludes $raw), so there is no path to raw_json / PublicRemarks /
         contact-media / lockbox data. This template adds none.

  DISABLED is unreachable over HTTP (the middleware 404s first); it degrades to the same
  neutral empty state as NOT_FOUND if ever rendered directly.
--}}

@if ($analysis->isScored())
    @php($report = $analysis->report)
    <div class="card mb-3" data-status="scored">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Your match</h2>
                @if ($report)
                    <span class="badge badge-primary" style="font-size: 1rem;">{{ $report->totalScore }}/100</span>
                @endif
            </div>

            @if ($report)
                @if (!empty($report->categoryScores))
                    <h3 class="h6 text-muted">Score by category</h3>
                    <ul class="list-group list-group-flush mb-3">
                        @foreach ($report->categoryScores as $category => $points)
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>{{ ucwords(str_replace('_', ' ', $category)) }}</span>
                                <span class="text-muted">{{ $points }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($report->whyThisMatches))
                    <h3 class="h6 text-success">Why this matches</h3>
                    <ul class="mb-3">
                        @foreach ($report->whyThisMatches as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($report->whyNot))
                    <h3 class="h6 text-danger">What doesn't line up</h3>
                    <ul class="mb-3">
                        @foreach ($report->whyNot as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($report->tradeoffs))
                    <h3 class="h6">Trade-offs</h3>
                    <ul class="mb-3">
                        @foreach ($report->tradeoffs as $tradeoff)
                            <li>{{ $tradeoff }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($report->missingData))
                    <h3 class="h6 text-muted">Not enough information</h3>
                    <ul class="mb-3">
                        @foreach ($report->missingData as $field)
                            <li>{{ ucwords(str_replace('_', ' ', $field)) }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($report->recommendations))
                    <h3 class="h6">Recommendations</h3>
                    <ul class="mb-0">
                        @foreach ($report->recommendations as $recommendation)
                            <li>{{ $recommendation }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </div>

@elseif ($analysis->isAmbiguous())
    <div class="card mb-3" data-status="ambiguous">
        <div class="card-body">
            <h2 class="h5">More than one listing matches that address</h2>
            <p class="text-muted">Pick the exact listing to check its match.</p>
            <ul class="list-group">
                @foreach ($analysis->candidates as $candidate)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="font-weight-bold">{{ $candidate['unparsed_address'] ?? 'Address unavailable' }}</div>
                            <small class="text-muted">
                                {{ collect([$candidate['city'] ?? null, $candidate['state_or_province'] ?? null, $candidate['postal_code'] ?? null])->filter()->implode(', ') }}
                                @if (!empty($candidate['mls_number']))
                                    &middot; MLS&nbsp;#{{ $candidate['mls_number'] }}
                                @endif
                            </small>
                        </div>
                        {{-- Re-submit by the globally-unique ListingKey (always present → every
                             candidate is selectable, C2), falling back to MLS # only if a
                             candidate somehow lacks a key. --}}
                        @php($resubmit = !empty($candidate['listing_key'])
                            ? ['mode' => 'listing_key', 'field' => 'listing_key', 'value' => $candidate['listing_key']]
                            : (!empty($candidate['mls_number'])
                                ? ['mode' => 'mls', 'field' => 'mls_number', 'value' => $candidate['mls_number']]
                                : null))
                        @if ($resubmit)
                            <form method="POST" action="{{ route('match-check.lookup') }}" class="mb-0">
                                @csrf
                                <input type="hidden" name="mode" value="{{ $resubmit['mode'] }}">
                                <input type="hidden" name="{{ $resubmit['field'] }}" value="{{ $resubmit['value'] }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Check this one</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

@elseif ($analysis->isNoCriteria())
    <div class="alert alert-info" data-status="no_criteria">
        <h2 class="h5">Set up your search criteria first</h2>
        <p class="mb-0">We need your saved buyer or renter criteria to compare a listing against. Add your criteria, then try again.</p>
    </div>

@elseif ($analysis->isCriteriaNotLoaded())
    <div class="alert alert-warning" data-status="criteria_not_loaded">
        <h2 class="h5">We couldn't load your criteria this time</h2>
        <p class="mb-0">Please try again in a moment.</p>
    </div>

@elseif ($analysis->isBlocked())
    <div class="alert alert-secondary" data-status="blocked">
        <h2 class="h5">This listing isn't available for a match check</h2>
        <p class="mb-0">Try a different listing.</p>
    </div>

@else
    {{-- NOT_FOUND, and the (HTTP-unreachable) DISABLED fallback --}}
    <div class="alert alert-secondary" data-status="not_found">
        <h2 class="h5">We couldn't find that listing</h2>
        <p class="mb-0">Double-check the MLS&nbsp;# or address and try again.</p>
    </div>
@endif
