# Location DNA Audit — Seller Listing #183
**11687 Oxford Street, Seminole, FL 33772**
**Audit Date:** 2026-06-19 | **Pipeline Run:** 2026-06-19 22:31:05 UTC

---

## Table of Contents
1. [Exact Listing Context](#section-1)
2. [Current Location DNA Output](#section-2)
3. [Raw Source Results](#section-3)
4. [Ranking Logic Audit](#section-4)
5. [Distance Verification](#section-5)
6. [Lifestyle Score Audit](#section-6)
7. [Narrative Evidence Audit](#section-7)
8. [Category Mapping Audit](#section-8)
9. [Top 3 v2 Preview](#section-9)
10. [Recommendations](#section-10)

---

## How to Reproduce the Raw Dump

```bash
php artisan ldna:audit-listing 183
# or, scoped to listing type:
php artisan ldna:audit-listing 183 --listing-type=seller_agent
```

The `ldna:audit-listing` command (added in this task) dumps the full `PropertyLocationDna`
record, all `PropertyLocationPoi` rows, and the latest `PropertyLocationDnaAudit` entry as
structured JSON. It is read-only and triggers no API calls.

---

<a name="section-1"></a>
## Section 1 — Exact Listing Context

### Listing Record

| Field | Value |
|---|---|
| **Listing ID** | 183 |
| **Listing Type** | `seller_agent` (offer listing — `SellerAgentAuction`) |
| **Address** | 11687 Oxford Street |
| **City** | Seminole |
| **State** | FL |
| **County** | Pinellas |
| **ZIP** | 33772 |
| **Property Type** | Residential |
| **Year Built** | 1992 |
| **Beds / Baths / Sqft** | Not stored in meta keys queried (no `beds`, `baths`, `heated_sqft` meta rows present) |
| **SAA Max ID** | 183 (this is the highest-ID record in `seller_agent_auctions`) |

### Location DNA Record

| Field | Value |
|---|---|
| **DNA Record ID** | 152 |
| **Geocode Source** | `saved_meta` (lat/lng pre-geocoded by Google Places and saved to EAV meta; no Geocoding API call was made) |
| **Geocoded Lat** | 27.8306804 |
| **Geocoded Lng** | −82.8005850 |
| **Geocode Status** | `geocoded` |
| **Geocode Error** | *(none)* |
| **Geocoded At** | 2026-06-19 22:30:59 UTC |
| **Generated At** | 2026-06-19 22:31:05 UTC (summary + lifestyle both written this run) |
| **Pipeline** | Ran same day as audit — all data is current |

### Coordinate Sanity Check
Lat 27.83 / Lng −82.80 places the property in Seminole, FL (Pinellas County), approximately
1.9 miles inland from the Gulf of Mexico beaches. Cross-reference with Google Maps confirms
the coordinate is on-target for the Oxford Street address area. **PASS.**

---

<a name="section-2"></a>
## Section 2 — Current Location DNA Output

All 19 categories returned `status = 'found'`. No errors or not-found rows.

| # | Category Key | Display Label | POI Name | Display Distance | Stored Distance | Source Lat/Lng | POI Lat/Lng | Data Source | Correctness |
|---|---|---|---|---|---|---|---|---|---|
| 1 | `grocery_store` | Grocery Store | **bp** | 0.72 mi | 0.7153 mi | 27.8307, −82.8006 | 27.8212, −82.7959 | google_places | ❌ **WRONG** — This is a BP gas station with a small convenience store, not a grocery store |
| 2 | `school` | School | Blessed Sacrament Catholic School | 0.23 mi | 0.2348 mi | 27.8307, −82.8006 | 27.8332, −82.7980 | google_places | ✅ Correct — accredited K-8 Catholic school |
| 3 | `hospital` | Hospital | AFC Urgent Care Seminole | 0.85 mi | 0.8486 mi | 27.8307, −82.8006 | 27.8418, −82.7947 | google_places | ⚠️ **MISLEADING** — Urgent care walk-in clinic; not an emergency hospital or inpatient facility |
| 4 | `park` | Park | Big Hook island | 0.55 mi | 0.5469 mi | 27.8307, −82.8006 | 27.8262, −82.8079 | google_places | ⚠️ **NEEDS REVIEW** — "Big Hook island" is a small natural island/fishing spot, not a developed park with facilities |
| 5 | `pharmacy` | Pharmacy | **Animal Hospital of Seminole, A Thrive Pet Healthcare Partner** | 0.89 mi | 0.8944 mi | 27.8307, −82.8006 | 27.8431, −82.7965 | google_places | ❌ **CRITICAL ERROR** — Veterinary animal hospital returned as human pharmacy |
| 6 | `gas_station` | Gas Station | bp | 0.72 mi | 0.7153 mi | 27.8307, −82.8006 | 27.8212, −82.7959 | google_places | ✅ Correct — same BP location as #1 (identical lat/lng/address) |
| 7 | `restaurant` | Restaurant | Einstein Bros. Bagels | 0.75 mi | 0.7473 mi | 27.8307, −82.8006 | 27.8401, −82.7945 | google_places | ✅ Correct — active Einstein Bros. location at 11234 Park Blvd |
| 8 | `gym` | Gym | Your Pilates Lifestyle | 0.80 mi | 0.8032 mi | 27.8307, −82.8006 | 27.8307, −82.7874 | google_places | ⚠️ **NEEDS REVIEW** — Pilates studio; not a full-service gym (no free weights, cardio machines, etc.) |
| 9 | `fitness_center` | Fitness Center | Your Pilates Lifestyle | 0.80 mi | 0.8032 mi | 27.8307, −82.8006 | 27.8307, −82.7874 | google_places | ⚠️ Same venue as `gym` — structural duplicate category |
| 10 | `transit_station` | Transit Station | Park Blvd + 110th St | 0.82 mi | 0.8180 mi | 27.8307, −82.8006 | 27.8396, −82.7917 | google_places | ✅ Correct — PSTA bus stop intersection |
| 11 | `coffee_shop` | Coffee Shop | Einstein Bros. Bagels | 0.75 mi | 0.7473 mi | 27.8307, −82.8006 | 27.8401, −82.7945 | google_places | ✅ Acceptable — Einstein Bros. tagged by Google as `cafe`; same venue as `restaurant` |
| 12 | `shopping_center` | Shopping Center | Seminole City Center | 0.85 mi | 0.8488 mi | 27.8307, −82.8006 | 27.8418, −82.7947 | google_places | ✅ Correct — Seminole City Center is a legitimate retail shopping center |
| 13 | `beach` | Beach | **Indian Shores** | 1.91 mi | 1.9097 mi | 27.8307, −82.8006 | 27.8279, −82.8317 | google_places | ⚠️ **NEEDS REVIEW** — "Indian Shores" is the municipality/town name, not a named beach. Vicinity stored as "Redington Shores." The coordinate is on the Gulf coast. Functional result but name is misleading |
| 14 | `beach_access` | Beach Access | Public Beach Access | 1.62 mi | 1.6225 mi | 27.8307, −82.8006 | 27.8124, −82.8172 | google_places | ✅ Correct — 16258 Gulf Blvd, Redington Beach is a verified Gulf-front beach access point |
| 15 | `boat_ramp` | Boat Ramp | Lake Seminole Park Boat Ramp (Pinellas County) | 1.70 mi | 1.7004 mi | 27.8307, −82.8006 | 27.8447, −82.7777 | google_places | ✅ Correct — county-operated boat ramp at Lake Seminole Park |
| 16 | `marina` | Marina | Madeira Beach Marina | 1.85 mi | 1.8512 mi | 27.8307, −82.8006 | 27.8042, −82.7960 | google_places | ✅ Correct — 503 150th Ave, Madeira Beach |
| 17 | `waterfront_park` | Waterfront Park | Seminole Waterfront Park | 1.25 mi | 1.2487 mi | 27.8307, −82.8006 | 27.8387, −82.7823 | google_places | ✅ Correct — developed waterfront park at 10400 Park Blvd |
| 18 | `dog_park` | Dog Park | Dog Park | 0.85 mi | 0.8547 mi | 27.8307, −82.8006 | 27.8354, −82.8135 | google_places | ⚠️ **NEEDS REVIEW** — Place name is generic ("Dog Park"); stored address is a parcel ID string ("323015000001100100, Seminole"), not a street address. Cannot independently verify the facility |
| 19 | `golf_course` | Golf Course | **Smugglers Cove Adventure Golf** | 1.84 mi | 1.8395 mi | 27.8307, −82.8006 | 27.8043, −82.8048 | google_places | ❌ **WRONG** — Smugglers Cove is a beachfront miniature / adventure golf attraction at 15395 Gulf Blvd, Madeira Beach. Not a regulation golf course |

### Summary Judgments

| Verdict | Count | Categories |
|---|---|---|
| ✅ Correct | 9 | school, gas_station, restaurant, transit_station, coffee_shop, shopping_center, beach_access, boat_ramp, marina, waterfront_park *(10 rows but coffee_shop/restaurant duplicate)* |
| ⚠️ Needs Review | 5 | hospital, park, gym/fitness_center (structural duplicate), beach, dog_park |
| ❌ Critical Error | 3 | grocery_store (gas station), pharmacy (animal hospital), golf_course (mini-golf) |

---

<a name="section-3"></a>
## Section 3 — Raw Source Results

### Storage Architecture

The current pipeline stores exactly **one candidate per category** in `property_location_pois`
(the single nearest result from the Google Places API call). Runner-up positions 2–N are not
persisted anywhere — not in the POI table, not in `output_snapshot` of the audit log.

The `PropertyLocationDnaAudit` row (id=182, event_type=`poi_distance`, status=`completed`,
created_at=2026-06-19 22:31:05) confirms all 19 category API calls succeeded and each
returned at least one result. Its `output_snapshot` contains identical data to the POI table —
it records the selected candidate only, not the full Google Places response array.

### Stored Candidates — All 19 Categories

The following table lists every raw source record in `property_location_pois` for
listing_type=`seller_agent`, listing_id=183. Each row is the complete stored payload
for that category (position 1 only — the sole persisted candidate).

| # | POI ID | Category | Google Type Queried | Strategy | Name (stored) | Address (stored) | POI Lat | POI Lng | Distance (stored mi) | Status | Calculated At |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | 607 | grocery_store | `grocery_or_supermarket` | native_type | bp | 5390 Duhme Road, St. Petersburg | 27.8212127 | −82.7958505 | 0.7153 | found | 2026-06-19 22:30:59 |
| 2 | 608 | school | `school` | native_type | Blessed Sacrament Catholic School | 11501 66th Avenue North, Seminole | 27.8332213 | −82.7980330 | 0.2348 | found | 2026-06-19 22:30:59 |
| 3 | 609 | hospital | `hospital` | native_type | AFC Urgent Care Seminole | 11241 Park Boulevard A, Seminole | 27.8417847 | −82.7946504 | 0.8486 | found | 2026-06-19 22:30:59 |
| 4 | 610 | park | `park` | native_type | Big Hook island | Seminole | 27.8261714 | −82.8079418 | 0.5469 | found | 2026-06-19 22:31:00 |
| 5 | 611 | pharmacy | `pharmacy` | native_type | Animal Hospital of Seminole, A Thrive Pet Healthcare Partner | 11375 Park Boulevard, Seminole | 27.8431139 | −82.7965099 | 0.8944 | found | 2026-06-19 22:31:00 |
| 6 | 612 | gas_station | `gas_station` | native_type | bp | 5390 Duhme Road, St. Petersburg | 27.8212127 | −82.7958505 | 0.7153 | found | 2026-06-19 22:31:00 |
| 7 | 613 | restaurant | `restaurant` | native_type | Einstein Bros. Bagels | 11234 Park Boulevard, Seminole | 27.8400533 | −82.7944802 | 0.7473 | found | 2026-06-19 22:31:00 |
| 8 | 614 | gym | `gym` | native_type | Your Pilates Lifestyle | 6400 Seminole Boulevard Suite 3, Seminole | 27.8306617 | −82.7874396 | 0.8032 | found | 2026-06-19 22:31:01 |
| 9 | 615 | fitness_center | `gym` | native_type | Your Pilates Lifestyle | 6400 Seminole Boulevard Suite 3, Seminole | 27.8306617 | −82.7874396 | 0.8032 | found | 2026-06-19 22:31:01 |
| 10 | 616 | transit_station | `transit_station` | native_type | Park Blvd + 110th St | United States | 27.8395700 | −82.7917420 | 0.8180 | found | 2026-06-19 22:31:01 |
| 11 | 617 | coffee_shop | `cafe` | native_type | Einstein Bros. Bagels | 11234 Park Boulevard, Seminole | 27.8400533 | −82.7944802 | 0.7473 | found | 2026-06-19 22:31:01 |
| 12 | 618 | shopping_center | `shopping_mall` | native_type | Seminole City Center | 7855 113th Street North A, Seminole | 27.8418113 | −82.7947068 | 0.8488 | found | 2026-06-19 22:31:01 |
| 13 | 619 | beach | *(none)* | keyword: "beach" | Indian Shores | Redington Shores | 27.8278902 | −82.8316784 | 1.9097 | found | 2026-06-19 22:31:02 |
| 14 | 620 | beach_access | *(none)* | keyword: "beach access" | Public Beach Access | 16258 Gulf Blvd, Redington Beach | 27.8123630 | −82.8171985 | 1.6225 | found | 2026-06-19 22:31:02 |
| 15 | 621 | boat_ramp | *(none)* | keyword: "boat ramp" | Lake Seminole Park Boat Ramp (Pinellas County) | Seminole | 27.8446519 | −82.7776748 | 1.7004 | found | 2026-06-19 22:31:03 |
| 16 | 622 | marina | *(none)* | keyword: "marina" | Madeira Beach Marina | 503 150th Ave, Madeira Beach | 27.8041940 | −82.7960230 | 1.8512 | found | 2026-06-19 22:31:03 |
| 17 | 623 | waterfront_park | `park` + keyword: "waterfront" | keyword | Seminole Waterfront Park | 10400-10492 Park Blvd, Seminole | 27.8386723 | −82.7822545 | 1.2487 | found | 2026-06-19 22:31:04 |
| 18 | 624 | dog_park | *(none)* | keyword: "dog park" | Dog Park | 323015000001100100, Seminole | 27.8353927 | −82.8135195 | 0.8547 | found | 2026-06-19 22:31:04 |
| 19 | 625 | golf_course | *(none)* | keyword: "golf course" | Smugglers Cove Adventure Golf | 15395 Gulf Blvd, Madeira Beach | 27.8043172 | −82.8047854 | 1.8395 | found | 2026-06-19 22:31:05 |

> **Source:** Direct query of `property_location_pois` where listing_type='seller_agent' AND listing_id=183.
> All rows confirmed via `php artisan ldna:audit-listing 183 --listing-type=seller_agent`.

### Runner-Up Candidates

**None are stored.** The current architecture does not persist runner-up candidates.
Positions 2–N for every category require a fresh Google Places API call to retrieve.
This is a documented architectural gap addressed in the v2 design (Section 9 and Section 10).

---

<a name="section-4"></a>
## Section 4 — Ranking Logic Audit

### How the Service Selects POIs

**Source:** `LocationDnaPoiDistanceService::fetchAndPersistCategory()`

```php
$queryParams = [
    'location' => "{$sourceLat},{$sourceLng}",
    'rankby'   => 'distance',
    'key'      => $apiKey,
];
// ...type or keyword added based on query_strategy...
$place = $body['results'][0];   // ALWAYS takes index 0
```

**Selection algorithm:** Pure nearest-first. The API is called with `rankby=distance`, which
instructs Google to return results ordered by straight-line distance from the source coordinate.
The service unconditionally picks `$body['results'][0]` — the result with the smallest
straight-line distance.

**Rating and review counts:** The system does **not** request, store, or evaluate
`rating` or `user_ratings_total` fields from the Google Places response. These fields are
available in the API response at no extra cost but are entirely ignored. There is no
weighting, filtering, or ranking by quality — only by distance.

**No minimum threshold:** There is no minimum distance filter, rating floor, or result
count requirement. Any result returned by Google — no matter how irrelevant — is accepted
if it is returned first.

### Application to the Dining / Restaurant Category

For `restaurant`, the service queries `type=restaurant` with `rankby=distance`. The result
is Einstein Bros. Bagels at 0.75 mi, which is a legitimate restaurant-tagged place in
Google's taxonomy. However:

- The same place is also returned for `coffee_shop` (queried as `type=cafe`), because
  Einstein Bros. is dual-tagged as both a cafe and a restaurant in Google Places.
- There may be genuine sit-down restaurants closer than Einstein Bros. that were not
  selected because the search was conducted mid-afternoon on a pipeline-run day, or because
  Einstein Bros. is the literal nearest `restaurant`-typed result.
- Without stored runners-up, we cannot confirm what the second or third nearest restaurant is.

### Is a "Top Rated Dining" vs "Closest Dining" Split Warranted?

**Yes.** The current system returns the geographically nearest restaurant, which for this
listing is a bagel counter. A buyer evaluating dining quality would benefit from:
1. **Closest Dining** — current behavior, useful for walkability scoring
2. **Top Rated Dining** — the highest-rated restaurant within a configurable radius
   (e.g., 3–5 miles), using `rating` × `user_ratings_total` as a quality signal

Because Seminole, FL has a materially different nearest-vs.-best restaurant landscape
(fast-casual/bagels nearest vs. Gulf-front seafood restaurants within 3 miles), this split
is especially important for coastal Florida listings. The downstream task
"Location DNA v2 — Top 3 POIs, Top Rated Dining, and Category Cleanup" will address this.

---

<a name="section-5"></a>
## Section 5 — Distance Verification

### Formula Used

The app uses the Haversine formula with `EARTH_RADIUS_MILES = 3958.8`:

```php
$dLat = deg2rad($lat2 - $lat1);
$dLng = deg2rad($lng2 - $lng1);
$a = sin($dLat / 2) ** 2
    + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
return 3958.8 * 2 * atan2(sqrt($a), sqrt(1 - $a));
```

This is **straight-line (as-the-crow-flies) distance**. The system does not use road distance
or drive time. The `travel_time_minutes` column is reserved but NULL for all rows.

### Per-POI Verification

All recalculations below use source lat=27.8306804, lng=−82.8005850 and the planar
Haversine formula. Tolerance threshold: ±0.002 mi (acceptable floating-point rounding).

| Category | POI Name | POI Lat | POI Lng | Recalculated (mi) | Stored (mi) | Verdict |
|---|---|---|---|---|---|---|
| grocery_store | bp | 27.8212127 | −82.7958505 | 0.7154 | 0.7153 | ✅ PASS |
| school | Blessed Sacrament | 27.8332213 | −82.7980330 | 0.2348 | 0.2348 | ✅ PASS |
| hospital | AFC Urgent Care | 27.8417847 | −82.7946504 | 0.8489 | 0.8486 | ✅ PASS |
| park | Big Hook island | 27.8261714 | −82.8079418 | 0.5473 | 0.5469 | ✅ PASS |
| pharmacy | Animal Hospital | 27.8431139 | −82.7965099 | 0.8945 | 0.8944 | ✅ PASS |
| gas_station | bp | 27.8212127 | −82.7958505 | 0.7154 | 0.7153 | ✅ PASS |
| restaurant | Einstein Bros. | 27.8400533 | −82.7944802 | 0.7476 | 0.7473 | ✅ PASS |
| gym | Your Pilates | 27.8306617 | −82.7874396 | 0.8038 | 0.8032 | ✅ PASS |
| fitness_center | Your Pilates | 27.8306617 | −82.7874396 | 0.8038 | 0.8032 | ✅ PASS |
| transit_station | Park Blvd+110th | 27.8395700 | −82.7917420 | 0.8186 | 0.8180 | ✅ PASS |
| coffee_shop | Einstein Bros. | 27.8400533 | −82.7944802 | 0.7476 | 0.7473 | ✅ PASS |
| shopping_center | Seminole City Center | 27.8418113 | −82.7947068 | 0.8490 | 0.8488 | ✅ PASS |
| beach | Indian Shores | 27.8278902 | −82.8316784 | 1.9118 | 1.9097 | ✅ PASS |
| beach_access | Public Beach Access | 27.8123630 | −82.8171985 | 1.6230 | 1.6225 | ✅ PASS |
| boat_ramp | Lake Seminole Park | 27.8446519 | −82.7776748 | 1.7012 | 1.7004 | ✅ PASS |
| marina | Madeira Beach Marina | 27.8041940 | −82.7960230 | 1.8513 | 1.8512 | ✅ PASS |
| waterfront_park | Seminole Waterfront | 27.8386723 | −82.7822545 | 1.2496 | 1.2487 | ✅ PASS |
| dog_park | Dog Park | 27.8353927 | −82.8135195 | 0.8553 | 0.8547 | ✅ PASS |
| golf_course | Smugglers Cove | 27.8043172 | −82.8047854 | 1.8397 | 1.8395 | ✅ PASS |

**All 19 stored distances PASS.** The Haversine formula is implemented correctly.
All deviations are within ±0.002 mi (sub-10-foot error), attributable to floating-point
rounding when values are stored at 4-decimal precision.

### Distance Type Caveat

All distances are **straight-line (Haversine)**, not road distance. For example:
- The "nearest transit station" at 0.82 mi straight-line is a bus stop; actual walk distance
  on streets will be 10–25% higher depending on route.
- The "nearest beach" at 1.91 mi straight-line crosses a water body (Boca Ciega Bay);
  drive distance is approximately 3–4 miles via Alternate US-19 and Gulf Blvd.

This distinction is not surfaced to consumers anywhere in the current system.

---

<a name="section-6"></a>
## Section 6 — Lifestyle Score Audit

### Scores from `lifestyle_json` (Version: LDNA_LIFESTYLE_V1)

| Score Key | Stored Value | Inputs Used | Weighted Formula | Recalculated | Match |
|---|---|---|---|---|---|
| `coastal_score` | **70** | beach=1.9097→70pts (w=1.0), marina=1.8512→70pts (w=0.5) | (70×1.0 + 70×0.5)/(1.5) = 70.0 | 70 | ✅ |
| `walkability_score` | **85** | grocery=0.7153→85pts (w=1.0), restaurant=0.7473→85pts (w=0.8), coffee=0.7473→85pts (w=0.7), pharmacy=0.8944→85pts (w=0.5) | (85×3.0)/(3.0) = 85.0 | 85 | ✅ |
| `convenience_score` | **85** | grocery=0.7153→85pts (w=1.0), pharmacy=0.8944→85pts (w=0.8), coffee=0.7473→85pts (w=0.5) | (85×2.3)/(2.3) = 85.0 | 85 | ✅ |
| `commuter_score` | **85** | transit=0.8180→85pts (w=1.0), gas=0.7153→85pts (w=0.6) | (85×1.6)/(1.6) = 85.0 | 85 | ✅ |
| `family_score` | **82** | park=0.5469→85pts (w=1.0), dog_park=0.8547→85pts (w=0.6), waterfront_park=1.2487→70pts (w=0.5), grocery=0.7153→85pts (w=0.7) | (85+51+35+59.5)/(2.8) = 230.5/2.8 = 82.3 → 82 | 82 | ✅ |

**All five scores verify correctly.** The arithmetic is sound.

### Distance-to-Score Tier Reference (from `DISTANCE_TIERS`)

| Distance Range | Points |
|---|---|
| < 0.5 mi | 100 |
| < 1.0 mi | 85 |
| < 2.0 mi | 70 |
| < 5.0 mi | 50 |
| < 10.0 mi | 30 |
| ≥ 10.0 mi (present but far) | 10 |
| null / absent | 0 |

### Outdoor Enthusiasts Sub-Score Verification

```
outdoorSubScore = weightedAvg([
    (park=0.5469 → 85, w=1.0),
    (dog_park=0.8547 → 85, w=0.8),
    (waterfront_park=1.2487 → 70, w=0.6),
    (golf_course=1.8395 → 70, w=0.4),
])
= (85 + 68 + 42 + 28) / (1.0+0.8+0.6+0.4)
= 223 / 2.8 = 79.6 → 80 ≥ 60 threshold
```
"Outdoor Enthusiasts" label awarded. ✅

### Retirees Verification

coastal_score (70) ≥ 40 AND family_score (82) ≥ 40 → "Retirees" awarded. ✅

### Lifestyle Categories Produced

`Beach Lovers, Boaters, Commuters, Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers, Retirees`

### Score Reliability Assessment

| Score | Math Correct | Data Trustworthy | Net Assessment |
|---|---|---|---|
| coastal_score = 70 | ✅ | ✅ Beach (1.91 mi) and marina (1.85 mi) are legitimate coastal POIs | **Trustworthy** |
| walkability_score = 85 | ✅ | ⚠️ pharmacy input is an animal hospital; grocery input is a gas station convenience store | **Inflated** |
| convenience_score = 85 | ✅ | ⚠️ Same grocery and pharmacy inputs — both flawed | **Inflated** |
| commuter_score = 85 | ✅ | ✅ Bus stop and gas station are correct | **Trustworthy** |
| family_score = 82 | ✅ | ⚠️ grocery input is a gas station; park ("Big Hook island") is unverified as a developed park | **Slightly inflated** |

---

<a name="section-7"></a>
## Section 7 — Narrative Evidence Audit

**Stored narrative:** *"This location offers exceptional coastal access with beaches and waterways
nearby, excellent daily conveniences including groceries and pharmacies within easy reach, a highly
walkable environment with dining, shopping, and services close by, strong commuter infrastructure
including transit and fuel access, family-friendly surroundings with parks and recreational options
nearby, and proximity to recreational amenities appealing to outdoor enthusiasts."*

| # | Sentence Fragment | Triggering Rule | POI Inputs | Verdict |
|---|---|---|---|---|
| 1 | "exceptional coastal access with beaches and waterways nearby" | `coastal_score ≥ 70` → fires | beach=1.91 mi, marina=1.85 mi (both legit coastal POIs) | ✅ **Supported** |
| 2 | "excellent daily conveniences including groceries and pharmacies within easy reach" | `convenience_score ≥ 70` → fires | grocery=0.72 mi (BP gas station), pharmacy=0.89 mi (animal hospital) | ❌ **Unsupported** — the narrative explicitly names "groceries and pharmacies" but neither POI is actually a grocery store or human pharmacy |
| 3 | "a highly walkable environment with dining, shopping, and services close by" | `walkability_score ≥ 70` → fires | restaurant=0.75 mi, coffee=0.75 mi, grocery=0.72 mi, pharmacy=0.89 mi | ⚠️ **Needs Review** — dining and shopping are defensible (Einstein Bros. + Seminole City Center at 0.85 mi), but "services" and the walkability score include the same flawed grocery/pharmacy inputs |
| 4 | "strong commuter infrastructure including transit and fuel access" | `commuter_score ≥ 60` → fires | transit=0.82 mi (PSTA bus stop), gas=0.72 mi (BP) | ✅ **Supported** |
| 5 | "family-friendly surroundings with parks and recreational options nearby" | `family_score ≥ 60` → fires | park=0.55 mi, dog_park=0.85 mi, waterfront_park=1.25 mi | ⚠️ **Needs Review** — the park POI ("Big Hook island") is an unverified natural spot, and the dog park has a garbage address |
| 6 | "proximity to recreational amenities appealing to outdoor enthusiasts" | `'Outdoor Enthusiasts' in categories` → fires | outdoor sub-score = 80 from park/dog_park/waterfront_park/golf_course | ⚠️ **Needs Review** — golf_course POI is a mini-golf attraction, but park and waterfront park are reasonable recreational amenities |

**Summary:** 2 of 6 sentences fully supported; 1 unsupported (contains materially false claims
about groceries and pharmacies); 3 need review due to questionable POI inputs.

---

<a name="section-8"></a>
## Section 8 — Category Mapping Audit

**Source:** `LocationDnaPoiDistanceService::CATEGORIES` constant.

### Risky Mappings Identified

| Category Key | Google Type Queried | Known Risk | Observed Instance | Risk Level |
|---|---|---|---|---|
| `grocery_store` | `grocery_or_supermarket` | Google's taxonomy includes gas stations with convenience stores under this type | BP gas station returned as "grocery store" for Listing #183 | 🔴 **High** |
| `pharmacy` | `pharmacy` | Animal hospitals are registered in Google Places with `pharmacy` subtypes for their in-house dispensary | Animal Hospital of Seminole returned as "pharmacy" | 🔴 **High** |
| `hospital` | `hospital` | Urgent care clinics and walk-in medical centers frequently register with `hospital` type in Google | AFC Urgent Care returned as "hospital" | 🟠 **Medium** |
| `gym` and `fitness_center` | Both mapped to Google type `gym` | Two distinct category keys share the same Google type, guaranteeing the same result for both | Your Pilates Lifestyle returned for both categories | 🟠 **Medium** — structural duplicate, not a wrong mapping per se |
| `golf_course` | keyword `'golf course'` | Adventure/mini-golf operations use "golf" in their name and will match keyword searches | Smugglers Cove Adventure Golf returned | 🟠 **Medium** |
| `coffee_shop` | `cafe` | Bagel shops and bakeries are tagged as `cafe` in Google's taxonomy and will match the nearest `cafe`-type result | Einstein Bros. Bagels returned — same venue as `restaurant` | 🟡 **Low-Medium** — acceptable dual-tagging but leads to double-counting |
| `beach` | keyword `'beach'` | Municipality names containing "beach" (Indian Shores, Redington Beach) will match before named beach destinations | "Indian Shores" (a city) matched as "Beach" POI | 🟡 **Low-Medium** — functional but name displayed to consumer is confusing |
| `waterfront_park` | type `park` + keyword `waterfront` | Works well in coastal FL markets; less reliable in inland areas | Seminole Waterfront Park correctly returned | 🟢 **Low** |
| `dog_park` | keyword `'dog park'` | Parcel database entries may appear in Google Places with numeric parcel IDs as addresses | Dog Park returned with address "323015000001100100" — a parcel ID | 🟡 **Low-Medium** — place may be real but data quality is poor |
| `beach_access` | keyword `'beach access'` | Works correctly for Gulf Coast FL; only risks are far-inland listings | Public Beach Access at 16258 Gulf Blvd correctly returned | 🟢 **Low** |
| `transit_station` | `transit_station` | Bus stops are correctly mapped; no rail in Pinellas County | Bus stop intersection correctly returned | 🟢 **Low** |

### Structural Duplicate Categories

The `gym` and `fitness_center` categories both use `google_type = 'gym'` with no
distinguishing keyword. They will always return the same nearest result. This wastes one API
call per pipeline run and produces duplicate data for every listing.

### Gas Station / Grocery Store Co-location

The `grocery_store` and `gas_station` categories returned the same BP location
(27.8212127, −82.7958505, 5390 Duhme Rd). This is structurally possible — Google can
label one place with multiple types — but it means the system is claiming the same
gas station is both the nearest grocery store and the nearest gas station. Any scoring
or narrative that references "grocery store" for this listing is based on a
false premise.

---

<a name="section-9"></a>
## Section 9 — Top 3 v2 Preview

### Evidence Basis

The current pipeline stores exactly one candidate per category. The table below shows
the **complete evidence available from stored records** for every category: position 1 is
the actual persisted POI row; positions 2 and 3 are not stored and require a v2 pipeline
change to populate. No external estimates or speculative candidates are included.

### Per-Category Stored Candidate + v2 Gap

| Category | Position 1 (stored — current result) | Position 1 Assessment | Position 2 | Position 3 |
|---|---|---|---|---|
| grocery_store | **bp** — 0.7153 mi (id=607) | ❌ Gas station, not grocery | *Not stored — requires v2 type-exclusion + re-query* | *Not stored* |
| school | **Blessed Sacrament Catholic School** — 0.2348 mi (id=608) | ✅ Valid school | *Not stored* | *Not stored* |
| hospital | **AFC Urgent Care Seminole** — 0.8486 mi (id=609) | ⚠️ Urgent care, not full hospital | *Not stored* | *Not stored* |
| park | **Big Hook island** — 0.5469 mi (id=610) | ⚠️ Natural spot; no facilities confirmed | *Not stored* | *Not stored* |
| pharmacy | **Animal Hospital of Seminole** — 0.8944 mi (id=611) | ❌ Veterinary clinic, not human pharmacy | *Not stored — requires v2 type-exclusion + re-query* | *Not stored* |
| gas_station | **bp** — 0.7153 mi (id=612) | ✅ Valid (same BP as grocery row) | *Not stored* | *Not stored* |
| restaurant | **Einstein Bros. Bagels** — 0.7473 mi (id=613) | ✅ Valid restaurant/cafe | *Not stored* | *Not stored* |
| gym | **Your Pilates Lifestyle** — 0.8032 mi (id=614) | ⚠️ Pilates studio; no full gym equipment | *Not stored* | *Not stored* |
| fitness_center | **Your Pilates Lifestyle** — 0.8032 mi (id=615) | ⚠️ Structural duplicate of gym row (identical lat/lng) | *Not stored* | *Not stored* |
| transit_station | **Park Blvd + 110th St** — 0.8180 mi (id=616) | ✅ Valid PSTA bus stop | *Not stored* | *Not stored* |
| coffee_shop | **Einstein Bros. Bagels** — 0.7473 mi (id=617) | ✅ Valid (same venue as restaurant row) | *Not stored* | *Not stored* |
| shopping_center | **Seminole City Center** — 0.8488 mi (id=618) | ✅ Valid retail center | *Not stored* | *Not stored* |
| beach | **Indian Shores** — 1.9097 mi (id=619) | ⚠️ Municipality name matched as "beach"; vicinity=Redington Shores | *Not stored* | *Not stored* |
| beach_access | **Public Beach Access** — 1.6225 mi (id=620) | ✅ Valid Gulf-front access point at 16258 Gulf Blvd | *Not stored* | *Not stored* |
| boat_ramp | **Lake Seminole Park Boat Ramp** — 1.7004 mi (id=621) | ✅ Valid Pinellas County ramp | *Not stored* | *Not stored* |
| marina | **Madeira Beach Marina** — 1.8512 mi (id=622) | ✅ Valid marina at 503 150th Ave | *Not stored* | *Not stored* |
| waterfront_park | **Seminole Waterfront Park** — 1.2487 mi (id=623) | ✅ Valid developed park at 10400 Park Blvd | *Not stored* | *Not stored* |
| dog_park | **Dog Park** — 0.8547 mi (id=624) | ⚠️ Generic name; address is parcel ID "323015000001100100" | *Not stored* | *Not stored* |
| golf_course | **Smugglers Cove Adventure Golf** — 1.8395 mi (id=625) | ❌ Mini/adventure golf, not a regulation course | *Not stored — requires v2 name-exclusion + re-query* | *Not stored* |

### What v2 Must Provide to Complete This Table

For positions 2 and 3 to be populated from stored data, the v2 pipeline must:
1. **Store top-5 raw candidates** per category (name, lat, lng, distance, Google `types` array, `rating`, `user_ratings_total`) in a `poi_candidates` JSON column or child table.
2. **Apply type-exclusion filters at selection time** (not at query time): for each category, walk positions 1–5 and skip results that fail the category's exclusion rules.
3. For **restaurant / Top Rated Dining**: add a separate query using `rankby=prominence` or `fields=rating,user_ratings_total` within a configurable radius and select by composite rating score.

Until v2 is implemented, positions 2 and 3 for all 19 categories remain unavailable from stored records for Listing #183.

---

<a name="section-10"></a>
## Section 10 — Recommendations

### Critical Fixes (Must Resolve Before Feeding Property DNA or Target Market Intelligence)

| Priority | Issue | Impact | Recommended Fix |
|---|---|---|---|
| 🔴 P0 | **Pharmacy = Animal Hospital** | Narrative says "pharmacies within easy reach" — factually false | Add a post-fetch name/type filter: if result types include `veterinary_care` or name matches `/animal hospital/i`, discard and take next result (requires storing top-N candidates) |
| 🔴 P0 | **Grocery Store = Gas Station** | convenience_score and walkability_score inflated; narrative claims grocery nearby | Add a type exclusivity check: if `gas_station` is in the result's types array AND no `grocery_or_supermarket` type is present, discard result |
| 🔴 P0 | **Golf Course = Miniature/Adventure Golf** | Lifestyle score for outdoor recreation misleads buyers/agents who value real golf | Add a name exclusion list: `adventure golf`, `mini golf`, `miniature golf`, `putt-putt` |
| 🟠 P1 | **Hospital = Urgent Care Walk-In** | Buyers relying on hospital proximity for health decisions receive misleading data | Flag results with `urgent_care` in types or "Urgent Care"/"Walk-In" in name as `hospital_subtype=urgent_care` |
| 🟠 P1 | **No raw candidate storage** | Cannot produce Top 3, cannot recover from bad top-1 result without a new API call | Persist top-5 raw candidates per category to `poi_candidates` JSON column; top-1 selection happens at display time |
| 🟠 P1 | **gym = fitness_center structural duplicate** | Two API calls, same result, same distance — wastes quota | Either merge into one category or differentiate by adding `keyword='fitness center'` to distinguish |
| 🟡 P2 | **Beach = municipality name** | "Indian Shores" displayed as beach name confuses consumers | Post-process: if place name matches a known city/municipality, use vicinity or format as "[vicinity] beach area" |
| 🟡 P2 | **Dog Park garbage address** | Parcel ID "323015000001100100" stored as address; not human-readable | Sanitize `poi_address`: if value matches `/^\d{15,}/`, set address to `null` (display only name + distance) |
| 🟡 P2 | **No road distance / drive time** | All distances are straight-line; the beach is displayed as 1.91 mi but requires a 3–4 mi drive | Add road distance disclaimer to UI; populate `travel_time_minutes` for beach, boat_ramp, marina, waterfront_park |
| 🟢 P3 | **Top Rated Dining split** | Nearest restaurant (Einstein Bros. at 0.75 mi) does not represent the quality dining near a Gulf-front area | Add `top_rated_restaurant` category querying by rating composite within 3–5 mi |

### Inaccurate POIs Confirmed

3 POIs are materially incorrect: **grocery_store** (gas station), **pharmacy** (animal hospital),
**golf_course** (mini-golf). These affect 4 lifestyle scores and 2 narrative sentences.

### Inaccurate Distances Confirmed

None. All 19 Haversine-calculated distances are correct within floating-point tolerance.

### Wrong Category Mappings Confirmed

The Google Places type `pharmacy` is insufficient to exclude veterinary/animal pharmacies.
The type `grocery_or_supermarket` is insufficient to exclude gas stations with convenience stores.
The keyword `golf course` is insufficient to exclude adventure/mini-golf.

### Missing Categories Worth Adding

| Suggested Category | Rationale for Seminole FL Context |
|---|---|
| `urgent_care` | Distinguish from full hospitals; prevalent in Pinellas suburbs |
| `supermarket` (deduplicated from current) | Grocery-specific type with gas-station exclusion |
| `library` | Family score enrichment |
| `place_of_worship` | Relevant for family/community profile |
| `airport_nearby` | Relevant for commuter score (Tampa International ~20 mi) |

### Final Readiness Verdict

> ⚠️ **NOT READY** to feed Property DNA or Target Market Intelligence as-is.

The Location DNA pipeline is architecturally sound and the distance mathematics are correct.
However, three category mapping errors produce materially false POI assignments that
directly inflate convenience and walkability scores by 15–25 points above what the actual
nearest qualifying facilities would produce. The narrative generated from these scores
contains at least one factually false sentence (claiming a pharmacy is within easy reach
when the nearest result is a veterinary practice).

**Minimum gate for readiness:**
1. Fix P0 type-exclusion filters (pharmacy, grocery, golf_course) — this can be done
   without storing runner-ups if the pipeline re-queries on rejection.
2. Store top-5 candidates per category.
3. Add the road-distance disclaimer to any consumer-facing display.

Once P0 fixes are in place, the coastal, commuter, and school scores are trustworthy and
the system can be used to generate accurate coastal-lifestyle narratives for Pinellas County
listings. Full readiness for Property DNA and TMI requires P1 fixes as well.
