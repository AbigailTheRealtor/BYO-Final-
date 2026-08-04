{{--
    Hire Agent Listing Detail Framework — the shared page shell.

    Milestone 5A.3. This is the wrapper the four detail views had each been carrying their own
    copy of: framework styles, flash messages, hero, listing container, the Bootstrap row and the
    two column wrappers. It owns page STRUCTURE and nothing else.

    WHY IT COULD ONLY BE BUILT NOW. Milestone 4 deferred this deliberately. At that point the four
    pages did not actually share the structure they appeared to: Buyer's sidebar rendered outside
    the grid row entirely, and Tenant's was nested inside the main column. A shell imposes one
    grid, so extracting it then would have silently changed two live layouts under cover of a
    refactor. 5A.2-B and 5A.2-T repaired those first. This component now emits exactly the shape
    all four already render, so adoption is a move, not a redesign.

    WHAT IT DOES NOT DO. It makes no authorization decision, reads no user id, resolves no route,
    and branches on role nowhere below. `$role` is passed through to the hero, which uses it to
    pick role-specific LABELS, and is echoed into a data attribute for tests; `$auction` exists
    solely so the hero can read its own display fields. Neither is consulted here. The sidebar
    body stays entirely with each role view — extracting that is Milestone 5B, and doing it in the
    same change as this would have mixed a mechanical move with a real contract design.

    CLASSES ARE PRESERVED, NOT REPLACED. `listingDescription`, `leftCol` and `rightCol` are kept
    exactly as they were because existing CSS and the structural tests select on them. The new
    `data-hire-agent-*` markers are added alongside so tests can target the shell's own regions
    without depending on Bootstrap column classes that may legitimately change.

    @param string $role     seller|buyer|landlord|tenant — passed to the hero, never branched on
    @param mixed  $auction  the role's auction model, for the hero's display fields only
    @param slot   $main        main-column content (role-specific)
    @param slot   $sidebar     sidebar-column content (role-specific, untouched by this milestone)
    @param slot   $afterGrid   optional content that belongs inside the container but AFTER the row
    @param slot   $heroActions optional controls for the hero; forwarded, never inspected

    ON heroActions. The hero has always declared an `actions` slot and nothing could ever reach
    it, because this shell invoked the hero with no slot at all — the control was dead markup.
    Forwarding it is what lets a role view move an existing control into the hero instead of
    duplicating one. The shell does not decide what an action is, whether the viewer may see it,
    or where it points; it passes the slot through untouched.
--}}
@props(['role', 'auction'])

{{--
    Pushed rather than emitted inline so the stylesheet still lands in <head> via the layout's
    styles stack, exactly where each view was putting it before. Each role view keeps its own
    residual @push for the handful of rules that genuinely differ between roles.
--}}
@push('styles')
    @include('hire_agent.framework.styles')
@endpush

{{--
    M5.1 — the scope root.

    The framework stylesheet is pushed into the document's style stack, so every rule it declared
    without an .hla- prefix applied to the WHOLE page: the site header, the off-canvas mobile
    navigation and the footer included. Measured on a live landlord page, the bare `ul` rules were
    styling 19 list items in the mobile nav alone.

    This wrapper is what those rules are now scoped to. It carries no styling of its own and adds
    no layout: it exists solely so `.hla-detail-page ul` can mean "a list on a Hire Agent detail
    page" rather than "every list in the document".

    It wraps the flash block as well as the containers, because the flash is part of this page and
    was in range of the old unscoped rules too.
--}}
<div class="hla-detail-page">

<x-hire-agent.flash />

<div class="container">
    <x-hire-agent.hero :role="$role" :auction="$auction">
        @isset($heroActions)
            <x-slot name="actions">{{ $heroActions }}</x-slot>
        @endisset
    </x-hire-agent.hero>
</div>

<div class="container listingDescription"
     data-hire-agent-detail-shell
     data-hire-agent-role="{{ $role }}">
    <div class="row">
        <div class="col-sm-12 col-md-8 col-lg-8 leftCol" data-hire-agent-main>
            {{ $main }}
        </div>

        <div class="col-sm-12 col-md-4 col-lg-4 rightCol" data-hire-agent-sidebar>
            {{ $sidebar }}
        </div>
    </div>

    {{--
        Rendered bare, with no wrapper element. Buyer keeps a share block here as a direct child
        of the container following the row — the position its own markup established in 749ace982
        — and introducing a wrapper div to hang a marker on would change that DOM for the sake of
        the test rather than the page. Roles with nothing after the grid emit nothing at all.
    --}}
    {{ $afterGrid ?? '' }}
</div>

</div>{{-- .hla-detail-page --}}
