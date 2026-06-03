# Ask AI — Rate Limiting Specification

## 1. Purpose

The Ask AI feature allows users to submit natural-language questions about listings and receive AI-generated answers. Without rate limiting, this surface is exposed to:

- **Cost overruns** — every question incurs an API call to the underlying LLM provider; unbounded usage directly increases infrastructure costs.
- **Abuse and scraping** — bad actors could automate thousands of questions to extract listing data or probe the system.
- **Denial-of-service** — a flood of requests (accidental or deliberate) could degrade response times for all users.
- **Fairness** — without caps, a small number of heavy users can monopolize shared capacity.

Rate limiting enforces per-identity quotas at the IP, user, and listing levels so that the feature remains fast, affordable, and available to everyone.

---

## 2. Public Guest Limits

Unauthenticated (guest) users are identified by their originating IP address.

| Dimension | Limit |
|-----------|-------|
| Questions per IP per hour | **5** |
| Reset cadence | Rolling 60-minute window |

Guests who exceed this limit receive an HTTP 429 response (see Section 8). They are not blocked permanently; the throttle lifts automatically when the rolling window expires.

---

## 3. Logged-In User Limits

Authenticated users are identified by their `users.id`.

| Dimension | Limit |
|-----------|-------|
| Questions per user per hour | **20** |
| Reset cadence | Rolling 60-minute window |

The per-user limit is tracked independently of the per-IP limit. Both limits apply simultaneously — whichever cap is hit first triggers the throttle.

---

## 4. Admin / Internal Test Limits

Users with the `admin` role are subject to a relaxed daily cap to support internal testing, QA, and content review without disrupting the main user quotas.

| Dimension | Limit |
|-----------|-------|
| Questions per admin per day | **100** |
| Reset cadence | Calendar day (midnight UTC) |

Admin requests still count toward per-IP limits to prevent infrastructure abuse from a single machine.

---

## 5. Per-IP Limits

A shared IP cap applies across all request types (guest and authenticated) originating from the same address.

| Dimension | Limit |
|-----------|-------|
| Total questions per IP per hour | **30** |
| Reset cadence | Rolling 60-minute window |

This cap prevents a single IP from bypassing per-user limits by cycling through many accounts or mixing guest and authenticated sessions. The per-IP cap is evaluated in addition to the per-user or per-guest cap, not instead of it.

---

## 6. Per-Listing Limits

Regardless of who is asking, each listing has its own cap to prevent coordinated targeting of a single property.

| Dimension | Limit |
|-----------|-------|
| Questions per listing per hour | **10** |
| Reset cadence | Rolling 60-minute window |
| Applies to | All request types (guest, user, admin) |

When this limit is reached, all further questions about that listing return HTTP 429 until the window resets, even if the requester has remaining personal quota.

---

## 7. Abuse Behavior

Violations are tracked per identity (IP or user ID). Repeated or severe violations trigger escalating responses.

### Escalation Levels

| Level | Trigger | Platform Response |
|-------|---------|-------------------|
| **Level 1** | Rate limit exceeded | **Temporary throttle** — requests return HTTP 429 for the remainder of the current rolling window. No permanent action. |
| **Level 2** | Repeated violations (≥ 3 limit-exceeded events within 24 hours) | **Extended cooldown** — the identity is locked out for an extended period (e.g., 24 hours) beyond the normal window reset. |
| **Level 3** | Pattern consistent with automated or scripted abuse (e.g., requests at machine-speed intervals, rotating IPs with identical fingerprints) | **Manual review flag** — the identity is queued for human review. Requests may continue to be throttled during the review period. |
| **Level 4** | Confirmed severe abuse (e.g., sustained scraping, credential stuffing, or intentional DoS) | **Permanent block** — the IP or user account is blocked from the Ask AI feature. Account suspension follows internal policy. |

Escalation state is stored in cache with appropriate TTLs aligned to each level's duration. Levels reset downward after a clean period (no violations) as determined by platform policy at implementation time.

---

## 8. Error Response Format

When any rate limit is exceeded, the API returns:

**HTTP Status:** `429 Too Many Requests`

**Response body (JSON):**

```json
{
  "error": {
    "message": "You have exceeded the Ask AI rate limit. Please try again later.",
    "retry_after": 3412,
    "limit_type": "user_hourly"
  }
}
```

### Field Definitions

| Field | Type | Description |
|-------|------|-------------|
| `message` | `string` | Human-readable explanation of why the request was rejected. |
| `retry_after` | `integer` | Seconds until the current window resets and the requester may try again. |
| `limit_type` | `string` | Machine-readable identifier of which limit was hit. See values below. |

### `limit_type` Values

| Value | Meaning |
|-------|---------|
| `guest_ip_hourly` | Guest (unauthenticated) IP hourly cap hit |
| `user_hourly` | Logged-in user hourly cap hit |
| `admin_daily` | Admin daily cap hit |
| `ip_shared_hourly` | Shared per-IP cap hit |
| `listing_hourly` | Per-listing hourly cap hit |

The `Retry-After` HTTP response header should also be set to the same integer value as `retry_after` for compatibility with standard HTTP clients.

---

## 9. Future Paid-Tier Limits

> **Placeholder** — specific values are to be determined during the paid-tier product design phase.

A future paid subscription tier is expected to offer significantly higher or unlimited Ask AI allowances. The following are anticipated dimensions:

| Dimension | Anticipated Behavior |
|-----------|---------------------|
| Per-user hourly limit | Raised significantly (e.g., 200+) or unlimited |
| Per-listing hourly limit | Raised or removed for listing owners |
| Admin / staff accounts | Potentially exempt from all limits |
| Per-IP shared cap | May be relaxed for verified paid subscribers |

Implementation notes for when this tier is designed:
- Paid-tier status should be checked via the user's subscription/plan attribute before evaluating rate limit keys.
- The rate limit middleware should branch on plan tier, not bypass the middleware entirely, to keep audit logging consistent.
- Per-IP caps should remain in place even for paid users to guard against credential sharing.

---

## 10. Implementation Plan

The following Laravel primitives and patterns should be used when implementing the rate limiting described in this spec.

### Laravel `RateLimiter` Facade

Use `Illuminate\Support\Facades\RateLimiter` to define named limiters. Define all Ask AI limiters in `AppServiceProvider::boot()` or a dedicated `RateLimitServiceProvider`.

```php
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

RateLimiter::for('ask-ai-guest', function (Request $request) {
    return Limit::perHour(5)->by('guest:ip:' . $request->ip());
});

RateLimiter::for('ask-ai-user', function (Request $request) {
    return $request->user()
        ? Limit::perHour(20)->by('user:' . $request->user()->id)
        : Limit::perHour(5)->by('guest:ip:' . $request->ip());
});

RateLimiter::for('ask-ai-ip-shared', function (Request $request) {
    return Limit::perHour(30)->by('ip:shared:' . $request->ip());
});
```

### Laravel Cache-Based Throttles

All rate limit counters are stored in the configured Laravel cache driver (Redis recommended for production). Keys should be namespaced consistently to avoid collisions with other throttles:

- `ask_ai:guest:{ip}:hourly`
- `ask_ai:user:{user_id}:hourly`
- `ask_ai:admin:{user_id}:daily`
- `ask_ai:ip:{ip}:hourly`
- `ask_ai:listing:{listing_id}:hourly`

TTLs should match the window duration (3600 seconds for hourly, 86400 for daily).

### Route Middleware

Apply throttles as route middleware on the Ask AI route group:

```php
Route::middleware([
    'throttle:ask-ai-ip-shared',
    'throttle:ask-ai-user',
])->group(function () {
    Route::post('/ask-ai/{listing}', [AskAiController::class, 'ask']);
});
```

The per-listing limit should be evaluated inside the controller or a dedicated `CheckListingAskAiLimit` middleware that reads the `{listing}` route parameter.

### Per-IP Cache Keys

```
ask_ai:ip:{sha256(ip_address)}:hourly
```

Hashing the IP avoids storing raw addresses in cache keys and ensures consistent key length.

### Per-User Cache Keys

```
ask_ai:user:{user_id}:hourly
ask_ai:admin:{user_id}:daily
```

User ID is a stable integer — no hashing required.

### Per-Listing Cache Keys

```
ask_ai:listing:{listing_type}:{listing_id}:hourly
```

Including `listing_type` (e.g., `seller`, `buyer`, `landlord`, `tenant`) prevents ID collisions across listing tables.
