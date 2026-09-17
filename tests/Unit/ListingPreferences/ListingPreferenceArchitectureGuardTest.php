<?php

namespace Tests\Unit\ListingPreferences;

use PHPUnit\Framework\TestCase;

/**
 * Structural guarantees a code review would otherwise have to remember.
 *
 * PHASE 2 REWROTE THIS FILE, and that is the intended lifecycle. Phase 1's
 * guards asserted total inertness — no route, no view, no write — which was
 * exactly right while nothing was wired and is exactly wrong now. What survives
 * is every boundary that did NOT change when the feature became real:
 *
 *   • the reason vocabulary still has one reader,
 *   • only the write service touches the models,
 *   • the Virtual Drive is still untouched,
 *   • nothing feeds ranking, Ask AI or a learner.
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
     * One reader of the reason config, so a Blade view, a controller or a
     * JavaScript file cannot grow its own idea of what a reason means.
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
            'Only ListingPreferenceConfig may read the reason catalog config'
        );
    }

    /**
     * THE write boundary. Controllers, views and JavaScript describe intent;
     * only ListingPreferenceWriter persists it, so "what happens when a customer
     * presses Pass" has one answer rather than one per surface.
     *
     * @test
     */
    public function only_the_write_service_persists_a_preference(): void
    {
        $writers = $this->filesMatching(
            'ListingPreference(Event|Reason)?::(create|insert|updateOrCreate|firstOrCreate|upsert)'
            . '|ListingPreference(Event|Reason)?::where\\([^)]*\\)->(delete|update)'
        );

        $allowed = ['app/Services/ListingPreferences/ListingPreferenceWriter.php'];

        $this->assertSame(
            [],
            array_values(array_diff($writers, $allowed)),
            'Only ListingPreferenceWriter may persist a listing preference'
        );
    }

    /**
     * No surface reaches the models directly — they go through the writer or
     * the reader.
     *
     * @test
     */
    public function no_view_or_javascript_touches_a_preference_model(): void
    {
        $touching = $this->filesMatching(
            'App\\\\Models\\\\ListingPreference',
            ['resources/views', 'resources/js', 'public/js']
        );

        $this->assertSame([], $touching, 'A template must not reach a preference model');
    }

    /**
     * Phase 2 does not touch the Virtual Drive.
     *
     * @test
     */
    public function the_virtual_drive_is_untouched(): void
    {
        $support = (string) file_get_contents(
            $this->root . '/app/Support/VirtualDrive/VirtualDriveListingActions.php'
        );

        // Still reported as unavailable. A later phase replaces this one array
        // entry with a delegation to the shared service — and this assertion
        // with it.
        $this->assertStringContainsString(
            'No Save / Favorite feature exists anywhere in this application.',
            $support
        );

        $this->assertSame(
            [],
            $this->filesMatching('ListingPreference', ['app/Support/VirtualDrive', 'app/Http/Controllers/Dev', 'public/js/virtual-drive']),
            'No Virtual Drive file may reference listing preferences in Phase 2'
        );
    }

    /**
     * Phase 2 is CAPTURE ONLY. Nothing may read a preference into ranking, Ask
     * AI, Match DNA or a learner — those are governed later phases, and the
     * governance revision for behavioural learning has not been made.
     *
     * @test
     */
    public function nothing_consumes_preferences_for_ranking_ai_or_learning(): void
    {
        $consumers = $this->filesMatching(
            'ListingPreference',
            [
                'app/Services/Stellar',
                'app/Services/AskAi',
                'app/Services/Dna',
                'app/Services/Matching',
                'app/Helpers',
            ]
        );

        $this->assertSame(
            [],
            $consumers,
            'Phase 2 captures preferences; it must not feed ranking, Ask AI, DNA or a learner'
        );
    }

    /**
     * The reason vocabulary never appears as a hard-coded list in a template or
     * a script — the chips are always the catalog, serialised.
     *
     * @test
     */
    public function no_template_or_script_hard_codes_reason_keys(): void
    {
        $sample = ['too_expensive', 'updated_kitchen', 'needs_complete_update', 'location_proximity'];

        foreach (['resources/views', 'resources/js', 'public/js'] as $dir) {
            foreach ($sample as $key) {
                $this->assertSame(
                    [],
                    $this->filesMatching(preg_quote($key, '/'), [$dir]),
                    "{$key} must not be hard-coded in {$dir} — chips come from the catalog"
                );
            }
        }
    }

    /**
     * @param  list<string>|null $directories
     * @return list<string>      repo-relative paths
     */
    private function filesMatching(string $pattern, ?array $directories = null): array
    {
        $directories ??= ['app', 'config', 'routes', 'resources/views', 'resources/js', 'public/js'];

        $out = [];
        $cmd = sprintf(
            'cd %s && grep -rlE %s %s 2>/dev/null',
            escapeshellarg($this->root),
            escapeshellarg($pattern),
            implode(' ', array_map('escapeshellarg', $directories)),
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
