# Agent AI Assistant V2 — Context Source Map & Cost Analysis

**Status:** Draft for review  
**Audit Date:** June 15, 2026  
**Scope:** All five V2 context scopes across four listing types plus agent profile  
**Basis:** Verified against current production code and live PostgreSQL schema  

> **No V2 code should be built until this context-source map is reviewed and approved.**

### V2 Build Roadmap

This audit is Phase 1 of the following approved ticket sequence. References to specific build numbers in this document use the ticket numbers below.

| Ticket | Purpose |
|---|---|
| #2776 | **Context Source Audit** ← this document |
| #2777 | Skeleton + Feature Flag |
| #2779 | Context Loaders |
| #2780 | Conversation Layer + Permission Guard + OpenAI |
| #2781 | CTA Resolver |
| #2782 | Lead Capture + Inbox + Scoring |
| #2783 | Chat Modal UI |
| #2784 | Tests + Safety |
| #2785 | Rollout + Analytics |

### OpenAI Integration Status

> OpenAI integration is intentionally not implemented in this audit. This document only defines the context contract and data source map. Live OpenAI connectivity will be validated during Build #2780 through a real end-to-end API call using the configured model. Future reviewers should not interpret the absence of an OpenAI smoke test in this document as an omission — it belongs in #2780, not here.

---

### Related Governance Documents

| Document | Purpose |
|---|---|
| `docs/audits/AGENT_AI_V2_MVP_SUCCESS_CRITERIA.md` | Defines the release-gate criteria that must be satisfied before Agent AI V2 is considered MVP-ready |

> **Important:** Completion of this context-source audit (#2776) does not mean Agent AI V2 is MVP-ready. MVP readiness is governed exclusively by `AGENT_AI_V2_MVP_SUCCESS_CRITERIA.md` and requires successful completion of Builds 1–8 (#2777–#2784) plus all release-gate criteria defined in that document.

---

## Table of Contents

1. [Current Ask AI Service Inventory](#1-current-ask-ai-service-inventory)
2. [Context Scope: Seller Public Listing Chat](#2-context-scope-seller-public-listing-chat)
3. [Context Scope: Landlord Public Listing Chat](#3-context-scope-landlord-public-listing-chat)
4. [Context Scope: Buyer Criteria Chat](#4-context-scope-buyer-criteria-chat)
5. [Context Scope: Tenant Criteria Chat](#5-context-scope-tenant-criteria-chat)
6. [Context Scope: Agent Profile Chat](#6-context-scope-agent-profile-chat)
7. [Agent Default Profile Data Map](#7-agent-default-profile-data-map)
8. [Ask AI Knowledge Tables](#8-ask-ai-knowledge-tables)
9. [Token Budget & Cost Analysis](#9-token-budget--cost-analysis)
10. [V2 Context Fragment Contract](#10-v2-context-fragment-contract)
11. [Context Compression & Caching Strategy](#11-context-compression--caching-strategy)
12. [Privacy Boundary Table](#12-privacy-boundary-table)
13. [Gaps & Missing Fields](#13-gaps--missing-fields)
14. [Future Channel Support (Reserved)](#14-future-channel-support-reserved)
15. [Future Context Source: Uploaded Listing Documents / Disclosure Documents](#15-future-context-source-uploaded-listing-documents--disclosure-documents)
16. [Future Context Source: MLS Import Snapshot / Raw Parsed MLS Payload](#16-future-context-source-mls-import-snapshot--raw-parsed-mls-payload)
17. [Future Context Source: Knowledge Documents / Educational Content Library](#17-future-context-source-knowledge-documents--educational-content-library)

---

## 1. Current Ask AI Service Inventory

All services reside under `app/Services/AskAi/`. Each is governed by an internal `MUST NEVER` block that prohibits writing, external HTTP calls, inventing data, and fair-housing violations.

### 1.1 AskAiRunnerV2Service (4,704 lines)

**Role:** End-to-end pipeline orchestrator. Chains all pipeline services in sequence.

**Pipeline order:**
1. `AskAiQuestionClassifierService::classify()` — deterministic classification
2. Optional `AskAiIntentNormalizerService::normalize()` — feature-flagged OpenAI normalization
3. Optional description-fallback path (step 1a-desc) — fires for unsupported questions when a listing description is available
4. `AskAiInternalRunnerService::run()` — builds context → contract → prompt package
5. `AskAiOpenAiAdapterService` — calls OpenAI with the prompt package
6. `AskAiKnowledgeSearchService` — Phase 4 database-first answer search (fires before OpenAI when a snapshot answer exists)
7. `AskAiFinalResponseBuilderService::build()` — normalizes the raw adapter output
8. `AskAiFollowUpQuestionService` — appends follow-up chips

**Key internal data structures:**
- `FAQ_KEY_KEYWORD_MAP` — maps keyword phrases to `faq_answers.*` canonical paths (seller and landlord FAQ keys; ~50 entries)
- `LISTING_KEY_KEYWORD_MAP` — maps keyword phrases to `listing.*` canonical paths; used by `detectListingFieldKey()` for deterministic Guard B routing without OpenAI

**Limitations V2 must resolve:**
- Stateless per-call — no multi-turn conversation memory
- One listing context per call — no cross-listing or portfolio-level questions
- All four listing types share the same code path; no specialized agent-profile path exists
- Description fallback (step 1a-desc) is a plain text dump — no structured parsing or fragment prioritization
- The runner returns a flat array; no token count is reported back to the caller

---

### 1.2 AskAiQuestionClassifierService (1,451 lines)

**Role:** Deterministic keyword-based classifier. No external calls. Returns `{question_type, confidence, reason}`.

**Question types classified (in priority order):**
1. `prohibited` — fair-housing violations; always blocks before any other check
2. `listing_facts` — factual lookup for listing fields and FAQ answers
3. `compatibility_signals` — match score/compatibility questions
4. `buyer_tenant_match` — buyer or tenant criteria questions
5. `property_standout` — property highlights/DNA questions
6. `suited_audience` — who is this property suited for
7. `offer_analysis` — bid/offer related questions
8. `missing_data` — questions about unavailable data
9. `unsupported` — fallback when nothing matches (triggers optional normalizer)

**Limitations V2 must resolve:**
- Keyword list is static — novel phrasings not in the list fall through to `unsupported` even when answerable
- Order-sensitive: wrong classification order causes silent mis-routing (documented as "boundary collision" risk in memory)
- No role-awareness — buyer and seller share the same keyword list; a question about "monthly rent" always routes to `listing_facts` even on a seller listing where it is inapplicable
- No score or confidence threshold for borderline matches

---

### 1.3 AskAiIntentNormalizerService (555 lines)

**Role:** Feature-flagged LLM-based intent normalizer for `unsupported` questions. Calls OpenAI with a tightly constrained prompt (max_tokens=80, timeout=10s). Returns one of: `{status: matched, context_path}` | `{status: unsupported}` | `{status: prohibited}`.

**Flag:** `ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION=true` (Replit platform-level env var, not `.env`).

**Hallucination guard:** Any `context_path` not present in `buildKnownFieldKeys()` is rejected with `invalid_key` status.

**Limitations V2 must resolve:**
- Only fires for `unsupported` questions — cannot improve mis-classified `listing_facts` questions
- Adds a second OpenAI round-trip (extra latency + cost) when triggered
- `buildKnownFieldKeys()` combines `getListingFactsAllowedPaths()` + FAQ config keys — any V2 new field must be added here or the normalizer cannot route to it
- No role filtering in `buildKnownFieldKeys()` — keys from all roles are present; OpenAI may route a landlord question to a seller-only field

---

### 1.4 AskAiContextBuilderService (1,715 lines)

**Role:** Read-only context assembly layer. Gathers approved structured data from listing models, DNA profiles, location intelligence, compatibility scores, and offer analysis into a single safe context object.

**Output contract keys:**
```
success, listing_type, listing_id, context_version (ASK_AI_CONTEXT_V1),
status (assembled|partial|not_found|failed),
listing, faq_answers, property_intelligence, location_intelligence,
buyer_avatar, tenant_avatar, compatibility, offer_analysis,
missing_sources, warnings, source_versions, assembled_at, error
```

**Data sources assembled:**
- `listing` — from `extractListingFields()` + `extractFactualFields()` (EAV + native columns)
- `faq_answers` — from `listing_ai_faq` EAV meta key (primary) or `ai_faq_answers` table (fallback)
- `property_intelligence` — from `PropertyDnaProfile` via `PropertyIntelligenceProfileService::buildPayloadReadOnly()` (seller + landlord only)
- `location_intelligence` — from `PropertyLocationDna` via `LocationDnaIntelligenceContextService` + `LocationDnaMarketingContextService`
- `buyer_avatar` — from `BuyerTenantDnaProfile` (buyer listings only)
- `tenant_avatar` — from `BuyerTenantDnaProfile` (tenant listings only)
- `compatibility` — from `ListingCompatibilityScore` (when pair options provided)
- `offer_analysis` — from `AcceptedBidSummary` (V1 only; **excluded from all V2 public chat scopes** — see Section 12.1)

**Limitations V2 must resolve:**
- No agent profile context — `agent_default_profiles.profile_data` is never loaded
- No token budget enforcement — context is assembled wholesale regardless of size
- No caching — full context rebuilt on every question call
- `buildChipContext()` (lightweight path for suggested questions) does not populate `property_intelligence`, `location_intelligence`, or avatar data
- `property_intelligence` is only available when a `PropertyDnaProfile` row exists — new listings always miss this source
- Location intelligence is optional; its absence produces a warning but not a contract failure

---

### 1.5 AskAiResponseContractService (521 lines)

**Role:** Deterministic governance contract layer. Maps question types to allowed context fields (`allowed_context`), required sources (`required_sources`), response rules, and required disclosures.

**Defined question-type contracts:**
- `property_standout` — requires `property_intelligence`; allows property highlights, strengths, story, location narrative
- `suited_audience` — requires `property_intelligence`; allows target audiences, personality tags, avatar data
- `buyer_tenant_match` — requires `compatibility`; allows avatar match preferences, compatibility highlights
- `compatibility_signals` — allows compatibility data
- `listing_facts` — requires `listing`; allows listing.* paths and faq_answers.* paths (role-filtered)
- `prohibited` — always returns `refusal_required` with fair-housing refusal template
- `unsupported` — returns `unsupported` contract status
- `offer_analysis` — exists in V1 code but is **permanently excluded from all V2 public chat scopes** (see Section 12.1); no bid, offer, counteroffer, accepted-bid-summary, or compensation negotiation data may enter any V2 public context
- `missing_data` — allows listing metadata only

**Limitations V2 must resolve:**
- No `agent_profile_chat` contract exists
- `listing_facts` contract does not scope allowed paths by role — a buyer question could theoretically access seller-only context paths if not guarded elsewhere
- Missing-data contract is very narrow — cannot answer "what information is still needed?"
- No versioned contract per listing type — all seller and landlord questions use the same contract despite having different field sets

---

### 1.6 AskAiPromptBuilderService (524 lines)

**Role:** Deterministic prompt package assembler. Consumes context + contract and produces a governed prompt package.

**System instructions:** 12 static governance strings (no external calls, no protected classes, no invented data, attribution required, disclosures required).

**Status outputs:** `prompt_ready` | `blocked` | `insufficient_context` | `unsupported` | `failed`

**Limitations V2 must resolve:**
- `filterAllowedContext()` does a simple dot-notation key filter — no truncation or token budgeting
- Entire `faq_answers` array is passed through when the contract path is `faq_answers` (no field-level filtering unless a specific `faq_answers.<key>` path is in the contract)
- No per-question context size limit — a listing with 100 FAQ answers and full property intelligence could produce a 10,000+ token context
- No fragment-level priority — all allowed context paths are included or excluded together; no graceful degradation when context is large

---

### 1.7 AskAiKnowledgeSnapshotBuilderService (245 lines)

**Role:** Orchestrates versioned knowledge snapshot creation. Delegates to role-specific builders (`SellerSnapshotBuilder`, `BuyerSnapshotBuilder`, `LandlordSnapshotBuilder`, `TenantSnapshotBuilder`).

**Concurrency guard:** Unique index on `(listing_type, listing_id, version)` + retry loop (max 5 retries) using `orderByDesc('version')->value('version')` (not `max()` — PostgreSQL safe).

**Triggered by:** Listing save events (silently — exceptions never interrupt the listing save).

**Limitations V2 must resolve:**
- Snapshot content is static post-build — no partial invalidation when one EAV key changes
- `buildSilently()` swallows all exceptions — failed snapshots may not be noticed until the answer search path returns empty
- No TTL or staleness check — snapshots from months ago are served without an age warning
- Snapshot builder role delegates are in `app/Services/AskAi/Snapshot/` — their exact field inventories are outside this audit's direct scope but must be aligned with the field maps in sections 2–5

---

### 1.8 AskAiFinalResponseBuilderService (170 lines)

**Role:** Pure transformation layer. Converts prompt package + adapter result into the final public response. Handles status routing and text normalization only.

**Output contract:** `{success, status, answer, disclosures, source_attribution, refusal_message, error}`

**Text normalization:** Trims, collapses horizontal whitespace, preserves newlines.

**Limitations V2 must resolve:**
- No answer length enforcement — model could return multi-paragraph answers with no truncation
- No citation formatting — `source_attribution` is passed through as-is from the prompt builder; V2 may need structured citation rendering
- No language detection or locale handling

---

### 1.9 AskAiApiController (122 lines)

**Role:** Channel-agnostic HTTP entry point. Two routes:
- `POST /ask-ai/ask` — web middleware (session/CSRF), open to authenticated + guest
- `POST /api/ask-ai/ask` — `auth:sanctum` middleware

**Accepted channels:** `web`, `sms`, `messenger`, `whatsapp`, `mobile`, `crm`

**Input validation:** `listing_type` (string), `listing_id` (integer), `question` (string, max:1000), `options` (array), `channel` (string enum), `session_id` (string, max:255)

**Throttling:** `throttle:ask-ai-api` rate limit group (both routes)

**Limitations V2 must resolve:**
- `session_id` is accepted but not persisted or used — no conversation continuity
- `options` is an open array with no validation schema — V2 must define a strict options contract
- No authentication required on the web route — unauthenticated users can query any listing by ID
- No input sanitization beyond `max:1000` length — prompt injection is a risk
- No response streaming — all responses are buffered JSON

---

### 1.10 Supporting Services (summary)

| Service | Role |
|---|---|
| `AskAiInternalRunnerService` | Phases 1–3 orchestrator (context→contract→prompt) |
| `AskAiOpenAiAdapterService` | OpenAI HTTP adapter with retry/timeout logic |
| `AskAiKnowledgeSearchService` | Database-first snapshot answer lookup (Phase 4) |
| `AskAiUsageLoggerService` | Logs token counts, cost, model, outcome to `ask_ai_usage_logs` |
| `AskAiRateLimitService` | Per-IP/user rate limiting |
| `AskAiDisclosureRegistry` | Static registry of disclosure templates by question type |
| `AskAiFaqEnrichmentService` | Enriches raw FAQ answers with config metadata |
| `AskAiFieldQuestionRegistryService` | Registry of field paths with labels and sample questions |
| `AskAiKnowledgeSourceRegistry` | Registry of source keys → labels + version keys |
| `AskAiSuggestedQuestionsService` | Generates suggested question chips for the UI |
| `AskAiFollowUpQuestionService` | Appends follow-up question chips to completed answers |
| `AskAiTestHarnessService` | Admin test harness (non-production) |

---

## 2. Context Scope: Seller Public Listing Chat

**Scope name:** `public_listing_agent_chat` (role = seller)  
**Page URL pattern:** `/seller-agent-auction/{id}` (public listing view page)  
**Controller/Livewire:** `AskAiApiController::ask` (via POST from the listing view Blade/JS)  
**Model:** `App\Models\SellerAgentAuction`  
**Tables:** `seller_agent_auctions` (native) + `seller_agent_auction_metas` (EAV)

### 2.1 Native Columns — seller_agent_auctions

| Column | Type | Ask AI Access | Notes |
|---|---|---|---|
| id | bigint | Internal only | PK |
| user_id | bigint | Private | Owner FK — never expose |
| address | varchar(255) | Public | Exposed as `listing.address` |
| auction_type | varchar(255) | Internal only | Workflow type |
| auction_length | integer | Public | Exposed as `listing.auction_length` |
| city_id | bigint | Internal only | FK resolved via meta |
| county_id | bigint | Internal only | FK resolved via meta |
| state_id | bigint | Internal only | FK resolved via meta |
| bathroom_id | bigint | Internal only | FK fallback for bathrooms |
| bedroom_id | bigint | Internal only | FK fallback for bedrooms |
| sqft | varchar(255) | Internal only | Legacy; superseded by EAV `minimum_heated_square` |
| min_price | double | Internal only | Legacy; superseded by EAV `maximum_budget` |
| max_commission | double | Private | Agent compensation — never expose |
| financings | json | Internal only | Legacy FK list |
| additional_services | text | Private | Agent services text |
| important_info | text | Private | Internal notes |
| contract_terms | text | Private | Internal workflow |
| description | text | Public | Exposed as `listing.description` |
| prop_condition | text | Conditionally public | General condition; already surfaced in FAQ |
| description_ideal_agent | text | Private | Internal agent search criteria |
| need_cma | varchar(255) | Private | Internal |
| photos | json | Internal only | Not surfaced in chat context |
| video_url | varchar(255) | Internal only | Not surfaced |
| video_file | varchar(255) | Internal only | Not surfaced |
| audio_file | varchar(255) | Internal only | Not surfaced |
| is_approved | varchar(255) | Internal only | Workflow status |
| is_sold | varchar(255) | Internal only | Resolved to `listing.sold` |
| is_paid | varchar(255) | Private | Payment status |
| sold_date | timestamp | Internal only | |
| created_at | timestamp | Public | Exposed as `listing.created_at` |
| updated_at | timestamp | Public | Exposed as `listing.updated_at` |
| listing_id | varchar(20) | Public | Unique listing reference ID |
| title | varchar(255) | Public | Exposed as `listing.listing_title` |
| is_draft | boolean | Internal only | |
| referring_agent_id | bigint | Private | Referral chain |
| referral_source_code | varchar(255) | Private | |
| referral_captured_at | timestamp | Private | |
| referral_locked | boolean | Private | |

### 2.2 EAV Keys — seller_agent_auction_metas

The seller role stores almost all property detail in EAV via `saveMeta()`. The following table documents the EAV keys read by `AskAiContextBuilderService::extractFactualFields()` for the seller role.

| EAV Meta Key | Context Output Key | Access | Notes |
|---|---|---|---|
| `maximum_budget` | `asking_price` | Public | Desired Sale Price |
| `buy_now_price` | `buy_now_price` | Public | Fixed-price offer |
| `bedrooms` | `bedrooms` | Public | With `other_bedrooms` fallback |
| `other_bedrooms` | (fallback) | Public | Free-text when bedrooms="Other" |
| `bathrooms` | `bathrooms` | Public | With `other_bathrooms` fallback |
| `other_bathrooms` | (fallback) | Public | |
| `minimum_heated_square` | `square_feet` | Public | Primary sqft key |
| `heated_square_footage` | `square_feet` | Public | Offer-form fallback |
| `heated_square` | `square_feet` | Public | Legacy fallback |
| `year_built` | `year_built` | Public | |
| `pool_needed` | `pool` | Public | |
| `pool_type` | `pool_type` | Public | JSON decoded |
| `carport_needed` | `carport` | Public | |
| `other_carport_needed` | (fallback) | Public | |
| `garage_needed` | `garage` | Public | |
| `other_garage` | (fallback) | Public | |
| `other_garage_needed` | (fallback) | Public | |
| `garage_parking_spaces` | `garage_spaces`, `parking_spaces` | Public | |
| `water_view` | `water_view` | Public | JSON decoded; falls back to `view_preference` |
| `view_preference` | `water_view` | Public | Legacy fallback |
| `total_acreage` | `lot_size`, `total_acreage` | Public | |
| `min_acreage` | `lot_size` | Public | Fallback |
| `lot_dimensions` | `lot_dimensions` | Public | |
| `zoning` | `zoning` | Public | |
| `waterfront` | `waterfront` | Public | |
| `water_access` | `water_access` | Public | JSON decoded |
| `interior_features` | `interior_features` | Public | JSON decoded |
| `appliances` | `appliances` | Public | JSON decoded |
| `roof_type` | `roof_type` | Public | JSON decoded |
| `exterior_construction` | `exterior_construction` | Public | JSON decoded |
| `foundation` | `foundation` | Public | JSON decoded |
| `heating_and_fuel` | `heating_and_fuel`, `heating_fuel` | Public | JSON decoded |
| `air_conditioning` | `air_conditioning` | Public | JSON decoded |
| `water` | `water`, `water_source` | Public | JSON decoded |
| `sewer` | `sewer` | Public | JSON decoded |
| `utilities` | `utilities` | Public | JSON decoded |
| `sale_provision` | `sale_provision` | Public | JSON decoded |
| `offered_financing` | `offered_financing` | Public | JSON decoded |
| `occupant_status` | `occupant_status` | Public | |
| `building_features` | `furnished`, `building_features` | Public | JSON decoded; `furnished` is filtered subset |
| `has_hoa` | `hoa_association` | Public | |
| `association_fee_amount` | `hoa_fee` | Public | |
| `association_fee_frequency` | `hoa_payment_schedule` | Public | |
| `association_name` | `association_name`, `hoa_name` | Public | |
| `association_fee_includes` | `association_fee_includes` | Public | JSON decoded |
| `has_cdd` | `has_cdd` | Public | |
| `annual_cdd_fee` | `annual_cdd_fee` | Public | |
| `has_special_assessments` | `has_special_assessments` | Public | |
| `additional_parcels` | `additional_parcels` | Public | |
| `total_parcel_count` | `total_parcel_count` | Public | |
| `special_assessment_amount` | `special_assessment_amount` | Public | |
| `special_assessment_description` | `special_assessment_description` | Public | |
| `pets` | `pets_allowed` | Public | |
| `number_of_pets` | `number_of_pets_allowed` | Public | |
| `weight_of_pets` | `max_pet_weight` | Public | |
| `pet_restrictions` | `pet_restrictions` | Public | |
| `leasing_restrictions` | `rental_restrictions` | Public | |
| `flood_zone_code` | `flood_zone_code` | Compliance-Sensitive | Resolves "Other" to `flood_zone_code_other` |
| `flood_zone_code_other` | (fallback) | Compliance-Sensitive | |
| `flood_zone_panel` | `flood_zone_panel` | Compliance-Sensitive | |
| `flood_zone_date` | `flood_zone_date` | Compliance-Sensitive | |
| `flood_insurance_required` | `flood_insurance_required` | Compliance-Sensitive | |
| `parcel_id` | `parcel_id` | Public | |
| `tax_year` | `tax_year` | Public | |
| `legal_description` | `legal_description` | Public | |
| `target_closing_date` | `closing_date` | Public | |
| `annual_property_taxes` | `annual_property_taxes` | Public | |
| `service_type` | `service_type` | Internal only | Full Service vs Limited |
| `total_square_feet` | `building_sqft` | Public | Commercial |
| `ceiling_height` | `ceiling_height` | Public | Commercial |
| `minimum_annual_net_income` | `annual_noi`, `annual_net_income`, `minimum_annual_net_income` | Public | Income property |
| `minimum_cap_rate` | `cap_rate`, `minimum_cap_rate` | Public | Income property |
| `price_per_sqft` | `price_per_sqft` | Public | |
| `existing_lease_type` | `existing_lease_type` | Public | Commercial |
| `lease_expiration` | `lease_expiration` | Public | Commercial |
| `lease_assignable` | `lease_assignable` | Public | Commercial |
| `property_items` | `property_items` | Public | JSON decoded |
| `unit_number` | `total_units` | Public | Income property |
| `unit_buildings` | `total_buildings` | Public | |
| `unit_type_configurations` | `unit_mix_summary` | Public | Summarized |
| `gross_annual_income` | `gross_annual_income` | Public | Income property |
| `annual_operating_expenses` | `annual_operating_expenses` | Public | |
| `rent_roll_available` | `rent_roll_available` | Public | |
| `operating_statement_available` | `operating_statement_available` | Public | |
| `assumable_occupancy_requirement` | `occupancy_requirement` | Public | |
| `monthly_income` | `income_requirement` | Compliance-Sensitive | Occupant qualification |
| `seller_contribution_credit_offered` | `seller_credit_offered` | Public | Yes/No field |
| `property_type` | `property_type` | Public | Via base fields |
| `city` | `city` | Public | Via base fields |
| `state` | `state` | Public | Via base fields |
| `county` | `county` | Public | Via base fields |
| `listing_ai_faq` | (faq_answers map) | Public | JSON blob of Q&A pairs |
| `expiration_date` | (status logic) | Internal only | |
| `listing_status` | `listing_status` | Public | |

**Vacant Land additional EAV keys (when property_type = 'Vacant Land'):**
`current_adjacent_use`, `water_available`, `sewer_available`, `electric_available`, `gas_available`, `telecom_available`, `road_surface_type`, `front_footage`, `number_of_wells`, `number_of_septics`, `fences`, `vegetation`, `buildable`, `easements`, `current_use`, `road_frontage`

**Business Opportunity additional EAV keys (when property_type = 'Business'):**
`business_type`, `other_business_type`, `business_name`, `year_established`, `annual_revenue`, `gross_profit`, `sde_ebitda`, `inventory_value`, `ffe_value`, `reason_for_sale`, `other_reason_for_sale`, `employee_count`, `financial_statements_available`, `tax_returns_available`, `nda_required`, `business_location_leased`, `business_lease_monthly_rent`, `business_lease_expiration`, `business_lease_renewal_options`, `business_lease_assignable`, `business_lease_additional_terms`, `licenses`, `sale_includes`, `electrical_service`, `business_assets`

### 2.3 Seller FAQ Answer Keys

FAQ answers stored under the `listing_ai_faq` EAV meta key as a JSON object. Key names map to the `FAQ_KEY_KEYWORD_MAP` entries in `AskAiRunnerV2Service`. Active seller FAQ keys:

`roof_age_and_condition`, `hvac_system_age`, `water_heater_age_type`, `recent_renovations_list`, `permits_for_renovations`, `known_defects_issues`, `foundation_type_and_issues`, `pest_termite_history`, `flood_damage_history`, `mold_issues_history`, `average_utility_costs`, `internet_utility_providers`, `seller_concessions_offered`, `closing_timeline_flexibility`, `items_excluded_from_sale`, `as_is_condition`, `unique_selling_points`, `parking_arrangements`, `hoa_community_highlights`, `neighborhood_character`, `traffic_or_noise_concerns`, `planned_nearby_development`, `commute_options_access`, `natural_light_orientation`, `nearby_amenities_description`, `neighborhood_restrictions`, `showing_tips_seller`, `property_unique_features`, `neighborhood_highlights`, `hoa_rules_restrictions`

### 2.4 Seller Property Intelligence (property_dna_profiles)

Loaded from `property_dna_profiles` (non-archived, latest `computed_at`) via `PropertyIntelligenceProfileService::buildPayloadReadOnly()`.

| Context Key | Source | Access |
|---|---|---|
| `property_strengths` | DNA profile | Public |
| `property_highlights` | DNA profile | Public |
| `property_positioning` | DNA profile | Public |
| `property_target_audiences` | DNA profile | Public (lifestyle terms only — no protected classes) |
| `property_personality_tags` | DNA profile | Public |
| `property_story` | DNA profile | Public |
| `location_intelligence_context` | Persisted column on DNA profile | Public |
| `property_intelligence_version` | DNA profile | Internal |
| `source_profile_id` | DNA profile | Internal |
| `source_profile_version` | DNA profile | Internal |
| `source_profile_computed_at` | DNA profile | Internal |

---

## 3. Context Scope: Landlord Public Listing Chat

**Scope name:** `public_listing_agent_chat` (role = landlord)  
**Page URL pattern:** `/landlord-agent-auction/{id}`  
**Model:** `App\Models\LandlordAgentAuction`  
**Tables:** `landlord_agent_auctions` (native) + `landlord_agent_auction_metas` (EAV)

### 3.1 Native Columns — landlord_agent_auctions

| Column | Type | Ask AI Access | Notes |
|---|---|---|---|
| id | bigint | Internal only | PK |
| user_id | bigint | Private | |
| auction_type | varchar(255) | Internal only | |
| is_approved | boolean | Internal only | |
| is_draft | boolean | Internal only | |
| is_sold | boolean | Internal only | |
| sold_date | timestamp | Internal only | |
| created_at | timestamp | Public | |
| updated_at | timestamp | Public | |
| display_bids | boolean | Internal only | |
| auction_ended | boolean | Internal only | |
| listing_id | varchar(20) | Public | |
| title | varchar(255) | Public | `listing.listing_title` |
| referring_agent_id | bigint | Private | |
| referral_source_code | varchar(255) | Private | |
| referral_captured_at | timestamp | Private | |
| referral_locked | boolean | Private | |

**Note:** Landlord has far fewer native columns than seller. Almost all property data is EAV-only.

### 3.2 EAV Keys — landlord_agent_auction_metas

| EAV Meta Key | Context Output Key | Access | Notes |
|---|---|---|---|
| `desired_rental_amount` | `rent_amount` | Public | Primary rent key |
| `starting_rent` | `rent_amount` | Public | Fallback |
| `lease_now_price` | `rent_amount` | Public | Fallback |
| `bedrooms` | `bedrooms` | Public | |
| `other_bedrooms` | (fallback) | Public | |
| `bathrooms` | `bathrooms` | Public | |
| `other_bathrooms` | (fallback) | Public | |
| `minimum_heated_square` | `square_feet` | Public | |
| `heated_square_footage` | `square_feet` | Public | Fallback |
| `heated_square` | `square_feet` | Public | Legacy |
| `unit_size` | `unit_size` | Public | |
| `number_of_unit` | `number_of_units` | Public | |
| `property_zip` | `property_zip` | Public | |
| `property_items` | `property_items` | Public | JSON decoded |
| `condition_prop` | `condition_prop` | Public | |
| `other_property_condition` | (fallback) | Public | |
| `appliances` | `appliances` | Public | JSON decoded |
| `water_view` | `water_view`, `view` | Public | JSON decoded; falls back to `view_preference` |
| `view_preference` | (fallback) | Public | |
| `available_date` | `available_date` | Public | |
| `pet_policy` | `pet_policy` | Public | Falls back to `pets` |
| `pets` | `pet_policy` | Public | Legacy fallback |
| `pet_deposit_fee_rent` | `pet_deposit_fee_rent` | Public | |
| `pet_max_weight_lbs` | `pet_max_weight_lbs` | Public | |
| `pet_species_allowed` | `pet_species_allowed` | Public | JSON decoded |
| `parking_terms` | `parking_terms` | Public | |
| `property_utilities` | `utilities` | Public | JSON decoded (primary key) |
| `utilities` | `utilities` | Public | Legacy fallback |
| `smoking_policy` | `smoking_policy` | Public | |
| `subletting_policy` | `subletting_policy` | Public | |
| `has_hoa` | `has_hoa` | Public | |
| `association_name` | `association_name` | Public | |
| `association_fee_amount` | `association_fee_amount` | Public | |
| `association_fee_frequency` | `association_fee_frequency` | Public | |
| `association_amenities` | `association_amenities` | Public | JSON decoded |
| `annual_property_taxes` | `annual_property_taxes` | Public | |
| `leasing_restrictions` | `leasing_restrictions` | Public | |
| `min_lease_period` | `lease_length` | Public | With `min_lease_period_other` fallback |
| `minimum_lease_period` | `lease_length` | Public | Alternate key |
| `min_lease_period_other` | (fallback) | Public | |
| `desired_lease_length` | `lease_length` | Public | JSON decoded; secondary fallback |
| `renewal_option_offered` | `renewal_option` | Public | |
| `number_occupant` | `number_of_occupants` | Public | |
| `additional_landlord_lease_terms` | `additional_lease_terms` | Public | |
| `terms_of_lease` | `lease_terms`, `terms_of_lease` | Public | JSON decoded |
| `commercial_lease_type` | `commercial_lease_type` | Public | |
| `cam_nnn_additional_rent_charges` | `cam_nnn_additional_rent_charges` | Public | |
| `rent_escalation_terms` | `rent_escalation_terms` | Public | |
| `tenant_improvement_buildout_terms` | `tenant_improvement_buildout_terms` | Public | |
| `permitted_use_restrictions` | `permitted_use_restrictions` | Public | |
| `signage_rights` | `signage_rights` | Public | |
| `commercial_parking_terms` | `commercial_parking_terms` | Public | |
| `personal_guarantee_requirement` | `personal_guarantee_requirement` | Public | |
| `commercial_approval_conditions` | `commercial_approval_conditions` | Public | |
| `parcel_id` | `parcel_id` | Public | |
| `tax_year` | `tax_year` | Public | |
| `legal_description` | `legal_description` | Public | |
| `additional_parcels` | `additional_parcels` | Public | |
| `total_parcel_count` | `total_parcel_count` | Public | |
| `additional_parcel_ids` | `additional_parcel_ids` | Public | |
| `year_built` | `year_built` | Public | |
| `lot_dimensions` | `lot_dimensions` | Public | |
| `zoning` | `zoning` | Public | |
| `waterfront` | `waterfront` | Public | |
| `water_access` | `water_access` | Public | JSON decoded |
| `interior_features` | `interior_features` | Public | JSON decoded |
| `roof_type` | `roof_type` | Public | JSON decoded |
| `exterior_construction` | `exterior_construction` | Public | JSON decoded |
| `foundation` | `foundation` | Public | JSON decoded |
| `flood_zone_code` | `flood_zone_code` | Compliance-Sensitive | |
| `flood_zone_panel` | `flood_zone_panel` | Compliance-Sensitive | |
| `flood_zone_date` | `flood_zone_date` | Compliance-Sensitive | |
| `flood_insurance_required` | `flood_insurance_required` | Compliance-Sensitive | |
| `security_deposit_amount` | `security_deposit_amount` | Compliance-Sensitive | |
| `tenant_pays` | `tenant_pays` | Public | JSON decoded |
| `rent_includes` | `rent_includes` | Public | JSON decoded |
| `heating_fuel` | `heating_fuel` | Public | JSON decoded |
| `air_conditioning` | `air_conditioning` | Public | JSON decoded |
| `water` | `water` | Public | JSON decoded |
| `sewer` | `sewer` | Public | JSON decoded |
| `lease_amount_frequency` | `lease_amount_frequency` | Public | |
| `has_cdd` | `has_cdd` | Public | |
| `annual_cdd_fee` | `annual_cdd_fee` | Public | |
| `sqft_heated_source` | `sqft_heated_source` | Public | |
| `first_month_rent_required` | `first_month_rent_required` | Public | Move-in funds |
| `last_month_rent_required` | `last_month_rent_required` | Public | |
| `total_move_in_funds_required` | `total_move_in_funds_required` | Public | |
| `min_income_requirement` | `min_income_requirement` | Compliance-Sensitive | Occupant qualification |
| `ll_maintenance_responsibility` | `ll_maintenance_responsibility` | Public | |
| `renewal_option_details` | `renewal_option_details` | Public | |
| `landlord_approval_conditions` | `landlord_approval_conditions` | Public | |
| `pet_deposit_amount` | `pet_deposit_amount` | Public | |
| `pet_monthly_fee` | `pet_monthly_fee` | Public | |
| `number_of_occupants_allowed` | `number_of_occupants_allowed` | Public | |
| `city` | `city` | Public | Base field |
| `state` | `state` | Public | Base field |
| `county` | `county` | Public | Base field |
| `property_type` | `property_type` | Public | Base field |
| `listing_ai_faq` | (faq_answers map) | Public | |

### 3.3 Landlord FAQ Answer Keys

`heating_cooling_system`, `laundry_situation`, `maintenance_request_response_time`, `emergency_maintenance_available`, `security_features`, `pest_or_mold_history`, `lease_renewal_process`, `subletting_allowed`, `smoking_policy`, `what_makes_property_unique`, `appliances_included`, `hoa_rules_restrictions`, `neighborhood_highlights`, `landlord_responsibilities`, `tenant_rules_regulations`, `lease_renewal_terms`, `utility_setup_instructions`, `pet_policy_details`, `parking_instructions`

---

## 4. Context Scope: Buyer Criteria Chat

**Scope name:** `buyer_criteria_agent_chat`  
**Page URL pattern:** `/buyer-agent-auction/{id}`  
**Model:** `App\Models\BuyerAgentAuction`  
**Tables:** `buyer_agent_auctions` (native) + `buyer_agent_auction_metas` (EAV)

### 4.1 Native Columns — buyer_agent_auctions

| Column | Type | Ask AI Access | Notes |
|---|---|---|---|
| id | bigint | Internal only | |
| user_id | bigint | Private | |
| address | varchar(255) | Public | `listing.address` |
| title | varchar(255) | Public | `listing.listing_title` |
| auction_type | varchar(255) | Internal only | |
| auction_length | integer | Internal only | |
| city_id | bigint | Internal only | FK resolved via meta |
| county_id | bigint | Internal only | |
| state_id | bigint | Internal only | |
| bathroom_id | bigint | Internal only | FK fallback |
| bedroom_id | bigint | Internal only | FK fallback |
| property_type_id | bigint | Internal only | FK |
| concession | double | Private | Agent compensation |
| financing_currency | json | Internal only | Legacy |
| financing_approved | varchar(255) | Internal only | Legacy flag |
| need_lender | varchar(255) | Private | |
| preapproval_amount | double | Private | Financial PII |
| additional_details | text | Public | `listing.description` |
| other | json | Internal only | Legacy |
| cash_budget | varchar(255) | Private | Financial detail |
| crypto_budget | varchar(255) | Private | Financial detail |
| is_approved | varchar(255) | Internal only | |
| is_sold | varchar(255) | Internal only | |
| is_paid | varchar(255) | Private | |
| created_at | timestamp | Public | |
| updated_at | timestamp | Public | |
| listing_id | varchar(20) | Public | |
| is_draft | boolean | Internal only | |
| referring_agent_id | bigint | Private | |

### 4.2 EAV Keys — buyer_agent_auction_metas

| EAV Meta Key | Context Output Key | Access | Notes |
|---|---|---|---|
| `maximum_budget` | `max_price` | Public | Buyer's max purchase budget |
| `bedrooms` | `bedrooms` | Public | |
| `other_bedrooms` | (fallback) | Public | |
| `bathrooms` | `bathrooms` | Public | |
| `other_bathrooms` | (fallback) | Public | |
| `minimum_heated_square` | `square_feet` | Public | |
| `heated_square_footage` | `square_feet` | Public | Fallback |
| `heated_square` | `square_feet` | Public | Legacy |
| `pool_needed` | `pool` | Public | |
| `carport_needed` | `carport` | Public | |
| `other_carport_needed` | (fallback) | Public | |
| `garage_needed` | `garage` | Public | |
| `other_garage` / `other_garage_needed` | (fallback) | Public | |
| `garage_parking_spaces` | `garage_spaces` | Public | |
| `view_preference` | `water_view` | Public | JSON decoded (`water_view` key does not exist in buyer metas per live-DB audit) |
| `hoa_acceptance` | `hoa_acceptable` | Public | |
| `hoa_max_monthly_fee` | `max_hoa_fee` | Public | |
| `pets` | `pets_allowed` | Public | |
| `type_of_pets` | `pets_detail` | Public | |
| `breed_of_pets` | `pets_breed` | Public | |
| `weight_of_pets` | `pets_weight` | Public | |
| `pre_approved` | `loan_pre_approved` | Public | Yes/No |
| `financing_type` | `financing_type` | Public | JSON decoded (primary key; `offered_financing` always null per live-DB audit) |
| `inspection_period_days` | `inspection_period` | Public | |
| `target_closing_date` | `closing_date` | Public | |
| `inspection_contingency_buyer` | `inspection_contingency_buyer` | Public | |
| `appraisal_contingency_buyer` | `appraisal_contingency_buyer` | Public | |
| `financing_contingency_buyer` | `financing_contingency_buyer` | Public | |
| `cities` | `cities` | Public | JSON decoded |
| `counties` | `counties` | Public | JSON decoded |
| `city` | `city` | Public | Base field |
| `state` | `state` | Public | Base field |
| `county` | `county` | Public | Base field |
| `property_type` | `property_type` | Public | Base field |
| `listing_ai_faq` | (faq_answers map) | Public | |

**Private fields not exposed:** `pre_approved` loan amount details, `concession`, `cash_budget`, `crypto_budget`, `preapproval_amount`.

### 4.3 Buyer Avatar (buyer_tenant_dna_profiles)

| Context Key | Source Column | Access |
|---|---|---|
| `avatar_type` | `avatar_type` | Public |
| `primary_motivation` | `primary_motivation` | Public |
| `secondary_motivation` | `secondary_motivation` | Public |
| `buyer_narrative` | `buyer_narrative` | Public |
| `buyer_preference_summary` | `buyer_preference_summary` | Public |
| `buyer_personality_tags` | `buyer_personality_tags` | Public |
| `buyer_match_preferences` | `buyer_match_preferences` | Public |
| `avatar_confidence_score` | `avatar_confidence_score` | Internal |
| `buyer_avatar_version` | `buyer_avatar_version` | Internal |
| `buyer_readiness_score` | `buyer_readiness_score` | Internal |

**Note:** `lifestyle_tags`, `deal_breaker_flags`, `commute_polygon_cache` are NOT currently surfaced in the chat context.

---

## 5. Context Scope: Tenant Criteria Chat

**Scope name:** `tenant_criteria_agent_chat`  
**Page URL pattern:** `/tenant-agent-auction/{id}`  
**Model:** `App\Models\TenantAgentAuction`  
**Tables:** `tenant_agent_auctions` (native) + `tenant_agent_auction_metas` (EAV)

### 5.1 Native Columns — tenant_agent_auctions

| Column | Type | Ask AI Access | Notes |
|---|---|---|---|
| id | bigint | Internal only | |
| user_id | bigint | Private | |
| auction_type | varchar(255) | Internal only | |
| is_approved | boolean | Internal only | |
| is_draft | boolean | Internal only | |
| is_sold | boolean | Internal only | |
| sold_date | timestamp | Internal only | |
| auction_ended | boolean | Internal only | |
| created_at | timestamp | Public | |
| updated_at | timestamp | Public | |
| listing_id | varchar(20) | Public | |
| title | varchar(255) | Public | |
| referring_agent_id | bigint | Private | |

**Note:** Tenant has fewer native columns than buyer. No `address` or `additional_details` native column.

### 5.2 EAV Keys — tenant_agent_auction_metas

| EAV Meta Key | Context Output Key | Access | Notes |
|---|---|---|---|
| `budget` | `max_rent` | Public | Primary tenant budget key |
| `maximum_budget` | `max_rent` | Public | Fallback |
| `bedrooms` | `bedrooms` | Public | |
| `other_bedrooms` | (fallback) | Public | |
| `bathrooms` | `bathrooms` | Public | |
| `other_bathrooms` | (fallback) | Public | |
| `desired_lease_length` | `desired_lease_length` | Public | JSON decoded |
| `lease_for` | `desired_lease_length` | Public | Fallback |
| `property_items` | `property_items` | Public | JSON decoded |
| `appliances` | `appliances` | Public | JSON decoded |
| `condition_prop` | `condition_prop` | Public | |
| `other_property_condition` | (fallback) | Public | |
| `pet_information` | `pet_information` | Public | |
| `parking_needed` | `parking_needed` | Public | |
| `utilities` | `utilities` | Public | |
| `utility_preference` | `utility_preference` | Public | |
| `tenant_pays` | `tenant_pays` | Public | JSON decoded |
| `current_status` | `current_status` | Public | |
| `number_of_occupants` | `number_of_occupants` | Public | |
| `number_of_unit` | `number_of_units` | Public | |
| `credit_score_range` | `credit_score_range` | Public | |
| `credit_score` | `credit_score_range` | Public | Fallback |
| `monthly_income` | `monthly_income` | Conditionally public | Tenant household income |
| `household_monthly_income` | `monthly_income` | Conditionally public | Alternate key |
| `cities` | (via buyer path) | Public | If populated |
| `counties` | (via buyer path) | Public | If populated |
| `additional_details` | `description` | Public | Via context builder |
| `listing_ai_faq` | (faq_answers map) | Public | |

**Note on `monthly_income`:** The tenant's income is used for qualification purposes. Classified as conditionally public — it is the tenant's own stated income submitted to attract agent bids, not a landlord-imposed requirement.

### 5.3 Tenant Avatar (buyer_tenant_dna_profiles)

| Context Key | Source Column | Access |
|---|---|---|
| `avatar_type` | `avatar_type` | Public |
| `tenant_narrative` | `tenant_narrative` | Public |
| `tenant_preference_summary` | `tenant_preference_summary` | Public |
| `tenant_personality_tags` | `tenant_personality_tags` | Public |
| `tenant_match_preferences` | `tenant_match_preferences` | Public |
| `tenant_avatar_version` | `tenant_avatar_version` | Internal |

---

## 6. Context Scope: Agent Profile Chat

**Scope name:** `agent_profile_chat`  
**Page URL patterns:** `/hire/{agentShortId}/{role}/{propertyType?}` (public hire page), `/widget/hire/{agentShortId}/{role}/{propertyType}` (embeddable widget)  
**Model:** `App\Models\AgentDefaultProfile` + `App\Models\User`  
**Tables:** `agent_default_profiles`, `users`

### 6.1 Current Implementation Status

**⚠️ GAP: `agent_profile_chat` does not exist in the current system.**

`AskAiContextBuilderService` has no path for `agent_profile_chat`. The context builder only handles the four listing type scopes (`seller`, `buyer`, `landlord`, `tenant`). No controller, route, or contract exists for agent-profile-level questions. The context source map documented in this section describes the V2 design target — not current behavior.

### 6.2 Data Sources for V2 Agent Profile Context

**From `agent_default_profiles.profile_data` (JSONB):** See Section 7 for complete key inventory and public/private classification.

**From `users` table (public-safe fields only):**

| Column | Access | Notes |
|---|---|---|
| `first_name` | Public | Display name |
| `last_name` | Public | Display name |
| `short_id` | Public | Used in hire URL |
| `user_type` | Internal only | |
| `created_at` | Internal only | Account age — do not expose |
| `email` | Private | Never expose |
| `phone` | Private | Never expose |

---

## 7. Agent Default Profile Data Map

**Table:** `agent_default_profiles`  
**Schema:** `id`, `user_id`, `role_type`, `property_type`, `profile_data` (jsonb), `created_at`, `updated_at`  
**Unique index:** `(user_id, role_type, property_type)`

### 7.1 profile_data Key Inventory

The following keys were verified from the live database via `SELECT DISTINCT jsonb_object_keys(profile_data)`. Keys are grouped by category.

#### Identity & Credentials (Public-Safe)

| Key | Type | Public-Safe | Notes |
|---|---|---|---|
| `first_name` | string | Yes | Display name |
| `last_name` | string | Yes | Display name |
| `bio` | text | Yes | Agent bio for hire page |
| `license_no` | string | Yes | License number |
| `nar_id` | string | Yes | NAR membership ID |
| `brokerage` | string | Yes | Brokerage name |
| `year_licensed` | number | Yes | |
| `years_experience` | number | Yes | |
| `is_full_time` | boolean | Yes | Full-time agent flag |
| `transactions_last_12_months` | number | Yes | |
| `awards_recognition` | text | Yes | |
| `what_sets_you_apart` | text | Yes | |
| `why_hire_you` | text | Yes | |
| `review_1`, `review_2`, `review_3` | text | Yes | Agent reviews |
| `reviews_links` | string | Yes | |
| `intro_video_url` | string | Yes | |
| `website_link` | string | Yes | |
| `social_media` | json | Yes | |
| `presentation_link` | string | Yes | |

#### Contact (Private)

| Key | Type | Public-Safe | Notes |
|---|---|---|---|
| `email` | string | **No** | Direct contact — never expose in chat |
| `phone` | string | **No** | Direct contact — never expose |
| `business_card_upload_path` | string | **No** | File path — internal |
| `business_card_link` | string | **No** | Could contain personal contact info |

#### Availability & Communication (Public-Safe)

| Key | Type | Public-Safe | Notes |
|---|---|---|---|
| `availability_status` | string | Yes | |
| `avg_response_time` | string | Yes | |
| `communication_style` | string | Yes | |
| `preferred_contact_method` | string | Yes | Method (not value) |
| `evenings_available` | boolean | Yes | |
| `weekends_available` | boolean | Yes | |

#### Service Area (Public-Safe)

| Key | Type | Public-Safe | Notes |
|---|---|---|---|
| `cities_served` | json/string | Yes | |
| `counties_served` | json/string | Yes | |
| `neighborhoods_served` | json/string | Yes | |
| `primary_areas_served` | json/string | Yes | |
| `areas_notes` | text | Yes | |

#### Services Offered (Public-Safe)

| Key | Type | Public-Safe | Notes |
|---|---|---|---|
| `services` | json | Yes | Array of offered services |
| `other_services` | json/text | Yes | Custom services |
| `marketing_plan` | text | Yes | |
| `interested_in_selling` | boolean | Yes | |
| `interested_in_selling_type` | string | Yes | |
| `interested_lease_option` | boolean | Yes | |
| `interested_lease_option_agreement` | string | Yes | |
| `interested_in_property_management` | boolean | Yes | |
| `interested_in_property_management_fee` | string | Yes | Fee structure label only |

#### Compensation Structure (Conditionally Private)

These keys represent the agent's compensation structure. The **structure type** and **description** are public-safe (they appear on the hire page). The specific dollar amounts and percentage rates are private and should not be surfaced in public chat context.

| Key | Public-Safe | Notes |
|---|---|---|
| `commission_structure` | Yes | Structure description |
| `commission_structure_type` | Yes | Type label |
| `purchase_fee_type` | Yes | Type label only |
| `lease_fee_type` | Yes | Type label only |
| `purchase_fee_percentage` | **No** | Specific rate |
| `purchase_fee_flat` | **No** | Dollar amount |
| `lease_fee_percentage` | **No** | Specific rate |
| `lease_fee_flat` | **No** | Dollar amount |
| `retainer_fee_option` | Yes | Whether retainer is offered |
| `retainer_fee_amount` | **No** | Dollar amount |
| `retainer_fee_application` | Yes | How retainer is applied |
| `referral_fee_percent` | **No** | Rate |
| `protection_period` | Yes | Duration only |
| `early_termination_fee_option` | Yes | Whether fee exists |
| `early_termination_fee_amount` | **No** | Dollar amount |
| All `*_flat_fee`, `*_percentage`, `*_price`, `*_amount` keys | **No** | Specific rates/amounts |
| `nominal` | **No** | Specific compensation value |

**Rule:** For V2 agent profile chat, the AI may answer:

> "This agent offers buyer representation and consultation services."

but must never answer:

> "Their fee is 2.5%." or "Their flat fee is $3,500."

unless the platform has explicitly decided those fee amounts are public-facing content — that decision is outside this audit's scope. Until an explicit decision is recorded, all fee amounts, percentage rates, and dollar values from `profile_data` are private and must not appear in any chat context or AI response.

Expose only: service types offered (not fee amounts), availability, bio, credentials, service area, reviews, marketing approach. Never expose specific fee amounts, commission rates, or percentage values.

---

## 8. Ask AI Knowledge Tables

### 8.1 ask_ai_knowledge_snapshots

Versioned snapshot registry. One row per (listing_type, listing_id, version).

| Column | Type | Role |
|---|---|---|
| `listing_type` | varchar(20) | `seller`/`buyer`/`landlord`/`tenant` |
| `listing_id` | bigint | FK to the listing |
| `version` | integer | Auto-incremented; unique per listing |
| `status` | varchar(20) | `building`/`ready`/`failed` |
| `error_message` | text | Non-null on failed status |
| `built_at` | timestamp | When snapshot completed |
| `snapshot_uuid` | uuid | Unique identifier for cache/CDN keys |
| `source_model` | varchar(120) | PHP class name of the source model |
| `source_updated_at` | timestamp | `updated_at` from the source listing at build time |
| `facts_count` | integer | Count of `ask_ai_facts` rows |
| `questions_count` | integer | Count of `ask_ai_questions` rows |
| `answers_count` | integer | Count of `ask_ai_answers` rows |

**Stale detection:** Compare `source_updated_at` on the latest snapshot against `updated_at` on the current listing record.

### 8.2 ask_ai_facts

Individual structured facts extracted from listing data.

| Column | Type | Role |
|---|---|---|
| `canonical_key` | varchar(120) | Dot-notation path, e.g. `listing.bedrooms` |
| `value` | text | String value |
| `visibility` | varchar(20) | `public_allowed` / other |
| `label` | text | Human-readable label |
| `value_type` | varchar(20) | `string`/`number`/`boolean`/`json` |
| `source_path` | varchar(200) | Source EAV key or native column name |
| `classification` | varchar(30) | `public`/`private`/`compliance_sensitive` |
| `public_allowed` | boolean | Whether fact may be used in chat |
| `restricted` | boolean | Whether fact is restricted to authenticated users |
| `sort_order` | integer | Display ordering |

### 8.3 ask_ai_questions

Registry of answerable questions per snapshot.

| Column | Type | Role |
|---|---|---|
| `canonical_key` | varchar(120) | Links to fact or answer |
| `field_type` | varchar(30) | `faq` / `listing` |
| `keyword_route_status` | varchar(40) | `pinned`/`umbrella_only`/`match_criteria`/`opaque_key` |
| `label` | text | Question display label |
| `sample_question` | text | First sample question for suggested chips |
| `sample_question_2` | text | Second sample question |
| `question_text` | text | Full question text |
| `question_type` | varchar(30) | Classifier question type |
| `source_path` | varchar(200) | |
| `sort_order` | integer | |

### 8.4 ask_ai_answers

Pre-computed answers linked to snapshot questions.

| Column | Type | Role |
|---|---|---|
| `canonical_key` | varchar(120) | Bare key (no `listing.` prefix) or full path |
| `answer_text` | text | Pre-computed answer text |
| `question_id` | bigint | FK to `ask_ai_questions` (nullable) |
| `classification` | varchar(30) | Classification of the answer |
| `visibility` | varchar(20) | `public_allowed` / other |
| `source_path` | varchar(200) | |

**Note on canonical_key dual form:** Per memory entry `ask-ai-phase4-answer-key-dualform.md`, the `canonical_key` in `ask_ai_answers` may be bare (`roof_age_and_condition`) or full-path (`faq_answers.roof_age_and_condition`). The `AskAiKnowledgeSearchService` must try both forms.

### 8.5 ai_faq_answers

Inline FAQ answer storage (separate from snapshot tables). Used as the primary FAQ data source in `buildFaqAnswers()` when the `listing_ai_faq` EAV meta is not present.

| Column | Type | Role |
|---|---|---|
| `listing_type` | varchar(255) | Role |
| `listing_id` | bigint | FK |
| `question_key` | varchar(255) | FAQ key (e.g. `roof_age_and_condition`) |
| `question_group` | varchar(255) | FAQ category group |
| `intelligence_category` | varchar(255) | Snake-case category |
| `answer_text` | text | Human-provided answer text |
| `answer_normalized` | json | Normalized metadata |

### 8.6 ask_ai_usage_logs

Token accounting and outcome tracking per request.

| Column | Type | Role |
|---|---|---|
| `prompt_tokens` | integer | Tokens consumed in prompt |
| `completion_tokens` | integer | Tokens consumed in completion |
| `total_tokens` | integer | Sum |
| `estimated_cost_usd` | numeric(10,6) | Per-request cost |
| `model` | varchar(255) | Model identifier (e.g. `gpt-4o-mini`) |
| `question_type` | varchar(255) | Classifier output |
| `outcome_category` | varchar(40) | High-level outcome bucket |
| `api_request_id` | varchar(255) | OpenAI request ID for support |

**Note:** `user_id` and `ip_address` are logged but `question_hash` is used for question analytics to avoid storing raw PII questions.

---

## 9. Token Budget & Cost Analysis

> **Reading guide:** Sections 9.1–9.3 present **V1-equivalent baseline** estimates — they assume no fragment caching and full context resent on every turn, matching current system behavior. Section 9.3 "With OpenAI Prompt Caching" and Section 9.4 present the **V2 optimized** targets after caching, fragment budgeting, and the hybrid model strategy are implemented in builds #2779–#2780. These are clearly labelled. Do not apply the V2 optimized numbers to the V1 codebase.

### 9.1 Context Package Size Estimates (V1-Equivalent Baseline)

Token estimates are calculated using ~4 chars/token (GPT-4o approximation for mixed prose/data text). All estimates reflect a "fully populated" listing with property intelligence available. These are worst-case baselines — the V2 fragment system in #2779 will reduce these through selective loading and null-field stripping.

#### Seller Listing Context

| Fragment | Content | Est. Tokens |
|---|---|---|
| Base listing fields (address, type, status, dates) | ~200 chars | 50 |
| Core property facts (beds/baths/sqft/price/year_built) | ~300 chars | 75 |
| Structural/mechanical fields (roof, HVAC, foundation, appliances) | ~400 chars | 100 |
| HOA/CDD/tax fields | ~300 chars | 75 |
| Flood zone and disclosure fields | ~200 chars | 50 |
| Extended fields (pool, parking, lot, utilities, etc.) | ~500 chars | 125 |
| Property intelligence (strengths, highlights, story, positioning) | ~2,000 chars | 500 |
| Location intelligence (narrative, categories, highlights) | ~1,500 chars | 375 |
| FAQ answers (20–30 entries, avg 150 chars each) | ~3,750 chars | 938 |
| System instructions (12 static strings) | ~800 chars | 200 |
| **Total (fully assembled seller context)** | | **~2,488 tokens** |

#### Landlord Listing Context

| Fragment | Content | Est. Tokens |
|---|---|---|
| Base listing fields | ~200 chars | 50 |
| Core rental facts (rent, beds/baths/sqft, availability) | ~300 chars | 75 |
| Lease terms (lease length, renewal, terms, deposit) | ~400 chars | 100 |
| Pet/parking/utilities/HOA | ~500 chars | 125 |
| Property intelligence | ~2,000 chars | 500 |
| Location intelligence | ~1,500 chars | 375 |
| FAQ answers (15–20 entries) | ~2,625 chars | 656 |
| System instructions | ~800 chars | 200 |
| **Total (fully assembled landlord context)** | | **~2,081 tokens** |

#### Buyer Criteria Context

| Fragment | Content | Est. Tokens |
|---|---|---|
| Base listing fields | ~200 chars | 50 |
| Criteria fields (price, beds/baths, location prefs, financing) | ~600 chars | 150 |
| Avatar (narrative, preference summary, personality tags) | ~800 chars | 200 |
| Compatibility data (when pair context provided) | ~600 chars | 150 |
| System instructions | ~800 chars | 200 |
| **Total (buyer criteria context)** | | **~750 tokens** |

#### Tenant Criteria Context

| Fragment | Content | Est. Tokens |
|---|---|---|
| Base listing fields | ~200 chars | 50 |
| Criteria fields (budget, beds/baths, lease, pets, income) | ~500 chars | 125 |
| Avatar (narrative, preference summary, personality tags) | ~800 chars | 200 |
| System instructions | ~800 chars | 200 |
| **Total (tenant criteria context)** | | **~575 tokens** |

#### Agent Profile Context (V2 New)

| Fragment | Content | Est. Tokens |
|---|---|---|
| Identity & credentials | ~300 chars | 75 |
| Bio + reviews | ~800 chars | 200 |
| Services + marketing plan | ~600 chars | 150 |
| Service area | ~200 chars | 50 |
| Availability | ~100 chars | 25 |
| System instructions | ~800 chars | 200 |
| **Total (agent profile context)** | | **~700 tokens** |

### 9.2 Per-Turn Token Totals (V1-Equivalent Baseline)

**Assumptions (V1 behavior):** User question = ~50 tokens. Completion = 150–200 tokens for factual answers. Follow-on turns re-send the **full context** on every turn — there is no caching in the current V1 system. The "Follow-on Turn" column therefore reflects the same context overhead as the first turn minus the system-instruction prefix (~100 tokens). Under V2 with fragment caching, follow-on turns are expected to cost 0 input tokens for the cached context prefix (OpenAI charges $1.25/1M cached vs $2.50/1M uncached for GPT-4o).

| Page Type | First Turn — V1 baseline | Follow-on Turn — V1 baseline |
|---|---|---|
| Seller listing | ~2,700 input + ~200 output = **~2,900** | ~2,600 input + ~200 output = **~2,800** |
| Landlord listing | ~2,280 input + ~200 output = **~2,480** | ~2,180 input + ~200 output = **~2,380** |
| Buyer criteria | ~950 input + ~150 output = **~1,100** | ~850 input + ~150 output = **~1,000** |
| Tenant criteria | ~775 input + ~150 output = **~925** | ~675 input + ~150 output = **~825** |
| Agent profile | ~900 input + ~150 output = **~1,050** | ~800 input + ~150 output = **~950** |

### 9.3 Monthly Cost Projections

#### GPT-4o Pricing (as of June 2026)

- Input: $2.50 per 1M tokens  
- Output: $10.00 per 1M tokens  
- Cached input: $1.25 per 1M tokens

#### GPT-4o-mini Pricing (as of June 2026)

- Input: $0.15 per 1M tokens  
- Output: $0.60 per 1M tokens  
- Cached input: $0.075 per 1M tokens

#### Volume Tier: 1,000 chats/month — V1-Equivalent Baseline

Assuming 65% seller/landlord, 25% buyer/tenant, 10% agent profile. Average 1.5 turns per session. Blended avg ~2,200 tokens/turn (input) + 180 tokens (output). No caching assumed.

| Model | Input Cost | Output Cost | Monthly Total |
|---|---|---|---|
| GPT-4o | 1,000 × 1.5 × 2,200/1M × $2.50 + 1,000 × 1.5 × 180/1M × $10 | | **~$11.00/mo** |
| GPT-4o-mini | 1,000 × 1.5 × 2,200/1M × $0.15 + 1,000 × 1.5 × 180/1M × $0.60 | | **~$0.66/mo** |

#### Volume Tier: 10,000 chats/month — V1-Equivalent Baseline

| Model | Monthly Total |
|---|---|
| GPT-4o | **~$110/mo** |
| GPT-4o-mini | **~$6.60/mo** |

#### Volume Tier: 100,000 chats/month — V1-Equivalent Baseline

| Model | Monthly Total |
|---|---|
| GPT-4o | **~$1,100/mo** |
| GPT-4o-mini | **~$66/mo** |

#### With OpenAI Prompt Caching — V2 Optimized Estimate

Implemented in build #2779 (context loaders) + #2780 (conversation layer). V2 caching strategy (see Section 11) targets 70–80% cache hit rate on context tokens. At 75% cache hit and GPT-4o pricing, the effective input cost drops by ~56%:

| Volume | GPT-4o (cached) | GPT-4o-mini (cached) |
|---|---|---|
| 1,000/mo | **~$5.50/mo** | **~$0.35/mo** |
| 10,000/mo | **~$55/mo** | **~$3.50/mo** |
| 100,000/mo | **~$550/mo** | **~$35/mo** |

### 9.4 Hybrid Model Recommendation — V2 Optimized

**Recommended V2 model strategy** (to be wired in build #2780):

| Scenario | Model | Rationale |
|---|---|---|
| `listing_facts` (deterministic Guard B path) | No OpenAI call | Snapshot lookup or keyword match — zero cost |
| `listing_facts` (description fallback) | GPT-4o-mini | Simple factual extraction from description text |
| `buyer_tenant_match`, `compatibility_signals` | GPT-4o-mini | Structured data interpretation; high volume expected |
| `property_standout`, `suited_audience` | GPT-4o | Marketing-quality language; quality matters more than cost |
| `agent_profile_chat` | GPT-4o-mini | Simple profile Q&A; cost-sensitive |
| Intent normalization (normalizer service) | GPT-4o-mini | Already using mini; keep |

> `offer_analysis` is **not included** in the V2 hybrid model strategy because it is permanently excluded from all public chat scopes. See Section 12.1.

**Estimated blended cost at 10,000 chats/month using hybrid strategy (V2 optimized):** approximately **$12–20/month** depending on question type distribution.

> **Model governance:** Model names shown in this audit are examples only. Production model selection must be configuration-driven through `config/ask_ai.php` and must not be hardcoded in application services. Any change to the model used for a given question type requires only a config update — no service code changes.

---

## 10. V2 Context Fragment Contract

All V2 context loaders must return fragments conforming to this contract. `AgentAiContextBuilder` (V2) will aggregate fragments, sort by priority, and apply token budget enforcement before assembling the prompt.

### 10.1 Fragment Schema

```php
[
    'source_key'     => string,   // Unique identifier, e.g. 'listing_facts', 'property_intelligence'
    'priority'       => int,      // Lower = higher priority (1 = highest; not dropped first)
    'content'        => array,    // Key-value content passed to the prompt
    'token_estimate' => int,      // Pre-calculated token estimate for this fragment
    'public_allowed' => bool,     // Whether fragment may be shown to unauthenticated users
    'role_scope'     => string[], // Which roles this fragment applies to: ['seller','landlord',...]
    'cache_ttl'      => int|null, // Seconds to cache this fragment (null = no cache)
    'loaded_at'      => string,   // ISO-8601 timestamp of when this fragment was loaded
]
```

### 10.2 Source Keys Registry

Priority follows the **lower = higher priority** convention defined in Section 10.1 above: priority 1 is most important (never dropped), higher numbers are dropped first when the token budget is exceeded. Future reserved loaders (marked *reserved*) are defined in Sections 15–17 and must not be implemented until the prerequisite data sources exist.

| source_key | Priority | Roles | Cache TTL | Notes |
|---|---|---|---|---|
| `listing_base` | 1 | all | 300s | Core listing metadata; changes rarely |
| `listing_facts` | 2 | all | 300s | Property detail fields; changes on save |
| `listing_description` | 3 | seller, landlord | 600s | Native description column |
| `mls_snapshot` | 3 | seller, landlord | 86400s | **Reserved** — public-safe MLS payload; see Section 16 |
| `agent_profile` | 3 | agent_profile_chat | 1800s | Preset data |
| `faq_answers` | 4 | seller, landlord | 3600s | Agent-authored Q&A; infrequent changes |
| `uploaded_document` | 4 | seller, landlord | 86400s | **Reserved** — public-safe document fragments; see Section 15 |
| `property_intelligence` | 5 | seller, landlord | 3600s | DNA profile; updated on listing save |
| `buyer_avatar` | 5 | buyer | 3600s | DNA profile |
| `tenant_avatar` | 5 | tenant | 3600s | DNA profile |
| `location_intelligence` | 6 | all | 86400s | Location data; daily refresh |
| `compatibility` | 6 | all (pair context) | 600s | Score changes with new bids |
| `knowledge_snapshot` | 7 | all | 86400s | Pre-computed snapshot answers |
| `knowledge_document` | 7 | all | 86400s | **Reserved** — public educational content; see Section 17 |

### 10.3 Token Budget Enforcement and Truncation Order

When the assembled context exceeds the per-turn token budget (recommended: 3,000 input tokens for listing chats, 1,500 for buyer/tenant, 1,000 for agent profile), fragments are dropped in this order:

1. **Drop first:** `knowledge_snapshot` (priority 7), `knowledge_document` (priority 7) — educational content and pre-computed snapshots; can be queried separately if needed
2. **Drop second:** `location_intelligence` (priority 6) — optional for all question types
3. **Drop third:** `compatibility` (priority 6) — only required for `buyer_tenant_match`
4. **Drop fourth:** `property_intelligence` (priority 5), `buyer_avatar` (priority 5), `tenant_avatar` (priority 5) — optional; FAQ covers most property detail
5. **Drop fifth:** `faq_answers` (priority 4), `uploaded_document` (priority 4) — drop by relevance; `faq_answers` entries dropped by sort_order descending (least relevant last)
6. **Drop sixth:** `listing_description` (priority 3), `mls_snapshot` (priority 3) — contextual supplements; drop when listing_facts already covers the same information
7. **Never drop:** `listing_base` (priority 1), `listing_facts` (priority 2)

Within `faq_answers`, individual FAQ entries may be dropped starting with `intelligence_category = null` entries, then by reverse question group priority.

**This truncation order is the source of truth for build #2779 (context loaders) and build #2780 (conversation layer + token budgeting).** `token_estimate` values are calculated per-fragment before assembly, enabling `AgentAiContextBuilder` to enforce budgets without re-tokenizing the full assembled context.

---

## 11. Context Compression & Caching Strategy

### 11.1 Context Compression Rules

1. **Serialize as compact key-value text** (not JSON) for the prompt:  
   `bedrooms: 3, bathrooms: 2, square_feet: 1,850, pool: Yes`  
   rather than `{"bedrooms": 3, "bathrooms": 2, ...}`  
   Estimated savings: ~30% token reduction vs raw JSON.

2. **Flatten null values:** Never include null/empty fields in the serialized context. A field with no value contributes no information and wastes tokens.

3. **Deduplicate aliases:** The context builder currently exports several alias keys (e.g. `heating_fuel` and `heating_and_fuel`, `water_source` and `water`, `annual_net_income` and `minimum_annual_net_income`). V2 should resolve aliases at load time and surface only one key.

4. **Summarize JSON arrays** at load time: `["Washer", "Dryer", "Dishwasher"]` → `Washer, Dryer, Dishwasher`. The `decodeJsonField()` pattern already does this; V2 loaders should follow the same approach.

5. **Truncate long free-text fields:** Description, bio, marketing plan, and FAQ answer fields should be capped at 300 chars (≈75 tokens) per entry in the serialized context. Full text is available on request via Phase 4 snapshot search.

### 11.2 Caching Strategy

V2 should implement two caching layers:

**Layer 1 — Fragment-level cache** (Redis or Laravel cache):  
Cache each context fragment by `{source_key}:{listing_type}:{listing_id}` with the TTLs defined in Section 10.2. Invalidate `listing_facts` and `listing_base` fragments on listing save events (same trigger that fires `buildSilently()`).

**Layer 2 — OpenAI Prompt Caching**:  
OpenAI automatically caches prompts when the prefix (system instructions + context) is identical across calls for the same listing. Ensure the serialized context is deterministic (sorted keys, no timestamps in the context payload) so OpenAI's 1024-token cache prefix matches across questions on the same listing.

### 11.3 Knowledge Snapshot Reuse

The existing `AskAiKnowledgeSearchService` (Phase 4 database-first path) already attempts to answer questions from pre-computed snapshots before calling OpenAI. V2 should expand this path to cover all `listing_facts` question types, not just the ones currently indexed. A snapshot cache hit costs zero OpenAI tokens.

**Snapshot freshness rule:** A snapshot is considered stale when `ask_ai_knowledge_snapshots.source_updated_at < seller_agent_auctions.updated_at` (or the equivalent for other listing types). Stale snapshots should be rebuilt asynchronously; stale but not absent snapshots may still serve answers with an age warning appended.

### 11.4 Conversation Memory Limit

V2 should support a **maximum of 5 conversation turns** per session before the session is reset. This prevents unbounded context growth in multi-turn chats. Implementation:

- Store the last N user questions and AI answers in the session (keyed by `session_id` from the API input)
- Include prior turns as a compressed `conversation_history` fragment with priority 8 (lowest; dropped first)
- Each prior turn contributes approximately 100–150 tokens (question + answer summary)
- At 5 turns: ~600–750 additional tokens for conversation history

---

## 12. Privacy Boundary Table

### 12.1 What Is Explicitly Forbidden in Every Scope

Regardless of listing type or question asked, the following are **always forbidden** from appearing in any chat context or response:

| Category | Examples |
|---|---|
| **Offer, bid, and counteroffer data — permanently excluded** | All rows from `seller_agent_auction_bids`, `buyer_agent_auction_bids`, `landlord_agent_auction_bids`, `tenant_agent_auction_bids`; all counteroffer records; all `AcceptedBidSummary` data; any compensation negotiation history |
| Other agents' bid data | Competing agent bid amounts, commission rates, service offerings |
| Counteroffers and negotiation history | Counter terms, counter amounts, negotiation notes |
| Accepted bid details | Accepted agent identity, accepted commission rate, negotiated compensation |
| User PII | Email addresses, phone numbers, full names of private parties |
| Financial qualification data | Preapproval amounts, buyer cash reserves, crypto holdings |
| Internal workflow flags | `is_paid`, `need_cma`, `referral_source_code`, `referring_agent_id` |
| Protected class characteristics | Race, color, religion, national origin, sex, familial status, disability |
| Agent compensation rates | Specific percentage rates, flat fee amounts in `profile_data` |

> **Bid/offer data — V2 public chat exclusion rule:** Offer, counteroffer, bid, accepted-bid-summary, negotiation, and competing-offer data are permanently excluded from all public Agent AI V2 context scopes. This exclusion applies to all four listing types and the agent profile scope. A future authenticated agent-only scope (outside this audit's V2 scope) could revisit this restriction, but no such scope is planned in builds #2777–#2785.

### 12.2 Per-Scope Permitted vs. Forbidden

| Data Item | Seller Chat | Landlord Chat | Buyer Chat | Tenant Chat | Agent Profile Chat |
|---|---|---|---|---|---|
| Listing address | ✅ Public | ✅ Public | ✅ Public | ❌ Not available | N/A |
| Asking price / rent | ✅ Public | ✅ Public | ✅ Public | ✅ Public | N/A |
| Any bid / offer data | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded |
| Any counteroffer data | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded |
| Accepted bid summary | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded | ❌ Permanently excluded |
| Other agents' bids | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden |
| Accepted bid agent ID | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | N/A |
| Buyer preapproval amount | N/A | N/A | ❌ Forbidden | N/A | N/A |
| Tenant credit score range | N/A | N/A | N/A | ✅ Conditionally public | N/A |
| Tenant monthly income | N/A | N/A | N/A | ✅ Conditionally public | N/A |
| Income requirement (landlord) | N/A | ✅ Compliance-sensitive | N/A | N/A | N/A |
| Security deposit amount | N/A | ✅ Compliance-sensitive | N/A | N/A | N/A |
| Flood zone code | ✅ Compliance-sensitive | ✅ Compliance-sensitive | N/A | N/A | N/A |
| Agent email/phone | N/A | N/A | N/A | N/A | ❌ Forbidden |
| Agent commission rates | N/A | N/A | N/A | N/A | ❌ Forbidden |
| Agent bio/services/area | N/A | N/A | N/A | N/A | ✅ Public |
| Property intelligence | ✅ Public | ✅ Public | N/A | N/A | N/A |
| Buyer/tenant avatar | N/A | N/A | ✅ Public | ✅ Public | N/A |
| School quality ratings | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden |
| Crime statistics / safety ratings | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden |
| Neighborhood demographics | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden |

**Note on Compliance-Sensitive fields:** Flood zone code, security deposit, and income requirements are permitted in chat context but require mandatory disclosures. The `AskAiDisclosureRegistry` manages these disclosure templates. The `disclosure_flags` field in the seller context (`{'flood_zone': true}`) signals to the prompt builder that the flood-zone disclosure must be included.

---

## 13. Gaps & Missing Fields

The following gaps were identified by comparing the V2 design requirements against the current code and database schema.

### 13.1 Critical Gaps (Block V2 Build)

| Gap | Description | Affected Scopes |
|---|---|---|
| **No `agent_profile_chat` context path** | `AskAiContextBuilderService` has no branch for `canonicalType = 'agent'`. No controller, route, or contract exists. V2 must add a new context scope, loader, contract, and route. | `agent_profile_chat` |
| **No conversation memory** | `session_id` is accepted by the API but not persisted or used. Multi-turn context requires a store (Redis or DB table) keyed by `session_id`. | All scopes |
| **No token budget enforcement** | Context is assembled and passed to OpenAI without any size check. A listing with 50 FAQ answers + full DNA + location intelligence could exceed 4,000 tokens before the question is added. | All scopes |
| **No fragment-level caching** | Every question triggers a full rebuild of all context sources. Redis caching by `{source_key}:{listing_type}:{listing_id}` is absent. | All scopes |

### 13.2 Data Gaps (V2 Context Missing Fields)

| Gap | Description | Status |
|---|---|---|
| **Buyer `water_view` key** | Live-DB audit confirmed buyer metas use `view_preference`, not `water_view`. The context builder correctly uses `view_preference` for buyer; the LISTING_KEY_KEYWORD_MAP entry `listing.water_view` resolves to `ctx['listing']['water_view']` which reads `view_preference` correctly. Verified — no code gap. | ✅ Resolved |
| **Tenant `additional_details` description** | `TenantAgentAuction` has no native `additional_details` column. The description for tenant is stored under EAV key `additional_details`. The context builder reads `$nativeGet('additional_details')` which will always be null for tenant. V2 must add `$infoGet('additional_details')` for the tenant base fields. | ❌ **Gap** |
| **Landlord `pet_policy` EAV empty** | Per memory entry `landlord-pet-policy-eav.md`: 50 landlord records have `pet_policy` EAV meta but values are all empty; real data is under `pets` EAV key. The context builder already applies `$infoGet('pet_policy') ?: $infoGet('pets')` fallback — this is handled. | ✅ Handled |
| **Buyer `financing_type` key** | Live-DB confirmed `offered_financing` is always null for buyer; `financing_type` is the correct key. Context builder already uses `financing_type ?? offered_financing`. | ✅ Handled |
| **Landlord `description`** | `LandlordAgentAuction` has no native `description` column and no `additional_details` native column. The Ask AI context builder returns `null` for `listing.description` on all landlord listings. Ask AI uses `description` as the description fallback source. Landlord description text (if any) must be read from EAV — key TBD. | ❌ **Gap** |
| **Agent profile context fields** | No V2 context loader reads `agent_default_profiles.profile_data` for any question type. Entire `agent_profile_chat` scope must be built from scratch. | ❌ **Gap** |
| **`buy_now_price` seller** | Confirmed populated in seller metas per code comment ("Live-DB audit confirmed the key is present on offer-listing rows"). | ✅ No gap |

### 13.3 Schema Gaps

| Gap | Description |
|---|---|
| **No `session_turns` or `conversation_history` table** | Multi-turn conversation memory requires a storage layer. Build #2777 (skeleton) should define the Redis key schema or migration for this table; it is wired in build #2780 (conversation layer). |
| **No `agent_profile_chat` question type in `ask_ai_questions`** | The `field_type` and `keyword_route_status` enums in `ask_ai_questions` do not include agent profile question types. V2 must extend the snapshot builder for agent profiles (build #2779). |
| **`ask_ai_usage_logs` missing `session_id` column** | Multi-turn analytics require session_id tracking. A migration should be added in build #2777 or #2780. |

### 13.4 V2 Classifier Gaps

| Gap | Description |
|---|---|
| **No `agent_profile_chat` question type** | `AskAiQuestionClassifierService::KEYWORD_RULES` has no entry for agent profile questions. V2 must add agent-profile keyword rules or create a dedicated classifier path. |
| **No role-scoped classification** | The classifier does not know which listing type it is classifying for. A question about "monthly rent" is always `listing_facts` even on a seller listing (where it is inapplicable). V2 should pass listing type to the classifier for role-filtered classification. |
| **Buyer/tenant description field missing from Guard B** | `listing.description` is exposed in the context builder for buyer (as `additional_details`) and seller, but LISTING_KEY_KEYWORD_MAP has no entry for `listing.description`. Description-question routing relies entirely on the step 1a-desc fallback path, not Guard B. |

---

## 14. Future Channel Support (Reserved)

Agent AI V2 is being designed as a **channel-agnostic conversation engine**. The V2 MVP deploys website chat only (build #2783), but the underlying session, context, permission, and lead-capture architecture must not assume that all conversations originate from website chat. The existing `AskAiApiController` already accepts a `channel` parameter with the values `web`, `sms`, `messenger`, `whatsapp`, `mobile`, `crm` — none beyond `web` are active in V2, but the foundation should accommodate them without structural rework.

Future channels may include:

| Channel | Notes |
|---|---|
| Website chat | V2 MVP scope — build #2783 |
| SMS | Short-form answers; requires response length cap; no rich UI chips |
| Voice | Text-to-speech output; requires sentence-safe answer formatting; no markdown |
| Facebook Messenger | Platform message length limits apply |
| Instagram | Direct Message API; similar to Messenger |
| WhatsApp | Business API; message template restrictions may apply |
| Email | Asynchronous; full response in email body; no follow-up chips |
| Other messaging systems | Any future channel reachable via webhook or API adapter |

**Architecture constraints for the V2 database and session layer:**

- The `session_id` field (already accepted by `AskAiApiController`) must be stored and scoped to a channel-and-listing pair — not assumed to be a browser session.
- The conversation history store (to be designed in build #2780) must be keyed on `{channel}:{session_id}:{listing_type}:{listing_id}` or equivalent — not on a web session cookie.
- Lead capture and inbox routing (#2782) must accept a `channel` attribute on every captured lead, so that future channels can route to the same inbox without schema changes.

**Architecture constraint:** All future channels **must reuse** the same context loading, permission guard, privacy boundary, lead capture, inbox routing, and notification pipelines built in #2779–#2782. Channel adapters are thin translation layers only — no channel should have its own context-building logic.

These integrations are out of scope for the V2 MVP. No channel beyond `web` should be activated without a dedicated channel-adapter task and safety review.

---

## 15. Future Context Source: Uploaded Listing Documents / Disclosure Documents

**Status:** Reserved — no extraction infrastructure currently exists.  
**Future loader name:** `UploadedDocumentLoader`  
**Source key:** `uploaded_document`  
**Priority:** 4 (Section 10 convention: lower = higher priority; same tier as `faq_answers`; dropped before listing facts and MLS snapshot, retained before property intelligence and below)

### 15.1 Public-Safe Document Examples

The following uploaded document types are eligible for Ask AI context when text extraction is available:

| Document Type | Scope | Notes |
|---|---|---|
| Seller property condition disclosure | Seller | General condition facts; no private party info |
| AS-IS disclosure | Seller | States known condition status |
| HOA declaration / rules / bylaws | Seller, Landlord | Association rules applicable to prospective buyers/tenants |
| HOA meeting minutes (public summaries) | Seller, Landlord | Budget votes, rule changes — exclude confidential member matters |
| Flood zone elevation certificate | Seller, Landlord | Public compliance document |
| Survey / plat map | Seller, Landlord | Legal lot boundaries |
| Floor plan (publicly shared) | Seller, Landlord | Layout only; no security or access details |
| Publicly shared inspection report | Seller | Only when explicitly released by seller for buyer review |
| Lead paint disclosure | Landlord | Federal compliance document |
| Mold / radon disclosure | Landlord | State-required disclosures |
| Lease document summary (term sheet) | Landlord | Publicly stated terms; no private tenant data |

### 15.2 Excluded Document Examples

The following document types must never enter any Ask AI context under any circumstances:

| Excluded Document | Reason |
|---|---|
| Private offer or purchase contract | Contains competing negotiation data |
| Signed accepted contract / closing docs | Private transaction terms |
| Identity documents (IDs, passports, licenses) | Personal identifying information |
| Financial documents (bank statements, tax returns, pay stubs, pre-approval letters with amounts) | Private financial data |
| Competing-agent bid documents | Private agent/buyer data |
| Internal inspection reports not released by seller | Private due-diligence data |
| Attorney correspondence | Privileged |
| Any document containing another party's private contact information | Privacy violation |

### 15.3 Standard Fragment Contract

```
{
  source_key:     "uploaded_document",
  priority:       4,
  content:        "<extracted text fragment — public-safe sections only>",
  token_estimate: <integer>
}
```

Multiple documents may produce multiple fragments. Each fragment must carry the document type as a label prefix (e.g., `[HOA Rules] …`) so the prompt builder can identify the source.

### 15.4 Example Future Questions

- "What does the HOA disclosure say about pets?"
- "Are there any known defects listed in the seller disclosure?"
- "What does the AS-IS disclosure cover?"
- "Does the survey show any easements on the property?"
- "What flood elevation certificate data is available?"

### 15.5 Gap Note

No OCR, document parsing, or text extraction pipeline currently exists for uploaded listing documents. Files are stored on the public disk under `{role}-disclosures/{id}/{type}/` (as documented in `replit.md`), but no queryable text index is maintained. The `UploadedDocumentLoader` must not be implemented until:

1. A text extraction pipeline exists that produces per-document queryable text (raw OCR output or structured parsed text).
2. Each extracted document is classified as public-safe by the uploader or platform workflow before extraction is allowed to flow into Ask AI.
3. A document-level privacy flag or allowlist governs which uploaded files may be surfaced in chat context.

Until these conditions are met, the loader contract (`uploaded_document` source_key, priority 4, fragment shape) is reserved and must not be implemented with live data access.

---

## 16. Future Context Source: MLS Import Snapshot / Raw Parsed MLS Payload

**Status:** Reserved — full-payload persistence does not currently exist as a separate store.  
**Future loader name:** `MlsImportSnapshotLoader`  
**Source key:** `mls_snapshot`  
**Priority:** 3 (Section 10 convention: lower = higher priority; same tier as `listing_description`; dropped only when listing facts already cover the same information)

### 16.1 Correct Model

The MLS importer (`HasMlsImport` trait + `MlsFieldMap`) currently maps parsed MLS fields to form fields at apply-time. The full raw parsed payload is not separately persisted after the form fields are populated. The correct V2 model is:

1. **Map supported fields to form** (current behavior — unchanged).
2. **Preserve the full parsed payload separately** in a dedicated store (e.g., `mls_import_snapshots` table) so Ask AI can access MLS-specific data that was not mapped to a form field, without re-parsing the original import.

The `MlsImportSnapshotLoader` reads from the preserved snapshot, not from re-parsing the raw import source.

### 16.2 Public-Safe MLS Fields

| MLS Field Category | Example Fields | Ask AI Access |
|---|---|---|
| Legal description | Parcel ID, legal description text | Public |
| Tax data | Tax year, annual taxes, tax ID | Public |
| Utilities | Water source, sewer type, electric, gas, internet/telecom | Public |
| Zoning | Zoning code, zoning description | Public |
| Construction details | Roof type, exterior construction, foundation type | Public |
| Year built | Year built, effective year built | Public |
| Lot data | Lot dimensions, total acreage, front footage | Public |
| Flood zone | Flood zone code, panel number, date, elevation cert indicator | Compliance-Sensitive |
| HOA / CDD | HOA name, HOA fee, fee frequency, CDD amount | Public |
| Schools | School district, elementary/middle/high school names | Public (no ratings — see Section 12) |
| Public remarks | MLS public remarks field | Public |

### 16.3 Excluded MLS Fields

The following MLS field categories must never enter any Ask AI context:

| Excluded Category | Reason |
|---|---|
| Private remarks | Listing-agent-only remarks; may contain access codes, negotiation notes, or private seller instructions |
| Owner / seller contact information | Name, phone number, email address — private party data |
| Agent-only compensation | Buyer-agent commission percentage, bonuses, variable compensation — not public disclosure |
| Lockbox / showing access instructions | Security-sensitive; contains access codes or showing-service authorization details |
| Offer / transaction data | Pending offer count, contract price, days under contract, multiple-offer indicator |
| Listing-agent contact details | Direct phone/email of listing agent — use agent profile scope instead |

### 16.4 Standard Fragment Contract

```
{
  source_key:     "mls_snapshot",
  priority:       3,
  content:        "<structured text of approved public-safe MLS fields>",
  token_estimate: <integer>
}
```

The content string should label each field (e.g., `School District: Hillsborough County Public Schools`) so the prompt builder can identify MLS-sourced facts. Raw MLS field codes or internal MLS IDs must not be included in the fragment.

### 16.5 Example Future Questions

- "What does the MLS say about the school district?"
- "What utilities are listed in the MLS record?"
- "What is the legal description of the property?"
- "What flood zone information is in the MLS filing?"
- "What are the HOA details from the MLS listing?"

### 16.6 Gap Note

The current MLS import system maps parsed fields to form fields at apply-time and does not separately persist the full parsed payload. The `MlsImportSnapshotLoader` must not be implemented until:

1. A separate persistence layer (e.g., `mls_import_snapshots` table with `listing_type`, `listing_id`, `parsed_payload` JSON, and `imported_at`) exists to store the full parsed MLS payload independently of the form-mapped fields.
2. The snapshot is linked to the specific listing so the loader can query by `(listing_type, listing_id)`.
3. The snapshot store distinguishes public-safe fields from excluded fields at query time (either via a field allowlist query or by storing public-safe fields separately from the raw payload).

Until these conditions are met, the loader contract (`mls_snapshot` source_key, priority 3, fragment shape) is reserved and must not be implemented with live data access. The existing `MlsFieldMap` and `HasMlsImport` trait must not be modified as part of this reserved contract.

---

## 17. Future Context Source: Knowledge Documents / Educational Content Library

**Status:** Reserved — no centralized educational-content repository currently exists.  
**Future loader name:** `KnowledgeDocumentLoader`  
**Source key:** `knowledge_document`  
**Priority:** 7 (Section 10 convention: lower = higher priority; same tier as `knowledge_snapshot`; dropped first before any other source)

### 17.1 Purpose

The Knowledge Documents context source provides brokerage-controlled real estate education content that is not tied to any specific listing. It answers general process questions that a prospective buyer, seller, landlord, or tenant might have during their research — questions that cannot be answered from listing facts alone but that the brokerage can address with curated, vetted, public-safe educational content.

### 17.2 Public-Safe Content Categories

| Content Category | Examples | Eligible Scopes |
|---|---|---|
| Buyer process guides | Home buying steps, what to expect at closing, how to make an offer | buyer_criteria, public_listing_seller |
| Seller process guides | Listing process, preparing your home, pricing strategies, seller timelines | public_listing_seller |
| Landlord process guides | Rental property management overview, screening tenants, fair-housing basics for landlords | public_listing_landlord |
| Tenant process guides | Renting process overview, what to look for in a lease, move-in checklist | tenant_criteria, public_listing_landlord |
| Brokerage platform FAQs | How auction listings work, what is a buyer agent auction, how bids are submitted | All five scopes |
| Transaction process guides | Offer-to-close timeline, escrow overview, title insurance basics | public_listing_seller, public_listing_landlord |
| Offer / contract education | What is earnest money, contingencies explained, inspection period overview | buyer_criteria, public_listing_seller |
| Financing education | Mortgage types overview, pre-approval process, down payment options | buyer_criteria, public_listing_seller |
| Inspection education | What home inspectors look for, how to read an inspection report | buyer_criteria, public_listing_seller |
| Closing education | Closing costs explained, what happens at closing, title transfer overview | All listing scopes |
| Fair housing education | Fair Housing Act overview, protected classes awareness, prohibited questions | All five scopes |
| Relocation education | Moving timelines, school district research tips, neighborhood resources | All five scopes |

### 17.3 Excluded Content Examples

The following content types must never enter any Ask AI context from the Knowledge Documents source:

| Excluded Content | Reason |
|---|---|
| Internal brokerage training material | Not public-safe; may contain proprietary procedures |
| Agent onboarding / playbooks | Internal agent-facing content |
| Internal sales scripts or follow-up cadences | Private operating procedures |
| Agent performance data (production reports, rankings, quotas) | Private agent data |
| Client records of any kind | Privacy violation |
| Offer, counteroffer, or accepted-bid data | Permanently excluded from all V2 public chat scopes (see Section 12.1) |
| Compensation information (agent commission rates, referral terms) | Private negotiated terms |
| Transaction-specific records | Any record tied to a specific deal or client |
| Any content not explicitly classified as public-safe educational material | Default exclude — if classification is unclear, omit |

### 17.4 Standard Fragment Contract

```
{
  source_key:     "knowledge_document",
  priority:       7,
  content:        "<approved public educational content fragment>",
  token_estimate: <integer>
}
```

Fragments should label their content category (e.g., `[Buyer Guide — Earnest Money] …`) so the prompt builder can identify the knowledge source. Raw document IDs, internal CMS identifiers, or author information must not be included in the fragment.

### 17.5 Scope Support

`KnowledgeDocumentLoader` registers under all five scopes:

| Scope | Example Use |
|---|---|
| `public_listing_seller` | Buyer asks "How does the offer process work?" on a seller listing |
| `public_listing_landlord` | Tenant asks "What should I know about signing a lease?" on a rental listing |
| `buyer_criteria` | Buyer asks "What is a contingency?" during a buyer-criteria chat |
| `tenant_criteria` | Tenant asks "What is a security deposit?" during a tenant-criteria chat |
| `agent_profile_chat` | Visitor asks "How does your auction platform work?" on an agent profile chat |

### 17.6 Priority Guidance

`KnowledgeDocumentLoader` runs at priority 7 — same tier as `knowledge_snapshot`, dropped first alongside pre-computed snapshots before any other source when the token budget is tight. The full recommended truncation order for `AgentAiContextBuilder`, stated in drop-first order (highest number = dropped first), is:

| Drop Order | Source | Priority (Section 10 scale) | Notes |
|---|---|---|---|
| 1st (drop first) | `knowledge_document`, `knowledge_snapshot` | 7 | Educational content and pre-computed snapshots — queried separately if needed |
| 2nd | `location_intelligence` | 6 | Optional for all question types |
| 3rd | `compatibility` | 6 | Only required for `buyer_tenant_match` |
| 4th | `property_intelligence`, `buyer_avatar`, `tenant_avatar` | 5 | Optional; FAQ covers most property detail |
| 5th | `faq_answers`, `uploaded_document` | 4 | Drop by relevance; individual FAQ entries by sort_order descending |
| 6th | `listing_description`, `mls_snapshot` | 3 | Contextual supplements; drop when listing_facts already covers the same data |
| Never drop | `listing_base`, `listing_facts` | 1, 2 | Core listing data — always included |

When context must be truncated to fit the token budget, sources at priority 7 are dropped first; sources at priority 1–2 are never dropped. This table is derived from and fully consistent with the truncation order defined in Section 10.3, which is the authoritative reference.

### 17.7 Gap Note

No centralized educational-content repository currently exists. The existing `ask_ai_knowledge` table (if present) stores per-listing FAQ snapshots, not general educational content. The `AskAiKnowledgeSnapshotBuilderService` is listing-specific and cannot serve as the knowledge library backend. The `KnowledgeDocumentLoader` must not be implemented until:

1. A centralized educational-content store exists (e.g., a `knowledge_library_documents` table or an approved CMS integration) containing brokerage-curated educational content.
2. Each document in the store is explicitly classified with a `content_category` and a `public_safe` flag before it is eligible for Ask AI access.
3. A `scope_tags` or equivalent field governs which of the five scopes each document may be surfaced in.
4. A curation and review workflow exists so that internal training material cannot be inadvertently published to the knowledge library.

Until these conditions are met, the loader contract (`knowledge_document` source_key, priority 7, fragment shape) is reserved and must not be implemented with live data access.

---

*End of Context Source Map. This document reflects the system state as of June 15, 2026. Sections 1–14 are verified against the current production PostgreSQL schema and PHP service code. Sections 15–17 define reserved future context-source contracts; no underlying data stores or extraction infrastructure for these sources currently exist. No V2 implementation code was created during this audit. OpenAI connectivity is intentionally deferred to build #2780.*
