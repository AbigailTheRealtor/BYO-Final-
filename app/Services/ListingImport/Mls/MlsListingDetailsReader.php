<?php

namespace App\Services\ListingImport\Mls;

use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;

/**
 * Reads the MLS payload back off an imported listing, and answers the one
 * question a listing page must ask before it prints anything MLS-sourced.
 *
 * WHY A READER RATHER THAN INLINE META LOOKUPS
 * --------------------------------------------
 * Four surfaces need this — the seller listing view, the landlord listing view,
 * and each of their controllers. Four inline `$meta['mls_property_details']`
 * reads would be four opportunities to forget the permission check, and the one
 * that forgot would be the one publishing an address the MLS said to withhold.
 * Asking here means the gate travels with the data.
 *
 * THE OWNER SEES WHAT THE PUBLIC MAY NOT
 * --------------------------------------
 * `InternetAddressDisplayYN = false` is an instruction about PUBLIC display. It
 * is not an instruction to hide a property's address from the person who owns
 * the listing and typed the MLS number, and blanking it for them would read as
 * data loss. So the address gate takes the viewer into account, and the owner
 * additionally gets told why the public view differs — an unexplained
 * difference between what you see and what your visitors see is worse than
 * either.
 */
final class MlsListingDetailsReader
{
    /**
     * The supplemental MLS payload stored on this listing, or an empty one.
     *
     * @param array<string,mixed> $meta the listing's decoded meta array
     */
    public function detailsFrom(array $meta): MlsSupplementalDetails
    {
        return MlsSupplementalDetails::fromStored(
            $meta[MlsQuickImportDraftWriter::META_PROPERTY_DETAILS] ?? null
        );
    }

    /**
     * The feed's display permissions for this listing.
     *
     * Read from its OWN meta key rather than from inside the details blob: the
     * case that matters most — a listing whose address may not be shown — is
     * also a case where the blob can legitimately be absent, and a gate that
     * only works when the data it guards is present is not a gate.
     *
     * A listing with no MLS provenance at all (a manually created one, or one
     * from the Listing Link importer) has no stored permissions and is not
     * governed by them: `fromStored([])` permits everything, which is correct,
     * because the MLS never made a statement about it.
     *
     * @param array<string,mixed> $meta
     */
    public function permissionsFrom(array $meta): MlsDisplayPermissions
    {
        return MlsDisplayPermissions::fromStored(
            $meta[MlsQuickImportDraftWriter::META_DISPLAY_PERMISSIONS] ?? null
        );
    }

    /** Did this listing come from an MLS import at all? */
    public function isMlsImported(array $meta): bool
    {
        return ! empty($meta[MlsQuickImportDraftWriter::META_LISTING_KEY])
            || ! empty($meta[MlsQuickImportDraftWriter::META_MLS_NUMBER]);
    }

    /**
     * May this viewer be shown the MLS-sourced address?
     *
     * @param array<string,mixed> $meta
     */
    public function addressVisibleTo(array $meta, bool $viewerIsOwner): bool
    {
        if ($viewerIsOwner) {
            return true;
        }

        return $this->permissionsFrom($meta)->addressDisplayable();
    }

    /**
     * The sentence shown to the OWNER explaining why the public view differs,
     * or null when it does not.
     *
     * @param array<string,mixed> $meta
     */
    public function addressRestrictionNotice(array $meta): ?string
    {
        if (! $this->isMlsImported($meta)) {
            return null;
        }

        return $this->permissionsFrom($meta)->addressWithheldReason();
    }
}
