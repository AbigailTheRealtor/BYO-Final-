{{--
    Property Features — the listing owner's Smart Tag picker.

    ONE PARTIAL, FOUR SURFACES. Included from each role's
    `property-preferences.blade.php`, which already serves that role's Create AND
    Edit wizard, so the two can never offer different options — the same reason
    `applicant-requirements.blade.php` is shared.

    THE method_exists GUARD IS LOAD-BEARING, NOT DEFENSIVE. The Seller partial is
    also included by the Buyer and Tenant wizards, and the Landlord partial by the
    Tenant and Buyer ones; those components describe a SEARCH, not a property, and
    must not receive an owner surface. Only the four Offer Listing components use
    HasOwnerSmartTags, so asking for the method is asking exactly the right
    question, and a test pins that Buyer/Tenant render nothing.

    NOTHING HERE DECIDES ANYTHING. Every option was projected by
    SmartTagSelectionPolicy before it reached this file, and the same projection
    runs again inside ManualSmartTagWriter against the STORED property type. This
    template renders a list; it does not filter one.
--}}
@if (method_exists($this, 'ownerSmartTagPanel'))
    @php($byoTagPanel = $this->ownerSmartTagPanel())

    @if ($byoTagPanel->available || $byoTagPanel->unavailableReason === \App\Support\SmartTags\OwnerSmartTagPanel::REASON_NO_CONTEXT)
        <div class="form-group" wire:key="owner-smart-tags-{{ $byoTagPanel->context?->value ?? 'none' }}">
            <label class="fw-bold">Property Features:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Optional. Tick the features this property actually has. They help buyers and renters find listings like yours, and they are the same feature names used everywhere on BidYourOffer.">
                <i class="fa-solid fa-circle-info"></i>
            </span>

            @if (! $byoTagPanel->available)
                <p class="text-muted small mb-0 mt-2">
                    Choose a property type on the Listing Details tab to see the features you can add.
                </p>
            @else
                @if ($byoTagPanel->hasDetected())
                    <div class="mt-2 mb-3 p-3 rounded" style="background:#f6f8fa;border:1px solid #e3e7ec;">
                        <div class="fw-bold small mb-2">
                            <i class="fa-solid fa-circle-check text-success me-1"></i>
                            Already included from your listing details
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($byoTagPanel->detected as $byoDetected)
                                <span class="badge rounded-pill"
                                    style="background:#e8f2ec;color:#1f5134;font-weight:500;"
                                    title="{{ $byoDetected['category'] }}">{{ $byoDetected['label'] }}</span>
                            @endforeach
                        </div>
                        <p class="text-muted small mb-0 mt-2">
                            These come from answers you have already given above, so there is nothing to tick.
                        </p>
                    </div>
                @endif

                <div x-data="{ byoTagQuery: '' }" class="mt-2">
                    <div class="input-cover mb-3">
                        <input type="text" class="form-control has-icon"
                            data-icon="fa-solid fa-magnifying-glass"
                            placeholder="Search features (e.g. pool, quartz, loading dock)"
                            x-model="byoTagQuery" autocomplete="off">
                    </div>

                    <p class="text-muted small">
                        Add more features — {{ $byoTagPanel->optionCount() }} available for this property type,
                        {{ $byoTagPanel->selectedCount() }} selected.
                    </p>

                    @foreach ($byoTagPanel->groups as $byoGroupIndex => $byoGroup)
                        @php($byoGroupHaystack = \Illuminate\Support\Str::lower(implode(' ', array_column($byoGroup['options'], 'label'))))
                        <div class="mb-2 rounded" style="border:1px solid #e3e7ec;"
                            wire:key="owner-smart-tag-group-{{ $byoGroup['key'] }}"
                            data-byo-tag-group="{{ $byoGroup['key'] }}"
                            data-byo-tag-labels="{{ $byoGroupHaystack }}"
                            x-show="byoTagQuery === '' || $el.dataset.byoTagLabels.includes(byoTagQuery.toLowerCase())">
                            <div x-data="{ byoGroupOpen: {{ $byoGroupIndex === 0 ? 'true' : 'false' }} }">
                                <button type="button"
                                    class="btn w-100 d-flex justify-content-between align-items-center text-start px-3 py-2"
                                    style="background:transparent;border:0;"
                                    @click="byoGroupOpen = ! byoGroupOpen">
                                    <span class="fw-bold">{{ $byoGroup['label'] }}</span>
                                    <span class="text-muted small">
                                        {{ count($byoGroup['options']) }}
                                        <i class="fa-solid ms-2"
                                            :class="(byoGroupOpen || byoTagQuery !== '') ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </span>
                                </button>

                                <div class="px-3 pb-3" x-show="byoGroupOpen || byoTagQuery !== ''" x-cloak>
                                    <div class="row">
                                        @foreach ($byoGroup['options'] as $byoOption)
                                            <div class="col-12 col-md-6 col-lg-4 mb-2"
                                                wire:key="owner-smart-tag-{{ $byoOption['key'] }}"
                                                data-byo-tag-label="{{ \Illuminate\Support\Str::lower($byoOption['label']) }}"
                                                x-show="byoTagQuery === '' || $el.dataset.byoTagLabel.includes(byoTagQuery.toLowerCase())">
                                                {{-- `checked` is rendered server-side deliberately: Livewire does
                                                     not set a checkbox's initial state from the component data, so
                                                     without it every tick restored on Edit would come back empty. --}}
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                        id="owner-smart-tag-{{ $byoOption['key'] }}"
                                                        value="{{ $byoOption['key'] }}" {{ $byoOption['selected'] ? 'checked' : '' }}
                                                        wire:model.defer="smart_tag_selections">
                                                    <label class="form-check-label"
                                                        for="owner-smart-tag-{{ $byoOption['key'] }}"
                                                        @if ($byoOption['description'] !== '') title="{{ $byoOption['description'] }}" @endif>
                                                        {{ $byoOption['label'] }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
@endif
