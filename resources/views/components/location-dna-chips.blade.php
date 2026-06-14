@props(['chips' => [], 'overflow' => 0])

@if (!empty($chips))
    <div class="location-dna-chips mb-1" style="display: flex; flex-wrap: wrap; gap: 4px;">
        @foreach ($chips as $chip)
            <span class="badge"
                  style="background-color: #e8f4f8; color: #2c6e8a; font-size: 0.7rem; font-weight: 500; padding: 3px 7px; border-radius: 12px; white-space: nowrap;">
                📍 {{ $chip }}
            </span>
        @endforeach
        @if ($overflow > 0)
            <span class="badge"
                  style="background-color: #f0f0f0; color: #6c757d; font-size: 0.7rem; font-weight: 400; padding: 3px 7px; border-radius: 12px;">
                +{{ $overflow }} more
            </span>
        @endif
    </div>
@endif
