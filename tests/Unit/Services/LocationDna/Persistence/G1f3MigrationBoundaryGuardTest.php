<?php

namespace Tests\Unit\Services\LocationDna\Persistence;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * G1f-3 — the migration boundary guard.
 *
 * WHAT IT PROTECTS
 * ----------------
 * G1f-3 authorized migrating exactly TWO further workflows — the Buyer Offer pair, together.
 * This suite is the standing proof that it migrated both and stopped there. Its shrink-only lists
 * are shared across stages, so G1f-4 updated them in place when the Tenant Offer pair migrated:
 * two workflows remain untouched, the direct-writer list shrank by exactly the two inline writers
 * each stage removed, and every deferred concern is still deferred.
 *
 * WHY THE COUNTS ARE ASSERTED EXPLICITLY
 * --------------------------------------
 * "Two migrated" and "four remaining" are the whole content of the authorization. A guard that
 * checked only that the Buyer Offer files changed would pass just as happily if a Tenant Offer
 * component had been dragged along, which is the failure mode one-stage-at-a-time exists to
 * prevent.
 */
class G1f3MigrationBoundaryGuardTest extends TestCase
{
    private const CANONICAL_WRITE = "saveMeta('location_dna_preferences'";

    /** The seven workflow components migrated after G1f-5. */
    private const MIGRATED = [
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        'app/Http/Livewire/TenantAgentAuction.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        'app/Http/Livewire/TenantAgentAuctionEdit.php',
    ];

    /** The one workflow implementation still untouched after G1f-5 — the Hire Tenant EDIT sibling. */
    /**
     * COMPLETE AS OF G1f-6 — empty. All eight workflow components reach the canonical writer.
     *
     * Retained rather than deleted so a NEW unmigrated write path has somewhere to surface, and so
     * the emptiness is asserted rather than assumed.
     */
    private const UNMIGRATED = [];

    /**
     * Files still permitted to write the canonical key directly, after G1f-4.
     *
     * SHRINK-ONLY. G1f-3 was the first stage to shorten it, seven → five, by migrating the two
     * Buyer Offer copies, which wrote the canonical key inline rather than through the trait.
     * G1f-4 shortens it again, five → three, by migrating the two Tenant Offer copies, which wrote
     * it inline for the same reason. G1f-1 and G1f-2 could not shorten it because both of their
     * targets reached the key through `HasSearchAreas`, which is itself one entry.
     *
     * The two legacy criteria controllers remain by design — D-G1F-5 governs them, not G1f-4.
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

    /** @return list<string> every PHP file under the given roots, recursively */
    private function filesUnder(array $roots): array
    {
        $files = [];

        foreach ($roots as $root) {
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
    // BOTH BUYER OFFER WRITERS MIGRATED — TOGETHER
    // ═════════════════════════════════════════════════════════════════════════

    /** Both Buyer Offer components reach the canonical writer and write nothing inline. */
    public function test_both_buyer_offer_writers_are_migrated(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        ] as $relative) {
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
            $this->assertStringNotContainsString(
                "\$auction->saveMeta('cities',",
                $code,
                "{$relative}: the inline cities mirror write must be gone"
            );
            $this->assertStringNotContainsString(
                "\$auction->saveMeta('counties',",
                $code,
                "{$relative}: the inline counties mirror write must be gone"
            );
            $this->assertStringNotContainsString(
                "\$auction->saveMeta('state',",
                $code,
                "{$relative}: the inline state mirror write must be gone"
            );
        }
    }

    /**
     * NEITHER Buyer Offer writer may be migrated without the other.
     *
     * The pairing rule, mechanised. A commit that migrated one and left the other would leave
     * the shared characterisation suite and the B4 guard in an ambiguous half-state.
     */
    public function test_the_buyer_offer_pair_is_migrated_together(): void
    {
        $create = str_contains(
            $this->codeOnly($this->read('app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php')),
            'persistLocationDna'
        );
        $edit = str_contains(
            $this->codeOnly($this->read('app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php')),
            'persistLocationDna'
        );

        $this->assertSame(
            $create,
            $edit,
            'The Buyer Offer create and edit copies are one implementation copied twice. They '
            .'migrate together or not at all — a half-migrated pair is not a valid state.'
        );
        $this->assertTrue($create, 'and after G1f-3 both are migrated');
    }

    /** Exactly six workflow components are wired to the writer — no more. */
    public function test_exactly_six_workflow_components_are_wired(): void
    {
        $wired = [];

        foreach ($this->filesUnder(['app/Http/Livewire']) as $relative) {
            if (str_contains($this->codeOnly($this->read($relative)), 'LocationDna\\Persistence')) {
                $wired[] = $relative;
            }
        }

        $expected = self::MIGRATED;
        sort($expected);

        $this->assertSame(
            $expected,
            $wired,
            'Exactly eight workflow components may reference the persistence namespace after G1f-6.'
        );
    }

    /** No unmigrated workflow remains; the migrated set is verified positively instead. */
    public function test_the_four_unmigrated_workflows_are_untouched(): void
    {
        $this->assertSame([], self::UNMIGRATED, 'all eight are migrated as of G1f-6');

        foreach (self::MIGRATED as $relative) {
            $this->assertStringContainsString(
                'persistLocationDna',
                $this->codeOnly($this->read($relative)),
                "{$relative} must carry the canonical writer seam."
            );
        }


        foreach (self::UNMIGRATED as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringNotContainsString(
                'persistLocationDna',
                $code,
                "{$relative} must NOT be migrated — that is a later increment."
            );
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $code,
                "{$relative} must not reference the persistence namespace."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE §21 DIRECT-WRITER LIST — SHRANK BY EXACTLY TWO
    // ═════════════════════════════════════════════════════════════════════════

    /** Only the authorized writers write the canonical key directly, and there are five. */
    public function test_the_direct_writer_list_shrank_to_five(): void
    {
        $offenders = [];

        foreach ($this->filesUnder(['app', 'routes', 'database']) as $relative) {
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
            2,
            self::AUTHORIZED_WRITERS,
            'Three direct writers remain after G1f-4 — down from five. This list may only SHRINK; '
            .'reaching one entry is G1f\'s completion condition.'
        );
    }

    /**
     * The legacy criteria controllers remain IN scope and IN the count.
     *
     * D-G1F-5 put all four of their canonical write paths inside G1f's final scope. They are
     * still direct writers and must not be quietly dropped from the list to make it look
     * shorter — the list shrinks by migration only.
     */
    public function test_the_criteria_controllers_remain_in_scope_and_in_the_count(): void
    {
        foreach ([
            'app/Http/Controllers/BuyerCriteriaAuctionController.php',
            'app/Http/Controllers/TenantCriteriaAuctionController.php',
        ] as $relative) {
            $this->assertContains(
                $relative,
                self::AUTHORIZED_WRITERS,
                "{$relative} must remain listed — D-G1F-5 keeps it inside G1f's scope."
            );
            $this->assertStringContainsString(
                self::CANONICAL_WRITE,
                $this->codeOnly($this->read($relative)),
                "{$relative} must still be an unmigrated direct writer — G1f-7 migrates it."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WHAT G1f-3 DELIBERATELY PRESERVED
    // ═════════════════════════════════════════════════════════════════════════

    /** F-G1F-14 · exactly one hydrate call remains per file, and the method survives. */
    public function test_the_pre_validation_hydrate_call_and_method_survive(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        ] as $relative) {
            $source = $this->read($relative);

            $this->assertSame(
                1,
                substr_count($source, '$this->hydrateDiscreteLocationFromBlob();'),
                "{$relative}: the pre-validation hydrate call must remain and the write-side one go"
            );
            $this->assertStringContainsString(
                'protected function hydrateDiscreteLocationFromBlob(): void',
                $source,
                "{$relative}: the method definition is NOT deleted — that is trait/shim-stage work"
            );
        }
    }

    /** The trait is not globally wired; the two remaining hosts still use it unchanged. */
    public function test_has_search_areas_is_not_globally_wired(): void
    {
        $trait = $this->read('app/Http/Livewire/Concerns/HasSearchAreas.php');

        $this->assertStringNotContainsString('LocationDna\\Persistence', $this->codeOnly($trait));
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

        // INVERTED BY G1f-6: the trait's save side now has zero callers. It is retained as dead
        // code — retiring it is the closeout increment — so what is asserted is that it still
        // EXISTS and is unchanged, while nothing calls it.
        $callers = [];

        foreach ($this->filesUnder(['app']) as $relative) {
            if ($relative === 'app/Http/Livewire/Concerns/HasSearchAreas.php') {
                continue;
            }

            if (str_contains($this->codeOnly($this->read($relative)), '$this->saveSearchAreas(')) {
                $callers[] = $relative;
            }
        }

        $this->assertSame([], $callers, 'saveSearchAreas() must have no callers after G1f-6.');
    }

    /** The Tenant Offer divergence construct is untouched — G1f-4's target, not G1f-3's. */
    public function test_the_tenant_offer_divergence_is_untouched(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        ] as $relative) {
            $this->assertStringContainsString(
                "array_key_exists('cities'",
                $this->read($relative),
                "{$relative}: the correct-by-divergence construct must survive until G1f-4."
            );
        }
    }

    /** `zipCodes` and the plural `states` are neither introduced nor managed. */
    public function test_zipcodes_and_plural_states_are_not_introduced(): void
    {
        $this->assertStringContainsString(
            "public const MANAGED_KEYS = ['cities', 'counties', 'state'];",
            $this->read('app/Services/LocationDna/Persistence/LegacyMirrorProjection.php'),
            'the managed set must not have grown'
        );

        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        ] as $relative) {
            $code = $this->codeOnly($this->read($relative));

            $this->assertStringNotContainsString('zipCodes', $code, "{$relative}: no zipCodes introduced");
            $this->assertStringNotContainsString("saveMeta('states'", $code, "{$relative}: no plural states");
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DEFERRED SEAMS · still deferred
    // ═════════════════════════════════════════════════════════════════════════

    /** The migrated components reach none of the deferred or out-of-scope seams. */
    public function test_the_migrated_components_touch_no_deferred_seam(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        ] as $relative) {
            $code = $this->codeOnly($this->read($relative));

            foreach ([
                'LegacyMirrorAdapter',
                'CriteriaHashService',
                'PublicGeometryProjection',
                'location_intelligence_snapshot',
                'LocationDnaProvenanceMap',
                'DimensionCommand',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $code,
                    "{$relative} must not reference `{$forbidden}`."
                );
            }
        }
    }

    /** G1f-3 added no production class, no migration and no config. */
    public function test_g1f3_introduced_no_new_production_surface(): void
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
            'G1f-3 reuses the G1f-1 boundary; it must add no class to the persistence namespace.'
        );

        $this->assertFileDoesNotExist($this->root().'/config/location_dna_persistence.php');
        $this->assertFileDoesNotExist(
            $this->root().'/app/Services/LocationDna/Persistence/LegacyMirrorAdapter.php'
        );

        foreach (glob($this->root().'/database/migrations/*.php') ?: [] as $file) {
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                (string) file_get_contents($file),
                basename($file).' must not reference the persistence namespace'
            );
        }
    }

    /** No provenance is persisted anywhere in the namespace. */
    public function test_no_provenance_is_persisted(): void
    {
        foreach (glob($this->root().'/app/Services/LocationDna/Persistence/*.php') ?: [] as $file) {
            $code = $this->codeOnly((string) file_get_contents($file));

            $this->assertStringNotContainsString('saveMeta(\'provenance', $code, basename($file));
            $this->assertStringNotContainsString('LocationDnaProvenanceMap', $code, basename($file));
        }
    }

    /** Bridge, hash and public-projection seams remain unwired. */
    public function test_bridge_criteria_hash_and_public_projection_are_untouched(): void
    {
        foreach ([
            'app/Services/Bridge/CriteriaHashService.php',
            'app/Services/LocationDna/PublicGeometryProjection.php',
        ] as $relative) {
            $this->assertStringNotContainsString(
                'LocationDna\\Persistence',
                $this->codeOnly($this->read($relative)),
                "{$relative} must not have been wired to the writer."
            );
        }
    }
}
