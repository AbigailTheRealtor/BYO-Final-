<?php

namespace Tests\Unit\Services\LocationDna\Persistence;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * G1f-1 — the migration boundary guard.
 *
 * WHAT IT PROTECTS
 * ----------------
 * G1f-1 authorized migrating exactly ONE workflow. The value of "one at a time" is entirely in
 * the boundary holding, and a boundary that is only in a commit message is not a boundary. This
 * suite is the standing proof that the other seven canonical writers, the trait, and every
 * deferred concern are untouched.
 *
 * It is the §21 direct-writer guard in its G1f-1 form: `AUTHORIZED_WRITERS` shrinks as workflows
 * migrate, and it reaching one entry is G1f's completion condition. Adding an entry is a
 * regression; the list may only get shorter.
 */
class G1f1MigrationBoundaryGuardTest extends TestCase
{
    private const CANONICAL_WRITE = "saveMeta('location_dna_preferences'";

    /**
     * Files still permitted to write the canonical key directly.
     *
     * SHRINK-ONLY. `BuyerAgentAuction` is absent because G1f-1 migrated it. Every remaining entry
     * is a workflow G1f-2 and later will remove. When only the persistence namespace remains,
     * D-G1-5's "sole canonical writer" is true and G1f can be declared complete.
     */
    private const AUTHORIZED_WRITERS = [
        'app/Http/Livewire/Concerns/HasSearchAreas.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        'app/Http/Controllers/BuyerCriteriaAuctionController.php',
        'app/Http/Controllers/TenantCriteriaAuctionController.php',
    ];

    /**
     * The workflow implementations that must stay completely alone.
     *
     * SHRINK-ONLY, like `AUTHORIZED_WRITERS`. `TenantAgentAuction` left this list at G1f-2 and
     * both Buyer Offer copies left it at G1f-3; their post-migration boundaries are asserted by
     * `G1f2MigrationBoundaryGuardTest` and `G1f3MigrationBoundaryGuardTest`. Four remain.
     */
    private const UNMIGRATED_WORKFLOWS = [
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
        'app/Http/Livewire/TenantAgentAuctionEdit.php',
    ];

    private function root(): string
    {
        return dirname(__DIR__, 5);
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents($this->root().'/'.$relative);
    }

    /** Strip comment lines so a docblock mention never fails a scan. */
    private function codeOnly(string $source): string
    {
        $out = [];

        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
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

        foreach (['app', 'routes', 'database'] as $root) {
            $path = $this->root().'/'.$root;

            if (! is_dir($path)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($it as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $files[] = str_replace($this->root().'/', '', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ONE WORKFLOW MIGRATED
    // ═════════════════════════════════════════════════════════════════════════

    /** `BuyerAgentAuction` writes through the seam and no longer writes the canonical key itself. */
    public function test_buyer_agent_auction_is_migrated(): void
    {
        $source = $this->read('app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php');

        $this->assertStringContainsString('OwnerPrivateLocationDnaWriter', $source);
        $this->assertStringContainsString('$this->persistLocationDna($auction);', $source);
        $this->assertStringNotContainsString(self::CANONICAL_WRITE, $this->codeOnly($source));
        $this->assertStringNotContainsString('$this->saveSearchAreas($auction);', $this->codeOnly($source));
    }

    /**
     * Only the authorized writers write the canonical key directly — and the list shrank by one.
     *
     * The §21 guard. If a new direct writer appears anywhere, this fails.
     */
    public function test_only_the_authorized_writers_write_the_canonical_key(): void
    {
        $offenders = [];

        foreach ($this->productionFiles() as $relative) {
            if (in_array($relative, self::AUTHORIZED_WRITERS, true)) {
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
            5,
            self::AUTHORIZED_WRITERS,
            'Five direct writers remain after G1f-3 — the first stage to shorten this list, because '
            .'the two Buyer Offer copies wrote the canonical key inline rather than through the '
            .'trait. This list may only SHRINK; reaching one entry is G1f\'s completion condition.'
        );
    }

    /** The remaining unmigrated workflows still write their mirrors the old way. */
    public function test_the_seven_unmigrated_workflows_are_untouched(): void
    {
        foreach (self::UNMIGRATED_WORKFLOWS as $relative) {
            $source = $this->codeOnly($this->read($relative));

            $this->assertStringNotContainsString(
                'OwnerPrivateLocationDnaWriter',
                $source,
                "{$relative} must NOT be wired to the canonical writer — that is G1f-2 and later."
            );
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $source,
                "{$relative} must not reference the persistence namespace."
            );
        }
    }

    /** The trait is not globally rewired; the remaining hosts still use it unchanged. */
    public function test_has_search_areas_is_not_globally_wired(): void
    {
        $trait = $this->read('app/Http/Livewire/Concerns/HasSearchAreas.php');

        $this->assertStringNotContainsString('LocationDna\\Persistence', $this->codeOnly($trait));
        $this->assertStringContainsString(
            "empty(\$ldna['cities'] ?? [])",
            $trait,
            'the trait keeps its pre-consolidation semantics for the unmigrated hosts'
        );
        $this->assertStringContainsString(
            "\$auction->saveMeta('location_dna_preferences', \$this->location_dna_preferences_json);",
            $trait,
            'and still performs its own canonical write'
        );

        foreach ([
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $host) {
            $this->assertStringContainsString(
                '$this->saveSearchAreas($auction);',
                $this->read($host),
                "{$host} must still call the trait save."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DEFERRED CONCERNS · still deferred
    // ═════════════════════════════════════════════════════════════════════════

    /** D-G1F-2 · `LegacyMirrorAdapter` remains uncreated in every namespace. */
    public function test_legacy_mirror_adapter_does_not_exist(): void
    {
        foreach ([
            'App\\Services\\LocationDna\\Contract\\LegacyMirrorAdapter',
            'App\\Services\\LocationDna\\Capability\\LegacyMirrorAdapter',
            'App\\Services\\LocationDna\\Provenance\\LegacyMirrorAdapter',
            'App\\Services\\LocationDna\\Persistence\\LegacyMirrorAdapter',
        ] as $fqcn) {
            $this->assertFalse(class_exists($fqcn), "{$fqcn} must not exist — read/fallback/repair is deferred");
        }

        $this->assertFileDoesNotExist($this->root().'/app/Services/LocationDna/Persistence/LegacyMirrorAdapter.php');
    }

    /** The persistence namespace touches none of the deferred seams. */
    public function test_the_persistence_namespace_touches_no_deferred_seam(): void
    {
        foreach (glob($this->root().'/app/Services/LocationDna/Persistence/*.php') ?: [] as $file) {
            $code = $this->codeOnly((string) file_get_contents($file));

            foreach ([
                'CriteriaHashService',
                'PublicGeometryProjection',
                'location_intelligence_snapshot',
                'AcceptedBidSummary',
                'Bridge\\',
                'summary_json',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $code,
                    basename($file)." must not reference `{$forbidden}` — it is deferred or out of scope."
                );
            }
        }
    }

    /** No provenance is persisted: no column, no table, no migration, no cast. */
    public function test_no_provenance_is_persisted_anywhere(): void
    {
        foreach (glob($this->root().'/app/Services/LocationDna/Persistence/*.php') ?: [] as $file) {
            $code = $this->codeOnly((string) file_get_contents($file));

            $this->assertStringNotContainsString(
                'saveMeta(\'provenance',
                $code,
                basename($file).' must not write provenance'
            );
            $this->assertStringNotContainsString(
                'LocationDnaProvenanceMap',
                $code,
                basename($file).' must not persist a provenance map — the actor is transient only'
            );
        }
    }

    /** G1f-1 introduced no migration and no schema change. */
    public function test_no_migration_references_the_persistence_namespace(): void
    {
        foreach (glob($this->root().'/database/migrations/*.php') ?: [] as $file) {
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                (string) file_get_contents($file),
                basename($file).' must not reference the G1f-1 namespace'
            );
        }

        $this->assertFileDoesNotExist($this->root().'/config/location_dna_persistence.php');
    }

    /** No controller, model, route or view was wired to the new boundary. */
    public function test_no_controller_model_route_or_view_was_wired(): void
    {
        $offenders = [];

        foreach ($this->productionFiles() as $relative) {
            if (str_starts_with($relative, 'app/Services/LocationDna/Persistence')
                || $relative === 'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php'
                || $relative === 'app/Http/Livewire/TenantAgentAuction.php'
                || $relative === 'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php'
                || $relative === 'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php'
                || $relative === 'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php'
                || $relative === 'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php') {
                continue;
            }

            if (str_contains($this->codeOnly($this->read($relative)), 'LocationDna\\Persistence')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Only the two migrated workflow components may reference the persistence namespace. Found: '
            .implode(', ', $offenders)
        );
    }

    /** The service never reaches for a request, global auth or Livewire state. */
    public function test_the_service_reads_no_request_auth_or_livewire_state(): void
    {
        $code = $this->codeOnly($this->read('app/Services/LocationDna/Persistence/LocationDnaPersistenceService.php'));

        foreach (['Request', 'Auth::', 'auth()', 'request(', 'Livewire', '$this->dirty'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $code,
                "The canonical writer must not reference `{$forbidden}`."
            );
        }
    }
}
