# Listing Preference Governance — Save | Maybe | Pass

Status: **Phase 5 — Taste DNA as a bounded, post-score reorder of near-tied Stellar Buyer/Tenant
results under Best Match, behind its own default-off flag; see §14.** Phase 4 (Taste DNA: learn and
explain on the customer's own page, §13) and Phases 2–3 (capture, surfaces, management area) are merged.
Earlier status: **Phase 2 — capture, behind a default-off flag.** Authenticated Buyers and Tenants can
Save, Maybe or Pass a listing, give optional structured reasons, and undo, from the BidYourOffer
Seller/Landlord property-detail pages. `LISTING_PREFERENCES_ENABLED` ships **false**, and off means
the routes 404 and no control renders.

Still not built, and still governed: Ask AI consumption, recommendations, filtering, and **any
learning that acts on what a customer is shown beyond the §14 rerank** (§6, §8, §13, §14).

Customer terminology is **Save | Maybe | Pass**, everywhere — config, storage, docs and (later)
the interface. Earlier planning notes used "Love/Maybe/Pass"; that wording is **superseded and must
not reappear**. "Save" describes what the control does; the sentiment lives in the reasons the
customer then chooses.

---

## 1. What this is

One reusable preference system for Buyers and Tenants, shared by every surface that shows a
property — search results, listing detail pages, Explore, Virtual Drive and future recommendation
surfaces. There is **no per-surface preference feature**: a surface contributes the `surface` value
on an event and nothing else.

| State | Means | Prompt |
|-------|-------|--------|
| Save | the customer likes the property | *What do you like about this property?* |
| Maybe | interested but unsure | *What are you unsure about?* |
| Pass | not for them | *Why isn't this one for you?* |

**Pass is a display decision and nothing else.** It never deletes, hides or alters listing or MLS
data, and a passed property stays recoverable. Nothing in this subsystem writes to
`bridge_properties`, to listing meta, or through `MlsSyncFieldPolicy`.

---

## 2. Storage — one current state, plus an immutable history

Three tables, following the `smart_tag_evidence` → `smart_tag_assignments` →
`smart_tag_manual_events` precedent.

| Table | Holds | Mutability |
|-------|-------|-----------|
| `listing_preferences` | exactly one CURRENT state per `(user_id, seeker_role, subject_key)` | replaced on change |
| `listing_preference_reasons` | the reasons behind that current state | replaced wholesale |
| `listing_preference_events` | every transition, with an immutable reason snapshot | **append-only** |

The split is load-bearing, not tidiness. Four independent requirements need it:

1. Moving Save → Maybe → Pass must **update** the current signal rather than stack contradictory
   states — one current row, enforced by a unique index, not by application discipline.
2. "Repeated patterns become stronger over time" and "one Save or Pass must not permanently define
   a customer's preference" are both statements about **time**. Recency weighting and decay are
   uncomputable from current state alone.
3. Undo, and recovering a passed property.
4. Fair Housing auditability: what a customer was shown and chose, after the fact.

`listing_preference_reasons` cascades from its preference — a reason cannot outlive the state it
explains. `listing_preference_events` deliberately **does not cascade**: deleting a current state
must never erase the record that it existed.

`dimension` and `smart_tag_key` are denormalised onto a stored reason on purpose. They record what
the chip meant **when it was chosen**, so a stored reason stays interpretable after the catalog
changes. Live interpretation always goes to `ListingPreferenceReasonCatalog`.

---

## 3. Identity — two of them, and the collision they prevent

A preference stores **both** the listing the customer acted on and the durable subject it is about.

```
bridge                                    → mls:<listing_key>
seller_agent / landlord_agent, no MLS     → byo:<listing_type>:<id>
seller_agent / landlord_agent, MLS-linked → mls:<listing_key>
```

`(listing_type, listing_id)` is the house addressing convention, produced only by
`SmartTagListingType`, so a caller cannot invent a type string. It is audit truth.

`subject_key` decides **uniqueness**, and answers a question the ref cannot: the same property
reaches a customer as two different rows. `ExploreCanonicalListingResolver` already resolves a
Bridge MLS row to its canonical BidYourOffer page through the `mls_listing_key` provenance meta, so
one house is both `bridge:12345` and `seller_agent:678`. Storing only the ref would let someone Pass
a property on the map and meet it again, unpassed, in results.

The key is also more durable than the ref: Smart Tags address `bridge_properties.id`, stable only
because the importer upserts on `listing_key`. A preference outlives a listing in a way tag evidence
does not, so a Bridge subject is keyed on the **listing_key string**.

`ListingPreferenceSubjectResolver` is the only thing that may read provenance to decide a key. It is
read-only, dispatches nothing, and opens no provider connection. A Bridge row with **no** listing key
resolves to `null` and the caller declines the preference — better than inventing a key that may
later belong to something else.

**No parcel, address or coordinate grouping.** Grouping successive listings of one property is a
different question, answered by `ExplorePropertyIdentity`; answering it here would merge two listings
of the same house that a customer may legitimately feel differently about. Coordinates are never an
identity, for the reason that class already documents.

**Not every listing can be a subject.** Buyer and Tenant auctions are search criteria, not
properties, and are absent from `SmartTagListingType` for that reason. Hire Agent rows are excluded
by the same rule that excludes them from Smart Tags: native subjects are Offer Listings only.

---

## 4. Seeker role

`seeker_role` is `buyer` or `tenant`, stored on the row and **never derived at read time**.

`users.user_type` is single-valued and a customer's row can change; deriving the role would
retroactively reinterpret every preference they ever expressed. Buying and renting are also
different intents about different inventory — a Pass on a rental must not suppress a purchase. So
the role is captured at write time and forms part of the uniqueness of a preference.

Seller and Landlord are not seekers. They express preferences about their **own** listing through
Smart Tags on `SURFACE_OWNER`.

---

## 5. The reason vocabulary

`config/listing_preference_reasons.php` is the SSOT, read only through `ListingPreferenceConfig`
(test-asserted). `ListingPreferenceReasonPolicy` is the write boundary — an **intersection, never a
deny-list**, the same rule as `SmartTagSelectionPolicy`, `CompatibilityPreferencePolicy` and
`LandlordScreeningPolicy`. Free text is not a key, so no request can add to the vocabulary.

**This is not a second taxonomy.** A reason about a property characteristic must **link** to a
canonical Smart Tag rather than restate it. The catalog fails the build when a `smart_tag` reason
names a key that is not canonical, active, seeker-selectable and past compliance review.

### Dimensions

| Dimension | Domain | Learnable |
|-----------|--------|-----------|
| `smart_tag` | property characteristics — `config/smart_tags.php` | yes |
| `criteria` | price, size, fees — structured search criteria | yes |
| `location` | proximity and the customer's own places — Location DNA | yes, restricted (§6) |
| `unspecified` | real customer language with no structured counterpart | **never** |

Four dimensions exist because the customer's vocabulary spans three subsystems that deliberately do
not overlap. The Smart Tag taxonomy header excludes price, size and proximity **by name**; forcing
them into tags would create exactly the duplicate vocabulary that governance forbids, and forcing
tags into criteria would lose the link that makes a reason scorable.

`unspecified` is captured for product insight and **never learned**: there is nothing structured to
learn it against, and inferring one would be inventing the vocabulary this design exists to avoid.

### States

A reason declares which of Save / Maybe / Pass may offer it. The three prompts ask different
questions: "Too expensive" is not an answer to *What do you like about this property?*

### Reviewed decision — the null-context fallback

`SmartTagSelectionPolicy` refuses a null context outright. That is right for an **owner**: a listing
always has a property type, so no context means something went wrong. A **seeker** is different — a
customer can Pass from a card whose property type was never resolved.

So the reason policy takes two paths, and **this was reviewed and approved for the inert foundation**:

* **Context available** → delegate to `SmartTagSelectionPolicy` on `SURFACE_SEEKER` (the strict path,
  including applicability).
* **Context genuinely unresolvable** → fall back to `isSeekerSelectable()` alone.

`isSeekerSelectable()` is context-independent and carries the Fair Housing exclusions and the
pending-review hold, so **every Fair Housing and compliance restriction still applies on both
paths**. Only *applicability* is relaxed, and only where it is unanswerable.

**Obligation on Phase 2:** resolve the real listing/property context wherever it can be resolved,
before presenting chips and before accepting them. The fallback is the floor for the genuinely
unresolvable case — never the convenient default, and never a reason to skip a context the caller
could have obtained.

### Reviewed decision — `natural_light` is canonical but non-derivable

Added to the Smart Tag taxonomy (version `2026-09-16.1`) so the "Natural light" chip links to a
canonical key instead of minting a parallel vocabulary. It is owner- and seeker-selectable.

It is **approved as non-derivable**, and until a source rule is separately reviewed there is to be
**no phrase rule, no photo or vision inference, and no marketing-copy inference**. Interior daylight
is claimed in prose far more often than it is recorded structurally; any of those written before the
evidence is reviewed would manufacture confident tags out of sales language.

Derivation may be enabled **only** by a separately reviewed rule in `config/smart_tag_sources.php`.
`SmartTagSourceRulesTest` asserts the derivable flags and the rules agree in both directions, so the
flags can only flip in the same change that adds the rule.

### Changing it

Keys are permanent. Retire with `status => 'retired'`; never reuse or rename, because stored
`listing_preference_reasons` rows reference them. Adding a reason for a property characteristic that
has no canonical tag is a **Smart Tags taxonomy change** under that subsystem's governance process —
never an edit here.

Generic **Style** is deliberately absent in V1: there is no well-defined style taxonomy, and a vague
style tag would be the duplicate vocabulary this file exists to prevent. Specific architectural tags
may be linked individually if and when they are added.

---

## 6. Fair Housing

Reason keys and labels are scanned by `SmartTagComplianceGuard` — the **same** guard the taxonomy
uses, not a copy, and it lives in code precisely so this config cannot relax it.

Tags excluded as seeker preferences are excluded as chips, **inherited rather than restated**:
`accessible_features` (disability) and `playground` (familial status) can never become reasons, and
the write boundary refuses them even when a request names one directly.

### Prohibited in any current or future learned signal

- **user-to-user similarity learning** — "customers like you also passed on…"
- **collaborative neighbourhood or location learning** — any signal derived from how *other*
  customers treated an area
- **neighbourhood demographic inference** of any kind
- **geographic clustering of preference outcomes**
- **protected-class preference inference**, whether stated, derived or correlated

This is the most serious exposure in the whole feature, and it is invisible to the compliance guard.
*"Customers who passed on this area also passed on…"* is redlining reconstructed from behaviour: it
requires no protected-class field to produce a protected-class outcome, and no prohibited word is
ever written down. The prohibition therefore lives here, in prose, and is a condition of building a
learner at all.

### Permitted

Learned signals must be **per customer** and restricted to:

- legitimate property characteristics (canonical Smart Tags),
- structured criteria (price, size, fees) and transaction terms,
- **the customer's own stated Important Places and commute anchors**, measured locally with
  `ImportantPlaceMatcher` / `GreatCircleDistance` — never neighbourhood identity, and never other
  customers' behaviour.

Location learning may use **only** the customer's own stated places and distance preferences.

Other free text may be **stored** later but must never be automatically parsed into a learned signal
unless separately governed and reviewed — the same rule that already makes every `other_*` /
`custom_*` box a forbidden derivation source for Smart Tags.

---

## 7. Authentication and guests

**Phase 1–2: authenticated only.** No guest or session preference records are stored.

The controls may eventually be **visible** to a signed-out visitor, but using one prompts
authentication rather than creating an anonymous record. Guest capture-and-claim needs a retention
policy and an identity-merge path, neither of which has been decided.

The authenticated data model is shaped so that adding it later is **additive** — a claim step that
rewrites `user_id` on existing rows — and never a redesign. `listing_preferences.guest_capture_enabled`
records the decision; it is not a dial to turn.

---

## 8. Ranking, and what a learner may not do to it

Phase 5 built the first and only ranking consumer, under these rules; §14 is its full record.

- `config/match_scoring.php` requires **all enabled weights to sum to 100**, and `BuyerMatchScorer`
  has fixed category caps. Preference must **not** become a new scoring category.
- The precedent is `ImportantPlaceMatcher`: it **scores, it never selects** — shares a slot by
  `max()`, can only raise a score, and leaves the SQL geography untouched.
- Preference therefore applies as a **post-score re-rank** and a separately displayed signal.
  Nothing is excluded in SQL and nothing is deleted. **Phase 5 decision:** a Passed listing is
  **neither hidden nor filtered** — it stays in the results with its Pass shown as UI state, and only
  LEARNED taste (never the listing's own current state) may move it, within the §14 bound.

---

## 9. Feature flags

`config/listing_preferences.php`. **Absence is OFF**, and every gate fails closed.

| Flag | Default | Governs |
|------|---------|---------|
| `LISTING_PREFERENCES_ENABLED` | `false` | the master gate — capture, display, learning |
| `listing_preferences.guest_capture_enabled` | `false` | guest records (§7) — a recorded decision |
| `LISTING_PREFERENCE_TASTE_DNA_ENABLED` | `false` | "Your Home Taste" — Phase 4, §13. Requires the master gate too |
| `LISTING_PREFERENCE_TASTE_RERANKING_ENABLED` | `false` | the bounded Best Match rerank — Phase 5, §14. Requires both gates above too |
| `listing_preferences.learning_enabled` | `false` | learning that acts BEYOND the §14 rerank — recommendations, filtering, Ask AI; hard-coded, read by nothing |

Phase 1 **does not read these to decide anything**: there is no write path to gate, and the
foundation behaves identically whether they are on or off. They are declared now so Phase 2 adds a
caller rather than inventing a flag.

**None of them is in `config/required_production_flags.php`, and none may be added** — that contract
may never name a safety switch, and a gate that starts customer-facing writes is exactly one.

---

## 10. Explicitly out of scope for Phase 1

Routes, controllers, APIs, UI, Blade components, JavaScript, any write path, undo, the passed-listing
recovery surface, ranking changes, Ask AI consumption, Explore integration, Virtual Drive
integration, Taste DNA, "find more like this", and **all behavioural learning**.

Behavioural learning additionally requires the governance revision that
`docs/smart-tags/SMART_TAGS_GOVERNANCE.md` §11 demands. §6 above is the Fair Housing half of that
revision; a learner also needs its decay model, its retention policy and its audit surface reviewed
before any code is written.

---

## 11. Phases

| Phase | Content | Gate |
|-------|---------|------|
| 1 | inert foundation — tables, vocabulary, boundaries, tests, this document | — (merged) |
| 2 | write path and one shared surface; authenticated only; undo works | `LISTING_PREFERENCES_ENABLED` (shipped off) |
| 3 | remaining surfaces incl. Virtual Drive's `save` action; history and recovery UI | Phase 2 verified |
| 4 | Taste DNA: learn and explain, on the customer's own page only (§13) | §6 + §13 — `LISTING_PREFERENCE_TASTE_DNA_ENABLED` (ships off) |
| 5 | bounded post-score rerank of near-tied Stellar Buyer/Tenant results under Best Match (§14) | Phase 4 + score/membership invariant tests — `LISTING_PREFERENCE_TASTE_RERANKING_ENABLED` (ships off) |
| later | Ask AI consumption, recommendations, BYO ranking | each its own governance revision; `learning_enabled` stays hard-off |

---

## 12. Phase 2 — what is wired, and where the boundaries are

**The clear/undo schema correction.** Phase 1 could record the three transitions INTO a state and
not the one out of all of them: `to_state` was `NOT NULL`. Migration
`2026_09_17_000001_allow_null_to_state_on_listing_preference_events` makes it nullable, and NULL
means exactly *no current preference after this transition* — never a fourth state, never a synonym
for `pass`. Its `down()` **refuses** while any clear event exists rather than inventing a preference
for those rows or deleting append-only history; on a database with no clear events it restores
`NOT NULL` normally. Both behaviours are tested.

**One write service.** `ListingPreferenceWriter` is the only thing that persists a preference — a
guard test asserts it. Controllers, Blade and JavaScript describe intent; the service resolves the
subject key, the context and the seeker role, validates the state and the reasons, and performs the
current-state + reasons + history mutation **inside one transaction**. `setState()`,
`updateReasons()` and `clear()` are the three mutations; each appends exactly **one** event.

**One event per completed mutation, not per chip.** Selecting chips is browsing; pressing Done is
the decision. The tray sends the final set in one request.

**Reasons never survive a state change.** `setState()` replaces the reason set outright — "Too
expensive" is not an answer to *What do you like about this property?*

**Nothing is trusted from the browser.** `subject_key`, `seeker_role` and `user_id` are never
accepted from a request; `listing_type` is validated against `SmartTagListingType`; reasons are
re-projected server-side. The routes sit behind `web` (CSRF + session), `auth`, the feature gate and
a per-user rate limit.

**Guests.** No route accepts them and no anonymous row is ever created. The control still renders
and routes through the existing login flow, preserving the listing so they return to it.

**Context.** `ListingPreferenceContextResolver` resolves the listing's real property context before
chips are presented or accepted — the obligation §5 places on Phase 2. The null-context fallback is
reached only when the listing genuinely cannot answer, and even then `isSeekerSelectable()` still
applies: only *applicability* is relaxed.

**Pass changes nothing about the listing.** A test asserts the seller row and its meta are
byte-identical after a Pass and a clear.

---

## 13. Phase 4 — Taste DNA ("Your Home Taste")

**This section is the governance revision that §10 and Smart Tags governance §11 require before any
learner ships.** It covers the three things they name — the decay model, the retention policy and
the audit surface — and the Fair Housing half is §6, unchanged and binding.

### What Phase 4 is, and what it is not

Taste DNA learns patterns from **one customer's own explicit** Save, Maybe and Pass choices and the
structured reasons they picked, and **explains** them back to that customer on one authenticated page,
`/my/listing-preferences/taste`. In Phase 4 it changed nothing a customer is shown anywhere else. **Phase 5 (§14)
adds exactly one consumer** — the bounded Best Match rerank on the Stellar results page, behind its
own flag. Match DNA, the 100-point score, BYO ordering, filtering, recommendations and Ask AI still
never read it; `learning_enabled` stays hard-coded `false` with no reader.

`TasteDnaArchitectureGuardTest` enforces that by reading the source: only a named allowlist of files
may reference Taste DNA, and none of them is in ranking, matching, Stellar, Explore, Ask AI, DNA, the
Livewire wizards, jobs, commands, the helpers or `config/match_scoring.php`.

### Evidence — the only inputs

| Input | Source | Notes |
|-------|--------|-------|
| choices | `listing_preference_events` for ONE `(user_id, seeker_role)` | the existing append-only history; no second feedback store |
| stated reasons | the event's `reasons_json` snapshot, resolved **live** through `ListingPreferenceReasonCatalog::learnable()` | `smart_tag` and `criteria` dimensions only |
| listing characteristics | resolved **present** `smart_tag_assignments`; bedrooms, bathrooms, living area, lot size (buyers only), property sub-type | read by an allow-list of columns and meta keys |

**Never an input:** page views, searches, dwell time or any passive browsing; free text of any kind
(the snapshot holds keys, and `other_property_items` / descriptions / remarks are never read);
`unspecified` reasons (`other`, `layout`, …); **location reasons** — a preference carries no structural
link to the customer's Important Places, so "Location / proximity", "Commute" and "Busy road" are not
learned in Phase 4 at all rather than guessed at; any address, city, ZIP, county, subdivision, school,
coordinate or demographic field; and **any other customer's history** — the one query is keyed on the
signed-in user and role, and no method reads across users.

A tag is learned only while `isSeekerSelectable()` — asked of the taxonomy for stated reasons **and**
for listing characteristics, so `accessible_features`, `playground`, retired and pending-review tags
cannot enter by the back door of "the house had it". `natural_light` participates (seeker-selectable)
and stays non-derivable. A property sub-type is dropped when it is "Other", a placeholder
("Non-Applicable", "N/A", "None", "Unknown") or fails `SmartTagComplianceGuard` — the guard is asked of
every sub-type, so this dimension cannot carry a concept the tag taxonomy may not.

**Historical reasons, current facts.** The two sources age differently, and the rule is:

* A **stated reason is historical evidence**: the snapshot stored on the event when the customer chose.
  It never changes afterwards, and it keeps counting when the listing is later archived, refused by the
  feed, or deleted outright.
* An **observed characteristic is a correlation against the facts the platform CURRENTLY publishes**
  for the listing the customer last acted on for that home. If the seller corrects the listing, the
  correlation follows the current canonical fact on the next derivation. Nothing is snapshotted, no
  second event system exists, and preference history is never rewritten.
* When the listing or the fact is **unavailable** — feed-refused Bridge row, archived or draft native
  listing, deleted row, blank or unreadable value, implausible number — that characteristic is
  **omitted**, never guessed and never carried over from an earlier value.
* The page says which is which (see *Audit surface*), so a correlation never reads as something the
  customer told us.

**One home, one subject.** Choices are grouped by `subject_key` only. An MLS home seen through its
Bridge row and through the BYO Seller/Landlord listing imported from it resolves to the one
provider-scoped `mls:` subject and counts as **one** home; a key two providers hold stays unmerged
(the listing keeps its own `byo:` subject); nothing is ever grouped by address, parcel or proximity.

### Weighting — deterministic, in `TasteDnaDeriver`

* Save → positive, Pass → negative, **Maybe → uncertain**: a Maybe never counts toward a positive or
  negative pattern; it only dilutes agreement, and an uncertain pattern is never called "often".
* A stated reason weighs 1.0; a listing characteristic weighs 0.5. One choice that both states and has
  a feature counts once, at the stronger weight.
* **Per home, per direction, the maximum — not the sum.** Toggling one house ten times is one house;
  repetition strengthens a pattern only across independent homes.
* Conflict lowers confidence; comparable Saves and Passes on a reason the customer named read as
  **mixed**. A feature that is merely present on both saved and passed homes explains nothing and is
  not announced.
* Tiers, never percentages: `insufficient` (never shown), `emerging` ("tend to"), `established`
  ("often"). Every shown tier needs at least two homes; one choice is never a taste.
* Numeric facts are described as the middle range of saved (and passed) homes, and not shown when
  the saved and passed ranges overlap.
* `RULES_VERSION` changes whenever a rule or constant changes.

### Decay model — supersession, not the clock

A choice still in force weighs 1.0. A choice the customer later **changed or cleared** is
**superseded**: it weighs 0.25, stays in the evidence and still counts as a home they chose. So later
contrary feedback outweighs older evidence **without deleting it**, a clear never erases history, and
a clear is never read as a Pass. A reason revision replaces the earlier answer to the same choice.

There is **no wall-clock decay**, deliberately: decay by age would make the profile depend on when it
was computed, and "the same history rebuilds to the same profile" would stop being true.

### Retention — nothing new is stored

Taste DNA is **derived on demand and never persisted**: no table, no migration, no cache, no job.
Every page view recomputes from the events, so there is no derived copy to drift, to retain, or to
forget, and "rebuild" is simply computing again — `TasteDnaServiceTest` proves identical output from
identical history and that deriving writes nothing. Retention of Taste DNA is therefore exactly the
retention of `listing_preference_events`, which this phase does not change.

**A truncated history is never derived from.** `TasteEvidenceReader::MAX_EVENTS` (5,000 per customer
and role) is a safety ceiling against a runaway account, not a window. The reader asks for one row past
it; if that row exists, the evidence is reported incomplete, the profile is `incomplete` with no
signals, and the page says it cannot summarise the customer's taste right now instead of showing
patterns drawn from part of it. Reading only the newest N events could cut one home's Save → Pass
sequence in the middle and state a direction the full history does not support.

### Audit surface — every observation is traceable

Each signal carries its dimension and canonical key, direction, confidence tier, the internal
strength and agreement behind the tier, the number of homes supporting it with Saved / Maybe / Passed
counts, first and last supporting timestamps, and its sources (stated reason, listing
characteristic). `TasteProfile::toArray()` is the full internal record. The customer page is worded
only by `TasteObservationPresenter`, which shows the pattern, the direction in words, the number of
homes and where it came from — and **no score, percentage, key, id or subject**. The source line
separates the two kinds of evidence in words: a stated reason reads *"Based on reasons you picked."*;
an observed characteristic reads *"Seen across homes you Saved, from details those homes currently
list — not a reason you picked."* (or *Passed on* / *chose*), so a feature the homes happened to have
is never presented as something the customer said.

### Flags

`LISTING_PREFERENCE_TASTE_DNA_ENABLED` (default `false`, parsed fail-closed) **and** the master
`LISTING_PREFERENCES_ENABLED` must both be on, read only through `TasteDnaAvailability`, which the
route middleware and the management page's tab both ask. Base Save / Maybe / Pass runs with Taste DNA
off. Neither flag may be added to `config/required_production_flags.php`.

### Still prohibited, and still Phase 5 or later

Everything in §6 — user-to-user similarity, collaborative or neighbourhood learning, demographic
inference, geographic clustering, protected-class inference — and any use of Taste DNA to filter,
recommend or answer questions. Ranking is permitted only as the bounded rerank §14 governs. Location learning against the customer's own Important Places
needs a structural link from a preference to those places first; it is not approximated here.

---

## 14. Phase 5 — Taste DNA as a bounded Best Match rerank

**This section is the governance revision §8, §11 and §13 require before Taste DNA may act on what a
customer is shown.** §6 is unchanged and binding.

### What Phase 5 is

The customer's own Phase 4 Taste profile may **reorder near-tied results** on the Stellar results page
(`/stellar/buyer/results`, which serves both Buyer and Tenant criteria), **under Best Match only**. The
pipeline is and stays: eligibility / filters → Match DNA score → Taste rerank → presentation. Taste
DNA never decides whether a property qualifies.

| Never changes | How it is guaranteed |
|---|---|
| any listing's 100-point Match DNA score | the reranker receives scores read-only and returns none; `config/match_scoring.php`, `BuyerMatchService`, `BuyerMatchScorer`, `BuyerMatchResultBuilder` and `BuyerResultViewMapper` are untouched (guard test); a real-matcher test compares every score, bar and display ON vs OFF |
| which listings appear | the reranker returns a permutation of its input and asserts it; the total, the map pins and pagination read the same list; a listing the customer Passed stays |
| an explicit sort | only an absent `sort` or `sort=best_match` is personalized; any other value is left untouched. Stellar offers **no sort control today** (audited: the controller reads only `criteria_type`, `criteria_id`, `page`) |
| whose taste | the signed-in account's own profile, for the seeker role its `user_type` names, and only when that is the results' market; an agent viewing a client's search gets the standard order |
| an explicit request | a search whose criteria carry explicit seeker Smart Tag picks gets the standard order (below) |

### Explicit criteria take precedence over learned taste

What a customer asks for today must never be overruled by what their history suggests. Everything
the matcher filters and scores on (types, price, beds, baths, geography, amenities…) is decided
**before** reranking and cannot be undone by it — the rerank sees only already-eligible listings and
cannot cross a real score gap.

**Seeker Smart Tag picks (PR #195) are the exception, and are handled conservatively.** Buyer/Tenant
Offer Listings (and Criteria) can now carry explicit Smart Tag picks
(`smart_tag_seeker_preferences`). They are now **scored** in Match DNA — one expressed amenity
inside the 10-pt Amenities category (Smart Tags governance §14) — but never filtered on. Scoring
does not by itself make the rerank safe: a pick can open a lead **under the 3 points the rerank
never crosses** (one pick among eight is one eighth of 10 points), and inside such a lead maximum
Taste can put a listing with FEWER explicit picks above one with more. `ExplicitSeekerTagAuthorityTest`
pins both halves — a 3+-point explicit lead survives maximum opposing Taste, a 1-point one does not.
So **any stored pick on the searched criteria still bypasses the rerank** and
the page shows the standard Best Match order (`explicit_criteria`). The check runs before the Taste
profile is read, ignores whether the picker is switched on right now (a request is still a request
while its control is hidden), and fails closed for a criteria type it cannot check. It is lifted —
by a governance edit, not a flag — only once an explicit-pick lead can no longer be crossed: for
example by withholding Taste signals on the very tags the seeker picked and giving every explicit
lead the 3-point floor, or by confining Taste to results that tie on the picks. Neither exists yet.

### Where it runs, and why there

`StellarBuyerResultsController::index()` → `TasteRerankingService::rerankStellarResults()`, **after**
`BuyerResultViewMapper::map()` and **before** the in-memory `array_slice` that makes a page. The matcher
already scores every eligible candidate (≤ 200) and sorts them before that slice, so reordering the
whole list there means page 1 is genuinely the top of the personalized order and no listing is
duplicated or lost between pages. Nothing is loaded that the matcher did not already load.

**BYO search is out of scope, deliberately — a scope boundary, not an unfinished bug.** `/search/seller-listings` and `/search/rental-properties`
have **no match score**, offer only explicit sorts (`newest` default, `most_viewed`, `ending_soon`) and
paginate in SQL. There is no Best Match order to refine, every order there is an explicit choice, and
reordering after SQL pagination could only shuffle one page. Personalizing BYO needs a native property
score first. Seller, Landlord, Virtual Drive, Explore and Ask AI are untouched.

### The bounded-influence rule

`TasteDnaReranker` (pure, container-free, no database) gives each candidate an ordering adjustment in
**[−1.25, +1.25] points** and sorts by (base score + adjustment), ties broken by the matcher's original
position. So a listing can pass another only when its base score is **less than 2.5 points lower**. The
Stellar total is an integer, so **a gap of 3 or more points is never crossed** — an 88 cannot pass a 98,
or a 91.

Why 2.5, on this scale: the Stellar total is an integer sum of eight capped categories. Its smallest
distinctions are one-point items — one lifestyle signal (new construction, energy efficiency, pets, one
community feature), a view, a water view. From three points up the matcher is recording something
substantive: a garage the buyer asked for, a subtype match, a band of price proximity. Taste may decide
between listings the matcher considers near-equal (0–2 points apart) and never between listings it has
separated. A production score distribution was **not** read to choose this (the production database is
off-limits to development tooling); the rule is derived from the scorer's own granularity and is pinned
by a deterministic randomized test over 200 × 25 candidates.

Crossing any gap needs strong evidence:

| Evidence on one listing | Adjustment | Can cross |
|---|---|---|
| one emerging correlation (seen on homes, never stated) | 0.156 | exact ties only |
| one established stated reason | 0.625 | exact ties only |
| two established stated reasons | 1.25 (saturated) | a 1-point gap |
| saturated positive here AND saturated negative on the other listing | 2.5 swing | a 2-point gap |

Weights: `established` 1.0, `emerging` 0.5; a signal the customer supported with a stated reason 1.0,
an observed-only correlation 0.5 (Phase 4's own 1.0 / 0.5); raw evidence saturates at 2.0. Constants
live in `TasteDnaReranker`, versioned by its `RULES_VERSION`.

### Which signals may act

* **Confidence** `emerging` or `established` only — `insufficient` never acts, so one choice never
  moves anything (Phase 4 already needs two homes for `emerging`).
* **Direction** `positive` or `negative` only — `mixed` and `uncertain` contribute nothing.
* **Dimensions: Smart Tags and structured property sub-type only.** A tag must be
  `isSeekerSelectable()` **now**, asked of the taxonomy by the reranker itself, so `accessible_features`,
  `playground`, retired and pending-review tags (e.g. `gated_community`) cannot act; `natural_light`
  acts and stays non-derivable. A sub-type matches through `TasteDnaDeriver::subtypeKey()`, the same
  cleaning and compliance check Phase 4 learns with.
* **Excluded, and still shown on Your Home Taste:** `reason` signals (price, size, fees — the
  customer's criteria, already scored explicitly; a learned price tendency would read as affordability)
  and the numeric bands (bedrooms, bathrooms, living area, lot size — observed-only, correlated with
  price, and a learned "usually 2 bedrooms" must never rank against an explicit "3+ bedrooms").
* **An absent characteristic is not evidence.** A negative signal demotes only a listing that HAS the
  characteristic; a listing with no facts (feed-refused, unpublished) is not moved.
* **No location of any kind.** No Important Places (their structural link to a preference does not
  exist), no neighbourhood, school, demographic or proximity signal, and nothing from another customer.

### Current Save / Maybe / Pass — shown, not scored

The customer's current state on a result is **UI state only**. It is not a boost, a penalty or a
filter: the same choice already reached the learned profile once, and scoring it again directly would
count one click twice. A Passed listing remains in the results; if it has characteristics the customer
tends to Pass on, learned taste may lower it within the bound — like any other listing.

### Transparency and control

Beside "sorted by **Best Match**" the page says **"Personalized with Your Home Taste · Show standard
Best Match order"** (the link adds `taste=off`, keeps the criteria, drops the page). Opted out, it says
**"Standard Best Match order · Personalize with Your Home Taste"**. A listing Taste RAISED, on evidence
of its own, shows up to two sentences from `TasteRerankExplanation` about what it has that the
customer tends to Save — *"Natural Light is a reason you have picked when Saving homes."* for a stated
reason, *"Fireplace appears often in homes you Save."* for a correlation — never a number,
percentage, key, id or confidence tier, and never the word AI. A lowered listing is **not** explained
(no "you usually Pass on this" on a card), nor is one that moved only because a neighbour moved.
Nothing is rendered when the feature is off, the viewer is not a matching seeker, an explicit sort is
chosen, or their taste has nothing to act on. Your Home Taste's own page says ordering may change when
reranking is on.

### Determinism, identity, performance

Same candidates, scores, profile and facts → same order; equal keys keep the matcher's order; no
randomness, no clock. Identity is inherited, not rebuilt: history is grouped by the stored
`subject_key` exactly as Phase 4 groups it, so a Bridge row and its MLS-linked BYO listing are one
home and two listings at one address are two. PR #192's whitespace behaviour is pinned as-is
(`TasteRerankingIdentityTest`: padded keys trim to one provider-scoped subject; native linkage matches
the stored key exactly) and **no history is rewritten and no migration exists**. Candidate facts come
from rows the matcher already loaded plus **one** Smart Tag query (`TasteListingFactsReader::forBridgeRows()`);
the profile read is bounded by the customer's own history. With any gate closed there are no extra
queries; with all open the count does not grow with the number of results (tested at 10 vs 150).

### Flags

`LISTING_PREFERENCE_TASTE_RERANKING_ENABLED` (default `false`, fail-closed — ON only for
`true`/`1`/`on`/`yes`) **and** `LISTING_PREFERENCE_TASTE_DNA_ENABLED` **and**
`LISTING_PREFERENCES_ENABLED`, read only through `TasteDnaAvailability::rerankingEnabled()`, each
`=== true`. Taste DNA's page runs with reranking off. None may be added to
`config/required_production_flags.php`.

### Still prohibited

Everything in §6. Ask AI consumption, recommendations, "more like this", filtering or hiding by taste,
BYO ranking, location learning, price learning in ranking, and any use of the numeric bands in ranking
— each needs its own governance revision.
