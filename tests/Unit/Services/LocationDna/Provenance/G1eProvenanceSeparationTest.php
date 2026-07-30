<?php

namespace Tests\Unit\Services\LocationDna\Provenance;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use App\Services\LocationDna\Contract\LocationDnaRevisionToken;
use App\Services\LocationDna\Provenance\DimensionProvenance;
use App\Services\LocationDna\Provenance\LocationDnaProvenanceKind as Kind;
use App\Services\LocationDna\Provenance\LocationDnaProvenanceMap as Map;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * G1e — separation: provenance grants no capability and changes no revision token.
 *
 * Two boundaries are asserted here because both are easy to erode by accident. Principle 8:
 * provenance must not imply read, edit, exposure, snapshot or repair permission — capability stays
 * G1d's. Principle 9: the G1c revision token represents interpreted VALUES, not provenance
 * metadata, and `LocationDnaRevisionToken` is not modified in this phase.
 */
class G1eProvenanceSeparationTest extends TestCase
{
    /** @return list<string> raw sources */
    private function provenanceSources(): array
    {
        $dir   = dirname(__DIR__, 5).'/app/Services/LocationDna/Provenance';
        $files = glob($dir.'/*.php') ?: [];

        return array_map(static fn (string $f): string => (string) file_get_contents($f), $files);
    }

    /**
     * @return list<string> sources with comment lines stripped
     *
     * The docblocks in this namespace deliberately NAME the capability layer in order to state that
     * it is excluded — "capability is G1d's and only G1d's". A raw match would therefore fail on the
     * very prose that documents the boundary, so code lines alone are compared.
     */
    private function provenanceCodeOnly(): array
    {
        return array_map(static function (string $source): string {
            $out = [];

            foreach (preg_split('/\R/', $source) ?: [] as $line) {
                $t = ltrim($line);

                if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*')
                    || str_starts_with($t, '/*') || str_starts_with($t, '#')) {
                    continue;
                }

                $out[] = $line;
            }

            return implode("\n", $out);
        }, $this->provenanceSources());
    }

    // ── capability separation ────────────────────────────────────────────────

    public function test_no_provenance_type_exposes_a_capability_style_method(): void
    {
        // A provenance object answers "where did this come from", never "may you do X".
        // `allowsTransition` is deliberately NOT banned: transition legality is this layer's own
        // question. What must not exist is a method answering "may this actor SEE or CHANGE the
        // value" — that is capability, and it belongs to G1d.
        $banned = [
            'mayExpose', 'mayEdit', 'mayRead', 'mayClear', 'maySet',
            'canExpose', 'canEdit', 'canRead', 'grant', 'permit', 'authorize', 'authorise',
        ];

        foreach ([
            Kind::class,
            \App\Services\LocationDna\Provenance\ProvenanceAuthority::class,
            \App\Services\LocationDna\Provenance\ProvenanceActor::class,
            DimensionProvenance::class,
            Map::class,
            \App\Services\LocationDna\Provenance\ProvenanceTransition::class,
        ] as $class) {
            foreach ((new ReflectionClass($class))->getMethods() as $method) {
                foreach ($banned as $needle) {
                    $this->assertStringNotContainsString(
                        strtolower($needle),
                        strtolower($method->getName()),
                        "{$class}::{$method->getName()} looks like a capability decision",
                    );
                }
            }
        }
    }

    public function test_owner_authored_does_not_itself_grant_geometry_exposure_or_edit(): void
    {
        $record = DimensionProvenance::ownerAuthored(Dimension::Polygons);

        // It reports authority, and nothing more. There is no exposure or edit answer to obtain.
        $this->assertTrue($record->isAuthoritative());
        $this->assertFalse(method_exists($record, 'mayExpose'));
        $this->assertFalse(method_exists($record, 'mayEdit'));
    }

    public function test_snapshot_provenance_does_not_grant_snapshot_access(): void
    {
        $record = DimensionProvenance::of(Dimension::Polygons, Kind::SnapshotRetained);

        $this->assertFalse($record->isAuthoritative());
        $this->assertFalse($record->authority()->mayBeAutomaticRestorationSource());
        $this->assertFalse(method_exists($record, 'mayReadSnapshot'));
    }

    public function test_legacy_repaired_does_not_grant_mirror_repair_capability(): void
    {
        $record = DimensionProvenance::of(Dimension::Cities, Kind::LegacyRepaired);

        // Recording that a repair happened is not permission to perform one.
        $this->assertFalse($record->isAuthoritative());
        $this->assertFalse(method_exists($record, 'mayRepair'));
    }

    public function test_derived_provenance_does_not_grant_public_exposure(): void
    {
        $record = DimensionProvenance::of(Dimension::Cities, Kind::Derived);

        $this->assertFalse($record->isAuthoritative());
        $this->assertFalse(method_exists($record, 'mayExposePublicly'));
    }

    public function test_the_provenance_namespace_never_invokes_the_capability_layer(): void
    {
        foreach ($this->provenanceCodeOnly() as $source) {
            $this->assertStringNotContainsString('LocationDnaCapabilityResolver', $source);
            $this->assertStringNotContainsString('LocationDna\\Capability', $source);
            $this->assertStringNotContainsString('LocationDnaCapabilitySet', $source);
        }
    }

    public function test_the_provenance_namespace_imports_no_authorization_types(): void
    {
        foreach ($this->provenanceSources() as $source) {
            preg_match_all('/^use\s+([^;]+);/m', $source, $m);

            foreach ($m[1] ?? [] as $import) {
                $this->assertContains(
                    trim($import),
                    ['App\\Services\\LocationDna\\Contract\\Dimension', 'RuntimeException'],
                    "unexpected import: {$import}",
                );
            }
        }
    }

    // ── revision-token separation ────────────────────────────────────────────

    public function test_provenance_changes_alone_do_not_affect_the_g1c_revision_token(): void
    {
        $document = LocationDnaDocument::fromCanonical([
            'cities'   => ['Tampa'],
            'polygons' => [['label' => 'A', 'path' => [['lat' => 27.9, 'lng' => -82.4]]]],
        ]);

        $token  = new LocationDnaRevisionToken();
        $before = $token->forDocument($document);

        // Build wildly different provenance for the same document. The token must not move, because
        // it hashes interpreted values, not metadata.
        $authored = Map::fromKinds(['cities' => Kind::OwnerAuthored, 'polygons' => Kind::OwnerAuthored]);
        $derived  = Map::fromKinds(['cities' => Kind::Derived, 'polygons' => Kind::SnapshotRetained]);

        $this->assertNotSame($authored->toInternalArray(), $derived->toInternalArray(), 'provenance differs');
        $this->assertSame($before, $token->forDocument($document), 'the token must be unaffected');
        $this->assertSame($before, $token->forDocument($document));
    }

    public function test_the_revision_token_takes_no_provenance_argument(): void
    {
        foreach (['forDocument', 'forDimension'] as $method) {
            $params = (new \ReflectionMethod(LocationDnaRevisionToken::class, $method))->getParameters();

            foreach ($params as $param) {
                $this->assertStringNotContainsString(
                    'Provenance',
                    (string) $param->getType(),
                    "{$method} must not accept provenance",
                );
            }
        }
    }

    public function test_the_revision_token_source_does_not_mention_provenance(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 5).'/app/Services/LocationDna/Contract/LocationDnaRevisionToken.php',
        );

        $this->assertStringNotContainsString('Provenance', $source, 'G1c token must be unchanged by G1e');
    }

    // ── sensitivity is recorded, not authorised ──────────────────────────────

    public function test_location_notes_provenance_is_representable_but_authorises_nothing(): void
    {
        $map = Map::fromKinds(['location_notes' => Kind::OwnerAuthored]);

        $this->assertTrue($map->isAuthoritative(Dimension::LocationNotes), 'origin is recorded');
        $this->assertFalse(method_exists($map, 'mayExposeNotes'), 'exposure is not this layer');
    }

    public function test_geometry_provenance_is_representable_but_authorises_nothing(): void
    {
        $map = Map::fromKinds(['polygons' => Kind::OwnerAuthored]);

        $this->assertTrue($map->isAuthoritative(Dimension::Polygons));
        $this->assertFalse(method_exists($map, 'mayExposeGeometry'));
    }

    // ── purity ───────────────────────────────────────────────────────────────

    public function test_the_provenance_layer_needs_no_framework_boot(): void
    {
        // No container, no app, no DB. Construction alone proves the dependency shape.
        $this->assertInstanceOf(Map::class, Map::fromKinds(['cities' => Kind::OwnerAuthored]));
    }
}
