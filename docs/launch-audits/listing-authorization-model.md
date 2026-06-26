# Listing Authorization — Current Model, Assigned-Agent Evaluation & Future Direction

**Date:** 2026-06-26
**Status:** Evaluation + recommendation. **No code change is proposed for this launch.** Documents a product decision and the intended long-term direction.
**Related:** `completed-work-inventory-and-remaining-work.md` (BYO C1), `bidyouroffer-remediation-plan.md` (C1), `ResolvesOwnedAuction` trait.

---

## 0. Product framing (owner directive)

> The current **owner-only** authorization model reflects the **existing product implementation and launch scope**. It is **not** to be interpreted as the long-term product architecture. Future work is expected to evolve toward a **Listing Participants / Shared Listing Management** model.

Everything below is written against that framing: owner-only is shipped *because it is what the product does today and is safe to launch*, **not** because it is the intended end state. The IDOR guarantee carries forward into the participant model unchanged; the access *scope* is what expands. Do not harden owner-only into a permanent assumption anywhere in the codebase — keep the `ResolvesOwnedAuction` trait seam and the Seller/Landlord `$isAssigned` branch (§3–§5) as the designed extension points.

---

## 1. Question evaluated

The BidYourOffer C1 fix introduced owner-scoped authorization on the four Offer-Listing edit components via the `ResolvesOwnedAuction` trait. The Seller/Landlord call sites pass `null` for the trait's `$assignedListingType` parameter, making all four roles **owner-only** at `mount()`/`hydrate()`.

The BidYourOffer remediation plan (C1 §8) had flagged a regression risk: *"Legitimate assigned agents editing a client's listing must still pass — preserve the `$isAssigned` branch."*

**Does the current owner-only implementation unintentionally block a legitimate assigned-agent workflow?**

---

## 2. Finding: No live workflow is blocked. Owner-only is correct and safe to launch.

Verified by reading the code:

1. **The agent's own listings are owner-owned.** Agents (`user_type='agent'`) can themselves create offer-listings. Their management hub — `AgentController::offerListings()` (`app/Http/Controllers/AgentController.php:1356`) — queries every role model with **`where('user_id', Auth::id())`**, and the hub's `edit_route` (`resources/views/agent/offer-listings.blade.php:120`) points only at those rows. An agent editing via this hub **is the owner**, so the owner-only guard passes. ✅
2. **Two-persona model.** A consumer owns all their listings under one `user_id`; an agent is a separate account. The edit components' `hydrate()` carries the explicit note *"Agents never write consumer listings via this component (two-persona model)."* There is **no wired UI path** that sends a hired (non-owner) agent to another user's `/offer-listing/{role}/edit/{id}`.
3. **The pre-existing `$isAssigned` allowance was view-only.** It lives solely in Seller/Landlord `render()` (`SellerOfferListingEdit.php:1617`, `LandlordOfferListingEdit.php:1625`) and gated **only `$canViewLocationDnaPanel`** — i.e. whether the read-only Location DNA panel renders. It never granted write access. The predicate: an `AcceptedBidSummary` row with `listing_type ∈ {seller_agent, landlord_agent}`, matching `listing_id`, and `agent_user_id = Auth::id()` (column confirmed: `2026_01_04_060614_create_accepted_bid_summaries_table.php:22`).
4. **Buyer/Tenant never had an assigned-agent concept** in their edit components (zero `AcceptedBidSummary`/`isAssigned` references). Owner-only is their only-ever behavior — no regression is even possible.

**Conclusion:** owner-only matches the product as it exists today. The IDOR fix should ship as-is.

---

## 3. The one artifact (not a regression): a now-unreachable render branch

After the fix, `mount()`/`hydrate()` abort 403 for any non-owner **before** `render()` runs. So the `$isAssigned` branch in Seller/Landlord `render()` (the Location-DNA-panel allowance) is now **unreachable for a non-owner** — effectively dead, but harmless.

- **No action required for launch.** It does not weaken security and does not break the owner path (owners still hit `$isOwner = true`).
- **Recommended: leave it in place** as a documented seed of the future participant model (see §5), optionally with a one-line comment noting it is currently gated upstream by `assertCanManageAuction`. Do **not** delete it — it encodes intended business behavior and is the natural extension point.

---

## 4. Smallest, safest change *if/when* assigned-agent management is desired

**Not for this launch.** Documented so the path is known and pre-vetted.

The `ResolvesOwnedAuction` trait was deliberately built with the seam already in place — `assertCanManageAuction(string $modelClass, $id, ?string $assignedListingType)`. To extend Seller/Landlord from owner-only to "owner **or** assigned agent," the change is to pass the listing type instead of `null` at the two guard sites per component:

```php
// Seller — mount() and hydrate()
$this->assertCanManageAuction(SellerAgentAuctionModel::class, $id, 'seller_agent');
// Landlord — mount() and hydrate()
$this->assertCanManageAuction(HirelandLordAgentAuction::class, $id, 'landlord_agent');
```

Why this is the smallest-safe option:
- **Reuses the exact predicate already trusted in `render()`** — the `AcceptedBidSummary` assigned-agent check. No new authorization logic, no new model, no new table.
- **IDOR protection is fully preserved.** The assigned-agent branch is itself a scoped ownership predicate (`agent_user_id = Auth::id()` AND `listing_id = $id`); it is not a blanket allow. Unauthorized users still get 403.
- **Four lines, two files** (`SellerOfferListingEdit`, `LandlordOfferListingEdit`); guard already runs on every write entrypoint.

**Two caveats that make this a product decision, not a pure bug-fix (hence: document + decide, do not auto-apply):**
1. It **expands** the assigned agent from *view the LDNA panel* to *full edit/submit/publish* of that listing, because the trait guards all write methods uniformly. That may be broader than "manage where appropriate" — scope the intended agent capabilities first.
2. **Buyer/Tenant have no assigned-agent predicate** (no `*_agent` `AcceptedBidSummary` semantics wired into those edit components). They would remain owner-only until the participant model (§5) defines a buyer-agent / tenant-agent assignment. Applying the §4 change to Seller/Landlord only would leave a deliberate, documented role asymmetry.

---

## 5. Long-term direction: Listing Participants / Shared Listing Management

The long-term architecture is **not** permanent owner-only. It should evolve toward an explicit **Listing Participants** model for shared listing management, with these business rules:

- The listing **owner** always has full access.
- A **properly assigned/hired agent** can also manage the listing where appropriate.
- **Unauthorized users must never** have access (the IDOR guarantee is non-negotiable and carries forward).

Intended participant pairings (each a listing + its assigned agent):

| Listing | Assigned participant |
|---|---|
| Seller | Listing agent |
| Buyer | Buyer agent |
| Landlord | Landlord's agent |
| Tenant | Tenant's agent |

Eventually extensible to additional participant roles — **explicitly out of scope now.**

**Design seam already in place:** `ResolvesOwnedAuction` centralizes object-level authorization for all four edit components in one place. A future participant system replaces the ad-hoc `AcceptedBidSummary` lookups with a first-class participants concept (e.g. a `listing_participants` table keyed by `(listing_type, listing_id, user_id, role, capabilities)`), resolved behind the **same trait method**. Consumers do not change; only the predicate inside the trait does. This is the migration target — not a launch task.

### Guardrails for this launch
- **Do not** build a participant system now.
- **Do not** redesign authorization now.
- **Preserve** the current IDOR fixes exactly as committed.
- Treat the trait's `$assignedListingType` parameter and the Seller/Landlord `render()` `$isAssigned` branch as the **designed extension points** — keep them; build on them later.

---

## 6. Recommendation summary

| | |
|---|---|
| **Ship owner-only for launch?** | **Yes.** No live assigned-agent workflow is blocked; agents already own the listings they edit. |
| **Any launch-blocking change?** | **None.** |
| **Cleanup (optional, low priority)** | Add a one-line comment by the Seller/Landlord `render()` `$isAssigned` branch noting it is gated upstream by `assertCanManageAuction`. Do not delete it. |
| **Future change, when shared management is scoped** | §4 — pass the `seller_agent`/`landlord_agent` listing type into the trait guard (Seller/Landlord); define buyer/tenant assignment before extending those. |
| **Long-term** | §5 — first-class Listing Participants model behind the same trait seam. |
