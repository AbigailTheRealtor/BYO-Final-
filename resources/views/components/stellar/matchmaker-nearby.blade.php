{{--
  matchmaker-nearby — nearby amenities from Location DNA pipeline.
  Section 2 (BidYourOffer Matchmaker Intelligence).
  Props: $locationSummary (full LocationDnaSummaryService response, or [])
--}}
@props(['locationSummary' => []])

@php
    $status  = $locationSummary['status'] ?? null;
    $summary = ($status === 'completed') ? ($locationSummary['summary'] ?? null) : null;
    $byCategory = $summary['nearest_by_category'] ?? [];

    // Thematic groups: [display_label => [poi_category, ...]]
    $themeGroups = [
        'Coastal'            => ['beach', 'beach_access', 'boat_ramp', 'marina'],
        'Beaches'            => ['beach', 'beach_access'],
        'Parks & Recreation' => ['park', 'dog_park', 'golf_course', 'waterfront_park'],
        'Daily Convenience'  => ['grocery_store', 'pharmacy', 'coffee_shop', 'restaurant', 'top_rated_dining'],
        'Restaurants'        => ['restaurant', 'top_rated_dining'],
        'Transportation'     => ['transit_station', 'gas_station'],
    ];

    // Deduplicate: build a flat ordered list of (group, poi_category) pairs
    // using the thematic blocks from the summary for clean labels
    $coastal      = $summary['coastal']           ?? [];
    $convenience  = $summary['daily_convenience'] ?? [];
    $outdoor      = $summary['outdoor_recreation'] ?? [];
    $transport    = $summary['transportation']     ?? [];

    // Label map for distance keys
    $distanceLabels = [
        'nearest_beach_miles'           => 'Beach',
        'nearest_beach_access_miles'    => 'Beach Access',
        'nearest_boat_ramp_miles'       => 'Boat Ramp',
        'nearest_marina_miles'          => 'Marina',
        'nearest_grocery_miles'         => 'Grocery Store',
        'nearest_pharmacy_miles'        => 'Pharmacy',
        'nearest_coffee_miles'          => 'Coffee Shop',
        'nearest_restaurant_miles'      => 'Restaurant',
        'nearest_top_rated_dining_miles'=> 'Top Dining',
        'nearest_park_miles'            => 'Park',
        'nearest_dog_park_miles'        => 'Dog Park',
        'nearest_golf_course_miles'     => 'Golf Course',
        'nearest_waterfront_park_miles' => 'Waterfront Park',
        'nearest_transit_miles'         => 'Transit',
        'nearest_gas_station_miles'     => 'Gas Station',
    ];

    $sections = array_filter([
        'Coastal'            => $coastal,
        'Daily Convenience'  => $convenience,
        'Parks & Recreation' => $outdoor,
        'Transportation'     => $transport,
    ], fn($s) => count($s) > 0);

    // Get POI name from nearest_by_category for a given key
    // e.g. 'nearest_beach_miles' → poi_category 'beach'
    $keyToCategory = [
        'nearest_beach_miles'           => 'beach',
        'nearest_beach_access_miles'    => 'beach_access',
        'nearest_boat_ramp_miles'       => 'boat_ramp',
        'nearest_marina_miles'          => 'marina',
        'nearest_grocery_miles'         => 'grocery_store',
        'nearest_pharmacy_miles'        => 'pharmacy',
        'nearest_coffee_miles'          => 'coffee_shop',
        'nearest_restaurant_miles'      => 'restaurant',
        'nearest_top_rated_dining_miles'=> 'top_rated_dining',
        'nearest_park_miles'            => 'park',
        'nearest_dog_park_miles'        => 'dog_park',
        'nearest_golf_course_miles'     => 'golf_course',
        'nearest_waterfront_park_miles' => 'waterfront_park',
        'nearest_transit_miles'         => 'transit_station',
        'nearest_gas_station_miles'     => 'gas_station',
    ];
@endphp

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-1">
        <h6 class="mb-0 fw-semibold" style="font-size:.9rem;color:#374151;">
            <i class="fas fa-location-dot me-2" style="color:#0ea5e9;"></i>Nearby Amenities
        </h6>
    </div>
    <div class="card-body pt-2 pb-3">

        @if($summary && count($sections) > 0)
            @foreach($sections as $sectionLabel => $sectionData)
                <div class="mb-3">
                    <div class="text-muted mb-1" style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">
                        {{ $sectionLabel }}
                    </div>
                    <div class="d-flex flex-column gap-1">
                        @foreach($sectionData as $distKey => $miles)
                            @if($miles !== null)
                            @php
                                $label   = $distanceLabels[$distKey] ?? $distKey;
                                $catKey  = $keyToCategory[$distKey] ?? null;
                                $poiName = $catKey ? ($byCategory[$catKey]['name'] ?? null) : null;
                                $miles   = round((float)$miles, 1);
                            @endphp
                            <div class="d-flex justify-content-between align-items-center" style="font-size:.875rem;">
                                <span>
                                    {{ $label }}
                                    @if($poiName)
                                        <span class="text-muted" style="font-size:.8rem;">— {{ $poiName }}</span>
                                    @endif
                                </span>
                                <span class="badge bg-light text-dark border" style="font-size:.78rem;font-weight:500;white-space:nowrap;">
                                    {{ $miles }} mi
                                </span>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach

        @elseif($status === 'completed')
            <p class="text-muted mb-0" style="font-size:.875rem;">
                No nearby amenity data was found for this property.
            </p>

        @else
            <div class="d-flex align-items-center gap-2 text-muted" style="font-size:.875rem;">
                <i class="fas fa-clock text-secondary"></i>
                Location analysis not yet available for this property. Check back shortly.
            </div>
        @endif

    </div>
</div>
