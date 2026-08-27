{{--
    Hire Agent — the guest "log in to bid" call to action, one structure for all four roles.

    WHY THIS EXISTS. Landlord's redesign branch already rendered this action as an
    `x-viho.button`; seller, buyer and tenant still emitted a hand-written
    `<a><button class="btn w-100"><span class="bid">…` from the legacy page. Same action, four
    spellings, and the two families disagree on height, radius, padding and type — which is
    exactly the sidebar misalignment visual QA reported. Routing all four through one component
    makes the presentation shared by construction rather than by four files staying in step.

    IT DECIDES PRESENTATION ONLY, NEVER ELIGIBILITY. The component is reached only from inside
    each role's existing `@elseif (! $auth_id)` guest branch; it does not test the viewer, the
    listing, or anything else. Every authorization branch around it — landlord's "Only agents can
    place bids", the owner's deliberately empty slot, the already-bid and sold states — stays in
    the role view untouched. Nothing here can widen who may bid, because nothing here asks.

    THE AMOUNT IS THE CALLER'S FACT, NOT THIS FILE'S. Each role reads a different field, and one
    of them was already fixed deliberately: seller's `$hsaCtaPrice` comes from `maximum_budget`
    and is the value behind the approved $550,000 CTA. So the component takes an ALREADY-FORMATTED
    string and never derives, formats or falls back to a field of its own — substituting a
    "nearby" budget column here is precisely the bug that fix corrected.

    AND IT REFUSES TO RENDER A BARE CURRENCY SYMBOL. Buyer and tenant wrote `${{ $…->budget }}`
    unconditionally, so a listing with no budget rendered the dangling "Login to Bid $" visual QA
    caught on buyer. `filled()` is the whole guard: absent, null or empty means the badge is not
    emitted at all, rather than emitted empty.

    @param ?string $amount  formatted money string (e.g. "$550,000"), or null for no badge
--}}
@props(['amount' => null])

<x-viho.button
    :href="route('login')"
    variant="primary"
    :block="true"
    icon="fa-solid fa-right-to-bracket">Log in to bid</x-viho.button>

@if (filled($amount))
    {{-- A companion rather than button content. The VIHO button centres a single label, so
         pushing a right-aligned figure inside it would mean overriding the component's own
         layout at three call sites — the drift this file exists to remove. Sitting beneath the
         button, the figure keeps the same information at the same width in every role. --}}
    <div class="hla-cta-amount">{{ $amount }}</div>
@endif
