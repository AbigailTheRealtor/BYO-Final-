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
     * The flag has no role allowlist, so role scope is enforced entirely by which files consult
     * it. That makes this assertion the actual scoping mechanism for the pilot: if Seller, Buyer
     * or Tenant starts reading the flag, the redesign has silently widened past landlord and this
     * is what says so.
     */
    public function test_the_landlord_detail_view_is_the_only_consumer_of_the_flag(): void
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
            ['resources/views/hire_landlord_agent/view.blade.php'],
            $consumers,
            'The M5 detail redesign is a landlord pilot and the flag carries no role allowlist, so '
            . 'the set of views reading it IS the role scope. Another role appearing here means the '
            . 'pilot widened without that being decided.'
        );
    }
}
