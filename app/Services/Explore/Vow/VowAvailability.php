<?php

namespace App\Services\Explore\Vow;

use App\Services\Explore\ExploreAccessTier;

/**
 * Whether this installation has a VOW capability at all.
 *
 * IT DOES NOT. THAT IS THE FINDING, NOT A PLACEHOLDER.
 * ----------------------------------------------------
 * A repository-wide audit on 2026-09-10 found, for Virtual Office Website
 * access: no MLS approval or agreement recorded, no VOW-capable dataset
 * configured, no credentials, no consumer registration or terms-acknowledgement
 * flow, no policy or field-filtering classes, and no VOW field of any kind
 * among the 551 distinct Property fields this feed actually returns. The only
 * occurrences of the word in the codebase are in docs/, every one of them
 * listing VOW settings as things NOT to import.
 *
 * So this class reports MISSING and {@see decideTier()} returns PUBLIC_IDX for
 * every caller, unconditionally.
 *
 * WHY THE FLAG IS NOT THE GATE
 * ----------------------------
 * `explore.vow_enabled` exists and defaults false, but flipping it changes
 * nothing here. A boolean in a config file is the wrong last line of defence
 * between an unapproved licence tier and a member of the public: it can be set
 * by an environment variable, by a stale secret, by somebody testing. The
 * refusal is in the code, and the flag is checked only so that
 * `activationRequirements()` can report honestly on the whole posture.
 *
 * WHAT WOULD HAVE TO BE TRUE TO REMOVE THIS
 * -----------------------------------------
 * `activationRequirements()` is the list, deliberately in code rather than in a
 * document, so that the next person to ask "can we turn VOW on" reads the
 * answer next to the thing that refuses.
 *
 * DELIBERATELY NOT Auth::check(). A logged-in user is not a VOW-registered
 * consumer, and implementing the gate as "authenticated" would be a fake VOW
 * gate wearing a real one's name.
 */
final class VowAvailability
{
    public const STATUS_MISSING = 'MISSING';

    /**
     * Is a VOW capability present in this installation?
     *
     * Always false. Written as a method rather than a constant so that the one
     * place this becomes true is a code change with a diff and a reviewer.
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /** 'MISSING', for the operator-facing posture report. */
    public function status(): string
    {
        return self::STATUS_MISSING;
    }

    /** Whether the operator has ASKED for VOW, which is not whether they have it. */
    public function flagRequested(): bool
    {
        return (bool) config('explore.vow_enabled', false);
    }

    /**
     * The access tier for a consumer.
     *
     * Takes the user so that the signature is already the one a real VOW
     * eligibility check needs, and ignores it because there is nothing to check
     * it against. When a genuine VOW contract exists this method grows a real
     * decision; every caller is already shaped for it.
     */
    public function decideTier(?object $user = null): ExploreAccessTier
    {
        if (! $this->isAvailable()) {
            return ExploreAccessTier::PUBLIC_IDX;
        }

        // Unreachable today. Left as a single explicit statement so that the
        // future eligibility check has exactly one home.
        return ExploreAccessTier::PUBLIC_IDX; // @codeCoverageIgnore
    }

    /**
     * Everything that must be established before Property Intelligence can be
     * built, in the order it has to happen.
     *
     * @return list<string>
     */
    public function activationRequirements(): array
    {
        return [
            'A signed Stellar MLS VOW agreement covering this application, recorded in the repository.',
            'A Bridge/Stellar dataset provisioned for VOW access, with its own credentials — the '
                . 'existing BRIDGE_DATASET is an IDX dataset and must not be reused to imply VOW rights.',
            'A written field-by-field determination of which VOW fields may be shown to a registered '
                . 'consumer, which are internal, and which are prohibited. Bridge returning a field is '
                . 'not permission to display it.',
            'A consumer VOW registration flow: identity, a bona fide relationship, and an accepted '
                . 'terms-of-use acknowledgement, with the acceptance recorded and revocable.',
            'A written determination on historical media. Prior-listing photographs, tours and video '
                . 'are NOT covered by any permission in this repository and are assumed prohibited.',
            'A retention and display-duration rule for historical records.',
            'Access logging and abuse controls sufficient for the VOW rules, since a VOW tier is '
                . 'auditable in a way an IDX surface is not.',
        ];
    }
}
