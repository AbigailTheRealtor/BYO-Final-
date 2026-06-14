---
name: water_view vs view_preference meta key split
description: Seller and landlord forms have two separate view-related props/meta keys; Ask AI must read water_view first with view_preference fallback; public views only show view_preference.
---

## Architecture

The seller and landlord offer listing forms have TWO separate view-related props:

| Prop | Meta key saved | Blade label | Options |
|------|----------------|-------------|---------|
| `$water_view` | `water_view` | "Water View" | Bay/Harbor - Full, Canal, Gulf/Ocean - Full, Lake, Pond, River, Other |
| `$view_preference` | `view_preference` | "View" | General scenic/preference options from `$preferences` array |

MLS import routes `water_view` canonical key → `*water_view` prop → saved as `water_view` meta.

## Stage 8 (public view) gap

`offer-listing/seller/view.blade.php` and `offer-listing/landlord/view.blade.php` both read `$arr('view_preference')` for their "View Preferences" display section. Neither reads `$arr('water_view')` — `water_view` meta data is never shown on the public listing page.

This is a **pre-existing gap** affecting ALL listings (MLS-imported and manually entered). Not introduced by MLS import.

## Stage 9 (Ask AI) fix

`AskAiContextBuilderService::extractFactualFields()` originally read `$infoGet('view_preference')` for the `water_view` output key in both seller and landlord sections. A "Live-DB audit (June 2026)" comment claimed `water_view` meta doesn't exist, but it was auditing LEGACY rows created before the Livewire wizard was updated.

**Fix:** Changed to `$infoGet('water_view') ?: $infoGet('view_preference')` — reads the Livewire/MLS-created `water_view` key first; falls back to `view_preference` for legacy rows.

**Why:** The live DB audit was done on old records; the Livewire `saveDraft()` and `saveMeta()` calls explicitly write `water_view` meta for all new listings. Trusting the comment over the actual Livewire code caused Ask AI to always return null for water view on new listings.

## How to apply

- Any future context builder work for `water_view`: always check `water_view` first, fallback to `view_preference`.
- Any public view work that needs to show water view: read BOTH `water_view` (for the specific water body types) and `view_preference` (for general scenic preference).
- Do NOT merge the two props or meta keys — they are semantically distinct fields on the form.
