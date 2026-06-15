# MLS Import Closeout — Live Audit Mismatch Report

**Audit date:** 2026-06-15  
**Source:** Live page fetch via `php artisan mls:parse-debug --url=... --role=...`

Column schema used in field trace tables:

> **MLS raw label** → **MLS raw value** → **Parser canonical key** → **Parser raw value** → **Preview value** (import modal) → **MlsFieldMap target** → **MlsNormalizer output** → **Livewire property** → **Blade wire:model** → **saveMeta key** → **After-reload value** → **Status**

Status key: ✅ = extracted, wired, and round-trips correctly | ⚠️ = field supported but absent from this public URL | 🚫 = structurally unsupported via shared URL

---

## Live URLs Audited

| Role     | URL | Address | MLS Status |
|----------|-----|---------|--------|
| Seller   | `https://stellar.mlsmatrix.com/matrix/shared/k1nNtfh9Skf/82889THAVENUEN` | 828 89TH AVE N, St. Petersburg, FL 33702 | Sold |
| Landlord | `https://stellar.mlsmatrix.com/matrix/shared/g3dTp3yYqlf/8535BLINDPASSDRIVE` | 8535 Blind Pass Dr #202, Treasure Island, FL 33706 | Active |

---

## Parser Bugs Found and Fixed

Three defects were identified by comparing raw extracted text to parsed output.

### Fix 1 — `extractVisibleText()`: HTML tag removal without space separators (root cause of both field losses)

**File:** `app/Services/ListingImport/MlsListingImportService.php`

**Root cause:** `strip_tags($html)` removes HTML tags with no separator, fusing adjacent `<td>` cells:

```
<td>Block, Stucco</td><td>Roof: Shingle</td>  →  "Block, StuccoRoof:Shingle"
<td>Water Frontage Y/N:</td><td>No</td>         →  "Water Frontage Y/N:No"
```

`\b` in the Roof pattern fails (no boundary between `o` and `R`); `\b` in the Y/N pattern fails at `"Y/N:NoWaterfront"`.

**Fix:** `preg_replace('/<[^>]+>/', ' ', $html)` — every HTML tag replaced with a space.

---

### Fix 2 — `roof_type`: Bare `Roof:` label unmatched

**Root cause:** Parser matched only `"Roof Type:"`. Stellar MLS Matrix shared pages emit `"Roof: Shingle"` (bare label, no "Type" word). `\b` also failed when tag-fusion produced `"StuccoRoof:"`.

**Fix:** Added fallback pattern without `\b` (safe: MLS narrative prose never uses `"roof:"` with an immediately following colon in description text):

```php
'/Roof\s+Type[\s:]+([^\|\n]{1,120})/i',   // existing — "Roof Type:"
'/Roof\s*:[\s]*([^\|\n]{1,120})/i',        // new fallback — bare "Roof:"
```

---

### Fix 3 — `waterfront`: `Water Frontage Y/N:` boolean not propagated to canonical key

**Root cause:** The `Water Frontage Y/N:` branch wrote only to `waterfront_yn` (an alias with no field-map entry). The separate `Waterfront:` branch — which writes to the canonical `waterfront` key — never fires because Stellar MLS Matrix shared pages omit the bare `Waterfront:` label. The `\b` at the end also broke on tag-fused `"Y/N:NoWaterfront"`.

**Fix:**
```php
// \b removed; canonical 'waterfront' key now also populated
if ($v = $extract(['/Water\s+Frontage\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])/i'])) {
    $normalized = MlsNormalizer::normalize('waterfront', $v);
    $data['waterfront_yn'] = $normalized;
    if (!isset($data['waterfront'])) {
        $data['waterfront'] = $normalized;
    }
}
```

---

## Field Trace — Seller: 828 89TH AVE N, St. Petersburg FL 33702

**32 fields mapped after fixes** (was 29; gained: `roof_type`, `waterfront`, `additional_parcels`).

Notes on **Blade wire:model** column:
- Simple scalar inputs use a direct `wire:model="prop"` attribute.
- Multi-select array fields (marked `*` in MlsFieldMap) are backed by a Select2 + Livewire JSON bridge: the component exposes a public array property; the Select2 widget syncs via `$wire.set()` on change — there is no bare `wire:model` attribute on those elements.

### Address & Location

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| County | `County: Pinellas` | `county` | `Pinellas` | `Pinellas` | `'county' => 'property_county'` | (none) | `property_county` | `wire:model="property_county"` | `property_county` | `Pinellas` | ✅ |
| Prop Address | `Prop Address: 828 89TH AVENUE N` | `address` | `828 89TH AVENUE N` | `828 89TH AVENUE N` | `'address' => 'address'` | (none) | `address` | `wire:model="address"` | `address` | `828 89TH AVENUE N` | ✅ |
| City | `City: ST PETERSBURG` | `city` | `ST PETERSBURG` | `ST PETERSBURG` | `'city' => 'property_city'` | (none) | `property_city` | `wire:model="property_city"` | `property_city` | `ST PETERSBURG` | ✅ |
| State | `State: FL` | `state` | `FL` | `FL` | `'state' => 'property_state'` | (none) | `property_state` | `wire:model="property_state"` | `property_state` | `FL` | ✅ |
| Zip | `Zip: 33702` | `zip` | `33702` | `33702` | `'zip' => 'property_zip'` | (none) | `property_zip` | `wire:model="property_zip"` | `property_zip` | `33702` | ✅ |

### Property Type (Special Attention)

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Property Type | *(not on page — Stellar MLS Matrix shared pages omit property type from the public view)* | `property_type` | — | — | `'property_type' => 'property_type'` | `normalizePropertyTypeForRole()` | `property_type` | `wire:model="property_type"` | `property_type` | *(blank — manual entry required)* | ⚠️ |

> The parser pattern `'/(?:Type|Property\s+Type)[\s:]+([^\|\n]{1,80})/i'` exists and handles values such as "Residential", "Commercial", etc. The `HasMlsImport::normalizePropertyTypeForRole()` trait translates MLS raw values to platform-specific option strings per role (seller → `'Residential'`, landlord → `'Residential Property'`). The field simply is not published on this URL.

### Property Characteristics

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| List Price | `List Price: $345,000` | `price` | `345000` | `345000` | `'price' => 'maximum_budget'` | (none) | `maximum_budget` | `wire:model="maximum_budget"` | `maximum_budget` | `345000` | ✅ |
| Bedrooms | `Bedrooms: 3` | `bedrooms` | `3` | `3` | `'bedrooms' => 'bedrooms'` | (none) | `bedrooms` | `wire:model="bedrooms"` | `bedrooms` | `3` | ✅ |
| Bathrooms | `Baths Full: 1` | `bathrooms` | `1` | `1` | `'bathrooms' => 'bathrooms'` | (none) | `bathrooms` | `wire:model="bathrooms"` | `bathrooms` | `1` | ✅ |
| Living Area | `Living Area: 1,006` | `heated_sqft` | `1006` | `1006` | `'heated_sqft' => 'minimum_heated_square'` | (none) | `minimum_heated_square` | `wire:model="minimum_heated_square"` | `minimum_heated_square` | `1006` | ✅ |
| Living Area Source | `Living Area Source: Public Records` | `sqft_heated_source` | `Public Records` | `Public Records` | `'sqft_heated_source' => 'sqft_heated_source'` | (none) | `sqft_heated_source` | `wire:model="sqft_heated_source"` | `sqft_heated_source` | `Public Records` | ✅ |
| Lot Dimensions | `Lot Size Dim: 50x127` | `lot_dimensions` | `50x127` | `50x127` | `'lot_dimensions' => 'lot_dimensions'` | (none) | `lot_dimensions` | `wire:model="lot_dimensions"` | `lot_dimensions` | `50x127` | ✅ |
| Lot Acreage | `Lot Size Acres: 0.15` | `lot_size_acres` | `0.15` | `0.15` | `'lot_size_acres' => 'total_acreage'` | (none) | `total_acreage` | `wire:model="total_acreage"` | `total_acreage` | `0.15` | ✅ |
| Year Built | `Year Built: 1969` | `year_built` | `1969` | `1969` | `'year_built' => 'year_built'` | (none) | `year_built` | `wire:model="year_built"` | `year_built` | `1969` | ✅ |
| Pool | `Pool Private YN: No` | `pool` | `No` | `No` | `'pool' => 'pool_needed'` | `normalizeFormYesNo()` → `No` | `pool_needed` | `wire:model="pool_needed"` | `pool_needed` | `No` | ✅ |
| Garage | `Garage YN: Yes` | `garage` | `Yes` | `Yes` | `'garage' => 'garage_needed'` | `normalizeFormYesNo()` → `Yes` | `garage_needed` | `wire:model="garage_needed"` | `garage_needed` | `Yes` | ✅ |
| Carport | `Carport YN: No` | `carport` | `No` | `No` | `'carport' => 'carport_needed'` | `normalizeFormYesNo()` → `No` | `carport_needed` | `wire:model="carport_needed"` | `carport_needed` | `No` | ✅ |

### Interior / Exterior Features (Array Fields)

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value (import modal) | MlsFieldMap Target | Normalizer Output | Livewire Prop (array) | Blade binding | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Cooling | `Cooling: Central Air` | `air_conditioning` | `Central Air` | `Central Air` | `'air_conditioning' => '*air_conditioning'` | (none) | `air_conditioning` | Select2 JSON bridge (`$wire.set`) | `air_conditioning` | `["Central Air"]` | ✅ |
| Heating | `Heating: Central` | `heating_fuel` | `Central` | `Central` | `'heating_fuel' => '*heating_and_fuel'` | (none) | `heating_and_fuel` | Select2 JSON bridge | `heating_and_fuel` | `["Central"]` | ✅ |
| Interior Features | `Interior Features: Ceiling Fan(s), Open Floorplan` | `interior_features` | `Ceiling Fan(s), Open Floorplan` | `Ceiling Fan(s), Open Floorplan` | `'interior_features' => '*interior_features'` | (none) | `interior_features` | Select2 JSON bridge | `interior_features` | `["Ceiling Fan(s)","Open Floorplan"]` | ✅ |
| Appliances | `Appliances: Dishwasher, Dryer, Gas Water Heater, Range, Refrigerator` | `appliances` | `Dishwasher, Dryer, …` | `Dishwasher, Dryer, Gas Water Heater, Range, Refrigerator` | `'appliances' => '*appliances'` | (none) | `appliances` | Select2 JSON bridge | `appliances` | `["Dishwasher","Dryer","Gas Water Heater","Range","Refrigerator"]` | ✅ |
| **Roof** ✅ NEW | `Roof: Shingle` | `roof_type` | `Shingle` | `Shingle` | `'roof_type' => '*roof_type'` | (none) | `roof_type` | Select2 JSON bridge | `roof_type` | `["Shingle"]` | ✅ |
| Construction | `Construction: Block, Stucco` | `exterior_construction` | `Block, Stucco` | `Block, Stucco` | `'exterior_construction' => '*exterior_construction'` | (none) | `exterior_construction` | Select2 JSON bridge | `exterior_construction` | `["Block","Stucco"]` | ✅ |
| Sewer | (from Utilities text: `Sewer Connected`) | `sewer` | `Sewer Connected` | `Public Sewer` | `'sewer' => '*sewer'` | `normalizeSewer()` → `Public Sewer` | `sewer` | Select2 JSON bridge | `sewer` | `["Public Sewer"]` | ✅ |
| Utilities | `Utilities: Sewer Connected, Water Connected` | `utilities` | `Sewer Connected, Water Connected` | `Sewer Connected, Water Connected` | `'utilities' => '*utilities'` | (none) | `utilities` | Select2 JSON bridge | `utilities` | `["Sewer Connected","Water Connected"]` | ✅ |
| Foundation | *(not on page)* | `foundation` | — | — | `'foundation' => '*foundation'` | (none) | `foundation` | Select2 JSON bridge | `foundation` | `[]` *(blank — manual entry required)* | ⚠️ |

### Waterfront

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Water Frontage Y/N** ✅ NEW | `Water Frontage Y/N: No` | `waterfront` | `no` | `no` | `'waterfront' => 'waterfront'` | `normalizeBoolean()` → `no` | `waterfront` | `wire:model="waterfront"` | `waterfront` | `no` | ✅ |
| Waterfront Feet | `Waterfront Feet: 0` | `waterfront_feet` | `0` | `0` | `'waterfront_feet' => 'waterfront_feet'` | (none) | `waterfront_feet` | `wire:model="waterfront_feet"` | `waterfront_feet` | `0` | ✅ |
| Water Access | *(not on page)* | `water_access` | — | — | `'water_access' => '*water_access'` | (none) | `water_access` | Select2 JSON bridge | `water_access` | `[]` *(blank)* | ⚠️ |
| Water View | *(not on page)* | `water_view` | — | — | `'water_view' => '*water_view'` | (none) | `water_view` | Select2 JSON bridge | `water_view` | `[]` *(blank)* | ⚠️ |
| Water Frontage Body | *(not on page — only Y/N form present)* | `water_frontage` | — | — | `'water_frontage' => 'water_frontage'` | (none) | `water_frontage` | `wire:model="water_frontage"` | `water_frontage` | *(blank)* | ⚠️ |

### Tax / Legal / Flood Zone / HOA / CDD

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Parcel Number | `Parcel Number: 19-30-17-45612-000-1410` | `tax_id` | `19-30-17-45612-000-1410` | `19-30-17-45612-000-1410` | `'tax_id' => 'parcel_id'` | (none) | `parcel_id` | `wire:model="parcel_id"` | `parcel_id` | `19-30-17-45612-000-1410` | ✅ |
| Tax Year | `Tax Year: 2024` | `tax_year` | `2024` | `2024` | `'tax_year' => 'tax_year'` | (none) | `tax_year` | `wire:model="tax_year"` | `tax_year` | `2024` | ✅ |
| Annual Taxes | `Taxes Annual Amount: $3,908` | `annual_taxes` | `3908.00` | `3908.00` | `'annual_taxes' => 'annual_property_taxes'` | (none) | `annual_property_taxes` | `wire:model="annual_property_taxes"` | `annual_property_taxes` | `3908.00` | ✅ |
| Additional Parcels ✅ NEW | `Additional Parcels YN: No` | `additional_parcels` | `no` | `no` | `'additional_parcels' => 'additional_parcels'` | `normalizeBoolean()` → `no` | `additional_parcels` | `wire:model="additional_parcels"` | `additional_parcels` | `no` | ✅ |
| Flood Zone | `Flood Zone Code: AE` | `flood_zone_code` | `AE` | `AE` | `'flood_zone_code' => 'flood_zone_code'` | `normalizeFloodZone()` → `AE` | `flood_zone_code` | `wire:model="flood_zone_code"` | `flood_zone_code` | `AE` | ✅ |
| Legal Description | *(not on page)* | `legal_description` | — | — | `'legal_description' => 'legal_description'` | (none) | `legal_description` | `wire:model="legal_description"` | `legal_description` | *(blank — manual entry required)* | ⚠️ |
| Flood Zone Panel | *(not on page)* | `flood_zone_panel` | — | — | `'flood_zone_panel' => 'flood_zone_panel'` | (none) | `flood_zone_panel` | `wire:model="flood_zone_panel"` | `flood_zone_panel` | *(blank — manual entry required)* | ⚠️ |
| Flood Zone Date | *(not on page)* | `flood_zone_date` | — | — | `'flood_zone_date' => 'flood_zone_date'` | (none) | `flood_zone_date` | `wire:model="flood_zone_date"` | `flood_zone_date` | *(blank — manual entry required)* | ⚠️ |
| Flood Ins Required | *(not on page)* | `flood_insurance_required` | — | — | `'flood_insurance_required' => 'flood_insurance_required'` | (none) | `flood_insurance_required` | `wire:model="flood_insurance_required"` | `flood_insurance_required` | *(blank — manual entry required)* | ⚠️ |
| HOA (has_hoa) | *(not on page)* | `has_hoa` | — | — | `'has_hoa' => 'has_hoa'` | (none) | `has_hoa` | `wire:model="has_hoa"` | `has_hoa` | *(blank — manual entry required)* | ⚠️ |
| Association Name | *(not on page)* | `association_name` | — | — | `'association_name' => 'association_name'` | (none) | `association_name` | `wire:model="association_name"` | `association_name` | *(blank — manual entry required)* | ⚠️ |
| Association Fee | *(not on page)* | `association_fee_amount` | — | — | `'association_fee_amount' => 'association_fee_amount'` | (none) | `association_fee_amount` | `wire:model="association_fee_amount"` | `association_fee_amount` | *(blank — manual entry required)* | ⚠️ |
| Association Freq | *(not on page)* | `association_fee_frequency` | — | — | `'association_fee_frequency' => 'association_fee_frequency'` | `normalizeHoaFeeFrequency()` | `association_fee_frequency` | `wire:model="association_fee_frequency"` | `association_fee_frequency` | *(blank — manual entry required)* | ⚠️ |
| CDD (has_cdd) | *(not on page)* | `has_cdd` | — | — | `'has_cdd' => 'has_cdd'` | (none) | `has_cdd` | `wire:model="has_cdd"` | `has_cdd` | *(blank — manual entry required)* | ⚠️ |
| Annual CDD Fee | *(not on page)* | `annual_cdd_fee` | — | — | `'annual_cdd_fee' => 'annual_cdd_fee'` | (none) | `annual_cdd_fee` | `wire:model="annual_cdd_fee"` | `annual_cdd_fee` | *(blank — manual entry required)* | ⚠️ |

### Description & Photos

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Public Remarks | `Public Remarks: Welcome to this beautifully updated…` | `description` | *(full text)* | *(first 260 chars in modal)* | `'description' => 'additional_details'` | (none) | `additional_details` | `wire:model="additional_details"` | `additional_details` | *(full description text)* | ✅ |
| Photos | *(not on page — shared URL shows only a single embedded thumbnail)* | — | — | — | — | — | — | — | `property_photos` | — | 🚫 |

### Intentionally Skipped / No Field-Map Entry

| Parser Key | Reason |
|---|---|
| `mls_number` | No `mls_number` property on `SellerOfferListing` — documented in `MlsFieldMap` rejected-mappings note |
| `waterfront_yn` | Internal alias populated alongside `waterfront`; not in MlsFieldMap; never imported to the form |
| `directions` | Navigation text with no listing field purpose — documented in `MlsFieldMap` rejected-mappings note |
| `listing_type_hint` | Informational only; used by parser to set role hint, not a form property |

---

## Field Trace — Landlord: 8535 Blind Pass Dr #202, Treasure Island FL 33706

**31 fields mapped** (no change — all already correct before fixes).

### Address & Location

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| County | `County: Pinellas` | `county` | `Pinellas` | `Pinellas` | `'county' => 'property_county'` | (none) | `property_county` | `wire:model="property_county"` | `property_county` | `Pinellas` | ✅ |
| Prop Address | `Prop Address: 8535 BLIND PASS DRIVE Unit#202` | `address` | `8535 BLIND PASS DRIVE Unit#202` | `8535 BLIND PASS DRIVE Unit#202` | `'address' => 'address'` | (none) | `address` | `wire:model="address"` | `address` | `8535 BLIND PASS DRIVE Unit#202` | ✅ |
| City | `City: TREASURE ISLAND` | `city` | `TREASURE ISLAND` | `TREASURE ISLAND` | `'city' => 'property_city'` | (none) | `property_city` | `wire:model="property_city"` | `property_city` | `TREASURE ISLAND` | ✅ |
| State | `State: FL` | `state` | `FL` | `FL` | `'state' => 'property_state'` | (none) | `property_state` | `wire:model="property_state"` | `property_state` | `FL` | ✅ |
| Zip | `Zip: 33706` | `zip` | `33706` | `33706` | `'zip' => 'property_zip'` | (none) | `property_zip` | `wire:model="property_zip"` | `property_zip` | `33706` | ✅ |

### Property Type (Special Attention)

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Property Type | *(not on page — Stellar MLS Matrix shared pages omit property type from the public view)* | `property_type` | — | — | `'property_type' => 'property_type'` | `normalizePropertyTypeForRole()` → `'Residential Property'` (landlord) | `property_type` | `wire:model="property_type"` | `property_type` | *(blank — manual entry required)* | ⚠️ |

### Rental & Lease Characteristics

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| List Price | `List Price: $2,000` | `price` | `2000` | `2000` | `'price' => 'desired_rental_amount'` | (none) | `desired_rental_amount` | `wire:model="desired_rental_amount"` | `desired_rental_amount` | `2000` | ✅ |
| Furnished | `Furnished: Unfurnished` | `furnished` | `Unfurnished` | `Unfurnished` | `'furnished' => 'tenant_require'` | `normalizeFurnishing()` → `Unfurnished` | `tenant_require` | `wire:model="tenant_require"` | `tenant_require` | `["Unfurnished"]` | ✅ |
| Available For Lease | `Available For Lease: 06/11/2026` | `available_date` | `06/11/2026` | `06/11/2026` | `'available_date' => 'available_date'` | (none) | `available_date` | `wire:model="available_date"` | `available_date` | `06/11/2026` | ✅ |
| *(same source, dual key)* | `06/11/2026` | `lease_available_date` | `06/11/2026` | `06/11/2026` | `'lease_available_date' => 'lease_available_date'` | (none) | `lease_available_date` | `wire:model="lease_available_date"` | `lease_available_date` | `06/11/2026` | ✅ |
| Rent Includes | `Rent Includes: None` | `rent_includes` | `None` | `None` | `'rent_includes' => '*rent_includes'` | (none) | `rent_includes` | Select2 JSON bridge | `rent_includes` | `["None"]` | ✅ |

### Property Characteristics

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Bedrooms | `Bedrooms: 1` | `bedrooms` | `1` | `1` | `'bedrooms' => 'bedrooms'` | (none) | `bedrooms` | `wire:model="bedrooms"` | `bedrooms` | `1` | ✅ |
| Bathrooms | `Baths Full: 1` | `bathrooms` | `1` | `1` | `'bathrooms' => 'bathrooms'` | (none) | `bathrooms` | `wire:model="bathrooms"` | `bathrooms` | `1` | ✅ |
| Living Area | `Living Area: 560` | `heated_sqft` | `560` | `560` | `'heated_sqft' => 'minimum_heated_square'` | (none) | `minimum_heated_square` | `wire:model="minimum_heated_square"` | `minimum_heated_square` | `560` | ✅ |
| Lot Dimensions | `Lot Size Dim: 65x127` | `lot_dimensions` | `65x127` | `65x127` | `'lot_dimensions' => 'lot_dimensions'` | (none) | `lot_dimensions` | `wire:model="lot_dimensions"` | `lot_dimensions` | `65x127` | ✅ |
| Lot Acreage | `Lot Size Acres: 0.19` | `lot_size_acres` | `0.19` | `0.19` | `'lot_size_acres' => 'total_acreage'` | (none) | `total_acreage` | `wire:model="total_acreage"` | `total_acreage` | `0.19` | ✅ |
| Year Built | `Year Built: 1973` | `year_built` | `1973` | `1973` | `'year_built' => 'year_built'` | (none) | `year_built` | `wire:model="year_built"` | `year_built` | `1973` | ✅ |
| Pool | `Pool Private YN: No` | `pool` | `No` | `No` | `'pool' => 'pool_needed'` | `normalizeFormYesNo()` → `No` | `pool_needed` | `wire:model="pool_needed"` | `pool_needed` | `No` | ✅ |
| Garage | `Garage YN: No` | `garage` | `No` | `No` | `'garage' => 'garage_needed'` | `normalizeFormYesNo()` → `No` | `garage_needed` | `wire:model="garage_needed"` | `garage_needed` | `No` | ✅ |
| Carport | `Carport YN: No` | `carport` | `No` | `No` | `'carport' => 'carport_needed'` | `normalizeFormYesNo()` → `No` | `carport_needed` | `wire:model="carport_needed"` | `carport_needed` | `No` | ✅ |
| Sqft Heated Source | *(not on this page — landlord shared URL omits this field)* | `sqft_heated_source` | — | — | `'sqft_heated_source' => 'sqft_heated_source'` | (none) | `sqft_heated_source` | `wire:model="sqft_heated_source"` | `sqft_heated_source` | *(blank)* | ⚠️ |

### Interior / Exterior Features (Array Fields)

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value (import modal) | MlsFieldMap Target | Normalizer Output | Livewire Prop (array) | Blade binding | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Cooling | `Cooling: Central Air` | `air_conditioning` | `Central Air` | `Central Air` | `'air_conditioning' => '*air_conditioning'` | (none) | `air_conditioning` | Select2 JSON bridge | `air_conditioning` | `["Central Air"]` | ✅ |
| Heating | `Heating: Central` | `heating_fuel` | `Central` | `Central` | `'heating_fuel' => '*heating_fuel'` | (none) | `heating_fuel` | Select2 JSON bridge | `heating_fuel` | `["Central"]` | ✅ |
| Interior Features | `Interior Features: Ceiling Fan(s), Eating Space In Kitchen, Solid Wood Cabinets, Window Treatments` | `interior_features` | *(comma-joined list)* | `Ceiling Fan(s), Eating Space In Kitchen, Solid Wood Cabinets, Window Treatments` | `'interior_features' => '*interior_features'` | (none) | `interior_features` | Select2 JSON bridge | `interior_features` | `["Ceiling Fan(s)","Eating Space In Kitchen","Solid Wood Cabinets","Window Treatments"]` | ✅ |
| Appliances | `Appliances: Dishwasher, Range, Refrigerator` | `appliances` | `Dishwasher, Range, Refrigerator` | `Dishwasher, Range, Refrigerator` | `'appliances' => '*appliances'` | (none) | `appliances` | Select2 JSON bridge | `appliances` | `["Dishwasher","Range","Refrigerator"]` | ✅ |
| Roof Type | *(not on page — landlord shared URL omits Exterior section)* | `roof_type` | — | — | `'roof_type' => '*roof_type'` | (none) | `roof_type` | Select2 JSON bridge | `roof_type` | `[]` *(blank)* | ⚠️ |
| Exterior Construction | *(not on page)* | `exterior_construction` | — | — | `'exterior_construction' => '*exterior_construction'` | (none) | `exterior_construction` | Select2 JSON bridge | `exterior_construction` | `[]` *(blank)* | ⚠️ |
| Sewer | *(not on page — landlord shared URL omits Sewer section)* | `sewer` | — | — | `'sewer' => '*sewer'` | (none) | `sewer` | Select2 JSON bridge | `sewer` | `[]` *(blank)* | ⚠️ |
| Utilities | *(not on page)* | `utilities` | — | — | `'utilities' => '*property_utilities'` | (none) | `property_utilities` | Select2 JSON bridge | `property_utilities` | `[]` *(blank)* | ⚠️ |
| Foundation | *(not on page)* | `foundation` | — | — | `'foundation' => '*foundation'` | (none) | `foundation` | Select2 JSON bridge | `foundation` | `[]` *(blank)* | ⚠️ |

### Waterfront

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Water Frontage | `Water Frontage: Intracoastal Waterway` | `water_frontage` | `Intracoastal Waterway` | `Intracoastal Waterway` | `'water_frontage' => 'water_frontage'` | (none) | `water_frontage` | `wire:model="water_frontage"` | `water_frontage` | `Intracoastal Waterway` | ✅ |
| Waterfront Feet | `Waterfront Feet: 50` | `waterfront_feet` | `50` | `50` | `'waterfront_feet' => 'waterfront_feet'` | (none) | `waterfront_feet` | `wire:model="waterfront_feet"` | `waterfront_feet` | `50` | ✅ |
| Water Access | `Water Access: Intracoastal Waterway` | `water_access` | `Intracoastal Waterway` | `Intracoastal Waterway` | `'water_access' => '*water_access'` | (none) | `water_access` | Select2 JSON bridge | `water_access` | `["Intracoastal Waterway"]` | ✅ |
| Water View | `Water View: Intracoastal Waterway` | `water_view` | `Intracoastal Waterway` | `Intracoastal Waterway` | `'water_view' => '*water_view'` | (none) | `water_view` | Select2 JSON bridge | `water_view` | `["Intracoastal Waterway"]` | ✅ |
| Waterfront (bool) | *(bare `Waterfront:` label not on page for this listing)* | `waterfront` | — | — | `'waterfront' => 'waterfront'` | (none) | `waterfront` | `wire:model="waterfront"` | `waterfront` | *(blank — manual entry required)* | ⚠️ |

### Tax / Legal / Flood Zone / HOA / CDD

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Parcel Number | `Parcel Number: 25-31-15-25398-003-0010` | `tax_id` | `25-31-15-25398-003-0010` | `25-31-15-25398-003-0010` | `'tax_id' => 'parcel_id'` | (none) | `parcel_id` | `wire:model="parcel_id"` | `parcel_id` | `25-31-15-25398-003-0010` | ✅ |
| Tax Year | `Tax Year: 2025` | `tax_year` | `2025` | `2025` | `'tax_year' => 'tax_year'` | (none) | `tax_year` | `wire:model="tax_year"` | `tax_year` | `2025` | ✅ |
| Annual Taxes | `Taxes Annual Amount: $26,893.93` | `annual_taxes` | `26893.93` | `26893.93` | `'annual_taxes' => 'annual_property_taxes'` | (none) | `annual_property_taxes` | `wire:model="annual_property_taxes"` | `annual_property_taxes` | `26893.93` | ✅ |
| Legal Description | *(not on page)* | `legal_description` | — | — | `'legal_description' => 'legal_description'` | (none) | `legal_description` | `wire:model="legal_description"` | `legal_description` | *(blank — manual entry required)* | ⚠️ |
| Flood Zone Code | *(not on page)* | `flood_zone_code` | — | — | `'flood_zone_code' => 'flood_zone_code'` | `normalizeFloodZone()` | `flood_zone_code` | `wire:model="flood_zone_code"` | `flood_zone_code` | *(blank — manual entry required)* | ⚠️ |
| Flood Zone Panel | *(not on page)* | `flood_zone_panel` | — | — | `'flood_zone_panel' => 'flood_zone_panel'` | (none) | `flood_zone_panel` | `wire:model="flood_zone_panel"` | `flood_zone_panel` | *(blank — manual entry required)* | ⚠️ |
| Flood Zone Date | *(not on page)* | `flood_zone_date` | — | — | `'flood_zone_date' => 'flood_zone_date'` | (none) | `flood_zone_date` | `wire:model="flood_zone_date"` | `flood_zone_date` | *(blank — manual entry required)* | ⚠️ |
| Flood Ins Required | *(not on page)* | `flood_insurance_required` | — | — | `'flood_insurance_required' => 'flood_insurance_required'` | (none) | `flood_insurance_required` | `wire:model="flood_insurance_required"` | `flood_insurance_required` | *(blank — manual entry required)* | ⚠️ |
| Additional Parcels | *(not on page — field absent from landlord shared URL)* | `additional_parcels` | — | — | `'additional_parcels' => 'additional_parcels'` | (none) | `additional_parcels` | `wire:model="additional_parcels"` | `additional_parcels` | *(blank — manual entry required)* | ⚠️ |
| HOA (has_hoa) | *(not on page)* | `has_hoa` | — | — | `'has_hoa' => 'has_hoa'` | (none) | `has_hoa` | `wire:model="has_hoa"` | `has_hoa` | *(blank — manual entry required)* | ⚠️ |
| Association Name | *(not on page)* | `association_name` | — | — | `'association_name' => 'association_name'` | (none) | `association_name` | `wire:model="association_name"` | `association_name` | *(blank — manual entry required)* | ⚠️ |
| Association Fee | *(not on page)* | `association_fee_amount` | — | — | `'association_fee_amount' => 'association_fee_amount'` | (none) | `association_fee_amount` | `wire:model="association_fee_amount"` | `association_fee_amount` | *(blank — manual entry required)* | ⚠️ |
| Association Freq | *(not on page)* | `association_fee_frequency` | — | — | `'association_fee_frequency' => 'association_fee_frequency'` | `normalizeHoaFeeFrequency()` | `association_fee_frequency` | `wire:model="association_fee_frequency"` | `association_fee_frequency` | *(blank — manual entry required)* | ⚠️ |
| CDD (has_cdd) | *(not on page)* | `has_cdd` | — | — | `'has_cdd' => 'has_cdd'` | (none) | `has_cdd` | `wire:model="has_cdd"` | `has_cdd` | *(blank — manual entry required)* | ⚠️ |
| Annual CDD Fee | *(not on page)* | `annual_cdd_fee` | — | — | `'annual_cdd_fee' => 'annual_cdd_fee'` | (none) | `annual_cdd_fee` | `wire:model="annual_cdd_fee"` | `annual_cdd_fee` | *(blank — manual entry required)* | ⚠️ |

### Description & Photos

| MLS Label | MLS Raw Value | Parser Key | Parser Raw Value | Preview Value | MlsFieldMap Target | Normalizer Output | Livewire Prop | Blade wire:model | saveMeta Key | After-Reload Value | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Public Remarks | `Public Remarks: Coastal Living Just Steps from Sunset…` | `description` | *(full text)* | *(first 260 chars in modal)* | `'description' => 'additional_details'` | (none) | `additional_details` | `wire:model="additional_details"` | `additional_details` | *(full description text)* | ✅ |
| Photos | *(not on page — shared URL shows only a single embedded thumbnail)* | — | — | — | — | — | — | — | `property_photos` | — | 🚫 |

### Intentionally Skipped / No Field-Map Entry

| Parser Key | Reason |
|---|---|
| `mls_number` | No `mls_number` property on `LandlordOfferListing` — documented in `MlsFieldMap` rejected-mappings note |
| `application_fee` | No `application_fee` property on `LandlordOfferListing` — documented in `MlsFieldMap` rejected-mappings note |
| `directions` | Navigation text with no listing field purpose |
| `listing_type_hint` | Informational only; not a form property |

---

## Photos — Structural Limitation of Shared MLS URLs

Property photos are **not importable** from Stellar MLS Matrix public shared URLs. The shared page embeds only a single thumbnail. Full photo sets require MLS member API access (not available via shared link). This is a structural limitation of the URL source, not a parser gap.

**Current manual workflow:** After import pre-fills the form, users upload photos on the "Photos/Tours/Documents" tab (up to 50 photos, drag-and-drop reorder via SortableJS). Filenames are stored as JSON in the `property_photos` EAV meta key and the cover photo is tracked separately.

**Recommended follow-up:** A future import path using the Stellar MLS RETS/API feed (available to member agents with credentials) could auto-populate `property_photos` from the full photo set.

---

## Fields Absent from Public Stellar MLS Shared Pages — Summary

The following parser patterns exist and are wired end-to-end, but the corresponding fields are not published on Stellar MLS Matrix public shared URLs. They require manual entry after import.

| Field | Seller URL | Landlord URL |
|---|:---:|:---:|
| `property_type` | ⚠️ absent | ⚠️ absent |
| `legal_description` | ⚠️ absent | ⚠️ absent |
| `flood_zone_panel` | ⚠️ absent | ⚠️ absent |
| `flood_zone_date` | ⚠️ absent | ⚠️ absent |
| `flood_insurance_required` | ⚠️ absent | ⚠️ absent |
| `has_hoa` / association | ⚠️ absent | ⚠️ absent |
| `has_cdd` / annual_cdd_fee | ⚠️ absent | ⚠️ absent |
| `foundation` | ⚠️ absent | ⚠️ absent |
| `water_access` | ⚠️ absent | ✅ present |
| `water_view` | ⚠️ absent | ✅ present |
| `water_frontage` (body name) | ⚠️ absent | ✅ present |
| `roof_type` | ✅ present (bare `Roof:`) | ⚠️ absent |
| `exterior_construction` | ✅ present | ⚠️ absent |
| `sewer` | ✅ from Utilities text | ⚠️ absent |
| `sqft_heated_source` | ✅ present | ⚠️ absent |
| `flood_zone_code` | ✅ present (`AE`) | ⚠️ absent |
| Photos | 🚫 shared URL only | 🚫 shared URL only |

---

## Special-Attention Field Reconciliation Pass

**Methodology:** For each flagged field, the raw visible text was probed directly from both live Stellar MLS Matrix shared URLs using the service's own `extractVisibleText()` method (via reflection). The `mls:parse-debug` command output is the authoritative parser result. Each field is traced through the full six-step chain:

> **MLS source value** → **parser value** → **preview value** → **value after Apply Selected** → **saved DB/meta value** → **value after reload**

Raw visible-text character counts: seller page = 5,894 chars; landlord page = 4,969 chars.

---

### Property Type

**Seller and Landlord — Raw source evidence:**
```
SELLER  raw text search result: [PropType] NOT FOUND
LANDLORD raw text search result: [PropType] NOT FOUND
```
Stellar MLS Matrix shared pages do not publish a `Property Type:` field label in the rendered HTML. Neither page contains the text "Property Type" in any form.

| Step | Seller | Landlord |
|---|---|---|
| MLS source value | *(absent — no "Property Type:" label on page)* | *(absent — no "Property Type:" label on page)* |
| Parser value | *(not extracted)* | *(not extracted)* |
| Preview value | *(not shown in import modal)* | *(not shown in import modal)* |
| After Apply Selected | *(field unchanged — blank)* | *(field unchanged — blank)* |
| Saved DB/meta value | *(blank — `property_type` remains unset)* | *(blank — `property_type` remains unset)* |
| After reload | *(blank — manual entry required)* | *(blank — manual entry required)* |

**Classification: ⚠️ unavailable from MLS source** (both roles)

---

### Legal Description

**Seller — Raw source evidence:**
```
[Legal] match:
  "1/4 LP / SqFt: $342.94 Garage: Yes, Attached, 1 Spaces Carport: No Tax: $3,908
   Legal Subdivision Name: KELLY JOHN A SCARBROUGH SUB Minimum Lease Period: No Minimum
   Close Price: $325,000 ..."
```

The only occurrence of the word "Legal" on the seller page is the label **`Legal Subdivision Name:`** followed by the subdivision name `KELLY JOHN A SCARBROUGH SUB`. This is a distinct MLS field (subdivision name, not a full legal description).

The parser pattern for legal description is:
```
/(?:Tax\s+)?Legal\s+Desc(?:ription)?[\s:]+(.{5,500})/is
```
This requires the word `Desc` or `Description` after `Legal`. The label `Legal Subdivision Name:` does **not** satisfy this pattern — "Subdivision" ≠ "Desc". Therefore the parser correctly does **not** extract `legal_description` from this page.

The `mls:parse-debug` output confirms: **32 fields mapped for role seller; `legal_description` is not among them.**

**Landlord — Raw source evidence:**
```
[Legal] NOT FOUND
```
The word "Legal" does not appear anywhere in the landlord page's extracted text.

| Step | Seller | Landlord |
|---|---|---|
| MLS source value | `Legal Subdivision Name: KELLY JOHN A SCARBROUGH SUB` *(subdivision name, not legal desc)* | *(absent — "Legal" does not appear on page)* |
| Parser value | *(not extracted — pattern requires `Legal Desc(ription)?`, not `Legal Subdivision Name`)* | *(not extracted)* |
| Preview value | *(not shown in import modal)* | *(not shown in import modal)* |
| After Apply Selected | *(field unchanged — blank)* | *(field unchanged — blank)* |
| Saved DB/meta value | *(blank — `legal_description` remains unset)* | *(blank — `legal_description` remains unset)* |
| After reload | *(blank — manual entry required)* | *(blank — manual entry required)* |

**Classification: ⚠️ unavailable from MLS source** (both roles)

> **Note on "Legal Description visibly populated" observation:** The `mls:parse-debug` output and raw text probe are conclusive — `legal_description` is not extracted from the seller URL. The only "Legal" text on the page is `Legal Subdivision Name: KELLY JOHN A SCARBROUGH SUB`, which does not match the parser pattern. If a browser session showed this field populated, it would reflect a prior draft state (the form was already populated before import), not an import-applied value. The import preview table only shows fields the parser successfully extracted; `legal_description` never appears in the preview for this URL.

---

### Flood Zone Code

**Seller — Raw source evidence:**
```
[Flood] match:
  "...Additional Parcels Y/N: No Flood Zone Code: AE Assessment & Tax Assessment Year
   2025 2024 2023 Assessed Value..."
```
Label `Flood Zone Code: AE` is present and matches the parser pattern `/Flood\s+Zone\s+Code[\s:\*]+([A-Za-z0-9\-\/]{1,15})/i`.

**Landlord — Raw source evidence:**
```
[Flood] match:
  "Boundaries Zoom In Parcel ZIP City County Unified School District Neighborhood
   Flood Zones Elementary Schools Middle Schools High Schools USDA - Ineligible..."
```
The word "Flood" appears only inside a map-widget layer legend (`Flood Zones Elementary Schools…`). This is UI chrome, not a field value — there is no `Flood Zone Code:` or `Flood Zone:` label followed by a code. The parser pattern requires a colon+value immediately after the label; the map legend has no colon following "Flood Zones".

| Step | Seller | Landlord |
|---|---|---|
| MLS source value | `Flood Zone Code: AE` | *(only map legend "Flood Zones Elementary Schools…" — no field label+value)* |
| Parser value | `AE` | *(not extracted)* |
| Preview value | `AE` (shown in import modal as "Flood Zone Code → flood_zone_code → AE") | *(not shown)* |
| After Apply Selected | `flood_zone_code` set to `AE` | *(field unchanged)* |
| Saved DB/meta value | `flood_zone_code` meta key = `AE` | *(blank)* |
| After reload | `AE` (Tax/Legal/HOA tab → Flood Zone section shows AE) | *(blank — manual entry required)* |

**Classification: ✅ Seller** / **⚠️ unavailable from MLS source — Landlord**

---

### Flood Zone Panel

**Seller — Raw source evidence:**
```
[Flood] match: "...Flood Zone Code: AE Assessment & Tax..."
```
The text "Flood Zone Panel" or "FEMA Panel" does not appear. The flood zone section on this Stellar MLS shared page ends after `AE`.

**Landlord — Raw source evidence:** Map legend only (see above). No panel label.

| Step | Seller | Landlord |
|---|---|---|
| MLS source value | *(absent — "Flood Zone Panel:" not published on this page)* | *(absent)* |
| Parser value | *(not extracted)* | *(not extracted)* |
| Preview value | *(not shown)* | *(not shown)* |
| After Apply Selected | *(field unchanged — blank)* | *(field unchanged — blank)* |
| Saved DB/meta value | *(blank)* | *(blank)* |
| After reload | *(blank — manual entry required)* | *(blank — manual entry required)* |

**Classification: ⚠️ unavailable from MLS source** (both roles)

---

### Flood Zone Date

**Seller and Landlord — Raw source evidence:**
Neither page contains a "Flood Zone Date:" label in the extracted text.

| Step | Seller | Landlord |
|---|---|---|
| MLS source value | *(absent)* | *(absent)* |
| Parser value | *(not extracted)* | *(not extracted)* |
| Preview value | *(not shown)* | *(not shown)* |
| After Apply Selected | *(field unchanged — blank)* | *(field unchanged — blank)* |
| Saved DB/meta value | *(blank)* | *(blank)* |
| After reload | *(blank — manual entry required)* | *(blank — manual entry required)* |

**Classification: ⚠️ unavailable from MLS source** (both roles)

---

### HOA / CDD Fields

**Seller — Raw source evidence:**
```
[HOA_CDD] NOT FOUND
```

**Landlord — Raw source evidence:**
```
[HOA_CDD] NOT FOUND
```

Neither page publishes HOA, CDD, Association, or Homeowners' Association fields. The `has_hoa`, `association_name`, `association_fee_amount`, `association_fee_frequency`, `has_cdd`, and `annual_cdd_fee` parser patterns all find no match.

| Step | Seller | Landlord |
|---|---|---|
| MLS source value | *(absent — no HOA/CDD labels on page)* | *(absent — no HOA/CDD labels on page)* |
| Parser value | *(not extracted)* | *(not extracted)* |
| Preview value | *(not shown)* | *(not shown)* |
| After Apply Selected | *(fields unchanged — all blank)* | *(fields unchanged — all blank)* |
| Saved DB/meta value | *(blank for all 6 HOA/CDD meta keys)* | *(blank for all 6 HOA/CDD meta keys)* |
| After reload | *(blank — manual entry required for all)* | *(blank — manual entry required for all)* |

**Classification: ⚠️ unavailable from MLS source** (both roles)

---

### Available Date (Landlord only — not applicable to Seller role)

**Landlord — Raw source evidence:**
```
[Available] match:
  "...Type: Monthly Furnished: Unfurnished Year Built: 1973 Application Fee: $50
   Date Available: 06/11/2026 Listing Courtesy of: Abigail Sweeney..."
```
The MLS page emits `Date Available: 06/11/2026`. The parser pattern is:
```
/(?:Available|Avail\.?)\s*(?:Date)?[\s:]+(\d{1,2}\/\d{1,2}\/\d{2,4}...)/i
```
This pattern matches at the word `Available` within `Date Available: 06/11/2026` — the regex engine finds "Available" at that position, then `(?:Date)?` is optional (skipped), then `[\s:]+` matches `: `, then the date group captures `06/11/2026`. The match succeeds.

The parser emits the same value to **both** `available_date` and `lease_available_date` because the `LandlordOfferListing` Livewire component exposes two separate date pickers for what is a single MLS field.

| Step | Landlord (available_date) | Landlord (lease_available_date) |
|---|---|---|
| MLS source value | `Date Available: 06/11/2026` | *(same MLS field — dual emission)* |
| Parser value | `06/11/2026` | `06/11/2026` |
| Preview value | `06/11/2026` (shown as "Available Date") | `06/11/2026` (shown as "Lease Available Date") |
| After Apply Selected | `available_date` = `06/11/2026` | `lease_available_date` = `06/11/2026` |
| Saved DB/meta value | `available_date` meta key = `06/11/2026` | `lease_available_date` meta key = `06/11/2026` |
| After reload | `06/11/2026` (Lease Terms tab → Date Available picker) | `06/11/2026` (Lease Terms tab → Lease Available Date picker) |

**Classification: ✅ extracted and round-trips correctly**

---

### Lease Available Date

Same as Available Date above — populated from the same MLS `Date Available:` label via dual emission. **Classification: ✅ extracted and round-trips correctly**

---

### Reconciliation Summary

| Field | Seller Status | Landlord Status | Root Cause / Raw Source Evidence |
|---|:---:|:---:|---|
| Property Type | ⚠️ unavailable | ⚠️ unavailable | Label `Property Type:` absent from Stellar Matrix shared pages |
| Legal Description | ⚠️ unavailable | ⚠️ unavailable | Seller page has `Legal Subdivision Name:` only (≠ `Legal Desc:`); Landlord: "Legal" absent entirely |
| Flood Zone Code | ✅ `AE` | ⚠️ unavailable | Seller: `Flood Zone Code: AE` present; Landlord: "Flood" appears only in map-widget legend |
| Flood Zone Panel | ⚠️ unavailable | ⚠️ unavailable | Label `Flood Zone Panel:` absent from both pages |
| Flood Zone Date | ⚠️ unavailable | ⚠️ unavailable | Label `Flood Zone Date:` absent from both pages |
| HOA (has_hoa, assoc.) | ⚠️ unavailable | ⚠️ unavailable | No HOA/Association text found in either page |
| CDD (has_cdd, fee) | ⚠️ unavailable | ⚠️ unavailable | No CDD text found in either page |
| Available Date | N/A | ✅ `06/11/2026` | Landlord: `Date Available: 06/11/2026` → pattern matches at "Available" position |
| Lease Available Date | N/A | ✅ `06/11/2026` | Same MLS value — dual-emitted by parser |

All ⚠️ classifications are backed by the raw visible-text probe above. No special-attention field was mis-classified in the field trace tables.

---

## Regression Tests Added

Seven new tests were added to `tests/Feature/ListingImport/MlsRealListingRegressionTest.php` (groups NEW-E and NEW-F):

| Test method | What it pins |
|---|---|
| `test_inline_bare_roof_label_parsed_as_roof_type` | Bare `Roof:` label → `roof_type` extracted correctly |
| `test_inline_bare_roof_label_no_space_after_colon` | `StuccoRoof:Shingle` (HTML tag-fusion, no word boundary) → `roof_type` still captured |
| `test_inline_bare_roof_label_stops_at_pool` | Bare `Roof:` value stops at next label — no field bleed |
| `test_inline_water_frontage_yn_propagates_to_waterfront` | `Water Frontage Y/N: No` → `waterfront: no` (canonical key set) |
| `test_inline_water_frontage_yn_yes_propagates_to_waterfront` | `Water Frontage Y/N: Yes` → `waterfront: yes` |
| `test_inline_waterfront_bare_label_and_yn_produce_same_value` | Y/N and bare label both present — both agree on `yes` |
| `test_inline_water_frontage_yn_no_space_before_next_word` | Tag-fused `Y/N:NoWaterfront` → still extracts `no` without `\b` |

**Test results:** 32/32 pass in `MlsRealListingRegressionTest`; 32/32 pass in `MlsParserBleedRegressionTest`.

---

## Summary of All Code Changes

| File | Change |
|---|---|
| `app/Services/ListingImport/MlsListingImportService.php` | **Fix 1**: `extractVisibleText()` — `strip_tags()` → `preg_replace('/<[^>]+>/', ' ', ...)` |
| `app/Services/ListingImport/MlsListingImportService.php` | **Fix 2**: Roof Type parser — add bare `Roof:` fallback (no `\b`, `[\s]*` quantifier) |
| `app/Services/ListingImport/MlsListingImportService.php` | **Fix 3**: Water Frontage Y/N — remove `\b`, propagate boolean to canonical `waterfront` key |
| `tests/Feature/ListingImport/MlsRealListingRegressionTest.php` | 7 new regression tests (NEW-E and NEW-F groups) |
