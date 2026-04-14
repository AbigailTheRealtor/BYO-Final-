@extends('layouts.main')
@section('content')
<div class="mainDashboard">
    <div class="container">

        @include('layouts.partials.dashboard_user_section')

        <div class="dashboardContentDetails mt-3 mb-5">
            <div class="card">
                <div class="row">

                    @include('layouts.partials.sidenav')

                    <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                        <div class="container mt-4 mb-5 mySettings">

                            <h4 class="mb-1" style="color: #1a3a5c; font-weight: 700;">Profile Settings</h4>
                            <p class="text-muted mb-4" style="font-size: 0.95rem;">Keep your account details up to date.</p>

                            @if(session('profile_success'))
                            <div class="alert alert-success alert-dismissible fade show mb-2" role="alert">
                                <i class="fas fa-check-circle me-2"></i><strong>{{ session('profile_success') }}</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            @endif

                            @if(session('password_success'))
                            <div class="alert alert-success alert-dismissible fade show mb-2" role="alert">
                                <i class="fas fa-key me-2"></i>{{ session('password_success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            @endif

                            @if(session('password_error'))
                            <div class="alert alert-warning alert-dismissible fade show mb-2" role="alert" style="border-left: 4px solid #e65c00;">
                                <strong><i class="fas fa-exclamation-triangle me-2"></i>Password Not Updated</strong><br>
                                <span style="font-size: 0.92rem;">{{ session('password_error') }}</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            @endif

                            @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            @endif

                            {{-- ===== MAIN SETTINGS FORM ===== --}}
                            <form method="POST" action="{{ route('settings') }}" enctype="multipart/form-data">
                                @csrf

                                {{-- Alpine-only accordion — no Bootstrap collapse to avoid JS/CSS conflict --}}
                                <div x-data="{ openSection: null }">

                                    {{-- ── 1. Account Information ── --}}
                                    <div class="accordion-item border mb-3 rounded" style="border-radius: 10px !important; overflow: hidden;">
                                        <h2 class="accordion-header">
                                            <button type="button"
                                                    class="accordion-button"
                                                    :class="openSection !== 'account' && 'collapsed'"
                                                    @click="openSection = openSection === 'account' ? null : 'account'"
                                                    style="font-weight: 600; font-size: 1rem; color: #1a3a5c;">
                                                <i class="fas fa-user-circle me-2 text-muted"></i>Account Information
                                            </button>
                                        </h2>
                                        <div x-show="openSection === 'account'" x-transition style="display:none;">
                                            <div class="accordion-body pt-2 pb-4">
                                                <p class="text-muted small mb-3">Your login credentials. Email cannot be changed.</p>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Username</label>
                                                        <input type="text" class="form-control bg-light" value="{{ $user->user_name }}" disabled>
                                                        <div class="form-text">Username cannot be changed.</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Email Address</label>
                                                        <input type="email" name="email" class="form-control bg-light" value="{{ $user->email }}" readonly>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Phone Number</label>
                                                        <input type="tel" name="phone" class="form-control" value="{{ $user->phone }}" placeholder="e.g. (555) 123-4567">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Display Name</label>
                                                        <input type="text" name="name" class="form-control" value="{{ $user->name }}" placeholder="How you appear on the platform">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ── 2. Profile Details ── --}}
                                    <div class="accordion-item border mb-3 rounded" style="border-radius: 10px !important; overflow: hidden;">
                                        <h2 class="accordion-header">
                                            <button type="button"
                                                    class="accordion-button"
                                                    :class="openSection !== 'profile' && 'collapsed'"
                                                    @click="openSection = openSection === 'profile' ? null : 'profile'"
                                                    style="font-weight: 600; font-size: 1rem; color: #1a3a5c;">
                                                <i class="fas fa-id-card me-2 text-muted"></i>Profile Details
                                            </button>
                                        </h2>
                                        <div x-show="openSection === 'profile'" x-transition style="display:none;">
                                            <div class="accordion-body pt-2 pb-4">
                                                <p class="text-muted small mb-3">Your personal information and profile photo.</p>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">First Name</label>
                                                        <input type="text" name="first_name" class="form-control" value="{{ $user->first_name }}" placeholder="First name">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Last Name</label>
                                                        <input type="text" name="last_name" class="form-control" value="{{ $user->last_name }}" placeholder="Last name">
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label class="form-label fw-semibold">About Me</label>
                                                        <textarea name="bio" class="form-control" rows="4" placeholder="Tell others a little about yourself...">{{ $user->bio }}</textarea>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label class="form-label fw-semibold">Profile Photo</label>
                                                        @php
                                                            $hasUploadedPhoto = $user->avatar && !preg_match('/^\d+\.png$/', $user->avatar);
                                                        @endphp
                                                        <div class="d-flex align-items-center gap-3 mt-2">
                                                            {{-- Preview --}}
                                                            @if($hasUploadedPhoto)
                                                                <img src="{{ asset('images/avatar/' . $user->avatar) }}"
                                                                     id="profile-photo-preview"
                                                                     class="rounded-circle border"
                                                                     style="width: 72px; height: 72px; object-fit: cover; flex-shrink: 0;"
                                                                     alt="Current profile photo">
                                                            @else
                                                                <div class="rounded-circle border d-flex align-items-center justify-content-center bg-light"
                                                                     style="width: 72px; height: 72px; flex-shrink: 0; position: relative; overflow: hidden;">
                                                                    <i class="fas fa-user text-muted" style="font-size: 1.6rem;"></i>
                                                                    <img id="profile-photo-preview" src="" alt=""
                                                                         style="display:none; position:absolute; inset:0; width:100%; height:100%; object-fit:cover;">
                                                                </div>
                                                            @endif
                                                            {{-- Upload --}}
                                                            <div class="flex-grow-1">
                                                                <p class="mb-1 text-muted small">
                                                                    {{ $hasUploadedPhoto ? 'Upload a new photo to replace the current one.' : 'No profile photo uploaded yet.' }}
                                                                </p>
                                                                <input type="file" name="avatar" class="form-control form-control-sm" id="avatarUpload"
                                                                       accept="image/jpeg,image/png,image/gif">
                                                                <div class="form-text">JPG, PNG, or GIF. Max 2MB.</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ── 3. Preferences ── --}}
                                    <div class="accordion-item border mb-3 rounded" style="border-radius: 10px !important; overflow: hidden;">
                                        <h2 class="accordion-header">
                                            <button type="button"
                                                    class="accordion-button"
                                                    :class="openSection !== 'prefs' && 'collapsed'"
                                                    @click="openSection = openSection === 'prefs' ? null : 'prefs'"
                                                    style="font-weight: 600; font-size: 1rem; color: #1a3a5c;">
                                                <i class="fas fa-sliders-h me-2 text-muted"></i>Preferences
                                            </button>
                                        </h2>
                                        <div x-show="openSection === 'prefs'" x-transition style="display:none;">
                                            <div class="accordion-body pt-2 pb-4">
                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Preferred Contact Method</label>
                                                        @php $prefContact = $user->preferred_contact_method; @endphp
                                                        <div class="d-flex flex-column gap-2 mt-1">
                                                            @foreach(['Call' => 'fas fa-phone', 'Text' => 'fas fa-sms', 'Email' => 'fas fa-envelope'] as $method => $icon)
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio"
                                                                       name="preferred_contact_method"
                                                                       id="contact_{{ strtolower($method) }}"
                                                                       value="{{ $method }}"
                                                                       {{ $prefContact === $method ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="contact_{{ strtolower($method) }}">
                                                                    <i class="{{ $icon }} me-1 text-muted"></i>{{ $method }}
                                                                </label>
                                                            </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Best Time to Contact</label>
                                                        @php $bestTime = $user->best_time_to_contact; @endphp
                                                        <div class="d-flex flex-column gap-2 mt-1">
                                                            @foreach(['Morning', 'Afternoon', 'Evening', 'Anytime'] as $time)
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio"
                                                                       name="best_time_to_contact"
                                                                       id="time_{{ strtolower($time) }}"
                                                                       value="{{ $time }}"
                                                                       {{ $bestTime === $time ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="time_{{ strtolower($time) }}">{{ $time }}</label>
                                                            </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="p-3 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe;">
                                                            <i class="fas fa-bell me-2" style="color: #049399;"></i>
                                                            <strong style="color: #1a3a5c;">Email Notifications</strong>
                                                            <p class="mb-0 mt-1 text-muted small">
                                                                You will receive email notifications for important activity,
                                                                including bids, counters, and listing updates.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ── 4. Privacy & Security ── --}}
                                    <div class="accordion-item border mb-3 rounded" style="border-radius: 10px !important; overflow: hidden;">
                                        <h2 class="accordion-header">
                                            <button type="button"
                                                    class="accordion-button"
                                                    :class="openSection !== 'security' && 'collapsed'"
                                                    @click="openSection = openSection === 'security' ? null : 'security'"
                                                    style="font-weight: 600; font-size: 1rem; color: #1a3a5c;">
                                                <i class="fas fa-lock me-2 text-muted"></i>Privacy &amp; Security
                                            </button>
                                        </h2>
                                        <div x-show="openSection === 'security'" x-transition style="display:none;">
                                            <div class="accordion-body pt-2 pb-4">
                                                <p class="text-muted small mb-3">Leave these fields blank if you do not want to change your password.</p>
                                                <div class="row g-3">
                                                    <div class="col-md-12">
                                                        <label class="form-label fw-semibold">Current Password</label>
                                                        <input type="password" name="current_password" class="form-control"
                                                               placeholder="Enter your current password to change it" autocomplete="current-password">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">New Password</label>
                                                        <input type="password" name="password" class="form-control"
                                                               placeholder="New password" autocomplete="new-password">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Confirm New Password</label>
                                                        <input type="password" name="confirm_password" class="form-control"
                                                               placeholder="Re-enter new password" autocomplete="new-password">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>{{-- end Alpine accordion --}}

                                {{-- Save button --}}
                                <div class="d-flex justify-content-end mt-2 mb-2">
                                    <button type="submit" class="btn btn-lg px-5"
                                            style="background: #049399; color: #fff; border: none; border-radius: 8px; font-weight: 600;">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>

                            </form>{{-- end main form --}}

                            {{-- ── 5. Delete Account (always-visible danger zone) ── --}}
                            <div class="settings-danger-zone mt-2 mb-4 p-4" style="border: 1px solid #f5c6cb; border-radius: 10px; background: #fff5f5;">
                                <h6 style="color: #842029; font-weight: 700; margin-bottom: 0.5rem;">
                                    <i class="fas fa-trash-alt me-2"></i>Delete Account
                                </h6>
                                <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 0.25rem;">This action will deactivate your account and log you out. All your listings and bids will be deactivated.</p>
                                <p style="color: #842029; font-size: 0.9rem; font-weight: 600; margin-bottom: 1rem;">This cannot be undone. To confirm, type <code style="background:#fce4e4; padding: 1px 5px; border-radius:3px; color:#842029;">DELETE</code> below.</p>
                                <form action="{{ route('settings.delete-account') }}" method="POST" id="delete-account-form">
                                    @csrf
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <input type="text"
                                               id="delete-confirm-input"
                                               name="delete_confirm"
                                               class="form-control"
                                               placeholder="Type DELETE to confirm"
                                               autocomplete="off"
                                               style="max-width: 220px; border-color: #f5c6cb; font-family: monospace; letter-spacing: 0.05em;">
                                        <button type="submit"
                                                id="btn-delete-account"
                                                class="settings-delete-btn"
                                                disabled>
                                            <i class="fas fa-trash-alt me-2"></i>Delete My Account
                                        </button>
                                    </div>
                                    <p id="delete-hint" style="color: #aaa; font-size: 0.8rem; margin-top: 0.4rem; display: none;">
                                        Type <strong>DELETE</strong> exactly to enable the button.
                                    </p>
                                </form>
                            </div>

                        </div>{{-- end .mySettings --}}
                    </div>{{-- end .rightCol --}}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@push('styles')
<style>
/* Override global .mainDashboard button rules for settings accordion buttons */
.mySettings .accordion-button,
.mySettings .accordion-button:not(.collapsed) {
    background-color: #049399 !important;
    color: #fff !important;
    border: none !important;
    padding: 1rem 1.25rem !important;
}
.mySettings .accordion-button.collapsed {
    background-color: #049399 !important;
    color: #fff !important;
}
/* Delete My Account button */
.settings-delete-btn {
    background-color: #dc3545 !important;
    color: #ffffff !important;
    border: 1px solid #dc3545 !important;
    border-radius: 5px !important;
    padding: 8px 24px !important;
    font-size: 0.95rem !important;
    cursor: pointer !important;
}
.settings-delete-btn:hover {
    background-color: #bb2d3b !important;
    border-color: #bb2d3b !important;
    color: #fff !important;
}
/* Save Changes button */
.mySettings .btn[type="submit"] {
    background: #049399 !important;
    color: #fff !important;
    border: none !important;
    padding: 0.6rem 2rem !important;
}
/* Ensure accordion body text is always visible */
.mySettings .accordion-body p,
#deleteAccordion .accordion-body p {
    color: #495057 !important;
}
/* Accordion chevron color (white on teal) */
.mySettings .accordion-button::after {
    filter: brightness(0) invert(1);
}
#deleteAccordion .accordion-button::after {
    filter: none;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Preview uploaded photo immediately
    var avatarInput = document.getElementById('avatarUpload');
    if (avatarInput) {
        avatarInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var preview = document.getElementById('profile-photo-preview');
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // Typed DELETE confirmation for account deletion
    var deleteInput  = document.getElementById('delete-confirm-input');
    var deleteBtn    = document.getElementById('btn-delete-account');
    var deleteHint   = document.getElementById('delete-hint');
    if (deleteInput && deleteBtn) {
        deleteInput.addEventListener('input', function () {
            var matches = this.value === 'DELETE';
            deleteBtn.disabled = !matches;
            if (matches) {
                deleteBtn.style.opacity = '1';
                deleteBtn.style.cursor  = 'pointer';
                if (deleteHint) deleteHint.style.display = 'none';
            } else {
                deleteBtn.style.opacity = '0.5';
                deleteBtn.style.cursor  = 'not-allowed';
                if (deleteHint && this.value.length > 0) deleteHint.style.display = 'block';
                else if (deleteHint) deleteHint.style.display = 'none';
            }
        });
        // Initial disabled style
        deleteBtn.style.opacity = '0.5';
        deleteBtn.style.cursor  = 'not-allowed';
    }
});
</script>
@endpush
