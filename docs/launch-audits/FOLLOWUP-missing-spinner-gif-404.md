# FOLLOW-UP: Missing `images/Spinner.gif` — 404 on nearly every page

**Status:** Documented only — **not fixed.** Logged as an incidental finding during the Location DNA
Search-Areas browser QA (2026-07-02). Unrelated to the `map-input.blade.php` change.

**Severity:** Low (cosmetic)
- Console 404 + a broken `<img>` on the global loading-spinner overlay.
- No functional impact; the overlay is normally hidden, so users rarely see the broken image.

---

## Summary

The global layout references a loading spinner at `images/Spinner.gif`, but that file does not exist
under `public/images/`. Every page that renders `layouts/main.blade.php` therefore emits a **404**
for the asset (surfaced in the browser console as
`Failed to load resource: the server responded with a status of 404`).

## Location

- Reference: `resources/views/layouts/main.blade.php:172`
  ```blade
  <img src="{{ asset('images/Spinner.gif') }}" alt="" />
  ```
- Missing file: `public/images/Spinner.gif` (confirmed absent; no case-variant such as
  `spinner.gif` present either).

## Reproduction path

1. Load any page that uses the main layout (e.g. `/dev/offer-listing/buyer`).
2. Open DevTools → Network/Console.
3. Observe a 404 for
   `…/images/Spinner.gif`.

Confirmed via Playwright network capture: the only 404 on the Buyer Offer page was
`/images/Spinner.gif`.

## Recommended fix (do not apply yet)

Pick one:
1. **Add the asset** — drop the intended spinner file at `public/images/Spinner.gif`.
2. **Repoint the reference** — change `layouts/main.blade.php:172` to an existing spinner asset in
   `public/images/`, if one is already shipped under a different name.
3. **Remove** the `<img>` if the loading overlay is no longer used.

Option 1 or 2 preferred so the loading overlay keeps working.
