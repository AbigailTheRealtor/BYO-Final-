<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction as LandlordHireComponent;
use App\Http\Livewire\TenantAgentAuction as MultiRoleHireComponent;
use App\Http\Livewire\TenantAgentAuctionEdit as MultiRoleHireEditComponent;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * S4 — Hire Agent media deletion: the target comes from the owned record, not from the client.
 *
 * TWO SEPARATE QUESTIONS, AND THIS FILE KEEPS THEM SEPARATE.
 *
 *   1. Does the caller own the listing row?      (ownership — S1/S3, PR #61 and PR #68)
 *   2. Is the deleted file the one on that row?  (path authority — this milestone)
 *
 * An owner-scoped row lookup answers only the first. Every Hire Agent delete built its storage
 * path as `'auction/images/' . $this->photo`, and `$this->photo` is a public Livewire property,
 * so a caller who legitimately owned SOME listing could still name any file as the target.
 *
 * WHY THE TRAVERSAL CASES REACH REAL PATHS. Flysystem 1.x collapses `..` with array_pop and only
 * raises once the stack empties, so against a two-segment prefix `../../probe.txt` resolves to
 * `probe.txt` — the public disk root — rather than being rejected. The fixtures below assert on
 * the files themselves for that reason: asserting "no exception" would pass while a file was
 * being deleted.
 *
 * Storage::fake('public') throughout — no real media is touched.
 */
class HireAgentMediaDeleteOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private const VICTIM_PHOTO = 'victim-photo.jpg';
    private const VICTIM_VIDEO = 'victim-video.mp4';
    private const OWNER_PHOTO  = 'owner-photo.jpg';
    private const OWNER_VIDEO  = 'owner-video.mp4';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // The three files every attack case tries to reach, each in a different position
        // relative to the 'auction/images/' prefix the delete methods use.
        Storage::disk('public')->put('auction/images/' . self::VICTIM_PHOTO, 'victim photo bytes');
        Storage::disk('public')->put('auction/videos/' . self::VICTIM_VIDEO, 'victim video bytes');
        Storage::disk('public')->put('probe.txt', 'public disk root file');
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    private function sellerListing(User $owner, string $photo = null, string $video = null): SellerAgentAuction
    {
        $listing = SellerAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Listing',
            'address'     => '1 Any Way',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        // Required by the edit component: loadAuctionData() overwrites $this->user_type from this
        // meta on mount, and the delete methods resolve their model class from it. A fixture
        // without it mounts into an empty user_type and fails on "Invalid user_type" rather than
        // on anything this test is about.
        $listing->saveMeta('user_type', 'seller');
        $listing->saveMeta('workflow_type', 'hire_agent');
        if ($photo !== null) {
            $listing->saveMeta('photo', $photo);
        }
        if ($video !== null) {
            $listing->saveMeta('video', $video);
        }

        return $listing->fresh();
    }

    private function landlordListing(User $owner, string $photo = null): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::forceCreate([
            'user_id'  => $owner->id,
            'title'    => 'Listing',
            'is_draft' => false,
        ]);
        if ($photo !== null) {
            $listing->saveMeta('photo', $photo);
        }

        return $listing->fresh();
    }

    /** Every file the attacks aim at must still be on disk. */
    private function assertAllTargetFilesSurvive(): void
    {
        Storage::disk('public')->assertExists('auction/images/' . self::VICTIM_PHOTO);
        Storage::disk('public')->assertExists('auction/videos/' . self::VICTIM_VIDEO);
        Storage::disk('public')->assertExists('probe.txt');
    }

    private function multiRoleForm(string $userType)
    {
        return Livewire::test(MultiRoleHireComponent::class, ['user_type' => $userType])
            ->set('user_type', $userType)
            ->set('service_type', 'limited_service');
    }

    /**
     * The three tampering payloads, and what each would have reached before the fix.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public function tamperedPathProvider(): array
    {
        return [
            // A — a sibling file in the same directory: another listing's photo.
            'cross-listing filename'   => [self::VICTIM_PHOTO, 'auction/images/' . self::VICTIM_PHOTO],
            // B — one level up and back down: crosses into the video directory.
            'directory crossing'       => ['../videos/' . self::VICTIM_VIDEO, 'auction/videos/' . self::VICTIM_VIDEO],
            // C — two levels up: the public disk root itself.
            'public root traversal'    => ['../../probe.txt', 'probe.txt'],
        ];
    }

    // =====================================================================
    // A/B/C — TenantAgentAuction (create), photo
    // =====================================================================

    /** @dataProvider tamperedPathProvider */
    public function test_multi_role_delete_photo_ignores_a_tampered_photo_property(string $tampered, string $targetPath): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, self::OWNER_PHOTO);
        Storage::disk('public')->put('auction/images/' . self::OWNER_PHOTO, 'owner photo bytes');

        $this->actingAs($owner);

        $this->multiRoleForm('seller')
            ->set('listingId', $listing->id)
            ->set('photo', $tampered)
            ->call('deletePhoto');

        Storage::disk('public')->assertExists($targetPath);
        $this->assertAllTargetFilesSurvive();
    }

    /** @dataProvider tamperedPathProvider */
    public function test_multi_role_delete_video_ignores_a_tampered_video_property(string $tampered, string $targetPath): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, null, self::OWNER_VIDEO);
        Storage::disk('public')->put('auction/videos/' . self::OWNER_VIDEO, 'owner video bytes');

        $this->actingAs($owner);

        $this->multiRoleForm('seller')
            ->set('listingId', $listing->id)
            ->set('video', $tampered)
            ->call('deleteVideo');

        Storage::disk('public')->assertExists($targetPath);
        $this->assertAllTargetFilesSurvive();
    }

    // =====================================================================
    // A/B/C — TenantAgentAuctionEdit (the live Seller/Landlord edit component)
    // =====================================================================

    /** @dataProvider tamperedPathProvider */
    public function test_edit_delete_photo_ignores_a_tampered_photo_property(string $tampered, string $targetPath): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, self::OWNER_PHOTO);
        Storage::disk('public')->put('auction/images/' . self::OWNER_PHOTO, 'owner photo bytes');

        $this->actingAs($owner);

        Livewire::test(MultiRoleHireEditComponent::class, ['auctionId' => $listing->id, 'user_type' => 'seller'])
            ->set('photo', $tampered)
            ->call('deletePhoto');

        Storage::disk('public')->assertExists($targetPath);
        $this->assertAllTargetFilesSurvive();
    }

    /** @dataProvider tamperedPathProvider */
    public function test_edit_delete_video_ignores_a_tampered_video_property(string $tampered, string $targetPath): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, null, self::OWNER_VIDEO);

        $this->actingAs($owner);

        Livewire::test(MultiRoleHireEditComponent::class, ['auctionId' => $listing->id, 'user_type' => 'seller'])
            ->set('video', $tampered)
            ->call('deleteVideo');

        Storage::disk('public')->assertExists($targetPath);
        $this->assertAllTargetFilesSurvive();
    }

    // =====================================================================
    // A/B/C — LandLordAgentAuction
    // =====================================================================

    /** @dataProvider tamperedPathProvider */
    public function test_landlord_delete_photo_ignores_a_tampered_photo_property(string $tampered, string $targetPath): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordListing($owner, self::OWNER_PHOTO);
        Storage::disk('public')->put('auction/images/' . self::OWNER_PHOTO, 'owner photo bytes');

        $this->actingAs($owner);

        Livewire::test(LandlordHireComponent::class)
            ->set('listingId', $listing->id)
            ->set('photo', $tampered)
            ->call('deletePhoto');

        Storage::disk('public')->assertExists($targetPath);
        $this->assertAllTargetFilesSurvive();
    }

    // =====================================================================
    // E — positive owner cases
    // =====================================================================

    public function test_owner_can_still_delete_their_own_photo_via_the_multi_role_component(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, self::OWNER_PHOTO);
        Storage::disk('public')->put('auction/images/' . self::OWNER_PHOTO, 'owner photo bytes');

        $this->actingAs($owner);

        $this->multiRoleForm('seller')
            ->set('listingId', $listing->id)
            ->set('photo', self::OWNER_PHOTO)
            ->call('deletePhoto')
            ->assertSet('photo', null)          // UI state stays coherent
            ->assertSet('photoDeleted', true);

        Storage::disk('public')->assertMissing('auction/images/' . self::OWNER_PHOTO);
        $this->assertFalse($listing->fresh()->info('photo'), 'photo meta must be cleared');
        $this->assertAllTargetFilesSurvive();
    }

    public function test_owner_can_still_delete_their_own_video_via_the_multi_role_component(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, null, self::OWNER_VIDEO);
        Storage::disk('public')->put('auction/videos/' . self::OWNER_VIDEO, 'owner video bytes');

        $this->actingAs($owner);

        $this->multiRoleForm('seller')
            ->set('listingId', $listing->id)
            ->set('video', self::OWNER_VIDEO)
            ->call('deleteVideo')
            ->assertSet('video', null)
            ->assertSet('videoDeleted', true);

        Storage::disk('public')->assertMissing('auction/videos/' . self::OWNER_VIDEO);
        $this->assertFalse($listing->fresh()->info('video'), 'video meta must be cleared');
        $this->assertAllTargetFilesSurvive();
    }

    public function test_owner_can_still_delete_their_own_photo_via_the_edit_component(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, self::OWNER_PHOTO);
        Storage::disk('public')->put('auction/images/' . self::OWNER_PHOTO, 'owner photo bytes');

        $this->actingAs($owner);

        Livewire::test(MultiRoleHireEditComponent::class, ['auctionId' => $listing->id, 'user_type' => 'seller'])
            ->set('photo', self::OWNER_PHOTO)
            ->call('deletePhoto')
            ->assertSet('photo', null);

        Storage::disk('public')->assertMissing('auction/images/' . self::OWNER_PHOTO);
        $this->assertFalse($listing->fresh()->info('photo'), 'photo meta must be cleared');
    }

    public function test_owner_can_still_delete_their_own_landlord_photo(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordListing($owner, self::OWNER_PHOTO);
        Storage::disk('public')->put('auction/images/' . self::OWNER_PHOTO, 'owner photo bytes');

        $this->actingAs($owner);

        Livewire::test(LandlordHireComponent::class)
            ->set('listingId', $listing->id)
            ->set('photo', self::OWNER_PHOTO)
            ->call('deletePhoto')
            ->assertSet('photo', null);

        Storage::disk('public')->assertMissing('auction/images/' . self::OWNER_PHOTO);
        $this->assertFalse($listing->fresh()->info('photo'), 'photo meta must be cleared');
    }

    /**
     * The edit component's deleteVideo clears the meta but does not remove the file, because it
     * deletes from 'auction/images' while the save path stores under 'auction/videos'. That
     * mismatch is a PRE-EXISTING functional bug, deliberately not changed by this security work.
     * Asserted here so the behaviour is recorded rather than discovered later, and so the
     * security property — nothing outside the named directory is reachable — is covered.
     */
    public function test_edit_delete_video_clears_meta_and_reaches_nothing_outside_its_directory(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, null, self::OWNER_VIDEO);
        Storage::disk('public')->put('auction/videos/' . self::OWNER_VIDEO, 'owner video bytes');

        $this->actingAs($owner);

        Livewire::test(MultiRoleHireEditComponent::class, ['auctionId' => $listing->id, 'user_type' => 'seller'])
            ->set('video', self::OWNER_VIDEO)
            ->call('deleteVideo')
            ->assertSet('video', null);

        $this->assertFalse($listing->fresh()->info('video'), 'video meta must be cleared');
        $this->assertAllTargetFilesSurvive();
    }

    // =====================================================================
    // F — listingId / auctionId tampering
    // =====================================================================

    public function test_landlord_delete_photo_refuses_a_tampered_listing_id(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->landlordListing($victim, self::VICTIM_PHOTO);

        $this->actingAs($attacker);
        $this->withoutExceptionHandling();

        try {
            Livewire::test(LandlordHireComponent::class)
                ->set('listingId', $listing->id)
                ->set('photo', self::VICTIM_PHOTO)
                ->call('deletePhoto');
            $this->fail('Expected a 403 — the unauthorized deletion was allowed through.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(self::VICTIM_PHOTO, $listing->fresh()->info('photo'), 'victim meta must survive');
        $this->assertAllTargetFilesSurvive();
    }

    public function test_multi_role_delete_photo_refuses_a_tampered_listing_id(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->sellerListing($victim, self::VICTIM_PHOTO);

        $this->actingAs($attacker);
        $this->withoutExceptionHandling();

        try {
            $this->multiRoleForm('seller')
                ->set('listingId', $listing->id)
                ->set('photo', self::VICTIM_PHOTO)
                ->call('deletePhoto');
            $this->fail('Expected a 403 — the unauthorized deletion was allowed through.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(self::VICTIM_PHOTO, $listing->fresh()->info('photo'), 'victim meta must survive');
        $this->assertAllTargetFilesSurvive();
    }

    public function test_edit_delete_photo_refuses_a_foreign_auction_id(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->sellerListing($victim, self::VICTIM_PHOTO);

        $this->actingAs($attacker);

        // mount() is owner-scoped and throws for a foreign id, so the component is driven with a
        // listing the attacker owns and the id is tampered afterwards — the reachable shape.
        $own = $this->sellerListing($attacker, self::OWNER_PHOTO);

        Livewire::test(MultiRoleHireEditComponent::class, ['auctionId' => $own->id, 'user_type' => 'seller'])
            ->set('auctionId', $listing->id)
            ->set('photo', self::VICTIM_PHOTO)
            ->call('deletePhoto');

        $this->assertSame(self::VICTIM_PHOTO, $listing->fresh()->info('photo'), 'victim meta must survive');
        $this->assertAllTargetFilesSurvive();
    }

    // =====================================================================
    // G — malformed PERSISTED meta
    // =====================================================================

    /**
     * The persisted value is not automatically trustworthy: save paths contain a
     * `saveMeta('photo', $this->photo)` branch for the already-a-string case, so an attacker can
     * store path syntax on their OWN listing. Reading from the record is therefore necessary but
     * not sufficient — the value must also be validated, and a malformed one must be refused
     * rather than normalized into something deletable.
     */
    public function test_a_malformed_persisted_photo_path_is_refused_not_normalized(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, '../../probe.txt');

        $this->actingAs($owner);

        $this->multiRoleForm('seller')
            ->set('listingId', $listing->id)
            ->call('deletePhoto');

        Storage::disk('public')->assertExists('probe.txt');
        $this->assertAllTargetFilesSurvive();
        $this->assertFalse($listing->fresh()->info('photo'), 'the unusable reference is still cleared');
    }

    public function test_a_malformed_persisted_video_path_is_refused_not_normalized(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerListing($owner, null, '../images/' . self::VICTIM_PHOTO);

        $this->actingAs($owner);

        $this->multiRoleForm('seller')
            ->set('listingId', $listing->id)
            ->call('deleteVideo');

        $this->assertAllTargetFilesSurvive();
        $this->assertFalse($listing->fresh()->info('video'), 'the unusable reference is still cleared');
    }

    public function test_a_malformed_persisted_landlord_photo_path_is_refused(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordListing($owner, '../../probe.txt');

        $this->actingAs($owner);

        Livewire::test(LandlordHireComponent::class)
            ->set('listingId', $listing->id)
            ->call('deletePhoto');

        Storage::disk('public')->assertExists('probe.txt');
        $this->assertAllTargetFilesSurvive();
    }
}
