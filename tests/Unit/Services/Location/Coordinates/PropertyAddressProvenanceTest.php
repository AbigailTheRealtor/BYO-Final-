<?php

namespace Tests\Unit\Services\Location\Coordinates;

use App\Services\Location\Coordinates\PropertyAddress;
use Tests\TestCase;

/**
 * G2 gave PropertyAddress three record handles so the local adapters can find
 * the row that holds a coordinate. This suite pins down the thing that makes
 * that safe: the handles are carried, and they change nothing.
 *
 * The risk being closed is quiet. If a listing id reached
 * `coordinateCacheKeyInput()`, every listing would get its own cache entry for a
 * building that already had one, and the only symptom would be a provider bill
 * that grew with the listing count. If it reached `propertyIdentityLine()`, one
 * physical property would have as many identities as it had rows. Neither would
 * fail loudly, so both are asserted.
 */
class PropertyAddressProvenanceTest extends TestCase
{
    private function withHandles(): PropertyAddress
    {
        return new PropertyAddress(
            address:       '123 Main St',
            unitAddress:   'Unit 4A',
            city:          'Tampa',
            county:        'Hillsborough',
            state:         'FL',
            zip:           '33602',
            listingType:   'seller_agent_auction',
            listingId:     4321,
            mlsListingKey: 'STELLAR-MFR-1234567',
        );
    }

    private function withoutHandles(): PropertyAddress
    {
        return new PropertyAddress(
            address:     '123 Main St',
            unitAddress: 'Unit 4A',
            city:        'Tampa',
            county:      'Hillsborough',
            state:       'FL',
            zip:         '33602',
        );
    }

    public function test_handles_do_not_change_the_coordinate_lookup_line(): void
    {
        $this->assertSame(
            $this->withoutHandles()->coordinateLookupLine(),
            $this->withHandles()->coordinateLookupLine()
        );
    }

    public function test_handles_do_not_change_the_property_identity_line(): void
    {
        $this->assertSame(
            $this->withoutHandles()->propertyIdentityLine(),
            $this->withHandles()->propertyIdentityLine()
        );
    }

    public function test_handles_do_not_change_the_fingerprint_input(): void
    {
        $this->assertSame(
            $this->withoutHandles()->identityFingerprintInput(),
            $this->withHandles()->identityFingerprintInput()
        );
    }

    public function test_handles_do_not_change_the_coordinate_cache_key(): void
    {
        $this->assertSame(
            $this->withoutHandles()->coordinateCacheKeyInput(),
            $this->withHandles()->coordinateCacheKeyInput()
        );
    }

    public function test_two_listings_for_one_building_still_share_a_cache_key(): void
    {
        $listingA = new PropertyAddress(
            address: '123 Main St', unitAddress: 'Unit 4A', city: 'Tampa',
            state: 'FL', zip: '33602', listingType: 'seller_agent_auction', listingId: 1,
        );

        $listingB = new PropertyAddress(
            address: '123 Main St', unitAddress: 'Unit 9C', city: 'Tampa',
            state: 'FL', zip: '33602', listingType: 'landlord_agent_auction', listingId: 2,
        );

        $this->assertSame(
            $listingA->coordinateCacheKeyInput(),
            $listingB->coordinateCacheKeyInput(),
            'One building, one lookup — regardless of which table holds the listing'
        );

        $this->assertNotSame(
            $listingA->propertyIdentityLine(),
            $listingB->propertyIdentityLine(),
            'Still two distinct properties'
        );
    }

    public function test_handles_do_not_make_an_incomplete_address_resolvable(): void
    {
        $streetOnly = new PropertyAddress(
            address: '123 Main St', listingType: 'seller_agent_auction', listingId: 7, mlsListingKey: 'KEY',
        );

        $this->assertFalse(
            $streetOnly->hasMinimumForLookup(),
            'A record handle is not a substitute for a locatable address'
        );
    }

    // ── presence reporting ──────────────────────────────────────────────────

    public function test_a_listing_handle_needs_both_halves(): void
    {
        $this->assertTrue($this->withHandles()->hasListingHandle());

        $this->assertFalse(
            (new PropertyAddress(address: '1 A St', listingType: 'seller_agent_auction'))->hasListingHandle()
        );
        $this->assertFalse(
            (new PropertyAddress(address: '1 A St', listingId: 5))->hasListingHandle()
        );
        $this->assertFalse((new PropertyAddress(address: '1 A St'))->hasListingHandle());
    }

    public function test_a_whitespace_only_mls_key_is_not_a_key(): void
    {
        $this->assertTrue($this->withHandles()->hasMlsListingKey());
        $this->assertFalse((new PropertyAddress(mlsListingKey: '   '))->hasMlsListingKey());
        $this->assertFalse((new PropertyAddress())->hasMlsListingKey());
    }

    // ── fromArray ───────────────────────────────────────────────────────────

    public function test_from_array_reads_the_handles(): void
    {
        $address = PropertyAddress::fromArray([
            'address'         => '123 Main St',
            'property_city'   => 'Tampa',
            'property_state'  => 'FL',
            'property_zip'    => '33602',
            'listing_type'    => 'seller_agent_auction',
            'listing_id'      => '4321',
            'mls_listing_key' => 'STELLAR-MFR-1234567',
        ]);

        $this->assertSame('seller_agent_auction', $address->listingType);
        $this->assertSame(4321, $address->listingId);
        $this->assertSame('STELLAR-MFR-1234567', $address->mlsListingKey);
    }

    public function test_from_array_accepts_the_bridge_column_name_for_the_key(): void
    {
        $address = PropertyAddress::fromArray(['listing_key' => 'STELLAR-MFR-7654321']);

        $this->assertSame('STELLAR-MFR-7654321', $address->mlsListingKey);
    }

    public function test_from_array_leaves_the_handles_empty_when_absent(): void
    {
        $address = PropertyAddress::fromArray([
            'address'        => '123 Main St',
            'property_city'  => 'Tampa',
            'property_state' => 'FL',
            'property_zip'   => '33602',
        ]);

        $this->assertSame('', $address->listingType);
        $this->assertNull($address->listingId);
        $this->assertSame('', $address->mlsListingKey);
        $this->assertFalse($address->hasListingHandle());
        $this->assertFalse($address->hasMlsListingKey());
    }

    public function test_an_empty_string_listing_id_is_not_coerced_to_zero(): void
    {
        // (int) '' is 0, and listing 0 does not exist. Absent must stay absent.
        $address = PropertyAddress::fromArray(['listing_type' => 'seller_agent_auction', 'listing_id' => '']);

        $this->assertNull($address->listingId);
        $this->assertFalse($address->hasListingHandle());
    }
}
