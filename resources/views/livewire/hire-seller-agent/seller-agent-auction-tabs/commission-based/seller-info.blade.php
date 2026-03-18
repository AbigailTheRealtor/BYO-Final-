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
            placeholder="Enter email address " required>
    </div>
</div>


<!-- Buyer’s Current Status -->
<div class="form-group mt-3">
    <label class="fw-bold">Seller’s Current Status: <span class="text-danger">*</span></label>

    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
        title="Select the option that best describes the Seller’s current situation.">
        <i class="fa-solid fa-circle-info"></i>

    </span>
    <div class="input-cover">
        <select wire:model="current_status" class="form-control has-icon" data-icon="fa-solid fa-chart-pie" required>
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
                    data-icon="fas fa-camera" accept="image/*">
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
                title="Paste a YouTube or Vimeo link that explains what the Tenant is looking for in an Agent.">
                {{-- 💬 --}}
                <i class="fa-solid fa-circle-info"></i>

            </span>
        </label>
        <div class="input-cover">
            <input type="url" wire:model="video_link" class="form-control has-icon"
                data-icon="fa-solid fa-video" placeholder="Enter video link (e.g. YouTube, Vimeo)">
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
