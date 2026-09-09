<?php

namespace Tests\Feature\ListingImport;

use App\Services\ListingImport\Mls\MlsFieldCatalog;
use App\Services\ListingImport\MlsFieldMap;
use Tests\TestCase;

/**
 * SEARCH-VS-IMPORT PARITY.
 *
 * A user can reach the same Stellar listing two ways: through
 * `/stellar/property/{listingKey}`, or by importing it into a BidYourOffer
 * listing. Before this test, 37 fields the Stellar page rendered had no
 * disposition at all on the import side — schools, days on market, original
 * list price, unit number, the virtual tour, the water-view flag — so the same
 * property looked materially poorer after importing it than before.
 *
 * The rule enforced here is not "both surfaces render identically". It is the
 * weaker and more useful one: **every field the Stellar detail page renders must
 * have an explicit, written-down disposition on the import side.** Mapped to a
 * form field, shown under MLS Details, shown as listing context or attribution,
 * handled as media, or withheld for a stated reason. What is forbidden is the
 * fourth outcome — no answer at all.
 *
 * If a future developer adds a field to `PropertyDetailViewMapper` and forgets
 * the import, this fails and names the field.
 */
class MlsSearchImportParityTest extends TestCase
{
    /**
     * The RESO keys `PropertyDetailViewMapper::map()` reads.
     *
     * Maintained by hand and pinned by the test below, which re-derives the list
     * from the mapper's source. A hand list alone would go stale; a derived list
     * alone would silently shrink if the mapper were refactored into helpers.
     * Both together fail loudly on either kind of change.
     *
     * @return list<string>
     */
    private function stellarDetailFields(): array
    {
        return [
            'ListingKey', 'MlsStatus', 'ListPrice', 'OriginalListPrice', 'UnparsedAddress',
            'UnitNumber', 'City', 'StateOrProvince', 'PostalCode', 'CountyOrParish',
            'SubdivisionName', 'Latitude', 'Longitude', 'PropertyType', 'PropertySubType',
            'NewConstructionYN', 'BedroomsTotal', 'BathroomsFull', 'BathroomsHalf',
            'BathroomsTotalInteger', 'LivingArea', 'LotSizeSquareFeet', 'LotSizeAcres',
            'YearBuilt', 'Stories', 'Levels', 'DaysOnMarket', 'OnMarketDate', 'PublicRemarks',
            'InteriorFeatures', 'ExteriorFeatures', 'CommunityFeatures', 'Appliances',
            'Cooling', 'Heating', 'ParkingFeatures', 'Flooring', 'ConstructionMaterials',
            'Roof', 'FoundationDetails', 'LaundryFeatures', 'FireplaceFeatures',
            'PoolFeatures', 'SpaFeatures', 'View', 'WaterfrontFeatures',
            'AccessibilityFeatures', 'OtherStructures', 'PatioAndPorchFeatures',
            'SecurityFeatures', 'WindowFeatures', 'Utilities', 'Sewer', 'WaterSource',
            'PoolPrivateYN', 'GarageYN', 'GarageSpaces', 'CarportSpaces', 'SpaYN',
            'WaterfrontYN', 'ViewYN', 'STELLAR_WaterViewYN', 'PetsAllowed',
            'SeniorCommunityYN', 'STELLAR_CDDYN', 'FireplaceYN', 'AssociationYN',
            'AssociationFee', 'AssociationFeeFrequency', 'AssociationName',
            'AssociationAmenities', 'TaxAnnualAmount', 'ElementarySchool',
            'MiddleOrJuniorSchool', 'HighSchool', 'ListOfficeName', 'Media',
            'VirtualTourURLUnbranded',
        ];
    }

    /**
     * @test
     *
     * The list above must still describe the mapper. Derived by scanning the
     * mapper's source for RESO keys it reads out of `$raw`, plus the native
     * columns it reads off the model.
     */
    public function the_pinned_stellar_field_list_still_matches_the_mapper(): void
    {
        $source = (string) file_get_contents(
            base_path('app/Services/Stellar/PropertyDetailViewMapper.php')
        );

        preg_match_all("/\\\$raw\\['([A-Za-z0-9_]+)'\\]/", $source, $matches);

        $readFromRaw = array_values(array_unique($matches[1]));
        $pinned      = $this->stellarDetailFields();

        $missing = array_values(array_diff($readFromRaw, $pinned));

        $this->assertSame(
            [],
            $missing,
            'PropertyDetailViewMapper reads RESO fields the parity list does not know about. '
            . 'Add them to stellarDetailFields() AND give them a disposition in MlsFieldCatalog: '
            . implode(', ', $missing)
        );
    }

    /**
     * @test
     *
     * THE PARITY ASSERTION. Zero unexplained "search has / import loses".
     */
    public function every_field_the_stellar_page_renders_has_an_import_disposition(): void
    {
        $unexplained = [];

        foreach ($this->stellarDetailFields() as $field) {
            if (MlsFieldCatalog::classify($field) === MlsFieldCatalog::D_UNKNOWN) {
                $unexplained[] = $field;
            }
        }

        $this->assertSame(
            [],
            $unexplained,
            'These fields render on /stellar/property/{key} and have no disposition on the '
            . 'import side, so importing the listing loses them with no reason recorded: '
            . implode(', ', $unexplained)
        );
    }

    /**
     * @test
     *
     * Each Stellar field's disposition, spelled out. This is the "explicit
     * disposition" half of the requirement — the test above proves every field
     * HAS one, this one makes the actual answers visible in the diff so a change
     * of policy cannot be made silently.
     */
    public function the_disposition_of_every_stellar_field_is_pinned(): void
    {
        $actual = [];

        foreach ($this->stellarDetailFields() as $field) {
            $actual[$field] = MlsFieldCatalog::classify($field);
        }

        $expected = [
            // Imported into an editable Create Offer field.
            'ListingKey' => 'tier1_byo', 'MlsStatus' => 'tier1_byo', 'ListPrice' => 'tier1_byo',
            'UnparsedAddress' => 'tier1_byo', 'City' => 'tier1_byo', 'StateOrProvince' => 'tier1_byo',
            'PostalCode' => 'tier1_byo', 'CountyOrParish' => 'tier1_byo', 'Latitude' => 'tier1_byo',
            'Longitude' => 'tier1_byo', 'PropertyType' => 'tier1_byo', 'PropertySubType' => 'tier1_byo',
            'BedroomsTotal' => 'tier1_byo', 'BathroomsTotalInteger' => 'tier1_byo',
            'LivingArea' => 'tier1_byo', 'LotSizeSquareFeet' => 'tier1_byo', 'LotSizeAcres' => 'tier1_byo',
            'YearBuilt' => 'tier1_byo', 'InteriorFeatures' => 'tier1_byo', 'Appliances' => 'tier1_byo',
            'Cooling' => 'tier1_byo', 'Heating' => 'tier1_byo', 'Flooring' => 'tier1_byo',
            'ConstructionMaterials' => 'tier1_byo', 'Roof' => 'tier1_byo',
            'FoundationDetails' => 'tier1_byo', 'Utilities' => 'tier1_byo', 'Sewer' => 'tier1_byo',
            'WaterSource' => 'tier1_byo', 'PoolPrivateYN' => 'tier1_byo', 'GarageYN' => 'tier1_byo',
            'WaterfrontYN' => 'tier1_byo', 'WaterfrontFeatures' => 'tier1_byo',
            'STELLAR_CDDYN' => 'tier1_byo', 'AssociationYN' => 'tier1_byo',
            'AssociationFee' => 'tier1_byo', 'AssociationFeeFrequency' => 'tier1_byo',
            'TaxAnnualAmount' => 'tier1_byo',

            // No editable equivalent — rendered under MLS Details.
            'SubdivisionName' => 'property_facts', 'NewConstructionYN' => 'property_facts',
            'BathroomsFull' => 'property_facts', 'BathroomsHalf' => 'property_facts',
            'Stories' => 'property_facts', 'Levels' => 'property_facts',
            'ExteriorFeatures' => 'property_facts', 'CommunityFeatures' => 'property_facts',
            'ParkingFeatures' => 'property_facts', 'LaundryFeatures' => 'property_facts',
            'FireplaceFeatures' => 'property_facts', 'PoolFeatures' => 'property_facts',
            'SpaFeatures' => 'property_facts', 'View' => 'property_facts',
            'AccessibilityFeatures' => 'property_facts', 'OtherStructures' => 'property_facts',
            'PatioAndPorchFeatures' => 'property_facts', 'SecurityFeatures' => 'property_facts',
            'WindowFeatures' => 'property_facts', 'GarageSpaces' => 'property_facts',
            'CarportSpaces' => 'property_facts', 'SpaYN' => 'property_facts',
            'ViewYN' => 'property_facts', 'STELLAR_WaterViewYN' => 'property_facts',
            'PetsAllowed' => 'property_facts', 'SeniorCommunityYN' => 'property_facts',
            'FireplaceYN' => 'property_facts', 'AssociationAmenities' => 'property_facts',
            'ElementarySchool' => 'property_facts', 'MiddleOrJuniorSchool' => 'property_facts',
            'HighSchool' => 'property_facts',

            // The MLS's own account of the listing.
            'OriginalListPrice' => 'listing_context', 'DaysOnMarket' => 'listing_context',
            'OnMarketDate' => 'listing_context', 'VirtualTourURLUnbranded' => 'listing_context',

            // Attribution, gated on the feed's display permissions.
            'AssociationName' => 'contacts', 'ListOfficeName' => 'contacts',

            // Address component — imported, and suppressed at render when the
            // feed forbids the address.
            'UnitNumber' => 'address_component',

            // Its own resource.
            'Media' => 'related_resource',

            // Withheld: authored marketing prose, per the locked licensing decision.
            'PublicRemarks' => 'restricted',
        ];

        // Compared key-by-key rather than as an ordered array: the pinned list is
        // grouped by DISPOSITION for a reader, while $actual follows the mapper's
        // own field order. Sorting both keeps the failure message about a changed
        // decision instead of a changed ordering.
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }

    /**
     * @test
     *
     * `MlsFieldCatalog::TIER1_MAPPED_BUT_UNRENDERED` is a claim about the Blade
     * templates, so it is checked against them. A view that starts or stops
     * rendering a Tier-1 destination changes whether that fact needs to appear
     * under MLS Details, and getting it wrong in either direction is a silent
     * loss or a duplicate row.
     */
    public function the_mapped_but_unrendered_exception_list_matches_the_templates(): void
    {
        $views = [
            'seller'   => base_path('resources/views/offer-listing/seller/view.blade.php'),
            'landlord' => base_path('resources/views/offer-listing/landlord/view.blade.php'),
        ];

        foreach ($views as $role => $file) {
            $blade = (string) file_get_contents($file);
            $map   = MlsFieldMap::forRole($role);

            $derived = [];

            foreach (MlsFieldCatalog::TIER1_BYO as $field => $canonicalKey) {
                $target = $map[$canonicalKey] ?? null;

                if ($target === null) {
                    continue;
                }

                $meta = ltrim($target, '*');

                if (! str_contains($blade, "'{$meta}'") && ! str_contains($blade, "\"{$meta}\"")) {
                    $derived[] = $field;
                }
            }

            $this->assertSame(
                MlsFieldCatalog::TIER1_MAPPED_BUT_UNRENDERED[$role] ?? [],
                $derived,
                "The {$role} listing view no longer renders the same set of Tier-1 destinations. "
                . 'Update MlsFieldCatalog::TIER1_MAPPED_BUT_UNRENDERED — a mapped field the page '
                . 'does not render must stay in MLS Details, and one it does render must not be '
                . 'duplicated there.'
            );
        }
    }
}
