---
name: Phase D year_built collision resolution
description: Formal record of the year_built collision check and stop-and-report outcome for Property DNA Phase D.
---

# Phase D: year_built Collision Resolution

## Collision Check Result

`year_built` was a known collision-risk candidate identified before Phase D began.
A grep across all 4 Landlord/Tenant Livewire components was performed before any code was written.

## Findings (pre-Phase-D state — verified by grep)

**LandlordOfferListing.php** — year_built fully wired pre-Phase-D:
- Line 591: `public $year_built = '';`  (property declaration)
- Line 1877: `'year_built' => $this->year_built,` (validation rules array)
- Line 2662: `$this->year_built = $auction->get->year_built ?? '';` (load assignment)
- Line 3217: `$auction->saveMeta('year_built', $this->year_built);` (saveMeta call)

**LandlordOfferListingEdit.php** — year_built fully wired pre-Phase-D:
- Line 577: `public $year_built = '';`
- Line 1815: `'year_built' => $this->year_built,`
- Line 2627: `$this->year_built = $auction->get->year_built ?? '';`
- Line 3366: `$auction->saveMeta('year_built', $this->year_built);`

**TenantOfferListing.php** — year_built: NOT PRESENT (correct; Tenant role, not applicable)
**TenantOfferListingEdit.php** — year_built: NOT PRESENT (correct; Tenant role, not applicable)

## Collision Resolution Decision

Stop-and-report rule triggered: year_built was found to be fully implemented
(property + validation + load + saveMeta) in both Landlord components before Phase D.

Resolution: **SKIP — do not add duplicate wiring.**

Phase D intentionally excluded year_built from new additions because:
1. The public property already existed.
2. The load assignment already existed.
3. The saveMeta call already existed.
4. Adding any of these again would create duplicate declarations or double-save side effects.

Existing year_built behavior was intentionally preserved unchanged.
No Phase D code touches year_built in any file.

**Why:** Duplicate public properties in Livewire cause a PHP fatal error. Duplicate saveMeta calls would silently write the same key twice, which is wasteful and potentially confusing in audit logs. The pre-existing implementation is complete and correct — Phase D had nothing to add.
