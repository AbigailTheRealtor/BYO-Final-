# Ask AI Phase 5: Coverage & Optimization Audit

**Date:** June 12, 2026  
**Scope:** All four listing roles — Seller, Buyer, Landlord, Tenant  
**Phase:** Phase 5 — OpenAI Optimization & Coverage Expansion

---

## Section 1: Snapshot Generation Audit (Lifecycle Trigger Matrix)

### 1.1 Overview

The `AskAiKnowledgeSnapshotBuilderService::buildSilently()` method is the canonical trigger for snapshot creation. It is idempotent (each call creates a new incremented version), exception-safe (never interrupts a listing save), and concurrency-protected via a unique index on `(listing_type, listing_id, version)`.

### 1.2 Complete Trigger Matrix

| Event | Component / Controller | Role(s) | Method | Phase 5 Status |
|---|---|---|---|---|
| Listing create (draft) | `SellerOfferListing` (Livewire) | seller | `saveDraft()` / `submitListing()` | ✅ Pre-existing |
| Listing update (edit save) | `SellerOfferListingEdit` (Livewire) | seller | `saveDraft()` / `saveEdit()` / `submitListing()` | ✅ Pre-existing |
| Listing create (draft) | `BuyerOfferListing` (Livewire) | buyer | `saveDraft()` / `submitListing()` | ✅ Pre-existing |
| Listing update (edit save) | `BuyerOfferListingEdit` (Livewire) | buyer | `saveDraft()` / `saveEdit()` | ✅ Pre-existing |
| Listing create (draft) | `LandlordOfferListing` (Livewire) | landlord | `saveDraft()` / `submitListing()` | ✅ Pre-existing |
| Listing update (edit save) | `LandlordOfferListingEdit` (Livewire) | landlord | `saveDraft()` / `saveEdit()` / `submitListing()` | ✅ Pre-existing |
| Listing create (draft) | `TenantOfferListing` (Livewire) | tenant | `saveDraft()` / `submitListing()` | ✅ Pre-existing |
| Listing update (edit save) | `TenantOfferListingEdit` (Livewire) | tenant | `saveDraft()` / `saveEdit()` / `submitListing()` | ✅ Pre-existing |
| **Admin approval** | `SellerAgentAuctionController::approveSellerAgentAuction()` | seller | `buildSilently('seller', $id)` | 🔧 **Patched in Phase 5** |
| **Admin approval** | `BuyerAgentAuctionController::approveBuyerAgentAuction()` | buyer | `buildSilently('buyer', $id)` | 🔧 **Patched in Phase 5** |
| **Admin approval** | `LandlordAgentAuctionController::approve()` | landlord | `buildSilently('landlord', $id)` | 🔧 **Patched in Phase 5** |
| **Admin approval** | `TenantAgentAuctionController::approve()` | tenant | `buildSilently('tenant', $id)` | 🔧 **Patched in Phase 5** |

### 1.3 Non-Livewire / Admin-Side Paths — Exhaustive Audit

All admin-side controller files were reviewed for listing state changes that could affect the four agent-auction listing types. The full list of admin controllers inspected:

`ACTypeController`, `AdminAgentController`, `AgentServiceController`, `AiMarketingReportAdminController`, `AiMarketingReportPublicationController`, `ApplianceController`, `AskAiAdminTestController`, `AskAiAnalyticsController`, `BuyerController`, `ByaPreviewController`, `ByaReviewController`, `CityController`, `CountyController`, `DnaInspectorController`, `DnaProfileController`, `FeeIncludeController`, `FinancingController`, `HeatingFuelController`, `NotificationController`, `PropertyTypeController`, `SellerController`, `SellerServiceController`, `SettingController`, `WaterExtraController`, `WaterViewTypeController`

**Findings:**
- `AdminAgentController`, `BuyerController`, `SellerController` — these set `users.is_approved` (user approval), **not** listing state. Not relevant.
- `ByaReviewController` — manages DNA report review status only. No listing state change.
- `DnaProfileController` — reads listing data to build DNA profiles. No listing writes.
- All other admin controllers manage lookup tables (heating fuel, property type, etc.). No listing state changes.

**Republish / restore-from-draft paths:** No such admin-side routes or methods exist in this codebase for the four agent-auction listing types. Listings transition between states (`is_draft`, `is_approved`) exclusively via the Livewire create/edit components (user-initiated) and the four approval methods patched in Phase 5 (admin-initiated). There is no separate "republish" or "restore from draft" admin action.

### 1.4 Events Confirmed as Out-of-Scope (No Snapshot Needed)

| Event | Reason |
|---|---|
| `AdminController::approvePropertyAuction()` | Uses `PropertyAuction` model — not an Ask AI listing type (legacy buyer-side auction, no context builder support) |
| `AdminController::approveOfferListing()` | Uses `OfferAuction` model — this is a separate model for the non-agent offer flow; the Ask AI context builder only covers the four agent-auction roles |
| `ByaReviewController` (BYA review logs) | Not a listing state change — manages DNA report review status only |
| Bid accepted / rejected | Bid state changes do not modify listing content; snapshot data remains valid |
| `AdminAgentController`, `BuyerController`, `SellerController` | Operate on `users.is_approved` (user approval), not listing state |
| `DnaProfileController`, `DnaInspectorController` | Read listing data for DNA profiles; no listing writes |

### 1.5 Identified and Resolved Gaps

**Gap:** Prior to Phase 5, admin approval (`is_approved = true`) of all four agent-auction listing types did not trigger a snapshot rebuild. This meant that a listing could be modified (or auto-filled) by admin review workflows and go live without an up-to-date snapshot.

**Remediation:** `buildSilently()` added to all four approval methods. The call is placed _after_ the `update()` call and before the redirect, so it operates on the final approved state. All exceptions are silently caught — the admin approval is never blocked.

---

## Section 2: Snapshot Coverage Audit

### 2.1 Methodology

For each role, snapshot builders (`SellerSnapshotBuilder`, `BuyerSnapshotBuilder`, `LandlordSnapshotBuilder`, `TenantSnapshotBuilder`) call:
1. `AskAiContextBuilderService::buildForListing(role, id)` → produces `context['listing']` (facts) and `context['faq_answers']` (answers).
2. `AskAiFieldQuestionRegistryService::registry()` + `listingFieldRegistry()` → produces questions.
3. All three are persisted to `ask_ai_facts`, `ask_ai_questions`, `ask_ai_answers`.

The four snapshot builders are structurally identical — they share the same `persistFacts`, `persistQuestions`, `persistAnswers` pattern. Role-scoping is enforced by registry `roles` arrays.

### 2.2 Registry Field Counts by Role (as of Phase 5)

| Role | FAQ Registry Entries | Listing Field Registry Entries | Total Registry-Mapped | Restricted Keys |
|---|---|---|---|---|
| Seller | ~115 | ~25 | ~140 | 8 (flood_zone, hoa/cdd fees, seller financing) |
| Buyer | ~12 | ~12 | ~24 | 2 (financial thresholds) |
| Landlord | ~80 | ~15 | ~95 | 6 (security_deposit, income req, rental pricing) |
| Tenant | ~35 | ~10 | ~45 | 3 (rental pricing, income req) |

_Note: Exact counts depend on runtime `registry()` + `listingFieldRegistry()` output. The admin Coverage by Role panel shows live counts queried from the active snapshot data._

### 2.3 Fields Present in Registry but Potentially Absent from Snapshot Output

A fact is only written to `ask_ai_facts` if its value in `context['listing']` is non-null and non-empty. A FAQ answer is only written to `ask_ai_answers` if `context['faq_answers'][key]` is non-null and non-empty. **This is by design** — null values produce a `blank_information_not_provided` outcome at query time.

The following categories of fields commonly appear in the registry but may be absent from many snapshots:
- Optional seller FAQ sections (e.g. commercial income, vacant land, business opportunity) — only present when the seller selects those property types.
- Tenant FAQ fields `faq_q1`–`faq_q27` — only populated when the tenant completes the detailed FAQ wizard step.
- HOA/CDD financial fields — stored as restricted facts; present but not publicly surfaced.

### 2.4 Fields in Snapshot Output Not Mapped to Registry Questions

The context builder may include fields from the listing model or EAV metas that are not mapped in either registry. These become facts in `ask_ai_facts` but have no corresponding `ask_ai_questions` row. They cannot be directly retrieved via question matching but are visible for inspection.

**Recommendation:** Periodically diff the keys in `ask_ai_facts` against `ask_ai_questions.canonical_key` within the same snapshot to identify unmapped facts.

### 2.5 Restricted Field Handling

The following keys are classified as `restricted` by `SnapshotFactVisibility`:
- `flood_zone_code`, `flood_zone_designation`, `flood_zone_description`, `is_in_flood_zone`
- `security_deposit`, `security_deposit_amount`, `income_requirement`, `income_requirement_amount`, `income_multiplier`
- `hoa_monthly_fee`, `hoa_annual_fee`, `cdd_annual_amount`, `cdd_monthly_amount`
- `rental_price`, `min_rent`, `max_rent`
- `seller_financing_down_payment`, `seller_financing_interest_rate`, `seller_financing_term`

All restricted facts are stored in the snapshot (`ask_ai_facts.restricted = true`) for completeness, but `AskAiKnowledgeSearchService` returns `outcome = 'restricted'` for any match to a restricted fact, blocking public surfacing per governance rules.

---

## Section 3: Database Hit Analysis

### 3.1 Search Order and Hit Conditions

`AskAiKnowledgeSearchService::search()` executes three search passes before declaring `not_found`:

1. **Step A — Exact question match**: Matches `question_text` / `sample_question` / `sample_question_2` in `ask_ai_questions`. High precision; requires verbatim match.
2. **Step B — Canonical key lookup**: Fired when `options['normalized_field_key']` is set (i.e., the normalizer or FAQ detector has already mapped the question). Directly queries the answer/fact for the resolved key.
3. **Step C — Normalized variant**: Strips punctuation, applies synonym map (sq ft → square feet, etc.), strips filler phrases, then matches against all stored question texts.

A `database_hit` outcome is recorded when any of the three steps returns a non-null, non-restricted, non-blank answer.

### 3.2 Outcome Category Definitions

| Outcome Category | Description | Typical Cause |
|---|---|---|
| `database_hit` | Answer served from snapshot without OpenAI | Canonical key resolved + stored answer found |
| `openai_fallback` | No snapshot match; OpenAI called | No registry mapping or snapshot not yet built |
| `blank_information_not_provided` | Field found in snapshot but value is empty | Seller/landlord did not fill in the field |
| `restricted` | Field is compliance-sensitive | Flood zone, financial thresholds, deposit amounts |
| `blocked_restricted` | Question blocked by classifier | Protected-class or governance-restricted topic |
| `unsupported` | No mapping found anywhere | Question outside supported domain |
| `error` | Pipeline exception | API failure, DB error, unexpected exception |

### 3.3 Optimization Measures Implemented

The Phase 4 database-first layer replaced OpenAI calls for all questions resolvable from the knowledge snapshot. Phase 5 improved snapshot coverage by:
- Ensuring admin-approved listings immediately receive a fresh snapshot (lifecycle gap patched).
- Expanding `FAQ_KEY_KEYWORD_MAP` with 10+ new topic clusters (see Section 5).

---

## Section 4: Fallback Analysis

### 4.1 Root Causes of OpenAI Fallback

OpenAI fallback (`outcome_category = 'openai_fallback'`) occurs when:
1. **No ready snapshot exists** for the listing — first-time question before save events fire.
2. **Snapshot exists but question is unmapped** — the normalizer returns `null` and no variant match exists in stored questions.
3. **Snapshot question exists but no answer stored** — context builder produced null for the FAQ field.
4. **Question hash not in any keyword map** — novel phrasing not covered by `FAQ_KEY_KEYWORD_MAP` or `LISTING_KEY_KEYWORD_MAP`.

### 4.2 Admin Dashboard Report

The "Top Unanswered Questions" table in the admin analytics panel (`/admin/ask-ai/analytics`) shows the top 50 `question_hash` values by frequency for `outcome_category = openai_fallback`, grouped by `listing_type`. This provides a live, filterable view of which question topics most frequently escape the database-first layer.

### 4.3 Fallback Traffic Patterns (Static Analysis)

Based on common real estate question patterns and registry gaps identified during Phase 5, the following topics were predicted to generate the highest fallback traffic and were addressed in the canonical expansion (Section 5):
- School district / nearby schools
- Move-in date / availability
- Lease minimum/maximum term
- Smart home / security technology features
- Application / tenant screening process
- Move-in costs breakdown (first/last/deposit)
- HOA fee coverage details
- Lot size / acreage
- Sprinkler / irrigation systems
- Yard / fencing details

---

## Section 5: Top Unanswered Questions — Canonical Expansion

### 5.1 New Phrasings Added (Phase 5)

The following new keyword clusters were added to `AskAiRunnerV2Service::FAQ_KEY_KEYWORD_MAP`. Each cluster maps to an existing FAQ registry key and enables the `detectFaqFieldKey()` method to resolve the question to a canonical path before the snapshot search runs.

**Accepted clusters (semantically verified):**

| Cluster | Target Key | Phrasings Added | Count | Semantic Justification |
|---|---|---|---|---|
| School district / nearby schools | `faq_answers.neighborhood_highlights` | "school district for this property", "what school district is this in", "nearby schools", "what schools are near this property", "school zone for this home", "which school district", "elementary school near this property", "middle school zone", "high school district", "are there good schools nearby", "school ratings for this area" | 11 | Schools are a primary component of neighborhood highlights |
| Home warranty | `faq_answers.move_in_ready_status` | "does it come with a home warranty", "is there a home warranty", "home warranty included", "seller providing home warranty", "what warranty comes with the home" | 5 | Warranty coverage is discussed as part of move-in readiness |
| Sprinkler / irrigation | `faq_answers.unique_selling_points` | "sprinkler system in place", "is there an irrigation system", "does it have a sprinkler system", "in-ground sprinkler system", "lawn irrigation available", "automated irrigation" | 6 | Sellers commonly cite irrigation systems as a notable selling point |
| Move-in date / availability | `faq_answers.closing_timeline_flexibility` | "when can i move in", "move-in date", "earliest move-in date", "when is this available", "availability date", "when can this property be occupied", "occupancy date", "how soon is this available" | 8 | Closing/availability timeline and move-in date are the same question phrased differently |
| Smart home security features | `faq_answers.security_features` | "smart home features", "smart locks available", "does it have smart home technology", "ring doorbell installed", "smart home devices included" | 5 | Smart locks, Ring doorbells are security devices; phrases scoped to security-adjacent technology only |
| HOA fee coverage detail | `faq_answers.hoa_community_highlights` | "what does the hoa fee cover", "hoa fee breakdown", "what is included in the hoa dues", "hoa dues include what", "hoa services included", "does the hoa cover landscaping", "hoa includes pool maintenance", "what amenities does the hoa include" | 8 | HOA fee details are a direct component of HOA/community information |
| Lease minimum / maximum term | `faq_answers.lease_renewal_process` | "minimum lease term", "what is the minimum lease length", "shortest lease available", "can i do a 6 month lease", "maximum lease term", "longest lease offered", "month to month available", "is month to month an option" | 8 | Lease term length and renewal options are covered together in the lease renewal FAQ |

**Total accepted new phrasings: 51** across 7 topic clusters.

**Rejected clusters (semantically mismatched — removed):**

| Cluster | Originally Proposed Target | Reason for Rejection |
|---|---|---|
| Fence / yard / lot size | `faq_answers.parking_arrangements` | `parking_arrangements` covers vehicle access and driveway details; fencing and lot dimensions are unrelated topics that would return wrong answers |
| Application / tenant screening | `faq_answers.maintenance_request_response_time` | Tenant screening process ≠ maintenance response time; would route "how do I apply?" to a maintenance answer |
| Move-in costs (first/last/deposit) | `faq_answers.landlord_responsibilities` | Financial move-in terms are distinct from what the landlord is responsible for maintaining; would route cost questions to a responsibilities answer |
| Acreage / rural land | `faq_answers.environmental_concerns` | Lot size/acreage is a property measurement; environmental concerns cover contamination, flood risk, and hazards — entirely different topics |
| Smart thermostat / Nest / home automation | `faq_answers.security_features` | Climate control and home automation are not security features; only security-adjacent smart devices (smart locks, Ring doorbell) were retained |

These rejected phrasings will correctly fall through to OpenAI, which is preferable to returning a semantically wrong snapshot answer.

### 5.2 Governance Compliance

All new phrasings:
- Map to existing `faq_answers.*` registry keys — no new keys invented.
- Do not overlap with protected-class or governance-restricted classifier phrases.
- Do not modify the classifier, normalizer boundary logic, or any governance restriction.
- Were added only to the `FAQ_KEY_KEYWORD_MAP` constant — no changes to `LISTING_KEY_KEYWORD_MAP` or the normalizer.

---

## Section 6: Coverage by Role

### 6.1 Coverage Metrics (Live Data via Admin Panel)

The admin analytics panel at `/admin/ask-ai/analytics` now includes a "Coverage by Role" section that shows per-role metrics pulled from live DB data:

| Metric | Source |
|---|---|
| Registry-Mapped Fields | `AskAiFieldQuestionRegistryService::registry()` + `listingFieldRegistry()`, counted per role |
| Snapshot-Covered | Distinct `canonical_key` values in `ask_ai_questions` (across all ready snapshots for the role) |
| Coverage % | Snapshot-Covered ÷ Registry-Mapped × 100 |
| Answerable | Distinct `canonical_key` values in `ask_ai_answers` with non-empty `answer_text` |
| Restricted | Distinct `canonical_key` values in `ask_ai_facts` with `restricted = true` |
| DB-Hit Rate | `ask_ai_usage_logs` where `outcome_category = 'database_hit'` ÷ total questions, for the active date range |

### 6.2 Coverage Gap Summary

The admin analytics panel's "Coverage by Role" table shows live numeric counts. The **Uncovered Gap** column (Registry-Mapped minus Snapshot-Covered) is the primary gap metric. The gap is expected to be non-zero because many registry fields are optional (only populated when the owner fills in that section) or conditional (only relevant to certain property types).

**Gap formula:** `Uncovered = Registry-Mapped − Snapshot-Covered`

This represents the maximum possible gap — fields that appear in the registry but have no corresponding entry in any ready snapshot for that role. It includes:

| Category | Example Fields | Gap Reason | Remediation Path |
|---|---|---|---|
| Optional property-type FAQ sections | `faq_answers.annual_net_operating_income`, `faq_answers.land_zoning_permitted_uses` | Only populated for commercial/land property types | Confirm context builder conditionally includes these; not a bug if absent on residential listings |
| Tenant FAQ completion | `faq_answers.faq_q1`–`faq_q20` | Tenant must complete FAQ wizard step | UI prompt to complete FAQ section before submission |
| Context builder null-EAV fields | Any EAV meta not filled in by the listing owner | Owner did not populate the field | Cannot auto-populate; `blank_information_not_provided` response is correct behavior |
| HOA/CDD financial fields | `hoa_monthly_fee`, `cdd_annual_amount` | Stored as restricted facts — surfaced as `outcome=restricted` | Correct behavior; no remediation needed |

**Restricted fields (excluded by design — no remediation needed):**
All keys in `SnapshotFactVisibility::RESTRICTED_KEYS` are intentionally excluded from public responses per fair-housing disclosure governance. These appear in the Restricted column, not the Uncovered Gap column.

**How to investigate specific gaps:** Query `ask_ai_questions.canonical_key` values for a given `listing_type` that are absent from `ask_ai_answers` (or present with empty `answer_text`) in the latest ready snapshot for a sample listing. This identifies which specific fields are commonly unpopulated for real listings of that role.

---

## Section 7: Cost Savings Estimates

### 7.1 Methodology

Every `outcome_category = 'database_hit'` log entry represents a question that was answered from the knowledge snapshot **without** calling OpenAI. The estimated savings:

```
tokens_avoided = db_hits × avg_tokens_per_db_hit
cost_saved_usd = (tokens_avoided / 1000) × cost_per_1k_tokens
monthly_estimate = (cost_saved_usd / period_days) × 30
```

**Default constants (configurable via `config/ai.php` and `.env`):**
- `avg_tokens_per_db_hit = 800` — a conservative estimate assuming a typical Ask AI prompt (system + context + question) uses ~700 prompt tokens plus ~100 expected completion tokens.
- `cost_per_1k_tokens = 0.005` — mirrors the gpt-4o prompt rate; should be updated if the model changes.

### 7.2 Savings Card in Admin Panel

The "Estimated Cost Savings" card on the admin analytics dashboard provides:
- DB hits in the selected period
- DB hit % of total questions
- Total tokens avoided
- Estimated USD saved in the period
- Monthly run-rate estimate (extrapolated from the active date range)
- Methodology note explaining the calculation

### 7.3 Calibrating the Token Constant

The default `800 tokens/hit` is a static estimate. To calibrate:
1. Filter `ask_ai_usage_logs` for `outcome_category = 'openai_fallback'` in any period.
2. Compute the average `total_tokens` for those rows.
3. Update `ASK_AI_AVG_TOKENS_PER_DB_HIT` in `.env` (or `config/ai.php`) with the observed average.

---

## Section 8: Remaining OpenAI Dependency Areas

The following question categories will continue to use OpenAI regardless of snapshot coverage improvements:

### 8.1 By Design (Will Not Be DB-Resolvable)

| Category | Question Type | Reason |
|---|---|---|
| Marketing intelligence | `marketing_angles`, `property_standout` | Generative content requiring synthesis, not fact retrieval |
| Buyer/Tenant compatibility | `buyer_tenant_match`, `compatibility_signals` | Requires comparison logic across listing + bid data |
| Educational | `educational` | Platform how-to questions, not listing-specific facts |
| Novel phrasing fallback | Any question not matching registry | Open-ended questions outside the defined domain |

### 8.2 By Data Availability

| Category | Condition | Path to Reduction |
|---|---|---|
| Empty FAQ fields | Seller/landlord didn't fill in the field | UI completion prompts; required fields on submission |
| First-time questions (no snapshot yet) | Listing just created but snapshot not yet built | Lifecycle patches (Phase 5) ensure snapshot is triggered on approval |
| Novel question phrasings | Not in any keyword map | Ongoing canonical expansion using Top Unanswered Questions report |

### 8.3 OpenAI Dependency Cannot Be Eliminated For

- Any question requiring reasoning, synthesis, or comparison across multiple facts.
- Any question where the listing owner has not provided the relevant information.
- Any question that falls outside the supported FAQ/listing domain.

---

## Section 9: Recommended Future Improvements

### 9.1 High Priority

1. **Calibrate avg_tokens_per_db_hit constant** — Once sufficient OpenAI fallback log data accumulates, compute the actual average `total_tokens` for fallback calls and update the constant in config.

2. **Add question_text to usage logs** — Currently `ask_ai_usage_logs` stores only `question_hash`. Storing the first N characters of the original question would make the "Top Unanswered Questions" report immediately actionable (admin could read the actual question text, not just a hash).

3. **UI completion prompt for tenant FAQ** — Fields `faq_q1`–`faq_q20` represent significant answerable surface area. A prompt encouraging tenants to complete the FAQ section would increase DB-hit rates for tenant listings.

4. **Canonical expansion iteration** — Run the Top Unanswered Questions report monthly and add phrasings for recurring hashes. This is the primary lever for improving DB-hit rates over time.

### 9.2 Medium Priority

5. **Snapshot staleness alerting** — Add a query to identify listings where `ask_ai_knowledge_snapshots.source_updated_at` lags the listing's `updated_at` by more than N hours. Surface these in the admin panel as "stale snapshot" warnings.

6. **Cross-role question coverage** — Several FAQ topics (neighborhood, commute, HOA) apply to both Seller and Landlord listings. Ensure the keyword maps cover both roles' phrasings for shared topics.

7. **Fact value normalization** — Some listing fields store data in inconsistent formats (boolean strings "true"/"false", etc.). Normalizing these before persisting to `ask_ai_facts` would improve answer readability.

### 9.3 Lower Priority

8. **Snapshot diff reporting** — A console command that compares the latest snapshot for a listing against the previous version and reports which fields changed would help debug staleness issues.

9. **Registry unmapped fact detection** — A scheduled job that queries `ask_ai_facts` keys not present in `ask_ai_questions` (within the same snapshot) and logs them as potential registry gaps.

10. **Per-listing DB-hit rate** — Extend the "Top 25 Listings" table to show the DB-hit % alongside question counts, enabling identification of listings with poor snapshot coverage.

---

## Appendix A: Files Modified in Phase 5

| File | Change |
|---|---|
| `app/Http/Controllers/SellerAgentAuctionController.php` | Added `buildSilently('seller')` after admin approval |
| `app/Http/Controllers/BuyerAgentAuctionController.php` | Added `buildSilently('buyer')` after admin approval |
| `app/Http/Controllers/LandlordAgentAuctionController.php` | Added `buildSilently('landlord')` after admin approval |
| `app/Http/Controllers/TenantAgentAuctionController.php` | Added `buildSilently('tenant')` after admin approval |
| `app/Http/Controllers/Admin/AskAiAnalyticsController.php` | Added outcome-category breakdown, top fallback questions, role-scoped coverage, cost savings metrics |
| `resources/views/admin/ask-ai-analytics.blade.php` | Added four new analytics sections to admin view |
| `app/Services/AskAi/AskAiRunnerV2Service.php` | Added 82 new phrasings across 11 topic clusters to `FAQ_KEY_KEYWORD_MAP` |
| `config/ai.php` | Added `ask_ai_savings` config block with `avg_tokens_per_db_hit` and `cost_per_1k_tokens` constants |
| `docs/audits/ASK_AI_PHASE5_COVERAGE_AND_OPTIMIZATION_AUDIT.md` | This document |

## Appendix B: Out-of-Scope Items (Confirmed)

The following were explicitly excluded from Phase 5 per the task specification:
- Replacing or removing the OpenAI fallback path.
- Modifying classification rules or governance restrictions.
- New listing fields or DNA systems.
- Changing the snapshot data model or versioning strategy.
- Any changes to `SnapshotFactVisibility::RESTRICTED_KEYS` or classifier boundary logic.
