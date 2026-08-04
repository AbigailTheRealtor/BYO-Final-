<?php

namespace Tests\Unit\Services\LocationDna\Criteria;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Phase 1a — the inertness guard for `App\Services\LocationDna\Criteria`.
 *
 * WHAT IT PROTECTS
 * ----------------
 * Phase 1a authorized a READ-ONLY foundation and nothing else. The value of an inert increment is
 * entirely in the inertness, and inertness that is only in a commit message is not inertness. This
 * suite is the standing proof that the new namespace cannot change a stored byte, is wired into no
 * surface, and left every G1f boundary exactly where the closeout put it.
 *
 * It is the direct descendant of `G1cContractCoreInertnessGuardTest`, which guarded the contract
 * core the same way before G1f wired it in.
 *
 * THE SIX BOUNDARIES
 * ------------------
 *   1. The namespace contains no write of any kind.
 *   2. Nothing in production references it — no controller, view, Livewire component, route,
 *      model, migration or provider.
 *   3. The criteria controllers' write paths are untouched.
 *   4. The persistence boundary, the projection and the trait are untouched.
 *   5. The §21 direct-writer list is still exactly the two legacy criteria controllers.
 *   6. The feature flag ships OFF, and no new migration was added.
 */
class Phase1aCriteriaInertnessGuardTest extends TestCase
{
    private const NAMESPACE_DIR = 'app/Services/LocationDna/Criteria';

    private const CANONICAL_WRITE = "saveMeta('location_dna_preferences'";

    /** The §21 direct-writer list as the HasSearchAreas closeout left it. */
    private const AUTHORIZED_WRITERS = [
        'app/Http/Controllers/BuyerCriteriaAuctionController.php',
        'app/Http/Controllers/TenantCriteriaAuctionController.php',
    ];

    private function root(): string
    {
        return dirname(__DIR__, 5);
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents($this->root().'/'.$relative);
    }

    /** Source with comments and docblocks stripped, so prose can never satisfy an assertion. */
    private function codeOnly(string $source): string
    {
        $out = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $out .= $token[1];
                continue;
            }

            $out .= $token;
        }

        return $out;
    }

    /** @return list<string> */
    private function filesUnder(array $roots, string $extension = 'php'): array
    {
        $files = [];

        foreach ($roots as $dir) {
            $base = $this->root().'/'.$dir;

            if (! is_dir($base)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === $extension) {
                    $files[] = ltrim(str_replace($this->root(), '', $file->getPathname()), '/');
                }
            }
        }

        sort($files);

        return $files;
    }

    /** @return list<string> the files of the new namespace */
    private function namespaceFiles(): array
    {
        return $this->filesUnder([self::NAMESPACE_DIR]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE NAMESPACE CANNOT WRITE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * No persistence call of any kind appears in the new namespace.
     *
     * The whole safety argument for Phase 1a is "it cannot change stored data". This is that
     * argument, mechanised. `saveMeta` is the EAV write; `->save(`, `create(`, `update(`,
     * `insert(`, `delete(` cover Eloquent and the query builder.
     */
    public function test_the_namespace_contains_no_write_of_any_kind(): void
    {
        $files = $this->namespaceFiles();

        $this->assertNotEmpty($files, 'the namespace must exist and contain files');

        foreach ($files as $relative) {
            $code = $this->codeOnly($this->read($relative));

            foreach ([
                'saveMeta(',
                '->save(',
                '->create(',
                '->update(',
                '->insert(',
                '->delete(',
                '->forceFill(',
                'DB::statement(',
            ] as $write) {
                $this->assertStringNotContainsString(
                    $write,
                    $code,
                    "{$relative} contains `{$write}` — Phase 1a is read-only by construction."
                );
            }
        }
    }

    /** It reaches no persistence, contract or capability layer either. */
    public function test_the_namespace_touches_no_location_dna_write_layer(): void
    {
        foreach ($this->namespaceFiles() as $relative) {
            $code = $this->codeOnly($this->read($relative));

            foreach ([
                'LocationDna\\Persistence',
                'LocationDna\\Contract',
                'LocationDna\\Capability',
                'LocationDna\\Provenance',
                'OwnerPrivateLocationDnaWriter',
                'LegacyMirrorProjection',
                'HasSearchAreas',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $code,
                    "{$relative} references `{$forbidden}` — Phase 1a is a read layer and must not "
                    .'reach the write architecture.'
                );
            }
        }
    }

    /** And it introduces no Google dependency — the approved direction is OSM/MapLibre. */
    public function test_the_namespace_introduces_no_google_dependency(): void
    {
        foreach ($this->namespaceFiles() as $relative) {
            $this->assertStringNotContainsStringIgnoringCase(
                'google',
                $this->read($relative),
                "{$relative} references Google — the approved direction is OSM/MapLibre."
            );
        }
    }

    /**
     * It uses no `ILIKE`, so it runs on the test connection.
     *
     * `ILIKE` is Postgres-only and SQLite rejects it outright — it is already the cause of a
     * pre-existing failure elsewhere in the suite. The reference models' own `search()` helpers
     * all use it, which is exactly why this namespace must not call them.
     */
    public function test_the_namespace_is_portable_to_the_test_connection(): void
    {
        foreach ($this->namespaceFiles() as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringNotContainsStringIgnoringCase(
                'ilike',
                $code,
                "{$relative} uses ILIKE, which SQLite rejects."
            );
            $this->assertStringNotContainsString(
                '::search(',
                $code,
                "{$relative} calls a reference model's search() helper, which uses ILIKE."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · NOTHING REFERENCES IT
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * No production file outside the namespace references it.
     *
     * Phase 1a adds a foundation, not a feature. A reference from a controller, view, Livewire
     * component, route or provider would mean a surface was wired in the same increment that
     * created the thing it wires — which is what the phase split exists to prevent.
     */
    public function test_no_production_file_references_the_namespace(): void
    {
        $offenders = [];

        foreach ($this->filesUnder(['app', 'routes', 'database', 'config']) as $relative) {
            if (str_starts_with($relative, self::NAMESPACE_DIR)) {
                continue;
            }

            if (str_contains($this->codeOnly($this->read($relative)), 'LocationDna\\Criteria')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Phase 1a must be referenced by nothing. Wiring it is Phase 1c. Found: '
            .implode(', ', $offenders)
        );
    }

    /** No Blade view references it either. */
    public function test_no_view_references_the_namespace(): void
    {
        $offenders = [];

        foreach ($this->filesUnder(['resources/views'], 'php') as $relative) {
            $source = $this->read($relative);

            if (str_contains($source, 'LocationDna\\Criteria')
                || str_contains($source, 'criteria-geography')
                || str_contains($source, 'criteria_location_dna')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'No view may reference the Phase 1a namespace or its flag. Found: '
            .implode(', ', $offenders)
        );
    }

    /** No Livewire component was added for it. */
    public function test_no_livewire_component_was_added(): void
    {
        $this->assertDirectoryDoesNotExist(
            $this->root().'/app/Http/Livewire/Criteria',
            'The Livewire preview is Phase 1c, not Phase 1a.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE CRITERIA CONTROLLERS ARE UNTOUCHED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Both criteria controllers still carry their original write paths, verbatim.
     *
     * Asserted POSITIVELY rather than as an absence: a change that removed a write while adding
     * nothing would satisfy every "does not reference" assertion above and still be a behaviour
     * change Phase 1a was not authorized to make.
     */
    public function test_the_criteria_controllers_write_paths_are_unchanged(): void
    {
        $buyer  = $this->read('app/Http/Controllers/BuyerCriteriaAuctionController.php');
        $tenant = $this->read('app/Http/Controllers/TenantCriteriaAuctionController.php');

        // The canonical guard shape, still present on all four write sites.
        $this->assertSame(
            2,
            substr_count($buyer, self::CANONICAL_WRITE),
            'Buyer must still carry its two canonical writes (W6, W7).'
        );
        $this->assertSame(
            2,
            substr_count($tenant, self::CANONICAL_WRITE),
            'Tenant must still carry its two canonical writes (W8, W9).'
        );

        // The discrete mirror writes, still present and still request-sourced.
        $this->assertStringContainsString(
            '$auction->saveMeta("states", json_encode($request->states));',
            $buyer,
            'Buyer still writes the plural states mirror — migrating its readers is Phase 2.'
        );
        $this->assertStringContainsString(
            '$auction->saveMeta("state",json_encode($request->state));',
            $tenant,
            'Tenant still double-encodes its state mirror — normalizing it is Phase 3.'
        );

        // Neither controller has been wired to the canonical writer.
        foreach (['BuyerCriteriaAuctionController', 'TenantCriteriaAuctionController'] as $name) {
            $code = $this->codeOnly($this->read("app/Http/Controllers/{$name}.php"));

            $this->assertStringNotContainsString(
                'persistLocationDna',
                $code,
                "{$name} must NOT reach the canonical writer — that is Phase 2."
            );
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $code,
                "{$name} must not reference the persistence namespace yet."
            );
        }
    }

    /** The four criteria forms are unchanged — no selector, no flag, no widget swap. */
    public function test_the_criteria_forms_are_unchanged(): void
    {
        foreach ([
            'resources/views/buyer_criteria/add.blade.php',
            'resources/views/buyer_criteria/edit.blade.php',
            'resources/views/tenant_criteria/add.blade.php',
            'resources/views/tenant_criteria/edit.blade.php',
        ] as $relative) {
            $source = $this->read($relative);

            $this->assertStringContainsString(
                "@include('partials.location-dna.map-input'",
                $source,
                "{$relative} must still include the existing widget — swapping it is Phase 4."
            );
            $this->assertStringNotContainsString(
                'maplibre',
                strtolower($source),
                "{$relative} must not reference MapLibre yet."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · THE G1f BOUNDARIES ARE WHERE THE CLOSEOUT LEFT THEM
    // ═════════════════════════════════════════════════════════════════════════

    /** The §21 direct-writer list is still exactly the two legacy criteria controllers. */
    public function test_the_direct_writer_list_is_unchanged_at_two(): void
    {
        $offenders = [];

        foreach ($this->filesUnder(['app', 'routes', 'database', 'config']) as $relative) {
            if (in_array($relative, self::AUTHORIZED_WRITERS, true)
                || str_starts_with($relative, 'app/Services/LocationDna/')) {
                continue;
            }

            if (str_contains($this->codeOnly($this->read($relative)), self::CANONICAL_WRITE)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A canonical write appeared outside the authorized set: '.implode(', ', $offenders)
        );
        $this->assertCount(
            2,
            self::AUTHORIZED_WRITERS,
            'Two direct writers remain after the closeout. Phase 1a does not change that.'
        );
    }

    /** `HasSearchAreas` is still load-only, as the closeout left it. */
    public function test_the_trait_is_still_load_only(): void
    {
        $trait = $this->read('app/Http/Livewire/Concerns/HasSearchAreas.php');

        $this->assertStringNotContainsString(
            'function saveSearchAreas',
            $trait,
            'the retired save side must not return'
        );
        $this->assertStringContainsString('function loadSearchAreas', $trait);
        $this->assertStringNotContainsString(
            'saveMeta(',
            $this->codeOnly($trait),
            'the trait must still write nothing'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · THE FLAG SHIPS OFF, AND NO SCHEMA CHANGED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The feature flag defaults OFF, read from the config file rather than the runtime value.
     *
     * Reading the file directly is what makes this fail if the shipped default is ever flipped,
     * regardless of what the environment happens to hold — the technique
     * `MaplibreProofRouteTest` established for the basemap flag.
     */
    public function test_the_feature_flag_ships_disabled(): void
    {
        $path = $this->root().'/config/criteria_location_dna.php';

        $this->assertFileExists($path);

        $config = require $path;

        $this->assertFalse(
            $config['geography_preview_enabled'],
            'The Criteria geography preview must ship disabled by default.'
        );
        $this->assertSame(
            'eloquent',
            $config['geography_source'],
            'The default source is the real reference tables.'
        );
    }

    /** Phase 1a added no migration. */
    public function test_no_migration_was_added(): void
    {
        $offenders = [];

        foreach ($this->filesUnder(['database/migrations']) as $relative) {
            $source = $this->read($relative);

            if (str_contains($source, 'criteria_location_dna')
                || str_contains($source, 'LocationDna\\Criteria')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Phase 1a required no schema change.');
    }

    /** Every class in the namespace is autoloadable and the interface is satisfied. */
    public function test_the_namespace_is_coherent(): void
    {
        foreach ([
            \App\Services\LocationDna\Criteria\GeographyOption::class,
            \App\Services\LocationDna\Criteria\CriteriaGeographyRepository::class,
            \App\Services\LocationDna\Criteria\EloquentCriteriaGeographyRepository::class,
            \App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository::class,
        ] as $class) {
            $this->assertTrue(
                class_exists($class) || interface_exists($class),
                "{$class} must be autoloadable"
            );
        }

        foreach ([
            \App\Services\LocationDna\Criteria\EloquentCriteriaGeographyRepository::class,
            \App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository::class,
        ] as $implementation) {
            $this->assertContains(
                \App\Services\LocationDna\Criteria\CriteriaGeographyRepository::class,
                class_implements($implementation),
                "{$implementation} must satisfy the repository contract"
            );
        }
    }
}
