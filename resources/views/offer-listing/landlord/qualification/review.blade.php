@extends('layouts.main')

@php
    $str = function (string $key) use ($meta): string {
        $v = $meta[$key] ?? '';
        return is_array($v) ? implode(', ', array_map(fn($e) => (string)$e, $v)) : (string) $v;
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

    /* ─── Qualification comparison helpers ─── */

    // Credit score band ordering (lowest to highest)
    $creditBandOrder = [
        'below 500' => 0,
        '500–549'   => 1, '500-549' => 1,
        '550–599'   => 2, '550-599' => 2,
        '600–649'   => 3, '600-649' => 3,
        '650–699'   => 4, '650-699' => 4,
        '700–749'   => 5, '700-749' => 5,
        '750–799'   => 6, '750-799' => 6,
        '800+'      => 7,
        'no minimum'   => -1,  // legacy stored value
        'no requirement' => -1,  // normalized stored value
    ];

    $landlordCreditRaw = strtolower(trim($str('min_credit_score')));
    if ($landlordCreditRaw === 'other') {
        $landlordCreditRaw = strtolower(trim($str('custom_credit_score_requirement')));
    }

    $applicantCreditRaw = strtolower(trim($check->estimated_credit_score ?? ''));
    $creditFlexibility  = $str('credit_score_flexibility');

    // Compare credit bands
    $creditStatus = null; // 'pass', 'fail', 'flexible', 'na'
    if (!$landlordCreditRaw || $landlordCreditRaw === 'no minimum' || $landlordCreditRaw === 'no requirement') {
        $creditStatus = 'na';
    } elseif (!$applicantCreditRaw || str_contains($applicantCreditRaw, 'prefer not')) {
        $creditStatus = 'unknown';
    } else {
        $lBand = $creditBandOrder[$landlordCreditRaw] ?? -99;
        $aBand = $creditBandOrder[$applicantCreditRaw] ?? -99;
        if ($lBand === -99 || $aBand === -99) {
            $creditStatus = 'unknown';
        } elseif ($aBand >= $lBand) {
            $creditStatus = 'pass';
        } elseif ($creditFlexibility && !in_array(strtolower($creditFlexibility), ['strict requirement', 'no additional flexibility'])) {
            $creditStatus = 'flexible';
        } else {
            $creditStatus = 'fail';
        }
    }

    // Employment status comparison
    $landlordEmpReq = $str('employment_requirement');
    $applicantEmp   = $check->employment_status;
    $empStatus = 'na';
    if ($landlordEmpReq && strtolower($landlordEmpReq) !== 'no requirement') {
        if (!$applicantEmp) {
            $empStatus = 'unknown';
        } else {
            $lEmp = strtolower($landlordEmpReq);
            $aEmp = strtolower($applicantEmp);
            // Map: "employed" covers "employed full-time" and "employed part-time"
            if (str_contains($aEmp, 'employed') && ($lEmp === 'employed' || str_contains($lEmp, 'employed'))) {
                $empStatus = 'pass';
            } elseif ($lEmp === 'self-employed allowed' && str_contains($aEmp, 'self-employed')) {
                $empStatus = 'pass';
            } elseif ($lEmp === 'retired allowed' && str_contains($aEmp, 'retired')) {
                $empStatus = 'pass';
            } elseif ($lEmp === 'student allowed' && str_contains($aEmp, 'student')) {
                $empStatus = 'pass';
            } elseif (str_contains($lEmp, 'other')) {
                $empStatus = 'review';
            } else {
                $empStatus = 'review';
            }
        }
    }

    // Eviction comparison
    $landlordEvic = $str('eviction_history_requirement');
    $applicantEvic = $check->eviction_history;
    $evicStatus = 'na';
    if ($landlordEvic && strtolower($landlordEvic) !== 'no requirement') {
        if (!$applicantEvic) {
            $evicStatus = 'unknown';
        } elseif (str_contains(strtolower($applicantEvic), 'no prior')) {
            $evicStatus = 'pass';
        } else {
            $evicStatus = 'review';
        }
    }

    // Pet policy comparison
    $landlordPets  = $str('pet_policy_requirement');
    $applicantPets = $check->has_pets;
    $petStatus = 'na';
    if ($landlordPets && strtolower($landlordPets) !== 'no requirement') {
        if (strtolower($landlordPets) === 'no pets' && $applicantPets === 'Yes') {
            $petStatus = 'fail';
        } elseif ($applicantPets === 'No') {
            $petStatus = 'pass';
        } elseif (!$applicantPets) {
            $petStatus = 'unknown';
        } else {
            $petStatus = 'review';
        }
    }

    // Smoking comparison
    $landlordSmoke   = $str('smoking_policy_requirement');
    $applicantSmoke  = $check->smoking;
    $smokeStatus = 'na';
    if ($landlordSmoke && strtolower($landlordSmoke) !== 'no requirement') {
        if (str_contains(strtolower($landlordSmoke), 'no smoking') && $applicantSmoke === 'Smoker') {
            $smokeStatus = 'fail';
        } elseif ($applicantSmoke === 'Non-smoker') {
            $smokeStatus = 'pass';
        } elseif (!$applicantSmoke) {
            $smokeStatus = 'unknown';
        } else {
            $smokeStatus = 'review';
        }
    }

    // Criminal background comparison
    $landlordCriminal  = $str('criminal_background_requirement');
    $applicantCriminal = $check->criminal_background;
    $crimStatus = 'na';
    if ($landlordCriminal && strtolower($landlordCriminal) !== 'no requirement') {
        if (!$applicantCriminal) {
            $crimStatus = 'unknown';
        } elseif (strtolower($applicantCriminal) === 'no criminal background') {
            $crimStatus = 'pass';
        } else {
            $crimStatus = 'review';
        }
    }

    // Reference comparison
    $landlordRef  = $str('reference_requirement');
    $applicantRef = $check->landlord_reference_available;
    $refStatus = 'na';
    if ($landlordRef && strtolower($landlordRef) === 'required') {
        if ($applicantRef === 'Yes') {
            $refStatus = 'pass';
        } elseif (!$applicantRef) {
            $refStatus = 'unknown';
        } else {
            $refStatus = 'fail';
        }
    } elseif ($landlordRef && strtolower($landlordRef) === 'preferred') {
        $refStatus = $applicantRef === 'Yes' ? 'pass' : ($applicantRef ? 'review' : 'unknown');
    }

    // Bankruptcy comparison — uses landlord's bankruptcy_requirement key
    $landlordBankruptcy = $str('bankruptcy_requirement');
    if ($landlordBankruptcy === 'Other') $landlordBankruptcy = $str('custom_bankruptcy_requirement');
    $applicantBankruptcy = $check->bankruptcy_history;
    $bankruptcyStatus = 'na';
    if ($landlordBankruptcy && strtolower($landlordBankruptcy) !== 'no requirement') {
        if (!$applicantBankruptcy) {
            $bankruptcyStatus = 'unknown';
        } elseif (strtolower($applicantBankruptcy) === 'no bankruptcy') {
            $bankruptcyStatus = 'pass';
        } elseif (str_contains(strtolower($landlordBankruptcy), 'more than 5') &&
                  str_contains(strtolower($applicantBankruptcy), 'more than 5')) {
            $bankruptcyStatus = 'pass';
        } elseif (strtolower($applicantBankruptcy) === 'active bankruptcy') {
            $bankruptcyStatus = 'fail';
        } else {
            $bankruptcyStatus = 'review';
        }
    } elseif (!$applicantBankruptcy) {
        $bankruptcyStatus = 'unknown';
    } else {
        // No landlord requirement — just show what the applicant reported, neutral
        $bankruptcyStatus = 'na';
    }

    // Income qualification — uses income_qualification_method
    // Multiplier options: "2x Monthly Rent", "2.5x Monthly Rent", "3x Monthly Rent"
    // Fixed option:       "Fixed Monthly Income" + min_monthly_income_fixed
    // Other:              custom_income_requirement (review manually)
    $incomeMethod     = $str('income_qualification_method');
    $applicantIncome  = (float) preg_replace('/[^0-9.]/', '', (string) ($check->monthly_household_income ?? ''));
    $incomeStatus     = 'na';
    $incomeThreshold  = 0.0;
    $incomeThresholdDisplay = '';

    if ($incomeMethod && strtolower($incomeMethod) !== 'no requirement') {
        if (!$check->monthly_household_income) {
            $incomeStatus = 'unknown';
        } elseif ($incomeMethod === 'Fixed Monthly Income') {
            $fixed = (float) preg_replace('/[^0-9.]/', '', $str('min_monthly_income_fixed'));
            $incomeThreshold = $fixed;
            $incomeThresholdDisplay = $fixed > 0 ? '$' . number_format($fixed, 0) . '/mo (fixed)' : '';
            if ($fixed > 0) {
                $incomeStatus = $applicantIncome >= $fixed ? 'pass' : 'fail';
            } else {
                $incomeStatus = 'review';
            }
        } elseif (preg_match('/^(\d+(?:\.\d+)?)x\s+Rent$/i', $incomeMethod, $m)) {
            $multiplier = (float) $m[1];
            // Listing rent: prefer desired_rental_amount, fall back to starting_rent / reserve_rent
            $listingRentRaw = $str('desired_rental_amount') ?: $str('starting_rent') ?: $str('reserve_rent');
            $listingRent = (float) preg_replace('/[^0-9.]/', '', $listingRentRaw);
            if ($listingRent > 0) {
                $incomeThreshold = $listingRent * $multiplier;
                $incomeThresholdDisplay = $multiplier . '× $' . number_format($listingRent, 0) . ' = $' . number_format($incomeThreshold, 0) . '/mo';
                $incomeStatus = $applicantIncome >= $incomeThreshold ? 'pass' : 'fail';
            } else {
                // Listing rent not on record — cannot compute threshold; show requirement label neutrally
                $incomeThresholdDisplay = $incomeMethod;
                $incomeStatus = 'na'; // neutral — neither pass nor fail without listing rent
            }
        } elseif ($incomeMethod === 'Other' && $str('custom_income_requirement')) {
            $incomeThresholdDisplay = $str('custom_income_requirement');
            $incomeStatus = 'review';
        } else {
            $incomeThresholdDisplay = $incomeMethod;
            $incomeStatus = 'review';
        }
    }

    // Occupant limit — canonical keys: number_of_occupants_allowed fallback number_occupant
    $landlordMaxOccRaw = $str('number_of_occupants_allowed') ?: $str('number_occupant');
    $landlordMaxOcc    = (int) preg_replace('/[^0-9]/', '', $landlordMaxOccRaw);
    $applicantOcc      = (int) ($check->number_of_occupants ?? 0);
    $occStatus = 'na';
    if ($landlordMaxOcc > 0) {
        if (!$check->number_of_occupants) {
            $occStatus = 'unknown';
        } else {
            $occStatus = $applicantOcc <= $landlordMaxOcc ? 'pass' : 'fail';
        }
    }

    // Employment verification
    $landlordEmpVerif = $str('employment_verification_requirement');
    $applicantEmpVerif = $check->employment_verification_available;
    $empVerifStatus = 'na';
    if ($landlordEmpVerif && strtolower($landlordEmpVerif) === 'required') {
        $empVerifStatus = $applicantEmpVerif === 'Yes' ? 'pass' : ($applicantEmpVerif ? 'fail' : 'unknown');
    } elseif ($landlordEmpVerif && strtolower($landlordEmpVerif) === 'preferred') {
        $empVerifStatus = $applicantEmpVerif === 'Yes' ? 'pass' : ($applicantEmpVerif ? 'review' : 'unknown');
    }

    // Income verification
    $landlordIncVerif = $str('income_verification_requirement');
    $applicantIncVerif = $check->income_verification_available;
    $incVerifStatus = 'na';
    if ($landlordIncVerif && strtolower($landlordIncVerif) === 'required') {
        $incVerifStatus = $applicantIncVerif === 'Yes' ? 'pass' : ($applicantIncVerif ? 'fail' : 'unknown');
    } elseif ($landlordIncVerif && strtolower($landlordIncVerif) === 'preferred') {
        $incVerifStatus = $applicantIncVerif === 'Yes' ? 'pass' : ($applicantIncVerif ? 'review' : 'unknown');
    }

    // Status badge helpers
    $badge = function(string $status): string {
        return match($status) {
            'pass'     => '<span class="badge" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;font-size:.72rem;"><i class="fa-solid fa-check me-1"></i>Meets</span>',
            'fail'     => '<span class="badge" style="background:#fffbeb;color:#92400e;border:1px solid #fde68a;font-size:.72rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i>Below requirement</span>',
            'flexible' => '<span class="badge" style="background:#fffbeb;color:#92400e;border:1px solid #fde68a;font-size:.72rem;"><i class="fa-solid fa-circle-exclamation me-1"></i>Below (flexible)</span>',
            'review'   => '<span class="badge" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-size:.72rem;"><i class="fa-solid fa-magnifying-glass me-1"></i>Review</span>',
            'unknown'  => '<span class="badge" style="background:#f8fafc;color:#64748b;border:1px solid #e2e8f0;font-size:.72rem;"><i class="fa-solid fa-minus me-1"></i>Not provided</span>',
            default    => '<span class="badge" style="background:#f8fafc;color:#94a3b8;border:1px solid #e2e8f0;font-size:.72rem;">N/A</span>',
        };
    };

    $compRow = function(string $field, string $landlordVal, string $applicantVal, string $status) use ($badge): string {
        return sprintf(
            '<tr>
                <td style="font-size:.85rem;color:#475569;padding:.6rem .75rem;font-weight:600;">%s</td>
                <td style="font-size:.85rem;color:#334155;padding:.6rem .75rem;">%s</td>
                <td style="font-size:.85rem;color:#334155;padding:.6rem .75rem;">%s</td>
                <td style="padding:.6rem .75rem;">%s</td>
            </tr>',
            e($field), $landlordVal ?: '<span class="text-muted">—</span>', $applicantVal ?: '<span class="text-muted">—</span>', $badge($status)
        );
    };
@endphp

@push('styles')
<style>
.rqr-page { max-width: 940px; margin: 0 auto; }
.rqr-comparison-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: .75rem;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    margin-bottom: 1.5rem;
}
.rqr-comparison-card .rqr-card-header {
    background: linear-gradient(135deg,#0f766e,#0d9488);
    color: #fff;
    padding: 1rem 1.25rem;
    font-weight: 700;
    font-size: .95rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    justify-content: space-between;
}
.rqr-comparison-card .col-header {
    font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;padding:.5rem .75rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;
}
.rqr-comparison-card table { width:100%; border-collapse:collapse; }
.rqr-comparison-card table tr { border-bottom: 1px solid #f1f5f9; }
.rqr-comparison-card table tr:last-child { border-bottom: none; }

.rqr-detail-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: .75rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}
.rqr-detail-card .rqr-detail-title {
    font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:1px solid #f1f5f9;
}
.rqr-field { display:flex;gap:.5rem;margin-bottom:.45rem;font-size:.88rem; }
.rqr-field-label { color:#64748b;font-weight:600;min-width:210px;flex-shrink:0; }
.rqr-field-value { color:#1e293b; }
</style>
@endpush

@section('content')
<div class="container py-4 rqr-page">

    <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('offer.listing.landlord.qualification.submissions', ['listing' => $auction->id]) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>All submissions
        </a>
        <a href="{{ route('offer.listing.landlord.view', ['id' => $auction->id]) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-house me-1"></i>Listing
        </a>
    </div>

    <h2 class="fw-bold mb-1" style="color:#1e293b;">Qualification review</h2>
    <p class="text-muted mb-1">
        <span class="fw-semibold">{{ $check->name }}</span>
        <span class="mx-2">·</span>
        {{ $check->email }}
        @if($check->phone)
            <span class="mx-2">·</span>{{ $check->phone }}
        @endif
    </p>
    @if($fullAddress)
    <p class="text-muted mb-4 small"><i class="fa-solid fa-location-dot me-1" style="color:#0f766e;"></i>{{ $fullAddress }}</p>
    @endif

    {{-- ══════════════════════════════════════════════════
         QUALIFICATION COMPARISON CARD
         ══════════════════════════════════════════════════ --}}
    <div class="rqr-comparison-card">
        <div class="rqr-card-header">
            <span><i class="fa-solid fa-table-columns me-2"></i>Qualification comparison</span>
            <span style="font-size:.78rem;font-weight:400;opacity:.85;">Submitted {{ $check->created_at->format('M j, Y') }}</span>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th class="col-header" style="width:22%;">Criterion</th>
                        <th class="col-header" style="width:30%;">Your requirement</th>
                        <th class="col-header" style="width:32%;">Applicant's answer</th>
                        <th class="col-header" style="width:16%;">Match</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $landlordCreditDisplay = $str('min_credit_score');
                        if ($landlordCreditDisplay === 'Other') $landlordCreditDisplay = $str('custom_credit_score_requirement');
                        if (!$landlordCreditDisplay) $landlordCreditDisplay = 'No requirement';

                        $landlordEmpDisplay = $str('employment_requirement');
                        if ($landlordEmpDisplay === 'Other') $landlordEmpDisplay = $str('custom_employment_requirement');
                        if (!$landlordEmpDisplay || strtolower($landlordEmpDisplay) === 'no requirement') $landlordEmpDisplay = 'No requirement';

                        $landlordEvicDisplay = $str('eviction_history_requirement');
                        if ($landlordEvicDisplay === 'Other') $landlordEvicDisplay = $str('custom_eviction_requirement');
                        if (!$landlordEvicDisplay || strtolower($landlordEvicDisplay) === 'no requirement') $landlordEvicDisplay = 'No requirement';

                        $landlordPetDisplay = $str('pet_policy_requirement');
                        if ($landlordPetDisplay === 'Other') $landlordPetDisplay = $str('custom_pet_policy_requirement');
                        if (!$landlordPetDisplay || strtolower($landlordPetDisplay) === 'no requirement') $landlordPetDisplay = 'No requirement';

                        $landlordSmokeDisplay = $str('smoking_policy_requirement');
                        if ($landlordSmokeDisplay === 'Other') $landlordSmokeDisplay = $str('custom_smoking_policy_requirement');
                        if (!$landlordSmokeDisplay || strtolower($landlordSmokeDisplay) === 'no requirement') $landlordSmokeDisplay = 'No requirement';

                        $landlordCrimDisplay = $str('criminal_background_requirement');
                        if ($landlordCrimDisplay === 'Other') $landlordCrimDisplay = $str('custom_criminal_background_requirement');
                        if (!$landlordCrimDisplay || strtolower($landlordCrimDisplay) === 'no requirement') $landlordCrimDisplay = 'No requirement';

                        $landlordRefDisplay = $str('reference_requirement');
                        if ($landlordRefDisplay === 'Other') $landlordRefDisplay = $str('custom_reference_requirement');
                        if (!$landlordRefDisplay || strtolower($landlordRefDisplay) === 'no requirement') $landlordRefDisplay = 'No requirement';

                        // Income requirement display — uses income_qualification_method
                        $landlordIncomeMethodDisplay = $incomeThresholdDisplay ?: ($incomeMethod && strtolower($incomeMethod) !== 'no requirement' ? $incomeMethod : 'No requirement');
                        if (!$landlordIncomeMethodDisplay) $landlordIncomeMethodDisplay = 'No requirement';

                        // Applicant income display
                        $applicantIncomeDisplay = $check->monthly_household_income
                            ? '$' . number_format((float) preg_replace('/[^0-9.]/', '', (string) $check->monthly_household_income), 0) . '/mo'
                            : '';

                        // Max occupants display — canonical keys: number_of_occupants_allowed / number_occupant
                        $landlordMaxOccDisplay = $landlordMaxOccRaw ?: 'No limit';

                        // Bankruptcy requirement display
                        $landlordBankReqDisplay = ($landlordBankruptcy && strtolower($landlordBankruptcy) !== 'no requirement')
                            ? $landlordBankruptcy : 'No requirement';

                        // Emp/income verification requirement display
                        $landlordEmpVerifDisplay = $str('employment_verification_requirement');
                        if (!$landlordEmpVerifDisplay || strtolower($landlordEmpVerifDisplay) === 'no requirement') $landlordEmpVerifDisplay = 'No requirement';

                        $landlordIncVerifDisplay = $str('income_verification_requirement');
                        if (!$landlordIncVerifDisplay || strtolower($landlordIncVerifDisplay) === 'no requirement') $landlordIncVerifDisplay = 'No requirement';
                    @endphp
                    {!! $compRow(
                        'Minimum credit score',
                        $landlordCreditDisplay . ($creditFlexibility ? ' (' . $creditFlexibility . ')' : ''),
                        $check->estimated_credit_score ?? '',
                        $creditStatus
                    ) !!}
                    {!! $compRow(
                        'Monthly income',
                        $landlordIncomeMethodDisplay,
                        $applicantIncomeDisplay,
                        $incomeStatus
                    ) !!}
                    {!! $compRow(
                        'Employment / income source',
                        $landlordEmpDisplay,
                        implode(' / ', array_filter([
                            $check->employment_status
                                ? $check->employment_status . ($check->employment_status_other ? ': ' . $check->employment_status_other : '')
                                : null,
                            $check->income_source && $check->income_source !== $check->employment_status
                                ? $check->income_source
                                : null,
                        ])),
                        $empStatus
                    ) !!}
                    {!! $compRow(
                        'Eviction history',
                        $landlordEvicDisplay,
                        $check->eviction_history ?? '',
                        $evicStatus
                    ) !!}
                    {!! $compRow(
                        'Bankruptcy history',
                        $landlordBankReqDisplay,
                        $check->bankruptcy_history ?? '',
                        $bankruptcyStatus
                    ) !!}
                    {!! $compRow(
                        'Pet policy',
                        $landlordPetDisplay,
                        $check->has_pets
                            ? $check->has_pets . ($check->pet_details ? ': ' . $check->pet_details : '')
                            : '',
                        $petStatus
                    ) !!}
                    {!! $compRow(
                        'Smoking policy',
                        $landlordSmokeDisplay,
                        $check->smoking ?? '',
                        $smokeStatus
                    ) !!}
                    {!! $compRow(
                        'Criminal background',
                        $landlordCrimDisplay,
                        $check->criminal_background
                            ? $check->criminal_background . ($check->criminal_background_other ? ': ' . $check->criminal_background_other : '')
                            : '',
                        $crimStatus
                    ) !!}
                    {!! $compRow(
                        'Max occupants allowed',
                        $landlordMaxOccDisplay,
                        $check->number_of_occupants ? (string) $check->number_of_occupants : '',
                        $occStatus
                    ) !!}
                    {!! $compRow(
                        'Prior landlord reference',
                        $landlordRefDisplay,
                        $check->landlord_reference_available ?? '',
                        $refStatus
                    ) !!}
                    {!! $compRow(
                        'Employment docs available',
                        $landlordEmpVerifDisplay,
                        $check->employment_verification_available ?? '',
                        $empVerifStatus
                    ) !!}
                    {!! $compRow(
                        'Income docs available',
                        $landlordIncVerifDisplay,
                        $check->income_verification_available ?? '',
                        $incVerifStatus
                    ) !!}
                </tbody>
            </table>
        </div>
        <div style="background:#f8fafc;padding:.75rem 1rem;border-top:1px solid #e2e8f0;font-size:.75rem;color:#64748b;line-height:1.6;">
            <div><i class="fa-solid fa-circle-info me-1"></i><strong>How to read this comparison:</strong></div>
            <ul style="margin:.3rem 0 0 1.1rem;padding:0;">
                <li><strong style="color:#166534;">Meets</strong> — applicant's answer satisfies your stated requirement.</li>
                <li><strong style="color:#92400e;">⚠ Below requirement</strong> — applicant's answer falls below your stated threshold.</li>
                <li><strong style="color:#92400e;">Below (flexible)</strong> — credit score is below minimum but you indicated flexibility; applicant may still qualify.</li>
                <li><strong style="color:#1d4ed8;">Review</strong> — answer was provided but automated matching could not make a clear determination; evaluate manually.</li>
                <li><strong style="color:#64748b;">Not provided</strong> — applicant did not answer this question.</li>
                <li><strong>N/A</strong> — you did not set a requirement for this criterion.</li>
            </ul>
            <div class="mt-1"><i class="fa-solid fa-triangle-exclamation me-1" style="color:#d97706;"></i>This comparison is informational only. Qualification decisions must be made by the landlord based on all available information. Income matching uses the minimum income you specified — if you entered a descriptive requirement (e.g., "3× rent"), review manually.</div>
            @if($creditFlexibility && $creditStatus === 'flexible')
                <div class="mt-1"><strong style="color:#92400e;"><i class="fa-solid fa-circle-exclamation me-1"></i>Credit score flexibility is set to "{{ $creditFlexibility }}" — this applicant may still qualify at your discretion.</strong></div>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         FULL SUBMISSION DETAIL
         ══════════════════════════════════════════════════ --}}

    <div class="rqr-detail-card">
        <div class="rqr-detail-title"><i class="fa-solid fa-user me-1"></i>Applicant identity</div>
        <div class="row">
            <div class="col-md-6">
                <div class="rqr-field"><span class="rqr-field-label">Name</span><span class="rqr-field-value">{{ $check->name }}</span></div>
                <div class="rqr-field"><span class="rqr-field-label">Email</span><span class="rqr-field-value">{{ $check->email }}</span></div>
                @if($check->phone)
                <div class="rqr-field"><span class="rqr-field-label">Phone</span><span class="rqr-field-value">{{ $check->phone }}</span></div>
                @endif
            </div>
            <div class="col-md-6">
                <div class="rqr-field"><span class="rqr-field-label">Submitted</span><span class="rqr-field-value">{{ $check->created_at->format('M j, Y g:i A') }}</span></div>
                @if($check->desired_move_in_date)
                <div class="rqr-field"><span class="rqr-field-label">Desired move-in</span><span class="rqr-field-value">{{ $check->desired_move_in_date->format('M j, Y') }}</span></div>
                @endif
                <div class="rqr-field"><span class="rqr-field-label">Consent to screening</span><span class="rqr-field-value">{{ $check->consent_to_screening ? 'Yes' : 'No' }}</span></div>
            </div>
        </div>
    </div>

    <div class="rqr-detail-card">
        <div class="rqr-detail-title"><i class="fa-solid fa-money-bill-wave me-1"></i>Financial information</div>
        <div class="row">
            <div class="col-md-6">
                @if($check->estimated_credit_score)
                <div class="rqr-field"><span class="rqr-field-label">Estimated credit score</span><span class="rqr-field-value">{{ $check->estimated_credit_score }}</span></div>
                @endif
                @if($check->monthly_household_income)
                <div class="rqr-field"><span class="rqr-field-label">Monthly household income</span><span class="rqr-field-value">${{ number_format((float)preg_replace('/[^0-9.]/', '', $check->monthly_household_income), 0) }}</span></div>
                @endif
            </div>
            <div class="col-md-6">
                @if($check->employment_status)
                <div class="rqr-field">
                    <span class="rqr-field-label">Employment status</span>
                    <span class="rqr-field-value">
                        {{ $check->employment_status }}
                        @if($check->employment_status === 'Other' && $check->employment_status_other)
                            : {{ $check->employment_status_other }}
                        @endif
                    </span>
                </div>
                @endif
                @if($check->income_source)
                <div class="rqr-field"><span class="rqr-field-label">Income source</span><span class="rqr-field-value">{{ $check->income_source }}</span></div>
                @endif
                @if($check->employment_verification_available)
                <div class="rqr-field"><span class="rqr-field-label">Employment docs available</span><span class="rqr-field-value">{{ $check->employment_verification_available }}</span></div>
                @endif
                @if($check->income_verification_available)
                <div class="rqr-field"><span class="rqr-field-label">Income docs available</span><span class="rqr-field-value">{{ $check->income_verification_available }}</span></div>
                @endif
            </div>
        </div>
    </div>

    <div class="rqr-detail-card">
        <div class="rqr-detail-title"><i class="fa-solid fa-house-user me-1"></i>Household information</div>
        <div class="row">
            <div class="col-md-6">
                @if($check->number_of_occupants)
                <div class="rqr-field"><span class="rqr-field-label">Number of occupants</span><span class="rqr-field-value">{{ $check->number_of_occupants }}</span></div>
                @endif
                @if($check->has_pets !== null)
                <div class="rqr-field">
                    <span class="rqr-field-label">Pets</span>
                    <span class="rqr-field-value">
                        {{ $check->has_pets }}
                        @if($check->has_pets === 'Yes' && $check->pet_details)
                            — {{ $check->pet_details }}
                        @endif
                    </span>
                </div>
                @endif
            </div>
            <div class="col-md-6">
                @if($check->smoking)
                <div class="rqr-field"><span class="rqr-field-label">Smoking</span><span class="rqr-field-value">{{ $check->smoking }}</span></div>
                @endif
            </div>
        </div>
    </div>

    <div class="rqr-detail-card">
        <div class="rqr-detail-title"><i class="fa-solid fa-shield-halved me-1"></i>Rental &amp; background history</div>
        <div class="row">
            <div class="col-md-6">
                @if($check->eviction_history)
                <div class="rqr-field"><span class="rqr-field-label">Eviction history</span><span class="rqr-field-value">{{ $check->eviction_history }}</span></div>
                @endif
                @if($check->bankruptcy_history)
                <div class="rqr-field"><span class="rqr-field-label">Bankruptcy history</span><span class="rqr-field-value">{{ $check->bankruptcy_history }}</span></div>
                @endif
                @if($check->criminal_background)
                <div class="rqr-field">
                    <span class="rqr-field-label">Criminal background</span>
                    <span class="rqr-field-value">
                        {{ $check->criminal_background }}
                        @if($check->criminal_background_other)
                            — {{ $check->criminal_background_other }}
                        @endif
                    </span>
                </div>
                @endif
            </div>
            <div class="col-md-6">
                @if($check->landlord_reference_available)
                <div class="rqr-field"><span class="rqr-field-label">Prior landlord reference</span><span class="rqr-field-value">{{ $check->landlord_reference_available }}</span></div>
                @endif
            </div>
        </div>
    </div>

    @if($check->applicant_profile || $check->additional_notes)
    <div class="rqr-detail-card">
        <div class="rqr-detail-title"><i class="fa-solid fa-id-card me-1"></i>Applicant's notes</div>
        @if($check->applicant_profile)
        <div class="mb-3">
            <div class="fw-semibold small text-muted mb-1">About the applicant</div>
            <div style="font-size:.9rem;color:#1e293b;white-space:pre-wrap;">{{ $check->applicant_profile }}</div>
        </div>
        @endif
        @if($check->additional_notes)
        <div>
            <div class="fw-semibold small text-muted mb-1">Additional notes</div>
            <div style="font-size:.9rem;color:#1e293b;white-space:pre-wrap;">{{ $check->additional_notes }}</div>
        </div>
        @endif
    </div>
    @endif

</div>
@endsection
