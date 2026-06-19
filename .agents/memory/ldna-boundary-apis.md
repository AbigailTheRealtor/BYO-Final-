---
name: ldna boundary API sources
description: Which APIs to use for each location type when rendering GeoJSON boundaries on the Location DNA map, and how they queue.
---

## City and County Boundaries → Nominatim (queued)
Called via `ldnaEnqueueBoundary(key, url)` → `ldnaBoundaryProcess()` with 1,100ms between requests.

URL for city (with lat/lng viewbox bias):
```
https://nominatim.openstreetmap.org/search?q={CITY}%2C+USA
  &format=geojson&polygon_geojson=1&featuretype=city&countrycodes=us&limit=3
  &viewbox={lng-0.3},{lat+0.3},{lng+0.3},{lat-0.3}&bounded=0
```
The viewbox bias is CRITICAL for cities like "Seminole, FL" — without it Nominatim may return Seminole County (near Orlando) instead of the city in Pinellas County. The lat/lng comes from Google Places Autocomplete and is cached in `cityLatLngCache[label]`.

URL for county:
```
https://nominatim.openstreetmap.org/search?q={COUNTY}%2C+USA
  &format=geojson&polygon_geojson=1&countrycodes=us&limit=1
```

## ZIP Code Boundaries → Census TIGER primary, Nominatim fallback
**Primary**: Census TIGERweb ZCTA2020 — no rate limit, complete US coverage
```
https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/tigerWMS_ZCTA2020/MapServer/0/query
  ?f=geojson&where=GEOID20%3D'{ZIP}'&outFields=GEOID20&returnGeometry=true&geometryPrecision=3&outSR=4326
```
**Fallback**: Nominatim postalcode (US coverage is incomplete):
```
https://nominatim.openstreetmap.org/search?postalcode={ZIP}&country=US&format=geojson&polygon_geojson=1&limit=1
```
Both failures → `ldnaShowZipWarning()` shows inline red message (auto-hides in 10s).

ZIP fetches bypass the Nominatim rate-limit queue (`ldnaFetchZipBoundary` is a direct fetch).

## Neighborhoods → No Free GeoJSON Source
No reliable free GeoJSON source exists for US neighborhood boundaries. Neighborhoods were **removed from the UI entirely**. Existing saved data round-trips in JSON for backend matching.

## Map Styling
- Cities / Counties: blue (#0369a1), fillOpacity 0.10
- ZIP codes: purple (#7c3aed), fillOpacity 0.10
- All boundary features stored in `ldnaBoundaryOverlays[key]` as `[google.maps.Data.Feature]`
- "Clear Drawings" clears `ldnaOverlays` ONLY — boundaries are NOT cleared by it

## City lat/lng Cache
`cityLatLngCache[label]` is populated by Places Autocomplete `place_changed` listener (stores `{lat, lng}` from `place.geometry.location`). Used to build the viewbox bias URL. Only populated via autocomplete — manually-typed cities have no cache entry and get an unbiased Nominatim search.

## Places Autocomplete for Cities
Use `types: ['locality']` (NOT `['(cities)']`). `'(cities)'` can return counties/administrative areas, causing Seminole County to appear for "Seminole". `'locality'` is strictly city/town level.
