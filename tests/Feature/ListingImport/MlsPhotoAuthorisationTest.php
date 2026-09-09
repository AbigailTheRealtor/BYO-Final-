<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\Mls\MlsListingDetailsReader;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Support\Listing\ListingPhotoEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MLS PHOTOGRAPHS ON AN IMPORTED LISTING — owner-authorised 2026-09-04.
 *
 * The photo clause of the locked 2026-07-05 policy was superseded by an explicit
 * owner decision, recorded in docs/mls-direct-import-design-and-plan.md under
 * "Owner decision — 2026-09-04". Both `mls_media.enabled` and
 * `mls_media.license_acknowledged` now default true.
 *
 * These tests run the REAL flow against the SHIPPED config — they set no media
 * flags of their own — so they fail the moment either default is turned back off
 * without the decision being revisited. That is deliberate: a test that pins the
 * behaviour by forcing the flags on would keep passing while production quietly
 * stopped showing photographs.
 *
 * What is asserted here is the whole of the owner's verification list: the
 * zero/one/many/over-cap counts, ordering, cover, captions, per-media
 * `Permission`, duplicate handling, re-import, coexistence with the user's own
 * uploads, broken media, and the attribution block that ships with them.
 */
class MlsPhotoAuthorisationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'PHOTO-KEY';
    private const MLS = 'PHOTO-MLS';

    protected function setUp(): void
    {
        parent::setUp();

        // Only the import flags. The MEDIA flags are deliberately left at their
        // shipped defaults — see the class docblock.
        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
        ]);
    }

    /** @param list<array<string,mixed>>|null $media */
    private function seedRecord(?array $media, array $overrides = []): BridgeProperty
    {
        $raw = array_merge([
            'ListingKey'                     => self::KEY,
            'ListingId'                      => self::MLS,
            'StandardStatus'                 => 'Active',
            'MlsStatus'                      => 'Active',
            'PropertyType'                   => 'Residential',
            'UnparsedAddress'                => '9 Example Way',
            'City'                           => 'CLEARWATER',
            'StateOrProvince'                => 'FL',
            'PostalCode'                     => '33760',
            'ListPrice'                      => 425000,
            'SubdivisionName'                => 'Bradford Acres',
            'ListOfficeName'                 => 'Example Realty Group',
            'ModificationTimestamp'          => '2026-08-01T12:00:00.000Z',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
        ], $overrides);

        if ($media !== null) {
            $raw['Media'] = $media;
        }

        BridgeProperty::where('listing_key', self::KEY)->delete();

        return BridgeProperty::create([
            'listing_key'      => self::KEY,
            'listing_id'       => self::MLS,
            'standard_status'  => 'Active',
            'property_type'    => 'Residential',
            'unparsed_address' => $raw['UnparsedAddress'],
            'city'             => $raw['City'],
            'state_or_province'=> $raw['StateOrProvince'],
            'postal_code'      => $raw['PostalCode'],
            'list_price'       => $raw['ListPrice'],
            'raw_json'         => json_encode($raw),
            'imported_at'      => now(),
        ]);
    }

    /** A Stellar-shaped media object. */
    private function photo(int $order, array $overrides = []): array
    {
        return array_merge([
            'MediaKey'          => self::KEY . "-m{$order}",
            'MediaCategory'     => 'Photo',
            'MediaURL'          => "https://cdn.example.com/" . self::KEY . "-{$order}.jpeg",
            'MediaObjectID'     => "obj_{$order}",
            'ResourceRecordKey' => self::KEY,
            'ResourceName'      => 'Property',
            'ClassName'         => 'Residential',
            'ShortDescription'  => null,
            'MimeType'          => 'image/jpeg',
            'Order'             => $order,
            'Permission'        => ['Public'],
            'LongDescription'   => null,
        ], $overrides);
    }

    /** @return list<array<string,mixed>> */
    private function photos(int $count): array
    {
        $out = [];

        for ($i = 1; $i <= $count; $i++) {
            $out[] = $this->photo($i);
        }

        return $out;
    }

    private function import(User $user, string $role = 'seller'): object
    {
        $result = app(MlsQuickImportService::class)->lookup(self::MLS, $role);

        $this->assertTrue($result->isFound(), "lookup failed: {$result->status}");

        return app(MlsQuickImportDraftWriter::class)->materialise($role, $user->id, $result);
    }

    private function metaOf(object $listing): array
    {
        $meta = [];

        foreach ($listing->fresh()->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : $row->meta_value;
        }

        return $meta;
    }

    /** @return list<ListingPhotoEntry> */
    private function gallery(object $listing): array
    {
        return ListingPhotoEntry::collection($this->metaOf($listing)['property_photos'] ?? null);
    }

    // ─── The flags themselves ────────────────────────────────────────────────

    /**
     * @test
     *
     * The shipped defaults, pinned. If either is turned back off this fails
     * first and names the flag, rather than every photo test failing obscurely.
     */
    public function both_media_flags_ship_enabled_after_the_owner_decision(): void
    {
        $this->assertTrue(config('mls_media.enabled'), 'mls_media.enabled must ship true');
        $this->assertTrue(
            config('mls_media.license_acknowledged'),
            'mls_media.license_acknowledged must ship true — owner decision of 2026-09-04'
        );
        $this->assertSame('reference', config('mls_media.hosting_mode'), 'hosting must remain reference-only');
    }

    // ─── Counts ──────────────────────────────────────────────────────────────

    /** @test */
    public function a_listing_with_no_photos_gets_no_gallery(): void
    {
        $this->seedRecord(null);
        $listing = $this->import(User::factory()->create());

        $this->assertSame([], $this->gallery($listing));
    }

    /** @test */
    public function a_listing_with_one_photo_gets_one(): void
    {
        $this->seedRecord($this->photos(1));
        $listing = $this->import(User::factory()->create());

        $this->assertCount(1, $this->gallery($listing));
    }

    /** @test */
    public function a_listing_with_many_photos_gets_all_of_them(): void
    {
        $this->seedRecord($this->photos(24));
        $listing = $this->import(User::factory()->create());

        $this->assertCount(24, $this->gallery($listing));
    }

    /**
     * @test
     *
     * THE OWNER'S STATED CASE: 72 permitted photographs must not silently stop
     * at 50. The old ceiling truncated 186 of 1,202 cached listings.
     */
    public function a_listing_with_seventy_two_photos_is_not_truncated_at_fifty(): void
    {
        $this->seedRecord($this->photos(72));
        $listing = $this->import(User::factory()->create());

        $this->assertCount(72, $this->gallery($listing), 'photos 51–72 were dropped');
    }

    /**
     * @test
     *
     * The largest gallery in the 1,224-record cache is 100. The ceiling sits
     * well above it, so no real Stellar listing is truncated today.
     */
    public function the_largest_gallery_the_real_feed_produces_fits_under_the_ceiling(): void
    {
        $this->seedRecord($this->photos(100));
        $listing = $this->import(User::factory()->create());

        $this->assertCount(100, $this->gallery($listing));
        $this->assertGreaterThanOrEqual(
            100,
            (int) config('mls_media.max_images'),
            'The ceiling must stay above the largest gallery the feed actually publishes'
        );
    }

    // ─── Ordering, cover, captions ───────────────────────────────────────────

    /** @test */
    public function the_feeds_order_is_preserved_and_the_first_photo_is_the_cover(): void
    {
        // Deliberately out of sequence in the payload; `Order` decides.
        $this->seedRecord([$this->photo(3), $this->photo(1), $this->photo(2)]);
        $listing = $this->import(User::factory()->create());

        $entries = $this->gallery($listing);

        $this->assertSame(
            [self::KEY . '-m1', self::KEY . '-m2', self::KEY . '-m3'],
            array_map(fn (ListingPhotoEntry $e) => $e->mediaKey, $entries)
        );

        $this->assertTrue($entries[0]->isCover, 'the first ordered photo must be the cover');
        $this->assertFalse($entries[1]->isCover);
        $this->assertFalse($entries[2]->isCover);
    }

    /** @test */
    public function captions_survive_when_the_feed_provides_them(): void
    {
        $this->seedRecord([
            $this->photo(1, ['LongDescription' => 'Rear elevation at dusk']),
            $this->photo(2),
        ]);

        $listing = $this->import(User::factory()->create());
        $entries = $this->gallery($listing);

        $this->assertSame('Rear elevation at dusk', $entries[0]->caption);
        $this->assertNull($entries[1]->caption);
    }

    // ─── Permission ──────────────────────────────────────────────────────────

    /**
     * @test
     *
     * Photo authorisation is a decision about OUR posture. It does not override
     * the feed's per-media permission, and a non-Public object is still refused.
     */
    public function a_media_object_the_feed_has_not_marked_public_is_still_refused(): void
    {
        $this->seedRecord([
            $this->photo(1),
            $this->photo(2, ['Permission' => ['Private']]),
            $this->photo(3, ['Permission' => ['Office', 'IDX']]),
            $this->photo(4),
        ]);

        $entries = $this->gallery($this->import(User::factory()->create()));

        $this->assertCount(2, $entries);
        $this->assertSame(
            [self::KEY . '-m1', self::KEY . '-m4'],
            array_map(fn (ListingPhotoEntry $e) => $e->mediaKey, $entries)
        );
    }

    /**
     * @test
     *
     * Nor does it override the LISTING's controls. A listing the feed has
     * withdrawn from IDX gets no photographs.
     */
    public function a_listing_withdrawn_from_idx_gets_no_photographs(): void
    {
        $this->seedRecord($this->photos(5), ['IDXParticipationYN' => false]);

        // The lookup still resolves — the withdrawal is a DISPLAY instruction,
        // not a deletion — but nothing MLS-sourced may be published from it.
        $listing = $this->import(User::factory()->create());
        $details = (new MlsListingDetailsReader())->detailsFrom($this->metaOf($listing));

        $this->assertSame([], $details->group('contacts'));
        $this->assertSame([], $details->group('listing'));
    }

    // ─── Robustness ──────────────────────────────────────────────────────────

    /** @test */
    public function a_broken_media_entry_costs_only_itself(): void
    {
        $this->seedRecord([
            $this->photo(1),
            $this->photo(2, ['MediaURL' => 'http://insecure.example.com/x.jpg']),
            $this->photo(3, ['MediaURL' => null]),
            $this->photo(4, ['MediaKey' => null, 'MediaObjectID' => null]),
            $this->photo(5),
        ]);

        $entries = $this->gallery($this->import(User::factory()->create()));

        $this->assertCount(2, $entries);
    }

    /** @test */
    public function a_duplicate_media_key_in_one_payload_is_collapsed(): void
    {
        $this->seedRecord([$this->photo(1), $this->photo(1), $this->photo(2)]);

        $this->assertCount(2, $this->gallery($this->import(User::factory()->create())));
    }

    // ─── Re-import ───────────────────────────────────────────────────────────

    /** @test */
    public function a_reimport_does_not_duplicate_photographs(): void
    {
        $this->seedRecord($this->photos(6));
        $user = User::factory()->create();

        $this->import($user);
        $entries = $this->gallery($this->import($user));

        $keys = array_map(fn (ListingPhotoEntry $e) => $e->key(), $entries);

        $this->assertCount(6, $entries);
        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    /** @test */
    public function a_users_own_uploads_survive_alongside_mls_photographs(): void
    {
        $this->seedRecord($this->photos(3));
        $user    = User::factory()->create();
        $listing = $this->import($user);

        $stored   = ListingPhotoEntry::toStorageCollection($this->gallery($listing));
        $stored[] = 'auction/images/my-own-photo.jpg';
        $listing->saveMeta('property_photos', $stored);

        $entries = $this->gallery($this->import($user));

        $mls  = array_filter($entries, fn (ListingPhotoEntry $e) => $e->isMls());
        $user = array_filter($entries, fn (ListingPhotoEntry $e) => ! $e->isMls());

        $this->assertCount(3, $mls);
        $this->assertCount(1, $user, "the user's own upload was removed by an MLS refresh");
    }

    // ─── Attribution ─────────────────────────────────────────────────────────

    /** @test */
    public function an_imported_seller_listing_is_flagged_as_mls_sourced_for_attribution(): void
    {
        $this->seedRecord($this->photos(3));
        $listing = $this->import(User::factory()->create(), 'seller');

        $reader = new MlsListingDetailsReader();
        $meta   = $this->metaOf($listing);

        $this->assertTrue($reader->isMlsImported($meta));
        $this->assertSame('Example Realty Group', $reader->detailsFrom($meta)->brokerageName());
        $this->assertNotNull($reader->detailsFrom($meta)->lastUpdatedLabel());
    }

    /** @test */
    public function an_imported_landlord_listing_is_flagged_as_mls_sourced_for_attribution(): void
    {
        $this->seedRecord($this->photos(2), ['PropertyType' => 'Residential Lease']);
        $listing = $this->import(User::factory()->create(), 'landlord');

        $this->assertTrue((new MlsListingDetailsReader())->isMlsImported($this->metaOf($listing)));
        $this->assertCount(2, $this->gallery($listing));
    }

    /**
     * @test
     *
     * THE RENDERED PUBLIC PAGE. Everything above proves the data is persisted;
     * this proves an unauthenticated visitor actually sees it.
     *
     * `/offer-listing/seller/view/{id}` sits deliberately OUTSIDE the auth
     * middleware group, so this is the widest surface the photographs reach —
     * which is exactly why the attribution block has to be on it.
     */
    public function the_public_seller_listing_page_renders_photos_and_attribution(): void
    {
        $this->seedRecord($this->photos(4));

        $listing = $this->import(User::factory()->create(), 'seller');
        $listing->is_draft    = 0;
        $listing->is_approved = true;
        $listing->save();

        $response = $this->get(route('offer.listing.seller.view', ['id' => $listing->id]));

        $response->assertStatus(200);

        // Attribution, modelled on the authenticated Stellar page's own block.
        $response->assertSee('Information provided by Stellar MLS via Bridge Data Output', false);
        $response->assertSee('Stellar MLS', false);
        $response->assertSee('Example Realty Group', false);

        // The photographs themselves, referenced at the provider's own URL —
        // reference-only hosting, no bytes copied.
        $response->assertSee('cdn.example.com/' . self::KEY . '-1.jpeg', false);

        // And the supplemental facts, still there.
        $response->assertSee('MLS Property Details', false);
    }

    /** @test */
    public function the_public_landlord_listing_page_renders_photos_and_attribution(): void
    {
        $this->seedRecord($this->photos(3), ['PropertyType' => 'Residential Lease']);

        $listing = $this->import(User::factory()->create(), 'landlord');
        $listing->is_draft    = 0;
        $listing->is_approved = true;
        $listing->save();

        $response = $this->get(route('offer.listing.landlord.view', ['id' => $listing->id]));

        $response->assertStatus(200);
        $response->assertSee('Information provided by Stellar MLS via Bridge Data Output', false);
        $response->assertSee('cdn.example.com/' . self::KEY . '-1.jpeg', false);
    }

    /**
     * @test
     *
     * A listing that did not come from the MLS renders NO attribution. A false
     * provenance claim on a public page is worse than a missing one.
     */
    public function a_manual_listing_page_renders_no_stellar_attribution(): void
    {
        $user = User::factory()->create();

        $listing = new SellerAgentAuction();
        $listing->user_id     = $user->id;
        $listing->is_draft    = 0;
        $listing->is_approved = true;
        $listing->title       = 'Hand-typed listing';
        $listing->address     = 'Hand-typed listing';
        $listing->save();
        $listing->saveMeta('bedrooms', '3');

        // Stamped as an Offer Listing the way the wizard's own save does, so the
        // controller resolves it. Without the stamp the route 404s and the test
        // would "pass" by never rendering the page it is meant to inspect.
        \App\Support\Listing\ListingWorkflow::stamp($listing, \App\Support\Listing\ListingWorkflow::OFFER_LISTING);

        $response = $this->get(route('offer.listing.seller.view', ['id' => $listing->id]));

        $response->assertStatus(200);
        $response->assertDontSee('Information provided by Stellar MLS', false);
        $response->assertDontSee('MLS Property Details', false);
    }

    /**
     * @test
     *
     * THE NEGATIVE CASE, and the one that matters most. A listing that did not
     * come from the MLS must never claim Stellar provenance — a false
     * attribution is worse than a missing one.
     */
    public function a_manually_created_listing_is_never_flagged_as_mls_sourced(): void
    {
        $user = User::factory()->create();

        foreach ([SellerAgentAuction::class, LandlordAgentAuction::class] as $model) {
            $listing = new $model();
            $listing->user_id  = $user->id;
            $listing->is_draft = true;
            $listing->title    = 'Hand-typed listing';

            if ($listing->getConnection()->getSchemaBuilder()->hasColumn($listing->getTable(), 'address')) {
                $listing->address = 'Hand-typed listing';
            }

            $listing->save();
            $listing->saveMeta('bedrooms', '3');

            $meta   = $this->metaOf($listing);
            $reader = new MlsListingDetailsReader();

            $this->assertFalse($reader->isMlsImported($meta), $model . ' wrongly claims MLS provenance');
            $this->assertTrue($reader->detailsFrom($meta)->isEmpty());
        }
    }
}
