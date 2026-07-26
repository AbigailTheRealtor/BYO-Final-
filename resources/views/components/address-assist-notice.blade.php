{{--
  Address assist notice — Phase 0 (Spatial UI Integration).
  ============================================================================
  Inline explanation shown when the app corrected something about the address
  the user typed — today, only when a real US ZIP was entered into the Street
  Address field and we moved it to the ZIP field and filled in the location.

  This is deliberately NOT an error. The user gave us something usable, just in
  the wrong box; telling them what we did with it beats rejecting them. Errors
  are reserved for input we genuinely cannot use.

  Rendered by every Seller/Landlord address surface. The host component supplies
  `$addressAssistNotice` via App\Http\Livewire\Concerns\ValidatesPropertyAddress.
--}}
@props(['notice' => ''])

@if (trim((string) $notice) !== '')
    <div role="status"
         style="border:1px solid #7dd3fc; background-color:#f0f9ff; color:#075985;
                padding:8px 12px; border-radius:4px; font-size:13px; margin:4px 0 12px;">
        <i class="fa-solid fa-circle-info" style="margin-right:6px;"></i>{{ $notice }}
    </div>
@endif
