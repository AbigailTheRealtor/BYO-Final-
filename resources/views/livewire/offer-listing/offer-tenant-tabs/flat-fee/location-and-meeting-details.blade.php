<h4> Location Where the Service(s) Are Needed:</h4>
<!-- Acceptable Cities -->
<div class="form-group mb-3">
    <label class="fw-bold mb-2">City:</label>
    <div class="input-cover position-relative">
        <input type="text" wire:model.debounce.300ms="newCity" wire:keydown.enter.prevent="selectCitySuggestion()"
            wire:keydown.arrow-up.prevent="decrementHighlight('City')"
            wire:keydown.arrow-down.prevent="incrementHighlight('City')"
            class="form-control has-icon @error('newCity') is-invalid @enderror" data-icon="fa-solid fa-city"
            autocomplete="off" placeholder="Enter city">

        <!-- City Suggestions Dropdown -->
        @if (count($citySuggestions) > 0)
            <div class="autocomplete-dropdown shadow-sm">
                <ul class="list-group">
                    @foreach ($citySuggestions as $index => $suggestion)
                        <li class="list-group-item {{ $highlightedCityIndex === $index ? 'bg-light' : '' }}"
                            wire:click="selectCitySuggestion('{{ $suggestion }}')"
                            wire:key="city-suggestion-{{ $index }}">
                            <i class="fa-solid fa-city me-2 text-muted"></i>
                            {{ $suggestion }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @error('newCity')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <!-- Display added cities -->
    <div class="mt-1 cities-container">
        @if (count($cities) > 0)
            @foreach ($cities as $index => $city)
                <span class="badge bg-primary rounded-pill d-inline-flex align-items-center" wire:key="city-badge-{{ $index }}">
                    <i class="fa-solid fa-city me-2"></i>
                    {{ $city }}
                    <button type="button" class="byo-pill-remove ms-2"
                        wire:click="removeCity({{ $index }})" aria-label="Remove">&times;</button>
                </span>
            @endforeach

        @endif
    </div>
</div>

<!-- Acceptable Counties -->
<div class="form-group mb-3">
    <label class="fw-bold mb-2"> County:</label>
    <div class="input-cover position-relative">
        <input type="text" wire:model.debounce.300ms="newCounty" wire:keydown.enter.prevent="selectCountySuggestion()"
            wire:keydown.arrow-up.prevent="decrementHighlight('County')"
            wire:keydown.arrow-down.prevent="incrementHighlight('County')"
            class="form-control has-icon @error('newCounty') is-invalid @enderror" data-icon="fa-solid fa-map"
            autocomplete="off" placeholder="Enter county">

        <!-- County Suggestions Dropdown -->
        @if (count($countySuggestions) > 0)
            <div class="autocomplete-dropdown shadow-sm">
                <ul class="list-group">
                    @foreach ($countySuggestions as $index => $suggestion)
                        <li class="list-group-item {{ $highlightedCountyIndex === $index ? 'bg-light' : '' }}"
                            wire:click="selectCountySuggestion('{{ $suggestion }}')"
                            wire:key="county-suggestion-{{ $index }}">
                            <i class="fa-solid fa-map me-2 text-muted"></i>
                            {{ $suggestion }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @error('newCounty')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <!-- Display added counties -->
    <div class="mt-1 counties-container">
        @if (count($counties) > 0)
            @foreach ($counties as $index => $county)
                <span class="badge bg-primary rounded-pill d-inline-flex align-items-center" wire:key="county-badge-{{ $index }}">
                    <i class="fa-solid fa-map me-2"></i>
                    {{ $county }}
                    <button type="button" class="byo-pill-remove ms-2"
                        wire:click="removeCounty({{ $index }})" aria-label="Remove">&times;</button>
                </span>
            @endforeach

        @endif
    </div>
    <span class="error mt-2" id="counties_error"></span>
</div>

<!-- Acceptable State -->
<div class="form-group">
    <label class="fw-bold"> State:</label>
    <div class="input-cover position-relative">
        <input type="text" wire:model="state" class="form-control has-icon" data-icon="fa-solid fa-flag-usa" required
            wire:keydown.arrow-up="decrementHighlight('state')" wire:keydown.arrow-down="incrementHighlight('state')"
            wire:keydown.enter.prevent="selectStateSuggestion" autocomplete="off" placeholder="Enter state">

        @if (count($stateSuggestions) > 0)
            <div class="autocomplete-dropdown">
                <ul class="list-group">
                    @foreach ($stateSuggestions as $index => $suggestion)
                        <li class="list-group-item {{ $highlightedStateIndex === $index ? 'active' : '' }}"
                            wire:click="selectStateSuggestion('{{ $suggestion }}')">
                            {{ $suggestion }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <span class="error mt-2" id="state_error"></span>
</div>

<!-- In-Person Meeting Section -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Will any of the selected services require an in-person meeting?
    </label>
    <div class="input-cover mt-2">
        <select wire:model="person_meeting" class="form-control has-icon" data-icon="fa-solid fa-handshake" required>
            <option value="">Select</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </div>

    @if ($person_meeting === 'yes')
        <div class="bg-light p-3 mt-3 rounded">
            <h5 class="fw-bold mb-3">
                In-Person Meeting Details:
                <small class="text-muted" data-bs-toggle="tooltip"
                    title="Specify the date and time for the in-person meeting related to the selected service(s). This will be visible to the agent before they accept the job.">
                    <i class="fa-solid fa-comment-dots text-primary"></i>
                </small>
            </h5>

            <!-- Date -->
            <div class="form-group mb-3">
                <label class="fw-bold"> Date:</label>
                <div class="input-cover">
                    <input type="date" wire:model="meeting_details_meeting_date" class="form-control has-icon"
                        data-icon="fa-regular fa-calendar-days" min="{{ now()->format('Y-m-d') }}" required>
                </div>
            </div>

            <!-- Time -->
            <div class="form-group mb-3">
                <label class="fw-bold"> Time:</label>
                <div class="input-cover">
                    <input type="time" wire:model="meeting_details_meeting_time" class="form-control has-icon"
                        data-icon="fa-solid fa-clock" required>
                </div>
            </div>

            <!-- Time Zone -->
            <div class="form-group mb-3">
                <label class="fw-bold">Time Zone:</label>
                <div class="input-cover">
                    <select wire:model="meeting_details_time_zone" class="form-control has-icon"
                        data-icon="fa-solid fa-globe" required>
                        <option value="">Select Time Zone</option>
                        <option value="ET">Eastern Time (ET)</option>
                        <option value="CT">Central Time (CT)</option>
                        <option value="MT">Mountain Time (MT)</option>
                        <option value="PT">Pacific Time (PT)</option>
                        <option value="AKT">Alaska Time (AKT)</option>
                        <option value="HT">Hawaii Time (HT)</option>
                    </select>
                </div>
            </div>

            <!-- Private Meeting Details -->
            <div class="mt-3 pt-3 border-top">
                <h5 class="fw-bold mb-3">
                    Private Meeting Details
                    <small class="text-muted d-block mt-2">
                        This information will only be visible to the hired Broker and will not appear on the public
                        listing.
                    </small>
                </h5>

                <!-- First Name -->
                <div class="form-group mb-3">
                    <label class="fw-bold">First Name:</label>
                    <div class="input-cover">
                        <input type="text" wire:model="meeting_details_first_name" class="form-control has-icon"
                            data-icon="fa-solid fa-user" placeholder="Enter first name" required>
                    </div>
                </div>

                <!-- Last Name -->
                <div class="form-group mb-3">
                    <label class="fw-bold">Last Name:</label>
                    <div class="input-cover">
                        <input type="text" wire:model="meeting_details_last_name" class="form-control has-icon"
                            data-icon="fa-solid fa-user" placeholder="Enter last name" required>
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="form-group mb-3">
                    <label class="fw-bold">Phone Number:</label>
                    <div class="input-cover">
                        <input type="tel" wire:model="meeting_details_phone" class="form-control has-icon"
                            data-icon="fa-solid fa-phone" placeholder="Enter phone number" required>
                    </div>
                </div>

                <!-- Email Address -->
                <div class="form-group mb-3">
                    <label class="fw-bold">Email Address:</label>
                    <div class="input-cover">
                        <input type="email" wire:model="meeting_details_email" class="form-control has-icon"
                            data-icon="fa-solid fa-envelope" placeholder="Enter email address" required>
                    </div>
                </div>

                <!-- Property Address -->
                <div class="form-group mb-3">
                    <label class="fw-bold"> Property Address:</label>
                    <div class="input-cover position-relative">
                        <input type="text" wire:model="address" class="form-control has-icon"
                            data-icon="fa-solid fa-map-pin" placeholder="Enter property address"
                            wire:keydown.arrow-up="decrementHighlight('address')"
                            wire:keydown.arrow-down="incrementHighlight('address')"
                            wire:keydown.enter.prevent="selectAddressSuggestion" autocomplete="off" required>

                        @if (count($addressSuggestions) > 0)
                            <div class="autocomplete-dropdown">
                                <ul class="list-group">
                                    @foreach ($addressSuggestions as $index => $suggestion)
                                        <li class="list-group-item {{ $highlightedAddressIndex === $index ? 'active' : '' }}"
                                            wire:click="selectAddressSuggestion('{{ $suggestion }}')">
                                            <i class="fa-solid fa-location-dot me-2"></i>
                                            {{ $suggestion }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="form-group">
                    <label class="fw-bold">Showing Instructions:</label>
                    <div class="input-cover">
                        <textarea wire:model="meeting_details_instructions" id="meeting_details_instructions" class="form-control has-icon"
                            rows="4" data-icon="fa-solid fa-question" placeholder="Enter showing instructions"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label class="fw-bold">Additional Private Details:</label>
                    <div class="input-cover">
                        <textarea wire:model="meeting_details_additional_details" id="meeting_details_additional_details"
                            class="form-control has-icon" rows="4" data-icon="fa-solid fa-question"
                            placeholder="Enter additional private details"></textarea>
                    </div>
                </div>

            </div>
        </div>
    @elseif ($person_meeting === 'no')
        <!-- Deadline for Service Completion -->
        <div class="bg-light p-3 mt-3 rounded">
            <h5 class="fw-bold mb-3">
                Deadline for Service Completion (if no in-person meeting is required):
                <small class="text-muted" data-bs-toggle="tooltip"
                    title="This is the date and time by which the agent should complete the selected service(s) if no in-person meeting is required.">
                    <i class="fa-solid fa-comment-dots text-primary"></i>
                </small>
            </h5>
            <!-- Date -->
            <div class="form-group mb-3">
                <label class="fw-bold">Date:</label>
                <div class="input-cover">
                    <input type="date" wire:model="service_completion_date" class="form-control has-icon"
                        data-icon="fa-regular fa-calendar-days" min="{{ now()->format('Y-m-d') }}" required>
                </div>
            </div>

            <!-- Time -->
            <div class="form-group mb-3">
                <label class="fw-bold"> Time:</label>
                <div class="input-cover">
                    <input type="time" wire:model="service_completion_time" class="form-control has-icon"
                        data-icon="fa-solid fa-clock" required>
                </div>
            </div>

            <!-- Time Zone -->
            <div class="form-group">
                <label class="fw-bold"> Time Zone:</label>
                <div class="input-cover">
                    <select wire:model="service_time_zone" class="form-control has-icon"
                        data-icon="fa-solid fa-globe" required>
                        <option value="">Select Time Zone</option>
                        <option value="ET">Eastern Time (ET)</option>
                        <option value="CT">Central Time (CT)</option>
                        <option value="MT">Mountain Time (MT)</option>
                        <option value="PT">Pacific Time (PT)</option>
                        <option value="AKT">Alaska Time (AKT)</option>
                        <option value="HT">Hawaii Time (HT)</option>
                    </select>
                </div>
            </div>
        </div>
    @endif
</div>
