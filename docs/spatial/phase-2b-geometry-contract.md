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

### FINDING 2B-3 · The two Tenant Offer components never write the `cities` mirror — highest consequence

`saveMeta('cities', …)` is present in the trait and both **Buyer** Offer components, and **absent from both Tenant Offer components**.

| Component | writes `cities` mirror |
|---|---|
| `HasSearchAreas` (4 Hire components) | ✅ |
| `BuyerOfferListing` / `BuyerOfferListingEdit` | ✅ |
| `TenantOfferListing` / `TenantOfferListingEdit` | ❌ |

For a Tenant offer listing the discrete `cities` meta therefore **goes stale relative to the blob**. A tenant can change their cities in the Search Areas widget and the value read by Ask AI, the match engine, filtering and public display will not follow.

**This is a live data-correctness defect, not cosmetic.** It is recorded rather than fixed because fixing it is a behaviour change. **Recommended as its own scoped task.**

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
| `tests/Unit/Spatial/SearchAreasWidgetContractTest.php` | 14 | Structural source assertions |

**39 tests total.** Vehicle for the persistence suite is `TenantAgentAuction` — a real host of the trait and the only one with a factory. The trait is exercised through a thin host object rather than the full Livewire component, because those components carry hundreds of unrelated required props and booting one would characterise its validation rather than the geometry contract.

---

## 8. What Phase 2C must preserve

1. **Byte-identical geometry** — full float precision on `polygons[].path[]` and `radius_searches[]`
2. **Radius in miles**, not metres — the `1609.34` conversion
3. **The `address` XOR `label`** convention on radius entries
4. **Both transports** — Livewire bridge and plain form POST
5. **`syncInput` injection**, never `serverMemo` mutation
6. **All three discrete mirrors** — including fixing or preserving FINDING 2B-3 deliberately, not by accident
7. **Countdown-timer independence** (§6)
8. **A manual save-and-restore demonstration**, because the JS gap in §4 means the automated suite cannot close criterion #7 alone
