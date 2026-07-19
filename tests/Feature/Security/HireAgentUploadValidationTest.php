<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Concerns\ValidatesMediaUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithFileUploads;
use Tests\TestCase;

/**
 * HI-04 — Hire-Agent upload hardening.
 *
 * The four Hire-Agent flows (Seller/Buyer/Landlord/Tenant, create + edit) share
 * the ValidatesMediaUploads trait, which validates the $photo/$video uploads by
 * actual content (image / mimetypes) plus size before any disk write. These
 * tests exercise the trait through a minimal Livewire stub component with real
 * UploadedFile fakes — the same validation path every flow now runs.
 */
class HireAgentUploadValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function stub()
    {
        return Livewire::test(MediaUploadStubComponent::class);
    }

    public function test_valid_image_is_accepted(): void
    {
        $this->stub()
            ->set('photo', UploadedFile::fake()->image('house.jpg'))
            ->call('runValidation')
            ->assertHasNoErrors('photo');
    }

    public function test_valid_video_is_accepted(): void
    {
        $this->stub()
            ->set('video', UploadedFile::fake()->create('tour.mp4', 2048, 'video/mp4'))
            ->call('runValidation')
            ->assertHasNoErrors('video');
    }

    public function test_oversized_photo_is_rejected(): void
    {
        $this->stub()
            ->set('photo', UploadedFile::fake()->image('big.jpg')->size(20480)) // 20 MB > 10 MB
            ->call('runValidation')
            ->assertHasErrors(['photo' => 'max']);
    }

    public function test_executable_disguised_as_photo_is_rejected(): void
    {
        $this->stub()
            ->set('photo', UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'))
            ->call('runValidation')
            ->assertHasErrors('photo');
    }

    public function test_svg_photo_is_rejected(): void
    {
        // SVG can carry active script; excluded from the image allow-list.
        $this->stub()
            ->set('photo', UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'))
            ->call('runValidation')
            ->assertHasErrors('photo');
    }

    public function test_disallowed_video_type_is_rejected(): void
    {
        // An executable offered as a video is rejected by the mimetypes rule.
        //
        // NOTE: a true content spoof (a valid .mp4 EXTENSION carrying executable
        // BYTES) cannot be faithfully reproduced here — Livewire's temporary-file
        // harness derives the MIME from the stored temp file's extension, and
        // fake files carry no real divergent content. In production the
        // `mimetypes` rule sniffs actual content on the real HTTP upload; this
        // test asserts the enforceable, harness-supported case (a disallowed
        // type/extension is refused).
        $this->stub()
            ->set('video', UploadedFile::fake()->create('evil.exe', 100, 'application/x-msdownload'))
            ->call('runValidation')
            ->assertHasErrors(['video' => 'mimetypes']);
    }

    public function test_optional_upload_remains_optional(): void
    {
        $this->stub()
            ->call('runValidation')
            ->assertHasNoErrors(['photo', 'video']);
    }

    public function test_existing_string_path_passes_on_resave(): void
    {
        // On edit re-save the property holds the already-stored path (a string),
        // not a fresh upload — it must not be re-validated as a file.
        $this->stub()
            ->set('photo', 'auction/images/existing.jpg')
            ->set('video', 'auction/videos/existing.mp4')
            ->call('runValidation')
            ->assertHasNoErrors(['photo', 'video']);
    }
}

/**
 * Minimal Livewire stub that mounts only the shared media-validation trait.
 */
class MediaUploadStubComponent extends Component
{
    use WithFileUploads;
    use ValidatesMediaUploads;

    public $photo;
    public $video;

    public function runValidation(): void
    {
        $this->validateMediaUploads();
    }

    public function render()
    {
        return '<div></div>';
    }
}
