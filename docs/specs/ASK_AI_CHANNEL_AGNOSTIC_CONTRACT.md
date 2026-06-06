# Ask AI Channel-Agnostic API Contract

**Version:** ASK_AI_API_V1  
**Status:** Active  
**Authoritative design source:** `docs/audits/ASK_AI_NATURAL_LANGUAGE_ROUTING_AUDIT.md` §9.4

---

## 1. Purpose

The Ask AI pipeline is a single governed intelligence brain. This document defines the canonical API contract that every delivery channel (web, SMS, Messenger, WhatsApp, mobile app, CRM) must use to invoke the pipeline. Channel adapters translate between their native format and this contract. The pipeline executes identically regardless of channel.

---

## 2. Routes

| Route | Middleware | Use |
|---|---|---|
| `POST /ask-ai/ask` | `web`, `throttle:ask-ai-api` | Website / web-channel use; session + CSRF protected |
| `POST /api/ask-ai/ask` | `auth:sanctum`, `throttle:ask-ai-api` | SMS, Messenger, WhatsApp, mobile, CRM integrations |

The existing `POST /ask-ai/listing-question` route is **not** replaced. It continues to serve the current website widget unchanged.

---

## 3. Input Contract

```json
{
  "listing_type": "seller | buyer | landlord | tenant",
  "listing_id":   123,
  "question":     "How many bedrooms does this have?",
  "options":      {},
  "channel":      "web | sms | messenger | whatsapp | mobile | crm",
  "session_id":   "optional-opaque-string"
}
```

### Field definitions

| Field | Type | Required | Constraints |
|---|---|---|---|
| `listing_type` | string | Yes | Canonical or aliased listing type |
| `listing_id` | integer | Yes | Primary key of the listing record |
| `question` | string | Yes | Max 1,000 characters |
| `options` | object | No | Optional pair/context options forwarded to the internal runner |
| `channel` | string | No | One of: `web`, `sms`, `messenger`, `whatsapp`, `mobile`, `crm`. Defaults to `web` if omitted |
| `session_id` | string | No | Opaque, caller-supplied session reference for logging/continuity; max 255 characters |

A missing or invalid `listing_id` returns **HTTP 422** with a structured validation error body:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "listing_id": ["The listing id field is required."]
  }
}
```

---

## 4. Output Contract

```json
{
  "success":             true,
  "status":              "answered | insufficient_context | blocked | unsupported | failed",
  "answer_text":         "This property has 4 bedrooms.",
  "question_type":       "listing_facts",
  "follow_up_questions": [
    {"label": "...", "question": "...", "question_type": "..."}
  ],
  "disclosures":         ["AI-generated response. Verify independently."],
  "attribution":         {
    "sources": [...],
    "required_sources": [...],
    "versions": {...}
  },
  "error":               null,
  "contract_version":    "ASK_AI_API_V1"
}
```

### Field definitions

| Field | Type | Notes |
|---|---|---|
| `success` | bool | `true` only when status is `answered` |
| `status` | string | Canonical API status (see §5) |
| `answer_text` | string\|null | The generated answer text; null when status is not `answered` |
| `question_type` | string\|null | Classified question type from the pipeline |
| `follow_up_questions` | array | Chip suggestions; empty array when status is not `answered` |
| `disclosures` | array\|string\|null | Required disclosures; null on non-answer paths |
| `attribution` | object\|null | Source attribution from the pipeline; null on non-answer paths |
| `error` | string\|null | null on success; generic public message on failure; never exposes internals |
| `contract_version` | string | Always `ASK_AI_API_V1`; bump when output shape changes |

---

## 5. Internal-to-API Status Mapping

The `AskAiRunnerV2Service::run()` method returns a `status` field that reflects the final response state. The controller maps this to the canonical API status as follows:

| Runner output `status` | API output `status` | `success` |
|---|---|---|
| `ready` | `answered` | `true` |
| `insufficient_context` | `insufficient_context` | `false` |
| `blocked` | `blocked` | `false` |
| `unsupported` | `unsupported` | `false` |
| `failed` | `failed` | `false` |

**Implementation note:** The mapping is applied against `$result['status']` from the runner return value (the final-response level). The intermediate statuses `prompt_ready` and `refusal_required` are consumed inside the pipeline and are **never present** in the runner return value.

---

## 6. Rate Limiting

- **Limit:** `config('ask_ai.rate_limit_per_minute', 20)` requests per minute
- **Key:** authenticated user ID; falls back to IP address for anonymous/web requests
- **Named limiter:** `ask-ai-api` (defined in `RouteServiceProvider::configureRateLimiting()`)
- **On breach:** HTTP 429 with `Retry-After` header

---

## 7. Authentication

| Route | Auth requirement |
|---|---|
| `POST /ask-ai/ask` | None (web session optional); CSRF-protected via `web` middleware |
| `POST /api/ask-ai/ask` | Laravel Sanctum token required; returns HTTP 401 if unauthenticated |

---

## 8. Channel Adapter Responsibilities

Each channel adapter wraps the canonical API. Adapter rules:

1. **Translate** incoming channel format to the canonical input contract before calling the API.
2. **Translate** the canonical output contract to the channel's expected format after receiving the response.
3. **Apply channel-specific length limits:**
   - SMS: truncate `answer_text` to ≤ 160 characters
   - Messenger: truncate `answer_text` to ≤ 2,000 characters
4. **Never** modify pipeline internals, governance layers, or bypass fair housing compliance.
5. **Never** pass protected-class signals via the `options` field.

---

## 9. Source Hierarchy

When the pipeline resolves a factual question, answer sources are prioritized in this order:

```
1. faq_answers[field_key]     — human-authored seller/landlord answers (highest fidelity)
2. listing[field_key]         — native column or EAV meta (structured data)
3. property_intelligence      — AI-derived strengths/highlights (secondary)
4. location_intelligence      — AI-derived location context (supplementary)
5. OpenAI intent normalization — ambiguous questions only (lowest priority)
```

FAQ answers always win because they are authored by the seller/landlord with specific contextual knowledge. AI-derived intelligence enriches but cannot fabricate facts.

---

## 10. Versioning Policy

| Scenario | Action |
|---|---|
| New optional output field added | No version bump; field added with null default |
| Existing field renamed or removed | Bump `contract_version` to `ASK_AI_API_V2` |
| Input field added (optional) | No version bump |
| Input field made required | Bump contract version |
| Status value added or removed | Bump contract version |

The `contract_version` field in the response allows callers to detect breaking changes. Channel adapters should assert the expected version and alert on mismatch.

---

## 11. Governance Constraints

- The pipeline executes identically regardless of which channel triggered the request.
- Fair housing compliance is enforced for all channels equally.
- Internal pipeline state (`prompt_package`, `context`, `contract`, `adapter_result`, `classification`) is **never** exposed in the API response.
- Error messages in the `error` field are always generic public-safe strings; no internal exception messages, stack traces, or service internals are returned.
- The `channel` field is logged for analytics but does not alter pipeline behavior.

---

## 12. Future Channels

The following channels are approved for future adapter implementation (separate tasks per channel):

| Channel | Adapter file (planned) | Notes |
|---|---|---|
| Twilio SMS | `app/Channels/AskAi/TwilioSmsAdapter.php` | 160-char truncation required |
| Facebook Messenger | `app/Channels/AskAi/MessengerAdapter.php` | 2,000-char truncation required |
| WhatsApp | `app/Channels/AskAi/WhatsAppAdapter.php` | Similar to Messenger |
| Mobile SDK | `app/Channels/AskAi/MobileAdapter.php` | No length truncation needed |
| CRM | `app/Channels/AskAi/CrmAdapter.php` | Caller responsible for display rendering |
