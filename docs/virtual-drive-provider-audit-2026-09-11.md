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

> **Superseded in part by §16.** A credentialed session has since been run: the loads, the
> geo-anchoring and the marker scaling below are now LIVE, and the condo-stacking limitation is
> fixed. The NOT RUN labels in this section describe the state before that session.

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

## 14. Phase 2 — guarded comparison harness (live run blocked on credentials)

> **Superseded in part by §16.** The Google half of this is no longer blocked — a key exists and one
> session has run. Apple is still blocked on its token. The procedure below still governs any
> further session.

**Status:**

- The comparison harness and its Google billing guard are built and proven against fakes.
- **The live, credentialed session has not run.** Neither `VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY`
  nor `VIRTUAL_DRIVE_MAPKIT_JS_TOKEN` exists in this environment, and creating them needs a
  Google Cloud project and an Apple Developer account.
- **This audit has made no request to Google or Apple.** The only exception is the one-time,
  credential-free download of Apple's public MapKit JS library for §5 (no `init`, no map view).

### What changed

- **Comparison page — `/dev/virtual-drive`:**
  - Lists the same stored homes with **Test Apple** / **Test Google** links.
  - Includes no provider script and emits no credential (a test asserts both).
  - A link only preselects a home (`?listing=`); it never launches anything.
- **A launch button on each provider page is the billing boundary:**
  - Before the press: the listing card, photos, tour, nearby list and Previous / Next all work,
    and the provider library is not even requested.
  - `launch()` is the only caller of `provider.load()`, and nothing calls `launch()` except that
    button (a source test pins it).
  - The button locks on the first press: `loading → idle → opening → open`, with branches to
    `retry` (no imagery; press again) or `locked`.
  - Nothing retries by itself. A library that failed to load is never requested again, and a
    rejected key (`gm_authFailure`, or MapKit `Unauthorized`) locks the page.
  - The URL carries the selected home but never a launch, so a reload cannot start a session.
- **Google has a hard ceiling of one panorama:** `MAX_PANORAMAS_PER_PAGE = 1`.
  - `constructPanorama()` is the single construction site. It **refuses** a second construction
    and stops the page with a STOP message; it never performs one.
  - Every home change, rotation, walk, marker click and card action reuses the one panorama
    through `setPano()` / `setPov()`.
  - `window.VirtualDriveDiagnostics.google` exposes the counts: library requests,
    `importLibrary` calls, constructions, refusals, lookups and `setPano` moves.
- **Providers adopt an API already on the page instead of loading a second one.** Explore's
  loader has the same rule, and it is what lets the specs substitute fakes without a test-only
  switch in the code under test.
- **A fifth real test home for the condo problem:** 1226 Siesta Bayside Dr #1226-C, FOR RENT,
  $9,300/mo.
  - In stored data, 31 active units share one identical coordinate and 5 more sit about 1 m away.
  - LIVE: the nearby query around it returns **12 listings on only 2 distinct points** (7 + 5),
    at the proof's 12-result cap. None of the original four shares a coordinate.
- **Observation sheet on each provider page:**
  - The same checklist on both providers, per home.
  - Measured values fill in automatically: coverage, panorama distance from the MLS coordinate,
    Google `imageDate`, time to imagery, and the Apple Place resolved in Place mode.
  - Stored in the browser only. **Copy results** produces one Markdown export covering both
    providers.
- **Credentials can't reach tests:** both are blanked, and the proof switched off, in `phpunit.xml`
  (`force="true"`) and in `tests/bootstrap.php` across `getenv()`, `$_SERVER` and `$_ENV`.
  `VirtualDriveCredentialTestEnvGuardTest` asserts it.
- **CI:** `browser-tests.yml` now also runs on changes to `public/js/virtual-drive/**`.

### Guard verification — real shell and provider scripts, fake APIs, network aborted

The fakes count their own constructor calls and the provider keeps separate counters; every spec
checks both. `installNetworkGuard` aborts off-origin requests, and every spec asserts that no
Google or Apple request was attempted.

| Scenario | Required | Observed |
|---|---|---|
| Open the page, browse homes, open cards, photos and tours, hover the button | 0 panoramas, 0 library requests | 0 / 0 ✓ |
| Double-click plus 10 direct `launch()` calls while opening and after | 1 panorama, 1 launch | 1 / 1 ✓ |
| Rotate 90°+90°+180°, walk forward and back, travel ~200 m, 6 home changes, marker clicks, photos, tour, "Face the home", a URL change | still 1 | 1, with ≥ 5 `setPano` moves ✓ |
| Each marker sits at its listing's MLS coordinate and opens only that listing | all | all 7 ✓ — the 33 m neighbours are 2 points; the 3 condo units stack on 1 |
| No coverage | no automatic retry | 2 lookups, then nothing for 1.5 s; a deliberate press → 2 more, 0 panoramas ✓ |
| Rejected key | page locks, no retry | locked, 0 panoramas ✓ |
| First construction throws, then a deliberate retry | second construction refused | refused, STOP logged, constructor called once ✓ |
| Reload after a session | no launch | 0 ✓ |
| Apple: before the press, on launch, on the next home | 0, then 1 `init` + 1 Look Around, then destroy + 1 new | as required; sign captioned "Screen-fixed" ✓ |

### Google APIs the proof actually requires

- **Maps JavaScript API — the only API to enable, and the only one the key should allow.**
  - The proof uses its `streetView`, `marker`, `geometry` and `core` libraries, all part of that
    API.
  - Expected billable SKU: **Dynamic Street View**, one per panorama object.
  - `StreetViewService.getPanorama` lookups are metadata. They are not listed as a separate
    Maps JavaScript API SKU in the table I retrieved; confirm this in the billing report.
- **Not used:** Places, Geocoding, Map Tiles, Street View Static, Routes, and Dynamic Maps (no
  `google.maps.Map` is created).

### Running the one live session

1. **Credentials (you):**
   - A **new** Google browser key, restricted to the dev origin that serves the proof (HTTP
     referrer) and to the **Maps JavaScript API** only, with a low daily quota on that API.
   - A MapKit JS token restricted to the same origin.
   - Add both as Replit Secrets named `VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY` and
     `VIRTUAL_DRIVE_MAPKIT_JS_TOKEN`. Don't change any production key.
   - Test runs blank both automatically.
2. **Billing baseline:** before launching, note the Maps JavaScript API request count and the
   Billing report for SKU *Dynamic Street View* (Cloud console), with the time.
3. **Serve:** from the worktree, run `bash scripts/dev/virtual-drive-proof-serve.sh`.
   - It uses a read-only database session and never prints the credentials.
   - Open `/dev/virtual-drive` on that dev origin.
4. **Google — press "Drive with Google" once:**
   - Do the whole checklist inside that one session: all five homes via Next / Previous,
     markers, rotation, walking, ~200 m of travel, the condo stack.
   - **The Instrumentation panel must read "StreetViewPanorama constructions: 1" throughout.**
   - If it reads 2, or the log shows STOP, stop and report.
   - Don't reload and press again. A reload doesn't launch, but pressing launch on the reloaded
     page starts a new, separately billed session — the ceiling is per page load.
5. **Apple:** the same checklist, in both start modes (MLS coordinate, then Apple Place).
6. **Wrap up:**
   - Press **Copy results**, paste the export back, and stop the server.
   - Re-check the billing report once it has caught up; that can take hours. Expected: 1 Dynamic
     Street View load for the one launch.

## 15. Apple re-audit against MapKit JS 6 (supersedes the 5.81.65 version references in §5)

Apple's current Look Around sample loads `https://cdn.apple-mapkit.com/mk/6/mapkit.core.js` with
`data-libraries="services,look-around"`. This section re-checks every Apple conclusion against
**MapKit JS 6**.

### Sources, all Apple's own

1. **Official type definitions:** `https://cdn.apple-mapkit.com/mk/6/types/mapkit.d.ts`
   (Last-Modified 30 Jul 2026, 9,109 lines).
2. **The documentation JSON behind developer.apple.com/documentation/mapkitjs,** parsed directly.
3. **The shipped library, 6.0.128,** loaded with no token and never `init`-ed; public class members
   listed.
4. **Apple's migration guide:** *Migrating from Version 5 to Version 6*.

### Findings

| # | Question | MapKit JS 6 evidence | Answer |
|---|---|---|---|
| a | Can the stored MLS coordinate go straight into Look Around? | `constructor(parent?: HTMLElement, location?: CoordinateData \| Place \| LookAroundScene, options?: LookAroundOptions)` (d.ts, and the `lookaroundconstructor` doc). `interface CoordinateData` is "a plain object representation of a coordinate" with `latitude` and `longitude`, introduced in **MapKit JS 6.0**. | **Yes.** No PlaceLookup needed. |
| b | Does Look Around expose its current scene? | `get scene(): LookAroundScene \| null; set scene(value: LookAroundScene);` on `AbstractLookAround`. | **Yes, as an object,** but see (c). |
| c | Does `LookAroundScene` expose a coordinate, camera position, heading, pitch, orientation or field of view? | `export class LookAroundScene { #private; copy(): LookAroundScene; }`, documented since 5.79.0 and unchanged in 6. The shipped 6.0.128 class carries only `constructor`, `copy` and an opaque private `_`. | **No.** |
| d | Any movement or navigation event? | `AbstractLookAround extends EventTarget` and declares no events. The v6 LookAround doc lists only `LookAroundErrorEvent` (`CustomEvent<{ type: LookAroundErrorType; message: string }>`) and `LookAroundErrorType` (`availability-error`, `browser-error`, `service-error`, `unknown-error`). Doc pages for `load` / `readystatechange` return 404. State is `readyState`: `loading \| complete \| error \| destroyed`. | **No.** Loading and error state only. |
| e | Any heading, pitch, camera or field-of-view member anywhere on Look Around? | `AbstractLookAround` declares exactly `element`, `scene`, `openDialog`, `readyState`, `isNavigationEnabled`, `isZoomEnabled`, `isScrollEnabled`, `showsRoadLabels`, `showsPointsOfInterest`, `padding`, `destroy()`. Every "camera" in the d.ts is a Map zoom limit (`CameraZoomRange`, `CameraBoundaryDescription`). | **No.** |
| f | Can we add our own annotations or markers inside Look Around? | `Annotation`, `MarkerAnnotation` and `ImageAnnotation` take `location: CoordinateData \| Place \| SearchAutocompleteResult` but are added to a Map. `addAnnotation(s)` / `showItems` are declared only on `class Map`, and `AbstractLookAround` declares no annotation member. `showsPointsOfInterest` toggles **Apple's own** POIs; it is a boolean, not a way to add ours. | **No.** |
| g | Can a coordinate be projected into the Look Around viewport? | The only coordinate-to-screen method, `convertCoordinateToPointOnPage`, is declared on `class Map` alone. | **No.** |
| h | Did v6 change any of this? | The migration guide never mentions Look Around. Its changes are native `EventTarget`, `null` instead of `undefined`, object literals as data types (which is what enables `CoordinateData`), CORS for images, and async service APIs. The shipped LookAround public members are identical in 5.81.65 and 6.0.128. | **No.** |

### What only a live token can show

These gaps are real, but none can turn Apple into a working fit.

- **Whether a given home has Look Around coverage,** and where the camera starts relative to it.
  A coordinate carries no heading, and Apple's DTS confirms the view can face the wrong house.
- **Whether the `scene` object's identity changes as the user moves.** Even if it does, the class
  documents nothing readable. It would say *that* the view moved, never *where* it is or what it
  faces.
- **Imagery quality, navigation feel, load time and mobile behaviour.**

### Proof changes

- The Apple library is now `mk/6`.
- The default start passes `{ latitude, longitude }` from the stored Stellar row directly to
  `new mapkit.LookAround(...)`. It makes **no service call**, which the browser spec asserts: the
  fake records a plain object with exactly those two keys, and 0 PlaceLookup / 0 Geocoder calls.
- Place mode remains strictly opt-in and off by default. Its one reason is Apple DTS naming a
  Place as the way to face the right house; it costs one Geocoder call per home. PlaceLookup is
  not used at all.
- Readiness comes from the documented `readyState` getter and the documented `error` event,
  instead of a `load` event v6 no longer names.

### Updated Apple verdict (MapKit JS 6)

| Capability | Answer | Evidence |
|---|---|---|
| Can embed interactive Look Around | **YES** (documented; not yet seen live) | `class LookAround extends AbstractLookAround`; `isNavigationEnabled` / `isScrollEnabled` / `isZoomEnabled` |
| Can initialize directly from the MLS coordinate | **YES** | `location?: CoordinateData \| Place \| LookAroundScene`; `CoordinateData` = `{ latitude, longitude }` (6.0) |
| Can read current navigation / camera state | **NO** | `LookAroundScene` = `copy()` only; no heading, pitch, position, FOV or navigation event on `AbstractLookAround` |
| Can add custom geographic MLS markers inside Look Around | **NO** | annotations attach to `Map`; no annotation member on `AbstractLookAround` |
| Can keep a FOR SALE / FOR RENT marker attached to the correct house while driving | **NO** | needs (c), (f) or (g); MapKit JS 6 provides none of them |

**Apple overall: PARTIAL PASS at most, and FAIL against the benchmark. Unchanged by MapKit JS 6.**
Apple can show a neighbourhood from the MLS coordinate with no service call, next to a
selected-home card. It cannot tell the page which house is in view.

## 16. The one live Google session, and the customer experience it failed

**This section supersedes every "Google: NOT RUN" in §6, §9 and §14, and closes the condo-stacking
limitation listed in §6 and §12.2.** Apple is unchanged: `VIRTUAL_DRIVE_MAPKIT_JS_TOKEN` is still
absent and no Look Around session has run.

**Status:** one credentialed Google Street View session was run on 2026-09-11 against the real
stored homes. `VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY` is present in this workspace as a secret (it
is not in `.env` and is blanked in every test run — §14). **No further live session has been run
since, and none may be started without the owner's explicit authorisation.**

### What the session proved — the mechanics

- **The billing ceiling held.** One `StreetViewPanorama` for the whole session, through home
  changes, rotation, walking and travel. `setPano()` moved it; nothing rebuilt it.
- **Markers are genuinely geo-anchored.** Google placed each sign at its listing's MLS coordinate
  and kept it on the house as the camera turned and moved, exactly as §6 predicted from the
  documentation. Nothing in this proof repositions a sign.
- **Marker scaling with distance was measured**, and it is the measurement the sign sizing now
  rests on: a 168 px icon rendered **165 px at 19 m, 91 at 36 m, 67 at 49 m and 25 at 132 m** in a
  960 px-wide panorama — `width ≈ 168 × 19.4 m ÷ distance`. The 19.4 m constant is **measured, not
  documented** (`virtual-drive-signs.js`).

### What the session failed — the experience

Five defects, all found live, none of them visible to the fake-API specs as they then stood:

1. **Signs became unreadable dots.** At 132 m a sign rendered 25 px wide. Stones Throw's nearest
   imagery is 132 m from the home, so the default first view was the worst case.
2. **Neighbouring homes carried identical wording.** The two Manasota Key houses, 33 m apart, were
   distinguishable only by a selection outline — nothing on the sign said *which house*.
3. **Condo units stacked.** Three units sharing one coordinate drew three signs on one point; two
   were unreachable underneath the third. §12.2 recorded this as a known data fact; it is now
   handled rather than merely stated.
4. **A sign click led to a developer panel, not a listing.** There was no shopper-facing card.
5. **The HUD and "Face the selected home" were invisible and unclickable.** Google's panorama sets
   large z-indexes on its internal layers, and `#vd-street` created no stacking context, so those
   layers painted over every later sibling. The fake Maps API draws no DOM, which is why no
   existing spec could see it.

### What was changed in response

All of it is proven against the fake Maps API with the network aborted — **no further Street View
session was spent on any of this**.

| Defect | Fix | Proven by |
|---|---|---|
| Unreadable dots | `VirtualDriveSigns` re-chooses each sign's documented `icon.scaledSize` as the camera moves, so the size a shopper *sees* stays between 132 px and 200 px. The geographic anchor is untouched — Google still places and moves the sign. Beyond 160 m a sign is **hidden, not shrunk**; inside 6 m it is hidden too. | `virtual-drive-signs.spec.js` — the size model is checked against the four measured live widths (within 12%) |
| Identical neighbours | Each sign carries its own **house number**, its FOR SALE / FOR RENT label and its price. A withheld address yields no number and none is invented. | same spec: `['6590','FOR RENT','$14,000/mo']` vs `['6580', …]` |
| Stacked condos | Listings within 8 m become **one building sign** ("FOR RENT · 3 UNITS") that opens a **unit chooser**; picking a unit opens that unit and a back link returns to the building. | same spec, and the launch-guard spec's marker-identity test |
| Developer panel on click | A **shopper card** over the imagery: photos with paging, price, beds/baths/sq ft, address, and only the actions that exist — Photos, 3D Tour, Details, Ask a Question, Schedule Showing. It is pure DOM: opening it, switching listings, paging photos and choosing a unit **never touch the provider**, so the panorama is never rebuilt. Video and Save remain absent for the reasons in §4. | same spec, asserting `panoramaConstructorCalls === 1` and `setPano === 0` across every card interaction |
| Overlays painted over | `#vd-street` is given `z-index: 0` so it becomes a stacking context, and our overlays sit at `z-index: 1` above it. | `virtual-drive-overlay-stacking.spec.js`, which injects a full-cover layer at `z-index: 1000000` and hit-tests each overlay |

Three further changes came out of the same session:

- **Coverage is reported honestly.** Imagery more than 60 m from the home now says so on the status
  line and on the card — *"Street View is available nearby, but not directly at this property… You
  are not in front of the home."* Microscopic signs at the edge of a distant panorama are no longer
  presented as a view of the house.
- **The default home moved to Manasota Key Road** (`VIRTUAL_DRIVE_DEFAULT_LISTING_KEY`). Imagery
  there is ~35 m from the home with a neighbour 33 m away — a sign-shopping test. Stones Throw's
  132 m is a coverage limit, correctly reported as one, but a poor first view.
- **A customer preview** (`?view=customer`) hides the instrumentation, event log, observation sheet
  and developer card so the imagery, the signs and the shopper card are what a reviewer judges. The
  counters keep running underneath; the Stellar attribution, the homes walk and the aim control stay
  visible. It changes **what is shown, never what is loaded or when**.

### Standing prerequisite for the next live session — NORMAL GOOGLE IMAGERY

**The next manual test must not be started until the Google project renders ordinary, unwatermarked
Street View imagery.** A key whose project has no billing account attached, or whose Maps JavaScript
API is not enabled or is restricted away, still loads and still constructs a panorama — Google
serves darkened, *"For development purposes only"* tiles instead of refusing. That state is
**invisible to the Maps JavaScript API**: it raises no error, fires no event and sets no property,
so neither this proof nor any code can detect it, work around it or report it. It is a Google Cloud
configuration matter only.

Judging sign legibility, sign size or the shopper experience against watermarked imagery would
produce conclusions about a rendering mode no customer will ever see, and would spend a billable
panorama to do it.

**This has not been cleared from inside this repository, and cannot be.** Confirm in the Cloud
console before the next session: billing account attached, Maps JavaScript API enabled, and the
browser key restricted to that API and to the dev origin — then confirm on screen that the first
panorama is clean. Everything else in §14's live-session procedure still applies, including the
"constructions: 1" check throughout.

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
  - `public/js/virtual-drive/virtual-drive-signs.js` (§16 — pure sign rules: wording, sizing, grouping)
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
