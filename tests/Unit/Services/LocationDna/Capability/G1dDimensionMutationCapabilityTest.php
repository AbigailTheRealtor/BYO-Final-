<?php

namespace Tests\Unit\Services\LocationDna\Capability;

use App\Services\LocationDna\Capability\LocationDnaAccessContext;
use App\Services\LocationDna\Capability\LocationDnaCapability;
use App\Services\LocationDna\Capability\LocationDnaCapabilityResolver;
use App\Services\LocationDna\Capability\LocationDnaCapabilitySet;
use App\Services\LocationDna\Capability\LocationDnaPurpose;
use App\Services\LocationDna\Capability\LocationDnaSurface;
use App\Services\LocationDna\Capability\LocationDnaViewerRelationship;
use App\Services\LocationDna\Contract\Dimension;
use PHPUnit\Framework\TestCase;

/**
 * G1d — dimension-level mutation capability, expressed in the G1c Dimension vocabulary.
 *
 * §7.3: "every envelope is authorised against (principal, record, dimension)". Dimension grants
 * use the G1c enum rather than arbitrary strings, so an unknown dimension is unnameable — which is
 * a stronger guarantee than validating a string at the boundary.
 */
class G1dDimensionMutationCapabilityTest extends TestCase
{
    private LocationDnaCapabilityResolver $r;

    protected function setUp(): void
    {
        parent::setUp();
        $this->r = new LocationDnaCapabilityResolver();
    }

    private function ownerEditSet(): LocationDnaCapabilitySet
    {
        return $this->r->resolve(LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            authenticated: true,
        ));
    }

    public function test_a_permitted_dimension_may_be_set(): void
    {
        $this->assertTrue($this->ownerEditSet()->maySet(Dimension::Cities));
        $this->assertTrue($this->ownerEditSet()->maySet(Dimension::Polygons));
    }

    public function test_a_permitted_dimension_may_be_cleared(): void
    {
        $this->assertTrue($this->ownerEditSet()->mayClear(Dimension::Cities));
        $this->assertTrue($this->ownerEditSet()->mayClear(Dimension::Polygons));
    }

    public function test_a_read_only_dimension_cannot_be_set_or_cleared(): void
    {
        // subject_property is read-only in G1d: §17 G8 gives it its own gate.
        $set = $this->ownerEditSet();

        $this->assertFalse($set->maySet(Dimension::SubjectProperty));
        $this->assertFalse($set->mayClear(Dimension::SubjectProperty));
    }

    public function test_a_read_only_context_grants_no_dimension_mutation_at_all(): void
    {
        $preview = $this->r->resolve(LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivatePreview,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Preview,
            authenticated: true,
        ));

        foreach (Dimension::all() as $dimension) {
            $this->assertFalse($preview->maySet($dimension));
            $this->assertFalse($preview->mayClear($dimension));
        }
    }

    public function test_reading_a_dimension_does_not_imply_editing_it(): void
    {
        $preview = $this->r->resolve(LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivatePreview,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Preview,
            authenticated: true,
        ));

        $this->assertTrue($preview->allows(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertFalse($preview->maySet(Dimension::Cities), 'read is not edit');
    }

    public function test_an_unknown_dimension_is_unnameable_so_cannot_be_granted(): void
    {
        // The G1c enum has no case for an arbitrary key, so a grant cannot even be expressed.
        $this->assertNull(Dimension::tryFromKey('not_a_dimension'));
        $this->assertNull(Dimension::tryFromKey('neighborhoods'), 'withdrawn: not a canonical dimension');
        $this->assertNull(Dimension::tryFromKey('commute'));

        // And a set built for one dimension does not leak to any other.
        $set = LocationDnaCapabilitySet::granting(
            [LocationDnaCapability::EditDocument],
            [Dimension::Cities],
            [Dimension::Cities],
        );

        foreach (Dimension::all() as $dimension) {
            if ($dimension === Dimension::Cities) {
                continue;
            }

            $this->assertFalse($set->maySet($dimension), "no wildcard grant reached {$dimension->value}");
            $this->assertFalse($set->mayClear($dimension));
        }
    }

    public function test_there_is_no_wildcard_grant(): void
    {
        $set = $this->ownerEditSet();

        // The owner-edit grant is an explicit enumeration, not "all dimensions".
        // Compared as sets: the capability set stores keys in deterministic sorted order, while
        // ownerEditableDimensions() returns them in declaration order.
        $expected = array_map(fn (Dimension $d): string => $d->value, LocationDnaCapabilityResolver::ownerEditableDimensions());
        sort($expected);
        $actual = $set->settableDimensionKeys();
        sort($actual);

        $this->assertSame($expected, $actual);
        $this->assertNotContains(Dimension::SubjectProperty->value, $set->settableDimensionKeys());
        $this->assertCount(8, $set->settableDimensionKeys());
        $this->assertCount(9, Dimension::all(), 'nine canonical dimensions exist; eight are owner-editable');
    }

    public function test_administrative_label_dimensions_are_an_explicit_named_subset(): void
    {
        $labels = LocationDnaCapabilityResolver::administrativeLabelDimensions();

        $this->assertContains(Dimension::Cities, $labels);
        $this->assertContains(Dimension::Counties, $labels);
        $this->assertContains(Dimension::State, $labels);
        $this->assertContains(Dimension::ZipCodes, $labels);

        // Geometry and free text are not administrative labels.
        $this->assertNotContains(Dimension::Polygons, $labels);
        $this->assertNotContains(Dimension::RadiusSearches, $labels);
        $this->assertNotContains(Dimension::LocationNotes, $labels);
    }

    public function test_private_persistence_context_grants_the_same_explicit_dimension_set(): void
    {
        $set = $this->r->resolve(LocationDnaAccessContext::of(
            LocationDnaSurface::PrivateCanonicalPersistence,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::Persistence,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::EditDocument));
        $this->assertTrue($set->maySet(Dimension::Cities));
        $this->assertFalse($set->maySet(Dimension::SubjectProperty));

        // Persistence must not carry outward exposure.
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeExactGeometry));
    }
}
