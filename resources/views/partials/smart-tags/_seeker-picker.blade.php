{{--
    Smart Tag criteria picker — Buyer / Tenant.

    EVERY option here is projected from the canonical taxonomy at render time
    (SmartTagTaxonomy::forContext(..., SURFACE_SEEKER)). There is deliberately no
    hard-coded tag list in this file or in any script: a second list is exactly
    the duplicate vocabulary Smart Tags governance forbids, and it would go stale
    the first time a tag is added.

    The property type is chosen in the same wizard, so the picker renders every
    context this ROLE can reach and each option carries its own data-contexts.
    Changing the property type filters what is visible. That is a convenience
    only — SmartTagSeekerPreferenceWriter re-projects every submitted key against
    the STORED property type, so a hidden or hand-crafted checkbox is refused
    server-side rather than trusted.

    Required: $stRole ('buyer'|'tenant'). Optional: $stSelected (array of keys).
--}}
@php
    use App\Support\SmartTags\SmartTagContextResolver;
    use App\Support\SmartTags\SmartTagSeekerPreferenceGate;
    use App\Support\SmartTags\SmartTagTaxonomy;

    // The picker and the write path ask the SAME gate. A control whose submit is
    // ignored is worse than no control.
    $stEnabled = SmartTagSeekerPreferenceGate::enabled();

    $stSelected = collect($stSelected ?? [])->filter(fn ($v) => is_string($v))->values()->all();

    $stMap = $stRole === 'tenant'
        ? SmartTagContextResolver::TENANT_CRITERIA
        : SmartTagContextResolver::BUYER_CRITERIA;

    // tag key => [contexts it is seeker-selectable in], plus its definition.
    $stTags = [];
    foreach ($stMap as $propertyType => $context) {
        foreach (SmartTagTaxonomy::forContext($context, SmartTagTaxonomy::SURFACE_SEEKER) as $key => $definition) {
            $stTags[$key]['definition'] = $definition;
            $stTags[$key]['contexts'][] = $context->value;
        }
    }

    // Group by taxonomy category, preserving config order; drop empty groups.
    $stGroups = [];
    foreach (SmartTagTaxonomy::categories() as $slug => $category) {
        $inGroup = array_filter($stTags, fn ($row) => $row['definition']->category === $slug);
        if ($inGroup !== []) {
            $stGroups[$slug] = ['label' => $category['label'] ?? $slug, 'tags' => $inGroup];
        }
    }

    $stNoun = $stRole === 'tenant' ? 'rental' : 'property';
@endphp

@if ($stEnabled && ! empty($stGroups))
    <div class="form-group" id="smart-tag-picker" data-smart-tag-picker>
        <label class="fw-bold">Property Features You Want:</label>
        <p class="text-muted" style="font-size:.85rem; margin-bottom:.5rem;">
            Optional. Pick the features that matter to you and we will use them when we look for a
            {{ $stNoun }}. Leaving this empty simply means no feature preference.
        </p>

        <input type="text" class="form-control" id="smart-tag-search" autocomplete="off"
            placeholder="Search features (e.g. pool, garage, updated kitchen)"
            style="margin-bottom:.75rem;">

        <div id="smart-tag-groups">
            @foreach ($stGroups as $slug => $group)
                @php
                    $selectedInGroup = count(array_intersect(array_keys($group['tags']), $stSelected));
                @endphp
                <div class="card smart-tag-group" data-smart-tag-group style="margin-bottom:.5rem;">
                    <button type="button" class="btn btn-link smart-tag-group-toggle"
                        data-smart-tag-toggle aria-expanded="{{ $selectedInGroup > 0 ? 'true' : 'false' }}"
                        style="width:100%; text-align:left; text-decoration:none; padding:.6rem .85rem; font-weight:600;">
                        {{ $group['label'] }}
                        <span class="badge bg-secondary smart-tag-count"
                            data-smart-tag-count style="{{ $selectedInGroup > 0 ? '' : 'display:none;' }}">
                            {{ $selectedInGroup }}
                        </span>
                        <span style="float:right;" aria-hidden="true">▾</span>
                    </button>

                    <div class="smart-tag-group-body" data-smart-tag-body
                        style="padding:.25rem .85rem .75rem; {{ $selectedInGroup > 0 ? '' : 'display:none;' }}">
                        <div class="row">
                            @foreach ($group['tags'] as $key => $row)
                                <div class="col-md-4 col-sm-6 smart-tag-option"
                                    data-smart-tag-option
                                    data-tag-key="{{ $key }}"
                                    data-tag-label="{{ Str::lower($row['definition']->label) }}"
                                    data-tag-contexts="{{ implode(',', array_unique($row['contexts'])) }}">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            name="smart_tags[]" value="{{ $key }}"
                                            id="smart-tag-{{ $key }}"
                                            data-smart-tag-checkbox
                                            {{ in_array($key, $stSelected, true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="smart-tag-{{ $key }}">
                                            {{ $row['definition']->label }}
                                        </label>
                                        @include('partials.stellar.compliance-notices', ['notices' => filled($row['definition']->complianceNotice) ? [$row['definition']->complianceNotice] : []])
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted" id="smart-tag-empty" style="display:none; font-size:.85rem;">
            No features match that search.
        </p>
    </div>

    <script>
        (function () {
            var picker = document.querySelector('[data-smart-tag-picker]');
            if (!picker || picker.dataset.stBound === '1') { return; }
            picker.dataset.stBound = '1';

            var search = document.getElementById('smart-tag-search');
            var empty = document.getElementById('smart-tag-empty');
            var propertyTypeEl = document.getElementById('property_type');

            // Property type -> Smart Tag context, emitted from the SAME PHP map the
            // server validates against, so the two can never drift.
            var contextByPropertyType = @json(collect($stMap)->map(fn ($c) => $c->value));

            function currentContext() {
                if (!propertyTypeEl) { return null; }
                return contextByPropertyType[propertyTypeEl.value] || null;
            }

            function apply() {
                var term = (search && search.value ? search.value : '').trim().toLowerCase();
                var context = currentContext();
                var anyVisible = false;

                picker.querySelectorAll('[data-smart-tag-option]').forEach(function (option) {
                    var matchesTerm = term === '' || option.dataset.tagLabel.indexOf(term) !== -1;
                    var contexts = (option.dataset.tagContexts || '').split(',');
                    var matchesContext = context === null || contexts.indexOf(context) !== -1;
                    var visible = matchesTerm && matchesContext;

                    option.style.display = visible ? '' : 'none';

                    // A tag hidden because the property type changed must not be
                    // submitted: the server would refuse it anyway, but leaving it
                    // ticked would tell the customer it was saved.
                    if (!matchesContext) {
                        var box = option.querySelector('[data-smart-tag-checkbox]');
                        if (box) { box.checked = false; }
                    }

                    if (visible) { anyVisible = true; }
                });

                picker.querySelectorAll('[data-smart-tag-group]').forEach(function (group) {
                    var visibleInGroup = group.querySelectorAll('[data-smart-tag-option]:not([style*="display: none"])').length;
                    group.style.display = visibleInGroup > 0 ? '' : 'none';
                    if (term !== '' && visibleInGroup > 0) { openGroup(group, true); }
                    recount(group);
                });

                if (empty) { empty.style.display = anyVisible ? 'none' : ''; }
            }

            function openGroup(group, open) {
                var body = group.querySelector('[data-smart-tag-body]');
                var toggle = group.querySelector('[data-smart-tag-toggle]');
                if (!body || !toggle) { return; }
                body.style.display = open ? '' : 'none';
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            function recount(group) {
                var badge = group.querySelector('[data-smart-tag-count]');
                if (!badge) { return; }
                var n = group.querySelectorAll('[data-smart-tag-checkbox]:checked').length;
                badge.textContent = n;
                badge.style.display = n > 0 ? '' : 'none';
            }

            picker.querySelectorAll('[data-smart-tag-toggle]').forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    var group = toggle.closest('[data-smart-tag-group]');
                    openGroup(group, toggle.getAttribute('aria-expanded') !== 'true');
                });
            });

            picker.addEventListener('change', function (e) {
                if (e.target && e.target.matches('[data-smart-tag-checkbox]')) {
                    recount(e.target.closest('[data-smart-tag-group]'));
                }
            });

            if (search) { search.addEventListener('input', apply); }
            if (propertyTypeEl) { propertyTypeEl.addEventListener('change', apply); }

            apply();
        })();
    </script>
@endif
