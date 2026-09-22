<?php

namespace Tests\Unit\Support\Listing;

use App\Services\Canonical\Adapters\MlsListingAdapter;
use App\Services\ListingImport\MlsNormalizer;
use App\Support\Listing\MlsProvider;
use App\Support\ListingPreferences\ListingPreferenceSubjectRef;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use PHPUnit\Framework\TestCase;

/**
 * P0-5 — the one producer of a provider-scoped MLS identity, and the one reading
 * of a feed rent period. Pure: no container, no database.
 */
class MlsNativeIdentityTest extends TestCase
{
    public function test_native_identity_is_provider_scoped(): void
    {
        $this->assertSame('mls:stellar_bridge:K-123', MlsProvider::StellarBridge->nativeIdentity('K-123'));
        $this->assertSame('mls:stellar_bridge:K-123', MlsProvider::StellarBridge->nativeIdentity('  K-123 '));
    }

    public function test_a_blank_key_names_no_record(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MlsProvider::StellarBridge->nativeIdentity('   ');
    }

    /**
     * The preference subject key now delegates to nativeIdentity(). Stored
     * `listing_preferences.subject_key` values must not move by a byte.
     */
    public function test_the_preference_subject_key_is_byte_identical_to_before(): void
    {
        $ref = new SmartTagListingRef(SmartTagListingType::Bridge, 42);

        $subject = ListingPreferenceSubjectRef::mls($ref, MlsProvider::StellarBridge, ' FIXTURE-K1 ');

        $this->assertSame(
            ListingPreferenceSubjectRef::PREFIX_MLS . ':' . MlsProvider::StellarBridge->value . ':FIXTURE-K1',
            $subject->subjectKey,
        );
        $this->assertSame('mls:stellar_bridge:FIXTURE-K1', $subject->subjectKey);
        $this->assertSame(MlsProvider::StellarBridge->nativeIdentity('FIXTURE-K1'), $subject->subjectKey);
    }

    public function test_a_blank_subject_key_is_still_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ListingPreferenceSubjectRef::mls(new SmartTagListingRef(SmartTagListingType::Bridge, 1), MlsProvider::StellarBridge, '');
    }

    /** @dataProvider periods */
    public function test_a_rent_period_is_the_platform_token_or_unknown(?string $feed, ?string $token): void
    {
        $this->assertSame($token, MlsListingAdapter::leasePeriodToken($feed));
    }

    public static function periods(): array
    {
        return [
            'Monthly'          => ['Monthly', 'monthly'],
            'Weekly'           => ['Weekly', 'weekly'],
            'Daily'            => ['Daily', 'daily'],
            'Annually'         => ['Annually', 'annually'],
            'Seasonal'         => ['Seasonal', 'seasonal'],
            'Month to Month'   => ['Month to Month', 'month_to_month'],
            // A lease LENGTH says nothing about what the price is quoted per.
            '12 Months'        => ['12 Months', null],
            '24 Months'        => ['24 Months', null],
            '>6 Months <12'    => ['>6 Months <12', null],
            'Short Term Lease' => ['Short Term Lease', null],
            'unrecognised'     => ['Fortnightly', null],
            'blank'            => ['  ', null],
            'absent'           => [null, null],
        ];
    }

    public function test_every_period_token_is_one_the_normalizer_produces(): void
    {
        $produced = array_map([MlsNormalizer::class, 'normalizeLeaseFrequency'], [
            'Annually', 'Daily', 'Monthly', 'Seasonal', 'Weekly', 'Month to Month',
        ]);

        $this->assertEqualsCanonicalizing($produced, MlsListingAdapter::LEASE_PERIOD_TOKENS);
    }
}
