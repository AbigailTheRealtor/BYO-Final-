<?php

namespace App\Services\LocationDna\Capability;

/**
 * LocationDnaCapability — the closed set of Location DNA capabilities.
 *
 * G1d inert capability resolver. INERT: nothing in this namespace is referenced by any
 * existing production workflow, controller, model, view, trait or service.
 *
 * Approved by D-G1-... capability model per v1.2 §7 and the G1c decision package. §7 fixes
 * two properties this enum exists to serve: capability is DECLARED separately from the code
 * that consumes it, and it is ENFORCED server-side. A closed enum means an unrecognised
 * capability cannot be invented at a call site.
 *
 * SEPARATION THIS ENUM DELIBERATELY ENCODES
 * -----------------------------------------
 * Reading is not editing. Exposing administrative labels is not exposing geometry. Exposing
 * geometry is not exposing free text. Consulting a legacy mirror is not repairing it. Each is
 * its own case precisely so a grant of one can never imply another — the implication chains
 * G1b found across 44 consumers are what this prevents.
 */
enum LocationDnaCapability: string
{
    /** Read the private canonical document (§5.1) in full, geometry included. */
    case ReadCanonicalDocument = 'read_canonical_document';

    /** Emit published administrative names — cities, counties, state, ZIPs. */
    case ExposeAdministrativeLabels = 'expose_administrative_labels';

    /** Emit exact user-authored geometry: polygon vertices, radius centres. */
    case ExposeExactGeometry = 'expose_exact_geometry';

    /** Emit `location_notes` free text (§5.1, D5). */
    case ExposeLocationNotes = 'expose_location_notes';

    /** Mutate the document at all — the precondition for any set or clear. */
    case EditDocument = 'edit_document';

    /** Consult a legacy discrete mirror as a fallback source (§5.4 S5). */
    case ConsultLegacyMirrors = 'consult_legacy_mirrors';

    /** Write a corrected legacy mirror. Separate from consulting one, by design. */
    case RepairLegacyMirrors = 'repair_legacy_mirrors';

    /** Read the retained accepted-bid location snapshot (G1b F-G1B-7, D-G1-7). */
    case ReadRetainedSnapshot = 'read_retained_snapshot';

    /**
     * A public projection MUST be applied before this payload leaves the server.
     *
     * An obligation, not a permission: its presence means the caller owes a projection, and
     * it is granted together with a denial of geometry and notes, never instead of one.
     */
    case RequirePublicProjection = 'require_public_projection';

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
