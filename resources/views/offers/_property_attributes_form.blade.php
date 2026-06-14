{{--
    Property Attribute Fields — property-type-conditional detail question groups.

    Replicates the same conditional field groups from:
      - offer-seller-tabs/commission-based/property-preferences.blade.php  (buyer role)
      - offer-landlord-tabs/commission-based/property-preferences.blade.php (tenant role)
    …using identical labels, option lists (from config), and type-conditional logic.

    Conditions match exactly — in the seller form the @if blocks use wire:model; here
    we use Alpine x-show against the parent x-data's `propType` reactive variable.

    MUST be included INSIDE the parent x-data block that exposes `propType`.
    Adds a nested x-data for garageChoice / poolChoice / bedroomsChoice / bathroomsChoice.

    Available from caller:
      $pm        — metas collection (plucked key → value)
      $isTenant  — bool (true = tenant responding, false = buyer responding)

    Buyer role types : Residential | Income | Commercial | Business | Vacant Land
    Tenant role types: Residential Property | Commercial Property
--}}
@php
    // ── Option lists — sourced from config (single source of truth) ──────────
    // bedroom_options  matches $bedroomsRes in property-preferences.blade.php (1–10, Other).
    // bathroom_options matches $bathroomOptions in property-preferences.blade.php.
    // acreage_options  matches $acreageRes in property-preferences.blade.php.
    $attrBedroomOptions  = config('property_types.bedroom_options');
    $attrBathroomOptions = config('property_types.bathroom_options');
    $attrAcreageOptions  = config('property_types.acreage_options');

    // ── Saved attribute values ────────────────────────────────────────────────
    $savedCondition      = old('prop_attr_condition',          $pm->get('prop_attr_condition',          ''));
    $savedBedrooms       = old('prop_attr_bedrooms',           $pm->get('prop_attr_bedrooms',           ''));
    $savedOtherBeds      = old('prop_attr_other_bedrooms',     $pm->get('prop_attr_other_bedrooms',     ''));
    $savedBathrooms      = old('prop_attr_bathrooms',          $pm->get('prop_attr_bathrooms',          ''));
    $savedOtherBaths     = old('prop_attr_other_bathrooms',    $pm->get('prop_attr_other_bathrooms',    ''));
    $savedHeatedSqft     = old('prop_attr_heated_sqft',        $pm->get('prop_attr_heated_sqft',        ''));
    $savedNetLeasable    = old('prop_attr_net_leasable_sqft',  $pm->get('prop_attr_net_leasable_sqft',  ''));
    $savedTotalSqft      = old('prop_attr_total_sqft',         $pm->get('prop_attr_total_sqft',         ''));
    $savedSqftSource     = old('prop_attr_sqft_source',        $pm->get('prop_attr_sqft_source',        ''));
    $savedAcreage        = old('prop_attr_total_acreage',      $pm->get('prop_attr_total_acreage',      ''));
    $savedGarage         = old('prop_attr_garage',             $pm->get('prop_attr_garage',             ''));
    $savedGarageSpaces   = old('prop_attr_garage_spaces',      $pm->get('prop_attr_garage_spaces',      ''));
    $savedPool           = old('prop_attr_pool',               $pm->get('prop_attr_pool',               ''));
    $savedPoolPrivate    = old('prop_attr_pool_private',       $pm->get('prop_attr_pool_private',       ''));
    $savedPoolCommunity  = old('prop_attr_pool_community',     $pm->get('prop_attr_pool_community',     ''));
    $savedYearBuilt      = old('prop_attr_year_built',         $pm->get('prop_attr_year_built',         ''));
    $savedZoning         = old('prop_attr_zoning',             $pm->get('prop_attr_zoning',             ''));

    // ── Property condition options — exact lists from seller / landlord forms ─
    // Buyer context  → matches $property_condition_seller (seller-agent-auction-tabs/property-preferences.blade.php line ~211).
    // Tenant context → matches $property_condition_landlord (offer-landlord-tabs/property-preferences.blade.php line ~318).
    if ($isTenant) {
        $conditionOptions = [
            'New Construction',
            'Updated / Renovated',
            'Partially Updated',
            'Older but Well Maintained',
        ];
    } else {
        $conditionOptions = [
            'No updates needed: Completely updated',
            'Currently being built',
            'New Construction',
            'Not updated: Requires a complete update',
            'Pre-Construction',
            'Semi-updated: Needs minor updates',
            'Tear Down: Requires complete demolition and reconstruction',
        ];
    }

    // ── Alpine x-show expressions — role-specific conditional logic ──────────
    //
    // Tenant (landlord form source — conditions taken verbatim from @if blocks):
    //   Property Condition  : no @if wrapper in source → propType !== ''
    //   Bedrooms            : $property_type === 'Residential Property'
    //   Bathrooms           : in_array($property_type, ['Residential Property', 'Commercial Property'])
    //   Heated SqFt         : $property_type === 'Residential Property'
    //   Net Leasable SqFt   : $property_type === 'Commercial Property'
    //   Total SqFt          : no @if wrapper → propType !== ''
    //   SqFt Heated Source  : no @if wrapper → propType !== ''
    //   Total Acreage       : no @if wrapper → propType !== ''
    //   Garage              : $property_type === 'Residential Property'
    //   Pool                : $property_type === 'Residential Property'
    //   Year Built          : 'Residential Property' block + 'Commercial Property' block → propType !== ''
    //   Zoning              : $property_type === 'Commercial Property'
    //
    // Buyer (seller form source — conditions taken verbatim from @if blocks):
    //   Property Condition  : $property_type != 'Vacant Land'
    //   Bedrooms            : $property_type === 'Residential'
    //   Bathrooms           : in_array($property_type, ['Residential', 'Business', 'Commercial'])
    //   Heated SqFt         : in_array($property_type, ['Residential', 'Business', 'Commercial'])
    //   Net Leasable SqFt   : not applicable for buyer → hidden
    //   Total SqFt          : $property_type != 'Vacant Land'
    //   SqFt Heated Source  : $property_type != 'Vacant Land' (same block as Total SqFt)
    //   Total Acreage       : no @if wrapper → propType !== ''
    //   Garage              : $property_type === 'Residential'
    //   Pool                : in_array($property_type, ['Residential', 'Income'])
    //   Year Built          : 'Residential'+'Income' block + 'Commercial' block + 'Business' block → all except Vacant Land
    //   Zoning              : 'Commercial' block + 'Business' block + 'Vacant Land' block → those three types
    if ($isTenant) {
        $xsCondition   = "propType !== ''";
        $xsBedrooms    = "propType === 'Residential Property'";
        $xsBathrooms   = "propType !== ''";
        $xsHeated      = "propType === 'Residential Property'";
        $xsNetLease    = "propType === 'Commercial Property'";
        $xsTotalSqft   = "propType !== ''";
        $xsSqftSource  = "propType !== ''";
        $xsAcreage     = "propType !== ''";
        $xsGarage      = "propType === 'Residential Property'";
        $xsGarSpaces   = "propType === 'Residential Property' && garageChoice === 'Yes'";
        $xsPool        = "propType === 'Residential Property'";
        $xsPoolType    = "propType === 'Residential Property' && poolChoice === 'Yes'";
        $xsYearBuilt   = "propType !== ''";
        $xsZoning      = "propType === 'Commercial Property'";
    } else {
        $xsCondition   = "propType !== '' && propType !== 'Vacant Land'";
        $xsBedrooms    = "propType === 'Residential'";
        $xsBathrooms   = "propType === 'Residential' || propType === 'Business' || propType === 'Commercial'";
        $xsHeated      = "propType === 'Residential' || propType === 'Business' || propType === 'Commercial'";
        $xsNetLease    = "false";   // not applicable for buyer role
        $xsTotalSqft   = "propType !== '' && propType !== 'Vacant Land'";
        $xsSqftSource  = "propType !== '' && propType !== 'Vacant Land'";
        $xsAcreage     = "propType !== ''";
        $xsGarage      = "propType === 'Residential'";
        $xsGarSpaces   = "propType === 'Residential' && garageChoice === 'Yes'";
        $xsPool        = "propType === 'Residential' || propType === 'Income'";
        $xsPoolType    = "(propType === 'Residential' || propType === 'Income') && poolChoice === 'Yes'";
        // Year Built: Residential + Income (line 1888) + Commercial (line 2111) + Business (line ~2501) = all except Vacant Land
        $xsYearBuilt   = "propType !== '' && propType !== 'Vacant Land'";
        // Zoning: Commercial (line 2127) + Business (line 2508) + Vacant Land (line 2767)
        $xsZoning      = "propType === 'Commercial' || propType === 'Business' || propType === 'Vacant Land'";
    }

    // ── Server-side initial display (mirrors x-show, pre-Alpine hydration) ───
    $sPropType    = old('prop_type', $pm->get('prop_type', ''));
    $spCondition  = $isTenant
        ? $sPropType !== ''
        : ($sPropType !== '' && $sPropType !== 'Vacant Land');
    $spBedrooms   = $isTenant
        ? $sPropType === 'Residential Property'
        : $sPropType === 'Residential';
    $spBathrooms  = $isTenant
        ? $sPropType !== ''
        : in_array($sPropType, ['Residential', 'Business', 'Commercial']);
    $spHeated     = $isTenant
        ? $sPropType === 'Residential Property'
        : in_array($sPropType, ['Residential', 'Business', 'Commercial']);
    $spNetLease   = $isTenant && $sPropType === 'Commercial Property';
    $spTotalSqft  = $isTenant
        ? $sPropType !== ''
        : ($sPropType !== '' && $sPropType !== 'Vacant Land');
    $spSqftSource = $spTotalSqft;
    $spAcreage    = $sPropType !== '';
    $spGarage     = $isTenant
        ? $sPropType === 'Residential Property'
        : $sPropType === 'Residential';
    $spGarSpaces  = $spGarage && $savedGarage === 'Yes';
    $spPool       = $isTenant
        ? $sPropType === 'Residential Property'
        : in_array($sPropType, ['Residential', 'Income']);
    $spPoolType   = $spPool && $savedPool === 'Yes';
    $spYearBuilt  = $isTenant
        ? $sPropType !== ''
        : ($sPropType !== '' && $sPropType !== 'Vacant Land');
    $spZoning     = $isTenant
        ? $sPropType === 'Commercial Property'
        : in_array($sPropType, ['Commercial', 'Business', 'Vacant Land']);
@endphp

{{-- ── Property Attributes ─────────────────────────────────────────────────── --}}
<p class="offer-section-header mt-4">Property Attributes</p>

<div x-data="{
    garageChoice: '{{ addslashes($savedGarage) }}',
    poolChoice: '{{ addslashes($savedPool) }}',
    bedroomsChoice: '{{ addslashes($savedBedrooms) }}',
    bathroomsChoice: '{{ addslashes($savedBathrooms) }}'
}">

    {{-- ── Property Condition ─────────────────────────────────────────────── --}}
    <div class="mb-3"
        x-show="{{ $xsCondition }}"
        style="display:{{ $spCondition ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Property Condition</label>
        <select name="prop_attr_condition" class="form-select">
            <option value="">Select</option>
            @foreach($conditionOptions as $opt)
                <option value="{{ $opt }}" {{ $savedCondition === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>

    {{-- ── Bedrooms (Residential / Residential Property only) ──────────────── --}}
    <div class="mb-3"
        x-show="{{ $xsBedrooms }}"
        style="display:{{ $spBedrooms ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Bedrooms</label>
        <select name="prop_attr_bedrooms" class="form-select" x-model="bedroomsChoice">
            <option value="">Select</option>
            @foreach($attrBedroomOptions as $opt)
                <option value="{{ $opt }}" {{ $savedBedrooms === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3"
        x-show="{{ $xsBedrooms }} && bedroomsChoice === 'Other'"
        style="display:{{ ($spBedrooms && $savedBedrooms === 'Other') ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Number of Bedrooms</label>
        <input type="number" name="prop_attr_other_bedrooms" class="form-control"
            value="{{ $savedOtherBeds }}"
            placeholder="Enter number of bedrooms (e.g., 11)" min="1">
    </div>

    {{-- ── Bathrooms ───────────────────────────────────────────────────────── --}}
    {{-- Buyer:  Residential | Business | Commercial  (seller form line 818)       --}}
    {{-- Tenant: Residential Property | Commercial Property  (landlord form line 402) --}}
    <div class="mb-3"
        x-show="{{ $xsBathrooms }}"
        style="display:{{ $spBathrooms ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Bathrooms</label>
        <select name="prop_attr_bathrooms" class="form-select" x-model="bathroomsChoice">
            <option value="">Select</option>
            @foreach($attrBathroomOptions as $opt)
                <option value="{{ $opt }}" {{ $savedBathrooms === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3"
        x-show="{{ $xsBathrooms }} && bathroomsChoice === 'Other'"
        style="display:{{ ($spBathrooms && $savedBathrooms === 'Other') ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Number of Bathrooms</label>
        <input type="number" name="prop_attr_other_bathrooms" class="form-control"
            value="{{ $savedOtherBaths }}"
            placeholder="Enter number of bathrooms (e.g., 11)" min="1" step="0.5">
    </div>

    {{-- ── Heated SqFt ─────────────────────────────────────────────────────── --}}
    {{-- Buyer: Residential | Business | Commercial  (seller form line 868)    --}}
    {{-- Tenant: Residential Property only  (landlord form line 435)           --}}
    <div class="mb-3"
        x-show="{{ $xsHeated }}"
        style="display:{{ $spHeated ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Heated SqFt</label>
        <input type="text" name="prop_attr_heated_sqft" class="form-control"
            value="{{ $savedHeatedSqft }}"
            placeholder="Enter heated square footage (e.g., 1500)">
    </div>

    {{-- ── Net Leasable SqFt (tenant / Commercial Property only) ─────────── --}}
    {{-- Mirrors landlord form line 455: $property_type === 'Commercial Property' --}}
    @if($isTenant)
    <div class="mb-3"
        x-show="{{ $xsNetLease }}"
        style="display:{{ $spNetLease ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Net Leasable SqFt</label>
        <input type="text" name="prop_attr_net_leasable_sqft" class="form-control"
            value="{{ $savedNetLeasable }}"
            placeholder="Enter net leasable square footage (e.g., 1500)">
    </div>
    @endif

    {{-- ── Total SqFt ──────────────────────────────────────────────────────── --}}
    {{-- Buyer:  $property_type != 'Vacant Land'  (seller form line 885)        --}}
    {{-- Tenant: no @if wrapper in landlord form (lines 473–488) → all types    --}}
    <div class="mb-3"
        x-show="{{ $xsTotalSqft }}"
        style="display:{{ $spTotalSqft ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Total SqFt</label>
        <input type="text" name="prop_attr_total_sqft" class="form-control"
            value="{{ $savedTotalSqft }}"
            placeholder="Enter total square footage (e.g., 2000)">
    </div>

    {{-- ── SqFt Heated Source ──────────────────────────────────────────────── --}}
    {{-- Buyer:  $property_type != 'Vacant Land'  (seller form line 885 block)  --}}
    {{-- Tenant: no @if wrapper in landlord form (lines 490–506) → all types    --}}
    <div class="mb-3"
        x-show="{{ $xsSqftSource }}"
        style="display:{{ $spSqftSource ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">SqFt Heated Source</label>
        <select name="prop_attr_sqft_source" class="form-select">
            <option value="">Select</option>
            @foreach(['Appraisal','Builder','Measured','Owner Provided','Public Records'] as $src)
                <option value="{{ $src }}" {{ $savedSqftSource === $src ? 'selected' : '' }}>{{ $src }}</option>
            @endforeach
        </select>
    </div>

    {{-- ── Total Acreage (all types, no @if wrapper in both source forms) ─── --}}
    <div class="mb-3"
        x-show="{{ $xsAcreage }}"
        style="display:{{ $spAcreage ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Total Acreage</label>
        <select name="prop_attr_total_acreage" class="form-select">
            <option value="">Select</option>
            @foreach($attrAcreageOptions as $opt)
                <option value="{{ $opt }}" {{ $savedAcreage === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>

    {{-- ── Garage ───────────────────────────────────────────────────────────── --}}
    {{-- Buyer:  $property_type === 'Residential'         (seller form line 1026)  --}}
    {{-- Tenant: $property_type === 'Residential Property' (landlord form line 615) --}}
    <div class="mb-3"
        x-show="{{ $xsGarage }}"
        style="display:{{ $spGarage ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Garage</label>
        <select name="prop_attr_garage" class="form-select" x-model="garageChoice">
            <option value="">Select</option>
            <option value="Yes" {{ $savedGarage === 'Yes' ? 'selected' : '' }}>Yes</option>
            <option value="No"  {{ $savedGarage === 'No'  ? 'selected' : '' }}>No</option>
        </select>
    </div>
    <div class="mb-3"
        x-show="{{ $xsGarSpaces }}"
        style="display:{{ $spGarSpaces ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Number of Garage Spaces</label>
        <input type="number" name="prop_attr_garage_spaces" class="form-control"
            value="{{ $savedGarageSpaces }}"
            placeholder="Enter number of garage spaces (e.g., 2)" min="1">
    </div>

    {{-- ── Pool ────────────────────────────────────────────────────────────── --}}
    {{-- Buyer:  in_array(['Residential','Income'])        (seller form line 1251)  --}}
    {{-- Tenant: $property_type === 'Residential Property' (landlord form line 794) --}}
    <div class="mb-3"
        x-show="{{ $xsPool }}"
        style="display:{{ $spPool ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Pool</label>
        <select name="prop_attr_pool" class="form-select" x-model="poolChoice">
            <option value="">Select</option>
            <option value="Yes" {{ $savedPool === 'Yes' ? 'selected' : '' }}>Yes</option>
            <option value="No"  {{ $savedPool === 'No'  ? 'selected' : '' }}>No</option>
        </select>
    </div>
    <div class="mb-3"
        x-show="{{ $xsPoolType }}"
        style="display:{{ $spPoolType ? 'block' : 'none' }}">
        <label class="form-label fw-semibold d-block">Pool Type</label>
        <div class="form-check form-check-inline">
            <input type="checkbox" name="prop_attr_pool_private" id="prop-attr-pool-private"
                class="form-check-input" value="1" {{ $savedPoolPrivate ? 'checked' : '' }}>
            <label class="form-check-label" for="prop-attr-pool-private">Private</label>
        </div>
        <div class="form-check form-check-inline">
            <input type="checkbox" name="prop_attr_pool_community" id="prop-attr-pool-community"
                class="form-check-input" value="1" {{ $savedPoolCommunity ? 'checked' : '' }}>
            <label class="form-check-label" for="prop-attr-pool-community">Community</label>
        </div>
    </div>

    {{-- ── Year Built ──────────────────────────────────────────────────────── --}}
    {{-- Buyer:  Residential+Income (seller line 1888) + Commercial (line 2111) + Business (line 2501) = all except Vacant Land --}}
    {{-- Tenant: Residential Property block (line 931) + Commercial Property block (line 1236) = all types --}}
    <div class="mb-3"
        x-show="{{ $xsYearBuilt }}"
        style="display:{{ $spYearBuilt ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Year Built</label>
        <input type="number" name="prop_attr_year_built" class="form-control"
            value="{{ $savedYearBuilt }}"
            placeholder="Enter year built (e.g., 1998)" min="1800" max="{{ date('Y') }}">
    </div>

    {{-- ── Zoning ───────────────────────────────────────────────────────────── --}}
    {{-- Buyer:  Commercial (seller line 2127) + Business (line 2508) + Vacant Land (line 2767) --}}
    {{-- Tenant: Commercial Property (landlord line 1252)                       --}}
    <div class="mb-3"
        x-show="{{ $xsZoning }}"
        style="display:{{ $spZoning ? 'block' : 'none' }}">
        <label class="form-label fw-semibold">Zoning</label>
        <input type="text" name="prop_attr_zoning" class="form-control"
            value="{{ $savedZoning }}"
            placeholder="Enter zoning code (e.g., C-1, B-2)">
    </div>

</div>{{-- end nested x-data (garageChoice / poolChoice / bedroomsChoice / bathroomsChoice) --}}
