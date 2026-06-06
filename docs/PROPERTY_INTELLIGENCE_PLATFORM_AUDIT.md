# Property Intelligence Platform — Consolidated Audit

**Date:** 2026-06-06
**Sources:** `PROPERTY_DNA_AUDIT.md`, `LOCATION_DNA_AUDIT.md`, `BUYER_TENANT_DNA_COMPATIBILITY_AUDIT.md`, `MARKETING_INTELLIGENCE_AUDIT.md`
**Scope:** Read-only synthesis. No production code was changed. All findings are sourced exclusively from the four audit documents above.

---

## 1. What Already Exists

### 1.1 Property DNA System

The supply-side DNA system is fully wired end-to-end for seller and landlord listings.

**Storage:** `property_dna_profiles` table — one active row per listing (append-only versioning; prior rows archived via `archived_at`).

**Generation service:** `PropertyDnaGenerator` — maps 29 dimension slots, builds archetype tags and marketing hooks, computes 6 coverage scores, and persists with a PostgreSQL advisory lock inside a DB transaction.

**Trigger chain:** Eloquent observer (`PropertyAuctionDnaObserver` / `LandlordAuctionDnaObserver`) fires on every `saved` event and dispatches `ComputePropertyDnaProfile` synchronously (`QUEUE_CONNECTION=sync`).

**Downstream read layer:** `PropertyIntelligenceProfileService` — reads a persisted profile, assembles a "Property Intelligence Profile" output array, and caches `location_intelligence_context` back onto the `PropertyDnaProfile` row as a side effect.

**Interpretation layers:** `SellerDnaReportService` and `LandlordDnaReportService` — read-only services that translate archetype tags into `buyer_archetype_alignment` maps.

**Admin UI (full access):**
- `/admin/dna/property` — paginated DNA profile list, filterable
- `/admin/dna/property/{id}` — version history for a listing
- `/admin/dna/profiles/seller/{listingId}` — full seller DNA detail (scores, archetype tags, marketing hooks, property personality)
- `/admin/dna/profiles/landlord/{listingId}` — equivalent for landlord
- `/admin/property-dna/{profile}/marketing-brief-preview` — brief preview + generate AI report button
- `/admin/property-dna/marketing-reports/{report}` — AI report review and publish action

**Agent UI (marketing reports only):**
- `/agent/property-dna/{profile}/marketing-brief-review` — 9-section brief, read-only
- `/agent/property-dna/marketing-reports/{report}` — AI report sections; agent can revise 4 sections

**Owner UI (approve/reject only):**
- `/owner/property-dna/marketing-reports/{report}/approval` — AI report approval or rejection

---

### 1.2 Location DNA System

A full four-phase pipeline is implemented and tested in isolation.

**Storage:** Three dedicated tables.
- `property_location_dna` — one row per listing; stores geocode data, `summary_json` (Phase D output), and `lifestyle_json` (Phase 2 output)
- `property_location_pois` — one row per POI category per listing; 19 categories fetched from Google Places
- `property_location_dna_audits` — append-only event log; immutable at the model level

**Plus one column** on `property_dna_profiles`: `location_intelligence_context` (added by a second migration; written by `PropertyIntelligenceProfileService`).

**Pipeline phases (services exist, tested in isolation):**
1. `LocationDnaGeocodeService` — geocodes listing address via Google Maps; stores lat/lng
2. `LocationDnaPoiDistanceService` — fetches 19 POI categories from Google Places; stores Haversine distances
3. `LocationDnaSummaryService` — compiles `summary_json` with thematic blocks (coastal, daily_convenience, outdoor_recreation, transportation) and raw nearest_by_category map
4. `LocationDnaLifestyleScoreService` — writes `lifestyle_json` with 5 scores (0–100), 8 lifestyle category labels, and one deterministic plain-English narrative

**19 POI categories implemented:** beach, beach_access, grocery_store, pharmacy, coffee_shop, restaurant, park, dog_park, waterfront_park, transit_station, gas_station, hospital, gym, fitness_center, shopping_center, boat_ramp, marina, golf_course, school.

**Lifestyle scores generated:** coastal_score, walkability_score, convenience_score, commuter_score, family_score.

**8 lifestyle category labels:** Beach Lovers, Boaters, Families, Retirees, Remote Workers, Commuters, Outdoor Enthusiasts, Convenience Seekers.

**Internal consumers:** `AskAiContextBuilderService` and `AskAiPromptBuilderService` — Location DNA feeds the AI question-answering pipeline via `location_intelligence` context block. `LocationDnaMarketingContextService` reshapes `summary_json` into four thematic marketing blocks (built and tested; not yet connected to the AI report pipeline).

---

### 1.3 Demand DNA (Buyer / Tenant) System

**Storage:** Two tables.
- `buyer_tenant_dna_profiles` — demand-side DNA for buyer and tenant listings; append-only versioning
- `listing_compatibility_scores` — pairwise supply↔demand scores (buyer↔seller, tenant↔landlord)

**Generation:** `BuyerTenantDnaGenerator` — produces `archetype_label`, `lifestyle_tags`, `deal_breaker_flags`, and `preference_completeness`. Enriched by `BuyerAvatarService` / `TenantAvatarService` into avatar fields (narrative, preference_summary, personality_tags, match_preferences, motivation, readiness_score, confidence_score).

**Trigger:** Observers dispatch `ComputeBuyerTenantDnaProfile` on buyer/tenant listing save; compatibility is recomputed by `PropertyDnaProfileCompatibilityObserver` and `BuyerTenantDnaProfileCompatibilityObserver` via `ComputeCompatibilityScore`.

**Compatibility scores (8 of 14 dimensions active in Phase H):** property_type, price_budget, bedrooms, bathrooms, square_footage, features_amenities, parking, budget_flexibility.

**Explanation/narrative columns on `listing_compatibility_scores`:** score_explanation (JSON), compatibility_narrative (text), compatibility_summary_json, compatibility_highlights, compatibility_warnings, compatibility_readiness_score, compatibility_trait_results (raw per-dimension engine output).

**Supply-side audience signal:** `ai_buyer_archetype_tags` on `property_dna_profiles` — structured tags (14 prefix types: type, style, condition, amenity, parking, feature, policy, community, use, governance, timing, structure, financing, marketing) encoding which buyer/tenant archetypes the property suits.

**Buyer archetypes (11 defined):** Commercial Buyer, Waterfront Buyer, Investor Buyer, Vacation Buyer, Downsizing Buyer, Luxury Buyer, Move-Up Buyer, Budget-Conscious Buyer, First-Time Buyer, Flexible Buyer, Unknown Buyer. (Relocation Buyer: signal detection exists; archetype assignment deferred to V2.)

**Tenant archetypes (8 defined):** Commercial Tenant, Lease-Option Tenant, Pet-Conscious Tenant, Amenity-Focused Tenant, Space-Focused Tenant, Budget-Conscious Tenant, Flexible Tenant, Unknown Tenant.

**Admin UI:**
- `/admin/dna/demand` — demand DNA profile list
- `/admin/dna/demand/scores` — compatibility scores table
- `/admin/dna/buyer/{listingId}` — buyer DNA detail (avatar, lifestyle tags, flags)
- `/admin/dna/tenant/{listingId}` — tenant DNA detail

**Consumer route exists** (`ConsumerCompatibilityReportController`) with a privacy-filtered BYA report pipeline (4 services: alignment → explanation → narrative → report assembly). Blade template does not exist yet.

---

### 1.4 Marketing Intelligence System

Twelve services across four layers.

**Layer 1 — Deterministic context builders (no AI, no writes):**
- `PropertyMarketingContextService` — 14-tag-prefix → 10 named attribute/transaction buckets
- `BuyerTenantMarketingContextService` — 8 lifestyle prefix → demand preference buckets (built; not connected to the report pipeline)
- `LocationDnaMarketingContextService` — summary_json → 4 thematic location blocks (built; not connected to the report pipeline)

**Layer 2 — Deterministic brief builder (no AI, no writes):**
- `PropertyMarketingBriefService` — 9-section brief computed fresh on every call; never persisted
- `PropertyMarketingReadinessService` — gates AI generation on 3 required information groups being present

**Layer 3 — AI report pipeline (calls OpenAI, writes to DB):**
- `AiMarketingReportGeneratorService` → `AiMarketingReportReviewService` → `AiMarketingReportOrchestratorService` → `AiMarketingReportPersistenceService`
- 5 AI-authored sections: `property_feature_narrative`, `transaction_terms_summary`, `marketing_asset_statement`, `missing_information_note` (internal only), `listing_preparation_summary`
- Persisted in 3 tables: `marketing_reports`, `marketing_report_versions`, `marketing_report_audits`
- Status lifecycle: `pending_review → seller_approved → published` (or `→ rejected`)

**Layer 4 — Post-generation workflow (writes, no AI):**
- Agent section revision (4 editable sections)
- Owner approve/reject
- Admin publish

---

## 2. What Data Is Being Generated

The following data is actively produced when pipeline triggers fire:

| Data | Produced By | Stored In |
|---|---|---|
| 29 dimension slot values | `PropertyDnaGenerator` | `property_dna_profiles` (via archetype tags + hooks) |
| 6 coverage scores (physical, financial, flexibility, occupant_qualification, marketing, commercial) | `PropertyDnaGenerator` | `property_dna_profiles` |
| Archetype tag array (14 prefix types) | `PropertyDnaGenerator` | `property_dna_profiles.ai_buyer_archetype_tags` |
| Marketing hook array (trait/value pairs) | `PropertyDnaGenerator` | `property_dna_profiles.ai_marketing_hooks` |
| Property personality, buyer archetype alignment | `SellerDnaReportService` / `LandlordDnaReportService` | In-memory (admin view only) |
| Location intelligence context block | `PropertyIntelligenceProfileService` | `property_dna_profiles.location_intelligence_context` |
| Geocode (lat/lng, status) | `LocationDnaGeocodeService` | `property_location_dna` |
| 19 POI category distances | `LocationDnaPoiDistanceService` | `property_location_pois` |
| Thematic POI summary blocks | `LocationDnaSummaryService` | `property_location_dna.summary_json` |
| 5 lifestyle scores, 8 category labels, location narrative | `LocationDnaLifestyleScoreService` | `property_location_dna.lifestyle_json` |
| 4 thematic marketing blocks from location data | `LocationDnaMarketingContextService` | In-memory only (not persisted or connected) |
| Demand archetype, lifestyle tags, deal-breaker flags | `BuyerTenantDnaGenerator` | `buyer_tenant_dna_profiles` |
| Buyer/tenant avatar (narrative, personality tags, motivations, readiness) | `BuyerAvatarService` / `TenantAvatarService` | `buyer_tenant_dna_profiles` |
| Pairwise compatibility scores (8 dimensions) + explanations + narrative | `CompatibilityEngine` → persistence layer | `listing_compatibility_scores` |
| 9-section deterministic marketing brief | `PropertyMarketingBriefService` | In-memory only (never persisted) |
| 5 AI-authored marketing report sections | `AiMarketingReportGeneratorService` | `marketing_reports` + `marketing_report_versions` |
| Marketing report audit trail | `AiMarketingReportPersistenceService` | `marketing_report_audits` |

---

## 3. What Is Not Being Generated

These are confirmed absences — either columns hardcoded null, pipeline steps with no trigger, or capabilities explicitly deferred or governance-blocked.

| Gap | Classification | Detail |
|---|---|---|
| `location_score` on `property_dna_profiles` | Always null | Hardcoded null in `PropertyDnaGenerator`; no service writes it |
| `condition_score` on `property_dna_profiles` | Always null | Hardcoded null in `PropertyDnaGenerator` |
| `legal_score` on `property_dna_profiles` | Always null | Hardcoded null in `PropertyDnaGenerator` |
| `compatibility_score` on `property_dna_profiles` | Always null | Hardcoded null in `PropertyDnaGenerator` |
| `walk_score`, `transit_score`, `bike_score` | Reserved / not implemented (F-01) | Schema placeholder; no integration |
| `school_rating` | Reserved / not implemented (F-02) | Schema placeholder; no integration |
| `flood_zone_verified` | Reserved / not implemented (F-03) | Schema placeholder; no integration |
| `estimated_monthly_utilities` | Reserved / not implemented (F-05) | Schema placeholder; no integration |
| `location_match_score` on `listing_compatibility_scores` | Always null | Location DNA Phase 2 (geospatial radius) not implemented |
| `travel_time_minutes` on `property_location_pois` | Always null | Drive/walk time API not integrated; reserved column only |
| `commute_polygon_cache` on `buyer_tenant_dna_profiles` | Always null | Reserved for Location DNA Phase 2; no code reads or writes it |
| Airport POI category | Not implemented | In Phase A governance plan; never added to `LocationDnaPoiDistanceService` |
| Downtown / City Center POI category | Not implemented | In Phase A governance plan; never added |
| Thematic block coverage for school, hospital, gym, fitness_center, shopping_center | Fetched and stored; not mapped | These 5 categories appear in `nearest_by_category` but do not feed lifestyle scores, thematic blocks, or intelligence context |
| Relocation Buyer archetype assignment | Not classified in V1 | Signal detection exists; falls through to Flexible/Unknown Buyer |
| 6 of 14 CompatibilityEngine dimensions (occupancy, furnishing, timeline, lease_term, hoa_fees, location) | Ineligible or null | Field data not yet collected; no score produced |
| Location DNA generation trigger | Not implemented | No observer, job, event listener, Artisan command, or controller hook invokes any LocationDna* service |
| Location context in AI report | Explicitly deferred | `LocationDnaMarketingContextService` governance block documents this as deferred until a separately approved hook phase |
| Buyer/tenant preference context in AI report | Not connected | `BuyerTenantMarketingContextService` exists and is tested; not wired to `PropertyMarketingBriefService` or `AiMarketingReportGeneratorService` |
| Marketing angles as persisted structured output | Not built | Ask AI `marketing_angles` type is real-time conversational only; nothing is saved |
| Recommended audience statements (any form) | Governance-blocked | Every marketing service governance block prohibits audience targeting or persona inference |
| Headlines, ad copy, social copy, email copy | Governance-blocked | Explicitly prohibited in every marketing service governance block |
| AI report regeneration after rejection | Not built | `rejected` is a terminal state; no reset or re-trigger path exists |
| `agent_approved` status transition | Not built | DB constraint allows it; no service writes it |

---

## 4. What Is Stored But Not Visible

The following data is written to the database but is not rendered in any Blade view, admin panel, agent dashboard, or public page:

| Data | Stored In | Visible Where |
|---|---|---|
| `lifestyle_json` (5 scores, 8 labels, narrative) | `property_location_dna.lifestyle_json` | Nowhere — zero UI references confirmed |
| `summary_json` (19 POI distances, 4 thematic blocks) | `property_location_dna.summary_json` | Nowhere — consumed only by Ask AI pipeline internally |
| `location_intelligence_context` | `property_dna_profiles.location_intelligence_context` | Ask AI pipeline only; not rendered in any view |
| Geocode data (lat/lng, status) | `property_location_dna` | Nowhere — no admin view, no listing view |
| All 19 POI rows per listing | `property_location_pois` | Nowhere — no admin view, no listing view |
| Pipeline audit trail | `property_location_dna_audits` | Nowhere — no admin panel reads from this table |
| Buyer/tenant avatar fields (narrative, personality tags, motivations, readiness score) | `buyer_tenant_dna_profiles` | Admin buyer/tenant DNA views only — not visible to buyers or tenants |
| Seller/landlord recommended audience (`buyer_archetype_alignment`) | Derived from `property_dna_profiles.ai_buyer_archetype_tags` by `SellerDnaReportService` | Admin seller/landlord DNA views only — not visible to sellers, agents, or buyers |
| Compatibility trait results (raw per-dimension) | `listing_compatibility_scores.compatibility_trait_results` | Admin scores table only — excluded from consumer view by privacy filter |
| `compatibility_highlights`, `compatibility_warnings`, `compatibility_readiness_score` | `listing_compatibility_scores` | Admin view only; consumer Blade template does not exist |
| Published AI marketing report (`published` status) | `marketing_reports` | No delivery path — published status has no connection to any public listing page or buyer-facing view |
| `marketing_report_versions` (full section revision history) | `marketing_report_versions` | Admin and agent views only — no owner revision history visible; no public access |
| `marketing_report_audits` (event log) | `marketing_report_audits` | Admin view only |
| 6 coverage scores (`physical_score`, `financial_score`, `flexibility_score`, `occupant_qualification_score`, `marketing_score`, `commercial_score`) | `property_dna_profiles` | Admin DNA inspector only — not visible to sellers or agents |
| `ai_marketing_hooks` (trait/value marketing hook pairs) | `property_dna_profiles.ai_marketing_hooks` | Admin DNA views only |
| Overall DNA completeness (`overall_dna_completeness`) | `property_dna_profiles` | Admin DNA inspector only |

---

## 5. What UI Is Missing

These are confirmed absent Blade views, routes, or rendered surfaces — either from explicit audit findings or from confirmed zero-reference searches.

### For Sellers / Owners
- No seller-facing view of DNA completeness, coverage scores, or archetype tags
- No seller-facing "what we know about your property" summary page
- No seller-facing listing presentation document or summary
- No PDF export of any marketing output
- No notification when an AI marketing report is ready for review
- No notification when the report is approved or rejected by admin

### For Agents
- No agent view of raw DNA scores, archetype tags, or dimension completeness
- No agent view of which buyer archetypes a listing attracts (audience alignment) — this is admin-only
- No agent notification when an owner approves or rejects a report
- No `agent_approved` gate before owner review (DB constraint exists; no service writes it)
- No re-generation path for a listing with a rejected report

### For Buyers / Tenants
- No consumer-facing compatibility report Blade template (`consumer/compatibility_report.blade.php` does not exist — any request will throw `ViewNotFoundException`)
- No buyer/tenant view of their own archetype, lifestyle tags, or avatar classification
- No buyer-facing display of POI distances, lifestyle scores, or location narrative for any listing
- No public listing page display of any marketing report section after it reaches `published` status

### For Admins
- No admin "regenerate DNA" button or Artisan command — regeneration requires resaving the source listing
- No admin view of Location DNA data (`property_location_dna`, `property_location_pois`, `property_location_dna_audits`) — no admin panel references these tables
- No admin interface to trigger, inspect, or regenerate the Location DNA pipeline for any listing
- No re-generation path for AI reports that have already been generated for a profile (generate button is hidden once a report exists, regardless of rejection or stale data)

### Pipeline Surfaces Missing Entirely
- No Location DNA orchestrator UI or Artisan command
- No pipeline status indicator for any listing (geocode status, POI completion, lifestyle score generation)
- No "missing information" action path for the seller — the Phase R brief produces `missing_information_checklist` and `seller_landlord_questions` but there is no UI that routes a seller to fill in the missing fields

---

## 6. What Can Be Used Immediately for Seller Listing Intelligence

These capabilities are fully built, tested, and available in memory or the database with no additional logic required. They can be surfaced in new views without modifying any service.

### From Property DNA (available per listing save)

| Data | Source | Ready To Display |
|---|---|---|
| 6 coverage scores (physical, financial, flexibility, occupant_qualification, marketing, commercial) | `property_dna_profiles` | Yes — plain decimal values |
| `overall_dna_completeness` — % of 29 dimensions populated | `property_dna_profiles` | Yes — progress indicator |
| `ai_buyer_archetype_tags` — structured buyer signal tags | `property_dna_profiles` | Yes — via `SellerDnaReportService::buyer_archetype_alignment()` |
| `ai_marketing_hooks` — trait/value marketing hook pairs | `property_dna_profiles` | Yes — bullet list |
| Property personality profile | `SellerDnaReportService` | Yes — narrative text, no additional computation |
| Buyer archetype alignment (which archetypes this listing suits) | `SellerDnaReportService` | Yes — ranked map, admin view already renders it |

### From Marketing Intelligence (deterministic, always fresh)

| Data | Source | Ready To Display |
|---|---|---|
| 9-section Phase R brief (attributes, transactions, quantitative, missing info, seller questions, prep notes, neutral summary) | `PropertyMarketingBriefService::build()` | Yes — recomputed on demand from existing `PropertyDnaProfile` |
| `missing_information_checklist` — named empty dimensions | Phase R | Yes — actionable list for seller |
| `seller_landlord_questions` — pre-written clarifying questions | Phase R | Yes — ready-made UX content |
| `listing_preparation_notes` — factual notes on financing, timing, structure | Phase R | Yes |
| `neutral_feature_summary` — factual attribute records | Phase R | Yes |
| AI marketing report sections (for listings that have a `published` report) | `marketing_reports.sections` | Yes — 5 section `draft_text` values are already stored |

### From Location DNA (available per listing where pipeline has run)

| Data | Source | Ready To Display |
|---|---|---|
| Lifestyle scores (coastal, walkability, convenience, commuter, family) | `property_location_dna.lifestyle_json` | Yes — 0–100 scores and 8 category labels |
| Location narrative | `property_location_dna.lifestyle_json.location_narrative` | Yes — deterministic English sentence |
| Lifestyle category labels (e.g. "Beach Lovers", "Families") | `property_location_dna.lifestyle_json.lifestyle_categories` | Yes — array of strings |
| Thematic POI distances (coastal, daily_convenience, outdoor_recreation, transportation) | `property_location_dna.summary_json` | Yes — structured blocks with distance values |
| Nearest POI per category (19 categories) | `property_location_dna.summary_json.nearest_by_category` | Yes — name, distance, status per category |

### From Compatibility (available per listing where scores exist)

| Data | Source | Ready To Display |
|---|---|---|
| Pairwise compatibility scores per buyer listing | `listing_compatibility_scores` | Yes — overall_score, 3 sub-scores, narrative |
| Compatibility narrative per buyer-seller pair | `listing_compatibility_scores.compatibility_narrative` | Yes |
| Buyer archetype for each matched buyer listing | `buyer_tenant_dna_profiles.archetype_label` | Yes |

---

## 7. What Requires New Logic

These capabilities need new code to exist — either a new service, a new trigger, new schema, or a new pipeline connection.

### High-Dependency Logic (blocks multiple downstream features)

| Requirement | What It Needs |
|---|---|
| Location DNA generation trigger | A queued job (`ComputeLocationDna`), observer, or Artisan command that chains the four services in order (B→C→D→Lifestyle) on listing save or approval |
| Location DNA orchestrator | A single facade or runner class that chains `LocationDnaGeocodeService → LocationDnaPoiDistanceService → LocationDnaSummaryService → LocationDnaLifestyleScoreService` in sequence; currently no such class exists |
| Admin Location DNA inspection panel | New admin views reading from `property_location_dna`, `property_location_pois`, and `property_location_dna_audits`; required for diagnosing stale or missing data |

### AI Report Pipeline Connections (built but unconnected)

| Requirement | What It Needs |
|---|---|
| Location context in AI report | An approved hook phase connecting `LocationDnaMarketingContextService` output into `AiMarketingReportGeneratorService` prompt payload; currently governance-blocked pending that approval |
| Buyer/tenant demand context in AI report | Connecting `BuyerTenantMarketingContextService` output to `PropertyMarketingBriefService` or `AiMarketingReportGeneratorService` |

### Report Lifecycle Gaps

| Requirement | What It Needs |
|---|---|
| Report regeneration after rejection | A new code path creating a new `marketing_reports` row for a profile that already has a report, bypassing the existing duplicate `report_id` guard |
| `agent_approved` status transition | A new controller action + service method writing `agent_approved` status; DB constraint already allows it |
| Owner/agent notifications | Email or in-app notification service calls at status transitions (`pending_review`, `seller_approved`, `rejected`) — no notification infrastructure is currently wired to the marketing report pipeline |

### Seller Intelligence Views

| Requirement | What It Needs |
|---|---|
| Seller listing intelligence page | New route + controller + Blade view composing DNA completeness, archetype tags, marketing hooks, location scores, and Phase R brief sections into a seller-readable format |
| Seller "complete your listing" guidance | Logic routing from `missing_information_checklist` (Phase R output, already computed) back to the listing edit form for the specific empty fields |
| Published report delivery to listing page | A new route or component that reads `published` marketing reports and renders sections on the public listing page or agent listing summary |

### Compatibility Gaps

| Requirement | What It Needs |
|---|---|
| Consumer compatibility report Blade template | `resources/views/consumer/compatibility_report.blade.php` must be created; `ConsumerCompatibilityReportController` already exists and passes the right data |
| `location_match_score` population | Location DNA Phase 2 — geospatial radius matching between buyer commute polygon (or point) and property coordinates |
| 6 ineligible CompatibilityEngine dimensions | Field data collection for occupancy, furnishing, timeline, lease_term, hoa_fees in buyer/tenant listing forms |

### POI Coverage Gaps

| Requirement | What It Needs |
|---|---|
| Airport category | Add `airport` to `LocationDnaPoiDistanceService::CATEGORIES`; define thematic block membership |
| Downtown / City Center category | Add to `CATEGORIES`; define thematic block membership |
| Thematic block mapping for school, hospital, gym, fitness_center, shopping_center | Update `LocationDnaSummaryService` to wire these 5 already-fetched categories into named thematic blocks and feed them into lifestyle scoring |

---

## 8. Recommended Build Phases

These phases are ordered by dependency and by the ratio of new-logic cost to immediate value for Seller Listing Intelligence. Each phase is self-contained unless marked as depending on a prior phase.

---

### Phase 1 — Surface Existing Data to Sellers and Agents (no new logic)

**Effort:** Low — new views only; no new services
**Prerequisite:** None
**Value:** Immediate — all data is already in the database

1. **Seller DNA summary panel** — New route + Blade view under `/seller/listings/{id}/intelligence` (or injected into the existing listing dashboard). Renders: `overall_dna_completeness` progress bar, 6 coverage scores, `ai_buyer_archetype_tags` via `SellerDnaReportService::buyer_archetype_alignment()`, `ai_marketing_hooks` as bullets, property personality text.

2. **Agent marketing brief view (Phase R)** — The agent route `/agent/property-dna/{profile}/marketing-brief-review` exists and renders all 9 Phase R sections. What is missing is a link to this page from the listing dashboard and from any seller view. Add navigation entry only.

3. **Seller "missing information" action panel** — Read `missing_information_checklist` and `seller_landlord_questions` from Phase R (already computed on demand) and render them as a guided checklist on the seller's listing edit page. No new computation; the brief already contains the data.

4. **Published report delivery to listing page** — Add a conditional block to the public listing view that reads the `published` marketing report for the listing (if one exists) and renders the 4 non-internal sections. No new service; `marketing_reports.sections` is already structured JSON.

5. **Consumer compatibility report Blade template** — Create `resources/views/consumer/compatibility_report.blade.php`. The controller, privacy filter, and four-service BYA pipeline are already built. This is a template-only gap.

---

### Phase 2 — Wire Location DNA Pipeline Trigger

**Effort:** Medium — new job, observer hook or Artisan command; no new service logic
**Prerequisite:** Google Maps API key confirmed in production environment
**Value:** Unlocks Phases 3, 4, 5 for any listing going forward

1. **Location DNA orchestrator class** — A single `LocationDnaPipelineRunner` (or equivalent) that chains `geocodeForListing → calculateForListing → summarizeForListing → generateForListing` in sequence, with guard checks between steps.

2. **`ComputeLocationDna` queued job** — Wraps the orchestrator; dispatched on listing save or listing approval.

3. **Observer hook** — Wire `ComputeLocationDna::dispatch()` into `PropertyAuctionDnaObserver` and `LandlordAuctionDnaObserver` after DNA generation, or create a dedicated observer on `PropertyDnaProfile::saved`.

4. **Artisan command** — `location-dna:generate {listing_type} {listing_id}` for backfilling existing listings.

---

### Phase 3 — Surface Location DNA to Sellers and Public

**Effort:** Low — new Blade partials only
**Prerequisite:** Phase 2 (data must be present)
**Value:** High — lifestyle scores and POI distances are meaningful and differentiating for sellers

1. **Location intelligence panel on seller/listing pages** — Render: lifestyle scores (0–100 for each of 5 dimensions), lifestyle category labels, location narrative sentence, and nearest POI per category from `summary_json`.

2. **Location panel on public listing page** — Condensed version of the above for prospective buyers: beach distance, grocery distance, park distance, nearest transit, lifestyle categories, narrative.

3. **Admin Location DNA inspection panel** — New admin views reading `property_location_dna` (geocode status, summary_json, lifestyle_json), `property_location_pois` (table of 19 category rows per listing), and `property_location_dna_audits` (event log). Required for diagnosing stale or failed pipelines.

---

### Phase 4 — Connect Location Context to AI Report

**Effort:** Medium — requires governance approval and controlled hook into `AiMarketingReportGeneratorService`
**Prerequisite:** Phase 2 (location data must exist)
**Value:** Richer AI report sections that reflect the property's real location story

1. **Approve and implement Phase G hook** — Add `LocationDnaMarketingContextService` output as a sixth key in the AI prompt payload. Update `AiMarketingReportGeneratorService` under its existing governance framework.

2. **Connect `BuyerTenantMarketingContextService`** — Wire demand-side preference signals (from compatible buyer/tenant listings in the area) into the brief or prompt as a demand context key.

---

### Phase 5 — Extend Compatibility and Audience Intelligence

**Effort:** Medium-to-High — requires field data collection changes and new scoring logic
**Prerequisite:** Phase 2 for `location_match_score`; field collection work is independent

1. **Enable 5 ineligible CompatibilityEngine dimensions** — Add occupancy, furnishing, timeline, lease_term, and hoa_fees fields to buyer/tenant listing forms; wire them into `CompatibilityEngine` dimension handlers.

2. **`location_match_score` via geospatial radius** — Implement Location DNA Phase 2: commute polygon or point-radius matching between `buyer_tenant_dna_profiles.commute_polygon_cache` and `property_location_dna.geocoded_lat/lng`. Populate `location_match_score` on `listing_compatibility_scores`.

3. **Thematic block coverage for 5 orphaned POI categories** — Update `LocationDnaSummaryService` to map school, hospital, gym, fitness_center, and shopping_center into thematic blocks and feed them into lifestyle score calculations.

4. **Airport and Downtown POI categories** — Add to `LocationDnaPoiDistanceService::CATEGORIES` and assign thematic block membership.

---

### Phase 6 — Report Lifecycle and Notification Gaps

**Effort:** Low-to-Medium — surgical additions to existing pipeline
**Prerequisite:** None; independent of Phases 1–5

1. **Report regeneration path** — Allow a new `marketing_reports` row for a profile that already has a rejected report. Update the admin UI to expose a "Generate New Report" option when the existing report is in `rejected` status.

2. **`agent_approved` status transition** — Add a controller action + `AiMarketingReportAgentRevisionService` method that transitions a report to `agent_approved` before routing to owner review.

3. **Notification wiring** — Send email or in-app notifications at three status transitions: (a) owner notified when report enters `pending_review`; (b) agent notified when owner approves or rejects; (c) admin notified when a report reaches `seller_approved` and is ready to publish.

4. **Admin Location DNA regeneration tool** — Add a route + controller action that dispatches `ComputeLocationDna` for a specific listing, accessible from the admin DNA inspector (once Phase 2 is built).

---

## Summary Table

| Dimension | Status |
|---|---|
| Property DNA pipeline (seller + landlord) | Fully operational — fires on every listing save |
| Location DNA services | Built and tested in isolation — no trigger exists |
| Location data visible in any UI | No — zero Blade view references |
| Demand DNA pipeline (buyer + tenant) | Fully operational — fires on every buyer/tenant listing save |
| Compatibility scoring (8 of 14 dimensions) | Operational — fires on DNA profile save; 6 dimensions ineligible |
| Consumer compatibility report view | Controller built — Blade template missing |
| Deterministic marketing brief (Phase R) | Always available on demand — never persisted |
| AI marketing report pipeline | Operational — admin trigger only; no regeneration after rejection |
| Location context in AI report | Built, not connected — governance deferred |
| Buyer/tenant context in AI report | Built, not connected |
| Published report delivery to listing page | No delivery path exists |
| Seller-facing intelligence view | Does not exist |
| Agent buyer audience alignment view | Admin-only — not exposed to agents |
| Public POI / lifestyle data display | Does not exist |
| Notification system | Does not exist |
| Any admin tooling for Location DNA | Does not exist |
