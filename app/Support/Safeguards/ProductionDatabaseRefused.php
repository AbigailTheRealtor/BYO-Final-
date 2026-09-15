<?php

namespace App\Support\Safeguards;

use RuntimeException;

/**
 * Thrown by fixture seeders that resolve to production. A seeder has no exit code of its own, and
 * an exception is what makes `db:seed` stop and exit non-zero instead of printing a line and
 * reporting success.
 */
final class ProductionDatabaseRefused extends RuntimeException
{
    /**
     * Refuse if the running application resolves to production. No override: nothing that calls
     * this has a legitimate production use.
     *
     * @throws self
     */
    public static function unlessSafe(string $subject, string $invocation): void
    {
        $assessment = ProductionDatabaseGuard::assessApplication();

        if ($assessment->isProduction()) {
            throw new self($assessment->refusalMessage($subject, false, $invocation));
        }
    }
}
