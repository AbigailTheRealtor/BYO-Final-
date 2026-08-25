<?php

namespace Tests\Feature\ListingImport;

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
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shortened MLS creation path, end to end through the real components.
 *
 *   MLS #  →  confirm  →  Traditional / Bidding Period  →  terms  →  review  →  publish
 *
 * The Bridge row is seeded into the local cache so these exercise the real
 * local-first lookup without any HTTP, exactly as MlsNumberPrefillTest does.
 * Its raw_json carries the restricted fields a real Stellar record would, so
 * the compliance assertions run against data that genuinely contains what must
 * not leak.
 */
class MlsQuickImportFlowTest extends TestCase
{
    use DatabaseTransactions;

    private const MLS = 'PHPUNIT-QI-A4567890';
    private const KEY = 'PHPUNIT-QI-STELLAR-KEY-1';

    private User $seller;
    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_direct_import.prefill_enabled'     => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'       => ['seller', 'landlord'],
            'mls_media.enabled'                     => true,
            'mls_media.license_acknowledged'        => true,
            'mls_media.hosting_mode'                => 'reference',
            'mls_media.roles'                       => ['seller', 'landlord'],
            'mls_media.max_images'                  => 50,
            'bridge.dataset'                        => 'phpunit_dataset',
            'bridge.token'                          => 'phpunit-token',
            'bya_beta.bidding_period_enabled'       => true,
        ]);

        $this->seller   = User::factory()->create(['user_type' => 'seller']);
        $this->landlord = User::factory()->create(['user_type' => 'seller']);
    }

    /**
     * @param  int|null  $photoCount  null = no Media array at all
     */
    private function seedBridgeProperty(?int $photoCount = 3, array $overrides = [], ?string $key = null, ?string $mls = null): BridgeProperty
    {
        $key ??= self::KEY;
        $mls ??= self::MLS;

        $raw = [
            'ListingKey' => $key,
            'ListingId'  => $mls,

            // Permitted display-only attributes (Layer C).
            'Appliances'            => ['Dishwasher', 'Range'],
            'Flooring'              => ['Tile'],
            'Roof'                  => 'Shingle',
            'Zoning'                => 'RSF-3',
            'ConstructionMaterials' => ['Block'],

            // Restricted — must never reach a form, a gallery or a page.
            'PublicRemarks'        => 'RESTRICTED_PUBLIC_REMARKS charming pool home',
            'PrivateRemarks'       => 'RESTRICTED_PRIVATE_REMARKS gate code 4455',
            'ShowingInstructions'  => 'RESTRICTED_SHOWING appointment only',
            'ListAgentFullName'    => 'RESTRICTED_AGENT Jane Agent',
            'ListAgentDirectPhone' => 'RESTRICTED_AGENTPHONE 8135550100',
            'ListOfficeName'       => 'RESTRICTED_BROKER Acme Realty',
        ];

        if ($photoCount !== null) {
            $media = [];
            for ($i = 0; $i < $photoCount; $i++) {
                $media[] = [
                    'MediaKey'         => "{$key}-m{$i}",
                    'MediaURL'         => "https://cdn.example.com/{$key}-{$i}.jpg",
                    'Order'            => $i,
                    'MediaCategory'    => 'Photo',
                    'ShortDescription' => "Photo {$i}",
                ];
            }
            $raw['Media'] = $media;
        }

        $raw = array_merge($raw, $overrides['raw'] ?? []);
        unset($overrides['raw']);

        return BridgeProperty::create(array_merge([
            'listing_key'             => $key,
            'listing_id'              => $mls,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => 'Residential',
            'property_sub_type'       => 'Single Family Residence',
            'list_price'              => 525000,
            'unparsed_address'        => '123 Main Street, Tampa, FL 33601',
            'city'                    => 'Tampa',
            'state_or_province'       => 'FL',
            'postal_code'             => '33601',
            'county_or_parish'        => 'Hillsborough',
            'bedrooms_total'          => 4,
            'bathrooms_total_integer' => 3,
            'living_area'             => 2450,
            'year_built'              => 2005,
            'latitude'                => 27.9506,
            'longitude'               => -82.4572,
            'raw_json'                => json_encode($raw),
            'imported_at'             => now(),
        ], $overrides));
    }

    /**
     * Drive the flow to the review step and return the live component.
     *
     * Seller's terms step renders the CANONICAL Sale Terms tab, so answers are
     * set on the real Livewire properties that tab binds — the same properties
     * the manual Create screen sets. There is no quick-import-only $terms bag
     * for this role any more, and a test that reintroduced one would be testing
     * a field list the product no longer has.
     *
     * @param  array<string, mixed>  $terms  canonical property => value
     */
    private function flowToReview(User $user, array $terms = [], array $multi = []): \Livewire\Testing\TestableLivewire
    {
        $component = Livewire::actingAs($user)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms')
            ->set('maximum_budget', '525000')
            ->set('offered_financing', ['Cash', 'Conventional']);

        foreach (array_merge($terms, $multi) as $field => $value) {
            $component->set($field, $value);
        }

        return $component->call('continueToReview');
    }

    // ─── Ingestion ───────────────────────────────────────────────────────────

    /** @test */
    public function a_valid_mls_number_produces_a_confirmation_with_the_property_headline(): void
    {
        $this->seedBridgeProperty();

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->assertSet('step', 'confirm')
            ->assertSet('errorMessage', '')
            ->assertSet('photoCount', 3)
            ->assertSee('123 Main Street')
            ->assertSee('4 Beds')
            ->assertSee('3 Baths');
    }

    /**
     * @test
     *
     * The provider answered, and had nothing. "Check the number" is the right
     * instruction here and it must not be confused with the outage case below —
     * telling someone their number is wrong when it was correct sends them off
     * to re-check something that was never the problem.
     */
    public function an_unknown_mls_number_reports_not_found_and_stays_on_the_lookup_step(): void
    {
        Http::fake(['*' => Http::response(['value' => []], 200)]);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', 'NO-SUCH-LISTING-999')
            ->call('findListing')
            ->assertSet('step', 'lookup')
            ->assertSee('find a listing matching that MLS');
    }

    /**
     * @test
     *
     * The provider could not be reached at all. A different sentence, because it
     * calls for a different action from the user.
     *
     * @dataProvider providerOutages
     */
    public function an_unreachable_provider_is_reported_as_an_outage_not_a_bad_number(callable $outage): void
    {
        $outage();

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', 'ANY-NUMBER-AT-ALL')
            ->call('findListing')
            ->assertSet('step', 'lookup')
            ->assertSee('connect to the MLS data service');
    }

    public function providerOutages(): array
    {
        return [
            'auth failure' => [fn () => Http::fake(['*' => Http::response('', 401)])],
            'server error' => [fn () => Http::fake(['*' => Http::response('', 500)])],
            'timeout'      => [fn () => Http::fake(function () {
                throw new \Illuminate\Http\Client\ConnectionException('timed out');
            })],
        ];
    }

    /**
     * @test
     *
     * No error message may carry a token, dataset name, status line or URL —
     * these strings reach a screen, and nothing derived from a token-bearing
     * request should travel that far.
     */
    public function a_provider_error_never_leaks_provider_detail_to_the_user(): void
    {
        Http::fake(['*' => Http::response('provider said: invalid access_token SECRET_TOKEN_VALUE', 401)]);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', 'ANY-NUMBER')
            ->call('findListing');

        $message = $component->get('errorMessage');

        $this->assertStringNotContainsString('SECRET_TOKEN_VALUE', $message);
        $this->assertStringNotContainsString('phpunit-token', $message);
        $this->assertStringNotContainsString('phpunit_dataset', $message);
        $this->assertStringNotContainsString('401', $message);
        $this->assertStringNotContainsString('bridgedataoutput', $message);
    }

    /** @test */
    public function an_empty_mls_number_is_refused(): void
    {
        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', '   ')
            ->call('findListing')
            ->assertSet('step', 'lookup')
            ->assertSee('Please enter an MLS #');
    }

    /** @test */
    public function the_flow_is_unreachable_when_the_feature_is_off(): void
    {
        config(['mls_direct_import.quick_import_enabled' => false]);

        $this->actingAs($this->seller)
            ->get(route('offer.listing.seller.quick-import'))
            ->assertNotFound();
    }

    /** @test */
    public function accepting_the_property_creates_a_draft_owned_by_the_importer(): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->assertSet('step', 'method');

        $listing = SellerAgentAuction::find($component->get('listingId'));

        $this->assertNotNull($listing);
        $this->assertSame($this->seller->id, $listing->user_id);
        $this->assertTrue((bool) $listing->is_draft, 'An import must produce a DRAFT, never a live listing');
    }

    /** @test */
    public function the_normalised_property_facts_are_written_to_the_listing(): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $meta = SellerAgentAuction::find($component->get('listingId'))->get;

        $this->assertSame('123 Main Street, Tampa, FL 33601', $meta->address);
        $this->assertSame('Tampa', $meta->property_city);
        $this->assertSame('FL', $meta->property_state);
        $this->assertSame('33601', $meta->property_zip);
        $this->assertSame('4', (string) $meta->bedrooms);
        $this->assertSame('3', (string) $meta->bathrooms);
        $this->assertSame('2005', (string) $meta->year_built);
        $this->assertSame(self::KEY, $meta->mls_listing_key);
        $this->assertSame(self::MLS, $meta->mls_number);
    }

    /** @test */
    public function import_provenance_is_recorded(): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $listing = SellerAgentAuction::find($component->get('listingId'));

        $this->assertSame('bridge', $listing->info(MlsQuickImportDraftWriter::META_PROVIDER));
        $this->assertSame('Active', $listing->info(MlsQuickImportDraftWriter::META_SOURCE_STATUS));
        $this->assertNotFalse($listing->info(MlsQuickImportDraftWriter::META_IMPORTED_AT));
    }

    /** @test */
    public function a_record_with_missing_attributes_is_handled_without_error(): void
    {
        $this->seedBridgeProperty(0, [
            'bedrooms_total'          => null,
            'bathrooms_total_integer' => null,
            'living_area'             => null,
            'year_built'              => null,
            'county_or_parish'        => null,
        ]);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->assertSet('step', 'confirm')
            ->call('acceptProperty')
            ->assertSet('step', 'method');
    }

    // ─── Media ───────────────────────────────────────────────────────────────

    /** @test */
    public function the_full_permitted_photo_set_is_imported_in_mls_order(): void
    {
        $this->seedBridgeProperty(5);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $entries = ListingPhotoEntry::collection(
            SellerAgentAuction::find($component->get('listingId'))->info('property_photos')
        );

        $this->assertCount(5, $entries);
        $this->assertSame(
            ['mls:' . self::KEY . '-m0', 'mls:' . self::KEY . '-m1', 'mls:' . self::KEY . '-m2',
             'mls:' . self::KEY . '-m3', 'mls:' . self::KEY . '-m4'],
            array_map(fn ($e) => $e->key(), $entries),
        );
    }

    /** @test */
    public function a_listing_with_one_photo_imports_it(): void
    {
        $this->seedBridgeProperty(1);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $entries = ListingPhotoEntry::collection(
            SellerAgentAuction::find($component->get('listingId'))->info('property_photos')
        );

        $this->assertCount(1, $entries);
        $this->assertTrue($entries[0]->isCover);
    }

    /** @test */
    public function a_listing_with_no_photos_imports_cleanly(): void
    {
        $this->seedBridgeProperty(null);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->assertSet('photoCount', 0)
            ->call('acceptProperty')
            ->assertSet('step', 'method');

        $this->assertSame(
            [],
            ListingPhotoEntry::collection(
                SellerAgentAuction::find($component->get('listingId'))->info('property_photos')
            )
        );
    }

    /** @test */
    public function the_first_mls_photo_becomes_the_cover(): void
    {
        $this->seedBridgeProperty(4);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $entries = ListingPhotoEntry::collection(
            SellerAgentAuction::find($component->get('listingId'))->info('property_photos')
        );

        $this->assertTrue($entries[0]->isCover);
        $this->assertCount(1, array_filter($entries, fn ($e) => $e->isCover));
    }

    /** @test */
    public function an_explicitly_preferred_mls_photo_becomes_the_cover_instead(): void
    {
        $property = $this->seedBridgeProperty(3);
        $raw = json_decode($property->raw_json, true);
        $raw['Media'][2]['PreferredPhotoYN'] = true;
        $property->update(['raw_json' => json_encode($raw)]);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $entries = ListingPhotoEntry::collection(
            SellerAgentAuction::find($component->get('listingId'))->info('property_photos')
        );

        $this->assertFalse($entries[0]->isCover);
        $this->assertTrue($entries[2]->isCover);
    }

    /** @test */
    public function broken_media_entries_are_skipped_without_failing_the_import(): void
    {
        $property = $this->seedBridgeProperty(2);
        $raw = json_decode($property->raw_json, true);
        $raw['Media'][] = ['MediaURL' => 'https://cdn.example.com/no-key.jpg']; // no key
        $raw['Media'][] = ['MediaKey' => 'no-url'];                              // no url
        $raw['Media'][] = 'not-an-object';
        $property->update(['raw_json' => json_encode($raw)]);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $this->assertCount(2, ListingPhotoEntry::collection(
            SellerAgentAuction::find($component->get('listingId'))->info('property_photos')
        ));
    }

    /**
     * @test
     *
     * The refresh contract's headline guarantee: re-running the import must not
     * grow the gallery.
     */
    public function a_duplicate_import_does_not_duplicate_the_photo_gallery(): void
    {
        $this->seedBridgeProperty(4);

        $first = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $listingId = $first->get('listingId');

        // A second, entirely separate visit to the flow.
        $second = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $this->assertSame($listingId, $second->get('listingId'), 'A re-import must resume the same draft');
        $this->assertCount(4, ListingPhotoEntry::collection(
            SellerAgentAuction::find($listingId)->info('property_photos')
        ));
        $this->assertSame(1, SellerAgentAuction::where('user_id', $this->seller->id)->count());
    }

    /** @test */
    public function a_refresh_recognises_changed_media_without_duplicating_it(): void
    {
        $property = $this->seedBridgeProperty(2);

        $first = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $listingId = $first->get('listingId');

        // The MLS replaces one image and adds another.
        $raw = json_decode($property->raw_json, true);
        $raw['Media'][0]['MediaURL'] = 'https://cdn.example.com/replaced.jpg';
        $raw['Media'][] = [
            'MediaKey' => self::KEY . '-m9', 'MediaURL' => 'https://cdn.example.com/new.jpg',
            'Order' => 9, 'MediaCategory' => 'Photo',
        ];
        $property->update(['raw_json' => json_encode($raw)]);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $entries = ListingPhotoEntry::collection(
            SellerAgentAuction::find($listingId)->info('property_photos')
        );

        $this->assertCount(3, $entries);
        $this->assertSame('https://cdn.example.com/replaced.jpg', $entries[0]->url);
    }

    /**
     * @test
     *
     * A background refresh must never destroy the photographs a seller took
     * themselves.
     */
    public function a_refresh_preserves_user_uploaded_photos(): void
    {
        $this->seedBridgeProperty(2);

        $first = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $listing = SellerAgentAuction::find($first->get('listingId'));

        // The owner adds their own photo, as the manual uploader would.
        $stored = ListingPhotoEntry::collection($listing->info('property_photos'));
        $raw    = ListingPhotoEntry::toStorageCollection($stored);
        $raw[]  = 'my-own-photo.jpg';
        $listing->saveMeta('property_photos', $raw);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $keys = array_map(
            fn ($e) => $e->key(),
            ListingPhotoEntry::collection($listing->fresh()->info('property_photos'))
        );

        $this->assertContains('my-own-photo.jpg', $keys, 'A refresh deleted a user-uploaded photo');
    }

    /** @test */
    public function no_mls_media_is_attached_when_the_media_feature_is_off(): void
    {
        config(['mls_media.enabled' => false]);
        $this->seedBridgeProperty(4);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->assertSet('photoCount', 0)
            ->call('acceptProperty');

        $this->assertSame([], ListingPhotoEntry::collection(
            SellerAgentAuction::find($component->get('listingId'))->info('property_photos')
        ));
    }

    /** @test */
    public function no_mls_media_is_attached_without_the_licence_acknowledgement(): void
    {
        config(['mls_media.license_acknowledged' => false]);
        $this->seedBridgeProperty(4);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->assertSet('photoCount', 0);
    }

    /** @test */
    public function an_unimplemented_hosting_mode_attaches_nothing(): void
    {
        config(['mls_media.hosting_mode' => 'cached']);
        $this->seedBridgeProperty(4);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->assertSet('photoCount', 0);
    }

    // ─── Flow ────────────────────────────────────────────────────────────────

    /** @test */
    public function the_flow_never_shows_the_giant_manual_property_form(): void
    {
        $this->seedBridgeProperty(2);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');

        // The terms step asks transaction questions and nothing else. If a
        // property input ever appears here, the feature has become the thing it
        // exists to replace.
        //
        // The labels asserted are the CANONICAL tab's, because that is what this
        // step renders now. "Financing You Will Accept" was the quick-import-only
        // schema's wording for the same question and no longer exists anywhere.
        $component->assertSee('Offered Financing/Currency')
            ->assertDontSee('Total Acreage')
            ->assertDontSee('Roof Type')
            ->assertDontSee('Exterior Construction');
    }

    /** @test */
    public function the_traditional_path_reaches_review_and_publishes(): void
    {
        $this->seedBridgeProperty(3);

        $component = $this->flowToReview($this->seller)->assertSet('step', 'review');

        $listingId = $component->get('listingId');
        $this->assertTrue((bool) SellerAgentAuction::find($listingId)->is_draft);

        $component->call('publish');

        $listing = SellerAgentAuction::find($listingId)->fresh();
        $this->assertFalse((bool) $listing->is_draft, 'Publish did not clear the draft flag');
        $this->assertSame('Traditional', $listing->info('auction_type'));
        $this->assertSame('Active', $listing->info('listing_status'));
    }

    /** @test */
    public function the_bidding_period_path_persists_its_window(): void
    {
        $this->seedBridgeProperty(1);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Bidding Period')
            ->set('auction_time', '7 Days')
            ->call('continueToTerms')
            ->set('terms', ['maximum_budget' => '525000'])
            ->set('multiTerms', ['offered_financing' => ['Cash']])
            ->call('continueToReview')
            ->assertSet('step', 'review')
            ->call('publish');

        $listing = SellerAgentAuction::where('user_id', $this->seller->id)->first();

        $this->assertSame('Bidding Period', $listing->info('auction_type'));
        $this->assertSame('7 Days', $listing->info('auction_time'));
    }

    /**
     * @test
     *
     * REPLACES `bidding_period_cannot_be_chosen_when_the_flag_hides_it`, which
     * asserted the opposite and was wrong about the product.
     *
     * That test encoded the belief that quick import should gate Bidding Period
     * on bya_beta.bidding_period_enabled "exactly as the wizard gates it". The
     * wizard does not gate it: the canonical Seller and Landlord Create Listing
     * partials render the Listing Type select with both options and no
     * condition around either — the gate was deliberately lifted for these two
     * roles and survives only as a mount() default for a blank auction_type.
     * Quick import reading the flag therefore offered a SMALLER set of listing
     * methods than the manual flow for the same role, so a listing created
     * through the shortened path could never be a Bidding Period listing.
     *
     * The flag is forced OFF here because that is the configuration under which
     * the option used to disappear.
     */
    public function bidding_period_is_offered_regardless_of_the_beta_flag(): void
    {
        config(['bya_beta.bidding_period_enabled' => false]);
        $this->seedBridgeProperty(1);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Bidding Period')
            ->assertSet('auction_type', 'Bidding Period')
            ->assertSet('errorMessage', '');
    }

    /** @test */
    public function a_bidding_period_with_no_window_cannot_continue(): void
    {
        config(['bya_beta.bidding_period_enabled' => true]);
        $this->seedBridgeProperty(1);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Bidding Period')
            ->call('continueToTerms')
            ->assertSet('step', 'method')
            ->assertSee('how long the bidding period should run');
    }

    /** @test */
    public function financing_selections_persist(): void
    {
        $this->seedBridgeProperty(1);

        $component = $this->flowToReview($this->seller, [], ['offered_financing' => ['Cash', 'VA', 'Seller Financing']]);

        $listing = SellerAgentAuction::find($component->get('listingId'));

        $this->assertEqualsCanonicalizing(
            ['Cash', 'VA', 'Seller Financing'],
            (array) $listing->get->offered_financing,
        );
    }

    /**
     * @test
     *
     * The schema is the vocabulary. A payload naming something outside it must
     * not be able to write free text into a field the review screen presents as
     * a fixed list.
     */
    public function a_financing_value_outside_the_declared_vocabulary_is_discarded(): void
    {
        $this->seedBridgeProperty(1);

        $component = $this->flowToReview($this->seller, [], [
            'offered_financing' => ['Cash', 'Barter With Livestock'],
        ]);

        $this->assertSame(
            ['Cash'],
            array_values((array) SellerAgentAuction::find($component->get('listingId'))->get->offered_financing),
        );
    }

    /** @test */
    public function other_transaction_terms_persist(): void
    {
        $this->seedBridgeProperty(1);

        $component = $this->flowToReview($this->seller, [
            'initial_deposit_requested'   => '15000',
            'possession_preference'       => 'At Closing',
            'preferred_inspection_period' => '10',
            'excluded_items'              => 'Dining room chandelier',
        ]);

        $meta = SellerAgentAuction::find($component->get('listingId'))->get;

        $this->assertSame('15000', $meta->initial_deposit_requested);
        $this->assertSame('At Closing', $meta->possession_preference);
        $this->assertSame('10', $meta->preferred_inspection_period);
        $this->assertSame('Dining room chandelier', $meta->excluded_items);
    }

    /**
     * @test
     *
     * The conditional Sale Terms sections quick import could not express at all
     * before this existed: seller financing, a balloon schedule, a crypto split,
     * an exchange, and the Estimated Payment Assumptions. Each is set through the
     * canonical property the manual tab binds and read back from the SAME meta
     * key the manual flow writes.
     *
     * This is the behavioural half of SellerSaleTermsParityTest's structural
     * checks: those prove the fields EXIST on this path, this proves an answer
     * given on this path actually survives to the listing.
     */
    public function canonical_conditional_sale_terms_persist_through_quick_import(): void
    {
        $this->seedBridgeProperty(1);

        $component = $this->flowToReview($this->seller, [
            // Seller financing + amortisation
            'seller_financing_type'      => '%',
            'seller_down_payment_amount' => '50,000',
            'interest_rate'              => '6.5',
            'loan_duration'              => '30',
            'seller_amortization_type'   => 'Fully Amortized',
            'seller_payment_frequency'   => 'Monthly',

            // Balloon
            'balloon_payment_amount' => '120,000',
            'balloon_payment_date'   => '2030-01-01',

            // Cryptocurrency
            'cryptocurrency_type'    => 'Bitcoin',
            'crypto_percentage'      => '25',
            'cash_percentage_crypto' => '75',

            // Exchange / Trade
            'other_exchange_item' => 'Vintage tractor',
            'exchange_item_value' => '18,500',

            // Estimated Payment Assumptions
            'payment_interest_rate'         => '6.75',
            'payment_loan_term'             => '30',
            'payment_annual_property_taxes' => '7,200',
            'payment_monthly_insurance'     => '150',
        ]);

        $meta = SellerAgentAuction::find($component->get('listingId'))->get;

        // Stored under the canonical keys…
        $this->assertSame('%', $meta->seller_financing_type);
        $this->assertSame('Fully Amortized', $meta->seller_amortization_type);
        $this->assertSame('Monthly', $meta->seller_payment_frequency);
        $this->assertSame('6.5', $meta->interest_rate);
        $this->assertSame('30', $meta->loan_duration);
        $this->assertSame('2030-01-01', $meta->balloon_payment_date);
        $this->assertSame('Bitcoin', $meta->cryptocurrency_type);
        $this->assertSame('Vintage tractor', $meta->other_exchange_item);
        $this->assertSame('6.75', $meta->payment_interest_rate);
        $this->assertSame('30', $meta->payment_loan_term);

        // …and money fields are comma-stripped exactly as the manual flow strips
        // them, so "50,000" is stored as a number and not as text.
        $this->assertSame('50000', $meta->seller_down_payment_amount);
        $this->assertSame('120000', $meta->balloon_payment_amount);
        $this->assertSame('18500', $meta->exchange_item_value);
        $this->assertSame('7200', $meta->payment_annual_property_taxes);
        $this->assertSame('150', $meta->payment_monthly_insurance);
        $this->assertSame('25', $meta->crypto_percentage);
        $this->assertSame('75', $meta->cash_percentage_crypto);
    }

    /**
     * @test
     *
     * The fifteen fields manual Create used to silently discard reach the
     * listing through Quick Import too, with the same transforms.
     *
     * Quick Import inherited Create's gap by design — it writes through the same
     * routine — so repairing Create repaired this path in the same edit. This
     * asserts that rather than assuming it.
     */
    public function the_repaired_sale_terms_persist_through_quick_import(): void
    {
        $this->seedBridgeProperty(1);

        $component = $this->flowToReview($this->seller, [
            'occupant_tenant'                    => 'Lease expires March 2027',
            'balloon_payment'                    => 'Yes',
            'outstanding_balance'                => '245000',
            'lease_option_fee_credit'            => 'Yes',
            'lease_option_fee_credit_percentage' => '50',
            'lease_option_maintenance'           => 'Buyer',
            'lease_option_extension_terms'       => 'One 6-month extension',
            'lease_purchase_rent_credit'         => 'Yes',
            'lease_purchase_rent_credit_amount'  => '1,000',
            'lease_purchase_deposit'             => '15,000',
            'lease_purchase_maintenance'         => 'Seller',
            'lease_purchase_extension_terms'     => 'Month to month thereafter',
            'nft_gas_fees'                       => 'Buyer',
            'nft_transfer_method'                => 'Direct wallet transfer',
            'nft_valuation_method'               => 'Third-party appraisal',
        ]);

        $meta = SellerAgentAuction::find($component->get('listingId'))->get;

        $this->assertSame('Lease expires March 2027', $meta->occupant_tenant);
        $this->assertSame('Yes', $meta->balloon_payment);
        $this->assertSame('245000', $meta->outstanding_balance);
        $this->assertSame('Yes', $meta->lease_option_fee_credit);
        $this->assertSame('50', $meta->lease_option_fee_credit_percentage);
        $this->assertSame('Buyer', $meta->lease_option_maintenance);
        $this->assertSame('One 6-month extension', $meta->lease_option_extension_terms);
        $this->assertSame('Yes', $meta->lease_purchase_rent_credit);
        $this->assertSame('Seller', $meta->lease_purchase_maintenance);
        $this->assertSame('Month to month thereafter', $meta->lease_purchase_extension_terms);
        $this->assertSame('Buyer', $meta->nft_gas_fees);
        $this->assertSame('Direct wallet transfer', $meta->nft_transfer_method);
        $this->assertSame('Third-party appraisal', $meta->nft_valuation_method);

        // The two money fields among them are comma-stripped.
        $this->assertSame('1000', $meta->lease_purchase_rent_credit_amount);
        $this->assertSame('15000', $meta->lease_purchase_deposit);
    }

    /**
     * @test
     *
     * Bidding-period pricing — starting, reserve and buy-now — reaches the
     * listing from quick import. The old reduced schema had none of these, so a
     * bidding-period listing created this way could not carry a reserve at all.
     */
    public function bidding_period_pricing_persists_through_quick_import(): void
    {
        $this->seedBridgeProperty(1);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Bidding Period')
            ->set('auction_time', '7 Days')
            ->call('continueToTerms')
            ->set('maximum_budget', '525000')
            ->set('starting_price', '499,000')
            ->set('reserve_price', '515,000')
            ->set('buy_now_price', '599,000')
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $meta = SellerAgentAuction::find($component->get('listingId'))->get;

        $this->assertSame('Bidding Period', $meta->auction_type);
        $this->assertSame('7 Days', $meta->auction_time);
        $this->assertSame('499000', $meta->starting_price);
        $this->assertSame('515000', $meta->reserve_price);
        $this->assertSame('599000', $meta->buy_now_price);
    }

    /** @test */
    public function a_missing_required_term_blocks_review(): void
    {
        $this->seedBridgeProperty(1);

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms')
            // The asking price is the one term this path requires. The manual
            // flow's publish rules require no sale-terms field at all, so the
            // former additional requirement on financing was quick import being
            // stricter than the screen it mirrors, and is gone.
            ->set('maximum_budget', '')
            ->set('offered_financing', [])
            ->call('continueToReview')
            ->assertSet('step', 'terms')
            ->assertSee('Please complete');
    }

    // ─── Review before publish ───────────────────────────────────────────────

    /**
     * @test
     *
     * The protection against a wrong MLS number, stale feed data and an
     * accidental publication: nothing goes public until the review step.
     */
    public function nothing_is_published_before_the_review_step(): void
    {
        $this->seedBridgeProperty(2);

        $component = Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');

        $this->assertTrue(
            (bool) SellerAgentAuction::find($component->get('listingId'))->is_draft,
            'The listing was published before the user reviewed it'
        );

        // …and calling publish() out of order does nothing.
        $component->call('publish');

        $this->assertTrue((bool) SellerAgentAuction::find($component->get('listingId'))->fresh()->is_draft);
    }

    /** @test */
    public function the_review_shows_the_imported_photos_and_permitted_mls_details(): void
    {
        $this->seedBridgeProperty(3);

        $this->flowToReview($this->seller)
            ->assertSee('Review your listing')
            ->assertSee('Dishwasher, Range')   // permitted Layer C attribute
            ->assertSee('Shingle')
            ->assertSee('Cover');
    }

    /**
     * @test
     *
     * Storage, use and display are three different permissions. The record we
     * hold contains all of these; the page must contain none of them.
     */
    public function the_review_never_renders_restricted_mls_content(): void
    {
        $this->seedBridgeProperty(3);

        $component = $this->flowToReview($this->seller);

        foreach ([
            'RESTRICTED_PUBLIC_REMARKS',
            'RESTRICTED_PRIVATE_REMARKS',
            'RESTRICTED_SHOWING',
            'RESTRICTED_AGENT',
            'RESTRICTED_AGENTPHONE',
            'RESTRICTED_BROKER',
        ] as $needle) {
            $component->assertDontSee($needle);
        }
    }

    // ─── Photo control ───────────────────────────────────────────────────────

    /** @test */
    public function the_owner_can_choose_a_different_cover(): void
    {
        $this->seedBridgeProperty(3);

        $component = $this->flowToReview($this->seller);
        $listing   = SellerAgentAuction::find($component->get('listingId'));

        $component->call('setCoverPhoto', 'mls:' . self::KEY . '-m2');

        $entries = ListingPhotoEntry::collection($listing->fresh()->info('property_photos'));

        $this->assertFalse($entries[0]->isCover);
        $this->assertTrue($entries[2]->isCover);
        $this->assertTrue($entries[2]->coverChosenByOwner);
    }

    /** @test */
    public function the_owner_can_reorder_the_gallery_and_the_order_survives_a_refresh(): void
    {
        $this->seedBridgeProperty(3);

        $component = $this->flowToReview($this->seller);
        $listing   = SellerAgentAuction::find($component->get('listingId'));

        $component->call('reorderGallery', [
            'mls:' . self::KEY . '-m2',
            'mls:' . self::KEY . '-m0',
            'mls:' . self::KEY . '-m1',
        ]);

        $keys = array_map(fn ($e) => $e->key(), ListingPhotoEntry::collection($listing->fresh()->info('property_photos')));
        $this->assertSame(
            ['mls:' . self::KEY . '-m2', 'mls:' . self::KEY . '-m0', 'mls:' . self::KEY . '-m1'],
            $keys,
        );

        // A later refresh must not undo the owner's arrangement.
        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        $keysAfter = array_map(fn ($e) => $e->key(), ListingPhotoEntry::collection($listing->fresh()->info('property_photos')));
        $this->assertSame($keys, $keysAfter, 'An MLS refresh reshuffled a gallery the owner arranged');
    }

    /** @test */
    public function a_partial_reorder_payload_cannot_delete_photos(): void
    {
        $this->seedBridgeProperty(3);

        $component = $this->flowToReview($this->seller);
        $listing   = SellerAgentAuction::find($component->get('listingId'));

        $component->call('reorderGallery', ['mls:' . self::KEY . '-m2']);

        $this->assertCount(3, ListingPhotoEntry::collection($listing->fresh()->info('property_photos')));
    }

    // ─── Landlord parity ─────────────────────────────────────────────────────

    /** @test */
    public function the_landlord_flow_imports_property_and_photos_too(): void
    {
        $this->seedBridgeProperty(3, ['property_type' => 'Residential Lease'], 'PHPUNIT-QI-LL-KEY', 'PHPUNIT-QI-LL-MLS');

        $component = Livewire::actingAs($this->landlord)
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', 'PHPUNIT-QI-LL-MLS')
            ->call('findListing')
            ->assertSet('step', 'confirm')
            ->assertSet('photoCount', 3)
            ->call('acceptProperty')
            ->assertSet('step', 'method');

        $listing = LandlordAgentAuction::find($component->get('listingId'));

        $this->assertSame($this->landlord->id, $listing->user_id);
        $this->assertCount(3, ListingPhotoEntry::collection($listing->info('property_photos')));
    }

    /**
     * @test
     *
     * The brief calls this out specifically: seller financing questions must not
     * be forced onto a landlord workflow where they do not belong.
     */
    public function the_landlord_flow_never_asks_the_seller_financing_questions(): void
    {
        $schema = (new LandlordMlsQuickImport())->questionSchema();

        $this->assertArrayNotHasKey('offered_financing', $schema);
        $this->assertArrayNotHasKey('other_financing', $schema);
        $this->assertArrayHasKey('desired_lease_length', $schema);
        $this->assertArrayHasKey('security_deposit_amount', $schema);
    }

    /** @test */
    public function the_landlord_flow_reaches_review_and_publishes(): void
    {
        $this->seedBridgeProperty(2, [], 'PHPUNIT-QI-LL2-KEY', 'PHPUNIT-QI-LL2-MLS');

        $component = Livewire::actingAs($this->landlord)
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', 'PHPUNIT-QI-LL2-MLS')
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms')
            // desired_rental_amount, not maximum_budget: the landlord rent key is the
            // one the published page reads. See LandlordMlsQuickImport::priceField().
            ->set('terms', ['desired_rental_amount' => '2400'])
            ->set('multiTerms', ['desired_lease_length' => ['1 Year']])
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $component->call('publish');

        $listing = LandlordAgentAuction::find($component->get('listingId'))->fresh();

        $this->assertFalse((bool) $listing->is_draft);
        $this->assertSame('Traditional', $listing->info('auction_type'));
        $this->assertEqualsCanonicalizing(['1 Year'], (array) $listing->get->desired_lease_length);
    }

    // ─── Regression: the manual path is untouched ────────────────────────────

    /** @test */
    public function the_manual_creation_routes_still_work_with_the_quick_import_on(): void
    {
        $this->actingAs($this->seller)->get(route('offer.listing.seller'))->assertOk();
        $this->actingAs($this->landlord)->get(route('offer.listing.landlord'))->assertOk();
    }

    /** @test */
    public function the_manual_creation_routes_still_work_with_the_quick_import_off(): void
    {
        config(['mls_direct_import.quick_import_enabled' => false]);

        $this->actingAs($this->seller)->get(route('offer.listing.seller'))->assertOk();
        $this->actingAs($this->landlord)->get(route('offer.listing.landlord'))->assertOk();
    }
}
