# Location DNA / POI / Nearby Amenities — System Audit

**Date:** 2026-06-06
**Scope:** Read-only audit. No production code was changed.

---

## 1. What Location Tables Exist?

Four tables are part of this system.

### `property_location_dna`
One row per listing (unique on `listing_type` + `listing_id`).

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint PK | Primary key |
| `listing_type` | varchar | Polymorphic type (e.g. `seller_agent_auction`) |
| `listing_id` | bigint | FK to the listing record |
| `source_address` | varchar | Street address as given at geocode time |
| `source_city` | varchar | City |
| `source_county` | varchar | County |
| `source_state` | varchar | State |
| `source_zip` | varchar | ZIP code |
| `geocoded_lat` | decimal(10,7) | Resolved latitude |
| `geocoded_lng` | decimal(10,7) | Resolved longitude |
| `geocode_source` | varchar | Always `'google'` for now |
| `geocode_status` | varchar | `pending` / `geocoded` / `failed` / `skipped` |
| `geocode_error` | text | Error message on failure |
| `geocoded_at` | timestamp | When geocoding last succeeded |
| `summary_json` | jsonb | Full compiled Location DNA summary (Phase D output) |
| `lifestyle_json` | jsonb | Lifestyle scores, categories, narrative (Phase 2 output) |
| `generated_at` | timestamp | When summary_json was last written |
| `created_at` / `updated_at` | timestamp | Standard timestamps |

Indexes: `geocode_status`, `geocoded_at`, unique on `(listing_type, listing_id)`.

---

### `property_location_pois`
One row per POI category per listing (unique on `listing_type` + `listing_id` + `poi_category`).

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint PK | Primary key |
| `listing_type` | varchar | Polymorphic type |
| `listing_id` | bigint | FK to the listing record |
| `poi_category` | varchar | Canonical category key (e.g. `beach`, `grocery_store`) |
| `poi_subtype` | varchar | Human-readable label (e.g. `'Beach'`, `'Grocery Store'`) |
| `poi_name` | varchar | Name returned by Google Places |
| `poi_address` | text | Vicinity string from Google Places |
| `poi_lat` | decimal(10,7) | POI latitude |
| `poi_lng` | decimal(10,7) | POI longitude |
| `source_lat` | decimal(10,7) | Property latitude used for this calculation |
| `source_lng` | decimal(10,7) | Property longitude used for this calculation |
| `distance_miles` | decimal(8,4) | Haversine straight-line distance in miles |
| `travel_time_minutes` | smallint | Reserved for a future phase — always NULL |
| `data_source` | varchar | Always `'google_places'` |
| `status` | varchar | `found` / `not_found` / `error` / `pending` |
| `error` | text | Error detail when status is `error` |
| `calculated_at` | timestamp | When this row was last written |
| `created_at` / `updated_at` | timestamp | Standard timestamps |

Indexes: `(listing_type, listing_id)`, `poi_category`, `status`.

---

### `property_location_dna_audits`
Append-only log of every pipeline event. Cannot be updated or deleted (enforced at the model level via `LogicException` in `boot()`).

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint PK | Primary key |
| `listing_type` | varchar | Polymorphic type |
| `listing_id` | bigint | FK to the listing record |
| `event_type` | varchar | `geocode` / `poi_distance` / `summary_generated` / `lifestyle_scores_generated` |
| `status` | varchar | Outcome status from the service |
| `source` | varchar | Data source, if applicable |
| `input_snapshot` | jsonb | Snapshot of inputs at event time |
| `output_snapshot` | jsonb | Snapshot of output at event time |
| `error` | text | Error message, if any |
| `created_at` | timestamp | Event timestamp |

Indexes: `(listing_type, listing_id)`, `event_type`, `status`, `created_at`.

---

### `property_dna_profiles.location_intelligence_context`
Not a dedicated table — a nullable `jsonb` column added to the existing `property_dna_profiles` table
by migration `2026_06_01_000001_add_location_intelligence_context_to_property_dna_profiles.php`.

`PropertyIntelligenceProfileService` writes the structured location context block from
`LocationDnaIntelligenceContextService` into this column as a caching side-effect of its `generate()`
call, so downstream reads can avoid re-querying `property_location_dna`.

---

## 2. What Fields Are Stored?

### In `property_location_dna.summary_json`
The Phase D Summary Service compiles this JSON. Top-level keys:

```
geocode               — lat, lng, source, geocoded_at
nearest_by_category   — keyed by poi_category; for each: label, name, distance_miles, status, data_source
category_counts       — total_categories, found, not_found, error
coastal               — nearest_beach_miles, nearest_beach_access_miles, nearest_boat_ramp_miles, nearest_marina_miles
daily_convenience     — nearest_grocery_miles, nearest_pharmacy_miles, nearest_coffee_miles, nearest_restaurant_miles
outdoor_recreation    — nearest_park_miles, nearest_dog_park_miles, nearest_golf_course_miles, nearest_waterfront_park_miles
transportation        — nearest_transit_miles, nearest_gas_station_miles
missing_categories    — list of poi_category keys with status not_found
error_categories      — list of poi_category keys with status error
```

### In `property_location_dna.lifestyle_json`
The Phase 2 Lifestyle Score Service writes this JSON:

```
version               — 'LDNA_LIFESTYLE_V1'
coastal_score         — int 0–100
walkability_score     — int 0–100
convenience_score     — int 0–100
commuter_score        — int 0–100
family_score          — int 0–100
lifestyle_categories  — array of label strings
location_narrative    — deterministic plain-English sentence
```

---

## 3. Are the Requested POI Categories Supported?

| Requested Category | Status | Notes |
|---|---|---|
| Beach | Supported | `beach` (keyword) + `beach_access` (keyword) |
| Grocery | Supported | `grocery_store` via Google type `grocery_or_supermarket` |
| Parks | Supported | `park` (native) + `dog_park` + `waterfront_park` (keyword) |
| Restaurants | Supported | `restaurant` via Google native type |
| Schools | Partial | `school` fetched and stored, but NOT mapped into any thematic block and NOT used in any lifestyle score |
| Airport | Not implemented | In Phase A governance plan but never added to `LocationDnaPoiDistanceService::CATEGORIES` |
| Downtown / City Center | Not implemented | In Phase A governance plan but never added |
| Transportation | Supported | `transit_station` via Google native type; drives `commuter_score` |
| Lifestyle categories | Supported | Eight labels: Beach Lovers, Boaters, Families, Retirees, Remote Workers, Commuters, Outdoor Enthusiasts, Convenience Seekers |

Additional implemented categories not in the original request:

| Category | Google Strategy |
|---|---|
| hospital | native type |
| pharmacy | native type |
| gas_station | native type |
| gym | native type |
| fitness_center | native type (maps to `gym`) |
| coffee_shop | native type (maps to `cafe`) |
| shopping_center | native type (maps to `shopping_mall`) |
| boat_ramp | keyword |
| marina | keyword |
| golf_course | keyword |

NOTE: `hospital`, `gym`, `fitness_center`, `shopping_center`, and `school` are all fetched and stored
in `property_location_pois` and appear in `nearest_by_category` in `summary_json`, but none of them
are wired into the four thematic blocks (`coastal`, `daily_convenience`, `outdoor_recreation`,
`transportation`). This means they do not contribute to lifestyle scores or to the structured context
blocks consumed by Ask AI or the Property DNA layer.

---

## 4. When Does Location DNA Generate?

Never automatically. There is no:
- Artisan command to invoke the pipeline
- Queued job (`app/Jobs/`) for background generation
- Event listener (`app/Listeners/`) triggered on listing save or update
- Livewire lifecycle hook calling any `LocationDna*` service
- Controller hook calling any `LocationDna*` service
- Route exposing a generation endpoint

The pipeline is only invoked from unit tests. Each service has a corresponding test file under
`tests/Unit/Services/LocationDna/`, and those tests call the services directly with mocked HTTP
clients or stubbed data.

The four services must be called in order for a listing to have complete Location DNA:
1. `LocationDnaGeocodeService::geocodeForListing()`
2. `LocationDnaPoiDistanceService::calculateForListing()`
3. `LocationDnaSummaryService::summarizeForListing()`
4. `LocationDnaLifestyleScoreService::generateForListing()`

There is no orchestrator class, pipeline runner, or facade that chains these four steps together.

---

## 5. Does It Require Latitude/Longitude?

No. Latitude and longitude are computed by the system, not provided as inputs.

Phase B (`LocationDnaGeocodeService`) requires only:
- `address` (required)
- `city` (required)
- `state` (required)
- `county` (optional)
- `zip` (optional)

It calls the Google Maps Geocoding API and stores `geocoded_lat` / `geocoded_lng` in
`property_location_dna`. Phase C reads lat/lng from the Phase B record and will not run if
`geocode_status` is not `'geocoded'`.

---

## 6. What Happens If Geocoding Fails?

The service is fully non-throwing. On any failure path:

| Failure Reason | Recorded Status | Behavior |
|---|---|---|
| Missing required address field (`address`, `city`, or `state` blank) | `skipped` | Record NOT created; audit row written |
| Missing Google API key | `failed` | Record created/updated with `geocode_status='failed'`; audit row written |
| Google API returns empty results | `failed` | `geocode_status='failed'`, `geocode_error` set; audit row written |
| Any Throwable (network error, DB failure, etc.) | `failed` | Record persists `geocode_status='failed'` if already initialized; output returned without re-throwing |

Downstream impact of geocoding failure:
- Phase C (`calculateForListing`) returns `skipped` — guards on `geocode_status === 'geocoded'`
- Phase D (`summarizeForListing`) returns `skipped` — same guard
- Phase 2 (`generateForListing`) returns `skipped` — same guard
- All three context services return `not_generated` (record exists but `summary_json` is null)
- Ask AI context includes `'location_intelligence'` in `missingSources`; degrades gracefully

Cache invalidation: if any of the five address fields change, the cached lat/lng is cleared and the
record resets to `geocode_status='pending'` before the next API call.

---

## 7. Is Location Intelligence Visible in Any UI?

No. Location DNA data is not rendered in any Blade view, Livewire component, or admin panel.

Confirmed by searching:
- `resources/views/**/*.blade.php`
- `app/Http/Controllers/**/*.php`
- `app/Http/Livewire/**/*.php`
- `routes/web.php` and `routes/api.php`

None reference `property_location_dna`, `lifestyle_json`, `summary_json`, `nearest_beach`,
`coastal_score`, or any Location DNA service.

The only consumers of location data are internal services:

| Consumer | What It Uses | How |
|---|---|---|
| `AskAiContextBuilderService` | `lifestyle_json` (scores, categories, narrative), `LocationDnaIntelligenceContextService` output, `LocationDnaMarketingContextService` output | Assembled into the `location_intelligence` context block for AI question answering |
| `AskAiPromptBuilderService` | `location_intelligence` context block | Included in AI prompt when the question type's `allowed_context` includes `location_intelligence` |
| `PropertyIntelligenceProfileService` | `LocationDnaIntelligenceContextService` output | Cached to `property_dna_profiles.location_intelligence_context`; included in the intelligence profile passed to Ask AI |
| `AskAiKnowledgeSourceRegistry` | Knowledge source description | Declares `location_intelligence` as a named source |

Location DNA is live inside the Ask AI question-answering pipeline only. It is not surfaced to any
user-facing listing page, agent dashboard, public listing card, or admin listing view.

---

## 8. Can It Be Regenerated?

Not through any current interface.
- No Artisan command exists
- No admin route exists
- No UI button or form exists
- No background queue job exists

Cache invalidation does exist within the services themselves:
- `LocationDnaGeocodeService`: address field change clears lat/lng and re-geocodes on next call
- `LocationDnaPoiDistanceService`: coordinate change deletes all POI rows and re-fetches all categories

To regenerate for a listing today, a developer must call the services directly via `php artisan tinker`
or a one-off script.

---

## 9. What Is Missing?

### Missing: Pipeline Trigger / Orchestration

There is no mechanism to run the Location DNA pipeline for any listing in any environment. The four
services are tested in isolation but nothing calls them on listing creation, update, approval, or on
any schedule.

Gap: An Artisan command (e.g., `location-dna:generate {listing_type} {listing_id}`) and/or a queue
job is needed to actually produce data in production.

---

### Missing: Pipeline Orchestrator

There is no class that chains Phase B -> C -> D -> Lifestyle in a single call. A facade or runner
class would reduce the risk of callers missing a step or running them out of order.

---

### Missing: Airport and Downtown Categories

Phase A governance planned these categories but they were never implemented in Phase C:

| Category | Phase A Status | Phase C Status |
|---|---|---|
| Airports | Approved | Not implemented |
| Downtown / City Center | Approved | Not implemented |

---

### Missing: Thematic Block Coverage for 5 POI Categories

These five categories are fetched and stored but are not mapped into any thematic block and therefore
do not feed into lifestyle scores, the intelligence context, or the marketing context:

| Category | Fetched | In summary_json | In thematic block | In lifestyle score |
|---|---|---|---|---|
| `school` | Yes | Yes (nearest_by_category only) | No | No |
| `hospital` | Yes | Yes (nearest_by_category only) | No | No |
| `gym` | Yes | Yes (nearest_by_category only) | No | No |
| `fitness_center` | Yes | Yes (nearest_by_category only) | No | No |
| `shopping_center` | Yes | Yes (nearest_by_category only) | No | No |

These distances are fetched from Google Places (at API cost) but are currently invisible to the
lifestyle scoring engine, context services, and Ask AI.

---

### Missing: Drive Time Calculation

`travel_time_minutes` column exists in `property_location_pois` and is always NULL. Explicitly marked
"reserved for a future phase." No drive/walk time API integration has been implemented.

---

### Missing: Any UI Surface

No listing page, public view, bid card, agent dashboard, or admin view displays:
- POI distances
- Lifestyle scores
- Location narrative
- Beach / grocery / park distances
- Any Location DNA data whatsoever

The data is fully computed and stored, but completely invisible to all users.

---

### Missing: Marketing Intelligence Integration (Phase G Deferred)

`LocationDnaMarketingContextService` was built but its output is not connected to the AI Marketing
Report pipeline (`AiMarketingReportGeneratorService`). The governance block on that service explicitly
documents this as deferred until a separately approved hook phase is planned.

---

### Missing: Admin Regeneration Tool

No admin panel interface exists to view, inspect, trigger, or regenerate Location DNA for any listing.
An admin cannot currently diagnose a listing with missing or stale location data without direct
database access.

---

### Missing: End-to-End Pipeline Test

Tests exist for each service in isolation. There is no integration test that exercises the full
B -> C -> D -> Lifestyle chain together, and no feature test that verifies Location DNA data appears
in the Ask AI response for a listing that has it.

---

## Summary Table

| Audit Question | Answer |
|---|---|
| Location tables exist? | 3 dedicated tables + 1 column on `property_dna_profiles` |
| Fields stored? | Geocode data, 19 POI category distances, thematic blocks, lifestyle scores, categories, narrative, audit trail |
| Beach supported? | Yes (`beach` + `beach_access`) |
| Grocery supported? | Yes |
| Parks supported? | Yes (park, dog_park, waterfront_park) |
| Restaurants supported? | Yes |
| Schools supported? | Fetched but not in thematic blocks or scores |
| Airport supported? | Not implemented |
| Downtown supported? | Not implemented |
| Transportation supported? | Yes (transit_station) |
| Lifestyle categories? | 8 labels derived from 5 scores |
| When does it generate? | Never automatically — no trigger exists anywhere |
| Requires lat/lng input? | No — geocoded from address |
| Geocoding failure behavior? | Graceful: persists failed status, downstream phases skip |
| Visible in any UI? | No — zero Blade views or controllers render this data |
| Can be regenerated? | No — no command, job, route, or UI button exists |
| Key missing pieces | Pipeline trigger, orchestrator, airport/downtown categories, thematic coverage for school/hospital/gym/fitness/shopping, drive times, any UI surface, admin tooling, Marketing Intelligence hook |
