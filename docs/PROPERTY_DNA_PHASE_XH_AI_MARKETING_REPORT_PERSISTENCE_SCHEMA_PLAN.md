# Property DNA Phase XH — AI Marketing Report Persistence & Audit Schema Plan

**Document Date:** 2026-05-31
**Phase:** XH — AI Marketing Report Persistence & Audit Schema Plan
**Preceding Phases:** P, Q, R, S, T, U, V, W, X, XA, XB, XC, XD, XE, XF, XG
**Type:** Schema planning and documentation only — no code, no migrations, no schema changes, no routes, no UI

---

## 1. Purpose

This document defines the persistence and audit schema for the AI Marketing Intelligence Report feature introduced in Phases W and X. It establishes the authoritative design targets for the three database tables that will store generated reports, section-level version history, and the append-only audit log. Every subsequent implementation phase that reads from or writes to these tables must conform to the schema defined here.

### Why This Document Exists

Phase W defined the exact JSON contract that every AI Marketing Intelligence Report must conform to, including all field shapes, section status values, source attribution requirements, and the generation audit record fields that must be captured for each event. Phase X defined the nine-stage generation workflow, the four required audit record types, the field-level design targets for three future database entities (`MarketingReport`, `MarketingReportVersion`, `MarketingReportAudit`), and the ordered sequence of future phases (XA through XF) that consume those entities. Phase XG completed the in-memory orchestration pipeline.

Before any migration, table creation, route, controller, admin UI, agent review UI, or seller/landlord review workflow is built, the persistence and audit schema must be formally specified in a single reference document. This document serves that purpose. It collects every field, constraint, retention rule, and access requirement already specified in Phases W and X into one structured plan, so that each downstream implementation phase has a single authoritative schema reference.

### What This Document Governs

- The schema design of `marketing_reports`, `marketing_report_versions`, and `marketing_report_audits`
- The boundary rule for when reports may be persisted (after generation plus review pass; never mid-pipeline)
- The append-only integrity rules for the audit table
- The five-year retention obligations on all audit records
- The access and write-permission rules for each table by application role
- The ordered phase sequence (XB through XF) that consumes these tables

### Relationship to Phases W and X

This document does not introduce new constraints. Every field, rule, and requirement here is derived directly from Phase W (the AI Marketing Intelligence Report Contract) and Phase X (the AI Marketing Intelligence Implementation Architecture). In all cases of apparent conflict between this document and Phases W or X, Phases W and X take precedence. This document is a consolidation of those authoritative sources into a schema-planning form.

---

## 2. Persistence Scope

### What Gets Persisted

The following data is persisted to the database as part of the AI Marketing Intelligence Report lifecycle:

| Data | Table | When Persisted |
|---|---|---|
| The generated report object: listing context, readiness snapshot, all five sections with their initial `draft_text`, `source_attribution`, and `status` values, generation metadata, and `attribution_verified` flag | `marketing_reports` | After Stage 6 (Attribution Verification) passes and the generation audit record is confirmed writable — before the report is surfaced to any user |
| Each section's initial AI-generated content and all subsequent agent revisions, stored as distinct versioned records | `marketing_report_versions` | At report creation (initial version for each section) and at each agent revision action |
| Audit records for all four event types: generation, agent/seller-landlord review, readiness gate failure, and attribution failure | `marketing_report_audits` | Atomically with each triggering event; see Section 7 for append-only rules |

### What Stays In Memory Only

The following data is produced during the generation workflow but is **never persisted** to any database table:

| Data | Reason Not Persisted |
|---|---|
| The raw AI model API response before contract-shape validation | A non-conforming response causes generation failure; no partial data is stored |
| Intermediate attribution verification state during claim-by-claim checking | Transient computation; only the final `attribution_verified` boolean is stored |
| The in-memory orchestrator result array produced by `AiMarketingReportOrchestratorService::run()` | Phase XG is explicitly memory-only; nothing from the orchestrator is written to any store |
| Cached or session-persisted Phase R brief and Phase U readiness outputs | These must always be recomputed fresh; stored snapshots belong only in generation audit records, not in a cache |

### The Boundary Rule

**Reports may be persisted only after the complete generation-and-review pipeline has produced a valid, attributed report object.** Specifically:

1. Stage 4 (Readiness Gate) must have passed (`is_marketing_ready === true`).
2. Stage 5 (AI Generation Request) must have returned a response that passes all contract-shape validation checks (Phase X Section 3.5).
3. Stage 6 (Attribution Verification) must have completed, with unattributable claims removed from `draft_text`, and the `attribution_verified` flag set.
4. The generation audit record in `marketing_report_audits` must be confirmed writable before the report object is surfaced to any user.

No partial report object, no mid-pipeline snapshot, and no best-effort report created from a gate failure or a validation failure may be stored in `marketing_reports`. If the generation audit record cannot be created, the generation is treated as failed and the report object is discarded.

---

## 3. Proposed Tables

Three tables are proposed to support the full AI Marketing Intelligence Report lifecycle:

| Table | Role |
|---|---|
| `marketing_reports` | Primary record for each generated report, storing the report object, its lifecycle status, and all generation provenance metadata. One row per generation event. |
| `marketing_report_versions` | Section-level version history, storing the original AI-generated text and every subsequent agent revision for each section of each report. Supports full revision history and compliance auditability. |
| `marketing_report_audits` | Append-only audit log covering all four event types across the report lifecycle: generation, review (agent and seller/landlord), readiness gate failure, and attribution failure. No UPDATE or DELETE is permitted on this table. |

Each table is described in full in Sections 4, 5, and 6 respectively.

---

## 4. `marketing_reports` Table Design

This table holds one row per generated AI Marketing Intelligence Report. It stores the report's top-level metadata, the readiness snapshot at generation time, the persisted section content, the generation provenance fields, the `attribution_verified` flag, and the overall lifecycle status.

### 4.1 Schema Design Target

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `uuid` | NOT NULL | — | Primary key. Corresponds to `report_id` in the Phase W JSON contract. Assigned at generation time before the report is surfaced to any user. |
| `listing_id` | `bigint` | NOT NULL | — | Foreign key to the property listing. Matches `listing_context.listing_id` in the report object. |
| `profile_id` | `bigint` | NOT NULL | — | Foreign key to `property_dna_profiles`. Matches `listing_context.profile_id` in the report object. |
| `generated_at` | `timestamp with time zone` | NOT NULL | — | UTC timestamp of the AI generation call. Must match `generated_at` in the generation audit record and in the report object itself. |
| `ai_model` | `varchar(255)` | NOT NULL | — | AI model identifier and version string at generation time (e.g., `gpt-4o-2024-08-06`). From `generation_metadata.ai_model` in the report object. |
| `prompt_template_version` | `varchar(255)` | NOT NULL | — | Version identifier of the prompt template used for this generation. From `generation_metadata.prompt_template_version`. Must be version-controlled and match the corresponding generation audit record. |
| `report_contract_version` | `varchar(255)` | NOT NULL | `'phase-w-v1'` | The Phase W contract version active at the time of generation (e.g., `phase-w-v1`). Enables future auditors to determine which contract constraints governed a given generation event. Sourced from Phase W Section 9.7. |
| `phase_r_brief_version` | `varchar(255)` | NOT NULL | — | Hash or version token of the Phase R brief snapshot provided to the AI. From `generation_metadata.phase_r_brief_version`. |
| `phase_u_readiness_version` | `varchar(255)` | NOT NULL | — | Hash or version token of the Phase U readiness snapshot provided to the AI. From `generation_metadata.phase_u_readiness_version`. |
| `readiness_snapshot` | `jsonb` | NOT NULL | — | Serialized snapshot of the Phase U readiness output at generation time. Contains `is_marketing_ready`, `present_groups`, and `missing_groups`. Must not be updated after generation. |
| `sections` | `jsonb` | NOT NULL | — | Persisted JSON of all five named section objects at the time of initial creation. Each section contains `draft_text`, `status`, and `source_attribution`. Updated as section statuses transition through the review lifecycle. |
| `attribution_verified` | `boolean` | NOT NULL | `false` | Set to `true` only when all sections with non-empty `draft_text` carry at least one `source_attribution` entry. A report with `false` must not be surfaced to any user. |
| `status` | `varchar(50)` | NOT NULL | `'pending_review'` | Overall lifecycle status of the report. Constrained to: `pending_review`, `agent_approved`, `seller_approved`, `published`, `rejected`, `held_attribution_failure`. Transitions are gated by affirmative human actions — no auto-transitions. |
| `created_at` | `timestamp with time zone` | NOT NULL | `now()` | Record creation timestamp. Set once at INSERT; never updated. |
| `updated_at` | `timestamp with time zone` | NOT NULL | `now()` | Record last updated timestamp. Updated on each status transition or section content update. |

### 4.2 Constraints and Indexes

| Constraint / Index | Type | Description |
|---|---|---|
| `marketing_reports_pkey` | PRIMARY KEY | On `id` (UUID). |
| `marketing_reports_listing_id_idx` | INDEX | On `listing_id` to support querying all reports for a given listing. |
| `marketing_reports_profile_id_idx` | INDEX | On `profile_id` to support querying all reports for a given profile. |
| `marketing_reports_status_idx` | INDEX | On `status` to support admin and agent filtered views. |
| `marketing_reports_generated_at_idx` | INDEX | On `generated_at` to support date-range audit queries. |
| `marketing_reports_attribution_verified_idx` | INDEX | On `attribution_verified` to support identifying held reports. |

### 4.3 Status Value Constraints

The `status` column is constrained to the following values. No other value is permitted. Status transitions must be initiated by explicit, recorded human actions or by the system on confirmed failure conditions.

| Value | Meaning | Who Sets It |
|---|---|---|
| `pending_review` | Report created; awaiting agent review | Set at report creation |
| `agent_approved` | All required sections have received agent approval or revision | Set after all section review actions are complete (Phase XD) |
| `seller_approved` | Seller or landlord has affirmatively approved the content | Set after explicit seller/landlord approval action (Phase XE) |
| `published` | Report content has been transmitted to authorized channels after Fair Housing compliance review | Set after qualified human reviewer sign-off (Phase XF) |
| `rejected` | Agent or seller/landlord has rejected the report | Set after explicit rejection action |
| `held_attribution_failure` | Attribution verification failed; report is held and not surfaced to any user | Set by system on attribution failure |

---

## 5. `marketing_report_versions` Table Design

This table stores section-level version history for each report. Every time the initial AI-generated content is created or an agent submits a revision, a new row is inserted. The original AI draft and all subsequent agent-modified versions are preserved as distinct rows, enabling full revision history and compliance auditability.

### 5.1 Schema Design Target

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | NOT NULL | auto-increment | Primary key. |
| `marketing_report_id` | `uuid` | NOT NULL | — | Foreign key to `marketing_reports.id`. Links this version record to the parent report. |
| `section_key` | `varchar(100)` | NOT NULL | — | The report section this version belongs to. Constrained to: `property_feature_narrative`, `transaction_terms_summary`, `marketing_asset_statement`, `missing_information_note`, `listing_preparation_summary`. |
| `version_number` | `integer` | NOT NULL | — | Incrementing version counter scoped per `(marketing_report_id, section_key)`. Version 1 is always the initial AI-generated draft. Each agent revision increments this counter. |
| `draft_text` | `text` | NOT NULL | `''` | The content of this version of the section. Empty string for sections where the AI produced no content or the section was cleared. Must not be null. |
| `source_attribution` | `jsonb` | NOT NULL | `'[]'` | The `source_attribution` array for this version. Each entry contains `source_section` (string) and `source_records` (array of strings) as defined in Phase W Section 5.1. Empty array for sections with empty `draft_text`. |
| `status` | `varchar(50)` | NOT NULL | `'pending_review'` | Section status at this version. Constrained to: `pending_review`, `approved`, `revised`, `rejected`, `internal_note`. Matches the Phase W Section 3.3 allowed values. |
| `created_by` | `varchar(255)` | NOT NULL | — | `'ai_generated'` for the initial version (version 1). For agent revisions, stores the user identifier of the agent who submitted the revision. |
| `created_at` | `timestamp with time zone` | NOT NULL | `now()` | Record creation timestamp. Set once at INSERT; never updated. Version records are immutable after creation. |

### 5.2 Constraints and Indexes

| Constraint / Index | Type | Description |
|---|---|---|
| `marketing_report_versions_pkey` | PRIMARY KEY | On `id`. |
| `marketing_report_versions_report_section_version_uq` | UNIQUE | On `(marketing_report_id, section_key, version_number)`. Enforces that no two version records share the same version number for the same section of the same report. |
| `marketing_report_versions_report_id_idx` | INDEX | On `marketing_report_id` to support retrieving all versions for a given report. |
| `marketing_report_versions_section_key_idx` | INDEX | On `(marketing_report_id, section_key)` to support retrieving all versions of a specific section. |
| `marketing_report_versions_created_by_idx` | INDEX | On `created_by` to support audit queries filtering by human vs. AI authorship. |

### 5.3 Versioning Rules

1. Version 1 is always the initial AI-generated draft, created at report creation time (Phase XB). `created_by` is `'ai_generated'`.
2. Each affirmative agent revision action (Phase XD) inserts a new version record with an incremented `version_number` and `created_by` set to the agent's user identifier.
3. Version records are immutable after creation. No UPDATE or DELETE may be applied to any version row. Error corrections must take the form of a new version record.
4. The highest `version_number` for a given `(marketing_report_id, section_key)` pair is the current version of that section.
5. The original AI-generated text (version 1) is always retained alongside all revisions, regardless of subsequent agent actions.

---

## 6. `marketing_report_audits` Table Design

This table is the append-only audit log for all events in the AI Marketing Intelligence Report lifecycle. It records generation events, agent and seller/landlord review actions, readiness gate failures, and attribution failures. No row in this table may ever be modified or deleted by any application process.

### 6.1 Schema Design Target

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `bigint` | NOT NULL | auto-increment | Primary key. |
| `event_type` | `varchar(50)` | NOT NULL | — | The type of event recorded. Constrained to: `generation`, `review`, `readiness_failure`, `attribution_failure`. |
| `report_id` | `uuid` | NULL | — | Foreign key to `marketing_reports.id`. NULL for `readiness_failure` events where no report was created (the gate aborted before a report object existed). NOT NULL for all other event types. |
| `listing_id` | `bigint` | NOT NULL | — | The property listing identifier. Always present, even for `readiness_failure` events where no report exists. |
| `profile_id` | `bigint` | NOT NULL | — | The `PropertyDnaProfile` identifier. Always present for all event types. |
| `actor_id` | `bigint` | NULL | — | User identifier for review events (the agent, seller, or landlord performing the action). NULL for system-generated events (`generation`, `readiness_failure`, `attribution_failure`). |
| `event_at` | `timestamp with time zone` | NOT NULL | — | UTC timestamp of the event. For generation events, matches `generated_at` in the report object. |
| `event_data` | `jsonb` | NOT NULL | — | Event-specific payload. Shape varies by `event_type`. See Section 6.2 for the required payload shape per event type. |
| `created_at` | `timestamp with time zone` | NOT NULL | `now()` | Record insertion timestamp. Set once at INSERT; never modified. |

### 6.2 `event_data` Payload Shape by `event_type`

#### `generation` Event

A generation event is created for each successful AI generation call, before the report is surfaced to any user. If this record cannot be persisted, the generation is treated as failed.

| `event_data` Field | Type | Description |
|---|---|---|
| `ai_model` | string | The AI model identifier and version (e.g., `gpt-4o-2024-08-06`) |
| `prompt_template_version` | string | The version identifier of the prompt template used |
| `report_contract_version` | string | The Phase W contract version active at generation time (e.g., `phase-w-v1`). Corresponds to `report_version` in Phase W Section 9.1 and `report_contract_version` in `marketing_reports`. |
| `phase_r_brief_snapshot` | object | Serialized snapshot of the Phase R brief array provided to the AI as input. Serves as the authoritative attribution reference if the profile is later updated. |
| `phase_u_readiness_snapshot` | object | Serialized snapshot of the Phase U readiness review array at generation time. |
| `is_marketing_ready_at_call` | boolean | The readiness gate result evaluated immediately before the AI call. |
| `attribution_verified` | boolean | The `attribution_verified` value of the generated report object. |

#### `review` Event

A review event is created for each affirmative agent review action on any report section, and for each seller/landlord approval or rejection action. One audit record is created per section per action.

| `event_data` Field | Type | Description |
|---|---|---|
| `section_key` | string | The report section key that was reviewed (e.g., `property_feature_narrative`). For seller/landlord whole-report approval, this is `'all_sections'`. |
| `action` | string | One of: `approved`, `approved_with_revisions`, `rejected`. |
| `revisions_made` | boolean | Whether the reviewer modified the AI draft text. |
| `original_ai_text` | string | The original AI-generated `draft_text` (version 1) before any revisions. Retained in the audit record even if subsequently revised. |
| `approved_text` | string | The final approved or revised text. Identical to `original_ai_text` if no revisions were made; agent-modified text if revisions were made; empty or null for rejections. |
| `review_version_id` | integer | The `id` of the `marketing_report_versions` row that corresponds to the version being reviewed or approved. |

#### `readiness_failure` Event

A readiness failure event is created each time the readiness gate is evaluated and fails. No AI call is initiated. No report object exists; `report_id` on the parent row is NULL.

| `event_data` Field | Type | Description |
|---|---|---|
| `missing_groups` | array of strings | The information group names that were absent at gate evaluation time. |
| `gate_result` | boolean | Always `false` for this event type. |
| `gate_evaluated_at` | string | UTC ISO 8601 timestamp of the gate evaluation. |

#### `attribution_failure` Event

An attribution failure event is created when a report generation call produces a report with `attribution_verified: false` after unattributable claims have been removed per Phase X Section 4.2.

| `event_data` Field | Type | Description |
|---|---|---|
| `unattributed_sections` | array of strings | The section keys where `draft_text` was non-empty but `source_attribution` was empty before removal. |
| `detected_at` | string | UTC ISO 8601 timestamp of detection. |
| `action_taken` | string | One of: `report_discarded`, `report_held_pending_resolution`. |

### 6.3 Constraints and Indexes

| Constraint / Index | Type | Description |
|---|---|---|
| `marketing_report_audits_pkey` | PRIMARY KEY | On `id`. |
| `marketing_report_audits_event_type_idx` | INDEX | On `event_type` to support audit queries filtered by event type. |
| `marketing_report_audits_report_id_idx` | INDEX | On `report_id` to support retrieving all audit records for a given report. |
| `marketing_report_audits_listing_id_idx` | INDEX | On `listing_id` to support audit queries by listing. |
| `marketing_report_audits_actor_id_idx` | INDEX | On `actor_id` to support audit queries by user identifier. |
| `marketing_report_audits_event_at_idx` | INDEX | On `event_at` to support date-range audit queries. |

---

## 7. Append-Only Audit Rules

The `marketing_report_audits` table is a tamper-evident, append-only audit log. The following rules are non-negotiable and must be enforced at both the database level and the application level.

### 7.1 No UPDATE or DELETE

1. No application role, database user, or ORM operation may execute an `UPDATE` or `DELETE` statement on any row in `marketing_report_audits`.
2. This prohibition applies without exception — including administrative correction operations, data migrations, and seeder scripts.
3. `marketing_report_versions` rows are likewise immutable after INSERT (see Section 5.3), but the strictest append-only enforcement applies to `marketing_report_audits`.

### 7.2 Enforcement Mechanisms

Two independent enforcement layers must be implemented:

1. **Database-level trigger:** A `BEFORE UPDATE OR DELETE` trigger on `marketing_report_audits` must raise an exception and abort the operation unconditionally, regardless of the calling application role. This is the primary enforcement mechanism.
2. **Application-layer constraint:** No application code path — including admin controllers, Artisan commands, and test helpers — may construct or execute an `UPDATE` or `DELETE` query against `marketing_report_audits`. Code review and the CI pipeline must enforce this.

### 7.3 Error Correction by New Record

If an audit record contains an error (for example, an incorrect `actor_id` or an incorrectly recorded `action`), the correction must take the form of a new audit record inserted into the table. The correction record must:

1. Use the same `event_type` as the record being corrected.
2. Include a `corrects_audit_id` field in `event_data` identifying the `id` of the original erroneous record.
3. Include a `correction_reason` field in `event_data` with a plain-language description of the error and the correction applied.
4. Be inserted by an authorized actor with a recorded `actor_id` — no anonymous corrections are permitted.

The original erroneous record is retained in full. The correction record does not overwrite it; the two records coexist in the table permanently.

### 7.4 Valid Correction Record

A correction record is valid only when it satisfies all of the following:

- It is a new INSERT — no existing row is modified.
- `event_data.corrects_audit_id` is present and references a real, existing `id` in `marketing_report_audits`.
- `event_data.correction_reason` is a non-empty string.
- `actor_id` is non-null and identifies the authorized actor who initiated the correction.
- The `event_type` of the correction record matches the `event_type` of the record being corrected.

### 7.5 No Application Role May Perform UPDATE or DELETE

The following roles and code paths are explicitly prohibited from performing any UPDATE or DELETE on `marketing_report_audits`:

- Platform administrator controllers and admin panel UI
- Agent-facing controllers and Livewire components
- Seller/landlord-facing controllers and Livewire components
- Any artisan command, scheduled job, or queue worker
- Any seeder or test factory

Only the system-generated audit insertion path (invoked by the report generator service, the review service, and the gate evaluation service) may INSERT rows. Even that path may not UPDATE or DELETE.

---

## 8. Retention Requirements

### 8.1 Minimum Five-Year Retention

All rows in `marketing_report_audits` must be retained for a minimum of five years from their `created_at` date, consistent with HUD Fair Housing recordkeeping guidance and applicable state real estate law. This obligation applies regardless of whether the associated listing has been closed, sold, cancelled, or deleted.

Sourced from Phase W Section 9.5 and Phase X Section 7.5.

### 8.2 Tables Carrying Retention Obligations

| Table | Retention Obligation | Retention Clock |
|---|---|---|
| `marketing_report_audits` | 5 years minimum, all rows | From `created_at` of each row |
| `marketing_reports` | Retain while any associated audit record is within the retention window | From `created_at` of the report row |
| `marketing_report_versions` | Retain while the parent `marketing_reports` row is retained | From `created_at` of each version row |

### 8.3 Deletion Prohibition

Deletion of audit records is prohibited unless required by a specific legal obligation that supersedes the retention requirement (e.g., a court order, a right-to-erasure request under an applicable statute that explicitly overrides real estate recordkeeping law). Any deletion under a legal obligation must itself be documented in a separate compliance log that is outside the normal application flow.

### 8.4 Export Format Requirements

Authorized platform administrators must be able to export audit records in both CSV and JSON formats for use as Fair Housing compliance evidence. The export must support filtering by:

- Listing identifier
- User identifier (`actor_id`)
- Date range (on `event_at`)
- Event type (`event_type`)

The export must include all `event_data` fields in their complete, unredacted form. No field may be omitted or masked in a compliance export.

### 8.5 Retention Clock

The retention clock for each row in `marketing_report_audits` starts from the row's own `created_at` timestamp — not from the listing creation date, the report generation date, or any other event. A readiness failure audit record created on 2026-05-31 must be retained until at least 2031-05-31, regardless of when the associated listing was created.

---

## 9. Access and Control Requirements

### 9.1 Who May Read Each Table

| Table | Read-Permitted Roles |
|---|---|
| `marketing_reports` | Platform administrators (all records); listing agents (only reports for their own listings); sellers/landlords (only the report for their own listing, and only sections with `status: approved` or `status: revised` after agent approval is complete) |
| `marketing_report_versions` | Platform administrators (all records); listing agents (all versions for their own listing's reports); sellers/landlords (only the current approved version for their own listing) |
| `marketing_report_audits` | Platform administrators (all records, read-only); no other application role may directly query this table — all agent and seller/landlord access to audit information must be mediated by a read-only service layer |

### 9.2 Who May Write to Each Table

| Table | Write-Permitted Operations | Who May Perform |
|---|---|---|
| `marketing_reports` | INSERT (new report) | Report generator service (Phase XB) only |
| `marketing_reports` | UPDATE `status` and `sections` | Review service (Phase XD) for section status transitions; seller/landlord approval service (Phase XE) for report-level status transitions; publication service (Phase XF) for publication status |
| `marketing_report_versions` | INSERT (new version record) | Report generator service (Phase XB) for initial version; agent review service (Phase XD) for revision versions |
| `marketing_report_versions` | UPDATE or DELETE | **Prohibited for all roles** |
| `marketing_report_audits` | INSERT (new audit record) | System-generated audit path only: report generator service, gate evaluation service, review service. No other path. |
| `marketing_report_audits` | UPDATE or DELETE | **Prohibited for all roles — no exceptions** |

### 9.3 Roles That May Never Write to `marketing_report_audits`

The following roles may never INSERT, UPDATE, or DELETE rows in `marketing_report_audits` except through the designated system-generated audit insertion path:

- Any human user acting through the admin UI
- Any human user acting through the agent review UI
- Any human user acting through the seller/landlord approval UI
- Any artisan command or queue worker not part of the designated audit path
- Any external API client or webhook handler

Audit records may only be created by the internal services designated in Section 9.2. Human actions (agent approvals, seller/landlord sign-offs) create audit records indirectly — by triggering the designated service, which inserts the record — never directly.

### 9.4 Queryability Requirements

Authorized platform administrators must be able to query audit records without direct database access, using an admin interface (Phase XC) that supports:

- Query by `listing_id`
- Query by `actor_id` (user identifier)
- Query by date range on `event_at`
- Query by `event_type`
- Combined filters (e.g., all `review` events for a specific listing in a date range)

### 9.5 Prohibition on Direct Audit Table Mutations

No application controller, Livewire component, Blade view, JavaScript handler, or API endpoint may construct, execute, or trigger an UPDATE or DELETE against `marketing_report_audits`. This prohibition applies to every layer of the application stack. The database trigger described in Section 7.2 is the fallback enforcement mechanism; the primary enforcement is application-layer code review and CI checks.

---

## 10. Future Implementation Sequence

The following phases consume the schema defined in this document. Each phase requires its own approved governance document before implementation begins. No phase may begin without that governance document. No phase may relax any constraint defined in Phases V, W, or X without a formal amendment reviewed by a qualified compliance authority.

1. **Phase XB — Report Generator Service:** Creates all three tables (`marketing_reports`, `marketing_report_versions`, `marketing_report_audits`) via database migrations. Implements the report generator service that persists the initial report object and initial version records for all five sections. Creates the `generation` audit record and the `attribution_failure` audit record as applicable. Implements the database trigger enforcing append-only on `marketing_report_audits`. Responsible for: all INSERT operations into `marketing_reports` and `marketing_report_versions` (initial records only), and the `generation` and `attribution_failure` INSERT operations into `marketing_report_audits`.

2. **Phase XC — Internal Admin Report Review:** Read-only. Responsible for: SELECT queries against all three tables; no INSERT, UPDATE, or DELETE. Provides filtered admin views by listing ID, user ID, date range, and event type. Provides CSV and JSON export of audit records for Fair Housing compliance evidence.

3. **Phase XD — Agent Report Review:** Responsible for: reading `marketing_reports` and `marketing_report_versions` for the agent's listings; INSERT of revision version records into `marketing_report_versions` when an agent submits edits; UPDATE of `marketing_reports.status` and `marketing_reports.sections` on section status transitions (`pending_review` → `approved`, `revised`, or `rejected`); INSERT of `review` audit records into `marketing_report_audits` for each affirmative agent action.

4. **Phase XE — Seller / Landlord Approval:** Responsible for: reading the agent-approved sections of the relevant `marketing_reports` record; UPDATE of `marketing_reports.status` to `seller_approved` or `rejected` on the seller/landlord's affirmative action; INSERT of `review` audit records into `marketing_report_audits` for the seller/landlord approval or rejection action.

5. **Phase XF — Publication Controls:** Responsible for: reading `marketing_reports` records where both agent approval and seller/landlord approval have been satisfied; UPDATE of `marketing_reports.status` to `published` after the qualified human reviewer Fair Housing compliance gate is cleared; INSERT of publication event audit records into `marketing_report_audits` for the publication action and Fair Housing sign-off. No autonomous publication path — all publication events are human-initiated.

No phase from this list may begin before its governance document is approved. No phase may write to `marketing_report_audits` via any path other than the designated system-generated audit insertion service. No phase may perform UPDATE or DELETE on `marketing_report_audits` under any circumstance.

---

## Verification Report

The following checklist confirms the completeness of this Phase XH schema plan document and confirms that no implementation work was performed.

### Document Completeness

1. [x] Document created: `docs/PROPERTY_DNA_PHASE_XH_AI_MARKETING_REPORT_PERSISTENCE_SCHEMA_PLAN.md`
2. [x] Section 1 — Purpose: Present. Explains why the schema plan exists, what it governs, and how it relates to Phases W and X.
3. [x] Proposed tables documented: Section 3 names all three tables (`marketing_reports`, `marketing_report_versions`, `marketing_report_audits`) with a brief statement of each table's role.
4. [x] Audit rules documented: Section 7 (Append-Only Audit Rules) states all rules: no UPDATE or DELETE, enforcement mechanisms (database trigger and application-layer constraint), error correction via new record, valid correction record criteria, and prohibited roles.
5. [x] Versioning rules documented: Section 5.3 states all versioning rules: version 1 is always the AI-generated draft, agent revisions increment the counter, version records are immutable, highest version number is current, original AI text is always retained.
6. [x] Retention rules documented: Section 8 states minimum five-year retention per HUD Fair Housing guidance, identifies all three tables carrying retention obligations, defines the retention clock (from `created_at` of each row), and specifies CSV and JSON export format requirements for compliance evidence.
7. [x] Access controls documented: Section 9 specifies who may read each table, who may write to each table, which roles may never write to `marketing_report_audits`, queryability requirements (by listing ID, user ID, date range, event type), and the prohibition on any application role performing UPDATE or DELETE on the audit table.
8. [x] No code modified: No PHP file, configuration file, or any other code file was created or modified.
9. [x] No migrations created: No database migration files were created. No schema changes were made to the live database.
10. [x] No UI, routes, or schema changes made: No routes, controllers, Blade views, Livewire components, JavaScript files, seeders, or any other implementation artifact was created or modified.

### Scope Boundary Confirmation

- [x] No PHP code files were created or modified
- [x] No routes were added or changed
- [x] No controllers were created or modified
- [x] No Blade views or UI elements were created or modified
- [x] No Livewire components were created or modified
- [x] No JavaScript was written
- [x] No database migrations were created
- [x] No schema changes were made
- [x] No AI system, language model, or ML inference was implemented
- [x] No OpenAI or LLM integration was introduced
- [x] No prompt was created or executed
- [x] No listing description was generated
- [x] No ad copy, social media content, or marketing output was generated
- [x] No audience targeting or advertising recommendations were implemented

### Source Traceability

- [x] All `marketing_reports` fields are sourced from Phase X Section 9.1 and Phase W Section 9.1 / 9.7
- [x] All `marketing_report_versions` fields are sourced from Phase X Section 9.2 and Phase X Section 6.2 (revision tracking)
- [x] All `marketing_report_audits` fields and `event_data` payload shapes are sourced from Phase X Section 9.3, Phase X Section 7.1–7.4, and Phase W Section 9.1–9.4
- [x] Append-only rules are sourced from Phase X Section 9.3 integrity constraints, Phase X Section 7.5, and Phase W Section 9.6
- [x] Retention requirements are sourced from Phase X Section 7.5 and Phase W Section 9.5
- [x] Access controls are sourced from Phase X Section 10 (future phase sequence) and Phase W Section 10 (future implementation requirements)
- [x] Future implementation sequence is sourced from Phase X Section 10 (Phases XA–XF)

---

**Document confirmed complete. No code was written. No migrations were created. No routes, UI, or schema changes were made. This document is a schema planning and documentation specification only.**
