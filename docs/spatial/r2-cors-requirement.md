# R2 CORS — the blocking prerequisite for MapLibre basemap tiles

**Status: NOT APPLIED. This environment is not authorised to apply it.**
Recorded 2026-09-10, during Location DNA MapLibre Phase 2.

---

## The finding

The PMTiles archive is present, correct and range-served. It is unreadable **from a
browser**, and only from a browser.

Probed read-only from this container:

```
GET  <archive>                     Range: bytes=0-15
  -> 206 Partial Content
     Content-Range: bytes 0-15/1119503390
     Accept-Ranges: bytes
     Last-Modified: Tue, 28 Jul 2026 00:17:10 GMT

GET  <archive>   Origin: <any>     Range: bytes=0-15
  -> 206 Partial Content
     ** NO Access-Control-Allow-Origin header, for any origin tried **

OPTIONS <archive>  Origin: <any>
                   Access-Control-Request-Method: GET
                   Access-Control-Request-Headers: range
  -> 403 Forbidden
```

The bucket has **no CORS policy at all**. `curl` does not care; a browser does. Every
MapLibre tile request is therefore refused by the user agent before the response is
readable, and the map falls back to its blank backdrop.

## Why the map still works without it

`ldna-maplibre-renderer.js` treats the backdrop as the optional part. On the first source
error it swaps to the blank style **once** and repaints the geometry onto it, then reports
`degraded` with a sentence saying the saved areas are still shown and still editable. So
today, with the flags on, a user gets a working, interactive, pan/zoom/draw/edit map with
the correct geometry framed — on a flat background, with no streets, water or landuse.

Applying the policy below is what turns that flat background into a real map. Nothing else
changes; no code change accompanies it.

## Why this environment could not apply it

The R2 credentials present here are **object-scoped**, not admin-scoped. Verified against
the live endpoint:

| S3 operation | Result |
|---|---|
| `HeadObject` (object read) | **OK** — 1,119,503,390 bytes |
| `ListObjectsV2` (bucket read) | **OK** |
| `GetBucketCors` (bucket configuration) | **AccessDenied** |

`PutBucketCors` requires an **Admin Read & Write** R2 API token, or the Cloudflare
dashboard. Neither is available here, so nothing was attempted beyond the read above.

## Exactly where to apply it

Cloudflare dashboard → **R2** → bucket **`byo-basemap`** → **Settings** → **CORS Policy**
→ *Add CORS policy*.

(Equivalently, an S3 `PutBucketCors` call against
`https://<account>.r2.cloudflarestorage.com/byo-basemap?cors` using an API token with
Admin Read & Write.)

## Exactly what to apply

Deliberately **not** `AllowedOrigins: ["*"]`. The archive is public and read-only, so a
wildcard would leak nothing — but it also invites every other site to serve their maps
from our egress, and a named list is a record of who is expected to read it.

```json
[
  {
    "AllowedOrigins": [
      "https://3ce01d2c-f79e-44c2-b048-2e8dc64316ef-00-tu7aronbf5wk.spock.replit.dev",
      "https://*.replit.app"
    ],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedHeaders": ["range", "if-match"],
    "ExposeHeaders": ["content-range", "content-length", "etag", "accept-ranges"],
    "MaxAgeSeconds": 86400
  }
]
```

### Why each field is what it is

- **`AllowedOrigins`** — the first entry is this repository's Replit workspace, read from
  `REPLIT_DOMAINS`; it is the origin that serves the app today. The second covers Replit
  `vm` deployments, which is the `deploymentTarget` in `.replit`.
  **A custom production domain is NOT included, because this repository does not record
  one** — `.replit` deliberately declines to declare `APP_URL`/`ASSET_URL`, and
  `ProductionUrlIsolationTest` pins that. **If the app is served from a custom domain, add
  it to this list or the map will be flat there.** That is the one value only the owner can
  supply.
  Local development is omitted: nothing needs it, and `http://localhost` in a public
  bucket's policy outlives the reason it was added.
- **`AllowedMethods: GET, HEAD`** — PMTiles reads an archive with ranged `GET`s. `HEAD` is
  included because range readers commonly probe length first. **No `PUT`, `POST` or
  `DELETE`**: this policy must not make the bucket writable from a browser.
- **`AllowedHeaders: range`** — the request header PMTiles sends. `if-match` lets a reader
  pin the ETag across a multi-range read so a mid-read archive swap surfaces as an error
  rather than as silently mixed bytes.
- **`ExposeHeaders`** — a browser hides response headers from script unless they are
  exposed here. `content-range` and `accept-ranges` are what a range reader needs to
  confirm it got the bytes it asked for; without them the fetch succeeds and pmtiles cannot
  interpret it.
- **`MaxAgeSeconds: 86400`** — preflight cache. A tile-heavy session preflights once a day
  rather than once a request.

## Verification after applying

Re-run exactly the probes at the top of this file. The policy is correct when:

1. `OPTIONS` with an allowed `Origin` returns **204** (not 403) and carries
   `Access-Control-Allow-Origin` echoing that origin.
2. A ranged `GET` with an allowed `Origin` returns **206** **and**
   `Access-Control-Allow-Origin`, `Access-Control-Expose-Headers` including
   `content-range`.
3. An origin NOT in the list gets **no** `Access-Control-Allow-Origin` — proof the list is
   doing something.
4. In a browser on an allowed origin, with the flags on, the Location DNA panel reports
   `data-ldna-map-state="ready"` rather than `"degraded"`, and streets and water are
   visible under the geometry.

**Do not change the archive itself.** Its path, contents and hosting are correct; this is a
bucket-configuration change and nothing else.
