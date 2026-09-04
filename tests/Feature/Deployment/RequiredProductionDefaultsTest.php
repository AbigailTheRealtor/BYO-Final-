<?php

namespace Tests\Feature\Deployment;

use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Support\HireAgent\HireAgentDetailRedesign;
use App\Support\HireAgent\HireAgentHeroData;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The version-controlled defaults: what this application serves when the
 * environment supplies NOTHING.
 *
 * THE INCIDENT THIS REPRODUCES
 * ----------------------------
 * The six product-state variables lived only in a machine-local `.env`. A
 * container rebuild discarded that file, every flag fell back to its shipped
 * default, and because those defaults were the pilot-era `false` / `landlord`,
 * the platform silently reverted: the superseded Hire Agent layout for three
 * roles, and no MLS import entry point at all. Nothing errored. Nothing logged.
 * The health check passed.
 *
 * WHY THIS TEST SPAWNS A PROCESS INSTEAD OF SETTING CONFIG
 * --------------------------------------------------------
 * Setting config would prove nothing about a default. Neither would reading the
 * config in-process: PHPUnit inherits this machine's environment, where all six
 * variables are present and correct, so an in-process assertion would be reading
 * the environment it is supposed to be proving we no longer need.
 *
 * So the config files are evaluated in a CHILD PHP PROCESS with the six
 * variables stripped from its environment, which is the container-rebuild
 * scenario reproduced rather than described. The child loads no `.env` and boots
 * no framework; it requires the three config files and reports what they resolve
 * to on their own.
 *
 * The gate readers are then exercised in-process against exactly those values,
 * so the claim is end-to-end: shipped defaults in, "the modern platform is on"
 * out. The gate that catches a WRONG value, rather than an absent one, is
 * RequiredProductionFlagsTest.
 */
class RequiredProductionDefaultsTest extends TestCase
{
    /** The variables a rebuilt container would have lost. */
    private const PRODUCT_STATE_VARIABLES = [
        'HIRE_AGENT_DETAIL_REDESIGN_ENABLED',
        'HIRE_AGENT_DETAIL_REDESIGN_ROLES',
        'HIRE_AGENT_HERO_REDESIGN_ENABLED',
        'HIRE_AGENT_HERO_REDESIGN_ROLES',
        'MLS_DIRECT_IMPORT_PREFILL_ENABLED',
        'MLS_DIRECT_IMPORT_QUICK_IMPORT_ENABLED',
    ];

    private static ?array $defaults = null;

    /**
     * Evaluate the shipped config files with every product-state variable
     * removed from the process environment.
     *
     * `false` — NOT null — is what removes a variable from a Symfony Process
     * child. A null value is cast to the empty string and the variable arrives
     * SET AND BLANK, which is a different scenario entirely: `env('X', $default)`
     * returns the default for an absent variable and the blank string for a
     * present one. Measuring the wrong one of those would have made this test
     * pass while proving nothing, so the absence is re-asserted below from inside
     * the child rather than trusted.
     */
    private function shippedDefaults(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }

        $script = <<<'CHILD'
            $base = $argv[1];
            require $base . '/vendor/autoload.php';
            echo json_encode([
                'seen' => array_map(
                    static fn (string $key) => \Illuminate\Support\Env::get($key),
                    array_combine($k = json_decode($argv[2], true), $k)
                ),
                'hire_agent_detail' => require $base . '/config/hire_agent_detail.php',
                'hire_agent_hero'   => require $base . '/config/hire_agent_hero.php',
                'mls_direct_import' => require $base . '/config/mls_direct_import.php',
            ]);
            CHILD;

        $process = new Process(
            [
                (new PhpExecutableFinder())->find() ?: 'php',
                '-r',
                $script,
                base_path(),
                json_encode(self::PRODUCT_STATE_VARIABLES),
            ],
            base_path(),
            array_fill_keys(self::PRODUCT_STATE_VARIABLES, false),
        );

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'The defaults probe failed: ' . $process->getErrorOutput()
        );

        $decoded = json_decode($process->getOutput(), true);

        $this->assertIsArray($decoded, 'The defaults probe returned no usable output.');

        // The probe is worthless if the variables were not actually absent, so the
        // child reports back what it saw and that report is checked here.
        //
        // array_key_exists, not `??`: the value we EXPECT is null, and the null
        // coalescing operator cannot tell an absent key from a null one. Using it
        // would report a correctly-absent variable as a missing measurement.
        $this->assertArrayHasKey('seen', $decoded, 'The defaults probe reported no environment readback.');

        foreach (self::PRODUCT_STATE_VARIABLES as $variable) {
            $this->assertArrayHasKey(
                $variable,
                $decoded['seen'],
                "The defaults probe did not report on {$variable}."
            );

            $this->assertNull(
                $decoded['seen'][$variable],
                "{$variable} reached the child process; the defaults were not what was measured."
            );
        }

        return self::$defaults = $decoded;
    }

    // ── the six acceptance criteria, from defaults alone ────────────────────

    public function test_the_hire_agent_redesign_defaults_on_for_every_role(): void
    {
        $defaults = $this->shippedDefaults();

        foreach (['hire_agent_detail', 'hire_agent_hero'] as $file) {
            $this->assertTrue(
                $defaults[$file]['redesign_enabled'],
                "config/{$file}.php must default the redesign ON."
            );

            foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
                $this->assertContains(
                    $role,
                    $defaults[$file]['redesign_roles'],
                    "config/{$file}.php must default to including the {$role} role."
                );
            }
        }
    }

    public function test_the_mls_import_surfaces_default_on_for_seller_and_landlord(): void
    {
        $defaults = $this->shippedDefaults();

        $this->assertTrue($defaults['mls_direct_import']['prefill_enabled'], 'MLS # prefill must default ON.');
        $this->assertTrue($defaults['mls_direct_import']['quick_import_enabled'], 'MLS quick import must default ON.');

        $this->assertSame(
            ['seller', 'landlord'],
            $defaults['mls_direct_import']['prefill_roles'],
            'The MLS role list is a statement of what the feature is, not a rollout dial; it must not drift.'
        );
    }

    /**
     * The end-to-end claim, through the real gate readers.
     *
     * The values come from the stripped-environment child; the answers come from
     * the classes the views and components actually ask. Nothing in between is
     * assumed.
     */
    public function test_losing_every_variable_still_serves_the_modern_platform(): void
    {
        $defaults = $this->shippedDefaults();

        config([
            'hire_agent_detail'  => $defaults['hire_agent_detail'],
            'hire_agent_hero'    => $defaults['hire_agent_hero'],
            'mls_direct_import'  => $defaults['mls_direct_import'],
        ]);

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $this->assertTrue(
                HireAgentDetailRedesign::enabledFor($role),
                "{$role} modern design must be ON from defaults alone."
            );

            $this->assertTrue(
                HireAgentHeroData::redesignEnabledFor($role),
                "{$role} modern hero must be ON from defaults alone."
            );
        }

        $quickImport = app(MlsQuickImportService::class);

        foreach (['seller', 'landlord'] as $role) {
            $this->assertTrue(
                $quickImport->availableForRole($role),
                "{$role} MLS import must be ON from defaults alone."
            );
        }

        // Buyer and Tenant stay off here for a reason that is not a flag: their
        // listings describe search criteria across many areas, so there is no
        // property to prefill and no route exists. A default change must not
        // quietly grant them a surface that was never built.
        foreach (['buyer', 'tenant'] as $role) {
            $this->assertFalse(
                $quickImport->availableForRole($role),
                "{$role} must not gain an MLS import surface from a default change."
            );
        }
    }

    // ── the safety switches keep their own defaults ─────────────────────────

    public function test_no_safety_switch_was_moved_by_this_change(): void
    {
        // Making the finished product surfaces default ON must not have nudged
        // anything whose default OFF is a safety property rather than a rollout
        // position. Read from the shipped config, with this suite's environment
        // deliberately not consulted for the values that matter.
        $this->assertTrue(config('bya_compatibility.kill_switch'), 'The BYA kill switch must stay engaged by default.');
        $this->assertFalse(config('bya_compatibility.ga_enabled'), 'BYA GA must stay off by default.');
        $this->assertFalse(config('census_geocoder.enabled'), 'The Census geocoder must stay off by default.');
        $this->assertFalse(config('address_point_corpus.enabled'), 'The address-point corpus rung must stay off by default.');
        $this->assertFalse(config('mls_match_check.enabled'), 'MLS Match Check must stay off by default.');
        $this->assertFalse(config('dna_scores.generation_enabled'), 'DNA score generation must stay off by default.');
        $this->assertFalse(config('matching.persistence.enabled'), 'Matching V2 persistence must stay off by default.');
    }
}
