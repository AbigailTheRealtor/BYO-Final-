<?php

namespace App\Services\SmartTags\Derivation;

/**
 * What the one public listing description amounted to for this listing.
 *
 * TWO KINDS OF NOTHING, and the difference is the whole reason this type exists.
 * `read()` returns null both when the landlord wrote no description and when
 * LandlordProviderTextPolicy withheld the one they wrote. Derivation treats those
 * identically — correctly, since neither may be parsed — but an operator reading
 * a log line needs to tell them apart: the first is a listing with no prose, the
 * second is a Fair Housing suppression doing its job.
 *
 * Carries the text because the parser needs it. Carries no reason string, no
 * matched phrase and no category: the log line records the BOOLEAN only, and a
 * field that could hold prose is a field that eventually holds prose.
 */
final class NativeDescription
{
    private function __construct(
        public readonly ?string $text,
        public readonly bool $suppressedByPolicy,
    ) {
    }

    /** No description was stored, or this listing type publishes none. */
    public static function absent(): self
    {
        return new self(null, false);
    }

    /** Text was stored, and the provider-text policy withholds it from the public page. */
    public static function suppressed(): self
    {
        return new self(null, true);
    }

    public static function published(string $text): self
    {
        return new self($text, false);
    }

    /** True when there is prose the parser may read. */
    public function isParsable(): bool
    {
        return $this->text !== null;
    }
}
