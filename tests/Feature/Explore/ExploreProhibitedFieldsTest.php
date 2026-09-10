<?php

namespace Tests\Feature\Explore;

use App\Models\User;
use App\Services\Explore\ExploreListingProjection;
use App\Services\ListingImport\Mls\MlsFieldCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * §39 / §50 D–K, P — the DTO is the security boundary, and this is the test
 * that fails when somebody widens it.
 *
 * The records built here carry REAL prohibited values under their REAL feed
 * field names, on listings that Explore genuinely publishes. That matters: a
 * test using invented field names would pass while the actual fields leaked.
 */
class ExploreProhibitedFieldsTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    /**
     * Prohibited values, seeded under the exact fields Stellar sends them in.
     * Every one of these is present in the live payload on records this feature
     * publishes.
     */
    private const POISONED = [
        // Occupant identity and contact.
        'STELLAR_TenantName'              => 'PROHIBITED-occupant-name',
        'STELLAR_TenantPhone'             => 'PROHIBITED-occupant-phone',
        // Physical access to somebody's home.
        'LockBoxLocation'                 => 'PROHIBITED-lockbox-location',
        'LockBoxSerialNumber'             => 'PROHIBITED-lockbox-serial',
        'LockBoxType'                     => 'PROHIBITED-lockbox-type',
        'ShowingInstructions'             => 'PROHIBITED-showing-instructions',
        'STELLAR_ShowingRequirements'     => 'PROHIBITED-showing-requirements',
        'STELLAR_ShowingConsiderations'   => 'PROHIBITED-showing-considerations',
        'STELLAR_CallCenterPhoneNumber'   => 'PROHIBITED-call-centre',
        // Broker-only prose.
        'PrivateRemarks'                  => 'PROHIBITED-private-remarks',
        'STELLAR_RealtorInfoConfidential' => 'PROHIBITED-confidential-realtor-info',
        'STELLAR_SoldRemarks'             => 'PROHIBITED-sold-remarks',
        // Authored prose withheld on licensing grounds.
        'PublicRemarks'                   => 'PROHIBITED-public-remarks',
        'SyndicationRemarks'              => 'PROHIBITED-syndication-remarks',
        // The listing agreement.
        'ListingTerms'                    => 'PROHIBITED-listing-agreement',
        // Owner details.
        'OwnerName'                       => 'PROHIBITED-owner-name',
        'OwnerPhone'                      => 'PROHIBITED-owner-phone',
        'OwnerEmail'                      => 'PROHIBITED-owner-email',
        // Listing agent and brokerage contact.
        'ListAgentFullName'               => 'PROHIBITED-agent-name',
        'ListAgentDirectPhone'            => 'PROHIBITED-agent-phone',
        'ListAgentEmail'                  => 'PROHIBITED-agent-email',
        'ListOfficePhone'                 => 'PROHIBITED-office-phone',
        // Internal provider keys.
        'ListingKeyNumeric'               => 'PROHIBITED-provider-key',
        'SourceSystemKey'                 => 'PROHIBITED-source-key',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['explore.enabled' => true]);
    }

    private function poisonedListing(): void
    {
        $this->makeListing([], self::POISONED);
    }

    /** @test */
    public function no_prohibited_value_reaches_the_viewport_response(): void
    {
        $this->poisonedListing();

        $body = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->assertOk()
            ->getContent();

        // Sanity: the listing IS being published, so absence is suppression
        // rather than the record simply not being there.
        $this->assertStringContainsString('"transaction_type":"sale"', $body);

        foreach (self::POISONED as $field => $value) {
            $this->assertStringNotContainsString($value, $body, "value of {$field} leaked");
            $this->assertStringNotContainsString($field, $body, "field name {$field} leaked");
        }
    }

    /** @test */
    public function no_prohibited_value_reaches_the_property_panel(): void
    {
        $this->poisonedListing();

        $listingKey = \App\Models\BridgeProperty::query()->value('listing_key');

        $body = $this->getJson('/api/explore/listings/' . $listingKey)->assertOk()->getContent();

        foreach (self::POISONED as $field => $value) {
            $this->assertStringNotContainsString($value, $body, "value of {$field} leaked to the panel");
            $this->assertStringNotContainsString($field, $body, "field name {$field} leaked to the panel");
        }
    }

    /** @test */
    public function an_authenticated_visitor_receives_no_more_than_an_anonymous_one(): void
    {
        $this->poisonedListing();

        $this->actingAs(User::factory()->create());

        $body = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->assertOk()
            ->getContent();

        foreach (self::POISONED as $field => $value) {
            $this->assertStringNotContainsString($value, $body, "{$field} leaked to a signed-in visitor");
        }
    }

    /**
     * The general form of the rule, checked against the classification
     * authority rather than against a hand-written list.
     *
     * Every field MlsFieldCatalog classifies as RESTRICTED (withheld for a
     * stated licensing or privacy reason) or INTERNAL (provider plumbing) is
     * absent from the projection's key set. A deny-list would have to be kept
     * complete forever against a feed that gains fields without asking us; this
     * asserts the allow-list stayed narrow.
     *
     * @test
     */
    public function the_projection_exposes_no_field_the_catalog_withholds(): void
    {
        $this->makeListing();

        $keys = array_keys(
            $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
                ->json('listings.0')
        );

        $withheld = array_merge(
            array_keys(MlsFieldCatalog::RESTRICTED),
            array_keys(MlsFieldCatalog::INTERNAL),
        );

        foreach ($withheld as $field) {
            $this->assertNotContains($field, $keys, "{$field} must never be a projection key");

            // Also in the snake_case shape a careless mapping would produce.
            $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace('STELLAR_', '', $field)));
            $this->assertNotContains($snake, $keys, "{$snake} (from {$field}) must never be a projection key");
        }
    }

    /**
     * Contact data is gated on the feed's own permissions everywhere else in
     * this codebase and is simply not part of Explore at all.
     *
     * @test
     */
    public function no_contact_field_is_part_of_the_projection(): void
    {
        $this->makeListing();

        $keys = array_keys(
            $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
                ->json('listings.0')
        );

        foreach (MlsFieldCatalog::CONTACTS as $fields) {
            foreach (array_keys($fields) as $field) {
                $this->assertNotContains($field, $keys, "{$field} must never be a projection key");
            }
        }
    }

    /**
     * The projection has no dynamic expansion. Its wire shape is exactly the
     * keys written by hand in toArray(), so a field cannot arrive by being
     * present on the input.
     *
     * @test
     */
    public function the_wire_shape_cannot_grow_keys_from_its_input(): void
    {
        $this->poisonedListing();

        $projected = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->json('listings.0');

        $expected = [
            'id', 'provider', 'property_id', 'transaction_type', 'latitude', 'longitude',
            'effective_status', 'display_price', 'price_value', 'price_qualifier',
            'address', 'city', 'state', 'postal_code', 'beds', 'baths', 'living_area',
            'property_type', 'property_subtype', 'primary_thumbnail', 'has_photos',
            'photo_count', 'photo_urls', 'has_virtual_tour', 'virtual_tour_url',
            'has_video', 'video_url', 'canonical_url', 'detail_url', 'showing_available',
            'attribution', 'match_score',
        ];

        sort($expected);
        $actual = array_keys($projected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    /**
     * The parcel number is an assessor identifier that leads to owner records.
     * It is used server-side to group a physical property's listings and never
     * emitted; only an opaque hash leaves.
     *
     * @test
     */
    public function the_parcel_number_is_never_published(): void
    {
        $this->makeListing([], ['ParcelNumber' => '183116855380170208', 'UnitNumber' => '17208']);

        $body = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->getContent();

        $this->assertStringNotContainsString('183116855380170208', $body);
        $this->assertStringNotContainsString('ParcelNumber', $body);

        $propertyId = json_decode($body, true)['listings'][0]['property_id'];
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $propertyId);
    }

    /**
     * Two units in one building share a parcel and must NOT collapse into one
     * property. A grouping rule that dropped the unit would merge every
     * condominium in a tower into a single history.
     *
     * @test
     */
    public function two_units_sharing_a_parcel_keep_separate_property_identities(): void
    {
        $this->makeListing([], ['ParcelNumber' => 'SAME-PARCEL', 'UnitNumber' => '101']);
        $this->makeListing([], ['ParcelNumber' => 'SAME-PARCEL', 'UnitNumber' => '201']);

        $ids = array_column(
            $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->json('listings'),
            'property_id'
        );

        $this->assertCount(2, $ids);
        $this->assertNotSame($ids[0], $ids[1], 'unit 101 and unit 201 are not the same property');
    }

    /** @test */
    public function the_same_unit_in_the_same_building_keeps_one_property_identity(): void
    {
        $this->makeListing([], ['ParcelNumber' => 'SAME-PARCEL', 'UnitNumber' => '101']);
        $this->makeListing([], ['ParcelNumber' => 'SAME-PARCEL', 'UnitNumber' => '101']);

        $ids = array_column(
            $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->json('listings'),
            'property_id'
        );

        $this->assertCount(2, $ids);
        $this->assertSame($ids[0], $ids[1], 'the same unit is one property across two listings');
    }

    /**
     * Proximity is never identity. Two neighbouring houses at almost the same
     * coordinate are two properties.
     *
     * @test
     */
    public function nearby_coordinates_never_merge_two_properties(): void
    {
        $this->makeListing(
            ['unparsed_address' => '10 Close Street', 'latitude' => self::LAT, 'longitude' => self::LNG],
            ['ParcelNumber' => null, 'UnparsedAddress' => '10 Close Street', 'UnitNumber' => null]
        );
        $this->makeListing(
            ['unparsed_address' => '12 Close Street', 'latitude' => self::LAT + 0.00001, 'longitude' => self::LNG],
            ['ParcelNumber' => null, 'UnparsedAddress' => '12 Close Street', 'UnitNumber' => null]
        );

        $ids = array_column(
            $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->json('listings'),
            'property_id'
        );

        $this->assertCount(2, $ids);
        $this->assertNotSame($ids[0], $ids[1]);
    }

    /** @test */
    public function the_projection_class_declares_only_readonly_properties(): void
    {
        $reflection = new \ReflectionClass(ExploreListingProjection::class);

        $this->assertTrue($reflection->isFinal(), 'the boundary must not be subclassable');

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "{$property->getName()} must be readonly — a mutable boundary is not one"
            );
        }
    }
}
