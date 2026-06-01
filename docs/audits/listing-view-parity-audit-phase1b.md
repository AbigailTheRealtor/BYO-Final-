# Listing View Parity Audit — Phase 1B (Visual & Content)

**Date:** 2026-06-01
**Auditor:** Agent Task #1638
**Status:** BASELINE LOCKED — no further changes without a new audit task
**Predecessor:** `docs/audits/listing-view-parity-audit-phase1.md` (Phase 1A functional gaps)
**Phase 1A implementation:** Task #1624 (completed)

---

## Purpose

Phase 1A (Task #1623 audit → Task #1624 implementation) fixed critical visitor
interaction issues: contact/inquiry modals, Schedule Showing flows, sidebar engagement
CTAs, smooth-scroll offsets, and mobile bar fixes.

Phase 1B audits the remaining ten dimensions covering **visual consistency, empty-state
guards, CTA placement, mobile layout, sidebar parity, sticky-nav completeness, data
display gaps, and cross-role section parity.** No code changes are made by this audit;
it exists to lock in the remaining baseline before Phase 1B implementation begins.

---

## Listing-Type Rules (applied throughout)

- **Seller** and **Landlord** are **property listings** — may include photo galleries,
  hero carousels, Photos & Tours, virtual tours, property documents, Schedule Showing
  CTAs, disclosures, and all property-media features.
- **Buyer** and **Tenant** are **criteria/wanted listings** — must NOT include any
  property-media features. All such items are marked **Intentional N/A** below.

---

## Files Audited

| Role | View File | Lines |
|---|---|---|
| Seller (primary reference) | `resources/views/offer-listing/seller/view.blade.php` | 2 540 |
| Buyer | `resources/views/offer-listing/buyer/view.blade.php` | 1 352 |
| Landlord | `resources/views/offer-listing/landlord/view.blade.php` | 1 337 |
| Tenant | `resources/views/offer-listing/tenant/view.blade.php` | 1 400 |

---

## Dimension Definitions

| # | Dimension |
|---|---|
| DIM-1 | Remaining visual inconsistencies affecting user clarity |
| DIM-2 | Missing applicable display sections by listing type |
| DIM-3 | Duplicate, broken, or confusing CTA placement |
| DIM-4 | Mobile layout issues |
| DIM-5 | Sidebar consistency |
| DIM-6 | Sticky nav / section visibility issues |
| DIM-7 | Empty sections rendering when no data exists |
| DIM-8 | Data saved in create/edit flow but not displayed on public detail page |
| DIM-9 | Seller / Landlord property display parity |
| DIM-10 | Buyer / Tenant criteria display parity |

---

## PASS / FAIL / N/A Matrix

> **Legend:**
> - **PASS** — no actionable gap found
> - **FAIL** — gap exists, fix required
> - **N/A** — intentionally not applicable to this listing type (documented below)
> - **N/A†** — covered by an existing tracked task (do not duplicate)
> - *Italics* — partially passing; detail in findings section

| Dimension | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| **DIM-1** Visual inconsistencies | **FAIL** — phone format bug; duplicate `id="section-overview"` | PASS | **FAIL** — modal CSS not scoped to `.lol-view-page` | **FAIL** — question modal uses hardcoded inline header style |
| **DIM-2** Missing display sections | **FAIL** — Broker Compensation always renders empty card | **FAIL** — no Additional Details section; no catch-all fallback | **FAIL** — `#section-additional` has no nav tab; missing "Why This Property Stands Out" callout | PASS — all sections present and guarded; catch-all fallback present |
| **DIM-3** CTA placement | **FAIL** — sidebar lacks "Back to Search" link | **FAIL** — primary hero CTA opens a Coming Soon dead-end modal | PASS | *PARTIAL* — sidebar primary CTA conditionally absent when no email stored |
| **DIM-4** Mobile layout | **FAIL** — mobile bar has no "Back to Search" | PASS | PASS | PASS |
| **DIM-5** Sidebar consistency | **FAIL** — no "Back to Search"; Edit link not in sidebar | PASS | **FAIL** — Edit link absent from desktop sidebar | *PARTIAL* — primary CTA absent when no email |
| **DIM-6** Sticky nav / section visibility | **FAIL** — always rendered (no guard); no active-section scroll highlight | **FAIL** — no active-section scroll highlight | **FAIL** — `#section-additional` has no nav tab; no active-section highlight | PASS — all sections guarded; active-section highlight implemented |
| **DIM-7** Empty sections rendering | **FAIL** — Broker Compensation card renders unconditionally | **FAIL** — Views/Saves activity strip always renders | PASS | PASS |
| **DIM-8** Data saved but not displayed | PASS — extensive named-section coverage | **FAIL** — `additional_details` field likely collected but has no display section | PASS | PASS — catch-all fallback handles remaining keys |
| **DIM-9** Seller/Landlord parity | PASS (reference) | N/A — criteria listing | **FAIL** — missing "Why This Property Stands Out" callout; Financial Details, Tax/Legal/HOA, Documents & Disclosures absent | N/A — criteria listing |
| **DIM-10** Buyer/Tenant parity | N/A — property listing | **FAIL** — missing Additional Details section, catch-all fallback, Location as own section, video in Contact | N/A — property listing | PASS (reference for criteria type) |

---

## Intentional N/A Items (must not be built)

The following items are confirmed **intentional N/A** for Buyer and Tenant because they
are criteria/wanted listings, not property listings:

| Feature | Buyer | Tenant |
|---|---|---|
| Hero photo carousel | N/A — no property photos | N/A — no property photos |
| Photos & Tours section | N/A — no property photos | N/A — no property photos |
| Lightbox / thumbnail gallery | N/A | N/A |
| Virtual tour / video embed (property) | N/A | N/A |
| Schedule Showing CTA | N/A — no property to show | N/A — no property to show |
| Property Documents section | N/A — no property docs | N/A — no property docs |
| Documents & Disclosures section | N/A | N/A |
| Tax / Legal / HOA section | N/A | N/A |
| Financial Details (property income) | N/A | N/A |

---

## Phase 1A Status Check

The following gaps were fixed by Phase 1A implementation (Task #1624). Confirmed
by reading the current view files:

| Phase 1A Gap | Verified Status |
|---|---|
| GAP-4: Tenant contact section CTA row absent | ✅ Fixed — `tcl-contact-cta-row` now present (~line 996) |
| GAP-5: Landlord inquiry modal had no form submission | ✅ Fixed — `lolQuestionModal` with POST form present |
| GAP-6: Landlord sidebar missing Ask Question + Showing | ✅ Fixed — both buttons now in sidebar and contact CTA row |
| GAP-7: Tenant sidebar primary CTA was "Back to Search" | ✅ Fixed — Contact Tenant / Ask Question now primary |
| GAP-8: Landlord mobile bar highlight absent | ✅ Fixed — `lol-mobile-primary` applied |
| GAP-9: Landlord smooth-scroll used bare `scrollIntoView()` | ✅ Fixed — uses `- 82` px offset |
| GAP-10: Tenant Auction Time field missing | ✅ Fixed — present in Overview section |
| GAP-11: BP countdown timer lacked model-column fallback | ✅ Fixed — Seller/Buyer/Landlord timers updated |

> **New finding from Phase 1A review:** The Phase 1A matrix marked Tenant contact section
> CTA row as FAIL. It is now PASS. That Phase 1A entry should be considered resolved.

---

## Gap Details

### DIM-1 · Visual Inconsistencies

#### 1-A · Seller: Phone Format Applied to Unstripped String
**Severity:** High | **Complexity:** Low

Seller formats the contact phone by slicing directly from the raw stored string:

```php
// seller/view.blade.php ~line 2139 (buggy)
$phone = $str('phone_number');
if ($phone && strlen(preg_replace('/\D/', '', $phone)) === 10) {
    $phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
}
```

The length guard strips non-digits, but `substr()` runs on the original `$phone` which
may already contain parens or dashes (e.g. `(813) 555-1234` stored from a pre-formatted
input). Result: `((81) ) 5` instead of `(813) 555-1234`.

Landlord (~line 1008) and Tenant (~line 952) extract digits to a separate variable first:

```php
// Landlord / Tenant (correct)
$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen($phoneDigits) === 10) {
    $phone = '(' . substr($phoneDigits, 0, 3) . ') ' . substr($phoneDigits, 3, 3) . '-' . substr($phoneDigits, 6);
}
```

**Fix:** Apply `substr()` to `$phoneDigits`, not `$phone`.
**Tracked by:** Task #1642 (proposed follow-up)

---

#### 1-B · Seller: Duplicate `id="section-overview"` on Two Cards
**Severity:** Medium | **Complexity:** Low

Two distinct section cards share `id="section-overview"`:
- "Property Description" card (added in Phase 1A, ~line 1 289)
- "Listing Details" card (pre-existing, ~line 1 333)

Duplicate IDs break smooth-scroll anchor targeting (`querySelector` returns only the first
match) and will break active-section highlight logic if backported (DIM-6).

**Fix:** Rename the Property Description card to `id="section-description"` and add a
corresponding nav tab.
**Tracked by:** Task #1643 (proposed follow-up)

---

#### 1-C · Tenant: Question Modal Header Uses Inline Gradient Style
**Severity:** Low | **Complexity:** Low

The Tenant question modal header uses a hardcoded inline style:

```html
<div class="modal-header" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;">
```

All Seller (`sol-modal-header`), Buyer (`bol-modal-header`), and Landlord
(`lol-modal-header`) modals use CSS classes. The Tenant class prefix (`tcl-`) is used
elsewhere in the same file but not for this modal header. Inline styles cannot be
overridden from a stylesheet without `!important`.

**Fix:** Add `.tcl-modal-header` to the Tenant `<style>` block and replace the inline style.
**Tracked by:** Task #1643 (proposed follow-up)

---

#### 1-D · Landlord: Modal Header CSS Not Scoped to `.lol-view-page`
**Severity:** Low | **Complexity:** Low

The `.lol-modal-header` rule in Landlord's pushed `<style>` block is declared at global
scope. Seller (`sol-`) and Buyer (`bol-`) scope all component rules under `.sol-view-page`
and `.bol-view-page` wrappers respectively, preventing style bleed. The Landlord and
Tenant views namespace most rules but the modal header selector is bare.

**Fix:** Scope to `.lol-view-page .lol-modal-header { … }`.
**Tracked by:** Task #1643 (proposed follow-up)

---

### DIM-2 · Missing Applicable Display Sections

#### 2-A · Seller: Broker Compensation Card Has No `@if` Guard
**Severity:** Medium | **Complexity:** Low

The Seller "Broker Compensation & Agency Agreement" card renders unconditionally.
If a listing has no broker compensation data, an empty card body is visible to all
public visitors. Landlord's Tenant Broker Compensation is correctly gated
(`@if($navHasCompensation)`). Seller Sale Terms uses the correct `count()` guard pattern.

**Fix:** Collect compensation fields into a filtered array and wrap the card in
`@if(count($compensationFields))`.
**Tracked by:** Task #1643 (proposed follow-up)

---

#### 2-B · Landlord: `#section-additional` Has No Sticky Nav Tab
**Severity:** Medium | **Complexity:** Low

The "Additional Details" section card exists in content (`id="section-additional"`,
~line 1 045) behind `@if($hasAddlDetails)`, but the sticky nav has no matching tab.
Users cannot jump to this section via the nav bar and see no active-highlight when
scrolling past it. All other conditional Landlord sections have matching nav tabs.

**Fix:** Add a nav tab for `#section-additional` inside the same `@if($hasAddlDetails)`
guard, matching the pattern for other conditional Landlord nav tabs.
**Tracked by:** Task #1643 (proposed follow-up)

---

#### 2-C · Buyer: No "Additional Details" Display Section
**Severity:** Medium | **Complexity:** Low

The Buyer criteria form likely collects an `additional_details` meta key (the Tenant form
does, and both wizards share similar structures). The Buyer view page has no card for
this key, meaning any free-text notes entered by a buyer are silently discarded on the
public view. Tenant displays the field in `#section-additional`.

**Fix:** Verify whether `additional_details` is collected in the Buyer criteria wizard.
If yes, add a guarded `#section-additional` card to `buyer/view.blade.php`.
**Tracked by:** Task #1643 (proposed follow-up)

---

#### 2-D · Buyer and Landlord: No "Additional Information" Catch-All Fallback
**Severity:** Low | **Complexity:** Medium

Tenant implements a catch-all "Additional Information" section (`id="section-remaining"`)
that displays any populated meta key not explicitly covered by a named section, using a
comprehensive `$knownKeys` exclusion list and auto-labeling via `snake_case → Title Case`.
This ensures no submitted data is silently swallowed.

Buyer and Landlord have no equivalent. Seller also lacks it but has extensive named-section
coverage. This gap is lower priority for Seller given coverage depth.

**Fix:** Port the Tenant catch-all pattern to Buyer and Landlord, scoped to each role's
meta schema.

---

#### 2-E · Landlord: "Why This Property Stands Out" Callout Absent
**Severity:** Low | **Complexity:** Low

Seller renders a highlighted "Why This Property Stands Out" promotional callout in the
Property Description section when the relevant meta key is populated. This is a
property-listing feature (not applicable to Buyer/Tenant criteria), so Landlord — also
a property listing — should have a corresponding callout for rental highlight text.

**Fix:** Add a guarded callout card to the Landlord Property Description section.

---

### DIM-3 · CTA Placement

#### 3-A · Buyer: Primary Hero CTA Leads to Coming Soon Dead-End
**Severity:** High | **Complexity:** High (feature work)

The Buyer hero "Respond to Buyer Criteria" button opens `#bolRespondModal`, which contains
only a "🔒 Under Development — Coming Soon" placeholder body. Visitors see a primary
call to action that leads to a dead end. No form, route, or controller handler exists.

**Fix options:**
1. Wire the modal to a real response form/route, or
2. Replace the button with a disabled state + tooltip indicating the feature is pending.

This is a feature-level gap, not a cosmetic one. It should be tracked as its own task.

---

#### 3-B · Seller Sidebar: Missing "Back to Search"
**Severity:** Low | **Complexity:** Low

Buyer, Landlord, and Tenant all include a "Back to Search" link as the last item in
their sticky sidebar. Seller's sidebar and mobile bar both omit it.

**Fix:** Add a `Back to Search` link to Seller sidebar pointing to
`route('offer.listing.seller.searchListing')`, and add the corresponding mobile bar entry.
**Tracked by:** Task #1643 (proposed follow-up)

---

#### 3-C · Tenant Sidebar: Primary CTA Conditionally Absent
**Severity:** Low | **Complexity:** Low

The Tenant sidebar renders "Contact Tenant" (primary CTA, `tcl-action-primary`) only when
`$ifFilled($str('email'))` is truthy. When no email is stored, the sidebar has no primary
action. The "Ask a Question" button is present but styled as `tcl-action-outline`
(secondary). Landlord always shows "Ask a Question" as its unconditional primary.

**Fix:** Unconditionally render "Ask a Question" as `tcl-action-primary`, demoting it to
secondary only when the email CTA is present — matching the Landlord sidebar pattern.

---

### DIM-4 · Mobile Layout

#### 4-A · Seller Mobile Bar: Missing "Back to Search"
**Severity:** Low | **Complexity:** Low

Buyer, Landlord, and Tenant mobile bars all include a "Search" back-navigation entry.
Seller mobile bar omits it. Consistent with the sidebar gap (DIM-3-B).

**Fix:** Add a mobile bar entry for Back to Search alongside the Seller sidebar fix.
**Tracked by:** Task #1643 (proposed follow-up)

---

#### 4-B · Tenant Mobile Bar: "Ask" Button Not Hidden for Owner on Owner's Listing
**Severity:** Very Low | **Complexity:** Low

The Tenant mobile bar conditionally hides the "Ask" button for the listing owner
(`@if ... style="display:none;"` guard), since an owner would not "ask a question"
to their own listing. The Buyer mobile bar does not apply this owner check — the owner
sees the "Respond" button and could click through to the Coming Soon modal. This is
a low-priority inconsistency.

---

### DIM-5 · Sidebar Consistency

#### Summary Matrix

| Action | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| Primary CTA | Submit Offer ✓ | Respond (Coming Soon) | Ask Question ✓ | Contact Tenant (conditional) |
| Schedule Showing | ✓ | N/A | ✓ | N/A |
| Ask AI About Property | ✓ | ✗ | ✗ | ✗ |
| Ask a Question | ✓ secondary | ✗ | ✓ primary | ✓ secondary |
| Save Listing (disabled) | ✓ | ✓ | ✓ | ✓ |
| Share Listing | ✓ | ✓ | ✓ | ✓ |
| Back to Search | **✗ MISSING** | ✓ | ✓ | ✓ |
| Edit Listing (owner, sidebar) | **✗ MISSING** | ✓ | **✗ MISSING** | ✓ |
| Price display | Asking Price | — | Monthly Rent | Rent Budget |

#### 5-A · Seller and Landlord Sidebars Missing Owner Edit Link

Buyer and Tenant surface an owner-gated Edit Listing link in the desktop sticky sidebar.
Seller owners must scroll to the bottom of the content column to find the Edit button.
Landlord desktop sidebar has no Edit link at all — owner must use the mobile bar or
bottom-of-content button.

**Fix:** Add a owner-gated Edit link below the price display in both Seller and Landlord
sticky sidebars, matching Buyer and Tenant.

#### 5-B · "Ask AI" Seller-Only Feature (Intentional)

The "Ask AI About Property" modal and sidebar button exist only in Seller. This is a
Seller-specific feature. It should not be added to other roles unless explicitly designed
in. Documented here so it is not accidentally backported in future refactors.

---

### DIM-6 · Sticky Nav / Section Visibility

#### 6-A · Seller: Nav Always Rendered Without Guard
**Severity:** Low | **Complexity:** Medium

Seller renders all sticky nav tabs unconditionally regardless of whether the underlying
sections have data. Buyer, Landlord, and Tenant guard each tab behind the same `@if`
that guards its section card. The Phase 1A audit noted this as a known P3 item.

**Fix:** Wrap each Seller nav tab in the same conditional guard as its section card.
**Tracked by:** Task #1643 (proposed follow-up); previously noted as P3 in Phase 1A.

---

#### 6-B · Landlord `#section-additional` Missing Nav Tab (see also DIM-2-B)

Already documented above.

---

#### 6-C · Active-Section Scroll Highlight: Seller, Buyer, Landlord
**Severity:** Low | **Complexity:** Medium

Tenant implements a full `onScroll` active-section highlighter (`window.scroll` listener
adding/removing `tcl-nav-active`). Seller, Buyer, and Landlord have sticky navs but no
active-state visual feedback as users scroll.

This is blocked by DIM-1-B for Seller (duplicate `id="section-overview"` must be fixed
first so the scroll tracker targets the right element).

**Fix:** Port the Tenant `onScroll` logic to all three roles with appropriate CSS class
prefixes (`sol-nav-active`, `bol-nav-active`, `lol-nav-active`).
**Tracked by:** Task #1644 (proposed follow-up)

---

#### 6-D · Smooth-Scroll Offset: 82 px vs. 80 px
**Severity:** Very Low | **Complexity:** Low

Seller and Landlord use the literal `82` for smooth-scroll offset. Buyer and Tenant
define a named constant `HEADER_OFFSET = 80`. The two-pixel difference is cosmetically
invisible but creates a maintenance inconsistency. Fix while addressing DIM-6-C.

---

### DIM-7 · Empty Sections Rendering

#### 7-A · Seller: Broker Compensation Card Unguarded (see DIM-2-A)

Already documented above.

---

#### 7-B · Buyer: Views/Saves Activity Strip Always Renders
**Severity:** Low | **Complexity:** Low

The Buyer sidebar includes a "Views / Saves" activity strip (two stat boxes showing
placeholder zeros) that always renders. No other role sidebar includes this element, and
no real data source backs it. If this is a future analytics placeholder, it should be
hidden behind a feature flag until the data source exists.

**Fix:** Remove or gate behind a feature flag / real data condition.

---

### DIM-8 · Data Saved but Not Displayed

#### 8-A · Buyer: `additional_details` Not Displayed
**Severity:** Medium | **Complexity:** Low

The Buyer criteria form is structurally similar to the Tenant form, which collects and
displays `additional_details`. The Buyer view page has no rendering for this key.
Confirmed: the Tenant view displays `additional_details` in a guarded `#section-additional`
card. If the Buyer wizard collects this field, any notes entered are silently dropped.

**Fix:** Audit the Buyer wizard for `additional_details` usage, then add the guarded
display card to `buyer/view.blade.php`.
**Tracked by:** Task #1643 (proposed follow-up)

---

### DIM-9 · Seller / Landlord Property Display Parity

| Section / Feature | Seller | Landlord | Gap Status |
|---|---|---|---|
| "Why This Property Stands Out" callout | ✓ | ✗ | Phase 1B gap — see DIM-2-E |
| Seller / Landlord Sale Terms | ✓ | ✗ | Tracked: Task #159 (lease terms) |
| Financial Details (Income/Commercial/Business) | ✓ | ✗ | Tracked: Task #304 |
| Tax, Legal, HOA & Disclosures | ✓ | ✗ | Tracked: Task #277 |
| Documents & Disclosures | ✓ | ✗ | Tracked: Task #290 |
| Photo gallery / hero carousel | ✓ | ✓ (Phase 1A) | PASS |
| Schedule Showing modal | ✓ | ✓ (Phase 1A) | PASS |
| Ask a Question modal | ✓ | ✓ (Phase 1A) | PASS |
| Broker Compensation section | ✓ (unguarded) | ✓ (guarded) | Seller guard missing — DIM-2-A |
| Conditional sticky nav tabs | ✗ (always shown) | ✓ | Seller is deficient — DIM-6-A |
| Active-section scroll highlight | ✗ | ✗ | Both need fix — DIM-6-C |
| Pets & Occupancy section | ✗ | ✓ | Intentional — rental-specific |
| Pre-Screening section | ✗ | ✓ | Intentional — rental-specific |
| Contact section header label | "Contact Information" | "Contact / Landlord Information" | Minor inconsistency |

**Key finding for DIM-9:** The large sections missing from Landlord (Sale Terms,
Financial Details, Tax/Legal/HOA, Documents & Disclosures) are all tracked by existing
tasks (#159, #304, #277, #290). The only net-new Phase 1B Landlord gap is the
"Why This Property Stands Out" callout (DIM-2-E) and the nav tab for `#section-additional`
(DIM-2-B).

---

### DIM-10 · Buyer / Tenant Criteria Display Parity

| Section / Feature | Buyer | Tenant | Gap Status |
|---|---|---|---|
| Pre-Screening section | ✗ | ✓ | Intentional N/A — rental-specific |
| Location as own section card | ✗ (embedded in criteria) | ✓ `#section-location` | Intentional — architecture choice |
| Video upload + embed in Contact | ✗ | ✓ | Criteria difference — low priority |
| Additional Details section | ✗ | ✓ | Phase 1B gap — DIM-2-C / DIM-8-A |
| Additional Information catch-all | ✗ | ✓ | Phase 1B gap — DIM-2-D |
| Active-section scroll highlight | ✗ | ✓ | Phase 1B gap — DIM-6-C |
| Question modal uses CSS class | ✓ | ✗ (inline style) | Phase 1B gap — DIM-1-C |
| Ask a Question modal | ✓ | ✓ | PASS |
| Back to Search in sidebar | ✓ | ✓ | PASS |
| Smooth-scroll with px offset | ✓ 80 px | ✓ 80 px | PASS |
| Mobile bar back navigation | ✓ | ✓ | PASS |
| Conditional nav tabs | ✓ | ✓ | PASS |

**Key finding for DIM-10:** Tenant is the more feature-complete criteria view. Buyer
lacks Additional Details and catch-all display (Phase 1B gaps) but is otherwise sound.
The location-section architecture difference (embedded vs. own card) is intentional.
Property-media items (photo, showing, documents) are intentional N/A for both.

---

## Cross-Reference: Existing Task Coverage

| Gap Found | Covered by Existing Task | Action |
|---|---|---|
| Landlord Sale / Lease Terms section | Task #159 (lease terms, landlord) | Do not duplicate |
| Tenant Lease Terms section | Task #161 (lease terms, tenant) | Do not duplicate |
| Seller / All: Documents & Disclosures display | Task #290 | Do not duplicate |
| Seller / All: Tax / Legal / HOA display | Task #277 | Do not duplicate |
| Seller Financial Details section | Task #304 | Do not duplicate |
| Landlord / Seller photo gallery | Tasks #250, #253 | Do not duplicate |
| Landlord photo upload infrastructure | Task #270 | Do not duplicate |
| Phase 1A functional gaps (modals, CTAs, etc.) | Task #1624 | Confirmed fixed |
| Seller phone format bug | Task #1642 (proposed) | New follow-up |
| Empty-card guards, nav tab, CSS fixes | Task #1643 (proposed) | New follow-up |
| Active-section scroll highlight | Task #1644 (proposed) | New follow-up |
| Buyer "Respond" CTA Coming Soon dead-end | Not yet tracked | Requires separate task |

---

## Recommended Phase 1B Implementation Order

### P0 — Fix before any other Phase 1B work (blocking)

| ID | Fix | Why First |
|---|---|---|
| DIM-1-B | Seller phone format bug | Active data-display defect on all Seller listings |
| DIM-1-B + DIM-6-C | Rename Seller `#section-overview` duplicate ID | Blocks active-section highlight backport (DIM-6-C) |

### P1 — High-value, low-risk quick wins

| ID | Fix |
|---|---|
| DIM-2-A / DIM-7-A | Add `@if` guard to Seller Broker Compensation card |
| DIM-2-B / DIM-6-B | Add Landlord `#section-additional` nav tab |
| DIM-3-B / DIM-4-A | Add Seller sidebar + mobile bar "Back to Search" |
| DIM-2-C / DIM-8-A | Add Buyer `#section-additional` card for `additional_details` |
| DIM-1-C | Replace Tenant question modal inline style with `tcl-modal-header` class |
| DIM-1-D | Scope Landlord `.lol-modal-header` under `.lol-view-page` |

### P2 — Medium effort, meaningful parity improvements

| ID | Fix |
|---|---|
| DIM-6-A | Add `@if` guards to Seller sticky nav tabs |
| DIM-6-C | Backport active-section scroll highlight to Seller, Buyer, Landlord |
| DIM-5-A | Add owner Edit link to Seller and Landlord desktop sidebars |
| DIM-3-C | Promote Tenant sidebar "Ask a Question" to primary when no email CTA |
| DIM-2-D | Add Buyer + Landlord "Additional Information" catch-all sections |
| DIM-2-E | Add "Why This Property Stands Out" callout to Landlord |

### P3 — Low priority / nice-to-have

| ID | Fix |
|---|---|
| DIM-7-B | Remove or gate Buyer sidebar Views/Saves placeholder strip |
| DIM-4-B | Standardize smooth-scroll offset to single constant across all four views |
| DIM-4-B (Tenant mobile) | Hide Buyer mobile "Respond" button for listing owner |
| DIM-3-A | Wire Buyer "Respond to Buyer Criteria" modal (feature-level — separate task) |

---

## Confirmed Intentional Differences (do not build)

| Observation | Decision |
|---|---|
| Buyer / Tenant have no hero photo carousel | Intentional — criteria listings have no property photos |
| Buyer / Tenant have no Photos & Tours section | Same — N/A by listing type rule |
| Buyer / Tenant have no Schedule Showing CTA | Same — no property to show |
| Buyer / Tenant have no property Documents & Disclosures | Same — N/A by listing type rule |
| Buyer / Tenant have no Tax / Legal / HOA section | Same — N/A by listing type rule |
| Buyer / Tenant have no Financial Details section | Same — N/A by listing type rule |
| Seller has "Ask AI" that others lack | Feature scoped to property listings only; do not add to Buyer/Tenant |
| Tenant has Pre-Screening section; Buyer does not | Rental-specific screening requirement |
| Landlord has Pets & Occupancy; Buyer/Seller do not | Rental-specific policy |
| Buyer Location embedded in Criteria section (no own card) | Intentional architecture choice |
| Each role uses its own color palette | Intentional role-based branding system |
| Tenant catch-all Additional Information section | Intentional safety net — Tenant is the reference for DIM-2-D fix |
