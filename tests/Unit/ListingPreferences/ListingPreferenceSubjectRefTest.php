<?php

namespace Tests\Unit\ListingPreferences;

use App\Support\Listing\MlsProvider;
use App\Support\ListingPreferences\ListingPreferenceSubjectRef;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The durable identity model: two identities, and the collision it prevents.
 */
class ListingPreferenceSubjectRefTest extends TestCase
{
    /** @test */
    public function a_bridge_listing_is_keyed_on_its_mls_listing_key(): void
    {
        $subject = ListingPreferenceSubjectRef::mls(
            new SmartTagListingRef(SmartTagListingType::Bridge, 12345),
            MlsProvider::StellarBridge,
            'MFR123456789',
        );

        $this->assertSame('mls:stellar_bridge:MFR123456789', $subject->subjectKey);
        $this->assertSame(MlsProvider::StellarBridge, $subject->mlsProvider());
        $this->assertTrue($subject->isMlsSubject());
        $this->assertSame('MFR123456789', $subject->mlsListingKey());

        // The acted-on ref is kept alongside the key, not replaced by it.
        $this->assertSame(SmartTagListingType::Bridge, $subject->listingType());
        $this->assertSame(12345, $subject->listingId());
    }

    /** @test */
    public function a_native_listing_without_mls_provenance_is_keyed_on_itself(): void
    {
        $seller = ListingPreferenceSubjectRef::native(
            new SmartTagListingRef(SmartTagListingType::SellerAgent, 678)
        );
        $landlord = ListingPreferenceSubjectRef::native(
            new SmartTagListingRef(SmartTagListingType::LandlordAgent, 678)
        );

        $this->assertSame('byo:seller_agent:678', $seller->subjectKey);
        $this->assertSame('byo:landlord_agent:678', $landlord->subjectKey);
        $this->assertFalse($seller->isMlsSubject());
        $this->assertNull($seller->mlsListingKey());

        // Same id, different table — never the same subject.
        $this->assertFalse($seller->sameSubjectAs($landlord));
    }

    /**
     * THE collision this key exists to prevent: one property reaches a customer
     * as a Bridge MLS row on Explore and as the BidYourOffer listing imported
     * from it in results. Both must be ONE subject, or a Pass on the map would
     * not suppress the same property in results.
     *
     * @test
     */
    public function an_mls_linked_native_listing_shares_the_subject_of_its_bridge_row(): void
    {
        $bridge = ListingPreferenceSubjectRef::mls(
            new SmartTagListingRef(SmartTagListingType::Bridge, 12345),
            MlsProvider::StellarBridge,
            'MFR999',
        );
        $native = ListingPreferenceSubjectRef::mls(
            new SmartTagListingRef(SmartTagListingType::SellerAgent, 678),
            MlsProvider::StellarBridge,
            'MFR999',
        );

        $this->assertTrue($bridge->sameSubjectAs($native));
        $this->assertSame($bridge->subjectKey, $native->subjectKey);

        // …while remaining distinguishable as the listings they are.
        $this->assertNotSame($bridge->listingType(), $native->listingType());
        $this->assertNotSame($bridge->listingId(), $native->listingId());
    }

    /** @test */
    public function two_different_mls_listings_are_two_subjects(): void
    {
        $a = ListingPreferenceSubjectRef::mls(new SmartTagListingRef(SmartTagListingType::Bridge, 1), MlsProvider::StellarBridge, 'MFR1');
        $b = ListingPreferenceSubjectRef::mls(new SmartTagListingRef(SmartTagListingType::Bridge, 2), MlsProvider::StellarBridge, 'MFR2');

        $this->assertFalse($a->sameSubjectAs($b));
    }

    /** @test */
    public function a_bridge_listing_can_never_take_a_byo_subject_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ListingPreferenceSubjectRef::native(new SmartTagListingRef(SmartTagListingType::Bridge, 1));
    }

    /** @test */
    public function an_empty_mls_listing_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ListingPreferenceSubjectRef::mls(new SmartTagListingRef(SmartTagListingType::Bridge, 1), MlsProvider::StellarBridge, '   ');
    }

    /** @test */
    public function a_malformed_subject_key_is_refused(): void
    {
        foreach ([
            '', 'nonsense', 'byo:bridge:1', 'byo:seller_agent:0', 'byo:seller_agent:abc',
            'mls:',
            'mls:MFR123',                 // the legacy un-namespaced form
            'mls::MFR123',                // empty provider segment
            'mls:not_a_provider:MFR123',  // unrecognised provider
            'mls:stellar_bridge:',        // empty listing key
        ] as $bad) {
            try {
                new ListingPreferenceSubjectRef(
                    new SmartTagListingRef(SmartTagListingType::SellerAgent, 1),
                    $bad,
                );
                $this->fail("Malformed subject key accepted: " . var_export($bad, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Parcel and address grouping is deliberately absent — grouping successive
     * listings of one property is a different question, and answering it here
     * would merge listings a customer may feel differently about.
     *
     * @test
     */
    public function the_subject_ref_carries_no_parcel_or_coordinate_grouping(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Support/ListingPreferences/ListingPreferenceSubjectRef.php'
        );

        foreach (['ParcelNumber', 'parcel_number', 'latitude', 'longitude'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', (string) $source),
                "{$forbidden} must not participate in subject identity"
            );
        }
    }
}
