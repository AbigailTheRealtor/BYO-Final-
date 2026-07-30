<?php

namespace Tests\Unit\Services\LocationDna\Capability;

use App\Services\LocationDna\Capability\LocationDnaAccessContext;
use App\Services\LocationDna\Capability\LocationDnaCapability;
use App\Services\LocationDna\Capability\LocationDnaCapabilityResolver;
use App\Services\LocationDna\Capability\LocationDnaPurpose;
use App\Services\LocationDna\Capability\LocationDnaSurface;
use App\Services\LocationDna\Capability\LocationDnaViewerRelationship;
use App\Services\LocationDna\Contract\Dimension;
use PHPUnit\Framework\TestCase;

/**
 * G1d — LocationDnaCapabilityResolver: the per-context result matrix.
 *
 * Every assertion encodes an approved rule. The rule this suite most exists to protect is D4:
 * authentication alone authorises neither geometry nor location_notes, so an authenticated
 * non-owner resolves identically to an anonymous viewer.
 */
class LocationDnaCapabilityResolverTest extends TestCase
{
    private LocationDnaCapabilityResolver $r;

    protected function setUp(): void
    {
        parent::setUp();
        $this->r = new LocationDnaCapabilityResolver();
    }

    private function ctx(
        LocationDnaSurface $surface,
        LocationDnaViewerRelationship $viewer,
        LocationDnaPurpose $purpose,
        bool $auth = false,
        bool $legacyOnly = false,
    ): LocationDnaAccessContext {
        return LocationDnaAccessContext::of($surface, $viewer, $purpose, $auth, $legacyOnly);
    }

    /** Assert a set denies geometry, notes and every mutation. */
    private function assertNoGeometryNotesOrMutation(\App\Services\LocationDna\Capability\LocationDnaCapabilitySet $set): void
    {
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeExactGeometry));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeLocationNotes));
        $this->assertTrue($set->denies(LocationDnaCapability::EditDocument));

        foreach (Dimension::all() as $d) {
            $this->assertFalse($set->maySet($d));
            $this->assertFalse($set->mayClear($d));
        }
    }

    // ── 1 · public surfaces ──────────────────────────────────────────────────

    public function test_public_surface_gets_labels_and_a_projection_obligation_only(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaViewerRelationship::Anonymous,
            LocationDnaPurpose::Read,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertTrue($set->allows(LocationDnaCapability::RequirePublicProjection));

        // Cannot read the private canonical document directly.
        $this->assertTrue($set->denies(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
        $this->assertNoGeometryNotesOrMutation($set);
    }

    public function test_administrative_label_projection_surface_matches_the_public_rule(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::AdministrativeLabelProjection,
            LocationDnaViewerRelationship::Anonymous,
            LocationDnaPurpose::Read,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertTrue($set->allows(LocationDnaCapability::RequirePublicProjection));
        $this->assertNoGeometryNotesOrMutation($set);
    }

    public function test_a_mutating_purpose_on_a_public_surface_is_fully_denied(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaViewerRelationship::Anonymous,
            LocationDnaPurpose::Edit,
        ));

        $this->assertTrue($set->isFullyDenied());
    }

    // ── 2 · authentication alone grants nothing extra ─────────────────────────

    public function test_authenticated_non_owner_gets_no_geometry_no_notes_no_mutation(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::AuthenticatedNonOwnerDisplay,
            LocationDnaViewerRelationship::AuthenticatedNonOwner,
            LocationDnaPurpose::Read,
            auth: true,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertTrue($set->allows(LocationDnaCapability::RequirePublicProjection));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
        $this->assertNoGeometryNotesOrMutation($set);
    }

    public function test_authenticated_non_owner_resolves_identically_to_anonymous(): void
    {
        $anon = $this->r->resolve($this->ctx(
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaViewerRelationship::Anonymous,
            LocationDnaPurpose::Read,
        ));
        $auth = $this->r->resolve($this->ctx(
            LocationDnaSurface::AuthenticatedNonOwnerDisplay,
            LocationDnaViewerRelationship::AuthenticatedNonOwner,
            LocationDnaPurpose::Read,
            auth: true,
        ));

        $this->assertSame(
            $anon->grantedCapabilities(),
            $auth->grantedCapabilities(),
            'D4: authentication alone changes nothing',
        );
    }

    public function test_authenticated_non_owner_does_not_become_owner_private_access(): void
    {
        $nonOwner = $this->r->resolve($this->ctx(
            LocationDnaSurface::AuthenticatedNonOwnerDisplay,
            LocationDnaViewerRelationship::AuthenticatedNonOwner,
            LocationDnaPurpose::Read,
            auth: true,
        ));
        $owner = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivatePreview,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Read,
            auth: true,
        ));

        $this->assertTrue($nonOwner->denies(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($owner->allows(LocationDnaCapability::ReadCanonicalDocument));
    }

    // ── 3 · owner-private edit and preview ───────────────────────────────────

    public function test_owner_private_edit_grants_canonical_read_and_per_dimension_mutation(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            auth: true,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($set->allows(LocationDnaCapability::EditDocument));
        $this->assertTrue($set->allows(LocationDnaCapability::ExposeExactGeometry), 'owner sees their own geometry');
        $this->assertTrue($set->allows(LocationDnaCapability::ExposeLocationNotes));

        // No projection obligation at the private boundary — that is what makes it private.
        $this->assertTrue($set->denies(LocationDnaCapability::RequirePublicProjection));

        // Snapshot remains independently denied.
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));

        foreach (LocationDnaCapabilityResolver::ownerEditableDimensions() as $d) {
            $this->assertTrue($set->maySet($d), "{$d->value} should be settable");
            $this->assertTrue($set->mayClear($d), "{$d->value} should be clearable");
        }
    }

    public function test_owner_private_edit_does_not_grant_subject_property_mutation(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            auth: true,
        ));

        // §17 G8 makes the subject-property profile its own gate; G1d does not open it.
        $this->assertFalse($set->maySet(Dimension::SubjectProperty));
        $this->assertFalse($set->mayClear(Dimension::SubjectProperty));
    }

    public function test_owner_private_preview_is_read_only(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivatePreview,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Preview,
            auth: true,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($set->allows(LocationDnaCapability::ExposeExactGeometry));

        // The distinction from the edit surface: no mutation whatsoever.
        $this->assertTrue($set->denies(LocationDnaCapability::EditDocument));

        foreach (Dimension::all() as $d) {
            $this->assertFalse($set->maySet($d), 'a read-only preview must not grant mutation');
            $this->assertFalse($set->mayClear($d));
        }

        // And it is not a public projection either.
        $this->assertTrue($set->denies(LocationDnaCapability::RequirePublicProjection));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
    }

    public function test_owner_edit_surface_with_a_read_purpose_is_denied(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Read,
            auth: true,
        ));

        $this->assertTrue($set->isFullyDenied(), 'a read on the edit surface belongs to the preview surface');
    }

    // ── 4 · counterparty accepted-bid document ───────────────────────────────

    public function test_counterparty_document_is_administrative_labels_only(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::CounterpartyAcceptedBidDocument,
            LocationDnaViewerRelationship::Counterparty,
            LocationDnaPurpose::Read,
            auth: true,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeExactGeometry));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeLocationNotes));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot), 'no snapshot access');
        $this->assertTrue($set->denies(LocationDnaCapability::EditDocument));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadCanonicalDocument));
    }

    // ── 5 · internal matching / Ask AI ───────────────────────────────────────

    public function test_internal_matching_reads_privately_but_exposes_nothing(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::InternalMatching,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::Matching,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ReadCanonicalDocument));

        // Purpose-specific: no outward exposure grant of any kind.
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeExactGeometry));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeLocationNotes));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
        $this->assertNoGeometryNotesOrMutation($set);
    }

    public function test_internal_matching_with_the_wrong_purpose_is_denied(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::InternalMatching,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::Edit,
        ));

        $this->assertTrue($set->isFullyDenied());
    }

    public function test_internal_ask_ai_reads_privately_and_exposes_nothing(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::InternalAskAi,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::Read,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeAdministrativeLabels));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
        $this->assertNoGeometryNotesOrMutation($set);
    }

    // ── 6 · Bridge consumer ──────────────────────────────────────────────────

    public function test_bridge_consumer_gets_criteria_read_only(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::BridgeCriteriaConsumer,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::BridgeCriteria,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ReadCanonicalDocument));
        $this->assertTrue($set->denies(LocationDnaCapability::EditDocument));
        $this->assertTrue($set->denies(LocationDnaCapability::ExposeAdministrativeLabels), 'no public exposure');
        $this->assertTrue($set->denies(LocationDnaCapability::RequirePublicProjection));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
        $this->assertNoGeometryNotesOrMutation($set);
    }

    // ── 7 · legacy fallback and repair ───────────────────────────────────────

    public function test_mirror_fallback_is_granted_only_for_a_legacy_only_record(): void
    {
        $legacy = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivatePreview,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Read,
            auth: true,
            legacyOnly: true,
        ));
        $canonical = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivatePreview,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Read,
            auth: true,
            legacyOnly: false,
        ));

        $this->assertTrue($legacy->allows(LocationDnaCapability::ConsultLegacyMirrors));
        $this->assertTrue($canonical->denies(LocationDnaCapability::ConsultLegacyMirrors));
    }

    public function test_consulting_a_mirror_never_carries_repair_capability(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            auth: true,
            legacyOnly: true,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::ConsultLegacyMirrors));
        $this->assertTrue($set->denies(LocationDnaCapability::RepairLegacyMirrors), 'repair is a separate capability');
    }

    public function test_legacy_repair_capability_is_representable_only_in_the_migration_context(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::LegacyMigrationRepair,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::LegacyRepair,
            legacyOnly: true,
        ));

        $this->assertTrue($set->allows(LocationDnaCapability::RepairLegacyMirrors));
        $this->assertTrue($set->allows(LocationDnaCapability::ConsultLegacyMirrors));
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
    }

    public function test_repair_is_denied_for_a_canonical_record_so_a_mirror_cannot_resurrect_a_clear(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::LegacyMigrationRepair,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::LegacyRepair,
            legacyOnly: false,
        ));

        $this->assertTrue(
            $set->isFullyDenied(),
            'a canonical record with a present-but-cleared dimension must not be repairable from a mirror',
        );
    }

    // ── 8 · snapshot: always denied in this phase ────────────────────────────

    public function test_retained_snapshot_context_is_fully_denied(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::RetainedSnapshot,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::SnapshotRead,
        ));

        $this->assertTrue($set->isFullyDenied());
        $this->assertTrue($set->denies(LocationDnaCapability::ReadRetainedSnapshot));
    }

    public function test_future_snapshot_reader_context_is_fully_denied(): void
    {
        $set = $this->r->resolve($this->ctx(
            LocationDnaSurface::FutureSnapshotReader,
            LocationDnaViewerRelationship::InternalService,
            LocationDnaPurpose::SnapshotRead,
        ));

        $this->assertTrue($set->isFullyDenied(), 'D-G1-7: no reader without a separate approved amendment');
    }

    public function test_no_context_whatsoever_grants_snapshot_access(): void
    {
        // Exhaustive: every surface × a plausible viewer/purpose. None may grant the snapshot.
        $viewers  = LocationDnaViewerRelationship::cases();
        $purposes = LocationDnaPurpose::cases();

        foreach (LocationDnaSurface::cases() as $surface) {
            foreach ($viewers as $viewer) {
                foreach ($purposes as $purpose) {
                    try {
                        $ctx = LocationDnaAccessContext::of($surface, $viewer, $purpose, true, false);
                    } catch (\App\Services\LocationDna\Capability\LocationDnaCapabilityException) {
                        continue; // contradictory combinations are rejected, not granted
                    }

                    $this->assertTrue(
                        $this->r->resolve($ctx)->denies(LocationDnaCapability::ReadRetainedSnapshot),
                        "snapshot must be denied for {$surface->value}/{$viewer->value}/{$purpose->value}",
                    );
                }
            }
        }
    }

    // ── default deny ─────────────────────────────────────────────────────────

    public function test_unknown_context_is_fully_denied(): void
    {
        $this->assertTrue($this->r->resolve(LocationDnaAccessContext::unknown())->isFullyDenied());
    }

    public function test_unknown_surface_purpose_or_viewer_is_fully_denied(): void
    {
        foreach ([
            [LocationDnaSurface::Unknown, LocationDnaViewerRelationship::Anonymous, LocationDnaPurpose::Read],
            [LocationDnaSurface::PublicListingDisplay, LocationDnaViewerRelationship::Unknown, LocationDnaPurpose::Read],
            [LocationDnaSurface::PublicListingDisplay, LocationDnaViewerRelationship::Anonymous, LocationDnaPurpose::Unknown],
        ] as [$s, $v, $p]) {
            $this->assertTrue(
                $this->r->resolve($this->ctx($s, $v, $p))->isFullyDenied(),
                "incomplete context {$s->value}/{$v->value}/{$p->value} must be fully denied",
            );
        }
    }

    public function test_the_existence_of_geometry_never_grants_exposure(): void
    {
        // The resolver never sees a document, so geometry cannot influence a grant. Asserted by
        // signature: resolve() takes only a context.
        $method = new \ReflectionMethod(LocationDnaCapabilityResolver::class, 'resolve');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame(
            LocationDnaAccessContext::class,
            (string) $params[0]->getType(),
            'the resolver must not accept a document, so its contents cannot grant anything',
        );
    }

    // ── purity and determinism ───────────────────────────────────────────────

    public function test_resolution_is_deterministic_for_equivalent_contexts(): void
    {
        $a = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            auth: true,
        ));
        $b = $this->r->resolve($this->ctx(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            auth: true,
        ));

        $this->assertSame($a->grantedCapabilities(), $b->grantedCapabilities());
        $this->assertSame($a->settableDimensionKeys(), $b->settableDimensionKeys());
        $this->assertSame($a->contextSignature, $b->contextSignature);
    }

    public function test_the_resolver_does_not_mutate_the_context(): void
    {
        $ctx    = $this->ctx(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            auth: true,
            legacyOnly: true,
        );
        $before = $ctx->signature();

        $this->r->resolve($ctx);
        $this->r->resolve($ctx);

        $this->assertSame($before, $ctx->signature());
        $this->assertTrue($ctx->recordIsLegacyOnly);
    }

    public function test_every_surface_resolves_without_error(): void
    {
        // Totality: the resolver is a total function over the closed vocabulary.
        foreach (LocationDnaSurface::cases() as $surface) {
            $viewer = match ($surface) {
                LocationDnaSurface::OwnerPrivateEdit,
                LocationDnaSurface::OwnerPrivatePreview          => LocationDnaViewerRelationship::Owner,
                LocationDnaSurface::CounterpartyAcceptedBidDocument => LocationDnaViewerRelationship::Counterparty,
                default                                          => LocationDnaViewerRelationship::InternalService,
            };

            $set = $this->r->resolve(LocationDnaAccessContext::of(
                $surface,
                $viewer,
                LocationDnaPurpose::Read,
                true,
            ));

            $this->assertInstanceOf(\App\Services\LocationDna\Capability\LocationDnaCapabilitySet::class, $set);
        }
    }
}
