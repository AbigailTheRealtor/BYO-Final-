---
name: Stellar tenant price ceiling bug
description: Monthly rental budget (maximum_budget) must NOT be used as list_price ceiling for tenant MLS matching — sale prices and rent are incomparable scales.
---

# Tenant MLS Matching — Monthly Budget ≠ Sale Price Ceiling

## The Rule
`TenantOfferListingCriteriaLoader` and `TenantCriteriaLoader` must emit `max_price = null`.
The tenant's `maximum_budget` (~$1k–$5k/month) is a monthly rental budget.
`bridge_properties.list_price` stores sale prices ($200k–$1M+).
Applying monthly rent as a `whereIn` sale price ceiling returns zero results.

**Why:** BuyerMatchQueryBuilder applies `max_price` as `list_price <= max_price` in SQL.
For tenants, no sale price ceiling is meaningful — emit null to skip the filter entirely.

**How to apply:** Any loader that maps tenant rental budget → `max_price` must set `max_price = null`.
Keep the parsed budget value available for future tenant-specific scoring if needed.

## Files Fixed
- `app/Services/Stellar/TenantOfferListingCriteriaLoader.php` — `maximum_budget` → `$maxPrice = null`
- `app/Services/Stellar/TenantCriteriaLoader.php` — `monthly_price` → `$maxPrice = null`
