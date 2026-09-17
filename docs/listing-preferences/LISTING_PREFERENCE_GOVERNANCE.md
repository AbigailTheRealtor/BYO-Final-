# Listing Preference Governance — Save | Maybe | Pass

Status: **Phase 2 — capture, behind a default-off flag.** Authenticated Buyers and Tenants can
Save, Maybe or Pass a listing, give optional structured reasons, and undo, from the BidYourOffer
Seller/Landlord property-detail pages. `LISTING_PREFERENCES_ENABLED` ships **false**, and off means
the routes 404 and no control renders.

Still not built, and still governed: ranking influence, Ask AI consumption, Virtual Drive, and
**all behavioural learning** (§6, §10).

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

No learning or ranking code exists. When it is built:

- `config/match_scoring.php` requires **all enabled weights to sum to 100**, and `BuyerMatchScorer`
  has fixed category caps. Preference must **not** become a new scoring category.
- The precedent is `ImportantPlaceMatcher`: it **scores, it never selects** — shares a slot by
  `max()`, can only raise a score, and leaves the SQL geography untouched.
- Preference therefore applies as a **post-score re-rank** and a separately displayed signal.
  Passed listings are demoted or filtered **at presentation only**; nothing is excluded in SQL and
  nothing is deleted.

---

## 9. Feature flags

`config/listing_preferences.php`. **Absence is OFF**, and every gate fails closed.

| Flag | Default | Governs |
|------|---------|---------|
| `LISTING_PREFERENCES_ENABLED` | `false` | the master gate — capture, display, learning |
| `listing_preferences.guest_capture_enabled` | `false` | guest records (§7) — a recorded decision |
| `listing_preferences.learning_enabled` | `false` | behavioural learning — blocked by §6 and §10 |

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
| 4 | behavioural learning | §6 + §10 governance revision |
| 5 | ranking integration as post-score re-rank; Ask AI consumption | Phase 4 + weight-invariant tests |

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
