# Buyer Criteria Bid Form — Field & Property-Type Audit

**Form:** `resources/views/buyer_criteria/add-bid.blade.php`
**Controller:** `app/Http/Controllers/BuyerCriteriaAuctionBidController.php`
**Audit Date:** 2026-06-15

---

## 1. Wizard Step Map by Property Type

The form has two top-level branches driven by `property_listed` (step 2):

| Branch | Steps | Description |
|---|---|---|
| `property_listed = No` | 3–92 (type-gated) | Property not yet listed; full criteria intake |
| `property_listed = Yes` | 89–92 | Already-listed property; shorter path |

### Shared / Common Steps (all property types)

| Steps | Purpose |
|---|---|
| 1 | Landing / intro |
| 2 | Is property listed? (branches here) |
| 4 (embedded in step 1 DOM) | Purchase Offer Terms — price, financing, possession date, home warranty, seller contribution, included/excluded items, custom terms |
| 5 | Property Type selection + Style picker |
| 94 | Property Description (**required**, min 10 chars) |
| 95 | Property Highlights (multi-select + free text) |
| 96 | Why This Property Matches (optional textarea) |
| 97 | Compromises & Concessions (optional textarea) |
| 98 | Negotiation Timeline (optional textarea) + Closing / Response dates |

### Residential Property (property_listed = No)

Route after step 5: `5 → 6 → 8 → 10 → … → 38 → 94`

| Step Range | Key Fields |
|---|---|
| 6 | Special sale provisions, assignment contract, contract assignment fee |
| 7 (×2, duplicate DOM) | Property sub-type (Single Family, Condo, Townhouse, etc.) |
| 8 | Bedrooms / bathrooms |
| 9 | HOA, CDD/special assessments |
| 10 | Year built range |
| 11–15 | Lot size, garage, pool, waterfront |
| 16–20 | School district, pets, desired features, required features |
| 21–25 | Inspection, contingencies, financing type (term_financings) |
| 26–30 | Move-in date, earnest money, closing timeframe |
| 31–34 | Additional notes, referral preferences |
| 35–37 | Agent info (license, brokerage, agency agreement) |
| 38 | Media / availability (vimeo link) — **terminal Residential step** |

### Income Property (property_listed = No)

Route after step 5: `5 → 6 → 9 → 10 → … → 38 → 94`

Shares steps 10–38 with Residential. Income-specific steps:

| Step | Key Fields |
|---|---|
| 6 | Special sale provisions |
| 9 | Number of units, property class, cap rate range |

All other steps (10–38) identical to Residential. Income Property uses the same terminal at step 38.

### Commercial Property & Business Opportunity (property_listed = No)

Route after step 5: `5 → 39 → 41 → … → 72 → 94`

| Step Range | Key Fields |
|---|---|
| 39 | Commercial property sub-type / business type |
| 40 | Special sale provisions (commercial) |
| 41 | Building class, zoning, permitted uses |
| 42–50 | Square footage, lot size, traffic count, parking |
| 51–58 | Lease type, tenant mix, NOI, cap rate |
| 59–65 | Environmental, ADA, title, contingencies |
| 66–71 | Agent info (license, brokerage, agency agreement) |
| 72 | Media / availability — **terminal Commercial/Business step** |

### Vacant Land (property_listed = No)

Route after step 5: `5 → 73 → … → 92 → 94`

| Step Range | Key Fields |
|---|---|
| 73 | Land sub-type (Residential Lot, Agricultural, etc.) |
| 74–76 | Acreage range, zoning, road frontage |
| 77–80 | Utilities (water, sewer, electric, gas) |
| 81–83 | Flood zone, wetlands, environmental constraints |
| 84–86 | Development feasibility, soil testing |
| 87–88 | Contingencies, financing |
| 89–91 | Agent info (license, brokerage, agency agreement) |
| 92 | Media / availability — **terminal Vacant Land step** |

### property_listed = Yes (any property type)

Route: `2 → 89 → 92 → 94` (shortened path, pre-listed property)

| Step | Key Fields |
|---|---|
| 89 | Confirm listing details |
| 90 | Agent commission offer (co-op — does seller offer buyer's agent commission?) |
| 91 | Agent info |
| 92 | Media / availability |

---

## 2. Property Highlights List — Justification

The 21-item highlights list (step 95) was designed as **universally applicable** across all five property types:

```
Move-in ready, Recently renovated, Updated kitchen, Updated bathrooms,
Open floor plan, Natural light, Private backyard, Waterfront / water view,
Corner lot, Cul-de-sac, New roof, New HVAC, Energy efficient,
Solar panels, Smart home features, Gated community, Low HOA fees,
No HOA, Pool, Garage / parking, Other
```

**Why a single shared list:**
- Buyer Criteria Auctions are driven by buyer preference, not property-type-specific data sheets.
  Highlights represent selling points the bidding agent wishes to emphasize relative to the buyer's stated criteria.
- Commercial, Business, and Vacant Land bids arrive at step 95 after completing their property-type-specific
  steps (39–72, 73–92 respectively), so those bids already carry granular property-type data.
- The "Other" option with a free-text field covers any property-type-specific highlight not in the list
  (e.g., "Triple-net lease in place", "Paved county road access", "Approved building plans included").

**Not a closed set:** The "Other" + free-text field makes this list extensible without code changes.
Future tasks may decompose this into property-type-specific sub-lists (#2824 scope).

---

## 3. JS Criteria Match Badge Engine — Rationale

### Why a custom JS function instead of reusing `BuyerBidMatchScoreHelper`

`BuyerBidMatchScoreHelper` (PHP, `app/Helpers/BuyerBidMatchScoreHelper.php`) is a **server-side,
post-submission** match scoring engine. It:
- Runs after a bid is submitted and stored
- Computes a weighted score across 16+ logical field groups (commission structure, lease options, retainer fees, etc.)
- Powers the view-page match score displayed to listing owners
- Requires Eloquent model instances and database access

`updateCriteriaBadges()` (JS, in `add-bid.blade.php`) is a **client-side, pre-submission** real-time
feedback tool. It:
- Runs in the browser as the agent fills in their bid
- Shows ✓ Match / ✗ No match against the buyer's stated criteria (the Checklist panel)
- Operates only on fields visible in the Checklist: property_type, price, beds, baths, sqft, city, county, state, financing
- Uses simple comparison rules (exact, gte, lte) sufficient for real-time feedback

These two systems are **complementary, not redundant**:
- JS badges = in-form guidance to help the agent align their bid BEFORE submitting
- PHP helper = authoritative post-submission score shown to the listing owner (buyer)

Reusing the PHP helper in JS would require either:
1. An AJAX endpoint called on every form change (latency, server load), or
2. Porting the full 16-group scoring logic to JavaScript (maintenance burden, drift risk)

Neither is appropriate for a quick visual-feedback widget. The JS engine intentionally covers only
the Checklist-displayed fields and uses lightweight comparisons.

---

## 4. Location DNA Panel — Safety Verification

**Model:** `app/Models/PropertyLocationDna.php`
- `summary_json` is cast as `array` in the model's `$casts` declaration → PHP decodes JSON automatically

**Blade panel** (lines 342–450, `add-bid.blade.php`):
```php
$dnaPrefs = ($locationDna && is_array($locationDna->summary_json))
    ? $locationDna->summary_json
    : [];
```
- Double guard: `$locationDna` null-check AND `is_array()` before any array access
- All sub-keys use `?? []` or `?? false` or `?? ''` null-coalescing
- Radius search sub-arrays access `$r['label'] ?? 'Zone'` and `$r['radius_miles'] ?? '?'`
- Polygon labels access `$p['label'] ?? 'Area'`
- No raw `json_decode()` calls in the view; model cast handles decoding

**Verdict:** JSON decoding is safe. No XSS risk (Blade `{{ }}` auto-escapes output).

---

## 5. Commission Visibility — `agent_commission_offered`

**Field:** `agent_commission_offered` (step 90, property_listed="Yes" branch)

**What it captures:** Whether the seller will offer a co-op commission to the buyer's agent.
This is a seller-facing disclosure, not a compensation term visible to the public.

**Policy alignment:**
- The field IS persisted via `saveMeta()` (controller line 157) and available for internal use.
- It does NOT appear in `view.blade.php` (confirmed: no template display block exists).
- It was removed from the Preview modal in this task to align with the pattern established
  in `view.blade.php` — only fields rendered in the readonly bid card should appear in the preview.

---

## 6. Preview Modal — Alignment with View Card

The preview modal (`#offerPreviewModal`) surfaces the same fields that appear in the
`view.blade.php` bid accordion card, using the same label names. Fields not rendered in
`view.blade.php` (e.g., `agent_commission_offered`) were excluded from the preview after
the view-page audit above.

Fields covered in both preview and view card:
- Offer price, financing, bedrooms, bathrooms, sq ft
- County, state, possession date, response deadline, closing date
- Home warranty, seller contribution
- Included personal property, excluded items, custom terms
- Property description, highlights (as badges), why matches, compromises, negotiation notes

---

## 7. Five-Path Routing Verification Summary

All five property-type paths reach the new steps 94–98:

| Property Type | Terminal Before Step 94 | Routing |
|---|---|---|
| Residential Property | Step 38 | next-click `currentStep==38 && type==Residential/Income` → jump to 94 |
| Income Property | Step 38 | Same as Residential (shared terminal) |
| Commercial Property | Step 72 | next-click `currentStep==72 && type==Commercial/Business` → jump to 94 |
| Business Opportunity | Step 72 | Same as Commercial (shared terminal) |
| Vacant Land | Step 92 | next-click `currentStep==92 && type==other` (fallback) → jump to 94; also covered by the listed-property path at step 92 |
| property_listed=Yes | Step 92 | next-click `currentStep==92 && property_listed==Yes` → jump to 94 |

Back-navigation from each new step uses `data-step` attribute comparison (not DOM index)
to avoid offset from the duplicate `data-step="7"` element in the DOM.
