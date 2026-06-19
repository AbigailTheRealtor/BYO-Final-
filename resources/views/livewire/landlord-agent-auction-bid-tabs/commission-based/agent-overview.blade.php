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
            <textarea wire:model="why_hire_you" id="why_hire_you" class="form-control has-icon @error('why_hire_you') is-invalid @enderror" rows="4" placeholder="Enter why you should be hired (e.g., Proven track record, 10+ years experience, 5-star reviews)"
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
            <textarea wire:model="what_sets_you_apart" id="what_sets_you_apart" class="form-control has-icon @error('what_sets_you_apart') is-invalid @enderror" rows="4"  placeholder="Enter what sets you apart (e.g., Off-market network, same-day responsiveness, multilingual)"
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
            <textarea wire:model="marketing_plan" id="marketing_plan" class="form-control has-icon @error('marketing_plan') is-invalid @enderror" rows="4" placeholder="Enter Marketing Strategy (e.g., Social media ads, email campaigns, open houses)"
                 required></textarea>
        </div>
        @error('marketing_plan')<span class="text-danger small">{{ $message }}</span>@enderror
        <span class="error mt-2" id="marketing_plan_error"></span>
    </div>
