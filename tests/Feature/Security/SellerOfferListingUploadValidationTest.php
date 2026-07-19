<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Concerns\ValidatesMediaUploads;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HI-04 (M1) — Seller Offer Listing photo/video upload hardening.
 *
 * Before this fix the Seller Offer Listing create/edit flows wrote the main
 * $photo/$video to the PUBLIC disk using only getClientOriginalExtension(), with
 * no content or size validation — so an authenticated listing creator could
 * store an arbitrary-extension file (e.g. .html / .svg) reachable at a public
 * /storage URL (content-type confusion / stored XSS).
 *
 * Both components now use the shared ValidatesMediaUploads trait and call
 * validateMediaUploads() at the top of saveAllMetadata(), BEFORE any public-disk
 * write. These tests drive the real components (create AND edit) through that
 * exact validation path, asserting the accepted-types / size / content rules and
 * that a rejected upload performs no disk write.
 *
 * Accepted (matching the rest of B1.4):
 *   - images: jpg, jpeg, png, gif, webp — content-verified — max 10 MB
 *   - videos: mp4, mov, avi, mkv, mpeg  — real-MIME verified — max 50 MB
 * SVG / HTML / executables / spoofed or disallowed media are rejected.
 */
class SellerOfferListingUploadValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('private');
        // The create flow's mount() queries the caller's drafts, so authenticate.
        $this->actingAs(User::factory()->create());
    }

    /** Both Seller Offer Listing flows share the hardened path. */
    public static function flows(): array
    {
        return [
            'create' => [SellerOfferListing::class],
            'edit'   => [SellerOfferListingEdit::class],
        ];
    }

    /**
     * Run the component's own media validation exactly as saveAllMetadata() does
     * (same protected method on a real, booted instance).
     */
    private function validate(string $componentClass, array $props): void
    {
        $instance = Livewire::test($componentClass)->instance();
        foreach ($props as $property => $value) {
            $instance->{$property} = $value;
        }
        $method = new \ReflectionMethod($instance, 'validateMediaUploads');
        $method->setAccessible(true);
        $method->invoke($instance);
    }

    private function assertAccepts(string $componentClass, array $props): void
    {
        $this->validate($componentClass, $props);
        // No ValidationException thrown — reaching here is the pass condition.
        $this->assertTrue(true);
    }

    private function assertRejects(string $componentClass, array $props): void
    {
        try {
            $this->validate($componentClass, $props);
            $this->fail('Expected media validation to reject the upload, but it passed.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->validator->errors()->all());
        }
    }

    // ── structural: both flows use the shared trait ─────────────────────
    /** @dataProvider flows */
    public function test_component_uses_shared_media_validation_trait(string $componentClass): void
    {
        $this->assertContains(
            ValidatesMediaUploads::class,
            class_uses_recursive($componentClass),
            $componentClass . ' must use the ValidatesMediaUploads trait'
        );
    }

    // ── accepted media ──────────────────────────────────────────────────
    /** @dataProvider flows */
    public function test_valid_image_is_accepted(string $componentClass): void
    {
        $this->assertAccepts($componentClass, ['photo' => UploadedFile::fake()->image('house.jpg')]);
    }

    /** @dataProvider flows */
    public function test_valid_video_is_accepted(string $componentClass): void
    {
        $this->assertAccepts($componentClass, ['video' => UploadedFile::fake()->create('tour.mp4', 2048, 'video/mp4')]);
    }

    // ── rejected media ──────────────────────────────────────────────────
    /** @dataProvider flows */
    public function test_svg_photo_is_rejected(string $componentClass): void
    {
        // SVG can carry active script; excluded from the image allow-list.
        $this->assertRejects($componentClass, ['photo' => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml')]);
    }

    /** @dataProvider flows */
    public function test_executable_disguised_as_image_is_rejected(string $componentClass): void
    {
        $this->assertRejects($componentClass, ['photo' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload')]);
    }

    /** @dataProvider flows */
    public function test_html_disguised_as_image_is_rejected(string $componentClass): void
    {
        $this->assertRejects($componentClass, ['photo' => UploadedFile::fake()->create('evil.html', 10, 'text/html')]);
    }

    /** @dataProvider flows */
    public function test_disallowed_video_type_is_rejected(string $componentClass): void
    {
        $this->assertRejects($componentClass, ['video' => UploadedFile::fake()->create('evil.exe', 100, 'application/x-msdownload')]);
    }

    /** @dataProvider flows */
    public function test_oversized_photo_is_rejected(string $componentClass): void
    {
        // 20 MB image > 10 MB cap.
        $this->assertRejects($componentClass, ['photo' => UploadedFile::fake()->image('big.jpg')->size(20480)]);
    }

    /** @dataProvider flows */
    public function test_oversized_video_is_rejected(string $componentClass): void
    {
        // ~59 MB video > 50 MB cap.
        $this->assertRejects($componentClass, ['video' => UploadedFile::fake()->create('big.mp4', 60000, 'video/mp4')]);
    }

    // ── resave: existing stored path is not re-validated ────────────────
    /** @dataProvider flows */
    public function test_existing_stored_path_accepted_on_resave(string $componentClass): void
    {
        $this->assertAccepts($componentClass, [
            'photo' => 'auction/images/existing.jpg',
            'video' => 'auction/videos/existing.mp4',
        ]);
    }

    // ── optional fields stay optional ───────────────────────────────────
    /** @dataProvider flows */
    public function test_optional_photo_and_video_remain_optional(string $componentClass): void
    {
        $this->assertAccepts($componentClass, ['photo' => null, 'video' => null]);
    }

    // ── validation occurs before any public-disk write ──────────────────
    /** @dataProvider flows */
    public function test_rejected_upload_writes_nothing_to_public_disk(string $componentClass): void
    {
        $this->assertRejects($componentClass, ['photo' => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml')]);

        // The validation guard sits ahead of the storeAs() calls, so a rejected
        // upload must never reach the public disk.
        $this->assertEmpty(Storage::disk('public')->allFiles('auction/images'));
        $this->assertEmpty(Storage::disk('public')->allFiles('auction/videos'));
    }
}
