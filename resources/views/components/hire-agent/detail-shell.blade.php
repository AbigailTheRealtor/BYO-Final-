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
    @param slot   $beforeGrid  optional full-width content inside the container but BEFORE the row
    @param slot   $afterGrid   optional content that belongs inside the container but AFTER the row
    @param slot   $heroActions optional controls for the hero; forwarded, never inspected

    ON beforeGrid. M5.3. The counterpart to afterGrid, added for the Quick Actions band, which is
    full-width above the two columns in Create Offer and has to be able to be full-width here for
    the same reason: it is page-level, not main-column content, and putting it inside `main` would
    make it column-width and imply it belongs to the listing detail rather than to the page.
    It is OPTIONAL and emits nothing when unused, so the three roles that do not pass it render
    byte-identically to before — the same guarantee heroActions and afterGrid already carry. Like
    both of those, the shell does not inspect what it is given, does not decide whether the viewer
    may see it, and adds no wrapper element of its own.

    ON heroActions. The hero has always declared an `actions` slot and nothing could ever reach
    it, because this shell invoked the hero with no slot at all — the control was dead markup.
    Forwarding it is what lets a role view move an existing control into the hero instead of
    duplicating one. The shell does not decide what an action is, whether the viewer may see it,
    or where it points; it passes the slot through untouched.
--}}
@props(['role', 'auction'])
@php
    /*
     | M7.1 — resolved FIRST, because two things below depend on it and Blade runs top to bottom.
     |
     | NOTE ON WHITESPACE: this block sits flush against the props line above, with a single
     | newline before the comment below. A directive block like this one emits nothing itself, but
     | the newlines AROUND it survive into the rendered page — so a blank line here would add one
     | to every Hire Agent page in both flag states, and "flag off changes nothing" would stop
     | being literally true. Verified by byte-diffing all four roles.
     |
     | (Directive names are described rather than written out in these comments. Blade scans for
     | them inside PHP blocks too, so spelling the closing one here would end this block early —
     | which it did, once, before this note existed.)
     |
     | Two things read it: the framework stylesheet included just below, which emits the sidebar
     | sticky rule only for an enabled role, and the grid classes near the end of this file.
     | Computing it once here is what keeps those two in step; computing it twice would be the
     | second opinion this component's whole design avoids.
     |
     | The sticky rule lives in that stylesheet rather than here because it reads VIHO tokens, and
     | that file is the only product file permitted to. Putting it here was tried, and the token
     | guard rejected it — correctly.
     |
     | The full reasoning for each class change is in the block above the container markup.
     */
    $hlaShellRedesign = \App\Support\HireAgent\HireAgentDetailRedesign::enabledFor($role);
@endphp

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
@php
    /*
     | M7.1 — PAGE LAYOUT, AND THE ONE PLACE ROLE SCOPE IS DECIDED.
     |
     | This component renders for all four roles, so the flag it reads cannot be the master
     | switch: the bare master reader would hand seller, buyer and tenant the new grid on the
     | switch that turns landlord on. enabledFor($role) consults the role allowlist,
     | which defaults to landlord alone. (The config path is deliberately not spelled out in this
     | file — a guard asserts that only the reader class names that key, and prose counts.)
     |
     | The role is asked of the service, never tested here. `$role` is passed to it and nothing
     | else — no equality test against a role name, no match(), no array literal here. An inline
     | check would be a second opinion about rollout scope living in markup, which is exactly
     | what the config key exists to prevent.
     |
     | WHAT CHANGES, AND WHY EACH ONE. Measured against the Offer Listing detail page, which is
     | the approved visual reference and is NOT modified by this milestone:
     |
     |   · py-4 on the container      — Offer Listing uses `container py-4`; this had no vertical
     |                                  padding at all, so the grid began flush against the band
     |                                  above it.
     |   · g-4 on the row             — Offer Listing uses `row g-4`; this had no gutter, so the
     |                                  two columns touched.
     |   · align-items-start          — NOT cosmetic, and the reason it ships with the sticky
     |                                  preparation rather than after it. A flex child stretched
     |                                  to the row's full height cannot stick, because it is
     |                                  never shorter than its container. align-items-start is
     |                                  what lets the sidebar shrink to its content, and Offer
     |                                  Listing pairs the two for the same reason.
     |   · col-lg-9 / col-lg-3        — measured: Offer Listing renders 960px main / 320px
     |                                  sidebar; this rendered 853 / 427. The sidebar was 107px
     |                                  WIDER while holding less, which is what made an
     |                                  under-filled sidebar read as broken rather than airy.
     |
     | CLASSES ARE ADDED, NEVER REPLACED. `listingDescription`, `leftCol` and `rightCol` are
     | carried through both branches untouched: HireAgentShellStructureTest selects on all three
     | by name, three role views carry their own CSS hanging off .leftCol, and .listingDescription
     | is shared with buyer_criteria, seller_property and tenant_criteria — pages this milestone
     | must not reach. Only the Bootstrap width classes differ between branches, and no test
     | asserts on those (verified by repo-wide grep before the change).
     |
     | STICKY IS PREPARED HERE, NOT FINISHED HERE. The sidebar gains a hook and the alignment
     | that makes sticking possible; the rail that benefits from it is M7.4. Measured caveat
     | worth knowing: a landlord sidebar carrying a populated proposal console renders as tall
     | as the main column, and a sticky element taller than the viewport is inert until scrolled
     | past. That is correct behaviour, not a defect — it is why Offer Listing sticks an inner
     | card rather than the column, and why M7.4 revisits this.
     */
    $hlaShellContainer = 'container listingDescription' . ($hlaShellRedesign ? ' py-4' : '');
    $hlaShellRow       = 'row' . ($hlaShellRedesign ? ' g-4 align-items-start' : '');
    $hlaShellMainCol   = $hlaShellRedesign
        ? 'col-sm-12 col-md-8 col-lg-9 leftCol'
        : 'col-sm-12 col-md-8 col-lg-8 leftCol';
    $hlaShellSideCol   = $hlaShellRedesign
        ? 'col-sm-12 col-md-4 col-lg-3 rightCol hla-sidebar-sticky'
        : 'col-sm-12 col-md-4 col-lg-4 rightCol';
@endphp

<div class="{{ $hlaShellContainer }}"
     data-hire-agent-detail-shell
     data-hire-agent-role="{{ $role }}">
    {{-- Bare, like afterGrid below and for the same reason: a wrapper introduced only to hang a
         marker on would change the DOM for the test's benefit rather than the page's. --}}
    {{ $beforeGrid ?? '' }}

    <div class="{{ $hlaShellRow }}">
        <div class="{{ $hlaShellMainCol }}" data-hire-agent-main>
            {{ $main }}
        </div>

        <div class="{{ $hlaShellSideCol }}" data-hire-agent-sidebar>
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
