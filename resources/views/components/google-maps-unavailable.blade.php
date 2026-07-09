{{--
  "Google Maps is not configured" notice — Phase 0 item 1 (SIP-P15, INV-12).
  ============================================================================
  The single source of truth for the degraded-state panel. Previously this markup was
  copy-pasted into five Blade files, so a wording or styling fix had to be made five
  times and inevitably was not.

  Per erratum E-43, the Phase 0 address degrade is **free-text entry only**. The field
  still accepts whatever the user types; only the autocomplete suggestions are absent.
  There is deliberately no "pin confirmation" here: pin confirmation presupposes a map,
  and with no credential there is no map. That capability arrives with MapLibre in
  Phase 5.
--}}
@props(['message' => 'Google Maps is not configured for this environment — address autocomplete is unavailable. You can still type the address manually.'])

<div role="status"
     style="border: 2px solid #f59e0b; background-color: #fffbeb; color: #92400e; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin: 4px 0;">
    &#9888; {{ $message }}
</div>
