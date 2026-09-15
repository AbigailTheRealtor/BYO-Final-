<?php

namespace Tests\Feature\Security;

use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Support\Google\GoogleBrowserMaps;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The SERVER Google key never reaches a browser, and the BROWSER key is the only one that can.
 *
 * WHAT THIS CLOSES
 * ----------------
 * `GOOGLE_PLACES_API_KEY` authenticates this application's own Places and Geocoding calls,
 * behind the admission budgets. It was ALSO printed into every page that loads the Maps SDK —
 * the loader components, the Location DNA map injector and the Stellar Maps Embed iframe —
 * including the PUBLIC Offer Listing detail pages, which need no login. Anyone could read it
 * from the page source and spend it against our account, and no server-side ceiling could see
 * those requests. A key used from both places cannot be referrer-restricted either, so the one
 * protection a browser key has was unavailable to it.
 *
 * The browser key (`config/google_maps_browser.php`) is a separate credential behind its own
 * default-off switch, and there is NO FALLBACK: with it absent the surfaces degrade to what they
 * already do without a credential — free-text address entry and a "not configured" panel.
 *
 * HOW IT IS PROVEN
 * ----------------
 * Two deliberately different fake keys. Every assertion is "the server key does not appear in
 * the rendered output" — page source, script URLs, iframe src, attributes and inline JS alike —
 * which is the property that matters, rather than an inspection of which config key some PHP
 * happens to read.
 *
 * ZERO outbound requests: nothing here loads an SDK or contacts Google.
 */
class GoogleBrowserKeySeparationTest extends TestCase
{
    use DatabaseTransactions;

    private const SERVER_KEY  = 'FAKE-SERVER-KEY-DO-NOT-EMIT';
    private const BROWSER_KEY = 'FAKE-BROWSER-KEY-PUBLIC';
    private const SDK_URL     = 'maps.googleapis.com/maps/api/js';

    protected function setUp(): void
    {
        parent::setUp();

        // The server credential is present and perfectly usable — that is the point. Nothing
        // may put it in a page even when it is right there to be read.
        config([
            'services.google.places_key' => self::SERVER_KEY,
            'google_maps_browser.enabled' => true,
            'google_maps_browser.key'     => self::BROWSER_KEY,
        ]);
    }

    /* ── the reader ─────────────────────────────────────────────────────── */

    /** Both halves must agree, and neither reads the server key. @test */
    public function the_browser_reader_requires_the_switch_and_the_key(): void
    {
        $this->assertTrue(GoogleBrowserMaps::available());
        $this->assertSame(self::BROWSER_KEY, GoogleBrowserMaps::keyForRender());

        config(['google_maps_browser.enabled' => false]);
        $this->assertFalse(GoogleBrowserMaps::available());
        $this->assertSame('', GoogleBrowserMaps::keyForRender(), 'the switch alone withholds the key');

        config(['google_maps_browser.enabled' => true, 'google_maps_browser.key' => '']);
        $this->assertFalse(GoogleBrowserMaps::available());
        $this->assertSame('', GoogleBrowserMaps::keyForRender(), 'and never falls back to the server key');
    }

    /** The switch parses fail-closed, exactly like the server-side Google switches. @test */
    public function the_browser_switch_is_off_unless_explicitly_switched_on(): void
    {
        foreach ([null, '', 'false', '0', 'off', 'no', 'banana', '2'] as $value) {
            $this->assertFalse(
                $this->browserConfig(['GOOGLE_MAPS_BROWSER_ENABLED' => $value])['enabled'],
                'GOOGLE_MAPS_BROWSER_ENABLED=' . var_export($value, true) . ' must leave browser Google OFF'
            );
        }

        foreach (['true', 'TRUE', '1', 'on', 'yes', ' true '] as $value) {
            $this->assertTrue(
                $this->browserConfig(['GOOGLE_MAPS_BROWSER_ENABLED' => $value])['enabled'],
                'GOOGLE_MAPS_BROWSER_ENABLED=' . var_export($value, true) . ' must switch browser Google ON'
            );
        }

        $shipped = $this->browserConfig(['GOOGLE_MAPS_BROWSER_ENABLED' => null, 'GOOGLE_MAPS_BROWSER_KEY' => null]);

        $this->assertFalse($shipped['enabled'], 'the shipped default is OFF');
        $this->assertNull($shipped['key'], 'and no key ships');
    }

    /* ── every rendered surface ─────────────────────────────────────────── */

    /** @return array<string, array{0: string}> */
    public function browserSurfaces(): array
    {
        return [
            'canonical SDK loader' => ['<x-google-maps-script :callback="\'initialize\'" />'],
            'deferred SDK loader'  => ['<x-google-maps-deferred-loader callback="initializeMap" />'],
            'Stellar Maps Embed'   => ['<x-stellar.property-map :latitude="27.95" :longitude="-82.45" address="1 Main St" />'],
        ];
    }

    /**
     * @test
     * @dataProvider browserSurfaces
     */
    public function a_browser_surface_renders_the_browser_key_and_never_the_server_key(string $tag): void
    {
        $html = Blade::render($tag);

        $this->assertStringContainsString(self::BROWSER_KEY, $html, 'the browser credential is what a page carries');
        $this->assertStringNotContainsString(self::SERVER_KEY, $html, 'the SERVER key reached the browser');
    }

    /**
     * @test
     * @dataProvider browserSurfaces
     */
    public function a_browser_surface_emits_no_credential_at_all_when_the_browser_key_is_absent(string $tag): void
    {
        // The no-fallback case: the server key is configured and usable, and must still not appear.
        config(['google_maps_browser.key' => null]);

        $html = Blade::render($tag);

        $this->assertStringNotContainsString(self::SERVER_KEY, $html);
        $this->assertStringNotContainsString(self::BROWSER_KEY, $html);
        $this->assertStringNotContainsString(self::SDK_URL, $html, 'no SDK is loaded without a browser credential');
    }

    /* ── whole pages, public and authenticated ──────────────────────────── */

    /** A published Offer Listing, as its public detail page renders it. */
    private function publishedSellerListing(User $owner): SellerAgentAuction
    {
        config(['offer.playoff_access.allowed_user_ids' => '*']);

        $listing = SellerAgentAuction::create([
            'user_id'     => $owner->id,
            'address'     => '1 Browser Key Way',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('listing_title', 'BROWSER KEY SEPARATION LISTING');
        $listing->saveMeta('property_city', 'Tampa');
        $listing->saveMeta('property_state', 'FL');
        $listing->saveMeta('property_lat', '27.95');
        $listing->saveMeta('property_lng', '-82.45');
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $owner->id])->id);

        return $listing->fresh();
    }

    /** The public listing page — no login, so its source is readable by anyone. @test */
    public function the_public_listing_page_never_carries_the_server_key(): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->publishedSellerListing($owner);

        $response = $this->get(route('offer.listing.seller.view', ['id' => $listing->id]));

        $response->assertStatus(200);
        $this->assertStringNotContainsString(self::SERVER_KEY, $response->getContent(), 'the SERVER key reached a public page');
    }

    /** The same page while signed in as the owner, which renders more of it. @test */
    public function the_authenticated_listing_page_never_carries_the_server_key(): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->publishedSellerListing($owner);

        $response = $this->actingAs($owner)->get(route('offer.listing.seller.view', ['id' => $listing->id]));

        $response->assertStatus(200);
        $this->assertStringNotContainsString(self::SERVER_KEY, $response->getContent(), 'the SERVER key reached an authenticated page');
    }

    /** With browser Google switched off, a page carries no Google credential and loads no SDK. @test */
    public function a_switched_off_page_loads_no_sdk_and_no_credential(): void
    {
        config(['google_maps_browser.enabled' => false]);

        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->publishedSellerListing($owner);

        $content = $this->get(route('offer.listing.seller.view', ['id' => $listing->id]))->getContent();

        $this->assertStringNotContainsString(self::SERVER_KEY, $content);
        $this->assertStringNotContainsString(self::BROWSER_KEY, $content);
        $this->assertStringNotContainsString(self::SDK_URL, $content, 'the page must not load the Maps SDK at all');
    }

    /* ── the one-way rule, at source ────────────────────────────────────── */

    /** No view may read the server credential, by any of its names. @test */
    public function no_view_reads_the_server_credential(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $file) {
            // Comments are prose, not code: the loaders explain in words why they do NOT read the
            // server key, and a scan that counted those sentences would fail on its own rationale.
            $source = self::withoutComments(file_get_contents($file));

            foreach (["services.google.places_key", "GOOGLE_PLACES_API_KEY", 'GoogleCredential::'] as $needle) {
                if (str_contains($source, $needle)) {
                    $offenders[] = ltrim(str_replace(base_path(), '', $file), '/') . " — {$needle}";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These views read the SERVER Google credential. Browser surfaces must use "
            . "App\\Support\\Google\\GoogleBrowserMaps:\n  " . implode("\n  ", $offenders),
        );
    }

    /** The reader itself must not know the server key exists. @test */
    public function the_browser_reader_never_reads_the_server_credential(): void
    {
        // Its own docblock names both — explaining the separation is the point of the class —
        // so the assertion is about CODE, with the comments stripped.
        $source = self::withoutComments(file_get_contents(base_path('app/Support/Google/GoogleBrowserMaps.php')));

        $this->assertStringNotContainsString('services.google', $source);
        $this->assertStringNotContainsString('GoogleCredential', $source);
    }

    /** The deleted backups stay deleted — one printed the server key straight into a script tag. @test */
    public function the_unreferenced_backup_views_are_gone(): void
    {
        foreach ([
            'resources/views/hire_tenant_agent/add.blade09012024.php',
            'resources/views/buyer_criteria/add-bid.blade1.php',
        ] as $path) {
            $this->assertFileDoesNotExist(base_path($path));
        }
    }

    /* ── helpers ────────────────────────────────────────────────────────── */

    /** Blade comments, block comments and line comments removed — what is left is code. */
    private static function withoutComments(string $source): string
    {
        $source = preg_replace('#\{\{--.*?--\}\}#s', '', $source);
        $source = preg_replace('#/\*.*?\*/#s', '', $source);

        return preg_replace('#(^|\s)//[^\n]*#', '$1', $source);
    }

    /** @return string[] every Blade/PHP view file */
    private function viewFiles(): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.php')) {
                $files[] = $entry->getPathname();
            }
        }

        $this->assertNotEmpty($files, 'the view scan found nothing and would prove nothing');

        return $files;
    }

    /**
     * config/google_maps_browser.php as it resolves with the given variables set — or absent,
     * for a null — in every place `env()` reads from, restored afterwards.
     *
     * @param array<string, string|null> $variables
     * @return array<string, mixed>
     */
    private function browserConfig(array $variables): array
    {
        $saved = [];

        foreach ($variables as $name => $value) {
            $saved[$name] = [
                'env'    => array_key_exists($name, $_ENV) ? $_ENV[$name] : null,
                'hadEnv' => array_key_exists($name, $_ENV),
                'server' => array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null,
                'hadSrv' => array_key_exists($name, $_SERVER),
                'putenv' => getenv($name),
            ];

            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            if ($value !== null) {
                $_ENV[$name]    = $value;
                $_SERVER[$name] = $value;
                putenv("{$name}={$value}");
            }
        }

        try {
            return require base_path('config/google_maps_browser.php');
        } finally {
            foreach ($saved as $name => $was) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);

                if ($was['hadEnv']) {
                    $_ENV[$name] = $was['env'];
                }

                if ($was['hadSrv']) {
                    $_SERVER[$name] = $was['server'];
                }

                if ($was['putenv'] !== false) {
                    putenv("{$name}={$was['putenv']}");
                }
            }
        }
    }
}
