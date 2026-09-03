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
 *   - the production entrypoints add that dir to PHP_INI_SCAN_DIR; ServeCommand passes the env
 *     to the worker, and a starting PHP process scans PHP_INI_SCAN_DIR — so the values reach it.
 *     ADD, not replace: the variable overrides PHP's own scan directory outright, and assigning
 *     it bare unloaded every extension the interpreter declares there. See
 *     deploy/lib/php-runtime.sh and tests/Feature/Deployment/PhpIniScanDirTest.php.
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
     * #6/#7: every production entrypoint points its PHP worker at the ini override dir.
     *
     * RETARGETED. This assertion used to read `.replit`, because that is where the env prefix
     * lived when Batch C shipped. It does not live there any more: `[deployment] run` and the
     * port-5000 workflow both invoke a script now, and the scripts own their own runtime
     * configuration. The assertion did not follow, so it had been failing against a `.replit`
     * with zero occurrences of PHP_INI_SCAN_DIR — asserting about a file that no longer decides
     * anything, while the three files that do decide it went unguarded.
     *
     * It also pinned the WRONG SHAPE. `PHP_INI_SCAN_DIR=$PWD/deploy/php` replaces the
     * interpreter's own scan directory rather than adding to it, which unloaded every extension
     * — PDO included — and is what eventually stopped production from serving. So the regex that
     * demanded that exact form was actively protecting the bug.
     *
     * What is guarded now: the overlay still reaches the worker, via the shared helper, in all
     * three entrypoints. That the resulting runtime keeps its extensions AND its raised limits is
     * proven by running the real scripts in
     * tests/Feature/Deployment/PhpIniScanDirTest.php — this is the cheap structural half.
     */
    public function test_every_production_entrypoint_points_the_worker_at_the_ini_override_dir(): void
    {
        foreach ([
            'deploy/start-production.sh',
            'deploy/start-serving.sh',
            'deploy/scheduler.sh',
        ] as $script) {
            $source = file_get_contents($this->repoPath($script));

            $this->assertStringContainsString(
                'configure_php_ini_scan_dir "$PWD/deploy/php"',
                $source,
                "$script must point its PHP process at deploy/php, or the raised upload limits never reach the worker."
            );

            $this->assertStringContainsString(
                'deploy/lib/php-runtime.sh',
                $source,
                "$script must source the shared helper that owns this."
            );

            // The bare assignment is the destructive form. It applied uploads.ini correctly and
            // silently dropped every extension the interpreter loads for itself.
            $this->assertStringNotContainsString(
                'export PHP_INI_SCAN_DIR=',
                $source,
                "$script must not assign PHP_INI_SCAN_DIR directly — that replaces PHP's own scan directory."
            );

            // The inert `-d post_max_size=55M` approach must stay gone (it never reached the worker).
            $this->assertStringNotContainsString('-d post_max_size=55M', $source);
        }
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
