<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\Mls\MlsListingDetailsReader;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Support\Listing\ListingPhotoEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RE-IMPORT AND REFRESH.
 *
 * Importing the same MLS listing twice must be boring. No duplicate metadata,
 * no duplicate photographs, no duplicate agent block, and — the part that
 * matters most — nothing the user typed lost along the way.
 *
 * THE PRECEDENCE RULE, STATED ONCE.
 * Where an MLS value and a later user edit disagree:
 *
 *   · EDITABLE FIELDS — the user wins. A re-import never overwrites a populated
 *     Create Offer field. Somebody who corrected a bedroom count the feed got
 *     wrong must not have their correction reverted by pressing refresh, and
 *     that field is theirs to own.
 *
 *   · SUPPLEMENTAL MLS DETAILS — the feed wins, wholesale. That blob is derived
 *     entirely from the MLS and contains nothing a user authored, so a refresh
 *     replaces it completely. A merge would leave rows for facts the MLS has
 *     since retracted, and a listing asserting a retracted fact is worse than
 *     one missing it.
 *
 *   · PHOTOGRAPHS — the feed owns MLS-sourced entries; the user owns their own
 *     uploads, their cover choice, and their ordering. See MlsListingGallerySync.
 *
 * Neither direction is silent, and neither is destructive to the other's data.
 */
class MlsReimportBehaviourTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'REIMPORT-KEY';
    private const MLS = 'REIMPORT-MLS';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => true,
            'mls_media.license_acknowledged'         => true,
            'mls_media.roles'                        => ['seller', 'landlord'],
        ]);
    }

    private function seedRecord(array $overrides = [], int $photos = 3): BridgeProperty
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
            'BedroomsTotal'                  => 3,
            'SubdivisionName'                => 'Bradford Acres',
            'LaundryFeatures'                => ['Laundry Closet'],
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
        ], $overrides);

        $media = [];
        for ($i = 1; $i <= $photos; $i++) {
            $media[] = [
                'MediaKey'      => self::KEY . "-m{$i}",
                'MediaURL'      => "https://cdn.example.com/" . self::KEY . "-{$i}.jpg",
                'Order'         => $i,
                'MediaCategory' => 'Photo',
                'Permission'    => ['Public'],
            ];
        }
        $raw['Media'] = $media;

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
            'bedrooms_total'   => $raw['BedroomsTotal'] ?? null,
            'raw_json'         => json_encode($raw),
            'imported_at'      => now(),
        ]);
    }

    private function import(User $user): SellerAgentAuction
    {
        $result = app(MlsQuickImportService::class)->lookup(self::MLS, 'seller');

        $this->assertTrue($result->isFound());

        return app(MlsQuickImportDraftWriter::class)->materialise('seller', $user->id, $result);
    }

    private function metaOf(SellerAgentAuction $listing): array
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

    /** @test */
    public function a_second_import_reuses_the_same_draft(): void
    {
        $this->seedRecord();
        $user = User::factory()->create();

        $first  = $this->import($user);
        $second = $this->import($user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SellerAgentAuction::where('user_id', $user->id)->count());
    }

    /** @test */
    public function a_second_import_creates_no_duplicate_metadata_rows(): void
    {
        $this->seedRecord();
        $user = User::factory()->create();

        $this->import($user);
        $listing = $this->import($user);

        $keys = $listing->fresh()->meta->pluck('meta_key')->all();

        $this->assertSame(
            count($keys),
            count(array_unique($keys)),
            'A re-import duplicated meta rows: ' . implode(', ', array_diff_assoc($keys, array_unique($keys)))
        );
    }

    /** @test */
    public function a_second_import_creates_no_duplicate_photos_or_agent_sections(): void
    {
        $this->seedRecord();
        $user = User::factory()->create();

        $this->import($user);
        $listing = $this->import($user);
        $meta    = $this->metaOf($listing);

        $entries = ListingPhotoEntry::collection($meta['property_photos'] ?? null);
        $keys    = array_map(fn (ListingPhotoEntry $e) => $e->key(), $entries);

        $this->assertCount(3, $entries);
        $this->assertSame(count($keys), count(array_unique($keys)), 'A re-import duplicated photos');

        $details = (new MlsListingDetailsReader())->detailsFrom($meta);
        $titles  = array_map(fn (array $s) => $s['title'], $details->sections);

        $this->assertSame(count($titles), count(array_unique($titles)), 'A re-import duplicated a section');
    }

    /**
     * @test
     *
     * The whole blob is replaced, so a fact the MLS has RETRACTED disappears
     * rather than lingering. A merge would leave the listing asserting something
     * the feed no longer says.
     */
    public function refreshed_mls_details_replace_the_previous_payload(): void
    {
        $this->seedRecord();
        $user    = User::factory()->create();
        $listing = $this->import($user);

        $before = (new MlsListingDetailsReader())->detailsFrom($this->metaOf($listing));
        $this->assertStringContainsString('Bradford Acres', json_encode($before->toArray()));

        // The feed drops the subdivision and adds a community feature.
        $this->seedRecord(['SubdivisionName' => null, 'CommunityFeatures' => ['Gated']]);

        $listing = $this->import($user);
        $after   = (new MlsListingDetailsReader())->detailsFrom($this->metaOf($listing));

        $this->assertStringNotContainsString('Bradford Acres', json_encode($after->toArray()));
        $this->assertStringContainsString('Gated', json_encode($after->toArray()));
    }

    /**
     * @test
     *
     * PRECEDENCE, THE PART THAT MATTERS. A user correction to an editable field
     * survives a refresh. Reverting it would punish the person who fixed the
     * feed's mistake.
     */
    public function a_user_edit_to_an_editable_field_survives_a_reimport(): void
    {
        $this->seedRecord();
        $user    = User::factory()->create();
        $listing = $this->import($user);

        $listing->saveMeta('bedrooms', '4');   // the user corrects the feed

        $this->seedRecord(['BedroomsTotal' => 3]);
        $listing = $this->import($user);

        $this->assertSame('4', $this->metaOf($listing)['bedrooms'] ?? null);
    }

    /**
     * @test
     *
     * A user's own uploads are never removed by an MLS refresh.
     */
    public function user_uploaded_photos_survive_a_reimport(): void
    {
        $this->seedRecord();
        $user    = User::factory()->create();
        $listing = $this->import($user);

        $stored   = ListingPhotoEntry::collection($this->metaOf($listing)['property_photos'] ?? null);
        $withUser = ListingPhotoEntry::toStorageCollection($stored);
        $withUser[] = 'auction/images/my-own-photo.jpg';

        $listing->saveMeta('property_photos', $withUser);

        $listing = $this->import($user);
        $after   = ListingPhotoEntry::collection($this->metaOf($listing)['property_photos'] ?? null);

        $userEntries = array_values(array_filter($after, fn (ListingPhotoEntry $e) => ! $e->isMls()));

        $this->assertCount(1, $userEntries, 'The user\'s own upload was removed by an MLS refresh');
    }

    /**
     * @test
     *
     * `imported_at` records the FIRST import and never moves; `refreshed_at`
     * moves on every subsequent pass. Collapsing them would lose whichever
     * question is asked second.
     */
    public function the_first_import_timestamp_is_never_rewritten(): void
    {
        $this->seedRecord();
        $user    = User::factory()->create();

        $listing  = $this->import($user);
        $imported = $this->metaOf($listing)[MlsQuickImportDraftWriter::META_IMPORTED_AT] ?? null;

        $this->assertNotNull($imported);

        $listing = $this->import($user);
        $meta    = $this->metaOf($listing);

        $this->assertSame($imported, $meta[MlsQuickImportDraftWriter::META_IMPORTED_AT] ?? null);
        $this->assertArrayHasKey(MlsQuickImportDraftWriter::META_REFRESHED_AT, $meta);
    }

    /**
     * @test
     *
     * Two users importing the same MLS number get two independent drafts.
     * Neither can see or affect the other's — an MLS number is public, so
     * "find the listing for this number" must never resolve across owners.
     */
    public function two_users_importing_the_same_listing_get_independent_drafts(): void
    {
        $this->seedRecord();

        $a = User::factory()->create();
        $b = User::factory()->create();

        $listingA = $this->import($a);
        $listingB = $this->import($b);

        $this->assertNotSame($listingA->id, $listingB->id);
        $this->assertSame($a->id, $listingA->user_id);
        $this->assertSame($b->id, $listingB->user_id);
    }
}
