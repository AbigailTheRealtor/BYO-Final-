<?php

namespace Database\Seeders;

use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Support\Safeguards\ProductionDatabaseRefused;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ListingPreferenceBrowserTestSeeder — DEV / CI ONLY
 *
 * The fixture the Playwright `app` project drives: one Buyer account and one
 * published Seller Offer Listing, which is the minimum a real
 * /offer-listing/seller/view/{id} page needs to render the Save | Maybe | Pass
 * control.
 *
 * PRODUCTION GUARD: refuses before writing anything if the target database
 * shows any production signal — the same rule every other fixture seeder here
 * follows, because a standalone invocation inherits the shell and on this host
 * the shell is production.
 *
 * Usage (manual / harness invocation only):
 *   php artisan db:seed --class=ListingPreferenceBrowserTestSeeder
 *
 * DO NOT add this class to DatabaseSeeder::run().
 *
 * IDEMPOTENT: re-running replaces the fixture rather than accumulating copies,
 * so the browser suite can be run repeatedly against one throwaway database.
 */
class ListingPreferenceBrowserTestSeeder extends Seeder
{
    /** Known to the specs; this database is created and destroyed by the harness. */
    public const EMAIL    = 'lp-browser-buyer@example.test';
    public const PASSWORD = 'lp-browser-secret';

    public function run(): void
    {
        ProductionDatabaseRefused::unlessSafe(
            'ListingPreferenceBrowserTestSeeder',
            'php artisan db:seed --class=ListingPreferenceBrowserTestSeeder'
        );

        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name'              => 'LP Browser',
                'first_name'        => 'LP',
                'last_name'         => 'Browser',
                'user_type'         => 'buyer',
                'password'          => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ]
        );

        SellerAgentAuction::where('address', 'like', '%Playwright Way%')->get()
            ->each(function (SellerAgentAuction $old): void {
                SellerAgentAuctionMeta::where('seller_agent_auction_id', $old->id)->delete();
                $old->delete();
            });

        $listing = SellerAgentAuction::create([
            'user_id' => $user->id + 1_000,  // deliberately NOT the viewer: a seeker is not the owner
            'address' => '1 Playwright Way, St. Petersburg, FL 33701',
        ]);

        // `workflow_type` is what makes this an Offer Listing rather than a Hire
        // Agent record — without it the controller 404s. `property_type` is what
        // lets the context resolve, so the strict reason path runs.
        foreach ([
            'workflow_type' => 'offer_listing',
            'property_type' => 'Residential',
        ] as $key => $value) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $listing->id,
                'meta_key'                => $key,
                'meta_value'              => $value,
            ]);
        }

        $this->command?->info('Buyer:   ' . self::EMAIL);
        $this->command?->info('Listing: /offer-listing/seller/view/' . $listing->id);

        // The specs read this to find the listing without parsing console output.
        // The PATH comes from the harness: two instances (feature on and feature
        // off) seed concurrently, and a single shared filename would have them
        // overwrite each other's listing id.
        $fixturePath = getenv('LP_FIXTURE_PATH') ?: storage_path('app/lp-browser-fixture.json');

        file_put_contents(
            $fixturePath,
            (string) json_encode([
                'listing_id' => $listing->id,
                'user_id'    => $user->id,
                'email'      => self::EMAIL,
                'password'   => self::PASSWORD,
            ], JSON_PRETTY_PRINT)
        );
    }
}
