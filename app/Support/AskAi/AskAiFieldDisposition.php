<?php

namespace App\Support\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;

/**
 * AskAiFieldDisposition — every canonical listing field has an Ask AI answer, and
 * "nobody got round to it" is not one of them.
 *
 * WHY THIS EXISTS
 * ---------------
 * Coverage was previously provable only by inspection: a field either had a question or
 * it did not, and a field that had none looked exactly like a field that should not have
 * one. So a public field added next quarter would be un-askable and nothing would say so.
 * This class makes the distinction explicit and machine-checkable — **silence fails**.
 *
 * THE FIVE DISPOSITIONS
 * ---------------------
 *   ANSWERABLE_PUBLIC        a shopper may ask; the catalog carries a question for it,
 *                            either as a question's own source or inside a composite.
 *   ANSWERABLE_OWNER_ONLY    the listing's own owner may ask; the public card may not.
 *                            Reserved, and deliberately EMPTY today — see the note below.
 *   PRIVATE                  SnapshotFactVisibility says OWNER_ONLY. Not publishable.
 *   INTERNAL                 SnapshotFactVisibility says RESTRICTED. Never leaves the system.
 *   NOT_APPLICABLE_TO_ASK_AI publishable in principle, deliberately not asked. Every entry
 *                            states WHY, because this is the only bucket a reviewer cannot
 *                            re-derive from another class.
 *
 * DERIVED WHERE IT CAN BE, DECLARED ONLY WHERE IT CANNOT
 * ------------------------------------------------------
 * PRIVATE and INTERNAL are read from {@see SnapshotFactVisibility} rather than restated —
 * a second copy of a privacy classification is a second thing to keep in step, and the one
 * that drifts is always the copy. ANSWERABLE_PUBLIC is derived from the catalog itself.
 * Only NOT_APPLICABLE_TO_ASK_AI is hand-written, and it is the smallest possible list.
 *
 * WHY ANSWERABLE_OWNER_ONLY IS EMPTY
 * -----------------------------------
 * The owner Ask AI path is now deterministic and its language-model route is hard-disabled,
 * so an owner-scoped deterministic answer is newly possible — commercial CAM, zoning,
 * ceiling height, occupancy and the rest. It is NOT built here. The public question service
 * has one visibility context and adding a second is a change to how every answer is
 * screened, not a list of new fields; doing it inside a coverage batch would put an
 * untested second visibility path behind the same card that serves the public. The category
 * exists so the audit has somewhere honest to land, and a test asserts it is empty rather
 * than letting it quietly fill up.
 *
 * Pure: no container, no database, no clock, no network.
 */
final class AskAiFieldDisposition
{
    public const ANSWERABLE_PUBLIC        = 'ANSWERABLE_PUBLIC';
    public const ANSWERABLE_OWNER_ONLY    = 'ANSWERABLE_OWNER_ONLY';
    public const PRIVATE_FIELD            = 'PRIVATE';
    public const INTERNAL                 = 'INTERNAL';
    public const NOT_APPLICABLE           = 'NOT_APPLICABLE_TO_ASK_AI';

    /**
     * Public-allowed fields that are deliberately NOT asked about, and why.
     *
     * Keyed `role.field`. Every reason is a product or compliance decision that already
     * exists elsewhere in this codebase; none is "not done yet". A field belongs here only
     * when publishing it would be wrong, never when writing the question would be work.
     *
     * @var array<string, string>
     */
    public const DELIBERATELY_NOT_ASKED = [
        // ── Location identifiers ────────────────────────────────────────────
        //
        // The listing PAGE already publishes the address exactly where the feed and the
        // owner permit it, and withholds it on the 71-of-1,202 records that refuse it. An
        // Ask AI question would be a SECOND place that decision lives, and the two would
        // eventually disagree — which on this field means publishing an address a seller
        // asked us to withhold. The PII screen already strips street addresses out of
        // knowledge-base answers for the same reason; asking for one directly would walk
        // around that screen rather than through it.
        'seller.address'            => 'Address visibility is the listing page\'s decision and the PII screen\'s; a question would duplicate it.',
        'landlord.address'          => 'Address visibility is the listing page\'s decision and the PII screen\'s; a question would duplicate it.',
        'landlord.property_zip'     => 'Part of the address. Withheld wherever the address line is withheld.',
        'seller.parcel_id'          => 'A parcel number resolves to owner records. Published nowhere, asked nowhere.',
        'seller.legal_description'  => 'A legal description locates the property precisely and is a conveyancing artifact, not a shopper fact.',

        // ── Fair Housing ────────────────────────────────────────────────────
        //
        // Restriction prose and "other" free-text boxes are where BREED limits are written,
        // and a breed limit is a recognised proxy. The seller pet entry has documented this
        // exclusion since Batch 2c; these two keys are the same decision, recorded.
        'seller.pet_restrictions'   => 'Restriction prose carries breed limits, a recognised Fair Housing proxy. Structured pet limits are answered instead.',
        'landlord.pet_fee_other'    => 'An "other" free-text box beside the pet policy; same breed-proxy exposure as restriction prose. The structured fee and deposit are answered instead.',

        // ── A name that matches and a meaning that does not ─────────────────
        //
        // Each of these reads a stored value under a label it does not carry. Answering
        // would state a fact the listing never made; the adjacent composite answers the
        // honest part instead.
        'seller.garage_spaces'      => 'garage_parking_spaces is a Yes/No control, not a count; read as one it would publish a garage size nobody stated. Batch 2b pins that no size is shown. Parking is answered from garage + carport.',
        'landlord.lease_length'     => 'Resolves from min_lease_period, the HOA minimum lease period. Published as a lease length it would read as the term on offer, which Batch 2b pins must never happen. The offered lease terms are answered instead.',

        // ── One context key, two meanings ───────────────────────────────────
        //
        // `listing.utilities` is a cascade: the offer-listing scalar the public page labels
        // "Utilities Included in Rent", falling back to the MLS `property_utilities` list of
        // utilities AVAILABLE ("Electricity Connected"). Under "What utilities are included?"
        // the fallback would tell a renter that connected utilities come with the rent.
        // Batch 1 withheld it for this reason; answering it safely needs the key split into
        // its two meanings in the context builder, not a question over the merged value.
        'landlord.utilities'        => 'A two-meaning cascade: "included in rent" and "available/connected" share one context key, so any answer could state the wrong one.',
    ];

    /**
     * Meta keys MLS quick import can write that the Ask AI context deliberately does not read,
     * and why. A bare key holds for both Seller and Landlord; `role.key` for one role only. The other half of the four-way contract (form ↔ import ↔ context ↔ question):
     * AskAiMlsImportCoverageContractTest fails, naming the key, when import can write a key that
     * is neither read nor listed here — the silent-drop failure, one layer down.
     *
     * @var array<string, string>
     */
    public const IMPORT_META_NOT_READ = [
        // Location identifiers — the same decision as seller.address / landlord.property_zip.
        'address'                 => 'Address visibility is the listing page\'s and the PII screen\'s decision; the context carries the native address only.',
        'property_city'           => 'Part of the address; location identifiers are not asked.',
        'property_state'          => 'Part of the address; location identifiers are not asked.',
        'seller.property_zip'     => 'Part of the address; withheld wherever the address line is withheld. (The landlord context reads it and dispositions it NOT_APPLICABLE.)',
        'property_county'         => 'Part of the address; location identifiers are not asked.',
        'property_lat'            => 'A coordinate locates the property exactly, and carries no precision (CoordinatePrecision) — never an Ask AI answer.',
        'property_lng'            => 'Same reason as property_lat.',
        'seller.sqft_heated_source' => 'Where a square-footage figure came from (e.g. Public Records) — metadata about a fact, not a property fact. (The landlord context reads it as owner-only.)',
        // Provenance and sync bookkeeping — facts about the import, not the property.
        'mls_listing_key'                  => 'Import provenance (linkage identity).',
        'mls_number'                       => 'Import provenance (linkage identity).',
        'mls_provider'                     => 'Import provenance.',
        'mls_imported_at'                  => 'Import provenance timestamp.',
        'mls_refreshed_at'                 => 'Import provenance timestamp.',
        'mls_source_status'                => 'Feed status string recorded at import; ListingStatusDisplay owns MLS-linked status presentation.',
        'mls_source_property_type'         => 'The feed\'s property type at import, used for price-sync safety; the listing\'s own property_type is read.',
        'mls_quick_import'                 => 'Import provenance flag.',
        'property_photos_order_customized' => 'Gallery ordering state, not a property fact.',
        'mls_standard_status'              => 'Written by sync, not import; ListingStatusDisplay owns MLS-linked status presentation.',
        'mls_status_unrecognised'          => 'Sync bookkeeping.',
        'mls_source_modified_at'           => 'Sync change marker.',
        'mls_source_status_changed_at'     => 'Sync change marker.',
        'mls_source_price_changed_at'      => 'Sync change marker.',
        'mls_source_photos_changed_at'     => 'Sync change marker.',
        'mls_sync_attempted_at'            => 'Sync bookkeeping.',
        'mls_synced_at'                    => 'Sync bookkeeping.',
        'mls_sync_error'                   => 'Sync bookkeeping.',
        'mls_sync_overwritten'             => 'Sync bookkeeping.',
        'mls_display_permissions'          => 'The feed\'s display permissions — a gate applied to what is shown, not a fact to answer.',
    ];

    /**
     * Import-written meta keys Ask AI reads OUTSIDE CANONICAL_SOURCE_MAP, and where.
     *
     * @var array<string, string>
     */
    public const IMPORT_META_READ_ELSEWHERE = [
        'property_type'        => 'AskAiContextBuilderService base block (listing.property_type).',
        'mls_list_price'       => 'AskAiPublicPropertyQuestionService mls_price_not_divergent guard (via ListingPriceDisplay).',
        'mls_property_details' => 'AskAiMlsDetailsQuestionMatcher — the owner\'s MLS Details facts, by label.',
    ];

    /**
     * Every canonical field for a role, with its disposition.
     *
     * @return array<string, string> field => disposition
     */
    public static function forRole(string $role): array
    {
        $map      = AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role] ?? [];
        $answered = self::answeredFields($role);
        $out      = [];

        foreach (array_keys($map) as $field) {
            $visibility = SnapshotFactVisibility::classify($field, $role);

            if ($visibility === SnapshotFactVisibility::RESTRICTED) {
                $out[$field] = self::INTERNAL;
                continue;
            }

            if ($visibility !== SnapshotFactVisibility::PUBLIC_ALLOWED) {
                $out[$field] = self::PRIVATE_FIELD;
                continue;
            }

            if (isset($answered[$field])) {
                $out[$field] = self::ANSWERABLE_PUBLIC;
                continue;
            }

            $out[$field] = array_key_exists("{$role}.{$field}", self::DELIBERATELY_NOT_ASKED)
                ? self::NOT_APPLICABLE
                : '';   // '' is the failure: a public field nobody has dispositioned.
        }

        return $out;
    }

    /**
     * The canonical fields this role's catalog actually answers about.
     *
     * A field counts when it is a question's own source, OR feeds one as a supporting
     * path, OR is named in a composite's `covers`. All three are real coverage: "is it on
     * the water?" answers `waterfront_feet` without that field ever being a source_path.
     *
     * @return array<string, true>
     */
    public static function answeredFields(string $role): array
    {
        $answered = [];

        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $entry) {
            if (($entry['role'] ?? null) !== $role) {
                continue;
            }

            $paths = array_merge(
                [$entry['source_path'] ?? null],
                (array) ($entry['supporting_paths'] ?? []),
                (array) ($entry['covers'] ?? []),
            );

            foreach ($paths as $path) {
                if (is_string($path) && preg_match('/^listing\.([a-z0-9_]+)$/', $path, $m) === 1) {
                    $answered[$m[1]] = true;
                }
            }
        }

        return $answered;
    }

    /**
     * Public fields with no disposition at all — the CI failure condition.
     *
     * @return array<string, list<string>> role => fields
     */
    public static function undispositioned(): array
    {
        $out = [];

        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            $missing = array_keys(array_filter(self::forRole($role), static fn ($d): bool => $d === ''));

            if ($missing !== []) {
                $out[$role] = array_values($missing);
            }
        }

        return $out;
    }
}
