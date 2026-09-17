<?php

namespace App\Support\VirtualDrive;

/**
 * Whether the Virtual Drive provider proof may answer at all.
 *
 * ENVIRONMENT FIRST, FLAG SECOND
 * ------------------------------
 * The proof is development-only, and that must not depend on somebody leaving
 * a flag alone. The environment is an allow-list, checked before the flag is
 * read: production is refused explicitly, and so is anything that is not
 * named (`staging`, a typo, an unset APP_ENV that defaulted to `production`).
 * Only then does VIRTUAL_DRIVE_PROOF_ENABLED — itself parsed fail-closed —
 * get a say.
 *
 * `testing` is on the list so the gate itself can be tested; the flag still
 * defaults off there, so a test that does not switch it on sees 404s.
 */
final class VirtualDriveProofGate
{
    public const ALLOWED_ENVIRONMENTS = ['local', 'development', 'testing'];

    public static function allows(): bool
    {
        if (app()->environment('production') || ! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            return false;
        }

        return config('virtual_drive.proof_enabled') === true;
    }
}
