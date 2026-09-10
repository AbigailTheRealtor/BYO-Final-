<?php

namespace Tests\Feature\ListingImport;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WHAT A SELLER OR LANDLORD LISTING PAGE SAYS THE LISTING'S STATUS IS.
 *
 * The model already answered this: SellerAgentAuction::getStatusAttribute()
 * resolves is_sold, then the MLS market status, then the stored value. The
 * published page did not ask it — the hero pill, the hero badge and the
 * "Listing Status" row each read the raw `listing_status` meta value, which on
 * an MLS-linked listing is what the seller typed, not what Stellar currently
 * reports. The page contradicted itself on one screen: 'Active' in the pill,
 * 'Pending' in MLS Details underneath.
 *
 * These render the real template through the real controller, because the
 * failure is a stale string reaching a specific pixel and that is not visible
 * from the resolver alone. Sibling of MlsListPriceDisplayTest, same fixture
 * shape and the same reason for choosing the full path.
 *
 * NO SYNC IS ACTIVATED HERE. `mls_sync.enabled` stays false throughout; a
 * "refreshed" status is simulated by writing the meta key a sync would have
 * written, which is exactly the input the display layer under test consumes.
 *
 * LANDLORD LIVES HERE TOO, AND ON PURPOSE. It had half the same defect — its
 * hero already asked the model, its "Listing Status" row did not — and it is
 * fixed by the same helper. Splitting the two roles into two files is how the
 * assertion that they agree stops being written at all; section F is the
 * landlord half and section D/E the seller's.
 */
class SellerMlsStatusDisplayTest extends TestCase
{
    use RefreshDatabase;

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $meta */
    private function sellerListing(array $meta): SellerAgentAuction
    {
        $user = User::factory()->create();

        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '6817 STONES THROW CIRCLE N UNIT 17208',
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        $this->applyMeta($listing, $meta);
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    /** @param array<string,mixed> $meta */
    private function landlordListing(array $meta): LandlordAgentAuction
    {
        $user = User::factory()->create();

        $listing = LandlordAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'title'       => 'Rental at Stones Throw',
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        $this->applyMeta($listing, $meta);
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    /** @param array<string,mixed> $meta */
    private function applyMeta(object $listing, array $meta): void
    {
        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }
    }

    /**
     * The MLS provenance an imported listing carries. Either identifier is
     * enough for MlsLinkedListingStatus::isLinked(); both are supplied because
     * a real import writes both.
     *
     * @param  array<string,mixed>  $extra
     * @return array<string,mixed>
     */
    private function linked(array $extra = []): array
    {
        return array_merge([
            Meta::META_LISTING_KEY => 'STONES-THROW-KEY',
            Meta::META_MLS_NUMBER  => 'TB8300001',
            Meta::META_PROVIDER    => 'bridge',
        ], $extra);
    }

    private function sellerPage(SellerAgentAuction $listing): string
    {
        return $this->get(route('offer.listing.seller.view', ['id' => $listing->id]))
            ->assertStatus(200)
            ->getContent();
    }

    private function landlordPage(LandlordAgentAuction $listing): string
    {
        return $this->get(route('offer.listing.landlord.view', ['id' => $listing->id]))
            ->assertStatus(200)
            ->getContent();
    }

    // ── Surface readers ──────────────────────────────────────────────────────
    //
    // Asserted against the specific elements rather than the whole document,
    // because the word "Active" legitimately appears elsewhere in the page's
    // JavaScript ('v2Active') and inside the RESO status 'Active Under
    // Contract'. A whole-page assertStringNotContainsString('Active') would
    // pass or fail for reasons unrelated to the status being displayed.

    private function heroStatus(string $html, string $pillClass = 'sol-hero-status'): ?string
    {
        if (! preg_match('/<span class="' . preg_quote($pillClass, '/') . '">(.*?)<\/span>/s', $html, $m)) {
            return null;
        }

        return trim(strip_tags($m[1]));
    }

    private function listingStatusRow(string $html): ?string
    {
        if (! preg_match('/>Listing Status<\/div>\s*<div class="col-md-7"[^>]*>(.*?)<\/div>/s', $html, $m)) {
            return null;
        }

        return trim(strip_tags($m[1]));
    }

    // =====================================================================
    // A. MLS-linked: the hero shows the feed's status, not the stored one
    // =====================================================================

    /** @test */
    public function an_mls_linked_seller_hero_displays_the_effective_mls_status_not_the_stored_one(): void
    {
        $html = $this->sellerPage($this->sellerListing($this->linked([
            'listing_status'            => 'Active',
            Meta::META_STANDARD_STATUS  => 'Pending',
        ])));

        $this->assertSame('Pending', $this->heroStatus($html), 'Hero pill must show the effective MLS status.');
        $this->assertNotSame('Active', $this->heroStatus($html), 'The stale stored status must not be presented as current.');
    }

    /** @test */
    public function the_mls_linked_seller_hero_badge_carries_the_effective_status_too(): void
    {
        // The status is rendered twice in the hero — as the pill and as a badge
        // in the priority-ordered badge strip. Both read one variable; if they
        // ever stop doing so, one of them goes stale silently.
        $html = $this->sellerPage($this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ])));

        $this->assertMatchesRegularExpression(
            '/<span class="sol-badge sol-badge-green"><i class="fa-solid fa-circle-check"><\/i>\s*Pending<\/span>/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<span class="sol-badge sol-badge-green"><i class="fa-solid fa-circle-check"><\/i>\s*Active<\/span>/',
            $html,
        );
    }

    /** @test */
    public function standard_status_is_preferred_over_the_local_mls_status_vocabulary(): void
    {
        // The probe found the two fields disagreeing on the same record:
        // StandardStatus 'Closed' alongside MlsStatus 'Sold'. The page must
        // report the RESO-normalised field.
        $html = $this->sellerPage($this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_SOURCE_STATUS   => 'Sold',
            Meta::META_STANDARD_STATUS => 'Closed',
        ])));

        $this->assertSame('Closed', $this->heroStatus($html));
    }

    // =====================================================================
    // B. The "Listing Status" detail row consumes the same effective status
    // =====================================================================

    /** @test */
    public function the_seller_listing_status_detail_row_consumes_the_effective_status(): void
    {
        $html = $this->sellerPage($this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ])));

        $this->assertSame('Pending', $this->listingStatusRow($html));
    }

    /** @test */
    public function the_hero_and_the_detail_row_never_disagree(): void
    {
        $html = $this->sellerPage($this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Active Under Contract',
        ])));

        $this->assertSame('Active Under Contract', $this->heroStatus($html));
        $this->assertSame($this->heroStatus($html), $this->listingStatusRow($html));
    }

    // =====================================================================
    // C. A refreshed feed status reaches a fresh render
    // =====================================================================

    /** @test */
    public function a_refreshed_mls_status_is_displayed_on_the_next_render(): void
    {
        $listing = $this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ]));

        $this->assertSame('Pending', $this->heroStatus($this->sellerPage($listing)));

        // What a sync would have written. No sync runs; the stored source
        // status is simply now a different string.
        $listing->saveMeta(Meta::META_STANDARD_STATUS, 'Closed');

        $refreshed = $this->sellerPage($listing->fresh());

        $this->assertSame('Closed', $this->heroStatus($refreshed));
        $this->assertSame('Closed', $this->listingStatusRow($refreshed));
    }

    /** @test */
    public function an_unrecognised_feed_status_is_displayed_verbatim(): void
    {
        // MlsSourceStatus returns the exact Stellar string, unrecognised ones
        // included. A status we do not have a label for is still what the MLS
        // says, and must not be swallowed into 'Active'.
        $html = $this->sellerPage($this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Temporarily Off Market',
        ])));

        $this->assertSame('Temporarily Off Market', $this->heroStatus($html));
    }

    // =====================================================================
    // D. Manual / non-MLS listings keep the behaviour they had
    // =====================================================================

    /** @test */
    public function a_manual_seller_listing_still_displays_its_stored_status(): void
    {
        $html = $this->sellerPage($this->sellerListing([
            'listing_status' => 'Active',
        ]));

        $this->assertSame('Active', $this->heroStatus($html));
        $this->assertSame('Active', $this->listingStatusRow($html));
    }

    /** @test */
    public function a_manual_seller_listing_with_a_past_expiration_date_is_unchanged(): void
    {
        // The platform's own lifecycle. `expiration_date` still governs a
        // manual listing, and this fix does not reach it: the page prints the
        // stored status exactly as it did before, not the accessor's derived
        // 'Expired'.
        $html = $this->sellerPage($this->sellerListing([
            'listing_status'  => 'Active',
            'expiration_date' => now()->subYear()->toDateString(),
        ]));

        $this->assertSame('Active', $this->heroStatus($html));
        $this->assertSame('Active', $this->listingStatusRow($html));
    }

    /** @test */
    public function an_mls_linked_listing_with_no_stored_feed_status_keeps_its_stored_status(): void
    {
        // An import that predates sync, or one whose first sync has not run.
        // Inventing a market status for it would assert something the feed has
        // not told us.
        $html = $this->sellerPage($this->sellerListing($this->linked([
            'listing_status' => 'Active',
        ])));

        $this->assertSame('Active', $this->heroStatus($html));
        $this->assertSame('Active', $this->listingStatusRow($html));
    }

    // =====================================================================
    // E. No status value means no status, never a fabricated one
    // =====================================================================

    /** @test */
    public function a_seller_listing_with_no_status_at_all_displays_none(): void
    {
        $html = $this->sellerPage($this->sellerListing([]));

        $this->assertNull($this->heroStatus($html), 'No hero status pill may be rendered.');
        $this->assertNull($this->listingStatusRow($html), 'No Listing Status row may be rendered.');
    }

    /** @test */
    public function an_mls_linked_seller_listing_with_no_status_anywhere_displays_none(): void
    {
        $html = $this->sellerPage($this->sellerListing($this->linked()));

        $this->assertNull($this->heroStatus($html));
        $this->assertNull($this->listingStatusRow($html));
    }

    /** @test */
    public function a_blank_stored_status_does_not_become_a_status(): void
    {
        $html = $this->sellerPage($this->sellerListing($this->linked([
            'listing_status'           => '   ',
            Meta::META_STANDARD_STATUS => '   ',
        ])));

        $this->assertNull($this->heroStatus($html));
        $this->assertNull($this->listingStatusRow($html));
    }

    // =====================================================================
    // F. Landlord: the same page, the same contract
    // =====================================================================
    //
    // The landlord page had HALF this defect. Its hero already asked the model
    // ($auction->status) and was the reference the seller fix was written
    // against; its "Listing Status" row still read the raw `listing_status`
    // meta value, so an MLS-linked landlord listing printed 'Pending' in the
    // pill and 'Active' in the row underneath — the seller defect, one role
    // over. The row now consumes ListingStatusDisplay; the hero is untouched.

    /** @test */
    public function the_landlord_hero_still_displays_its_effective_status(): void
    {
        // Landlord's hero already read $auction->status and was the reference
        // for this fix. Pinned so neither change can disturb it.
        $html = $this->landlordPage($this->landlordListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ])));

        $this->assertSame('Pending', $this->heroStatus($html, 'lol-hero-status'));
    }

    /** @test */
    public function an_mls_linked_landlord_detail_row_displays_the_effective_status_not_the_stored_one(): void
    {
        // The defect, stated as the owner stated it: stored 'Active', effective
        // 'Pending', and the row must say Pending.
        $html = $this->landlordPage($this->landlordListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ])));

        $this->assertSame('Pending', $this->listingStatusRow($html));
        $this->assertNotSame('Active', $this->listingStatusRow($html), 'The stale stored status must not be presented as current.');
    }

    /** @test */
    public function the_landlord_hero_and_detail_row_never_disagree(): void
    {
        // Both surfaces resolve through the model for an MLS-linked listing —
        // the hero via the accessor, the row via ListingStatusDisplay, which
        // delegates to that same accessor once the feed owns the answer. One
        // value, two pixels; they cannot drift.
        foreach (['Pending', 'Closed', 'Expired', 'Active Under Contract', 'Temporarily Off Market'] as $standard) {
            $html = $this->landlordPage($this->landlordListing($this->linked([
                'listing_status'           => 'Active',
                Meta::META_STANDARD_STATUS => $standard,
            ])));

            $this->assertSame($standard, $this->heroStatus($html, 'lol-hero-status'), "Hero for {$standard}");
            $this->assertSame($standard, $this->listingStatusRow($html), "Detail row for {$standard}");
            $this->assertSame(
                $this->heroStatus($html, 'lol-hero-status'),
                $this->listingStatusRow($html),
                "Hero and detail row disagreed on {$standard}",
            );
        }
    }

    /** @test */
    public function a_refreshed_mls_status_reaches_a_fresh_landlord_render(): void
    {
        $listing = $this->landlordListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ]));

        $first = $this->landlordPage($listing);
        $this->assertSame('Pending', $this->heroStatus($first, 'lol-hero-status'));
        $this->assertSame('Pending', $this->listingStatusRow($first));

        // What a sync would have written. No sync runs; the stored source
        // status is simply now a different string.
        $listing->saveMeta(Meta::META_STANDARD_STATUS, 'Closed');

        $refreshed = $this->landlordPage($listing->fresh());

        $this->assertSame('Closed', $this->heroStatus($refreshed, 'lol-hero-status'));
        $this->assertSame('Closed', $this->listingStatusRow($refreshed));

        // And the stale metadata was never rewritten to make that true.
        $this->assertSame('Active', $listing->fresh()->info('listing_status'));
    }

    /** @test */
    public function a_completed_landlord_transaction_still_outranks_the_feed(): void
    {
        $listing = $this->landlordListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ]));
        $listing->is_sold = true;
        $listing->save();

        $html = $this->landlordPage($listing->fresh());

        $this->assertSame('Hired Agent', $this->heroStatus($html, 'lol-hero-status'));
        $this->assertSame('Hired Agent', $this->listingStatusRow($html));
    }

    /** @test */
    public function a_manual_landlord_listing_hero_is_unchanged(): void
    {
        $html = $this->landlordPage($this->landlordListing([
            'listing_status' => 'Pending',
        ]));

        $this->assertSame('Pending', $this->heroStatus($html, 'lol-hero-status'));
    }

    /** @test */
    public function a_manual_landlord_listing_detail_row_still_displays_its_stored_status(): void
    {
        $html = $this->landlordPage($this->landlordListing([
            'listing_status' => 'Active',
        ]));

        $this->assertSame('Active', $this->heroStatus($html, 'lol-hero-status'));
        $this->assertSame('Active', $this->listingStatusRow($html));
    }

    /** @test */
    public function an_mls_linked_landlord_listing_with_no_stored_feed_status_keeps_its_stored_status(): void
    {
        $html = $this->landlordPage($this->landlordListing($this->linked([
            'listing_status' => 'Active',
        ])));

        $this->assertSame('Active', $this->heroStatus($html, 'lol-hero-status'));
        $this->assertSame('Active', $this->listingStatusRow($html));
    }

    /** @test */
    public function a_landlord_listing_with_no_status_at_all_renders_no_detail_row(): void
    {
        // The row must not fabricate one. The hero is a separate question and
        // is pinned as-found in the characterisation test below.
        $html = $this->landlordPage($this->landlordListing([]));

        $this->assertNull($this->listingStatusRow($html), 'No Listing Status row may be rendered.');
    }

    /** @test */
    public function a_blank_stored_landlord_status_does_not_become_a_detail_row(): void
    {
        $html = $this->landlordPage($this->landlordListing($this->linked([
            'listing_status'           => '   ',
            Meta::META_STANDARD_STATUS => '   ',
        ])));

        $this->assertNull($this->listingStatusRow($html));
    }

    /** @test */
    public function the_landlord_hero_still_derives_its_own_lifecycle_where_the_feed_is_silent(): void
    {
        // CHARACTERISATION, NOT ENDORSEMENT — and the exact bound of this fix.
        //
        // The hero asks a TOTAL accessor and the row asks a display contract
        // that deliberately is not total. Where the feed has said nothing, the
        // two therefore still differ, in two ways that predate this change and
        // are left exactly as found:
        //
        //   · a past `expiration_date` makes the accessor say 'Expired' while
        //     the row prints the stored value. That derivation is BidYourOffer's
        //     own lifecycle for a listing that owns it, and removing it from the
        //     hero would lose real information, not stale information;
        //   · a listing with no status at all makes the accessor fall through to
        //     its final `return 'Active'` while the row renders nothing. The row
        //     must not invent a status; whether the hero should stop doing so is
        //     a separate decision about the accessor, not about this display.
        //
        // Neither case involves an MLS status, which is what this change is
        // about. Recorded so a future move in either direction is a red build
        // rather than a silent one.
        $expired = $this->landlordPage($this->landlordListing([
            'listing_status'  => 'Active',
            'expiration_date' => now()->subYear()->toDateString(),
        ]));

        $this->assertSame('Expired', $this->heroStatus($expired, 'lol-hero-status'));
        $this->assertSame('Active', $this->listingStatusRow($expired));

        $none = $this->landlordPage($this->landlordListing([]));

        $this->assertSame('Active', $this->heroStatus($none, 'lol-hero-status'));
        $this->assertNull($this->listingStatusRow($none));
    }

    /** @test */
    public function rendering_the_landlord_page_does_not_mutate_any_stored_status(): void
    {
        $listing = $this->landlordListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_SOURCE_STATUS   => 'Sold',
            Meta::META_STANDARD_STATUS => 'Closed',
        ]));

        $before = $listing->fresh()->meta->pluck('meta_value', 'meta_key')->toArray();

        $this->landlordPage($listing);

        $after = $listing->fresh()->meta->pluck('meta_value', 'meta_key')->toArray();

        $this->assertSame($before, $after, 'The listing page must not write meta while rendering.');
        $this->assertSame('Active', $after['listing_status']);
        $this->assertSame('Closed', $after[Meta::META_STANDARD_STATUS]);
        $this->assertSame('Sold', $after[Meta::META_SOURCE_STATUS]);
    }

    // =====================================================================
    // G. Displaying a status writes nothing
    // =====================================================================

    /** @test */
    public function rendering_the_seller_page_does_not_mutate_any_stored_status(): void
    {
        $listing = $this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_SOURCE_STATUS   => 'Sold',
            Meta::META_STANDARD_STATUS => 'Closed',
        ]));

        $before = $listing->fresh()->meta->pluck('meta_value', 'meta_key')->toArray();

        $this->sellerPage($listing);

        $after = $listing->fresh()->meta->pluck('meta_value', 'meta_key')->toArray();

        $this->assertSame($before, $after, 'The listing page must not write meta while rendering.');
        $this->assertSame('Active', $after['listing_status']);
        $this->assertSame('Closed', $after[Meta::META_STANDARD_STATUS]);
        $this->assertSame('Sold', $after[Meta::META_SOURCE_STATUS]);
    }

    /** @test */
    public function the_display_helper_itself_writes_nothing(): void
    {
        $listing = $this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ]));

        $before = $listing->fresh()->meta->pluck('meta_value', 'meta_key')->toArray();

        $this->assertSame(
            'Pending',
            \App\Support\Listing\ListingStatusDisplay::for($listing->fresh()),
        );

        $this->assertSame($before, $listing->fresh()->meta->pluck('meta_value', 'meta_key')->toArray());
    }

    /** @test */
    public function a_completed_bidyouroffer_transaction_still_outranks_the_feed(): void
    {
        // is_sold records a closed deal on this platform. MlsLinkedListingStatus
        // documents that an MLS status must not reopen it, and the accessor
        // checks it first — going through the model rather than re-reading
        // StandardStatus in Blade is what preserves that ordering.
        $listing = $this->sellerListing($this->linked([
            'listing_status'           => 'Active',
            Meta::META_STANDARD_STATUS => 'Pending',
        ]));
        $listing->is_sold = true;
        $listing->save();

        $html = $this->sellerPage($listing->fresh());

        $this->assertSame('Hired Agent', $this->heroStatus($html));
        $this->assertSame('Hired Agent', $this->listingStatusRow($html));
    }
}
