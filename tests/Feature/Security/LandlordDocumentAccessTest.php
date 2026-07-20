<?php

namespace Tests\Feature\Security;

use App\Models\AcceptedBidSummary;
use App\Models\LandlordAgentAuction;
use App\Models\User;
use App\Services\Documents\DocumentClassification;
use App\Services\Documents\ListingDocumentAccessService;
use App\Services\Documents\ListingDocumentCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * HI-05A — landlord Offer Listing documents get the same private storage +
 * authorized delivery + interim gated-viewer model as seller (mirrors
 * ListingDocumentAccessTest), including the doc-row route which resolves the
 * landlord-specific `landlord_doc_rows` / `stored_path` conventions.
 */
class LandlordDocumentAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');
    }

    private function publishedListing(int $ownerId): LandlordAgentAuction
    {
        return LandlordAgentAuction::forceCreate([
            'user_id' => $ownerId, 'is_approved' => true, 'is_draft' => false, 'is_archived' => false,
        ]);
    }

    private function draftListing(int $ownerId): LandlordAgentAuction
    {
        return LandlordAgentAuction::forceCreate([
            'user_id' => $ownerId, 'is_approved' => false, 'is_draft' => true, 'is_archived' => false,
        ]);
    }

    /** AI_READABLE landlord disclosure (full relative path). */
    private function putDisclosure(LandlordAgentAuction $listing, string $disk = 'private'): void
    {
        $rel = 'landlord-disclosures/' . $listing->id . '/landlord-disclosure/test.pdf';
        Storage::disk($disk)->put($rel, '%PDF-1.4 fake');
        $listing->saveMeta('landlord_disclosure_file_path', $rel);
    }

    /** REQUEST_REQUIRED general listing document (bare filename). */
    private function putListingDocument(LandlordAgentAuction $listing, string $disk = 'private'): void
    {
        Storage::disk($disk)->put('auction/documents/ld.pdf', '%PDF-1.4 fake');
        $listing->saveMeta('listing_documents', 'ld.pdf');
    }

    /** REQUEST_REQUIRED doc-row (landlord_doc_rows + stored_path). */
    private function putDocRow(LandlordAgentAuction $listing, string $relative, string $disk = 'private'): void
    {
        Storage::disk($disk)->put($relative, '%PDF-1.4 fake');
        $listing->saveMeta('landlord_doc_rows', json_encode([
            ['type' => 'Survey', 'stored_path' => $relative, 'original_name' => 'survey.pdf'],
        ]));
    }

    private function disclosureUrl(int $id): string
    {
        return "/listings/landlord/{$id}/document/landlord_disclosure_file";
    }

    private function listingDocUrl(int $id): string
    {
        return "/listings/landlord/{$id}/document/listing_documents";
    }

    private function docRowUrl(int $id, int $index): string
    {
        return "/listings/landlord/{$id}/additional-document/{$index}";
    }

    private function assignAgent(LandlordAgentAuction $listing, User $agent, User $owner): void
    {
        AcceptedBidSummary::forceCreate([
            'listing_type'    => 'landlord',
            'listing_id'      => $listing->id,
            'accepted_bid_id' => 1,
            'tenant_user_id'  => $owner->id,
            'agent_user_id'   => $agent->id,
            'summary_html'    => '',
        ]);
    }

    // ── rule 2: interim gated-viewer for AI-readable on published ────────
    public function test_tenant_can_download_ai_readable_on_published(): void
    {
        $owner  = User::factory()->create();
        $tenant = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing);

        $this->actingAs($tenant)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    public function test_guest_is_denied(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing);

        $this->get($this->disclosureUrl($listing->id))->assertRedirect();
    }

    // ── rule 3: draft / unpublished stay owner/agent only ───────────────
    public function test_stranger_denied_on_draft_owner_allowed(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->draftListing($owner->id);
        $this->putDisclosure($listing);

        $this->actingAs($stranger)->get($this->disclosureUrl($listing->id))->assertForbidden();
        $this->actingAs($owner)->get($this->disclosureUrl($listing->id))->assertOk();
    }

    public function test_stranger_denied_request_required_document(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putListingDocument($listing);

        $this->actingAs($stranger)->get($this->listingDocUrl($listing->id))->assertForbidden();
    }

    // ── rule 1: owner / authorized landlord agent retain full access ────
    public function test_owner_can_download_request_required_document(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putListingDocument($listing);

        $this->actingAs($owner)->get($this->listingDocUrl($listing->id))->assertOk();
    }

    public function test_authorized_landlord_agent_can_download(): void
    {
        $owner = User::factory()->create();
        $agent = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putListingDocument($listing);
        $this->assignAgent($listing, $agent, $owner);

        $this->actingAs($agent)->get($this->listingDocUrl($listing->id))->assertOk();
    }

    // ── doc-row authorized route (landlord_doc_rows / stored_path) ──────
    public function test_owner_can_download_landlord_doc_row(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $rel = 'landlord-disclosures/' . $listing->id . '/row.pdf';
        $this->putDocRow($listing, $rel);

        $this->actingAs($owner)->get($this->docRowUrl($listing->id, 0))->assertOk();
    }

    public function test_stranger_denied_landlord_doc_row(): void
    {
        // Doc-rows are REQUEST_REQUIRED-equivalent: owner/agent only, never gated-viewer.
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $rel = 'landlord-disclosures/' . $listing->id . '/row.pdf';
        $this->putDocRow($listing, $rel);

        $this->actingAs($stranger)->get($this->docRowUrl($listing->id, 0))->assertForbidden();
    }

    public function test_doc_row_traversal_path_is_rejected(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $listing->saveMeta('landlord_doc_rows', json_encode([
            ['type' => 'X', 'stored_path' => '../../etc/passwd', 'original_name' => 'x'],
        ]));

        $this->actingAs($owner)->get($this->docRowUrl($listing->id, 0))->assertNotFound();
    }

    // ── cross-listing + cross-TYPE isolation ────────────────────────────
    public function test_cross_listing_isolation(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listingA = $this->publishedListing($owner->id);
        $this->putDisclosure($listingA);
        $listingB = $this->publishedListing($owner->id); // no meta/file

        $this->actingAs($stranger)->get($this->disclosureUrl($listingA->id))->assertOk();
        $this->actingAs($stranger)->get($this->disclosureUrl($listingB->id))->assertNotFound();
    }

    public function test_landlord_disclosure_key_is_not_reachable_as_seller_type(): void
    {
        // A landlord listing id addressed under the seller type resolves the SELLER
        // model (a different table). No seller row exists for that id, so access is
        // DENIED (403) and the landlord file is never served — cross-type isolation:
        // each listingType only ever reads its own model's meta, never another's.
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $this->putDisclosure($listing);

        $this->actingAs($owner)
            ->get("/listings/seller/{$listing->id}/document/landlord_disclosure_file")
            ->assertForbidden();
    }

    public function test_unknown_document_key_is_404(): void
    {
        $owner = User::factory()->create();
        $listing = $this->publishedListing($owner->id);

        $this->actingAs($owner)->get("/listings/landlord/{$listing->id}/document/not_a_real_key")->assertNotFound();
    }

    // ── classification + gated-viewer parity across the catalog ─────────
    public function test_landlord_disclosure_is_ai_readable(): void
    {
        $this->assertSame(
            DocumentClassification::AI_READABLE,
            ListingDocumentCatalog::classificationFor('landlord_disclosure_file')
        );
    }

    public function test_gated_viewer_only_opens_for_ai_readable_class_landlord(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->publishedListing($owner->id);
        $svc = app(ListingDocumentAccessService::class);

        foreach (ListingDocumentCatalog::keys() as $key) {
            $expected = ListingDocumentCatalog::classificationFor($key) === DocumentClassification::AI_READABLE;
            $this->assertSame(
                $expected,
                $svc->canViewDownload($stranger, 'landlord', $listing->id, $key),
                "gated-viewer for landlord {$key} should be " . ($expected ? 'allowed' : 'denied')
            );
        }
    }
}
