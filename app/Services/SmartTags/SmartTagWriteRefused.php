<?php

namespace App\Services\SmartTags;

use RuntimeException;

/**
 * A Smart Tag write that must not happen at all — the wrong source for the
 * listing type, a source that is not approved, a context the listing cannot be in.
 */
final class SmartTagWriteRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
