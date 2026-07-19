<?php

namespace App\Http\Livewire\Concerns;

/**
 * HI-04 — Hire-Agent upload hardening.
 *
 * Shared, content-based validation for the marketing photo/video uploads on the
 * four Hire-Agent flows (Seller / Buyer / Landlord / Tenant, create + edit).
 * Before this trait those uploads were stored with no MIME/size validation —
 * only the client-supplied extension was read to name the file, so executables,
 * scripts, SVG (script-carrying), archives, and MIME-spoofed files were accepted.
 *
 * The rules here validate actual file CONTENT, not just the filename:
 *   - photos use Laravel's `image` rule (content sniff via getimagesize) plus a
 *     `mimes` extension allow-list;
 *   - videos use `mimetypes`, which checks the real MIME guessed from content.
 *
 * SVG is intentionally excluded (it can carry active script). HEIC is out of
 * scope for this batch.
 */
trait ValidatesMediaUploads
{
    /** Accepted image extensions (content-verified via the `image` rule). */
    public static array $mediaPhotoMimes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Accepted video content types (verified against the real MIME, not extension). */
    public static array $mediaVideoMimeTypes = [
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/mpeg',
    ];

    /** Max photo size in KB (10 MB). */
    public static int $mediaPhotoMaxKb = 10240;

    /** Max video size in KB (50 MB — aligned with the Livewire temp-upload ceiling). */
    public static int $mediaVideoMaxKb = 51200;

    /**
     * Validate the component's $photo / $video properties when they hold a fresh
     * upload. A string value means an already-stored path being re-saved on edit,
     * which is skipped so a resubmit without a new file still passes. Absent or
     * null uploads are optional and skipped (nullable shape preserved).
     */
    protected function validateMediaUploads(): void
    {
        $rules    = [];
        $messages = [];

        if (isset($this->photo) && $this->photo && !is_string($this->photo)) {
            $rules['photo'] = 'image|mimes:' . implode(',', self::$mediaPhotoMimes)
                . '|max:' . self::$mediaPhotoMaxKb;
            $messages['photo.image'] = 'The photo must be a valid image file.';
            $messages['photo.mimes'] = 'The photo must be a ' . implode(', ', self::$mediaPhotoMimes) . ' file.';
            $messages['photo.max']   = 'The photo may not be larger than ' . (int) (self::$mediaPhotoMaxKb / 1024) . ' MB.';
        }

        if (isset($this->video) && $this->video && !is_string($this->video)) {
            $rules['video'] = 'mimetypes:' . implode(',', self::$mediaVideoMimeTypes)
                . '|max:' . self::$mediaVideoMaxKb;
            $messages['video.mimetypes'] = 'The video must be an MP4, MOV, AVI, MKV, or MPEG file.';
            $messages['video.max']       = 'The video may not be larger than ' . (int) (self::$mediaVideoMaxKb / 1024) . ' MB.';
        }

        if (!empty($rules)) {
            $this->validate($rules, $messages);
        }
    }
}
