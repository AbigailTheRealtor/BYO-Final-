<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction;
use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuction;
use App\Http\Livewire\OfferAuction;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\TenantAgentAuction;
use App\Http\Middleware\EnsureProductSurface;
use App\Models\User;
use App\Support\Product\ProductSurfaceCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The product boundary on the generic Livewire endpoint.
 *
 * EnsureProductSurface refuses a BidYourOffer ROUTE in BidYourAgent mode. It does
 * not see a component: every Livewire update is a POST to `livewire/message/{name}`,
 * which the catalog classifies as infrastructure, so the route gate passes it.
 *
 * What stops a crafted request is the snapshot. A Livewire 2 update must carry a
 * fingerprint and server memo signed with APP_KEY, so a component cannot be invoked
 * from nothing — but a snapshot minted where the surface IS served stays valid
 * wherever the key is the same: a host-mapped deployment serving both products, a
 * BidYourAgent deployment sharing the combined platform's key, or a tab opened
 * before the product was switched. Replayed against a BidYourAgent deployment, that
 * snapshot hydrated the Offer Listing component and ran its actions.
 *
 * The fingerprint records the route the component was rendered on, and Livewire
 * re-runs that route's PERSISTENT middleware on every update. Registering the
 * product gate as persistent makes every update answer the same question the page
 * did: is this surface served here? These tests replay real, server-minted
 * snapshots through the whole HTTP kernel — not Livewire::test(), whose transport
 * disables middleware and so cannot see this boundary at all.
 */
class BidYourAgentLivewireProductBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    private function combined(): void
    {
        config(['products.active' => null, 'products.hosts' => []]);
    }

    private function bidYourAgent(): void
    {
        config(['products.active' => 'bidyouragent', 'products.hosts' => []]);
    }

    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Render a page through the full stack and return the signed snapshot the
     * server minted for one component on it — exactly what a browser holds.
     */
    private function snapshotFrom(string $uri, string $componentClass, ?User $as): array
    {
        $response = $as ? $this->actingAs($as)->get($uri) : $this->get($uri);
        $response->assertOk();

        $name = $componentClass::getName();

        preg_match_all('/wire:initial-data="([^"]+)"/', (string) $response->getContent(), $matches);

        foreach ($matches[1] as $encoded) {
            $data = json_decode(html_entity_decode($encoded, ENT_QUOTES), true);

            if (($data['fingerprint']['name'] ?? null) === $name) {
                return $data;
            }
        }

        $this->fail("{$uri} rendered no snapshot for Livewire component [{$name}].");
    }

    /**
     * POST a snapshot to the generic endpoint the way the Livewire client does.
     * `$refresh` is an action: it hydrates the component and re-renders it.
     */
    private function replay(array $snapshot, ?array $updates = null): TestResponse
    {
        $updates ??= [[
            'type'    => 'callMethod',
            'payload' => ['id' => 'boundary', 'method' => '$refresh', 'params' => []],
        ]];

        return $this->withHeaders(['X-Livewire' => 'true'])->postJson(
            '/livewire/message/' . $snapshot['fingerprint']['name'],
            [
                'fingerprint' => $snapshot['fingerprint'],
                'serverMemo'  => $snapshot['serverMemo'],
                'updates'     => $updates,
            ]
        );
    }

    private function assertRefusedWithoutDisclosure(TestResponse $response, array $snapshot): void
    {
        $response->assertNotFound();

        $body = (string) $response->getContent();

        $this->assertStringNotContainsString('serverMemo', $body);
        $this->assertStringNotContainsString('"effects"', $body);
        $this->assertStringNotContainsString($snapshot['fingerprint']['id'], $body);
    }

    /**
     * Create Offer Listing, all four roles — the BidYourOffer components a
     * consumer account can mount.
     *
     * @return array<string, array{0: string, 1: class-string<Component>, 2: string}>
     */
    public function bidYourOfferComponents(): array
    {
        return [
            'seller offer listing'   => ['/offer-listing/seller',   SellerOfferListing::class,   'seller'],
            'buyer offer listing'    => ['/offer-listing/buyer',    BuyerOfferListing::class,    'buyer'],
            'landlord offer listing' => ['/offer-listing/landlord', LandlordOfferListing::class, 'landlord'],
            'tenant offer listing'   => ['/offer-listing/tenant',   TenantOfferListing::class,   'tenant'],
        ];
    }

    /**
     * The Hire Agent create wizard for each role — the components BidYourAgent is.
     *
     * @return array<string, array{0: string, 1: class-string<Component>, 2: string}>
     */
    public function bidYourAgentComponents(): array
    {
        return [
            'seller hire agent'   => ['/hire/agent/seller',          SellerAgentAuction::class,   'seller'],
            'buyer hire agent'    => ['/buyer/add-auction',          BuyerAgentAuction::class,    'buyer'],
            'landlord hire agent' => ['/landlord/hire/agent/auction', LandLordAgentAuction::class, 'landlord'],
            'tenant hire agent'   => ['/hire/agent/auction/tenant',  TenantAgentAuction::class,   'tenant'],
        ];
    }

    /**
     * @test
     * @dataProvider bidYourOfferComponents
     */
    public function a_bidyouroffer_snapshot_cannot_be_replayed_into_a_bidyouragent_deployment(
        string $uri,
        string $class,
        string $role
    ): void {
        $this->combined();
        $user     = User::factory()->create(['user_type' => $role]);
        $snapshot = $this->snapshotFrom($uri, $class, $user);

        $this->bidYourAgent();

        $this->assertRefusedWithoutDisclosure($this->replay($snapshot), $snapshot);
    }

    /**
     * @test
     * @dataProvider bidYourOfferComponents
     */
    public function the_same_replay_is_still_served_by_the_combined_platform(
        string $uri,
        string $class,
        string $role
    ): void {
        // The control. Without it the refusal above could be any breakage at all.
        $this->combined();
        $user     = User::factory()->create(['user_type' => $role]);
        $snapshot = $this->snapshotFrom($uri, $class, $user);

        $response = $this->replay($snapshot);

        $response->assertOk();
        $response->assertJsonStructure(['effects', 'serverMemo']);
    }

    /** @test */
    public function a_signed_out_replay_gets_the_same_404_and_not_a_login_challenge(): void
    {
        // A 401 would confirm the surface exists and invite a sign-in to reach it.
        $this->combined();
        $snapshot = $this->snapshotFrom(
            '/offer-listing/seller',
            SellerOfferListing::class,
            User::factory()->create(['user_type' => 'seller'])
        );

        $this->signOut();
        $this->bidYourAgent();

        $this->assertRefusedWithoutDisclosure($this->replay($snapshot), $snapshot);
    }

    /** @test */
    public function an_agent_account_is_refused_the_same_way(): void
    {
        $this->combined();
        $agent    = User::factory()->create(['user_type' => 'agent']);
        $snapshot = $this->snapshotFrom('/offer-listing/seller', SellerOfferListing::class, $agent);

        $this->bidYourAgent();

        $this->assertRefusedWithoutDisclosure($this->replay($snapshot), $snapshot);
    }

    /** @test */
    public function relabelling_the_snapshot_onto_a_hire_agent_page_breaks_its_signature(): void
    {
        // The fingerprint's path is what the gate judges, so it must not be
        // forgeable. It is covered by the checksum; this pins that it stays so.
        $this->combined();
        $snapshot = $this->snapshotFrom(
            '/offer-listing/seller',
            SellerOfferListing::class,
            User::factory()->create(['user_type' => 'seller'])
        );

        $this->bidYourAgent();
        $snapshot['fingerprint']['path'] = 'hire/agent/seller';

        $response = $this->replay($snapshot);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertStringNotContainsString('serverMemo', (string) $response->getContent());
    }

    /**
     * @test
     * @dataProvider bidYourAgentComponents
     */
    public function hire_agent_components_keep_working_in_bidyouragent_mode(
        string $uri,
        string $class,
        string $role
    ): void {
        $this->bidYourAgent();
        $user     = User::factory()->create(['user_type' => $role]);
        $snapshot = $this->snapshotFrom($uri, $class, $user);

        $response = $this->replay($snapshot);

        $response->assertOk();
        $response->assertJsonStructure(['effects', 'serverMemo']);
    }

    /** @test */
    public function the_product_gate_never_stands_in_for_authentication(): void
    {
        // An allowed Hire Agent snapshot replayed without a session still meets
        // `auth`: product visibility and authorization are separate layers.
        $this->bidYourAgent();
        $snapshot = $this->snapshotFrom(
            '/hire/agent/seller',
            SellerAgentAuction::class,
            User::factory()->create(['user_type' => 'seller'])
        );

        $this->signOut();

        $this->replay($snapshot)->assertUnauthorized();
    }

    /** @test */
    public function authentication_still_guards_bidyouroffer_components_on_the_combined_platform(): void
    {
        $this->combined();
        $snapshot = $this->snapshotFrom(
            '/offer-listing/seller',
            SellerOfferListing::class,
            User::factory()->create(['user_type' => 'seller'])
        );

        $this->signOut();

        $this->replay($snapshot)->assertUnauthorized();
    }

    /** @test */
    public function the_product_gate_is_persistent_livewire_middleware(): void
    {
        $this->assertContains(EnsureProductSurface::class, Livewire::getPersistentMiddleware());
    }

    /** @test */
    public function every_bidyouroffer_component_is_mounted_only_on_a_bidyouroffer_route(): void
    {
        // The persistent gate judges the ROUTE a snapshot was minted on. That is a
        // complete answer only while a BidYourOffer component is never mounted on a
        // route BidYourAgent serves — this pins it, so a new mount point is a red
        // build rather than a way around the boundary.
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $class = explode('@', (string) $route->getActionName())[0];

            if (! $this->isBidYourOfferComponent($class)) {
                continue;
            }

            // Registered only in local/development (routes/web.php, LAYER 2 DEV-ONLY).
            if (strpos($route->uri(), 'dev/') === 0) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $disposition = ProductSurfaceCatalog::dispositionFor($method, $route->uri());

                if ($disposition !== ProductSurfaceCatalog::BIDYOUROFFER_ONLY) {
                    $violations[] = "{$method} {$route->uri()} mounts {$class} but is {$disposition}";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /** @test */
    public function no_view_embeds_a_bidyouroffer_component(): void
    {
        // An embed would mint the snapshot under the HOST page's route, and a
        // shared host page is one the gate allows.
        $names = array_map(
            fn (string $class) => $class::getName(),
            array_filter($this->livewireComponentClasses(), fn ($c) => $this->isBidYourOfferComponent($c))
        );

        $this->assertNotEmpty($names);

        $embeds = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($files as $file) {
            if (! $file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            foreach ($names as $name) {
                $quoted = preg_quote($name, '/');

                if (preg_match("/@livewire\(\s*['\"]{$quoted}['\"]|<livewire:{$quoted}[\s\/>]/", $contents)) {
                    $embeds[] = "{$file->getPathname()} embeds {$name}";
                }
            }
        }

        $this->assertSame([], $embeds, implode("\n", $embeds));
    }

    private function isBidYourOfferComponent(string $class): bool
    {
        return class_exists($class)
            && is_subclass_of($class, Component::class)
            && (strpos($class, 'App\\Http\\Livewire\\OfferListing\\') === 0 || $class === OfferAuction::class);
    }

    /**
     * @return array<int, class-string>
     */
    private function livewireComponentClasses(): array
    {
        $classes = [];
        $root    = app_path('Http/Livewire');

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative  = substr($file->getPathname(), strlen($root) + 1, -4);
            $classes[] = 'App\\Http\\Livewire\\' . str_replace('/', '\\', $relative);
        }

        return $classes;
    }
}
