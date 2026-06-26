{{--
  property-office — listing office name and IDX copyright notice.
  Section 1 (MLS Listing Information). IDX-permitted display.
--}}
@props(['officeName' => null])

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-2 px-3">
        @if($officeName)
            <div class="text-muted mb-1" style="font-size:.82rem;">
                <i class="fas fa-building me-1"></i>Listed by <strong>{{ $officeName }}</strong>
            </div>
        @endif
        <div style="font-size:.75rem;color:#9ca3af;line-height:1.5;">
            Information provided by Stellar MLS via Bridge Data Output. All information is deemed reliable
            but not guaranteed and should be independently verified. Listing data last updated.
            &copy; {{ date('Y') }} Stellar MLS.
        </div>
    </div>
</div>
