@php
use App\Support\TenantServicesCatalog;

$propertyType = @$auction->get->property_type ?? 'Residential Property';
$snapshotRaw = @$auction->get->services_snapshot;
$allServices = is_array(@$auction->get->services) ? $auction->get->services : [];
$otherServices = is_array(@$auction->get->other_services) ? $auction->get->other_services : [];

$servicesGrouped = [];

if (!empty($snapshotRaw)) {
    $snapshot = is_string($snapshotRaw) ? json_decode($snapshotRaw, true) : $snapshotRaw;
    if (is_array($snapshot)) {
        $servicesGrouped = TenantServicesCatalog::getCheckedServicesInOrder($snapshot);
    }
}

if (empty($servicesGrouped) && !empty($allServices)) {
    $catalog = TenantServicesCatalog::forPropertyType($propertyType);
    foreach ($catalog as $sectionName => $sectionItems) {
        $matched = array_filter($sectionItems, fn($item) => in_array($item, $allServices, true));
        if (!empty($matched)) {
            $servicesGrouped[$sectionName] = array_values($matched);
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
