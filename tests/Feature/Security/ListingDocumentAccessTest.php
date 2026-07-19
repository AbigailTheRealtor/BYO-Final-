<?php

namespace Tests\Feature\Security;

use App\Models\AcceptedBidSummary;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Documents\DocumentClassification;
use App\Services\Documents\ListingDocumentAccessService;
use App\Services\Documents\ListingDocumentCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * HI-05 (foundational) — private storage + authenticated, authorized document
 * delivery for Seller Offer Listing documents, with the INTERIM gated-viewer
 * rule (option 2):
 *
 *   1. owner or authorized listing agent           → any document;
 *   2. authenticated user + publicly visible listing + AI_READABLE document
 *      (the seven buyer-facing disclosures)         → allowed;
 *   3. everything else                              → denied.
 *
 * Guests are always denied; draft/unpublished/archived listings and
 * REQUEST_REQUIRED / ALWAYS_RESTRICTED documents stay owner/agent-only. This
 * broad authenticated access is temporary — the Document Access batch replaces
 * rule (2) with explicit request/approval/revocation.
 */
class ListingDocumentAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');
    }

    private function publishedListing(int $ownerId): SellerAgentAuction
    {
        // Model defaults: is_approved = true, is_draft = false → publicly visible.
        return SellerAgentAuction::forceCreate(['user_id' => $ownerId]);
    }

    private function draftListing(int $ownerId): SellerAgentAuction
    {
        return SellerAgentAuction::forceCreate(['user_id' => $ownerId, 'is_draft' => true, 'is_approved' => false]);
    }

    /** AI_READABLE disclosure. */
    private function putDisclosure(SellerAgentAuction $listing, string $disk = 'private'): void
    {
        $rel = 'seller-disclosures/' . $listing->id . '/seller-disclosure/test.pdf';
        Storage::disk($disk)->put($rel, '%PDF-1.4 fake');
        $listing->saveMeta('seller_disclosure_file_path', $rel);
    }

    /** REQUEST_REQUIRED general listing document. */
    private function putListingDocument(SellerAgentAuction $listing, string $disk = 'private'): void
    {
        Storage::disk($disk)->put('auction/documents/ld.pdf', '%PDF-1.4 fake');
        $listing->saveMeta('listing_documents', 'ld.pdf');
    }

    private function disclosureUrl(int $id): string
    {
        return "/listings/seller/{$id}/document/seller_disclosure_file";
    }

    private function listingDocUrl(int $id): string
    {
        return "/listings/seller/{$id}/document/listing_documents";
    }

    private function assignAgent(SellerAgentAuction $listing, User $agent, User $owner): void
    {
        AcceptedBidSummary::forceCreate([
            'listing_type'    => 'seller',
            'listing_id'      => $listing->id,
            'accepted_bid_id' => 1,
            'tenant_user_id'  => $owner->id,
            'agent_user_id'   => $agent->id,
            'summary_html'    => '',
        ]);
    }

    // ── rule 2: interim gated-viewer for AI-readable on published ────────
    public function test_authenticated_buyer_can_download_ai_readable_on_published(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing);

        $this->actingAs($buyer)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    public function test_authenticated_buyer_agent_can_download_ai_readable_on_published(): void
    {
        $owner = User::factory()->create();
        $buyerAgent = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing);

        $this->actingAs($buyerAgent)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    public function test_guest_is_denied(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing);

        $this->get($this->disclosureUrl($listing->id))->assertRedirect();
    }

    // ── rule 3: draft / unpublished stay owner/agent only ───────────────
    public function test_unrelated_user_denied_ai_readable_on_draft(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->draftListing($owner->id);
        $this->putDisclosure($listing);

        $this->actingAs($stranger)->get($this->disclosureUrl($listing->id))->assertForbidden();
        // owner keeps access to their own draft
        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    public function test_unrelated_user_denied_ai_readable_on_unpublished(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'is_approved' => false, 'is_draft' => false]);
        $this->putDisclosure($listing);

        $this->actingAs($stranger)->get($this->disclosureUrl($listing->id))->assertForbidden();
    }

    // ── rule 3: REQUEST_REQUIRED / ALWAYS_RESTRICTED stay owner/agent only ─
    public function test_unrelated_user_denied_request_required_document(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putListingDocument($listing);

        // listing_documents is REQUEST_REQUIRED → gated-viewer must NOT apply.
        $this->actingAs($stranger)->get($this->listingDocUrl($listing->id))->assertForbidden();
    }

    public function test_gated_viewer_only_opens_for_ai_readable_class(): void
    {
        // Generic proof across the whole catalog: a stranger on a published
        // listing is allowed a document IFF its class is AI_READABLE. This is
        // what keeps REQUEST_REQUIRED and ALWAYS_RESTRICTED closed to strangers.
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $svc = app(ListingDocumentAccessService::class);

        foreach (ListingDocumentCatalog::keys() as $key) {
            $expected = ListingDocumentCatalog::classificationFor($key) === DocumentClassification::AI_READABLE;
            $this->assertSame(
                $expected,
                $svc->canViewDownload($stranger, 'seller', $listing->id, $key),
                "gated-viewer for {$key} should be " . ($expected ? 'allowed' : 'denied')
            );
        }

        // ALWAYS_RESTRICTED is never AI-readable, so it can never open rule 2.
        $this->assertFalse(DocumentClassification::allowsAiQuery(DocumentClassification::ALWAYS_RESTRICTED));
        $this->assertFalse(DocumentClassification::allowsAiQuery(DocumentClassification::REQUEST_REQUIRED));
    }

    // ── rule 1: owner / authorized listing agent retain full access ─────
    public function test_owner_can_download_request_required_document(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putListingDocument($listing);

        $this->actingAs($owner)->get($this->listingDocUrl($listing->id))->assertOk();
    }

    public function test_authorized_listing_agent_can_download_request_required_document(): void
    {
        $owner = User::factory()->create();
        $agent = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putListingDocument($listing);
        $this->assignAgent($listing, $agent, $owner);

        $this->actingAs($agent)->get($this->listingDocUrl($listing->id))->assertOk();
    }

    // ── cross-listing isolation ─────────────────────────────────────────
    public function test_listing_a_access_cannot_retrieve_listing_b_document(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $listingA = $this->publishedListing($owner->id);
        $this->putDisclosure($listingA); // only A has the file + meta

        $listingB = $this->publishedListing($owner->id); // B has no disclosure meta/file

        // A stranger allowed to view A's disclosure cannot pull A's file through
        // B's id — the controller only ever reads B's own (empty) meta → 404.
        $this->actingAs($stranger)->get($this->disclosureUrl($listingA->id))->assertOk();
        $this->actingAs($stranger)->get($this->disclosureUrl($listingB->id))->assertNotFound();
    }

    // ── structural / no public URL ──────────────────────────────────────
    public function test_unknown_document_key_is_404(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);

        $this->actingAs($owner)->get("/listings/seller/{$listing->id}/document/not_a_real_key")->assertNotFound();
    }

    public function test_missing_file_returns_404_not_500(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);

        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertNotFound();
    }

    public function test_legacy_public_file_is_served_through_authorized_controller(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing, 'public'); // only on public disk

        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    public function test_private_disk_exposes_no_public_url(): void
    {
        $this->assertArrayNotHasKey('url', config('filesystems.disks.private'));
        $this->assertSame('private', config('filesystems.disks.private.visibility'));
    }

    // ── capability model (design; diverges in the follow-up) ────────────
    public function test_ai_query_is_classification_gated(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $svc = app(ListingDocumentAccessService::class);

        // AI-readable on a published listing → AI-queryable.
        $this->assertTrue($svc->canAiQuery($stranger, 'seller', $listing->id, 'seller_disclosure_file'));
        // REQUEST_REQUIRED → not AI-queryable for a stranger.
        $this->assertFalse($svc->canAiQuery($stranger, 'seller', $listing->id, 'listing_documents'));
    }

    public function test_ai_query_denied_on_draft_for_stranger(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->draftListing($owner->id);
        $svc = app(ListingDocumentAccessService::class);

        $this->assertFalse($svc->canAiQuery($stranger, 'seller', $listing->id, 'seller_disclosure_file'));
    }

    public function test_classification_map(): void
    {
        $this->assertSame(DocumentClassification::AI_READABLE, ListingDocumentCatalog::classificationFor('seller_disclosure_file'));
        $this->assertSame(DocumentClassification::AI_READABLE, ListingDocumentCatalog::classificationFor('flood_disclosure_file'));
        $this->assertSame(DocumentClassification::REQUEST_REQUIRED, ListingDocumentCatalog::classificationFor('listing_documents'));
    }
}
