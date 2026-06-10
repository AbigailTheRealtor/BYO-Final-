---
name: MLS Data Entry Form PDFs — location and relationship to fieldInventory()
description: All 7 Stellar MLS Data Entry Form PDFs live in attached_assets/; fieldInventory() is the code-internal representation derived from them.
---

## Rule
The authoritative MLS field universe for crosswalk and audit work is the Stellar MLS Data Entry Form PDFs in `attached_assets/`. `MlsCoverageReporter::fieldInventory()` is the project's code-internal representation of those PDFs.

## Why
Any field present in the PDFs but absent from `fieldInventory()` is a gap in the reporter, not in the import pipeline. Any crosswalk audit should cite the PDFs as the primary source and note that fieldInventory() was derived from them.

## How to apply
- PDF files: `attached_assets/Residential_Data_Entry_Form_*.pdf`, `Rental_Data_Entry_Form_*.pdf`, `Vacant_Land_Data_Entry_Form_*.pdf`, `Income_Data_Entry_Form_*.pdf`, `Commercial_Sale_Data_Entry_Form_*.pdf`, `Commercial_Lease_Data_Entry_Form_*.pdf`, `Business_Opportunity_Data_Entry_Form_*.pdf` (multiple timestamp versions exist; use the most recent).
- `fieldInventory()` is in `app/Services/ListingImport/MlsCoverageReporter.php` starting around line 439.
- If a field is in the PDFs but not in `fieldInventory()`, it will appear as a "blind spot" — the reporter will never flag it as missing even if the parser and field map both lack it.
