# Location DNA v2 Verification Report — Listing #183

**Property:** 11687 Oxford Street, Seminole, FL 33772
**Verification Date:** 2026-06-22
**Pipeline Run:** 2026-06-22 23:46:05 UTC
**Verifier:** Task #3145 automated verification

---

## Table of Contents
1. [Pipeline Run Verification](#section-1)
2. [Top 3 Per Category — Data Completeness](#section-2)
3. [Top Rated Dining Block](#section-3)
4. [Category Exclusion Cleanup](#section-4)
5. [Distance Correctness](#section-5)
6. [Duplicate Category Check](#section-6)
7. [Blank Cards and PHP Error Check](#section-7)
8. [Admin DNA Inspector — Data Completeness](#section-8)
9. [Consumer Reasonableness Review](#section-9)
10. [Final Verdict](#section-10)

---

<a name="section-1"></a>
## Section 1 — Pipeline Run Verification

**Command:** `php artisan ldna:refresh-all --listing-type=seller_agent`
**Result:** ✅ **PASS**

```
Found 2 PropertyLocationDna record(s) to refresh.
Cleared 38 existing POI row(s) — fresh candidates will be fetched.
[1/2] Refreshing seller_agent / 121 ...
  ✓ seller_agent / 121 — success
[2/2] Refreshing seller_agent / 183 ...
  ✓ seller_agent / 183 — success

Done. Success: 2 / Fail: 0 / Total: 2
```

- No errors, no exceptions, no failed categories
- Listing #183 refreshed at 2026-06-22 23:46:05 UTC
- DNA record id=152 | geocode_status=geocoded | lat=27.8306804, lng=−82.800585

---

<a name="section-2"></a>
## Section 2 — Top 3 Per Category — Data Completeness

**Result:** ✅ **PASS**

### Database State

| Metric | Value |
|---|---|
| Distinct categories stored | **20** (19 regular + `top_rated_dining`) |
| Total POI candidate rows | **193** (10 per regular category + 3 for top_rated_dining) |
| Null `rank` rows | **0** — all rows have rank |
| Null `types_json` rows | **0** — all rows have types |
| Null `rating` rows (status=found) | **24** — expected for places with no Google reviews (schools, transit stops) |
| Null `user_ratings_total` (status=found) | **24** — same 24 rows as above |

The presenter (`LocationDnaPresenter::poisByCategory($pois, topN=3)`) selects the top 3 by rank for each category. All 20 categories have ≥3 stored candidates except `top_rated_dining` which derives exactly 3 from restaurant candidates.

### All 20 Categories — Top 3 Summary

| Category | Rank 1 | Rank 2 | Rank 3 |
|---|---|---|---|
| **beach** | Indian Shores — 1.91 mi ⭐4.8 (132) | Archibald Beach Park — 1.99 mi ⭐4.7 (3117) | Redington Shores Beach Access — 2.07 mi ⭐4.7 (575) |
| **beach_access** | Public Beach Access — 1.62 mi ⭐4.2 (13) | Public Beach Access — 1.69 mi ⭐4.8 (5) | Public Beach Access — 1.81 mi ⭐4.1 (9) |
| **boat_ramp** | Lake Seminole Park Boat Ramp — 1.70 mi ⭐4.7 (92) | Lake Seminole Park — 1.74 mi ⭐4.7 (3510) | Madeira Beach Marina — 1.85 mi ⭐4.7 (504) |
| **coffee_shop** | Einstein Bros. Bagels — 0.75 mi ⭐3.8 (508) | BB House — 0.83 mi ⭐5.0 (4) | McDonald's — 0.94 mi ⭐3.6 (1986) |
| **dog_park** | Dog Park — 0.85 mi ⭐4.0 (1) | Boca Ciega Millennium Park Dog Park — 0.86 mi ⭐4.5 (48) | Boca Ciega Millennium Park — 0.88 mi ⭐4.7 (1377) |
| **fitness_center** | SPENGA Seminole — 0.83 mi ⭐4.8 (164) | LA Fitness — 0.95 mi ⭐4.1 (712) | Crunch Fitness - Seminole — 1.02 mi ⭐4.2 (1004) |
| **gas_station** | bp — 0.72 mi ⭐3.8 (32) | Angelo Mini Mart — 0.85 mi ⭐3.3 (86) | Chevron Quick Stop — 0.87 mi ⭐1.8 (14) |
| **golf_course** | Seminole Lake Country Club — 2.30 mi ⭐4.2 (338) | Bardmoor Golf & Tennis Club — 3.84 mi ⭐3.6 (751) | Bayou Club — 3.90 mi ⭐4.2 (357) |
| **grocery_store** | bp — 0.72 mi ⭐3.8 (32) | B S Food & Gas — 0.72 mi ⭐3.7 (3) | Publix Super Market — 0.93 mi ⭐4.6 (2383) |
| **gym** | Your Pilates Lifestyle — 0.80 mi ⭐5.0 (4) | Health Fitness And Well Being — 0.81 mi (no rating) | Corrective Personal Training — 0.81 mi ⭐5.0 (7) |
| **hospital** | AFC Urgent Care Seminole — 0.85 mi ⭐4.5 (1925) | Publix Pharmacy on 113th St — 0.93 mi ⭐3.7 (6) | Gulf Breeze Dental — 1.02 mi ⭐4.7 (46) |
| **marina** | Madeira Beach Marina — 1.85 mi ⭐4.7 (504) | Freedom Boat Club — 1.86 mi ⭐4.8 (152) | Bowden's Marina — 1.86 mi ⭐3.7 (3) |
| **park** | Big Hook island — 0.55 mi (no rating) | Seminole City Park — 0.63 mi ⭐4.7 (1201) | Tidal Waterfall — 0.69 mi ⭐4.5 (2) |
| **pharmacy** | Publix Pharmacy on 113th St — 0.93 mi ⭐3.7 (6) | Carepoint Pharmacy — 0.99 mi ⭐2.1 (10) | Walgreens Pharmacy — 1.08 mi ⭐3.4 (35) |
| **restaurant** | Einstein Bros. Bagels — 0.75 mi ⭐3.8 (508) | Toss Salads & Wraps — 0.75 mi ⭐4.6 (56) | Sushi & Soy Japanese — 0.76 mi ⭐4.7 (289) |
| **school** | Blessed Sacrament Catholic School — 0.23 mi (no Google rating) | Faith Community Preschool — 0.40 mi ⭐5.0 (1) | Bay Pines Lutheran School — 0.77 mi (no rating) |
| **shopping_center** | Seminole City Center — 0.85 mi ⭐4.4 (10) | Gallery Oaks Shopping Center — 0.85 mi ⭐4.5 (114) | Seminole City Center — 1.00 mi ⭐4.5 (4525) |
| **top_rated_dining** | Noel's Awesome Hotdogs — 0.85 mi ⭐5.0 (19) | Gulfside Tap Room ll — 0.84 mi ⭐4.9 (83) | Sushi & Soy Japanese — 0.76 mi ⭐4.7 (289) |
| **transit_station** | Park Blvd + 110th St — 0.82 mi ⭐4.5 (2) | Seminole Blvd + 64th Ave — 0.82 mi (no rating) | Seminole Blvd + The Pinellas Trl — 0.83 mi ⭐4.0 (1) |
| **waterfront_park** | Seminole Waterfront Park — 1.25 mi ⭐4.5 (311) | Freedom Lake Park — 6.66 mi ⭐4.6 (1838) | Edgewater Park — 12.51 mi ⭐4.7 (2807) |

---

<a name="section-3"></a>
## Section 3 — Top Rated Dining Block

**Result:** ✅ **PASS (appears and has data)** | ⚠️ **CONSUMER CONCERN (see Section 9)**

The v2 pipeline introduced the `top_rated_dining` derived category. It is built from restaurant candidates sorted by rating descending, filtered to minimum 10 reviews.

### Stored Top Rated Dining Candidates

| Rank | Name | Address | Rating | Reviews | Distance | Types |
|---|---|---|---|---|---|---|
| 1 | Noel's Awesome Hotdogs | 6585 Seminole Boulevard, Seminole | ⭐ 5.0 | 19 | 0.85 mi | meal_takeaway, restaurant |
| 2 | Gulfside Tap Room ll | 6032 Seminole Boulevard, Seminole | ⭐ 4.9 | 83 | 0.84 mi | bar, restaurant |
| 3 | Sushi & Soy Japanese restaurant | 11220 Park Boulevard, Seminole | ⭐ 4.7 | 289 | 0.76 mi | restaurant |

The block appears in both the public listing panel and the admin inspector. Rating and review count are displayed for all three entries. All three have `status=found`.

**Consumer concern flagged:** Noel's Awesome Hotdogs (rank 1, 5.0★/19 reviews) is a hot dog takeaway counter. It earns the top slot because it has the highest rating, but with only 19 reviews its 5.0 rating is statistically fragile and a hot dog stand may not represent the "top dining experience" a buyer expects from this coastal FL market. Gulfside Tap Room (rank 2, 4.9★/83) and Sushi & Soy (rank 3, 4.7★/289) are more representative of the local restaurant landscape. See Section 9.

---

<a name="section-4"></a>
## Section 4 — Category Exclusion Cleanup

### Pharmacy: Animal Hospital Exclusion
**Result:** ✅ **PASS**

Rank 1 pharmacy is now **Publix Pharmacy on 113th St** (types: `pharmacy, drugstore, hospital, health`).
The previous v1 rank 1 result — "Animal Hospital of Seminole, A Thrive Pet Healthcare Partner" — does not appear in any of the 10 stored pharmacy candidates. The `exclude_if_types_include: ['veterinary_care']` and `exclude_if_name_matches: '/animal\s+hospital/i'` rules successfully filtered all veterinary results during the pipeline run.

### Golf Course: Miniature/Adventure Golf Exclusion
**Result:** ✅ **PASS**

Rank 1 golf course is now **Seminole Lake Country Club** at 2.30 mi (a regulation country club).
Rank 2 is Bardmoor Golf & Tennis Club (3.84 mi), rank 3 is Bayou Club (3.90 mi). None of the 10 stored golf candidates contain "adventure golf," "mini golf," "miniature golf," or "putt-putt" in their names. The `exclude_if_name_matches: '/adventure\s+golf|mini.?golf|miniature\s+golf|putt.?putt/i'` rule successfully excluded Smugglers Cove Adventure Golf and all similar venues.

### Grocery Store: Gas Station Exclusion
**Result:** ⚠️ **PARTIAL — gas station still at rank 1 due to dual Google typing**

| Rank | Name | Types | Status |
|---|---|---|---|
| 1 | **bp** | `gas_station, car_wash, supermarket, grocery_or_supermarket, store, food` | ❌ Still showing — passes exclusion filter |
| 2 | **B S Food & Gas** | `supermarket, atm, grocery_or_supermarket, finance` | ⚠️ Also gas station adjacent (name contains "Gas") |
| 3 | **Publix Super Market** | `supermarket, florist, grocery_or_supermarket` | ✅ Correct grocery result |

**Root cause:** The v2 exclusion rule fires only when `gas_station` is in the types array AND `grocery_or_supermarket`/`supermarket` is absent. The BP location has Google types `["gas_station", "car_wash", "supermarket", "grocery_or_supermarket", ...]` — it carries both `gas_station` AND `grocery_or_supermarket`. Because it is not missing the grocery type, the rule does not exclude it. BP passes the filter by design.

A consumer who sees "bp" as the top result in the Grocery Store category is likely to find this misleading. The nearest true supermarket (Publix) is at rank 3, 0.93 mi away.

**This criterion is partially met.** Pure gas stations (with no grocery typing) would be correctly excluded. However, the BP station's dual-typing means it still occupies rank 1 for both `grocery_store` and `gas_station`. The exclusion rule as written does not distinguish a full-service grocery store from a gas station convenience store that Google additionally tags as `grocery_or_supermarket`.

---

<a name="section-5"></a>
## Section 5 — Distance Correctness

**Result:** ✅ **PASS**

All distances are stored as straight-line Haversine distances (Earth radius = 3958.8 miles). A sample re-verification for the 20 rank-1 results confirms all values are within ±0.005 mi (sub-26-foot rounding). The source coordinate (27.8306804, −82.800585) is confirmed correct for 11687 Oxford Street, Seminole FL.

**Display format:** Distances display as `X.XX mi` (2 decimal places) throughout the public panel and admin inspector. No blank distances observed for any `status=found` POI row.

**Road distance note:** All distances remain straight-line, not drive distance. The beach (1.91 mi straight-line) requires a ~3–4 mi drive via Alternate US-19 and Gulf Blvd. This distinction is not surfaced to consumers. Waterfront_park rank 2 (Freedom Lake Park) at 6.66 mi is unusually far from rank 1 (Seminole Waterfront Park at 1.25 mi), but both distances are correct.

---

<a name="section-6"></a>
## Section 6 — Duplicate Category Check

**Result:** ✅ **PASS**

All 20 stored categories are distinct:
```
beach, beach_access, boat_ramp, coffee_shop, dog_park, fitness_center,
gas_station, golf_course, grocery_store, gym, hospital, marina, park,
pharmacy, restaurant, school, shopping_center, top_rated_dining,
transit_station, waterfront_park
```

No duplicate category keys. The v1 structural issue where both `gym` and `fitness_center` mapped to `google_type=gym` (returning identical results) is now resolved: in v2, `fitness_center` rank 1 is **SPENGA Seminole** (0.83 mi, ⭐4.8/164) while `gym` rank 1 is **Your Pilates Lifestyle** (0.80 mi, ⭐5.0/4) — different venues.

---

<a name="section-7"></a>
## Section 7 — Blank Cards and PHP Error Check

**Result:** ✅ **PASS**

The public listing page (`/offer-listing/seller/view/183`) loads without any PHP errors, 500 responses, or visible exception traces. The page renders the listing correctly (address, price, photos, features visible). No blank Location DNA cards were observed.

All 20 categories have `status=found` for their rank 1 results; no category returned `status=not_found` or `status=error` in the v2 run. The admin DNA inspector card template (`location-dna-card.blade.php`) handles null-rating gracefully (renders `—`) so the 24 rows with null rating/review_count produce no rendering errors.

---

<a name="section-8"></a>
## Section 8 — Admin DNA Inspector — Data Completeness

**Result:** ⚠️ **PARTIAL — rank is shown implicitly; rating/review_count/types_json absent from regular POI grid**

### What the Admin Inspector Displays

The admin inspector at `/admin/dna/location/seller_agent/183` includes the `location-dna-card` partial, which renders:

1. **Location Narrative** — text summary
2. **Lifestyle Labels** — badge row
3. **Lifestyle Scores** — table with score bars
4. **Top Rated Dining table** — shows: rank (#), name, address, **rating**, **review count**, distance, status ✅
5. **Nearby Points of Interest grid** — shows all candidates per category (not just top 3), with rank embedded as `#N` prefix in the name for non-rank-1 results, and distance

### Gap — Regular POI Grid Missing Rating/Review Count/Types JSON

The regular POI grid cards show only **name** (with `#N` rank prefix for non-first rows) and **distance**. They do **not** show:
- A dedicated `rank` column (rank is embedded in the name string, not a separate field)
- `rating` for regular categories
- `user_ratings_total` for regular categories
- `types_json` (raw Google types array) for any category

These fields are stored in the database and are accessible via `php artisan ldna:audit-listing 183 --listing-type=seller_agent`, which dumps the complete JSON payload including `types_json` for every POI row. However, the visual admin inspector page does not surface them.

### Audit Command Verification

Running `ldna:audit-listing 183` confirms that the database records do contain rank, rating, user_ratings_total, and types_json for all 193 rows:
- Null rank: **0 rows** — all 193 rows have rank populated
- Null types_json: **0 rows** — all rows have types_json populated
- Null rating (found status): **24 rows** — only for places Google itself reports no rating (schools, small transit stops, etc.)

The data is present and correct in the database. The inspector UI gap is a presentation issue, not a data quality issue.

---

<a name="section-9"></a>
## Section 9 — Consumer Reasonableness Review

This section evaluates whether the Top 3 results for the six key categories (Beaches, Grocery, Parks, Schools, Closest Dining, Top Rated Dining) represent what a reasonable local consumer would expect for a property at 11687 Oxford Street, Seminole FL 33772 (Pinellas County, ~1.9 mi from Gulf beaches).

### Beaches (beach category)

| Rank | POI | Distance | Rating | Assessment |
|---|---|---|---|---|
| 1 | Indian Shores | 1.91 mi | ⭐4.8 (132) | ⚠️ **Municipality name.** "Indian Shores" is a town, not a named beach. The coordinate is on the Gulf coast but the name is the city name. Consumer will see "Indian Shores" and not know it's a beach. |
| 2 | Archibald Beach Park | 1.99 mi | ⭐4.7 (3117) | ✅ Correct — popular Pinellas County Gulf-front beach park with lifeguards and facilities. Excellent result. |
| 3 | Redington Shores Beach Access | 2.07 mi | ⭐4.7 (575) | ✅ Correct — verified Gulf-front beach access point in Redington Shores. |

**Consumer verdict:** Ranks 2–3 are exactly what a buyer would expect. Rank 1 is a real beach location but displays as a municipality name — potentially confusing but not factually wrong.

### Grocery Stores (grocery_store category)

| Rank | POI | Distance | Rating | Assessment |
|---|---|---|---|---|
| 1 | bp | 0.72 mi | ⭐3.8 (32) | ❌ **Gas station with convenience store.** A buyer asking "how close is the grocery store?" would not consider a BP gas station the answer. |
| 2 | B S Food & Gas | 0.72 mi | ⭐3.7 (3) | ❌ **Gas station food mart.** The name includes "Gas." Not a grocery store. |
| 3 | Publix Super Market on 113th St | 0.93 mi | ⭐4.6 (2383) | ✅ **Correct.** Publix is the dominant full-service grocery in Pinellas County and this is a legitimate result. |

**Consumer verdict:** MISLEADING. Two of the top 3 "grocery" results are gas stations. A consumer evaluating grocery access would find this confusing and potentially misleading. Publix is only 0.21 mi farther than BP (0.93 vs 0.72 mi), but ranks third. For Property DNA and lifestyle scoring purposes, grocery_store should not advertise BP or B S Food & Gas as grocery options.

### Parks (park category)

| Rank | POI | Distance | Rating | Assessment |
|---|---|---|---|---|
| 1 | Big Hook island | 0.55 mi | no rating | ⚠️ **Unverified natural area.** No rating, no reviews, no street address. "Big Hook island" appears to be a small natural island or tidal area accessible by water. Not a park with recreational facilities. |
| 2 | Seminole City Park | 0.63 mi | ⭐4.7 (1201) | ✅ **Excellent.** Developed municipal park with high rating and many reviews. This is what a buyer expects when hearing "nearest park." |
| 3 | Tidal Waterfall | 0.69 mi | ⭐4.5 (2) | ⚠️ **Interesting feature** — appears to be a decorative waterfall at Seminole City Park or adjacent area. Only 2 reviews. Likely part of the City Park complex rather than an independent park. |

**Consumer verdict:** Rank 1 is a natural area that a consumer walking to a park would not find useful. Rank 2 (Seminole City Park) is the correct consumer answer.

### Schools (school category)

| Rank | POI | Distance | Rating | Assessment |
|---|---|---|---|---|
| 1 | Blessed Sacrament Catholic School | 0.23 mi | no Google rating | ✅ Accredited K-8 Catholic school, 0.23 mi — excellent proximity result. |
| 2 | Faith Community Preschool | 0.40 mi | ⭐5.0 (1) | ✅ Valid preschool, very close. |
| 3 | Bay Pines Lutheran School | 0.77 mi | no Google rating | ✅ Valid K-8 Lutheran school. |

**Consumer verdict:** ✅ All three are legitimate schools. The top result (Blessed Sacrament, 0.23 mi) is the standout result — highly walkable school proximity is a strong selling point for this property. Note: no public elementary schools appear in top 3; buyers with public school preference should use district lookup. This is a data gap, not a filter error.

### Closest Dining (restaurant category)

| Rank | POI | Distance | Rating | Assessment |
|---|---|---|---|---|
| 1 | Einstein Bros. Bagels | 0.75 mi | ⭐3.8 (508) | ✅ Acceptable — fast casual bagel counter, correctly tagged as restaurant. Closest result. |
| 2 | Toss Salads & Wraps | 0.75 mi | ⭐4.6 (56) | ✅ Correct — fast casual restaurant. |
| 3 | Sushi & Soy Japanese restaurant | 0.76 mi | ⭐4.7 (289) | ✅ Correct — well-reviewed sit-down Japanese restaurant at 0.76 mi. |

**Consumer verdict:** ✅ All three are real restaurants. Ranks 2–3 are notably more appealing than rank 1 for a "dining" impression. The closest dining category now shows variety rather than a single bagel shop.

### Top Rated Dining (top_rated_dining category)

| Rank | POI | Distance | Rating | Reviews | Assessment |
|---|---|---|---|---|---|
| 1 | Noel's Awesome Hotdogs | 0.85 mi | ⭐5.0 | 19 | ⚠️ **Hot dog takeaway stand, 19 reviews.** A 5.0 rating from 19 reviews is statistically fragile. A buyer evaluating dining quality would not consider a hot dog stand the "top rated dining" near their potential home. |
| 2 | Gulfside Tap Room ll | 0.84 mi | ⭐4.9 | 83 | ✅ Bar and restaurant, reasonable result with 83 reviews. |
| 3 | Sushi & Soy Japanese restaurant | 0.76 mi | ⭐4.7 | 289 | ✅ Well-reviewed sit-down restaurant with substantial review volume. Best consumer-facing result of the three. |

**Consumer verdict:** ⚠️ MISLEADING as a "Top Rated" experience. Rank 1 (a hot dog stand with 19 reviews) does not represent the dining quality a Gulf-coastal Florida buyer would expect when told "top rated dining is 0.85 mi away." The minimum 10-review threshold filters out zero-review places but is insufficient to avoid low-volume review manipulation. Within 3–5 miles of this listing there are well-known Gulf-front seafood and waterfront restaurants with hundreds of reviews that would be more representative. Sushi & Soy (rank 3, 289 reviews) is the most consumer-credible result of the three.

**Root cause:** The v2 algorithm ranks by rating descending with only a 10-review minimum. A minimum of ~50 reviews would prevent micro-businesses with 5-star ratings from dominating.

---

<a name="section-10"></a>
## Section 10 — Final Verdict

### Per-Criterion Summary

| Criterion | Result | Notes |
|---|---|---|
| `ldna:refresh-all` completes without errors | ✅ **PASS** | 2/2 success, 0 fail |
| Top 3 results for all eligible categories | ✅ **PASS** | 10 candidates stored per category; presenter selects top 3 |
| Top Rated Dining block appears with rating and review count | ✅ **PASS** | 3 entries with rating and review count displayed |
| Grocery category no longer shows gas stations | ⚠️ **PARTIAL** | BP dual-typed (gas_station + grocery_or_supermarket); passes exclusion filter. B S Food & Gas also at rank 2. Publix at rank 3. |
| Pharmacy category no longer shows animal hospitals | ✅ **PASS** | Publix Pharmacy at rank 1; no veterinary results in any of 10 stored candidates |
| Golf category no longer shows miniature/adventure golf | ✅ **PASS** | Seminole Lake Country Club at rank 1; no adventure/mini-golf in stored candidates |
| Distances display correctly | ✅ **PASS** | All Haversine distances verified within ±0.005 mi |
| No duplicate categories | ✅ **PASS** | 20 distinct categories; gym and fitness_center now return different venues |
| No blank cards or PHP errors | ✅ **PASS** | Public listing page loads cleanly; no errors in any category |
| Admin inspector shows rank, rating, review count, types_json | ⚠️ **PARTIAL** | Top Rated Dining table shows all four fields; regular POI grid shows rank (embedded in name) and distance only — no separate rating/review_count/types_json columns |
| Consumer reasonableness (Top 3 key categories) | ⚠️ **ISSUES FOUND** | See Section 9 |

### Specific Issues Documented

| Priority | Issue | Affected Category | Consumer Impact |
|---|---|---|---|
| 🔴 P0 | BP gas station remains rank 1 grocery because Google dual-tags it `gas_station + grocery_or_supermarket`; ranks 1–2 are both gas-station-adjacent results | grocery_store | Lifestyle scores and narrative referencing "grocery access" remain based on a gas station convenience store |
| 🟠 P1 | Top Rated Dining rank 1 is a hot dog takeaway stand with 19 reviews (5.0★); the 10-review minimum is insufficient to surface quality-representative restaurants | top_rated_dining | Buyers and agents told "top rated dining is nearby" get a hot dog stand as the headline result |
| 🟠 P1 | Admin inspector UI does not surface `rating`, `user_ratings_total`, or `types_json` for regular POI categories — only the Top Rated Dining table shows these fields | admin UI | Admins reviewing POI quality cannot see rating/review data without running `ldna:audit-listing` from the CLI |
| 🟡 P2 | Beach rank 1 displays the municipality name "Indian Shores" rather than a named beach; Archibald Beach Park (rank 2) is the clearer consumer-facing result | beach | Minor consumer confusion about the beach name |
| 🟡 P2 | Park rank 1 "Big Hook island" is an unverified natural area with no rating/reviews; Seminole City Park (rank 2) is the correct consumer answer | park | Family score and park narrative reference an unverified natural area |
| 🟡 P2 | waterfront_park rank 2 (Freedom Lake Park) is 6.66 mi away — a large distance jump from rank 1 (1.25 mi) — suggesting sparse keyword-search results beyond 1.5 mi in this area | waterfront_park | Ranks 2–3 waterfront parks are in different cities (Pinellas Park, Dunedin) |

### Property DNA Readiness Verdict

> ## ❌ FAIL — Not Yet Ready for Property DNA

The v2 pipeline is a significant improvement over v1:
- Top-N candidate storage is working (10 candidates per category, fully ranked)
- Pharmacy and golf exclusion filters work correctly and completely
- Top Rated Dining is a real new feature with functional data
- All 193 stored rows have `rank` and `types_json` populated; zero null values for these fields
- Distances are mathematically correct

However, two blocking issues prevent a PASS for Property DNA readiness:

1. **Grocery category still returns gas stations at ranks 1–2.** BP and B S Food & Gas are the displayed "grocery stores" for Listing #183. Any Property DNA narrative, lifestyle score, or buyer match that cites grocery proximity is built on this misleading foundation. The exclusion rule needs a tighter condition — e.g., require that `grocery_or_supermarket` be a primary type (first in array) rather than an ancillary one, or add a name-exclusion pattern for "gas", "fuel", "bp", "chevron", etc.

2. **Admin DNA Inspector does not show rating/review_count/types_json for regular POI categories.** The task specification for this verification requires confirming that the inspector shows these fields. They are in the database but absent from the UI grid. This is a presentation gap that limits admin ability to spot-check POI quality without running CLI commands.

**Recommended minimum gate for readiness:**
1. Fix grocery exclusion to eliminate dual-typed gas station convenience stores (require a minimum review threshold or tighter type exclusivity).
2. Raise the Top Rated Dining minimum review threshold from 10 to ≥50 to avoid low-volume outliers dominating the ranking.
3. Add `rating`, `user_ratings_total`, and `types_json` display columns to the admin inspector's regular POI grid.

---

*Report generated: 2026-06-22 | Pipeline timestamp: 2026-06-22 23:46:05 UTC*
*Source data: `property_location_pois` where listing_type='seller_agent' AND listing_id=183 (193 rows)*
*Audit command: `php artisan ldna:audit-listing 183 --listing-type=seller_agent`*
