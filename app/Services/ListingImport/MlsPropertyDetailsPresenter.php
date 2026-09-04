<?php

namespace App\Services\ListingImport;

use App\Services\ListingImport\Mls\MlsFieldCatalog;
use App\Services\ListingImport\Mls\MlsValueFormatter;

/**
 * Tier 2a — supplemental MLS property facts, for DISPLAY only.
 *
 * The problem this solves is the one the whole feature exists for: MLS carries
 * far more about a property than our consumer form has inputs for, and the
 * answer must not be "add three hundred form fields". These attributes are
 * already stored — `bridge_properties.raw_json` holds the complete record — so
 * this class renders the permitted ones instead of discarding them.
 *
 * STORAGE, USE AND DISPLAY ARE THREE DIFFERENT PERMISSIONS
 * --------------------------------------------------------
 * That distinction is the entire point of this class existing at all. The raw
 * record we hold contains PublicRemarks, PrivateRemarks, ShowingInstructions,
 * ListAgent* and ListOffice* fields, compensation, lockbox details and contact
 * information. Holding them is permitted. Scoring against them internally is
 * permitted (that is what Match Check does). Publishing them on a consumer page
 * is a different act entirely, and "we already have the data" is not an argument
 * that it may be shown.
 *
 * So this is an ALLOW-LIST, and it is now sourced from one place:
 * {@see MlsFieldCatalog::PROPERTY_FACTS}. A denylist would have to be updated
 * every time the feed adds a column and would fail OPEN — a new `AgentNotes`
 * field would appear on a public listing page until somebody noticed. This
 * fails closed: a field nobody has explicitly cleared is a field nobody sees.
 *
 * WHAT IS NOT HERE, AND WHY IT IS LISTED
 * --------------------------------------
 * {@see EXCLUDED} names everything withheld from THIS surface and why. It is
 * documentation with teeth: the guard test asserts none of those keys can appear
 * in the output, so a future edit that adds `PublicRemarks` to the catalog fails
 * the build rather than shipping. Some entries are withheld outright
 * (licensing, privacy); others are simply rendered by a different presenter —
 * agent and brokerage data by {@see Mls\MlsContactsPresenter}, MLS bookkeeping
 * by {@see Mls\MlsListingContextPresenter}, photographs by the gallery.
 *
 * TIER 1 IS NOT REPEATED HERE
 * ---------------------------
 * When a role is supplied, a fact that reached an editable Create Offer field
 * for that role is omitted: the listing already shows it, from the field the
 * user can correct, and printing it twice makes the MLS copy look like a second
 * conflicting claim. Role asymmetry is respected — `building_size_sqft` has a
 * Seller destination and no Landlord one, so a landlord listing still shows it
 * here rather than losing it. With no role (the pure allow-list view used by the
 * compliance tests) nothing is omitted.
 *
 * Only populated values are rendered. A section whose every field is empty does
 * not appear at all, because a "Community" heading over a blank space tells the
 * reader the listing has no community information, which is a claim the feed
 * never made.
 */
class MlsPropertyDetailsPresenter
{
    /**
     * The complete set of RESO fields that may be shown, grouped for display.
     *
     * @var array<string, array<string,string>>  section => [RESO field => label]
     */
    public const FIELDS = MlsFieldCatalog::PROPERTY_FACTS;

    /**
     * Fields that reach a Create Offer field but must ALSO appear here.
     *
     * `Furnished` is the case that forced this to exist. Its Seller destination
     * is a MERGE into `building_features` that contributes at most the single
     * word "Furnished", and its Landlord destination is a control the quick
     * import deliberately declines to write. Suppressing the fact because a
     * Tier-1 mapping nominally exists would lose "Negotiable", "Partial" and
     * "Turnkey" entirely, which is the opposite of what the mapping is for.
     */
    private const ALWAYS_SUPPLEMENTAL = ['Furnished'];

    /**
     * Withheld from this surface for a reason other than licensing — because
     * another presenter owns them.
     *
     * @var array<string,string>
     */
    private const RENDERED_ELSEWHERE = [
        'ListingKey'                         => 'rendered by MlsListingContextPresenter',
        'ListingId'                          => 'rendered by MlsListingContextPresenter',
        'OriginatingSystemKey'               => 'internal identifier',
        'OriginatingSystemName'              => 'rendered by MlsListingContextPresenter',
        'SourceSystemName'                   => 'internal identifier',
        'ListAgentFullName'                  => 'rendered by MlsContactsPresenter',
        'ListAgentKey'                       => 'internal provider identifier',
        'ListAgentMlsId'                     => 'rendered by MlsContactsPresenter',
        'ListAgentEmail'                     => 'rendered by MlsContactsPresenter',
        'ListAgentDirectPhone'               => 'rendered by MlsContactsPresenter',
        'ListAgentPreferredPhone'            => 'rendered by MlsContactsPresenter',
        'ListOfficeName'                     => 'rendered by MlsContactsPresenter',
        'ListOfficeKey'                      => 'internal provider identifier',
        'ListOfficePhone'                    => 'rendered by MlsContactsPresenter',
        'AssociationName'                    => 'rendered by MlsContactsPresenter',
        'AssociationPhone'                   => 'rendered by MlsContactsPresenter',
        'STELLAR_AssociationEmail'           => 'rendered by MlsContactsPresenter',
        'Media'                              => 'handled by MlsMediaPolicy and the gallery',
        'VirtualTourURLUnbranded'            => 'rendered by MlsListingContextPresenter',
    ];

    /**
     * Field names that must never appear in THIS presenter's output.
     *
     * Not a filter — {@see FIELDS} being an allow-list already excludes
     * everything absent from it, so nothing here is load-bearing at runtime.
     * It is a named list of what is withheld and WHY, checked by the guard test,
     * so that adding one to the catalog breaks the build instead of quietly
     * publishing it.
     *
     * @var array<string,string>  field => reason
     */
    public const EXCLUDED = MlsFieldCatalog::RESTRICTED + self::RENDERED_ELSEWHERE;

    /**
     * Populated, permitted MLS attributes grouped for display.
     *
     * @param  array<string,mixed>  $raw   a decoded Bridge/RESO property record
     * @param  string|null          $role  'seller' | 'landlord' — omits facts
     *                                     that reached that role's own form
     * @return array<string, list<array{key: string, label: string, value: string}>>
     *         section => rows; empty sections are omitted entirely
     */
    public function present(array $raw, ?string $role = null): array
    {
        $tier1ForRole = $this->tier1DestinationsFor($role);
        $sections     = [];

        foreach (self::FIELDS as $section => $fields) {
            $rows = [];

            foreach ($fields as $field => $label) {
                if (isset($tier1ForRole[$field]) && ! in_array($field, self::ALWAYS_SUPPLEMENTAL, true)) {
                    continue;
                }

                $value = MlsValueFormatter::format($raw[$field] ?? null);

                if ($value === null) {
                    continue;
                }

                // Two aliases can map to the same label. Showing "Furnished: Yes"
                // twice reads as a rendering bug, so the first populated one wins.
                if ($this->hasLabel($rows, $label)) {
                    continue;
                }

                $rows[] = ['key' => $field, 'label' => $label, 'value' => $value];
            }

            if ($rows !== []) {
                $sections[$section] = $rows;
            }
        }

        return $sections;
    }

    public function hasAnything(array $raw, ?string $role = null): bool
    {
        return $this->present($raw, $role) !== [];
    }

    /**
     * Bridge fields whose canonical key actually has a destination on this
     * role's form.
     *
     * Asked of {@see MlsFieldMap} rather than assumed, because the Seller and
     * Landlord maps are deliberately asymmetric: a fact with no destination on
     * this role's form was never written anywhere, so omitting it here would
     * lose it outright.
     *
     * @return array<string,true>
     */
    private function tier1DestinationsFor(?string $role): array
    {
        if ($role === null) {
            return [];
        }

        $map        = MlsFieldMap::forRole($role);
        $unrendered = MlsFieldCatalog::TIER1_MAPPED_BUT_UNRENDERED[$role] ?? [];
        $out        = [];

        foreach (MlsFieldCatalog::TIER1_BYO as $field => $canonicalKey) {
            // Mapped is not the same as shown. A destination this role's listing
            // page never renders is one whose only surface is MLS Details, so it
            // stays here. See TIER1_MAPPED_BUT_UNRENDERED.
            if (isset($map[$canonicalKey]) && ! in_array($field, $unrendered, true)) {
                $out[$field] = true;
            }
        }

        return $out;
    }

    /** @param list<array{key: string, label: string, value: string}> $rows */
    private function hasLabel(array $rows, string $label): bool
    {
        foreach ($rows as $row) {
            if ($row['label'] === $label) {
                return true;
            }
        }

        return false;
    }
}
