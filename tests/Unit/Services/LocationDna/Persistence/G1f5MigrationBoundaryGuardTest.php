<?php

namespace Tests\Unit\Services\LocationDna\Persistence;

use Tests\TestCase;

/**
 * G1f-5 — the migration boundary. `BuyerAgentAuctionEdit` and NOTHING ELSE.
 *
 * WHAT THIS SUITE IS FOR
 * ----------------------
 * The behavioural proof that the migrated component writes correctly lives in
 * {@see \Tests\Feature\Spatial\G1f5BuyerAgentAuctionEditMigrationTest}. This suite asserts the
 * complement, which no behavioural test can: that the migration touched nothing else. A
 * consolidation that quietly rewires a second workflow, retires the shared trait, or introduces a
 * new production surface would pass every behavioural test and still be outside its authorization.
 *
 * THE FOUR BOUNDARIES G1f-5 WAS SCOPED TO
 * ---------------------------------------
 *   1. ONE component migrated. `BuyerAgentAuctionEdit` only — not its Hire Tenant edit sibling,
 *      which shares most of its save body and is the one most likely to be migrated "while we are
 *      in there".
 *   2. `HasSearchAreas` NOT retired and NOT rewired. It still serves `TenantAgentAuctionEdit`,
 *      which is now its ONLY host. A trait with one remaining host is exactly when someone is
 *      tempted to inline it; that is a separate increment with its own authorization.
 *   3. NO `zipCodes`. The Buyer family has never written that key. G1f-4 added a surface-scoped
 *      opt-in for the Tenant family, and the whole point of scoping it was that it must not leak
 *      into Buyer workflows — including this one.
 *   4. NO new transaction. `LocationDnaPersistenceService` owns the transaction; a second one
 *      opened in the component would nest silently and change failure semantics.
 *
 * The legacy criteria controllers (D-G1F-5) are explicitly still direct writers and still in the
 * §21 count. G1f-5 did not touch them.
 */
class G1f5MigrationBoundaryGuardTest extends TestCase
{
    private const CANONICAL_WRITE = "saveMeta('location_dna_preferences'";

    /** The component G1f-5 migrated — the only one it was authorized to touch. */
    private const TARGET = 'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php';

    /** Every workflow component wired to the canonical writer after G1f-5. */
    private const MIGRATED = [
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        'app/Http/Livewire/TenantAgentAuction.php',
        'app/Http/Livewire/TenantAgentAuctionEdit.php',
    ];

    /**
     * COMPLETE AS OF G1f-6 — empty. SHRINK-ONLY.
     *
     * Retained rather than deleted so a NEW unmigrated write path has somewhere to surface, and so
     * the emptiness is asserted rather than assumed.
     */
    private const UNMIGRATED = [];

    /**
     * Files still permitted to write the canonical key directly, after G1f-5.
     *
     * The trait plus the two legacy criteria controllers D-G1F-5 governs. This list may only
     * SHRINK; reaching one entry is G1f's completion condition.
     */
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
    private function productionFiles(): array
    {
        $files = [];

        foreach (['app', 'routes', 'database', 'config'] as $dir) {
            $base = $this->root().'/'.$dir;

            if (! is_dir($base)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = ltrim(str_replace($this->root(), '', $file->getPathname()), '/');
                }
            }
        }

        sort($files);

        return $files;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE TARGET IS WIRED, AND CORRECTLY
    // ═════════════════════════════════════════════════════════════════════════

    /** The migrated component reaches the canonical writer through the approved seam, once. */
    public function test_the_target_is_wired_to_the_canonical_writer_exactly_once(): void
    {
        $code = $this->codeOnly($this->read(self::TARGET));

        $this->assertStringContainsString(
            'OwnerPrivateLocationDnaWriter',
            $code,
            'BuyerAgentAuctionEdit must reach the writer through the owner-private seam.'
        );
        $this->assertSame(
            1,
            substr_count($code, 'new OwnerPrivateLocationDnaWriter()'),
            'Exactly one writer instantiation — a second would be a second write path.'
        );
        $this->assertSame(
            1,
            substr_count($code, '$this->persistLocationDna($auction);'),
            'Exactly one call site for the seam.'
        );
        $this->assertStringContainsString(
            'persistFromEditorPayload($auction, $this->location_dna_preferences_json)',
            $code,
            'The bridged editor payload must be passed through as-is — the component holds no '
            .'Location DNA policy of its own.'
        );
    }

    /** The old write path is gone in full: three inline mirrors AND the trait call. */
    public function test_the_pre_migration_write_path_is_gone(): void
    {
        $code = $this->codeOnly($this->read(self::TARGET));

        foreach ([
            "saveMeta('cities', json_encode(\$this->cities))",
            "saveMeta('counties', json_encode(\$this->counties))",
            "saveMeta('state', \$this->state)",
        ] as $inlineWrite) {
            $this->assertStringNotContainsString(
                $inlineWrite,
                $code,
                "The inline mirror write `{$inlineWrite}` must be gone — it was the component-property "
                .'half of the double-write.'
            );
        }

        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            $code,
            'The trait save call must be gone — it was the second half.'
        );
        $this->assertStringNotContainsString(
            self::CANONICAL_WRITE,
            $code,
            'And the component must not write the canonical key directly either.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · NOTHING ELSE MOVED
    // ═════════════════════════════════════════════════════════════════════════

    /** Exactly the eight migrated components reference the persistence namespace. */
    public function test_exactly_eight_workflow_components_are_wired(): void
    {
        $wired = [];

        foreach ($this->productionFiles() as $relative) {
            if (str_starts_with($relative, 'app/Services/LocationDna/')) {
                continue;
            }

            if (str_contains($this->codeOnly($this->read($relative)), 'LocationDna\\Persistence')) {
                $wired[] = $relative;
            }
        }

        sort($wired);
        $expected = self::MIGRATED;
        sort($expected);

        $this->assertSame(
            $expected,
            array_values(array_unique($wired)),
            'Exactly eight workflow components may reference the persistence namespace after G1f-6. '
            .'A new name here is a workflow migrated without authorization.'
        );
        $this->assertCount(8, self::MIGRATED);
    }

    /** No unmigrated workflow remains; the migrated set is verified positively instead. */
    public function test_no_unmigrated_workflow_remains(): void
    {
        $this->assertSame(
            [],
            self::UNMIGRATED,
            'All eight workflow components are migrated as of G1f-6. This list may only SHRINK.'
        );

        // Non-vacuous counterpart, so this test still exercises all eight rather than an empty set.
        foreach (self::MIGRATED as $relative) {
            $this->assertStringContainsString(
                'OwnerPrivateLocationDnaWriter',
                $this->codeOnly($this->read($relative)),
                "{$relative} must be wired to the canonical writer."
            );
        }
    }

    /**
     * INVERTED BY G1f-6 · `TenantAgentAuctionEdit` is migrated, and its seller/landlord branch is
     * preserved exactly.
     *
     * This test used to assert the sibling still carried its ORIGINAL construct — it was G1f-5's
     * guard against dragging it along. It has since been migrated under its own authorization, so
     * the assertion is inverted to pin what that migration was and was not allowed to do.
     *
     * The third assertion is the load-bearing one. The three inline mirror writes used to stand
     * ABOVE the gate and therefore ran for all four roles, while the trait ran for only two. For
     * `seller` and `landlord` they were the ONLY mirror writes those roles ever had, uncorrected by
     * any blob. They must survive in the else branch; removing them would silently stop mirroring
     * location for half the supported roles.
     */
    public function test_the_tenant_edit_sibling_is_migrated_and_kept_its_seller_landlord_branch(): void
    {
        $code = $this->codeOnly($this->read('app/Http/Livewire/TenantAgentAuctionEdit.php'));

        $this->assertStringContainsString(
            '$this->persistLocationDna($auction);',
            $code,
            'it reaches the canonical writer'
        );
        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            $code,
            'and no longer reaches the canonical key through the trait'
        );

        // The seller / landlord else branch, preserved verbatim.
        foreach ([
            "\$auction->saveMeta('cities', json_encode(\$this->cities));",
            "\$auction->saveMeta('counties', json_encode(\$this->counties));",
            "\$auction->saveMeta('state', \$this->state);",
        ] as $write) {
            $this->assertStringContainsString(
                $write,
                $code,
                "the seller/landlord branch must keep `{$write}` — those roles have no canonical "
                .'document, so this is their only mirror write'
            );
        }

        // The gate itself, preserved — D-G1F-3 3-C.
        $this->assertStringContainsString(
            "in_array(\$this->user_type, ['buyer', 'tenant'])",
            $code,
            'the user_type gate must survive verbatim'
        );

        // And zipCodes stayed unmanaged and property-sourced, matching the create sibling.
        $this->assertStringContainsString(
            "\$auction->saveMeta('zipCodes', json_encode(\$this->zipCodes));",
            $code,
            'zipCodes must remain an ungated, property-sourced mirror for the Hire family'
        );
        $this->assertStringNotContainsString(
            'managingMirrors(',
            $code,
            'and must NOT adopt the Offer family\'s managed opt-in'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE TRAIT IS NEITHER RETIRED NOR REWIRED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * `HasSearchAreas` survives G1f-5 unchanged, and still serves its one remaining host.
     *
     * Down to a single host, the trait becomes tempting to inline or delete. Retiring it is a
     * separate increment; this pins that G1f-5 did not start it.
     */
    public function test_has_search_areas_is_not_retired_or_rewired(): void
    {
        $this->assertFileExists(
            $this->root().'/app/Http/Livewire/Concerns/HasSearchAreas.php',
            'HasSearchAreas must not be deleted by G1f-5.'
        );

        $trait = $this->read('app/Http/Livewire/Concerns/HasSearchAreas.php');

        $this->assertStringNotContainsString(
            'LocationDna\\Persistence',
            $this->codeOnly($trait),
            'The trait must not be rewired to the canonical writer — that would migrate its '
            .'remaining host by side effect.'
        );
        $this->assertStringContainsString(
            "empty(\$ldna['cities'] ?? [])",
            $trait,
            'the trait keeps its pre-consolidation presence semantics for its remaining host'
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

        // INVERTED BY G1f-6 · the trait's save side now has ZERO callers.
        //
        // G1f-5 asserted here that `TenantAgentAuctionEdit` still called it, because it was then
        // the last host. G1f-6 migrated it, so the surviving invariant is the complement: the trait
        // is retained but nothing calls its save side. Asserted as an absence across the whole
        // application rather than against one named file, so a workflow reacquiring the old write
        // path fails here regardless of which one it is.
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
            'saveSearchAreas() must have no callers after G1f-6 — it is retained as dead code and '
            .'retiring it is the closeout increment. Found: '.implode(', ', $callers)
        );

        // The migrated component still USES the trait for its load side; only the write moved.
        $this->assertStringContainsString(
            'HasSearchAreas',
            $this->read(self::TARGET),
            'The target still uses the trait for loadSearchAreas() — G1f-5 moved the WRITE only, '
            .'and unhooking the load side was never in scope.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · SCOPE BOUNDARIES: zipCodes AND TRANSACTIONS
    // ═════════════════════════════════════════════════════════════════════════

    /** `zipCodes` did not leak into the Buyer family (D-G1F-4, §17.4). */
    public function test_zip_codes_was_not_introduced(): void
    {
        $code = $this->codeOnly($this->read(self::TARGET));

        $this->assertStringNotContainsString(
            'zipCodes',
            $code,
            'The Buyer family has never written the zipCodes mirror and G1f-5 must not start.'
        );
        $this->assertStringNotContainsString(
            'managingMirrors(',
            $code,
            'The target must use the DEFAULT managed mirror set. Naming an opt-in set here would '
            .'emit a legacy key this workflow has never written.'
        );

        // The default set is still exactly the three keys, unchanged by this increment.
        $this->assertStringContainsString(
            "MANAGED_KEYS = ['cities', 'counties', 'state']",
            $this->read('app/Services/LocationDna/Persistence/LegacyMirrorProjection.php'),
            'The default managed mirror set must be unchanged — making zipCodes global would make '
            .'four Buyer workflows emit a mirror they have never written.'
        );
    }

    /** No transaction was opened in the component; the writer owns it. */
    public function test_no_transaction_was_introduced_in_the_component(): void
    {
        $code = $this->codeOnly($this->read(self::TARGET));

        foreach (['DB::transaction(', 'DB::beginTransaction(', '->beginTransaction('] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $code,
                "G1f-5 must not open a transaction ({$needle}) — LocationDnaPersistenceService "
                .'already wraps the canonical write and its mirrors in one.'
            );
        }

        $this->assertStringContainsString(
            'DB::transaction(',
            $this->read('app/Services/LocationDna/Persistence/LocationDnaPersistenceService.php'),
            'and the writer must still own one'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · THE §21 DIRECT-WRITER LIST
    // ═════════════════════════════════════════════════════════════════════════

    /** Only the authorized writers write the canonical key directly, and there are still three. */
    public function test_the_direct_writer_list_is_unchanged_at_three(): void
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

        // G1f-5 migrated a TRAIT host, so the trait itself remains a direct writer for its last
        // host and the count is unchanged. This increment shortens the migration list, not this one.
        $this->assertCount(
            2,
            self::AUTHORIZED_WRITERS,
            'Three direct writers remain after G1f-5, unchanged — the target reached the canonical '
            .'key through the trait, so migrating it removes a HOST, not a writer.'
        );

        // The two D-G1F-5 governs are still in scope and still counted.
        $this->assertContains('app/Http/Controllers/BuyerCriteriaAuctionController.php', self::AUTHORIZED_WRITERS);
        $this->assertContains('app/Http/Controllers/TenantCriteriaAuctionController.php', self::AUTHORIZED_WRITERS);
    }

    /** The legacy criteria controllers are untouched — D-G1F-5 governs them, not G1f-5. */
    public function test_the_legacy_criteria_controllers_are_untouched(): void
    {
        foreach ([
            'app/Http/Controllers/BuyerCriteriaAuctionController.php',
            'app/Http/Controllers/TenantCriteriaAuctionController.php',
        ] as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringContainsString(
                self::CANONICAL_WRITE,
                $code,
                "{$relative} must still write the canonical key directly — it is in G1f's final "
                .'scope and must not be quietly dropped from the §21 list.'
            );
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $code,
                "{$relative} must not be wired by G1f-5."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 6 · DEFERRED SEAMS · STILL DEFERRED
    // ═════════════════════════════════════════════════════════════════════════

    /** G1f-5 introduced no new production surface. */
    public function test_g1f5_introduced_no_new_production_surface(): void
    {
        $this->assertFileDoesNotExist(
            $this->root().'/app/Services/LocationDna/Persistence/LegacyMirrorAdapter.php',
            'LegacyMirrorAdapter remains uncreated and separately authorization-gated.'
        );
        $this->assertFileDoesNotExist(
            $this->root().'/config/location_dna_persistence.php',
            'No configuration surface was introduced.'
        );
    }
}
