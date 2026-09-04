# Stellar MLS Import — Complete Data Parity Implementation

> Companion to `AUDIT.md`. Branch `audit/stellar-mls-import-complete-payload`,
> worktree `/home/runner/listing-marketing-ai-mls-import-audit`, audited base `d342f5447`.
> **No migration. Not merged, not deployed.**

---

## The three tiers, as built

```
Bridge /Property  (553 fields, no $select — the payload is complete)
        │
        ├── raw_json                      100% preserved, unchanged
        │
        └── MlsFieldCatalog               ← the single classification authority
              │
              ├── TIER 1  TIER1_BYO ................ 61 fields → an editable Create Offer field
              │                                       via MlsFieldMap, unchanged storage
              │
              ├── TIER 2  PROPERTY_FACTS ........... 12 sections, no editable equivalent
              │           LISTING_CONTEXT .......... MLS #, status, dates, DOM, tour
              │           CONTACTS ................. agent / brokerage / HOA attribution
              │             ↓
              │           MlsSupplementalDetails → one meta blob `mls_property_details`
              │             ↓
              │           _mls_property_facts.blade.php  (ONE partial, three surfaces)
              │
              ├── TIER 3  Media (inline) · Member · Office · OpenHouse
              │           BridgeRelatedResourceService, cached per member/office KEY
              │
              └── withheld: DISPLAY_CONTROL · ADDRESS_COMPONENT · INTERNAL ·
                            RESTRICTED · DERIVED   (each with a written reason)
```

Every populated field lands in exactly one of those, and
`MlsNoFieldDropContractTest` fails the build naming any that does not.

## Live evidence

`php artisan mls:probe-resources --force-probe`, 2026-09-04, against the real dataset:

| Resource | Result |
|---|---|
| `Property` | HTTP 200, **552 fields** |
| `Member` | HTTP 200, **79 fields** |
| `Office` | HTTP 200, **55 fields** |
| `OpenHouse` | HTTP 200, **36 fields** |
| `Room` | **HTTP 404 — not exposed** |
| `Unit` | **HTTP 404 — not exposed** |

End-to-end against live Bridge records, read-only:

| Property type | MLS Detail rows | Sections | **Blank rows** |
|---|---|---|---|
| Residential | **90** | 12 | **0** |
| Residential Lease | **75** | 10 | **0** |
| Commercial Sale | **83** | 13 | **0** |
| Business Opportunity | **48** | 9 | **0** |
| Listing with an open house | **65** | 11 | **0** |

A real open house rendered from the OpenHouse resource:
`Sat 14 Mar 2026 · 2:00 PM – 7:00 PM · Public · In Person`

Before this work the same listings carried **41 facts and nothing else**.

## MLS photographs — authorised by the owner, 2026-09-04

Both flags now default **true** in `config/mls_media.php`, so permitted MLS
photographs reach imported listings. The 50-image ceiling that truncated 186 of
1,202 cached listings is 250; each media object's own `Permission` is read and
honoured; ordering and cover selection are pinned; re-import is idempotent; user
uploads are untouchable; hosting is reference-only and no bytes are copied.

**On what authority.** An owner product/policy decision on 2026-09-04, which
explicitly superseded the photo clause of the locked 2026-07-05 policy. A
licence-flag audit run immediately beforehand found **no written Stellar
approval** in this repository addressing public imported-listing photo use, and
found that a prior document's claim to the contrary rested on a misread
configuration state. Nothing here should be read as evidence that Stellar has
approved this specific use. The full record — including what the audit did and
did not find — is in `docs/mls-direct-import-design-and-plan.md` under
"Owner decision — 2026-09-04".

**Production environment variables were not changed.** The defaults live in
config; if production supplies `MLS_MEDIA_LICENSE_ACKNOWLEDGED` explicitly, that
value still wins and would need setting at deploy time.

**The decision overrides our posture, never the feed's.** `IDXParticipationYN`,
`InternetEntireListingDisplayYN`, `InternetAddressDisplayYN` and per-media
`Permission` are all still enforced; a media object not marked `Public` is still
refused.

**Attribution now ships with the photographs.**
`resources/views/offer-listing/partials/_mls_attribution.blade.php` renders the
Stellar/Bridge provenance and copyright block on MLS-sourced Seller and Landlord
listings, modelled on the authenticated Stellar page's own block. It is gated on
import PROVENANCE, so a manually created listing or one from the Listing Link
importer never claims Stellar provenance it does not have.

## The two contradictions the audit found, resolved

1. **`ListOfficeName` was withheld on import and published on
   `/stellar/property/{key}`.** Resolved toward publishing: IDX display rules
   generally *require* brokerage attribution on a displayed listing, which is
   why the Stellar page already did it. Attribution now renders on both
   surfaces, gated on the feed's own display permissions.

2. **Photographs were blocked from import by two flags and published on the
   Stellar page under none.** Resolved on 2026-09-04 by owner decision, in the
   direction of publishing — see the section above. The asymmetry that made it a
   contradiction rather than a simple gap (public vs authenticated audience, and
   attribution present on one page and absent on the other) is closed too: the
   imported listing now carries the same Stellar/Bridge attribution block.

## Compliance posture, unchanged where it should be

Still never rendered anywhere: `PublicRemarks` and every remarks variant,
`PrivateRemarks`, showing instructions, lockbox details, escrow contacts, the
showing call-centre number, tenant identity, broker compensation, and the
**counterparty** — every `BuyerAgent*`, `CoBuyerAgent*` and `BuyerOffice*`
field. Each carries a stated reason in `MlsFieldCatalog::RESTRICTED`, and
`MlsNoFieldDropContractTest` proves none of them reaches the persisted payload
on any of the seven property-type fixtures.

Newly enforced: `InternetAddressDisplayYN` (false on **71 of 1,202** cached
records) and `InternetEntireListingDisplayYN`, on the import path, the listing
pages, the Stellar detail page and the Stellar results card. The address is
still imported, still stored, still drives the coordinate ladder, and is still
shown to the listing's owner — it is withheld from the public, which is what
the feed actually asked for.
