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

                            @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            @endif
                            @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            @endif

                            {{-- ===== MAIN SETTINGS FORM ===== --}}
                            <form method="POST" action="{{ route('settings') }}" enctype="multipart/form-data">
                                @csrf

                                <div class="accordion" id="settingsAccordion">

                                    {{-- ── 1. Account Information ── --}}
                                    <div class="accordion-item border mb-3 rounded" style="border-radius: 10px !important; overflow: hidden;">
                                        <h2 class="accordion-header">
                                            <button type="button"
                                                    class="accordion-button collapsed"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#section-account"
                                                    aria-expanded="false"
                                                    style="font-weight: 600; font-size: 1rem; color: #1a3a5c;">
                                                <i class="fas fa-user-circle me-2 text-muted"></i>Account Information
                                            </button>
                                        </h2>
                                        <div id="section-account" class="accordion-collapse collapse">
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
                                                    class="accordion-button collapsed"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#section-profile"
                                                    aria-expanded="false"
                                                    style="font-weight: 600; font-size: 1rem; color: #1a3a5c;">
                                                <i class="fas fa-id-card me-2 text-muted"></i>Profile Details
                                            </button>
                                        </h2>
                                        <div id="section-profile" class="accordion-collapse collapse">
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
                                                        <div class="d-flex align-items-start gap-4 mt-1 flex-wrap">
                                                            {{-- Current photo preview --}}
                                                            <div>
                                                                @php
                                                                    $defaultAvatars = array_map(fn($n) => "$n.png", range(1, 30));
                                                                    $isDefaultAvatar = in_array($user->avatar, $defaultAvatars) || !$user->avatar;
                                                                @endphp
                                                                <img src="{{ asset('images/avatar/' . ($user->avatar ?: '1.png')) }}"
                                                                     class="rounded-circle border"
                                                                     style="width: 80px; height: 80px; object-fit: cover;"
                                                                     id="current-avatar-preview"
                                                                     alt="Current photo">
                                                                <div class="form-text text-center mt-1">Current</div>
                                                            </div>
                                                            {{-- Upload new photo --}}
                                                            <div class="flex-grow-1">
                                                                <label class="form-label text-muted small">Upload a new photo</label>
                                                                <input type="file" name="avatar" class="form-control form-control-sm" id="avatarUpload"
                                                                       accept="image/jpeg,image/png,image/gif">
                                                                <div class="form-text">JPG, PNG, or GIF. Max 2MB.</div>
                                                            </div>
                                                        </div>
                                                        {{-- Default avatar picker --}}
                                                        <div class="mt-3">
                                                            <div class="form-text mb-2">Or choose a default avatar:</div>
                                                            <div class="d-flex flex-wrap gap-2" style="max-height: 160px; overflow-y: auto;">
                                                                @for ($i = 1; $i <= 30; $i++)
                                                                <label for="av{{ $i }}" class="mb-0" title="Avatar {{ $i }}"
                                                                       style="cursor: pointer; opacity: {{ ($i.'.png' == $user->avatar) ? '1' : '0.6' }}; transition: opacity 0.2s;"
                                                                       onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=this.querySelector('input').checked?'1':'0.6'">
                                                                    <img src="{{ asset('/images/avatar/'.$i.'.png') }}"
                                                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%; border: 2px solid {{ ($i.'.png' == $user->avatar) ? '#049399' : 'transparent' }};"
                                                                         alt="Avatar {{ $i }}">
                                                                    <input class="user-avatar d-none" id="av{{ $i }}" type="radio"
                                                                           value="{{ $i }}.png" name="myavatar"
                                                                           {{ $i.'.png' == $user->avatar ? 'checked' : '' }}>
                                                                </label>
                                                                @endfor
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
                                                    class="accordion-button collapsed"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#section-prefs"
                                                    aria-expanded="false"
                                                    style="font-weight: 600; font-size: 1rem; color: #1a3a5c;">
                                                <i class="fas fa-sliders-h me-2 text-muted"></i>Preferences
                                            </button>
                                        </h2>
                                        <div id="section-prefs" class="accordion-collapse collapse">
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
                                                    class="accordion-button collapsed"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#section-security"
                                                    aria-expanded="false"
                                                    style="font-weight: 600; font-size: 1rem; color: #1a3a5c;">
                                                <i class="fas fa-lock me-2 text-muted"></i>Privacy &amp; Security
                                            </button>
                                        </h2>
                                        <div id="section-security" class="accordion-collapse collapse">
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

                                </div>{{-- end accordion --}}

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
                                <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 0.5rem;">We're really sorry to see you go. Deleting your account is permanent and cannot be undone.</p>
                                <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 1rem;">All your listings, bids, and account data will be deactivated.</p>
                                <form action="{{ route('settings.delete-account') }}" method="POST" id="delete-account-form" style="display: inline;">
                                    @csrf
                                    <button type="button" id="btn-delete-account"
                                            class="settings-delete-btn"
                                            onclick="if(confirm('Are you sure you want to permanently delete your account? This cannot be undone.')) { document.getElementById('delete-account-form').submit(); }">
                                        <i class="fas fa-trash-alt me-2"></i>Delete My Account
                                    </button>
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
                    var preview = document.getElementById('current-avatar-preview');
                    if (preview) { preview.src = e.target.result; }
                };
                reader.readAsDataURL(this.files[0]);
                // Deselect any chosen default avatar
                document.querySelectorAll('.user-avatar').forEach(function(r) { r.checked = false; });
            }
        });
    }

    // Highlight selected default avatar
    document.querySelectorAll('.user-avatar').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('label[for^="av"]').forEach(function (lbl) {
                var img = lbl.querySelector('img');
                if (img) img.style.border = '2px solid transparent';
                lbl.style.opacity = '0.6';
            });
            var selectedLabel = document.querySelector('label[for="' + radio.id + '"]');
            if (selectedLabel) {
                var img = selectedLabel.querySelector('img');
                if (img) img.style.border = '2px solid #049399';
                selectedLabel.style.opacity = '1';
            }
        });
    });
});
</script>
@endpush
