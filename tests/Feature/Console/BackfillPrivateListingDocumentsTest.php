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
}
