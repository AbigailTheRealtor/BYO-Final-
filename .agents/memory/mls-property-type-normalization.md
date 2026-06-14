---
name: MLS property_type role-specific normalization
description: Seller/buyer/tenant use short form ('Residential'); landlord uses full form ('Residential Property'). HasMlsImport normalizes at apply time.
---

## The Rule

MLS text emits `property_type` as a verbose form: `"Residential Property"`, `"Commercial Property"`, `"Business Opportunity"`, `"Income/Multifamily"`, `"Vacant Land Sale"`.

**Seller / buyer / tenant** form options use the SHORT form (no " Property" suffix):
- `'Residential'`, `'Commercial'`, `'Business'`, `'Income'`, `'Vacant Land'`

**Landlord** form options use the FULL form:
- `'Residential Property'`, `'Commercial Property'`

**Why:** The blade conditionals `@if ($property_type === 'Residential')` vs `@if ($property_type === 'Residential Property')` are hard-coded per role; they must receive the exact form option value or the entire conditional section (sewer, water, utilities, bedrooms, etc.) never renders.

**How to apply:** Normalization lives in `HasMlsImport::normalizePropertyTypeForRole(string $value, string $role): string` (private static), called inside `applyImportedFields()` when `$canonicalKey === 'property_type'` — before the hasExisting guard.

## MLS → Form Mapping

| MLS raw value | seller/buyer/tenant | landlord |
|---------------|---------------------|----------|
| `"Residential Property"` | `'Residential'` | `'Residential Property'` |
| `"Residential"` | `'Residential'` | `'Residential Property'` |
| `"Single Family Residence"` | `'Residential'` | `'Residential Property'` |
| `"Condominium"` / `"Condo"` | `'Residential'` | `'Residential Property'` |
| `"Commercial Property"` | `'Commercial'` | `'Commercial Property'` |
| `"Business Opportunity"` | `'Business'` | pass-through |
| `"Income/Multifamily"` | `'Income'` | pass-through |
| `"Vacant Land Sale"` / `"Land"` | `'Vacant Land'` | pass-through |

## Tests

10 regression tests in `MlsGapFixesRegressionTest.php` via `ReflectionMethod` on the private static method. All pass verified via direct PHP reflection invocation.
