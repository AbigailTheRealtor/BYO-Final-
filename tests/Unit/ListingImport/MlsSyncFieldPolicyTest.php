<?php

namespace Tests\Unit\ListingImport;

use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\Sync\MlsFactProjection;
use App\Services\ListingImport\Sync\MlsSyncFieldPolicy;
use App\Support\Listing\MlsLinkedListingStatus;
use App\Support\Listing\MlsSourceStatus;
use PHPUnit\Framework\TestCase;

/**
 * The write boundary, tested at source.
 *
 * These are the negative controls for the live-sync feature: not "does the
 * listing follow the feed" — the feature test covers that — but "is there any
 * route by which the feed reaches something it does not own".
 *
 * Extends PHPUnit's TestCase directly, with no application booted. That is
 * deliberate and is a property being asserted: this boundary must not depend on
 * a container, because it is consulted from paths that do not have one. The same
 * lesson as LandlordScreeningPolicy, whose `config()` call raised inside an
 * extractor and surfaced several frames away as an empty listing context.
 */
class MlsSyncFieldPolicyTest extends TestCase
{
    // =====================================================================
    // The intersection
    // =====================================================================

    /** @test */
    public function every_syncable_target_is_named_in_the_shared_field_map(): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $map = MlsFieldMap::forRole($role);

            foreach (MlsSyncFieldPolicy::syncableTargets($role) as $canonicalKey => $target) {
                $this->assertArrayHasKey(
                    $canonicalKey,
                    $map,
                    "Sync would write '{$canonicalKey}' for {$role}, which the import map does not define. "
                    . 'That is a second mapping, which is the thing this design forbids.'
                );
                $this->assertSame($map[$canonicalKey], $target);
            }
        }
    }

    /** @test */
    public function a_field_reaches_sync_by_being_named_and_never_by_escaping_a_list(): void
    {
        // An invented canonical key is not syncable. If this ever passes by
        // default, the policy has become a deny-list and fails open.
        $this->assertFalse(MlsSyncFieldPolicy::allowsFact('seller', 'some_field_added_next_year'));
        $this->assertFalse(MlsSyncFieldPolicy::allowsFact('landlord', 'some_field_added_next_year'));
    }

    /** @test */
    public function an_unsupported_role_can_sync_nothing(): void
    {
        $this->assertSame([], MlsSyncFieldPolicy::syncableTargets('buyer'));
        $this->assertSame([], MlsSyncFieldPolicy::syncableTargets('tenant'));
        $this->assertSame([], MlsSyncFieldPolicy::syncableTargets('nonsense'));
    }

    // =====================================================================
    // Fields whose NAME matches and whose MEANING does not
    // =====================================================================

    /**
     * @test
     *
     * @dataProvider meaningMismatchedFields
     */
    public function a_field_whose_meaning_differs_from_its_name_is_never_synced(string $canonicalKey): void
    {
        $this->assertFalse(
            MlsSyncFieldPolicy::allowsFact('seller', $canonicalKey),
            "'{$canonicalKey}' is syncable, but it does not mean what the MLS field of that name means. "
            . 'Syncing it would rewrite the seller\'s own intent on a schedule.'
        );

        $this->assertArrayHasKey($canonicalKey, MlsSyncFieldPolicy::neverSyncReasons());
    }

    public static function meaningMismatchedFields(): array
    {
        return [
            "seller's minimum cap rate"   => ['cap_rate'],
            "seller's minimum NOI"        => ['net_operating_income'],
            "business minimum net income" => ['annual_net_income_business'],
            'garage yes/no not a count'   => ['garage_spaces'],
            'parking yes/no not a count'  => ['parking_spaces_count'],
            'address unit not unit count' => ['number_of_units'],
        ];
    }

    /** @test */
    public function the_address_block_is_not_synced_in_this_phase(): void
    {
        foreach (['address', 'city', 'state', 'zip', 'county', 'latitude', 'longitude'] as $key) {
            $this->assertFalse(
                MlsSyncFieldPolicy::allowsFact('seller', $key),
                "'{$key}' is syncable, but an address change cascades into the coordinate ladder "
                . 'and Location DNA, neither of which is in this phase.'
            );
        }
    }

    // =====================================================================
    // Protected BidYourOffer keys
    // =====================================================================

    /**
     * @test
     *
     * @dataProvider ownerProtectedKeys
     */
    public function an_owner_authored_key_is_protected(string $metaKey): void
    {
        $this->assertTrue(
            MlsSyncFieldPolicy::isProtectedMetaKey($metaKey),
            "'{$metaKey}' is not protected from MLS sync, and the owner's contract names it as user-owned."
        );
    }

    public static function ownerProtectedKeys(): array
    {
        return array_map(
            static fn (string $k) => [$k],
            array_combine(
                $keys = [
                    'listing_method', 'service_type', 'auction_type', 'auction_time',
                    'contract_terms', 'important_info', 'offered_financing',
                    'seller_motivation', 'price_firmness',
                    'starting_price', 'reserve_price', 'buy_now_price',
                    'bidding_starts_at', 'bidding_ends_at', 'expiration_date',
                    'listing_status', 'offer_requirements', 'showing_instructions',
                    'compatibility_preferences', 'property_photos',
                ],
                $keys
            )
        );
    }

    /** @test */
    public function no_protected_key_is_reachable_as_a_sync_target_for_either_role(): void
    {
        foreach (['seller', 'landlord'] as $role) {
            foreach (MlsSyncFieldPolicy::syncableTargets($role) as $canonicalKey => $target) {
                $this->assertFalse(
                    MlsSyncFieldPolicy::isProtectedMetaKey($target),
                    "Sync target '{$target}' (from '{$canonicalKey}', role {$role}) is a protected key."
                );
            }
        }
    }

    // =====================================================================
    // The projector's two precedences
    // =====================================================================

    /** @test */
    public function import_mode_leaves_a_populated_field_alone_and_sync_mode_updates_it(): void
    {
        $projection = new MlsFactProjection();
        $facts      = ['bedrooms' => '4'];
        $existing   = ['bedrooms' => '2'];

        $import = $projection->project('seller', $facts, $existing, MlsFactProjection::MODE_IMPORT);
        $sync   = $projection->project('seller', $facts, $existing, MlsFactProjection::MODE_SYNC);

        $this->assertArrayNotHasKey('bedrooms', $import, 'A re-import overwrote a value the user already had');
        $this->assertSame('4', $sync['bedrooms'], 'A sync failed to bring an MLS fact into line');
    }

    /** @test */
    public function sync_mode_still_refuses_a_protected_target(): void
    {
        $writes = (new MlsFactProjection())->project(
            'seller',
            // Real canonical keys whose targets are BYO-owned or meaning-mismatched.
            ['cap_rate' => '7.5', 'number_of_units' => '12'],
            [],
            MlsFactProjection::MODE_SYNC,
        );

        $this->assertArrayNotHasKey('minimum_cap_rate', $writes);
        $this->assertArrayNotHasKey('unit_number', $writes);
    }

    // =====================================================================
    // The landlord rent hazard
    // =====================================================================

    /** @test */
    public function a_landlord_rent_is_only_synced_from_a_lease_record(): void
    {
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('landlord', 'Residential Lease'));
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('landlord', 'Commercial Lease'));

        // A sale ListPrice must never become a monthly rent.
        $this->assertFalse(MlsSyncFieldPolicy::allowsPriceSync('landlord', 'Residential'));
        $this->assertFalse(MlsSyncFieldPolicy::allowsPriceSync('landlord', 'Commercial Sale'));
        $this->assertFalse(MlsSyncFieldPolicy::allowsPriceSync('landlord', null));
        $this->assertFalse(MlsSyncFieldPolicy::allowsPriceSync('landlord', ''));

        // The seller side has no such hazard: a sale price is a sale price.
        $this->assertTrue(MlsSyncFieldPolicy::allowsPriceSync('seller', 'Residential'));
    }

    /** @test */
    public function the_projector_drops_a_landlord_rent_from_a_sale_record(): void
    {
        $projection = new MlsFactProjection();

        $fromSale = $projection->project(
            'landlord',
            ['price' => '450000'],
            [],
            MlsFactProjection::MODE_SYNC,
            'Residential',
        );

        $fromLease = $projection->project(
            'landlord',
            ['price' => '2500'],
            [],
            MlsFactProjection::MODE_SYNC,
            'Residential Lease',
        );

        $this->assertArrayNotHasKey('desired_rental_amount', $fromSale);
        $this->assertSame('2500', $fromLease['desired_rental_amount']);
    }

    // =====================================================================
    // Status vocabulary
    // =====================================================================

    /** @test */
    public function the_probe_confirmed_statuses_are_recognised(): void
    {
        foreach (MlsSourceStatus::PROBE_CONFIRMED as $status) {
            $this->assertTrue(MlsSourceStatus::isRecognised($status));
            $this->assertTrue(MlsSourceStatus::isProbeConfirmed($status));
        }
    }

    /** @test */
    public function the_owner_declared_statuses_are_recognised_but_not_claimed_as_observed(): void
    {
        foreach (MlsSourceStatus::OWNER_DECLARED as $status) {
            $this->assertTrue(
                MlsSourceStatus::isRecognised($status),
                "'{$status}' is in the owner's transition contract and must be handled."
            );
            $this->assertFalse(
                MlsSourceStatus::isProbeConfirmed($status),
                "'{$status}' was NOT returned by the live dataset probe; this file must not imply it was."
            );
        }
    }

    /** @test */
    public function an_unknown_status_is_not_recognised_and_is_not_inferred_to_be_off_market(): void
    {
        $this->assertFalse(MlsSourceStatus::isRecognised('Zorbulated'));
        $this->assertFalse(
            MlsSourceStatus::isOffMarket('Zorbulated'),
            'An unfamiliar status was read as off-market. Guessing what an unknown word means is the '
            . 'exact silent misreading the unknown-status rule exists to prevent.'
        );
    }

    /** @test */
    public function off_market_statuses_are_identified_without_being_acted_on(): void
    {
        foreach (['Closed', 'Expired', 'Withdrawn', 'Canceled', 'Temporarily Off Market'] as $status) {
            $this->assertTrue(MlsSourceStatus::isOffMarket($status));
        }

        foreach (['Active', 'Pending', 'Coming Soon', 'Active Under Contract'] as $status) {
            $this->assertFalse(MlsSourceStatus::isOffMarket($status));
        }
    }

    // =====================================================================
    // Which listings Stellar governs
    // =====================================================================

    /** @test */
    public function a_manual_listing_keeps_its_own_lifecycle(): void
    {
        $this->assertFalse(MlsLinkedListingStatus::isLinked([]));
        $this->assertNull(
            MlsLinkedListingStatus::marketStatus(['expiration_date' => '2020-01-01']),
            'A manual listing was handed an MLS market status'
        );
    }

    /** @test */
    public function an_mls_linked_listing_with_no_stored_status_falls_through_rather_than_inventing_one(): void
    {
        $meta = ['mls_listing_key' => 'ABC123'];

        $this->assertTrue(MlsLinkedListingStatus::isLinked($meta));
        $this->assertNull(
            MlsLinkedListingStatus::marketStatus($meta),
            "An MLS listing that has never synced was given a status the feed never supplied."
        );
    }

    /** @test */
    public function standard_status_outranks_mls_status_where_the_two_disagree(): void
    {
        // The exact disagreement the live probe observed on a real record.
        $status = MlsLinkedListingStatus::marketStatus([
            'mls_listing_key'     => 'ABC123',
            'mls_standard_status' => 'Closed',
            'mls_source_status'   => 'Sold',
        ]);

        $this->assertSame('Closed', $status);
    }

    /** @test */
    public function an_unrecognised_stored_status_is_still_reported_verbatim(): void
    {
        $this->assertSame('Zorbulated', MlsLinkedListingStatus::marketStatus([
            'mls_listing_key'     => 'ABC123',
            'mls_standard_status' => 'Zorbulated',
        ]));
    }
}
