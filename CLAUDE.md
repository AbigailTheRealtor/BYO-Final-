# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Asset compilation (Laravel Mix / Webpack — NOT Vite, despite vite.config.js existing)
npm run dev          # one-shot dev build
npm run watch        # watch mode (use in Replit)
npm run production   # minified production build

# Tests — SQLite in-memory, NOT PostgreSQL
php artisan test                                              # all suites
php artisan test tests/Unit/BidMatchScoreHelperAuditTest.php  # single file
php artisan test --filter=CompatibilityScoreServiceTest       # by class name

# Migrations
php artisan migrate:status    # check what has run before touching anything
php artisan migrate --pretend # dry-run SQL before executing
php artisan migrate           # run pending

# Key artisan commands
# The command is location-dna:generate, NOT ldna:generate — this line said the latter
# for a long time and no such command exists.
php artisan location-dna:generate {seller|landlord|bridge} {listing_id}   # one listing, one run
php artisan location-dna:generate bridge {id} --canary --dry-run          # canary posture, writes nothing
php artisan ldna:refresh-all             # re-run pipeline for all listings
php artisan ldna:audit-listing {id}      # inspect pipeline state for a listing
```

## Architecture

### Role symmetry (Seller / Buyer / Landlord / Tenant)

Almost everything in this codebase is quadruplicated by role. Each role has its own controller, Livewire component, model, bid model, meta model, routes, and Blade views. When fixing a bug or adding a field, check whether all four role variants need the same change.

**Schema asymmetry**: `seller_agent_auctions` and `buyer_agent_auctions` store data in **native columns**. `landlord_agent_auctions` and `tenant_agent_auctions` store data via **EAV meta** (`meta_key` / `meta_value` in `*_metas` tables). This is not an accident — it was architectural and must be respected.

### EAV meta pattern

Many extended fields are stored as key-value rows in `*_metas` tables (e.g. `buyer_agent_auction_metas`, `agent_service_auction_metas`). The meta model is a plain Eloquent model with `meta_key` and `meta_value` columns. Livewire components read/write these via `saveMeta()` / `getMeta()` calls rather than native Eloquent attributes.

### Livewire bid wizards

Bid forms are multi-tab Livewire components located in `app/Http/Livewire/` subdirected by role (`Buyer/`, `Seller/`, `Landlord/`, `Tenant/`) and by flow (`HireBuyerAgent/`, `HireSellerAgent/`, `HireLandLordAgent/`, `OfferListing/`). Tab navigation uses a **delegation pattern** — tabs emit events to the parent component rather than navigating directly. Validation runs twice: partial on "Save Draft" and full on "Save Edit / Submit".

`HasListingLifecycle` (`app/Http/Livewire/Concerns/HasListingLifecycle.php`) is the shared trait for listing state (`isDraft`, `isApproved`, `isSold`, `listing_status`, flash helpers). **`TenantAgentAuction` does not use this trait** — it predates it and is too large to refactor safely.

### Agent default profiles & auto-bid

`AgentDefaultProfile` stores a `profile_data` JSON blob per agent per role. `AgentBidMapperService::mapFromProfile()` is a pure, side-effect-free transformer from that blob to the normalized bid-field array consumed by all four role bid components. The "Hire Me" direct-entry flow calls this mapper to auto-populate a bid when a client hires an agent.

### Match scoring

`*BidMatchScoreHelper` classes (one per role, in `app/Helpers/`) compare listing criteria against bid fields. Dimension weights and activation flags live in `config/match_scoring.php` — **all enabled weights must sum to 100**. The helpers read config; scoring logic never lives in config.

### Location DNA pipeline

`LocationDnaPipelineRunner` (in `app/Services/LocationDna/`) orchestrates async enrichment for a property: POI lookup (Google Places via `GooglePlacesPoiAdapter`), flood zone (FEMA API), school districts (Census TIGER), and commute times. Results are cached via `LocationDnaPoiTileCache`. The pipeline runs as a queued job (`app/Jobs/ComputeLocationDna.php`). FEMA bounding-box size limits are configured in `config/location_dna.php`.

### Location DNA map rendering — Google today, MapLibre behind two gates

**There are two renderers and exactly one is live per surface.** The incumbent is Google
Maps, in `partials/location-dna/map-input.blade.php` (the eight Buyer/Tenant Search Areas
create/edit surfaces) and `components/location-dna-map.blade.php` (the read-only detail
surface for all four roles). The replacement is the MapLibre + PMTiles renderer in
`resources/js/spatial/`, which Phase 1 merged **wired to nothing** — `config/spatial_basemap.php`
had no PHP reader at all, so its two flags governed nothing and no Blade template emitted it.

`App\Support\Spatial\LdnaBasemapSurface` is that reader, and it is the **only** one — a test
asserts it. `enabledFor($surface)` is the only gate; `enabled()` answers the master switch
alone and nothing may render from it. It fails closed three ways: an absent config reads as
off, an unrecognised surface key can never be enabled however it is spelled in the
environment, and **both** `LOCATION_DNA_MAPLIBRE_ENABLED` and `LOCATION_DNA_MAPLIBRE_SURFACES`
must agree. Recognised surfaces are `hire_buyer`, `hire_tenant`, `create_buyer`,
`create_tenant`, `buyer_criteria`, `tenant_criteria`, `display`.

**The markup and the serialiser read one variable, and that is a data-safety property, not
tidiness.** `map-input` computes `$ldnaUseMaplibre` once; the panel branches on it and so does
`ldnaSerialize()`. A panel rendering MapLibre while the serialiser still rebuilt geometry from
Google's `ldnaOverlays` would write `"polygons":[]` over stored shapes on the next save. Under
MapLibre the authority flag is the renderer's own `isHydrated()` — the exact counterpart of
`ldnaOverlaysAuthoritative`, and a missing or unhydrated renderer lands in the same safe branch,
leaving the server-seeded values untouched.

**The Google poll is bounded now, and it was not.** `ldnaTryInit` re-armed itself every 200 ms
forever whenever `google` was undefined. With the SDK absent — blank credential, rejected key,
referrer refusal — `ldnaInitMap()` was never reached, the absolutely-positioned placeholder was
never hidden, and the panel stayed a grey 420 px box reading "Loading map…" for the life of the
page, taking both draw tools, all three autocompletes, every boundary overlay, the Important
Places pins and the saved-geometry `fitBounds` with it, silently. Twelve seconds is now the
ceiling, after which the panel says what happened and says the stored geometry is safe.

**Geometry paints even when the basemap does not, and the renderer binds to `style.load`
because of it.** `load` waits for every declared source to resolve; when the PMTiles archive
cannot be read that never happens, so a handler on `load` never runs and the user's polygons
are invisible on a map that is otherwise alive. On the first source error the style is swapped
for the blank one — once, latched — and the geometry is repainted onto it. **This is the live
condition, not a hypothetical: the R2 bucket serving the archive returns no
`Access-Control-Allow-Origin` header and its OPTIONS preflight is 403**, so browsers currently
refuse every tile. See `docs/spatial/r2-cors-requirement.md`.

**Seller and Landlord get a pin and no search geometry, deliberately.** A listing is one
property, not a search area; they are already structurally excluded from the geography cascade.
The display panel is handed an empty geometry set alongside the pin, and nothing synthesises a
circle or a bounding shape around it. Buyer and Tenant carry the real polygons, radii,
boundaries and Important Places.

**Important Places are now read on the detail pages.** They have been stored in
`important_places_json` since 9C and were read by nothing on the listing page — the wizard wrote
them and the listing never showed them. Both Buyer and Tenant controllers normalise them through
`ImportantPlacesService` so the page cannot develop its own idea of the row shape.

**Flood-zone and school-district overlays remain Google-only.** They are styled per FEMA
designation, which needs a styled multi-layer source the renderer does not have yet; their
legends are suppressed under MapLibre rather than drawn in the wrong colours. Deferred, not
dropped.

### Location DNA attribution, and the Overture pre-activation gate

**Nothing here activates the corpus.** `OVERTURE_CORPUS_POI_ENABLED` and the registry's
`location_providers.providers.overture_corpus.enabled` are both `false`, no corpus version is
pinned, and `OvertureActivationReadinessTest` fails if any of that changes.

**Places is not ODbL, and that is the mistake to avoid.** Four of Overture's six themes are ODbL;
the Places theme is three permissive licenses at once — CDLA-Permissive-2.0 (the bulk),
**Apache-2.0 (the Foursquare slice)** and CC0-1.0. The corpus as imported cannot say which member
supplied a given row: `OverturePlaceNormalizer` counts `sources[].dataset` and discards the names.
So every published row is treated as though it could be the Foursquare slice, and **the strictest
obligation governs the whole theme** — which is why the Apache NOTICE requirement, the one thing
here that can actually be breached, is the blocking prerequisite.

**The Apache-2.0 obligations are discharged by three separate files, and the separation is the
point.** `resources/legal/foursquare-os-places-NOTICE.txt` is Foursquare's NOTICE **verbatim**
(retrieved 2026-09-09 from `https://opensource.foursquare.com/places-notice-txt/`, the URL Overture's
attribution page names as authoritative; they publish it as a web page, so the file is that page's
notice content with markup removed and nothing else changed).
`resources/legal/apache-2.0-LICENSE.txt` is the licence in full, served to recipients at
`/data-sources/apache-2.0` from our own bytes rather than by linking apache.org.
`resources/legal/overture-corpus-MODIFICATIONS.txt` is **ours**, disclosing the FL bbox filter, the
0.90 confidence floor, the 8-token category crosswalk, schema normalisation, and the discarding of
`sources[].dataset`. That NOTICE permits appending our changes to it — we do not, because a merged
file leaves a reader unable to tell whose sentences are whose, and preserving the NOTICE *as
Foursquare's* is the obligation. Storage details are deliberately absent from the published notice.

`notice_verified` is now **`true`**, and `LocationDnaNoticeComplianceTest` asserts the artifacts
rather than the flag. **This is not activation authorization** — the two provider gates are
independent, still false, and `OvertureActivationReadinessTest` asserts that a satisfied NOTICE did
not move either. The canary command says the same thing out loud, because an operator who has just
watched the licensing prerequisite clear is the person most likely to read it as permission.

**Attribution resolves from the ROWS, never from the active provider.** A POI row persists: switch
the corpus off and its rows are still on the page tomorrow, still owing Overture; switch it on and
yesterday's Google rows are still there. Provider config says what will be fetched *next*; a
license obligation is about what is on the screen *now*. So `LocationDataAttribution::forPois()`
reads each row's own `provenance_json.provider`.

**`data_source` used to lie, and now does not.** It was written as the literal `'google_places'` on
every row regardless of which adapter answered — it predates the provider registry — so activating
the corpus would have stored rows claiming Foursquare/Overture places came from Google, contradicting
`provenance_json` on the same row. It now takes `$currentProvenanceProvider`, the **same** identity
`provenance_json` is built from, deliberately rather than a second lookup: two independent
source-selection mechanisms on one row is how they come to disagree. Attribution still reads
`provenance_json` — it is the richer record, and any row written before the fix still carries the old
literal. `PoiProviderProvenanceTest` pins both values and the no-contradiction invariant.

**Two attribution blocks on one page, kept apart deliberately.**
`offer-listing/partials/_mls_attribution.blade.php` states where the *listing* came from under the
Bridge/Stellar IDX terms; `partials/location-dna/_data-attribution.blade.php` states where the
*places beside it* came from under open-data licenses. Merged into one "data sources" line, a
reader would take the Stellar copyright as covering the nearby-restaurants list, or the reverse.
Shared visual language, separate claims — `LocationDnaAttributionSurfaceTest` pins it.

The Location DNA component is included **once**, in the shared
`partials/location-dna-agent-panel.blade.php`, which is why neither the seller nor the landlord
view file needed to change. `config/location_attribution.php` is the SSOT with exactly two readers
(the support class, and `routes/web.php` for the NOTICE path); no Blade file reads it, so a
template edit cannot change an attribution claim. `/data-sources` is public and unauthenticated
because the pages publishing the data are.

**Corpus identity is one definition, `CorpusSurface`, with two readers.** `capabilityHash()` hashes
`config/location_providers.php` alone, and the corpus version is pinned in a different file — so
re-pinning `OVERTURE_CORPUS_POI_VERSION`, the exact operation the two-corpus design exists to make
possible, was invisible to **both** things that depend on it: the tile key (the previous corpus's raw
candidates kept being served for the tile TTL) *and* `LocationDnaVersionService::fetchVersion()`, the
stamp on every row's `pois_fetch_version` (already-persisted rows from the previous import read as
current and were never refetched). Same defect, two layers. Fixing them separately would have left
two definitions that must agree forever, so both read `CorpusSurface::token()`. **`fetchVersion` only
— never `scoringVersion`**: a re-pin requires a refetch but is not a scoring change, and the two
stamps are independent on purpose.

**The Bridge canary.** `location-dna:generate` accepts `bridge` (the pipeline runner always could;
only the command refused). It requires `--canary` — a canary you can start by typing the wrong word
is not one — and refuses any `listing_id` that is not a single positive integer, because
`(int)'all'` is `0` and `(int)'12,13'` is `12`, and both would read afterwards as a successful run
against the wrong record. There is no `--all` and no id list. `--dry-run` reports the provider and
licensing posture and writes nothing. One-listing isolation is the service's own property — every
POI delete is scoped to `(listing_type, listing_id)` — and the posture report prints the listing's
existing row count so a re-run's idempotency is observable rather than assumed.

### Property coordinate ladder (separate from the Location DNA pipeline)

`app/Services/Location/Coordinates/` resolves one property address to one coordinate, provider-neutrally. `PropertyCoordinateResolver` walks a list of `CoordinateProviderAdapterInterface` rungs in precedence order and returns the first resolved answer — local sources before any paid or rate-limited one.

Three types carry the design and are worth reading before touching anything here:

- **`PropertyAddress`** normalizes twice on purpose — `coordinateLookupLine()` drops the unit (what a geocoder is asked, and the cache key), `propertyIdentityLine()` keeps it (what distinguishes two condos).
- **`CoordinatePrecision`** decides via `isExact()` whether a point may drive distance, commute and flood-boundary work. A ZIP centroid and a rooftop are both "a latitude and a longitude"; this enum is what stops the first being measured from.
- **`PropertyCoordinateResult`** is the single immutable return type. Consumers should reach for `exactCoordinates()`, not `->latitude` — the accessor enforces the gate, the property bypasses it.

Built in phases: G1 the contracts, G2 the two local rungs (`ExistingCoordinatesAdapter`, `BridgeMlsCoordinatesAdapter`, assembled by `LocalCoordinateLadder`), G3 the first network rung (`CensusGeocoderAdapter`), G4 operational safety. **Nothing here is wired into a listing flow yet** — `PropertyCoordinateResolverInterface` is deliberately bound to nothing, so no component can inject a resolver by accident. Integration is G5, and Seller/Landlord Location DNA dispatch stays separately gated regardless.

A rung that is *broken* raises `CoordinateProviderUnavailable`; a rung that simply *cannot match the address* returns an unresolved result. Keep that distinction when adding a rung — the first must never be cached, the second should be.

**The address-point rung and the suggestion contracts (exact-address foundation).** `AddressPointCoordinateAdapter` answers from our own imported corpus in `pgsql_spatial.addresses`, and sits on `StandardCoordinateLadder` **below `BridgeMlsCoordinatesAdapter` and above `CensusGeocoderAdapter`** — below Bridge because a coordinate carried by the listing record outranks one matched by address line, above Census because a published address point beats interpolating a house number along a street range, for free. It is flag-inert (`ADDRESS_POINT_CORPUS_ENABLED`, plus a pinned `ADDRESS_POINT_CORPUS_VERSION`) and the corpus is empty; **no importer exists and no address dataset has been downloaded**.

Rows are matched on `normalized` = `PropertyAddress::coordinateLookupLine()` by **equality only**. The `addresses_trgm` GIN index is for typeahead and is deliberately unused here — trigram similarity returns the *nearest* address, which is another property's coordinate reported as success. When matched rows disagree on a point, the rung returns unresolved and the next rung tries.

Address suggestions live in `app/Services/Location/Suggestions/` (`AddressSuggestionProviderInterface`, `AddressCandidate`) and are **deliberately not** part of the coordinate contract. A candidate offers `toPropertyAddress()` and no conversion to a `PropertyCoordinateResult`: a pick re-enters through the ladder like any other address. That separation is the fix for the old path, where an autocomplete pick's coordinate became the listing's coordinate with no provider and no precision recorded. Nothing implements the interface yet.

**G4 added four things worth knowing before touching this code:**

- **Geography agreement.** A returned coordinate is checked against the requested state and ZIP5. A geocoder will happily resolve a street name that exists in two states to the wrong one and return a perfectly valid coordinate; nothing about the numbers reveals it. Exact equality on normalized values only — no fuzzy fallback.
- **Provenance is stored.** `property_location_dna` carries `geocode_precision`, `geocode_provider` and `normalized_address`. Read precision back through `CoordinateProvenance::storedIsUsableForLocationDna()`, never by comparing strings — an unrecognised value must read as coarse, and that rule lives in one place.
- **Guards, in `Coordinates/Guards/`.** `ProviderRequestBudget` (hourly/daily caps) and `ProviderCircuitBreaker` refuse *before* a request by raising, so the resolver skips the rung exactly as it would any other unavailable one. Both are provider-neutral so a future commercial adapter wraps identically. Neither can affect a local rung.
- **Telemetry.** `CoordinateProviderTelemetry` writes one structured `coordinate_provider` log line per attempt. **It must never carry an address** — only `CoordinateProviderTelemetry::addressHash()`. A test asserts no address fragment reaches the log.

`php artisan location:probe-census-address` sends one live request on demand. Dry-run only, refuses to run without `--force-probe` while the flag is off, never scheduled, never called from application code.

### MLS import — the three tiers and the no-drop contract

Bridge sends **553 Property fields**; `bridge_properties.raw_json` keeps every one of them, and
`BridgeApiService` sends **no `$select`** — which is *why* the payload is complete. **Do not add
one.** Everything that was ever lost was lost after the data was already in our database.

Every populated Bridge field resolves to exactly one disposition in
`MlsFieldCatalog` (`app/Services/ListingImport/Mls/`) — the single classification authority:

* **Tier 1 `TIER1_BYO`** — imported into an existing editable Create Offer field.
* **Tier 2 `PROPERTY_FACTS` / `LISTING_CONTEXT` / `CONTACTS`** — legitimate facts with no editable
  equivalent, persisted as supplemental MLS metadata and rendered in MLS Details.
* **Tier 3 `RELATED_RESOURCE`** — belongs to Media / OpenHouse / Member / Office.
* plus `DISPLAY_CONTROL`, `ADDRESS_COMPONENT`, `INTERNAL`, `RESTRICTED`, `DERIVED`, `UNSUPPORTED`.

`MlsNoFieldDropContractTest` fails the build, naming the field and the property type, when a
populated field in any of the **seven per-type fixtures** (`tests/fixtures/mls/bridge/`) resolves to
none of them. `MlsSearchImportParityTest` does the same for every field
`PropertyDetailViewMapper` renders, so a field added to Stellar search and forgotten on import is a
red build. **There is deliberately no generic bucket** — `UNSUPPORTED` is empty and an entry added
there must carry a sentence saying why.

**Adding a field to a display allow-list is a licensing decision, not a mapping tweak.** All the
allow-lists fail closed; a field nobody has cleared is rendered nowhere.

**Tier 1 is not repeated in Tier 2** — except where the listing page does not actually render the
destination. `TIER1_MAPPED_BUT_UNRENDERED` names those (landlord's `air_conditioning`, `sewer`,
`water`, `floor_covering`, …) and is re-derived from the Blade templates by the parity test, so it
cannot go stale in either direction.

**Do not map a field whose NAME matches and whose MEANING does not.** `minimum_cap_rate` and
`minimum_annual_net_income` are the seller's *desired minimum*, not the property's actual figures;
`garage_parking_spaces` is a Yes/No control, not a count; `unit_number` is the address's unit, not a
building's unit count; and the lease-term vocabularies do not intersect the feed's. All seven such
fields are preserved and displayed under MLS Details instead. Four map targets (`water_view`,
`pet_policy`, `tenant_pays`, `rent_includes`) have **no `wire:model` binding** — importing into them
would write a value the user can neither see nor correct, so they are Tier 2 as well.

**Empty is never rendered.** `MlsValueFormatter` is the one place that decides: null, blank,
whitespace, empty array/object and a `false` boolean all become nothing, so a field with no value
never becomes a row and a section with no rows never becomes a section. **Zero does render** — an
application fee of $0 is a fact; `false` does not, because a wall of "No" buries the facts a reader
came for.

**Display permissions are the feed's, not ours.** `MlsDisplayPermissions` reads
`IDXParticipationYN`, `InternetEntireListingDisplayYN`, `InternetAddressDisplayYN`,
`InternetAutomatedValuationDisplayYN` and `InternetConsumerCommentYN`. An explicit `false` is
absolute; a *missing* flag permits, because these columns are populated on 1,202/1,202 records and
treating absence as refusal would blank every address the day Stellar renames a column.
`InternetAddressDisplayYN` is false on **71 of 1,202** cached records — the address is still
imported, still stored, still drives the coordinate ladder, and is shown to the listing's owner; it
is withheld from the public and from the Stellar page. **Preservation and display are separate
permissions and must stay separate.**

**Related resources, probed live 2026-09-04** (`php artisan mls:probe-resources --force-probe` —
read-only, refuses without the flag, never scheduled): **Member (79 fields), Office (55) and
OpenHouse (36) are exposed; Room and Unit return 404.** `BridgeRelatedResourceService` caches on the
**member/office key, never the listing** — which is the whole N+1 answer, since one brokerage lists
hundreds of properties — with a per-import ceiling counted after the cache. Every failure resolves to
an empty section: **an import must never fail because a phone number could not be fetched.** Do not
synthesise Rooms or Units from `RoomsTotal` / `NumberOfUnitsTotal`; a count is not a roster.

**Precedence on re-import.** Editable fields: the **user wins** — a populated field is never
overwritten. Supplemental MLS details: the **feed wins, wholesale** — the blob is replaced, so a fact
the MLS retracted disappears rather than lingering. Photographs: the feed owns MLS entries, the user
owns their uploads, their cover choice and their ordering.

`mls_media.max_images` was **50 and is now 250**: the old ceiling mirrored the manual uploader's,
which is about bytes *we* store, and MLS media is referenced not copied — it truncated 186 of 1,202
cached listings. **Both `MLS_MEDIA_IMPORT_ENABLED` and `MLS_MEDIA_LICENSE_ACKNOWLEDGED` now default
true**, by an **owner decision of 2026-09-04** that explicitly superseded the photo clause of the
locked 2026-07-05 policy. A licence audit taken immediately before it found **no written Stellar
approval in this repository** for public imported-listing photo use — the decision rests on owner
authority, not on discovered documentation, and
`docs/mls-direct-import-design-and-plan.md` § "Owner decision — 2026-09-04" is the record. Both
flags are still read on every extract, write and render, and neither overrides the feed's own
per-listing or per-media controls. MLS-sourced listings carry a Stellar/Bridge attribution block
(`_mls_attribution.blade.php`), gated on import provenance so a manual listing never claims it.

### Imported-listing presentation, and the Your Terms follow-ups

**One property fact, one presentation.** An imported listing used to carry TWO descriptions of the
same house: its own Property Details card, and directly beneath it a dense block titled *MLS
Property Details* in its own typography. No **field** was duplicated — `MlsPropertyDetailsPresenter`
already suppresses a Tier-1 fact that reached an editable field at import time — but a reader met
two competing presentations and had to decide which to believe. The duplication was structural, not
field-level, and that is what `MlsDetailLayout` (`app/Services/ListingImport/Mls/`) fixes.

**Every stored section gets a SLOT, and an unknown title is placed rather than dropped.**
`Property Details` merges into the page's own card under an *MLS Property Details* sub-heading;
`HOA / Association` and `Taxes / Financial` merge into the Tax / Legal / HOA card (and are part of
that card's gate — an imported listing can carry a whole fee schedule with every canonical tax field
blank); everything else becomes an ordinary `section-card`. A title the class has never heard of
lands in a slot chosen from its **group**, so widening `MlsFieldCatalog` publishes the new section
instead of silently losing it — the failure mode a title allow-list would have.

**The parity guarantee moved from one block to one ROW.** `_mls_facts_rows.blade.php` is the single
row template for all three surfaces, and it emits the **host page's own** row markup — the seller and
landlord `$row()` closures differ in font sizing, so the styles are passed in rather than hard-coded,
or the imported rows would be the odd ones out on exactly one of the two pages. The review screen
(`_mls_property_facts.blade.php`) still renders every section in one card, because it is a wizard
panel; only the listing pages place sections. `MlsListingDetailPresentationTest` counts every stored
row onto the page rather than sampling labels, since a section landing in no slot is precisely what
spot-checks miss.

**Related-resource rows that repeat a contacts VALUE are dropped at READ time.**
`MlsRelatedResources::rowsFrom()` compares label **and** value, and the two presenters deliberately
label the same fact differently (`Agent Phone` vs `Direct Phone`), so one phone number reached the
page three times. Read time, not write time, because the duplicates are already in every stored blob.
Contacts rows are **never** deduplicated against each other: an agent phone and a brokerage phone that
happen to match are two facts.

**Attribution is last on the page and sits with the MLS contact and bookkeeping cards.** It used to
sit directly under the old block, two thirds of the way up, reading as a footnote to that block alone
rather than to the imported facts now spread across the cards above it.

**`auction_type` renders as "Listing Method"** on both pages — the name every screen that *asks* the
question uses. "Auction Type" named the storage key, and on a Traditional listing announced an auction
that is not happening.

**Your Terms conditionals: the PARENT decides whether a branch is published.**
`ConditionalTerms` (`app/Support/OfferListing/`) holds the rule, and it is display logic only — no
stored value changes. A gate used to read `$hasAssumable || $str('assumable_loan_type') || …`, so any
child value left behind by a financing type the seller had since **deselected** re-opened its whole
section: a cash-only listing kept advertising an assumable mortgage. Now the branch asks only what is
currently offered, and the child decides its own row. `amount()` formats by the `$` / `%` control
beside the figure — a 3% initial deposit used to publish as `$3`.

**Six follow-up answers were stored by every entry path and rendered by none**, and are now shown:
`exchange_item` (only its "Other" box was printed, so a traded vehicle showed nothing),
`exchange_liens_disclosure` (read under `exchange_liens`, a key no flow writes),
`assumption_fee_responsibility`, `prepayment_penalty` (only its amount was printed), and the landlord
"Other" boxes `other_lease_term` (the row substituted the legacy `other_lease_for`), `custom_lease_term`,
`other_rent_include`, `other_tenant_pays`, `other_owner_pays`, plus the commercial single-unit storage
pair and `space_features` / `neighboring_tenants`. `value_determination` and
`assumable_occupancy_requirement` moved out of the Property Details card, where the second was rendered
a **second** time. `Association Fee` no longer publishes the literal row `/ monthly` when a frequency
arrives with no amount.

### MLS live sync — keeping an imported listing current

Import copies the feed once; **sync keeps the copy true**. `MlsListingSyncService`
(`app/Services/ListingImport/Sync/`) refreshes an MLS-linked listing from Bridge without a manual
re-import. **It ships inert** — see the activation gates below.

**Linkage identity is the listing key, never the address.** `mls_listing_key` (Stellar's
`ListingKey`) is the preferred stable identity; `mls_number` (`ListingId`) is the fallback used only
when the key is absent, and it is also what the per-listing lock is keyed on. A listing carrying
neither is not MLS-linked and is never synced. **Address is not a synchronization identity** — it
changes, it is normalized in two different ways for two different purposes, and matching on it is
how one property's data lands on another's record.

**Status: `StandardStatus` is authoritative, `MlsStatus` is context.** They are different fields
with different values on the same record — the 2026-09-10 `mls:probe-lifecycle` run caught
`Closed`↔`Sold` and `Active Under Contract`↔`Pending` directly. `StandardStatus` is the
RESO-normalised field, so it is the market status for an MLS-linked listing; `MlsStatus` is retained
verbatim alongside it as Stellar's own local wording, **never mapped onto the other and never used
as a fallback** — a listing with no `StandardStatus` is one whose status we do not know, and
inferring it from `Sold` would re-perform the normalisation RESO already did. `MlsSourceStatus`
records which strings the live dataset actually returned (`Active`, `Pending`, `Closed`,
`Active Under Contract`, `Coming Soon` confirmed; the off-market vocabulary unconfirmed, which is
not the same as non-existent — an IDX feed commonly withholds those).

**For an MLS-linked listing, BidYourOffer timers do not override Stellar.** `expiration_date`, a
manually selected `listing_status`, a bidding timer and an auction timer are all reached only by
listings that own their own lifecycle. `expiration_date` is a date a user types into a form; on an
imported listing it silently computed `Expired` for a property Stellar still lists as Active, with
nothing on the page explaining why. `MlsLinkedListingStatus` is one shared class rather than a copy
per model, because seller and landlord disagreeing about whether the same feed listing has expired
is not a cosmetic difference. **`is_sold` still wins** and is checked before this class is
consulted — a closed BidYourOffer transaction is not something an MLS status string may reopen.
**Manual non-MLS listings keep their existing lifecycle behaviour, unchanged.**

**Price: `mls_list_price` is separate from Your Terms, and that separation is the feature.** Stellar's
`ListPrice` is persisted under its own key and refreshed whenever it changes. The user's Desired Sale
Price, landlord rent terms, starting/reserve/buy-now prices and the rest of Your Terms stay
**BYO-owned and are never overwritten by a price move in the feed**. The payment calculator *may
consume* `mls_list_price` — it leads the Seller controller's fallback chain, which is why an imported
listing no longer opens the Estimated Monthly Payment panel at $0 — and a what-if inside that
calculator is browser-side only and writes nothing back.

**Fact sync shares one mapping with import, and differs only in precedence.** `MlsFactProjection` is
the single place a canonical MLS fact becomes a listing meta value, used by both the owner-scoped
quick import and the unattended sync, so there is no second lookalike mapping to drift.
`MODE_IMPORT` leaves a populated field alone (the user may have corrected the feed; re-importing must
not revert them). `MODE_SYNC` lets Stellar win for the facts it owns — beds, baths, square footage,
year built, property type, features, HOA, taxes. **Which** facts those are is `MlsSyncFieldPolicy`'s
decision, not the projection's, and it guards from both ends: `NEVER_SYNC` filters canonical facts
before projection and `PROTECTED_META_KEYS` filters the resulting meta keys, so a future `MlsFieldMap`
entry cannot reach a BYO term by arriving under a canonical key nobody thought to exclude. **Every
never-synced field states its reason in the policy, and a test asserts that.**

**Change detection.** `ModificationTimestamp` is the primary source change marker;
`StatusChangeTimestamp`, `PriceChangeTimestamp` and `PhotosChangeTimestamp` are retained and used for
what they each describe. Media goes through the existing `MlsListingGallerySync`, unchanged —
**user-uploaded photos, the user's cover choice and their ordering are preserved**, and MLS media
identity is by media key.

**Failure preserves last-known-good.** An unavailable provider is never read as a deletion, and a
record absent from the feed leaves the listing standing rather than blanking it; `NOT_FOUND` records
the fact and deletes nothing. The success stamp advances **only** on an actual success — not on a
fault and not on a not-found. Sync is **idempotent** (running it twice against the same source
changes nothing the second time) and **lock-protected** per listing key. A sync never dispatches the
Location DNA pipeline.

**Activation is fail-closed, in three independent gates** (`config/mls_sync.php`, whose header states
*absence is OFF*):

| Flag | Default | Governs |
|-----|---------|---------|
| `MLS_SYNC_ENABLED` | `false` | the master gate — off stops every sync by any route |
| `MLS_SYNC_SCHEDULE_ENABLED` | `false` | the unattended sweep and the daily reconcile |
| `MLS_SYNC_LAZY_REFRESH_ENABLED` | `false` | the stale-on-access refresh |

None of the three is in `config/required_production_flags.php`, and must not be added — **the deploy
contract may never name a safety switch.**

**When explicitly enabled**, the operating parameters are: normal sweep every **15 minutes**, live
freshness window **60 minutes**, daily reconcile at **03:20**, terminal/non-live freshness **1440
minutes**, normal sweep ceiling **100**, reconcile ceiling **500**, failure backoff **30 minutes**.

**Stale-on-access has two viewers and two answers, and the difference is the design.** The
authenticated **owner or their agent**, opening their own listing, gets one synchronous refresh —
a known user acting deliberately on their own record, bounded by how fast a person loads a page, and
the one person who needs the answer current *now*. **Everybody else, including every anonymous
visitor, triggers no outbound request**: the view is recorded as demand in `MlsSyncDemandQueue` and
influences ordering for the next sweep. Twenty visitors on one stale listing produce twenty cache
writes and zero Bridge requests; twenty simultaneous owner views produce one request, because the
losers re-read inside the lock and find the work done. **A repeated visitor cannot create a request
storm** — not because a counter rations it, but because the expensive work is not on that path.
"Queue it for the public path" would be a lie on this deployment: `QUEUE_CONNECTION` is `sync` and
nothing runs `queue:work`, so a dispatched job executes inline in the dispatching request.

**Deliberately deferred — do not document or treat any of these as done:**

* **Address and coordinate live sync.** An address *is* an MLS fact, but a change cascades into
  `coordinateLookupLine()`, the coordinate ladder and Location DNA; a listing whose address moved
  while its coordinate stayed is worse than one whose address did not move. Writing a latitude
  straight from the feed also bypasses `CoordinatePrecision` entirely.
* **`PublicRemarks` / marketing prose sync** — a test pins that prose never reaches the listing
  through a sync.
* **Off-market MLS media retention / delete policy.** `detachAll()` is deliberately *not* called for
  off-market statuses — an unreviewed status string must not be what silently deletes a gallery.
* **Hero / listing display of both the MLS List Price and the user's Your Terms** side by side.
* **Production activation.** All three flags ship `false`.

### BidYourOffer Explore (public IDX discovery surface)

`/explore` is a Google Photorealistic 3D neighbourhood with eligible Stellar **FOR SALE and
FOR RENT** listings placed at their own MLS coordinates. It is a *presentation surface over
systems that already exist*: it synchronises nothing, imports nothing, scores nothing and
persists nothing. Everything lives in `app/Services/Explore/`, gated by `config/explore.php`
(`EXPLORE_ENABLED`, default `false` → every route 404s, data endpoints included).

**VOW is MISSING, and that is a finding rather than a placeholder.** No approval, dataset,
credential, registration flow, policy class or feed field exists — see
`docs/bidyouroffer-explore-audit-2026-09-10.md`. `VowAvailability` therefore returns
`PUBLIC_IDX` for every caller **regardless of `EXPLORE_VOW_ENABLED`**: a boolean in a config
file is the wrong last line of defence between an unapproved licence tier and the public, so
the refusal is in the code and the flag exists only so the posture can be reported honestly.
The Property Intelligence control is **absent, not disabled** — greying it out would tell
every visitor we hold off-market intelligence we are withholding. **Delayed Distribution is
not represented in this feed at all** (no such field among the 551), so it cannot be honoured
or leaked; `ExploreAccessTier` keeps `PUBLIC_IDX` / `VOW_REGISTERED` as separate concepts
rather than one `is_mls_visible` boolean precisely so the distinction stays expressible.

**Sale/rent is an exact-match allowlist over `PropertyType`, and it is deliberately not
`PropertyTypeVocabulary`.** That class matches SUBSTRINGS, which is right for picking a form
vocabulary and catastrophic here: `forRole('Residential Lease', 'seller')` is `'Residential'`,
so a rental read through it becomes a sale and a monthly rent prints as a purchase price.
`ExploreTransactionType` is exact and case-sensitive; SALE is Residential / Income /
Commercial Sale / Vacant Land / **Business Opportunity** (199 live records — not omitted),
RENT is Residential Lease / Commercial Lease. An unclassified type is excluded from **both**
filters, because its `ListPrice` cannot be labelled. On a lease record `ListPrice` IS the
periodic rent and the period is `LeaseAmountFrequency` — **`/mo` is wrong for about a quarter
of the rental inventory** (live: Seasonal 78, Annually 19, Weekly 16, Daily 2 against Monthly
386), and a rental with no stated frequency gets no suffix rather than an assumed one.

**Eligibility is `ExploreEligibilityPolicy`, and it delegates rather than reimplements.**
The feed gate is `MlsDisplayPermissions::listingDisplayable()` — note this is STRICTER than
`ListingVisibilityGate`, which reads only `IDXParticipationYN`; where they differ the strict
one is what a public surface must use. Status is `StandardStatus` against
`explore.public_statuses` (**`Active` alone** — the same status both OData builders and the
Stellar detail page already treat as consumer inventory; `Coming Soon` is excluded
specifically because pre-marketing distribution is governed by rules this repo has no record
of). A record whose `raw_json` cannot be read resolves to `denyAll()` — "we could not
determine the permissions" is not "there were no permissions". **An address refusal is not a
listing refusal**: 71 of 1,203 live records suppress the street line and postcode and keep
the marker, the price and the facts.

**`ExploreListingProjection` is the security boundary.** Every consumer-visible field is a
named readonly property and `toArray()` writes every key by hand — no dynamic expansion, no
extra bag, no path from a `BridgeProperty` to a response. It is an ALLOW-list: a deny-list
would have to stay complete forever against a 553-field feed that gains fields without asking
us. `ExploreProhibitedFieldsTest` seeds real prohibited values under their real field names
(`STELLAR_TenantName`, `LockBoxLocation`, `PrivateRemarks`, `ListingTerms`, the ListAgent
contact block, …) on a listing Explore genuinely publishes, and asserts against
`MlsFieldCatalog::RESTRICTED` / `INTERNAL` / `CONTACTS` rather than a hand-written list.

**Property identity is parcel + unit, never coordinates.** A parcel is routinely the whole
building — in the live cache a condominium and the building's income listing share one parcel
exactly — so dropping the unit would merge two homes. Proximity is absent from
`ExplorePropertyIdentity` and must stay absent, or a forty-unit tower becomes one "property"
with forty conflicting histories. Only an opaque hash is emitted; a parcel number leads to
owner records.

**Reused, not rebuilt:** canonical destinations are the existing public Seller/Landlord
listing pages, resolved from the `mls_listing_key` provenance meta and batched one query per
role (`forWorkflow()` narrows in SQL, `ListingWorkflowResolver` decides in PHP — both halves).
Media is `MlsMediaExtractor` + `MlsMediaPolicy`, so only the **unbranded** tour is offered
(branded is RESTRICTED) and `has_video` is correctly false until a video category is licensed.
**Ask AI is not wired in** — `ask-ai.listing-question` answers only about a listing the
requester OWNS and serves private offer-listings, not public MLS data. **Save does not exist
anywhere in this application.** Schedule Showing appears only where a real BidYourOffer
listing exists. `match_score` is null: no reliable score exists for an MLS-only property and
Phase 1 invents none.

**Explore discovers CURRENT inventory through the ONE existing MLS pipeline — it is not a
reader of an old cache.** A viewport request hands the bbox to `ExploreInventoryService`, which
is a *translator*, not an importer: it builds a minimal `BuyerCriteriaPayload` (property types
+ one rectangular polygon) and calls `LazyBridgeImportService::importForCriteria()`. That is
the same advisory lock, the same `bridge_criteria_fetch_cache`, the same pagination and the same
`BridgePropertyNormalizer::upsert()` the criteria searches use — but NOT the Location DNA
dispatch, which Explore opts out of (see below). **No Explore class holds a provider client,
writes an MLS row, or reaches the network — a test scans the whole namespace for it.** Every
change to shared code is additive and inert at its default: two role strings in
`LazyBridgeImportService::SUPPORTED_ROLES`; optional pagination caps that the importer clamps
DOWNWARDS so a call site can lower a spend limit and never raise one; a per-request admission
callback (`$beforeProviderRequest`); a `$dispatchDna` switch (default `true`); and
`ProviderRequestBudget::admit()`.

**Both Explore roles use the BUYER filter builder, deliberately.** Its name is historical — it
emits `StandardStatus eq 'Active'`, a PropertyType disjunction and a lat/lng box, and knows
nothing about purchasing — so supplying the rental PropertyTypes produces the rental filter.
The tenant builder is NOT used: its documented rental vocabulary includes `Residential`, which
in this dataset is a SALE type. That defect belongs to the tenant-search work and Explore must
neither inherit nor fix it here.

**The tile grid is load-bearing, not tidying.** The fetch cache is keyed on a payload hash, so
an unsnapped viewport would mint a new key on every pixel of pan and "reuse the existing cache"
would collapse into a provider request per camera nudge. The discovery box is snapped
**outwards** (default 0.05°) so neighbouring viewports share one entry, a pan within a tile
sends nothing, and the box always *contains* the viewport — a property near the screen edge
must never be rendered from an area discovery did not ask about.

**Presence is not currency, and that is how a listing disappears.** After a pass that COMPLETELY
covered the viewport, every currently-eligible listing in it was just upserted — so a row the
pass did not touch is one the provider no longer returns (sold, withdrawn, off IDX, gone from
the feed) and it is withheld. The window is `ExploreFreshness`, which borrows
`bridge.lazy_ttl_minutes` — the same value `mls_sync.freshness_minutes` borrows, for the same
reason — against the `imported_at` stamp every upsert already writes. **Nothing is deleted**;
declining to render is a much smaller decision than deleting MLS data on the strength of an
absence. The rule is NOT applied after a **partial** pass (a pagination ceiling: absence means
"we stopped asking"), nor when discovery is off, nor when the provider was unreachable — and
the response carries `discovery.complete` / `discovery.degraded` so an empty map is never
worded as an empty market.

**The property panel re-asks about the one record it is about to publish**, through the existing
`BridgeListingLookupService::refreshByListingKey()` — the method built precisely because every
other lookup there is local-first and a freshness question cannot be answered from the row you
would be handed back. One request, one record, on an explicit user action, skipped entirely
when the row is already inside the window. Eligibility is then re-decided, so a listing that
went Pending or lost IDX participation since the marker was drawn **404s** rather than being
presented as available.

**Discovery ships OFF** (`EXPLORE_DISCOVERY_ENABLED`), for the same reason `MLS_SYNC_ENABLED`
does: deploying code must not by itself begin unattended traffic to a third-party provider.
With it off Explore is cache-only **and says so** in the response, because a cache-only answer
must not be mistaken for a complete one.

**Explore being current is only half of what a consumer experiences, and the other half is
somebody else's flag.** The canonical Seller/Landlord pages do NOT read `bridge_properties` —
`ListingStatusDisplay` and `ListingPriceDisplay` read `mls_standard_status` / `mls_list_price`
**meta on the auction**, and the only writer of those is `MlsListingSyncService`. Explore's
discovery writes no listing meta, deliberately: which facts may move onto a listing is
`MlsSyncFieldPolicy`'s decision from both ends, and a public map endpoint reaching into that
store would be the parallel ingestion path that must not exist. So with the sync gates closed a
consumer can see **Pending / $525,000** on the map and **Active / $535,000** on the listing they
click through to. `ExploreCanonicalCurrentnessTest` pins that gap AND pins that the existing
sync closes it. **A coherent launch therefore needs `MLS_SYNC_ENABLED` + `MLS_SYNC_SCHEDULE_ENABLED`
alongside the Explore flags** — the schedule specifically, because a non-owner view sends nothing
and only records demand, so a public visitor's page is current only if a sweep already made it so.

**Provider spend is bounded by a HARD ceiling, and the throttle was never what bounded it.**
`throttle:120,1` limits REQUESTS; one unfiltered viewport request costs up to five provider pages
per transaction type, so a caller inside that throttle could reach ~72,000 Bridge requests an hour
by traversing distinct cold tiles. Tile snapping and the fetch cache make REPEAT visits free;
nothing made DISTINCT ones bounded. `ExploreProviderBudget` (`app/Services/Explore/Guards/`)
closes it, and it is a **composer, not an implementation** — every counter, key, window, TTL and
lock belongs to the existing `ProviderRequestBudget`, asked at two scopes. A test asserts the
guard contains no `Cache::`, no `increment(`, no `gmdate(`: two mechanisms counting "a request"
would eventually disagree about what one is, and the disagreement would arrive as a bill.

**The unit is one outbound Bridge HTTP request, admitted immediately before it is sent.**
`ProviderRequestBudget::admit()` checks and charges one request against the global and actor
ceilings together, under one cache lock (`flock` on the file driver this deployment uses), and
`LazyBridgeImportService` asks it through its optional `$beforeProviderRequest` before EVERY page.
A configured 60 admits exactly 60 with any number of PHP processes — a test races real processes
against the real file store. A pass that runs out between page 3 and page 4 never sends page 4,
keeps the rows it did fetch, writes **no** fetch-cache row (a truncated tile must not later be
served as a warm, complete one) and reports `budget_limited`. The earlier shape — one check before
a pass, its pages charged afterwards — let 59/60 finish at 69/60 and let two racing workers both
take the last unit, which is why it was replaced. Admission that cannot be decided (lock timeout,
cache fault) is a refusal. The panel lookup is admitted the same way, as one unit.
`ExploreProviderBudget::blockedReason()` survives only as a read-only fast path that lets an
already-spent request skip the importer; it is not the ceiling. The Census rung still uses the
older check-then-charge pair and is unaffected.

**The two scopes fail in opposite directions.** The ACTOR ceiling (`user id, else IP` — the
identity every throttled route already uses, hashed before it becomes a cache key; nothing new is
fingerprinted) stops one browser traversing unlimited tiles. The GLOBAL ceiling stops what the
actor ceiling cannot see — many actors, or one rotating addresses — and is the one that would
have caught the ~16,000-request incident this work exists because of. The defaults are
deliberately conservative for a controlled launch — **actor 60/hour and 300/day, global 300/hour
and 2,000/day** — and are application-side ceilings, not a statement of Stellar's allowance, which
is not known here. **There is no way to configure "unlimited"**: a zero, negative or non-numeric
cap falls back to the shipped default, because a config value that restores an unbudgeted path to
a paid provider is that failure with an extra step. Switching the guard OFF does not unleash
traffic either — a disabled guard reads as "do not call the provider".

**Charge for what was SENT, not for what succeeded.** Admission happens before anybody knows how a
request will end, so a page that failed is charged: it consumed the provider's capacity whatever
came back, and counting successes only is how a failing integration retries its way through a
ceiling that looks like it is holding. A retry cannot bypass anything, because every page
re-enters admission. A cache hit sends nothing and is charged nothing — rationing our own memory
would defeat the cache the ceiling relies on. The panel's single-record refresh spends from the
SAME ceilings; a separate allowance for "cheap" calls would be a second budget, and the sum of two
ceilings is not a ceiling.

**Exhaustion degrades, it never empties.** `ExploreDiscoveryOutcome::budgetLimited()` reports
`complete = false` (so the withhold-unconfirmed-rows rule is suppressed and last-known inventory
stays on the map) and `degraded = true` (so the surface says "temporarily unavailable" rather than
"no listings in this view"). A ceiling we chose to impose is a fact about US; stating it as a fact
about somebody's neighbourhood would be a false claim.

**Explore never starts Location DNA, and therefore never reaches Google Places through it.** The
importer dispatches `ComputeLocationDna` for every new or re-addressed row, and that job's POI step
can call Google Places — whose `GOOGLE_PLACES_DAILY_LIMIT` / `_HOURLY_LIMIT` are declared in config
and read by no code, so `GOOGLE_PLACES_ENABLED` is the only gate on it. A discovery pass can upsert
500 rows, inline, because the queue runs `sync`. Explore renders no Location DNA, so both entry
points opt out: discovery passes `dispatchDna: false` to `importForCriteria()` (a
backward-compatible option, default `true`) and the panel passes it to `refreshByListingKey()` (an
option that already existed). Every other caller keeps its dispatch. Worth knowing: a later lazy
import of a row Explore first imported will not dispatch either, because the normalizer dispatches
only for a NEW or re-addressed row.

**Google 3D is browser-side, so a server budget cannot protect it — the renderer's structure
does.** `loadGoogleMaps()` is latched by a memoized promise: a second caller receives the same
promise, a caller arriving after it settles receives the settled one, and a pre-existing script
tag is adopted rather than duplicated. **A failed load stays failed** — the promise is never
cleared, so there is no accidental retry against a billed script, and the file contains no
`setInterval`, no `location.reload`, and exactly one `setTimeout` (the viewport debounce).
`buildMap()` is latched on `state.worldBuilt`, so there is one Map3DElement per page lifecycle:
driving moves that camera, and `renderMarkers()` updates the marker layer against it without ever
touching the world. Drive listeners bind to `document` and the map container — which outlive any
single world — so they are latched too, or every keystroke would double.

**`EXPLORE_GOOGLE_3D_ENABLED` defaults OFF, and a browser key alone turns nothing on.** Google
needs `EXPLORE_ENABLED`, this switch AND `EXPLORE_GOOGLE_MAPS_BROWSER_KEY`. Off means the loader
**never runs** and the credential is **not emitted into the HTML at all** — `ExploreController`
withholds it unless `isReady()`, because printing a live billable key into a page that is
deliberately not using it leaves it there for anyone to lift. Not "load Google and then hide the
map": the expensive provider stays untouched, or the switch protects nothing. It is also the
emergency stop — Explore keeps serving listings with no key deletion. Before it existed, deleting
the key was the only way to stop Google.

**The provider switches parse fail-safe, not with `(bool)`, which reads `off` and `no` as ON.**
`EXPLORE_GOOGLE_3D_ENABLED` and `EXPLORE_DISCOVERY_ENABLED` are ON only for `true`/`1`/`on`/`yes`;
unset, empty, `false`/`0`/`off`/`no` and any malformed value are OFF. `EXPLORE_PROVIDER_KILL_SWITCH`
fails the other way: untripped only for unset, empty, `false`/`0`/`off`/`no`, and TRIPPED for
`true`/`1`/`on`/`yes` **and any unrecognised value**. The runtime readers re-assert it
(`ExploreGoogleConfig::enabled()` is `=== true`, `ExploreProviderBudget::killed()` is `!== false`).
`EXPLORE_ENABLED`, `EXPLORE_VOW_ENABLED` and `EXPLORE_PROVIDER_BUDGET_ENABLED` keep the ordinary
cast: none of them can start provider traffic on its own, and a disabled budget guard refuses.
`ExploreProviderFlagParsingTest` evaluates `config/explore.php` against the values operators type.

**Superseded viewport fetches are aborted, not merely discarded.** The `seq` guard stopped a stale
response repainting the map; it did not stop the server answering it, and on a cold tile answering
it costs provider requests. An `AbortController` turns a rapid pan into one useful request instead
of a queue of them; the seq guard stays as the half that cannot be raced.

Telemetry is one `explore_provider` log line per decision (`ExploreProviderTelemetry`, shaped
after `CoordinateProviderTelemetry`): fetched / cache_hit / budget_blocked / provider_failure /
partial / disabled, with pages, records, the hashed actor and the spend. No MLS record, no
address, no credential, no raw IP.

**Before any public activation**, `docs/explore-google-cloud-launch-checklist.md` is the manual
Google Cloud work this repository cannot do: a dedicated browser key, referrer restrictions, API
restriction to Maps JavaScript alone, per-API quotas as the actual brake, and a billing budget
understood as an **alarm rather than a cap** — Google keeps serving past a budget unless a quota
stops it.

Eligibility still needs `raw_json` decoded per row (permissions and lease frequency exist only
there), so the repository overfetches, filters, then slices under a hard read ceiling — a bare
SQL `LIMIT` would silently shrink a page and look like a thinner neighbourhood.

**The Google credential is its own, permanently.** `GOOGLE_PLACES_API_KEY` is a SERVER key and
must never become a fallback — emitting it would publish a server credential to every visitor.
`EXPLORE_GOOGLE_MAPS_BROWSER_KEY` is absent by default, which is a **third state**: Explore on
with no map credential serves the page, answers the API, and states why the map is empty,
because a blank grey rectangle is indistinguishable from a bug and no PHP test can see one.
Only `maps3d` is requested; Places, Routes, Roads, Directions and Geocoding are asserted
absent from the shipped renderer. The renderer is a **static asset**, not a Mix bundle and not
part of `app.js`: it has no imports, so compiling it would buy nothing and couple `/explore`
to a build.

### AI DNA profiles (separate from Location DNA)

`PropertyDnaGenerator` and `BuyerTenantDnaGenerator` (in `app/Services/Dna/`) produce AI-generated personality/marketing profiles via the OpenAI client. These are unrelated to the geospatial Location DNA system despite the similar naming.

### Bridge API (MLS data)

`BridgeApiService` (`app/Services/Bridge/BridgeApiService.php`) fetches external MLS listings from the Bridge Data Output OData API. Credentials are `BRIDGE_DATASET` and `BRIDGE_SERVER_TOKEN` in `.env` (see `config/bridge.php`). `BuyerCriteriaODataFilterBuilder` and `TenantCriteriaODataFilterBuilder` (in `app/Services/Bridge/OData/`) translate search criteria objects into OData `$filter` strings.

### Accepted Bid Summary & PDF

When a bid is accepted, an `AcceptedBidSummary` row is created with `summary_html` containing `{{placeholder}}` tokens for signatures. `AcceptedBidSummaryService` performs placeholder replacement at render time. PDFs are generated on demand via `barryvdh/laravel-dompdf`. **Invalidate the cached PDF whenever bid terms change** — the service tracks this; do not bypass it.

### Display logic in config

Service order, compensation fields, and UI display decisions are driven by config files rather than hardcoded in views: `config/buyer_services_order.php`, `config/seller_services_order.php`, `config/landlord_services_order.php`, `config/tenant_services_order.php`, `config/agent_preset_compensation.php`. The `ListingDisplayHelper` and `OfferListingViewHelper` read these at render time.

### Feature flags

`config/bya_compatibility.php` has a **kill switch** (`BYA_COMPATIBILITY_KILL_SWITCH`, defaults `true` = all consumer-facing compatibility blocked) and a GA flag (`BYA_COMPATIBILITY_GA_ENABLED`, defaults `false`). Do not enable GA without coordinating with the owner.

### Hire Agent compatibility preferences — the allowlist boundary

`compatibility_preferences` is one EAV meta blob per listing, keyed `{role}_specific`. **Every write
goes through `CompatibilityPreferencePolicy::project()`** (`app/Support/HireAgent/`), which rebuilds
the sub-array from the allowlist in `config/hire_agent_compatibility_keys.php` — that config has one
reader and a test asserts it.

**Validation cannot do this job, and assuming it could is what made the policy necessary.**
`$compatibility_preferences` is a public Livewire property, so a client can set any nested path;
`validate()` checks the keys named in `rules()` and leaves the rest on the property; the persist then
wrote the sub-array verbatim. A `prohibited` rule narrows only the paths that reach full validation,
and a draft save does not. So the gate is at the write, it is an **intersection** (a key survives by
being named, never by escaping a deny-list), and it covers Create, Save Draft, Save Edit and the
three still-routed legacy per-role create components.

**Landlord keys are scoped by property type.** `preferred_business_use` /
`preferred_business_use_other` are commercial-only. Anything that is not exactly
`Commercial Property` — null, `''`, a legacy spelling — is treated as residential, because
`property_type` is EAV and can be absent on an older row. **On Edit the STORED property type governs**
(`propertyTypeForProjection()`): one request can otherwise flip the listing commercial and supply the
commercial-only key in the same message.

**Retired for Fair Housing reasons, and not to be reintroduced:**
`tenant_type_preference` / `tenant_type_preference_other` (mixed occupant categories — Individual /
Family, Young Professionals, Students — with business ones, rendered on residential and commercial
listings alike, and published on a route with no auth middleware). Residential has **no** replacement
occupant question. Commercial answers **Preferred Business Use** instead; its options live in
`config/landlord_business_use_options.php`. Landlord `risk_tolerance` became
`applicant_screening_approach` (method, not tolerance) and is `informational_context` only — never a
trait slot, because a slot is what a future scorer reads. Buyer `risk_tolerance` is unrelated and
stays. `HireAgentFairHousingWordingTest` guards the wording and the keys at source.

`php artisan hireagent:retire-tenant-type` remediates stored values. It is a command rather than a
migration because nothing schema-shaped changes and `deploy/start-production.sh` is the single
migration owner. **Not yet run against any database, and running `--write` against one requires
separate explicit approval.**

It runs in **two phases, and the order is the safety property**. *Phase A* plans: it scans every
candidate row and computes each affected listing's exact original and remediated blob, writing
nothing in either mode — the default invocation is Phase A plus a report, and creates no backup row,
no rollback record and no timestamp change. *Phase B* is reached only under `--write`: every affected
listing's original is persisted, **then every backup is read back out of the database and
checksum-verified, and only then does the first compatibility blob change.** Any failure in backup or
verification aborts with `FAILURE` having performed zero remediation writes.

**The rollback record lives in the database, not the filesystem** — one row per listing in
`landlord_agent_auction_metas` under `fair_housing_backup_compatibility_preferences`, holding the
original bytes, a SHA-256 over them and the run id. `storage/app` was the wrong home: the Replit
container is rebuilt from the image on deploy and on restart, `storage/` has no persistent mount and
the file is not in git, so the undo evaporated while the deletion stayed. **Nothing at runtime
resolves that key** — every meta consumer reads a named key, `$auction->get->namedKey`, or an
explicit field whitelist (`LandlordFieldMap::sections()`, Ask AI's `CANONICAL_SOURCE_MAP`), so the
row is inert to the application. It is **written once per listing and never overwritten**, so the
path back to the value the landlord actually submitted survives any re-dirty / re-remediate cycle.

`--list-backups` (read-only) lists the restorable runs; `--restore=RUN_ID` undoes one **named** run —
no default, no "latest", and one unreadable envelope anywhere refuses the whole restore, because an
envelope that cannot be decoded is one whose run cannot be ruled out. Restore is idempotent and
leaves the backup rows in place.

**Malformed rows are skipped, never repaired, and now counted.** A blob that is not valid JSON and a
`landlord_specific` that is present but not an object are reported separately, and the run ends with
an explicit `REMEDIATION INCOMPLETE` warning — those listings may still hold retired values. A
`landlord_specific` that is simply *absent* is normal and is not counted.

**Detail-page visibility.** Representation rows are built in two buckets: `$repAdd()` is public,
`$repAddOwn()` is owner-only (free text, screening posture, and the seller's own motivation and price
firmness). The gate is `$hlaViewerIsOwner`, resolved in the four controllers from
`HireAgentProposalAccess::isListingOwner()` — **the ownership relationship, not the audience tier**,
because `audienceFor()` resolves widest-match-first and would otherwise hide an owner's own answers
from them whenever that owner also holds an agent account.

### Landlord applicant screening — the second write boundary (Fair Housing Phase 2)

The landlord **Applicant Requirements** tab has its own allowlist, separate from the Hire Agent
one and for the same reason. Every screening key is a public Livewire property that `saveMeta()`
wrote verbatim, and the audit found **no validation rule referencing any of them** — so deleting
an `<option>` changed the form and nothing else.

`config/landlord_screening_options.php` is the SSOT. It has exactly two readers: the Blade
partial that renders the options and `LandlordScreeningPolicy` (`app/Support/OfferListing/`)
that enforces them on the write; a test asserts both. Every governed write on Create **and**
Edit goes through `LandlordScreeningPolicy::project()`, which is an **intersection** — a value
survives by being named in the allowlist, never by escaping a deny-list.

**One partial serves Create and Edit** (`applicant-requirements.blade.php`), so option lists
cannot drift between the two wizards. That is structural, and a test pins it.

**The policy must not depend on a booted container.** It is called from Blade, from both Livewire
components, and from the Ask AI landlord extractor — and that extractor's unit test extends
PHPUnit's `TestCase` directly with no application. A `config()` call there raises,
`AskAiContextBuilderService::buildForListing()` catches every `Throwable`, and the symptom is not
a missing screening key but an **entirely empty listing context**, with the real error swallowed
several frames away. `LandlordScreeningPolicy::conf()` uses the container when one is bound and
reads the file when it is not; a test runs the policy in a separate process to prove it.

**Retired for Fair Housing reasons, and not to be reintroduced:** `employment_requirement`,
`custom_employment_requirement`, `employment_verification_requirement`. They required an
employment *status* ("Employed", "Retired allowed", "Student allowed"), which gates tenancy on
how income is earned rather than whether rent can be paid. **There is no replacement control** —
`income_qualification_method`, `min_income_requirement` / `min_monthly_income_fixed` and
`income_verification_requirement` (relabelled *Income documentation required*) already ask the
objective question, and the income block carries fixed copy stating that all lawful verifiable
income counts. Do not add an "accepted income sources" checklist: a landlord who ticks everything
except benefit letters has rebuilt source-of-income exclusion inside a field that looks neutral.

**Stale values are suppressed or normalized, and the difference is deliberate.** A blanket
`No criminal background` is **suppressed** — rendering it as `Individualized review of
convictions` would credit a listing with a process it never had. `Case-by-case review` is
**normalized** forward, because the meaning survives the rename. `Compensating factors
considered` normalizes to the generic documented-criteria wording, **not** to the new deposit /
co-signer option, which would assert a remedy the landlord never chose. No rows are deleted;
remediation is a later backup-first operation.

**The rental qualification page is a second publication of these values** on a route with **no
auth middleware** (`offer.listing.landlord.qualification.check`). It reads landlord policy to
show applicants what is required — the safe direction — but it must resolve through
`LandlordScreeningPolicy::displayValue()`, or a retired requirement stays published there after
the field that set it is gone.

**`criminal_background` is not `criminal_background_requirement`.** The first is what an
*applicant* discloses about themselves on the legacy qualification and offer-terms forms; the
second is what a *landlord* requires. They share the option string `No criminal background` and
are one word apart. `LegacyApplicantDisclosureContainmentTest` fails if anything ever aliases one
onto the other. The legacy applicant *controls* are deferred work and were not changed.

### Landlord provider-authored free text — the third write boundary (Fair Housing Phase 3)

Phase 2 closed the screening **dropdowns**. Phase 3 closes the **prose** beside them and the
custom-text inputs the audit found with the same shape and none of the protection.

`config/landlord_provider_text.php` is the SSOT and `LandlordProviderTextPolicy`
(`app/Support/OfferListing/`) is the boundary. Three landlord fields are governed:
`landlord_approval_conditions`, `pet_restrictions`, `additional_details` — all three were
written verbatim by `saveMeta()` with **no validation rule anywhere**, and all three render on
two routes with no auth middleware and reach Ask AI.

**Patterns match an exclusion STRUCTURE, never a word, and that is the whole design.** The same
nouns appear in the sentences most worth keeping: *"Property is wheelchair accessible with a
zero-step entry"* and *"Two most recent pay stubs or benefit award letter"* must pass while
*"No wheelchair users"* and *"No housing vouchers"* must not. A vocabulary filter cannot tell
those apart, so a rule that fires on either kept example is wrong by construction. Categories:
protected-class preference, disability exclusion, assistance-animal-as-pet, source-of-income
exclusion, steering.

**Authorship is part of the rule, enforced structurally.** There is no "moderate this string"
entry point — every method takes a FIELD KEY, and only landlord provider fields are named. An
unknown key is `allowed` and untouched, so pointing the policy at consumer text does nothing.
That matters because provider and consumer text meet in `AskAiContextBuilderService`, and the
identical words mean opposite things by author: a landlord's *"No emotional support animals"* is
an exclusion; a tenant's *"I have an emotional support animal"* is a lawful first-person
disclosure that Phase 1 already keeps private.

**Nothing is ever rewritten.** `decide()` returns a verdict, not a cleaned string, and there is
no redaction path. **Save Draft preserves the landlord's exact prose** so they can revise it;
**Submit/Publish refuses**, with the error attached to the field and naming the offending phrase.
The gate runs **before** the required-field validation, because both throw `ValidationException`
into the same catch and a listing that is merely incomplete would otherwise never reach it.
`addError()` is called before throwing, since `store()`/`update()` turn that exception into a
flash banner and a bare throw would tell the landlord nothing about which field to edit.

**Suppression is read-time and shared.** `LandlordProviderTextPolicy::displayValue()` returns
null for unsafe prose, and the landlord public view, both qualification pages and the Ask AI
context builder all resolve through it — the views via the one `$str` helper every row already
uses, rather than at each call site. That is deliberate: editing call sites is how Phase 2's
review page ended up scoring applicants against a criterion the listing page had stopped showing.
**Historical prose becomes inert with no remediation pass and no changed bytes.**

**Five custom inputs had no parent gate at all** (`custom_credit_score_requirement`,
`custom_income_requirement`, `custom_smoking_policy_requirement`, `custom_reference_requirement`,
`custom_preferred_move_in_timeframe`), plus `min_monthly_income_fixed`. They were rendered under
an Alpine `x-show`, which is a CSS decision in the browser and not a write boundary. They now go
through `LandlordScreeningPolicy::projectCustomFields()` against `custom_fields` in
`config/landlord_screening_options.php`. **The trigger is read from config, never assumed to be
`Other`** — `min_monthly_income_fixed` unlocks on `Fixed Monthly Income`, and hard-coding `Other`
would have silently discarded every landlord's fixed income amount on the next save.

**The tenant "Additional Information" section was a deny-list** — "any populated key not in this
list will appear" — on a route with no auth middleware, so PUBLIC was the default disposition of
every tenant meta key and staying private depended on someone remembering to add it to a
200-entry Blade array. It is now an allowlist, `config/tenant_public_overflow_keys.php`, which
**ships empty**: the section was never a designed surface, everything the page means to show has
a named section above it, and seeding a list by guessing which consumer answers are safe to
broadcast is the mistake being fixed. Adding a key is a reviewable edit plus a test.

**`tenant_require` holds a FURNISHINGS value** ("Furnished", "Unfurnished", "Turnkey"). The
landlord public view published it as **"Tenant Type Required"** and the agent view as "Tenant
Requirements" — announcing an occupant-category requirement the listing never made, which is the
concept Phase 1 retired as `tenant_type_preference`. Both are relabelled **"Furnishings"**. The
meta key is deliberately **not** renamed: that would be a data migration for a copy defect. The
tenant view's own "Tenant Requirements" row is a different key on a different table and is
correct as-is.

**A fixed line of copy sits by the pet policy**: *Assistance animals are accommodation requests
and are not governed by ordinary pet restrictions.* It is informational, has no control and no
stored value. The retired landlord Yes/No assistance-animal fields are **not** reintroduced —
asking a landlord to pre-declare a policy invites a blanket answer to an individualised request.

**Deferred, deliberately:** `custom_pet_policy_requirement` (its parent is a multi-select, so the
unlocking value is ambiguous and guessing it would drop stored text), the lease/commercial prose
set, and any historical remediation command.

### Deployment & migrations

**`deploy/start-production.sh` is the only thing that runs migrations.** The Replit `[deployment] run` command invokes it; it reports via `deploy:preflight`, then runs `php artisan migrate --force`, then serves — and a failed migration stops the deploy rather than serving against an old schema.

Nothing else may migrate: not `deploy/scheduler.sh`, not the build phase, not a second web process. This app is on **Laravel 8, which has no `migrate --isolated`**, so there is no migration lock and concurrency safety rests entirely on single ownership. `DeploymentMigrationReadinessTest` asserts all of it.

`scripts/post-merge.sh` also migrates, but it is the Replit **workspace** `[postMerge]` hook — it does not fire on deploy or on a GitHub merge. Do not treat it as the deployment's migration step; that assumption is exactly how G4's migration reached `main` and never reached a schema.

Two CI gates: `migration-tests.yml` (`migrate:fresh`, empty DB) and `incremental-migration-tests.yml` (previous-release schema, populated, migrated forward — the operation a deploy actually performs).

**`deploy/start-production.sh` also gates on the required product flags, and that gate can fail a deploy.** `php artisan deploy:require-flags` runs after `deploy:preflight` and **before `migrate`** — with no `|| true`, so `set -euo pipefail` stops the deploy before any schema change and long before a port is bound. The contract is `config/required_production_flags.php`: the Hire Agent hero and detail redesigns enabled for all four roles, and both MLS direct-import surfaces enabled.

**The defaults and the gate solve different halves of the same problem.** The shipped config defaults (now `true` / all four roles) cover the **absent** variable — an environment that supplies nothing serves the modern platform. The gate covers the variable that is **present and wrong**: a stale secret, a typo, a value left behind by a finished pilot. That failure is the invisible one — the app boots, answers 200, passes its health check, and serves the superseded surface until somebody notices. It is exactly what happened when these six values lived only in a machine-local `.env` that a container rebuild discarded.

**The contract may never name a safety switch.** BYA compatibility (kill switch and GA), every Location DNA gate, the Census geocoder, the address-point corpus, MLS Match Check, DNA score generation, Matching V2 persistence and the Bridge credentials are all excluded, and `RequiredProductionFlagsTest` asserts the exclusion rather than leaving it to reviewer memory — otherwise the gate would become a deploy-time mechanism for enabling a consumer-facing or spend-incurring feature, decided in a file nobody reads during a rollout conversation. The command is **read-only in both directions**: it compares and reports, so a wrong value stops a deploy and is never silently corrected.

Role lists are compared as a **subset**, so adding a fifth role later cannot fail a deployment for being extra. An **empty or unreadable contract fails closed** — a config file that did not load is indistinguishable from one that requires nothing, and that ambiguity is not safe to resolve as "pass". `REQUIRED_PRODUCTION_FLAGS_ENFORCED=false` downgrades it to a loud warning.

`deploy/start-serving.sh` (the Replit **workspace** workflow) calls the same command as `deploy:require-flags --report || true` — **informational only, and it can refuse nothing**. `--report` exits 0 whatever it finds and `|| true` catches even an unexpected fault, because turning a flag off locally to test the legacy path is legitimate work and a visibility line must never cost somebody their local server.

Version-controlled defaults are proven by `RequiredProductionDefaultsTest`, which evaluates the config files in a child process with all six variables **removed** — the container-rebuild scenario reproduced rather than described.

`ProvenanceSchemaReadiness` is the runtime backstop: when the provenance columns are absent, coordinate writes proceed and provenance is skipped with `schema_not_ready` rather than raising `SQLSTATE[42703]` inside a listing save. See `deploy/DEPLOYMENT.md`, which also documents an **open question about `APP_DEBUG` in deployments**.

**`PHP_INI_SCAN_DIR` must be added to, never assigned.** All three entrypoints (`start-production.sh`, `start-serving.sh`, `scheduler.sh`) apply `deploy/php/uploads.ini` through `configure_php_ini_scan_dir` in `deploy/lib/php-runtime.sh`, which resolves the interpreter's own scan directory at run time and prepends it. A bare `export PHP_INI_SCAN_DIR="$PWD/deploy/php"` **replaces** that directory — on this Nix build it is where every extension is declared, so the assignment delivered the upload limits and silently unloaded PDO, pdo_pgsql and tokenizer, taking production from 54 extensions to 12. The symptom was `deploy:migrations-pending` reporting `error=repository_unreadable` and the restart correctly refusing to serve, against a database that was entirely healthy. The documented `":$dir"` shorthand does **not** work here (`PHP_CONFIG_FILE_SCAN_DIR` is defined but empty on this build), and the resolved path must never be hardcoded — it is a store path whose hash moves with the Nix channel. `PhpIniScanDirTest` runs the real scripts and proves the resulting runtime keeps its extensions *and* its raised limits.

## Frozen / legacy code

**`initializeLimitedService()`** — present in all four Create Offer Listing Blade files (seller, buyer, landlord, tenant). This function is **frozen legacy code for the Limited Service flow**. Never modify, test, or clean up anything inside it. All validation cleanup applies only to the Full Service scope, never inside this function.

**`TenantAgentAuction` Livewire component** — predates the `HasListingLifecycle` engine and is intentionally excluded from the shared trait. Do not attempt to refactor it to use the trait.

## Key `.env` variables

Beyond standard Laravel keys, this app requires:

| Key | Purpose |
|-----|---------|
| `BRIDGE_DATASET` | Bridge Data Output dataset ID |
| `BRIDGE_SERVER_TOKEN` | Bridge API access token |
| `GOOGLE_PLACES_API_KEY` | Address validation + POI lookup |
| `OPENAI_API_KEY` | DNA profile generation |
| `BYA_COMPATIBILITY_KILL_SWITCH` | Consumer compatibility gate (default `true` = blocked) |
| `BYA_COMPATIBILITY_GA_ENABLED` | GA rollout flag (default `false`) |
| `DNA_SCORES_GENERATION_ENABLED` | Master gate for production `dna_scores` generation via the lifecycle (observers + `ComputeLocationDna` chain + `dna:generate-scores`). Default `false` = inert. Independent of Matching V2. |
| `MATCHING_V2_PERSISTENCE_ENABLED` | Matching V2 C7 persistence gate (materialize ranked results into `matching_v2_*`). Default `false`. A write also requires `MATCHING_V2_ENABLED` and a non-production environment — `MatchResultPersister` hard-refuses in production. |
| `MATCHING_V2_PERSISTENCE_VERSION` | Materialization version tag stamped on persisted runs; the reader trusts only rows at the current value (read-time re-gate). Default `c7-v1`. |
| `HIRE_AGENT_HERO_REDESIGN_ENABLED` | Master gate for the redesigned Hire Agent hero (M4). **Default `true` as of the required-modern-platform-defaults change** — it shipped `false` so the merge was inert, and that pilot is over. A `false` default now describes a regression rather than an inert merge: it is what let a container rebuild, which discarded the machine-local `.env` these values lived in, silently restore the superseded hero. Read only via `HireAgentHeroData::redesignEnabledFor()`. Set to `false` to roll back — still an environment change, not a revert. Required to be `true` in production by `config/required_production_flags.php`. Manual visual verification remains the prerequisite before changing the hero itself; there is no automated browser coverage. |
| `HIRE_AGENT_HERO_REDESIGN_ROLES` | Comma-separated roles the redesign applies to while enabled. **Default `seller,buyer,landlord,tenant`** (was `landlord`, the pilot). Independent of the master switch — both must agree. All four is the default because a partial list produces a visibly mixed platform, which reads as a rendering bug rather than as a missing variable. Narrowing it is a rollout decision, not a code change. The production contract requires all four to be present, as a **subset** test, so adding a fifth role later does not fail a deploy. |
| `HIRE_AGENT_DETAIL_REDESIGN_ENABLED` | Master gate for the redesigned Hire Agent listing **detail page** (M5) — section navigation, quick actions, sidebar, cards, photo gallery. **Default `true`**, for the same reason as the hero flag above. **Still independent of the hero flag on purpose**: the two moved together once, which is a fact about today's values, not a merger of the switches. Gating is read only via `HireAgentDetailRedesign::enabledFor($role)` — no view may gate on the master switch, because the page body and the shared shell disagreeing is what once let the body render redesign markup without the stylesheet that lays it out. `enabled()` still answers the master switch alone and is not a gate. The reader keeps its own `false` fallback for a **missing** key — a config that failed to load must still read as off, which is a different question from the default. Pairs with `HIRE_AGENT_DETAIL_REDESIGN_ROLES`; both must agree. Required `true` in production. |
| `HIRE_AGENT_DETAIL_REDESIGN_ROLES` | Comma-separated roles the detail redesign applies to while enabled. **Default `seller,buyer,landlord,tenant`** (was `landlord`, the pilot). Independent of the master switch — both must agree, and this list is the only thing that grants a role the redesign. Mirrors `HIRE_AGENT_HERO_REDESIGN_ROLES`; added in M7.1 when page layout moved into the shared shell all four roles render, so "which files exist" stopped being able to scope the pilot. Required in production as a subset. |
| `CENSUS_GEOCODER_ENABLED` | Master gate for `CensusGeocoderAdapter` (G3) — the first non-Google coordinate provider. Default `false` = the adapter reports itself unavailable and is skipped without being called. **This flag carries more weight than the other gates in this table**: the US Census Geocoder needs no API key, so the missing credential that normally keeps an unfinished integration quiet does not exist here. Nothing else stands between the adapter and an outbound request. As of G3 the adapter is on no ladder, bound in no container and referenced by no flow, so enabling it changes nothing — assembling a ladder that includes it is G4/G5. |
| `CENSUS_GEOCODER_BENCHMARK` | Which vintage of the Census address-range corpus to match against. Default `Public_AR_Current`. Pinned explicitly rather than relying on the service default so a change on the Census side arrives as a config diff instead of as coordinates that quietly moved. Valid values come from `/geocoder/benchmarks?format=json`; an unrecognised one is rejected with HTTP 400 and surfaces as a provider fault, not as "this address does not exist". |
| `CENSUS_GEOCODER_TIMEOUT` / `CENSUS_GEOCODER_CACHE_TTL` / `CENSUS_GEOCODER_MAX_ADDRESS_LENGTH` | Request ceiling (default 10s), cache lifetime (default 30 days, keyed on the unit-free lookup line so every unit in a building shares one call), and the service's own 100-character address limit mirrored locally so an over-long address is declined before a request is spent on it. |
| `CENSUS_GEOCODER_HOURLY_CAP` / `CENSUS_GEOCODER_DAILY_CAP` | Request ceilings (G4), defaults 500/hour and 5,000/day. **Deliberately independent of price.** Census is free and publishes no rate limit, which is exactly why these exist: an observer firing per save or a page resolving per render turns one user action into thousands of requests, and against a free provider that produces no bill and no signal until the Bureau stops answering us. These are a backstop against a bug, not a capacity plan — raise them with evidence from telemetry. `null` disables a ceiling; prefer a high number, since a ceiling you can see in config beats one that is absent. Note `(int) null` is `0`, which would block everything — `config/census_geocoder.php` guards that explicitly. |
| `CENSUS_GEOCODER_BREAKER_THRESHOLD` / `_COOLDOWN` / `_WINDOW` | Circuit breaker (G4): 5 faults inside 600s opens the circuit for 300s, during which nothing is sent. Only genuine provider faults count — a no-match is the provider working correctly, and a rate-limit block is our own decision (counting it would let the breaker trip on our own rationing and stay open blaming Census). **Local rungs are never affected**: an open circuit must not stop a coordinate we already hold from being returned. |
| `CENSUS_GEOCODER_AMBIGUOUS_CACHE_TTL` | How long an ambiguous match is remembered (default 1 day, vs 30 for a clean hit or miss). Ambiguity is deterministic, so re-asking every render wastes budget — but it is usually the symptom of a thin address rather than a property of the world, so it expires sooner and a corrected ZIP is picked up the next day without anyone needing to know a cache exists. |
| `MLS_DIRECT_IMPORT_PREFILL_ENABLED` | Master gate for the Seller/Landlord **"Import by MLS #"** entry point on Create Offer Listing — the Bridge OData lookup that turns an MLS number into a facts-only prefill. **Default `true`** (was `false`): the owner has enabled it and it is verified in production, and the off default has since done the only harm it can do — removing a working entry point from the form with no error anywhere, indistinguishable from the feature having been withdrawn. `false` = inert: the input is not rendered and `HasMlsImport::importListingByMlsNumber()` returns early, so a hidden input or a hand-crafted Livewire call lands on the same answer as the UI. Read via `mlsNumberImportAvailable()`, which requires both this flag **and** a role in `mls_direct_import.prefill_roles` (`seller`, `landlord`). That role list is not a rollout dial — Buyer/Tenant listings describe search criteria across many areas rather than one property, so there is nothing to prefill. **Does not gate the pre-existing URL / raw-text importer** (`MlsListingImportService`), which is not a Bridge feature and keeps working regardless. **Not the Match Check flag either**: `mls_match_check.enabled` gates a Buyer/Tenant scoring page that never writes a form, while this gates a Seller/Landlord write path into a listing. **This default does not supply credentials** — Bridge credentials are still required (see `config/bridge.php`); with the flag on and credentials absent the lookup reports "MLS data service unavailable" rather than "listing not found". Required `true` in production. |
| `MLS_DIRECT_IMPORT_QUICK_IMPORT_ENABLED` | Master gate for the shortened Seller/Landlord MLS quick-import path: enter an MLS #, have the property portion of the listing built for you, answer only the BidYourOffer transaction questions, review, publish. **Default `true`** (was `false`). **Separate from `MLS_DIRECT_IMPORT_PREFILL_ENABLED` and still an additional gate, never a replacement one** — both must be on for the flow to be reachable, because prefill adds an input to a form the user is already filling in while this adds a whole creation path that writes a draft listing. Role scope is `mls_direct_import.prefill_roles`, one list, so the two surfaces cannot drift about which roles the feature exists for. Deliberately **not** tied to `config/mls_media.php`: the flow's promise is delivered by the facts alone, which is what lets the media licence be settled on its own timetable. Required `true` in production. |
| `REQUIRED_PRODUCTION_FLAGS_ENFORCED` | Whether `deploy:require-flags` **gates** a production start or merely warns. Default `true`. See the Deployment & migrations section above — this is the escape hatch, not a rollout dial, and the command announces in capitals when it is taken. Setting it `false` does not change any product flag; it only stops the gate refusing. |
| `ADDRESS_POINT_CORPUS_ENABLED` | Master gate for `AddressPointCoordinateAdapter`, the ladder rung that reads our own address-point corpus. Default `false`. Off is not a placeholder: the corpus holds **zero rows** and no importer exists, so an enabled rung would spend a query per resolution to return `address_point_not_found` forever. Turn it on only after an import has been loaded and verified. Unlike the Census flag, an enabled rung here cannot reach the network — the worst case is a wasted local query. |
| `ADDRESS_POINT_CORPUS_VERSION` | Which `corpus_version` the rung reads. **Both this and the flag must be set** — an enabled rung with no version pinned reports itself unavailable rather than guessing which import to serve. Deliberately not "whatever the ledger says is active": two corpus versions coexisting is what makes a new import verifiable before it is trusted, and a rung that followed activation would start serving new coordinates the instant a ledger row flipped, with no deploy and no diff. |
| `ADDRESS_POINT_CORPUS_MAX_MATCHES` | How many corpus rows one lookup line may pull back (default 25). Rows sharing a normalized line are units of one building; a handful settles whether they agree on a point. A zero or negative value falls back to the default rather than silencing the rung. |
| `OVERTURE_CORPUS_POI_ENABLED` | Master gate for `OvertureCorpusPoiAdapter`, the local Overture Places corpus. Default `false`. The **licensing** prerequisite is now met — the verbatim Foursquare NOTICE, the Apache-2.0 text and our notice of changes are committed and served — but that cleared one blocker, not the gate: activation is a separate, reviewed decision and this ships off. Both this and the registry's `location_providers.providers.overture_corpus.enabled` must agree — two gates, two files, neither redundant. `OvertureActivationReadinessTest` asserts both are off **and** that satisfying the NOTICE did not move either. Changing this alongside `OVERTURE_CORPUS_POI_VERSION` rotates the POI tile keys and every row's `pois_fetch_version` (see `CorpusSurface`). |
| `OVERTURE_CORPUS_POI_VERSION` | Which `corpus_version` the adapter reads, pinned explicitly rather than following the activation ledger — two corpus versions coexisting is what lets a new import be verified before it is trusted. Default unpinned; an enabled adapter with no version reports itself unavailable rather than guessing. **Changing this rotates every POI tile cache key** (`LocationDnaPoiTileCache::$corpusToken`), which is the point: before that token existed, re-pinning served the previous corpus's cached candidates under the new pin for the tile TTL. |
| `LOCATION_DNA_FLOOD_ZONE_MAX_AREA` | FEMA API bounding-box threshold in sq-degrees |
| `CRITERIA_LDNA_GEOGRAPHY_SOURCE` | Which `CriteriaGeographyRepository` backs the geography cascade. **Exactly three values are accepted** — `eloquent` (default; the `us_*` reference tables), `census` (the `census_*` corpus from `census:import-geography`), `fake` (in-memory fixture, local/demo only). **Anything else throws at container resolution.** That is deliberate: the binding used to fall through to `eloquent`, so a typo silently served legacy data and looked exactly like success. Selecting `census` requires the corpus to be present — run `php artisan census:verify-geography` first, and in the deploy sequence of any environment using it, or every tier enumerates empty with no error. |
| `CRITERIA_LDNA_PREVIEW_ENABLED` | Geography preview surface. Default `false`. |
| `OFFER_PLAYOFF_ALLOWED_IDS` | Comma-separated user IDs or `*` for all |
| `EXPLORE_ENABLED` | Master gate for BidYourOffer Explore (`/explore` plus `/api/explore/listings*`). Default `false` = every route 404s, data endpoints included, so the feature is invisible rather than advertised. Mirrors `CheckMatchCheckEnabled`. Fails closed: a config that did not load reads as off. Says nothing about VOW. |
| `EXPLORE_VOW_ENABLED` | The Property Intelligence / VOW tier. Default `false`, **and setting it `true` grants nothing** — `VowAvailability` refuses in code, and a test asserts the flag cannot move the tier. It exists so the posture can be reported, not as the gate: no VOW approval, dataset, credential, registration flow or feed field exists in this application. Activation requirements are in `VowAvailability::activationRequirements()` and `docs/bidyouroffer-explore-audit-2026-09-10.md`. |
| `EXPLORE_GOOGLE_MAPS_BROWSER_KEY` | Browser key for the Maps JavaScript API `maps3d` renderer. **Not `GOOGLE_PLACES_API_KEY`**, which is a server key for address validation and POI lookup and must never be emitted into a page or used as a fallback here. Absent by default — that is a distinct third state from "Explore off": the route serves, the API answers, and the map area states why it is empty, because a blank grey rectangle is indistinguishable from a bug. With no key the page issues **zero** Google requests. |
| `EXPLORE_GOOGLE_MAPS_MAP_ID` / `EXPLORE_GOOGLE_MAPS_VERSION` | Optional styled Map ID (photorealistic tiles render without one) and the API version channel (default `alpha`, which is where `Map3DElement` currently lives). |
| `EXPLORE_DEFAULT_LAT` / `_LNG` / `_ALTITUDE` / `_TILT` / `_HEADING` / `_RANGE` | The opening camera. Defaults to St. Petersburg / Tampa Bay because that is where this dataset's 1,225 cached records actually are; opening anywhere else shows an empty neighbourhood, which reads as a broken feature rather than an empty market. |
| `EXPLORE_DISCOVERY_ENABLED` | Whether an Explore viewport may ask the provider for the CURRENT eligible listings in that area, through `LazyBridgeImportService` — the one existing MLS ingestion pipeline. Default `false`, the same posture as `MLS_SYNC_ENABLED` and for the same reason: merging and activating are two decisions. **Off is not merely quieter, it is less complete** — Explore then renders only what some earlier workflow happened to import, which is a statement about our cache, so the response labels itself `discovery.status = "disabled"` rather than letting a thin result read as a thin market. On, a request costs at most one provider pass per transaction type per viewport, free while the tile's fetch cache is warm, every page admitted against the provider budget before it is sent — and it never dispatches Location DNA. Parsed strictly: ON only for `true`/`1`/`on`/`yes`; `off`, `no` and any malformed value are OFF. |
| `EXPLORE_DISCOVERY_TILE_DEGREES` | Grid (default `0.05°`, ~5.5 km) the discovery bbox is snapped **outwards** to before it is hashed into a fetch-cache key. **This is what makes cache reuse real**: unsnapped, the key changes with every pixel of pan and every camera nudge becomes a provider request. Outwards, never nearest, so the box always contains the viewport — otherwise a property at the screen edge would be rendered from an area discovery never asked about. |
| `EXPLORE_DISCOVERY_MAX_PAGES` / `EXPLORE_DISCOVERY_MAX_RECORDS` | Per-pass pagination ceilings (default 5 × 500), **clamped downwards** against the global `BRIDGE_LAZY_*` envelope by the importer — a call site may lower a spend limit, never raise one. Lower than the criteria-search defaults because this runs while somebody is moving a camera rather than on a results page they are waiting for. Hitting a ceiling makes the pass *partial*, which suppresses the withhold-unconfirmed-rows rule: absence would then mean "we stopped asking", not "it is gone". |
| `EXPLORE_PROVIDER_BUDGET_ENABLED` | The provider-spend guard. Default `true`. **Switching it off does not unleash traffic** — `ExploreProviderBudget` reads a disabled guard as "do not call the provider", so it is a second way to stop spending and never a way to start it. All accounting is the existing `ProviderRequestBudget`; no second budget system exists, and a test asserts the guard contains no counters of its own. |
| `EXPLORE_PROVIDER_KILL_SWITCH` | Emergency stop for Explore's outbound Stellar/Bridge traffic (discovery pages and the panel lookup), leaving Explore and the rest of the application serving. Default `false`. **Fails safe**: tripped by `true`/`1`/`on`/`yes` and by ANY unrecognised value; untripped only for unset, empty, `false`/`0`/`off`/`no`. Distinct from `EXPLORE_DISCOVERY_ENABLED` only in intent: that is the feature gate, this is the thing you set at 2am. Exhaustion degrades — last-known rows stay on the map and the response is marked `degraded` — it never reports an empty market. |
| `EXPLORE_PROVIDER_GLOBAL_HOURLY` / `_GLOBAL_DAILY` | HARD ceiling across every caller (300 / 2,000), in outbound Bridge HTTP requests, each admitted before it is sent. The bill's backstop, and the ceiling that catches what no per-caller limit can see — many actors, or one rotating addresses. Conservative for a controlled launch; an application-side ceiling, not a statement of Stellar's allowance, which is unknown here. Raise only from `explore_provider` telemetry. |
| `EXPLORE_PROVIDER_ACTOR_HOURLY` / `_ACTOR_DAILY` | HARD ceiling per actor (60 / 300), where the actor is `user id, else IP` — the identity every throttled route here already uses, hashed before it becomes a cache key. Nothing new is fingerprinted. Bounds one browser traversing unlimited distinct cold tiles, which `throttle:120,1` cannot: that limits requests, not provider spend. **No value means "unlimited"** — zero, negative or non-numeric falls back to the shipped default. |
| `EXPLORE_GOOGLE_3D_ENABLED` | The 3D renderer's own switch. **Default OFF**, and a browser key alone turns nothing on: Google needs `EXPLORE_ENABLED`, this switch and `EXPLORE_GOOGLE_MAPS_BROWSER_KEY`. ON only for `true`/`1`/`on`/`yes`; unset, empty, `false`/`0`/`off`/`no` and any malformed value are OFF. **Off means the loader never runs**: no `<script>`, no contact with `maps.googleapis.com`, and the browser credential is not emitted into the HTML at all. Also the emergency stop for Google — no key deletion needed. |
| `EXPLORE_MAX_RESULTS` / `EXPLORE_MAX_SPAN_DEGREES` | Page size (default 150, hard ceiling 250) and the bounding-box span ceiling (default 1.0°). An over-large bbox is **refused with a 422, never clamped** — a clamped box returns markers for somewhere the consumer is not looking, and the thinner result reads as "nothing for sale here", which is a false statement about a real market. |

`.env` is not tracked in git — back it up separately.
