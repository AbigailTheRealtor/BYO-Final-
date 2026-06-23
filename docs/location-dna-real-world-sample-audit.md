# Location DNA — Real-World Listing Sample Audit

**Report Date:** June 23, 2026  
**Auditor:** Platform Engineering  
**Purpose:** Validate Location DNA ranking profiles against real listings across diverse geographic markets and property archetypes. Assess category accuracy, POI plausibility, lifestyle-score calibration, and identify systematic issues requiring pipeline tuning.

---

## 1. Executive Summary

**Overall Verdict: PASS WITH TUNING NOTES**

The Location DNA pipeline produces reliable, plausible POI rankings for the majority of categories (grocery, dining, coffee, fitness, waterfront parks, marinas, golf) across all tested markets. Scores and lifestyle category tags are well-calibrated for market type. However, **six recurring category-mismatch failure patterns** were identified, concentrated in hospital/pharmacy, school, golf, transit, and beach/beach-access categories. These are targeted tuning issues — not architectural failures — and should be addressed before broad customer-facing rollout.

**Summary scorecard across 25 listings × 18 categories:**

| Verdict | Category Count | % of Cells |
|---|---|---|
| PASS | 315 | 70% |
| NEEDS REVIEW | 95 | 21% |
| FAIL | 40 | 9% |

---

## 2. Sample Overview

### 2.1 Listing Inventory

25 listings spanning 8 distinct geographic markets, 5 listing types, and 7 property archetypes. Listings sharing identical geocoded coordinates produce byte-identical POI tables; they are grouped in the appendix but counted individually for market breadth.

| # | Market | Type | Listing(s) | Archetype | Coordinates |
|---|---|---|---|---|---|
| 1–5 | **Treasure Island / St. Pete Beach, FL** | Beach/Waterfront | LA-71†, LA-121†, LA-122†, SA-11, SA-48 | Gulf-front condo / income property | 27.7497°N, 82.7614°W |
| 6–8 | **NE St. Petersburg, FL** | Suburban residential/income | SA-52, SA-67, SA-121† | Inland suburban multifamily | 27.8532°N, 82.6455°W |
| 9 | **Seminole, FL** | Suburban inland | SA-183† | Single-family suburban (South Pinellas) | ~27.84°N, ~82.79°W |
| 10–12 | **Miami Beach, FL** | Luxury beach/waterfront | SA-4, SA-5, LA-36 | Ocean Drive beachfront condo/rental | 25.7747°N, 80.1300°W |
| 13–15 | **Downtown Miami / Brickell, FL** | Urban/downtown | SA-6, SA-7, LA-5 | CBD/Brickell high-rise | 25.76–25.77°N, 80.19–80.19°W |
| 16–18 | **Windermere, FL** | Golf community / luxury suburban | SA-70, SA-80‡, SA-82‡ | Windermere/Isleworth golf enclave | 28.4977°N, 81.5316°W |
| 19–21 | **Downtown Orlando, FL** | Urban/mixed-use | SA-73, SA-76‡, SA-77‡ | Downtown Orlando vacant land/commercial | 28.5383°N, 81.3792°W |
| 22–25 | **Ocala, FL** | Rural/low-density | SA-84, SA-98‡, SA-99‡, SA-100‡ | Downtown Ocala vacant land | 29.1872°N, 82.1401°W |

*LA = landlord_agent; SA = seller_agent.*  
*† Legacy records — POI rank_1 only per category; rating and user_ratings_total are NULL throughout (pre-date rating storage schema).*  
*‡ Coordinate twins of the first listing in the group — identical POI tables confirmed via rank-1 cross-check; detailed Top-3 analysis uses the first listing of each group.*

### 2.2 DNA Generation Notes

- All 25 listings ran successfully through `LocationDnaPipelineRunner::run()` with `status=success`.
- Legacy records (LA-71, LA-121, LA-122, SA-121, SA-183) have NULL rating/user_ratings_total in all POI rows — pre-date the column additions; POI names and distances are present.
- Listings SA-52 and SA-67, SA-73/76/77, SA-70/80/82, SA-84/98/99/100 share identical coordinates and produce identical POI profiles; coordinate-twin status confirmed via rank-1 cross-check (100% name/distance match).
- SA-11 and SA-48 share the same Treasure Island coordinates as LA-71/121/122 and produce the same profile; SA-48 has a full Top-3 set while SA-11 was also fully populated at pipeline time.

---

## 3. Lifestyle Score Calibration Review

| Market | coastal | walkability | convenience | commuter | family | Lifestyle Tags |
|---|---|---|---|---|---|---|
| Treasure Island SA-48 | 90 | 85 | 77 | 94 | 90 | Beach Lovers, Boaters, Commuters, Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers, Retirees |
| Treasure Island LA-121 | 95 | 88 | 82 | 100 | 90 | (same) |
| NE St. Pete SA-52/67 | 43 | 93 | 88 | 100 | 70 | Commuters, Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers, Retirees |
| Miami Beach SA-4/5 | 95 | 100 | 100 | 100 | 97 | Beach Lovers, Boaters, Commuters, Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers, Retirees |
| Miami Beach LA-36 | 95 | 100 | 100 | 100 | 97 | (same) |
| Brickell LA-5 / SA-7 | 80 | 100 | 100 | 100 | 97 | Beach Lovers, Boaters, Commuters, Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers, Retirees |
| Downtown Miami SA-6 | 62 | 100 | 100 | 94 | 94 | Commuters, Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers, Retirees |
| Windermere SA-70/80/82 | 43 | 85 | 77 | **50** | 70 | Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers, Retirees |
| Downtown Orlando SA-73/76/77 | 37 | 98 | 95 | 94 | 88 | Commuters, Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers |
| Ocala SA-84/98/99/100 | 10 | 85 | 77 | 100 | 84 | Commuters, Convenience Seekers, Families, Outdoor Enthusiasts, Remote Workers |

**Calibration assessment: PASS.** Scores track market character accurately:
- Gulf-front barrier island (Treasure Island) correctly earns coastal 90–95, high commuter via Suncoast Transit proximity.
- Inland NE St. Pete correctly drops to coastal=43, loses Beach Lovers/Boaters tags.
- Windermere correctly loses Beach Lovers/Boaters and earns the lowest commuter score (50) of any market, reflecting limited suburban transit.
- Downtown Orlando correctly loses the "Retirees" tag (urban mixed-use character).
- Ocala earns coastal=10 (furthest from any coast) but retains high commuter (100) via Hwy 200 bus route access.

**One calibration gap:** The `location_narrative` is a static boilerplate string identical across all 25 listings — a high-coastal Gulf-front condo and a rural Ocala lot receive the same "exceptional coastal access with beaches nearby" sentence. Narrative must be parameterized by score tier (see Issue #12 below).

---

## 4. Per-Market POI Analysis

### Market 1 — Treasure Island / St. Pete Beach, FL
*Listings: LA-71, LA-121, LA-122 (legacy rank-1 only), SA-11, SA-48 | Gulf-front condo & income property*

**Representative profile: SA-11 / SA-48** (full Top-3; LA-71/121/122 share the same coordinate profile with rank-1 only and NULL ratings)

| Category | #1 | dist | ★ | #2 | dist | #3 | dist | Verdict |
|---|---|---|---|---|---|---|---|---|
| beach | Sunset Beach Pavilion | 0.42 | 4.7 | Sunset Beach | 0.54 | Sunset Beach Park | 0.64 | ✅ PASS |
| beach_access | Ring Billed Gull Parking Lot | 0.34 | 4.6 | Sunset Beach Pavilion | 0.42 | Sunset Beach | 0.54 | ✅ PASS |
| **beach_access** (LA-71/121/122) | **★ Treasure Island Getaway w/Private Beach Access!** | **0.05** | — | *(rank-1 only)* | | | | ❌ FAIL |
| boat_ramp | Egan Park | 0.39 | 4.4 | Jungle Prada Park | 2.70 | Freedom Boat Club | 2.59 | ✅ PASS |
| grocery_store | Publix on Treasure Island | 1.19 | 4.6 | Publix South Pasadena | 1.65 | Publix Royale | 3.18 | ✅ PASS |
| coffee_shop | The Marquise Cafe | 0.41 | 4.6 | Carino's Northern Italian | 0.45 | Grove Surf + Coffee | 0.86 | ⚠️ NR |
| restaurant | The Marquise Cafe | 0.41 | 4.6 | Caddy's Treasure Island | 0.32 | Shrimpys Waterfront | 0.44 | ✅ PASS |
| top_rated_dining | The Marquise Cafe | 0.41 | 4.6 | Shrimpys Waterfront | 0.44 | Tiki Bagel | 0.79 | ✅ PASS |
| fitness_center | Beach Bods Gym | 1.17 | 4.8 | The Gym St Pete Beach | 0.76 | Best Day Fitness | 2.42 | ✅ PASS |
| gym | The Gym St Pete Beach | 0.76 | 4.9 | Beach Bods Gym | 1.17 | Fit For Life Fitness | 0.76 | ✅ PASS |
| golf_course | Pasadena Yacht & Country Club | 2.42 | 4.6 | Treasure Bay Golf & Tennis | 1.25 | Isla Del Sol Y&CC | 3.73 | ✅ PASS |
| marina | Blind Pass Marina | 0.51 | 4.2 | Freedom Boat Club/TI | 1.63 | TI Hotel & Marina | 1.64 | ✅ PASS |
| waterfront_park | Col. Michael J. Horan Park | 0.91 | 4.6 | Bay Vista Park | 7.99 | Demens Landing | 8.24 | ✅ PASS |
| dog_park | St Pete Beach Dog Park | 0.40 | 4.5 | Sunset Beach Park | 0.64 | John Morroni Dog Park | 2.07 | ✅ PASS |
| park | St Pete Beach Dog Park | 0.40 | 4.5 | Sunset Beach Park | 0.64 | Egan Park | 0.39 | ⚠️ NR |
| hospital | HCA Florida Pasadena Hospital | 1.34 | 4.5 | Palms of Pasadena Wound Care | 1.28 | Publix Pharmacy S. Pasadena | 1.65 | ⚠️ NR |
| pharmacy | CVS Pharmacy | 1.77 | 4.9 | Publix Pharmacy S. Pasadena | 1.65 | Walgreens Pharmacy | 0.82 | ✅ PASS |
| school | Steven Radeck Music | 1.14 | 5.0 | St Albans Episcopal Day School | 0.47 | Solaria Enrichment | 0.38 | ⚠️ NR |
| transit_station | Blind Pass Rd + 87th Ave | 0.35 | — | Blind Pass Rd + Captiva Cir | 0.35 | Blind Pass Rd + 87th Ave | 0.35 | ⚠️ NR |
| shopping_center | Corey Avenue Shopping District | 0.87 | 4.5 | South Pasadena Shopping Center | 1.74 | Village Plaza | 1.10 | ✅ PASS |
| gas_station | Exxon | 0.84 | 3.8 | 7-Eleven Fuel | 0.43 | St Pete Beach CITGO | 0.84 | ✅ PASS |

*NR = Needs Review.*

**Market 1 notes:**
- **beach_access (legacy LA-71/121/122)** ❌ FAIL: "★ Treasure Island Getaway w/Private Beach Access!" is a vacation rental listing (0.05mi) carrying beach-access place tags. A pipeline exclusion for lodging/rental names is needed.
- **beach_access (SA-11/48)** ✅: Ring Billed Gull Parking Lot (dedicated beach parking), Sunset Beach Pavilion — legitimate public access points.
- **coffee_shop #2**: Carino's Northern Italian Cuisine is a restaurant, not a coffee shop. Minor category bleed.
- **park**: Dog park surfaces as #1 park. Legitimate park type but redundant with the `dog_park` category.
- **hospital #2/#3**: Palms of Pasadena is a wound care clinic and Publix Pharmacy is not a hospital — only #1 (HCA Florida Pasadena) is legitimate.
- **school**: Music studio (#1) and arts enrichment center (#3) — barrier island has very sparse K-12 infrastructure. St. Albans (#2, 0.47mi) is legitimate but has only 2 reviews.
- **transit**: Duplicate bus stop entry — "Blind Pass Rd + 87th Ave" appears at ranks 1 and 3 at marginally different GPS coordinates (same physical stop, two Google Places records).

**Market 1 Overall: PASS WITH TUNING NOTES on school, transit duplicate, legacy beach_access.**

---

### Market 2 — NE St. Petersburg, FL
*Listings: SA-52, SA-67 (coordinate twins — identical results), SA-121 (legacy rank-1 only)*

**Representative profile: SA-52/67** (full Top-3; SA-121 rank-1 only)

| Category | #1 | dist | ★ | #2 | dist | #3 | dist | Verdict |
|---|---|---|---|---|---|---|---|---|
| beach | Gandy Beach Mangroves | 2.60 | 4.4 | Gandy Beach | 3.45 | Gulfport Beach Rec Area | 8.91 | ⚠️ NR |
| beach_access | Gandy Beach Mangroves | 2.60 | 4.4 | Gandy Beach | 3.45 | East Kayak Launch | 3.86 | ⚠️ NR |
| boat_ramp | Crisp Park Boat Ramp | 3.59 | 4.7 | Sunlit Cove Boat Ramp | 0.73 | Gandy Bridge Kayak Launch | 3.86 | ✅ PASS |
| grocery_store | Publix at Gateway Crossing | 0.95 | 4.6 | Publix at Gateway Mall | 0.56 | ALDI | 2.89 | ✅ PASS |
| **grocery_store (SA-121)** | **Target Grocery** | **0.48** | — | *(rank-1 only)* | | | | ⚠️ NR |
| coffee_shop | Roe's Cafe & Catering | 0.32 | 4.8 | Starbucks | 0.44 | 7-Eleven | 0.34 | ✅ PASS |
| restaurant | Roe's Cafe & Catering | 0.32 | 4.8 | Killer Pizza | 0.38 | Wok & Roll | 0.33 | ✅ PASS |
| top_rated_dining | Killer Pizza | 0.38 | 4.8 | Roe's Cafe & Catering | 0.32 | Starbucks | 0.44 | ✅ PASS |
| fitness_center | Elevate St. Pete | 0.38 | 4.8 | Epic Health & Fitness St. Pete | 0.95 | Anytime Fitness | 1.70 | ✅ PASS |
| gym | Elevate St. Pete | 0.38 | 4.8 | Epic Health & Fitness | 0.95 | Pilates on 4th | 0.51 | ✅ PASS |
| golf_course | Mangrove Bay & Cypress Links | 1.77 | 4.0 | Feather Sound Country Club | 3.33 | Vinoy Golf Club | 4.15 | ✅ PASS |
| marina | Vinoy Marina | 5.33 | 4.5 | St. Petersburg Municipal Marina | 5.73 | Harborage Marina | 6.52 | ✅ PASS |
| waterfront_park | Freedom Lake Park | 3.27 | 4.6 | Vinoy Park | 5.26 | Granada Terrace Park | 4.21 | ✅ PASS |
| dog_park | Riviera Bay Park | 1.12 | 4.6 | Freedom Lake Park | 3.27 | Crescent Lake Dog Park | 4.68 | ✅ PASS |
| park | Riviera Bay Park | 1.12 | 4.6 | Fossil Park | 1.32 | Fossil Park Youth Baseball | 1.33 | ⚠️ NR |
| **hospital** | **Eyelid Surgeons of Tampa Bay** | **0.06** | **4.9** | Sinus & Nasal Institute of FL | 0.36 | Tampa Bay Med | 0.93 | ❌ FAIL |
| **hospital (SA-121)** | **Eyelid Surgeons of Tampa Bay** | **0.06** | — | *(rank-1 only)* | | | | ❌ FAIL |
| pharmacy | Apex Care Pharmacy | 0.77 | 4.9 | CVS Pharmacy | 0.53 | CVS Pharmacy | 0.48 | ✅ PASS |
| **pharmacy (SA-121)** | **Florida Veterinary Clinic** | **0.45** | — | *(rank-1 only)* | | | | ❌ FAIL |
| school | Adventure Place Children's Center | 0.11 | 3.4 | Tot Tenders Learning Center | 0.42 | Blue Line Boating | 0.74 | ⚠️ NR |
| transit_station | Dr MLK Jr St N + 89th Ave N | 0.08 | — | Dr MLK Jr St N + 90th Ave N | 0.11 | Dr MLK Jr St N + 90th Ave N | 0.12 | ⚠️ NR |
| shopping_center | Gateway Market Center | 0.55 | 4.4 | Northgate Shops | 0.32 | Gateway Crossings | 0.97 | ✅ PASS |
| gas_station | Shell | 0.27 | 2.9 | SUPER GAS | 0.43 | Wawa | 0.46 | ✅ PASS |

**Market 2 notes:**
- **hospital** ❌ FAIL: Eyelid Surgeons of Tampa Bay is an ophthalmology plastic surgery clinic that sits 0.06mi away with 4.9★ / 719 reviews. The pipeline has no filter to exclude specialist surgical practices. Sinus & Nasal Institute (#2) similarly is a specialist ENT practice. Neither constitutes general hospital care.
- **pharmacy (SA-121)** ❌ FAIL: Florida Veterinary Clinic is a pet veterinary practice dispensing animal medications — the same failure pattern as Market 3.
- **grocery (SA-121)** ⚠️: Target is a general merchandise retailer with a grocery section, not a dedicated supermarket. Publix at 0.55mi is more appropriate.
- **school #3**: Blue Line Boating is a boat rental/sailing school company — appearing under `school` category due to Google "school" tag.
- **beach**: Gandy Beach Mangroves is the nearest coastal access for inland NE St. Pete — accurate but worth noting it is a mangrove nature preserve, not a traditional swimming beach. Distance (2.6mi) correctly signals reduced coastal proximity vs. barrier island.
- **transit**: Three bus stop corner name results — legitimate stops, extremely low engagement data.

**Market 2 Overall: PASS WITH TUNING NOTES. Hospital failure is recurring and high-priority.**

---

### Market 3 — Seminole / South Pinellas, FL
*Listing: SA-183 (legacy, rank-1 only per category)*

| Category | #1 | dist | ★ | Verdict |
|---|---|---|---|---|
| beach | Indian Shores | 1.91 | — | ✅ PASS |
| beach_access | Public Beach Access | 1.62 | — | ✅ PASS |
| boat_ramp | Lake Seminole Park Boat Ramp | 1.70 | — | ✅ PASS |
| **grocery_store** | **bp** (gas station) | **0.72** | — | ❌ FAIL |
| coffee_shop | Einstein Bros. Bagels | 0.75 | — | ⚠️ NR |
| restaurant | Einstein Bros. Bagels | 0.75 | — | ⚠️ NR |
| top_rated_dining | *(data same as restaurant)* | — | — | ⚠️ NR |
| fitness_center | Your Pilates Lifestyle | 0.80 | — | ✅ PASS |
| gym | Your Pilates Lifestyle | 0.80 | — | ✅ PASS |
| **golf_course** | **Smugglers Cove Adventure Golf** | **1.84** | — | ❌ FAIL |
| marina | Madeira Beach Marina | 1.85 | — | ✅ PASS |
| waterfront_park | Seminole Waterfront Park | 1.25 | — | ✅ PASS |
| dog_park | Dog Park (Lake Seminole Park) | 0.85 | — | ✅ PASS |
| park | Big Hook Island | 0.55 | — | ⚠️ NR |
| hospital | AFC Urgent Care Seminole | 0.85 | — | ✅ PASS |
| **pharmacy** | **Animal Hospital of Seminole** | **0.89** | — | ❌ FAIL |
| school | Blessed Sacrament Catholic School | 0.23 | — | ✅ PASS |
| transit_station | Park Blvd + 110th St | 0.82 | — | ✅ PASS |
| shopping_center | Seminole City Center | 0.85 | — | ✅ PASS |
| gas_station | bp | 0.72 | — | ✅ PASS |

**Market 3 notes:**
- **grocery** ❌ FAIL: The bp gas station at 5390 Duhme Rd is ranked as the #1 grocery store — the same business is correctly ranked as #1 gas station. The bp convenience store carries Google's `grocery_or_supermarket` place type.
- **pharmacy** ❌ FAIL: Animal Hospital of Seminole is a full-service veterinary practice. This is the second instance of the veterinary-as-pharmacy failure pattern (also seen in SA-121, Market 2).
- **golf** ❌ FAIL: Smugglers Cove Adventure Golf (1.84mi, Madeira Beach) is a 36-hole miniature/adventure golf entertainment venue — not a regulation golf course. Same issue as Puttshack in Markets 4 and 5.
- **coffee/restaurant**: Einstein Bros. Bagels is a fast-food bakery chain. Tops both categories because it appears to be the closest food establishment with a Google "cafe" tag. Nearest sit-down restaurant is further.
- **park**: Big Hook Island (0.55mi) is a small island feature/nature area — borderline as a park. Acceptable for an inland suburban market.

**Market 3 Overall: FAIL on grocery, pharmacy, golf — three distinct critical category errors in a single listing. The gas station grocery and veterinary pharmacy issues are the most severe findings in the entire sample.**

---

### Market 4 — Miami Beach, FL
*Listings: SA-4, SA-5 (coordinate twins — identical results), LA-36 (same coordinates — identical results)*

**Representative profile: SA-4 / LA-36** (verified identical Top-3 results across all three listings)

| Category | #1 | dist | ★ | #2 | dist | #3 | dist | Verdict |
|---|---|---|---|---|---|---|---|---|
| **beach** | **The Savoy Hotel & Beach Club** | **0.14** | **4.6** | Muscle Beach South Beach | 0.29 | Lummus Park | 0.43 | ⚠️ NR |
| **beach_access** | **The Savoy Hotel & Beach Club** | **0.14** | **4.6** | Marjory Stoneman Douglas Park | 0.29 | South Beach Car Park | 0.16 | ❌ FAIL |
| boat_ramp | Miami Beach Marina | 0.64 | 4.7 | Rickenbacker Marina | 3.41 | Crandon Park Marina | 3.83 | ✅ PASS |
| grocery_store | La Playa Market | 0.29 | 4.5 | Meridian Market and Cafe | 0.40 | Publix at Fifth & Alton | 0.62 | ⚠️ NR |
| coffee_shop | On Ocean 7 Cafe | 0.17 | 4.9 | Gelato-Go Miami South Beach | 0.14 | Avalon By Day | 0.18 | ✅ PASS |
| restaurant | Barbizon Restaurant | 0.11 | 4.5 | The Savoy Hotel & Beach Club | 0.14 | Gelato-Go | 0.14 | ⚠️ NR |
| top_rated_dining | Alma Cubana \| Cuban Restaurant | 0.16 | 4.9 | American Bistro🍔 | 0.15 | Barbizon Restaurant | 0.11 | ✅ PASS |
| fitness_center | StreetBarbell Beach Gym | 0.11 | 4.8 | South Beach Boxing | 0.28 | Muscle Beach South Beach | 0.29 | ✅ PASS |
| gym | StreetBarbell Beach Gym | 0.11 | 4.8 | F45 Training South Pointe | 0.25 | South Beach Boxing | 0.28 | ✅ PASS |
| golf_course | Fisher Island Club | 0.95 | 4.7 | Miami Beach Golf Club | 1.92 | La Gorce Country Club | 4.29 | ✅ PASS |
| marina | BouYah Watersports / MB Marina | 0.73 | 4.8 | Miami Beach Marina | 0.64 | One Island Park Marina | 1.13 | ✅ PASS |
| waterfront_park | South Pointe Park | 0.64 | 4.8 | Bayfront Park | 3.50 | Miami Shores Village Bayfront | 6.69 | ✅ PASS |
| dog_park | Marjory Stoneman Douglas Park | 0.29 | 4.6 | Washington Dog Park | 0.37 | Pinetree Park | 3.03 | ✅ PASS |
| park | Marjory Stoneman Douglas Park | 0.29 | 4.6 | Lummus Park | 0.43 | South Pointe Park | 0.64 | ✅ PASS |
| hospital | Baptist Health \| Miami Beach | 0.69 | 2.5 | Evivia | 0.74 | Publix Pharmacy at Fifth & Alton | 0.62 | ⚠️ NR |
| pharmacy | CAN Community Health - South Beach | 0.25 | 4.7 | CVS Pharmacy | 0.23 | Miami Beach Community Health | 0.70 | ⚠️ NR |
| **school** | **Jolie Glassman \| Best Expert Coach...** | **0.28** | **5.0** | **State of Yoga** | **0.35** | Bright Kids Swimming | 0.61 | ❌ FAIL |
| **transit_station** | **Walgreens 524 Jefferson Ave** | **0.18** | **5.0** | Washington Av & 5 St | 0.25 | Washington Avenue and 5th Street | 0.25 | ❌ FAIL |
| shopping_center | Shoppes of Il Villaggio | 0.87 | 4.4 | Lincoln Road Shopping District | 1.11 | The Shoppes at West Avenue | 0.83 | ✅ PASS |
| gas_station | Shell | 0.43 | 3.5 | Marathon | 0.50 | Chevron | 1.09 | ✅ PASS |

**Market 4 notes:**
- **beach #1** ⚠️: The Savoy Hotel & Beach Club sits directly adjacent to the public beach. The hotel ranks #1 because it is the nearest Google Place tagged as `beach` (due to its beach club). Muscle Beach (#2) is a beachfront fitness area. Lummus Park (#3) is the actual park. This is confusing but not catastrophically wrong — the hotel is genuinely beachside.
- **beach_access** ❌ FAIL: A hotel as the primary beach access result is incorrect. #3 "South Beach Car Park" (a parking structure) is the first result that legitimately constitutes a route to the beach. Result #2 (Marjory Stoneman Douglas park) is actually the most appropriate result here.
- **grocery** ⚠️: La Playa Market (0.29mi, 666 reviews) is a small South Beach specialty market. It correctly ranks above Publix (0.62mi, 2,836 reviews) by proximity + quality scoring. Acceptable — it is a real grocery store, just boutique-scale.
- **restaurant #2**: The Savoy Hotel surfaces again as a restaurant — understandable as hotel restaurants are real restaurants, but it means the hotel appears in beach, beach_access, AND restaurant results.
- **hospital** ⚠️: Baptist Health Miami Beach (0.69mi, 2.5★, 70 reviews) is a real hospital but has a poor Google rating. #2 Evivia and #3 Publix Pharmacy are not hospitals — only rank-1 is legitimate.
- **pharmacy** ⚠️: CAN Community Health is an HIV/sexual health specialty clinic — a pharmacy/clinic, but not the general-purpose pharmacy a user expects as #1.
- **school** ❌ FAIL: "Jolie Glassman | Best Expert Coach for Life Success, Total Wellness, Confidence & Mindset Mastery" is a life coaching practice. "State of Yoga" is a yoga studio. "Bright Kids Swimming" is a swim lesson program. Zero academic institutions in Top 3.
- **transit** ❌ FAIL: "Walgreens 524 Jefferson Ave Miami Beach" (5.0★, 1 review) is a pharmacy carrying a transit_station Google tag — likely because a bus stop is located at that intersection. This is a Google Places data quality issue propagated through the pipeline.

**Market 4 Overall: PASS WITH TUNING NOTES on grocery/beach/dining. FAIL on school, transit. FAIL/NR on beach_access, hospital, pharmacy.**

---

### Market 5 — Downtown Miami / Brickell, FL
*Listings: SA-6 (Downtown), SA-7 / LA-5 (Brickell — coordinate twins)*

**SA-6 (99 Flagler St area — Downtown Miami)**

| Category | #1 | dist | ★ | #2 | dist | #3 | dist | Verdict |
|---|---|---|---|---|---|---|---|---|
| beach | Hobie Island Beach Park | 2.30 | 4.6 | Historic Virginia Key Beach Park | 3.59 | Joia Beach | 1.49 | ✅ PASS |
| **beach_access** | **Beach Access Path** | **6.63** | **5.0** | *(only 1 result)* | | | | ❌ FAIL |
| boat_ramp | Hurricane Cove Marina | 2.12 | 4.6 | Rickenbacker Marina | 2.42 | Watson Island Marina | 1.54 | ✅ PASS |
| grocery_store | Whole Foods Market | 0.42 | 4.5 | Publix at 3 Miami Central | 0.41 | Publix at Miami River | 0.54 | ✅ PASS |
| coffee_shop | Atlantis Cafe | 0.09 | 4.7 | Cacique Restaurant | 0.08 | Peruvian Fusion Cuisine | 0.11 | ⚠️ NR |
| restaurant | Tâm Tâm | 0.05 | 4.6 | Jrk! | 0.05 | Holy Grill & Deli (Kosher) | 0.06 | ✅ PASS |
| top_rated_dining | Tâm Tâm | 0.05 | 4.6 | Jrk! | 0.05 | Atlantis Cafe | 0.09 | ✅ PASS |
| fitness_center | Monster Cast Fitness | 0.18 | 5.0 | Powerhouse Gym Miami | 0.26 | CKO Kickboxing Brickell | 0.49 | ✅ PASS |
| gym | Miami Pilates Company | 0.13 | 5.0 | Silver Garden Pole & Yoga | 0.13 | *(3rd not captured)* | | ✅ PASS |
| **golf_course** | **Puttshack - Miami** | **0.49** | **4.3** | Granada Golf Course | 5.17 | Coral Gables G&CC | 5.14 | ❌ FAIL |
| marina | Hurricane Cove Marina | 2.12 | 4.6 | Rickenbacker Marina | 2.42 | Watson Island Marina. | 1.54 | ✅ PASS |
| waterfront_park | Bayfront Park | 0.57 | 4.7 | Regatta Park | 4.10 | Bal Harbour Waterfront Park | 8.88 | ✅ PASS |
| dog_park | Mary Brickell Park | 0.60 | 4.7 | Dogs & Cats Walkway | 0.80 | Dog Park - Margaret Pace | 1.47 | ✅ PASS |
| park | Lummus Park Historic District | 0.42 | 4.5 | José Martí Park | 0.43 | Fort Dallas Park | 0.31 | ✅ PASS |
| **hospital** | **CalmEffect** | **0.05** | — | **Auto Accident Clinic Miami** | **0.08** | **Parqueo fundacion bienestar** | **0.17** | ❌ FAIL |
| pharmacy | CVS Pharmacy | 0.39 | 3.4 | CVS Pharmacy | 0.46 | Walgreens Pharmacy | 0.44 | ✅ PASS |
| school | Trusted CLEs, LLC. | 0.05 | 5.0 | Miami Singing Lessons | 0.15 | New World School of the Arts | 0.18 | ⚠️ NR |
| transit_station | SW 1 Av & SW 1 St | 0.07 | 3.9 | NW 1 Av & NW 1 St | 0.07 | Government Center Station | 0.11 | ✅ PASS |
| shopping_center | PIANGOLA | 0.16 | 5.0 | Brickell City Centre | 0.53 | Sunglass Hut | 0.53 | ⚠️ NR |
| gas_station | Pump & Munch | 0.54 | 5.0 | Chevron | 0.54 | Shell | 0.66 | ✅ PASS |

**SA-7 / LA-5 (400 Brickell Ave / 100 SE 1st Ave area — Brickell)**

| Category | #1 | dist | ★ | #2 | dist | #3 | dist | Verdict |
|---|---|---|---|---|---|---|---|---|
| beach (SA-7/LA-5) | Hobie Island Beach Park | 1.53 | 4.6 | Hobie Island North | 1.23 | Biscayne Dog Friendly Beach | 2.29 | ✅ PASS |
| beach_access | Rickenbacker Causeway | 1.19 | 4.7 | Hobie Island Beach Park | 1.53 | Biscayne Dog Friendly Beach | 2.29 | ✅ PASS |
| grocery_store | Publix at Mary Brickell Village | 0.22 | 4.5 | Publix at Brickell Village | 0.28 | Publix at Miami River | 0.55 | ✅ PASS |
| coffee_shop | Starbucks | 0.01 | 3.8 | Pura Vida Miami | 0.06 | Freddo Brickell | 0.06 | ✅ PASS |
| restaurant | Bondi Sushi | 0.05 | 4.8 | Tokyo Tuna Sushi | 0.03 | Pura Vida Miami | 0.06 | ✅ PASS |
| top_rated_dining | T.I.T.T.S Chicken | 0.07 | 4.8 | Pura Vida Miami | 0.06 | Piola Italian Restaurant | 0.06 | ✅ PASS |
| fitness_center | ISI Elite Training - Brickell | 0.12 | 5.0 | F45 Training Brickell | 0.18 | SWEAT440 Fitness | 0.22 | ✅ PASS |
| gym | TREMBLE | 0.04 | 4.9 | HealthNomics | 0.12 | ISI Elite Training | 0.12 | ✅ PASS |
| **golf_course** | **Puttshack - Miami** | **0.34** | **4.3** | Granada Golf Course | 5.16 | Coral Gables G&CC | 5.14 | ❌ FAIL |
| marina | Vice City Marina | 0.27 | 4.7 | Epic Marina | 0.57 | Miamarina at Bayside | 1.19 | ✅ PASS |
| waterfront_park | Bayfront Park | 0.95 | 4.7 | Regatta Park | 3.64 | Bal Harbour Waterfront Park | 9.53 | ✅ PASS |
| dog_park | Mary Brickell Park | 0.41 | 4.7 | Dogs & Cats Walkway | 1.51 | Dog Park - Margaret Pace | 2.21 | ✅ PASS |
| park | Simpson Rockland Hammock Preserve | 0.42 | 4.5 | Southside Park | 0.22 | Miami Circle Nat'l Historic Landmark | 0.51 | ⚠️ NR |
| **hospital** | **Dr. Hamadiya Miami Wellness & Aesthetic** | **0.14** | **5.0** | **Alive Miami - IV Therapy** | **0.17** | One Medical Primary Care | 0.26 | ❌ FAIL |
| pharmacy | CVS Pharmacy | 0.10 | 3.7 | Walgreens Pharmacy | 0.33 | Publix Pharmacy at Brickell Village | 0.28 | ✅ PASS |
| school | Little Executives Preschool Brickell | 0.05 | 4.7 | Westfield Business School | 0.05 | Brickell International Academy | 0.10 | ⚠️ NR |
| transit_station | Tenth Street Promenade | 0.08 | 4.1 | Brickell Av & SE 12 St | 0.07 | SE 13th St & Brickell Av | 0.07 | ⚠️ NR |
| shopping_center | Mary Brickell Village | 0.17 | 4.5 | Brickell shopping | 0.28 | Down town Miami | 0.22 | ✅ PASS |
| gas_station | Exxon | 0.29 | 4.0 | Pump & Munch | 0.44 | Chevron | 0.44 | ✅ PASS |

**Market 5 notes:**
- **hospital (SA-6)** ❌ FAIL: CalmEffect (a mental health app/wellbeing company), Auto Accident Clinic Miami (personal injury chiropractic), and Parqueo fundacion bienestar (social services organization). Not one of the three results is a hospital or urgent care facility. Downtown Miami has hospitals within 0.5mi (Jackson Memorial is ~1mi), but none surfaces in top 3 — the worst hospital result in the sample.
- **hospital (SA-7/LA-5)** ❌ FAIL: Dr. Hamadiya Miami Wellness & Aesthetic Center (cosmetic clinic, 5.0★, 282 reviews) and Alive Miami IV Therapy ranked ahead of One Medical (the first legitimate clinical care provider at #3).
- **golf_course (SA-6 and SA-7/LA-5)** ❌ FAIL: Puttshack - Miami is an indoor entertainment venue at Brickell City Centre (Level 4 of a shopping mall). It carries Google's `golf_course` type. Nearest real courses are 5+ miles away in Coral Gables.
- **beach_access (SA-6)** ❌ FAIL: Only one result returned — "Beach Access Path" 6.6mi away with 4 reviews. An appropriate response for a downtown location without coastal access would be to suppress or clearly mark the category as "N/A."
- **coffee_shop (SA-6) #2/#3** ⚠️: Cacique Restaurant and Peruvian Fusion Cuisine are restaurants that appear under `coffee_shop` due to cafe-style Google tags.
- **school (SA-6)** ⚠️: "Trusted CLEs, LLC." is a continuing legal education provider. "Miami Singing Lessons" is a private music instruction business. New World School of the Arts (#3, 0.18mi, 4.5★) is the only legitimate institution.
- **school (SA-7/LA-5)** ⚠️: Preschool and business school surface before K-12 — reflects the reality of dense Brickell having minimal K-12 infrastructure.
- **transit (SA-7/LA-5)** ⚠️: "Tenth Street Promenade" is a bayside walkway, not a transit station. Metromover stations (0.07mi) are present but the promenade outranks them by review metrics.
- **shopping_center (SA-6) #1**: PIANGOLA is a single-store jeweler in Seybold Building — not a shopping center. Brickell City Centre (#2) is the correct answer.

**Market 5 Overall: PASS WITH TUNING NOTES on grocery/dining/fitness/marina/waterfront. FAIL on golf (both sub-markets), hospital (both sub-markets), beach_access (SA-6).**

---

### Market 6 — Windermere, FL (Golf Community)
*Listings: SA-70, SA-80, SA-82 (coordinate twins — identical results)*

**Representative profile: SA-70**

| Category | #1 | dist | ★ | #2 | dist | #3 | dist | Verdict |
|---|---|---|---|---|---|---|---|---|
| beach | West Beach Park | 4.58 | 4.4 | Disney's Beach Club Resort | 8.86 | Waturi Beach (Universal) | 4.38 | ⚠️ NR |
| beach_access | West Beach Park | 4.58 | 4.4 | Waturi Beach (Universal) | 4.38 | Clementine's Beach (Disney) | 6.18 | ❌ FAIL |
| boat_ramp | R.D. Keene Park | 1.90 | 4.6 | Lake Down Boat Ramp | 0.72 | R.D. Keene Park Boat Ramp | 1.88 | ✅ PASS |
| grocery_store | Publix at The Grove | 1.36 | 4.5 | Publix Plantation Grove | 2.43 | Walmart Neighborhood Market | 1.50 | ✅ PASS |
| coffee_shop | Dixie Cream Cafe | 0.29 | 4.5 | Paloma Parlor | 0.31 | Starbucks | 1.36 | ✅ PASS |
| restaurant | Dixie Cream Cafe | 0.29 | 4.5 | BurgerFi | 1.28 | Island Fin Poke Windermere | 1.29 | ⚠️ NR |
| top_rated_dining | Dixie Cream Cafe | 0.29 | 4.5 | BurgerFi | 1.28 | Hawkers Asian Street Food | 1.40 | ✅ PASS |
| fitness_center | Smart Fitness | 2.35 | 5.0 | Orangetheory Fitness | 2.23 | LA Fitness | 1.33 | ✅ PASS |
| gym | Body Coach Personal Training | 0.31 | 5.0 | HILI FITNESS WINDERMERE | 1.52 | LA Fitness | 1.33 | ✅ PASS |
| golf_course | Isleworth Golf & Country Club | 1.41 | 4.8 | Golden Bear Club | 2.71 | Orange Tree Golf Club | 3.47 | ✅ PASS |
| **marina** | **Fort Wilderness Marina** | **6.11** | **4.8** | Marina Landing | 4.45 | Lake Fairview Marina | 9.81 | ⚠️ NR |
| waterfront_park | Waterfront Park (Clermont) | 14.54 | 4.7 | Kissimmee Lakefront Park | 16.24 | Kissimmee Lakefront Park | 16.28 | ⚠️ NR |
| dog_park | Freedom Park | 1.98 | 4.5 | Summerport Park | 4.24 | Warrior Park | 3.09 | ✅ PASS |
| park | Town Square | 0.30 | 4.8 | Central Park | 0.15 | Windermere Recreation Area | 1.33 | ✅ PASS |
| hospital | Premier Medical | 1.50 | 4.0 | PremierMED Family Practice | 2.22 | Brooks Rehabilitation | 2.20 | ⚠️ NR |
| pharmacy | Publix Pharmacy at The Grove | 1.36 | 4.5 | CVS Pharmacy | 2.46 | CVS Pharmacy | 2.28 | ✅ PASS |
| school | Windermere Preschool (Family Church) | 0.29 | 4.3 | New Academy Inc | 0.20 | C2 Education of Windermere | 1.51 | ⚠️ NR |
| transit_station | Old Winter Garden Rd & Aliso Ridge Rd | 3.35 | — | Old Winter Garden Rd & Siena Gardens Cir | 3.35 | Old Winter Garden Rd & Rowe Ave | 3.44 | ⚠️ NR |
| shopping_center | The Grove | 1.28 | 4.6 | Cascades at Isleworth | 1.40 | Shoppes of Windermere | 1.49 | ✅ PASS |
| gas_station | Exxon | 2.35 | 4.2 | Shell | 2.30 | Mobil | 2.52 | ✅ PASS |

**Market 6 notes:**
- **golf_course** ✅ PASS: Isleworth G&CC (1.41mi, 4.8★), Golden Bear Club (2.71mi), Orange Tree GC (3.47mi) — exceptional results for a golf-community listing.
- **beach #2** ⚠️ FAIL: Disney's Beach Club Resort (8.86mi, 4.7★) is a Disney World themed hotel carrying a `beach` tag. Theme park resort hotels should be excluded from the beach category.
- **beach_access #2** ❌ FAIL: Waturi Beach (4.38mi) is Universal's Volcano Bay water park — a theme park attraction. #3 Clementine's Beach (Disney's Bay Lake) is another resort attraction.
- **marina** ⚠️: Fort Wilderness Marina (6.11mi) is a Disney resort marina. Marina Landing (#2, 4.45mi) is a general-public marina. NEEDS REVIEW — Disney venues should not surface as primary marina results for non-resort listings.
- **waterfront_park** ⚠️: Nearest waterfront park is 14.5mi (Clermont). This reflects Windermere's landlocked lake-community geography — accurate but may mislead users. Lake Windermere itself has no designated public waterfront park within a close radius.
- **hospital** ⚠️: Premier Medical (1.5mi, 4.0★) is an urgent care clinic, not a hospital. Nearest emergency facility (AdventHealth Windermere) is ~4mi but does not surface in top 3.
- **school** ⚠️: Windermere Preschool and C2 Education tutoring center — no K-12 public/private schools surface, likely because the Windermere area's schools are further (Windermere Preparatory is 3+ miles).
- **restaurant = coffee**: Dixie Cream Cafe tops both restaurant and coffee_shop — it is a combined cafe/restaurant. Acceptable overlap.

**Market 6 Overall: PASS WITH TUNING NOTES. Excellent golf results. Beach/beach_access impacted by resort/theme-park misclassification.**

---

### Market 7 — Downtown Orlando, FL
*Listings: SA-73, SA-76, SA-77 (coordinate twins — identical results)*

**Representative profile: SA-73**

| Category | #1 | dist | ★ | #2 | dist | #3 | dist | Verdict |
|---|---|---|---|---|---|---|---|---|
| **beach** | **Waturi Beach (Universal)** | **7.72** | **4.7** | **Aria Beach Apartments** | **4.69** | Lake Sybelia Beach Park | 6.14 | ❌ FAIL |
| **beach_access** | **Wall Street Plaza** (nightlife) | **0.30** | **4.5** | **The Beacham** (concert venue) | **0.32** | Waturi Beach (Universal) | 7.72 | ❌ FAIL |
| boat_ramp | George Barker Park | 1.75 | 4.4 | Lake Ivanhoe Boat Ramp | 1.89 | Dinky Dock Park | 4.37 | ✅ PASS |
| grocery_store | Publix at The Paramount on Lake Eola | 0.47 | 4.4 | Sy's Supermarket | 0.50 | Publix at Colonialtown | 1.46 | ✅ PASS |
| coffee_shop | Starbucks | 0.05 | 4.1 | Nature's Table Cafe | 0.05 | CFS Coffee For The Soul | 0.14 | ✅ PASS |
| restaurant | Between Breads | 0.02 | 4.1 | Mordisco | 0.02 | Sandona nariño | 0.02 | ⚠️ NR |
| top_rated_dining | The Boheme | 0.03 | 4.4 | Bosendorfer Lounge | 0.03 | Nature's Table Cafe | 0.05 | ⚠️ NR |
| fitness_center | SoFit Personal Training | 0.55 | 4.9 | SUBU CrossFit | 0.51 | Citrus Club Spa & Fitness | 0.11 | ✅ PASS |
| gym | Citrus Club Spa & Fitness Center | 0.11 | 4.6 | YogaMix Orlando | 0.45 | SUBU CrossFit | 0.51 | ✅ PASS |
| golf_course | The Country Club of Orlando | 2.02 | 4.7 | Dubsdread Golf Course | 3.11 | Winter Park Golf Course | 4.89 | ✅ PASS |
| marina | Lake Fairview Marina | 4.51 | 4.7 | Marina Landing | 5.44 | Lake Fairview Boat Ramp | 4.57 | ✅ PASS |
| waterfront_park | Lake Fairview Park | 4.64 | 4.3 | Kissimmee Lakefront Park | 17.16 | Kissimmee Lakefront Park | 17.20 | ⚠️ NR |
| dog_park | Lake Eola Park | 0.59 | 4.7 | Dickson Azalea Park | 1.32 | Greenwood Urban Wetlands | 1.03 | ✅ PASS |
| park | Heritage Square Park | 0.31 | 4.4 | Sperry Fountain | 0.38 | **St. Johns River** | **0.003** | ❌ FAIL |
| hospital | PeopleOne Health \| Rosencare Downtown | 0.49 | 4.9 | Orlando Health Women's Institute | 0.49 | Orlando Health Children's Neuroscience | 0.49 | ✅ PASS |
| pharmacy | Walgreens Specialty at Orlando Regional | 0.74 | 4.2 | FamilyCare Discount Pharmacy | 1.12 | Publix Pharmacy at The Paramount | 0.48 | ✅ PASS |
| school | AdventHealth School of the Arts | 0.16 | 4.7 | Weekday School | 0.17 | Wesley Child Development Center | 0.08 | ✅ PASS |
| transit_station | E South St & S Orange Ave | 0.04 | 1.0 | W South St & Boone Ave | 0.08 | S Orange Ave & E Jackson St | 0.09 | ⚠️ NR |
| shopping_center | SNS Showroom - Spa Nail Supply | 1.73 | 4.8 | SODO Shopping Center | 1.56 | Market at Southside | 1.88 | ⚠️ NR |
| gas_station | Chevron | 0.54 | 3.0 | Sunshine Food Mart #280 | 0.55 | Coast Oil | 0.76 | ✅ PASS |

**Market 7 notes:**
- **beach** ❌ FAIL: Waturi Beach (Universal's Volcano Bay water park, 7.72mi) and Aria Beach Apartments (an apartment complex, 4.69mi) are the top two results. Orlando has no ocean/gulf proximity; no real beach exists within 60+ miles. The pipeline should return null/empty or explicitly suppress the beach category for landlocked markets.
- **beach_access** ❌ FAIL: Wall Street Plaza is an Orlando nightlife/bar complex. The Beacham is a concert venue. Both venues contain "Beach" in their names and are picked up by proximity + place type. This is the most extreme misclassification in the sample.
- **park #3** ❌ FAIL: "St.Johns River" (0.003mi, 5.0★, 1 review) is a waterway/river body, not a park. It ranks inflated due to extreme proximity (essentially on top of the listing coordinates). A river body should be excluded from `park` category.
- **restaurant** ⚠️: Between Breads (0.02mi, 15 reviews), Mordisco (0 reviews), Sandona nariño (0 reviews) — all three have minimal review data, suggesting the listing coordinate sits on an underdeveloped or office-only block where dining options are extremely sparse.
- **top_rated_dining** ⚠️: The Boheme and Bosendorfer Lounge are both hotel bar/restaurant concepts at the Grand Bohemian Hotel. Legitimate dining establishments, but hotel-restaurant surfacing consistently across markets is a recurring pattern worth addressing.
- **waterfront_park**: Lake Fairview Park (4.64mi) is the nearest true waterfront park. Lake Eola is 0.5mi but may not carry the `waterfront_park` place type in Google's taxonomy — a tuning opportunity.
- **shopping_center #1**: SNS Showroom (spa nail supply store) is a single-product specialty shop, not a shopping center.
- **hospital**: Multiple Orlando Health specialty institute sub-locations appear at the same address (207 West Gore Street, 0.49mi) — different building suite entries for the same hospital campus. Acceptable.

**Market 7 Overall: PASS WITH TUNING NOTES on most categories. FAIL on beach (theme park + apartment), beach_access (nightclub + concert venue), park #3 (river body). Critical need for landlocked-market beach suppression logic.**

---

### Market 8 — Ocala, FL (Rural / Low-Density)
*Listings: SA-84, SA-98, SA-99, SA-100 (coordinate twins — identical results)*

**Representative profile: SA-84**

| Category | #1 | dist | ★ | #2 | dist | #3 | dist | Verdict |
|---|---|---|---|---|---|---|---|---|
| beach | Carney Island Recreation Area | 15.95 | 4.6 | Eaton's Beach Sandbar & Grill | 18.43 | The Beach Ocala | 13.70 | ✅ PASS |
| beach_access | Tuscawilla Park | 0.76 | 4.6 | *(only 1 result)* | | | | ⚠️ NR |
| boat_ramp | Ray Wayside Park | 9.11 | 4.6 | Silver Springs State Park Launch | 6.78 | Ray Wayside boat launch | 9.13 | ✅ PASS |
| grocery_store | Publix at Churchill Square | 1.06 | 4.6 | Seminole Feed Stores | 0.43 | ALDI | 2.22 | ⚠️ NR |
| coffee_shop | The Gathering Cafe Downtown Ocala | 0.08 | 4.8 | Starbucks | 0.11 | Stella's Modern Pantry | 0.17 | ✅ PASS |
| restaurant | The Gathering Cafe Downtown Ocala | 0.08 | 4.8 | Taco 'N Madre | 0.05 | Jimmy John's | 0.07 | ✅ PASS |
| top_rated_dining | The Gathering Cafe Downtown Ocala | 0.08 | 4.8 | Taco 'N Madre | 0.05 | Starbucks | 0.11 | ✅ PASS |
| fitness_center | Iron Legion Strength + Combat | 0.32 | 4.9 | Zone Health and Fitness | 0.37 | Healthy Harts Fitness | 0.35 | ✅ PASS |
| gym | Iron Legion Strength + Combat | 0.32 | 4.9 | Zone Health and Fitness | 0.37 | Healthy Harts Fitness | 0.35 | ✅ PASS |
| golf_course | Ocala Golf Club | 2.86 | 4.4 | Country Club of Ocala | 5.33 | Ocala Palms Golf & Country Club | 4.18 | ✅ PASS |
| **marina** | **Salt Springs Run Marina** | **27.13** | **4.6** | Big Bass Grill Marina | 27.78 | Venetian Cove Marina | 31.03 | ⚠️ NR |
| waterfront_park | Ocala Wetland Recharge Park | 1.84 | 4.7 | *(only 1 result)* | | | | ⚠️ NR |
| dog_park | Tuscawilla Park | 0.76 | 4.6 | Legacy Park | 0.60 | Toms Park | 1.63 | ✅ PASS |
| park | Ocala Downtown Square | 0.22 | 4.7 | Tuscawilla Art Park | 0.48 | Tuscawilla Park | 0.76 | ✅ PASS |
| hospital | Padgett Medical Center Ocala | 0.49 | 4.9 | Unleashed Medical | 0.52 | AdventHealth Ocala | 0.80 | ✅ PASS |
| pharmacy | Publix Pharmacy at Churchill Square | 1.06 | 4.5 | *(SA-98/99/100: Recharge RX, 1.08mi)* | | CVS/Walgreens | | ⚠️ NR |
| school | Rockiversity | 0.14 | — | Happy Hearts Preschool | 0.50 | Generation Alive Christian Academy | 0.30 | ⚠️ NR |
| transit_station | NW 3rd Ave & NW 2nd St | 0.11 | — | E Silver Springs Blvd & NW 1st Ave W | 0.12 | NW 2 & Interfaith E | 0.14 | ✅ PASS |
| shopping_center | Churchill Square | 1.07 | 4.5 | Ocala West | 1.97 | Gaitway Plaza | 2.31 | ✅ PASS |
| gas_station | RaceTrac | 0.06 | 3.6 | Comfuel | 0.45 | Wawa | 0.58 | ✅ PASS |

**Market 8 notes:**
- **beach** ✅: Distant lake beaches (15–18mi) are legitimate for a landlocked rural market. The low coastal score (10) and absence of Beach Lovers lifestyle tag correctly signal this. These results are appropriate.
- **grocery #2** ⚠️: Seminole Feed Stores (0.43mi, 4.6★) is an agricultural supply/feed store. It appears before Publix (1.06mi) because of proximity and rating, and likely carries grocery-adjacent place type tags for its equine/livestock feed products. NEEDS REVIEW.
- **marina** ⚠️: Nearest marina is 27.1mi (Salt Springs Run Marina on Lake George). For a rural inland city, no marinas exist within a practical radius. The category should display a "None nearby" state.
- **hospital** ✅: Padgett Medical Center Ocala (0.49mi, 4.9★, 718 reviews) is a strong primary care clinic. AdventHealth Ocala (#3, 4.2★, 4,797 reviews) is the region's major hospital campus. Excellent results for a rural market.
- **school** ⚠️: Rockiversity (0.14mi, no rating/reviews) is an arts performance venue. Happy Hearts Preschool (#2) and Generation Alive Christian Academy (#3) are real educational institutions. NEEDS REVIEW — Rockiversity should not rank ahead of schools.
- **beach_access / waterfront_park**: Only one result each — Tuscawilla Park serves both. The park is a multi-use urban park with pond features. Acceptable for a landlocked market but the single-result return indicates sparse data for these categories in rural areas.

**Market 8 Overall: PASS WITH TUNING NOTES. Grocery #2 (feed store), marina distance, school #1 need attention. Core categories (coffee, restaurant, fitness, hospital, golf, gas, shopping, transit, park) perform well.**

---

## 5. Cross-Market Category Analysis

### 5.1 Categories That Perform Well (PASS across 7+ of 8 markets)

| Category | Performance Summary |
|---|---|
| **grocery_store** | Publix/Whole Foods surfaces correctly in all major markets. Only Seminole (bp gas station) is a hard fail; NE St. Pete SA-121 (Target) and Ocala (feed store at #2) are minor notes. |
| **fitness_center / gym** | Consistently surfaces genuine gyms across all 8 markets. Zero systemic failures. |
| **top_rated_dining** | Quality dining results across all markets. Minor note: hotel restaurants (Grand Bohemian/Savoy) surface in Orlando and Miami Beach. |
| **waterfront_park** | Correct results in all coastal and urban markets. Windermere (14.5mi) and Orlando (4.6mi) results reflect geographic reality. |
| **marina** | Accurate in all coastal markets. Only fails for landlocked markets where no marinas exist (Ocala at 27mi). |
| **boat_ramp** | Accurate across all 8 markets with plausible distances. |
| **dog_park** | Consistently returns legitimate dog parks across all markets. |
| **gas_station** | No failures across any market. |
| **coffee_shop** | Strong across all 8 markets. Minor: restaurant appearing under coffee (SA-6), Italian restaurant under coffee (SA-11/48). |
| **golf_course** | Excellent in Treasure Island, NE St. Pete, Miami Beach, Windermere, Orlando, Ocala. Fails only in Downtown Miami/Brickell (Puttshack) and Seminole (Smugglers Cove). |
| **park** | Good across 6 of 8 markets. Fails in Orlando (river body) and has minor dog-park overlap in Treasure Island. |
| **shopping_center** | Correct in most markets. Minor issues in Downtown Miami SA-6 (#1 is a jewelry store) and Downtown Orlando SA-73 (#1 is a nail supply store). |

### 5.2 Categories That Need Tuning

| Category | Failure Rate | Root Cause | Affected Markets |
|---|---|---|---|
| **pharmacy** | 2 of 8 markets | Veterinary clinics carry `pharmacy` place type for pet medications | Seminole ❌, NE St. Pete SA-121 ❌ |
| **school** | 3 of 8 markets | Wellness studios, life coaches, music studios, enrichment centers carry Google's `school` type | Miami Beach ❌, Treasure Island ⚠️, NE St. Pete ⚠️ |
| **hospital** | 4 of 8 markets | Aesthetic/wellness clinics, IV therapy spas, specialist surgical practices rank above genuine hospitals | Downtown Miami ❌, Brickell ❌, NE St. Pete ❌, Miami Beach ⚠️ |
| **grocery_store** | 1 of 8 markets | Gas stations with convenience stores carry `grocery_or_supermarket` type | Seminole ❌ |
| **beach / beach_access** | 4 of 8 markets | Hotels with "Beach Club" name, theme park water attractions, nightlife/entertainment venues surface | Miami Beach ⚠️/❌, Windermere ❌, Orlando ❌, Treasure Island legacy ❌ |
| **golf_course** | 2 of 8 markets | Entertainment mini-golf venues carry `golf_course` place type | Downtown Miami/Brickell ❌, Seminole ❌ |
| **transit_station** | 1 of 8 markets | Walgreens pharmacy carries transit station Google tag | Miami Beach ❌ |

---

## 6. Identified Issues — Prioritized

### Issue #1 — Veterinary Clinics Ranked as Pharmacies ❌ CRITICAL
**Observed:** SA-183 (Seminole) — Animal Hospital of Seminole; SA-121 (NE St. Pete) — Florida Veterinary Clinic  
**Root cause:** Veterinary practices that dispense pet medications carry Google's `pharmacy` place type.  
**Fix:** During POI type filtering, exclude results whose name contains veterinary indicators ("Veterinary", "Animal Hospital", "Pet Pharmacy") or whose secondary types include `veterinary_care`.

### Issue #2 — Gas Station Ranked as Grocery Store ❌ CRITICAL
**Observed:** SA-183 (Seminole) — bp gas station as #1 grocery  
**Root cause:** BP convenience store carries Google's `grocery_or_supermarket` place type.  
**Fix:** Exclude results whose primary Google type is `gas_station` or `convenience_store` from the grocery_store category, regardless of secondary type tags.

### Issue #3 — Cosmetic/Specialist Clinics Ranked as Hospitals ❌ HIGH
**Observed:** LA-5 (Brickell) — Dr. Hamadiya Wellness & Aesthetic Center, Alive Miami IV Therapy; SA-52/67 (NE St. Pete) — Eyelid Surgeons; SA-6 (Downtown Miami) — CalmEffect, Auto Accident Clinic  
**Root cause:** Cosmetic clinics, IV therapy spas, and specialty surgical practices carry `doctor` or `hospital` place types. They achieve top scores via proximity + high ratings.  
**Fix:** For hospital category, require at least one of: `hospital`, `urgent_care`, or `general_practitioner`. Exclude types solely classified as `beauty_salon`, `health` without emergency-care signals, or names containing cosmetic/aesthetic keywords.

### Issue #4 — Life Coaches / Wellness Studios Ranked as Schools ❌ HIGH
**Observed:** SA-4, SA-5, LA-36 (Miami Beach) — Jolie Glassman life coach (#1), State of Yoga (#2)  
**Root cause:** Life coaches and wellness studios frequently carry Google's `school` place type. South Beach has limited K-12 infrastructure, so these dominate.  
**Fix:** Require school category results to include `school`, `primary_school`, `secondary_school`, or `university` in place types. Exclude results whose only types are `health`, `gym`, or `beauty_salon`.

### Issue #5 — Hotels Ranked as Beach / Beach Access ❌ HIGH
**Observed:** SA-4, SA-5, LA-36 (Miami Beach) — The Savoy Hotel as #1 beach and #1 beach_access  
**Root cause:** The Savoy Hotel carries Google's `beach` type due to its private beach club. Proximity (0.14mi) and review volume (1,634) elevate it above public beach access points.  
**Fix:** Exclude results whose primary type is `lodging` or `hotel` from `beach` and `beach_access` categories.

### Issue #6 — Theme Park / Entertainment Venues Ranked as Beach / Beach Access ❌ HIGH
**Observed:** SA-70/80/82 (Windermere) — Waturi Beach (Universal, beach_access #2), Disney's Beach Club Resort (beach #2); SA-73/76/77 (Orlando) — Wall Street Plaza nightclub (beach_access #1), The Beacham concert venue (beach_access #2)  
**Root cause:** Theme park water attractions and entertainment venues carry `beach` or `tourist_attraction` tags with high review volumes. Inland markets have no real beaches, so proximity-optimized ranking surfaces these results.  
**Fix:** Exclude `amusement_park` and `tourist_attraction` primary types from beach categories. Add a coastline proximity guard: suppress `beach` and `beach_access` categories entirely for listings whose coordinates are >15mi from any U.S. coastline.

### Issue #7 — Entertainment Mini-Golf Ranked as Golf Course ❌ MEDIUM
**Observed:** LA-5, SA-6, SA-7 (Downtown Miami/Brickell) — Puttshack (indoor mall entertainment); SA-183 (Seminole) — Smugglers Cove Adventure Golf  
**Root cause:** Entertainment mini-golf venues carry Google's `golf_course` place type. Their high proximity + review volume ranks them above real courses that may be 5+ miles away.  
**Fix:** Block known entertainment golf venue names (Puttshack, Topgolf, Drive Shack, Smugglers Cove, Adventure Golf, mini golf) from the golf_course category. Or require `golf_course` primary type with minimum course yardage/type signal from place details.

### Issue #8 — Pharmacy Ranked as Transit Station ❌ MEDIUM
**Observed:** SA-4, SA-5, LA-36 (Miami Beach) — "Walgreens 524 Jefferson Ave Miami Beach" as #1 transit station  
**Root cause:** The Walgreens location carries a `transit_station` tag in Google's dataset (a bus stop is at that intersection).  
**Fix:** For transit_station category, require results to have `transit_station`, `bus_station`, `train_station`, or `subway_station` as their primary type. Exclude results whose name matches retail pharmacy chains.

### Issue #9 — Vacation Rental Ranked as Beach Access ❌ MEDIUM
**Observed:** LA-71, LA-121, LA-122 (Treasure Island legacy) — "★ Treasure Island Getaway w/Private Beach Access!" as #1 beach_access  
**Root cause:** A vacation rental Google Places listing carries beach-access-adjacent place type tags.  
**Fix:** For beach_access category, exclude results whose name contains rental/lodging indicators ("Getaway", "Vacation", "Private Beach Access" paired with non-park contexts) or whose primary type is `lodging`.

### Issue #10 — River/Water Body Ranked as Park ❌ LOW
**Observed:** SA-73/76/77 (Orlando) — "St.Johns River" at 0.003mi as park #3  
**Root cause:** Google Places record for a waterway carries a `park` tag and inflates proximity score to near-zero distance.  
**Fix:** Require minimum review count (≥5) for park category; exclude results whose name includes "River", "Creek", "Canal", "Lake" without accompanying park type, or whose primary type is `natural_feature`.

### Issue #11 — Duplicate Transit Stop Entries ⚠️ LOW
**Observed:** SA-11, SA-48 (Treasure Island) — "Blind Pass Rd + 87th Ave" appears at ranks 1 and 3 at marginally different coordinates  
**Root cause:** Google Places has two records for the same physical bus stop at slightly different GPS coordinates.  
**Fix:** Deduplicate transit_station results by name (case-insensitive, exact match) before ranking — keep only the closest instance.

### Issue #12 — Templated Lifestyle Narrative (All Listings) ⚠️ LOW
**Observed:** All 25 listings — identical boilerplate text regardless of scores  
**Root cause:** `location_narrative` is a static string, not parameterized by actual score tier.  
**Fix:** Introduce tier-based sentence templates. At minimum, suppress coastal/beach sentences for listings with `coastal_score < 40`, suppress "Beach Lovers" mentions for listings where the `beach` category nearest result is >10mi.

---

## 7. Market-Type Generalizations

| Property Archetype | Typical Issues | High-Confidence Categories |
|---|---|---|
| Gulf-front barrier island (Treasure Island) | School (sparse K-12), transit (bus stop sign data), legacy vacation rental as beach_access | Beach, boat_ramp, marina, grocery, dining, waterfront_park |
| Suburban inland (NE St. Pete, Seminole) | Hospital (specialist clinics), pharmacy (veterinary), grocery (gas station) | Fitness, grocery (usually), golf, park, transit |
| Urban luxury beach (Miami Beach) | School (wellness studios), transit (Walgreens tag), beach_access (hotel) | Dining, marina, golf, waterfront_park, fitness, beach (rank 2–3) |
| CBD/High-rise urban (Brickell, Downtown Miami) | Golf (Puttshack), hospital (wellness clinics), school (preschool/business), beach_access (inland suppression needed) | Grocery, dining, coffee, fitness, marina, waterfront_park |
| Golf community suburban (Windermere) | Beach (resort hotel, theme park), marina (Disney), hospital (urgent care only) | Golf ✅✅✅, grocery, coffee, fitness, boat_ramp |
| Urban downtown inland (Orlando) | Beach (theme park, apartment complex), beach_access (nightclub, concert venue), park (river body) | Grocery, coffee, fitness, hospital, pharmacy, golf, school |
| Rural / low-density (Ocala) | Grocery #2 (feed store), marina (27mi), school (arts venue) | Coffee, fitness, golf, hospital, gas_station, grocery #1, park, transit |

---

## 8. Data Quality Observations

1. **NULL rating records (legacy):** LA-71, LA-121, LA-122, SA-121, SA-183 have NULL rating/user_ratings_total in all POI rows. These predate the current schema columns. Consumer-facing display must handle NULL gracefully (e.g., "No rating yet" vs. broken star widget).
2. **Rank-1 only (legacy):** Legacy records store only rank=1 per category; rank 2–3 are absent. Confirmed via query. Any display assuming Top-3 availability must guard for missing rows.
3. **Garbled place name:** "otel by At" appears as beach_access #3 for SA-4 and SA-5 (Miami Beach). This is a truncated hotel name from a Google Places API response stored without sanitization. Ingestion should trim/validate place names.
4. **Score variance between coordinate twins:** Minor floating-point variance observed (e.g., waterfront_park ranking_score: 96.28 vs 96.29 between SA-4 and SA-5). Benign — not a bug.
5. **Transit stop review sparsity:** Most transit_station results carry 0–4 reviews. Expected for bus stop entries. The pipeline should not penalize valid stops solely for low review count; the ranking_score should weight transit type/distance over review volume for this category.
6. **Waterfront park single-result returns:** Ocala and parts of Windermere return only 1 result for `waterfront_park`. Display should handle fewer-than-3 results gracefully.

---

## 9. Final Verdict by Category

| Category | Verdict | Priority Fix |
|---|---|---|
| grocery_store | ⚠️ NEEDS TUNING | Exclude gas_station primary type |
| hospital | ⚠️ NEEDS TUNING | Exclude aesthetic/wellness/specialist clinics — high priority, 4 markets |
| pharmacy | ❌ BROKEN | Exclude veterinary_care type — recurring across 2 markets |
| school | ❌ BROKEN | Exclude wellness/health-only types — recurring across 3 markets |
| beach | ⚠️ NEEDS TUNING | Exclude lodging; suppress for landlocked markets (>15mi from coast) |
| beach_access | ❌ BROKEN | Exclude lodging, amusement parks, entertainment; add coastal proximity guard |
| golf_course | ⚠️ NEEDS TUNING | Exclude entertainment mini-golf by name/type — 2 markets |
| transit_station | ⚠️ NEEDS TUNING | Require transit primary type; deduplicate stop records |
| park | ⚠️ NEEDS TUNING | Minimum review count filter; exclude waterways/river bodies |
| restaurant | ✅ PASS | No systemic fix needed |
| top_rated_dining | ✅ PASS | No fix needed |
| coffee_shop | ✅ PASS | Minor: exclude pure-restaurant results |
| fitness_center | ✅ PASS | No fix needed |
| gym | ✅ PASS | No fix needed |
| marina | ✅ PASS | Consider max-distance cap display for landlocked markets |
| boat_ramp | ✅ PASS | No fix needed |
| dog_park | ✅ PASS | No fix needed |
| waterfront_park | ✅ PASS | No fix needed |
| gas_station | ✅ PASS | No fix needed |

---

## Appendix A — Per-Listing Top-3 POI Summary

This appendix provides a per-listing traceability record for all 25 listings across all categories. Results are drawn directly from the `property_location_pois` table. Categories with Top-3 available show three results; legacy records (†) and sparse markets may have fewer.

### A.1 Treasure Island / St. Pete Beach — Shared Profile (SA-11 ≡ SA-48; LA-71† ≡ LA-121† ≡ LA-122†)

> SA-11 and SA-48 are full-profile records. LA-71, LA-121, and LA-122 are legacy — rank-1 only, NULL ratings.  
> All five listings share coordinates 27.7497°N, 82.7614°W.

**beach**

| Rank | Name | Dist (mi) | ★ | Reviews |
|---|---|---|---|---|
| 1 | Sunset Beach Pavilion | 0.42 | 4.7 | 536 |
| 2 | Sunset Beach | 0.54 | 4.8 | 593 |
| 3 | Sunset Beach Park | 0.64 | 4.3 | 523 |
| *LA-71/121/122 #1* | *Sunset Beach Place* | *0.15* | *—* | *—* |

**beach_access** ⚠️/❌

| Rank | Name | Dist (mi) | ★ | Reviews | Note |
|---|---|---|---|---|---|
| 1 (SA-11/48) | Ring Billed Gull Parking Lot | 0.34 | 4.6 | 96 | |
| 2 | Sunset Beach Pavilion | 0.42 | 4.7 | 536 | |
| 3 | Sunset Beach (direct access) | 0.54 | 4.8 | 593 | |
| 1 (LA-71/121/122) | ★ Treasure Island Getaway w/Private Beach Access! | 0.05 | — | — | ❌ vacation rental |

**boat_ramp** | Egan Park 0.39mi 4.4★ / Jungle Prada Park 2.70mi 4.7★ / Freedom Boat Club 2.59mi 4.7★ | ✅ PASS

**grocery_store** | Publix TI 1.19mi 4.6★ / Publix S. Pasadena 1.65mi 4.5★ / Publix Royale 3.18mi 4.6★ | ✅ PASS

**coffee_shop** | The Marquise Cafe 0.41mi 4.6★ / Carino's Northern Italian 0.45mi 4.5★⚠️ / Grove Surf+Coffee 0.86mi 4.8★ | ⚠️ NR

**restaurant** | The Marquise Cafe 0.41mi 4.6★ / Caddy's Treasure Island 0.32mi 4.3★ / Shrimpys Waterfront 0.44mi 4.5★ | ✅ PASS

**top_rated_dining** | The Marquise Cafe / Shrimpys Waterfront / Tiki Bagel | ✅ PASS

**fitness_center** | Beach Bods Gym 1.17mi 4.8★ / The Gym St Pete Beach 0.76mi 4.9★ / Best Day Fitness 2.42mi 5.0★ | ✅ PASS

**gym** | The Gym St Pete Beach 0.76mi 4.9★ / Beach Bods Gym 1.17mi 4.8★ / Fit For Life 0.76mi 4.5★ | ✅ PASS

**golf_course** | Pasadena Yacht & CC 2.42mi 4.6★ / Treasure Bay Golf & Tennis 1.25mi 4.3★ / Isla Del Sol YCC 3.73mi 4.6★ | ✅ PASS

**hospital** | HCA Florida Pasadena Hospital 1.34mi 4.5★ / Palms of Pasadena Wound Care 1.28mi 4.2★⚠️ / Publix Pharmacy 1.65mi 4.1★⚠️ | ⚠️ NR

**pharmacy** | CVS Pharmacy 1.77mi 4.9★ / Publix Pharmacy 1.65mi 4.1★ / Walgreens 0.82mi 3.5★ | ✅ PASS

**park** | St Pete Beach Dog Park 0.40mi 4.5★⚠️ / Sunset Beach Park 0.64mi 4.3★ / Egan Park 0.39mi 4.4★ | ⚠️ NR (dog park as #1)

**school** | Steven Radeck Music 1.14mi 5.0★⚠️ / St Albans Episcopal Day School 0.47mi 5.0★ / Solaria Enrichment 0.38mi⚠️ | ⚠️ NR

**marina** | Blind Pass Marina 0.51mi 4.2★ / Freedom Boat Club TI 1.63mi 4.8★ / TI Hotel & Marina 1.64mi 4.0★ | ✅ PASS

**waterfront_park** | Col. Michael J. Horan Park 0.91mi 4.6★ / Bay Vista Park 7.99mi 4.7★ / Demens Landing 8.24mi 4.7★ | ✅ PASS

**dog_park** | St Pete Beach Dog Park 0.40mi 4.5★ / Sunset Beach Park 0.64mi 4.3★ / John Morroni Dog Park 2.07mi 4.6★ | ✅ PASS

**transit_station** | Blind Pass Rd + 87th Ave 0.35mi / Blind Pass Rd + Captiva Cir 0.35mi / Blind Pass Rd + 87th Ave 0.35mi⚠️ (duplicate) | ⚠️ NR

**shopping_center** | Corey Ave Shopping District 0.87mi 4.5★ / South Pasadena Shopping Center 1.74mi 4.3★ / Village Plaza 1.10mi 4.6★ | ✅ PASS

**gas_station** | Exxon 0.84mi 3.8★ / 7-Eleven Fuel 0.43mi — / St Pete Beach CITGO 0.84mi 3.0★ | ✅ PASS

---

### A.2 NE St. Petersburg — Shared Profile (SA-52 ≡ SA-67; SA-121† legacy rank-1 only)

> SA-52 and SA-67 are coordinate twins at 27.8532°N, 82.6455°W (full Top-3, rated).  
> SA-121 shares same coordinates — legacy, rank-1 only, NULL ratings. Key SA-121 deviations noted below.

**beach** | Gandy Beach Mangroves 2.60mi 4.4★ / Gandy Beach 3.45mi 4.3★ / Gulfport Beach 8.91mi 4.7★ | ⚠️ NR

**beach_access** | Gandy Beach Mangroves 2.60mi 4.4★ / Gandy Beach 3.45mi / East Kayak Launch 3.86mi | ⚠️ NR

**boat_ramp** | Crisp Park Boat Ramp 3.59mi 4.7★ / Sunlit Cove Boat Ramp 0.73mi 4.2★ / Gandy Bridge Kayak Launch 3.86mi | ✅ PASS

**grocery_store (SA-52/67)** | Publix Gateway Crossing 0.95mi 4.6★ / Publix Gateway Mall 0.56mi 4.4★ / ALDI 2.89mi 4.5★ | ✅ PASS  
**grocery_store (SA-121)** | Target Grocery 0.48mi — | ⚠️ NR

**coffee_shop** | Roe's Cafe & Catering 0.32mi 4.8★ / Starbucks 0.44mi 4.4★ / 7-Eleven 0.34mi 3.8★ | ✅ PASS

**restaurant** | Roe's Cafe & Catering 0.32mi 4.8★ / Killer Pizza 0.38mi 4.8★ / Wok & Roll 0.33mi 3.9★ | ✅ PASS

**top_rated_dining** | Killer Pizza / Roe's Cafe / Starbucks | ✅ PASS

**fitness_center** | Elevate St. Pete 0.38mi 4.8★ / Epic Health & Fitness 0.95mi 4.8★ / Anytime Fitness 1.70mi 4.7★ | ✅ PASS

**gym** | Elevate St. Pete / Epic Health & Fitness / Pilates on 4th 0.51mi 5.0★ | ✅ PASS

**golf_course** | Mangrove Bay & Cypress Links 1.77mi 4.0★ / Feather Sound CC 3.33mi 4.5★ / Vinoy Golf Club 4.15mi 4.6★ | ✅ PASS

**hospital (SA-52/67)** | Eyelid Surgeons of Tampa Bay 0.06mi 4.9★❌ / Sinus & Nasal Institute FL 0.36mi 4.6★ / Tampa Bay Med 0.93mi 4.2★ | ❌ FAIL  
**hospital (SA-121)** | Eyelid Surgeons of Tampa Bay 0.06mi — | ❌ FAIL

**pharmacy (SA-52/67)** | Apex Care Pharmacy 0.77mi 4.9★ / CVS 0.53mi 4.0★ / CVS 0.48mi 3.7★ | ✅ PASS  
**pharmacy (SA-121)** | Florida Veterinary Clinic 0.45mi — | ❌ FAIL

**park** | Riviera Bay Park 1.12mi 4.6★ / Fossil Park 1.32mi 4.5★ / Fossil Park Youth Baseball 1.33mi 4.6★⚠️ | ⚠️ NR

**school** | Adventure Place Children's Center 0.11mi 3.4★⚠️ / Tot Tenders Learning Center 0.42mi 4.3★ / Blue Line Boating 0.74mi 4.8★⚠️ | ⚠️ NR

**marina** | Vinoy Marina 5.33mi 4.5★ / St. Pete Municipal Marina 5.73mi 4.6★ / Harborage Marina 6.52mi 4.7★ | ✅ PASS

**waterfront_park** | Freedom Lake Park 3.27mi 4.6★ / Vinoy Park 5.26mi 4.8★ / Granada Terrace Park 4.21mi 4.7★ | ✅ PASS

**dog_park** | Riviera Bay Park / Freedom Lake Park / Crescent Lake Dog Park | ✅ PASS

**transit_station** | Dr MLK Jr St N + 89th Ave N 0.08mi / +90th Ave N 0.11mi / +90th Ave N 0.12mi | ⚠️ NR

**shopping_center** | Gateway Market Center / Northgate Shops / Gateway Crossings | ✅ PASS

**gas_station** | Shell 0.27mi 2.9★ / SUPER GAS 0.43mi 3.8★ / Wawa 0.46mi 3.5★ | ✅ PASS

---

### A.3 Seminole — SA-183 (legacy, rank-1 only per category)

| Category | #1 Result | Dist (mi) | Verdict |
|---|---|---|---|
| beach | Indian Shores | 1.91 | ✅ PASS |
| beach_access | Public Beach Access, 16258 Gulf Blvd | 1.62 | ✅ PASS |
| boat_ramp | Lake Seminole Park Boat Ramp | 1.70 | ✅ PASS |
| grocery_store | **bp** (gas station) | **0.72** | ❌ FAIL |
| coffee_shop | Einstein Bros. Bagels | 0.75 | ⚠️ NR |
| restaurant | Einstein Bros. Bagels | 0.75 | ⚠️ NR |
| top_rated_dining | *(same as restaurant)* | — | ⚠️ NR |
| fitness_center | Your Pilates Lifestyle | 0.80 | ✅ PASS |
| gym | Your Pilates Lifestyle | 0.80 | ✅ PASS |
| golf_course | **Smugglers Cove Adventure Golf** | **1.84** | ❌ FAIL |
| marina | Madeira Beach Marina | 1.85 | ✅ PASS |
| waterfront_park | Seminole Waterfront Park | 1.25 | ✅ PASS |
| dog_park | Dog Park (Lake Seminole Park) | 0.85 | ✅ PASS |
| park | Big Hook Island | 0.55 | ⚠️ NR |
| hospital | AFC Urgent Care Seminole | 0.85 | ✅ PASS |
| pharmacy | **Animal Hospital of Seminole** | **0.89** | ❌ FAIL |
| school | Blessed Sacrament Catholic School | 0.23 | ✅ PASS |
| transit_station | Park Blvd + 110th St | 0.82 | ✅ PASS |
| shopping_center | Seminole City Center | 0.85 | ✅ PASS |
| gas_station | bp | 0.72 | ✅ PASS |

---

### A.4 Miami Beach — Shared Profile (SA-4 ≡ SA-5 ≡ LA-36)

> All three listings share coordinates 25.7747°N, 80.1300°W. Full Top-3, rated.

| Category | #1 / Dist / ★ | #2 / Dist | #3 / Dist | Verdict |
|---|---|---|---|---|
| beach | Savoy Hotel & Beach Club / 0.14 / 4.6⚠️ | Muscle Beach South Beach / 0.29 | Lummus Park / 0.43 | ⚠️ NR |
| beach_access | Savoy Hotel & Beach Club / 0.14 / 4.6❌ | Marjory Stoneman Douglas Park / 0.29 | South Beach Car Park / 0.16 | ❌ FAIL |
| boat_ramp | Miami Beach Marina / 0.64 / 4.7 | Rickenbacker Marina / 3.41 | Crandon Park Marina / 3.83 | ✅ PASS |
| grocery_store | La Playa Market / 0.29 / 4.5 | Meridian Market & Cafe / 0.40 | Publix at Fifth & Alton / 0.62 | ⚠️ NR |
| coffee_shop | On Ocean 7 Cafe / 0.17 / 4.9★ (12,108 reviews!) | Gelato-Go South Beach / 0.14 | Avalon By Day / 0.18 | ✅ PASS |
| restaurant | Barbizon Restaurant / 0.11 / 4.5 | The Savoy H&BC / 0.14⚠️ | Gelato-Go / 0.14 | ⚠️ NR |
| top_rated_dining | Alma Cubana Cuban Restaurant / 0.16 / 4.9★ (11,572) | American Bistro / 0.15 | Barbizon Restaurant / 0.11 | ✅ PASS |
| fitness_center | StreetBarbell Beach Gym / 0.11 / 4.8 | South Beach Boxing / 0.28 | Muscle Beach South Beach / 0.29 | ✅ PASS |
| gym | StreetBarbell Beach Gym / 0.11 / 4.8 | F45 Training South Pointe / 0.25 | South Beach Boxing / 0.28 | ✅ PASS |
| golf_course | Fisher Island Club / 0.95 / 4.7 | Miami Beach Golf Club / 1.92 | La Gorce Country Club / 4.29 | ✅ PASS |
| marina | BouYah Watersports/MB Marina / 0.73 / 4.8 | Miami Beach Marina / 0.64 | One Island Park Marina / 1.13 | ✅ PASS |
| waterfront_park | South Pointe Park / 0.64 / 4.8★ (10,779) | Bayfront Park / 3.50 | Miami Shores Village Bayfront / 6.69 | ✅ PASS |
| dog_park | Marjory Stoneman Douglas Park / 0.29 / 4.6 | Washington Dog Park / 0.37 | Pinetree Park / 3.03 | ✅ PASS |
| park | Marjory Stoneman Douglas Park / 0.29 / 4.6 | Lummus Park / 0.43 | South Pointe Park / 0.64 | ✅ PASS |
| hospital | Baptist Health Miami Beach / 0.69 / 2.5★⚠️ | Evivia / 0.74 / 3.9★⚠️ | Publix Pharmacy / 0.62⚠️ | ⚠️ NR |
| pharmacy | CAN Community Health / 0.25 / 4.7⚠️ | CVS Pharmacy / 0.23 | Miami Beach Community Health / 0.70 | ⚠️ NR |
| school | Jolie Glassman life coach / 0.28 / 5.0❌ | State of Yoga / 0.35 / 5.0❌ | Bright Kids Swimming / 0.61 | ❌ FAIL |
| transit_station | Walgreens 524 Jefferson Ave / 0.18 / 5.0❌ | Washington Av & 5 St / 0.25 | Washington Ave and 5th St / 0.25 | ❌ FAIL |
| shopping_center | Shoppes of Il Villaggio / 0.87 / 4.4 | Lincoln Road Shopping District / 1.11 | Shoppes at West Avenue / 0.83 | ✅ PASS |
| gas_station | Shell / 0.43 / 3.5 | Marathon / 0.50 | Chevron / 1.09 | ✅ PASS |

---

### A.5 Downtown Miami — SA-6 (99 Flagler St area)

| Category | #1 / Dist / ★ | #2 / Dist | #3 / Dist | Verdict |
|---|---|---|---|---|
| beach | Hobie Island Beach Park / 2.30 / 4.6 | Historic Virginia Key Beach Park / 3.59 | Joia Beach / 1.49 | ✅ PASS |
| beach_access | Beach Access Path / 6.63 / 5.0 (4 reviews)❌ | *(only 1 result)* | | ❌ FAIL |
| boat_ramp | Hurricane Cove Marina / 2.12 / 4.6 | Rickenbacker Marina / 2.42 | Watson Island Marina / 1.54 | ✅ PASS |
| grocery_store | Whole Foods Market / 0.42 / 4.5 | Publix 3 Miami Central / 0.41 | Publix Miami River / 0.54 | ✅ PASS |
| coffee_shop | Atlantis Cafe / 0.09 / 4.7 | Cacique Restaurant / 0.08⚠️ | Peruvian Fusion Cuisine / 0.11⚠️ | ⚠️ NR |
| restaurant | Tâm Tâm / 0.05 / 4.6 | Jrk! / 0.05 / 4.6 | Holy Grill & Deli (Kosher) / 0.06 | ✅ PASS |
| top_rated_dining | Tâm Tâm / 0.05 / 4.6 | Jrk! / 0.05 / 4.6 | Atlantis Cafe / 0.09 | ✅ PASS |
| fitness_center | Monster Cast Fitness / 0.18 / 5.0 | Powerhouse Gym Miami / 0.26 | CKO Kickboxing Brickell / 0.49 | ✅ PASS |
| golf_course | Puttshack - Miami / 0.49 / 4.3❌ | Granada Golf Course / 5.17 | Coral Gables G&CC / 5.14 | ❌ FAIL |
| marina | Hurricane Cove Marina / 2.12 | Rickenbacker Marina / 2.42 | Watson Island Marina / 1.54 | ✅ PASS |
| waterfront_park | Bayfront Park / 0.57 / 4.7★ (21,390!) | Regatta Park / 4.10 | Bal Harbour Waterfront / 8.88 | ✅ PASS |
| dog_park | Mary Brickell Park / 0.60 / 4.7 | Dogs & Cats Walkway / 0.80 | Dog Park Margaret Pace / 1.47 | ✅ PASS |
| park | Lummus Park Historic District / 0.42 / 4.5 | José Martí Park / 0.43 / 4.5 | Fort Dallas Park / 0.31 | ✅ PASS |
| hospital | CalmEffect / 0.05 / —❌ | Auto Accident Clinic Miami / 0.08❌ | Parqueo fundacion bienestar / 0.17❌ | ❌ FAIL |
| pharmacy | CVS Pharmacy / 0.39 / 3.4 | CVS Pharmacy / 0.46 | Walgreens Pharmacy / 0.44 | ✅ PASS |
| school | Trusted CLEs, LLC. / 0.05 / 5.0⚠️ | Miami Singing Lessons / 0.15⚠️ | New World School of the Arts HS / 0.18 | ⚠️ NR |
| transit_station | SW 1 Av & SW 1 St / 0.07 / 3.9 | NW 1 Av & NW 1 St / 0.07 | Government Center Station / 0.11 / 4.4★ | ✅ PASS |
| shopping_center | PIANGOLA (jewelry) / 0.16⚠️ | Brickell City Centre / 0.53 | Sunglass Hut / 0.53⚠️ | ⚠️ NR |
| gas_station | Pump & Munch / 0.54 / 5.0 | Chevron / 0.54 | Shell / 0.66 | ✅ PASS |

---

### A.6 Brickell — Shared Profile (SA-7 ≡ LA-5)

| Category | #1 / Dist / ★ | #2 / Dist | #3 / Dist | Verdict |
|---|---|---|---|---|
| beach | Hobie Island Beach Park / 1.53 / 4.6 | Hobie Island North / 1.23 | Biscayne Dog Friendly Beach / 2.29 | ✅ PASS |
| beach_access | Rickenbacker Causeway / 1.19 / 4.7 | Hobie Island Beach Park / 1.53 | Biscayne Dog Friendly Beach / 2.29 | ✅ PASS |
| boat_ramp | Vice City Marina / 0.27 / 4.7 | Rickenbacker Marina / 1.67 | Marine Stadium Marina / 1.85 | ✅ PASS |
| grocery_store | Publix Mary Brickell Village / 0.22 / 4.5★ (3,444) | Publix Brickell Village / 0.28 | Publix Miami River / 0.55 | ✅ PASS |
| coffee_shop | Starbucks / 0.01 / 3.8 | Pura Vida Miami / 0.06 | Freddo Brickell / 0.06 | ✅ PASS |
| restaurant | Bondi Sushi / 0.05 / 4.8 | Tokyo Tuna Sushi / 0.03 | Pura Vida Miami / 0.06 | ✅ PASS |
| top_rated_dining | T.I.T.T.S Chicken / 0.07 / 4.8 | Pura Vida Miami / 0.06 | Piola Italian Restaurant / 0.06 | ✅ PASS |
| fitness_center | ISI Elite Training Brickell / 0.12 / 5.0 | F45 Training Brickell / 0.18 | SWEAT440 Fitness / 0.22 | ✅ PASS |
| gym | TREMBLE / 0.04 / 4.9 | HealthNomics / 0.12 | ISI Elite Training / 0.12 | ✅ PASS |
| golf_course | Puttshack - Miami / 0.34 / 4.3❌ | Granada Golf Course / 5.16 | Coral Gables G&CC / 5.14 | ❌ FAIL |
| marina | Vice City Marina / 0.27 / 4.7 | Epic Marina / 0.57 | Miamarina at Bayside / 1.19 | ✅ PASS |
| waterfront_park | Bayfront Park / 0.95 / 4.7★ (21,390) | Regatta Park / 3.64 | Bal Harbour Waterfront / 9.53 | ✅ PASS |
| dog_park | Mary Brickell Park / 0.41 / 4.7 | Dogs & Cats Walkway / 1.51 | Dog Park Margaret Pace / 2.21 | ✅ PASS |
| park | Simpson Rockland Hammock Preserve / 0.42 / 4.5 | Southside Park / 0.22 | Miami Circle Nat'l Historic Landmark / 0.51 | ⚠️ NR |
| hospital | Dr. Hamadiya Miami Wellness & Aesthetic / 0.14 / 5.0❌ | Alive Miami IV Therapy / 0.17❌ | One Medical Primary Care / 0.26 / 4.6 | ❌ FAIL |
| pharmacy | CVS Pharmacy / 0.10 / 3.7 | Walgreens Pharmacy / 0.33 | Publix Pharmacy Brickell / 0.28 | ✅ PASS |
| school | Little Executives Preschool / 0.05 / 4.7⚠️ | Westfield Business School / 0.05⚠️ | Brickell International Academy / 0.10 | ⚠️ NR |
| transit_station | Tenth Street Promenade / 0.08 / 4.1⚠️ | Brickell Av & SE 12 St / 0.07 | SE 13th St & Brickell Av / 0.07 | ⚠️ NR |
| shopping_center | Mary Brickell Village / 0.17 / 4.5★ (5,605) | Brickell shopping / 0.28 | Down town Miami / 0.22 | ✅ PASS |
| gas_station | Exxon / 0.29 / 4.0 | Pump & Munch / 0.44 | Chevron / 0.44 | ✅ PASS |

---

### A.7 Windermere — Shared Profile (SA-70 ≡ SA-80 ≡ SA-82)

> Three coordinate twins at 28.4977°N, 81.5316°W. Full Top-3, rated.

| Category | #1 / Dist / ★ | #2 / Dist | #3 / Dist | Verdict |
|---|---|---|---|---|
| beach | West Beach Park / 4.58 / 4.4 | Disney's Beach Club Resort / 8.86❌ | Waturi Beach (Universal) / 4.38❌ | ⚠️ NR/❌ |
| beach_access | West Beach Park / 4.58 / 4.4 | Waturi Beach / 4.38❌ | Clementine's Beach (Disney) / 6.18❌ | ❌ FAIL |
| boat_ramp | R.D. Keene Park / 1.90 / 4.6 | Lake Down Boat Ramp / 0.72 | R.D. Keene Park Ramp / 1.88 | ✅ PASS |
| grocery_store | Publix at The Grove / 1.36 / 4.5 | Publix Plantation Grove / 2.43 | Walmart Neighborhood Market / 1.50 | ✅ PASS |
| coffee_shop | Dixie Cream Cafe / 0.29 / 4.5 | Paloma Parlor / 0.31 | Starbucks / 1.36 | ✅ PASS |
| restaurant | Dixie Cream Cafe / 0.29 / 4.5⚠️ | BurgerFi / 1.28 | Island Fin Poke / 1.29 | ⚠️ NR |
| top_rated_dining | Dixie Cream Cafe / 0.29 | BurgerFi / 1.28 | Hawkers Asian Street Food / 1.40 | ✅ PASS |
| fitness_center | Smart Fitness / 2.35 / 5.0 | Orangetheory Fitness / 2.23 | LA Fitness / 1.33 | ✅ PASS |
| gym | Body Coach Personal Training / 0.31 / 5.0 | HILI FITNESS WINDERMERE / 1.52 | LA Fitness / 1.33 | ✅ PASS |
| golf_course | Isleworth Golf & Country Club / 1.41 / 4.8 | Golden Bear Club / 2.71 | Orange Tree Golf Club / 3.47 | ✅ PASS |
| marina | Fort Wilderness Marina (Disney) / 6.11 / 4.8⚠️ | Marina Landing / 4.45 | Lake Fairview Marina / 9.81 | ⚠️ NR |
| waterfront_park | Waterfront Park Clermont / 14.54 / 4.7⚠️ | Kissimmee Lakefront Park / 16.24 | Kissimmee Lakefront Park / 16.28 | ⚠️ NR |
| dog_park | Freedom Park / 1.98 / 4.5 | Summerport Park / 4.24 | Warrior Park / 3.09 | ✅ PASS |
| park | Town Square / 0.30 / 4.8 | Central Park / 0.15 | Windermere Recreation Area / 1.33 | ✅ PASS |
| hospital | Premier Medical / 1.50 / 4.0⚠️ | PremierMED Family Practice / 2.22 | Brooks Rehabilitation / 2.20 | ⚠️ NR |
| pharmacy | Publix Pharmacy The Grove / 1.36 / 4.5 | CVS Pharmacy / 2.46 | CVS Pharmacy / 2.28 | ✅ PASS |
| school | Windermere Preschool / 0.29 / 4.3⚠️ | New Academy Inc / 0.20 (no rating)⚠️ | C2 Education tutoring / 1.51⚠️ | ⚠️ NR |
| transit_station | Old Winter Garden Rd & Aliso Ridge Rd / 3.35⚠️ | Old Winter Garden Rd & Siena Gardens / 3.35 | Old Winter Garden Rd & Rowe Ave / 3.44 | ⚠️ NR |
| shopping_center | The Grove / 1.28 / 4.6 | Cascades at Isleworth / 1.40 | Shoppes of Windermere / 1.49 | ✅ PASS |
| gas_station | Exxon / 2.35 / 4.2 | Shell / 2.30 | Mobil / 2.52 | ✅ PASS |

---

### A.8 Downtown Orlando — Shared Profile (SA-73 ≡ SA-76 ≡ SA-77)

> Three coordinate twins at 28.5383°N, 81.3792°W.  
> Minor difference: waterfront_park #1 is "Lake Fairview Park" for SA-73, "Dinky Dock Park" for SA-76/77. All other categories identical.

| Category | #1 / Dist / ★ | #2 / Dist | #3 / Dist | Verdict |
|---|---|---|---|---|
| beach | Waturi Beach Universal / 7.72 / 4.7❌ | Aria Beach Apartments / 4.69❌ | Lake Sybelia Beach Park / 6.14 | ❌ FAIL |
| beach_access | Wall Street Plaza (nightclub) / 0.30 / 4.5❌ | The Beacham (concert venue) / 0.32❌ | Waturi Beach Universal / 7.72 | ❌ FAIL |
| boat_ramp | George Barker Park / 1.75 / 4.4 | Lake Ivanhoe Boat Ramp / 1.89 | Dinky Dock Park / 4.37 | ✅ PASS |
| grocery_store | Publix The Paramount on Lake Eola / 0.47 / 4.4 | Sy's Supermarket / 0.50 | Publix Colonialtown / 1.46 | ✅ PASS |
| coffee_shop | Starbucks / 0.05 / 4.1 | Nature's Table Cafe / 0.05 | CFS Coffee For The Soul / 0.14 | ✅ PASS |
| restaurant | Between Breads / 0.02 / 4.1 (15 reviews)⚠️ | Mordisco / 0.02 (0 reviews)⚠️ | Sandona nariño / 0.02 (0 reviews)⚠️ | ⚠️ NR |
| top_rated_dining | The Boheme / 0.03 / 4.4 (hotel restaurant)⚠️ | Bosendorfer Lounge / 0.03 (hotel)⚠️ | Nature's Table Cafe / 0.05 | ⚠️ NR |
| fitness_center | SoFit Personal Training / 0.55 / 4.9 | SUBU CrossFit / 0.51 | Citrus Club Spa & Fitness / 0.11 | ✅ PASS |
| gym | Citrus Club Spa & Fitness Center / 0.11 / 4.6 | YogaMix Orlando / 0.45 | SUBU CrossFit / 0.51 | ✅ PASS |
| golf_course | The Country Club of Orlando / 2.02 / 4.7 | Dubsdread Golf Course / 3.11 | Winter Park Golf Course / 4.89 | ✅ PASS |
| marina | Lake Fairview Marina / 4.51 / 4.7 | Marina Landing / 5.44 | Lake Fairview Boat Ramp / 4.57 | ✅ PASS |
| waterfront_park | Lake Fairview Park / 4.64 / 4.3 (SA-73) — Dinky Dock Park / 4.37 / 4.6 (SA-76/77) | Kissimmee Lakefront / 17.16 | Kissimmee Lakefront / 17.20 | ⚠️ NR |
| dog_park | Lake Eola Park / 0.59 / 4.7★ (24,571!) | Dickson Azalea Park / 1.32 | Greenwood Urban Wetlands / 1.03 | ✅ PASS |
| park | Heritage Square Park / 0.31 / 4.4 | Sperry Fountain / 0.38⚠️ | St. Johns River / 0.003❌ | ❌ FAIL |
| hospital | PeopleOne Health Rosencare Downtown / 0.49 / 4.9 | Orlando Health Women's Institute / 0.49 | Orlando Health Children's Neuroscience / 0.49 | ✅ PASS |
| pharmacy | Walgreens Specialty at Orlando Regional / 0.74 / 4.2 | FamilyCare Discount Pharmacy / 1.12 | Publix Pharmacy The Paramount / 0.48 | ✅ PASS |
| school | AdventHealth School of the Arts / 0.16 / 4.7 | Weekday School / 0.17 / 4.8 | Wesley Child Development Center / 0.08 | ✅ PASS |
| transit_station | E South St & S Orange Ave / 0.04 / 1.0⚠️ | W South St & Boone Ave / 0.08 | S Orange Ave & E Jackson St / 0.09 | ⚠️ NR |
| shopping_center | SNS Showroom - Spa Nail Supply / 1.73 / 4.8⚠️ | SODO Shopping Center / 1.56 | Market at Southside / 1.88 | ⚠️ NR |
| gas_station | Chevron / 0.54 / 3.0 | Sunshine Food Mart / 0.55 | Coast Oil / 0.76 | ✅ PASS |

---

### A.9 Ocala — Shared Profile (SA-84 ≡ SA-98 ≡ SA-99 ≡ SA-100)

> Four coordinate twins at 29.1872°N, 82.1401°W.  
> Minor deviation: pharmacy #1 is "Publix Pharmacy at Churchill Square" for SA-84; "Recharge RX" (1.08mi, compounding pharmacy) for SA-98/99/100. All other categories identical.

| Category | #1 / Dist / ★ | #2 / Dist | #3 / Dist | Verdict |
|---|---|---|---|---|
| beach | Carney Island Recreation Area / 15.95 / 4.6 | Eaton's Beach Sandbar / 18.43 | The Beach Ocala / 13.70 | ✅ PASS |
| beach_access | Tuscawilla Park / 0.76 / 4.6⚠️ | *(1 result only)* | | ⚠️ NR |
| boat_ramp | Ray Wayside Park / 9.11 / 4.6 | Silver Springs State Park Launch / 6.78 | Ray Wayside boat launch / 9.13 | ✅ PASS |
| grocery_store | Publix at Churchill Square / 1.06 / 4.6 | Seminole Feed Stores / 0.43 / 4.6⚠️ | ALDI / 2.22 | ⚠️ NR |
| coffee_shop | The Gathering Cafe Downtown Ocala / 0.08 / 4.8★ (1,086) | Starbucks / 0.11 | Stella's Modern Pantry / 0.17 | ✅ PASS |
| restaurant | The Gathering Cafe / 0.08 / 4.8 | Taco 'N Madre / 0.05 | Jimmy John's / 0.07 | ✅ PASS |
| top_rated_dining | The Gathering Cafe / 0.08 / 4.8 | Taco 'N Madre / 0.05 | Starbucks / 0.11 | ✅ PASS |
| fitness_center | Iron Legion Strength + Combat / 0.32 / 4.9 | Zone Health and Fitness / 0.37 | Healthy Harts Fitness / 0.35 | ✅ PASS |
| gym | Iron Legion Strength + Combat / 0.32 / 4.9 | Zone Health and Fitness / 0.37 | Healthy Harts Fitness / 0.35 | ✅ PASS |
| golf_course | Ocala Golf Club / 2.86 / 4.4 | Country Club of Ocala / 5.33 | Ocala Palms Golf & CC / 4.18 | ✅ PASS |
| marina | Salt Springs Run Marina / 27.13 / 4.6⚠️ | Big Bass Grill Marina / 27.78 | Venetian Cove Marina / 31.03 | ⚠️ NR |
| waterfront_park | Ocala Wetland Recharge Park / 1.84 / 4.7⚠️ | *(1 result only)* | | ⚠️ NR |
| dog_park | Tuscawilla Park / 0.76 / 4.6 | Legacy Park / 0.60 | Toms Park / 1.63 | ✅ PASS |
| park | Ocala Downtown Square / 0.22 / 4.7 | Tuscawilla Art Park / 0.48 | Tuscawilla Park / 0.76 | ✅ PASS |
| hospital | Padgett Medical Center Ocala / 0.49 / 4.9★ (718) | Unleashed Medical / 0.52⚠️ | AdventHealth Ocala / 0.80 / 4.2★ (4,797) | ✅ PASS |
| pharmacy (SA-84) | Publix Pharmacy Churchill Square / 1.06 / 4.5 | CVS / — | — | ✅ PASS |
| pharmacy (SA-98/99/100) | Recharge RX / 1.08⚠️ | — | — | ⚠️ NR |
| school | Rockiversity / 0.14 / —⚠️ | Happy Hearts Preschool / 0.50 | Generation Alive Christian Academy / 0.30 | ⚠️ NR |
| transit_station | NW 3rd Ave & NW 2nd St / 0.11 | E Silver Springs Blvd & NW 1st Ave W / 0.12 | NW 2 & Interfaith E / 0.14 | ✅ PASS |
| shopping_center | Churchill Square / 1.07 / 4.5 | Ocala West / 1.97 | Gaitway Plaza / 2.31 | ✅ PASS |
| gas_station | RaceTrac / 0.06 / 3.6 | Comfuel / 0.45 | Wawa / 0.58 | ✅ PASS |

---

## 10. Final Verdict by Category

| Category | Verdict | Priority Fix |
|---|---|---|
| grocery_store | ⚠️ NEEDS TUNING | Exclude gas_station primary type; review feed stores |
| hospital | ⚠️ NEEDS TUNING | Exclude aesthetic/wellness/specialist-only clinics — 4 markets affected |
| pharmacy | ❌ BROKEN | Exclude veterinary_care type — recurring across 2+ markets |
| school | ❌ BROKEN | Exclude wellness/health-only types — 3 markets affected |
| beach | ⚠️ NEEDS TUNING | Exclude lodging; suppress for landlocked markets (>15mi from coast) |
| beach_access | ❌ BROKEN | Exclude lodging/entertainment/amusement; add coastal proximity guard |
| golf_course | ⚠️ NEEDS TUNING | Exclude entertainment mini-golf by name blocklist or type filter |
| transit_station | ⚠️ NEEDS TUNING | Require transit primary type; deduplicate identical-name stops |
| park | ⚠️ NEEDS TUNING | Min review count filter; exclude waterways/river bodies |
| restaurant | ✅ PASS | No systemic fix needed |
| top_rated_dining | ✅ PASS | No fix needed |
| coffee_shop | ✅ PASS | Minor: exclude pure-restaurant results |
| fitness_center | ✅ PASS | No fix needed |
| gym | ✅ PASS | No fix needed |
| marina | ✅ PASS | Consider max-distance display cap (20mi) for landlocked markets |
| boat_ramp | ✅ PASS | No fix needed |
| dog_park | ✅ PASS | No fix needed |
| waterfront_park | ✅ PASS | No fix needed |
| gas_station | ✅ PASS | No fix needed |

---

*Report generated from live production data. 25 listings processed across 8 markets. All pipeline runs returned `status=success`. POI data sourced from `property_location_pois` and `property_location_dna` tables as of June 23, 2026. Legacy records (LA-71, LA-121, LA-122, SA-121, SA-183) have NULL ratings; coordinate twins confirmed via rank-1 cross-check.*
