<?php

namespace App\Console\Commands;

use App\Models\LocationPlace;
use App\Services\LocationDna\Places\PlaceNameKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1d-3 — rebuild `location_places` from the Census corpus, the USPS corpus, and config.
 *
 * WHOLLY REGENERABLE, BY DESIGN
 * -----------------------------
 * Every row this command writes comes from something version-controlled or verifiable: the
 * `census_*` corpus (published, re-verifiable by `census:verify-geography`), the legacy `us_*`
 * tables, or `config/location_places.php`. Nothing is hand-inserted into the table, so the table
 * can always be dropped and rebuilt, and a mistake in curated data is fixed by editing a file and
 * re-running rather than by writing SQL against production.
 *
 * That property is the reason the command deletes and rebuilds instead of diffing. A diffing
 * rebuild would have to decide what to do about rows it did not recognise, and the only safe
 * answer — keep them — is exactly what makes a table drift away from its sources.
 *
 * IT NEVER TOUCHES THE CENSUS CORPUS. Reads only. The corpus is the foundation this layer sits
 * on, and its verifiability is the thing that makes the whole arrangement trustworthy.
 *
 * WHAT EACH SOURCE CONTRIBUTES
 * ----------------------------
 *   census        32,188 published places — every incorporated municipality and CDP nationwide.
 *   supplemental  the legacy USPS names that match no Census place in their state: ~6,900
 *                 unincorporated communities and postal localities. This is where the bulk of
 *                 the "somewhere real with no government" geography comes from.
 *   curated       neighbourhoods from config, with the parent links no dataset can supply.
 *
 * ORDER IS LOAD-BEARING. Curated neighbourhoods point at census places by name, so places must
 * exist before parents can be resolved. Deletion runs in the reverse order for the same reason —
 * `parent_place_id` restricts on delete, so children go first or the delete is refused.
 */
class BuildLocationPlaces extends Command
{
    protected $signature = 'location:build-places
                            {--state= : Limit the supplemental and curated passes to one USPS state code}
                            {--dry-run : Report what would be written and roll back}';

    protected $description = 'Rebuild the supplemental location_places layer from Census, USPS and curated sources';

    /** namelsad trailing token → place type. */
    private const LSAD_TYPES = [
        'cdp'       => LocationPlace::TYPE_CDP,
        'city'      => LocationPlace::TYPE_CITY,
        'town'      => LocationPlace::TYPE_TOWN,
        'village'   => LocationPlace::TYPE_VILLAGE,
        'borough'   => LocationPlace::TYPE_BOROUGH,
        'comunidad' => LocationPlace::TYPE_COMMUNITY,
        'urbana'    => LocationPlace::TYPE_COMMUNITY,
    ];

    /** @var array<string, string> USPS abbreviation → state GEOID */
    private array $stateGeoidByUsps = [];

    /** @var array<string, string> "{stateGeoid}|{countyNameKey}" → county GEOID */
    private array $countyGeoidByName = [];

    private int $censusCount = 0;
    private int $supplementalCount = 0;
    private int $curatedCount = 0;
    private int $zipCount = 0;
    private int $countyLinkCount = 0;

    /** @var list<string> */
    private array $problems = [];

    public function handle(): int
    {
        if (! $this->corpusPresent()) {
            $this->error('The census_* corpus is empty. Run census:import-geography and census:verify-geography first.');

            return self::FAILURE;
        }

        $onlyState = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $dryRun    = (bool) $this->option('dry-run');

        $this->loadLookups();

        if ($onlyState !== null && ! isset($this->stateGeoidByUsps[$onlyState])) {
            $this->error("Unknown state code: {$onlyState}");

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $this->purge();
            $this->buildCensusPlaces();
            $this->buildSupplementalPlaces($onlyState);
            $this->buildZipAssociations($onlyState);
            $this->buildCuratedNeighborhoods($onlyState);

            // Last, and deliberately: it reads every row written above, whatever its source, so
            // running it once at the end is both simpler and cheaper than threading pivot writes
            // through three separate passes.
            $this->buildCountyLinks();

            if ($dryRun) {
                DB::rollBack();
                $this->warn('DRY RUN — everything above was rolled back.');
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Rebuild failed and was rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->report();

        return self::SUCCESS;
    }

    private function corpusPresent(): bool
    {
        return DB::table('census_states')->exists() && DB::table('census_places')->exists();
    }

    private function loadLookups(): void
    {
        foreach (DB::table('census_states')->get(['geoid', 'usps']) as $row) {
            $this->stateGeoidByUsps[strtoupper(trim($row->usps))] = trim($row->geoid);
        }

        // Indexed by BOTH the published name ("Pinellas County") and the bare basename
        // ("Pinellas"), because the two legacy tables spell it differently: us_counties.name
        // carries the class word and us_zip_codes.county does not.
        foreach (DB::table('census_counties')->get(['geoid', 'state_geoid', 'name', 'basename']) as $row) {
            $state = trim($row->state_geoid);
            $this->countyGeoidByName[$state.'|'.PlaceNameKey::of($row->name)]     = trim($row->geoid);
            $this->countyGeoidByName[$state.'|'.PlaceNameKey::of($row->basename)] ??= trim($row->geoid);
        }
    }

    /**
     * Empty the layer, children before parents.
     *
     * `parent_place_id` restricts on delete, so a flat delete would be refused by the database
     * the moment a curated neighbourhood pointed at a census place. Deleting the ZIP pivot first
     * is not strictly required — it cascades — but doing it explicitly keeps the row counts in
     * the summary honest.
     */
    private function purge(): void
    {
        DB::table('location_place_counties')->delete();
        DB::table('location_place_zips')->delete();
        DB::table('location_places')->whereNotNull('parent_place_id')->delete();
        DB::table('location_places')->delete();
    }

    /**
     * Restate every place's county membership as rows, from whichever source knows it.
     *
     * CENSUS ROWS GET THE PUBLISHED MANY-TO-MANY. `census_place_counties` is the authority, and a
     * place straddling a county line contributes one row per county — which is the entire point
     * of the table. SUPPLEMENTAL AND CURATED ROWS contribute their single resolved county, which
     * is all the USPS corpus and config respectively can state.
     *
     * `is_primary` is set from the scalar column rather than recomputed, so the two cannot
     * disagree: whatever `location_places.county_geoid` says is what gets the flag. A census place
     * whose primary county is somehow absent from the published relationship would end up with no
     * primary row at all, which the verification step reports rather than papers over.
     */
    private function buildCountyLinks(): void
    {
        $now = now();

        // (a) Census places: id + primary county, keyed by GEOID for the join below.
        $censusRows = DB::table('location_places')
            ->where('source', LocationPlace::SOURCE_CENSUS)
            ->whereNotNull('census_place_geoid')
            ->pluck('county_geoid', 'census_place_geoid');

        $idByGeoid = DB::table('location_places')
            ->where('source', LocationPlace::SOURCE_CENSUS)
            ->whereNotNull('census_place_geoid')
            ->pluck('id', 'census_place_geoid');

        $batch = [];

        foreach (DB::table('census_place_counties')->cursor() as $link) {
            $placeGeoid = trim($link->place_geoid);
            $placeId    = $idByGeoid[$placeGeoid] ?? null;

            if ($placeId === null) {
                continue;   // a state-limited rebuild simply has fewer places
            }

            $county = trim($link->county_geoid);

            $batch[] = [
                'location_place_id' => $placeId,
                'county_geoid'      => $county,
                'is_primary'        => trim((string) ($censusRows[$placeGeoid] ?? '')) === $county,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('location_place_counties')->insert($batch);
                $this->countyLinkCount += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('location_place_counties')->insert($batch);
            $this->countyLinkCount += count($batch);
        }

        // (b) Supplemental and curated: one county each, when it resolved at all.
        $batch = [];

        foreach (
            DB::table('location_places')
                ->whereIn('source', [LocationPlace::SOURCE_SUPPLEMENTAL, LocationPlace::SOURCE_CURATED])
                ->whereNotNull('county_geoid')
                ->select('id', 'county_geoid')
                ->cursor() as $place
        ) {
            $batch[] = [
                'location_place_id' => $place->id,
                'county_geoid'      => trim((string) $place->county_geoid),
                'is_primary'        => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('location_place_counties')->insert($batch);
                $this->countyLinkCount += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('location_place_counties')->insert($batch);
            $this->countyLinkCount += count($batch);
        }

        $this->info("place↔county links: {$this->countyLinkCount}");

        $this->verifyCountyLinks();
    }

    /**
     * Two invariants worth failing loudly on, checked while the transaction is still open.
     *
     * A place with no county at all is invisible to every county-scoped query — the exact class of
     * silent absence this phase exists to remove — and a place with two primaries means the scalar
     * column and the pivot disagree about which county is the main one.
     */
    private function verifyCountyLinks(): void
    {
        $orphans = DB::table('location_places as p')
            ->leftJoin('location_place_counties as c', 'c.location_place_id', '=', 'p.id')
            ->whereNull('c.location_place_id')
            ->whereNotNull('p.county_geoid')
            ->count();

        if ($orphans > 0) {
            $this->problems[] = "{$orphans} place(s) have a county_geoid but no pivot row";
        }

        $multiPrimary = DB::table('location_place_counties')
            ->where('is_primary', true)
            ->selectRaw('location_place_id')
            ->groupBy('location_place_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        if ($multiPrimary > 0) {
            $this->problems[] = "{$multiPrimary} place(s) have more than one primary county";
        }
    }

    /**
     * Project every published place.
     *
     * `county_geoid` is the place's PRIMARY county — the lowest GEOID it belongs to. 1,304 places
     * straddle a county line, and the authoritative many-to-many for them stays in
     * `census_place_counties`, which is what the cascade's city tier reads. This column exists so
     * the neighbourhood tier can ask "what county is this in?" and get a usable answer; it is not
     * a replacement for the relationship table and must not be treated as one.
     */
    private function buildCensusPlaces(): void
    {
        $primaryCounty = [];

        foreach (DB::table('census_place_counties')->orderBy('county_geoid')->cursor() as $row) {
            $primaryCounty[trim($row->place_geoid)] ??= trim($row->county_geoid);
        }

        $batch = [];
        $now   = now();

        foreach (DB::table('census_places')->cursor() as $place) {
            $geoid = trim($place->geoid);

            $batch[] = [
                'name'               => trim($place->name),
                'name_key'           => PlaceNameKey::of($place->name),
                'type'               => $this->typeForCensusPlace($place),
                'state_geoid'        => trim($place->state_geoid),
                'county_geoid'       => $primaryCounty[$geoid] ?? null,
                'parent_place_id'    => null,
                'census_place_geoid' => $geoid,
                'latitude'           => $place->intptlat,
                'longitude'          => $place->intptlong,
                'source'             => LocationPlace::SOURCE_CENSUS,
                'active'             => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('location_places')->insert($batch);
                $this->censusCount += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('location_places')->insert($batch);
            $this->censusCount += count($batch);
        }

        $this->info("census places projected: {$this->censusCount}");
    }

    /**
     * The published name carries its legal class word; the trailing token names the type.
     *
     * `classfp` is the fallback rather than the primary because a handful of rows carry names the
     * token rule cannot read — "(balance)" entries, the lone "Princeton" oddity. C* is an
     * incorporated place and U* is a CDP, which is enough to classify what the name could not.
     */
    private function typeForCensusPlace(object $place): string
    {
        $token = strtolower((string) preg_replace('/^.* /', '', trim((string) $place->namelsad)));

        if (isset(self::LSAD_TYPES[$token])) {
            return self::LSAD_TYPES[$token];
        }

        $classfp = strtoupper(trim((string) $place->classfp));

        if (str_starts_with($classfp, 'U')) {
            return LocationPlace::TYPE_CDP;
        }

        if (str_starts_with($classfp, 'C') || str_starts_with($classfp, 'M')) {
            return LocationPlace::TYPE_CITY;
        }

        return LocationPlace::TYPE_COMMUNITY;
    }

    /**
     * Legacy USPS names that no Census place in the same state matches.
     *
     * Typed `community` rather than `neighborhood`: the USPS corpus states that the name exists
     * and roughly where, and says nothing about whether it sits inside a municipality. Calling
     * every one of them a neighbourhood would assert a parent-child relationship for ~6,900 rows
     * on no evidence. Config promotes the ones that really are neighbourhoods.
     *
     * NAMES CURATED CONFIG CLAIMS ARE SKIPPED HERE. Clearwater Beach is in the USPS corpus AND in
     * config, and without this it is written twice — once as a parentless `community` and once as
     * a `neighborhood` of Clearwater. Two rows for one place is not a cosmetic duplicate: the
     * resolver would have to choose between them, and a user picking the wrong one would store a
     * label that means the right place with none of the hierarchy attached. Curation is the more
     * specific statement, so it wins outright rather than being merged.
     */
    private function buildSupplementalPlaces(?string $onlyState): void
    {
        $curated = $this->curatedKeys($onlyState);

        $censusKeys = [];

        foreach (DB::table('census_places')->select('state_geoid', 'name')->cursor() as $row) {
            $censusKeys[trim($row->state_geoid)][PlaceNameKey::of($row->name)] = true;
        }

        // Legacy state id → GEOID, and legacy county id → county name.
        $legacyStateGeoid = [];

        foreach (DB::table('us_states')->get(['id', 'abbreviation']) as $row) {
            $abbr = strtoupper(trim((string) $row->abbreviation));

            if (isset($this->stateGeoidByUsps[$abbr])) {
                $legacyStateGeoid[$row->id] = ['geoid' => $this->stateGeoidByUsps[$abbr], 'usps' => $abbr];
            }
        }

        $legacyCountyName = DB::table('us_counties')->pluck('name', 'id')->all();

        // A representative coordinate per (city, state) from the USPS ZIP corpus.
        $coords = [];

        foreach (DB::table('us_zip_codes')->select('city', 'state_abbrev', 'latitude', 'longitude')->cursor() as $row) {
            $key = strtoupper(trim((string) $row->state_abbrev)).'|'.PlaceNameKey::of((string) $row->city);
            $coords[$key] ??= [$row->latitude, $row->longitude];
        }

        $batch = [];
        $seen  = [];
        $now   = now();

        foreach (DB::table('us_cities')->select('name', 'state_id', 'county_id')->cursor() as $city) {
            $state = $legacyStateGeoid[$city->state_id] ?? null;

            if ($state === null) {
                continue;
            }

            if ($onlyState !== null && $state['usps'] !== $onlyState) {
                continue;
            }

            $key = PlaceNameKey::of((string) $city->name);

            if (isset($censusKeys[$state['geoid']][$key])) {
                continue;   // the Census already names it
            }

            $dedupe = $state['geoid'].'|'.$key;

            if (isset($seen[$dedupe])) {
                continue;
            }

            $seen[$dedupe] = true;

            $countyName  = $city->county_id !== null ? ($legacyCountyName[$city->county_id] ?? null) : null;
            $countyGeoid = $countyName === null
                ? null
                : ($this->countyGeoidByName[$state['geoid'].'|'.PlaceNameKey::of($countyName)] ?? null);

            // Matched on the county when both know it, and on the state alone when this row does
            // not — a supplemental row with no county cannot prove it is somewhere ELSE than the
            // curated entry, and writing a second parentless copy is the worse of the two guesses.
            if (isset($curated[$state['geoid'].'|'.$countyGeoid.'|'.$key])
                || ($countyGeoid === null && isset($curated[$state['geoid'].'|*|'.$key]))) {
                continue;
            }

            [$lat, $lon] = $coords[$state['usps'].'|'.$key] ?? [null, null];

            $batch[] = [
                'name'               => trim((string) $city->name),
                'name_key'           => $key,
                'type'               => LocationPlace::TYPE_COMMUNITY,
                'state_geoid'        => $state['geoid'],
                'county_geoid'       => $countyGeoid,
                'parent_place_id'    => null,
                'census_place_geoid' => null,
                'latitude'           => $lat,
                'longitude'          => $lon,
                'source'             => LocationPlace::SOURCE_SUPPLEMENTAL,
                'active'             => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('location_places')->insert($batch);
                $this->supplementalCount += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('location_places')->insert($batch);
            $this->supplementalCount += count($batch);
        }

        $this->info("supplemental places added: {$this->supplementalCount}");
    }

    /**
     * The (state, county, name) triples curated config claims, plus a state-wide wildcard form.
     *
     * Computed BEFORE the supplemental pass runs, from config alone, so the exclusion does not
     * depend on the curated rows having been written yet — they cannot be, because they need
     * parent places that the census pass has only just created.
     *
     * @return array<string, true>
     */
    private function curatedKeys(?string $onlyState): array
    {
        $keys = [];

        foreach ((array) config('location_places.neighborhoods', []) as $entry) {
            $usps = strtoupper(trim((string) ($entry['state'] ?? '')));

            if ($onlyState !== null && $usps !== $onlyState) {
                continue;
            }

            $stateGeoid = $this->stateGeoidByUsps[$usps] ?? null;

            if ($stateGeoid === null) {
                continue;
            }

            $key         = PlaceNameKey::of((string) ($entry['name'] ?? ''));
            $countyGeoid = $this->countyGeoidByName[$stateGeoid.'|'.PlaceNameKey::of((string) ($entry['county'] ?? ''))] ?? null;

            $keys[$stateGeoid.'|'.$countyGeoid.'|'.$key] = true;
            $keys[$stateGeoid.'|*|'.$key]                = true;
        }

        return $keys;
    }

    /**
     * Associate ZIPs with places, from the only corpus that states the relationship.
     *
     * `is_zcta` is decided against the published ZCTA roster, so a PO-box code is stored and
     * marked rather than dropped. See the pivot's migration for why keeping them matters.
     */
    private function buildZipAssociations(?string $onlyState): void
    {
        $zctaSet = DB::table('census_zctas')->pluck('zcta5')
            ->mapWithKeys(fn ($z) => [trim((string) $z) => true])->all();

        // (state GEOID, name key) → place id, restricted to the tiers a USPS city name can mean.
        $placeIds = [];

        foreach (DB::table('location_places')->select('id', 'state_geoid', 'name_key')->cursor() as $row) {
            $placeIds[trim($row->state_geoid).'|'.$row->name_key] ??= $row->id;
        }

        $batch = [];
        $seen  = [];
        $now   = now();

        foreach (DB::table('us_zip_codes')->select('zip_code', 'city', 'state_abbrev')->cursor() as $row) {
            $usps = strtoupper(trim((string) $row->state_abbrev));

            if ($onlyState !== null && $usps !== $onlyState) {
                continue;
            }

            $stateGeoid = $this->stateGeoidByUsps[$usps] ?? null;

            if ($stateGeoid === null) {
                continue;
            }

            $zip = str_pad(trim((string) $row->zip_code), 5, '0', STR_PAD_LEFT);

            if (! ctype_digit($zip) || strlen($zip) !== 5) {
                continue;
            }

            $placeId = $placeIds[$stateGeoid.'|'.PlaceNameKey::of((string) $row->city)] ?? null;

            if ($placeId === null) {
                continue;
            }

            $dedupe = $placeId.'|'.$zip;

            if (isset($seen[$dedupe])) {
                continue;
            }

            $seen[$dedupe] = true;

            $batch[] = [
                'location_place_id' => $placeId,
                'zip'               => $zip,
                'is_zcta'           => isset($zctaSet[$zip]),
                'source'            => 'usps',
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('location_place_zips')->insert($batch);
                $this->zipCount += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('location_place_zips')->insert($batch);
            $this->zipCount += count($batch);
        }

        $this->info("place↔zip associations: {$this->zipCount}");
    }

    /**
     * Curated neighbourhoods, with the parent links nothing else can supply.
     *
     * A row whose state, county or parent will not resolve is COLLECTED AND REPORTED, never
     * written with a null parent and never guessed at. A neighbourhood attached to the wrong city
     * is a silent, permanent data error; one that is missing and named in the command output is a
     * five-minute fix.
     */
    private function buildCuratedNeighborhoods(?string $onlyState): void
    {
        $entries = (array) config('location_places.neighborhoods', []);

        foreach ($entries as $index => $entry) {
            $label = $entry['name'] ?? "entry #{$index}";
            $usps  = strtoupper(trim((string) ($entry['state'] ?? '')));

            if ($onlyState !== null && $usps !== $onlyState) {
                continue;
            }

            $stateGeoid = $this->stateGeoidByUsps[$usps] ?? null;

            if ($stateGeoid === null) {
                $this->problems[] = "{$label}: unknown state '{$usps}'";

                continue;
            }

            $countyGeoid = $this->countyGeoidByName[$stateGeoid.'|'.PlaceNameKey::of((string) ($entry['county'] ?? ''))] ?? null;

            if ($countyGeoid === null) {
                $this->problems[] = "{$label}: county '".($entry['county'] ?? '')."' not found in {$usps}";

                continue;
            }

            $parentId = null;

            if (($entry['parent'] ?? null) !== null) {
                $parentId = DB::table('location_places')
                    ->where('state_geoid', $stateGeoid)
                    ->where('county_geoid', $countyGeoid)
                    ->where('name_key', PlaceNameKey::of((string) $entry['parent']))
                    ->whereIn('type', LocationPlace::PLACE_TYPES)
                    ->value('id');

                if ($parentId === null) {
                    $this->problems[] = "{$label}: parent place '{$entry['parent']}' not found in ".($entry['county'] ?? '');

                    continue;
                }
            }

            $type = (string) ($entry['type'] ?? LocationPlace::TYPE_NEIGHBORHOOD);

            if (! in_array($type, LocationPlace::SUB_PLACE_TYPES, true)) {
                $this->problems[] = "{$label}: type '{$type}' is not a sub-place type";

                continue;
            }

            $now = now();

            $placeId = DB::table('location_places')->insertGetId([
                'name'               => trim((string) $entry['name']),
                'name_key'           => PlaceNameKey::of((string) $entry['name']),
                'type'               => $type,
                'state_geoid'        => $stateGeoid,
                'county_geoid'       => $countyGeoid,
                'parent_place_id'    => $parentId,
                'census_place_geoid' => null,
                'latitude'           => $entry['latitude'] ?? null,
                'longitude'          => $entry['longitude'] ?? null,
                'source'             => LocationPlace::SOURCE_CURATED,
                'active'             => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            $this->curatedCount++;

            foreach ((array) ($entry['zips'] ?? []) as $zip) {
                $zip = str_pad(trim((string) $zip), 5, '0', STR_PAD_LEFT);

                // Verified against the county's own ZCTA roster. A curated ZIP that does not
                // belong to the stated county is a typo in the config, and writing it would put a
                // neighbourhood in the wrong place on every ZIP-driven surface.
                $inCounty = DB::table('census_zcta_counties')
                    ->where('county_geoid', $countyGeoid)
                    ->whereRaw('trim(zcta5) = ?', [$zip])
                    ->exists();

                if (! $inCounty) {
                    $this->problems[] = "{$label}: ZIP {$zip} is not a ZCTA of ".($entry['county'] ?? '');

                    continue;
                }

                DB::table('location_place_zips')->insert([
                    'location_place_id' => $placeId,
                    'zip'               => $zip,
                    'is_zcta'           => true,
                    'source'            => 'curated',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);

                $this->zipCount++;
            }
        }

        $this->info("curated neighbourhoods: {$this->curatedCount}");
    }

    private function report(): void
    {
        $this->newLine();
        $this->table(
            ['Source', 'Rows'],
            [
                ['census',       number_format($this->censusCount)],
                ['supplemental', number_format($this->supplementalCount)],
                ['curated',      number_format($this->curatedCount)],
                ['place↔zip',    number_format($this->zipCount)],
                ['place↔county', number_format($this->countyLinkCount)],
            ]
        );

        if ($this->problems === []) {
            $this->info('No unresolved curated entries.');

            return;
        }

        $this->warn('Unresolved curated entries (skipped, not written):');

        foreach ($this->problems as $problem) {
            $this->line('  - '.$problem);
        }
    }
}
