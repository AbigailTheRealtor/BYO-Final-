@props(['title' => 'Client Info', 'auction', 'user' => null])
@php
    $meta = $auction->get ?? (object)[];
    $clientUser = $user ?? $auction->user ?? null;
    $photos = $meta->photo ?? $meta->photos ?? [];
    if (is_string($photos)) { $photos = json_decode($photos, true) ?? []; }
    if (!is_array($photos)) { $photos = []; }
    $videoUrl = $meta->video ?? $meta->video_url ?? null;
@endphp
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header section-header">
        <h4 class="section-title">{{ $title }}</h4>
    </div>
    <div class="card-body">
        @if (!empty($photos))
        <div class="row mb-3">
            @foreach ($photos as $photo)
            <div class="col-6 col-md-4 mb-2">
                <img src="{{ asset('storage/' . $photo) }}" class="img-fluid rounded" style="max-height: 200px; object-fit: cover; width: 100%;" alt="Client Photo">
            </div>
            @endforeach
        </div>
        @endif
        @if (!empty($videoUrl))
        <div class="mb-3">
            <video controls class="w-100 rounded" style="max-height: 300px;">
                <source src="{{ asset('storage/' . $videoUrl) }}" type="video/mp4">
            </video>
        </div>
        @endif
        {{ $slot }}
    </div>
</div>
