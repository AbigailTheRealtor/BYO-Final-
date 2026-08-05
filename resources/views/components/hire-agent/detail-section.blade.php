{{--
    Hire Agent Listing Detail Framework — one content section.

    M7.2. The reference page (Offer Listing) renders ~15 discrete `div.card.section-card`, each
    carrying its own `id`; Hire Agent rendered ONE card spanning eight sub-sections with zero-height
    `<span>` anchors buried inside it. Both pages already agree on card CHROME — `.viho-card`
    resolves to exactly the values `.lol-view-page .section-card` declares (radius 0.75rem, border
    #E2E8F0, shadow 0 1px 6px rgba(0,0,0,.06), 1.75rem separation, and the same 135deg header
    gradient at 700/1.05rem/-0.01em). The gap was never the card. It was granularity: a nav link
    landed on a bare span mid-document instead of on a card header, so arriving somewhere never
    looked like arriving.

    IT RENDERS A CARD OR IT RENDERS WHAT WAS THERE BEFORE. There is no third state and no partial
    adoption. With the flag off this emits the `x-viho.section-header` the section already had,
    followed by the slot, and nothing else — no wrapper, no class, no attribute. That is what keeps
    "flag off changes nothing" true for a component that every converted section now routes through.

    THE RULE STAYS WITH THE CALLER. The `<hr>` that separated sections in the legacy layout is NOT
    emitted here, because it is not the same in every section — some are `<hr>`, some `<hr />`, one
    has none, and one sits inside a conditional the section does not own. Reproducing that variance
    through a prop would mean encoding four spellings of a horizontal rule in a component that has
    no opinion about any of them. Each caller keeps its own rule, wrapped in the same
    `@if (! $redesign)` idiom the anchors already used, and the component stays ignorant of it.

    IT MAKES NO AUTHORIZATION DECISION. It reads no user, resolves no route, and does not know which
    role is rendering. A section that must be hidden from a viewer is wrapped in the caller's own
    guard — `Auth::check()` and `$hasLandlordBrokerCompData` for compensation — and this component
    sits INSIDE those guards, never around them. It cannot hide a section and must never be the
    reason one is visible.

    IT DOES NOT READ THE FLAG EITHER. `$redesign` arrives as a resolved boolean from the caller,
    which got it from the single flag reader in app/Support/HireAgent. A component that consulted
    config would be a second reader, and a second reader is how the M4 hero came to publish data the
    page body was gating.

    (The reader's class name is deliberately not written out here, and neither is the name of the
    test that enumerates its consumers — which contains the class name as a substring, and tripped
    the same guard when this note first tried to explain itself. That test finds the views gating on
    the redesign by searching for the class name, so a component NAMING it in prose while consuming
    nothing would register as a consumer nobody decided to add. Describing it keeps the guard
    honest. The test lives in tests/Feature/HireAgent and its name ends in FlagTest.)

    THE FLAG-OFF GUARANTEE IS DOM-IDENTITY, NOT LITERAL BYTES — A DELIBERATE CHANGE FROM M7.1.
    Moving markup inside a component changes the indentation it is emitted at: the header used to be
    written at the caller's depth and is now written at column 0 of this file. Nothing renders
    differently, because inter-element whitespace has no layout effect, but the raw bytes of the
    landlord page shift.

    M7.1 held literal byte identity and was able to: it changed a class string and moved nothing.
    M7.2 is a component extraction, and extraction necessarily relocates markup. The two ways to
    keep the stronger guarantee were both rejected on the record — inlining unbalanced card markup
    at eight call sites, and an `indent` prop encoding each caller's original column depth into this
    component's API. Both trade component quality for whitespace, which is the wrong trade.

    What is preserved, and asserted by HireAgentSectionCardDomEquivalenceTest: with the flag off the
    normalised DOM is identical for all four roles — same elements, same order, same attributes,
    same text, same ids, same counts. Only whitespace between elements differs.

    ON `legacyHeader`, WHICH IS FALSE IN EXACTLY ONE PLACE. The first section — "Listing Details:" —
    had no header of its own before M7.2, because the wrapper card's heading WAS its heading. With
    the redesign on it becomes a card like any other and needs one; with the redesign off it must
    still emit nothing, or the page would grow a duplicate heading directly beneath the card title
    it duplicates. Every other section passes the default and renders its header in both branches.

    @param bool   $redesign      resolved flag state — never read from config here
    @param string $title         section heading, identical in both branches
    @param string $id            anchor id; the CARD carries it, so a nav link lands on the header
    @param string $icon          optional decorative icon class, card branch only
    @param bool   $legacyHeader  emit the legacy header — false only for the wrapper card's own
                                 section, which already had a heading. See above.
--}}
@props([
    'redesign' => false,
    'title',
    'id'           => null,
    'icon'         => null,
    'legacyHeader' => true,
])
@if ($redesign)<x-viho.card :id="$id" :title="$title" :icon="$icon" title-tag="h4">{{ $slot }}</x-viho.card>@elseif ($legacyHeader)<x-viho.section-header :title="$title" tag="h4" />{{ $slot }}@else{{ $slot }}@endif
