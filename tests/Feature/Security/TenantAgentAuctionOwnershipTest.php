<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\TenantAgentAuction as MultiRoleHireComponent;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction as TenantAgentAuctionModel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Ownership regression tests for the LIVE multi-role Hire Agent wizard.
 *
 * WHY THIS FILE EXISTS SEPARATELY FROM SellerLandlordHireAgentOwnershipTest.
 * That file covers the two ROLE-SPECIFIC create components hardened by PR #61
 * (HireSellerAgent\SellerAgentAuction, HireLandLordAgent\LandLordAgentAuction).
 * It does not cover App\Http\Livewire\TenantAgentAuction, which is a THIRD live
 * create surface serving all four roles from one class at
 * /hire/agent/auction/{user_type}. PR #61 hardened its siblings and left it
 * alone, so the shape PR #61 was written to kill survived here.
 *
 * THE SHAPE. store() resolved the row to write with an unscoped
 * `$auctionClass::find($this->listingId)` and then assigned
 * `user_id = Auth::id()`. BOTH inputs are public Livewire properties:
 *
 *   · $listingId  picks the ROW
 *   · $user_type  picks the TABLE (via the match that builds $auctionClass)
 *
 * Livewire 2 applies client `syncInput` updates to any public property with no
 * allowlist, so an authenticated attacker controls both. Pointing them at a
 * victim's listing overwrote its contents AND transferred the row to the
 * attacker — across any of the four auction tables.
 *
 * WHAT EACH ATTACK ASSERTS. Two things, deliberately split across two tests per
 * vector rather than combined:
 *
 *   1. the victim's row is untouched — owner, title and metadata unchanged;
 *   2. the refusal surfaces as a 403 rather than a silent no-op.
 *
 * They are separate because they fail differently and the difference is the
 * evidence. A combined test that asserts the 403 first would abort before
 * reaching the damage assertions, and "no 403 was thrown" reads identically
 * whether the attack was blocked some other way or succeeded completely. The
 * intact-assertions are the ones that name the breach.
 *
 * ON THE 403 TESTS. store(), saveDraft(), deletePhoto() and deleteVideo() each
 * wrap their body in `catch (\Exception)`, and Symfony's HttpException extends
 * \Exception — so a guard placed INSIDE those try blocks would be swallowed into
 * a flash message and the caller would see success. The guards therefore sit
 * ahead of the try, and these tests are what hold them there.
 */
class TenantAgentAuctionOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private const VICTIM_TITLE   = 'Victim Multi-Role Listing';
    private const VICTIM_ADDRESS = '1 Victim Way';
    private const ATTACKER_TITLE = 'Attacker Supplied Title';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    private function sellerVictimListing(User $victim): SellerAgentAuction
    {
        $listing = SellerAgentAuction::forceCreate([
            'user_id'     => $victim->id,
            'title'       => self::VICTIM_TITLE,
            'address'     => self::VICTIM_ADDRESS,
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $listing->saveMeta('workflow_type', 'hire_agent');
        $listing->saveMeta('first_name', 'Victoria');
        $listing->saveMeta('photo', 'victim-photo.jpg');
        $listing->saveMeta('video', 'victim-video.mp4');

        return $listing->fresh();
    }

    private function landlordVictimListing(User $victim): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::forceCreate([
            'user_id'  => $victim->id,
            'title'    => self::VICTIM_TITLE,
            'is_draft' => false,
        ]);
        $listing->saveMeta('workflow_type', 'hire_agent');
        $listing->saveMeta('first_name', 'Victoria');
        $listing->saveMeta('address', self::VICTIM_ADDRESS);
        $listing->saveMeta('photo', 'victim-photo.jpg');
        $listing->saveMeta('video', 'victim-video.mp4');

        return $listing->fresh();
    }

    private function assertSellerVictimIntact(SellerAgentAuction $listing, User $victim): void
    {
        $fresh = $listing->fresh();

        $this->assertNotNull($fresh, 'Victim listing must still exist');
        $this->assertSame($victim->id, $fresh->user_id, 'Victim must still own the listing — no ownership transfer');
        $this->assertSame(self::VICTIM_TITLE, $fresh->title, 'Victim listing title must be unchanged');
        $this->assertSame('Victoria', $fresh->info('first_name'), 'Victim metadata must be unchanged');
    }

    private function assertLandlordVictimIntact(LandlordAgentAuction $listing, User $victim): void
    {
        $fresh = $listing->fresh();

        $this->assertNotNull($fresh, 'Victim listing must still exist');
        $this->assertSame($victim->id, $fresh->user_id, 'Victim must still own the listing — no ownership transfer');
        $this->assertSame(self::VICTIM_TITLE, $fresh->title, 'Victim listing title must be unchanged');
        $this->assertSame('Victoria', $fresh->info('first_name'), 'Victim metadata must be unchanged');
    }

    /**
     * The minimum submission the wizard accepts.
     *
     * limited_service on purpose: validateOnlyFilledFields() adds a long list of
     * role-specific rules for tenant/landlord ONLY under full_service, and none
     * of them bear on ownership. Keeping the payload at the seven universal rules
     * means these tests fail on the authorization boundary rather than on an
     * unrelated required field.
     */
    private function formWithValidData(string $userType)
    {
        return Livewire::test(MultiRoleHireComponent::class, ['user_type' => $userType])
            ->set('user_type', $userType)
            ->set('service_type', 'limited_service')
            ->set('listing_title', self::ATTACKER_TITLE)
            ->set('working_with_agent', 'No')
            ->set('listing_date', now()->toDateString())
            ->set('expiration_date', now()->addDays(30)->toDateString())
            ->set('auction_type', 'Open Bidding')
            ->set('address', '123 Main St');
    }

    private function assertForbids(callable $operation): void
    {
        $this->withoutExceptionHandling();

        try {
            $operation();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode(), 'Ownership failures must surface as 403');

            return;
        }

        $this->fail('Expected a 403 HttpException — the unauthorized operation was allowed through.');
    }

    // =====================================================================
    // A — store() / publish takeover
    // =====================================================================

    public function test_attacker_cannot_take_over_a_victim_seller_listing_via_store(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $this->formWithValidData('seller')
            ->set('listingId', $listing->id)
            ->call('store');

        $this->assertSellerVictimIntact($listing, $victim);
    }

    public function test_attacker_cannot_take_over_a_victim_landlord_listing_via_store(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->landlordVictimListing($victim);

        $this->actingAs($attacker);

        $this->formWithValidData('landlord')
            ->set('listingId', $listing->id)
            ->call('store');

        $this->assertLandlordVictimIntact($listing, $victim);
    }

    public function test_store_refuses_a_foreign_listing_with_a_403(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $this->assertForbids(function () use ($listing) {
            $this->formWithValidData('seller')
                ->set('listingId', $listing->id)
                ->call('store');
        });

        $this->assertSellerVictimIntact($listing, $victim);
    }

    // =====================================================================
    // B/C — media deletion
    // =====================================================================

    public function test_attacker_cannot_delete_a_victim_seller_listing_photo(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $this->formWithValidData('seller')
            ->set('listingId', $listing->id)
            ->set('photo', 'victim-photo.jpg')
            ->call('deletePhoto');

        $this->assertSame(
            'victim-photo.jpg',
            $listing->fresh()->info('photo'),
            "Victim's photo meta must survive a foreign deletePhoto"
        );
    }

    public function test_attacker_cannot_delete_a_victim_landlord_listing_photo(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->landlordVictimListing($victim);

        $this->actingAs($attacker);

        $this->formWithValidData('landlord')
            ->set('listingId', $listing->id)
            ->set('photo', 'victim-photo.jpg')
            ->call('deletePhoto');

        $this->assertSame(
            'victim-photo.jpg',
            $listing->fresh()->info('photo'),
            "Victim's photo meta must survive a foreign deletePhoto"
        );
    }

    public function test_attacker_cannot_delete_a_victim_seller_listing_video(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $this->formWithValidData('seller')
            ->set('listingId', $listing->id)
            ->set('video', 'victim-video.mp4')
            ->call('deleteVideo');

        $this->assertSame(
            'victim-video.mp4',
            $listing->fresh()->info('video'),
            "Victim's video meta must survive a foreign deleteVideo"
        );
    }

    // =====================================================================
    // D — public-property tampering
    // =====================================================================

    /**
     * user_type is the second authoritative identifier and the one easy to miss:
     * it does not name a record, it names a TABLE. An attacker who legitimately
     * owns a tenant listing may not reach a seller row by switching it.
     */
    public function test_attacker_cannot_reach_a_victim_listing_by_tampering_user_type(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();

        $victimSeller = $this->sellerVictimListing($victim);

        // The attacker genuinely owns a tenant listing with an id of its own.
        TenantAgentAuctionModel::forceCreate([
            'user_id'  => $attacker->id,
            'title'    => 'Attacker Own Tenant Listing',
            'is_draft' => false,
        ]);

        $this->actingAs($attacker);

        $this->formWithValidData('seller')
            ->set('listingId', $victimSeller->id)
            ->call('store');

        $this->assertSellerVictimIntact($victimSeller, $victim);
    }

    /**
     * saveDraft() reads the previous draft with the same unscoped find() before
     * cloning it forward. It does not write to that row, but it reads its
     * draft_payload_hash and draft_version out of it and records its id as the
     * new draft's parent — so a foreign listingId leaks state and links the
     * attacker's draft to a victim's row.
     */
    public function test_attacker_cannot_link_a_draft_to_a_victim_listing(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();

        // The victim's row must be a DRAFT: saveDraft() only enters the branch that
        // reads draft_payload_hash / draft_version and records parent_draft_id when
        // `$previousDraft->is_draft` is true. A published victim row skips it
        // entirely, so testing against one would pass without probing anything.
        $listing = $this->sellerVictimListing($victim);
        $listing->is_draft = true;
        $listing->save();
        $listing->saveMeta('draft_payload_hash', 'victim-secret-hash');
        $listing->saveMeta('draft_version', '7');
        $listing = $listing->fresh();

        $this->actingAs($attacker);

        $this->formWithValidData('seller')
            ->set('listingId', $listing->id)
            ->call('saveDraft');

        $this->assertSellerVictimIntact($listing, $victim);

        $orphaned = SellerAgentAuction::where('user_id', $attacker->id)->get();
        foreach ($orphaned as $row) {
            $this->assertNotSame(
                (string) $listing->id,
                (string) $row->info('parent_draft_id'),
                "An attacker's draft must not be parented to a victim's listing"
            );
        }
    }

    // =====================================================================
    // Legitimate behaviour must survive the fix
    // =====================================================================

    public function test_owner_can_still_publish_their_own_seller_listing(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerVictimListing($owner);

        $this->actingAs($owner);

        $this->formWithValidData('seller')
            ->set('listingId', $listing->id)
            ->call('store');

        $fresh = $listing->fresh();
        $this->assertSame($owner->id, $fresh->user_id, 'Owner must retain their listing');
        $this->assertSame(self::ATTACKER_TITLE, $fresh->title, 'Owner edit must persist');
    }

    public function test_owner_can_still_publish_their_own_landlord_listing(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->landlordVictimListing($owner);

        $this->actingAs($owner);

        $this->formWithValidData('landlord')
            ->set('listingId', $listing->id)
            ->call('store');

        $fresh = $listing->fresh();
        $this->assertSame($owner->id, $fresh->user_id, 'Owner must retain their listing');
        $this->assertSame(self::ATTACKER_TITLE, $fresh->title, 'Owner edit must persist');
    }

    /** A brand-new listing carries no listingId and must remain creatable. */
    public function test_a_new_listing_can_still_be_created_with_no_listing_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $before = SellerAgentAuction::where('user_id', $user->id)->count();

        $this->formWithValidData('seller')->call('store');

        $this->assertSame(
            $before + 1,
            SellerAgentAuction::where('user_id', $user->id)->count(),
            'Creating a new listing must still work'
        );
    }

    public function test_owner_can_still_delete_their_own_photo(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->sellerVictimListing($owner);

        $this->actingAs($owner);

        $this->formWithValidData('seller')
            ->set('listingId', $listing->id)
            ->set('photo', 'victim-photo.jpg')
            ->call('deletePhoto');

        // info() answers false — not null — for an absent meta key (see the model's
        // info()), so the assertion is "no longer the stored filename" rather than
        // a null check, which would pass for the wrong reason.
        $this->assertNotSame(
            'victim-photo.jpg',
            $listing->fresh()->info('photo'),
            'The owner must still be able to delete their own photo'
        );
    }
}
