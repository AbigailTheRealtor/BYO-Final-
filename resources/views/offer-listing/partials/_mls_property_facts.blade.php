{{--
    MLS Details — the supplemental Stellar/Bridge payload for an imported listing.

    ONE PARTIAL, THREE SURFACES.
    The quick-import review screen, the seller listing page and the landlord
    listing page all include this file. That is deliberate and it is the whole
    of the search-vs-import parity guarantee at the view layer: "what the review
    screen showed me" and "what my published listing shows" cannot drift apart,
    because there is only one template.

    EVERY ROW HERE IS POPULATED, BY CONSTRUCTION.
    Nothing in this file tests a value for emptiness, and it must stay that way.
    MlsSupplementalDetails drops empty values, empty rows and empty sections at
    build time and again at read time, so a section that reaches this template
    has content and a row that reaches it has a value. A blank-row guard here
    would be a second implementation of that rule, and the two would eventually
    disagree.

    Expects:
      $details      MlsSupplementalDetails
      $mlsHeading   optional string heading (defaults to "MLS Property Details")
--}}
@php
    /** @var \App\Services\ListingImport\Mls\MlsSupplementalDetails $details */
    $mlsHeading = $mlsHeading ?? 'MLS Property Details';
@endphp

@if($details instanceof \App\Services\ListingImport\Mls\MlsSupplementalDetails && ! $details->isEmpty())
    <div class="card shadow-sm mb-3 mls-property-facts">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-baseline justify-content-between mb-3">
                <h5 class="fw-semibold mb-0">{{ $mlsHeading }}</h5>
                <span class="small text-muted">
                    Source: {{ \App\Services\ListingImport\Mls\MlsSupplementalDetails::SOURCE_LABEL }}
                    @if($details->mlsNumber)
                        &middot; MLS #{{ $details->mlsNumber }}
                    @endif
                </span>
            </div>

            @foreach($details->sections as $section)
                <div class="mb-3 mls-facts-section">
                    <div class="text-uppercase small fw-semibold text-muted mb-1">{{ $section['title'] }}</div>
                    <dl class="row mb-0 small">
                        @foreach($section['rows'] as $row)
                            <dt class="col-sm-4 fw-normal text-muted">{{ $row['label'] }}</dt>
                            <dd class="col-sm-8">
                                @php
                                    // One href per row at most. Both are validated
                                    // in MlsSupplementalDetails on the way out of
                                    // storage — anything that is not an absolute
                                    // https URL or a real mailto arrives as null
                                    // and the value renders as plain text.
                                    $href = $row['url'] ?? $row['link'] ?? null;
                                @endphp
                                @if($href)
                                    <a href="{{ $href }}"
                                       @if(! \Illuminate\Support\Str::startsWith($href, 'mailto:'))
                                           target="_blank" rel="noopener noreferrer nofollow"
                                       @endif
                                    >{{ $row['value'] }}</a>
                                @else
                                    {{ $row['value'] }}
                                @endif
                            </dd>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </div>
@endif
