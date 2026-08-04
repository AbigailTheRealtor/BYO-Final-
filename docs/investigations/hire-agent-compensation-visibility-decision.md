# M5.5 decision item — Compensation visibility authorization model

**Status:** OPEN. Blocks the M5.5 body rebuild for any card that renders compensation.
**Raised:** M5.0b, 2026-08-04, from evidence gathered during the public-exposure audit.
**Owner decision required.** No code change is authorized by this document.

---

## Why this is open

M5.0b removed agent compensation from the shared hero because it was published to
anonymous visitors. That fix is merged and closed. It did **not** touch the page body,
which renders compensation under a different rule — and that rule has never been stated
explicitly, only implemented.

M5.5 rebuilds those body sections into cards. A rebuild cannot preserve a rule nobody has
written down, so the rule has to be defined first. The risk is not that M5.5 changes the
behaviour deliberately; it is that a rebuild silently widens or narrows it while nobody is
looking, which is exactly how the hero came to publish compensation in the first place.

## What the code does today

`resources/views/hire_landlord_agent/view.blade.php:1357`

```blade
@if (Auth::check()) {{-- broker compensation: hidden from anonymous visitors --}}
```

The gate is **authentication, not authorization**. Any logged-in user sees the
compensation block. There is no ownership test, no bid-relationship test, and no role test.

The comment states the intent precisely and the code matches it: anonymous visitors are
excluded, and nobody else is. Treat this as deliberate as far as anonymous visitors go, and
as **undefined** for every authenticated class below.

## What is confirmed, and what is not

| Claim | Status |
|---|---|
| Body compensation is hidden from anonymous visitors | **Confirmed** — gate read, and verified live on four listings across all four roles |
| Body compensation is visible to any authenticated user | **Confirmed by reading the gate** — `Auth::check()` has no further condition |
| A competing agent can see it | **Not empirically verified.** Follows from the gate, but not proven by request |
| The other three role views use the same gate | **Not verified.** Only the landlord view was read |

The last two rows must be settled before the decision is made, not after. They are cheap to
establish and are listed as prerequisites below.

## The question to answer

Define the intended visibility for each class. These are the classes the codebase already
distinguishes elsewhere — `HireAgentProposalAccess` draws exactly these lines for proposals,
and it is the natural precedent for drawing them here.

| Viewer class | Should see compensation? | Current behaviour |
|---|---|---|
| Anonymous visitor | | Hidden |
| Listing owner (the client) | | Visible |
| Agent who has submitted a proposal on this listing | | Visible |
| Agent who has **not** proposed (competing/prospective) | | Visible |
| Authenticated user unrelated to the listing | | Visible |
| Administrator | | Visible (no separate path exists) |

The row that motivated raising this: **a competing agent bidding on the same listing can
read the compensation terms.** M2 was specifically about competing-agent proposal privacy,
so the product may already have a position here that the body predates.

## Tension to resolve explicitly

Compensation is a legitimate reason for an agent to bid. Hiding it from prospective agents
may suppress bidding; showing it to competing agents may distort it. That is a product
judgement, not an engineering one, which is why this document does not recommend an answer.

Note also that the hero and the body answered this question differently for as long as both
existed. Whatever is decided should be enforced in **one** place so they cannot diverge
again — `HireAgentHeroData` proved that a second opinion in a presenter is enough to defeat
a gate in a view.

## Prerequisites before deciding

1. Verify empirically that a competing agent sees the block (mint a session for a
   non-owner agent with a proposal on another listing, request the page, diff).
2. Check whether seller, buyer and tenant views carry the same `Auth::check()` gate or a
   different one. Role symmetry is not guaranteed in this codebase.
3. Inventory exactly which fields sit inside the gated block, so "compensation" means a
   defined set of keys rather than a vague category.

## Constraints on whatever is decided

- No permission change ships inside a UI milestone. If the model changes, it ships as its
  own change with its own tests, ahead of or behind M5.5 but not inside it.
- M5.5 must reproduce today's behaviour exactly until this is closed. A rebuilt card that
  renders compensation must carry the same gate, not a reimplementation of it.
- The redesigned public experience introduces **no** new compensation surface regardless of
  the outcome here. That was settled when M5 scope was approved and is not reopened by this
  document.
