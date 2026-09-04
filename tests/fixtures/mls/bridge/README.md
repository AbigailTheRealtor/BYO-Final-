# Bridge/Stellar Property fixtures — one per supported property type

These seven files are complete, decoded Bridge `Property` records, one for each
Stellar category the platform supports. They exist so the MLS import contract can
be tested against the shape the feed actually has rather than against a
hand-written record that only contains the fields somebody remembered.

| File | Populated fields | Provenance |
|---|---|---|
| `residential.json` | 208 | real cached record |
| `residential_lease.json` | 208 | real cached record |
| `commercial_sale.json` | 215 | real cached record |
| `business_opportunity.json` | 199 | real cached record |
| `income.json` | 221 | **composed** |
| `commercial_lease.json` | 227 | **composed** |
| `vacant_land.json` | 206 | **composed** |

## Real records are scrubbed

Every value that names or contacts a real person is replaced with a synthetic
one before the record is written here: `ListAgent*`, `ListOffice*`,
`CoListAgent*`, `CoListOffice*`, `Association*`, `STELLAR_PropertyManager*`,
`STELLAR_Escrow*`, `STELLAR_TenantName/Phone`, the call-centre number, and the
remarks and directions columns. `ListingKey`, `ListingId`,
`STELLAR_UniversalPropertyId` and the media URLs are replaced so no fixture
identifies a real property or points at real imagery.

Everything else — every field NAME, every enumeration value, every shape — is
exactly as Stellar sent it. That is the part these fixtures exist to preserve.

## Three are composed, and why

The local `bridge_properties` cache holds only locally-seeded stubs for Income,
Commercial Lease and Vacant Land, so no real record of those types could be
extracted. Those three are built instead from a real record of the nearest type
with the missing type's own vocabulary overlaid, using values observed in the
cached corpus for each of those columns (for example `CapRate` 5.55,
`STELLAR_TotalAcreage` "10 to less than 20", `LeaseTerm` "3 to 5 Years").

The field names and value shapes are the feed's; only the combination is
constructed. Replace any of them with a real scrubbed record the moment one is
available — the tests do not care which kind they get.

## What reads them

* `MlsNoFieldDropContractTest` — every populated field in every fixture must
  resolve to a disposition in `MlsFieldCatalog`, or the build fails naming it.
* `MlsPropertyTypeCoverageTest` — each type must produce populated MLS Details.
* `MlsSearchImportParityTest` — every field the Stellar detail page renders must
  have a disposition on the import side too.
