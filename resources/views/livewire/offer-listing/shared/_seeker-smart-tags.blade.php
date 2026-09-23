{{--
    Property Features You Want — the Buyer/Tenant Offer Listing seeker Smart Tag picker.

    THE LIVEWIRE COUNTERPART OF partials/smart-tags/_seeker-picker.blade.php, not a second
    vocabulary. Both render only what SmartTagSeekerPreferenceReader projects from the
    canonical taxonomy on SURFACE_SEEKER; neither holds a tag list, and both write through
    the same SmartTagSeekerPreferenceWriter into the same table. The criteria partial is a
    plain POST form filtered in the browser; this one is a Livewire wizard, so the component
    resolves the context from the FORM's property type and the options change in the same
    request as the select.

    ONE PARTIAL, FOUR SURFACES. Included from the Buyer `property-preferences` and Tenant
    `property-details` tabs, each of which serves its role's Create AND Edit wizard.

    THE method_exists GUARD IS LOAD-BEARING. These tab partials are also rendered inside
    other roles' components; only the four Buyer/Tenant Offer Listing components use
    HasSeekerSmartTags. The panel is also null while SMART_TAGS_SEEKER_PREFERENCES_ENABLED
    is off, or when the wizard is describing a different role — the same gate the write asks.

    NOTHING HERE DECIDES ANYTHING. The writer re-projects every submitted key against the
    STORED property type, so a hidden or hand-crafted checkbox is refused server-side.
--}}
@if (method_exists($this, 'seekerSmartTagPanel'))
    @php($byoSeekerPanel = $this->seekerSmartTagPanel())

    @if ($byoSeekerPanel !== null)
        <div class="form-group mt-3" data-seeker-smart-tags
            wire:key="seeker-smart-tags-{{ $byoSeekerPanel['context'] ?? 'none' }}">
            <label class="fw-bold">Property Features You Want:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Optional. Pick the features that matter to you and we will use them when we look for a match. Leaving this empty simply means no feature preference.">
                <i class="fa-solid fa-circle-info"></i>
            </span>

            @if ($byoSeekerPanel['context'] === null)
                <p class="text-muted small mb-0 mt-2">
                    Choose a property type above to see the features you can ask for.
                </p>
            @else
                {{-- byoSeekerTotal mirrors the per-group badge counts: the boxes are wire:model.defer,
                     so no round trip re-renders the server-side count while the seeker is ticking. --}}
                <div x-data="{ byoSeekerQuery: '', byoSeekerTotal: {{ $byoSeekerPanel['selectedCount'] }} }" class="mt-2">
                    <div class="input-cover mb-3">
                        <input type="text" class="form-control has-icon"
                            data-icon="fa-solid fa-magnifying-glass"
                            placeholder="Search features (e.g. pool, garage, updated kitchen)"
                            x-model="byoSeekerQuery" autocomplete="off">
                    </div>

                    <p class="text-muted small">
                        Optional — {{ $byoSeekerPanel['optionCount'] }} features available for this property type,
                        <span x-text="byoSeekerTotal">{{ $byoSeekerPanel['selectedCount'] }}</span> selected.
                    </p>

                    @foreach ($byoSeekerPanel['groups'] as $byoSeekerSlug => $byoSeekerGroup)
                        @php($byoSeekerHaystack = \Illuminate\Support\Str::lower(implode(' ', array_column($byoSeekerGroup['options'], 'label'))))
                        @php($byoSeekerGroupSelected = count(array_filter($byoSeekerGroup['options'], fn ($o) => $o['selected'])))
                        {{-- The CONTEXT is part of every key below, the checkbox's included (without
                             a wire:key Livewire keys it by its id, which must stay context-free for
                             the label). Livewire's morph matches keyed nodes, so a group both
                             contexts share (Interior, Water, Parking, …) was reused across a
                             property-type change with its Alpine bindings still tied to the
                             discarded scope — search stopped filtering it and ticking a box no
                             longer moved its count badge. --}}
                        <div class="mb-2 rounded" style="border:1px solid #e3e7ec;"
                            wire:key="seeker-smart-tag-group-{{ $byoSeekerPanel['context'] }}-{{ $byoSeekerSlug }}"
                            data-seeker-tag-group="{{ $byoSeekerSlug }}"
                            data-seeker-tag-labels="{{ $byoSeekerHaystack }}"
                            x-show="byoSeekerQuery === '' || $el.dataset.seekerTagLabels.includes(byoSeekerQuery.toLowerCase())">
                            <div x-data="{ byoSeekerOpen: {{ $byoSeekerGroupSelected > 0 ? 'true' : 'false' }}, byoSeekerCount: {{ $byoSeekerGroupSelected }} }">
                                <button type="button"
                                    class="btn w-100 d-flex justify-content-between align-items-center text-start px-3 py-2"
                                    style="background:transparent;border:0;"
                                    :aria-expanded="(byoSeekerOpen || byoSeekerQuery !== '') ? 'true' : 'false'"
                                    @click="byoSeekerOpen = ! byoSeekerOpen">
                                    <span class="fw-bold">{{ $byoSeekerGroup['label'] }}</span>
                                    <span class="text-muted small text-nowrap">
                                        <span class="badge bg-secondary me-1" x-show="byoSeekerCount > 0"
                                            x-text="byoSeekerCount + ' selected'"
                                            @if ($byoSeekerGroupSelected === 0) style="display:none;" @endif>{{ $byoSeekerGroupSelected }} selected</span>
                                        {{ count($byoSeekerGroup['options']) }}
                                        <i class="fa-solid ms-2"
                                            :class="(byoSeekerOpen || byoSeekerQuery !== '') ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </span>
                                </button>

                                <div class="px-3 pb-3" x-show="byoSeekerOpen || byoSeekerQuery !== ''" x-cloak>
                                    <div class="row">
                                        @foreach ($byoSeekerGroup['options'] as $byoSeekerOption)
                                            <div class="col-12 col-md-6 col-lg-4 mb-2"
                                                wire:key="seeker-smart-tag-option-{{ $byoSeekerPanel['context'] }}-{{ $byoSeekerOption['key'] }}"
                                                data-seeker-tag-label="{{ \Illuminate\Support\Str::lower($byoSeekerOption['label']) }}"
                                                x-show="byoSeekerQuery === '' || $el.dataset.seekerTagLabel.includes(byoSeekerQuery.toLowerCase())">
                                                {{-- `checked` is rendered server-side deliberately: Livewire does not
                                                     set a checkbox's initial state from component data, so without it
                                                     every pick restored on Edit would come back empty. --}}
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                        wire:key="seeker-smart-tag-input-{{ $byoSeekerPanel['context'] }}-{{ $byoSeekerOption['key'] }}"
                                                        id="seeker-smart-tag-{{ $byoSeekerOption['key'] }}"
                                                        value="{{ $byoSeekerOption['key'] }}" {{ $byoSeekerOption['selected'] ? 'checked' : '' }}
                                                        @change="byoSeekerCount += $event.target.checked ? 1 : -1; byoSeekerTotal += $event.target.checked ? 1 : -1"
                                                        wire:model.defer="seeker_smart_tags">
                                                    <label class="form-check-label"
                                                        for="seeker-smart-tag-{{ $byoSeekerOption['key'] }}"
                                                        @if ($byoSeekerOption['description'] !== '') title="{{ $byoSeekerOption['description'] }}" @endif>
                                                        {{ $byoSeekerOption['label'] }}
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
