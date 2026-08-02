<?php

namespace Tests\Unit\Services\LocationDna\Provenance;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * G1e — INERTNESS GUARD.
 *
 * The provenance model is defined and wired into nothing. This suite is the standing proof and fails
 * the moment any production file outside the provenance namespace references it.
 *
 * Comment lines are stripped before matching, so prose in a docblock cannot fail the build; only real
 * production references count. Tests are not scanned.
 */
class G1eProvenanceInertnessGuardTest extends TestCase
{
    private const NAMESPACE_TOKEN = 'App\\Services\\LocationDna\\Provenance';

    private const PROVENANCE_DIR = 'app/Services/LocationDna/Provenance';

    /**
     * G1f-1's canonical writer — the one approved production consumer.
     *
     * It uses provenance ONLY to validate a transition, transiently. It persists none, and
     * test_no_provenance_is_persisted_by_the_canonical_writer in the G1f-1 suite asserts that
     * separately, so this exemption widens the reference scan and not the storage rule.
     */
    private const PERSISTENCE_DIR = 'app/Services/LocationDna/Persistence';

    private function root(): string
    {
        return dirname(__DIR__, 5);
    }

    private function codeOnly(string $source): string
    {
        $out = [];

        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $t = ltrim($line);

            if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*')
                || str_starts_with($t, '/*') || str_starts_with($t, '#') || str_starts_with($t, '{{--')) {
                continue;
            }

            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /** @return list<string> */
    private function productionFiles(): array
    {
        $files = [];

        foreach (['app', 'routes', 'config', 'database', 'resources/views'] as $root) {
            $path = $this->root().'/'.$root;

            if (! is_dir($path)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($it as $file) {
                if ($file->isDir() || ! in_array($file->getExtension(), ['php', 'js', 'ts'], true)) {
                    continue;
                }

                $rel = str_replace($this->root().'/', '', $file->getPathname());

                // ONE explicit directory, not a wildcard — a future namespace must be added
                // here under its own authorisation.
                if (str_starts_with($rel, self::PROVENANCE_DIR) || str_starts_with($rel, self::PERSISTENCE_DIR)) {
                    continue;
                }

                $files[] = $rel;
            }
        }

        sort($files);

        return $files;
    }

    public function test_no_production_file_outside_the_provenance_namespace_references_it(): void
    {
        $offenders = [];

        foreach ($this->productionFiles() as $rel) {
            $code = $this->codeOnly((string) file_get_contents($this->root().'/'.$rel));

            if (str_contains($code, self::NAMESPACE_TOKEN) || str_contains($code, 'LocationDna\\Provenance\\')) {
                $offenders[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'The G1e provenance model must remain inert. References found in: '.implode(', ', $offenders),
        );
    }

    public function test_the_eight_workflows_and_the_trait_do_not_reference_it(): void
    {
        foreach ([
            'app/Http/Livewire/Concerns/HasSearchAreas.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        ] as $rel) {
            $full = $this->root().'/'.$rel;
            $this->assertFileExists($full);
            $this->assertStringNotContainsString(
                'LocationDna\\Provenance',
                $this->codeOnly((string) file_get_contents($full)),
                "{$rel} must not be wired to the provenance model",
            );
        }
    }

    public function test_no_controller_model_route_view_or_migration_references_it(): void
    {
        foreach (['app/Http/Controllers', 'app/Models', 'routes', 'resources/views', 'database'] as $dir) {
            $path = $this->root().'/'.$dir;

            if (! is_dir($path)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($it as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'LocationDna\\Provenance',
                    $this->codeOnly((string) file_get_contents($file->getPathname())),
                    $file->getPathname().' must not reference the provenance namespace',
                );
            }
        }
    }

    public function test_the_provenance_namespace_is_framework_and_persistence_free(): void
    {
        foreach (['Illuminate\\', 'Livewire', 'Eloquent', 'DB::', 'Request', 'Auth::', 'saveMeta',
                  'Gate', 'Policy', 'Middleware', 'ServiceProvider', 'config(', 'Schema::',
                  'Observer', 'Event::', 'dispatch('] as $needle) {
            foreach (glob($this->root().'/'.self::PROVENANCE_DIR.'/*.php') ?: [] as $file) {
                $this->assertStringNotContainsString(
                    $needle,
                    $this->codeOnly((string) file_get_contents($file)),
                    basename($file)." must not depend on {$needle}",
                );
            }
        }
    }

    public function test_it_depends_only_on_the_g1c_dimension_and_native_php(): void
    {
        foreach (glob($this->root().'/'.self::PROVENANCE_DIR.'/*.php') ?: [] as $file) {
            preg_match_all('/^use\s+([^;]+);/m', (string) file_get_contents($file), $m);

            foreach ($m[1] ?? [] as $import) {
                $this->assertContains(
                    trim($import),
                    ['App\\Services\\LocationDna\\Contract\\Dimension', 'RuntimeException'],
                    basename($file)." imports {$import}, outside the permitted set",
                );
            }
        }
    }

    public function test_the_untouchable_g1c_and_g0_1_files_are_unchanged_and_unreferenced(): void
    {
        // Present where earlier gates left them …
        foreach ([
            'app/Services/LocationDna/PublicGeometryProjection.php',
            'app/Services/Bridge/CriteriaHashService.php',
            'app/Services/LocationDna/Contract/LocationDnaRevisionToken.php',
        ] as $rel) {
            $this->assertFileExists($this->root().'/'.$rel);
        }

        // … and never named in provenance code.
        foreach (glob($this->root().'/'.self::PROVENANCE_DIR.'/*.php') ?: [] as $file) {
            $code = $this->codeOnly((string) file_get_contents($file));

            $this->assertStringNotContainsString('PublicGeometryProjection', $code);
            $this->assertStringNotContainsString('CriteriaHashService', $code);
            $this->assertStringNotContainsString('LocationDnaRevisionToken', $code);
        }
    }

    public function test_nothing_forbidden_was_created(): void
    {
        foreach ([
            'LocationDnaPersistenceService', 'LegacyMirrorAdapter', 'WorkflowAdapter',
            'ProvenanceRepository', 'ProvenanceObserver', 'ProvenancePolicy',
            'ProvenanceServiceProvider', 'ProvenanceMiddleware',
        ] as $class) {
            $this->assertFileDoesNotExist(
                $this->root().'/'.self::PROVENANCE_DIR.'/'.$class.'.php',
                "{$class} must not be created in G1e",
            );
            $this->assertFalse(class_exists(self::NAMESPACE_TOKEN.'\\'.$class), $class);
        }

        // No provenance configuration file.
        $this->assertFileDoesNotExist($this->root().'/config/location_dna_provenance.php');

        // No migration may reference the G1e namespace.
        //
        // Asserted on the namespace rather than on the word "provenance" in a filename: two
        // migrations dated 2026-07-05 already carry that word
        // (add_provenance_columns_to_property_location_pois_table,
        // add_provenance_columns_to_dna_scores_table). They belong to the ADJACENT POI and
        // dna_scores provenance work that §10.1 records as already existing, predate every G1
        // increment, and are untouched by this branch. A filename heuristic would flag them
        // falsely; the namespace check is what actually detects a G1e-driven migration.
        foreach (glob($this->root().'/database/migrations/*.php') ?: [] as $migration) {
            $this->assertStringNotContainsString(
                'LocationDna\\Provenance',
                (string) file_get_contents($migration),
                basename($migration).' must not reference the G1e provenance namespace',
            );
        }
    }

    public function test_no_snapshot_reader_exists_and_the_column_is_untouched(): void
    {
        foreach (glob($this->root().'/'.self::PROVENANCE_DIR.'/*.php') ?: [] as $file) {
            // The SnapshotRetained kind names the snapshot conceptually; it must not name the column,
            // which is what the G1b column-reference guard protects.
            $this->assertStringNotContainsString(
                'location_intelligence_snapshot',
                (string) file_get_contents($file),
                basename($file).' must not name the retained-snapshot column',
            );
        }
    }

    public function test_every_provenance_class_is_autoloadable(): void
    {
        foreach ([
            'LocationDnaProvenanceKind', 'ProvenanceAuthority', 'ProvenanceActor',
            'DimensionProvenance', 'LocationDnaProvenanceMap', 'ProvenanceTransition',
            'LocationDnaProvenanceException',
        ] as $class) {
            $fqcn = self::NAMESPACE_TOKEN.'\\'.$class;

            $this->assertTrue(class_exists($fqcn) || enum_exists($fqcn), "{$fqcn} must be autoloadable");
        }
    }
}
