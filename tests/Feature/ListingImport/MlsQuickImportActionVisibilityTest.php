<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quick Import's action buttons must be visible, and its listing methods must
 * match the canonical Create Listing flow.
 *
 * WHY A TEST ASSERTS AN INLINE STYLE
 * ----------------------------------
 * Normally a rendered colour is not worth pinning. Here it is the fix itself.
 * public/css/app.css (Breeze's Tailwind build) ships Preflight, whose
 * `[type='button'] { background-color: transparent }` has the SAME specificity
 * as Bootstrap's `.btn` (0,1,0) and is loaded afterwards by layouts.main — so a
 * filled button renders with no background while `.btn-primary:hover` (0,2,0)
 * still works. The result is a button that is invisible until hovered, which is
 * exactly what manual testing found. An inline declaration is what outranks
 * both without touching global CSS, and this asserts it stays.
 */
class MlsQuickImportActionVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
        ]);

        $this->user = User::factory()->create(['user_type' => 'seller']);
    }

    /** @return array<string, array{0: class-string}> */
    public function roleProvider(): array
    {
        return [
            'seller'   => [SellerMlsQuickImport::class],
            'landlord' => [LandlordMlsQuickImport::class],
        ];
    }

    // ── Button visibility ────────────────────────────────────────────────────

    /**
     * "Find My Listing" — step 1's only action.
     *
     * @dataProvider roleProvider
     */
    public function test_find_my_listing_button_has_a_visible_background(string $component): void
    {
        $html = Livewire::actingAs($this->user)->test($component)->lastRenderedDom;

        $this->assertStringContainsString('Find My Listing', $html);
        $this->assertMatchesRegularExpression(
            '/<button[^>]*wire:click="findListing"[^>]*>/s',
            $html,
            'the lookup button is missing'
        );
        $this->assertButtonCarriesBackground($html, 'findListing');
    }

    /**
     * "Continue" (step 3) and "Review My Listing" (step 4).
     *
     * `step` is a public Livewire property, so the later steps can be rendered
     * directly without driving a live Bridge lookup — this test is about the
     * markup, not the flow.
     *
     * @dataProvider roleProvider
     */
    public function test_continue_and_review_buttons_have_a_visible_background(string $component): void
    {
        $method = Livewire::actingAs($this->user)->test($component)
            ->set('step', 'method')->lastRenderedDom;

        $this->assertStringContainsString('Continue', $method);
        $this->assertButtonCarriesBackground($method, 'continueToTerms');

        $terms = Livewire::actingAs($this->user)->test($component)
            ->set('step', 'terms')->lastRenderedDom;

        $this->assertStringContainsString('Review My Listing', $terms);
        $this->assertButtonCarriesBackground($terms, 'continueToReview');
    }

    /**
     * "Publish Listing" — the last action, and a btn-success, which Preflight
     * flattens for exactly the same reason.
     *
     * @dataProvider roleProvider
     */
    public function test_publish_button_has_a_visible_background(string $component): void
    {
        $html = Livewire::actingAs($this->user)->test($component)
            ->set('step', 'review')->lastRenderedDom;

        $this->assertStringContainsString('Publish Listing', $html);
        $this->assertButtonCarriesBackground($html, 'publish');
    }

    /**
     * Outline buttons are deliberately NOT given a background — theirs is meant
     * to be transparent, so Preflight changes nothing about them. Pinned so a
     * later "consistency" pass does not fill them in.
     *
     * @dataProvider roleProvider
     */
    public function test_outline_buttons_are_left_transparent(string $component): void
    {
        $html = Livewire::actingAs($this->user)->test($component)
            ->set('step', 'terms')->lastRenderedDom;

        $pattern = '/<button(?:(?!<\/button>)[\s\S])*?wire:click="backToMethod"(?:(?!<\/button>)[\s\S])*?<\/button>/';
        $this->assertMatchesRegularExpression($pattern, $html, 'expected a Back action on the terms step');

        preg_match($pattern, $html, $m);

        $this->assertStringContainsString('btn-outline-secondary', $m[0]);
        $this->assertStringNotContainsString('background-color', $m[0]);
    }

    private function assertButtonCarriesBackground(string $html, string $action): void
    {
        $pattern = '/<button(?:(?!<\/button>)[\s\S])*?wire:click="' . preg_quote($action, '/') . '"(?:(?!<\/button>)[\s\S])*?<\/button>/';

        $this->assertMatchesRegularExpression($pattern, $html, "no button found for {$action}");

        preg_match($pattern, $html, $m);
        $button = $m[0] ?? '';

        $this->assertMatchesRegularExpression(
            '/style="[^"]*background-color:\s*#[0-9a-fA-F]{3,6}/',
            $button,
            "the {$action} button has no explicit background-color and will render "
            . 'transparent under the Tailwind Preflight rule in css/app.css'
        );
        $this->assertMatchesRegularExpression(
            '/style="[^"]*color:\s*#[0-9a-fA-F]{3,6}/',
            $button,
            "the {$action} button has no explicit text colour"
        );
    }

    // ── Listing method parity ────────────────────────────────────────────────

    /**
     * Both methods, for both roles, with no flag involved.
     *
     * The canonical Create Listing partials render the Listing Type select with
     * "Bidding Period" and "Traditional" and no gate around either — the
     * bya_beta gate was lifted for Seller and Landlord. Quick Import used to
     * still read that flag, so the shortened path silently offered a smaller set
     * of listing methods than the wizard for the same role.
     *
     * Asserted with the flag explicitly OFF, which is the configuration that
     * used to hide Bidding Period.
     *
     * @dataProvider roleProvider
     */
    public function test_both_listing_methods_are_offered_regardless_of_the_beta_flag(string $component): void
    {
        config(['bya_beta.bidding_period_enabled' => false]);

        $test = Livewire::actingAs($this->user)->test($component)->set('step', 'method');

        $this->assertSame(
            ['Traditional', 'Bidding Period'],
            $test->instance()->availableMethods()
        );

        $test->assertSee('Traditional')->assertSee('Bidding Period');
    }

    /** @dataProvider roleProvider */
    public function test_both_listing_methods_are_selectable(string $component): void
    {
        config(['bya_beta.bidding_period_enabled' => false]);

        foreach (['Traditional', 'Bidding Period'] as $method) {
            Livewire::actingAs($this->user)
                ->test($component)
                ->call('chooseMethod', $method)
                ->assertSet('auction_type', $method)
                ->assertSet('errorMessage', '');
        }
    }

    /**
     * Choosing Bidding Period still carries its requirement.
     *
     * Parity means the same options AND the same obligations — a bidding
     * listing without a period length is the thing the wizard refuses too.
     *
     * @dataProvider roleProvider
     */
    public function test_bidding_period_still_requires_a_period_length(string $component): void
    {
        Livewire::actingAs($this->user)
            ->test($component)
            ->set('step', 'method')
            ->call('chooseMethod', 'Bidding Period')
            ->set('auction_time', '')
            ->call('continueToTerms')
            ->assertSet('step', 'method')
            ->assertSee('how long the bidding period should run');

        Livewire::actingAs($this->user)
            ->test($component)
            ->set('step', 'method')
            ->call('chooseMethod', 'Bidding Period')
            ->set('auction_time', '7 Days')
            ->call('continueToTerms')
            ->assertSet('step', 'terms');
    }

    /**
     * Traditional clears any period length, so a listing cannot carry a stale
     * bidding window it no longer uses.
     *
     * @dataProvider roleProvider
     */
    public function test_switching_to_traditional_clears_the_period_length(string $component): void
    {
        Livewire::actingAs($this->user)
            ->test($component)
            ->call('chooseMethod', 'Bidding Period')
            ->set('auction_time', '7 Days')
            ->call('chooseMethod', 'Traditional')
            ->assertSet('auction_type', 'Traditional')
            ->assertSet('auction_time', '');
    }
}
