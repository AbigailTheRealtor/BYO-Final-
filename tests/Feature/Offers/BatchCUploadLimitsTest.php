<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch C — launch-audit remediation regression guards (#6 / #7: photo/document uploads).
 *
 * ROOT CAUSE (proven, not guessed): the app is served by `php artisan serve` (PHP built-in
 * `cli-server` SAPI). That SAPI ignores `.user.ini`, and Laravel 8's ServeCommand spawns its
 * `php -S` worker WITHOUT forwarding the `-d` flags set in `.replit`. So real uploads ran at
 * PHP's compiled defaults (post_max_size=8M, upload_max_filesize=2M, max_file_uploads=20),
 * which silently dropped a 14-JPG batch (Livewire sends a multi-file selection as ONE POST).
 *
 * THE FIX is deployment config, not application code:
 *   - deploy/php/uploads.ini declares the real limits (50M/file, 150M POST, 50 files, 512M mem).
 *   - .replit sets PHP_INI_SCAN_DIR to that dir; ServeCommand passes the env to the worker,
 *     and a starting PHP process scans PHP_INI_SCAN_DIR — so the values reach the worker.
 *
 * The Laravel/Livewire APPLICATION rules were already correct at 50M and are unchanged; the
 * per-file rule is guarded below so a future edit can't silently loosen it.
 *
 * NOTE (Owner Decision #4): code verification only. The effective PHP ini can only be proven
 * on the running cli-server (done manually this session); it CANNOT be asserted from PHPUnit,
 * which runs a different SAPI. #6/#7 stay "CODE COMPLETE — HUMAN BROWSER QA REQUIRED" until a
 * human uploads 14 real JPGs against the running app.
 */
class BatchCUploadLimitsTest extends TestCase
{
    use DatabaseTransactions;

    private function agent(): User
    {
        return User::factory()->create(['user_type' => 'agent']);
    }

    private function repoPath(string $relative): string
    {
        return base_path($relative);
    }

    /** #7: the ini override file exists and declares the approved target limits. */
    public function test_upload_ini_override_declares_target_limits(): void
    {
        $path = $this->repoPath('deploy/php/uploads.ini');
        $this->assertFileExists($path, 'deploy/php/uploads.ini must exist — it is what actually raises the runtime PHP upload limits under `php artisan serve`.');

        $ini = file_get_contents($path);
        $this->assertMatchesRegularExpression('/^\s*upload_max_filesize\s*=\s*50M\s*$/m', $ini);
        $this->assertMatchesRegularExpression('/^\s*post_max_size\s*=\s*150M\s*$/m', $ini);
        $this->assertMatchesRegularExpression('/^\s*max_file_uploads\s*=\s*50\s*$/m', $ini);
        $this->assertMatchesRegularExpression('/^\s*memory_limit\s*=\s*512M\s*$/m', $ini);
    }

    /**
     * #6/#7: .replit applies the ini via PHP_INI_SCAN_DIR for BOTH the dev workflow and the
     * deployment run command, and no longer relies on the inert `-d` flags that never reached
     * the request-handling worker.
     */
    public function test_replit_points_the_worker_at_the_ini_override_dir(): void
    {
        // Read the raw TOML; quotes inside string values are backslash-escaped, so match on
        // the meaningful tokens rather than exact quote characters.
        $replit = file_get_contents($this->repoPath('.replit'));

        // The scan dir must be wired in BOTH places: the dev "Laravel Server" workflow AND the
        // deployment run command (>= 2 occurrences).
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($replit, 'PHP_INI_SCAN_DIR'),
            '.replit must apply PHP_INI_SCAN_DIR for both the dev workflow and the deployment run.'
        );
        $this->assertMatchesRegularExpression('/PHP_INI_SCAN_DIR=.{0,4}\$PWD\/deploy\/php/', $replit);

        // The dev workflow applies it before `php artisan serve`.
        $this->assertMatchesRegularExpression('/PHP_INI_SCAN_DIR=.*deploy\/php.*php artisan serve/', $replit);

        // The deployment run command uses bash -c so the env prefix actually takes effect.
        $this->assertMatchesRegularExpression('/run\s*=\s*\[.*bash.*-c.*PHP_INI_SCAN_DIR.*deploy\/php.*artisan serve/s', $replit);

        // The inert `-d post_max_size=55M` approach must be gone (it never reached the worker).
        $this->assertStringNotContainsString('-d post_max_size=55M', $replit);
    }

    /** #7: the app-layer per-file rule stays at 50 MB (51200 KB) in both role handlers. */
    public function test_per_file_validation_rule_still_enforces_fifty_mb(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
        ] as $handler) {
            $src = file_get_contents($this->repoPath($handler));
            $this->assertStringContainsString(
                "'newPropertyPhotos.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:51200'",
                $src,
                "$handler must keep the 50 MB (max:51200) per-photo validation rule."
            );
            $this->assertStringContainsString('> 50', $src, "$handler must keep the 50-photo count cap.");
        }
    }

    /**
     * #7: both photo blades surface a clear error when the browser/PHP rejects an oversize upload.
     *
     * UPDATED (Batch 3): the original assertion required `livewire-upload-error.window`. That
     * binding was itself the bug — `.window` catches the error from EVERY file input on the
     * page, so a failure on the Info tab's personal-photo input lit up this alert inside the
     * Photos pane, which is not `show active` at the time. The user still saw nothing. The
     * blades now wrap their input in <x-upload-error-boundary>, which listens on the wrapper
     * and relies on the event bubbling from the input, scoping the alert to its own surface.
     * The assertion is inverted accordingly and now guards against `.window` returning.
     */
    public function test_photo_blades_surface_oversize_upload_error(): void
    {
        foreach ([
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/photos-tours-documents.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/photos-tours-documents.blade.php',
        ] as $blade) {
            $markup = file_get_contents($this->repoPath($blade));
            $this->assertStringContainsString('<x-upload-error-boundary', $markup, "$blade must wrap its file input in the scoped upload-error boundary.");
            $this->assertStringContainsString('too large to send at once', $markup, "$blade must show a friendly oversize message.");
            $this->assertStringNotContainsString(
                'livewire-upload-error.window',
                $markup,
                "$blade must NOT bind the upload-error listener to .window — that is what rendered the alert in a hidden tab pane."
            );
        }
    }

    /**
     * #7 app-layer guard: an over-50 MB photo is rejected by the Livewire component before any
     * image processing runs. (This exercises the Laravel rule, NOT the PHP ini — the ini is a
     * deployment concern proven separately on the running cli-server.)
     */
    public function test_seller_component_rejects_oversize_photo(): void
    {
        $oversize = UploadedFile::fake()->image('too-big.jpg')->size(60000); // 60,000 KB ≈ 58.6 MB > 50 MB

        Livewire::actingAs($this->agent())
            ->test(SellerOfferListing::class)
            ->set('newPropertyPhotos', [$oversize])
            ->assertHasErrors('newPropertyPhotos.0');
    }

    public function test_landlord_component_rejects_oversize_photo(): void
    {
        $oversize = UploadedFile::fake()->image('too-big.jpg')->size(60000);

        Livewire::actingAs($this->agent())
            ->test(LandlordOfferListing::class)
            ->set('newPropertyPhotos', [$oversize])
            ->assertHasErrors('newPropertyPhotos.0');
    }
}
