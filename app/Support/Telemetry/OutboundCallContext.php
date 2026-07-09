<?php

namespace App\Support\Telemetry;

/**
 * Ambient listing context for outbound-call telemetry.
 *
 * GoogleOutboundTelemetryMiddleware observes the HTTP client, which knows nothing
 * about listings. Callers that enrich a specific listing set the context here so
 * every outbound request can be attributed to the listing that provoked it.
 *
 * The context is overwritten by each caller and is never read for behaviour — only
 * for logging. A stale value therefore mislabels a log line; it can never mislead
 * the application.
 */
class OutboundCallContext
{
    private static ?string $listingType = null;

    private static ?int $listingId = null;

    public static function for(?string $listingType, ?int $listingId): void
    {
        self::$listingType = $listingType;
        self::$listingId   = $listingId;
    }

    public static function clear(): void
    {
        self::for(null, null);
    }

    public static function listingType(): ?string
    {
        return self::$listingType;
    }

    public static function listingId(): ?int
    {
        return self::$listingId;
    }
}
