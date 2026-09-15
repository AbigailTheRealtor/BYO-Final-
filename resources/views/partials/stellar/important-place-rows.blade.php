{{--
  One row per Important Place requirement: category, straight-line distance, requested maximum,
  verdict. Shared by the results card, the property detail page and the Match Check report, so the
  three cannot word a distance differently.

  Input: $items — ImportantPlaceMatcher::present() rows
         {type, label, status, matches, actual_display, required_display}.

  These rows are CALCULATED property location matching. They name a category and a distance and
  never the place's address or coordinate — the client's Important Places stay private.
--}}
<ul class="list-unstyled mb-0" style="font-size:.85rem;">
  @foreach ($items as $ipRow)
    @php
      $ipIcon = $ipRow['matches'] === true
          ? 'fas fa-circle-check text-success'
          : ($ipRow['matches'] === false ? 'fas fa-circle-xmark text-danger' : 'fas fa-circle-minus text-muted');
      $ipVerdict = $ipRow['matches'] === true
          ? 'Matches'
          : ($ipRow['matches'] === false ? 'Outside preferred distance' : 'Distance unavailable');
    @endphp
    <li class="d-flex align-items-start gap-2 py-1" data-important-place-row data-status="{{ $ipRow['status'] }}">
      <i class="{{ $ipIcon }} mt-1 flex-shrink-0" style="font-size:.8rem;" aria-hidden="true"></i>
      <span>
        <strong>{{ $ipRow['label'] }}</strong>
        &mdash; {{ $ipRow['actual_display'] ?? 'Distance unavailable' }}
        <span class="text-muted d-block" style="font-size:.78rem;">Preferred: {{ $ipRow['required_display'] }} &middot; {{ $ipVerdict }}</span>
      </span>
    </li>
  @endforeach
</ul>
