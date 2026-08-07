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
 * This class holds NO presentation and NO data. It answers two questions: whether the redesign is
 * on at all, and whether a given role may have it.
 *
 * IT USED TO ANSWER ONLY THE FIRST, and said so here — role scope was "a property of which files
 * exist" because the redesigned markup lived solely in the landlord role view. M7.1 moved the page
 * layout into components/hire-agent/detail-shell.blade.php, which all four role views render, and
 * the premise died with it: a bare boolean there would flip seller, buyer and tenant on landlord's
 * switch. The role allowlist in config/hire_agent_detail.php and enabledFor() below arrived in that
 * same milestone, so scope is now configuration rather than file topology.
 *
 * GATING GOES THROUGH enabledFor($role), never enabled(). No view calls enabled() — the landlord
 * body's three call sites moved in M7.1 as well, because the body gating on the master switch while
 * the shell gated on the role let the page render redesign markup without the stylesheet that lays
 * it out. Views failing OPEN into a broken page is the wrong direction for a rollout switch, and
 * HireAgentDetailRedesignFlagTest holds that line.
 */
class HireAgentDetailRedesign
{
    /**
     * Is the detail redesign switched on at all?
     *
     * NOT A GATE, and true here does not mean any role has the redesign — the allowlist decides
     * that. Callers deciding what to render want enabledFor($role); see its note.
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
     * EVERY GATE MUST USE THIS ONE — the shared shell, which renders for all four roles, and the
     * landlord body alike. A gate asking enabled() would flip seller, buyer and tenant on
     * landlord's switch, and mixing the two readers within one page is what once let the body
     * render redesign markup that the shell had withheld the stylesheet from.
     *
     * enabled() SURVIVES AS A SEPARATE METHOD because "is the redesign on at all" is a real
     * question, distinct from "may this role have it" — the flag tests ask it directly. It is
     * neither deprecated nor removed; it is simply not a gate, and no view may call it.
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
