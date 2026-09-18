<?php

namespace Tests\Unit\Bridge;

use PHPUnit\Framework\TestCase;

/**
 * Nothing outside the model may look a Bridge row up by its native key alone.
 *
 * WHY A GUARD RATHER THAN A CODE REVIEW HABIT
 * -------------------------------------------
 * `where('listing_key', $key)` is not a syntax error, does not fail a test, and
 * returns a row. It is only wrong when a second provider exists — and then it
 * returns the WRONG property, silently. That is the shape of defect a reviewer
 * reliably misses and a scan reliably catches, so the pairing is enforced here
 * rather than remembered.
 *
 * There were eight such call sites before P0-2. Each is now
 * {@see \App\Models\BridgeProperty::forNativeKey()} or
 * {@see \App\Models\BridgeProperty::forNativeMlsNumber()}.
 */
class BridgeNativeIdentityGuardTest extends TestCase
{
    /** The one file allowed to pair the columns itself. */
    private const OWNER = 'app/Models/BridgeProperty.php';

    private function appRoot(): string
    {
        return dirname(__DIR__, 3) . '/app';
    }

    /** @return list<string> every PHP file under app/, repo-relative */
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

    private function contents(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3) . '/' . $relative);
    }

    /** @test */
    public function no_application_file_looks_up_a_bridge_row_by_listing_key_alone(): void
    {
        $offenders = [];

        foreach ($this->appFiles() as $relative) {
            if ($relative === self::OWNER) {
                continue;
            }

            if (preg_match("/where\(\s*'listing_key'/", $this->contents($relative))) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A Bridge listing key is unique only within the MLS that minted it. These files pair it with "
            . "no provider, so they would return another provider's property once a second MLS exists. "
            . 'Use BridgeProperty::forNativeKey(MlsProvider, $key) instead: ' . implode(', ', $offenders)
        );
    }

    /**
     * The same rule for the MLS number, which is weaker still — `ListingId` is
     * documented as unique only within its originating system.
     *
     * Scoped to files that actually query BridgeProperty, because
     * `where('listing_id', …)` is ALSO the polymorphic enrichment column on
     * `property_location_dna`, `smart_tag_*`, `dna_scores` and others, where it
     * means a listing's primary key and is entirely correct.
     *
     * @test
     */
    public function no_bridge_query_looks_up_a_row_by_mls_number_alone(): void
    {
        $offenders = [];

        foreach ($this->appFiles() as $relative) {
            if ($relative === self::OWNER) {
                continue;
            }

            $source = $this->contents($relative);

            if (! str_contains($source, 'BridgeProperty')) {
                continue;
            }

            if (preg_match("/BridgeProperty::(query\(\)->)?where\(\s*'listing_id'/", $source)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Use BridgeProperty::forNativeMlsNumber(MlsProvider, $number): ' . implode(', ', $offenders)
        );
    }

    /**
     * The upsert identity is the pair. This is the single most important line in
     * the change — matching on the key alone is how one provider overwrites
     * another's row — so it is asserted at the source rather than only through
     * behaviour.
     *
     * @test
     */
    public function the_normalizer_upserts_on_the_provider_and_key_pair(): void
    {
        $source = $this->contents('app/Services/Bridge/BridgePropertyNormalizer.php');

        $this->assertMatchesRegularExpression(
            "/'provider'\s*=>\s*\\\$provider->value,\s*'listing_key'\s*=>\s*\\\$listingKey/",
            $source,
            'BridgePropertyNormalizer must build its upsert identity from BOTH columns.'
        );

        $this->assertStringNotContainsString(
            "updateOrCreate(\n            ['listing_key' => \$listingKey],",
            $source,
            'The upsert must not match on listing_key alone.'
        );
    }
}
