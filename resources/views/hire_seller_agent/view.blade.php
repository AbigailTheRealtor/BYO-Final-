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

  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };

  $basisText = function($basis) {
    return $basis ? ('of ' . $basis) : null;
  };
@endphp

@push('styles')
{{-- Hire Agent Listing Detail Framework (Milestone 4): the thirty rules that were
     byte-identical across all four detail views now live in one place. --}}

{{--
    Milestone 3 — the shared VIHO foundation, extended from the approved Landlord pilot.

    Included HERE rather than in the shared detail shell, and that placement is the whole point:
    the shell is rendered by all four roles, so putting it there would have enrolled Buyer and
    Tenant in a migration they are not part of. Landlord and Seller are the only roles that have
    migrated, so they are the only two files that load it. Buyer and Tenant keep rendering
    exactly as they do today.

    It arrives AFTER the framework stylesheet, which matters: where the two define the same
    property for an element that carries both class families, VIHO wins. That is the intended
    direction of the migration and the reason this page now looks like Create Offer.
--}}
@include('viho.styles')

{{-- Residual Seller-only rules. These LOOK shared but are not: they differ
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
/* Service category title styling */
    .service-category-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
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
/* Counter (blue) - always solid blue background, same size as Reject */
    .btn-counter {
        background-color: #0d6efd !important;
        color: #ffffff !important;
    }
.btn-counter:hover {
        background-color: #0b5ed7 !important;
    }
/* Left column content - vertically centered with symmetrical padding */
    .leftCol .card.description .card-body {
        padding-top: 1.75rem;
        padding-bottom: 1.75rem;
    }
.leftCol .card.description {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
/* Fix white space below bid cards - ensure collapse content uses natural height */
    .card.higestBider .accordion-item > .card.mb-3 {
        margin-bottom: 0.75rem;
    }
.card.higestBider .accordion-item > .card.mb-3 > .collapse {
        height: auto;
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

{{-- S1 — THE STICKY OFFSET FOR THE SECTION NAV, SUPPLIED BY THE CONSUMER.

     x-viho.section-nav declares `position: sticky` and deliberately leaves `top` unset, because the
     only correct value is the height of whatever fixed chrome the host page puts above the bar —
     which the primitive cannot know and must not guess. This page is that host, so this page
     answers. The values are buyer's and landlord's, and they are the same values because the CHROME
     is the same: all three render through layouts.main, which has no fixed header above the reading
     column on desktop and a 104px header bar below the lg breakpoint.

     Declared here rather than in the framework stylesheet because that file may READ --viho tokens
     and this one DECLARES them; the rule that consumes both lives there and cannot move here.

     TWO VARIABLES, NOT ONE, AND THE ARITHMETIC IS THE REASON. The bar sticks at the height of the
     chrome above it. A scroll target must clear the chrome AND the bar itself, because the bar is
     what it is being scrolled underneath. Reusing one value for both leaves the target short by
     exactly the bar's own height — 0px of clearance on desktop, where the chrome is 0 and the bar is
     not. Landlord shipped that bug in M7.2 and M7.4 measured it; seller inherits the fix.

     Gated on the ROLE-AWARE reader, not the master switch: declaring offsets for a role the shell
     has withheld the layout from is the M7.1 disagreement in miniature. --}}
@if (\App\Support\HireAgent\HireAgentDetailRedesign::enabledFor('seller'))
<style>
:root {
    --viho-section-nav-offset: 0px;

    /* The bar's own height. Generous on purpose: measured at 46.9px on the landlord page, declared
       at 3.5rem (56px). Overshooting parks the card a few pixels below the bar, which reads as
       breathing room; undershooting clips the header, which is the bug this avoids. */
    --viho-section-nav-height: 3.5rem;
}
@media (max-width: 991.98px) {
    :root {
        --viho-section-nav-offset: 104px;
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
         | S1 — THE SELLER DETAIL REDESIGN, RESOLVED ONCE.
         |
         | Seller is the last of the four roles to adopt the framework. It took the shared shell in
         | M5A.3 and nothing since, so until this milestone its whole main column was one
         | x-viho.card holding eight sub-headings, and it consulted neither the section registry nor
         | the flag.
         |
         | enabledFor('seller') rather than enabled(), for the reason the reader class states at
         | length: the master switch answers "is the redesign on at all" and is not a gate. A view
         | gating on it would hand seller the new layout on the switch that turns landlord on. The
         | role is passed to the service and never tested here — no equality check against a role
         | name lives in this file.
         */
        $hsaDetailRedesign = \App\Support\HireAgent\HireAgentDetailRedesign::enabledFor('seller');

        /*
         | ── THE HOISTED DERIVATIONS ──────────────────────────────────────────────────────────
         |
         | Four blocks move up here from inside the main slot: the financing pill list, the
         | representation rows, the referral percentage and the owner-info heading. They are pure
         | reads of the listing that emit no markup, so hoisting them cannot change what the page
         | renders — buyer and landlord hoisted the same expressions for the same reason.
         |
         | THE REASON IS THAT THE NAV IS BUILT BEFORE THE SECTIONS ARE. A guard has to answer
         | "does this section have content" up here, and three of these sections derive the very
         | values that answer it. Re-deriving them in two places is how a nav entry comes to point
         | at a card that decided not to render.
         */

        /*
         | Financing. Guarded from the resolved pill list rather than from the raw meta, because
         | `offered_financing` can hold '[]' — a stored answer that normalises to nothing. Asking
         | anyHasValue() of the raw key would call that a populated section and publish a nav entry
         | above an empty card.
         */
        $hsaFinancingData  = @$auction->get->offered_financing;
        $hsaFinancingArray = [];
        if ($hsaFinancingData) {
            $hsaFinancingArray = is_string($hsaFinancingData)
                ? (json_decode($hsaFinancingData, true) ?? [])
                : (is_array($hsaFinancingData) ? $hsaFinancingData : []);
        }
        $hsaFinancingArray = array_filter($hsaFinancingArray);
        $hsaOtherFinancing = str_replace('"', '', @$auction->get->other_financing ?? '');
        $hsaFinancingPills = \App\Helpers\ListingDisplayHelper::normalizeList($hsaFinancingArray, $hsaOtherFinancing);

        /*
         | T6 — THE SELLER PRICE ON THE THREE SIDEBAR CTA BADGES.
         |
         | The badges beside "Bid Already Placed", "Bid Now" and "Login to Bid" read
         | `$auction->get->budget` and hardcoded a `$` in front of it. That key is never written by
         | the seller flow — it is the same dead key T3 removed from the hero — so all three
         | rendered a bare dollar sign with no number. Visible on a rendered page, which is how it
         | was found.
         |
         | Re-pointed at `maximum_budget`, the same source of truth T3 gave the hero, resolved ONCE
         | here rather than three times inline so the three badges cannot drift apart.
         |
         | fmtMoneyWhole() supplies the `$`, which is why the literal one is removed from all three
         | call sites. It is the helper the hero already uses on this exact field: it strips
         | thousands separators, formats a numeric value as currency, and returns a non-numeric
         | value unchanged — so a stored "Negotiable" reads as "Negotiable" rather than
         | "$Negotiable". That last behaviour differs from the detail body's "Desired Sale Price"
         | row, which prefixes `$` unconditionally; the hero's treatment is the right one for a
         | badge, and this deliberately follows the hero.
         |
         | NULL WHEN ABSENT, and each badge is wrapped in a truth test rather than rendering an
         | empty span — replacing a bare `$` with an empty box would not be a fix. hasValue() is
         | the same absence rule the rest of this page uses, so 'null', '' and the placeholder
         | strings all count as missing.
         |
         | NOTHING ELSE ABOUT THE CTAs CHANGES: not the authorization above them, not the wording,
         | not the routes, not the visibility conditions, not the bid logic.
         */
        $hsaCtaPrice = \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->maximum_budget)
            ? \App\Helpers\ListingDisplayHelper::fmtMoneyWhole(@$auction->get->maximum_budget)
            : null;

        /*
         | Owner info heading. Resolved in PHP rather than inline because a bound attribute
         | containing `&&` is not parseable by Blade's attribute compiler. Same expression and same
         | two outcomes as the copy it replaces further down.
         |
         | It keeps the one-type `user_type === 'agent'` check rather than isAgentUser(): that is
         | the latent defect HireAgentDetailAudienceTest records for all four roles, and correcting
         | it here would change what the legacy page calls a real user. The agent-credentials guard
         | below deliberately does NOT repeat it.
         */
        $_ownerInfoHeading = ($auction->user && $auction->user->user_type === 'agent')
            ? "Agent's Info"
            : "Seller Info";

        /*
         | Referral. Falls back to the FIRST bid's referral percentage when the listing carries
         | none — business logic that predates this milestone and is reproduced verbatim.
         */
        $referralPct = trim((string)($auction->get->referral_percentage ?? ''));
        if ($referralPct === '') {
            $_firstBid = $auction->bids()->orderBy('id', 'asc')->first();
            if ($_firstBid) {
                $referralPct = trim((string)($_firstBid->get->referral_fee_percent ?? ''));
            }
            unset($_firstBid);
        }
        $referralPctDisplay = $referralPct !== '' ? (str_ends_with($referralPct, '%') ? $referralPct : $referralPct . '%') : '';

        /*
         | Representation & Compatibility. The whole builder moves as one unit; every $repAdd call
         | and its ordering is unchanged.
         */
        $rawCompatView = $auction->info('compatibility_preferences');
        $compatView    = ($rawCompatView !== null && $rawCompatView !== '')
            ? (json_decode($rawCompatView, true) ?? [])
            : [];
        $ssView = $compatView['seller_specific'] ?? [];

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

        // Phase 5/6 QA Follow-up (Seller Rep & Compatibility): full listing-display parity — every
        // field captured by the Seller representation form is rendered here when populated.
        // Primary Transaction Goal resolves its "Other" custom value for display.
        $repAdd('Primary Transaction Goal', $ssView['primary_transaction_goal'] ?? '', $ssView['primary_transaction_goal_other'] ?? '');
        $repAdd('Target Sale Timeline', $ssView['target_sale_timeline'] ?? '', '');
        $repAdd('Timeline Flexibility', $ssView['flexibility_on_timeline'] ?? '', '');
        $repAdd('Post-Sale Plans', $ssView['post_sale_plan'] ?? '', '');
        $repAdd('Representation Priorities', $ssView['representation_priorities'] ?? [], '');
        $repAdd('Agent Qualities Most Important to You', $ssView['qualities_most_important'] ?? [], '');
        $repAdd('Preferred Communication Style', $ssView['communication_style'] ?? '', '');
        $repAdd('Preferred Contact Method', $ssView['preferred_contact_method'] ?? [], '');
        $repAdd('Expected Agent Response Time', $ssView['response_time_expectation'] ?? '', '');
        $repAdd('Negotiation Style', $ssView['negotiation_style'] ?? '', '');
        $repAdd('Areas Willing to Negotiate On', $ssView['willing_to_negotiate_on'] ?? [], '');
        $repAdd('Firm on Asking Price', $ssView['firm_on_price'] ?? '', '');
        $repAdd('Preferred Agent Working Style', $ssView['preferred_agent_working_style'] ?? '', '');
        $repAdd('Decision-Making Style', $ssView['decision_making_style'] ?? '', '');
        $repAdd('Involvement Level', $ssView['involvement_level'] ?? '', '');
        $repAdd('Decision Makers Involved', $ssView['additional_decision_makers'] ?? '', '');
        $repAdd('Past Experience Working with a Real Estate Agent', $ssView['past_agent_experience'] ?? '', '');
        $repAdd('What Did Not Work Well with Past Agents', $ssView['what_did_not_work_before'] ?? '', '');
        $repAdd('Showing Availability', $ssView['showing_availability'] ?? [], '');
        $repAdd('Open House Preference', $ssView['open_house_preference'] ?? '', '');
        $repAdd('Additional Compatibility Notes', $ssView['additional_compatibility_notes'] ?? '', '');

        $hsaSections = [];

        if ($hsaDetailRedesign) {
            /*
             | ── THE SECTION GUARDS ──────────────────────────────────────────────────────────
             |
             | One boolean per section, computed UNCONDITIONALLY and AUDIENCE-BLIND. The resolver
             | drops the ones this viewer's tier does not admit, so a withheld section reaches
             | neither its card nor the bar. $hlaAudience arrives resolved from
             | SellerAgentAuctionController and is passed, never compared — an audience test in
             | Blade would be a second opinion about a rule that already has an owner, and the nav
             | is where such a drift becomes a disclosure, because the bar names what it links to.
             |
             | THE LISTS ARE COMPLETE, AND COMPLETENESS IS THE SAFETY PROPERTY. A key omitted here
             | is not a cosmetic miss — the section is judged empty and hidden while still holding
             | a row that renders. Each was derived by enumerating every `$auction->get->` read
             | inside the section's line range rather than written from memory.
             */

            /*
             | `listing_title` is NOT in this list, and its absence is deliberate. Both Seller
             | components write the answer to the auction's native `title` COLUMN
             | (`$auction->title = $this->listing_title`) and never as `listing_title` meta, while
             | `$auction->get` reads meta alone — so the key is always null and contributes
             | nothing. Buyer, landlord and tenant each removed the same dead row during their own
             | conversions; removing SELLER's row is T2's job, and the guard simply declines to
             | advertise a field the section cannot render.
             */
            $hsaHasListingDetails = \App\Helpers\ListingDisplayHelper::anyHasValue([
                @$auction->get->working_with_agent,
                @$auction->get->desired_agent_hire_date,
                @$auction->get->listing_date,
                @$auction->get->expiration_date,
                @$auction->get->auction_type,
                @$auction->get->meeting_Preference,
            ]);

            /*
             | Property Details — sixty keys, and the section is the largest on any of the four
             | pages. It absorbs the "Business/Property Assets" and "Income & Investment Metrics"
             | sub-headings rather than promoting them: the section registry's own config file
             | records that decision by name, and buyer's "Required Property or Business Assets" is
             | kept the same way. They are divisions within one subject.
             |
             | (That file is described rather than named, here and below. A guard asserts the
             | resolver class is the only thing in app/, resources/views/ or routes/ whose source
             | contains the config key — and prose counts, so naming it would register this view as
             | a second reader of a registry it only consumes through the resolver.)
             */
            $hsaHasProperty = \App\Helpers\ListingDisplayHelper::anyHasValue([
                @$auction->get->cities, @$auction->get->counties,
                @$auction->get->property_city, @$auction->get->property_county,
                @$auction->get->property_state, @$auction->get->state,
                @$auction->get->property_zip, @$auction->get->zip_code,
                @$auction->get->property_type, @$auction->get->property_items,
                @$auction->get->other_property_style,
                @$auction->get->business_type, @$auction->get->business_type_selected,
                @$auction->get->other_business_type,
                @$auction->get->condition_prop, @$auction->get->condition_prop_buyer,
                @$auction->get->other_property_condition,
                @$auction->get->bedrooms, @$auction->get->other_bedrooms,
                @$auction->get->bathrooms, @$auction->get->other_bathrooms,
                @$auction->get->minimum_heated_square, @$auction->get->total_square_feet,
                @$auction->get->sqft_heated_source, @$auction->get->minimum_net_leasable_square,
                @$auction->get->total_acreage,
                @$auction->get->carportOptions, @$auction->get->custom_carport,
                @$auction->get->garageOptions, @$auction->get->custom_garage,
                @$auction->get->carport_needed, @$auction->get->other_carport_needed,
                @$auction->get->garage_needed, @$auction->get->other_garage_needed,
                @$auction->get->garage_parking_spaces_option,
                @$auction->get->other_parking_space_wrapper,
                @$auction->get->appliances, @$auction->get->other_appliances,
                @$auction->get->pool_needed,
                @$auction->get->view_preference, @$auction->get->other_preferences,
                @$auction->get->leasing_55_plus,
                @$auction->get->non_negotiable_amenities,
                @$auction->get->other_non_negotiable_amenities,
                @$auction->get->pets, @$auction->get->number_of_pets,
                @$auction->get->type_of_pets, @$auction->get->weight_of_pets,
                @$auction->get->breed_of_pets, @$auction->get->breed_restrictions,
                @$auction->get->has_breed_restrictions,
                @$auction->get->assets, @$auction->get->business_assets,
                @$auction->get->assets_other,
                @$auction->get->real_estate_purchase,
                @$auction->get->minimum_annual_net_income, @$auction->get->minimum_cap_rate,
                @$auction->get->unit_number, @$auction->get->unit_buildings,
                @$auction->get->unit_type_configurations,
            ]);

            /*
             | Sale Terms. `offered_financing` and `other_financing` are read inside this section's
             | line range but belong to Financing below — they drive the pill list that opens it,
             | not any Sale Terms row — so they are guarded there and not here.
             */
            $hsaHasTerms = \App\Helpers\ListingDisplayHelper::anyHasValue([
                @$auction->get->sale_provision, @$auction->get->sale_provision_other,
                @$auction->get->sale_provision_assignment,
                @$auction->get->buyer_sell_contract,
                @$auction->get->assignment_fee_amount, @$auction->get->assignment_fee_type,
                @$auction->get->target_closing_date,
                @$auction->get->occupant_status, @$auction->get->occupant_tenant,
                @$auction->get->maximum_budget,
            ]);

            /*
             | Financing. The section opens on the pill list and every config-driven sub-block
             | renders inside it, so the pill list IS the guard — no separate enumeration of
             | config/seller-financing-config.php field keys can make it truer.
             */
            $hsaHasFinancing = ! empty($hsaFinancingPills);

            $hsaHasAdditionalDetails = \App\Helpers\ListingDisplayHelper::hasValue(
                @$auction->get->additional_details
            );

            /*
             | Owner info. The four values below ARE the section — first_name plus the three media
             | fields — so the list is complete rather than representative. isset() for `photo`,
             | matching the guard the img row itself uses: a filename is a filename and the
             | placeholder rules do not apply to it.
             |
             | `current_status` is included because Seller renders a "Seller's Current Status" row
             | that landlord has no counterpart for.
             */
            $hsaHasOwnerInfo = \App\Helpers\ListingDisplayHelper::anyHasValue([
                @$auction->get->first_name,
                @$auction->get->current_status,
                @$auction->get->video,
                @$auction->get->video_link,
            ]) || isset($auction->get->photo);

            /*
             | Agent Credentials — the one guard that is new rather than hoisted. Seller renders no
             | such section today; T4 adds the card, and the registry already carries the label, so
             | the guard arrives with the scaffold rather than after it.
             |
             | isAgentUser() rather than `user_type === 'agent'`, because three user types are
             | agents and the bare comparison silently demotes buyer_agent and seller_agent to
             | consumers. It asks about the listing OWNER, which is a different question from the
             | viewer's audience.
             */
            $hsaOwnerIsAgent = $auction->user
                && app(\App\Services\HireAgent\HireAgentDetailAudience::class)->isAgentUser($auction->user);

            $hsaHasAgentCredentials = $hsaOwnerIsAgent && \App\Helpers\ListingDisplayHelper::anyHasValue([
                @$auction->user->brokerage,
                @$auction->user->license_no,
                @$auction->user->phone,
                @$auction->user->email,
            ]);

            $hsaSections = app(\App\Support\HireAgent\HireAgentDetailSections::class)->resolveForRole(
                'seller',
                $hlaAudience,
                [
                    'listing-details'    => $hsaHasListingDetails,
                    'property'           => $hsaHasProperty,
                    'terms'              => $hsaHasTerms,
                    'financing'          => $hsaHasFinancing,
                    'additional-details' => $hsaHasAdditionalDetails,
                    'representation'     => ! empty($repRows),
                    'referral'           => $referralPctDisplay !== '',
                    'role-info'          => $hsaHasOwnerInfo,
                    'agent-credentials'  => $hsaHasAgentCredentials,
                ],
                ['role-info' => $_ownerInfoHeading],
            );
        }

        /*
         | Retained for the section cards, which ask isset() on the resolved set. A tiny helper
         | rather than repeating the array lookup nine times, and it is the ONLY thing the cards
         | consult — no card re-derives its own visibility. With the redesign off $hsaSections stays
         | empty, every card falls back to its legacy branch, and this closure is never reached.
         */
        $hsaShows = function (string $key) use (&$hsaSections): bool {
            return isset($hsaSections[\App\Support\HireAgent\HireAgentDetailSections::ID_PREFIX . $key]);
        };
    @endphp
    @php
        // T4 — seller counterpart of $byaListingUrl / $hlaListingUrl / $tnaListingUrl. Resolved
        // once here because the Quick Actions band below uses it five times (four share targets
        // and the copy control), and a route() call repeated per tile is five chances for them to
        // drift apart.
        //
        // `seller.agent.auction.detail` is the URL this page's own legacy copy control already
        // hands out further down, so the new Copy Link control and the old one agree about what
        // "this listing" means. Same reasoning tenant recorded for its own URL choice.
        $hsaListingUrl = route('seller.agent.auction.detail', $auction->id);
    @endphp

    {{-- Milestone 5A.3: flash, hero, the listing container, the grid row and both column
         wrappers now come from the shared shell. Only role-specific content lives here. --}}
    <x-hire-agent.detail-shell role="seller" :auction="$auction">
        @if ($hsaDetailRedesign)
        {{-- T4 — chrome parity with the buyer, landlord and tenant pages. Full-width, above the
             grid: these are page-level actions, not main-column content, which is what the shell's
             beforeGrid slot exists for.

             THE SLOT ITSELF IS INSIDE THE FLAG, not just its contents — the T3 lesson about
             heroActions applies verbatim. An always-emitted slot is `isset()` in the shell even
             when it renders nothing, and the shell would then emit its beforeGrid position on a
             page the flag is supposed to leave untouched.

             The tiles are the set all three completed roles carry, in the same order, with seller
             routes and seller wording. No tile is added and none is dropped: Send Message uses the
             `seller-agent` chat channel this page already links to twice, and Share/Copy Link
             point at $hsaListingUrl. No new business action is introduced here — every target
             already existed on this page. --}}
        <x-slot name="beforeGrid">
            <x-viho.quick-actions label="Quick Actions" icon="fa-solid fa-bolt" ariaLabel="Quick actions">

                {{-- 1. Send Message — authenticated user action; the route enforces it, exactly as
                     it does for the two existing seller links to the same conversation. --}}
                <x-viho.action-tile
                    :href="route('auction-chat', ['seller-agent', $auction->id])"
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
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($hsaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on Facebook">
                                    <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://twitter.com/intent/tweet?url={{ urlencode($hsaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on X">
                                    <i class="fa-brands fa-twitter" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://pinterest.com/pin/create/button/?url={{ urlencode($hsaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on Pinterest">
                                    <i class="fa-brands fa-pinterest" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($hsaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on LinkedIn">
                                    <i class="fa-brands fa-linkedin" aria-hidden="true"></i>
                                </a>
                            </li>
                        </ul>
                    </x-slot>
                </x-viho.action-tile>

                {{-- 3. Copy Link — public action.
                     The legacy share card further down carries a `js-copy-link` button that NOTHING
                     in the repository binds a handler to — dead here as it is in the other three
                     views. This one is wired by the quick-actions behaviour partial.

                     THE LEGACY CARD IS KEPT, AND THE COMPLETED ROLES DISAGREE ABOUT THIS. Landlord
                     suppresses its share/QR card under the detail flag (M5.3, with a note that the
                     QR's re-siting was deferred); buyer leaves its copy in afterGrid ungated, and
                     tenant leaves its copy in the sidebar ungated. Two of three keep it, including
                     tenant, the role migrated immediately before this one — so seller keeps it too.
                     Removing a working control is not something to infer from a one-role
                     precedent, and the flags-off page is identical either way. If the product wants
                     the landlord treatment everywhere, that is one deliberate change across three
                     views, not a decision to make quietly here. --}}
                <x-viho.action-tile
                    icon="fa-solid fa-link"
                    label="Copy Link"
                    description="Copy a direct link to this listing.">
                    <x-slot name="action">
                        <x-viho.button
                            variant="outline"
                            icon="fa-solid fa-link"
                            data-hla-copy-link="{{ $hsaListingUrl }}">Copy Link</x-viho.button>
                        <span class="hla-quick-copy-status" data-hla-copy-status role="status" aria-live="polite"></span>
                    </x-slot>
                </x-viho.action-tile>

            </x-viho.quick-actions>
        </x-slot>
        @endif

        <x-slot name="main">
            {{-- T4. Outside the card wrapper and above it, so the bar spans the column and sticks
                 to the top of the reading area rather than to the inside of a card. $hsaSections is
                 the same registry the section cards consult, so a card and its nav entry cannot
                 disagree about which sections exist. --}}
            @if ($hsaDetailRedesign)
                <x-viho.section-nav :items="array_values($hsaSections)" ariaLabel="Listing sections" />
            @endif
            {{--
                M3. Was `div.card.description` wrapping `card-header.section-header` + an
                `h4.section-title` + `card-body`. The heading level stays h4: typography is
                migrating, the document outline is not.

                This is one card containing twelve sub-sections, not twelve sibling cards — the
                rendered DOM has exactly two children under leftCol, this and the review card.
                The sub-headings below therefore become x-viho.section-header rather than more
                cards, which is what keeps the section order and nesting identical. Blade source
                could not settle that question, because @if branches make div-counting
                unreliable; it was resolved by rendering the page and reading real DOM.
                S1 — the wrapper card becomes x-hire-agent.detail-body, which emits exactly this
                x-viho.card with the flag off and nothing at all with it on. The sub-headings below
                become x-hire-agent.detail-section, which emits exactly the x-viho.section-header
                they already had with the flag off, and a card of their own with it on. Seller is
                the last of the four roles to make this move; the note above describes what was
                true until now and stays as the record of why one card was correct then.
            --}}
            <x-hire-agent.detail-body :redesign="$hsaDetailRedesign" title="Listing Details:">

            {{-- The wrapper card's own title WAS this section's heading, so it emits no header of
                 its own with the flag off — :legacy-header="false". With the flag on it becomes a
                 card like any other and needs one. The single place in this file that passes it. --}}
            @if (! $hsaDetailRedesign || $hsaShows('listing-details'))
            <x-hire-agent.detail-section :redesign="$hsaDetailRedesign" id="hla-section-listing-details" title="Listing Details:" icon="fa-solid fa-file-lines" :legacy-header="false">
                        <div class="row" style="flex-wrap: wrap;">
                            {{-- S2 — the "Listing Title" row is gone, and it could never have
                                 rendered. The questionnaire DOES ask for a listing title, but both
                                 Seller components store the answer in the auction's native `title`
                                 COLUMN — SellerAgentAuction and SellerAgentAuctionEdit each write
                                 `$auction->title = $this->listing_title` — and never as
                                 `listing_title` meta. `$auction->get` reads the meta table alone,
                                 so this row read a key nothing writes: the `!= null` guard was
                                 never satisfied and the row was dead in BOTH flag states.

                                 Verified rather than inherited: no saveMeta('listing_title') call
                                 anywhere in the app targets a SellerAgentAuction — the two that
                                 exist write a TenantAgentAuction and an OfferAuction. Buyer,
                                 landlord and tenant removed the identical row for the identical
                                 reason during their own conversions. --}}
                            @if (@$auction->get->working_with_agent != null)
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Current Representation Status with Broker" :value="@$auction->get->working_with_agent" />
                            @endif


                            @if (@$auction->get->desired_agent_hire_date != null)
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Desired Agent Hire Date" :value="date('F j, Y', strtotime(@$auction->get->desired_agent_hire_date))" />
                            @endif
                            @if (@$auction->get->listing_date != null)
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Listing Date" :value="date('F j, Y', strtotime(@$auction->get->listing_date))" />
                            @endif
                            @if (@$auction->get->expiration_date != null)
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Expiration Date" :value="date('F j, Y', strtotime(@$auction->get->expiration_date))" />
                            @endif
                            @if (@$auction->get->auction_type != null)
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Listing Type" :value="@$auction->get->auction_type" />
                            @endif


                            {{-- Milestone 3: the "Bidding Period Length: 14 Days" row was removed
                                 here. It is a bidding-period label describing a timer that no
                                 longer exists or governs anything. --}}
                            @if (@$auction->get->meeting_Preference != null)
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Meeting Preference" :value="@$auction->get->meeting_Preference" />
                            @endif


                        </div>
            </x-hire-agent.detail-section>
            @endif
            {{-- The rule stays with the caller: the section component emits no separator, because
                 the four roles do not spell it the same way and encoding four spellings of a
                 horizontal rule in a shared component is not an API. --}}
            @if (! $hsaDetailRedesign)  <hr>@endif
            @if (! $hsaDetailRedesign || $hsaShows('property'))
            <x-hire-agent.detail-section :redesign="$hsaDetailRedesign" id="hla-section-property" title="Property Details:" icon="fa-solid fa-house">

                        <div class="row" style="flex-wrap: wrap;">

                            @php
                                $citiesData = @$auction->get->cities;
                                $citiesArray = [];
                                if ($citiesData) {
                                    if (is_string($citiesData)) {
                                        $citiesArray = json_decode($citiesData, true) ?? [];
                                    } elseif (is_array($citiesData)) {
                                        $citiesArray = $citiesData;
                                    }
                                }
                                $citiesArray = array_filter($citiesArray);

                                $countiesData = @$auction->get->counties;
                                $countiesArray = [];
                                if ($countiesData) {
                                    if (is_string($countiesData)) {
                                        $countiesArray = json_decode($countiesData, true) ?? [];
                                    } elseif (is_array($countiesData)) {
                                        $countiesArray = $countiesData;
                                    }
                                }
                                $countiesArray = array_filter($countiesArray);

                                $stateVal = @$auction->get->state ?: @$auction->get->property_state;
                                $zipVal = @$auction->get->zip_code ?: @$auction->get->property_zip;
                                $propertyCityVal = @$auction->get->property_city;
                                $propertyCountyVal = @$auction->get->property_county;

                                $stripState = function($str) {
                                    return trim(preg_replace('/,\s*[A-Z]{2}$/', '', $str));
                                };
                            @endphp

                            {{-- S2 — THESE PASS :value AND NOT :list-value, WHICH IS NOT AN
                                 OVERSIGHT. Seller joins its city and county lists with "; " where
                                 the component's listValue joins with ", ". Handing it the array
                                 would silently re-punctuate a row that flag-off renders with
                                 semicolons, so the join stays at the call site and the component
                                 receives the finished string. Buyer's location rows read ", "
                                 because buyer's own markup always did. --}}
                            @if (!empty($citiesArray))
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="City" :value="implode('; ', array_map($stripState, $citiesArray))" />
                            @elseif (!empty($propertyCityVal))
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="City" :value="$stripState($propertyCityVal)" />
                            @endif

                            @if (!empty($countiesArray))
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="County" :value="implode('; ', array_map($stripState, $countiesArray))" />
                            @elseif (!empty($propertyCountyVal))
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="County" :value="$stripState($propertyCountyVal)" />
                            @endif

                            @if (!empty($stateVal))
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="State" :value="$stateVal" />
                            @endif

                            @if (!empty($zipVal))
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="ZIP Code" :value="$zipVal" />
                            @endif
                            @php
                                $propType = @$auction->get->property_type ?? '';
                            @endphp
                            {{-- S2 — THIS ROW WAS UNGUARDED AND IS NOW EMPTINESS-GUARDED BY THE
                                 COMPONENT. normalizePropertyType() returns '' for a listing with no
                                 property_type, so the hand-written row emitted a "Property Type:"
                                 label above an empty span; the component declines to render a row
                                 with no value, in both flag states. Landlord converted the same
                                 unguarded row the same way at its own milestone. --}}
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Property Type" :value="\App\Helpers\ListingDisplayHelper::normalizePropertyType($propType)" />
                            @php
                                $propertyStyleItems = \App\Helpers\ListingDisplayHelper::normalizeList(
                                    @$auction->get->property_items,
                                    @$auction->get->other_property_style
                                );
                            @endphp
                            @if (!empty($propertyStyleItems))
                                {{-- :value, not :list-value — this row is plain ", "-joined text in
                                     BOTH branches, and listValue is read only by the redesign one,
                                     so passing it alone would leave the legacy branch with nothing
                                     to render and hide the row. --}}
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Property Style" :value="implode(', ', $propertyStyleItems)" />
                            @endif

                            @php
                                $businessTypeValue = @$auction->get->business_type_selected ?: @$auction->get->business_type;
                                $otherBusinessType = @$auction->get->other_business_type;

                                /*
                                 | S2 — the "Other" resolution moves out of the markup and into one
                                 | expression, because the row now has two renderings and the choice
                                 | of WHICH value to show is common to both. The two-branch
                                 | @if/@elseif it replaces produced exactly these values.
                                 */
                                $businessTypeDisplay = ($businessTypeValue != 'Other')
                                    ? $businessTypeValue
                                    : ($otherBusinessType ?: '');
                            @endphp
                            @if (!empty($businessTypeValue))
                                {{-- A pill in legacy, plain text in the redesign — buyer's rule that
                                     a pill means STATE and text means DATA, and a business type is
                                     data. The pill markup is untouched and still emitted by this
                                     call site; only the redesign branch reads $businessTypeDisplay
                                     as text. --}}
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Business Type" :bare-slot="true" :list-value="$businessTypeDisplay">
                                    @if ($businessTypeDisplay !== '')
                                        <span class="removeBold badge bg-secondary">{{ $businessTypeDisplay }}</span>
                                    @endif
                                </x-hire-agent.field>
                            @endif

                            @php
                                $condRawBuyer = @$auction->get->condition_prop_buyer;
                                if (is_string($condRawBuyer)) {
                                    $condRawBuyerDecoded = json_decode(str_replace('&quot;', '"', $condRawBuyer), true);
                                    if (empty($condRawBuyerDecoded) || (is_array($condRawBuyerDecoded) && count($condRawBuyerDecoded) === 0)) {
                                        $condRawBuyer = null;
                                    }
                                }
                                $condRaw = !empty($condRawBuyer) ? $condRawBuyer : (@$auction->get->condition_prop ?? null);
                                $condOther = @$auction->get->other_property_condition;
                                $conditionItems = [];
                                if (!empty($condRaw)) {
                                    $decoded = is_string($condRaw) ? json_decode(str_replace('"', '"', $condRaw), true) : (array) $condRaw;
                                    if (is_array($decoded)) {
                                        foreach ($decoded as $v) {
                                            $v = is_string($v) ? trim(str_replace('"', '', $v)) : $v;
                                            if ($v !== '' && $v !== null) {
                                                if (strtolower($v) === 'other' && !empty($condOther)) {
                                                    $conditionItems[] = trim($condOther);
                                                } else {
                                                    $conditionItems[] = $v;
                                                }
                                            }
                                        }
                                    } else {
                                        $v = trim(str_replace('"', '', $condRaw));
                                        if ($v !== '') {
                                            $conditionItems[] = $v;
                                        }
                                    }
                                }
                            @endphp
                            @if (!empty($conditionItems) && $propType !== 'Vacant Land')
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Property Condition" :value="implode(', ', $conditionItems)" />
                            @endif

                            {{-- Bedrooms and Bathrooms both resolve an "Other" answer to its custom
                                 value. The two-branch @if that did it inline becomes one ternary per
                                 row: same two outcomes, and the property-type guards around them are
                                 untouched — Bedrooms is Residential only, Bathrooms adds Commercial
                                 and Business. --}}
                            @if (in_array($propType, ['Residential']))
                            @if (@$auction->get->bedrooms != null)
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Bedrooms"
                                    :value="@$auction->get->bedrooms != 'Other' ? @$auction->get->bedrooms : @$auction->get->other_bedrooms" />
                            @endif
                            @endif

                            @if (in_array($propType, ['Residential', 'Commercial', 'Business']))
                            @if (@$auction->get->bathrooms != null)
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Bathrooms"
                                    :value="@$auction->get->bathrooms != 'Other' ? @$auction->get->bathrooms : @$auction->get->other_bathrooms" />
                            @endif
                            @endif

                            @php
                                /*
                                 | S2 — the square-footage formatter, written once.
                                 |
                                 | The three property-type branches below each repeated this
                                 | expression twice: strip thousands separators, and either
                                 | re-format the number or hand back the original string when it is
                                 | not numeric. Six copies became one closure; the RULE is unchanged
                                 | and each call still receives exactly the value its branch passed.
                                 |
                                 | DEFINED OUT HERE, not inside the first branch that uses it — the
                                 | three trios are separate @if blocks, so a closure declared inside
                                 | the Residential one would be undefined for a Commercial or Income
                                 | listing, which is every listing the first branch does not match.
                                 */
                                $hsaSqft = function ($v) {
                                    $clean = str_replace(',', '', (string) $v);
                                    return is_numeric($clean) ? number_format((float) $clean, 0) : $v;
                                };
                            @endphp
                            @if ($propType === 'Residential')
                                {{-- ── THE THREE SQFT TRIOS ARE NOT DUPLICATES. DO NOT MERGE THEM.
                                     Residential, Commercial|Business and Income each render the same
                                     three meta keys, and the branches are mutually exclusive, so
                                     they look like copy-paste. Their LABELS DIFFER, and the
                                     differences are load-bearing because flag-off text is asserted:
                                     Residential says "Sqft Heated Source" where the other two say
                                     "SqFt Heated Source", and Income says "Heated SqFt" where the
                                     other two say "Heated Sqft". Collapsing them into one block
                                     would silently re-caption whichever branches lost. ── --}}
                                @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null' && @$auction->get->minimum_heated_square != '')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Heated Sqft" :value="$hsaSqft(@$auction->get->minimum_heated_square)" />
                                @endif
                                @php $totalSqFt = @$auction->get->total_square_feet; @endphp
                                @if (!empty($totalSqFt) && $totalSqFt != 'null')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Total Sqft" :value="$hsaSqft($totalSqFt)" />
                                @endif
                                @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != '' && @$auction->get->sqft_heated_source != 'null')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Sqft Heated Source" :value="@$auction->get->sqft_heated_source" />
                                @endif
                            @endif

                            @if (in_array($propType, ['Commercial', 'Business']))
                                @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null' && @$auction->get->minimum_heated_square != '')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Heated Sqft" :value="$hsaSqft(@$auction->get->minimum_heated_square)" />
                                @endif
                                @php $totalSqFtCom = @$auction->get->total_square_feet; @endphp
                                @if (!empty($totalSqFtCom) && $totalSqFtCom != 'null')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Total Sqft" :value="$hsaSqft($totalSqFtCom)" />
                                @endif
                                @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != '' && @$auction->get->sqft_heated_source != 'null')
                                    {{-- "SqFt", not "Sqft" — see the note above. --}}
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="SqFt Heated Source" :value="@$auction->get->sqft_heated_source" />
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential']))
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->carportOptions))
                                    @php
                                        $carportVal = @$auction->get->carportOptions;
                                        $carportSpaces = @$auction->get->custom_carport;
                                        if ($carportVal === 'Yes' && \App\Helpers\ListingDisplayHelper::hasValue($carportSpaces)) {
                                            $carportDisplay = 'Yes (' . $carportSpaces . ' Spaces)';
                                        } else {
                                            $carportDisplay = $carportVal;
                                        }
                                    @endphp
                                    {{-- The FIRST of two Carport rows on this page, and the two are
                                         not duplicates: this one reads `carportOptions` /
                                         `custom_carport`, the one further down reads
                                         `carport_needed` / `other_carport_needed` through
                                         formatYesCount(). Both are Residential-gated and both can
                                         render on the same listing. Same for Garage. --}}
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Carport" :value="$carportDisplay" />
                                @endif
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->garageOptions))
                                    @php
                                        $garageVal = @$auction->get->garageOptions;
                                        $garageSpaces = @$auction->get->custom_garage;
                                        if (in_array($garageVal, ['Yes', 'Optional']) && \App\Helpers\ListingDisplayHelper::hasValue($garageSpaces)) {
                                            $garageDisplay = $garageVal . ' (' . $garageSpaces . ' Spaces)';
                                        } else {
                                            $garageDisplay = $garageVal;
                                        }
                                    @endphp
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Garage" :value="$garageDisplay" />
                                @endif
                            @endif

                            @if ($propType === 'Income')
                                @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null' && @$auction->get->minimum_heated_square != '')
                                    {{-- "Heated SqFt", not "Heated Sqft" — the Income branch
                                         capitalises this one differently from the two above it. --}}
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Heated SqFt" :value="$hsaSqft(@$auction->get->minimum_heated_square)" />
                                @endif
                                @php $totalSqFtInc = @$auction->get->total_square_feet; @endphp
                                @if (!empty($totalSqFtInc) && $totalSqFtInc != 'null')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Total Sqft" :value="$hsaSqft($totalSqFtInc)" />
                                @endif
                                @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != '' && @$auction->get->sqft_heated_source != 'null')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="SqFt Heated Source" :value="@$auction->get->sqft_heated_source" />
                                @endif
                            @endif

                            @if (@$auction->get->total_acreage != null && @$auction->get->total_acreage != '' && @$auction->get->total_acreage != 'null')
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Total Acreage" :value="@$auction->get->total_acreage" />
                            @endif

                            @php
                                $appRaw = @$auction->get->appliances;
                                $appOther = @$auction->get->other_appliances;
                                $applianceItems = [];
                                if (!empty($appRaw)) {
                                    $decoded = is_string($appRaw) ? json_decode(str_replace('"', '"', $appRaw), true) : (array) $appRaw;
                                    if (is_array($decoded)) {
                                        foreach ($decoded as $v) {
                                            $v = is_string($v) ? trim(str_replace('"', '', $v)) : $v;
                                            if ($v !== '' && $v !== null) {
                                                if (strtolower($v) === 'other' && !empty($appOther)) {
                                                    $applianceItems[] = trim($appOther);
                                                } else {
                                                    $applianceItems[] = $v;
                                                }
                                            }
                                        }
                                    } else {
                                        $v = trim(str_replace('"', '', $appRaw));
                                        if ($v !== '') {
                                            $applianceItems[] = $v;
                                        }
                                    }
                                }
                            @endphp
                            @if (!empty($applianceItems) && in_array($propType, ['Residential', 'Income', 'Commercial', 'Business']))
                                {{-- PILLS IN LEGACY, ", "-JOINED TEXT IN THE REDESIGN — buyer's rule
                                     that a pill means STATE and plain text means DATA, and an
                                     appliance list is data. The caller passes BOTH: the slot, which
                                     only the legacy branch reads and which keeps its
                                     one-item-plain / many-items-pills shape verbatim, and
                                     $applianceItems as listValue, which only the redesign branch
                                     reads. Neither branch has to know what the other does. --}}
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Appliances Included" :bare-slot="true" :list-value="$applianceItems">
                                    @if (count($applianceItems) === 1)
                                        <span class="removeBold">{{ $applianceItems[0] }}</span>
                                    @else
                                        @foreach ($applianceItems as $appItem)
                                            <span class="removeBold badge bg-secondary">{{ $appItem }}</span>
                                        @endforeach
                                    @endif
                                </x-hire-agent.field>
                            @endif

                            @if ($propType === 'Income' && @$auction->get->pool_needed !== null && @$auction->get->pool_needed !== '' && @$auction->get->pool_needed !== 'null')
                                @include('hire_seller_agent.partials.pool-display', ['auction' => $auction, 'redesign' => $hsaDetailRedesign])
                            @endif

                            @if (in_array($propType, ['Commercial', 'Business', 'Income']))
                                @if (@$auction->get->minimum_net_leasable_square != null && @$auction->get->minimum_net_leasable_square != 'null' && @$auction->get->minimum_net_leasable_square != '')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Net Leasable Square Footage" :value="$hsaSqft(@$auction->get->minimum_net_leasable_square)" />
                                @endif
                            @endif

                            @if (in_array($propType, ['Commercial', 'Business']))
                                @php
                                    $parkingItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->garage_parking_spaces_option, @$auction->get->other_parking_space_wrapper);
                                @endphp
                                @if (!empty($parkingItems))
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Garage/Parking Features" :bare-slot="true" :list-value="$parkingItems">
                                        @foreach ($parkingItems as $feature)
                                            <span class="removeBold badge bg-secondary">{{ $feature }}</span>
                                        @endforeach
                                    </x-hire-agent.field>
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential']))
                                {{-- The SECOND Carport/Garage pair. Different meta keys from the one
                                     above and formatted by formatYesCount() rather than inline; both
                                     pairs are Residential-gated and both can render together. --}}
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->carport_needed))
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Carport" :value="\App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->carport_needed, @$auction->get->other_carport_needed, 'Spaces')" />
                                @endif
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->garage_needed))
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Garage" :value="\App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->garage_needed, @$auction->get->other_garage_needed, 'Spaces')" />
                                @endif
                            @endif

                            @if ($propType === 'Residential' && @$auction->get->pool_needed !== null && @$auction->get->pool_needed !== '' && @$auction->get->pool_needed !== 'null')
                                @include('hire_seller_agent.partials.pool-display', ['auction' => $auction, 'redesign' => $hsaDetailRedesign])
                            @endif

                            @php
                                $viewPrefItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->view_preference, @$auction->get->other_preferences);
                            @endphp
                            @if (!empty($viewPrefItems))
                                <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="View" :bare-slot="true" :list-value="$viewPrefItems">
                                    @foreach ($viewPrefItems as $item)
                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                    @endforeach
                                </x-hire-agent.field>
                            @endif

                            @if (in_array($propType, ['Residential']))
                                @if (@$auction->get->leasing_55_plus != null && @$auction->get->leasing_55_plus != '')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Age-Restricted Community" :value="@$auction->get->leasing_55_plus" />
                                @endif
                            @endif

                            @if (!in_array($propType, ['Vacant Land']))
                                @php
                                    $amenityItems = \App\Helpers\ListingDisplayHelper::normalizeList(@$auction->get->non_negotiable_amenities, @$auction->get->other_non_negotiable_amenities);
                                @endphp
                                @if (!empty($amenityItems))
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Amenities and Property Features" :bare-slot="true" :list-value="$amenityItems">
                                        @foreach ($amenityItems as $item)
                                            <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                        @endforeach
                                    </x-hire-agent.field>
                                @endif
                            @endif

                            @if (in_array($propType, ['Residential', 'Income']))
                                @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->pets))
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Pets Allowed" :value="\App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->pets, @$auction->get->number_of_pets)" />
                                @endif

                                {{-- isParentYes(), not hasValue() — the three rows below describe
                                     WHICH pets are allowed and must stay behind a "Pets Allowed =
                                     Yes" answer rather than merely a present one. --}}
                                @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->pets))
                                    @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->type_of_pets))
                                        <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Acceptable Pet Types" :value="@$auction->get->type_of_pets" />
                                    @endif

                                    @if (\App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->weight_of_pets))
                                        <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Maximum Weight Per Pet (lbs)" :value="@$auction->get->weight_of_pets . ' lbs'" />
                                    @endif

                                    @php
                                        $petRestrictVal = @$auction->get->breed_of_pets ?: @$auction->get->breed_restrictions ?: @$auction->get->has_breed_restrictions;
                                    @endphp
                                    @if (\App\Helpers\ListingDisplayHelper::hasValue($petRestrictVal))
                                        <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Pet Restrictions" :value="$petRestrictVal" />
                                    @endif
                                @endif
                            @endif


                            @if (in_array($propType, ['Commercial', 'Business', 'Income']))
                                @php
                                    $rawAssetsView = @$auction->get->assets;
                                    if (empty($rawAssetsView) || $rawAssetsView === '[]' || $rawAssetsView === 'null') {
                                        $rawAssetsView = @$auction->get->business_assets;
                                    }
                                    $assetItems = \App\Helpers\ListingDisplayHelper::normalizeList($rawAssetsView, @$auction->get->assets_other);
                                @endphp
                                @if (!empty($assetItems))
                                {{-- S1 — A SUB-HEADING, NOT A SECTION, and that is recorded rather
                                     than chosen here: the section registry's config names
                                     "Business/Property Assets" and "Income & Investment Metrics"
                                     among the things that fold into Property Details rather than
                                     becoming sections of their own, the same way buyer keeps
                                     "Required Property or Business Assets" inside its property
                                     card. They are divisions within one subject. (Described rather
                                     than named — see the note in the prologue.)

                                     The heading is suppressed under the flag for the reason buyer
                                     recorded for its counterpart: x-viho.section-header always
                                     emits card-title typography, so inside a card it is
                                     indistinguishable from the card's own title. Landlord's
                                     redesigned cards contain a title and fields and no
                                     intermediate heading, and there is nothing to shrink this to
                                     that landlord also has. The legacy branch keeps it exactly
                                     where and as it was — it is load-bearing there, because there
                                     is no card title above it to make it redundant.

                                     The row break around it is legacy-only for the same reason:
                                     with the flag on the cells sit directly in the section's
                                     field grid, which is what supplies the layout. --}}
                                @if (! $hsaDetailRedesign)
                                </div>
                                <hr>
                                <x-viho.section-header title="Business/Property Assets" tag="h4" />
                                <div class="row" style="flex-wrap: wrap;">
                                @endif
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Included Property or Business Assets" :bare-slot="true" :list-value="$assetItems">
                                        @foreach ($assetItems as $asset)
                                            <span class="removeBold badge bg-secondary">{{ $asset }}</span>
                                        @endforeach
                                    </x-hire-agent.field>
                                @endif
                            @endif
                            @if ($propType === 'Business')
                                @php
                                    $realEstatePurchase = @$auction->get->real_estate_purchase;
                                @endphp
                                @if (!empty($realEstatePurchase) && $realEstatePurchase != 'null')
                                    {{-- Literal & in the label: Blade escapes it back to &amp; on
                                         output, so the rendered text is unchanged. --}}
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Business & Real Estate Purchase Requirements" :value="$realEstatePurchase" />
                                @endif
                            @endif


                            @if (in_array($propType, ['Commercial', 'Business', 'Income']))
                                @php
                                    $hasNOI = \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->minimum_annual_net_income);
                                    $hasCapRate = \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->minimum_cap_rate);
                                @endphp
                                @if ($hasNOI || $hasCapRate)
                                {{-- S1 — a sub-heading, not a section. See the note on
                                     "Business/Property Assets" above; the same registry decision
                                     and the same legacy-only row break apply here unchanged. --}}
                                @if (! $hsaDetailRedesign)
                                </div>
                                <hr>
                                <x-viho.section-header title="Income & Investment Metrics" tag="h4" />
                                <div class="row" style="flex-wrap: wrap;">
                                @endif
                                    @if ($hasNOI)
                                        <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Annual Net Income" :value="\App\Helpers\ListingDisplayHelper::fmtMoney(@$auction->get->minimum_annual_net_income)" />
                                    @endif

                                    @if ($hasCapRate)
                                        <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Cap Rate" :value="\App\Helpers\ListingDisplayHelper::fmtPercent(@$auction->get->minimum_cap_rate)" />
                                    @endif
                                @endif
                            @endif

                            @if ($propType === 'Income')
                                @php
                                    $unitNumber = @$auction->get->unit_number;
                                @endphp
                                @if (!empty($unitNumber) && $unitNumber != 'null')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Total Number of Units" :value="$unitNumber" />
                                @endif

                                @php
                                    $unitBuildings = @$auction->get->unit_buildings;
                                @endphp
                                @if (!empty($unitBuildings) && $unitBuildings != 'null')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Total Number of Buildings" :value="$unitBuildings" />
                                @endif

                                @php
                                    $unitConfigs = @$auction->get->unit_type_configurations;
                                    $unitConfigList = [];
                                    if ($unitConfigs) {
                                        $unitConfigList = is_string($unitConfigs) ? (json_decode($unitConfigs, true) ?? []) : (array)$unitConfigs;
                                    }
                                    $unitConfigList = array_values(array_filter($unitConfigList ?? [], function($unit) {
                                        return !empty($unit['unit_type']) || !empty($unit['beds_unit']) || !empty($unit['baths_unit'])
                                            || !empty($unit['number_of_units']) || !empty($unit['expected_rent']) || !empty($unit['unit_type_description']);
                                    }));
                                @endphp
                                @if (!empty($unitConfigList))
                                    <div class="col-12 pt-3">
                                        <h5 class="fw-bold">Unit Type Configuration</h5>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Unit Type</th>
                                                        <th>Beds</th>
                                                        <th>Baths</th>
                                                        <th>Garage</th>
                                                        <th>Carport</th>
                                                        <th>Other Spaces</th>
                                                        <th># Units</th>
                                                        <th># Occupied</th>
                                                        <th>Expected Rent</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($unitConfigList as $unit)
                                                        <tr>
                                                            <td>{{ @$unit['unit_type'] ?? '' }}</td>
                                                            <td>{{ @$unit['beds_unit'] ?? '' }}</td>
                                                            <td>{{ @$unit['baths_unit'] ?? '' }}</td>
                                                            <td>{{ @$unit['garage_spaces'] ?? '' }}</td>
                                                            <td>{{ @$unit['carport_spaces'] ?? '' }}</td>
                                                            <td>{{ @$unit['other_spaces'] ?? '' }}</td>
                                                            <td>{{ @$unit['number_of_units'] ?? '' }}</td>
                                                            <td>{{ @$unit['number_occupied'] ?? '' }}</td>
                                                            <td>
                                                                @if (!empty($unit['expected_rent']))
                                                                    ${{ number_format((float) str_replace(',', '', $unit['expected_rent']), 2) }}
                                                                @endif
                                                            </td>
                                                            <td>{{ @$unit['unit_type_description'] ?? '' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            @endif

                        </div>
            </x-hire-agent.detail-section>
            @endif
            @if (! $hsaDetailRedesign)<hr>@endif
            @if (! $hsaDetailRedesign || $hsaShows('terms'))
            <x-hire-agent.detail-section :redesign="$hsaDetailRedesign" id="hla-section-terms" title="Sale Terms" icon="fa-solid fa-file-contract">

                        @php
                            $spRaw = @$auction->get->sale_provision;
                            $spOther = @$auction->get->sale_provision_other;
                            $saleProvisionItems = [];
                            if (!empty($spRaw)) {
                                $decoded = is_string($spRaw) ? json_decode(str_replace('"', '"', $spRaw), true) : (array) $spRaw;
                                if (is_array($decoded)) {
                                    foreach ($decoded as $v) {
                                        $v = is_string($v) ? trim(str_replace('"', '', $v)) : $v;
                                        if ($v !== '' && $v !== null) {
                                            if (strtolower($v) === 'other' && !empty($spOther)) {
                                                $saleProvisionItems[] = trim($spOther);
                                            } else {
                                                $saleProvisionItems[] = $v;
                                            }
                                        }
                                    }
                                } else {
                                    $v = trim(str_replace('"', '', $spRaw));
                                    if ($v !== '') {
                                        $saleProvisionItems[] = $v;
                                    }
                                }
                            }
                        @endphp
                        @if (!empty($saleProvisionItems))
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Special Sale Provision" :bare-slot="true" :list-value="$saleProvisionItems">
                                @if (count($saleProvisionItems) === 1)
                                    <span class="removeBold">{{ $saleProvisionItems[0] }}</span>
                                @else
                                    @foreach ($saleProvisionItems as $spItem)
                                        <span class="removeBold badge bg-secondary">{{ $spItem }}</span>
                                    @endforeach
                                @endif
                            </x-hire-agent.field>
                        @endif

                        {{-- ── THE INVERTED ROWS ──────────────────────────────────────────────
                             Seven of Seller's eight `legacyInverted` rows are in this section; the
                             eighth is "Seller's Current Status" in Seller Info. They are written
                             with the row div carrying `removeBold` and the LABEL in a `fw-bold`
                             span, which is the opposite of every other row on the page and which
                             the shared component could not emit until S2 added the branch. Flag-off
                             markup is reproduced exactly; with the redesign on they converge with
                             every other row, because both shapes reach the same kv cell. --}}
                        @if (@$auction->get->sale_provision_assignment != null && @$auction->get->sale_provision_assignment != '')
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" :legacy-inverted="true" label="Assignment Contract" :value="@$auction->get->sale_provision_assignment" />
                            @if (strtolower(@$auction->get->sale_provision_assignment) === 'yes')
                                @if (@$auction->get->buyer_sell_contract != null && @$auction->get->buyer_sell_contract != '')
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" :legacy-inverted="true" span="full" label="Seller Under Contract for Assignment" :value="@$auction->get->buyer_sell_contract" />
                                @endif
                                @if (@$auction->get->assignment_fee_amount != null && @$auction->get->assignment_fee_amount != '')
                                    @php
                                        $assignFeeType = @$auction->get->assignment_fee_type ?? '$';
                                        $assignFeeAmt = @$auction->get->assignment_fee_amount;
                                        /*
                                         | The %-vs-$ choice, unchanged. `assignment_fee_type` holds
                                         | either a literal '%' or the word 'percent'; anything else
                                         | (including the '$' default) formats as money.
                                         */
                                        $assignFeeDisplay = ($assignFeeType === '%' || $assignFeeType === 'percent')
                                            ? $assignFeeAmt . '%'
                                            : $fmtMoney($assignFeeAmt);
                                    @endphp
                                    <x-hire-agent.field :redesign="$hsaDetailRedesign" :legacy-inverted="true" span="full" label="Assignment Contract Fee to Broker" :value="$assignFeeDisplay" />
                                @endif
                            @endif
                        @endif

                        @if (@$auction->get->target_closing_date != null)
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" :legacy-inverted="true" label="Target Closing Date" :value="@$auction->get->target_closing_date" />
                        @endif
                        @if (@$auction->get->occupant_status != null)
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" :legacy-inverted="true" label="Occupant Type" :value="@$auction->get->occupant_status" />
                        @endif
                        @if (@$auction->get->occupant_tenant != '' && @$auction->get->occupant_tenant != 'null')
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" :legacy-inverted="true" label="Occupied Until" :value="\Carbon\Carbon::parse($auction->get->occupant_tenant)->format('F j, Y')" />
                        @endif
                        @if (@$auction->get->maximum_budget != null)
                            @php
                                /*
                                 | S2 — the sale price, formatted exactly as before: strip thousands
                                 | separators, then either re-format as currency or prefix the raw
                                 | answer with a dollar sign when it is not numeric. The fallback
                                 | branch keeps its '$' prefix, which is what the page has always
                                 | shown for a non-numeric price.
                                 |
                                 | $fmtMoney is NOT used here — it returns null for a non-numeric
                                 | value, which would hide the row instead of showing the answer.
                                 */
                                $salePriceRaw = str_replace(',', '', @$auction->get->maximum_budget);
                                $salePriceDisplay = is_numeric($salePriceRaw)
                                    ? '$' . number_format((float) $salePriceRaw, 0)
                                    : '$' . @$auction->get->maximum_budget;
                            @endphp
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" :legacy-inverted="true" label="Desired Sale Price" :value="$salePriceDisplay" />
                        @endif

                        @php
                            /*
                             | S1 — the raw list and the resolved pills are hoisted to the prologue,
                             | where the Financing section guard reads them. Only the display ORDER
                             | is applied here: sorting is presentation and belongs beside the
                             | markup, while "is there anything to show at all" is what the nav
                             | needs before any of this runs. One derivation, two uses — re-deriving
                             | it here is how a nav entry comes to point at a card that decided not
                             | to render.
                             |
                             | $financingArray keeps its name because the config-driven term loop
                             | below asks in_array() of it.
                             */
                            $financingArray = $hsaFinancingArray;
                            $financingPills = $hsaFinancingPills;
                            $financingDisplayOrder = ['Assumable','Cash','Conventional','Cryptocurrency','Exchange/Trade','FHA','Jumbo','Lease Option','Lease Purchase','No-Doc','Non-QM','NFT','Non-Fungible Token (NFT)','Seller Financing','USDA','VA'];
                            usort($financingPills, function($a, $b) use ($financingDisplayOrder) {
                                $aIdx = array_search($a, $financingDisplayOrder);
                                $bIdx = array_search($b, $financingDisplayOrder);
                                if ($aIdx === false && strtolower($a) === 'other') return 1;
                                if ($bIdx === false && strtolower($b) === 'other') return -1;
                                if ($aIdx === false) $aIdx = 999;
                                if ($bIdx === false) $bIdx = 999;
                                return $aIdx - $bIdx;
                            });
                        @endphp
            </x-hire-agent.detail-section>
            @endif
            {{-- Financing was already guarded on its own content, so unlike the sections above it
                 needs no `! $hsaDetailRedesign ||` half: with the flag off the pill list decides, and
                 with it on $hsaShows('financing') decides — and that reads the SAME pill list one
                 level up, so the card and its nav entry cannot disagree. --}}
            @if ($hsaDetailRedesign ? $hsaShows('financing') : !empty($financingPills))
            @if (! $hsaDetailRedesign)<hr>@endif
            <x-hire-agent.detail-section :redesign="$hsaDetailRedesign" id="hla-section-financing" title="Financing Details" icon="fa-solid fa-money-check-dollar">
                            <div class="row">

                            <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Offered Financing/Currency" :bare-slot="true" :list-value="$financingPills">
                                @if (count($financingPills) === 1)
                                    <span class="removeBold">{{ $financingPills[0] }}</span>
                                @else
                                    @foreach ($financingPills as $fp)
                                        <span class="removeBold badge bg-secondary">{{ $fp }}</span>
                                    @endforeach
                                @endif
                            </x-hire-agent.field>

                            @php
                                $financingConfig = config('seller-financing-config.sections');
                                $termOrder = config('seller-financing-config.term_order');
                                $fmtMoneyVal = function($val) {
                                    return '$' . number_format((float)str_replace(',', '', $val));
                                };
                                $getVal = function($key) use ($auction) {
                                    return @$auction->get->$key;
                                };
                            @endphp

                            @foreach ($termOrder as $termName)
                                @if (in_array($termName, $financingArray) && isset($financingConfig[$termName]))
                                    @php
                                        $section = $financingConfig[$termName];
                                        $fields = $section['fields'];
                                        $hasAnyData = false;
                                        foreach ($fields as $field) {
                                            $val = $getVal($field['key']);
                                            if (\App\Helpers\ListingDisplayHelper::hasValue($val)) { $hasAnyData = true; break; }
                                            if (isset($field['alt_keys'])) {
                                                foreach ($field['alt_keys'] as $altKey) {
                                                    if (\App\Helpers\ListingDisplayHelper::hasValue($getVal($altKey))) { $hasAnyData = true; break 2; }
                                                }
                                            }
                                        }
                                    @endphp
                                    @if ($hasAnyData)
                                        <div class="col-12 mt-3 mb-1">
                                            <h6 class="financing-subsection-header">{{ $section['header'] }}</h6>
                                        </div>
                                        @foreach ($fields as $field)
                                            @php
                                                $fieldVal = $getVal($field['key']);
                                                if (empty($fieldVal) && isset($field['alt_keys'])) {
                                                    foreach ($field['alt_keys'] as $altKey) {
                                                        $altVal = $getVal($altKey);
                                                        if (!empty($altVal)) { $fieldVal = $altVal; break; }
                                                    }
                                                }
                                                $showField = \App\Helpers\ListingDisplayHelper::hasValue($fieldVal);
                                                if ($showField && isset($field['show_when'])) {
                                                    $condKey = $field['show_when']['key'];
                                                    $condVal = $field['show_when']['value'];
                                                    $condActual = $getVal($condKey);
                                                    if ($condVal === null) {
                                                        $showField = empty($condActual);
                                                    } elseif (is_array($condVal)) {
                                                        $showField = in_array($condActual, $condVal);
                                                    } else {
                                                        $showField = ($condActual === $condVal);
                                                    }
                                                }
                                            @endphp
                                            @if ($showField)
                                                {{-- S2 — THE CONFIG-DRIVEN FINANCING ROW, CONVERTED
                                                     ONCE RATHER THAN TEN TIMES.

                                                     This one call site renders every field in
                                                     config/seller-financing-config.php, and the
                                                     @switch below turns its `format` key into
                                                     markup — ten formats, several of which emit
                                                     more than one element (a badge plus a
                                                     parenthetical, a pill run) or choose between
                                                     money and percent from a second meta key.

                                                     It uses bareSlot for exactly that reason: the
                                                     slot is emitted unwrapped, so the switch keeps
                                                     producing the elements it always has and the
                                                     legacy row is reproduced verbatim. Passing
                                                     :value instead would mean re-deriving all ten
                                                     formats as plain strings — a second
                                                     implementation of a rule that already has one,
                                                     and the config would then be read twice.

                                                     The label comes from the config and carries no
                                                     colon there, which is what this component
                                                     wants; the legacy branch adds it exactly where
                                                     the hand-written row had it. --}}
                                                <x-hire-agent.field :redesign="$hsaDetailRedesign" :label="$field['label']" :bare-slot="true">
                                                    @switch($field['format'])
                                                        @case('text')
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @break
                                                        @case('text_with_suffix')
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}{{ $field['suffix'] ?? '' }}</span>
                                                            @break
                                                        @case('money')
                                                            <span class="removeBold">{!! $fmtMoneyVal($fieldVal) !!}</span>
                                                            @break
                                                        @case('percent')
                                                            <span class="removeBold">{{ $fieldVal }}%</span>
                                                            @break
                                                        @case('badge')
                                                            <span class="removeBold badge bg-secondary">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @break
                                                        @case('money_or_percent')
                                                            @php
                                                                $typeKey = $field['type_key'] ?? '';
                                                                $typeVal = $getVal($typeKey);
                                                                $percentVal = $field['type_percent_value'] ?? '%';
                                                            @endphp
                                                            @if ($typeVal === $percentVal)
                                                                <span class="removeBold">{{ $fieldVal }}%</span>
                                                            @else
                                                                <span class="removeBold">{!! $fmtMoneyVal($fieldVal) !!}</span>
                                                            @endif
                                                            @break
                                                        @case('badge_or_other')
                                                            @php
                                                                $otherVal = $field['other_value'] ?? 'Other';
                                                                $otherKey = $field['other_key'] ?? '';
                                                                $otherText = $getVal($otherKey);
                                                                $isMultiBadge = !empty($field['multi']);
                                                                $badgeItems = [];
                                                                if ($isMultiBadge) {
                                                                    $rawB = $fieldVal;
                                                                    if (is_string($rawB)) {
                                                                        $decodedB = json_decode(str_replace('&quot;', '"', $rawB), true);
                                                                        $badgeItems = is_array($decodedB) ? $decodedB : ($rawB !== '' ? [$rawB] : []);
                                                                    } else {
                                                                        $badgeItems = is_array($rawB) ? $rawB : [];
                                                                    }
                                                                }
                                                            @endphp
                                                            @if ($isMultiBadge)
                                                                @foreach ($badgeItems as $bItem)
                                                                    @php $bItem = trim(str_replace('"', '', (string) $bItem)); @endphp
                                                                    @if ($bItem === $otherVal && !empty($otherText))
                                                                        <span class="removeBold badge bg-secondary">{{ trim($otherText) }}</span>
                                                                    @elseif ($bItem !== '')
                                                                        <span class="removeBold badge bg-secondary">{{ $bItem }}</span>
                                                                    @endif
                                                                @endforeach
                                                            @elseif ($fieldVal === $otherVal && !empty($otherText))
                                                                <span class="removeBold badge bg-secondary">{{ $otherText }}</span>
                                                            @else
                                                                <span class="removeBold badge bg-secondary">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @endif
                                                            @break
                                                        @case('text_or_other')
                                                            @php
                                                                $otherVal = $field['other_value'] ?? 'Other';
                                                                $otherKey = $field['other_key'] ?? '';
                                                                $otherText = $getVal($otherKey);
                                                                $isMulti = !empty($field['multi']);
                                                                $multiItems = [];
                                                                if ($isMulti) {
                                                                    $raw = $fieldVal;
                                                                    if (is_string($raw)) {
                                                                        $decoded = json_decode(str_replace('&quot;', '"', $raw), true);
                                                                        $multiItems = is_array($decoded) ? $decoded : ($raw !== '' ? [$raw] : []);
                                                                    } else {
                                                                        $multiItems = is_array($raw) ? $raw : [];
                                                                    }
                                                                    $displayItems = [];
                                                                    foreach ($multiItems as $mi) {
                                                                        $mi = trim(str_replace('"', '', (string) $mi));
                                                                        if ($mi === $otherVal && !empty($otherText)) {
                                                                            $displayItems[] = trim($otherText);
                                                                        } elseif ($mi !== '') {
                                                                            $displayItems[] = $mi;
                                                                        }
                                                                    }
                                                                }
                                                            @endphp
                                                            @if ($isMulti)
                                                                <span class="removeBold">{{ implode(', ', $displayItems) }}</span>
                                                            @elseif ($fieldVal === $otherVal && !empty($otherText))
                                                                <span class="removeBold">{{ $otherText }}</span>
                                                            @else
                                                                <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @endif
                                                            @break
                                                        @case('badge_with_details')
                                                            <span class="removeBold badge bg-secondary">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @php
                                                                $detailTrigger = $field['detail_trigger'] ?? 'Yes';
                                                                $detailKey = $field['detail_key'] ?? '';
                                                                $detailVal = $getVal($detailKey);
                                                                $triggers = is_array($detailTrigger) ? $detailTrigger : [$detailTrigger];
                                                            @endphp
                                                            @if (in_array($fieldVal, $triggers) && !empty($detailVal))
                                                                @if (isset($field['detail_format']) && $field['detail_format'] === 'money')
                                                                    <span class="removeBold">({!! $fmtMoneyVal($detailVal) !!})</span>
                                                                @else
                                                                    <span class="removeBold">({{ $detailVal }})</span>
                                                                @endif
                                                            @endif
                                                            @break
                                                        @case('text_with_details')
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                            @php
                                                                $detailTrigger = $field['detail_trigger'] ?? 'Yes';
                                                                $detailKey = $field['detail_key'] ?? '';
                                                                $detailVal = $getVal($detailKey);
                                                                $triggers = is_array($detailTrigger) ? $detailTrigger : [$detailTrigger];
                                                            @endphp
                                                            @if (in_array($fieldVal, $triggers) && !empty($detailVal))
                                                                @if (isset($field['detail_format']) && $field['detail_format'] === 'money')
                                                                    <span class="removeBold">({!! $fmtMoneyVal($detailVal) !!})</span>
                                                                @else
                                                                    <span class="removeBold">({{ $detailVal }})</span>
                                                                @endif
                                                            @endif
                                                            @break
                                                        @case('yes_parenthetical')
                                                            @php
                                                                $amtKey = $field['amount_key'] ?? '';
                                                                $amtVal = $getVal($amtKey);
                                                                $cleanVal = str_replace('"', '', $fieldVal);
                                                            @endphp
                                                            @if (strtolower($cleanVal) === 'yes' && !empty($amtVal))
                                                                <span class="removeBold">Yes ({!! $fmtMoneyVal($amtVal) !!})</span>
                                                            @else
                                                                <span class="removeBold">{{ $cleanVal }}</span>
                                                            @endif
                                                            @break
                                                        @default
                                                            <span class="removeBold">{{ str_replace('"', '', $fieldVal) }}</span>
                                                    @endswitch
                                                </x-hire-agent.field>
                                            @endif
                                        @endforeach
                                    @endif
                                @endif
                            @endforeach
                            </div>
            </x-hire-agent.detail-section>
            @endif


            {{-- Unconditional, and it was before this change too — the separator sits OUTSIDE the
                 Additional Details guard, so a listing that answered neither Financing nor
                 Additional Details still emits it. Reproduced rather than tidied. --}}
            @if (! $hsaDetailRedesign)<hr>@endif
            @if ($hsaDetailRedesign ? $hsaShows('additional-details') : \App\Helpers\ListingDisplayHelper::hasValue(@$auction->get->additional_details))
            <x-hire-agent.detail-section :redesign="$hsaDetailRedesign" id="hla-section-additional-details" title="Additional Details:" icon="fa-solid fa-circle-info">

                            {{-- The label repeats the section heading, and both are kept: with the
                                 flag off the heading and the row have always both read "Additional
                                 Details:", and flag-off text is asserted. --}}
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" span="full" label="Additional Details" :value="$auction->get->additional_details" />
            </x-hire-agent.detail-section>
            @endif

                        {{-- M7.3 — Photos, Tours & Documents removed. See the full reasoning in
                             hire_landlord_agent/view.blade.php; it applies here unchanged.

                             In short: the four fields the partial reads are written only by the
                             Offer Listing components, no Hire Agent questionnaire captures any of
                             them, and this view was the partial's only other caller. Seller is
                             removed in the same change as landlord for the same reason it was
                             included in M6 — leaving one of the two behind is how the pair drifts.

                             Nothing about document delivery or authorization changes. The Seller
                             OFFER Listing view keeps its own document link, which points at the
                             same untouched route. --}}

                        {{-- C9: Representation Preferences & Compatibility display (public; parity with tenant hire view).

                             S1 — the whole $repRows builder is hoisted to the prologue, where the
                             section guard reads it. The rows, their order and their "Other"
                             resolution are unchanged; only the line they are computed on moved. --}}

            {{-- Literal & in the title: Blade escapes it back to &amp; on output, so the rendered
                 text is unchanged. Passing &amp; here would double-escape it. --}}
            @if ($hsaDetailRedesign ? $hsaShows('representation') : !empty($repRows))
            @if (! $hsaDetailRedesign)<hr />@endif
            <x-hire-agent.detail-section :redesign="$hsaDetailRedesign" id="hla-section-representation" title="Representation Preferences & Compatibility:" icon="fa-solid fa-handshake">
                        {{-- One call site for up to twenty-one rows. The labels and the "Other"
                             resolution live in $repAdd in the prologue and are unchanged; this loop
                             only renders what that builder produced.

                             NO span="full" HERE, AND THAT IS THE PARITY FIX. This loop used to pass
                             it, and it was the only representation loop of the four that did —
                             buyer, landlord and tenant all let the component take its `half`
                             default. `full` resolves to `col-12`, so twenty-one short label/value
                             pairs stacked into one very long single column while the equivalent
                             tenant card flowed them two-up; visual QA caught seller as the odd one
                             out. Dropping the attribute puts these rows on `col-lg-6 col-12` — the
                             established two-up-at-lg, one-up-below behaviour every other role's
                             representation card already has.

                             span="full" IS STILL CORRECT ELSEWHERE IN THIS FILE and none of those
                             call sites are touched: Appliances, Amenities, Additional Details and
                             the rest are list-valued or sentence-length, and all three other roles
                             pass `full` for their equivalents too. The attribute was never wrong in
                             general — it was wrong for THESE rows.

                             LEGACY IS UNREACHABLE FROM HERE. `span` is read only when building
                             $hlaFieldRedesignWidth, inside the redesign branch; the flag-off element
                             tree does not consult it, so this line cannot move flag-off output. --}}
                        @foreach ($repRows as $repRow)
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" :label="$repRow['label']" :value="$repRow['value']" />
                        @endforeach
            </x-hire-agent.detail-section>
            @endif


            {{-- S1 — $referralPct / $referralPctDisplay are hoisted to the prologue, including the
                 fallback to the first bid's referral percentage.

                 THIS SECTION IS CARRIED AT AUDIENCE 'agent' IN THE REGISTRY, and with the flag off
                 it is guarded on content alone — so a referral percentage is public today and
                 becomes agent-only when the redesign is enabled for seller. That is the same
                 narrowing buyer, landlord and tenant each took at this milestone, not a change
                 invented here, and it is why the guard reads through $hsaShows rather than adding
                 an audience test to this file. --}}
            @if ($hsaDetailRedesign ? $hsaShows('referral') : $referralPctDisplay !== '')
            @if (! $hsaDetailRedesign)<hr />@endif
            <x-hire-agent.detail-section :redesign="$hsaDetailRedesign" id="hla-section-referral" title="Referral & Cooperation Terms" icon="fa-solid fa-share-nodes">
                        <x-hire-agent.field :redesign="$hsaDetailRedesign" label="Referral Fee" :value="$referralPctDisplay" />
            </x-hire-agent.detail-section>
            @endif

            {{-- Resolved in PHP rather than inline: a bound attribute containing `&&` is not
                 parseable by Blade's attribute compiler. S1 hoisted the expression to the prologue,
                 where the registry takes it as the `role-info` label override — the heading and the
                 nav entry are now the same string by construction rather than by two authors
                 remembering to match.

                 UNCONDITIONAL WITH THE FLAG OFF, exactly as before. Its rows each decline to render
                 when absent, so a listing answering none of them produced a heading with nothing
                 under it — survivable as a trailing sub-heading, not survivable as the last CARD on
                 the page, which is why $hsaHasOwnerInfo guards it on the redesign side only. --}}
            @if (! $hsaDetailRedesign || $hsaShows('role-info'))
            @if (! $hsaDetailRedesign)<hr />@endif
            <x-hire-agent.detail-section :redesign="$hsaDetailRedesign" :title="$_ownerInfoHeading" id="hla-section-role-info" icon="fa-solid fa-id-card">

                        @if (!empty($auction->get->first_name))
                            {{-- The label was split across two source lines as "First\nName:", which
                                 normalises to "First Name:" — that is the text this row has always
                                 rendered and the label the component is given. --}}
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" label="First Name" :value="$auction->get->first_name" />
                        @endif

                        {{-- The eighth and last inverted row; the other seven are in Sale Terms. --}}
                        @if (@$auction->get->current_status != null && @$auction->get->current_status != '' && @$auction->get->current_status != 'null')
                            <x-hire-agent.field :redesign="$hsaDetailRedesign" :legacy-inverted="true" span="full" label="Seller's Current Status" :value="@$auction->get->current_status" />
                        @endif

                        {{-- ── THE MEDIA BLOCK IS DELIBERATELY NOT CONVERTED ──────────────────
                             Video, Photo and Personal Video keep their hand-written rows, matching
                             buyer, landlord and tenant — none of the three converted these, and
                             this is the one place where following them means leaving markup alone
                             rather than replacing it.

                             Two reasons, both structural rather than stylistic. They carry
                             `col-md-6 col-6 pt-2 fw-bold`, a half-width class list no other row on
                             the page uses, so each would have to pass `width` verbatim to preserve
                             it. And their "value" is an embedded <video>, <img> or <iframe> sized
                             at 29vh — putting one inside a 5/7 label/value split gives it 58% of a
                             half-width cell, which is smaller than the media needs and is not what
                             the reference page does with media either.

                             They stay inside their own div.row, which is also unconverted. --}}
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
                                            if (strpos($videoLink, 'watch?v=') !== false) {
                                                $youtubeEmbedUrl = str_replace('watch?v=', 'embed/', $videoLink);
                                            } elseif (strpos($videoLink, 'youtu.be/') !== false) {
                                                $videoId = basename(parse_url($videoLink, PHP_URL_PATH));
                                                $youtubeEmbedUrl = "https://www.youtube.com/embed/{$videoId}";
                                            } else {
                                                $youtubeEmbedUrl = $videoLink;
                                            }
                                            $youtubeEmbedUrl .=
                                                (strpos($youtubeEmbedUrl, '?') === false ? '?' : '&') .
                                                'autoplay=1&mute=1';
                                        @endphp

                                        <div class="col-md-6 col-6 pt-2 fw-bold">Personal Video:
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
                                            preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/', $videoLink, $matches);
                                            $vimeoVideoId =
                                                $matches[1] ?? basename(parse_url($videoLink, PHP_URL_PATH));
                                            $vimeoEmbedUrl = "https://player.vimeo.com/video/{$vimeoVideoId}?autoplay=1&muted=1";
                                        @endphp

                                        <div class="col-md-6 col-6 pt-2 fw-bold">Personal Video:
                                            <span class="removeBold">
                                                <iframe src="{{ $vimeoEmbedUrl }}" width="100%" height="315"
                                                    frameborder="0"
                                                    allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
                                                    allowfullscreen>
                                                </iframe>
                                            </span>
                                        </div>
                                    @else
                                        <div class="col-md-12 col-12 pt-2 fw-bold">Personal Video:
                                            <span class="removeBold">
                                                <a href="{{ $videoLink }}" target="_blank" rel="noopener noreferrer">{{ $videoLink }}</a>
                                            </span>
                                        </div>
                                    @endif
                                @else
                                    <div class="col-md-12 col-12 pt-2 fw-bold">Personal Video:
                                        <span class="removeBold">{{ $auction->get->video_link }}</span>
                                    </div>
                                @endif
                            @endif
                        </div>
            </x-hire-agent.detail-section>
            @endif

            {{-- T4 — Agent Credentials & Contact Info.

                 THE SECTION THE T1 SCAFFOLD PROMISED. $hsaHasAgentCredentials has been computed
                 and handed to the section registry since T1, with a note saying "T4 adds the
                 card". Until now nothing consumed the resolved set except the cards themselves, so
                 the missing card was inert. The section bar added above IS such a consumer: with
                 the guard true and no card, the bar would offer an anchor that resolves to
                 nothing. That is why this lands in the same change as the nav rather than after
                 it.

                 No Hire Agent detail view renders anything like this with the flag off, so there
                 is no legacy branch to preserve: the whole block sits inside the redesign guard
                 and emits nothing when it is off. That is why it carries none of the
                 `@if (! $hsaDetailRedesign)` fallbacks every section above it has, and why
                 :redesign="true" below is a statement of fact rather than a flag read — the
                 section is already gated `$hsaDetailRedesign &&`.

                 THE LISTING OWNER'S credentials, and only when that owner is an agent — never the
                 viewer's own and never the hired agent's. It is the counterpart to Referral &
                 Cooperation above: the terms of a referral, and who the agent on the other side of
                 it is. $hsaHasAgentCredentials carries both halves (the owner is an agent, and has
                 at least one field to show), and the resolver additionally withholds the section
                 from anyone below the agent tier.

                 Nothing here is new data: these are the same values author.blade.php already
                 publishes on the public profile page. Buyer's and landlord's identical sections are
                 the reference — same four fields, same order, each row guarding itself so a partly
                 filled profile shows what it has. --}}
            @if ($hsaDetailRedesign && $hsaShows('agent-credentials'))
            <x-hire-agent.detail-section :redesign="true" :legacy-header="false" id="hla-section-agent-credentials" title="Agent Credentials & Contact Info" icon="fa-solid fa-address-card">
                        @if (@$auction->user->brokerage != null)
                            <x-hire-agent.field :redesign="true" label="Brokerage" :value="$auction->user->brokerage" />
                        @endif

                        @if (@$auction->user->license_no != null)
                            <x-hire-agent.field :redesign="true" label="License No." :value="$auction->user->license_no" />
                        @endif

                        @if (@$auction->user->phone != null)
                            <x-hire-agent.field :redesign="true" label="Phone" :value="$auction->user->phone" />
                        @endif

                        @if (@$auction->user->email != null)
                            <x-hire-agent.field :redesign="true" label="Email" :value="$auction->user->email" />
                        @endif
            </x-hire-agent.detail-section>
            @endif
            {{-- M3: the former card-body close is gone with its opening tag; the card closes here.
                 S1: "here" is now the detail-body, which emits that same card with the flag off. --}}
            </x-hire-agent.detail-body>
                @inject('auctionUser', 'App\Models\User')
                @php
                    $auser = $auctionUser::find(@$auction->user_id);

                    /*
                     | M7.5 — the owner card says only what the record supports. Applied to all
                     | four roles, because the card was copied to all four and so were its three
                     | fabrications: a five-star rating with no rating data behind it, a hardcoded
                     | "last online 5 days ago", and a "..." standing in for a bio that does not
                     | exist.
                     |
                     | Not flag-gated. This role has adopted no redesign flag at all, and the
                     | reasoning holds regardless: invented claims about a named person are not
                     | something to leave live pending a layout rollout.
                     |
                     | The guard adopts the buyer view's existing `@if ($auser)` — the only one of
                     | the four that already had it — rather than inventing a second spelling. The
                     | name is part of it: a resolvable row with no usable name identifies nobody,
                     | and would render a bold empty anchor where the name belongs.
                     */
                    $hlaOwnerName = trim((string) ($auser->name ?? ''));

                    if ($auser && $hlaOwnerName === '') {
                        $hlaOwnerName = trim(($auser->first_name ?? '') . ' ' . ($auser->last_name ?? ''));
                    }
                @endphp
                <!-- Review  -->
                @if ($auser && $hlaOwnerName !== '')
                <div class="card review">
                    <div class="card-body d-flex align-items-center">
                        <div class="left d-flex align-items-center">
                            <x-avatar-img :avatar="$auser->avatar" alt="" class="w-25" />
                            <div>
                                {{-- The name IS the link; it read "User Details" before, with the
                                     actual name demoted to a muted line below. --}}
                                <p class="mb-0"><a href="{{ route('author', [$auser->id]) }}"><b>{{ $hlaOwnerName }}</b></a></p>
                            </div>
                        </div>
                        <div class="right text-center">
                            {{-- Message goes to the conversation, not to the profile. Both controls
                                 pointed at `author` before this, so the two labels were one link. --}}
                            <a href="{{ route('auction-chat', ['seller-agent', $auction->id]) }}"><button class="btn">Message</button></a>
                            <a href="{{ route('author', [$auser->id]) }}"><button class="btn view-btn">View
                                    Profile</button></a>
                        </div>

                    </div>
                </div>
                @endif
        </x-slot>

        {{-- Sidebar body untouched by 5A.3; the shell supplies only the column wrapper.
             Extracting it is Milestone 5B. --}}
        <x-slot name="sidebar">
    @php
        // T3 — hoisted out of the identity block below so this assignment happens in BOTH
        // treatments. Later sidebar code reads $auth_id, and leaving it inside a block that the
        // redesigned hero suppresses would silently change those reads the moment the hero is
        // enabled for seller. Mirrors the buyer and landlord views. The prologue at the top of
        // this section already assigns $auth_id unconditionally, so this is belt-and-braces
        // rather than a behaviour change — but the other three roles state it here, and a reader
        // checking why the guarded block is safe should not have to scroll 1,600 lines to find
        // out. The directive emits no output, so hoisting it cannot alter the legacy rendering.
        $auth_id = auth()->id();
    @endphp

    {{--
        T4 — THE SIDEBAR SURFACE, chrome parity with buyer, landlord and tenant.

        Everything from here to the proposal console is one card. Before this the seller sidebar
        was a bare stack — heading, badges, rules and buttons sitting directly on the page
        background beside a main column made entirely of cards.

        WHERE IT CLOSES. Above the proposal console, which stays a SIBLING rather than a child. The
        console brings its own `.card` chrome, so nesting it would render border inside border and
        shadow inside shadow; and its contents are gated by HireAgentProposalAccess, so keeping it
        outside this wrapper means no geometry rule added here has a selector that can reach a
        proposal card. That fence is deliberate and is not crossed by this change.

        AND IT IS WHAT MAKES THE STICKY WORK. A sidebar column carrying a populated console is as
        tall as the main column, and an element that is never shorter than its container never
        sticks. This card is short by construction, because the thing that made the column tall is
        left outside it.

        Redesign-only, so with the detail flag off the sidebar emits exactly the bytes it did
        before. Gated on the DETAIL flag, not the hero flag: this is page chrome, and the identity
        block below it is gated on the hero flag for its own separate reason.
    --}}
    @if ($hsaDetailRedesign)
    <div class="hla-surface-card hla-sidebar-card hla-sidebar-sticky" data-hire-agent-sidebar-card>
    @endif

    {{--
        T3 — the sidebar identity block.

        Title, listing id, status and Edit Listing move INTO the hero when the hero redesign is on
        for seller, so this block renders only when it is off. What is avoided is duplication:
        without this guard the page would carry two <h1> elements, two status pills and two Edit
        controls, which is worse than either treatment alone.

        Gated on the HERO flag, not the detail flag — the two roll out independently by design, and
        this block's counterpart lives in the hero. Reading the detail flag here would suppress the
        identity block for a role whose hero is still off, leaving the page with no title at all.

        THE EXPIRY OVERRIDE BELOW IS NOT MOVED, AND DOES NOT NEED TO BE. It re-derives a result
        SellerAgentAuction::getStatusAttribute() has already produced — the accessor returns
        'Hired Agent' from is_sold or listing_status, 'Pending' from listing_status, and 'Expired'
        from expiration_date, which is the same input in the same precedence — so the hero reading
        $auction->status directly yields the identical label for every state. Nothing about expiry
        is reimplemented in the presenter; there is nothing left to reimplement. Same conclusion
        landlord reached.
    --}}
    @unless (\App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('seller'))
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
                            // Milestone 3: this override used to synthesise an expiry from
                            // created_at + auction_time for Bidding Period listings, so the badge
                            // could read "Expired" purely because a countdown had elapsed. That
                            // branch is retired; expiration_date is the only input, for every
                            // listing type. Still display-only — the model is never mutated.
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
                    <a href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => 'seller']) }}" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
                    </a>
                    {{-- PDF download button hidden from UI (backend route preserved) --}}
                </div>
                @endif
    @endunless
                {{-- T4 — and this is the change T3 deferred to here. This rule only ever separated
                     the identity block from what follows. Under the redesign the sidebar is a card,
                     and the card's own edge and padding are the separation the rule stood in for —
                     so it is suppressed there and left exactly as-is for the legacy branch. Gated
                     on the DETAIL flag, matching buyer and landlord, because it is the CARD that
                     makes it redundant, not the hero.

                     WORTH RECORDING, because it is a live flag combination rather than a
                     hypothetical: with the hero flag on and the detail flag off, the identity block
                     above does not render but this rule still does, separating nothing. Landlord
                     carries the same artifact and documented the same answer — the fix is turning
                     the detail flag on after visual verification, not suppressing the rule a second
                     time in the legacy branch. --}}
    @unless ($hsaDetailRedesign)
                <hr>
    @endunless

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
        $expiration = !empty($auction->get->expiration_date)
            ? $carbon::parse($auction->get->expiration_date)
            : null;

        $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;
    @endphp


    {{-- 💰 Bid Info --}}
    @php
        // Milestone 2 — competing-agent proposal privacy.
        // $lowest_bid_price / $lowest_bidder were removed. They existed only to render
        // "Agent N was the last bidder", which disclosed a competing agent and was mislabelled
        // besides: it resolved the MINIMUM brokerage bid while calling that agent the LAST
        // bidder. Not restored in any form.
        // $auction->bids is already narrowed to this viewer's authorized proposals by
        // HireAgentProposalAccess in SellerAgentAuctionController::viewDetail(), so $my_bid
        // still resolves for the viewer's own bid and can never resolve a competitor's.
        $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp


        {{-- 📩 Message Button.
             SUPPRESSED UNDER THE REDESIGN, matching the `@unless` landlord already carries. The
             Quick Actions band above the grid renders this same route as a tile, so leaving this
             button in place put the identical action on the page twice — the duplication visual
             QA reported. Suppressed rather than deleted: with the flag off this is the only
             Send Message on the page.

             WHO MAY SEE IT IS UNCHANGED. The control stays unconditional in both flag states and
             the route keeps enforcing its own middleware; this decides WHERE the action lives,
             never WHO may take it. --}}
        @unless ($hsaDetailRedesign)
        <a href="{{ route('auction-chat', ['seller-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message
        </a>
        @endunless


        {{--
            Milestone 3: the Days / Hrs / Mins / Secs countdown block stood here, along with the
            "Bidding Ended" pill it fell back to. Both are retired. The listing's state is already
            carried by the status pill above (Active / Pending / Expired / Hired Agent) and by the
            expiry notice below, neither of which counts down. No replacement urgency mechanism is
            introduced — that is the point of the retirement, not an omission.
        --}}



        @php
        $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
        @endphp

        {{-- 🔹 Bid Button --}}
        @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
        @if (!$isExpired && !in_array($auction->is_sold, [true,'true',1,'1'], true) && $auction->status !== 'Pending' && $auction->status !== 'Hired Agent')
        @if ($userHasBid)
        {{-- User already placed a bid --}}
        <div class="alert alert-info text-center mb-2">
            <i class="fa-solid fa-circle-check"></i> You have already placed a bid
        </div>
        <div class="status-pill status-disabled w-100 d-flex justify-content-between">
            <span>Bid Already Placed</span>
            @if ($hsaCtaPrice)<span style="font-weight:normal;font-size:.85em;">{{ $hsaCtaPrice }}</span>@endif
        </div>

        @else
        {{-- User can place a bid --}}
        <button class="btn w-100 bid-btn"
            onclick="window.location='{{ route('add_seller_agent_bid', @$auction->id) }}';">
            <span class="bid">Bid Now</span>
            @if ($hsaCtaPrice)<span class="badge bg-light float-end text-dark">{{ $hsaCtaPrice }}</span>@endif
        </button>
        @endif

        @elseif($auction->status === 'Hired Agent' || in_array($auction->is_sold, [true,'true',1,'1'], true))
        <div class="alert alert-success text-center mb-2">
            <i class="fa-solid fa-trophy"></i> <strong>An agent has been hired</strong>
        </div>
        <div class="status-pill status-hired w-100 d-flex justify-content-center">
            <i class="fa-solid fa-trophy me-2"></i>Hired Agent
        </div>
        @elseif($auction->status === 'Pending')
        <div class="alert alert-warning text-center mb-2">
            <i class="fa-solid fa-pause-circle"></i> <strong>This listing is pending &mdash; not accepting new bids</strong>
        </div>
        <div class="status-pill status-pending w-100 d-flex justify-content-center">
            <i class="fa-solid fa-pause-circle me-2"></i>Pending
        </div>
        @else
        {{-- Expiry catch-all. Milestone 3: this used to branch on listing type, suppressing the
             notice for Bidding Period listings because the retired timer block had already
             rendered "Bidding Ended". With the timer gone there is one expiry state and one
             notice, driven by expiration_date. --}}
        <div class="alert alert-secondary text-center mb-2">
            <i class="fa-solid fa-calendar-xmark me-1"></i> <strong>This listing has expired</strong>
        </div>
        @endif

        @if (@$auction->sold)
        <span class="status-pill status-ended w-100 d-flex justify-content-center mt-2">Sold</span>
        @endif
        @elseif(!$auth_id)
        {{-- Guest CTA. Under the redesign this routes through x-hire-agent.login-cta so all four
             roles emit one button structure; the legacy markup below is untouched and still the
             flag-off answer. The BRANCH is unchanged — same `! $auth_id` condition, same login
             route — only how the action is drawn. --}}
        @if ($hsaDetailRedesign)
        <x-hire-agent.login-cta :amount="$hsaCtaPrice" />
        @else
        <a href="{{ route('login') }}">
            <button class="btn w-100">
                <span class="bid m-0">Login to Bid</span>
                @if ($hsaCtaPrice)<span class="badge bg-light float-end text-dark">{{ $hsaCtaPrice }}</span>@endif
            </button>
        </a>
        @endif
        @else
        <div class="alert alert-secondary text-center">
            Only agents can place bids
        </div>
        @endif
        @php
            // === SELLER BID SECTION: VARIABLE SETUP (matches Tenant pattern) ===
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
            $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
            $isAgentViewer  = $auth_id && in_array(auth()->user()->user_type ?? '', ['agent']);
        @endphp

    {{-- T4 — the sidebar card closes HERE, above the proposal console. The console is its sibling,
         not its child: it brings its own card chrome, and its contents are gated by
         HireAgentProposalAccess. Nesting it would double the border and shadow, and would put a
         geometry rule from this change in reach of a proposal card. See the block that opens the
         wrapper for the full reasoning. Buyer closes at the identical point. --}}
    @if ($hsaDetailRedesign)
    </div>
    @endif

        {{--
            Milestone 2 — competing-agent proposal privacy. Removed from this position:

              • "Agent N was the last bidder."  — competing identity + activity + order, and
                mislabelled (it named the minimum brokerage bid). Not restored in any form.
              • The "Submit your bid to view competing bids" prompt — transparent-bidding
                framing, and an existence disclosure even before any bid was shown.
              • The "Competing bids are visible below" banner and the whole inline
                CompetingBidsService block that followed it — competing counts, anonymous
                labels, match summaries and per-bid score breakdowns.
              • $lastBidderNumber / $canSeeBidSummary / $otherBidsExist — the variables that
                existed only to drive the above.

            The second checkpoint completed that removal: CompetingBidsService, its controller,
            its two routes, its dedicated view and BiddingPeriodAgentMapping are all deleted.
            The retired URLs 404. Only the mapping TABLE remains, by design.

            The owner-only empty state is retained below, gated on the server-side decision
            rather than on a Blade-local guess, because a bid count is itself a disclosure.
        --}}
        @if (($canReviewAllProposals ?? false) && $auction->bids->isEmpty())
            <p class="mb-3">No agents have submitted a bid yet.</p>
        @endif

        {{-- THE PROPOSAL CONSOLE EXISTS ONLY FOR VIEWERS WHO HAVE A PROPOSAL TO SEE.
        
             `<div class="card higestBider">` rendered unconditionally. For every viewer the access
             layer hands zero proposals — a guest, a competing agent, an agent who has not bid, an
             unrelated authenticated user — it drew an empty bordered card. The redesign made that
             conspicuous by clearing what used to sit around it: on a guest page the sidebar became
             the CTA and this empty bar. That is the leftover control visual QA reported.
        
             THE CONDITION IS LANDLORD'S, VERBATIM — see the long note at its own console. Two allow
             branches matching HireAgentProposalAccess: the owner, who must still get the console and
             its empty state before anyone bids; and a non-empty `$auction->bids`, which the
             controller ALREADY narrowed, so "non-empty" here means "this viewer is authorized to see
             at least one proposal" and cannot mean anything else.
        
             THIS IS NOT THE PRIVACY MECHANISM. Withholding happens server-side before the view runs
             and the per-card gates are untouched; this only stops an empty container being drawn.
             Flag-gated, so the legacy page keeps the empty card it has today. --}}
        @if (! $hsaDetailRedesign || ($canReviewAllProposals ?? false) || $auction->bids->isNotEmpty())

        <div class="card higestBider" id="bids-section">
            <div class="card-body card-body-padding">
                <div class="accordion" id="accordionExample">
                    <div class="accordion-item border-0">

                        @foreach (@$auction->bids as $bid)
                        @php
                            $agentNumber   = $agentNumberMap[$bid->user_id] ?? $loop->iteration;
                            $bidState      = data_get($bid, 'accepted', 'active');
                            // Check if a Seller counter term exists for this bid (never overrides terminal accepted/rejected states)
                            $hasSellerCounter = \App\Models\SellerCounterTerm::where('seller_agent_auction_bid_id', data_get($bid, 'id'))->where('status', 1)->exists();
                            $isTerminalState  = in_array((string)$bidState, ['accepted', 'rejected'], true);
                            $effectiveBidState = (!$isTerminalState && $hasSellerCounter) ? 'countered' : (string)$bidState;
                            $bidStatusLabel = match($effectiveBidState) {
                                'accepted'  => 'Accepted',
                                'rejected'  => 'Rejected',
                                'countered' => 'Countered',
                                default     => 'Active',
                            };
                            $bidStatusColor = match($effectiveBidState) {
                                'accepted'  => '#28a745',
                                'rejected'  => '#dc3545',
                                'countered' => '#ffc107',
                                default     => '#1a4a6e',
                            };
                            $servicesList        = (array) data_get($bid, 'get.services', []);
                            $additionalServices  = (array) data_get($bid, 'get.other_services', []);
                            $totalServicesCount  = count(array_filter($servicesList, fn($s) => $s !== 'Other')) + count($additionalServices);
                            $isBidOwner          = (data_get($bid, 'user_id') == $auth_id);
                            $bidAccepted         = data_get($bid, 'accepted');
                            $canEditWithdraw     = $isBidOwner && !$isExpired && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected';
                            $isAgent = $auth_id && in_array(auth()->user()->user_type ?? '', ['agent']);

                            // Milestone 2 — competing-agent proposal privacy.
                            // $auction->bids was narrowed by HireAgentProposalAccess in the
                            // controller, so this loop can only ever iterate the owner's full
                            // set or a single agent's own bid. The guard below is deliberate
                            // defence-in-depth with the opposite default to the one it
                            // replaced: skip anything that is not the owner's to review or the
                            // viewer's own, rather than admitting competitors under Traditional
                            // or post-bid Bidding Period conditions.
                            if (! $isListingOwner && ! $isBidOwner) { continue; }

                            // Build Seller purchase fee display for card body summary
                            $sellerCommStructure        = data_get($bid, 'get.commission_structure', 'Not specified');
                            $sellerPurchaseFeeType      = data_get($bid, 'get.purchase_fee_type', '');
                            $sellerPurchaseFeeFlat      = data_get($bid, 'get.purchase_fee_flat', '');
                            $sellerPurchaseFeePerc      = data_get($bid, 'get.purchase_fee_percentage', '');
                            $sellerPurchaseFeeFlatCombo = data_get($bid, 'get.purchase_fee_flat_combo', '');
                            $sellerPurchaseFeePercCombo = data_get($bid, 'get.purchase_fee_percentage_combo', '');
                            $sellerPurchaseFeeOther     = data_get($bid, 'get.purchase_fee_other', '');

                            $sellerPurchaseFeeDisplay = '-';
                            if ($sellerPurchaseFeeType === 'flat' && $sellerPurchaseFeeFlat) {
                                $sellerPurchaseFeeDisplay = $fmtMoney($sellerPurchaseFeeFlat) ?? '-';
                            } elseif ($sellerPurchaseFeeType === 'percentage' && $sellerPurchaseFeePerc) {
                                $sellerPurchaseFeeDisplay = ($fmtPercent($sellerPurchaseFeePerc) ?? '-') . ' of Total Purchase Price';
                            } elseif ($sellerPurchaseFeeType === 'combo') {
                                $sellerPurchaseFeeDisplay = $joinParts([
                                    $sellerPurchaseFeePercCombo ? ($fmtPercent($sellerPurchaseFeePercCombo) . ' of Total Purchase Price') : null,
                                    $fmtMoney($sellerPurchaseFeeFlatCombo),
                                ]) ?? '-';
                            } elseif ($sellerPurchaseFeeType === 'other' && $sellerPurchaseFeeOther) {
                                $sellerPurchaseFeeDisplay = $sellerPurchaseFeeOther;
                            }

                            // === MATCH SCORE CALCULATION via SellerBidMatchScoreHelper ===
                            // ── Seller Match Score Baseline ─────────────────────────────────────────────────────────
                            // BASELINE POLICY: Card score ALWAYS uses the original auction listing terms as baseline
                            // to ensure a consistent denominator across all bids on the same listing.
                            // Counter comparison is computed separately for the dual-score display (authorized only).
                            $latestCounter   = \App\Models\SellerCounterTerm::with('meta')
                                ->where('seller_agent_auction_bid_id', $bid->id)
                                ->where('status', 1)
                                ->latest('updated_at')
                                ->first();
                            // Note: ->get accessor on SellerAgentAuction / SellerAgentAuctionBid calls getGetAttribute()
                            // which queries the meta table directly each access — always fresh, no eager-load dependency.
                            $propertyType    = data_get($auction, 'get.property_type', '');
                            $bidDataArr      = (array) data_get($bid, 'get', []);
                            $auctionDataArr  = (array) data_get($auction, 'get', []);

                            // Card score always uses original listing baseline
                            $baselineData  = $auctionDataArr;
                            $baselineLabel = $isListingOwner ? 'Your Original Terms' : "Seller's Original Terms";

                            $scoreResult     = \App\Helpers\SellerBidMatchScoreHelper::calculate($baselineData, $bidDataArr, null, $propertyType);
                            $totalScore      = $scoreResult['overall_percent'] ?? 100;
                            $brokerScore     = $scoreResult['broker_comp_percent'] ?? 100;
                            $servicesScore   = $scoreResult['services_percent'] ?? 100;
                            $brokerTotal     = $scoreResult['broker_comp_total'] ?? 0;
                            $brokerMatched   = $scoreResult['broker_comp_matched'] ?? 0;
                            $servicesTotal   = $scoreResult['services_baseline_total'] ?? 0;
                            $servicesMatched = $scoreResult['services_matched_count'] ?? 0;
                            $servicesExtraCount   = $scoreResult['services_extra_count'] ?? 0;
                            $servicesMissingCount = $scoreResult['services_missing_count'] ?? 0;
                            $brokerMismatches = $scoreResult['changed_terms'] ?? [];
                            $termsChangedCount = $scoreResult['terms_changed_count'] ?? 0;
                            $termsAddedCount   = $scoreResult['terms_added_count'] ?? 0;
                            $mismatchStyle    = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                            $mismatchBadge    = '<span class="badge" style="background-color: #dc3545; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Mismatch</span>';
                            $totalScoreColor = \App\Helpers\SellerBidMatchScoreHelper::scoreColor($totalScore);
                            $getScoreColor   = fn($s) => \App\Helpers\SellerBidMatchScoreHelper::scoreColor((int)$s);
                            /**
                             * ZERO-BASELINE / NO-DATA GUARD
                             *
                             * If there is no comparable baseline match data, do not display 100%.
                             * Render "No match data available" instead.
                             *
                             * This behavior is locked by QA baseline documentation.
                             * Reference: qa_reports/QA_LOCK_BidComparison_v1.md
                             */
                            $hasAnyBaseline  = ($brokerTotal > 0 || $servicesTotal > 0);

                            // Dual-score: $scoreResult is already listing-based; compute counter score separately
                            $originalScore = $scoreResult;
                            if ($latestCounter && $latestCounter->meta->count()) {
                                $latestCounterScore = \App\Helpers\SellerBidMatchScoreHelper::calculate(
                                    $latestCounter->meta->pluck('meta_value', 'meta_key')->toArray(),
                                    $bidDataArr, null, $propertyType
                                );
                                $showDualScore = true;
                            } else {
                                $latestCounterScore = null;
                                $showDualScore = false;
                            }
                        @endphp

                        <!-- Bid Card - Collapsible Accordion Design (matches Tenant) -->
                        <div class="card mb-3" style="border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">

                            <!-- A) Card Header - Clickable to expand/collapse -->
                            <div class="card-header d-flex justify-content-between align-items-center hla-bid-accordion-header"
                                 style="cursor: pointer; background: #fff; border-bottom: 1px solid #e0e0e0; padding: 15px 20px;"
                                 data-target="bidCollapse-{{ data_get($bid, 'id') }}"
                                 aria-expanded="false">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-chevron-down bid-chevron" style="transition: transform 0.3s; color: #1a3a5c;"></i>
                                    <h5 class="mb-0" style="font-weight: 700; color: #1a3a5c; font-size: 1.4rem;">Agent {{ $agentNumber }}</h5>
                                </div>
                                <span style="font-weight: 600; color: {{ $bidStatusColor }}; font-size: 1.1rem;">{{ $bidStatusLabel }}</span>
                            </div>

                            <!-- Collapsible Content -->
                            <div class="bid-collapse-content" id="bidCollapse-{{ data_get($bid, 'id') }}" style="display: none;">
                            <div class="card-body" style="padding: 20px;">

                                @if($isListingOwner || $isBidOwner)
                                <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">

                                {{-- Counter Offer Notice Banner — visible immediately on accordion expand (owner/agent only) --}}
                                @if ($latestCounter && ($isListingOwner || $isBidOwner))
                                @php $scBidCardCounterFromOwner = ($latestCounter->user_id == data_get($auction, 'user_id')); @endphp
                                <div class="alert d-flex align-items-start gap-2 mb-3 py-2 px-3"
                                     style="background: #fff8e1; border: 1px solid #ffc107; border-left: 4px solid #ffc107; border-radius: 6px; font-size: 0.9rem;">
                                    <i class="fa-solid fa-right-left mt-1" style="color: #e6a800; flex-shrink: 0;"></i>
                                    <div>
                                        @if ($isListingOwner && $scBidCardCounterFromOwner)
                                            <strong>Counter Offer Sent.</strong>
                                        @elseif ($isListingOwner && !$scBidCardCounterFromOwner)
                                            <strong>Counter Offer Received.</strong>
                                        @elseif ($isBidOwner && $scBidCardCounterFromOwner)
                                            <strong>Counter Offer Received.</strong>
                                        @elseif ($isBidOwner && !$scBidCardCounterFromOwner)
                                            <strong>Counter Offer Sent.</strong>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                {{-- ── Counter action row — directly on bid card ── --}}
                                @if ($latestCounter && ($isListingOwner || $isBidOwner) && $bidState !== 'accepted' && $bidState !== 'rejected')
                                @php $bidCardViewerSentLatestSeller = ($isListingOwner && $scBidCardCounterFromOwner) || ($isBidOwner && !$scBidCardCounterFromOwner); @endphp
                                @if ($bidCardViewerSentLatestSeller)
                                {{-- WAITING: single row — View CT + Edit CT --}}
                                <div class="d-flex gap-2 align-items-center mb-2">
                                    <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                    </a>
                                    <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                    </a>
                                </div>
                                @else
                                {{-- RESPONSE: View CT only — Accept/Counter Back/Reject are on View Counter Terms page --}}
                                <div class="d-flex align-items-center mb-2">
                                    <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                        <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                    </a>
                                </div>
                                @endif
                                @endif

                                <!-- B) Offered Services Count -->
                                <p class="mb-0" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Offered Services:</span>
                                    <span style="color: #28a745; font-weight: 600;">{{ $servicesTotal > 0 ? $servicesMatched.'/'.$servicesTotal : 'No services requested' }}</span>{{ $servicesTotal > 0 ? ' matched' : '' }}
                                    @if ($servicesTotal > 0 && $servicesExtraCount > 0)
                                    <span class="text-muted ms-2">&bull; {{ $servicesExtraCount }} extra</span>
                                    @endif
                                    @if ($servicesTotal > 0 && $servicesMissingCount > 0)
                                    <span class="ms-2" style="color: #dc3545;">&bull; {{ $servicesMissingCount }} missing</span>
                                    @endif
                                </p>
                                @if ($servicesExtraCount > 0)
                                <div class="mt-2 d-flex align-items-center flex-wrap" style="gap: 4px 6px;">
                                    <span style="font-size: 0.9rem; line-height: 1.4;">&#11088;</span>
                                    <span style="font-weight: 500; color: #856404; font-size: 0.95rem;" title="Extra services were included by the Agent beyond the Seller&#39;s original request. These do not increase the match score but may provide additional value.">Extra Value Added: {{ $servicesExtraCount }} {{ $servicesExtraCount === 1 ? 'Service' : 'Services' }}</span>
                                    <span class="text-muted" style="font-size: 0.78rem; font-style: italic;">&mdash; does not affect match score</span>
                                </div>
                                @endif

                                <!-- Terms Match Row -->
                                @if ($hasAnyBaseline && $brokerTotal > 0)
                                <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Terms Match:</span>
                                    <span style="color: #28a745; font-weight: 600;">{{ $brokerMatched }}/{{ $brokerTotal }} matched</span>
                                    @if ($termsChangedCount > 0)
                                    <span class="ms-2" style="color: #dc3545;">&bull; {{ $termsChangedCount }} changed</span>
                                    @endif
                                    @if ($termsAddedCount > 0)
                                    <span class="text-muted ms-2">&bull; {{ $termsAddedCount }} added</span>
                                    @endif
                                    @php $termsMissingCount = max(0, $brokerTotal - $brokerMatched - $termsChangedCount); @endphp
                                    @if ($termsMissingCount > 0)
                                    <span class="ms-2" style="color: #dc3545;">&bull; {{ $termsMissingCount }} missing</span>
                                    @endif
                                </p>
                                <div class="mt-1" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&mdash; affects match score</div>
                                @elseif ($hasAnyBaseline && $brokerTotal === 0)
                                <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Terms Match:</span>
                                    <span class="text-muted">&mdash;</span>
                                </p>
                                @endif

                                <hr style="margin: 15px 0; border-color: #e0e0e0;">

                                <!-- B2) Match Score Summary (Compact Display on Bid Card) -->
                                @php
                                    // Milestone 2: the third disjunct — Bidding Period + any
                                    // agent who had bid — showed a competitor's match score.
                                    // Owner review and the bidder's own score only.
                                    $showMatchScoreOnCard = $isListingOwner || $isBidOwner;
                                @endphp
                                @if ($showMatchScoreOnCard && $hasAnyBaseline)
                                <div class="match-score-summary mb-3 p-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6; font-size: 0.88rem;">
                                    @if ($showDualScore && $originalScore && $latestCounterScore)
                                    {{-- DUAL SCORE: Original Match + Latest Counter Match side-by-side --}}
                                    <div class="mb-2">
                                        <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                            <i class="fa-solid fa-chart-pie me-2"></i>Match Summary
                                        </span>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        {{-- Original Match --}}
                                        @php
                                            $osColor = $getScoreColor($originalScore['overall_percent']);
                                        @endphp
                                        <div class="col-6">
                                            <div class="p-2 rounded" style="background: #fff; border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                                                    <span class="badge" style="background: {{ $osColor }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $originalScore['overall_percent'] }}%</span>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #6c757d;">vs. Seller's Original Request</div>
                                                <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                    <div class="col-6" style="color: {{ $getScoreColor($originalScore['services_match_percent']) }};">Services {{ $originalScore['services_match_percent'] }}%</div>
                                                    <div class="col-6" style="color: {{ $getScoreColor($originalScore['terms_match_percent']) }};">Terms {{ $originalScore['terms_match_percent'] }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Latest Counter Match --}}
                                        @php
                                            $lcColor2 = $getScoreColor($latestCounterScore['overall_percent']);
                                        @endphp
                                        <div class="col-6">
                                            <div class="p-2 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColor2 }};">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                                                    <span class="badge" style="background: {{ $lcColor2 }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $latestCounterScore['overall_percent'] }}%</span>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #6c757d;">vs. Your Latest Counter</div>
                                                <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                    <div class="col-6" style="color: {{ $getScoreColor($latestCounterScore['services_match_percent']) }};">Services {{ $latestCounterScore['services_match_percent'] }}%</div>
                                                    <div class="col-6" style="color: {{ $getScoreColor($latestCounterScore['terms_match_percent']) }};">Terms {{ $latestCounterScore['terms_match_percent'] }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="small" style="color: #6c757d; font-style: italic; font-size: 0.76rem;">
                                        <i class="fa-solid fa-circle-info me-1"></i>Added services or terms do not increase either score.
                                    </div>
                                    @else
                                    {{-- SINGLE SCORE fallback --}}
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                            <i class="fa-solid fa-chart-pie me-2"></i>Match Score
                                        </span>
                                        <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1rem; padding: 6px 12px; color: white;">
                                            {{ $totalScore }}%
                                        </span>
                                    </div>
                                    <div class="row g-2 small">
                                        <div class="col-6">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Services Match:</span>
                                                <span style="color: {{ $getScoreColor($servicesScore) }}; font-weight: 600;">{{ $servicesScore }}%</span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">
                                                {{ $servicesTotal > 0 ? 'Matched: '.$servicesMatched.'/'.$servicesTotal : 'No services requested' }}
                                                @if ($servicesTotal > 0 && $servicesExtraCount > 0) &bull; Extra: {{ $servicesExtraCount }}@endif
                                                @if ($servicesTotal > 0 && $servicesMissingCount > 0) &bull; Missing: {{ $servicesMissingCount }}@endif
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Terms Match:</span>
                                                <span style="color: {{ $getScoreColor($brokerScore) }}; font-weight: 600;">{{ $brokerScore }}%</span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">
                                                {{ $brokerTotal > 0 ? 'Matched: '.$brokerMatched.'/'.$brokerTotal : 'No terms provided' }}
                                                @if ($brokerTotal > 0 && $termsChangedCount > 0) &bull; Changed: {{ $termsChangedCount }}@endif
                                                @if ($brokerTotal > 0 && $termsAddedCount > 0) &bull; Added: {{ $termsAddedCount }}@endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 small text-muted">
                                        <i class="fa-solid fa-circle-info me-1"></i>Compared to: {{ $baselineLabel }}
                                    </div>
                                    <div class="mt-1 small" style="color: #6c757d; font-style: italic; font-size: 0.78rem;">
                                        Match Score compares this bid only to the Seller's original request. Added services or added terms are shown for transparency but do not increase the score.
                                    </div>
                                    @endif
                                </div>
                                @endif

                                <!-- D) View Full Bid link -->
                                @if ($isListingOwner || $isBidOwner)
                                <a href="#" data-bs-toggle="modal" data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
                                   style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                    View Full Bid
                                </a>
                                @else
                                <span style="color: #888; font-style: italic; font-size: 0.95rem;">
                                    <i class="fa-solid fa-lock me-1"></i> Full bid details are private
                                </span>
                                @endif

                                <!-- E) Edit Actions for Bid Owner -->
                                @if ($canEditWithdraw)
                                <div class="d-flex gap-2 mt-3 justify-content-end align-items-center">
                                    <a href="{{ route('add_seller_agent_bid', $auction->id) }}?edit={{ data_get($bid, 'id') }}"
                                       class="btn btn-primary hla-bid-action-btn">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Bid
                                    </a>
                                </div>
                                @elseif ($isBidOwner && $isExpired)
                                <div class="mt-3">
                                    <span class="text-muted small">
                                        <i class="fa-solid fa-clock me-1"></i> Bidding has ended - edit unavailable
                                    </span>
                                </div>
                                @elseif ($isBidOwner && ($bidAccepted === 'accepted' || $bidAccepted === 'rejected'))
                                <div class="mt-3">
                                    <span class="text-muted small">
                                        <i class="fa-solid fa-lock me-1"></i> Bid {{ $bidAccepted }} - edit unavailable
                                    </span>
                                    @if($bidAccepted === 'accepted')
                                    @php
                                        $bidOwnerSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))
                                            ->where('agent_user_id', data_get($bid, 'user_id'))
                                            ->first();
                                    @endphp
                                    @if($bidOwnerSummary)
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <a href="{{ route('accepted-bid-summary.view', $bidOwnerSummary->id) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="fa-solid fa-file-lines me-1"></i> View Accepted Bid Summary
                                        </a>
                                        @if(!$bidOwnerSummary->isAgentSigned())
                                        <a href="{{ route('accepted-bid-summary.sign-form', $bidOwnerSummary->id) }}" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-signature me-1"></i> Agent: E-Sign Acknowledgement
                                        </a>
                                        @endif
                                        @if($bidOwnerSummary->isFullySigned())
                                        <a href="{{ route('accepted-bid-summary.download-pdf', $bidOwnerSummary->id) }}" class="btn btn-success btn-sm">
                                            <i class="fa-solid fa-download me-1"></i> Download Signed PDF
                                        </a>
                                        @endif
                                    </div>
                                    @endif
                                    @endif
                                </div>
                                @endif

                                @endif
                                {{--
                                    Milestone 2 — the COMPETITOR SUMMARY @else branch was removed
                                    here. It rendered a competing agent's Offered Services and
                                    Terms Match counts plus a full Original/Counter match-score
                                    breakdown to any other agent viewing that bid. With the bid
                                    set now narrowed server-side the branch was already
                                    unreachable, but an unreachable competitor-disclosure branch
                                    is exactly the fragility this milestone exists to remove: the
                                    next person to widen the loop would silently re-enable it.
                                --}}

                            </div>
                            </div> {{-- End collapse div --}}
                        </div> {{-- End bid card --}}

                        {{-- ===== INLINE COUNTER BIDDING HISTORY ===== --}}
                        @php
                            $sellerCounterBids = \App\Models\SellerCounterTerm::with('meta', 'user')
                                ->where('seller_agent_auction_bid_id', data_get($bid, 'id'))
                                ->orderBy('created_at', 'desc')
                                ->get();
                            $showSellerCounterBids = ($isListingOwner || $isBidOwner);
                        @endphp

                        @if ($showSellerCounterBids && $sellerCounterBids->count() > 0)
                        <div class="counter-bids-section mt-4" id="counter-section-{{ data_get($bid, 'id') }}">
                            <div class="counter-bids-toggle"
                                style="cursor: pointer;"
                                onclick="event.stopPropagation(); var target = document.getElementById('counterBids{{ data_get($bid, 'id') }}'); var arrow = this.querySelector('.counter-arrow'); if(target.style.display === 'none' || target.style.display === '') { target.style.display = 'block'; arrow.style.transform = 'rotate(180deg)'; } else { target.style.display = 'none'; arrow.style.transform = 'rotate(0deg)'; }">
                                <div class="d-flex justify-content-between align-items-center flex-wrap p-2 border rounded">
                                    <h5 class="mb-0" style="color: #2c3e50;">Counter Bidding History</h5>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-secondary me-2">{{ $sellerCounterBids->count() }} counter offers</span>
                                        <span class="counter-arrow" style="transition: transform 0.3s;">↓</span>
                                    </div>
                                </div>
                            </div>

                            <div id="counterBids{{ data_get($bid, 'id') }}"
                                class="counter-bids-content"
                                style="display: none;"
                                aria-labelledby="counterBidsHeading{{ data_get($bid, 'id') }}">
                                <div class="accordion-body p-3 border border-top-0 rounded-bottom hla-counter-font">
                                    @foreach ($sellerCounterBids as $counterBid)
                                    @php
                                        $scIsOwner = data_get($auction, 'user_id') == $auth_id;
                                        $scIsAgent = data_get($bid, 'user_id') == $auth_id;
                                        $scIsCounterFromOwner = $counterBid->user_id == data_get($auction, 'user_id');
                                        $scIsCounterFromAgent = $counterBid->user_id == data_get($bid, 'user_id');

                                        $scRawBidState = data_get($bid, 'accepted', '0');
                                        $scBidState = in_array($scRawBidState, [null, 0, '0', 'no', 'pending'], true)
                                            ? '0'
                                            : (string) $scRawBidState;

                                        // Seller uses integer status: 1 = active/pending, 0 = rejected
                                        $scRawCounterState = data_get($counterBid, 'status', '0');
                                        // Normalize: 0 or '0' → rejected; 1 or '1' → pending ('0' in canonical form)
                                        $scCounterState = in_array((string)$scRawCounterState, ['0'], true)
                                            ? 'rejected'
                                            : (in_array((string)$scRawCounterState, ['1'], true) ? '0' : (string) $scRawCounterState);

                                        $scShowCounterActions = false;
                                        if ($scBidState === '0' && $scCounterState === '0') {
                                            if ($scIsOwner && $scIsCounterFromAgent) {
                                                $scShowCounterActions = true;
                                            }
                                            if ($scIsAgent && $scIsCounterFromOwner) {
                                                $scShowCounterActions = true;
                                            }
                                        }

                                        $scOwnerFirst = data_get($auction, 'user.first_name', '');
                                        $scOwnerLast  = data_get($auction, 'user.last_name', '');
                                        $scAgentFirst = data_get($bid, 'user.first_name', '');
                                        $scAgentLast  = data_get($bid, 'user.last_name', '');

                                        $scActorUserId = $scIsCounterFromOwner ? data_get($bid, 'user_id') : data_get($auction, 'user_id');
                                        $scActorFirst  = $scIsCounterFromOwner ? $scAgentFirst : $scOwnerFirst;
                                        $scActorLast   = $scIsCounterFromOwner ? $scAgentLast  : $scOwnerLast;

                                        $scCreatorFirst = data_get($counterBid, 'user.first_name', '');
                                        $scCreatorLast  = data_get($counterBid, 'user.last_name', '');

                                        // Get all meta for this counter term
                                        $scAllMeta = $counterBid->getAllMeta();

                                        // Changed badge helper: compare counter value against original bid
                                        $scIsChanged = function($counterVal, $origKey) use ($bid) {
                                            $origVal = data_get($bid, 'get.' . $origKey, null);
                                            $normalizeVal = function($v) {
                                                if (is_null($v) || $v === '') return '';
                                                if (is_array($v) || is_object($v)) return json_encode($v);
                                                $v = trim((string) $v);
                                                return preg_replace('/[\s$,%]/', '', strtolower($v));
                                            };
                                            return $normalizeVal($counterVal) !== $normalizeVal($origVal);
                                        };

                                        $scChangedStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                                        $scChangedBadge = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; vertical-align: middle;">Changed</span>';

                                        // Purchase fee display
                                        $scPurchaseFeeType = $scAllMeta['purchase_fee_type'] ?? '';
                                        $scPurchaseFeeDisplay = $scPurchaseFeeType;
                                        if ($scPurchaseFeeType === 'flat' && !empty($scAllMeta['purchase_fee_flat'])) {
                                            $scPurchaseFeeDisplay = '$' . number_format((float)preg_replace('/[^0-9.]/', '', $scAllMeta['purchase_fee_flat']), 2);
                                        } elseif ($scPurchaseFeeType === 'percentage' && !empty($scAllMeta['purchase_fee_percentage'])) {
                                            $scPurchaseFeeDisplay = rtrim(rtrim(number_format((float)preg_replace('/[^0-9.]/', '', $scAllMeta['purchase_fee_percentage']), 2), '0'), '.') . '% of Total Purchase Price';
                                        } elseif ($scPurchaseFeeType === 'combo') {
                                            $pctPart = !empty($scAllMeta['purchase_fee_percentage_combo']) ? (rtrim(rtrim(number_format((float)preg_replace('/[^0-9.]/', '', $scAllMeta['purchase_fee_percentage_combo']), 2), '0'), '.') . '% of Total Purchase Price') : null;
                                            $flatPart = !empty($scAllMeta['purchase_fee_flat_combo']) ? ('$' . number_format((float)preg_replace('/[^0-9.]/', '', $scAllMeta['purchase_fee_flat_combo']), 2)) : null;
                                            $scPurchaseFeeDisplay = implode(' + ', array_filter([$pctPart, $flatPart])) ?: $scPurchaseFeeType;
                                        } elseif ($scPurchaseFeeType === 'other' && !empty($scAllMeta['purchase_fee_other'])) {
                                            $scPurchaseFeeDisplay = $scAllMeta['purchase_fee_other'];
                                        }

                                        // ── Services diff (counter vs original bid) ──
                                        $scCtrSvcsRaw = is_string($scAllMeta['services'] ?? '') ? json_decode($scAllMeta['services'] ?? '', true) ?? [] : ($scAllMeta['services'] ?? []);
                                        $scCtrSvcsRaw = array_values(array_filter((array)$scCtrSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'));
                                        $scOtherSvcs  = is_string($scAllMeta['other_services'] ?? '') ? json_decode($scAllMeta['other_services'] ?? '', true) ?? [] : ($scAllMeta['other_services'] ?? []);
                                        $scOtherSvcs  = array_values(array_filter((array)$scOtherSvcs, fn($s) => is_string($s) && trim($s) !== ''));

                                        // Unicode-safe normalizer (matches main bid section normalizeStr)
                                        $scNormStr = function($s) {
                                            $s = (string)$s;
                                            // Convert literal \uXXXX escape sequences to actual Unicode (handles copy-pasted JSON strings in config)
                                            $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/i', fn($m) => mb_chr(hexdec($m[1]), 'UTF-8'), $s);
                                            // Normalize curly/smart quotes to straight equivalents
                                            $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
                                            $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
                                            return strtolower(trim($s));
                                        };

                                        // Build normalized lookup for counter services
                                        $scSelectedNormalized = [];
                                        foreach ($scCtrSvcsRaw as $svc) {
                                            $displaySvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                            $scSelectedNormalized[$scNormStr($svc)]        = $displaySvc;
                                            $scSelectedNormalized[$scNormStr($displaySvc)] = $displaySvc;
                                        }

                                        // Property type → config key
                                        $scBidPropTypeRaw = $scAllMeta['property_type'] ?? data_get($auction, 'get.property_type', 'Residential');
                                        $scPropNorm = strtolower(trim($scBidPropTypeRaw));
                                        if (str_contains($scPropNorm, 'income')) {
                                            $scBidPropKey = 'Income';
                                        } elseif (str_contains($scPropNorm, 'commercial')) {
                                            $scBidPropKey = 'Commercial';
                                        } elseif (str_contains($scPropNorm, 'business')) {
                                            $scBidPropKey = 'Business';
                                        } elseif (str_contains($scPropNorm, 'vacant') || str_contains($scPropNorm, 'land')) {
                                            $scBidPropKey = 'Vacant Land';
                                        } else {
                                            $scBidPropKey = 'Residential';
                                        }

                                        // Seller services config (same structure as main bid section)
                                        $scSellerServicesConfig = [
                                            'Residential' => [
                                                '📢 Property Marketing & Listing Promotion' => [
                                                    "List the property on the local Multiple Listing Service (MLS)",
                                                    "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                    "Create a branded flyer featuring the property\u2019s key highlights",
                                                    "Post the property on Facebook Marketplace",
                                                    "Post the property on Craigslist under the \"Homes for Sale\" category",
                                                    "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                                    "Promote the listing on Facebook in Real Estate or Community Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn in Professional or Real Estate Groups",
                                                    "Upload a TikTok video walkthrough of the property",
                                                    "Upload a YouTube video walkthrough of the property",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Listing Preparation & Presentation' => [
                                                    "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                    "Provide a custom listing preparation checklist",
                                                    "Collect property details and prepare MLS remarks and a public listing description",
                                                    "Provide a visual consultation for interior layout, cleanliness, and presentation",
                                                    "Provide a curb appeal consultation focused on exterior presentation",
                                                    "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made",
                                                ],
                                                '📸 Photography, Video & Virtual Media' => [
                                                    "Provide professional property photography",
                                                    "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                    "Provide a video walkthrough tour",
                                                    "Provide a 3D virtual tour",
                                                    "Provide virtual staging (digital enhancements only; no physical staging)",
                                                    "Provide digital photo enhancements",
                                                    "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                ],
                                                '🏡 Showings & Access Coordination' => [
                                                    "Ensure proper notice is provided if the property is occupied",
                                                    "Install a real estate sign on the property",
                                                    "Install a lockbox for Agent access",
                                                    "Schedule and attend showings with prospective Buyers",
                                                    "Coordinate showings with Buyer\u2019s Agents",
                                                    "Collect and relay showing feedback to the Seller",
                                                ],
                                                '📑 Offer & Contract Management' => [
                                                    "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                    "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                    "Negotiate price, terms, and contingencies with the Buyer\u2019s Agent or Buyer",
                                                    "Manage communications with the Buyer\u2019s Agent or Buyer",
                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                    "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Coordinate scheduling for inspections, appraisals, and other requested evaluations",
                                                    "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                    "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                    "Schedule and confirm the Final Walkthrough",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions",
                                                    "Provide general insight on local market trends, seasonal timing, and pricing thresholds",
                                                    "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                    "Provide general guidance on Seller obligations, required disclosures, and listing preparation",
                                                ],
                                            ],
                                            'Income' => [
                                                '📢 Property Marketing & Listing Promotion' => [
                                                    "List the property on the local Multiple Listing Service (MLS)",
                                                    "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                    "List the property on Crexi.com",
                                                    "List the property on LoopNet.com",
                                                    "Create a branded flyer with key rental data (e.g., unit mix, gross income, occupancy)",
                                                    "Post the property on Craigslist under the \"Multi-Family for Sale\" category",
                                                    "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                                    "Promote the listing on Facebook in Real Estate Investor or Multi-Family Buyer Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn in Investment or Real Estate Groups",
                                                    "Upload a TikTok video walkthrough of the property",
                                                    "Upload a YouTube video walkthrough of the property",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Listing Preparation & Investment Packaging' => [
                                                    "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                    "Provide a custom listing preparation checklist",
                                                    "Assist with assembling an income property packet, including rent roll, lease copies, and an income/expense summary (as available)",
                                                    "Provide a visual consultation focused on interior layout, cleanliness, and unit presentation",
                                                    "Provide a curb appeal consultation focused on exterior maintenance and first impressions",
                                                    "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made",
                                                ],
                                                '📸 Photography, Video & Virtual Media' => [
                                                    "Provide professional property photography",
                                                    "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                    "Provide a video walkthrough tour",
                                                    "Provide a 3D virtual tour",
                                                    "Provide virtual staging (digital enhancements only; no physical staging)",
                                                    "Provide digital photo enhancements",
                                                    "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                ],
                                                '🏘️ Showings & Access Coordination' => [
                                                    "Respond to Buyer inquiries and screen for general qualifications",
                                                    "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                                    "Ensure proper notice is provided if the property is occupied",
                                                    "Install a real estate sign on the property",
                                                    "Install a lockbox for Agent access",
                                                    "Schedule and attend showings with prospective Buyers",
                                                    "Coordinate showings with Buyer\u2019s Agents",
                                                    "Collect and relay showing feedback to the Seller",
                                                ],
                                                '📉 Offer & Contract Management' => [
                                                    "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                    "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                    "Negotiate deal structure, deposits, due diligence timelines, and Buyer contingencies",
                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                    "Manage communication with the Buyer\u2019s Agent or Buyers",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                    "Monitor contract contingencies, including financing, lease audits, estoppel review, insurance, inspections, and environmental reports",
                                                    "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals. Referrals only \u2014 no endorsement or warranty is made",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Review and organize due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                    "Coordinate with the Buyer\u2019s Agent, Buyer\u2019s Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                    "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed",
                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                    "Schedule and confirm the Final Walkthrough",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent income property sales, rental income trends, unit mix, and local investor activity",
                                                    "Assist in estimating Gross Rent Multiplier (GRM), Capitalization Rate (Cap Rate), or Price per Unit based on listing details and income property comparables",
                                                    "Provide general insight on likely Investor Buyer behavior, common value drivers, and investment strategies",
                                                    "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                    "Provide general guidance on lease transfers, rent proration, security deposits, and possession timelines",
                                                ],
                                            ],
                                            'Commercial' => [
                                                '📢 Property Marketing & Listing Promotion' => [
                                                    "List the property on the local Multiple Listing Service (MLS)",
                                                    "List the property on Crexi.com",
                                                    "List the property on LoopNet.com",
                                                    "Create a branded flyer summarizing the property\u2019s investment highlights and key selling points",
                                                    "Post the property on Craigslist under the \"Commercial for Sale\" category",
                                                    "Promote the listing on Facebook in Commercial or Investor Real Estate Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                                                    "Upload a TikTok video walkthrough of the property",
                                                    "Upload a YouTube video walkthrough of the property",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Listing Preparation & Asset Presentation' => [
                                                    "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                    "Provide a visual consultation on interior layout, cleanliness, and overall presentation",
                                                    "Provide a curb appeal consultation focused on exterior appearance and first impressions",
                                                    "Provide referrals to third-party vendors such as cleaners, handypeople, electricians, and landscapers (vendor fees billed separately; referrals only \u2014 no endorsement or warranty is made)",
                                                    "Compile essential marketing materials such as rent rolls, lease summaries, financial statements, and operating data (as available)",
                                                    "Organize zoning documentation, surveys, and public record reports (as available)",
                                                ],
                                                '📸 Photography, Video & Virtual Media' => [
                                                    "Provide professional property photography",
                                                    "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                    "Provide a video walkthrough tour",
                                                    "Provide a 3D virtual tour",
                                                    "Provide virtual staging (digital enhancements only; no physical staging)",
                                                    "Provide digital photo enhancements",
                                                    "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                ],
                                                '🏢 Showings & Access Coordination' => [
                                                    "Respond to Buyer inquiries and screen for general qualifications",
                                                    "Provide Non-Disclosure Agreement (NDA) templates for access to confidential documents or showings",
                                                    "Ensure proper notice is provided if the property is occupied",
                                                    "Install a real estate sign on the property",
                                                    "Install a lockbox for Agent access",
                                                    "Schedule and attend showings with prospective Buyers",
                                                    "Coordinate showings with Buyer\u2019s Agents",
                                                    "Collect and relay showing feedback to the Seller",
                                                ],
                                                '📉 Offer & Contract Management' => [
                                                    "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                    "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                    "Negotiate price, deal structure, deposits, and Buyer contingencies",
                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                    "Manage communications with the Buyer\u2019s Agent or Buyer",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                    "Assist with inspection, environmental, and due diligence negotiations",
                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                    "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                    "Compile and review relevant transaction documentation (as available)",
                                                    "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable commercial sales, current market conditions, and asset class trends",
                                                    "Provide general insight on local market trends, timing, and pricing thresholds",
                                                    "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                    "Provide general guidance on Seller obligations, required disclosures, and listing preparation",
                                                ],
                                            ],
                                            'Business' => [
                                                '📢 Business Marketing & Listing Promotion' => [
                                                    "List the business on BizBuySell.com",
                                                    "List the business on BizQuest.com",
                                                    "Promote the listing on Facebook in Business-for-Sale or Entrepreneur Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn targeting investors, entrepreneurs, and business buyers",
                                                    "Upload a TikTok video summarizing the business opportunity",
                                                    "Upload a YouTube video summarizing the business opportunity",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Business Listing Preparation & Packaging' => [
                                                    "Conduct a business overview consultation to understand operations, financials, and key selling points",
                                                    "Assist with assembling a business overview packet summarizing revenue, expenses, and key operations (based on information provided by Seller)",
                                                    "Provide guidance on organizing materials such as P&L summaries, tax returns, and operational documents for Buyer review",
                                                    "Provide referrals to Business Attorneys, CPAs, or Business Valuation experts (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🏢 Buyer Screenings & Meetings' => [
                                                    "Respond to Buyer inquiries and conduct preliminary screening conversations",
                                                    "Prepare and distribute a Non-Disclosure Agreement (NDA) prior to sharing sensitive business information",
                                                    "Coordinate and schedule Buyer meetings, walkthroughs, or virtual tours",
                                                    "Facilitate preliminary meetings between the Buyer and Seller (as appropriate)",
                                                ],
                                                '📑 Offer & Contract Management' => [
                                                    "Present all offers or Letters of Intent (LOIs) to the Seller and summarize key terms",
                                                    "Assist with negotiations on purchase price, deal structure, earnest money, and contingencies",
                                                    "Coordinate due diligence requests and document sharing between parties",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements and related documents to all parties",
                                                    "Monitor contract contingencies and key transaction milestones",
                                                    "Provide referrals to Business Attorneys, CPAs, or Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Coordinate with the Buyer\u2019s representatives, Business Attorney, and Escrow Officer to prepare for Closing",
                                                    "Assist with organizing final closing documents, transfer instructions, and bill of sale coordination",
                                                    "Confirm delivery of final executed agreements and relevant disclosures to all parties",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide general insight on local market demand, buyer profiles, and pricing expectations",
                                                    "Advise on positioning the business for sale, including presentation improvements and key value drivers",
                                                    "Recommend adjustments to pricing or marketing strategy if the business is not receiving sufficient interest",
                                                    "Provide general guidance on Seller obligations, confidentiality considerations, and transition planning",
                                                ],
                                            ],
                                            'Vacant Land' => [
                                                '📢 Property Marketing & Listing Promotion' => [
                                                    "List the property on the local Multiple Listing Service (MLS)",
                                                    "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                    "List the property on LandWatch.com",
                                                    "List the property on LandFlip.com",
                                                    "List the property on Lands of America",
                                                    "Create a branded flyer highlighting the land\u2019s key features and potential uses",
                                                    "Post the property on Craigslist under the \"Land for Sale\" category",
                                                    "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                                    "Promote the listing on Facebook in Real Estate or Land Buyer Groups",
                                                    "Share the listing on Instagram using posts, stories, or reels",
                                                    "Promote the listing on LinkedIn in Real Estate or Development Groups",
                                                    "Upload a TikTok video walkthrough or overview of the land",
                                                    "Upload a YouTube video walkthrough or overview of the land",
                                                    "Launch a mass email campaign promoting the listing",
                                                    "Distribute printed flyers or postcards in target geographic areas",
                                                    "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                ],
                                                '🛠️ Listing Preparation & Land Presentation' => [
                                                    "Conduct a site walkthrough and provide recommendations for listing readiness",
                                                    "Provide a custom land listing preparation checklist",
                                                    "Collect and organize available property information (e.g., zoning, utilities, access, surveys)",
                                                    "Prepare MLS remarks and a public listing description highlighting land use potential",
                                                    "Provide referrals to surveyors, engineers, or land use consultants (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '📸 Photography, Video & Virtual Media' => [
                                                    "Provide professional property photography",
                                                    "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                    "Provide a video walkthrough or site overview",
                                                    "Provide digital photo enhancements",
                                                ],
                                                '🔍 Showings & Site Access Coordination' => [
                                                    "Schedule and attend property showings with prospective Buyers",
                                                    "Coordinate showings with Buyer\u2019s Agents",
                                                    "Collect and relay showing feedback to the Seller",
                                                ],
                                                '📑 Offer & Contract Management' => [
                                                    "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                    "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                    "Negotiate price, terms, and contingencies with the Buyer\u2019s Agent or Buyer",
                                                    "Manage communications with the Buyer\u2019s Agent or Buyer",
                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                    "Monitor contract milestones, contingency periods, and financing or due diligence deadlines",
                                                    "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                ],
                                                '🧾 Closing Coordination & Transaction Management' => [
                                                    "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                    "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                    "Schedule and confirm the Closing Appointment",
                                                ],
                                                '💡 Selling Strategy & Guidance' => [
                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable land sales, local development activity, and market conditions",
                                                    "Provide general insight on local market trends, typical buyer profiles, and land use considerations",
                                                    "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                    "Provide general guidance on Seller obligations, required disclosures, and land listing considerations",
                                                ],
                                            ],
                                        ];
                                        $scPropConfig = $scSellerServicesConfig[$scBidPropKey] ?? $scSellerServicesConfig['Residential'];

                                        // Build flat config norm map for unmapped detection
                                        $scConfigFlatNorm = [];
                                        foreach ($scPropConfig as $scCatSvcs) {
                                            foreach ($scCatSvcs as $scS) {
                                                $scConfigFlatNorm[$scNormStr($scS)] = true;
                                            }
                                        }
                                        $scUnmappedSvcs = array_values(array_filter($scCtrSvcsRaw, fn($s) => !isset($scConfigFlatNorm[$scNormStr($s)])));

                                        // Diff: added (in counter but not in original bid)
                                        $scOrigBidSvcsRaw = (array) data_get($bid, 'get.services', []);
                                        if (is_string(data_get($bid, 'get.services', []))) $scOrigBidSvcsRaw = json_decode(data_get($bid, 'get.services', '[]'), true) ?: [];
                                        $scOrigBidSvcsRaw = array_values(array_filter($scOrigBidSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'));
                                        $scOrigBidSvcsNorm = array_map($scNormStr, $scOrigBidSvcsRaw);
                                        $scCtrSvcIsAdded = fn(string $s): bool => !in_array($scNormStr($s), $scOrigBidSvcsNorm, true);

                                        // Diff: removed (in original bid but not in counter)
                                        $scCtrSvcsNormFlat = array_map($scNormStr, $scCtrSvcsRaw);
                                        $scCtrRemovedSvcs = array_values(array_filter($scOrigBidSvcsRaw, fn($s) => !in_array($scNormStr($s), $scCtrSvcsNormFlat, true)));

                                        // Other services diff
                                        $scOrigOtherRaw = data_get($bid, 'get.other_services', []);
                                        if (is_string($scOrigOtherRaw)) $scOrigOtherRaw = json_decode($scOrigOtherRaw, true) ?: [];
                                        $scOrigOtherNorm = array_map(fn($s) => strtolower(trim((string)$s)), array_filter((array)$scOrigOtherRaw, fn($s) => is_string($s) && trim($s) !== ''));
                                        $scOtherIsAdded = fn(string $s): bool => !in_array(strtolower(trim($s)), $scOrigOtherNorm, true);
                                        $scOtherRemovedDisplay = array_values(array_filter(
                                            (array)$scOrigOtherRaw,
                                            fn($s) => is_string($s) && trim($s) !== '' && !in_array(strtolower(trim($s)), array_map(fn($x) => strtolower(trim((string)$x)), $scOtherSvcs), true)
                                        ));

                                        // === Format helpers ===
                                        $scFmtMoney = function($v) {
                                            if (empty($v)) return null;
                                            $c = preg_replace('/[^0-9.\-]/', '', (string)$v);
                                            if ($c === '' || !is_numeric($c)) return null;
                                            return '$' . number_format((float)$c, 2);
                                        };
                                        $scFmtPct = function($v) {
                                            if (empty($v)) return null;
                                            $c = preg_replace('/[^0-9.\-]/', '', (string)$v);
                                            if ($c === '' || !is_numeric($c)) return null;
                                            return rtrim(rtrim(number_format((float)$c, 2), '0'), '.') . '%';
                                        };

                                        // === A) Buyer's Broker Commission Fee ===
                                        $scCommStructType = $scAllMeta['commission_structure_type'] ?? '';
                                        $scBuyerBrokerFee = null;
                                        if ($scCommStructType === 'Flat Fee' && !empty($scAllMeta['commission_structure_type_fee_flat'])) {
                                            $scBuyerBrokerFee = $scFmtMoney($scAllMeta['commission_structure_type_fee_flat']);
                                        } elseif ($scCommStructType === 'Percentage of the Total Purchase Price' && !empty($scAllMeta['commission_structure_type_fee_percentage'])) {
                                            $scBuyerBrokerFee = $scFmtPct($scAllMeta['commission_structure_type_fee_percentage']) . ' of Total Purchase Price';
                                        } elseif ($scCommStructType === 'Flat Fee + Percentage' && (!empty($scAllMeta['commission_structure_type_fee_flat_combo']) || !empty($scAllMeta['commission_structure_type_fee_percentage_combo']))) {
                                            $bbfParts = [];
                                            if (!empty($scAllMeta['commission_structure_type_fee_percentage_combo'])) $bbfParts[] = ($scFmtPct($scAllMeta['commission_structure_type_fee_percentage_combo']) ?? '') . ' of Total Purchase Price';
                                            if (!empty($scAllMeta['commission_structure_type_fee_flat_combo'])) $bbfParts[] = $scFmtMoney($scAllMeta['commission_structure_type_fee_flat_combo']);
                                            $scBuyerBrokerFee = implode(' + ', array_filter($bbfParts)) ?: null;
                                        } elseif (strtolower($scCommStructType) === 'other' && !empty($scAllMeta['commission_structure_type_fee_other'])) {
                                            $scBuyerBrokerFee = $scAllMeta['commission_structure_type_fee_other'];
                                        } elseif ($scCommStructType) {
                                            $scBuyerBrokerFee = $scCommStructType;
                                        }

                                        // === B) Seller's Broker Leasing Fee ===
                                        $scLeasingFeeType = $scAllMeta['seller_leasing_fee_type'] ?? '';
                                        $scLeasingFeeDisplay = null;
                                        if ($scLeasingFeeType === 'Flat Fee' && !empty($scAllMeta['seller_leasing_gross_purchase_fee_flat_amount'])) {
                                            $scLeasingFeeDisplay = $scFmtMoney($scAllMeta['seller_leasing_gross_purchase_fee_flat_amount']);
                                        } elseif ($scLeasingFeeType === 'Percentage of the Gross Lease Value' && !empty($scAllMeta['seller_leasing_gross'])) {
                                            $scLeasingFeeDisplay = $scFmtPct($scAllMeta['seller_leasing_gross']) . ' of the Gross Lease Value';
                                        } elseif ($scLeasingFeeType === 'Percentage of the Rent Due Each Rental Period' && !empty($scAllMeta['seller_leasing_gross_rental'])) {
                                            $scLeasingFeeDisplay = $scFmtPct($scAllMeta['seller_leasing_gross_rental']) . ' of the Rent Due Each Rental Period';
                                        } elseif ($scLeasingFeeType === "Percentage of the First Month's Rent" && !empty($scAllMeta['seller_leasing_gross_month_rent'])) {
                                            $scLeasingFeeDisplay = $scFmtPct($scAllMeta['seller_leasing_gross_month_rent']) . " of the First Month's Rent";
                                        } elseif ($scLeasingFeeType === "Percentage of Month's Rent" && !empty($scAllMeta['seller_leasing_gross_month_rent'])) {
                                            $scLeasingFeeDisplay = $scFmtPct($scAllMeta['seller_leasing_gross_month_rent']) . " of Month's Rent";
                                            if (!empty($scAllMeta['seller_leasing_gross_no_of_months']) && $scAllMeta['seller_leasing_gross_no_of_months'] != 'null') {
                                                $scLeasingFeeDisplay .= ' x ' . intval($scAllMeta['seller_leasing_gross_no_of_months']) . ' Months';
                                            }
                                        } elseif ($scLeasingFeeType === 'Percentage of Net Aggregate Rent') {
                                            $scNetAggVal = $scAllMeta['seller_leasing_gross_other'] ?? $scAllMeta['seller_leasing_gross'] ?? null;
                                            if ($scNetAggVal) $scLeasingFeeDisplay = $scFmtPct($scNetAggVal) . ' of Net Aggregate Rent';
                                        } elseif ($scLeasingFeeType === 'Percentage of Gross Rent') {
                                            $scGrossRentVal = $scAllMeta['seller_leasing_gross_percentage'] ?? $scAllMeta['seller_leasing_gross_ross_percentage_rent'] ?? null;
                                            if ($scGrossRentVal) $scLeasingFeeDisplay = $scFmtPct($scGrossRentVal) . ' of Gross Rent';
                                        } elseif ($scLeasingFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                                            $scLfParts = [];
                                            if (!empty($scAllMeta['seller_leasing_gross_flat_combo'])) $scLfParts[] = $scFmtMoney($scAllMeta['seller_leasing_gross_flat_combo']);
                                            if (!empty($scAllMeta['seller_leasing_gross_percentage_combo'])) $scLfParts[] = $scFmtPct($scAllMeta['seller_leasing_gross_percentage_combo']) . ' of Gross Lease Value';
                                            $scLeasingFeeDisplay = implode(' + ', array_filter($scLfParts)) ?: null;
                                        } elseif ($scLeasingFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent') {
                                            $scLfParts = [];
                                            if (!empty($scAllMeta['seller_leasing_gross_flat_net_combo'])) $scLfParts[] = $scFmtMoney($scAllMeta['seller_leasing_gross_flat_net_combo']);
                                            if (!empty($scAllMeta['seller_leasing_gross_percentage_net_combo'])) $scLfParts[] = $scFmtPct($scAllMeta['seller_leasing_gross_percentage_net_combo']) . ' of Net Aggregate Rent';
                                            $scLeasingFeeDisplay = implode(' + ', array_filter($scLfParts)) ?: null;
                                        } elseif (strtolower($scLeasingFeeType) === 'other' && !empty($scAllMeta['seller_leasing_gross_purchase_fee_other'])) {
                                            $scLeasingFeeDisplay = $scAllMeta['seller_leasing_gross_purchase_fee_other'];
                                        } elseif ($scLeasingFeeType) {
                                            $scLeasingFeeDisplay = $scLeasingFeeType;
                                        }

                                        // === C) Lease-Option Term fee displays ===
                                        $scLeaseValue   = $scAllMeta['lease_value'] ?? '';
                                        $scLeaseType2   = $scAllMeta['lease_type'] ?? '';
                                        $scPurchaseValue = $scAllMeta['purchase_value'] ?? '';
                                        $scPurchaseType2 = $scAllMeta['purchase_type'] ?? '';
                                        $scLeaseOptionFee = null;
                                        if ($scLeaseValue) {
                                            if (in_array($scLeaseType2, ['%', 'percent']) || str_contains((string)$scLeaseValue, '%')) {
                                                $scLeaseOptionFee = str_replace('%', '', $scLeaseValue) . '% of Total Purchase Price';
                                            } else {
                                                $scLeaseOptionFee = $scFmtMoney($scLeaseValue);
                                            }
                                        }
                                        $scPurchaseOptFee = null;
                                        if ($scPurchaseValue) {
                                            if (in_array($scPurchaseType2, ['%', 'percent']) || str_contains((string)$scPurchaseValue, '%')) {
                                                $scPurchaseOptFee = str_replace('%', '', $scPurchaseValue) . '% of Total Purchase Price';
                                            } else {
                                                $scPurchaseOptFee = $scFmtMoney($scPurchaseValue);
                                            }
                                        }

                                        // === D) Legal Terms fields ===
                                        $scEarlyTermAmt = $scAllMeta['early_termination_fee_amount'] ?? '';
                                        $scRetainerAmt  = $scAllMeta['retainer_fee_amount'] ?? '';
                                        $scRetainerApp  = $scAllMeta['retainer_fee_application'] ?? '';
                                        $scRetainedDep  = $scAllMeta['retained_deposits'] ?? '';
                                        $scAgencyTfDisplay = strtolower(trim($scAllMeta['agency_agreement_timeframe'] ?? '')) === 'other'
                                            ? ($scAllMeta['agency_agreement_custom'] ?? 'Other')
                                            : ($scAllMeta['agency_agreement_timeframe'] ?? '');

                                        $scHasBrokerComp =
                                            !empty($scAllMeta['purchase_fee_type']) || !empty($scAllMeta['commission_structure']) ||
                                            !empty($scAllMeta['nominal']) || !empty($scAllMeta['commission_structure_type']) ||
                                            !empty($scAllMeta['interested_purchase_fee_type']) || !empty($scAllMeta['interested_lease_option_agreement']) ||
                                            !empty($scAllMeta['protection_period']) || !empty($scAllMeta['early_termination_fee_option']) ||
                                            !empty($scAllMeta['retainer_fee_option']) || !empty($scAllMeta['retained_deposits']) ||
                                            !empty($scAllMeta['agency_agreement_timeframe']) || !empty($scAllMeta['brokerage_relationship']) ||
                                            !empty($scAllMeta['additional_details_broker']) || !empty($scAllMeta['additional_details']);
                                    @endphp

                                    <div class="counter-bid-card mb-3 p-3 border rounded mt-2">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                            <h6 class="mb-0">
                                                @if ($counterBid->user_id == Auth::id())
                                                    Your Counter Offer
                                                @else
                                                    Counter Offer from {{ data_get($counterBid, 'user.first_name') }} {{ data_get($counterBid, 'user.last_name') }}
                                                @endif
                                            </h6>
                                            <small class="text-muted">{{ optional($counterBid->created_at)->format('M j, Y g:i A') }}</small>
                                        </div>

                                        {{-- ── Broker Compensation & Agency Agreement Terms ── --}}
                                        @if ($scHasBrokerComp)
                                        <div class="mb-4">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa-solid fa-handshake me-2"></i>Broker Compensation &amp; Agency Agreement Terms
                                            </h6>

                                            {{-- A) Broker Compensation --}}
                                            @if (!empty($scAllMeta['purchase_fee_type']) || !empty($scAllMeta['commission_structure']) || !empty($scAllMeta['nominal']) || !empty($scAllMeta['commission_structure_type']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">A) Broker Compensation</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @if (!empty($scAllMeta['purchase_fee_type']))
                                                    @php $scPFChg = $scIsChanged($scAllMeta['purchase_fee_type'], 'purchase_fee_type'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scPFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Seller's Broker Purchase Fee:</span> {{ $scPurchaseFeeDisplay }}{!! $scPFChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['nominal']))
                                                    @php $scNomChg = $scIsChanged($scAllMeta['nominal'], 'nominal'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scNomChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Nominal Consideration Fee:</span> {{ $scFmtMoney($scAllMeta['nominal']) }}{!! $scNomChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['commission_structure']))
                                                    @php $scCSChg = $scIsChanged($scAllMeta['commission_structure'], 'commission_structure'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scCSChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ $scAllMeta['commission_structure'] }}{!! $scCSChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['commission_structure_type']) && $scBuyerBrokerFee)
                                                    @php $scCSTChg = $scIsChanged($scAllMeta['commission_structure_type'], 'commission_structure_type'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scCSTChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Buyer's Broker Commission Fee:</span> {{ $scBuyerBrokerFee }}{!! $scCSTChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- B) Lease Terms --}}
                                            @if (!empty($scAllMeta['interested_purchase_fee_type']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">B) Lease Terms</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scIPFTChg = $scIsChanged($scAllMeta['interested_purchase_fee_type'], 'interested_purchase_fee_type'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scIPFTChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Interested in Offering a Lease Agreement:</span> {{ $scAllMeta['interested_purchase_fee_type'] }}{!! $scIPFTChg ? $scChangedBadge : '' !!}</li>
                                                    @if (strtolower(trim($scAllMeta['interested_purchase_fee_type'])) === 'yes' && $scLeasingFeeDisplay)
                                                    @php $scLFChg = $scIsChanged($scLeasingFeeType, 'seller_leasing_fee_type'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scLFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Seller's Broker Leasing Fee:</span> {{ $scLeasingFeeDisplay }}{!! $scLFChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- C) Lease-Option Terms --}}
                                            @if (!empty($scAllMeta['interested_lease_option_agreement']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">C) Lease-Option Terms</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scILOAChg = $scIsChanged($scAllMeta['interested_lease_option_agreement'], 'interested_lease_option_agreement'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scILOAChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Interested in Lease-Option Agreement:</span> {{ $scAllMeta['interested_lease_option_agreement'] }}{!! $scILOAChg ? $scChangedBadge : '' !!}</li>
                                                    @if (strtolower(trim($scAllMeta['interested_lease_option_agreement'])) === 'yes')
                                                        @if ($scLeaseOptionFee)
                                                        @php $scLOFChg = $scIsChanged($scLeaseType2, 'lease_type'); @endphp
                                                        <li class="mb-1" style="font-size: 12px; {{ $scLOFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Compensation for Creating Lease-Option Agreement:</span> {{ $scLeaseOptionFee }}{!! $scLOFChg ? $scChangedBadge : '' !!}</li>
                                                        @endif
                                                        @if ($scPurchaseOptFee)
                                                        @php $scPOFChg = $scIsChanged($scPurchaseType2, 'purchase_type'); @endphp
                                                        <li class="mb-1" style="font-size: 12px; {{ $scPOFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> {{ $scPurchaseOptFee }}{!! $scPOFChg ? $scChangedBadge : '' !!}</li>
                                                        @endif
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- D) Legal Terms --}}
                                            @if (!empty($scAllMeta['early_termination_fee_option']) || !empty($scAllMeta['retainer_fee_option']) || $scRetainedDep || !empty($scAllMeta['protection_period']) || !empty($scAllMeta['agency_agreement_timeframe']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">D) Legal Terms</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @if (!empty($scAllMeta['early_termination_fee_option']))
                                                    @php $scETFChg = $scIsChanged($scAllMeta['early_termination_fee_option'], 'early_termination_fee_option'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scETFChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ ucfirst($scAllMeta['early_termination_fee_option']) }}{!! $scETFChg ? $scChangedBadge : '' !!}</li>
                                                    @if (strtolower($scAllMeta['early_termination_fee_option']) === 'yes' && $scEarlyTermAmt)
                                                    @php $scETAChg = $scIsChanged($scEarlyTermAmt, 'early_termination_fee_amount'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scETAChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $scFmtMoney($scEarlyTermAmt) }}{!! $scETAChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @endif
                                                    @if (!empty($scAllMeta['retainer_fee_option']))
                                                    @php $scRFOChg = $scIsChanged($scAllMeta['retainer_fee_option'], 'retainer_fee_option'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRFOChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Retainer Fee:</span> {{ ucfirst($scAllMeta['retainer_fee_option']) }}{!! $scRFOChg ? $scChangedBadge : '' !!}</li>
                                                    @if (strtolower($scAllMeta['retainer_fee_option']) === 'yes' && $scRetainerAmt)
                                                    @php $scRAChg = $scIsChanged($scRetainerAmt, 'retainer_fee_amount'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRAChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Retainer Fee Amount:</span> {{ $scFmtMoney($scRetainerAmt) }}{!! $scRAChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (strtolower($scAllMeta['retainer_fee_option'] ?? '') === 'yes' && $scRetainerApp)
                                                    @php $scRAppChg = $scIsChanged($scRetainerApp, 'retainer_fee_application'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRAppChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Retainer Fee Application:</span> {{ $scRetainerApp }}{!! $scRAppChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @endif
                                                    @if ($scRetainedDep)
                                                    @php $scRDChg = $scIsChanged($scRetainedDep, 'retained_deposits'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRDChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Seller's Broker's Share of Retained Deposits:</span> {{ $scFmtPct($scRetainedDep) }}{!! $scRDChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['protection_period']))
                                                    @php $scPPChg = $scIsChanged($scAllMeta['protection_period'], 'protection_period'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scPPChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ $scAllMeta['protection_period'] }} days{!! $scPPChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                    @if (!empty($scAllMeta['agency_agreement_timeframe']))
                                                    @php $scATChg = $scIsChanged($scAllMeta['agency_agreement_timeframe'], 'agency_agreement_timeframe'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scATChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Seller Agency Agreement Timeframe:</span> {{ $scAgencyTfDisplay }}{!! $scATChg ? $scChangedBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- E) Brokerage Relationship --}}
                                            @if (!empty($scAllMeta['brokerage_relationship']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">E) Brokerage Relationship</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scBRChg = $scIsChanged($scAllMeta['brokerage_relationship'], 'brokerage_relationship'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scBRChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $scAllMeta['brokerage_relationship'] }}{!! $scBRChg ? $scChangedBadge : '' !!}</li>
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- F) Additional Terms --}}
                                            @if (!empty($scAllMeta['additional_details_broker']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">F) Additional Terms</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scADBChg = $scIsChanged($scAllMeta['additional_details_broker'], 'additional_details_broker'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scADBChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Additional Terms:</span> {{ $scAllMeta['additional_details_broker'] }}{!! $scADBChg ? $scChangedBadge : '' !!}</li>
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- G) Referral Fee --}}
                                            @if ($auction->isCreatedByAgent() && !empty($scAllMeta['referral_fee_percent']))
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">G) Referral Fee</div>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @php $scRefFeeChg = $scIsChanged($scAllMeta['referral_fee_percent'], 'referral_fee_percent'); @endphp
                                                    <li class="mb-1" style="font-size: 12px; {{ $scRefFeeChg ? $scChangedStyle : '' }}"><span class="fw-semibold">Referral Fee (%):</span> {{ $scAllMeta['referral_fee_percent'] }}%{!! $scRefFeeChg ? $scChangedBadge : '' !!}</li>
                                                </ul>
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        {{-- Additional Details --}}
                                        @if (!empty($scAllMeta['additional_details']))
                                        <div class="mb-3">
                                            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;"><i class="fa-solid fa-circle-info me-1"></i>Additional Details</div>
                                            @php $scADChg = $scIsChanged($scAllMeta['additional_details'], 'additional_details'); @endphp
                                            <div class="ps-3" style="font-size: 12px; {{ $scADChg ? $scChangedStyle : '' }}">{{ $scAllMeta['additional_details'] }}{!! $scADChg ? $scChangedBadge : '' !!}</div>
                                        </div>
                                        @endif

                                        {{-- Services Offered (Tenant-pattern: direct config loop) --}}
                                        @if (!empty($scCtrSvcsRaw) || !empty($scOtherSvcs))
                                        <div class="mb-5">
                                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                <i class="fa-solid fa-clipboard-list me-2"></i>Offered Services
                                            </h6>

                                            @foreach ($scPropConfig as $scCategory => $scCatSvcs)
                                                @php
                                                $scSelectedInCat = array_filter($scCatSvcs, fn($s) => isset($scSelectedNormalized[$scNormStr($s)]));
                                                @endphp
                                                @if (count($scSelectedInCat) > 0)
                                                <div class="mb-3">
                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $scCategory }}</div>
                                                    <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                        @foreach ($scCatSvcs as $scService)
                                                            @php
                                                            $scServiceNorm = $scNormStr($scService);
                                                            $scServiceDisplay = $scSelectedNormalized[$scServiceNorm] ?? null;
                                                            @endphp
                                                            @if ($scServiceDisplay !== null)
                                                                @if ($scCtrSvcIsAdded($scServiceDisplay))
                                                                <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                                    <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $scServiceDisplay }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                                </li>
                                                                @else
                                                                <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $scServiceDisplay }}</li>
                                                                @endif
                                                                @if (in_array(strtolower(trim($scService)), ['provide digital photo enhancements', 'provide digital enhancements to media assets']))
                                                                @php
                                                                    $scCtrPhotoEnhRaw = $scAllMeta['photo_enhancements'] ?? [];
                                                                    if (is_string($scCtrPhotoEnhRaw)) $scCtrPhotoEnhRaw = json_decode($scCtrPhotoEnhRaw, true) ?: [];
                                                                    $scCtrCustomEnh = $scAllMeta['custom_enhancement'] ?? '';
                                                                    $scEnhOrder = ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'];
                                                                @endphp
                                                                @if (!empty($scCtrPhotoEnhRaw))
                                                                <ul style="padding-left: 1.5rem; margin: 4px 0; list-style: disc;">
                                                                    @foreach ($scEnhOrder as $scEnh)
                                                                        @if (in_array($scEnh, $scCtrPhotoEnhRaw))
                                                                            @if ($scEnh === 'Other' && !empty($scCtrCustomEnh))
                                                                                <li style="font-size: 0.85rem;">{{ $scCtrCustomEnh }}</li>
                                                                            @elseif ($scEnh !== 'Other')
                                                                                <li style="font-size: 0.85rem;">{{ $scEnh }}</li>
                                                                            @endif
                                                                        @endif
                                                                    @endforeach
                                                                </ul>
                                                                @endif
                                                                @endif
                                                            @endif
                                                        @endforeach
                                                    </ul>
                                                </div>
                                                @endif
                                            @endforeach

                                            @if (!empty($scUnmappedSvcs))
                                            <div class="mb-3">
                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                    @foreach ($scUnmappedSvcs as $scUnmappedSvc)
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $scCtrSvcIsAdded((string)$scUnmappedSvc) ? 'background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;' : '' }}">
                                                        @if ($scCtrSvcIsAdded((string)$scUnmappedSvc))<i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>@endif{{ $scUnmappedSvc }}@if ($scCtrSvcIsAdded((string)$scUnmappedSvc)) <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>@endif
                                                    </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif

                                            @if (!empty($scOtherSvcs))
                                            <div class="mb-3">
                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                    @foreach ($scOtherSvcs as $scOtherSvc)
                                                    @if ($scOtherIsAdded($scOtherSvc))
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                        <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $scOtherSvc }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                    </li>
                                                    @else
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $scOtherSvc }}</li>
                                                    @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- Removed Services --}}
                                            @if (!empty($scCtrRemovedSvcs) || !empty($scOtherRemovedDisplay))
                                            <div class="mb-3 mt-2 p-3" style="background-color: #fff5f5; border-radius: 6px; border: 1px solid #f5c6cb;">
                                                <div class="fw-bold mb-1" style="color: #dc3545; font-size: 0.95rem;">
                                                    <i class="fa-solid fa-minus-circle me-1"></i>Removed Services
                                                </div>
                                                <ul class="services mb-0" style="margin-top: 0.5rem; padding-left: 1.2rem; list-style: none;">
                                                    @foreach ($scCtrRemovedSvcs as $svc)
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>{{ $svc }}
                                                    </li>
                                                    @endforeach
                                                    @foreach ($scOtherRemovedDisplay as $svc)
                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>{{ $svc }}
                                                    </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        {{-- Counter action banner (link to View Counter Terms page where actions live) --}}
                                        @if ($scShowCounterActions)
                                        <div class="mt-3 pt-3 border-top">
                                            <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                                <i class="fa-solid fa-right-left me-1"></i>
                                                @if ($scIsCounterFromOwner)
                                                    {{ trim($scOwnerFirst . ' ' . $scOwnerLast) }} has submitted a counter offer.
                                                @else
                                                    {{ trim($scAgentFirst . ' ' . $scAgentLast) }} has submitted a counter offer.
                                                @endif
                                            </div>
                                            <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                                <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                                </a>
                                                @if ($scIsOwner)
                                                <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                                </a>
                                                @endif
                                            </div>
                                        </div>
                                        @endif

                                        {{-- Counter footer status --}}
                                        <div class="mt-3 pt-3 border-top">
                                            @if ($scCounterState === 'accepted')
                                            @if (Auth::id() == $scActorUserId)
                                            <div class="alert alert-success mb-0 py-1 small">
                                                ✅ This counter bid has been accepted.
                                            </div>
                                            @else
                                            <div class="alert alert-success mb-0 py-1 small">
                                                ✅ {{ trim($scActorFirst . ' ' . $scActorLast) }} accepted the counter bid.
                                            </div>
                                            @endif
                                            @elseif ($scCounterState === 'rejected')
                                            @if (Auth::id() == $scActorUserId)
                                            <div class="alert alert-danger mb-0 py-1 small">
                                                ❌ This counter bid has been rejected.
                                            </div>
                                            @else
                                            <div class="alert alert-danger mb-0 py-1 hla-alert-font">
                                                ❌ {{ trim($scActorFirst . ' ' . $scActorLast) }} rejected the counter bid.
                                            </div>
                                            @endif
                                            @elseif ($scCounterState === '0')
                                            @if ($counterBid->user_id == Auth::id())
                                            <div class="alert alert-secondary mb-0 py-1 small">
                                                ⏳ Waiting for response from {{ $scIsCounterFromOwner ? trim($scAgentFirst . ' ' . $scAgentLast) : trim($scOwnerFirst . ' ' . $scOwnerLast) }}...
                                            </div>
                                            @else
                                            <div class="alert alert-light mb-0 py-1 small" style="font-size:13px;">
                                                ⏳ Counter bid from {{ trim($scCreatorFirst . ' ' . $scCreatorLast) }} is pending.
                                            </div>
                                            @endif
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                        {{-- ===== END INLINE COUNTER BIDDING HISTORY ===== --}}

                        @if ($isListingOwner || $isBidOwner)
                        {{-- Private Data Modal - visible to listing owner OR bid owner (agent) --}}
                        @php
                            $rawState   = data_get($bid, 'accepted');
                            $_isTerminal = in_array((string)$rawState, ['accepted', 'rejected'], true);
                            $state      = (!$_isTerminal && $hasSellerCounter) ? 'countered' : ((!$rawState || $rawState === '0') ? '0' : (string) $rawState);
                            $isOwnerRow = ($auth_id == data_get($auction, 'user_id'));
                            $ownerFirst = data_get($auction, 'user.first_name', '');
                            $ownerLast  = data_get($auction, 'user.last_name', '');
                            $agentFirst = data_get($bid, 'user.first_name', '');
                            $agentLast  = data_get($bid, 'user.last_name', '');
                            $ownerId    = data_get($auction, 'user_id');
                        @endphp
                        <div class="modal fade"
                             id="privateDataModal{{ data_get($bid, 'id') }}"
                             tabindex="-1"
                             aria-labelledby="privateDataModalLabel{{ data_get($bid, 'id') }}"
                             aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content" style="border-radius: 10px; border: none;">
                                    <div class="modal-header text-white"
                                         style="background: #049399; border-bottom: none; padding: 20px;">
                                        <h5 class="modal-title"
                                            id="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                            style="font-weight: 600;">
                                            <i class="fa-solid fa-lock me-2"></i> Private Compensation &amp; Agreement Terms
                                        </h5>
                                    </div>
                                    <div class="modal-body" style="background: #fafafa; padding: 25px;">
                                        @include('partials.bid_detail_body.seller')
                                    </div>{{-- End modal-body --}}

                                    {{-- ===== TENANT-STYLE MODAL FOOTER ===== --}}
                                    <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; flex-wrap: wrap; gap: 12px;">

                                        {{-- Confidential notice --}}
                                        <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                            <i class="fa-solid fa-shield-halved me-2"></i>
                                            <strong>Confidential:</strong> This information is private and only visible to you.
                                        </div>

                                        {{-- ── Listing owner: action buttons when bid is undecided ── --}}
                                        @if ($state === '0' && $isOwnerRow && !in_array(data_get($auction, 'is_sold'), [true,'true',1,'1'], true))
                                            {{-- Milestone 3: was ($isTraditionalListing && $isExpired). The listing-type
                                                 qualifier is gone with the timer — expiry is expiry, whatever the old
                                                 auction_type said. Owner review of the proposal itself is unaffected. --}}
                                            @if ($isExpired)
                                            <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                                                <i class="fa-solid fa-calendar-xmark me-1"></i> Listing has expired — no further actions available. You can extend the expiration date by editing the listing.
                                            </div>
                                            @else
                                            <div class="d-flex gap-3 justify-content-center align-items-center w-100" style="flex-wrap: nowrap;">
                                                <form action="{{ route('acceptSABid') }}" method="post" style="margin: 0;"
                                                      onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
                                                    @csrf
                                                    <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                    <button type="submit" class="btn btn-success btn-accept" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="fa-solid fa-check me-1"></i> Accept Bid
                                                    </button>
                                                </form>
                                                <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}"
                                                   class="btn btn-primary btn-counter" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                    <i class="fa-solid fa-right-left me-1"></i> Counter Bid
                                                </a>
                                                <form action="{{ route('rejectSABid') }}" method="post" style="margin: 0;"
                                                      onsubmit="return confirm('Are you sure you want to reject this bid?');">
                                                    @csrf
                                                    <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                    <button type="submit" class="btn btn-danger btn-reject" style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="fa-solid fa-xmark me-1"></i> Reject Bid
                                                    </button>
                                                </form>
                                            </div>
                                            @endif
                                        @endif

                                        {{-- ── Accepted state ── --}}
                                        @if ($state === 'accepted')
                                        <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
                                            <i class="fa-solid fa-circle-check me-1"></i>
                                            @if (Auth::id() == $ownerId)
                                                This bid has been accepted.
                                            @else
                                                {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted this bid.
                                            @endif
                                        </div>
                                        @php
                                            $absFooterBidSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))
                                                ->where('agent_user_id', data_get($bid, 'user_id'))
                                                ->first();
                                        @endphp
                                        @if ($absFooterBidSummary && (Auth::id() == $ownerId || data_get($bid, 'user_id') == Auth::id()))
                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                            <a href="{{ route('accepted-bid-summary.view', $absFooterBidSummary->id) }}" class="btn btn-outline-primary btn-sm">
                                                <i class="fa-solid fa-file-lines me-1"></i> View Accepted Bid Summary
                                            </a>
                                            @if (data_get($bid, 'user_id') == Auth::id() && !$absFooterBidSummary->isAgentSigned())
                                            <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-signature me-1"></i> E-Sign Acknowledgement
                                            </a>
                                            @endif
                                            @if (Auth::id() == $ownerId && !$absFooterBidSummary->isOwnerSigned())
                                            <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-signature me-1"></i> Seller: E-Sign Acknowledgement
                                            </a>
                                            @endif
                                            @if ($absFooterBidSummary->isFullySigned())
                                            <a href="{{ route('accepted-bid-summary.download-pdf', $absFooterBidSummary->id) }}" class="btn btn-success btn-sm">
                                                <i class="fa-solid fa-download me-1"></i> Download Signed PDF
                                            </a>
                                            @endif
                                        </div>
                                        @endif

                                        {{-- ── Rejected state ── --}}
                                        @elseif ($state === 'rejected')
                                        <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
                                            <i class="fa-solid fa-circle-xmark me-1"></i>
                                            @if (Auth::id() == $ownerId)
                                                This bid has been rejected.
                                            @else
                                                {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected this bid.
                                            @endif
                                        </div>

                                        {{-- ── Countered state ── --}}
                                        @elseif ($state === 'countered')
                                        @php $scFooterLatestFromOwner = $latestCounter && ($latestCounter->user_id == $ownerId); @endphp
                                        <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                            <i class="fa-solid fa-right-left me-1"></i>
                                            @if (($scFooterLatestFromOwner && Auth::id() == $ownerId) || (!$scFooterLatestFromOwner && Auth::id() != $ownerId))
                                                <strong>Counter Offer Sent.</strong>
                                            @else
                                                <strong>Counter Offer Received.</strong>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                            @if (($scFooterLatestFromOwner && Auth::id() == $ownerId) || (!$scFooterLatestFromOwner && Auth::id() != $ownerId))
                                            {{-- Viewer sent latest counter — show View CT + Edit CT --}}
                                            <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                            </a>
                                            <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                            </a>
                                            @else
                                            {{-- Other party sent latest: View CT only — actions on View Counter Terms page --}}
                                            <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                            </a>
                                            @endif
                                        </div>

                                        {{-- ── Pending state ── --}}
                                        @elseif ($state === '0')
                                        @if (data_get($bid, 'user_id') == Auth::id())
                                        <div class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                            ⏳ Waiting for a response from {{ trim($ownerFirst . ' ' . $ownerLast) }}...
                                        </div>
                                        @else
                                        <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
                                            ⏳ Bid from {{ trim($agentFirst . ' ' . $agentLast) }} is pending.
                                        </div>
                                        @endif
                                        @endif

                                        {{-- ── Close button ── --}}
                                        <div class="w-100 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                                                    style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">
                                                Close
                                            </button>
                                        </div>
                                    </div>{{-- End modal-footer --}}

                                </div>{{-- End modal-content --}}
                            </div>{{-- End modal-dialog --}}
                        </div>{{-- End modal --}}
                        @endif


                        @endforeach
                    </div>{{-- End accordion-item --}}

                </div>
            </div>
        </div>
        @endif

                {{-- T5: a full-width button carrying a single user icon — no label, no handler, no
                     destination, and no accessible name. It predates the redesign and renders for
                     every viewer. It is suppressed only behind the redesign flag: it is visible
                     today, so deleting it outright would change the legacy page for everyone, and
                     "it looks like a mistake" is not the same standard as "it can never render".
                     It goes with the share card below rather than after it — suppressing the card
                     alone would leave this button standing on its own, which is exactly the
                     conspicuousness landlord recorded when M5.3 did the same thing there. --}}
                @unless ($hsaDetailRedesign)
                <button class="btn w-100 mt-0">
                    <span class="bid m-0"><i class="fa-solid fa-user"></i> </span>
                </button>
                @endunless
                {{-- T5: the sidebar share card is suppressed when the Seller detail redesign is on —
                     Share Listing and Copy Link both live in the Quick Actions band above the grid,
                     and the copy control there is wired where this card's `js-copy-link` button is
                     bound by nothing in the repository. Confirmed on a rendered page: the two sets
                     of controls appeared together, and the duplicate was the reason for this change.

                     The QR code goes with it. It has no Quick Actions tile because a QR image is
                     listing INFORMATION rather than an action; re-siting it is a sidebar question,
                     not this one.

                     SELLER ONLY. Buyer and tenant still render their copies ungated, so this closes
                     the gap between seller and landlord and leaves the buyer/tenant divergence
                     exactly where it was — normalising all four is a cross-role task with its own
                     scope, and the wider `.js-copy-link` audit is deliberately not started here. --}}
                @unless ($hsaDetailRedesign)
                <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
                    <div class="qr-code" style="width: 100%; height:200px;">
                        {{ qr_code(route('seller.agent.auction.detail', @$auction->id), 200) }}
                    </div>
                    <div class="card-social">
                        <ul class="icons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('seller.agent.auction.detail', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('seller.agent.auction.detail', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-twitter"></i>
                            </a>
                            <a href="">
                                <i class="fa-brands fa-instagram"></i>
                            </a>
                            <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(route('seller.agent.auction.detail', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-pinterest"></i>
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('seller.agent.auction.detail', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-linkedin"></i>
                            </a>
                        </ul>
                        <p class="small opacity-8">Or copy link</p>
                        <div class="field">
                            <i class="fa-solid fa-link"></i>
                            <input type="text" readonly="" id="copylink"
                                value="{{ route('seller.agent.auction.detail', $auction->id) }}">
                            <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                                style="min-width:60px;">Copy</button>
                        </div>
                    </div>
                </div>
                @endunless
            </div>
        </x-slot>

        {{--
            T3 — the hero's Edit Listing control, the seller counterpart of the buyer, landlord and
            tenant slots.

            THE SLOT ITSELF IS CONDITIONAL, not just its contents. An always-emitted slot would be
            `isset()` even when empty, and the legacy hero would then render an empty actions
            wrapper — a DOM change on a page the flag is supposed to leave untouched.

            THE AUTHORIZATION TEST IS SELLER'S OWN, UNCHANGED. Owner-only, by user id — the same
            test the sidebar control has always carried, and buyer's and landlord's shape rather
            than tenant's, because tenant's extra two conditions (tenant-type listing, no accepted
            bid) come from tenant's sidebar control and seller's has never had them. Adding them
            here would hide the control from a seller the sidebar has always shown it to.

            `auth()->id()` is read directly rather than through $auth_id so this does not depend on
            the sidebar slot having been captured first — slot capture order is not something this
            control should have an opinion about.

            Route, params, label, icon and classes are identical to the sidebar control it replaces,
            and the sidebar copy is suppressed under the same hero flag — so exactly one Edit
            Listing renders in either flag state, never two and never none.
        --}}
        @if (\App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('seller')
            && auth()->id() && auth()->id() == @$auction->user_id)
        <x-slot name="heroActions">
            <a href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => 'seller']) }}"
               class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
            </a>
        </x-slot>
        @endif
    </x-hire-agent.detail-shell>
@endsection
@push('scripts')
{{--
    Milestone 3: the timer.jquery CDN tag and the four .timer-d/.timer-h/.timer-m/.timer-s
    countdown initialisers were removed from here. No JavaScript countdown is initialised on this
    page any more, and the library is no longer loaded at all.
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

{{-- T4 — active section highlighting. Flag-gated: with the redesign off this page pushes no
     additional script, so there is no new behaviour to regress.

     THE ROLE-AWARE READER, NOT THE MASTER SWITCH. This script drives the bar emitted under the
     same gate; reading a different flag than the markup it operates on is how a page ends up
     binding behaviour to elements that were never rendered. See the $hsaDetailRedesign note.

     Both partials are shared with the three completed roles rather than copied, and neither
     carries a gate of its own on purpose, so the decision stays here with the markup. --}}
@if (\App\Support\HireAgent\HireAgentDetailRedesign::enabledFor('seller'))
@include('hire_agent.framework.section-nav-behaviour')

{{-- Binds the Quick Actions Copy Link control emitted in the beforeGrid slot. Gated by the same
     role-aware reader as the markup it operates on: binding behaviour to elements that were never
     rendered is the failure this pairing avoids. --}}
@include('hire_agent.framework.quick-actions-behaviour')
@endif
@endpush
