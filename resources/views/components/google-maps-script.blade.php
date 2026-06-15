@props(['libraries' => 'places', 'callback' => null])
@php
    $key = config('services.google.places_key', '');
@endphp

@if($key !== '' && $key !== null)
    @php
        $src = 'https://maps.googleapis.com/maps/api/js?key=' . $key . '&libraries=' . $libraries;
        if ($callback) {
            $src .= '&callback=' . $callback;
        }
    @endphp
    <script async defer src="{{ $src }}"></script>
@else
    <div style="border: 2px solid #f59e0b; background-color: #fffbeb; color: #92400e; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin: 4px 0;">
        &#9888; Google Maps is not configured for this environment &mdash; address autocomplete is unavailable.
    </div>
@endif
