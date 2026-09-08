<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hire Agent detail page redesign — M5
    |--------------------------------------------------------------------------
    |
    | Master switch for the redesigned Hire Agent listing detail page: section
    | navigation, quick actions, sidebar, information cards and photo gallery.
    |
    | THE DEFAULT IS NOW TRUE, AND THE REASON IT CHANGED MATTERS. It shipped false
    | so that merging M5.0 was inert. That rollout has finished: the redesign is
    | the platform's design for all four roles and has been verified live. A false
    | default now means the OPPOSITE of safe — it means an environment that loses
    | its variables silently serves the superseded layout, which is exactly what a
    | container rebuild did when these values lived only in a machine-local `.env`.
    | Rollback is still an environment change, not a revert: set
    | HIRE_AGENT_DETAIL_REDESIGN_ENABLED=false.
    |
    | THE READER KEEPS ITS OWN `false` FALLBACK FOR A MISSING KEY, and that is a
    | different question from this default. A config file that failed to load must
    | still read as off; HireAgentDetailRedesign::enabled() is where that rule
    | lives, and it is unchanged.
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

    'redesign_enabled' => env('HIRE_AGENT_DETAIL_REDESIGN_ENABLED', true),

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
    | config/hire_agent_hero.php uses. It is the ONLY thing that grants a role the
    | new layout.
    |
    | THE DEFAULT IS NOW ALL FOUR ROLES rather than the landlord pilot. The pilot is
    | over. Leaving 'landlord' here would mean an environment that lost its
    | variables served three roles the old layout and one the new — a worse state
    | than either being uniformly wrong, because a mixed platform reads as a
    | rendering bug rather than as a missing variable. NARROWING this list is still
    | a rollout decision and still a value change, not a code change.
    |
    | Read exclusively through HireAgentDetailRedesign::enabledFor($role). Nothing
    | may read this key directly, and no Blade file may test a role name inline —
    | an inline check is a second opinion about rollout scope, and the reason this
    | key exists is so there is only one.
    |
    */

    'redesign_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HIRE_AGENT_DETAIL_REDESIGN_ROLES', 'seller,buyer,landlord,tenant'))
    ))),

];
