<?php

namespace App\Services\Location\Suggestions;

/**
 * A source of address suggestions for a partially-typed address.
 *
 * Deliberately a sibling of {@see \App\Services\Location\Coordinates\CoordinateProviderAdapterInterface}
 * rather than an extension of it, because the two answer different questions:
 *
 *   suggestion  "what addresses might this person be typing?"   many answers
 *   coordinate  "where is this specific property?"              one answer
 *
 * Collapsing them is tempting — one provider can often do both — and it is what
 * makes a suggestion silently become a property's location. Keeping the
 * contracts apart means a provider that is good enough to help somebody type is
 * not thereby trusted to place a pin. See {@see AddressCandidate}.
 *
 * SHAPE MIRRORS THE COORDINATE CONTRACT ON PURPOSE
 * ------------------------------------------------
 * `providerId()`, `requiresNetwork()` and `isAvailable()` mean exactly what they
 * mean on the coordinate adapter, so budgets, caps, telemetry and "is this
 * allowed to run right now" reasoning transfer without a second vocabulary. A
 * suggestion surface fires per keystroke, so `requiresNetwork()` is the more
 * consequential of the two here, not less.
 *
 * NOTHING IMPLEMENTS THIS YET
 * ---------------------------
 * No implementation, no container binding, no route, no Livewire caller. Adding
 * a local corpus implementation is the next step; a network one is a separate
 * decision with its own review, and this contract is not that decision.
 */
interface AddressSuggestionProviderInterface
{
    /**
     * Stable identifier recorded on every candidate and used for budgets, caps
     * and telemetry. E.g. 'address_point'.
     */
    public function providerId(): string;

    /**
     * True when producing suggestions reaches the network.
     *
     * A suggestion surface is typed into, so a network provider here costs one
     * request per keystroke unless something debounces it. Callers must be able
     * to know that before calling rather than after the bill.
     */
    public function requiresNetwork(): bool;

    /**
     * True when this provider is currently permitted to run — corpus present,
     * credentials present, feature flag on.
     *
     * Must be answerable without a network call, for the same reason as on the
     * coordinate adapter: an unavailable provider is skipped, never awaited.
     */
    public function isAvailable(): bool;

    /**
     * Suggestions for a partial address, best first.
     *
     * Returns an empty list rather than throwing when nothing matches — "no
     * suggestions" is an ordinary answer while somebody is still typing, and a
     * caller must not have to distinguish it from a fault in order to render an
     * empty dropdown. Reserve exceptions for genuine provider faults.
     *
     * @param  string $query partial address as typed
     * @param  int    $limit maximum candidates to return
     * @return list<AddressCandidate>
     */
    public function suggest(string $query, int $limit = 10): array;
}
