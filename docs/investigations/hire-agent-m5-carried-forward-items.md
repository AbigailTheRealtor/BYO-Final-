# Carried-forward items from Hire Agent M5.2 / M5.3 / M5.4

**Status:** OPEN, unowned. Recorded deliberately; **none of these is in scope for the
milestone that found it.**
**Recorded:** 2026-08-04, at the close of M5.3 (Quick Actions); extended at the close of M5.4
(bid CTA and sidebar action rail).

Each item below was found during M5 work, reviewed, and explicitly deferred rather than fixed in
passing. They are written down because each is the kind of thing that is obvious on the day and
invisible three milestones later.

---

## 1. VIHO section-nav — link density on desktop

**Class:** future VIHO refinement. Not a defect.

`x-viho.section-nav` scrolls horizontally rather than wrapping. That is deliberate and documented
in the component: a wrapped nav changes height as sections appear and disappear, and a sticky
element that changes height shifts the content beneath it on every page.

The consequence, seen on the landlord detail page at 1440px: with five entries in an 856px column
the last label truncates ("Refer…") and must be scrolled to. It reads as truncation rather than as
an affordance.

**Deferred deliberately, with a rule attached:**

- Evaluate link density only **after additional consumers exist**. One consumer is not enough
  evidence to retune a shared primitive — the current padding may be exactly right for a page with
  three sections or a full-width band.
- **Do not alter the padding or the wrapping behaviour solely for Hire Agent.** If the landlord
  page specifically needs more room, that is an argument about where the page puts the bar, not
  about what the primitive does.

Any change here is a design decision about the shared layer and needs its own milestone.

---

## 2. `HireAgentDirectReadOnlyReviewTest` — 10 pre-existing failures

**Class:** pre-existing, unrelated to M5. **Documented, not addressed.**

`tests/Feature/HireAgentDirectReadOnlyReviewTest.php` fails 10 of 18 tests. All ten are the
`hire-agent-direct` accept/counter POST flows, failing at:

```
$listing = $listingClass::where('user_id', $client->id)->latest()->first();
$this->assertNotNull($listing, "Listing must be created for role [{$role}] after accept POST");
```

so the POST is not creating a listing. It fails identically for **all four roles**, including
buyer, seller and tenant, and it fails before any view renders.

**Why it is not M5's:** M5 changed the landlord detail view, the shared VIHO layer and the Hire
Agent detail shell. None of those is on the path of a `hire-agent-direct` confirm POST, and a
landlord-only view change cannot stop a *buyer* listing from being created.

Confirmed green throughout M5: `tests/Feature/HireAgent` (304), `tests/Feature/Viho` (243),
`tests/Feature/Offers/OfferWorkflowReadinessTest` (10).

---

## 3. Full test suite exhausts memory

**Class:** pre-existing tooling limitation. **Documented, not addressed.**

`php artisan test` with no path argument dies partway through:

```
Fatal error: Allowed memory size of 536870912 bytes exhausted
  in app/Services/ListingImport/MlsCoverageReporter.php on line 141
```

The 512MB limit is hit by the MLS coverage reporter, unrelated to any M5 surface. The practical
consequence is that **there is no single command that runs everything**, so per-directory runs are
the working practice. Anyone relying on a full-suite run as a release gate should know it does not
currently complete.

---

## 4. `js-copy-link` is dead markup in ~10 views

**Class:** pre-existing defect, narrow fix applied only where M5.3 needed one.

The `js-copy-link` class appears in about ten views — `hire_landlord_agent`, `hire_buyer_agent`,
`hire_seller_agent`, `hire_tenant_agent`, `property_detail`, `agent_service/view`,
`buyer_criteria/view`, `landlord_auction/view` and others — and **nothing in the repository binds a
handler to it.** Every one of those Copy buttons does nothing when clicked.

Working implementations of the same idea exist in `dashboard.blade.php` and
`agent-presets/index.blade.php` (`navigator.clipboard` with an `execCommand` fallback).

M5.3 did **not** fix this. The landlord Quick Actions band ships its own working handler
(`data-hla-copy-link`), and the legacy button was left exactly as it was — suppressed along with
the rest of the sidebar share card when the redesign flag is on, and unchanged when it is off.
Fixing the other nine views is a separate, cross-cutting change.

---

## 5. Sidebar residue exposed by M5.3

**Class:** pre-existing dead markup, now more visible. **For M5.4 (sidebar).**

The landlord sidebar contains an empty control:

```blade
<button class="btn w-100 mt-0">
    <span class="bid m-0"><i class="fa-solid fa-user"></i> </span>
</button>
```

— a full-width button with an icon and no label, no handler and no destination. It predates M5.

With the redesign flag on, M5.3 suppresses the sidebar share card, which leaves this button
adjacent to the bid CTA with nothing around it, so it reads as broken rather than as filler. It was
not removed here because the sidebar is M5.4's subject and deleting a control is a UX decision, not
a cleanup.

---

## 6. Empty proposal-console card for viewers with no visible proposals

**Class:** visual residue. **CLOSED by M5.5.**

> **Closed 2026-08-04 by M5.5.** The console is now wrapped in
> `($canReviewAllProposals ?? false) || $auction->bids->isNotEmpty()`, flag-gated behind
> `HIRE_AGENT_DETAIL_REDESIGN_ENABLED`, so it exists in the DOM only for the listing owner and for
> an agent with a proposal of their own.
>
> **The stated blocker below turned out not to hold, and that is worth recording.** The note says
> the wrap could not be applied because `@php` blocks *inside* the card compute values that later
> markup *outside* it reads. Checked against the M5.4 tree before writing any code: every variable
> defined inside the card (`$landlordBaselineData`, `$getScoreColor`, `$auctionPropType`, and all
> loop-scoped ones) has **zero** references after the card closes, and the values read outside it
> — `$my_bid`, `$userHasBid`, `$agentNumberMap`, `$isListingOwner` — are all computed *before* it.
> Nothing had to move. The claim was probably true of an earlier arrangement and was carried
> forward without being re-checked; a deferred blocker is worth re-testing rather than inheriting.

**Class (original note):** visual residue, **deferred to M5.5** (proposal console).

`<div class="card higestBider">` wraps the whole proposal area and renders unconditionally. For any
viewer the access layer hands zero proposals — guest, unrelated authenticated user, agent who has
not bid — it renders as an empty ~30px card. M5.3 and M5.4 made it conspicuous by removing
everything else that used to sit around it: on a guest page the sidebar is now that empty card and
one button.

**Why M5.4 did not fix it.** The obvious guard — render the card only when the viewer can review
all proposals or has at least one visible proposal — cannot be applied by wrapping the element,
because `@php` blocks *inside* the card compute values (the match-score baseline among them) that
later markup outside it reads. Making the wrap safe means moving those computations first, which is
a change to the console, and M5.4 is explicitly the action rail only.

The correct fix belongs with the console redesign, alongside the owner-only empty state
(`No agents have submitted a bid yet.`) that is already gated on `canReviewAllProposals`.

---

## 7. Markup gates independently hide proposal data

**Class:** observation, no action. Recorded because it changes how a future test should be read.

Removing `restrictLoadedProposals()` from `LandlordAgentAuctionController::view()` — verified by
mutation during M5.4 — fails **only** the collection-level assertion. Every rendered-HTML privacy
test still passes, because the per-card `@if ($isListingOwner || $isBidOwner)` gates hide the extra
rows on their own.

That is defence in depth and it is good. It also means **HTML assertions alone cannot prove the
server-side rule holds.** `HireAgentBidCtaTest::test_the_view_is_handed_only_authorized_proposals`
asserts against the collection the view is handed, and it is the test that actually enforces
"authorization happens before data reaches the view". Anyone trimming that suite should keep it.

---

## 8. Budget visibility — a future cross-platform privacy decision

**Class:** open product decision, **deliberately not acted on.** Reviewed and settled for M5.4.

`$auction->get->budget` is printed to anonymous visitors in the bid CTA. Audited during M5.4: it is
**already public** by a wider route. `search/hire/landlord/agent/auctions` carries only the `web`
middleware and `LandlordAgentAuctionController::search()` applies no auth check — only
approved / non-draft / non-archived filters — so `search.blade.php` prints the same value for every
listing to every visitor.

**Decision: preserve the guest budget display.** Removing it from the CTA alone would produce
inconsistent privacy behaviour and would not make the budget private, because the public search
surface would continue to show it. It would look like a protection without being one.

If budgets should become private, that is **one cross-platform decision**, not a UI change: it
spans the public search page, all four role detail views, the CTA, and any API or export that reads
the same meta key. Record it here rather than solving a tenth of it in a milestone about sidebars.

---

---

## 9. Counter-terms form routes have no authorization on GET

**Class:** suspected IDOR, **outside the detail page**. Found during M5.5 discovery. **Not fixed —
a permission change does not ship inside a UI milestone.** Needs its own change and its own tests.

`LandlordCounteredTermsController::add()` and `::edit()` load an arbitrary bid by id:

```php
$pab = LandlordAgentAuctionBid::whereId($id)->first();   // add()
$pab = LandlordAgentAuctionBid::findOrFail($id);         // edit()
```

and hand it to `LandlordAgentAuctionCounterTerm`, whose `mount()` prefills **the agent's proposed
services** from that bid and **the other party's latest counter terms**. Only `store()` and
`update()` carry an `abort_unless`; the GET form renders carry none.

- Routes: `/landlord/counter-terms/{bid_id}` (`landlord.counter-terms`) and
  `/landlord/edit-counter-terms/{bid_id}` (`landlord.edit-counter-terms`) — `routes/web.php:700,702`.
- Middleware on the enclosing group: `auth` + `verified` + `noAdmin`. **No ownership test, no
  authorship test, no role test.**
- Reachable by any authenticated non-admin who can guess or enumerate a bid id.

Two things to settle, not one:

1. The missing GET authorization above.
2. The write-side check is *"the listing owner **or any agent who has bid on this listing**"* —
   broader than *"the author of **this** bid"*. Whether that is deliberate has not been
   established.

This is the same class of defect `HireAgentProposalAccess` was built to remove, one route away
from the surface it protects. The natural fix is to ask that service rather than to write a fourth
inline comparison.

---

## 10. `$leaseFeeDisplay` is never defined, so a counter-term diff always reads "Changed"

**Class:** pre-existing display defect. Found while extracting the card. **Not fixed** — M5.5 is
scoped out of counter logic, and the card body was moved verbatim.

In the counter-history block, the lease-fee "Changed" badge compares against a variable that does
not exist anywhere in the file:

```blade
@php $ctLeaseFeeChg = $ctCompositeChanged($ctLeaseFeeDisplay, $leaseFeeDisplay ?? ''); @endphp
```

`$leaseFeeDisplay` has exactly one occurrence in the view, and it is this read. The `?? ''` makes
it silent rather than fatal: the comparison is always against an empty string, so the badge marks
the landlord's broker lease fee as changed on every counter offer whether or not it changed. The
neighbouring rows (`$feeTimingDisplay`, `$renewalFeeDisplay`, `$tenantBrokerFeeDisplay`) read the
same way and want the same check.

Fixing it means deciding what the counter is supposed to be compared *against* — the original bid,
or the listing — which is a product question about the diff, not a rename.

---

## Not recorded here, on purpose

Two M5.3 questions were settled rather than deferred, and live in the code:

- **Guest "Send Message"** renders for anonymous visitors and the route bounces them to login. That
  behaviour is **pre-existing and preserved verbatim**; changing who sees the control is an
  authorization and UX decision, not a UI milestone's to make. The reasoning is in the M5.3 block
  in `resources/views/hire_landlord_agent/view.blade.php`.
- **"View Proposals" is not a Quick Action.** Proposals are listing-owner-only, and a public tile
  naming the workflow discloses that it exists to guests and competing agents regardless of whether
  the route is protected. Its own decision, not folded into a UI milestone.
