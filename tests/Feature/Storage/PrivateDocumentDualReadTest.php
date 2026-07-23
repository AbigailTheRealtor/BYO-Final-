<?php

namespace Tests\Feature\Storage;

use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-D.1 (HI-05A) — private document delivery through the object-first read seam,
 * exercised end-to-end via ListingDocumentController (authorization + streaming).
 *
 * The load-bearing guarantees:
 *   - Default (private_read=local): behavior is byte-for-byte the prior
 *     controller — local private, then local public legacy, then 404. The object
 *     secondary is never read.
 *   - object_first: the private secondary is tried first and falls back to the
 *     local chain, so delivery degrades gracefully.
 *   - Authorization is unchanged — the owner is served; the reader only changes
 *     the byte source, never the access decision.
 *
 * All disks are faked; no network occurs.
 */
class PrivateDocumentDualReadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');
        Storage::fake('s3_private');
    }

    private function publishedListing(int $ownerId): SellerAgentAuction
    {
        return SellerAgentAuction::forceCreate(['user_id' => $ownerId]);
    }

    /** Put the owner-only disclosure on a chosen disk and register its meta path. */
    private function putDisclosure(SellerAgentAuction $listing, string $disk): void
    {
        $rel = 'seller-disclosures/' . $listing->id . '/seller-disclosure/test.pdf';
        Storage::disk($disk)->put($rel, '%PDF-1.4 fake');
        $listing->saveMeta('seller_disclosure_file_path', $rel);
    }

    private function disclosureUrl(int $id): string
    {
        return "/listings/seller/{$id}/document/seller_disclosure_file";
    }

    /** DEFAULT: file on local private → owner served (regression). */
    public function test_default_serves_local_private(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing, 'private');

        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    /** DEFAULT: legacy file on local public → still served via fallback (regression). */
    public function test_default_serves_legacy_local_public(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing, 'public');

        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    /**
     * DEFAULT never reads the object secondary: a disclosure present ONLY on
     * s3_private is not served while private_read=local → 404.
     */
    public function test_default_does_not_read_object_secondary(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing, 's3_private');

        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertNotFound();
    }

    /** object_first: disclosure only on the private secondary → served first. */
    public function test_object_first_serves_from_secondary(): void
    {
        config(['listing_storage.private_read' => 'object_first']);

        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing, 's3_private');

        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    /** object_first: object miss falls back to the local private disk. */
    public function test_object_first_falls_back_to_local(): void
    {
        config(['listing_storage.private_read' => 'object_first']);

        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing, 'private'); // nothing on s3_private

        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    /** Authorization is unchanged: a guest is still redirected, object_first or not. */
    public function test_object_first_does_not_relax_authorization(): void
    {
        config(['listing_storage.private_read' => 'object_first']);

        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing, 's3_private');

        $this->get($this->disclosureUrl($listing->id))->assertRedirect(); // guest denied
    }
}
