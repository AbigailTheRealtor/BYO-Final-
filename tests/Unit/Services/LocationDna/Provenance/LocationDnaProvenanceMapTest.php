<?php

namespace Tests\Unit\Services\LocationDna\Provenance;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Provenance\LocationDnaProvenanceException;
use App\Services\LocationDna\Provenance\LocationDnaProvenanceKind as Kind;
use App\Services\LocationDna\Provenance\LocationDnaProvenanceMap as Map;
use App\Services\LocationDna\Provenance\ProvenanceActor as Actor;
use PHPUnit\Framework\TestCase;

/**
 * G1e — the provenance map: immutable, sparse, per dimension.
 *
 * Sparseness is the load-bearing property. An ABSENT dimension has no provenance entry, because
 * absence is not an origin — which is how this model stays compatible with the G1c three states
 * without becoming the `dimension_meta` structure settled decision 6 forbids.
 */
class LocationDnaProvenanceMapTest extends TestCase
{
    // ── per-dimension independence ───────────────────────────────────────────

    public function test_each_dimension_may_carry_a_different_kind(): void
    {
        $map = Map::fromKinds([
            'cities'          => Kind::OwnerAuthored,
            'counties'        => Kind::LegacyFallback,
            'polygons'        => Kind::OwnerCleared,
            'location_notes'  => Kind::Derived,
            'radius_searches' => Kind::Inherited,
        ]);

        $this->assertSame(Kind::OwnerAuthored, $map->kindFor(Dimension::Cities));
        $this->assertSame(Kind::LegacyFallback, $map->kindFor(Dimension::Counties));
        $this->assertSame(Kind::OwnerCleared, $map->kindFor(Dimension::Polygons));
        $this->assertSame(Kind::Derived, $map->kindFor(Dimension::LocationNotes));
        $this->assertSame(Kind::Inherited, $map->kindFor(Dimension::RadiusSearches));
    }

    public function test_one_dimensions_provenance_does_not_affect_another(): void
    {
        $before = Map::fromKinds(['cities' => Kind::OwnerAuthored, 'counties' => Kind::OwnerAuthored]);
        $after  = $before->with(Dimension::Cities, Kind::OwnerCleared);

        $this->assertSame(Kind::OwnerCleared, $after->kindFor(Dimension::Cities));
        $this->assertSame(Kind::OwnerAuthored, $after->kindFor(Dimension::Counties), 'counties untouched');
    }

    public function test_an_explicit_clear_on_cities_does_not_clear_counties_provenance(): void
    {
        $map = Map::fromKinds(['cities' => Kind::OwnerAuthored, 'counties' => Kind::OwnerAuthored])
            ->withTransition(Dimension::Cities, Kind::OwnerCleared, Actor::ExplicitOwner);

        $this->assertTrue($map->blocksFallbackResurrection(Dimension::Cities));
        $this->assertFalse($map->blocksFallbackResurrection(Dimension::Counties));
        $this->assertSame(Kind::OwnerAuthored, $map->kindFor(Dimension::Counties));
    }

    public function test_geometry_provenance_is_independent_of_administrative_label_provenance(): void
    {
        $map = Map::fromKinds([
            'polygons' => Kind::OwnerAuthored,   // user-drawn
            'cities'   => Kind::Derived,          // computed label
        ]);

        $this->assertTrue($map->isAuthoritative(Dimension::Polygons));
        $this->assertFalse($map->isAuthoritative(Dimension::Cities));
    }

    public function test_location_notes_provenance_can_be_represented(): void
    {
        $map = Map::fromKinds(['location_notes' => Kind::OwnerAuthored]);

        $this->assertSame(Kind::OwnerAuthored, $map->kindFor(Dimension::LocationNotes));
        $this->assertTrue($map->isAuthoritative(Dimension::LocationNotes));
    }

    // ── sparse: absent has no provenance ─────────────────────────────────────

    public function test_an_absent_dimension_has_no_provenance_entry(): void
    {
        $map = Map::fromKinds(['cities' => Kind::OwnerAuthored]);

        $this->assertNull($map->kindFor(Dimension::State), 'absence is not an origin');
        $this->assertNull($map->for(Dimension::State));
        $this->assertNull($map->authorityFor(Dimension::State), 'null is not an authority');
        $this->assertFalse($map->has(Dimension::State));
        $this->assertFalse($map->isAuthoritative(Dimension::State));
    }

    public function test_not_every_dimension_needs_an_entry(): void
    {
        $map = Map::fromKinds(['cities' => Kind::OwnerAuthored]);

        $this->assertSame(['cities'], $map->recordedDimensionKeys());
        $this->assertCount(9, Dimension::all(), 'nine dimensions exist; one is recorded');
    }

    public function test_an_empty_map_is_representable(): void
    {
        $this->assertTrue(Map::empty()->isEmpty());
        $this->assertSame([], Map::empty()->recordedDimensionKeys());
    }

    // ── three-state compatibility ────────────────────────────────────────────

    public function test_cleared_is_distinct_from_absent(): void
    {
        $cleared = Map::empty()->with(Dimension::Cities, Kind::OwnerCleared);

        $this->assertTrue($cleared->has(Dimension::Cities), 'cleared has an entry');
        $this->assertFalse(Map::empty()->has(Dimension::Cities), 'absent has none');
        $this->assertNotSame($cleared->recordedDimensionKeys(), Map::empty()->recordedDimensionKeys());
    }

    public function test_cleared_is_distinct_from_an_authored_value(): void
    {
        $this->assertNotSame(Kind::OwnerCleared, Kind::OwnerAuthored);

        $a = Map::empty()->with(Dimension::Cities, Kind::OwnerCleared);
        $b = Map::empty()->with(Dimension::Cities, Kind::OwnerAuthored);

        $this->assertNotSame($a->kindFor(Dimension::Cities), $b->kindFor(Dimension::Cities));
        $this->assertTrue($a->blocksFallbackResurrection(Dimension::Cities));
        $this->assertFalse($b->blocksFallbackResurrection(Dimension::Cities));
    }

    public function test_repaired_canonical_is_distinct_from_authored_canonical(): void
    {
        $repaired = Map::empty()->with(Dimension::Cities, Kind::LegacyRepaired);
        $authored = Map::empty()->with(Dimension::Cities, Kind::OwnerAuthored);

        $this->assertNotSame($repaired->kindFor(Dimension::Cities), $authored->kindFor(Dimension::Cities));
        $this->assertFalse($repaired->isAuthoritative(Dimension::Cities));
        $this->assertTrue($authored->isAuthoritative(Dimension::Cities));
    }

    // ── immutability ─────────────────────────────────────────────────────────

    public function test_assignment_returns_a_new_map_and_leaves_the_original_alone(): void
    {
        $before = Map::fromKinds(['cities' => Kind::OwnerAuthored]);
        $after  = $before->with(Dimension::Cities, Kind::Derived);

        $this->assertNotSame($before, $after);
        $this->assertSame(Kind::OwnerAuthored, $before->kindFor(Dimension::Cities), 'original unchanged');
        $this->assertSame(Kind::Derived, $after->kindFor(Dimension::Cities));
    }

    public function test_removal_returns_a_new_map(): void
    {
        $before = Map::fromKinds(['cities' => Kind::OwnerAuthored]);
        $after  = $before->without(Dimension::Cities);

        $this->assertTrue($before->has(Dimension::Cities));
        $this->assertFalse($after->has(Dimension::Cities));
    }

    public function test_the_source_array_cannot_mutate_the_map(): void
    {
        $source = ['cities' => Kind::OwnerAuthored];
        $map    = Map::fromKinds($source);

        $source['cities']   = Kind::Derived;
        $source['counties'] = Kind::Derived;

        $this->assertSame(Kind::OwnerAuthored, $map->kindFor(Dimension::Cities));
        $this->assertFalse($map->has(Dimension::Counties));
    }

    public function test_returned_data_cannot_widen_or_alter_the_map(): void
    {
        $map = Map::fromKinds(['cities' => Kind::OwnerAuthored]);

        $keys   = $map->recordedDimensionKeys();
        $keys[] = 'polygons';

        $internal = $map->toInternalArray();
        $internal['dimensions']['polygons'] = 'owner_authored';

        $this->assertSame(['cities'], $map->recordedDimensionKeys());
        $this->assertFalse($map->has(Dimension::Polygons));
    }

    public function test_a_returned_dimension_provenance_cannot_alter_the_map(): void
    {
        $map     = Map::fromKinds(['cities' => Kind::OwnerAuthored]);
        $record  = $map->for(Dimension::Cities);
        $changed = $record?->withKind(Kind::Derived);

        $this->assertSame(Kind::Derived, $changed?->kind);
        $this->assertSame(Kind::OwnerAuthored, $map->kindFor(Dimension::Cities), 'map untouched');
    }

    // ── no wildcard, no arbitrary strings ────────────────────────────────────

    public function test_an_unknown_dimension_key_is_rejected(): void
    {
        foreach (['not_a_dimension', 'neighborhoods', 'commute', ''] as $key) {
            try {
                Map::fromKinds([$key => Kind::OwnerAuthored]);
                $this->fail("`{$key}` must be rejected");
            } catch (LocationDnaProvenanceException $e) {
                $this->assertStringContainsString('not a canonical dimension', $e->getMessage());
            }
        }
    }

    public function test_a_non_kind_value_is_rejected(): void
    {
        $this->expectException(LocationDnaProvenanceException::class);
        /** @phpstan-ignore-next-line intentionally invalid input */
        Map::fromKinds(['cities' => 'owner_authored']);
    }

    public function test_there_is_no_wildcard_assignment(): void
    {
        $map = Map::empty()->with(Dimension::Cities, Kind::OwnerAuthored);

        foreach (Dimension::all() as $dimension) {
            if ($dimension === Dimension::Cities) {
                continue;
            }

            $this->assertFalse($map->has($dimension), "{$dimension->value} must not be set implicitly");
        }
    }

    // ── checked transitions through the map ──────────────────────────────────

    public function test_a_dimension_with_no_entry_transitions_from_unknown(): void
    {
        $map = Map::empty();

        // Explicit owner authoring from nothing is allowed …
        $this->assertTrue($map->allowsTransition(Dimension::Cities, Kind::OwnerAuthored, Actor::ExplicitOwner));
        // … but an automatic promotion of an unrecorded origin is not.
        $this->assertFalse($map->allowsTransition(Dimension::Cities, Kind::Derived, Actor::AutomaticSystem));
    }

    public function test_a_checked_transition_refuses_automatic_resurrection_of_a_clear(): void
    {
        $map = Map::empty()->with(Dimension::Cities, Kind::OwnerCleared);

        $this->assertFalse($map->allowsTransition(Dimension::Cities, Kind::LegacyFallback, Actor::AutomaticSystem));

        $this->expectException(LocationDnaProvenanceException::class);
        $map->withTransition(Dimension::Cities, Kind::LegacyFallback, Actor::AutomaticSystem);
    }

    public function test_a_checked_transition_permits_explicit_owner_reauthoring(): void
    {
        $map = Map::empty()
            ->with(Dimension::Cities, Kind::OwnerCleared)
            ->withTransition(Dimension::Cities, Kind::OwnerAuthored, Actor::ExplicitOwner);

        $this->assertSame(Kind::OwnerAuthored, $map->kindFor(Dimension::Cities));
    }

    // ── deterministic, versioned private serialisation ───────────────────────

    public function test_internal_serialisation_is_deterministic_and_versioned(): void
    {
        $a = Map::fromKinds(['state' => Kind::Derived, 'cities' => Kind::OwnerAuthored]);
        $b = Map::fromKinds(['cities' => Kind::OwnerAuthored, 'state' => Kind::Derived]);

        $this->assertSame($a->toInternalArray(), $b->toInternalArray());
        $this->assertSame(1, $a->toInternalArray()['version']);
        $this->assertSame(['cities', 'state'], array_keys($a->toInternalArray()['dimensions']));
    }

    public function test_internal_round_trip_preserves_kinds(): void
    {
        $map   = Map::fromKinds(['cities' => Kind::OwnerCleared, 'polygons' => Kind::LegacyRepaired]);
        $again = Map::fromInternalArray($map->toInternalArray());

        $this->assertSame(Kind::OwnerCleared, $again->kindFor(Dimension::Cities));
        $this->assertSame(Kind::LegacyRepaired, $again->kindFor(Dimension::Polygons));
    }

    public function test_malformed_input_is_rejected_not_trusted(): void
    {
        foreach ([null, 'a string', 42, [], ['version' => 'one'], ['version' => 0],
                  ['version' => 1, 'dimensions' => 'nope']] as $bad) {
            try {
                Map::fromInternalArray($bad);
                $this->fail('malformed input must be rejected: '.get_debug_type($bad));
            } catch (LocationDnaProvenanceException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_unsupported_newer_version_is_refused(): void
    {
        try {
            Map::fromInternalArray(['version' => 99, 'dimensions' => ['cities' => 'owner_authored']]);
            $this->fail('a newer version must be refused');
        } catch (LocationDnaProvenanceException $e) {
            $this->assertStringContainsString('newer than the supported version', $e->getMessage());
            $this->assertStringContainsString('read-only', $e->getMessage());
        }
    }

    public function test_an_unreadable_kind_degrades_to_unknown_never_to_authoritative(): void
    {
        $map = Map::fromInternalArray([
            'version'    => 1,
            'dimensions' => ['cities' => 'trusted_somehow', 'counties' => 12345],
        ]);

        $this->assertSame(Kind::Unknown, $map->kindFor(Dimension::Cities));
        $this->assertSame(Kind::Unknown, $map->kindFor(Dimension::Counties));
        $this->assertFalse($map->isAuthoritative(Dimension::Cities), 'a parse failure never grants authority');
    }

    public function test_deserialising_rejects_an_unknown_dimension_key(): void
    {
        $this->expectException(LocationDnaProvenanceException::class);
        Map::fromInternalArray(['version' => 1, 'dimensions' => ['neighborhoods' => 'derived']]);
    }

    public function test_serialisation_does_not_mutate_the_map(): void
    {
        $map    = Map::fromKinds(['cities' => Kind::OwnerAuthored]);
        $before = $map->recordedDimensionKeys();

        $map->toInternalArray();
        $map->toInternalArray();

        $this->assertSame($before, $map->recordedDimensionKeys());
        $this->assertSame(Kind::OwnerAuthored, $map->kindFor(Dimension::Cities));
    }

    public function test_equivalent_inputs_produce_equivalent_maps(): void
    {
        $a = Map::fromKinds(['cities' => Kind::OwnerAuthored]);
        $b = Map::fromKinds(['cities' => Kind::OwnerAuthored]);

        $this->assertSame($a->toInternalArray(), $b->toInternalArray());
        $this->assertSame($a->recordedDimensionKeys(), $b->recordedDimensionKeys());
    }
}
