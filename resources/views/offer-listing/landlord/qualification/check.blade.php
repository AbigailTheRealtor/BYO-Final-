@extends('layouts.main')

@php
    $str = function (string $key) use ($meta): string {
        $v = $meta[$key] ?? '';
        return is_array($v) ? implode(', ', array_map(fn($e) => is_array($e) ? json_encode($e) : (string)$e, $v)) : (string) $v;
    };

    $addrParts = array_filter([
        $meta['address'] ?? null,
        !empty($meta['unit_number']) ? $meta['unit_number'] : null,
        $meta['property_city'] ?? null,
    ]);
    $addrState = trim($meta['property_state'] ?? '');
    $addrZip   = trim($meta['property_zip'] ?? $meta['zip_code'] ?? '');
    $stateZip  = trim($addrState . ($addrState && $addrZip ? ' ' : '') . $addrZip);
    if ($stateZip) $addrParts[] = $stateZip;
    $fullAddress = implode(', ', array_filter($addrParts));

    $pageTitle = ($str('listing_title') ?: $auction->title) ?: ($fullAddress ?: 'Rental Property');

    /* ── Applicant Requirements display helpers ── */
    $creditScore   = $str('min_credit_score');
    $creditDisplay = null;
    if ($creditScore) {
        if ($creditScore === 'Other' && $str('custom_credit_score_requirement')) {
            $creditDisplay = $str('custom_credit_score_requirement');
        } elseif ($creditScore !== 'Other') {
            $creditDisplay = $creditScore;
        }
    }

    $creditFlexibility = $str('credit_score_flexibility');

    $incomeMethod  = $str('income_qualification_method');
    $incomeDisplay = null;
    if ($incomeMethod && strtolower($incomeMethod) !== 'no requirement') {
        if ($incomeMethod === 'Fixed Monthly Income' && $str('min_monthly_income_fixed')) {
            $incomeDisplay = 'Fixed: $' . number_format((float)preg_replace('/[^0-9.]/', '', $str('min_monthly_income_fixed')), 0) . '/mo';
        } elseif ($incomeMethod === 'Other' && $str('custom_income_requirement')) {
            $incomeDisplay = $str('custom_income_requirement');
        } else {
            $incomeDisplay = $incomeMethod;
        }
    }

    $empReq    = $str('employment_requirement');
    $empDisplay = ($empReq && strtolower($empReq) !== 'no requirement')
        ? ($empReq === 'Other' && $str('custom_employment_requirement') ? $str('custom_employment_requirement') : $empReq)
        : null;

    $evicReq    = $str('eviction_history_requirement');
    $evicDisplay = ($evicReq && strtolower($evicReq) !== 'no requirement')
        ? ($evicReq === 'Other' && $str('custom_eviction_requirement') ? $str('custom_eviction_requirement') : $evicReq)
        : null;

    $bankReq    = $str('bankruptcy_requirement');
    $bankDisplay = ($bankReq && strtolower($bankReq) !== 'no requirement')
        ? ($bankReq === 'Other' && $str('custom_bankruptcy_requirement') ? $str('custom_bankruptcy_requirement') : $bankReq)
        : null;

    $petReq    = $str('pet_policy_requirement');
    $petDisplay = null;
    if ($petReq && strtolower($petReq) !== 'no requirement') {
        $petDisplay = ($petReq === 'Other' && $str('custom_pet_policy_requirement')) ? $str('custom_pet_policy_requirement') : $petReq;
    }

    $smokeReq    = $str('smoking_policy_requirement');
    $smokeDisplay = null;
    if ($smokeReq && strtolower($smokeReq) !== 'no requirement') {
        $smokeDisplay = ($smokeReq === 'Other' && $str('custom_smoking_policy_requirement')) ? $str('custom_smoking_policy_requirement') : $smokeReq;
    }

    $criminalReq    = $str('criminal_background_requirement');
    $criminalDisplay = null;
    if ($criminalReq && strtolower($criminalReq) !== 'no requirement') {
        $criminalDisplay = ($criminalReq === 'Other' && $str('custom_criminal_background_requirement')) ? $str('custom_criminal_background_requirement') : $criminalReq;
    }

    $refReq    = $str('reference_requirement');
    $refDisplay = ($refReq && strtolower($refReq) !== 'no requirement')
        ? (($refReq === 'Other' && $str('custom_reference_requirement')) ? $str('custom_reference_requirement') : $refReq)
        : null;

    $empVerifReq   = $str('employment_verification_requirement');
    $incVerifReq   = $str('income_verification_requirement');
    $moveInPref    = $str('preferred_move_in_timeframe');
    $moveInDisplay = null;
    if ($moveInPref && strtolower($moveInPref) !== 'no preference') {
        $moveInDisplay = ($moveInPref === 'Other' && $str('custom_preferred_move_in_timeframe')) ? $str('custom_preferred_move_in_timeframe') : $moveInPref;
    }

    $minIncome      = $str('min_income_requirement');
    $maxOccupants   = $str('number_of_occupants_allowed') ?: $str('number_occupant');
    $approvalConds  = $str('landlord_approval_conditions');

    $hasRequirements = $creditDisplay || $incomeDisplay || $empDisplay || $evicDisplay || $bankDisplay
        || $minIncome || $maxOccupants || $approvalConds || $petDisplay || $smokeDisplay
        || $criminalDisplay || $refDisplay || $empVerifReq || $incVerifReq || $moveInDisplay
        || $creditFlexibility;

    /* ── Estimated Utility Costs (mirrors public card logic) ── */
    $hasUtils = $str('est_water_sewer_trash') || $str('est_electric') || $str('est_internet') || $str('est_cable');
@endphp

@push('styles')
<style>
.rqc-page { max-width: 860px; margin: 0 auto; }
.rqc-req-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}
.rqc-req-card .rqc-req-title {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.07em; color: #64748b;
    margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e2e8f0;
}
.rqc-req-row { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem; }
.rqc-req-label { color: #64748b; font-weight: 600; min-width: 200px; flex-shrink: 0; }
.rqc-req-value { color: #1e293b; }
.rqc-form-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}
.rqc-form-card .rqc-form-title {
    font-size: 1rem; font-weight: 700; color: #1e293b;
    margin-bottom: 0.25rem;
}
.rqc-form-card .rqc-form-subtitle {
    font-size: 0.84rem; color: #64748b; margin-bottom: 1.25rem;
}
.rqc-neutral-note {
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.5rem;
    padding: 0.75rem 1rem; font-size: 0.82rem; color: #92400e;
    margin-bottom: 1.5rem;
}
.rqc-success-banner {
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.75rem;
    padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;
}
.rqc-success-banner .rqc-success-icon { font-size: 2rem; color: #15803d; margin-bottom: 0.5rem; }
.rqc-success-banner .rqc-success-title { font-size: 1.05rem; font-weight: 700; color: #14532d; margin-bottom: 0.4rem; }
.rqc-success-banner .rqc-success-msg { font-size: 0.88rem; color: #166534; }
</style>
@endpush

@section('content')
<div class="container py-4 rqc-page">

    <div class="mb-3">
        <a href="{{ route('offer.listing.landlord.view', ['id' => $auction->id]) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to listing
        </a>
    </div>

    <h2 class="fw-bold mb-1" style="color:#1e293b;">Check rental qualification</h2>
    @if($fullAddress)
        <p class="text-muted mb-3"><i class="fa-solid fa-location-dot me-1" style="color:#0f766e;"></i>{{ $fullAddress }}</p>
    @endif

    {{-- ── Success confirmation ── --}}
    @if(session('qualification_submitted'))
    <div class="rqc-success-banner">
        <div class="rqc-success-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="rqc-success-title">Your rental qualification information has been saved.</div>
        <div class="rqc-success-msg">This is not an approval or denial. The landlord will review the information you submitted.</div>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ── Applicant Requirements (read-only) ── --}}
    @if($hasRequirements)
    <div class="rqc-req-card">
        <div class="rqc-req-title"><i class="fa-solid fa-user-check me-1"></i>Landlord's applicant requirements</div>
        @if($minIncome)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Minimum monthly income</span>
            <span class="rqc-req-value">${{ number_format((float)preg_replace('/[^0-9.]/', '', $minIncome), 0) }}/mo</span>
        </div>
        @endif
        @if($maxOccupants)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Max occupants allowed</span>
            <span class="rqc-req-value">{{ $maxOccupants }}</span>
        </div>
        @endif
        @if($approvalConds)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Approval conditions</span>
            <span class="rqc-req-value">{{ $approvalConds }}</span>
        </div>
        @endif
        @if($creditDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Minimum credit score</span>
            <span class="rqc-req-value">{{ $creditDisplay }}</span>
        </div>
        @endif
        @if($creditFlexibility)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Credit score flexibility</span>
            <span class="rqc-req-value">{{ $creditFlexibility }}</span>
        </div>
        @endif
        @if($incomeDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Income qualification</span>
            <span class="rqc-req-value">{{ $incomeDisplay }}</span>
        </div>
        @endif
        @if($empDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Employment requirement</span>
            <span class="rqc-req-value">{{ $empDisplay }}</span>
        </div>
        @endif
        @if($evicDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Eviction history</span>
            <span class="rqc-req-value">{{ $evicDisplay }}</span>
        </div>
        @endif
        @if($bankDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Bankruptcy requirement</span>
            <span class="rqc-req-value">{{ $bankDisplay }}</span>
        </div>
        @endif
        @if($petDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Pet policy</span>
            <span class="rqc-req-value">{{ $petDisplay }}</span>
        </div>
        @endif
        @if(!empty($str('pet_restrictions')) && $petDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Pet restrictions</span>
            <span class="rqc-req-value">{{ $str('pet_restrictions') }}</span>
        </div>
        @endif
        @if($smokeDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Smoking policy</span>
            <span class="rqc-req-value">{{ $smokeDisplay }}</span>
        </div>
        @endif
        @if($criminalDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Criminal background</span>
            <span class="rqc-req-value">{{ $criminalDisplay }}</span>
        </div>
        @endif
        @if($refDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Prior landlord reference</span>
            <span class="rqc-req-value">{{ $refDisplay }}</span>
        </div>
        @endif
        @if($empVerifReq && strtolower($empVerifReq) !== 'no requirement')
        <div class="rqc-req-row">
            <span class="rqc-req-label">Employment verification</span>
            <span class="rqc-req-value">{{ $empVerifReq }}</span>
        </div>
        @endif
        @if($incVerifReq && strtolower($incVerifReq) !== 'no requirement')
        <div class="rqc-req-row">
            <span class="rqc-req-label">Income verification</span>
            <span class="rqc-req-value">{{ $incVerifReq }}</span>
        </div>
        @endif
        @if($moveInDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Preferred move-in</span>
            <span class="rqc-req-value">{{ $moveInDisplay }}</span>
        </div>
        @endif
        @if($hasUtils)
        <hr style="border-color:#e2e8f0;margin:0.75rem 0;">
        <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:0.5rem;">Estimated monthly utility costs</div>
        <div class="d-flex flex-wrap gap-3">
            @if($str('est_water_sewer_trash'))
            <div class="rqc-req-row" style="margin-bottom:0;">
                <span class="rqc-req-label" style="min-width:160px;">Water / sewer / trash</span>
                <span class="rqc-req-value">${{ number_format((float)preg_replace('/[^0-9.]/', '', $str('est_water_sewer_trash')), 0) }}/mo</span>
            </div>
            @endif
            @if($str('est_electric'))
            <div class="rqc-req-row" style="margin-bottom:0;">
                <span class="rqc-req-label" style="min-width:160px;">Electric</span>
                <span class="rqc-req-value">${{ number_format((float)preg_replace('/[^0-9.]/', '', $str('est_electric')), 0) }}/mo</span>
            </div>
            @endif
            @if($str('est_internet'))
            <div class="rqc-req-row" style="margin-bottom:0;">
                <span class="rqc-req-label" style="min-width:160px;">Internet</span>
                <span class="rqc-req-value">${{ number_format((float)preg_replace('/[^0-9.]/', '', $str('est_internet')), 0) }}/mo</span>
            </div>
            @endif
            @if($str('est_cable'))
            <div class="rqc-req-row" style="margin-bottom:0;">
                <span class="rqc-req-label" style="min-width:160px;">Cable</span>
                <span class="rqc-req-value">${{ number_format((float)preg_replace('/[^0-9.]/', '', $str('est_cable')), 0) }}/mo</span>
            </div>
            @endif
        </div>
        @endif
    </div>
    @else
    <div class="alert alert-secondary mb-3" style="font-size:.88rem;">
        <i class="fa-solid fa-circle-info me-1"></i>
        The landlord has not specified detailed applicant requirements for this listing.
    </div>
    @endif

    {{-- ── Intake form ── --}}
    @if(!session('qualification_submitted'))
    <div class="rqc-form-card">
        <div class="rqc-form-title"><i class="fa-solid fa-clipboard-list me-2" style="color:#0f766e;"></i>Your qualification information</div>
        <div class="rqc-form-subtitle">Fill out the fields below so the landlord can review your rental qualification information.</div>

        <div class="rqc-neutral-note">
            <i class="fa-solid fa-circle-info me-1"></i>
            Submitting this form <strong>saves your qualification information only</strong>. It is not an approval, denial, or commitment of any kind.
        </div>

        <form method="POST" action="{{ route('offer.listing.landlord.qualification.store', ['listing' => $auction->id]) }}" novalidate
              x-data="{
                employmentStatus: '{{ old('employment_status') }}',
                hasPets: '{{ old('has_pets') }}',
                criminalBg: '{{ old('criminal_background') }}'
              }">
            @csrf

            {{-- Contact Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Contact information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_name">Full name <span class="text-danger">*</span></label>
                    <input type="text" id="rqc_name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', auth()->user()?->name) }}"
                           placeholder="Enter your full name" required maxlength="191">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_email">Email address <span class="text-danger">*</span></label>
                    <input type="email" id="rqc_email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', auth()->user()?->email) }}"
                           placeholder="Enter your email address" required maxlength="191">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_phone">Phone number</label>
                    <input type="tel" id="rqc_phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone') }}"
                           placeholder="e.g., (555) 867-5309" maxlength="64">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Financial Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Financial information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_credit_score">Estimated credit score</label>
                    <select id="rqc_credit_score" name="estimated_credit_score"
                            class="form-control @error('estimated_credit_score') is-invalid @enderror">
                        <option value="">Select a range</option>
                        <option value="Below 500" @selected(old('estimated_credit_score') === 'Below 500')>Below 500</option>
                        <option value="500–549" @selected(old('estimated_credit_score') === '500–549')>500–549</option>
                        <option value="550–599" @selected(old('estimated_credit_score') === '550–599')>550–599</option>
                        <option value="600–649" @selected(old('estimated_credit_score') === '600–649')>600–649</option>
                        <option value="650–699" @selected(old('estimated_credit_score') === '650–699')>650–699</option>
                        <option value="700–749" @selected(old('estimated_credit_score') === '700–749')>700–749</option>
                        <option value="750–799" @selected(old('estimated_credit_score') === '750–799')>750–799</option>
                        <option value="800+" @selected(old('estimated_credit_score') === '800+')>800+</option>
                        <option value="Prefer not to disclose" @selected(old('estimated_credit_score') === 'Prefer not to disclose')>Prefer not to disclose</option>
                    </select>
                    @error('estimated_credit_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_income">Monthly household income</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" id="rqc_income" name="monthly_household_income"
                               class="form-control @error('monthly_household_income') is-invalid @enderror"
                               value="{{ old('monthly_household_income') }}"
                               placeholder="e.g., 5000" maxlength="50">
                        @error('monthly_household_income')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">Combined monthly income for all applicants.</div>
                </div>
                <div class="col-md-6" x-data>
                    <label class="form-label fw-semibold" for="rqc_employment">Employment status</label>
                    <select id="rqc_employment" name="employment_status"
                            class="form-control @error('employment_status') is-invalid @enderror"
                            x-model="employmentStatus">
                        <option value="">Select</option>
                        <option value="Employed full-time" @selected(old('employment_status') === 'Employed full-time')>Employed full-time</option>
                        <option value="Employed part-time" @selected(old('employment_status') === 'Employed part-time')>Employed part-time</option>
                        <option value="Self-employed" @selected(old('employment_status') === 'Self-employed')>Self-employed</option>
                        <option value="Independent contractor" @selected(old('employment_status') === 'Independent contractor')>Independent contractor</option>
                        <option value="Retired" @selected(old('employment_status') === 'Retired')>Retired</option>
                        <option value="Student" @selected(old('employment_status') === 'Student')>Student</option>
                        <option value="Not currently employed" @selected(old('employment_status') === 'Not currently employed')>Not currently employed</option>
                        <option value="Other" @selected(old('employment_status') === 'Other')>Other</option>
                    </select>
                    @error('employment_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div x-show="employmentStatus === 'Other'" x-cloak class="mt-2">
                        <input type="text" name="employment_status_other" class="form-control"
                               value="{{ old('employment_status_other') }}"
                               placeholder="Enter employment status (e.g., Independent contractor, Seasonal worker, Gig worker)"
                               maxlength="200">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_income_source">Income source</label>
                    <select id="rqc_income_source" name="income_source"
                            class="form-control @error('income_source') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="Employed full-time" @selected(old('income_source') === 'Employed full-time')>Employed full-time</option>
                        <option value="Employed part-time" @selected(old('income_source') === 'Employed part-time')>Employed part-time</option>
                        <option value="Self-employed" @selected(old('income_source') === 'Self-employed')>Self-employed</option>
                        <option value="Independent contractor" @selected(old('income_source') === 'Independent contractor')>Independent contractor</option>
                        <option value="Retired" @selected(old('income_source') === 'Retired')>Retired</option>
                        <option value="Student" @selected(old('income_source') === 'Student')>Student</option>
                        <option value="Not currently employed" @selected(old('income_source') === 'Not currently employed')>Not currently employed</option>
                        <option value="Other" @selected(old('income_source') === 'Other')>Other</option>
                    </select>
                    @error('income_source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Household Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Household information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_occupants">Number of occupants</label>
                    <input type="number" id="rqc_occupants" name="number_of_occupants"
                           class="form-control @error('number_of_occupants') is-invalid @enderror"
                           value="{{ old('number_of_occupants') }}"
                           placeholder="e.g., 2" min="1" max="50">
                    @error('number_of_occupants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Total number of people who will occupy the unit.</div>
                </div>
                <div class="col-md-6" x-data>
                    <label class="form-label fw-semibold" for="rqc_pets">Pets</label>
                    <select id="rqc_pets" name="has_pets"
                            class="form-control @error('has_pets') is-invalid @enderror"
                            x-model="hasPets">
                        <option value="">Select</option>
                        <option value="Yes" @selected(old('has_pets') === 'Yes')>Yes</option>
                        <option value="No" @selected(old('has_pets') === 'No')>No</option>
                    </select>
                    @error('has_pets')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div x-show="hasPets === 'Yes'" x-cloak class="mt-2">
                        <textarea name="pet_details" rows="2" class="form-control"
                                  placeholder="Enter pet details (e.g., 1 golden retriever, 2 indoor cats)"
                                  maxlength="500">{{ old('pet_details') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Lifestyle Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Lifestyle information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_smoking">Smoking</label>
                    <select id="rqc_smoking" name="smoking"
                            class="form-control @error('smoking') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="Non-smoker" @selected(old('smoking') === 'Non-smoker')>Non-smoker</option>
                        <option value="Smoker" @selected(old('smoking') === 'Smoker')>Smoker</option>
                    </select>
                    @error('smoking')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Rental History --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Rental history</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_eviction">Eviction history</label>
                    <select id="rqc_eviction" name="eviction_history"
                            class="form-control @error('eviction_history') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="No prior evictions" @selected(old('eviction_history') === 'No prior evictions')>No prior evictions</option>
                        <option value="Eviction more than 7 years ago" @selected(old('eviction_history') === 'Eviction more than 7 years ago')>Eviction more than 7 years ago</option>
                        <option value="Eviction within last 7 years" @selected(old('eviction_history') === 'Eviction within last 7 years')>Eviction within last 7 years</option>
                        <option value="Eviction within last 3 years" @selected(old('eviction_history') === 'Eviction within last 3 years')>Eviction within last 3 years</option>
                        <option value="Prefer not to say" @selected(old('eviction_history') === 'Prefer not to say')>Prefer not to say</option>
                    </select>
                    @error('eviction_history')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_bankruptcy">Bankruptcy history</label>
                    <select id="rqc_bankruptcy" name="bankruptcy_history"
                            class="form-control @error('bankruptcy_history') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="No bankruptcy" @selected(old('bankruptcy_history') === 'No bankruptcy')>No bankruptcy</option>
                        <option value="Bankruptcy discharged more than 5 years ago" @selected(old('bankruptcy_history') === 'Bankruptcy discharged more than 5 years ago')>Bankruptcy discharged more than 5 years ago</option>
                        <option value="Bankruptcy discharged within last 5 years" @selected(old('bankruptcy_history') === 'Bankruptcy discharged within last 5 years')>Bankruptcy discharged within last 5 years</option>
                        <option value="Active bankruptcy" @selected(old('bankruptcy_history') === 'Active bankruptcy')>Active bankruptcy</option>
                        <option value="Prefer not to say" @selected(old('bankruptcy_history') === 'Prefer not to say')>Prefer not to say</option>
                    </select>
                    @error('bankruptcy_history')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Background Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Background information</h6>
            <div class="row g-3 mb-4" x-data>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_criminal">Criminal background</label>
                    <select id="rqc_criminal" name="criminal_background"
                            class="form-control @error('criminal_background') is-invalid @enderror"
                            x-model="criminalBg">
                        <option value="">Select</option>
                        <option value="No criminal background" @selected(old('criminal_background') === 'No criminal background')>No criminal background</option>
                        <option value="Criminal background disclosed" @selected(old('criminal_background') === 'Criminal background disclosed')>Criminal background disclosed</option>
                        <option value="Prefer to discuss" @selected(old('criminal_background') === 'Prefer to discuss')>Prefer to discuss</option>
                        <option value="Other" @selected(old('criminal_background') === 'Other')>Other</option>
                    </select>
                    @error('criminal_background')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div x-show="criminalBg === 'Criminal background disclosed' || criminalBg === 'Other'" x-cloak class="mt-2">
                        <input type="text" name="criminal_background_other" class="form-control"
                               value="{{ old('criminal_background_other') }}"
                               placeholder="Enter background details (e.g., Non-violent misdemeanor, Record expunged)"
                               maxlength="500">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_reference">Prior landlord reference available</label>
                    <select id="rqc_reference" name="landlord_reference_available"
                            class="form-control @error('landlord_reference_available') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="Yes" @selected(old('landlord_reference_available') === 'Yes')>Yes</option>
                        <option value="No" @selected(old('landlord_reference_available') === 'No')>No</option>
                        <option value="Not applicable" @selected(old('landlord_reference_available') === 'Not applicable')>Not applicable</option>
                    </select>
                    @error('landlord_reference_available')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_emp_verif">Employment verification available</label>
                    <select id="rqc_emp_verif" name="employment_verification_available"
                            class="form-control @error('employment_verification_available') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="Yes" @selected(old('employment_verification_available') === 'Yes')>Yes</option>
                        <option value="No" @selected(old('employment_verification_available') === 'No')>No</option>
                    </select>
                    @error('employment_verification_available')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_inc_verif">Income verification available</label>
                    <select id="rqc_inc_verif" name="income_verification_available"
                            class="form-control @error('income_verification_available') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="Yes" @selected(old('income_verification_available') === 'Yes')>Yes</option>
                        <option value="No" @selected(old('income_verification_available') === 'No')>No</option>
                    </select>
                    @error('income_verification_available')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="consent_to_screening" id="rqc_consent"
                               value="1" {{ old('consent_to_screening') ? 'checked' : '' }}>
                        <label class="form-check-label" for="rqc_consent">
                            I consent to a background and credit screening as part of the rental application process.
                        </label>
                    </div>
                </div>
            </div>

            {{-- Move Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Move information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_move_in_date">Desired move-in date</label>
                    <input type="date" id="rqc_move_in_date" name="desired_move_in_date"
                           class="form-control @error('desired_move_in_date') is-invalid @enderror"
                           value="{{ old('desired_move_in_date') }}">
                    @error('desired_move_in_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Applicant Profile --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">About yourself</h6>
            <div class="mb-4">
                <label class="form-label fw-semibold" for="rqc_profile">Tell the landlord about yourself <span class="text-muted fw-normal">(Optional)</span></label>
                <textarea id="rqc_profile" name="applicant_profile" rows="4"
                          class="form-control @error('applicant_profile') is-invalid @enderror"
                          placeholder="Enter a brief introduction — employment history, rental history, reasons for moving, etc."
                          maxlength="3000">{{ old('applicant_profile') }}</textarea>
                @error('applicant_profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Maximum 3,000 characters.</div>
            </div>

            {{-- Additional Notes --}}
            <div class="mb-4">
                <label class="form-label fw-semibold" for="rqc_notes">Additional notes <span class="text-muted fw-normal">(Optional)</span></label>
                <textarea id="rqc_notes" name="additional_notes" rows="3"
                          class="form-control @error('additional_notes') is-invalid @enderror"
                          placeholder="Any additional context you would like to share with the landlord…"
                          maxlength="3000">{{ old('additional_notes') }}</textarea>
                @error('additional_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Maximum 3,000 characters.</div>
            </div>

            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="submit" class="btn btn-primary" style="background-color:#2563eb;border-color:#2563eb;">
                    <i class="fa-solid fa-paper-plane me-1"></i>Submit qualification information
                </button>
                <a href="{{ route('offer.listing.landlord.view', ['id' => $auction->id]) }}"
                   class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
    @else
    <div class="text-center mt-2">
        <a href="{{ route('offer.listing.landlord.view', ['id' => $auction->id]) }}" class="btn btn-outline-primary">
            <i class="fa-solid fa-arrow-left me-1"></i>Return to listing
        </a>
    </div>
    @endif

</div>
@endsection
