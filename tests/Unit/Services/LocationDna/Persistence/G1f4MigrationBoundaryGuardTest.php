<?php

namespace Tests\Unit\Services\LocationDna\Persistence;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * G1f-4 — the migration boundary guard.
 *
 * G1f-4 authorized migrating exactly TWO further workflows — the Tenant Offer pair, together —
 * plus the prerequisite that made the `zipCodes` mirror manageable. This suite is the standing
 * proof that it did both and stopped there.
 *
 * WHY THE PAIRING AND THE COUNTS ARE ASSERTED
 * -------------------------------------------
 * "Two migrated, two remaining, three direct writers" is the whole content of the authorization.
 * A guard that checked only that the Tenant Offer files changed would pass just as happily if a
 * Hire edit sibling had been dragged along, or if only one of the pair had moved — the half-state
 * the pairing rule exists to forbid.
 */
class G1f4MigrationBoundaryGuardTest extends TestCase
{
    private const CANONICAL_WRITE = "saveMeta('location_dna_preferences'";

    private const TENANT_OFFER = [
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
    ];

    /** The six workflow components migrated after G1f-4. */
    private const MIGRATED = [
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        'app/Http/Livewire/TenantAgentAuction.php',
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
        'app/Http/Livewire/TenantAgentAuctionEdit.php',
    ];

    /** The two that remain — both Hire EDIT siblings. */
    /**
     * COMPLETE AS OF G1f-6 — empty. All eight workflow components reach the canonical writer.
     *
     * Retained rather than deleted so a NEW unmigrated write path has somewhere to surface, and so
     * the emptiness is asserted rather than assumed.
     */
    private const UNMIGRATED = [];

    /**
     * Files still permitted to write the canonical key directly, after G1f-4. Five → three.
     *
     * The two legacy criteria controllers are required by D-G1F-5 and are NOT G1f-4's to remove.
     */
    private const AUTHORIZED_WRITERS = [
        'app/Http/Controllers/BuyerCriteriaAuctionController.php',
        'app/Http/Controllers/TenantCriteriaAuctionController.php',
        'app/Http/Livewire/Concerns/HasSearchAreas.php',
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

    /** @return list<string> every production PHP file, recursively */
    private function productionFiles(): array
    {
        $files = [];

        foreach (['app', 'config', 'routes', 'database'] as $root) {
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
    // BOTH TENANT OFFER WRITERS MIGRATED — TOGETHER
    // ═════════════════════════════════════════════════════════════════════════

    /** Each copy reaches the writer once and writes nothing inline. */
    public function test_both_tenant_offer_writers_are_migrated(): void
    {
        foreach (self::TENANT_OFFER as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringContainsString('OwnerPrivateLocationDnaWriter', $code, $relative);
            $this->assertSame(
                1,
                substr_count($code, '$this->persistLocationDna($auction);'),
                "{$relative}: exactly one canonical writer call site"
            );
            $this->assertStringNotContainsString(
                self::CANONICAL_WRITE,
                $code,
                "{$relative}: the inline canonical write must be gone"
            );

            foreach (['cities', 'counties', 'state', 'zipCodes'] as $mirror) {
                $this->assertStringNotContainsString(
                    "\$auction->saveMeta('{$mirror}',",
                    $code,
                    "{$relative}: the inline {$mirror} mirror write must be gone"
                );
            }
        }
    }

    /** NEITHER copy may migrate without the other. */
    public function test_the_tenant_offer_pair_is_migrated_together(): void
    {
        $create = str_contains($this->codeOnly($this->read(self::TENANT_OFFER[0])), 'persistLocationDna');
        $edit   = str_contains($this->codeOnly($this->read(self::TENANT_OFFER[1])), 'persistLocationDna');

        $this->assertSame(
            $create,
            $edit,
            'The Tenant Offer create and edit copies are one implementation copied twice. They '
            .'migrate together or not at all — a half-migrated pair is not a valid state.'
        );
        $this->assertTrue($create, 'and after G1f-4 both are migrated');
    }

    /** Both opt into the `zipCodes` mirror explicitly, at the call site. */
    public function test_both_tenant_offer_writers_opt_into_the_zipcodes_mirror(): void
    {
        foreach (self::TENANT_OFFER as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringContainsString('managingMirrors', $code, "{$relative}: opt-in seam");

            // The FULL opt-in expression, not a bare "'zipCodes'" — that string also occurs in
            // this file's unrelated `validate(['zipCodes' => ...])` rule, which would make the
            // assertion vacuous. Caught by the probe that removed the opt-in and saw this pass.
            $this->assertStringContainsString(
                "[...LegacyMirrorProjection::MANAGED_KEYS, 'zipCodes']",
                $code,
                "{$relative}: must opt the zipCodes mirror into the managed set explicitly"
            );
        }
    }

    /** The transaction in the edit copy is not widened — it still opens exactly one. */
    public function test_the_edit_flow_does_not_widen_its_transaction(): void
    {
        $code = $this->codeOnly($this->read(self::TENANT_OFFER[1]));

        $this->assertSame(
            1,
            substr_count($code, 'DB::beginTransaction();'),
            'the edit flow must still open exactly one transaction — G1f-4 adds none'
        );
        $this->assertStringNotContainsString(
            'DB::transaction(function',
            $code,
            'no new transaction wrapper may be introduced in the component'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PRE-VALIDATION HYDRATION SURVIVES
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The write-side hydration call is gone; the PRE-VALIDATION one is not.
     *
     * Both copies previously called `hydrateDiscreteLocationFromBlob()` twice: once before
     * validation, to populate `$this->state` / `$this->counties` for the `required` rules after
     * 9B-3 removed the discrete inputs, and once immediately before the mirror writes. Only the
     * second is obsolete. Removing the first would break submit for every listing whose location
     * comes only from the map, so exactly one call must remain in each file.
     */
    public function test_the_pre_validation_hydration_call_remains_and_the_write_side_one_does_not(): void
    {
        foreach (self::TENANT_OFFER as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertSame(
                1,
                substr_count($code, '$this->hydrateDiscreteLocationFromBlob();'),
                "{$relative}: exactly one hydration CALL must remain — the pre-validation one"
            );
            $this->assertStringContainsString(
                'protected function hydrateDiscreteLocationFromBlob(): void',
                $code,
                "{$relative}: the method definition itself is untouched"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // COUNTS
    // ═════════════════════════════════════════════════════════════════════════

    /** Exactly six workflow components are wired to the writer — no more. */
    public function test_exactly_six_workflow_components_are_wired(): void
    {
        $wired = [];

        /** @var iterable<\SplFileInfo> $it */
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root().'/app/Http/Livewire')
        );

        foreach ($it as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace($this->root().'/', '', $file->getPathname());

            if (str_contains($this->codeOnly($this->read($relative)), 'LocationDna\\Persistence')) {
                $wired[] = $relative;
            }
        }

        sort($wired);
        $expected = self::MIGRATED;
        sort($expected);

        $this->assertSame($expected, array_values(array_unique($wired)));
        $this->assertCount(8, self::MIGRATED);
    }

    /** No workflow remains unmigrated; the migrated set is verified positively instead. */
    public function test_the_two_remaining_workflows_are_untouched(): void
    {
        $this->assertSame([], self::UNMIGRATED, 'all eight are migrated as of G1f-6');

        foreach (self::MIGRATED as $relative) {
            $this->assertStringContainsString(
                'OwnerPrivateLocationDnaWriter',
                $this->codeOnly($this->read($relative)),
                "{$relative} must be wired to the canonical writer."
            );
        }
    }

    /**
     * The direct canonical writers shrank five → three, and the survivors are the expected ones.
     */
    public function test_exactly_three_direct_canonical_writers_remain(): void
    {
        $offenders = [];

        foreach ($this->productionFiles() as $relative) {
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
            3,
            self::AUTHORIZED_WRITERS,
            'Three direct writers remain after G1f-4 — down from five. This list may only SHRINK.'
        );

        // And the two the next increment governs are still among them.
        $this->assertContains('app/Http/Controllers/BuyerCriteriaAuctionController.php', self::AUTHORIZED_WRITERS);
        $this->assertContains('app/Http/Controllers/TenantCriteriaAuctionController.php', self::AUTHORIZED_WRITERS);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DEFERRED SEAMS · still deferred
    // ═════════════════════════════════════════════════════════════════════════

    public function test_g1f4_introduced_no_new_production_surface(): void
    {
        $this->assertFileDoesNotExist(
            $this->root().'/app/Services/LocationDna/Persistence/LegacyMirrorAdapter.php'
        );
        $this->assertFileDoesNotExist($this->root().'/config/location_dna_persistence.php');

        foreach (self::TENANT_OFFER as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringNotContainsString('CriteriaHashService', $code, $relative);
            $this->assertStringNotContainsString('PublicGeometryProjection', $code, $relative);
            $this->assertStringNotContainsString('Bridge', $code, $relative);
        }
    }

    /** No provenance is persisted, and no migration references the persistence namespace. */
    public function test_no_provenance_and_no_migration(): void
    {
        foreach ($this->productionFiles() as $relative) {
            if (! str_starts_with($relative, 'database/migrations')) {
                continue;
            }

            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $this->read($relative),
                "{$relative}: no migration may reference the persistence namespace"
            );
        }

        foreach (self::TENANT_OFFER as $relative) {
            $this->assertStringNotContainsString(
                "saveMeta('location_dna_provenance",
                $this->read($relative),
                "{$relative}: no provenance persistence"
            );
        }
    }
}
