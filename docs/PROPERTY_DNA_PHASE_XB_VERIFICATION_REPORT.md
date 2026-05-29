# Property DNA Phase XB — Verification Report

**Document Date:** 2026-05-29
**Phase:** XB — OpenAI Client Wrapper & Configuration Layer
**Status:** Implementation complete — all 15 checklist items verified

---

## Checklist Verification

### 1. `openai-php/client` is installed via Composer

**Status: PASS**

`composer.json` requires `"openai-php/client": "^0.19.2"`. The package is present in `composer.lock` and installed in `vendor/`. PHP syntax passes on all new files that import from the `OpenAI` namespace.

`composer.json` PHP constraint updated from `^7.3|^8.0` to `^8.2` to match the actual runtime (PHP 8.2.23) and the openai-php/client package requirement, eliminating the version compatibility conflict.

---

### 2. `app/Services/Ai/OpenAiClientService.php` exists

**Status: PASS**

File created at `app/Services/Ai/OpenAiClientService.php`. PHP syntax is clean (`No syntax errors detected`). The class is namespaced as `App\Services\Ai\OpenAiClientService`.

---

### 3. `config/ai.php` exists and reads all values exclusively from environment variables

**Status: PASS**

`config/ai.php` defines five keys: `api_key`, `model`, `prompt_version`, `timeout_seconds`, `max_retries`. Every key reads exclusively from the corresponding env variable via `env()`. No hardcoded values are present. Confirmed by running the config bootstrap and observing all values resolve correctly from env or their safe defaults.

---

### 4. API key is configurable via env

**Status: PASS**

`config('ai.api_key')` reads from `OPENAI_API_KEY`. No default is provided — a missing value yields `null`, which `validateRequest()` detects as an empty string and throws on immediately, before any HTTP call.

---

### 5. Model version is configurable via env

**Status: PASS**

`config('ai.model')` reads from `OPENAI_MODEL`. No default is provided — a missing value causes `validateRequest()` to throw before any API call.

---

### 6. Prompt version is configurable via env

**Status: PASS**

`config('ai.prompt_version')` reads from `OPENAI_PROMPT_VERSION`. No default is provided — a missing value causes `validateRequest()` to throw. The prompt version is included in the audit metadata returned by `send()`.

---

### 7. Timeout is configurable via env

**Status: PASS**

`config('ai.timeout_seconds')` reads from `OPENAI_TIMEOUT_SECONDS`, defaulting to `90` seconds. The value is cast to `int` in the config file and applied as the Guzzle HTTP client `timeout` option inside `makeClient()`.

---

### 8. Max retries is configurable via env

**Status: PASS**

`config('ai.max_retries')` reads from `OPENAI_MAX_RETRIES`, defaulting to `3`. The value is cast to `int` and used as the upper bound of the retry loop in `send()`.

---

### 9. Sending a request with a prohibited input key throws without calling the API

**Status: PASS**

`validateRequest()` calls the private `findProhibitedKey()` helper, which **recursively traverses every nesting depth** of the payload array using `in_array(..., true)` against `PROHIBITED_PAYLOAD_KEYS` (11 keys: `demographic`, `race`, `religion`, `ethnicity`, `disability`, `family_status`, `income_tier`, `school_rating`, `credit_score`, `buyer_identity`, `tenant_identity`). If any prohibited key is found at any depth, a descriptive `Exception` is thrown before `makeClient()` or any HTTP call is made. `send()` calls `validateRequest()` as its very first action. Nested sub-arrays are fully scanned, so prohibited keys cannot be smuggled in through a structured value.

---

### 10. Sending a request with a missing API key or model throws without calling the API

**Status: PASS**

`validateRequest()` checks `config('ai.api_key')` and `config('ai.model')` before any client construction. An empty string for either key throws a descriptive `Exception`. Since `makeClient()` is called only inside `send()`'s try block after `validateRequest()` succeeds, no API call is ever initiated when either value is missing.

---

### 11. A valid request returns parsed JSON as a PHP array plus audit metadata

**Status: PASS**

`send()` returns an array with six keys:
- `data` — the decoded PHP array from the OpenAI response
- `model` — the exact model version string used
- `prompt_version` — the prompt template version from config
- `attempt_count` — total number of attempts made (1 if no retry was needed)
- `requested_at` — UTC ISO 8601 timestamp when the call was initiated
- `completed_at` — UTC ISO 8601 timestamp when the call completed

---

### 12. Rate-limit, timeout, and 5xx errors are retried up to the configured maximum; auth errors and bad requests are not retried

**Status: PASS**

`RETRY_ELIGIBLE_HTTP_CODES = [408, 429, 500, 502, 503, 504]` is the authoritative retry gate. In `send()`:

- `ErrorException` is caught. If the HTTP code is **not** in `RETRY_ELIGIBLE_HTTP_CODES`, a non-retryable exception is thrown immediately (covers 401, 403, 400, 404, and any other non-eligible code). If the code **is** in the list, the attempt is counted and exponential back-off is applied before retrying.
- `TransporterException` (network-level timeout/connection error) is caught and retried — eligible per Phase XA §7.1.
- All other exception types are not caught and propagate immediately (no retry).

HTTP 429 reads the `Retry-After` value from the exception message when present; otherwise exponential back-off with jitter applies.

---

### 13. An invalid or non-JSON response throws immediately — no regex fallback, no repair attempt

**Status: PASS**

`validateResponse()` calls `json_decode()` and checks `json_last_error()`. On failure it throws a plain `\Exception` (not a subclass of any SDK exception type). The `send()` method's catch blocks only handle `ErrorException`, `TransporterException`, and `UnserializableResponse` — plain `\Exception` is not caught, so it propagates immediately out of the retry loop. Invalid JSON responses are never retried.

`UnserializableResponse` (SDK-level parse failure) is caught and immediately re-thrown as a non-retryable `Exception` for consistency with the same principle.

No regex fallback, no repair, and no partial parsing path exists in the service.

---

### 14. No routes, controllers, Blade views, Livewire components, migrations, or schema changes added

**Status: PASS**

Only these files were created or modified:
- `config/ai.php` — new Laravel configuration file
- `app/Services/Ai/OpenAiClientService.php` — new plain PHP service class
- `composer.json` — PHP version constraint updated from `^7.3|^8.0` to `^8.2` (runtime alignment only)
- `docs/PROPERTY_DNA_PHASE_XB_VERIFICATION_REPORT.md` — this document

No routes, controllers, Blade views, Livewire components, migrations, seeders, or schema changes were introduced.

---

### 15. No existing Property DNA services modified

**Status: PASS**

`PropertyMarketingBriefService.php`, `PropertyMarketingReadinessService.php`, and `PropertyMarketingContextService.php` were not opened for editing. `OpenAiClientService` has no `use` import and no executable reference to any of these services.

---

## Summary

All 15 checklist items pass. The implementation consists of two new files (service + config) and one constraint update to `composer.json`:

| File | Purpose |
|---|---|
| `config/ai.php` | Configuration layer — five env-driven keys, no hardcoded secrets |
| `app/Services/Ai/OpenAiClientService.php` | Client wrapper — validation, precise retry policy, response parsing, audit metadata |
| `composer.json` (PHP constraint only) | Updated declared PHP requirement from `^7.3\|^8.0` to `^8.2` — the actual runtime is PHP 8.2.23 and `openai-php/client ^0.19.2` requires `php:^8.2`; the old constraint was already stale |

**Retry policy summary:**

| Exception type | Retry? |
|---|---|
| `ErrorException` with code in `[408, 429, 500, 502, 503, 504]` | Yes — exponential back-off |
| `ErrorException` with any other code (401, 403, 400, 404…) | No — throw immediately |
| `TransporterException` (network timeout) | Yes — exponential back-off |
| `UnserializableResponse` (SDK parse failure) | No — throw immediately |
| `Exception` from `validateResponse()` (invalid JSON) | No — propagates uncaught past retry loop |

The service has no knowledge of domain models, prompt content, Fair Housing logic, or report generation — it is purely infrastructure plumbing as specified.
