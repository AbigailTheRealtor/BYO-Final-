<?php

namespace Tests\Unit\Services\LocationDna\Persistence;

use Tests\TestCase;

/**
 * G1f-6 — the migration boundary. `TenantAgentAuctionEdit`, and the END of the workflow phase.
 *
 * WHAT MAKES THIS ONE DIFFERENT
 * -----------------------------
 * Every earlier increment replaced a write path. This one replaces a write path for HALF the roles
 * a single component serves, and must leave the other half byte-identical.
 *
 * `TenantAgentAuctionEdit` is multi-role: `$auctionClass = match ($this->user_type)` resolves to
 * the Buyer, Seller, Landlord or Tenant auction model. Before G1f-6 its Location DNA writes were
 * arranged so that the three discrete mirror writes stood ABOVE the `user_type` gate — running for
 * all four roles — while `saveSearchAreas()` stood inside it, running for only two:
 *
 *     saveMeta('cities' | 'counties' | 'state')      ← ALL FOUR roles
 *     if (in_array($this->user_type, ['buyer','tenant'])) {
 *         $this->saveSearchAreas($auction);          ← buyer / tenant ONLY
 *     }
 *     saveMeta('zipCodes')                           ← ALL FOUR roles
 *
 * For `buyer` and `tenant` the three inline writes were the losing half of the double-write. For
 * `seller` and `landlord` they were the ONLY mirror writes those roles have ever had, uncorrected
 * by any blob, because the trait never ran for them. A migration that replaced them
 * unconditionally would have silently stopped mirroring location for half the supported roles —
 * which is why this is an if/else and not a substitution, and why the else branch is asserted here
 * as hard as the migrated branch.
 *
 * THE FIVE BOUNDARIES G1f-6 WAS SCOPED TO
 * ---------------------------------------
 *   1. ONE component migrated — this one, and no other.
 *   2. The `user_type` gate PRESERVED verbatim (D-G1F-3 3-C). Seller and landlord records still
 *      never enter the canonical writer.
 *   3. The seller/landlord else branch PRESERVED verbatim — same three writes, same source.
 *   4. `zipCodes` stays UNMANAGED and property-sourced, matching the create sibling. The
 *      surface-scoped opt-in G1f-4 added for the Tenant OFFER copies is deliberately not adopted.
 *   5. NO new transaction. `update()` already opened one and every write site was already inside
 *      it; the writer owns its own. Nothing is widened and nothing is nested.
 *
 * `HasSearchAreas` is NOT retired. After this increment its save side has zero callers and is dead
 * code — removing it is the closeout increment, with its own authorization. Its load side is still
 * live, including in this very component.
 */
class G1f6MigrationBoundaryGuardTest extends TestCase
{
    private const CANONICAL_WRITE = "saveMeta('location_dna_preferences'";

    /** The component G1f-6 migrated — the only one it was authorized to touch. */
    private const TARGET = 'app/Http/Livewire/TenantAgentAuctionEdit.php';

    /** Its create sibling, whose shape G1f-6 was required to match. */
    private const SIBLING = 'app/Http/Livewire/TenantAgentAuction.php';

    /** All eight workflow components, now every one of them migrated. */
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
     * Files still permitted to write the canonical key directly, after G1f-6.
     *
     * UNCHANGED AT THREE, and that is correct rather than a miss. The target reached the canonical
     * key THROUGH `HasSearchAreas`, so migrating it removes a HOST, not a writer — the trait still
     * contains the write and therefore still counts, even though nothing calls it any more.
     * Shortening this list is the trait closeout plus D-G1F-5, not G1f-6.
     */
    private const AUTHORIZED_WRITERS = [
        'app/Http/Livewire/Concerns/HasSearchAreas.php',
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
    // 1 · THE TARGET IS WIRED
    // ═════════════════════════════════════════════════════════════════════════

    /** The migrated component reaches the canonical writer through the approved seam, once. */
    public function test_the_target_is_wired_to_the_canonical_writer_exactly_once(): void
    {
        $code = $this->codeOnly($this->read(self::TARGET));

        $this->assertStringContainsString('OwnerPrivateLocationDnaWriter', $code);
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
            'The bridged editor payload must be passed through as-is.'
        );
        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            $code,
            'and the trait save must be gone'
        );
        $this->assertStringNotContainsString(
            self::CANONICAL_WRITE,
            $code,
            'and the canonical key must not be written inline either'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE GATE, AND THE SELLER/LANDLORD BRANCH
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * D-G1F-3 3-C · the `user_type` gate is preserved and the writer sits behind it.
     *
     * The gate literal occurs several times in this 4,200-line component for unrelated guards, so a
     * whole-file search would still pass if the gate guarding the WRITER were widened to admit
     * seller and landlord. The assertion is therefore on the NEAREST gate preceding the writer
     * call — the same technique `G1fHireTenantUserTypeGateCharacterisationTest` established after
     * its own non-vacuity probe found exactly that hole.
     */
    public function test_the_user_type_gate_guards_the_writer_and_is_unchanged(): void
    {
        $code       = $this->codeOnly($this->read(self::TARGET));
        $writerCall = strpos($code, '$this->persistLocationDna($auction);');

        $this->assertNotFalse($writerCall, 'the writer call must exist');

        $before = substr($code, 0, $writerCall);
        $lastIf = strrpos($before, 'if (in_array($this->user_type,');

        $this->assertNotFalse(
            $lastIf,
            'the writer call must be preceded by a user_type gate'
        );

        $gateLine = substr($before, $lastIf, strpos($before, ')', $lastIf + 30) - $lastIf + 1);

        $this->assertStringContainsString(
            "['buyer', 'tenant']",
            $gateLine,
            'The gate immediately guarding the canonical writer must still admit buyer and tenant '
            .'only — D-G1F-3 3-C. Widening it would start writing canonical documents for seller '
            .'and landlord records, which G1f-6 is not authorized to do.'
        );
    }

    /**
     * The seller / landlord else branch is preserved, verbatim.
     *
     * THE assertion of this increment. Those two roles have no canonical document, so these three
     * property-sourced writes are their only location mirroring. They must survive, and they must
     * survive AFTER the writer call — i.e. in the else branch — rather than above the gate where
     * they used to run for all four roles.
     */
    public function test_the_seller_landlord_else_branch_is_preserved(): void
    {
        $code = $this->codeOnly($this->read(self::TARGET));

        $writerCall = strpos($code, '$this->persistLocationDna($auction);');

        foreach ([
            "\$auction->saveMeta('cities', json_encode(\$this->cities));",
            "\$auction->saveMeta('counties', json_encode(\$this->counties));",
            "\$auction->saveMeta('state', \$this->state);",
        ] as $write) {
            $position = strpos($code, $write);

            $this->assertNotFalse(
                $position,
                "The seller/landlord branch must keep `{$write}` — those roles have no canonical "
                .'document, so removing it stops mirroring location for them entirely.'
            );
            $this->assertGreaterThan(
                $writerCall,
                $position,
                "`{$write}` must sit in the ELSE branch, after the gated writer call. Above the "
                .'gate it would run for buyer and tenant too, reinstating the double-write.'
            );
            $this->assertSame(
                1,
                substr_count($code, $write),
                "`{$write}` must appear exactly once — a second copy would mean it was kept in "
                .'both branches.'
            );
        }

        $this->assertStringContainsString(
            '} else {',
            $code,
            'the migration must be an if/else, not a substitution'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · zipCodes STAYS UNMANAGED — THE HIRE FAMILY DOES NOT ADOPT G1f-4's OPT-IN
    // ═════════════════════════════════════════════════════════════════════════

    /** `zipCodes` is unchanged: ungated, property-sourced, unmanaged, same serialization. */
    public function test_zip_codes_remains_unmanaged_and_property_sourced(): void
    {
        $code = $this->codeOnly($this->read(self::TARGET));

        $this->assertStringContainsString(
            "\$auction->saveMeta('zipCodes', json_encode(\$this->zipCodes));",
            $code,
            'the property-sourced zipCodes mirror must be unchanged, serialization included'
        );
        $this->assertStringNotContainsString(
            'managingMirrors(',
            $code,
            'G1f-6 must use the DEFAULT managed mirror set. Naming an opt-in set here would make '
            .'this workflow derive zipCodes from canonical state — the Offer-family behaviour the '
            .'authorization explicitly declined.'
        );

        // It is OUTSIDE the gate, so it still runs for all four roles, exactly as before.
        $writerCall  = strpos($code, '$this->persistLocationDna($auction);');
        $zipPosition = strpos($code, "\$auction->saveMeta('zipCodes', json_encode(\$this->zipCodes));");

        $this->assertGreaterThan(
            $writerCall,
            $zipPosition,
            'zipCodes must remain AFTER the canonical block, so a failure in the canonical write '
            .'aborts before the mirror lands and the transaction stays all-or-nothing.'
        );
    }

    /** The default managed mirror set is unchanged — zipCodes did not become global. */
    public function test_the_default_managed_mirror_set_is_unchanged(): void
    {
        $this->assertStringContainsString(
            "MANAGED_KEYS = ['cities', 'counties', 'state']",
            $this->read('app/Services/LocationDna/Persistence/LegacyMirrorProjection.php'),
            'Making zipCodes global would make five already-migrated workflows start emitting a '
            .'mirror they have never written.'
        );
    }

    /** The migrated component matches its create sibling's treatment exactly. */
    public function test_the_target_matches_its_create_sibling(): void
    {
        $target  = $this->codeOnly($this->read(self::TARGET));
        $sibling = $this->codeOnly($this->read(self::SIBLING));

        foreach ([$target, $sibling] as $code) {
            $this->assertStringContainsString('new OwnerPrivateLocationDnaWriter()', $code);
            $this->assertStringNotContainsString('managingMirrors(', $code);
            $this->assertStringContainsString(
                "\$auction->saveMeta('zipCodes', json_encode(\$this->zipCodes));",
                $code
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · NO NEW TRANSACTION
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * `update()` keeps exactly the transaction it already had — none added, none widened.
     *
     * The component opened a transaction long before G1f, and every Location DNA write site was
     * already inside it. Adding another would nest silently and change failure semantics.
     */
    public function test_no_new_transaction_was_introduced(): void
    {
        $code = $this->codeOnly($this->read(self::TARGET));

        $this->assertSame(
            1,
            substr_count($code, 'DB::beginTransaction()'),
            'exactly the one pre-existing transaction'
        );
        $this->assertStringNotContainsString(
            'DB::transaction(',
            $code,
            'and no closure-style transaction added on top of it'
        );

        // The writer still owns its own, which is what makes canonical+mirrors atomic.
        $this->assertStringContainsString(
            'DB::transaction(',
            $this->read('app/Services/LocationDna/Persistence/LocationDnaPersistenceService.php')
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · THE WORKFLOW PHASE IS COMPLETE, AND THE TRAIT IS DEAD BUT PRESENT
    // ═════════════════════════════════════════════════════════════════════════

    /** All eight workflow components are wired, and nothing else is. */
    public function test_exactly_the_eight_workflow_components_are_wired(): void
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

        $this->assertSame($expected, array_values(array_unique($wired)));
        $this->assertCount(8, self::MIGRATED);
    }

    /**
     * `HasSearchAreas` is retained, unchanged, and its save side has ZERO callers.
     *
     * The closeout condition, asserted but deliberately not acted on. Both halves matter: deleting
     * the trait would fail the first, and any workflow reacquiring the old write path would fail
     * the second.
     */
    public function test_the_trait_is_retained_unchanged_with_a_dead_save_side(): void
    {
        $path = 'app/Http/Livewire/Concerns/HasSearchAreas.php';

        $this->assertFileExists(
            $this->root().'/'.$path,
            'HasSearchAreas must not be deleted — retiring it is the closeout increment.'
        );

        $trait = $this->read($path);

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
        $this->assertStringContainsString(
            "\$auction->saveMeta('location_dna_preferences', \$this->location_dna_preferences_json);",
            $trait,
            'and still contains the canonical write that keeps it in the §21 list'
        );

        $callers = [];
        $loaders = [];

        foreach ($this->productionFiles() as $relative) {
            if ($relative === $path) {
                continue;
            }

            $code = $this->codeOnly($this->read($relative));

            if (str_contains($code, '$this->saveSearchAreas(')) {
                $callers[] = $relative;
            }

            if (str_contains($code, '$this->loadSearchAreas(')) {
                $loaders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $callers,
            'saveSearchAreas() must have no callers after G1f-6. Found: '.implode(', ', $callers)
        );
        $this->assertNotEmpty(
            $loaders,
            'loadSearchAreas() must still have callers — the load side is why the trait is retained.'
        );
        $this->assertContains(
            self::TARGET,
            $loaders,
            'including the migrated component itself: G1f-6 moved the WRITE only.'
        );
    }

    /** The §21 direct-writer list is unchanged at three, and that is the correct outcome. */
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
        $this->assertCount(
            3,
            self::AUTHORIZED_WRITERS,
            'Unchanged at three: the target reached the key through the trait, so migrating it '
            .'removed a host rather than a writer.'
        );

        $this->assertContains('app/Http/Controllers/BuyerCriteriaAuctionController.php', self::AUTHORIZED_WRITERS);
        $this->assertContains('app/Http/Controllers/TenantCriteriaAuctionController.php', self::AUTHORIZED_WRITERS);
    }

    /** The legacy criteria controllers are untouched — D-G1F-5 governs them, not G1f-6. */
    public function test_the_legacy_criteria_controllers_are_untouched(): void
    {
        foreach ([
            'app/Http/Controllers/BuyerCriteriaAuctionController.php',
            'app/Http/Controllers/TenantCriteriaAuctionController.php',
        ] as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringContainsString(self::CANONICAL_WRITE, $code);
            $this->assertStringNotContainsString('LocationDna\\Persistence', $code);
        }
    }

    /** G1f-6 introduced no new production surface. */
    public function test_g1f6_introduced_no_new_production_surface(): void
    {
        $this->assertFileDoesNotExist(
            $this->root().'/app/Services/LocationDna/Persistence/LegacyMirrorAdapter.php'
        );
        $this->assertFileDoesNotExist($this->root().'/config/location_dna_persistence.php');
    }
}
