    <h4>Agent Overview & Qualifications</h4>
    <input type="hidden" wire:model="auctionId" value="{{ $auctionId }}">

    {{-- Default Profile Banner --}}
    @if($defaultProfileLoaded)
        <div class="alert alert-success d-flex align-items-center justify-content-between mb-3">
            <div>
                <i class="fa-solid fa-circle-check me-2"></i>
                <strong>Your default profile has been pre-filled.</strong>
                You can edit any field before submitting.
            </div>
        </div>
    @elseif($defaultProfileExists)
        <div class="alert alert-warning d-flex align-items-center justify-content-between mb-3">
            <div>
                <i class="fa-solid fa-bookmark me-2"></i>
                <strong>You have a saved default profile for this listing type.</strong>
                Would you like to pre-fill your Agent Overview with your saved answers?
            </div>
            <button type="button" wire:click="loadDefaultProfile" class="btn btn-sm btn-warning ms-3 text-nowrap">
                Load My Profile
            </button>
        </div>
    @endif

    <!-- About Agent -->
    <div class="form-group">
         <div class="alert alert-info bg-light-info border-info mb-4">
        <div class="d-flex align-items-center">
            <div>
                <strong>💡 Tell us about yourself. Use this section to highlight your background, skills, and qualifications that make you a great fit for this opportunity. These responses help clients compare and evaluate Agent bids. </strong>
            </div>
        </div>
    </div>
        <label class="fw-bold">About Agent:<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Provide a brief summary of your background, experience, and the areas you serve. This helps clients get to know you before reviewing your full bid.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="bio" id="bio" class="form-control has-icon @error('bio') is-invalid @enderror" rows="4" placeholder="Enter a brief summary of your background, experience, and areas you serve"
                required></textarea>
        </div>
        @error('bio')<span class="text-danger small">{{ $message }}</span>@enderror
        <span class="error mt-2" id="bio_error"></span>
    </div>

    <!-- Why Hire You -->
    <div class="form-group">
        <label class="fw-bold">Why Should You Be Hired as Their Agent?<span class="text-danger">*</span></label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Explain why you're the right fit for this client. Highlight your experience, communication style, service approach, or results you've delivered for past clients.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="why_hire_you" id="why_hire_you" class="form-control has-icon @error('why_hire_you') is-invalid @enderror" rows="4" placeholder="Explain why you're a great fit for this opportunity"
                 required></textarea>
        </div>
        @error('why_hire_you')<span class="text-danger small">{{ $message }}</span>@enderror
        <span class="error mt-2" id="why_hire_you_error"></span>
    </div>

    <!-- What Sets You Apart -->
    <div class="form-group">
        <label class="fw-bold">What Sets You Apart From Other Agents?<span class="text-danger">*</span></label>
         <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="What makes you different? Share your niche expertise, negotiation style, market knowledge, tech tools, or how you personalize your service.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="what_sets_you_apart" id="what_sets_you_apart" class="form-control has-icon @error('what_sets_you_apart') is-invalid @enderror" rows="4"  placeholder="Describe what makes your approach or service different"
                 required></textarea>
        </div>
        @error('what_sets_you_apart')<span class="text-danger small">{{ $message }}</span>@enderror
        <span class="error mt-2" id="what_sets_you_apart_error"></span>
    </div>

    <!-- Marketing Strategy -->
    <div class="form-group required-field">
        <label class="fw-bold">What Is Your Marketing Strategy?<span class="text-danger">*</span></label>
          <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Briefly describe how you promote listings or support your clients' goals. For example, mention paid ads, social media, email campaigns, networking, or door-to-door outreach.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="marketing_plan" id="marketing_plan" class="form-control has-icon @error('marketing_plan') is-invalid @enderror" rows="4" placeholder="Outline how you market listings or support your clients' goals"
                 required></textarea>
        </div>
        @error('marketing_plan')<span class="text-danger small">{{ $message }}</span>@enderror
        <span class="error mt-2" id="marketing_plan_error"></span>
    </div>

  <div class="form-group">
    <label class="fw-bold">Reviews Link:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter a link to the Agent's public reviews (e.g., Zillow, Google, Realtor.com, Facebook). This helps clients understand your service quality and client satisfaction.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    @foreach($reviews_links as $index => $link)
        <div class="input-cover mb-2 d-flex align-items-center">
            <input type="text" wire:model="reviews_links.{{ $index }}.url" id="reviews_link_{{ $index }}"
                class="form-control has-icon" placeholder="Enter reviews link (e.g., https://www.google.com/search?q=Agent+Name+Reviews)"
                data-icon="fa-solid fa-link">

            @if($index > 0)
                <button type="button" wire:click="removeReviewLink({{ $index }})" class="btn btn-outline-danger btn-sm ms-2">
                    Remove
                </button>
            @endif
        </div>
    @endforeach

    <button type="button" wire:click="addReviewLink" class="btn btn-outline-primary mt-2">
        + Add Another Review Link
    </button>

    <span class="error mt-2" id="reviews_link1_error"></span>
</div>

    <div class="form-group">
        <label class="fw-bold">Website Link:</label>
         <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Provide a link to the Agent's professional website or online profile. This may include a personal website, brokerage page, or third-party profile.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="website_link" id="website_link"
                class="form-control has-icon" placeholder="Enter website link (e.g., https://www.youragentsite.com)"
                data-icon="fa-solid fa-globe">
        </div>
        <span class="error mt-2" id="website_link_error"></span>
    </div>

    <!-- Social Media Platforms -->
    <div class="form-group">
    <label class="fw-bold">Social Media Platforms:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Add links to the Agent's professional social media profiles (e.g., Instagram, LinkedIn, Facebook, TikTok, YouTube). These help showcase your personality, marketing style, and market activity.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    @foreach ($social_media as $index => $media)
        @php $mediaArray = is_object($media) ? (array) $media : (is_array($media) ? $media : []); @endphp
        <div class="row mb-2">
            <div class="col-md-6">
                <div class="input-cover">
                    <select wire:model="social_media.{{ $index }}.platform"
                        class="form-control has-icon" data-icon="fa-solid fa-share-nodes"
                        wire:change="updatePlaceholder({{ $index }}, $event.target.value)">
                        <option value="">Select Platform</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Instagram">Instagram</option>
                        <option value="LinkedIn">LinkedIn</option>
                        <option value="TikTok">TikTok</option>
                        <option value="X">X</option>
                        <option value="YouTube">YouTube</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="input-cover">
                  <input type="text" wire:model="social_media.{{ $index }}.url"
       class="form-control has-icon"
       placeholder="{{ $mediaArray['placeholder'] ?? 'Enter profile link' }}"
       data-icon="fa-solid fa-link">
                </div>
            </div>
            <div class="col-md-2">
                @if ($index > 0)
                    <button wire:click="removeSocialMedia({{ $index }})"
                        class="btn btn-danger btn-sm">
                        Remove
                    </button>
                @endif
            </div>
        </div>
    @endforeach
    <button type="button" wire:click="addSocialMedia" class="btn btn-primary btn-sm" style="background-color: #0d6efd !important; border-color: #0d6efd !important; color: #ffffff !important;">
        <i class="fa-solid fa-plus"></i> Add Social Media
    </button>
</div>

    <!-- Year Licensed -->
    <div class="form-group required-field">
        <label class="fw-bold">Year Agent Got Licensed:<span class="text-danger">*</span></label>
         <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the year the Agent was first licensed. This gives clients a general idea of your experience level in the industry.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="year_licensed" placeholder="Enter year licensed (e.g., 2015)" id="year_licensed"
                class="form-control has-icon @error('year_licensed') is-invalid @enderror" min="1900" max="{{ date('Y') }}"
                required data-icon="fa-solid fa-calendar">
        </div>
        @error('year_licensed')<span class="text-danger small">{{ $message }}</span>@enderror
        <span class="error mt-2" id="year_licensed_error"></span>
    </div>

    <!-- Availability -->
    <hr class="my-4">
    <h5 class="fw-bold mb-3">Availability</h5>

    <div class="form-group">
        <label class="fw-bold">Average Response Time:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="How quickly do you typically respond to client inquiries? This helps clients know what to expect when working with you.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="avg_response_time" id="avg_response_time"
                class="form-control has-icon" data-icon="fa-solid fa-clock"
                placeholder="e.g. Within 1 hour">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Current Availability Status:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Indicate your current capacity to take on new clients. This helps clients understand if you're ready to engage immediately.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <select wire:model="availability_status" id="availability_status"
                class="form-control has-icon" data-icon="fa-solid fa-user-check">
                <option value="">Select availability</option>
                <option value="Actively Taking New Clients">Actively Taking New Clients</option>
                <option value="Limited Availability">Limited Availability</option>
                <option value="By Referral Only">By Referral Only</option>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Available in the Evenings?</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Let clients know if you are reachable or available for showings and calls during evening hours.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <select wire:model="evenings_available" id="evenings_available"
                        class="form-control has-icon" data-icon="fa-solid fa-moon">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Available on Weekends?</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Let clients know if you are available for showings, calls, or meetings on Saturdays and Sundays.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <select wire:model="weekends_available" id="weekends_available"
                        class="form-control has-icon" data-icon="fa-solid fa-calendar-week">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Experience & Track Record -->
    <hr class="my-4">
    <h5 class="fw-bold mb-3">Experience & Track Record</h5>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Years of Experience:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the total number of years you have been actively working as a licensed real estate agent.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="number" wire:model="years_experience" id="years_experience"
                        class="form-control has-icon" placeholder="e.g., 8"
                        min="0" data-icon="fa-solid fa-briefcase">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Transactions in Last 12 Months:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Enter the approximate number of transactions you have completed in the past 12 months. This helps clients gauge your recent market activity.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="number" wire:model="transactions_last_12_months" id="transactions_last_12_months"
                        class="form-control has-icon" placeholder="e.g., 15"
                        min="0" data-icon="fa-solid fa-handshake">
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Full-Time Agent?</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Indicate whether real estate is your full-time profession. Full-time agents are generally more accessible and dedicated to their clients.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <select wire:model="is_full_time" id="is_full_time"
                        class="form-control has-icon" data-icon="fa-solid fa-star">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Primary Areas Served:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="Briefly describe the primary geographic areas or markets where you have the most experience and transaction history.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="primary_areas_served" id="primary_areas_served"
                        class="form-control has-icon" placeholder="e.g., Downtown Miami, Coral Gables, Brickell"
                        data-icon="fa-solid fa-map-marker-alt">
                </div>
            </div>
        </div>
    </div>

    <!-- Service Areas -->
    <hr class="my-4">
    <h5 class="fw-bold mb-3">Service Areas</h5>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Cities Served:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="List the cities where you actively provide real estate services. Separate multiple cities with commas.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="cities_served" id="cities_served"
                        class="form-control has-icon" placeholder="e.g., Miami, Fort Lauderdale, Hollywood"
                        data-icon="fa-solid fa-city">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="fw-bold">Counties Served:</label>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                    title="List the counties where you actively work. Separate multiple counties with commas.">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <div class="input-cover">
                    <input type="text" wire:model="counties_served" id="counties_served"
                        class="form-control has-icon" placeholder="e.g., Miami-Dade, Broward, Palm Beach"
                        data-icon="fa-solid fa-map">
                </div>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Neighborhoods Served:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="List specific neighborhoods or subdivisions where you have strong market knowledge. Separate multiple entries with commas.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="text" wire:model="neighborhoods_served" id="neighborhoods_served"
                class="form-control has-icon" placeholder="e.g., Wynwood, Design District, South Beach"
                data-icon="fa-solid fa-location-dot">
        </div>
    </div>

    <div class="form-group">
        <label class="fw-bold">Additional Notes on Service Areas:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Use this space to provide any additional context about your geographic coverage, travel radius, or specialty markets.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="areas_notes" id="areas_notes"
                class="form-control has-icon" rows="3"
                placeholder="Any additional details about your service area coverage or geographic specialization"></textarea>
        </div>
    </div>
