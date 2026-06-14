{{--
    Match Explanation — read-only display.

    Available from caller:
      $metas — metas collection (plucked key → value)

    Renders nothing when neither field is populated.
--}}
@if($metas->get('match_explanation') || $metas->get('match_compromise_note'))
<p class="fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dee2e6;padding-bottom:4px;margin:0 0 8px;">Match Explanation</p>
<dl class="row mb-0">
    @if($metas->get('match_explanation'))
    <dt class="col-sm-3">Why It Matches</dt>
    <dd class="col-sm-9" style="white-space:pre-wrap;">{{ $metas->get('match_explanation') }}</dd>
    @endif
    @if($metas->get('match_compromise_note'))
    <dt class="col-sm-3">Compromises / Notes</dt>
    <dd class="col-sm-9" style="white-space:pre-wrap;">{{ $metas->get('match_compromise_note') }}</dd>
    @endif
</dl>
@endif
