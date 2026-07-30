<?php

namespace Tests\Unit\Services\LocationDna\Capability;

use App\Services\LocationDna\Capability\LocationDnaCapability;
use App\Services\LocationDna\Capability\LocationDnaCapabilitySet;
use App\Services\LocationDna\Contract\Dimension;
use PHPUnit\Framework\TestCase;

/**
 * G1d — LocationDnaCapabilitySet: immutability, explicit grants, and no implication.
 *
 * The property this suite exists to pin is that capabilities do not imply one another. G1b found
 * 29 of 44 consumers conflating "can I see it" with "may I act on it"; a set that inferred edit
 * from read, or geometry from labels, would rebuild that conflation in the authorisation layer.
 */
class LocationDnaCapabilitySetTest extends TestCase
{
    public function test_default_deny_grants_nothing(): void
    {
        $set = LocationDnaCapabilitySet::deniedAll();

        $this->assertTrue($set->isFullyDenied());

        foreach (LocationDnaCapability::all() as $capability) {
            $this->assertTrue($set->denies($capability), "{$capability->value} must be denied by default");
        }

        foreach (Dimension::all() as $dimension) {
            $this->assertFalse($set->maySet($dimension));
            $this->assertFalse($set->mayClear($dimension));
        }
    }

    public function test_explicit_grants_are_honoured_and_nothing_else_is(): void
    {
        $set = LocationDnaCapabilitySet::granting([
            LocationDnaCapability::ReadCanonicalDocument,
            LocationDnaCapability::ExposeAdministrativeLabels,
        ]);

        $this->assertTrue($set->allows(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($set->allows(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertFalse($set->isFullyDenied());

        // Everything not listed is denied because it was never granted.
        $this->assertTrue($set->denies(LocationDnaCapability::EditDocument));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeExactGeometry));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeLocationNotes));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
        $this->assertTrue($set->denies(LocationDnaCapability::RepairLegacyMirrors));
    }

    // ── the three non-implications ───────────────────────────────────────────

    public function test_read_does_not_imply_edit(): void
    {
        $set = LocationDnaCapabilitySet::granting([LocationDnaCapability::ReadCanonicalDocument]);

        $this->assertTrue($set->allows(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($set->denies(LocationDnaCapability::EditDocument));
        $this->assertFalse($set->maySet(Dimension::Cities), 'no edit capability means no mutation');
        $this->assertFalse($set->mayClear(Dimension::Cities));
    }

    public function test_administrative_labels_do_not_imply_geometry(): void
    {
        $set = LocationDnaCapabilitySet::granting([LocationDnaCapability::ExposeAdministrativeLabels]);

        $this->assertTrue($set->allows(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeExactGeometry));
    }

    public function test_geometry_does_not_imply_location_notes(): void
    {
        $set = LocationDnaCapabilitySet::granting([LocationDnaCapability::ExposeExactGeometry]);

        $this->assertTrue($set->allows(LocationDnaCapability::ExposeExactGeometry));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeLocationNotes));
    }

    public function test_consulting_mirrors_does_not_imply_repairing_them(): void
    {
        $set = LocationDnaCapabilitySet::granting([LocationDnaCapability::ConsultLegacyMirrors]);

        $this->assertTrue($set->allows(LocationDnaCapability::ConsultLegacyMirrors));
        $this->assertTrue($set->denies(LocationDnaCapability::RepairLegacyMirrors));
    }

    // ── dimension mutation requires BOTH edit and the dimension grant ────────

    public function test_dimension_grant_without_edit_capability_grants_nothing(): void
    {
        $set = LocationDnaCapabilitySet::granting([], [Dimension::Cities], [Dimension::Cities]);

        $this->assertFalse($set->maySet(Dimension::Cities), 'EditDocument is the precondition');
        $this->assertFalse($set->mayClear(Dimension::Cities));
    }

    public function test_edit_capability_without_a_dimension_grant_grants_nothing(): void
    {
        $set = LocationDnaCapabilitySet::granting([LocationDnaCapability::EditDocument]);

        foreach (Dimension::all() as $dimension) {
            $this->assertFalse($set->maySet($dimension), 'no wildcard: each dimension must be granted');
            $this->assertFalse($set->mayClear($dimension));
        }
    }

    public function test_set_and_clear_are_independently_grantable(): void
    {
        $set = LocationDnaCapabilitySet::granting(
            [LocationDnaCapability::EditDocument],
            [Dimension::Cities],
            [Dimension::Counties],
        );

        $this->assertTrue($set->maySet(Dimension::Cities));
        $this->assertFalse($set->mayClear(Dimension::Cities), 'settable does not imply clearable');

        $this->assertTrue($set->mayClear(Dimension::Counties));
        $this->assertFalse($set->maySet(Dimension::Counties), 'clearable does not imply settable');
    }

    // ── immutability and determinism ─────────────────────────────────────────

    public function test_returned_lists_cannot_be_used_to_widen_the_set(): void
    {
        $set = LocationDnaCapabilitySet::granting(
            [LocationDnaCapability::EditDocument],
            [Dimension::Cities],
            [Dimension::Cities],
        );

        $caps = $set->grantedCapabilities();
        $caps[] = LocationDnaCapability::ExposeExactGeometry->value;

        $settable = $set->settableDimensionKeys();
        $settable[] = Dimension::Polygons->value;

        $this->assertTrue($set->denies(LocationDnaCapability::ExposeExactGeometry), 'set not widened');
        $this->assertFalse($set->maySet(Dimension::Polygons), 'dimension grants not widened');
        $this->assertSame(['cities'], $set->settableDimensionKeys());
    }

    public function test_granted_lists_are_deterministically_ordered(): void
    {
        $a = LocationDnaCapabilitySet::granting([
            LocationDnaCapability::EditDocument,
            LocationDnaCapability::ReadCanonicalDocument,
        ], [Dimension::State, Dimension::Cities]);

        $b = LocationDnaCapabilitySet::granting([
            LocationDnaCapability::ReadCanonicalDocument,
            LocationDnaCapability::EditDocument,
        ], [Dimension::Cities, Dimension::State]);

        $this->assertSame($a->grantedCapabilities(), $b->grantedCapabilities());
        $this->assertSame($a->settableDimensionKeys(), $b->settableDimensionKeys());
    }

    public function test_the_capability_vocabulary_is_the_audited_closed_set(): void
    {
        $this->assertSame([
            'read_canonical_document',
            'expose_administrative_labels',
            'expose_exact_geometry',
            'expose_location_notes',
            'edit_document',
            'consult_legacy_mirrors',
            'repair_legacy_mirrors',
            'read_retained_snapshot',
            'require_public_projection',
        ], array_map(fn (LocationDnaCapability $c): string => $c->value, LocationDnaCapability::all()));
    }
}
