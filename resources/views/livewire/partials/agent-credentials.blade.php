<h3><i class="fa fa-id-badge me-2 text-muted"></i>Agent Credentials &amp; Contact Info</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>Provide your basic contact and license information. This section helps the other party verify your credentials and understand who they're working with.</strong>
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
    @error('first_name') <span class="text-danger">{{ $message }}</span> @enderror
</div>

<!-- Last Name -->
<div class="form-group">
    <label class="fw-bold">Last Name:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="text" wire:model="last_name" class="form-control has-icon" data-icon="fa-solid fa-user"
            placeholder="Enter last name" required>
    </div>
    @error('last_name') <span class="text-danger">{{ $message }}</span> @enderror
</div>

<!-- Phone Number -->
<div class="form-group">
    <label class="fw-bold">Phone Number:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="text" wire:model.defer="phone_number" class="form-control has-icon" data-icon="fa-solid fa-phone"
            placeholder="(555) 555-5555" id="agent_cred_phone" inputmode="numeric" autocomplete="tel" maxlength="14"
            oninput="formatAgentCredPhone(this)" required>
    </div>
    @error('phone_number') <span class="text-danger">{{ $message }}</span> @enderror
</div>

<!-- Email -->
<div class="form-group">
    <label class="fw-bold">Email Address:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="email" wire:model="email" class="form-control has-icon" data-icon="fa-solid fa-envelope"
            placeholder="Enter email address" required>
    </div>
    @error('email') <span class="text-danger">{{ $message }}</span> @enderror
</div>

<!-- Brokerage -->
<div class="form-group">
    <label class="fw-bold">Brokerage:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="text" wire:model="agent_brokerage" class="form-control has-icon" data-icon="fa-solid fa-building"
            placeholder="Enter brokerage name" required>
    </div>
    @error('agent_brokerage') <span class="text-danger">{{ $message }}</span> @enderror
</div>

<!-- Real Estate License # -->
<div class="form-group">
    <label class="fw-bold">Real Estate License #:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="text" wire:model="agent_license_number" class="form-control has-icon" data-icon="fa-solid fa-certificate"
            placeholder="Enter your real estate license number" required>
    </div>
    @error('agent_license_number') <span class="text-danger">{{ $message }}</span> @enderror
</div>

<!-- NAR Member ID (NRDS ID) -->
<div class="form-group">
    <label class="fw-bold">
        NAR Member ID (NRDS ID):
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Your National Association of REALTORS® Member ID (also known as NRDS ID). This is optional but helps verify your NAR membership.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="text" wire:model="agent_nar_member_id" class="form-control has-icon" data-icon="fa-solid fa-id-card"
            placeholder="Enter your NAR Member ID (optional)">
    </div>
    @error('agent_nar_member_id') <span class="text-danger">{{ $message }}</span> @enderror
</div>

<script>
function formatAgentCredPhone(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 10) value = value.substring(0, 10);
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

function handleAgentCredPhonePaste(event) {
    event.preventDefault();
    let paste = (event.clipboardData || window.clipboardData).getData('text');
    event.target.value = paste.replace(/\D/g, '');
    formatAgentCredPhone(event.target);
}

function initAgentCredPhoneFormatting() {
    const phoneInput = document.getElementById('agent_cred_phone');
    if (phoneInput) {
        if (phoneInput.value) formatAgentCredPhone(phoneInput);
        if (!phoneInput.hasAttribute('data-paste-init')) {
            phoneInput.addEventListener('paste', handleAgentCredPhonePaste);
            phoneInput.setAttribute('data-paste-init', 'true');
        }
    }
}

initAgentCredPhoneFormatting();
document.addEventListener('DOMContentLoaded', initAgentCredPhoneFormatting);
document.addEventListener('livewire:load', function() {
    Livewire.hook('message.processed', initAgentCredPhoneFormatting);
});
document.addEventListener('livewire:init', function() {
    Livewire.hook('morph.updated', initAgentCredPhoneFormatting);
});
</script>
