# Runbook: Location Data Seeders

## Overview

City/county/state autocomplete in Create Offer Listing and Hire Agent flows is backed by the `us_cities`, `us_counties`, `us_states`, and `us_zip_codes` tables. If expected cities are missing from the autocomplete, the most likely cause is that a patch seeder was not run against the live database.

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

## Re-Running the Florida Cities Patch Seeder

The seeder is **idempotent** — safe to re-run at any time without creating duplicates.

```bash
php artisan db:seed --class=FlCitiesPatchSeeder
```

Expected output:
```
Running Florida cities patch seeder...
Florida patch complete — added: N, county updated: N, already correct: N.
Adding missing Florida zip code entries for county fallback...
Zip code patch complete — added N missing zip entries.
```

## Important: Database Connection

Always verify the artisan command runs against the **correct live DB** (`heliumdb`).
Check `.env` before running:

```bash
grep DB_DATABASE .env
grep DB_HOST .env
```

Expected: `DB_DATABASE=heliumdb`, `DB_HOST=helium` (or equivalent configured host).

## History

| Date       | Action                     | Result                        |
|------------|----------------------------|-------------------------------|
| 2026-05-06 | Re-ran FlCitiesPatchSeeder | FL cities: 64 → 111 (47 added) |

## Related Files

- `database/seeders/FlCitiesPatchSeeder.php` — Florida cities and zip code patch
- `app/Models/UsCity.php` — Eloquent model with state/county relations
- `app/Models/UsCounty.php` — Eloquent model with state relation
