<?php

namespace Tests\Unit\ListingPreferences;

use PHPUnit\Framework\TestCase;

/**
 * Structural guarantees that a code review would otherwise have to remember.
 */
class ListingPreferenceArchitectureGuardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 3);
    }

    /**
     * One reader of the reason config, so a Blade view or a controller cannot
     * grow its own idea of what a reason means — the same guarantee
     * SmartTagConfig has.
     *
     * @test
     */
    public function listing_preference_config_is_the_only_reader_of_the_reason_config(): void
    {
        $readers = $this->filesMatching("config\\(['\"]listing_preference_reasons|listing_preference_reasons\\.php");

        $allowed = [
            'app/Support/ListingPreferences/ListingPreferenceConfig.php',
            'config/listing_preference_reasons.php',
        ];

        $this->assertSame(
            [],
            array_values(array_diff($readers, $allowed)),
            'Only ListingPreferenceConfig may read config/listing_preference_reasons.php'
        );
    }

    /**
     * Phase 1 is inert. No route, controller, Blade view, JavaScript file or
     * Livewire component may reference the subsystem — the whole point of an
     * inert foundation is that merging it changes no customer-visible behaviour.
     *
     * @test
     */
    public function phase_one_is_inert_and_reaches_no_customer_facing_surface(): void
    {
        $referencing = $this->filesMatching('ListingPreference|listing_preference');

        foreach ($referencing as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '~^(routes/|resources/views/|resources/js/|public/js/|app/Http/)~',
                $file,
                "Phase 1 is inert: {$file} must not reference the listing preference subsystem yet"
            );
        }
    }

    /**
     * Phase 1 writes nothing. The models exist; nothing may persist through
     * them, exactly as Smart Tags Phase 1 shipped.
     *
     * @test
     */
    public function no_application_code_writes_a_listing_preference(): void
    {
        $writers = $this->filesMatching(
            'ListingPreference(Event|Reason)?::(create|insert|updateOrCreate|firstOrCreate|upsert)'
        );

        $this->assertSame([], $writers, 'Phase 1 must not write preferences anywhere in app/.');
    }

    /**
     * The Virtual Drive is untouched by this phase.
     *
     * @test
     */
    public function the_virtual_drive_is_not_referenced(): void
    {
        $support = (string) file_get_contents(
            $this->root . '/app/Support/VirtualDrive/VirtualDriveListingActions.php'
        );

        // The Save action is still reported as unavailable — Phase 2 changes
        // this line, and this assertion with it.
        $this->assertStringContainsString(
            'No Save / Favorite feature exists anywhere in this application.',
            $support
        );
    }

    /**
     * @return list<string> repo-relative paths
     */
    private function filesMatching(string $pattern): array
    {
        $out = [];
        $cmd = sprintf(
            'cd %s && grep -rlE %s app config routes resources/views resources/js public/js 2>/dev/null',
            escapeshellarg($this->root),
            escapeshellarg($pattern),
        );

        foreach (explode("\n", trim((string) shell_exec($cmd))) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        sort($out);

        return $out;
    }
}
