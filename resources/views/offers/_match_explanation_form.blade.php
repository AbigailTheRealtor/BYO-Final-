{{--
    Match Explanation — editable form fields.

    Available from caller:
      $pm      — metas collection (plucked key → value)
      $offer   — Offer model (used for role-aware copy)
--}}
@php $isTenant = $offer->role === 'tenant'; @endphp

<p class="offer-section-header">Match Explanation</p>

<div class="mb-3">
    <label class="form-label fw-semibold">
        {{ $isTenant ? 'Why This Rental Matches' : 'Why This Property Matches' }}
        <span class="text-danger">*</span>
    </label>
    <textarea name="match_explanation" class="form-control" rows="4"
        placeholder="Explain how this {{ $isTenant ? 'rental' : 'property' }} meets the criteria listed in the {{ $isTenant ? 'tenant' : 'buyer' }}'s listing...">{{ old('match_explanation', $pm->get('match_explanation')) }}</textarea>
    <div class="form-text">
        Required before submitting. Describe how this {{ $isTenant ? 'rental' : 'property' }} aligns with the stated requirements.
    </div>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Compromises / Concessions Noted</label>
    <textarea name="match_compromise_note" class="form-control" rows="3"
        placeholder="Note any areas where the property may not fully match the stated criteria and how you propose to address them...">{{ old('match_compromise_note', $pm->get('match_compromise_note')) }}</textarea>
    <div class="form-text">
        Optional — identify any gaps and how they might be resolved.
    </div>
</div>
