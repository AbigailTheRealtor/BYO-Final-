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
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        'app/Http/Controllers/BuyerCriteriaAuctionController.php',
        'app/Http/Controllers/TenantCriteriaAuctionController.php',
    ];

    /**
     * COMPLETE AS OF G1f-6 — no workflow component writes Location DNA the old way.
     *
     * SHRINK-ONLY, and now empty. `TenantAgentAuction` left at G1f-2, both Buyer Offer copies at
     * G1f-3, both Tenant Offer copies at G1f-4, `BuyerAgentAuctionEdit` at G1f-5 and
     * `TenantAgentAuctionEdit` at G1f-6. All eight workflow components reach the canonical writer.
     *
     * The list is kept rather than deleted so a NEW unmigrated write path re-entering the codebase
     * has somewhere to show up, and so the emptiness itself is asserted rather than assumed.
     */
    private const UNMIGRATED_WORKFLOWS = [];

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
            4,
            self::AUTHORIZED_WRITERS,
            'Four direct writers remain. The closeout removed HasSearchAreas by deleting its dead save '
            .'method — the write went with it. This list may only SHRINK; the two legacy criteria '
            .'controllers D-G1F-5 governs are the last workflow-independent entries.'
        );
    }

    /** No unmigrated workflow remains — the emptiness is asserted, not assumed. */
    public function test_no_unmigrated_workflow_remains(): void
    {
        $this->assertSame(
            [],
            self::UNMIGRATED_WORKFLOWS,
            'All eight workflow components are migrated as of G1f-6. A name reappearing here means '
            .'an unmigrated Location DNA write path re-entered the codebase.'
        );

        foreach (self::UNMIGRATED_WORKFLOWS as $relative) {
            $source = $this->codeOnly($this->read($relative));

            $this->assertStringNotContainsString(
                'OwnerPrivateLocationDnaWriter',
                $source,
                "{$relative} must NOT be wired to the canonical writer — that is a later increment."
            );
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $source,
                "{$relative} must not reference the persistence namespace."
            );
        }
    }

    /**
     * `HasSearchAreas` is neither retired nor rewired, and its save side is now DEAD CODE.
     *
     * INVERTED BY G1f-6. This test used to name the hosts that still called `saveSearchAreas()`
     * and assert they did. `TenantAgentAuctionEdit` was the last of them, so that list is empty and
     * asserting over it would iterate nothing.
     *
     * The property that replaces it is stronger and is the actual post-migration invariant: the
     * trait still EXISTS and is UNCHANGED — G1f-6 was not authorized to retire it — while its save
     * side has ZERO callers anywhere in the application. Both halves matter. A silent deletion
     * would fail the first; a workflow quietly reacquiring the old write path would fail the
     * second. Retiring `saveSearchAreas()` is the closeout increment, with its own authorization.
     *
     * Its LOAD side is deliberately still live and still used, so the trait cannot simply go.
     */
    public function test_has_search_areas_is_not_retired_and_its_save_side_is_dead_code(): void
    {
        $this->assertFileExists(
            $this->root().'/app/Http/Livewire/Concerns/HasSearchAreas.php',
            'HasSearchAreas must not be deleted — retiring it is a separate increment.'
        );

        $trait = $this->read('app/Http/Livewire/Concerns/HasSearchAreas.php');

        $this->assertStringNotContainsString(
            'LocationDna\\Persistence',
            $this->codeOnly($trait),
            'the trait must not be rewired to the canonical writer'
        );
        $this->assertStringContainsString(
            "empty(\$ldna['cities'] ?? [])",
            $trait,
            'the trait keeps its pre-consolidation semantics, unchanged'
        );
        // CLOSEOUT: the canonical write is GONE from the trait. This is what removes it from the
        // §21 direct-writer list and makes the persistence service the sole writer of this key.
        $this->assertStringNotContainsString(
            self::CANONICAL_WRITE,
            $this->codeOnly($trait),
            'the trait must no longer contain a canonical write — the closeout removed it'
        );
        $this->assertStringNotContainsString(
            'function saveSearchAreas',
            $trait,
            'and the dead save method itself must be gone'
        );
        $this->assertStringContainsString(
            'function loadSearchAreas',
            $trait,
            'the load side is retained and unchanged — it is why the trait still exists'
        );

        // ZERO callers of the save side, anywhere.
        $callers = [];

        foreach ($this->productionFiles() as $relative) {
            if ($relative === 'app/Http/Livewire/Concerns/HasSearchAreas.php') {
                continue;
            }

            if (str_contains($this->codeOnly($this->read($relative)), '$this->saveSearchAreas(')) {
                $callers[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $callers,
            'saveSearchAreas() must have no callers after G1f-6. Found: '.implode(', ', $callers)
        );

        // The LOAD side is still live — this is why the trait cannot simply be deleted.
        $loaders = [];

        foreach ($this->productionFiles() as $relative) {
            if (str_contains($this->codeOnly($this->read($relative)), '$this->loadSearchAreas(')) {
                $loaders[] = $relative;
            }
        }

        $this->assertNotEmpty(
            $loaders,
            'loadSearchAreas() must still have callers — the trait is retained for its load side.'
        );
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
                || $relative === 'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php'
                || $relative === 'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php'
                || $relative === 'app/Http/Livewire/TenantAgentAuctionEdit.php') {
                continue;
            }

            if (str_contains($this->codeOnly($this->read($relative)), 'LocationDna\\Persistence')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Only the eight migrated workflow components may reference the persistence namespace. Found: '
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
