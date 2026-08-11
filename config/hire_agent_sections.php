<?php

use App\Services\HireAgent\HireAgentDetailAudience;

return [

    /*
    |--------------------------------------------------------------------------
    | Hire Agent detail page — the section registry
    |--------------------------------------------------------------------------
    |
    | THE ORDERED LIST OF SECTIONS THE REDESIGNED DETAIL PAGE CAN RENDER, for all
    | four roles and both audiences. Array order IS document order and IS nav
    | order; there is no separate ordering key, because two ways to say what
    | comes first is one way too many.
    |
    | WHY THIS IS CONFIGURATION. CLAUDE.md's convention is that service order,
    | compensation fields and UI display decisions live in config rather than in
    | views — buyer_services_order.php and its three siblings already work this
    | way. This is the same kind of decision at page scale. It is also the fix
    | for a duplication that was about to get much worse: four role views were
    | each going to grow their own nav builder, and with two audiences that is
    | eight lists to hold in agreement. There is one list.
    |
    | A SEPARATE FILE FROM config/hire_agent_detail.php, AND THAT IS FORCED
    | RATHER THAN STYLISTIC. That file is read by exactly one class, and
    | HireAgentDetailRedesignFlagTest asserts at source that nothing else under
    | app/, resources/views/ or routes/ so much as contains its name. A resolver
    | reading a `sections` key there would have to name it and would break that
    | guard. The two also have genuinely different lifecycles: one is a rollout
    | switch that flips per environment, this is product structure that changes
    | when the page changes.
    |
    | NOTHING READS THIS FILE DIRECTLY. HireAgentDetailSections is the single
    | reader, the same contract HireAgentDetailRedesign has for the flag, and
    | HireAgentSectionRegistryTest asserts it.
    |
    |
    | ── HOW A SECTION IS SCOPED TO A ROLE ────────────────────────────────────
    |
    | BY HAVING A LABEL FOR IT. There is no separate `roles` key, and the absence
    | is deliberate: a role list and a label map can disagree, and the failure
    | mode is a section that is in scope with nothing to call itself — a nav
    | entry with no accessible name, or a card with a blank header. Financing
    | applies to seller and buyer because those are the two labels it has;
    | Pre-Screening applies to tenant for the same reason. One fact, one place.
    |
    |
    | ── AUDIENCE ─────────────────────────────────────────────────────────────
    |
    | 'both'  — every viewer of the page.
    | 'agent' — only a viewer HireAgentDetailAudience resolves to the agent
    |           audience: an agent user type WITH a relationship to this listing.
    |
    | The two agent-only sections are the two that are agent-to-agent business —
    | a referral fee, and the counterparty's licence and contact details. They
    | render for everyone today, so gating them narrows what is published. That
    | narrowing is an authorization decision and it is NOT made here: this file
    | records which sections carry it, and HireAgentDetailAudience decides who
    | satisfies it. A registry that could see the viewer would be a second
    | opinion about a rule that already has an owner.
    |
    |
    | ── WHAT IS ABSENT, AND WHY ABSENCE IS THE MECHANISM ─────────────────────
    |
    | BROKER COMPENSATION AND SERVICES ARE NOT HERE. Not present-and-disabled,
    | not audience => 'none' — absent. A registry entry is what makes a section
    | capable of existing on the redesigned page, so removal means having no
    | entry. A disabled entry would be a switch someone could flip.
    |
    |   · Broker Compensation — compensation belongs to the hire agreement and
    |     the transaction workflow, not to the details of a request.
    |     AcceptedBidSummaryService already carries it there. Its presence on
    |     this page has been an open question since M5.0b, recorded in
    |     docs/investigations/hire-agent-compensation-visibility-decision.md,
    |     where the gate is authentication rather than authorization — every
    |     logged-in user, including a competing agent, can read it. Removing the
    |     surface answers the question rather than continuing to defer it.
    |
    |   · Services — Representation Preferences & Compatibility supersedes it as
    |     the statement of what the client wants from an agent.
    |
    | NEITHER REMOVAL DELETES ANY DATA. Services remain a weighted dimension in
    | config/match_scoring.php, remain on agent proposals, and both remain in the
    | accepted-bid summary. This file governs one page's presentation and nothing
    | else. HireAgentDetailSections rejects either id by name, with a message
    | pointing here, so an attempt to reintroduce one fails loudly rather than
    | reappearing.
    |
    | LEGACY RENDERING IS UNTOUCHED. Both sections still render with the redesign
    | off, exactly as they always have, and
    | HireAgentSectionCardDomEquivalenceTest pins their headings for all four
    | roles. This registry describes the redesigned page only.
    |
    */

    'sections' => [

        /*
         | Listing Details. Became a section when the wrapper card was
         | decomposed — its title had been serving as this block's heading.
         */
        [
            'id'       => 'listing-details',
            'audience' => 'both',
            'icon'     => 'fa-solid fa-file-lines',
            'labels'   => [
                'seller'   => 'Listing Details',
                'buyer'    => 'Listing Details',
                'landlord' => 'Listing Details',
                'tenant'   => 'Listing Details',
            ],
        ],

        /*
         | What the client is looking for, or offering. The label splits on the
         | direction of the request: a seller and a landlord describe a property
         | they HAVE, a buyer and a tenant describe one they WANT.
         |
         | Buyer's "Required Property or Business Assets" and seller's
         | "Business/Property Assets" and "Income & Investment Metrics" fold in
         | here as sub-headings rather than becoming sections of their own —
         | they are divisions within one subject, the same way landlord keeps
         | eleven compensation sub-headings inside one card.
         */
        [
            'id'       => 'property',
            'audience' => 'both',
            'icon'     => 'fa-solid fa-house',
            'labels'   => [
                'seller'   => 'Property Details',
                'buyer'    => 'Property Preferences',
                'landlord' => 'Property Details',
                'tenant'   => 'Property Preferences',
            ],
        ],

        /*
         | The terms of the transaction. ONE section with four labels rather than
         | four sections: they occupy the same position, hold the same kind of
         | content and differ only in what the transaction is called.
         */
        [
            'id'       => 'terms',
            'audience' => 'both',
            'icon'     => 'fa-solid fa-file-contract',
            'labels'   => [
                'seller'   => 'Sale Terms',
                'buyer'    => 'Purchasing Terms',
                'landlord' => 'Leasing Terms',
                'tenant'   => 'Leasing Terms',
            ],
        ],

        /*
         | Financing — seller and buyer only, because only a sale is financed.
         | Landlord and tenant have no label here and are therefore out of scope.
         */
        [
            'id'       => 'financing',
            'audience' => 'both',
            'icon'     => 'fa-solid fa-money-check-dollar',
            'labels'   => [
                'seller' => 'Financing Details',
                'buyer'  => 'Financing Details',
            ],
        ],

        /*
         | Pre-Screening — tenant only. The counterpart to financing on a lease:
         | what the applicant can show a landlord about themselves.
         */
        [
            'id'       => 'pre-screening',
            'audience' => 'both',
            'icon'     => 'fa-solid fa-clipboard-check',
            'labels'   => [
                'tenant' => 'Pre-Screening',
            ],
        ],

        [
            'id'       => 'additional-details',
            'audience' => 'both',
            'icon'     => 'fa-solid fa-circle-info',
            'labels'   => [
                'seller'   => 'Additional Details',
                'buyer'    => 'Additional Details',
                'landlord' => 'Additional Details',
                'tenant'   => 'Additional Details',
            ],
        ],

        /*
         | Representation Preferences & Compatibility. The successor to Services:
         | it states what the client wants from an agent, which is what Services
         | was reaching for one item at a time.
         |
         | The nav label is shorter than the card heading on purpose — a bar has
         | a width budget a card header does not, and landlord already abbreviates
         | it the same way.
         */
        [
            'id'       => 'representation',
            'audience' => 'both',
            'icon'     => 'fa-solid fa-handshake',
            'labels'   => [
                'seller'   => 'Representation Preferences',
                'buyer'    => 'Representation Preferences',
                'landlord' => 'Representation Preferences',
                'tenant'   => 'Representation Preferences',
            ],
        ],

        /*
         | Who posted the request.
         |
         | THESE LABELS ARE THE CONSUMER DEFAULT AND ARE OVERRIDDEN PER LISTING.
         | The heading flips to "Agent's Info" when the listing owner is an agent,
         | which is a fact about one row rather than about a role, so it cannot
         | live in config. The caller passes the resolved heading as a label
         | override; see HireAgentDetailSections::resolve().
         */
        [
            'id'       => 'role-info',
            'audience' => 'both',
            'icon'     => 'fa-solid fa-id-card',
            'labels'   => [
                'seller'   => "Seller's Info",
                'buyer'    => "Buyer's Info",
                'landlord' => "Landlord's Info",
                'tenant'   => "Tenant's Info",
            ],
        ],

        /*
         | ── AGENT-ONLY FROM HERE ─────────────────────────────────────────────
         |
         | Both sections are agent-to-agent business and they belong together:
         | the terms of a referral, and who the agent on the other side of it is.
         | Last in document order because they are an appendix for a subset of
         | readers rather than part of the request itself.
         */

        /*
         | Referral & Cooperation Terms. Renders for every visitor today,
         | including anonymous ones; this is where it stops.
         */
        [
            'id'       => 'referral',
            'audience' => 'agent',
            'icon'     => 'fa-solid fa-share-nodes',
            'labels'   => [
                'seller'   => 'Referral & Cooperation',
                'buyer'    => 'Referral & Cooperation',
                'landlord' => 'Referral & Cooperation',
                'tenant'   => 'Referral & Cooperation',
            ],
        ],

        /*
         | Agent Credentials & Contact Info.
         |
         | THE LISTING OWNER'S CREDENTIALS, WHEN THE OWNER IS AN AGENT — not the
         | viewer's own, and not the hired agent's. The page has always modelled
         | the agent-posted request: the Owner Info heading above already flips
         | to "Agent's Info" for exactly this case. This section is the licence
         | and contact detail behind that name, shown to the agents on the other
         | side of the referral.
         |
         | NEW, NOT MIGRATED. No Hire Agent detail view renders anything like it
         | today; the data lives on the User record (brokerage, license_no,
         | phone) and surfaces only on the public profile page and inside bid
         | forms. Its visibility guard is therefore a real one to be written, not
         | one to be moved.
         */
        [
            'id'       => 'agent-credentials',
            'audience' => 'agent',
            'icon'     => 'fa-solid fa-address-card',
            'labels'   => [
                'seller'   => 'Agent Credentials',
                'buyer'    => 'Agent Credentials',
                'landlord' => 'Agent Credentials',
                'tenant'   => 'Agent Credentials',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | The audience values a SECTION may declare
    |--------------------------------------------------------------------------
    |
    | Kept beside the registry so a typo in an `audience` value is a validation
    | failure rather than a section that quietly applies to nobody.
    |
    | THIS IS NOT THE LIST OF AUDIENCES A VIEWER CAN BE, and the two were briefly
    | conflated while this was written. They are different vocabularies:
    |
    |   · a SECTION declares 'both' or 'agent' — who it is for;
    |   · a VIEWER resolves to 'consumer' or 'agent' — who they are.
    |
    | No section is ever declared 'consumer' (a consumer-facing section is 'both',
    | because agents see it too) and no viewer is ever 'both'. Validating one
    | against the other rejects every real call, which is exactly what happened.
    | The viewer vocabulary belongs to HireAgentDetailAudience, which produces it;
    | only this list is registry policy.
    |
    | 'both' is this file's own word. The agent name is imported from the service
    | so the one value the two vocabularies share cannot drift into a string that
    | matches nothing on one side.
    |
    */

    'section_audiences' => [
        'both',
        HireAgentDetailAudience::AUDIENCE_AGENT,
    ],

];
