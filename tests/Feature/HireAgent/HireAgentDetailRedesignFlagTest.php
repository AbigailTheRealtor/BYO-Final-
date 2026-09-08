<?php

namespace Tests\Feature\HireAgent;

use App\Support\HireAgent\HireAgentDetailRedesign;
use Tests\TestCase;

/**
 * The detail-page redesign flag.
 *
 * M5.0's claim was that the flag was INERT — off by default — because it shipped before anything
 * read it. THAT CLAIM EXPIRED WHEN THE ROLLOUT COMPLETED. The redesign is now the platform design
 * for all four roles, the shipped default is ON, and an off default would describe a regression
 * rather than an inert merge: an environment that lost its variables would silently serve the
 * superseded layout.
 *
 * WHAT DID NOT CHANGE, and is still asserted below: the flag is independent of the hero flag, it
 * is read in exactly one place, the reader follows the config in both directions, and a MISSING
 * key still reads as OFF. That last one is not the same question as the default — a config file
 * that failed to load must not enable a page layout — and it is why
 * HireAgentDetailRedesign::enabled() keeps its own `false` fallback.
 *
 * The default is asserted here against the shipped configuration. The stronger claim — that the
 * default holds with the environment supplying NOTHING AT ALL, which is the container-rebuild
 * case — is proven in Tests\Feature\Deployment\RequiredProductionDefaultsTest, which strips the
 * variables from a child process rather than trusting this suite's inherited environment.
 */
class HireAgentDetailRedesignFlagTest extends TestCase
{
    /**
     * The shipped default is ON, for every role.
     *
     * This is the version-controlled floor: an environment that supplies nothing still serves the
     * current design. Losing a variable must not bring the old platform back.
     */
    public function test_the_flag_is_on_by_default_for_every_role(): void
    {
        $this->assertTrue(
            config('hire_agent_detail.redesign_enabled'),
            'The detail redesign is the platform default; an absent variable must not disable it.'
        );

        $this->assertTrue(HireAgentDetailRedesign::enabled());

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $this->assertTrue(
                HireAgentDetailRedesign::enabledFor($role),
                "The detail redesign must default on for the {$role} role."
            );
        }
    }

    /** A missing key reads as off, not as on — a config that failed to load must not enable it. */
    public function test_a_missing_key_reads_as_off(): void
    {
        config()->offsetUnset('hire_agent_detail');

        $this->assertFalse(HireAgentDetailRedesign::enabled());
    }

    /** The reader reflects the config, both ways — it is a reader, not a hard-coded false. */
    public function test_the_reader_follows_the_config(): void
    {
        config(['hire_agent_detail.redesign_enabled' => true]);
        $this->assertTrue(HireAgentDetailRedesign::enabled());

        config(['hire_agent_detail.redesign_enabled' => false]);
        $this->assertFalse(HireAgentDetailRedesign::enabled());
    }

    /** Truthy env strings survive the cast; "false" from an env file must not read as true. */
    public function test_the_value_is_cast_to_a_boolean(): void
    {
        config(['hire_agent_detail.redesign_enabled' => 1]);
        $this->assertTrue(HireAgentDetailRedesign::enabled());

        config(['hire_agent_detail.redesign_enabled' => 0]);
        $this->assertFalse(HireAgentDetailRedesign::enabled());

        config(['hire_agent_detail.redesign_enabled' => null]);
        $this->assertFalse(HireAgentDetailRedesign::enabled());
    }

    /**
     * The two flags are independent.
     *
     * This is the reason a second flag exists at all. The hero flag is enabled for landlord in a
     * live environment while the detail rebuild is still being written, so one must never move the
     * other. Asserted in both directions.
     */
    public function test_the_detail_flag_is_independent_of_the_hero_flag(): void
    {
        config([
            'hire_agent_hero.redesign_enabled' => true,
            'hire_agent_hero.redesign_roles'   => ['landlord'],
            'hire_agent_detail.redesign_enabled' => false,
        ]);

        $this->assertTrue(\App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('landlord'));
        $this->assertFalse(HireAgentDetailRedesign::enabled(), 'The hero flag must not enable the detail redesign.');

        config([
            'hire_agent_hero.redesign_enabled'   => false,
            'hire_agent_detail.redesign_enabled' => true,
        ]);

        $this->assertTrue(HireAgentDetailRedesign::enabled());
        $this->assertFalse(\App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('landlord'), 'The detail flag must not enable the hero.');
    }

    /**
     * Exactly one place in the application may read the config key.
     *
     * The rule this enforces is the one the M4 hero broke: a presenter held a second opinion about
     * a rule a view was enforcing, and the two diverged in production. A view that reads the key
     * itself is that same shape of bug waiting to happen.
     */
    public function test_only_the_single_reader_touches_the_config_key(): void
    {
        $readers = [];

        foreach ([base_path('app'), base_path('resources/views'), base_path('routes')] as $root) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($items as $item) {
                if (! $item->isFile()) {
                    continue;
                }

                $path = str_replace(base_path() . '/', '', $item->getPathname());

                if ($path === 'app/Support/HireAgent/HireAgentDetailRedesign.php') {
                    continue;
                }

                if (str_contains((string) file_get_contents($item->getPathname()), 'hire_agent_detail')) {
                    $readers[] = $path;
                }
            }
        }

        $this->assertSame(
            [],
            $readers,
            "Only HireAgentDetailRedesign::enabled() may read config('hire_agent_detail.*'). "
            . 'Found other readers: ' . implode(', ', $readers)
        );
    }

    /**
     * Exactly one view consults the flag, and it is the landlord detail view.
     *
     * M5.0 shipped the switch with no consumer and this test asserted the list was empty, with a
     * note that the list changing is a signal to update the test deliberately. M5.2b is that
     * change: the landlord view gained the section navigation the flag gates. The test stays,
     * inverted — an EXACT list rather than an empty one — because the property worth protecting
     * was never "nobody reads it", it was "the set of readers is known".
     *
     * M7.1 CHANGED THE MECHANISM, AND THIS TEST CHANGES WITH IT — DELIBERATELY, NOT TO GO GREEN.
     *
     * The paragraph that stood here read: "The flag has no role allowlist, so role scope is
     * enforced entirely by which files consult it. That makes this assertion the actual scoping
     * mechanism for the pilot." That was true while the redesigned markup lived in one role view.
     *
     * M7.1 moved page layout into components/hire-agent/detail-shell.blade.php, which all four
     * role views render. The consumer set can no longer BE the role scope, because the shell is
     * one file serving four roles — which is precisely why M7.1 added `redesign_roles` and
     * HireAgentDetailRedesign::enabledFor(). Role scope moved from "which files read the flag" to
     * "which roles the config allows", and that is now asserted by
     * HireAgentDetailShellLayoutTest, including that the shipped default is landlord alone and
     * that non-pilot roles keep the legacy grid with the master switch ON.
     *
     * SO WHAT IS LEFT WORTH ASSERTING HERE, and it is not nothing: the set of readers is still
     * KNOWN. A consumer appearing means a file started gating on the redesign without that
     * being decided, and the entries below are exactly the ones that should. The shell must
     * read it through enabledFor() — a shell reading the master enabled() would flip all four
     * roles at once, and HireAgentDetailShellLayoutTest pins that too.
     *
     * M7 PHASE 3 ADDS THE BUYER VIEW, AND THIS IS THE DELIBERATE DECISION THE MESSAGE BELOW ASKS
     * FOR — not a widening to go green.
     *
     * The buyer view had carried the redesign branch since Phase 2 (the Financing Details and
     * Representation Preferences section cards) while assigning nothing to `$byaDetailRedesign`,
     * so every one of its gates read the `?? false` fallback and the branch was unreachable. It
     * was migrated markup behind a switch with no wire to it. Phase 3 assigns the variable from
     * enabledFor('buyer'), which is what moved buyer from "contains redesign markup" to "consults
     * the flag" and therefore into this list.
     *
     * THE LIST GROWING IS NOT THE ROLLOUT. `redesign_roles` still ships as landlord alone, so
     * buyer reading the flag changes no rendered page; it makes buyer switchable by config rather
     * than by code. That separation is the whole point of the allowlist, and it is why this entry
     * can be added without any accompanying rollout decision.
     *
     * A FOURTH ENTRY IS STILL THE SIGNAL. Seller and tenant have not been migrated and must not
     * appear here; if one does, the same question applies to it that Phase 3 answered for buyer.
     */
    public function test_the_flag_consumers_are_exactly_the_migrated_views_and_the_shared_shell(): void
    {
        $consumers = [];

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if ($item->isFile() && str_contains((string) file_get_contents($item->getPathname()), 'HireAgentDetailRedesign')) {
                $consumers[] = str_replace(base_path() . '/', '', $item->getPathname());
            }
        }

        sort($consumers);

        $this->assertSame(
            [
                'resources/views/components/hire-agent/detail-shell.blade.php',
                'resources/views/hire_buyer_agent/view.blade.php',
                'resources/views/hire_landlord_agent/view.blade.php',
                // S1. Seller reads the flag but the allowlist does not grant it, so the view is
                // capable of the redesign and does not have it — the same state tenant entered
                // at T1. With seller in, every role view plus the shared shell gates on the
                // role-aware reader, which is the end state this list was tracking toward.
                'resources/views/hire_seller_agent/view.blade.php',
                // T1. Tenant reads the flag but the allowlist does not grant it, so the view is
                // capable of the redesign and does not have it. Declared here because that is
                // the decision this list records — which files gate — not which roles are live.
                'resources/views/hire_tenant_agent/view.blade.php',
            ],
            $consumers,
            'The set of views gating on the detail redesign must stay known. ALL read it through '
            . 'enabledFor(), so role scope comes from config in every file. A further entry means a '
            . 'file started gating on the redesign without that being decided — which is the signal, '
            . 'not a failure to fix by widening this list.'
        );
    }

    /**
     * NEITHER consumer may read the master switch. The landlord view used to, and it was a bug.
     *
     * The reasoning for it was that the redesigned markup lives in that file, so the role is a
     * property of the file rather than a value — sound about role scope, and silent about the
     * failure it caused. That file's markup depends on the framework stylesheet's redesign block,
     * which the shell gates on enabledFor(); with the master on and landlord off the allowlist the
     * page rendered the markup with none of the CSS that lays it out. Failing open into a broken
     * page is the wrong direction for a rollout switch.
     *
     * ASSERTED AT SOURCE, like the shell guard below, because it is a statement about which method
     * a file calls rather than about what any page renders. The rendered behaviour is pinned
     * separately by HireAgentDetailShellLayoutTest, and both are worth having: this one fails on
     * the line that caused it, that one fails on the symptom a reader would actually see.
     *
     * `enabled()` is NOT deprecated and is not removed. It still answers the master switch, which
     * is a genuine question — the tests above ask it. What is asserted here is only that no VIEW
     * makes a rollout decision with it.
     */
    public function test_no_view_gates_on_the_master_switch(): void
    {
        foreach ([
            'resources/views/components/hire-agent/detail-shell.blade.php',
            'resources/views/hire_buyer_agent/view.blade.php',
            'resources/views/hire_landlord_agent/view.blade.php',
            'resources/views/hire_seller_agent/view.blade.php',
            'resources/views/hire_tenant_agent/view.blade.php',
        ] as $path) {
            $this->assertStringNotContainsString(
                'HireAgentDetailRedesign::enabled()',
                (string) file_get_contents(base_path($path)),
                "{$path} must gate on enabledFor(), not the master switch — the two disagreeing is "
                . 'what let the body render redesign markup without the stylesheet that lays it out.'
            );
        }
    }

    /**
     * The shell must consult the ROLE-AWARE reader, never the master switch.
     *
     * This is the one-line difference between "landlord is a pilot" and "one environment variable
     * migrates four roles". Asserted at source, because it is a statement about which method the
     * file calls rather than about what any page renders.
     */
    public function test_the_shared_shell_reads_the_role_aware_flag_not_the_master_switch(): void
    {
        $src = (string) file_get_contents(
            base_path('resources/views/components/hire-agent/detail-shell.blade.php')
        );

        $this->assertStringContainsString('HireAgentDetailRedesign::enabledFor($role)', $src);
        $this->assertStringNotContainsString('HireAgentDetailRedesign::enabled()', $src);
    }
}
