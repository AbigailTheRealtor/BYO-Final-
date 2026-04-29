<div class="wizard-step" data-step="1">
    <div class="form-group">
        <label class="fw-bold">Is the landlord currently represented by another agent?
        </label>
        <select class="grid-picker" name="working_with_agent" id="working_with_agent"
            style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($yes_or_nos as $yes_or_no)
                <option value="{{ $yes_or_no['name'] }}"
                    data-target="{{ $yes_or_no['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>' {{isset($auction->get->working_with_agent) && $auction->get->working_with_agent === $yes_or_no['name'] ? 'selected' : ''}}>
                    {{ $yes_or_no['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label class="yes_message text-danger"
            style="font-size:18px; font-weight:bold;"></label>
    </div>
</div>
<div class="wizard-step" data-step="2">
    <h4> Please provide the property&#39;s complete
        address, along with the city, county, and state, pertaining to the real estate asset
        that the
        landlord intends to place on the market:</h4>
    <div class="form-group">
        <label class="fw-bold">Address:</label>
        <input type="text" name="address" class="form-control search_places has-icon"
            data-type="address" required value="{{ old('address', isset($auction->get->address) ? $auction->get->address : '') }}"
            data-icon="fa-solid fa-location-dot" placeholder="" />
    </div>

    <div class="form-group">
        <label class="fw-bold">Unit Number:</label>
        <input type="text" name="unit_num" data-type="cities" id="city" value="{{old('unit_num', isset($auction->get->unit_num) ? $auction->get->unit_num : '')}}"
            class="form-control has-icon search_places" data-icon="fa-solid fa-city"
            placeholder="">
    </div>

    <div class="form-group">
        <label class="fw-bold">City: </label>
        <input type="text" name="city" data-type="cities" id="city" value="{{old('city', isset($auction->get->city) ? $auction->get->city : '')}}"
            class="form-control has-icon search_places" data-icon="fa-solid fa-city"
            placeholder="" required>
    </div>

    <div class="form-group">
        <label class="fw-bold">County: </label>
        <input type="text" name="county" data-type="counties" id="county" value="{{old('county', isset($auction->get->county) ? $auction->get->county : '')}}"
            class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
            placeholder="" required>
    </div>

    <div class="form-group">
        <label class="fw-bold">State:</label>
        <input type="text" name="state" data-type="states" id="state" value="{{old('state', isset($auction->get->state) ? $auction->get->state : '')}}"
            class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
            placeholder="" required>
    </div>
    <small class="mt-1 fs-5">Note: The listing's address remains confidential until a seller
        employs an agent.
    </small>
</div>
<div class="wizard-step" data-step="3">
    <?php
        $listingDateTime = $auction->listing_date;
        $listingDate = (new DateTime($listingDateTime))->format('Y-m-d');
        
        $expirationDateTime = $auction->expiration_date;
        $expirationDate = (new DateTime($expirationDateTime))->format('Y-m-d');
    ?>
    <div class="form-group">
        <label for="address" class="fw-bold">Listing Date:</label>
        <input type="date" name="listing_date" id="listing_date" value="{{old('listing_date', $listingDate)}}"
            class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
            min="{{ date('Y-m-d') }}" required>
    </div>

    <div class="form-group">
        <label for="address" class="fw-bold">Expiration Date:</label>
        <input type="date" name="expiration_date" id="expiration_date" value="{{old('expiration_date', $expirationDate)}}"
            class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
            min="{{ date('Y-m-d') }}" required>
    </div>
</div>
<div class="wizard-step" data-step="4">
    <div class="form-group">
        <label class="fw-bold">
            Listing Type:
        </label>
        <div>
            @php
                $auction_types = [
                    [
                        'name' => 'Auction Listing',
                        'icon' => '<i class="fa-regular fa-clock"></i>',
                        'target' => '.auction_length_cover',
                    ],
                    [
                        'name' => 'Traditional Listing',
                        'icon' => '<i class="fa-solid fa-clipboard-list"></i>',
                        'target' => '',
                    ],
                ];
            @endphp

            <select name="auction_type" id="auction_type" class="grid-picker"
                style="justify-content: flex-start;" onchange="changeAuctionType(this.value);"
                required>
                <option value=""></option>
                @foreach ($auction_types as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" 
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='{{ $item['icon'] }}' {{isset($auction->get->auction_type) && $auction->get->auction_type == $item['name'] ? 'selected' : ''}}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="form-group auction_length_cover d-none">
        <label class="fw-bold">
            Auction Length:
        </label>
        <div>
            @php
                $auction_lengths = [
                    ['name' => '1 Day', 'class' => 'normal-length'],
                    ['name' => '3 Days', 'class' => 'normal-length'],
                    ['name' => '5 Days', 'class' => 'normal-length'],
                    ['name' => '7 Days', 'class' => 'normal-length'],
                    ['name' => '10 Days', 'class' => 'normal-length'],
                    ['name' => '14 Days', 'class' => 'normal-length'],
                    ['name' => '21 Days', 'class' => 'normal-length'],
                    ['name' => '30 Days', 'class' => 'normal-length'],
                    ['name' => '45 Days', 'class' => 'normal-length'],
                    ['name' => '60 Days', 'class' => 'normal-length'],
                    ['name' => '75 Days', 'class' => 'normal-length'],
                    ['name' => '90 Days', 'class' => 'normal-length'],
                    ['name' => 'No time limit', 'class' => 'traditional-length'],
                ];
            @endphp
            <select name="auction_length" id="auction_length"
                class="auction_length grid-picker" style="justify-content: flex-start;"
                required>
                <option value=""></option>
                @foreach ($auction_lengths as $item)
                    <option value="{{ $item['name'] }}" data-target=""
                        class="card flex-row {{ $item['class'] }}"
                        style="width:calc(33.33% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->auction_length) && $auction->get->auction_length === $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="wizard-step" data-step="5">
    <div class="form-group">
        <label class="fw-bold">Title of Listing:</label>
        <input type="text" class="form-control has-icon" name="titleListing" value="{{old('titleListing', $auction->get->titleListing)}}"
            id="" required data-icon="fa-solid fa-hotel" />
    </div>
</div>
<div class="wizard-step" data-step="6">
    @php
        $property_types = [
            ['name' => 'Residential Property'],
            ['name' => 'Commercial Property'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Property Style:</label>
        <select class="grid-picker" name="property_type" id="property_type"
            onchange="changePropertyType(this.value);" required>
            <option value="">Select</option>
            @foreach ($property_types as $row_pt)
                <option value="{{ $row_pt['name'] }}" class="card flex-column"
                    style="width:calc(24% - 10px);"
                    data-icon='<i class="fa-solid fa-hotel"></i>' {{isset($auction->get->property_type) && $auction->get->property_type === $row_pt['name'] ? 'selected' : '' }} >
                    {{ $row_pt['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <div>
            @php
                $property_items = [
                    ['name' => '1/2 Duplex', 'class' => 'residential-length'],
                    ['name' => '1/3 Triplex', 'class' => 'residential-length'],
                    ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
                    ['name' => 'Apartment', 'class' => 'residential-length'],
                    ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
                    ['name' => 'Condominium', 'class' => 'residential-length'],
                    ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                    ['name' => 'Farm', 'class' => 'residential-length'],
                    ['name' => 'Garage Condo', 'class' => 'residential-length'],
                    ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                    ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                    ['name' => 'Modular Home', 'class' => 'residential-length'],
                    ['name' => 'Single Family Residence', 'class' => 'residential-length'],
                    ['name' => 'Townhouse', 'class' => 'residential-length'],
                    ['name' => 'Villa', 'class' => 'residential-length'],
                    ['name' => 'Unimproved Land', 'class' => 'residential-length'],

                    ['name' => 'Agriculture', 'class' => 'commercial-length'],
                    ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                    ['name' => 'Business', 'class' => 'commercial-length'],
                    ['name' => 'Five or More', 'class' => 'commercial-length'],
                    ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                    ['name' => 'Industrial', 'class' => 'commercial-length'],
                    ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                    ['name' => 'Office', 'class' => 'commercial-length'],
                    ['name' => 'Restaurant', 'class' => 'commercial-length'],
                    ['name' => 'Retail', 'class' => 'commercial-length'],
                    ['name' => 'Warehouse', 'class' => 'commercial-length'],


                    // [
                    //     'name' => 'Five or More (Residential units)',
                    //     'class' => 'income-length',
                    // ],
                    // ['name' => 'Duplex', 'class' => 'income-length'],
                    // ['name' => 'Triplex', 'class' => 'income-length'],
                    // ['name' => 'Quadplex', 'class' => 'income-length'],
                ];
            @endphp
            <select name="property_items[]" id="property_items"
                class="property_items grid-picker" style="justify-content: flex-start;"
                multiple required>
                @foreach ($property_items as $item)
                    <option value="{{ $item['name'] }}" data-target=""
                        class="card flex-row {{ $item['class'] }}"
                        style="width:calc(33.33% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->property_items) && in_array($item['name'], $auction->get->property_items) ? 'selected' : ''}} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="wizard-step" data-step="7">
    <span class="resFields">
        @php
            $lease_prop_res = [
                ['name' => 'Entire Property', 'target' => ''],
                ['name' => 'Single Room', 'target' => '.singleRoomRes'],
            ];
        @endphp
        <div class="form-group">
            <label class="fw-bold">Is the landlord looking to lease their entire property or a single room? 
            </label>
            <select class="grid-picker" name="leaseRoom" id="prop_condition"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($lease_prop_res as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(50% - 10px);" {{isset($auction->get->leaseRoom) && $auction->get->leaseRoom === $item['name'] ? 'selected' : ''}} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group singleRoomRes d-none">
                <div class="form-group input-cover">
                    <span class="commercialFields">
                        <div class="form-group">
                            <label class="fw-bold">Are there any shared amenities, such as
                                conference rooms or parking
                                facilities?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->sharedAmenities) ? $auction->get->sharedAmenities : ''}}"
                                data-icon="fa-regular fa-circle-check" name="sharedAmenities"
                                id="sharedAmenities" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Are there specific hours of operation for
                                the
                                building, and is 24/7
                                access available?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->hoursOfOperation) ? $auction->get->hoursOfOperation : ''}}"
                                data-icon="fa-regular fa-circle-check" name="hoursOfOperation"
                                id="hoursOfOperation" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Are there specific zoning restrictions or
                                permitted uses for the
                                space?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->zoningRestrictions) ? $auction->get->zoningRestrictions : ''}}"
                                data-icon="fa-regular fa-circle-check"
                                name="zoningRestrictions" id="zoningRestrictions" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How are maintenance issues and repairs
                                handled
                                for the commercial
                                space?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->maintenanceHandling) ? $auction->get->maintenanceHandling : ''}}"
                                data-icon="fa-regular fa-circle-check"
                                name="maintenanceHandling" id="maintenanceHandling"
                                required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How are the utilities split?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->utilitiesSplit) ? $auction->get->utilitiesSplit : ''}}"
                                data-icon="fa-regular fa-circle-check" name="utilitiesSplit"
                                id="utilitiesSplit" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How is cleaning and maintenance of common
                                areas
                                managed?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->areasManaged) ? $auction->get->areasManaged : ''}}"
                                data-icon="fa-regular fa-circle-check" name="areasManaged"
                                required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How is the layout of the commercial space
                                configured?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->layoutConfiguration) ? $auction->get->layoutConfiguration : ''}}"
                                data-icon="fa-regular fa-circle-check"
                                name="layoutConfiguration" id="layoutConfiguration"
                                required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How much storage space is available?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->storageSpace) ? $auction->get->storageSpace : ''}}"
                                data-icon="fa-regular fa-circle-check" name="storageSpace"
                                id="storageSpaceAvailable" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">What is the size of the room the landlord
                                intends to lease?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->sizeOfRoom) ? $auction->get->sizeOfRoom : ''}}"
                                data-icon="fa-regular fa-circle-check" name="sizeOfRoom"
                                id="roomSize" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">What types of businesses are neighboring
                                tenants
                                in the building or
                                surrounding area?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->neighboringTenants) ? $auction->get->neighboringTenants : ''}}"
                                data-icon="fa-regular fa-circle-check"
                                name="neighboringTenants" id="neighboringTenants" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Is there a designated reception
                                area?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->designatedReceptionArea) ? $auction->get->designatedReceptionArea : ''}}"
                                data-icon="fa-regular fa-circle-check"
                                name="designatedReceptionArea" id="designatedReceptionArea"
                                required />
                        </div>
                    </span>
                    <span class="resFields">
                        <div class="form-group">
                            <label class="fw-bold">Are tenants allowed to have guests, and if
                                so,
                                are there any
                                restrictions?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->tenantsGuests) ? $auction->get->tenantsGuests : ''}}"
                                data-icon="fa-regular fa-circle-check" name="tenantsGuests"
                                id="custom_property_condition" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Can tenants use common areas like the
                                kitchen,
                                living room, or
                                backyard?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->commonAreas) ? $auction->get->commonAreas : ''}}"
                                data-icon="fa-regular fa-circle-check" name="commonAreas"
                                id="custom_property_condition" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How are maintenance issues handled?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->maintenanceIssues) ? $auction->get->maintenanceIssues : ''}}"
                                data-icon="fa-regular fa-circle-check"
                                name="maintenanceIssues" id="custom_property_condition"
                                required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How are the utilities split?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->utilitiesSplit) ? $auction->get->utilitiesSplit : ''}}"
                                data-icon="fa-regular fa-circle-check" name="utilitiesSplit"
                                id="custom_property_condition" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How is cleaning and maintenance of common
                                areas
                                managed?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->areasManaged) ? $auction->get->areasManaged : ''}}"
                                data-icon="fa-regular fa-circle-check" name="areasManaged"
                                id="custom_property_condition" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold"> How much storage space is
                                available?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->storageSpace) ? $auction->get->storageSpace : ''}}"
                                data-icon="fa-regular fa-circle-check" name="storageSpace"
                                id="custom_property_condition" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold"> Is there a private bathroom, or is it
                                shared?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->privateBathroom) ? $auction->get->privateBathroom : ''}}"
                                data-icon="fa-regular fa-circle-check" name="privateBathroom"
                                id="custom_property_condition" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold"> What is the size of the room the landlord
                                intends to lease?</label>
                            <input type="text" class="form-control has-icon" value="{{isset($auction->get->sizeOfRoom) ? $auction->get->sizeOfRoom : ''}}"
                                data-icon="fa-regular fa-circle-check" name="sizeOfRoom"
                                id="custom_property_condition" required />
                        </div>
                    </span>
                </div>
            </div>
        </div>
    </span>
    <span class="commercialFields">
        @php
            $lease_prop_commercial = [
                ['name' => 'Entire Property', 'target' => ''],
                ['name' => 'Single Room', 'target' => '.singleRoomCommercial'],
            ];
        @endphp
        <div class="form-group">
            <label class="fw-bold">Is the landlord looking to lease their entire property or a
                single room?
            </label>
            <select class="grid-picker" name="leaseRoom" id="prop_condition"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($lease_prop_commercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(50% - 10px);" {{isset($auction->get->leaseRoom) && $auction->get->leaseRoom === $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group singleRoomCommercial d-none">
                <span class="commercialFields">
                    <div class="form-group">
                        <label class="fw-bold">Are there any shared amenities, such as
                            conference rooms or parking
                            facilities?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->sharedAmenities) ? $auction->get->sharedAmenities : ''}}"
                            data-icon="fa-regular fa-circle-check" name="sharedAmenities"
                            id="sharedAmenities" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">Are there specific hours of operation for
                            the
                            building, and is 24/7
                            access available?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->hoursOfOperation) ? $auction->get->hoursOfOperation : ''}}"
                            data-icon="fa-regular fa-circle-check" name="hoursOfOperation"
                            id="hoursOfOperation" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">Are there specific zoning restrictions or
                            permitted uses for the
                            space?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->zoningRestrictions) ? $auction->get->zoningRestrictions : ''}}"
                            data-icon="fa-regular fa-circle-check" name="zoningRestrictions"
                            id="zoningRestrictions" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">How are maintenance issues and repairs
                            handled
                            for the commercial
                            space?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->maintenanceHandling) ? $auction->get->maintenanceHandling : ''}}"
                            data-icon="fa-regular fa-circle-check" name="maintenanceHandling"
                            id="maintenanceHandling" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">How are the utilities split?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->utilitiesSplit) ? $auction->get->utilitiesSplit : ''}}"
                            data-icon="fa-regular fa-circle-check" name="utilitiesSplit"
                            id="utilitiesSplit" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">How is cleaning and maintenance of common
                            areas
                            managed?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->areasManaged) ? $auction->get->areasManaged : ''}}"
                            data-icon="fa-regular fa-circle-check" name="areasManaged"
                            required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">How is the layout of the commercial space
                            configured?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->layoutConfiguration) ? $auction->get->layoutConfiguration : ''}}"
                            data-icon="fa-regular fa-circle-check" name="layoutConfiguration"
                            id="layoutConfiguration" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">How much storage space is available?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->storageSpace) ? $auction->get->storageSpace : ''}}"
                            data-icon="fa-regular fa-circle-check" name="storageSpace"
                            id="storageSpaceAvailable" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">What is the size of the room the landlord
                            intends to lease?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->sizeOfRoom) ? $auction->get->sizeOfRoom : ''}}"
                            data-icon="fa-regular fa-circle-check" name="sizeOfRoom"
                            id="roomSize" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">What types of businesses are neighboring
                            tenants
                            in the building or
                            surrounding area?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->neighboringTenants) ? $auction->get->neighboringTenants : ''}}"
                            data-icon="fa-regular fa-circle-check" name="neighboringTenants"
                            id="neighboringTenants" required />
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">Is there a designated reception
                            area?</label>
                        <input type="text" class="form-control has-icon" value="{{isset($auction->get->designatedReceptionArea) ? $auction->get->designatedReceptionArea : ''}}"
                            data-icon="fa-regular fa-circle-check"
                            name="designatedReceptionArea" id="designatedReceptionArea"
                            required />
                    </div>
                </span>
            </div>
        </div>
    </span>
</div>
<div class="wizard-step" data-step="8">
    @php
        $prop_conditions = [
            ['name' => 'New Construction', 'target' => ''],
            ['name' => 'Completely Updated: No updates needed.', 'target' => ''],
            ['name' => 'Semi-updated: Needs minor updates.', 'target' => ''],
            ['name' => 'Not Updated: Requires a complete update.', 'target' => ''],
            ['name' => 'Other', 'target' => '.custom_property_condition'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Property Condition: </label>
        <select class="grid-picker" name="prop_condition" id="prop_condition"
            style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($prop_conditions as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row"
                    data-icon='<i class="fa-regular fa-circle-check"></i>'
                    style="width:calc(50% - 10px);" {{isset($auction->get->prop_condition) && $auction->get->prop_condition === $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group custom_property_condition d-none">
        <label class="fw-bold">Property Condition: </label>
        <input type="text" class="form-control has-icon" name="custom_property_condition" value="{{isset($auction->get->custom_property_condition) ? $auction->get->custom_property_condition : ''}}"
            data-icon="fa-regular fa-circle-check" id="custom_property_condition" required />
    </div>
</div>
<div class="wizard-step bedroom" data-step="9">
    <span class="resFields">
        @php
            $bedrooms = [
                ['name' => '1', 'target' => ''],
                ['name' => '2', 'target' => ''],
                ['name' => '3', 'target' => ''],
                ['name' => '4', 'target' => ''],
                ['name' => '5', 'target' => ''],
                ['name' => '6', 'target' => ''],
                ['name' => '7', 'target' => ''],
                ['name' => '8', 'target' => ''],
                ['name' => '9', 'target' => ''],
                ['name' => '10', 'target' => ''],
                ['name' => 'Other', 'target' => '.custom_bedrooms_commerical'],
            ];
        @endphp
        <div class="form-group">
            <label class="fw-bold">Bedrooms:</label>
            <select class="grid-picker" name="bedrooms" id="bedrooms"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($bedrooms as $bedroom)
                    <option value="{{ $bedroom['name'] }}"
                        data-target="{{ $bedroom['target'] }}" class="card flex-column"
                        style="width:calc(10% - 10px);"
                        data-icon='<i class="fa-solid fa-bed"></i>' {{isset($auction->get->bedrooms) && $auction->get->bedrooms === $bedroom['name'] ? 'selected' : '' }} >
                        {{ $bedroom['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group custom_bedrooms_commerical d-none">
                <label class="fw-bold">Bedrooms:</label>
                <input type="number" class="form-control has-icon" name="custom_bedrooms" value="{{isset($auction->get->custom_bedrooms) ? $auction->get->custom_bedrooms : ''}}"
                    data-icon="fa-solid fa-bed" id="custom_bedrooms" required />
            </div>
        </div>
    </span>
</div>
<div class="wizard-step bedroom" data-step="10">
    <div class="form-group">
        @php
            $bathrooms = [
                ['name' => '1', 'target' => ''],
                ['name' => '1.5', 'target' => ''],
                ['name' => '2', 'target' => ''],
                ['name' => '2.5', 'target' => ''],
                ['name' => '3', 'target' => ''],
                ['name' => '3.5', 'target' => ''],
                ['name' => '4', 'target' => ''],
                ['name' => '4.5', 'target' => ''],
                ['name' => '5', 'target' => ''],
                ['name' => '6', 'target' => ''],
                ['name' => '7', 'target' => ''],
                ['name' => '8', 'target' => ''],
                ['name' => '9', 'target' => ''],
                ['name' => '10', 'target' => ''],
                ['name' => 'Other', 'target' => '.custom_bathrooms'],
            ];
        @endphp
        <label class="fw-bold">Bathrooms: </label>
        <select class="grid-picker" name="bathrooms" id="bathrooms"
            style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($bathrooms as $bathroom)
                <option value="{{ $bathroom['name'] }}"
                    data-target="{{ $bathroom['target'] }}" class="card flex-column"
                    style="width:calc(10% - 10px);"
                    data-icon='<i class="fa-solid fa-bath"></i>' {{isset($auction->get->bathrooms) && $auction->get->bathrooms === $bathroom['name'] ? 'selected' : '' }} >
                    {{ $bathroom['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group custom_bathrooms d-none">
            <label class="fw-bold">Bathrooms:</label>
            <input type="number" class="form-control has-icon" name="custom_bathrooms" value="{{isset($auction->get->custom_bathrooms) ? $auction->get->custom_bathrooms : ''}}"
                data-icon="fa-solid fa-bath" id="custom_bathrooms" required />
        </div>
    </div>
</div>