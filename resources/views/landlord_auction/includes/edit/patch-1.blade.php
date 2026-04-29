<div class="wizard-step" data-step="1">
    <h4 class="title">Please provide the property's complete address, along with the city, county, and
        state, pertaining to the real estate asset that the landlord intends to place on the market:
    </h4>
    <div class="form-group">
        <label for="address" class="fw-bold">Address:</label>
        <input type="text" name="address" data-type="address" placeholder="" value="{{ $auction->address }}"
            id="address" class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot" required>
    </div>
    <div class="form-group">
        <label class="fw-bold" for="unit_number">Unit Number:</label>
        <input type="text" name="unit_number" data-type="unit_number" value="{{ $auction->get->unit_number }}"
            placeholder="" id="unit_number" class="form-control has-icon search_places"
            data-icon="fa-solid fa-location-dot" required>
    </div>
    <div class="form-group">
        <label class="fw-bold">County:</label>
        <input type="text" name="county" data-type="counties" id="county" value="{{ $auction->county }}"
            class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city" placeholder="" required>
    </div>
    
    {{-- <div class="form-group">
        <label class="fw-bold">City:</label>
        <input type="text" name="city" data-type="cities" id="cities" value="{{ $auction->city }}"
            class="form-control has-icon search_places" data-icon="fa-solid fa-city" placeholder="" required>
    </div>
    <div class="form-group">
        <label class="fw-bold">State:</label>
        <input type="text" name="state" data-type="states" id="state" value="{{ $auction->state }}"
            class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa" placeholder="" required>
    </div> --}}
</div>
<div class="wizard-step" data-step="2">
    <?php
    $listingDateTime = $auction->listing_date;
    $listingDate = (new DateTime($listingDateTime))->format('Y-m-d');
    
    $expirationDateTime = $auction->expiration_date; // Example date from database
    $expirationDate = (new DateTime($expirationDateTime))->format('Y-m-d');
    ?>
    <div class="form-group">
        <label for="address" class="fw-bold">Listing Date:</label>
        <input type="date" name="listing_date" id="listing_date" class="form-control has-icon"
            value="{{ $listingDate }}" data-icon="fa-regular fa-calendar-days" required>
    </div>
    <div class="form-group">
        <label for="address" class="fw-bold">Expiration Date:</label>
        <input type="date" name="expiration_date" id="expiration_date" value="{{ $expirationDate }}"
            class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
    </div>
</div>
<div class="wizard-step" data-step="3">
    {{-- 19 June 2023 for Residential --}}
    {{-- @php
        $serviceTypeRes = [
            ['name' => 'Full Service', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
            [
                'name' => 'Limited Service',
                'target' => '.limitedServiceRes',
                'icon' => 'fa-regular fa-circle-check',
            ],
        ];
    @endphp
    <div class="form-group" id="pool">
        <label class="fw-bold">Listing Service Type:</label>
        <select class="grid-picker" name="listing_service_type" id="listing_service_type"
            style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($serviceTypeRes as $item)
                <option value="{{ $item['name'] }}"
                    {{ $auction->get->listing_service_type == $item['name'] ? 'selected' : '' }}
                    data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div> --}}
    <div class="form-group">
        @php
            $representationRes = [
                [
                    'name' => 'Landlord Represented',
                    'target' => '',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                [
                    'name' => 'Landlord Not Represented',
                    'target' => '',
                    'icon' => 'fa-regular fa-circle-check',
                ],
            ];
        @endphp
        <label class="fw-bold">Representation: </label>
        <select class="grid-picker" name="representation" id="listing_service_type" style="justify-content: flex-start;"
            required>
            <option value="">Select</option>
            @foreach ($representationRes as $item)
                <option value="{{ $item['name'] }}"
                    {{ $auction->get->representation == $item['name'] ? 'selected' : '' }}
                    data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step="4">
    <div class="form-group">
        <label class="fw-bold">Listing Type:</label>
        <div>
            @php
                $auction_types = [
                    [
                        'name' => 'Auction Listing',
                        'icon' => '<i class="fa-regular fa-clock"></i>',
                        'target' => '.auctionTimer',
                    ],
                    [
                        'name' => 'Traditional Listing',
                        'icon' => '<i class="fa-solid fa-clipboard-list"></i>',
                        'target' => '',
                    ],
                ];
            @endphp
            <select name="auction_type" id="auction_type" class="grid-picker" style="justify-content: flex-start;"
                onchange="changeAuctionType(this.value);" required>
                <option value=""></option>
                @foreach ($auction_types as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        {{ $auction->get->auction_type == $item['name'] ? 'selected' : '' }} class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group auctionTimer d-none">
        <label class="fw-bold">Timer Length:</label>
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
            <select name="auction_length" id="auction_length" class="auction_length grid-picker"
                style="justify-content: flex-start;" required>
                <option value=""></option>
                @foreach ($auction_lengths as $item)
                    <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }} {{ $auction->get->auction_length == $item['name'] ? 'selected' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

</div>
<div class="wizard-step" data-step="5">
    @php
        $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Property Style: </label>
        <select class="grid-picker" name="property_type" id="property_type"
            onchange="changePropertyType(this.value);" required>
            <option value="">Select</option>
            @foreach ($property_types as $item)
                <option value="{{ $item['name'] }}" class="card flex-column" style="width:calc(24% - 10px);"
                    data-icon='<i class="fa-solid fa-hotel"></i>'
                    {{ $item['name'] == $auction->get->property_type ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <div>
            @php
                $property_items = [
                    ['name' => '1/3 Triplex', 'class' => 'residential-length'],
                    ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
                    ['name' => '½ Duplex', 'class' => 'residential-length'],
                    ['name' => 'Apartment', 'class' => 'residential-length'],
                    ['name' => 'Condominium', 'class' => 'residential-length'],
                    ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
                    ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                    ['name' => 'Farm', 'class' => 'residential-length'],
                    ['name' => 'Garage Condo', 'class' => 'residential-length'],
                    ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                    ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                    ['name' => 'Modular Home', 'class' => 'residential-length'],
                    ['name' => 'Single Family Residence', 'class' => 'residential-length'],
                    ['name' => 'Townhouse', 'class' => 'residential-length'],
                    ['name' => 'Unimproved Land', 'class' => 'residential-length'],
                    ['name' => 'Villa', 'class' => 'residential-length'],

                    ['name' => 'Duplex', 'class' => 'income-length'],
                    ['name' => 'Triplex', 'class' => 'income-length'],
                    ['name' => 'Quadplex', 'class' => 'income-length'],
                    ['name' => 'Five or More', 'class' => 'income-length'],
                    ['name' => 'Agriculture', 'class' => 'commercial-length'],
                    ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                    ['name' => 'Business', 'class' => 'commercial-length'],
                    // Changing nisar
                    ['name' => 'Five or More', 'class' => 'commercial-length'],
                    ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                    ['name' => 'Industrial', 'class' => 'commercial-length'],
                    ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                    ['name' => 'Office', 'class' => 'commercial-length'],
                    ['name' => 'Restaurant', 'class' => 'commercial-length'],
                    ['name' => 'Retail', 'class' => 'commercial-length'],
                    ['name' => 'Warehouse', 'class' => 'commercial-length'],
                ];
            @endphp
            <select name="property_items[]" id="property_items" class="property_items grid-picker"
                style="justify-content: flex-start;" multiple required>
                <option value=""></option>
                @foreach ($property_items as $item)
                    <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}"
                        style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        {{ in_array($item['name'], json_decode($auction->get->property_items) ?? []) ? 'selected' : ''  }}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="wizard-step" data-step="6">
    <div class="form-group ">
        <label class="fw-bold">Leasing Space:</label>
        @php
            $leasePropOptions = [
                ['name' => 'Entire Property', 'target' => ''],
                ['name' => 'Single Room', 'target' => '.singleRoomRes'],
            ];
        @endphp
        <select name="leasePropOption" id="auction_length" class="auction_length grid-picker"
            style="justify-content: flex-start;" required>
            <option value=""></option>
            @foreach ($leasePropOptions as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'
                    {{ $item['name'] == $auction->get->leasePropOption ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        @php
            $singleRoom = json_decode($auction->get->singleRoom);
        @endphp
        <span class="resFields">
            <div class="form-group singleRoomRes d-none">
                <label class="fw-bold">Are tenants allowed to have guests, and if so, are there any
                    restrictions?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-regular fa-circle-check">
                <label class="fw-bold">Can tenants use common areas like the kitchen, living room, or
                    backyard?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-regular fa-circle-check">
                <label class="fw-bold">How are maintenance issues handled?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-regular fa-circle-check">
                <label class="fw-bold">How are the utilities split?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-regular fa-circle-check">
                <label class="fw-bold">How is cleaning and maintenance of common areas managed?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-regular fa-circle-check">
                <label class="fw-bold">How much storage space is available?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-regular fa-circle-check">
                <label class="fw-bold">Is there a private bathroom, or is it shared?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-regular fa-circle-check">
                <label class="fw-bold">What is the size of the room the landlord intends to lease?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-regular fa-circle-check">
            </div>
        </span>
        <span class="commercialFields">
            <div class="form-group singleRoomRes d-none">
                <label class="fw-bold">Are there any shared amenities, such as conference rooms or parking
                    facilities?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">Are there specific hours of operation for the building, and is 24/7 access
                    available?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">Are there specific zoning restrictions or permitted uses for the space?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">How are maintenance issues and repairs handled for the commercial space?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">How are the utilities split?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">How is cleaning and maintenance of common areas managed?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">How is the layout of the commercial space configured?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">How much storage space is available?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">What is the size of the room the landlord intends to lease?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">What types of businesses are neighboring tenants in the building or surrounding
                    area?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">

                <label class="fw-bold">Is there a designated reception area?</label>
                <input class="form-control has-icon" type="text" name="singleRoom[]"
                    data-icon="fa-solid fa-question">
            </div>
        </span>
    </div>
</div>
<div class="wizard-step" data-step="7">
    <div class="form-group">
        @php
            $propConditions = [
                ['name' => 'New Construction', 'target' => ''],
                ['name' => 'Completely Updated: No updates needed', 'target' => ''],
                ['name' => 'Semi-Updated: Needs minor updates', 'target' => ''],
                ['name' => 'Not Updated: Requires a complete update', 'target' => ''],
                ['name' => 'Other', 'target' => '.propOtherRes'],
            ];
        @endphp
        <label class="fw-bold">Property Condition: </label>
        <select class="grid-picker" name="propConditions" id="housing_for_older_persons"
            style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($propConditions as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'
                    {{ $item['name'] == $auction->get->propConditions ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group propOtherRes d-none">
            <label class="fw-bold" for="">Property Condition: </label>
            <input type="text" name="propOther" id="" placeholder="" class="form-control has-icon"
                data-icon="fa-regular fa-circle-check" value="{{ $auction->get->propOther }}" required>
        </div>
    </div>
</div>
<div class="wizard-step" data-step="8">
    <span class="timerAuction">
        <div class="form-group">
            <label class="fw-bold" for="custom_terms">Rent Now Price:</label>
            <input type="number" name="rentNow" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                value="{{ $auction->get->rentNow }}" required>
        </div>
        <div class="form-group">
            <label class="fw-bold" for="custom_terms">Rent Now Price Per Sqft:</label>
            <input type="number" name="rentNowSqft" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" value="{{ $auction->get->rentNowSqft }}" required>
        </div>
        <div class="form-group">
            <label class="fw-bold" for="custom_terms">Starting Price:</label>
            <input type="number" name="startingPrice" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" value="{{ $auction->get->startingPrice }}" required>
        </div>
        <div class="form-group">
            <label class="fw-bold" for="custom_terms">Reserve Price:</label>
            <input type="number" name="reservePrice" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" value="{{ $auction->get->reservePrice }}" required>
        </div>
    </span>
    <span class="traditional">
        <div class="form-group">
            <label class="fw-bold" for="custom_terms">Price:</label>
            <input type="number" name="price" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                value="{{ $auction->get->price }}" required>
        </div>
        <div class="form-group">
            <label class="fw-bold" for="custom_terms">List Price Per Sqft:</label>
            <input type="number" name="list_price_per_sq" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" value="{{ $auction->get->list_price_per_sq }}" required>
        </div>
    </span>
    <div class="form-group">
        <?php
        $storedDateTime = $auction->get->leaseDate;
        $leaseDate = (new DateTime($storedDateTime))->format('Y-m-d');
        ?>
        <label class="fw-bold" for="custom_terms">Lease Availability Date:</label>
        <input type="date" name="leaseDate" class="form-control has-icon" data-icon="fa-regular fa-calendar-days"
            required value="{{ isset($leaseDate) ? $leaseDate : '' }}">

    </div>
    <div class="form-group">
        @php
            $leaseTime = [
                ['name' => '3 Months', 'target' => ''],
                ['name' => '6 Months', 'target' => ''],
                ['name' => '9 Months', 'target' => ''],
                ['name' => '1 Year', 'target' => ''],
                ['name' => '2 Years', 'target' => ''],
                ['name' => '3-5 Years', 'target' => ''],
                ['name' => '5+ Years', 'target' => ''],
                ['name' => 'Month to Month', 'target' => ''],
                ['name' => 'Other', 'target' => '.otherLeaseDuration'],
            ];
        @endphp
        <label class="fw-bold">Acceptable Lease Duration: </label>
        <select class="grid-picker" name="leaseTime[]" id="leaseTermRes" style="justify-content: flex-start;"
            required multiple>
            <option value="">Select</option>
            @foreach ($leaseTime as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-calendar-days"></i>'
                    {{ isset($auction->get->leasetime) && in_array($item['name'], json_decode($auction->get->leasetime) ?? []) ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherLeaseDuration d-none">
            <div class="form-group">
                <label class="fw-bold">Acceptable Lease Duration:</label>
                <input type="text" name="other_lease_duration" id="other_lease_duration"
                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days"
                    value="{{ $auction->get->other_lease_duration }}">
            </div>
        </div>
    </div>
    <span class="commercialFields">
        <div class="form-group">
            @php
                $leaseTerms = [
                    ['name' => 'Absolute (Triple) Net', 'target' => ''],
                    ['name' => 'Gross Lease', 'target' => ''],
                    ['name' => 'Gross Percentages', 'target' => ''],
                    ['name' => 'Ground Lease', 'target' => ''],
                    ['name' => 'Lease Option', 'target' => ''],
                    ['name' => 'Modified Gross', 'target' => ''],
                    ['name' => 'Net Lease', 'target' => ''],
                    ['name' => 'Net Net', 'target' => ''],
                    ['name' => 'Pass Throughs', 'target' => ''],
                    ['name' => 'Purchase Option', 'target' => ''],
                    ['name' => 'Renewal Option', 'target' => ''],
                    ['name' => 'Sale-Leaseback', 'target' => ''],
                    ['name' => 'Seasonal', 'target' => ''],
                    ['name' => 'Special Available (CLO)', 'target' => ''],
                    ['name' => 'Varied Terms', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherTermsLease'],
                ];
            @endphp
            <label class="fw-bold"> Terms of Lease: </label>
            <select class="grid-picker" name="leaseTerms[]" id="leaseTermRes" style="justify-content: flex-start;"
                required multiple>
                <option value="">Select</option>
                @foreach ($leaseTerms as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-calendar-days"></i>'
                        {{ isset($auction->get->leaseTerms) && in_array($item['name'], json_decode($auction->get->leaseTerms) ?? []) ? 'selected' : '' }}>

                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group otherTermsLease d-none">
                <div class="form-group">
                    <label class="fw-bold">Terms of Lease:</label>
                    <input type="text" name="other_lease_terms" id="other_lease_terms"
                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                        value="{{ isset($auction->get->other_lease_terms) ? $auction->get->other_lease_terms : '' }}">
                </div>
            </div>
        </div>
        <div class="form-group">
            @php
                $frequencyRes = [['name' => 'Annually', 'target' => ''], ['name' => 'Monthly', 'target' => '']];
            @endphp
            <label class="fw-bold">Select the frequency in which the Lease Amount is paid: </label>
            <select class="grid-picker" name="frequency[]" style="justify-content: flex-start;" required multiple>
                <option value="">Select</option>
                @foreach ($frequencyRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-calendar-days"></i>'
                        {{ in_array($item['name'], json_decode($auction->get->frequency) ?? []) ? 'selected' : '' }}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            @php
                $tenant_pays = [
                    ['name' => 'Association Fees', 'target' => ''],
                    ['name' => 'Capital Expenses', 'target' => ''],
                    ['name' => 'Common Area Maintenance', 'target' => ''],
                    ['name' => 'Condominium Fees', 'target' => ''],
                    ['name' => 'Electricity', 'target' => ''],
                    ['name' => 'Gas', 'target' => ''],
                    ['name' => 'Liability Insurance', 'target' => ''],
                    ['name' => 'Parking Fee', 'target' => ''],
                    ['name' => 'Property Insurance', 'target' => ''],
                    ['name' => 'Property Taxes', 'target' => ''],
                    ['name' => 'Pro-Rated', 'target' => ''],
                    ['name' => 'Reserves', 'target' => ''],
                    ['name' => 'Sewer', 'target' => ''],
                    ['name' => 'Trash Collection', 'target' => ''],
                    ['name' => 'Water', 'target' => ''],
                    // ['name' => 'Telephone', 'target' => ''],
                    // ['name' => 'Trash Collection', 'target' => ''],
                    // ['name' => 'Water', 'target' => ''],
                    // ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.tenantPaysOther'],
                ];
            @endphp
            <label class="fw-bold">Tenant Pays:</label>
            <select class="grid-picker" name="tenant_pays[]" multiple id="tenant_pays"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($tenant_pays as $tenant_pay)
                    <option value="{{ $tenant_pay['name'] }}" data-target="{{ $tenant_pay['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                        {{ isset($auction->get->tenant_pays) && in_array($item['name'], json_decode($auction->get->tenant_pays) ?? []) ? 'selected' : '' }}>

                        {{ $tenant_pay['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group tenantPaysOther d-none">
                <label class="fw-bold">Tenant Pays:</label>
                <input type="text" name="tenantPaysOther" class="form-control has-icon"
                    data-icon="fa-regular fa-circle-check"
                    value="{{ isset($auction->get->tenantPaysOther) ? $auction->get->tenantPaysOther : '' }}">
            </div>
        </div>
        <div class="form-group">
            @php
                $landlordPays = [
                    ['name' => 'Cable TV', 'target' => ''],
                    ['name' => 'Electricity', 'target' => ''],
                    ['name' => 'Gas', 'target' => ''],
                    ['name' => 'Grounds Care', 'target' => ''],
                    ['name' => 'Insurance', 'target' => ''],
                    ['name' => 'Internet', 'target' => ''],
                    ['name' => 'Laundry', 'target' => ''],
                    ['name' => 'Management', 'target' => ''],
                    ['name' => 'Pest Control', 'target' => ''],
                    ['name' => 'Pool Maintenance', 'target' => ''],
                    ['name' => 'Recreational', 'target' => ''],
                    ['name' => 'Repairs', 'target' => ''],
                    ['name' => 'Security', 'target' => ''],
                    ['name' => 'Sewer', 'target' => ''],
                    ['name' => 'Taxes', 'target' => ''],
                    ['name' => 'Telephone', 'target' => ''],
                    ['name' => 'Trash Collection', 'target' => ''],
                    ['name' => 'Water', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.landlordPaysOther'],
                ];
            @endphp
            <label class="fw-bold">Landlord Pays:</label>
            <select class="grid-picker" name="ownerPays[]" multiple style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($landlordPays as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        {{ isset($auction->get->frequency) && in_array($item['name'], json_decode($auction->get->frequency) ?? []) ? 'selected' : '' }}>

                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group landlordPaysOther d-none">
                <label class="fw-bold">Landlord Pays:</label>
                <input type="text" name="landlordPaysOther" id="owner_pays" class="form-control has-icon"
                    data-icon="fa-regular fa-circle-check"
                    value="{{ isset($auction->get->landlordPaysOther) ? $auction->get->landlordPaysOther : '' }}">
            </div>
        </div>
    </span>
    <span class="resFields">
        <div class="form-group">
            @php
                $frequencyCommercial = [
                    ['name' => 'Annually', 'target' => ''],
                    ['name' => 'Daily', 'target' => ''],
                    ['name' => 'Monthly', 'target' => ''],
                    ['name' => 'Seasonally', 'target' => '.season_runs'],
                    ['name' => 'Weekly', 'target' => ''],
                ];
            @endphp
            <label class="fw-bold">Select the frequency in which the Lease Amount is paid: </label>
            <select class="grid-picker" name="frequency[]" style="justify-content: flex-start;" required multiple>
                <option value="">Select</option>
                @foreach ($frequencyCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-calendar-days"></i>'
                        {{ in_array($item['name'], json_decode($auction->get->frequency) ?? []) ? 'selected' : '' }}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="season_runs d-none">
            <div class="form-group">
                @php
                    $seasonFrom = (new DateTime($auction->get->season_runs_from))->format('Y-m-d');
                    $seasonTo = (new DateTime($auction->get->season_runs_to))->format('Y-m-d');
                @endphp
                <label class="fw-bold">Season runs from:</label>
                <input type="date" name="season_runs_from" id="season_runs_from" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days" min="{{ date('Y-m-d') }}" value="{{isset($seasonFrom) ? $seasonFrom : ''}}" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Season runs to:</label>
                <input type="date" name="season_runs_to" id="season_runs_to" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days" min="{{ date('Y-m-d') }}" value="{{isset($seasonTo) ? $seasonTo : ''}}" required>
            </div>
        </div>
    </span>
    <span class="resFields">
        <div class="form-group">
            @php
                $rentRes = [
                    ['name' => 'Cable TV', 'target' => ''],
                    ['name' => 'Electricity', 'target' => ''],
                    ['name' => 'Gas', 'target' => ''],
                    ['name' => 'Grounds Care', 'target' => ''],
                    ['name' => 'Insurance', 'target' => ''],
                    ['name' => 'Internet', 'target' => ''],
                    ['name' => 'Laundry', 'target' => ''],
                    ['name' => 'Management', 'target' => ''],
                    ['name' => 'Pest Control', 'target' => ''],
                    ['name' => 'Pool Maintenance', 'target' => ''],
                    ['name' => 'Recreational', 'target' => ''],
                    ['name' => 'Repairs', 'target' => ''],
                    ['name' => 'Security', 'target' => ''],
                    ['name' => 'Sewer', 'target' => ''],
                    ['name' => 'Taxes', 'target' => ''],
                    ['name' => 'Telephone', 'target' => ''],
                    ['name' => 'Trash Collection', 'target' => ''],
                    ['name' => 'Water', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.rentOtherRes'],
                ];
            @endphp
            <label class="fw-bold">Rent Includes:</label>
            <select class="grid-picker" name="rent[]" multiple id="tenant_pays"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($rentRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        {{ isset($auction->get->rent) && in_array($item['name'], json_decode($auction->get->rent) ?? []) ? 'selected' : '' }}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group rentOtherRes d-none">
                <label class="fw-bold">Rent Includes:</label>
                <input type="text" name="rentOther" class="form-control has-icon"
                    data-icon="fa-regular fa-circle-check"
                    value="{{ isset($auction->get->rentOther) ? $auction->get->rentOther : '' }}">

            </div>
        </div>
    </span>
    <div class="form-group">
        @php
           $leaseTermRes = [
                ['name' => 'First, Last, and Security', 'target' => '.depositOne'],
                [
                    'name' => 'First, Last, Security Deposit, Exit Cleaning Fee, & Application Fee',
                    'target' => '.depositSecond',
                ],
                [
                    'name' => 'First, Last, Security Deposit, Pet Deposit, Exit Cleaning Fee, & Application Fee',
                    'target' => '.depositThird',
                ],
                [
                    'name' => 'First, Last, Security Deposit, Exit Cleaning Fee, Application Fee, Vacation Tax',
                    'target' => '.depositFour',
                ],
                [
                    'name' => 'First, Security Deposit, Exit Cleaning Fee, Application Fee, & Vacation Tax',
                    'target' => '.depositFive',
                ],
                [
                    'name' => 'First, Security, Exit Cleaning Fee & Application Fee',
                    'target' => '.depositSix',
                ],
                ['name' => 'First, Security, & Application Fee', 'target' => '.depositSeven'],
                ['name' => 'Other', 'target' => '.custom_input'],
            ];
        @endphp
    </div>
    <div class="form-group ">
        <label class="fw-bold">What is required at move-in?</label>
        <select class="grid-picker" name="required_at_move_in" id="" style="justify-content: flex-start;"
            required>
            <option value="">Select</option>
            @foreach ($leaseTermRes as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'
                    {{ isset($auction->get->required_at_move_in) && $item['name'] == $auction->get->required_at_move_in ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="custom_input d-none">
        <div class="form-group">
            <label class="fw-bold">What is required at move-in?</label>
            <input type="text" name="required_move_in" class="form-control has-icon"
                data-icon="fa-regular fa-circle-check"
                value="{{ isset($auction->get->required_move_in) ? $auction->get->required_move_in : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Please enter the required move-in amounts:</label>
            <input type="number" name="required_move_in_amount" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign"
                value="{{ isset($auction->get->required_move_in_amount) ? $auction->get->required_move_in_amount : '' }}">

        </div>
    </div>
    <div class="form-group depositOne d-none">
        <label class="fw-bold">Please enter the required move-in amounts:</label>
        <div class="form-group">
            <label class="fw-bold">First Month:</label>
            <input type="number" name="firstMonthDeposit" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->firstMonthDeposit) ? $auction->get->firstMonthDeposit : '' }}">

        </div>
        <div class="form-group">
            <label class="fw-bold">Last Month:</label>
            <input type="number" name="lastMonthDeposit" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->lastMonthDeposit) ? $auction->get->lastMonthDeposit : '' }}">

        </div>
        <div class="form-group">
            <label class="fw-bold">Security Deposit:</label>
            <input type="number" name="securityDeposit" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->securityDeposit) ? $auction->get->securityDeposit : '' }}">
        </div>
    </div>
    <div class="form-group depositSecond d-none">
        <label class="fw-bold">Please enter the required move-in amounts:</label>
        <div class="form-group">
            <label class="fw-bold">First Month:</label>
            <input type="number" name="firstMonthSecond" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->firstMonthSecond) ? $auction->get->firstMonthSecond : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Last Month:</label>
            <input type="number" name="lastMonthSecond" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->lastMonthSecond) ? $auction->get->lastMonthSecond : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Security Deposit:</label>
            <input type="number" name="securityDepositSecond" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->securityDepositSecond) ? $auction->get->securityDepositSecond : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Exit Cleaning Fee:</label>
            <input type="number" name="exitCleaningFeeSecond" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->exitCleaningFeeSecond) ? $auction->get->exitCleaningFeeSecond : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Fee:</label>
            <input type="number" name="applicationFeeSecond" data-type="cities" id="cities"
                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->applicationFeeSecond) ? $auction->get->applicationFeeSecond : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Link: </label>
            <input type="number" name="applicationLinkSecond" data-type="cities" id="cities"
                class="form-control has-icon search_places" data-icon="fa-solid fa-link" placeholder="" required
                value="{{ isset($auction->get->applicationLinkSecond) ? $auction->get->applicationLinkSecond : '' }}">
        </div>
    </div>
    <div class="form-group depositThird d-none">
        <label class="fw-bold">Please enter the required move-in amounts:</label>
        <div class="form-group">
            <label class="fw-bold">First Month:</label>
            <input type="number" name="firstMonthThird" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->firstMonthThird) ? $auction->get->firstMonthThird : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Last Month:</label>
            <input type="number" name="lastMonthThird" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->lastMonthThird) ? $auction->get->lastMonthThird : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Security Deposit:</label>
            <input type="number" name="securityDepositThird" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->securityDepositThird) ? $auction->get->securityDepositThird : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Pet Deposit:</label>
            <input type="number" name="petDepositThird" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->petDepositThird) ? $auction->get->petDepositThird : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Exit Cleaning Fee:</label>
            <input type="number" name="exitCleaningFeeThird" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->exitCleaningFeeThird) ? $auction->get->exitCleaningFeeThird : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Fee:</label>
            <input type="number" name="applicationFeeThird" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->applicationFeeThird) ? $auction->get->applicationFeeThird : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Link:</label>
            <input type="text" name="applicationLinkThird" class="form-control has-icon "
                data-icon="fa-solid fa-link" placeholder="" required
                value="{{ isset($auction->get->applicationLinkThird) ? $auction->get->applicationLinkThird : '' }}">
        </div>
    </div>
    <div class="form-group depositFour d-none">
        <label class="fw-bold">Please enter the required move-in amounts:</label>
        <div class="form-group">
            <label class="fw-bold">First Month:</label>
            <input type="number" name="firstMonthFour" class="form-control has-icon "
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->firstMonthFour) ? $auction->get->firstMonthFour : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Last Month:</label>
            <input type="number" name="lastMonthFour" class="form-control has-icon "
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->lastMonthFour) ? $auction->get->lastMonthFour : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Security Deposit:</label>
            <input type="number" name="securityDepositFour" class="form-control has-icon "
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->securityDepositFour) ? $auction->get->securityDepositFour : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Exit Cleaning Fee:</label>
            <input type="number" name="exitCleaningFeeFour" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->exitCleaningFeeFour) ? $auction->get->exitCleaningFeeFour : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Fee:</label>
            <input type="number" name="applicationFeeFour" class="form-control has-icon "
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->applicationFeeFour) ? $auction->get->applicationFeeFour : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Link:</label>
            <input type="text" name="applicationLinkFour" class="form-control has-icon "
                data-icon="fa-solid fa-link" placeholder="" required
                value="{{ isset($auction->get->applicationLinkFour) ? $auction->get->applicationLinkFour : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Vacation Tax:</label>
            <input type="number" name="vacationTaxFour"class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign"
                value="{{ isset($auction->get->vacationTaxFour) ? $auction->get->vacationTaxFour : '' }}">
        </div>
    </div>
    <div class="form-group depositFive d-none">
        <label class="fw-bold">Please enter the required move-in amounts:</label>
        <div class="form-group">
            <label class="fw-bold">First Month:</label>
            <input type="number" name="firstMonthFive" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->firstMonthFive) ? $auction->get->firstMonthFive : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Security Deposit:</label>
            <input type="number" name="securityDepositFive" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->securityDepositFive) ? $auction->get->securityDepositFive : '' }}">

        </div>
        <div class="form-group">
            <label class="fw-bold">Exit Cleaning Fee:</label>
            <input type="number" name="exitCleaningFeeFive" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->exitCleaningFeeFive) ? $auction->get->exitCleaningFeeFive : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Fee:</label>
            <input type="number" name="applicationFeeFive" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->applicationFeeFive) ? $auction->get->applicationFeeFive : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Link:</label>
            <input type="text" name="applicationLinkFive" data-icon="fa-solid fa-link"
                class="form-control has-icon" required
                value="{{ isset($auction->get->applicationLinkFive) ? $auction->get->applicationLinkFive : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Vacation Tax:</label>
            <input type="number" name="vacationTaxFive" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->vacationTaxFive) ? $auction->get->vacationTaxFive : '' }}">
        </div>
    </div>
    <div class="form-group depositSix d-none">
        <label class="fw-bold">Please enter the required move-in amounts:</label>
        <div class="form-group">
            <label class="fw-bold">First Month:</label>
            <input type="number" name="firstMonthSix" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->firstMonthSix) ? $auction->get->firstMonthSix : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Security Deposit:</label>
            <input type="number" name="securityDepositSix" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->securityDepositSix) ? $auction->get->securityDepositSix : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Exit Cleaning Fee:</label>
            <input type="number" name="exitCleaningFeeSix" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->exitCleaningFeeSix) ? $auction->get->exitCleaningFeeSix : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Fee:</label>
            <input type="number" name="applicationFeeSix" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->applicationFeeSix) ? $auction->get->applicationFeeSix : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Link:</label>
            <input type="text" name="applicationLinkSix" data-icon="fa-solid fa-link"
                class="form-control has-icon" required
                value="{{ isset($auction->get->applicationLinkSix) ? $auction->get->applicationLinkSix : '' }}">
        </div>
    </div>
    <div class="form-group depositSeven d-none">
        <label class="fw-bold">Please enter the required move-in amounts:</label>
        <div class="form-group">
            <label class="fw-bold">First Month:</label>
            <input type="number" name="firstMonthSeven" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->firstMonthSeven) ? $auction->get->firstMonthSeven : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Security Deposit:</label>
            <input type="number" name="securityDepositSeven" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->securityDepositSeven) ? $auction->get->securityDepositSeven : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Fee:</label>
            <input type="number" name="applicationFeeSeven" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" placeholder="" required
                value="{{ isset($auction->get->applicationFeeSeven) ? $auction->get->applicationFeeSeven : '' }}">
        </div>
        <div class="form-group">
            <label class="fw-bold">Application Link:</label>
            <input type="text" name="applicationLinkSeven" data-icon="fa-solid fa-link"
                class="form-control has-icon" required
                value="{{ isset($auction->get->applicationLinkSeven) ? $auction->get->applicationLinkSeven : '' }}">
        </div>
    </div>
    {{-- <div class="form-group">
    <div class="form-group">
        @php
            $timeFrame = [
                ['name' => '12 hours', 'target' => ''],
                ['name' => '24 hours (1 day)', 'target' => ''],
                ['name' => '36 hours', 'target' => ''],
                ['name' => '48 hours (2 days)', 'target' => ''],
                ['name' => '60 hours', 'target' => ''],
                ['name' => '72 hours (3 days)', 'target' => ''],
                ['name' => '96 hours (4 days)', 'target' => ''],
                ['name' => '120 hours (5 days)', 'target' => ''],
                ['name' => '144 hours (6 days)', 'target' => ''],
                ['name' => '168 hours (7 days)', 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">Time Frame Allocated to Respond Offers:</label>
        <select class="grid-picker" name="timeFrame" style="justify-content: flex-start;" required
            multiple>
            <option value="">Select</option>
            @foreach ($timeFrame as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" style="width:calc(25% - 10px);"
                    data-icon='<i class="fa-regular fa-clock"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
  </div> --}}
    <div class="form-group">
        {{-- <div class="form-group">
      @php
          $timeFrame = [
              ['name' => '12 hours', 'target' => ''],
              ['name' => '24 hours (1 day)', 'target' => ''],
              ['name' => '36 hours', 'target' => ''],
              ['name' => '48 hours (2 days)', 'target' => ''],
              ['name' => '60 hours', 'target' => ''],
              ['name' => '72 hours (3 days)', 'target' => ''],
              ['name' => '96 hours (4 days)', 'target' => ''],
              ['name' => '120 hours (5 days)', 'target' => ''],
              ['name' => '144 hours (6 days)', 'target' => ''],
              ['name' => '168 hours (7 days)', 'target' => ''],
          ];
      @endphp
      <label class="fw-bold">Time Frame Allocated to Respond to Multiple Offers:</label>
      <select class="grid-picker" name="timeFrameMultiple" style="justify-content: flex-start;"
          required multiple>
          <option value="">Select</option>
          @foreach ($timeFrame as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                  class="card flex-row" style="width:calc(25% - 10px);"
                  data-icon='<i class="fa-regular fa-clock"></i>'>
                  {{ $item['name'] }}
              </option>
          @endforeach
      </select>
    </div> --}}
        <div class="form-group ">
            @php
                $specialMoveRes = [
                    [
                        'name' => 'Yes',
                        'target' => '.specialMoveRes',
                        'icon' => 'fa-regular fa-circle-check',
                    ],
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
            @endphp
            <label class="fw-bold">Would the landlord like to offer any move in specials for a tenant?
            </label>
            <select class="grid-picker" name="specialMoveOption" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($specialMoveRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'
                        {{ isset($auction->get->specialMoveOption) && $item['name'] == $auction->get->specialMoveOption ? 'selected' : '' }}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group specialMoveRes d-none">
                <label class="fw-bold">What is the move in special?</label>
                <input type="text" name="specialMove" id="" class="form-control has-icon"
                    data-icon="fa-regular fa-circle-check"
                    value="{{ isset($auction->get->specialMove) ? $auction->get->specialMove : '' }}">
            </div>
        </div>
    </div>
</div>
<div class="wizard-step" data-step="9">
    <h4>Landlord Prescreening Terms:</h4>
    <span class="resFields">
        <div class="form-group">
            @php
                $petsRes = [
                    [
                        'name' => 'Yes',
                        'target' => '.petsYesRes',
                        'icon' => 'fa-regular fa-circle-check',
                    ],
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
            @endphp
            <label class="fw-bold">Will the landlord accept pets? </label>
            <select class="grid-picker" name="petsOpt" style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($petsRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        {{ isset($auction->get->petsOpt) && $item['name'] == $auction->get->petsOpt ? 'selected' : '' }}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="from-group petsYesRes d-none">
                <label class="fw-bold">Number of Pets Allowed:</label>
                <input type="number" class="form-control has-icon" name="petsNumber" data-icon="fa-solid fa-dog"
                    value="{{ isset($auction->get->petsNumber) ? $auction->get->petsNumber : '' }}">
                <label class="fw-bold">Acceptable Pet Types:</label>
                <input type="text" class="form-control has-icon" name="petsType" data-icon="fa-solid fa-dog"
                    value="{{ isset($auction->get->petsType) ? $auction->get->petsType : '' }}">
                <label class="fw-bold">Maximum Pet Weight:</label>
                <input type="text" class="form-control has-icon" name="petsWeight" data-icon="fa-solid fa-dog"
                    value="{{ isset($auction->get->petsWeight) ? $auction->get->petsWeight : '' }}">
                <label class="fw-bold">One-Time Pet Deposit or Monthly Pet Fee:</label>
                <input type="text" class="form-control has-icon" name="petsFee" data-icon="fa-solid fa-dog"
                    value="{{ isset($auction->get->petsFee) ? $auction->get->petsFee : '' }}">
                <label class="fw-bold">Pet Fee Amount:</label>
                <input type="number" class="form-control has-icon" name="petsAmount" data-icon="fa-solid fa-dog"
                    value="{{ isset($auction->get->petsAmount) ? $auction->get->petsAmount : '' }}">
                <label class="fw-bold">Is the Pet Fee Refundable or Non-Refundable?</label>
                <input type="text" class="form-control has-icon" name="petsFund" data-icon="fa-solid fa-dog"
                    value="{{ isset($auction->get->petsFund) ? $auction->get->petsFund : '' }}">
            </div>
        </div>
    </span>
    @php
        $offer_occupants_accept = [
            ['name' => '1', 'target' => ''],
            ['name' => '2', 'target' => ''],
            ['name' => '3', 'target' => ''],
            ['name' => '4', 'target' => ''],
            ['name' => '5', 'target' => ''],
            ['name' => '6', 'target' => ''],
            ['name' => '7', 'target' => ''],
            ['name' => '8+', 'target' => ''],
            ['name' => 'Other', 'target' => '.custom_occupants'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">How many occupants will the landlord accept?</label>
        <select class="grid-picker" name="offer_allowed_occupants" id="offer_allowed_occupants"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($offer_occupants_accept as $oca)
                <option value="{{ $oca['name'] }}" data-target="{{ $oca['target'] }}"
                    class="card flex-row pt-0 pb-0" style="width:calc(10% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check" style="font-size:24px;position:relative;top:-5px;"></i>'
                    {{ isset($auction->get->offer_allowed_occupants) && $oca['name'] == $auction->get->offer_allowed_occupants ? 'selected' : '' }}>

                    {{ $oca['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group custom_occupants d-none">
            <label class="fw-bold" for="custom_occupants">How many occupants will the landlord
                accept?</label>
            <input type="text" name="custom_occupants" placeholder="" id="custom_occupants"
                class="form-control has-icon hide_arrow" data-icon="fa-regular fa-circle-check"
                value="{{ isset($auction->get->custom_occupants) ? $auction->get->custom_occupants : '' }}">
        </div>
    </div>
    <div class="form-group">
        @php
            $creditScoreRes = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
        @endphp
        <label class="fw-bold">What is the minimum credit score rating the landlord will
            accept?</label>
        <select class="grid-picker" name="creditScore" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($creditScoreRes as $item)
                <option value="{{ $item }}" data-target="" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'
                    {{ isset($auction->get->creditScore) && $item == $auction->get->creditScore ? 'selected' : '' }}>
                    {{ $item }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label class="fw-bold" for="offer_min_net_income">
            What is the minimum net income a household must earn to qualify for the rental?
        </label>
        <input type="number" name="offer_min_net_income" id="offer_min_net_income"
            class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar"
            value="{{ isset($auction->get->offer_min_net_income) ? $auction->get->offer_min_net_income : '' }}">
    </div>
    <div class="form-group">
        @php
            $evictionRes = [
                ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                [
                    'name' => 'Depends on the circumstance',
                    'target' => '',
                    'icon' => 'fa-regular fa-circle-check',
                ],
            ];
        @endphp
        <label class="fw-bold">Will the landlord accept a tenant with a prior eviction within the last 7 Years?</label>
        <select class="grid-picker" name="eviction" id="" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($evictionRes as $item)
                <option value="{{ $item['name'] }}" data-target="" class="card flex-row"
                    style="width:calc(30% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'
                    {{ isset($auction->get->eviction) && $item['name'] == $auction->get->eviction ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label class="fw-bold">Will the landlord accept a tenant with a prior felony within the last 7
            Years?
        </label>
        <select class="grid-picker" name="offer_prior_felony" id="offer_prior_felony"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($evictionRes as $item)
                <option value="{{ $item['name'] }}" data-target="" class="card flex-row"
                    style="width:calc(30% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'
                    {{ isset($auction->get->offer_prior_felony) && $item['name'] == $auction->get->offer_prior_felony ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step='10'>
    @php
        $bedrooms = [
            ['target' => '', 'name' => '1'],
            ['target' => '', 'name' => '2'],
            ['target' => '', 'name' => '3'],
            ['target' => '', 'name' => '4'],
            ['target' => '', 'name' => '5'],
            ['target' => '', 'name' => '6'],
            ['target' => '', 'name' => '7'],
            ['target' => '', 'name' => '8'],
            ['target' => '', 'name' => '9'],
            ['target' => '', 'name' => '10'],
            ['target' => '.other_bedrooms', 'name' => 'Other'],
        ];

    @endphp
    <div class="form-group">
        <label class="fw-bold">Bedrooms:</label>
        <select class="grid-picker" name="bedroom" style="justify-content: center;" required>
            <option value="">Select</option>
            @foreach ($bedrooms as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                    style="width:calc(15% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>'
                    {{ isset($auction->get->bedroom) && $item['name'] == $auction->get->bedroom ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group other_bedrooms d-none">
            <label class="fw-bold" for="other_bedrooms">Bedrooms:</label>
            <input type="number" name="other_bedrooms" id="other_bedrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bed" required
                value="{{ isset($auction->get->other_bedrooms) ? $auction->get->other_bedrooms : '' }}">

        </div>
    </div>
</div>
<div class="wizard-step" data-step='11'>
    @php
        $bathrooms = [
            ['target' => '', 'name' => '1'],
            ['target' => '', 'name' => '1.5'],
            ['target' => '', 'name' => '2'],
            ['target' => '', 'name' => '2.5'],
            ['target' => '', 'name' => '3'],
            ['target' => '', 'name' => '3.5'],
            ['target' => '', 'name' => '4'],
            ['target' => '', 'name' => '4.5'],
            ['target' => '', 'name' => '5'],
            ['target' => '', 'name' => '5.5'],
            ['target' => '', 'name' => '6'],
            ['target' => '', 'name' => '6.5'],
            ['target' => '', 'name' => '7'],
            ['target' => '', 'name' => '7.5'],
            ['target' => '', 'name' => '8'],
            ['target' => '', 'name' => '8.5'],
            ['target' => '', 'name' => '9'],
            ['target' => '', 'name' => '9.5'],
            ['target' => '', 'name' => '10'],
            ['target' => '.other_bathrooms', 'name' => 'Other'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Bathrooms:</label>
        <select class="grid-picker" name="bathrooms" id="bathrooms" style="justify-content: center;" required>
            <option value="">Select</option>
            @foreach ($bathrooms as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column" style="width:calc(15% - 10px);"
                    data-icon='<i class="fa-solid fa-bath"></i>'
                    {{ isset($auction->get->bathrooms) && $item['name'] == $auction->get->bathrooms ? 'selected' : '' }}>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group other_bathrooms d-none">
            <label class="fw-bold" for="other_bathrooms">Bathrooms:</label>
            <input type="number" name="other_bathrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bath"
                value="{{ isset($auction->get->other_bathrooms) ? $auction->get->other_bathrooms : '' }}" required>
        </div>
    </div>
</div>
