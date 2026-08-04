<?php

namespace Tests\Unit\Services\LocationDna\Persistence;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\DimensionCommand;
use App\Services\LocationDna\Contract\DimensionCommandApplier;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use App\Services\LocationDna\Persistence\LegacyMirrorProjection;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * G1f-2 — the migration boundary guard.
 *
 * WHAT IT PROTECTS
 * ----------------
 * G1f-2 authorized migrating exactly ONE further workflow. `G1f1MigrationBoundaryGuardTest`
 * remains the standing §21 direct-writer guard and its shrink-only lists; this suite adds the
 * assertions specific to the second migration: that TWO workflows are migrated and not three,
 * that the two things G1f-2 deliberately did NOT change are still unchanged, and that the
 * deferred seams stayed deferred.
 *
 * TWO PRESERVATIONS, GUARDED EXPLICITLY
 * -------------------------------------
 * A migration that quietly removes the `user_type` gate, or quietly folds `zipCodes` into the
 * managed mirror set, would look like a successful consolidation. Both are decisions with their
 * own authorization (D-G1F-3 3-C and the §17.4 checkpoint), so both are asserted here rather
 * than left to review.
 */
class G1f2MigrationBoundaryGuardTest extends TestCase
{
    private const MIGRATED = [
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
        'app/Http/Livewire/TenantAgentAuction.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
    ];

    /**
     * The workflow implementations that must be left completely alone.
     *
     * SHRINK-ONLY. The two Buyer Offer copies left at G1f-3, the two Tenant Offer copies at
     * G1f-4 and `BuyerAgentAuctionEdit` at G1f-5; only `TenantAgentAuctionEdit` remains.
     */
    private const UNMIGRATED_WORKFLOWS = [
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

    // ═════════════════════════════════════════════════════════════════════════
    // EXACTLY TWO WORKFLOWS MIGRATED
    // ═════════════════════════════════════════════════════════════════════════

    /** `TenantAgentAuction` reaches the canonical writer and no longer calls the trait save. */
    public function test_tenant_agent_auction_is_migrated(): void
    {
        $source = $this->read('app/Http/Livewire/TenantAgentAuction.php');
        $code   = $this->codeOnly($source);

        $this->assertStringContainsString('OwnerPrivateLocationDnaWriter', $code);
        $this->assertStringContainsString('$this->persistLocationDna($auction);', $code);
        $this->assertStringNotContainsString(
            "saveMeta('location_dna_preferences'",
            $code,
            'it must not write the canonical key directly'
        );
        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            $code,
            'the trait save must be gone from the migrated component'
        );
    }

    /**
     * Exactly the migrated workflow components reference the persistence namespace.
     *
     * The count is the point: it fails if a further workflow is wired, whether deliberately or
     * by a copy-paste that looked harmless. Six after G1f-4.
     */
    public function test_exactly_the_migrated_workflow_components_are_wired_to_the_writer(): void
    {
        $wired = [];

        // RECURSIVE, deliberately. This scan originally used `glob('…/Livewire/**/*.php')` plus a
        // one-level glob. PHP's `glob()` does NOT recurse on `**` — it behaves as a single `*` —
        // so the two-level-deep `OfferListing/Buyer/…` and `OfferListing/Tenant/…` components
        // were never scanned at all. The guard would have reported "exactly two wired" no matter
        // what those four files contained. Caught when G1f-3 migrated two of them and the count
        // did not move. Use a real recursive iterator, as `G1f1MigrationBoundaryGuardTest` does.
        /** @var iterable<\SplFileInfo> $it */
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root().'/app/Http/Livewire')
        );

        foreach ($it as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace($this->root().'/', '', $file->getPathname());

            if (str_contains($this->codeOnly((string) file_get_contents($file->getPathname())), 'LocationDna\\Persistence')) {
                $wired[] = $relative;
            }
        }

        sort($wired);

        $expected = self::MIGRATED;
        sort($expected);

        $this->assertSame(
            $expected,
            array_values(array_unique($wired)),
            'Exactly seven workflow components may be wired to the canonical writer after G1f-5.'
        );
    }

    /** The remaining unmigrated workflow is untouched. */
    public function test_the_remaining_unmigrated_workflows_are_untouched(): void
    {
        $this->assertCount(1, self::UNMIGRATED_WORKFLOWS);

        foreach (self::UNMIGRATED_WORKFLOWS as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringNotContainsString(
                'OwnerPrivateLocationDnaWriter',
                $code,
                "{$relative} must NOT be wired to the canonical writer — that is a later increment."
            );
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $code,
                "{$relative} must not reference the persistence namespace."
            );
        }
    }

    /**
     * `TenantAgentAuctionEdit` — the sibling of the migrated component — is explicitly
     * unchanged, gate and all.
     *
     * Called out separately from the list above because it is the one most likely to be
     * migrated "while we are in there": it shares the gate, the zipCodes write and most of
     * the save body with the component G1f-2 did migrate.
     */
    public function test_the_tenant_edit_sibling_still_writes_the_old_way(): void
    {
        $source = $this->read('app/Http/Livewire/TenantAgentAuctionEdit.php');

        $this->assertStringContainsString(
            "in_array(\$this->user_type, ['buyer', 'tenant'])",
            $source,
            'the gate is still there'
        );
        $this->assertStringContainsString(
            '$this->saveSearchAreas($auction);',
            $source,
            'and it still reaches the canonical key through the trait'
        );
        $this->assertStringContainsString(
            "\$auction->saveMeta('cities', json_encode(\$this->cities));",
            $source,
            'and still carries its component-property mirror write — the last surviving double-write'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WHAT G1f-2 DELIBERATELY PRESERVED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * D-G1F-3 3-C · the `user_type` gate survives in the migrated component, unchanged.
     *
     * The gate literal occurs several times in this 5,300-line component for unrelated
     * guards, so a whole-file search would still pass if the gate guarding the WRITER were
     * widened to admit `seller` and `landlord`. Non-vacuity probe 6 demonstrated exactly
     * that, so the assertion is on the nearest gate preceding the writer call.
     */
    public function test_the_user_type_gate_is_preserved_in_the_migrated_component(): void
    {
        $code       = $this->codeOnly($this->read('app/Http/Livewire/TenantAgentAuction.php'));
        $entryPoint = '$this->persistLocationDna($auction);';
        $at         = strpos($code, $entryPoint);

        $this->assertNotFalse($at, 'the canonical writer call must be present');

        preg_match_all('/in_array\(\$this->user_type, \[[^\]]*\]\)/', substr($code, 0, $at), $matches);

        $this->assertSame(
            "in_array(\$this->user_type, ['buyer', 'tenant'])",
            (string) end($matches[0]),
            'the gate immediately guarding the canonical writer must admit buyer and tenant only. '
            .'Widening it is a separate product decision, not part of a writer migration.'
        );

        $this->assertSame(
            1,
            substr_count($code, $entryPoint),
            'and there must be exactly one route past it into the canonical writer'
        );
    }

    /**
     * D-G1F-4 (a) · `zipCodes` is still unmanaged BY DEFAULT and still property-sourced here.
     *
     * SUPERSEDED CLAUSE, DELIBERATELY REPLACED
     * ----------------------------------------
     * This test used to assert that the projection contained no `zipCodes` string at all. The
     * G1f-4 prerequisite (approved) makes `zipCodes` a SURFACE-SCOPED OPT-IN key, so the projection
     * legitimately names it now. What the §17.4 checkpoint actually protects is not the absence of
     * a string but the absence of a behaviour change, so the guarantee is asserted behaviourally:
     * the DEFAULT projection must still emit nothing, even when canonical ZIPs are present-cleared,
     * which is the exact shape every Buyer blob carries.
     */
    public function test_zipcodes_is_still_outside_the_default_managed_mirror_set(): void
    {
        $this->assertStringContainsString(
            "public const MANAGED_KEYS = ['cities', 'counties', 'state'];",
            $this->read('app/Services/LocationDna/Persistence/LegacyMirrorProjection.php'),
            'the DEFAULT managed set must not have grown'
        );

        $document = (new DimensionCommandApplier())->apply(LocationDnaDocument::emptyDocument(), [
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
            DimensionCommand::clear(Dimension::ZipCodes),
        ]);

        $this->assertArrayNotHasKey(
            'zipCodes',
            (new LegacyMirrorProjection())->project($document),
            'the default projection must still emit no zipCodes mirror'
        );

        $this->assertStringContainsString(
            "\$auction->saveMeta('zipCodes', json_encode(\$this->zipCodes));",
            $this->read('app/Http/Livewire/TenantAgentAuction.php'),
            'the migrated component keeps its property-sourced zipCodes write, unchanged'
        );
    }

    /**
     * No LIVEWIRE writer derives the `zipCodes` mirror from the blob.
     *
     * The projection is excluded from this list by the G1f-4 prerequisite: deriving `zipCodes` from
     * canonical state is now precisely its job when a surface opts in. The components below have
     * not opted in and must still be property-sourced.
     */
    public function test_no_livewire_writer_derives_zipcodes_from_the_canonical_document(): void
    {
        foreach ([
            'app/Http/Livewire/Concerns/HasSearchAreas.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringNotContainsString("\$ldnaDecoded['zip_codes']", $code, $relative);
            $this->assertStringNotContainsString("\$ldna['zip_codes']", $code, $relative);
            $this->assertStringNotContainsString('Dimension::ZipCodes', $code, $relative);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DEFERRED SEAMS · still deferred
    // ═════════════════════════════════════════════════════════════════════════

    /** The migrated component reaches none of the deferred or out-of-scope seams. */
    public function test_the_migrated_component_touches_no_deferred_seam(): void
    {
        $code = $this->codeOnly($this->read('app/Http/Livewire/TenantAgentAuction.php'));

        foreach ([
            'LegacyMirrorAdapter',
            'CriteriaHashService',
            'PublicGeometryProjection',
            'location_intelligence_snapshot',
            'AcceptedBidSummary',
            'LocationDnaProvenanceMap',
            'Dimension::',
            'DimensionCommand',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $code,
                "TenantAgentAuction must not reference `{$forbidden}` — the seam is deferred, out of "
                .'scope, or belongs behind OwnerPrivateLocationDnaWriter.'
            );
        }
    }

    /** D-G1F-2 · `LegacyMirrorAdapter` is still uncreated. */
    public function test_legacy_mirror_adapter_still_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            $this->root().'/app/Services/LocationDna/Persistence/LegacyMirrorAdapter.php'
        );
        $this->assertFalse(class_exists('App\\Services\\LocationDna\\Persistence\\LegacyMirrorAdapter'));
    }

    /** G1f-2 added no production class, no migration and no config. */
    public function test_g1f2_introduced_no_new_production_surface(): void
    {
        $persistence = array_map(
            'basename',
            glob($this->root().'/app/Services/LocationDna/Persistence/*.php') ?: []
        );
        sort($persistence);

        $this->assertSame(
            [
                'CanonicalMetaKey.php',
                'LegacyMirrorProjection.php',
                'LocationDnaCommandBuilder.php',
                'LocationDnaPersistenceService.php',
                'LocationDnaWritableRecord.php',
                'MetaKeyedRecord.php',
                'OwnerPrivateLocationDnaWriter.php',
                'PatchResult.php',
                'PersistenceOutcome.php',
            ],
            $persistence,
            'G1f-2 reuses the G1f-1 boundary; it must add no class to the persistence namespace.'
        );

        $this->assertFileDoesNotExist($this->root().'/config/location_dna_persistence.php');

        foreach (glob($this->root().'/database/migrations/*.php') ?: [] as $file) {
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                (string) file_get_contents($file),
                basename($file).' must not reference the persistence namespace'
            );
        }
    }

    /** The Bridge, hash and public-projection seams are untouched by the writer. */
    public function test_bridge_criteria_hash_and_public_projection_are_untouched(): void
    {
        foreach ([
            'app/Services/Bridge/CriteriaHashService.php',
            'app/Services/LocationDna/PublicGeometryProjection.php',
        ] as $relative) {
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $this->codeOnly($this->read($relative)),
                "{$relative} must not have been wired to the writer — D-G1-3's carried condition "
                .'keeps both unchanged through G1f.'
            );
        }
    }
}
