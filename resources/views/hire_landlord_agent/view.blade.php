@extends('layouts.main')

{{-- Combined Fee Display Helper Functions (display-only, no storage changes) --}}
@php
  $fmtMoney = function($v) {
    if ($v === null || $v === '') return null;
    $raw = preg_replace('/[^0-9.]/', '', (string)$v);
    if ($raw === '' || !is_numeric($raw)) return null;
    return '$' . number_format((float)$raw, 0);
  };

  $fmtPercent = function($v) {
    if ($v === null || $v === '') return null;
    $raw = preg_replace('/[^0-9.]/', '', (string)$v);
    if ($raw === '' || !is_numeric($raw)) return null;
    $num = (float)$raw;
    return (floor($num) == $num ? (string)(int)$num : (string)$num) . '%';
  };

  $rentalPeriodSuffix = 'of Rent Due Each Rental Period';

  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };

  $basisText = function($basis) {
    return $basis ? ('of ' . $basis) : null;
  };

  $canon = function($str) {
    if (!is_string($str)) return $str;
    return str_replace(["\xe2\x80\x99", "\xe2\x80\x98", "\xe2\x80\x9c", "\xe2\x80\x9d"], ["'", "'", '"', '"'], $str);
  };

  // Determine if property is Residential or Commercial (case-insensitive, handles variations)
  $propertyType = strtolower(trim($auction->get->property_type ?? ''));
  $isResidential = str_contains($propertyType, 'residential') || 
                   str_contains($propertyType, 'single-family') || 
                   str_contains($propertyType, 'single family') ||
                   str_contains($propertyType, 'condo') ||
                   str_contains($propertyType, 'townhouse') ||
                   str_contains($propertyType, 'apartment');
  $isCommercial = str_contains($propertyType, 'commercial') || 
                  str_contains($propertyType, 'industrial') ||
                  str_contains($propertyType, 'office') ||
                  str_contains($propertyType, 'retail') ||
                  str_contains($propertyType, 'warehouse');
  // Default to Residential if neither is explicitly set
  if (!$isResidential && !$isCommercial && !empty($propertyType)) {
      $isResidential = true;
  }
@endphp

@push('styles')
{{-- Hire Agent Listing Detail Framework (Milestone 4): the thirty rules that were
     byte-identical across all four detail views now live in one place. --}}

{{--
    Milestone 3 pilot — the shared VIHO foundation.

    Included HERE rather than in the shared detail shell, and that placement is the whole point:
    the shell is rendered by all four roles, so putting it there would have enrolled Seller, Buyer
    and Tenant in the pilot at the same time. Landlord is the only role migrating, so Landlord is
    the only file that loads it. The other three keep rendering exactly as they do today.

    It arrives AFTER the framework stylesheet above, which matters: where the two define the same
    property for an element that carries both class families, VIHO wins. That is the intended
    direction of the migration and the reason this page now looks like Create Offer.
--}}
@include('viho.styles')

{{-- Residual Landlord-only rules. These LOOK shared but are not: they differ
     between roles in colour, !important or comment text, so moving them into the shared
     partial would have changed what this page renders. Left in place deliberately. --}}
<style>
/* SECTION HEADER BAR — shorter + true vertical centering */
    .card-header.section-header {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start;
        padding: 12px 18px !important;
        min-height: 0 !important;
        margin-top: 1.25rem;
    }
/* SECTION TITLE TEXT — remove default heading spacing */
    .section-header .section-title {
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1 !important;
        display: block;
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #0f1a24;
    }
/* Base button style */
    .btn-custom {
        width: 100% !important;
        color: white !important;
        border: none;
        padding: 10px 20px;
        min-width: 120px;
        font-weight: 500;
        border-radius: 4px;
        cursor: pointer;
        text-align: center;
        display: inline-block;
    }
/* Accept (green) - always solid green background */
    .btn-accept {
        background-color: #28a745 !important;
        color: #ffffff !important;
    }
.btn-accept:hover {
        background-color: #218838 !important;
    }
/* Reject (red) - always solid red background */
    .btn-reject {
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }
.btn-reject:hover {
        background-color: #c82333 !important;
    }
/* Counter (blue) - always solid blue background */
    .btn-counter {
        background-color: #0d6efd !important;
        color: #ffffff !important;
    }
.btn-counter:hover {
        background-color: #0b5ed7 !important;
    }
/* Bid action buttons - matched sizing for Edit bid */
    .hla-bid-action-btn {
        min-width: 140px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.875rem;
        border: none !important;
        box-shadow: none;
    }
</style>

{{-- M5.2b — the product half of the section navigation. Flag-gated, so with the redesign off this
     page pushes no additional CSS at all. --}}
@if (\App\Support\HireAgent\HireAgentDetailRedesign::enabled())
<style>
/* THE STICKY OFFSET, SUPPLIED BY THE CONSUMER.
   x-viho.section-nav declares `position: sticky` but deliberately leaves `top` unset, because the
   only correct value is the height of whatever fixed chrome the host page puts above the bar —
   which the primitive cannot know and must not guess. This page is that host, so this page answers.

   Desktop has no fixed header above the reading column, so the bar sticks to the viewport top: 0.
   Below the lg breakpoint the mobile header bar occupies 104px, so the nav must clear it.

   ONE variable does both jobs: the bar sticks below the chrome, and .viho-section-nav-target uses
   the same value for scroll-margin-top so an anchored heading never lands underneath the bar it
   was reached from. Two variables would drift, and the symptom is easy to miss. */
:root {
    --viho-section-nav-offset: 0px;
}
@media (max-width: 991.98px) {
    :root {
        --viho-section-nav-offset: 104px;
    }
}

/* Smooth scrolling is CSS here, not script. The nav emits real hrefs, so the browser performs the
   scroll itself and honours the reader's motion preference — reimplementing it in JS would mean
   reimplementing that too, and getting it wrong for anyone who asked for less motion. */
@media (prefers-reduced-motion: no-preference) {
    html {
        scroll-behavior: smooth;
    }
}

</style>
@endif
@endpush


@section('content')
@php
$auth_id = auth()->user() ? auth()->user()->id : 0;
@endphp

@php
    /*
     | M5.2a — SECTION VISIBILITY GUARDS, HOISTED.
     |
     | These five conditions decide whether their sections render. They used to be computed
     | immediately above each section, hundreds of lines apart and hundreds of lines below
     | the top of the page — which is fine while the only consumer is the section itself,
     | and impossible once anything above needs the same answer.
     |
     | M5.2b adds a section navigation bar at the top of the page. A nav entry for a section
     | that did not render would be a link to nothing, and — for the compensation section —
     | would leak the existence and name of a section the viewer is not shown. Re-deriving
     | these conditions up here would have been the obvious shortcut and the wrong one: the
     | copy and the original drift, and the drift is invisible until someone reports a dead
     | link. So the conditions move; nothing is duplicated.
     |
     | The expressions are reproduced verbatim and in their original order. This commit
     | changes WHERE they are evaluated and nothing else — no condition altered, no
     | authorization changed, no output changed.
     |
     | ON THE COMPENSATION GUARD: only the computation moved. `@if (Auth::check())` still
     | wraps the section exactly where it did. Computing the flag for an anonymous visitor
     | reveals nothing — it emits no markup — and the section remains as unreachable to them
     | as before. Whether authenticated-but-unrelated viewers should see compensation at all
     | is an open product question, recorded in
     | docs/investigations/hire-agent-compensation-visibility-decision.md and deliberately
     | untouched here.
     */

    // Services — moved from just above the Services section.
    $hasServices = !empty(@$auction->get->services) || !empty(@$auction->get->other_services);

    // Additional Details — moved from just above the Additional Details section.
    $additionalDetailsRaw = @$auction->get->additional_details ?? null;
    $additionalDetailsStr = is_string($additionalDetailsRaw) ? trim($additionalDetailsRaw) : null;
@endphp

{{-- Representation Preferences — moved verbatim from above its section. --}}
        @php
            $rawCompatView = $auction->info('compatibility_preferences');
            $compatView    = ($rawCompatView !== null && $rawCompatView !== '')
                ? (json_decode($rawCompatView, true) ?? [])
                : [];
            $llView = $compatView['landlord_specific'] ?? [];

            $repResolve = function(string $val, string $otherVal): string {
                return ($val === 'Other' && !empty($otherVal)) ? $otherVal : $val;
            };
            $repResolveArr = function(array $vals, string $otherVal): array {
                return array_values(array_filter(array_map(function($v) use ($otherVal) {
                    return ($v === 'Other' && !empty($otherVal)) ? $otherVal : $v;
                }, $vals)));
            };
            $repRows = [];
            $repAdd = function(string $label, $raw, string $otherVal = '') use (&$repRows, $repResolve, $repResolveArr) {
                if (empty($raw) || $raw === '' || $raw === [] || $raw === '[]') return;
                $display = is_array($raw) ? implode(', ', $repResolveArr($raw, $otherVal)) : $repResolve((string)$raw, $otherVal);
                if (!empty($display)) { $repRows[] = ['label' => $label, 'value' => $display]; }
            };

            $repAdd('Primary Leasing Goal', $llView['primary_leasing_goal'] ?? '', $llView['primary_leasing_goal_other'] ?? '');
            $repAdd('Preferred Tenant Type', $llView['tenant_type_preference'] ?? '', $llView['tenant_type_preference_other'] ?? '');
            $repAdd('Preferred Lease Duration', $llView['lease_duration_preference'] ?? '', '');
            $repAdd('Level of Involvement in Day-to-Day Management', $llView['property_management_involvement'] ?? '', '');
            $repAdd('Preferred Communication Style', $llView['communication_style'] ?? '', '');
            $repAdd('Preferred Contact Frequency', $llView['preferred_contact_method'] ?? '', '');
            $repAdd('Expected Agent Response Time', $llView['response_time_expectation'] ?? '', '');
            $repAdd('Preferred Agent Working Style', $llView['preferred_agent_working_style'] ?? '', '');
            $repAdd('Negotiation Style', $llView['negotiation_style'] ?? '', '');
            $repAdd('Representation Priorities', $llView['representation_priorities'] ?? [], '');
            $repAdd('Risk Tolerance', $llView['risk_tolerance'] ?? '', '');
            $repAdd('Willingness to Offer Concessions', $llView['concessions_willingness'] ?? '', '');
            $repAdd('Flexibility on Lease Terms', $llView['lease_terms_flexibility'] ?? '', '');
            $repAdd('Additional Notes on Representation Preferences', $llView['additional_representation_notes'] ?? '', '');
        @endphp

@php
    // Broker Compensation — moved from inside the Auth::check() wrapper, which stays put.
    $hasLandlordBrokerCompData = !empty(@$auction->get->purchase_fee_type)
        || !empty(@$auction->get->tenant_broker_commission_structure)
        || !empty(@$auction->get->broker_fee_timing)
        || !empty(@$auction->get->renewal_fee_type)
        || !empty(@$auction->get->protection_period)
        || !empty(@$auction->get->agency_agreement_timeframe)
        || !empty(@$auction->get->early_termination_fee_option)
        || !empty(@$auction->get->interested_in_selling)
        || !empty(@$auction->get->interested_lease_option_agreement)
        || !empty(@$auction->get->interested_in_property_management);
@endphp

{{-- Referral & Cooperation — moved verbatim from above its section. Note it issues a query;
     hoisting moves that query earlier in the request, it does not add one. --}}
        @php
            $referralPct = trim((string)($auction->get->referral_percentage ?? ''));
            if ($referralPct === '') {
                $_firstBid = $auction->bids()->orderBy('id', 'asc')->first();
                if ($_firstBid) {
                    $referralPct = trim((string)($_firstBid->get->referral_fee_percent ?? ''));
                }
                unset($_firstBid);
            }
            $referralPctDisplay = $referralPct !== '' ? (str_ends_with($referralPct, '%') ? $referralPct : $referralPct . '%') : '';
        @endphp

{{-- Owner/Agent Info heading — hoisted by M7.2 so the nav entry and the section share one value
     rather than two expressions that have to be kept in agreement. Moved verbatim from just above
     the section; it was already resolved in PHP because a bound attribute containing `&&` is not
     parseable by Blade's attribute compiler. --}}
        @php
            $_ownerInfoHeading = ($auction->user && $auction->user->user_type === 'agent')
                ? "Agent's Info"
                : "Landlord's Info";
        @endphp

@php
    /*
     | M5.2b — SECTION NAVIGATION.
     |
     | The nav entries are built HERE, from the guards M5.2a hoisted, and each entry repeats its
     | section's condition CHARACTER FOR CHARACTER. That duplication is the point and the reason
     | M5.2a happened first: the alternative is a second, looser expression that means roughly the
     | same thing, and "roughly" is how a nav ends up linking to a section that did not render.
     | Anything changing a section's visibility must change the matching line below, and
     | HireAgentSectionNavTest asserts the two agree for every viewer it can construct.
     |
     | THE COMPENSATION ENTRY CARRIES ITS Auth::check() TOO. Its section sits inside that wrapper,
     | so the entry must sit inside the same check — a nav entry reading "Broker Compensation"
     | leaks both the existence and the name of a section an anonymous visitor is never shown, and
     | it would leak it in the one place on the page guaranteed to be visible. This is the specific
     | mistake the primitive is built to be incapable of making on its own: it cannot see the
     | viewer, so the decision has to be made, and be correct, right here.
     |
     | Property Details and Leasing Terms are unconditional — they render for every viewer — so
     | they are added without a guard rather than behind a condition that is always true.
     |
     | Everything is behind the flag: with HIRE_AGENT_DETAIL_REDESIGN_ENABLED off the array stays
     | empty, no nav renders, no anchors are emitted and no script is pushed.
     */
    $hlaDetailRedesign = \App\Support\HireAgent\HireAgentDetailRedesign::enabled();

    $hlaNavSections = [];

    if ($hlaDetailRedesign) {
        /*
         | M7.2 — Listing Details, which became a section by being decomposed.
         |
         | It was the WRAPPER card's heading, not a section, so there was nothing for the nav to
         | point at. Now that each section is its own card it is one, and an anchor with no entry
         | breaks the invariant HireAgentSectionNavTest enforces in both directions: every entry
         | has a section AND every section has an entry.
         |
         | The alternative was to leave the card without an id, which satisfies the invariant by
         | making the section unreachable instead. Rejected — it would be the only section card a
         | reader cannot navigate to, and "the nav lists the page's sections" is a simpler rule to
         | keep than "the nav lists the page's sections except the first one".
         |
         | Unconditional, because the section is.
         */
        $hlaNavSections[] = ['id' => 'hla-section-listing-details', 'label' => 'Listing Details'];
        $hlaNavSections[] = ['id' => 'hla-section-property-details', 'label' => 'Property Details'];
        $hlaNavSections[] = ['id' => 'hla-section-leasing-terms', 'label' => 'Leasing Terms'];

        if ($hasServices) {
            $hlaNavSections[] = ['id' => 'hla-section-services', 'label' => 'Services'];
        }

        if (!empty($additionalDetailsStr) && $additionalDetailsStr !== 'null') {
            $hlaNavSections[] = ['id' => 'hla-section-additional-details', 'label' => 'Additional Details'];
        }

        if (!empty($repRows)) {
            $hlaNavSections[] = ['id' => 'hla-section-representation', 'label' => 'Representation Preferences'];
        }

        if (Auth::check() && $hasLandlordBrokerCompData) {
            $hlaNavSections[] = ['id' => 'hla-section-compensation', 'label' => 'Broker Compensation'];
        }

        if ($referralPctDisplay !== '') {
            $hlaNavSections[] = ['id' => 'hla-section-referral', 'label' => 'Referral & Cooperation'];
        }

        /*
         | M7.2 — the Owner/Agent Info entry, and why it did not exist before.
         |
         | The section always rendered and was never reachable from the nav. That was survivable
         | while it was a sub-heading inside a long card: a reader scrolling to the end arrived at
         | it anyway. Decomposition makes it the LAST CARD ON THE PAGE, and a card the nav declines
         | to mention reads as something the page is hiding rather than something it forgot.
         |
         | UNCONDITIONAL, because the section is. Unlike every entry above it there is no guard to
         | mirror — the heading and the fields below it render for every viewer, including an
         | anonymous one. Adding a condition here would be inventing one.
         |
         | The label is the heading, not a paraphrase of it. $_ownerInfoHeading is hoisted here
         | from just above the section for exactly the reason M5.2a hoisted the other guards: the
         | nav and the section must agree by construction rather than by two authors remembering
         | to. It is a pure read of $auction->user with no side effect, so computing it earlier
         | changes nothing but the line it sits on.
         */
        $hlaNavSections[] = ['id' => 'hla-section-owner-info', 'label' => $_ownerInfoHeading];
    }
@endphp

@php
    /*
     | M5.3 — QUICK ACTIONS.
     |
     | EVERY TILE IS CLASSIFIED BEFORE IT IS ADDED. The four classes are: public action,
     | authenticated user action, agent-only action, listing-owner-only action. Owner-only and
     | agent-only workflows DO NOT GO IN THIS BAND. The band is page-level and public, so a tile
     | advertises both that a workflow exists and what it is called — which is a disclosure in its
     | own right, independent of whether the underlying route is protected.
     |
     | That rule is why "View Proposals" is not here. Proposals are owner-only: competing agents
     | are walled off from them by HireAgentProposalAccess, and a public tile naming the workflow
     | would tell a rival agent, and a passing guest, that proposals exist on this listing and can
     | be opened. The route being protected would not undo that. It is deferred as its own
     | decision, not folded into a UI milestone.
     |
     | Also deliberately absent: the Bid CTA (agent-only, and a five-branch state machine that
     | belongs with the sidebar in M5.4) and Edit Listing (listing-owner-only, and already owned by
     | the M4 hero — a second copy here would be exactly the two-opinions bug the single flag
     | reader exists to prevent).
     |
     | THE THREE TILES, AND THEIR CLASSES:
     |   1. Send Message  — AUTHENTICATED USER ACTION. Rendered exactly as the sidebar button it
     |      replaces: unconditionally. That is preserved on purpose. The route enforces the class
     |      (Authenticate + EnsureEmailIsVerified + NoAdminAuth), so a guest who clicks it is sent
     |      to login — a dead end, but a PRE-EXISTING one. Changing who sees the control is an
     |      authorization and UX decision wearing a UI change's clothes, and is not this milestone.
     |   2. Share Listing — PUBLIC ACTION. The listing URL is already public; these are the same
     |      share targets the sidebar block carried.
     |   3. Copy Link     — PUBLIC ACTION. Same URL, same reasoning.
     |
     | NOTHING IS DUPLICATED. With the flag on, the sidebar's Send Message button and the sidebar
     | share card are suppressed, so each of these actions exists in exactly one place on the page.
     */
    $hlaListingUrl = route('landlord.agent.auction.view', $auction->id);
@endphp

@php
    /*
     | M5.4 — ONE ANSWER TO "IS THIS VIEWER THE OWNER", AND IT IS NOT THIS FILE'S.
     |
     | The view had TWO local definitions of $isListingOwner, both loose:
     |
     |     $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
     |     $isListingOwner = data_get($auction, 'user_id') == $auth_id;
     |
     | $auth_id is 0 for a guest, `landlord_agent_auctions.user_id` IS nullable, and in PHP
     | `0 == null` is true. So on a listing with a null owner every anonymous visitor satisfied
     | the view's own ownership test. No such row exists today — the column is nullable and the
     | table holds zero nulls — so this was latent rather than live, and the proposals those
     | gates wrap were already withheld server-side. It was still the wrong test.
     |
     | HireAgentProposalAccess::isListingOwner() has always done this correctly: it refuses a
     | null viewer, refuses a null owner, and compares as integers. The fix is to ASK IT rather
     | than to copy it — a second correct implementation is still a second implementation, and
     | the reason this file had two subtly different copies is that copies are what happens.
     |
     | This is applied unconditionally, not behind the redesign flag: it only ever narrows who
     | counts as the owner, and a flag would mean the legacy page kept the weaker test.
     */
    $hlaIsListingOwner = app(\App\Services\HireAgent\HireAgentProposalAccess::class)
        ->isListingOwner(auth()->id(), $auction);

    /*
     | M5.4 — ORPHANED SEPARATORS.
     |
     | Two bare <hr> rules sit at the top of the sidebar, each separating a block that is
     | frequently absent. The first follows the identity/Edit Listing block, which M4 moved into
     | the hero — so with the hero flag on it separates nothing, which is the state this
     | environment already runs in. The second follows the "Agent Selected" winner alert, which
     | renders only for a sold listing, so it separates nothing on every live listing.
     |
     | Browser verification found them as the only remaining children of an otherwise empty
     | guest sidebar: two 1px rules and a button. Each is now tied to the block it belongs to.
     | Flag-gated, like the stranded icon button, because they are visible today.
     */
    $hlaSidebarIdentityShown = ! \App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('landlord');
@endphp

    {{-- Milestone 5A.3: flash, hero, the listing container, the grid row and both column
         wrappers now come from the shared shell. Only role-specific content lives here. --}}
    <x-hire-agent.detail-shell role="landlord" :auction="$auction">
        @if ($hlaDetailRedesign)
        {{-- M5.3. Full-width, above the grid — page-level actions, not main-column content. The
             shell's beforeGrid slot exists for this and emits nothing for the roles not using it. --}}
        <x-slot name="beforeGrid">
            <x-viho.quick-actions label="Quick Actions" icon="fa-solid fa-bolt" ariaLabel="Quick actions">

                {{-- 1. Send Message — authenticated user action; route enforces it. --}}
                <x-viho.action-tile
                    :href="route('auction-chat', ['landlord-agent', $auction->id])"
                    icon="fa-solid fa-paper-plane"
                    label="Send Message"
                    description="Message the listing contact about this listing." />

                {{-- 2. Share Listing — public action. --}}
                <x-viho.action-tile
                    icon="fa-solid fa-share-nodes"
                    label="Share Listing"
                    description="Share this listing with agents or your network.">
                    <x-slot name="action">
                        <ul class="hla-quick-share">
                            <li>
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($hlaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on Facebook">
                                    <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://twitter.com/intent/tweet?url={{ urlencode($hlaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on X">
                                    <i class="fa-brands fa-twitter" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://pinterest.com/pin/create/button/?url={{ urlencode($hlaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on Pinterest">
                                    <i class="fa-brands fa-pinterest" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($hlaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on LinkedIn">
                                    <i class="fa-brands fa-linkedin" aria-hidden="true"></i>
                                </a>
                            </li>
                        </ul>
                    </x-slot>
                </x-viho.action-tile>

                {{-- 3. Copy Link — public action.
                     The sidebar's legacy Copy button carries a `js-copy-link` class that NOTHING
                     in the repository binds a handler to — it is dead in this view and in roughly
                     ten others. This one is wired, so the control does what it says. --}}
                <x-viho.action-tile
                    icon="fa-solid fa-link"
                    label="Copy Link"
                    description="Copy a direct link to this listing.">
                    <x-slot name="action">
                        <x-viho.button
                            variant="outline"
                            icon="fa-solid fa-link"
                            data-hla-copy-link="{{ $hlaListingUrl }}">Copy Link</x-viho.button>
                        <span class="hla-quick-copy-status" data-hla-copy-status role="status" aria-live="polite"></span>
                    </x-slot>
                </x-viho.action-tile>

            </x-viho.quick-actions>
        </x-slot>
        @endif

        <x-slot name="main">
            {{-- M5.2b. Outside the card and above it, so the bar spans the column and sticks to the
                 top of the reading area rather than to the inside of a card. --}}
            @if ($hlaDetailRedesign)
                <x-viho.section-nav :items="$hlaNavSections" ariaLabel="Listing sections" />
            @endif
            {{--
                M3 pilot. Was `div.card.description` wrapping a bare `card-header` + inline-styled
                `h4` + `card-body`. The heading level stays h4: typography is migrating, the
                document outline is not.

                M7.2 — THE NOTE THAT USED TO SIT HERE DESCRIBED A SHAPE THAT NO LONGER HOLDS. It
                read: "This is one card containing eight sub-sections, not eight sibling cards — the
                rendered DOM has exactly two children under leftCol, this and the review card. The
                sub-headings below therefore become x-viho.section-header rather than more cards,
                which is what keeps the section order and nesting identical."

                That was true, and keeping the nesting identical was right for a typography
                milestone. It is what M7.2 changes: the reference page renders discrete cards each
                carrying its own id, and a nav link into a monolith lands on a bare span mid-card
                rather than on a header. Sibling cards ARE the parity fix.

                Both shapes still exist and the flag chooses between them. x-hire-agent.detail-body
                emits the wrapper card when the redesign is off and nothing when it is on; each
                section below is an x-hire-agent.detail-section that renders a card or the original
                header accordingly. Section ORDER is unchanged in both branches.
            --}}
            <x-hire-agent.detail-body :redesign="$hlaDetailRedesign" title="Listing Details:">
            <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-listing-details" title="Listing Details:" icon="fa-solid fa-file-lines" :legacy-header="false">
                    <div class="row" style="flex-wrap: wrap;">
                        @if (@$auction->get->listing_title != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Title
                            <span class="removeBold">{{ @$auction->get->listing_title }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->working_with_agent != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Current Representation Status with Broker:
                            <span class="removeBold">{{ @$auction->get->working_with_agent }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->desired_agent_hire_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Desired Agent Hire Date:
                            <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->desired_agent_hire_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->listing_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Date:
                            <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->listing_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->expiration_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Expiration Date:
                            <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->expiration_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->auction_type != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Type:
                            <span class="removeBold"> {{ @$auction->get->auction_type }}
                            </span>
                        </div>
                        @endif

                        {{-- Milestone 3: the "Bidding Period Length: 14 Days" row was removed
                             here. It is a bidding-period label describing a timer that no
                             longer exists or governs anything. --}}
                        @if (@$auction->get->meeting_Preference != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Meeting Preference:
                            <span class="removeBold"> {{ @$auction->get->meeting_Preference }}
                            </span>
                        </div>
                        @endif

                    </div>
            </x-hire-agent.detail-section>@if (! $hlaDetailRedesign)<hr>@endif
            <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-property-details" title="Property Details:" icon="fa-solid fa-house">

                    <div class="row" style="flex-wrap: wrap;">

                        @php
                            $stripState = function($str) {
                                return trim(preg_replace('/,\s*[A-Z]{2}$/', '', $str));
                            };

                            $propertyCityVal = @$auction->get->property_city;
                            $propertyCountyVal = @$auction->get->property_county;
                            $propertyStateVal = @$auction->get->property_state ?: @$auction->get->state;
                            $propertyZipVal = @$auction->get->property_zip ?: @$auction->get->zip_code;

                            $rawCities = @$auction->get->cities;
                            if (is_string($rawCities)) { $rawCities = json_decode($rawCities, true); }
                            $rawCities = is_array($rawCities) ? $rawCities : [];
                            $cleanCities = array_map(function($city) use ($stripState) {
                                return $stripState($city);
                            }, array_filter($rawCities));

                            $rawCounties = @$auction->get->counties;
                            if (is_string($rawCounties)) { $rawCounties = json_decode($rawCounties, true); }
                            $rawCounties = is_array($rawCounties) ? $rawCounties : [];
                            $cleanCounties = array_map(function($county) use ($stripState) {
                                return $stripState($county);
                            }, array_filter($rawCounties));

                            $stateVal = null;
                            $rawStates = @$auction->get->states;
                            if (is_string($rawStates)) { $rawStates = json_decode($rawStates, true); }
                            if (is_array($rawStates) && !empty($rawStates)) {
                                $stateVal = implode('; ', $rawStates);
                            } elseif (!empty(@$auction->get->state)) {
                                $stateVal = @$auction->get->state;
                            }

                            $rawZips = @$auction->get->zipCodes;
                            if (is_string($rawZips)) { $rawZips = json_decode($rawZips, true); }
                            $rawZips = is_array($rawZips) ? array_filter($rawZips) : [];
                        @endphp

                        @if (\App\Helpers\ListingDisplayHelper::hasValue($propertyCityVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            City:
                            <span class="removeBold">{{ $stripState($propertyCityVal) }}</span>
                        </div>
                        @endif
                        @if (\App\Helpers\ListingDisplayHelper::hasValue($propertyCountyVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            County:
                            <span class="removeBold">{{ $stripState($propertyCountyVal) }}</span>
                        </div>
                        @endif
                        @if (\App\Helpers\ListingDisplayHelper::hasValue($propertyStateVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            State:
                            <span class="removeBold">{{ $propertyStateVal }}</span>
                        </div>
                        @endif
                        @if (\App\Helpers\ListingDisplayHelper::hasValue($propertyZipVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Zip Code:
                            <span class="removeBold">{{ $propertyZipVal }}</span>
                        </div>
                        @endif

                        @if (!empty($cleanCities))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Cities:
                            @foreach ($cleanCities as $city)
                                <span class="removeBold badge bg-secondary">{{ $city }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if (!empty($cleanCounties))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Counties:
                            @foreach ($cleanCounties as $county)
                                <span class="removeBold badge bg-secondary">{{ $county }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if (!empty($stateVal))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable State:
                            <span class="removeBold">{{ $stateVal }}</span>
                        </div>
                        @endif
                        @if (!empty($rawZips))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Zip Code:
                            @foreach ($rawZips as $zip)
                                <span class="removeBold badge bg-secondary">{{ $zip }}</span>
                            @endforeach
                        </div>
                        @endif

                        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->property_type))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Property Type:
                            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::normalizePropertyType(@$auction->get->property_type) }}</span>
                        </div>
                        @endif
                        @php
                            $landlordPropertyStyleItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->property_items);
                        @endphp
                        @if (!empty($landlordPropertyStyleItems))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Property Style:
                            <span class="removeBold">{{ implode(', ', $landlordPropertyStyleItems) }}</span>
                        </div>
                        @endif


                        {{-- <div class="col-md-12 col-12 pt-2 fw-bold">
                              Property Type :<span class="removeBold"> {{ @$auction->get->property_type }}</span><br>
                        @if (gettype(@$auction->get->property_items) == 'array')
                        @foreach (@$auction->get->property_items as $item)
                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                        @endforeach
                        @endif
                    </div>

                    @if (@$auction->get->property_items != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"> Property Style:
                        <span class="removeBold">{{ @$auction->get->property_items }}</span>
                    </div>
                    @endif --}}
                    @php
                        $rawLandlordCondition = @$auction->get->condition_prop_buyer;
                        if (empty($rawLandlordCondition)) {
                            $rawLandlordCondition = @$auction->get->condition_prop;
                        }
                        $landlordConditionItems = \App\Helpers\ListingDisplayHelper::normalizeList(
                            $rawLandlordCondition,
                            @$auction->get->other_property_condition
                        );
                        if (empty($landlordConditionItems) && !empty($rawLandlordCondition)) {
                            $landlordConditionItems = is_array($rawLandlordCondition) ? $rawLandlordCondition : [$rawLandlordCondition];
                        }
                        $landlordConditionLabelMap = [
                            'Older but Well Maintained'           => 'Older but Clean & Well Maintained',
                            'Older but clean & well maintained'   => 'Older but Clean & Well Maintained',
                        ];
                        $landlordConditionItems = array_map(function($item) use ($landlordConditionLabelMap) {
                            return $landlordConditionLabelMap[$item] ?? $item;
                        }, $landlordConditionItems);
                    @endphp
                    @if (!empty($landlordConditionItems))
                    <div class="col-md-12 col-12 pt-2 fw-bold"> Property Condition:
                        <span class="removeBold">{{ implode(', ', $landlordConditionItems) }}</span>
                    </div>
                    @endif

                    {{-- @if (@$auction->get->property_type != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold">
                                    Property Type:
                                    <span class="removeBold">{{ @$auction->get->property_type }}</span>
                </div>
                @endif

                @if (@$auction->get->property_items != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i
                        class="fa-regular fa-check-square"></i>Property Style:
                    <span class="removeBold">{{ @$auction->get->property_items }}</span>
                </div>
                @endif --}}

                {{-- <div class="col-md-12 col-12 pt-2 fw-bold">
                                Property
                                Condition:
                                @if (gettype(@$auction->get->condition_prop_buyer) == 'array')
                                    @foreach (array_filter(@$auction->get->condition_prop_buyer) as $item)
                                        <span class="removeBold"> {{ $item }}</span>
                @if ($item == 'Other')
                <span class="removeBold"> {{ @$auction->get->other_property_condition }}</span>
                @endif
                @endforeach
                @endif

            </div> --}}

            @if (@$auction->get->bedrooms != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Bedrooms:
                <span class="removeBold">{{ @$auction->get->bedrooms !== 'Other' ? @$auction->get->bedrooms : @$auction->get->other_bedrooms }}</span>
            </div>
            @endif
            @php
                $bathroomDisplay = @$auction->get->bathrooms !== 'Other' ? @$auction->get->bathrooms : @$auction->get->other_bathrooms;
            @endphp
            @if (\App\Helpers\ListingDisplayHelper::hasValue($bathroomDisplay))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Bathrooms:
                <span class="removeBold">{{ $bathroomDisplay }}</span>
            </div>
            @endif

            @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Heated SqFt:
                <span class="removeBold">

                    {{ str_replace(',', '', $auction->get->minimum_heated_square ?? '') }}


                </span>
            </div>
            @endif
            @if (@$auction->get->minimum_leaseable != null && @$auction->get->minimum_leaseable != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Net Leasable SqFt:
                <span class="removeBold">

                    {{ str_replace(',', '', $auction->get->minimum_leaseable ?? '') }}
                </span>
            </div>
            @endif
            @if (@$auction->get->total_square_feet != null && @$auction->get->total_square_feet != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Total SqFt:
                <span class="removeBold">

                    {{ str_replace(',', '', $auction->get->total_square_feet ?? '') }}
                </span>
            </div>
            @endif
            @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                SqFt Heated Source:
                <span class="removeBold">
                    {{ str_replace(',', '', $auction->get->sqft_heated_source ?? '') }}
                </span>
            </div>
            @endif
            @if (@$auction->get->total_acreage != null && @$auction->get->total_acreage != 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Total Acreage:
                <span class="removeBold">
                    {{ @$auction->get->total_acreage != '' ? @$auction->get->total_acreage : '' }}</span>
            </div>
            @endif
            @if (!empty($auction->get->appliances) && is_array($auction->get->appliances) && count($auction->get->appliances) > 0)
            @php
            $appliancesToShow = [];
            foreach ($auction->get->appliances as $appliance) {
            if ($appliance !== 'Other') {
            $appliancesToShow[] = $appliance;
            }
            }
            if (!empty($auction->get->other_appliances)) {
            $appliancesToShow[] = $auction->get->other_appliances;
            }
            @endphp

            @if (count($appliancesToShow) > 0)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Appliances Included:

                @foreach ($appliancesToShow as $appliance)
                <span class="removeBold badge bg-secondary">
                    {{ $appliance }}
                </span>
                @endforeach
            </div>
            @endif
            @endif


            @if ($isResidential)
            @php
                $tenantRequireRaw = @$auction->get->tenant_require;
                $tenantRequireVal = is_string($tenantRequireRaw) ? trim(trim($tenantRequireRaw, '"')) : '';
            @endphp
            @if (!empty($tenantRequireVal) && $tenantRequireVal !== 'null')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Furnishings:
                <span class="removeBold">
                    <span class="removeBold badge bg-secondary">{{ $tenantRequireVal }}</span>
                </span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->carport_needed))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Carport:
                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->carport_needed, @$auction->get->other_carport_needed, 'Spaces') }}</span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->garage_needed))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Garage:
                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->garage_needed, @$auction->get->other_garage_needed, 'Spaces') }}</span>
            </div>
            @endif
            @endif

            @if ($isCommercial)
            @php
                $parkingRaw = @$auction->get->garage_parking_spaces_option;
                $parkingOther = @$auction->get->other_parking_space_wrapper;
                $parkingOtherStr = is_string($parkingOther) ? trim(trim((string)$parkingOther), '"') : '';
                $parkingOtherHasValue = $parkingOtherStr !== '' && $parkingOtherStr !== 'null';
                $parkingItems = [];
                if (!empty($parkingRaw)) {
                    if (is_string($parkingRaw)) {
                        $decoded = json_decode($parkingRaw, true);
                        $parkingItems = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$parkingRaw];
                    } elseif (is_array($parkingRaw)) {
                        $parkingItems = $parkingRaw;
                    }
                }
                $parkingResult = [];
                $foundParkingOther = false;
                foreach ($parkingItems as $pItem) {
                    $pVal = trim((string)$pItem);
                    $pVal = trim($pVal, '"');
                    if ($pVal === '' || \App\Helpers\ListingDisplayHelper::isPlaceholder($pVal)) continue;
                    if (strtolower($pVal) === 'other') {
                        $foundParkingOther = true;
                        if ($parkingOtherHasValue) {
                            $parkingResult[] = $parkingOtherStr;
                        }
                        continue;
                    }
                    $parkingResult[] = $pVal;
                }
                if (!$foundParkingOther && $parkingOtherHasValue) {
                    $parkingResult[] = $parkingOtherStr;
                }
            @endphp
            @if (!empty($parkingResult))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Garage/Parking Features:
                @if (count($parkingResult) === 1)
                <span class="removeBold">{{ $parkingResult[0] }}</span>
                @else
                @foreach ($parkingResult as $feature)
                <span class="removeBold badge bg-secondary">{{ $feature }}</span>
                @endforeach
                @endif
            </div>
            @endif
            @endif


            @if ($isResidential)
            @php
            // Normalize pool_type to an array of key => bool
            $poolTypeRaw = optional($auction->get)->pool_type;
            if (is_string($poolTypeRaw)) {
            $poolTypeRaw = json_decode($poolTypeRaw, true);
            }
            if (is_object($poolTypeRaw)) {
            $poolTypeRaw = (array) $poolTypeRaw;
            }
            $poolTypeRaw = is_array($poolTypeRaw) ? $poolTypeRaw : [];

            // Format pool types with proper capitalization
            $poolTypes = collect($poolTypeRaw)
            ->filter(fn($v) => $v === true || $v === 1 || $v === '1' || $v === 'true')
            ->keys()
            ->map(function($type) {
            // Handle specific capitalization cases
            $capitalized = [
            'private' => 'Private',
            'community' => 'Community',
            'indoor' => 'Indoor',
            'outdoor' => 'Outdoor',
            'heated' => 'Heated',
            'saltwater' => 'Saltwater'
            ];

            return $capitalized[strtolower($type)] ?? ucwords($type);
            })
            ->implode(', ');
            @endphp

            @if (optional($auction->get)->pool_needed === 'Yes')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                
                Pool:
                <span class="removeBold">
                    @if (!empty($poolTypes))
                    Yes ({{ $poolTypes }})
                    @else
                    Yes
                    @endif
                </span>
            </div>
            @elseif (optional($auction->get)->pool_needed === 'No')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                
                Pool:
                <span class="removeBold">No</span>
            </div>
            @elseif (optional($auction->get)->pool_needed === 'Optional')
            <div class="col-md-12 col-12 pt-2 fw-bold">
                
                Pool:
                <span class="removeBold">Optional</span>
            </div>
            @endif
            @endif


            @php
                $viewPrefItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->view_preference, @$auction->get->other_preferences);
            @endphp
            @if (!empty($viewPrefItems))
            <div class="col-md-12 col-12 pt-2 fw-bold"> View Preference:
                @foreach ($viewPrefItems as $item)
                <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
            @endif
            @if (@$auction->get->leasing_55_plus)

            <div class="col-md-12 col-12 pt-2 fw-bold">
                Age-Restricted Community:
                <span class="removeBold">
                    {{ @$auction->get->leasing_55_plus != '' ? @$auction->get->leasing_55_plus : '' }}</span>
            </div>

            @endif
            @php
                $amenityItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->non_negotiable_amenities, @$auction->get->other_non_negotiable_amenities);
            @endphp
            @if (!empty($amenityItems))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Amenities and Property Features:
                @foreach ($amenityItems as $item)
                <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
            @endif

            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->pets))
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Pets Allowed:
                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->pets, @$auction->get->number_of_pets) }}</span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->pets))
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->type_of_pets))
            <div class="col-md-12 col-12 pt-2 fw-bold"> Acceptable Pet Types:
                <span class="removeBold">{{ @$auction->get->type_of_pets }}</span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->weight_of_pets))
            <div class="col-md-12 col-12 pt-2 fw-bold"> Maximum Weight Per Pet (lbs):
                <span class="removeBold">{{ @$auction->get->weight_of_pets }} lbs</span>
            </div>
            @endif
            @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->breed_restrictions))
            <div class="col-md-12 col-12 pt-2 fw-bold"> Pet Restrictions:
                <span class="removeBold">{{ @$auction->get->breed_restrictions }}</span>
            </div>
            @endif
            @endif

        </div>
        </x-hire-agent.detail-section>@if (! $hlaDetailRedesign)<hr>@endif
        <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-leasing-terms" title="Leasing Terms:" icon="fa-solid fa-file-contract">
        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->occupant_status))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">  Occupant Type:
                <span class="removeBold">{{ @$auction->get->occupant_status }}</span>
            </div>
        </div>
        @endif
        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->occupant_tenant))
            <div class="row" style="flex-wrap: wrap;">
                <div class="col-12 fw-bold pt-2">  Occupied Until:
                    <span class="removeBold">
                        @php
                            $date = \Carbon\Carbon::parse($auction->get->occupant_tenant);
                            echo $date->format('F j, Y');
                        @endphp
                    </span>
                </div>
            </div>
        @endif

        @php
            $lsType = trim(@$auction->get->leasing_spaces ?? '');
            $hlp = \App\Helpers\ListingDisplayHelper::class;
            // Resolve storage fields based on listing type and leasing space type
            $lsStorageIncluded = null;
            $lsStorageSize = null;
            if ($isCommercial) {
                if ($lsType === 'Single Room') {
                    $lsStorageIncluded = $hlp::hasValue(@$auction->get->included_storage_space_com_single)
                        ? $auction->get->included_storage_space_com_single
                        : ($hlp::hasValue(@$auction->get->included_storage_space_res_single) ? $auction->get->included_storage_space_res_single : @$auction->get->included_storage_space);
                    $lsStorageSize = $hlp::hasValue(@$auction->get->storage_space_com_single)
                        ? $auction->get->storage_space_com_single
                        : ($hlp::hasValue(@$auction->get->storage_space_res_single) ? $auction->get->storage_space_res_single : @$auction->get->storage_space);
                } else {
                    $lsStorageIncluded = $hlp::hasValue(@$auction->get->included_storage_space_com_entire)
                        ? $auction->get->included_storage_space_com_entire
                        : @$auction->get->included_storage_space;
                    $lsStorageSize = $hlp::hasValue(@$auction->get->storage_space_com_entire)
                        ? $auction->get->storage_space_com_entire
                        : @$auction->get->storage_space;
                }
            } else {
                $lsStorageIncluded = $hlp::hasValue(@$auction->get->included_storage_space_res_both)
                    ? $auction->get->included_storage_space_res_both
                    : ($hlp::hasValue(@$auction->get->included_storage_space_res_single)
                        ? $auction->get->included_storage_space_res_single
                        : @$auction->get->included_storage_space);
                $lsStorageSize = $hlp::hasValue(@$auction->get->storage_space_res_both)
                    ? $auction->get->storage_space_res_both
                    : ($hlp::hasValue(@$auction->get->storage_space_res_single)
                        ? $auction->get->storage_space_res_single
                        : @$auction->get->storage_space);
            }
        @endphp

        @if ($hlp::hasValue($lsType))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">  Leasing Space:
                <span class="removeBold">{{ $lsType }}</span>
            </div>
        </div>
        @endif

        @if ($lsType === 'Single Room')
        {{-- Single Room: strict ordered fields for both Residential and Commercial --}}
        {{-- 2. Guests are --}}
        @if ($hlp::hasValue(@$auction->get->guests_allowed))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Guests are:
                <span class="removeBold">{{ $auction->get->guests_allowed }}</span>
            </div>
        </div>
        @endif
        {{-- 3. Restrictions Include --}}
        @if ($hlp::hasValue(@$auction->get->restrictions))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Restrictions Include:
                <span class="removeBold">{{ $auction->get->restrictions }}</span>
            </div>
        </div>
        @endif
        {{-- 4. Shared Areas Available --}}
        @if ($hlp::hasValue(@$auction->get->common_areas_access))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Shared Areas Available:
                <span class="removeBold">{{ $auction->get->common_areas_access }}</span>
            </div>
        </div>
        @endif
        {{-- 5. Maintenance and Repairs Are Handled By --}}
        @if ($hlp::hasValue(@$auction->get->maintenance_by))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance and Repairs Are Handled By:
                <span class="removeBold">{{ $auction->get->maintenance_by }}</span>
            </div>
        </div>
        @endif
        {{-- 6. Maintenance Response Time --}}
        @if ($hlp::hasValue(@$auction->get->maintenance_response_time))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance Response Time:
                <span class="removeBold">{{ $auction->get->maintenance_response_time }}</span>
            </div>
        </div>
        @endif
        {{-- 7. Utilities --}}
        @if ($hlp::hasValue(@$auction->get->utilities))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Utilities:
                <span class="removeBold">{{ $auction->get->utilities }}</span>
            </div>
        </div>
        @endif
        {{-- 8. Common Area Maintenance --}}
        @if ($hlp::hasValue(@$auction->get->common_areas_cleaning))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Common Area Maintenance:
                <span class="removeBold">{{ $auction->get->common_areas_cleaning }}</span>
            </div>
        </div>
        @endif
        {{-- 9. Included Storage Space --}}
        @if ($hlp::hasValue($lsStorageIncluded))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $lsStorageIncluded }}</span>
            </div>
        </div>
        @endif
        {{-- 10. Storage Space Size --}}
        @if ($hlp::hasValue($lsStorageSize))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $lsStorageSize }}</span>
            </div>
        </div>
        @endif
        {{-- 11. Bathroom Facilities --}}
        @if ($hlp::hasValue(@$auction->get->bathroom_facilities))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Bathroom Facilities:
                <span class="removeBold">{{ $auction->get->bathroom_facilities }}</span>
            </div>
        </div>
        @endif
        {{-- 12. Approximate Room Size --}}
        @if ($hlp::hasValue(@$auction->get->room_size))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Approximate Room Size:
                <span class="removeBold">{{ $auction->get->room_size }}</span>
            </div>
        </div>
        @endif

        @elseif ($lsType === 'Entire Property')
        {{-- Entire Property: strict ordered fields for both Residential and Commercial --}}
        {{-- 2. Restrictions Include --}}
        @if ($hlp::hasValue(@$auction->get->restrictions))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Restrictions Include:
                <span class="removeBold">{{ $auction->get->restrictions }}</span>
            </div>
        </div>
        @endif
        {{-- 3. Maintenance and Repairs Are Handled By --}}
        @if ($hlp::hasValue(@$auction->get->maintenance_by))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance and Repairs Are Handled By:
                <span class="removeBold">{{ $auction->get->maintenance_by }}</span>
            </div>
        </div>
        @endif
        {{-- 4. Maintenance Response Time --}}
        @if ($hlp::hasValue(@$auction->get->maintenance_response_time))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Maintenance Response Time:
                <span class="removeBold">{{ $auction->get->maintenance_response_time }}</span>
            </div>
        </div>
        @endif
        {{-- 5. Included Storage Space --}}
        @if ($hlp::hasValue($lsStorageIncluded))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Included Storage Space:
                <span class="removeBold">{{ $lsStorageIncluded }}</span>
            </div>
        </div>
        @endif
        {{-- 6. Storage Space Size --}}
        @if ($hlp::hasValue($lsStorageSize))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Storage Space Size:
                <span class="removeBold">{{ $lsStorageSize }}</span>
            </div>
        </div>
        @endif
        {{-- 7. Shared Amenities Include --}}
        @if ($hlp::hasValue(@$auction->get->shared_amenities))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Shared Amenities Include:
                <span class="removeBold">{{ $auction->get->shared_amenities }}</span>
            </div>
        </div>
        @endif
        {{-- 8. Building Hours --}}
        @if ($hlp::hasValue(@$auction->get->building_hours))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Building Hours:
                <span class="removeBold">{{ $auction->get->building_hours }}</span>
            </div>
        </div>
        @endif
        {{-- 9. 24/7 Access Available --}}
        @if ($hlp::hasValue(@$auction->get->access_24_7))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">24/7 Access Available:
                <span class="removeBold">{{ $auction->get->access_24_7 }}</span>
            </div>
        </div>
        @endif
        {{-- 10. Zoning Allows --}}
        @if ($hlp::hasValue(@$auction->get->zoning_allows))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Zoning Allows:
                <span class="removeBold">{{ $auction->get->zoning_allows }}</span>
            </div>
        </div>
        @endif
        {{-- 11. Space Features --}}
        @if ($hlp::hasValue(@$auction->get->space_features))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Space Features:
                <span class="removeBold">{{ $auction->get->space_features }}</span>
            </div>
        </div>
        @endif
        {{-- 12. Neighboring Tenants Include --}}
        @if ($hlp::hasValue(@$auction->get->neighboring_tenants))
        <div class="row" style="flex-wrap: wrap; margin-left: 1rem;">
            <div class="col-12 fw-bold">Neighboring Tenants Include:
                <span class="removeBold">{{ $auction->get->neighboring_tenants }}</span>
            </div>
        </div>
        @endif
        @endif

        @php
            $tenantPayItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->tenant_pays, @$auction->get->other_tenant_pays);
            $ownerPayItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->owner_pays, @$auction->get->other_owner_pays);

            $rawTermsOfLease = $auction->get->terms_of_lease ?? null;
            $termsOfLease = is_string($rawTermsOfLease)
            ? (json_decode($rawTermsOfLease, true) ?? [])
            : (is_array($rawTermsOfLease) ? $rawTermsOfLease : []);
        @endphp

        @if (!empty($tenantPayItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Tenant Responsible For:
                @foreach ($tenantPayItems as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @if ($isCommercial && !empty($ownerPayItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Owner Responsible For:
                @foreach ($ownerPayItems as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @php
            $leaseTermItems = \App\Helpers\ListingDisplayHelper::normalizeList($termsOfLease, @$auction->get->custom_lease_term);
        @endphp
        @if (!empty($leaseTermItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Terms of Lease:
                @foreach ($leaseTermItems as $lt)
                    <span class="removeBold badge bg-secondary">{{ $lt }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->desired_rental_amount))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Desired Rental Amount:
                <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::fmtMoney(@$auction->get->desired_rental_amount) }}</span>
            </div>
        </div>
        @endif
        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->lease_amount_frequency))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">
                Lease Amount Frequency:
                <span class="removeBold">{{ @$auction->get->lease_amount_frequency }}</span>
            </div>
        </div>
        @endif

        @php
            $desiredLeaseTermItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->desired_lease_length, @$auction->get->other_lease_term);
        @endphp
        @if (!empty($desiredLeaseTermItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">Desired Lease Term:
                @foreach ($desiredLeaseTermItems as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
        </div>
        @endif
        @php
            $rawRentIncludes = @$auction->get->rent_includes;
            $rawRentIncludesStr = is_string($rawRentIncludes) ? trim(str_replace('"', '', $rawRentIncludes)) : '';
            $isRentNone = (is_array($rawRentIncludes) && count($rawRentIncludes) === 1 && strtolower(trim($rawRentIncludes[0])) === 'none')
                || strtolower($rawRentIncludesStr) === 'none'
                || (is_string($rawRentIncludes) && json_decode($rawRentIncludes, true) === ['None']);
            $rentIncludesItems = $isRentNone ? [] : \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->rent_includes, @$auction->get->other_rent_include);
        @endphp
        @if ($isRentNone)
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">Rent Includes:
                <span class="removeBold">None</span>
            </div>
        </div>
        @elseif (!empty($rentIncludesItems))
        <div class="row" style="flex-wrap: wrap;">
            <div class="col-12 fw-bold pt-2">Rent Includes:
                @foreach ($rentIncludesItems as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                @endforeach
            </div>
        </div>
        @endif


        </x-hire-agent.detail-section>@if (! $hlaDetailRedesign)<hr>@endif

        @php
        // Photo enhancements data — needed inside the services loop
        $rawPhotoEnhancements = $auction->get->photo_enhancements ?? null;
        $photoEnhancements = is_string($rawPhotoEnhancements)
            ? (json_decode($rawPhotoEnhancements, true) ?? [])
            : (is_array($rawPhotoEnhancements) ? $rawPhotoEnhancements : []);
        $customEnhancement = $auction->get->custom_enhancement ?? null;
        $enhancementOrder = [
            'Basic edits (brightness, contrast, cropping)',
            'Twilight conversion (convert daytime photo to sunset look)',
            'Object removal (e.g., cars, trash cans, furniture, etc.)',
            'Virtual twilight photography',
            'Color correction or sky replacement',
            'Other',
        ];
        @endphp

        {{-- The card opens INSIDE $hasServices, so it exists exactly when the section does and an
             empty card can never render. Same rule for every conditional section below. --}}
        @if ($hasServices)
        <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-services" title="Services:" icon="fa-solid fa-list-check">

        @php
        // Landlord Residential service categories (exact match with listing creation form)
        $landlordResidentialCategories = [
            "📢 Rental Marketing & Listing Promotion" => [
                "List the property on the local Multiple Listing Service (MLS)",
                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                "Create a branded flyer featuring the property's key highlights",
                "Post the property on Facebook Marketplace",
                "Post the property on Craigslist in the appropriate \"Homes for Rent\" category",
                "Share the listing on Nextdoor in Neighborhood or Community Groups",
                "Promote the listing on Facebook in Housing or Rental Groups",
                "Share the listing on Instagram using posts, stories, or reels",
                "Promote the listing on LinkedIn in Professional or Real Estate Groups",
                "Upload a TikTok video walkthrough of the property",
                "Upload a YouTube video walkthrough of the property",
                "Launch a mass email campaign promoting the listing",
                "Distribute printed flyers or postcards in target geographic areas",
                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
            ],
            "📋 Listing Presentation & Preparation" => [
                "Conduct a property walkthrough and provide recommendations for listing readiness",
                "Provide a custom listing preparation checklist",
                "Collect property details and prepare MLS remarks and a public listing description",
                "Provide a visual consultation for interior layout, cleanliness, and presentation",
                "Provide a curb appeal consultation focused on exterior presentation",
                "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only — no endorsement or warranty is made",
            ],
            "📸 Photography, Video & Virtual Media" => [
                "Provide professional property photography",
                "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                "Provide a video walkthrough tour",
                "Provide a 3D virtual tour",
                "Provide virtual staging (digital enhancements only; no physical staging)",
                "Provide digital photo enhancements",
                "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
            ],
            "🏡 Showings & Access Coordination" => [
                "Ensure proper notice is given if the property is occupied",
                "Install a real estate sign on the property",
                "Install a lockbox for Agent access",
                "Schedule and attend showings with prospective Tenants",
                "Coordinate showings with Tenant's Agents",
                "Collect and relay feedback to the Landlord after showings",
            ],
            "📝 Tenant Application Support" => [
                "Provide a link to an online application platform with third-party screening tools (e.g., credit, background, and eviction checks)",
                "Ensure compliance with Fair Housing laws and screening regulations throughout the application process",
                "Collect and organize application documents submitted by prospective Tenants",
                "Verify basic information provided in the application (e.g., employment, income, and references)",
                "Present complete and organized application packages to the Landlord for review and final selection",
            ],
            "📃 Lease Preparation & Execution" => [
                "Review lease offers submitted by prospective Tenants and summarize key terms",
                "Coordinate lease negotiation with the Tenant or Tenant's Agent",
                "Prepare a state-specific lease agreement using approved forms or templates",
                "Assist with completing required lease disclosures and reviewing key lease terms",
                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                "Confirm receipt of required move-in funds and assist the Landlord in verifying amounts due, payment deadlines, and accepted payment methods",
            ],
            "🚚 Move-In Support & Coordination" => [
                "Coordinate move-in date and key handoff logistics with the Tenant or Tenant's Agent",
                "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
                "Verify receipt of all required move-in funds prior to occupancy (e.g., deposit, rent, pet fees)",
                "Provide a utility setup checklist and local provider resources for the Tenant",
                "Share a move-in checklist for documentation and property condition review",
            ],
            "📑 Property Management" => [
                "Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)",
            ],
            "💡 Leasing Strategy & Guidance" => [
                "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
                "Advise on lease types and structures (e.g., month-to-month, annual, furnished, corporate, lease-option)",
                "Provide general guidance on Landlord obligations and Tenant rights under state law",
                "Provide general guidance on rental demand, local market conditions, and Tenant expectations",
            ],
        ];

        // Landlord Commercial service categories (exact match with listing creation form)
        $landlordCommercialCategories = [
            "📢 Rental Marketing & Listing Promotion" => [
                "List the property on the local Multiple Listing Service (MLS)",
                "List the property on Crexi.com",
                "List the property on LoopNet.com",
                "Create a branded flyer featuring the property's key highlights",
                "Post the property on Craigslist under the \"Office/Commercial\" category",
                "Promote the listing on Facebook in Commercial Leasing or Business Startup Groups",
                "Share the listing on Instagram using photos, stories, or reels",
                "Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                "Upload a TikTok video walkthrough of the property",
                "Upload a YouTube video walkthrough of the property",
                "Launch a mass email campaign promoting the listing",
                "Distribute printed flyers or postcards in target geographic areas",
                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
            ],
            "📋 Listing Presentation & Preparation" => [
                "Conduct a property walkthrough and provide recommendations for listing readiness",
                "Provide a custom listing preparation checklist",
                "Collect property details such as lease terms, square footage, property features, and allowable uses",
                "Prepare a marketing packet including zoning, cap rate references, and permitted uses",
                "Provide a visual consultation focused on interior layout, cleanliness, and presentation",
                "Provide a curb appeal consultation for exterior appearance and signage opportunities",
                "Provide referrals to third-party vendors (e.g., cleaners, sign installers, minor repair vendors). Vendor fees billed separately. Referrals only — no endorsement or warranty is made",
            ],
            "📸 Photography, Video & Virtual Media" => [
                "Provide professional property photography",
                "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                "Provide a video walkthrough tour",
                "Provide a 3D virtual tour",
                "Provide virtual staging (digital enhancements only; no physical staging)",
                "Provide digital photo enhancements",
                "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
            ],
            "🏢 Showings & Access Coordination" => [
                "Ensure proper notice is given if the property is occupied",
                "Install a real estate sign on the property",
                "Install a lockbox for Agent access",
                "Schedule and attend showings with prospective Tenants",
                "Coordinate showings with Tenant's Agents",
                "Collect and relay showing feedback to the Landlord",
            ],
            "📝 Tenant Application Support" => [
                "Provide a link to an online application platform or share instructions with prospective Tenants or Tenant's Agents",
                "Ensure compliance with applicable federal, state, and local commercial leasing and anti-discrimination laws",
                "Collect and organize application documents (e.g., business licenses, financials, entity records, references)",
                "Verify basic information provided in the application (e.g., business operations, income sources, references)",
                "Present complete application packages to the Landlord for review and final selection",
            ],
            "📃 Lease Preparation, LOI & Execution" => [
                "Coordinate lease negotiation with the Tenant or Tenant's Agent",
                "Collect and organize Letters of Intent (LOIs) or draft lease proposals",
                "Draft or assist with execution of the final lease agreement using approved forms or templates",
                "Provide and review required lease disclosures and addenda based on state or municipal requirements",
                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                "Verify receipt of required deposits and track rent commencement and key lease dates to ensure move-in readiness",
            ],
            "🚚 Move-In Support & Coordination" => [
                "Coordinate move-in date and key handoff logistics with the Tenant or Tenant's Agent",
                "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or improvements",
                "Verify receipt of all required move-in funds and documents prior to occupancy (e.g., rent, security deposit, insurance certificates)",
                "Provide a utility setup checklist and local provider resources for the Tenant",
                "Share a move-in checklist for documentation and property condition review",
                "Assist with coordination of move-in logistics, including Certificate of Insurance (COI) and vendor access (as agreed)",
            ],
            "📑 Property Management" => [
                "Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)",
            ],
            "💡 Leasing Strategy & Guidance" => [
                "Provide a Comparable Lease Analysis with pricing recommendations based on similar properties, local vacancy trends, and current market conditions",
                "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
                "Provide general guidance on Landlord obligations and Tenant rights under applicable commercial leasing laws",
                "Provide general guidance on zoning, permitted uses, occupancy standards, or rent escalation terms",
            ],
        ];

        $landlordCategories = $isCommercial ? $landlordCommercialCategories : $landlordResidentialCategories;
        $allServices = is_array(@$auction->get->services) ? $auction->get->services : [];
        $otherServices = is_array(@$auction->get->other_services) ? $auction->get->other_services : [];
        @endphp

        <div class="col-md-12 col-12 pt-2">
            @foreach ($landlordCategories as $categoryName => $categoryServices)
                @php
                    $matchedServices = [];
                    foreach ($categoryServices as $catalogService) {
                        $canonCatalog = trim($canon($catalogService));
                        foreach ($allServices as $savedService) {
                            if (trim($canon($savedService)) === $canonCatalog) {
                                $matchedServices[] = $savedService;
                                break;
                            }
                        }
                    }
                @endphp
                @if (!empty($matchedServices))
                <div class="mt-3">
                    <strong>{{ $categoryName }}</strong>
                    <ul class="services">
                        @foreach ($matchedServices as $service)
                        <li style="font-size: 16px;">{{ $service }}</li>
                        @if (trim($canon($service)) === 'Provide digital photo enhancements' && !empty($photoEnhancements))
                            <ul style="padding-left: 1.5rem; margin: 4px 0;">
                                @foreach ($enhancementOrder as $enh)
                                    @if (in_array($enh, $photoEnhancements))
                                        @if ($enh === 'Other' && !empty($customEnhancement))
                                            <li style="font-size: 14px;">{{ $customEnhancement }}</li>
                                        @elseif ($enh !== 'Other')
                                            <li style="font-size: 14px;">{{ $enh }}</li>
                                        @endif
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                        @endforeach
                    </ul>
                </div>
                @endif
            @endforeach

            @if (!empty($otherServices))
            <div class="mt-3">
                <strong>✍️ Additional Services</strong>
                <ul class="services">
                    @foreach ($otherServices as $other_service)
                    <li style="font-size: 16px;">{{ $other_service }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @php
                $ccsRawLandlord = @$auction->get->client_custom_services;
                $clientCustomServicesLandlord = is_array($ccsRawLandlord)
                    ? $ccsRawLandlord
                    : (is_string($ccsRawLandlord) ? (json_decode($ccsRawLandlord, true) ?? []) : []);
                $clientCustomServicesLandlord = array_values(array_filter($clientCustomServicesLandlord, fn($s) => is_string($s) && trim($s) !== ''));
            @endphp
            @if (!empty($clientCustomServicesLandlord))
            <div class="mt-3">
                <strong>📋 Client Requested Services</strong>
                <ul class="services">
                    @foreach ($clientCustomServicesLandlord as $ccs)
                    <li style="font-size: 16px;">{{ $ccs }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

        </div>
        </x-hire-agent.detail-section>@endif

        @if (! $hlaDetailRedesign)<hr>@endif
        @if (!empty($additionalDetailsStr) && $additionalDetailsStr !== 'null')
        <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-additional-details" title="Additional Details:" icon="fa-solid fa-circle-info">

        <div class="col-md-12 col-12 pt-2 fw-bold">
            Additional Details: <span
                class="removeBold">{{ $additionalDetailsStr }}</span>
        </div>
        </x-hire-agent.detail-section>@endif

        {{-- M6: the listing type is supplied here, not guessed inside the partial. It selects the
             model and the document rules the access service applies; a partial that inferred it
             would be deciding authorization scope from markup. Omitting it fails closed — the
             document control simply does not render. --}}
        @include('partials.listing-photos-tours-documents', ['listingDocumentType' => 'landlord'])

        {{-- C9: Representation Preferences & Compatibility display (public; parity with tenant hire view). --}}

        @if (!empty($repRows))
        @if (! $hlaDetailRedesign)<hr />@endif
        {{-- Literal & in the prop: Blade escapes it back to &amp; on output, so the rendered
             text is unchanged. Passing &amp; here would double-escape it. --}}
        <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-representation" title="Representation Preferences & Compatibility:" icon="fa-solid fa-handshake">
        @foreach ($repRows as $repRow)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            {{ $repRow['label'] }}:
            <span class="removeBold">{{ $repRow['value'] }}</span>
        </div>
        @endforeach
        </x-hire-agent.detail-section>@endif

        @if (Auth::check()) {{-- broker compensation: hidden from anonymous visitors --}}
        @if ($hasLandlordBrokerCompData)
        @if (! $hlaDetailRedesign)<hr />@endif
        {{-- Inside BOTH guards — Auth::check() above and $hasLandlordBrokerCompData — so the CARD
             exists exactly when the section does, and the nav entry's matching pair of conditions
             is what keeps the link from pointing at nothing. M7.2 moved the id from a bare span
             onto the card; both guards are untouched and the card opens inside them, never around
             them. An anonymous visitor reaches neither. --}}
        <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-compensation" title="Broker Compensation & Agency Agreement Terms" icon="fa-solid fa-dollar-sign">

        <div class="broker-compensation-section">

        <!-- Landlord's Broker Compensation Sub-section -->
        @if (@$auction->get->purchase_fee_type != null)
        <h5 class="mt-3 mb-2"><strong>Landlord's Broker Compensation:</strong></h5>
        @endif

        @if (@$auction->get->purchase_fee_type != null)
        @php
            // Build combined Landlord's Broker Lease Fee display
            $landlordLeaseFeeType = $canon(@$auction->get->purchase_fee_type ?? '');
            $landlordLeaseFeeCombined = '—';
            
            if ($landlordLeaseFeeType === 'Flat Fee' && @$auction->get->purchase_fee_flat) {
                $landlordLeaseFeeCombined = $fmtMoney(@$auction->get->purchase_fee_flat);
            } elseif ($landlordLeaseFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->purchase_fee_rental_period) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_rental_period) . " $rentalPeriodSuffix";
            } elseif ($landlordLeaseFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->purchase_fee_percentage_combo) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_percentage_combo) . ' of Gross Lease Value';
            } elseif ($landlordLeaseFeeType === "Percentage of the First Month's Rent" && @$auction->get->purchase_fee_flat_combo) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_flat_combo) . " of First Month's Rent";
            } elseif ($landlordLeaseFeeType === 'Percentage of the Net Aggregate Rent' && @$auction->get->purchase_fee_net_aggregate) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_net_aggregate) . ' of Net Aggregate Rent';
            } elseif ($landlordLeaseFeeType === 'Percentage of the Gross Rent' && @$auction->get->purchase_fee_gross_rent) {
                $landlordLeaseFeeCombined = $fmtPercent(@$auction->get->purchase_fee_gross_rent) . ' of Gross Rent';
            } elseif ($landlordLeaseFeeType === "Percentage of Month's Rent" && @$auction->get->purchase_fee_monthly_percentage) {
                $display = $fmtPercent(@$auction->get->purchase_fee_monthly_percentage) . " of Month's Rent";
                if (@$auction->get->purchase_fee_months) {
                    $display .= ' x ' . @$auction->get->purchase_fee_months . ' Months';
                }
                $landlordLeaseFeeCombined = $display;
            } elseif (strtolower($landlordLeaseFeeType) === 'other') {
                $landlordLeaseFeeCombined = @$auction->get->purchase_fee_other ?? @$auction->get->purchase_fee_other_commercial ?? '—';
            } elseif ($landlordLeaseFeeType) {
                $landlordLeaseFeeCombined = $landlordLeaseFeeType;
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Landlord's Broker Lease Fee:
            <span class="removeBold">{{ $landlordLeaseFeeCombined }}</span>
        </div>
        @endif

        @if ($canon(@$auction->get->purchase_fee_type ?? '') === 'Percentage of the Gross Rent' && !empty(@$auction->get->sales_tax_option_gross) && @$auction->get->sales_tax_option_gross !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ @$auction->get->sales_tax_option_gross === 'including' ? 'Including Sales Tax' : (@$auction->get->sales_tax_option_gross === 'excluding' ? 'Excluding Sales Tax' : $auction->get->sales_tax_option_gross) }}</span>
        </div>
        @endif

        @if ($canon(@$auction->get->purchase_fee_type ?? '') === "Percentage of Month's Rent" && !empty(@$auction->get->sales_tax_option_monthly) && @$auction->get->sales_tax_option_monthly !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ @$auction->get->sales_tax_option_monthly === 'including' ? 'Including Sales Tax' : (@$auction->get->sales_tax_option_monthly === 'excluding' ? 'Excluding Sales Tax' : $auction->get->sales_tax_option_monthly) }}</span>
        </div>
        @endif

        @if ($canon(@$auction->get->purchase_fee_type ?? '') === 'Flat Fee' && !empty(@$auction->get->sales_tax_option_flat) && @$auction->get->sales_tax_option_flat !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ @$auction->get->sales_tax_option_flat === 'including' ? 'Including Sales Tax' : (@$auction->get->sales_tax_option_flat === 'excluding' ? 'Excluding Sales Tax' : $auction->get->sales_tax_option_flat) }}</span>
        </div>
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Tenant's Broker Compensation Sub-section (Residential Only) -->
        @if ($isResidential && @$auction->get->tenant_broker_commission_structure != null)
        <h5 class="mt-3 mb-2"><strong>Tenant's Broker Compensation:</strong></h5>

        @if (@$auction->get->tenant_broker_commission_structure != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Tenant's Broker Commission Structure:
            <span class="removeBold">{{ $auction->get->tenant_broker_commission_structure ?? '' }}</span>
        </div>
        @endif

        @if (@$auction->get->tenant_broker_commission_structure != 'no_compensation' && @$auction->get->tenant_broker_commission_structure != "No Compensation Offered to the Tenant's Broker")
        @php
            // Build combined Tenant's Broker Fee display
            $tenantFeeType = $canon(@$auction->get->tenant_broker_fee_structure ?? '');
            $tenantFeeCombined = '—';
            
            if ($tenantFeeType === 'Flat Fee' && @$auction->get->tenant_broker_flat_fee) {
                $tenantFeeCombined = $fmtMoney(@$auction->get->tenant_broker_flat_fee);
            } elseif ($tenantFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->tenant_broker_percentage) {
                $tenantFeeCombined = $fmtPercent(@$auction->get->tenant_broker_percentage) . " $rentalPeriodSuffix";
            } elseif ($tenantFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->tenant_broker_gross_lease) {
                $tenantFeeCombined = $fmtPercent(@$auction->get->tenant_broker_gross_lease) . ' of Gross Lease Value';
            } elseif ($tenantFeeType === "Percentage of the First Month's Rent" && @$auction->get->tenant_broker_first_month_rent) {
                $tenantFeeCombined = $fmtPercent(@$auction->get->tenant_broker_first_month_rent) . " of First Month's Rent";
            } elseif (strtolower($tenantFeeType) === 'other' && @$auction->get->tenant_broker_other) {
                $tenantFeeCombined = @$auction->get->tenant_broker_other;
            } elseif ($tenantFeeType) {
                $tenantFeeCombined = $tenantFeeType;
            }
        @endphp
        @if ($tenantFeeCombined !== '—')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Tenant's Broker Commission Fee:
            <span class="removeBold">{{ $tenantFeeCombined }}</span>
        </div>
        @endif
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
        @endif

        <!-- Tenant's Broker Compensation Sub-section (Commercial Only) -->
        @if (!$isResidential && @$auction->get->tenant_broker_commission_structure != null)
        <h5 class="mt-3 mb-2"><strong>Tenant's Broker Compensation:</strong></h5>

        <div class="col-md-12 col-12 pt-2 fw-bold">
            Tenant's Broker Commission Structure:
            <span class="removeBold">{{ $auction->get->tenant_broker_commission_structure ?? '' }}</span>
        </div>

        @if (@$auction->get->tenant_broker_commission_structure != "No Compensation Offered to the Tenant's Broker")
        @php
            $commFeeType = $canon(@$auction->get->tenant_broker_fee_structure ?? '');
            $commFeeCombined = null;

            if ($commFeeType === 'Percentage of the Net Aggregate Rent' && @$auction->get->tenant_broker_percentage) {
                $commFeeCombined = $fmtPercent(@$auction->get->tenant_broker_percentage) . ' of Net Aggregate Rent';
            } elseif ($commFeeType === 'Percentage of the Gross Rent' && @$auction->get->tenant_broker_gross_lease) {
                $commFeeCombined = $fmtPercent(@$auction->get->tenant_broker_gross_lease) . ' of Gross Rent';
            } elseif (strtolower($commFeeType) === 'flat fee' && @$auction->get->tenant_broker_flat_fee) {
                $commFeeCombined = $fmtMoney(@$auction->get->tenant_broker_flat_fee);
            } elseif (strtolower($commFeeType) === 'other' && @$auction->get->tenant_broker_other) {
                $commFeeCombined = @$auction->get->tenant_broker_other;
            }
        @endphp
        @if ($commFeeCombined !== null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Tenant's Broker Commission Fee:
            <span class="removeBold">{{ $commFeeCombined }}</span>
        </div>
        @endif
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>
        @endif

        <!-- Payment Timing & Renewal Terms Sub-section -->
        @if (@$auction->get->broker_fee_timing != null || @$auction->get->renewal_fee_type != null || @$auction->get->expansion_commission_percentage != null)
        <h5 class="mt-3 mb-2"><strong>Payment Timing & Renewal Terms:</strong></h5>
        @endif

        @if (@$auction->get->broker_fee_timing != null)
        @php
            $paymentTimingDisplay = @$auction->get->broker_fee_timing;
            
            $paymentTimingMap = [
                'full_execution' => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
            ];
            if (isset($paymentTimingMap[$paymentTimingDisplay])) {
                $paymentTimingDisplay = $paymentTimingMap[$paymentTimingDisplay];
            }
            
            if ($paymentTimingDisplay === 'other' || $paymentTimingDisplay === 'Other') {
                $paymentTimingDisplay = @$auction->get->broker_fee_timing_other ?? '';
            }
            
            $canonTiming = $canon($paymentTimingDisplay);
            if ($canonTiming === 'Paid Within Calendar Days After Executed Lease' && @$auction->get->broker_fee_days_after_lease) {
                $paymentTimingDisplay = 'Paid Within ' . $auction->get->broker_fee_days_after_lease . ' Calendar Days After Executed Lease';
            } elseif ($canonTiming === 'Paid Within Calendar Days of Tenant Rent Payment' && @$auction->get->broker_fee_days_after_rent) {
                $paymentTimingDisplay = 'Paid Within ' . $auction->get->broker_fee_days_after_rent . ' Calendar Days of Tenant Rent Payment';
            } elseif ($canonTiming === 'Deducted from Rent Collected' && @$auction->get->broker_fee_days_from_rent) {
                $paymentTimingDisplay = 'Deducted from Rent Collected (' . $auction->get->broker_fee_days_from_rent . ' Calendar Days to Pay Balance)';
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Payment Timing for Broker Fees:
            <span class="removeBold">{{ $paymentTimingDisplay }}</span>
        </div>
        @endif

        @if (@$auction->get->broker_fee_days_after_due_event != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Days After Due Event:
            <span class="removeBold">{{ $auction->get->broker_fee_days_after_due_event }} days</span>
        </div>
        @endif

        @if (@$auction->get->renewal_fee_type != null)
        @php
            // Build combined Lease Renewal/Extension Fee display
            $renewalFeeType = $canon(@$auction->get->renewal_fee_type ?? '');
            $renewalFeeCombined = '—';
            
            if ($renewalFeeType === 'Flat Fee' && @$auction->get->renewal_fee_flat_free) {
                $renewalFeeCombined = $fmtMoney(@$auction->get->renewal_fee_flat_free);
            } elseif ($renewalFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->renewal_fee_percentage) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_percentage) . " $rentalPeriodSuffix";
            } elseif ($renewalFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->renewal_fee_lease_value) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_lease_value) . ' of Gross Lease Value';
            } elseif ($renewalFeeType === "Percentage of the First Month's Rent" && @$auction->get->renewal_fee_first_month) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_first_month) . " of First Month's Rent";
            } elseif ($renewalFeeType === 'Percentage of the Net Aggregate Rent' && @$auction->get->renewal_fee_percentage) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_percentage) . ' of Net Aggregate Rent';
            } elseif ($renewalFeeType === 'Percentage of the Gross Rent' && @$auction->get->renewal_fee_lease_value) {
                $renewalFeeCombined = $fmtPercent(@$auction->get->renewal_fee_lease_value) . ' of Gross Rent';
            } elseif ($renewalFeeType === "Percentage of Month's Rent" && @$auction->get->renewal_fee_first_month) {
                $display = $fmtPercent(@$auction->get->renewal_fee_first_month) . " of Month's Rent";
                if (@$auction->get->renewal_fee_no_of_months) {
                    $display .= ' x ' . @$auction->get->renewal_fee_no_of_months . ' Months';
                }
                $renewalFeeCombined = $display;
            } elseif (strtolower($renewalFeeType) === 'other' && @$auction->get->renewal_fee_custom) {
                $renewalFeeCombined = @$auction->get->renewal_fee_custom;
            } elseif ($renewalFeeType) {
                $renewalFeeCombined = $renewalFeeType;
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Lease Renewal/Extension Fee:
            <span class="removeBold">{{ $renewalFeeCombined }}</span>
        </div>
        @endif

        @php
            $renewalSalesTax = null;
            $canonRenewalType = $canon($renewalFeeType ?? '');
            if (in_array($canonRenewalType, ['Percentage of the Gross Lease Value', 'Percentage of the Gross Rent'])) {
                $renewalSalesTax = @$auction->get->renewal_fee_sales_tax_lease_value;
            } elseif (in_array($canonRenewalType, ["Percentage of the First Month's Rent", "Percentage of Month's Rent"])) {
                $renewalSalesTax = @$auction->get->renewal_fee_sales_tax_first_month;
            } elseif ($canonRenewalType === 'Flat Fee') {
                $renewalSalesTax = @$auction->get->renewal_fee_sales_tax_flat_fee;
            } else {
                $renewalSalesTax = @$auction->get->renewal_fee_sales_tax_lease_value ?? @$auction->get->renewal_fee_sales_tax_first_month ?? @$auction->get->renewal_fee_sales_tax_flat_fee ?? null;
            }
        @endphp
        @if (!empty($renewalSalesTax) && $renewalSalesTax !== 'null')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Sales Tax:
            <span class="removeBold">{{ $renewalSalesTax === 'including' ? 'Including Sales Tax' : ($renewalSalesTax === 'excluding' ? 'Excluding Sales Tax' : $renewalSalesTax) }}</span>
        </div>
        @endif

        @if (@$auction->get->expansion_commission_percentage != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Expansion Commission for Lease Amendment:
            <span class="removeBold">{{ $fmtPercent($auction->get->expansion_commission_percentage) }} of original commission</span>
        </div>
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Property Management Sub-section -->
        @if (@$auction->get->interested_in_property_management != null)
        <h5 class="mt-3 mb-2"><strong>Property Management:</strong></h5>
        @endif

        @if (@$auction->get->interested_in_property_management != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Interested in Property Management:
            <span class="removeBold">{{ $auction->get->interested_in_property_management === 'yes' ? 'Yes' : 'No' }}</span>
        </div>
        @endif

        @if (@$auction->get->interested_in_property_management === 'yes')
        @php
            // Build combined Property Management Fee display
            $pmFeeType = @$auction->get->interested_in_property_management_fee ?? '';
            $pmFeeCombined = '—';
            
            if ($pmFeeType === 'Flat Fee' && @$auction->get->interested_in_property_management_fee_flate_free) {
                $pmFeeCombined = $fmtMoney(@$auction->get->interested_in_property_management_fee_flate_free);
            } elseif ($pmFeeType === 'Percentage of the Rent Due Each Rental Period' && @$auction->get->interested_in_property_management_fee_rental_periord) {
                $pmFeeCombined = $fmtPercent(@$auction->get->interested_in_property_management_fee_rental_periord) . " $rentalPeriodSuffix";
            } elseif ($pmFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->interested_in_property_management_fee_gross_lease) {
                $pmFeeCombined = $fmtPercent(@$auction->get->interested_in_property_management_fee_gross_lease) . ' of Gross Lease Value';
            } elseif (strtolower($pmFeeType) === 'other' && @$auction->get->interested_in_property_management_fee_other) {
                $pmFeeCombined = @$auction->get->interested_in_property_management_fee_other;
            } elseif ($pmFeeType) {
                $pmFeeCombined = $pmFeeType;
            }
        @endphp
        @if ($pmFeeCombined !== '—')
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Property Management Fee:
            <span class="removeBold">{{ $pmFeeCombined }}</span>
        </div>
        @endif
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Lease-Option Details Sub-section -->
        @if (@$auction->get->interested_lease_option_agreement != null)
        <h5 class="mt-3 mb-2"><strong>Lease-Option Details:</strong></h5>
        @endif

        @if (@$auction->get->interested_lease_option_agreement != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Interested in Offering a Lease-Option Agreement:
            <span class="removeBold">{{ $auction->get->interested_lease_option_agreement ?? '' }}</span>
        </div>
        @endif

        @if (@$auction->get->interested_lease_option_agreement === 'Yes')
            @if (@$auction->get->lease_value != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Compensation for Creating the Lease-Option Agreement:
                <span class="removeBold">
                    @if (@$auction->get->lease_type === 'percent')
                        {{ $fmtPercent($auction->get->lease_value) }} of Total Purchase Price
                    @else
                        {{ $fmtMoney($auction->get->lease_value) }}
                    @endif
                </span>
            </div>
            @endif

            @if (@$auction->get->purchase_value != null)
            <div class="col-md-12 col-12 pt-2 fw-bold">
                Compensation if Purchase Option is Exercised:
                <span class="removeBold">
                    @if (@$auction->get->purchase_type === 'percent')
                        {{ $fmtPercent($auction->get->purchase_value) }} of Total Purchase Price
                    @else
                        {{ $fmtMoney($auction->get->purchase_value) }}
                    @endif
                </span>
            </div>
            @endif
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Purchase Fee Details Sub-section -->
        @if (@$auction->get->interested_in_selling != null)
        <h5 class="mt-3 mb-2"><strong>Purchase Fee Details:</strong></h5>
        @endif

        @if (@$auction->get->interested_in_selling != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Interested in Selling:
            <span class="removeBold">{{ $auction->get->interested_in_selling ?? '' }}</span>
        </div>
        @endif

        @if (@$auction->get->interested_in_selling === 'Yes')
        @php
            // Build combined Landlord's Broker Purchase Fee display
            $purchaseFeeType = @$auction->get->interested_in_selling_type ?? '';
            $purchaseFeeCombined = '—';
            
            if ($purchaseFeeType === 'Flat Fee' && @$auction->get->landlord_broker_flate_fee) {
                $purchaseFeeCombined = $fmtMoney(@$auction->get->landlord_broker_flate_fee);
            } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price' && @$auction->get->landlord_broker_purchase_price) {
                $purchaseFeeCombined = $fmtPercent(@$auction->get->landlord_broker_purchase_price) . ' of Total Purchase Price';
            } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                $purchaseFeeCombined = $joinParts([
                    @$auction->get->landlord_broker_percentage_price ? ($fmtPercent(@$auction->get->landlord_broker_percentage_price) . ' of Total Purchase Price') : null,
                    $fmtMoney(@$auction->get->landlord_broker_dollar_price),
                ]) ?? '—';
            } elseif (strtolower($purchaseFeeType) === 'other' && @$auction->get->landlord_broker_other) {
                $purchaseFeeCombined = @$auction->get->landlord_broker_other;
            } elseif ($purchaseFeeType) {
                $purchaseFeeCombined = $purchaseFeeType;
            }
        @endphp
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Landlord's Broker Purchase Fee:
            <span class="removeBold">{{ $purchaseFeeCombined }}</span>
        </div>
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Legal Terms Sub-section -->
        @if (@$auction->get->protection_period != null || @$auction->get->agency_agreement_timeframe != null || ($isResidential && @$auction->get->early_termination_fee_option != null))
        <h5 class="mt-3 mb-2"><strong>Legal Terms:</strong></h5>
        @endif

        @if (@$auction->get->protection_period != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Protection Period Timeframe:
            <span class="removeBold">{{ $auction->get->protection_period }} days</span>
        </div>
        @endif

        @if ($isResidential && @$auction->get->early_termination_fee_option != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Early Termination Fee:
            <span class="removeBold">{{ \App\Helpers\ListingDisplayHelper::formatYesParenthetical(
                $auction->get->early_termination_fee_option == 'yes' ? 'Yes' : 'No',
                $auction->get->early_termination_fee_option == 'yes' && @$auction->get->early_termination_fee_amount ? $fmtMoney($auction->get->early_termination_fee_amount) : null
            ) }}</span>
        </div>
        @endif

        @if (@$auction->get->agency_agreement_timeframe != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Landlord Agency Agreement Timeframe:
            <span class="removeBold">
                {{ $auction->get->agency_agreement_timeframe === 'Other' ? $auction->get->agency_agreement_custom : $auction->get->agency_agreement_timeframe }}
            </span>
        </div>
        @endif

        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <!-- Brokerage Relationship Sub-section -->
        @if (@$auction->get->brokerage_relationship != null)
        <h5 class="mt-3 mb-2"><strong>Brokerage Relationship:</strong></h5>
        @endif

        @if (@$auction->get->brokerage_relationship != null)
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Acceptable Brokerage Relationship:
            <span class="removeBold">{{ $auction->get->brokerage_relationship ?? '' }}</span>
        </div>
        @endif

        @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->additional_details_broker))
        <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

        <h5 class="mt-3 mb-2"><strong>Additional Terms:</strong></h5>

        <div class="col-md-12 col-12 pt-2 fw-bold">
            Additional Terms:
            <span class="removeBold">{{ $auction->get->additional_details_broker }}</span>
        </div>
        @endif

        </div> <!-- end broker-compensation-section -->
        </x-hire-agent.detail-section>@endif
        @endif {{-- /Auth::check() broker compensation --}}
        @if ($referralPctDisplay !== '')
        @if (! $hlaDetailRedesign)<hr />@endif
        <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-referral" title="Referral & Cooperation Terms" icon="fa-solid fa-share-nodes">
        <div class="col-md-12 col-12 pt-2 fw-bold">
            Referral Fee:
            <span class="removeBold">{{ $referralPctDisplay }}</span>
        </div>
        </x-hire-agent.detail-section>@endif
        @if (! $hlaDetailRedesign)<hr />@endif
        {{-- $_ownerInfoHeading is resolved near the nav block above, not here — M7.2 hoisted it so
             the nav entry and this heading are one value rather than two expressions. --}}
        <x-hire-agent.detail-section :redesign="$hlaDetailRedesign" id="hla-section-owner-info" :title="$_ownerInfoHeading" icon="fa-solid fa-id-card">
        @if (!empty($auction->get->first_name))
        <div class="col-md-12 col-12 pt-2 fw-bold"> First
            Name:
            <span class="removeBold">
                {{ $auction->get->first_name }}
            </span>
        </div>
        @endif

        <div class="row">
            {{-- @if (isset($auction->get->video))
                                <div class="col-md-6 col-6 pt-2 fw-bold">Video:
                                    <span class="removeBold">
                                        <video controls style="width:100%;height:29vh;">
                                            <source src="{{ \App\Support\Storage\ListingMediaUrl::get('auction/videos/' . $auction->get->video) }}"
            type="video/mp4">
            Your browser does not support the video tag.
            </video>
            </span>
        </div>
        @endif --}}

        @if (!empty($auction->get->video))
        <div class="col-md-6 col-6 pt-2 fw-bold">Video:
            <span class="removeBold">
                <video autoplay muted loop playsinline controls style="width:100%; height:29vh;">
                    <source src="{{ \App\Support\Storage\ListingMediaUrl::get('auction/videos/' . $auction->get->video) }}"
                        type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </span>
        </div>
        @endif

        @if (isset($auction->get->photo))
        <div class="col-md-6 col-6 pt-2 fw-bold">Photo:
            <span class="removeBold">
                <img src="{{ \App\Support\Storage\ListingMediaUrl::get('auction/images/' . $auction->get->photo) }}"
                    style="width:100%;height:29vh;" />
            </span>
        </div>
        @endif

        @if (!empty($auction->get->video_link))
        @if (filter_var($auction->get->video_link, FILTER_VALIDATE_URL))
        @php
        $videoLink = $auction->get->video_link;
        @endphp

        @if (strpos($videoLink, 'youtube.com') !== false || strpos($videoLink, 'youtu.be') !== false)
        @php
        // Convert YouTube URL to embed format
        if (strpos($videoLink, 'watch?v=') !== false) {
        $youtubeEmbedUrl = str_replace('watch?v=', 'embed/', $videoLink);
        } elseif (strpos($videoLink, 'youtu.be/') !== false) {
        $videoId = basename(parse_url($videoLink, PHP_URL_PATH));
        $youtubeEmbedUrl = "https://www.youtube.com/embed/{$videoId}";
        } else {
        $youtubeEmbedUrl = $videoLink;
        }

        // Add autoplay + mute parameters (mute avoids browser block)
        $youtubeEmbedUrl .=
        (strpos($youtubeEmbedUrl, '?') === false ? '?' : '&') .
        'autoplay=1&mute=1';
        @endphp

        <div class="col-md-6 col-6 pt-2 fw-bold">Video Link:
            <span class="removeBold">
                <iframe width="100%" height="315" src="{{ $youtubeEmbedUrl }}"
                    frameborder="0"
                    allow="autoplay; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </span>
        </div>
        @elseif (strpos($videoLink, 'vimeo.com') !== false)
        @php
        // Extract Vimeo video ID from any kind of URL (e.g. /channels/staffpicks/1120141041)
        preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/', $videoLink, $matches);
        $vimeoVideoId =
        $matches[1] ?? basename(parse_url($videoLink, PHP_URL_PATH));

        // Vimeo autoplay embed URL
        $vimeoEmbedUrl = "https://player.vimeo.com/video/{$vimeoVideoId}?autoplay=1&muted=1";
        @endphp

        <div class="col-md-6 col-6 pt-2 fw-bold">Video Link:
            <span class="removeBold">
                <iframe src="{{ $vimeoEmbedUrl }}" width="100%" height="315"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
                    allowfullscreen>
                </iframe>
            </span>
        </div>
        @endif
        @endif
        @endif

    </div>
{{-- M3 pilot: the former card-body close is gone with its opening tag; the card itself closes here.
     M7.2: "here" is now the last SECTION's close, followed by the body wrapper's. With the redesign
     off the wrapper still emits the single card these two used to be; with it on the wrapper emits
     nothing and the sections above are siblings. --}}
</x-hire-agent.detail-section>
</x-hire-agent.detail-body>
@inject('auctionUser', 'App\Models\User')
@php
$auser = $auctionUser::find(@$auction->user_id);
@endphp
<!-- Review  -->
<div class="card review">
    <div class="card-body d-flex align-items-center">
        <div class="left d-flex align-items-center">
            <x-avatar-img :avatar="$auser->avatar" alt="" class="w-25" />
            <div>
                <p class="mb-0"><a href="{{ route('author', [$auser->id]) }}"><b>User
                            Details</b></a><span></span>
                    <span class="start opacity-50">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </span>
                </p>
                <p class="mb-0">...</p>
                <p class="mb-0 opacity-50">{{ $auser->name }} • last online 5 days ago.</p>
            </div>
        </div>
        <div class="right text-center">
            <a href="{{ route('author', [$auser->id]) }}"><button class="btn">Message</button></a>
            <a href="{{ route('author', [$auser->id]) }}"><button class="btn view-btn">View
                    Profile</button></a>
        </div>

    </div>
</div>
        </x-slot>

        {{-- Sidebar body untouched by 5A.3; the shell supplies only the column wrapper.
             Extracting it is Milestone 5B. --}}
        <x-slot name="sidebar">
    @php
        // M4 — hoisted out of the identity block below so this assignment happens in BOTH
        // treatments. Later sidebar code reads $auth_id, and leaving it inside a block that the
        // redesigned hero suppresses would have silently changed those reads for the pilot role.
        // The directive emits no output, so hoisting it cannot alter the legacy rendering.
        $auth_id = auth()->id();
    @endphp

    {{--
        M4 — the sidebar identity block.

        Title, listing id, status and Edit Listing move INTO the hero when the redesign is on for
        this role, so this block renders only when it is off. What is avoided is duplication:
        without this guard the page would carry two <h1> elements, two status pills and two Edit
        controls, which is worse than either treatment alone.

        The expiry override that used to live in this block is gone rather than moved. It
        re-derived a result LandlordAgentAuction::getStatusAttribute() had already produced — the
        accessor returns 'Expired' from expiration_date itself — so the hero reading $auction->status
        directly yields the identical label for every state. Nothing about expiry was reimplemented
        in the presenter; there was nothing left to reimplement.
    --}}
    @unless (\App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('landlord'))
    <h1 style="font-size: 1.5rem; font-weight: bold; color: #049399; line-height: 1.3;">{{ @$auction->title }}</h1>
    @if(@$auction->listing_id)
    <div class="mb-2">
        <span class="badge bg-secondary" style="font-size: 0.9rem;">Listing ID: {{ @$auction->listing_id }}</span>
    </div>
    @endif
    @if(@$auction->status)
    <div class="mb-2">
        @php
            $_statusStyles = [
                'Active'       => 'background-color:#16a34a;color:#fff;',
                'Pending'      => 'background-color:#d97706;color:#fff;',
                'Hired Agent'  => 'background-color:#2563eb;color:#fff;',
                'Expired'      => 'background-color:#6b7280;color:#fff;',
            ];
            $_statusIcons = [
                'Active'       => 'fa-circle-check',
                'Pending'      => 'fa-clock',
                'Hired Agent'  => 'fa-user',
                'Expired'      => 'fa-circle-xmark',
            ];
            $_statusStyle        = $_statusStyles[$auction->status] ?? 'background-color:#6b7280;color:#fff;';
            $_statusIcon         = $_statusIcons[$auction->status] ?? 'fa-circle';
            $_displayStatusLabel = $auction->status; // separate label var — never touches the model

            // ── Display-layer expiry override (badge only, no DB change) ──────────
            if (!in_array($auction->status, ['Hired Agent', 'Pending', 'Draft'], true)) {
                // Milestone 3: this override used to synthesise an expiry from created_at +
                // auction_time for Bidding Period listings, so the badge could read "Expired"
                // purely because a countdown had elapsed. That branch is retired;
                // expiration_date is the only input, for every listing type. Still
                // display-only — the model is never mutated.
                $_badgeNow  = \Carbon\Carbon::now();
                $_badgeExp  = !empty($auction->get->expiration_date)
                    ? \Carbon\Carbon::parse($auction->get->expiration_date)
                    : null;
                if ($_badgeExp && $_badgeNow->gte($_badgeExp)) {
                    $_statusStyle        = $_statusStyles['Expired'];
                    $_statusIcon         = $_statusIcons['Expired'];
                    $_displayStatusLabel = 'Expired'; // display only — model not mutated
                }
            }
            $_statusPillClass = match($_displayStatusLabel) {
                'Active'      => 'status-active',
                'Pending'     => 'status-pending',
                'Expired'     => 'status-expired',
                'Hired Agent' => 'status-hired',
                default       => 'status-expired',
            };
        @endphp
        <span class="status-pill {{ $_statusPillClass }}"><i class="fa-solid {{ $_statusIcon }} me-1"></i>Status: {{ $_displayStatusLabel }}</span>
    </div>
    @endif

    @if($auth_id && $auth_id == @$auction->user_id)
    <div class="mb-2">
        <a href="{{ route('landlord.hire.agent.auction.edit', ['auctionId' => $auction->id]) }}" 
           class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
        </a>
        {{-- PDF download button hidden from UI (backend route preserved) --}}
    </div>
    @endif
    @endunless
    {{-- M5.4: only separates the identity block when that block actually rendered. --}}
    @if (! $hlaDetailRedesign || $hlaSidebarIdentityShown)
    <hr>
    @endif

    {{-- 🏆 Display Winner Information if Listing is Sold --}}
    @php
        $acceptedBid = $auction->bids->where('accepted', 'accepted')->first();
        // Check for accepted counter bids
        $acceptedCounterBid = null;
        foreach ($auction->bids as $bid) {
            $counterBid = \App\Models\LandlordCounterTerm::where('landlord_agent_auction_id', $bid->id)
                            ->where('status', 'accepted')
                            ->first();
            if ($counterBid) {
                $acceptedCounterBid = $counterBid;
                break;
            }
        }
    @endphp

    @if ($auction->is_sold && ($acceptedBid || $acceptedCounterBid))
    <div class="alert alert-success mb-3" style="border-left: 4px solid #28a745;">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-check me-3" style="font-size: 28px; color: #28a745;"></i>
            <div class="flex-grow-1">
                <h5 class="mb-1 fw-bold">🎉 Agent Selected!</h5>
                @if($acceptedCounterBid)
                    <p class="mb-1">
                        <strong>Accepted Counter Offer from:</strong>
                        {{ $acceptedCounterBid->user->first_name ?? '' }} {{ $acceptedCounterBid->user->last_name ?? '' }}
                    </p>
                    <small class="text-muted">
                        <i class="fa-solid fa-calendar-check"></i>
                        Accepted on {{ \Carbon\Carbon::parse($acceptedCounterBid->accepted_date)->format('M j, Y g:i A') }}
                    </small>
                @elseif($acceptedBid)
                    <p class="mb-1">
                        <strong>Purchased by:</strong>
                        {{ $acceptedBid->user->first_name ?? '' }} {{ $acceptedBid->user->last_name ?? '' }}
                    </p>
                    <small class="text-muted">
                        <i class="fa-solid fa-calendar-check"></i>
                        Accepted on {{ \Carbon\Carbon::parse($acceptedBid->accepted_date)->format('M j, Y g:i A') }}
                    </small>
                @endif
            </div>
        </div>
    </div>
    @endif
    {{-- M5.4: only separates the winner alert when the listing is actually sold. --}}
    @if (! $hlaDetailRedesign || ($auction->is_sold && ($acceptedBid || $acceptedCounterBid)))
    <hr>
    @endif
    @inject('carbon', 'Carbon\Carbon')

    @php
        // Milestone 3 — legacy countdown retirement.
        //
        // This block used to compute TWO different expiries depending on auction_type. For a
        // "Bidding Period" / "Auction (Timer)" listing it synthesised one from created_at +
        // auction_time ("14 Days") and drove a live countdown from it; only a "Traditional"
        // listing used expiration_date. The synthesised value then flowed into $isExpired, which
        // gates the Bid button below — so an elapsed countdown, not the listing's own status,
        // decided whether an agent could propose.
        //
        // The Hire Agent bidding timer is retired. expiration_date is now the SOLE expiry source
        // for every listing, which is what it always was for Traditional listings. Note the
        // direction: the timer is GONE, not re-pointed at expiration_date. Nothing derives a
        // countdown from expiration_date, nothing derives expiration_date from elapsed time, and
        // the two concepts are not synchronised. expiration_date answers one question only —
        // is this listing still live — exactly as it does for a listing that never had a timer.
        //
        // Removed with the timer: $isBiddingPeriodListing, $isTraditionalListing, $start_time,
        // $auction_time, $useAuctionTime, the duration switch, $isBiddingTimerActive,
        // $canTakeAction (always true — a dead soft-deadline escape hatch) and $diff_d/H/I/S.
        // $isSold and $isPending are listing STATUS and are retained unchanged.
        $expiration = !empty($auction->get->expiration_date)
            ? $carbon::parse($auction->get->expiration_date)
            : null;

        $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;
        $isSold    = $auction->is_sold;
        $isPending = ($auction->status === 'Pending');
    @endphp


    {{-- 💰 Bid Info --}}
    @php
        // Milestone 2 — competing-agent proposal privacy.
        // $lowest_bid_price / $lowest_bidder were removed. They existed only to render
        // "Agent N was the last bidder", which disclosed a competing agent and was mislabelled
        // besides: it resolved the MINIMUM brokerage bid while calling that agent the LAST
        // bidder. Not restored in any form.
        // $auction->bids is already narrowed to this viewer's authorized proposals by
        // HireAgentProposalAccess in LandlordAgentAuctionController::view().
        $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp


        {{-- 📩 Message Button.
             M5.3: suppressed when the redesign is on, because the Quick Actions band above the
             grid now carries this action. Suppressed rather than moved — the band's tile is a
             new control with the same route and the same (unconditional) rendering, and leaving
             this one in place would put the same action on the page twice. --}}
        @unless ($hlaDetailRedesign)
        <a href="{{ route('auction-chat', ['landlord-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message
        </a>
        @endunless


        {{--
            Milestone 3: the Days / Hrs / Mins / Secs countdown block stood here, along with the
            "Bidding Ended" pill it fell back to. Both are retired. The listing's state is already
            carried by the status pill above (Active / Pending / Expired / Hired Agent) and by the
            expiry notice below, neither of which counts down. No replacement urgency mechanism is
            introduced — that is the point of the retirement, not an omission.

            The sold branch is kept: it is driven by listing STATUS (an agent was selected), never
            by elapsed time, and it is the outcome notice rather than a deadline.
        --}}
        @if ($isSold)
            <div class="alert alert-success text-center mt-2 mb-0 p-2">
                <strong><i class="fa-solid fa-circle-check"></i> Agent Selected</strong>
            </div>
        @endif

        @php
        $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
        @endphp


        {{-- 🔹 Bid CTA.

             M5.4. The redesigned branch decides STATE FIRST, THEN VIEWER — hired, pending,
             expired, owner, then the agent/non-agent/guest cases. The legacy branch below asks
             "are you an agent?" first, and everything else is nested inside that answer, which is
             why a guest on an expired listing is still invited to "Login to Bid": the expiry
             notice lives inside a branch a guest never reaches.
        --}}
        @if ($hlaDetailRedesign)
            @php
                /*
                 | THE OWNER IS EXCLUDED FROM THE CTA ENTIRELY — no button, and no disabled state
                 | either. This is a correctness fix, not a preference. The legacy gate asks only
                 | whether the viewer is an agent, and listing creation carries no role middleware
                 | (routes/web.php: `middleware('landlordAuth')` is commented out under a comment
                 | claiming the opposite), so an `agent` user can own a landlord listing. Today
                 | that owner is shown "Bid Now" and the server then refuses the submission —
                 | LandlordAgentAuctionBid::…(BYA-H2 Rule B1) flashes "You cannot submit an agent
                 | bid on your own listing." and redirects. The CTA was offering an action the
                 | server had already decided to reject.
                 |
                 | An owner who is NOT an agent fares no better today: they fall through to the
                 | catch-all and are told "Only agents can place bids" about their own listing,
                 | which is both wrong and confusing. Neither viewer is offered a CTA now.
                 |
                 | Ownership is answered by HireAgentProposalAccess rather than re-derived here.
                 | See the M5.4 note where $hlaIsListingOwner is computed.
                 */
                $hlaViewerIsAgent = $auth_id && optional(auth()->user())->user_type === 'agent';
                $hlaListingHired  = $isSold || $auction->status === 'Hired Agent';
            @endphp

            @if ($hlaListingHired)
                <div class="alert alert-success text-center mb-2">
                    <i class="fa-solid fa-trophy"></i> <strong>An agent has been hired</strong>
                </div>
                <div class="status-pill status-hired w-100 d-flex justify-content-center">
                    <i class="fa-solid fa-trophy me-2"></i>Hired Agent
                </div>
            @elseif ($isPending)
                <div class="alert alert-warning text-center mb-2">
                    <i class="fa-solid fa-pause-circle"></i> <strong>This listing is pending &mdash; not accepting new bids</strong>
                </div>
                <div class="status-pill status-pending w-100 d-flex justify-content-center">
                    <i class="fa-solid fa-pause-circle me-2"></i>Pending
                </div>
            @elseif ($isExpired)
                <div class="alert alert-secondary text-center mb-2">
                    <i class="fa-solid fa-calendar-xmark me-1"></i> <strong>This listing has expired</strong>
                </div>
            @elseif ($hlaIsListingOwner)
                {{-- Deliberately nothing. See the note above: not a button, not a disabled
                     control, and not an explanatory alert — the owner has no bid workflow, so the
                     slot is empty rather than occupied by something inert. --}}
            @elseif ($hlaViewerIsAgent && $userHasBid)
                <div class="alert alert-info text-center mb-2">
                    <i class="fa-solid fa-circle-check"></i> You have already placed a bid
                </div>
                <div class="status-pill status-disabled w-100 d-flex justify-content-between">
                    <span>Bid Already Placed</span>
                    <span style="font-weight:normal;font-size:.85em;">${{ @$auction->get->budget }}</span>
                </div>
            @elseif ($hlaViewerIsAgent)
                {{-- Route unchanged: agent.landlord.agent.auction.bid, still behind AgentAuth. --}}
                <x-viho.button
                    :href="route('agent.landlord.agent.auction.bid', $auction->id)"
                    variant="primary"
                    :block="true"
                    icon="fa-solid fa-gavel">Bid Now</x-viho.button>
            @elseif ($auth_id)
                <div class="alert alert-secondary text-center mb-0">
                    Only agents can place bids
                </div>
            @else
                <x-viho.button
                    :href="route('login')"
                    variant="primary"
                    :block="true"
                    icon="fa-solid fa-right-to-bracket">Log in to bid</x-viho.button>
            @endif
        @else
            {{-- 🔹 Bid Button --}}
            @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
                @if (!$isExpired && !$isSold && !$isPending && $auction->status !== 'Hired Agent')
                    @if ($userHasBid)
                    {{-- User already placed a bid --}}
                    <div class="alert alert-info text-center mb-2">
                        <i class="fa-solid fa-circle-check"></i> You have already placed a bid
                    </div>
                    <div class="status-pill status-disabled w-100 d-flex justify-content-between">
                        <span>Bid Already Placed</span>
                        <span style="font-weight:normal;font-size:.85em;">${{ @$auction->get->budget }}</span>
                    </div>
                    @else
                    {{-- User can place a bid --}}
                    <button class="btn w-100 bid-btn"
                        onclick="window.location='{{ route('agent.landlord.agent.auction.bid', @$auction->id) }}';">
                        <span class="bid">Bid Now</span>
                        <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
                    </button>
                    @endif
                @elseif($auction->status === 'Hired Agent' || $isSold)
                    <div class="alert alert-success text-center mb-2">
                        <i class="fa-solid fa-trophy"></i> <strong>An agent has been hired</strong>
                    </div>
                    <div class="status-pill status-hired w-100 d-flex justify-content-center">
                        <i class="fa-solid fa-trophy me-2"></i>Hired Agent
                    </div>
                @elseif($isPending)
                    <div class="alert alert-warning text-center mb-2">
                        <i class="fa-solid fa-pause-circle"></i> <strong>This listing is pending &mdash; not accepting new bids</strong>
                    </div>
                    <div class="status-pill status-pending w-100 d-flex justify-content-center">
                        <i class="fa-solid fa-pause-circle me-2"></i>Pending
                    </div>
                @else
                {{-- Expiry catch-all. Milestone 3: this used to branch on listing type, suppressing
                     the notice for Bidding Period listings because the retired timer block had
                     already rendered "Bidding Ended". With the timer gone there is one expiry state
                     and one notice, driven by expiration_date. --}}
                    <div class="alert alert-secondary text-center mb-2">
                        <i class="fa-solid fa-calendar-xmark me-1"></i> <strong>This listing has expired</strong>
                    </div>
                @endif

            @elseif(!$auth_id)
                <a href="{{ route('login') }}">
                    <button class="btn w-100">
                        <span class="bid m-0">Login to Bid</span>
                        <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
                    </button>
                </a>
            @else
                <div class="alert alert-secondary text-center">
                    Only agents can place bids
                </div>
            @endif
        @endif

        @php
            // M5.4: delegated to HireAgentProposalAccess — see the note near the top of this file.
            $isListingOwner = $hlaIsListingOwner;
            $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
            // Build a stable per-agent alias map keyed by user_id.
            // Sort by created_at asc, id asc, user_id asc; first bid per unique agent sets that agent's alias.
            // Excludes the listing owner so alias numbers reflect only competing agents.
            $bidsByOrder = $auction->bids
                ->where('user_id', '!=', data_get($auction, 'user_id'))
                ->sortBy([['created_at', 'asc'], ['id', 'asc'], ['user_id', 'asc']])
                ->values();
            $agentNumberMap = []; // keyed by user_id → alias number
            foreach ($bidsByOrder as $orderedBid) {
                if (!isset($agentNumberMap[$orderedBid->user_id])) {
                    $agentNumberMap[$orderedBid->user_id] = count($agentNumberMap) + 1;
                }
            }

            /*
             | M5.5 — THE PROPOSAL CONSOLE EXISTS ONLY FOR VIEWERS WHO HAVE A PROPOSAL TO SEE.
             |
             | `<div class="card higestBider">` rendered unconditionally. For every viewer the
             | access layer hands zero proposals — a guest, a competing agent, an agent who has not
             | bid, an unrelated authenticated user, an administrator — it produced an empty ~30px
             | card. M5.3 and M5.4 made that conspicuous by removing everything that used to sit
             | around it: on a guest page the sidebar had become that empty card and one button.
             |
             | THE CONDITION IS ASKED OF THE SERVER-SIDE DECISION, NOT RE-DERIVED. Two allow
             | branches, matching HireAgentProposalAccess exactly:
             |
             |   · $canReviewAllProposals — the owner, who may review the whole set and must still
             |     get the console (and its empty state) when nobody has bid yet;
             |   · $auction->bids->isNotEmpty() — a submitting agent. That collection was ALREADY
             |     narrowed by restrictLoadedProposals() in the controller, so "non-empty" here
             |     means "this viewer is authorized to see at least one proposal" and cannot mean
             |     anything else. Asking the narrowed collection is what keeps this a presentation
             |     guard rather than a second opinion about authorization.
             |
             | THIS IS NOT THE PRIVACY MECHANISM AND MUST NOT BE MISTAKEN FOR ONE. Withholding
             | still happens server-side, before the view runs; the per-card gates are still there;
             | this only stops an empty container from being drawn. If this guard were deleted
             | tomorrow no proposal data would leak — which is precisely why it is safe to make it
             | a display decision, and why HireAgentBidCtaTest::test_the_view_is_handed_only
             | _authorized_proposals remains the test that proves the real rule.
             |
             | Flag-gated, like every other M5 change that alters what renders today. The empty
             | card is visible on the live page, so removing it unconditionally would change the
             | legacy page for everyone with no browser coverage to catch a regression.
             */
            $hlaProposalConsoleVisible = ($canReviewAllProposals ?? false)
                || $auction->bids->isNotEmpty();
        @endphp

        @if (! $hlaDetailRedesign || $hlaProposalConsoleVisible)
        <div class="card higestBider">
            <div class="card-body card-body-padding">
                {{--
                    Milestone 2 — the "Agent N was the last bidder." line was removed here. It is
                    not restored in any form. The empty state it shared an @if with is retained,
                    but gated on the server-side owner decision: a bid count is itself a
                    disclosure, so this message is owner-only rather than public.

                    M5.5 — the redesigned treatment moves it onto x-viho.empty-state. The GATE is
                    untouched and the sentence is unchanged, so the disclosure rule and the copy
                    both survive the swap; only the markup differs.
                --}}
                @if (($canReviewAllProposals ?? false) && $auction->bids->isEmpty())
                    @if ($hlaDetailRedesign)
                    <x-viho.empty-state
                        icon="fa-solid fa-inbox"
                        title="No agents have submitted a bid yet."
                        description="Proposals will appear here as agents respond to your listing." />
                    @else
                    <p>No agents have submitted a bid yet.</p>
                    @endif
                @endif
                @php
                    // ── Match Score Baseline (Landlord listing request as the reference) ──────
                    $auctionPropType = $auction->get->property_type ?? 'Residential Property';
                    $landlordBaselineData = json_decode(json_encode($auction->get ?? []), true) ?: [];
                    $getScoreColor = fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::scoreColor((int)$s);
                @endphp

                <div class="accordion" id="accordionExample">
                    <div class="accordion-item border-0">

                        @foreach (@$auction->bids as $bid)
                        @php
                            /*
                             | M5.5 — THE GUARD STAYS IN THE LOOP, deliberately.
                             |
                             | `continue` cannot cross into an included view. Blade compiles each
                             | view to its own function, so a `continue` inside the partial is a
                             | fatal "Cannot break/continue 1 level" rather than a skipped card.
                             | Keeping it here is also the stronger arrangement: an unauthorized
                             | row never reaches the partial at all, instead of being included and
                             | then abandoned.
                             |
                             | Milestone 2 — competing-agent proposal privacy. $auction->bids was
                             | already narrowed by HireAgentProposalAccess in the controller. This
                             | is defence in depth, with the opposite default to the gate it
                             | replaced: skip anything that is not the owner's to review or the
                             | viewer's own.
                             */
                            $isBidOwner  = (data_get($bid, 'user_id') == $auth_id);
                            $agentNumber = $agentNumberMap[$bid->user_id] ?? $loop->iteration;

                            if (! $isListingOwner && ! $isBidOwner) { continue; }
                        @endphp
                        @include('hire_landlord_agent.partials.proposal_card')
                    @endforeach


                </div>
            </div>
        </div>
</div>
@endif{{-- M5.5 proposal console --}}
{{-- M5.4: a full-width button carrying a single user icon — no label, no handler, no
     destination, and no accessible name. It predates M5 and renders for every viewer. It is
     removed only behind the redesign flag: it is visible today, so deleting it outright would
     change the legacy page for everyone, and "it looks like a mistake" is not the same standard
     as "it can never render" (which is why the dead legacy `sold` branch above WAS removed
     unconditionally — no such column or accessor exists, so it emitted nothing either way).
     M5.3 made this one conspicuous by suppressing the share card that used to sit beneath it. --}}
@unless ($hlaDetailRedesign)
<button class="btn w-100 mt-0">
    <span class="bid m-0"><i class="fa-solid fa-user"></i> </span>
</button>
@endunless
{{-- M5.3: the sidebar share card is suppressed when the redesign is on — Share Listing and Copy
     Link both live in the Quick Actions band above the grid. The QR code goes with it; it has no
     tile because a QR image is listing INFORMATION rather than an action, and re-siting it is a
     sidebar question, which is M5.4. --}}
@unless ($hlaDetailRedesign)
<div class="p-4 card">
    <p class="text-600">Share this link via</p>
    <div class="qr-code" style="width: 100%; height:200px;">
        {{ qr_code(route('landlord.agent.auction.view', @$auction->id), 200) }}
    </div>
    <div class="card-social">
        <ul class="icons">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('landlord.agent.auction.view', $auction->id)) }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-facebook-f"></i>
            </a>
            <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('landlord.agent.auction.view', $auction->id)) }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-twitter"></i>
            </a>
            <a href="">
                <i class="fa-brands fa-instagram"></i>
            </a>
            <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(route('landlord.agent.auction.view', $auction->id)) }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-pinterest"></i>
            </a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('landlord.agent.auction.view', $auction->id)) }}" target="_blank" rel="noopener">
                <i class="fa-brands fa-linkedin"></i>
            </a>
        </ul>
        <p class="small opacity-8">Or copy link</p>
        <div class="field">
            <i class="fa-solid fa-link"></i>
            <input type="text" readonly="" id="copylink"
                value="{{ route('landlord.agent.auction.view', $auction->id) }}">
            <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                style="min-width:60px;">Copy</button>
        </div>
    </div>
</div>
@endunless
        </x-slot>

        {{--
            M4 — the Edit Listing control, relocated into the hero for the piloted role.

            THE SLOT ITSELF IS CONDITIONAL, not just its contents. An always-emitted slot would be
            `isset()` even when empty, and the legacy hero would then render an empty actions
            wrapper — a DOM change on a page the flag is supposed to leave untouched.

            The authorization test is the one this control has always carried, unchanged:
            owner-only, by user id. `auth()->id()` is read directly rather than through $auth_id so
            this does not depend on the sidebar slot having been captured first.

            Route, params, label, icon and classes are identical to the sidebar control it
            replaces. Message/View Profile are deliberately NOT hoisted: they live in the user
            review card further down, which this milestone does not touch, and lifting them would
            create the second copy this change exists to remove.
        --}}
        @if (\App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('landlord')
            && auth()->id() && auth()->id() == @$auction->user_id)
        <x-slot name="heroActions">
            <a href="{{ route('landlord.hire.agent.auction.edit', ['auctionId' => $auction->id]) }}"
               class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
            </a>
        </x-slot>
        @endif
    </x-hire-agent.detail-shell>
{{-- Milestone 5A: an accidental trailing <hr> stood here, as the last node on the page with
     nothing after it to separate. Removed. Buyer has a superficially similar trailing <hr>
     which is NOT accidental — it divides the listing from the "Recommended For You" section
     that follows it — and is deliberately kept. --}}
@endsection

@push('scripts')
{{--
    Milestone 3: the timer.jquery CDN tag and the whole countdown initialiser were removed from
    here. Beyond rendering the clock, its onTimerEnd callback replaced the countdown with a
    "Bidding Ended" pill, faded out the Bid button, and then force-reloaded the page two seconds
    later to pick up "updated bid statuses" — a client-side, timer-derived proposal restriction
    layered on top of the server-side one. Proposal availability is now decided solely by listing
    status and expiration_date, server-side. No JavaScript countdown is initialised on this page
    any more, and the library is no longer loaded at all.
--}}
<script>
document.querySelectorAll('.hla-bid-accordion-header').forEach(function(header) {
    header.addEventListener('click', function() {
        var targetId = this.getAttribute('data-target');
        var target = document.getElementById(targetId);
        var chevron = this.querySelector('.bid-chevron');
        if (!target) return;
        if (target.style.display === 'none' || target.style.display === '') {
            target.style.display = 'block';
            if (chevron) chevron.style.transform = 'rotate(-180deg)';
            this.setAttribute('aria-expanded', 'true');
        } else {
            target.style.display = 'none';
            if (chevron) chevron.style.transform = 'rotate(0deg)';
            this.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>

{{-- M5.2b — active section highlighting. Flag-gated: with the redesign off this page pushes no
     additional script, so there is no new behaviour to regress. --}}
@if (\App\Support\HireAgent\HireAgentDetailRedesign::enabled())
<script>
/*
    The behaviour half of the section navigation, and the reason x-viho.section-nav ships without
    a script of its own: "which section am I reading" is a product question, and a primitive that
    answered it would be answering it for every page that ever adopted the bar.

    SCROLLING IS NOT HERE. The links are real hrefs and `scroll-behavior: smooth` is in the
    stylesheet above, so the browser does the scrolling and respects prefers-reduced-motion. This
    file only decides which link is marked current.

    It reads --viho-section-nav-offset rather than repeating 0/104: the breakpoint lives in CSS,
    where it can be seen next to the rule it belongs to, and a media query is the wrong thing to
    duplicate in JavaScript.
*/
(function () {
    var nav = document.querySelector('[data-viho-section-nav]');
    if (!nav) { return; }

    // Pair each link with the element it points at. A link whose target is missing is dropped
    // rather than tracked — it cannot become current, and the nav is built so it cannot happen.
    var pairs = [];
    Array.prototype.forEach.call(nav.querySelectorAll('[data-viho-section-nav-link]'), function (link) {
        var id = (link.getAttribute('href') || '').slice(1);
        var el = id ? document.getElementById(id) : null;
        if (el) { pairs.push({ link: link, el: el }); }
    });

    if (!pairs.length) { return; }

    // The line a heading has to cross to count as "the section being read": the fixed chrome the
    // page declares, plus the bar itself, which sits directly below it.
    function readingLine() {
        var declared = parseFloat(
            getComputedStyle(document.documentElement).getPropertyValue('--viho-section-nav-offset')
        );
        return (isNaN(declared) ? 0 : declared) + nav.offsetHeight + 1;
    }

    function sync() {
        ticking = false;

        var line = readingLine();
        var currentIndex = 0;

        for (var i = 0; i < pairs.length; i++) {
            if (pairs[i].el.getBoundingClientRect().top <= line) { currentIndex = i; }
        }

        // At the bottom of the document the last sections may be too short to ever reach the
        // line, so the final entry would never light up. Award it explicitly at the end.
        if (window.innerHeight + window.pageYOffset >= document.documentElement.scrollHeight - 2) {
            currentIndex = pairs.length - 1;
        }

        for (var j = 0; j < pairs.length; j++) {
            if (j === currentIndex) {
                pairs[j].link.setAttribute('aria-current', 'true');
            } else {
                pairs[j].link.removeAttribute('aria-current');
            }
        }
    }

    // Scroll fires far more often than the page can repaint; coalesce to one update per frame.
    var ticking = false;
    function request() {
        if (ticking) { return; }
        ticking = true;
        window.requestAnimationFrame(sync);
    }

    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request);
    sync();
}());
</script>

<script>
/*
    M5.3 — Copy Link.

    The behaviour half of the Quick Actions band. x-viho.quick-actions and x-viho.action-tile ship
    no script by contract, so a caller that wants a control to DO something wires it here.

    This is a new handler rather than a reuse: the sidebar's legacy Copy button carries a hook
    class that nothing in the repository binds to. It is dead markup in this view and in about ten
    others. Fixing all of them is not this milestone's scope, so this control gets its own working
    handler and the legacy one is left exactly as it was.

    Two paths, because the modern one is not always available: navigator.clipboard requires a
    secure context, so it is absent on any environment served over plain HTTP. The textarea +
    execCommand fallback is the same shape dashboard.blade.php already uses.
*/
(function () {
    var buttons = document.querySelectorAll('[data-hla-copy-link]');
    if (!buttons.length) { return; }

    function confirmCopy(button, ok) {
        var tile = button.closest('.viho-action-tile');
        var status = tile ? tile.querySelector('[data-hla-copy-status]') : null;
        if (!status) { return; }
        status.textContent = ok ? 'Link copied' : 'Press Ctrl+C to copy';
        window.setTimeout(function () { status.textContent = ''; }, 2500);
    }

    function legacyCopy(text) {
        var field = document.createElement('textarea');
        field.value = text;
        // Kept in the viewport but visually inert: a field positioned off-screen is not always
        // selectable, and display:none never is.
        field.setAttribute('readonly', 'readonly');
        field.style.position = 'fixed';
        field.style.top = '0';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(field);
        return ok;
    }

    Array.prototype.forEach.call(buttons, function (button) {
        button.addEventListener('click', function () {
            var url = button.getAttribute('data-hla-copy-link');
            if (!url) { return; }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(
                    function () { confirmCopy(button, true); },
                    function () { confirmCopy(button, legacyCopy(url)); }
                );
                return;
            }

            confirmCopy(button, legacyCopy(url));
        });
    });
}());
</script>
@endif
@endpush
