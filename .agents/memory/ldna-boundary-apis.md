---
name: ldna boundary API sources
description: Which free APIs to use for each location type when rendering GeoJSON boundaries on the Location DNA map.
---

## City and County Boundaries → Nominatim OpenStreetMap
URL pattern:
```
https://nominatim.openstreetmap.org/search?q={ENCODED_NAME}%2C+USA&format=geojson&polygon_geojson=1&featuretype=city&countrycodes=us&limit=1
```
- Free, no API key required
- Rate limit: **1 request per second per IP** — must use a request queue with ≥1,100ms delay between calls
- For counties: omit `featuretype=city`; Nominatim finds county polygons naturally
- Map styling: blue (#0369a1), fill opacity 0.1

## ZIP Code Boundaries → US Census Bureau TIGERweb ZCTA2020
URL pattern:
```
https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/tigerWMS_ZCTA2020/MapServer/0/query?where=GEOID20%3D%27{ZIP}%27&outFields=GEOID20&returnGeometry=true&geometryPrecision=4&outSR=4326&f=geojson
```
- Free, no API key, public US government API
- Complete US coverage (unlike Nominatim which is patchy for US ZIPs)
- Returns ZCTA (ZIP Code Tabulation Area) — approximate ZIP boundary
- Map styling: purple (#7c3aed), fill opacity 0.1

## Neighborhoods → No Free GeoJSON Source
No reliable free GeoJSON source exists for US neighborhood boundaries.
- Google Maps JS API does not expose neighborhood polygons
- OpenStreetMap coverage is incomplete/inconsistent for US neighborhoods
- **Decision**: Neighborhoods are matching/scoring data only — no boundary is rendered. Show an info note explaining this in the UI.

## State Boundaries
Not implemented — lower priority, no UI hook. Nominatim can fetch state polygons if needed in future.

## Implementation Notes
- All boundary overlays stored in `ldnaBoundaryOverlays` object (key → `[google.maps.Data.Feature]`)
- Nominatim requests queued in `ldnaBoundaryQueue` with 1,100ms between calls
- Census TIGER requests bypass Nominatim queue (no rate limit) but share same render path
- On multi-boundary: after each render, fit map to union of all boundary bounds
- `window.ldnaShowCountyBoundary(name)` / `ldnaHideCountyBoundary(name)` are public functions host blades can call to bridge the main form's county selection to the map
