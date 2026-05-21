<!-- Section Heading -->
<h3>Describe your criteria, preferences, and requirements.</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📋 Share your criteria and preferences to help interested parties understand exactly what you are looking for.</strong>
        </div>
    </div>
</div>
<div class="form-group">
    <label class="fw-bold">Criteria &amp; Preferences</label>
    <div class="input-cover">
        <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
            placeholder="@php
    $placeholderMap = [
        'Residential'          => 'Describe your ideal home (e.g., 3-bed/2-bath single-family in a quiet neighborhood, close to schools)',
        'Commercial'           => 'Describe your commercial property needs (e.g., 2,000 sq ft office space, ground-floor retail preferred)',
        'Business Opportunity' => 'Describe the business you are looking for (e.g., established restaurant with existing lease, under \$500K)',
        'Vacant Land'          => 'Describe the land you are seeking (e.g., 5+ acres zoned agricultural, flat terrain, road access)',
        'Income'               => 'Describe your investment criteria (e.g., 4-unit multifamily, cap rate 6%+, value-add opportunity)',
    ];
    echo $placeholderMap[$property_type ?? ''] ?? 'Enter your criteria and preferences (e.g., 3-bedroom home, pet-friendly, close to downtown)';
@endphp"></textarea>
    </div>
</div>
