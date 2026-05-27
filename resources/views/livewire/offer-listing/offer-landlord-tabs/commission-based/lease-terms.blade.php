@php
    $occupant_types_seller = $occupant_types_seller ?? [['name' => 'Owner'], ['name' => 'Tenant'], ['name' => 'Vacant']];

    $acceptable_leasing_space = $acceptable_leasing_space ?? (($property_type ?? '') === 'Commercial Property'
        ? [['name' => 'Entire Property'], ['name' => 'Single Room']]
        : [['name' => 'Accessory Unit / Guest Suite (ADU)'], ['name' => 'Entire Property'], ['name' => 'Single Room']]);

    $residential_lease_term_options = $residential_lease_term_options ?? [
        ['name' => '3 Months'], ['name' => '6 Months'], ['name' => '9 Months'],
        ['name' => '1 Year'], ['name' => '2 Years'], ['name' => 'Month-to-Month'],
    ];

    $Commercial_lease_term_options = $Commercial_lease_term_options ?? [
        ['name' => '6 Months'], ['name' => '1 Year'], ['name' => '2 Years'],
        ['name' => '3-5 Years'], ['name' => '6+ Years'], ['name' => 'Month-to-Month'],
    ];

    $lease_types = $lease_types ?? [
        ['name' => 'Absolute (Triple) Net'], ['name' => 'Gross Lease'], ['name' => 'Gross Percentages'],
        ['name' => 'Ground Lease'], ['name' => 'Lease Option'], ['name' => 'Modified Gross'],
        ['name' => 'Net Lease'], ['name' => 'Net Net'], ['name' => 'Pass Throughs'],
        ['name' => 'Purchase Option'], ['name' => 'Renewal Option'], ['name' => 'Sale-Leaseback'],
        ['name' => 'Seasonal'], ['name' => 'Special Available (CLO)'], ['name' => 'Varied Terms'],
        ['name' => 'Other'],
    ];

    $is_other_owner_pays_visible = is_array($owner_pays ?? null) && in_array('Other', $owner_pays ?? []);
    $is_other_tenant_pay_visible = is_array($tenant_pays ?? null) && in_array('Other', $tenant_pays ?? []);
    $is_rent_include_visible = is_array($this->rent_includes ?? null) && in_array('Other', $this->rent_includes ?? []);
    $is_update_lease_term_option_visible = is_array($desired_lease_length ?? null) && in_array('Other', $desired_lease_length ?? []);
@endphp
<!-- Section Heading -->
<h3>Leasing Terms</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>💵 Enter the lease terms, such as rent amount, lease duration, occupancy status, and who is
                responsible for specific expenses.</strong>
        </div>
    </div>
</div>
<!-- Special Sale Provisions Dropdown -->
<div class="form-group">
    <label class="fw-bold">Occupant Type: <span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select who currently occupies the property. If Tenant or Owner is selected, enter the Occupied Until date. If Vacant is selected, no date is required.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="occupant_status" class="form-control has-icon" data-icon="fa-solid fa-screwdriver-wrench"
            required>
            <option value="">Select</option>
            @foreach ($occupant_types_seller as $row_pt)
                <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
            @endforeach
        </select>
    </div>
</div>

@if ($occupant_status === 'Tenant' || $occupant_status === 'Owner')
    <div class="form-group">
        <label class="fw-bold">Occupied Until: <span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the expected date the property will be vacated. This field is required if the current occupant is the Owner or a Tenant.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <input type="date" wire:model="occupant_tenant" class="form-control has-icon"
                data-icon="fa-regular fa-clock" placeholder="Enter Occupied until" required>
        </div>
    </div>
@endif

<!-- Leasing Space Selection -->

{{-- @if ($property_type === 'Residential Property') --}}

<div class="form-group">
    <label class="fw-bold"> Leasing Space:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Indicate what part of the property is available for lease.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="leasing_spaces" class="form-control has-icon" data-icon="fa-solid fa-building" required>
            <option value="">Select</option>
            @foreach ($acceptable_leasing_space as $row_pt)
                @if ($property_type === 'Residential Property' || $row_pt['name'] !== 'Accessory Unit / Guest Suite (ADU)')
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>

@if ($property_type === 'Residential Property')

    <!-- Leasing Details Based on Selected Space -->
    <div class="form-group mt-4" id="leasing_space_details">

        @if (in_array($leasing_spaces, ['Entire Property', 'Accessory Unit / Guest Suite (ADU)']))
            <div class="form-group">
                <label>Restrictions Include:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="List any rules Tenants must follow—such as no smoking, pet restrictions, quiet hours, or limits on guests and overnight stays.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>
                <div class="input-cover">
                    <input type="text" wire:model="restrictions" class="form-control has-icon" data-icon="fa-solid fa-ban"
                        @if ($property_type === 'Residential Property') placeholder="Enter details (e.g., Visiting hours, Overnight stay rules)"
                @elseif($property_type === 'Commercial Property')
                placeholder="Enter details (e.g., Visiting hours, Overnight use, Guest limitations)"
                @else
                placeholder="Enter details (e.g., Visiting hours, Overnight use, Guest limitations)" @endif>
                </div>
            </div>

            <div class="form-group">
                <label>Maintenance and Repairs Are Handled By:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select who is responsible for handling maintenance and repairs.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <select wire:model="maintenance_by" class="form-control has-icon" data-icon="fa-solid fa-screwdriver-wrench">
                        <option value="">Select</option>
                        <option value="Landlord">Landlord</option>
                        <option value="Property Manager">Property Manager</option>
                        <option value="Real Estate Agent">Real Estate Agent</option>
                        <option value="Tenant">Tenant</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Maintenance Response Time:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Indicate the typical turnaround time for addressing maintenance requests (e.g., 24 hours, 2 business days). This helps set Tenant expectations.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <input type="text" wire:model="maintenance_response_time" class="form-control has-icon"
                        data-icon="fa-solid fa-stopwatch" placeholder="Enter timeframe (e.g., 24 hours)">
                </div>
            </div>

            <div class="form-group" wire:key="included-storage-space-res-both-{{ $property_type }}">
                <label class="fw-bold"> Included Storage Space:

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select if dedicated storage space is included in the lease for the Tenant’s exclusive use.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <select wire:model="included_storage_space_res_both" id="garage-needed"
                        class="form-control has-icon" data-icon="fa-solid fa-warehouse">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>

            @if ($included_storage_space_res_both === 'Yes')
                <div class="form-group">
                    <label>Storage Space Size:</label>

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Enter the approximate size of any dedicated storage space available to the Tenant, such as a storage closet, garage bay, or external unit. (e.g., 6x6 or 4x10).">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <input type="text" wire:model="storage_space_res_both" class="form-control has-icon"
                            data-icon="fa-solid fa-warehouse" placeholder="Enter size of storage space (e.g., 6x6)">
                    </div>
                </div>
            @endif
        @endif
        @if (in_array($leasing_spaces, ['Single Room']))

            <div class="form-group">
                <label>Guests are:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Indicate whether guests are allowed and under what conditions (e.g., daytime only, no overnight guests, prior approval required).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <select wire:model="guests_allowed" class="form-control has-icon"
                        data-icon="fa-solid fa-user-group">
                        <option value="">Select</option>
                        <option value="Allowed">Allowed</option>
                        <option value="Not Allowed">Not Allowed</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Restrictions Include:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="List any house rules the Tenant must follow—such as quiet hours, kitchen use limitations, curfews, or pet policies.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>
                <div class="input-cover">
                    <input type="text" wire:model="restrictions" class="form-control has-icon"
                        data-icon="fa-solid fa-ban"
                        placeholder="Enter details (e.g., Visiting hours, Overnight stay rules)" />
                </div>
            </div>

            <div class="form-group">
                <label>Shared Areas Available:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Specify which shared areas the Tenant may use (e.g., kitchen, living room, laundry room, backyard).">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <input type="text" wire:model="common_areas_access" class="form-control has-icon"
                        data-icon="fa-solid fa-door-open"
                        placeholder="Enter common areas (e.g., Kitchen, Living Room, Backyard)">

                </div>
            </div>

            <div class="form-group">
                <label>Maintenance and Repairs Are Handled By:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select who is responsible for handling maintenance and repairs.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <select wire:model="maintenance_by" class="form-control has-icon" data-icon="fa-solid fa-screwdriver-wrench">
                        <option value="">Select</option>
                        <option value="Landlord">Landlord</option>
                        <option value="Property Manager">Property Manager</option>
                        <option value="Real Estate Agent">Real Estate Agent</option>
                        <option value="Tenant">Tenant</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Maintenance Response Time:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Indicate the typical turnaround time for addressing maintenance requests (e.g., 24 hours, 2 business days). This helps set Tenant expectations.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <input type="text" wire:model="maintenance_response_time" class="form-control has-icon"
                        data-icon="fa-solid fa-stopwatch" placeholder="Enter timeframe (e.g., 24 hours)">
                </div>
            </div>

            <div class="form-group">
                <label>Utilities:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select how utilities are managed—whether included in rent, shared among Tenants, or paid separately by the room.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <select wire:model="utilities" class="form-control has-icon" data-icon="fa-solid fa-bolt">
                        <option value="">Select</option>
                        <option value="Included in Rent">Included in Rent</option>
                        <option value="Split Among Tenants">Split Among Tenants</option>
                        <option value="Individually Metered">Individually Metered</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Common Area Maintenance:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter cleaning frequency and who is responsible (e.g., Weekly by Landlord, Tenants Take Turns).">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <input type="text" wire:model="common_areas_cleaning" class="form-control has-icon"
                        data-icon="fa-solid fa-broom"
                        placeholder="Enter cleaning frequency and who is responsible (e.g., Weekly by Landlord, Tenants Take Turns)">
                </div>
            </div>

            <div class="form-group" wire:key="included-storage-space-res-single-{{ $property_type }}">
                <label class="fw-bold"> Included Storage Space:

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select if dedicated storage space is included in the lease for the Tenant’s exclusive use.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <select wire:model="included_storage_space_res_single" id="garage-needed"
                        class="form-control has-icon" data-icon="fa-solid fa-warehouse">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>

            @if ($included_storage_space_res_single === 'Yes')
                <div class="form-group">
                    <label>Storage Space Size:</label>

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Enter the approximate size of any dedicated storage space available to the Tenant, such as a storage closet, garage bay, or external unit. (e.g., 6x6 or 4x10).">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <input type="text" wire:model="storage_space_res_single" class="form-control has-icon"
                            data-icon="fa-solid fa-warehouse" placeholder="Enter size of storage space (e.g., 6x6)">
                    </div>
                </div>
            @endif
            <div class="form-group">
                <label>Bathroom Facilities:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select the type of bathroom access — private or shared.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

                <div class="input-cover">
                    <select wire:model="bathroom_facilities" class="form-control has-icon" data-icon="fa-solid fa-bath">
                        <option value="">Select</option>
                        <option value="Private">Private</option>
                        <option value="Shared">Shared</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Approximate Room Size:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the approximate size of the room in square feet.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

                <div class="input-cover">
                    <input type="text" wire:model="room_size" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" placeholder="Enter square footage (e.g., 300 SqFt)">
                </div>
            </div>
        @endif
    </div>
@endif

@if ($property_type === 'Commercial Property')

    <!-- Leasing Details Based on Selected Space -->
    <div class="form-group mt-4" id="leasing_space_details">

        @if (in_array($leasing_spaces, ['Entire Property']))
            <div class="form-group">
                <label>Restrictions Include:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Specify any limitations on property use, access, or behavior—such as guest policies, business hours, noise rules, or overnight restrictions.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>
                <div class="input-cover">
                    <input type="text" wire:model="restrictions" class="form-control has-icon"
                        data-icon="fa-solid fa-ban"
                        placeholder="Enter details (e.g., Visiting hours, Overnight use, Guest limitations)">

                </div>
            </div>

            <div class="form-group">
                <label>Maintenance and Repairs Are Handled By:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select who is responsible for handling maintenance and repairs.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <select wire:model="maintenance_by" class="form-control has-icon" data-icon="fa-solid fa-screwdriver-wrench">
                        <option value="">Select</option>
                        <option value="Landlord">Landlord</option>
                        <option value="Property Manager">Property Manager</option>
                        <option value="Real Estate Agent">Real Estate Agent</option>
                        <option value="Tenant">Tenant</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Maintenance Response Time:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Indicate the typical turnaround time for addressing maintenance requests (e.g., 24 hours, 2 business days). This helps set Tenant expectations.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <input type="text" wire:model="maintenance_response_time" class="form-control has-icon"
                        data-icon="fa-solid fa-stopwatch" placeholder="Enter timeframe (e.g., 24 hours)">
                </div>
            </div>

            <div class="form-group" wire:key="included-storage-space-com-entire-{{ $property_type }}">
                <label class="fw-bold"> Included Storage Space:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select if dedicated storage space is included in the lease for the Tenant’s exclusive use.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <select wire:model="included_storage_space_com_entire" id="garage-needed"
                        class="form-control has-icon" data-icon="fa-solid fa-warehouse">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>

            @if ($included_storage_space_com_entire === 'Yes')
                <div class="form-group">
                    <label>Storage Space Size:</label>

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Enter the approximate size of any dedicated storage space available to the Tenant, such as a storage closet, garage bay, or external unit. (e.g., 6x6 or 4x10).">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <input type="text" wire:model="storage_space_com_entire" class="form-control has-icon"
                            data-icon="fa-solid fa-warehouse" placeholder="Enter size of storage space (e.g., 6x6)">
                    </div>
                </div>
            @endif
            {{-- 🏢 Shared Amenities --}}
            <div class="form-group">
                <label class="fw-bold">Shared Amenities Include:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="List any shared facilities available to Tenants, such as conference rooms, break areas, restrooms, or common parking.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="shared_amenities" class="form-control has-icon"
                        data-icon="fa-solid fa-people-roof"
                        placeholder="Enter shared amenities (e.g., Conference rooms, Parking facilities)">
                </div>
            </div>

            {{-- 🕒 Building Hours --}}
            <div class="form-group">
                <label class="fw-bold">Building Hours:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Provide the regular building access hours (e.g., Mon–Fri, 8am–6pm). This helps Tenants understand when the property is typically accessible.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="building_hours" class="form-control has-icon"
                        data-icon="fa-solid fa-clock" placeholder="Enter hours (e.g., Open 8AM-5PM)">
                </div>
            </div>

            {{-- ⏰ 24/7 Access --}}
            <div class="form-group">
                <label class="fw-bold">24/7 Access Available:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Indicate whether Tenants have unrestricted 24/7 access to the leased premises, or if access is limited to set hours.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

                <div class="input-cover">
                    <select wire:model="access_24_7" class="form-control has-icon" data-icon="fa-solid fa-door-open">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>

            {{-- 🏛️ Zoning Allows --}}
            <div class="form-group">
                <label class="fw-bold">Zoning Allows:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="State the permitted commercial uses under current zoning (e.g., office, retail, light industrial). This helps ensure the Tenant’s business use is compliant.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

                <div class="input-cover">
                    <input type="text" wire:model="zoning_allows" class="form-control has-icon"
                        data-icon="fa-solid fa-building-columns"
                        placeholder="Enter permitted uses (e.g., Retail, Office, Light Industrial)">
                </div>
            </div>

            {{-- 🧱 Space Features --}}
            <div class="form-group">
                <label class="fw-bold"> Space Features:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Describe the layout or functional aspects of the space (e.g., private offices, warehouse bay, open floor plan, loading dock).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

                <div class="input-cover">
                    <input type="text" wire:model="space_features" class="form-control has-icon"
                        data-icon="fa-solid fa-table-cells-large"
                        placeholder="Enter layout (e.g., Open floor plan, Private offices)">
                </div>
            </div>

            {{-- 🧭 Neighboring Tenants --}}
            <div class="form-group">
                <label class="fw-bold">Neighboring Tenants Include:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="List nearby or anchor Tenants that may affect traffic, visibility, or co-tenancy appeal (e.g., Target, Walmart, Starbucks).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

                <div class="input-cover">
                    <input type="text" wire:model="neighboring_tenants" class="form-control has-icon"
                        data-icon="fa-solid fa-store"
                        placeholder="Enter name of business types or names if notable (e.g., Walmart, Target) ">
                </div>
            </div>
        @elseif (in_array($leasing_spaces, ['Single Room']))
            <div class="form-group">
                <label>Guests are:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Indicate whether guests are allowed and under what conditions (e.g., daytime only, no overnight guests, prior approval required).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <select wire:model="guests_allowed" class="form-control has-icon"
                        data-icon="fa-solid fa-user-group">
                        <option value="">Select</option>
                        <option value="Allowed">Allowed</option>
                        <option value="Not Allowed">Not Allowed</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Restrictions Include:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Describe any limitations on property use, access, or behavior—such as shared hours, overnight use, or guest limitations.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>
                <div class="input-cover">
                    <input type="text" wire:model="restrictions" class="form-control has-icon"
                        data-icon="fa-solid fa-ban"
                        placeholder="Enter details (e.g., Shared hours, Overnight use, Guest limitations)">
                </div>
            </div>
            <div class="form-group">
                <label>Shared Areas Available:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Specify which shared areas the Tenant may use (e.g., lobby, breakroom, restroom).">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <input type="text" wire:model="common_areas_access" class="form-control has-icon"
                        data-icon="fa-solid fa-door-open"
                        placeholder="Enter shared spaces (e.g., Lobby, Breakroom, Restroom)" />

                </div>
            </div>

            <div class="form-group">
                <label>Maintenance and Repairs Are Handled By:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select who is responsible for handling maintenance and repairs (e.g., Landlord, Property Manager, Real Estate Agent, Tenant).">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <select wire:model="maintenance_by" class="form-control has-icon" data-icon="fa-solid fa-screwdriver-wrench">
                        <option value="">Select</option>
                        <option value="Landlord">Landlord</option>
                        <option value="Property Manager">Property Manager</option>
                        <option value="Real Estate Agent">Real Estate Agent</option>
                        <option value="Tenant">Tenant</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Maintenance Response Time:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the usual turnaround time for addressing maintenance requests (e.g., 24 hours, 2 business days). This helps set Tenant expectations.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <input type="text" wire:model="maintenance_response_time" class="form-control has-icon"
                        data-icon="fa-solid fa-stopwatch" placeholder="Enter timeframe (e.g., 24 hours)">
                </div>
            </div>

            <div class="form-group">
                <label>Utilities:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select how utilities are managed—whether included in rent, shared among Tenants, or paid separately by the room.">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <select wire:model="utilities" class="form-control has-icon" data-icon="fa-solid fa-bolt">
                        <option value="">Select</option>
                        <option value="Included in Rent">Included in Rent</option>
                        <option value="Split Among Tenants">Split Among Tenants</option>
                        <option value="Individually Metered">Individually Metered</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Common Area Maintenance:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter cleaning frequency and who is responsible for maintaining common areas (e.g., nightly by cleaning service).">
                    <i class="fa-solid fa-circle-info"></i>

                </span>

                <div class="input-cover">
                    <input type="text" wire:model="common_areas_cleaning" class="form-control has-icon"
                        data-icon="fa-solid fa-broom"
                        placeholder="Enter frequency and by whom (e.g., Nightly by Cleaning Service)">
                </div>
            </div>

            <div class="form-group" wire:key="included-storage-space-com-single-{{ $property_type }}">
                <label class="fw-bold"> Included Storage Space:

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select if dedicated storage space is included in the lease for the Tenant’s exclusive use.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <select wire:model="included_storage_space_com_single" id="garage-needed"
                        class="form-control has-icon" data-icon="fa-solid fa-warehouse">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>

            @if ($included_storage_space_com_single === 'Yes')
                <div class="form-group">
                    <label>Storage Space Size:</label>

                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Enter the approximate size of any dedicated storage space available to the Tenant, such as a storage closet, garage bay, or external unit. (e.g., 6x6 or 4x10).">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <input type="text" wire:model="storage_space_com_single" class="form-control has-icon"
                            data-icon="fa-solid fa-warehouse" placeholder="Enter size of storage space (e.g., 6x6)">
                    </div>
                </div>
            @endif

            <div class="form-group">
                <label>Bathroom Facilities:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select the type of bathroom access — private or shared.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

                <div class="input-cover">
                    <select wire:model="bathroom_facilities" class="form-control has-icon" data-icon="fa-solid fa-bath">
                        <option value="">Select</option>
                        <option value="Private">Private</option>
                        <option value="Shared">Shared</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Approximate Room Size:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the approximate size of the room in square footage (e.g., 200 SqFt).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>

                <div class="input-cover">
                    <input type="text" wire:model="room_size" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" placeholder="Enter square footage (e.g., 200 SqFt)">
                </div>
            </div>
        @endif
    </div>

@endif

{{-- @if ($property_type === 'Commercial Property')

    @if (in_array($leasing_spaces, ['Accessory Unit / Guest Suite (ADU)']))

        <div class="form-group">
            <label class="fw-bold">Shared Amenities Include:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="List any shared facilities available to Tenants, such as conference rooms, break areas, restrooms, or common parking.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="shared_amenities" class="form-control has-icon"
                    data-icon="fa-solid fa-people-roof"
                    placeholder="Enter shared amenities (e.g., Conference rooms, Parking facilities)">
            </div>
        </div>

        <div class="form-group">
            <label class="fw-bold">Building Hours:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Provide the regular building access hours (e.g., Mon–Fri, 8am–6pm). This helps Tenants understand when the property is typically accessible.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="building_hours" class="form-control has-icon"
                    data-icon="fa-solid fa-clock" placeholder="Enter hours (e.g., Open 8AM-5PM)">
            </div>
        </div>

        <div class="form-group">
            <label class="fw-bold">24/7 Access Available:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Indicate whether Tenants have unrestricted 24/7 access to the leased premises, or if access is limited to set hours.">
                <i class="fa-solid fa-circle-info"></i>
            </span>

            <div class="input-cover">
                <select wire:model="access_24_7" class="form-control has-icon" data-icon="fa-solid fa-door-open">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="fw-bold">Zoning Allows:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="State the permitted commercial uses under current zoning (e.g., office, retail, light industrial). This helps ensure the Tenant’s business use is compliant.">
                <i class="fa-solid fa-circle-info"></i>
            </span>

            <div class="input-cover">
                <input type="text" wire:model="zoning_allows" class="form-control has-icon"
                    data-icon="fa-solid fa-building-columns"
                    placeholder="Enter permitted uses (e.g., Retail, Office, Light Industrial)">
            </div>
        </div>

        <div class="form-group">
            <label class="fw-bold">Maintenance and repairs are handled by:</label>
            <div class="input-cover">
                <select wire:model="maintenance_handler" class="form-control has-icon" data-icon="fa-solid fa-screwdriver-wrench">
                    <option value="">Select</option>
                    <option value="Landlord">Landlord</option>
                    <option value="Property Manager">Property Manager</option>
                    <option value="Real Estate Agent">Real Estate Agent</option>
                    <option value="Tenant">Tenant</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="fw-bold"> Space Features:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Describe the layout or functional aspects of the space (e.g., private offices, warehouse bay, open floor plan, loading dock).">
                <i class="fa-solid fa-circle-info"></i>
            </span>

            <div class="input-cover">
                <input type="text" wire:model="space_features" class="form-control has-icon"
                    data-icon="fa-solid fa-table-cells-large"
                    placeholder="Enter layout (e.g., Open floor plan, Private offices)">
            </div>
        </div>

        <div class="form-group" wire:ignore wire:key="included-storage-space-res-single-{{ $property_type }}">
            <label class="fw-bold"> Included Storage Space:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Select if dedicated storage space is included in the lease for the Tenant’s exclusive use.">
                    <i class="fa-solid fa-circle-info"></i>

            </label>
            <div class="input-cover">
                <select wire:model="included_storage_space" id="garage-needed" class="form-control has-icon"
                    data-icon="fa-solid fa-warehouse">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>

        @if ($included_storage_space === 'Yes')
            <div class="form-group">
                <label>Storage Space Size:</label>

                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the approximate size of any dedicated storage space available to the Tenant, such as a storage closet, garage bay, or external unit. (e.g., 6x6 or 4x10).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="storage_space" class="form-control has-icon"
                        data-icon="fa-solid fa-warehouse" placeholder="Enter size of storage space (e.g., 6x6)">
                </div>
            </div>
        @endif

        <div class="form-group">
            <label class="fw-bold">Neighboring Tenants Include:</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="List nearby or anchor tenants that may affect traffic, visibility, or co-tenancy appeal (e.g., Target, Starbucks, medical offices).">
                <i class="fa-solid fa-circle-info"></i>
            </span>

            <div class="input-cover">
                <input type="text" wire:model="neighboring_tenants" class="form-control has-icon"
                    data-icon="fa-solid fa-store"
                    placeholder="Enter name of business types or names if notable (e.g., Walmart, Target) ">
            </div>
        </div>

    @endif
@endif --}}
@if ($property_type === 'Commercial Property')
    <div class="form-group" wire:ignore wire:key="landlord-tenant-pays-group">
        <label class="fw-bold">Tenant Pays:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any expenses or responsibilities the Tenant is required to pay under the lease terms.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="tenant_pays" class="tenant_pays form-control has-icon select2-multiple"
                data-icon="fa-solid fa-user" data-placeholder="Select" multiple>
                @foreach ($tenantPays as $row_pt)
                    <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $this->tenant_pays ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- <div class="form-group tenant_pays_other d-none"> --}}
    <div class="form-group mt-2 tenant_pays_other_wrapper" id="other_tenant_pays_wrapper"
        style="display: {{ $is_other_tenant_pay_visible ? 'block' : 'none' }}">
        <div class="input-cover">
            <input type="text" wire:model="other_tenant_pays" class="form-control has-icon"
                data-icon="fa-solid fa-user"
                placeholder="Enter what tenant pays (e.g., Gas, Electric, Internet)">
        </div>
    </div>

    {{-- <div class="form-group mt-2 tenant_pays_other_wrapper"
                style="display: {{ $is_other_tenant_pay_visible ? 'block' : 'none' }}">
<label class="fw-bold">Please specify other expense paid by Tenant:</label>
<div class="input-cover">
    <input type="text" wire:model="tenant_pays_other" class="form-control has-icon"
        data-icon="fa-solid fa-pencil" placeholder="Enter other expense paid by Tenant (e.g., HVAC maintenance)">
</div>
</div> --}}

    <div class="form-group" wire:ignore wire:key="landlord-owner-pays-group">
        <label class="fw-bold">Owner Pays:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any expenses the Owner will cover as part of the lease arrangement.">
            <i class="fa-solid fa-circle-info"></i>

        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="owner_pays" class="owner_pays form-control has-icon select2-multiple"
                data-icon="fa-solid fa-user-tie" data-placeholder="Select" multiple>
                @foreach ($ownerPays as $row_pt)
                    <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $this->owner_pays ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group other_owner_pays" id="other_owner_pays_wrapper" style="display: {{ $is_other_owner_pays_visible ? 'block' : 'none' }}">
        <div class="input-cover">
            <input type="text" wire:model="other_owner_pays" class="form-control has-icon"
                data-icon="fa-solid fa-user-tie"
                placeholder="Enter what owner pays (e.g., HOA Fees, Water, Trash)">
        </div>
    </div>

    {{-- 📜 Terms of Lease --}}
    {{-- <div class="form-group" wire:ignore>
        <label class="fw-bold">Terms of Lease:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the lease term offered for this property. This defines how costs and responsibilities—such as taxes, insurance, and maintenance—are structured between the Landlord and Tenant.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover has-select-icon">
            <select id="terms_of_lease" wire:model="terms_of_lease"  class="terms_of_lease form-control has-icon select2-multiple"
                data-icon="fa-solid fa-file-signature" multiple required>
                @foreach ($lease_types as $type)
                    <option value="{{ $type['name'] }}">{{ $type['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div> --}}


{{-- 📜 Terms of Lease --}}
<div class="form-group" wire:ignore wire:key="landlord-terms-of-lease-group">
    <label class="fw-bold">Terms of Lease:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the lease term offered for this property. This defines how costs and responsibilities—such as taxes, insurance, and maintenance—are structured between the Landlord and Tenant.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    <div class="input-cover has-select-icon" wire:ignore>
        <select id="terms_of_lease" class="terms_of_lease form-control has-icon select2-multiple"
            data-icon="fa-solid fa-file-signature" data-placeholder="Select" multiple>
            <option value=""></option>
            @foreach ($lease_types as $type)
            <option value="{{ $type['name'] }}" {{ in_array($type['name'], $this->terms_of_lease ?? []) ? 'selected' : '' }}>{{ $type['name'] }}</option>

            @endforeach
        </select>
    </div>

    {{-- Show custom input when "Other" is selected --}}
    <div id="otherLeaseContainer" class="other-lease-input mt-3 d-none">
        <div class="form-group">
            {{-- <label class="fw-bold">Custom Lease Term:</label> --}}
            <div class="input-cover">
                <input type="text" wire:model="custom_lease_term" class="form-control has-icon"
                    data-icon="fa-solid fa-file-signature" placeholder="Enter lease terms (e.g., Month-to-Month, Furnished, Pet Addendum)">
            </div>
        </div>
    </div>
</div>
@endif

@if ($auction_type === 'Bidding Period')
<div class="financing-section-header mt-4 mb-3 pb-2 border-bottom">
    <h5 class="fw-bold text-primary mb-0">
        <i class="fa-solid fa-gavel me-2"></i>Bidding Period Lease Pricing
    </h5>
</div>
<div class="form-group">
    <label class="fw-bold">Desired Lease Price:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the lease price the Landlord would like to receive.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="desired_rental_amount" id="landlord_desired_lease_price" class="form-control"
            placeholder="Enter desired lease price (e.g., 2500)" required
            data-error-id="desired_rental_amount_error"
            oninput="validateInput(this);" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
    <span class="error mt-2" id="desired_rental_amount_error"></span>
</div>
<div class="form-group mt-3">
    <label class="fw-bold">Starting Rent / Opening Offer:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum rent amount at which bidding will open for this lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="starting_rent" class="form-control"
            placeholder="Enter opening offer amount (e.g., 4000)"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>
<div class="form-group mt-3">
    <label class="fw-bold">Reserve Rent:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the minimum rent amount the Landlord will accept. The property will not be leased below this amount. Reserve Rent is optional.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="reserve_rent" class="form-control"
            placeholder="Enter reserve rent (e.g., 4500) — optional"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>
<div class="form-group mt-3">
    <label class="fw-bold">Lease Now Price:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the rent amount at which a Tenant can immediately lease the property and end the bidding period. Defaults to Desired Lease Price if left unchanged.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="lease_now_price" id="landlord_lease_now_price" class="form-control"
            placeholder="Enter lease now price (e.g., 5000)"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>
@else
<div class="form-group">
    <label class="fw-bold">Desired Lease Price:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the monthly rent amount the Landlord would like to collect. ">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="desired_rental_amount" class="form-control"
            placeholder="Enter desired lease price (e.g., 2500)" required
            data-error-id="desired_rental_amount_error"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
    <span class="error mt-2" id="desired_rental_amount_error"></span>
</div>
@endif

<!-- Lease Amount Frequency Dropdown -->
<div class="form-group">
    <label class="fw-bold">Lease Amount Frequency <span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select how often rent will be collected.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="lease_amount_frequency" class="form-control has-icon" data-icon="fa-solid fa-calendar"
            required>
            <option value="">Select</option>

            @if ($property_type === 'Residential Property')
                <option value="Annually">Annually</option>
                <option value="Monthly">Monthly</option>
                <option value="Daily">Daily</option>
                <option value="Weekly">Weekly</option>
                <option value="Seasonal">Seasonal</option>
            @elseif ($property_type === 'Commercial Property')
                <option value="Annually">Annually</option>
                <option value="Monthly">Monthly</option>
            @endif

        </select>
    </div>
    @error('lease_amount_frequency')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

<div class="form-group" wire:key="landlord-desired-lease-{{ $property_type ?? 'none' }}">
    <label class="fw-bold">Desired Lease Term: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the lease term the Landlord prefers to offer.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover has-select-icon" wire:ignore>
        <select class="lease_term_options form-control has-icon select2-multiple"
            data-icon="fa-solid fa-calendar-days" data-placeholder="Select" multiple required>
            @if ($property_type === 'Residential Property')
                @foreach ($residential_lease_term_options as $row_pt)
                    <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $this->desired_lease_length ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
                @endforeach
            @elseif ($property_type === 'Commercial Property')
                @foreach ($Commercial_lease_term_options as $row_pt)
                    <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $this->desired_lease_length ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
                @endforeach
            @endif

            <option value="Other" {{ in_array('Other', $this->desired_lease_length ?? []) ? 'selected' : '' }}>Other</option>
        </select>
    </div>

    <div class="form-group mt-2 other_lease_term other_lease_term_wrapper" id="other_desired_lease_length_wrapper"
        style="display: {{ $is_update_lease_term_option_visible ? 'block' : 'none' }}">
        {{-- <label class="fw-bold">Other Lease Term:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_lease_term" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-days" placeholder="Enter lease term (e.g., 6-month, Week-to-week)">
        </div>
    </div>

</div>
@error('desired_lease_length')
    <span class="text-danger">{{ $message }}</span>
@enderror


{{-- ===== NEW LANDLORD LEASING TERMS FIELDS ===== --}}

{{-- Lease Available Date --}}
<div class="form-group">
    <label class="fw-bold">Lease Available Date:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the date the property will be available for the Tenant to begin the lease.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="date" wire:model="lease_available_date" class="form-control has-icon"
            data-icon="fa-regular fa-calendar">
    </div>
</div>

{{-- Security Deposit Required --}}
<div class="form-group">
    <label class="fw-bold">Security Deposit Required:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the amount of the security deposit the Landlord requires from the Tenant.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="security_deposit_required" class="form-control"
            placeholder="Enter security deposit amount (e.g., 2500)"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>

{{-- First Month Rent Required --}}
<div class="form-group">
    <label class="fw-bold">First Month Rent Required:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Indicate whether the first month's rent must be paid upfront at lease signing.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="first_month_rent_required" class="form-control has-icon"
            data-icon="fa-solid fa-money-bill">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
</div>

{{-- Last Month Rent Required --}}
<div class="form-group">
    <label class="fw-bold">Last Month Rent Required:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Indicate whether the last month's rent must be paid upfront at lease signing.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="last_month_rent_required" class="form-control has-icon"
            data-icon="fa-solid fa-money-bill">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
            <option value="Negotiable">Negotiable</option>
        </select>
    </div>
</div>

{{-- Total Move-In Funds Required --}}
<div class="form-group">
    <label class="fw-bold">Total Move-In Funds Required:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the total amount the Tenant must provide at move-in (e.g., first month, last month, and security deposit combined).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input type="text" wire:model="total_move_in_funds_required" class="form-control"
            placeholder="Enter total move-in funds required (e.g., 7500)"
            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
    </div>
</div>

{{-- Phase B L-02: Available Date --}}
<div class="form-group">
    <label class="fw-bold">Available Date:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the date the property will be available for move-in. This is used to match Tenant move-in timelines.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="date" wire:model="available_date" class="form-control has-icon"
            data-icon="fa-regular fa-calendar" placeholder="Select available date">
    </div>
</div>

{{-- Pet Deposit / Fee / Rent (shown when Pets = Yes) --}}
@if ($pets === 'Yes')
    <div class="form-group">
        <label class="fw-bold">Pet Deposit / Pet Fee / Pet Rent:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the pet deposit, one-time fee, or additional monthly pet rent amount the Landlord requires.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="pet_deposit_fee_rent" class="form-control has-icon"
                data-icon="fa-solid fa-paw"
                placeholder="Enter pet deposit/fee/rent (e.g., $300 deposit, $50/month)">
        </div>
    </div>
@endif

{{-- Phase B L-03: Pet sub-fields (shown when Pets = Yes, same guard as pet_deposit_fee_rent) --}}
@if ($pets === 'Yes')
    <div class="form-group">
        <label class="fw-bold">Maximum Pet Weight (lbs):</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the maximum allowable pet weight in pounds (e.g., 25).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="pet_max_weight_lbs" class="form-control has-icon"
                data-icon="fa-solid fa-weight-scale" min="0"
                placeholder="Enter max weight in lbs (e.g., 25)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Pet Species Allowed:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the species of pets permitted on the property.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="pet_species_allowed" class="form-control has-icon"
                data-icon="fa-solid fa-paw" multiple>
                <option value="Dog">Dog</option>
                <option value="Cat">Cat</option>
                <option value="Bird">Bird</option>
                <option value="Small caged animal">Small Caged Animal</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Pet Deposit Amount:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the one-time pet deposit amount required (e.g., 300).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="pet_deposit_amount" class="form-control"
                placeholder="Enter pet deposit amount (e.g., 300)"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Pet Monthly Fee:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the monthly recurring pet fee amount (e.g., 50).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <span class="input-group-text-seller">$</span>
            <input type="text" wire:model="pet_monthly_fee" class="form-control"
                placeholder="Enter monthly pet fee (e.g., 50)"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
        </div>
    </div>
@endif

{{-- Number of Occupants Allowed --}}
<div class="form-group">
    <label class="fw-bold">Number of Occupants Allowed:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the maximum number of occupants permitted to live in the property under the lease.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <input type="number" wire:model="number_of_occupants_allowed" class="form-control has-icon"
            data-icon="fa-solid fa-users" min="1" placeholder="Enter max number of occupants (e.g., 4)">
    </div>
</div>

{{-- Parking Terms --}}
<div class="form-group">
    <label class="fw-bold">Parking Terms:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Describe the parking arrangement included with the lease (e.g., 1 assigned space, street parking only, garage included).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <textarea wire:model="parking_terms" class="form-control has-icon landlord-compact-textarea" rows="1"
            data-icon="fa-solid fa-car"
            placeholder="Enter parking terms (e.g., 1 assigned covered space included, 2 guest spaces available)"></textarea>
    </div>
</div>

{{-- Maintenance Responsibility --}}
<div class="form-group">
    <label class="fw-bold">Maintenance Responsibility:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Describe maintenance and repair responsibilities for both the Landlord and Tenant.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <textarea wire:model="ll_maintenance_responsibility" class="form-control has-icon landlord-compact-textarea" rows="1"
            data-icon="fa-solid fa-screwdriver-wrench"
            placeholder="Enter maintenance responsibilities (e.g., Landlord responsible for major repairs, Tenant responsible for lawn care)"></textarea>
    </div>
</div>

{{-- Renewal Option Offered --}}
<div class="form-group">
    <label class="fw-bold">Renewal Option Offered:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Indicate whether the Landlord is willing to offer a lease renewal option to the Tenant.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <select wire:model="renewal_option_offered" class="form-control has-icon"
            data-icon="fa-solid fa-rotate">
            <option value="">Select</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
            <option value="Negotiable">Negotiable</option>
        </select>
    </div>
</div>

{{-- Renewal Option Details (shown when Yes or Negotiable) --}}
@if ($renewal_option_offered === 'Yes' || $renewal_option_offered === 'Negotiable')
    <div class="form-group">
        <label class="fw-bold">Renewal Option Details:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe the terms of the renewal option (e.g., 1-year renewal at market rate with 60-day notice required).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="renewal_option_details" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-file-signature"
                placeholder="Enter renewal option terms (e.g., 1-year renewal at market rate, 60-day notice required)"></textarea>
        </div>
    </div>
@endif

{{-- Landlord Approval Conditions --}}
<div class="form-group">
    <label class="fw-bold">Landlord Approval Conditions:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="List any conditions or requirements the Tenant must meet for the Landlord to approve the lease (e.g., credit check, income verification, references).">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <textarea wire:model="landlord_approval_conditions" class="form-control has-icon landlord-compact-textarea" rows="1"
            data-icon="fa-solid fa-clipboard-check"
            placeholder="Enter approval conditions (e.g., Credit score 650+, Income 3x monthly rent, No prior evictions)"></textarea>
    </div>
</div>

{{-- Additional Landlord Lease Terms --}}
<div class="form-group">
    <label class="fw-bold">Additional Landlord Lease Terms:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter any additional lease terms or conditions not covered above.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <textarea wire:model="additional_landlord_lease_terms" class="form-control has-icon landlord-compact-textarea" rows="1"
            data-icon="fa-solid fa-file-lines"
            placeholder="Enter any additional lease terms or special conditions"></textarea>
    </div>
</div>

{{-- ===== COMMERCIAL-ONLY LEASING TERM FIELDS ===== --}}
@if ($property_type === 'Commercial Property')
    {{-- Commercial Lease Type --}}
    <div class="form-group">
        <label class="fw-bold">Commercial Lease Type:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of commercial lease structure the Landlord is offering.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select id="commercial_lease_type" wire:model="commercial_lease_type" class="form-control has-icon"
                data-icon="fa-solid fa-building">
                <option value="">Select</option>
                <option value="Gross Lease">Gross Lease</option>
                <option value="Modified Gross Lease">Modified Gross Lease</option>
                <option value="Triple Net / NNN">Triple Net / NNN</option>
                <option value="Full Service Gross">Full Service Gross</option>
                <option value="Percentage Rent">Percentage Rent</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>
    <div class="form-group" id="commercial_lease_type_other_wrapper" style="display: {{ $commercial_lease_type === 'Other' ? 'block' : 'none' }}">
        <div class="input-cover">
            <input type="text" wire:model="commercial_lease_type_other" class="form-control has-icon"
                data-icon="fa-solid fa-building"
                placeholder="Enter commercial lease type (e.g., Ground lease, Build-to-suit)">
        </div>
    </div>

    {{-- CAM/NNN/Additional Rent Charges --}}
    <div class="form-group">
        <label class="fw-bold">CAM / NNN / Additional Rent Charges:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe any Common Area Maintenance (CAM), NNN, or additional charges the Tenant is responsible for beyond base rent.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="cam_nnn_additional_rent_charges" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-receipt"
                placeholder="Enter CAM/NNN charges (e.g., Estimated $3/SqFt annually for taxes, insurance, and maintenance)"></textarea>
        </div>
    </div>

    {{-- Rent Escalation Terms --}}
    <div class="form-group">
        <label class="fw-bold">Rent Escalation Terms:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe how rent increases are structured over the lease term (e.g., 3% annual increase, CPI-based adjustment).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="rent_escalation_terms" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-arrow-trend-up"
                placeholder="Enter rent escalation (e.g., 3% annual increase beginning year 2)"></textarea>
        </div>
    </div>

    {{-- Tenant Improvement / Buildout Terms --}}
    <div class="form-group">
        <label class="fw-bold">Tenant Improvement / Buildout Terms:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe any Tenant Improvement (TI) allowance or buildout terms the Landlord is offering.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="tenant_improvement_buildout_terms" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-hammer"
                placeholder="Enter TI/buildout terms (e.g., $20/SqFt TI allowance, Delivered in Shell Condition)"></textarea>
        </div>
    </div>

    {{-- Permitted Use / Use Restrictions --}}
    <div class="form-group">
        <label class="fw-bold">Permitted Use / Use Restrictions:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Specify the permitted uses for the space and any restrictions on how the Tenant may use the premises.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="permitted_use_restrictions" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-building-columns"
                placeholder="Enter permitted uses and restrictions (e.g., General Office Use Only, No Retail or Food Service)"></textarea>
        </div>
    </div>

    {{-- Signage Rights --}}
    <div class="form-group">
        <label class="fw-bold">Signage Rights:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe the Tenant's rights to install signage on the building, windows, or monument sign.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="signage_rights" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-sign-hanging"
                placeholder="Enter signage rights (e.g., Tenant may install one suite sign and one directory listing)"></textarea>
        </div>
    </div>

    {{-- Commercial Parking Terms --}}
    <div class="form-group">
        <label class="fw-bold">Commercial Parking Terms:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Describe parking availability, ratios, reserved spaces, and any associated costs.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="commercial_parking_terms" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-square-parking"
                placeholder="Enter commercial parking terms (e.g., 4 spaces per 1,000 SqFt, 2 reserved spaces included)"></textarea>
        </div>
    </div>

    {{-- Personal Guarantee Requirement --}}
    <div class="form-group">
        <label class="fw-bold">Personal Guarantee Requirement:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate whether a personal guarantee from the Tenant or principal is required for the lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="personal_guarantee_requirement" class="form-control has-icon"
                data-icon="fa-solid fa-file-signature">
                <option value="">Select</option>
                <option value="Required">Required</option>
                <option value="Not Required">Not Required</option>
                <option value="Negotiable">Negotiable</option>
            </select>
        </div>
    </div>

    {{-- Commercial Approval Conditions --}}
    <div class="form-group">
        <label class="fw-bold">Commercial Approval Conditions:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List any conditions or requirements the Tenant must meet for the Landlord to approve the commercial lease.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="commercial_approval_conditions" class="form-control has-icon landlord-compact-textarea" rows="1"
                data-icon="fa-solid fa-clipboard-check"
                placeholder="Enter commercial approval conditions (e.g., 2 years of financial statements, business plan, references)"></textarea>
        </div>
    </div>
@endif

{{-- ===== END NEW LANDLORD LEASING TERMS FIELDS ===== --}}

@if ($property_type === 'Residential Property')
    <div class="form-group" wire:ignore wire:key="landlord-rent-includes-group">
        <label class="fw-bold">Rent Includes:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any utilities or services included in the rent.">
            <i class="fa-solid fa-circle-info"></i>

        </span>
        <div class="input-cover has-select-icon" wire:ignore>
            <select id="rent_includes" class="form-control has-icon select2-multiple rent_includes" multiple
                data-icon="fa-solid fa-receipt" data-placeholder="Select">
                @foreach ($rent_includes as $row_pt)
                    <option value="{{ $row_pt['name'] }}" {{ in_array($row_pt['name'], $this->rent_includes ?? []) ? 'selected' : '' }}>{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group mt-2 other_rent_include other_rent_input_wrapper" id="other_rent_includes_wrapper"
        style="display: {{ $is_rent_include_visible ? 'block' : 'none' }}">
        {{-- <label class="fw-bold">Please specify other included items:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_rent_include" class="form-control has-icon"
                data-icon="fa-solid fa-receipt"
                placeholder="Enter what rent includes (e.g., Water, Trash, Cable)">
        </div>
    </div>
@endif
