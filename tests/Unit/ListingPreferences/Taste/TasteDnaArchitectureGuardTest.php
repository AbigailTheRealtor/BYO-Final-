<?php

namespace Tests\Unit\ListingPreferences\Taste;

use PHPUnit\Framework\TestCase;

/**
 * Taste DNA reaches exactly TWO surfaces — structurally.
 *
 * Phase 4 learns and explains on the customer's own page. Phase 5 adds ONE
 * consumer (governance §14): the bounded Best Match reorder on the Stellar
 * results page, reached only through the results controller and the two
 * worded partials. It must still never reach Match DNA, the 100-point score,
 * the matcher, BYO search, filtering, recommendations or Ask AI. A feature flag
 * cannot prove that — a flag gates a caller, and the promise is about which
 * callers exist — so these guards read the source.
 *
 * Code is compared with comments stripped, so prose DESCRIBING a prohibition
 * ("no address, no ZIP") is never read as a violation of it.
 */
class TasteDnaArchitectureGuardTest extends TestCase
{
    private string $root;

    /**
     * The ONLY files that may reference Taste DNA. Adding one is a deliberate
     * edit here, in the same change as the new reference — which is the point.
     */
    private const ALLOWED = [
        'app/Support/ListingPreferences/Taste/',
        'app/Services/ListingPreferences/Taste/',
        'app/Http/Controllers/MyListingPreferencesController.php',
        'app/Http/Middleware/EnsureTasteDnaEnabled.php',
        'app/Http/Kernel.php',
        'routes/web.php',
        'config/listing_preferences.php',
        'resources/views/listing-preferences/mine/taste.blade.php',
        // Phase 5 (governance §14): the one ranking consumer, and its two
        // worded partials. The card component includes the explanation partial
        // by name and reads no Taste class — asserted below.
        'app/Http/Controllers/Stellar/StellarBuyerResultsController.php',
        'resources/views/listing-preferences/taste/',
    ];

    /** Where a consumer would have to live to change what a customer is shown. */
    private const NEVER_CONSUMERS = [
        'app/Services/Stellar',
        'app/Services/Matching',
        'app/Services/AskAi',
        'app/Services/Dna',
        'app/Services/Explore',
        'app/Services/Bridge',
        'app/Services/LocationDna',
        'app/Services/SmartTags',
        'app/Helpers',
        'app/Http/Livewire',
        'app/Jobs',
        'app/Console',
        'app/Observers',
        'app/Support/VirtualDrive',
        'config/match_scoring.php',
        'resources/views/components',
        'resources/js',
        'public/js',
    ];

    private const REFERENCE = 'ListingPreferences\\\\Taste|TasteDna|Taste(Profile|Signal|Observation|Evidence|ListingFacts|Choice)|taste_dna_enabled|listing-preference-taste|LISTING_PREFERENCE_TASTE_DNA';

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 4);
    }

    /** @test */
    public function only_the_named_files_reference_taste_dna(): void
    {
        $offenders = [];

        foreach ($this->grep(self::REFERENCE, ['app', 'config', 'routes', 'resources', 'public/js', 'database']) as $path) {
            if (! $this->allowed($path)) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'Taste DNA gained a reference outside its allowlist — every consumer is a governance decision');
    }

    /** @test */
    public function ranking_scoring_stellar_ai_and_recommendation_code_never_reads_taste_dna(): void
    {
        foreach (self::NEVER_CONSUMERS as $dir) {
            $this->assertSame([], $this->grep(self::REFERENCE, [$dir]), "{$dir} must not consume Taste DNA");
        }

        $scoring = (string) file_get_contents($this->root . '/config/match_scoring.php');
        $this->assertDoesNotMatchRegularExpression('/taste|listing_preference/i', $scoring, 'no scoring weight may exist for preference');
    }

    /** @test */
    public function the_learning_flag_that_would_let_it_act_is_still_read_by_nothing(): void
    {
        // A READ is the config key in quotes; prose naming it in backticks is not.
        $this->assertSame(
            [],
            $this->grep("listing_preferences\\.learning_enabled['\"]", ['app', 'routes', 'resources']),
            'listing_preferences.learning_enabled governs learning beyond the Phase 5 rerank and has no reader'
        );
    }

    /** @test */
    public function taste_code_never_touches_location_people_ai_network_or_writes(): void
    {
        $forbidden = [
            // Where a home is, or who lives near it.
            'latitude', 'longitude', 'coordinate', 'postal_code', 'zip', 'city', 'county', 'subdivision',
            'school', 'census', 'address', 'neighbo', 'demographic', 'crime', 'important_place',
            // Other customers.
            'User::', "whereIn('user_id'", 'groupBy(', 'similar',
            // Models, networks and caches.
            'OpenAI', 'openai', 'Http::', 'Guzzle', 'curl_', 'Cache::', 'file_get_contents',
            // Writes of any kind.
            '->save(', '::create(', '->update(', '->delete(', 'insert(', 'upsert(', 'updateOrCreate', 'DB::',
            // Free text.
            'other_property_items', 'additional_details', 'remarks',
        ];

        foreach ($this->tasteFiles() as $path => $code) {
            foreach ($forbidden as $token) {
                $this->assertStringNotContainsStringIgnoringCase($token, $code, "{$path} must not contain {$token}");
            }
        }
    }

    /** @test */
    public function the_evidence_query_is_scoped_to_one_customer_and_one_role(): void
    {
        $code = $this->tasteFiles()['app/Services/ListingPreferences/Taste/TasteEvidenceReader.php'];

        $this->assertStringContainsString("->where('user_id', \$userId)", $code);
        $this->assertStringContainsString("->where('seeker_role', \$role->value)", $code);
    }

    /** @test */
    public function the_domain_classes_are_container_free(): void
    {
        foreach ($this->tasteFiles() as $path => $code) {
            if (! str_starts_with($path, 'app/Support/ListingPreferences/Taste/') || str_ends_with($path, 'TasteDnaAvailability.php')) {
                continue;
            }

            foreach (['config(', 'app(', 'now(', 'time(', 'date(', 'rand(', 'Carbon'] as $token) {
                $this->assertStringNotContainsString($token, $code, "{$path} must be pure and clock-free ({$token})");
            }
        }
    }

    /** @test */
    public function taste_dna_is_derived_and_adds_no_table(): void
    {
        $this->assertSame([], $this->grep('taste', ['database/migrations']), 'Taste DNA is recomputed, never stored');
    }

    // ---------------------------------------------------------------- helpers

    private function allowed(string $path): bool
    {
        foreach (self::ALLOWED as $prefix) {
            if ($path === $prefix || (str_ends_with($prefix, '/') && str_starts_with($path, $prefix))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comment-stripped code of every Taste DNA PHP file.
     *
     * @return array<string, string>
     */
    private function tasteFiles(): array
    {
        $out = [];

        foreach (['app/Support/ListingPreferences/Taste', 'app/Services/ListingPreferences/Taste'] as $dir) {
            foreach (glob($this->root . '/' . $dir . '/*.php') as $file) {
                $code = '';
                foreach (token_get_all((string) file_get_contents($file)) as $token) {
                    if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $code .= is_array($token) ? $token[1] : $token;
                }
                $out[substr($file, strlen($this->root) + 1)] = $code;
            }
        }

        $this->assertNotEmpty($out);

        return $out;
    }

    /**
     * @param  list<string> $paths
     * @return list<string>
     */
    private function grep(string $pattern, array $paths): array
    {
        $existing = array_values(array_filter($paths, fn ($p) => file_exists($this->root . '/' . $p)));

        if ($existing === []) {
            return [];
        }

        $cmd = sprintf(
            'cd %s && grep -rliE %s %s 2>/dev/null',
            escapeshellarg($this->root),
            escapeshellarg($pattern),
            implode(' ', array_map('escapeshellarg', $existing)),
        );

        $out = array_values(array_filter(array_map('trim', explode("\n", (string) shell_exec($cmd)))));
        sort($out);

        return $out;
    }
}
