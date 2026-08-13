<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Controllers\FileUploadHandler;
use Livewire\FileUploadConfiguration;
use Livewire\Livewire;
use Livewire\Testing\TestableLivewire;
use Tests\TestCase;

/**
 * S6 — Offer Listing photo ORDER and UPLOAD: the listing comes from the owner, the filenames come
 * from the persisted record.
 *
 * PR #71 (S5) closed the DELETE paths in these same four components. It deliberately did not touch
 * the four mutation methods audited here, which kept the pre-S5 shape:
 *
 *     $auction = SellerAgentAuctionModel::findOrFail($this->listingId);   // no user_id predicate
 *     $auction->saveMeta('property_photos', $this->propertyPhotos);       // client-supplied array
 *
 * TWO INDEPENDENT AXES, and closing one does not close the other:
 *
 *   1. ROW OWNERSHIP        — may this caller write THIS listing? An unscoped findOrFail() answers
 *                             "does the row exist", which is a different question.
 *   2. VALUE AUTHORITY      — which filenames belong to it? `$this->propertyPhotos` is a PUBLIC
 *                             Livewire property, so the client supplies both the candidate list and
 *                             the list it is checked against. reorderPhotos()'s in_array() guard is
 *                             circular for exactly that reason.
 *
 * WHY EVERY EXPLOIT HERE IS SENT AS ONE REQUEST
 * ---------------------------------------------
 * Livewire v2 runs its middleware in a fixed order (LivewireServiceProvider::registerHydrationMiddleware):
 *
 *     CallHydrationHooks        ← the component's hydrate() ownership guard runs here
 *     PerformDataBindingUpdates ← every syncInput in the request is applied here
 *     PerformActionCalls        ← every callMethod in the request runs here
 *
 * The guard therefore inspects the value that arrived in the SIGNED serverMemo, two stages before
 * the client's substitution lands. `updates` is not covered by the payload checksum — it is how
 * legitimate input arrives — so an attacker keeps their own id in serverMemo (checksum intact) and
 * rewrites it afterwards, in the same request.
 *
 * The Livewire test harness sends ONE message per ->set()/->call(), so the usual
 * ->set('listingId', $victim)->call('reorderPhotos') chain does NOT reproduce this: the tampered id
 * is dehydrated into serverMemo and the NEXT request's hydrate() rejects it. Such a test passes
 * against vulnerable code — it proves hydrate() works, not that the action is safe. exploit() below
 * posts the real thing: one request carrying both the syncInput and the callMethod.
 *
 * @see OfferListingMediaDeleteOwnershipTest — the S5 delete-path guarantee, which must stay green.
 */
class OfferListingPhotoOrderOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private const VICTIM_A = 'victim-a.jpg';
    private const VICTIM_B = 'victim-b.jpg';
    private const OWNER_A  = 'owner-a.jpg';
    private const OWNER_B  = 'owner-b.jpg';

    /** Filenames the attacker tries to graft onto a listing they do not own. */
    private const FOREIGN_X = 'foreign-x.jpg';
    private const FOREIGN_Y = 'foreign-y.jpg';

    private string $publicDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $disks = app(\App\Support\Storage\ListingStorageDisks::class);
        $this->publicDisk = $disks->publicDiskName();

        Storage::fake($this->publicDisk);
        Storage::fake(FileUploadConfiguration::disk());
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Storage::disk($this->publicDisk)->put('auction/images/' . self::VICTIM_A, 'victim a');
        Storage::disk($this->publicDisk)->put('auction/images/' . self::VICTIM_B, 'victim b');
        Storage::disk($this->publicDisk)->put('auction/images/' . self::FOREIGN_X, 'foreign x');
        Storage::disk($this->publicDisk)->put('auction/images/' . self::FOREIGN_Y, 'foreign y');
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    private function sellerListing(User $owner, array $photos): SellerAgentAuction
    {
        $l = SellerAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'L', 'address' => '1 Any Way',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $l->saveMeta('property_photos', $photos);

        return $l->fresh();
    }

    private function landlordListing(User $owner, array $photos): LandlordAgentAuction
    {
        $l = LandlordAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'L', 'is_draft' => false,
        ]);
        $l->saveMeta('property_photos', $photos);

        return $l->fresh();
    }

    /** The persisted collection, decoded. saveMeta() json_encodes arrays; info() returns the string. */
    private function persistedPhotos($listing): array
    {
        $stored = $listing->fresh()->info('property_photos');
        $decoded = is_string($stored) ? json_decode($stored, true) : $stored;

        return is_array($decoded) ? array_values($decoded) : [];
    }

    // =====================================================================
    // The single-request exploit harness
    // =====================================================================

    /** One `syncInput` entry, as Livewire's JavaScript would send it. */
    private function syncInput(string $name, $value): array
    {
        return ['type' => 'syncInput', 'payload' => ['id' => 'sync', 'name' => $name, 'value' => $value]];
    }

    /** One `callMethod` entry, as Livewire's JavaScript would send it. */
    private function callMethod(string $method, array $params = []): array
    {
        return ['type' => 'callMethod', 'payload' => ['id' => 'call', 'method' => $method, 'params' => $params, 'ref' => null]];
    }

    /**
     * POST one Livewire message carrying SEVERAL updates, which the harness cannot express.
     *
     * The component's own serverMemo is replayed verbatim, so its checksum stays valid and the
     * hydrate() guard sees the attacker's own (legitimate) identifiers. Everything in $updates is
     * applied afterwards, exactly as PerformDataBindingUpdates / PerformActionCalls would.
     *
     * callEndpoint() disables Laravel's exception handling, so an abort() surfaces as a thrown
     * HttpException rather than a 403 response. Both are a refusal; the assertions that matter are
     * on the victim's stored state, never on the status.
     */
    private function exploit(TestableLivewire $component, array $updates): void
    {
        try {
            $component->callEndpoint('POST', '/livewire/message/' . $component->componentName, [
                'fingerprint' => $component->payload['fingerprint'],
                'serverMemo'  => $component->payload['serverMemo'],
                'updates'     => $updates,
            ]);
        } catch (\Throwable $e) {
            // A fail-closed refusal is acceptable. A silent foreign write is not, and only the
            // post-conditions below can tell the two apart.
        }
    }

    /**
     * The four components, each with the identifier its photo methods actually read.
     *
     * The identifier is NOT uniform, and that asymmetry is itself a finding: SellerOfferListingEdit
     * guards `auctionId ?: listingId` in hydrate() but writes using `listingId` alone, so leaving
     * auctionId untouched walks straight past the guard even before the ordering issue applies.
     * LandlordOfferListingEdit reads `auctionId ?? listingId`, so auctionId is its lever.
     *
     * @return array<string,array{0:string,1:string,2:string,3:bool}>
     *         [component class, role, tampered property, needs an owned listing to mount]
     */
    public function componentProvider(): array
    {
        return [
            'seller create'   => [SellerOfferListing::class,       'seller',   'listingId', false],
            'seller edit'     => [SellerOfferListingEdit::class,   'seller',   'listingId', true],
            'landlord create' => [LandlordOfferListing::class,     'landlord', 'listingId', false],
            'landlord edit'   => [LandlordOfferListingEdit::class, 'landlord', 'auctionId', true],
        ];
    }

    /** Mount the component as the attacker would legitimately reach it. */
    private function mountAs(string $class, string $role, bool $needsOwnListing, User $attacker): TestableLivewire
    {
        if (! $needsOwnListing) {
            return Livewire::test($class);
        }

        $own = $role === 'seller'
            ? $this->sellerListing($attacker, [self::OWNER_A, self::OWNER_B])
            : $this->landlordListing($attacker, [self::OWNER_A, self::OWNER_B]);

        return Livewire::test($class, ['auctionId' => $own->id]);
    }

    private function victimListingFor(string $role, User $victim)
    {
        return $role === 'seller'
            ? $this->sellerListing($victim, [self::VICTIM_A, self::VICTIM_B])
            : $this->landlordListing($victim, [self::VICTIM_A, self::VICTIM_B]);
    }

    // =====================================================================
    // A / B / C / D / E — cross-user metadata writes
    // =====================================================================

    /**
     * A — reorderPhotos() may not rewrite a listing the caller does not own.
     *
     * The attacker supplies both the collection and the requested order, so a successful write
     * replaces the victim's photo set outright rather than merely permuting it.
     *
     * @dataProvider componentProvider
     */
    public function test_reorder_photos_cannot_mutate_a_foreign_listing(string $class, string $role, string $idProp, bool $needsOwn): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $this->actingAs($attacker);

        $victimListing = $this->victimListingFor($role, $victim);
        $component     = $this->mountAs($class, $role, $needsOwn, $attacker);

        $this->exploit($component, [
            $this->syncInput($idProp, $victimListing->id),
            $this->syncInput('propertyPhotos', [self::FOREIGN_X, self::FOREIGN_Y]),
            $this->callMethod('reorderPhotos', [[self::FOREIGN_Y, self::FOREIGN_X]]),
        ]);

        $this->assertSame(
            [self::VICTIM_A, self::VICTIM_B],
            $this->persistedPhotos($victimListing),
            "[{$class}] reorderPhotos rewrote a listing the caller does not own"
        );
    }

    /**
     * B — movePhotoUp() may not rewrite a foreign listing.
     *
     * movePhotoUp makes no membership check at all: it swaps two slots of the client-supplied array
     * and persists the result, so the swap is only the pretext for the write.
     *
     * @dataProvider componentProvider
     */
    public function test_move_photo_up_cannot_mutate_a_foreign_listing(string $class, string $role, string $idProp, bool $needsOwn): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $this->actingAs($attacker);

        $victimListing = $this->victimListingFor($role, $victim);
        $component     = $this->mountAs($class, $role, $needsOwn, $attacker);

        $this->exploit($component, [
            $this->syncInput($idProp, $victimListing->id),
            $this->syncInput('propertyPhotos', [self::FOREIGN_X, self::FOREIGN_Y]),
            $this->callMethod('movePhotoUp', [1]),
        ]);

        $this->assertSame(
            [self::VICTIM_A, self::VICTIM_B],
            $this->persistedPhotos($victimListing),
            "[{$class}] movePhotoUp rewrote a listing the caller does not own"
        );
    }

    /**
     * C — movePhotoDown() may not rewrite a foreign listing.
     *
     * @dataProvider componentProvider
     */
    public function test_move_photo_down_cannot_mutate_a_foreign_listing(string $class, string $role, string $idProp, bool $needsOwn): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $this->actingAs($attacker);

        $victimListing = $this->victimListingFor($role, $victim);
        $component     = $this->mountAs($class, $role, $needsOwn, $attacker);

        $this->exploit($component, [
            $this->syncInput($idProp, $victimListing->id),
            $this->syncInput('propertyPhotos', [self::FOREIGN_X, self::FOREIGN_Y]),
            $this->callMethod('movePhotoDown', [0]),
        ]);

        $this->assertSame(
            [self::VICTIM_A, self::VICTIM_B],
            $this->persistedPhotos($victimListing),
            "[{$class}] movePhotoDown rewrote a listing the caller does not own"
        );
    }

    /**
     * D — the ordering itself: a guard that ran during hydration cannot protect the action.
     *
     * The attacker holds a listing they genuinely own, so hydrate() is satisfied by the signed
     * serverMemo. The substitution is applied afterwards, inside the same request. This is the same
     * mechanism as A–C, asserted on its own so a future refactor that "fixes" this by moving
     * hydrate() later — rather than by re-resolving at the action — still fails here.
     */
    public function test_post_hydrate_tampering_cannot_bypass_action_time_ownership(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $this->actingAs($attacker);

        $victimListing   = $this->sellerListing($victim, [self::VICTIM_A, self::VICTIM_B]);
        $attackerListing = $this->sellerListing($attacker, [self::OWNER_A, self::OWNER_B]);

        // Mounted on the attacker's OWN listing: hydrate() has a legitimate id to approve.
        $component = Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $attackerListing->id]);

        $this->exploit($component, [
            $this->syncInput('listingId', $victimListing->id),
            $this->syncInput('propertyPhotos', [self::FOREIGN_X]),
            $this->callMethod('reorderPhotos', [[self::FOREIGN_X]]),
        ]);

        $this->assertSame(
            [self::VICTIM_A, self::VICTIM_B],
            $this->persistedPhotos($victimListing),
            'A hydrate-time guard was accepted as authorization for an action-time write'
        );
        $this->assertSame(
            [self::OWNER_A, self::OWNER_B],
            $this->persistedPhotos($attackerListing),
            "The attacker's own listing must also be untouched by a refused request"
        );
    }

    /**
     * E — Seller Edit's guard and its sink read DIFFERENT properties.
     *
     * hydrate() checks `auctionId ?: listingId`. With auctionId left at the attacker's own listing
     * the `?:` short-circuits and listingId is never examined on any request — while all four photo
     * methods write using listingId alone. This bypass does not depend on the ordering at all, so
     * an action-time resolver must consult the same expression the guard does.
     */
    public function test_seller_edit_own_auction_id_with_foreign_listing_id_cannot_bypass_ownership(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $this->actingAs($attacker);

        $victimListing   = $this->sellerListing($victim, [self::VICTIM_A, self::VICTIM_B]);
        $attackerListing = $this->sellerListing($attacker, [self::OWNER_A, self::OWNER_B]);

        $component = Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $attackerListing->id]);

        // auctionId deliberately NOT tampered — it stays valid, and satisfies the guard.
        $this->exploit($component, [
            $this->syncInput('listingId', $victimListing->id),
            $this->syncInput('propertyPhotos', [self::FOREIGN_X, self::FOREIGN_Y]),
            $this->callMethod('movePhotoUp', [1]),
        ]);

        $this->assertSame(
            [self::VICTIM_A, self::VICTIM_B],
            $this->persistedPhotos($victimListing),
            'A valid auctionId was accepted as authorization for a write aimed at listingId'
        );
    }

    // =====================================================================
    // F — value authority, independent of row ownership
    // =====================================================================

    /**
     * F — a filename absent from the persisted collection may not be introduced by reordering.
     *
     * The caller here OWNS the listing, so row ownership is satisfied and cannot be what refuses
     * this. reorderPhotos() checks membership against `$this->propertyPhotos`, which the same
     * request supplied, so the check confirms only that the attacker is consistent with themselves.
     * The persisted collection is the sole authority over which filenames belong to a listing.
     */
    public function test_a_foreign_filename_cannot_be_introduced_through_reorder(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $listing   = $this->sellerListing($owner, [self::OWNER_A, self::OWNER_B]);
        $component = Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $listing->id]);

        $this->exploit($component, [
            $this->syncInput('propertyPhotos', [self::FOREIGN_X, self::OWNER_A, self::OWNER_B]),
            $this->callMethod('reorderPhotos', [[self::FOREIGN_X, self::OWNER_B, self::OWNER_A]]),
        ]);

        $persisted = $this->persistedPhotos($listing);

        $this->assertNotContains(
            self::FOREIGN_X,
            $persisted,
            'A filename that was never part of the listing entered its collection through reordering'
        );
        $this->assertEqualsCanonicalizing(
            [self::OWNER_A, self::OWNER_B],
            $persisted,
            'Reordering must permute the persisted set, never change its membership'
        );
    }

    /**
     * F(ii) — the same guarantee for the move operations, which have no membership check at all.
     */
    public function test_a_foreign_filename_cannot_be_introduced_through_move(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $listing   = $this->landlordListing($owner, [self::OWNER_A, self::OWNER_B]);
        $component = Livewire::test(LandlordOfferListingEdit::class, ['auctionId' => $listing->id]);

        $this->exploit($component, [
            $this->syncInput('propertyPhotos', [self::FOREIGN_X, self::FOREIGN_Y]),
            $this->callMethod('movePhotoUp', [1]),
        ]);

        $persisted = $this->persistedPhotos($listing);

        $this->assertNotContains(self::FOREIGN_X, $persisted, 'A foreign filename entered the collection through movePhotoUp');
        $this->assertNotContains(self::FOREIGN_Y, $persisted, 'A foreign filename entered the collection through movePhotoUp');
        $this->assertEqualsCanonicalizing([self::OWNER_A, self::OWNER_B], $persisted, 'Membership must be unchanged by a move');
    }

    // =====================================================================
    // G — upload attachment
    // =====================================================================

    /**
     * Put real files in Livewire's temporary-upload disk and return their hashed names, which is
     * what the browser holds by the time finishUpload() is called.
     */
    private function temporaryUploadHashes(array $files): array
    {
        return collect((new FileUploadHandler)->validateAndStore($files, FileUploadConfiguration::disk()))->all();
    }

    /**
     * G — an attacker's upload may not be attached to a victim's listing.
     *
     * updatedNewPropertyPhotos() cannot be invoked directly — Livewire's PROTECTED_METHODS blocks
     * `updated*` — but it is not thereby unreachable: finishUpload() calls syncInput(), which fires
     * the hook. So the exploit is a legitimate upload handshake with the listing id swapped
     * underneath it, in one request.
     *
     * Asserted on the victim's stored collection AND on the disk, because the method stores the
     * file before it resolves any listing: a fix that refuses the metadata write but keeps writing
     * the file leaves an orphan.
     */
    public function test_an_upload_cannot_be_attached_to_a_foreign_listing(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $this->actingAs($attacker);

        $victimListing = $this->sellerListing($victim, [self::VICTIM_A, self::VICTIM_B]);
        $component     = Livewire::test(SellerOfferListing::class);

        $hashes = $this->temporaryUploadHashes([UploadedFile::fake()->image('attacker.jpg')]);
        $before = Storage::disk($this->publicDisk)->files('auction/images');

        $this->exploit($component, [
            $this->syncInput('listingId', $victimListing->id),
            $this->callMethod('finishUpload', ['newPropertyPhotos', $hashes, true]),
        ]);

        $this->assertSame(
            [self::VICTIM_A, self::VICTIM_B],
            $this->persistedPhotos($victimListing),
            "An attacker's upload was attached to a listing they do not own"
        );

        $this->assertSame(
            $before,
            Storage::disk($this->publicDisk)->files('auction/images'),
            'A refused upload left a newly stored file orphaned in public storage'
        );
    }

    // =====================================================================
    // H / I / J / K — the legitimate flows these guards must not break
    // =====================================================================

    /** H — the owner can still reorder, and the requested order is honoured. */
    public function test_the_owner_can_still_reorder_their_photos(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $listing = $this->sellerListing($owner, [self::OWNER_A, self::OWNER_B]);

        Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $listing->id])
            ->call('reorderPhotos', [self::OWNER_B, self::OWNER_A]);

        $this->assertSame(
            [self::OWNER_B, self::OWNER_A],
            $this->persistedPhotos($listing),
            'The owner\'s requested order was not persisted'
        );
    }

    /** I — move up and move down still work for the owner, on both roles. */
    public function test_the_owner_can_still_move_photos_up_and_down(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $seller = $this->sellerListing($owner, [self::OWNER_A, self::OWNER_B]);
        Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $seller->id])
            ->call('movePhotoUp', 1);

        $this->assertSame(
            [self::OWNER_B, self::OWNER_A],
            $this->persistedPhotos($seller),
            'movePhotoUp did not persist for the owner'
        );

        $landlord = $this->landlordListing($owner, [self::OWNER_A, self::OWNER_B]);
        Livewire::test(LandlordOfferListingEdit::class, ['auctionId' => $landlord->id])
            ->call('movePhotoDown', 0);

        $this->assertSame(
            [self::OWNER_B, self::OWNER_A],
            $this->persistedPhotos($landlord),
            'movePhotoDown did not persist for the owner'
        );
    }

    /** J — the owner can still upload to their own listing, and it persists. */
    public function test_the_owner_can_still_upload_to_their_own_listing(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $listing = $this->sellerListing($owner, [self::OWNER_A]);
        $before  = Storage::disk($this->publicDisk)->files('auction/images');

        Livewire::test(SellerOfferListingEdit::class, ['auctionId' => $listing->id])
            ->set('newPropertyPhotos', [UploadedFile::fake()->image('new.jpg')]);

        $persisted = $this->persistedPhotos($listing);

        $this->assertContains(self::OWNER_A, $persisted, 'The existing photo was dropped by an upload');
        $this->assertCount(2, $persisted, 'The uploaded photo was not appended to the owner\'s collection');
        $this->assertCount(
            count($before) + 1,
            Storage::disk($this->publicDisk)->files('auction/images'),
            'The uploaded file was not stored'
        );
        Storage::disk($this->publicDisk)->assertExists('auction/images/' . end($persisted));
    }

    /**
     * K — the create flow has no listing yet, and must keep buffering photos in component state.
     *
     * The guard must treat "no listing" as "nothing to authorize", exactly as
     * ResolvesOwnedAuction::userCanManageAuction() already does for an empty id. Requiring a
     * persisted listing here would break Create Offer Listing for every user.
     */
    public function test_the_create_flow_still_buffers_photos_with_no_persisted_listing(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $before = Storage::disk($this->publicDisk)->files('auction/images');

        $component = Livewire::test(SellerOfferListing::class)
            ->set('newPropertyPhotos', [UploadedFile::fake()->image('draft.jpg')]);

        $buffered = $component->get('propertyPhotos');

        $this->assertCount(
            1,
            $buffered,
            'The create flow must buffer an uploaded photo in component state'
        );
        $this->assertCount(
            count($before) + 1,
            Storage::disk($this->publicDisk)->files('auction/images'),
            'The create flow must still store the uploaded file'
        );
        Storage::disk($this->publicDisk)->assertExists('auction/images/' . $buffered[0]);
        $this->assertSame(0, SellerAgentAuction::where('user_id', $owner->id)->count(), 'No listing may be created by an upload');
    }
}
