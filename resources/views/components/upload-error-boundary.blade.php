{{--
    Launch-audit #7 — scoped upload-error boundary.

    Livewire v2 (FileUploads.js) dispatches `livewire-upload-error` ON THE FILE INPUT with
    `{ bubbles: true }` and NO `detail` payload — there is no property name to filter on:

        let error = () => el.dispatchEvent(new CustomEvent('livewire-upload-error', { bubbles: true }))

    Two consequences drive this component:

    1. Without any listener the browser/PHP rejection is a SILENT no-op — the user picks a
       file, nothing happens, and no error is shown. That is the #7 defect.

    2. Listening on `.window` "works" but is wrong: the window sees the error from EVERY file
       input in the page, so an upload started on the Documents tab lights up an alert that
       lives in the Photos tab — inside a `.tab-pane` that is not `show active`. The user
       still sees nothing. Same silent failure, different cause.

    Because the event BUBBLES from the input, the fix is to listen on an element that CONTAINS
    the input. Wrap each upload surface in this component and the alert reacts only to its own
    input, in the pane the user is actually looking at.

    Usage:
        <x-upload-error-boundary message="Upload failed — ...">
            <input type="file" wire:model="photo">
        </x-upload-error-boundary>
--}}
@props([
    'message' => 'Upload failed — the file may be too large to send. Please choose a smaller file and try again.',
])

{{-- NOTE: deliberately NOT `.window` — see above. The listener must stay scoped to the
     subtree that holds the input, or the alert renders in a hidden tab pane. --}}
<div x-data="{ uploadErr: '' }"
    x-on:livewire-upload-start="uploadErr = ''"
    x-on:livewire-upload-error="uploadErr = @js($message)"
    {{ $attributes }}>
    {{ $slot }}

    <template x-if="uploadErr">
        <div class="alert alert-danger d-flex align-items-center gap-2 mt-2 mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span x-text="uploadErr"></span>
        </div>
    </template>
</div>
