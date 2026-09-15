/*
 |-----------------------------------------------------------------------------
 | Location DNA address lookup — the browser half
 |-----------------------------------------------------------------------------
 |
 | One job: hand a typed address to our own endpoint and hand back a coordinate.
 | It is not a geocoder, it does not know who the provider is, and it holds no
 | credential — because there is none to hold. The provider (the US Census
 | geocoder) is reached only by the server, and the only thing this file knows
 | about it is the path `/location/address-lookup`.
 |
 | THIS IS NOT A SECOND LOCATION DNA STATE MANAGER
 | -----------------------------------------------
 | It does not read the stored blob, does not write it, does not touch the
 | renderer and does not know what a radius or an Important Place is. Callers
 | own all of that. Keeping it that narrow is what lets the Radius Search box
 | and the Important Places rows share it without either learning the other's
 | shape.
 |
 | THE FAILURE CONTRACT, WHICH IS THE POINT OF THE WHOLE FILE
 | ----------------------------------------------------------
 | A failed lookup resolves — it does not reject — with `{ ok: false, message }`
 | and NO COORDINATE. There is no fallback provider, no Google, no 0,0, no
 | Florida centre and no "use the middle of the current viewport". A caller that
 | reads `lat` off a failure gets `undefined` rather than a plausible number,
 | which is what stops a failed lookup from quietly relocating something the
 | user already saved.
 |
 | IN-FLIGHT DEDUPE
 | ----------------
 | Identical queries that overlap in time share one request and one promise, so
 | a double-clicked button, a blur that fires alongside a click, and a row that
 | is re-serialised mid-lookup cost one round trip between them. The server
 | rations separately and does not trust this; the two are independent.
 */

/** Where the application's own lookup lives. Same origin, always. */
export const LOOKUP_URL = '/location/address-lookup';

/**
 * The sentence shown when the server could not be reached at all.
 *
 * Deliberately different from the server's own "could not be located": this one
 * means the request never got an answer, and telling somebody to add a ZIP code
 * when their connection dropped would send them off correcting an address that
 * was fine.
 */
export const TRANSPORT_MESSAGE = 'Address lookup is temporarily unavailable. Please try again in a moment.';

const RATE_LIMIT_MESSAGE = 'Too many address lookups just now. Wait a few seconds and try again.';
const SESSION_MESSAGE = 'Your session has expired. Reload the page and sign in again to look up an address.';

/** query -> in-flight promise. Cleared when the request settles. */
const inFlight = new Map();

/** The CSRF token Blade puts in the page head, or '' when there is none. */
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta && meta.getAttribute('content')) {
        return meta.getAttribute('content');
    }

    // Legacy Blade forms render @csrf as a hidden input and some of them predate
    // the meta tag. Falling back to it means the two non-Livewire criteria
    // surfaces work without their layouts having to change.
    const input = document.querySelector('input[name="_token"]');

    return input && input.value ? input.value : '';
}

/**
 * Normalise whatever the endpoint returned into the one shape callers handle.
 *
 * Anything unexpected — a missing key, a non-numeric coordinate, an HTML error
 * page parsed as JSON — becomes an ordinary failure rather than a half-built
 * success. A coordinate is only accepted when BOTH numbers are finite.
 */
function normaliseResponse(payload) {
    if (!payload || payload.ok !== true) {
        return {
            ok: false,
            message: (payload && typeof payload.message === 'string' && payload.message)
                ? payload.message
                : TRANSPORT_MESSAGE,
        };
    }

    const lat = Number(payload.lat);
    const lng = Number(payload.lng);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return { ok: false, message: TRANSPORT_MESSAGE };
    }

    return {
        ok: true,
        lat,
        lng,
        address: typeof payload.address === 'string' ? payload.address : '',
        precision: typeof payload.precision === 'string' ? payload.precision : '',
    };
}

async function request(query) {
    let response;

    try {
        response = await fetch(LOOKUP_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ address: query }),
        });
    } catch (error) {
        // Offline, DNS, aborted — never reached the server, so nothing about the
        // address itself is known.
        return { ok: false, message: TRANSPORT_MESSAGE };
    }

    if (response.status === 429) {
        return { ok: false, message: RATE_LIMIT_MESSAGE };
    }

    // 401 (unauthenticated) and 419 (expired CSRF token) are the same thing to
    // the person looking at the screen: sign in again.
    if (response.status === 401 || response.status === 419) {
        return { ok: false, message: SESSION_MESSAGE };
    }

    let payload = null;

    try {
        payload = await response.json();
    } catch (error) {
        payload = null;
    }

    if (!response.ok && (!payload || typeof payload.ok === 'undefined')) {
        return { ok: false, message: TRANSPORT_MESSAGE };
    }

    return normaliseResponse(payload);
}

/**
 * Locate one typed address.
 *
 * Always resolves. Never throws, never rejects, and never returns a coordinate
 * it did not receive from the server.
 *
 * @param {string} text what the user typed
 * @returns {Promise<{ok: true, lat: number, lng: number, address: string, precision: string}
 *                  | {ok: false, message: string}>}
 */
export function lookupAddress(text) {
    const query = String(text == null ? '' : text).trim();

    if (query === '') {
        return Promise.resolve({ ok: false, message: TRANSPORT_MESSAGE });
    }

    if (inFlight.has(query)) {
        return inFlight.get(query);
    }

    const pending = request(query).finally(() => {
        inFlight.delete(query);
    });

    inFlight.set(query, pending);

    return pending;
}

/**
 * Run one lookup with a button disabled for its duration.
 *
 * The second half of duplicate suppression, and the half a user can see: the
 * in-flight map already collapses identical overlapping requests, but a button
 * that stays live through a slow lookup invites the click that produced the
 * duplicate in the first place.
 *
 * Restores the button in a `finally`, so a thrown handler cannot leave the form
 * permanently dead.
 */
export function lookupWithButton(button, text) {
    if (!button) {
        return lookupAddress(text);
    }

    if (button.dataset.ldnaLookupBusy === '1') {
        return Promise.resolve({ ok: false, message: '' });   // already running; say nothing
    }

    button.dataset.ldnaLookupBusy = '1';
    button.disabled = true;

    return lookupAddress(text).finally(() => {
        delete button.dataset.ldnaLookupBusy;
        button.disabled = false;
    });
}

/** Test seam: forget every in-flight request. */
export function resetAddressLookup() {
    inFlight.clear();
}
