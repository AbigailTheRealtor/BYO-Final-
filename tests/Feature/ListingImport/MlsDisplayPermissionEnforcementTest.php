<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\Mls\MlsListingDetailsReader;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Services\Stellar\PropertyDetailViewMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 — the feed's display permissions, enforced end to end.
 *
 * The 2026-09-04 payload audit's most urgent finding: `InternetAddressDisplayYN`
 * is false on 71 of 1,202 cached records, the import path read no display flag
 * at all, and the Stellar page read only `IDXParticipationYN`. So a listing the
 * MLS said to publish WITHOUT its address had it published on both surfaces.
 *
 * PRESERVATION AND DISPLAY ARE SEPARATE, AND BOTH ARE ASSERTED HERE.
 * The restricted address must still be imported, still stored, and still
 * available to its owner — the tests below check that it is, not merely that it
 * is hidden. A fix that deleted the data would pass a suppression test and be
 * badly wrong.
 */
class MlsDisplayPermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'PERM-KEY-1';
    private const MLS = 'PERM-MLS-1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
        ]);
    }

    private function seedRecord(array $flags = []): BridgeProperty
    {
        $raw = array_merge([
            'ListingKey'                     => self::KEY,
            'ListingId'                      => self::MLS,
            'StandardStatus'                 => 'Active',
            'MlsStatus'                      => 'Active',
            'PropertyType'                   => 'Residential',
            'UnparsedAddress'                => '2142 BRADFORD STREET UNIT 308',
            'UnitNumber'                     => '308',
            'City'                           => 'CLEARWATER',
            'StateOrProvince'                => 'FL',
            'PostalCode'                     => '33760',
            'ListPrice'                      => 100000,
            'SubdivisionName'                => 'Bradford Acres',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
        ], $flags);

        return BridgeProperty::create([
            'listing_key'      => self::KEY,
            'listing_id'       => self::MLS,
            'standard_status'  => 'Active',
            'property_type'    => 'Residential',
            'unparsed_address' => $raw['UnparsedAddress'],
            'city'             => $raw['City'],
            'state_or_province'=> $raw['StateOrProvince'],
            'postal_code'      => $raw['PostalCode'],
            'list_price'       => $raw['ListPrice'],
            'raw_json'         => json_encode($raw),
            'imported_at'      => now(),
        ]);
    }

    private function importFor(User $user): SellerAgentAuction
    {
        $result = app(MlsQuickImportService::class)->lookup(self::MLS, 'seller');

        $this->assertTrue($result->isFound());

        return app(MlsQuickImportDraftWriter::class)->materialise('seller', $user->id, $result);
    }

    private function metaOf(SellerAgentAuction $listing): array
    {
        $meta = [];

        foreach ($listing->fresh()->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : $row->meta_value;
        }

        return $meta;
    }

    // ─── Import path ─────────────────────────────────────────────────────────

    /** @test */
    public function the_import_records_the_feeds_display_permissions(): void
    {
        $this->seedRecord(['InternetAddressDisplayYN' => false]);
        $listing = $this->importFor(User::factory()->create());

        $meta = $this->metaOf($listing);

        $this->assertArrayHasKey(
            MlsQuickImportDraftWriter::META_DISPLAY_PERMISSIONS,
            $meta,
            'An MLS import must record the feed permissions it was made under'
        );

        $permissions = (new MlsListingDetailsReader())->permissionsFrom($meta);

        $this->assertTrue($permissions->listingDisplayable());
        $this->assertFalse($permissions->addressDisplayable());
    }

    /**
     * @test
     *
     * The address is still IMPORTED. Withholding it from the public is not the
     * same as refusing to hold it, and the owner needs it to run their listing.
     */
    public function a_restricted_address_is_still_imported_and_stored(): void
    {
        $this->seedRecord(['InternetAddressDisplayYN' => false]);
        $listing = $this->importFor(User::factory()->create());

        $meta = $this->metaOf($listing);

        $this->assertSame('2142 BRADFORD STREET UNIT 308', $meta['address'] ?? null);
        $this->assertSame('CLEARWATER', $meta['property_city'] ?? null);
    }

    /** @test */
    public function a_restricted_address_is_hidden_from_the_public_and_shown_to_the_owner(): void
    {
        $this->seedRecord(['InternetAddressDisplayYN' => false]);
        $listing = $this->importFor(User::factory()->create());

        $reader = new MlsListingDetailsReader();
        $meta   = $this->metaOf($listing);

        $this->assertFalse($reader->addressVisibleTo($meta, viewerIsOwner: false));
        $this->assertTrue($reader->addressVisibleTo($meta, viewerIsOwner: true));

        $this->assertSame(
            'The MLS does not permit this address to be displayed publicly.',
            $reader->addressRestrictionNotice($meta)
        );
    }

    /** @test */
    public function an_unrestricted_listing_shows_its_address_to_everyone(): void
    {
        $this->seedRecord();
        $listing = $this->importFor(User::factory()->create());

        $reader = new MlsListingDetailsReader();
        $meta   = $this->metaOf($listing);

        $this->assertTrue($reader->addressVisibleTo($meta, viewerIsOwner: false));
        $this->assertNull($reader->addressRestrictionNotice($meta));
    }

    /**
     * @test
     *
     * A listing with no MLS provenance — manual creation, or the Listing Link
     * importer — is not governed by MLS permissions, because the MLS never made
     * a statement about it.
     */
    public function a_non_mls_listing_is_not_governed_by_mls_permissions(): void
    {
        $reader = new MlsListingDetailsReader();

        $this->assertFalse($reader->isMlsImported([]));
        $this->assertTrue($reader->addressVisibleTo([], viewerIsOwner: false));
        $this->assertNull($reader->addressRestrictionNotice([]));
    }

    /**
     * @test
     *
     * A withdrawn listing keeps its property facts (they are not gated on the
     * listing flag) but publishes no attribution and no market data.
     */
    public function a_withdrawn_listing_publishes_no_contacts_or_listing_context(): void
    {
        $this->seedRecord([
            'InternetEntireListingDisplayYN' => false,
            'ListOfficeName'                 => 'Example Realty Group',
            'DaysOnMarket'                   => 16,
        ]);

        $listing = $this->importFor(User::factory()->create());
        $details = (new MlsListingDetailsReader())->detailsFrom($this->metaOf($listing));

        $this->assertSame([], $details->group('contacts'));
        $this->assertSame([], $details->group('listing'));
    }

    // ─── Stellar search / detail path ────────────────────────────────────────

    /** @test */
    public function the_stellar_detail_mapper_suppresses_a_restricted_address(): void
    {
        $listing = $this->seedRecord(['InternetAddressDisplayYN' => false]);

        $mapped = (new PropertyDetailViewMapper())->map($listing);

        $this->assertNull($mapped['address']);
        $this->assertNull($mapped['unit_number']);
        $this->assertFalse($mapped['address_display_permitted']);

        // City / state / ZIP survive: an address-suppressed IDX listing is meant
        // to show those, and blanking them would make the listing unusable.
        $this->assertSame('CLEARWATER', $mapped['city']);
        $this->assertSame('FL', $mapped['state']);
        $this->assertSame('33760', $mapped['postal_code']);
    }

    /** @test */
    public function the_stellar_detail_mapper_shows_a_permitted_address(): void
    {
        $listing = $this->seedRecord();

        $mapped = (new PropertyDetailViewMapper())->map($listing);

        $this->assertSame('2142 BRADFORD STREET UNIT 308', $mapped['address']);
        $this->assertSame('308', $mapped['unit_number']);
        $this->assertTrue($mapped['address_display_permitted']);
    }

    /** @test */
    public function the_stellar_detail_page_refuses_a_listing_withdrawn_from_idx(): void
    {
        $this->seedRecord(['IDXParticipationYN' => false]);

        $this->actingAs(User::factory()->create())
            ->get(route('stellar.property.show', ['listingKey' => self::KEY]))
            ->assertStatus(403);
    }

    /**
     * @test
     *
     * The second, independent refusal. Before this work only IDXParticipationYN
     * was checked, so a listing the MLS had withdrawn from internet display
     * entirely still rendered in full.
     */
    public function the_stellar_detail_page_refuses_a_listing_withdrawn_from_internet_display(): void
    {
        $this->seedRecord(['InternetEntireListingDisplayYN' => false]);

        $this->actingAs(User::factory()->create())
            ->get(route('stellar.property.show', ['listingKey' => self::KEY]))
            ->assertStatus(403);
    }

    /**
     * @test
     *
     * An address refusal is NOT a 403. A listing whose address may not be shown
     * is still a listing that may be shown, and conflating the two would hide a
     * page the MLS permits.
     */
    public function an_address_refusal_does_not_block_the_stellar_detail_page(): void
    {
        $this->seedRecord(['InternetAddressDisplayYN' => false]);

        $this->actingAs(User::factory()->create())
            ->get(route('stellar.property.show', ['listingKey' => self::KEY]))
            ->assertStatus(200)
            ->assertDontSee('2142 BRADFORD STREET');
    }
}
