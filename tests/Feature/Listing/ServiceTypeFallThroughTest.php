<?php

namespace Tests\Feature\Listing;

use App\Http\Livewire\TenantAgentAuction as HireTenant;
use App\Support\Listing\ListingWorkflow;
use App\Support\Listing\ServiceTypeMode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * A missing or unrecognised service_type must not render the Limited Service wizard.
 *
 * The reproduced screen was a Seller listing in a five-tab, limited-service-shaped wizard.
 * It got there because the tab bar asked one question:
 *
 *     @if ($service_type === 'full_service') … @else … @endif
 *
 * and NULL is not full_service. Create Offer Listing has no service-type concept and never
 * writes the key, so every Offer Listing row reads back null — which `loadDraft()` then
 * assigned straight over the component's 'full_service' default.
 *
 * These tests pin the three outcomes separately: full service works, Limited Service still
 * works, and the third case is now its own named, fail-closed branch instead of borrowing
 * whichever layout sat next to it in the template.
 */
class ServiceTypeFallThroughTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    /** Distinctive strings that only appear in one branch of the tab bar. */
    private const FULL_ONLY    = 'Representation Preferences &amp; Compatibility';
    private const LIMITED_ONLY = 'Location and Meeting Details';
    private const FAIL_CLOSED  = 'data-service-type-unrecognised';

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    /**
     * Render the Hire wizard with a given service_type.
     *
     * Returns the Livewire testable — this Livewire version has no `html()`, so the
     * assertions below use assertSeeHtml()/assertDontSeeHtml(), which compare against the
     * rendered DOM directly.
     */
    private function renderWith($serviceType, string $userType = 'seller')
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        return Livewire::test(HireTenant::class, ['user_type' => $userType])
            ->set('service_type', $serviceType);
    }

    public function test_full_service_renders_the_full_service_wizard(): void
    {
        $this->renderWith(ServiceTypeMode::FULL)
            ->assertSeeHtml(self::FULL_ONLY)
            ->assertDontSeeHtml(self::FAIL_CLOSED);
    }

    public function test_limited_service_still_renders_the_limited_service_wizard(): void
    {
        $this->renderWith(ServiceTypeMode::LIMITED)
            ->assertSeeHtml(self::LIMITED_ONLY)
            ->assertDontSeeHtml(self::FAIL_CLOSED);
    }

    /**
     * THE REPRODUCED DEFECT.
     *
     * A null service_type must NOT produce the limited-service-shaped tab bar.
     */
    public function test_null_service_type_does_not_render_the_limited_service_shape(): void
    {
        $this->renderWith(null)
            ->assertDontSeeHtml(self::LIMITED_ONLY)
            ->assertDontSeeHtml(self::FULL_ONLY)
            ->assertSeeHtml(self::FAIL_CLOSED);
    }

    public function test_empty_string_service_type_fails_closed(): void
    {
        $this->renderWith('')
            ->assertDontSeeHtml(self::LIMITED_ONLY)
            ->assertSeeHtml(self::FAIL_CLOSED);
    }

    public function test_unrecognised_service_type_fails_closed(): void
    {
        $this->renderWith('flat_fee_v2')
            ->assertDontSeeHtml(self::LIMITED_ONLY)
            ->assertDontSeeHtml(self::FULL_ONLY)
            ->assertSeeHtml(self::FAIL_CLOSED);
    }

    /**
     * The authenticated-agent label on a LEGITIMATE limited_service listing is unchanged.
     *
     * That label is what made the reproduced screen recognisable, so it is worth pinning
     * that narrowing the condition did not disturb it where it legitimately applies.
     */
    public function test_agent_label_is_unchanged_on_a_legitimate_limited_service_listing(): void
    {
        $agent = $this->makeUser();
        $agent->user_type = 'agent';
        $agent->save();

        $this->actingAs($agent);

        Livewire::test(HireTenant::class, ['user_type' => 'seller'])
            ->set('service_type', ServiceTypeMode::LIMITED)
            ->assertSeeHtml('Agent Credentials &amp; Contact Info')
            ->assertSeeHtml(self::LIMITED_ONLY)
            ->assertDontSeeHtml(self::FAIL_CLOSED);
    }

    /** All four roles behave the same way — the fall-through was never role-specific. */
    public function test_every_role_fails_closed_on_a_null_service_type(): void
    {
        foreach (ListingWorkflow::ROLES as $role) {
            $this->renderWith(null, $role)
                ->assertSeeHtml(self::FAIL_CLOSED)
                ->assertDontSeeHtml(self::LIMITED_ONLY);
        }
    }

    // ── The rule itself ────────────────────────────────────────────────────────

    public function test_service_type_mode_recognises_only_the_two_real_values(): void
    {
        $this->assertTrue(ServiceTypeMode::isRecognised('full_service'));
        $this->assertTrue(ServiceTypeMode::isRecognised('limited_service'));

        foreach ([null, '', ' ', 'FULL_SERVICE', 'flat_fee', 0, false, []] as $bad) {
            $this->assertFalse(ServiceTypeMode::isRecognised($bad),
                'unrecognised value must not be accepted: ' . var_export($bad, true));
        }
    }
}
