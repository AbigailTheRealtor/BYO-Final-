<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hire Agent hero redesign — pilot gate
    |--------------------------------------------------------------------------
    |
    | Master switch for the redesigned Hire Agent hero.
    |
    | DEFAULT TRUE as of this change, for the reason set out at length in
    | config/hire_agent_detail.php: the pilot is finished, the hero is verified live
    | for all four roles, and a false default now describes a regression rather than
    | an inert merge. Rollback remains an environment change:
    | HIRE_AGENT_HERO_REDESIGN_ENABLED=false.
    |
    | STILL INDEPENDENT OF THE DETAIL FLAG. The two happen to have moved together
    | this time; that is a fact about today's values, not a merge of the two
    | switches. Either can still be turned off without the other, and
    | HireAgentDetailRedesignFlagTest asserts it in both directions.
    |
    | Nothing reads these keys directly. HireAgentHeroData::redesignEnabledFor()
    | is the single reader, so the on/off decision exists in exactly one place and
    | cannot drift between the component, the role view and the tests.
    |
    */

    'redesign_enabled' => env('HIRE_AGENT_HERO_REDESIGN_ENABLED', true),

    /*
    | Roles the redesign applies to WHILE ENABLED. The landlord pilot completed and
    | the default is now all four roles; narrowing it is a value change here, not a
    | code change.
    |
    | This allowlist is load-bearing and is not made redundant by the presentation
    | -boundary exception granted to the shared hero composition. That exception
    | governs which FILE may consume VIHO; this list governs which ROLES render it.
    */

    'redesign_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HIRE_AGENT_HERO_REDESIGN_ROLES', 'seller,buyer,landlord,tenant'))
    ))),

];
