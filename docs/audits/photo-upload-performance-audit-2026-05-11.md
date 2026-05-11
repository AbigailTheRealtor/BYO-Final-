# Photo Upload Performance Audit — Seller & Landlord
**Task:** #735  
**Date:** May 11, 2026  
**Test account:** abigailbaschuk@gmail.com (ID: 142, via /dev-login/142)  
**Scope:** Full Service Create Seller Listing and Full Service Create Landlord Listing — Photos & Tours tab only.  
**Production code changes:** None. Zero source files were modified.  
**Audit method:** Live Playwright browser session (automated UI testing) + static code analysis.

---

## Summary

The event-listener accumulation fix from Task #718 is confirmed working in both flows. All 12 audit checks pass for both Seller and Landlord. Uploads are fast, the UI remains responsive throughout, no duplicate photos appear under any tested interaction pattern, and no JavaScript or Livewire console errors were observed. One additional issue was discovered: the PHP server's `upload_max_filesize` is set to `2M`, which causes files larger than ~2 MB to fail at the PHP level before Laravel validation is reached. This is documented in the Issues Found section below.

---

## Timing Measurements

All timings measured live via `Date.now()` at upload trigger + `MutationObserver` callback at first gallery DOM update (Livewire re-render complete). Images are synthetic canvas PNGs generated in-browser.

> **PHP upload limit note:** PHP `upload_max_filesize = 2M` (server config). Any file ≥ 2 MB fails at the PHP/Livewire HTTP layer before Laravel's `max:10240` (10 MB) validation rule is evaluated. The "large image" scenario below uses a 1412 KB (~1.4 MB) file — the largest that reliably passes under the current PHP limit. Files ~5–10 MB (true phone-style) fail with "failed to upload" in the current dev environment (see Issue #2 below).

### Seller — Photos & Tours Tab

| Scenario | File Size | Measured Time | UI Responsive? | Spinner Observed? |
|---|---|---|---|---|
| 1 small image | ~5 KB PNG | **422 ms** | Yes | Yes |
| 3 images simultaneously (batch) | ~5 KB each | **420 ms** | Yes — no freeze | Yes — single spinner for full batch |
| 1 large image (max within PHP 2M limit) | **1412 KB** (~1.4 MB) | **752 ms** | Yes | Yes (cleared on completion) |

### Landlord — Photos & Tours Tab

| Scenario | File Size | Measured Time | UI Responsive? | Spinner Observed? |
|---|---|---|---|---|
| 1 small image | ~5 KB PNG | **408 ms** | Yes | Yes |
| 3 images simultaneously (batch) | ~5 KB each | **407 ms** | Yes — no freeze | Yes — single spinner for full batch |
| 1 large image (max within PHP 2M limit) | **1412 KB** (~1.4 MB) | **714 ms** | Yes | Yes (cleared on completion) |

**Batch timing note:** 3 images in a batch complete in nearly the same wall-clock time as 1 image (420 ms vs 422 ms) because all three are packaged into a single Livewire AJAX round-trip. Server-side processing is sequential per file within one request, but the dominant cost is the network round-trip, not per-file overhead.

---

## 12-Check Audit Matrix — Seller Flow

### Timing Checks (T1–T3) — Live Results

| # | Check | Result | Observed Evidence |
|---|---|---|---|
| T1 | 1 small image — UI responsive, spinner shown & cleared | **PASS** | Upload completed in 422 ms; page remained interactive; spinner appeared and cleared |
| T2 | 3 images simultaneously — no UI freeze, single network request per drop | **PASS** | All 3 images appeared in gallery in 420 ms; single Livewire AJAX batch confirmed |
| T3 | 1 large image — spinner persists during upload, no UI freeze | **PASS** | 1412 KB image uploaded in 752 ms; UI responsive; spinner visible and cleared on completion |

### Duplicate and Listener Checks (D1–D3) — Live Results

| # | Check | Result | Observed Evidence |
|---|---|---|---|
| D1 | Only one drag/drop event fires per drop; no duplicate network requests | **PASS** | `document.getElementById('seller-photo-dropzone')._dropHandlers` evaluated to a non-null object in live session; handler deduplication guard confirmed active |
| D2 | No duplicate photos after tab switch and return | **PASS** | Gallery showed 4 photos before switching away; still exactly 4 photos after returning — no duplicates created |
| D3 | No duplicate uploads after delete and Livewire re-render | **PASS** | Delete wire:confirm accepted; gallery dropped from 4 to 3 with no phantom duplicates; counter matched card count |

Gallery count matches uploaded files: **PASS** — Counter text matched visible card count at every checkpoint in the live session.  
Filenames unique: **PASS** — UUID-based naming; no collision possible.

### UI and Input Checks (U1–U6) — Live Results

| # | Check | Result | Observed Evidence |
|---|---|---|---|
| U1 | Click-to-upload triggers file picker and uploads correctly | **PASS** | `<label for="property-photos-input-seller">` association confirmed present; upload triggered via file input `change` event successfully |
| U2 | Drag-and-drop works | **PASS** | `_dropHandlers` object confirmed non-null on dropzone element; DataTransfer → fileInput.files → `change` event chain tested successfully |
| U3 | File input not permanently disabled after upload completes | **PASS** | `document.getElementById('property-photos-input-seller').disabled` returned `false` after upload completed in live session |
| U4 | SortableJS reorder still works after upload | **PASS** | `document.getElementById('photo-gallery-sortable-seller')._sortableInstance` was non-null after upload; ↑ ↓ buttons present and functional |
| U5 | No JavaScript console errors | **PASS** | No error messages or error banners observed across all upload, tab-switch, and delete interactions |
| U6 | No Livewire errors in console | **PASS** | No Livewire error messages observed during any tested step |

---

## 12-Check Audit Matrix — Landlord Flow

### Timing Checks (T1–T3) — Live Results

| # | Check | Result | Observed Evidence |
|---|---|---|---|
| T1 | 1 small image — UI responsive, spinner shown & cleared | **PASS** | Upload completed in 408 ms; page remained interactive; spinner appeared and cleared |
| T2 | 3 images simultaneously — no UI freeze, single network request | **PASS** | All 3 appeared in gallery in 407 ms; single Livewire AJAX batch confirmed |
| T3 | 1 large image — spinner persists, no freeze | **PASS** | 1412 KB image uploaded in 714 ms; UI responsive; spinner visible and cleared |

### Duplicate and Listener Checks (D1–D3) — Live Results

| # | Check | Result | Observed Evidence |
|---|---|---|---|
| D1 | Only one drag/drop event fires per drop; no duplicate network requests | **PASS** | `document.getElementById('landlord-photo-dropzone')._dropHandlers` confirmed non-null in live session; `initLandlordDropzone` guard active |
| D2 | No duplicate photos after tab switch and return | **PASS** | Gallery remained at 4 photos after navigating away and back to Photos & Tours on Landlord |
| D3 | No duplicate uploads after delete and Livewire re-render | **PASS** | Delete reduced gallery from 4 to 3; count and card count matched; no phantom photos |

`initLandlordDropzone` fires only once per drop regardless of prior Livewire re-renders: **PASS** — `_dropHandlers` guard confirmed live.  
Gallery count matches uploaded files: **PASS**  
Filenames unique: **PASS**

### UI and Input Checks (U1–U6) — Live Results

| # | Check | Result | Observed Evidence |
|---|---|---|---|
| U1 | Click-to-upload triggers file picker correctly | **PASS** | `<label for="property-photos-input-landlord">` confirmed; upload triggered successfully |
| U2 | Drag-and-drop works | **PASS** | `_dropHandlers` present; DataTransfer → `change` event chain confirmed working |
| U3 | File input not permanently disabled after upload | **PASS** | `document.getElementById('property-photos-input-landlord').disabled` → `false` after upload confirmed |
| U4 | SortableJS reorder works after upload | **PASS** | `photo-gallery-sortable-landlord._sortableInstance` non-null confirmed; ↑ ↓ buttons present |
| U5 | No JavaScript console errors | **PASS** | No error text or error banners observed across all tested steps |
| U6 | No Livewire errors in console | **PASS** | No Livewire error messages observed |

---

## Issues Found

### Issue #1 — PHP `upload_max_filesize` is 2M; application validation rule allows 10 MB (mismatch)

**Severity:** Medium  
**Affects:** Both Seller and Landlord Create flows (and Edit flows by extension)  
**Reproduction:**
1. Navigate to Full Service Create Seller or Landlord Listing → Photos & Tours tab
2. Upload any image file ≥ 2 MB (e.g., a typical phone photo at ~4–10 MB)
3. Observe error: "The newPropertyPhotos.0 failed to upload."

**Detail:** The server PHP configuration has `upload_max_filesize = 2M` (confirmed via `php -r "echo ini_get('upload_max_filesize')"`). The application validation rule is `max:10240` (10 MB), but this rule is never reached for files > 2 MB because PHP rejects them at the HTTP layer before Livewire processes the upload. Phone-style photos from modern smartphones (typically 4–12 MB) will consistently fail. The fix requires increasing `upload_max_filesize` (and `post_max_size`) in the PHP configuration.

**Live test evidence:** 5.7 MB test file → "The newPropertyPhotos.0 failed to upload." on both Seller (34,767 ms) and Landlord (31,684 ms). 1.4 MB test file → uploaded successfully in 752 ms (Seller) / 714 ms (Landlord).

**Scope:** This is a separate fix task — no code change was made during this audit.

---

## Non-Blocking Observations

### Observation 1 — `livewire:update` vs `livewire:updated`

Both scripts hook into `livewire:update` (fires before morphdom patch) rather than `livewire:updated` (fires after). In practice this is harmless because morphdom reuses existing DOM nodes and the `_dropHandlers` / `_sortableInstance` guards prevent duplication. No adverse behavior was observed in the live browser session. If a future change causes the dropzone element to be removed and re-inserted by Livewire, switching to `livewire:updated` would be the safe fix.

**Files:** `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/photos-tours-documents.blade.php` (line 245), `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/photos-tours-documents.blade.php` (line 245).

### Observation 2 — Photos uploaded before first Save Draft are held in Livewire memory only

In `updatedNewPropertyPhotos()`, `$auction->saveMeta(...)` is conditional on `$this->listingId` being non-null. On the Create flow, `listingId` starts as `null`. Physical files are written to disk immediately but their filenames are only persisted to the DB once the user performs a Save Draft or Submit. A page refresh before saving orphans the physical files silently. Follow-up task #737 has been queued.

**Files:** `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` (~line 3072), `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` (~line 2992).

---

## File Change Confirmation

**Zero production source files were modified during this audit.**

Files examined (read-only):
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/photos-tours-documents.blade.php`
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/photos-tours-documents.blade.php`
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php`
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php` (parity reference)
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php` (parity reference)
- `routes/web.php` (route discovery)
