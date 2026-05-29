# Property DNA Phase XA — OpenAI Integration Layer Specification

**Document Date:** 2026-05-29
**Phase:** XA — OpenAI Integration Layer Specification
**Preceding Phases:** P — Deterministic Marketing Context Builder, Q — Marketing Brief Readiness Plan, R — Deterministic Property Marketing Brief Builder, S — Internal Brief Inspector / Admin Preview, T — Agent-Reviewed Brief UI, U — AI Drafting Guardrails Plan, V — AI Marketing Intelligence Governance & Readiness Plan, W — AI Marketing Intelligence Report Contract, X — AI Marketing Intelligence Implementation Architecture
**Type:** Specification and planning document only — no code, no routes, no schema changes, no AI calls, no UI, no SDK installation, no environment variable changes

---

## 1. Purpose

Phase X completed the AI Marketing Intelligence Implementation Architecture — a comprehensive, nine-stage generation workflow, a full attribution verification protocol, a two-stage human review system, and a complete audit infrastructure design. Before a single line of OpenAI integration code is written, this Phase XA specification defines the exact technical layer that will mediate between the Bid Your Offer platform and the OpenAI API.

This specification governs the OpenAI integration as infrastructure and generation support only. It is not a specification for report generation logic, prompt content, agent review UI, or seller review UI — those are addressed in separate downstream phases. The integration layer defined here is a neutral, auditable, policy-enforcing conduit between the platform's deterministic service pipeline (Phases P, R, and U) and the OpenAI API.

**Explicit scope constraints:**

- OpenAI is not an autonomous decision maker in this platform. It is a text-generation tool called with structured, policy-approved inputs and expected to produce structured, policy-conforming outputs.
- OpenAI is not a broker. No output it produces constitutes a professional real estate opinion, a market valuation, a comparative market analysis, or any representation that a licensed broker would make.
- OpenAI is not a legal advisor. No output it produces constitutes legal counsel, legal interpretation, disclosure guidance, or any form of legal advice.
- The integration layer defined in this specification does not change any of the governance constraints, Fair Housing safeguards, readiness gate requirements, hallucination prevention rules, or human review requirements established in Phases V, W, and X. All constraints from those phases remain fully in effect.

This is a specification and planning document only. No PHP files are modified, no routes are added, no UI is created, no OpenAI SDK is installed, no API calls are implemented, and no environment variables are added in Phase XA. Implementation begins in a separately approved downstream phase and must not proceed without satisfying every constraint documented here and in Phases V, W, and X.

---

## 2. Approved AI Provider

### 2.1 Approved Provider

OpenAI is the approved AI provider for all AI-assisted generation described in Phases V through X and this specification. No other AI provider, API endpoint, self-hosted model, or proxied model service is approved for use in the generation workflow described in Phase X Section 1 without a separately approved governance document with its own legal, compliance, and technical review.

### 2.2 Approved Model Families

The approved model family for all generation calls is the **GPT-5.x family** (e.g., `gpt-5`, `gpt-5-turbo`, or any formally released variant of the GPT-5.x series). No model outside the GPT-5.x family may be used in production without a formal specification amendment. This constraint applies to:

- All synchronous generation calls
- Any background or queued generation jobs
- Any retry calls made after a failed generation attempt
- Any fallback calls made when a primary model is unavailable

### 2.3 Model Version Pinning Requirement

The exact model version string used in each generation call (e.g., `gpt-5-2025-11-01`) must be:

- Specified explicitly in the API request — wildcard or alias identifiers that resolve to an unspecified version are prohibited
- Stored as a required field in every generation audit record (see Section 8)
- Included in the `generation_metadata.ai_model` field of the report object (see Phase W Section 3.1)
- Logged in the application log entry created for the generation event

### 2.4 Model Version Change Policy

Any change to the approved model version — including upgrades to a new point release within the GPT-5.x family — requires:

1. A documented review confirming that the new version's output behavior is consistent with the Phase W report contract and the Phase V prohibited output categories
2. An update to this specification identifying the new approved version string
3. A corresponding bump to the prompt template version (see Section 4) to create a clean audit boundary between generations made with different model versions

Production deployments must never silently receive a model version change as a result of an unversioned API alias resolving to a new model.

---

## 3. API Key Management

### 3.1 Storage Requirement

The OpenAI API key must be stored exclusively as an environment variable in the Replit environment's secret management system. No other storage location is permitted.

The following storage locations are unconditionally prohibited:

| Prohibited Location | Prohibition Basis |
|---|---|
| Hardcoded in any PHP, Blade, or JavaScript file | Permanent exposure in source control; violation of least-privilege |
| In any `.env` file committed to source control | `.env` files must be excluded from version control; any committed key is compromised |
| In any database table, row, or column | Database storage is queryable and logged; API keys must not be queryable |
| In any log file, application log, or audit record | Log files are frequently shared for debugging; API keys must never appear |
| In any config file committed to source control | Same risk as hardcoded; all config files in the repository are version-controlled |
| In any cache, session variable, or request attribute | Cache and session stores may be shared, exported, or logged |

### 3.2 Environment Variable Name

The API key must be accessed via a single, consistently named environment variable. The integration layer must read this variable at call time — not cache it in a class property, static variable, or application-boot-time singleton — so that key rotation takes effect without requiring an application restart.

### 3.3 Key Rotation Policy

API keys must be rotated on a defined policy schedule and immediately upon any of the following events:

- Suspected or confirmed exposure of the key in any log, file, or communication
- Departure of any team member with access to the environment secrets
- Any security incident affecting the environment where the key is stored
- Any accidental logging of the key by any application component

After rotation, the previous key must be revoked at the OpenAI account level, not merely replaced in the environment variable. Rotation must be confirmed by verifying that the previous key returns an authentication error.

### 3.4 Prohibition on Logging

The API key must never appear in:

- Any application log entry, regardless of log level
- Any error message surfaced to users or agents
- Any audit record created by the integration layer
- Any HTTP response body, header, or redirect

The integration layer must not log the full HTTP request payload when that payload includes an Authorization header carrying the key.

---

## 4. Prompt Management

### 4.1 Version-Controlled Prompts

Every prompt template used in an AI generation call must be stored as a version-controlled artifact in the platform's source repository. Prompts assembled dynamically at runtime from unversioned fragments are prohibited. The complete, assembled prompt that will be sent to the OpenAI API must be reconstructable from the stored prompt template and the structured Phase R and Phase U inputs — no additional runtime assembly logic may alter the prompt's structure, safety instructions, or prohibited-output rules.

### 4.2 Prompt Identifier Convention

Each prompt template must be identified by a string conforming to the following convention:

```
property-dna-report-v{MAJOR}.{MINOR}
```

Examples: `property-dna-report-v1.0`, `property-dna-report-v1.1`, `property-dna-report-v2.0`

- **MAJOR** version increments when the prompt's structural contract, output shape, or safety prohibitions change in any way that could affect how generated output is interpreted or validated
- **MINOR** version increments for wording refinements, instruction clarifications, or phrasing adjustments that do not alter the output contract or safety prohibitions

The identifier must be a plain string that is readable, sortable, and unambiguous without reference to a separate registry.

### 4.3 Prompt Version Tracking Requirements

The prompt template version must be:

- Stored as a required field in every generation audit record (see Section 8)
- Included in the `generation_metadata.prompt_template_version` field of the report object (see Phase W Section 3.1)
- Included in the application log entry created for the generation event
- Immutable once a generation record has been created — no retroactive prompt version reassignment is permitted

When a prompt template version changes, previously generated reports retain the prompt version that was in effect at their generation time. The new version applies only to generation calls made after the new template is deployed.

### 4.4 Prompt Content Exclusion

This specification does not define, include, or approve any prompt content. Prompt content — including system instructions, user messages, prohibited-output language, and structural framing — is addressed in a separately approved downstream phase. This section establishes only the versioning, storage, and traceability requirements that any prompt content must satisfy.

---

## 5. Request Construction Rules

### 5.1 Approved Inputs

The AI generation request may include only the following structured inputs, sourced exclusively from the deterministic Phase P, R, and U service outputs. No other data source, database field, HTTP request parameter, session value, user-provided string, or external API response may be included in the request payload sent to the OpenAI API.

| Approved Input | Source Service | Permitted Use in Request |
|---|---|---|
| `property_attribute_context` | Phase R (`PropertyMarketingBriefService`) | Primary content input for `property_feature_narrative` section |
| `transaction_context` | Phase R | Primary content input for `transaction_terms_summary` section |
| `quantitative_context` | Phase R | Primary content input for `property_feature_narrative` section |
| `marketing_asset_checklist` | Phase R | Primary content input for `marketing_asset_statement` section |
| `missing_information_checklist` | Phase R | Primary content input for `missing_information_note` section |
| `listing_preparation_notes` | Phase R | Primary content input for `listing_preparation_summary` section |
| `neutral_feature_summary` | Phase R | Attribution verification reference only |
| `seller_landlord_questions` | Phase R | Context only — not presented as questions for the AI to answer autonomously |
| `summary` | Phase R | Metadata only — integer counts |
| `is_marketing_ready` | Phase U (`PropertyMarketingReadinessService`) | Gate confirmation context only |
| `present_groups` | Phase U | Context — informs the AI which data groups are available |
| `missing_groups` | Phase U | Context — informs the AI which data is absent |
| `review_items` | Phase U | Supplemental context |
| `attribute_context` | Phase P (`PropertyMarketingContextService`) | Source attribution resolution only |
| `transaction_context` (tag-level) | Phase P | Source attribution resolution only |
| `quantitative_context` (tag-level) | Phase P | Source attribution resolution only |

### 5.2 Prohibited Inputs

The following inputs are categorically prohibited from inclusion in any AI generation request, regardless of availability, feature flag, configuration setting, or code path:

| Prohibited Input | Prohibition Basis |
|---|---|
| Neighborhood demographic data (census, ACS, or similar) | Fair Housing Act §804(c); HUD steering guidelines |
| School demographic or rating data | Fair Housing Act; HUD established case law on school-based steering |
| Buyer or tenant identity, credit history, or financial records | FCRA; ECOA; CFPB guidance |
| Protected-class signals of any kind (race, color, national origin, religion, sex, familial status, disability) | Fair Housing Act; 42 U.S.C. §3604 |
| Income tier, wealth tier, or socioeconomic classification | Fair Housing Act disparate impact doctrine; ECOA |
| Prior transaction history or buyer/tenant behavioral profiles | Fair Housing; CFPB guidance on algorithmic decision-making |
| External real estate market data not present in the Phase R brief | Out of scope; introduces unaudited inference |
| Any user PII not required for generation (names, contact details, identifiers beyond listing_id and profile_id) | Data minimization; see Section 9 |
| Any data not produced by an approved Phase P, R, or U service output | Scope boundary — the AI may only see what the deterministic pipeline has already produced |

### 5.3 Fresh Service Output Requirement

The Phase R and Phase U service outputs included in the request must be freshly computed from the live `PropertyDnaProfile` record at the time the request is constructed. Cached, session-persisted, or previously stored service outputs must not be used as substitutes. This requirement is consistent with and required by the readiness gate fresh-evaluation rule established in Phase X Section 2.3.

### 5.4 Request Payload Validation Before Dispatch

Before dispatching the request to the OpenAI API, the integration layer must verify that:

1. No prohibited input type (Section 5.2) is present anywhere in the payload
2. All approved inputs (Section 5.1) that are required for the current generation call are present and non-null
3. The readiness gate has passed (Section 8 of Phase X) — the integration layer must not dispatch any request when `is_marketing_ready` is `false`
4. The prompt template version identifier is set and non-empty

If any verification step fails, the request must be aborted and a generation failure audit record must be created. No partial request may be dispatched.

---

## 6. Response Validation Rules

### 6.1 JSON Requirement

Every response received from the OpenAI API must be parsed as valid JSON before any other validation step. A response that is not valid JSON must be treated as a generation failure immediately. No partial parsing, string extraction, or regex-based content extraction may be used as a fallback for an invalid JSON response.

### 6.2 Contract Validation

After successful JSON parsing, the response must be validated against the full Phase W Section 3.1 report contract. The following validations are required in the order listed:

1. All seven required top-level keys must be present and non-null: `report_id`, `generated_at`, `listing_context`, `readiness_snapshot`, `sections`, `generation_metadata`, `attribution_verified`
2. `listing_context` must contain both `listing_id` and `profile_id`, and their values must match the identifiers of the listing and profile for which the generation was requested
3. `sections` must contain all five required section keys: `property_feature_narrative`, `transaction_terms_summary`, `marketing_asset_statement`, `missing_information_note`, `listing_preparation_summary`
4. Each section must contain `draft_text` (string), `status` (string), and `source_attribution` (array)
5. `draft_text` must be a string in every section — null, absent, and non-string values are not permitted
6. `status` must be one of the values defined in Phase W Section 3.3: `pending_review`, `approved`, `revised`, `rejected`, or `internal_note`; at generation time, all sections must carry `pending_review` except `missing_information_note`, which must carry `internal_note`
7. `source_attribution` must be an array in every section — null, absent, and non-array values are not permitted
8. `generation_metadata` must contain `ai_model`, `prompt_template_version`, `phase_r_brief_version`, and `phase_u_readiness_version`, all as non-null strings
9. `attribution_verified` must be a boolean

### 6.3 Required Field Enforcement

A response that is missing any required field listed in Section 6.2 must be treated as a generation failure. The integration layer must not attempt to populate missing fields with defaults, synthesized values, or carry-forward data from the request. A non-conforming response is a failed generation — not a partial success.

### 6.4 Invalid Response Handling

When a response fails any validation in Section 6.2:

- The generation is recorded as failed in the audit log with the specific validation failure reason
- No report object is created or stored
- No content from the non-conforming response is surfaced to any user
- The failure is eligible for retry per the retry policy in Section 7
- If all retry attempts are exhausted, the final failure is logged and the requesting agent is informed that generation failed

---

## 7. Retry and Failure Policy

### 7.1 Retry-Eligible Failure Types

The following failure types are eligible for automatic retry:

| Failure Type | Description |
|---|---|
| HTTP 429 — Rate Limit | OpenAI API rate limit reached; the request may succeed after a backoff interval |
| HTTP 500 / 502 / 503 / 504 — Server Error | Transient OpenAI infrastructure error; the request may succeed on retry |
| HTTP 408 — Request Timeout | The API did not respond within the configured timeout; the request may succeed on retry |
| Network-level timeout | The connection to the OpenAI API timed out before a response was received |
| Invalid JSON response | The response was not valid JSON; may be a transient serialization issue |

### 7.2 Non-Retry-Eligible Failure Types

The following failure types must not be retried automatically:

| Failure Type | Description | Required Action |
|---|---|---|
| HTTP 401 — Unauthorized | API key is invalid or missing | Log as configuration error; alert platform operator; do not retry |
| HTTP 403 — Forbidden | Access denied for the requested resource or model | Log as configuration error; alert platform operator; do not retry |
| HTTP 400 — Bad Request | The request payload was malformed or violated API policy | Log full failure details; do not retry with the same payload |
| Readiness gate failure | `is_marketing_ready` was `false` at request time | Should have been caught before request construction; log as implementation error |
| Prohibited input detected | Request payload contained a prohibited input type | Log as security incident; do not retry; escalate for investigation |

### 7.3 Timeout Handling

The HTTP client used to call the OpenAI API must enforce a configurable request timeout. The timeout must be:

- Enforced at the HTTP client level — not only as a soft limit in application logic
- Logged when exceeded, including the elapsed time and the generation audit record identifier
- Treated as a retry-eligible failure per Section 7.1

The default timeout must be set conservatively enough to allow for complete response generation for a five-section report while avoiding indefinite hangs. The specific timeout value is a configuration concern for the downstream implementation phase.

### 7.4 Rate Limit Behavior

When an HTTP 429 response is received:

- The integration layer must read the `Retry-After` header from the response, if present, and wait at least that duration before retrying
- If no `Retry-After` header is present, exponential backoff with jitter must be applied
- The rate limit event must be logged, including the retry count and the backoff duration applied
- Rate limit events must be included in the generation audit record

### 7.5 Maximum Retry Limit

The maximum number of automatic retry attempts for any single generation call is **three (3)**. After three failed attempts:

- No further retry is attempted for that generation call
- The final failure reason is logged in full
- The generation audit record is marked as permanently failed
- The requesting listing agent is informed that generation failed and invited to try again manually at a later time
- No partial or best-effort report is generated from any subset of the attempted responses

### 7.6 Failure Logging Requirements

Every generation failure — whether on the initial attempt or a retry — must be logged with the following minimum fields:

- Generation audit record identifier (or a pending identifier if the record had not yet been created)
- Failure type (from Sections 7.1 and 7.2)
- HTTP status code (if applicable)
- Attempt number (1 through maximum retry limit)
- Timestamp of the failure
- Elapsed time from request dispatch to failure
- A sanitized error message — the log entry must not include the API key, any portion of the prompt, or any user PII

---

## 8. Audit Requirements

### 8.1 Required Audit Record Fields

For every AI generation call — whether it succeeds or fails — the integration layer must create a generation audit record. The record must be created and persisted before the report object is surfaced to any user. If the audit record cannot be persisted, the generation must be treated as failed.

The following fields are required in every generation audit record:

| Field | Type | Description |
|---|---|---|
| `generation_id` | string | Unique identifier for this generation event; must match `report_id` in the report object when generation succeeds |
| `listing_id` | string / integer | Identifier of the property listing for which generation was requested |
| `profile_id` | string / integer | Identifier of the `PropertyDnaProfile` used as input |
| `model_version` | string | The exact OpenAI model version string used in the API call (e.g., `gpt-5-2025-11-01`) |
| `prompt_version` | string | The version identifier of the prompt template used (e.g., `property-dna-report-v1.0`) |
| `requested_at` | datetime (UTC) | Timestamp at which the generation call was initiated |
| `completed_at` | datetime (UTC) or null | Timestamp at which the generation call completed (success or final failure); null if the record is created before the call completes |
| `attempt_count` | integer | Total number of attempts made, including the initial attempt and any retries |
| `outcome` | string | One of: `success`, `validation_failure`, `rate_limit_exhausted`, `timeout_exhausted`, `auth_error`, `bad_request`, `attribution_failure`, `gate_failure` |
| `readiness_result` | boolean | The value of `is_marketing_ready` from the Phase U output at the time of the call |
| `attribution_verified` | boolean or null | The `attribution_verified` value from the report object; null when generation failed before a report object was produced |
| `report_id` | string or null | The `report_id` from the generated report object; null when generation failed |

### 8.2 Prohibition on Logging API Secrets

The audit record must not contain, in any field or serialized payload:

- The OpenAI API key or any portion of it
- Any Authorization header value
- Any session token, CSRF token, or authentication credential of any kind

This prohibition extends to all application log entries, error log entries, and debug log entries associated with the generation call.

### 8.3 Phase R and Phase U Snapshot Storage

Consistent with Phase X Section 7.1, the generation audit record must also store:

- A serialized snapshot of the Phase R brief input provided to the AI (the `phase_r_brief_snapshot` field defined in Phase X)
- A serialized snapshot of the Phase U readiness output at the time of the call (the `phase_u_readiness_snapshot` field defined in Phase X)

These snapshots are required for compliance, auditability, and Fair Housing investigation purposes. They must be stored in their complete form — not summarized or truncated. The schema design for these snapshot fields is a concern for the downstream implementation phase.

### 8.4 Audit Record Immutability

Once created, a generation audit record must not be modified. Status fields (e.g., `outcome`, `attribution_verified`) must be set at the time the record is finalized, not updated retroactively. If a generation attempt produces a multi-step outcome (e.g., initial failure followed by successful retry), the final state of all fields must reflect the outcome of the complete generation event, and a separate retry log entry must record each intermediate attempt.

---

## 9. Security and Privacy

### 9.1 Data Minimization

The AI generation request must contain only the data fields explicitly listed in Section 5.1 as approved inputs. No additional property data, listing metadata, agent profile information, or platform configuration data may be included in the request payload, even if such data is available in the application context at the time the request is constructed.

The principle of data minimization applies: if a data field is not required for the AI to produce the five report sections defined in Phase W, it must not be sent.

### 9.2 Least Privilege

The OpenAI API key used by the integration layer must be scoped to the minimum permissions required to make generation calls. Organization-level administrative permissions, billing access, and fine-tuning access must not be granted to the key used by the generation integration layer. Where the OpenAI API supports key-level permission scoping, the most restrictive scope compatible with generation calls must be applied.

### 9.3 No Assumption of Training Data Reuse

The platform does not assume, and the integration layer must not be designed to assume, that data sent to the OpenAI API will not be used for model training. The integration layer must comply with whatever data processing agreement and API usage policy is in effect between the platform and OpenAI at the time of implementation. If the applicable OpenAI data usage policy does not provide an explicit opt-out from training data use for API calls, the platform operator must obtain a data processing agreement that does before deploying this integration in production.

This constraint does not modify the prohibited input rules in Section 5.2 — those prohibitions apply unconditionally, regardless of any data processing agreement in place.

### 9.4 No Unnecessary User Information

User PII — including agent names, agent license numbers, seller or landlord names, addresses beyond what is present in the Phase R brief as a property attribute, email addresses, phone numbers, and any other personally identifying data — must not be included in the AI generation request unless that specific field is explicitly listed in Section 5.1 as an approved input.

The `listing_id` and `profile_id` identifiers may be included for request traceability purposes. No other user or agent identifier may be included.

### 9.5 Transport Security

All API calls to the OpenAI API must use HTTPS with TLS 1.2 or higher. Plaintext HTTP connections to the OpenAI API are prohibited. Certificate validation must not be disabled in any environment, including local development.

### 9.6 No Client-Side API Calls

The OpenAI API key must never be exposed to a browser or mobile client. All API calls to the OpenAI API must originate from the server-side PHP application layer. No JavaScript, Livewire client-side action, or AJAX call may directly contact the OpenAI API. The integration layer defined in this specification operates exclusively on the server side.

---

## 10. Future XA Implementation Scope

### 10.1 What Phase XA Implementation Will Deliver

The downstream implementation phase that acts on this specification (referred to as Phase XA Implementation) will deliver the following components only:

| Component | Description |
|---|---|
| OpenAI client wrapper | A PHP class or service that encapsulates the OpenAI HTTP client, applies the API key from the environment variable, enforces the configured model version, and handles the HTTP request lifecycle |
| Configuration management | A configuration layer that provides the active model version string, the prompt template version identifier, and the request timeout value to the client wrapper without hardcoding any of these values |
| Request/response validation | The input validation logic (Section 5.4) and the response contract validation logic (Section 6.2) described in this specification |
| Retry logic | The retry and backoff mechanism described in Section 7, including timeout handling, rate limit handling, and maximum retry enforcement |
| Audit hooks | The generation audit record creation logic described in Section 8, including all required fields and the prohibition on logging secrets |

### 10.2 What Phase XA Implementation Will Not Deliver

The following are explicitly out of scope for the Phase XA implementation phase and must not be introduced as part of it:

| Out-of-Scope Item | Responsible Phase |
|---|---|
| Prompt content or prompt templates | A separately approved prompt authoring phase |
| Report generation orchestration (the nine-stage workflow from Phase X) | Phase XB or equivalent |
| Agent review UI for AI-generated report sections | Phase XD or equivalent |
| Seller / landlord review UI for AI-generated content | Phase XE or equivalent |
| Marketing report publication or distribution | Phase XF or equivalent |
| Marketing report creation or storage schema | A separately approved schema phase |
| Any route, controller action, or HTTP endpoint | Out of scope until the generation orchestration phase |
| Any Livewire component, Blade view, or frontend asset | Out of scope until the agent review UI phase |
| Any database migration, table creation, or schema change | Out of scope until the schema phase |

### 10.3 Integration Boundary

The OpenAI client wrapper produced by the Phase XA implementation must be a pure infrastructure component — it accepts a structured request payload and returns a parsed, validated response object (or throws a well-typed exception on failure). It must have no knowledge of Phase R brief structures, Phase U readiness logic, report object schemas, agent review workflows, or any other domain concept. Domain knowledge lives in the generation orchestration layer (Phase XB) and above. The client wrapper is a generic, policy-enforcing HTTP adapter.

---

## Verification Report

This section confirms that Phase XA satisfies all ten required deliverable checklist items.

| # | Checklist Item | Status | Notes |
|---|---|---|---|
| 1 | Document created at `docs/PROPERTY_DNA_PHASE_XA_OPENAI_INTEGRATION_SPECIFICATION.md` | Confirmed | This document |
| 2 | Section 1 — Purpose present | Confirmed | Defines integration as infrastructure-only, generation-support-only, and advisory-only; explicitly states OpenAI is not an autonomous decision maker, not a broker, and not a legal advisor |
| 3 | Section 2 — Approved AI Provider present | Confirmed | Documents OpenAI as approved provider, GPT-5.x family as approved model family, and the model version pinning and storage requirements |
| 4 | Section 3 — API Key Management present | Confirmed | Documents environment-variable-only storage, prohibited storage locations, key rotation policy, and prohibition on logging |
| 5 | Section 4 — Prompt Management present | Confirmed | Documents version-controlled prompts, prompt identifier convention (`property-dna-report-v{MAJOR}.{MINOR}`), and prompt version tracking requirements; no prompt content included |
| 6 | Section 5 — Request Construction Rules present | Confirmed | Documents approved inputs (Phase P, R, and U outputs) and all prohibited inputs (demographic data, protected-class data, external market data, user PII not required for generation) |
| 7 | Section 6 — Response Validation Rules present | Confirmed | Documents JSON requirement, full contract validation against Phase W Section 3.1, required field enforcement, and invalid response handling |
| 8 | Section 7 — Retry and Failure Policy present | Confirmed | Documents timeout handling, rate-limit behavior with `Retry-After` header and exponential backoff, maximum retry limit of three attempts, and failure logging requirements |
| 9 | Section 8 — Audit Requirements present | Confirmed | Documents all required log fields (model version, prompt version, timestamp via `requested_at`/`completed_at`, `generation_id` as request ID, `readiness_result`); explicitly prohibits logging API secrets |
| 10 | Section 9 — Security and Privacy present | Confirmed | Documents data minimization, least-privilege principles, no assumption of training data reuse by the provider, and prohibition on sending unnecessary user information |
| 11 | Section 10 — Future XA Implementation Scope present | Confirmed | Documents what XA will implement (OpenAI client wrapper, configuration management, request/response validation, retry logic, audit hooks) and what it will not implement (report generation, agent review UI, seller review UI, marketing report creation, routes, schema changes) |
| 12 | No code files modified | Confirmed | This is a documentation-only phase |
| 13 | No OpenAI SDK installed | Confirmed | No package installation of any kind |
| 14 | No API calls implemented | Confirmed | No PHP, JavaScript, or shell code introduced |
| 15 | No environment variables added | Confirmed | Section 3 describes the requirement; no variable has been set |
| 16 | No routes added | Confirmed | No route files modified |
| 17 | No UI created | Confirmed | No Blade, Livewire, or frontend files introduced |
| 18 | Security requirements documented | Confirmed | Section 9 covers data minimization, least privilege, no training data assumption, no unnecessary PII, transport security, and server-side-only API calls |
| 19 | Audit requirements documented | Confirmed | Section 8 covers all required log fields and the prohibition on logging secrets |
| 20 | XA implementation scope documented | Confirmed | Section 10 lists both in-scope deliverables and explicit out-of-scope items with responsible downstream phases |
