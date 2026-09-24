# Smart Tags — Governance

Status: **Phase 1 foundation — inert.** Nothing in the application writes, reads or displays
Smart Tags yet. This document is the governance revision the Property DNA Phase 2 plan requires
before any new inference system is built.

## 1. What Smart Tags are

One governed, closed taxonomy of **property characteristics** (`private_pool`, `quartz_countertops`,
`loading_dock`, `cleared_land`, …) shared by every listing source and every future consumer:

| Consumer | Uses the same canonical keys |
|---|---|
| Bridge / MLS rows (`bridge_properties`) | ✓ |
| Native Seller Offer Listings (`seller_agent_auctions`, `workflow_type = offer_listing`) | ✓ |
| Native Landlord Offer Listings (`landlord_agent_auctions`, `workflow_type = offer_listing`) | ✓ |
| Buyer / Tenant preferences (later phase) | ✓ |

**One key, one meaning.** A key means the same thing whichever source attached it. A phrase that
means different things in different property types becomes separate keys with disjoint contexts
(`turnkey_home` / `turnkey_business`, `fenced_yard` / `fenced_lot`).

There is no Seller, Landlord, Buyer, Tenant or MLS vocabulary. There is one taxonomy.

## 2. Where things live

| Concern | File | Single reader |
|---|---|---|
| Taxonomy (keys, labels, contexts, surfaces, compliance) | `config/smart_tags.php` | `SmartTagConfig` |
| Source rules, value dictionaries, description phrases, forbidden keys | `config/smart_tag_sources.php` | `SmartTagConfig` |
| Prohibited-concept patterns | `App\Support\SmartTags\SmartTagComplianceGuard` (code, deliberately) | — |
| Listing-type registry | `App\Support\SmartTags\SmartTagListingType` | — |

The prohibited-concept patterns live in code so an edit to the taxonomy config cannot relax them.

## 3. Contexts

Every tag declares the contexts it applies to. Every listing and search resolves to at most one.

| Context | Bridge `PropertyType` | Seller `property_type` | Landlord `property_type` |
|---|---|---|---|
| `residential.sale` | Residential | Residential | — |
| `income.sale` | Income | Income | — |
| `commercial.sale` | Commercial Sale | Commercial | — |
| `business.sale` | Business Opportunity | Business | — |
| `land.sale` | Vacant Land | Vacant Land | — |
| `residential.lease` | Residential Lease | — | Residential Property |
| `commercial.lease` | Commercial Lease | — | Commercial Property |

Resolution is exact (`SmartTagContextResolver`). For native listings the transaction comes from the
role, never from the property-type string. **An unknown or unsupported property type has no context:
nothing is derived, nothing is selectable.**

## 4. Tag definition fields

`contexts`, `mls_derivable`, `native_derivable`, `owner_selectable`, `seeker_selectable`,
`public_display`, `negatable`, conflict membership, `compliance.status` (`approved`, `restricted`,
`pending_review`), `compliance.note`, `compliance.notice`.

- `mls_derivable` / `native_derivable` must match the rules that exist — a test enforces it.
- `restricted` — approved with surface limits, explained in `compliance.note`.
- `pending_review` — may be derived as evidence, but is **not selectable and not publicly displayed**
  until reviewed.
- `notice` — fixed copy that must accompany display (e.g. `pets_allowed`: *Assistance animals are not
  pets and are not governed by pet policies.*).

Keys are permanent. Retire with `status => 'retired'`; never rename or reuse a key.

## 5. Sources and precedence

| Rank | Source | May assert "not present"? |
|---|---|---|
| 1 | `structured_mls` | yes (negatable tags only) |
| 2 | `structured_native_listing` | yes (negatable tags only) |
| 3 | `manual_listing_owner` | **no** |
| 4 | `mls_remarks` / `native_listing_description` | **no** |

Evidence is stored per source (`smart_tag_evidence`); the resolver projects exactly one canonical row
per listing × tag (`smart_tag_assignments`). The strongest source with an opinion decides; a weaker
disagreeing source sets `has_conflict`. Two present tags declared to conflict cannot both stand: the
stronger source survives and records the tag it overrode; at equal strength neither survives.

## 6. Unknown does not mean No

There are three states: **Confirmed Present**, **Unknown**, **Confirmed Not Present**. Unknown is the
absence of a row. Only an authoritative structured field that explicitly says "No" creates Confirmed
Not Present. A description that does not mention a feature, a feature missing from a checklist, and
an owner who did not select (or deselected) a tag are all Unknown.

## 7. Manual owner tags

Written only by `ManualSmartTagWriter` (no UI in Phase 1). A selection must be a canonical, active,
owner-selectable key applicable to the listing's **stored** context, and must not be a tag the
listing's own authoritative Yes/No field already answers — the owner edits Property Details instead.
Authorization is the existing Offer Listing rule: owner only, Offer Listing rows only, not archived.
Every change appends a `smart_tag_manual_events` row. Manual entry never bypasses the taxonomy.

## 8. Descriptions

`ListingDescriptionTagParser` is deterministic phrase matching: controlled synonyms, negation windows,
qualifier suppression, context-specific rules, conflict groups. It can only emit keys with rules. No
AI, no network, no model.

**Native description field** (verified 2026-09-15): meta `additional_details` for both Seller
("Property Description") and Landlord ("Rental Description"). Landlord prose is read only through
`LandlordProviderTextPolicy::displayValue()`: text withheld from the public page is never parsed. No
other free-text field may be parsed — screening, qualification, approval conditions, pet/breed text,
neighbouring tenants, clientele, business use, compatibility preferences, deal breakers, broker notes
and every `other_*` / `custom_*` box are forbidden sources (test-enforced).

**MLS PublicRemarks** is licence-RESTRICTED (`MlsFieldCatalog`). The parser supports it for testing,
but `SmartTagDerivationService::MLS_REMARKS_PROCESSING_APPROVED` and
`SmartTagEvidenceWriter::MLS_REMARKS_PERSISTENCE_APPROVED` are both `false`. Enabling either is a
reviewed code change after a separate licensing decision — not a configuration edit. No production
remarks census, storage, display or third-party transmission.

## 9. Fair Housing

Smart Tags describe the property — never people, protected classes, demographics, neighbourhood
quality, proximity to places of worship, schools as quality signals, or "type of people" nearby.

- `SmartTagComplianceGuard` scans every key, label, description and description phrase.
- Prohibited keys are refused by the selection policy and the evidence writer.
- **55+ / 62+** is a legal compliance gate (`SeniorCommunityComplianceGate`), never a tag;
  `leasing_55_plus` and `SeniorCommunityYN` are not sources.
- **Accessibility** may describe the property (owner-selectable) but is never a Buyer/Tenant
  preference; `accessibility_requirements` is never a source.
- **Playground** is owner-describable, not a seeker preference in V1.
- **Pets** tags carry the assistance-animal notice; "not present" must never be displayed as excluding
  assistance animals.
- Proximity (schools, transit, highways, marinas, golf nearby) is Location DNA, not Smart Tags.
- Ranges and terms (price, beds/baths, acreage, zoning, lease type, business type, unit counts,
  ceiling-height bands) remain structured search criteria.

## 10. Reprocessing and cost

`smart_tag_derivation_states` holds independent hashes for structured inputs, MLS remarks, native
description and the tagger version (taxonomy + rules + engine version). A source is re-derived only
when its own input, the tagger version or the listing's context changes; unchanged prose is never
reparsed. Derivation is local CPU only — no provider calls at import, save, search or render.

**Structured hashes are taken over INTERPRETED values, not raw stored ones.** An accessor's
`inputsFor()` must give equal output for two records the rules would read identically, whatever shape
the store handed back. `BridgeRecordAccessor` therefore reads each rule through the same accessor
method the rule engine will use for that rule's kind — `boolean`, `scalar`, `values`, `number`,
`flag` — and keys each entry by that reading, so one field read two ways keeps both interpretations
and kinds that share a reading collapse to one.

This is not tidiness. `bridge_properties.waterfront_yn` and `pool_private_yn` are boolean columns that
come back as PHP `true` from a just-written model and as `1` from a re-read row. Hashing the raw value
made the same unchanged listing produce two different hashes depending on which code path had looked
at it, so a caller deriving from a freshly written model and a caller reading rows fresh each
re-derived what the other had already done. The tags were never wrong — re-derivation is idempotent —
but "unchanged input skips re-derivation" was not true across those paths.

`true`, `1`, `"1"`, `"true"`, `"Y"` and `"yes"` all canonicalise to the same hash because
`BridgeRecordAccessor::toBool()` already says they mean the same thing; this changes no vocabulary,
it stops hashing before interpretation. An unrecognised value canonicalises to UNKNOWN and never to
YES. A rule kind with no declared reading falls back to the raw value rather than being dropped,
because change detection that silently stops watching a field is the worse failure.

## 11. Explicitly out of scope

Production remarks processing, any picker UI, search and ranking, cards and detail pages, Explore
presentation, Matching V2, Location DNA, Virtual Drive, Taste DNA, Find More Like This,
natural-language search, image or vision analysis. Behavioural learning additionally requires its
own governance revision.

**Update, 2026-09-16 — save hooks and the backfill are no longer out of scope.** Phase 2 wired
derivation into the native publish paths, the single-record Bridge lookup and a `smart-tags:derive`
command; §12 governs it. Everything else in the list above is still out of scope, and Phase 2 added
no UI, no ranking and no search behaviour. MLS **sync** remains unwired — see §12.

**Update, 2026-09-16 — Buyer/Tenant preferences.** What this section called
`smart_tag_preferences` and "Love/Maybe/Pass" now exists as a separate subsystem:
**Save | Maybe | Pass**, governed by
[`docs/listing-preferences/LISTING_PREFERENCE_GOVERNANCE.md`](../listing-preferences/LISTING_PREFERENCE_GOVERNANCE.md).
Three points matter here:

* **Customer terminology is Save | Maybe | Pass.** "Love" is superseded and must not reappear.
* **It is not a second vocabulary and not a Smart Tag table.** Preference reasons live in
  `config/listing_preference_reasons.php` and *link* to canonical tag keys; a reason about a
  property characteristic with no canonical tag is a taxonomy change made here, under §13, never an
  edit there. Price, size and proximity stay out of this taxonomy, as §9 already requires — the
  reason vocabulary carries them in its own `criteria` and `location` dimensions.
* **`seeker_selectable` is now load-bearing.** It was inert; it is the gate that keeps
  `accessible_features` and `playground` out of the customer-facing chip list, and the preference
  catalog inherits it rather than restating it. Clearing that flag on a tag removes its chip.

Behavioural learning remains out of scope and still requires its own governance revision. The Fair
Housing half of that revision is §6 of the listing-preference governance document, which prohibits
user-to-user similarity learning, collaborative neighbourhood or location learning, neighbourhood
demographic inference, geographic clustering of preference outcomes and protected-class preference
inference. A learner additionally needs its decay model, retention policy and audit surface reviewed.

## 12. Phase 2 — lifecycle wiring

Phase 2 activated derivation for published native Offer Listings and single-record Bridge lookups.
It added no taxonomy, no rules, no tables, no migration and no UI.

**Two gates, both default `false`, both fail-closed** (`true`/`1`/`on`/`yes` only; unset, empty,
`false`/`0`/`off`/`no` and anything malformed are OFF): `SMART_TAGS_DERIVATION_ENABLED` is the master
and `SMART_TAGS_BRIDGE_ENABLED` is an **additional** gate for `bridge` rows, never a replacement —
enabling tagging for our own listing forms must not also start tagging a licensed MLS feed.
`App\Support\SmartTags\SmartTagWiring::enabledFor($type)` is the only gate. Neither flag may be
named in the deploy-time production flag contract: that contract may never name a safety switch.

**One seam.** `SmartTagLifecycle` is the only class application code may call, through the static,
non-throwing `tryDeriveNative()` / `tryDeriveBridge()` / `tryPurge()`. No call site touches the
derivation service, evidence writer, resolver, projector or purger; a guard test asserts it, and a
second guard names every file permitted to reference Smart Tags at all. Listing Preferences (§11) is
a taxonomy READER and is held to the same no-internals rule.

**Failure isolation is the contract.** Smart Tags are secondary derived data; the listing or property
save is primary. A Smart Tag failure may never fail or roll back a listing save, a publish, a Bridge
upsert or a deletion. Every call therefore happens *after* the primary write has committed, never
inside its transaction — the draft purge calls the lifecycle after `DB::transaction()` returns — and
the static shim additionally swallows a failure to RESOLVE the lifecycle from the container, which an
instance method cannot.

**Derived on publish, never on a draft.** The Create and Edit wizards set `SAVE_AS_NEW_DRAFT = true`,
so every draft save inserts a NEW listing row and leaves the previous one; deriving there would mint
evidence for unpublished versions that nothing reads. A listing acquires Smart Tags when it becomes a
listing. This is the rule Location DNA already applies to the same wizards.

**Where derivation runs.** Inline, after persistence — the queue connection is `sync` on this
deployment, so a job would be inline with extra indirection, and a listing save must never depend on
a worker that does not run.

| Path | Derives? |
|---|---|
| Seller and Landlord Offer Listing publish (`store()`, `update()`) | yes |
| MLS quick-import publish | yes |
| Bridge single-record lookup (`BridgeListingLookupService`) | yes |
| Every draft save | **no** |
| Explore viewport discovery, Buyer/Tenant criteria search | **no** — `deriveSmartTags: false` |
| `bridge:import-properties` | **no**, unless `--derive-smart-tags`, and the gates still apply |

**Deferring is not suppressing.** The high-volume Bridge paths can upsert hundreds of rows inside a
request somebody is waiting on, and none of them renders Smart Tags, so they opt out and
`php artisan smart-tags:derive --only-stale` catches those rows up. `smart_tag_derivation_states` IS
the staleness ledger: a row with no state has simply never been derived. Tagging belongs to the
listing lifecycle and to backfill — never to search, a card, a detail page or a map movement.

**Bridge structured hashes use canonical INTERPRETED rule values**, as §10 describes. That is what
makes "unchanged input skips re-derivation" true for a Bridge row whichever code path read it.

**The backfill command** is idempotent, resumable (`--from-id`, ordered by primary key, `chunkById`)
and batch-oriented; one failing listing is counted and the batch continues; `--dry-run` reaches no
writer at all rather than checking a flag inside one. **A production write requires a real
interactive terminal** — both an interactive input and a TTY on STDIN — so a cron entry, CI step,
queued job or agent invocation ABORTS rather than proceeding. There is no override token.

**Manual evidence is never touched by automatic derivation.** `replaceDerived()` deletes only the one
source being rewritten, so `manual_listing_owner` rows are outside every automatic delete, and the
purger keeps the append-only manual event audit. §5's precedence still decides what an owner's
selection means against an authoritative structured "No".

**Native descriptions follow §8 unchanged.** The Seller description is meta `additional_details`; the
Landlord description is the same key read ONLY through `LandlordProviderTextPolicy::displayValue()`,
so prose the policy withholds from the public page is never parsed. Removing a description removes
its evidence and re-resolves the listing.

**MLS PublicRemarks remains hard-disabled.** `MLS_REMARKS_PROCESSING_APPROVED` and
`MLS_REMARKS_PERSISTENCE_APPROVED` are both still `false`, Phase 2 changed neither, and tests assert
both the constants and the writer's refusal.

**Telemetry** is one structured `smart_tags` line per decision: outcome, listing type and id, context,
entry point, which sources ran, tag and conflict counts, an abbreviated tagger version, duration, and
an exception CLASS on failure. **No prose ever** — the description is reported as a boolean, and a
test seeds distinctive sentences and asserts no fragment reaches a log. Backfills log per batch.

**`MlsListingSyncService` is deliberately NOT wired.** Its three flags are off, so nothing drifts
today — but a sync writes native structured facts, and **Smart Tags would go stale if MLS sync were
activated without lifecycle integration**. Wire it before that activation; until then `--only-stale`
is sufficient.

**Still not built:** no Buyer/Tenant or owner Smart Tag picker, no Must Have / Prefer UI, no Smart Tag
search filtering, ranking or match percentages, no listing-card or detail-page Smart Tag UI, and no
cross-source deduplication between a Bridge row and the native listing that was imported from it.
Each source keeps its own evidence.

## 13. Changing the taxonomy

1. Add or edit the tag in `config/smart_tags.php`; add rules in `config/smart_tag_sources.php`.
2. Run `php artisan test tests/Unit/SmartTags tests/Feature/SmartTags tests/Feature/FairHousing/SmartTagTaxonomyComplianceTest.php`.
3. A change to either file changes the tagger version, which marks every listing stale. Re-derive
   with `php artisan smart-tags:derive --only-stale` (dry-run first); see §12.
4. A new sensitive concept needs `compliance.status` other than `approved`, with a note, and review.
5. A tag that is `seeker_selectable` becomes eligible to back a preference reason chip. Confirm that
   is intended: the chip is customer-facing on public pages.

### Change log

**2026-09-16 — added `natural_light`** (`interior`, residential contexts; taxonomy version
`2026-09-15.1` → `2026-09-16.1`).

Added as a real property characteristic so the Save/Maybe/Pass reason "Natural light" links to a
canonical key instead of minting a parallel one. Owner- and seeker-selectable; compliance
`approved` — `SmartTagComplianceGuard` reports no violation in the key, label or description.

It ships **non-derivable by review decision**, so both `mls_derivable` and `native_derivable` are
`false`. Until a source rule is separately reviewed there is to be:

* **no phrase rule**,
* **no photo or vision inference**,
* **no marketing-copy inference**.

Interior daylight is claimed in prose far more often than it is recorded in a structured field, and
any of those written before that evidence is reviewed would manufacture confident tags out of sales
language. `SmartTagSourceRulesTest` asserts the flags and the rules agree in both directions, so the
flags can only flip in the same change that adds the rule — a later, separately reviewed edit to
`config/smart_tag_sources.php`.

Generic **"Style"** was considered alongside it and **rejected for V1**: there is no well-defined
style taxonomy, and a vague style tag would be precisely the duplicate vocabulary §2 forbids.
Specific architectural tags remain available to add individually.

## 14. Seeker Smart Tags in Match DNA

A Buyer/Tenant's "Property Features You Want" picks are **scored, never filtered**. The picker
promises a preference ("Optional … we will use them when we look for a match … Leaving this empty
simply means no feature preference"), not a requirement, so no pick removes a listing and result
membership is unchanged.

**Allocation.** The picks are one expressed item inside `BuyerMatchScorer`'s existing 10-pt Amenities
category, weighted 4 (`SEEKER_FEATURES_MAX_PTS`, like the pool) and earning `4 × matched ÷ selected`
before the category's own normalisation. Amenities still tops out at 10 and the total at 100 however
many tags are picked; with no other amenity expressed the picks are the whole category; with no picks
the score is exactly the pre-feature one.

**Two gates, deliberately separate.** `SMART_TAGS_SEEKER_PREFERENCES_ENABLED` shows the picker and saves
picks; `SMART_TAGS_SEEKER_MATCHING_ENABLED` (default off, fail-closed, an ADDITIONAL gate) lets them score.
`SmartTagSeekerPreferenceGate::matchingEnabled()` requires both; the reader and `BuyerCriteriaPayload` each
ask it, so with matching off every result is identical to the pre-feature score and no listing tag is read.
Rollout: store picks → derive and backfill Bridge tags → verify coverage → enable matching. Neither gate
may enter the production flag contract.

**Seeker side.** `SmartTagSeekerPreferenceReader::matchingKeysFor()` is the one read: both gates must be
on (a hidden control cannot steer results, and saved picks are not scored early), and every stored key is
re-projected through `SmartTagSelectionPolicy` on `SURFACE_SEEKER` against the record's **current**
context, so a tag later retired, put under review, made non-seeker-selectable or made inapplicable
stops contributing on the next search. `BuyerCriteriaPayload` governs the keys again, context-free.
All four Stellar loaders (Buyer/Tenant × Criteria/Offer Listing) use it, so the results page, the
property-detail match context and Match Check score alike.

**Listing side.** Present rows of `smart_tag_assignments` only, read in batch by `ListingSmartTagIndex`
(two queries per 500 candidates, none when nothing is picked) — never evidence, remarks, descriptions,
free text or photos, and nothing is derived at match time. Stellar candidates are Bridge rows; BYO
listings have no score, and a Bridge row is never merged with a BYO listing that shares its MLS key.

**Unknown earns what known-absent earns: nothing.** That is the Amenities category's own rule — a pool,
garage or waterfront the feed did not report scores exactly as a reported "No". Excluding the picks
from an untagged listing's denominator instead would give it the no-picks score (the full 10 when
nothing else is expressed), above a tagged listing matching some picks: positive credit for unknown.
The two states differ only in wording: "Feature details not available — your selected features could
not be checked" versus "Does not list: …". Inventory-wide missing enrichment is handled by the matching
gate, not by scoring.

**Deduplication.** `BuyerMatchScorer::STRUCTURED_TAG_EQUIVALENTS` is the one, narrow map between the
legacy structured criteria and the tag derived from the same column: `private_pool`↔pool,
`garage`↔garage, `waterfront`↔waterfront (Amenities, suppressed when the criterion is expressed),
`new_construction`↔new construction and `pets_allowed`↔pet-friendly (Lifestyle, suppressed when the
criterion is `true`, the only value Lifestyle scores). The structured criterion stays authoritative;
the tag leaves the pick set for that search; unrelated picks are untouched. Not equivalent, on purpose:
`water_view`, `heated_pool`, `community_pool`, `oversized_garage`, `carport`, `solar_power`, and free-text
community keywords. It is not an eligibility list — eligibility is the taxonomy and the policy.

Cards name features by label only — no key, id or weight.

**Taste.** Scoring the picks did not lift Phase 5's explicit-pick bypass: a pick can open a lead
under the rerank's 3-point floor. See `LISTING_PREFERENCE_GOVERNANCE.md` §14.
