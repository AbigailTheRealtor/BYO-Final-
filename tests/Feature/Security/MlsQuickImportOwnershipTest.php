<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Support\Listing\ListingPhotoEntry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Livewire\Testing\TestableLivewire;
use Tests\TestCase;

/**
 * Ownership and IDOR surface of the MLS quick-import flow.
 *
 * WHY THIS FILE EXISTS AT ALL
 * ---------------------------
 * An MLS number is public. Anyone can type any listing's number, which makes
 * "find the BidYourOffer listing for this MLS number" the most obvious
 * ownership-takeover shape this feature could have had: import 123 Main Street,
 * land on the listing somebody else already created for it, and start writing.
 *
 * These tests assert that no such path exists — every resolution is scoped to
 * the authenticated user first, and the MLS key only narrows within what that
 * user already owns.
 *
 * The tampering tests mirror OfferListingPhotoOrderOwnershipTest's technique:
 * `$listingId` is a public Livewire property, and Livewire 2 applies the
 * client's syncInput updates AFTER the hydration hooks run — so a value changed
 * between requests reaches an action having already passed hydrate(). That is
 * exactly why every action re-resolves owner-scoped, and it is what these tests
 * exercise.
 */
class MlsQuickImportOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private const MLS = 'PHPUNIT-SEC-A4567890';
    private const KEY = 'PHPUNIT-SEC-STELLAR-KEY-1';

    private User $owner;
    private User $attacker;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => true,
            'mls_media.license_acknowledged'         => true,
            'mls_media.hosting_mode'                 => 'reference',
            'mls_media.roles'                        => ['seller', 'landlord'],
            'mls_media.max_images'                   => 50,
            'bridge.dataset'                         => 'phpunit_dataset',
            'bridge.token'                           => 'phpunit-token',
            'bya_beta.bidding_period_enabled'        => true,
        ]);

        $this->owner    = User::factory()->create(['user_type' => 'seller']);
        $this->attacker = User::factory()->create(['user_type' => 'seller']);

        $this->seedBridgeProperty();
    }

    private function seedBridgeProperty(int $photoCount = 3, ?string $key = null, ?string $mls = null): void
    {
        $key ??= self::KEY;
        $mls ??= self::MLS;

        $media = [];
        for ($i = 0; $i < $photoCount; $i++) {
            $media[] = [
                'MediaKey'      => "{$key}-m{$i}",
                'MediaURL'      => "https://cdn.example.com/{$key}-{$i}.jpg",
                'Order'         => $i,
                'MediaCategory' => 'Photo',
            ];
        }

        BridgeProperty::create([
            'listing_key'             => $key,
            'listing_id'              => $mls,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 525000,
            'unparsed_address'        => '123 Main Street, Tampa, FL 33601',
            'city'                    => 'Tampa',
            'state_or_province'       => 'FL',
            'postal_code'             => '33601',
            'bedrooms_total'          => 4,
            'bathrooms_total_integer' => 3,
            'living_area'             => 2450,
            'raw_json'                => json_encode([
                'ListingKey' => $key,
                'ListingId'  => $mls,
                'Media'      => $media,
            ]),
            'imported_at'             => now(),
        ]);
    }

    /** Import as $user and return the resulting owned listing. */
    private function importAs(User $user, string $componentClass = SellerMlsQuickImport::class, string $mls = self::MLS): object
    {
        $component = Livewire::actingAs($user)
            ->test($componentClass)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty');

        $modelClass = $componentClass === SellerMlsQuickImport::class
            ? SellerAgentAuction::class
            : LandlordAgentAuction::class;

        return $modelClass::findOrFail($component->get('listingId'));
    }

    /**
     * Livewire's testing harness surfaces an abort() as an HttpException rather than a
     * 403 response. Both are a refusal; the assertions that matter are the state ones
     * that follow, so the throw is caught and the write is checked either way.
     */
    private function attempt(TestableLivewire $component, callable $action): void
    {
        try {
            $action($component);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode(), 'Refused, but not with a 403');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Also a refusal.
        }
    }

    // ─── Ownership takeover via the MLS number ───────────────────────────────

    /**
     * @test
     *
     * The headline threat. A second user importing the same public MLS number
     * must get their OWN listing, never a handle on the first user's.
     */
    public function a_second_user_importing_the_same_mls_number_gets_their_own_listing(): void
    {
        $ownersListing    = $this->importAs($this->owner);
        $attackersListing = $this->importAs($this->attacker);

        $this->assertNotSame(
            $ownersListing->id,
            $attackersListing->id,
            'A second importer was handed the first user\'s listing'
        );
        $this->assertSame($this->owner->id, $ownersListing->fresh()->user_id);
        $this->assertSame($this->attacker->id, $attackersListing->user_id);
    }

    /** @test */
    public function importing_an_mls_number_never_reassigns_an_existing_listings_owner(): void
    {
        $ownersListing = $this->importAs($this->owner);

        $this->importAs($this->attacker);

        $this->assertSame(
            $this->owner->id,
            $ownersListing->fresh()->user_id,
            'An import changed the owner of an existing listing'
        );
    }

    /** @test */
    public function a_second_importer_cannot_see_the_first_users_photos(): void
    {
        $ownersListing = $this->importAs($this->owner);
        $ownersListing->saveMeta('property_photos', ['owner-private-photo.jpg']);

        $attackersListing = $this->importAs($this->attacker);

        $keys = array_map(
            fn ($e) => $e->key(),
            ListingPhotoEntry::collection($attackersListing->info('property_photos'))
        );

        $this->assertNotContains('owner-private-photo.jpg', $keys);
    }

    /**
     * @test
     *
     * Drafts only. A published listing must never be resumed and rewritten
     * because somebody typed its MLS number.
     */
    public function a_published_listing_is_never_resumed_by_a_re_import(): void
    {
        $listing = $this->importAs($this->owner);
        $listing->update(['is_draft' => 0]);

        $second = $this->importAs($this->owner);

        $this->assertNotSame($listing->id, $second->id, 'A re-import reopened a published listing');
        $this->assertFalse((bool) $listing->fresh()->is_draft, 'The published listing was reverted to a draft');
    }

    /** @test */
    public function the_owned_draft_finder_never_returns_another_users_listing(): void
    {
        $ownersListing = $this->importAs($this->owner);
        $writer        = app(MlsQuickImportDraftWriter::class);

        $this->assertNotNull($writer->findOwnedDraft('seller', $this->owner->id, self::KEY));
        $this->assertNull(
            $writer->findOwnedDraft('seller', $this->attacker->id, self::KEY),
            'A foreign user resolved another user\'s draft by MLS key'
        );
        $this->assertSame($ownersListing->id, $writer->findOwnedDraft('seller', $this->owner->id, self::KEY)->id);
    }

    // ─── Client id tampering ─────────────────────────────────────────────────

    /** @test */
    public function a_tampered_listing_id_cannot_reach_the_review_step(): void
    {
        $victim = $this->importAs($this->owner);

        $component = Livewire::actingAs($this->attacker)->test(SellerMlsQuickImport::class);

        $this->attempt($component, function ($c) use ($victim) {
            $c->set('listingId', $victim->id)
                ->set('terms', ['maximum_budget' => '1'])
                ->set('multiTerms', ['offered_financing' => ['Cash']])
                ->call('continueToReview');
        });

        $this->assertSame(
            '525000',
            (string) ($victim->fresh()->get->maximum_budget ?? '525000'),
            'A tampered listingId wrote transaction terms onto a foreign listing'
        );
    }

    /** @test */
    public function a_tampered_listing_id_cannot_publish_a_foreign_listing(): void
    {
        $victim = $this->importAs($this->owner);

        $component = Livewire::actingAs($this->attacker)->test(SellerMlsQuickImport::class);

        $this->attempt($component, function ($c) use ($victim) {
            $c->set('listingId', $victim->id)
                ->set('step', 'review')
                ->set('terms', ['maximum_budget' => '1'])
                ->set('multiTerms', ['offered_financing' => ['Cash']])
                ->call('publish');
        });

        $this->assertTrue(
            (bool) $victim->fresh()->is_draft,
            'A tampered listingId published a foreign listing'
        );
    }

    /** @test */
    public function a_tampered_listing_id_cannot_change_a_foreign_listings_cover(): void
    {
        $victim = $this->importAs($this->owner);

        $before = ListingPhotoEntry::collection($victim->info('property_photos'));
        $this->assertTrue($before[0]->isCover, 'precondition: the first photo is the cover');

        $component = Livewire::actingAs($this->attacker)->test(SellerMlsQuickImport::class);

        $this->attempt($component, function ($c) use ($victim) {
            $c->set('listingId', $victim->id)
                ->call('setCoverPhoto', 'mls:' . self::KEY . '-m2');
        });

        $after = ListingPhotoEntry::collection($victim->fresh()->info('property_photos'));

        $this->assertTrue($after[0]->isCover, 'A foreign user moved another listing\'s cover photo');
        $this->assertFalse($after[2]->isCover);
    }

    /** @test */
    public function a_tampered_listing_id_cannot_reorder_a_foreign_listings_photos(): void
    {
        $victim = $this->importAs($this->owner);

        $before = array_map(fn ($e) => $e->key(), ListingPhotoEntry::collection($victim->info('property_photos')));

        $component = Livewire::actingAs($this->attacker)->test(SellerMlsQuickImport::class);

        $this->attempt($component, function ($c) use ($victim) {
            $c->set('listingId', $victim->id)
                ->call('reorderGallery', [
                    'mls:' . self::KEY . '-m2',
                    'mls:' . self::KEY . '-m1',
                    'mls:' . self::KEY . '-m0',
                ]);
        });

        $after = array_map(fn ($e) => $e->key(), ListingPhotoEntry::collection($victim->fresh()->info('property_photos')));

        $this->assertSame($before, $after, 'A foreign user reordered another listing\'s photos');
    }

    /** @test */
    public function a_tampered_listing_id_cannot_delete_a_foreign_listings_photos(): void
    {
        $victim = $this->importAs($this->owner);

        $countBefore = count(ListingPhotoEntry::collection($victim->info('property_photos')));

        $component = Livewire::actingAs($this->attacker)->test(SellerMlsQuickImport::class);

        // The flow exposes no delete, and a reorder that omits entries must not
        // become one. Both halves of that are asserted.
        $this->attempt($component, function ($c) use ($victim) {
            $c->set('listingId', $victim->id)->call('reorderGallery', []);
        });

        $this->assertCount(
            $countBefore,
            ListingPhotoEntry::collection($victim->fresh()->info('property_photos')),
            'A foreign reorder payload removed photos'
        );
    }

    /** @test */
    public function a_landlord_listing_is_equally_protected(): void
    {
        $this->seedBridgeProperty(3, 'PHPUNIT-SEC-LL-KEY', 'PHPUNIT-SEC-LL-MLS');

        $victim = $this->importAs($this->owner, LandlordMlsQuickImport::class, 'PHPUNIT-SEC-LL-MLS');

        $before = array_map(fn ($e) => $e->key(), ListingPhotoEntry::collection($victim->info('property_photos')));

        $component = Livewire::actingAs($this->attacker)->test(LandlordMlsQuickImport::class);

        $this->attempt($component, function ($c) use ($victim) {
            $c->set('listingId', $victim->id)->call('setCoverPhoto', 'mls:PHPUNIT-SEC-LL-KEY-m2');
        });

        $this->assertSame(
            $before,
            array_map(fn ($e) => $e->key(), ListingPhotoEntry::collection($victim->fresh()->info('property_photos'))),
        );
        $this->assertSame($this->owner->id, $victim->fresh()->user_id);
    }

    // ─── Cross-listing media attachment ──────────────────────────────────────

    /**
     * @test
     *
     * A selector is an input, not an authority. Naming media that belongs to a
     * different listing must not attach it — the owned collection decides
     * membership, and a non-member is refused rather than added.
     */
    public function media_belonging_to_another_listing_cannot_be_attached(): void
    {
        $this->seedBridgeProperty(2, 'PHPUNIT-SEC-OTHER-KEY', 'PHPUNIT-SEC-OTHER-MLS');

        $mine = $this->importAs($this->attacker);

        $before = array_map(fn ($e) => $e->key(), ListingPhotoEntry::collection($mine->info('property_photos')));

        Livewire::actingAs($this->attacker)
            ->test(SellerMlsQuickImport::class)
            ->set('listingId', $mine->id)
            ->call('setCoverPhoto', 'mls:PHPUNIT-SEC-OTHER-KEY-m0')
            ->call('reorderGallery', ['mls:PHPUNIT-SEC-OTHER-KEY-m0', 'mls:PHPUNIT-SEC-OTHER-KEY-m1']);

        $after = ListingPhotoEntry::collection($mine->fresh()->info('property_photos'));

        $this->assertSame($before, array_map(fn ($e) => $e->key(), $after), 'Foreign media entered the gallery');

        // …and the cover did not move to something that is not a member.
        $this->assertTrue($after[0]->isCover);
    }

    /** @test */
    public function a_cover_selector_naming_a_local_file_that_is_not_a_member_is_refused(): void
    {
        $mine = $this->importAs($this->attacker);

        Livewire::actingAs($this->attacker)
            ->test(SellerMlsQuickImport::class)
            ->set('listingId', $mine->id)
            ->call('setCoverPhoto', '../../../etc/passwd')
            ->call('setCoverPhoto', 'someone-elses-upload.jpg');

        $after = ListingPhotoEntry::collection($mine->fresh()->info('property_photos'));

        $this->assertCount(3, $after);
        $this->assertTrue($after[0]->isCover, 'A non-member selector moved the cover');
    }

    // ─── Unauthenticated access ──────────────────────────────────────────────

    /** @test */
    public function the_quick_import_routes_require_authentication(): void
    {
        $this->get(route('offer.listing.seller.quick-import'))->assertRedirect();
        $this->get(route('offer.listing.landlord.quick-import'))->assertRedirect();
    }
}
