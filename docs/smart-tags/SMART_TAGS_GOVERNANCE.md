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

## 11. Explicitly out of scope for Phase 1

Import / sync / save hooks, backfill, production remarks or description processing, any picker UI,
search and ranking, cards and detail pages, Explore, Matching V2, Location DNA,
Virtual Drive, Taste DNA, Find More Like This, natural-language search, image or
vision analysis. Behavioural learning additionally requires its own governance revision.

**Update, 2026-09-16 — Buyer/Tenant preferences.** What this section called
`smart_tag_preferences` and "Love/Maybe/Pass" now exists as a separate subsystem:
**Save | Maybe | Pass**, governed by
[`docs/listing-preferences/LISTING_PREFERENCE_GOVERNANCE.md`](../listing-preferences/LISTING_PREFERENCE_GOVERNANCE.md).
Three points matter here:

* **Customer terminology is Save | Maybe | Pass.** "Love" is superseded and must not reappear.
* **It is not a second vocabulary and not a Smart Tag table.** Preference reasons live in
  `config/listing_preference_reasons.php` and *link* to canonical tag keys; a reason about a
  property characteristic with no canonical tag is a taxonomy change made here, under §12, never an
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

## 12. Changing the taxonomy

1. Add or edit the tag in `config/smart_tags.php`; add rules in `config/smart_tag_sources.php`.
2. Run `php artisan test tests/Unit/SmartTags tests/Feature/SmartTags tests/Feature/FairHousing/SmartTagTaxonomyComplianceTest.php`.
3. A change to either file changes the tagger version, which marks every listing stale for re-derivation
   once derivation is wired.
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
