<?php

namespace Tests\Unit\ListingImport\Mls;

use App\Services\ListingImport\Mls\MlsDisplayPermissions;
use Tests\TestCase;

/**
 * The feed's own display controls, and the one rule that matters:
 * an explicit `false` is a refusal that nothing can override.
 *
 * The 2026-09-04 payload audit found `InternetAddressDisplayYN` false on 71 of
 * 1,202 cached records and no code path reading it — so those addresses were
 * published against the MLS's instruction on the Stellar page and would have
 * been on every imported listing. These tests are what stop that returning.
 */
class MlsDisplayPermissionsTest extends TestCase
{
    /** @test */
    public function an_explicit_false_on_idx_participation_blocks_the_whole_listing(): void
    {
        $p = MlsDisplayPermissions::fromRecord(['IDXParticipationYN' => false]);

        $this->assertFalse($p->listingDisplayable());
        $this->assertFalse($p->addressDisplayable());
        $this->assertFalse($p->automatedValuationDisplayable());
        $this->assertFalse($p->consumerCommentDisplayable());
    }

    /** @test */
    public function an_explicit_false_on_entire_listing_display_blocks_the_whole_listing(): void
    {
        // The second, independent refusal. Before this class only
        // IDXParticipationYN was read, so a listing the MLS had withdrawn from
        // internet display entirely still rendered.
        $p = MlsDisplayPermissions::fromRecord([
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => false,
        ]);

        $this->assertFalse($p->listingDisplayable());
    }

    /** @test */
    public function an_address_refusal_does_not_block_the_listing_itself(): void
    {
        $p = MlsDisplayPermissions::fromRecord([
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => false,
        ]);

        // A listing whose ADDRESS may not be shown is still a listing that may
        // be shown. Conflating the two would 403 a page the MLS permits.
        $this->assertTrue($p->listingDisplayable());
        $this->assertFalse($p->addressDisplayable());
        $this->assertSame(
            'The MLS does not permit this address to be displayed publicly.',
            $p->addressWithheldReason()
        );
    }

    /** @test */
    public function string_booleans_are_read_the_same_as_native_ones(): void
    {
        foreach (['false', 'FALSE', '0', 'N', 'no'] as $falsey) {
            $this->assertFalse(
                MlsDisplayPermissions::fromRecord(['InternetAddressDisplayYN' => $falsey])->addressDisplayable(),
                "'{$falsey}' must read as a refusal"
            );
        }

        foreach (['true', 'TRUE', '1', 'Y', 'yes'] as $truthy) {
            $this->assertTrue(
                MlsDisplayPermissions::fromRecord(['InternetAddressDisplayYN' => $truthy])->addressDisplayable(),
                "'{$truthy}' must read as permission"
            );
        }
    }

    /**
     * @test
     *
     * Absence is permission, deliberately. These columns are populated on
     * 1,202/1,202 records in this feed, so a missing one means "this record
     * predates the column", not "permission withheld" — and treating absence as
     * a refusal would blank every address the day Stellar renamed a column.
     * The refusal that matters is an explicit false, and that is absolute.
     */
    public function a_missing_flag_permits_rather_than_refuses(): void
    {
        $p = MlsDisplayPermissions::fromRecord([]);

        $this->assertTrue($p->listingDisplayable());
        $this->assertTrue($p->addressDisplayable());
        $this->assertNull($p->addressWithheldReason());
    }

    /**
     * @test
     *
     * "We could not read the record" is a different statement from "the record
     * set no permissions", and the two must not resolve the same way.
     */
    public function deny_all_permits_nothing(): void
    {
        $p = MlsDisplayPermissions::denyAll();

        $this->assertFalse($p->listingDisplayable());
        $this->assertFalse($p->addressDisplayable());
    }

    /** @test */
    public function permissions_round_trip_through_the_persisted_shape(): void
    {
        $original = MlsDisplayPermissions::fromRecord([
            'IDXParticipationYN'                  => true,
            'InternetEntireListingDisplayYN'      => true,
            'InternetAddressDisplayYN'            => false,
            'InternetAutomatedValuationDisplayYN' => false,
            'InternetConsumerCommentYN'           => true,
        ]);

        $restored = MlsDisplayPermissions::fromStored($original->toArray());

        $this->assertSame($original->toArray(), $restored->toArray());
        $this->assertTrue($restored->listingDisplayable());
        $this->assertFalse($restored->addressDisplayable());
        $this->assertFalse($restored->automatedValuationDisplayable());
        $this->assertTrue($restored->consumerCommentDisplayable());
    }

    /**
     * @test
     *
     * A listing with no stored MLS permissions at all — a manual creation, or
     * one from the Listing Link importer — is not governed by them. The MLS
     * never made a statement about it, so there is nothing to enforce.
     */
    public function a_listing_with_no_stored_permissions_is_unrestricted(): void
    {
        $p = MlsDisplayPermissions::fromStored(null);

        $this->assertTrue($p->listingDisplayable());
        $this->assertTrue($p->addressDisplayable());
    }
}
