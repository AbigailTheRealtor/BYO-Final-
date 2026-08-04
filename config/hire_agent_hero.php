<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hire Agent hero redesign — pilot gate
    |--------------------------------------------------------------------------
    |
    | Master switch for the redesigned Hire Agent hero. Default false means the
    | branch is INERT: merging it changes nothing on any page, and rollback is an
    | environment change rather than a revert.
    |
    | Nothing reads these keys directly. HireAgentHeroData::redesignEnabledFor()
    | is the single reader, so the on/off decision exists in exactly one place and
    | cannot drift between the component, the role view and the tests.
    |
    */

    'redesign_enabled' => env('HIRE_AGENT_HERO_REDESIGN_ENABLED', false),

    /*
    | Roles the redesign applies to WHILE ENABLED. The pilot ships landlord only;
    | rollout to a further role is a value change here, not a code change.
    |
    | This allowlist is load-bearing and is not made redundant by the presentation
    | -boundary exception granted to the shared hero composition. That exception
    | governs which FILE may consume VIHO; this list governs which ROLES render it.
    */

    'redesign_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HIRE_AGENT_HERO_REDESIGN_ROLES', 'landlord'))
    ))),

];
