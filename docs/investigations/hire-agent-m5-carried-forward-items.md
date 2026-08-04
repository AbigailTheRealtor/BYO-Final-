# Carried-forward items from Hire Agent M5.2 / M5.3

**Status:** OPEN, unowned. Recorded deliberately; **none of these is in scope for M5.3.**
**Recorded:** 2026-08-04, at the close of M5.3 (Quick Actions).

Each item below was found during M5 work, reviewed, and explicitly deferred rather than fixed in
passing. They are written down because all four are the kind of thing that is obvious on the day
and invisible three milestones later.

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

## Not recorded here, on purpose

Two M5.3 questions were settled rather than deferred, and live in the code:

- **Guest "Send Message"** renders for anonymous visitors and the route bounces them to login. That
  behaviour is **pre-existing and preserved verbatim**; changing who sees the control is an
  authorization and UX decision, not a UI milestone's to make. The reasoning is in the M5.3 block
  in `resources/views/hire_landlord_agent/view.blade.php`.
- **"View Proposals" is not a Quick Action.** Proposals are listing-owner-only, and a public tile
  naming the workflow discloses that it exists to guests and competing agents regardless of whether
  the route is protected. Its own decision, not folded into a UI milestone.
