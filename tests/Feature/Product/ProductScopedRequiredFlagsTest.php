<?php

namespace Tests\Feature\Product;

use Tests\TestCase;

/**
 * The deploy-time flag contract, per product.
 *
 * `deploy:require-flags` can fail a production start. Both MLS direct-import entries
 * it requires are Create Offer Listing surfaces, which a BidYourAgent deployment
 * refuses to serve — so requiring them there would block a start over flags governing
 * nothing that deployment can reach.
 *
 * The scoping narrows nothing for the product that DOES serve the surface, and that
 * is the half worth pinning: this must not become a way to quietly drop a required
 * flag from production.
 */
class ProductScopedRequiredFlagsTest extends TestCase
{
    /** @test */
    public function the_combined_platform_still_requires_both_mls_import_surfaces(): void
    {
        config([
            'products.active'                          => null,
            'products.hosts'                           => [],
            'mls_direct_import.prefill_enabled'        => false,
            'mls_direct_import.quick_import_enabled'   => false,
        ]);

        $this->artisan('deploy:require-flags')->assertExitCode(1);
    }

    /** @test */
    public function a_bidyouragent_deployment_is_not_blocked_by_them(): void
    {
        config([
            'products.active'                        => 'bidyouragent',
            'mls_direct_import.prefill_enabled'      => false,
            'mls_direct_import.quick_import_enabled' => false,
        ]);

        $this->artisan('deploy:require-flags')->assertExitCode(0);
    }

    /** @test */
    public function a_bidyouragent_deployment_is_still_blocked_by_a_hire_agent_flag(): void
    {
        // The Hire Agent hero and detail redesigns carry no product scope, because
        // they are BidYourAgent's own surfaces. Scoping must not have loosened them.
        config([
            'products.active'                     => 'bidyouragent',
            'hire_agent_hero.redesign_enabled'    => false,
        ]);

        $this->artisan('deploy:require-flags')->assertExitCode(1);
    }

    /** @test */
    public function the_contract_scopes_only_the_offer_listing_entries(): void
    {
        $contract = require config_path('required_production_flags.php');

        $scoped = [];

        foreach ($contract['required'] as $key => $spec) {
            if (isset($spec['products'])) {
                $scoped[$key] = $spec['products'];
            }
        }

        $this->assertSame([
            'mls_direct_import.prefill_enabled'      => ['combined'],
            'mls_direct_import.quick_import_enabled' => ['combined'],
        ], $scoped, 'Only the two Create Offer Listing entries may be product-scoped.');
    }
}
