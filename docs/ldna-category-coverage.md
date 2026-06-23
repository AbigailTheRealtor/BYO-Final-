# Location DNA — POI Category Coverage Report

**Generated:** 2026-06-23
**Source of truth:** `LocationDnaPoiDistanceService::CATEGORIES` (19 keys)
**Profile store:** `LocationDnaRankingProfileService::profiles()`

---

## Coverage Map

| Category key       | Label             | Query strategy | Dedicated profile | Tuning note |
|--------------------|-------------------|---------------|------------------|-------------|
| `grocery_store`    | Grocery Store     | native_type   | ✅ Yes           | Relevance + review weighted; penalizes gas_station/convenience_store dual-types |
| `school`           | School            | native_type   | ✅ Yes           | Distance-heavy (0.40); review weight low — many schools lack Google reviews |
| `hospital`         | Hospital          | native_type   | ✅ Yes           | Balanced distance + relevance; penalizes veterinary_care |
| `park`             | Park              | native_type   | ✅ Yes           | Review-heavy to favour developed city parks over unnamed natural features |
| `pharmacy`         | Pharmacy          | native_type   | ✅ Yes           | Balanced; penalizes veterinary_care and hospital types |
| `gas_station`      | Gas Station       | native_type   | ✅ Yes (added)   | Distance-dominant (0.55); reviews carry very little weight — proximity is the utility |
| `restaurant`       | Restaurant        | native_type   | ✅ Yes           | Rating + review heavy; distance secondary |
| `gym`              | Gym               | native_type   | ✅ Yes           | Balanced relevance + distance |
| `fitness_center`   | Fitness Center    | keyword       | ✅ Yes (alias)   | Intentionally reachable — uses keyword 'fitness center' with gym google_type to differentiate from `gym`; profile mirrors gym with same weights |
| `transit_station`  | Transit Station   | native_type   | ✅ Yes (added)   | Distance-dominant (0.65); review/rating weight minimal — proximity is the primary value; penalizes parking |
| `coffee_shop`      | Coffee Shop       | native_type   | ✅ Yes (added)   | Balanced review (0.30) + distance (0.30); quality signal matters; penalizes fast_food/meal_takeaway |
| `shopping_center`  | Shopping Center   | native_type   | ✅ Yes           | Balanced relevance + review; prefers shopping_mall/department_store types |
| `beach`            | Beach             | keyword       | ✅ Yes           | Review volume distinguishes named beach parks from municipality locality results |
| `beach_access`     | Beach Access      | keyword       | ✅ Yes           | Point-of-interest preferred; moderate distance weight |
| `boat_ramp`        | Boat Ramp         | keyword       | ✅ Yes           | Distance-leaning; low review threshold (20) — boat ramps rarely accumulate reviews |
| `marina`           | Marina            | keyword       | ✅ Yes           | Balanced; penalizes locality/political types |
| `waterfront_park`  | Waterfront Park   | keyword       | ✅ Yes           | Park + point_of_interest preferred; moderate distance weight |
| `dog_park`         | Dog Park          | keyword       | ✅ Yes           | Park preferred; low min_confidence_reviews (20) |
| `golf_course`      | Golf Course       | keyword       | ✅ Yes           | Point-of-interest/establishment preferred; amusement_park penalized; exclusion filter removes mini/adventure golf upstream |

**Derived category (not in CATEGORIES, produced post-fetch):**

| Category key       | Label             | Dedicated profile | Tuning note |
|--------------------|-------------------|------------------|-------------|
| `top_rated_dining` | Top Rated Dining  | ✅ Yes           | Higher review weight (0.40) and confidence threshold (100) than base restaurant profile |

---

## fitness_center Alias — Intent Confirmed

`fitness_center` IS a live entry in `CATEGORIES` (keyword strategy: `fitness center` + google_type `gym`). The profile in `profiles()` is reachable via `getProfile('fitness_center')` and is **not orphaned**. It was differentiated from `gym` in v2 to avoid returning identical Google Places results for both categories (audit finding: Section 8, gym/fitness_center structural duplicate).

---

## Gap Fill Summary (this task)

Three categories previously fell through to the `default` profile:

| Category key      | Gap status    | Resolution |
|-------------------|--------------|------------|
| `gas_station`     | ❌ Gap (was default) | ✅ Dedicated profile added — distance_weight=0.55 |
| `transit_station` | ❌ Gap (was default) | ✅ Dedicated profile added — distance_weight=0.65 |
| `coffee_shop`     | ❌ Gap (was default) | ✅ Dedicated profile added — review_weight=0.30, distance_weight=0.30 |

---

## Coverage Enforcement

A unit test in `LocationDnaRankingEngineTest` asserts that every key in `CATEGORIES` resolves to a **dedicated** profile (i.e., `profiles()` contains a matching key). Future category additions that omit a profile will fail the test suite immediately.
