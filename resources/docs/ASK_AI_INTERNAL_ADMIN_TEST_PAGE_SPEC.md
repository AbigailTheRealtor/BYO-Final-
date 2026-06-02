# Ask AI — Internal Admin Test Page Specification

**Status:** Pre-implementation specification (no UI built yet)
**Audience:** Internal engineering and product team

---

## 1. Purpose

The Ask AI Internal Admin Test Page is a diagnostic tool for verifying the Ask AI pipeline end-to-end in a controlled, admin-only environment before any public or customer-facing release.

Its role is to let authorized team members submit a question against a specific listing and observe every stage of the pipeline — from initial classification through final response assembly — without touching production user flows. This gives the team confidence that each pipeline stage is behaving correctly, surfaces misconfiguration or prompt issues early, and documents the full data flow during QA.

The page is strictly a testing and inspection surface. It does not replace any production UI and must never be exposed publicly.

---

## 2. Admin-Only Access Requirement

- The page is restricted to **authenticated admin users only**.
- No public route may point to this page. The route must be registered exclusively inside a middleware group that enforces admin authentication (e.g., `auth` + `admin` or an equivalent admin guard).
- The page must never appear in customer-facing navigation, sitemap, or any publicly documented URL.
- Direct URL access by non-admin authenticated users must return a 403 (Forbidden) response, not a redirect to login.
- The page must never be indexed by search engines (include `X-Robots-Tag: noindex` and/or a `<meta name="robots" content="noindex">` tag when implemented).
- No link to this page should appear in any customer-visible template, email, or notification.

---

## 3. Inputs

The test page exposes four input fields that together define one test run against the Ask AI pipeline:

| Field | Type | Description |
|---|---|---|
| `listing_type` | Select / dropdown | The auction or listing type to test against (e.g., `seller_agent_auction`, `buyer_agent_auction`, `landlord_agent_auction`, `tenant_agent_auction`). Drives context-builder and contract resolution. |
| `listing_id` | Integer / text | The ID of the specific listing record to load context from. The pipeline will fetch real listing data for this record. |
| `question` | Textarea (free text) | The natural-language question to send through the full pipeline. Simulates what a user would ask on a live listing page. |
| Compatibility pair options | Optional selectors | Role and/or property-type override fields for testing edge cases — e.g., forcing a specific role context or property type that may differ from what the listing record natively resolves to. These are optional and intended for compatibility-matrix testing only. |

All four areas must be submitted together as a single test run. The page should make it clear which fields are required vs. optional.

---

## 4. Pipeline Shown

The page must display the output of each pipeline stage in order, giving the tester full visibility into how the question is transformed from raw input to final response. The stages, in execution order, are:

1. **Classifier**
   Receives the raw question text and determines its intent category (e.g., property detail, process/procedure, legal/compliance refusal trigger). This stage controls which downstream path the question takes.

2. **Context Builder**
   Loads the listing record identified by `listing_type` + `listing_id` and assembles a structured context object (listing fields, agent info, offer terms, etc.) relevant to answering the question. Output feeds the contract and prompt stages.

3. **Response Contract**
   Evaluates the classified intent and the assembled context to determine what shape the final answer must take — which fields to include, which to omit, and whether a refusal is required. Acts as a guardrail layer before the prompt is constructed.

4. **Prompt Builder**
   Combines the context object and the response contract into the final prompt package that will be sent to the language model. This stage controls token usage, field ordering, and any system-level instructions.

5. **OpenAI Adapter**
   Sends the prompt package to the configured OpenAI model and retrieves the raw model response. Handles API call mechanics (model selection, temperature, timeout, error handling). This is the only stage with external network I/O.

6. **Final Response Builder**
   Takes the raw model response and post-processes it into the structured final response — applying disclosure injection, source attribution, formatting, and any refusal overrides required by the response contract.

---

## 5. Output Panels

The test page must render nine distinct output panels, one per result artifact, so testers can inspect each layer independently:

| # | Panel | Contents |
|---|---|---|
| 1 | **Classification result** | The intent category assigned by the Classifier, plus any confidence score or secondary labels if available. |
| 2 | **Context summary** | A human-readable summary of the data the Context Builder assembled from the listing record — key fields included, fields omitted, and any warnings about missing data. |
| 3 | **Contract result** | The response contract produced for this question+context combination: required fields, disallowed topics, refusal flag (yes/no), and the reasoning. |
| 4 | **Prompt package** | The full prompt text (system + user messages) that was sent to the OpenAI Adapter. Displayed verbatim so prompt issues are immediately visible. |
| 5 | **Raw model response** | The unmodified response body returned by the OpenAI API, including any finish reason, token usage, and model metadata. |
| 6 | **Final response** | The post-processed, user-facing answer as assembled by the Final Response Builder — exactly what a real user would receive. |
| 7 | **Disclosures** | Any disclosure strings injected into or appended to the final response (e.g., "This is not legal advice"), listed individually. |
| 8 | **Source attribution** | The listing fields, documents, or data sources cited as the basis for the answer, if the pipeline produces attribution metadata. |
| 9 | **Errors / warnings** | Any non-fatal warnings, pipeline exceptions, missing-field notices, or degraded-path flags raised during the run. Fatal errors must surface here with enough detail to diagnose without reading server logs. |

All panels should be visible on the same page after a single test run submission. Panels for stages that did not execute (e.g., because an earlier stage raised a fatal error) should indicate they were skipped and why.

---

## 6. Safety Constraints

The following constraints are non-negotiable and must be enforced in any future implementation, regardless of how the UI is built:

- **No public access.** The page must never be reachable without admin authentication. Any route misconfiguration that exposes it publicly is a blocking defect.
- **No customer-facing launch.** This page is a diagnostic tool only. It must not be promoted, linked, or described to customers at any point.
- **No database writes** unless logging is explicitly designed, reviewed, and approved in a separate future task. The test page must be read-only by default. If logging is added later, it must be an explicit opt-in with its own access controls and data retention policy — not a side effect of running a test.
- **No hidden decision-making.** Every pipeline stage must be visible in the output panels. No stage may silently alter the question, context, or response without that transformation appearing somewhere in the displayed output. The test page is specifically designed to make the pipeline transparent.
- **No legal, brokerage, tax, or lending advice output.** The Ask AI pipeline's refusal policy applies on this page exactly as it would in production. The admin test context does not grant permission to bypass refusal guardrails. If the pipeline would refuse a question in production, it must refuse it here too.

---

## 7. Future Implementation Notes

The following items are open decisions and considerations to be resolved when UI work begins. They are captured here so the implementing engineer has the full context:

- **Auth middleware choice.** The admin guard implementation in this codebase should be confirmed before registering the route. Options include a dedicated `admin` middleware, a policy check, or a role-checked `can()` gate. The choice must be consistent with how other admin-only pages are protected.
- **Logging opt-in design.** If test runs should be logged for audit or regression purposes, this must be designed as a separate feature with its own task, schema review, and data retention policy. The initial implementation must not log anything by default.
- **Panel rendering approach.** Panels could be rendered as Blade partials, a Livewire component, or a simple AJAX-refreshed div. The choice should weigh developer ergonomics against the need for progressive disclosure (e.g., streaming results stage by stage vs. all-at-once after completion).
- **Error handling UX.** If a pipeline stage throws a fatal exception, the page should degrade gracefully — showing completed-stage panels and a clear error in the Errors/Warnings panel — rather than returning a 500. This requires a try/catch boundary around each stage invocation.
- **Token and cost visibility.** Displaying the OpenAI token usage from the Raw Model Response panel helps the team monitor cost-per-query during testing. Consider surfacing this prominently (e.g., total tokens used, estimated cost) so it is easy to spot regressions.
- **Listing ID lookup UX.** A future enhancement could allow searching listings by address or MLS number rather than requiring the internal database ID. This is out of scope for the initial implementation but worth considering in the input field layout.
- **Compatibility pair defaults.** When the optional role/property-type overrides are left blank, the pipeline should default to what the listing record natively resolves to. The spec for exactly how these overrides interact with the Context Builder should be documented when the Context Builder is implemented.
