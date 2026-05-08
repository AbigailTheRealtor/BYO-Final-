# Runbook: Location Data Seeders

## Overview

City/county/state autocomplete in Create Offer Listing and Hire Agent flows is backed by the `us_cities`, `us_counties`, `us_states`, `us_zip_codes`, and `us_zip_code_cities` tables. If expected cities are missing from the autocomplete, the most likely cause is that a patch seeder or ZIP import was not run against the live database.

## ZIP Code Architecture

### Primary table: `us_zip_codes`
Holds one row per ZIP (unique constraint on `zip_code`). The `city` column is the USPS-preferred city name for that ZIP. This table is queried first when autofilling city/county/state from a ZIP.

### Alias table: `us_zip_code_cities`
Holds alternate municipality names that share a ZIP code. For example, ZIP 33706 belongs to Saint Petersburg (primary) but Treasure Island is also a valid city for that ZIP. The alias table stores this relationship without touching the unique constraint on `us_zip_codes`.

Unique constraint: `(zip_code, city, state_abbrev)` — safe to re-run insertions.

### Model: `UsZipCode`
- `aliases()` — hasMany relationship to `UsZipCodeCity`
- `getZipCodesForCity($city, $state)` — checks both primary table and alias table, returns merged ZIP array
- `getAllCitiesForZip($zip)` — returns primary + all aliases for a given ZIP
- `searchZipCodes($input)` — prefix search on primary table (returns USPS-preferred city)

---

## Diagnosing Missing City Data

Run this in tinker to confirm the current FL city count:

```bash
php artisan tinker --execute="
echo DB::table('us_cities')
    ->join('us_states','us_cities.state_id','=','us_states.id')
    ->where('us_states.abbreviation','FL')
    ->count();
"
```

Expected count after `FlCitiesPatchSeeder` has run: **≥ 111**

Spot-check a specific city:

```bash
php artisan tinker --execute="
use App\Models\UsCity;
\$c = UsCity::with(['state','county'])
    ->where('name','Treasure Island')
    ->whereHas('state', fn(\$q) => \$q->where('abbreviation','FL'))
    ->first();
echo \$c ? \$c->name.', '.\$c->state->abbreviation.' → '.\$c->county?->name : 'NOT FOUND';
"
```

Spot-check a ZIP and its aliases:

```bash
php artisan tinker --execute="
\$all = App\Models\UsZipCode::getAllCitiesForZip('33706');
echo implode(', ', \$all);
"
```

---

## Re-Running the Florida Cities Patch Seeder

The seeder is **idempotent** — safe to re-run at any time without creating duplicates. It now also writes alias entries to `us_zip_code_cities` for shared-ZIP municipalities.

```bash
php artisan db:seed --class=FlCitiesPatchSeeder
```

Expected output:
```
Running Florida cities patch seeder...
Florida patch complete — added: N, county updated: N, already correct: N.
Adding missing Florida zip code entries for county fallback...
Zip code patch complete — added N missing zip entries, N alias entries.
```

---

## Nationwide ZIP Import (`zips:import-nationwide`)

### Overview
This command expands ZIP coverage from the GeoNames `US.zip` dataset (~41K rows) and populates the `us_zip_code_cities` alias table. It **never truncates** `us_zip_codes` — existing ZIPs and their IDs are always preserved.

### Dry run (always run first)
```bash
php artisan zips:import-nationwide --dry-run
```
Downloads GeoNames data, simulates all inserts inside a rolled-back transaction, and prints a change report. No data is committed.

### Live import
```bash
php artisan zips:import-nationwide
```
Runs the full import. New ZIPs are inserted into `us_zip_codes`; alternate city names are written to `us_zip_code_cities`. Existing rows are never modified.

### Using a pre-downloaded source file
```bash
# Pass the extracted US.txt directly (skips download):
php artisan zips:import-nationwide --source=/path/to/US.txt

# Or pass the zip archive:
php artisan zips:import-nationwide --source=/path/to/US.zip
```
Downloaded files are cached at `storage/app/us_zip_geonames/US.txt`.

### What the command does
1. Downloads GeoNames `US.zip` via HTTPS (or uses `--source`)
2. Parses tab-delimited `US.txt` — skips non-5-digit postal codes. Note: US territories
   (PR, VI, GU) use 5-digit ZIPs and pass the format check; they are filtered at the
   valid-states lookup step, which only accepts abbreviations present in `us_states`.
3. For each row:
   - If ZIP is absent from `us_zip_codes` → insert it (primary city)
   - If ZIP exists and incoming city ≠ primary → insert into `us_zip_code_cities` (alias)
4. Prints before/after counts and runs spot-checks

### Expected output after first run
```
ZIPs before:              34741
ZIPs after:               44991
New ZIPs added:           10250
Aliases before:           0
Aliases after:            222
New aliases added:        212
```
> Note: the 222 alias total includes 10 FL municipality aliases manually seeded
> (Treasure Island, Gulfport, Madeira Beach, etc.) that GeoNames does not include.

---

## Important: Database Connection

Always verify the artisan command runs against the **correct live DB** (`heliumdb`).
Check `.env` before running:

```bash
grep DB_DATABASE .env
grep DB_HOST .env
```

Expected: `DB_DATABASE=heliumdb`, `DB_HOST=helium` (or equivalent configured host).

---

## History

| Date       | Action                                        | Result                                                      |
|------------|-----------------------------------------------|-------------------------------------------------------------|
| 2026-05-06 | Re-ran FlCitiesPatchSeeder                    | FL cities: 64 → 111 (47 added)                              |
| 2026-05-08 | Ran `zips:import-nationwide` (GeoNames)       | ZIPs: 34,741 → 44,991 (+10,250); aliases: 0 → 222           |
| 2026-05-08 | Manually seeded 10 FL municipality aliases    | Treasure Island, Gulfport, Madeira Beach, etc. now resolve  |

---

## Related Files

- `database/seeders/FlCitiesPatchSeeder.php` — Florida cities and zip code patch (also writes aliases)
- `app/Console/Commands/ImportNationwideZips.php` — Nationwide ZIP import command
- `app/Models/UsZipCode.php` — Eloquent model with aliases() relationship and updated getZipCodesForCity()
- `app/Models/UsZipCodeCity.php` — Eloquent model for the alias pivot table
- `database/migrations/2026_05_08_000001_create_us_zip_code_cities_table.php` — Alias table migration
- `app/Models/UsCity.php` — Eloquent model with state/county relations
- `app/Models/UsCounty.php` — Eloquent model with state relation
