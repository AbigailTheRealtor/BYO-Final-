@props(['items' => [], 'otherText' => null])
@php
    $normalized = \App\Helpers\ListingDisplayHelper::normalizeList($items, $otherText);
@endphp
@if (!empty($normalized))
    @foreach ($normalized as $item)
        <span class="removeBold badge bg-secondary">{{ $item }}</span>
    @endforeach
@endif
