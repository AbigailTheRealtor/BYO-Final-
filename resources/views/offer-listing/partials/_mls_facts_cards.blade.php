{{--
    MLS sections that have no canonical card to merge into, rendered as ordinary
    listing section-cards.

    Interior, Exterior, Waterfront / Views, Lease / Rental, the agent and
    brokerage cards and the MLS's own bookkeeping all describe things the
    BidYourOffer form never asked about, so there is no canonical card of the
    same name for them to collide with and each becomes a card of its own — the
    same `.card.section-card` + `.card-header` the rest of the page uses.

    The source line is repeated per card rather than stated once for the group.
    These cards are interleaved with the listing's own cards, so a reader
    arriving at "Interior" by scrolling or by an in-page anchor must be able to
    see, without scrolling back, that its contents are the MLS's claim and not
    the seller's.

    Expects:
      $sections  list<array{title,rows}>
      $mlsNumber string|null
--}}
@php
    $mlsCardSections   = is_array($sections ?? null) ? $sections : [];
    $mlsCardNumber     = $mlsNumber  ?? null;
    $mlsCardLabelStyle = $labelStyle ?? '';
    $mlsCardValueStyle = $valueStyle ?? 'overflow-wrap:break-word;word-break:break-word;';
@endphp

@foreach($mlsCardSections as $mlsCardSection)
    <div class="card section-card mls-facts-section" id="section-mls-{{ \Illuminate\Support\Str::slug($mlsCardSection['title']) }}">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <span>
                <i class="{{ \App\Services\ListingImport\Mls\MlsDetailLayout::iconFor($mlsCardSection['title']) }} me-2"></i>{{ $mlsCardSection['title'] }}
            </span>
            <span class="small text-muted fw-normal">
                {{ \App\Services\ListingImport\Mls\MlsSupplementalDetails::SOURCE_LABEL }}@if($mlsCardNumber) &middot; MLS #{{ $mlsCardNumber }}@endif
            </span>
        </div>
        <div class="card-body">
            @include('offer-listing.partials._mls_facts_rows', [
                'sections'   => [$mlsCardSection],
                'headings'   => false,
                'labelStyle' => $mlsCardLabelStyle,
                'valueStyle' => $mlsCardValueStyle,
            ])
        </div>
    </div>
@endforeach
