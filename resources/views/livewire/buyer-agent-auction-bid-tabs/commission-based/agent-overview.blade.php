    <h4>Agent Overview & Qualifications</h4>
    <input type="hidden" wire:model="auctionId" value="{{ $auctionId }}">

    <!-- About Agent -->
    <div class="form-group">
         <div class="alert alert-info bg-light-info border-info mb-4">
        <div class="d-flex align-items-center">
            <div>
                <strong>💡 Tell us about yourself. Use this section to highlight your background, skills, and qualifications that make you a great fit for this opportunity. These responses help clients compare and evaluate Agent bids. </strong>
            </div>
        </div>
    </div>
        <label class="fw-bold">About Agent:</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Provide a brief summary of your background, experience, and the areas you serve. This helps clients get to know you before reviewing your full bid.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="bio" id="bio" class="form-control has-icon" rows="4" placeholder="Enter a brief summary of your background, experience, and areas you serve"
                required></textarea>
        </div>
        <span class="error mt-2" id="bio_error"></span>
    </div>

    <!-- Why Hire You -->
    <div class="form-group">
        <label class="fw-bold">Why Should You Be Hired as Their Agent?</label>
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Explain why you're the right fit for this client. Highlight your experience, communication style, service approach, or results you've delivered for past clients.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="why_hire_you" id="why_hire_you" class="form-control has-icon" rows="4" placeholder="Explain why you’re a great fit for this opportunity"
                 required></textarea>
        </div>
        <span class="error mt-2" id="why_hire_you_error"></span>
    </div>

    <!-- What Sets You Apart -->
    <div class="form-group">
        <label class="fw-bold">What Sets You Apart From Other Agents?</label>
         <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="What makes you different? Share your niche expertise, negotiation style, market knowledge, tech tools, or how you personalize your service.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="what_sets_you_apart" id="what_sets_you_apart" class="form-control has-icon" rows="4"  placeholder="Describe what makes your approach or service different"
                 required></textarea>
        </div>
        <span class="error mt-2" id="what_sets_you_apart_error"></span>
    </div>

    <!-- Marketing Strategy -->
    <div class="form-group required-field">
        <label class="fw-bold">What Is Your Marketing Strategy?</label>
          <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Briefly describe how you promote listings or support your clients’ goals. For example, mention paid ads, social media, email campaigns, networking, or door-to-door outreach.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <textarea wire:model="marketing_plan" id="marketing_plan" class="form-control has-icon" rows="4" placeholder="Outline how you market listings or support your clients’ goals"
                 required></textarea>
        </div>
        <span class="error mt-2" id="marketing_plan_error"></span>
    </div>

    <!-- Reviews Links -->
    {{-- <div class="form-group">
        <label class="fw-bold">Reviews Link</label>
         <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter a link to the Agent’s public reviews (e.g., Zillow, Google, Realtor.com, Facebook). This helps clients understand your service quality and client satisfaction.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="url" wire:model="reviews_link" id="reviews_link"
                class="form-control has-icon" placeholder="Enter reviews link (e.g., https://www.google.com/search?q=Agent+Name+Reviews)"
                data-icon="fa-solid fa-link">
        </div>
        <span class="error mt-2" id="reviews_link1_error"></span>
    </div> --}}


  <div class="form-group">
    <label class="fw-bold">Reviews Link:</label>
    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Enter a link to the Agent’s public reviews (e.g., Zillow, Google, Realtor.com, Facebook). This helps clients understand your service quality and client satisfaction.">
        <i class="fa-solid fa-circle-info"></i>
    </span>

    {{-- Loop through the reviews_links array and generate an input for each --}}
    @foreach($reviews_links as $index => $link)
        <div class="input-cover mb-2 d-flex align-items-center">
            <input type="text" wire:model="reviews_links.{{ $index }}.url" id="reviews_link_{{ $index }}"
                class="form-control has-icon" placeholder="Enter reviews link (e.g., https://www.google.com/search?q=Agent+Name+Reviews)"
                data-icon="fa-solid fa-link">

            {{-- Remove button on the right side --}}
            @if($index > 0)
                <button type="button" wire:click="removeReviewLink({{ $index }})" class="btn btn-outline-danger btn-sm ms-2">
                    Remove
                </button>
            @endif
        </div>
    @endforeach

    {{-- Button to add a new review link input field --}}
    <button type="button" wire:click="addReviewLink" class="btn btn-outline-primary mt-2">
        + Add Another Review Link
    </button>

    <span class="error mt-2" id="reviews_link1_error"></span>
</div>



    <div class="form-group">
        <label class="fw-bold">Website Link:</label>
         <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Provide a link to the Agent’s professional website or online profile. This may include a personal website, brokerage page, or third-party profile.">
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
        title="Add links to the Agent’s professional social media profiles (e.g., Instagram, LinkedIn, Facebook, TikTok, YouTube). These help showcase your personality, marketing style, and market activity.">
        <i class="fa-solid fa-circle-info"></i>
    </span>
    @foreach ($social_media as $index => $media)
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
       placeholder="{{ $media['placeholder'] ?? 'Enter profile link' }}"
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
        <i class="fas fa-plus"></i> Add Social Media
    </button>
</div>

    <!-- Year Licensed -->
    <div class="form-group required-field">
        <label class="fw-bold">Year Agent Got Licensed:</label>
         <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Enter the year the Agent was first licensed. This gives clients a general idea of your experience level in the industry.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="input-cover">
            <input type="number" wire:model="year_licensed" placeholder="Enter year licensed (e.g., 2015)" id="year_licensed"
                class="form-control has-icon" min="1900" max="{{ date('Y') }}"
                required data-icon="fa-solid fa-calendar">
        </div>
        <span class="error mt-2" id="year_licensed_error"></span>
    </div>

