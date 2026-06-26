# Launch Audits — Final Commit Plan

**Date:** 2026-06-26
**Companion to:** `completed-work-inventory-and-remaining-work.md`
**State:** branch `main`, last commit `b9416e999`. 46 modified, 32 untracked, 0 staged (+1,809 / −613). **All work uncommitted.**
**Goal:** Land the uncommitted work as a sequence of small, reviewable, logically-isolated commits so nothing is lost or duplicated — *before* any new remediation.

> This is a **plan only**. No commits are executed by this document. Review §0 housekeeping first; the one manual step is splitting `routes/web.php` by hunk (it is shared by three security commits).

---

## 0. Pre-commit housekeeping (do first)

1. **`scratch/`** — working diffs (`ll_create.txt`, `t_edit.txt`, …). Do **not** commit. Add to `.gitignore`:
   ```
   echo "scratch/" >> .gitignore
   ```
2. **`.claude/settings.local.json`** — flagged in the BidYourOffer cert as **rewritten by an out-of-scope audit agent**. Inspect and revert if unwanted:
   ```
   git diff .claude/settings.local.json
   # if the rewrite is unwanted:
   git checkout -- .claude/settings.local.json
   ```
3. **`.replit`** — environment/run config. Inspect; commit separately only if the change is intentional (`git diff .replit`).
4. **`routes/web.php` is shared** by commits 4 (BYO C1), 5 (BYO C2), 6 (BYA P1). Each of those commits stages **only its own hunks** with `git add -p routes/web.php`. Markers to recognize hunks: `offer-listing` routes → C1; `ask-ai/listing-question` → C2; `renew_save`/`endAuction`/`counter`/dead-landlord → BYA P1.

---

## 1. Commit order & dependency rationale

Docs and the standalone feature first (no dependency), then the self-contained Location DNA fixes, then the four security workstreams (each isolated; routes split by hunk). Security last so the route-file splits happen against an otherwise-clean tree.

| # | Commit | Workstream | Risk | Depends on |
|---|---|---|---|---|
| 1 | Documentation | G | none | — |
| 2 | Stellar consumer UI feature | F | low | — |
| 3 | Location DNA Phase 1 fixes | E | low | — |
| 4 | BidYourOffer C1 — offer-listing IDOR | C | med | — |
| 5 | BidYourOffer C2/C3 — Ask-AI auth | D | med | 2 (widget), 4 (shared trait/idiom) |
| 6 | BidYourAgent Phase 1 — auth | A | med | — |
| 7 | BidYourAgent HIGH-5 — CounteredTerms | B | low | 6 |

---

## 2. The commits

### Commit 1 — docs: launch-audit reports + inventory + commit plan
```
git add docs/launch-audits/ docs/CENSUS_INTELLIGENCE_PHASE_5_3_GOVERNANCE_AND_ARCHITECTURE_PLAN.md
git commit
```
**Message:**
```
docs: launch-audit certifications, remediation plans, and work inventory

Adds the BidYourAgent / BidYourOffer launch certifications, Location DNA
audit + architecture review, Census Intelligence Phase 5.3 governance plan,
the completed-work inventory, and this commit plan.
```

### Commit 2 — feat(stellar): consumer property-detail + matchmaker UI
New: 14 `matchmaker-*` + 12 `property-*` components, `app/Services/Stellar/PropertyMatchContextService.php`.
Modified: `StellarPropertyDetailController.php`, `BuyerResultViewMapper.php`, `PropertyDetailViewMapper.php`, `buyer-result-card.blade.php`, `stellar/property/detail.blade.php`.
```
git add resources/views/components/stellar/ resources/views/stellar/property/detail.blade.php \
        app/Services/Stellar/PropertyMatchContextService.php \
        app/Http/Controllers/Stellar/StellarPropertyDetailController.php \
        app/Services/Stellar/BuyerResultViewMapper.php \
        app/Services/Stellar/PropertyDetailViewMapper.php
git commit
```
**Note:** the new `matchmaker-ask-ai.blade.php` already posts the correct `listing_type`/`listing_id` contract — it materially realizes the **C3** fix (the auth/route half lands in commit 5).

### Commit 3 — fix(location-dna): Phase 1 high-value bug fixes
```
git add app/Services/LocationDna/LocationDnaPipelineRunner.php \
        app/Services/LocationDna/LocationDnaPoiDistanceService.php \
        app/Services/LocationDna/LocationDnaPoiTileCache.php \
        app/Services/AgentAi/Loaders/ExtendedKnowledgeLoader.php \
        app/Services/AskAi/AskAiContextBuilderService.php \
        config/location_dna.php \
        app/Console/Commands/LdnaBenchmarkTilePrecision.php \
        tests/Unit/Services/LocationDna/CategoryExclusionRulesRegressionTest.php \
        tests/Unit/Services/LocationDna/LocationDnaPoiTileCacheTest.php \
        tests/Unit/AgentAi/ExtendedKnowledgeLoaderTest.php \
        tests/Unit/Services/AskAi/AskAiContextBuilderServiceTest.php \
        tests/Feature/LocationDnaPipelineTriggerTest.php
git commit
```
**Message:**
```
fix(location-dna): Phase 1 bug fixes — bridge LDNA, agent-AI status,
Ask-AI lifestyle keys, tile-cache store, marina/hospital exclusions

- bridge listing type now geocodes (resolveAddressData branch)
- ExtendedKnowledgeLoader geocode_status 'success' -> 'geocoded'
- AskAiContextBuilder reads lifestyle_categories/location_narrative
- tile cache off the per-process array store (Cache::store)
- marina/boat_ramp boat-dealer + animal-hospital-in-hospital exclusions
```

### Commit 4 — fix(security): BidYourOffer C1 — offer-listing IDOR (all 4 roles)
```
git add app/Http/Livewire/Concerns/ResolvesOwnedAuction.php \
        app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php \
        app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php \
        app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php \
        app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php \
        app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php \
        app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php \
        app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php \
        app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php \
        tests/Feature/Offers/OfferListingAuthorizationTest.php
git add -p routes/web.php      # stage ONLY offer-listing edit/create route hunks
git commit
```
**Message:**
```
fix(security): owner-scope offer-listing edit/create write+load paths (C1)

Adds ResolvesOwnedAuction concern; every write resolves the record by
(id, user_id=Auth::id()) firstOrFail across all four roles. Closes the
IDOR allowing any authenticated user to read/overwrite another user's
offer listing. Implemented via shared trait (no OfferListingPolicy).
```

### Commit 5 — fix(security): BidYourOffer C2/C3 — Ask-AI auth + owner scope
```
git add app/Http/Controllers/AskAiListingQuestionController.php \
        tests/Feature/AskAiListingQuestionTest.php \
        tests/Feature/AskAiCostTrackingTest.php \
        tests/Feature/AskAiRateLimitLoggingTest.php \
        tests/Feature/AskAiRateLimiterTest.php \
        tests/Feature/AskAiUsageLoggingTest.php
git add -p routes/web.php      # stage ONLY the ask-ai/listing-question route hunk
git commit
```
**Message:**
```
fix(security): authenticate + owner-scope Ask-AI listing-question (C2/C3)

Route moved behind ['auth','throttle:ask-ai-api']; controller enforces
ownsListing(Auth::id(), ...). Engine now serves only the requester's own
offer-listing/criteria (paired with the reworked matchmaker-ask-ai widget
in the Stellar commit). Remaining: KB-miss RESTRICTED-key strip (runtime).
```

### Commit 6 — fix(security): BidYourAgent Phase 1 — authorization
```
git add app/Http/Middleware/AgentAuth.php \
        app/Http/Livewire/TenantAgentAuctionEdit.php \
        app/Http/Controllers/PropertyAuctionController.php \
        app/Http/Controllers/LandlordAgentAuctionController.php \
        app/Http/Controllers/SellerCounterBidController.php \
        app/Http/Controllers/BuyerCriteriaAuctionBidController.php \
        app/Http/Controllers/TenantCriteriaAuctionController.php \
        tests/Feature/Security/Phase1AuthorizationTest.php
git add -p routes/web.php      # stage remaining endAuction/renew/counter/dead-landlord hunks
git commit
```
**Message:**
```
fix(security): BidYourAgent Phase 1 authorization (CRIT-1/2/5, HIGH-1/7)

- listing-edit owner scoping (CRIT-1)
- auth + owner guard on endAuction (CRIT-2) and renew_save (HIGH-7)
- party guard on destroyCounter (CRIT-5)
- AgentAuth allowlists user_type==='agent' (HIGH-1)
- remaining sensitive routes wrapped in auth; dead CRIT-3/4 paths gated
```

### Commit 7 — fix(security): BidYourAgent HIGH-5 — CounteredTerms IDOR
```
git add app/Http/Controllers/CounteredTerms.php \
        app/Http/Controllers/SellerCounteredTermsController.php \
        app/Http/Controllers/BuyerCounteredTermsController.php \
        app/Http/Controllers/LandlordCounteredTermsController.php \
        app/Http/Controllers/TenantCounteredTermsController.php \
        tests/Feature/Security/CounteredTermsAuthorizationTest.php
git commit
```
**Message:**
```
fix(security): party-guard legacy CounteredTerms controllers (HIGH-5)

Adds the proven add()-style party check to store/update across all
five legacy countered-terms controllers. Hardened (not retired) per
the smallest-safe, rule-aligned recommendation.
```

---

## 3. After committing

- Run the suites that can run here: `php artisan test tests/Feature/Security tests/Feature/Offers tests/Unit/Services/LocationDna tests/Unit/Services/AskAi` — expect the 3 known live-pgsql ownership skips.
- Do **not** push or open a PR unless asked.
- Leave `scratch/` and (if reverted) `.claude/settings.local.json` out of history.
- Each commit message gets the standard trailer:
  ```
  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  ```

## 4. Open organizational decisions (surface before executing)

1. **Branch vs. `main`:** all seven commits currently land on `main`. If a PR is wanted, branch first (`git switch -c launch-audit-remediation`).
2. **Squash vs. granular:** plan is 7 granular commits for reviewability. Could squash the four security commits into one "Phase 1 security" commit if preferred.
3. **`routes/web.php` hunk-splitting** is the only fiddly step — acceptable to instead make a single combined "routing & auth wiring" commit if hunk-splitting proves error-prone.
4. **`.replit` / `.claude/settings.local.json`** — confirm intent before either is committed.
