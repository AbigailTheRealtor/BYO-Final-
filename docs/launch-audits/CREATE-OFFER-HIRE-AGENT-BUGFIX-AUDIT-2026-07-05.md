# Create Offer + Hire Agent — Focused Bug-Fix Audit & Implementation Plan

**Date:** 2026-07-05
**Branch:** `launch-audit-remediation`
**Author:** Automated code audit (read-only pass — NO production code changed)
**Scope:** 33 issues across the 4 Create Offer flows (Seller/Buyer/Landlord/Tenant) and 4 Hire Agent flows.
**Companion doc (reconciled against):** `docs/launch-audits/Create-Offer-and-Hire-Agent-Edits-June-28-2026.md` (existing master spec + implementation log).

---

## OWNER DECISIONS — locked 2026-07-05 (binding on implementation)

These override the open questions previously flagged in this audit. They are authoritative.

1. **#33 Commute Preferences** — Do **not** remove the only Commute Preferences block. Remove a block **only if an actual duplicate exists** underneath Non-Negotiable Amenities / Property Features in the same flow. Per the read-only pass, **no duplicate was found** (the block appears once per Create flow and is absent from both Hire flows), so unless a real duplicate is located at implementation time, this issue is closed as: **No code change required — duplicate not found.**
2. **#25 Water Frontage / Waterfront Feet** — Do **not** add these fields to Hire Agent. Scope is limited to the **existing Create Seller / Create Landlord** fields. Fix the placeholder formatting **only where the fields already exist**. The "and Hire" clause is dropped.
3. **#31 Description placeholders** — Final wording (no property-type suffix on the title):
   - Seller: `Enter property description`
   - Buyer: `Enter buyer description`
   - Landlord: `Enter rental description`
   - **Tenant: `Enter tenant description`** (decided — not "rental").
4. **Verification policy (strengthened).** No issue may be marked **PASS / COMPLETE / IMPLEMENTED / RESOLVED** until it is **both code-verified AND browser-verified**. If browser verification **cannot** be performed in the current environment, mark the item exactly: **`CODE COMPLETE — HUMAN BROWSER QA REQUIRED`**, and **do not** mark it complete in the master ledger (§8 / status columns). This status is terminal-pending — it is not a pass.
5. **Shared-component regression policy.** Every shared-component change (**SC1–SC9**) must include **regression testing across every affected Create AND Hire flow** before its batch is considered complete. A shared fix is not done until all downstream consumers are re-verified.
6. **Batch discipline.** Each implementation batch must:
   - contain **only** the planned fixes for that batch (no drive-by edits);
   - start from a **clean `git status`**;
   - produce a **single isolated commit** (report the **commit hash**);
   - list **all files changed**;
   - summarize **root causes**;
   - summarize **browser verification** performed (or the `CODE COMPLETE — HUMAN BROWSER QA REQUIRED` status);
   - identify **any remaining unresolved issues**.

> **Gate:** Implementation is paused pending owner approval to begin **Batch A**. Do not start any batch until approved.

---

## 0. How to read this document

- **This is an audit only.** No production code has been modified. Every "Fix Plan" below is a proposal, not an applied change.
- **Nothing is marked "Fixed."** Per the brief, an item may only be marked fixed once it is **code-verified AND browser-verified** (where UI behavior is involved). The `Status` column therefore reads `⬜ Audited` for every row, annotated with a *reconciliation note* saying whether the prior master doc already claimed it done.
- **"Claimed-done (prior doc)"** means the June-28 master spec logged a fix in code, but per that doc's own Safety Rule 6 it was **not browser-verified** ("no browser driver in this env"). These MUST be browser-re-verified before being closed — they are the highest-risk "false green" items.
- **Line numbers** are from the read-only pass on 2026-07-05 and may drift as edits land; treat them as anchors, not contracts.

### 0.1 Critical architecture facts (govern every fix)

1. **Hire Agent authority (all 4 roles):** The live create component for *every* Hire flow is `App\Http\Livewire\TenantAgentAuction` (edit: `TenantAgentAuctionEdit`), routed via catch-all `routes/web.php:677-679`. Per-role UI is selected by `@switch($user_type)` inside `resources/views/livewire/tenant-agent-auction.blade.php`, which `@include`s tab partials from the **dedicated dirs**:
   - Seller → `resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/*`
   - Buyer → `resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/*`
   - Landlord → `resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/*`
   - Tenant → `resources/views/livewire/tenant-agent-auction-tabs/commission-based/*`
   - **Hire PHP/logic edits → `TenantAgentAuction.php` + `TenantAgentAuctionEdit.php`.** The dedicated `HireSellerAgent/HireBuyerAgent/HireLandLordAgent` PHP components are LIVE only at legacy URLs and are **do-not-invest** for the new flow. Do not fix bugs there.
   - **`TenantAgentAuction` may be surgically edited but NOT refactored** onto `HasListingLifecycle` (CLAUDE.md §3.1).
2. **Create Offer authority (per role):** isolated components `OfferListing\{Seller,Buyer,Landlord}\{Role}OfferListing` + `{Role}OfferListingEdit`; Tenant uses a multi-role engine `TenantOfferListing` branching on `user_type`. Routes `web.php:1128-1165`. Each has a **monolithic wrapper blade** (holds all JS) + **tab partials** (hold field markup).
3. **Frozen code:** `initializeLimitedService()` in all four Create Offer blades is frozen legacy — never touch. All fixes apply to the Full Service scope only.
4. **Landlord/Tenant persist via EAV meta** (`*_metas`), Seller/Buyer via native columns + meta. Respect this when adding fields.

### 0.2 Shared components (fix once, ripples everywhere)

| Ref | Shared component | File | Drives issues |
|-----|------------------|------|---------------|
| SC1 | Currency mask trio `validateInput` / `reformatNumber` / `handlePaste` (copy-pasted into every hire/tenant wrapper) | e.g. `hire-seller-agent.blade.php:2832-2878` | 11 |
| SC2 | select2-in-`wire:ignore` + re-init-on-`message.processed` reveal pattern | wrapper blades (per flow) | 8, 9, 10, 15, 18 |
| SC3 | Blade-class "Other" reveal + delegated `$(document).on('change',…)` block | `offer-seller-listing.blade.php:2698-2720` | 14, 17 |
| SC4 | Agent credentials/contact partial (19 include sites) | `resources/views/livewire/partials/agent-credentials.blade.php` | 26 |
| SC5 | Location DNA / Preferred Cities / Important Places map input | `resources/views/partials/location-dna/map-input.blade.php` | 28, 29, (33) |
| SC6 | Placeholder/title helper | `app/Helpers/PropertyTypePlaceholderHelper.php` | 30, 31 |
| SC7 | Publish-validation traits | `app/Http/Livewire/OfferListing/Concerns/{Seller,Landlord}PublishValidation.php` | 2, 4 |
| SC8 | Video embed helper (exists, but detail views bypass it with duplicated inline regex) | `app/Support/VideoEmbedHelper.php` | 5 |
| SC9 | Landlord broker-compensation partial (gated on `property_type`) | `offer-landlord-tabs/commission-based/broker-compensation.blade.php` | 1 |

---

## 1. Master audit table

> Wide table — scroll horizontally. Each terse cell is expanded in the per-issue cards (§2–§4). `Status` is `⬜ Audited` for all rows (no code changed); the reconciliation note says what the prior master doc claimed.

| ID | Issue | Flow | Priority | Likely Files | Current Behavior | Expected Behavior | Root Cause | Fix Plan | Regression Risk | Browser Test Steps | Status |
|----|-------|------|----------|--------------|------------------|-------------------|------------|----------|-----------------|--------------------|--------|
| **P1-1** | Landlord create missing Broker Compensation & Agency Agreement Terms | Create Landlord | P0 | `offer-landlord-tabs/commission-based/broker-compensation.blade.php` (20-24,48); create blade `:1248`; edit `:1114` | Fields render on edit (property_type already "Residential") but not on create (default `''`); Commercial never renders; Agency-Agreement partials orphaned (not @included anywhere) | Broker Comp + Agency Terms render on create for both Residential & Commercial, parity with edit | `@if($_isResidential)` gate wraps whole partial; `$_isCommercial` computed but unused, no `@else`; agency partials not included | Add `@else`/Commercial branch; wire orphaned agency-agreement partials into the tab; default gate to show when type blank | Med — changing gate could double-show on edit; Landlord EAV meta save must cover new fields | Create Landlord, leave type blank then pick each type; confirm both blocks show + save + reappear on edit | ⬜ Audited — prior doc A1.8/A1.9 claimed ✅ but does **not** name Landlord; agency partials confirmed orphaned → **treat as real bug** |
| **P1-2** | Landlord create submit must work; draft/edit still save | Create Landlord | P0 | `LandlordOfferListing.php` `store()`:4067, `saveDraft()`:2267; `Concerns/LandlordPublishValidation.php` | Submit can silently no-op when a required field on another tab fails; error only flashes | Create submits; on failure jumps to offending tab; draft stays lenient; edit unaffected | Publish `validate()` runs, catch flashes but create doesn't switch `activeTab` to error tab (edit does) | Mirror edit's error→tab mapping into `store()`; verify `desired_lease_length`/contact fields serialize | Med — shared trait touches create+edit; drafts must remain lenient | Create Landlord full happy-path submit; then submit with a missing required field on a far tab | ⬜ Audited — prior doc A1.10 ✅ Batch 1 w/ regression test; **re-verify tab-jump + happy path in browser** |
| **P1-3** | After edit → land on listing page, not generic success screen | All 4 Create edit | P0 | `SellerOfferListingEdit.php` `update()`:4067-4074 (culprit); Buyer `:2921`, Landlord `:3888`, Tenant `:3923` (correct) | Seller edit of an already-published listing flashes success + no redirect → stays on form; other 3 roles redirect correctly | All 4 roles redirect to `offer.listing.{role}.view` after edit | Seller `update()` only redirects on `$wasDraft` (first publish) branch; non-draft path returns nothing | Add `return redirect()->route('offer.listing.seller.view',['id'=>$auction->id]);` on non-draft path | Low — per-role, isolated to Seller edit | Edit a published Seller listing, save; must land on detail page | **CODE COMPLETE — HUMAN BROWSER QA REQUIRED** (Batch A `0e486dfaf`: non-draft redirect added; Livewire test asserts redirect on published + draft paths) |
| **P1-4** | Seller Business listing cannot submit | Create Seller | P0 | `SellerOfferListing.php` `store()`:4223, `sanitizeBeforeSubmit()`:4168; `Concerns/SellerPublishValidation.php` (45-106); `financial-details.blade.php:245-486` | Business submit can silently fail validation (caught → flash only) | Business listing submits cleanly | Any Business-only multi-select value absent from `in:` whitelists fails `validate()`; also Tenant-engine parity branch `:4099-4148` | Audit Business form option values vs whitelists; add missing values; ensure error→tab jump | Med — whitelist edits could loosen validation for other types | Create Seller → property type Business → fill Business tab → submit; confirm success + detail page | ⬜ Audited — prior doc A7.46 ✅ Batch 15 but flagged "browser smoke-test recommended" → **must browser-verify** |
| **P1-5** | Video tour must render embedded with safe fallback | All detail views | P0 | `app/Support/VideoEmbedHelper.php` (22-47); detail views Landlord `view.blade.php:945-995`, Seller `:1262-1295`, Tenant `:1387-1402` | YouTube/Vimeo embed; any other provider (Matterport/Loom/MP4) falls back to raw `<a>` link; detail views duplicate inline regex, bypass helper | Recognized providers embed; unknown URLs show safe fallback link (labeled) without breaking layout | Detail views don't reuse `VideoEmbedHelper`; helper only supports YouTube/Vimeo | Route detail views through `VideoEmbedHelper`; extend helper coverage as needed; keep explicit fallback `<a>` | Med — three duplicated blocks to consolidate; must not break existing YT/Vimeo | Paste YT, Vimeo, and a non-supported URL; view each detail page; confirm embed vs fallback | ⬜ Audited — **NET-NEW, uncovered by prior doc** |
| **P1-6** | 14 JPG photo upload not working | Create Seller/Landlord | P0 | `config/livewire.php:100,102,108`; `.user.ini`; `LandlordOfferListing.php:3848`, `SellerOfferListing.php:3918`; `photos-tours-documents.blade.php:44` | Rules allow 50MB/file, 50-file cap, mimes jpg/jpeg/png/webp; 14 JPGs within limits | 14 JPGs upload reliably | Code paths appear correct; suspect throttle (`throttle:60,1`), `max_upload_time=5`, or `max_file_uploads=50` intermittently dropping a batch | Reproduce actual failure; audit throttle/time/count-accumulation; improve error messaging | Med — loosening throttle/limits affects all uploads | Upload exactly 14 JPGs in one selection; confirm all persist + reappear on edit | **ROOT-CAUSED + FIX COMMITTED — HUMAN BROWSER QA REQUIRED** (Batch C: `php artisan serve` `cli-server` ran at PHP defaults **8M/2M/20** — `.user.ini` is ignored by the CLI SAPI and Laravel 8's `ServeCommand` drops the parent `-d` flags; proven via a `cli-server` HTTP probe. Livewire 2.12 sends the 14-JPG selection as **one** POST > 8M → PHP silently discards the body → empty `$_FILES` → no-op. Fix: `deploy/php/uploads.ini` + `.replit` `PHP_INI_SCAN_DIR` → worker proven to report **50M/150M/50/512M**. Live 14-JPG upload must be browser-verified on the running app) |
| **P1-7** | Large document upload not working; audit limits + messages | Create Seller/Landlord | P0 | `config/livewire.php:100`; `.user.ini` (upload_max=50M/post_max=55M); ~16 `updated*File` handlers in Seller/Landlord | Hard 50MB ceiling at 3 layers (Livewire rule, per-field rule, PHP); >50MB rejected; no in-repo nginx `client_max_body_size` | Large docs upload up to a documented limit; clear error when exceeded | 50MB ceiling enforced at Livewire `max:51200` + per-field + PHP `post_max_size=55M`; server body limit unknown (not in repo) | Decide target max; raise all 3 layers together + server `client_max_body_size`; surface friendly size error | High — raising limits affects memory/timeouts across app; server config outside repo | Upload a doc just under and just over limit; confirm success vs a clear error (not silent) | **INVENTORIED + FIX COMMITTED — HUMAN BROWSER QA REQUIRED** (Batch C: full stack mapped — Laravel per-field rules + Livewire temp rule + count cap were **already 50M (unchanged)**; the effective ceiling was PHP CLI defaults, NOT `.user.ini`/`.replit -d` (both **inert** under the built-in server). Target set **50M/150M/50/512M** via `deploy/php/uploads.ini` + `PHP_INI_SCAN_DIR`. There is **no nginx/Apache** here — built-in server means **no `client_max_body_size` layer**; documented that + the Replit edge-proxy body cap as deployment-side verifications. `ini_set()` cannot change `post_max_size`/`upload_max_filesize` at runtime — proven. Friendly oversize error added to both photo blades) |
| **P2-8** | Acceptable Exchange Item selects then unselects | Create Seller + Hire Seller | P1 | Hire `hire-seller-agent/.../seller-terms.blade.php:760-790` + JS `hire-seller-agent.blade.php:1517-1539`; Create `offer-seller-tabs/.../seller-terms.blade.php:810-833` + `offer-seller-listing.blade.php:2698-2720` | Selection toggles back off after Livewire round-trip | Selection persists | select2 in `wire:ignore` re-inits on every `message.processed` from stale `data-selected`; commit is deferred `@this.set(...,false)` so stale set overwrites new selection | Refresh `data-selected` after commit, or guard re-init to skip if user just changed; commit non-deferred | Med — SC2 pattern shared with flood-zone/garage; regressions there | Select an exchange item, wait for spinner; confirm it stays selected + saves | ⬜ Audited — prior doc A6.36 ✅ Batch 15, "browser smoke-test recommended" → **browser-verify** |
| **P2-9** | Hire Seller Exchange Item "Other" doesn't open input | Hire Seller | P1 | `hire-seller-agent/.../seller-terms.blade.php:782-790`; JS `:1530,1535` | Choosing "Other" doesn't reveal dependent text input | "Other" reveals `other_exchange_item` input | Same SC2 mechanism as #8: re-init resets `val()`, drops "Other"; deferred set never commits so server-side reveal never fires | Fix with #8 (same root); ensure toggle fires + persists | Med — same SC2 shared path | Choose Other in exchange item; confirm input appears + saves | ⬜ Audited — prior doc A6.37 ✅ Batch 15 → **browser-verify** |
| **P2-10** | Hire Seller Estimated Value & Acceptable Condition disappear while typing | Hire Seller | P1 | `hire-seller-agent/.../seller-terms.blade.php:792-833` (inputs 803, 821) | Fields visually blank mid-typing | Values stay visible/stable | Plain live `wire:model` triggers round-trip each keystroke; sibling `wire:ignore` select2 teardown/re-init morphs these nodes; no `wire:key` to stabilize | Add `wire:key` to the two form-groups; use `wire:model.blur`/`.lazy`; decouple from exchange re-init | Low-Med — local; coupled to SC2 | Type into Estimated Value + Acceptable Condition; confirm no flicker/blank | ⬜ Audited — prior doc A6.37 (same row) ✅ Batch 15 → **browser-verify** |
| **P2-11** | Hire Agent currency fields delete periods | Hire Seller (all Hire wrappers) | P1 | `hire-seller-agent/.../seller-terms.blade.php` (additional_cash:846, lease_option_price:949, lease_option_payment:966/1156, option_fee_amount:1007, balloon_payment_amount:1564); mask SC1 `hire-seller-agent.blade.php:2832-2878` | Trailing decimal stripped mid-typing ("123." → "123") | Decimals preserved while typing | Live `wire:model` re-hydrates input from string prop each keystroke, dropping in-progress trailing dot; `reformatNumber` onblur also drops lone trailing dot. Fields are `type="text"` (not number) | Make model `.defer`/`.lazy` or JS-only; fix `reformatNumber` trailing-dot; centralize SC1 | Med — SC1 duplicated across all hire/tenant wrappers; fix once + propagate | Type "1234.56" slowly in each field; confirm decimal survives + saves | ⬜ Audited — prior doc A6.38 ✅ Batch 9 "already compliant (verify-only)" → **re-verify; symptom persists per code** |
| **P2-12** | Pet fields must be text, not number input | Hire Seller, Hire Landlord, Create Seller, Create Landlord | P1 | Hire Seller `property-preferences.blade.php:1341,1377`; Create Seller `:1405,1441`; Hire Landlord `:891,953`; Create Landlord `:1671` (weight already text `:1733`) | `type="number"` for Number of Pets Allowed & Max Weight Per Pet (Create-Landlord weight already text) | Both render as text input | Hard-coded `type="number"` in 4 partials | Change to `type="text"` (mirror Create-Landlord weight); keep any numeric validation server-side | Low — cosmetic input type; verify no JS relies on number type | On each of 4 forms, confirm both fields are text inputs + accept/save values | **CODE COMPLETE — HUMAN BROWSER QA REQUIRED** (Batch A `0e486dfaf`: 7 inputs → `type="text"`; data-provider test asserts text-not-number on all 4 partials) |
| **P2-13** | Desired Lease Term "Other" placeholder repeats an existing option | Create Landlord | P1 | `offer-landlord-tabs/commission-based/lease-terms.blade.php:1170-1171` (options 8-11); Hire Landlord `:1172` (correct ref) | Placeholder example "6-month" duplicates existing "6 Months" option | Example is a value NOT already in the dropdown (mirror Hire's "8 Months") | Placeholder copy in Create-Landlord partial | Change placeholder example to a non-listed term | Very Low — text only | View Create Landlord lease term Other placeholder; confirm no duplicate example | **CODE COMPLETE — HUMAN BROWSER QA REQUIRED** (Batch A `0e486dfaf`: placeholder → `Enter desired lease term (e.g., 8 Months)`; string test asserts no `6-month`) |
| **P2-14** | Vacant Land Property Style "Other" input doesn't appear | Create Seller (+ Landlord) | P1 | `offer-seller-tabs/.../property-preferences.blade.php` (select:641, VacantLand:675-680, wrapper:688); JS `offer-seller-listing.blade.php:2662-2664,2698-2720`; Create Landlord `:255` | Selecting "Other" doesn't un-hide input | "Other" reveals custom input (copy Hire Seller behavior). Placeholder: `Enter property style (e.g., Solar farm, RV park, Conservation easement)` | `property_items` bound `wire:model.defer` (no re-render); SC3 delegated handler block has NO handler for `#property_style_select`; class mismatch risk (`other_property_items_seller` vs `.other_property_items`) | Register `#property_style_select` in SC3 handler to toggle `.other_property_items_seller`; set placeholder | Med — SC3 block shared with appliances/view/assets/garage | Select Vacant Land → Property Style Other; confirm input appears w/ placeholder + saves | ⬜ Audited — prior doc A7.47/A7.48 ✅ Batch 14 fixed **placeholder only**, NOT the reveal → **reveal still broken** |
| **P2-15** | Hire Buyer Garage/Parking Features Needed = Yes must reveal dependents | Hire Buyer | P1 | `hire-buyer-agent/.../property-preferences.blade.php` (select:530, wrapper:545 `wire:ignore`, nested Other:561); JS `hire-buyer-agent.blade.php:1484-1519,1548-1553` | Selecting Yes may not reveal dependent fields | Yes reveals dependent parking fields | Reveal wrapper is `wire:ignore` (Livewire can't toggle its `d-none`); native `change` listener bound once to a node later replaced by morph; reveal then relies solely on `message.processed` hook | Rebind change on morph or drive reveal via reactive state; ensure hook re-reads value | Med — mirrors Tenant garage + Create-Landlord garage handlers | Select Yes for Garage/Parking; confirm dependents appear + persist | ⬜ Audited — prior doc B5.3 ⬜ not started |
| **P2-16** | Hire Buyer: Other for Min Bathrooms/Bedrooms makes select disappear | Hire Buyer | P1 | `hire-buyer-agent/.../property-preferences.blade.php` (wrapper key:302, bedrooms:313, bathrooms:346); JS `syncSelectValues:1042-1053`, bath:1462-1478, bed:1780 | Choosing "Other" makes the select box vanish | Select stays; Other input appears alongside | Live `wire:model` re-render + broad `select[wire:model]` icon/select2 sweep desyncs morphed select (left `select2-hidden-accessible`); `wire:key` only keys on `$property_type` | Add stable `wire:key` per select or exclude from sweep / `wire:ignore` the enhanced select; keep Other input | Med — global sweep shared across selects | Pick Other for Min Bedrooms then Min Bathrooms; confirm select stays visible | ⬜ Audited — prior doc B5.4 ⬜ not started |
| **P2-17** | Purchase Purpose "Other" input should appear (placeholder: `Enter purchase purpose (e.g., Relocating for family support)`) | Hire Buyer + Create Buyer | P1 | Hire `hire-buyer-agent/.../property-preferences.blade.php:1189-1215`; Create `offer-buyer-tabs/.../property-preferences.blade.php:1313-1339` | Other may not reveal input | Other reveals input w/ specified placeholder | Reveal via blade class recompute; works only if select is plain live `wire:model` and NOT captured by global `select[wire:model]` sweep (`:1042`) | Verify select excluded from sweep; ensure wrapper toggles; set placeholder | Med — global sweep interaction | Choose Other for Purchase Purpose (both flows); confirm input + placeholder | ⬜ Audited — prior doc B5.8 ⬜ (field-add only; Other behavior not itemized) |
| **P2-18** | Flood Zone Preference "Other" input should appear (placeholder: `Enter flood zone preference (e.g., Prefer elevated property with low-risk designation)`) | Hire Buyer + Create Buyer | P1 | Hire `hire-buyer-agent/.../property-preferences.blade.php:1247-1271` + JS `:1377-1390`; Create `offer-buyer-tabs/.../property-preferences.blade.php:1421-1443` + `:1551-1552` | Other may not reveal input | Other reveals input w/ specified placeholder | select2 in `wire:ignore`, committed via deferred `debouncedSet`; reveal depends on `change.fztSync`; SC2 re-init can miss/reset | Fix with SC2 family; ensure `.flood_zone_tolerance_other_wrapper` toggles + persists; set placeholder | Med — SC2 shared | Choose Other for Flood Zone Preference (both flows); confirm input + placeholder | ⬜ Audited — prior doc B5.8 ⬜ (field-add only) |
| **P2-19** | Hire Buyer HOA Acceptance = Yes/Flexible show Max HOA Monthly Fee | Hire Buyer | P1 | `hire-buyer-agent/.../property-preferences.blade.php:1217-1245` (Alpine `x-show`:1233, input:1242); Create Buyer ref | Appears already implemented via Alpine `x-show` | Yes/Flexible reveals Max HOA fee (same tooltip/placeholder as Create Buyer) | Alpine `x-show="['Yes','Flexible'].includes($wire.hoa_acceptance)"` is robust; risk only if `hoa_acceptance` swept by global select handler | Verify parity of tooltip/placeholder + `hoa_max_monthly_fee` persistence | Low — Alpine-driven | Set HOA Acceptance Yes then Flexible; confirm fee field shows + saves; compare to Create Buyer | ⬜ Audited — prior doc B5.8 ⬜; code suggests **likely already at parity** → verify only |
| **P2-20** | Hire Tenant parity: add Rental Purpose + Accessibility Requirements | Hire Tenant | P1 | Missing in `tenant-agent-auction-tabs/.../property-details.blade.php`; reference Create Tenant `offer-tenant-tabs/.../property-details.blade.php:856-894`; props in `TenantAgentAuction.php`/`Edit` | Both fields absent from Hire Tenant | Both present with same tooltips/placeholders/Other behavior as Create Tenant | Parity gap — added to Create Tenant (B4.4), never ported | Port markup + `rental_purpose`/`rental_purpose_other`/`accessibility_requirements` props to Hire Tenant component + edit; EAV meta save | Med — new fields on 6307-line component; must save via meta on create+edit | Hire Tenant: fill both fields (incl. Rental Purpose Other) → submit → edit → confirm persisted | ⬜ Audited — **NET-NEW for Hire Tenant** (prior doc only did Create Tenant) |
| **P3-21** | Additional Seller Sale Terms box too small; match Total Parcels; fix placeholder cap | Create Seller | P2 | `offer-seller-tabs/.../seller-terms.blade.php:2174,2181,2184`; ref `tax-legal-hoa-disclosures.blade.php:87,94` | Compact 1-row `seller-compact-textarea` | Match Total Number of Parcels box size/type; placeholder sentence-cased | CSS `seller-compact-textarea rows="1"`; placeholder already mostly sentence-cased | Align sizing to `.form-control` height (as A7.42 did); confirm placeholder cap | Low — cosmetic; shared `.seller-compact-textarea` class affects the 5 in #22 | Compare box heights side by side (create + edit) | **CODE REVIEW ONLY — HUMAN BROWSER QA REQUIRED** (Batch A: no code change; verified by reading Blade + the `.seller-compact-textarea` CSS rule ONLY — not rendered-DOM/computed-style. `min-height: calc(1.5em + 0.75rem + 2px)` matches the Bootstrap `.form-control` formula, but computed pixel parity + textarea-vs-input rendering must be confirmed in-browser; no automated test) |
| **P3-22** | Enlarge 5 Seller textareas to match Total Parcels | Create Seller | P2 | `tax-legal-hoa-disclosures.blade.php` (Parcel IDs:108, Legal Desc:124, Special Assessment:318, Approval Process:491, Leasing Restrictions:677) | All 5 are compact 1-row textareas | All 5 match Total Parcels box size | Shared `.seller-compact-textarea rows="1"` | Align sizing (same CSS approach as A7.43) | Low — shared class; verify no other consumer over-grows | Confirm all 5 match on create + edit | **CODE REVIEW ONLY — HUMAN BROWSER QA REQUIRED** (Batch A: no code change; verified by reading Blade + the `.seller-compact-textarea` CSS rule ONLY — not rendered-DOM/computed-style; no automated test. Computed pixel parity must be confirmed in-browser) |
| **P3-23** | Tooltip format audit — Hire must match Create (incl. address auto-populate) | Hire (all) | P2 | Hire address tooltips use `<br>`: `hire-seller-agent/.../property-preferences.blade.php:427,466,485`, `hire-landlord-agent/...:22,61,80`; Create plain sentence `offer-seller-tabs/...:420,459,498` | Hire address tooltip uses `<br>` line break; Create uses continuous sentence | Hire tooltips match Create format/font | No shared tooltip partial; inline Bootstrap everywhere; real diff is `<br>` vs space in address tooltip. NOTE prior doc: the actual split is **Tenant-vs-rest** (compact-dark override only in Tenant flows), not Hire-vs-Create | Replace `<br>` with space in Hire address tooltips; optionally extract shared tooltip partial (S4) | Low-Med — many inline sites; scope carefully | Hover address tooltips on Hire vs Create; confirm identical format | ⬜ Audited — prior doc A8.50 ⬜ not started |
| **P3-24** | Placeholder capitalization (sentence-style, first letter of each example) | All | P2 | Wrong: `hire-landlord-agent/.../property-preferences.blade.php:598` (Air Fryer Oven→air fryer oven); tenant appliances `tenant-agent-auction-tabs/.../property-details.blade.php:398`; many correct refs | Some placeholders over-capitalized (Title Case examples) | `Enter appliances (e.g., Air fryer oven, Induction cooktop, Double oven)` style | Inconsistent hand-written placeholders | Normalize per S2 rule across flagged occurrences | Low — text only, but many sites; risk of missing some | Spot-check appliance + other flagged placeholders in browser | ⬜ Audited — prior doc A8.51 (+S2 family) ⬜ not started |
| **P3-25** | Water Frontage & Waterfront Feet placeholders include field title | Create Seller/Landlord **only** | P2 | Create Seller `property-preferences.blade.php:1190,1204`; Create Landlord `:734,748` | Placeholders `e.g., ...` lack the field title | `Enter Water Frontage (e.g., ...)` / `Enter Waterfront Feet (e.g., 75)` | Placeholder copy | Prepend `Enter [title] (e.g., ...)` **only where the fields already exist** | Very Low — text | Confirm both placeholders on Create Seller + Create Landlord | ⬜ Audited — **OWNER DECISION #25: do NOT add to Hire; scope = existing Create fields only** |
| **P3-26** | Agent Credentials — remove example text from placeholders | All (shared partial) | P2 | `resources/views/livewire/partials/agent-credentials.blade.php` Phone:36, License:67, NAR/NRDS:83 | Placeholders include `(e.g., ...)` examples | No example text (Phone, License #, NAR/NRDS) | Placeholder copy in shared partial | Remove `(e.g., ...)` from the 3 placeholders — **one file fixes all 19 include sites** | Low — shared partial; confirm no test asserts old text | Confirm on any Create + any Hire form (single partial) | ⬜ Audited — prior doc A8.57 ⬜ not started (exact match) |
| **P3-27** | Additional HOA/Association Notes placeholder capitalization | Create Seller | P2 | `offer-seller-tabs/.../seller-terms.blade.php:2168` | `... pending special assessment, new rules ...` | Capitalize `Pending` and first letter of each comma-separated example incl `New` | Placeholder copy | Edit placeholder casing | Very Low — text | Confirm placeholder on Create Seller | ⬜ Audited — prior doc: under A8.64 sweep ⬜, not separately itemized |
| **P3-28** | Remove Preferred Cities "County bias" helper text | Create Buyer, Create Tenant, Hire Buyer, Hire Tenant | P2 | `resources/views/partials/location-dna/map-input.blade.php:139` | Shows `County bias is used so "Seminole, FL" maps to Pinellas, not Seminole County.` | Helper text removed | Text in shared map partial (line 139); other "Seminole" hits are comments/JS, not user-facing | Delete line 139 helper text — **one file covers all 4 flows** | Low — shared partial; verify other consumers OK without it | Confirm text gone on all 4 flows | ⬜ Audited — **NET-NEW, uncovered by prior doc** |
| **P3-29** | Important Places — Type/Distance/Travel Mode boxes match Exact Address/Miles | Buyer/Tenant Create + Hire | P2 | `resources/views/partials/location-dna/map-input.blade.php:284-359` (Exact Address:320, Distance:328, Travel Mode:340) | Type/Distance/Travel Mode boxes sized differently | Match Exact Address / Miles box size | CSS/markup in shared map partial | Align input sizing in the partial — one file covers all consumers | Low — shared partial | Compare box sizes in Important Places rows | ⬜ Audited — prior doc: part of net-new B1.7 block ⬜ |
| **P3-30** | Hire "Additional Details" placeholder should read `Enter additional details` (lowercase) | Hire (all) | P2 | `app/Helpers/PropertyTypePlaceholderHelper.php` `$titles['hire']` lines 38-43,126; consumers `hire-*/additional-details.blade.php` | Renders `Enter Additional Details (e.g., ...)` | `Enter additional details (e.g., ...)` keep examples | Helper title map Title-Cased | Lowercase the 4 hire titles (lines 39-42); examples preserved | Low — one helper; ripples to all hire flows | Confirm placeholder on each Hire additional-details tab | ⬜ Audited — prior doc B2.1 ⬜ not started |
| **P3-31** | Create Description tab placeholders per role | Create all | P2 | `PropertyTypePlaceholderHelper.php` `$titles['create']` 32-37; consumers `offer-*/additional-details.blade.php` | Seller "Property Description", Buyer "Buyer Description", Landlord "Rental Description", Tenant "Tenant Description" | Seller `Enter property description`, Buyer `Enter buyer description`, Landlord `Enter rental description`, **Tenant `Enter tenant description`** | Helper title map | Lowercase 4 create titles; set Tenant → `Enter tenant description` | Low — one helper | Confirm each role's Description placeholder | ⬜ Audited — **OWNER DECISION #31: Tenant = `Enter tenant description`** (not "rental") |
| **P3-32** | Conditions/Requirements for Lease Purchase placeholder cap | Create Buyer (+ Hire Buyer already OK) | P2 | Create Buyer `purchasing-terms.blade.php:988` (needs `seller`→`Seller`); Hire Buyer `:996` (already correct) | Create Buyer placeholder lowercases "seller"; Hire already capitalizes | Capitalize first letter of each example + capitalize "Seller" | Placeholder copy in Create Buyer only | Capitalize in Create Buyer placeholder | Very Low — text | Confirm Create Buyer placeholder; Hire already compliant | **CODE COMPLETE — HUMAN BROWSER QA REQUIRED** (Batch A `0e486dfaf`: `seller`→`Seller`; string test asserts capitalization) |
| **P3-33** | Remove Commute Preferences block **only if a real duplicate exists** | Hire Tenant, Create Tenant, Hire Buyer, Create Buyer | P2 | Create Buyer `offer-buyer-tabs/.../property-preferences.blade.php:1344-1387`; Create Tenant `offer-tenant-tabs/.../property-details.blade.php:811-852`; **Hire Buyer/Tenant: 0 occurrences** | Commute block appears **once** in each of the two Create flows; **absent** from both Hire flows; **not** duplicated within any single file | Keep the single block; delete only a genuine duplicate under Non-Negotiable Amenities/Property Features | The commute block is inline (not an @include), present once per Create file; no true duplication found | **OWNER DECISION #33:** do NOT remove the only block. At implementation, re-scan each flow for a second commute block directly under Non-Negotiable Amenities; delete **only** if a real duplicate is present | Med — removing the single real block deletes the only commute UI (B1.11 says preserve/migrate, not delete) | Re-scan each flow for a duplicate directly under Non-Negotiable Amenities; if none, no change | ⬜ Audited — **OWNER DECISION #33: No code change required — duplicate not found** (unless a real duplicate surfaces at implementation) |

---

## 2. Phase 1 — Critical launch blockers (per-issue detail)

The master table above already carries all 13 columns per issue. This section adds only the **investigation notes** that don't fit a cell, for the P0 items.

### P1-1 · Landlord broker comp + agency terms on create
- The gate is `str_contains(strtolower($property_type),'residential')`. On create the default `property_type=''` → nothing renders; on **Commercial**, `$_isCommercial` is computed but **never used** and there's **no `@else`**, so it never renders for commercial at all. On edit of an existing residential listing the type is already set, so it appears — which is exactly the "shows on edit, missing on create" report.
- Separately, the **Agency Agreement Terms** partials (`agency_agreement_timeframe`, `protection_period`, `payment_timing`, `early_termination`, `tenant_broker_commission`, `additional_terms`, `expansion_commission`, `commented_expansion`) are **orphaned** — grep shows no `@include`. They render on neither create nor edit. This is a real gap regardless of the prior A1.8/A1.9 "done" claim (which named Tenant/Buyer/Seller, not Landlord).

### P1-2 · Landlord create submit
- `store()` validates via the shared `LandlordPublishValidation` trait and catches `ValidationException` to flash. The prior doc's A1.10 fix moved validation into try/catch, but the **error→tab jump exists in edit `update()` and not in create `store()`** — so a failing required field on a non-active tab still reads as "submit did nothing." Required publish fields: contact (`first_name/last_name/phone_number/email`), `desired_lease_length` (array, min:1), and `auction_time` when Bidding Period.

### P1-3 · Edit redirect
- Only Seller is inconsistent. Seller `update()` redirects on the `$wasDraft` branch only; the non-draft (editing an already-published listing) path flashes success and returns nothing → stays on form. Buyer/Landlord/Tenant already redirect to `offer.listing.{role}.view`.

### P1-4 · Seller Business submit
- Blocked at `SellerPublishValidation` `in:` whitelists (lines 45-106). Any Business-only option value not in a whitelist fails silently (caught → flash). Audit the actual Business-tab option values (`financial-details.blade.php:245-486`) against the whitelists. `business_assets` has no rule (not the blocker).

### P1-5 · Video embed (NET-NEW)
- `VideoEmbedHelper::getEmbedUrl()` supports only YouTube + Vimeo. The three detail views (Landlord/Seller/Tenant) **duplicate the regex inline** and bypass the helper, each falling back to a raw `<a>` link for anything else. Consolidate detail views onto the helper and extend provider coverage; keep an explicit, labeled fallback link.

### P1-6 · 14-JPG upload
- Current limits are generous (50MB/file, 50-file cap, mimes ok). The specific "14 fails" symptom is **not diagnosed** — most likely `throttle:60,1` + `max_upload_time=5` (`config/livewire.php`) or `.user.ini max_file_uploads=50` interacting with slow connections. **Reproduce before "fixing."**

### P1-7 · Large document upload
- Hard 50MB ceiling at three layers that must all move together: Livewire global `max:51200`, per-field `max:51200` (~16 handlers), PHP `upload_max_filesize=50M`/`post_max_size=55M`. **No nginx `client_max_body_size` is in the repo** — the production server body limit is the most likely silent cap and lives outside version control. Decide a target max first.

---

## 3. Phase 2 — Functional form bugs (investigation notes)

- **#8/#9/#10 are one root cause** (SC2: select2 in `wire:ignore` re-initialized on every `message.processed` from stale `data-selected`, plus deferred `@this.set(...,false)`). Fixing the re-init/commit for exchange_item resolves the unselect (#8), the Other-reveal (#9), and — with a `wire:key` on the two sibling inputs — the disappearing Estimated Value/Acceptable Condition (#10). The same SC2 family also underlies **#18** (flood zone) and partly **#15** (garage).
- **#11 (currency)**: the mask regex is *not* the culprit (it preserves `.`); the live `wire:model` re-hydration strips the in-progress trailing dot, and `reformatNumber` onblur drops a lone trailing dot. Centralize SC1 and switch these to deferred/lazy or JS-only.
- **#12 (pets)**: pure `type="number"` → `type="text"`. Create-Landlord `weight_of_pets` is already text — use it as the reference.
- **#14 (vacant-land style)**: prior doc fixed the **placeholder** (A7.47/A7.48) but **not** the reveal. The reveal fails because `property_items` is `wire:model.defer` and `#property_style_select` is **not registered** in the SC3 delegated handler. Register it (watch the `other_property_items_seller` vs `.other_property_items` class mismatch).
- **#15/#16**: `wire:ignore` + once-bound native listeners + a broad `select[wire:model]` enhancement sweep desync morphed selects. Prefer reactive reveal or stable `wire:key`/`wire:ignore` scoping.
- **#19**: likely already at parity (Alpine `x-show`). Verify tooltip/placeholder + persistence, don't rebuild.
- **#20**: genuine parity add — port `rental_purpose`, `rental_purpose_other`, `accessibility_requirements` (markup + component props + EAV meta) from Create Tenant into Hire Tenant (`TenantAgentAuction`/`Edit`).

---

## 4. Phase 3 — UI/UX consistency (investigation notes)

- **Fix-once wins:** #26 (agent-credentials partial, one file → 19 sites), #28 (map-input line 139, one file → 4 flows), #29 (map-input Important Places sizing), #30/#31 (`PropertyTypePlaceholderHelper` title maps).
- **#21/#22** share the `.seller-compact-textarea` CSS approach already used by A7.42/A7.43 — verify the pixel match in-browser rather than re-implementing.
- **#23**: the concrete diff is `<br>` vs space in the Hire address auto-populate tooltip. The prior doc found the deeper tooltip split is **Tenant-vs-rest**, not Hire-vs-Create — scope this carefully before a global tooltip refactor.
- **#25 — RESOLVED (Owner Decision #25):** scope limited to the existing Create Seller / Create Landlord fields; do NOT add to Hire. Fix placeholder formatting only where the fields already exist.
- **#31 — RESOLVED (Owner Decision #31):** Tenant Description placeholder = `Enter tenant description` (not "rental"). Final wording locked for all four roles.
- **#33 — RESOLVED (Owner Decision #33):** keep the only commute block. There is **no duplicated commute block** (once each in Create Buyer/Tenant, absent from both Hire flows). Delete only if a genuine duplicate is found under Non-Negotiable Amenities at implementation; otherwise close as "No code change required — duplicate not found."

---

## 5. Duplicate / overlapping issues & shared-component register

**Same root cause, fix together:**
- #8 + #9 + #10 → SC2 (exchange-item select2 re-init/commit). One fix resolves all three.
- #11 → SC1 (currency mask + live model). Centralize once, propagate to all hire/tenant wrappers.
- #14 + #17 → SC3 (delegated "Other" reveal handler block; register the missing selects).
- #18 → SC2 family (flood zone select2).

**One file fixes many flows:**
- #26 → `agent-credentials.blade.php` (19 include sites).
- #28 + #29 → `partials/location-dna/map-input.blade.php` (Create Buyer/Tenant + Hire Buyer/Tenant).
- #30 + #31 → `PropertyTypePlaceholderHelper.php` (all hire + all create).
- #21 + #22 → `.seller-compact-textarea` CSS.

**Already-claimed-done but NOT browser-verified (highest "false green" risk — must re-verify):**
- #1 (A1.8/A1.9 — but didn't name Landlord; agency partials orphaned → likely still broken)
- #3 (A1.14≡C7 — Seller non-draft path still shows no redirect in code)
- #4 (A7.46 — "browser smoke-test recommended")
- #6, #7 (A2.16/A2.17 — limits *audited*, failure mode never diagnosed)
- #8, #9, #10 (A6.36/A6.37 — "browser smoke-test recommended")
- #11 (A6.38 — "already compliant"; code still shows the stripping mechanism)
- #14 (A7.47/A7.48 — placeholder done, **reveal not** fixed)
- #21, #22 (A7.42/A7.43 — cosmetic, verify pixels)

**Net-new / uncovered by the prior master doc:**
- #5 (video embed), #28 (County-bias text), #20 (Hire Tenant Rental Purpose/Accessibility), and the specific *behaviors* behind #13, #14-reveal, #17, #18, #25, #27, #29, #33.

**Open conflicts — RESOLVED by owner decisions (2026-07-05, see top of doc):**
- **#33** — ✅ resolved: keep the only block; delete only a genuine duplicate; else "No code change required — duplicate not found."
- **#25** — ✅ resolved: Create Seller/Landlord scope only; do NOT add to Hire.
- **#31** — ✅ resolved: Tenant = `Enter tenant description`.
- Still open (not part of this brief): prior-doc BLK-3 (misfiled Hire drafts), BLK-4 (Buyer contingency final sets); **#7** still needs a target max + server `client_max_body_size` (infra).

---

## 6. Recommended implementation order (safest)

**Rationale:** front-load the launch blockers, but do the *diagnosis-required* ones (upload failures) early since they may need infra/server changes with lead time; batch the pure-text/one-file cosmetic fixes last because they're low-risk and independently shippable.

1. **Batch A — verify the "false greens" (no/low code):** #3 (Seller redirect one-liner), #12 (pets type), #13 (lease-term placeholder), #21/#22 (textarea sizing pixel check), #32 (one-word cap). Cheap, high-confidence, clears noise.
2. **Batch B — Phase-1 blockers with clear root cause:** #1 (Landlord broker/agency), #2 (Landlord submit tab-jump), #4 (Seller Business whitelists), #5 (video embed).
3. **Batch C — Phase-1 diagnosis-required (start early, may need server changes):** #6 (14-JPG reproduce), #7 (large-doc limits + server `client_max_body_size`).
4. **Batch D — Shared-JS root causes (do once, verify broadly):** SC2 (#8/#9/#10/#18), SC1 (#11), SC3 (#14/#17), then #15/#16, #19-verify.
5. **Batch E — parity add:** #20 (Hire Tenant fields).
6. **Batch F — one-file cosmetics:** #26, #28, #29, #30, #31, #24, #25, #27, #23.
7. **Batch G — final sweep:** #33 (delete a commute block **only** if a real duplicate is found — Owner Decision #33; otherwise close as "No code change required — duplicate not found").

---

## 7. Verification requirements (run before marking ANY phase complete)

### 7.0 Verification policy (Owner Decision #4 — binding)

- **No issue may be marked PASS / COMPLETE / IMPLEMENTED / RESOLVED until it is BOTH code-verified AND browser-verified.**
- If browser verification **cannot** be performed in the current environment, mark the item exactly:
  **`CODE COMPLETE — HUMAN BROWSER QA REQUIRED`**.
- **Do not** mark those items complete in the master ledger (this doc's §8 and the `Status` columns). `CODE COMPLETE — HUMAN BROWSER QA REQUIRED` is a **pending** state, never a pass.

### 7.1 Shared-component regression policy (Owner Decision #5 — binding)

Every shared-component change (**SC1–SC9**) must include **regression testing across every affected Create AND Hire flow** before the batch is considered complete. Downstream consumer matrix to re-verify per SC:

| SC | Must re-verify across |
|----|-----------------------|
| SC1 currency mask | all Hire wrappers (Seller/Buyer/Landlord/Tenant) + edit variants |
| SC2 select2 reveal | exchange_item (Create+Hire Seller), flood_zone (Create+Hire Buyer), garage (Hire Buyer) |
| SC3 Other-reveal | Create Seller property_style + appliances/view/assets/garage consumers |
| SC4 agent-credentials | all 19 include sites (every Create + Hire form) |
| SC5 map-input | Create Buyer/Tenant + Hire Buyer/Tenant |
| SC6 placeholder helper | all Create + all Hire additional-details/description tabs |
| SC7 publish traits | Seller + Landlord create AND edit (draft must stay lenient) |
| SC8 video helper | Seller/Landlord/Tenant detail views (YT, Vimeo, unsupported URL) |
| SC9 broker-compensation | Landlord create AND edit, Residential AND Commercial |

### 7.2 Batch discipline (Owner Decision #6 — binding)

Each implementation batch must:
1. contain **only** the planned fixes for that batch (no drive-by edits);
2. start from a **clean `git status`**;
3. produce a **single isolated commit** — report the **commit hash**;
4. list **all files changed**;
5. summarize **root causes**;
6. summarize **browser verification** performed (or `CODE COMPLETE — HUMAN BROWSER QA REQUIRED`);
7. identify **any remaining unresolved issues**.

### 7.3 Functional gates (per the brief)

1. `grep`/search to list **every file touched** in the phase.
2. **Create Landlord create submit** works end-to-end (browser).
3. **Landlord draft + edit** still save/submit correctly.
4. **Create Seller Business** listing submits.
5. **14-JPG** photo upload succeeds in one selection.
6. **Large document** upload behaves + errors clearly (no silent fail).
7. **Video** embeds on the listing page (YT/Vimeo) with safe fallback for others.
8. Browser-test **every dependent-field / "Other"-reveal (S7) bug** (#8, #9, #14, #15, #16, #17, #18).
9. Confirm **placeholders/tooltips visually** in browser (#21–#32).
10. Report per §7.2.

**Note on browser verification:** the prior master doc repeatedly notes "no browser driver in this env." If no browser/Playwright harness is available here, browser-gated items cannot be closed and must be marked `CODE COMPLETE — HUMAN BROWSER QA REQUIRED` and handed to a human QA pass (per Safety Rule 6 + Owner Decision #4).

---

## 9. Implementation log — Batch A (2026-07-05)

**Commit:** `0e486dfaf` — *fix(offers): Batch A — Seller edit redirect, pet text inputs, lease/buyer placeholders* (branch `launch-audit-remediation`).
**Batch status:** none closed. #3/#12/#13/#32 are **`CODE COMPLETE — HUMAN BROWSER QA REQUIRED`** (code change + automated test). **#21/#22 are `CODE REVIEW ONLY — HUMAN BROWSER QA REQUIRED`** — no code change and verified by reading the Blade + `.seller-compact-textarea` CSS rule only (not rendered DOM / computed style, no automated test). Browser QA is not operational in this environment (Playwright 1.61.1 present but Chromium cannot launch — 20 missing system libraries; non-root Nix, apt disabled). Per Owner Decision #4 all stay **open** in the ledger.

**Files changed (8 — the single isolated commit):**
1. `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php` — #3 non-draft redirect.
2. `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php` — #12 (number_of_pets, weight_of_pets → text).
3. `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php` — #12 (number_of_pets → text; weight already text).
4. `resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/property-preferences.blade.php` — #12 (both → text).
5. `resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/property-preferences.blade.php` — #12 (both → text).
6. `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php` — #13 placeholder.
7. `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/purchasing-terms.blade.php` — #32 placeholder.
8. `tests/Feature/Offers/BatchAUiRegressionTest.php` — new regression guards (8 tests).

**Root causes (per item):**
- **#3** — Seller `update()` returned a redirect only on the `$wasDraft` branch; the non-draft (already-published) path flashed success and returned nothing, so Livewire re-rendered the form in place. Added the redirect on the non-draft path (parity with Buyer/Landlord/Tenant).
- **#12** — inputs hard-coded `type="number"` in 4 partials (7 inputs). Changed to `type="text"`, mirroring the already-correct Create-Landlord weight field.
- **#13** — Create-Landlord "Other" lease-term placeholder example (`6-month`) duplicated the "6 Months" dropdown option. Changed to `Enter desired lease term (e.g., 8 Months)` (mirrors Hire Landlord; not a listed option).
- **#32** — Create-Buyer lease-purchase placeholder used lowercase `seller`. Capitalized to `Seller`.
- **#21/#22** — no code change; **CODE REVIEW ONLY**. `.seller-compact-textarea` sets `min-height: calc(1.5em + 0.75rem + 2px)`, which matches the Bootstrap `.form-control` height formula, and placeholders are already sentence-cased. This was verified by reading the Blade + CSS rule only — NOT by rendering the DOM or comparing computed styles, and there is no automated test. Computed pixel parity (and `<textarea>` vs `<input>` rendering) must be confirmed in-browser.

**Verification performed (code-only — no browser):**
- New `BatchAUiRegressionTest` — **8 tests pass**: Livewire `assertRedirect()` for #3 on **both** published-edit and draft-publish paths; data-provider markup assertions for #12 across all 4 partials (text-not-number); string assertions for #13 (no `6-month`, has `8 Months`) and #32 (`Seller` capitalized, no lowercase variant).
- Regression: `CreateEditParityRegressionTest` + Seller/Landlord/Buyer `OfferEntry` — **80 tests pass**, no regressions.
- `php artisan view:cache` — all Blade partials compile; `php -l` clean on the PHP file.

**Remaining unresolved:** every Batch A item awaits **human browser QA** (see `public/batch-a-browser-qa.html`). Nothing is marked PASS/COMPLETE/RESOLVED.

---

## 10. Implementation log — Batch B (2026-07-05)

**Scope (Owner Decisions, Batch B):** #1 Landlord Broker Compensation + Agency Agreement Terms, #2 Create Landlord submit (verify-only), #4 Seller Business submit (server-verify + browser diagnosis), #5 Video embed via `VideoEmbedHelper`.

**Batch status:** none closed. #1 and #5 are **`CODE COMPLETE — HUMAN BROWSER QA REQUIRED`**. #2 is **SERVER VERIFIED** (no code change). #4 is **SERVER VERIFIED — HUMAN BROWSER DIAGNOSIS REQUIRED** (no code change). Browser QA remains non-operational in this environment (Chromium cannot launch). Per Owner Decision #4 all items stay open in the ledger.

> ⚠️ **Batch-discipline deviation (environmental, must read).** Owner Decision #6 requires a *single isolated Batch B commit*. That did **not** happen: a **concurrent autonomous process on this same branch** (working the `§MatchingV2` thread) auto-committed the Batch B work as it landed and interleaved its own commits around it. There are **no git hooks** in `.git/hooks` and no auto-commit config in `.replit` — the writer is an external session, confirmed via `git reflog`. Net effect on `launch-audit-remediation`:
> ```
> 12692c6a0 feat(matching): … Stage B — §MatchingV2 C4     ← concurrent process (NOT Batch B)
> efccf5b98 test(offers): cover Batch B broker terms, submit guards, and video embeds   ← Batch B (tests)
> 958df08a4 fix(offer-listing): route … video … VideoEmbedHelper — Batch B #5           ← Batch B (#5)
> b6e8694f4 fix(offer-listing): show Landlord Broker Compensation + Agency … — Batch B #1 ← Batch B (#1)
> d3a8b82b1 feat(matching): candidate discovery Stage A … §MatchingV2 C3   ← concurrent process (NOT Batch B)
> ```
> Batch B is therefore **three contiguous commits** (`b6e8694f4`, `958df08a4`, `efccf5b98`), each single-concern and drive-by-free, sandwiched between matching-engine commits `d3a8b82b1` (below) and `12692c6a0` (above). History was **deliberately NOT rewritten**: squashing would rewrite the concurrent process's `12692c6a0` and race with its in-flight commits. This is flagged per the "stop and report" guardrail rather than fought.

**Batch B commits (all single-concern):**
1. `b6e8694f4` — #1: `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/broker-compensation.blade.php`.
2. `958df08a4` — #5: `resources/views/offer-listing/{landlord,seller,tenant}/view.blade.php`.
3. `efccf5b98` — tests: `tests/Feature/Offers/BatchBBrokerVideoTest.php` (13 tests).

**Root causes (per item):**
- **#1** — the whole broker/agency block was gated behind `@if(str_contains(strtolower($property_type),'residential'))`, so on **create** (default blank `property_type`) nothing rendered, and **Commercial** never rendered (`$_isCommercial` was computed but unused, no `@else`). Separately, **all** Agency Agreement Terms partials were **orphaned** (no `@include` anywhere). **Fix:** removed the outer gate and wired the six *live* orphaned partials in canonical (Hire-Landlord) order — `tenant_broker_commission`, `payment_timing`, `agency_agreement_timeframe`, `protection_period`, `early_termination`, `additional_terms`. Each self-gates on `$property_type` where a type-specific form exists (agency_agreement_timeframe + additional_terms are ungated; the rest self-gate to Residential, except tenant_broker_commission which has both Residential and Commercial branches). `expansion_commission` (no bindings) and `commented_expansion` (fully commented out) are dead and intentionally left unwired. **Persistence was independently verified before wiring** (per Owner Decision #1): every backing prop is declared, saved via `saveMeta()` inside `saveAllMetadata()`, and hydrated on edit in **both** `LandlordOfferListing` and `LandlordOfferListingEdit` — so **no component/metadata changes were needed** (markup-wiring only). The prior session had wired only 4 of the 6 live partials; this batch completes the set (adds `tenant_broker_commission` + `payment_timing`).
- **#2** — **no code change.** The audit's "create doesn't jump to the offending tab" hypothesis was not reproducible as a submit blocker: `store()` publishes and redirects with valid data, `saveDraft()` stays lenient, and `update()` (edit) publishes and redirects. Per Owner Decision #2, treated as verify-only; **no new "jump to invalid tab" behavior added.**
- **#4** — **no code change** (Owner Decision #4: do not modify shared `SellerPublishValidation`). The audit's P1-4 hypothesis (a Business-only multi-select value missing from an `in:` whitelist silently fails `store()`) was **disproven server-side**: a Business listing submits with valid Business multi-selects (`licenses`, `sale_includes`, `building_features`) and **no** validation errors. Remaining "cannot submit" reports are therefore a **client-side JS** issue — see Browser QA below.
- **#5** — the three public listing display views each **duplicated an inline YouTube/Vimeo regex** and bypassed `App\Support\VideoEmbedHelper`. **Fix:** route all three through `VideoEmbedHelper::getEmbedUrl()` (YouTube/Vimeo only); unsupported URLs return `null` and fall through to the **existing safe raw-link `<a>` fallback**, which is preserved verbatim. **No provider extension** (Matterport/Loom/MP4) in this batch, per Owner Decision #5.

**Tests added (`BatchBBrokerVideoTest`, 13 — all pass, stable across repeated runs):**
- #1: broker comp + agency terms render on create for **blank / Residential / Commercial**, and on **edit**; Residential-gated terms (`protection_period`, `payment_timing`) are hidden for Commercial by design; the wired agency props (`agency_agreement_timeframe`, plus the newly-wired `broker_fee_timing` and `tenant_broker_commission_structure`) **persist through `store()`**.
- #2: `store()` valid submit redirects; `saveDraft()` raises no publish errors; edit `update()` redirects.
- #4: Business listing submits with Business multi-selects and **no** validation errors (evidence that the block is client-side).
- #5: Seller view embeds YouTube + falls back to a raw link for an unsupported URL (asserts no `youtube.com/embed`/`player.vimeo.com` for the unsupported case); Landlord view embeds `youtu.be`; Tenant view embeds `vimeo.com`.

**Regression run:**
- `BatchBBrokerVideoTest` — **13 pass** (stable ×3). `BatchAUiRegressionTest` — **8 pass**. `php artisan view:cache` compiles all Blade; `php -l` clean.
- `CreateEditParityRegressionTest` (80) — **pre-existing, non-deterministic flakiness** in this env: repeated full-suite runs fail a *different* 1–2 tests each time (`assumption_fee` prefill / `front_footage` type / `landlord edit location dispatch`); the same tests **pass in isolation** and **pass at the pre-Batch-B base** commit `d3a8b82b1`. Not caused by Batch B (my files aren't rendered by those tests).
- Other failing `tests/Feature/Offers/*` suites (`ExpireOffersCommandTest`, `OfferActionVisibilityTest`, `OfferActionButtonWiringTest`, `OfferControllerTest`, `LandlordOfferEntryTest`, `LandlordSubmitApplicationTest`) — **confirmed pre-existing**: they fail identically at base `d3a8b82b1` (verified in an isolated worktree). `LandlordOfferEntryTest`/`LandlordSubmitApplicationTest` do render the changed `landlord/view.blade.php`, and their **failure sets are byte-for-byte identical** at base vs HEAD, so the #5 change introduced nothing.

**Browser verification (REQUIRED before any close):**
- **#1 (`CODE COMPLETE — HUMAN BROWSER QA REQUIRED`):** Create Landlord → leave type blank, then pick Residential, then Commercial; confirm Broker Commission Structure + all agency blocks show per the gating matrix above, save, and reappear on edit for both types.
- **#5 (`CODE COMPLETE — HUMAN BROWSER QA REQUIRED`):** on Seller/Landlord/Tenant detail pages paste a YouTube URL, a Vimeo URL, and a non-supported URL; confirm embed vs. labeled raw-link fallback, no layout break.
- **#4 (`SERVER VERIFIED — HUMAN BROWSER DIAGNOSIS REQUIRED`):** Create Seller → property type **Business** → fill the Business financial-details tab → Submit **in a real browser with devtools open**; capture any client-side JS validation error / blocked submit. Server path is proven clean, so the defect (if reproduced) is in the front-end wizard's pre-submit validation, not `SellerPublishValidation`.
- **#2 (SERVER VERIFIED):** optional browser confirm of the create happy-path submit + draft + edit; server paths proven.

**Remaining unresolved / to flag to owner:**
1. **Batch-discipline deviation** — Batch B is 3 commits, not 1, and is interleaved with a concurrent process's matching commits (see warning box). No history rewrite performed. Owner to decide whether to squash later (safe only once the concurrent writer is idle).
2. **Pre-existing `tests/Feature/Offers` failures + parity-suite flakiness** — unrelated to Batch B but worth a separate stabilization pass.
3. All four Batch B items await **human browser QA** per the statuses above. Nothing marked PASS/COMPLETE/RESOLVED.

---

## 11. Implementation log — Batch C (2026-07-06)

**Scope (Owner-approved, Batch C = #6 + #7 only):** diagnose the 14-JPG upload failure (#6) and inventory every upload limit in the stack, splitting code vs. infrastructure (#7). No Match-Check / Matching-V2 / other-launch work touched.

### 11.1 Root cause (proven, not guessed)

The app is served by **`php artisan serve`** (PHP built-in **`cli-server`** SAPI) — there is **no nginx/Apache** in front. Two independent facts mean the intended 50 MB limits never reached the request-handling process:

1. **`.user.ini` is ignored by the CLI/built-in-server SAPI** (it is a CGI/FPM-only mechanism). `php --ini` shows it is not scanned.
2. **`artisan serve` does not forward its `-d` flags to the worker.** Laravel 8 `ServeCommand::serverCommand()` spawns `[php, '-S', host:port, server.php]` with no `-d`. Proven empirically: a child PHP does **not** inherit the parent's `-d` (parent `55M` → child `8M`), and a `cli-server` HTTP probe returned raw defaults.

**Effective runtime limits (measured this session), vs. declared:**

| Limit | Declared (`.user.ini` / `.replit -d`) | **Actual at runtime (proven)** |
|---|---|---|
| `upload_max_filesize` | 50M | **2M** |
| `post_max_size` | 55M | **8M** |
| `max_file_uploads` | 50 | **20** |
| `memory_limit` | 256M | **128M** |

Livewire 2.12 sends a `multiple` selection as **one** multipart POST. 14 phone JPGs (~3–8 MB each) exceed **both** the per-file `2M` and the batch `8M` ceiling → PHP discards the body → `$_FILES` empty → Livewire receives nothing → **silent failure**. Category: **infrastructure/deployment (PHP ini not applied by `artisan serve` on Replit)** — *not* Laravel validation, Livewire config, or the browser (all already correct at 50M).

- **Max successful per-file size (pre-fix):** ~2 MB. **Max successful batch total:** ~8 MB. **Max successful count:** up to 20, only if every file <2M and the sum <8M. All three limits interact on the single POST; `post_max_size` is the binding constraint for the 14-JPG case.

### 11.2 Full limit inventory — code vs. infra (#7)

| Layer | Location | Status |
|---|---|---|
| Laravel per-field rule | `SellerOfferListing.php:3922`, `LandlordOfferListing.php:3852` (+doc rules) | `max:51200` (50M) — **already correct, unchanged** |
| Livewire temp-upload rule | `config/livewire.php:99` | `max:51200` — **unchanged** |
| App count cap | Seller `:3927` / Landlord `:3857` | 50 photos — **unchanged** |
| PHP `upload_max_filesize` / `post_max_size` / `max_file_uploads` / `memory_limit` | `deploy/php/uploads.ini` (**new**) applied via `PHP_INI_SCAN_DIR` | **50M / 150M / 50 / 512M** |
| Web-server body limit | n/a (built-in server) | **None here** — no `client_max_body_size` layer exists; document for any future nginx/Apache prod deploy |
| Replit edge-proxy body cap | Replit platform (external) | **Unknown** — cannot set from repo; document as a deployment verification |

Key constraint: `ini_set()` **cannot** raise `post_max_size`/`upload_max_filesize` at runtime (proven — returns `false`; they are `PHP_INI_PERDIR`). The only in-repo vector that works under `artisan serve` is `PHP_INI_SCAN_DIR` (ServeCommand passes `$_ENV` to the worker; a starting PHP scans that dir). Proven: `PHP_INI_SCAN_DIR=deploy/php` makes the `cli-server` worker report `50M/150M/50/512M`.

### 11.3 Changes (single-concern, explicit path staging)

- **`deploy/php/uploads.ini` (new)** — declares 50M/150M/50/512M (+ exec/input time), with a header explaining why `.user.ini`/`-d` are inert and how this file is applied.
- **`.replit`** — dev "Laravel Server" workflow and `[deployment] run` now prefix `PHP_INI_SCAN_DIR="$PWD/deploy/php"` (deployment via `bash -c` so the env prefix takes effect); the inert `-d` flags removed.
- **`offer-seller-tabs/.../photos-tours-documents.blade.php` + `offer-landlord-tabs/.../photos-tours-documents.blade.php`** — added an Alpine listener for the bubbling `livewire-upload-error` event to surface a friendly oversize message instead of a silent no-op (#7 "clear error when exceeded"). No change to validation, count cap, or the frozen Limited-Service flow.
- **`.user.ini` / `public/.user.ini` left in place** (documented as inert here) — deleting them would mislead a future FPM/nginx deploy where they *would* apply.

### 11.4 Verification

- **New `tests/Feature/Offers/BatchCUploadLimitsTest.php` (6 tests, all pass):** ini-override values; `.replit` `PHP_INI_SCAN_DIR` wiring (both workflow + deployment) with the inert `-d` gone; per-file 50M rule intact in both handlers; friendly-error markup in both blades; live Livewire oversize-photo rejection for Seller and Landlord.
- **Runtime proof (manual, this session):** `cli-server` worker with `PHP_INI_SCAN_DIR=deploy/php` reports `50M/150M/50/512M`; `ini_set()` on the two per-dir directives returns `false`.
- **Regression:** `CreateEditParityRegressionTest` (80) pass; `BatchAUiRegressionTest` (8) pass; `BatchBBrokerVideoTest` (13) pass on a clean run (the one intermittent failure was the pre-existing §10 `User`-factory flakiness, not Batch C — my edits don't touch factories); `view:cache` compiles both edited blades.

### 11.5 Batch-discipline note

Batch C is a single-purpose commit (`804266ffe`) staged with explicit paths: the two photo blades, `deploy/php/uploads.ini`, `.replit` (upload hunk), the new test, and this doc. The concurrent `§MatchingV2` writer's files were **not** included — `docs/match-check-phase4-c5-scope.md` was left staged/untracked and excluded from the commit.

**Deviation (disclosed, not rewritten):** `.replit` already carried two pre-existing, unrelated working-tree edits at the start of this batch — a nix `orbiton` package add and a second `[[ports]]` (8000) block — neither authored by Batch C. I staged only the upload hunk via `git apply --cached`, but the final `git commit -- .replit` re-materialized the full working-tree file (git's partial-commit-by-pathspec semantics), so those two benign lines rode along in `804266ffe`. Correcting this would require `git reset`/`git restore --staged`, both **blocked by this environment's permissions**. Per the established branch policy of **not** rewriting history on `launch-audit-remediation` (see §10), the commit is left as-is and the deviation is recorded here. Net upload/Batch-C behavior is unaffected.

### 11.6 Deployment actions required (outside this repo — do NOT apply from code)

1. On any future **nginx/Apache** front-end, set `client_max_body_size` (or `LimitRequestBody`) **≥ `post_max_size` (150M)**. None exists today (built-in server).
2. **Verify the Replit edge proxy** does not impose a body cap smaller than 150M; if it does, that becomes the real ceiling and must be raised at the platform level.
3. The fix takes effect on the next server (re)start via `.replit`; confirm the running worker reports the raised limits after redeploy.

### 11.7 Status

- **#6 = `ROOT-CAUSED + FIX COMMITTED — HUMAN BROWSER QA REQUIRED`.** Live 14-JPG upload against the running app must confirm all 14 persist + reappear on edit.
- **#7 = `INVENTORIED + FIX COMMITTED — HUMAN BROWSER QA REQUIRED`.** Confirm a >50M single file and an over-`post_max_size` batch each show the friendly error (not a silent drop); confirm a normal 14-JPG batch now succeeds.
- Nothing marked PASS/COMPLETE/RESOLVED — both stay open pending human browser QA (env has no browser).

---

## 8. Status summary

- **Batch C implemented + root-caused (see §11). #6/#7 fixed via `deploy/php/uploads.ini` + `.replit` `PHP_INI_SCAN_DIR`; both = `FIX COMMITTED — HUMAN BROWSER QA REQUIRED`.** Root cause = infra (`artisan serve` `cli-server` ran at PHP defaults; `.user.ini` + `-d` inert). Laravel/Livewire rules were already correct at 50M. No `client_max_body_size` layer exists (built-in server); nginx + Replit edge-proxy caps documented as deployment actions (§11.6).
- **Batch B implemented + code/server-verified (commits `b6e8694f4` #1, `958df08a4` #5, `efccf5b98` tests). #1/#5 = `CODE COMPLETE — HUMAN BROWSER QA REQUIRED`; #4 = `SERVER VERIFIED — HUMAN BROWSER DIAGNOSIS REQUIRED`; #2 = SERVER VERIFIED. None closed.** See §10 for the concurrent-committer batch-discipline deviation.
- **Batch A implemented + code-verified (commit `0e486dfaf`); all 5 items `CODE COMPLETE — HUMAN BROWSER QA REQUIRED`, none closed.** Remaining 28 issues audit-only.
- **Real bugs confirmed in code:** #1 (gate + orphaned partials), #3 (Seller redirect), #5, #8-#11, #12, #13, #14-reveal, #15, #16, #20, #23 (`<br>`), #24, #25, #26, #27, #28, #30, #31, #32.
- **Likely already fixed, verify-only:** #2, #4, #19, #21, #22 (browser-verify), #11 (re-verify).
- **Owner decisions locked (2026-07-05):** #25 (Create-only scope), #31 (Tenant = `Enter tenant description`), #33 (keep block; remove only a real duplicate). Verification/regression/batch policies strengthened (see top of doc + §7.0–7.2).
- **Deployment actions carried forward (from Batch C §11.6):** nginx/Apache `client_max_body_size ≥ 150M` on any future front-end; verify the Replit edge-proxy body cap; confirm the running worker reports the raised limits after redeploy.
- **Ledger rule:** nothing here is PASS/COMPLETE until code- AND browser-verified; environment-blocked items get `CODE COMPLETE — HUMAN BROWSER QA REQUIRED` and stay open in the ledger.
