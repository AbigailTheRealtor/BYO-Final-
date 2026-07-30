{{--
    Shared client-side publish gate for all four Offer-Listing wizards
    (Seller + Landlord, create + edit).

    WHY THIS EXISTS
    ---------------
    The edit views had no authoritative submit gate at all. What they had was a
    legacy completeness check that disabled #save-button whenever ANY DOM
    [required] field was empty — a set roughly three times wider than the
    server's publish rules — combined with
    `#save-button.disabled { pointer-events: none }`. The click was swallowed
    before wire:submit could fire, so Submit did nothing, no Livewire request was
    sent, and no error was ever shown. Resumed drafts, which are part-filled by
    definition, were the worst hit.

    THE CONTRACT
    ------------
    The one source of truth for "may this publish proceed" is the server's own
    rule set, surfaced by
    {@see \App\Http\Livewire\OfferListing\Concerns\GuidesPublishValidation::publishRequiredFieldNames()},
    which derives from the role's getConditionalRules(). Create already worked
    this way; this partial gives edit the identical contract so the two cannot
    drift.

    The gate is advisory — it exists to explain a failure before a round trip.
    The server re-validates everything in update() and stays authoritative; a
    rejection there comes back through the same navigation path via the
    `publish-validation-failed` browser event.

    The button is never disabled for form completeness. The only disabled state
    is the attribute Livewire sets while a submit is in flight
    (wire:loading.attr), which prevents double submission natively.

    REQUIRED VARIABLES
    ------------------
      $gateFormId       string  id of the <form> to intercept
      $gateRequired     array   publishRequiredFieldNames()
      $gateLabels       array   field key => human label (optional, defaults to key)
--}}
<script>
    (function () {
        // The server's own publish-required list. Conditional rules are already
        // resolved against live component state, so e.g. auction_time appears here
        // only for a Bidding Period listing.
        var GATE_REQUIRED = @json($gateRequired);
        var GATE_LABELS   = @json($gateLabels ?? []);
        var GATE_FORM_ID  = @json($gateFormId);

        function gateFieldKey(field) {
            var wm = field.getAttribute('wire:model')
                  || field.getAttribute('wire:model.defer')
                  || field.getAttribute('wire:model.lazy')
                  || field.getAttribute('wire:model.live')
                  || field.getAttribute('wire:model.debounce.300ms');
            if (wm) return wm.split('.')[0];
            return field.id || field.name || '';
        }

        function gateLabel(key, field) {
            if (GATE_LABELS[key]) return GATE_LABELS[key];
            if (field) {
                var group = field.closest('.form-group');
                var labelEl = group ? group.querySelector('label') : null;
                if (labelEl) return labelEl.textContent.replace(/[*:]/g, '').trim();
                if (field.getAttribute('placeholder')) return field.getAttribute('placeholder');
            }
            return key;
        }

        // True when a field is switched off by its OWN conditional markup, as opposed
        // to merely sitting on an inactive tab. Only the former is genuinely not
        // required — conflating the two is what hid cross-tab failures previously.
        function gateHiddenWithinTab(field) {
            var pane = field.closest('.tab-pane');
            if (!pane) return false;
            var el = field.parentElement;
            while (el && el !== pane) {
                if (el.classList && el.classList.contains('d-none')) return true;
                if (el.style && el.style.display === 'none') return true;
                el = el.parentElement;
            }
            return false;
        }

        function gateIsEmpty(field) {
            if (field.type === 'file')     return !field.files || field.files.length === 0;
            if (field.type === 'checkbox') return !field.checked;
            if (field.type === 'radio')    return !document.querySelector('input[name="' + field.name + '"]:checked');
            if (field.type === 'select-one' || field.type === 'select-multiple') {
                return field.value === '' || field.value === null || field.value === undefined;
            }
            return !(field.value != null && field.value.toString().trim());
        }

        function gateLivewireComponent() {
            try {
                var el = document.querySelector('[wire\\:id]');
                if (!el || !window.livewire) return null;
                return window.livewire.find(el.getAttribute('wire:id'));
            } catch (e) {
                return null;
            }
        }

        // Missing publish-required fields, across every tab.
        //
        // DOM first. Any required key with no usable DOM field — a Select2 multi-select
        // inside wire:ignore, for instance, whose DOM value is unreliable — falls back
        // to the Livewire property, so the gate never reports a field the user has in
        // fact filled in.
        function gateMissingItems() {
            var items = [];
            var seen  = new Set();
            var resolved = new Set();

            document.querySelectorAll('.tab-pane [wire\\:model], .tab-pane [wire\\:model\\.defer], .tab-pane [wire\\:model\\.lazy], .tab-pane [id]').forEach(function (field) {
                var key = gateFieldKey(field);
                if (!key || GATE_REQUIRED.indexOf(key) === -1) return;
                if (field.disabled || field.type === 'hidden') return;
                if (gateHiddenWithinTab(field)) return;

                // Select2-backed multi-selects report an unreliable DOM value; defer to
                // the Livewire property below rather than guessing from the DOM.
                if (field.tagName === 'SELECT' && field.multiple) return;
                if (field.closest('[wire\\:ignore]')) return;

                resolved.add(key);
                if (seen.has(key)) return;

                if (gateIsEmpty(field)) {
                    seen.add(key);
                    items.push({
                        field: field,
                        tab:   field.closest('.tab-pane'),
                        key:   key,
                        label: gateLabel(key, field)
                    });
                }
            });

            var comp = gateLivewireComponent();
            if (comp) {
                GATE_REQUIRED.forEach(function (key) {
                    if (resolved.has(key) || seen.has(key)) return;

                    var value;
                    try { value = comp.get(key); } catch (e) { return; }
                    if (value === undefined) return;

                    var empty = Array.isArray(value)
                        ? value.length === 0
                        : (value === null || value.toString().trim() === '');
                    if (!empty) return;

                    var el = document.querySelector('[wire\\:model="' + key + '"], [wire\\:model\\.defer="' + key + '"], #' + key);
                    seen.add(key);
                    items.push({
                        field: el,
                        tab:   el ? el.closest('.tab-pane') : null,
                        key:   key,
                        label: gateLabel(key, el)
                    });
                });
            }

            return items;
        }

        // Navigate to the tab owning the item, keep the server's $activeTab in step,
        // then scroll to and focus the field. Livewire state is untouched, so every
        // entered value — including an in-progress draft — survives.
        function gateNavigateTo(item) {
            if (!item) return;
            if (item.tab) {
                var trigger = document.querySelector('[data-bs-target="#' + item.tab.id + '"], [href="#' + item.tab.id + '"]');
                if (trigger && window.bootstrap) {
                    bootstrap.Tab.getOrCreateInstance(trigger).show();
                    var idx = [].slice.call(document.querySelectorAll('.tab-pane')).indexOf(item.tab);
                    if (idx >= 0 && typeof Livewire !== 'undefined') {
                        Livewire.emit('setActiveTab', idx);
                    }
                }
            }
            setTimeout(function () {
                if (item.field && item.field.classList) {
                    item.field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof item.field.focus === 'function' && item.field.tagName !== 'DIV') {
                        item.field.focus();
                    }
                    item.field.classList.add('is-invalid');
                }
            }, 350);
        }

        // ------------------------------------------------------------------
        // Banner survival across Livewire DOM morphs.
        //
        // The banner markup lives INSIDE the Livewire component root, so every
        // re-render morphs it back to the server-rendered `d-none` shell with an
        // empty <ul>. The gate triggers that itself: gateNavigateTo() emits
        // `setActiveTab`, so showing the banner and then walking the user to the
        // failing tab wipes the message a few hundred ms later. The Seller views
        // had no compensating hook, so Submit looked like it did nothing.
        //
        // We therefore remember what is on the banner and re-apply it after each
        // Livewire message. Remembering, not re-validating: re-running the whole
        // gate on every render would surface fields the user has not attempted.
        //
        //   source 'client'  our own missing-field list. On re-apply we drop keys
        //                    the user has since filled, so the banner self-clears
        //                    as they fix things and never goes stale.
        //   source 'server'  a publish-validation-failed payload. Re-applied
        //                    verbatim: those messages are the server's and may
        //                    name fields outside GATE_REQUIRED.
        // ------------------------------------------------------------------
        var gateActive = { source: null, keys: [], lines: [], heading: '' };

        function gateRemember(source, lines, heading, keys) {
            gateActive = {
                source:  source,
                keys:    keys || [],
                lines:   lines || [],
                heading: heading || ''
            };
        }

        function gateForget() {
            gateActive = { source: null, keys: [], lines: [], heading: '' };
        }

        /**
         * Is this one remembered key STILL empty?
         *
         * Deliberately narrow, and deliberately pessimistic. It inspects only the
         * named field rather than re-running the whole form scan, because during a
         * morph that scan is unreliable — mid-render the panes can momentarily
         * report nothing missing, and an earlier version of this repair used that
         * to hide the banner, which reproduced the very bug it was fixing.
         *
         * Returns true (still missing) whenever we cannot positively see a value.
         * A key is dropped only on affirmative evidence that the user filled it.
         */
        function gateKeyStillMissing(key) {
            var field = document.querySelector(
                '[wire\\:model="' + key + '"], [wire\\:model\\.defer="' + key + '"], ' +
                '[wire\\:model\\.lazy="' + key + '"], #' + key
            );

            if (field && !field.disabled && field.type !== 'hidden') {
                return gateIsEmpty(field);
            }

            var comp = gateLivewireComponent();
            if (comp) {
                var value;
                try { value = comp.get(key); } catch (e) { return true; }
                if (value === undefined) return true;
                return Array.isArray(value)
                    ? value.length === 0
                    : (value === null || value.toString().trim() === '');
            }

            return true;
        }

        /**
         * Re-assert the banner after a Livewire render.
         *
         * Content only — no scroll, no tab change, no emit — so this can never loop
         * or steal focus, and it never hides the banner. Hiding is reserved for
         * gateHideBanner(), which only ever runs at the start of a fresh submit.
         * That asymmetry is the whole fix: a DOM morph can no longer erase a
         * validation message the user has not yet acted on.
         */
        function gateReapply() {
            if (!gateActive.source) return;

            var banner = document.getElementById('submit-error-banner');
            var list   = document.getElementById('submit-error-list');
            if (!banner || !list) return;

            if (gateActive.source === 'client') {
                // Narrow the list as the user fixes things, but never to nothing.
                var keptKeys  = [];
                var keptLines = [];

                gateActive.keys.forEach(function (key, i) {
                    if (gateKeyStillMissing(key)) {
                        keptKeys.push(key);
                        keptLines.push(gateActive.lines[i]);
                    }
                });

                if (keptKeys.length > 0) {
                    gateActive.keys  = keptKeys;
                    gateActive.lines = keptLines;
                }
                // If everything now reads as filled we still leave the banner up.
                // The next submit is authoritative: it clears the banner first and
                // either passes through or reports a fresh list.
            }

            gateRenderBanner(gateActive.lines, gateActive.heading);
        }

        // One shared Livewire hook for however many gate instances a page mounts.
        // Registration is idempotent: the flag lives on window, so a re-executed
        // script or a second instance adds its callback without ever hooking twice.
        window.__offerPublishGate = window.__offerPublishGate || { reappliers: [], hooked: false };
        window.__offerPublishGate.reappliers.push(gateReapply);

        function gateRegisterLivewireHook() {
            var reg = window.__offerPublishGate;
            if (reg.hooked) return;
            if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;

            reg.hooked = true;
            // Livewire 2 fires message.processed after the DOM has been morphed.
            window.Livewire.hook('message.processed', function () {
                reg.reappliers.forEach(function (fn) {
                    try { fn(); } catch (e) { /* one gate must not break another */ }
                });
            });
        }

        gateRegisterLivewireHook();
        document.addEventListener('livewire:load', gateRegisterLivewireHook);

        // Paint the banner. Pure DOM write — no state bookkeeping, so gateReapply
        // can reuse it without re-remembering what it just read.
        function gateRenderBanner(lines, heading) {
            var banner = document.getElementById('submit-error-banner');
            var list   = document.getElementById('submit-error-list');
            if (!banner || !list) return null;
            list.innerHTML = '';
            var seen = new Set();
            lines.forEach(function (line) {
                if (seen.has(line)) return;
                seen.add(line);
                var li = document.createElement('li');
                li.textContent = line;
                list.appendChild(li);
            });
            var strong = banner.querySelector('strong');
            if (strong && heading) strong.textContent = heading;
            banner.classList.remove('d-none');
            return banner;
        }

        /**
         * Show the banner AND remember it, so a later Livewire morph cannot erase it.
         *
         * @param {string}   source  'client' (our gate) or 'server' (publish-validation-failed)
         * @param {string[]} keys    field keys behind the lines; 'client' only
         */
        function gateShowBanner(lines, heading, source, keys) {
            var banner = gateRenderBanner(lines, heading);
            if (banner) gateRemember(source || 'client', lines, heading, keys);
            return banner;
        }

        function gateHideBanner() {
            var banner = document.getElementById('submit-error-banner');
            var list   = document.getElementById('submit-error-list');
            if (banner) banner.classList.add('d-none');
            if (list) list.innerHTML = '';
            // Stop re-applying a message we have just retracted, so a successful
            // validation pass stays clear through every subsequent render.
            gateForget();
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Whatever state the markup or a stale script left behind, the button is
            // clickable. Completeness is decided here, on click — never by disabling.
            var saveButton = document.getElementById('save-button');
            if (saveButton) {
                saveButton.classList.remove('disabled');
                saveButton.removeAttribute('disabled');
            }

            document.addEventListener('submit', function (e) {
                if (!e.target || e.target.id !== GATE_FORM_ID) return;

                gateHideBanner();

                var missing = gateMissingItems();
                if (missing.length === 0) {
                    if (typeof syncAllSelect2BeforeSave === 'function') syncAllSelect2BeforeSave();
                    return;
                }

                e.preventDefault();
                e.stopImmediatePropagation();

                var banner = gateShowBanner(
                    missing.map(function (m) { return m.label; }),
                    'Please complete the required fields before submitting.',
                    'client',
                    missing.map(function (m) { return m.key; })
                );
                gateNavigateTo(missing[0]);
                setTimeout(function () {
                    if (banner) banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 300);

                return false;
            }, true);
        });

        // The server is authoritative. When update() rejects — including a legacy draft
        // whose stored address predates the Phase 0 street-address rule — walk the user
        // to the tab owning the first failing field and show the server's own message.
        window.addEventListener('publish-validation-failed', function (ev) {
            var detail   = ev.detail || {};
            var fields   = detail.fields || [];
            var messages = detail.messages || {};
            if (!fields.length) return;

            // Re-applied verbatim after a morph: these are the server's own
            // messages and may name fields outside GATE_REQUIRED, so the
            // client-side recompute must not second-guess them.
            var banner = gateShowBanner(
                fields.map(function (name) {
                    return messages[name] || GATE_LABELS[name] || name;
                }),
                'Please correct the following before submitting.',
                'server',
                fields
            );

            var el = document.querySelector('[wire\\:model="' + fields[0] + '"], [wire\\:model\\.defer="' + fields[0] + '"], #' + fields[0]);
            if (el) {
                gateNavigateTo({ field: el, tab: el.closest('.tab-pane'), key: fields[0] });
            } else if (banner) {
                banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    })();
</script>
