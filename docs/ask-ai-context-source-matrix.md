# Ask AI — Context Source Matrix

**Last Updated:** 2026-06-16  
**Version:** ASK_AI_CONTEXT_V1 / ASK_AI_RESPONSE_CONTRACT_V1

This document maps every Ask AI question type to its approved context sources, describes which pipeline components produce and consume each source, and summarises the governance constraints for each.

---

## 1. Question Type → Source Matrix

| Question Type          | Context Sources Used                                                                 | Required Sources (must be non-null) |
|------------------------|--------------------------------------------------------------------------------------|--------------------------------------|
| `listing_facts`        | `listing`, `faq_answers`                                                             | `listing`                            |
| `property_standout`    | `property_intelligence`, `listing` (title/type), `location_intelligence` (optional)  | `property_intelligence`              |
| `suited_audience`      | `property_intelligence`, `listing` (type), `location_intelligence`, `buyer_avatar`, `tenant_avatar` | `property_intelligence` |
| `buyer_tenant_match`   | `buyer_avatar`, `tenant_avatar`, `compatibility`                                     | `compatibility`                      |
| `compatibility_signals`| `compatibility`                                                                      | `compatibility`                      |
| `missing_data`         | `listing`, `offer_analysis` (optional)                                               | `listing`                            |
| `marketing_angles`     | `property_intelligence`, `listing` (title/type), `location_intelligence` (optional)  | `property_intelligence`              |
| `educational`          | `location_intelligence` (optional)                                                   | _(none)_                             |
| `agent_profile`        | `agent_profile`, `agent_presets`, `listing` (type/id)                               | _(none — always contract_ready)_     |
| `prohibited`           | _(none — always refused)_                                                            | _(n/a)_                              |

> **Note on `required_sources`:** When a required source is absent from the context, `AskAiResponseContractService` returns `status = insufficient_context` and the prompt builder produces an `insufficient_context` package. The pipeline returns without calling OpenAI. `agent_profile` intentionally has no required sources so the contract is always `contract_ready` even when the listing has no linked agent or the agent has no preset data — the LLM is instructed to state the information is unavailable.

---

## 2. Context Sources — Producers and Consumers

### `listing`
- **Produced by:** `AskAiContextBuilderService::extractListingFields()` (base fields + `extractFactualFields()`)
- **Source model:** `SellerAgentAuction`, `BuyerAgentAuction`, `LandlordAgentAuction`, `TenantAgentAuction`
- **Data type:** Native columns + EAV meta (`info($key)`)
- **Consumed by:** `listing_facts`, `property_standout`, `suited_audience`, `missing_data`, `marketing_angles`, `agent_profile`
- **Governance:** Only Public-Factual fields from `ASK_AI_FULL_CONTEXT_MAP.md`. PII (names, phone, email) excluded. Compliance-Sensitive fields (flood zone, security deposit) included with disclosure requirement.

### `faq_answers`
- **Produced by:** `AskAiContextBuilderService::buildFaqAnswers()` via `AsFaqAnswer` model
- **Consumed by:** `listing_facts` (umbrella `faq_answers` path covers all `faq_answers.*` leaves)
- **Governance:** Only `answer_text`, `question_label`, `question_group`, `intelligence_category` forwarded to LLM. Internal model attributes stripped by `AskAiPromptBuilderService::sanitizeFaqAnswers()`.

### `property_intelligence`
- **Produced by:** `AskAiContextBuilderService::buildPropertyIntelligence()` via `PropertyIntelligenceProfileService::buildPayloadReadOnly()`
- **Available for:** `seller`, `landlord` only
- **Consumed by:** `property_standout`, `suited_audience`, `marketing_angles`
- **Governance:** Never calls `generate()` (which persists side-effects). Read-only path only.

### `location_intelligence`
- **Produced by:** `AskAiContextBuilderService::buildLocationIntelligence()` via `LocationDnaIntelligenceContextService` + `LocationDnaMarketingContextService`
- **Available for:** all roles
- **Consumed by:** `property_standout`, `suited_audience`, `marketing_angles`, `educational`
- **Governance:** No crime statistics, demographic, or school-quality data exposed. Lifestyle scores only.

### `buyer_avatar`
- **Produced by:** `AskAiContextBuilderService::buildBuyerAvatar()` via `BuyerTenantDnaProfile`
- **Available for:** `buyer` only
- **Consumed by:** `buyer_tenant_match`, `suited_audience`
- **Governance:** Only avatar_type, personality tags, match preferences, preference summary. No PII.

### `tenant_avatar`
- **Produced by:** `AskAiContextBuilderService::buildTenantAvatar()` via `BuyerTenantDnaProfile`
- **Available for:** `tenant` only
- **Consumed by:** `buyer_tenant_match`, `suited_audience`
- **Governance:** Same as `buyer_avatar`.

### `compatibility`
- **Produced by:** `AskAiContextBuilderService::buildCompatibility()` via `ListingCompatibilityScore`
- **Available for:** supply/demand pairs only (requires `demand_listing_type`, `demand_listing_id`, `supply_listing_type`, `supply_listing_id` in `$options`)
- **Consumed by:** `buyer_tenant_match`, `compatibility_signals`
- **Governance:** Score values and narrative only. No PII. No protected-class inference.

### `offer_analysis`
- **Produced by:** `AskAiContextBuilderService::buildOfferAnalysis()` via `AcceptedBidSummary`
- **Available for:** all roles
- **Consumed by:** `missing_data`
- **Governance:** Only deal-content fields (id, listing_type, listing_id, accepted_bid_id, summary_html, summary_pdf_path, timestamps). Signature metadata and user IDs excluded.

### `agent_profile` _(new in Task #2959)_
- **Produced by:** `AskAiContextBuilderService::buildAgentProfile()` via `AgentProfileLoader`
- **Available for:** all four roles (resolved from `listing.user_id`)
- **Consumed by:** `agent_profile` question type only
- **Governance:** `AgentProfileLoader::PRIVATE_KEYS` excludes email, phone, fee amounts, commission rates, business card paths. Returns `null` when `user_id` is absent or the agent user is not found.
- **Fragment fields (public-safe):** `agent_name`, `short_id`, `brokerage`, `license_no`, `nar_id`, `year_licensed`, `years_experience`, `is_full_time`, `transactions_last_12_months`, `bio`, `awards_recognition`, `what_sets_you_apart`, `why_hire_you`, `review_1/2/3`, `reviews_links`, `website_link`, `intro_video_url`, `presentation_link`, `social_media`, `availability_status`, `avg_response_time`, `communication_style`, `preferred_contact_method`, `evenings_available`, `weekends_available`, `cities_served`, `counties_served`, `neighborhoods_served`, `primary_areas_served`, `areas_notes`, `services`, `other_services`, `marketing_plan`, `commission_structure`, `commission_structure_type`, `purchase_fee_type`, `lease_fee_type`, `retainer_fee_option`, `retainer_fee_application`, `protection_period`, `early_termination_fee_option`, `interested_in_selling`, `interested_in_selling_type`, `interested_in_property_management`, `interested_in_property_management_fee`

### `agent_presets` _(new in Task #2959)_
- **Produced by:** `AskAiContextBuilderService::buildAgentPresets()` via `AgentPresetLoader`
- **Available for:** all four roles (resolved from `listing.user_id`)
- **Consumed by:** `agent_profile` question type only
- **Governance:** `AgentPresetLoader::PRIVATE_KEYS` excludes email, phone, all fee/rate amounts, personal bio, credential details, and location service area fields. Returns `null` when no presets exist for the agent.
- **Fragment shape:** `{ presets: [...summary objects...], preset_count: N }`
- **Preset summary fields (public-safe):** `role`, `property_type`, `services`, `other_services`, `commission_structure`, `commission_structure_type`, `purchase_fee_type`, `lease_fee_type`, `retainer_fee_option`, `retainer_fee_application`, `protection_period`, `early_termination_fee_option`, `interested_in_selling`, `interested_in_property_management`, `interested_in_property_management_fee`

---

## 3. Pipeline Component Roles

| Component                        | Role                                                                                           |
|----------------------------------|-----------------------------------------------------------------------------------------------|
| `AskAiQuestionClassifierService` | Deterministic keyword classifier → returns `question_type`. `agent_profile` inserted before `listing_facts` in KEYWORD_RULES. |
| `AskAiContextBuilderService`     | Assembles all context sources for a given listing + role. Now includes `agent_profile` and `agent_presets` top-level keys. |
| `AskAiResponseContractService`   | Maps question type → allowed_context paths + governance rules. `agent_profile` contract has empty `required_sources`. |
| `AskAiPromptBuilderService`      | Filters context to contract's `allowed_context` paths and assembles the final prompt package. Passes `agent_profile` and `agent_presets` as top-level keys (single-segment paths). |
| `AskAiKnowledgeSourceRegistry`   | Authoritative registry of all approved knowledge sources. Now includes `agent_profile` (AGENT_PROFILE_V1) and `agent_presets` (AGENT_PRESETS_V1). |
| `AskAiInternalRunnerService`     | Chains context builder → contract → prompt builder. No agent-specific changes needed. |
| `AskAiRunnerV2Service`           | Full pipeline orchestration. `agent_profile` type skips normalizer (only fires on `unsupported`), FAQ detection (only on `listing_facts`), and Guard A/B (only on `faq_answers.*`/`listing.*` field keys). |

---

## 4. Role-to-Source Availability Matrix

| Source               | seller | buyer | landlord | tenant |
|----------------------|:------:|:-----:|:--------:|:------:|
| `listing`            | ✓      | ✓     | ✓        | ✓      |
| `faq_answers`        | ✓      | ✓     | ✓        | ✓      |
| `property_intelligence` | ✓   | —     | ✓        | —      |
| `location_intelligence` | ✓   | ✓     | ✓        | ✓      |
| `buyer_avatar`       | —      | ✓     | —        | —      |
| `tenant_avatar`      | —      | —     | —        | ✓      |
| `compatibility`      | pair   | pair  | pair     | pair   |
| `offer_analysis`     | ✓      | ✓     | ✓        | ✓      |
| `agent_profile`      | ✓      | ✓     | ✓        | ✓      |
| `agent_presets`      | ✓      | ✓     | ✓        | ✓      |

> **pair** = only when supply+demand listing pair options are provided to `buildForListing()`.  
> **—** = always null; not loaded for this role.  
> **✓** = attempted for all listings of this role; may be null if no data exists.

---

## 5. Classifier Keyword Order (KEYWORD_RULES evaluation order)

KEYWORD_RULES are evaluated top-to-bottom; the first match wins. Current order:

1. `prohibited` — fair housing refusals (confidence 1.0)
2. `agent_profile` — agent profile/bio/credentials/services (confidence 0.90) ← _new_
3. `listing_facts` — structural listing fields + FAQ answers (confidence 0.90)
4. `compatibility_signals` — compatibility score breakdown (confidence 0.85)
5. `property_standout` — property highlights/strengths (confidence 0.85)
6. `suited_audience` — ideal buyer/tenant lifestyle (confidence 0.80)
7. `buyer_tenant_match` — buyer/tenant fit assessment (confidence 0.80)
8. `missing_data` — missing/incomplete listing data (confidence 0.80)
9. `marketing_angles` — marketing copy/description writing (confidence 0.75)
10. `educational` — general real estate education (confidence 0.70)
11. `unsupported` — fallback (confidence 0.0)

`agent_profile` is placed **before** `listing_facts` to prevent generic listing-facts keywords from intercepting agent-intent questions.

---

## 6. Data Governance Summary

All agent context flowing into Ask AI obeys the following hard constraints regardless of question type:

| Constraint | Enforced by |
|-----------|------------|
| Email never exposed | `AgentProfileLoader::PRIVATE_KEYS` + `AgentPresetLoader::PRIVATE_KEYS` |
| Phone never exposed | Same as above |
| Specific fee amounts (flat/percentage) never exposed | PRIVATE_KEYS + suffix-based filter (`_flat_fee`, `_percentage`, `_price`, `_amount`) |
| Commission rates never exposed | PRIVATE_KEYS |
| Business card / document paths never exposed | PRIVATE_KEYS |
| Private keys removed at loader level | Loaders filter before fragment is returned to context builder |
| No OpenAI calls during context assembly | Context builder governance block; no LLM calls permitted |
| No DB writes during context assembly | Context builder governance block |
| Null-safe — never throws on missing agent | `buildAgentProfile()` and `buildAgentPresets()` return null when `user_id` is 0 or agent is not found |
