# Browser QA Remediation — Batch Execution Checkpoint

**Worktree (dedicated):** `/home/runner/workspace-browser-qa`
**Branch:** `browser-qa-remediation` (created off `b7fb48bac`)
**Last updated:** 2026-07-11 (end of day)
**Current HEAD:** `8741e2bdc07db68fa073a3b62cbf12991223f361`

> Source of truth for what must be fixed = the original **Browser QA checklist + browser
> evidence** (`docs/launch-audits/BROWSER-QA-CHECKLIST.md`). "Code present" ≠ "browser
> verified". An item is complete only when the rendered app matches the expected result.

---

## Batch status

| Batch | Scope | Status |
|-------|-------|--------|
| **Batch 1 — Launch Blockers** | #17, #18, #19, #4 | ✅ **Committed** (`8741e2bdc`) — automated + DOM-level verified; **manual browser QA still pending** |
| **Batch 2 — Browser Functional Fixes** | #15, #14, #2 (Pet Policy) | 🟡 **Next implementation batch** |
| **Batch 3 — Upload Infrastructure** | #6, #7 | ⚪ Not started |
| **Batch 4 — Shared UI Cleanup** | #26 + browser-verify #11, #20, #21, #22, #25 | ⚪ Not started |
| **Batch 5 — Product Decision** | #1 Landlord Commercial Broker Compensation | ⛔ Blocked on product decision (do not begin) |

---

## Batch 1 — completed today (commit `8741e2bdc`)

### Issues fixed
- **#17 Purchase Purpose "Other" (Hire Buyer)** — live 500 fixed.
- **#18 Flood Zone Preference "Other" (Hire Buyer)** — dead reveal fixed (select2→Livewire sync added).
- **#19 HOA Acceptance → Max HOA Monthly Fee (Hire Buyer)** — live 500 fixed.
- **#4 Seller Commercial `ceiling_height` submit** — `nullable|array` → `nullable|string|in:…`.
  - #4 "Business won't submit" sub-symptom: server path proven clean; **not reproducible from
    code** → remains a browser-devtools item (client JS / hidden-tab error, no auto-jump).

### Root cause (17/18/19)
The live Hire Buyer form is served by `TenantAgentAuction` / `TenantAgentAuctionEdit`
(`@switch($user_type) @case('buyer')` → `@include` hire-buyer `property-preferences`),
**not** the dormant `HireBuyerAgent\BuyerAgentAuction`. The six B5.8 props were never
declared on the live components, so live `wire:model` threw `PublicPropertyNotFoundException`
(HTTP 500) on interaction, and flood zone never synced to the server.

### Files changed (7 files, +324 / −5)
- `app/Http/Livewire/TenantAgentAuction.php` — declare 6 props; hydrate (`loadDraft`); persist (`saveAllMetadata`)
- `app/Http/Livewire/TenantAgentAuctionEdit.php` — same 6 props + hydrate + persist; removed 2 dead `// dd/dump` lines
- `resources/views/livewire/tenant-agent-auction.blade.php` — flood-zone select2→`@this.set` sync + "Other" toggle (create)
- `resources/views/livewire/tenant-agent-auction-edit.blade.php` — same sync + registered in `rehydrateAllSelect2Fields` (edit)
- `app/Http/Livewire/OfferListing/Concerns/SellerPublishValidation.php` — ceiling_height rule (#4)
- `tests/Feature/Offers/LiveHireBuyerB58FieldsTest.php` — **new**, 5 tests
- `tests/Feature/Offers/SellerCeilingHeightRuleTest.php` — **new**, 2 tests

### Tests executed — all green (SQLite in-memory)
- `LiveHireBuyerB58FieldsTest` — 5/5 (declare, flood default array, edit hydrate+set, live-create DOM render, create save)
- `SellerCeilingHeightRuleTest` — 2/2
- Regression: `HireBuyerPortedFieldsRoundTripTest` 2/2 · `HireSearchAreasParityTest` 9/9 · `BatchEHireTenantParityTest` 11/11
- **Total 29 tests passing, no regressions.**
- Test harness note: worktree has no isolated `vendor/` (rm/cp/composer blocked); tests run via a
  read-only `--bootstrap` shim that forces `App\`/`Tests\` to this worktree and chains through the
  real `tests/bootstrap.php` (blanks `DATABASE_URL`, enforces SQLite `:memory:` guard).
  Shim lives in scratchpad (not committed); `vendor`/`.env` are gitignored.

### Browser verification STILL REQUIRED for Batch 1 (manual)
1. Hire Buyer → Property Preferences → **Purchase Purpose = Other** → input reveals, no 500; save/reopen persists.
2. **HOA Acceptance = Yes** then **Flexible** → Max HOA Monthly Fee reveals both times; save/reopen persists.
3. **Flood Zone = Other** (multi-select) → "Other" input reveals; multi-select + save/reopen persists.
4. **Seller Commercial** listing → pick a **Ceiling Height** → submit succeeds.
5. (Investigation) Seller **Business** submit → reproduce with devtools (console + network).

---

## Remaining Browser QA roadmap (execution order)

### Batch 2 — Browser Functional Fixes (next)
| QA | Description | Dependencies | Risk |
|----|-------------|--------------|------|
| **#15** | Hire Buyer Garage/Parking "Yes" reveal — broken on **Buyer create**: `toggleGarageOptions()` gates on `'Commercial Property'`, but Buyer `property_type` is `'Commercial'`/`'Business'`. Fix in `tenant-agent-auction.blade.php:3063` (mirror edit wrapper / broaden gate). | Shared `toggleGarageOptions` (all roles) — regress-check Tenant/Seller/Landlord | Med |
| **#14** | Vacant Land Property Style "Other" reveal — Seller **create** verified fixed; confirm/repair Seller **edit** delegated toggle (`offer-seller-listing-edit.blade.php`). Landlord N/A. | SC3 delegated handler | Low-Med |
| **#2 Part B** | Landlord Pet Policy redesign — build `pet_fee_type` dropdown (One Time Fee Refundable / Non Refundable / Monthly Pet Fee / No Pet Fee / Other) + conditional $ reveal + "Other" placeholder; remove/migrate 5 legacy pet fields. Net-new; back-compat for existing drafts. | Landlord create+edit shared partials; new EAV props | Med-High |
| _#2 Part A_ | Landlord submit — already OK (verify-only: no auto-jump-to-tab). | — | Low |

### Batch 3 — Upload Infrastructure
| QA | Description | Dependencies | Risk |
|----|-------------|--------------|------|
| **#6** | 14-photo upload — code correct (`deploy/php/uploads.ini` + `PHP_INI_SCAN_DIR`); needs **infra verification** (running worker `ini_get`, Replit edge proxy ≥150M). | Deployment env | Low code / infra unproven |
| **#7** | Friendly oversize error — Seller/Landlord (create+edit) covered; **gaps**: Buyer/Tenant single-photo have no listener; Documents-tab alert may render in a hidden pane. | Shared photos/documents partials | Low |

### Batch 4 — Shared UI Cleanup
| QA | Description | Dependencies | Risk |
|----|-------------|--------------|------|
| **#26** | Agent Credentials placeholder capitalization — lowercase `Enter phone number` / `Enter license number` / `Enter NAR member ID` in `partials/agent-credentials.blade.php` (36/67/83). Fans out to ~19 include sites. | Shared partial (all roles) | Very low |
| **#11** | Currency decimal preservation — code-complete; **browser-verify** (caret + 150ms debounce). | — | Low |
| **#20** | Hire Tenant Rental Purpose "Other" placeholder — code-complete; browser-verify. | — | Low |
| **#21 / #22** | Seller textarea heights — code-complete; browser-verify (theme-CSS override risk). | Shared `seller-compact-textarea` CSS | Low |
| **#25** | Water Frontage / Waterfront Feet placeholders — code-complete; browser-verify. | — | Low |

### Batch 5 — Product Decision (blocked)
| QA | Description | Dependencies | Risk |
|----|-------------|--------------|------|
| **#1** | Landlord Commercial "Tenant's Broker Commission" parity. **Ambiguous target** — the Month's-Rent/#-Months/Sales-Tax pattern belongs to the Landlord *Lease Fee*, not the Tenant Broker fee; no reference has it on the tenant-broker fee. Requires **new EAV props** (not markup-only) + casing normalization (risks orphaning saved `Flat fee`). **Do not begin until product decides the exact option set.** | Landlord create+edit; new props | Med |

---

## Resume instructions (tomorrow)

1. `cd /home/runner/workspace-browser-qa`; confirm branch `browser-qa-remediation`, HEAD `8741e2bdc`, clean `git status`.
2. Begin **Batch 2 only** (#15, #14, #2 Pet Policy). Do **not** repeat Batch 1 or re-audit completed work.
3. Test-run mechanism: `vendor/bin/phpunit --bootstrap <scratchpad shim> <path>` (recreate the shim if the
   scratchpad was cleared — it chains `tests/bootstrap.php` then prepends an `App\`/`Tests\` loader pointing
   at this worktree). `vendor` may need re-linking/re-copying from main if the worktree lost it.
4. Commit only Batch 2. Stop after Batch 2 for approval. Do not start Batch 3+.
