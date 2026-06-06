# Marketing Intelligence Audit

**Date:** 2026-06-06
**Scope:** PropertyMarketingBriefService, Marketing Context services, lifestyle/tag group maps, AI report pipeline, Ask AI marketing sources, output tables, UI views, tests.
**Goal:** Determine what marketing recommendations already exist and whether they can support a seller-facing listing presentation.

---

## 1. What marketing services exist?

Twelve services participate in the marketing intelligence pipeline. They are divided into four layers.

### Layer 1 — Deterministic Context Builders (no AI, no writes)

| Service | Phase | Purpose |
|---|---|---|
| `PropertyMarketingContextService` | P | Translates Phase O archetype tag explanation records into named attribute/transaction buckets |
| `BuyerTenantMarketingContextService` | P (parallel) | Translates buyer/tenant lifestyle tags and deal-breaker flags into named preference buckets |
| `LocationDnaMarketingContextService` | G | Reshapes `property_location_dna.summary_json` into four thematic marketing blocks |

### Layer 2 — Deterministic Brief Builder (no AI, no writes)

| Service | Phase | Purpose |
|---|---|---|
| `PropertyMarketingBriefService` | R | Assembles nine named sections from Phase P context using static maps only |
| `PropertyMarketingReadinessService` | U | Inspects Phase R output and determines whether three required information groups are present before allowing AI generation |

### Layer 3 — AI Report Pipeline (calls OpenAI, then writes)

| Service | Phase | Purpose |
|---|---|---|
| `AiMarketingReportGeneratorService` | XD | Assembles prompt payload from Phase P + R + U, calls `OpenAiClientService`, validates Phase W contract |
| `AiMarketingReportReviewService` | XF | In-memory compliance checker: contract presence, attribution, section status, publication safety, governance text scan |
| `AiMarketingReportOrchestratorService` | XG | Wires generator + reviewer into one in-memory pipeline, returns unified result |
| `AiMarketingReportPersistenceService` | XJ | Writes passing orchestration results to three DB tables (`marketing_reports`, `marketing_report_versions`, `marketing_report_audits`) |

### Layer 4 — Post-Generation Workflow (writes only, no AI)

| Service | Phase | Purpose |
|---|---|---|
| `AiMarketingReportAgentRevisionService` | XN | Allows the listing agent to revise four editable section texts; records versions and audits |
| `AiMarketingReportOwnerApprovalService` | XO | Allows seller/landlord owner to approve (`pending_review → seller_approved`) or reject (`pending_review → rejected`) |
| `AiMarketingReportPublicationService` | XP | Allows admin to publish (`seller_approved → published`) |

---

## 2. What sections do they generate?

### Phase R deterministic brief — 9 sections (always present, no AI)

| # | Key | Content |
|---|---|---|
| 1 | `property_attribute_context` | Pass-through of Phase P attribute buckets: property_type, property_style, property_condition, amenities, parking, features, policies, community, use_classification, governance |
| 2 | `transaction_context` | Pass-through of Phase P transaction buckets: timing, transaction_structure, financing, presentation |
| 3 | `quantitative_context` | Marketing hook records from Phase O (square footage, bed/bath counts, price, etc.) |
| 4 | `marketing_asset_checklist` | One entry per record in the `presentation` bucket; currently maps only `marketing:video-tour` |
| 5 | `missing_information_checklist` | One entry per empty named bucket across all 14 named dimensions |
| 6 | `seller_landlord_questions` | Pre-written clarifying questions for each empty or sparse bucket |
| 7 | `listing_preparation_notes` | Factual internal notes for timing, transaction_structure, and financing records (e.g., seller financing, assumable loan, lease-option) |
| 8 | `neutral_feature_summary` | Factual per-record entries drawn from all attribute and quantitative records |
| 9 | `summary` | Six integer counts: total_attribute_records, total_transaction_records, total_quantitative_records, marketing_assets, missing_dimensions, listing_preparation_entries |

### Phase W AI report — 5 sections (AI-authored narrative, persisted)

| # | Section key | Expected status | Content |
|---|---|---|---|
| 1 | `property_feature_narrative` | `pending_review` | AI-drafted narrative of property features |
| 2 | `transaction_terms_summary` | `pending_review` | AI-drafted summary of transaction terms |
| 3 | `marketing_asset_statement` | `pending_review` | AI-drafted statement about available marketing assets |
| 4 | `missing_information_note` | `internal_note` | AI-drafted internal note about missing data; read-only, not agent-editable |
| 5 | `listing_preparation_summary` | `pending_review` | AI-drafted preparation checklist narrative |

Each section carries `draft_text` (the AI narrative), `source_attribution` (array of source refs), and `status`.

---

## 3. What data sources feed them?

### Phase P / Phase R / Phase U (deterministic pipeline)

| Source | What it provides |
|---|---|
| `PropertyDnaProfile` (via `PropertyDnaExplanationService`) | Archetype tags (14 prefixes) → attribute/transaction buckets; marketing hook records → quantitative context |
| `PropertyDnaGenerator` (upstream of explanation) | Generates archetype tags from listing fields |

### TAG_GROUP_MAP — 14 archetype tag prefixes (PropertyMarketingContextService)

| Prefix | Context group | Bucket |
|---|---|---|
| `type` | attribute_context | property_type |
| `style` | attribute_context | property_style |
| `condition` | attribute_context | property_condition |
| `amenity` | attribute_context | amenities |
| `parking` | attribute_context | parking |
| `feature` | attribute_context | features |
| `policy` | attribute_context | policies |
| `community` | attribute_context | community |
| `use` | attribute_context | use_classification |
| `governance` | attribute_context | governance |
| `timing` | transaction_context | timing |
| `structure` | transaction_context | transaction_structure |
| `financing` | transaction_context | financing |
| `marketing` | transaction_context | presentation |

### LIFESTYLE_GROUP_MAP — 8 lifestyle tag prefixes (BuyerTenantMarketingContextService)

| Prefix / bare tag | Preference bucket |
|---|---|
| `prefers-type` | property_type_preference |
| `prefers-condition` | property_condition_preference |
| `has-pets` | occupant_signals |
| `seeks` | community_signals |
| `requires` | required_amenities |
| `open-to` | transaction_openness |
| `financial` | financial_signals |
| `preference` | policy_preferences |

### Phase G — Location context (LocationDnaMarketingContextService)

| Source | What it provides |
|---|---|
| `property_location_dna.summary_json` | Four thematic blocks: `coastal`, `daily_convenience`, `outdoor_recreation`, `transportation` with POI distances |

**Status: NOT yet connected to AI report.** The `LocationDnaMarketingContextService` governance block explicitly states integration into the AI Marketing Report pipeline is deferred until a separately approved hook phase.

### Buyer/Tenant context (parallel track)

`BuyerTenantMarketingContextService` exists and is tested, but its output (lifestyle preferences, required amenities, deal-breakers) is **not connected** to `PropertyMarketingBriefService` or `AiMarketingReportGeneratorService`.

### AI report prompt payload (Phase XC contract)

The payload sent to OpenAI contains exactly five keys:

```
phase_p          — PropertyMarketingContextService output
phase_r          — PropertyMarketingBriefService output
phase_u          — PropertyMarketingReadinessService output
required_contract — 'phase_w'
prompt_version   — from config('ai.prompt_version')
```

Location data and buyer/tenant preference data are **not** in the AI prompt.

---

## 4. Do they generate recommended audiences?

**No.** Every service governance block explicitly prohibits audience targeting, buyer persona inference, demographic grouping, "ideal buyer / ideal tenant" language, and any protected-class inference or characterization.

The Ask AI system has a `suited_audience` question type that produces an AI response describing property fit in lifestyle and preference terms only, constrained by `AskAiResponseContractService` to use only `property_intelligence.property_target_audiences`, positioning, personality tags, avatar type/personality/preference, and lifestyle categories — with mandatory Fair Housing disclosures. This is a conversational chatbot response only; it is not saved or exportable.

---

## 5. Do they generate marketing angles?

**Not in the persisted pipeline.** The Phase W AI report has no `marketing_angles` section; its five sections cover narrative, terms, assets, missing info, and preparation.

The Ask AI system has a `marketing_angles` question type drawing from:
- `property_intelligence.property_positioning`
- `property_intelligence.property_personality_tags`
- `property_intelligence.property_story`
- `property_intelligence.property_highlights`
- `location_intelligence.location_narrative`
- `location_intelligence.lifestyle_categories`
- `location_intelligence.marketing_context`
- `listing.listing_title`, `listing.property_type`

These are real-time conversational responses only — not saved, not versioned, not part of any approval workflow.

---

## 6. Do they generate headlines / ad copy / social copy / email copy?

**No.** None of these exist anywhere in the current system.

Every service governance block explicitly prohibits generating "narrative marketing copy," "ad copy," "listing descriptions," or "persuasive text." The Phase W AI report generates internal `draft_text` narratives that go through agent-revision + owner-approval + admin-publication before they could theoretically be used externally — but even after `published` status, no export or delivery mechanism exists.

---

## 7. Are outputs saved anywhere?

### Phase R brief — NOT saved

`PropertyMarketingBriefService::build()` computes the 9-section brief entirely in memory on every call. It writes nothing to the database and is recomputed each time it is requested.

### Phase W AI report — YES, saved to three tables

| Table | What is stored |
|---|---|
| `marketing_reports` | UUID, listing_id, profile_id, generated_at, ai_model, prompt_template_version, phase_r/u versions, readiness_snapshot (JSONB), sections (JSONB — all 5 sections), attribution_verified, status |
| `marketing_report_versions` | One row per section per revision: marketing_report_id, section_key, version_number, draft_text, source_attribution (JSONB), status, created_by |
| `marketing_report_audits` | Append-only audit log: event_type (`generation`, `review`, `readiness_failure`, `attribution_failure`), report_id, listing_id, profile_id, actor_id, event_at, event_data (JSONB) |

### Status lifecycle in `marketing_reports`

```
pending_review
  → seller_approved  (owner approves via AiMarketingReportOwnerApprovalService)
  → rejected         (owner rejects via AiMarketingReportOwnerApprovalService)
  → published        (admin publishes a seller_approved report via AiMarketingReportPublicationService)
```

The DB constraint also allows `agent_approved` and `held_attribution_failure`, but no service currently writes either status. `archived` is explicitly deferred — absent from the DB constraint.

---

## 8. Is there any UI to view them?

### Admin UI

| Route | View | What it shows |
|---|---|---|
| `GET /admin/property-dna/{profile}/marketing-brief-preview` | `admin.dna.marketing-brief-preview` | All 9 Phase R brief sections in raw form; "Generate Marketing Report" button shown only when no report exists yet |
| `GET /admin/property-dna/marketing-reports/{report}` | `admin.dna.marketing-report-show` | AI report record + all section versions + full audit log |
| `POST /admin/property-dna/{profile}/marketing-reports/generate` | redirect | Triggers AI generation pipeline |
| `POST /admin/property-dna/marketing-reports/{report}/publish` | redirect | Publishes a seller_approved report |

### Agent UI

| Route | View | What it shows |
|---|---|---|
| `GET /agent/property-dna/{profile}/marketing-brief-review` | `agent.dna.marketing-brief-review` | All 9 Phase R brief sections; read-only |
| `GET /agent/property-dna/marketing-reports/{report}` | `agent.dna.marketing-report-show` | AI report sections + version history + audits; agent can revise 4 sections |
| `POST /agent/property-dna/marketing-reports/{report}/sections/{section}` | redirect | Submits an agent section revision |

### Owner (Seller/Landlord) UI

| Route | View | What it shows |
|---|---|---|
| `GET /owner/property-dna/marketing-reports/{report}/approval` | `owner.dna.marketing-report-approval` | AI report sections + versions + audits; approve or reject actions |
| `POST /owner/property-dna/marketing-reports/{report}/approve` | redirect | Approves report (`pending_review → seller_approved`) |
| `POST /owner/property-dna/marketing-reports/{report}/reject` | redirect | Rejects report with optional reason (`pending_review → rejected`) |

### No public or buyer-facing UI

No marketing output is surfaced on any public listing page, buyer-facing page, or API endpoint. All views are behind `agentAuth`, `adminAuth`, or `auth + verified + noAdmin` middleware.

---

## 9. Can they be regenerated?

### Phase R brief — yes, always

Computed fresh on every request. No state. `PropertyMarketingBriefService::build($profile)` can be called at any time.

### Phase W AI report — limited

- An admin can trigger generation once per profile via the admin brief preview page.
- A duplicate `report_id` guard in `AiMarketingReportPersistenceService` prevents writing the same report_id twice.
- Once a report exists for a profile, the admin UI shows "A marketing report already exists for this profile" and hides the generate button.
- There is **no re-generation path** for a profile that already has a report (e.g., after rejection or after the profile is updated with new listing data).
- After owner rejection, the report sits at `rejected` status permanently with no reset or re-trigger workflow.

### Location DNA context — yes, read-only

`LocationDnaMarketingContextService` reads from `property_location_dna.summary_json` on every call. It reflects the current state of Location DNA data whenever called — but its output is not yet connected to the AI report pipeline.

---

## 10. What is missing for a seller listing presentation?

The following capabilities do not exist and would need to be built for a seller-facing listing presentation feature.

### Missing: Seller presentation view / exportable document

There is no seller-readable summary of what the system knows about their property or how it will be marketed. The owner approval view is not designed as a presentation — it is an approval workflow page. No PDF export, downloadable packet, or printable view exists for any marketing output.

### Missing: Location data in the AI report

`LocationDnaMarketingContextService` (Phase G) is fully built and tested. Its output (coastal features, daily convenience, outdoor recreation, transportation proximity) is directly relevant to listing presentations. It is explicitly deferred from the AI report pipeline due to governance protection on `AiMarketingReportGeneratorService`. Connecting it requires an approved hook phase.

### Missing: Marketing angles as a persisted, structured output

`marketing_angles` exists only as an Ask AI conversational question type — real-time, not saved, not versioned. No service generates and persists marketing angle suggestions as structured data that an agent or seller could review, download, or act on.

### Missing: Recommended audience statement

No service produces any form of audience characterization in a saved or presentable format. The AskAi `suited_audience` type approaches this in conversation only. Any compliant, Fair Housing-safe audience statement for a seller presentation would require a purpose-built service with its own governance review.

### Missing: Headline and copy generation

No service generates listing headlines, taglines, social media copy, email campaign copy, or ad copy. This is explicitly prohibited by current governance blocks in every marketing service. Building it would require a new, separately approved service with its own Fair Housing compliance layer.

### Missing: Agent-approved status transition

The DB constraint includes `agent_approved` as a valid status, and agents can revise sections. However, no service or controller transitions a report to `agent_approved`. The current flow moves directly from `pending_review` (agent can revise) to owner review, with no formal "agent has approved this for owner review" gate.

### Missing: Regeneration path after rejection

When an owner rejects a report, no workflow exists to create a new version. The report sits at `rejected` permanently. A seller presentation feature would need either a "request new report" flow (new `marketing_reports` row) or a "reset and regenerate" path.

### Missing: Notification when report is ready for review

No email or in-app notification is sent to the owner when a report enters `pending_review`, or to the agent when the owner approves/rejects. The owner has no way to know a report is waiting without navigating directly to the approval URL.

### Missing: Buyer/tenant preference data in property marketing context

`BuyerTenantMarketingContextService` exists and is tested, but demand-side signal data (what buyers/tenants are looking for) is not connected to the property marketing pipeline. This data could provide relevant positioning context for a seller presentation.

### Missing: Public listing page integration

No marketing report content (Phase W sections, Phase R brief, or Ask AI marketing angles) appears on any public-facing listing page. After a report reaches `published` status, it has no delivery path to the public listing or to prospective buyers.

---

## Summary table

| Capability | Exists? | Notes |
|---|---|---|
| Deterministic property context (attribute/transaction/quantitative) | Yes | Phase P, computed on-demand |
| Deterministic brief with seller questions and prep notes | Yes | Phase R, computed on-demand |
| Marketing readiness gate | Yes | Phase U |
| AI-authored narrative sections (5) | Yes | Phase W, persisted in DB |
| Agent section revision | Yes | Phase XN |
| Owner approve/reject | Yes | Phase XO |
| Admin generate + publish | Yes | Phase XJ + XP |
| Admin + agent + owner views | Yes | Blade views exist |
| Location context (thematic POI data) | Built, not connected | Phase G, explicitly deferred |
| Buyer/tenant preference context | Built, not connected | Parallel Phase P track |
| Marketing angles (persisted/structured) | No | Ask AI chatbot only |
| Recommended audiences (any form) | No | Governance-blocked |
| Headlines / ad copy / social copy / email copy | No | Governance-blocked |
| Seller-facing presentation view | No | Owner view is approval-only |
| PDF/export of any marketing output | No | Not built |
| Agent-approved status transition | No | DB constraint allows it; no service writes it |
| Regeneration path after rejection | No | Terminal state, no reset path |
| Notification system (ready / approved / rejected) | No | Not built |
| Public listing page integration | No | No delivery path after publish |

---

## Relevant files

`app/Services/Dna/PropertyMarketingContextService.php`
`app/Services/Dna/PropertyMarketingBriefService.php`
`app/Services/Dna/PropertyMarketingReadinessService.php`
`app/Services/Dna/AiMarketingReportGeneratorService.php`
`app/Services/Dna/AiMarketingReportReviewService.php`
`app/Services/Dna/AiMarketingReportOrchestratorService.php`
`app/Services/Dna/AiMarketingReportPersistenceService.php`
`app/Services/Dna/AiMarketingReportAgentRevisionService.php`
`app/Services/Dna/AiMarketingReportOwnerApprovalService.php`
`app/Services/Dna/AiMarketingReportPublicationService.php`
`app/Services/Dna/BuyerTenantMarketingContextService.php`
`app/Services/LocationDna/LocationDnaMarketingContextService.php`
`app/Services/AskAi/AskAiKnowledgeSourceRegistry.php`
`app/Services/AskAi/AskAiResponseContractService.php`
`app/Services/AskAi/AskAiQuestionClassifierService.php`
`app/Services/AskAi/AskAiSuggestedQuestionsService.php`
`app/Http/Controllers/Admin/AiMarketingReportAdminController.php`
`app/Http/Controllers/Admin/AiMarketingReportPublicationController.php`
`app/Http/Controllers/Agent/AiMarketingReportAgentController.php`
`app/Http/Controllers/Agent/PropertyMarketingBriefReviewController.php`
`app/Http/Controllers/Owner/AiMarketingReportOwnerApprovalController.php`
`resources/views/admin/dna/marketing-brief-preview.blade.php`
`resources/views/admin/dna/marketing-report-show.blade.php`
`resources/views/agent/dna/marketing-brief-review.blade.php`
`resources/views/agent/dna/marketing-report-show.blade.php`
`tests/Unit/Services/Dna/AiMarketingReportGeneratorServiceTest.php`
`tests/Unit/Services/LocationDna/LocationDnaMarketingContextServiceTest.php`
`tests/Feature/AiMarketingReportGenerateAdminTest.php`
