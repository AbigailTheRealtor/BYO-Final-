<?php

namespace App\Services\LocationDna\Capability;

/**
 * LocationDnaSurface — the closed vocabulary of capability contexts.
 *
 * G1d inert capability resolver. INERT.
 *
 * DELIBERATELY NOT BLADE OR CONTROLLER NAMES
 * ------------------------------------------
 * v1.2 L6 and §7.3: "which Blade file rendered is not an authorisation input". The domain
 * vocabulary is therefore the KIND of surface, not the file that happens to render it. A
 * mapping from real application surfaces to these cases is later wiring (G1f/G1g), not part of
 * this inert resolver — which is why no case names a controller, route or view.
 *
 * `Unknown` exists so an unrecognised input has a representable home that resolves to a fully
 * denied capability set, rather than being absent and inviting a null-ish default.
 */
enum LocationDnaSurface: string
{
    /** Fully public listing display. No authentication assumed. */
    case PublicListingDisplay = 'public_listing_display';

    /** Authenticated, but not the owner. §7/D4: authentication alone authorises nothing. */
    case AuthenticatedNonOwnerDisplay = 'authenticated_non_owner_display';

    /** The record owner editing their own private document. */
    case OwnerPrivateEdit = 'owner_private_edit';

    /** The record owner viewing their own private document without editing. */
    case OwnerPrivatePreview = 'owner_private_preview';

    /** A durable accepted-bid document reaching a transaction counterparty. */
    case CounterpartyAcceptedBidDocument = 'counterparty_accepted_bid_document';

    /** An administrative-label-only projection intended for public or shared display. */
    case AdministrativeLabelProjection = 'administrative_label_projection';

    /** Internal matching / filtering, outside any request cycle. */
    case InternalMatching = 'internal_matching';

    /** Internal Ask AI consumer. */
    case InternalAskAi = 'internal_ask_ai';

    /** Bridge criteria / cache consumer. */
    case BridgeCriteriaConsumer = 'bridge_criteria_consumer';

    /** Private canonical persistence — the server writing canonical state. */
    case PrivateCanonicalPersistence = 'private_canonical_persistence';

    /** Legacy migration or lazy repair of a mirror. */
    case LegacyMigrationRepair = 'legacy_migration_repair';

    /** The retained snapshot itself, as a subject. */
    case RetainedSnapshot = 'retained_snapshot';

    /** A hypothetical future snapshot reader. Denied absent an approved amendment. */
    case FutureSnapshotReader = 'future_snapshot_reader';

    /** An unrecognised surface. Always fully denied. */
    case Unknown = 'unknown';

    /**
     * Parse a surface name, mapping anything unrecognised to {@see self::Unknown}.
     *
     * Returns Unknown rather than throwing precisely so an unknown surface string flows into
     * the default-deny path instead of an error a caller might catch and treat as permissive.
     */
    public static function fromNameOrUnknown(?string $name): self
    {
        if ($name === null) {
            return self::Unknown;
        }

        return self::tryFrom(strtolower(trim($name))) ?? self::Unknown;
    }

    public function isPubliclyReachable(): bool
    {
        return $this === self::PublicListingDisplay || $this === self::AdministrativeLabelProjection;
    }
}
