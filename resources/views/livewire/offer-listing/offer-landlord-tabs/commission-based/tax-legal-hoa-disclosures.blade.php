<h3 class="fw-bold mb-3">Tax, Legal, HOA &amp; Disclosures</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>Provide tax, legal, flood zone, CDD/assessment, and HOA details so Agents and Tenants have the full picture of ongoing obligations and restrictions.</strong>
        </div>
    </div>
</div>

{{-- ===== GROUP 1: TAX / LEGAL / PARCEL ===== --}}
<div class="card border mb-4">
    <div class="card-header fw-bold bg-light">
        <i class="fa-solid fa-landmark me-2 text-primary"></i>Tax / Legal / Parcel Information
    </div>
    <div class="card-body">

        {{-- Parcel ID --}}
        <div class="form-group">
            <label class="fw-bold">Parcel ID / Folio Number:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Enter the parcel identification number (also called folio number or tax account number) found on the property tax bill or county property appraiser website.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <input type="text" wire:model="parcel_id" class="form-control has-icon"
                    data-icon="fa-solid fa-hashtag"
                    placeholder="Enter parcel ID (e.g., 12-34-56-789-0001)">
            </div>
        </div>

        {{-- Tax Year --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Tax Year:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Enter the tax year for which the property taxes below are reported.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <input type="text" wire:model="tax_year" class="form-control has-icon"
                    data-icon="fa-solid fa-calendar-days"
                    placeholder="Enter Tax Year (e.g., 2025)">
            </div>
        </div>

        {{-- Annual Property Taxes --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Annual Property Taxes:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Enter the total annual property tax amount for the tax year listed. This figure is informational and may change after the lease commences.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <span class="input-group-text-seller">$</span>
                <input type="text" wire:model="annual_property_taxes" class="form-control"
                    style="padding-left: 12px;"
                    placeholder="Enter Annual Property Taxes (e.g., 5,200)"
                    data-error-id="annual_property_taxes_error"
                    oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
            </div>
            <span class="error mt-1" id="annual_property_taxes_error"></span>
        </div>

        {{-- Additional Parcels --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Additional Parcels Included in Listing:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether the listing includes additional parcels beyond the primary parcel listed above.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="additional_parcels" class="form-control has-icon"
                    data-icon="fa-solid fa-layer-group">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>

        @if ($additional_parcels === 'Yes')
            <div class="form-group mt-3">
                <label class="fw-bold">Total Number of Parcels:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Enter the total number of parcels included in this listing (including the primary parcel).">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <input type="number" wire:model="total_parcel_count" class="form-control has-icon"
                        data-icon="fa-solid fa-hashtag"
                        placeholder="Enter Total Number of Parcels (e.g., 3)" min="2">
                </div>
            </div>

            <div class="form-group mt-3">
                <label class="fw-bold">Additional Parcel IDs:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Enter the parcel ID numbers for all additional parcels included in the listing, one per line.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <textarea wire:model="additional_parcel_ids" class="form-control landlord-compact-textarea" rows="2"
                        style="padding: 10px; font-size: 16px;"
                        placeholder="Enter each additional parcel ID on a new line"></textarea>
                </div>
            </div>
        @endif

        {{-- Legal Description --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Legal Description:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Enter the full legal description of the property as it appears on the deed or county records. This typically includes lot, block, subdivision, section, township, and range information.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <textarea wire:model="legal_description" class="form-control landlord-compact-textarea" rows="2"
                    style="padding: 10px; font-size: 16px;"
                    placeholder="Enter legal description (e.g., Lot 12, Block 4, SUNSET HILLS SUBDIVISION, as recorded in Plat Book 25, Page 17)"></textarea>
            </div>
        </div>

    </div>
</div>

{{-- ===== GROUP 2: FLOOD ZONE ===== --}}
<div class="card border mb-4">
    <div class="card-header fw-bold bg-light">
        <i class="fa-solid fa-water me-2 text-primary"></i>Flood Zone
    </div>
    <div class="card-body">

        {{-- Flood Zone Code --}}
        <div class="form-group">
            <label class="fw-bold">Flood Zone Designation:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Select the FEMA flood zone designation for the property. Zone X indicates minimal flood hazard. Zones A and AE indicate high flood hazard areas where flood insurance is typically required by lenders.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select id="flood_zone_code" wire:model="flood_zone_code" class="form-control has-icon"
                    data-icon="fa-solid fa-map-location-dot">
                    <option value="">Select</option>
                    <option value="X">X — Minimal Flood Hazard</option>
                    <option value="AE">AE — High Risk (Base Flood Elevation Determined)</option>
                    <option value="A">A — High Risk (No Base Flood Elevation)</option>
                    <option value="AH">AH — High Risk (Shallow Flooding)</option>
                    <option value="AO">AO — High Risk (Sheet Flow Flooding)</option>
                    <option value="VE">VE — Coastal High Hazard</option>
                    <option value="V">V — Coastal High Hazard (No Base Flood Elevation)</option>
                    <option value="D">D — Undetermined Risk</option>
                    <option value="Unknown">Unknown</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="form-group mt-3" id="flood_zone_code_other_wrapper" style="display: {{ $flood_zone_code === 'Other' ? 'block' : 'none' }}">
            <label class="fw-bold">Other Flood Zone Code:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Enter the FEMA flood zone code exactly as shown on the official flood zone determination or FEMA Flood Map Service Center record.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <input type="text" wire:model="flood_zone_code_other" class="form-control has-icon"
                    data-icon="fa-solid fa-water"
                    placeholder="Enter flood zone code (e.g., AO, AR, A99)">
            </div>
        </div>

        {{-- Flood Insurance Required --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Flood Insurance Required:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether flood insurance is currently required for this property. Lenders typically require flood insurance for properties in high-risk flood zones (Zone A, AE, V, VE, etc.).">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="flood_insurance_required" class="form-control has-icon"
                    data-icon="fa-solid fa-shield-halved">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>

        {{-- Flood Zone Panel --}}
        <div class="form-group mt-3">
            <label class="fw-bold">FEMA Flood Zone Panel / Map Number:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Enter the FEMA flood map panel number (also called FIRM panel number) if known. This can be found on the flood zone determination or the FEMA Flood Map Service Center.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <input type="text" wire:model="flood_zone_panel" class="form-control has-icon"
                    data-icon="fa-solid fa-map"
                    placeholder="Enter FEMA Flood Zone Panel / Map Number (e.g., 12086C0318H)">
            </div>
        </div>

    </div>
</div>

{{-- ===== GROUP 3: CDD / SPECIAL ASSESSMENTS ===== --}}
<div class="card border mb-4">
    <div class="card-header fw-bold bg-light">
        <i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>CDD / Special Assessments
    </div>
    <div class="card-body">

        {{-- CDD --}}
        <div class="form-group">
            <label class="fw-bold">Community Development District (CDD):
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="A CDD (Community Development District) is a special-purpose government entity that finances and manages infrastructure and community services for a development. CDD fees are typically assessed annually and included in the property tax bill.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="has_cdd" class="form-control has-icon"
                    data-icon="fa-solid fa-city">
                    <option value="">Select</option>
                    <option value="Yes">Yes — Property is subject to a CDD</option>
                    <option value="No">No — No CDD</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>

        @if ($has_cdd === 'Yes')
            <div class="form-group mt-3">
                <label class="fw-bold">Annual CDD Fee:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Enter the annual CDD fee amount. This fee is typically billed with the property taxes and is the Tenant's responsibility during the lease term.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <span class="input-group-text-seller">$</span>
                    <input type="text" wire:model="annual_cdd_fee" class="form-control"
                        style="padding-left: 12px;"
                        placeholder="Enter Annual CDD Fee (e.g., 1800)"
                        data-error-id="annual_cdd_fee_error"
                        oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                </div>
                <span class="error mt-1" id="annual_cdd_fee_error"></span>
            </div>
        @endif

        {{-- Special Assessments --}}
        <div class="form-group mt-4">
            <label class="fw-bold">Special Assessments:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Special assessments are charges levied by local governments or HOAs for specific improvements such as road paving, sewer installation, or other infrastructure projects. Disclose any outstanding or pending special assessments.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="has_special_assessments" class="form-control has-icon"
                    data-icon="fa-solid fa-file-invoice">
                    <option value="">Select</option>
                    <option value="Yes">Yes — Outstanding or pending special assessments exist</option>
                    <option value="No">No — No special assessments</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>

        @if ($has_special_assessments === 'Yes')
            <div class="form-group mt-3">
                <label class="fw-bold">Special Assessment Amount:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Enter the total outstanding special assessment balance or annual payment amount.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <span class="input-group-text-seller">$</span>
                    <input type="text" wire:model="special_assessment_amount" class="form-control"
                        style="padding-left: 12px;"
                        placeholder="Enter Special Assessment Amount (e.g., 4500)"
                        data-error-id="special_assessment_amount_error"
                        oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                </div>
                <span class="error mt-1" id="special_assessment_amount_error"></span>
            </div>

            <div class="form-group mt-3">
                <label class="fw-bold">Special Assessment Description:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Briefly describe the nature of the special assessment (e.g., road repaving, sewer connection, drainage improvement).">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <textarea wire:model="special_assessment_description" class="form-control" rows="2"
                        style="padding: 10px; font-size: 16px;"
                        placeholder="Describe the special assessment (e.g., Road resurfacing assessment through 2028 at $900/year)"></textarea>
                </div>
            </div>
        @endif

    </div>
</div>

{{-- ===== GROUP 4: STRUCTURED HOA ===== --}}
<div class="card border mb-4">
    <div class="card-header fw-bold bg-light">
        <i class="fa-solid fa-building-user me-2 text-primary"></i>HOA / Association
    </div>
    <div class="card-body">

        {{-- Is there an HOA --}}
        <div class="form-group">
            <label class="fw-bold">Is there a Homeowners Association (HOA) or Community Association?
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether the property is governed by a homeowners association, condominium association, or any other community or property owners association.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="has_hoa" class="form-control has-icon"
                    data-icon="fa-solid fa-building-user">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>

        @if ($has_hoa === 'Yes')

            {{-- Association Type --}}
            <div class="form-group mt-3">
                <label class="fw-bold">Association Type:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Select the type of association governing this property.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <select id="association_type" wire:model="association_type" class="form-control has-icon"
                        data-icon="fa-solid fa-sitemap">
                        <option value="">Select</option>
                        <option value="Homeowners Association (HOA)">Homeowners Association (HOA)</option>
                        <option value="Condominium Association">Condominium Association</option>
                        <option value="Property Owners Association (POA)">Property Owners Association (POA)</option>
                        <option value="Cooperative (Co-op)">Cooperative (Co-op)</option>
                        <option value="Community Association">Community Association</option>
                        <option value="Master Association">Master Association</option>
                        <option value="Commercial Association">Commercial Association</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group mt-2" id="association_type_other_wrapper" style="display: {{ $association_type === 'Other' ? 'block' : 'none' }}">
                <div class="input-cover">
                    <input type="text" wire:model="association_type_other" class="form-control has-icon"
                        data-icon="fa-solid fa-sitemap"
                        placeholder="Describe the association type">
                </div>
            </div>

            {{-- Association Name --}}
            <div class="form-group mt-3">
                <label class="fw-bold">Association Name:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Enter the full legal name of the association as it appears in the governing documents or on the association's website.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <input type="text" wire:model="association_name" class="form-control has-icon"
                        data-icon="fa-solid fa-building"
                        placeholder="Enter Association Name (e.g., Sunset Hills Homeowners Association, Inc.)">
                </div>
            </div>

            <div class="row mt-3">
                {{-- Fee Amount --}}
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="fw-bold">Association Fee Amount:
                            <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                                title="Enter the current association fee amount per the payment frequency selected below.">
                                <i class="fa-solid fa-circle-info"></i>
                            </span>
                        </label>
                        <div class="input-cover">
                            <span class="input-group-text-seller">$</span>
                            <input type="text" wire:model="association_fee_amount" class="form-control"
                                style="padding-left: 12px;"
                                placeholder="Enter Association Fee Amount (e.g., 350)"
                                data-error-id="association_fee_amount_error"
                                oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                        </div>
                        <span class="error mt-1" id="association_fee_amount_error"></span>
                    </div>
                </div>

                {{-- Fee Frequency --}}
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="fw-bold">Fee Frequency:
                            <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                                title="Select how often the association fee is charged.">
                                <i class="fa-solid fa-circle-info"></i>
                            </span>
                        </label>
                        <div class="input-cover">
                            <select id="association_fee_frequency" wire:model="association_fee_frequency" class="form-control has-icon"
                                data-icon="fa-regular fa-calendar-days">
                                <option value="">Select</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Bi-Monthly">Bi-Monthly</option>
                                <option value="Quarterly">Quarterly</option>
                                <option value="Semi-Annually">Semi-Annually</option>
                                <option value="Annually">Annually</option>
                                <option value="One-Time">One-Time</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group mt-2" id="association_fee_frequency_other_wrapper" style="display: {{ $association_fee_frequency === 'Other' ? 'block' : 'none' }}">
                <div class="input-cover">
                    <input type="text" wire:model="association_fee_frequency_other" class="form-control has-icon"
                        data-icon="fa-regular fa-calendar-days"
                        placeholder="Describe the fee frequency">
                </div>
            </div>

            {{-- Approval Required --}}
            <div class="form-group mt-3">
                <label class="fw-bold">Association Approval Required for Tenancy:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Indicate whether the association must approve the Tenant before the tenancy can begin. Some associations require tenants to submit an application, pay an application fee, and receive formal approval prior to or as a condition of moving in.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <select wire:model="association_approval_required" class="form-control has-icon"
                        data-icon="fa-solid fa-stamp">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                        <option value="Unknown">Unknown</option>
                    </select>
                </div>
            </div>

            @if ($association_approval_required === 'Yes')
                <div class="form-group mt-3">
                    <label class="fw-bold">Approval Process Details:
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                            title="Describe the association's approval process, including required documents, interview requirements, and typical timeline.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                    </label>
                    <div class="input-cover">
                        <textarea wire:model="association_approval_process" class="form-control" rows="2"
                            style="padding: 10px; font-size: 16px;"
                            placeholder="Describe the approval process (e.g., Application required, background check, 30-day review period)"></textarea>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="fw-bold">Association Application Fee:
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                            title="Enter the association application fee amount if applicable. This is typically paid by the Tenant.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                    </label>
                    <div class="input-cover">
                        <span class="input-group-text-seller">$</span>
                        <input type="text" wire:model="association_application_fee" class="form-control"
                            placeholder="Enter Association Application Fee (e.g., 150)"
                            data-error-id="association_application_fee_error"
                            oninput="validateInput(this)" onblur="reformatNumber(this)" onpaste="handlePaste(event)">
                    </div>
                    <span class="error mt-1" id="association_application_fee_error"></span>
                </div>
            @endif

            {{-- What does the fee include (Select2 multi-select) --}}
            <div class="form-group mt-3">
                <label class="fw-bold">What Does the Association Fee Include?
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Select all services and amenities covered by the association fee.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                @php
                    $feeIncludesOptions = [
                        'Cable TV', 'Common Area Maintenance', 'Community Pool',
                        'Exterior Maintenance', 'Flood Insurance', 'Gas',
                        'Grounds Maintenance', 'Insurance', 'Internet',
                        'Pest Control', 'Private Road Maintenance', 'Recreational Facilities',
                        'Roof Maintenance', 'Security', 'Sewer', 'Trash',
                        'Water', 'Other',
                    ];
                @endphp
                <div class="input-cover" wire:ignore>
                    <i class="input-icon fa-solid fa-list-check input-icon2"></i>
                    <select id="association_fee_includes" class="form-control has-icon select2-multiple" multiple>
                        @foreach ($feeIncludesOptions as $option)
                            <option value="{{ $option }}" {{ in_array($option, $association_fee_includes ?? []) ? 'selected' : '' }}>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div id="hoa-fee-includes-other-section" wire:ignore.self style="display: {{ in_array('Other', $association_fee_includes ?? []) ? 'block' : 'none' }}">
                <div class="form-group mt-2">
                    <div class="input-cover">
                        <input type="text" wire:model="association_fee_includes_other" class="form-control has-icon"
                            data-icon="fa-solid fa-list-check"
                            placeholder="Enter what else is included (e.g., Roof Maintenance, Building Reserves)">
                    </div>
                </div>
            </div>

            {{-- Association Amenities (Select2 multi-select) --}}
            <div class="form-group mt-3">
                <label class="fw-bold">Association Amenities:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Select all amenities available to residents through the association.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                @php
                    $amenityOptions = [
                        'Basketball Court', 'Boat Slip/Marina', 'Clubhouse', 'Dog Park',
                        'Fitness Center / Gym', 'Gated Entry', 'Golf Course', 'Jogging / Walking Trail',
                        'Pickleball Court', 'Playground', 'Pool', 'Recreation Center',
                        'Sauna / Spa', 'Tennis Court', 'Waterfront Access', 'Other',
                    ];
                @endphp
                <div class="input-cover" wire:ignore>
                    <i class="input-icon fa-solid fa-star input-icon2"></i>
                    <select id="association_amenities" class="form-control has-icon select2-multiple" multiple>
                        @foreach ($amenityOptions as $option)
                            <option value="{{ $option }}" {{ in_array($option, $association_amenities ?? []) ? 'selected' : '' }}>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div id="hoa-amenities-other-section" wire:ignore.self style="display: {{ in_array('Other', $association_amenities ?? []) ? 'block' : 'none' }}">
                <div class="form-group mt-2">
                    <div class="input-cover">
                        <input type="text" wire:model="association_amenities_other" class="form-control has-icon"
                            data-icon="fa-solid fa-star"
                            placeholder="Enter association amenity (e.g., Community Garden)">
                    </div>
                </div>
            </div>

            {{-- Leasing Restrictions --}}
            <div class="form-group mt-3">
                <label class="fw-bold">Leasing / Rental Restrictions:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Indicate whether the association imposes any restrictions on renting or leasing the property. This is important for prospective tenants to know before entering a lease.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <select wire:model="leasing_restrictions" class="form-control has-icon"
                        data-icon="fa-solid fa-ban">
                        <option value="">Select</option>
                        <option value="Yes">Yes — Leasing restrictions apply</option>
                        <option value="No">No — No leasing restrictions</option>
                        <option value="Not Applicable">Not Applicable</option>
                        <option value="Unknown">Unknown</option>
                    </select>
                </div>
            </div>

            @if ($leasing_restrictions === 'Yes')
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="fw-bold">Minimum Lease Period:
                                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="Select the minimum lease term permitted by the association.">
                                    <i class="fa-solid fa-circle-info"></i>
                                </span>
                            </label>
                            <div class="input-cover">
                                <select id="min_lease_period" wire:model="min_lease_period" class="form-control has-icon"
                                    data-icon="fa-regular fa-clock">
                                    <option value="">Select</option>
                                    <option value="1 Week">1 Week</option>
                                    <option value="2 Weeks">2 Weeks</option>
                                    <option value="1 Month">1 Month</option>
                                    <option value="2 Months">2 Months</option>
                                    <option value="3 Months">3 Months</option>
                                    <option value="6 Months">6 Months</option>
                                    <option value="7 Months">7 Months</option>
                                    <option value="8 Months">8 Months</option>
                                    <option value="9 Months">9 Months</option>
                                    <option value="10 Months">10 Months</option>
                                    <option value="11 Months">11 Months</option>
                                    <option value="1 Year">1 Year</option>
                                    <option value="2 Years">2 Years</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="fw-bold">Max Leases Per Year:
                                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="Enter the maximum number of separate leases/tenancies permitted per year by the association.">
                                    <i class="fa-solid fa-circle-info"></i>
                                </span>
                            </label>
                            <div class="input-cover">
                                <input type="number" wire:model="max_leases_per_year" class="form-control has-icon"
                                    data-icon="fa-solid fa-hashtag"
                                    placeholder="Enter Max Leases Per Year (e.g., 2)" min="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-2" id="min_lease_period_other_wrapper" style="display: {{ $min_lease_period === 'Other' ? 'block' : 'none' }}">
                    <div class="input-cover">
                        <input type="text" wire:model="min_lease_period_other" class="form-control has-icon"
                            data-icon="fa-regular fa-clock"
                            placeholder="Specify minimum lease period">
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="fw-bold">Additional Leasing Restrictions:
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                            title="Describe any additional leasing restrictions imposed by the association, such as owner occupancy requirements, tenant approval processes, or prohibited short-term rental platforms.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                    </label>
                    <div class="input-cover">
                        <textarea wire:model="additional_lease_restrictions" class="form-control" rows="2"
                            style="padding: 10px; font-size: 16px;"
                            placeholder="Describe additional restrictions (e.g., No Airbnb/VRBO, owner must occupy 1 year before renting, tenant must be HOA-approved)"></textarea>
                    </div>
                </div>
            @endif

            {{-- Pet Restrictions --}}
            <div class="form-group mt-3">
                <label class="fw-bold">Pet Restrictions:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Indicate whether the association imposes any pet restrictions, such as size limits, breed restrictions, or number of pets allowed.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <select wire:model="pet_restrictions" class="form-control has-icon"
                        data-icon="fa-solid fa-paw">
                        <option value="">Select</option>
                        <option value="Yes">Yes — Pet restrictions apply</option>
                        <option value="No">No — No pet restrictions</option>
                        <option value="Not Applicable">Not Applicable</option>
                        <option value="Unknown">Unknown</option>
                    </select>
                </div>
            </div>

            @if ($pet_restrictions === 'Yes')
                <div class="form-group mt-2">
                    <label class="fw-bold">Pet Restriction Details:
                        <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                            title="Describe the pet restrictions imposed by the association, such as size limits, breed restrictions, or the maximum number of pets allowed.">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                    </label>
                    <div class="input-cover">
                        <textarea wire:model="pet_restrictions_detail" class="form-control" rows="2"
                            style="padding: 10px; font-size: 16px;"
                            placeholder="Describe pet restrictions (e.g., Max 2 pets, no dogs over 25 lbs, no aggressive breeds)"></textarea>
                    </div>
                </div>
            @endif

        @endif

    </div>
</div>

{{-- ===== GROUP 5: DOCUMENTS & DISCLOSURES ===== --}}
<div class="card border mb-4">
    <div class="card-header fw-bold bg-light">
        <i class="fa-solid fa-file-shield me-2 text-primary"></i>Documents &amp; Disclosures
    </div>
    <div class="card-body">

        {{-- Landlord Disclosure Available --}}
        <div class="form-group">
            <label class="fw-bold">Landlord Disclosure Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether a completed Landlord Property Disclosure form is available for this listing. Landlord disclosures inform tenants of known property conditions and defects.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="landlord_disclosure_available" class="form-control has-icon"
                    data-icon="fa-solid fa-file-lines">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($landlord_disclosure_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Landlord Disclosure:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="landlord_disclosure_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="landlord_disclosure_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('landlord_disclosure_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($landlord_disclosure_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($landlord_disclosure_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Survey Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Survey Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether a current property survey is available. A survey shows the legal boundaries, improvements, and easements of the property.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="survey_available" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($survey_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Survey:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="survey_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="survey_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('survey_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($survey_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($survey_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Inspection Report Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Inspection Report Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether a recent property inspection report is available. Pre-listing inspections can increase tenant confidence and reduce negotiation friction.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="inspection_report_available" class="form-control has-icon"
                    data-icon="fa-solid fa-magnifying-glass">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($inspection_report_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Inspection Report:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="inspection_report_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="inspection_report_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('inspection_report_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($inspection_report_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($inspection_report_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- HOA/Condo Documents Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">HOA/Condo Documents Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether HOA or condominium governing documents are available, including CC&Rs, bylaws, rules and regulations, and financial statements.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="hoa_condo_docs_available" class="form-control has-icon"
                    data-icon="fa-solid fa-building-user">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($hoa_condo_docs_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload HOA/Condo Documents:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="hoa_condo_docs_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="hoa_condo_docs_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('hoa_condo_docs_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($hoa_condo_docs_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($hoa_condo_docs_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Flood Disclosure Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Flood Disclosure Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether a flood zone disclosure or flood history disclosure is available for this property.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="flood_disclosure_available" class="form-control has-icon"
                    data-icon="fa-solid fa-water">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($flood_disclosure_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Flood Disclosure:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="flood_disclosure_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="flood_disclosure_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('flood_disclosure_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($flood_disclosure_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($flood_disclosure_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Lead-Based Paint Disclosure Required --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Lead-Based Paint Disclosure Required:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Federal law requires landlords to disclose known lead-based paint and hazards for homes built before 1978. Select Yes if this disclosure is required for this property.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="lead_based_paint_disclosure" class="form-control has-icon"
                    data-icon="fa-solid fa-triangle-exclamation">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($lead_based_paint_disclosure === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Lead-Based Paint Disclosure:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="lead_based_paint_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="lead_based_paint_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('lead_based_paint_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($lead_based_paint_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($lead_based_paint_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Environmental Report Available --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Environmental Report Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Indicate whether an environmental assessment or report (such as a Phase I or Phase II ESA) is available for this property.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            <div class="input-cover">
                <select wire:model="environmental_report_available" class="form-control has-icon"
                    data-icon="fa-solid fa-leaf">
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable">Not Applicable</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
        </div>
        @if ($environmental_report_available === 'Yes')
            <div class="conditional-upload-block">
                <label class="fw-bold">Upload Environmental Report:</label>
                <p class="text-muted small mb-1">Upload the document related to this disclosure.</p>
                <input type="file" wire:model="environmental_report_file" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <div wire:loading wire:target="environmental_report_file" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Uploading…
                </div>
                @error('environmental_report_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($environmental_report_file_path)
                    <div class="mt-1 small">
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($environmental_report_file_path) }}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>View current file
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Additional Documents Available (Select2 multi-select) --}}
        <div class="form-group mt-3">
            <label class="fw-bold">Additional Documents Available:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Select any additional documents available for this listing. You may select multiple options or type to add a custom entry.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
            </label>
            @php
                $additionalDocumentOptions = [
                    'Appraisal Report', 'As-Built Plans / Floor Plans', 'Certificate of Occupancy',
                    'Drainage / Stormwater Report', 'Elevation Certificate', 'Energy Audit Report',
                    'Geotechnical / Soil Report', 'Hazardous Materials Report', 'Historic Designation Documents',
                    'Lease Agreements (Existing Tenants)', 'Maintenance Records', 'Permits & Permit History',
                    'Roof Certification', 'Septic Inspection Report', 'Title Insurance Commitment',
                    'Utility Bills / History', 'Warranty Documents', 'Well Water Test Report', 'Other',
                ];
            @endphp
            <div class="input-cover" wire:ignore>
                <i class="input-icon fa-solid fa-folder-open input-icon2"></i>
                <select id="additional_documents" class="form-control has-icon select2-multiple" multiple>
                    @foreach ($additionalDocumentOptions as $option)
                        <option value="{{ $option }}" {{ in_array($option, $additional_documents ?? []) ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="additional-documents-other-section" wire:ignore.self style="display: {{ in_array('Other', $additional_documents ?? []) ? 'block' : 'none' }}">
            <div class="form-group mt-2">
                <label class="fw-bold">Other Document Type:
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="Describe the additional document type that is available for this property.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </label>
                <div class="input-cover">
                    <input type="text" wire:model="other_document_type" class="form-control has-icon"
                        data-icon="fa-solid fa-folder-open"
                        placeholder="Enter document type (e.g., Lead Paint Disclosure, Inspection Report)">
                </div>
            </div>
        </div>

    </div>
</div>
