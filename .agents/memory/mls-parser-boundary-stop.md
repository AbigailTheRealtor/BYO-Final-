---
name: MLS parser boundary-stop over-firing
description: The $labelStop word-boundary pattern in the MLS parser fires on 8+ sub-words inside valid field values, silently truncating captured text. All 8 confirmed by live test SKIPs.
---

## Rule

Before adding any word to `$labelStop` in `MlsListingImportService::parseFields()`, verify it does not appear as a sub-word inside common field values. Require `\s*:` after the stop word rather than bare `\b` to terminate captures.

**Why:** Confirmed over-firing cases: `Furnished\b` fires inside "Unfurnished", `City\b` fires inside "Electricity" and "Dade City", `Sewer\b` inside "Public Sewer", `Available\b` inside "BB/HS Internet Available", `HOA\b` inside "Sunridge HOA", `Association\b` inside "Executive Commerce Park Association". Also: `[A-Za-z0-9\s\-]{1,30}` uses `\s` which includes `\n` — use `[^\n]` for single-line panel numbers.

**How to apply:** Run `php artisan test tests/Feature/ListingImport/MlsLiveImportAuditTest.php` after parser changes — the 8 `markTestSkipped()` tests will start FAILING (not skipping) when each bug is fixed, signaling that the test's `assertSame` needs updating to the correct expected value.
