<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flags a production release must not start without
    |--------------------------------------------------------------------------
    |
    | Every entry below is a config key that MUST resolve to the stated value
    | before deploy/start-production.sh will serve traffic. `deploy:require-flags`
    | is the only reader of this file, and RequiredProductionFlagsTest asserts
    | that it stays the only one — a second reader is a second opinion about what
    | production requires.
    |
    | WHY A GATE WHEN THE DEFAULTS ARE ALREADY CORRECT
    | ------------------------------------------------
    | The shipped defaults handle the ABSENT variable: an environment that
    | supplies nothing at all now gets the modern platform. They cannot handle a
    | variable that is PRESENT AND WRONG — a stale secret, a typo, a value left
    | behind by a finished pilot. That is the case this gate exists for, and it is
    | the invisible one: the application boots, answers 200, passes its health
    | check, and serves the superseded surface until a person happens to notice.
    |
    | WHAT MAY GO IN HERE
    | -------------------
    | Only product surfaces that are finished, verified live, and expected to be
    | on for everyone. A rollout still in progress does NOT belong here — pinning
    | one would convert a rollout dial into a deploy blocker, and the next person
    | who narrows a role list to investigate something would be unable to deploy.
    |
    | WHAT MAY NEVER GO IN HERE
    | -------------------------
    | Anything that is a SAFETY switch. The BYA compatibility kill switch and GA
    | flag, every Location DNA gate, the Census geocoder, the address-point
    | corpus, MLS Match Check, DNA score generation, Matching V2 persistence and
    | the Bridge credentials are all deliberately absent, and their absence is
    | asserted by a test rather than left to reviewer memory. This gate must never
    | become the thing that switches one of them on: it would be a deploy-time
    | mechanism for enabling a consumer-facing or spend-incurring feature, decided
    | in a file nobody reads during a rollout conversation.
    |
    | THE COMMAND IS READ-ONLY IN BOTH DIRECTIONS. It compares and reports. It
    | cannot set a flag, repair one, or reach a database, so a wrong value here
    | stops a deploy — it never silently corrects one.
    |
    | THE ESCAPE HATCH
    | ----------------
    | REQUIRED_PRODUCTION_FLAGS_ENFORCED=false downgrades the gate to a loud
    | warning for as long as it is set. It is a documented way out of a bad night,
    | not a default, and the command says so in capitals when it is taken — a
    | disabled gate that logs quietly is a gate nobody knows is disabled.
    |
    */

    'enforced' => env('REQUIRED_PRODUCTION_FLAGS_ENFORCED', true),

    /*
    | Each entry: config key => ['expect' => …, 'why' => …].
    |
    | A true/false 'expect' is compared as a boolean.
    |
    | An ARRAY 'expect' is a SUBSET requirement: the resolved list must contain
    | every named value, and may contain more. That is deliberate — a fifth role
    | adopting the redesign later must not fail a deployment for being extra. The
    | contract is "at least these", never "exactly these".
    */
    'required' => [

        'hire_agent_hero.redesign_enabled' => [
            'expect' => true,
            'why'    => 'The Hire Agent hero redesign is the platform design; off serves a superseded hero.',
        ],

        'hire_agent_hero.redesign_roles' => [
            'expect' => ['seller', 'buyer', 'landlord', 'tenant'],
            'why'    => 'The hero pilot is complete; every role must render the current hero.',
        ],

        'hire_agent_detail.redesign_enabled' => [
            'expect' => true,
            'why'    => 'The Hire Agent detail redesign is the platform design; off serves the superseded layout.',
        ],

        'hire_agent_detail.redesign_roles' => [
            'expect' => ['seller', 'buyer', 'landlord', 'tenant'],
            'why'    => 'The detail pilot is complete; a partial list serves a visibly mixed platform.',
        ],

        'mls_direct_import.prefill_enabled' => [
            'expect' => true,
            'why'    => 'Import by MLS # is a shipped Seller/Landlord entry point; off removes it with no error.',
        ],

        'mls_direct_import.quick_import_enabled' => [
            'expect' => true,
            'why'    => 'The Seller/Landlord MLS quick-import flow is shipped; off 404s a path we link to.',
        ],

    ],

];
