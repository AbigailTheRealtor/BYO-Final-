{{--
  Stellar Property Photo Carousel
  ================================
  Reusable photo display component shared across all property detail flows.

  Props:
    $photos  — array of MediaURL strings (already allowlisted by PropertyDetailViewMapper)
    $address — optional string used as alt text (e.g. unparsed_address)
--}}
@props(['photos' => [], 'address' => null])

@php
    $alt   = e($address ?? 'Property photo');
    $count = count($photos);
@endphp

@if($count === 0)
    {{-- Placeholder when no photos are available --}}
    <div class="d-flex align-items-center justify-content-center bg-light text-muted"
         style="height:360px;border-radius:8px;border:1px solid #e2e8f0;">
        <div class="text-center">
            <i class="fas fa-house fa-4x mb-3 opacity-25"></i>
            <div style="font-size:.9rem;">No photos available</div>
        </div>
    </div>

@elseif($count === 1)
    <div style="border-radius:8px;overflow:hidden;background:#000;max-height:420px;">
        <img src="{{ $photos[0] }}"
             alt="{{ $alt }}"
             class="d-block w-100"
             style="max-height:420px;object-fit:cover;">
    </div>

@else
    @php $carouselId = 'prop-carousel-' . substr(md5($photos[0] . $count), 0, 8); @endphp

    <div id="{{ $carouselId }}"
         class="carousel slide position-relative"
         data-bs-ride="false"
         style="border-radius:8px;overflow:hidden;background:#000;max-height:420px;">

        {{-- Dot indicators (shown for ≤ 30 photos; hidden on mobile to save space) --}}
        @if($count <= 30)
        <div class="carousel-indicators d-none d-md-flex">
            @for($i = 0; $i < $count; $i++)
                <button type="button"
                        data-bs-target="#{{ $carouselId }}"
                        data-bs-slide-to="{{ $i }}"
                        {{ $i === 0 ? 'class=active aria-current=true' : '' }}
                        aria-label="Photo {{ $i + 1 }}"></button>
            @endfor
        </div>
        @endif

        <div class="carousel-inner">
            @foreach($photos as $i => $url)
                <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                    <img src="{{ $url }}"
                         alt="{{ $alt }} — photo {{ $i + 1 }}"
                         class="d-block w-100"
                         style="max-height:420px;object-fit:cover;"
                         {{ $i > 0 ? 'loading=lazy' : '' }}>
                </div>
            @endforeach
        </div>

        <button class="carousel-control-prev" type="button"
                data-bs-target="#{{ $carouselId }}" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous photo</span>
        </button>
        <button class="carousel-control-next" type="button"
                data-bs-target="#{{ $carouselId }}" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next photo</span>
        </button>

        {{-- Photo counter badge --}}
        <div class="position-absolute bottom-0 end-0 m-2" style="z-index:10;">
            <span class="badge bg-dark bg-opacity-75 px-2 py-1" style="font-size:.75rem;">
                <i class="fas fa-camera me-1"></i>{{ $count }}
            </span>
        </div>
    </div>
@endif
