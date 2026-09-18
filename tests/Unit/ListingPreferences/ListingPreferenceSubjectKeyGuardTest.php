<?php

namespace Tests\Unit\ListingPreferences;

use App\Support\Listing\MlsProvider;
use App\Support\ListingPreferences\ListingPreferenceSubjectRef;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * One place builds an MLS subject key, and the legacy form cannot be built at all.
 *
 * WHY A GUARD AND NOT A CONVENTION
 * --------------------------------
 * `'mls:' . $listingKey` is a plausible-looking expression that produces a
 * perfectly valid-looking string. Nothing about it fails until a second MLS
 * exists, and then it fails by attaching one customer's Save to a different
 * provider's house. So the concatenation is forbidden structurally: the key is
 * assembled inside {@see ListingPreferenceSubjectRef} from a typed
 * {@see MlsProvider}, and the constructor refuses anything that is not
 * `mls:<recognised provider>:<key>`.
 */
class ListingPreferenceSubjectKeyGuardTest extends TestCase
{
    private function appRoot(): string
    {
        return dirname(__DIR__, 3) . '/app';
    }

    /** @return list<string> repo-relative PHP files under app/ */
    private function appFiles(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->appRoot()));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = 'app' . explode('/app', $file->getPathname(), 2)[1];
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Only the ref class may assemble a preference subject key.
     *
     * SCOPED TO THE SUBSYSTEM, DELIBERATELY. The literal `'mls:'` appears
     * elsewhere for two unrelated reasons and neither is a defect:
     *
     *   - artisan command signatures (`mls:sync-listings`, `mls:probe-lifecycle`)
     *   - a MEDIA identity prefix in `MlsMediaItem` and `ListingPhotoEntry`,
     *     which names a photograph, not a preference subject
     *
     * A scan that flagged those would be one somebody edits until it passes.
     * What must be true is narrower and exact: nothing in the Listing Preference
     * subsystem — the only code that writes `subject_key` — builds that key by
     * hand.
     *
     * @test
     */
    public function nothing_in_the_preference_subsystem_hand_builds_a_subject_key(): void
    {
        $owner     = 'app/Support/ListingPreferences/ListingPreferenceSubjectRef.php';
        $offenders = [];

        foreach ($this->appFiles() as $relative) {
            if ($relative === $owner) {
                continue;
            }

            if (! str_contains($relative, 'ListingPreference')) {
                continue;
            }

            $source = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $relative);

            if (preg_match("/'(mls|byo):/", $source)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Build subject keys through ListingPreferenceSubjectRef::mls(MlsProvider, $key) '
            . 'or ::native($ref): ' . implode(', ', $offenders)
        );
    }

    /**
     * And the media prefix stays a separate namespace — asserted so that a later
     * reader does not "unify" two identities that mean different things.
     *
     * @test
     */
    public function the_media_identity_prefix_is_a_different_namespace(): void
    {
        $media = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Support/Listing/ListingPhotoEntry.php');

        $this->assertStringContainsString('\'mls:\' . $this->mediaKey', $media);
        $this->assertStringNotContainsString('ListingPreferenceSubjectRef', $media);
    }

    /**
     * The legacy un-namespaced form is not merely unused — it is unconstructable.
     *
     * @test
     */
    public function the_legacy_un_namespaced_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ListingPreferenceSubjectRef(
            new SmartTagListingRef(SmartTagListingType::Bridge, 1),
            'mls:MFR123456789',
        );
    }

    /** @test */
    public function an_unrecognised_provider_segment_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ListingPreferenceSubjectRef(
            new SmartTagListingRef(SmartTagListingType::Bridge, 1),
            'mls:some_other_mls:MFR123456789',
        );
    }

    /**
     * The encoding must fit `string(191)` on both tables. A key that would be
     * truncated is refused rather than stored as a different subject than the
     * one asked for.
     *
     * @test
     */
    public function an_over_long_key_is_refused_rather_than_truncated(): void
    {
        $prefix = 'mls:' . MlsProvider::StellarBridge->value . ':';
        $room   = ListingPreferenceSubjectRef::MAX_KEY_LENGTH - strlen($prefix);

        // The longest key that fits is accepted…
        $fits = ListingPreferenceSubjectRef::mls(
            new SmartTagListingRef(SmartTagListingType::Bridge, 1),
            MlsProvider::StellarBridge,
            str_repeat('K', $room),
        );
        $this->assertSame(ListingPreferenceSubjectRef::MAX_KEY_LENGTH, strlen($fits->subjectKey));

        // …and one character more is not.
        $this->expectException(InvalidArgumentException::class);

        ListingPreferenceSubjectRef::mls(
            new SmartTagListingRef(SmartTagListingType::Bridge, 1),
            MlsProvider::StellarBridge,
            str_repeat('K', $room + 1),
        );
    }

    /**
     * Real RESO listing keys are nowhere near the ceiling — the guard exists for
     * the column, not because any feed approaches it.
     *
     * @test
     */
    public function a_realistic_listing_key_leaves_ample_room(): void
    {
        $subject = ListingPreferenceSubjectRef::mls(
            new SmartTagListingRef(SmartTagListingType::Bridge, 1),
            MlsProvider::StellarBridge,
            'MFR123456789',
        );

        $this->assertLessThan(64, strlen($subject->subjectKey));
    }

    /** A listing key containing a colon survives the round trip intact. */
    /** @test */
    public function a_listing_key_containing_a_colon_is_preserved(): void
    {
        $subject = ListingPreferenceSubjectRef::mls(
            new SmartTagListingRef(SmartTagListingType::Bridge, 1),
            MlsProvider::StellarBridge,
            'ODD:KEY:1',
        );

        $this->assertSame('mls:stellar_bridge:ODD:KEY:1', $subject->subjectKey);
        $this->assertSame('ODD:KEY:1', $subject->mlsListingKey());
        $this->assertSame(MlsProvider::StellarBridge, $subject->mlsProvider());
    }
}
