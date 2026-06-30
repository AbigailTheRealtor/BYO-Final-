# Ask AI Knowledge Base — Full Audit & Remediation Roadmap

**Scope:** The "Listing AI Knowledge Base" question sets used by Create Offer Listings.
**Status:** Audit only. No production knowledge base or Ask AI behavior has been modified by this document.
**Date:** 2026-06-30
**Companion document:** `docs/ask-ai-kb-replacement-spec.md` (draft replacement specification — the implementation roadmap derived from this audit).

---

## 1. Source files audited

| File | Role | Shape |
|---|---|---|
| `config/ai_faq_seller.php` | Seller | `questions` (nested category → key) + `addons` (gated by `visible_for`) |
| `config/ai_faq_buyer.php` | Buyer | `questions` + `addons` |
| `config/ai_faq_landlord.php` | Landlord | `questions` + `addons` |
| `config/tenant_ai_faq.php` | Tenant | flat list with `category` + `commercial_only` flag |

Rendered by `resources/views/livewire/offer-listing/shared/ai-questions-input.blade.php`.

These question `label`s are the form fields the listing creator fills in. Their answers pre-load the listing chatbot ("Ask AI") so the *other party* can ask questions and get answers automatically.

---

## 2. How questions compose (mechanics that drive most findings)

- **Base questions (`.questions`) render for EVERY property type — there is no property-type gate.**
- **Addons render only when `property_type` ∈ `visible_for`.**
- Tenant is a flat list; `commercial_only` questions appear only when `property_type === 'Commercial Property'`, but the 20 residential questions always render (including for commercial).

System property-type strings: `Residential Property`, `Income Property`, `Commercial Property`, `Business Opportunity`, `Vacant Land`. Landlord/Tenant use only `Residential Property` / `Commercial Property`.

### Composition & counts per knowledge base (14 total)

| Knowledge base | Base | Addon(s) | Total Q |
|---|---|---|---|
| Seller · Residential | 33 | — | **33** |
| Seller · Income Property | 33 | commercial_income (6) | **39** |
| Seller · Commercial | 33 | commercial_income (6) | **39** |
| Seller · Business Opportunity | 33 | business_opportunity (7) | **40** |
| Seller · Vacant Land | 33 | vacant_land (6) | **39** |
| Buyer · Residential | 28 | — | **28** |
| Buyer · Income Property | 28 | commercial_income (8) | **36** |
| Buyer · Commercial | 28 | commercial_income (8) | **36** |
| Buyer · Business Opportunity | 28 | commercial_income (8) + business_opportunity (7) | **43** |
| Buyer · Vacant Land | 28 | vacant_land (7) | **35** |
| Landlord · Residential | 28 | — | **28** |
| Landlord · Commercial | 28 | commercial (12) | **40** |
| Tenant · Residential | 20 | — | **20** |
| Tenant · Commercial | 20 | commercial_only (7) | **27** |

---

## 3. Cross-cutting findings

**F1 — Base questions are not gated by property type (highest-impact structural defect).**
Every base question shows for every property type:
- **Seller · Vacant Land** asks roof age, HVAC age, water-heater type, mold, pest/termite, foundation issues, HOA highlights, parking, storage, "what will you miss most," natural light — ~30 of 33 are about a house that doesn't exist on raw land.
- **Seller · Commercial / Income / Business Opportunity** inherit residential-home condition questions (water heater, mold, HOA community highlights, "what aspects will you miss," natural-light orientation) that don't fit those asset classes.
- **Landlord · Commercial** inherits residential questions (laundry, pets, bicycle storage, EV charging, furnished status, renter's insurance, "who typically lives here," smoking) largely irrelevant to a commercial lease.
- **Tenant · Commercial** inherits all 20 residential lifestyle/pet questions on top of the commercial set.

**F2 — Income/Commercial/Business/Land addons re-ask data that is natively displayed.**
- Seller income/commercial: **NOI, cap rate, occupancy, operating expenses** are native-displayed → re-asked.
- Seller business: **revenue, net profit/SDE, employee count, inventory/FF&E, lease status** are native-displayed → re-asked.
- Seller/landlord/tenant commercial: **CAM/NNN, lease structure, signage, build-out** are native-displayed → re-asked.

**F3 — Buyer base re-asks native buyer criteria.**
`buyer_commute_requirements`, `buyer_hoa_acceptable`, `buyer_view_preference` are fully displayed native fields. Partial dups: renovation tolerance vs. "acceptable conditions," must-haves vs. "non-negotiable amenities," long-term goals vs. "purchase purpose," simultaneous close vs. native home-sale contingency, seller concessions vs. native seller-contribution term.

**F4 — Tenant KB largely re-asks the native Pre-Screening section, and its lifestyle block is mis-aimed.**
Pets, income amount, move-in dates, business use, smoking, credit, occupants are native pre-screening fields. The tenant listing's audience is the landlord screening the applicant, yet q1–q6 ask the tenant about their own ideal vibe/WFH/outdoor space — low value to the approval decision.

**F5 — Audience alignment is otherwise good.** Seller→buyer, Buyer→seller/agent, Landlord→tenant framing is correct in the residential base sets. Exception is the Tenant lifestyle block (F4).

**F6 — Cross-role inconsistency.** Seller's `commercial_income` addon `visible_for` excludes `Business Opportunity`; Buyer's `commercial_income` includes it. Decide intentionally.

**F7 — Only genuinely hidden topic is Broker Compensation / Agency Terms** (collected but never shown publicly, both seller & buyer). These are agent-facing/negotiation items — recommend keeping them out of a consumer FAQ rather than adding them.

---

## 4. Duplicate questions (internal overlaps)

| Role | Overlapping questions | Note |
|---|---|---|
| Seller | `as_is_condition` ↔ `seller_concessions_offered` ↔ `environmental_concerns` ↔ `flood_damage_history` | Overlap on repairs/credits/environment — consolidate |
| Seller | `move_in_ready_status` ↔ property condition + `recent_renovations_list` + `known_defects_issues` | Redundant framing |
| Buyer | `buyer_must_have_features` ↔ `buyer_deal_breakers` ↔ `buyer_nice_to_have` | Partial overlap of "what matters" |
| Tenant | `q9` lease-length ↔ `q13` longer-lease ↔ `q16` short/long-term | Three angles on lease duration |
| Tenant | `q14` motivation ↔ `q15` why moving ↔ `q16` horizon | Overlapping background intent |

---

## 5. Listing-field duplicates (answer already displayed on listing)

| Role / KB | Question key | Native field that already displays it | Verdict |
|---|---|---|---|
| Buyer | `buyer_commute_requirements` | Commute ZIP / mode / max-minutes | **Full dup** |
| Buyer | `buyer_hoa_acceptable` | HOA acceptance + max monthly fee | **Full dup** |
| Buyer | `buyer_view_preference` | View preference | **Full dup** |
| Buyer | `buyer_renovation_tolerance` | Acceptable property conditions | Partial |
| Buyer | `buyer_must_have_features` | Non-negotiable amenities | Partial |
| Buyer | `buyer_long_term_goals` | Purchase purpose | Partial |
| Buyer | `buyer_simultaneous_close` | Home-sale contingency (+ their property) | Partial |
| Buyer | `buyer_seller_concessions` | Seller contribution / closing-cost responsibility | Partial |
| Buyer (com) | `com_cap_rate_target` | Min Cap Rate | **Full dup** |
| Buyer (com) | `com_investment_type` / `com_due_diligence_period` | Purchase purpose / DD contingency+period | Partial |
| Seller (income/com) | `annual_net_operating_income` | Annual Net Income (NOI) | **Full dup** |
| Seller (income/com) | `current_cap_rate` | Cap Rate | **Full dup** |
| Seller (income/com) | `current_occupancy_rate` | Units Occupied | **Full dup** |
| Seller (income/com) | `annual_operating_expenses_detail` | Annual Operating Expenses | Partial |
| Seller (income/com) | `existing_tenant_lease_terms` | Lease type/exp + rent roll flag + expected rent/unit | Partial |
| Seller (biz) | `annual_business_revenue` | Annual Revenue | **Full dup** |
| Seller (biz) | `annual_net_profit` | SDE/EBITDA / Gross Profit | **Full dup** |
| Seller (biz) | `business_employee_count` | Employee Count | **Full dup** (keep "will they stay") |
| Seller (biz) | `inventory_equipment_included` | Inventory / FF&E / Sale Includes | Partial |
| Seller (biz) | `business_lease_status` | Lease type/exp/assignable | Partial |
| Seller (land) | `land_utilities_availability` | Utility availability-to-site | Partial/dup |
| Seller (land) | `land_zoning_permitted_uses` | Zoning | Partial (keep "permitted uses") |
| Seller (land) | `land_access_and_road` | Road frontage / easements | Partial |
| Seller (land) | `land_development_restrictions` | Easements | Partial |
| Seller | `parking_arrangements` | Garage/carport spaces + parking | Partial |
| Seller | `hoa_community_highlights` | HOA fee / amenities / restrictions | Partial |
| Seller | `neighborhood_restrictions` | HOA leasing/pet restrictions | Partial |
| Seller | `foundation_type_and_issues` | Foundation type | Partial (keep "issues") |
| Seller | `closing_timeline_flexibility` | Target closing date | Partial (keep "flexibility") |
| Landlord | `subletting_allowed` | Subletting Policy | **Full dup** |
| Landlord | `smoking_policy` | Smoking Policy (lease terms + applicant req) | **Full dup** |
| Landlord | `lease_renewal_process` | Renewal Option (offered/details) | Partial |
| Landlord | `preferred_tenant_qualities` | Desired Tenant Criteria + Applicant Requirements | Partial |
| Landlord | `guest_parking` / `utilities_individually_metered` | Parking fields / utilities-included + tenant-pays | Partial |
| Landlord (com) | `commercial_cam_charges` | CAM / NNN Additional Rent | **Full dup** |
| Landlord (com) | `commercial_lease_structure_type` | Commercial Lease Type | **Full dup** |
| Landlord (com) | `commercial_signage_rights` | Signage Rights | **Full dup** |
| Landlord (com) | TI allowance / buildout flexibility / parking ratio / landlord maintenance | TI-buildout terms / parking terms / landlord maintenance | Partial |
| Tenant | `q7` pet breed/size | Pets + breed + weight (pre-screening) | **Full dup** |
| Tenant | `q5` top amenity / `q6` outdoor space | Required amenities / pool | Partial |
| Tenant | `q11` move-in firmness / `q9`,`q16` lease length | Move-in earliest/latest / desired lease length | Partial |
| Tenant | `q15` why moving | Rental history disclosure | Partial |
| Tenant (com) | `q21` business type | Intended Business Use | **Full dup** |
| Tenant (com) | `q24` signage / `q25` buildout / `q27` lease flex | Signage request / buildout request / commercial lease type+CAM pref | Partial |

> **Important:** "Full dup" means it should be removed from the curated *suggested-question* list — **not** that Ask AI should be prevented from answering it. Factual questions (beds, price, CAM, etc.) must still be answerable by Ask AI from structured listing data. The goal is to avoid wasting curated suggested-question slots on facts already visible.

---

## 6. Weak / low-value questions

| Role | Question | Issue |
|---|---|---|
| Seller | `permits_for_renovations` | Trends yes/no (acceptable, low risk) |
| Buyer | `buyer_wfh_needs` | Low value to a seller audience |
| Landlord | `emergency_maintenance_available` | Yes/no |
| Landlord | `bicycle_storage_available` | Very niche |
| Tenant | `q1` WFH, `q3` neighborhood vibe, `q19` communication preference | Low value to a landlord screening decision |
| Tenant | `q1`–`q6` lifestyle block | Mis-aimed audience (F4) |

---

## 7. Missing topics (by audience, compliance-screened)

> Topics involving negotiation, pricing/offer strategy, legal/financial/tax/investment advice, due-diligence advice, or approval recommendations are intentionally excluded per Phase 6 compliance standards. Schools and audience-fit topics are included only with neutral, Fair-Housing-safe framing.

- **Seller · Residential:** school districts serving the property (objective, Location DNA), age of windows/plumbing/electrical panel, insurance/CLUE claims history (factual disclosure), solar panels (owned vs. leased), smart-home/EV features, internet speed/availability.
- **Seller · Income:** disclosed tenant payment history, deferred maintenance / near-term capex (factual), recent capital improvements, professional management in place, disclosed rents vs. market (factual statement only), utility responsibility split, disclosed reason for selling.
- **Seller · Commercial:** zoning & permitted uses (interpretation), ADA/accessibility, building systems (HVAC/electrical), restroom count, ceiling height/clear height context, access/loading.
- **Seller · Business Opportunity:** customer concentration, vendor/supplier contracts, license/franchise transferability, online presence/reviews, seasonality, disclosed reason for selling, owner involvement (absentee?).
- **Seller · Vacant Land:** soil/perc status (factual), survey availability, buildability factors (objective), wetlands/flood context (objective), recorded easements/access.
- **Buyer (all):** disclosed financing type & pre-approval status (factual; native partly), disclosed timeline/possession needs, disclosed contingency preferences (factual only — no strategy).
- **Landlord · Residential:** application process/fee & timeline, average actual utility costs (native has estimates), lawn/landscaping responsibility, flooring/finishes, move-in inspection process, school districts (objective).
- **Landlord · Commercial:** ADA/accessibility, HVAC type/zones + after-hours HVAC, restroom count, co-tenancy/anchor tenants, expense-stop/gross-up structure (factual disclosure).
- **Tenant · Residential:** income source/stability (factual disclosure), references available, break-lease risk, prior rental conduct (disclosure), co-signer availability, application readiness.
- **Tenant · Commercial:** foot-traffic expectation, equipment/power needs, hours of operation, parking needs.

---

## 8. Coverage analysis

Coverage = how well each KB covers the relevant topic categories (Property, Lifestyle, Restrictions, History, Financial, Operations, Flexibility, Future plans, Compatibility, Location, Unique features, Usage) **for the correct audience and property type**.

- **Residential KBs (Seller/Buyer/Landlord):** broad coverage, minor gaps (schools, systems age, application process).
- **Income / Commercial / Business / Land KBs:** coverage is *nominally* high but *effectively* low — most slots are consumed by inherited residential questions (F1) or native-field dups (F2), leaving real operational/usage topics thin.
- **Tenant KBs:** background/qualification coverage is solid; lifestyle coverage is over-weighted for the wrong audience (F4).

## 9. Quality analysis

Quality = conversational, single-meaning, non-duplicative, value-adding, not yes/no, not AI-sounding, audience-appropriate.

- Wording quality is generally high across all files (clear, conversational, with helpful placeholders/tooltips).
- Quality is dragged down primarily by **duplication** (F2/F3/F4) and **property-type misfit** (F1), not by phrasing.
- A handful of yes/no or niche items (Section 6) are the only phrasing-level weaknesses.

---

## 10. Scorecard (all 14 knowledge bases)

Scores 0–100. Coverage = topic breadth for the right audience; Quality = conversational/non-duplicative/value-adding; Completeness = overall incl. property-type fit.

| Knowledge Base | Q | Field-dups | Weak/misfit | Coverage | Quality | Completeness |
|---|---|---|---|---|---|---|
| Seller · Residential | 33 | ~6 partial | low | 80 | 78 | **80** |
| Seller · Income Property | 39 | 3 full + 2 partial | base misfit | 60 | 55 | **55** |
| Seller · Commercial | 39 | 3 full + 2 partial | base misfit | 50 | 50 | **50** |
| Seller · Business Opportunity | 40 | 3–4 dups | base ~all misfit | 42 | 45 | **42** |
| Seller · Vacant Land | 39 | 4 partial + ~30 misfit | severe (F1) | 35 | 40 | **36** |
| Buyer · Residential | 28 | 3 full + 5 partial | low | 75 | 72 | **73** |
| Buyer · Income Property | 36 | 1 full + 2 partial | base lifestyle misfit | 65 | 60 | **62** |
| Buyer · Commercial | 36 | 1 full + 2 partial | base lifestyle misfit | 62 | 60 | **60** |
| Buyer · Business Opportunity | 43 | partial + F6 overlap | bloated, base misfit | 56 | 52 | **54** |
| Buyer · Vacant Land | 35 | minor | base lifestyle misfit | 60 | 57 | **58** |
| Landlord · Residential | 28 | 2 full + 4 partial | 2 niche | 80 | 78 | **80** |
| Landlord · Commercial | 40 | 3 full + 4 partial | base ~all misfit (F1) | 50 | 48 | **48** |
| Tenant · Residential | 20 | 1 full + several partial | lifestyle block mis-aimed | 60 | 58 | **58** |
| Tenant · Commercial | 27 | 1 full + 3 partial | 20 base misfit (F1) | 48 | 46 | **46** |

**Healthiest:** Seller·Residential, Landlord·Residential, Buyer·Residential.
**Most in need of work:** every Vacant Land, Commercial, Business Opportunity, and Income combination (dragged down by F1 + F2).

---

## 11. Remediation roadmap

1. **Structural (biggest win): gate base questions by BOTH user type and property type (F1).** Split each base set into `universal` / `residential` / asset-class groups so Income, Commercial, Business, and Vacant Land stop inheriting residential questions. Requires a config schema change + a small blade change.
2. **Drop native-displayed duplicates from the curated suggested-question list (F2/F3/F4)** — while preserving Ask AI's ability to answer them from structured data.
3. **Reframe partial dups** to capture only the non-obvious nuance.
4. **Fill missing high-value topics** (Section 7), compliance-screened.
5. **Reorganize into two categories:** *Common Questions* and *AI Insights*.
6. **Apply Phase 6 compliance standards** (educational/neutral/factual; Fair Housing; no advice; no steering) to every question and to Ask AI prompting/guardrails.
7. **Resolve F6** (business-opportunity addon visibility) intentionally.
8. **Decide F7** (recommend leaving broker-comp/agency terms out of the consumer FAQ).

> The exact per-knowledge-base implementation (remove / keep / reword / add, with audience, property type, category, source data, and data-sufficiency confirmation) is specified in `docs/ask-ai-kb-replacement-spec.md`.

---

## 12. Mandatory pre-implementation review gates (added 2026-06-30)

Two additional review passes are **required and now complete** before any production change. Both are documented in full in `docs/ask-ai-kb-replacement-spec.md` (Part E.1 and Part H).

### Phase 0 — Compliance Review (gate)
A comprehensive review of every proposed question, AI Insight, placeholder, tooltip, and *anticipated AI response* for: Fair Housing, steering, discrimination, real-estate licensing, legal/financial/investment/negotiation/tax advice, privacy, unsupported claims, speculation, and consumer protection. Any item creating unnecessary risk was rewritten to neutral educational language or removed.

### Final Real-World Review (gate)
Every question re-read from 12 audience perspectives (listing agent, buyer's agent, commercial broker, property manager, landlord, tenant, home buyer, home seller, commercial buyer, commercial tenant, investor, typical consumer), asking "is this a question a real person actually asks?" Non-realistic items were rewritten or removed.

**Standard reaffirmed:** Ask AI explains, educates, summarizes, clarifies, describes, and compares *on-platform factual data* only. It never advises, recommends actions/prices/approvals, gives professional advice, or speculates.

### New concerns discovered in these passes (summary; full detail in spec Part E.1)
- **C-A (highest priority):** KB free-text can itself contain steering/demographic/unsupported content, so Fair-Housing neutralization must happen at **AI output time**, not just at question level. **Implementation blocker.**
- **C-B (critical):** Tenant-listing Ask AI can expose sensitive applicant data — privacy/consumer-protection/FCRA-adjacent. Now specified as a full **access-control policy (spec Part J)** with a **conservative, fail-closed rule (J.6)**: sensitive data only to the tenant owner or a landlord/agent with an accepted/active in-platform relationship to that listing; never via public `/ask-ai/ask`; **default-redacted until verified in code.** Candidate backing tables catalogued in **J.7 (all TBD pending code inspection)**. **Implementation blocker** (build viewer-auth + redaction; choose/verify the relationship table).
- **C-C (refined R3):** Ask AI must never identify, evaluate, or describe any party's negotiating position, leverage, strengths, or weaknesses — it only explains disclosed information factually. The "strategic disadvantage / protect-leverage" framing is dropped entirely. `buyer_lost_deal` removed.
- **C-D/C-E (refined R3):** "Type of buyer/tenant" Insights reframed to be **property-centered** ("What property features may appeal to different buyers?"); never describe people or demographics. "Lifestyle" Insights limited to property features/amenities.
- **C-F (decided R3):** Schools = objective facts only (assigned school name(s) if available, district, public links/references). No recommending, rating, comparing, or quality opinions. Keep-vs-remove decision resolved (keep-constrained).
- **C-G:** Zoning/permitted-use changed from interpretation to restatement (+ verify-with-jurisdiction).
- **C-H:** Financial terms (cap rate, NOI, NNN, 1031, SBA) may be defined and restated, never benchmarked, computed, or opined on.
- **C-I:** "Stand out/unique" Insights barred from superlatives/comparative claims.
- **C-J:** Persistent educational disclaimer required on Ask AI.
- **C-K:** "Previous tenant feedback" removed (privacy/unsupported claims); optional objective retention statement instead.

### Quality/realism issues discovered
- Removed as non-realistic or mis-aimed: `seller_favorite_features`, `buyer_lost_deal`, `previous_tenant_feedback`, tenant `faq_q2`.
- Reworded for realism + compliance: all "type of person" Insights → needs/use framing; neighborhood "vibe/feel" → objective setting.
- **No knowledge base passed unchanged** — including the three highest-scoring ones (Seller·Residential, Landlord·Residential, Buyer·Residential), satisfying the requirement that high scores not be assumed to need only minor changes.

### Revised sequencing (gated)
Resolve Phase 0 blockers (C-A guardrail, C-J disclaimer, C-B access decision, C-F school decision) → build advice-refusal guardrail → Part A architecture → Part F content as revised by spec Part E.2 → platform-wide placeholder rewrite → re-validate (spec Part G + G.2) → enable.
