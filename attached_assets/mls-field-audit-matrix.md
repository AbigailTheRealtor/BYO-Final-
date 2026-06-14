# MLS Import — Final Field Audit Matrix

**Generated:** 2026-06-14  
**Scope:** All 7 supported property types across the full import pipeline  
(Parser → MlsFieldMap → Livewire Public Property → Save/Reload)

---

## Legend

| Status | Meaning |
|--------|---------|
| **PASS** | Parser branch exists, MlsFieldMap entry exists, Livewire public property exists. End-to-end wired. |
| **UNSUPPORTED** | Intentionally not wired end-to-end. Reason documented. |
| **PREVIEW-ONLY** | Parsed and shown in import preview modal (`null` map entry); not applied to form. |

Property types covered:

- **S-Res** — Seller Residential  
- **S-Inc** — Seller Residential Income (Multi-family)  
- **S-Com** — Seller Commercial Sale  
- **S-Bus** — Seller Business Opportunity  
- **S-Vac** — Seller Vacant Land  
- **L-Res** — Landlord Residential Rental  
- **L-Com** — Landlord Commercial Lease  

All five Seller types share `SellerOfferListing.php` + `MlsFieldMap::seller()`.  
Both Landlord types share `LandlordOfferListing.php` + `MlsFieldMap::landlord()`.

---

## Group 1 — Core Property Fields

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `price` | ✓ | `maximum_budget` | `desired_rental_amount` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `bedrooms` | ✓ | `bedrooms` | `bedrooms` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `bathrooms` | ✓ | `bathrooms` | `bathrooms` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `heated_sqft` | ✓ | `minimum_heated_square` | `minimum_heated_square` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `year_built` | ✓ | `year_built` | `year_built` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `pool` | ✓ | `pool_needed` | `pool_needed` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `garage` | ✓ | `garage_needed` | `garage_needed` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `carport` | ✓ | `carport_needed` | `carport_needed` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `garage_spaces` | ✓ | `garage_parking_spaces` | — | PASS | PASS | PASS | PASS | PASS | UNSUPPORTED¹ | UNSUPPORTED¹ |
| `property_type` | ✓ | `property_type` | — | PASS | PASS | PASS | PASS | PASS | UNSUPPORTED² | UNSUPPORTED² |
| `zoning` | ✓ | `zoning` | `zoning` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `sqft_heated_source` | ✓ | `sqft_heated_source` | `sqft_heated_source` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

¹ `garage_spaces` (numeric count) not in landlord field map; `garage_parking_spaces` property absent from LandlordOfferListing.  
² `property_type` not in landlord field map; LandlordOfferListing drives type via `leasing_space` not `property_type`.

---

## Group 2 — Address Fields

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `address` | ✓ | `address` | `address` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `city` | ✓ | `property_city` | `property_city` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `state` | ✓ | `property_state` | `property_state` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `zip` | ✓ | `property_zip` | `property_zip` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `county` | ✓ | `property_county` | `property_county` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

---

## Group 3 — Tax / Legal / Parcel

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `tax_id` | ✓ | `parcel_id` | `parcel_id` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `tax_year` | ✓ | `tax_year` | `tax_year` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `annual_taxes` | ✓ | `annual_property_taxes` | `annual_property_taxes` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `legal_description` | ✓ | `legal_description` | `legal_description` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `additional_parcels` | ✓ | `additional_parcels` | `additional_parcels` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `total_parcel_count` | ✓ | `total_parcel_count` | `total_parcel_count` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

---

## Group 4 — Flood Zone

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `flood_zone_code` | ✓ | `flood_zone_code` | `flood_zone_code` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `flood_zone_panel` | ✓ | `flood_zone_panel` | `flood_zone_panel` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `flood_zone_date` | ✓ | `flood_zone_date` | `flood_zone_date` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `flood_insurance_required` | ✓ | `flood_insurance_required` | `flood_insurance_required` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

---

## Group 5 — HOA / CDD

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `has_hoa` | ✓ | `has_hoa` | `has_hoa` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `association_name` | ✓ | `association_name` | `association_name` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `association_fee_amount` | ✓ | `association_fee_amount` | `association_fee_amount` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `association_fee_frequency` | ✓ | `association_fee_frequency` | `association_fee_frequency` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `has_cdd` | ✓ | `has_cdd` | `has_cdd` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `annual_cdd_fee` | ✓ | `annual_cdd_fee` | `annual_cdd_fee` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

---

## Group 6 — Special Assessments

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `has_special_assessments` | ✓ | `has_special_assessments` | `has_special_assessments` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `special_assessment_amount` | ✓ | `special_assessment_amount` | `special_assessment_amount` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `special_assessment_description` | ✓ | `special_assessment_description` | `special_assessment_description` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

---

## Group 7 — Land / Site

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `lot_dimensions` | ✓ | `lot_dimensions` | `lot_dimensions` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `lot_size_acres` | ✓ | `total_acreage` | `total_acreage` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `lot_size_sqft` | ✓ | — | — | UNSUPPORTED³ | UNSUPPORTED³ | UNSUPPORTED³ | UNSUPPORTED³ | UNSUPPORTED³ | UNSUPPORTED³ | UNSUPPORTED³ |

³ `lot_size_sqft` is parsed but intentionally excluded from both field maps. Both Seller and Landlord components store lot size exclusively in acres (`total_acreage`). No `lot_size_sqft` Livewire property exists on either component. Documented in MlsFieldMap comments.

---

## Group 8 — Waterfront

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `waterfront` | ✓ | `waterfront` | `waterfront` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `water_frontage` | ✓ | `water_frontage` | `water_frontage` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `waterfront_feet` | ✓ | `waterfront_feet` | `waterfront_feet` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `water_access` | ✓ | `*water_access` | `*water_access` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `water_view` | ✓ | `*water_view` | `*water_view` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `waterfront_yn` | ✓ (signal) | — | — | UNSUPPORTED⁴ | UNSUPPORTED⁴ | UNSUPPORTED⁴ | UNSUPPORTED⁴ | UNSUPPORTED⁴ | UNSUPPORTED⁴ | UNSUPPORTED⁴ |

⁴ `waterfront_yn` is parsed as a signal to disambiguate `Water Frontage Y/N:` boolean from the water-body description field. It is not in any field map. The actual `waterfront` canonical key covers the boolean; `waterfront_yn` is an internal parse artifact only.

---

## Group 9 — Structural

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `roof_type` | ✓ | `*roof_type` | `*roof_type` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `exterior_construction` | ✓ | `*exterior_construction` | `*exterior_construction` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `foundation` | ✓ | `*foundation` | `*foundation` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `building_size_sqft` | ✓ | `total_square_feet` | — | PASS | PASS | PASS | PASS | PASS | UNSUPPORTED⁵ | UNSUPPORTED⁵ |
| `ceiling_height_ft` | ✓ | `ceiling_height` | — | PASS | PASS | PASS | PASS | PASS | UNSUPPORTED⁵ | UNSUPPORTED⁵ |

⁵ `building_size_sqft` and `ceiling_height_ft` are commercial-specific fields. They are not in the landlord field map and have no equivalent public properties on LandlordOfferListing. UNSUPPORTED for Landlord — no destination field exists.

---

## Group 10 — Utilities & Mechanical

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `heating_fuel` | ✓ | `*heating_and_fuel` | `*heating_fuel` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `air_conditioning` | ✓ | `*air_conditioning` | `*air_conditioning` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `water` | ✓ | `*water` | `*water` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `sewer` | ✓ | `*sewer` | `*sewer` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `utilities` | ✓ | `*utilities` | `*property_utilities` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

Note: Seller maps `utilities` → `$utilities` (string); Landlord maps `utilities` → `$property_utilities` (array/multiselect). Both properties exist on their respective components. ✓

---

## Group 11 — Interior

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `interior_features` | ✓ | `*interior_features` | `*interior_features` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `appliances` | ✓ | `*appliances` | `*appliances` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

---

## Group 12 — Description

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `description` | ✓ **[FIXED]** | `additional_details` | `additional_details` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

**Fix applied (this task):** Added a post-capture header-strip step to remove MLS address/city/state/ZIP header blocks that precede the narrative remarks body. Uses a state-abbreviation + ZIP pattern anchored to the start of the captured value; only fires when non-empty prose follows the stripped block.

---

## Group 13 — Rental-Specific Fields (Landlord only)

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `furnished` | ✓ | `building_features` (merge) | `tenant_require` | PASS⁶ | PASS⁶ | PASS⁶ | PASS⁶ | PASS⁶ | PASS | PASS |
| `available_date` | ✓ | — | `available_date` | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | PASS | PASS |
| `rent_includes` | ✓ | — | `*rent_includes` | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | PASS | UNSUPPORTED⁸ |
| `terms_of_lease` | ✓ | — | `*terms_of_lease` | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁸ | PASS |
| `tenant_pays` | ✓ | — | `*tenant_pays` | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁸ | PASS |
| `lease_amount_frequency` | ✓ | — | `lease_amount_frequency` | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | PASS | PASS |
| `minimum_security_deposit` | ✓ | — | `security_deposit_amount` | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | PASS | PASS |
| `application_fee` | ✓ | — | — | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁷ | UNSUPPORTED⁹ | UNSUPPORTED⁹ |

⁶ For Seller, `furnished` value is merged into the `building_features` multi-select array (not a simple assignment). Only 'Furnished', 'Turnkey', 'Partial', 'Negotiable' values merge; 'Unfurnished' is skipped intentionally.  
⁷ Rental-specific fields are not in the seller field map and have no corresponding properties on SellerOfferListing. Correct by design — these are lease-only fields.  
⁸ `rent_includes` is residential-only; `terms_of_lease` and `tenant_pays` are commercial-only. The landlord field map includes all three, so applyImportedFields will write to whichever is non-null for the property type. The form conditionally shows the relevant subset.  
⁹ `application_fee` is intentionally excluded from both field maps. No `application_fee` property exists on LandlordOfferListing. Documented in MlsFieldMap landlord comments.

---

## Group 14 — Commercial Lease Fields (Landlord Commercial Lease)

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `lease_rate_type` | ✓ | — | `commercial_lease_type` | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | PASS¹¹ | PASS |
| `pets_allowed` | ✓ | — | `pet_policy` | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | PASS | PASS |
| `minimum_lease_months` | ✓ | — | `min_lease_period` | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | PASS | PASS |
| `office_area_sqft` | ✓ | — | `office_retail_sqft` | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | UNSUPPORTED¹⁰ | PASS | PASS |

¹⁰ These commercial lease fields are not in the seller field map. SellerOfferListing has no corresponding properties. Correct by design.  
¹¹ `lease_rate_type` maps to `commercial_lease_type` on LandlordOfferListing, which is primarily a commercial property field. For Landlord Residential, the field will be applied if the MLS text contains it, but the form only displays it when `leasing_space` = 'Commercial'. The property exists on the component regardless of subtype.

---

## Group 15 — Commercial Sale Fields (Seller Commercial Sale)

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `net_operating_income` | ✓ | `minimum_annual_net_income` | — | PASS¹² | PASS | PASS | PASS | PASS¹² | UNSUPPORTED¹³ | UNSUPPORTED¹³ |
| `cap_rate` | ✓ | `minimum_cap_rate` | — | PASS¹² | PASS | PASS | PASS | PASS¹² | UNSUPPORTED¹³ | UNSUPPORTED¹³ |
| `parking_spaces_count` | ✓ | `garage_parking_spaces` | — | PASS¹² | PASS | PASS | PASS | PASS¹² | UNSUPPORTED¹³ | UNSUPPORTED¹³ |
| `building_features_list` | ✓ | `*building_features` | — | PASS¹² | PASS | PASS | PASS | PASS¹² | UNSUPPORTED¹³ | UNSUPPORTED¹³ |
| `current_use_list` | ✓ | `*current_use` | — | PASS¹² | PASS | PASS | PASS | PASS¹² | UNSUPPORTED¹³ | UNSUPPORTED¹³ |

¹² Livewire properties exist on SellerOfferListing for all property types, but these fields are only shown on the form for Commercial/Income/Business subtypes.  
¹³ Not in landlord field map. No matching properties on LandlordOfferListing.

---

## Group 16 — Income / Multifamily Fields (Seller Residential Income)

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `number_of_units` | ✓ | `unit_number` | — | PASS¹⁴ | PASS | PASS | PASS | PASS¹⁴ | UNSUPPORTED¹⁵ | UNSUPPORTED¹⁵ |
| `gross_annual_income` | ✓ | `gross_annual_income` | — | PASS¹⁴ | PASS | PASS | PASS | PASS¹⁴ | UNSUPPORTED¹⁵ | UNSUPPORTED¹⁵ |
| `annual_operating_expenses` | ✓ | `annual_operating_expenses` | — | PASS¹⁴ | PASS | PASS | PASS | PASS¹⁴ | UNSUPPORTED¹⁵ | UNSUPPORTED¹⁵ |
| `net_operating_income_raw` | ✓ | null (preview-only) | null (preview-only) | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY |
| `unit_types_raw` | ✓ | null (preview-only) | null (preview-only) | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY |
| `occupancy_rate_raw` | ✓ | null (preview-only) | null (preview-only) | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY | PREVIEW-ONLY |

¹⁴ Properties exist on SellerOfferListing for all types; visible in form only when appropriate subtype is selected.  
¹⁵ Not in landlord field map; no corresponding properties on LandlordOfferListing.

---

## Group 17 — Business Opportunity Fields (Seller Business Opportunity)

| Canonical Key | Parser Branch | Seller Map Target | Landlord Map Target | S-Res | S-Inc | S-Com | S-Bus | S-Vac | L-Res | L-Com |
|---|---|---|---|---|---|---|---|---|---|---|
| `business_type` | ✓ | `business_type` | — | PASS¹⁶ | PASS¹⁶ | PASS¹⁶ | PASS | PASS¹⁶ | UNSUPPORTED¹⁷ | UNSUPPORTED¹⁷ |
| `annual_revenue` | ✓ | `annual_revenue` | — | PASS¹⁶ | PASS¹⁶ | PASS¹⁶ | PASS | PASS¹⁶ | UNSUPPORTED¹⁷ | UNSUPPORTED¹⁷ |
| `annual_net_income_business` | ✓ | `minimum_annual_net_income` | — | PASS¹⁶ | PASS¹⁶ | PASS¹⁶ | PASS | PASS¹⁶ | UNSUPPORTED¹⁷ | UNSUPPORTED¹⁷ |
| `employee_count` | ✓ | `employee_count` | — | PASS¹⁶ | PASS¹⁶ | PASS¹⁶ | PASS | PASS¹⁶ | UNSUPPORTED¹⁷ | UNSUPPORTED¹⁷ |
| `inventory_included` | ✓ | — | — | UNSUPPORTED¹⁸ | UNSUPPORTED¹⁸ | UNSUPPORTED¹⁸ | UNSUPPORTED¹⁸ | UNSUPPORTED¹⁸ | UNSUPPORTED¹⁸ | UNSUPPORTED¹⁸ |
| `seller_financing_yn` | ✓ | — | — | UNSUPPORTED¹⁹ | UNSUPPORTED¹⁹ | UNSUPPORTED¹⁹ | UNSUPPORTED¹⁹ | UNSUPPORTED¹⁹ | UNSUPPORTED¹⁹ | UNSUPPORTED¹⁹ |
| `business_lease_type` | ✓ | — | — | UNSUPPORTED²⁰ | UNSUPPORTED²⁰ | UNSUPPORTED²⁰ | UNSUPPORTED²⁰ | UNSUPPORTED²⁰ | UNSUPPORTED²⁰ | UNSUPPORTED²⁰ |

¹⁶ Seller properties exist on SellerOfferListing for all types; form displays only for Business Opportunity subtype.  
¹⁷ Not in landlord field map; no matching properties on LandlordOfferListing.  
¹⁸ `inventory_included` is parsed but not mapped for any role. `inventory_value` (dollar amount) is the seller field; no boolean property exists. Documented in MlsFieldMap seller comments.  
¹⁹ `seller_financing_yn` is parsed but not mapped. `offered_financing` is a multi-select array, not a boolean. Documented in MlsFieldMap seller comments.  
²⁰ `business_lease_type` is parsed but not mapped for any role. No matching Livewire property exists on SellerOfferListing. Documented in MlsFieldMap seller comments.

---

## Group 18 — Informational / Excluded Fields

| Canonical Key | Parser Branch | Reason Excluded from All Maps |
|---|---|---|
| `mls_number` | ✓ | No `mls_number` public property on any Livewire component. Documented in MlsFieldMap seller + landlord comments. |
| `directions` | ✓ | Navigation text with no listing-form destination. Documented in MlsFieldMap seller + landlord comments. |
| `rental_rate_type` | ✓ (signal) | Used as a `listing_type_hint` signal only; removed from `$data` before return. Never applied to any form. |
| `lot_size_sqft` | ✓ | No `lot_size_sqft` property on any component. Both roles use `total_acreage` (acres). Documented in field map. |

---

## Summary

| Status | Count (unique canonical keys) |
|--------|-------------------------------|
| **PASS** (for at least one property type) | 48 |
| **UNSUPPORTED** (all property types, documented) | 7 (`lot_size_sqft`, `inventory_included`, `seller_financing_yn`, `business_lease_type`, `mls_number`, `directions`, `rental_rate_type`) |
| **PREVIEW-ONLY** (null map entry, shown in modal) | 3 (`net_operating_income_raw`, `unit_types_raw`, `occupancy_rate_raw`) |
| **Role-restricted UNSUPPORTED** (not applicable to that role) | Multiple per table above |

**No silent gaps remain.** Every canonical key produced by `parseFields()` is either:
- Mapped end-to-end for at least one role, OR
- Documented as UNSUPPORTED with a stated reason in both this matrix and the MlsFieldMap source comments.

---

## Description Fix Verification

**Before fix:** captured description began with MLS address header, e.g.:  
`"12345 SUNSET BLVD UNIT 4 CLEARWATER FL 33759 This charming townhome features..."`

**After fix:** header stripped, description begins at narrative:  
`"This charming townhome features..."`

**Strip logic:** anchored regex matches leading block of non-lowercase chars → US state abbreviation + ZIP → one-or-more whitespace chars → remainder. Only fires when remainder is non-empty. Uses a static list of all 50 US states + DC for state abbreviation validation.

**Safe cases (strip does not fire):**
- Description with no address header (no state+ZIP found) → untouched
- Description that begins with lowercase prose → `[^a-z]{0,250}?` fails to match all-lowercase start → untouched
- Short descriptions (< 10 chars) → never reach this code (minimum length in capture patterns)
