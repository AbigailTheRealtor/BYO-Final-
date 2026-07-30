<?php

namespace Tests\Unit\Services\LocationDna\Contract;

use App\Services\LocationDna\Contract\ContractViolation;
use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\InterpretationMode;
use App\Services\LocationDna\Contract\LocationDnaContractException;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use PHPUnit\Framework\TestCase;

/**
 * G1c — LocationDnaDocument: the immutable canonical private document.
 *
 * Extends PHPUnit's TestCase rather than Laravel's, deliberately: the contract core must not
 * depend on the framework, and a test that never boots the application is the cheapest proof
 * of that. See test_document_needs_no_framework_boot.
 */
class LocationDnaDocumentTest extends TestCase
{
    private function populated(): LocationDnaDocument
    {
        return LocationDnaDocument::fromCanonical([
            'cities'         => ['Tampa'],
            'state'          => 'FL',
            'polygons'       => [['label' => 'A', 'path' => [['lat' => 27.9, 'lng' => -82.4]]]],
            'location_notes' => 'Walkable.',
        ]);
    }

    public function test_document_needs_no_framework_boot(): void
    {
        // Constructed with no container, no app, no DB. If this class ever acquires a
        // framework dependency, this test fails at construction.
        $this->assertInstanceOf(LocationDnaDocument::class, $this->populated());
    }

    // ── presence: three states, not two ──────────────────────────────────────

    public function test_absent_cleared_and_authored_are_three_distinct_states(): void
    {
        $doc = LocationDnaDocument::fromCanonical([
            'cities'   => ['Tampa'],   // authored
            'counties' => [],          // cleared
            // state absent entirely
        ]);

        $this->assertTrue($doc->isAuthored(Dimension::Cities));
        $this->assertFalse($doc->isCleared(Dimension::Cities));

        $this->assertTrue($doc->isCleared(Dimension::Counties));
        $this->assertTrue($doc->isPresent(Dimension::Counties));
        $this->assertFalse($doc->isAuthored(Dimension::Counties));
        $this->assertFalse($doc->isAbsent(Dimension::Counties));

        $this->assertTrue($doc->isAbsent(Dimension::State));
        $this->assertFalse($doc->isPresent(Dimension::State));
        $this->assertFalse($doc->isCleared(Dimension::State));
    }

    public function test_canonical_empty_per_dimension_kind(): void
    {
        $this->assertSame([], Dimension::Cities->canonicalEmpty());
        $this->assertSame([], Dimension::Polygons->canonicalEmpty());
        $this->assertSame([], Dimension::SubjectProperty->canonicalEmpty());
        $this->assertSame('', Dimension::State->canonicalEmpty());
        $this->assertSame('', Dimension::LocationNotes->canonicalEmpty());
        $this->assertFalse(Dimension::FlexibleLocation->canonicalEmpty());
    }

    public function test_cleared_collection_uses_the_canonical_empty_array(): void
    {
        $doc = LocationDnaDocument::emptyDocument()->withClearedDimension(Dimension::Polygons);

        $this->assertTrue($doc->isCleared(Dimension::Polygons));
        $this->assertSame([], $doc->value(Dimension::Polygons));
    }

    public function test_cleared_scalar_uses_its_canonical_empty(): void
    {
        $doc = LocationDnaDocument::emptyDocument()
            ->withClearedDimension(Dimension::State)
            ->withClearedDimension(Dimension::FlexibleLocation);

        $this->assertSame('', $doc->value(Dimension::State));
        $this->assertFalse($doc->value(Dimension::FlexibleLocation));
        $this->assertTrue($doc->isCleared(Dimension::FlexibleLocation));
    }

    // ── authored null rejection ──────────────────────────────────────────────

    public function test_authored_null_is_rejected_by_from_canonical(): void
    {
        try {
            LocationDnaDocument::fromCanonical(['cities' => null]);
            $this->fail('null must not be accepted as an authored value');
        } catch (LocationDnaContractException $e) {
            $this->assertSame(ContractViolation::AuthoredNull, $e->violation());
            $this->assertSame('cities', $e->dimension());
        }
    }

    public function test_authored_null_is_rejected_by_with_dimension(): void
    {
        $this->expectException(LocationDnaContractException::class);
        LocationDnaDocument::emptyDocument()->withDimension(Dimension::State, null);
    }

    public function test_unrecognised_canonical_key_is_rejected(): void
    {
        try {
            LocationDnaDocument::fromCanonical(['not_a_dimension' => ['x']]);
            $this->fail('an unknown key must not be accepted as a canonical dimension');
        } catch (LocationDnaContractException $e) {
            $this->assertSame(ContractViolation::InvalidDimensionValue, $e->violation());
        }
    }

    // ── immutability ─────────────────────────────────────────────────────────

    public function test_derivation_returns_a_new_instance_and_leaves_the_original_untouched(): void
    {
        $original = $this->populated();
        $derived  = $original->withDimension(Dimension::Cities, ['Orlando']);

        $this->assertNotSame($original, $derived);
        $this->assertSame(['Tampa'], $original->value(Dimension::Cities), 'the original must not change');
        $this->assertSame(['Orlando'], $derived->value(Dimension::Cities));
    }

    public function test_clearing_returns_a_new_instance(): void
    {
        $original = $this->populated();
        $cleared  = $original->withClearedDimension(Dimension::Cities);

        $this->assertSame(['Tampa'], $original->value(Dimension::Cities));
        $this->assertSame([], $cleared->value(Dimension::Cities));
    }

    public function test_returned_arrays_cannot_be_used_to_mutate_stored_state(): void
    {
        $doc   = $this->populated();
        $array = $doc->toDimensionArray();

        $array['cities'][] = 'Injected';
        $array['polygons'][0]['path'][0]['lat'] = 0.0;

        $this->assertSame(['Tampa'], $doc->value(Dimension::Cities), 'stored state must be unreachable');
        $this->assertSame(27.9, $doc->value(Dimension::Polygons)[0]['path'][0]['lat']);
    }

    public function test_extensions_accessor_cannot_mutate_stored_state(): void
    {
        $doc = LocationDnaDocument::fromCanonical([], ['neighborhoods' => ['Old Northeast']]);

        $ext = $doc->extensions();
        $ext['neighborhoods'][] = 'Injected';

        $this->assertSame(['Old Northeast'], $doc->extensions()['neighborhoods']);
    }

    // ── deterministic access ─────────────────────────────────────────────────

    public function test_presence_set_is_deterministic_regardless_of_input_order(): void
    {
        $a = LocationDnaDocument::fromCanonical(['state' => 'FL', 'cities' => ['Tampa']]);
        $b = LocationDnaDocument::fromCanonical(['cities' => ['Tampa'], 'state' => 'FL']);

        $this->assertSame($a->presenceSet(), $b->presenceSet());
        $this->assertSame(['cities', 'state'], $a->presenceSet());
    }

    public function test_value_or_canonical_empty_does_not_invent_presence(): void
    {
        $doc = LocationDnaDocument::emptyDocument();

        $this->assertNull($doc->value(Dimension::Cities));
        $this->assertSame([], $doc->valueOrCanonicalEmpty(Dimension::Cities));
        $this->assertTrue($doc->isAbsent(Dimension::Cities), 'reading a default must not create the key');
    }

    // ── extensions: unknown-future and withdrawn keys ────────────────────────

    public function test_unknown_and_withdrawn_keys_live_in_the_extension_bag(): void
    {
        $doc = LocationDnaDocument::fromCanonical(
            ['cities' => ['Tampa']],
            ['neighborhoods' => ['Old Northeast'], 'some_future_key' => ['x']],
        );

        $this->assertTrue($doc->hasExtension('neighborhoods'));
        $this->assertTrue($doc->hasExtension('some_future_key'));
        $this->assertArrayNotHasKey('neighborhoods', $doc->toDimensionArray());
        $this->assertSame(['cities'], $doc->presenceSet());
    }

    public function test_withdrawn_keys_are_declared_and_are_not_dimensions(): void
    {
        $this->assertSame(['neighborhoods'], Dimension::withdrawnKeys());
        $this->assertNull(Dimension::tryFromKey('neighborhoods'));
        $this->assertNull(Dimension::tryFromKey('commute'), 'commute is withdrawn entirely, with no placeholder');
        $this->assertNull(Dimension::tryFromKey('important_places_json'), 'stays a separate meta key');
    }

    public function test_the_nine_canonical_dimensions_are_the_audited_set(): void
    {
        $this->assertSame([
            'cities', 'zip_codes', 'counties', 'state', 'polygons',
            'radius_searches', 'flexible_location', 'location_notes', 'subject_property',
        ], Dimension::allKeys());
    }

    // ── interpretation mode travels on the document ──────────────────────────

    public function test_mode_and_version_are_carried_not_recomputed(): void
    {
        $legacy = LocationDnaDocument::fromCanonical([], [], InterpretationMode::Legacy, null);

        $this->assertSame(InterpretationMode::Legacy, $legacy->interpretationMode());
        $this->assertNull($legacy->schemaVersion());
        $this->assertTrue($legacy->interpretationMode()->allowsLegacyFallback());

        $upgraded = $legacy->withLazyUpgrade();

        $this->assertSame(InterpretationMode::Canonical, $upgraded->interpretationMode());
        $this->assertSame(2, $upgraded->schemaVersion());
        $this->assertFalse($upgraded->interpretationMode()->allowsLegacyFallback());
        $this->assertSame(InterpretationMode::Legacy, $legacy->interpretationMode(), 'original untouched');
    }
}
