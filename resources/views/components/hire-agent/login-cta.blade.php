{{--
    Hire Agent — the guest "log in to bid" call to action, one structure for all four roles.

    WHY THIS EXISTS. Landlord's redesign branch already rendered this action as an
    `x-viho.button`; seller, buyer and tenant still emitted a hand-written
    `<a><button class="btn w-100"><span class="bid">…` from the legacy page. Same action, four
    spellings, and the two families disagree on height, radius, padding and type — which is
    exactly the sidebar misalignment visual QA reported. Routing all four through one component
    makes the presentation shared by construction rather than by four files staying in step.

    IT RENDERS THE BUTTON AND NOTHING ELSE, and that is a decision rather than an omission. The
    component briefly took an optional formatted amount and drew it beneath the button; visual QA
    reviewed the result across all four roles and chose landlord's plain button as the standard.
    So there is no `@props` here at all: an unused optional slot is an invitation to put a figure
    back on one role and not the others, which is the drift this file was created to end.

    IT DECIDES PRESENTATION ONLY, NEVER ELIGIBILITY. The component is reached only from inside
    each role's existing `@elseif (! $auth_id)` guest branch; it does not test the viewer, the
    listing, or anything else. Every authorization branch around it — landlord's "Only agents can
    place bids", the owner's deliberately empty slot, the already-bid and sold states — stays in
    the role view untouched. Nothing here can widen who may bid, because nothing here asks.

    NO AMOUNT DATA WAS TOUCHED. Seller's `$hsaCtaPrice` still resolves from `maximum_budget` and
    still renders in the three other CTA branches that legitimately show a figure, including the
    flag-off guest button; the same is true of every other role's own fields. What changed is that
    the REDESIGNED guest CTA shows no money, on any role.
--}}
<x-viho.button
    :href="route('login')"
    variant="primary"
    :block="true"
    icon="fa-solid fa-right-to-bracket">Log in to bid</x-viho.button>
