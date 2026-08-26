<?php

namespace Tests\Feature\ListingImport;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * The Quick Import pages must be served through the application layout.
 *
 * THE BLIND SPOT THIS CLOSES
 * --------------------------
 * Every other test of this flow drives the component through Livewire::test(),
 * which renders the component's own view and NOTHING AROUND IT. The layout is
 * never involved, so the whole suite passed while the real browser page was
 * unusable.
 *
 * The component rendered with ->layout('layouts.app') — the untouched Laravel
 * Breeze starter layout. That layout carries no @livewireScripts, so the browser
 * received a Livewire component with no Livewire runtime: wire:click never
 * fired and "Find My Listing" issued no request at all. It carries no
 * @livewireStyles either, so the [wire:loading] rule that hides the "Searching…"
 * span was missing and the span showed from page load — which read as a hung
 * lookup that had in fact never started. And it carries no Bootstrap, which this
 * template's markup is written against, so the page was half-styled.
 *
 * Every assertion below therefore runs against a REAL HTTP GET, because the
 * defect lived entirely in the gap between the component and the page.
 *
 * Deliberately narrow: this is about the Quick Import pages honouring the
 * layout contract, not a general layout-auditing framework. It asserts what the
 * Livewire runtime demonstrably needs and what the old layout demonstrably
 * lacked, and nothing else about how either layout looks.
 */
class MlsQuickImportLayoutTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        // The flow 404s unless it is switched on; these pages cannot be
        // reached, let alone inspected, with the feature off.
        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
        ]);

        $this->user = User::factory()->create(['user_type' => 'seller']);
    }

    /** @return array<string, array{0: string}> */
    public function quickImportPageProvider(): array
    {
        return [
            'seller'   => ['offer.listing.seller.quick-import'],
            'landlord' => ['offer.listing.landlord.quick-import'],
        ];
    }

    /**
     * The Livewire runtime reaches the browser.
     *
     * Without this the page is inert: the component renders, the server returns
     * 200, and not one wire:click works.
     *
     * @dataProvider quickImportPageProvider
     */
    public function test_quick_import_page_ships_the_livewire_runtime(string $routeName): void
    {
        $response = $this->actingAs($this->user)->get(route($routeName));

        $response->assertOk();

        // @livewireScripts — the script tag and its config blob.
        $response->assertSee('livewire/livewire.js', false);
        $response->assertSee('livewire_token', false);

        // @livewireStyles — the rule that hides wire:loading elements until a
        // request is actually in flight. Its absence is what left "Searching…"
        // on screen permanently.
        $response->assertSee('[wire\:loading]', false);

        // And the component itself is actually mounted on the page.
        $response->assertSee('wire:id', false);
    }

    /**
     * The page is served through the application shell, not the Breeze starter.
     *
     * The template is written in Bootstrap 5; layouts.app ships Tailwind only,
     * which is why the page rendered half-styled.
     *
     * @dataProvider quickImportPageProvider
     */
    public function test_quick_import_page_uses_the_application_layout(string $routeName): void
    {
        $response = $this->actingAs($this->user)->get(route($routeName));

        $response->assertOk();

        // layouts.main
        $response->assertSee('bootstrap-5.2.2/css/bootstrap.min.css', false);

        // …and specifically NOT layouts.app, whose wrapper markup is unmistakable.
        $response->assertDontSee('min-h-screen bg-gray-100', false);
        $response->assertDontSee('font-sans antialiased', false);
    }

    /**
     * The lookup step still renders inside the layout.
     *
     * Guards the swap itself: layouts.main is a @yield('content') layout, so it
     * needs ->extends()->section('content'). Getting that wrong yields a page
     * with the chrome and no component in it — which still returns 200.
     *
     * @dataProvider quickImportPageProvider
     */
    public function test_the_lookup_step_renders_inside_the_layout(string $routeName): void
    {
        $response = $this->actingAs($this->user)->get(route($routeName));

        $response->assertOk();
        $response->assertSee('Find My Listing');
        $response->assertSee('wire:click="findListing"', false);
    }
}
