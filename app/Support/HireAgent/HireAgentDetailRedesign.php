<?php

namespace App\Support\HireAgent;

/**
 * The single reader of the M5 detail-page redesign flag.
 *
 * Centralised for the same reason HireAgentHeroData::redesignEnabledFor() is: the views and the
 * tests must not be able to disagree about what "enabled" means. A view that read
 * config('hire_agent_detail.redesign_enabled') directly would be a second opinion, and a second
 * opinion is how the M4 hero came to publish data the page body was gating — see
 * docs/investigations/hire-agent-compensation-visibility-decision.md.
 *
 * This class holds NO presentation and NO data. It answers one question. It deliberately does not
 * know which role is being rendered: the redesign's markup lives in the landlord role view, so
 * role scope is a property of which files exist, not of this flag. When a second role adopts the
 * redesign, this class gains a role-aware method and the config gains an allowlist — mirroring the
 * hero — rather than callers inventing their own role checks.
 */
class HireAgentDetailRedesign
{
    /**
     * Is the redesigned Hire Agent detail page active?
     *
     * Defaults to false when the key is missing entirely, not just when it is set false, so a
     * config file that failed to load cannot read as "on".
     */
    public static function enabled(): bool
    {
        return (bool) config('hire_agent_detail.redesign_enabled', false);
    }

    /**
     * Is the redesign active for THIS role?
     *
     * M7.1. The master switch above answers "is the redesign on at all"; this answers "may this
     * role have it". Both must agree, mirroring HireAgentHeroData::redesignEnabledFor().
     *
     * WHY A SECOND METHOD RATHER THAN A ROLE ARGUMENT ON enabled(). Every existing caller of
     * enabled() sits inside hire_landlord_agent/view.blade.php, where the role is a property of
     * the file rather than a value. Adding a required argument would have meant editing markup
     * this milestone is not reviewing, to pass a constant those call sites already imply. The two
     * methods answer two genuinely different questions and the class stays the single reader,
     * which is the property that matters.
     *
     * THE SHARED SHELL MUST USE THIS ONE. detail-shell.blade.php renders for all four roles, so
     * a caller there asking enabled() would flip seller, buyer and tenant on landlord's switch.
     *
     * Comparison is exact and case-sensitive against the configured list. No normalisation, no
     * aliases, no prefix matching: a role either appears in the allowlist or it does not, and a
     * typo must fail closed rather than resolve to something that looks close enough.
     */
    public static function enabledFor(string $role): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $roles = config('hire_agent_detail.redesign_roles', []);

        return is_array($roles) && in_array($role, $roles, true);
    }
}
