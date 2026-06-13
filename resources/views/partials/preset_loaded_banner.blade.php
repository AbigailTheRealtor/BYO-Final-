@if($defaultProfileLoaded)
<div class="alert alert-success d-flex align-items-start gap-2 mb-3" role="alert">
    <i class="fa-solid fa-wand-magic-sparkles mt-1 flex-shrink-0"></i>
    <div>
        <strong>Loaded from your preset</strong> &mdash; These fields were pre-filled from your agent default profile.
        Review all compensation terms before submitting.
        @if(\Illuminate\Support\Facades\Route::has('agent.presets'))
        <a href="{{ route('agent.presets') }}" class="alert-link ms-1">Edit preset &rarr;</a>
        @endif
    </div>
</div>
@endif
