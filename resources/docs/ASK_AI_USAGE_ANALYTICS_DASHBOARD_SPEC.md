# Ask AI — Usage Analytics Dashboard Specification

**Document ID:** ASK_AI_USAGE_ANALYTICS_DASHBOARD_SPEC_V1  
**Version:** 1.0  
**Status:** Approved — Planning Reference  
**Effective Date:** 2026-06-03  

---

## Table of Contents

1. [Purpose](#1-purpose)
2. [Data Source](#2-data-source)
3. [Admin-Only Access](#3-admin-only-access)
4. [Dashboard Summary Cards](#4-dashboard-summary-cards)
5. [Filters](#5-filters)
6. [Charts](#6-charts)
7. [Tables](#7-tables)
8. [Privacy Rules](#8-privacy-rules)
9. [Future Cost Tracking Integration](#9-future-cost-tracking-integration)
10. [Future Export Rules](#10-future-export-rules)
11. [Implementation Plan](#11-implementation-plan)

---

## 1. Purpose

This document defines the specification for the admin-facing dashboard that surfaces Ask AI usage metadata collected in the `ask_ai_usage_logs` table. It is a **planning and reference document only** — no code, migrations, models, routes, controllers, Blade views, Livewire components, or service classes are authorized by this document.

The goal of this dashboard is to give the platform team operational visibility into how the Ask AI feature is being used: request volume, success and failure rates, question type distribution, listing-level activity, and response performance. All data displayed is metadata only — no question content, answer content, or personally identifying information is surfaced.

Cross-references:

- `ASK_AI_ROADMAP_AND_GUARDRAILS.md` — phase gating rules governing when this dashboard may be implemented.
- `ASK_AI_COST_TRACKING_SPEC.md` — cost field definitions (token counts, estimated cost) that will extend this dashboard in Phase 3.

---

## 2. Data Source

The sole data source for this dashboard is the `ask_ai_usage_logs` table, created by `database/migrations/2026_06_03_000001_create_ask_ai_usage_logs_table.php`.

### Column Reference

| Column | Type | Description |
|---|---|---|
| `id` | bigint (PK) | Unique record identifier. |
| `listing_type` | string (nullable) | The listing role discriminator: `seller`, `buyer`, `landlord`, or `tenant`. |
| `listing_id` | bigint (nullable) | The ID of the listing associated with the request. References one of four listing tables; `listing_type` is the table discriminator. |
| `user_id` | bigint (nullable) | The authenticated platform user who submitted the request. Null for unauthenticated (guest) requests. |
| `ip_address` | string (nullable) | Client IP address stored for rate limiting and abuse detection only. Never displayed in any UI table or chart. |
| `question_hash` | string (nullable) | An opaque, one-way hash of the question text. Used for deduplication only. Never displayed as a readable value. |
| `question_type` | string (nullable) | The classifier-assigned question category (e.g., `property_standout`, `educational`, `prohibited`). See `ASK_AI_QUESTION_CLASSIFICATION_SPEC.md`. |
| `status` | string (nullable) | Outcome status of the request (e.g., `success`, `blocked`, `error`). |
| `success` | boolean | Whether the request completed successfully. `true` indicates a successful AI response was returned. |
| `model` | string (nullable) | The AI model identifier used to process the request (e.g., `gpt-4o`, `gpt-4-turbo`). |
| `response_time_ms` | integer (nullable) | Round-trip response time in milliseconds. |
| `error_code` | string (nullable) | Machine-readable error or refusal code, when applicable. |
| `created_at` | timestamp | UTC timestamp of the request. |

### Listing Discriminator Pattern

Because Ask AI may be invoked from any of the four listing types, `listing_id` alone is not a unique foreign key. The `listing_type` column functions as a table discriminator, identifying which of the four listing tables (`seller_agent_auctions`, `buyer_agent_auctions`, `landlord_agent_auctions`, `tenant_agent_auctions`) the `listing_id` references. All dashboard queries that join or aggregate by listing must use both columns together.

### Privacy Identifiers

`question_hash` and `ip_address` are metadata identifiers stored for operational purposes only. Neither column may be displayed in any dashboard UI element, chart, or export. See [Section 8 — Privacy Rules](#8-privacy-rules) for the authoritative constraint list.

---

## 3. Admin-Only Access

The Ask AI Usage Analytics Dashboard is restricted to authenticated users with the platform admin role.

- No usage data — aggregate or individual record — is surfaced to non-admin users under any circumstance.
- The dashboard route must be registered inside the existing admin middleware group consistent with the platform's current admin route conventions (e.g., `auth` + `admin` guard or equivalent).
- Direct URL access by non-admin authenticated users must return a `403 Forbidden` response, not a redirect.
- No link to this dashboard may appear in any customer-facing navigation, template, email, or notification.
- Agent, seller, buyer, landlord, and tenant roles must have no access to any page or endpoint that returns usage log data, even in aggregate form.

---

## 4. Dashboard Summary Cards

The dashboard header displays seven summary metric cards. All seven cards reflect the currently active filter state (see [Section 5 — Filters](#5-filters)). When no filters are applied, each card reflects the full default date range (last 30 days).

| Card | Definition |
|---|---|
| **Total Requests** | Count of all rows in `ask_ai_usage_logs` matching the active filter state. |
| **Successful Requests** | Count of rows where `success = true`. |
| **Blocked Requests** | Count of rows where `status = 'blocked'`. |
| **Failed Requests** | Count of rows where `status = 'error'` or (`success = false` and `status != 'blocked'`). |
| **Average Response Time** | Average of `response_time_ms` across all rows matching the active filter state, displayed in milliseconds. |
| **Unique Listings** | Count of distinct `listing_id` values among rows matching the active filter state. Uses both `listing_type` and `listing_id` to count distinct listing entities. |
| **Unique Users / Guests** | Count of distinct non-null `user_id` values among rows matching the active filter state. Note: guest traffic is tracked by `ip_address` (not by `user_id`) and is not included in this count. The card label must make clear it reflects authenticated users only. |

---

## 5. Filters

The dashboard provides five filter controls. All filters are combinable — applying multiple filters narrows results using AND logic across all active filter values.

| Filter | Source Column | Behavior |
|---|---|---|
| **Date Range** | `created_at` | A start-date and end-date picker applied as `created_at BETWEEN start AND end`. Default view is the last 30 days with end date set to today (UTC). |
| **Listing Type** | `listing_type` | A dropdown or multi-select containing all distinct values present in the table: `seller`, `buyer`, `landlord`, `tenant`. Selecting one or more values filters to rows matching any selected value. |
| **Status** | `status` | A dropdown or multi-select populated with all distinct `status` values present in the table. No values are hardcoded — the control must reflect what is actually stored. |
| **Question Type** | `question_type` | A dropdown or multi-select populated with all distinct `question_type` values present in the table. No values are hardcoded. |
| **Model** | `model` | A dropdown or multi-select populated with all distinct `model` values present in the table. No values are hardcoded. |

**Default state:** Last 30 days date range; all other filters unset (no restriction applied).

Filter state must propagate to all summary cards, charts, and tables on the page simultaneously. Changing a filter must refresh all dashboard sections.

---

## 6. Charts

The dashboard displays five chart panels. All charts respect the active filter state. Chart library selection is deferred to the implementation task; no new third-party charting library may be introduced without review (see [Section 11 — Implementation Plan](#11-implementation-plan)).

### 6.1 Requests by Day

| Attribute | Value |
|---|---|
| Chart type | Bar chart or line chart |
| X-axis | Calendar date (one data point per day within the active date range) |
| Y-axis | Count of all requests on that calendar date |
| Filter scope | Respects active date range and all other active filters |

### 6.2 Status Breakdown

| Attribute | Value |
|---|---|
| Chart type | Pie chart or donut chart |
| Segments | `success`, `blocked`, `failed`, `other` |
| Segment definitions | Success: `success = true`. Blocked: `status = 'blocked'`. Failed: `status = 'error'` or (`success = false` and not blocked). Other: any remaining rows not matching the above. |
| Filter scope | Respects all active filters |

### 6.3 Question Type Breakdown

| Attribute | Value |
|---|---|
| Chart type | Bar chart |
| X-axis | Distinct `question_type` values present in filtered results |
| Y-axis | Count of requests per `question_type` |
| Filter scope | Respects all active filters |

### 6.4 Listing Type Breakdown

| Attribute | Value |
|---|---|
| Chart type | Bar chart |
| X-axis | Distinct `listing_type` values: `seller`, `buyer`, `landlord`, `tenant` |
| Y-axis | Count of requests per `listing_type` |
| Filter scope | Respects all active filters |

### 6.5 Average Response Time by Day

| Attribute | Value |
|---|---|
| Chart type | Line chart |
| X-axis | Calendar date (one data point per day within the active date range) |
| Y-axis | Average `response_time_ms` per calendar date |
| Row inclusion | Successful requests only (`success = true`) to avoid skewing the average with failed or blocked records that may not have meaningful response times |
| Filter scope | Respects active date range and all other active filters |

---

## 7. Tables

The dashboard displays four data tables below the chart panels. All tables respect the active filter state.

### 7.1 Top Listings by Ask AI Usage

Ranks listings by the number of Ask AI requests they have received within the active filter window.

| Column | Description |
|---|---|
| `listing_id` | The listing's ID value. |
| `listing_type` | The listing type discriminator (`seller`, `buyer`, `landlord`, `tenant`). |
| Request Count | Total number of Ask AI requests for this listing in the filtered window. |
| Success Rate | Percentage of requests where `success = true`, formatted as a percentage. |
| Avg Response Time | Average `response_time_ms` for successful requests on this listing, in milliseconds. |

Default sort: descending by Request Count. Respects active filters.

### 7.2 Top Question Types

Summarizes request volume and outcome breakdown by question type within the active filter window.

| Column | Description |
|---|---|
| `question_type` | The classifier-assigned question category. |
| Request Count | Total requests classified as this type in the filtered window. |
| Success Count | Count where `success = true`. |
| Blocked Count | Count where `status = 'blocked'`. |
| Failed Count | Count where `status = 'error'` or (`success = false` and not blocked). |

Default sort: descending by Request Count. Respects active filters.

### 7.3 Recent Failed Requests

Displays the most recently failed requests for debugging and operational monitoring. This table is not filtered by date range beyond the active filter state; the default view shows all failures within the active date range.

| Column | Description |
|---|---|
| `id` | The usage log record ID. |
| `created_at` | UTC timestamp of the request. |
| `listing_type` | The listing type associated with the request. |
| `question_type` | The classifier-assigned question category. |
| `model` | The AI model used (or attempted). |
| `error_code` | The machine-readable error or refusal code recorded at time of failure. |
| `response_time_ms` | Response time in milliseconds, if available. |

Default sort: descending by `created_at`. Limited to the last 50 rows within the filtered window.

### 7.4 Recent Blocked Requests

Displays the most recently blocked requests. Blocked requests are those where `status = 'blocked'`, indicating the platform's rate limiting or classifier refusal layer rejected the request before an AI response was generated.

| Column | Description |
|---|---|
| `id` | The usage log record ID. |
| `created_at` | UTC timestamp of the request. |
| `listing_type` | The listing type associated with the request. |
| `question_type` | The classifier-assigned question category (if classification was reached before the block). |
| `model` | The model identifier, if available. |

Default sort: descending by `created_at`. Limited to the last 50 rows within the filtered window.

---

## 8. Privacy Rules

This section is authoritative. It governs all dashboard implementations and may not be overridden or weakened by any implementation task.

### Prohibited Display Fields

The following fields and data categories must never appear in any dashboard UI element, table column, chart label, tooltip, export, or API response endpoint that serves dashboard data:

| Prohibited Data | Rationale |
|---|---|
| Full question text | Not stored in `ask_ai_usage_logs`. The original question is never persisted. Attempting to reconstruct it would be prohibited even if a source existed. |
| AI answer text | Not stored in `ask_ai_usage_logs`. The AI-generated response is never persisted in the usage log table. |
| `prompt_package`, context packages, or `raw_response` | Not stored in `ask_ai_usage_logs`. These values are not available for display at any phase. |
| `ip_address` | Stored for rate limiting and abuse detection only. Must not appear in any UI table, chart, tooltip, or export. See `ASK_AI_COST_TRACKING_SPEC.md` Section 10 for the full `ip_address` permitted-use constraint. |
| `question_hash` | An opaque one-way hash used for deduplication only. Must not be displayed as a readable value in any UI element. It carries no meaning to an admin user and its display would be misleading. |

### Permitted Display Fields

Only the following categories of data may be shown in any dashboard UI element:

- **Opaque identifiers**: `id`, `listing_id`, `user_id` (used as counts or aggregates only — never displayed as a raw list of user IDs that could be used to profile individual users)
- **Enumerated categorical values**: `listing_type`, `question_type`, `status`, `model`, `error_code`
- **Boolean metrics**: `success`
- **Numeric metrics**: `response_time_ms`, request counts, success rates, averages
- **Timestamps**: `created_at` (for sorting and time-series charts)

Cross-reference: `ASK_AI_COST_TRACKING_SPEC.md` Section 10 — Privacy Limits.

---

## 9. Future Cost Tracking Integration

Cost fields (`prompt_tokens`, `completion_tokens`, `estimated_cost_usd`) are defined in `ASK_AI_COST_TRACKING_SPEC.md` and are not present in the current `ask_ai_usage_logs` schema. These fields will be added to the table and to this dashboard when cost tracking is implemented in Phase 3.

This section reserves dashboard space for the following panels, to be designed and implemented as a Phase 4 (or later) extension of this dashboard after Phase 3 cost tracking is live:

- **Cost summary cards** — total estimated cost, average cost per request, cost by model — added to the [Section 4 — Dashboard Summary Cards](#4-dashboard-summary-cards) row.
- **Cost by model chart** — a bar chart showing estimated cost broken down by model identifier, joining `model` with the platform cost rate table.
- **Daily cost line chart** — a time-series line chart of `estimated_cost_usd` by calendar date, overlaid with total token volume.

No action is required in this section until Phase 3 cost tracking fields are live in the database and contain real data.

---

## 10. Future Export Rules

A CSV export feature is planned for a future phase. This section defines the intended behavior so that the implementation task does not need to revisit these decisions.

| Rule | Detail |
|---|---|
| **What is exported** | The filtered summary table contents only — not raw individual log rows. The export reflects the same aggregated view that the admin sees on screen. |
| **Filename format** | Date-stamped: `ask-ai-usage-export-YYYY-MM-DD.csv` using the export date in UTC. |
| **Permitted columns** | Metadata columns only: `listing_type`, `listing_id`, `question_type`, `model`, `status`, `success`, `response_time_ms`, `error_code`, `created_at`, and any computed aggregation columns (request count, success rate, etc.). |
| **Prohibited columns** | `ip_address`, `question_hash`, `user_id` (as a raw column), and any column not listed in [Section 8 — Privacy Rules](#8-privacy-rules) as a permitted display field. |
| **Access control** | Admin-only. The export endpoint must enforce the same admin middleware as the dashboard itself. |
| **Raw log export** | Export of individual raw `ask_ai_usage_logs` rows is not planned. If this requirement arises in a future phase, it must be reviewed and approved separately before implementation. |

---

## 11. Implementation Plan

This dashboard is planned for Phase 4 or later, after the backend OpenAI integration (Phase 3) is confirmed complete and the `ask_ai_usage_logs` table contains live production data. No implementation task for this dashboard is authorized by this document alone. A separate scoped task must be created and approved, referencing this spec.

See `ASK_AI_ROADMAP_AND_GUARDRAILS.md` for the phase gating rules that govern when Phase 4 work may begin.

### High-Level Implementation Steps

The following steps define the intended implementation sequence. They are listed for planning purposes and must be executed as part of a separately scoped Phase 4 task.

| Step | Description |
|---|---|
| (a) Admin route and controller | Register a new admin-only route pointing to a dedicated controller that reads from `ask_ai_usage_logs`. The route must be inside the existing admin middleware group. No new admin middleware or guard may be introduced without review. |
| (b) Blade view — summary cards and filters | Build the primary dashboard Blade view with the seven summary cards defined in [Section 4](#4-dashboard-summary-cards) and the five filter controls defined in [Section 5](#5-filters). Filter state must propagate to all dashboard sections. |
| (c) Chart rendering | Integrate the five chart panels defined in [Section 6](#6-charts) using platform-approved libraries. No new third-party charting library may be added to the production build without a separate review and approval. Chart data must be served via the same controller or a dedicated admin API endpoint — not exposed on any public route. |
| (d) Data tables | Build the four data tables defined in [Section 7](#7-tables) with correct default sort orders and row limits. |
| (e) Export endpoint | When export is implemented (separate task, future phase), build the admin-only CSV export endpoint following the rules in [Section 10](#10-future-export-rules). The export endpoint must share the same middleware stack as the dashboard route. |

---

| Field | Value |
|---|---|
| Document ID | ASK_AI_USAGE_ANALYTICS_DASHBOARD_SPEC_V1 |
| Version | 1.0 |
| Created | 2026-06-03 |
| Last Updated | 2026-06-03 |
| Owner | Platform Policy |
| Review Cycle | Annual, or upon material change to the Ask AI feature scope, `ask_ai_usage_logs` schema, or applicable privacy regulations |

---

*This document is a planning reference. No code, migration, schema change, route, controller, Blade view, Livewire component, or service class is authorized by this document alone. Implementation tasks must reference this spec and be scoped separately, following the phase gating rules defined in `ASK_AI_ROADMAP_AND_GUARDRAILS.md`.*
