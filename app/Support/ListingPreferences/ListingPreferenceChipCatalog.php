<?php

namespace App\Support\ListingPreferences;

use App\Services\ListingPreferences\ListingPreferenceReader;
use App\Support\SmartTags\SmartTagContext;

/**
 * The reason chips a page needs, emitted once per CONTEXT instead of once per
 * card.
 *
 * THE PROBLEM THIS SOLVES. The chip catalog is the whole offerable reason
 * vocabulary for a listing's context, and it was serialised into a data
 * attribute on every control. One detail page, fine. A results page of 150
 * cards, and the identical payload is in the DOM 150 times.
 *
 * ONE PER CONTEXT, NOT ONE PER PAGE, and that distinction is correctness rather
 * than thrift. `offerableChips()` is a function of the listing's context — a
 * residential sale and a commercial lease do not offer the same reasons — and a
 * results page can legitimately mix them. A single page-wide blob would have to
 * pick one context and would then offer some listings the wrong chips, which is
 * precisely the "second vocabulary" failure the catalog exists to prevent.
 *
 * So each control carries a short context TOKEN, and the payload for a token is
 * emitted the first time that token appears in the request. The browser looks
 * the token up. Mixed pages stay correct; uniform pages — which is almost all of
 * them — carry exactly one payload.
 *
 * NO CHIP IS DEFINED HERE. Everything comes from ListingPreferenceReader, which
 * reads the governed catalog with its Fair Housing and seeker-selectability
 * filtering intact. This class decides WHEN a payload is written into the page,
 * never WHAT is in it.
 *
 * Request-scoped and bound as a singleton for the same reason
 * {@see ListingPreferencePrefetch} is: "already emitted" is a fact about one
 * HTML document.
 */
class ListingPreferenceChipCatalog
{
    /** Context tokens whose payload has already been written into this response. */
    private array $emitted = [];

    /** The token used for a listing whose context could not be resolved. */
    public const UNRESOLVED_TOKEN = '_unresolved';

    public function __construct(private readonly ListingPreferenceReader $reader)
    {
    }

    public function token(?SmartTagContext $context): string
    {
        return $context?->value ?? self::UNRESOLVED_TOKEN;
    }

    /**
     * The chip payload for this context if it has not been emitted yet, else
     * null.
     *
     * Null means "already in the page" — the caller renders nothing and the
     * control still points at the token.
     *
     * @return array<string, array{prompt: string, chips: list<array{key: string, label: string}>}>|null
     */
    public function takePayload(?SmartTagContext $context): ?array
    {
        $token = $this->token($context);

        if (isset($this->emitted[$token])) {
            return null;
        }

        $this->emitted[$token] = true;

        return $this->reader->offerableChips($context);
    }

    /** Test seam: one process renders many documents. */
    public function forget(): void
    {
        $this->emitted = [];
    }
}
