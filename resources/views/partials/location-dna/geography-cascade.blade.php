{{--
    Phase 1c — the Criteria geography cascade.

    state (required) → counties (required) → cities (optional) → ZIP codes (optional)

    Include INSTEAD OF the shared widget's own four geography inputs, by passing
    `ldnaGeographyCascade => true` to partials/location-dna/map-input. The map, the draw
    tools and Important Places are unaffected and still render from that partial.

    Host contract: App\Http\Livewire\Concerns\HasGeographyCascade.

    EVERY TIER BELOW THE FIRST IS DISABLED UNTIL ITS PARENT IS CHOSEN, and says why. A
    control that is merely empty makes the user wonder whether the data is missing; one
    that names its prerequisite tells them what to do next.
--}}

<div class="mb-3" id="geo-cascade" wire:key="geo-cascade">

    <label class="fw-bold d-block mb-2" style="font-size:.95rem;">
        Location Preferences<span class="text-danger">*</span>
        <small class="text-muted d-block fw-normal" style="font-size:.8rem;">
            Choose a state and at least one county. Cities and ZIP codes are optional refinements.
        </small>
    </label>

    {{-- ── Cleared-selection notice ─────────────────────────────────────────────
         Clearing a dependent selection is data loss from the user's point of view. A
         cascade that removes eleven ZIP codes and says nothing looks like a bug. --}}
    @if (!empty($geoCleared))
        <div class="alert alert-warning py-2 px-3 d-flex justify-content-between align-items-start"
             style="font-size:.85rem;" wire:key="geo-cascade-cleared">
            <div>
                <i class="fa-solid fa-circle-info me-1"></i>
                {{ count($geoCleared) }}
                {{ \Illuminate\Support\Str::plural('selection', count($geoCleared)) }}
                {{ count($geoCleared) === 1 ? 'was' : 'were' }}
                cleared because they no longer fall inside your current selection.
            </div>
            <button type="button" class="btn-close btn-sm ms-2"
                    wire:click="dismissGeographyCleared" aria-label="Dismiss"></button>
        </div>
    @endif

    <div class="row g-3">

        {{-- ── State ─────────────────────────────────────────────────────────── --}}
        <div class="col-md-6">
            <label class="fw-bold" style="font-size:.88rem;" for="geo-cascade-state">
                State<span class="text-danger">*</span>
            </label>
            <select id="geo-cascade-state" class="form-select form-select-sm"
                    wire:model="geoStateId">
                <option value="">Select a state</option>
                @foreach ($this->geoStateOptions() as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
            @foreach ($this->geographyViolationsFor('state') as $message)
                <div class="error-message text-danger" style="font-size:.8rem;">{{ $message }}</div>
            @endforeach
        </div>

        {{-- ── Counties ──────────────────────────────────────────────────────── --}}
        <div class="col-md-6">
            <label class="fw-bold" style="font-size:.88rem;" for="geo-cascade-counties">
                Counties<span class="text-danger">*</span>
                <small class="text-muted">(select one or more)</small>
            </label>
            <select id="geo-cascade-counties" class="form-select form-select-sm" multiple size="6"
                    wire:model="geoCountyIds" @if ($geoStateId === '') disabled @endif>
                @foreach ($this->geoCountyOptions() as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
            @if ($geoStateId === '')
                <div class="ldna-hint"><i class="fa-solid fa-circle-info"></i> Select a state first.</div>
            @elseif (empty($this->geoCountyOptions()))
                <div class="ldna-hint"><i class="fa-solid fa-circle-info"></i> No counties on record for this state.</div>
            @endif
            @foreach ($this->geographyViolationsFor('counties') as $message)
                <div class="error-message text-danger" style="font-size:.8rem;">{{ $message }}</div>
            @endforeach
        </div>

        {{-- ── Cities (optional) ─────────────────────────────────────────────── --}}
        <div class="col-md-6">
            <label class="fw-bold" style="font-size:.88rem;" for="geo-cascade-cities">
                Cities <small class="text-muted">(optional)</small>
            </label>
            <select id="geo-cascade-cities" class="form-select form-select-sm" multiple size="6"
                    wire:model="geoCityIds" @if (empty($geoCountyIds)) disabled @endif>
                @foreach ($this->geoCityOptions() as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
            @if (empty($geoCountyIds))
                <div class="ldna-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    Select at least one county to narrow by city.
                </div>
            @elseif (empty($this->geoCityOptions()))
                <div class="ldna-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    No cities on record for these counties — you can skip this.
                </div>
            @endif
            {{-- Truncation is stated, never silent: a list that quietly stops at a limit
                 reads as "this is everything" when it is not. --}}
            @if ($this->geoOptionsTruncated('cities'))
                <div class="ldna-hint text-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Showing the first 1,000 cities. Select fewer counties to see the rest.
                </div>
            @endif
            @foreach ($this->geographyViolationsFor('cities') as $message)
                <div class="error-message text-danger" style="font-size:.8rem;">{{ $message }}</div>
            @endforeach
        </div>

        {{-- ── ZIP codes (optional) ──────────────────────────────────────────── --}}
        <div class="col-md-6">
            <label class="fw-bold" style="font-size:.88rem;" for="geo-cascade-zips">
                ZIP Codes <small class="text-muted">(optional)</small>
            </label>
            <select id="geo-cascade-zips" class="form-select form-select-sm" multiple size="6"
                    wire:model="geoZipCodes" @if (empty($geoCountyIds)) disabled @endif>
                @foreach ($this->geoZipOptions() as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
            @if (empty($geoCountyIds))
                <div class="ldna-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    Select at least one county to narrow by ZIP code.
                </div>
            @elseif (empty($this->geoZipOptions()))
                <div class="ldna-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    No ZIP codes on record for these counties — you can skip this.
                </div>
            @endif
            @if ($this->geoOptionsTruncated('zips'))
                <div class="ldna-hint text-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Showing the first 1,000 ZIP codes. Select fewer counties to see the rest.
                </div>
            @endif
            @foreach ($this->geographyViolationsFor('zip_codes') as $message)
                <div class="error-message text-danger" style="font-size:.8rem;">{{ $message }}</div>
            @endforeach
        </div>

    </div>

    {{-- ── Preserved history ─────────────────────────────────────────────────────
         Values saved before this cascade existed that the reference data cannot match.
         They are NOT errors and are not styled as such: they are kept exactly as they
         were stored, saved back untouched, and removed only if the user says so. --}}
    @php
        $geoPreservedTiers = [
            'counties'  => 'Counties',
            'cities'    => 'Cities',
            'zip_codes' => 'ZIP codes',
        ];
        $geoPreservedCount = empty($geoPreserved['state']) ? 0 : 1;

        foreach (array_keys($geoPreservedTiers) as $geoPreservedTier) {
            $geoPreservedCount += count($geoPreserved[$geoPreservedTier] ?? []);
        }
    @endphp

    @if ($geoPreservedCount > 0)
        <div class="mt-3 p-2 border rounded bg-light" wire:key="geo-cascade-preserved">
            <div class="fw-bold mb-1" style="font-size:.82rem;">
                <i class="fa-solid fa-clock-rotate-left me-1 text-muted"></i>
                Kept from your earlier selection
            </div>
            <div class="text-muted mb-2" style="font-size:.78rem;">
                We could not match these to our reference list, so they have been left exactly as you
                saved them. They are still part of this listing. Remove any you no longer want.
            </div>

            @if (!empty($geoPreserved['state']))
                <span class="badge bg-secondary me-1 mb-1">State: {{ $geoPreserved['state'] }}</span>
            @endif

            @foreach ($geoPreservedTiers as $tier => $label)
                @foreach ($geoPreserved[$tier] ?? [] as $index => $value)
                    <span class="badge bg-secondary me-1 mb-1" wire:key="geo-preserved-{{ $tier }}-{{ $index }}">
                        {{ $value }}
                        <button type="button" class="btn-close btn-close-white ms-1"
                                style="font-size:.55rem;"
                                wire:click="removePreservedGeography('{{ $tier }}', {{ $index }})"
                                aria-label="Remove {{ $value }}"></button>
                    </span>
                @endforeach
            @endforeach
        </div>
    @endif

</div>
