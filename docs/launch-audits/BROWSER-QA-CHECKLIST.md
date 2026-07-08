# Browser QA Checklist — Create Offer & Hire Agent (Batches A–F)

**Branch:** `launch-audit-remediation` · **HEAD:** `be0aa4f98`
**Source:** `docs/launch-audits/CREATE-OFFER-HIRE-AGENT-BUGFIX-AUDIT-2026-07-05.md`
**Scope:** every implemented fix from Batches A → F-2 (32 test items).

> **Gate.** Nothing in the audit ledger is marked PASS until it is confirmed in a real browser. This document is that pass. Tick `☐ PASS`, `☐ FAIL`, or `☐ N/A` per item and add notes on any FAIL.

## How to read the status type

- **Implemented** — a code change shipped; confirm it works.
- **Verify-only** — behaviour should already be correct (no code change, or already-compliant); confirm by eye.
- **Diagnosis** — server path proven clean; if it still fails, the defect is front-end → capture it in devtools.

## Environment notes before you start

- The build environment has no working Chromium, so every item is **CODE COMPLETE — HUMAN BROWSER QA REQUIRED**.
- **Upload items #6 / #7** need the *running* app with `deploy/php/uploads.ini` applied (worker reporting **50M / 150M / 50 / 512M**). Without the raised limits they will still fail at the old ~8M POST cap regardless of the fix.
- Common tab names used below: *Property Preferences*, *Property Details*, *Seller Terms*, *Lease Terms*, *Purchasing Terms*, *Financial Details*, *Broker Compensation*, *Photos, Tours & Documents*, *Tax, Legal & HOA Disclosures*, *Additional Details*, *Description*.

---

# Batch A — false-greens & quick fixes
`commit 0e486dfaf · 6 items`

### #3 — Seller edit lands on the listing page, not a success screen
- **Batch:** A · **Type:** Implemented · **Flow:** Create Seller (edit)
- **Page/tab:** Edit an already-published Seller listing → Save/Update
- **Steps:**
  1. Open a published Seller offer listing and click Edit.
  2. Change any field and Save.
- **Expected:** Redirects to the Seller listing detail page (`offer.listing.seller.view`) — not a success screen, not the form.
- **Edge cases:** Also confirm first-publish (draft → publish) still redirects; confirm Buyer / Landlord / Tenant edits still redirect (already correct).
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #12 — Pet fields are text inputs, not number spinners
- **Batch:** A · **Type:** Implemented · **Flows:** Create Seller, Create Landlord, Hire Seller, Hire Landlord *(shared — check all 4)*
- **Page/tab:** Property Preferences — "Number of Pets Allowed" and "Max Weight Per Pet"
- **Steps:**
  1. On each of the 4 forms, open Property Preferences.
  2. Inspect both pet fields; type a value and save.
- **Expected:** Both render as text inputs (no up/down spinner), accept input, and save.
- **Edge cases:** Create-Landlord "Max Weight Per Pet" was already text — confirm it still is.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #13 — "Desired Lease Term → Other" placeholder no longer duplicates an option
- **Batch:** A · **Type:** Implemented · **Flow:** Create Landlord
- **Page/tab:** Lease Terms → Desired Lease Term
- **Steps:**
  1. Select "Other" for Desired Lease Term.
  2. Read the revealed input's placeholder.
- **Expected:** Placeholder reads `Enter desired lease term (e.g., 8 Months)` — the example is **not** a value already in the dropdown (no "6-month").
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #32 — Lease-purchase conditions placeholder capitalises "Seller"
- **Batch:** A · **Type:** Implemented · **Flow:** Create Buyer
- **Page/tab:** Purchasing Terms → "Conditions / Requirements for Lease Purchase"
- **Steps:**
  1. Read the placeholder text.
- **Expected:** Each example is capitalised and "Seller" is capital-S (not lowercase "seller"). Hire Buyer was already correct.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #21 — "Additional Seller Sale Terms" box matches the Total Parcels box
- **Batch:** A · **Type:** Verify-only (no code change) · **Flow:** Create Seller
- **Page/tab:** Seller Terms → "Additional Seller Sale Terms" textarea
- **Steps:**
  1. Compare its height side-by-side with the "Total Number of Parcels" box.
  2. Repeat on the edit view.
- **Expected:** Same rendered height/type as Total Parcels; placeholder sentence-cased.
- **Edge cases:** No code change shipped — confirm **computed pixel parity** in-browser (textarea vs input rendering).
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #22 — Five Seller disclosure textareas match the Total Parcels box
- **Batch:** A · **Type:** Verify-only (no code change) · **Flow:** Create Seller
- **Page/tab:** Tax, Legal & HOA Disclosures
- **Steps:**
  1. Check these 5 boxes: Parcel IDs, Legal Description, Special Assessment, Approval Process, Leasing Restrictions.
  2. Confirm each matches Total Parcels height on create **and** edit.
- **Expected:** All five match the Total Parcels box size.
- **Edge cases:** No code change — confirm computed pixel parity by eye.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

---

# Batch B — Phase-1 launch blockers
`commits b6e8694f4 / 958df08a4 · 4 items`

### #1 — Landlord Broker Compensation + Agency Agreement Terms render on create
- **Batch:** B · **Type:** Implemented · **Flow:** Create Landlord
- **Page/tab:** Broker Compensation
- **Steps:**
  1. Start a new Landlord listing, leave Property Type **blank** — confirm the block shows.
  2. Pick **Residential**: confirm Broker Commission Structure + all agency-terms blocks render.
  3. Pick **Commercial**: confirm the commercial variant renders.
  4. Save, then re-open in edit for both types.
- **Expected:** Broker Commission Structure + agency terms appear on create for blank/Residential/Commercial, save, and reappear on edit.
- **Edge cases:** Residential-only terms (Protection Period, Payment Timing) are intentionally hidden for Commercial — that is correct, not a bug.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #5 — Video tour embeds, with a safe fallback link for unsupported URLs
- **Batch:** B · **Type:** Implemented · **Flows:** Seller, Landlord, Tenant detail views *(shared — check all 3)*
- **Page/tab:** Public listing detail/view page
- **Steps:**
  1. On a listing, set the video URL to a **YouTube** link → view detail.
  2. Repeat with a **Vimeo** link.
  3. Repeat with an **unsupported** URL (e.g. Matterport / Loom / a raw `.mp4`).
- **Expected:** YouTube & Vimeo embed inline; the unsupported URL falls back to a labelled clickable link — no broken layout.
- **Edge cases:** Check all three detail views (Seller / Landlord / Tenant).
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #2 — Create Landlord submits; draft & edit still save
- **Batch:** B · **Type:** Verify-only (server-proven) · **Flow:** Create Landlord
- **Page/tab:** Full wizard → Submit / Save Draft / Edit
- **Steps:**
  1. Complete the happy path and Submit.
  2. Separately, Save Draft with partial data.
  3. Edit an existing listing and Save.
- **Expected:** Submit publishes + redirects; draft stays lenient (no publish-validation wall); edit saves.
- **Edge cases:** Server paths already proven — this is a confirmation.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #4 — Seller Business listing submits
- **Batch:** B · **Type:** Diagnosis if it still blocks · **Flow:** Create Seller · Business
- **Page/tab:** Property Type = Business → Financial Details (Business)
- **Steps:**
  1. Fill the Business financial-details tab with valid multi-selects.
  2. Open browser devtools (Console + Network) and Submit.
- **Expected:** Submits cleanly and lands on the detail page. The **server** path is proven clean.
- **Edge cases:** **If it fails**, capture the client-side JS validation error / blocked request in devtools — that is the remaining defect to report (not the server rules).
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

---

# Batch C — file-upload limits (infrastructure)
`commit 804266ffe · 2 items`

> Requires the **running app with the raised limits applied** (worker reporting 50M / 150M / 50 / 512M).

### #6 — 14 JPGs upload in one selection
- **Batch:** C · **Type:** Implemented (infra) · **Flows:** Create Seller, Create Landlord *(check both)*
- **Page/tab:** Photos, Tours & Documents
- **Steps:**
  1. Select exactly **14 phone-sized JPGs** in a single file-picker selection.
  2. Wait for upload; save; re-open in edit.
- **Expected:** All 14 upload, persist, and reappear on edit — no silent drop.
- **Edge cases:** Confirm the running worker reports the raised limits after redeploy; without it this still fails at the old 8M POST cap.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #7 — Large document uploads, with a clear over-limit error
- **Batch:** C · **Type:** Implemented (infra) · **Flows:** Create Seller, Create Landlord *(check both)*
- **Page/tab:** Photos, Tours & Documents → document upload
- **Steps:**
  1. Upload a document **just under** the 50M limit → expect success.
  2. Upload one **over** the limit → expect a friendly error.
- **Expected:** Under-limit succeeds; over-limit shows a clear oversize message — **never a silent no-op**.
- **Edge cases:** Verify the Replit edge-proxy body cap isn't smaller than the app limit; on any future nginx/Apache set `client_max_body_size ≥ 150M`.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

---

# Batch D — shared-JS "Other"/reveal & currency (+ SC1)
`commit 47407e37f · SC1 55e8fd5ec · 10 items`

### #8 — Acceptable Exchange Item stays selected (no toggle-off)
- **Batch:** D · **Type:** Implemented (SC2) · **Flows:** Create Seller, Hire Seller *(check both)*
- **Page/tab:** Seller Terms → Acceptable Exchange Item (select2)
- **Steps:**
  1. Select an exchange item; wait for the Livewire spinner to finish.
  2. Confirm it stays selected; save and re-open.
- **Expected:** Selection persists after the round-trip and saves.
- **Edge cases:** Check both Create Seller and Hire Seller.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #9 — Exchange Item "Other" opens its text input
- **Batch:** D · **Type:** Verify (SC2 family) · **Flow:** Hire Seller
- **Page/tab:** Seller Terms → Acceptable Exchange Item
- **Steps:**
  1. Choose "Other".
  2. Type into the revealed input and save.
- **Expected:** The "Other" custom input reveals and its value persists.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #10 — Estimated Value & Acceptable Condition stay stable while typing
- **Batch:** D · **Type:** Implemented (wire:key) · **Flow:** Hire Seller
- **Page/tab:** Seller Terms → Estimated Value + Acceptable Condition
- **Steps:**
  1. Type continuously into each field.
- **Expected:** No flicker or mid-typing blanking; values remain visible and save.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #11 — Currency fields keep decimals & leading dots
- **Batch:** D · **Type:** Implemented (SC1) · **Flows:** all Hire wrappers — Seller, Buyer, Landlord, Tenant *(shared mask — spot-check each)*
- **Page/tab:** Any Hire currency field — Additional Cash, Lease-Option Price/Payment, Option Fee, Balloon Payment
- **Steps:**
  1. Type `1234.56` slowly — the trailing dot must survive mid-typing.
  2. Type `.75` — the leading dot must be preserved (SC1 Tenant follow-up).
  3. Blur, then save.
- **Expected:** Decimals never get stripped while typing; `.75` stays `.75`; value saves correctly.
- **Edge cases:** Shared mask across all Hire/Tenant wrappers + edit variants.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #14 — Vacant Land "Property Style → Other" reveals its input
- **Batch:** D · **Type:** Implemented (SC3) · **Flows:** Create Seller, Create Landlord *(check both)*
- **Page/tab:** Property Preferences → Property Type = Vacant Land → Property Style
- **Steps:**
  1. Set Property Type to Vacant Land.
  2. Select "Other" for Property Style.
  3. Type in the revealed input and save.
- **Expected:** Custom input reveals with placeholder `Enter property style (e.g., Solar farm, RV park, Conservation easement)` and persists.
- **Edge cases:** The SC3 handler also drives appliances/view/assets/garage "Other" reveals — a quick sanity check those still open is worthwhile.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #15 — Garage/Parking = Yes reveals dependent fields
- **Batch:** D · **Type:** Verify · **Flow:** Hire Buyer
- **Page/tab:** Property Preferences → Garage/Parking Features Needed
- **Steps:**
  1. Select "Yes".
  2. Confirm dependent parking fields appear; fill and save.
- **Expected:** Dependent parking fields reveal and persist.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #16 — Min Bedrooms/Bathrooms "Other" keeps the select visible
- **Batch:** D · **Type:** Verify · **Flow:** Hire Buyer
- **Page/tab:** Property Preferences → Min Bedrooms & Min Bathrooms
- **Steps:**
  1. Pick "Other" for Min Bedrooms, then Min Bathrooms.
- **Expected:** The select stays visible (does not vanish) and the "Other" input appears alongside.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #17 — Purchase Purpose "Other" reveals its input
- **Batch:** D · **Type:** Verify (SC3) · **Flows:** Create Buyer, Hire Buyer *(check both)*
- **Page/tab:** Property Preferences → Purchase Purpose
- **Steps:**
  1. Choose "Other".
  2. Read placeholder; type & save.
- **Expected:** Input reveals with placeholder `Enter purchase purpose (e.g., Relocating for family support)`; persists in both flows.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #18 — Flood Zone Preference "Other" reveals its input
- **Batch:** D · **Type:** Verify (SC2) · **Flows:** Create Buyer, Hire Buyer *(check both)*
- **Page/tab:** Property Preferences → Flood Zone Preference
- **Steps:**
  1. Choose "Other".
  2. Read placeholder; type & save.
- **Expected:** Input reveals with placeholder `Enter flood zone preference (e.g., Prefer elevated property with low-risk designation)`; persists.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #19 — HOA Acceptance = Yes/Flexible shows Max HOA Monthly Fee
- **Batch:** D · **Type:** Verify (parity) · **Flow:** Hire Buyer
- **Page/tab:** Property Preferences → HOA Acceptance
- **Steps:**
  1. Set HOA Acceptance to "Yes", then "Flexible".
  2. Compare the revealed field/tooltip to Create Buyer.
- **Expected:** Max HOA Monthly Fee reveals for both values, saves, and matches Create Buyer's tooltip/placeholder.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

---

# Batch E — Hire Tenant field parity
`commit 89549834b · 1 item`

### #20 — Hire Tenant gains Rental Purpose + Accessibility Requirements
- **Batch:** E · **Type:** Implemented · **Flow:** Hire Tenant
- **Page/tab:** Property Details
- **Steps:**
  1. Set Rental Purpose to a preset value (e.g. Primary Residence).
  2. Set it to "Other" → confirm the custom text input appears; type a value.
  3. Fill Accessibility Requirements.
  4. Submit, then re-open in edit.
- **Expected:** Both fields save and repopulate on edit; the "Other" input reveals reactively; switching Rental Purpose **away** from "Other" clears the custom text.
- **Edge cases:** Load an existing draft where Rental Purpose = Other and confirm the custom value is **not** wiped on hydration.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

---

# Batch F-1 — cosmetic consistency
`commit 28f24c5a4 · 7 items`

### #23 — Hire address tooltips read as one sentence (no line break)
- **Batch:** F-1 · **Type:** Implemented · **Flows:** Hire Seller, Hire Landlord *(check both)*
- **Page/tab:** Property Preferences → hover the City / State / County info tooltips
- **Steps:**
  1. Hover each of the three address tooltips.
- **Expected:** Each tooltip is a continuous sentence with a normal space (no mid-tooltip line break), matching the Create flow's format.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #24 — Appliance placeholders are sentence-cased
- **Batch:** F-1 · **Type:** Implemented · **Flows:** Hire Landlord, Hire Tenant
- **Page/tab:** Hire Landlord → Property Preferences (appliances); Hire Tenant → Property Details (other appliances)
- **Steps:**
  1. Read each appliance placeholder.
- **Expected:** Hire Landlord: `Enter appliances (e.g., Air fryer oven, Induction cooktop, Double oven)`. Hire Tenant: `Enter other appliances (e.g., Warming drawer)` — first letter only, no Title Case.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #25 — Water Frontage / Waterfront Feet placeholders include the field title
- **Batch:** F-1 · **Type:** Implemented · **Flows:** Create Seller, Create Landlord *(Create-only)*
- **Page/tab:** Property Preferences
- **Steps:**
  1. Read the Water Frontage and Waterfront Feet placeholders.
- **Expected:** `Enter Water Frontage (e.g., …)` and `Enter Waterfront Feet (e.g., 75)`.
- **Edge cases:** **Owner decision** — Create-only; these fields are **not** added to Hire. Their absence on Hire is correct.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #26 — Agent Credentials placeholders drop the "(e.g., …)" examples
- **Batch:** F-1 · **Type:** Implemented · **Flows:** shared partial → all Create + all Hire *(spot-check 1 of each)*
- **Page/tab:** The Agent Credentials / contact section
- **Steps:**
  1. On at least one Create form and one Hire form, read the Phone, License #, and NAR/NRDS ID placeholders.
- **Expected:** Placeholders are just `Enter Phone Number`, `Enter License Number`, `Enter NAR Member ID` — no example text.
- **Edge cases:** Single shared partial → 19 sites; one Create + one Hire spot-check is sufficient.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #27 — HOA / Association Notes placeholder is capitalised
- **Batch:** F-1 · **Type:** Implemented · **Flow:** Create Seller
- **Page/tab:** Seller Terms → "Additional HOA / Association Notes"
- **Steps:**
  1. Read the placeholder.
- **Expected:** Reads `… $200 transfer fee, Pending special assessment, New rules effective Jan 2026` — "Pending" and "New" capitalised.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #30 — Hire "Additional Details" placeholder is lowercase
- **Batch:** F-1 · **Type:** Implemented · **Flows:** all Hire — Seller, Buyer, Landlord, Tenant *(helper → check all 4)*
- **Page/tab:** Additional Details
- **Steps:**
  1. Read the free-text placeholder on each of the four Hire flows.
- **Expected:** `Enter additional details (e.g., …)` — lowercase title, examples preserved.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #31 — Create Description placeholders per role
- **Batch:** F-1 · **Type:** Implemented · **Flows:** all Create — Seller, Buyer, Landlord, Tenant *(helper → check all 4)*
- **Page/tab:** Description
- **Steps:**
  1. Read the Description placeholder on each Create flow.
- **Expected:** Seller `Enter property description` · Buyer `Enter buyer description` · Landlord `Enter rental description` · Tenant `Enter tenant description`.
- **Edge cases:** **Owner decision** — Tenant is `tenant description`, deliberately **not** "rental".
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

---

# Batch F-2 — Location-DNA map partial
`commit 803a337b6 · 2 items`

### #28 — Preferred Cities "County bias / Seminole" helper text is gone
- **Batch:** F-2 · **Type:** Implemented · **Flows:** Create Buyer, Create Tenant, Hire Buyer, Hire Tenant *(shared — check all 4)*
- **Page/tab:** Location DNA / Preferred Cities map block (Property Preferences / Property Details, per flow)
- **Steps:**
  1. Open the Preferred Cities hint under the cities input on each of the 4 flows.
- **Expected:** No "County bias is used so 'Seminole, FL' maps to Pinellas…" sentence. The sibling line `Selecting a city draws its boundary on the map.` **stays**.
- **Edge cases:** —
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

### #29 — Important Places controls align with Exact Address / Miles
- **Batch:** F-2 · **Type:** Verify-only · already at parity · **Flows:** Create Buyer, Create Tenant, Hire Buyer, Hire Tenant
- **Page/tab:** Location DNA → Important Places → "Add Important Place" row
- **Steps:**
  1. Add an Important Place row.
  2. Compare the Type, Distance Preference, and Travel Mode controls to the Exact Address and Miles inputs.
- **Expected:** All controls render at the same height/size (Bootstrap 5.2.2 `-sm` parity). **No code change shipped** — this only confirms the existing markup renders as expected.
- **Edge cases:** If you see a real mismatch in-browser, flag it for a new scoped fix — do not treat parity as failed on the current markup.
- **Result:** ☐ PASS ☐ FAIL ☐ N/A — **Notes:**

---

# Shared partials & helpers — check every flow

One change ripples to many flows. A pass on one flow is not a pass on all — confirm each.

| Issue | Shared source | Flows to confirm |
|-------|---------------|------------------|
| #26 | agent-credentials partial (19 sites) | every Create + every Hire (spot-check 1 of each) |
| #30 | PropertyTypePlaceholderHelper (hire titles) | Hire Seller · Buyer · Landlord · Tenant |
| #31 | PropertyTypePlaceholderHelper (create titles) | Create Seller · Buyer · Landlord · Tenant |
| #28 / #29 | location-dna/map-input partial | Create Buyer · Create Tenant · Hire Buyer · Hire Tenant |
| #5 | VideoEmbedHelper (detail views) | Seller · Landlord · Tenant detail pages |
| #12 | pet-field input type | Create Seller · Create Landlord · Hire Seller · Hire Landlord |
| #11 | SC1 currency mask | all Hire wrappers (Seller · Buyer · Landlord · Tenant) + edit variants |
| #8 | SC2 exchange select2 | Create Seller · Hire Seller |
| #14 | SC3 "Other" reveal handler | Create Seller · Create Landlord (+ appliances/view/assets/garage) |
| #17 / #18 | Buyer property-preferences reveals | Create Buyer · Hire Buyer |
| #25 | water-frontage placeholders | Create Seller · Create Landlord (Create-only) |
| #6 / #7 | upload limits (deploy ini) | Create Seller · Create Landlord |

---

# Owner decisions & intentional non-fixes (read before QA)

- **#29 — verify-only.** Already at parity (BS 5.2.2 `-sm` sizing). No code change. Confirm visually; a real mismatch would be a *new* ticket, not a FAIL of this change.
- **#33 — closed, no code change.** The Commute Preferences block was **not** removed: only one block exists per Create flow and none in Hire, so there was no duplicate to delete. If you happen to see a genuine duplicate block directly under Non-Negotiable Amenities, flag it — otherwise nothing to test. *(Not listed as a test item.)*
- **#25 — Create-only.** Water Frontage / Waterfront Feet were intentionally **not** added to Hire flows. Their absence on Hire is correct.
- **#31 — Tenant wording.** Tenant Description placeholder is deliberately `Enter tenant description`, not "rental".
- **#2 / #4 — server-verified.** #2 needs only a happy-path confirmation. #4's server path is proven clean; if Business submit still blocks, the defect is front-end — capture it in devtools.
- **"C3" is not an Offer/Hire issue** — it belongs to the separate Match-Check / Matching-V2 thread, out of scope here.

---

# Suggested testing order (minimise page switching)

Grouped by flow so each form is opened once. Shared-partial items are folded into the flow where they naturally appear; use the matrix above to remember the other flows.

1. **Create Seller** — pets `#12`, Vacant-Land style Other `#14`, water frontage `#25`, exchange item `#8`, additional sale terms box `#21`, HOA notes `#27`, 5 disclosure boxes `#22`, Business submit `#4`, uploads `#6 #7`, description `#31`, credentials `#26`, then edit-redirect `#3`.
2. **Hire Seller** — pets `#12`, address tooltips `#23`, exchange stays/Other `#8 #9`, value/condition typing `#10`, currency `#11`, additional-details title `#30`.
3. **Create Landlord** — broker/agency terms `#1`, pets `#12`, water frontage `#25`, Vacant-Land style Other `#14`, lease-term Other placeholder `#13`, submit + draft + edit `#2`, uploads `#6 #7`, description `#31`.
4. **Hire Landlord** — pets `#12`, address tooltips `#23`, appliances placeholder `#24`, additional-details title `#30`.
5. **Create Buyer** — lease-purchase cap `#32`, Purchase-Purpose & Flood-Zone Other `#17 #18`, map county-bias text + Important Places `#28 #29`, description `#31`.
6. **Hire Buyer** — garage reveal `#15`, min bed/bath Other `#16`, Purchase-Purpose & Flood-Zone Other `#17 #18`, HOA fee reveal `#19`, map `#28 #29`, additional-details title `#30`, currency `#11`.
7. **Create Tenant** — map county-bias + Important Places `#28 #29`, description `#31`.
8. **Hire Tenant** — Rental Purpose + Accessibility `#20`, other-appliances placeholder `#24`, map `#28 #29`, currency incl. leading dot `#11`, additional-details title `#30`.
9. **Detail pages** — video embed + fallback on Seller / Landlord / Tenant detail views `#5`.

---

# Summary — all implemented issues

| # | Batch | Type | Fix | Primary flow(s) |
|---|-------|------|-----|-----------------|
| #3 | A | impl | Seller edit redirects to detail page | Create Seller |
| #12 | A | impl | Pet fields → text inputs | Create+Hire Seller/Landlord |
| #13 | A | impl | Lease-term Other placeholder | Create Landlord |
| #32 | A | impl | Lease-purchase "Seller" cap | Create Buyer |
| #21 | A | verify | Sale-terms textarea sizing | Create Seller |
| #22 | A | verify | 5 disclosure textareas sizing | Create Seller |
| #1 | B | impl | Landlord broker + agency terms | Create Landlord |
| #5 | B | impl | Video embed + fallback | Seller/Landlord/Tenant detail |
| #2 | B | verify | Landlord submit/draft/edit | Create Landlord |
| #4 | B | diag | Seller Business submit | Create Seller · Business |
| #6 | C | impl | 14-JPG upload | Create Seller/Landlord |
| #7 | C | impl | Large-doc upload + error | Create Seller/Landlord |
| #8 | D | impl | Exchange item stays selected | Create+Hire Seller |
| #9 | D | verify | Exchange "Other" input | Hire Seller |
| #10 | D | impl | Value/Condition typing stable | Hire Seller |
| #11 | D | impl | Currency decimals/leading dot | all Hire |
| #14 | D | impl | Vacant-Land style Other reveal | Create Seller/Landlord |
| #15 | D | verify | Garage Yes reveals fields | Hire Buyer |
| #16 | D | verify | Min bed/bath Other keeps select | Hire Buyer |
| #17 | D | verify | Purchase Purpose Other | Create+Hire Buyer |
| #18 | D | verify | Flood Zone Other | Create+Hire Buyer |
| #19 | D | verify | HOA Yes/Flexible fee reveal | Hire Buyer |
| #20 | E | impl | Rental Purpose + Accessibility | Hire Tenant |
| #23 | F-1 | impl | Address tooltip line break | Hire Seller/Landlord |
| #24 | F-1 | impl | Appliance placeholder case | Hire Landlord/Tenant |
| #25 | F-1 | impl | Water frontage placeholder title | Create Seller/Landlord |
| #26 | F-1 | impl | Agent credentials examples removed | all Create + Hire |
| #27 | F-1 | impl | HOA notes capitalisation | Create Seller |
| #30 | F-1 | impl | Hire additional-details lowercase | all Hire |
| #31 | F-1 | impl | Create description per role | all Create |
| #28 | F-2 | impl | Remove county-bias helper text | Create+Hire Buyer/Tenant |
| #29 | F-2 | verify | Important Places sizing parity | Create+Hire Buyer/Tenant |

**32 test items.** `impl` = implemented fix · `verify` = verify-only · `diag` = diagnose if it still fails.
#33 is closed (no duplicate found) and is not a test item.

---

*Generated read-only from the audit ledger at `be0aa4f98`. Nothing is PASS in the ledger until confirmed here.*
