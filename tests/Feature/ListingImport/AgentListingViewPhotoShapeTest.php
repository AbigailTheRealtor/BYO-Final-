<?php

namespace Tests\Feature\ListingImport;

use App\Models\OfferAuction;
use App\Models\OfferAuctionMeta;
use App\Models\User;
use App\Support\Storage\ListingMediaUrl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The agent-facing listing view, against both photo shapes.
 *
 * WHY THIS PAGE NEEDED ITS OWN FILE RATHER THAN A ROW IN THE EXISTING ONE
 * ----------------------------------------------------------------------
 * It does not share the public pages' URL convention. Those store a bare
 * filename and prefix `auction/images/`; this one passes the stored value to the
 * resolver unmodified, because several older controllers write a complete
 * `auction/images/…` path into the same meta. Both conventions are live in the
 * data. Prefixing here would yield `auction/images/auction/images/…` and break
 * every photograph an agent can currently see, so the FIRST test below pins the
 * existing convention exactly — it is the thing this change must not move.
 *
 * Everything else about MLS media is asserted to behave identically to the
 * Seller and Landlord pages, because it goes through the same component.
 *
 * The media flags are OFF unless a test turns them on. That is the shipped
 * configuration and the default this file is written against.
 */
class AgentListingViewPhotoShapeTest extends TestCase
{
    use DatabaseTransactions;

    private const PROVIDER_URL = 'https://media.example-mls.test/agent-photo-1.jpg';

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create(['user_type' => 'seller']);

        config([
            // The route is gated by the offer-playoff Gate; open it for this user
            // so these tests exercise the photo path rather than the rollout flag.
            'offer.playoff_access.allowed_user_ids' => '*',

            // The shipped media defaults, restated.
            'mls_media.enabled'              => false,
            'mls_media.license_acknowledged' => false,
            'mls_media.hosting_mode'         => 'reference',
            'mls_media.roles'                => ['seller', 'landlord'],
        ]);
    }

    private function allowMedia(): void
    {
        config([
            'mls_media.enabled'              => true,
            'mls_media.license_acknowledged' => true,
            'mls_media.hosting_mode'         => 'reference',
        ]);
    }

    private function listing(array $photos, ?User $owner = null, string $role = 'seller'): OfferAuction
    {
        $auction = OfferAuction::create([
            'user_id'     => ($owner ?? $this->agent)->id,
            'title'       => 'Agent Photo Shape Listing',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        foreach (['listing_role' => $role, 'property_photos' => json_encode($photos)] as $key => $value) {
            OfferAuctionMeta::create([
                'offer_auction_id' => $auction->id,
                'meta_key'         => $key,
                'meta_value'       => $value,
            ]);
        }

        return $auction;
    }

    private function mlsEntry(array $overrides = []): array
    {
        return array_merge([
            'source'      => 'mls',
            'media_key'   => 'AGENT-MK-1',
            'url'         => self::PROVIDER_URL,
            'provider'    => 'bridge',
            'listing_key' => 'AGENT-LK-1',
            'sequence'    => 0,
        ], $overrides);
    }

    /**
     * Render the page, failing on any array-to-string conversion.
     *
     * That conversion is a warning, not an exception: a page emitting it still
     * returns 200. Catching it has to be explicit or the defect passes silently.
     */
    private function render(OfferAuction $auction, ?User $as = null): string
    {
        $raised = [];
        set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });

        try {
            $response = $this->actingAs($as ?? $this->agent)
                ->get(route('offer.listing.view', $auction->id));
        } finally {
            restore_error_handler();
        }

        $response->assertStatus(200);

        $this->assertSame(
            [],
            array_values(array_filter($raised, static fn ($m) => str_contains($m, 'Array to string conversion'))),
            'the agent listing view must not convert an array to a string',
        );

        return $response->getContent();
    }

    // =====================================================================
    // 1 — the existing URL convention is preserved exactly
    // =====================================================================

    public function test_a_user_upload_renders_through_this_pages_existing_convention(): void
    {
        // A complete relative path, as several older controllers write it.
        $auction = $this->listing(['auction/images/legacy-agent.jpg']);

        $html = $this->render($auction);

        // Pinned against the resolver call this page has always made — the stored
        // value, unmodified. Not a hand-written string, so the assertion is tied
        // to the real URL builder.
        $this->assertStringContainsString(
            e(ListingMediaUrl::get('auction/images/legacy-agent.jpg')),
            $html,
        );

        // The public pages' convention must NOT have been applied on top.
        $this->assertStringNotContainsString('auction/images/auction/images/', $html);
    }

    public function test_a_bare_filename_still_resolves_the_way_it_always_did_here(): void
    {
        // This page has always treated the stored value as a whole key, including
        // when that value is a bare filename. Whether that is the right reading of
        // such a row is a pre-existing question about this surface; what matters
        // for this change is that the answer has not moved.
        $auction = $this->listing(['bare-name.jpg']);

        $html = $this->render($auction);

        $this->assertStringContainsString(e(ListingMediaUrl::get('bare-name.jpg')), $html);
    }

    public function test_a_user_upload_promoted_to_cover_keeps_the_same_convention(): void
    {
        $auction = $this->listing([['filename' => 'auction/images/promoted.jpg', 'is_cover' => true]]);

        $html = $this->render($auction);

        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/promoted.jpg')), $html);
    }

    // =====================================================================
    // 2 / 3 / 6 — the gates
    // =====================================================================

    public function test_the_master_flag_alone_is_insufficient(): void
    {
        config(['mls_media.enabled' => true]); // licence still unacknowledged

        $html = $this->render($this->listing([$this->mlsEntry()]));

        $this->assertStringNotContainsString('media.example-mls.test', $html);
    }

    public function test_the_licence_acknowledgement_alone_is_insufficient(): void
    {
        config(['mls_media.license_acknowledged' => true]); // master flag still off

        $html = $this->render($this->listing([$this->mlsEntry()]));

        $this->assertStringNotContainsString('media.example-mls.test', $html);
    }

    public function test_an_unimplemented_hosting_mode_renders_no_mls_media(): void
    {
        $this->allowMedia();
        config(['mls_media.hosting_mode' => 'cached']);

        $html = $this->render($this->listing([$this->mlsEntry()]));

        $this->assertStringNotContainsString('media.example-mls.test', $html);
    }

    public function test_an_mls_only_gallery_renders_no_mls_image_at_the_shipped_defaults(): void
    {
        $auction = $this->listing([
            $this->mlsEntry(),
            $this->mlsEntry(['media_key' => 'AGENT-MK-2', 'url' => 'https://media.example-mls.test/2.jpg']),
        ]);

        $html = $this->render($auction);

        $this->assertStringNotContainsString('media.example-mls.test', $html);
    }

    public function test_a_user_upload_is_unaffected_when_media_is_off(): void
    {
        $auction = $this->listing([$this->mlsEntry(), 'auction/images/mine.jpg']);

        $html = $this->render($auction);

        $this->assertStringNotContainsString('media.example-mls.test', $html);
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/mine.jpg')), $html);
    }

    /**
     * The gate is asked with the STORED role, not the client-supplied one.
     *
     * `?role=` is honoured for choosing an edit link. It must not be able to move
     * a licensing decision, so the media gate reads the listing's own recorded
     * role and fails closed when there isn't one.
     */
    public function test_a_role_query_parameter_cannot_turn_mls_media_on(): void
    {
        $this->allowMedia();

        // Recorded as a role the media feature is not built for.
        $auction = $this->listing([$this->mlsEntry()], role: 'tenant');

        $response = $this->actingAs($this->agent)
            ->get(route('offer.listing.view', $auction->id) . '?role=seller');

        $response->assertStatus(200);
        $response->assertDontSee('media.example-mls.test', false);
    }

    // =====================================================================
    // 4 / 5 — a permitted MLS entry resolves, and never locally
    // =====================================================================

    public function test_a_valid_mls_entry_resolves_when_both_gates_are_open(): void
    {
        $this->allowMedia();

        $html = $this->render($this->listing([$this->mlsEntry()]));

        $this->assertStringContainsString(e(self::PROVIDER_URL), $html);
    }

    public function test_a_permitted_mls_entry_never_becomes_a_local_path(): void
    {
        $this->allowMedia();

        $html = $this->render($this->listing([$this->mlsEntry()]));

        $this->assertStringNotContainsString('auction/images/agent-photo-1.jpg', $html);
        $this->assertStringNotContainsString('auction/images/AGENT-MK-1', $html);
        $this->assertStringNotContainsString('auction/images/Array', $html);
        $this->assertStringNotContainsString('storage/' . self::PROVIDER_URL, $html);
    }

    public function test_the_landlord_role_is_equally_served(): void
    {
        $this->allowMedia();

        $html = $this->render($this->listing([$this->mlsEntry()], role: 'landlord'));

        $this->assertStringContainsString(e(self::PROVIDER_URL), $html);
    }

    // =====================================================================
    // 7 — invalid entries fail closed
    // =====================================================================

    public function test_invalid_entries_are_skipped_without_a_diagnostic(): void
    {
        $this->allowMedia();

        $auction = $this->listing([
            ['source' => 'mls', 'media_key' => 'NO-URL'],
            ['source' => 'mls', 'url' => 'http://insecure.test/a.jpg'],
            ['source' => 'mls', 'media_key' => 'BAD', 'url' => 'data:image/png;base64,AAAA'],
            ['filename' => null],
            ['nested' => ['deep' => 'value']],
            'auction/images/survivor.jpg',
        ]);

        $html = $this->render($auction);

        $this->assertStringNotContainsString('auction/images/Array', $html);
        $this->assertStringNotContainsString('insecure.test', $html);
        $this->assertStringNotContainsString('data:image/png', $html);
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/survivor.jpg')), $html);
    }

    // =====================================================================
    // 8 — mixed galleries keep their order
    // =====================================================================

    public function test_a_mixed_gallery_retains_stored_order(): void
    {
        $this->allowMedia();

        $auction = $this->listing([
            $this->mlsEntry(['media_key' => 'M1', 'url' => 'https://media.example-mls.test/1.jpg']),
            'auction/images/user-a.jpg',
            $this->mlsEntry(['media_key' => 'M2', 'url' => 'https://media.example-mls.test/2.jpg']),
            'auction/images/user-b.jpg',
        ]);

        $html = $this->render($auction);

        $positions = [
            'mls-1'  => strpos($html, e('https://media.example-mls.test/1.jpg')),
            'user-a' => strpos($html, e(ListingMediaUrl::get('auction/images/user-a.jpg'))),
            'mls-2'  => strpos($html, e('https://media.example-mls.test/2.jpg')),
            'user-b' => strpos($html, e(ListingMediaUrl::get('auction/images/user-b.jpg'))),
        ];

        foreach ($positions as $label => $at) {
            $this->assertIsInt($at, "{$label} must be rendered");
        }

        $this->assertTrue(
            $positions['mls-1'] < $positions['user-a']
            && $positions['user-a'] < $positions['mls-2']
            && $positions['mls-2'] < $positions['user-b'],
            'stored order is gallery order, and must not be regrouped by source',
        );
    }

    // =====================================================================
    // 9 — authorization behaviour is untouched
    // =====================================================================

    public function test_another_users_listing_is_still_not_viewable(): void
    {
        $other   = User::factory()->create();
        $auction = $this->listing(['auction/images/theirs.jpg'], owner: $other);

        $this->actingAs($this->agent)
            ->get(route('offer.listing.view', $auction->id))
            ->assertStatus(404);
    }

    public function test_an_admin_retains_oversight_access(): void
    {
        $other   = User::factory()->create();
        $admin   = User::factory()->create(['user_type' => 'admin']);
        $auction = $this->listing(['auction/images/theirs.jpg'], owner: $other);

        $this->actingAs($admin)
            ->get(route('offer.listing.view', $auction->id))
            ->assertStatus(200);
    }

    public function test_the_playoff_gate_still_refuses_a_user_outside_the_allow_list(): void
    {
        config(['offer.playoff_access.allowed_user_ids' => []]);

        $auction = $this->listing(['auction/images/mine.jpg']);

        $this->actingAs($this->agent)
            ->get(route('offer.listing.view', $auction->id))
            ->assertStatus(403);
    }

    public function test_the_route_still_requires_authentication(): void
    {
        $auction = $this->listing(['auction/images/mine.jpg']);

        $response = $this->get(route('offer.listing.view', $auction->id));

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
    }

    public function test_a_missing_listing_is_still_a_404(): void
    {
        $this->actingAs($this->agent)
            ->get(route('offer.listing.view', 99999999))
            ->assertStatus(404);
    }
}
