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
    | serve both, and the page's history is the proof: Referral & Cooperation
    | Terms rendered to anonymous visitors, and Broker Compensation sat behind a
    | bare Auth::check() that admitted any logged-in stranger.
    |
    | So a section declares the LOWEST audience tier that may read it, and every
    | wider tier inherits. The tiers are strictly nested — public ⊂ owner ⊂ agent
    | — so nothing is ever shown to a narrower audience and withheld from a wider
    | one.
    |
    | 'public'      — every viewer, including anonymous. The request itself: what
    |                 is wanted, where, on what terms.
    | 'participant' — the listing OWNER and qualifying AGENTS, and nobody else.
    |                 CURRENTLY UNUSED; see the note below.
    | 'agent'       — qualifying agents only. Agent-to-agent business.
    |
    | The narrowing is an authorization decision and it is NOT made here: this
    | file records which tier each section carries, and HireAgentDetailAudience
    | decides which tier a viewer is in. A registry that could see the viewer
    | would be a second opinion about a rule that already has an owner.
    |
    |
    | ── EVERY LISTING SECTION IS 'public' OR 'agent' ─────────────────────────
    |
    | THE 'participant' TIER HAS NO MEMBERS, and the machinery is kept anyway —
    | the constant, the VISIBLE_TO row in HireAgentDetailSections, and its entry
    | in `section_audiences` below. It is deliberately retained as framework for
    | a future section that genuinely belongs to owner-and-agents, and removing
    | it would be a refactor of the audience model for no behavioural gain.
    |
    | A consequence worth knowing while it holds: with no participant sections,
    | the 'public' and 'owner' tiers resolve to IDENTICAL section sets for every
    | role. The audience model is three-tier by construction and two-tier in
    | practice. Nothing depends on that being true, and adding one participant
    | section restores the distinction without touching any other code.
    |
    |
    | ── WHY SERVICES AND BROKER COMPENSATION ARE NOT HERE ────────────────────
    |
    | THEY ARE NEGOTIATION TERMS, NOT LISTING DETAIL. A listing section describes
    | the REQUEST: what the client wants, where, on what terms, and how they want
    | to be worked with. Services and compensation are what an agent OFFERS in a
    | proposal and what the client then accepts, rejects or counters. They belong
    | to the bid workflow at every tier of viewer, including the listing's own
    | owner, and no audience widening puts them back on this page.
    |
    | THIS REVERSES AN EARLIER DECISION RECORDED HERE, and the reversal is the
    | reason this note is long. Both sections were once carried at 'participant',
    | on the reasoning that a client reading proposals needs to see what those
    | proposals are measured against. That argument mistook WHERE the material
    | belongs for WHETHER the client may see it: the client does need to weigh
    | services and compensation, and they do so on the proposal cards, against
    | each agent's actual offer — not against a copy of their own request
    | rendered as a listing section. One body of data, one surface.
    |
    | NOTE WHICH DATA THIS GOVERNS. It is the LISTING's own answers —
    | `$auction->get->services`, `$auction->get->commission_structure`. The
    | AGENT'S proposal carries its own services and compensation, rendered on the
    | per-bid cards and in the "Private Compensation & Agreement Terms" modal,
    | and narrowed server-side by HireAgentProposalAccess. That surface is
    | untouched by this registry and is the one place either subject appears:
    | owner sees every proposal, an agent sees their own, a competitor sees
    | nothing. Two bodies of data with similar names, and conflating them is how
    | one of them ended up public.
    |
    | NO DATA IS DELETED OR MOVED. Services remain a weighted dimension in
    | config/match_scoring.php, remain on agent proposals, remain in the
    | *_services_order.php display configs, and both remain in the accepted-bid
    | summary. Only the listing-view SECTIONS are gone.
    |
    | THE LEGACY BRANCH GOES TOO. Unlike every other decision in this file, this
    | one is not scoped to the redesigned page: the four role views no longer
    | render either section with the redesign flag off either, and
    | HireAgentSectionCardDomEquivalenceTest no longer pins their headings.
    | A rule about what a listing IS cannot be conditional on a rollout switch.
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
         | ── ORDER IS DOCUMENT ORDER, AND IT FOLLOWS THE LEGACY PAGE ──────────
         |
         | ARRAY ORDER IS DOCUMENT ORDER, and document order is not free: with the
         | redesign OFF these same sections render in the order the four views
         | have always rendered them, and
         | HireAgentSectionCardDomEquivalenceTest pins that order verbatim for
         | every role. Reordering the registry would mean physically moving blocks
         | in the role views, which changes the legacy order too and breaks that
         | pin. The legacy page is not negotiable, so the registry follows it.
         |
         | The removal of Services and Broker Compensation did not disturb this.
         | They sat between Financing and Additional Details, and after
         | Representation; deleting an entry closes the gap without moving
         | anything around it, and the surviving sequence is what the four views
         | already rendered with those two blocks taken out.
         |
         | One order serves all four roles: listing-details, property, terms,
         | [financing | pre-screening], additional-details, representation,
         | referral, role-info, agent-credentials — each role rendering the
         | subset it has labels for, with its own words.
         |
         | The tiers are interleaved rather than grouped, so a narrower viewer's
         | page has gaps where an agent-only section was withheld. Nothing renders
         | in a gap, so there is nothing to see.
         */

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
