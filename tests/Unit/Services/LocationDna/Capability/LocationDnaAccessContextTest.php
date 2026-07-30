<?php

namespace Tests\Unit\Services\LocationDna\Capability;

use App\Services\LocationDna\Capability\LocationDnaAccessContext;
use App\Services\LocationDna\Capability\LocationDnaCapabilityException;
use App\Services\LocationDna\Capability\LocationDnaPurpose;
use App\Services\LocationDna\Capability\LocationDnaSurface;
use App\Services\LocationDna\Capability\LocationDnaViewerRelationship;
use PHPUnit\Framework\TestCase;

/**
 * G1d — LocationDnaAccessContext: already-resolved facts, framework-free.
 */
class LocationDnaAccessContextTest extends TestCase
{
    public function test_context_is_built_from_enums_with_no_framework_boot(): void
    {
        $c = LocationDnaAccessContext::of(
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaViewerRelationship::Anonymous,
            LocationDnaPurpose::Read,
        );

        $this->assertSame(LocationDnaSurface::PublicListingDisplay, $c->surface);
        $this->assertFalse($c->authenticated);
        $this->assertFalse($c->isIncomplete());
    }

    public function test_unknown_context_is_incomplete(): void
    {
        $this->assertTrue(LocationDnaAccessContext::unknown()->isIncomplete());
    }

    public function test_any_unresolved_axis_makes_the_context_incomplete(): void
    {
        $unknownSurface = LocationDnaAccessContext::of(
            LocationDnaSurface::Unknown,
            LocationDnaViewerRelationship::Anonymous,
            LocationDnaPurpose::Read,
        );
        $this->assertTrue($unknownSurface->isIncomplete());

        $unknownPurpose = LocationDnaAccessContext::of(
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaViewerRelationship::Anonymous,
            LocationDnaPurpose::Unknown,
        );
        $this->assertTrue($unknownPurpose->isIncomplete());

        $unknownViewer = LocationDnaAccessContext::of(
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaViewerRelationship::Unknown,
            LocationDnaPurpose::Read,
        );
        $this->assertTrue($unknownViewer->isIncomplete());
    }

    // ── contradictory contexts are rejected explicitly ───────────────────────

    public function test_an_unauthenticated_owner_is_contradictory(): void
    {
        $this->expectException(LocationDnaCapabilityException::class);

        LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            authenticated: false,
        );
    }

    public function test_an_unauthenticated_counterparty_is_contradictory(): void
    {
        $this->expectException(LocationDnaCapabilityException::class);

        LocationDnaAccessContext::of(
            LocationDnaSurface::CounterpartyAcceptedBidDocument,
            LocationDnaViewerRelationship::Counterparty,
            LocationDnaPurpose::Read,
            authenticated: false,
        );
    }

    public function test_owner_relationship_on_the_public_surface_is_contradictory(): void
    {
        $this->expectException(LocationDnaCapabilityException::class);

        LocationDnaAccessContext::of(
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Read,
            authenticated: true,
        );
    }

    public function test_the_owner_edit_surface_requires_owner_relationship(): void
    {
        $this->expectException(LocationDnaCapabilityException::class);

        LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::AuthenticatedNonOwner,
            LocationDnaPurpose::Edit,
            authenticated: true,
        );
    }

    // ── unknown parsing routes to deny, not to an error ──────────────────────

    public function test_unrecognised_names_parse_to_unknown_rather_than_throwing(): void
    {
        $this->assertSame(LocationDnaSurface::Unknown, LocationDnaSurface::fromNameOrUnknown('some_new_page'));
        $this->assertSame(LocationDnaSurface::Unknown, LocationDnaSurface::fromNameOrUnknown(null));
        $this->assertSame(LocationDnaViewerRelationship::Unknown, LocationDnaViewerRelationship::fromNameOrUnknown('admin'));
        $this->assertSame(LocationDnaPurpose::Unknown, LocationDnaPurpose::fromNameOrUnknown('export'));
    }

    public function test_known_names_parse_case_insensitively(): void
    {
        $this->assertSame(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaSurface::fromNameOrUnknown(' Owner_Private_Edit '),
        );
    }

    // ── determinism ──────────────────────────────────────────────────────────

    public function test_equivalent_contexts_share_a_signature(): void
    {
        $a = LocationDnaAccessContext::of(
            LocationDnaSurface::InternalMatching,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::Matching,
        );
        $b = LocationDnaAccessContext::of(
            LocationDnaSurface::InternalMatching,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::Matching,
        );

        $this->assertSame($a->signature(), $b->signature());
    }

    public function test_differing_contexts_have_differing_signatures(): void
    {
        $read = LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivatePreview,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Read,
            authenticated: true,
        );
        $legacy = LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivatePreview,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Read,
            authenticated: true,
            recordIsLegacyOnly: true,
        );

        $this->assertNotSame($read->signature(), $legacy->signature());
    }

    public function test_authentication_is_recorded_as_a_fact_not_a_grant(): void
    {
        $c = LocationDnaAccessContext::of(
            LocationDnaSurface::AuthenticatedNonOwnerDisplay,
            LocationDnaViewerRelationship::AuthenticatedNonOwner,
            LocationDnaPurpose::Read,
            authenticated: true,
        );

        $this->assertTrue($c->authenticated);
        $this->assertFalse($c->viewer->isOwner(), 'authenticated is not owner');
    }
}
