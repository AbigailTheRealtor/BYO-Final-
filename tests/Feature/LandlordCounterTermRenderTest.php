<?php

namespace Tests\Feature;

use App\Http\Livewire\Landlord\LandlordAgentAuctionCounterTerm;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\LandlordCounterTerm;
use App\Models\User;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The LIVE Landlord counter page must render.
 *
 * Both routed Landlord counter entry points —
 *
 *   landlord/counter-terms/{id}       (LandlordCounteredTermsController@add)
 *   landlord/edit-counter-terms/{id}  (LandlordCounteredTermsController@edit)
 *
 * — render `landlord_counter_terms/add.blade.php`, which mounts
 * `App\Http\Livewire\Landlord\LandlordAgentAuctionCounterTerm`. That component is
 * therefore the authoritative one for this flow.
 *
 * Its view includes the Landlord broker-compensation tab, whose FIRST line is
 * `@include('partials.preset_loaded_banner')`. That shared partial opened with a
 * bare `@if($defaultProfileLoaded)`. The four *bid* components that historically
 * included it all declare `public bool $defaultProfileLoaded`; the counter
 * component never did and never should — it is not a preset-driven surface. So
 * the counter page died in the view with an undefined variable before any user
 * could counter at all.
 *
 * The fix is `@if($defaultProfileLoaded ?? false)` in the shared partial: the
 * banner stays exactly as it was for every caller that sets the flag, and is
 * simply absent for a caller that has no such concept.
 *
 * SCOPE: rendering only. `LandlordAgentAuctionBidCounter` is a DIFFERENT,
 * currently unreachable component (no route, no controller, no @livewire tag,
 * no @include anywhere references it) and its wiring is a separate open issue
 * that this file deliberately does not touch.
 */
class LandlordCounterTermRenderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @return array{owner: User, agent: User, auction: LandlordAgentAuction, bid: LandlordAgentAuctionBid} */
    private function negotiation(): array
    {
        $owner = User::factory()->create(['user_type' => 'landlord']);
        $agent = User::factory()->asAgent()->create();

        // Two decoy auctions first, so the auction id and the bid id can never
        // coincide. `landlord_counter_terms.landlord_agent_auction_id` stores a BID
        // id despite its name (see LandlordCounteredTermsController); an assertion
        // written while those two numbers happened to be equal would pass for
        // either meaning and prove neither.
        LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);
        LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);

        $auction = LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);

        $bid = LandlordAgentAuctionBid::forceCreate([
            'landlord_agent_auction_id' => $auction->id,
            'user_id'                   => $agent->id,
        ]);

        $this->assertNotSame((int) $auction->id, (int) $bid->id);

        return compact('owner', 'agent', 'auction', 'bid');
    }

    // ── 1 + 2. The routed page renders, and the compensation tab renders with it ──

    /** @test */
    public function the_routed_landlord_counter_page_renders(): void
    {
        $n = $this->negotiation();

        $response = $this->actingAs($n['owner'])
            ->get(route('landlord.counter-terms', ['id' => $n['bid']->id]));

        $response->assertOk();
        $response->assertDontSee('Undefined variable');
        $response->assertDontSee('defaultProfileLoaded');
    }

    /** @test */
    public function the_broker_compensation_partial_renders_on_the_routed_counter_page(): void
    {
        $n = $this->negotiation();

        $this->actingAs($n['owner'])
            ->get(route('landlord.counter-terms', ['id' => $n['bid']->id]))
            ->assertOk()
            ->assertSee('Broker Compensation', false);
    }

    /** @test */
    public function the_routed_landlord_edit_counter_page_renders(): void
    {
        $n = $this->negotiation();

        $this->actingAs($n['owner'])
            ->get(route('landlord.edit-counter-terms', ['id' => $n['bid']->id]))
            ->assertOk()
            ->assertSee('Broker Compensation', false);
    }

    // ── 3. A legitimate component mount succeeds ──

    /** @test */
    public function a_legitimate_counter_term_mount_renders_without_the_undefined_flag(): void
    {
        $n = $this->negotiation();

        Livewire::actingAs($n['owner'])
            ->test(LandlordAgentAuctionCounterTerm::class, [
                'pab'   => $n['bid'],
                'bidId' => $n['bid']->id,
            ])
            ->assertSee('Broker Compensation', false)
            ->assertDontSee('Loaded from your preset', false);
    }

    /**
     * @test
     *
     * The counter component must NOT gain the flag as a side effect of this fix.
     * If someone later "fixes" the undefined variable by declaring the property on
     * the counter component instead, the preset banner starts appearing on a
     * negotiation surface that has no preset — this asserts that did not happen.
     */
    public function the_counter_component_still_does_not_declare_the_preset_flag(): void
    {
        $this->assertFalse(
            property_exists(LandlordAgentAuctionCounterTerm::class, 'defaultProfileLoaded'),
            'LandlordAgentAuctionCounterTerm must stay free of $defaultProfileLoaded — '
            . 'the counter flow is not preset-driven. Fix the shared partial, not the component.'
        );
    }

    // ── 4. The real submit path, on the page that can now be reached ──

    /**
     * @test
     *
     * `submit()` swallows every exception into a flashed 'error' string, so a test that
     * only asserted "no exception was thrown" would pass on a total write failure.
     * Assert the row, its meta, AND the absence of that flash.
     */
    public function the_live_counter_term_submit_persists_the_counter_and_its_meta(): void
    {
        $n = $this->negotiation();

        Livewire::actingAs($n['owner'])
            ->test(LandlordAgentAuctionCounterTerm::class, [
                'pab'   => $n['bid'],
                'bidId' => $n['bid']->id,
            ])
            ->set('additional_details', 'LIVE-COUNTERTERM-SUBMIT')
            ->call('submit');

        $this->assertNull(
            session('error'),
            'submit() flashed an error instead of saving: ' . (string) session('error')
        );

        $row = LandlordCounterTerm::where('user_id', $n['owner']->id)->latest('id')->first();

        $this->assertNotNull($row, 'The landlord counter term did not persist.');
        $this->assertSame((int) $n['bid']->id, (int) $row->landlord_agent_auction_id);
        $this->assertNotSame((int) $n['auction']->id, (int) $row->landlord_agent_auction_id);
        $this->assertSame(1, (int) $row->status);
        $this->assertSame('LIVE-COUNTERTERM-SUBMIT', $row->getMeta('additional_details'));
    }

    /** @test */
    public function the_live_counter_term_submit_notifies_the_other_party(): void
    {
        $n = $this->negotiation();

        Livewire::actingAs($n['owner'])
            ->test(LandlordAgentAuctionCounterTerm::class, [
                'pab'   => $n['bid'],
                'bidId' => $n['bid']->id,
            ])
            ->call('submit');

        // The listing owner countered, so the bidding agent is the recipient.
        Notification::assertSentTo($n['agent'], CounterBidSubmittedNotification::class);
        Notification::assertNotSentTo($n['owner'], CounterBidSubmittedNotification::class);
    }
}
