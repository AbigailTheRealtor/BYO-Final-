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

        foreach (['%Playwright Way%', '%Cardview Court%'] as $pattern) {
            SellerAgentAuction::where('address', 'like', $pattern)->get()
                ->each(function (SellerAgentAuction $old): void {
                    SellerAgentAuctionMeta::where('seller_agent_auction_id', $old->id)->delete();
                    $old->delete();
                });
        }

        // The DETAIL page's listing. Deliberately not approved, so it is
        // reachable at its own URL and appears in NO search result.
        $listing = $this->listing($user, '1 Playwright Way, St. Petersburg, FL 33701');

        /*
         | A SEPARATE SET FOR THE CARD PAGE, AND THE SEPARATION IS THE POINT.
         |
         | The two spec files run in parallel against ONE database. While they
         | shared a listing, a card spec's Save and the detail spec's Remove
         | raced on the same row and each failed in the other's cleanup — a
         | flake that looks exactly like a product bug and is not one. Distinct
         | addresses keep the search filter disjoint from the detail fixture.
         |
         | FOUR OF THEM, because every page-level property the card specs assert
         | — one chip catalog, one stylesheet, one behaviour block, controls that
         | act independently — is trivially true of a page with one card.
         */
        $cards = [];
        foreach ([1, 2, 3, 4] as $n) {
            $cards[] = $this->publishedListing($user, "{$n} Cardview Court, St. Petersburg, FL 33701")->id;
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
                'listing_id'       => $listing->id,
                'card_listing_ids' => $cards,
                'search_path'      => '/search/seller-listings?title=Cardview+Court',
                'user_id'          => $user->id,
                'email'            => self::EMAIL,
                'password'         => self::PASSWORD,
            ], JSON_PRETTY_PRINT)
        );
    }

    /**
     * One listing the seller search page will actually return.
     *
     * `is_approved` / `is_draft` / `is_archived` are the discovery filters
     * SellerOfferListingController::searchOfferListings() applies; a listing
     * missing any of them renders on its detail page and appears in no search,
     * which would make a card spec silently assert against an empty grid.
     */
    private function publishedListing(User $user, string $address): SellerAgentAuction
    {
        return $this->listing($user, $address, [
            'is_approved' => 1,
            'is_draft'    => false,
            'is_archived' => 0,
        ]);
    }

    /** @param array<string,mixed> $extra */
    private function listing(User $user, string $address, array $extra = []): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create([
            // Deliberately NOT the viewer: a seeker is not the owner.
            'user_id' => $user->id + 1_000,
            'address' => $address,
        ] + $extra);

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

        return $listing;
    }
}
