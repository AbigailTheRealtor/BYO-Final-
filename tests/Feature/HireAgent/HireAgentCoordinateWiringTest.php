<?php

namespace Tests\Feature\HireAgent;

use Tests\TestCase;

/**
 * G6: the four Hire Agent components resolve coordinates, and dispatch Location
 * DNA at their publish boundaries only.
 *
 * WHY STRUCTURAL
 * --------------
 * Two of the three things G6 changed are positional, and position is not
 * something a behavioural test observes inside a 3,200-line component:
 *
 *   1. resolution happens at every save boundary, and
 *   2. dispatch happens at the publish boundaries and NOWHERE else, and
 *   3. resolution precedes the dispatch.
 *
 * (3) is the correctness argument rather than a preference. The pipeline
 * prefers `pre_lat`/`pre_lng` over geocoding, so writing the coordinate first
 * is what lets the dispatched job carry it — and its provenance — into
 * `property_location_dna`. Resolving afterwards would win that race only by
 * luck, and would still pass any test that merely asserted "both happened".
 *
 * (2) is the approved deviation from Create Offer. Drafts resolve but do not
 * dispatch: an unpublished listing has no consumer for Location DNA, and drafts
 * are saved repeatedly. A future edit "restoring parity" with Create Offer by
 * adding a draft dispatch should fail here loudly.
 *
 * The behaviour these calls produce is covered against the real components, the
 * real service and the real pipeline in {@see HireAgentCoordinateIntegrationTest}.
 */
class HireAgentCoordinateWiringTest extends TestCase
{
    /** The four Hire Agent components, and how many dispatch sites each may have. */
    private const HIRE_AGENT = [
        'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php'         => ['resolves' => 2, 'dispatches' => 1],
        'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php'     => ['resolves' => 2, 'dispatches' => 1],
        'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php'     => ['resolves' => 2, 'dispatches' => 1],
        'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php' => ['resolves' => 2, 'dispatches' => 1],
    ];

    private const CREATE_OFFER = [
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php'         => 1,
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php'     => 3,
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php'     => 2,
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php' => 3,
    ];

    /** Methods that publish. Everything else that saves is a draft. */
    private const PUBLISH_METHODS = ['store', 'update'];

    /**
     * Every `ComputeLocationDna::dispatch` in the application: 17 before G6, 21
     * after it added the four Hire Agent publish boundaries, 22 today.
     *
     * THE 22nd SITE
     * -------------
     * {@see \App\Http\Livewire\OfferListing\QuickImport\MlsQuickImportComponent::enrichLocation()},
     * added by the MLS quick-import flow. A listing that publishes through quick
     * import needs Location DNA exactly as one published through a wizard does,
     * so the baseline moves rather than the invariant.
     *
     * That site now resolves a coordinate through the shared concern immediately
     * before it dispatches, as every other site here does. The number is unchanged
     * by that — a resolution was added, not a dispatch — which is exactly what
     * this constant is for. See
     * {@see \Tests\Feature\ListingImport\MlsQuickImportCoordinateResolutionTest}.
     *
     * The Hire Agent four and the Create Offer nine are pinned separately below,
     * so this number's job is unchanged: catch a dispatch appearing anywhere
     * else. It is still a hard number — a 23rd must fail this test and be
     * explained here before it is admitted.
     *
     * Kept in step with the identical constant in
     * {@see \Tests\Feature\Location\CreateOfferCoordinateWiringTest}. Two tests
     * counting the same thing is deliberate — they pin it from opposite ends —
     * and they have to move together.
     */
    private const APP_WIDE_DISPATCH_BASELINE = 22;
    private const HIRE_AGENT_DISPATCH_BASELINE = 4;
    private const CREATE_OFFER_DISPATCH_BASELINE = 9;

    private function source(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents, "{$path} must be readable");

        return $contents;
    }

    /** @return list<int> 1-indexed line numbers of every match */
    private function lines(string $source, string $needle): array
    {
        $hits = [];

        foreach (preg_split('/\R/', $source) ?: [] as $i => $line) {
            if (str_contains($line, $needle)) {
                $hits[] = $i + 1;
            }
        }

        return $hits;
    }

    /** The name of the method a given line sits in, scanning upwards. */
    private function enclosingFunction(string $source, int $line): ?string
    {
        $lines = preg_split('/\R/', $source) ?: [];

        for ($i = $line - 1; $i >= 0; $i--) {
            if (preg_match('/function\s+(\w+)\s*\(/', $lines[$i] ?? '', $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function codeWithoutComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        return $code;
    }

    // ── resolution reaches every save boundary ──────────────────────────────

    public function test_every_hire_agent_component_resolves_at_both_save_boundaries(): void
    {
        foreach (self::HIRE_AGENT as $path => $expected) {
            $this->assertCount(
                $expected['resolves'],
                $this->lines($this->source($path), 'resolvePropertyCoordinates($'),
                basename($path) . ' must resolve at both its save boundaries (draft and publish)'
            );
        }
    }

    public function test_the_eight_resolution_sites_are_the_expected_methods(): void
    {
        $found = [];

        foreach (array_keys(self::HIRE_AGENT) as $path) {
            $source = $this->source($path);

            foreach ($this->lines($source, 'resolvePropertyCoordinates($') as $line) {
                $found[] = basename($path) . '::' . $this->enclosingFunction($source, $line);
            }
        }

        sort($found);

        $this->assertSame([
            'LandLordAgentAuction.php::saveDraft',
            'LandLordAgentAuction.php::store',
            'LandLordAgentAuctionEdit.php::saveDraft',
            'LandLordAgentAuctionEdit.php::update',
            'SellerAgentAuction.php::saveDraft',
            'SellerAgentAuction.php::store',
            'SellerAgentAuctionEdit.php::saveDraft',
            'SellerAgentAuctionEdit.php::update',
        ], $found);
    }

    // ── dispatch is publish-only ────────────────────────────────────────────

    public function test_dispatch_occurs_only_in_publish_methods(): void
    {
        foreach (array_keys(self::HIRE_AGENT) as $path) {
            $source = $this->source($path);

            foreach ($this->lines($source, 'ComputeLocationDna::dispatch') as $line) {
                $enclosing = $this->enclosingFunction($source, $line);

                $this->assertContains(
                    $enclosing,
                    self::PUBLISH_METHODS,
                    basename($path) . ": Location DNA is dispatched from {$enclosing}(), which is not a publish boundary"
                );
            }
        }
    }

    public function test_no_draft_boundary_dispatches_location_dna(): void
    {
        // The approved G6 deviation from Create Offer. A draft nobody can see has
        // no consumer for Location DNA, and drafts are saved repeatedly.
        foreach (array_keys(self::HIRE_AGENT) as $path) {
            $source = $this->source($path);

            foreach ($this->lines($source, 'ComputeLocationDna::dispatch') as $line) {
                $this->assertNotSame(
                    'saveDraft',
                    $this->enclosingFunction($source, $line),
                    basename($path) . ': saveDraft() must not dispatch Location DNA'
                );
            }
        }
    }

    public function test_every_publish_method_dispatches_exactly_once(): void
    {
        foreach (self::HIRE_AGENT as $path => $expected) {
            $this->assertCount(
                $expected['dispatches'],
                $this->lines($this->source($path), 'ComputeLocationDna::dispatch'),
                basename($path) . ' must dispatch exactly once, at its publish boundary'
            );
        }
    }

    // ── ordering ────────────────────────────────────────────────────────────

    public function test_resolution_precedes_every_dispatch(): void
    {
        foreach (array_keys(self::HIRE_AGENT) as $path) {
            $source = $this->source($path);

            $resolves = $this->lines($source, 'resolvePropertyCoordinates($');

            foreach ($this->lines($source, 'ComputeLocationDna::dispatch') as $dispatchLine) {
                $method = $this->enclosingFunction($source, $dispatchLine);

                $preceding = array_filter(
                    $resolves,
                    fn (int $r) => $r < $dispatchLine && $this->enclosingFunction($source, $r) === $method
                );

                $this->assertNotEmpty(
                    $preceding,
                    basename($path) . ": the dispatch at line {$dispatchLine} must be preceded by a resolution inside {$method}()"
                );
            }
        }
    }

    // ── baselines ───────────────────────────────────────────────────────────

    public function test_the_application_wide_dispatch_count_is_the_g6_baseline(): void
    {
        $total = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $total += count($this->lines((string) file_get_contents($file->getPathname()), 'ComputeLocationDna::dispatch'));
            }
        }

        $this->assertSame(
            self::APP_WIDE_DISPATCH_BASELINE,
            $total,
            'G6 adds exactly four dispatch sites — the Hire Agent publish boundaries — and the only '
            . 'other one added since is MLS quick import, which the baseline accounts for'
        );
    }

    public function test_hire_agent_dispatch_total_is_four(): void
    {
        $total = 0;

        foreach (array_keys(self::HIRE_AGENT) as $path) {
            $total += count($this->lines($this->source($path), 'ComputeLocationDna::dispatch'));
        }

        $this->assertSame(self::HIRE_AGENT_DISPATCH_BASELINE, $total);
    }

    public function test_the_create_offer_dispatch_baseline_is_untouched(): void
    {
        $total = 0;

        foreach (self::CREATE_OFFER as $path => $expected) {
            $found = count($this->lines($this->source($path), 'ComputeLocationDna::dispatch'));

            $this->assertSame($expected, $found, basename($path) . ' must keep its G5 dispatch count');

            $total += $found;
        }

        $this->assertSame(self::CREATE_OFFER_DISPATCH_BASELINE, $total, 'G6 must not disturb the Create Offer nine');
    }

    // ── the canonical listing types ─────────────────────────────────────────

    public function test_hire_agent_uses_the_canonical_listing_types(): void
    {
        $expected = [
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php'         => 'seller_agent',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php'     => 'seller_agent',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php'     => 'landlord_agent',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php' => 'landlord_agent',
        ];

        foreach ($expected as $path => $listingType) {
            $code = $this->codeWithoutComments($this->source($path));

            $this->assertStringContainsString("'{$listingType}'", $code, basename($path));

            // The pair HasMlsImport writes. They match no reader, so a row saved
            // under them is an orphan — see the G6 audit.
            foreach (['seller_agent_auction', 'landlord_agent_auction'] as $orphan) {
                $this->assertStringNotContainsString(
                    "'{$orphan}'",
                    $code,
                    basename($path) . " must not use the orphan listing type '{$orphan}'"
                );
            }
        }
    }

    // ── save boundaries only ────────────────────────────────────────────────

    public function test_resolution_is_never_triggered_from_a_render_or_lifecycle_hook(): void
    {
        foreach (array_keys(self::HIRE_AGENT) as $path) {
            $source = $this->source($path);

            foreach ($this->lines($source, 'resolvePropertyCoordinates($') as $line) {
                $enclosing = $this->enclosingFunction($source, $line);

                $this->assertNotNull($enclosing, basename($path) . ": line {$line} is not inside a method");

                foreach (['render', 'mount', 'hydrate', 'dehydrate', 'updating', 'updated', 'selectAddressSuggestion', 'selectStateSuggestion'] as $forbidden) {
                    $this->assertStringStartsNotWith(
                        $forbidden,
                        $enclosing,
                        basename($path) . ": resolution must not run from {$enclosing}()"
                    );
                }
            }
        }
    }

    // ── ownership (PR #61) is structurally intact ───────────────────────────

    public function test_the_ownership_assertion_still_precedes_the_try_block(): void
    {
        // PR #61 put assertCanManageAuction() BEFORE the try on purpose: abort()
        // throws an HttpException, and the catch below would otherwise swallow a
        // 403 into a flash message. G6 inserted code inside those try blocks, so
        // this pins that the guard did not move in with it.
        foreach ([
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
        ] as $path) {
            $source = $this->source($path);

            foreach (['saveDraft', 'store'] as $method) {
                $guard = null;

                foreach ($this->lines($source, 'assertCanManageAuction(') as $line) {
                    if ($this->enclosingFunction($source, $line) === $method) {
                        $guard = $line;
                        break;
                    }
                }

                $this->assertNotNull($guard, basename($path) . ": {$method}() lost its ownership assertion");

                $firstTry = null;

                foreach ($this->lines($source, 'try {') as $line) {
                    if ($line > $guard && $this->enclosingFunction($source, $line) === $method) {
                        $firstTry = $line;
                        break;
                    }
                }

                $this->assertNotNull($firstTry, basename($path) . ": {$method}() has no try block after the guard");
                $this->assertLessThan(
                    $firstTry,
                    $guard,
                    basename($path) . ": {$method}()'s ownership assertion must stay outside the try"
                );
            }
        }
    }

    // ── untouched neighbours ────────────────────────────────────────────────

    public function test_has_mls_import_is_untouched_and_still_fails_closed(): void
    {
        $source = $this->source('app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php');

        $this->assertStringContainsString(
            'LocationDnaGeocodeService',
            $source,
            'The legacy fallback is expected to still be here, unchanged'
        );
        $this->assertStringNotContainsString(
            'resolvePropertyCoordinates',
            $source,
            'G6 must not wire the shared MLS concern'
        );
        // Its orphan listing types are known debt, deliberately left alone by G6.
        $this->assertStringContainsString('seller_agent_auction', $source);
        $this->assertFalse(
            config('google_places.enabled'),
            'The legacy Google path must remain fail-closed'
        );
    }

    public function test_buyer_and_tenant_remain_outside_this_architecture(): void
    {
        // Their geography is multi-area search criteria (HasSearchAreas), not one
        // property's point. They have no property_lat to resolve.
        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringNotContainsString(
                'ResolvesPropertyCoordinates',
                $source,
                basename($path) . ' must not acquire single-property coordinate resolution'
            );
            $this->assertStringNotContainsString(
                'ComputeLocationDna',
                $source,
                basename($path) . ' must not dispatch property Location DNA'
            );
        }
    }

    /**
     * Pinned per file rather than asserted absent, because two of these files
     * already call Google.
     *
     * Both Edit components carry one pre-existing Places **autocomplete** request
     * (`/maps/api/place/autocomplete/json`) — address typeahead, not geocoding,
     * and untouched by G6. Asserting "no googleapis anywhere" would fail on that
     * inherited debt and say nothing about what G6 did. Pinning the counts is the
     * assertion that actually holds: a genuinely new Google call breaks it.
     */
    public function test_g6_adds_no_google_reference(): void
    {
        $inherited = [
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php'         => 0,
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php'     => 1,
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php'     => 0,
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php' => 1,
        ];

        foreach ($inherited as $path => $expected) {
            $code = $this->codeWithoutComments($this->source($path));

            $this->assertCount(
                $expected,
                $this->lines($code, 'googleapis'),
                basename($path) . ' must carry exactly its pre-G6 Google reference count'
            );

            // Geocoding specifically must not appear in any of them.
            foreach (['maps/api/geocode', 'GoogleGeocoder'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    basename($path) . " must not gain a Google geocoding path via {$needle}"
                );
            }
        }
    }

    public function test_the_shipped_census_default_is_still_off(): void
    {
        $shipped = require config_path('census_geocoder.php');

        $this->assertFalse($shipped['enabled'], 'G6 must not enable Census');
    }
}
