---
name: MLS terms_of_lease apply-time routing
description: Landlord residential MLS exports produce DURATION values under terms_of_lease canonical key; fix re-routes to desired_lease_length at apply time.
---

## Rule

When MLS imports for `role=landlord` with `property_type='Residential Property'`, the `terms_of_lease` canonical key carries duration values ('Month-to-Month', '1 Year', '6 Months', etc.) — NOT lease TYPE values.

These duration values belong in the `desired_lease_length` prop (blade uses `$residential_lease_term_options`), NOT `terms_of_lease` (which holds commercial lease types: Gross Lease, Net Lease).

**Fix location:** `HasMlsImport::applyImportedFields()` — routing guard re-directs `$propName` from `'terms_of_lease'` to `'desired_lease_length'` before the property_exists check.

**Guard condition:** `$canonicalKey === 'terms_of_lease' && $role === 'landlord' && $this->property_type === 'Residential Property' && property_exists($this, 'desired_lease_length')`

**Why:** The MLS "Lease Terms:" label (both Standard and reversed) is parsed under the single `terms_of_lease` canonical key regardless of residential vs. commercial. The form interprets the values differently — residential uses duration options, commercial uses lease type options.

**How to apply:** If new residential lease duration values appear from MLS that don't match `$residential_lease_term_options` in the landlord blade, add them to the blade options array (not to the normalizer's terms_of_lease mapping). Commercial listings (Form 7b, property_type='Commercial Property') are unaffected — the guard only fires for Residential Property.

## Test coverage

- `MlsGapFixesRegressionTest::test_p3_1_landlord_residential_terms_of_lease_routes_to_desired_lease_length` — primary routing test
- `MlsGapFixesRegressionTest::test_p3_1b_landlord_commercial_terms_of_lease_stays_on_terms_of_lease` — commercial not affected
- `MlsGapFixesRegressionTest::test_p3_1c_seller_terms_of_lease_not_rerouted` — seller role not affected
- `MlsMultiSelectCompatibilityTest::test_landlord_terms_of_lease_residential_month_to_month_is_valid_desired_lease_length` — confirms parser + option validity
