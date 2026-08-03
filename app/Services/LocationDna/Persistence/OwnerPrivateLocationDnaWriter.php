<?php

namespace App\Services\LocationDna\Persistence;

use App\Services\LocationDna\Capability\LocationDnaAccessContext;
use App\Services\LocationDna\Capability\LocationDnaCapabilityResolver;
use App\Services\LocationDna\Capability\LocationDnaPurpose;
use App\Services\LocationDna\Capability\LocationDnaSurface;
use App\Services\LocationDna\Capability\LocationDnaViewerRelationship;
use App\Services\LocationDna\Provenance\ProvenanceActor;

/**
 * G1f-1 — the workflow seam for an owner editing their own record privately.
 *
 * WHY THIS EXISTS AND WHY IT IS SO THIN
 * ------------------------------------
 * `BuyerAgentAuction` needs to reach the canonical writer without acquiring a dependency on the
 * G1c contract core, the G1d capability layer or the G1e provenance model. The inertness guards
 * assert exactly that separation for the eight workflow components, and G1f-1 has no reason to
 * weaken it: the component has no business naming a `Dimension` or resolving a capability.
 *
 * So the component names ONE class — this one — and this class assembles the rest. Every argument
 * it takes is a plain value the component already has.
 *
 * This is NOT the G1g adapter contract. G1g introduces Livewire / form-POST / Null adapters behind
 * one shared suite; this is the smallest safe seam for a single migration, which is what the G1f-1
 * authorization asks for. When G1g arrives this collapses into it.
 *
 * ONE SURFACE ONLY
 * ----------------
 * It hard-codes the owner-private edit context. It cannot be used to write from a public,
 * counterparty or internal surface, because it never accepts a surface argument — a caller wanting
 * a different one would have to build the context itself, deliberately.
 */
final class OwnerPrivateLocationDnaWriter
{
    public function __construct(
        private readonly LocationDnaPersistenceService $service = new LocationDnaPersistenceService(),
        private readonly LocationDnaCommandBuilder $commands = new LocationDnaCommandBuilder(),
        private readonly LocationDnaCapabilityResolver $resolver = new LocationDnaCapabilityResolver(),
    ) {
    }

    /**
     * The same owner-private seam, managing an explicitly named set of legacy mirror keys.
     *
     * For surfaces that ALREADY write a legacy mirror outside the default managed set — today that
     * is `zipCodes`, which the Tenant and Landlord/Seller families write and the Buyer family never
     * has. Naming the keys here keeps the decision at the call site: the projection stays generic
     * and the default {@see LegacyMirrorProjection::MANAGED_KEYS} is untouched, so no already
     * migrated workflow starts emitting a mirror it did not emit before.
     *
     * @param  list<string>  $managedMirrorKeys  the FULL set this surface manages, not an addition
     */
    public static function managingMirrors(array $managedMirrorKeys): self
    {
        return new self(
            service: new LocationDnaPersistenceService(
                mirrors: new LegacyMirrorProjection($managedMirrorKeys),
            ),
        );
    }

    /**
     * Persist the Location DNA a mounted owner editor submitted.
     *
     * @param  object  $model    any auction model exposing info()/saveMeta()
     * @param  mixed   $payload  the bridged editor value; false/null/'' state nothing
     */
    public function persistFromEditorPayload(object $model, mixed $payload): PatchResult
    {
        $commands = $this->commands->fromEditorPayload($payload);

        // Absence of a command is preserve (D-G1-2). Returning before building a context or
        // touching the record keeps a no-op genuinely free of side effects.
        if ($commands === []) {
            return PatchResult::noOp();
        }

        $capabilities = $this->resolver->resolve(LocationDnaAccessContext::of(
            LocationDnaSurface::OwnerPrivateEdit,
            LocationDnaViewerRelationship::Owner,
            LocationDnaPurpose::Edit,
            authenticated: true,
        ));

        return $this->service->apply(
            new MetaKeyedRecord($model),
            $commands,
            $capabilities,
            // The owner is acting explicitly. Used only to validate the transition; never stored.
            ProvenanceActor::ExplicitOwner,
        );
    }
}
