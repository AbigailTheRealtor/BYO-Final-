# Offer Button / Action Wiring Plan

**Purpose:** Self-contained implementation reference describing exactly how
`OfferAvailableActionsService` and `OfferWorkflowFacade` must be wired into the
UI for every offer action button. No PHP, Blade, or JS changes are made here.
This document is the authoritative spec that a future implementation task
executes against without needing to re-read the service layer.

---

## Table of Contents

1. [Where available action flags are loaded](#1-where-available-action-flags-are-loaded)
2. [Button → flag → facade method mapping table](#2-button--flag--facade-method-mapping-table)
3. [Button render rules](#3-button-render-rules)
4. [Error / denial handling](#4-error--denial-handling)
5. [Success message handling](#5-success-message-handling)
6. [Audit log expectations](#6-audit-log-expectations)
7. [Testing plan](#7-testing-plan)
8. [Safe rollout order](#8-safe-rollout-order)

---

## 1. Where available action flags are loaded

### Who calls the service

A controller method (e.g. `OfferController::show()`) or a Livewire component's
`mount()` / `render()` method is responsible for computing the `$actions` array.
The call must happen **after** the authenticated user is resolved and **before**
the view is returned.

```php
use App\Services\Offers\OfferAvailableActionsService;

// Resolve actor identity from the authenticated session
$actorId   = auth()->id();           // int|null
$actorRole = auth()->user()->role;   // string — 'buyer' | 'seller' | 'agent' | 'system'

// Compute all flags in one call
$actions = app(OfferAvailableActionsService::class)
    ->forOffer($offer, $actorId, $actorRole);
```

### Returned shape

`OfferAvailableActionsService::forOffer()` always returns the following array
(sourced from `OfferAvailableActionsService.php`):

```php
[
    'can_submit'        => bool,
    'can_counter'       => bool,
    'can_accept'        => bool,
    'can_reject'        => bool,
    'can_withdraw'      => bool,
    'can_expire'        => bool,        // always false for non-system actors
    'can_view_timeline' => bool,
    'reasons'           => [
        'submit'        => string,      // human-readable denial reason, or ''
        'counter'       => string,
        'accept'        => string,
        'reject'        => string,
        'withdraw'      => string,
        'expire'        => string,
        'view_timeline' => string,
    ],
]
```

### How to make flags available to the view

**HTTP controller (Blade):** Pass the array directly to the view:

```php
return view('offers.show', [
    'offer'   => $offer,
    'actions' => $actions,
]);
```

The Blade template then reads `$actions['can_submit']`, `$actions['reasons']['submit']`, etc.

**Livewire component:** Store as a public property so it is available in the
template and can be refreshed after any state-changing action:

```php
public array $actions = [];

public function mount(Offer $offer): void
{
    $this->offer   = $offer;
    $this->actions = app(OfferAvailableActionsService::class)
        ->forOffer($offer, auth()->id(), auth()->user()->role);
}
```

The flags must **never** be re-queried inside individual button components or
partials. Pass them down from the top-level view or Livewire component.

---

## 2. Button → flag → facade method mapping table

Each row maps a UI button to the `can_*` flag that gates it, the
`OfferWorkflowFacade` method that executes it, and the actor roles that
`OfferPermissionService` permits for that action.

| Button | `can_*` flag | Facade method signature | Permitted `actorRole` values |
|---|---|---|---|
| **Submit Offer** | `can_submit` | `$facade->submit($offer, $actorId, $actorRole, $metadata, $ip)` | `buyer`, `system` |
| **Counter Offer** | `can_counter` | `$facade->counter($parent, $actorId, $actorRole, $overrides, $metadata, $ip)` | `buyer`, `seller`, `agent`, `system` |
| **Accept** | `can_accept` | `$facade->accept($offer, $actorId, $actorRole, $metadata, $ip)` | `seller`, `agent`, `system` |
| **Reject** | `can_reject` | `$facade->reject($offer, $actorId, $actorRole, $metadata, $ip)` | `seller`, `agent`, `system` |
| **Withdraw** | `can_withdraw` | `$facade->withdraw($offer, $actorId, $actorRole, $metadata, $ip)` | `buyer`, `system` |
| **Expire** *(system only)* | `can_expire` | `$facade->expire($offer, $actorId, $actorRole, $metadata, $ip)` | `system` only |
| **Timeline** | `can_view_timeline` | `$facade->timeline($offer)` — returns `array<int, array>`, no `allowed` key | `buyer`, `seller`, `agent`, `system` |

### Notes

- **`expire` is system-only** and must be hidden from all human-facing UIs.
  It is wired exclusively via a scheduled artisan command or a console action.
  `OfferPermissionService::canExpire()` returns `allowed = false` with a
  non-empty `reason` for any `$actorRole !== 'system'`; following the render
  rules in Section 3, this means the button is **disabled with a tooltip** for
  non-system actors — but since human users should never see this button at all,
  the implementation should **not render it** in human-facing views regardless
  of the flag value.

- **`timeline` is read-only** — it does not attempt a state transition, never
  calls `OfferEventLogService`, and returns `array<int, array>` rather than the
  standard result shape (no `allowed` key).

- **`counter` has a distinct result shape** — it does **not** return an `offer`
  key. Instead it returns `parent_offer` (the original `Offer`, now in
  `countered` status) and `counter_offer` (the newly created child `Offer`).
  See Section 5 for the full shape. The post-action refresh must use one of
  these two keys, not `$result['offer']`.

---

## 3. Button render rules

Every action button follows a **three-state rendering contract** driven entirely
by the `can_*` flag and its paired `reasons` string:

| Condition | Render behaviour |
|---|---|
| `can_* === true` | Render the button as **active / clickable**. |
| `can_* === false` **and** `reasons['<action>']` is non-empty | Render the button as **disabled** with a tooltip whose text is `$actions['reasons']['<action>']`. |
| `can_* === false` **and** `reasons['<action>']` is empty | **Hide the button entirely** (do not emit any HTML). |

### Blade pseudocode example

```blade
@if ($actions['can_submit'] || $actions['reasons']['submit'] !== '')
    <button
        @if (!$actions['can_submit'])
            disabled
            title="{{ $actions['reasons']['submit'] }}"
        @endif
        wire:click="submitOffer"
    >
        Submit Offer
    </button>
@endif
```

The "hide entirely" branch (`can_* false + empty reason`) is the mechanism used
to suppress `expire` from human-facing UIs: `OfferPermissionService::canExpire()`
returns `reason = "Cannot expire: actor role '{role}' is not permitted; only
'system' may expire offers."` for every non-system role — which is non-empty —
so technically the disabled+tooltip branch fires. To guarantee the expire button
is never shown in human UIs, the implementation must add an explicit `@if
($actorRole === 'system')` guard around the expire button regardless of the flag.

---

## 4. Error / denial handling

The defence is two-layered. Both layers must be implemented.

### Layer 1 — UI (pre-request)

The `can_*` flag prevents the HTTP request from being sent in the first place.
A button that is `disabled` or hidden cannot be clicked, so the facade is never
called for a denied action under normal operation.

### Layer 2 — Backend (post-request)

> **Critical architecture note:** `OfferWorkflowFacade` and its underlying
> services (`OfferSubmissionService`, `OfferDecisionService`, etc.) enforce only
> **state-machine rules** via `OfferStateMachineService::validateTransition()`.
> They do **not** consult `OfferPermissionService` or check actor roles.
> Role-based permission is the controller/Livewire layer's sole responsibility.

If a request reaches the controller or Livewire action despite the UI flag being
`false` (e.g. forged request, race condition, stale Livewire state), the backend
must **explicitly re-run the permission check** before calling the facade:

```php
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;

public function accept(Offer $offer, Request $request): JsonResponse
{
    $actorId   = auth()->id();
    $actorRole = auth()->user()->role;

    // ── Layer 2: explicit server-side permission check ──────────────────
    $actions = app(OfferAvailableActionsService::class)
        ->forOffer($offer, $actorId, $actorRole);

    if (! $actions['can_accept']) {
        // Facade is NOT called; no forbidden_transition_attempt row is written.
        return response()->json(['message' => $actions['reasons']['accept']], 422);
    }
    // ────────────────────────────────────────────────────────────────────

    $metadata = ['source' => 'web'];
    $result   = app(OfferWorkflowFacade::class)
        ->accept($offer, $actorId, $actorRole, $metadata, $request->ip());

    // Guard against state-machine denial (race condition, unexpected state).
    // In this path the facade DID write a forbidden_transition_attempt row.
    if (! $result['allowed']) {
        return response()->json(['message' => $result['reason']], 422);
    }

    return response()->json(['to_status' => $result['to_status']]);
}
```

**Livewire equivalent:**
```php
public function acceptOffer(): void
{
    $actorId   = auth()->id();
    $actorRole = auth()->user()->role;

    // ── Layer 2: explicit server-side permission check ──────────────────
    $actions = app(OfferAvailableActionsService::class)
        ->forOffer($this->offer, $actorId, $actorRole);

    if (! $actions['can_accept']) {
        $this->addError('offer', $actions['reasons']['accept']);
        return;
    }
    // ────────────────────────────────────────────────────────────────────

    $result = app(OfferWorkflowFacade::class)
        ->accept($this->offer, $actorId, $actorRole, ['source' => 'livewire'], request()->ip());

    if (! $result['allowed']) {
        $this->addError('offer', $result['reason']);
        return;
    }

    $this->offer   = $result['offer'];
    $this->actions = app(OfferAvailableActionsService::class)
        ->forOffer($this->offer, $actorId, $actorRole);
}
```

### Two distinct denial paths — what gets logged

| Denial source | Facade called? | `forbidden_transition_attempt` row written? |
|---|---|---|
| Role not permitted (permission check fails before facade) | No | **No** — controller rejects and returns 422 directly |
| State-machine rule violation (facade rejects the transition) | Yes | **Yes** — facade writes the row automatically |

The second path (state-machine denial) should be rare in production because the
permission check already guards against most invalid requests. It is most likely
to occur in race conditions where the offer's status changes between the UI
rendering and the action being submitted.

---

## 5. Success message handling

### Standard result shape (submit, accept, reject, withdraw, expire)

These five facade methods all return the same shape on both success and failure:

```php
[
    'allowed'     => bool,
    'offer'       => Offer,        // refreshed model (updated status on success;
                                   // unchanged on failure)
    'from_status' => string,       // e.g. 'submitted'
    'to_status'   => string,       // e.g. 'accepted'
    'reason'      => string,       // '' on success; denial message on failure
    'event_log'   => OfferEventLog,
]
```

### `counter()` result shape — distinct, no `offer` key

`OfferWorkflowFacade::counter()` (delegating to `OfferCounterService`) returns
a **different shape** from the five methods above. It has no `offer` key:

```php
// Success:
[
    'allowed'       => true,
    'parent_offer'  => Offer,      // the original offer, now in 'countered' status
    'counter_offer' => Offer,      // the newly created child offer
    'from_status'   => string,
    'to_status'     => 'countered',
    'reason'        => '',
    'event_log'     => OfferEventLog,
]

// Failure (state-machine denial):
[
    'allowed'       => false,
    'parent_offer'  => Offer,      // unchanged
    'counter_offer' => null,
    'from_status'   => string,
    'to_status'     => 'countered',
    'reason'        => string,
    'event_log'     => OfferEventLog,
]
```

### `timeline()` result shape

`OfferWorkflowFacade::timeline()` returns `array<int, array>` — **no `allowed`
key**. Each element has:

```php
[
    'offer_id'          => int,
    'parent_offer_id'   => int|null,
    'status'            => string,
    'created_at'        => string,          // 'Y-m-d H:i:s'
    'submitted_at'      => string|null,     // 'Y-m-d H:i:s'
    'event_count'       => int,
    'latest_event_type' => string|null,
    'latest_event_at'   => string|null,     // 'Y-m-d H:i:s'
]
```

Because `timeline()` does not return an `allowed` key, display failures must be
caught with try/catch rather than an `if (!$result['allowed'])` check.

### Post-action UI contract

After any **state-changing** action succeeds, the UI must:

1. **Re-compute `$actions`** using the refreshed offer model and the same actor
   identity. The key to use for the refreshed offer depends on which facade
   method was called:

   | Action | Key holding the refreshed `Offer` |
   |---|---|
   | submit, accept, reject, withdraw, expire | `$result['offer']` |
   | counter | `$result['counter_offer']` (new offer) or `$result['parent_offer']` (original, now `countered`) — use whichever the UI navigates to next |

   In Livewire: re-assign `$this->offer` and `$this->actions`.
   In an HTTP controller: redirect to the offer view so the page re-mounts with fresh flags.

2. **Show a flash / toast message** keyed to `$result['to_status']`:

   | `to_status` | Suggested message |
   |---|---|
   | `submitted` | "Offer submitted successfully." |
   | `countered` | "Counter offer sent." |
   | `accepted`  | "Offer accepted successfully." |
   | `rejected`  | "Offer rejected." |
   | `withdrawn` | "Offer withdrawn." |
   | `expired`   | "Offer marked as expired." |

3. **Redirect or refresh** the offer view so buttons, status badges, and other
   status-dependent UI all reflect the new state.

---

## 6. Audit log expectations

`OfferEventLogService::log()` is called automatically by every facade method
(both on success and denial). The UI layer has no additional logging
responsibility beyond passing `$request->ip()` and a `$metadata` array.

### What is written automatically

Every facade call that attempts a state transition writes exactly **one
immutable row** to `offer_event_logs`.

**Success event types** (one per action):

| Action | `event_type` written |
|---|---|
| submit | `offer_submitted` |
| counter | `offer_countered` |
| accept | `offer_accepted` |
| reject | `offer_rejected` |
| withdraw | `offer_withdrawn` |
| expire | `offer_expired` |

**Denial event type** (written only when the facade is called with an invalid transition):

| Scenario | `event_type` written | Facade called? |
|---|---|---|
| State-machine transition forbidden (e.g. wrong `from_status`) | `forbidden_transition_attempt` | Yes |
| Role not permitted (rejected by controller permission check) | *nothing — facade is never called* | No |

The `forbidden_transition_attempt` row is written inside the facade's underlying
services (`OfferSubmissionService`, `OfferDecisionService`, `OfferCounterService`,
`OfferExpirationService`) when `OfferStateMachineService::validateTransition()`
returns `allowed = false`. It is **not** written when the controller or Livewire
action rejects the request based on the `OfferAvailableActionsService` permission
check — in that path the facade is never called and no row is written.

### Columns written per row

| Column | Source |
|---|---|
| `offer_id` | `$offer->id` |
| `actor_id` | `$actorId` (null for system/CLI) |
| `actor_role` | `$actorRole` |
| `event_type` | As listed above |
| `from_status` | Status before the attempted transition |
| `to_status` | Intended target status |
| `metadata` | JSON — arbitrary key/value context |
| `ip_address` | `$ipAddress` (null for system/CLI) |
| `created_at` | Database timestamp at insert time |

### What the UI layer must pass

```php
$metadata   = ['source' => 'web'];   // or 'livewire', 'api', etc.
$ipAddress  = $request->ip();        // never null for human-initiated actions
```

### What `timeline()` does NOT do

`OfferWorkflowFacade::timeline()` is a **read-only** method. It does not call
`OfferEventLogService` and writes zero rows to `offer_event_logs`.

---

## 7. Testing plan

The implementation task must add the following tests. No new service-layer unit
tests are required — `OfferWorkflowFacadeTest`, `OfferAvailableActionsServiceTest`,
and `OfferPermissionServiceTest` already cover that layer.

### Feature tests (HTTP or Livewire) — one set per action

For **each of the 7 actions** (submit, counter, accept, reject, withdraw, expire,
timeline), add three test cases:

**(a) Happy path — actor role and offer status permit the action:**
- Arrange: offer in the correct status; authenticated user with a permitted role.
- Act: POST (HTTP) or call the Livewire action.
- Assert:
  - The correct facade method is called.
  - The offer's status is updated in the database to the expected `to_status`.
  - The response is a redirect with a success flash / component re-renders
    with a status-keyed message.
  - `$actions` is refreshed — buttons that are no longer available are
    disabled/hidden in the re-rendered view.
  - For `counter`: assert `parent_offer` has status `countered` and a new child
    offer row exists in the database.

**(b) Permission-denial path — actor role is not permitted:**
- Arrange: offer in a valid status; authenticated user with a role that
  `OfferPermissionService` forbids for this action (e.g. a `seller` trying to
  submit, or a `buyer` trying to accept).
- Act: POST (HTTP) or call the Livewire action.
- Assert:
  - HTTP 422 is returned (or Livewire `$errors` contains the denial message).
  - The denial message matches `$actions['reasons']['<action>']`.
  - **The facade method is NOT called** (assert via mock/spy — no facade call
    should occur).
  - **No `forbidden_transition_attempt` row is written** to `offer_event_logs`
    (the facade was never invoked, so nothing is logged automatically).
  - The offer's status is unchanged in the database.

**(c) State-machine-denial path — role is permitted but offer status forbids the transition:**
- Arrange: offer in a final or incompatible status (e.g. `accepted`); actor
  role is otherwise permitted.
- Act: POST (HTTP) or call the Livewire action (bypass the UI flag by calling
  the action directly).
- Assert:
  - The permission check passes (role is permitted).
  - The facade IS called.
  - The facade returns `allowed = false`.
  - HTTP 422 is returned (or Livewire error is added) with the
    state-machine reason.
  - A `forbidden_transition_attempt` row IS written to `offer_event_logs`.
  - The offer's status is unchanged in the database.

### Unit tests (if a new controller or Livewire class is introduced)

- Mock `OfferWorkflowFacade` and `OfferAvailableActionsService` using
  `$this->mock()` or `Mockery`.
- Assert that the controller/component:
  - Calls `OfferAvailableActionsService::forOffer()` before calling any facade
    method.
  - Does NOT call the facade when the permission check returns `can_* = false`.
  - Delegates to the correct facade method when the permission check passes.
  - Handles `allowed = false` from the facade (state-machine denial) gracefully.
  - Re-computes `$actions` using the correct refreshed offer key after success
    (`$result['offer']` for all actions except `counter`, which uses
    `$result['counter_offer']` or `$result['parent_offer']`).

### What does NOT need new tests

- Internal service logic (already covered by existing unit tests).
- `OfferStateMachineService` transition rules (already covered).
- `OfferPermissionService` role checks (already covered).

---

## 8. Safe rollout order

Implement the wiring in this sequence to minimise risk and keep each step
independently reviewable:

1. **Inject `OfferAvailableActionsService` into the offer view controller (or
   Livewire component) and compute `$actions`.**
   Pass the array to the Blade view (or assign to a public property). No UI
   change yet — the data is simply available. Verify the array shape in a test.

2. **Wire button visibility and disabled state in Blade using `can_*` flags and
   `reasons`.**
   No backend calls are added yet. This is a pure UI-only change. All buttons
   should appear/disappear/disable correctly based on the current actor's role
   and the offer's status.

3. **Wire action handlers to facade methods, one button at a time.**
   Follow this order, from lowest risk to highest:
   - **`timeline`** — read-only, no state change, no risk.
   - **`withdraw`** — low-impact mutation; reverses a buyer's own offer.
   - **`reject`** — low-impact mutation; seller/agent declines.
   - **`counter`** — medium impact; creates a child offer.
   - **`submit`** — medium impact; starts the negotiation.
   - **`accept`** — high impact; finalises the offer.
   - **`expire`** — last, and **only** via an artisan scheduled command, never
     in a human-facing route or Livewire action.

4. **Add feature tests alongside each wired button** — do not defer testing
   until all buttons are wired.

5. **Wire `expire` exclusively via a scheduled artisan command.**
   The command resolves `OfferWorkflowFacade` from the service container, passes
   `$actorId = null`, `$actorRole = 'system'`, and `$ipAddress = null`. The
   expire button must never appear in any human-facing view or be reachable via
   any web route.
