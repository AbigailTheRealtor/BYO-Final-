---
name: Stellar property_type case normalization
description: buyer offer listing EAV stores lowercase 'residential'; bridge_properties stores 'Residential'; PostgreSQL whereIn is case-sensitive — must normalize in the loader.
---

# BuyerOfferListingCriteriaLoader — Property Type Normalization

## The Rule
`bridge_properties.property_type` uses title-case Bridge API values: `'Residential'`, `'Income'`, `'Commercial Sale'`, `'Commercial Lease'`, `'Business Opportunity'`, `'Vacant Land'`.
The buyer offer listing form stores lowercase/short-form values in EAV: `'residential'`, `'income'`, `'commercial'`, `'business'`, `'vacant land'`.
PostgreSQL `whereIn` is case-sensitive → `whereIn('property_type', ['residential'])` returns 0 rows.

**Why:** The normalization map was missing in `mapRecord()` — raw EAV string used verbatim.

**How to apply:** Use `normalizeBridgePropertyType()` (added to `BuyerOfferListingCriteriaLoader`)
whenever converting a scalar `property_type` EAV value to a `bridge_properties` query value.

## Normalization Map (loader method)
- 'residential' → 'Residential'
- 'income' → 'Income'
- 'commercial' / 'commercial sale' / 'commercial_sale' → 'Commercial Sale'
- 'commercial lease' / 'commercial_lease' → 'Commercial Lease'
- 'business' / 'business opportunity' → 'Business Opportunity'
- 'land' / 'vacant land' / 'vacant_land' → 'Vacant Land'
- fallback: ucfirst($type)

## Gap for #3167
`TenantOfferListingCriteriaLoader` maps any 'commercial*' → `['Commercial']` (wrong).
Bridge uses 'Commercial Sale' and 'Commercial Lease' — needs to distinguish sale vs. lease.
