<?php

namespace App\Support\ListingPreferences;

use App\Services\ListingPreferences\ListingPreferenceReader;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;

/**
 * One request's worth of already-batched preference reads, so the shared
 * control can render many times without asking per card.
 *
 * WHY THIS EXISTS RATHER THAN A SECOND COMPONENT
 * ----------------------------------------------
 * `x-listing-preference.control` is deliberately self-sufficient: it takes a
 * listing reference and resolves everything itself, which is what makes it
 * droppable onto any surface. That is exactly right for a detail page with one
 * listing on it, and exactly wrong for a results page with 150 — the same two
 * reads per card become 300 queries for one screen.
 *
 * The fix is NOT a card-specific component with its own logic. It is a place to
 * put the answers BEFORE the loop runs, which the one component then finds
 * already waiting. A page primes this (see `x-listing-preference.prefetch`),
 * every control on it is served from memory, and a control on a page that
 * primed nothing falls back to its own single-listing reads exactly as before.
 *
 * REQUEST-SCOPED, AND BOUND AS A SINGLETON. It holds one viewer's answers about
 * a page they are looking at now; it is not a cache and must never outlive the
 * request. `forget()` exists for tests, which render several pages in one
 * process.
 *
 * IT DECIDES NOTHING. Every value in here came from ListingPreferenceReader —
 * the same reader the unprimed path uses — so a primed render and an unprimed
 * render cannot disagree about a customer's state or a listing's context.
 */
class ListingPreferencePrefetch
{
    /** @var array<string, array{state: ?string, reasons: list<string>}> */
    private array $currents = [];

    /** @var array<string, ?SmartTagContext> */
    private array $contexts = [];

    /**
     * Which viewer the primed states belong to.
     *
     * Stored so a second prime for a DIFFERENT viewer cannot be served the
     * first viewer's answers. In one request that should be impossible, but
     * "should be impossible" is not a guarantee, and the failure mode here is
     * showing one customer another customer's preferences.
     */
    private ?string $viewerKey = null;

    public function __construct(private readonly ListingPreferenceReader $reader)
    {
    }

    /**
     * Resolve contexts and, for a signed-in seeker, current states for a page
     * of listings — in batch.
     *
     * Safe to call more than once per request: ids already primed are not
     * re-read, so two card sections on one page cost one extra query for the
     * listings the first section did not cover.
     *
     * @param list<int>|iterable<int> $listingIds
     */
    public function prime(
        SmartTagListingType $type,
        iterable $listingIds,
        ?int $userId = null,
        ?SeekerRole $role = null,
    ): void {
        $viewerKey = $userId !== null && $role !== null ? "{$userId}:{$role->value}" : null;

        // A different viewer in the same request invalidates what was primed;
        // answers are per-customer and must never be reused across them.
        if ($this->viewerKey !== null && $viewerKey !== null && $viewerKey !== $this->viewerKey) {
            $this->currents = [];
        }

        if ($viewerKey !== null) {
            $this->viewerKey = $viewerKey;
        }

        $contextRefs = [];
        $stateRefs   = [];

        foreach ($listingIds as $id) {
            $id = (int) $id;

            if ($id <= 0) {
                continue;
            }

            $key = "{$type->value}:{$id}";
            $ref = new SmartTagListingRef($type, $id);

            if (! array_key_exists($key, $this->contexts)) {
                $contextRefs[$key] = $ref;
            }

            if ($viewerKey !== null && ! array_key_exists($key, $this->currents)) {
                $stateRefs[$key] = $ref;
            }
        }

        if ($contextRefs !== []) {
            $this->contexts += $this->reader->contextForMany(array_values($contextRefs));
        }

        if ($stateRefs !== [] && $userId !== null && $role !== null) {
            $this->currents += $this->reader->currentMany($userId, $role, array_values($stateRefs));
        }
    }

    public function hasContext(SmartTagListingRef $ref): bool
    {
        return array_key_exists($this->key($ref), $this->contexts);
    }

    public function context(SmartTagListingRef $ref): ?SmartTagContext
    {
        return $this->contexts[$this->key($ref)] ?? null;
    }

    public function hasCurrent(int $userId, SeekerRole $role, SmartTagListingRef $ref): bool
    {
        return $this->viewerKey === "{$userId}:{$role->value}"
            && array_key_exists($this->key($ref), $this->currents);
    }

    /** @return array{state: ?string, reasons: list<string>} */
    public function current(SmartTagListingRef $ref): array
    {
        return $this->currents[$this->key($ref)] ?? ['state' => null, 'reasons' => []];
    }

    /** Test seam: a single process renders many pages for many viewers. */
    public function forget(): void
    {
        $this->currents  = [];
        $this->contexts  = [];
        $this->viewerKey = null;
    }

    private function key(SmartTagListingRef $ref): string
    {
        return "{$ref->type->value}:{$ref->id}";
    }
}
