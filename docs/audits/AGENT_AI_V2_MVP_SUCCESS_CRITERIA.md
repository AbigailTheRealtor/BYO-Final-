# Agent AI V2 — MVP Success Criteria & Release Gate

**Status:** Approved (pending build completion)  
**Document Date:** June 15, 2026  
**Scope:** Release-gate criteria for Agent AI V2 across all nine MVP success dimensions  
**Governed by:** This document. Completion of the context-source audit (#2776) alone does not satisfy MVP readiness.

> **MVP Readiness Rule:** Agent AI V2 is not considered MVP-ready until every criterion in this document has a ✅ verified status. Builds 1–8 must be complete and all release-gate checks must pass. No individual build completion constitutes MVP approval.

---

## V2 Build Map

| Ticket | Build | Purpose |
|---|---|---|
| #2776 | Phase 1 — Audit | Context Source Audit (prerequisite — not a build) |
| #2777 | Build 1 | Foundation & Contracts |
| #2779 | Build 2 | Context Loaders |
| #2780 | Build 3 | Conversation Layer + Permission Guard + OpenAI |
| #2781 | Build 4 | CTA / Action Resolver |
| #2782 | Build 5 | Lead Capture + Inbox + Notifications |
| #2783 | Build 6 | Chat Modal UI |
| #2784 | Build 7 | Tests + Safety |
| #2785 | Build 8 | Rollout + Analytics |

---

## Related Documents

| Document | Purpose |
|---|---|
| `docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md` | Defines all approved data sources, context fragment contract, privacy boundaries, and field-level access classifications |

---

## MVP Success Criteria

### Criterion 1 — Foundation & Contracts (Build 1 / #2777)

**Verified when:**

- The Agent AI V2 feature flag (`AGENT_AI_V2_ENABLED`) exists in `config/ask_ai.php` and defaults to `false`
- All context fragment interfaces and base contracts are defined (source_key, priority, content, token_estimate, public_allowed, role_scope, cache_ttl, loaded_at)
- The conversation session schema or Redis key contract is defined and documented (keyed on `{channel}:{session_id}:{listing_type}:{listing_id}`)
- `ask_ai_usage_logs` has a `session_id` column (migration applied)
- No legacy V1 code paths are broken — V1 continues to serve all existing Ask AI traffic when the flag is off
- Build 1 does not activate any user-facing functionality

---

### Criterion 2 — Context Loaders (Build 2 / #2779)

**Verified when:**

- Context loaders exist for all four listing types (seller, landlord, buyer, tenant) and the agent profile scope, each returning a fragment conforming to the Build 1 contract
- Token estimates are calculated per-fragment before assembly
- Fragment-level Redis caching is active with the TTLs defined in the context source audit (Section 10.2)
- Null/empty fields are stripped before serialization — no null-valued context keys reach the prompt builder
- JSON array fields are summarized as comma-separated strings — no raw JSON arrays reach the prompt
- `offer_analysis`, bid tables, counteroffer records, and `AcceptedBidSummary` data are confirmed absent from all loader outputs
- All four listing-type loaders and the agent profile loader have passing unit tests covering field extraction, null stripping, and cache key generation
- Token budget enforcement fires and drops lower-priority fragments when the assembled context exceeds the configured budget

---

### Criterion 3 — Real OpenAI Response (Build 3 / #2780)

**Verified when:**

- The conversation layer stores and retrieves conversation history keyed on `{channel}:{session_id}:{listing_type}:{listing_id}`
- The permission guard blocks unauthenticated access to private context fields — verified by test
- The OpenAI adapter calls the model specified in `config/ask_ai.php` — not a hardcoded model name
- **At least one successful end-to-end OpenAI call has been verified using the configured model in a non-production environment, and the response is persisted through the Agent AI conversation pipeline** (smoke-test result logged to `ask_ai_usage_logs` with `success = true`)
- The intent normalizer (`AskAiIntentNormalizerService`) correctly uses the configured model from `config/ask_ai.php`
- Multi-turn conversation context is passed correctly for sessions up to the configured maximum turn limit
- Prohibited questions (fair-housing violations) are refused before any OpenAI call — verified by test
- The feature flag gates all traffic: V1 behaviour is unchanged when `AGENT_AI_V2_ENABLED = false`

---

### Criterion 4 — CTA / Action Resolver (Build 4 / #2781)

**Verified when:**

- The CTA resolver produces a contextually appropriate call-to-action for each listing type based on the current conversation state and listing status
- CTAs are suppressed or hidden when the listing is closed, sold, or draft
- CTA output is included in the final response contract and rendered in the chat UI
- No CTA exposes or references bid, offer, or negotiation data
- CTA resolver has passing unit tests covering all listing types and status edge cases

---

### Criterion 5 — Lead Capture + Inbox + Notifications (Build 5 / #2782)

**Verified when:**

- Every completed Agent AI conversation that reaches a CTA generates a lead record with a `channel` attribute
- Lead records are routed to the correct agent inbox based on listing type and agent assignment
- The agent inbox displays all lead records regardless of originating channel
- Notification is sent to the agent (email or in-app, per notification preferences) when a new lead arrives
- Lead capture schema accepts the `channel` field without schema changes — verified against the channel-agnostic architecture contract from the context source audit (Section 14)
- Lead capture does not store raw conversation transcript — only the session identifier, listing reference, contact intent signal, and channel
- Passing integration tests cover lead creation, inbox routing, and notification dispatch for at least two channels (`web` and one reserved channel)

---

### Criterion 6 — Chat Modal UI (Build 6 / #2783)

**Verified when:**

- The chat modal renders correctly on all four listing-type public view pages (seller, landlord, buyer, tenant)
- The agent profile chat renders correctly on the Hire Me page and embeddable widget
- Suggested question chips appear on modal open and refresh correctly after each answer
- Follow-up question chips appear after each answer when the runner returns them
- The modal is accessible (ARIA labels, keyboard navigation, focus trap on open/close)
- The modal degrades gracefully when the API is unavailable — an error state is shown without crashing the surrounding page
- The feature flag gates the modal — no modal UI renders when `AGENT_AI_V2_ENABLED = false`
- Verified across Chrome, Firefox, and Safari (latest stable)
- Verified on mobile viewport (375px width minimum)

---

### Criterion 7 — Privacy & Safety (Build 7 / #2784)

**Verified when:**

All of the following safety gate tests pass in CI:

- **Prompt injection protection** — Adversarial inputs containing instruction-override patterns (e.g. "Ignore previous instructions and…") are detected and refused before reaching the OpenAI adapter
- **Context exfiltration protection** — The response never includes raw context data (field names, internal keys, EAV meta key names, or system instruction text) that was not explicitly authored as answer content
- **Session token tampering** — A request with a manipulated `session_id` cannot access conversation history belonging to a different user or listing
- **Cross-session isolation** — Two concurrent sessions on the same listing cannot read each other's conversation history
- **Scope isolation** — A question routed through the seller context cannot receive landlord-only or buyer-only fields in its response
- **Cross-role isolation** — A buyer listing context cannot populate seller-only fields (e.g. `asking_price` from `maximum_budget` EAV) in its response, and vice versa
- **Fair-housing refusal** — All test questions touching protected class characteristics (race, religion, national origin, familial status, sex, disability, color) are refused with the correct refusal template and zero OpenAI calls
- **Offer and bid data exclusion** — **Offer, counteroffer, accepted bid summary, competing bid, commission negotiation, and compensation data are never included in any public Agent AI context package.** This is verified by asserting that no response for any listing type contains content derived from `*_agent_auction_bids`, `AcceptedBidSummary`, or any compensation negotiation record, regardless of question phrasing
- **Agent compensation privacy** — Agent profile chat never returns specific fee amounts, percentage rates, or dollar values from `agent_default_profiles.profile_data`
- **Rate limit enforcement** — The `throttle:ask-ai-api` limit is enforced per-IP and per-user; a 429 response is returned when the limit is exceeded

---

### Criterion 8 — Rollout (Build 8 / #2785)

**Verified when:**

- The feature flag (`AGENT_AI_V2_ENABLED`) has been toggled on in staging without errors in the application log
- A gradual rollout plan is documented (percentage rollout or allowlist) and the mechanism is implemented
- Rollback procedure is documented: toggling `AGENT_AI_V2_ENABLED = false` restores full V1 behavior without a deployment
- Load test results confirm the system handles the expected peak request volume without exceeding configured response time thresholds
- No V1 Ask AI regression is introduced — all existing V1 Ask AI tests continue to pass
- The staging environment has been verified end-to-end by a human reviewer (not just automated tests) before production rollout proceeds

---

### Criterion 9 — Analytics Verified (Build 8 / #2785)

**Verified when:**

- `ask_ai_usage_logs` is being populated correctly for every Agent AI V2 request: `session_id`, `channel`, `listing_type`, `listing_id`, `question_type`, `model`, `prompt_tokens`, `completion_tokens`, `total_tokens`, `estimated_cost_usd`, `outcome_category`, `success`
- At minimum the following analytics queries or dashboard panels are working:
  - Total V2 questions per day (by listing type and question type)
  - Success rate by question type
  - Estimated cost per day and per month
  - Top outcome categories (e.g. `deterministic_match`, `openai_answer`, `refused`, `insufficient_context`)
  - Average response time by question type
- The admin analytics route (`/admin/ask-ai/analytics`) returns data from V2 logs, not only V1 logs
- Token cost monitoring alert is configured: an alert fires when daily estimated cost exceeds a configurable threshold
- Analytics data is verified to be present after at least one full day of staging traffic

---

## Release Gate Checklist

The following checklist must be completed by a human reviewer before V2 is promoted to production. All nine criteria above must show ✅ verified status.

| # | Criterion | Build | Status |
|---|---|---|---|
| 1 | Foundation & Contracts | #2777 | ⬜ Pending |
| 2 | Context Loaders | #2779 | ⬜ Pending |
| 3 | Real OpenAI Response | #2780 | ⬜ Pending |
| 4 | CTA / Action Resolver | #2781 | ⬜ Pending |
| 5 | Lead Capture + Inbox + Notifications | #2782 | ⬜ Pending |
| 6 | Chat Modal UI | #2783 | ⬜ Pending |
| 7 | Privacy & Safety | #2784 | ⬜ Pending |
| 8 | Rollout | #2785 | ⬜ Pending |
| 9 | Analytics Verified | #2785 | ⬜ Pending |

**Final sign-off:** A production release requires sign-off confirming all nine criteria are ✅. No individual build completion substitutes for this gate.

---

*This document is the authoritative MVP release gate for Agent AI V2. It is updated as builds complete. All criteria are derived from the approved V2 roadmap (#2776–#2785) and the context-source audit (docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md).*
