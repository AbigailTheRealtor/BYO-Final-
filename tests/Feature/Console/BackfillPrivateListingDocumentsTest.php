<?php

namespace Tests\Feature\Console;

use App\Models\LandlordAgentAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * HI-05A — focused coverage for the documents:backfill-private command.
 *
 * The command reads the *_metas tables directly, so tests seed only meta rows
 * (no parent auction needed) and fake both disks.
 */
class BackfillPrivateListingDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('private');
    }

    private function sellerMeta(int $listingId, string $key, string $value): void
    {
        DB::table('seller_agent_auction_metas')->insert([
            'seller_agent_auction_id' => $listingId,
            'meta_key'                => $key,
            'meta_value'              => $value,
        ]);
    }

    private function landlordMeta(int $listingId, string $key, string $value): void
    {
        DB::table('landlord_agent_auction_metas')->insert([
            'landlord_agent_auction_id' => $listingId,
            'meta_key'                  => $key,
            'meta_value'                => $value,
        ]);
    }

    public function test_dry_run_makes_no_changes_and_writes_no_manifest(): void
    {
        $rel = 'seller-disclosures/1/seller-disclosure/a.pdf';
        Storage::disk('public')->put($rel, 'DATA');
        $this->sellerMeta(1, 'seller_disclosure_file_path', $rel);

        $this->artisan('documents:backfill-private', ['--dry-run' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertExists($rel);
        Storage::disk('private')->assertMissing($rel);
        // No manifest directory created.
        $this->assertEmpty(Storage::disk('private')->allFiles('_backfill-manifests'));
    }

    public function test_copy_moves_public_to_private_and_retains_public_by_default(): void
    {
        $rel = 'seller-disclosures/1/seller-disclosure/a.pdf';
        Storage::disk('public')->put($rel, 'DATA');
        $this->sellerMeta(1, 'seller_disclosure_file_path', $rel);

        $this->artisan('documents:backfill-private')->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertExists($rel); // copy-only: public retained
        $this->assertSame('DATA', Storage::disk('private')->get($rel));
        // A manifest was written.
        $this->assertNotEmpty(Storage::disk('private')->allFiles('_backfill-manifests'));
    }

    public function test_delete_public_removes_public_after_verified_copy(): void
    {
        $rel = 'seller-disclosures/1/seller-disclosure/a.pdf';
        Storage::disk('public')->put($rel, 'DATA');
        $this->sellerMeta(1, 'seller_disclosure_file_path', $rel);

        $this->artisan('documents:backfill-private', ['--delete-public' => true])
            ->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertMissing($rel);
    }

    public function test_is_idempotent_on_second_run(): void
    {
        $rel = 'seller-disclosures/1/seller-disclosure/a.pdf';
        Storage::disk('public')->put($rel, 'DATA');
        $this->sellerMeta(1, 'seller_disclosure_file_path', $rel);

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();
        // Second run: file already private, public gone → no error, no change.
        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertMissing($rel);
        $this->assertSame('DATA', Storage::disk('private')->get($rel));
    }

    public function test_delete_public_self_heals_interrupted_run_when_private_matches(): void
    {
        // Simulate a prior run that copied+verified but was interrupted before delete:
        // the same byte-identical file exists on BOTH disks.
        $rel = 'seller-disclosures/1/seller-disclosure/a.pdf';
        Storage::disk('public')->put($rel, 'DATA');
        Storage::disk('private')->put($rel, 'DATA');
        $this->sellerMeta(1, 'seller_disclosure_file_path', $rel);

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertMissing($rel); // stale public copy removed
    }

    public function test_delete_public_never_removes_mismatched_public_copy(): void
    {
        // Private and public differ → the command must retain BOTH and flag it.
        $rel = 'seller-disclosures/1/seller-disclosure/a.pdf';
        Storage::disk('public')->put($rel, 'PUBLIC-VERSION');
        Storage::disk('private')->put($rel, 'DIFFERENT-PRIVATE');
        $this->sellerMeta(1, 'seller_disclosure_file_path', $rel);

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        Storage::disk('public')->assertExists($rel);  // retained — not byte-identical
        Storage::disk('private')->assertExists($rel);
    }

    public function test_listing_documents_bare_filename_resolves_under_auction_documents(): void
    {
        $filename = 'contract.pdf';
        $rel      = 'auction/documents/' . $filename;
        Storage::disk('public')->put($rel, 'DOC');
        $this->sellerMeta(2, 'listing_documents', $filename);

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertMissing($rel);
    }

    public function test_doc_rows_file_paths_are_migrated(): void
    {
        $rel  = 'seller-doc-uploads/3/b.pdf';
        Storage::disk('public')->put($rel, 'ROW');
        $this->sellerMeta(3, 'doc_rows', json_encode([['type' => 'Addendum', 'file_path' => $rel]]));

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertMissing($rel);
    }

    public function test_landlord_documents_are_supported(): void
    {
        // landlord_agent_auction_metas has a FK to landlord_agent_auctions, so a
        // real parent row is required before seeding meta.
        $listingId = LandlordAgentAuction::forceCreate(['user_id' => 1])->id;
        $rel = 'landlord-disclosures/' . $listingId . '/landlord-disclosure/c.pdf';
        Storage::disk('public')->put($rel, 'LL');
        $this->landlordMeta($listingId, 'landlord_disclosure_file_path', $rel);

        $this->artisan('documents:backfill-private', ['--listing-type' => 'landlord', '--delete-public' => true])
            ->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertMissing($rel);
    }

    public function test_photos_and_videos_are_never_touched(): void
    {
        // A doc_row maliciously/accidentally pointing at marketing media must be skipped.
        $image = 'auction/images/photo.jpg';
        Storage::disk('public')->put($image, 'IMG');
        $this->sellerMeta(4, 'doc_rows', json_encode([['file_path' => $image]]));

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        Storage::disk('public')->assertExists($image);  // retained
        Storage::disk('private')->assertMissing($image); // never copied
    }

    public function test_path_traversal_and_absolute_paths_are_skipped(): void
    {
        Storage::disk('public')->put('safe/keep.pdf', 'X');
        $this->sellerMeta(5, 'survey_file_path', '../../etc/passwd');
        $this->sellerMeta(6, 'inspection_report_file_path', '/etc/passwd');

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        // Nothing traversed; no private writes for the malicious paths.
        $this->assertEmpty(array_filter(
            Storage::disk('private')->allFiles(),
            fn ($f) => ! str_starts_with($f, '_backfill-manifests')
        ));
    }

    public function test_missing_public_file_is_a_noop_not_an_error(): void
    {
        $this->sellerMeta(7, 'flood_disclosure_file_path', 'seller-disclosures/7/flood-disclosure/gone.pdf');

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        $this->assertEmpty(array_filter(
            Storage::disk('private')->allFiles(),
            fn ($f) => ! str_starts_with($f, '_backfill-manifests')
        ));
    }

    public function test_unknown_listing_type_fails(): void
    {
        $this->artisan('documents:backfill-private', ['--listing-type' => 'buyer'])
            ->assertFailed();
    }

    public function test_manifest_records_actions_and_summary(): void
    {
        $rel = 'seller-disclosures/1/seller-disclosure/a.pdf';
        Storage::disk('public')->put($rel, 'DATA');
        $this->sellerMeta(1, 'seller_disclosure_file_path', $rel);

        $this->artisan('documents:backfill-private')->assertSuccessful();

        $files = Storage::disk('private')->allFiles('_backfill-manifests');
        $this->assertNotEmpty($files);
        $manifest = json_decode(Storage::disk('private')->get($files[0]), true);

        $this->assertSame(1, $manifest['summary']['copied'] ?? null);
        $this->assertFalse($manifest['options']['delete_public']);
        $this->assertSame('copied', $manifest['records'][0]['action']);
        $this->assertSame($rel, $manifest['records'][0]['relative_path']);
        $this->assertTrue($manifest['records'][0]['verified']);
    }

    // ---------------------------------------------------------------------
    // PR-A3 — listing-type-aware doc-row path field.
    //
    // Seller doc rows carry the path in `file_path`; landlord doc rows carry it
    // in `stored_path`. The field is resolved from a fixed server-side map keyed
    // by listing type (never from command input). These tests prove landlord
    // coverage AND that seller behavior is preserved exactly.
    // ---------------------------------------------------------------------

    /** Landlord meta rows FK to landlord_agent_auctions, so a parent row is required. */
    private function landlordListingId(): int
    {
        return LandlordAgentAuction::forceCreate(['user_id' => 1])->id;
    }

    public function test_seller_doc_rows_use_file_path_field_only(): void
    {
        // Seller preservation guard: a seller row keys off `file_path`. A landlord-style
        // `stored_path` on a SELLER row must be ignored even though its file exists.
        $rel     = 'seller-doc-uploads/31/s.pdf';
        $ignored = 'seller-doc-uploads/31/ignored-stored-path.pdf';
        Storage::disk('public')->put($rel, 'SROW');
        Storage::disk('public')->put($ignored, 'NOPE');
        $this->sellerMeta(31, 'doc_rows', json_encode([
            ['type' => 'Addendum', 'file_path' => $rel],
            ['type' => 'WrongField', 'stored_path' => $ignored],
        ]));

        $this->artisan('documents:backfill-private', ['--delete-public' => true])->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertMissing($rel);
        // The stored_path-only seller row was never discovered.
        Storage::disk('private')->assertMissing($ignored);
        Storage::disk('public')->assertExists($ignored);
    }

    public function test_landlord_doc_rows_use_stored_path_and_copy_public_to_private(): void
    {
        $id      = $this->landlordListingId();
        $rel     = 'landlord-doc-uploads/' . $id . '/l.pdf';
        $ignored = 'landlord-doc-uploads/' . $id . '/ignored-file-path.pdf';
        Storage::disk('public')->put($rel, 'LROW');
        Storage::disk('public')->put($ignored, 'NOPE');
        // Landlord rows key off `stored_path`; a seller-style `file_path` must be ignored.
        $this->landlordMeta($id, 'landlord_doc_rows', json_encode([
            ['type' => 'Lease', 'stored_path' => $rel],
            ['type' => 'WrongField', 'file_path' => $ignored],
        ]));

        // Copy-only (no --delete-public): public retained, private created.
        $this->artisan('documents:backfill-private', ['--listing-type' => 'landlord'])
            ->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertExists($rel); // copy-only
        $this->assertSame('LROW', Storage::disk('private')->get($rel));
        // The file_path-only landlord row was never discovered.
        Storage::disk('private')->assertMissing($ignored);
    }

    public function test_landlord_doc_rows_delete_public_only_after_verified_private_copy(): void
    {
        $id  = $this->landlordListingId();
        $rel = 'landlord-doc-uploads/' . $id . '/l.pdf';
        Storage::disk('public')->put($rel, 'LROW');
        $this->landlordMeta($id, 'landlord_doc_rows', json_encode([['stored_path' => $rel]]));

        $this->artisan('documents:backfill-private', ['--listing-type' => 'landlord', '--delete-public' => true])
            ->assertSuccessful();

        Storage::disk('private')->assertExists($rel);       // verified copy present
        Storage::disk('public')->assertMissing($rel);       // removed only after verification
        $this->assertSame('LROW', Storage::disk('private')->get($rel));
    }

    public function test_landlord_doc_rows_dry_run_makes_no_changes(): void
    {
        $id  = $this->landlordListingId();
        $rel = 'landlord-doc-uploads/' . $id . '/l.pdf';
        Storage::disk('public')->put($rel, 'LROW');
        $this->landlordMeta($id, 'landlord_doc_rows', json_encode([['stored_path' => $rel]]));

        $this->artisan('documents:backfill-private', [
            '--listing-type'  => 'landlord',
            '--dry-run'       => true,
            '--delete-public' => true,
        ])->assertSuccessful();

        Storage::disk('public')->assertExists($rel);
        Storage::disk('private')->assertMissing($rel);
        $this->assertEmpty(Storage::disk('private')->allFiles('_backfill-manifests'));
    }

    public function test_landlord_doc_rows_reject_traversal_and_absolute_paths(): void
    {
        $id = $this->landlordListingId();
        Storage::disk('public')->put('safe/keep.pdf', 'X');
        $this->landlordMeta($id, 'landlord_doc_rows', json_encode([
            ['stored_path' => '../../etc/passwd'],
            ['stored_path' => '/etc/passwd'],
        ]));

        $this->artisan('documents:backfill-private', ['--listing-type' => 'landlord', '--delete-public' => true])
            ->assertSuccessful();

        // Nothing traversed; no private writes for the malicious paths.
        $this->assertEmpty(array_filter(
            Storage::disk('private')->allFiles(),
            fn ($f) => ! str_starts_with($f, '_backfill-manifests')
        ));
    }

    public function test_landlord_doc_rows_never_touch_photos_or_videos(): void
    {
        $id    = $this->landlordListingId();
        $image = 'auction/images/ll-photo.jpg';
        $video = 'auction/videos/ll-tour.mp4';
        Storage::disk('public')->put($image, 'IMG');
        Storage::disk('public')->put($video, 'VID');
        $this->landlordMeta($id, 'landlord_doc_rows', json_encode([
            ['stored_path' => $image],
            ['stored_path' => $video],
        ]));

        $this->artisan('documents:backfill-private', ['--listing-type' => 'landlord', '--delete-public' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertExists($image);   // retained
        Storage::disk('public')->assertExists($video);   // retained
        Storage::disk('private')->assertMissing($image); // never copied
        Storage::disk('private')->assertMissing($video); // never copied
    }

    public function test_landlord_doc_rows_are_idempotent_on_second_run(): void
    {
        $id  = $this->landlordListingId();
        $rel = 'landlord-doc-uploads/' . $id . '/l.pdf';
        Storage::disk('public')->put($rel, 'LROW');
        $this->landlordMeta($id, 'landlord_doc_rows', json_encode([['stored_path' => $rel]]));

        $this->artisan('documents:backfill-private', ['--listing-type' => 'landlord', '--delete-public' => true])->assertSuccessful();
        // Second run: private present, public gone → no error, no change.
        $this->artisan('documents:backfill-private', ['--listing-type' => 'landlord', '--delete-public' => true])->assertSuccessful();

        Storage::disk('private')->assertExists($rel);
        Storage::disk('public')->assertMissing($rel);
        $this->assertSame('LROW', Storage::disk('private')->get($rel));
    }

    public function test_landlord_doc_rows_malformed_or_missing_json_is_safely_skipped(): void
    {
        // Non-JSON blob.
        $id1 = $this->landlordListingId();
        $this->landlordMeta($id1, 'landlord_doc_rows', 'not-json{');
        // Valid JSON but scalar rows / rows missing stored_path.
        $id2 = $this->landlordListingId();
        $this->landlordMeta($id2, 'landlord_doc_rows', json_encode(['scalar-not-a-row', ['type' => 'NoPath'], ['stored_path' => '']]));

        $this->artisan('documents:backfill-private', ['--listing-type' => 'landlord', '--delete-public' => true])
            ->assertSuccessful();

        $this->assertEmpty(array_filter(
            Storage::disk('private')->allFiles(),
            fn ($f) => ! str_starts_with($f, '_backfill-manifests')
        ));
    }
}
