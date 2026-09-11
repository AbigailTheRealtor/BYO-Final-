{{--
  matchmaker-important-places — how far this listing is from each of the client's Important Places.
  Section 2 (BidYourOffer Matchmaker Intelligence).

  CALCULATED property location matching — not the client's "Search Areas & Location Preferences",
  which describes what they asked for. Rows carry a category and a distance only.

  Props: $items — ImportantPlaceMatcher::present() rows.
--}}
@props(['items' => []])

@if(count($items) > 0)
<div class="card shadow-sm border-0 mb-4" data-important-place-matches>
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-location-crosshairs me-2" style="color:#0369a1;"></i>Location Match &middot; Important Places
        </h6>
    </div>
    <div class="card-body pt-1 pb-2">
        @include('partials.stellar.important-place-rows', ['items' => $items])
    </div>
</div>
@endif
