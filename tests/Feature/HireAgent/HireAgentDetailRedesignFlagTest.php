<?php

namespace Tests\Feature\HireAgent;

use App\Support\HireAgent\HireAgentDetailRedesign;
use Tests\TestCase;

/**
 * M5.0 — the detail-page redesign flag.
 *
 * The flag ships before anything reads it, so this milestone's whole claim is that it is INERT:
 * off by default, independent of the hero flag, and read in exactly one place. Each of those is
 * asserted here rather than assumed, because all three are the kind of property that is true on
 * the day it is written and quietly false three milestones later.
 */
class HireAgentDetailRedesignFlagTest extends TestCase
{
    /** Merging M5.0 must change nothing, which requires the default to be off. */
    public function test_the_flag_is_off_by_default(): void
    {
        $this->assertFalse(
            config('hire_agent_detail.redesign_enabled'),
            'The detail redesign must default to off so merging it is inert.'
        );

        $this->assertFalse(HireAgentDetailRedesign::enabled());
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
