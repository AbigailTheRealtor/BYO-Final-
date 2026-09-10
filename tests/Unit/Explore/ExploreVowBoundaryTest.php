<?php

namespace Tests\Unit\Explore;

use App\Services\Explore\ExploreAccessTier;
use App\Services\Explore\Vow\VowAvailability;
use Tests\TestCase;

/**
 * VOW is absent from this installation, and the code says so out loud.
 *
 * These assertions are the record of the 2026-09-10 audit finding. If a genuine
 * VOW capability is ever built, this file is where the change becomes visible
 * — deliberately, so that turning VOW on cannot be a quiet config edit.
 */
class ExploreVowBoundaryTest extends TestCase
{
    /** @test */
    public function vow_is_reported_as_missing(): void
    {
        $vow = new VowAvailability();

        $this->assertFalse($vow->isAvailable());
        $this->assertSame('MISSING', $vow->status());
    }

    /**
     * The flag is not the gate.
     *
     * Even with EXPLORE_VOW_ENABLED true, the tier stays public. A boolean in a
     * config file is the wrong last line of defence between an unapproved
     * licence tier and a member of the public.
     *
     * @test
     */
    public function enabling_the_flag_does_not_grant_the_vow_tier(): void
    {
        config(['explore.vow_enabled' => true]);

        $vow = new VowAvailability();

        $this->assertTrue($vow->flagRequested());
        $this->assertFalse($vow->isAvailable());
        $this->assertSame(ExploreAccessTier::PUBLIC_IDX, $vow->decideTier(null));
    }

    /**
     * Being logged in is not being VOW-registered.
     *
     * A gate implemented as Auth::check() would be a fake VOW gate wearing a
     * real one's name — the specific thing the brief forbids.
     *
     * @test
     */
    public function an_authenticated_user_is_not_a_vow_consumer(): void
    {
        $vow = new VowAvailability();

        $this->assertSame(
            ExploreAccessTier::PUBLIC_IDX,
            $vow->decideTier(new \stdClass())
        );
    }

    /** @test */
    public function the_activation_requirements_are_stated_in_code(): void
    {
        $requirements = (new VowAvailability())->activationRequirements();

        $this->assertNotEmpty($requirements);

        $joined = strtolower(implode(' ', $requirements));

        foreach (['agreement', 'dataset', 'registration', 'historical media'] as $topic) {
            $this->assertStringContainsString($topic, $joined);
        }
    }
}
