<?php

namespace Tests\Unit\Services\LocationDna\Contract;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * G1c — INERTNESS GUARD.
 *
 * The G1c authorization requires the contract core to remain inert: introduced but wired into
 * nothing. This suite is the standing proof. It fails the moment any production file outside
 * the contract namespace references a contract class — which is exactly the signal a future
 * increment (G1f) needs before it starts wiring deliberately.
 *
 * SCOPE OF THE SEARCH, AND WHY IT IS NARROW
 * -----------------------------------------
 * Only real production references count. The scan looks for the namespace token in `app/`,
 * `routes/`, `config/`, `database/` and `resources/views/`, then EXCLUDES the approved Location
 * DNA domain namespaces (below) and strips comment lines before matching, so a harmless mention
 * in a docblock or a `//` note does not fail the build. Tests may reference the classes freely
 * and are not scanned.
 *
 * THE EXEMPTION LIST, AND WHY IT IS EXPLICIT RATHER THAN A WILDCARD
 * ----------------------------------------------------------------
 * As first written, this guard asserted that nothing outside `Contract/` referenced the contract.
 * That encoded a stronger claim than the architecture requires, and G1d exposed the difference:
 * the approved G1d design mandates reuse of the G1c {@see Dimension} vocabulary rather than
 * duplicating dimension names as unvalidated strings, so the capability layer necessarily depends
 * on the contract. A sibling DOMAIN layer consuming the contract is not production wiring.
 *
 * The invariant this guard actually protects is therefore:
 *
 *   nothing outside the approved Location DNA domain namespaces references the contract.
 *
 * `self::DOMAIN_DIRS` is an explicit two-entry list, **not** a wildcard under
 * `app/Services/LocationDna/`. Future domain namespaces — provenance, persistence, a legacy
 * mirror adapter — are deliberately NOT pre-exempted: each must be added here under its own
 * authorisation when it is introduced, so its arrival is a visible decision rather than a silent
 * pass. Everything else still fails: controllers, Livewire components, models, routes, Blade
 * views, traits, existing services and the eight workflows.
 */
class G1cContractCoreInertnessGuardTest extends TestCase
{
    private const NAMESPACE_TOKEN = 'App\\Services\\LocationDna\\Contract';

    private const CONTRACT_DIR = 'app/Services/LocationDna/Contract';

    /**
     * The approved Location DNA domain namespaces that may depend on the contract.
     *
     * Explicit, and deliberately not a wildcard under `app/Services/LocationDna/`. Adding an entry
     * is an authorisation decision; omitting one means that namespace fails this guard.
     */
    private const DOMAIN_DIRS = [
        'app/Services/LocationDna/Contract',    // G1c — the contract core itself
        'app/Services/LocationDna/Capability',  // G1d — reuses the G1c Dimension vocabulary
        'app/Services/LocationDna/Provenance',  // G1e — reuses the G1c Dimension vocabulary
        'app/Services/LocationDna/Persistence', // G1f-1 — the canonical writer applies the contract
    ];

    /** Class basenames that would appear in a `use` statement or a fully-qualified reference. */
    private const CONTRACT_CLASSES = [
        'LocationDnaDocument', 'LocationDnaHydrator', 'LocationDnaNormalizer',
        'LocationDnaSerializer', 'LocationDnaRevisionToken', 'DimensionCommand',
        'DimensionCommandApplier', 'DimensionOperation', 'HydrationResult',
        'HydrationOutcome', 'InterpretationMode', 'LocationDnaContractException',
    ];

    private function projectRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    /** @return list<string> production files, excluding the contract namespace itself */
    private function productionFiles(): array
    {
        $roots = ['app', 'routes', 'config', 'database', 'resources/views'];
        $files = [];

        foreach ($roots as $root) {
            $path = $this->projectRoot().'/'.$root;

            if (! is_dir($path)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($it as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $relative = str_replace($this->projectRoot().'/', '', $file->getPathname());

                // An approved sibling domain namespace may depend on the contract.
                if ($this->isApprovedDomainNamespace($relative)) {
                    continue;
                }

                if (in_array($file->getExtension(), ['php', 'js', 'ts'], true)) {
                    $files[] = $relative;
                }
            }
        }

        sort($files);

        return $files;
    }

    /** True when the path belongs to an approved Location DNA domain namespace. */
    private function isApprovedDomainNamespace(string $relative): bool
    {
        foreach (self::DOMAIN_DIRS as $dir) {
            if (str_starts_with($relative, $dir)) {
                return true;
            }
        }

        return false;
    }

    /** Strip line comments and docblock lines so a prose mention cannot fail the guard. */
    private function codeOnly(string $source): string
    {
        $out = [];

        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')
                || str_starts_with($trimmed, '{{--')) {
                continue;
            }

            $out[] = $line;
        }

        return implode("\n", $out);
    }

    public function test_no_production_file_outside_the_approved_domain_namespaces_references_it(): void
    {
        $offenders = [];

        foreach ($this->productionFiles() as $relative) {
            $code = $this->codeOnly((string) file_get_contents($this->projectRoot().'/'.$relative));

            if (str_contains($code, self::NAMESPACE_TOKEN) || str_contains($code, 'LocationDna\\Contract\\')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'The G1c contract core must remain inert outside the approved Location DNA domain '
            .'namespaces. Production references found in: '.implode(', ', $offenders)
            .'. Wiring is G1f work and requires separate authorization; a new domain namespace '
            .'must be added to self::DOMAIN_DIRS under its own authorisation.',
        );
    }

    public function test_the_eight_workflow_components_and_the_trait_do_not_reference_the_core(): void
    {
        $targets = [
            'app/Http/Livewire/Concerns/HasSearchAreas.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        ];

        foreach ($targets as $relative) {
            $full = $this->projectRoot().'/'.$relative;
            $this->assertFileExists($full, "{$relative} must still exist and be untouched by G1c");

            $this->assertStringNotContainsString(
                'LocationDna\\Contract',
                $this->codeOnly((string) file_get_contents($full)),
                "{$relative} must not be wired to the contract core in G1c",
            );
        }
    }

    public function test_the_classes_deliberately_not_created_in_this_increment_do_not_exist(): void
    {
        // D-G1-5 and the G1c authorization: the persistence service and the legacy mirror adapter
        // are explicitly out of scope, and the capability resolver is G1d.
        foreach ([
            'LocationDnaPersistenceService',
            'LegacyMirrorAdapter',
            'LocationDnaCapabilityResolver',
        ] as $class) {
            $this->assertFalse(
                class_exists(self::NAMESPACE_TOKEN.'\\'.$class),
                "{$class} must NOT be created in the G1c contract-core increment",
            );
        }
    }

    public function test_the_contract_core_does_not_reference_the_framework_or_persistence(): void
    {
        $forbidden = [
            'Illuminate\\', 'Livewire', 'Eloquent', 'DB::', 'Model', 'Request',
            'saveMeta', 'location_dna_preferences_json', 'PublicGeometryProjection',
            'CriteriaHashService',
        ];

        /** @var iterable<\SplFileInfo> $it */
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectRoot().'/'.self::CONTRACT_DIR),
        );

        foreach ($it as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = $this->codeOnly((string) file_get_contents($file->getPathname()));

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    $file->getBasename().' must not depend on '.$needle
                    .' — the domain core is framework- and persistence-free (§6).',
                );
            }
        }
    }

    public function test_every_contract_class_is_autoloadable(): void
    {
        foreach (self::CONTRACT_CLASSES as $class) {
            $fqcn = self::NAMESPACE_TOKEN.'\\'.$class;

            $this->assertTrue(
                class_exists($fqcn) || enum_exists($fqcn),
                "{$fqcn} must be autoloadable under the chosen namespace",
            );
        }
    }
}
