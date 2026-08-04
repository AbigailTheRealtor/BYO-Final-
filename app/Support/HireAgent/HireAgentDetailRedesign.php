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
}
