<?php

namespace App\Services\ListingImport;

/**
 * Layer C — additional permitted MLS property facts, for DISPLAY only.
 *
 * The problem this solves is the one the whole feature exists for: MLS carries
 * far more about a property than our consumer form has inputs for, and the
 * answer must not be "add three hundred form fields". These attributes are
 * already stored — `bridge_properties.raw_json` holds the complete record — so
 * this class simply renders the permitted ones instead of discarding them.
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
 * So this is an ALLOW-LIST, in the same shape and for the same reason as
 * {@see MlsListingPrefillService::ALLOWED_FIELDS}. A denylist would have to be
 * updated every time the feed adds a column and would fail OPEN — a new
 * `AgentNotes` field would appear on a public listing page until somebody
 * noticed. This fails closed: a field nobody has explicitly cleared is a field
 * nobody sees. Adding an entry to {@see FIELDS} is a licensing decision, and the
 * guard test asserts the constant's exact contents so it cannot be done quietly.
 *
 * WHAT IS NOT HERE, AND WHY IT IS LISTED
 * --------------------------------------
 * {@see EXCLUDED} names the categories deliberately withheld. It is documentation
 * with teeth: the guard test asserts none of those keys can appear in the
 * output, so a future edit that adds `PublicRemarks` to FIELDS fails the build
 * rather than shipping.
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
     * Every entry is an objective, publicly-advertised characteristic of the
     * building or the land. Nothing here is authored marketing prose, an
     * identifier, a price history, a person, or a term of the transaction.
     *
     * @var array<string, array<string,string>>  section => [RESO field => label]
     */
    public const FIELDS = [
        'Interior' => [
            'Appliances'            => 'Appliances',
            'Flooring'              => 'Flooring',
            'InteriorFeatures'      => 'Interior Features',
            'FireplaceYN'           => 'Fireplace',
            'FireplacesTotal'       => 'Fireplaces',
            'FireplaceFeatures'     => 'Fireplace Features',
            'Heating'               => 'Heating',
            'Cooling'               => 'Cooling',
            'LaundryFeatures'       => 'Laundry',
            'WindowFeatures'        => 'Windows',
            'Levels'                => 'Levels',
            'StoriesTotal'          => 'Stories',
            'BasementYN'            => 'Basement',
            'AccessibilityFeatures' => 'Accessibility Features',
            'FurnishedYN'           => 'Furnished',
            'Furnished'             => 'Furnished',
        ],

        'Exterior' => [
            'ExteriorFeatures'      => 'Exterior Features',
            'ConstructionMaterials' => 'Construction',
            'Roof'                  => 'Roof',
            'RoofType'              => 'Roof Type',
            'FoundationDetails'     => 'Foundation',
            'ArchitecturalStyle'    => 'Architectural Style',
            'PropertyCondition'     => 'Condition',
            'PatioAndPorchFeatures' => 'Patio & Porch',
            'Fencing'               => 'Fencing',
            'PoolPrivateYN'         => 'Private Pool',
            'PoolFeatures'          => 'Pool Features',
            'SpaYN'                 => 'Spa',
            'SpaFeatures'           => 'Spa Features',
            'View'                  => 'View',
            'WaterfrontYN'          => 'Waterfront',
            'WaterfrontFeatures'    => 'Waterfront Features',
            'WaterBodyName'         => 'Water Body',
            'LotFeatures'           => 'Lot Features',
            'LotDimensions'         => 'Lot Dimensions',
            'LotSizeAcres'          => 'Lot Size (Acres)',
            'Topography'            => 'Topography',
            'OtherStructures'       => 'Other Structures',
            'NewConstructionYN'     => 'New Construction',
        ],

        'Parking' => [
            'GarageYN'          => 'Garage',
            'GarageSpaces'      => 'Garage Spaces',
            'AttachedGarageYN'  => 'Attached Garage',
            'CarportYN'         => 'Carport',
            'CarportSpaces'     => 'Carport Spaces',
            'ParkingFeatures'   => 'Parking Features',
            'ParkingTotal'      => 'Total Parking',
            'OpenParkingYN'     => 'Open Parking',
        ],

        'Community' => [
            // AssociationName and AssociationPhone are DELIBERATELY absent — see
            // EXCLUDED. The feed's AssociationName is frequently a named
            // individual sitting beside a phone number, which is contact data
            // regardless of the column it arrives in.
            'AssociationYN'           => 'Association',
            'AssociationFee'          => 'Association Fee',
            'AssociationFeeFrequency' => 'Fee Frequency',
            'AssociationFeeIncludes'  => 'Fee Includes',
            'AssociationAmenities'    => 'Amenities',
            'CommunityFeatures'       => 'Community Features',
            'SeniorCommunityYN'       => 'Senior Community',
            'PetsAllowed'             => 'Pets Allowed',
            'SubdivisionName'         => 'Subdivision',
        ],

        'Utilities' => [
            'Utilities'   => 'Utilities',
            'Sewer'       => 'Sewer',
            'WaterSource' => 'Water Source',
            'Electric'    => 'Electric',
        ],

        'Property Details' => [
            'Zoning'            => 'Zoning',
            'ZoningDescription' => 'Zoning Description',
            'TaxAnnualAmount'   => 'Annual Taxes',
            'TaxYear'           => 'Tax Year',
            'TaxBlock'          => 'Tax Block',
            'TaxLot'            => 'Tax Lot',
            'YearBuilt'         => 'Year Built',
            'YearBuiltEffective'=> 'Effective Year Built',
            'MLSAreaMajor'      => 'MLS Area',
        ],
    ];

    /**
     * Field names that must never appear in this presenter's output.
     *
     * Not a filter — {@see FIELDS} being an allow-list already excludes
     * everything absent from it, so nothing here is load-bearing at runtime.
     * It is a named list of the categories withheld and WHY, checked by the
     * guard test, so that adding one to FIELDS breaks the build instead of
     * quietly publishing it.
     *
     * @var array<string,string>  field => reason
     */
    public const EXCLUDED = [
        // Authored prose, not a property fact. The locked decision names
        // remarks reuse alongside photo reuse as the licensing risk.
        'PublicRemarks'                => 'authored marketing prose',
        'STELLAR_PublicRemarksAgent'   => 'authored marketing prose',
        'PrivateRemarks'               => 'not public',
        'SyndicationRemarks'           => 'authored marketing prose',

        // Access and safety.
        'ShowingInstructions'          => 'access instructions',
        'STELLAR_ShowingConsiderations'=> 'access instructions',
        'LockBoxLocation'              => 'access instructions',
        'LockBoxType'                  => 'access instructions',

        // People. Names, licence numbers, phones and emails of the agents and
        // brokerages on the other side of this listing.
        'ListAgentFullName'            => 'agent identity',
        'ListAgentKey'                 => 'agent identity',
        'ListAgentMlsId'               => 'agent identity',
        'ListAgentDirectPhone'         => 'contact information',
        'ListAgentEmail'               => 'contact information',
        'ListOfficeName'               => 'brokerage identity',
        'ListOfficeKey'                => 'brokerage identity',
        'ListOfficePhone'              => 'contact information',
        'BuyerAgentFullName'           => 'agent identity',
        'BuyerOfficeName'              => 'brokerage identity',
        'AssociationName'              => 'frequently a named individual',
        'AssociationPhone'             => 'contact information',
        'STELLAR_AssociationEmail'     => 'contact information',

        // Compensation between brokerages is not a fact about the property, and
        // its display is separately regulated.
        'BuyerAgencyCompensation'      => 'broker compensation',
        'SubAgencyCompensation'        => 'broker compensation',

        // Provider bookkeeping. Meaningless to a consumer and a leak of our
        // integration's internals.
        'ListingKey'                   => 'internal identifier',
        'OriginatingSystemKey'         => 'internal identifier',
        'OriginatingSystemName'        => 'internal identifier',
        'SourceSystemName'             => 'internal identifier',

        // Media is handled by the gallery under its own policy, never rendered
        // as a text attribute here.
        'Media'                        => 'handled by MlsMediaPolicy',
        'VirtualTourURLBranded'        => 'carries listing brokerage branding',
    ];

    /**
     * Populated, permitted MLS attributes grouped for display.
     *
     * @param  array<string,mixed>  $raw  a decoded Bridge/RESO property record
     * @return array<string, list<array{label: string, value: string}>>
     *         section => rows; empty sections are omitted entirely
     */
    public function present(array $raw): array
    {
        $sections = [];

        foreach (self::FIELDS as $section => $fields) {
            $rows = [];

            foreach ($fields as $field => $label) {
                $value = $this->formatValue($raw[$field] ?? null);

                if ($value === null) {
                    continue;
                }

                // Two aliases can map to the same label (RoofType/Roof,
                // Furnished/FurnishedYN). Showing "Furnished: Yes" twice reads
                // as a rendering bug, so the first populated one wins.
                if ($this->hasLabel($rows, $label)) {
                    continue;
                }

                $rows[] = ['label' => $label, 'value' => $value];
            }

            if ($rows !== []) {
                $sections[$section] = $rows;
            }
        }

        return $sections;
    }

    public function hasAnything(array $raw): bool
    {
        return $this->present($raw) !== [];
    }

    /**
     * Render one value, or null when there is nothing to show.
     *
     * Multi-value RESO fields (Appliances, InteriorFeatures, …) arrive as
     * arrays and are joined in SOURCE ORDER, not sorted. The feed's own
     * sequence is a faithful record of what it said; re-sorting would be this
     * layer editorialising about a property it knows nothing about.
     *
     * Nothing here rewords, infers or summarises. "Size Limit" does not become
     * "size restrictions apply" — authored prose is precisely what the display
     * boundary excludes, and a presenter is the wrong place to invent it.
     */
    private function formatValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_bool($value)) {
            // A false boolean is dropped rather than rendered as "No". A
            // gallery of "Pool: No / Spa: No / Waterfront: No" is noise that
            // buries the facts the reader came for; absence already says it.
            return $value ? 'Yes' : null;
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item) || is_object($item)) {
                    continue;
                }
                $item = trim((string) $item);
                if ($item !== '' && ! in_array($item, $parts, true)) {
                    $parts[] = $item;
                }
            }

            return $parts === [] ? null : implode(', ', $parts);
        }

        if (is_object($value)) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        // Feed booleans arrive as strings on some columns. Normalised to the
        // same "Yes, or nothing" treatment as a real boolean so the two spellings
        // do not render differently on the same page.
        $lower = strtolower($string);
        if (in_array($lower, ['true', 'yes', 'y'], true)) {
            return 'Yes';
        }
        if (in_array($lower, ['false', 'no', 'n'], true)) {
            return null;
        }

        return $string;
    }

    /** @param list<array{label: string, value: string}> $rows */
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
