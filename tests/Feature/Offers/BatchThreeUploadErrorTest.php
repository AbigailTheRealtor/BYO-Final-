<?php

namespace Tests\Feature\Offers;

use Tests\TestCase;

/**
 * Batch 3 — #6 / #7 upload infrastructure.
 *
 * #6 (14-photo upload) needs NO code change: deploy/php/uploads.ini + the PHP_INI_SCAN_DIR
 *    wiring in .replit already raise the worker's limits, and BatchCUploadLimitsTest already
 *    asserts both. What remains is runtime/edge-proxy verification, which cannot be performed
 *    from PHPUnit. The mechanism itself is asserted here (a PHP process started with
 *    PHP_INI_SCAN_DIR=deploy/php really does report the raised limits), so a regression in the
 *    ini file or the scan-dir wiring fails loudly.
 *
 * #7 (friendly oversize error) had two distinct silent-failure defects, with DIFFERENT causes:
 *
 *   (a) The single personal-photo input (wire:model="photo") in all four *-info tabs had NO
 *       upload-error listener at all. Livewire dispatches `livewire-upload-error` on that
 *       input, but nothing listened, so a rejected photo did nothing visible.
 *
 *   (b) The document upload rows are NOT Livewire wire:model inputs — they are plain file
 *       inputs driven by an Alpine handler calling @this.upload(). Livewire's DOM event never
 *       fires for them. Their error callback only cleared the spinner and discarded the
 *       failure, so an oversize document was a silent no-op.
 *
 *   Separately, the two photo tabs listened with `livewire-upload-error.window`. Because the
 *   event bubbles to window from EVERY file input, a failure on the Info tab lit up the alert
 *   inside the Photos pane — a pane that is not `show active` at that moment. Visible nowhere.
 *
 * The fix is one scoped <x-upload-error-boundary> component that wraps an input and listens on
 * the wrapper (no `.window`), plus a real error callback for the Alpine-driven document rows.
 */
class BatchThreeUploadErrorTest extends TestCase
{
    // NOTE: keys are surface-unique ("seller-info", "seller-photos"), NOT role names. These
    // arrays get array_merge()d, and array_merge OVERWRITES on duplicate string keys — a
    // role-keyed map silently collapsed the six Create surfaces down to four and made these
    // guards weaker than they appeared.
    private const INFO_TABS = [
        'seller-info'   => 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-info.blade.php',
        'buyer-info'    => 'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/buyer-info.blade.php',
        'landlord-info' => 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/landlord-info.blade.php',
        'tenant-info'   => 'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/tenant-info.blade.php',
    ];

    private const PHOTO_TABS = [
        'seller-photos'   => 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/photos-tours-documents.blade.php',
        'landlord-photos' => 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/photos-tours-documents.blade.php',
    ];

    private const DOC_TABS = [
        'seller-docs'   => 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/documents-disclosures.blade.php',
        'landlord-docs' => 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/documents-disclosures.blade.php',
    ];

    private const BOUNDARY = 'resources/views/components/upload-error-boundary.blade.php';

    private function source(string $relativePath): string
    {
        $path = base_path($relativePath);
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    // ── #6 — infra mechanism ─────────────────────────────────────────────────

    /**
     * #6: the ini override really is what raises the limits. Runs a real PHP process with the
     * scan dir the .replit `run` command sets, and reads back what that process reports.
     */
    public function test_the_ini_override_actually_raises_the_limits_in_a_real_php_process(): void
    {
        $php    = PHP_BINARY;
        $script = 'echo ini_get("upload_max_filesize"), "|", ini_get("post_max_size"), "|", ini_get("max_file_uploads"), "|", ini_get("memory_limit");';

        $cmd = sprintf(
            'PHP_INI_SCAN_DIR=%s %s -r %s 2>&1',
            escapeshellarg(base_path('deploy/php')),
            escapeshellarg($php),
            escapeshellarg($script)
        );

        $reported = trim((string) shell_exec($cmd));

        $this->assertSame(
            '50M|150M|50|512M',
            $reported,
            'A PHP process started with PHP_INI_SCAN_DIR=deploy/php must report the raised upload limits. '
            . 'If this fails, deploy/php/uploads.ini is broken and the 14-photo upload (#6) will silently drop files.'
        );
    }

    /** #6: the raised POST limit must stay comfortably above the per-file limit x a real batch. */
    public function test_post_limit_can_carry_a_fourteen_photo_batch(): void
    {
        $ini = $this->source('deploy/php/uploads.ini');

        preg_match('/^\s*post_max_size\s*=\s*(\d+)M\s*$/m', $ini, $post);
        preg_match('/^\s*max_file_uploads\s*=\s*(\d+)\s*$/m', $ini, $count);

        $this->assertNotEmpty($post, 'post_max_size must be declared in M');
        $this->assertGreaterThanOrEqual(150, (int) $post[1], 'post_max_size must carry a multi-photo batch');
        $this->assertGreaterThanOrEqual(14, (int) $count[1], 'max_file_uploads must allow at least the 14 photos of #6');
    }

    // ── #7(a) — the single-photo input, all four roles ───────────────────────

    /**
     * Strip HTML and Blade comments. Markup inside a comment never reaches the DOM, so
     * Livewire never attaches to it and it cannot fail silently.
     *
     * This matters: the Landlord Info tab carries a commented-out `wire:model="video"`
     * input. A naive grep reports it as an unguarded upload surface; it is dead markup.
     * Every assertion below therefore runs against LIVE markup only.
     */
    private function liveMarkup(string $relativePath): string
    {
        $src = $this->source($relativePath);
        $src = preg_replace('/<!--.*?-->/s', '', $src);

        return preg_replace('/\{\{--.*?--\}\}/s', '', $src);
    }

    /**
     * @return list<array{model: string, offset: int}> every LIVE wire:model file input
     *
     * The tag matcher must tolerate a bare `>` inside an embedded Blade directive — the photo
     * inputs carry `@if(count($propertyPhotos ?? []) >= 50) disabled @endif`. A plain `[^>]*`
     * truncates the tag at that `>=` and silently misses the input, so `>` is allowed through
     * when (and only when) it is followed by `=`.
     */
    private function liveUploadInputs(string $markup): array
    {
        if (!preg_match_all('/<input\b(?:[^>]|>(?==))*>/s', $markup, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $inputs = [];
        foreach ($m[0] as [$tag, $offset]) {
            if (strpos($tag, 'type="file"') === false) {
                continue;
            }
            if (!preg_match('/wire:model[^=]*="([^"]+)"/', $tag, $wm)) {
                continue; // not a Livewire upload — cannot emit livewire-upload-error
            }
            $inputs[] = ['model' => $wm[1], 'offset' => $offset];
        }

        return $inputs;
    }

    /** @return list<array{start: int, end: int}> every <x-upload-error-boundary> span */
    private function boundarySpans(string $markup): array
    {
        preg_match_all('/<x-upload-error-boundary/', $markup, $o, PREG_OFFSET_CAPTURE);
        preg_match_all('/<\/x-upload-error-boundary>/', $markup, $c, PREG_OFFSET_CAPTURE);

        $this->assertSameSize($o[0], $c[0], 'every boundary must be closed');

        $spans = [];
        foreach ($o[0] as $i => [$_, $start]) {
            $spans[] = ['start' => $start, 'end' => $c[0][$i][1]];
        }

        return $spans;
    }

    /**
     * #7 — the load-bearing guard: EVERY live Livewire file upload on every in-scope Create
     * surface must sit INSIDE a boundary. Not "the input named photo" — every one of them.
     *
     * The earlier version of this test asserted only that `wire:model="photo"` was wrapped,
     * which would have passed even if a second, unguarded upload sat on the same tab. This
     * version enumerates the inputs from the markup itself, so a newly added upload on any of
     * these tabs fails until it is protected.
     */
    public function test_every_live_livewire_upload_on_the_create_surfaces_is_inside_a_boundary(): void
    {
        $surfaces = array_merge(self::INFO_TABS, self::PHOTO_TABS);
        $checked  = 0;

        foreach ($surfaces as $role => $blade) {
            $markup = $this->liveMarkup($blade);
            $spans  = $this->boundarySpans($markup);
            $inputs = $this->liveUploadInputs($markup);

            $this->assertNotEmpty($inputs, "$role: expected at least one Livewire upload input");

            foreach ($inputs as $input) {
                $inside = false;
                foreach ($spans as $span) {
                    if ($input['offset'] > $span['start'] && $input['offset'] < $span['end']) {
                        $inside = true;
                        break;
                    }
                }

                $this->assertTrue(
                    $inside,
                    sprintf(
                        '%s (%s): the Livewire upload wire:model="%s" is NOT inside an <x-upload-error-boundary>. '
                        . 'Livewire dispatches livewire-upload-error on this input; with no listener above it, a '
                        . 'rejected file is a silent no-op.',
                        $role,
                        $blade,
                        $input['model']
                    )
                );
                $checked++;
            }
        }

        $this->assertSame(6, $checked, 'expected exactly 6 live Livewire uploads across the four Info tabs + two Photo tabs');
    }

    /** The commented-out Landlord video input is dead markup and must stay out of the DOM. */
    public function test_the_landlord_video_input_is_commented_out_and_not_a_live_surface(): void
    {
        $blade = self::INFO_TABS['landlord-info'];

        $this->assertStringContainsString(
            'wire:model="video"',
            $this->source($blade),
            'the commented-out video input is expected to still exist in the source'
        );
        $this->assertStringNotContainsString(
            'wire:model="video"',
            $this->liveMarkup($blade),
            'if the Landlord video upload is ever UNCOMMENTED it becomes a live Livewire upload '
            . 'and must be wrapped in an <x-upload-error-boundary> — this test failing is the signal to do that.'
        );
    }

    // ── #7 — no `.window` anywhere ───────────────────────────────────────────

    /**
     * #7: `.window` is the hidden-pane bug. It must not exist on any offer-listing upload
     * surface — it catches errors from inputs in other tabs and renders the alert where the
     * user cannot see it.
     */
    public function test_no_offer_listing_upload_surface_binds_the_error_to_window(): void
    {
        $surfaces = array_merge(self::INFO_TABS, self::PHOTO_TABS, self::DOC_TABS);

        foreach ($surfaces as $role => $blade) {
            $this->assertStringNotContainsString(
                'livewire-upload-error.window',
                $this->source($blade),
                "$role ($blade): a .window-bound upload-error listener fires for every file input on the page and renders in a hidden tab pane."
            );
        }
    }

    /** #7: the shared boundary itself must not reintroduce `.window`. */
    public function test_the_boundary_component_listens_on_the_wrapper_not_the_window(): void
    {
        $markup = $this->source(self::BOUNDARY);

        $this->assertStringContainsString('x-on:livewire-upload-error=', $markup);
        $this->assertStringNotContainsString('livewire-upload-error.window', $markup);
        $this->assertStringContainsString('x-on:livewire-upload-start=', $markup, 'a fresh upload must clear the previous error');
        $this->assertStringContainsString('{{ $slot }}', $markup, 'the boundary must wrap its input via the slot');
    }

    // ── #7(b) — the Alpine-driven document rows ──────────────────────────────

    /**
     * #7: the document rows' error callback must surface the failure. Previously it only set
     * `uploading[index] = false`, which stopped the spinner and told the user nothing.
     */
    public function test_document_upload_error_callback_surfaces_the_failure(): void
    {
        foreach (self::DOC_TABS as $role => $blade) {
            $markup = $this->source($blade);

            $this->assertStringContainsString(
                'uploadErrors[index] =',
                $markup,
                "$role: the document upload error callback must record the failure, not discard it."
            );
            $this->assertStringContainsString(
                'x-show="uploadErrors[index]"',
                $markup,
                "$role: the failure must render on the row that failed."
            );
            $this->assertStringContainsString(
                'larger than 50 MB',
                $markup,
                "$role: the document error must name the size limit."
            );
        }
    }

    /** #7: the error state must be per-row and stay aligned with rows as they are added/removed. */
    public function test_document_upload_error_state_tracks_row_addition_and_removal(): void
    {
        foreach (self::DOC_TABS as $role => $blade) {
            $markup = $this->source($blade);

            $this->assertStringContainsString('uploadErrors: []', $markup, "$role: per-row error state must be declared");
            $this->assertStringContainsString('this.uploadErrors.push(\'\')', $markup, "$role: addRow must extend the error state");
            $this->assertStringContainsString('this.uploadErrors.splice(index, 1)', $markup, "$role: removeRow must shrink the error state, or errors would attach to the wrong row");
        }
    }

    /** #7: starting a new upload clears the previous error, so a stale message cannot linger. */
    public function test_a_new_document_upload_clears_the_previous_error(): void
    {
        foreach (self::DOC_TABS as $role => $blade) {
            $this->assertStringContainsString(
                "this.uploadErrors[index] = '';",
                $this->source($blade),
                "$role: uploadFile() must reset the row's error before retrying."
            );
        }
    }

    // ── guard: no duplicated handlers left behind ────────────────────────────

    /** The old inline alert must be fully replaced, not left duplicating the boundary. */
    public function test_the_photo_tabs_have_no_leftover_inline_alert_block(): void
    {
        foreach (self::PHOTO_TABS as $role => $blade) {
            $markup = $this->source($blade);

            $this->assertSame(
                1,
                substr_count($markup, '<x-upload-error-boundary'),
                "$role: exactly one boundary per photo surface"
            );
            $this->assertStringNotContainsString(
                "x-data=\"{ uploadErr: '' }\"",
                $markup,
                "$role: the old inline alert block must be gone, not duplicating the boundary."
            );
        }
    }
}
