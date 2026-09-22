<?php

namespace App\Support\ListingPreferences\Taste;

use App\Support\ListingPreferences\SeekerRole;

/**
 * One customer's Taste DNA for ONE side of the market.
 *
 * Per user AND per seeker role, never merged: buying and renting are different
 * intents about different inventory, which is why `seeker_role` is part of every
 * preference's identity in the first place.
 *
 * DERIVED, NEVER STORED. Every instance is recomputed from the append-only event
 * history, so there is no second copy that can drift from it, nothing to
 * migrate, and "rebuild" is simply "compute again" — which TasteDnaServiceTest
 * proves yields identical output from identical history.
 */
final class TasteProfile
{
    /**
     * @param list<TasteSignal> $signals ordered deterministically by the deriver
     */
    public function __construct(
        public readonly int $userId,
        public readonly SeekerRole $role,
        public readonly array $signals,
        public readonly int $homeCount,
        public readonly int $choiceCount,
        public readonly string $rulesVersion,
        public readonly bool $complete = true,
    ) {
    }

    public static function empty(int $userId, SeekerRole $role): self
    {
        return new self($userId, $role, [], 0, 0, TasteDnaDeriver::RULES_VERSION);
    }

    /**
     * The customer's history was longer than could be read whole, so nothing
     * was derived. Not "no taste": an explicitly UNKNOWN one, and the page says
     * so rather than showing patterns drawn from part of the story.
     */
    public static function incomplete(int $userId, SeekerRole $role): self
    {
        return new self($userId, $role, [], 0, 0, TasteDnaDeriver::RULES_VERSION, false);
    }

    /** @return list<TasteSignal> */
    public function displayable(): array
    {
        // Defence in depth: an incomplete profile never has a customer-facing
        // signal, whatever a later change might put in `signals`.
        if (! $this->complete) {
            return [];
        }

        return array_values(array_filter($this->signals, static fn (TasteSignal $s): bool => $s->isDisplayable()));
    }

    public function signal(TasteDimension $dimension, string $key): ?TasteSignal
    {
        foreach ($this->signals as $signal) {
            if ($signal->dimension === $dimension && $signal->key === $key) {
                return $signal;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id'       => $this->userId,
            'seeker_role'   => $this->role->value,
            'rules_version' => $this->rulesVersion,
            'complete'      => $this->complete,
            'home_count'    => $this->homeCount,
            'choice_count'  => $this->choiceCount,
            'signals'       => array_map(static fn (TasteSignal $s): array => $s->toArray(), $this->signals),
        ];
    }
}
