<?php

namespace Database\Seeders;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
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

    /*
     | Phase 3B accounts. SEPARATE from the detail/card buyer on purpose: those
     | specs assert "no preference yet" on their own listings, and a management
     | fixture that pre-seeds choices for the same account would falsify them.
     */
    public const MANAGE_BUYER  = 'lp-manage-buyer@example.test';
    public const MANAGE_TENANT = 'lp-manage-tenant@example.test';
    public const STRANGER      = 'lp-manage-stranger@example.test';
    public const VD_BUYER      = 'lp-vd-buyer@example.test';

    /** Bridge keys the Virtual Drive proof page is pointed at by the harness. */
    public const VD_KEYS = ['LP-VD-HOME-A', 'LP-VD-HOME-B'];

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

        $manage       = $this->seedManagement();
        $virtualDrive = $this->seedVirtualDrive();

        file_put_contents(
            $fixturePath,
            (string) json_encode([
                'listing_id'       => $listing->id,
                'card_listing_ids' => $cards,
                'search_path'      => '/search/seller-listings?title=Cardview+Court',
                'user_id'          => $user->id,
                'email'            => self::EMAIL,
                'password'         => self::PASSWORD,
                'manage'           => $manage,
                'virtual_drive'    => $virtualDrive,
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

    // ------------------------------------------------------------------ Phase 3B

    /**
     * The Saved / Maybe / Passed management fixture.
     *
     * Every choice is written through ListingPreferenceWriter — the one
     * persistence path — so the history rows the page reads are the ones a
     * real click would have produced, not hand-inserted lookalikes.
     *
     * @return array<string, mixed>
     */
    private function seedManagement(): array
    {
        $writer = app(ListingPreferenceWriter::class);

        $buyer    = $this->account(self::MANAGE_BUYER, 'buyer', 'Manage Buyer');
        $tenant   = $this->account(self::MANAGE_TENANT, 'tenant', 'Manage Tenant');
        $stranger = $this->account(self::STRANGER, 'buyer', 'Other Shopper');

        foreach (['%Manage Street%', '%Hidden Harbor%', '%Stranger Lane%', '%Gone Street%', '%Cleared Court%'] as $pattern) {
            SellerAgentAuction::where('address', 'like', $pattern)->get()->each(function (SellerAgentAuction $old): void {
                SellerAgentAuctionMeta::where('seller_agent_auction_id', $old->id)->delete();
                $old->delete();
            });
        }

        $saved   = $this->publishedListing($buyer, '10 Manage Street, St. Petersburg, FL 33701');
        $maybe   = $this->publishedListing($buyer, '11 Manage Street, St. Petersburg, FL 33701');
        $passed  = $this->publishedListing($buyer, '12 Manage Street, St. Petersburg, FL 33701');
        $hidden  = $this->publishedListing($buyer, '13 Hidden Harbor Way, Clearwater, FL 33755');
        $gone    = $this->publishedListing($buyer, '14 Gone Street, St. Petersburg, FL 33701');
        $cleared = $this->publishedListing($buyer, '15 Cleared Court, St. Petersburg, FL 33701');
        $secret  = $this->publishedListing($buyer, '99 Stranger Lane, St. Petersburg, FL 33701');

        // An MLS-imported listing whose feed withholds the street line. The
        // quick import seeds `title` FROM the address, reproduced here, because
        // that fallback is exactly how an address leaks through a heading.
        $hidden->update(['title' => '13 Hidden Harbor Way, Clearwater, FL 33755']);
        $hidden->saveMeta(MlsQuickImportDraftWriter::META_LISTING_KEY, 'LP-HIDDEN-HARBOR');
        $hidden->saveMeta(MlsQuickImportDraftWriter::META_DISPLAY_PERMISSIONS, [
            'idx_participation' => true,
            'address_display'   => false,
        ]);
        foreach (['property_city' => 'Clearwater', 'property_state' => 'FL', 'property_zip' => '33755'] as $k => $v) {
            $hidden->saveMeta($k, $v);
        }

        $bridge = $this->bridgeListing('LP-MANAGE-MLS', '20 Feed Avenue', 27.7710, -82.6390);

        $landlord = $this->landlordListing($buyer, 'Bayfront Rental Loft');

        $sale = $this->reasonKeys(ListingPreferenceState::Save, SmartTagContext::ResidentialSale, 2);
        $pass = $this->reasonKeys(ListingPreferenceState::Pass, SmartTagContext::ResidentialSale, 1);

        $seller = fn (SellerAgentAuction $l) => new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $l->id);

        $writer->setState((int) $buyer->id, SeekerRole::Buyer, $seller($saved), ListingPreferenceState::Save, $sale);
        $writer->setState((int) $buyer->id, SeekerRole::Buyer, $seller($maybe), ListingPreferenceState::Maybe);
        $writer->setState((int) $buyer->id, SeekerRole::Buyer, $seller($passed), ListingPreferenceState::Pass, $pass);
        $writer->setState((int) $buyer->id, SeekerRole::Buyer, $seller($hidden), ListingPreferenceState::Save);
        $writer->setState((int) $buyer->id, SeekerRole::Buyer, $seller($gone), ListingPreferenceState::Pass);
        $writer->setState((int) $buyer->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id), ListingPreferenceState::Maybe);

        // A withdrawal, so the history carries a clear event.
        $writer->setState((int) $buyer->id, SeekerRole::Buyer, $seller($cleared), ListingPreferenceState::Maybe);
        $writer->clear((int) $buyer->id, SeekerRole::Buyer, $seller($cleared));

        // Somebody else's choice — must never reach the buyer's pages.
        $writer->setState((int) $stranger->id, SeekerRole::Buyer, $seller($secret), ListingPreferenceState::Save, $sale);

        // The tenant's own market, on a rental.
        $writer->setState((int) $tenant->id, SeekerRole::Tenant,
            new SmartTagListingRef(SmartTagListingType::LandlordAgent, (int) $landlord->id), ListingPreferenceState::Save);

        // The listing goes away AFTER it was passed on: the preference stays.
        SellerAgentAuctionMeta::where('seller_agent_auction_id', $gone->id)->delete();
        $gone->delete();

        return [
            'buyer_email'    => self::MANAGE_BUYER,
            'tenant_email'   => self::MANAGE_TENANT,
            'stranger_email' => self::STRANGER,
            'password'       => self::PASSWORD,
            'saved_address'  => '10 Manage Street',
            'maybe_address'  => '11 Manage Street',
            'passed_address' => '12 Manage Street',
            'hidden_street'  => '13 Hidden Harbor Way',
            'hidden_locality'=> 'Clearwater, FL 33755',
            'cleared_address'=> '15 Cleared Court',
            'bridge_address' => '20 Feed Avenue',
            'secret_address' => '99 Stranger Lane',
            'landlord_title' => 'Bayfront Rental Loft',
            'save_reason_labels' => array_values(array_map(
                fn (string $k) => ListingPreferenceReasonCatalog::get($k)->label, $sale)),
            'pass_reason_labels' => array_values(array_map(
                fn (string $k) => ListingPreferenceReasonCatalog::get($k)->label, $pass)),
        ];
    }

    /**
     * Two eligible Bridge homes for the Virtual Drive proof page, 30 m apart.
     *
     * @return array<string, mixed>
     */
    private function seedVirtualDrive(): array
    {
        $this->account(self::VD_BUYER, 'buyer', 'Drive Buyer');

        $a = $this->bridgeListing(self::VD_KEYS[0], '6590 Harness Key Rd', 27.0100, -82.4100);
        $b = $this->bridgeListing(self::VD_KEYS[1], '6580 Harness Key Rd', 27.0102, -82.4103);

        return [
            'buyer_email' => self::VD_BUYER,
            'password'    => self::PASSWORD,
            'keys'        => self::VD_KEYS,
            'bridge_ids'  => [(int) $a->id, (int) $b->id],
            'addresses'   => ['6590 Harness Key Rd', '6580 Harness Key Rd'],
        ];
    }

    private function account(string $email, string $type, string $name): User
    {
        return User::updateOrCreate(['email' => $email], [
            'name'              => $name,
            'first_name'        => $name,
            'last_name'         => 'Fixture',
            'user_type'         => $type,
            'password'          => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);
    }

    /** An Explore-eligible Bridge row — the same shape the Explore test suite builds. */
    private function bridgeListing(string $key, string $street, float $lat, float $lng): BridgeProperty
    {
        BridgeProperty::where('listing_key', $key)->delete();

        return BridgeProperty::create([
            'listing_key'             => $key,
            'listing_id'              => 'MLS-' . $key,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => 'Residential',
            'property_sub_type'       => 'Single Family Residence',
            'list_price'              => 615000,
            'unparsed_address'        => $street,
            'city'                    => 'Englewood',
            'state_or_province'       => 'FL',
            'postal_code'             => '34223',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1720,
            'latitude'                => $lat,
            'longitude'               => $lng,
            'imported_at'             => now(),
            'raw_json'                => json_encode([
                'ListingKey'                          => $key,
                'StandardStatus'                      => 'Active',
                'PropertyType'                        => 'Residential',
                'ListPrice'                           => 615000,
                'UnparsedAddress'                     => $street,
                'City'                                => 'Englewood',
                'StateOrProvince'                     => 'FL',
                'PostalCode'                          => '34223',
                'Latitude'                            => $lat,
                'Longitude'                           => $lng,
                'IDXParticipationYN'                  => true,
                'InternetEntireListingDisplayYN'      => true,
                'InternetAddressDisplayYN'            => true,
                'InternetAutomatedValuationDisplayYN' => true,
                'InternetConsumerCommentYN'           => true,
            ]),
        ]);
    }

    private function landlordListing(User $viewer, string $title): LandlordAgentAuction
    {
        LandlordAgentAuctionMeta::where('meta_key', 'titleListing')->where('meta_value', $title)->get()
            ->each(function (LandlordAgentAuctionMeta $m): void {
                LandlordAgentAuctionMeta::where('landlord_agent_auction_id', $m->landlord_agent_auction_id)->delete();
                LandlordAgentAuction::whereKey($m->landlord_agent_auction_id)->delete();
            });

        $listing = LandlordAgentAuction::create([
            'user_id'     => $viewer->id + 2_000,
            'is_approved' => 1,
            'is_draft'    => false,
            'is_archived' => 0,
        ]);

        foreach ([
            'workflow_type' => 'offer_listing',
            'property_type' => 'Residential Property',
            'titleListing'  => $title,
            'leaseAmount'   => '$2,400/mo',
        ] as $key => $value) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $listing->id,
                'meta_key'                  => $key,
                'meta_value'                => $value,
            ]);
        }

        return $listing;
    }

    /**
     * Reason keys taken from the governed catalog for this state and context,
     * never written out by hand — the vocabulary may change, and a fixture that
     * hard-codes a retired key would seed nothing and pass anyway.
     *
     * @return list<string>
     */
    private function reasonKeys(ListingPreferenceState $state, SmartTagContext $context, int $count): array
    {
        return array_slice(array_keys(ListingPreferenceReasonCatalog::forState($state, $context)), 0, $count);
    }
}
