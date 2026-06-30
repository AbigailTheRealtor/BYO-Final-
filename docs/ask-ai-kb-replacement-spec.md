# Ask AI Knowledge Base — Draft Replacement Specification

**Status: DRAFT — FOR REVIEW. Nothing in this document has been applied.**
No production config (`config/ai_faq_*.php`, `config/tenant_ai_faq.php`), no blade, and no Ask AI prompt/guardrail has been modified. This is the implementation roadmap to be approved before any code change.

**Companion:** `docs/ask-ai-kb-audit.md` (findings & scorecard this spec is derived from).
**Date:** 2026-06-30
**Revision 2 (2026-06-30):** Added and completed two mandatory pre-implementation review passes — **Part E.1 (Phase 0 Compliance Review)** and **Part H (Final Real-World Review)** — plus **Part E.2 (R2 Revisions)**, **Part I (new platform requirements)**, and **Part G.2 (expanded validation)**. Where Part E.2 lists an entry, that entry's final wording/disposition **supersedes** Part F.
**Revision 3 (2026-06-30):** Applied owner refinements to the compliance findings: expanded output-sanitization categories (C-A); resolved the tenant access model into a documented policy (**new Part J**, C-B); **removed all strategic-disadvantage / leverage / negotiating-position analysis** (C-C); reframed "type of buyer/tenant" to **property-centered** language (C-D); finalized **schools = objective facts only** (C-F decided, no longer an open decision); tightened financial-term and superlative rules (C-H, C-I); confirmed previous-tenant-feedback removal (C-K). Updated Parts I, G.2, and sequencing accordingly.
**Revision 4 (2026-06-30):** Adopted the **most-restrictive, fail-closed tenant authorization rule** (Part J.6): sensitive applicant data is available only to the tenant owner or to a landlord/agent with an accepted/active in-platform relationship to that listing; never via public `/ask-ai/ask`; **default to redacted until verified in code.** Added **Part J.7** cataloguing candidate relationship tables/models, all marked **TBD pending code inspection**.

**Legend (per-question tags):**
- **Category:** `[Common]` = real question the audience commonly asks · `[Insight]` = AI-generated educational prompt the user often wouldn't think to ask.
- **Origin:** `KEEP` (existing, unchanged) · `REWORD` (existing key, new label) · `NEW` · (removals listed separately).
- **Source:** where Ask AI draws the answer. **Sufficiency:** whether data exists to answer.
- **⚖︎** = compliance note (Phase 6).

---

# PART A — Property-Type Architecture (Phase 2)

## A.1 Problem
Base questions currently render for every property type (no gate). Residential questions leak into Income, Commercial, Business Opportunity, and Vacant Land knowledge bases.

## A.2 Proposed two-axis gating model
Questions are grouped, and each knowledge base = **Universal group + the one matching property-type group** for that user type. Residential-only questions never render for non-residential types unless a question is **explicitly tagged `shared`** (e.g. roof/HVAC age for an income or commercial building).

### Proposed config shape (illustrative — not yet implemented)
```php
// config/ai_faq_seller.php (same pattern for buyer/landlord/tenant)
return [
    'groups' => [
        'universal'   => [ /* category => [ key => [label, placeholder, tooltip, category_type, source] ] */ ],
        'residential' => [ ... ],
        'income'      => [ ... ],
        'commercial'  => [ ... ],
        'business'    => [ ... ],
        'land'        => [ ... ],
    ],
    // which groups each property_type renders, in order
    'gating' => [
        'Residential Property' => ['universal', 'residential'],
        'Income Property'      => ['universal', 'income'],
        'Commercial Property'  => ['universal', 'commercial'],
        'Business Opportunity' => ['universal', 'business'],
        'Vacant Land'          => ['universal', 'land'],
    ],
];
```
- Each question entry gains two fields: `category_type` (`common` | `insight`) and `source` (data origin, see Part D/E).
- The render blade resolves `gating[$property_type]` → renders those groups only. Landlord/Tenant gating maps `Residential Property` / `Commercial Property` to `['universal','residential']` / `['universal','commercial']`.
- **Migration safety:** existing stored answers are keyed by question `key`; keys are preserved on KEEP/REWORD, so previously-saved answers remain bound. Removed keys simply stop rendering (data is retained, not deleted).

### A.3 Gating matrix (final)

| User type | Property type | Groups rendered |
|---|---|---|
| Seller | Residential | universal + residential |
| Seller | Income Property | universal + income |
| Seller | Commercial | universal + commercial |
| Seller | Business Opportunity | universal + business |
| Seller | Vacant Land | universal + land |
| Buyer | Residential | universal + residential |
| Buyer | Income Property | universal + income |
| Buyer | Commercial | universal + commercial |
| Buyer | Business Opportunity | universal + business |
| Buyer | Vacant Land | universal + land |
| Landlord | Residential Rental | universal + residential |
| Landlord | Commercial Rental | universal + commercial |
| Tenant | Residential Rental | universal + residential |
| Tenant | Commercial Rental | universal + commercial |

> **Resolves F6:** Business Opportunity renders the `business` group only (not `commercial_income`/`income`), for both Seller and Buyer. This is the deliberate decision replacing today's inconsistency.

---

# PART B — Suggested-question dedup principle (Phase 3)

A question is removed from the **curated suggested-question list** when its answer is already clearly displayed on the listing (beds, baths, sq ft, price, property type, MLS fields, broker compensation, agency terms, visible amenities, HOA fields, CAM, lease type, parking counts, NOI, cap rate, occupancy, revenue, etc.).

**This does not reduce Ask AI's answer capability.** Ask AI continues to answer such factual questions from structured listing data. Dedup only frees curated suggested-question slots for higher-value prompts. Every removal in Part F is "remove from suggested chips," not "block the answer."

---

# PART C — Audience alignment (Phase 4)

| Listing | Suggested questions represent what… |
|---|---|
| Seller | buyers / buyers' agents ask about the property |
| Buyer | sellers / listing agents ask about the buyer |
| Landlord | tenants / tenant agents ask about the rental |
| Tenant | landlords / leasing agents ask about the applicant |

Audiences are never mixed. Note for Buyer/Tenant listings there is **no subject property** — `[Insight]` prompts educate about the *buyer/tenant profile & criteria* (Buyer/Tenant DNA, stated criteria, Match), not a property.

---

# PART D — Two-category organization (Phase 5)

**1. Common Questions** — real questions the audience asks. Answered from the creator's Knowledge Base answers (`listing_ai_faq`) plus structured listing fields.

**2. AI Insights** — prompts users often don't think to ask, drawing on platform-generated data to educate:
- Listing data & structured fields · Property Description · Highlights · **Property DNA** · **Location DNA** · **Match** information · approved platform analysis.
- Approved Insight themes: standout features; audience fit (neutral); lifestyle supported (neutral); nearby location features; disclosed-information summary; what matters to this audience; uniqueness.
- **Prohibited Insight themes (hard rule):** negotiation, legal advice, financial advice, investment recommendations, approval recommendations, pricing strategy, offer strategy, due-diligence advice.

---

# PART E — Compliance standards (Phase 6) — governs every entry below

These override all other implementation decisions and apply to suggested questions, KB entries, prompts, and AI responses.

- **Purpose:** Ask AI is an *educational* assistant that helps users understand information disclosed on the platform. It is **not** a substitute for licensed professionals (agents, attorneys, lenders, inspectors, appraisers, accountants, insurance pros, engineers).
- **MAY:** explain/summarize listing data, disclosed features/lease/offer terms, Property DNA, Location DNA, Match, platform insights; answer factual questions supported by available data; summarize disclosures.
- **MUST NEVER:** give legal/financial/tax/investment/insurance/engineering/inspection/appraisal/lending advice; recommend negotiation strategy, offer prices, or accept/reject decisions; recommend approving/denying tenants or buyers; tell users what action to take; make decisions for users.
- **Fair Housing:** never discriminate, steer toward/away from neighborhoods, rank by protected class, or express protected-class preferences.
- **Steering prevention:** avoid "best/safest neighborhood," "perfect for families," "good ___ area," "ideal for retirees," "young professionals live here." Use objective data only (nearby parks, restaurants, shopping, transit, commute, amenities, disclosed listing info).
- **Neutral language:** prefer "according to the listing," "based on the information provided," "the seller disclosed," "the landlord indicated," "the listing includes." Avoid "should, must, recommend, ideal, perfect, safest, best, guaranteed."
- **Data sources:** answer only from structured fields, user-provided listing info, the KB, Property DNA, Location DNA, Match, and approved analysis. Never fabricate or speculate; if data is unavailable, state clearly that it has not been provided.

**Enforcement placement (for the later implementation phase, not this round):** these rules belong in the Ask AI system prompt / guardrail layer (e.g. `app/Services/AskAi/*` adapter + prompt templates), not just in question wording. Every `[Insight]` prompt below has been screened against the prohibited themes.

---

# PART F — Per-knowledge-base specification (Phase 7)

> **R2 note:** Several entries below are revised or removed by the Phase 0 Compliance Review. Where an entry is listed in **Part E.2 (R2 Revisions)**, the Part E.2 wording/disposition is final and supersedes the text in this part.

> Source/sufficiency conventions:
> `KB` = creator free-text answer (`listing_ai_faq`) → **Sufficiency: Conditional** — optional; AI states if not provided.
> `Field` = native structured listing field → **Sufficiency: Yes**.
> `PropDNA` / `LocDNA` / `BuyerDNA` / `TenantDNA` / `Match` / `Desc` = platform-generated → **Sufficiency: Yes when the pipeline has run; AI states if not yet generated.**

---

## F.1 SELLER — audience: buyers / buyers' agents

### Removed from suggested (answer still available via Ask AI)
| Key | Reason |
|---|---|
| `parking_arrangements` | Native garage/carport spaces + parking displayed |
| `hoa_community_highlights` | Native HOA fee/amenities/restrictions displayed |
| `neighborhood_restrictions` | Overlaps native HOA restrictions (folded into "known disclosures") |
| `move_in_ready_status` | Overlaps native condition + renovations/defects |
| `annual_net_operating_income`, `current_cap_rate`, `current_occupancy_rate` | Native Financial Details (NOI/cap/occupancy) |
| `annual_business_revenue`, `annual_net_profit`, `business_employee_count`, `inventory_equipment_included`, `business_lease_status` | Native business financials/lease |
| `land_utilities_availability` | Native utility-availability-to-site |

### Group: `universal` (all 5 property types)
- **Why is the owner selling the property?** — REWORD `seller_motivation_for_selling` · `[Common]` · Source: KB · ⚖︎ Optional, factual context only.
- **What is included in the sale, and is anything excluded?** — KEEP `items_excluded_from_sale` · `[Common]` · KB + Field (inclusions/exclusions).
- **Is any furniture or staging negotiable?** — KEEP `furniture_negotiability` · `[Common]` · KB. ⚖︎ Factual disclosure of what's available; AI must not advise negotiation.
- **How flexible is the timing for closing or possession?** — REWORD `closing_timeline_flexibility` (flexibility nuance only) · `[Common]` · KB + Field (target close).
- **Would the owner consider a short post-closing leaseback?** — KEEP `seller_leaseback_option` · `[Common]` · KB. ⚖︎ Factual stance only.
- **Are there any known issues or disclosures the owner has shared?** — REWORD `known_defects_issues` (generalized) · `[Common]` · KB.
- **Are there planned developments, road projects, or zoning changes nearby?** — KEEP `planned_nearby_development` · `[Common]` · KB + LocDNA.
- **Is the property being sold as-is, or is the owner open to repairs based on inspection?** — KEEP `as_is_condition` · `[Common]` · KB. ⚖︎ Factual disclosure of stance; AI must not recommend repair/credit strategy.
- **Has the owner indicated openness to concessions or credits?** — KEEP `seller_concessions_offered` · `[Common]` · KB. ⚖︎ Factual disclosure only; no negotiation strategy.
- **What features make this property stand out?** — REWORD `unique_selling_points` · `[Insight]` · PropDNA + Highlights + Desc.
- **What location features are nearby?** — REWORD `nearby_amenities_description` · `[Insight]` · LocDNA (POIs/parks/dining/transit/commute). ⚖︎ Objective only; no steering.
- **What lifestyle does this property appear to support?** — NEW · `[Insight]` · PropDNA + LocDNA. ⚖︎ Neutral; describe by features/use, never protected classes.
- **What property information has been disclosed?** — NEW · `[Insight]` · Field + KB summary.
- **What type of buyer might this property suit, based on its features?** — NEW · `[Insight]` · PropDNA + Match. ⚖︎ Describe by needs/use only; never protected classes; no "perfect for ___."

### Group: `residential` (Residential Property)
- **How old is the roof, and what condition is it in?** — KEEP `roof_age_and_condition` · `[Common]` · KB.
- **How old is the HVAC system, and when was it last serviced?** — KEEP `hvac_system_age` · `[Common]` · KB.
- **How old is the water heater, and what type is it?** — KEEP `water_heater_age_type` · `[Common]` · KB.
- **What renovations or upgrades have been made, and when?** — KEEP `recent_renovations_list` · `[Common]` · KB.
- **Were renovations completed with proper permits?** — KEEP `permits_for_renovations` · `[Common]` · KB.
- **Are there any known foundation or structural issues?** — REWORD `foundation_type_and_issues` (issues, not type) · `[Common]` · KB.
- **Any pest or termite history, and how was it resolved?** — KEEP `pest_termite_history` · `[Common]` · KB.
- **Has the property ever flooded or had water damage?** — KEEP `flood_damage_history` · `[Common]` · KB (distinct from native flood zone).
- **Any mold history, and how was it addressed?** — KEEP `mold_issues_history` · `[Common]` · KB.
- **What are the average monthly utility costs?** — KEEP `average_utility_costs` · `[Common]` · KB.
- **Which internet/utility providers serve the property, and what speeds are available?** — REWORD `internet_utility_providers` (+ speed) · `[Common]` · KB.
- **What storage options are available?** — KEEP `storage_space_available` · `[Common]` · KB.
- **How is the natural light, and which way does the home face?** — KEEP `natural_light_orientation` · `[Common]` · KB.
- **How would you describe the neighborhood feel?** — KEEP `neighborhood_character` · `[Common]` · KB. ⚖︎ Objective/neutral; no protected-class or "good area" language.
- **Are there notable traffic or noise considerations nearby?** — KEEP `traffic_or_noise_concerns` · `[Common]` · KB.
- **What are typical commute options and travel times?** — KEEP `commute_options_access` · `[Common]` · KB + LocDNA.
- **What will the owner miss most about the home?** — KEEP `seller_favorite_features` · `[Common]` · KB (optional lifestyle color).
- **Which school districts serve this property?** — NEW · `[Common]` · LocDNA (school districts). ⚖︎ Present objective district/boundary info only — no quality ranking, no "good schools," no protected-class framing.
- **Has the owner disclosed any insurance claims history for the property?** — NEW · `[Common]` · KB. ⚖︎ Factual disclosure; not insurance advice.
- **Are solar panels present, and are they owned or leased?** — NEW · `[Common]` · KB + Field.
- **Are there smart-home or EV-charging features?** — NEW · `[Common]` · KB.

### Group: `income` (Income Property) — ⚖︎ all entries factual; AI must not give investment/ROI/financial advice
- **What expenses are included in the operating costs?** — REWORD `annual_operating_expenses_detail` (breakdown beyond native total) · `[Common]` · KB.
- **What are the current lease terms and escalations for existing tenants?** — REWORD `existing_tenant_lease_terms` (beyond native type/exp) · `[Common]` · KB.
- **What recent improvements or income changes has the owner disclosed?** — REWORD `value_add_opportunities` (neutral, factual) · `[Common]` · KB.
- **What has the owner disclosed about tenant payment history?** — NEW · `[Common]` · KB.
- **Is there any deferred maintenance or near-term capital work disclosed?** — NEW · `[Common]` · KB.
- **Is the property professionally managed?** — NEW · `[Common]` · KB.
- **How are utilities split between owner and tenants?** — NEW · `[Common]` · KB + Field (tenant/owner pays).
- **How old are the roof and major building systems?** — `shared` from residential (roof/HVAC), reworded for a building · `[Common]` · KB.
- **What features make this property stand out to operators?** — `[Insight]` · PropDNA + Desc. ⚖︎ Neutral; no investment framing.
- **What has been disclosed about this property's operations?** — `[Insight]` · Field + KB summary.
- **What location features are nearby?** — `[Insight]` · LocDNA.

### Group: `commercial` (Commercial Property)
- **What is the zoning, and what uses does it permit?** — REWORD from `land_zoning_permitted_uses` concept · `[Common]` · Field (zoning) + KB. ⚖︎ Factual interpretation of disclosed zoning; not legal advice.
- **What are the building systems (HVAC, electrical capacity)?** — NEW · `[Common]` · KB + Field (electrical/ceiling height).
- **What is the clear/ceiling height?** — NEW · `[Common]` · Field.
- **Is the space ADA accessible?** — NEW · `[Common]` · KB.
- **How many restrooms are there?** — NEW · `[Common]` · KB/Field.
- **What parking, access, and loading are available?** — NEW · `[Common]` · KB + Field.
- **How old are the roof and major systems?** — `shared` (roof/HVAC) · `[Common]` · KB.
- **What recent improvements have been made?** — REWORD `recent_renovations_list` for commercial · `[Common]` · KB.
- **What features make this space stand out?** — `[Insight]` · PropDNA + Desc.
- **What location features are nearby?** — `[Insight]` · LocDNA.
- **What business uses might this space accommodate, based on disclosed zoning?** — `[Insight]` · Field (zoning) + KB. ⚖︎ Factual; not legal/business advice.

### Group: `business` (Business Opportunity)
- **Why is the business being sold?** — REWORD `business_reason_for_selling` · `[Common]` · KB.
- **How much training/transition support will the seller provide?** — KEEP `seller_training_transition` · `[Common]` · KB.
- **Will existing staff stay on after the sale?** — REWORD (the non-dup nuance of `business_employee_count`) · `[Common]` · KB.
- **How concentrated is the customer base?** — NEW · `[Common]` · KB.
- **What vendor or supplier contracts are in place?** — NEW · `[Common]` · KB.
- **Are licenses, permits, or franchise rights transferable?** — NEW · `[Common]` · KB. ⚖︎ Factual; not legal advice.
- **What is the business's online presence and review profile?** — NEW · `[Common]` · KB.
- **Is the business seasonal?** — NEW · `[Common]` · KB.
- **How involved is the current owner day-to-day?** — NEW · `[Common]` · KB.
- **What information has been disclosed about this business?** — `[Insight]` · Field + KB summary. ⚖︎ No financial/investment advice.
- **What does the sale appear to include?** — `[Insight]` · Field (sale_includes/FF&E/inventory).

### Group: `land` (Vacant Land) — ⚖︎ factual/objective; AI must not give engineering or due-diligence advice
- **Are there known soil, perc, or topography considerations?** — KEEP `land_soil_and_topography` · `[Common]` · KB.
- **Is a current survey available, and has the land been cleared/improved?** — KEEP `land_survey_available` · `[Common]` · KB.
- **What uses are permitted under current zoning?** — REWORD `land_zoning_permitted_uses` (permitted-use interpretation; zoning is native) · `[Common]` · Field + KB.
- **Are there deed restrictions beyond recorded easements?** — REWORD `land_development_restrictions` (non-easement) · `[Common]` · KB.
- **Are there access limitations or shared-road maintenance obligations?** — REWORD `land_access_and_road` (limitations nuance; frontage is native) · `[Common]` · KB.
- **Are there wetlands or environmental designations on the parcel?** — NEW · `[Common]` · KB + LocDNA (flood). ⚖︎ Objective; defer to professionals.
- **What was the land's prior use?** — NEW · `[Common]` · KB.
- **What location features are nearby?** — `[Insight]` · LocDNA.
- **What objective site characteristics has the listing disclosed?** — `[Insight]` · Field + KB summary.

---

## F.2 BUYER — audience: sellers / listing agents (about the buyer)

> No subject property: `[Insight]` prompts educate about the buyer profile/criteria (BuyerDNA + criteria + Match). ⚖︎ Surfacing a buyer's disclosed stance is factual; AI must never coach negotiation or recommend offer terms.

### Removed from suggested (answer still available via Ask AI)
| Key | Reason |
|---|---|
| `buyer_commute_requirements` | Native commute ZIP/mode/max-minutes |
| `buyer_hoa_acceptable` | Native HOA acceptance + max fee |
| `buyer_view_preference` | Native view preference |
| `buyer_renovation_tolerance` | Native acceptable conditions |
| `buyer_must_have_features` | Native non-negotiable amenities |
| `buyer_long_term_goals` | Native purchase purpose |
| `buyer_simultaneous_close` | Native home-sale contingency |
| `buyer_seller_concessions` | Native seller-contribution term + negotiation risk |
| `buyer_deal_breakers` | Overlaps native non-negotiable amenities |
| `buyer_wfh_needs` | Low value to seller audience |
| `com_cap_rate_target` | Native Min Cap Rate |
| `com_investment_type` | Native purchase purpose |
| `com_due_diligence_period` | Native DD contingency + period |
| `biz_type_seeking` | Native business_type_selected |
| `biz_profit_required` | Native min annual net income |

### Group: `universal` (all 5 property types)
- **What's driving the buyer's search right now?** — KEEP `buyer_motivation` · `[Common]` · KB.
- **What's the buyer's current living/ownership situation?** — KEEP `buyer_current_situation` · `[Common]` · KB.
- **How flexible is the buyer on timing or terms if the right property comes along?** — KEEP `buyer_flexibility` · `[Common]` · KB. ⚖︎ Factual; no negotiation coaching.
- **What's the buyer's biggest concern or hesitation?** — KEEP `buyer_biggest_concern` · `[Common]` · KB.
- **Is the buyer relocating or making decisions remotely?** — KEEP `buyer_relocation` · `[Common]` · KB.
- **Has the buyer had prior offers that didn't work out?** — KEEP `buyer_lost_deal` · `[Common]` · KB.
- **Would the buyer allow a short seller leaseback after closing?** — KEEP `buyer_leaseback` · `[Common]` · KB. ⚖︎ Factual stance only.
- **What type of property fits this buyer's stated criteria?** — NEW · `[Insight]` · BuyerDNA + criteria + Match. ⚖︎ Describe by needs/use; neutral.
- **What has this buyer disclosed about their needs and timeline?** — NEW · `[Insight]` · Field + KB summary.
- **What location features matter to this buyer?** — NEW · `[Insight]` · criteria (commute/preferred locations) + LocDNA. ⚖︎ Objective; no steering.

### Group: `residential` (Residential Property)
- **What neighborhood feel is the buyer looking for?** — KEEP `buyer_neighborhood_preferences` · `[Common]` · KB. ⚖︎ Neutral; no protected-class/"good area" language.
- **Is a specific school district a requirement or preference?** — KEEP `buyer_school_district` · `[Common]` · KB. ⚖︎ Objective requirement only; no quality ranking/steering.
- **How sensitive is the buyer to noise?** — KEEP `buyer_noise_tolerance` · `[Common]` · KB.
- **How familiar is the buyer with the area?** — KEEP `buyer_area_familiarity` · `[Common]` · KB.
- **Is the buyer open to off-market/pocket listings?** — KEEP `buyer_prefers_off_market` · `[Common]` · KB.
- **Does the buyer prefer a particular architectural style or era?** — KEEP `buyer_property_style` · `[Common]` · KB.
- **What's on the buyer's wish list (nice-to-haves)?** — KEEP `buyer_nice_to_have` · `[Common]` · KB (distinct from native non-negotiables).
- **Does the buyer need any accessibility features?** — KEEP `buyer_accessibility` · `[Common]` · KB.
- **What are the buyer's privacy preferences?** — KEEP `buyer_privacy_requirements` · `[Common]` · KB.
- **How does the buyer envision using the home?** — KEEP `buyer_lifestyle_goals` · `[Common]` · KB.
- **How important is outdoor space to the buyer?** — KEEP `buyer_outdoor_space` · `[Common]` · KB.

### Group: `income` (Income Property) — ⚖︎ factual; no investment advice
- **What's the buyer's intended use for the property?** — KEEP `com_property_use` · `[Common]` · KB.
- **What minimum occupancy does the buyer require at purchase?** — KEEP `com_occupancy_rate` · `[Common]` · KB.
- **What lease structure does the buyer prefer (NNN/gross/etc.)?** — KEEP `com_lease_terms` · `[Common]` · KB.
- **Is the buyer completing a 1031 exchange with a timing requirement?** — KEEP `com_1031_exchange` · `[Common]` · KB. ⚖︎ Factual timing disclosure; not tax advice.
- **Will the buyer require environmental studies (Phase I/II)?** — KEEP `com_environmental_concerns` · `[Common]` · KB.
- **What type of income property fits this buyer's criteria?** — NEW · `[Insight]` · BuyerDNA + criteria. ⚖︎ Neutral; no investment framing.

### Group: `commercial` (Commercial Property)
- Same five `[Common]` entries as `income` (`com_property_use`, `com_occupancy_rate`, `com_lease_terms`, `com_1031_exchange`, `com_environmental_concerns`).
- **What type of commercial space fits this buyer's intended use?** — NEW · `[Insight]` · BuyerDNA + criteria.

### Group: `business` (Business Opportunity) — Business renders this group only (F6)
- **What minimum revenue does the buyer require?** — KEEP `biz_revenue_required` · `[Common]` · KB.
- **How much seller training/transition does the buyer expect?** — KEEP `biz_training_expected` · `[Common]` · KB.
- **Does the buyer want existing staff retained?** — KEEP `biz_staff_included` · `[Common]` · KB.
- **Does the buyer require a non-compete from the seller?** — KEEP `biz_non_compete` · `[Common]` · KB. ⚖︎ Factual requirement; not legal advice.
- **Is the buyer using SBA or seller financing?** — KEEP `biz_sba_financing` · `[Common]` · KB. ⚖︎ Factual; not lending advice.
- **What type of business is this buyer seeking?** — `[Insight]` · BuyerDNA + criteria (business_type_selected).

### Group: `land` (Vacant Land) — strongest existing addon; all kept
- **What's the buyer's intended use for the land?** — KEEP `land_intended_use` · `[Common]` · KB.
- **What zoning classification does the buyer require?** — KEEP `land_zoning_required` · `[Common]` · KB.
- **What utilities does the buyer need available?** — KEEP `land_utilities_needed` · `[Common]` · KB.
- **Will the buyer require soil/perc/environmental testing?** — KEEP `land_soil_testing` · `[Common]` · KB. ⚖︎ Factual; not engineering advice.
- **What's the buyer's build/development timeline?** — KEEP `land_build_timeline` · `[Common]` · KB.
- **What road access or easement does the buyer require?** — KEEP `land_access_requirements` · `[Common]` · KB.
- **Does the buyer have flood/elevation/topography requirements?** — KEEP `land_topography` · `[Common]` · KB.
- **What land characteristics matter most to this buyer?** — NEW · `[Insight]` · BuyerDNA + criteria.

---

## F.3 LANDLORD — audience: tenants / tenant agents

### Removed from suggested (answer still available via Ask AI)
| Key | Reason |
|---|---|
| `subletting_allowed` | Native Subletting Policy |
| `smoking_policy` | Native Smoking Policy |
| `lease_renewal_process` | Native Renewal Option |
| `preferred_tenant_qualities` | Native Desired Tenant Criteria + Applicant Requirements |
| `emergency_maintenance_available` | Folded into maintenance-process question |
| `bicycle_storage_available` | Niche/low-value |
| `commercial_cam_charges` | Native CAM/NNN |
| `commercial_lease_structure_type` | Native Commercial Lease Type |
| `commercial_signage_rights` | Native Signage Rights |
| `commercial_landlord_maintenance_responsibilities` | Native Landlord Maintenance |
| `commercial_parking_ratio` | Native Commercial Parking Terms |

### Group: `universal` (both rental types)
- **How are maintenance requests handled, including emergencies and response times?** — REWORD `maintenance_request_response_time` (absorbs emergency) · `[Common]` · KB.
- **Are there planned renovations or construction that could affect tenants?** — KEEP `planned_renovations` · `[Common]` · KB.
- **How much notice is required to vacate at lease end?** — KEEP `notice_to_vacate_required` · `[Common]` · KB.
- **Is a lease-to-own or rent-credit arrangement possible?** — KEEP `lease_to_own_option` · `[Common]` · KB. ⚖︎ Factual disclosure of stance only.
- **What location features are nearby?** — REWORD `nearby_amenities` · `[Insight]` · LocDNA. ⚖︎ Objective; no steering.
- **What makes this rental stand out?** — REWORD `what_makes_property_unique` · `[Insight]` · PropDNA + Desc.
- **What lifestyle does this rental appear to support?** — NEW · `[Insight]` · PropDNA + LocDNA. ⚖︎ Neutral; no protected-class framing.
- **What has been disclosed about this rental?** — NEW · `[Insight]` · Field + KB summary.

### Group: `residential` (Residential Rental)
- **What heating and cooling system does the property have?** — KEEP `heating_cooling_system` · `[Common]` · KB.
- **Is there in-unit or shared laundry?** — KEEP `laundry_situation` · `[Common]` · KB.
- **Is dedicated storage included?** — KEEP `storage_area_included` · `[Common]` · KB.
- **Which internet providers and speeds are available?** — REWORD `internet_providers` (+ speed) · `[Common]` · KB.
- **What security features does the property have?** — KEEP `security_features` · `[Common]` · KB.
- **How would you describe the neighborhood feel?** — KEEP `neighborhood_character` · `[Common]` · KB. ⚖︎ Neutral; no "who lives here"/protected-class framing — reword placeholder to remove demographic phrasing.
- **What's the noise level like?** — KEEP `noise_levels` · `[Common]` · KB.
- **How close is public transit?** — KEEP `proximity_to_public_transit` · `[Common]` · KB.
- **Is the unit furnished, unfurnished, or negotiable?** — KEEP `furnished_or_unfurnished` · `[Common]` · KB (native gap).
- **Are short-term rentals permitted?** — KEEP `short_term_rentals_allowed` · `[Common]` · KB.
- **Is EV charging available or installable?** — KEEP `ev_charging_available` · `[Common]` · KB.
- **Any pest or mold history, and how was it resolved?** — KEEP `pest_or_mold_history` · `[Common]` · KB.
- **How are utilities metered and billed?** — REWORD `utilities_individually_metered` (billing nuance) · `[Common]` · KB + Field.
- **Is renter's insurance required, and at what coverage?** — KEEP `renters_insurance_required` · `[Common]` · KB. ⚖︎ Factual requirement; not insurance advice.
- **What do past tenants say about living here?** — KEEP `previous_tenant_feedback` · `[Common]` · KB. ⚖︎ Neutral testimonials; no protected-class framing.
- **Is guest/visitor parking available?** — REWORD `guest_parking` (guest nuance beyond native counts) · `[Common]` · KB.
- **What's the application process, fee, and timeline?** — NEW · `[Common]` · KB.
- **Who is responsible for lawn/landscaping?** — NEW · `[Common]` · KB.
- **What are typical average utility costs?** — NEW · `[Common]` · KB (complements native estimates).
- **Which school districts serve this rental?** — NEW · `[Common]` · LocDNA. ⚖︎ Objective district info only; no ranking/steering.

### Group: `commercial` (Commercial Rental)
- **Is there a loading dock or freight elevator?** — KEEP `commercial_loading_dock_freight_elevator` · `[Common]` · KB.
- **What is the electrical capacity (amperage/voltage/3-phase)?** — KEEP `commercial_electrical_capacity` · `[Common]` · KB.
- **Are exclusivity rights available?** — KEEP `commercial_exclusivity_rights` · `[Common]` · KB. ⚖︎ Factual; not legal advice.
- **Is there an expansion option or right of first refusal?** — KEEP `commercial_expansion_option_rofr` · `[Common]` · KB.
- **What are the building/suite access hours?** — KEEP `commercial_building_access_hours` · `[Common]` · KB.
- **What build-out or tenant-improvement support is available beyond what's listed?** — REWORD (merge `commercial_tenant_improvement_allowance` + `commercial_buildout_flexibility`) · `[Common]` · KB + Field. ⚖︎ Factual; no negotiation coaching.
- **Is the space ADA accessible?** — NEW · `[Common]` · KB.
- **What is the HVAC type, zoning of zones, and after-hours HVAC availability?** — NEW · `[Common]` · KB.
- **How many restrooms are there?** — NEW · `[Common]` · KB/Field.
- **What is the co-tenancy / anchor-tenant situation in the building?** — NEW · `[Common]` · KB.
- **What uses does the zoning permit for this space?** — REWORD from native permitted_use (interpretation) · `[Common]` · Field + KB. ⚖︎ Factual; not legal advice.
- **What business uses might this space accommodate, based on disclosed zoning?** — `[Insight]` · Field + KB. ⚖︎ Factual.

---

## F.4 TENANT — audience: landlords / leasing agents (about the applicant)

> No subject property: `[Insight]` prompts educate about the applicant/criteria (TenantDNA + pre-screening + criteria + Match). ⚖︎ AI must never recommend approving/denying a tenant; only surface disclosed factual info; Fair Housing — never reference protected classes.

### Removed from suggested (answer still available via Ask AI)
| Key | Reason |
|---|---|
| `faq_q7` (pet breed/size) | Native pets + breed + weight (pre-screening) |
| `faq_q5` (top amenity) | Native required amenities |
| `faq_q6` (outdoor space) | Native pool/amenities |
| `faq_q11` (move-in firmness) | Native move-in earliest/latest |
| `faq_q16` (short/long-term) | Native desired lease length |
| `faq_q1` (WFH), `faq_q3` (neighborhood vibe), `faq_q19` (communication preference) | Low value to a landlord screening decision |
| `faq_q21` (business type) | Native Intended Business Use |
| `faq_q24` (signage), `faq_q25` (buildout), `faq_q27` (lease flexibility) | Native commercial criteria (signage/buildout/lease-type+CAM) |

### Group: `universal` (both rental types)
- **What's driving the applicant's rental search?** — KEEP `faq_q14` · `[Common]` · KB.
- **What's the applicant's biggest concern in this search?** — KEEP `faq_q20` · `[Common]` · KB.
- **Is there any chance the applicant would need to break the lease early?** — KEEP `faq_q12` · `[Common]` · KB.
- **Does the applicant have landlord or employer references available?** — KEEP `faq_q17` · `[Common]` · KB.
- **What has this applicant disclosed about their rental background?** — NEW · `[Insight]` · TenantDNA + pre-screening summary. ⚖︎ Factual; never an approval recommendation; no protected classes.
- **What type of rental fits this applicant's stated needs?** — NEW · `[Insight]` · TenantDNA + criteria + Match. ⚖︎ Neutral.

### Group: `residential` (Residential Rental)
- **What is the source and stability of the applicant's income?** — KEEP `faq_q18` · `[Common]` · KB (native shows amount only). ⚖︎ Factual disclosure; not an approval recommendation.
- **Would the applicant consider a longer lease for a locked-in/reduced rate?** — KEEP `faq_q13` · `[Common]` · KB. ⚖︎ Factual stance; no negotiation coaching.
- **Does the applicant prefer furnished or unfurnished?** — KEEP `faq_q10` · `[Common]` · KB (native gap).
- **Is the applicant willing to pay a pet deposit or pet rent if required?** — KEEP `faq_q8` · `[Common]` · KB.
- **How long was the most recent tenancy, and why is the applicant moving?** — REWORD `faq_q15` · `[Common]` · KB.
- **How flexible is the applicant on lease length?** — REWORD `faq_q9` (flexibility nuance) · `[Common]` · KB.
- **What is the applicant generally looking for in a home?** — REWORD `faq_q2` (single optional lifestyle prompt) · `[Common]` · KB.
- **Is a co-signer or guarantor available if needed?** — NEW · `[Common]` · KB.
- **How soon is the applicant ready to apply and provide documentation?** — NEW · `[Common]` · KB.
- **Has the applicant disclosed any prior rental conduct (late payments, notices)?** — NEW · `[Common]` · KB. ⚖︎ Factual disclosure; never an approval recommendation; no protected-class inference.

### Group: `commercial` (Commercial Rental)
- **Does the applicant expect customer/client foot traffic, and how much?** — KEEP `faq_q22` · `[Common]` · KB.
- **Does the applicant have special equipment or power requirements?** — KEEP `faq_q23` · `[Common]` · KB.
- **What are the applicant's expected hours of operation?** — KEEP `faq_q26` · `[Common]` · KB.
- **What are the applicant's parking needs for staff and customers?** — NEW · `[Common]` · KB.
- **What business use and operating profile has this applicant disclosed?** — NEW · `[Insight]` · TenantDNA + criteria (intended business use). ⚖︎ Factual; neutral.

---

# PART G — Final validation (Phase 8)

Every proposed question above was screened against the Phase 8 checklist. Summary attestation:

| Check | Result |
|---|---|
| Audience appropriate | ✓ — each group is single-audience (Part C); no mixing |
| Property-type appropriate | ✓ — enforced by gating (Part A); `shared` items tagged explicitly |
| No unnecessary duplication of displayed info | ✓ — native-displayed facts removed from suggested (Part B/F), still answerable |
| Supported by available platform data | ✓ — every entry tagged with Source; KB items marked Conditional |
| Answerable by Ask AI | ✓ — Common via KB+fields; Insights via DNA/LocDNA/Match |
| Conversational | ✓ — single-meaning, plain phrasing |
| Valuable to real users | ✓ — weak/niche items removed (audit §6) |
| Educational | ✓ — Insights educate; Common explain disclosures |
| Neutral | ✓ — neutral-language rule applied; ⚖︎ notes added |
| Fair Housing compliant | ✓ — school/neighborhood/audience-fit items flagged neutral-only |
| No steering | ✓ — location items restricted to objective POI/commute/district data |
| No discrimination | ✓ — no protected-class language; "who lives here" phrasing removed |
| No legal advice | ✓ — zoning/non-compete/transferability tagged factual-only |
| No financial advice | ✓ — income/utility/cost items factual-only |
| No investment advice | ✓ — NOI/cap/value-add removed or reframed factual; no ROI insights |
| No negotiation advice | ✓ — as-is/concessions/leaseback/longer-lease tagged factual-stance-only |
| No approval recommendations | ✓ — tenant/buyer items surface disclosures only; explicit ⚖︎ on each |
| No unsupported claims | ✓ — "state if not provided" rule; no fabrication |

### Compliance concerns surfaced for reviewer attention (before implementation)
1. **School-district questions (Seller/Buyer/Landlord residential):** safe only if Ask AI returns objective district/boundary data (Location DNA) and never quality rankings or "good schools." Requires the guardrail rule to be enforced in the prompt layer, not just wording. **Recommend reviewer confirm Location DNA exposes district identity without rating.**
2. **Neighborhood-character / previous-tenant-feedback (Landlord) & neighborhood-preferences (Buyer):** today's placeholders include demographic-flavored examples ("who typically lives here," "mix of families/professionals"). These placeholders must be rewritten to objective phrasing to avoid steering. Flagged for the content pass.
3. **As-is / concessions / leaseback / longer-lease-for-discount / non-compete / SBA / 1031:** retained as **factual disclosures of a stated position**. The risk is Ask AI extrapolating into strategy/legal/tax advice. Mitigation = system-prompt guardrail (Part E). **These should not ship until the guardrail layer is in place.**
4. **Income/Business factual disclosures:** even factual financial disclosures risk the model adding "this looks like a strong return." The investment-advice prohibition must be enforced in the prompt layer.
5. **Tenant applicant questions:** surfacing income source, references, prior conduct is compliant only if Ask AI never frames it as an approve/deny recommendation and never infers protected characteristics. Explicit ⚖︎ on each; enforce in guardrail.

### Recommended implementation sequencing (future rounds, after approval)
1. Land the **Phase 6 guardrail layer** (system prompt + refusal rules) **first** — several questions above are only compliant with it in place.
2. Implement the **Part A architecture** (config schema + blade gating) with key-preserving migration.
3. Apply the **Part F content** (removals, rewords, additions, category tags).
4. Rewrite flagged **placeholders/tooltips** to neutral phrasing (concern #2).
5. Re-run Phase 8 validation on the final config before enabling.

---

# PART E.1 — Phase 0 Compliance Review (COMPLETED)

**Mandatory gate: no production change may proceed until the concerns below are resolved.** This pass reviewed every proposed question, AI Insight, placeholder, tooltip, and *anticipated AI response* against: Fair Housing, steering, discrimination, real-estate licensing, legal/financial/investment/negotiation/tax advice, privacy, unsupported claims, speculation, and consumer protection.

### Standing principle applied
Ask AI may **explain, educate, summarize, clarify, describe, and compare factual on-platform information**. It must **never** tell a user what to do, recommend negotiation/offer/approval/pricing actions, give legal/tax/financial/investment/inspection/appraisal/lending advice, or speculate beyond available data. Every entry was tested against this.

**Purpose reaffirmed (R3):** Ask AI's purpose is to explain, summarize, educate, clarify, answer factual questions, and help users better understand information disclosed within the platform. It must **never replace the user's professional judgment or licensed advisors**, and never tell users what decision to make.

**Ongoing professional-review standard (R3):** every suggested question is reviewed from the perspective of **Buyer, Seller, Landlord, Tenant, Residential Agent, Commercial Broker, Property Manager, Investor, and Typical Consumer**, asking "is this a question real people ask every day?" This persona set is the standing review standard for all future content changes.

### Cross-cutting compliance findings (NEW — discovered in this pass)

**C-A — KB free-text must be sanitized on OUTPUT, not just at question level (highest-priority new finding).**
Many `[Common]` questions are answered from creator free-text (`listing_ai_faq`). A creator can type steering or demographic content ("great family neighborhood," "quiet area, mostly retirees," "perfect for young professionals") or unsupported claims ("best schools in the county"). Question-level neutrality does **not** prevent the AI from repeating that text.
→ **Requirement (refined R3):** the Ask AI guardrail must, **at answer-generation time**, sanitize or decline any portion of the source text that contains:
- discriminatory language
- steering language
- demographic language (about people/who lives somewhere)
- unsupported claims
- offensive language
- legal conclusions
- financial advice
- negotiation advice

The guardrail **sanitizes or declines only the offending portion** while still answering the legitimate parts of the question using approved platform data. It surfaces only objective attributes, regardless of what the KB free-text or property description contains. This is a guardrail-layer requirement, not a wording fix.

**C-B — Tenant-listing Ask AI exposes sensitive applicant data (privacy / consumer-protection / FCRA-adjacent) (critical).**
Tenant `[Common]` items surface income source, references, prior rental conduct, and (via native pre-screening) eviction/felony disclosures. If a tenant listing's Ask AI is queryable by the general public, this exposes sensitive personal information about the applicant to anyone, and framing it as screening output risks consumer-protection / fair-screening exposure.
→ **Requirement (reviewer decision needed):** confirm the audience/access model for Tenant-listing Ask AI. Recommended: restrict sensitive applicant fields (income source, eviction/criminal history, references) to **verified landlord/agent viewers only**, never expose criminal/eviction specifics through AI, and have AI present only what the applicant chose to disclose, attributed and without any approve/deny framing. **Do not ship tenant applicant questions until this access model is confirmed.**

**C-C — Ask AI must never analyze negotiating positions (refined R3).**
Per owner direction, Ask AI must not attempt to identify, evaluate, or describe any party's:
- buyer weaknesses
- seller weaknesses
- negotiation posture
- strategic disadvantage
- leverage

→ **Requirement:** Ask AI **explains disclosed information; it does not evaluate negotiating positions.** Disclosure fields (e.g. seller "why selling," buyer "motivation / current situation / flexibility") remain as plain, optional, factual disclosures that the AI may restate neutrally — but the AI must never frame, infer, or comment on what they mean for negotiation, advantage, or leverage. The previously-discussed "protect the user's leverage" framing is dropped; the rule is simply that the AI does not do negotiating-position analysis for anyone. `buyer_lost_deal` is removed (also unrealistic — see Part H).

**C-D — "Type of buyer/tenant" Insights reframed to be PROPERTY-centered, never people (refined R3).**
The feature is kept, but every such Insight must be framed around the **property/listing**, never around people or demographics. Ask AI must never describe people.
- **Approved phrasings:** "What property features may appeal to different buyers?" · "What uses may this commercial property support?" · "What lifestyle features are highlighted by this property?"
- **Prohibited:** "What type of person would live here?" · "Families usually like…" · "Young professionals…" · "Retirees…" · any demographic profiling.
→ All "type of buyer/tenant" Insights are reworded to property-feature/use framing (see E.2). For Buyer/Tenant listings (no subject property), the equivalent Insight describes the **stated property needs/uses** the buyer/tenant disclosed — never the person.

**C-E — "Lifestyle" Insight constrained to features/amenities, never people.**
"What lifestyle does this property support?" must describe **layout/features/nearby amenities** (e.g., "an open layout and yard suit entertaining; nearby trails suit outdoor recreation"), never demographic lifestyles ("family lifestyle," "retiree living"). Wording retained, compliance constraint added.

**C-F — Schools: objective facts only (DECIDED, R3).**
Owner decision: **keep, strictly constrained.** Ask AI must **not recommend, rate, or compare** schools, and must not generate any opinion about school quality. If school information is shown at all, it is limited to objective items:
- assigned school name(s), if available
- school district
- publicly available links or references

The keep-vs-remove question is now resolved (keep-constrained). No quality, ranking, or comparison language under any circumstance.

**C-G — Zoning/permitted-use changed from interpretation to restatement (legal-advice risk).**
"What business uses might this space accommodate based on zoning?" is legal interpretation.
→ Reworded to **restate disclosed zoning/permitted-use** only, with a "verify permitted use with the local jurisdiction" disclaimer. See E.2.

**C-H — Financial terms: explain and restate, never advise (refined R3).**
Ask AI **may explain what terms mean** (NOI, CAP rate, NNN, CAM, SBA) and **may explain disclosed values** for a specific listing. It must **never** calculate investment quality, predict returns, recommend investments, benchmark against market, opine on whether a figure is good, or provide financial/tax advice — **unless that capability is intentionally designed and legally reviewed in a future phase.** Any definition is general/educational and paired with "consult a licensed professional." The Phase-5 "compare factual information" allowance is limited to **on-platform, disclosed data**; no external/market comparison.

**C-I — Avoid unsupported superlatives (refined R3).**
Across all questions, Insights, and answers, avoid: **best, safest, perfect, guaranteed, highest quality, ideal** (and "rare," "unbeatable," "better than," and any comparison not backed by on-platform data) — **unless directly quoting disclosed listing information** (attributed as a quote). Restrict claims to listing-sourced, objective features.

**C-J — Persistent educational disclaimer required (consumer protection).**
Ask AI responses (and the Ask AI UI) must carry a standing disclaimer: *educational/informational only; not legal, financial, tax, or professional advice; verify with licensed professionals.* New platform requirement (Part I).

**C-K — "Previous tenant feedback" removed (CONFIRMED, R3).**
Owner-confirmed: Ask AI must **not expose previous tenant opinions or anecdotal feedback** of any kind (hearsay, unverifiable, possible demographic/steering content). The question is **removed**. An optional, strictly **objective and landlord-attributed** retention statement ("According to the landlord, typical tenancy length is…") may remain — but **no opinions, anecdotes, or testimonials**.

---

# PART E.2 — R2 Revisions (these supersede Part F)

| Role · Group | Entry (Part F) | R2 disposition | Final wording / rule |
|---|---|---|---|
| Seller · residential | `seller_favorite_features` ("what will you miss most") | **Remove from suggested** (marketing fluff, not a real buyer question — see H) | Still answerable; not a curated chip |
| Seller · residential | School districts (NEW) | **Keep-constrained** (C-F) | "Which school district is this property assigned to?" → AI returns assigned district/boundary only + referral to official sources; no ratings |
| Seller · residential / Landlord · residential / Buyer · residential | `neighborhood_character` / `neighborhood_preferences` ("feel/vibe/who lives here") | **Reword to objective setting** (C-A) | "What can you share about the area's setting and nearby amenities?" — AI answers with objective attributes only; demographic descriptors withheld |
| Seller · residential | `internet_utility_providers` insurance/solar/smart-home NEW items | Keep | Unchanged; factual |
| Seller · income / business | All financial-term entries | Add C-H rule | Define term generically + restate disclosed figure + "consult a professional"; no benchmarking/computation |
| Seller · commercial / Landlord · commercial | "What business uses might this space accommodate…" `[Insight]` | **Reword (C-G)** | "What does the listing state about the zoning and permitted uses?" + "verify permitted use with the local jurisdiction" |
| Seller · universal | "What type of buyer might this property suit?" `[Insight]` | **Reword (C-D)** | "What property features may appeal to different buyers?" (property-centered; never describes people) |
| Seller · universal | "What lifestyle does this property appear to support?" `[Insight]` | Keep + C-E constraint | Feature/amenity-based only |
| Seller · universal | "What features make this property stand out?" `[Insight]` | Keep + C-I constraint | No superlatives/comparatives |
| Buyer · universal | `buyer_lost_deal` ("prior offers that didn't work out") | **Remove (C-C + low realism, see H)** | Removed entirely from suggested |
| Buyer · universal | "What type of property fits this buyer's criteria?" `[Insight]` | **Reword (C-D)** | "What property needs and uses has this buyer described?" |
| Buyer · all | Disclosure items (`buyer_motivation`, `buyer_biggest_concern`, `buyer_current_situation`, `buyer_flexibility`) | Keep as optional factual disclosures (C-C R3) | AI restates factually only; performs **no** negotiating-position / leverage / weakness analysis for any party |
| Buyer/Seller · income/commercial | 1031 / SBA / NOI / cap / NNN questions | Add C-H rule | Same as above |
| Landlord · universal | "What makes this rental stand out?" `[Insight]` | Keep + C-I constraint | No superlatives |
| Landlord · residential | `previous_tenant_feedback` | **Remove (C-K)**; optional objective replacement | NEW optional: "According to the landlord, what is the typical length of tenancy?" — landlord-attributed, objective, no demographics |
| Landlord · residential | School districts (NEW) | Keep-constrained (C-F) | Same rule as Seller school item |
| Landlord · residential | `neighborhood_character` placeholder | **Rewrite placeholder (C-A)** | Remove "who typically lives here / mix of families and professionals"; use objective examples (e.g., "near a park; two blocks from Main Street shops") |
| Tenant · all | Sensitive applicant items (income source, references, prior conduct; native eviction/felony) | **Gate pending C-B**; no approve/deny framing | Do not ship until access model confirmed |
| Tenant · residential | `faq_q2` reworded "what the applicant is looking for in a home" | **Remove (low landlord relevance — see H)** | Landlords screen; they don't survey tenant taste |
| Tenant · `[Insight]` | "What type of rental fits this tenant's needs?" | **Reword (C-D)** | "What rental needs and uses has the applicant described?" |
| ALL roles | Every AI response | Add C-J disclaimer + C-A output sanitization | Enforced in guardrail layer |
| ALL roles | All placeholders/tooltips | **Audit & rewrite (C-A)** | Remove demographic/subjective example phrasing platform-wide (see Part I.4) |

---

# PART H — Final Real-World Review (COMPLETED)

Each KB was re-read as the person who would actually use it. Verdict = does each question feel like something a real professional/consumer asks every day?

| Persona | Reviewing | Verdict & issues found |
|---|---|---|
| **Home Seller / Listing Agent** | Seller·Residential answers (buyer questions) | Realistic overall. **Issue:** `seller_favorite_features` ("what you'll miss") is marketing color, not a buyer question → removed from suggested (E.2). Buyers also ask price/negotiability — correctly excluded (compliance) and AI declines strategy. |
| **Home Buyer / Buyer's Agent** | Buyer·Residential answers (seller/agent questions) | Mostly realistic (pre-approval, timeline, flexibility, why buying). **Issue:** `buyer_lost_deal` is both low-realism for this audience and strategically harmful → removed (E.2). |
| **Investor** | Seller·Income / Buyer·Income | Realistic investor questions (expenses, leases, management, capex, tenant payment history). **Constraint:** AI must not opine on returns/cap-rate quality (C-H). |
| **Commercial Broker / Commercial Buyer** | Seller·Commercial / Buyer·Commercial | Strong, realistic set (zoning, clear height, power, HVAC, parking, loading, ADA, restrooms). **Change:** zoning Insight restated, not interpreted (C-G). |
| **Commercial Tenant** | Tenant·Commercial | Realistic (foot traffic, power, hours, parking). Native commercial criteria correctly removed from suggested. |
| **Property Manager / Landlord** | Landlord·Residential (tenant questions) | Realistic (laundry, pets, utilities, application process, furnished, maintenance). **Issue:** `previous_tenant_feedback` removed (C-K). Placeholders rewritten (C-A). |
| **Tenant / Renter** | Tenant·Residential (landlord questions) | Income source, references, break-lease, co-signer, readiness = exactly what landlords ask. **Blocker:** privacy/access model (C-B). **Issue:** `faq_q2` removed (not a screening question). |
| **Commercial Tenant / Business Owner** | Buyer·Business / Seller·Business | Realistic (reason for selling, training, staff, transferability, customer concentration). **Constraint:** no financial/transferability *advice* (C-G/C-H). |
| **Typical Consumer** | All | Wording is plain and conversational; passes. Needs the standing disclaimer (C-J) so consumers don't over-rely. |

**Realism rewrites/removals applied:** `seller_favorite_features` (remove from suggested), `buyer_lost_deal` (remove), `previous_tenant_feedback` (remove/replace), `faq_q2` (remove), buyer/tenant/property "type of person" Insights → "needs/uses" (reword). All reflected in E.2.

**Additional-requirement finding (high-scoring KBs are NOT exempt):** the three highest-scoring KBs each required changes in this pass — Seller·Residential (favorite-features removal, school constraint, neighborhood reword, placeholder rewrite), Landlord·Residential (tenant-feedback removal, neighborhood/placeholder reword, school constraint), Buyer·Residential (`buyer_lost_deal` removal, neighborhood reword, opt-in labeling). No KB passed unchanged.

---

# PART I — New platform requirements (from R2 review)

1. **Output-sanitization guardrail (C-A):** at answer time, Ask AI must sanitize or decline any portion of source text containing discriminatory / steering / demographic / unsupported / offensive language, legal conclusions, or financial/negotiation advice — even when present in KB free-text or the property description — while still answering the legitimate remainder. Objective attributes only.
2. **Tenant Ask AI access-control policy (C-B):** implement per **Part J**. Only information the requesting user is authorized to view may be summarized. **Implementation blocker for all Tenant applicant questions.**
3. **Persistent educational disclaimer (C-J):** standing, visible disclaimer on Ask AI (UI + responses): educational/informational only; not legal, financial, tax, or professional advice; verify with licensed professionals.
4. **Platform-wide placeholder/tooltip rewrite (C-A):** audit every `placeholder`/`tooltip` in all four config files and remove demographic/subjective examples (e.g. "mix of families and professionals," "family-friendly," "retirees and young families"); replace with objective examples. This is in addition to the question-label work.
5. **Schools = objective facts only (C-F, decided):** assigned school name(s) if available, district, public links/references. No recommending, rating, comparing, or quality opinions.
6. **No negotiating-position analysis (C-C):** Ask AI never identifies/evaluates weaknesses, posture, disadvantage, or leverage for any party. Disclosure fields are explained factually only.

---

# PART J — Tenant Ask AI Access-Control Policy (C-B)

**Principle:** Ask AI may summarize **only information the requesting user is authorized to view.** Confidential applicant information must never be exposed to unauthorized users.

### J.1 Current-state finding (grounded in code)
There are two Ask AI entry points with different access models:

| Endpoint | Controller | Current gating | Risk |
|---|---|---|---|
| `POST /ask-ai/listing-question` | `AskAiListingQuestionController` | **Owner-only** — authorizes every request against the listing's `user_id` before running (per its own docblock + `OWNER_TABLES` map incl. `tenant_agent_auctions`). | Low — only the listing owner (the tenant) can query. |
| `POST /ask-ai/ask` (web) and `POST /api/ask-ai/ask` (sanctum) | `AskAiApiController::ask` → `AskAiRunnerV2Service::run()` | **No per-viewer ownership/authorization check** in the controller; web route is `throttle`-only (authenticated **and guest**); the runner is called with just `listing_type` + `listing_id`. | **High** — a non-owner or guest can query a tenant criteria listing; nothing here restricts which fields are summarized. |

→ **This second path is the C-B blocker.** It must enforce viewer authorization and field-level redaction before any tenant applicant question ships.

### J.2 Who may access Tenant Ask AI
| Requester | May query a tenant listing's Ask AI? | Scope |
|---|---|---|
| The tenant who owns the listing | Yes | Full — their own disclosures |
| A verified landlord/leasing agent the tenant has engaged (or who is authorized in-platform) | Yes | Authorized subset (J.3) |
| Any other authenticated user | No applicant data | Only non-confidential listing criteria, if the listing is public |
| Guest / unauthenticated | No applicant data | Public criteria only, if the listing is public; otherwise blocked |

> The exact definition of "verified/authorized landlord" is the **conservative rule adopted in J.6**; the backing code relationship/table is **TBD pending code inspection (J.7)**.

### J.3 What MAY be summarized (to an authorized landlord/agent)
Only what the tenant chose to disclose, attributed and factual: stated lease/move-in preferences, furnishing preference, general income **source** (if disclosed), availability of references, co-signer availability, application readiness, stated rental needs/uses. No opinions, no approve/deny framing.

### J.4 What must NEVER be exposed via Ask AI
- Criminal/eviction specifics (history details, explanations).
- Exact financial figures beyond what the platform already displays natively, and never to unauthorized viewers.
- Contents of references or third-party report data.
- Any data the requesting user is not authorized to view.
- Any approve/deny recommendation or screening conclusion (Fair-screening / consumer-protection).

### J.5 How permissions are enforced (required implementation)
- The runner (`AskAiRunnerV2Service`) must receive the **authenticated requester identity** and an **authorization scope** (owner / authorized-landlord / other / guest) resolved from the listing's ownership + the in-platform relationship.
- The knowledge-snapshot builder must **redact** confidential fields per scope **before** they reach the model (defense at the data layer, not just the prompt).
- Default-deny: if scope cannot be established, treat as guest (public criteria only).
- The same redaction applies to **all** channels (`web, sms, messenger, whatsapp, mobile, crm`) routed through `AskAiApiController::ask`.

### J.6 Conservative authorization rule (ADOPTED — owner direction)
Use the **most restrictive** rule for now. Sensitive tenant/applicant information is available **only** to:
1. **The tenant listing owner** (the tenant themselves).
2. **An authorized landlord/agent — only after an accepted/active in-platform relationship tied to that specific tenant listing exists.**

Hard rules:
- **Never** expose sensitive tenant/applicant information through the public `POST /ask-ai/ask` path.
- **Default to redacted.** Until the authorization relationship is fully confirmed in code, sensitive fields are withheld and Ask AI returns **only non-sensitive public listing information**.
- If none of the qualifying relationships below can be **confidently verified**, Ask AI returns only non-sensitive public listing information.

For now, an "authorized landlord/agent" = someone with a **verified platform relationship to the tenant listing**, such as:
- accepted bid
- accepted match
- active engagement
- approved conversation/application relationship
- signed/confirmed representation relationship

This is a **default-deny / fail-closed** policy: ambiguity ⇒ redact.

### J.7 Candidate code relationships/tables to back the check (TBD — pending code inspection)
The exact table(s)/model(s) that authoritatively express each qualifying relationship are **TBD and must be confirmed in code before implementation**. Candidates identified so far:

| Qualifying relationship | Candidate table(s) / model(s) | Acceptance/active signal observed | Status |
|---|---|---|---|
| Tenant listing identity (the subject) | `tenant_agent_auctions` (`TenantAgentAuction`) — per AskAi `OWNER_TABLES` map; **also** `tenant_criteria_auctions` (`TenantCriteriaAuction`) and `HireTenantAgentAuction` referenced by the Tenant Offer Listing component | listing `user_id` = owner | **TBD** — confirm which model is the canonical "Tenant Offer Listing" |
| Accepted bid | `tenant_agent_auction_bids` (`TenantAgentAuctionBid`); `tenant_criteria_auction_bids` (`TenantCriteriaAuctionBid`) | `accepted` / `is_accepted` + `accepted_date` columns | **TBD** — confirm bidder→landlord identity column + which bid table applies |
| Accepted counter / finalized deal | `tenant_counter_bidding` (`accepted`, `accepted_date`); `tenant_counter_terms` (`accepted_date`); `accepted_bid_summaries` (`accepted_bid_id`, `accepted_counter_id`) | acceptance columns present | **TBD** — confirm linkage to listing + counterparty |
| Accepted match | `listing_compatibility_scores` (compatibility/match scores); `*BidMatchScoreHelper` (computed) | scores are **computed**, not an "accepted" record — may need an explicit accepted-match flag | **TBD** — verify a stored *accepted* match exists, else this relationship type is unavailable |
| Active engagement / approved conversation | `AuctionChat`, `AuctionChatUser`, `AuctionChatToken`, `AuctionChatUnread`; `AgentAiChatLead` / `AgentAiChatSession`; `HireAgentLead` (`hire_agent_leads`) | chat/lead participation records | **TBD** — confirm which represents an *approved* (not merely initiated) relationship |
| Signed/confirmed representation | likely a hire-agent acceptance (`HireTenantAgentAuction` + its accepted bid) or a representation/engagement record | acceptance columns on hire-agent bids | **TBD** — confirm representation is modeled distinctly from a listing bid |

**Notes for the code-inspection pass:**
- The authorization resolver must map **the requesting user** to the **counterparty identity** on the chosen relationship record (e.g. the bid's landlord/agent `user_id`), not merely confirm a record exists.
- "Match" via `*BidMatchScoreHelper` / `listing_compatibility_scores` appears **computed**; unless a stored *accepted* match exists, "accepted match" may not be enforceable and should be dropped from the allowed set rather than approximated.
- Until each chosen table + counterparty column is verified, the resolver returns scope = guest ⇒ redacted.

> The same viewer-authorization + redaction model should be applied to any **Buyer**-listing fields deemed sensitive, by the same mechanism.

---

# PART G.2 — Expanded validation (adds to Part G)

| Additional check (R2) | Result |
|---|---|
| Privacy of personal/sensitive data | ⚠︎ **Open** — conservative fail-closed policy adopted (Part J.6: default-redacted; never via public `/ask-ai/ask`). Enforcement (viewer-auth + redaction) must be built and the backing relationship table chosen (Part J.7, all TBD) before ship |
| No negotiating-position / leverage analysis | ✓ — C-C; AI explains disclosures only |
| Schools = objective facts only | ✓ — C-F decided (names/district/links; no ratings) |
| Consumer protection (over-reliance) | ✓ once C-J disclaimer is implemented |
| No speculation beyond available data | ✓ — "state if not provided"; no inference rules in guardrail |
| Comparison limited to on-platform factual data | ✓ — C-H bars external/market benchmarking |
| Output-level Fair Housing sanitization | ⚠︎ **Open** — requires C-A guardrail before ship |
| Realism (real-world persona pass) | ✓ — H pass complete; non-realistic items removed |
| High-scoring KBs reviewed to same standard | ✓ — all required changes (see H) |
| Legal/zoning restated not interpreted | ✓ — C-G |
| Financial terms defined not advised | ✓ — C-H |

---

# REVISED IMPLEMENTATION SEQUENCING (supersedes Part G's list)

1. **Phase 0 gate — resolve compliance blockers first:** implement the **C-A output-sanitization guardrail** and **C-J disclaimer**, and obtain the **C-B tenant-access decision** and **C-F school decision**. Nothing below ships until these are done.
2. Build the Phase 6 system-prompt guardrail (advice refusals).
3. Implement Part A architecture (config schema + blade gating), key-preserving.
4. Apply Part F content **as revised by Part E.2** (removals, rewords, additions, category tags).
5. Execute Part I.4 platform-wide placeholder/tooltip rewrite.
6. Re-run Part G + G.2 validation on the final config; confirm ⚠︎ items are cleared.
7. Only then enable.

---

**End of draft specification (Revision 2). No production knowledge base, render blade, or Ask AI behavior has been changed. Two mandatory review passes (Part E.1, Part H) are complete; their open items (C-A, C-B, C-F) are implementation blockers. Awaiting review/approval before any implementation.**
