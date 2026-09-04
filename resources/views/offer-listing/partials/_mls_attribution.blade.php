{{--
    Stellar MLS / Bridge attribution for an MLS-sourced listing.

    WHY THIS EXISTS
    ---------------
    The authenticated Stellar property-detail page has carried an attribution
    block since it shipped (`x-stellar.property-office`): the listing brokerage,
    and the Stellar/Bridge provenance and copyright notice. Imported BidYourOffer
    listings carried none — and they are the surface with the WIDER audience,
    because `/offer-listing/{seller,landlord}/view/{id}` sits deliberately
    outside the auth middleware group while `/stellar/*` sits inside it. So the
    page republishing MLS facts and photographs to the public was the one page
    not saying where the data came from.

    This is that block, modelled on the Stellar one.

    IT RENDERS ONLY FOR MLS-SOURCED LISTINGS.
    `$mlsImported` is resolved from listing provenance meta, not from "does this
    listing happen to have photos". A manually created listing and one from the
    Listing Link importer must NOT claim Stellar provenance they do not have —
    that would be a false attribution, which is worse than a missing one.

    Nothing here exposes configuration, tokens, dataset names or internal keys.

    Expects:
      $mlsImported  bool
      $details      MlsSupplementalDetails|null  (for brokerage + last-updated)
--}}
@php
    $mlsAttrBrokerage = ($details ?? null) instanceof \App\Services\ListingImport\Mls\MlsSupplementalDetails
        ? $details->brokerageName()
        : null;

    $mlsAttrUpdated = ($details ?? null) instanceof \App\Services\ListingImport\Mls\MlsSupplementalDetails
        ? $details->lastUpdatedLabel()
        : null;
@endphp

@if(!empty($mlsImported))
    <div class="card shadow-sm border-0 mb-3 mls-attribution">
        <div class="card-body py-2 px-3">
            @if($mlsAttrBrokerage)
                <div class="text-muted mb-1" style="font-size:.82rem;">
                    <i class="fas fa-building me-1"></i>Listed by <strong>{{ $mlsAttrBrokerage }}</strong>
                </div>
            @endif
            <div style="font-size:.75rem;color:#9ca3af;line-height:1.5;">
                Information provided by Stellar MLS via Bridge Data Output. All information is deemed
                reliable but not guaranteed and should be independently verified.
                @if($mlsAttrUpdated)
                    Listing data last updated {{ $mlsAttrUpdated }}.
                @endif
                &copy; {{ date('Y') }} Stellar MLS.
            </div>
        </div>
    </div>
@endif
