# Tech debt — retire the legacy ZIP mirror; Location DNA becomes the only geography source

**Status:** OPEN, unowned. **Explicitly out of scope for Phase 1c slice 1 (`hire_buyer`).**
**Found:** 2026-08-06, while wiring the Criteria geography cascade into Hire Buyer.
**Severity:** medium. Nothing is broken today, but geography is stored in three places that can
disagree, and the cascade makes a fourth writer. Divergence is silent by construction — no
assertion, log or constraint compares the copies.

---

## The decision this records

Location DNA is to become **the single source of truth for geography**: states, counties, cities
and ZIPs. Legacy mirrors that duplicate any of it are to be retired, not extended.

The cascade wiring deliberately did **not** start that retirement. This document exists so the
decision is written down at the moment it was taken, rather than inferred later from a constant
whose reasoning is no longer obvious.

## What is duplicated today

The **search-criteria** ZIP list (which ZIPs a buyer/tenant wants, or a seller/landlord's search
areas) is currently stored in up to three places per listing:

| # | Location | Written by | Nature |
|---|---|---|---|
| 1 | `location_dna_preferences` blob, `zipCodes` key | `HasSearchAreas::saveSearchAreas()` | **canonical** |
| 2 | `zipCodes` meta (JSON string array) | 11 Livewire components, directly | legacy mirror |
| 3 | `zip_code` meta (single string) | 7 components, seeded as `$this->zipCodes[0] ?? ''` | derived-from-mirror |

Copy 3 is the worst of the three: it is a *lossy projection of a mirror*, so it can be stale
against copy 2, which can itself be stale against copy 1.

**Not in scope:** `property_zip` / the subject property's own address ZIP. That is a different
fact — where the property *is*, not where the client is *searching* — and it legitimately lives on
the listing. Do not fold it into this cleanup.

### The 11 components writing the `zipCodes` mirror

All four roles participate:

```
Buyer / Tenant   TenantAgentAuction, TenantAgentAuctionEdit,
                 TenantOfferListing, TenantOfferListingEdit
Seller           SellerAgentAuction, SellerOfferListing, SellerOfferListingEdit
Landlord         LandLordAgentAuction, LandLordAgentAuctionEdit,
                 LandlordOfferListing, LandlordOfferListingEdit
```

Each also declares its own `public $zipCodes` property, so the duplication exists in component
state as well as in storage.

## The specific inconsistency the cascade exposed

`HasGeographyCascade` carries an opt-in list:

```php
private const ZIP_MIRROR_WORKFLOWS = ['hire_tenant'];
```

Only `hire_tenant` gets the cascade's ZIP selection mirrored back into the discrete `$zipCodes`
property. The reasoning inherited from the upstream branch was that "the Buyer family has never
written a `zipCodes` mirror" — and for `HireBuyerAgent\BuyerAgentAuction{,Edit}` that is true
today: they contain **zero** references to `zipCodes`.

But it is not true of the surfaces that actually serve Hire Buyer. `TenantAgentAuction` and
`TenantAgentAuctionEdit` are the shared catch-all components, they serve `user_type=buyer`, and
both write `saveMeta('zipCodes', …)` **ungated by `user_type`** — so the mirror is written for
every role they serve, buyer included.

The result, once `hire_buyer` is enabled: of the four wired surfaces, two write a ZIP mirror the
cascade does not maintain, and two write none at all. The canonical blob is correct in every case;
the mirror simply lags on the two that keep one.

**This is not data loss, and it is why it was left alone.** It is a stale secondary copy, and
fixing it properly means deleting the copy rather than adding a fifth writer to keep it fresh.

## Why it was not fixed in slice 1

Adding `hire_buyer` to `ZIP_MIRROR_WORKFLOWS` would have been a two-word change that made the
symptom disappear. It was rejected because it entrenches the mirror as architecture: the constant
would then encode "which workflows we have remembered to keep in sync", which is precisely the
shape of bug this cleanup is meant to end. `ZIP_MIRROR_WORKFLOWS` is a **transitional
accommodation, not a design**, and should be deleted by the work below rather than grown.

## Precedent: two consumers already treat the blob as authoritative

The read side has started this migration, and its pattern is the one to generalise:

- `TenantOfferListingCriteriaLoader` — the blob is authoritative whenever it carries the key;
  legacy `zipCodes` is consulted *only* when the blob cannot speak (no blob, unparseable, or the
  key absent).
- `LocationMatchAuctionExtractor` — merges `client_areas_of_interest` and legacy `zipCodes`
  alongside the blob.

So the retirement is not a green-field design question. It is: make every reader look like the
first one, then delete the fallback, then delete the writers.

## Goal

Retire legacy ZIP mirror synchronisation.

## Acceptance criteria

- [ ] The Location DNA payload is the only source of truth for states, counties, cities and ZIPs.
- [ ] No workflow writes ZIP data outside Location DNA.
- [ ] No component maintains duplicate ZIP state (`public $zipCodes`, `zip_code` derived from it).
- [ ] Matching and search logic reads geography from Location DNA only.
- [ ] Buyer, Tenant, Seller and Landlord workflows all use the same geography system.
- [ ] `ZIP_MIRROR_WORKFLOWS` is deleted from `HasGeographyCascade`.

## Sequencing constraint

**Do not start this until Buyer/Tenant cascade wiring is complete** (slices 1–4: `hire_buyer`,
`hire_tenant`, `create_tenant`, `create_buyer`). Two reasons:

1. Seller and Landlord have no geography cascade and no plan to get one in Phase 1c, yet they
   write the mirror from four components. Retiring the mirror before they have a canonical writer
   would remove their only storage for search-area ZIPs.
2. The acceptance criterion "all four roles use the same geography system" cannot be met while the
   cascade covers only two of them. Whoever picks this up should expect to answer the Seller and
   Landlord question first — it is the real blocker, not the deletion itself.

## Suggested order, unowned

1. **Audit the readers.** Enumerate everything reading the `zipCodes` and `zip_code` metas —
   matching, Ask AI, filtering, search, public display, Stellar/Bridge criteria loaders. The write
   sites are known and listed above; the read sites are not yet fully enumerated and are what
   determines whether deletion is safe.
2. **Make every reader blob-first with a legacy fallback**, following
   `TenantOfferListingCriteriaLoader`. Ship and observe.
3. **Backfill** any listing whose blob lacks ZIPs the mirror still holds, so the fallback stops
   being load-bearing. Report the count; a silent zero is indistinguishable from a broken query.
4. **Remove the fallback**, leaving readers blob-only.
5. **Delete the writers** and the `public $zipCodes` / `zip_code` component state.
6. **Delete `ZIP_MIRROR_WORKFLOWS`** and its branch in `HasGeographyCascade`.

Steps 3–5 are separately revertable and should not be one commit.

## Constraints

- Each step must be independently shippable; there is no acceptable window in which readers and
  writers disagree about which copy wins.
- Any backfill must be idempotent and must report what it changed — the same rule
  `census:import-geography` follows.
- Seller and Landlord must not silently lose stored ZIPs. If the answer for them is "keep the
  mirror until they get a cascade", that is a legitimate outcome, but it must be written down
  here rather than discovered by a user.
