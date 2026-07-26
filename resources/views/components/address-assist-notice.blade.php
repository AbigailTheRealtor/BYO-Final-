{{--
  "We moved your ZIP" inline notice — Phase 0 (Spatial UI Integration).
  ============================================================================
  Renders the explanation written by
  App\Http\Livewire\Concerns\ValidatesPropertyAddress::assistPropertyAddress()
  when a real US ZIP is typed into the street-address field.

  WHY THIS IS A NOTICE AND NOT AN ERROR
  ─────────────────────────────────────
  Nothing went wrong. The user typed `33708` where the street address goes; we
  knew what they meant, moved it to the ZIP field, and filled in the city, county
  and state it implies. Styling this red would tell them they made a mistake that
  needs correcting, when in fact the correction has already happened and the only
  thing left to do is type the street line. So it is informational blue, sits
  directly beneath the street field it refers to, and disappears the moment the
  component clears the notice.

  role="status" rather than role="alert": this is a polite announcement of a
  completed action, not an interruption. Screen readers should reach it in
  document order rather than being pulled out of the field the user is typing in.

  Props
  -----
  notice (string) the message; falsy renders nothing at all
--}}
@props(['notice' => ''])

@if(filled($notice))
    <div role="status"
         class="d-flex align-items-start gap-2 mt-2"
         style="border: 1px solid #93c5fd; background-color: #eff6ff; color: #1e40af;
                padding: 8px 12px; border-radius: 4px; font-size: 13px; line-height: 1.45;">
        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
        <span>{{ $notice }}</span>
    </div>
@endif
