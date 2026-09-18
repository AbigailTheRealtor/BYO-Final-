@props([
    'listingType',
    'listingId',
    // Card presentation: the same control, laid out for a result card.
    // NOT a second component — see the note below.
    'compact' => false,
])

{{--
    Save | Maybe | Pass — the one shared control.

    REUSABLE BY CONSTRUCTION. It takes a listing reference and nothing else: no
    current state, no chip list, no route. Everything is resolved here through
    the same services the write path uses, so search results, Virtual Drive and
    recommendation surfaces can drop this in without a second copy of the
    business logic. Phase 2 wired the BYO Seller/Landlord detail pages; Phase 3A
    adds the four property result surfaces through the `compact` prop.

    ONE COMPONENT, TWO LAYOUTS, AND THAT IS THE WHOLE POINT. `compact` changes
    where the tray sits and how big the buttons are. It changes nothing about
    what is offered, what is written, or which endpoint is called — a card and a
    detail page reach the same controller through the same service, so a rule
    can never apply on one surface and not the other. A card-specific component
    would be the duplicate preference logic the governance forbids.

    RESULT PAGES PRIME, THEY DO NOT CHANGE THE CONTRACT. A page of cards calls
    `x-listing-preference.prefetch` first and this control then finds its state
    and context already resolved, so 150 cards cost a bounded number of queries
    instead of two each. A page that primes nothing still works: the control
    falls back to its own reads. Slower, never different.

    THE CHIPS COME FROM THE CATALOG, NEVER FROM THIS FILE. There is no reason
    list in this template and none in the JavaScript — a second list would be a
    second vocabulary, and the Fair Housing exclusions that travel with the
    governed reason catalog would not travel with it. (The catalog's config file
    is deliberately not named here: a test asserts ListingPreferenceConfig is its
    only reader, and a mention would register as a second one.)

    A GUEST SEES THE CONTROL AND IS SENT TO LOGIN. No anonymous row is created;
    `intended` preserves the listing so they come back to it.
--}}

@php
    use App\Services\ListingPreferences\ListingPreferenceReader;
    use App\Support\ListingPreferences\ListingPreferenceAvailability;
    use App\Support\ListingPreferences\ListingPreferenceChipCatalog;
    use App\Support\ListingPreferences\ListingPreferencePrefetch;
    use App\Support\ListingPreferences\ListingPreferenceState;
    use App\Support\SmartTags\SmartTagListingRef;
    use App\Support\SmartTags\SmartTagListingType;

    $lpRender    = false;
    $lpGuest     = false;
    $lpCompact   = $compact === true;
    $lpCurrent   = ['state' => null, 'reasons' => []];
    $lpChipToken = ListingPreferenceChipCatalog::UNRESOLVED_TOKEN;
    $lpChipBlob  = null;

    try {
        $lpType = SmartTagListingType::tryFrom((string) $listingType);
        $lpId   = (int) $listingId;

        if ($lpType !== null && $lpId > 0) {
            $lpRef      = new SmartTagListingRef($lpType, $lpId);
            $lpReader   = app(ListingPreferenceReader::class);
            $lpPrefetch = app(ListingPreferencePrefetch::class);

            // Phase 2's context obligation: resolve the real listing context
            // before offering chips, so the null-context fallback is reached
            // only when the data genuinely cannot answer. A primed page has
            // already done this in batch; the answer is identical either way,
            // because both come from the same reader.
            $lpContext = $lpPrefetch->hasContext($lpRef)
                ? $lpPrefetch->context($lpRef)
                : $lpReader->contextFor($lpRef);

            $lpAvail = ListingPreferenceAvailability::for(auth()->user(), $lpContext);

            $lpRender = $lpAvail->shouldRender();
            $lpGuest  = $lpAvail->isGuest();

            if ($lpRender) {
                // The chips themselves are the governed catalog, filtered by
                // state and context. What changes on a card page is only WHERE
                // the payload is written: once per context, not once per card.
                $lpCatalog   = app(ListingPreferenceChipCatalog::class);
                $lpChipToken = $lpCatalog->token($lpContext);
                $lpChipBlob  = $lpCatalog->takePayload($lpContext);

                if ($lpAvail->allowed) {
                    $lpUserId = (int) auth()->id();

                    $lpCurrent = $lpPrefetch->hasCurrent($lpUserId, $lpAvail->seekerRole, $lpRef)
                        ? $lpPrefetch->current($lpRef)
                        : $lpReader->current($lpUserId, $lpAvail->seekerRole, $lpRef);
                }
            }
        }
    } catch (\Throwable $e) {
        // A listing page must never fail because a preference control could
        // not be prepared. Rendering nothing is the safe degradation.
        $lpRender = false;
    }
@endphp

@if($lpRender)
{{-- The chip payload for this listing's context, written the first time that
     context appears in the response. Later controls sharing it carry only the
     token. A mixed sale/lease page emits one payload per context, which is what
     keeps each card's chips correct. --}}
@if($lpChipBlob !== null)
<script type="application/json" data-lp-chip-catalog="{{ $lpChipToken }}">@json($lpChipBlob)</script>
@endif

<div class="lp-control{{ $lpCompact ? ' lp-compact' : '' }}"
     data-lp-control
     @if($lpCompact) data-lp-compact="1" @endif
     data-lp-listing-type="{{ $listingType }}"
     data-lp-listing-id="{{ $listingId }}"
     data-lp-guest="{{ $lpGuest ? '1' : '0' }}"
     data-lp-login-url="{{ route('login') }}"
     data-lp-state-url="{{ route('listing-preferences.store') }}"
     data-lp-reasons-url="{{ route('listing-preferences.reasons') }}"
     data-lp-clear-url="{{ route('listing-preferences.destroy') }}"
     data-lp-csrf="{{ csrf_token() }}"
     data-lp-chip-context="{{ $lpChipToken }}"
     data-lp-current='@json($lpCurrent)'>

    <div class="lp-buttons" role="group" aria-label="Save, maybe or pass on this listing">
        @foreach(ListingPreferenceState::cases() as $lpState)
            <button type="button"
                    class="lp-btn lp-btn-{{ $lpState->value }}{{ $lpCurrent['state'] === $lpState->value ? ' is-active' : '' }}"
                    data-lp-state="{{ $lpState->value }}"
                    aria-pressed="{{ $lpCurrent['state'] === $lpState->value ? 'true' : 'false' }}">
                <i class="fa-regular {{ ['save' => 'fa-bookmark', 'maybe' => 'fa-circle-question', 'pass' => 'fa-circle-xmark'][$lpState->value] }}"></i>
                <span>{{ ucfirst($lpState->value) }}</span>
            </button>
        @endforeach
    </div>

    {{-- Reason tray. Populated from the catalog payload above, never from a
         list held in this template or in the script. --}}
    <div class="lp-tray" data-lp-tray hidden>
        <div class="lp-tray-prompt" data-lp-prompt></div>
        <div class="lp-chips" data-lp-chips></div>
        <div class="lp-tray-actions">
            <button type="button" class="lp-tray-done" data-lp-done>Done</button>
            <button type="button" class="lp-tray-clear" data-lp-clear>Remove</button>
        </div>
    </div>

    <div class="lp-status" data-lp-status role="status" aria-live="polite"></div>
</div>

{{-- @once: the component is reusable and a results page will render many of
     them; the stylesheet and the behaviour must be emitted exactly once. The
     script binds by delegation for the same reason. --}}
@once
<style>
.lp-control { margin-bottom: .5rem; }
.lp-buttons { display: flex; gap: .35rem; }
.lp-btn {
    flex: 1 1 0; display: inline-flex; align-items: center; justify-content: center; gap: .35rem;
    padding: .5rem .35rem; font-size: .78rem; font-weight: 600; line-height: 1.1;
    border: 1px solid #cbd5e1; border-radius: .5rem; background: #fff; color: #334155; cursor: pointer;
}
.lp-btn:hover { background: #f8fafc; }
.lp-btn i { font-size: .8rem; }
.lp-btn.is-active { color: #fff; }
.lp-btn-save.is-active  { background: #2563eb; border-color: #2563eb; }
.lp-btn-maybe.is-active { background: #b45309; border-color: #b45309; }
.lp-btn-pass.is-active  { background: #64748b; border-color: #64748b; }
.lp-btn[disabled] { opacity: .6; cursor: default; }
.lp-tray { margin-top: .5rem; padding: .6rem; border: 1px solid #e2e8f0; border-radius: .5rem; background: #f8fafc; }
.lp-tray-prompt { font-size: .76rem; font-weight: 700; color: #475569; margin-bottom: .45rem; }
.lp-chips { display: flex; flex-wrap: wrap; gap: .3rem; }
.lp-chip {
    padding: .25rem .55rem; font-size: .72rem; border: 1px solid #cbd5e1; border-radius: 999px;
    background: #fff; color: #475569; cursor: pointer;
}
.lp-chip.is-selected { background: #1e293b; border-color: #1e293b; color: #fff; }
.lp-tray-actions { display: flex; gap: .4rem; margin-top: .55rem; }
.lp-tray-done, .lp-tray-clear {
    padding: .3rem .7rem; font-size: .74rem; font-weight: 600; border-radius: .4rem; cursor: pointer;
}
.lp-tray-done { background: #1e293b; border: 1px solid #1e293b; color: #fff; }
.lp-tray-clear { background: #fff; border: 1px solid #cbd5e1; color: #b91c1c; }
.lp-status { font-size: .72rem; color: #64748b; margin-top: .3rem; min-height: 1em; }
.lp-status.is-error { color: #b91c1c; }

/*
 | COMPACT — the card layout of the same control.
 */
.lp-compact { margin-bottom: 0; }
.lp-compact .lp-buttons { gap: .25rem; }
.lp-compact .lp-btn { padding: .3rem .25rem; font-size: .68rem; gap: .25rem; border-radius: .4rem; }
.lp-compact .lp-btn i { font-size: .7rem; }

/*
 | A FLOOR ON THE BUTTON'S HEIGHT, because it was a third-party font away from
 | collapsing.
 |
 | The icon is Font Awesome, served from a CDN. Its glyph is what gives an
 | icon-only button its height — so when that request fails, the button has no
 | content with height and shrinks to its padding. Measured at 12px on a phone:
 | present, clickable in theory, and far too small to hit.
 |
 | Paired with the note below: the labels are no longer hidden, so this floor is
 | belt and braces rather than the only thing holding the control up.
 */
.lp-compact .lp-btn { min-height: 28px; }
/*
 | THE TRAY STAYS IN FLOW, AND THAT IS NOT THE OBVIOUS CHOICE.
 |
 | It was `position: absolute`, so that opening a tray would float it over the
 | neighbouring cards and leave the card its original height. That is the nicer
 | behaviour and it did not work: BOTH card grids set `overflow: hidden` on
 | `.card` (for the rounded corners), and an absolutely-positioned descendant of
 | a clipping ancestor is clipped by it.
 |
 | The failure was invisible to every assertion that looked like it would catch
 | it. `getBoundingClientRect()` still reported a 240px box, and Playwright's
 | `toBeVisible()` still passed, because the element WAS laid out — it was simply
 | painted nowhere. Measured on the real pages: the tray extended 236px past the
 | bottom of its card, and `document.elementFromPoint()` at a chip's own centre
 | returned an element belonging to a DIFFERENT card. The reasons were
 | unreachable on every card surface.
 |
 | In flow, the card grows while the tray is open. That re-flows the row, which
 | is ordinary accordion behaviour in a card grid, and it is the difference
 | between a feature that works and one that is merely present in the DOM.
 |
 | DO NOT restore `position: absolute` without also removing the clipping from
 | the card, which is somebody else's styling. `position: fixed` would escape the
 | clip, but it needs JavaScript to place and re-place the panel on scroll and
 | resize — a real popover implementation, not a presentation tweak.
 |
 | Closing the other open tray (see the script below) keeps at most one card
 | expanded at a time.
 */
.lp-compact .lp-tray { margin-top: .3rem; }

/*
 | THE CHIP LIST SCROLLS, NOT THE TRAY.
 |
 | Capping the whole tray kept one card from growing enormously, and put Done
 | and Remove below the scroll fold: the panel's primary action was invisible
 | until the customer scrolled inside a 240px box they had no reason to think
 | was scrollable. Observed on every card surface at every width.
 |
 | Bounding the chips instead keeps the prompt above and the actions below
 | always in view, and still stops twenty reasons from making one card enormous.
 */
.lp-compact .lp-chips { max-height: 9rem; overflow-y: auto; }
.lp-compact .lp-status { margin-top: .2rem; }

/*
 | THE LABELS STAY, AT EVERY WIDTH.
 |
 | They were hidden below 400px on the assumption that three labelled buttons
 | could not fit a phone-width card. Measured, they fit easily: at a 320px
 | viewport each button is 85px wide and the widest label ("Maybe") needs 42px
 | including its icon, on one row, with no overflow.
 |
 | Hiding them was also what produced the 12px button above — an icon-only
 | control whose entire height came from a CDN font. "Save", "Maybe" and "Pass"
 | are three short words that say what the control does; dropping them bought
 | nothing and cost both legibility and a usable tap target.
 */
</style>

<script>
(function () {
    'use strict';

    // One delegated listener for every control on the page — a results page may
    // render dozens, and per-control binding would multiply with them.
    document.addEventListener('click', function (event) {
        var root = event.target.closest('[data-lp-control]');
        if (!root) { return; }

        var stateBtn = event.target.closest('[data-lp-state]');
        var chip     = event.target.closest('[data-lp-chip]');
        var done     = event.target.closest('[data-lp-done]');
        var clear    = event.target.closest('[data-lp-clear]');

        if (!stateBtn && !chip && !done && !clear) { return; }
        event.preventDefault();

        // A guest gets the existing login flow, carrying the page they were on
        // so they return to this listing. No anonymous preference is created.
        if (root.getAttribute('data-lp-guest') === '1') {
            var login = root.getAttribute('data-lp-login-url');
            window.location.href = login + '?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
            return;
        }

        if (stateBtn) { setState(root, stateBtn.getAttribute('data-lp-state')); return; }
        if (chip)     { chip.classList.toggle('is-selected'); return; }
        if (done)     { submitReasons(root); return; }
        if (clear)    { clearPreference(root); return; }
    });

    function payload(root, extra) {
        return Object.assign({
            listing_type: root.getAttribute('data-lp-listing-type'),
            listing_id:   parseInt(root.getAttribute('data-lp-listing-id'), 10)
        }, extra || {});
    }

    function send(root, url, method, body) {
        setStatus(root, '', false);
        return fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': root.getAttribute('data-lp-csrf'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'That could not be saved.');
                }
                return data;
            });
        });
    }

    function setState(root, state) {
        var selected = currentSelection(root);
        send(root, root.getAttribute('data-lp-state-url'), 'POST', payload(root, {
            state: state,
            // A state change does not carry the previous state's reasons: they
            // answered a different question. The server enforces this too.
            reasons: []
        })).then(function (data) {
            applyCurrent(root, data);
            openTray(root, data.state);
        }).catch(function (err) { setStatus(root, err.message, true); });
        return selected;
    }

    function submitReasons(root) {
        // ONE request carrying the final set, so one history event — not one
        // per chip click.
        send(root, root.getAttribute('data-lp-reasons-url'), 'POST', payload(root, {
            reasons: currentSelection(root)
        })).then(function (data) {
            applyCurrent(root, data);
            closeTray(root);
            setStatus(root, 'Saved.', false);
        }).catch(function (err) { setStatus(root, err.message, true); });
    }

    function clearPreference(root) {
        send(root, root.getAttribute('data-lp-clear-url'), 'DELETE', payload(root, {}))
            .then(function (data) {
                applyCurrent(root, data);
                closeTray(root);
                setStatus(root, 'Removed.', false);
            }).catch(function (err) { setStatus(root, err.message, true); });
    }

    function currentSelection(root) {
        return Array.prototype.map.call(
            root.querySelectorAll('[data-lp-chip].is-selected'),
            function (el) { return el.getAttribute('data-lp-chip'); }
        );
    }

    function applyCurrent(root, data) {
        var state = data.state || null;
        root.setAttribute('data-lp-current', JSON.stringify({
            state: state,
            reasons: (data.reasons || []).map(function (r) { return r.key; })
        }));

        Array.prototype.forEach.call(root.querySelectorAll('[data-lp-state]'), function (btn) {
            var active = btn.getAttribute('data-lp-state') === state;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    /*
     | The chip catalog is written into the page once per CONTEXT and looked up
     | by token, rather than copied onto every control. Parsed once per token
     | and memoised: a results page opening ten trays parses one payload.
     */
    var catalogCache = {};

    function catalogFor(token) {
        if (Object.prototype.hasOwnProperty.call(catalogCache, token)) {
            return catalogCache[token];
        }

        var el = document.querySelector('[data-lp-chip-catalog="' + token + '"]');
        var parsed = {};

        if (el) {
            try { parsed = JSON.parse(el.textContent || '{}'); } catch (e) { parsed = {}; }
        }

        catalogCache[token] = parsed;
        return parsed;
    }

    function openTray(root, state) {
        if (!state) { closeTray(root); return; }

        var chips = catalogFor(root.getAttribute('data-lp-chip-context') || '');
        var forState = chips[state];
        var tray = root.querySelector('[data-lp-tray]');
        if (!tray || !forState) { return; }

        root.querySelector('[data-lp-prompt]').textContent = forState.prompt;

        var current = JSON.parse(root.getAttribute('data-lp-current') || '{}');
        var selected = current.reasons || [];

        var host = root.querySelector('[data-lp-chips]');
        host.textContent = '';
        forState.chips.forEach(function (c) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'lp-chip' + (selected.indexOf(c.key) !== -1 ? ' is-selected' : '');
            b.setAttribute('data-lp-chip', c.key);
            b.textContent = c.label;
            host.appendChild(b);
        });

        tray.hidden = false;
    }

    function closeTray(root) {
        var tray = root.querySelector('[data-lp-tray]');
        if (tray) { tray.hidden = true; }
    }

    function setStatus(root, message, isError) {
        var el = root.querySelector('[data-lp-status]');
        if (!el) { return; }
        el.textContent = message;
        el.classList.toggle('is-error', !!isError);
    }

    /*
     | Reopening a DETAIL page with an existing choice shows its reasons.
     |
     | NEVER on a card. A results page where the customer has already decided
     | about twelve listings would open twelve trays at once — overlapping
     | panels covering the cards beneath them, and a reason editor nobody asked
     | for. On a card the tray opens when they press a state, and only then.
     */
    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-lp-control]'), function (root) {
            if (root.getAttribute('data-lp-compact') === '1') { return; }
            var current = JSON.parse(root.getAttribute('data-lp-current') || '{}');
            if (current.state) { openTray(root, current.state); }
        });
    });

    /*
     | One open card tray at a time, and a click elsewhere closes it. Card trays
     | float over the cards beside them, so two at once overlap; the tray is not
     | modal and must not trap the page.
     |
     | This listener runs BEFORE the delegated handler above only by document
     | order, so it must never close the tray that click is about to open — hence
     | the `closest` check.
     */
    document.addEventListener('click', function (event) {
        var inside = event.target.closest('[data-lp-control]');

        Array.prototype.forEach.call(document.querySelectorAll('[data-lp-compact="1"]'), function (root) {
            if (root !== inside) { closeTray(root); }
        });
    }, true);
})();
</script>
@endonce
@endif
