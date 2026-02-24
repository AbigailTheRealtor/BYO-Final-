@props(['categories' => [], 'limit' => 6])
@if (!empty($categories))
@foreach ($categories as $catIndex => $category)
@php
    $catTitle = $category['title'] ?? 'Services';
    $items = $category['items'] ?? [];
    $items = array_filter($items, function($item) {
        $val = trim((string) $item);
        return $val !== '' && $val !== 'Example 1' && $val !== 'Example 2'
            && stripos($val, 'Example:') !== 0;
    });
    $items = array_values($items);
    $total = count($items);
    $visible = array_slice($items, 0, $limit);
    $hidden = array_slice($items, $limit);
    $collapseId = 'svc-' . $catIndex . '-' . uniqid();
@endphp
@if ($total > 0)
<div class="mb-3">
    <h6 class="fw-bold">{{ $catTitle }}</h6>
    <ul class="list-unstyled mb-1">
        @foreach ($visible as $item)
        <li class="ps-2">• {{ $item }}</li>
        @endforeach
    </ul>
    @if (!empty($hidden))
    <div class="collapse" id="{{ $collapseId }}">
        <ul class="list-unstyled mb-1">
            @foreach ($hidden as $item)
            <li class="ps-2">• {{ $item }}</li>
            @endforeach
        </ul>
    </div>
    <a class="small text-decoration-none" data-bs-toggle="collapse" href="#{{ $collapseId }}" role="button"
        aria-expanded="false" aria-controls="{{ $collapseId }}">
        Show all ({{ $total }})
    </a>
    @endif
</div>
@endif
@endforeach
@endif
