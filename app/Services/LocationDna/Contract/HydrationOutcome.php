<?php

namespace App\Services\LocationDna\Contract;

/**
 * HydrationOutcome — the distinct states a hydration attempt can land in.
 *
 * G1c contract core. INERT.
 *
 * The G1c authorization requires the hydrator to distinguish an absent document, a valid
 * canonical cleared document, a malformed/quarantined document and an unsupported
 * higher-version document — and explicitly forbids representing every failure as an empty
 * valid document. That is the failure mode G1a proved live:
 * test_s3_corrupt_blob_is_silently_treated_as_an_empty_record.
 *
 * Note that "cleared" is NOT an outcome. A document whose dimensions are all present-but-
 * empty hydrated perfectly well; it is a Hydrated outcome carrying a cleared document. Only
 * the four situations above are outcomes.
 */
enum HydrationOutcome: string
{
    /** A canonical document was produced. It may be empty, populated, or cleared. */
    case Hydrated = 'hydrated';

    /** No document existed at all — meta key absent, null, or an empty string (§5.4 S3). */
    case Absent = 'absent';

    /** Present but uninterpretable. Quarantined with the raw input retained (§5.4 S3). */
    case Malformed = 'malformed';

    /** schema_version newer than this reader supports. Read-only; must not be rewritten (§5.5). */
    case UnsupportedVersion = 'unsupported_version';

    public function isUsable(): bool
    {
        return $this === self::Hydrated;
    }

    /** True when a write must be refused for this outcome (L5, §5.5). */
    public function forbidsWrite(): bool
    {
        return $this === self::Malformed || $this === self::UnsupportedVersion;
    }
}
