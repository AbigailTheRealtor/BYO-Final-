<?php

namespace App\Services\ListingPreferences;

use App\Support\ListingPreferences\ListingPreferenceReasonCatalog;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\ListingPreferenceSubjectRef;
use App\Support\SmartTags\SmartTagContext;

/**
 * What a write left behind: the current state (null = cleared), the reasons
 * that survived the policy, the ones that did not and why, and the context the
 * decision was made in.
 *
 * `rejected` is carried deliberately rather than swallowed. A chip refused
 * because its Smart Tag is not seeker-selectable is a compliance outcome, and a
 * surface that silently dropped it would look as though it had been stored.
 */
final class ListingPreferenceOutcome
{
    /**
     * @param list<string>          $reasons  accepted reason keys
     * @param array<string, string> $rejected requested value => refusal reason
     */
    public function __construct(
        public readonly ?ListingPreferenceState $state,
        public readonly array $reasons,
        public readonly array $rejected,
        public readonly ?SmartTagContext $context,
        public readonly ListingPreferenceSubjectRef $subject,
    ) {
    }

    public function wasCleared(): bool
    {
        return $this->state === null;
    }

    /** True when the context could be resolved — i.e. the strict policy path ran. */
    public function hadContext(): bool
    {
        return $this->context !== null;
    }

    /**
     * The shape a surface renders. Deliberately carries no model id: the
     * browser never needs the primary key, and not sending it is one fewer
     * internal identifier on the wire.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state'   => $this->state?->value,
            'reasons' => array_values(array_map(
                static fn (string $key): array => [
                    'key'   => $key,
                    'label' => ListingPreferenceReasonCatalog::get($key)?->label ?? $key,
                ],
                $this->reasons,
            )),
            'rejected' => $this->rejected,
            'cleared'  => $this->wasCleared(),
        ];
    }
}
