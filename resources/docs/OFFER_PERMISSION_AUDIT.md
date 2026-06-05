# Offer Permission Audit — Landlord & Tenant Offer Submission

**Audit date:** 2026-06-05  
**Scope:** `OfferPermissionService::canSubmit()` as it applies to landlord and tenant users  
**Status:** Findings documented; no code changes made

---

## 1. Actual `role` Column Values for Landlord and Tenant Accounts

### Source of truth: `users.user_type`

The `users` table has a **`user_type` enum column** — there is no `role` column on the table.

**Permitted `user_type` values** (from `2014_10_12_000000_create_users_table.php` and the subsequent check-constraint migrations):

| `user_type` value | Account kind |
|---|---|
| `admin` | Platform administrator |
| `buyer` | Property buyer |
| `seller` | Property seller |
| `buyer_agent` | Buyer's agent |
| `seller_agent` | Seller's agent |
| `agent` | Generic agent |
| `tenant` | Rental tenant |

**`landlord` is not a `user_type` value.** There is no landlord account type in the `users` table. Users who interact with landlord offer listings are expected to hold one of the existing types (most commonly `buyer`, `seller`, or `tenant`). This is confirmed by the test suite:

- `LandlordOfferEntryTest` creates its acting user as `user_type = 'buyer'`.
- `TenantOfferEntryTest` creates its acting user as `user_type = 'seller'`.

The `UserSeeder` defines one `tenant` account (`tenant@exp.com`, `user_type = 'tenant'`) and no landlord account at all.

### The `Offer.role` field is separate

The `role` column that lives on the `offers` table (`landlord`, `tenant`, `buyer`, `seller`) captures the **listing-context perspective** — the role the offer plays in the workflow. It is set by the hidden `<input name="role" value="landlord">` / `<input name="role" value="tenant">` inputs in the view Blade templates. It is **not** the actor's user account type.

---

## 2. Exact Logic in `OfferPermissionService::canSubmit()`

File: `app/Services/Offers/OfferPermissionService.php`, method `canSubmit()` (lines 14–32):

```php
public function canSubmit(Offer $offer, ?int $actorId, string $actorRole): array
{
    $action = 'submit';
    $status = $offer->status;

    // Gate 1 — offer must not be in a final state
    if ($this->isFinalStatus($status)) {
        return ['allowed' => false, 'action' => $action,
                'reason' => "Cannot submit: offer is in a final state '{$status}'."];
    }

    // Gate 2 — offer must be in 'draft' status
    if ($status !== 'draft') {
        return ['allowed' => false, 'action' => $action,
                'reason' => "Cannot submit: offer status is '{$status}', expected 'draft'."];
    }

    // Gate 3 — actor role must be 'buyer' or 'system'
    if (!in_array($actorRole, ['buyer', 'system'], true)) {
        return ['allowed' => false, 'action' => $action,
                'reason' => "Cannot submit: actor role '{$actorRole}' is not permitted for this action."];
    }

    return ['allowed' => true, 'action' => $action, 'reason' => ''];
}
```

**Gate 3 is the gating concern for this audit.** Allowed `$actorRole` values for submission: `buyer` and `system` only.

---

## 3. How `$actorRole` Is Derived in the Controller

File: `app/Http/Controllers/OfferController.php`, method `submit()` (line 59):

```php
$actorRole = Auth::user()->role ?? 'buyer';
```

### Critical finding: `Auth::user()->role` always returns `null`

The `User` model (`app/Models/User.php`) has **no `role` column**, **no `getRoleAttribute()` accessor**, and `role` does not appear in `$fillable` or anywhere in the model. The only identity column is `user_type`.

When Laravel's Eloquent resolves an undefined attribute (`->role`) it returns `null`. Therefore, the null-coalescing fallback **always activates**:

```
$actorRole = null ?? 'buyer'  →  'buyer'
```

**Every authenticated user — regardless of their actual `user_type` — receives `$actorRole = 'buyer'` when the `submit` endpoint is called.**

Because `'buyer'` is in the `canSubmit()` allowed list, **Gate 3 always passes** for any logged-in user, and submission is never blocked by the role check.

---

## 4. Verdict

### Does a real submission failure exist for landlord or tenant users?

**No — not in the current codebase.**

A landlord-perspective offer (stored with `offers.role = 'landlord'`) and a tenant-perspective offer (`offers.role = 'tenant'`) are both submitted by users whose `$actorRole` resolves to `'buyer'` via the null-fallback on `Auth::user()->role`. `canSubmit()` then permits the action.

### Is there a latent architectural problem?

**Yes.** The current behavior is correct only by accident. The controller reads a non-existent attribute and silently falls back to `'buyer'`. There are two distinct "role" concepts in play that the controller conflates:

| Concept | Column | Location | Example values |
|---|---|---|---|
| User account type | `users.user_type` | Database row | `buyer`, `seller`, `tenant`, `agent` |
| Offer listing-context role | `offers.role` | Offer record | `buyer`, `seller`, `landlord`, `tenant` |

The controller attempts to read the user account type (to govern who may act), but accidentally reads an undefined attribute and relies on the `'buyer'` default. If the `User` model ever gains a proper `role` accessor that returns `user_type` (e.g., during a refactor), users with `user_type = 'tenant'`, `user_type = 'seller'`, `user_type = 'agent'`, etc. would immediately fail Gate 3 in `canSubmit()`, because none of those values are in the allowed list `['buyer', 'system']`.

---

## 5. Recommended Fix (Pending Confirmation — Not Yet Implemented)

Two options are available. A decision should be made before any refactor touches `Auth::user()->role` or adds a `role` accessor to the `User` model.

### Option A — Expand `canSubmit()` allowed roles to reflect real user types

If the intent is that any non-agent, non-admin user may submit an offer (i.e., `buyer`, `seller`, `tenant`, and by extension anyone using a landlord offer listing), expand the Gate 3 check:

```php
// Proposed change in OfferPermissionService::canSubmit()
if (!in_array($actorRole, ['buyer', 'seller', 'tenant', 'system'], true)) {
    return ['allowed' => false, ...];
}
```

Pair this with fixing the controller to use the actual `user_type`:

```php
// Proposed change in OfferController::submit()
$actorRole = Auth::user()->user_type ?? 'buyer';
```

**Rationale:** `user_type` is the real identity column. Using it explicitly removes reliance on the accidental null-fallback and makes the permission intent auditable.

### Option B — Document the `'buyer'` fallback as intentional

If the design decision is that all non-system actors are treated uniformly as `'buyer'` for offer submission purposes (because the offer's listing-context role already records the perspective via `offers.role`), then:

1. Add a code comment in `OfferController::submit()` explaining the intentional fallback.
2. Consider renaming `$actorRole` to `$actorPermissionGroup` to avoid confusion with `user_type`.
3. Do not add a `role` accessor to the `User` model without updating this logic first.

**Rationale:** The `offers.role` field already captures the landlord/tenant context. The submission permission only needs to distinguish "platform user submitting" from "system acting automatically" — which `buyer` vs. `system` already covers adequately.

---

## 6. Related Entries in the QA Report

See `resources/docs/OFFER_SYSTEM_QA_REPORT.md`, lines 510–522 (Observation 3), which flagged this question and recommended clarification. This audit provides that clarification: no active failure exists, but the `role` attribute read is relying on an undefined attribute and a null fallback — a fragile pattern that should be resolved before any User model refactoring.
