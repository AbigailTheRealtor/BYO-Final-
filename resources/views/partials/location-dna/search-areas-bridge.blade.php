{{--
    Phase 9D — Search Areas Livewire bridge (shared).

    Hidden inputs + JS that carry the map widget's serialized state
    (`ldna-json-field` / `ldna-important-places-field` emitted by
    partials/location-dna/map-input.blade.php) back into the host Livewire
    component's `$location_dna_preferences_json` / `$important_places_json`
    props. Originally extracted verbatim from the Create-Offer buyer/tenant
    tabs; those tabs now include this partial too, so all four Search Areas
    tabs — Hire Buyer, Hire Tenant, Offer Buyer, Offer Tenant — share this one
    copy and no inline duplicate remains.

    Include this once, immediately AFTER the map-input @include, in any tab that
    hosts the Search Areas map. The host component must declare the two props
    (via App\Http\Livewire\Concerns\HasSearchAreas +
    App\Http\Livewire\OfferListing\Concerns\HasImportantPlaces).

    The `_ldnaSearchAreasBridgeReady` guard below is deliberately PAGE-GLOBAL,
    not per-tab. Everything it protects is global — `window.ldnaSerialize`,
    the fixed `ldna-*` DOM ids emitted by map-input, and the two `Livewire.hook`
    registrations — so re-running this block would double-wrap and double-fire
    rather than set up a second widget. Exactly one Search Areas tab renders per
    page: every parent view selects one via a mutually exclusive
    `@if ($user_type === ...)` chain. Panel-scoped state keys off `$mapPanelId`
    instead (see `ldnaInit_*` in map-input); page-global singletons key off this
    flag. Before the tabs were consolidated the Offer copies used their own
    `_ldnaBuyerBridgeReady` / `_ldnaTenantBridgeReady` names, which tracked which
    TAB had rendered rather than which workflow — misleading, since a component
    renders another role's tab whenever `$user_type` says so.
--}}
<input type="hidden" id="ldna-livewire-bridge" wire:model.defer="location_dna_preferences_json">
<input type="hidden" id="ldna-ip-livewire-bridge" wire:model.defer="important_places_json">
<script>
(function () {
    /* Guard: prevent re-wrapping ldnaSerialize on Livewire morphdom re-renders */
    if (window._ldnaSearchAreasBridgeReady) return;
    window._ldnaSearchAreasBridgeReady = true;

    function syncLdnaBridge() {
        var src = document.getElementById('ldna-json-field');
        if (!src || !src.value) return;
        var val = src.value;
        /* Keep wire:model.defer path for broad compatibility */
        var dst = document.getElementById('ldna-livewire-bridge');
        if (dst) {
            dst.value = val;
            dst.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /* 9C: mirror the Important Places field into its own Livewire bridge. Unlike the
       Search-Areas blob, an EMPTY value is meaningful (all rows removed) and is synced
       so clearing every place persists as "no important places". */
    function syncIpBridge() {
        var src = document.getElementById('ldna-important-places-field');
        if (!src) return;
        var dst = document.getElementById('ldna-ip-livewire-bridge');
        if (dst) {
            dst.value = src.value;
            dst.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /* Wrap ldnaSerialize so every map interaction pushes value to Livewire */
    var _origSerialize = window.ldnaSerialize;
    window.ldnaSerialize = function () {
        if (typeof _origSerialize === 'function') _origSerialize();
        syncLdnaBridge();
    };

    /* Wrap ldnaIpSerialize so every Important Places edit pushes value to Livewire */
    var _origIpSerialize = window.ldnaIpSerialize;
    window.ldnaIpSerialize = function () {
        if (typeof _origIpSerialize === 'function') _origIpSerialize();
        syncIpBridge();
    };

    /* Initial sync on page load */
    document.addEventListener('DOMContentLoaded', function () { syncLdnaBridge(); syncIpBridge(); });
    document.addEventListener('livewire:load', function () { syncLdnaBridge(); syncIpBridge(); });

    /* ── Pre-save hook: inject map JSON into Livewire's updateQueue ────────────
       In Livewire v2, message.sent fires BEFORE xhr.send() and receives the
       Message object. Its updateQueue is serialised as the 'updates' array in
       the XHR body — pushing a syncInput here is the ONLY reliable way to get
       the current map state to the server.
       WARNING: mutating component.data (= serverMemo.data) is UNSAFE because
       serverMemo is HMAC-signed; tampering causes a 403 / silent rejection.    */
    document.addEventListener('livewire:load', function () {
        if (window.Livewire && typeof Livewire.hook === 'function') {

            Livewire.hook('message.sent', function (message, component) {
                /* Inject the current map state as a syncInput update.
                   Livewire applies syncInputs BEFORE calling the action method,
                   so $location_dna_preferences_json is set before saveDraft(). */
                var src = document.getElementById('ldna-json-field');
                if (src && src.value) {
                    message.updateQueue = message.updateQueue.filter(function (u) {
                        return !(u.type === 'syncInput' && u.payload &&
                                 u.payload.name === 'location_dna_preferences_json');
                    });
                    message.updateQueue.push({
                        type: 'syncInput',
                        payload: { name: 'location_dna_preferences_json', value: src.value }
                    });
                }

                /* 9C: inject Important Places JSON independently (empty string allowed —
                   it clears all rows). Not gated by the Search-Areas blob above. */
                var ipSrc = document.getElementById('ldna-important-places-field');
                if (ipSrc) {
                    message.updateQueue = message.updateQueue.filter(function (u) {
                        return !(u.type === 'syncInput' && u.payload &&
                                 u.payload.name === 'important_places_json');
                    });
                    message.updateQueue.push({
                        type: 'syncInput',
                        payload: { name: 'important_places_json', value: ipSrc.value }
                    });
                }
            });

            /* ── Post-render re-sync ──────────────────────────────────────────
               After any Livewire morphdom update (e.g. county auto-complete),
               ldna-livewire-bridge gets reset to the server-side value.
               Re-dispatching keeps the wire:model.defer value current so the
               next save action carries the right JSON as a belt-and-suspenders
               fallback alongside the message.sent injection above.             */
            Livewire.hook('message.processed', function (message, component) {
                syncLdnaBridge();
                syncIpBridge();
            });
        }
    });
})();
</script>
