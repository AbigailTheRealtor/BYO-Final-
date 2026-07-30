<?php

namespace Tests\Unit\Services\LocationDna\Contract;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\InterpretationMode;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use App\Services\LocationDna\Contract\LocationDnaHydrator;
use App\Services\LocationDna\Contract\LocationDnaRevisionToken;
use PHPUnit\Framework\TestCase;

/**
 * G1c — LocationDnaRevisionToken (D-G1-3, option 3-C).
 *
 * Every approved semantic is asserted here. The two that most distinguish this token from the
 * Bridge cache key — which is deliberately NOT changed in this increment — are that polygon
 * vertex reordering DOES move the token, and that an explicit clear DOES move it. G1b proved
 * the Bridge key does neither.
 */
class LocationDnaRevisionTokenTest extends TestCase
{
    private LocationDnaRevisionToken $t;

    protected function setUp(): void
    {
        parent::setUp();
        $this->t = new LocationDnaRevisionToken();
    }

    private function doc(array $dimensions, array $extensions = []): LocationDnaDocument
    {
        return LocationDnaDocument::fromCanonical($dimensions, $extensions);
    }

    private function polygon(array $path, string $label = 'A'): array
    {
        return ['label' => $label, 'path' => $path];
    }

    // ── format ───────────────────────────────────────────────────────────────

    public function test_token_carries_an_algorithm_version_prefix(): void
    {
        $token = $this->t->forDocument($this->doc(['cities' => ['Tampa']]));

        $this->assertStringStartsWith('ldna-r1:', $token);
        $this->assertSame('ldna-r1', LocationDnaRevisionToken::ALGORITHM_PREFIX);
        $this->assertMatchesRegularExpression('/^ldna-r1:[0-9a-f]{64}$/', $token, 'sha256 hex digest');
    }

    // ── determinism ──────────────────────────────────────────────────────────

    public function test_equivalent_canonical_documents_produce_the_same_token(): void
    {
        $a = $this->doc(['cities' => ['Tampa'], 'state' => 'FL']);
        $b = $this->doc(['state' => 'FL', 'cities' => ['Tampa']]);

        $this->assertSame($this->t->forDocument($a), $this->t->forDocument($b));
    }

    public function test_associative_key_order_has_no_effect(): void
    {
        $a = $this->doc(['polygons' => [$this->polygon([['lat' => 27.9, 'lng' => -82.4]])]]);
        $b = $this->doc(['polygons' => [['path' => [['lng' => -82.4, 'lat' => 27.9]], 'label' => 'A']]]);

        $this->assertSame($this->t->forDocument($a), $this->t->forDocument($b));
    }

    public function test_repeat_calls_are_stable(): void
    {
        $doc = $this->doc(['cities' => ['Tampa']]);

        $this->assertSame($this->t->forDocument($doc), $this->t->forDocument($doc));
    }

    // ── collection order: NOT meaningful ─────────────────────────────────────

    public function test_polygon_collection_reordering_has_no_effect(): void
    {
        $p1 = $this->polygon([['lat' => 28.0, 'lng' => -82.0]], 'North');
        $p2 = $this->polygon([['lat' => 27.0, 'lng' => -82.0]], 'South');

        // Collection order is normalised upstream, so both orders reach the token identically.
        $n = new \App\Services\LocationDna\Contract\LocationDnaNormalizer();

        $a = $this->doc(['polygons' => $n->normalize(Dimension::Polygons, [$p1, $p2])]);
        $b = $this->doc(['polygons' => $n->normalize(Dimension::Polygons, [$p2, $p1])]);

        $this->assertSame($this->t->forDocument($a), $this->t->forDocument($b));
    }

    public function test_radius_collection_reordering_has_no_effect(): void
    {
        $r1 = ['lat' => 27.1, 'lng' => -82.1, 'radius_miles' => 1.0];
        $r2 = ['lat' => 28.2, 'lng' => -83.2, 'radius_miles' => 2.0];

        $n = new \App\Services\LocationDna\Contract\LocationDnaNormalizer();

        $a = $this->doc(['radius_searches' => $n->normalize(Dimension::RadiusSearches, [$r1, $r2])]);
        $b = $this->doc(['radius_searches' => $n->normalize(Dimension::RadiusSearches, [$r2, $r1])]);

        $this->assertSame($this->t->forDocument($a), $this->t->forDocument($b));
    }

    // ── vertex order: MEANINGFUL ─────────────────────────────────────────────

    public function test_polygon_vertex_reordering_changes_the_token(): void
    {
        $forward = $this->doc(['polygons' => [$this->polygon([
            ['lat' => 27.90, 'lng' => -82.40],
            ['lat' => 27.95, 'lng' => -82.45],
            ['lat' => 27.99, 'lng' => -82.49],
        ])]]);

        $shuffled = $this->doc(['polygons' => [$this->polygon([
            ['lat' => 27.99, 'lng' => -82.49],
            ['lat' => 27.90, 'lng' => -82.40],
            ['lat' => 27.95, 'lng' => -82.45],
        ])]]);

        $this->assertNotSame(
            $this->t->forDocument($forward),
            $this->t->forDocument($shuffled),
            'vertex order is semantically meaningful: two distinct shapes must not share a token',
        );
    }

    // ── value changes move the token ─────────────────────────────────────────

    public function test_city_change_changes_the_token(): void
    {
        $this->assertNotSame(
            $this->t->forDocument($this->doc(['cities' => ['Tampa']])),
            $this->t->forDocument($this->doc(['cities' => ['Orlando']])),
        );
    }

    public function test_county_change_changes_the_token(): void
    {
        $this->assertNotSame(
            $this->t->forDocument($this->doc(['counties' => ['Pinellas']])),
            $this->t->forDocument($this->doc(['counties' => ['Hillsborough']])),
        );
    }

    public function test_state_change_changes_the_token(): void
    {
        $this->assertNotSame(
            $this->t->forDocument($this->doc(['state' => 'FL'])),
            $this->t->forDocument($this->doc(['state' => 'GA'])),
        );
    }

    public function test_geometry_change_changes_the_token(): void
    {
        $this->assertNotSame(
            $this->t->forDocument($this->doc(['polygons' => [$this->polygon([['lat' => 27.9, 'lng' => -82.4]])]])),
            $this->t->forDocument($this->doc(['polygons' => [$this->polygon([['lat' => 40.7, 'lng' => -74.0]])]])),
        );
    }

    public function test_location_notes_change_changes_the_token(): void
    {
        $this->assertNotSame(
            $this->t->forDocument($this->doc(['location_notes' => 'Quiet street.'])),
            $this->t->forDocument($this->doc(['location_notes' => 'Busy street.'])),
        );
    }

    // ── clearing moves the token; omission does not ──────────────────────────

    public function test_explicit_geometry_clear_changes_the_token(): void
    {
        $authored = $this->doc(['polygons' => [$this->polygon([['lat' => 27.9, 'lng' => -82.4]])]]);
        $cleared  = $authored->withClearedDimension(Dimension::Polygons);

        $this->assertNotSame(
            $this->t->forDocument($authored),
            $this->t->forDocument($cleared),
            'a deliberate clear is a real change and must move the token',
        );
    }

    public function test_cleared_and_absent_produce_different_tokens(): void
    {
        $absent  = $this->doc([]);
        $cleared = $absent->withClearedDimension(Dimension::Cities);

        $this->assertNotSame(
            $this->t->forDocument($absent),
            $this->t->forDocument($cleared),
            'presence is part of canonical meaning',
        );
    }

    public function test_no_operation_does_not_change_the_token(): void
    {
        $doc     = $this->doc(['cities' => ['Tampa'], 'polygons' => []]);
        $applier = new \App\Services\LocationDna\Contract\DimensionCommandApplier();

        $this->assertSame(
            $this->t->forDocument($doc),
            $this->t->forDocument($applier->apply($doc, [])),
        );
    }

    // ── schema_version / lazy upgrade ────────────────────────────────────────

    public function test_interpretation_neutral_lazy_upgrade_does_not_change_the_token(): void
    {
        $legacy   = $this->doc(['cities' => ['Tampa']]);
        $legacy   = LocationDnaDocument::fromCanonical(
            $legacy->toDimensionArray(), [], InterpretationMode::Legacy, null,
        );
        $upgraded = $legacy->withLazyUpgrade();

        $this->assertNull($legacy->schemaVersion());
        $this->assertSame(2, $upgraded->schemaVersion());
        $this->assertSame(
            $this->t->forDocument($legacy),
            $this->t->forDocument($upgraded),
            'stamping a version while changing no interpreted value must not move the token (D-G1-3)',
        );
    }

    public function test_an_interpretation_changing_version_moves_the_token_through_its_values(): void
    {
        // D-G1-3's two clauses are reconciled by tokenising the INTERPRETED values only: if a
        // version change genuinely alters interpretation, the interpreted values differ, and the
        // token differs with them. Demonstrated here by the difference a changed interpretation
        // would produce — an absent dimension versus one interpreted as cleared.
        $hydrator = new LocationDnaHydrator();

        $indeterminate = $hydrator->hydrate(['cities' => ['Tampa']])->documentOrFail();
        $authoritative = $hydrator->hydrate(['schema_version' => 2, 'cities' => ['Tampa'], 'counties' => []])->documentOrFail();

        $this->assertNotSame(
            $this->t->forDocument($indeterminate),
            $this->t->forDocument($authoritative),
            'differing interpreted presence sets yield differing tokens',
        );
    }

    // ── extensions ───────────────────────────────────────────────────────────

    public function test_extension_change_changes_the_token_deterministically(): void
    {
        $a = $this->doc(['cities' => ['Tampa']], ['future_thing' => ['x']]);
        $b = $this->doc(['cities' => ['Tampa']], ['future_thing' => ['y']]);
        $c = $this->doc(['cities' => ['Tampa']], ['future_thing' => ['x']]);

        $this->assertNotSame($this->t->forDocument($a), $this->t->forDocument($b));
        $this->assertSame($this->t->forDocument($a), $this->t->forDocument($c));
    }

    // ── per-dimension scope ──────────────────────────────────────────────────

    public function test_per_dimension_tokens_scope_a_conflict_to_the_diverged_dimension(): void
    {
        $before = $this->doc(['cities' => ['Tampa'], 'state' => 'FL']);
        $after  = $before->withDimension(Dimension::Cities, ['Orlando']);

        $this->assertNotSame(
            $this->t->forDimension($before, Dimension::Cities),
            $this->t->forDimension($after, Dimension::Cities),
            'the edited dimension diverges',
        );
        $this->assertSame(
            $this->t->forDimension($before, Dimension::State),
            $this->t->forDimension($after, Dimension::State),
            'an untouched dimension does not, so concurrent edits to different dimensions do not conflict',
        );
    }

    public function test_per_dimension_token_distinguishes_absent_from_cleared(): void
    {
        $absent  = $this->doc([]);
        $cleared = $absent->withClearedDimension(Dimension::Cities);

        $this->assertNotSame(
            $this->t->forDimension($absent, Dimension::Cities),
            $this->t->forDimension($cleared, Dimension::Cities),
        );
    }

    // ── malformed cannot be tokenised; no mutation ───────────────────────────

    public function test_a_malformed_document_cannot_be_tokenised(): void
    {
        // There is no path from malformed input to a document: the hydrator returns a Malformed
        // outcome carrying no document, so the token has nothing to operate on. That is the
        // structural guarantee, asserted rather than asserted-by-exception.
        $result = (new LocationDnaHydrator())->hydrate('{"cities": ["Tampa"');

        $this->assertTrue($result->isMalformed());
        $this->assertNull($result->document(), 'no document exists, so no token can be computed');
    }

    public function test_tokenising_does_not_mutate_the_document(): void
    {
        $doc = $this->doc(['polygons' => [$this->polygon([
            ['lat' => 27.99, 'lng' => -82.49],
            ['lat' => 27.90, 'lng' => -82.40],
        ])]]);

        $before = $doc->toDimensionArray();
        $this->t->forDocument($doc);
        $this->t->forDimension($doc, Dimension::Polygons);

        $this->assertSame($before, $doc->toDimensionArray(), 'vertex order and values unchanged');
        $this->assertSame(27.99, $doc->value(Dimension::Polygons)[0]['path'][0]['lat']);
    }
}
