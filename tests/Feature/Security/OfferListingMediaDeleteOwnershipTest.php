<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * S5 — Offer Listing media deletion: the target comes from the owned record, not the client.
 *
 * The same defect PR #69 closed for Hire Agent, in the six Offer Listing components. Two axes,
 * kept distinct because a fix for one does not imply the other:
 *
 *   1. Does the caller own the row?      Every delete method used an unscoped find()/findOrFail().
 *   2. Is the deleted file the row's?    Every storage path was built from a public Livewire
 *                                        property, so owning ANY listing let a caller name any file.
 *
 * The hydrate() guard these components already carry does not answer (1) at the delete boundary:
 * Livewire applies client syncInput updates AFTER the hydration hooks run, so an id tampered
 * between requests arrives having already passed hydrate.
 *
 * THREE SHAPES OF MEDIA, THREE DIFFERENT AUTHORITIES:
 *
 *   scalar        photo / video           one filename in one meta key, public disk
 *   collection    property_photos         a JSON ARRAY of filenames, public disk — the client
 *                                         picks WHICH, so its value is a selector that must be a
 *                                         member of the owned set, never a path
 *   private       listing_documents       written with storePrivate, so deletion targets the
 *                                         PRIVATE disk — where disclosures and contracts live,
 *                                         which is why traversal there mattered more than public
 *
 * Both disks are faked. Every attack asserts on the FILES, not merely on an absent exception:
 * Flysystem 1.x collapses `..` rather than rejecting it, so `../../probe.txt` resolves to a real
 * path at the disk root and a test that only checked for a thrown error would pass while a file
 * was being deleted.
 */
class OfferListingMediaDeleteOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private const OWNER_PHOTO   = 'owner-photo.jpg';
    private const OWNER_PHOTO_2 = 'owner-photo-2.jpg';
    private const OWNER_VIDEO   = 'owner-video.mp4';
    private const OWNER_DOC     = 'owner-doc.pdf';
    private const VICTIM_PHOTO  = 'victim-photo.jpg';
    private const VICTIM_VIDEO  = 'victim-video.mp4';
    private const VICTIM_DOC    = 'victim-doc.pdf';

    private string $publicDisk;
    private string $privateDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $disks = app(\App\Support\Storage\ListingStorageDisks::class);
        $this->publicDisk  = $disks->publicDiskName();
        $this->privateDisk = $disks->privateDiskName();

        Storage::fake($this->publicDisk);
        Storage::fake($this->privateDisk);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // Public-disk targets: a sibling photo, a video one directory across, and a file at the
        // disk root two directories up.
        Storage::disk($this->publicDisk)->put('auction/images/' . self::VICTIM_PHOTO, 'victim photo');
        Storage::disk($this->publicDisk)->put('auction/videos/' . self::VICTIM_VIDEO, 'victim video');
        Storage::disk($this->publicDisk)->put('probe.txt', 'public root file');

        // Private-disk targets: another owner's document, and a file at the private root.
        Storage::disk($this->privateDisk)->put('auction/documents/' . self::VICTIM_DOC, 'victim doc');
        Storage::disk($this->privateDisk)->put('probe-private.txt', 'private root file');
    }

    private function assertAllVictimFilesSurvive(): void
    {
        Storage::disk($this->publicDisk)->assertExists('auction/images/' . self::VICTIM_PHOTO);
        Storage::disk($this->publicDisk)->assertExists('auction/videos/' . self::VICTIM_VIDEO);
        Storage::disk($this->publicDisk)->assertExists('probe.txt');
        Storage::disk($this->privateDisk)->assertExists('auction/documents/' . self::VICTIM_DOC);
        Storage::disk($this->privateDisk)->assertExists('probe-private.txt');
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    /**
     * WHY THESE ROWS ARE STAMPED
     * --------------------------
     * The four `*_agent_auctions` tables are shared by Hire Agent and Create Offer Listing,
     * and a row carrying no `workflow_type` is refused by both products' edit surfaces.
     * These fixtures back OFFER LISTING components, so an unstamped row is not a realistic
     * fixture — no Offer Listing edit route would ever have served one, and mounting it
     * models nothing that can happen in production.
     *
     * Stamping is a fixture correction, not an accommodation. What this file guards —
     * ownership of the row and authority over the filename — is untouched by it, and the
     * cross-product boundary is asserted separately in tests/Feature/Listing.
     */
    private function sellerListing(User $owner): SellerAgentAuction
    {
        $l = SellerAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'L', 'address' => '1 Any Way',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $this->seedMedia($l);
        ListingWorkflow::stamp($l, ListingWorkflow::OFFER_LISTING);

        return $l->fresh();
    }

    private function landlordListing(User $owner): LandlordAgentAuction
    {
        $l = LandlordAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'L', 'is_draft' => false,
        ]);
        $this->seedMedia($l);
        ListingWorkflow::stamp($l, ListingWorkflow::OFFER_LISTING);

        return $l->fresh();
    }

    private function tenantListing(User $owner): TenantAgentAuction
    {
        $l = TenantAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'L', 'is_draft' => false,
        ]);
        $this->seedMedia($l);
        $l->saveMeta('user_type', 'tenant');
        ListingWorkflow::stamp($l, ListingWorkflow::OFFER_LISTING);

        return $l->fresh();
    }

    /** The owner's own media, on disk and in meta, in all three shapes. */
    private function seedMedia($listing): void
    {
        $listing->saveMeta('property_photos', [self::OWNER_PHOTO, self::OWNER_PHOTO_2]);
        $listing->saveMeta('listing_documents', self::OWNER_DOC);
        $listing->saveMeta('photo', self::OWNER_PHOTO);
        $listing->saveMeta('video', self::OWNER_VIDEO);

        Storage::disk($this->publicDisk)->put('auction/images/' . self::OWNER_PHOTO, 'owner photo');
        Storage::disk($this->publicDisk)->put('auction/images/' . self::OWNER_PHOTO_2, 'owner photo 2');
        Storage::disk($this->publicDisk)->put('auction/videos/' . self::OWNER_VIDEO, 'owner video');
        Storage::disk($this->privateDisk)->put('auction/documents/' . self::OWNER_DOC, 'owner doc');
    }

    /** @return array<string,array{0:string}> the three tampering payloads */
    public function tamperedValueProvider(): array
    {
        return [
            'foreign filename'      => [self::VICTIM_PHOTO],
            'directory crossing'    => ['../videos/' . self::VICTIM_VIDEO],
            'public root traversal' => ['../../probe.txt'],
        ];
    }

    /** @return array<string,array{0:string}> tampering payloads aimed at the private disk */
    public function tamperedDocumentProvider(): array
    {
        return [
            'foreign document'       => [self::VICTIM_DOC],
            'private root traversal' => ['../../probe-private.txt'],
            'cross-disk traversal'   => ['../../auction/images/' . self::VICTIM_PHOTO],
        ];
    }

    // =====================================================================
    // A/B/C — property photo collection (Seller / Landlord, create + edit)
    // =====================================================================

    /** @dataProvider tamperedValueProvider */
    public function test_seller_create_property_photo_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $this->actingAs($owner);

        Livewire::test(SellerOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('propertyPhotos', [$tampered])
            ->call('deletePropertyPhoto', 0);

        $this->assertAllVictimFilesSurvive();
    }

    /** @dataProvider tamperedValueProvider */
    public function test_seller_edit_property_photo_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $this->actingAs($owner);

        Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $listing->id])
            ->set('listingId', $listing->id)
            ->set('propertyPhotos', [$tampered])
            ->call('deletePropertyPhoto', 0);

        $this->assertAllVictimFilesSurvive();
    }

    /** @dataProvider tamperedValueProvider */
    public function test_landlord_create_property_photo_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordListing($owner);
        $this->actingAs($owner);

        Livewire::test(LandlordOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('propertyPhotos', [$tampered])
            ->call('deletePropertyPhoto', 0);

        $this->assertAllVictimFilesSurvive();
    }

    /** @dataProvider tamperedValueProvider */
    public function test_landlord_edit_property_photo_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordListing($owner);
        $this->actingAs($owner);

        Livewire::test(LandlordOfferListingEdit::class, ['auctionId' => $listing->id])
            ->set('propertyPhotos', [$tampered])
            ->call('deletePropertyPhoto', 0);

        $this->assertAllVictimFilesSurvive();
    }

    /**
     * A filename that is well-formed but simply not part of the owned listing's persisted set.
     * The selector must be a member of that set; membership is what makes it deletable.
     */
    public function test_a_filename_absent_from_the_owned_collection_is_refused(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $this->actingAs($owner);

        Livewire::test(SellerOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('propertyPhotos', [self::VICTIM_PHOTO])
            ->call('deletePropertyPhoto', 0);

        $this->assertAllVictimFilesSurvive();
        $this->assertSame(
            [self::OWNER_PHOTO, self::OWNER_PHOTO_2],
            json_decode($listing->fresh()->info('property_photos'), true),
            'The owned collection must be unchanged by a non-member selector'
        );
    }

    // =====================================================================
    // E — listing documents (private disk)
    // =====================================================================

    /** @dataProvider tamperedDocumentProvider */
    public function test_seller_create_document_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $this->actingAs($owner);

        Livewire::test(SellerOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('listingDocuments', $tampered)
            ->call('deleteListingDocument');

        $this->assertAllVictimFilesSurvive();
    }

    /** @dataProvider tamperedDocumentProvider */
    public function test_seller_edit_document_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $this->actingAs($owner);

        Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $listing->id])
            ->set('listingId', $listing->id)
            ->set('listingDocuments', $tampered)
            ->call('deleteListingDocument');

        $this->assertAllVictimFilesSurvive();
    }

    /** @dataProvider tamperedDocumentProvider */
    public function test_landlord_create_document_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordListing($owner);
        $this->actingAs($owner);

        Livewire::test(LandlordOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('listingDocuments', $tampered)
            ->call('deleteListingDocument');

        $this->assertAllVictimFilesSurvive();
    }

    // =====================================================================
    // A/B/C — scalar photo/video (Landlord create, Tenant create + edit)
    // =====================================================================

    /** @dataProvider tamperedValueProvider */
    public function test_landlord_create_photo_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordListing($owner);
        $this->actingAs($owner);

        Livewire::test(LandlordOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('photo', $tampered)
            ->call('deletePhoto');

        $this->assertAllVictimFilesSurvive();
    }

    /** @dataProvider tamperedValueProvider */
    public function test_tenant_create_photo_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->tenantListing($owner);
        $this->actingAs($owner);

        Livewire::test(TenantOfferListing::class, ['user_type' => 'tenant'])
            ->set('listingId', $listing->id)
            ->set('photo', $tampered)
            ->call('deletePhoto');

        $this->assertAllVictimFilesSurvive();
    }

    /** @dataProvider tamperedValueProvider */
    public function test_tenant_edit_video_delete_ignores_a_tampered_value(string $tampered): void
    {
        $owner   = User::factory()->create();
        $listing = $this->tenantListing($owner);
        $this->actingAs($owner);

        Livewire::test(TenantOfferListingEdit::class, ['auctionId' => $listing->id, 'user_type' => 'tenant'])
            ->set('video', $tampered)
            ->call('deleteVideo');

        $this->assertAllVictimFilesSurvive();
    }

    // =====================================================================
    // Tenant multi-role: user_type must not redirect deletion to another table
    // =====================================================================

    /**
     * ON WHAT THESE TWO PROVE, AND WHAT THEY DO NOT.
     *
     * The Tenant components resolve their table from the public $user_type and their row from the
     * public $listingId/$auctionId, so the guard is keyed on the PAIR. The row axis is driven
     * directly below: a foreign id under a consistent user_type must be refused, and before this
     * change the unscoped find() let it through.
     *
     * The TABLE axis cannot be driven through Livewire's test harness. Each ->set() is a full
     * round trip including a render, and switching a Tenant component's user_type to 'seller'
     * renders the seller tab partials, which reference variables (e.g. $water_access) the Tenant
     * component does not define — the render fails before any action runs. That is a pre-existing
     * rendering fragility, unrelated to this change and deliberately not fixed here. A test built
     * on it would pass because the render exploded, not because the guard held, which is worse
     * than no test. The same resolver enforces both axes in one expression, and the table axis is
     * covered directly by TenantAgentAuctionOwnershipTest for the identical PR #68 construct.
     */
    public function test_tenant_create_refuses_a_foreign_listing_id(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $victimListing = $this->tenantListing($victim);

        $this->actingAs($attacker);

        try {
            Livewire::test(TenantOfferListing::class, ['user_type' => 'tenant'])
                ->set('listingId', $victimListing->id)
                ->set('photo', self::OWNER_PHOTO)
                ->call('deletePhoto');
        } catch (\Throwable $e) {
            // fail-closed refusal is acceptable; a silent foreign write is not
        }

        $this->assertSame(
            self::OWNER_PHOTO,
            $victimListing->fresh()->info('photo'),
            "The victim's listing meta must be untouched"
        );
        Storage::disk($this->publicDisk)->assertExists('auction/images/' . self::OWNER_PHOTO);
        $this->assertAllVictimFilesSurvive();
    }

    public function test_tenant_edit_refuses_a_foreign_auction_id(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $victimListing = $this->tenantListing($victim);
        $attackerOwn   = $this->tenantListing($attacker);

        $this->actingAs($attacker);

        try {
            Livewire::test(TenantOfferListingEdit::class, ['auctionId' => $attackerOwn->id, 'user_type' => 'tenant'])
                ->set('auctionId', $victimListing->id)
                ->set('photo', self::OWNER_PHOTO)
                ->call('deletePhoto');
        } catch (\Throwable $e) {
            // fail-closed refusal is acceptable
        }

        $this->assertSame(
            self::OWNER_PHOTO,
            $victimListing->fresh()->info('photo'),
            "The victim's listing meta must be untouched"
        );
        $this->assertAllVictimFilesSurvive();
    }

    // =====================================================================
    // D — foreign listing id
    // =====================================================================

    public function test_seller_create_refuses_a_foreign_listing_id(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->sellerListing($victim);

        $this->actingAs($attacker);

        try {
            Livewire::test(SellerOfferListing::class)
                ->set('listingId', $listing->id)
                ->set('propertyPhotos', [self::OWNER_PHOTO])
                ->call('deletePropertyPhoto', 0);
        } catch (\Throwable $e) {
            // A fail-closed refusal is acceptable; a silent foreign write is not.
        }

        $this->assertSame(
            [self::OWNER_PHOTO, self::OWNER_PHOTO_2],
            json_decode($listing->fresh()->info('property_photos'), true),
            "The victim's collection must be unchanged"
        );
        Storage::disk($this->publicDisk)->assertExists('auction/images/' . self::OWNER_PHOTO);
    }

    public function test_landlord_create_refuses_a_foreign_listing_id_for_documents(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->landlordListing($victim);

        $this->actingAs($attacker);

        try {
            Livewire::test(LandlordOfferListing::class)
                ->set('listingId', $listing->id)
                ->set('listingDocuments', self::OWNER_DOC)
                ->call('deleteListingDocument');
        } catch (\Throwable $e) {
            // fail-closed refusal acceptable
        }

        $this->assertSame(self::OWNER_DOC, $listing->fresh()->info('listing_documents'), "Victim's document meta must survive");
        Storage::disk($this->privateDisk)->assertExists('auction/documents/' . self::OWNER_DOC);
    }

    // =====================================================================
    // G — positive owner cases
    // =====================================================================

    public function test_owner_can_delete_one_property_photo_and_the_collection_is_rewritten(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $this->actingAs($owner);

        Livewire::test(SellerOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('propertyPhotos', [self::OWNER_PHOTO, self::OWNER_PHOTO_2])
            ->call('deletePropertyPhoto', 0);

        Storage::disk($this->publicDisk)->assertMissing('auction/images/' . self::OWNER_PHOTO);
        Storage::disk($this->publicDisk)->assertExists('auction/images/' . self::OWNER_PHOTO_2);
        $this->assertSame(
            [self::OWNER_PHOTO_2],
            array_values(json_decode($listing->fresh()->info('property_photos'), true)),
            'The remaining entry must survive in the rewritten collection'
        );
        $this->assertAllVictimFilesSurvive();
    }

    public function test_owner_can_delete_their_listing_document_from_private_storage(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $this->actingAs($owner);

        Livewire::test(SellerOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('listingDocuments', self::OWNER_DOC)
            ->call('deleteListingDocument');

        Storage::disk($this->privateDisk)->assertMissing('auction/documents/' . self::OWNER_DOC);
        $this->assertFalse($listing->fresh()->info('listing_documents'), 'document meta must be cleared');
        $this->assertAllVictimFilesSurvive();
    }

    public function test_owner_can_delete_their_landlord_photo(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordListing($owner);
        $this->actingAs($owner);

        Livewire::test(LandlordOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('photo', self::OWNER_PHOTO)
            ->call('deletePhoto');

        Storage::disk($this->publicDisk)->assertMissing('auction/images/' . self::OWNER_PHOTO);
        $this->assertFalse($listing->fresh()->info('photo'), 'photo meta must be cleared');
    }

    /**
     * The edit component deleted video from 'auction/images' while saves store to
     * 'auction/videos', so an owner's video was never removed. Corrected in this change because
     * the method's deletion directory is being made server-controlled anyway.
     */
    public function test_tenant_edit_deletes_the_owner_video_from_the_videos_directory(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->tenantListing($owner);
        $this->actingAs($owner);

        Livewire::test(TenantOfferListingEdit::class, ['auctionId' => $listing->id, 'user_type' => 'tenant'])
            ->set('video', self::OWNER_VIDEO)
            ->call('deleteVideo');

        Storage::disk($this->publicDisk)->assertMissing('auction/videos/' . self::OWNER_VIDEO);
        $this->assertFalse($listing->fresh()->info('video'), 'video meta must be cleared');
    }

    // =====================================================================
    // H — malformed persisted meta
    // =====================================================================

    public function test_a_malformed_persisted_document_path_is_refused_not_normalized(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $listing->saveMeta('listing_documents', '../../probe-private.txt');
        $this->actingAs($owner);

        Livewire::test(SellerOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('listingDocuments', '../../probe-private.txt')
            ->call('deleteListingDocument');

        Storage::disk($this->privateDisk)->assertExists('probe-private.txt');
        $this->assertAllVictimFilesSurvive();
    }

    public function test_a_malformed_persisted_collection_entry_is_refused_not_normalized(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner);
        $listing->saveMeta('property_photos', ['../../probe.txt']);
        $this->actingAs($owner);

        Livewire::test(SellerOfferListing::class)
            ->set('listingId', $listing->id)
            ->set('propertyPhotos', ['../../probe.txt'])
            ->call('deletePropertyPhoto', 0);

        Storage::disk($this->publicDisk)->assertExists('probe.txt');
        $this->assertAllVictimFilesSurvive();
    }
}
