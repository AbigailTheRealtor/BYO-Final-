{{--
    MLS Details — the supplemental Stellar/Bridge payload for an imported listing.

    THE QUICK-IMPORT REVIEW SCREEN'S PRESENTATION.
    The review step is a single wizard panel showing everything the import found,
    so it renders every section in one card. The published seller and landlord
    pages no longer include this file: they place the same sections into the
    page's own cards through {@see \App\Services\ListingImport\Mls\MlsDetailLayout},
    because a listing page that carries a second "Property Details" block beside
    its own reads as the same house described twice.

    THE PARITY GUARANTEE IS UNCHANGED, AND IS NOW AT THE ROW LEVEL.
    "What the review screen showed me" and "what my published listing shows"
    still cannot drift apart, because all three surfaces render their rows
    through ONE template — `_mls_facts_rows.blade.php` — from ONE payload. What
    differs between them is only which card a section is placed in, which is the
    thing that was supposed to differ all along.

    EVERY ROW IS POPULATED, BY CONSTRUCTION.
    Nothing in this file or in the row partial tests a value for emptiness, and
    it must stay that way. MlsSupplementalDetails drops empty values, empty rows
    and empty sections at build time and again at read time, so a section that
    reaches this template has content and a row that reaches it has a value. A
    blank-row guard here would be a second implementation of that rule, and the
    two would eventually disagree.

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

            @include('offer-listing.partials._mls_facts_rows', [
                'sections'      => $details->sections,
                'headings'      => true,
                'headingPrefix' => '',
            ])
        </div>
    </div>
@endif
