---
name: Landlord MLS wiring gaps — 4 missing fields
description: LandlordOfferListing is missing lot_dimensions/roof_type/exterior_construction/foundation; adding to MlsFieldMap alone silently fails at property_exists() gate.
---

## Rule
Adding `lot_dimensions`, `roof_type`, `exterior_construction`, or `foundation` to `MlsFieldMap::landlord()` will have no effect. `HasMlsImport::importListingFromUrl()` has a `property_exists($this, $propName)` gate that filters the field before it ever reaches the preview.

## Why
Grep of `LandlordOfferListing.php` and all Landlord blade files returns zero matches for these four field names. The Seller component has complete implementations (public properties, JSON encode/decode save/load, Select2 blade inputs, validation rules). The Landlord component has none of this.

## How to apply
Any task touching MLS import coverage for Landlord and these four fields requires:
1. Declare `public string $lot_dimensions = ''` / `public array $roof_type = []` etc. on `LandlordOfferListing`
2. Add companion `$other_*` properties for the multi-select fields
3. JSON encode/decode in `saveDraft()` / `saveEdit()` / `loadDraft()`
4. Add Select2 multi-select blade inputs to the Landlord `property-preferences` tab matching Seller's enum options
5. Add validation rules
6. Only then add the field-map entry in `MlsFieldMap::landlord()`

Estimated effort: 11–15 hours including testing (not a one-line fix).
