# Phase 4 (Buyer/Tenant Match Check) — Pre-Implementation Design Recommendations

> Status: **Investigation / design only — no code changed, nothing staged or committed.**
> Date: 2026-07-05. Author: pre-implementation review while Matching V2 work is isolated.
> Companion to `docs/mls-direct-import-design-and-plan.md` (the locked design). This doc does
> **not** override the locked decisions — it surfaces opportunities and open questions to settle
> *before* Phase 4 coding begins.

## How to read this

Each item is **Finding → Recommendation → Decision/tradeoff**. Items tagged **[DECISION]** need
an owner call before implementation. Items tagged **[LEAN]** are recommendations I'd default to
unless you object. Grouped by: Architecture · Scoring explanations · UX · Scalability/cost ·
Compliance.

The ground truth below was verified by reading the actual classes (not the doc): the property-match
pipeline is `app/Services/Stellar/…` and is **Buyer-named but role-shared** — there is no separate
Tenant scorer; tenant matching reuses `BuyerMatchScorer → BuyerMatchResultBuilder →
BuyerResultViewMapper`, differing only in which loader fills the shared `BuyerCriteriaPayload`.

---

## A. Architecture

### A1 — Reconsider "wire scorer to PropertyCandidate" as Phase 4 scope **[DECISION]**

**Finding.** The locked doc calls the scorer↔`BridgeProperty` coupling "the one real refactor" and
puts "wire scorer to `PropertyCandidate`" inside Phase 4. Reading the code, the coupling is **three
points, not one**, and the pipeline is **already live in production**:

1. `BuyerMatchScorer::score(BridgeProperty $listing, …)` — plus every private category scorer is
   typed `BridgeProperty`.
2. `BuyerMatchResult` DTO holds `public BridgeProperty $listing`.
3. Both `BuyerMatchResultBuilder::build()` and `BuyerResultViewMapper::mapOne()` read
   `$result->listing->*` and re-decode `raw_json`.

It also requires a **field-name remap** (camelCase DTO vs snake_case columns):
`PropertyCandidate::livingAreaSqft/pool/garage/waterView/newConstruction/cdd` vs
`BridgeProperty::living_area/pool_private_yn/garage_yn/water_view_yn/new_construction_yn/cdd_yn`, etc.

Live consumers of this exact pipeline today: `StellarBuyerResultsController`,
`StellarPropertyDetailController`. A wholesale swap risks regressing already-shipped buyer results.

**Recommendation [LEAN]: defer the `PropertyCandidate` refactor out of Phase 4.** In Phase 4 the
*only* candidate source is Bridge (the manual-entry / other-MLS adapters are explicitly "Later").
`BridgeListingLookupService` already caches every looked-up property into `bridge_properties`, and
`PropertyCandidate` carries `sourceRecordId = bridge_properties.id` when `source === 'bridge'`. So
match-check can:

```
lookup → PropertyCandidate (guarantees the bridge_properties row exists)
       → BridgeProperty::find($candidate->sourceRecordId)   // source==='bridge'
       → existing scorer/builder/mapper, UNCHANGED
```

This keeps Phase 4 true to the doc's own headline ("mostly wiring, not new engines"), avoids
touching a live scorer, and defers the abstraction to when a second source actually needs it.

**Tradeoff.** If deferred, match-check cannot score a *non-Bridge* property (manual/FSBO/other-MLS)
until the refactor lands — but those sources are already scheduled for "Later," so Phase 4 loses
nothing real. If you want the refactor in Phase 4 anyway, budget for all three coupling points +
the field remap + regression tests on the live buyer-results path, not a one-line signature change.

### A2 — Match-check is single-property; do **not** route it through `BuyerMatchService::match()` **[LEAN]**

**Finding.** `BuyerMatchService::match(BuyerCriteriaPayload, cap=200, role)` is a *candidate-discovery*
engine: lazy Bridge import → `BuyerMatchQueryBuilder` SQL → IDX participation gate → `scoreAll` →
`buildAll` → sort. It answers "given criteria, find matching properties." Phase 4 is the **inverse**:
one known property, scored against one (or a few selectable) criteria profiles.

**Recommendation.** `MlsPropertyMatchAnalysisService` should be a thin orchestrator that calls
`scorer->score()` + `resultBuilder->build()` + `viewMapper->mapOne()` directly on the single
looked-up property. Skip `match()` entirely — no import sweep, no query builder, no candidate cap.
This is cleaner, cheaper, and avoids dragging the whole discovery machinery into a point lookup.

### A3 — Mirror the `stellar.buyer.results` triplet, add a flag middleware **[LEAN]**

**Finding.** `StellarBuyerResultsController` (route → controller → `stellar.buyer.results` view) already
injects the criteria loaders, `CriteriaListingResolver`, and `BuyerResultViewMapper`, and already
enforces the compliance boundary via the mapper. Feature-flag precedent: `CheckAgentAiV2Enabled`
middleware (`abort(404)` when off) + Kernel `$routeMiddleware` alias + `config/*.php` env-default-false.

**Recommendation.** Copy that shape: `config/mls_match_check.php` (`enabled => env(..., false)`), a
`CheckMatchCheckEnabled` middleware mirroring `CheckAgentAiV2Enabled`, a Kernel alias, and
`Route::get('/match-check', …)->middleware([...,'match-check'])` inside the authed group. This
matches every existing flag in the app and keeps the safe-default-OFF posture.

---

## B. Scoring explanations

### B1 — Add a `mapOneDetailed()` for the single-property deep-dive **[LEAN]**

**Finding.** The batch `BuyerResultViewMapper::mapOne()` deliberately *strips* detail for scannable
cards: it discards `fields_used` from `why_this_matches`, and `dimension`/`fields_used`/`deviation`
from `tradeoffs`. But `BuyerMatchResultBuilder` **already computes** all of that
(`{dimension, label, fields_used, score_contribution}` and `{…, deviation}`). Match-check is a
single-property page where users *want* the "why," so the batch brevity is the wrong default here.

**Recommendation.** Add a match-check-specific `mapOneDetailed()` (or a verbosity flag on `mapOne`)
that surfaces `fields_used` and the actual `deviation` magnitudes — no recomputation, just stop
throwing the data away. This is the single highest-leverage transparency win and it's nearly free.

### B2 — `non_residential` score is invisible in `category_bars` **[DECISION]**

**Finding.** The scorer computes an additive `non_residential` contribution (0–10) for
commercial/land/business/income listings, but `category_bars` renders only the 7 residential
categories. For a non-residential match-check, the total won't reconcile with the visible bars —
a trust problem magnified on a single-property page where users scrutinize the number.

**Recommendation.** For non-residential property types, render a `non_residential` bar (or fold its
sub-scores into the explanation block). At minimum, decide how the total reconciles with the bars
so the page isn't self-contradicting for commercial/land users.

### B3 — Phase-1 caps make bars not sum to the total **[DECISION]**

**Finding.** Location is capped at 24/30 (`LOCATION_MAX_PHASE1_PTS`) and price proximity at 20/25
(`PRICE_PROXIMITY_MAX_PTS`). On a deep-dive page users may add the bars and question the total.

**Recommendation.** Frame bars explicitly as *relative contributions* (not additive points), or show
the caps, or reconcile them. A UX/label decision, not a scoring change.

---

## C. UX

### C1 — `CriteriaListingResolver` has no "preferred" selector yet **[LEAN]**

**Finding.** The doc says Phase 4 auto-selects the preferred saved profile "via
`CriteriaListingResolver`," but that method doesn't exist. Today the class only has
`resolveAllowedUserIds()` and `resolveAccessible()` (enumeration + access control).

**Recommendation.** Add `resolvePreferred(User): ?array` (newest active buyer/tenant criteria) for
the default, and feed `resolveAccessible()` into the switch dropdown. `resolveAccessible()` already
returns labeled, newest-first records — it's the right seam for both.

### C2 — Agent vs consumer default, and buyer-vs-tenant disambiguation **[DECISION]**

**Finding.** An agent (via `user_agents`) can access many clients' criteria; a single user may have
*both* buyer and tenant criteria. The buyer/tenant engines differ (sale vs lease PropertyType
strings; `TenantCriteriaLoader` intentionally drops `max_price`). "Preferred" is ambiguous for an
agent, and picking the wrong engine silently mis-scores.

**Recommendation.**
- **Consumer:** default to newest active criteria; auto-detect buyer-vs-tenant from the *looked-up
  listing's* sale/lease status (`PropertyCandidate` carries `standardStatus`/`mlsStatus`/`propertyType`)
  — if the MLS# is a lease, default to tenant criteria. Nice, low-cost UX correctness.
- **Agent:** don't guess a default client; force explicit selection, grouped by client in the dropdown.

### C3 — First-view "location enriching…" state **[LEAN]**

**Finding.** The lookup dispatches `ComputeLocationDna` for new/changed Bridge rows; flood/schools/
commute (Phase 5) won't be ready on the very first check of a fresh MLS#. The doc already flags
"first view may show enriching…".

**Recommendation.** Design the match-check view to render the score immediately and show a pending/
skeleton affordance for location insights, rather than blocking on enrichment. Build the empty/
pending state now even though Phase 5 fills the data — it's the realistic first-view path.

---

## D. Scalability / cost

### D1 — A **read** path with external-API **write** side effects **[DECISION]**

**Finding.** `BridgeListingLookupService::cacheRecord()` dispatches `ComputeLocationDna` whenever a
looked-up row is new or its address changed. Match-check is a user-initiated, repeatable *read*, so
at scale (many users checking many listings) this becomes an uncontrolled fan-out of enrichment jobs
— each pulling Google Places / FEMA / Census — gated only by `isNew || addressChanged`.

**Recommendation.** Decide whether match-check should trigger DNA at all. Options: (a) gate the
dispatch behind the Phase 4 flag / a queue rate-limit; (b) let match-check reuse cached DNA and
enqueue enrichment lazily/deduplicated. This is the biggest cost/scale risk in the phase and it's
easy to miss because it hides behind a "lookup."

### D2 — Verify lookup indexes are actually migrated **[LEAN]**

**Finding.** The `listing_id` + `city` (+ `postal_code`, `unparsed_address`) indexes shipped in the
Phase 2 commit (`2026_07_05_000001_add_lookup_indexes_to_bridge_properties`, Postgres
`CREATE INDEX CONCURRENTLY`). `findByMlsNumber` does `where('listing_id', …)`. Whether the migration
has *run* against the live DB can't be confirmed read-only.

**Recommendation.** Run `php artisan migrate:status` and confirm before enabling the flag widely —
otherwise MLS# lookups table-scan a growing `bridge_properties`.

### D3 — Bound the API/cost surface **[LEAN]**

**Finding.** Uncached MLS# = 1 Bridge API call + upsert (+ possible DNA). `searchByAddress` hits the
API on local miss and returns up to 25.

**Recommendation.** Local-first is already in place; add a short freshness TTL so repeated same-session
checks of one MLS# don't re-hit the API (the local row + `modificationTimestamp` supports staleness),
require minimum fields + debounce on the address path, and rate-limit address search.

---

## E. Compliance

### E1 — Enforce "read internally, never render" structurally, not by convention **[LEAN]**

**Finding.** Precedent is good: `BuyerResultViewMapper` guarantees "raw_json is NEVER passed to the
view layer" and strips PII/brokerage/lockbox; `SnapshotFactVisibility` classifies `RESTRICTED_KEYS`.
**But** `BridgeProperty` has no `$hidden`, stores full `raw_json` (incl. PublicRemarks), and the
existing detail page (`stellar/property/detail.blade.php`) *does* render `public_remarks`. The risk:
a dev adds a field to the match-check Blade and leaks restricted data.

**Recommendation.** Route **all** match-check rendering exclusively through
`BuyerResultViewMapper::mapOne()` output — the Blade must never touch the model or `raw_json`. Add a
test asserting the match-check response contains no restricted keys (mirror the mapper's existing
governance), and consider `SnapshotFactVisibility` as a belt-and-suspenders filter.

### E2 — Resolve the PublicRemarks restricted-vs-public inconsistency **[DECISION]**

**Finding.** The compliance boundary lists PublicRemarks as a restricted field B "reads internally,
never republishes." Yet (a) the scorer doesn't actually score on PublicRemarks (it reads
`CommunityFeatures`/`AssociationAmenities`/etc.), and (b) the detail page renders PublicRemarks as
publishable. So the codebase treats PublicRemarks as both restricted and public.

**Recommendation.** For match-check, treat PublicRemarks as **restricted** (don't render) to stay
safe. Separately, get an owner ruling on PublicRemarks' true status — it also affects Phase 3
prefill (facts-only excludes remarks) and whether the existing detail page is compliant.

### E3 — IDX participation gate on a known-listing lookup **[DECISION]**

**Finding.** `BuyerMatchService::match()` filters candidates by `IDXParticipationYN`. Match-check,
by contrast, analyzes a *specific MLS# the user already has*. Applying the IDX display gate to a
user's own explicit lookup may be neither required nor desired — but skipping it is a compliance call.

**Recommendation.** Get a product/compliance decision on whether `/match-check` honors the IDX gate.
If A2 is adopted (bypass `match()`), the gate won't apply by default — so this must be an explicit
decision, not an accident of which code path was reused.

---

## Open decisions to settle before coding (summary)

**All resolved by the owner on 2026-07-05 — see "Finalized owner decisions" below.**

| # | Decision | Finalized (2026-07-05) |
|---|----------|------------------------|
| A1 | Refactor scorer to `PropertyCandidate` in Phase 4, or defer? | **DEFER** — keep BridgeProperty pipeline; refactor only when a 2nd real source lands (Owner #1) |
| A2 | Route match-check through `match()`? | **NO** — score the one property directly; `match()` only as optional low-score "better matches" (Owner #2) |
| B1 | Richer single-property report? | **YES** — `mapOneDetailed()` + a full Match Report (Owner #3) |
| B2 | How does `non_residential` show in the bars/total? | **Render every contributing category; totals must reconcile** (Owner #4) |
| B3 | Reconcile Phase-1 caps vs bar sum | Fold into #4 reconciliation requirement |
| C2 | Agent default client + buyer/tenant engine pick | **Auto-detect Sale/Rent → Buyer/Tenant for consumers; manual switch for agents/power users** (Owner #5) |
| D1 | Does match-check trigger Location DNA? | **No unlimited enrichment** — rate-limited + cached strategy required (Owner #6) |
| E2 | Is PublicRemarks restricted or public? | **Internal analysis only; never display/republish in Match Check** until Stellar licensing confirmed (Owner #7) |
| E3 | Does `/match-check` honor the IDX gate? | **YES** — honor IDX/visibility gate; block non-IDX with a safe message; internal path is a later permission-gated feature (Owner #9) |

## What's already in place (no new build needed)

- `PropertyCandidate` DTO + `BridgePropertyCandidateAdapter::fromModel()` (provenance included).
- `BridgeListingLookupService` (`findByMlsNumber`/`findByListingKey`/`searchByAddress`, local-first).
- The full scorer → builder → view-mapper chain, with the compliance boundary already enforced in
  the mapper.
- Criteria loaders (`BuyerCriteriaLoader`/`TenantCriteriaLoader::loadById`) → shared
  `BuyerCriteriaPayload`.
- `CriteriaListingResolver` access-control + enumeration (needs only a `resolvePreferred()` add).
- Lookup index migration (committed; confirm it's *run*).
- Feature-flag pattern (config + middleware + Kernel alias) to copy verbatim.

## Net-new for Phase 4

`MlsPropertyMatchAnalysisService` (thin single-property orchestrator returning a typed `MatchReport`
DTO — see F8), `CheckMatchCheckEnabled` middleware + `config/mls_match_check.php`,
`CriteriaListingResolver::resolvePreferred()`, a `mapOneDetailed()` verbosity path (B1), the
`/match-check` route/controller/view, the compliance regression test (E1), a Location-DNA throttle
guard (F6), an optional low-score "better matches" fallback (F2), the report-enrichment blocks
required by the full Match Report (F3: why-not / confidence / recommendations), and a
`ListingVisibilityGate` policy object enforcing the IDX/visibility rule on both the primary property
and the fallback list (F9).

---

# F. Finalized owner decisions (2026-07-05)

These supersede the "default I'd take" column above. They are locked for Phase 4 unless revisited
explicitly. Each notes what it resolves and any *new* implication for implementation.

### F1 — Keep the existing `BridgeProperty` scoring pipeline for Phase 4 *(resolves A1)*

Do **not** refactor the scorer to `PropertyCandidate` yet. Phase 4 analyzes only Bridge/Stellar
properties, so it uses the existing cached `BridgeProperty` pipeline unchanged
(`lookup → BridgeProperty::find($candidate->sourceRecordId) → score() → build() → mapOne()`).
`PropertyCandidate` remains the long-term abstraction; the scorer refactor happens **only** when a
second real source exists (manual entry, URL parser, another MLS). *Implication:* Phase 4 must not
introduce new `PropertyCandidate`-typed scoring code paths — the DTO stays confined to the
lookup/adapter layer, and the seam back to the model is `sourceRecordId` (guaranteed set for
`source === 'bridge'`).

### F2 — Match Check is not a search engine *(resolves A2; adds a fallback stage)*

Match Check always starts from **one known property** and scores that property directly. It must
**never** call `BuyerMatchService::match()` to score the primary property.

**New two-stage flow (locked):**
1. **Primary (always):** `Bridge lookup → cached BridgeProperty → score() → build() → mapOne()`.
2. **Fallback (conditional):** *only* if the primary score is **below a configured threshold**,
   optionally call `BuyerMatchService::match()` to surface "better matching properties."

*Implications:* add a `match_check.better_matches_threshold` config value (default TBD, e.g. 60) and
a `better_matches_enabled` flag. The fallback is a **separate, clearly-labeled section** ("You may
also like…") — never mixed into the primary property's score. This is also the natural seam for the
future "Alternative property recommendations" feature (F8). The fallback still honors all compliance
rules (ViewMapper-only output, IDX decision E3).

### F3 — The Match Report is significantly richer than the listing cards *(resolves B1; expands scope)*

The single-property Match Report must explain: **why it matched · why it did not match · category
weighting · score breakdown · tradeoffs · missing information · confidence · recommendations.**
`mapOneDetailed()` is the endorsed direction for surfacing the *already-computed* blocks
(`fields_used`, `deviation`, per-category contributions).

**Accuracy note — `mapOneDetailed()` alone is not sufficient.** The current builder computes
`whyThisMatches`, `tradeoffs`, `cautionFlags`, `missingData`. Three report elements require **modest
new computation** in `BuyerMatchResultBuilder` (guarded so the live batch path is unaffected):
- **"Why it did *not* match"** — a negative-contributors block covering *all* low/zero-scoring
  categories, not just the current price/size/amenity/pet tradeoffs.
- **Confidence** — an overall confidence measure (aggregate data-completeness + the existing geo
  confidence signal), distinct from the score itself.
- **Recommendations** — actionable next steps ("widen price by ~$X", "consider adjacent city Y").
  Ship a **rule-based v1**; leave the AI narrative as an extension point (F8).

Keep these as structured data on the report DTO (F8), rendered by the detailed mapper — do not
inline prose in the Blade.

### F4 — Commercial/land/income/business transparency *(resolves B2 + B3)*

For non-residential property types, **every scoring category that contributes to the final score
must be visible**, and the displayed category totals **must reconcile to the overall score**. Users
should never wonder where points came from. *Implications:* render the `non_residential`
contribution (today invisible in `category_bars`) and its sub-scores; and resolve the Phase-1 cap
mismatch (location 24/30, price 20/25) so the visible breakdown adds up to the shown total — either
display true maxima or present a reconciled "points contributed / points available" per category.
This reconciliation is a **hard requirement**, not cosmetic.

### F5 — Auto-detect criteria type *(resolves C2)*

**Consumers:** automatically detect whether the looked-up property is **For Sale** or **For Rent**
(from `PropertyCandidate.standardStatus` / `mlsStatus` / `propertyType`) and automatically apply the
matching **Buyer** or **Tenant** criteria. **Agents / power users:** allow manual switching between
Buyer and Tenant criteria profiles (dropdown via `CriteriaListingResolver::resolveAccessible()`,
grouped by client). *Implication:* the sale/lease→engine mapping is a small pure helper; when a
consumer lacks the matching criteria type (e.g., a rental property but only Buyer criteria on file),
show a clear empty-state prompting them to create the right criteria rather than silently scoring
with the wrong engine.

### F6 — Location DNA generation must be rate-limited + cached *(resolves D1)*

**Locked rule:** Match Check must never allow unlimited user-triggered external enrichment. Repeated
Match Check requests must not continuously re-trigger Location DNA / external API usage.

**Proposed strategy (three layers — the read path never enriches inline):**

1. **Render from cache; never enqueue inline.** Match Check displays whatever Location DNA already
   exists for the property and renders immediately (with the C3 pending/skeleton affordance when
   absent). Producing the score never blocks on, and never inline-dispatches, enrichment.

2. **Per-listing cooldown (dedupe).** Any enrichment dispatch for a property is guarded by a cooldown
   keyed on `listing_key` — e.g. a cache key `ldna:enqueued:{listing_key}` with a TTL (proposed
   **24h**), or a `dna_last_enqueued_at` column on `bridge_properties`. Within the window, repeated
   checks of the same property **never** re-enqueue. Re-enqueue only when data is materially stale:
   `addressChanged`, or `modificationTimestamp` newer than the last enrichment, **and** the cooldown
   has elapsed.

3. **Per-user / global rate limit.** Wrap any Match-Check-originated enrichment dispatch in Laravel
   `RateLimiter` (e.g. **N enrichments per user per hour**, plus a global ceiling) so a single user
   fanning through many MLS#s cannot flood the Google Places / FEMA / Census providers. Over the
   limit → skip enrichment (still render the cached/partial report), never hard-fail the page.

**Centralize, don't duplicate.** Today `BridgeListingLookupService::cacheRecord()` auto-dispatches
`ComputeLocationDna` on `isNew || addressChanged`. Rather than fork that logic, either (a) give the
lookup a `dispatchDna: bool` parameter so the Match Check path can opt out and route dispatch through
the guarded enrichment service, or (b) move the cooldown + rate-limit gate *inside* the dispatch
path so **all** callers (import sweep, lazy import, match-check) inherit the throttle. Option (b) is
preferred — one guard, no drift. All of the above is additionally gated by the Phase 4 feature flag.

### F7 — PublicRemarks is internal-analysis-only until Stellar licensing is confirmed *(resolves E2)*

Treat `PublicRemarks` as **internal analysis only**: it may be *read* to improve matching if useful,
but must **never** be publicly displayed or republished within Match Check. *Implications:* enforce
structurally (E1) — the Match Check Blade renders only `BuyerResultViewMapper` / `mapOneDetailed`
output and never touches the model or `raw_json`; add/extend the compliance regression test to assert
`PublicRemarks` (and other restricted keys) never appear in the Match Check response. Note this makes
Match Check **stricter** than the existing `stellar/property/detail.blade.php`, which currently *does*
render `public_remarks` — that page's compliance is a separate, still-open question (out of Phase 4
scope, flagged for the owner).

### F8 — Reserve extension points for future AI / persistence features *(architecture guardrail)*

Do **not** implement any of the following in Phase 4, but structure the code so they slot in later
**without redesign**: AI "why it matches" / "why the tradeoffs" narratives, Buyer/Tenant DNA
compatibility narrative, Property DNA summary, marketability insights, alternative property
recommendations, Ask AI integration, and **saved / shareable / historical** Match Reports.

**Concrete seams to build in now (all cheap, all structural):**

- **Return a typed `MatchReport` DTO, not a bare array.** `MlsPropertyMatchAnalysisService` produces
  a structured, **serializable** report object (criteria id + type, listing_key, source, score
  snapshot, category breakdown, why/why-not/tradeoffs/missing/confidence/recommendations, timestamp).
  Rendering consumes the DTO; nothing non-serializable goes inside it. This one decision unlocks
  saved/shareable/history (a future `match_reports` table just persists the DTO) and gives AI layers
  a clean structured input.
- **Separate compute from narrate.** Keep a **nullable `narrative` / `ai` slot** on the DTO. AI
  explanation becomes an optional **decorator** between the analysis service and the mapper that
  fills those slots — the rule-based report (F3) is the always-present fallback when AI is off/absent.
- **External calls stay gated + async.** AI generation and Location DNA (F6) are never inline in the
  request path — same posture, so adding AI narration later doesn't change the page's latency model.
- **Ask AI reuses the existing snapshot seam.** Express the `MatchReport` as (or map it to) the
  `SnapshotFactVisibility` fact set already used by Ask AI, so restricted-key governance (F7) and
  Ask AI integration share one visibility classifier rather than two.
- **DNA narratives attach, don't embed.** `PropertyDnaGenerator` and `BuyerTenantDnaGenerator`
  already exist; the report DTO should carry optional slots to *attach* their output, computed
  out-of-band, rather than being coupled to them.
- **Config-drive the tunables.** Category weights, the F2 better-matches threshold, F6 cooldown/rate
  limits, and confidence parameters live in `config/mls_match_check.php` (or `match_scoring`-adjacent
  config), so tuning and future AI-assisted calibration need no code change.

**Guardrail summary:** Phase 4 ships a rule-based, cache-first, single-property Match Report behind a
default-OFF flag, emitting a typed serializable DTO through a compliance-enforcing mapper. Every
future feature above is an additive decorator, persistence of the DTO, or a config change — none
requires reworking the Phase 4 core.

### F9 — `/match-check` honors IDX / listing-visibility gates (safest default) *(resolves E3)*

**Locked rule:** consumer-facing Match Check must honor IDX / listing-visibility gates for **any**
property shown to the user. If a listing is not permitted for IDX / public consumer display, do
**not** render a public-facing Match Check report for it.

**Implementation default (Phase 4):**
- **Respect the IDX/visibility gate** on the looked-up property before scoring is displayed.
- **Do not display restricted / non-IDX listing details** (no score card, no report, no partial data).
- **If blocked, show the safe message:** *"This property cannot currently be analyzed or displayed
  through Match Check."* — a friendly terminal state, not an error, and no listing specifics leak in it.
- **Agent / admin / internal analysis of non-IDX listings is out of scope for Phase 4** — reserved
  as a **separate, permission-gated path** for later (only if/when compliance approves private/internal
  analysis).

**Where the gate lives (structure it to be adjustable):** the existing IDX signal is
`IDXParticipationYN` on `raw_json`, applied today inside `BuyerMatchService::match()`. Because F2
scores the primary property **directly** (bypassing `match()`), that gate would otherwise not run —
so the gate must be applied explicitly on the Match Check path. Recommendation: extract a single
**`ListingVisibilityGate::isConsumerVisible(BridgeProperty|PropertyCandidate): bool`** (or
`Decision` object carrying a reason) that `MlsPropertyMatchAnalysisService` calls **before** building
any report, and that `match()`'s existing filter can also delegate to — one policy, one place.
Making it a named policy object (rather than an inline `raw_json` check) is exactly what lets the
rule be relaxed later for an internal/permission-gated path without touching scoring or rendering:
the future internal path simply supplies a different visibility policy / permission context.

*Interaction with F2's "better matches" fallback:* the fallback list must run every candidate
through the **same** `ListingVisibilityGate`, so no non-IDX property can appear there either.

*Interaction with F7:* visibility (F9, "may this listing be shown at all") and field-level
restriction (F7, "which fields of a shown listing may render") are **distinct** gates and both
apply — a listing must pass F9 to appear, and even then only ViewMapper-approved fields render.
