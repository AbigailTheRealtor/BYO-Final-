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
    | ── AUDIENCE, AND THE DISTINCTION THAT DRIVES IT ─────────────────────────
    |
    | PUBLIC WEBSITE VISIBILITY IS NOT PRIVATE BID VISIBILITY. This page is both
    | things at once: a listing anyone can open — the route carries `web` and no
    | auth — and the host of a private proposal workflow where a client accepts,
    | rejects or counters agent bids. A single "logged in or not" gate cannot
    | serve both, and the page's history is the proof: Services renders to
    | anonymous visitors today, and Broker Compensation sits behind a bare
    | Auth::check() that admits any logged-in stranger.
    |
    | So a section declares the LOWEST audience tier that may read it, and every
    | wider tier inherits. The tiers are strictly nested — public ⊂ owner ⊂ agent
    | — so nothing is ever shown to a narrower audience and withheld from a wider
    | one.
    |
    | 'public'      — every viewer, including anonymous. The request itself: what
    |                 is wanted, where, on what terms.
    | 'participant' — the listing OWNER and qualifying AGENTS, and nobody else.
    |                 The material a proposal is evaluated against.
    | 'agent'       — qualifying agents only. Agent-to-agent business.
    |
    | The narrowing is an authorization decision and it is NOT made here: this
    | file records which tier each section carries, and HireAgentDetailAudience
    | decides which tier a viewer is in. A registry that could see the viewer
    | would be a second opinion about a rule that already has an owner.
    |
    |
    | ── WHY SERVICES AND BROKER COMPENSATION ARE 'participant' ───────────────
    |
    | They were briefly removed from this registry altogether, on the reasoning
    | that compensation belongs to the hire agreement and that Representation
    | Preferences & Compatibility supersedes Services. That was right about the
    | PUBLIC page and wrong about the private one, and the correction is the
    | reason the 'participant' tier exists.
    |
    | A client reading proposals on their own request needs both: they are what a
    | bid is measured against, and without them "accept, reject or counter" is a
    | decision made blind. An agent weighing whether to propose needs them for the
    | same reason. A passer-by needs neither, and today gets both.
    |
    | NOTE WHICH DATA THIS IS. These sections render the LISTING's own answers —
    | `$auction->get->services`, `$auction->get->commission_structure` — the
    | client's request and offer. The AGENT'S proposal carries its own services
    | and compensation, rendered on the per-bid cards and in the "Private
    | Compensation & Agreement Terms" modal further down the page, and narrowed
    | server-side by HireAgentProposalAccess. That surface is untouched by this
    | registry and was already correct: owner sees every proposal, an agent sees
    | their own, a competitor sees nothing. Two different bodies of data with
    | similar names, and conflating them is how one of them ends up public.
    |
    | NET EFFECT VERSUS TODAY, both narrowings:
    |   · Services      public → owner and qualifying agents
    |   · Compensation  any authenticated user → owner and qualifying agents
    |
    | The second closes the question left open since M5.0b in
    | docs/investigations/hire-agent-compensation-visibility-decision.md, whose
    | unanswered row was "a competing agent bidding on the same listing can read
    | the compensation terms". Under the participant tier they still can, because
    | they are a participant — but a logged-in stranger no longer can.
    |
    | NO DATA IS DELETED OR MOVED. Services remain a weighted dimension in
    | config/match_scoring.php, remain on agent proposals, and both remain in the
    | accepted-bid summary.
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
            'audience' => 'public',
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
            'audience' => 'public',
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
            'audience' => 'public',
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
            'audience' => 'public',
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
            'audience' => 'public',
            'icon'     => 'fa-solid fa-clipboard-check',
            'labels'   => [
                'tenant' => 'Pre-Screening',
            ],
        ],

        /*
         | ── TIERS ARE INTERLEAVED, AND THAT IS FORCED BY THE LEGACY PAGE ─────
         |
         | An earlier draft grouped the tiers — every public section, then the
         | participant pair, then the agent pair — so that each audience's page
         | was a clean PREFIX of the next one's. That is a nicer property and it
         | had to go.
         |
         | ARRAY ORDER IS DOCUMENT ORDER, and document order is not free: with the
         | redesign OFF these same sections render in the order the four views
         | have always rendered them, and
         | HireAgentSectionCardDomEquivalenceTest pins that order verbatim for
         | every role. Reordering the registry to group the tiers would have meant
         | physically moving blocks in the role views, which changes the legacy
         | order too and breaks that pin. The legacy page is not negotiable, so
         | the registry follows it.
         |
         | Services and Broker Compensation therefore sit where they have always
         | sat — between Financing and Additional Details, and after
         | Representation — and the two agent sections sit at the end. One order
         | serves all four roles: landlord's already-migrated nav is
         | listing-details, property, terms, services, additional-details,
         | representation, compensation, referral, role-info, and seller, buyer
         | and tenant render the same sequence with their own labels.
         |
         | The cost is only that a narrower viewer's page has gaps where a section
         | was withheld. Nothing renders in a gap, so there is nothing to see.
         */

        /*
         | Services. What the client is asking an agent to do — the checklist a
         | proposal is measured against, and a scored matching dimension.
         |
         | NOT superseded by Representation Preferences, which was the reasoning
         | for briefly removing it. Representation states HOW the client wants to
         | be worked with; Services states WHAT they want done. A bid is evaluated
         | against the second.
         */
        [
            'id'       => 'services',
            'audience' => 'participant',
            'icon'     => 'fa-solid fa-list-check',
            'labels'   => [
                'seller'   => 'Services',
                'buyer'    => 'Services',
                'landlord' => 'Services',
                'tenant'   => 'Services',
            ],
        ],

        [
            'id'       => 'additional-details',
            'audience' => 'public',
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
            'audience' => 'public',
            'icon'     => 'fa-solid fa-handshake',
            'labels'   => [
                'seller'   => 'Representation Preferences',
                'buyer'    => 'Representation Preferences',
                'landlord' => 'Representation Preferences',
                'tenant'   => 'Representation Preferences',
            ],
        ],

        /*
         | Broker Compensation & Agency Agreement Terms — the listing's own, not a
         | bid's. What the client is offering, which is what makes a proposal's
         | own compensation terms comparable to anything.
         |
         | Today this sits behind a bare Auth::check(), so any logged-in stranger
         | reads it. The participant tier is the narrowing that gate never had.
         */
        [
            'id'       => 'compensation',
            'audience' => 'participant',
            'icon'     => 'fa-solid fa-dollar-sign',
            'labels'   => [
                'seller'   => 'Broker Compensation',
                'buyer'    => 'Broker Compensation',
                'landlord' => 'Broker Compensation',
                'tenant'   => 'Broker Compensation',
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
            'audience' => 'public',
            'icon'     => 'fa-solid fa-id-card',
            'labels'   => [
                'seller'   => "Seller's Info",
                'buyer'    => "Buyer's Info",
                'landlord' => "Landlord's Info",
                'tenant'   => "Tenant's Info",
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
    |   · a SECTION declares the lowest tier that may read it —
    |     'public', 'participant' or 'agent';
    |   · a VIEWER resolves to the tier they are in —
    |     'public', 'owner' or 'agent'.
    |
    | They overlap in two words and differ in the middle one, which is exactly
    | the shape that invites a mix-up. No section is ever declared 'owner' — a
    | section for the owner is 'participant', because qualifying agents read it
    | too — and no viewer is ever 'participant', because 'participant' names a
    | pair of tiers rather than one. Validating one vocabulary against the other
    | rejects every real call, which is what happened on the first run.
    |
    | The viewer vocabulary belongs to HireAgentDetailAudience, which produces
    | it; only this list is registry policy. 'public' and 'participant' are this
    | file's own words, and the agent name is imported from the service so the
    | one value the two vocabularies genuinely share cannot drift on one side.
    |
    */

    'section_audiences' => [
        'public',
        'participant',
        HireAgentDetailAudience::AUDIENCE_AGENT,
    ],

];
