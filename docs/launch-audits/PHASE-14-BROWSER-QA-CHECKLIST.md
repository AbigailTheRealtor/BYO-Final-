# Phase 14 — Owner-Run Manual Browser QA Checklist

**Run by:** _______________  **Date:** _______________  **Build/commit:** `3cc3488d5` (+ any later)

> Governing rule (**C14**): **no faked passes.** If you cannot clearly observe the expected result, mark **☐ Fail** or write "BLOCKED" in Notes — never leave it implied-pass. Anything unexpected goes in Notes even if it "looks" fine.

---

## 0. One-time setup (do this first)

1. In the Replit shell, make sure assets are building:
   ```
   npm run watch
   ```
   Leave it running. Wait for "Compiled successfully" before testing.
2. Open the **Replit dev URL** in a normal browser tab (Chrome recommended).
3. Open **DevTools** (F12) and keep two panels handy:
   - **Console** tab — watch for red errors while you click.
   - **Network** tab — filter to `Fetch/XHR`; this is where Livewire (`/livewire/message/...`) and Ask AI (`/ask-ai/listing-question`) calls appear. Check "Preserve log" so nothing scrolls away.
4. Log in as a normal user account you own (the "owner" account). For steps that need a *second* user or an *agent* account, have those credentials ready.
5. Get your listing IDs from **`/my-listings`** (or the dashboard). Wherever a step says `{id}`, substitute a real ID of the correct type/role.

**Legend:** ☐ Pass ☐ Fail — tick one; add Notes always. "Console clean" = no new red errors during the action. "No 500" = no red 500 response in Network.

---

# PRIORITY 1 — Fix-pass items & the open hold (do these first)

## BQ-09 — `$purchase_purpose` "Other" reveal, UNIFIED live buyer create + edit  ·  Severity: High (was a 500)

**Why:** This session fixed a 500 here. Confirm the tab now renders AND the "Other" free-text field reveals/persists.

**Page — CREATE:** open **`/hire/agent/auction/buyer`**
**Actions:**
1. On load, watch Console/Network — the page must render fully (no 500, no red error).
2. Go to the **Property Preferences** tab.
3. Find **Purchase Purpose** → select **Other**.
4. A free-text input ("Enter purchase purpose…") should appear directly below.
5. Type any text (e.g. `Estate planning`). Click **Save Draft**.
6. Reload the draft (from `/my-listings` → resume) and return to Property Preferences.

**Expected:**
- Property Preferences tab renders with **no 500 / no ViewException** (Console clean).
- Selecting **Other** reveals the free-text input (it was `d-none`, now visible).
- Selecting any non-Other value hides it again.
- After Save Draft + resume, Purchase Purpose = **Other** and your typed text is still there.

**Network check:** the `wire:model` change on Purchase Purpose fires a `POST /livewire/message/...` → must return **200**, not 500.

☐ Pass ☐ Fail
Notes: ___________________________________________________________________

**Page — EDIT:** open **`/hire/agent/auction/edit/{id}/buyer`** (an existing unified buyer listing)
**Actions:** repeat steps 2–4 above on the edit screen; confirm an already-saved "Other" value **pre-fills** the free-text box on load.
**Expected:** edit screen renders (no 500); saved "Other" text pre-populates; toggle still works; **Save Edit** persists it.

☐ Pass ☐ Fail
Notes: ___________________________________________________________________

---

## BQ-10 — B5.4: Minimum Bedrooms/Bathrooms "Other" makes the select box vanish  ·  Severity: Medium (KNOWN HOLD)

**Why:** Documented open defect. We expect this to still FAIL — you are confirming the current behavior, not certifying a fix.

**Page:** **`/hire/agent/auction/buyer`** → **Property Preferences** tab
**Actions:**
1. First set **Property Type = Residential** (bed/bath fields only show for Residential).
2. Find **Minimum Bedrooms Needed** → select **Other**.
3. Watch the select box itself the moment the value changes.
4. Repeat with **Minimum Bathrooms Needed** → **Other**.

**Expected (defect reproduction):** after picking "Other", the **main dropdown box visually disappears, leaving only the small field icon** (the bug). If instead the select stays fully visible AND an "Other" number input appears cleanly, note that the defect did **not** reproduce.

**Console/Network:** note any red errors on the round-trip; confirm the `POST /livewire/message/...` returns 200 (the disappearance is a client render artifact, not a server error).

☐ Pass (box stays visible — defect gone) ☐ Fail (box disappears — defect confirmed)
Notes (which field(s), bedrooms/bathrooms/both): ______________________________

---

## BQ-01 — Ask AI 403 gating (owner vs non-owner vs guest)  ·  Severity: Critical (privacy/auth)

> Ask AI is a separate surface — **observe only, do not change anything.**

**Pages:** an **approved** offer listing detail, per role:
`/offer-listing/seller/view/{id}` · `/offer-listing/buyer/view/{id}` · `/offer-listing/landlord/view/{id}` · `/offer-listing/tenant/view/{id}`

**A. As the listing OWNER** (do for at least one role, ideally all four):
1. Open "Ask AI About This Property", type a question, submit.
2. **Expected:** you get an AI answer (or a normal "try again" message). In **Network**, `POST /ask-ai/listing-question` returns **200** — **no 403**.

☐ Pass ☐ Fail  Notes (roles checked): __________________________________

**B. As a DIFFERENT logged-in user (non-owner):**
1. Open the same listing's Ask AI modal, submit.
2. **Expected:** blue notice **"Ask AI for this listing is available to the listing owner."**; **no failed 403 request** is fired (the UI blocks it client-side). Console clean.

☐ Pass ☐ Fail  Notes: ___________________________________________________

**C. As a GUEST (logged out):**
1. Open the listing; try the Ask AI modal.
2. **Expected:** modal is non-actionable or shows the same owner-only notice; **no console error, no raw 403 / login-redirect surfaced as a broken answer.**

☐ Pass ☐ Fail  Notes: ___________________________________________________

**(Optional) Stellar shared detail:** `/stellar/property/{listingKey}?criteria_id=...&criteria_type=buyer`
- Your own criteria_id → Ask AI form + answers. Tamper to a criteria_id you don't own → "available from your saved criteria" notice, **no 403**, no form.

☐ Pass ☐ Fail  Notes: ___________________________________________________

---

## BQ-02 — Expired bidding-period listing rejects NEW bids  ·  Severity: High

**Setup:** you need a **Hire-Agent** listing in **Bidding Period** whose `expiration_date` is in the past. Use the hire detail pages:
Seller `/seller/agent/auction/view/{id}` · Buyer `/buyer/agent/auction/view/{id}` · Landlord `/hire/agent/auction/view/{id}` · Tenant `/tenant/agent/auction/view/{id}`

**Actions & Expected:**
1. On an **expired** listing (each role), attempt to **submit a NEW bid**.
   → **Expected:** rejected with *"This listing has expired and is no longer accepting new bids."* (or legacy *"not currently accepting new bids"*).
   ☐ Pass ☐ Fail  Notes (roles): _______________________________________
2. **Edit an existing bid** on that same expired listing.
   → **Expected:** editing still works (NOT blocked).
   ☐ Pass ☐ Fail  Notes: ___________________________________________
3. Open a **Traditional** (non-bidding) listing.
   → **Expected:** **no countdown timer**; bidding unaffected.
   ☐ Pass ☐ Fail  Notes: ___________________________________________
4. Open an **active** (future expiration) bidding listing.
   → **Expected:** still accepts new bids; **live countdown** visible.
   ☐ Pass ☐ Fail  Notes: ___________________________________________

---

# PRIORITY 2 — Standards & interactive dimensions

## BQ-06 — Conditional reveal + select2 rehydration (S7; garage B5.3 / ported fields B5.8)  ·  Severity: Medium

**Page:** `/hire/agent/auction/buyer` → **Property Preferences**
**Actions & Expected:**
1. Set property type so garage fields apply; set **Garage/Parking = Yes** → dependent field(s) appear.
   ☐ Pass ☐ Fail  Notes: _______________________________________________
2. Add a multi-select value (e.g. Garage Features), Save Draft, resume the draft.
   → **Expected:** on reload the **select2 multi-select shows your saved chips** (rehydrated), not an empty box or a broken/duplicated widget.
   ☐ Pass ☐ Fail  Notes: _______________________________________________
3. Confirm ported fields (HOA Acceptance, Maximum HOA Monthly Fee, Flood Zone Preference +Other) reveal/prefill correctly on edit.
   ☐ Pass ☐ Fail  Notes: _______________________________________________

**Console/Network:** after each Livewire round-trip, no red errors; no "select2 already initialized" warnings leaving a dead widget.

---

## BQ-07 — Shared address / Search Areas autocomplete (S10)  ·  Severity: Medium

**Page:** Hire Buyer/Tenant **Search Areas** tab (`/hire/agent/auction/buyer`), and Hire Seller/Landlord address field.
**Actions & Expected:**
1. Type a partial address/city → Google Places suggestions appear.
   ☐ Pass ☐ Fail  Notes: _______________________________________________
2. Pick a suggestion (mouse) → field populates; area/tag is added.
   ☐ Pass ☐ Fail  Notes: _______________________________________________
3. **Keyboard test (known follow-up):** type a state/area, use **arrow keys + Enter** to select a suggestion.
   → **Expected:** Enter selects the highlighted suggestion; it does **not** submit the form or clear the field unexpectedly.
   ☐ Pass ☐ Fail  Notes: _______________________________________________

**Console/Network:** watch for Google Maps JS errors (quota/`ApiNotActivated`/`RefererNotAllowed`) — record any in Notes.

---

## BQ-04 — "Other" fields persist across draft-resume & edit (S3)  ·  Severity: Medium

**Pages:** the Hire Buyer forms with "Other" free-text (amenities, assets, flood zone, purchase purpose, etc.).
**Actions:** for 2–3 "Other" fields: select Other, type a value, Save Draft, resume; then Save Edit and reopen.
**Expected:** each "Other" input stays revealed on reload and retains its typed value in both draft-resume and edit.

☐ Pass ☐ Fail  Notes: ___________________________________________________

---

## BQ-08 — Multi-surface parity: Summary / PDF / email / Ask-AI (S11 / S16)  ·  Severity: Medium

**Pages:** Accepted Bid Summary `/accepted-bid-summary/{id}` and PDF `/accepted-bid-summary/{id}/download-pdf`.
**Actions & Expected:**
1. Open an Accepted Bid Summary → the normalized fields (appliances, amenities, business assets, agency timeframe, condition, contingencies) display with correct labels/values.
   ☐ Pass ☐ Fail  Notes: _______________________________________________
2. Download the **PDF** → same fields render correctly (no `{{placeholder}}` tokens leaking, no blank sections).
   ☐ Pass ☐ Fail  Notes: _______________________________________________
3. (If available) confirm the same field values appear in the listing's Ask-AI answers / any email summary.
   ☐ Pass ☐ Fail  Notes: _______________________________________________

---

## BQ-03 — Agent contact/credential shows LIVE data (C5)  ·  Severity: Medium

**Actions & Expected:**
1. As an **agent**, place a bid on a listing.
2. Change your **profile**: phone / brokerage / license # / NAR ID / name.
3. As the **listing owner**, reopen that bid's detail.
   → **Expected:** contact/credential block shows the **updated** values (live); **negotiated terms** (commission %, offered price) and any **Accepted Bid Summary** stay the **historical** agreed values.
   ☐ Pass ☐ Fail  Notes: _______________________________________________
4. A legacy bid whose agent has no profile value → falls back to the original snapshot (no blank fields).
   ☐ Pass ☐ Fail  Notes: _______________________________________________

---

## BQ-05 — Tooltip visual/format parity (S4 / A8.50)  ·  Severity: Low

**Actions:** hover the ℹ️ tooltips across the Hire Buyer tabs (and spot-check other roles).
**Expected:** single compact dark style, consistent font/size/format everywhere; no clipped, doubled, or light/mismatched tooltips; wording matches the field.

☐ Pass ☐ Fail  Notes: ___________________________________________________

---

## BQ-11 — Accessibility / visual consistency (S14)  ·  Severity: Medium

**Actions & Expected:**
1. **Keyboard:** Tab through a full Hire Buyer tab — focus order is logical, focus ring is visible on every control.
   ☐ Pass ☐ Fail  Notes: ___________________________________________
2. **Required markers / errors:** trigger validation (submit empty) — required `*` markers and error icons/messages are visible and not hidden by any text change.
   ☐ Pass ☐ Fail  Notes: ___________________________________________
3. **Contrast:** labels/placeholders/helper text are legible (no near-invisible grey).
   ☐ Pass ☐ Fail  Notes: ___________________________________________

---

# Global console/network watch (applies to every step)

Record in Notes if you ever see:
- ❌ Any **red 500** on a `/livewire/message/...` request (server error — highest concern).
- ❌ Any **403** on `/ask-ai/listing-question` that surfaces as a broken answer (BQ-01).
- ❌ Uncaught JS exceptions (`TypeError`, `$(...).select2 is not a function`, `google is not defined`).
- ❌ select2 widgets that render empty/broken after a Livewire update (BQ-06/BQ-10).
- ❌ Google Maps errors (`ApiNotActivated`, `RefererNotAllowed`, quota) (BQ-07).

---

# Sign-off

| Priority | Items | Pass | Fail | Notes |
|---|---|---|---|---|
| P1 | BQ-09, BQ-10, BQ-01, BQ-02 | | | |
| P2 | BQ-03–BQ-08, BQ-11, BQ-05 | | | |

**Overall result:** ☐ Ready for launch ☐ Ready with known holds ☐ Blocked — remediation needed
**Blocking failures (if any):** _______________________________________________
**Tester signature / date:** _________________________________________________

> Return this filled-in sheet and I'll triage every ☐ Fail: severity, root cause, and a scoped fix recommendation — no code changes until you approve them.
