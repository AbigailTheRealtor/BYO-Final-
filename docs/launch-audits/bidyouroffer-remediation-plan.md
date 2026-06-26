# BidYourOffer — Launch Remediation Plan

**Phase:** Launch Remediation Planning (no code changes yet)
**Date:** 2026-06-25
**Status:** Every Critical and High finding below was re-verified directly against source (file:line) by the lead reviewer. Items requiring a live click-through/DB query to confirm exploit/impact are explicitly marked **NEEDS-RUNTIME**.

---

## PART A — CRITICAL ISSUE VALIDATION

### C1 — Broken Access Control / IDOR on offer-listing Edit (and conditional Create) write paths

**1. Exact code responsible**
- Edit routes pass the listing id in the URL, under `auth` (authenticated *any* user, not *owner*):
  `routes/web.php:1124-1138` — `/offer-listing/{seller,landlord,buyer,tenant}/edit/{auctionId}`.
- `mount()` copies the route param straight onto the component:
  `SellerOfferListingEdit.php:1581-1589` (`$this->auctionId = $auctionId; $this->listingId = $auctionId;`). Same shape in Buyer/Landlord/Tenant edit `mount()`.
- **Load** scoping differs by role:
  - Seller/Buyer/Landlord `loadAuctionData()` **is** owner-scoped: `->where('user_id', Auth::id())` — `SellerOfferListingEdit.php:2270-2276`, `BuyerOfferListingEdit.php:1800-1803`, `LandlordOfferListingEdit.php:2323-2329`. (No read leak.)
  - Tenant `loadAuctionData()` is **NOT** scoped: `$auctionClass::findOrFail($auctionId)` — `TenantOfferListingEdit.php:2404` (also `:2287`, `:2326`). (Read leak.)
- **Write** paths are **NOT** scoped in any role — they resolve by client-controlled id:
  - Seller `SellerOfferListingEdit.php:2141` (saveDraft), `:3978` (update/submit), plus photo helpers `:3043,3076,3102,3115,3128,3138` (`findOrFail($this->listingId)`).
  - Buyer `BuyerOfferListingEdit.php:1696` (saveDraft), `:2800` (update).
  - Landlord `LandlordOfferListingEdit.php:2200` (saveDraft), `:3825` (update), photo helpers `:3978,4004,4031,4045,4059` (`findOrFail($_photoId)`).
  - Tenant `TenantOfferListingEdit.php:3123` (save), `:2287,2326` (photo/video delete).
- **Conditional Create overwrite:** when `SAVE_AS_NEW_DRAFT` is flipped to `false` (the documented pre-launch TODO at `SellerOfferListing.php:24`, `TenantOfferListing.php:31`), the resume-draft block reassigns ownership on an unscoped find: `SellerOfferListing.php:2461-2470` (`SellerAgentAuctionModel::find($this->listingId)` → `$auction->user_id = Auth::id()` → `save()`).
- **No policies exist** for these models — `app/Policies/` contains only `ShowingPolicy.php`.

**2. Why it is a bug** — Authorization must be enforced on every state-changing action against an object addressed by a client-supplied identifier. Here the id comes from the URL/Livewire payload, and the write resolves it with `Model::find($id)` and **no ownership predicate and no policy**. Authenticated-but-not-owner is treated as authorized.

**3. Real-world impact** — Any logged-in user can:
- **Overwrite / publish / unpublish another user's listing** (all four roles) by opening `/offer-listing/{role}/edit/{victimId}`, filling the form, and saving — a blind hijack/defacement of someone else's offer listing, including flipping `is_draft`, title, terms, and triggering downstream side effects (linked OfferAuction creation, Location DNA dispatch).
- **Read another user's private Tenant listing** (Tenant load unscoped) — exposes private criteria (budget, income, lease terms).
- After the pre-launch `SAVE_AS_NEW_DRAFT=false` flip, also **reassign ownership** of an arbitrary draft to the attacker.

**4. Truly a launch blocker?** **YES.** Broken access control on objects holding private financial data is disqualifying (OWASP A01). Non-negotiable.

**5. Safest fix** — Centralize an owner-scoped fetch and a policy; never trust `$this->auctionId`/`$this->listingId` for writes:
- Add `OfferListingPolicy` with `update`/`view` checking `auction.user_id === user.id` (plus the already-existing assigned-agent allowance used in `SellerOfferListingEdit::render()` `:1595-1603` — reuse that exact `$isOwner || $isAssigned` rule so behavior is consistent).
- Introduce a shared trait method, e.g. `resolveOwnedAuction(): Model` → `Model::where('id',$this->auctionId)->where('user_id',Auth::id())->firstOrFail()` (or `$this->authorize('update',$auction)` then `abort(403)`), and call it at the top of **every** write method in all four edit components and the photo/video helpers.
- Make Tenant `loadAuctionData()` owner-scoped like the other three.
- Never execute `$auction->user_id = Auth::id()` on an **existing** row; only on `new`.
- Prefer `firstOrFail()` (404) over silent `find()` so a tampered id fails closed.

**6. Every file that must change**
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` (Create resume-draft block, before flag flip)
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php`, `Landlord/LandlordOfferListing.php`, `Tenant/TenantOfferListing.php` (same resume-draft pattern)
- NEW: `app/Policies/OfferListingPolicy.php`; register in `app/Providers/AuthServiceProvider.php`
- NEW: `app/Http/Livewire/Concerns/ResolvesOwnedAuction.php` (shared trait)

**7. Related components to keep consistent (cross-role)** — Models `SellerAgentAuction`, `HireBuyerAgentAuction`, `HirelandLordAgentAuction`, `HireTenantAgentAuction`; the four Create + four Edit Livewire components; routes `web.php:1089-1145` (and the dev aliases `:1177-1290`); `AuthServiceProvider`; any controller that loads these by id for display (`StellarPropertyDetailController`, dashboard/listing-detail controllers) — audit each for the same `find($id)`-without-owner pattern; **no notification changes** needed.

**8. Regression risks** — (a) Legitimate **assigned agents** editing a client's listing must still pass — preserve the `$isAssigned` branch. (b) Draft "resume" UX: switching writes to `firstOrFail()` will 404 a genuinely-deleted draft instead of silently creating a new row — acceptable but verify the resume flow. (c) Livewire re-hydration: `auctionId` is a public property; ensure the owner check runs on **every** write entrypoint, not just mount (Livewire persists state across requests).

**9. Safest implementation order** — (1) Add policy + trait. (2) Tenant first (worst: read+write) — scope load and all writes. (3) Seller, then Landlord, then Buyer writes + photo helpers. (4) Harden the Create resume-draft block. (5) Add IDOR regression tests (owner vs non-owner vs assigned-agent) before flipping `SAVE_AS_NEW_DRAFT`.

---

### C2 — Unauthenticated IDOR + restricted-field exposure on Ask-AI endpoint

**1. Exact code responsible**
- Route is **outside** the `auth` group and has **no throttle**: `routes/web.php:262-264` (comment: "public listing question endpoint. No auth required."). Contrast `/ask-ai/ask` which has `->middleware('throttle:ask-ai-api')` at `:269`.
- Controller has **no authorization/ownership check**: `AskAiListingQuestionController::run()` `app/Http/Controllers/AskAiListingQuestionController.php:28-83`. It validates only `listing_type`/`listing_id`/`question` (`:30-35`), then calls `$this->runner->run($listingType,$listingId,...)` (`:78`). `auth()->id()` is used **only for logging** (`:51,119,182`) and is null for guests.
- Restricted-field surface exists: `SnapshotFactVisibility::RESTRICTED_KEYS` (`app/Services/AskAi/Snapshot/SnapshotFactVisibility.php:27-57`) lists flood zone, security deposit, income requirement/multiplier, HOA/CDD amounts, rent min/max, seller-financing terms. **NEEDS-RUNTIME:** the Phase-6 audit traced a fall-through where, on a knowledge-base miss, restricted-mapped fields can still reach the OpenAI adapter via `allowed_context` (`AskAiResponseContractService.php:229,307,328`; `AskAiRunnerV2Service.php` restricted guard fires only on a KB hit). The unauthenticated-IDOR portion is **CONFIRMED**; the exact restricted emission on KB-miss should be confirmed with a live probe.

**2. Why it is a bug** — A public endpoint answers natural-language questions about *any* listing addressed by an integer id, with no check that the caller may see that listing, and ids are sequential.

**3. Real-world impact** — Anonymous enumeration: `POST /ask-ai/listing-question` with `listing_type=buyer&listing_id=N` for N=1..∞ returns AI answers about offer listings the caller does not own, potentially including restricted private fields (income requirements, financing terms, deposits, flood). No rate limit at the edge; abuse/cost exposure on the OpenAI path.

**4. Truly a launch blocker?** **YES** for the unauthenticated IDOR. The restricted-leak amplifies severity (verify at runtime), but the IDOR alone blocks launch.

**5. Safest fix** — Decide the intended audience:
- If Ask-AI is meant for **public MLS (Bridge) listings only**: restrict `listing_type` to a Bridge/public allow-list (`in:bridge`) and **reject** `seller|buyer|landlord|tenant` offer-listing types on the unauthenticated path.
- If it must serve party listings: move the route **inside** `auth` and add an ownership/visibility policy check before `runner->run()`.
- Enforce visibility at **context assembly** — strip `RESTRICTED_KEYS` from `allowed_context` for unauthenticated/unqualified callers (not only at the KB-hit gate).
- Add `->middleware('throttle:ask-ai-api')` to the route for edge rate-limiting.

**6. Files that must change** — `routes/web.php` (route middleware + name), `app/Http/Controllers/AskAiListingQuestionController.php` (allow-list/authorization), `app/Services/AskAi/AskAiResponseContractService.php` and/or `AskAiRunnerV2Service.php` (context-assembly visibility), validation rule for `listing_type`. (This fix interlocks with C3 — fix the field contract and the auth model together.)

**7. Related components** — `AskAiRunnerV2Service`, `AskAiRateLimitService`, `AskAiUsageLoggerService`, `SnapshotFactVisibility`, the `matchmaker-ask-ai.blade.php` widget (C3), and the named throttle limiter definition (`app/Providers/RouteServiceProvider.php` or wherever `ask-ai-api` is registered). No model/migration change.

**8. Regression risks** — Tightening `listing_type` could break any existing caller relying on the broad string (check `/ask-ai/test` admin tooling and the canonical `/ask-ai/ask` route which shares the runner). Moving to `auth` would break the public Stellar widget unless that page is itself authenticated (it is — `web.php:160` group), so verify the widget is only rendered for logged-in users.

**9. Safest implementation order** — (1) Add allow-list + authorization to the controller (fail closed). (2) Add edge throttle. (3) Add context-assembly visibility stripping. (4) Runtime-probe the KB-miss path with a restricted question. (5) Fix the widget field contract (C3) so the legitimate path works.

---

### C3 — Ask-AI widget is non-functional (Blade ↔ controller field-contract mismatch)

**1. Exact code responsible**
- Form posts `listing_key` (+ optional `criteria_id`/`criteria_type`) and `question`: `resources/views/components/stellar/matchmaker-ask-ai.blade.php:25-32`. It **never** sends `listing_type` or `listing_id`.
- Component is invoked with only `listing-key`: `resources/views/stellar/property/detail.blade.php:192-196`.
- Controller **requires** `listing_type` (string) **and** `listing_id` (**integer**): `AskAiListingQuestionController.php:30-35`.
- JS renders `json.message` on failure: `matchmaker-ask-ai.blade.php:83-84` (`json.answer || json.response || json.message`).

**2. Why it is a bug** — The client/server field contract does not match; every submission fails `validate()` with HTTP 422 before reaching the runner, and the validation message is shown to the user as if it were an AI answer.

**3. Real-world impact** — The flagship "Ask AI" feature on every Stellar property page is 100% broken; users get Laravel's "The given data was invalid." styled as an AI response. Total feature failure + unprofessional UX.

**4. Truly a launch blocker?** **YES** if Ask-AI is in launch scope (it is rendered on the primary property detail page). The feature does not work at all.

**5. Safest fix** — Make the component emit the contract the runner keys on: `listing_type` (the Bridge token) + numeric `listing_id` (= `BridgeProperty.id`, already in controller scope at `StellarPropertyDetailController.php:114`). Pass both into the component from the detail view and post them. Confirm the runner accepts the Bridge listing-type token. Make the JS fail safe on non-2xx (`if(!r.ok)` → show error box, never render `message` as an answer). **Coordinate with C2** so the now-working request is also authorized/throttled.

**6. Files that must change** — `resources/views/components/stellar/matchmaker-ask-ai.blade.php`, `resources/views/stellar/property/detail.blade.php`, `app/Http/Controllers/Stellar/StellarPropertyDetailController.php` (pass `listing_type`+`listing_id` to the component), possibly `AskAiListingQuestionController.php`/runner if the Bridge token needs mapping.

**7. Related components** — `AskAiRunnerV2Service` (must support the chosen `listing_type` for Bridge listings); `PropertyMatchContextService` (already resolves `criteria_id`/type for the page). No model change.

**8. Regression risks** — If any other page already posts to `/ask-ai/listing-question` with a *different* working contract, changing the controller could break it — grep confirms only this widget posts to it, so risk is low. Ensure CSRF token continues to flow (the JS reads `data.get('_token')`).

**9. Safest implementation order** — (1) Decide the canonical `(listing_type, listing_id)` for Bridge listings. (2) Update the controller/runner to accept it (fail closed for others — C2). (3) Update the component + detail view to post it. (4) Fail-safe the JS. (5) End-to-end test a real question.

---

### C4 — Seller Create silently drops Broker-Compensation + ~47 financing/leasing fields

**1. Exact code responsible**
- 47 `saveMeta` keys are persisted in **Edit** but absent from **Create** (verified by set-difference of `saveMeta('…')` keys between `SellerOfferListingEdit.php` and `SellerOfferListing.php`), including the entire buyer-broker `commission_structure_type` family, the `seller_leasing_*` fee family, `lease_option/lease_purchase` extension/credit/maintenance fields, `number_of_units`, `other_business_type`, NFT fields, `lender_approval_required`, etc.
- `commission_structure_type` (+ fee fields) is **validated `required` on Create** when buyer-broker compensation applies: `SellerOfferListing.php:4279-4288`; with messages at `:4325-4328`.
- It is **not** saved by Create `saveAllMetadata()` (`SellerOfferListing.php:3165`+ — grep for `saveMeta('commission_structure_type'` returns **none**) and **not** in `buildDraftPayload()` (`:1970`-`:2449`).
- It **is** saved on Edit: `SellerOfferListingEdit.php:3658-3663`, and loaded on Edit: `:2998-3003`.

**2. Why it is a bug** — Fields the user is forced to fill (validation `required`) are never written to the data store on the Create path; the same fields are correctly written on the Edit path, so the contract is internally inconsistent.

**3. Real-world impact** — A seller completes the Create wizard, passes validation, submits — and the buyer-broker commission terms, seller-leasing fees, and lease-option terms are **silently lost**. They appear blank on the next edit and are absent from any downstream consumer (match scoring, accepted-bid summary, PDF). These are legally/financially load-bearing terms; silent loss is a correctness and potential liability issue.

**4. Truly a launch blocker?** **YES.** Silent loss of compensation/financing data on the primary creation path.

**5. Safest fix** — Mirror Edit's persistence in Create: add the 47 missing `saveMeta` calls to Create `saveAllMetadata()` and add the same keys to `buildDraftPayload()` (so drafts persist them and the change-detection hash includes them — also fixes M11). Best done by extracting a single shared "persist metadata" method used by both Create and Edit to prevent future drift.

**6. Files that must change** — `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` (`saveAllMetadata`, `buildDraftPayload`). Recommended: factor a shared concern used by both Seller Create and Edit.

**7. Related components (cross-role parity)** — Re-run the same `saveMeta` create-vs-edit key diff for **Buyer** (Phase-1 flagged `other_business_type` create-loss — `BuyerOfferListing` saveAllMetadata), **Landlord**, and **Tenant** and close any analogous gaps. The model layer (`SellerAgentAuction` meta) and the view mappers that read these keys (`PropertyDetailViewMapper`, accepted-bid summary, match scoring) need no change once the data is written. Verify `AcceptedBidSummaryService` placeholder set still resolves.

**8. Regression risks** — Adding writes is low-risk, but (a) ensure conditional fields are cleared when not applicable (the existing `sanitizeBeforeSubmit`/reset logic at `:4097`+ must run so stale values aren't persisted); (b) including new keys in `buildDraftPayloadHash` changes the hash — first save after deploy may register as "changed" (benign).

**9. Safest implementation order** — (1) Extract shared persist method from Edit. (2) Wire it into Create `saveAllMetadata`. (3) Add keys to `buildDraftPayload`. (4) Round-trip test: create → submit → edit shows all fields. (5) Repeat the diff for Buyer/Landlord/Tenant.

---

## PART B — HIGH ISSUE VALIDATION (condensed)

### H1 — Edit submit validation far weaker than Create (all roles)
- **Code:** Seller Edit `update()` validates only `['unit_address' => 'nullable|string|max:100']` — `SellerOfferListingEdit.php:3970-3973`. Create runs full conditional rules (`SellerOfferListing.php:4255-4305`+). Tenant Edit validates ~8 fields (`TenantOfferListingEdit.php:3082-3102`) vs Create `validateOnlyFilledFields()`; Buyer/Landlord similar.
- **Bug/impact:** A listing can be **edited and re-published with required fields blank/invalid**, bypassing Create-time integrity. **Blocker:** strongly recommended pre-GA (not a security hole, but a data-integrity hole on the publish path). **Fix:** extract Create's `getConditionalRules()`/sanitize into a shared concern; call from both `store()` and `update()` (non-draft path only — keep partial validation for Save Draft). **Files:** four Edit + four Create components + new shared concern. **Regression:** ensure draft path keeps lenient validation; ensure `initializeLimitedService()` (frozen) is untouched.

### H2 — Landlord & Seller waterfront fields error / lose data in Edit
- **Code:** Shared partials bind `wire:model="water_frontage"`/`"waterfront_feet"` — `offer-landlord-tabs/commission-based/property-preferences.blade.php:732,746` and `offer-seller-tabs/commission-based/property-preferences.blade.php:1175,1189`. Create declares+saves+loads them (Landlord `:692-693,3668-3669,2967-2968`; Seller `:626-627,3680-3681,3088-3089`). Edit components have **zero** references (`grep` count 0 in both `LandlordOfferListingEdit.php` and `SellerOfferListingEdit.php`).
- **Bug/impact:** In Edit, binding `wire:model` to an undeclared public property throws a Livewire `PublicPropertyNotFoundException` on interaction, and the value can never be loaded/saved (frozen/blank). **Blocker:** YES for the edit flow when the waterfront section is reachable. **Fix:** add the two public props + save + load to Landlord and Seller Edit (mirror Create). **Files:** `LandlordOfferListingEdit.php`, `SellerOfferListingEdit.php`. **Regression:** none beyond the two fields; confirm Buyer/Tenant don't share the same partial gap.

### H3 — Buyer Edit may drop multiselect edits on Submit — **NEEDS-RUNTIME**
- **Code:** Create has `updated*Json` reconcile hooks (`BuyerOfferListing.php:2349-2421`); Edit `saveAllMetadata` lacks them. **Impact:** post-load changes to `property_items`/`condition_prop_buyer`/`number_of_unit_type` may save the stale array. **Blocker:** YES if confirmed. **Fix:** port the hooks or reconcile `_json→array` in Edit. **Verify** by editing a multiselect then submitting and checking DB. **Files:** `BuyerOfferListingEdit.php`.

### H4 — Tenant matching cannot distinguish rentals from sales
- **Code:** `TenantOfferListingCriteriaLoader.php:122-131` maps any non-commercial `property_type` → `'Residential'`; comment `:170-176` deliberately nulls `max_price` because Bridge `'Residential'` rows are **sale** listings with `list_price`. Commercial Lease is handled correctly (`:124-129`).
- **Bug/impact:** Residential **tenant** searches match against **for-sale** inventory with no rent ceiling → wrong results, meaningless scores. **Blocker:** YES *if Tenant is in launch scope* (Tenant has no public results route yet — confirm scope). **Fix:** use the correct Bridge rental PropertyType (e.g. `Residential Lease`) or a lease/transaction-type filter; apply a rent ceiling once the rental feed is correct. **Files:** `TenantOfferListingCriteriaLoader.php`, `TenantCriteriaODataFilterBuilder.php`, `BuyerMatchQueryBuilder.php`. **Regression:** verify Commercial Lease path unaffected.

### H5 — One-sided city normalization may zero out city matches — **NEEDS-RUNTIME**
- **Code:** `BuyerOfferListingCriteriaLoader.php:301-306` expands client `St.→Saint`; `BridgePropertyNormalizer.php:45` stores Bridge city raw; exact `in_array` at `BuyerMatchScorer.php:154`. **Impact:** abbreviated cities may never match (0 candidates/0 city points) for city-only searches. **Fix:** normalize both sides or add a city-alias map. **Verify:** `SELECT DISTINCT city FROM bridge_properties WHERE city ILIKE 'st%'`. **Files:** `BuyerOfferListingCriteriaLoader.php`, `BridgePropertyNormalizer.php`.

### H6 — Location DNA geocode HTTP call has no timeout
- **Code:** `LocationDnaGeocodeService.php:200-205` — request passes only `'query'`, **no `'timeout'`** (Guzzle default = no timeout). Contrast POI adapter which sets `'timeout' => $timeout` (`GooglePlacesPoiAdapter.php:75-78`). **Impact:** a hung geocode (pipeline stage 1) stalls the queue worker up to job `timeout`. **Blocker:** pre-GA (operational). **Fix:** add `'timeout' => config('location_dna.http_timeout', 10)`. **Files:** `LocationDnaGeocodeService.php`. **Regression:** none.

### H7 — Google POI failures are silent (status ignored, no logging)
- **Code:** `GooglePlacesPoiAdapter.php:82-84` checks only `empty($body['results'])` (ignores `$body['status']` REQUEST_DENIED/OVER_QUERY_LIMIT); `:106-108` `catch (Throwable) { return []; }` with no `Log`. **Impact:** total POI loss is indistinguishable from "no POIs nearby"; key/quota failures invisible. **Blocker:** pre-GA (observability). **Fix:** inspect `$body['status']`, `Log::warning` on non-OK and on exception. **Files:** `GooglePlacesPoiAdapter.php`. **Regression:** none (logging only).

### H8 — Transient POI outage cached as empty success for 24h (cache poisoning)
- **Code:** `PoiDistanceLookupService.php:110-119` caches the adapter result with `cache_ttl` (86400s); since the adapter returns `[]` on error (H7), an outage is cached as a valid empty result. (Confirmed by Phase-4 audit; logically follows from H7.) **Impact:** a blip blanks POIs for 24h. **Fix:** only cache on genuine success (FEMA/Census already do this). **Files:** `PoiDistanceLookupService.php`, depends on H7. **Regression:** more cache misses during outages (acceptable).

### H9 — Admin AI Marketing Report: no grounding/system prompt (NOT a blocker, gated)
- **Code:** `OpenAiClientService.php:276-285` (single `role:user` message of `json_encode($payload)`, no system prompt, temperature unset); `AiMarketingReportGeneratorService.php:142-148,280-301` (attribution only checked non-empty). **Impact:** ungrounded output possible, but written `pending_review`, HTML-escaped, admin/agent-only, behind a mandatory human-approval gate. **Blocker:** NO — *conditional on the gate staying hard*. **Fix (post-launch):** add anti-hallucination system prompt, low temperature, `max_tokens`, queue the call, validate attribution keys against real fields. **Files:** `OpenAiClientService.php`, `AiMarketingReportGeneratorService.php`. **Critical caveat:** if the human gate is ever bypassed/auto-published, H9 becomes a launch blocker.

---

## PART C — LAUNCH REMEDIATION PLAN (phased)

### Phase 1 — Critical Security Fixes
- **Goal:** Eliminate all broken-access-control. Close C1 (offer-listing IDOR, all roles + create resume-draft) and C2 (Ask-AI unauth IDOR + restricted-field exposure + edge throttle).
- **Files:** four `*OfferListingEdit.php`, four `*OfferListing.php` (resume-draft block), new `OfferListingPolicy` + `AuthServiceProvider`, new `ResolvesOwnedAuction` trait; `routes/web.php`, `AskAiListingQuestionController.php`, `AskAiResponseContractService.php`/`AskAiRunnerV2Service.php`.
- **Effort:** ~2–3 days (C1 one shared pattern ×4 roles + tests; C2 ~1 day).
- **Regression risk:** Medium — must preserve assigned-agent edit access; verify Livewire write entrypoints all gated; verify Ask-AI allow-list doesn't break `/ask-ai/ask` or admin test tooling.
- **Testing checklist:** owner can edit; non-owner gets 403/404 on edit + saveDraft + submit + photo/video delete (all four roles); Tenant non-owner cannot read; assigned agent can still edit; anonymous Ask-AI on a party listing is rejected; restricted question returns no restricted field (runtime probe); throttle enforced; IDOR feature tests added and green.
- **Expected outcome:** No object-level authorization gaps remain; Ask-AI cannot be queried for non-public/unowned listings.

### Phase 2 — Data Integrity Fixes
- **Goal:** Stop silent data loss. Close C4 (Seller 47-field create-persistence) and the cross-role analogues (Buyer `other_business_type`, any Landlord/Tenant gaps); fix C3 widget contract so Ask-AI actually works.
- **Files:** `SellerOfferListing.php` (+shared persist concern), `BuyerOfferListing.php`, `LandlordOfferListing.php`, `TenantOfferListing.php`; `matchmaker-ask-ai.blade.php`, `stellar/property/detail.blade.php`, `StellarPropertyDetailController.php`.
- **Effort:** ~2 days.
- **Regression risk:** Medium — conditional-field sanitize must run before persist; draft-hash changes are benign; confirm no other caller of the Ask-AI endpoint.
- **Testing checklist:** create→submit→edit round-trip shows every broker/financing/leasing field (all roles); `saveMeta` create-vs-edit key diff is empty for all four roles; draft save preserves the same fields; Ask-AI returns a real grounded answer end-to-end.
- **Expected outcome:** Create and Edit persist identical field sets; Ask-AI functions.

### Phase 3 — Validation & Create/Edit Parity
- **Goal:** Close H1 (Edit validation parity) and H2 (waterfront Edit props) and H3 (Buyer multiselect submit) so Edit enforces the same integrity as Create.
- **Files:** four `*OfferListingEdit.php`, four `*OfferListing.php`, shared validation concern; `LandlordOfferListingEdit.php`/`SellerOfferListingEdit.php` (waterfront props); `BuyerOfferListingEdit.php` (multiselect hooks).
- **Effort:** ~2–3 days.
- **Regression risk:** Medium — keep Save-Draft partial validation; do not touch frozen `initializeLimitedService()`; verify conditional rules fire per property type.
- **Testing checklist:** Edit submit rejects blank required fields per property type (all roles); waterfront field edits without Livewire error and round-trips; Buyer multiselect edit persists on submit (runtime); no hidden/conditional field is validated when not applicable.
- **Expected outcome:** Edit ≡ Create validation; no Livewire property errors; multiselect parity.

### Phase 4 — UX & Consistency
- **Goal:** Remove misleading UI and surface stranded data. Close M4 (always-on placeholder cards), M5 (flood-zone surfacing — liability-sensitive), M7 (vacant-land mislabel), M2/M1/M3 (match "why"/negative-amenity/score-ceiling), M15 (JS fail-safe), M6/L4 (non-residential display, lot-size fallback).
- **Files:** `resources/views/components/stellar/matchmaker-{commute,appreciation,flood-zone}.blade.php`, `LocationDnaSummaryService.php`, `PropertyPersonalityService.php`, `BuyerMatchScorer.php`, `PropertyDetailViewMapper.php`, `property-header.blade.php`/`property-key-facts.blade.php`.
- **Effort:** ~3–4 days.
- **Regression risk:** Low–Medium — scoring changes must be re-baselined; hiding cards must not break layout.
- **Testing checklist:** no permanent "coming soon" card ships visible without data; flood status renders real FEMA data or is intentionally hidden; vacant land shows correct (or suppressed) personality; match reasons only cite expressed criteria; residential score scale documented/consistent; non-residential detail pages render meaningful fields.
- **Expected outcome:** No misleading/empty UI; consumer-facing claims are grounded.

### Phase 5 — Performance & Technical Debt
- **Goal:** Operational robustness. Close H6 (geocode timeout), H7 (POI logging), H8 (no error-caching), M9 (concurrency guard), M16/M17/M18/M20 (retries, tile cache, POI dedup, school layers), M10 (queue admin OpenAI + max_tokens), per-request Bridge import → background; plus L1/L2/L5/L8/L10 cleanups and stale-doc fixes; harden H9 (system prompt) without removing the gate.
- **Files:** `LocationDnaGeocodeService.php`, `GooglePlacesPoiAdapter.php`, `PoiDistanceLookupService.php`, `ComputeLocationDna.php`, `LocationDnaPoiTileCache.php`, `CensusSchoolDistrictAdapter.php`, `OpenAiClientService.php`, `AiMarketingReportGeneratorService.php`, `BuyerMatchService.php`, the four create components (L1 dup key, L2 launch flags), CLAUDE.md.
- **Effort:** ~3–5 days.
- **Regression risk:** Low–Medium — caching/concurrency changes need load/queue testing.
- **Testing checklist:** geocode times out gracefully; POI failures logged + not cached; concurrent `ComputeLocationDna` runs don't violate the unique index; admin OpenAI runs queued with `max_tokens`; `SAVE_AS_NEW_DRAFT` set to intended production value (after C1 tests); duplicate array key removed.
- **Expected outcome:** No silent external-call failures, no unbounded cost, no concurrency exceptions, clean technical debt.

### Phase 6 — Final Browser Verification
- **Goal:** Exercise the live app to confirm the static fixes hold end-to-end and cover the NOT-TESTED gaps from the certification (console/500/404, per-role × per-property-type walkthrough, Maps-key restriction, city-normalization runtime check).
- **Files:** none (verification); add Feature/E2E tests where automatable.
- **Effort:** ~2 days.
- **Regression risk:** N/A (verification).
- **Testing checklist:** Login → for each role × each property type: create → save draft → edit draft → submit → view detail → matches → Ask AI; IDOR attempts blocked; no console errors / 500 / 404; Location DNA + Property DNA render correctly per property type; Maps key referrer-restricted; `SELECT DISTINCT city` confirms normalization.
- **Expected outcome:** A clean, evidence-backed go/no-go with zero unresolved Critical issues.

---

## Recommended global execution order
Phase 1 → Phase 2 → Phase 3 → (Phase 4 ∥ Phase 5 can overlap) → Phase 6. Implement one phase at a time, re-test, and verify before advancing. Do **not** flip `SAVE_AS_NEW_DRAFT` to `false` until Phase 1 IDOR tests are green.
