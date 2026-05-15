<h3> {{ ucfirst($user_type) }} Information</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>👤 Provide your contact details. You may also upload a photo or video to personalize your request
                and help Agents better understand your needs.
            </strong>
        </div>
    </div>
</div>
<!-- First Name -->
<div class="form-group">
    <label class="fw-bold">First Name:<span class="text-danger">*</span></label>

    <div class="input-cover">
        <input type="text" wire:model="first_name" class="form-control has-icon" data-icon="fa-solid fa-user"
            placeholder="Enter first name" required>
    </div>
</div>

<!-- Last Name -->
<div class="form-group">
    <label class="fw-bold">Last Name: <span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="text" wire:model="last_name" class="form-control has-icon" data-icon="fa-solid fa-user"
            placeholder="Enter last name" required>
    </div>
</div>

<!-- Phone Number -->
<div class="form-group">
    <label class="fw-bold">Phone Number:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="text" wire:model.defer="phone_number" class="form-control has-icon" data-icon="fa-solid fa-phone"
            placeholder="(555) 555-5555" id="seller_phone_number" inputmode="numeric" autocomplete="tel" maxlength="14"
            oninput="formatSellerPhone(this)" required>
    </div>
</div>

<!-- Email -->
<div class="form-group">
    <label class="fw-bold">Email Address:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="email" wire:model="email" class="form-control has-icon" data-icon="fa-solid fa-envelope"
            placeholder="Enter email address" required>
    </div>
</div>


<!-- Buyer’s Current Status -->
<div class="form-group mt-3">
    <label class="fw-bold">Seller’s Current Status:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the option that best describes the Seller’s current situation.">
        <i class="fa-solid fa-circle-info"></i>

    </span>
    <div class="input-cover">
        <select wire:model="current_status" class="form-control has-icon" data-icon="fa-solid fa-chart-pie">
            <option value="">Select status</option>
            <option value="First-Time Seller">First-Time Seller</option>
            <option value="Selling Primary Residence">Selling Primary Residence</option>
            <option value="Selling Secondary/Vacation Home">Selling Secondary/Vacation Home</option>
            <option value="Selling Investment Property">Selling Investment Property</option>
            <option value="Relocating and Need to Sell">Relocating and Need to Sell</option>
            <option value="Already Under Contract with Buyer">Already Under Contract with Buyer</option>
            <option value="Listing Expired or Canceled">Listing Expired or Canceled</option>
            <option value="Investor – Selling One or More Properties">Investor – Selling One or More Properties</option>
        </select>
    </div>
</div>
@if ($service_type === 'full_service')

    <!-- Photo Upload -->
    <div class="form-group">
        <label class="fw-bold">
            Personal Photo:
            <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                title="Upload a photo of yourself to personalize your listing and help build trust with Agents.">
                <i class="fa-solid fa-circle-info"></i>

            </span>
        </label>
        <div class="input-cover">
            <div class="input-group">
                <input type="file" wire:model="photo" id="photo-input" class="form-control has-icon"
                    data-icon="fa-solid fa-camera" accept="image/*">
            </div>
        </div>
        <span id="photo-error" class="text-danger" style="display: none;"></span>
    </div>

    <!-- Display Uploaded Photo -->
    @if ($photo)
        <div class="col-md-6 col-6 pt-2 fw-bold" id="photo-preview"
            style="width: 100%; max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
            Personal Photo:
            <span class="removeBold">
                @if (is_string($photo))
                    <!-- Existing file from storage -->
                    <img src="{{ asset('storage/auction/images/' . $photo) }}" style="width:100%;height:29vh;" />
                @else
                    <!-- Newly uploaded file (temporary) -->
                    <img src="{{ $photo->temporaryUrl() }}" style="width:100%;height:29vh;" />
                @endif
                <button wire:click="deletePhoto" wire:confirm="Are you sure you want to delete this photo?"
                    class="btn btn-danger btn-sm mt-2">
                    Delete Photo
                </button>
            </span>
        </div>
    @endif

    {{-- Video Upload removed from UI per requirements - DB/storage preserved --}}

    <!-- Full-Screen Loader (Handled by Livewire) -->
    <div id="video-loader" wire:loading wire:target="photo"
        style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 9999; display: flex; justify-content: center; align-items: center; visibility: hidden;">
        <div style="text-align: center; color: white;">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h3 class="mt-3">Uploading...</h3>
            <p>Please wait while we process your files.</p>
        </div>
    </div>

    <!-- Video Link (YouTube/Vimeo) -->
    <div class="form-group">
        <label class="fw-bold">
            Personal Video Link:
            <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                title="Paste a YouTube or Vimeo link that explains what the Seller is looking for in an Agent.">
                {{-- 💬 --}}
                <i class="fa-solid fa-circle-info"></i>

            </span>
        </label>
        <div class="input-cover">
            <input type="url" wire:model="video_link" class="form-control has-icon"
                data-icon="fa-solid fa-video" placeholder="Enter video link (e.g., YouTube, Vimeo)">
            <button class="btn btn-primary input-group-text-seller" type="button" wire:click="previewVideo">
                Enter
            </button>
        </div>
        @if($embedUrl)
            <div class="ratio ratio-16x9 mt-2" style="width:25%; height:40vh;">
                <iframe
                    src="{{ $embedUrl }}"
                    frameborder="0"
                    allow="autoplay; accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </div>
        @endif
    </div>

    <div class="alert alert-warning mt-3 p-2 small">
        <strong> 🛡️ Privacy Notice: </strong> Your last name, email address, and phone number are only visible to the platform admin. Only your first name and any uploaded photo or video will appear on the public listing. This ensures Agents contact you through the platform and protects your personal information.
    </div>
@endif

@if ($service_type === 'full_service' && $user_type === 'seller')
    @php
        $ss = $compatibility_preferences['seller_specific'] ?? [];
        $hasCompatData = !empty(array_filter($ss, fn($v) => is_array($v) ? count($v) > 0 : ($v !== '' && $v !== null)));
    @endphp
    @if ($hasCompatData)
        <hr class="mt-4">
        <div class="card border-info mt-3 mb-3">
            <div class="card-header bg-info text-white fw-bold">
                <i class="fa-solid fa-handshake me-2"></i> Representation Preferences &amp; Compatibility — Summary
            </div>
            <div class="card-body p-3">
                @php
                    $compatFields = [
                        // Section 1 — Communication
                        'communication_style'            => ['label' => 'Communication Style',          'type' => 'string'],
                        'preferred_contact_method'       => ['label' => 'Preferred Contact Method(s)',   'type' => 'array'],
                        'response_time_expectation'      => ['label' => 'Expected Response Time',        'type' => 'string'],
                        // Section 2 — Negotiation
                        'negotiation_style'              => ['label' => 'Negotiation Style',             'type' => 'string'],
                        'willing_to_negotiate_on'        => ['label' => 'Willing to Negotiate On',       'type' => 'array'],
                        'firm_on_price'                  => ['label' => 'Firm on Asking Price',          'type' => 'string'],
                        // Section 3 — Transaction Goal
                        'primary_transaction_goal'       => ['label' => 'Primary Transaction Goal',      'type' => 'string', 'other_key' => 'primary_transaction_goal_other'],
                        'target_sale_timeline'           => ['label' => 'Target Sale Timeline',          'type' => 'string'],
                        'flexibility_on_timeline'        => ['label' => 'Timeline Flexibility',          'type' => 'string'],
                        'post_sale_plan'                 => ['label' => 'Post-Sale Plans',               'type' => 'string'],
                        // Section 4 — Representation Priorities
                        'representation_priorities'      => ['label' => 'Representation Priorities',     'type' => 'array'],
                        'qualities_most_important'       => ['label' => 'Agent Qualities Most Important','type' => 'array'],
                        'past_agent_experience'          => ['label' => 'Past Agent Experience',         'type' => 'string'],
                        'what_did_not_work_before'       => ['label' => 'What Did Not Work Before',      'type' => 'string'],
                        // Section 5 — Decision-Making
                        'decision_making_style'          => ['label' => 'Decision-Making Style',         'type' => 'string'],
                        'involvement_level'              => ['label' => 'Involvement Level',              'type' => 'string'],
                        'additional_decision_makers'     => ['label' => 'Other Decision Makers',         'type' => 'string'],
                        // Section 6 — Working Style
                        'preferred_agent_working_style'  => ['label' => 'Preferred Agent Working Style', 'type' => 'string'],
                        'showing_availability'           => ['label' => 'Showing Availability',          'type' => 'array'],
                        'open_house_preference'          => ['label' => 'Open House Preference',         'type' => 'string'],
                        'additional_compatibility_notes' => ['label' => 'Additional Notes',              'type' => 'string'],
                    ];
                @endphp
                <div class="row g-2 small">
                    @foreach ($compatFields as $key => $meta)
                        @php
                            $val = $ss[$key] ?? null;
                            // Resolve "Other" to companion text — never display literal "Other"
                            if (isset($meta['other_key']) && $val === 'Other') {
                                $val = ($ss[$meta['other_key']] ?? '') ?: '';
                            }
                            $hasVal = $meta['type'] === 'array'
                                ? (!empty($val) && is_array($val) && count($val) > 0)
                                : ($val !== '' && $val !== null);
                        @endphp
                        @if ($hasVal)
                            <div class="{{ $meta['type'] === 'string' ? 'col-md-6' : 'col-12' }}">
                                <span class="fw-bold text-muted">{{ $meta['label'] }}:</span>
                                @if ($meta['type'] === 'array')
                                    <span class="ms-1">{{ implode(', ', (array)$val) }}</span>
                                @else
                                    <span class="ms-1">{{ $val }}</span>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endif

<script>
function formatSellerPhone(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    if (value.length >= 6) {
        input.value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6);
    } else if (value.length >= 3) {
        input.value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
    } else if (value.length > 0) {
        input.value = '(' + value;
    } else {
        input.value = '';
    }
}

function handleSellerPhonePaste(event) {
    event.preventDefault();
    let paste = (event.clipboardData || window.clipboardData).getData('text');
    event.target.value = paste.replace(/\D/g, '');
    formatSellerPhone(event.target);
}

function initSellerPhoneFormatting() {
    const phoneInput = document.getElementById('seller_phone_number');
    if (phoneInput) {
        if (phoneInput.value) {
            formatSellerPhone(phoneInput);
        }
        if (!phoneInput.hasAttribute('data-paste-init')) {
            phoneInput.addEventListener('paste', handleSellerPhonePaste);
            phoneInput.setAttribute('data-paste-init', 'true');
        }
    }
}

// Initialize immediately since Livewire may have already loaded
initSellerPhoneFormatting();

// Also initialize on DOMContentLoaded in case script runs before DOM is ready
document.addEventListener('DOMContentLoaded', initSellerPhoneFormatting);

// Re-initialize after any Livewire update
document.addEventListener('livewire:load', function() {
    Livewire.hook('message.processed', initSellerPhoneFormatting);
});

// For Livewire v3 compatibility
document.addEventListener('livewire:init', function() {
    Livewire.hook('morph.updated', initSellerPhoneFormatting);
});
</script>
