<h3> Buyer Information</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>

            <strong>👤 Provide your contact details. You may also upload a photo or video to personalize your request and help Agents better understand your needs.</strong>
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
    <label class="fw-bold">Last Name:<span class="text-danger">*</span></label>
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
            placeholder="(555) 555-5555" id="buyer_phone_number" inputmode="numeric" autocomplete="tel" maxlength="14"
            oninput="formatBuyerPhone(this)" required>
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
    <label class="fw-bold">Buyer’s Current Status:</label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Indicate the Buyer’s current situation to help Agents tailor their support.">
        <i class="fa-solid fa-circle-info"></i>

    </span>
    <div class="input-cover">
        <select wire:model="current_status" class="form-control has-icon" data-icon="fa-solid fa-chart-pie">
            <option value="">Select status</option>
            <option value="Currently Renting">Currently Renting</option>
            <option value="First-Time Buyer">First-Time Buyer</option>
            <option value="Homeowner – Selling to Buy">Homeowner – Selling to Buy</option>
            <option value="Homeowner – Keeping Current Property">Homeowner – Keeping Current Property</option>
            <option value="Relocating from Out of Area">Relocating from Out of Area</option>
            <option value="Investor">Investor</option>
        </select>
    </div>
    <span class="error mt-2" id="buyer_status_error"></span>
</div>

@if ($service_type === 'full_service')

    <!-- Representation Preferences & Compatibility Review Summary -->
    @php
        $bs = $compatibility_preferences['buyer_specific'] ?? [];
        $rpArr = isset($bs['representation_priorities']) && is_array($bs['representation_priorities']) ? $bs['representation_priorities'] : [];
        // Resolve "Other" companion text — never display literal "Other" in summary
        $ptgVal  = ($bs['primary_transaction_goal'] ?? '') === 'Other'
            ? (($bs['primary_transaction_goal_other'] ?? '') ?: 'Other')
            : ($bs['primary_transaction_goal'] ?? '');
        $pawsVal = ($bs['preferred_agent_working_style'] ?? '') === 'Other'
            ? (($bs['preferred_agent_working_style_other'] ?? '') ?: 'Other')
            : ($bs['preferred_agent_working_style'] ?? '');
        $compatReviewFields = [
            'Primary Transaction Goal'       => $ptgVal,
            'Risk Tolerance'                 => $bs['risk_tolerance'] ?? '',
            'Decision-Making Style'          => $bs['decision_making_style'] ?? '',
            'Timeline Flexibility'           => $bs['timeline_flexibility'] ?? '',
            'Communication Style'            => $bs['communication_style'] ?? '',
            'Preferred Contact Method'       => $bs['preferred_contact_method'] ?? '',
            'Meeting / Showing Preference'   => $bs['communication_frequency'] ?? '',
            'Availability / Best Times'      => $bs['availability_windows'] ?? '',
            'Negotiation Style'              => $bs['negotiation_style'] ?? '',
            'Preferred Agent Working Style'  => $pawsVal,
            'Expected Level of Support'      => $bs['support_level'] ?? '',
            'Non-Negotiable / Deal Breakers' => $bs['deal_breakers'] ?? '',
            'Additional Notes'               => $bs['additional_compatibility_notes'] ?? '',
        ];
        $compatHasValues = !empty($rpArr) || collect($compatReviewFields)->filter()->isNotEmpty();
    @endphp
    @if ($compatHasValues)
    <div class="card mb-4">
        <div class="card-header fw-bold bg-light">
            <i class="fa-solid fa-handshake me-2"></i>Representation Preferences &amp; Compatibility
        </div>
        <div class="card-body small">
            <ul class="list-unstyled mb-0">
                @if (!empty($rpArr))
                    <li class="mb-1"><strong>Representation Priorities:</strong> {{ implode(', ', $rpArr) }}</li>
                @endif
                @foreach ($compatReviewFields as $label => $val)
                    @if ($val)
                        <li class="mb-1"><strong>{{ $label }}:</strong> {{ $val }}</li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
    @endif

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
                title="Paste a YouTube or Vimeo link that explains what the Buyer is looking for in an Agent.">
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

<script>
function formatBuyerPhone(input) {
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

function handleBuyerPhonePaste(event) {
    event.preventDefault();
    let paste = (event.clipboardData || window.clipboardData).getData('text');
    event.target.value = paste.replace(/\D/g, '');
    formatBuyerPhone(event.target);
}

function initBuyerPhoneFormatting() {
    const phoneInput = document.getElementById('buyer_phone_number');
    if (phoneInput) {
        if (phoneInput.value) {
            formatBuyerPhone(phoneInput);
        }
        if (!phoneInput.hasAttribute('data-paste-init')) {
            phoneInput.addEventListener('paste', handleBuyerPhonePaste);
            phoneInput.setAttribute('data-paste-init', 'true');
        }
    }
}

// Initialize immediately since Livewire may have already loaded
initBuyerPhoneFormatting();

// Also initialize on DOMContentLoaded in case script runs before DOM is ready
document.addEventListener('DOMContentLoaded', initBuyerPhoneFormatting);

// Re-initialize after any Livewire update
document.addEventListener('livewire:load', function() {
    Livewire.hook('message.processed', initBuyerPhoneFormatting);
});

// For Livewire v3 compatibility
document.addEventListener('livewire:init', function() {
    Livewire.hook('morph.updated', initBuyerPhoneFormatting);
});
</script>
