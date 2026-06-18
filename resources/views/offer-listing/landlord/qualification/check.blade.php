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

    $incomeMethod  = $str('income_qualification_method');
    $incomeDisplay = null;
    if ($incomeMethod && $incomeMethod !== 'No Requirement') {
        if ($incomeMethod === 'Fixed Monthly Income' && $str('min_monthly_income_fixed')) {
            $incomeDisplay = 'Fixed: $' . number_format((float)preg_replace('/[^0-9.]/', '', $str('min_monthly_income_fixed')), 0) . '/mo';
        } elseif ($incomeMethod === 'Other' && $str('custom_income_requirement')) {
            $incomeDisplay = $str('custom_income_requirement');
        } else {
            $incomeDisplay = $incomeMethod;
        }
    }

    $empReq    = $str('employment_requirement');
    $empDisplay = ($empReq && $empReq !== 'No Requirement')
        ? ($empReq === 'Other' && $str('custom_employment_requirement') ? $str('custom_employment_requirement') : $empReq)
        : null;

    $evicReq    = $str('eviction_history_requirement');
    $evicDisplay = ($evicReq && $evicReq !== 'No Requirement')
        ? ($evicReq === 'Other' && $str('custom_eviction_requirement') ? $str('custom_eviction_requirement') : $evicReq)
        : null;

    $bankReq    = $str('bankruptcy_requirement');
    $bankDisplay = ($bankReq && $bankReq !== 'No Requirement')
        ? ($bankReq === 'Other' && $str('custom_bankruptcy_requirement') ? $str('custom_bankruptcy_requirement') : $bankReq)
        : null;

    $minIncome      = $str('min_income_requirement');
    $maxOccupants   = $str('number_of_occupants_allowed') ?: $str('number_occupant');
    $approvalConds  = $str('landlord_approval_conditions');

    $hasRequirements = $creditDisplay || $incomeDisplay || $empDisplay || $evicDisplay || $bankDisplay
        || $minIncome || $maxOccupants || $approvalConds;

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
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Listing
        </a>
    </div>

    <h2 class="fw-bold mb-1" style="color:#1e293b;">Check Rental Qualification</h2>
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
        <div class="rqc-req-title"><i class="fa-solid fa-user-check me-1"></i>Landlord's Applicant Requirements</div>
        @if($minIncome)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Minimum Monthly Income</span>
            <span class="rqc-req-value">${{ number_format((float)preg_replace('/[^0-9.]/', '', $minIncome), 0) }}/mo</span>
        </div>
        @endif
        @if($maxOccupants)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Max Occupants Allowed</span>
            <span class="rqc-req-value">{{ $maxOccupants }}</span>
        </div>
        @endif
        @if($approvalConds)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Approval Conditions</span>
            <span class="rqc-req-value">{{ $approvalConds }}</span>
        </div>
        @endif
        @if($creditDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Minimum Credit Score</span>
            <span class="rqc-req-value">{{ $creditDisplay }}</span>
        </div>
        @endif
        @if($incomeDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Income Qualification</span>
            <span class="rqc-req-value">{{ $incomeDisplay }}</span>
        </div>
        @endif
        @if($empDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Employment Requirement</span>
            <span class="rqc-req-value">{{ $empDisplay }}</span>
        </div>
        @endif
        @if($evicDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Eviction History</span>
            <span class="rqc-req-value">{{ $evicDisplay }}</span>
        </div>
        @endif
        @if($bankDisplay)
        <div class="rqc-req-row">
            <span class="rqc-req-label">Bankruptcy Requirement</span>
            <span class="rqc-req-value">{{ $bankDisplay }}</span>
        </div>
        @endif
        @if($hasUtils)
        <hr style="border-color:#e2e8f0;margin:0.75rem 0;">
        <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:0.5rem;">Estimated Monthly Utility Costs</div>
        <div class="d-flex flex-wrap gap-3">
            @if($str('est_water_sewer_trash'))
            <div class="rqc-req-row" style="margin-bottom:0;">
                <span class="rqc-req-label" style="min-width:160px;">Water / Sewer / Trash</span>
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
        <div class="rqc-form-title"><i class="fa-solid fa-clipboard-list me-2" style="color:#0f766e;"></i>Your Qualification Information</div>
        <div class="rqc-form-subtitle">Fill out the fields below so the landlord can review your rental qualification information.</div>

        <div class="rqc-neutral-note">
            <i class="fa-solid fa-circle-info me-1"></i>
            Submitting this form <strong>saves your qualification information only</strong>. It is not an approval, denial, or commitment of any kind.
        </div>

        <form method="POST" action="{{ route('offer.listing.landlord.qualification.store', ['listing' => $auction->id]) }}" novalidate>
            @csrf

            {{-- Contact Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Contact Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_name">Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="rqc_name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', auth()->user()?->name) }}"
                           placeholder="Enter your full name" required maxlength="191">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_email">Email Address <span class="text-danger">*</span></label>
                    <input type="email" id="rqc_email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', auth()->user()?->email) }}"
                           placeholder="Enter your email address" required maxlength="191">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_phone">Phone Number</label>
                    <input type="tel" id="rqc_phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone') }}"
                           placeholder="e.g., (555) 867-5309" maxlength="64">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Financial Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Financial Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_credit_score">Estimated Credit Score / Range</label>
                    <select id="rqc_credit_score" name="estimated_credit_score"
                            class="form-control @error('estimated_credit_score') is-invalid @enderror">
                        <option value="">Select a range</option>
                        <option value="Below 580" @selected(old('estimated_credit_score') === 'Below 580')>Below 580</option>
                        <option value="580–619" @selected(old('estimated_credit_score') === '580–619')>580 – 619</option>
                        <option value="620–659" @selected(old('estimated_credit_score') === '620–659')>620 – 659</option>
                        <option value="660–699" @selected(old('estimated_credit_score') === '660–699')>660 – 699</option>
                        <option value="700–739" @selected(old('estimated_credit_score') === '700–739')>700 – 739</option>
                        <option value="740–779" @selected(old('estimated_credit_score') === '740–779')>740 – 779</option>
                        <option value="780+" @selected(old('estimated_credit_score') === '780+')>780+</option>
                        <option value="Unsure" @selected(old('estimated_credit_score') === 'Unsure')>Unsure</option>
                    </select>
                    @error('estimated_credit_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_income">Monthly Household Income</label>
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
            </div>

            {{-- Background Information --}}
            <h6 class="fw-semibold border-bottom pb-2 mb-3" style="font-size:.9rem;color:#334155;">Background Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_employment">Employment Status</label>
                    <select id="rqc_employment" name="employment_status"
                            class="form-control @error('employment_status') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="Employed Full-Time" @selected(old('employment_status') === 'Employed Full-Time')>Employed Full-Time</option>
                        <option value="Employed Part-Time" @selected(old('employment_status') === 'Employed Part-Time')>Employed Part-Time</option>
                        <option value="Self-Employed" @selected(old('employment_status') === 'Self-Employed')>Self-Employed</option>
                        <option value="Retired" @selected(old('employment_status') === 'Retired')>Retired</option>
                        <option value="Student" @selected(old('employment_status') === 'Student')>Student</option>
                        <option value="Not Currently Employed" @selected(old('employment_status') === 'Not Currently Employed')>Not Currently Employed</option>
                        <option value="Other" @selected(old('employment_status') === 'Other')>Other</option>
                    </select>
                    @error('employment_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_occupants">Number of Occupants</label>
                    <input type="number" id="rqc_occupants" name="number_of_occupants"
                           class="form-control @error('number_of_occupants') is-invalid @enderror"
                           value="{{ old('number_of_occupants') }}"
                           placeholder="e.g., 2" min="1" max="50">
                    @error('number_of_occupants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Total number of people who will occupy the unit.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_eviction">Eviction History</label>
                    <select id="rqc_eviction" name="eviction_history"
                            class="form-control @error('eviction_history') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="No Prior Evictions" @selected(old('eviction_history') === 'No Prior Evictions')>No Prior Evictions</option>
                        <option value="Eviction More Than 7 Years Ago" @selected(old('eviction_history') === 'Eviction More Than 7 Years Ago')>Eviction More Than 7 Years Ago</option>
                        <option value="Eviction Within Last 7 Years" @selected(old('eviction_history') === 'Eviction Within Last 7 Years')>Eviction Within Last 7 Years</option>
                        <option value="Eviction Within Last 3 Years" @selected(old('eviction_history') === 'Eviction Within Last 3 Years')>Eviction Within Last 3 Years</option>
                        <option value="Prefer Not to Say" @selected(old('eviction_history') === 'Prefer Not to Say')>Prefer Not to Say</option>
                    </select>
                    @error('eviction_history')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rqc_bankruptcy">Bankruptcy History</label>
                    <select id="rqc_bankruptcy" name="bankruptcy_history"
                            class="form-control @error('bankruptcy_history') is-invalid @enderror">
                        <option value="">Select</option>
                        <option value="No Bankruptcy" @selected(old('bankruptcy_history') === 'No Bankruptcy')>No Bankruptcy</option>
                        <option value="Bankruptcy Discharged More Than 5 Years Ago" @selected(old('bankruptcy_history') === 'Bankruptcy Discharged More Than 5 Years Ago')>Bankruptcy Discharged More Than 5 Years Ago</option>
                        <option value="Bankruptcy Discharged Within Last 5 Years" @selected(old('bankruptcy_history') === 'Bankruptcy Discharged Within Last 5 Years')>Bankruptcy Discharged Within Last 5 Years</option>
                        <option value="Active Bankruptcy" @selected(old('bankruptcy_history') === 'Active Bankruptcy')>Active Bankruptcy</option>
                        <option value="Prefer Not to Say" @selected(old('bankruptcy_history') === 'Prefer Not to Say')>Prefer Not to Say</option>
                    </select>
                    @error('bankruptcy_history')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Additional Notes --}}
            <div class="mb-4">
                <label class="form-label fw-semibold" for="rqc_notes">Additional Notes <span class="text-muted fw-normal">(Optional)</span></label>
                <textarea id="rqc_notes" name="additional_notes" rows="4"
                          class="form-control @error('additional_notes') is-invalid @enderror"
                          placeholder="Any additional context you would like to share with the landlord…"
                          maxlength="3000">{{ old('additional_notes') }}</textarea>
                @error('additional_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Maximum 3,000 characters.</div>
            </div>

            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="submit" class="btn btn-primary" style="background-color:#2563eb;border-color:#2563eb;">
                    <i class="fa-solid fa-paper-plane me-1"></i>Submit Qualification Information
                </button>
                <a href="{{ route('offer.listing.landlord.view', ['id' => $auction->id]) }}"
                   class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
    @else
    <div class="text-center mt-2">
        <a href="{{ route('offer.listing.landlord.view', ['id' => $auction->id]) }}" class="btn btn-outline-primary">
            <i class="fa-solid fa-arrow-left me-1"></i>Return to Listing
        </a>
    </div>
    @endif

</div>
@endsection
