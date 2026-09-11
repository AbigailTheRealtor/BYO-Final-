# Virtual Drive provider audit — Apple Look Around vs Google Street View

**Date:** 2026-09-11 · **Branch:** `audit/virtual-drive-provider-comparison` ·
**Base:** `910a79ecb` (origin/main when the worktree was created; origin/main has since moved
to `bbbe097dd`, 10 commits ahead, not rebased) · **Status:** internal proof. Not merged, not
deployed, not enabled for anyone.

## The answer, briefly

The benchmark: *drive down the street → see which house is for sale or for rent → click it →
keep shopping.*

- **Google Street View can deliver that with documented APIs. Apple Look Around cannot.** The
  difference is not image quality. It is whether the provider tells the page where the camera is.
  Google documents markers placed at a latitude/longitude inside a panorama, plus the camera's
  position and heading. Apple documents neither, and gives the page no way to set the heading.
- **Neither provider has rendered imagery in this environment.** There is no MapKit JS token and
  no Google browser key here (§11). Both proofs are built and run against real stored MLS
  listings, and each needs one credentialed session to confirm what its documentation says.
- **The Apple conclusion does not wait on that session.** A token cannot add an API that does
  not exist. The shipped library was inspected without a credential, and its public
  `LookAround` object has no camera member of any kind, documented or otherwise.

Evidence labels used below:

| Label | Meaning |
|---|---|
| **LIVE** | Observed in this environment |
| **DOC** | Vendor documentation (sources at the end) |
| **LIB** | Observed in the vendor's shipped library, loaded with no credential: no `init`, no map view |
| **NOT RUN** | Needs the missing credential |

---

## 1. Current architecture (before this proof)

- **Authoritative listing coordinates** are `bridge_properties.latitude` / `longitude`, which
  `BridgePropertyNormalizer` copies from the feed's own `Latitude` / `Longitude`.
  - `BridgeMlsCoordinatesAdapter` treats them as parcel-level precision; the feed carries no
    precision field.
  - Seller and Landlord offer listings copy them into `property_lat` / `property_lng` meta at
    import. Live sync never refreshes those.
  - Nothing in this proof geocodes anything.
- **Sale vs rent** is decided by `ExploreTransactionType`, an exact `PropertyType` match:
  - SALE: Residential, Income, Commercial Sale, Vacant Land, Business Opportunity.
  - RENT: Residential Lease, Commercial Lease.
  - `PropertyTypeVocabulary` is deliberately not used here, because it matches substrings and
    would turn a lease into a sale.
- **Status** is `StandardStatus`, and only `Active` is public.
- **Price** is `ListPrice`. On a lease it is the rent, and its period comes from
  `LeaseAmountFrequency`.
- **Media:**
  - Photos come from `raw_json.Media[]` through `MlsMediaExtractor`.
  - Only the unbranded tour (`VirtualTourURLUnbranded`) may be shown.
  - The feed has **no video field** licensed for display.
- **The safe public shape already exists.** BidYourOffer Explore (PR #147) provides
  `ExploreListingRepository`, then `ExploreEligibilityPolicy` (which uses the feed's display
  permissions, including address suppression), then `ExploreListingProjection` (an allow-list),
  plus `ExploreCanonicalListingResolver`, which links a listing to its BidYourOffer page. This
  proof reuses that stack and adds no listing-domain logic.
- **MapLibre** renders in exactly one place: the flag-gated Location DNA widget
  (`resources/js/spatial/`, off by default). Every other map in the app is Google: Explore 3D,
  Stellar buyer results, and the pin map.
- **Google credentials:**
  - `GOOGLE_PLACES_API_KEY` is a server key and is empty in this workspace.
  - Explore has its own `EXPLORE_GOOGLE_MAPS_BROWSER_KEY`, which is absent here.
  - Before this proof there was no Street View code anywhere; the only reference was a
    `streetViewControl: false` setting.
- **No Apple MapKit code existed** in the repo.
- **Engagement features:**
  - **Save does not exist anywhere in the application.**
  - Ask a Question and Schedule Showing exist only on BidYourOffer offer-listing pages
    (`offer.listing.{seller,landlord}.{question,showing}`).
  - Ask AI answers only a listing's owner.
  - A property that exists only in the MLS has none of these.
- **Browser-side Google is not governed by any server budget.** Quotas in Google Cloud are the
  real limit (`docs/explore-google-cloud-launch-checklist.md`).

## 2. Test listings (read-only, real stored MLS rows)

| # | ListingKey | Type | Sign | Price | MLS coordinate | Address (internal verification) | BidYourOffer page |
|---|---|---|---|---|---|---|---|
| 1 | `b138f872…19829` | Condominium | FOR SALE | $184,900 | 27.788945, −82.735144 | 6817 Stones Throw Cir N #17208, St Petersburg | seller listing 130 |
| 2 | `f238599a…9d9d9` | Condominium | FOR RENT | $1,950/mo | 27.790155, −82.735682 | 6931 Stones Throw Cir N #5208, St Petersburg (145 m from #1) | none resolved |
| 3 | `c1382a83…5ad` | Single-family | FOR RENT | $14,000/mo | 26.960258, −82.382920 | 6590 Manasota Key Rd, Englewood | none |
| 4 | `2f2ae217…bb87` | Single-family | FOR RENT | $14,000/mo | 26.959994, −82.382761 | 6580 Manasota Key Rd, Englewood (33 m from #3) | none |

Why these four:

- **#1 and #2 are the only real sale + rent pair within 600 m** in the stored data.
  - The local cache holds 16 active Residential sale rows.
  - 13 of them look like seeded demo rows (listing IDs such as `ORL-001`, no media, no
    display flags) and were excluded rather than risk using fabricated data.
- **#3 and #4 are the "which house?" test:** two adjacent single-family homes 33 m apart.
- **Row #2 has a landlord auction (78) carrying its MLS key**, but that auction is not resolved
  as a public canonical page. It is probably unapproved or a draft, which is the correct
  outcome.

LIVE: all four resolve through the proof's endpoint with exact coordinates and correct signs.
They cost 0 provider requests (§3).

## 3. What was built

Two development-only pages share one listing UI and one read-only endpoint:

| Route | Purpose |
|---|---|
| `GET /dev/virtual-drive/apple` | Look Around page (MapKit JS) |
| `GET /dev/virtual-drive/google` | Street View page (Maps JavaScript API) |
| `GET /dev/virtual-drive/api/listings?set=test` · `?lat=&lng=&radius=` | Stored listings, via Explore's read path |

- **Gate:** `VirtualDriveProofGate` checks the environment first: only local, development or
  testing, and production is refused explicitly. Then it requires
  `VIRTUAL_DRIVE_PROOF_ENABLED`, which parses fail-closed and defaults off. Every route 404s
  otherwise. Production cannot serve it even with the flag on, and a test pins that.
- **Listing endpoint:**
  - Reuses Explore's repository, policy, projection and canonical resolver.
  - Never touches `ExploreInventoryService`, so a camera move can never become a Bridge
    request.
  - Tests fail on any SQL statement that is not a read, and on any HTTP call.
  - A test key that is missing or ineligible is listed in `unavailable_keys`, never
    substituted.
- **Shared shell** (`public/js/virtual-drive/virtual-drive-shell.js`):
  - Listing card (sign, price, facts, address or a "withheld" note, status, photos).
  - Previous / Next, a nearby list, and a photo lightbox.
  - Action buttons built by `VirtualDriveListingActions` (§4).
  - A fallback state and an instrumentation log with counters.
- **The shell never branches on provider name, only on declared capabilities.** Each provider
  implements one contract: `load`, `mount`, `setListings`, `select`, `show`, plus
  `capabilities.{geoAnchoredMarkers, cameraState}`.
- **Movement → data:** moving the camera triggers at most one query to our own endpoint per
  roughly 240 m travelled, and never a Bridge request.
- **Isolation:**
  - One provider per page, and neither page includes MapLibre, `app.js` or the main layout.
  - Each page emits only its own credential, only when that credential is set, and never falls
    back to the Places key or Explore's key.
- **Why `public/js/…` and not `resources/js/…`:** these are static assets, the same decision
  Explore made. They have no imports, and `npm install` is blocked in this environment
  (`webpack.mix.js:62-67`), so a Mix entry could not be built or run here.

## 4. Listing actions (identical on both providers) — LIVE

| Action | Listing #1 (has a BidYourOffer page) | Listings #2–#4 (MLS-only) |
|---|---|---|
| Photos | ✓ (32) lightbox | ✓ (21 / 72 / 95) |
| Video | ✗ — the feed has no video field licensed for display | ✗ same |
| 3D Tour | ✓ unbranded tour | #2 ✗ (none) · #3, #4 ✓ |
| Details | ✓ seller listing page | ✗ for guests; ✓ `/stellar/property/{key}` when signed in |
| Ask a Question | ✓ the listing page's real question form | ✗ — no BidYourOffer page to host it |
| Save | ✗ — **no Save feature exists in the app** | ✗ same |
| Schedule Showing | ✓ the listing page's real showing form | ✗ — no BidYourOffer page |

Every unavailable action shows its reason on the card. A test proves each listing's action URLs
point at that listing and never at its neighbour.

## 5. Apple Look Around — results

- **Loads:**
  - NOT RUN, because there is no `VIRTUAL_DRIVE_MAPKIT_JS_TOKEN`.
  - LIB: `mapkit.core.js` 5.81.65 loads, and the `look-around` library provides `LookAround`
    and `LookAroundPreview`.
- **Coverage:**
  - NOT RUN for all four listings.
  - DOC: MapKit JS has **no way to check coverage before rendering**. There is no scene request
    class in JS (Apple forum thread 814098). Coverage is only discovered by constructing a
    `LookAround` and waiting for `load` or `error`.
- **Navigation:** DOC — `isNavigationEnabled`, `isScrollEnabled` and `isZoomEnabled` exist.
  Actual feel is NOT RUN.
- **Exact-property association — not achievable with documented APIs.**
  - DOC: `LookAround` documents no heading, pitch, position or field of view.
  - DOC: `LookAroundScene`'s only documented member is `copy()`.
  - DOC: there is no camera-moved event, no heading setter, and no annotation or overlay inside
    the view.
  - LIB: the shipped prototype's public members are the documented toggles plus `scene`,
    `readyState`, `element`, `padding`, `openDialog`, `destroy`, event methods, an opaque `_`
    and an undocumented `when`. **No camera member exists at all.**
- **The start can face the wrong house.** DOC (Apple DTS, forum thread 802465): *"A coordinate
  is simply a 2D point on Earth, and does not indicate a direction or heading."* In that report,
  Look Around opened at a house's coordinate and faced the house across the street. Apple's
  suggested remedy is a `Place`, so the proof has a "Place" start mode: one
  `Geocoder.reverseLookup` service call, logging the address Apple resolved next to the MLS
  address. Whether that fixes orientation is NOT RUN.
- **Marker / sign behaviour** — the four outcomes from the brief:
  - **A. Stays attached to the house:** No. It is impossible without camera state.
  - **B. Stays at a fixed screen location:** Yes, and it is the only option. The proof draws it
    that way and captions it "Screen-fixed overlay. It is not attached to the house."
  - **C. Repositioned from documented scene or camera information:** No. No such information is
    documented.
  - **D. Becomes misleading once the camera moves:** Yes. After any turn or step, the sign sits
    over whatever is in front of the camera. It can be misleading from the first frame if the
    starting heading faces the wrong side of the street.
  - A documented-only mitigation is included: the proof watches the `scene` reference and dims
    the sign when the view changes. That says *that* the view moved, never *where*. Whether the
    reference changes per step is NOT RUN.
- **Multiple properties:** only as a list beside the imagery, not in the imagery itself. There
  are no annotations.
- **Listing click behaviour:** clicking the screen-fixed sign or the card opens that listing's
  actions. LIVE for the card; the sign over imagery is NOT RUN.
- **Photos / Video / 3D / Ask / Save / Showing:** as in §4. LIVE, identical to Google.
- **Mobile:** the layout works at 390 px (LIVE, screenshot). Look Around touch behaviour is
  NOT RUN.
- **Other limitations:**
  - Moving to a new home means `destroy()` and a new `LookAround`, because JS has no scene
    request.
  - The Apple Developer Program membership and token requirement applies.
- **Undocumented hacks required:** **NO**. None are used; a test restricts the file to
  documented members. None would help anyway, since the camera is not exposed on the public
  surface.

### APPLE VERDICT: PARTIAL PASS at most — provisional; FAIL against the benchmark

- The ceiling is a selected-property card beside neighbourhood imagery.
- Even that is not yet earned: coverage and orientation still need a credentialed session.
- No credential can make it a STRONG PASS. The documented API gives the page no way to know
  which house is in view.

## 6. Google Street View — results

- **Loads:** NOT RUN, because there is no `VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY`.
- **Coverage:**
  - NOT RUN.
  - The proof uses `StreetViewService.getPanorama`: outdoor imagery only, nearest match,
    within 50 m and then 150 m.
  - It reports the panorama's distance from the MLS coordinate.
- **Navigation:** DOC — `linksControl`, `clickToGo`, and the `pano_changed` /
  `position_changed` / `pov_changed` events.
- **Exact-property association — supported by documented APIs.**
  - DOC: "the types of overlays which are supported on Street View panoramas are limited to
    Markers, InfoWindows and custom OverlayViews". A `Marker` with `map: panorama` sits at the
    listing's MLS coordinate.
  - The camera opens aimed at the home: `computeHeading(panorama location → home)`.
  - A "Face the selected home" control re-aims it.
  - A readout such as "Selected home: 38 m away, 20° to your right" is computed from
    `getPosition()` and `getPov()`. That is exactly the information Apple does not expose.
- **Marker / sign behaviour:**
  - Each sign is positioned by Google's projection of a latitude/longitude, and the proof
    never moves one.
  - NOT RUN: how convincing it looks. That covers scale with distance, how high the sign
    appears to float, and the fact that **markers are not hidden behind buildings**.
- **Multiple properties:**
  - One marker per known listing.
  - Walking more than about 240 m re-queries our stored data and adds markers.
  - Each marker's click carries only its own listing id.
- **Listing click behaviour:**
  - A marker click selects that listing and opens its card without moving the camera.
    Previous / Next moves the camera and re-aims it.
  - LIVE at the data level (tests); the JS click is NOT RUN.
- **Photos / Video / 3D / Ask / Save / Showing:** as in §4. LIVE, identical to Apple.
- **Mobile:** the layout works at 390 px (LIVE). Street View touch behaviour is NOT RUN.
- **Limitations:**
  - The legacy `google.maps.Marker` is the documented Street View overlay. It was deprecated in
    February 2024, but Google says it is "not scheduled to be discontinued", with at least 12
    months' notice promised. `AdvancedMarkerElement` is not listed as a Street View overlay.
  - Condo units that share one MLS coordinate would stack on a single sign; the product needs
    grouping.
  - Private roads, such as a condo complex's internal loop, may have no coverage.
  - The ToS forbids a MapLibre map on the same screen (§8).
- **Undocumented hacks required:** **NO**.

### GOOGLE VERDICT: STRONG PASS on documented capability — provisional

It needs one credentialed session to confirm the visual result and to measure coverage at the
four test homes.

## 7. Cost / usage

**Apple (DOC):**

- Free up to **250,000 map views and 25,000 service calls per day** per Apple Developer Program
  membership. Beyond that, the only option is to contact Apple.
- **How Look Around is counted is not documented.** Assume each `LookAround` construction is at
  least a map view.
- The proof constructs one per home viewed, because there is no scene request in JS. Place mode
  adds one Geocoder service call per home.
- A paid Apple Developer Program membership is required.

**Google (DOC):**

- **Dynamic Street View** is a Pro-tier SKU, `658E-F885-E11A`.
  - Billable event: "Successful panorama load".
  - Trigger: `google.maps.StreetViewPanorama()` or `Map.getStreetView()`. It is "charged for
    each instantiation of a panorama object".
  - **5,000 free per month**, then **$14.00 per 1,000**, falling to $1.05 per 1,000 with volume.
- **The proof instantiates one panorama per page session.** Moving between panoramas and
  between homes reuses it through `setPano()`. Confirm in the Cloud billing report that
  navigation adds nothing.
- Example: 20,000 Virtual Drive sessions a month ≈ (20,000 − 5,000) × $14 ÷ 1,000 ≈ **$210 a
  month** at the first-tier list price.
- **Dynamic Maps** ($7.00 per 1,000, 10,000 free) does not apply: the proof creates no Google map.
- **Metadata:** the Street View *Static API* metadata SKU is free. The JS
  `StreetViewService.getPanorama` lookup is not listed as a separate SKU in the table I
  retrieved; confirm in the billing report.
- **Requirements:** Maps JavaScript API enabled, a billing account attached, and a
  referrer- and API-restricted browser key.

**Stellar/Bridge:** 0 requests per camera move, by construction and by test. The proof reads
`bridge_properties` only. Keeping that table current stays the job of the existing sync and
discovery pipeline, never of a camera.

**Cheaper:** Apple, because its free tier is generous. But the saving buys an experience that
cannot show which house is for sale.

## 8. Licensing / terms

- **Google Maps Platform ToS §3.2.3(e), "No Use With Non-Google Maps" (DOC, verbatim):**
  *"Customer will not use the Google Maps Core Services with or near a non-Google Map in a
  Customer Application. For example, Customer will not … (ii) display Street View imagery and
  non-Google Maps on the same screen."*
  - Street View and MapLibre must be separate screens, and the fallback must be a separate
    screen too. The proof complies.
  - Whether a MapLibre search screen that links to a separate Street View screen counts as
    "near" is **a question for counsel**, not for this audit.
- **Google ToS §3.2.3(a) and (b), no scraping and no caching:** the proof stores nothing from
  Google, including pano IDs and imagery.
- **Overlays:** Markers are a documented, supported overlay. The proof keeps its own overlays
  off the bottom edge, where Google's logo and terms links are drawn.
- **Apple MapKit JS (from Apple's web page and a search summary, not the agreement text itself):**
  - Apple map data may only be shown on an Apple map.
  - No bulk extraction or scraping.
  - Do not obscure Apple's notices.
  - Whether Look Around on a page that also shows a MapLibre map is permitted is not settled by
    anything retrieved. **Counsel should read the agreement.**
- **MLS / IDX:**
  - The signs show only Explore-projection fields: IDX-eligible, `Active` only, address
    suppression honoured, Stellar attribution kept.
  - Nothing in the repo records whether Stellar's IDX rules allow listing signs over
    third-party street imagery. **Confirm with Stellar before any public release.** This is the
    same posture Explore took.

## 9. Side-by-side

| Requirement | Apple Look Around | Google Street View |
|---|---|---|
| Embedded street-level imagery | Yes (DOC/LIB); not seen here | Yes (DOC); not seen here |
| Virtual navigation | Yes (DOC) | Yes (DOC) |
| Open at MLS coordinates | Yes, but the heading is Apple's choice and may face the wrong house | Yes, and the camera is aimed at the house |
| Geographic listing marker | **No** — no annotations in Look Around | **Yes** — Marker at the MLS LatLng |
| Marker stays with house | **No** — screen-fixed only | **Yes** — Google projects it (visual NOT RUN) |
| Multiple listing markers | No (list beside the imagery only) | Yes |
| Clickable listing marker | Only the screen-fixed sign | Yes, each carrying its own listing id |
| Photos integration | Yes (LIVE) | Yes (LIVE) |
| Video integration | No — no video in the feed | No — no video in the feed |
| 3D tour integration | Yes, where the listing has one (LIVE) | Same (LIVE) |
| Ask Question integration | Yes, via the BidYourOffer page (LIVE) | Same (LIVE) |
| Save integration | No — the feature does not exist | Same |
| Schedule Showing | Yes, via the BidYourOffer page (LIVE) | Same (LIVE) |
| Desktop support | Layout LIVE; imagery NOT RUN | Layout LIVE; imagery NOT RUN |
| Mobile support | Layout LIVE; touch NOT RUN | Layout LIVE; touch NOT RUN |
| Coverage observed | NOT RUN; no pre-check API | NOT RUN; `getPanorama` pre-check |
| API complexity | Low, but nothing to build the core feature on | Moderate; everything needed is documented |
| Expected usage / cost model | Free tier 250k views + 25k calls/day; Look Around accounting undocumented | $14 per 1,000 panorama objects after 5k/month; one per session |
| Licensing concerns | Agreement not reviewed; the MapLibre adjacency question is open | §3.2.3(e): no MapLibre on the same screen; "near" needs counsel |
| Unsupported hacks required | No (and none would work) | No |
| Overall fit | **Poor** for Virtual Drive | **Strong** |

## 10. The eight questions

1. **Can Apple place a FOR SALE / FOR RENT indicator in front of the actual property?** Not in the
   imagery. It can only put a screen-fixed sign over whatever the camera faces, and the starting
   heading is not ours to set.
2. **Does it stay associated while the user navigates?** No. With no camera state, nothing can
   re-associate it after the first turn or step.
3. **Can Google do this more reliably?** Yes. A marker at the house's coordinate is a documented
   Street View overlay, and Google exposes the camera's position and heading.
4. **Can either provider display several nearby MLS listings during virtual driving?** Google
   yes, one marker each. Apple only as a list beside the imagery.
5. **Can clicking a listing open our existing BidYourOffer listing actions?** Yes, on both. It
   uses the same card, and real Details / Ask / Showing go into the existing listing page where
   one exists. Save does not exist, and video is not in the feed.
6. **Which creates the better "virtual house shopping" experience?** Google. It is the only one
   where the shopper can see which house is for sale.
7. **Which is cheaper, based on the APIs actually required?** Apple, on paper, thanks to its free
   tier. Google is about $14 per 1,000 sessions after 5,000 free per month, with one panorama
   object per session.
8. **Is a hybrid architecture preferable?** Not a hybrid that includes Apple. Apple adds no
   capability Google lacks here. What the test supports is **MapLibre for map and search, with
   Google Street View on its own screen for Virtual Drive**. Revisit Apple if MapKit JS ever
   documents a camera or annotation API for Look Around.

## 11. Recommended provider and production architecture

**Recommended:** Google Street View for Virtual Drive, with MapLibre kept for the ordinary map
and search. Apple is not used for Virtual Drive.

```
bridge_properties  (kept current by the EXISTING MLS sync / discovery — never by a camera)
   └─ Explore read path: repository → eligibility policy → projection allow-list → canonical resolver
        └─ nearby-listings endpoint (read-only; queried per ~250 m travelled, not per camera event)

Screen A  MapLibre map / search            — no Google content on this screen
   └─ "Drive this street" ──────────────►  Screen B
Screen B  Google Street View, full screen  — no MapLibre on this screen
   one StreetViewPanorama per session · one Marker per nearby listing at its MLS coordinate
   marker click → our listing card → existing BidYourOffer page (Details / Ask / Showing), photos, tour
Fallback  no coverage · key off · quota hit → back to Screen A; listing access never depends on imagery
```

Before building it for users:

- **Operations:**
  - A dedicated browser key, restricted by referrer and to the Maps JavaScript API only.
  - Per-day quotas on Dynamic Street View; those are the real limit, and a budget is only an
    alarm.
  - A fail-closed kill switch like `EXPLORE_GOOGLE_3D_ENABLED`.
- **Product:**
  - Re-check the `Marker` deprecation status.
  - Group signs for multi-unit buildings.
- **Sign-off:** counsel on "with or near", and Stellar on IDX signs over street imagery.
- **Fixes:** the address bug in §12.

## 12. Findings outside this proof's scope (not changed)

1. **Explore duplicates the unit number in addresses.** LIVE: "6817 STONES THROW CIRCLE N UNIT
   17208 **#17208**". `ExploreListingProjector::addressLine()` appends `UnitNumber` even when
   `UnparsedAddress` already contains it, which affects `/explore` too.
2. **Condo units share one MLS coordinate.** For example, 20+ Siesta Bayside Drive leases sit at
   an identical point in stored data, so any street-level sign must group them.
3. **The local MLS cache is thin for sales:** 3 real active Residential sale rows, plus 13
   seeded demo rows.
4. **origin/main moved during the audit** from `910a79ecb` to `bbbe097dd`. This branch was not
   rebased.

## 13. Running the credentialed session (a handful of requests, not traffic)

1. **Credentials:**
   - **Apple — `VIRTUAL_DRIVE_MAPKIT_JS_TOKEN`:** a MapKit JS token from an Apple Developer
     Program account, restricted to the development origin.
     - For production, prefer a short-lived JWT signed on the server from a Team ID, a Key ID
       and a MapKit private key (`.p8`). Never commit the `.p8`.
   - **Google — `VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY`:** a new key in a project with billing
     attached.
     - Application restriction: HTTP referrers, the development origin only.
     - API restriction: Maps JavaScript API only.
     - Set a low daily quota on the Maps JavaScript API for the test.
   - Neither is committed. Both are read from the environment.
2. **Run:**

   ```
   APP_ENV=local VIRTUAL_DRIVE_PROOF_ENABLED=true \
   VIRTUAL_DRIVE_MAPKIT_JS_TOKEN=… VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY=… \
   php artisan serve
   ```

   Then open `/dev/virtual-drive/apple` and `/dev/virtual-drive/google`. The instrumentation
   panel counts every provider object, service call and listing request.
3. **For each of the four homes and each provider, record:**
   - coverage yes/no
   - where the camera starts relative to the house, and whether it faces the right one
   - the sign's behaviour when you turn left and right, turn 180°, step forward and back, move
     to another panorama, and travel about 200 m down the street
   - whether each marker click opens the right card
   - whether #3 and #4 (33 m apart) stay distinguishable
   - mobile touch behaviour
   - for Apple, run both start modes (coordinate and Place) and compare the resolved address
     with the MLS address
4. **Afterwards, check the Google Cloud billing report** to confirm the per-instantiation
   charging.

## Files

- **Created:**
  - `config/virtual_drive.php`
  - `app/Support/VirtualDrive/VirtualDriveProofGate.php`
  - `app/Support/VirtualDrive/VirtualDriveListingActions.php`
  - `app/Http/Middleware/CheckVirtualDriveProofEnabled.php`
  - `app/Http/Controllers/Dev/VirtualDriveProofController.php`
  - `app/Http/Controllers/Dev/VirtualDriveListingController.php`
  - `resources/views/dev/virtual-drive/show.blade.php`
  - `public/js/virtual-drive/virtual-drive-shell.js`
  - `public/js/virtual-drive/apple-lookaround-provider.js`
  - `public/js/virtual-drive/google-streetview-provider.js`
  - `public/css/virtual-drive/virtual-drive.css`
  - four test files under `tests/Feature/VirtualDrive/` and `tests/Unit/VirtualDrive/`
  - this document
- **Modified:**
  - `app/Http/Kernel.php`: one middleware alias.
  - `routes/web.php`: one gated route group. The routes fall under the catalog's existing
    `dev/*` INFRASTRUCTURE pattern.
- **Untouched:** MapLibre, Google Maps code, Location DNA, MLS import and sync, Explore.

## Sources

- Apple — LookAround: <https://developer.apple.com/documentation/mapkitjs/lookaround>
- Apple — LookAroundScene: <https://developer.apple.com/documentation/mapkitjs/lookaroundscene>
- Apple — CommonLookAroundOptions: <https://developer.apple.com/documentation/mapkitjs/commonlookaroundoptions>
- Apple — loading MapKit JS: <https://developer.apple.com/documentation/mapkitjs/loading-the-latest-version-of-mapkit-js>
- Apple — Geocoder.reverseLookup: <https://developer.apple.com/documentation/mapkitjs/geocoder/reverselookup>
- Apple — usage limits: <https://developer.apple.com/maps/web/>
- Apple forums — heading: <https://developer.apple.com/forums/thread/802465>
- Apple forums — no scene request: <https://developer.apple.com/forums/thread/814098>
- Google — Street View guide: <https://developers.google.com/maps/documentation/javascript/streetview>
- Google — Street View overlays sample: <https://developers.google.com/maps/documentation/javascript/examples/streetview-overlays>
- Google — Marker deprecation: <https://developers.google.com/maps/deprecations>
- Google — SKU details: <https://developers.google.com/maps/billing-and-pricing/sku-details>
- Google — pricing: <https://developers.google.com/maps/billing-and-pricing/pricing>
- Google — Maps Platform Terms: <https://cloud.google.com/maps-platform/terms>
- Google — Service Specific Terms: <https://cloud.google.com/maps-platform/terms/maps-service-terms>
