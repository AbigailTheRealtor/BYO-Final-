<?php

namespace App\Services\ListingImport\QuickImport;

use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgeRelatedResourceService;
use App\Services\ListingImport\Mls\MlsRelatedResources;
use App\Services\ListingImport\Media\MlsMediaExtractor;
use App\Services\ListingImport\Media\MlsMediaPolicy;
use App\Services\ListingImport\MlsListingPrefillService;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\Property\PropertyCandidate;
use Illuminate\Support\Facades\Log;

/**
 * One MLS number in, one reviewable property in.
 *
 * This is the read half of the quick-import flow: look the listing up, assemble
 * everything the user is about to be shown, and stop. It writes nothing and
 * knows nothing about listings, ownership or drafts — {@see MlsQuickImportDraftWriter}
 * does that, and keeping the two apart means the lookup can be exercised without
 * a database and the write path cannot be reached without an owner.
 *
 * THE COMPLIANCE BOUNDARY, RESTATED
 * ---------------------------------
 * This is the first class in the codebase that reads `PropertyCandidate::$raw`
 * on a path that ends at a consumer-facing page, so it is worth being explicit
 * about why that is not a widening of the facts-only rule.
 *
 * `$raw` is the untouched Bridge record. It contains PublicRemarks,
 * PrivateRemarks, ShowingInstructions, agent and office identities, contact
 * details and compensation. This class never reads a field off it directly.
 * It hands the whole record to exactly two allow-lists and keeps only what they
 * return:
 *
 *   · {@see MlsMediaExtractor} + {@see MlsMediaPolicy} — permitted photographs;
 *   · {@see MlsSupplementalDetails} — which fans out to the property-facts,
 *     contacts and listing-context allow-lists, and applies the feed's own
 *     display permissions before any of it is kept.
 *
 * Both are allow-list shaped and both fail closed, so a field the feed adds
 * tomorrow is excluded by default rather than included by accident. The facts
 * that reach a form still come from {@see MlsListingPrefillService}, whose
 * ALLOWED_FIELDS constant and guard test are untouched by this feature.
 *
 * Separate allow-lists rather than one is not duplication: they answer different
 * questions — what may be written into an editable form, what may be shown as an
 * image, what may be shown as a property fact, what may be shown as attribution
 * — and those have different answers, and different gates. The contact and
 * listing-context lists are additionally gated on the feed's own
 * {@see \App\Services\ListingImport\Mls\MlsDisplayPermissions}, which the
 * property-facts list is not.
 */
class MlsQuickImportService
{
    public function __construct(
        private readonly BridgeListingLookupService $lookup,
        private readonly MlsListingPrefillService $prefill,
        private readonly MlsMediaExtractor $mediaExtractor,
        private readonly MlsMediaPolicy $mediaPolicy,
        private readonly BridgeRelatedResourceService $related,
    ) {}

    /**
     * Is the quick-import flow available for this role?
     *
     * Gated by the prefill flag, not the media flag: the flow's core promise —
     * "we build the property portion for you" — is delivered by the facts alone,
     * and is useful with the photo gallery switched off. Media is an additive
     * layer inside a flow that works without it, which is what lets the media
     * licence be settled on its own timetable.
     */
    public function availableForRole(string $role): bool
    {
        if (! config('mls_direct_import.prefill_enabled', false)) {
            return false;
        }

        if (! config('mls_direct_import.quick_import_enabled', false)) {
            return false;
        }

        return in_array($role, (array) config('mls_direct_import.prefill_roles', []), true);
    }

    /**
     * Whether permitted MLS photographs may be attached for this role.
     *
     * Asked separately, and asked again at write time and at render time. A flag
     * can change between an import and a page view, and the answer that matters
     * at render time is the one that holds at render time.
     */
    public function mediaAvailableForRole(string $role): bool
    {
        return $this->mediaPolicy->enabledForRole($role);
    }

    /**
     * Look a listing up and assemble everything the confirmation screen needs.
     */
    public function lookup(string $mlsNumber, string $role): MlsQuickImportResult
    {
        if (! $this->availableForRole($role)) {
            return MlsQuickImportResult::disabled();
        }

        $mlsNumber = trim($mlsNumber);

        if ($mlsNumber === '') {
            return MlsQuickImportResult::invalid();
        }

        $result = $this->lookup->lookupByMlsNumber($mlsNumber);

        if ($result->isUnavailable()) {
            Log::warning('[MLS QUICK IMPORT] Bridge lookup unavailable', [
                'reason' => $result->failureReason,
                'role'   => $role,
            ]);

            return MlsQuickImportResult::unavailable();
        }

        if (! $result->isFound()) {
            return MlsQuickImportResult::notFound();
        }

        $candidate = $result->candidate;

        $prefilled = $this->prefill->fromCandidate($candidate);

        if (! $prefilled['success']) {
            return MlsQuickImportResult::noFacts();
        }

        return MlsQuickImportResult::found(
            facts:      $prefilled['data'],
            media:      $this->mediaFor($candidate, $role),
            headline:   $this->headline($candidate),
            details:    MlsSupplementalDetails::fromRecord(
                $candidate->raw,
                $role,
                // Member / Office / OpenHouse. Additive and best-effort: every
                // failure inside the enrichment resolves to an empty section, so
                // the worst case is a listing with exactly the facts it would
                // have had before this existed.
                MlsRelatedResources::fetch($candidate->raw, $this->related),
            ),
            listingKey: $candidate->listingKey,
            mlsNumber:  $candidate->mlsNumber,
            mlsStatus:  $candidate->mlsStatus ?? $candidate->standardStatus,
        );
    }

    /**
     * Permitted photographs, or none when media is switched off for this role.
     *
     * The extractor is not even called when the feature is off — the raw record
     * is not parsed for media at all. "Off" means the code path does not run,
     * not that it runs and its output is discarded somewhere later.
     *
     * @return list<\App\Services\ListingImport\Media\MlsMediaItem>
     */
    private function mediaFor(PropertyCandidate $candidate, string $role): array
    {
        if (! $this->mediaAvailableForRole($role)) {
            return [];
        }

        return $this->mediaExtractor->fromRecord($candidate->raw);
    }

    /**
     * The confirmation card: "123 Main Street / $525,000 / 4 Beds / 3 Baths".
     *
     * Built from the candidate's own typed properties, never from `$raw` — the
     * same named-property discipline the prefill service uses. Every value here
     * is one the facts-only boundary already permits in a listing form.
     *
     * @return array<string,mixed>
     */
    private function headline(PropertyCandidate $candidate): array
    {
        return array_filter([
            'address'       => $candidate->unparsedAddress,
            'city'          => $candidate->city,
            'state'         => $candidate->stateOrProvince,
            'postal_code'   => $candidate->postalCode,
            'price'         => $candidate->listPrice,
            'bedrooms'      => $candidate->bedrooms,
            'bathrooms'     => $candidate->bathrooms,
            'living_area'   => $candidate->livingAreaSqft,
            'year_built'    => $candidate->yearBuilt,
            'property_type' => $candidate->propertyType,
            'property_sub_type' => $candidate->propertySubType,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
