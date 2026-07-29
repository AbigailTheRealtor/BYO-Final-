# Phase 2B — Search Areas Geometry Contract

**Status:** Characterised · 2026-07-29
**Branch:** `phase-2-spatial/ui-repair-maplibre-basemap`
**Roadmap:** [`../spatial-integration-roadmap.md`](../spatial-integration-roadmap.md) § Phase 2B
**Scope:** characterisation only — **zero production files modified**

---

## 1. Why this document exists

Phase 2C criterion #7 — *"geometry round-trips byte-identically through the new renderer"* — was **unfalsifiable** before this work. No recorded baseline existed to compare a replacement renderer against, so the criterion could only ever have been assessed by eye, after the change, against a memory of how things used to behave.

This document and its three test files are that baseline. They record what the Search Areas geometry contract **is** at commit `0c7266a0e`, before any renderer work touches it.

Everything here is **descriptive**. Where the current behaviour is surprising or wrong, it is recorded as a finding and left alone. Phase 2B changes no production file.

---

## 2. The contract

### 2.1 Storage

One EAV meta key, `location_dna_preferences`, holds a JSON blob with nine keys. Three discrete meta keys — `state`, `counties`, `cities` — are **mirrored out of** that blob on save. The blob is authoritative; the mirrors are derived, and exist because Ask AI, the match engine, filtering and public listing display read them.

| Key | Type | Shape |
|---|---|---|
| `cities` | array of string | tag list |
| `zip_codes` | array of string | tag list |
| `neighborhoods` | array of string | tag list |
| `counties` | array of string | tag list |
| `state` | string | trimmed |
| `polygons` | array | `{ label, path: [{ lat, lng }] }` |
| `radius_searches` | array | `{ lat, lng, radius_miles, address \| label }` |
| `flexible_location` | boolean | checkbox state |
| `location_notes` | string | trimmed free text |

`radius_searches` entries carry **either** `address` (when derived from a resolved address) **or** `label` (when hand-drawn) — never both.

**Radius is stored in miles**, converted from the overlay's metres by a `1609.34` divisor. A renderer that reported metres instead would multiply every saved radius by ~1,609 without erroring.

### 2.2 The PHP path

`app/Http/Livewire/Concerns/HasSearchAreas.php`:

```
loadSearchAreas($auction)
  → hydrateDiscreteLocationFromBlob()
    → saveSearchAreas($auction)
```

### 2.3 The single most important property

> **The blob is opaque to PHP on the save path.** `saveSearchAreas()` writes `$location_dna_preferences_json` to meta **verbatim**. It never decodes and re-encodes the geometry.

Byte-identity across the PHP layer is therefore *structural*, not lucky: no float can be truncated, no key reordered, no unicode escape reshaped, because nothing parses it. Only `cities` is read back out of the blob, and only to build its mirror.

**The corollary is the point.** Criterion #7's real risk lives **entirely on the JavaScript side** — in `window.ldnaSerialize` — which no test in this repository can execute. See §4.

---

## 3. Transport — two structurally different paths

The blob reaches the server two different ways depending on the page. Both must survive Phase 2C.

| Path | Sites | Mechanism |
|---|---|---|
| **Livewire bridge** | 4 | `#ldna-json-field` → hidden input `wire:model.defer="location_dna_preferences_json"`, plus a `syncInput` injected into Livewire's `updateQueue` on `message.sent` |
| **Plain form POST** | 4 | the widget's `<textarea name="location_dna_preferences">` submitted with the surrounding form; no Livewire involved |

The Livewire bridge injects via `updateQueue` rather than mutating `component.data`, because `serverMemo` is HMAC-signed and tampering yields a 403 or a silent rejection. Any 2C transport rewrite must preserve this or reproduce that bug.

### The eight include sites

```
livewire/hire-buyer-agent/…/property-preferences.blade.php     Livewire · shared bridge partial
livewire/tenant-agent-auction-tabs/…/property-details.blade.php Livewire · shared bridge partial
livewire/offer-listing/offer-buyer-tabs/…/property-preferences.blade.php   Livewire · inline bridge
livewire/offer-listing/offer-tenant-tabs/…/property-details.blade.php      Livewire · inline bridge
buyer_criteria/add.blade.php                                    form POST
buyer_criteria/edit.blade.php                                   form POST
tenant_criteria/add.blade.php                                   form POST
tenant_criteria/edit.blade.php                                  form POST
```

This enumeration is **asserted complete** by `test_the_include_site_enumeration_is_complete`, which walks `resources/views` and fails if any unlisted file includes the widget. A stale list would let 2C rewrite the renderer against an incomplete map of its consumers.

---

## 4. ⚠️ What this does **not** cover

**This project has no JavaScript test runner.**

The blob is produced in the browser by `window.ldnaSerialize`. That function is beyond what any test in this repository can execute. `SearchAreasWidgetContractTest` asserts on **source text only** — it does not execute JavaScript, render Blade, or open a browser.

| Covered | Not covered |
|---|---|
| PHP load → hydrate → save logic | Whether `ldnaSerialize` emits correct geometry at runtime |
| Byte-identity through real EAV storage | Whether map events fire and trigger serialisation |
| Discrete mirror derivation | Whether the Livewire bridge actually syncs in a browser |
| Blob keys and geometry shapes **as written in source** | Precision or shape changes introduced at runtime |
| Include-site enumeration | Rendered DOM, user interaction, save/restore round trip in a live page |

> A green 2B run means **"the source still says what it said"** and **"the PHP contract holds"**. It does **not** mean the widget works. **Structural PHP assertions must never be reported as browser or runtime JavaScript coverage.**

Closing this gap requires a JS test runner and is out of 2B scope. Until then, Phase 2C criterion #7 needs a **manual, demonstrated** save-and-restore check on a real page in addition to these tests.

This is the same technique — and the same limitation — as the Phase 1 payload-guard assertions.

---

## 5. Findings

Recorded, **not corrected**. Each is pre-existing; none was introduced by 2B. Fixing any of them is a behaviour change and therefore outside characterisation scope.

### FINDING 2B-1 · `?? ''` does not normalise `info()`'s missing-key return

The models' `info()` returns boolean **`false`** for an absent meta key. The trait then does:

```php
$this->location_dna_preferences_json = $ldnaRaw ?? '';
```

`??` substitutes only for `null`, so a record with no blob leaves the property as boolean `false` — not the empty string its `= ''` default and its "Raw blob JSON" docblock both imply.

On the next save that `false` is persisted unchanged. It stores as `"0"`, and `json_decode("0")` is integer `0` — **valid JSON**. So a consumer doing `json_decode($blob, true)['cities']` does not get a clean null from a decode failure; it performs an array-offset read on an integer.

*An earlier draft of the test asserted the stored value was undecodable JSON. That was wrong, and the correction is recorded here because the actual behaviour is a strictly worse failure mode than the one predicted.*

**Consequence:** low in practice — the `cities` mirror still writes `[]` because `json_decode(false)` is `null` and the `?? []` fallback catches it. Recorded because it is a latent trap for any future consumer.

### FINDING 2B-2 · The discrete-mirror contract has five implementations

`HasSearchAreas` is used by the four **Hire** components only. The four **Offer** components each carry their own inline `hydrateDiscreteLocationFromBlob()`. The four copies are byte-identical to one another and differ from the trait only in the trait's `property_exists` guards, which they omit.

This is the same 5→1 shape Phase 1 resolved for `fillFromGooglePlaces()`, still open for Search Areas.

### FINDING 2B-3 · ✅ **RESOLVED 2026-07-29** — Tenant Offer components now write the `cities` mirror

**The defect as found:** `saveMeta('cities', …)` was present in the trait and both Buyer Offer components, and **absent from both Tenant Offer components**. The discrete `cities` meta therefore went stale relative to the blob for every Tenant offer listing — a live data-correctness defect, since `cities` is read by Ask AI, the match engine, filtering and public display.

| Component | writes `cities` mirror |
|---|---|
| `HasSearchAreas` (4 Hire components) | ✅ (unchanged) |
| `BuyerOfferListing` / `BuyerOfferListingEdit` | ✅ (unchanged) |
| `TenantOfferListing` / `TenantOfferListingEdit` | ✅ **added 2026-07-29** |

**Why the fix is four methods, not two.** A dev-database audit found **31 of 46** tenant records (67%) holding real city data in the mirror with **no blob at all** — adding only the mirror write would have persisted `[]` and destroyed that data on first save. The fix therefore pairs each mirror write with a **legacy-cities merge on the hydration path**, so a legacy record recovers its cities into the blob before the mirror is ever derived from it.

| # | Method | Change |
|---|---|---|
| 1 | `TenantOfferListingEdit::loadAuctionData()` | legacy-cities merge |
| 2 | `TenantOfferListingEdit::update()` | mirror write |
| 3 | `TenantOfferListing::loadDraft()` | legacy-cities merge |
| 4 | `TenantOfferListing::saveAllMetadata()` | mirror write |

`loadDraft()` was included because it is a second hydration path feeding the same save path; fixing only the edit flow would have left drafts destructive.

#### The hydration invariant

> **`location_dna_preferences.cities` is the single source of truth whenever that key EXISTS.** The legacy `cities` mirror may be consulted **only** when the blob carries no `cities` key at all, and may never overwrite an existing blob value — **including an intentionally empty array**.

This is enforced with `array_key_exists()`, **not** `empty()`. The distinction between *key missing* and *key present but empty* is the core behavioural contract: an intentionally cleared blob stores `"cities": []`, and `empty()` collapses that into the same state as a missing key, which would let the legacy mirror **resurrect cities the user had just deleted**.

> ⚠️ **Deliberate divergence from the Hire flow.** `HasSearchAreas::loadSearchAreas()` uses `empty()` and therefore **cannot honour this invariant**. The trait was left unchanged — altering it would change all four Hire flows, which is outside this scope. The divergence is intentional, pinned by a test, and means the trait carries the same latent resurrection defect. **Aligning the trait is separately scoped future work.**

### FINDING 2B-4 · The Livewire bridge is duplicated three ways

| Implementation | Guard flag | Used by |
|---|---|---|
| `partials/location-dna/search-areas-bridge.blade.php` | `_ldnaSearchAreasBridgeReady` | 2 Hire tabs |
| inline in `offer-buyer-tabs/…/property-preferences.blade.php` | `_ldnaBuyerBridgeReady` | 1 |
| inline in `offer-tenant-tabs/…/property-details.blade.php` | `_ldnaTenantBridgeReady` | 1 |

Three flags means three independently evolvable copies of the transport carrying every saved geometry.

### FINDING 2B-5 · Four legacy pages use a different transport entirely

The `buyer_criteria` and `tenant_criteria` add/edit pages carry no Livewire bridge at all — they post the widget's textarea with the surrounding form. 2C must preserve both transports or it breaks four pages silently.

### FINDING 2B-6 · The widget's renderer is Google Maps — the concrete form of the 2C licence blocker

`ldnaSerialize` reads geometry directly off Google overlay objects (`getPath()`, `getCenter()`, `getRadius()`), and geometry edits are driven by `google.maps.event` listeners. `map-input.blade.php` carries **49** Google references.

Swapping the basemap beneath this displays **Google Maps Content over a non-Google basemap**. This is the licence-ordering constraint (D1) in its concrete form, and it is why 2C cannot begin on renderer authorisation alone.

---

## 6. Standing constraint — countdown timer independence

**The countdown timer must never be coupled to, derived from, or dependent on an expiration date.** It must remain an independent component with its own lifecycle and logic.

Verified 2026-07-29: **no such coupling exists in the Phase 2 target area.**

| File | `countdown` | `expir*` |
|---|---|---|
| `partials/location-dna/map-input.blade.php` | 0 | 0 |
| `partials/location-dna/search-areas-bridge.blade.php` | 0 | 0 |
| `components/location-dna-map.blade.php` | 0 | 0 |
| `Concerns/HasSearchAreas.php` | 0 | 0 |

The countdown timer in this codebase is a view-layer toast/notification progress option (`countdown: true`) and is **not** driven by `expires_at`, `bidding_end` or `ExpireOffersCommand`.

> **Recorded, not enforced.** Characterisation can record that the coupling is absent today; it cannot prevent it appearing later. An enforcement guard test is an **optional, separately scoped task**, deliberately excluded from 2B (owner decision, 2026-07-29).

---

## 7. Test files

| File | Tests | Kind |
|---|---:|---|
| `tests/Unit/Spatial/SearchAreasGeometryContractTest.php` | 16 | Pure PHP, no DB |
| `tests/Feature/Spatial/SearchAreasPersistenceCharacterisationTest.php` | 9 | Real EAV storage |
| `tests/Unit/Spatial/SearchAreasWidgetContractTest.php` | 15 | Structural source assertions |
| `tests/Feature/Spatial/TenantOfferCitiesMirrorTest.php` | 15 | FINDING 2B-3 fix — behavioural |

**55 tests total.** Vehicle for the persistence and mirror suites is `TenantAgentAuction` — a real host of the trait and the only one with a factory. The characterisation suites exercise the trait through a thin host object, because the full Livewire components carry hundreds of unrelated required props and booting one would characterise its validation rather than the geometry contract. `TenantOfferCitiesMirrorTest` instead calls the **real** `loadAuctionData()`, `loadDraft()` and `saveAllMetadata()` against real records, because the invariant it protects lives in those exact methods.

**Non-vacuity verified.** The invariant tests were confirmed to fail when the implementation is reverted: swapping `array_key_exists()` back to `empty()` fails 2 tests; removing the mirror write fails 4. A test that cannot fail proves nothing, so this was measured rather than assumed.

---

## 8. Manual browser verification — required, not optional

The Search Areas blob is produced by `window.ldnaSerialize` and carried by a JavaScript bridge. §4 explains why no test here can execute either. **These steps must be run by hand before the FINDING 2B-3 fix is trusted in production.**

Use a legacy record — mirror populated, no blob. `scripts/audit-2b3-cities-mirror.php` identifies candidates.

### Edit flow

| # | Action | Expected |
|---|---|---|
| 1 | Open a legacy record in Tenant Offer edit | City tags render, populated from the legacy mirror |
| 2 | Reload without saving | Same cities — load is non-destructive |
| 3 | **Save with no changes** | Blob gains `cities`; mirror unchanged. **Critical** — proves the bridge synced the server-rendered blob with no user interaction |
| 4 | Reload after step 3 | Cities still present, now sourced from the blob |
| 5 | Add a city → save → reload | Both blob and mirror contain the addition |
| 6 | Remove one city → save → reload | Removal persists in both; the others survive |
| 7 | **Clear all cities → save → reload** | Both `[]`. **Cities must NOT reappear** — proves the load-time merge cannot resurrect an intentional clear |
| 8 | Save again after step 7 | Still `[]` — no oscillation |

### Draft flow

Repeat steps 1, 3, 5 and 7 through `saveDraft()` / `saveDraftOnly()`, then confirm the versioned clone carries the same mirror as its source.

### Regression spot-checks

One Buyer Offer record and one Hire Tenant record through add / remove / clear — behaviour must be **visibly unchanged**.

> **Steps 3 and 7 are the two that can fail.** Step 3 is the untestable bridge assumption; step 7 is the merge/clear interaction. If either misbehaves, the fix is wrong as designed and must not ship.

---

## 9. G0 — geometry-preservation guard (⚠️ temporary limitation)

**Implemented 2026-07-29 · `resources/views/partials/location-dna/map-input.blade.php` · authorisation gate G0**

### The defect

`ldnaSerialize()` rebuilt `polygons` and `radius_searches` from `ldnaOverlays` — the geometry editor's working set — on **every** call. When the editor never hydrated (dead Google credential, tile failure, hidden container, provider error) that set is empty, so editing **any unrelated dimension** — a city, a ZIP, the notes field — serialized `polygons: []` and `radius_searches: []`, and the next save **destroyed the saved geometry**.

An unhydrated editor is not a user intent to clear. Page load alone was safe, because `ldnaSerialize()` runs only from interaction handlers; the trigger was editing a non-geometry dimension while the renderer was down.

### The guard

A renderer-independent flag, `ldnaGeometryHydrated`, defaults to `false` and is set **once, after both hydration loops complete**. The geometry rebuild is gated on it; when unhydrated, the server-seeded values are left untouched.

**Naming is deliberate.** Not `ldnaGeometryRestored` — the serializer must not care whether geometry came from Google, MapLibre, server hydration, cache, or a future provider. Not `ldnaCanonicalStateReady` — the canonical state *is* ready at page load (`ldnaState` is seeded server-side); it is the *editor's working set* that is not, and conflating the two is the bug itself. Not `ldnaMapInitialized` — that flag is set on **entry** to `ldnaInitMap()`, ~70 lines before hydration finishes, leaving a race window in which overlays are empty while the renderer reports itself ready.

### ⚠️ Temporary limitation — accepted interim trade-off

> **While the geometry editor is unavailable, geometry cannot be intentionally cleared either.** From the client alone, "the user deleted every shape" and "the editor never loaded" are indistinguishable.

This is the correct trade — silently destroying geometry is far worse than temporarily being unable to delete it — but it **is** a real behaviour change, accepted by the owner on 2026-07-29 as an interim measure.

**This guard is not the permanent design.** G1 introduces explicit dimension-level clear operations (`{dimension, op: 'clear'}`) and server-authoritative patch merging, which distinguish the two cases properly and remove this limitation. G0 must not be treated as the final answer, and the limitation must not be allowed to become undocumented folklore.

Explicit clearing continues to work normally whenever the editor **is** hydrated: `ldnaClearAllOverlays()` and single-delete run only from the overlay-list UI, which exists only after hydration.

### Coverage

Protects all **8** `map-input` include sites — Buyer/Tenant Create Offer, Buyer/Tenant Hire Agent, and the four legacy criteria pages — across both transports (Livewire bridge ×4, plain form POST ×4), because the guard sits in the one shared serializer.

**No impact on the four subject-property workflows** (Seller/Landlord Create Offer, Seller/Landlord Hire Agent): none includes `map-input`. The read-only `components/location-dna-map.blade.php` is a separate file, untouched, and never serializes.

### ⚠️ Verification status

Tests: `tests/Unit/Spatial/SearchAreasGeometryGuardTest.php` — **11 structural assertions**, including positional ones that pin the race fix (verified non-vacuous: moving the flag to the racy position fails 2 tests).

**The guard is JavaScript and is NOT behaviourally verified.** This project has no JS test runner and browser automation is unauthorised. A green run proves the guard is present and correctly positioned; it does **not** prove the wipe is prevented at runtime. That proof requires the browser gate (§8), and until then G0 must not be described as behaviourally verified.

---

## 10. What Phase 2C must preserve

1. **Byte-identical geometry** — full float precision on `polygons[].path[]` and `radius_searches[]`
2. **Radius in miles**, not metres — the `1609.34` conversion
3. **The `address` XOR `label`** convention on radius entries
4. **Both transports** — Livewire bridge and plain form POST
5. **`syncInput` injection**, never `serverMemo` mutation
6. **All three discrete mirrors**, and the §5 hydration invariant — key *existence*, not emptiness
7. **Countdown-timer independence** (§6)
8. **A manual save-and-restore demonstration**, because the JS gap in §4 means the automated suite cannot close criterion #7 alone
