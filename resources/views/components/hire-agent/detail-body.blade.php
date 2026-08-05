{{--
    Hire Agent Listing Detail Framework — the main column's content wrapper.

    M7.2. It exists because the legacy layout and the redesigned one disagree about ONE thing at the
    top level: whether the main column's sections live inside a single card or are cards themselves.

      · flag OFF — one `x-viho.card` titled "Listing Details:" wraps every section, and each section
        renders an `x-viho.section-header` inside it. This is exactly what the page did before M7.2.
      · flag ON  — no wrapper at all. Each section is its own card, which is what the reference page
        (Offer Listing) renders and what makes a nav link land on a card header.

    WHY A COMPONENT RATHER THAN A CONDITIONAL AROUND THE CARD TAG. The alternative was to put
    `<x-viho.card>` inside one `@if` and `</x-viho.card>` inside another, 1,700 lines apart. Blade
    compiles those to paired PHP and it does work, but a component whose opening and closing tags
    live in different branches of different conditionals cannot be read, and an editor that folds or
    reindents the region between them can silently unbalance the page. The wrapper is a real
    structural decision, so it gets a real component.

    IT OWNS NO CONTENT AND MAKES NO DECISION. It does not know which role renders it, reads no user,
    and — like x-hire-agent.detail-section — receives the flag as a resolved boolean rather than
    consulting config, so there is still exactly one reader of the redesign flag.

    @param bool   $redesign  resolved flag state — never read from config here
    @param string $title     the legacy wrapper card's heading; unused when the redesign is on
--}}
@props([
    'redesign' => false,
    'title',
])
@if ($redesign){{ $slot }}@else<x-viho.card :title="$title" title-tag="h4">{{ $slot }}</x-viho.card>@endif
