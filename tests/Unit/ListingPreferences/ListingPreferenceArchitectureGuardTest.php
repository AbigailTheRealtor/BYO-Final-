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
     * Phase 3B wired the Virtual Drive through ONE seam.
     *
     * Phase 2 asserted the Virtual Drive was untouched and said a later phase
     * would replace the Save entry with a delegation — and this assertion with
     * it. The delegation is `VirtualDrivePreferenceControl`; it is the only
     * Virtual Drive file that may know listing preferences exist, and the
     * proof's JavaScript knows nothing at all.
     *
     * @test
     */
    public function the_virtual_drive_reaches_preferences_through_one_seam(): void
    {
        $support = (string) file_get_contents(
            $this->root . '/app/Support/VirtualDrive/VirtualDriveListingActions.php'
        );

        $this->assertStringNotContainsString(
            'No Save / Favorite feature exists anywhere in this application.',
            $support,
            'the obsolete finding must not survive the delegation'
        );

        $this->assertSame(
            ['app/Support/VirtualDrive/VirtualDrivePreferenceControl.php'],
            $this->filesMatching('ListingPreference', ['app/Support/VirtualDrive', 'app/Http/Controllers/Dev', 'public/js/virtual-drive']),
            'VirtualDrivePreferenceControl is the only Virtual Drive file that may reference listing preferences'
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
     * THE BROWSER HARNESS SPLIT, PINNED.
     *
     * There are two Playwright configurations because the two browser suites have
     * different dependencies. The DEFAULT one drives static fixtures with a
     * pure-Node server, and the CI job that runs it installs Node and nothing else
     * — no PHP, no Composer, no vendor/. The Listing Preferences specs boot the
     * real application and need all three.
     *
     * They were briefly one configuration, and the result was not subtle: the
     * Location DNA job inherited the Laravel `webServer` entries and died on
     * `Failed opening required '.../vendor/autoload.php'` before one browser spec
     * executed. Nothing in either file announces that coupling, and the failure
     * appears in a suite whose own code is untouched — so it is asserted here
     * rather than left to a reviewer noticing a webServer entry.
     *
     * @test
     */
    public function the_default_playwright_config_never_boots_the_laravel_application(): void
    {
        $config = (string) file_get_contents($this->root . '/playwright.config.js');

        // Everything after the header comment: the comments deliberately DESCRIBE
        // the app server, and describing it must not read as configuring it.
        $code = $this->withoutComments($config);

        foreach (['app-server.js', 'artisan', 'php ', '8932', '8933'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $code,
                "playwright.config.js must not reference {$forbidden} — the Laravel-backed "
                . 'suite belongs in playwright.app.config.js, whose CI job installs PHP'
            );
        }

        $this->assertStringContainsString(
            'static-server.js',
            $code,
            'playwright.config.js must keep the pure-Node static fixture server'
        );

        // The app spec is excluded here and matched there, from one constant.
        $this->assertStringContainsString('testIgnore: APP_SPEC', $code);

        $appConfig = $this->withoutComments(
            (string) file_get_contents($this->root . '/playwright.app.config.js')
        );

        $this->assertStringContainsString('testMatch: APP_SPEC', $appConfig);
        $this->assertStringContainsString('app-server.js', $appConfig);
    }

    /**
     * The Location DNA browser JOB must stay free of a PHP toolchain, and the
     * Listing Preferences one must have it. Installing PHP into the first would
     * "fix" a recurrence of the coupling above by hiding it.
     *
     * @test
     */
    public function the_location_dna_browser_job_provisions_no_php_toolchain(): void
    {
        // Comment lines are stripped from both files: these workflows DESCRIBE the
        // toolchain split in prose, and a sentence saying "no setup-php here" must
        // not itself trip the assertion that setup-php is absent.
        $locationDna = $this->withoutYamlComments(
            (string) file_get_contents($this->root . '/.github/workflows/browser-tests.yml')
        );

        foreach (['setup-php', 'composer install', 'vendor/autoload.php'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $locationDna,
                "The Location DNA browser job must not provision {$forbidden} — it runs "
                . 'a pure-Node static server, and adding PHP would mask the next coupling'
            );
        }

        $this->assertStringNotContainsString(
            'test:browser:app',
            $locationDna,
            'The Location DNA browser job must not run the Laravel-backed suite'
        );

        $app = $this->withoutYamlComments(
            (string) file_get_contents($this->root . '/.github/workflows/browser-tests-app.yml')
        );

        $this->assertStringContainsString('setup-php', $app);
        $this->assertStringContainsString('composer install', $app);
        $this->assertStringContainsString('npm run test:browser:app', $app);

        // `composer update` would resolve a tree the application does not ship.
        $this->assertStringNotContainsString('composer update', $app);
    }

    /**
     * Both npm entry points exist and point at their own configuration, so
     * `npm run test:browser` can never become the Laravel-backed suite by accident.
     *
     * @test
     */
    public function the_two_browser_suites_have_separate_npm_commands(): void
    {
        $package = json_decode(
            (string) file_get_contents($this->root . '/package.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('playwright test', $package['scripts']['test:browser'] ?? null);
        $this->assertSame(
            'playwright test --config=playwright.app.config.js',
            $package['scripts']['test:browser:app'] ?? null
        );
    }

    /** Strips whole-line YAML comments, so prose about a step is not read as the step. */
    private function withoutYamlComments(string $source): string
    {
        return (string) preg_replace('/^\s*#.*$/m', '', $source);
    }

    /** Strips /* *\/ and // comments, so prose describing a thing is not read as the thing. */
    private function withoutComments(string $source): string
    {
        $stripped = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;

        return (string) preg_replace('#^\s*//.*$#m', '', $stripped);
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
