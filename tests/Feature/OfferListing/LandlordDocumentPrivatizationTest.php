<?php

namespace Tests\Feature\OfferListing;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HI-05A — new landlord document uploads must land on the PRIVATE disk (never
 * public). Exercised through the isolated doc-row upload hook.
 */
class LandlordDocumentPrivatizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');
    }

    public function test_new_doc_row_upload_is_written_to_private_disk(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('survey.pdf', 100, 'application/pdf');

        $component = Livewire::actingAs($user)->test(LandlordOfferListing::class)
            ->set('landlord_doc_rows', [
                ['type' => 'Survey', 'custom_type' => '', 'description' => '', 'stored_path' => '', 'original_name' => ''],
            ])
            ->set('landlordDocFileIndex', 0)
            ->set('landlordDocFileUpload', $file);

        $storedPath = $component->get('landlord_doc_rows')[0]['stored_path'];

        $this->assertNotEmpty($storedPath);
        $this->assertStringStartsWith('landlord-disclosures/', $storedPath);
        Storage::disk('private')->assertExists($storedPath);
        Storage::disk('public')->assertMissing($storedPath);
    }
}
