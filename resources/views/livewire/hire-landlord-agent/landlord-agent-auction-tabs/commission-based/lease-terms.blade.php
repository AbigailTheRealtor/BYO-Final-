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

    $tenantPaysChecklist = [
        ['name' => 'Association Fees'],
        ['name' => 'Capital Expenses'],
        ['name' => 'Common Area Maintenance'],
        ['name' => 'Condominium Fees'],
        ['name' => 'Electricity'],
        ['name' => 'Gas'],
        ['name' => 'Liability Insurance'],
        ['name' => 'Parking Fee'],
        ['name' => 'Pro-Rated'],
        ['name' => 'Property Insurance'],
        ['name' => 'Property Taxes'],
        ['name' => 'Reserves'],
        ['name' => 'Sewer'],
        ['name' => 'Trash Collection'],
        ['name' => 'Water'],
        ['name' => 'None '],
        ['name' => 'Other'],
    ];

    $rentIncludesChecklist = [
        ['name' => 'Cable TV'],
        ['name' => 'Electricity'],
        ['name' => 'Gas'],
        ['name' => 'Grounds Care'],
        ['name' => 'Insurance'],
        ['name' => 'Internet'],
        ['name' => 'Laundry'],
        ['name' => 'Management'],
        ['name' => 'Pest Control'],
        ['name' => 'Pool Maintenance'],
        ['name' => 'Recreational'],
        ['name' => 'Repairs'],
        ['name' => 'Security'],
        ['name' => 'Sewer'],
        ['name' => 'Taxes'],
        ['name' => 'Telephone'],
        ['name' => 'Trash Collection'],
        ['name' => 'Water'],
        ['name' => 'None'],
        ['name' => 'Other'],
    ];

    $tenantPaysIconMap = [
        'Association Fees'        => 'fa-solid fa-building-columns',
        'Capital Expenses'        => 'fa-solid fa-money-bill-trend-up',
        'Common Area Maintenance' => 'fa-solid fa-broom',
        'Condominium Fees'        => 'fa-solid fa-building',
        'Electricity'             => 'fa-solid fa-bolt',
        'Gas'                     => 'fa-solid fa-fire-flame-curved',
        'Liability Insurance'     => 'fa-solid fa-scale-balanced',
        'Parking Fee'             => 'fa-solid fa-square-parking',
        'Pro-Rated'               => 'fa-solid fa-calculator',
        'Property Insurance'      => 'fa-solid fa-shield-halved',
        'Property Taxes'          => 'fa-solid fa-landmark',
        'Reserves'                => 'fa-solid fa-piggy-bank',
        'Sewer'                   => 'fa-solid fa-water-ladder',
        'Trash Collection'        => 'fa-solid fa-trash-can',
        'Water'                   => 'fa-solid fa-droplet',
        'None '                   => 'fa-solid fa-ban',
        'Other'                   => 'fa-solid fa-ellipsis',
    ];

    $rentIncludesIconMap = [
        'Cable TV'         => 'fa-solid fa-tv',
        'Electricity'      => 'fa-solid fa-bolt',
        'Gas'              => 'fa-solid fa-fire-flame-curved',
        'Grounds Care'     => 'fa-solid fa-leaf',
        'Insurance'        => 'fa-solid fa-shield-halved',
        'Internet'         => 'fa-solid fa-wifi',
        'Laundry'          => 'fa-solid fa-shirt',
        'Management'       => 'fa-solid fa-user-tie',
        'Pest Control'     => 'fa-solid fa-bug',
        'Pool Maintenance' => 'fa-solid fa-person-swimming',
        'Recreational'     => 'fa-solid fa-dumbbell',
        'Repairs'          => 'fa-solid fa-wrench',
        'Security'         => 'fa-solid fa-shield',
        'Sewer'            => 'fa-solid fa-water-ladder',
        'Taxes'            => 'fa-solid fa-file-invoice-dollar',
        'Telephone'        => 'fa-solid fa-phone',
        'Trash Collection' => 'fa-solid fa-trash-can',
        'Water'            => 'fa-solid fa-droplet',
        'None'             => 'fa-solid fa-ban',
        'Other'            => 'fa-solid fa-ellipsis',
    ];
@endphp
<!-- Section Heading -->
<h3>Leasing Terms</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>💵 Enter the Landlord's preferred lease terms. These terms will be used as the starting point for Tenant lease offers and future counteroffers.</strong>
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
                data-icon="fa-regular fa-clock" placeholder="Enter occupied-until date (e.g., December 2025)" required>
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
        <select wire:model="leasing_spaces" class="form-control has-icon" data-icon="fa-solid fa-building">
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
                        @if ($property_type === 'Residential Property') placeholder="Enter details (e.g., Visiting Hours, Overnight Stay Rules)"
                @elseif($property_type === 'Commercial Property')
                placeholder="Enter details (e.g., Visiting Hours, Overnight Use, Guest Limitations)"
                @else
                placeholder="Enter details (e.g., Visiting Hours, Overnight Use, Guest Limitations)" @endif>
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
                        placeholder="Enter details (e.g., Visiting Hours, Overnight Stay Rules)" />
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
                        placeholder="Enter details (e.g., Visiting Hours, Overnight Use, Guest Limitations)">

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
                        placeholder="Enter shared amenities (e.g., Conference Rooms, Parking Facilities)">
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
                        placeholder="Enter layout (e.g., Open Floor Plan, Private Offices)">
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
                        placeholder="Enter details (e.g., Shared Hours, Overnight Use, Guest Limitations)">
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
                    placeholder="Enter shared amenities (e.g., Conference Rooms, Parking Facilities)">
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
                    placeholder="Enter layout (e.g., Open Floor Plan, Private Offices)">
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
    <div class="form-group" wire:key="landlord-tenant-pays-checklist">
        <label class="fw-bold">Tenant Pays:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any expenses or responsibilities the Tenant is required to pay under the lease terms.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div x-data="{
            selected: $wire.entangle('tenant_pays').defer,
            toggle(val) {
                let arr = Array.isArray(this.selected) ? [...this.selected] : [];
                let idx = arr.indexOf(val);
                if (idx === -1) {
                    arr.push(val);
                } else {
                    arr.splice(idx, 1);
                    if (val === 'Other') { $wire.set('other_tenant_pays', '', false); }
                }
                this.selected = arr;
            },
            isSelected(val) { return Array.isArray(this.selected) && this.selected.indexOf(val) !== -1; }
        }">
            <div class="utility-checklist-grid mt-2">
                @foreach ($tenantPaysChecklist as $option)
                    @php $optName = $option['name']; @endphp
                    <div class="utility-checklist-card"
                        :class="{ 'utility-selected': isSelected('{{ addslashes($optName) }}') }"
                        @click="toggle('{{ addslashes($optName) }}')">
                        <span class="utility-checklist-icon">
                            <i class="{{ $tenantPaysIconMap[$optName] ?? 'fa-solid fa-circle-dot' }}"></i>
                        </span>
                        <span class="utility-checklist-label">{{ $optName }}</span>
                    </div>
                @endforeach
            </div>
            <div class="form-group mt-2" id="other_tenant_pays_wrapper" x-show="isSelected('Other')" style="display: none;">
                <div class="input-cover">
                    <input type="text" wire:model="other_tenant_pays" class="form-control has-icon"
                        data-icon="fa-solid fa-ellipsis"
                        placeholder="Enter expenses the Tenant is responsible for (e.g., HVAC Maintenance, Janitorial Services, Security Monitoring)">
                </div>
            </div>
        </div>
    </div>

    <div class="form-group" wire:ignore wire:key="landlord-owner-pays-group">
        <label class="fw-bold">Owner Pays:</label>

        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any expenses the Owner will cover as part of the lease arrangement.">
            <i class="fa-solid fa-circle-info"></i>

        </span>
        <div class="input-cover" wire:ignore>
            <select id="owner_pays" class="owner_pays form-control has-icon select2-multiple"
                data-icon="fa-solid fa-user-tie input-icon2" data-placeholder="Select" multiple>
                <option value=""></option>
                @foreach ($ownerPays as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group other_owner_pays" style="display: {{ $is_other_owner_pays_visible ? 'block' : 'none' }}">
        <div class="input-cover">
            <input type="text" wire:model="other_owner_pays" class="form-control has-icon"
                data-icon="fa-solid fa-user-tie"
                placeholder="Enter expenses paid by Owner (e.g., Elevator Maintenance, Window Cleaning, Janitorial Services)">
        </div>
    </div>

    {{-- 📜 Terms of Lease --}}
    {{-- <div class="form-group" wire:ignore>
        <label class="fw-bold">Terms of Lease:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the lease term offered for this property. This defines how costs and responsibilities—such as taxes, insurance, and maintenance—are structured between the Landlord and Tenant.">
            <i class="fa-solid fa-circle-info"></i>
        </span>

        <div class="input-cover">
            <select id="terms_of_lease" wire:model="terms_of_lease"  class="terms_of_lease form-control has-icon select2-multiple"
                data-icon="fa-solid fa-file-signature input-icon2" multiple required>
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

    <div class="input-cover" wire:ignore>
        <select id="terms_of_lease" class="terms_of_lease form-control has-icon select2-multiple"
            data-icon="fa-solid fa-file-signature input-icon2" data-placeholder="Select" multiple>
            <option value=""></option>
            @foreach ($lease_types as $type)
            <option value="{{ $type['name'] }}">{{ $type['name'] }}</option>

            @endforeach
        </select>
    </div>

    {{-- Show custom input when "Other" is selected --}}
    <div id="otherLeaseContainer" class="other-lease-input mt-3 d-none">
        <div class="form-group">
            {{-- <label class="fw-bold">Custom Lease Term:</label> --}}
            <div class="input-cover">
                <input type="text" wire:model="custom_lease_term" class="form-control has-icon"
                    data-icon="fa-solid fa-file-signature" placeholder="Enter lease type (e.g., Build-to-Suit Lease)">
            </div>
        </div>
    </div>
</div>
@endif

<div class="form-group mt-2 owner_pays_other_wrapper" style="display: none;">
    <label class="fw-bold">Please specify other expense paid by Owner:</label>
    <div class="input-cover">
        <input type="text" wire:model="owner_pays_other" class="form-control has-icon"
            data-icon="fa-solid fa-pencil" placeholder="Enter expense paid by Owner (e.g., Elevator maintenance)">
    </div>
</div>

<div class="form-group">
    <label class="fw-bold">Desired Rental Amount:<span class="text-danger">*</span></label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter the monthly rent amount the Landlord would like to collect. ">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>

        <input type="text" wire:model="desired_rental_amount" class="form-control has-icon"
            placeholder="Enter desired rental amount (e.g., 5000)" required
             data-error-id="desired_rental_amount_error"
                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)"
            >
    </div>
    <span class="error mt-2" id="desired_rental_amount_error"></span>

</div>

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
    <div class="input-cover" wire:ignore>
        <select class="lease_term_options form-control has-icon select2-multiple"
            data-icon="fa-solid fa-calendar-days input-icon2" data-placeholder="Select" multiple>
            <option value=""></option>
            @if ($property_type === 'Residential Property')
                @foreach ($residential_lease_term_options as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            @elseif ($property_type === 'Commercial Property')
                @foreach ($Commercial_lease_term_options as $row_pt)
                    <option value="{{ $row_pt['name'] }}">{{ $row_pt['name'] }}</option>
                @endforeach
            @endif

            <option value="Other">Other</option>
        </select>
    </div>

    <div class="form-group mt-2 other_lease_term other_lease_term_wrapper"
        style="display: {{ $is_update_lease_term_option_visible ? 'block' : 'none' }}">
        {{-- <label class="fw-bold">Other Lease Term:</label> --}}
        <div class="input-cover">
            <input type="text" wire:model="other_lease_term" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-days" placeholder="Enter desired lease term (e.g., 8 Months)">
        </div>
    </div>

</div>
@error('desired_lease_length')
    <span class="text-danger">{{ $message }}</span>
@enderror

@if ($property_type === 'Residential Property')
    <div class="form-group" wire:key="landlord-rent-includes-checklist">
        <label class="fw-bold">Rent Includes:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select any utilities or services included in the rent.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div x-data="{
            selected: $wire.entangle('rent_includes').defer,
            toggle(val) {
                let arr = Array.isArray(this.selected) ? [...this.selected] : [];
                let idx = arr.indexOf(val);
                if (idx === -1) {
                    arr.push(val);
                } else {
                    arr.splice(idx, 1);
                    if (val === 'Other') { $wire.set('other_rent_include', '', false); }
                }
                this.selected = arr;
            },
            isSelected(val) { return Array.isArray(this.selected) && this.selected.indexOf(val) !== -1; }
        }">
            <div class="utility-checklist-grid mt-2">
                @foreach ($rentIncludesChecklist as $option)
                    @php $optName = $option['name']; @endphp
                    <div class="utility-checklist-card"
                        :class="{ 'utility-selected': isSelected('{{ addslashes($optName) }}') }"
                        @click="toggle('{{ addslashes($optName) }}')">
                        <span class="utility-checklist-icon">
                            <i class="{{ $rentIncludesIconMap[$optName] ?? 'fa-solid fa-circle-dot' }}"></i>
                        </span>
                        <span class="utility-checklist-label">{{ $optName }}</span>
                    </div>
                @endforeach
            </div>
            <div class="form-group mt-2" id="other_rent_includes_wrapper" x-show="isSelected('Other')" style="display: none;">
                <div class="input-cover">
                    <input type="text" wire:model="other_rent_include" class="form-control has-icon"
                        data-icon="fa-solid fa-receipt"
                        placeholder="Enter what rent includes (e.g., Water, Trash, Cable)">
                </div>
            </div>
        </div>
    </div>
@endif
