<?php

namespace Tests\Feature\Spatial\OvertureV2Import;

use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureV2Import\InvalidOvertureV2Import;
use App\Services\Spatial\OvertureV2Import\OvertureV2CorpusImporter;
use App\Services\Spatial\OvertureV2Import\OvertureV2ImportGate;
use App\Services\Spatial\OvertureV2Import\OvertureV2ImportPlan;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The v2 schema and importer against a REAL PostGIS — LOCAL VALIDATION ONLY.
 *
 * CI provisions no PostGIS, so this class SKIPS unless a throwaway server is named explicitly:
 *
 *   OVERTURE_V2_SCRATCH_PGHOST      absolute Unix-socket DIRECTORY of a scratch server (required)
 *   OVERTURE_V2_SCRATCH_PGPORT      default 5432
 *   OVERTURE_V2_SCRATCH_PGUSER      the scratch superuser (CREATE DATABASE / CREATE EXTENSION)
 *   OVERTURE_V2_SCRATCH_PGDATABASE  its maintenance database, default `postgres`
 *
 * These are deliberately NOT the SPATIAL_* / PG* variables (tests/bootstrap.php blanks those): the
 * configured spatial database can never be selected here, and a TCP host is refused outright.
 * Every test creates its own `ov2t_*` database and drops it afterwards.
 *
 * The spatial migrations pin `protected $connection = 'pgsql_spatial'`, so to RUN them this test
 * points that connection's in-process config at the scratch database — never the environment,
 * never a file. The importer and command are pointed at a separate `overture_v2_scratch` name.
 */
class OvertureV2PostgisIntegrationTest extends TestCase
{
    use BuildsOvertureV2Extraction;

    private const SCRATCH = 'overture_v2_scratch';
    private const MAINTENANCE = 'overture_v2_scratch_maintenance';
    private const V2 = [
        '2026_09_24_000001_spatial_overture_v2_create_corpora.php',
        '2026_09_24_000002_spatial_overture_v2_create_places.php',
        '2026_09_24_000003_spatial_overture_v2_create_chain_memberships.php',
    ];
    private const EXTENSIONS_MIGRATION = '2026_07_16_000001_spatial_core_enable_extensions';
    private const V1_TABLES = [
        'place_categories', 'place_category_mappings', 'places', 'place_authority_links', 'boundaries',
        'boundaries_parts', 'listing_locations', 'addresses', 'isochrone_cache', 'corpus_imports',
    ];

    private ?string $database = null;

    protected function setUp(): void
    {
        parent::setUp();

        $host = (string) getenv('OVERTURE_V2_SCRATCH_PGHOST');
        if ($host === '') {
            $this->markTestSkipped('No scratch PostGIS named (OVERTURE_V2_SCRATCH_PGHOST). Local validation only.');
        }
        $this->assertStringStartsWith('/', $host, 'the scratch server must be a Unix-socket directory, never a network host');
        $this->assertDirectoryExists($host);
        foreach (['helium', 'heliumdb'] as $marker) {
            $this->assertStringNotContainsString($marker, strtolower($host . (string) getenv('OVERTURE_V2_SCRATCH_PGDATABASE')));
        }

        // tests/bootstrap.php blanks PGSERVICE to '' to close libpq's ambient fallback, but libpq
        // reads a PRESENT-but-empty PGSERVICE as a request for a service named "" and refuses to
        // connect. Removing it closes the fallback just as well; tearDown restores the blank.
        putenv('PGSERVICE');

        $base = [
            'driver' => 'pgsql', 'url' => null, 'host' => $host,
            'port' => (string) (getenv('OVERTURE_V2_SCRATCH_PGPORT') ?: '5432'),
            'username' => (string) (getenv('OVERTURE_V2_SCRATCH_PGUSER') ?: 'postgres'), 'password' => '',
            'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'schema' => 'public', 'sslmode' => 'disable',
        ];
        config(['database.connections.' . self::MAINTENANCE => $base + ['database' => (string) (getenv('OVERTURE_V2_SCRATCH_PGDATABASE') ?: 'postgres')]]);

        $this->database = 'ov2t_' . bin2hex(random_bytes(5));
        DB::connection(self::MAINTENANCE)->statement("CREATE DATABASE {$this->database}");

        $target = $base + ['database' => $this->database];
        config([
            'database.connections.' . self::SCRATCH => $target,
            'database.connections.pgsql_spatial' => $target,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownExtractions();
        if ($this->database !== null) {
            DB::purge(self::SCRATCH);
            DB::purge('pgsql_spatial');
            DB::connection(self::MAINTENANCE)->statement("DROP DATABASE IF EXISTS {$this->database} WITH (FORCE)");
            DB::purge(self::MAINTENANCE);
            putenv('PGSERVICE=');
        }
        parent::tearDown();
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────

    private function db(): ConnectionInterface
    {
        return DB::connection(self::SCRATCH);
    }

    private function migrate(array $files): void
    {
        $code = Artisan::call('migrate', [
            '--database' => 'pgsql_spatial',
            '--path' => array_map(fn ($f) => base_path('database/migrations/spatial/' . $f), $files),
            '--realpath' => true,
            '--force' => true,
        ]);
        $this->assertSame(0, $code, Artisan::output());
    }

    /** @return list<string> every v1 migration file except the extensions one, in order */
    private function v1Files(): array
    {
        $files = array_map('basename', glob(base_path('database/migrations/spatial') . '/2026_0[78]_*.php'));
        sort($files);

        return array_values(array_filter($files, fn ($f) => ! str_starts_with($f, self::EXTENSIONS_MIGRATION)));
    }

    /**
     * Runs the extensions migration. It pins PostGIS 3.6.3; on a scratch server with any other
     * version it refuses (and its transaction rolls back), in which case the same three extensions
     * are created directly and the migration is recorded as run — on the scratch database only.
     *
     * @return string the installed PostGIS version
     */
    private function extensions(): string
    {
        Artisan::call('migrate:install', ['--database' => 'pgsql_spatial']);
        try {
            $this->migrate([self::EXTENSIONS_MIGRATION . '.php']);
        } catch (\Throwable $e) {
            $this->assertStringContainsString('SIA-D39/E-49 pins [3.6.3]', $e->getMessage());
            foreach (['postgis', 'btree_gist', 'pg_trgm'] as $ext) {
                $this->db()->statement("CREATE EXTENSION IF NOT EXISTS {$ext}");
            }
            $this->db()->insert('INSERT INTO migrations (migration, batch) VALUES (?, 1)', [self::EXTENSIONS_MIGRATION]);
        }

        return (string) $this->db()->selectOne("SELECT extversion FROM pg_extension WHERE extname = 'postgis'")->extversion;
    }

    private function exists(string $table): bool
    {
        return $this->db()->selectOne('SELECT to_regclass(?) AS t', [$table])->t !== null;
    }

    private function scalarCount(string $sql, array $bindings = []): int
    {
        return (int) $this->db()->selectOne($sql, $bindings)->n;
    }

    /** Schema + data of every v1 table: columns, constraints, indexes and rows. */
    private function v1Snapshot(): array
    {
        $snap = [];
        foreach (self::V1_TABLES as $t) {
            $snap[$t] = [
                'columns' => $this->db()->select("SELECT column_name, data_type, udt_name, is_nullable, column_default FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? ORDER BY ordinal_position", [$t]),
                'constraints' => $this->db()->select("SELECT conname, pg_get_constraintdef(oid) AS def FROM pg_constraint WHERE conrelid = ?::regclass ORDER BY conname", [$t]),
                'indexes' => $this->db()->select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'public' AND tablename = ? ORDER BY indexname", [$t]),
                'rows' => $this->db()->selectOne("SELECT coalesce(json_agg(r ORDER BY r::text), '[]')::text AS j FROM {$t} r")->j,
            ];
        }

        return json_decode(json_encode($snap), true);
    }

    private function seedV1(): void
    {
        $db = $this->db();
        $db->insert("INSERT INTO place_categories (category_key, label, base_source, rank_strategy) VALUES ('grocery', 'Grocery', 'overture', 'authority')");
        $db->statement("CREATE TABLE places_v1_fixture PARTITION OF places FOR VALUES IN ('overture-2026-07-22.0-fl-r1')");
        $db->insert("INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name) VALUES ('overture-2026-07-22.0-fl-r1', 'overture', 'v1-ref-1', ST_GeogFromText('POINT(-82.64 27.77)'), ST_GeogFromText('POINT(-82.64 27.77)'), 'grocery', 'V1 Grocer')");
        $db->insert("INSERT INTO corpus_imports (dataset, corpus_version, row_count, status) VALUES ('overture_places', 'overture-2026-07-22.0-fl-r1', 1, 'active')");
    }

    private function importSchemaOnly(): void
    {
        $this->db()->statement('CREATE EXTENSION IF NOT EXISTS postgis');
        Artisan::call('migrate:install', ['--database' => 'pgsql_spatial']);
        $this->migrate(self::V2);
    }

    private function plan(string $dir, ?string $version = null): OvertureV2ImportPlan
    {
        return (new OvertureV2ImportGate(ChainRegistry::load()))->validate($this->contractFor($dir, [], $version), $dir);
    }

    private function import(OvertureV2ImportPlan $plan): string
    {
        return (new OvertureV2CorpusImporter())->import($this->db(), $plan);
    }

    private function corpus(string $version): object
    {
        return $this->db()->selectOne('SELECT * FROM overture_v2_corpora WHERE corpus_version = ?', [$version]);
    }

    private function assertViolates(string $sql, array $bindings, string $constraintOrState): void
    {
        try {
            $this->db()->transaction(fn () => $this->db()->statement($sql, $bindings));
            $this->fail("expected [{$constraintOrState}] to reject: {$sql}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($constraintOrState, $e->getMessage());
        }
    }

    // ── A. fresh ────────────────────────────────────────────────────────────────────────────────

    public function test_fresh_every_spatial_migration_applies_and_v2_has_its_keys_indexes_and_constraints(): void
    {
        $postgis = $this->extensions();
        $this->migrate(array_merge($this->v1Files(), self::V2));

        fwrite(STDERR, "\n  [scratch] PostgreSQL " . $this->db()->selectOne('SHOW server_version')->server_version . ", PostGIS {$postgis}, database {$this->database}\n");
        foreach (array_merge(self::V1_TABLES, OvertureV2CorpusImporter::TABLES) as $t) {
            $this->assertTrue($this->exists($t), $t);
        }
        $this->assertSame(16, $this->scalarCount('SELECT count(*) AS n FROM migrations'));

        $constraints = array_column($this->db()->select(
            "SELECT conname FROM pg_constraint WHERE conrelid IN ('overture_v2_corpora'::regclass, 'overture_v2_places'::regclass, 'overture_v2_chain_memberships'::regclass) ORDER BY conname"
        ), 'conname');
        foreach ([
            'overture_v2_corpora_failed', 'overture_v2_corpora_lanes', 'overture_v2_corpora_ready', 'overture_v2_corpora_registry', 'overture_v2_corpora_rule_hash',
            'overture_v2_corpora_source', 'overture_v2_corpora_status', 'overture_v2_places_confidence', 'overture_v2_places_id_corpus',
            'overture_v2_places_lane_contract', 'overture_v2_places_source', 'overture_v2_places_source_ref', 'overture_v2_places_status_known',
            'overture_v2_chain_memberships_place', 'overture_v2_chain_memberships_registry', 'overture_v2_chain_memberships_role', 'overture_v2_chain_memberships_rule_hash',
            'overture_v2_chain_memberships_unique',
        ] as $name) {
            $this->assertContains($name, $constraints);
        }

        $this->assertSame('geography(Point,4326)', $this->db()->selectOne("SELECT format_type(atttypid, atttypmod) AS t FROM pg_attribute WHERE attrelid = 'overture_v2_places'::regclass AND attname = 'geom'")->t);
        $gist = $this->db()->selectOne("SELECT am.amname, opc.opcname FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid JOIN pg_am am ON am.oid = c.relam JOIN pg_opclass opc ON opc.oid = i.indclass[0] WHERE c.relname = 'overture_v2_places_geom'");
        $this->assertSame(['gist', 'gist_geography_ops'], [$gist->amname, $gist->opcname]);

        $fk = fn (string $name) => $this->db()->selectOne('SELECT confdeltype FROM pg_constraint WHERE conname = ?', [$name])->confdeltype;
        $this->assertSame('c', $fk('overture_v2_chain_memberships_place'), 'memberships die with their place');
        $this->assertSame('a', $fk('overture_v2_places_corpus_version_fkey'), 'a corpus with places cannot be deleted');

        // The composite FK (place_id, corpus_version) is served by the unique index led by place_id.
        $this->assertStringContainsString('(place_id, brand_key)', $this->db()->selectOne("SELECT indexdef FROM pg_indexes WHERE indexname = 'overture_v2_chain_memberships_unique'")->indexdef);
    }

    public function test_fresh_the_extensions_migration_still_enforces_its_postgis_pin(): void
    {
        Artisan::call('migrate:install', ['--database' => 'pgsql_spatial']);
        $available = $this->db()->selectOne("SELECT default_version FROM pg_available_extensions WHERE name = 'postgis'")->default_version;
        if ($available === '3.6.3') {
            $this->markTestSkipped('this scratch server carries the pinned PostGIS');
        }

        try {
            $this->migrate([self::EXTENSIONS_MIGRATION . '.php']);
            $this->fail('the pin guard accepted PostGIS ' . $available);
        } catch (\Throwable $e) {
            $this->assertStringContainsString("is at version [{$available}] but SIA-D39/E-49 pins [3.6.3]", $e->getMessage());
        }
        $this->assertSame(0, $this->scalarCount("SELECT count(*) AS n FROM pg_extension WHERE extname = 'postgis'"), 'the refused migration rolled back');
    }

    // ── B. upgrade + C. rollback ────────────────────────────────────────────────────────────────

    public function test_upgrade_adds_v2_beside_a_populated_v1_and_rollback_removes_only_v2(): void
    {
        $this->extensions();
        $this->migrate($this->v1Files());
        $this->seedV1();
        $providers = config('location_providers');
        $overturePoi = config('overture_corpus_poi');
        $before = $this->v1Snapshot();
        $this->assertFalse($this->exists('overture_v2_corpora'));

        // B. upgrade
        $this->migrate(self::V2);
        foreach (OvertureV2CorpusImporter::TABLES as $t) {
            $this->assertTrue($this->exists($t), $t);
        }
        $this->assertSame($before, $this->v1Snapshot(), 'v1 schema and data are unchanged by the v2 migrations');
        // v2 arrived as ONE batch of its own, after every v1 batch — so a rollback of it is v2 only.
        $v2Batches = array_map('intval', array_column($this->db()->select("SELECT DISTINCT batch FROM migrations WHERE migration LIKE '%overture_v2%'"), 'batch'));
        $this->assertCount(1, $v2Batches);
        $this->assertGreaterThan($this->scalarCount("SELECT max(batch) AS n FROM migrations WHERE migration NOT LIKE '%overture_v2%'"), $v2Batches[0]);

        // Importing into v2 changes nothing in v1 either.
        $this->assertSame(OvertureV2CorpusImporter::IMPORTED, $this->import($this->plan($this->extractFixture())));
        $this->assertSame($before, $this->v1Snapshot(), 'v1 schema and data are unchanged by a v2 import');
        $this->assertSame($providers, config('location_providers'), 'no provider routing moved');
        $this->assertSame($overturePoi, config('overture_corpus_poi'), 'no corpus pin moved');

        // C. rollback — the v2 batch only, children first.
        $code = Artisan::call('migrate:rollback', ['--database' => 'pgsql_spatial', '--step' => 3, '--force' => true,
            '--path' => [base_path('database/migrations/spatial')], '--realpath' => true]);
        $this->assertSame(0, $code, Artisan::output());
        foreach (OvertureV2CorpusImporter::TABLES as $t) {
            $this->assertFalse($this->exists($t), $t);
        }
        $this->assertSame(0, $this->scalarCount("SELECT count(*) AS n FROM pg_class WHERE relname LIKE 'overture_v2_%'"), 'no v2 index, sequence or table survives');
        $this->assertSame($before, $this->v1Snapshot(), 'rollback leaves v1 exactly as it was');
        $this->assertSame(13, $this->scalarCount('SELECT count(*) AS n FROM migrations'));

        // Re-apply, and the importer works on the re-created schema.
        $this->migrate(self::V2);
        $this->assertSame(OvertureV2CorpusImporter::IMPORTED, $this->import($this->plan($this->extractFixture())));
        $this->assertSame(7, $this->scalarCount('SELECT count(*) AS n FROM overture_v2_places'));
        $this->assertSame($before, $this->v1Snapshot());
    }

    // ── importer: valid import ──────────────────────────────────────────────────────────────────

    public function test_the_command_imports_a_valid_extraction_into_the_named_scratch_connection(): void
    {
        $this->importSchemaOnly();
        $dir = $this->extractFixture();
        config(['overture_v2_corpus' => $this->contractConfigFor($dir)]);

        $code = Artisan::call('corpus:import-overture-v2', ['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir, '--write' => true, '--database' => self::SCRATCH]);
        $out = Artisan::output(); // fetching clears the buffer: read it once
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('IMPORTED — ' . $this->fixtureVersion . ' is ready (not active; nothing was activated)', $out);

        $c = $this->corpus($this->fixtureVersion);
        $this->assertSame('ready', $c->status);
        $this->assertSame([5, 5, 10, 2, 2, 1, 7], [(int) $c->base_rows, (int) $c->supplementary_rows, (int) $c->matcher_analysis_rows, (int) $c->diagnostic_rows, (int) $c->rescue_admitted_rows, (int) $c->rescue_refused_rows, (int) $c->expected_memberships]);
        $this->assertSame([5, 2, 7], [(int) $c->imported_base_rows, (int) $c->imported_rescued_rows, (int) $c->imported_memberships]);
        $this->assertSame(ChainRegistry::load()->ruleHash(), $c->registry_rule_hash);
        $this->assertSame(hash_file('sha256', $dir . '/manifest.json'), $c->manifest_sha256);
        $this->assertNull($c->failure_reason);

        $refs = array_column($this->db()->select('SELECT source_ref FROM overture_v2_places ORDER BY source_ref'), 'source_ref');
        $this->assertSame(['im-01', 'im-02', 'im-03', 'im-04', 'im-05', 'im-06', 'im-07'], $refs, 'diagnostic and refused rows are never stored');

        $rescued = $this->db()->selectOne("SELECT lane, supplementary_role, materialization_policy, rescue_verdict, rescued_lane, rescued_chain, rescued_as_category, rescued_format, category_key, source_category FROM overture_v2_places WHERE source_ref = 'im-06'");
        $this->assertSame(['supplementary', 'rescue_candidate', 'rescued', 'admitted', 'cvs_shopping', 'cvs', 'drugstore', 'store', null, 'shopping'], array_values((array) $rescued));
        $this->assertSame('store_in_target', $this->db()->selectOne("SELECT rescued_format FROM overture_v2_places WHERE source_ref = 'im-07'")->rescued_format);

        $geo = $this->db()->selectOne("SELECT ST_SRID(geom::geometry) AS srid, ST_X(geom::geometry) AS x, ST_Y(geom::geometry) AS y FROM overture_v2_places WHERE source_ref = 'im-01'");
        $this->assertSame([4326, -82.64, 27.77], [(int) $geo->srid, round((float) $geo->x, 6), round((float) $geo->y, 6)]);
    }

    public function test_every_membership_shape_persists(): void
    {
        $this->importSchemaOnly();
        $this->import($this->plan($this->extractFixture()));

        $rows = $this->db()->select("SELECT p.source_ref, m.brand_key, m.role, m.format_key, m.storefront_status, array_to_json(m.co_brand_with)::text AS co, m.rescued_from_source_category AS rescued_from, m.registry_rule_hash FROM overture_v2_chain_memberships m JOIN overture_v2_places p ON p.id = m.place_id AND p.corpus_version = m.corpus_version ORDER BY p.source_ref, m.brand_key");
        $got = array_map(fn ($r) => [$r->source_ref, $r->brand_key, $r->role, $r->format_key, $r->storefront_status, $r->co, $r->rescued_from], $rows);

        $this->assertSame([
            ['im-02', 'seven_eleven', 'storefront', 'store', 'storefront', '["speedway"]', null],
            ['im-02', 'speedway', 'storefront', 'store', 'storefront', '["seven_eleven"]', null],
            ['im-03', 'publix', 'department', 'pharmacy_department', 'storefront_unconfirmed', '[]', null],
            ['im-04', 'wawa', 'fuel', 'fuel', 'fuel', '[]', null],
            ['im-05', 'cvs', 'storefront', 'store_in_target', 'storefront', '[]', null],
            ['im-06', 'cvs', 'storefront', 'store', 'storefront', '[]', 'shopping'],
            ['im-07', 'cvs', 'storefront', 'store_in_target', 'storefront', '[]', 'shopping'],
        ], $got);
        $this->assertSame([ChainRegistry::load()->ruleHash()], array_values(array_unique(array_column($rows, 'registry_rule_hash'))));
    }

    // ── idempotency / version collision ─────────────────────────────────────────────────────────

    public function test_a_ready_corpus_reimported_from_the_same_files_is_an_explicit_no_op(): void
    {
        $this->importSchemaOnly();
        $dir = $this->extractFixture();
        $this->import($this->plan($dir));
        $first = $this->corpus($this->fixtureVersion);

        $this->assertSame(OvertureV2CorpusImporter::ALREADY_READY, $this->import($this->plan($dir)));
        $this->assertEquals($first, $this->corpus($this->fixtureVersion), 'nothing on the corpus row moved');
        $this->assertSame([7, 7], [$this->scalarCount('SELECT count(*) AS n FROM overture_v2_places'), $this->scalarCount('SELECT count(*) AS n FROM overture_v2_chain_memberships')]);
    }

    public function test_a_ready_corpus_is_never_overwritten_by_different_files(): void
    {
        $this->importSchemaOnly();
        $this->import($this->plan($this->extractFixture()));
        $before = $this->corpus($this->fixtureVersion);

        $other = $this->extractFixture();
        $this->editRow($other, 'base.ndjson', 'im-01', function (array &$r): void {
            $r['name'] = 'Joe\'s Diner (moved)';
        });
        try {
            $this->import($this->plan($other));
            $this->fail('a ready corpus was overwritten');
        } catch (InvalidOvertureV2Import $e) {
            $this->assertStringContainsString('already ready with DIFFERENT checksums', $e->getMessage());
        }

        $this->assertEquals($before, $this->corpus($this->fixtureVersion));
        $this->assertSame("Joe's Diner", $this->db()->selectOne("SELECT name FROM overture_v2_places WHERE source_ref = 'im-01'")->name);
    }

    public function test_two_corpora_live_side_by_side_and_share_source_refs(): void
    {
        $this->importSchemaOnly();
        $dir = $this->extractFixture();
        $this->import($this->plan($dir));
        $this->assertSame(OvertureV2CorpusImporter::IMPORTED, $this->import($this->plan($dir, 'overture-2026-08-19.0-fl-r91')));

        $this->assertSame(14, $this->scalarCount('SELECT count(*) AS n FROM overture_v2_places'));
        $this->assertSame(2, $this->scalarCount("SELECT count(*) AS n FROM overture_v2_places WHERE source_ref = 'im-01'"));
        $this->assertSame(2, $this->scalarCount("SELECT count(*) AS n FROM overture_v2_corpora WHERE status = 'ready'"));
    }

    // ── transaction ─────────────────────────────────────────────────────────────────────────────

    public function test_a_failure_after_places_are_inserted_rolls_everything_back_marks_failed_and_retries_cleanly(): void
    {
        $this->importSchemaOnly();
        $dir = $this->extractFixture();
        // Another corpus, ready, that the failing version must not disturb.
        $this->import($this->plan($dir, 'overture-2026-08-19.0-fl-r91'));
        $neighbour = $this->corpus('overture-2026-08-19.0-fl-r91');

        // Deterministic failure AFTER the place batch and INSIDE the membership insert.
        $this->db()->unprepared(<<<'SQL'
            CREATE FUNCTION ov2_injected_failure() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'ov2 injected failure after % places', (SELECT count(*) FROM overture_v2_places WHERE corpus_version = NEW.corpus_version); END $$;
            CREATE TRIGGER ov2_injected_failure BEFORE INSERT ON overture_v2_chain_memberships
              FOR EACH ROW WHEN (NEW.corpus_version = 'overture-2026-08-19.0-fl-r90') EXECUTE FUNCTION ov2_injected_failure();
        SQL);

        try {
            $this->import($this->plan($dir));
            $this->fail('the injected failure did not surface');
        } catch (InvalidOvertureV2Import $e) {
            $this->assertStringContainsString('rolled back', $e->getMessage());
            $this->assertStringContainsString('ov2 injected failure after 7 places', $e->getMessage(), 'the places WERE inserted before the failure');
        }

        $failed = $this->corpus($this->fixtureVersion);
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('ov2 injected failure', (string) $failed->failure_reason);
        $this->assertNotNull($failed->finished_at);
        $this->assertNull($failed->imported_base_rows);
        $this->assertSame(0, $this->scalarCount('SELECT count(*) AS n FROM overture_v2_places WHERE corpus_version = ?', [$this->fixtureVersion]));
        $this->assertSame(0, $this->scalarCount('SELECT count(*) AS n FROM overture_v2_chain_memberships WHERE corpus_version = ?', [$this->fixtureVersion]));
        $this->assertEquals($neighbour, $this->corpus('overture-2026-08-19.0-fl-r91'), 'the other corpus is untouched');

        // Retry from `failed`: clean, complete, no duplicates.
        $this->db()->unprepared('DROP TRIGGER ov2_injected_failure ON overture_v2_chain_memberships; DROP FUNCTION ov2_injected_failure();');
        $this->assertSame(OvertureV2CorpusImporter::IMPORTED, $this->import($this->plan($dir)));
        $ready = $this->corpus($this->fixtureVersion);
        $this->assertSame(['ready', null], [$ready->status, $ready->failure_reason]);
        $this->assertSame([7, 7], [
            $this->scalarCount('SELECT count(*) AS n FROM overture_v2_places WHERE corpus_version = ?', [$this->fixtureVersion]),
            $this->scalarCount('SELECT count(*) AS n FROM overture_v2_chain_memberships WHERE corpus_version = ?', [$this->fixtureVersion]),
        ]);
        $this->assertEquals($neighbour, $this->corpus('overture-2026-08-19.0-fl-r91'));
    }

    public function test_an_interrupted_preparing_corpus_is_retried_without_duplicating_rows(): void
    {
        $this->importSchemaOnly();
        $dir = $this->extractFixture();
        $plan = $this->plan($dir);
        // What a process killed between step 1 and step 2 leaves behind: `preparing`, no rows.
        $this->db()->insert(
            "INSERT INTO overture_v2_corpora (corpus_version, status, import_run, source_release, extract_recipe_version, taxonomy_map_version, registry_version, registry_rule_hash, registry_match_precedence_version, registry_normalizer_version, manifest_sha256, base_sha256, supplementary_sha256, base_rows, supplementary_rows, matcher_analysis_rows, diagnostic_rows, rescue_admitted_rows, rescue_refused_rows, expected_memberships, manifest, started_at) VALUES (?, 'preparing', 'a-crashed-run', '2026-08-19.0', 'overture-extract-v2', 'overture-taxonomy-v2.0', 'chain-registry-v2', ?, 'x', 'x', 'stale', 'stale', 'stale', 5, 5, 10, 2, 2, 1, 7, '{}', now() - interval '1 day')",
            [$this->fixtureVersion, $plan->contract->registryRuleHash],
        );

        $this->assertSame(OvertureV2CorpusImporter::IMPORTED, $this->import($plan));
        $c = $this->corpus($this->fixtureVersion);
        $this->assertSame(['ready', $plan->manifestSha256], [$c->status, $c->manifest_sha256]);
        $this->assertSame(7, $this->scalarCount('SELECT count(*) AS n FROM overture_v2_places'));
    }

    // ── the database's own guarantees ───────────────────────────────────────────────────────────

    public function test_the_schema_itself_refuses_what_the_importer_never_writes(): void
    {
        $this->importSchemaOnly();
        $this->import($this->plan($this->extractFixture()));
        $v = $this->fixtureVersion;
        $placeCols = "(corpus_version, source, source_ref, source_release, extract_recipe_version, taxonomy_map_version, lane, supplementary_role, materialization_policy, rescue_verdict, rescued_lane, rescued_chain, rescued_as_category, rescued_format, category_key, confidence, operating_status_known, geom, eligibility)";
        $point = "ST_GeogFromText('POINT(-82.64 27.77)')";

        // matcher_only — diagnostic or refused — can never be stored as a place.
        $this->assertViolates("INSERT INTO overture_v2_places {$placeCols} VALUES (?, 'overture', 'x-diag', 'r', 'e', 't', 'supplementary', 'diagnostic', 'matcher_only', 'not_candidate', NULL, NULL, NULL, NULL, NULL, 0.95, false, {$point}, 'supplementary_selector')", [$v], 'overture_v2_places_lane_contract');
        $this->assertViolates("INSERT INTO overture_v2_places {$placeCols} VALUES (?, 'overture', 'x-ref', 'r', 'e', 't', 'supplementary', 'rescue_candidate', 'matcher_only', 'refused', NULL, NULL, NULL, NULL, NULL, 0.95, false, {$point}, 'supplementary_selector')", [$v], 'overture_v2_places_lane_contract');
        // DEFECT FIX: a rescued row with an unknown verdict or role used to pass (NULL CHECK).
        $this->assertViolates("INSERT INTO overture_v2_places {$placeCols} VALUES (?, 'overture', 'x-null', 'r', 'e', 't', 'supplementary', NULL, 'rescued', NULL, 'cvs_shopping', 'cvs', 'drugstore', 'store', NULL, 0.95, false, {$point}, 'supplementary_selector')", [$v], 'overture_v2_places_lane_contract');
        // A base row must carry a category and no rescue fields.
        $this->assertViolates("INSERT INTO overture_v2_places {$placeCols} VALUES (?, 'overture', 'x-base', 'r', 'e', 't', 'base', NULL, 'corpus', NULL, NULL, NULL, NULL, NULL, NULL, 0.95, false, {$point}, 'base_category_eligible')", [$v], 'overture_v2_places_lane_contract');
        // One Overture place once per corpus.
        $this->assertViolates("INSERT INTO overture_v2_places {$placeCols} VALUES (?, 'overture', 'im-01', 'r', 'e', 't', 'base', NULL, 'corpus', NULL, NULL, NULL, NULL, NULL, 'restaurant', 0.95, false, {$point}, 'base_category_eligible')", [$v], 'overture_v2_places_source_ref');

        // DEFECT FIX: `ready` with unknown imported counts used to pass (NULL CHECK).
        $this->assertViolates('UPDATE overture_v2_corpora SET imported_memberships = NULL WHERE corpus_version = ?', [$v], 'overture_v2_corpora_ready');
        $this->assertViolates('UPDATE overture_v2_corpora SET imported_base_rows = imported_base_rows - 1 WHERE corpus_version = ?', [$v], 'overture_v2_corpora_ready');
        $this->assertViolates("UPDATE overture_v2_corpora SET status = 'active' WHERE corpus_version = ?", [$v], 'overture_v2_corpora_status');
        $this->assertViolates("UPDATE overture_v2_corpora SET status = 'failed' WHERE corpus_version = ?", [$v], 'overture_v2_corpora_failed');

        $place = (int) $this->db()->selectOne("SELECT id FROM overture_v2_places WHERE source_ref = 'im-01'")->id;
        $hash = ChainRegistry::load()->ruleHash();
        $memberCols = '(corpus_version, place_id, brand_key, role, format_key, match_method, storefront_status, registry_version, registry_rule_hash)';

        // Several distinct chains on one place: allowed. The same chain twice: refused.
        $this->db()->insert("INSERT INTO overture_v2_chain_memberships {$memberCols} VALUES (?, ?, 'publix', 'storefront', 'store', 'name_alias', 'storefront', 'chain-registry-v2', ?)", [$v, $place, $hash]);
        $this->db()->insert("INSERT INTO overture_v2_chain_memberships {$memberCols} VALUES (?, ?, 'wawa', 'fuel', 'fuel', 'name_alias', 'fuel', 'chain-registry-v2', ?)", [$v, $place, $hash]);
        $this->assertViolates("INSERT INTO overture_v2_chain_memberships {$memberCols} VALUES (?, ?, 'publix', 'storefront', 'store', 'name_alias', 'storefront', 'chain-registry-v2', ?)", [$v, $place, $hash], 'overture_v2_chain_memberships_unique');
        // A membership can never point across corpora.
        $this->assertViolates("INSERT INTO overture_v2_chain_memberships {$memberCols} VALUES ('overture-2026-08-19.0-fl-r91', ?, 'aldi', 'storefront', 'store', 'name_alias', 'storefront', 'chain-registry-v2', ?)", [$place, $hash], 'overture_v2_chain_memberships_place');
        // A membership must carry its own corpus's registry — never another hash, never a second
        // copy of the same chain under a different one.
        $this->assertViolates("INSERT INTO overture_v2_chain_memberships {$memberCols} VALUES (?, ?, 'aldi', 'storefront', 'store', 'name_alias', 'storefront', 'chain-registry-v2', ?)", [$v, $place, str_repeat('f', 64)], 'overture_v2_chain_memberships_registry');
        $this->assertViolates("INSERT INTO overture_v2_chain_memberships {$memberCols} VALUES (?, ?, 'aldi', 'storefront', 'store', 'name_alias', 'storefront', 'chain-registry-v1', ?)", [$v, $place, $hash], 'overture_v2_chain_memberships_registry');
        // A role and a storefront status must agree.
        $this->assertViolates("INSERT INTO overture_v2_chain_memberships {$memberCols} VALUES (?, ?, 'aldi', 'department', 'store', 'name_alias', 'storefront', 'chain-registry-v2', ?)", [$v, $place, $hash], 'overture_v2_chain_memberships_role');

        // Deleting a corpus with places is refused; deleting a place takes its memberships.
        $this->assertViolates('DELETE FROM overture_v2_corpora WHERE corpus_version = ?', [$v], 'overture_v2_places_corpus_version_fkey');
        $this->db()->delete('DELETE FROM overture_v2_places WHERE id = ?', [$place]);
        $this->assertSame(0, $this->scalarCount('SELECT count(*) AS n FROM overture_v2_chain_memberships WHERE place_id = ?', [$place]));
    }

    public function test_a_run_can_neither_finish_nor_fail_an_attempt_another_run_re_armed(): void
    {
        $this->importSchemaOnly();
        $plan = $this->plan($this->extractFixture());
        $importer = new OvertureV2CorpusImporter();
        $call = fn (string $method, ...$args) => (fn () => $this->{$method}(...$args))->call($importer);

        // Run A marks the attempt; run B re-arms it (what B's markPreparing does after A's rollback).
        $call('markPreparing', $this->db(), $plan, 'run-a');
        $call('markPreparing', $this->db(), $plan, 'run-b');

        // A's late markFailed must not stamp A's failure on B's attempt.
        $call('markFailed', $this->db(), $this->fixtureVersion, 'run-a', new \RuntimeException('run A broke'));
        $row = $this->corpus($this->fixtureVersion);
        $this->assertSame(['preparing', 'run-b', null], [$row->status, $row->import_run, $row->failure_reason]);

        // A may not lock or fill B's attempt either.
        try {
            $this->db()->transaction(fn () => $call('importRows', $this->db(), $plan, $this->fixtureVersion, 'run-a'));
            $this->fail("run A imported into run B's attempt");
        } catch (InvalidOvertureV2Import $e) {
            $this->assertStringContainsString('changed under this import', $e->getMessage());
        }
        $this->assertSame(0, $this->scalarCount('SELECT count(*) AS n FROM overture_v2_places'));

        // B's own failure is recorded against B.
        $call('markFailed', $this->db(), $this->fixtureVersion, 'run-b', new \RuntimeException('run B broke'));
        $row = $this->corpus($this->fixtureVersion);
        $this->assertSame(['failed', 'run-b'], [$row->status, $row->import_run]);
        $this->assertStringContainsString('run B broke', (string) $row->failure_reason);
    }

    public function test_the_importer_refuses_a_target_without_postgis_or_without_the_schema(): void
    {
        $plan = $this->plan($this->extractFixture());

        try {
            $this->import($plan);
            $this->fail('imported into PostgreSQL without PostGIS');
        } catch (InvalidOvertureV2Import $e) {
            $this->assertStringContainsString('without PostGIS', $e->getMessage());
        }

        $this->db()->statement('CREATE EXTENSION postgis');
        try {
            $this->import($plan);
            $this->fail('imported with no v2 tables');
        } catch (InvalidOvertureV2Import $e) {
            $this->assertStringContainsString('does not exist; run the spatial migrations first', $e->getMessage());
        }
    }
}
