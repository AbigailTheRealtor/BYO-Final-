<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hire Agent detail page redesign — M5
    |--------------------------------------------------------------------------
    |
    | Master switch for the redesigned Hire Agent listing detail page: section
    | navigation, quick actions, sidebar, information cards and photo gallery.
    | Default false means the branch is INERT — merging it changes nothing on any
    | page, and rollback is an environment change rather than a revert.
    |
    | SEPARATE FROM THE HERO FLAG, DELIBERATELY. config/hire_agent_hero.php gates
    | the M4 hero and is currently enabled for landlord in at least one
    | environment. Reusing it would have coupled two rollouts that need to move
    | independently: the hero is being visually verified now, while the detail
    | rebuild is still being written. One switch would mean neither could be
    | turned off without turning off the other.
    |
    | Nothing reads this key directly. HireAgentDetailRedesign::enabled() is the
    | single reader, so the on/off decision exists in exactly one place and cannot
    | drift between the views and the tests.
    |
    */

    'redesign_enabled' => env('HIRE_AGENT_DETAIL_REDESIGN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Role allowlist — M7.1
    |--------------------------------------------------------------------------
    |
    | THE CONDITION THE OLD NOTE DESCRIBED HAS NOW ARRIVED. It read, in full:
    |
    |     NO ROLE ALLOWLIST, AND WHY. The hero flag carries one because the hero
    |     is a SHARED component rendered by all four role views — a bare boolean
    |     there would have flipped four roles at once. The detail redesign is
    |     different in kind: its markup lives in the landlord role view, so there
    |     is nothing for a boolean to reach in the seller, buyer or tenant views.
    |     Role scope is enforced by which files exist, not by configuration.
    |
    |     That stops being true the moment a second role adopts the redesign. At
    |     that point add `redesign_roles` here mirroring config/hire_agent_hero.php,
    |     rather than letting one switch migrate a role nobody reviewed.
    |
    | M7.1 moves the page LAYOUT into components/hire-agent/detail-shell.blade.php,
    | which every one of the four role views renders. From this milestone on, the
    | premise "its markup lives in the landlord role view" is false: a bare boolean
    | in the shell would change the grid for seller, buyer and tenant on the same
    | switch that turns landlord on. That is precisely the migration-without-review
    | the note warned about, so the allowlist arrives with the shared markup rather
    | than after it.
    |
    | INDEPENDENT OF THE MASTER SWITCH, and both must agree — the same contract
    | config/hire_agent_hero.php uses. Widening this list is a rollout decision, not
    | a code change, and it is the ONLY thing that grants a role the new layout.
    |
    | Read exclusively through HireAgentDetailRedesign::enabledFor($role). Nothing
    | may read this key directly, and no Blade file may test a role name inline —
    | an inline check is a second opinion about rollout scope, and the reason this
    | key exists is so there is only one.
    |
    */

    'redesign_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HIRE_AGENT_DETAIL_REDESIGN_ROLES', 'landlord'))
    ))),

];
