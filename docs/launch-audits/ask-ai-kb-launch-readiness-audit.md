# Ask AI Knowledge Base — Launch Readiness Content Audit

**Scope:** read-only content-quality review of all 14 role × property-type interviews. No code changed. Baseline = committed state `f02b1783c`.

**Method:** Every question was read from the four `ai_faq_*` configs with its `category_type` (common vs. insight) and `source` tag. Both sections render as free-text `<textarea>` inputs the client fills in, so all 174 are "questions asked of the client." Each was run against the 5 tests and cross-checked against the structured-field inventory, Property DNA, Location DNA, and Match.

**Framing note (compliance guardrail):** This KB operates under a documented guardrail — no advice, negotiation/leverage coaching, superlatives-as-advice, steering, or protected-class/demographic framing. So the "Negotiation" dimension is intentionally thin, and "ideal buyer/tenant" must be phrased as **use/lifestyle fit**, never demographics. All added-question wording stays inside that guardrail.

---

## A. Current question count by role × property type

Each interview = the role's `universal` group + the one property-type group. (C = Common, I = AI-Insight.)

| Role | Property Type | Common | Insight | Total shown |
|---|---|---:|---:|---:|
| Seller | Residential | 30 | 5 | 35 |
| Seller | Income | 18 | 8 | 26 |
| Seller | Commercial | 16 | 7 | 23 |
| Seller | Business Opportunity | 18 | 7 | 25 |
| Seller | Vacant Land | 16 | 7 | 23 |
| Buyer | Residential | 18 | 3 | 21 |
| Buyer | Income | 12 | 4 | 16 |
| Buyer | Commercial | 12 | 4 | 16 |
| Buyer | Business | 12 | 4 | 16 |
| Buyer | Vacant Land | 14 | 4 | 18 |
| Landlord | Residential | 19 | 4 | 23 |
| Landlord | Commercial | 13 | 4 | 17 |
| Tenant | Residential | 12 | 2 | 14 |
| Tenant | Commercial | 9 | 2 | 11 |

Distinct authored keys: Seller 76 · Buyer 47 · Landlord 32 · Tenant 19 = 174, 0 duplicates.

---

## B. PASS / FAIL launch-readiness rating

| Role · Property Type | Rating | Reason |
|---|---|---|
| Seller · Residential | PASS (minor edits) | Rich, but systems-heavy and thin on emotional/marketing/objection intelligence; 1 data-duplication remove. |
| Seller · Income | PASS (minor edits) | Strong investor coverage; missing "ideal operator" + value-add vision. |
| Seller · Commercial | PASS (minor edits) | Missing redevelopment potential, visibility/signage, ideal use. |
| Seller · Business | PASS (minor edits) | Missing growth opportunities, goodwill/why-customers, competitive position. |
| Seller · Vacant Land | PASS (minor edits) | Missing on-site utility availability, entitlement/plat status, development vision. |
| Buyer · Residential | PASS (minor edits) | Add deal-breakers + compromise areas (best placed in universal). |
| Buyer · Income | PASS | Property-specific and complete. |
| Buyer · Commercial | PASS | Property-specific and complete. |
| Buyer · Business | PASS (minor edits) | Add buyer's industry background + operator-vs-absentee intent. |
| Buyer · Vacant Land | PASS | Complete. |
| Landlord · Residential | PASS (minor edits) | Add management style / long-term strategy + tenant-fit; 1 data-duplication remove. |
| Landlord · Commercial | PASS (minor edits) | Add target industries, signage, CAM/NNN clarification. |
| Tenant · Residential | WEAKEST — PASS only after edits | Screening/financial-heavy; lifestyle, commute, hobbies, deal-breakers all absent. |
| Tenant · Commercial | PASS (minor edits) | Add signage needs + tenant's own build-out needs. |

No combo is broken or non-compliant. One combo (Tenant Residential) is materially under-balanced.

---

## C. Questions to keep as-is (representative core)

- **Seller universal:** `seller_motivation_for_selling`, `closing_timeline_flexibility`, `known_defects_issues`, `as_is_condition`, `seller_concessions_offered`, `seller_leaseback_option`, `unique_selling_points`, `property_lifestyle_support`, `property_features_buyer_appeal`.
- **Seller Income:** `income_rent_roll_context`, `value_add_opportunities`, `deferred_maintenance_disclosed`, `tenant_payment_history`, `professional_management`, `existing_tenant_lease_terms`.
- **Seller Business:** `seller_training_transition`, `business_staff_retention`, `business_customer_concentration`, `business_licenses_transferable`, `business_owner_involvement`, `business_seasonality`.
- **Seller Land:** `land_soil_and_topography`, `land_survey_available`, `land_development_restrictions`, `land_access_and_road`, `land_prior_use`.
- **Buyer:** `buyer_motivation`, `buyer_flexibility`, `buyer_biggest_concern`, `buyer_financing_context`, and the 10 property-specific income/commercial keys.
- **Landlord:** `maintenance_request_response_time`, `lease_to_own_option`, `commercial_buildout_ti`, `commercial_co_tenancy`, `commercial_expansion_option_rofr`, `commercial_zoning_context`.
- **Tenant:** `faq_q18`, `faq_q15`, `tenant_prior_conduct`, `tenant_growth_plans`, `faq_q22`.

---

## D. Questions to rewrite (with exact replacement wording)

| Key | Role · Type | Current | Recommended replacement | Why |
|---|---|---|---|---|
| `nearby_amenities_description` | Seller universal (insight) | "What location features are nearby?" | "Beyond what a map shows, what do you personally love about this location — the neighbors, the street, the daily routine here?" | LocDNA already lists nearby features; add the human layer. |
| `nearby_amenities` | Landlord universal (insight) | "What location features are nearby?" | "What kind of renter tends to love this location, and what do current/past tenants say they enjoy about the area?" | Same LocDNA overlap; convert to lifestyle-fit. |
| `commute_options_access` | Seller residential | "What are typical commute options and travel times?" | "Are there commute or access details a buyer wouldn't discover on a map — a favorite route, a quick highway on-ramp, walkable errands?" | Commute times come from CommuteTimeLookupService; keep human nuance only. |
| `neighborhood_character` | Seller residential & Landlord residential | "What can you share about the area's setting and nearby amenities?" | "How would you describe the personality of this street or neighborhood — the vibe, the pace, what makes it feel like home?" | Current wording overlaps LocDNA; "personality" is human-only. |
| `buyer_nice_to_have` | Buyer residential | "What's on the buyer's wish list (nice-to-haves)?" | Keep, but pair with new deal-breakers question (see F). | Wish-list alone can't tell AI what's non-negotiable. |

---

## E. Questions to remove (with reason)

| Key | Role · Type | Reason |
|---|---|---|
| `school_district_assignment` | Seller residential & Landlord residential | Test 3 (structured data answers it). Location DNA resolves school-district assignment via Census TIGER. Asking the client to re-type it is the "is the beach nearby?" anti-pattern. Remove; let LocDNA supply it. |

Only outright removal. Note: `commercial_restroom_count` / `commercial_ada_accessibility` on the Seller side were explicitly NOT flagged — verified Seller has no structured restroom/ADA field (unlike Landlord, where equivalents were correctly removed), so those Seller questions are valid KB.

---

## F. Missing questions to add (with exact suggested wording)

**Seller Residential:**
- `seller_emotional_hook` — "What first made you fall in love with this home, or what will you miss most about living here?"
- `seller_buyer_feedback` — "What feedback or hesitations have buyers and agents shared when viewing the property?"
- `seller_best_showing_moment` — "Is there a particular time of day, season, or moment when the property shows at its best?"
- `seller_ideal_use_fit` — "What kind of lifestyle, household, or use is this home especially well suited to?"

**Seller Income:**
- `income_ideal_operator_fit` — "What type of owner or operator would get the most out of this property?"
- `income_value_add_vision` — "If you had more time or capital, what's the one improvement you'd make to increase income here?"

**Seller Commercial:**
- `commercial_redevelopment_potential` — "Is there redevelopment, expansion, or change-of-use potential a buyer should know about?"
- `commercial_visibility_signage` — "What are the visibility, frontage, and signage characteristics for a business operating here?"

**Seller Business Opportunity:**
- `business_growth_opportunities` — "What growth opportunities exist that the current owner hasn't pursued?"
- `business_customer_draw` — "Why do customers choose this business, and what keeps them coming back?"

**Seller Vacant Land:**
- `land_utilities_available` — "Which utilities are already available at the site (water, sewer/septic, power, gas, internet), and how close are they?"
- `land_entitlement_status` — "Are any entitlements, plats, permits, or development approvals in place or underway?"

**Buyer — add to UNIVERSAL (benefits all 5 property types):**
- `buyer_deal_breakers` — "What are the buyer's absolute deal-breakers — things that would rule a property out no matter what?"
- `buyer_compromise_areas` — "Where is the buyer most willing to compromise if the right opportunity comes along?"
- `buyer_future_plans` — "How long does the buyer expect to stay, and are any life or work changes on the horizon?"

**Buyer Business:**
- `biz_operator_intent` — "Does the buyer intend to run the business hands-on or as an absentee owner, and what relevant industry experience do they bring?"

**Landlord Residential:**
- `landlord_management_style` — "How hands-on is the landlord — self-managed or professionally managed — and what's their communication style with tenants?"
- `landlord_long_term_strategy` — "What's the landlord's long-term plan for this property (long-term hold, eventual sale, future move-in)?"
- `landlord_tenant_fit` — "What kind of lifestyle or living situation is this rental best suited to?"

**Landlord Commercial:**
- `commercial_target_industries` — "What types of businesses or uses is this space best suited for, and are any uses restricted?"
- `commercial_signage_rights` — "What signage and storefront-visibility options are available to a tenant?"
- `commercial_cam_structure` — "How are CAM / operating expenses handled (NNN, gross, modified), and what's included?"

**Tenant Residential (priority):**
- `tenant_lifestyle` — "How would the applicant describe their day-to-day lifestyle and what they're looking for in a home?" (verify vs TenantDNA first)
- `tenant_commute_priorities` — "What locations does the applicant need to be near (work, school, family), and how important is commute?"
- `tenant_work_habits` — "Does the applicant work from home, on-site, or a mix — and do they need dedicated space for it?"
- `tenant_deal_breakers` — "What are the applicant's must-haves and absolute deal-breakers in a rental?"

**Tenant Commercial:**
- `tenant_signage_needs` — "What signage or storefront visibility does the applicant's business need?"
- `tenant_buildout_needs` — "What build-out, layout, or improvements would the space need for the applicant's operation?"

Do NOT re-add parking/hours for tenant, building-access-hours for landlord — correctly removed in WS1 as structured-field duplicates.

---

## G. Over-/under-covered property types

**Over-covered**
- Seller Residential (35 Q): 12 property-condition/systems questions create completion fatigue and crowd out emotional/marketing signal. Consider grouping rather than deleting.

**Under-covered**
- Tenant Residential (14 Q): heavily weighted to Financial/Screening; Lifestyle, Location-fit, Matchmaking, and Deal-breakers near-empty — biggest single balance gap.
- Tenant Commercial (11 Q): smallest interview; missing the tenant's own build-out and signage needs (asymmetry vs landlord side).
- Landlord (both): Future-plans/strategy and management-style dimensions absent.

**Balance heat-check (9 dimensions)**

| Dimension | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| Property | strong | ok | ok | thin |
| Location | ok (LocDNA overlap) | ok | ok | missing (commute) |
| Lifestyle | thin (add emotional) | ok | thin | missing |
| Financial | ok | ok | ok | over-weighted |
| Marketing | thin | n/a | thin | n/a |
| Negotiation | thin (by design) | thin (by design) | thin | thin |
| Matchmaking | thin (ideal-use) | ok | thin (tenant-fit) | missing |
| Objections/deal-breakers | missing | missing (add) | thin | missing |
| Future plans/flexibility | ok | thin (add) | missing | ok (growth/lease) |

Systemic gaps: Objections/deal-breakers and Matchmaking/ideal-fit are thin across every role.

---

## H. Scoring table — actionable rows

`Already in Form?` = duplicated by a structured field / DNA / Match.

| Question (key) | Role | Prop Type | Already in Form? | AI Value | Action | Reason |
|---|---|---|---|---|---|---|
| school_district_assignment | Seller/Landlord | Residential | Yes — LocDNA | Low | Delete | Data already knows it. |
| nearby_amenities_description | Seller | Universal | Partial — LocDNA | Low→High | Rewrite | Convert to human-only. |
| nearby_amenities | Landlord | Universal | Partial — LocDNA | Low→High | Rewrite | Convert to tenant-fit. |
| commute_options_access | Seller | Residential | Partial — LocDNA | Med | Rewrite | Keep human nuance. |
| neighborhood_character | Seller/Landlord | Residential | Partial — LocDNA | Med→High | Rewrite | "Personality" is human-only. |
| seller_emotional_hook | Seller | Residential | No | High | Add | Emotional selling point. |
| seller_buyer_feedback | Seller | Residential | No | High | Add | Objection intelligence. |
| seller_best_showing_moment | Seller | Residential | No | Med | Add | Best-time-to-experience. |
| seller_ideal_use_fit | Seller | Residential | No | High | Add | Ideal-buyer as lifestyle-fit. |
| income_ideal_operator_fit | Seller | Income | No | High | Add | Matchmaking. |
| income_value_add_vision | Seller | Income | No | High | Add | Value-add opportunity. |
| commercial_redevelopment_potential | Seller | Commercial | No | High | Add | Redevelopment. |
| commercial_visibility_signage | Seller | Commercial | No | High | Add | Visibility/logistics. |
| business_growth_opportunities | Seller | Business | No | High | Add | Growth opportunity. |
| business_customer_draw | Seller | Business | No | High | Add | Goodwill/brand. |
| land_utilities_available | Seller | Land | No | High | Add | Utility availability. |
| land_entitlement_status | Seller | Land | No | High | Add | Entitlement potential. |
| buyer_deal_breakers | Buyer | Universal | No | High | Add | Deal-breakers (all types). |
| buyer_compromise_areas | Buyer | Universal | No | High | Add | Compromise. |
| buyer_future_plans | Buyer | Universal | No | Med | Add | Future plans. |
| biz_operator_intent | Buyer | Business | No | Med | Add | Operator-vs-absentee. |
| landlord_management_style | Landlord | Residential | No | High | Add | Management style. |
| landlord_long_term_strategy | Landlord | Residential | No | Med | Add | Long-term strategy. |
| landlord_tenant_fit | Landlord | Residential | No | High | Add | Tenant-fit (lifestyle). |
| commercial_target_industries | Landlord | Commercial | No | High | Add | Target industry. |
| commercial_signage_rights | Landlord | Commercial | No | Med | Add | Signage. |
| commercial_cam_structure | Landlord | Commercial | Partial — rent ≠ CAM | High | Add | CAM expectations. |
| tenant_lifestyle | Tenant | Residential | Verify vs TenantDNA | High | Add (verify) | Lifestyle. |
| tenant_commute_priorities | Tenant | Residential | No | High | Add | Commute. |
| tenant_work_habits | Tenant | Residential | No | High | Add | Work habits. |
| tenant_deal_breakers | Tenant | Residential | No | High | Add | Deal-breakers. |
| tenant_signage_needs | Tenant | Commercial | No | Med | Add | Signage needs. |
| tenant_buildout_needs | Tenant | Commercial | No | High | Add | Build-out needs. |

Totals: 1 Delete · 4 Rewrite · 30 Add · 0 Move. Every other one of the 174 = Keep.

---

## I. Final recommendation

**Launch-ready after minor edits.**

Architecture and consistency issues are resolved: gating works, duplicates gone, no question duplicates a structured field, and property-specific Buyer questions read as real interviews. Nothing is broken, non-compliant, or blocking.

Remaining work is additive enrichment, not repair:
1. 1 delete — `school_district_assignment` (data already answers it).
2. 4 rewrites — convert LocDNA-overlapping location questions into human-insight prompts.
3. ~30 adds — close the two systemic gaps (objections/deal-breakers, matchmaking/ideal-fit) plus per-type expert questions.

**Priority sequence:** (1) Tenant Residential; (2) Buyer universal deal-breakers/compromise/future-plans trio; (3) Seller Residential emotional/objection set; (4) everything else as polish.

---

## Appendix — Exact questions for every role × property type

Each interview below is exactly what renders in the form: the role universal group + the one property-type group. Tag: (C)=Common Question, (I)=AI Insight. Section headers match the on-screen grouping.

### Seller — Residential Property (35 questions)

**About the Sale**

- (C) Why is the owner selling the property?
- (C) What is included in the sale, and is anything excluded?
- (C) Is any furniture or staging negotiable?
- (C) How flexible is the timing for closing or possession?
- (C) Would the owner consider a short post-closing leaseback?
- (C) Are there any known issues or disclosures the owner has shared?
- (C) Are there planned developments, road projects, or zoning changes nearby?
- (C) Is the property being sold as-is, or is the owner open to repairs based on inspection?
- (C) Has the owner indicated openness to concessions or credits?

**Property Insights**

- (I) What features make this property stand out?
- (I) What location features are nearby?
- (I) What lifestyle does this property appear to support?
- (I) What property information has been disclosed?
- (I) What property features may appeal to different buyers?

**Property Condition & Systems**

- (C) How old is the roof, and what condition is it in?
- (C) How old is the HVAC system, and when was it last serviced?
- (C) How old is the water heater, and what type is it?
- (C) What renovations or upgrades have been made, and when?
- (C) Were renovations completed with proper permits?
- (C) Are there any known foundation or structural issues?
- (C) Any pest or termite history, and how was it resolved?
- (C) Has the property ever flooded or had water damage?
- (C) Any mold history, and how was it addressed?
- (C) Are solar panels present, and are they owned or leased?
- (C) Are there smart-home or EV-charging features?
- (C) If there is a pool or spa, how old is the equipment and what is its condition?

**Costs & Utilities**

- (C) What are the average monthly utility costs?
- (C) Which internet/utility providers serve the property, and what speeds are available?
- (C) Has the owner disclosed any insurance claims history for the property?

**Space & Light**

- (C) What storage options are available?
- (C) How is the natural light, and which way does the home face?

**Location & Neighborhood**

- (C) What can you share about the area's setting and nearby amenities?
- (C) Are there notable traffic or noise considerations nearby?
- (C) What are typical commute options and travel times?
- (C) Which school district is this property assigned to?

### Seller — Income Property (26 questions)

**About the Sale**

- (C) Why is the owner selling the property?
- (C) What is included in the sale, and is anything excluded?
- (C) Is any furniture or staging negotiable?
- (C) How flexible is the timing for closing or possession?
- (C) Would the owner consider a short post-closing leaseback?
- (C) Are there any known issues or disclosures the owner has shared?
- (C) Are there planned developments, road projects, or zoning changes nearby?
- (C) Is the property being sold as-is, or is the owner open to repairs based on inspection?
- (C) Has the owner indicated openness to concessions or credits?

**Property Insights**

- (I) What features make this property stand out?
- (I) What location features are nearby?
- (I) What lifestyle does this property appear to support?
- (I) What property information has been disclosed?
- (I) What property features may appeal to different buyers?

**Operations & Financials**

- (C) What expenses are included in the operating costs?
- (C) What are the current lease terms and escalations for existing tenants?
- (C) How do current rents compare to what the owner believes is market, and are any units below market?
- (C) What recent improvements or income changes has the owner disclosed?
- (C) What has the owner disclosed about tenant payment history?
- (C) Is there any deferred maintenance or near-term capital work disclosed?
- (C) Is the property professionally managed?
- (C) How are utilities split between owner and tenants?
- (C) How old are the roof and major building systems?

**Property Insights**

- (I) What features make this property stand out to operators?
- (I) What has been disclosed about this property's operations?
- (I) What location features are nearby?

### Seller — Commercial Property (23 questions)

**About the Sale**

- (C) Why is the owner selling the property?
- (C) What is included in the sale, and is anything excluded?
- (C) Is any furniture or staging negotiable?
- (C) How flexible is the timing for closing or possession?
- (C) Would the owner consider a short post-closing leaseback?
- (C) Are there any known issues or disclosures the owner has shared?
- (C) Are there planned developments, road projects, or zoning changes nearby?
- (C) Is the property being sold as-is, or is the owner open to repairs based on inspection?
- (C) Has the owner indicated openness to concessions or credits?

**Property Insights**

- (I) What features make this property stand out?
- (I) What location features are nearby?
- (I) What lifestyle does this property appear to support?
- (I) What property information has been disclosed?
- (I) What property features may appeal to different buyers?

**Building & Use**

- (C) What are the building systems (HVAC, electrical capacity)?
- (C) Is the space ADA accessible?
- (C) How many restrooms are there?
- (C) What parking, access, and loading are available?
- (C) How old are the roof and major systems?
- (C) What recent improvements have been made?
- (C) Beyond the zoning code, are there variances, special-use permits, conditional uses, or grandfathered uses in place?

**Property Insights**

- (I) What features make this space stand out?
- (I) What location features are nearby?

### Seller — Business Opportunity (25 questions)

**About the Sale**

- (C) Why is the owner selling the property?
- (C) What is included in the sale, and is anything excluded?
- (C) Is any furniture or staging negotiable?
- (C) How flexible is the timing for closing or possession?
- (C) Would the owner consider a short post-closing leaseback?
- (C) Are there any known issues or disclosures the owner has shared?
- (C) Are there planned developments, road projects, or zoning changes nearby?
- (C) Is the property being sold as-is, or is the owner open to repairs based on inspection?
- (C) Has the owner indicated openness to concessions or credits?

**Property Insights**

- (I) What features make this property stand out?
- (I) What location features are nearby?
- (I) What lifestyle does this property appear to support?
- (I) What property information has been disclosed?
- (I) What property features may appeal to different buyers?

**Business Details**

- (C) Why is the business being sold?
- (C) How much training/transition support will the seller provide?
- (C) Will existing staff stay on after the sale?
- (C) How concentrated is the customer base?
- (C) What vendor or supplier contracts are in place?
- (C) Are licenses, permits, or franchise rights transferable?
- (C) What is the business's online presence and review profile?
- (C) Is the business seasonal?
- (C) How involved is the current owner day-to-day?

**Business Insights**

- (I) What information has been disclosed about this business?
- (I) What does the sale appear to include?

### Seller — Vacant Land (23 questions)

**About the Sale**

- (C) Why is the owner selling the property?
- (C) What is included in the sale, and is anything excluded?
- (C) Is any furniture or staging negotiable?
- (C) How flexible is the timing for closing or possession?
- (C) Would the owner consider a short post-closing leaseback?
- (C) Are there any known issues or disclosures the owner has shared?
- (C) Are there planned developments, road projects, or zoning changes nearby?
- (C) Is the property being sold as-is, or is the owner open to repairs based on inspection?
- (C) Has the owner indicated openness to concessions or credits?

**Property Insights**

- (I) What features make this property stand out?
- (I) What location features are nearby?
- (I) What lifestyle does this property appear to support?
- (I) What property information has been disclosed?
- (I) What property features may appeal to different buyers?

**Site & Access**

- (C) Are there known soil, perc, or topography considerations?
- (C) Is a current survey available, and has the land been cleared/improved?
- (C) What uses are permitted under current zoning?
- (C) Are there deed restrictions beyond recorded easements?
- (C) Are there access limitations or shared-road maintenance obligations?
- (C) Are there wetlands or environmental designations on the parcel?
- (C) What was the land's prior use?

**Property Insights**

- (I) What location features are nearby?
- (I) What objective site characteristics has the listing disclosed?

### Buyer — Residential Property (21 questions)

**Buyer Background**

- (C) What's driving the buyer's search right now?
- (C) What's the buyer's current living/ownership situation?
- (C) How flexible is the buyer on timing or terms if the right property comes along?
- (C) What's the buyer's biggest concern or hesitation?
- (C) Is the buyer relocating or making decisions remotely?
- (C) Would the buyer allow a short seller leaseback after closing?
- (C) Is there anything about how the buyer is financing the purchase worth knowing?

**Buyer Insights**

- (I) What property needs and uses has this buyer described?
- (I) What has this buyer disclosed about their needs and timeline?
- (I) What location features matter to this buyer?

**Residential Preferences**

- (C) What kind of area setting is the buyer looking for?
- (C) Is a specific school district a requirement or preference?
- (C) How sensitive is the buyer to noise?
- (C) How familiar is the buyer with the area?
- (C) Is the buyer open to off-market/pocket listings?
- (C) Does the buyer prefer a particular architectural style or era?
- (C) What's on the buyer's wish list (nice-to-haves)?
- (C) Does the buyer need any accessibility features?
- (C) What are the buyer's privacy preferences?
- (C) How does the buyer envision using the home?
- (C) How important is outdoor space to the buyer?

### Buyer — Income Property (16 questions)

**Buyer Background**

- (C) What's driving the buyer's search right now?
- (C) What's the buyer's current living/ownership situation?
- (C) How flexible is the buyer on timing or terms if the right property comes along?
- (C) What's the buyer's biggest concern or hesitation?
- (C) Is the buyer relocating or making decisions remotely?
- (C) Would the buyer allow a short seller leaseback after closing?
- (C) Is there anything about how the buyer is financing the purchase worth knowing?

**Buyer Insights**

- (I) What property needs and uses has this buyer described?
- (I) What has this buyer disclosed about their needs and timeline?
- (I) What location features matter to this buyer?

**Investment Criteria**

- (C) What's the buyer's intended hold strategy for the property?
- (C) What minimum in-place occupancy does the buyer require at purchase?
- (C) What does the buyer expect from the existing rent roll and leases (in-place rents, term, escalations)?
- (C) Is the buyer completing a 1031 exchange with a timing requirement?
- (C) Will the buyer require environmental or property-condition studies (Phase I/II, PCA)?

**Buyer Insights**

- (I) What type of income property fits this buyer's criteria?

### Buyer — Commercial Property (16 questions)

**Buyer Background**

- (C) What's driving the buyer's search right now?
- (C) What's the buyer's current living/ownership situation?
- (C) How flexible is the buyer on timing or terms if the right property comes along?
- (C) What's the buyer's biggest concern or hesitation?
- (C) Is the buyer relocating or making decisions remotely?
- (C) Would the buyer allow a short seller leaseback after closing?
- (C) Is there anything about how the buyer is financing the purchase worth knowing?

**Buyer Insights**

- (I) What property needs and uses has this buyer described?
- (I) What has this buyer disclosed about their needs and timeline?
- (I) What location features matter to this buyer?

**Commercial Criteria**

- (C) What's the buyer's intended use for the space, and will they owner-occupy?
- (C) What space, layout, or build-out characteristics does the buyer's use require?
- (C) If leased-investment, what lease structure and tenancy does the buyer prefer?
- (C) Is the buyer completing a 1031 exchange with a timing requirement?
- (C) Will the buyer require environmental or property-condition studies (Phase I/II, PCA)?

**Buyer Insights**

- (I) What type of commercial space fits this buyer's intended use?

### Buyer — Business Opportunity (16 questions)

**Buyer Background**

- (C) What's driving the buyer's search right now?
- (C) What's the buyer's current living/ownership situation?
- (C) How flexible is the buyer on timing or terms if the right property comes along?
- (C) What's the buyer's biggest concern or hesitation?
- (C) Is the buyer relocating or making decisions remotely?
- (C) Would the buyer allow a short seller leaseback after closing?
- (C) Is there anything about how the buyer is financing the purchase worth knowing?

**Buyer Insights**

- (I) What property needs and uses has this buyer described?
- (I) What has this buyer disclosed about their needs and timeline?
- (I) What location features matter to this buyer?

**Business Criteria**

- (C) What minimum revenue does the buyer require?
- (C) How much seller training/transition does the buyer expect?
- (C) Does the buyer want existing staff retained?
- (C) Does the buyer require a non-compete from the seller?
- (C) Is the buyer using SBA or seller financing?

**Buyer Insights**

- (I) What type of business is this buyer seeking?

### Buyer — Vacant Land (18 questions)

**Buyer Background**

- (C) What's driving the buyer's search right now?
- (C) What's the buyer's current living/ownership situation?
- (C) How flexible is the buyer on timing or terms if the right property comes along?
- (C) What's the buyer's biggest concern or hesitation?
- (C) Is the buyer relocating or making decisions remotely?
- (C) Would the buyer allow a short seller leaseback after closing?
- (C) Is there anything about how the buyer is financing the purchase worth knowing?

**Buyer Insights**

- (I) What property needs and uses has this buyer described?
- (I) What has this buyer disclosed about their needs and timeline?
- (I) What location features matter to this buyer?

**Land Criteria**

- (C) What's the buyer's intended use for the land?
- (C) What zoning classification does the buyer require?
- (C) What utilities does the buyer need available?
- (C) Will the buyer require soil/perc/environmental testing?
- (C) What's the buyer's build/development timeline?
- (C) What road access or easement does the buyer require?
- (C) Does the buyer have flood/elevation/topography requirements?

**Buyer Insights**

- (I) What land characteristics matter most to this buyer?

### Landlord — Residential Property (23 questions)

**Tenancy & Maintenance**

- (C) How are maintenance requests handled, including emergencies and response times?
- (C) Are there planned renovations or construction that could affect tenants?
- (C) How much notice is required to vacate at lease end?
- (C) Is a lease-to-own or rent-credit arrangement possible?

**Rental Insights**

- (I) What location features are nearby?
- (I) What makes this rental stand out?
- (I) What lifestyle does this rental appear to support?
- (I) What has been disclosed about this rental?

**Systems & Amenities**

- (C) Which internet providers and speeds are available?
- (C) Is the unit furnished, unfurnished, or negotiable?
- (C) Is EV charging available or installable?

**Policies & Costs**

- (C) Are short-term rentals permitted?
- (C) Any pest or mold history, and how was it resolved?
- (C) How are utilities metered and billed?
- (C) Is renter's insurance required, and at what coverage?
- (C) What's the application process, fee, and timeline?
- (C) Who is responsible for lawn/landscaping?
- (C) According to the landlord, what is the typical length of tenancy?

**Location & Neighborhood**

- (C) What can you share about the area's setting and nearby amenities?
- (C) What's the noise level like?
- (C) How close is public transit?
- (C) Is guest/visitor parking available?
- (C) Which school district serves this rental?

### Landlord — Commercial Property (17 questions)

**Tenancy & Maintenance**

- (C) How are maintenance requests handled, including emergencies and response times?
- (C) Are there planned renovations or construction that could affect tenants?
- (C) How much notice is required to vacate at lease end?
- (C) Is a lease-to-own or rent-credit arrangement possible?

**Rental Insights**

- (I) What location features are nearby?
- (I) What makes this rental stand out?
- (I) What lifestyle does this rental appear to support?
- (I) What has been disclosed about this rental?

**Commercial Lease & Space**

- (C) Is there a loading dock or freight elevator?
- (C) What is the electrical capacity (amperage/voltage/3-phase)?
- (C) Are exclusivity rights available?
- (C) Is there an expansion option or right of first refusal?
- (C) What build-out or tenant-improvement support is available beyond what's listed?
- (C) Is the space ADA accessible?
- (C) What is the HVAC type, the zoning of zones, and after-hours HVAC availability?
- (C) What is the co-tenancy / anchor-tenant situation in the building?
- (C) Beyond the permitted-use field, are there variances, conditional uses, or restrictions tenants should know about?

### Tenant — Residential Property (14 questions)

**Applicant Background**

- (C) What's driving the applicant's rental search?
- (C) What's the applicant's biggest concern in this search?
- (C) Is there any chance the applicant would need to break the lease early?
- (C) Does the applicant have landlord or employer references available?

**Applicant Insights**

- (I) What has this applicant disclosed about their rental background?
- (I) What rental needs and uses has the applicant described?

**Residential Applicant**

- (C) What is the source and stability of the applicant's income?
- (C) Would the applicant consider a longer lease for a locked-in/reduced rate?
- (C) Is the applicant willing to pay a pet deposit or pet rent if required?
- (C) How long was the most recent tenancy, and why is the applicant moving?
- (C) How flexible is the applicant on lease length?
- (C) Is a co-signer or guarantor available if needed?
- (C) How soon is the applicant ready to apply and provide documentation?
- (C) Has the applicant disclosed any prior rental conduct (late payments, notices)?

### Tenant — Commercial Property (11 questions)

**Applicant Background**

- (C) What's driving the applicant's rental search?
- (C) What's the applicant's biggest concern in this search?
- (C) Is there any chance the applicant would need to break the lease early?
- (C) Does the applicant have landlord or employer references available?

**Applicant Insights**

- (I) What has this applicant disclosed about their rental background?
- (I) What rental needs and uses has the applicant described?

**Commercial Applicant**

- (C) Does the applicant expect customer/client foot traffic, and how much?
- (C) Does the applicant have special equipment or power requirements?
- (C) What are the applicant's expected hours of operation?
- (C) Does the applicant expect their space needs to change over the lease term (growth, seasonal, or downsizing)?

**Applicant Insights**

- (I) What business use and operating profile has this applicant disclosed?

