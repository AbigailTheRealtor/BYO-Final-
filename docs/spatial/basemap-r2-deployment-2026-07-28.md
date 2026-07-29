# Basemap Deployment Record — Florida PMTiles on Cloudflare R2

**Date:** 2026-07-28 · **Owner:** Abigail
**Implements:** Decision **D2** (basemap tile source) — [roadmap](../spatial-integration-roadmap.md#d2--basemap-tile-source---resolved-2026-07-28) · [audit §7.2](../spatial-ui-integration-audit-2026-07-25.md)
**Status:** Infrastructure complete and verified. **Browser rendering still blocked on CORS.**

This record covers object storage and delivery only. No renderer code, no map library, no Blade
changes, no persisted geometry, and no branch were created as part of this work.

---

## 1. Decision recorded

**Self-hosted Protomaps `.pmtiles` for Florida, served from Cloudflare R2.**

Chosen over the two alternatives in audit §7.2: direct OSM raster tiles (violates the OSM tile
usage policy at application volume) and a hosted vector vendor such as MapTiler/Stadia (adds a
vendor, an API key, and a new-provider approval). The selected option introduces **no vendor, no
API key, and no usage policy to breach**, and aligns with SIA-D25.

Cost is storage plus egress on a single static file. Nationwide expansion later is a config change
plus a 9–18 GB upload — no application code changes.

---

## 2. Archive

| Field | Value |
|---|---|
| Object key | `basemaps/florida/20260726/florida-z15.pmtiles` |
| Size | `1,119,503,390` bytes (1.07 GiB) |
| BLAKE3 | `96864f80abbe43f97cbc833a6a022855b4933d2dcbeefc07397449e14dff299d` |
| SHA-256 | `856d18124d12d8f6753e8e607226e59aac7e1c502e967a99ec3371b9459138af` |
| ETag | `"2ca93f5944a7f0354e4722e222232b7e-17"` (multipart, 17 × 64 MiB) |
| Content-Type | `application/octet-stream` |
| Cache-Control | **unset** — see §6.3 |

### Provenance

| Field | Value |
|---|---|
| Source | `https://build.protomaps.com/20260726.pmtiles` (127.6 GiB planet) |
| Source ETag | `"695bfd734c3f7c5f5a90962f0e965052-511"` |
| Source Last-Modified | `Sun, 26 Jul 2026 09:04:44 GMT` |
| Extraction | `pmtiles extract` CLI 1.28.0, bbox `-87.634896,24.396308,-79.974306,31.000968`, `--maxzoom=15` |
| PMTiles spec | 3 · tile type `mvt` (vector) · clustered `true` · gzip |
| Zoom range | 0–15 (the source planet's own maximum — no detail lost) |
| Addressed tiles | 635,098 · 212,566 entries · 196,709 unique contents |
| Attribution | `© OpenStreetMap` (embedded in archive metadata) |
| OSM source-data timestamp | `2026-07-26T04:00:00Z` (replication sequence 121557) |
| planetiler | 0.10.2 · git `0e5588c4a6e8c29a270a33afe8df62027d889604` |

Full build provenance, including the size-vs-zoom comparison and observed upstream-retry behaviour,
is in `.spatial-scratch/c2-zcta/pmtiles/PROVENANCE-florida-z15.md`. **That path is scratch and not
tracked in git** — the fields above are reproduced here so the record survives its cleanup.

---

## 3. R2 configuration

Configuration is supplied entirely through environment variables. **No values are recorded in this
document**; only the variable names and their roles.

| Variable | Role |
|---|---|
| `BASEMAP_R2_ENDPOINT` | S3-compatible R2 endpoint |
| `BASEMAP_R2_BUCKET` | Dedicated basemap bucket — separate from listing-media |
| `BASEMAP_R2_ACCESS_KEY_ID` | Object-scoped access key |
| `BASEMAP_R2_SECRET_ACCESS_KEY` | Object-scoped secret |
| `BASEMAP_R2_PUBLIC_URL` | `r2.dev` managed public URL — **development and verification only** |
| `BASEMAP_PMTILES_OBJECT_PATH` | Object key; verified to match the uploaded key exactly |

**Custom domain: not configured** (owner decision, 2026-07-28). Cloudflare rate-limits `r2.dev` and
does not recommend it for production traffic; binding a custom domain is deferred and would also
give direct control over CORS and cache headers. Revisit before production launch.

### Credential scope

The token was confirmed confined to the basemap bucket:

| Probe | Result |
|---|---|
| `ListBuckets` (enumerate account) | 403 AccessDenied |
| `HeadBucket` / `ListObjectsV2` on basemap bucket | Allowed |
| `HeadBucket` on 6 other names, incl. `listing-media` | All unreachable |
| `GetBucketCors` | 403 AccessDenied — token is object-scoped, not admin |

R2 masks out-of-scope buckets as 404 rather than 403, so this is a name-based probe: strong
evidence, but the Cloudflare token scope page remains authoritative. No `AWS_*` configuration was
read or modified at any point; `listing-media` was probed read-only and was not reachable.

---

## 4. Integrity verification — all passed

Upload was multipart, 17 parts × 64 MiB, into an empty bucket (nothing overwritten). No dangling
multipart uploads remain; the bucket holds exactly one object.

| # | Check | Result |
|---|---|---|
| 1 | Local size / BLAKE3 / SHA-256 re-verified against provenance | ✅ all three match |
| 2 | Configured object path matches uploaded key | ✅ exact |
| 3 | Returned ETag vs independently computed multipart ETag | ✅ identical |
| 4 | Object size at rest | ✅ `1,119,503,390` |
| 5 | HTTP 206 ranged GET | ✅ at offset 0 and mid-file |
| 6 | `Accept-Ranges: bytes` | ✅ on HEAD, ranged GET, full GET |
| 7 | HEAD response | ✅ 200, correct length and content type |
| 8 | Credential-free readability via public URL | ✅ 200 (caveat §6.2) |
| 9 | BLAKE3 parity after round-trip | ✅ |
| 10 | SHA-256 parity after round-trip | ✅ |
| 11 | Payload structurally valid | ✅ magic `PMTiles`, spec version 3 |

Byte-for-byte parity was established **twice independently** after upload: once via authenticated
`GetObject`, once via a full 1.07 GiB credential-free download over the public URL. Both produced
the BLAKE3 and SHA-256 in §2.

---

## 5. CORS configuration to apply

🔴 **Not configured. This is the remaining blocker for browser rendering.**

Empirically probed: `OPTIONS` preflight returns 403 for every origin tested, and successful `206`
responses carrying an `Origin` header return **no** `Access-Control-Allow-Origin`. A browser-based
PMTiles reader is blocked by the same-origin policy today.

Applying this requires an **admin-scoped R2 credential** — the current object-scoped token cannot
read or write bucket CORS. Apply from the Cloudflare dashboard (R2 → bucket → Settings → CORS
Policy), which takes the rule array directly:

```json
[
  {
    "AllowedOrigins": [
      "https://REPLACE-WITH-APPROVED-PRODUCTION-ORIGIN"
    ],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedHeaders": ["Range", "If-Match"],
    "ExposeHeaders": ["ETag", "Content-Length"],
    "MaxAgeSeconds": 86400
  }
]
```

S3-API equivalent (`PutBucketCors`) wraps the same rule in `{"CORSRules": [ ... ]}`.

| Setting | Value | Rationale |
|---|---|---|
| `AllowedOrigins` | **Pending approval** | Not yet finalised. No wildcard. |
| `AllowedMethods` | `GET`, `HEAD` | Reads only — no `PUT`/`POST`/`DELETE` |
| `AllowedHeaders` | `Range`, `If-Match` | `Range` for tile fetches; `If-Match` for PMTiles consistency checks |
| `ExposeHeaders` | `ETag`, `Content-Length` | PMTiles clients read `ETag` to detect archive changes mid-session |
| `MaxAgeSeconds` | `86400` | Preflight cache lifetime |

### Rules for populating `AllowedOrigins`

**No origins have been inferred** from repository configuration, preview URLs, or development
environments, and **no wildcard (`*`) origin is present** — wildcards require explicit owner
approval. When the approved list is supplied, each entry must be scheme + host + explicit
non-default port only, with no trailing slash, no path, and no wildcard:

- `https://example.com` and `https://www.example.com` are **distinct origins**; list both if both serve maps.
- `http://` and `https://` are distinct. List `http://` only for an explicitly approved local dev origin.
- A non-default port is part of the origin: `http://localhost:5000` ≠ `http://localhost`.
- Staging, if approved, is a separate entry — it does not inherit from production.

### Optional additions, deliberately omitted

`Content-Range` and `Accept-Ranges` are not CORS-safelisted, so JavaScript cannot read them under
the policy above. PMTiles clients generally do not need to — they rely on `ETag` plus the response
body — so the list above is sufficient. Add them only if a client-side debug tool must display
range metadata. `Content-Length` is already safelisted as a response header; exposing it is
redundant but harmless.

### Verification required after applying

Cloudflare applies bucket CORS to `r2.dev` URLs, but this could not be confirmed empirically for
this bucket because the token cannot set the policy. Once applied, re-run the preflight: an
`OPTIONS` request carrying `Origin` and `Access-Control-Request-Method: GET` must return the
`Access-Control-Allow-*` headers, and a `206` response carrying `Origin` must return
`Access-Control-Allow-Origin`. Both return nothing today.

---

## 6. Open risks

### 6.1 CORS unconfigured — blocker
See §5. Blocked on the approved origin list, then an admin-scoped credential.

### 6.2 Cloudflare error 1010 on non-browser clients — operational risk, not a blocker
The `r2.dev` URL returns `403 / error code: 1010` to clients without a browser-like user-agent.
Reproduced at the bucket root, the target key, and a nonexistent key alike; a browser user-agent
returns `200`/`206` immediately. Browser rendering is unaffected. **Server-side consumers are** — a
Laravel-side fetch, curl smoke test, or uptime probe will receive 403 and read as an outage. This
is independent of CORS and will **not** be fixed by applying the §5 policy. Factor it in before any
automated monitoring depends on this URL.

### 6.3 `Cache-Control` unset — deferred decision
The object path is date-versioned (`20260726`), so `public, max-age=31536000, immutable` would be
safe and would substantially cut repeat egress. Deliberately not set — it is a deployment policy
choice. Revisit alongside the custom-domain decision.

### 6.4 Upstream BLAKE3 unanchored — pre-existing, carried forward
The Protomaps-published BLAKE3 for build `20260726` is not machine-retrievable: it is served only
through the JS-rendered builds UI, and every static path probed returned 404. Our hash chain is
internally consistent (local → R2 → public URL all agree), but is **not** anchored to a
publisher-signed hash. The source is pinned by URL + build date + byte size + ETag + Last-Modified.
Closing this requires an operator to read the BLAKE3 from the Protomaps builds UI and record it in
§2.

---

## 7. What remains before Phase 2 renderer work

Recorded so the roadmap is not read as "tiles are live, therefore the map can be built":

1. 🔴 **CORS configuration** — §5, pending approved production origins.
2. 🔴 **No map library installed** — `package.json` contains no `maplibre-gl` and no PMTiles client.
3. ⚪ **Renderer authorisation** — Phase 2 implementation has not been authorised; the "no temporary renderer" hold in the roadmap still stands.
4. ⚪ **Licence ordering (D1)** — Google Maps Content may not be displayed over a non-Google basemap, so Google data must leave the address path before or alongside the renderer swap. **D1 remains an open decision.**

Items 1 and 2 are infrastructure. Items 3 and 4 are owner decisions.

---

## 8. Restoring the local proof after a container or worktree restart

The proof route depends on configuration that does **not** survive a Replit container restart,
because it lives outside the repository. As in §3, no values are recorded here — only variable names.

### Why the page can report an unconfigured archive while the secrets plainly exist

`BASEMAP_R2_PUBLIC_URL` and `BASEMAP_PMTILES_OBJECT_PATH` are held as **Replit Secrets**. They are
therefore present in the container's process environment and resolve correctly from the CLI, which is
what makes this misleading: `artisan tinker` and `artisan config:show` report the archive URL as
configured while the served page disagrees.

The cause is that **`php artisan serve` may strip non-whitelisted variables from the
request-handling worker it spawns whenever a `.env` file is present** — it does this so that `.env`
edits hot-reload. The worker then sees neither variable, `config('spatial_basemap.pmtiles_url')`
composes to `null`, and the page renders "No PMTiles archive configured".

A CLI check is therefore **not** evidence that the route is configured. Verify over HTTP.

### Recovery

1. Copy these two values from the Replit Secrets into the worktree `.env`:

| Variable | Why it belongs in `.env` |
|---|---|
| `BASEMAP_R2_PUBLIC_URL` | Public managed URL; reaches the browser by design (§3) |
| `BASEMAP_PMTILES_OBJECT_PATH` | Object key; reaches the browser by design (§3) |

2. Preserve `SPATIAL_MAPLIBRE_PROOF_ENABLED=true`, or the route aborts 404 by design
   (`config/spatial_basemap.php`).

3. **Never** copy the credential set into `.env`: `BASEMAP_R2_ACCESS_KEY_ID`,
   `BASEMAP_R2_SECRET_ACCESS_KEY`, `BASEMAP_R2_ENDPOINT`, `BASEMAP_R2_BUCKET`. These are server-side
   S3 credentials; `config/spatial_basemap.php` deliberately does not read them, and the archive is
   fetched credential-free over the public URL — see §3, Credential scope.

4. Laravel watches `.env` and should restart its serve worker automatically. If it does not, restart
   **only** the Phase 2 artisan server; leave any process serving the main workspace checkout alone.

### Expected successful state

Load `/internal/spatial/maplibre-proof` and confirm all three:

- the Florida basemap renders;
- the loading message disappears — MapLibre's `load` event fired;
- PMTiles range requests return **206 Partial Content**, and more than one of them.

That last point is the sharp one. Exactly one 206 means only the archive header was read and no tile
ever followed — a different fault with a near-identical appearance, and not a configuration problem.
See the worker-URL note in `webpack.mix.js`.
