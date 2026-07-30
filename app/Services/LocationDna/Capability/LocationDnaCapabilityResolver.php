<?php

namespace App\Services\LocationDna\Capability;

use App\Services\LocationDna\Contract\Dimension;

/**
 * LocationDnaCapabilityResolver — pure, default-deny capability resolution.
 *
 * G1d inert capability resolver. INERT: not referenced by any existing production workflow,
 * controller, model, view, trait or service outside this namespace. No user-visible behaviour and
 * no database behaviour changes.
 *
 * WHAT IT IS
 * ----------
 * A total function from {@see LocationDnaAccessContext} to {@see LocationDnaCapabilitySet}.
 * Configuration declares, the resolver decides, and the server would reject — v1.2 §7.3. This
 * increment implements only the deciding.
 *
 * WHAT IT NEVER DOES
 * ------------------
 * No environment inspection: no user object, session, policy, gate, request, model or config read.
 * No persistence, no mirror repair, no snapshot read, no projection. It answers questions; it
 * performs nothing. §6: the domain core must not depend on Livewire, Blade, HTTP or JavaScript
 * types.
 *
 * WHERE THE ARCHITECTURE IS SILENT, IT DENIES
 * ------------------------------------------
 * Several branches below deny while noting that the governing documents do not settle the case.
 * That is deliberate: §7.2 makes deny the outcome for anything not affirmatively enabled, and
 * inventing a permission from silence is what that rule exists to prevent. Each such branch says
 * so in a comment rather than leaving the reader to guess.
 */
final class LocationDnaCapabilityResolver
{
    /**
     * Administrative-label dimensions — published names, not user-authored geometry or free text.
     *
     * §10.1 records that these are "user selections of published names", which is why they are the
     * dimensions a public surface may show while geometry stays withheld.
     *
     * @return list<Dimension>
     */
    public static function administrativeLabelDimensions(): array
    {
        return [Dimension::Cities, Dimension::Counties, Dimension::State, Dimension::ZipCodes];
    }

    /**
     * Dimensions the owner may author on the private edit surface.
     *
     * `subject_property` is deliberately excluded: v1.2 §17 G8 makes the subject-property profile
     * its own gate, and G1d has no authority to open it.
     *
     * @return list<Dimension>
     */
    public static function ownerEditableDimensions(): array
    {
        return [
            Dimension::Cities,
            Dimension::Counties,
            Dimension::State,
            Dimension::ZipCodes,
            Dimension::Polygons,
            Dimension::RadiusSearches,
            Dimension::FlexibleLocation,
            Dimension::LocationNotes,
        ];
    }

    /**
     * Resolve capabilities for a context.
     *
     * Total: every input yields a set, and an unresolvable input yields a fully denied one.
     */
    public function resolve(LocationDnaAccessContext $context): LocationDnaCapabilitySet
    {
        $signature = $context->signature();

        // ── default deny ─────────────────────────────────────────────────────
        // §7.2: an unrecognised context, a missing profile or a typo resolves to deny. Checked
        // first so no later branch can be reached with an unresolved axis.
        if ($context->isIncomplete()) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return match ($context->surface) {
            LocationDnaSurface::PublicListingDisplay,
            LocationDnaSurface::AdministrativeLabelProjection => $this->publicSurface($context, $signature),

            LocationDnaSurface::AuthenticatedNonOwnerDisplay => $this->authenticatedNonOwner($context, $signature),

            LocationDnaSurface::OwnerPrivateEdit    => $this->ownerPrivateEdit($context, $signature),
            LocationDnaSurface::OwnerPrivatePreview => $this->ownerPrivatePreview($context, $signature),

            LocationDnaSurface::CounterpartyAcceptedBidDocument => $this->counterpartyDocument($context, $signature),

            LocationDnaSurface::InternalMatching => $this->internalMatching($context, $signature),
            LocationDnaSurface::InternalAskAi    => $this->internalAskAi($context, $signature),

            LocationDnaSurface::BridgeCriteriaConsumer => $this->bridgeConsumer($context, $signature),

            LocationDnaSurface::PrivateCanonicalPersistence => $this->privatePersistence($context, $signature),

            LocationDnaSurface::LegacyMigrationRepair => $this->legacyRepair($context, $signature),

            LocationDnaSurface::RetainedSnapshot,
            LocationDnaSurface::FutureSnapshotReader => $this->snapshot($signature),

            LocationDnaSurface::Unknown => LocationDnaCapabilitySet::deniedAll($signature),
        };
    }

    // ── 1 · public surfaces ──────────────────────────────────────────────────

    /**
     * Public: administrative labels only, projection required, everything else denied.
     *
     * G0.1 containment and D4: a public surface never receives exact geometry, radius centres or
     * `location_notes`, regardless of authentication state. `ReadCanonicalDocument` is denied too —
     * a public surface consumes a projection, never the private document.
     */
    private function publicSurface(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        // A mutating purpose on a public surface is a category error; deny outright.
        if ($context->purpose->isMutating()) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return LocationDnaCapabilitySet::granting(
            [
                LocationDnaCapability::ExposeAdministrativeLabels,
                LocationDnaCapability::RequirePublicProjection,
            ],
            [],
            [],
            $signature,
        );
    }

    // ── 2 · authentication alone authorises nothing extra ────────────────────

    /**
     * Authenticated non-owner: the same denials as anonymous.
     *
     * D4: "Authentication alone does not authorise geometry." G1b proved this matters — the
     * accepted-bid audience and the bid surfaces are authenticated non-owners. Being logged in
     * moves nothing here, and authenticated non-owner status never becomes owner-private access.
     */
    private function authenticatedNonOwner(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        if ($context->purpose->isMutating()) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return LocationDnaCapabilitySet::granting(
            [
                LocationDnaCapability::ExposeAdministrativeLabels,
                LocationDnaCapability::RequirePublicProjection,
            ],
            [],
            [],
            $signature,
        );
    }

    // ── 3 · owner-private edit ───────────────────────────────────────────────

    /**
     * Owner editing their own record: full private read plus per-dimension mutation.
     *
     * Geometry and notes are exposed *to the owner* because this is the private editing surface and
     * v1.2 forbids changing owner editing behaviour. No public projection is required at this
     * boundary — that is what makes it the private boundary.
     *
     * The snapshot stays denied: nothing about owning a record authorises reading the retained
     * snapshot (D-G1-7, G1b F-G1B-7).
     */
    private function ownerPrivateEdit(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        // Edit and Persistence are the mutating purposes this surface serves; a Read purpose here is
        // a preview and belongs to the preview surface.
        if (! in_array($context->purpose, [LocationDnaPurpose::Edit, LocationDnaPurpose::Persistence], true)) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        $capabilities = [
            LocationDnaCapability::ReadCanonicalDocument,
            LocationDnaCapability::ExposeAdministrativeLabels,
            LocationDnaCapability::ExposeExactGeometry,
            LocationDnaCapability::ExposeLocationNotes,
            LocationDnaCapability::EditDocument,
        ];

        // §5.4 S5: a legacy-only record may consult a mirror for fallback. Consulting is granted
        // only for such a record, and repairing is NOT granted here at all — that is a separate
        // capability and a separate surface.
        if ($context->recordIsLegacyOnly) {
            $capabilities[] = LocationDnaCapability::ConsultLegacyMirrors;
        }

        return LocationDnaCapabilitySet::granting(
            $capabilities,
            self::ownerEditableDimensions(),
            self::ownerEditableDimensions(),
            $signature,
        );
    }

    /**
     * Owner previewing their own record: private read, and explicitly NO mutation.
     *
     * Distinct from the public projection — the owner sees their real geometry — and distinct from
     * the edit surface, because a read-only preview must not carry `EditDocument`.
     */
    private function ownerPrivatePreview(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        if (! in_array($context->purpose, [LocationDnaPurpose::Read, LocationDnaPurpose::Preview], true)) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        $capabilities = [
            LocationDnaCapability::ReadCanonicalDocument,
            LocationDnaCapability::ExposeAdministrativeLabels,
            LocationDnaCapability::ExposeExactGeometry,
            LocationDnaCapability::ExposeLocationNotes,
        ];

        if ($context->recordIsLegacyOnly) {
            $capabilities[] = LocationDnaCapability::ConsultLegacyMirrors;
        }

        // No EditDocument, and therefore no settable or clearable dimension.
        return LocationDnaCapabilitySet::granting($capabilities, [], [], $signature);
    }

    // ── 4 · counterparty accepted-bid document ───────────────────────────────
    /**
     * Counterparty: administrative labels only — the proven current output.
     *
     * G1b U-G1B-4 proved the rendered accepted-bid document contains administrative labels and no
     * geometry, radius centre, address or `location_notes`. This encodes that proven behaviour
     * rather than widening it, and it does not grant the retained snapshot: F-G1B-7's policy half
     * is undecided, and §7.2 makes silence a denial.
     */
    private function counterpartyDocument(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        if ($context->purpose->isMutating()) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return LocationDnaCapabilitySet::granting(
            [LocationDnaCapability::ExposeAdministrativeLabels],
            [],
            [],
            $signature,
        );
    }

    // ── 5 · internal matching / Ask AI ───────────────────────────────────────

    /**
     * Internal matching: a purpose-specific private read, with no outward exposure.
     *
     * §7 requires purpose-specific capability rather than a blanket private-document grant.
     * Matching legitimately needs geometry server-side — G0.1 applies its projection *after*
     * enrichment for exactly this reason — so `ReadCanonicalDocument` is granted while every
     * Expose* capability is withheld: nothing here may reach a viewer.
     */
    private function internalMatching(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        if ($context->purpose !== LocationDnaPurpose::Matching
            || $context->viewer !== LocationDnaViewerRelationship::InternalService) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return LocationDnaCapabilitySet::granting(
            [LocationDnaCapability::ReadCanonicalDocument],
            [],
            [],
            $signature,
        );
    }

    /**
     * Internal Ask AI: private read only.
     *
     * §12 governs the AI boundary and is not implemented by G1d. What is safe to state now is the
     * narrow part: an internal consumer may read, and may expose nothing. Anything beyond that is
     * left denied because §12's conversion step is not built.
     */
    private function internalAskAi(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        if ($context->viewer !== LocationDnaViewerRelationship::InternalService
            || $context->purpose !== LocationDnaPurpose::Read) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return LocationDnaCapabilitySet::granting(
            [LocationDnaCapability::ReadCanonicalDocument],
            [],
            [],
            $signature,
        );
    }

    // ── 6 · Bridge criteria consumer ─────────────────────────────────────────

    /**
     * Bridge: criteria read only.
     *
     * No edit, no public exposure, no snapshot. D-G1-3's carried condition keeps
     * `CriteriaHashService` and the Bridge cache key unchanged in this phase, so this grant
     * describes what the consumer may read and changes nothing about how it hashes.
     */
    private function bridgeConsumer(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        if ($context->purpose !== LocationDnaPurpose::BridgeCriteria
            || $context->viewer !== LocationDnaViewerRelationship::InternalService) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return LocationDnaCapabilitySet::granting(
            [LocationDnaCapability::ReadCanonicalDocument],
            [],
            [],
            $signature,
        );
    }

    // ── 7 · private persistence and legacy repair ────────────────────────────

    /**
     * Private canonical persistence: read and edit, no exposure.
     *
     * The capability the future `LocationDnaPersistenceService` would need. That service is NOT
     * created in this or the previous increment, so this branch describes an authorisation for
     * something that does not yet exist — which is the point of an inert resolver.
     */
    private function privatePersistence(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        if ($context->purpose !== LocationDnaPurpose::Persistence
            || $context->viewer !== LocationDnaViewerRelationship::InternalService) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return LocationDnaCapabilitySet::granting(
            [
                LocationDnaCapability::ReadCanonicalDocument,
                LocationDnaCapability::EditDocument,
            ],
            self::ownerEditableDimensions(),
            self::ownerEditableDimensions(),
            $signature,
        );
    }

    /**
     * Legacy migration / repair: the one context where repair capability is representable.
     *
     * Granting `RepairLegacyMirrors` here describes an authorisation; it performs nothing, and
     * `LegacyMirrorAdapter` is not created. D-G1-6 approved lazy repair with no bulk backfill, and
     * repair is representable only for a record that is genuinely legacy-only — a canonical record
     * with a present-but-cleared dimension must never have a mirror resurrect it, so this branch
     * denies repair outright for a non-legacy record.
     */
    private function legacyRepair(LocationDnaAccessContext $context, string $signature): LocationDnaCapabilitySet
    {
        if ($context->purpose !== LocationDnaPurpose::LegacyRepair
            || $context->viewer !== LocationDnaViewerRelationship::InternalService) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        // Repair is only representable for a legacy-only record. For a canonical record this is
        // denied, which is what prevents a mirror from overriding a present-but-cleared dimension.
        if (! $context->recordIsLegacyOnly) {
            return LocationDnaCapabilitySet::deniedAll($signature);
        }

        return LocationDnaCapabilitySet::granting(
            [
                LocationDnaCapability::ReadCanonicalDocument,
                LocationDnaCapability::ConsultLegacyMirrors,
                LocationDnaCapability::RepairLegacyMirrors,
            ],
            [],
            [],
            $signature,
        );
    }

    // ── 8 · snapshot ─────────────────────────────────────────────────────────

    /**
     * Snapshot: always fully denied in this phase.
     *
     * D-G1-7 approved option 7-E — temporary retention under a sunset and a reader guard — and
     * recorded that no new production reader may be added without a separate architecture
     * amendment, with the final disposition due before G1g completes. There is no approved
     * amendment, so both the existing retained-snapshot context and any future-reader context
     * resolve to a fully denied set. No reader is created.
     */
    private function snapshot(string $signature): LocationDnaCapabilitySet
    {
        return LocationDnaCapabilitySet::deniedAll($signature);
    }
}
