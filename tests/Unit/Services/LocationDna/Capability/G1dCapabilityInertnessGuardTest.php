<?php

namespace Tests\Unit\Services\LocationDna\Capability;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * G1d — INERTNESS GUARD.
 *
 * The capability resolver is introduced and wired into nothing. This suite is the standing proof,
 * and it fails the moment any production file outside the capability namespace references it —
 * which is the signal a deliberate wiring increment (G1f/G1g) needs before it starts.
 *
 * Comment lines are stripped before matching, so a prose mention in a docblock cannot fail the
 * build; only real production references count. Tests are not scanned.
 */
class G1dCapabilityInertnessGuardTest extends TestCase
{
    private const NAMESPACE_TOKEN = 'App\\Services\\LocationDna\\Capability';

    private const CAPABILITY_DIR = 'app/Services/LocationDna/Capability';

    private const CONTRACT_DIR = 'app/Services/LocationDna/Contract';

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

    /** @return list<string> production files outside the capability namespace */
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

                if (str_starts_with($rel, self::CAPABILITY_DIR)) {
                    continue;
                }

                $files[] = $rel;
            }
        }

        sort($files);

        return $files;
    }

    public function test_no_production_file_outside_the_capability_namespace_references_it(): void
    {
        $offenders = [];

        foreach ($this->productionFiles() as $rel) {
            $code = $this->codeOnly((string) file_get_contents($this->root().'/'.$rel));

            if (str_contains($code, self::NAMESPACE_TOKEN) || str_contains($code, 'LocationDna\\Capability\\')) {
                $offenders[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'The G1d capability resolver must remain inert. References found in: '.implode(', ', $offenders),
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
                'LocationDna\\Capability',
                $this->codeOnly((string) file_get_contents($full)),
                "{$rel} must not be wired to the capability resolver",
            );
        }
    }

    public function test_no_controller_model_route_or_view_references_it(): void
    {
        foreach (['app/Http/Controllers', 'app/Models', 'routes', 'resources/views'] as $dir) {
            $path = $this->root().'/'.$dir;

            if (! is_dir($path)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($it as $file) {
                if ($file->isDir() || ! in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'LocationDna\\Capability',
                    $this->codeOnly((string) file_get_contents($file->getPathname())),
                    $file->getPathname().' must not reference the capability namespace',
                );
            }
        }
    }

    public function test_the_capability_namespace_is_framework_and_persistence_free(): void
    {
        foreach (['Illuminate\\', 'Livewire', 'Eloquent', 'DB::', 'Request', 'Auth::', 'saveMeta',
                  'Gate', 'Policy', 'Middleware', 'ServiceProvider', 'config('] as $needle) {
            foreach (glob($this->root().'/'.self::CAPABILITY_DIR.'/*.php') ?: [] as $file) {
                $this->assertStringNotContainsString(
                    $needle,
                    $this->codeOnly((string) file_get_contents($file)),
                    basename($file)." must not depend on {$needle}",
                );
            }
        }
    }

    public function test_no_policy_middleware_gate_or_service_provider_was_added(): void
    {
        foreach (glob($this->root().'/'.self::CAPABILITY_DIR.'/*.php') ?: [] as $file) {
            $base = basename($file);

            foreach (['Policy', 'Middleware', 'Gate', 'ServiceProvider'] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $base,
                    "G1d must not add a {$forbidden}",
                );
            }
        }

        // And no config file was introduced for capability declaration in this increment.
        $this->assertFileDoesNotExist($this->root().'/config/location_dna_capability.php');
    }

    public function test_no_persistence_service_mirror_adapter_or_snapshot_reader_exists(): void
    {
        foreach ([
            'App\\Services\\LocationDna\\Contract\\LocationDnaPersistenceService',
            'App\\Services\\LocationDna\\Contract\\LegacyMirrorAdapter',
            'App\\Services\\LocationDna\\Capability\\LocationDnaPersistenceService',
            'App\\Services\\LocationDna\\Capability\\LegacyMirrorAdapter',
            'App\\Services\\LocationDna\\Capability\\SnapshotReader',
        ] as $fqcn) {
            $this->assertFalse(class_exists($fqcn), "{$fqcn} must not exist");
        }

        // No file in either namespace mentions the retention column at all.
        foreach (array_merge(
            glob($this->root().'/'.self::CAPABILITY_DIR.'/*.php') ?: [],
            glob($this->root().'/'.self::CONTRACT_DIR.'/*.php') ?: [],
        ) as $file) {
            $this->assertStringNotContainsString(
                'location_intelligence_snapshot',
                $this->codeOnly((string) file_get_contents($file)),
                basename($file).' must not touch the retained snapshot column',
            );
        }
    }

    public function test_public_geometry_projection_and_criteria_hash_service_are_unreferenced_and_unchanged(): void
    {
        // Neither is imported or named in code by the capability namespace.
        foreach (glob($this->root().'/'.self::CAPABILITY_DIR.'/*.php') ?: [] as $file) {
            $code = $this->codeOnly((string) file_get_contents($file));

            $this->assertStringNotContainsString('PublicGeometryProjection', $code);
            $this->assertStringNotContainsString('CriteriaHashService', $code);
        }

        // And both still exist untouched where G0.1 / the Bridge left them.
        $this->assertFileExists($this->root().'/app/Services/LocationDna/PublicGeometryProjection.php');
        $this->assertFileExists($this->root().'/app/Services/Bridge/CriteriaHashService.php');
    }

    public function test_the_capability_namespace_only_depends_on_the_g1c_contract(): void
    {
        $allowed = [
            'App\\Services\\LocationDna\\Contract\\Dimension',
            'RuntimeException',
        ];

        foreach (glob($this->root().'/'.self::CAPABILITY_DIR.'/*.php') ?: [] as $file) {
            preg_match_all('/^use\s+([^;]+);/m', (string) file_get_contents($file), $m);

            foreach ($m[1] ?? [] as $import) {
                $this->assertContains(
                    trim($import),
                    $allowed,
                    basename($file)." imports {$import}, which is outside the permitted dependency set",
                );
            }
        }
    }

    public function test_every_capability_class_is_autoloadable(): void
    {
        foreach ([
            'LocationDnaCapability', 'LocationDnaCapabilitySet', 'LocationDnaAccessContext',
            'LocationDnaSurface', 'LocationDnaViewerRelationship', 'LocationDnaPurpose',
            'LocationDnaCapabilityResolver', 'LocationDnaCapabilityException',
        ] as $class) {
            $fqcn = self::NAMESPACE_TOKEN.'\\'.$class;

            $this->assertTrue(
                class_exists($fqcn) || enum_exists($fqcn),
                "{$fqcn} must be autoloadable",
            );
        }
    }
}
