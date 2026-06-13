# Value Normalization Audit

**Task:** P1B — Value Normalization Audit & Migration  
**Date:** 2026-06-13  
**Scope:** All four roles (Seller, Buyer, Landlord, Tenant); fields: `yes`/`no` casing fields, `lease_fee_type`, `purchase_fee_type`, `retainer_fee_application`  
**Audit boundary:** Code + database query. Migration applied only where confirmed mismatch.

---

## 1. Inventory Table

| Field | Role | Current Stored Values | Canonical Value (form option) | Rows Affected | Migration Required |
|-------|------|----------------------|-------------------------------|--------------|-------------------|
| `early_termination_fee_option` | Buyer | `'yes'`/`'no'` | `'yes'`/`'no'` | — | **N** |
| `early_termination_fee_option` | Seller | `'yes'`/`'no'` | `'yes'`/`'no'` | — | **N** |
| `early_termination_fee_option` | Landlord | `'yes'`/`'no'` | `'yes'`/`'no'` | — | **N** |
| `early_termination_fee_option` | Tenant | `'Yes'`/`'No'` | `'Yes'`/`'No'` | — | **N** |
| `retainer_fee_option` | Buyer | `'yes'`/`'no'` | `'yes'`/`'no'` | — | **N** |
| `retainer_fee_option` | Seller | `'yes'`/`'no'` | `'yes'`/`'no'` | — | **N** |
| `retainer_fee_option` | Landlord | `'yes'`/`'no'` | `'yes'`/`'no'` | — | **N** |
| `retainer_fee_option` | Tenant | `'Yes'`/`'No'` | `'Yes'`/`'No'` | — | **N** |
| `retainer_fee_application` | Buyer | `'Applied toward final compensation'` | `'Applied toward final compensation'` | — | **N** |
| `retainer_fee_application` | Seller (preset) | `'Applied toward final compensation'` | `'Applied Toward Final Compensation'` | **5 preset rows** | **Y** — preset data only |
| `retainer_fee_application` | Landlord (preset) | *(none stored)* | `'applied'`/`'additional'` (slugs) | 0 | **N** — latent gap, code-only fix |
| `retainer_fee_application` | Tenant | `'applied'`/`'additional'` | `'applied'`/`'additional'` | — | **N** |
| `lease_fee_type` | Buyer | `'flat'` (flat fee slug) | `'flat'` | — | **N** |
| `lease_fee_type` | Tenant | `'Flat Fee'` (full text) | `'Flat Fee'` | — | **N** |
| `purchase_fee_type` | Buyer | full text (e.g. `'Flat Fee'`) | full text | — | **N** |
| `purchase_fee_type` | Seller | slugs (`'flat'`,`'percentage'`,`'combo'`) | slugs | — | **N** |
| `purchase_fee_type` | Landlord | full text (property-type specific) | full text | — | **N** |
| `purchase_fee_type` | Tenant | full text (same as Buyer) | full text | — | **N** |

---

## 2. Detailed Findings

### 2.1 `early_termination_fee_option` and `retainer_fee_option`

**Finding: No mismatch. Intentional per-role design.**

The stored value casing differs across roles by design:
- Buyer / Seller / Landlord: lowercase `'yes'`/`'no'`
- Tenant: Title Case `'Yes'`/`'No'`

The preset editor is already role-aware via `$etfYesVal = ($role === 'tenant') ? 'Yes' : 'yes'` and `$rtfYesVal = ($role === 'tenant') ? 'Yes' : 'yes'`. Each role's preset stores the value its own bid/listing form expects. DB evidence confirms consistency:

| Role | preset stored | bid stored |
|------|--------------|-----------|
| buyer | `'yes'` | `'yes'` |
| seller | `'yes'` | `'yes'` |
| landlord | `'yes'` | `'yes'` |
| tenant | `'Yes'` | `'Yes'` |

**Conclusion:** No change required.

---

### 2.2 `retainer_fee_application`

**Finding: Two mismatches — one data-impacting (Seller), one latent (Landlord).**

#### Seller role

| Surface | Stored / option value |
|---------|----------------------|
| Seller offer-listing Blade form option values | `'Applied Toward Final Compensation'` (Title Case) |
| Seller preset editor option values (before fix) | `'Applied toward final compensation'` (sentence case) |
| `seller_agent_auction_metas` (listing records) | `'Applied Toward Final Compensation'` — 59 rows |
| `agent_default_profiles` (seller presets) | `'Applied toward final compensation'` — 5 rows |
| `seller_agent_auction_bid_metas` (bid records) | `'Applied toward final compensation'` — 17 rows (silently pre-filled from preset; no form UI) |

**Impact:** When an agent who saved a seller preset with `retainer_fee_application` set opens the seller offer-listing form, the dropdown renders blank because the stored preset value `'Applied toward final compensation'` doesn't match any option (`value="Applied Toward Final Compensation"`).

**Canonical value:** Title Case — confirmed by 59 listing records and the form option values that agents interact with in the offer-listing flow.

**Bid form note:** `SellerAgentAuctionBid` has no UI for this field. The value is pre-filled from preset and saved to bid metas silently. The 17 bid records with sentence case are benign because `CompensationFormatter::formatRetainerFeeApplication()` normalizes via `strtolower()` before matching, so display is unaffected.

#### Landlord role

| Surface | Stored / option value |
|---------|----------------------|
| Landlord bid form option values | `'applied'` / `'additional'` (slugs) |
| Landlord offer-listing form option values | `'applied'` / `'additional'` (slugs) |
| Preset editor option value (before fix) | `'Applied toward final compensation'` (sentence case — same @else branch as Buyer/Seller) |
| `landlord_agent_auction_bid_metas` with retainer_fee_application | 0 rows set |
| `agent_default_profiles` (landlord presets) with retainer_fee_application | 0 rows set |

**Impact:** Latent. No users affected today. If a Landlord agent saves retainer_fee_application in a preset, the value stored (`'Applied toward final compensation'`) would not match any option in the Landlord bid/listing forms (`'applied'`/`'additional'`), causing blank display.

**No data migration required** — 0 rows.

---

### 2.3 `lease_fee_type`

**Finding: No mismatch per-role. Cross-role difference is intentional and documented.**

Buyer bid form uses `'flat'` for the flat-fee option; Tenant bid form uses `'Flat Fee'`. Each role's preset editor config (`config/agent_preset_compensation.php`) correctly matches its own bid form:
- `buyer.lease_fee_type.residential['flat']` → `'flat'` stored, matches Buyer bid form
- `tenant.lease_fee_type.residential['Flat Fee']` → `'Flat Fee'` stored, matches Tenant bid form

DB evidence: all stored values are consistent with their respective role's form expectations.

**Conclusion:** No change required. Cross-role difference is known and documented in config.

---

### 2.4 `purchase_fee_type`

**Finding: No mismatch per-role. Cross-role differences are intentional.**

Each role's preset config uses the same option keys as its bid form:
- Buyer/Tenant: full-text labels (`'Flat Fee'`, `'Percentage of the Total Purchase Price'`, etc.)
- Seller: slugs (`'flat'`, `'percentage'`, `'combo'`, `'other'`)
- Landlord (residential/commercial): full-text, property-type specific options

DB evidence confirms all stored values match the respective form's select option values.

**Conclusion:** No change required. Documented in `config/agent_preset_compensation.php` with comments.

---

## 3. Migration Applied

### 3.1 Seller preset `retainer_fee_application` — sentence case → Title Case

**Migration file:** `database/migrations/2026_06_13_000002_normalize_seller_preset_retainer_fee_application.php`

**Scope:** `agent_default_profiles` where `role_type = 'seller'` and `profile_data->>'retainer_fee_application'` is sentence case.

**Pre-migration count:** 5 rows with `'Applied toward final compensation'`; 0 rows with `'Charged in addition to final compensation'`

**Post-migration count:** 5 rows with `'Applied Toward Final Compensation'`; 0 residual sentence-case rows

**Rollback:** `down()` in the migration file reverses to sentence case. To run: `php artisan migrate:rollback --step=1`

---

## 4. Code Changes Applied

| File | Change |
|------|--------|
| `resources/views/agent-presets/edit.blade.php` | Split `retainer_fee_application` options by role: `tenant`/`landlord` → slugs; `seller` → Title Case; `buyer` → sentence case |

### Why no form change to the Seller offer-listing Blade

The Seller offer-listing form (`hire-seller-agent/seller-agent-auction-tabs/commission-based/broker-compensation.blade.php`) already has the correct Title Case option values matching the 59 canonical listing records. No change needed there.

### Why no data migration for bid metas

The 17 `seller_agent_auction_bid_metas` rows with `'Applied toward final compensation'` (sentence case) are benign:
- `SellerAgentAuctionBid` has no UI for this field — it's saved silently from preset
- `CompensationFormatter::formatRetainerFeeApplication()` normalizes display via `strtolower()`, so both sentence case and Title Case render identically to users
- The task scope does not require migrating bid meta records, only preset profiles and forms

---

## 5. Existing Saved Bids and Fixtures Confirmed Unbroken

- All four bid form Livewire components load `retainer_fee_option`/`early_termination_fee_option` using their own per-role stored values — no cross-role loading occurs.
- `AgentBidMapperService::mapFromProfile()` is a pass-through; it does not normalize values. Per-role correctness is guaranteed by the preset editor storing per-role values.
- `CompensationFormatter` handles all known stored variants of `retainer_fee_application` (slugs, sentence case, Title Case) via `strtolower()` normalization.
- No test fixtures reference `retainer_fee_application` with a value that would be broken by these changes.

---

## 6. Fields with No Migration Required — Summary

| Field | Reason |
|-------|--------|
| `early_termination_fee_option` | Per-role values are intentional; preset editor is role-aware |
| `retainer_fee_option` | Per-role values are intentional; preset editor is role-aware |
| `lease_fee_type` | Per-role values are consistent; cross-role difference is by design |
| `purchase_fee_type` | Per-role values are consistent; cross-role difference is by design |
| `retainer_fee_application` (Buyer) | Sentence case stored and expected — no mismatch |
| `retainer_fee_application` (Tenant) | Slugs stored and expected — no mismatch |
| `retainer_fee_application` (Landlord) | Zero rows stored; latent gap resolved by preset editor code fix only |
