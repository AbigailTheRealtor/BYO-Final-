@php
use App\Support\TenantServicesCatalog;

$propertyType = @$auction->get->property_type ?? 'Residential Property';
$snapshotRaw = @$auction->get->services_snapshot;
$allServices = is_array(@$auction->get->services) ? $auction->get->services : [];
$otherServices = is_array(@$auction->get->other_services) ? $auction->get->other_services : [];

$servicesGrouped = [];

// Priority 1: Use saved snapshot (preserves exact order from creation)
if (!empty($snapshotRaw)) {
    $snapshot = is_string($snapshotRaw) ? json_decode($snapshotRaw, true) : $snapshotRaw;
    if (is_array($snapshot)) {
        $servicesGrouped = TenantServicesCatalog::getCheckedServicesInOrder($snapshot);
    }
}

// Priority 2: Fallback to catalog filtering with canonicalized matching
if (empty($servicesGrouped) && !empty($allServices)) {
    $catalog = TenantServicesCatalog::forPropertyType($propertyType);
    
    // Canonicalization helper for matching (handles smart quotes vs straight quotes)
    $canon = function(string $s): string {
        $s = trim($s);
        $s = str_replace(["'", "'", """, """], ["'", "'", "\"", "\""], $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    };
    
    // Canonicalize all selected services for comparison
    $allServicesCanon = array_map($canon, $allServices);
    
    foreach ($catalog as $sectionName => $sectionItems) {
        $matched = [];
        foreach ($sectionItems as $item) {
            if (in_array($canon($item), $allServicesCanon, true)) {
                $matched[] = $item;
            }
        }
        if (!empty($matched)) {
            $servicesGrouped[$sectionName] = $matched;
        }
    }
}
@endphp

@if (!empty($servicesGrouped) || !empty($otherServices))
<div class="col-md-12 col-12 pt-2">
    @foreach ($servicesGrouped as $sectionName => $items)
        @if (!empty($items))
        <div class="mt-3">
            <strong>{{ $sectionName }}</strong>
            <ul class="services">
                @foreach ($items as $service)
                <li style="font-size: 16px;">{{ $service }}</li>
                @endforeach
            </ul>
        </div>
        @endif
    @endforeach

    @if (!empty($otherServices))
    <div class="mt-3">
        <strong>✍️ Additional Services</strong>
        <ul class="services">
            @foreach ($otherServices as $other_service)
            <li style="font-size: 16px;">{{ $other_service }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
@endif
