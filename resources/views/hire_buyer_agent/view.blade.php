@extends('layouts.main')
{{-- Combined Fee Display Helper Functions (display-only, no storage changes) --}}
@php
  $toStr = function($v) {
    if (is_array($v)) return implode(', ', $v);
    return (string)($v ?? '');
  };

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
    Milestone 3 — the shared VIHO foundation, extended from the approved Landlord pilot and the
    approved Seller migration.

    Included HERE rather than in the shared detail shell, and that placement is the whole point:
    the shell is rendered by all four roles, so putting it there would have enrolled Tenant in a
    migration it is not part of. Landlord, Seller and Buyer are the migrated roles, so they are
    the only three files that load it. Tenant keeps rendering exactly as it does today.

    It arrives AFTER the framework stylesheet, which matters: where the two define the same
    property for an element that carries both class families, VIHO wins. That is the intended
    direction of the migration and the reason this page now looks like Create Offer.
--}}
@include('viho.styles')

{{-- Residual Buyer-only rules. These LOOK shared but are not: they differ
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
/* Consistent row spacing for all buyer listing data rows */
    .card-body .col-md-12.fw-bold,
    .card-body .col-12.fw-bold {
        padding-top: 0.5rem !important;
        padding-bottom: 0.35rem !important;
    }
/* Nested services lists inside another services list */
    ul.services ul.services {
        padding-left: 1.5em;
        margin-top: 0.25rem;
        margin-bottom: 0;
    }
/* Service category title styling */
    .service-category-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
    }
/* Financing Details section - subsection headers (darker than text-secondary) */
    .financing-subsection-header {
        font-weight: 700 !important;
        color: #374151 !important;
        margin-bottom: 0;
    }
/* Base button style */
        .btn-custom {
            width: 100% !important;
            color: white;
            border: none;
        }
/* Accept (green) */
        .btn-accept {
            background-color: #28a745;
        }
.btn-accept:hover {
            background-color: #218838;
        }
/* Reject (red) */
        .btn-reject {
            background-color: #dc3545;
        }
.btn-reject:hover {
            background-color: #c82333;
        }
/* Counter (yellow) */
        .btn-counter {
            background-color: #ffc107 !important;
            color: #000000 !important;
        }
.btn-counter:hover {
            background-color: #e0a800 !important;
            color: #000000 !important;
        }
/* Counter bidding history — field rows match Tenant typography (12px per li) */
        .counter-bid-card ul.list-unstyled li {
            font-size: 12px;
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

{{-- M7 Phase 4 — the product half of the section navigation. Flag-gated, so with the redesign off
     this page pushes no additional CSS at all.

     THE ROLE-AWARE READER, NOT THE MASTER SWITCH. This block sits above @section('content'), so
     $byaDetailRedesign does not exist yet and the flag is re-read here rather than threaded down —
     the landlord view resolves its own style block the same way for the same reason. Reading the
     master switch instead would declare the offsets for a role the shell has withheld the layout
     from, which is the M7.1 disagreement in miniature. --}}
@if (\App\Support\HireAgent\HireAgentDetailRedesign::enabledFor('buyer'))
<style>
/* THE STICKY OFFSET, SUPPLIED BY THE CONSUMER.
   x-viho.section-nav declares `position: sticky` and deliberately leaves `top` unset, because the
   only correct value is the height of whatever fixed chrome the host page puts above the bar —
   which the primitive cannot know and must not guess. This page is that host, so this page answers.

   The values are landlord's, and they are the same values because the CHROME is the same: both
   pages render through layouts.main, which has no fixed header above the reading column on desktop
   and a 104px header bar below the lg breakpoint. They are declared here rather than shared from
   the framework stylesheet because that file may read --viho tokens and this one declares them;
   the boundary is the same one M7.1 and M7.2 hit and moved rules across rather than widened.

   TWO VARIABLES, NOT ONE, AND THE ARITHMETIC IS THE REASON. The bar sticks at the height of the
   chrome above it. A scroll target must clear the chrome AND the bar itself, because the bar is
   what it is being scrolled underneath. Reusing one value for both leaves the target short by
   exactly the bar's own height — 0px of clearance on desktop, where the chrome is 0 and the bar is
   not. Landlord shipped that bug in M7.2 and M7.4 measured it; buyer inherits the fix rather than
   the bug. */
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

/* The rule that CONSUMES these two is not here and cannot be — it reads --viho tokens, and
   hire_agent/framework/styles.blade.php is the only product file permitted to. That file already
   emits `.hla-detail-page [id^="hla-section-"] { scroll-margin-top: … }` for any role the shell
   resolves the redesign for, so buyer gets it by declaring these and nothing else. */

/* Smooth scrolling is CSS here, not script. The nav emits real hrefs, so the browser performs the
   scroll itself and honours the reader's motion preference. */
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

        /*
         | M7 Phase 3 — the buyer view becomes a real consumer of the detail redesign flag.
         |
         | Phases 1 and 2 built the redesign branch — the hoisted guards, and the Financing Details
         | and Representation Preferences section cards — and every one of those sites gates on
         | `$byaDetailRedesign ?? false`. Nothing assigned it, so the coalesce answered false every
         | time and the whole branch was unreachable: migrated, reviewed, and dead. This line is the
         | wiring that makes it reachable, and it is the ONLY thing this step changes.
         |
         | THE ROLE IS PASSED, NOT TESTED, exactly as the landlord view states it. There is no
         | equality check against a role name here and no second opinion about rollout scope — the
         | `redesign_roles` allowlist in config remains the only thing that grants a role the
         | redesign, and it ships as `landlord` alone. Adding this line therefore turns nothing on;
         | it makes buyer capable of being turned on by a config change rather than a code change.
         |
         | enabledFor(), NEVER enabled(). The master switch alone would let the page body render
         | redesign markup while the shared shell — which gates on enabledFor($role) — withheld the
         | stylesheet that lays it out. That disagreement is the M7.1 failure the landlord view's
         | note records, and HireAgentDetailRedesignFlagTest asserts at source that no view repeats
         | it. The `?? false` at the nine consuming sites is left as written: it is now redundant
         | rather than load-bearing, and rewriting nine lines to prove that is churn this step
         | should not carry.
         */
        $byaDetailRedesign = \App\Support\HireAgent\HireAgentDetailRedesign::enabledFor('buyer');

        /*
         | M7 Phase 1 — the section guards, hoisted, and why they are here rather than beside the
         | sections they describe.
         |
         | The section navigation is emitted near the TOP of the main column and the sections it
         | points at are built hundreds of lines below it. Blade runs top to bottom, so a value
         | computed beside its section is not available to the nav — which is how a nav entry and
         | its section end up disagreeing about whether the section exists. Landlord's M7.2/M7.4
         | notes state the rule these follow: the nav and the section must agree BY CONSTRUCTION,
         | reading one value, rather than by two authors remembering to.
         |
         | NOTHING APPLIES THEM YET. Phase 1 only establishes the values; the nav array and the
         | section conditions arrive with the decomposition. Both booleans are therefore computed
         | and unused, deliberately, and flag-off rendering is unchanged because a value nothing
         | reads cannot change a page.
         |
         | EACH LIST IS THE SECTION'S WHOLE FIELD SET, NOT A SAMPLE. That is the condition that
         | makes hiding safe: an incomplete list would hide a card that still had a row in it.
         */

        /*
         | M7 Phase 5 — Listing Details, which became a section by being decomposed.
         |
         | It was the WRAPPER card's heading rather than a section of its own, so Phase 4 — which
         | made the wrapper conditional — left these seven rows with no heading at all in the
         | redesign branch. Giving them a card gives them their heading back, and it is the last
         | foundation-level gap the decomposition opened.
         |
         | SEVEN TESTS FOR SEVEN ROWS, ONE TO ONE. Each row below the section's own markup is
         | guarded by `!= null` on exactly one meta key, and each key appears here exactly once.
         | The correspondence is total in both directions, which is what makes hiding safe: no key
         | is listed that cannot produce a row (over-reporting would render an empty card), and no
         | row reads a key that is missing here (under-reporting would hide a card that still has
         | content in it).
         |
         | `!= null` RATHER THAN ListingDisplayHelper::anyHasValue(), AND THE DIFFERENCE IS REAL.
         | Landlord's equivalent guard uses the helper because landlord's rows are
         | x-hire-agent.field components, which apply hasValue() themselves — so there the helper
         | IS the row's own rule, asked one step earlier. Buyer's rows are hand-written `!= null`
         | checks and have not been migrated to the field component, so the helper would be a
         | SECOND, stricter opinion here: it rejects placeholder text that `!= null` accepts, and
         | the two disagreeing is precisely how a card gets hidden while a row inside it renders.
         | The rule is "mirror the row", not "use the helper", and these two only coincide once the
         | rows themselves migrate.
         |
         | `listing_title` IS INCLUDED even though landlord's M7.3 found its own listing_title meta
         | to be dead — written to a native column and read here as meta. Whether buyer's is
         | likewise dead is not established, and it does not matter for safety: the row and this
         | test read the identical key, so they cannot disagree in either direction. Removing the
         | row is a content decision and is not this change.
         */
        /*
         | `listing_title` is NOT in this list, and its absence is load-bearing.
         |
         | The guard must enumerate exactly the keys the section's rows read — no more. The row that
         | read `listing_title` is gone (see the note at the card, and landlord's M7.3 before it),
         | and a guard still naming the key would open the card for a listing that has nothing to
         | put in it: a bordered, titled, empty box. HireAgentBuyerSectionNavTest asserts precisely
         | that, in both directions, and caught this the moment the row was removed.
         */
        $byaHasListingDetails =
            @$auction->get->working_with_agent != null ||
            @$auction->get->desired_agent_hire_date != null ||
            @$auction->get->listing_date != null ||
            @$auction->get->expiration_date != null ||
            @$auction->get->auction_type != null ||
            @$auction->get->meeting_Preference != null;


        /*
         | Owner / Buyer's Info. Unconditional today — heading and rows render for every viewer,
         | including an anonymous one — which was survivable while it was a trailing sub-heading and
         | is not once it becomes the LAST CARD on the page. Landlord's M7.4 note records the same
         | reasoning for the same section.
         |
         | FIVE FIELDS, AND EACH TEST MIRRORS THE ROW'S OWN GUARD rather than being normalised.
         | `photo` is isset() because the img row is isset() — a filename is a filename and the
         | placeholder rules do not apply to it; landlord records this same exception. Using a
         | uniform helper here would disagree with the rows for exactly one field, which is the
         | kind of near-miss that hides a card that still has content in it.
         |
         | The photo test carries NO `@` suppression, unlike every other test here, because it
         | cannot: isset() takes a variable and `@expr` is an expression, so `isset(@$x)` is a
         | fatal parse error rather than a lenient read. The img row's own guard is written the
         | same way for the same reason, so mirroring it is also what makes this parse.
         |
         | `current_status` has no landlord counterpart; it is buyer's own row and is included
         | because the list has to be complete to be safe.
         |
         | The commented-out `isset($auction->get->video)` block above the live video row is dead
         | markup and is deliberately NOT counted — it renders nothing in either flag state.
         */
        $byaHasOwnerInfo =
            !empty(@$auction->get->first_name) ||
            !empty(@$auction->get->current_status) ||
            !empty(@$auction->get->video) ||
            isset($auction->get->photo) ||
            !empty(@$auction->get->video_link);

        /*
         | THE BUILDERS BELOW ARE HOISTED, NOT COPIED. Each block moved here verbatim from beside
         | the section that used it, under the SAME variable names, and the block it came from is
         | gone — so the deep conditions that read these names now read the one definition rather
         | than a second opinion. Re-deriving a lighter test up here was tried and rejected: two
         | expressions answering one question is exactly the drift the landlord notes warn about.
         |
         | They are safe to run this early because every input is a read of `$auction->get` or
         | `$auction->info` — none of them depends on a value computed further down the page, which
         | is what made a verbatim move possible rather than a rewrite.
         |
         | Order matters within the financing block only: $financingArray is built first because the
         | $hasAnyFinancingDetails test intersects it.
         */

        /* Required Property or Business Assets — was immediately after the Property Preferences row. */
        $buyerHasAssets = !empty(@$auction->get->assets) && count((array) @$auction->get->assets) > 0;
        $buyerHasRealEstate = !empty(@$auction->get->real_estate_purchase);
        $buyerHasMetrics = !empty(@$auction->get->property_criteria)
            || !empty(@$auction->get->unit_size)
            || (!empty(@$auction->get->number_of_unit_type) && count((array) @$auction->get->number_of_unit_type) > 0)
            || !empty(@$auction->get->minimum_annual_net_income)
            || !empty(@$auction->get->minimum_cap_rate)
            || !empty(@$auction->get->preferance_details);

        /* Financing Details — was inside the Purchasing Terms region. $financingArray and the seven
           per-type flags are also read by conditions much further down (the grouped displays), so
           they are hoisted whole rather than reduced to the one boolean the nav needs. */
        // Prepare financing items array for conditional checks
        $financingForChecks = @$auction->get->offered_financing;
        $_fDecoded = is_string($financingForChecks) ? json_decode($financingForChecks, true) : $financingForChecks;
        // Ensure always an array regardless of JSON encoding (string vs array)
        if (is_array($_fDecoded)) {
            $financingArray = $_fDecoded;
        } elseif (is_null($_fDecoded) || $_fDecoded === false) {
            $financingArray = is_string($financingForChecks) && !empty($financingForChecks) ? [$financingForChecks] : [];
        } else {
            $financingArray = [$_fDecoded]; // scalar (string decoded from JSON string)
        }

        // Check if each financing type has data for grouped display
        $hasSellerFinancingData = !empty(@$auction->get->purchase_price) || !empty(@$auction->get->down_payment_amount) || !empty(@$auction->get->seller_financing_amount) || !empty(@$auction->get->interest_rate) || !empty(@$auction->get->loan_duration) || !empty(@$auction->get->seller_amortization_type) || !empty(@$auction->get->seller_payment_frequency) || !empty(@$auction->get->seller_late_fee_amount) || !empty(@$auction->get->balloon_payment) || !empty(@$auction->get->balloon_payment_amount) || !empty(@$auction->get->balloon_payment_date) || !empty(@$auction->get->prepayment_penalty) || !empty(@$auction->get->prepayment_penalty_amount);

        $hasAssumableData = !empty(@$auction->get->assumable_interest) || !empty(@$auction->get->assumable_max_interest_rate) || !empty(@$auction->get->assumable_max_monthly_payment) || !empty(@$auction->get->assumable_bridge_gap_cash);

        $hasExchangeData = !empty(@$auction->get->exchange_item) || !empty(@$auction->get->exchange_item_value) || !empty(@$auction->get->exchange_item_condition) || !empty(@$auction->get->additional_cash) || !empty(@$auction->get->value_determination) || !empty(@$auction->get->exchange_transfer_method) || !empty(@$auction->get->exchange_liens) || !empty(@$auction->get->exchange_inspection_rights);

        $hasLeaseOptionData = !empty(@$auction->get->lease_option_price) || !empty(@$auction->get->lease_option_terms) || !empty(@$auction->get->lease_option_duration) || !empty(@$auction->get->lease_option_payment) || !empty(@$auction->get->lease_option_conditions) || !empty(@$auction->get->has_option_fee) || !empty(@$auction->get->option_fee_amount) || !empty(@$auction->get->lease_option_fee_credit) || !empty(@$auction->get->lease_option_fee_credit_percentage) || !empty(@$auction->get->lease_option_maintenance) || !empty(@$auction->get->lease_option_extension_terms);

        $hasLeasePurchaseData = !empty(@$auction->get->lease_purchase_price) || !empty(@$auction->get->lease_purchase_terms) || !empty(@$auction->get->lease_purchase_duration) || !empty(@$auction->get->lease_purchase_payment) || !empty(@$auction->get->lease_purchase_conditions) || !empty(@$auction->get->lease_purchase_option_fee) || !empty(@$auction->get->lease_purchase_option_fee_amount) || !empty(@$auction->get->lease_purchase_maintenance) || !empty(@$auction->get->lease_purchase_extension_terms) || !empty(@$auction->get->lease_purchase_rent_credit) || !empty(@$auction->get->lease_purchase_rent_credit_amount) || !empty(@$auction->get->lease_purchase_deposit);

        $hasCryptoData = !empty(@$auction->get->cryptocurrency_type) || !empty(@$auction->get->crypto_percentage) || !empty(@$auction->get->cash_percentage_crypto) || !empty(@$auction->get->crypto_exchange_method) || !empty(@$auction->get->crypto_custodian_wallet) || !empty(@$auction->get->crypto_transaction_fees) || !empty(@$auction->get->crypto_transfer_timing);

        $hasNftData = !empty(@$auction->get->nft_description) || !empty(@$auction->get->nft_percentage) || !empty(@$auction->get->cash_percentage_nft) || !empty(@$auction->get->nft_valuation_method) || !empty(@$auction->get->nft_transfer_method) || !empty(@$auction->get->nft_gas_fees);

        // Check if any financing details section should be shown
        $hasAnyFinancingDetails =
            (in_array('Seller Financing', $financingArray) && $hasSellerFinancingData) ||
            (in_array('Assumable', $financingArray) && $hasAssumableData) ||
            (in_array('Exchange/Trade', $financingArray) && $hasExchangeData) ||
            (in_array('Lease Option', $financingArray) && $hasLeaseOptionData) ||
            (in_array('Lease Purchase', $financingArray) && $hasLeasePurchaseData) ||
            (in_array('Cryptocurrency', $financingArray) && $hasCryptoData) ||
            (in_array('Non-Fungible Token (NFT)', $financingArray) && $hasNftData) ||
            (in_array('Cash', $financingArray) && @$auction->get->cash_budget) ||
            (count(array_intersect($financingArray, ['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'])) > 0 && (@$auction->get->pre_approved || @$auction->get->pre_approval_amount));

        /* C9: Representation Preferences & Compatibility display (public; parity with tenant hire view).

           Hoisted whole — the closures, the accumulator and all fourteen repAdd calls — rather than
           reduced to a boolean. $repRows IS the value three separate things need: whether the nav
           offers an entry, whether the section renders, and what rows it renders. Deriving a
           lighter "does it have anything" test up here would have made the first two read one
           expression and the third read another, which is the drift this hoist exists to remove.
           `!empty($repRows)` is therefore the guard, and the rows are the same array. */
        $rawCompatView = $auction->info('compatibility_preferences');
        $compatView    = ($rawCompatView !== null && $rawCompatView !== '')
            ? (json_decode($rawCompatView, true) ?? [])
            : [];
        $bsView = $compatView['buyer_specific'] ?? [];

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

        // Phase 5/6 QA Follow-up (Buyer Rep & Compatibility): full listing
        // parity — every captured field renders here when populated, with
        // "Other" custom values resolved for Primary Transaction Goal,
        // Representation Priorities and Preferred Agent Working Style.
        $repAdd('Primary Transaction Goal', $bsView['primary_transaction_goal'] ?? '', $bsView['primary_transaction_goal_other'] ?? '');
        $repAdd('Representation Priorities', $bsView['representation_priorities'] ?? [], $bsView['representation_priorities_other'] ?? '');
        $repAdd('Risk Tolerance Level', $bsView['risk_tolerance'] ?? '', '');
        $repAdd('Decision-Making Style', $bsView['decision_making_style'] ?? '', '');
        $repAdd('Timeline Flexibility', $bsView['timeline_flexibility'] ?? '', '');
        $repAdd('Communication Style', $bsView['communication_style'] ?? '', '');
        $repAdd('Preferred Contact Method', $bsView['preferred_contact_method'] ?? '', '');
        $repAdd('Availability / Best Times to Reach You', $bsView['availability_windows'] ?? '', '');
        $repAdd('Meeting / Showing Preference', $bsView['communication_frequency'] ?? '', '');
        $repAdd('Negotiation Style', $bsView['negotiation_style'] ?? '', '');
        $repAdd('Preferred Agent Working Style', $bsView['preferred_agent_working_style'] ?? '', $bsView['preferred_agent_working_style_other'] ?? '');
        $repAdd('Expected Level of Agent Support', $bsView['support_level'] ?? '', '');
        $repAdd('Non-Negotiable Requirements / Deal Breakers', $bsView['deal_breakers'] ?? '', '');
        $repAdd('Additional Notes for Agent', $bsView['additional_compatibility_notes'] ?? '', '');

        /*
         | M7 Phase 4 — SECTION NAVIGATION.
         |
         | Built HERE, at the bottom of the preparation block, because both entries read values the
         | block above computes — $hasAnyFinancingDetails and $repRows — and Blade runs top to
         | bottom. That ordering is the whole reason Phase 1 hoisted those guards: a value computed
         | beside its section is not available to a bar rendered hundreds of lines above it.
         |
         | EACH ENTRY REPEATS ITS SECTION'S CONDITION CHARACTER FOR CHARACTER. That duplication is
         | the point, not an oversight: the alternative is a second, looser expression that means
         | roughly the same thing, and "roughly" is how a nav ends up linking to a section that did
         | not render. Anything changing a section's visibility must change the matching line here.
         | The landlord view states the same rule at its own nav block, and
         | HireAgentBuyerSectionNavTest asserts the two agree in both directions for every viewer it
         | can construct.
         |
         | THE BAR LISTS TWO SECTIONS BECAUSE THE PAGE HAS TWO SECTION CARDS. Financing Details and
         | Representation Preferences are the only sections migrated to
         | x-hire-agent.detail-section, so they are the only ones carrying an `hla-section-*` id and
         | therefore the only ones an in-page link can reach. The remaining seven still render as
         | x-viho.section-header sub-headings with no anchor of their own; offering an entry for one
         | would be a link to nothing, and giving one an id without migrating it would be a section
         | the bar has to account for and cannot describe. Each becomes an entry as it is migrated,
         | in the same change that gives it a card — never before.
         |
         | THERE IS NO COMPENSATION ENTRY, AND ITS ABSENCE IS NOT AN OVERSIGHT. Buyer's compensation
         | section is not migrated, so it has no anchor; when it is, its entry must carry the same
         | auth condition its rows sit behind, exactly as landlord's does. A bar naming "Broker
         | Compensation" to an anonymous visitor leaks both the existence and the name of a section
         | they are never served, in the most prominent place on the page — the one mistake the
         | primitive is built to be incapable of making on its own, because it cannot see the viewer.
         |
         | Everything is behind the flag: with the redesign off for this role the array stays empty,
         | no bar renders, no anchors are emitted and no script is pushed.
         */
        /*
         | M7 Phase 6 — THE GUARDS THE RESOLVER OWES, and the two large sections that had none.
         |
         | `!= null` THROUGHOUT, mirroring the rows rather than using
         | ListingDisplayHelper::anyHasValue(). Landlord can use the helper because its rows are
         | x-hire-agent.field components that apply hasValue() themselves, so there the helper IS
         | the row's rule. Buyer's rows are hand-written `!= null` checks and have not been
         | migrated to the field component, so the helper would be a stricter second opinion: it
         | rejects the literal string 'null' and placeholder text that `!= null` accepts, and a
         | guard stricter than its rows hides a card that still has a row in it.
         |
         | `!= null` IS ALSO CORRECT FOR THE MULTI-SELECT ROWS, which is why one operator covers
         | both shapes. In PHP `[] == null` is true, so an empty array fails `!= null` exactly as
         | the rows' own `count(...) > 0` companion checks intend. No special case is needed.
         |
         | THE LISTS ARE COMPLETE, AND COMPLETENESS IS THE SAFETY PROPERTY. They were derived by
         | extracting every `$auction->get->*` read inside each section's line range rather than
         | written from memory. A key omitted here is not cosmetic: the section would be judged
         | empty and hidden while still holding a row that renders.
         |
         | HireAgentBuyerSectionNavTest exercises every key in both large sections ONE AT A TIME
         | and asserts the section renders AND carries at least one row — which catches the
         | omission in one direction and the empty card in the other.
         */
        $byaAnyPresent = function (array $values): bool {
            foreach ($values as $value) {
                if ($value != null) {
                    return true;
                }
            }

            return false;
        };

        /*
         | Property Preferences, plus the Assets and Income & Investment Metrics blocks that fold
         | into the same card as sub-headings rather than becoming sections of their own.
         |
         | ONLY KEYS THAT CAN TRIGGER A ROW ON THEIR OWN. The list started as every
         | `$auction->get->*` read inside the section and was then reduced: seventeen of them are
         | "Other" companions or nested answers that render only INSIDE a parent row —
         | `other_bedrooms` inside `bedrooms == 'Other'`, the five pet detail rows inside
         | `pets != null`, `assets_other` inside `assets`, and so on. Setting one alone made this
         | boolean true while the section rendered nothing, which is a bordered, titled, empty card.
         |
         | REMOVING THEM IS SAFE IN THE ONE DIRECTION THAT MATTERS, because every one of them has a
         | parent that IS listed here. A row can only appear through its parent, so the parent
         | answers for it. The retired Broker Compensation guard reached the same conclusion about
         | `lease_value` / `purchase_value`, which were added, probed, found unable to trigger
         | content alone, and reverted.
         |
         | Established by probe rather than by reading: each key was rendered in isolation and the
         | card inspected for content. HireAgentBuyerSectionNavTest keeps that probe as a test.
         */
        $byaHasProperty = $byaAnyPresent([
            @$auction->get->cities, @$auction->get->counties, @$auction->get->zipCodes,
            @$auction->get->state,
            @$auction->get->property_type, @$auction->get->property_items,
            @$auction->get->other_property_items,
            @$auction->get->condition_prop_buyer, @$auction->get->other_property_condition,
            @$auction->get->business_type, @$auction->get->business_type_selected,
            @$auction->get->bedrooms, @$auction->get->bathrooms,
            @$auction->get->minimum_heated_square, @$auction->get->total_acreage,
            @$auction->get->carport_needed, @$auction->get->garage_needed,
            @$auction->get->garage_parking_spaces,
            @$auction->get->view_preference,
            @$auction->get->leasing_55_plus,
            @$auction->get->non_negotiable_amenities,
            @$auction->get->pets,
            // The Assets and Metrics sub-blocks — same card, so the same guard.
            @$auction->get->assets, @$auction->get->real_estate_purchase,
            @$auction->get->unit_size, @$auction->get->number_of_unit_type,
            @$auction->get->minimum_annual_net_income, @$auction->get->minimum_cap_rate,
            @$auction->get->preferance_details,
        ]);

        /*
         | Purchasing Terms. Reduced by the same probe for the same reason: the assignment fee
         | fields and both `sale_provision` companions render only inside the sale-provision loop,
         | so `sale_provision` answers for all four.
         */
        $byaHasTerms = $byaAnyPresent([
            @$auction->get->sale_provision,
            @$auction->get->maximum_budget, @$auction->get->target_closing_date,
        ]);

        /* Additional Details. */
        $byaHasAdditionalDetails = @$auction->get->additional_details != null;

        /*
         | Referral & Cooperation. HOISTED from just above its section, verbatim, for the reason
         | every other guard was hoisted: the bar is built here and the section is built hundreds
         | of lines below, and a value computed beside its section is not available to the bar.
         |
         | It issues a query when the listing carries no referral_percentage of its own. Hoisting
         | moves that query earlier in the request; it does not add one. Landlord records the same
         | note for the same block.
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
         | Owner / Buyer's Info heading. Hoisted so the bar entry and the card carry ONE string
         | rather than two expressions that have to be kept in agreement. It was already resolved
         | in PHP because a bound attribute containing `&&` is not parseable by Blade's attribute
         | compiler.
         */
        $_ownerInfoHeading = ($auction->user && $auction->user->user_type === 'agent')
            ? "Agent's Info"
            : "Buyer's Info";

        /*
         | AGENT CREDENTIALS & CONTACT INFO — a new section, not a migrated one.
         |
         | THE LISTING OWNER'S credentials, and only when that owner is an agent. Not the viewer's
         | own — an agent has no use for their own licence number — and not the hired agent's. This
         | is the agent-posted request, the same case the Owner Info heading above already flips
         | for, and it pairs with Referral & Cooperation because both are agent-to-agent business.
         |
         | THE OWNER'S AGENT-NESS IS ASKED OF THE AUDIENCE SERVICE, not tested here. That service
         | knows all three agent user_types; the inline check one line above knows only 'agent',
         | which is a latent defect this file records elsewhere and does not repeat in new code.
         | The service is asked about the OWNER, which is a different question from the audience —
         | it answers "is this user an agent", with no relationship test, and that is exactly what
         | this section needs.
         |
         | The contact fields are read off the User record, where `info()` resolves them from EAV
         | with the column as a fallback. Guarded on the four fields the section can render, so an
         | agent-owned listing whose owner filled none of them shows no empty card.
         */
        $byaOwnerIsAgent = $auction->user
            && app(\App\Services\HireAgent\HireAgentDetailAudience::class)->isAgentUser($auction->user);

        $byaHasAgentCredentials = $byaOwnerIsAgent && $byaAnyPresent([
            @$auction->user->brokerage,
            @$auction->user->license_no,
            @$auction->user->phone,
            @$auction->user->email,
        ]);

        /*
         | THE ROLE-INFO HEADING, CORRECTED — IN THE REDESIGN BRANCH ONLY.
         |
         | $_ownerInfoHeading above tests `user_type === 'agent'` and therefore misses buyer_agent
         | and seller_agent, both of which are storable user types this application treats as
         | agents. That is the latent defect HireAgentDetailAudienceTest records rather than fixes,
         | because correcting it changes what a legacy page says about a real user — and the legacy
         | branch is required to be untouched by this change.
         |
         | It cannot simply be left alone HERE, though, because the redesign now renders two things
         | that would disagree about the same listing: an "Agent Credentials & Contact Info" card,
         | which uses the correct three-type check, under a heading reading "Buyer's Info", which
         | uses the one-type check. One page, two opinions about whether the owner is an agent.
         |
         | So the redesign branch gets the correct heading and the legacy branch keeps the old one,
         | which is the narrowest way to be coherent where it shows without moving the flag-off
         | page. Both variables exist deliberately and they converge the moment the underlying
         | check is fixed for all four roles, at which point $_ownerInfoHeading and this become the
         | same expression and one of them goes.
         */
        $byaRoleInfoHeading = $byaOwnerIsAgent ? "Agent's Info" : "Buyer's Info";

        /*
         | M7 Phase 6 — THE SECTION SET, RESOLVED ONCE.
         |
         | This replaces the hand-built nav array below. The bar and every section card now read
         | ONE value: the bar renders array_values($byaSections) and each card asks
         | isset($byaSections['hla-section-…']). There is no second expression to drift from, which
         | is what the hand-copying discipline was approximating.
         |
         | THE AUDIENCE IS PASSED, NEVER TESTED. `$hlaAudience` arrives resolved from the
         | controller and this file never compares it to anything — no `=== 'agent'`, no match().
         | The guards above are computed unconditionally and audience-blind; the resolver drops the
         | ones this viewer's tier does not admit, and a dropped section never reaches this array,
         | so neither its card nor its bar entry can render. An audience test in Blade would be a
         | second opinion about a rule that already has an owner, and a nav bar is where such a
         | drift becomes a disclosure — the bar names the section it links to.
         |
         | Everything stays behind the redesign flag: with it off the array is empty, no bar
         | renders, no anchors are emitted and every section falls back to its legacy branch.
         */
        $byaSections = [];

        if ($byaDetailRedesign) {
            $byaSections = app(\App\Support\HireAgent\HireAgentDetailSections::class)->resolveForRole(
                'buyer',
                $hlaAudience,
                [
                    'listing-details'    => $byaHasListingDetails,
                    'property'           => $byaHasProperty,
                    'terms'              => $byaHasTerms,
                    'financing'          => $hasAnyFinancingDetails || @$auction->get->offered_financing != null,
                    'additional-details' => $byaHasAdditionalDetails,
                    'representation'     => ! empty($repRows),
                    'role-info'          => $byaHasOwnerInfo,
                    'referral'           => $referralPctDisplay !== '',
                    'agent-credentials'  => $byaHasAgentCredentials,
                ],
                ['role-info' => $byaRoleInfoHeading],
            );
        }

        /*
         | Retained for the section cards, which ask `isset()` on the resolved set. A tiny helper
         | rather than repeating the array lookup eleven times, and it is the ONLY thing the cards
         | consult — no card re-derives its own visibility.
         */
        $byaShows = function (string $key) use (&$byaSections): bool {
            return isset($byaSections[\App\Support\HireAgent\HireAgentDetailSections::ID_PREFIX . $key]);
        };

    @endphp
    @php
        // Buyer counterpart of the landlord view's $hlaListingUrl. Resolved once here because the
        // Quick Actions band below uses it three times (share targets and the copy control), and a
        // route() call repeated per tile is four chances for them to drift apart.
        $byaListingUrl = route('buyer.view-auction', $auction->id);
    @endphp

    {{-- Milestone 5A.3: flash, hero, the listing container, the grid row and both column
         wrappers now come from the shared shell. Only role-specific content lives here. --}}
    <x-hire-agent.detail-shell role="buyer" :auction="$auction">
        @if ($byaDetailRedesign)
        {{-- Chrome parity with the landlord pilot. Full-width, above the grid — page-level actions,
             not main-column content, which is what the shell's beforeGrid slot exists for. The
             tiles are the landlord set with buyer routes and buyer wording; no tile is added and
             none is dropped, so the two pages carry the same three affordances in the same order. --}}
        <x-slot name="beforeGrid">
            <x-viho.quick-actions label="Quick Actions" icon="fa-solid fa-bolt" ariaLabel="Quick actions">

                {{-- 1. Send Message — authenticated user action; route enforces it. --}}
                <x-viho.action-tile
                    :href="route('auction-chat', ['buyer-agent', $auction->id])"
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
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($byaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on Facebook">
                                    <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://twitter.com/intent/tweet?url={{ urlencode($byaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on X">
                                    <i class="fa-brands fa-twitter" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://pinterest.com/pin/create/button/?url={{ urlencode($byaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on Pinterest">
                                    <i class="fa-brands fa-pinterest" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($byaListingUrl) }}"
                                   target="_blank" rel="noopener" aria-label="Share this listing on LinkedIn">
                                    <i class="fa-brands fa-linkedin" aria-hidden="true"></i>
                                </a>
                            </li>
                        </ul>
                    </x-slot>
                </x-viho.action-tile>

                {{-- 3. Copy Link — public action, and wired. The behaviour partial that binds it is
                     included with the page scripts below, exactly as the landlord view does. --}}
                <x-viho.action-tile
                    icon="fa-solid fa-link"
                    label="Copy Link"
                    description="Copy a direct link to this listing.">
                    <x-slot name="action">
                        <x-viho.button
                            variant="outline"
                            icon="fa-solid fa-link"
                            data-hla-copy-link="{{ $byaListingUrl }}">Copy Link</x-viho.button>
                        <span class="hla-quick-copy-status" data-hla-copy-status role="status" aria-live="polite"></span>
                    </x-slot>
                </x-viho.action-tile>

            </x-viho.quick-actions>
        </x-slot>
        @endif

        <x-slot name="main">
            {{-- M7 Phase 4. Outside the wrapper and above it, so the bar spans the column and sticks
                 to the top of the reading area rather than to the inside of a card. --}}
            @if ($byaDetailRedesign)
                <x-viho.section-nav :items="array_values($byaSections)" ariaLabel="Listing sections" />
            @endif
            {{--
                M3. Was `div.card.description` wrapping `card-header.section-header` + an
                `h4.section-title`. The heading level stays h4: typography is migrating, the
                document outline is not.

                THE INNER `div.card-body` BELOW IS DELIBERATELY LEFT IN PLACE, and this is the one
                point where Buyer departs from Seller. Seller's card-body wrapped the whole card,
                so it could be dropped and `viho-card-body` took its place one-for-one. Buyer's
                does not: rendered DOM shows it closing early — the parser rebalances a stray
                closer around the broker-compensation block — leaving three sections and one field
                row as direct children of the card, outside it.

                That matters because the Buyer-only rule `.card-body .col-md-12.fw-bold` styles
                field rows through it. Dropping the wrapper would unstyle all 13 rows inside it;
                re-pointing the rule at `.viho-card-body` would instead pull in the 1 row that sits
                outside it today. Keeping it changes no field row at all, which is the requirement.
                The cost is one extra nesting level and Buyer's existing ragged left edge, both of
                which are preserved exactly as they render today.

                M7 PHASE 4 — THE WRAPPER IS NOW A BRANCH, NOT A CARD. Everything above still
                describes the flag-OFF page exactly, including the inner `div.card-body` and the
                ragged left edge; what changes is that the wrapper card itself only exists in that
                branch. x-hire-agent.detail-body emits it when the redesign is off and nothing at
                all when it is on.

                WHY THAT MATTERS MORE THAN IT LOOKS. Phase 2 migrated Financing Details and
                Representation Preferences to x-hire-agent.detail-section, which renders each as an
                x-viho.card. With this wrapper unconditional, those two cards rendered INSIDE
                another card — a card in a card, each drawing its own border, radius and shadow,
                which is not a shape the reference page (Offer Listing) has anywhere. Phase 3 made
                that branch reachable, so the nesting stopped being theoretical. Decomposing the
                wrapper is what turns them into top-level siblings of the column, which is what the
                reference renders and what lets a nav link land on a card header.

                THE SECTIONS THAT ARE NOT MIGRATED KEEP THEIR SUB-HEADINGS AND LOSE THEIR CARD in
                the redesign branch, and that is a deliberate intermediate state rather than a
                finished page: this step builds the foundation, and each remaining section becomes a
                card in its own change. It is invisible to every environment because
                `redesign_roles` ships as landlord alone — buyer's redesign branch is reachable by
                config and reached by nobody. Landlord did the same decomposition and its eight
                section migrations in one milestone; buyer separates them so the wrapper change and
                the nav can be reviewed on their own.
            --}}
            <x-hire-agent.detail-body :redesign="$byaDetailRedesign" title="Listing Details:">
@if (! ($byaDetailRedesign ?? false))                    <div class="card-body">
@endif
{{-- M7 Phase 5 — Listing Details becomes a section card.

     THE GUARD IS THE SAME BOOLEAN THE NAV ENTRY READS, and the `! $byaDetailRedesign ||` arm is
     what keeps the legacy branch unconditional: with the redesign off these rows render exactly as
     they always have, whether or not any of them has an answer, because emptiness only became a
     reason to hide something once it became a bordered box. Landlord's M7.4 uses the same idiom for
     the same section.

     legacy-header IS FALSE, and this is the one section on the page where it must be. The wrapper
     card's own title "Listing Details:" IS this section's heading in the legacy branch, so emitting
     a header here would put a duplicate heading directly beneath the title it duplicates. With the
     redesign on the wrapper is gone and the card title supplies the heading instead — which is the
     entire point of this change.

     The trailing colon is passed and stripped by the component in the card branch only; the legacy
     branch never sees this title at all. --}}
@if (! ($byaDetailRedesign ?? false) || $byaShows('listing-details'))
<x-hire-agent.detail-section :redesign="$byaDetailRedesign ?? false" :legacy-header="false" id="hla-section-listing-details" title="Listing Details:" icon="fa-solid fa-file-lines">
                        <div class="row" style="flex-wrap: wrap;">
                            {{-- The "Listing Title" row is gone, and it could never have rendered.
                                 The questionnaire DOES ask for a listing title, but the component
                                 stores the answer in the auction's native `title` COLUMN, not as
                                 `listing_title` meta. This row read the meta key, which nothing
                                 writes — measured at zero rows in buyer_agent_auction_metas — so
                                 the `!= null` guard was never satisfied and the row was dead in
                                 both flag states.

                                 It is removed rather than repointed at `$auction->title`, because
                                 that value is already on the page: the hero renders it as the page
                                 heading. Fixing the read would have produced a heading followed
                                 immediately by a row repeating it. The one field, in the one place.

                                 This mirrors the landlord view's M7.3 removal exactly, for the
                                 same reason and on the same evidence. It ALSO could not have been
                                 adapted as-is: this row is the only one in the section whose label
                                 carries no trailing colon, and the component's legacy branch always
                                 appends one — so converting it would have changed flag-off output.
                                 A row that cannot render is the safest possible thing to delete. --}}
                            @php
                                /*
                                 | Dates are formatted BEFORE the row, not inside it.
                                 |
                                 | The three date rows each wrapped date(…, strtotime($v)) in their
                                 | own `!= null` guard, and the guard was doing double duty: it
                                 | decided whether the row appeared AND it kept strtotime() away
                                 | from a null, which is a deprecation in PHP 8.1+ and returns the
                                 | epoch rather than nothing. Moving the emptiness decision into the
                                 | row component would have removed the second job silently, so the
                                 | formatting is resolved here and the component receives a finished
                                 | string or nothing at all.
                                 |
                                 | ListingDisplayHelper::hasValue is the same rule the row applies,
                                 | asked one step earlier — not a second opinion, the same call.
                                 |
                                 | Named to the buyer view's own prefix; the landlord view carries
                                 | the identical closure as $hlaFmtDate. Two views, one page each,
                                 | and no shared scope between them.
                                 */
                                $byaFmtDate = function ($value) {
                                    return \App\Helpers\ListingDisplayHelper::hasValue($value)
                                        ? date('F j, Y', strtotime($value))
                                        : null;
                                };

                                $byaHireDate   = $byaFmtDate(@$auction->get->desired_agent_hire_date);
                                $byaListDate   = $byaFmtDate(@$auction->get->listing_date);
                                $byaExpiryDate = $byaFmtDate(@$auction->get->expiration_date);
                            @endphp
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Current Representation Status with Broker" :value="@$auction->get->working_with_agent" />
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Desired Agent Hire Date" :value="$byaHireDate" />
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Listing Date" :value="$byaListDate" />
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Expiration Date" :value="$byaExpiryDate" />
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Listing Type" :value="@$auction->get->auction_type" />

                            {{-- Milestone 3: the "Bidding Period Length: 14 Days" row was removed
                                 here. It is a bidding-period label describing a timer that no
                                 longer exists or governs anything. --}}
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Meeting Preference" :value="@$auction->get->meeting_Preference" />

                        </div>
</x-hire-agent.detail-section>
@endif
@if (! ($byaDetailRedesign ?? false))                          <hr>
@endif
                        {{-- M3: sub-section header inside the single Listing Details card.

                             M7 Phase 6: it becomes the card's own heading. The component emits this
                             exact header in the legacy branch — legacy-header defaults true — so the
                             flag-off page is unchanged, and renders it as a card title with the
                             colon stripped when the redesign is on.

                             THE CARD SPANS THREE BLOCKS. Property Preferences, "Required Property or
                             Business Assets" and the Income & Investment Metrics rows are divisions
                             within one subject, so they share a card and keep their own sub-headings
                             inside it — the same shape landlord uses for its eleven compensation
                             sub-headings. One guard covers all three, which is why $byaHasProperty
                             lists their keys together. --}}
@if (! ($byaDetailRedesign ?? false) || $byaShows('property'))
<x-hire-agent.detail-section :redesign="$byaDetailRedesign ?? false" id="hla-section-property" title="Property Preferences:" icon="fa-solid fa-house">

                        <div class="row" style="flex-wrap: wrap;">

                                    {{-- ── PILLS vs TEXT, AND WHICH ROWS GET WHICH ──────────────────
                                         The three unbounded enumerations below — Cities, Counties,
                                         ZIP Codes — keep their pills in BOTH branches (`badges` +
                                         `bareSlot`). They are open-ended lists of short tokens, and
                                         a forty-item comma list is genuinely worse to scan than
                                         forty chips. This matches the landlord view, which keeps
                                         pills on exactly these three rows and no others.

                                         Every BOUNDED multi-select below instead passes `listValue`
                                         alongside the pill run: the legacy branch reads only the
                                         slot and keeps its pills untouched, while the redesign
                                         branch reads `listValue` and renders ", "-joined text. That
                                         is the reference page's vocabulary — a pill means STATE,
                                         plain text means DATA — and it is why the pills cannot
                                         simply be deleted from the call sites: flag-off output is
                                         asserted verbatim, and flag-off renders pills. --}}
                                    {{-- ── THE LOCATION ROWS RENDER AS TEXT, LIKE EVERY OTHER ROW ───
                                         The three rows below — Acceptable Cities, Counties and ZIP
                                         Codes — pass `listValue` and NOT `badges`, so the redesign
                                         branch renders them as ", "-joined text in an ordinary
                                         half-width cell. They now read exactly like Acceptable State
                                         directly beneath them, and like the twenty-odd other
                                         multi-value rows on this page.

                                         WHY, AND WHY THE PROP-LEVEL ARGUMENT WAS THE WRONG ONE.
                                         These rows briefly carried `badges` in order to match the
                                         landlord call sites, which carry it. That matched landlord's
                                         SOURCE and not landlord's PAGE: no landlord listing carries
                                         acceptable-city data, so landlord's badge rows have never
                                         rendered, and the landlord location block a reader actually
                                         sees is City / County / State / Zip Code — four plain
                                         `:value` rows. Chips here were the only chips on either
                                         page. Parity is a property of what renders, so it has to be
                                         measured against rendered output, not against props.

                                         THE PILLS STAY IN THE SLOT AND THAT IS DELIBERATE. Flag-off
                                         output is asserted verbatim and flag-off renders pills, so
                                         the loops below cannot be deleted. The legacy branch reads
                                         only the slot; the redesign branch reads only `listValue`.
                                         One row, two renderings, neither branch aware of the other —
                                         which is exactly what M7.6 built `listValue` for, and these
                                         three rows are simply the last to adopt it.

                                         `bareSlot` STAYS for the same reason: it governs the LEGACY
                                         branch only, emitting the run without a wrapping
                                         `.removeBold`, which is the element tree flag-off is
                                         asserted against.

                                         LANDLORD IS UNTOUCHED and still says `badges` on its own
                                         three rows. If a landlord listing ever carries acceptable
                                         areas, the two roles will diverge again — in the opposite
                                         direction to before. That is a known, logged divergence, not
                                         an oversight; closing it means changing landlord and the
                                         landlord badge assertions in
                                         HireAgentFieldPresentationTest, which is a separate call. --}}
                                    <!-- Location Information -->
                                    @if (@$auction->get->cities != null && count(@$auction->get->cities) > 0)
                                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable Cities" :bare-slot="true" :list-value="\App\Helpers\ListingDisplayHelper::stripStateSuffixList(@$auction->get->cities ?? [])">
                                            @foreach (@$auction->get->cities as $item)
                                                <span class="removeBold badge bg-secondary">{{ \App\Helpers\ListingDisplayHelper::stripStateSuffix($item) }}</span>
                                            @endforeach
                                        </x-hire-agent.field>
                                    @endif

                                    @if (@$auction->get->counties != null && count(@$auction->get->counties) > 0)
                                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable Counties" :bare-slot="true" :list-value="\App\Helpers\ListingDisplayHelper::stripStateSuffixList(@$auction->get->counties ?? [])">
                                            @foreach (@$auction->get->counties as $item)
                                                <span class="removeBold badge bg-secondary">{{ \App\Helpers\ListingDisplayHelper::stripStateSuffix($item) }}</span>
                                            @endforeach
                                        </x-hire-agent.field>
                                    @endif

                                    @if (@$auction->get->zipCodes != null && count(@$auction->get->zipCodes) > 0)
                                        {{-- Included with Cities and Counties above, though visual
                                             review named only those two — listing 107 stores an
                                             empty zipCodes array, so this row simply has nothing to
                                             show and has never appeared in review. It is the third
                                             member of the same location family and takes the same
                                             treatment; leaving it as chips would make it the only
                                             pill run on the page the moment a listing named ZIPs.

                                             No stripStateSuffixList here — a ZIP carries no ", FL"
                                             suffix to strip, and running the mapper over it would
                                             imply otherwise. --}}
                                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable ZIP Codes" :bare-slot="true" :list-value="@$auction->get->zipCodes ?? []">
                                            @foreach (@$auction->get->zipCodes as $item)
                                                <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                            @endforeach
                                        </x-hire-agent.field>
                                    @endif

                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable State" :value="@$auction->get->state" />

                                    <!-- Property Type and Style -->
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable Property Type" :value="@$auction->get->property_type" />

                                    @php
                                        $detailPropertyStyles = \App\Helpers\ListingDisplayHelper::normalizeListDeduped(@$auction->get->property_items, @$auction->get->other_property_items);
                                    @endphp
                                    @if (!empty($detailPropertyStyles))
                                        {{-- HALF SPAN, matching landlord's "Property Style", which is
                                             the same question asked of the other side of the deal and
                                             renders at the default half width. A bounded pick-list of
                                             short names is a short answer: giving it the whole card
                                             breaks the two-up rhythm for the fields around it and
                                             leaves most of the row empty. `bareSlot` + `listValue`
                                             stay — they carry the flag-off pills and the redesign
                                             text respectively, and neither has anything to do with
                                             width. --}}
                                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable Property Styles" :bare-slot="true" :list-value="$detailPropertyStyles">
                                            @foreach ($detailPropertyStyles as $item)
                                                <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                            @endforeach
                                        </x-hire-agent.field>
                                    @endif

                                    <!-- Business Type (if applicable) - check both business_type and business_type_selected -->
                                    @php
                                        $businessTypeValue = @$auction->get->business_type_selected ?: @$auction->get->business_type;
                                        $otherBusinessType = @$auction->get->other_business_type;
                                        if (is_array($businessTypeValue)) {
                                            $businessTypeArray = $businessTypeValue;
                                        } elseif (is_string($businessTypeValue) && $businessTypeValue !== '') {
                                            $decoded = json_decode($businessTypeValue, true);
                                            $businessTypeArray = is_array($decoded) ? $decoded : [$businessTypeValue];
                                        } else {
                                            $businessTypeArray = [];
                                        }
                                        $businessTypeArray = array_filter($businessTypeArray, fn($v) => $v !== null && $v !== '');

                                        /*
                                         | The same list the pill loop below emits, resolved once so
                                         | the redesign branch can render it as text. It is NOT a
                                         | second opinion about what to show: it applies the loop's
                                         | own two rules — "Other" is replaced by the custom text,
                                         | and dropped entirely when that text is empty — so the two
                                         | branches cannot disagree about which items exist. The
                                         | loop is left exactly as it was because flag-off output is
                                         | asserted verbatim.
                                         */
                                        $businessTypeDisplay = [];
                                        foreach ($businessTypeArray as $businessTypeItem) {
                                            if (strtolower($businessTypeItem) === 'other') {
                                                if (!empty($otherBusinessType)) {
                                                    $businessTypeDisplay[] = $otherBusinessType;
                                                }
                                            } else {
                                                $businessTypeDisplay[] = $businessTypeItem;
                                            }
                                        }
                                    @endphp
                                    @if (!empty($businessTypeArray))
                                        {{-- Half span, same class of answer as Property Styles above:
                                             a bounded pick-list of short names. --}}
                                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Business Type" :bare-slot="true" :list-value="$businessTypeDisplay">
                                            @foreach ($businessTypeArray as $businessTypeItem)
                                                @if (strtolower($businessTypeItem) === 'other')
                                                    @if (!empty($otherBusinessType))
                                                        <span class="removeBold badge bg-secondary">{{ $otherBusinessType }}</span>
                                                    @endif
                                                @else
                                                    <span class="removeBold badge bg-secondary">{{ $businessTypeItem }}</span>
                                                @endif
                                            @endforeach
                                        </x-hire-agent.field>
                                    @endif

                                    @php
                                        $detailConditions = \App\Helpers\ListingDisplayHelper::normalizeListDeduped(@$auction->get->condition_prop_buyer, @$auction->get->other_property_condition);
                                    $conditionDisplayMap = [
                                        'Older but Clean'                => 'Older but Clean & Well Maintained',
                                        'Older but clean & well maintained' => 'Older but Clean & Well Maintained',
                                    ];
                                    $detailConditions = array_map(function($c) use ($conditionDisplayMap) {
                                        return $conditionDisplayMap[$c] ?? $c;
                                    }, $detailConditions);
                                    @endphp
                                    @if (!empty($detailConditions))
                                        {{-- The single-item case renders plain text rather than one
                                             lone pill, and `bareSlot` carries that branch through to
                                             flag-off untouched. The redesign reads `listValue` and
                                             so renders text either way, which is where the two
                                             spellings were always heading. --}}
                                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable Property Conditions" :bare-slot="true" :list-value="$detailConditions">
                                            @if (count($detailConditions) === 1)
                                                <span class="removeBold">{{ $detailConditions[0] }}</span>
                                            @else
                                                @foreach ($detailConditions as $cItem)
                                                    <span class="removeBold badge bg-secondary">{{ $cItem }}</span>
                                                @endforeach
                                            @endif
                                        </x-hire-agent.field>
                                    @endif

                                    <!-- Bedrooms and Bathrooms -->
                                    @php
                                        /*
                                         | Resolved before the row for the same reason the dates in
                                         | Listing Details are: the "Other" substitution was living
                                         | inside the row's own markup, where a component cannot see
                                         | it. Same two rules, same result, one step earlier.
                                         */
                                        $bedroomsDisplay = (@$auction->get->bedrooms != null && @$auction->get->bedrooms != 'Other')
                                            ? @$auction->get->bedrooms
                                            : (@$auction->get->bedrooms != null ? @$auction->get->other_bedrooms : null);

                                        $bathroomsDisplay = (@$auction->get->bathrooms === 'Other')
                                            ? @$auction->get->other_bathrooms
                                            : @$auction->get->bathrooms;
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Minimum Bedrooms Needed" :value="$bedroomsDisplay" />
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Minimum Bathrooms Needed" :value="$bathroomsDisplay" />

                                    <!-- Square Footage and Acreage -->
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Minimum Heated SqFt Needed" :value="@$auction->get->minimum_heated_square" />
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Minimum Total Acreage Needed" :value="@$auction->get->total_acreage" />

                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Carport Needed" :value="@$auction->get->carport_needed != null ? \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->carport_needed, @$auction->get->other_carport_needed, 'Spaces') : null" />
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Garage Needed" :value="@$auction->get->garage_needed != null ? \App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->garage_needed, @$auction->get->other_garage_needed, 'Spaces') : null" />

                                    <!-- Garage/Parking Features for Commercial/Business -->
                                    @if (@$auction->get->garage_parking_spaces != null)
                                        @php
                                            /*
                                             | The same items the three guarded blocks below emit, in
                                             | the same order, flattened for the redesign branch. It
                                             | reproduces each of their rules rather than restating
                                             | them: the main value is dropped when it is "Other" and
                                             | custom text exists, an "Other" option is dropped for
                                             | the same reason, and the custom text is appended last.
                                             | The blocks themselves are untouched — flag-off renders
                                             | a plain value followed by a pill run, and that mixed
                                             | shape is exactly what is asserted.
                                             */
                                            $byaParkingDisplay = [];
                                            if (!(@$auction->get->garage_parking_spaces === 'Other' && @$auction->get->other_parking_space_wrapper)) {
                                                $byaParkingDisplay[] = @$auction->get->garage_parking_spaces;
                                            }
                                            if (@$auction->get->garage_parking_spaces_option && count(@$auction->get->garage_parking_spaces_option) > 0) {
                                                foreach (@$auction->get->garage_parking_spaces_option as $byaParkingItem) {
                                                    if (!($byaParkingItem === 'Other' && @$auction->get->other_parking_space_wrapper)) {
                                                        $byaParkingDisplay[] = $byaParkingItem;
                                                    }
                                                }
                                            }
                                            if (@$auction->get->other_parking_space_wrapper) {
                                                $byaParkingDisplay[] = @$auction->get->other_parking_space_wrapper;
                                            }
                                        @endphp
                                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Garage Parking Features Needed" :bare-slot="true" :list-value="$byaParkingDisplay">
                                            {{-- Skip "Other" in main value when custom text exists --}}
                                            @if (!(@$auction->get->garage_parking_spaces === 'Other' && @$auction->get->other_parking_space_wrapper))
                                                <span class="removeBold">{{ @$auction->get->garage_parking_spaces }}</span>
                                            @endif
                                            @if (@$auction->get->garage_parking_spaces_option && count(@$auction->get->garage_parking_spaces_option) > 0)
                                                @foreach (@$auction->get->garage_parking_spaces_option as $item)
                                                    {{-- Skip "Other" when custom text exists --}}
                                                    @if (!($item === 'Other' && @$auction->get->other_parking_space_wrapper))
                                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                                    @endif
                                                @endforeach
                                            @endif
                                            {{-- Show the custom "Other" text without the word "Other" --}}
                                            @if (@$auction->get->other_parking_space_wrapper)
                                                <span class="removeBold badge bg-secondary">{{ @$auction->get->other_parking_space_wrapper }}</span>
                                            @endif
                                        </x-hire-agent.field>
                                    @endif

                                    <!-- Pool -->
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

                                // Keep only truthy entries and join their keys (capitalized)
                                $poolTypeList = collect($poolTypeRaw)
                                    ->filter(fn($v) => $v === true || $v === 1 || $v === '1' || $v === 'true')
                                    ->keys()
                                    ->map(fn($key) => ucfirst($key))
                                    ->implode(', ');
                            @endphp

                            @php
                                /*
                                 | Two mutually exclusive rows collapse into one value, because they
                                 | were never two fields — they are one field with two spellings of
                                 | its answer. "Yes" carries the pool types in parentheses; "No" and
                                 | "Optional" stand alone; anything else renders nothing, which is
                                 | what the absent @else already meant.
                                 */
                                $byaPoolNeeded = optional($auction->get)->pool_needed;
                                $byaPoolDisplay = $byaPoolNeeded === 'Yes'
                                    ? 'Yes' . ($poolTypeList !== '' ? ' (' . $poolTypeList . ')' : '')
                                    : (in_array($byaPoolNeeded, ['No', 'Optional'], true) ? $byaPoolNeeded : null);
                            @endphp
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Pool Needed" :value="$byaPoolDisplay" />

                        @php
                            /*
                             | Both lists below follow the same rule the questionnaire gives them:
                             | the literal option "Other" is a prompt for free text, not an answer,
                             | so it is dropped and the text the user typed takes its place at the
                             | end. Resolved here so the redesign branch can render the result as
                             | text while the pill loops stay exactly as flag-off asserts them.
                             */
                            $byaListWithOther = function ($items, $otherText) {
                                $out = [];
                                foreach ((array) $items as $byaListItem) {
                                    if ($byaListItem != 'Other') {
                                        $out[] = $byaListItem;
                                    }
                                }
                                if ($otherText) {
                                    $out[] = $otherText;
                                }
                                return $out;
                            };

                            $byaViewPreferences = $byaListWithOther(@$auction->get->view_preference, @$auction->get->other_preferences);
                            $byaNonNegotiables  = $byaListWithOther(@$auction->get->non_negotiable_amenities, @$auction->get->other_non_negotiable_amenities);
                        @endphp
                        <!-- View Preferences -->
                        @if (@$auction->get->view_preference != null && count(@$auction->get->view_preference) > 0)
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="View Preference Needed" :bare-slot="true" :list-value="$byaViewPreferences">
                                @foreach (@$auction->get->view_preference as $item)
                                    @if ($item != 'Other')
                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                    @endif
                                @endforeach
                                @if (@$auction->get->other_preferences)
                                    <span class="removeBold badge bg-secondary">{{ @$auction->get->other_preferences }}</span>
                                @endif
                            </x-hire-agent.field>
                        @endif

                        <!-- 55+ Communities -->
                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Age-Restricted Community" :value="@$auction->get->leasing_55_plus" />

                        <!-- Non-Negotiable Amenities -->
                        @if (@$auction->get->non_negotiable_amenities != null && count(@$auction->get->non_negotiable_amenities) > 0)
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Non-Negotiable Amenities and Property Features" :bare-slot="true" :list-value="$byaNonNegotiables">
                                @foreach (@$auction->get->non_negotiable_amenities as $item)
                                    @if ($item != 'Other')
                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                    @endif
                                @endforeach
                                @if (@$auction->get->other_non_negotiable_amenities)
                                    <span class="removeBold badge bg-secondary">{{ @$auction->get->other_non_negotiable_amenities }}</span>
                                @endif
                            </x-hire-agent.field>
                        @endif

                        {{-- The two pets blocks — this one and the `!= 'Income'` one below — carry
                             identical content behind opposite halves of the same test, so exactly
                             one of them renders for any listing. That duplication predates this
                             migration and is left exactly as it is: collapsing them is a change to
                             what the page decides, not to how it draws, and this pass is only the
                             latter. Both blocks are adapted identically so neither can drift. --}}
                        @if (@$auction->get->property_type == 'Income' && @$auction->get->pets != null)
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Pets" :value="\App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->pets, @$auction->get->number_of_pets)" />

                            @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->pets))
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Pet Types" :value="@$auction->get->type_of_pets" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Breed of Pets" :value="@$auction->get->breed_of_pets" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Pet Weight (lbs)" :value="@$auction->get->weight_of_pets" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Service Animal" :value="@$auction->get->service_animal" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Emotional Support Animal" :value="@$auction->get->emotional_support_animal" />
                            @endif
                        @endif

                        @if (@$auction->get->property_type != 'Income' && @$auction->get->pets != null)
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Pets" :value="\App\Helpers\ListingDisplayHelper::formatYesCount(@$auction->get->pets, @$auction->get->number_of_pets)" />

                            @if (\App\Helpers\ListingDisplayHelper::isParentYes(@$auction->get->pets))
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Pet Types" :value="@$auction->get->type_of_pets" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Breed of Pets" :value="@$auction->get->breed_of_pets" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Pet Weight (lbs)" :value="@$auction->get->weight_of_pets" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Service Animal" :value="@$auction->get->service_animal" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Emotional Support Animal" :value="@$auction->get->emotional_support_animal" />
                            @endif
                        @endif

                        </div>{{-- end Property Preferences row --}}

                        {{-- M7 Phase 2 — $buyerHasAssets / $buyerHasRealEstate / $buyerHasMetrics are
                             hoisted to the block at the top of this section so the nav reads the same
                             values these conditions do. The definitions moved verbatim; see the note
                             there. --}}

                        @if ($buyerHasAssets || $buyerHasRealEstate)
@if (! ($byaDetailRedesign ?? false))                        <hr>
@endif
                        {{-- LEGACY ONLY, AND THE GATE IS THE FIX.
                             ─────────────────────────────────────────────────────────────────────
                             This was the one sub-heading on the page rendering in BOTH branches,
                             and in the redesign branch it rendered at card-title weight. That is
                             not a `tag` mistake to be corrected by asking for an h5: x-viho.section
                             -header always emits `viho-section-header-title`, which its own docblock
                             describes as "the same typography the card header uses". The element
                             changes with `tag`; the size does not. So inside a card this heading was
                             indistinguishable from the card's own title.

                             MATCHING LANDLORD MEANS REMOVING IT, not shrinking it. Landlord's
                             redesigned cards contain a title and fields — no intermediate heading
                             anywhere in the view. There is nothing to shrink this to that landlord
                             also has.

                             IT ALSO SAID NOTHING NEW. The second field below carries the label
                             "Required Property or Business Assets" verbatim, so the redesign branch
                             was printing a heading immediately followed by a field repeating it. The
                             grouping the heading provided is what the two full-width fields already
                             do on their own.

                             FLAG-OFF IS UNTOUCHED. The legacy page keeps the heading exactly where
                             and as it was — it is load-bearing there, because the legacy branch has
                             no card title above it to make it redundant.

                             The nine `financing-subsection-header` h6 headings in the Financing card
                             are deliberately NOT changed by this: they render below card-title
                             weight already, they divide genuinely distinct financing types, and
                             landlord has no Financing section to be compared against. --}}
                        @if (! ($byaDetailRedesign ?? false))
                        <x-viho.section-header title="Required Property or Business Assets" tag="h4" />
                        @endif
                        <div class="row" style="flex-wrap: wrap;">
                            {{-- Literal & in the prop: Blade escapes it back to &amp; on output, so
                                 the rendered text is unchanged. Passing &amp; here would
                                 double-escape it. Same rule as the Representation card below. --}}
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Business & Real Estate Purchase Requirements" :value="$buyerHasRealEstate ? @$auction->get->real_estate_purchase : null" />

                            @if ($buyerHasAssets)
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Required Property or Business Assets" :bare-slot="true" :list-value="$byaListWithOther(@$auction->get->assets, @$auction->get->assets_other)">
                                    @foreach (@$auction->get->assets as $item)
                                        @if ($item != 'Other')
                                            <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                        @endif
                                    @endforeach
                                    @if (@$auction->get->assets_other)
                                        <span class="removeBold badge bg-secondary">{{ @$auction->get->assets_other }}</span>
                                    @endif
                                </x-hire-agent.field>
                            @endif
                        </div>
                        @endif

                        @if ($buyerHasMetrics)
                        <div class="row" style="flex-wrap: wrap;">

                            @php
                                /*
                                 | The "Other" substitution and the percent suffix both moved out of
                                 | the row markup for the same reason as everywhere else in this
                                 | pass: a component receives a finished value, and a value that is
                                 | half-built inside a <span> is one the component cannot judge
                                 | empty. Same rules, same output, resolved one step earlier.
                                 */
                                $byaUnitSize = @$auction->get->unit_size != null
                                    ? (@$auction->get->unit_size != 'Other' ? @$auction->get->unit_size : @$auction->get->unit_size_other)
                                    : null;

                                $byaCapRate = @$auction->get->minimum_cap_rate != null
                                    ? @$auction->get->minimum_cap_rate . '%'
                                    : null;
                            @endphp
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable Number of Units" :value="$byaUnitSize" />

                            @if (@$auction->get->number_of_unit_type != null && count(@$auction->get->number_of_unit_type) > 0)
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable Unit Type" :bare-slot="true" :list-value="@$auction->get->number_of_unit_type">
                                    @if (count(@$auction->get->number_of_unit_type) === 1)
                                        <span class="removeBold">{{ @$auction->get->number_of_unit_type[0] }}</span>
                                    @else
                                        @foreach (@$auction->get->number_of_unit_type as $item)
                                            <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </x-hire-agent.field>
                            @endif

                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Minimum Annual Net Income Needed" :value="@$auction->get->minimum_annual_net_income != null ? \App\Support\Format::money(@$auction->get->minimum_annual_net_income) : null" />
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Minimum Cap Rate Needed" :value="$byaCapRate" />
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Additional Details" :value="@$auction->get->preferance_details" />
                        </div>
                        @endif
</x-hire-agent.detail-section>
@endif
@if (! ($byaDetailRedesign ?? false))                        <hr>
@endif
{{-- M7 Phase 6 — Purchasing Terms becomes a card. The header the component emits in the legacy
     branch is byte-identical to the one that stood here. --}}
@if (! ($byaDetailRedesign ?? false) || $byaShows('terms'))
<x-hire-agent.detail-section :redesign="$byaDetailRedesign ?? false" id="hla-section-terms" title="Purchasing Terms:" icon="fa-solid fa-file-contract">

                            <!-- Special Sale Provisions -->
                            @php
                                $saleProvisionRaw = @$auction->get->sale_provision;
                                if (is_array($saleProvisionRaw)) {
                                    $saleProvisionArray = $saleProvisionRaw;
                                } elseif (is_string($saleProvisionRaw) && !empty($saleProvisionRaw)) {
                                    $decoded = json_decode($saleProvisionRaw, true);
                                    $saleProvisionArray = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$saleProvisionRaw];
                                } else {
                                    $saleProvisionArray = [];
                                }
                            @endphp
                            @if (!empty($saleProvisionArray) && count($saleProvisionArray) > 0)
                                @php
                                    /*
                                     | The loop's own two rules, resolved for the redesign branch:
                                     | quotes are stripped from a real provision, and "Other" is
                                     | replaced in place by the custom text — in place, not at the
                                     | end, because this loop substitutes rather than appends. That
                                     | difference from the lists above is why this cannot share
                                     | $byaListWithOther.
                                     */
                                    $byaSaleProvisions = [];
                                    foreach ($saleProvisionArray as $byaSale) {
                                        if ($byaSale != 'Other') {
                                            $byaSaleProvisions[] = str_replace('"', '', $byaSale);
                                        } elseif (@$auction->get->sale_provision_other) {
                                            $byaSaleProvisions[] = @$auction->get->sale_provision_other;
                                        }
                                    }
                                @endphp
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Acceptable Special Sale Provisions" :bare-slot="true" :list-value="$byaSaleProvisions">
                                    @foreach ($saleProvisionArray as $sale)
                                        @if ($sale != 'Other')
                                            @php $displaySale = str_replace('"', '', $sale); @endphp
                                            <span class="removeBold badge bg-secondary">{{ $displaySale }}</span>
                                        @elseif (@$auction->get->sale_provision_other)
                                            <span class="removeBold badge bg-secondary">{{ @$auction->get->sale_provision_other }}</span>
                                        @endif
                                    @endforeach
                                </x-hire-agent.field>
                            @endif

                            <!-- Assignment Contract Details -->
                            @if (in_array('Assignment Contract', $saleProvisionArray))
                                @if (@$auction->get->sale_provision_assignment)
                                    @php
                                        $displayAssignment = str_replace('"', '', $toStr(@$auction->get->sale_provision_assignment));
                                    @endphp
                                    {{-- Half span. The value is a Yes/No answer — structurally three
                                         characters, not three characters on this listing — and a
                                         full-width row gives the value column ~79% of the card for
                                         it to sit in. That is the corridor of empty space the field
                                         component's `badges` note describes, arrived at from the
                                         other direction. The label column is 20.833% at full span
                                         and 41.666% at half, which resolve to roughly the SAME
                                         absolute width, so halving costs the long label nothing and
                                         lets the field pair with its neighbour. Landlord has no
                                         full-width field whose value is structurally short. --}}
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Buyer Open to Purchasing an Assignment Contract" :value="$displayAssignment" />
                                @endif

                                @if (@$auction->get->sale_provision_assignment === 'Yes' && @$auction->get->assignment_fee_amount)
                                    @php
                                        /*
                                         | The fee formatter moved out of the row's <span> and into a
                                         | value. It was the last place on this page still echoing
                                         | from inside markup; the three branches are carried over
                                         | unchanged and only their destination differs.
                                         */
                                        $feeType = @$auction->get->assignment_fee_type;
                                        $feeAmount = @$auction->get->assignment_fee_amount;
                                        if ($feeType === '$' || $feeType === 'dollar' || empty($feeType)) {
                                            $byaAssignmentFee = $fmtMoney($feeAmount);
                                        } elseif ($feeType === '%' || $feeType === 'percent') {
                                            $byaAssignmentFee = $fmtPercent($feeAmount);
                                        } else {
                                            $byaAssignmentFee = $fmtMoney($feeAmount) . ' ' . $feeType;
                                        }
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Assignment Fee to Broker" :value="$byaAssignmentFee" />
                                @endif
                            @endif

                            <!-- Target Closing Date -->
                            @if (@$auction->get->target_closing_date != null)
                                @php
                                    $displayClosingDate = str_replace('"', '', $toStr(@$auction->get->target_closing_date));
                                @endphp
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Target Closing Date" :value="$displayClosingDate" />
                            @endif

                            <!-- Maximum Budget -->
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Maximum Budget" :value="@$auction->get->maximum_budget != null ? '$' . number_format((float) str_replace(',', '', @$auction->get->maximum_budget)) : null" />

                            {{-- M7 Phase 2 — the financing array and the seven per-type data flags are
                                 hoisted to the block at the top of this section, because the nav needs
                                 $hasAnyFinancingDetails and the grouped displays further down need the
                                 individual flags. Definitions moved verbatim; see the note there. --}}
</x-hire-agent.detail-section>
@endif

{{-- M7 Phase 2 — Financing Details becomes a section card.

     THE CONDITION IS THE SECTION'S OWN, UNCHANGED, and it has two clauses for a reason the audit
     measured: $hasAnyFinancingDetails alone does NOT cover everything this section renders. Two
     pieces escape it — the "Offered Financing/Currency" row, which triggers on `offered_financing`
     alone, and the loan-type sub-heading, which triggers on $hasAnyLoanType while the boolean's loan
     clause additionally demands pre_approved or pre_approval_amount. Gating the card on the boolean
     alone would hide both. The second clause covers them, and in fact subsumes the first: every
     clause of $hasAnyFinancingDetails needs $financingArray to be non-empty, and $financingArray is
     derived solely from `offered_financing`. The `||` is kept rather than reduced because the two
     clauses mean different things — "has substantive detail" versus "named a financing type" — and
     collapsing them would throw away a distinction a later nav decision may want.

     THE NAV MUST USE THIS SAME EXPRESSION. Reading only $hasAnyFinancingDetails would omit an entry
     for a section that renders, which is the both-directions invariant HireAgentSectionNavTest
     enforces. Both operands are available before the nav: the boolean is hoisted to the top block
     and `offered_financing` is a direct meta read.

     NO EMPTY CARD IS REACHABLE. Whenever either clause is true, `offered_financing` is set, and the
     "Offered Financing/Currency" row renders unconditionally on it — so the card always has at
     least one row. Verified by rendering every financing type in isolation.

     $hasAnyLoanType / $selectedLoanTypes STAY WHERE THEY ARE, at their original site below. Nothing
     here needs hoisting: the first is subsumed by the second clause above, and the second is a
     DISPLAY value — it prints as the loan-type sub-heading's text — so it belongs beside the markup
     that renders it rather than in the preparation block. That is the difference from $repRows,
     which had to move because the nav needs the row set itself. --}}
@if (! ($byaDetailRedesign ?? false) || $byaShows('financing'))
<x-hire-agent.detail-section :redesign="$byaDetailRedesign ?? false" :legacy-header="false" id="hla-section-financing" title="Financing Details:" icon="fa-solid fa-file-contract">
                            @if($hasAnyFinancingDetails || @$auction->get->offered_financing != null)
                            {{-- The legacy heading, and the `col-12` wrapper that is unique to this
                                 section's header. Suppressed under the redesign, where the card
                                 title supplies the heading — emitting both would show it twice. --}}
                            @if (! ($byaDetailRedesign ?? false))
                                <hr>
                                <div class="col-12">
                                    <x-viho.section-header title="Financing Details:" tag="h4" />
                                </div>
                            @endif
                            @endif

                            <!-- Offered Financing/Currency - Now inside Financing Details section -->
                            @if (@$auction->get->offered_financing != null)
                                @php
                                    $financingItems = \App\Helpers\ListingDisplayHelper::normalizeListDeduped(@$auction->get->offered_financing, @$auction->get->other_financing);
                                    $financingOrder = ['Assumable','Cash','Conventional','Cryptocurrency','Exchange/Trade','FHA','Jumbo','Lease Option','Lease Purchase','No-Doc','Non-QM','NFT','Non-Fungible Token (NFT)','Seller Financing','USDA','VA'];
                                    usort($financingItems, function($a, $b) use ($financingOrder) {
                                        $aIdx = array_search($a, $financingOrder);
                                        $bIdx = array_search($b, $financingOrder);
                                        if ($aIdx === false && strtolower($a) === 'other') return 1;
                                        if ($bIdx === false && strtolower($b) === 'other') return -1;
                                        if ($aIdx === false) $aIdx = 999;
                                        if ($bIdx === false) $bIdx = 999;
                                        return $aIdx - $bIdx;
                                    });
                                @endphp
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Offered Financing/Currency" :bare-slot="true" :list-value="$financingItems">
                                    @if (count($financingItems) === 1)
                                        <span class="removeBold">{{ $financingItems[0] }}</span>
                                    @else
                                        @foreach ($financingItems as $fItem)
                                            <span class="removeBold badge bg-secondary">{{ $fItem }}</span>
                                        @endforeach
                                    @endif
                                </x-hire-agent.field>
                            @endif

                            @php
                                /*
                                 | Every money row in this section spelled the same conversion inline
                                 | — strip the thousands separators the user typed, cast, re-format,
                                 | prefix a dollar sign. Named once here so the eleven call sites
                                 | below read as values rather than as arithmetic. It is the same
                                 | expression each of them already carried, moved and not altered;
                                 | $fmtMoney further up this file belongs to the Terms block and
                                 | applies a different rule to a null, so it is deliberately not
                                 | reused here.
                                 */
                                $byaMoney = function ($value) {
                                    return \App\Helpers\ListingDisplayHelper::hasValue($value)
                                        ? '$' . number_format((float) str_replace(',', '', $value))
                                        : null;
                                };

                                /* Same shape for the percent rows, which appended a literal '%'. */
                                $byaPercent = function ($value) {
                                    return \App\Helpers\ListingDisplayHelper::hasValue($value)
                                        ? $value . '%'
                                        : null;
                                };
                            @endphp

                            <!-- Cash Financing Details -->
                            @if (in_array('Cash', $financingArray) && @$auction->get->cash_budget)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Cash Terms</h6>
                                </div>
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Offered Cash Amount" :value="$byaMoney(@$auction->get->cash_budget)" />
                            @endif

                            <!-- Assumable Financing Details -->
                            @if (in_array('Assumable', $financingArray) && $hasAssumableData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Assumable Financing Interest</h6>
                                </div>
                                @php
                                    /*
                                     | Resolved in a block rather than inline, because the value
                                     | strips a double quote and a double quote cannot appear inside
                                     | a Blade attribute expression — it would close the attribute.
                                     | Every other de-quoting row in this section is written the same
                                     | way for the same reason.
                                     */
                                    $byaAssumableInterest = @$auction->get->assumable_interest
                                        ? str_replace('"', '', $toStr(@$auction->get->assumable_interest))
                                        : null;
                                @endphp
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Interested in Assumable Financing" :value="$byaAssumableInterest" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Maximum Acceptable Interest Rate" :value="$byaPercent(@$auction->get->assumable_max_interest_rate)" />
                                {{-- Literal & in the label: the legacy row spelled it as the HTML
                                     entity because it WAS raw HTML. The component escapes the label,
                                     so a literal here produces the identical entity on output. --}}
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Maximum Monthly Payment (P&I)" :value="$byaMoney(@$auction->get->assumable_max_monthly_payment)" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Cash Available to Bridge the Gap" :value="$byaMoney(@$auction->get->assumable_bridge_gap_cash)" />
                            @endif

                            @php
                                $loanTypes = ['Conventional', 'FHA', 'Jumbo', 'VA', 'No-Doc', 'Non-QM', 'USDA'];
                                $selectedLoanTypes = array_values(array_intersect($loanTypes, $financingArray));
                                $hasAnyLoanType = count($selectedLoanTypes) > 0;
                            @endphp
                            @if ($hasAnyLoanType)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">{{ implode(' / ', $selectedLoanTypes) }}</h6>
                                </div>
                                @php
                                    $byaPreApproved = @$auction->get->pre_approved
                                        ? \App\Helpers\ListingDisplayHelper::formatYesParenthetical(
                                            @$auction->get->pre_approved,
                                            $byaMoney(@$auction->get->pre_approval_amount)
                                        )
                                        : null;
                                @endphp
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Buyer Pre-Approved for a Loan" :value="$byaPreApproved" />
                            @endif

                            <!-- Cryptocurrency Details - ONLY SHOW IF offered_financing IS "Cryptocurrency" -->
                            @if (in_array('Cryptocurrency', $financingArray) && $hasCryptoData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Cryptocurrency Terms</h6>
                                </div>
                                @php
                                    /*
                                     | The crypto rows each carried their own @php block computing a
                                     | de-quoted display string; they are collected here so the rows
                                     | below can be one line each. Every transform is the one that
                                     | row already applied, and each stays behind the same emptiness
                                     | test — expressed now as a ternary yielding null, which is the
                                     | condition the component itself applies.
                                     |
                                     | These must live in a block rather than inline: they strip a
                                     | double quote, which cannot appear in a Blade attribute.
                                     */
                                    $displayCryptoType      = @$auction->get->cryptocurrency_type ? str_replace('"', '', $toStr(@$auction->get->cryptocurrency_type)) : null;
                                    $displayCryptoExchange  = @$auction->get->crypto_exchange_method ? str_replace('"', '', $toStr(@$auction->get->crypto_exchange_method)) : null;
                                    $displayCryptoCustodian = @$auction->get->crypto_custodian_wallet ? str_replace('"', '', $toStr(@$auction->get->crypto_custodian_wallet)) : null;
                                    $displayCryptoFees      = @$auction->get->crypto_transaction_fees ? str_replace('"', '', $toStr(@$auction->get->crypto_transaction_fees)) : null;

                                    /*
                                     | Timing keeps its two-branch rule: the custom text wins when
                                     | the answer is "Other" AND that text exists, otherwise the
                                     | answer itself. Collapsed to one value because it was always
                                     | one field with two sources, never two fields.
                                     */
                                    $displayCryptoTiming      = @$auction->get->crypto_transfer_timing ? str_replace('"', '', $toStr(@$auction->get->crypto_transfer_timing)) : null;
                                    $displayCryptoTimingOther = str_replace('"', '', $toStr(@$auction->get->crypto_transfer_timing_other ?? ''));
                                    $byaCryptoTiming = (@$auction->get->crypto_transfer_timing === 'Other' && $displayCryptoTimingOther)
                                        ? $displayCryptoTimingOther
                                        : $displayCryptoTiming;
                                @endphp
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Offered Cryptocurrency" :value="$displayCryptoType" />
                                {{-- Half span, same reasoning as the assignment answer above: a
                                     percentage is structurally three or four characters whatever the
                                     listing says, so a full-width value column is empty by
                                     construction. These two are also a natural pair — crypto share
                                     and cash share of the same purchase price — and at half width
                                     they sit side by side on one line, which reads as the pair they
                                     are rather than as two unrelated rows. --}}
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Percentage of Purchase Price to be Paid with Cryptocurrency" :value="$byaPercent(@$auction->get->crypto_percentage)" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Percentage of Purchase Price to be Paid with Cash" :value="$byaPercent(@$auction->get->cash_percentage_crypto)" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Exchange / Conversion Method" :value="$displayCryptoExchange" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Custodian / Wallet for Transfer" :value="$displayCryptoCustodian" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Transaction Fees Responsibility" :value="$displayCryptoFees" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Timing of Transfer" :value="$byaCryptoTiming" />
                            @endif

                            <!-- Exchange/Trade Details - ONLY SHOW IF offered_financing IS "Exchange/Trade" -->
                            @if (in_array('Exchange/Trade', $financingArray) && $hasExchangeData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Exchange/Trade Terms</h6>
                                </div>
                                @if (@$auction->get->exchange_item)
                                    @php
                                        $displayExchangeItem = str_replace('"', '', $toStr(@$auction->get->exchange_item));
                                        $displayOtherExchange = str_replace('"', '', $toStr(@$auction->get->other_exchange_item ?? ''));
                                        $exchangeItemIsOther = is_array(@$auction->get->exchange_item)
                                            ? in_array('Other', @$auction->get->exchange_item)
                                            : (@$auction->get->exchange_item === 'Other');

                                        /* One field, two sources — the same collapse as Timing of
                                           Transfer above, and the same rule: custom text wins only
                                           when the answer is "Other" AND that text exists. */
                                        $byaExchangeItem = ($exchangeItemIsOther && @$auction->get->other_exchange_item)
                                            ? $displayOtherExchange
                                            : $displayExchangeItem;
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Acceptable Exchange Item" :value="$byaExchangeItem" />
                                @endif

                                @if (@$auction->get->exchange_item_value)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Estimated Value of Exchange/Trade Item" :value="$byaMoney(@$auction->get->exchange_item_value)" />
                                @endif

                                @if (@$auction->get->exchange_item_condition)
                                    @php
                                        $displayExchangeCondition = str_replace('"', '', $toStr(@$auction->get->exchange_item_condition));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Condition of Exchange/Trade Item" :value="$displayExchangeCondition" />
                                @endif

                                @if (@$auction->get->additional_cash)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Additional Cash Buyer Will Offer" :value="$byaMoney(@$auction->get->additional_cash)" />
                                @endif

                                @if (@$auction->get->value_determination)
                                    @php
                                        $displayValueDetermination = str_replace('"', '', $toStr(@$auction->get->value_determination));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Value of Exchange/Trade Item Determined By" :value="$displayValueDetermination" />
                                @endif

                                @if (@$auction->get->exchange_transfer_method)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Transfer Method / Logistics" :value="@$auction->get->exchange_transfer_method" />
                                @endif

                                @if (@$auction->get->exchange_liens)
                                    @php
                                        /* The answer, with its detail in parentheses when there is
                                           one — two spans in the legacy row, one value here. The
                                           space between them is the one the browser rendered from
                                           the newline separating the two spans. */
                                        $byaExchangeLiens = @$auction->get->exchange_liens
                                            . ((@$auction->get->exchange_liens === 'Yes' && @$auction->get->exchange_liens_details)
                                                ? ' (' . @$auction->get->exchange_liens_details . ')'
                                                : '');
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Liens / Encumbrances Disclosure" :value="$byaExchangeLiens" />
                                @endif

                                @if (@$auction->get->exchange_inspection_rights)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Inspection / Verification Rights" :value="@$auction->get->exchange_inspection_rights" />
                                @endif
                            @endif

                            <!-- Lease Option Details - ONLY SHOW IF offered_financing IS "Lease Option" -->
                            @if (in_array('Lease Option', $financingArray) && $hasLeaseOptionData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Lease Option Terms</h6>
                                </div>
                                {{-- 1. Buyer's Desired Offering Price --}}
                                @if (@$auction->get->lease_option_price)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Buyer's Desired Offering Price for Lease Option" :value="$byaMoney(@$auction->get->lease_option_price)" />
                                @endif

                                {{-- 2. Monthly Payment --}}
                                @if (@$auction->get->lease_option_payment)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Monthly Payment Buyer is Offering" :value="$byaMoney(@$auction->get->lease_option_payment)" />
                                @endif

                                {{-- 3. Proposed Duration --}}
                                @if (@$auction->get->lease_option_duration)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Proposed Duration of Lease (Months)" :value="@$auction->get->lease_option_duration" />
                                @endif

                                {{-- 4. Offered Option Fee (inline with amount) --}}
                                @if (@$auction->get->has_option_fee)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Offered Option Fee" :value="\App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->has_option_fee, @$auction->get->option_fee_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->option_fee_amount)) : null)" />
                                @endif

                                {{-- 5. Option Fee Credit --}}
                                @if (@$auction->get->lease_option_fee_credit)
                                    @php
                                        $displayFeeCredit = str_replace('"', '', $toStr(@$auction->get->lease_option_fee_credit));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Option Fee Credit Toward Purchase Price" :value="$displayFeeCredit" />
                                @endif

                                {{-- 5a. Percentage of Option Fee Credited (conditional) --}}
                                @if (@$auction->get->lease_option_fee_credit === 'Partial' && @$auction->get->lease_option_fee_credit_percentage)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Percentage of Option Fee Credited" :value="$byaPercent(@$auction->get->lease_option_fee_credit_percentage)" />
                                @endif

                                {{-- 6. Conditions or Requirements --}}
                                @if (@$auction->get->lease_option_conditions)
                                    @php
                                        $displayLeaseConditions = str_replace('"', '', $toStr(@$auction->get->lease_option_conditions));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Conditions or Requirements for Lease Option" :value="$displayLeaseConditions" />
                                @endif

                                {{-- 7. Specific Terms Proposed --}}
                                @if (@$auction->get->lease_option_terms)
                                    @php
                                        $displayLeaseTerms = str_replace('"', '', $toStr(@$auction->get->lease_option_terms));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Specific Terms Proposed for Lease Option" :value="$displayLeaseTerms" />
                                @endif

                                {{-- 8. Maintenance / Repair Responsibility --}}
                                @if (@$auction->get->lease_option_maintenance)
                                    @php
                                        $displayMaintenance = str_replace('"', '', $toStr(@$auction->get->lease_option_maintenance));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Maintenance / Repair Responsibility" :value="$displayMaintenance" />
                                @endif

                                {{-- 9. Extension Terms --}}
                                @if (@$auction->get->lease_option_extension_terms)
                                    @php
                                        $displayExtension = str_replace('"', '', $toStr(@$auction->get->lease_option_extension_terms));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Extension Terms" :value="$displayExtension" />
                                @endif
                            @endif

                            <!-- Lease Purchase Details - ONLY SHOW IF offered_financing IS "Lease Purchase" -->
                            @if (in_array('Lease Purchase', $financingArray) && $hasLeasePurchaseData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Lease Purchase Terms</h6>
                                </div>
                                {{-- 1. Buyer's Desired Offering Price --}}
                                @if (@$auction->get->lease_purchase_price)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Buyer's Desired Offering Price for Lease Purchase" :value="$byaMoney(@$auction->get->lease_purchase_price)" />
                                @endif

                                {{-- 2. Monthly Payment --}}
                                @if (@$auction->get->lease_purchase_payment)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Monthly Payment Buyer is Offering" :value="$byaMoney(@$auction->get->lease_purchase_payment)" />
                                @endif

                                {{-- 3. Proposed Duration --}}
                                @if (@$auction->get->lease_purchase_duration)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Proposed Duration of Lease (Months)" :value="@$auction->get->lease_purchase_duration" />
                                @endif

                                {{-- 4. Rent Credit Toward Purchase Price (inline with amount) --}}
                                @if (@$auction->get->lease_purchase_rent_credit)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Rent Credit Toward Purchase Price" :value="\App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->lease_purchase_rent_credit, in_array(@$auction->get->lease_purchase_rent_credit, ['Yes', 'Partial']) && @$auction->get->lease_purchase_rent_credit_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->lease_purchase_rent_credit_amount)) : null)" />
                                @endif

                                {{-- 5. Non-Refundable Deposit --}}
                                @if (@$auction->get->lease_purchase_deposit)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Non-Refundable Deposit / Purchase Deposit" :value="$byaMoney(@$auction->get->lease_purchase_deposit)" />
                                @endif

                                {{-- 6. Conditions or Requirements --}}
                                @if (@$auction->get->lease_purchase_conditions)
                                    @php
                                        $displayLeasePurchaseConditions = str_replace('"', '', $toStr(@$auction->get->lease_purchase_conditions));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Conditions or Requirements for Lease Purchase" :value="$displayLeasePurchaseConditions" />
                                @endif

                                {{-- 7. Specific Terms Proposed --}}
                                @if (@$auction->get->lease_purchase_terms)
                                    @php
                                        $displayLeasePurchaseTerms = str_replace('"', '', $toStr(@$auction->get->lease_purchase_terms));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Specific Terms Proposed for Lease Purchase" :value="$displayLeasePurchaseTerms" />
                                @endif

                                {{-- 8. Maintenance / Repair Responsibility --}}
                                @if (@$auction->get->lease_purchase_maintenance)
                                    @php
                                        $displayLPMaintenance = str_replace('"', '', $toStr(@$auction->get->lease_purchase_maintenance));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Maintenance / Repair Responsibility" :value="$displayLPMaintenance" />
                                @endif

                                {{-- 9. Extension Terms --}}
                                @if (@$auction->get->lease_purchase_extension_terms)
                                    @php
                                        $displayLPExtension = str_replace('"', '', $toStr(@$auction->get->lease_purchase_extension_terms));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Extension Terms" :value="$displayLPExtension" />
                                @endif
                            @endif

                            <!-- NFT Details - ONLY SHOW IF offered_financing IS "Non-Fungible Token (NFT)" -->
                            @if (in_array('Non-Fungible Token (NFT)', $financingArray) && $hasNftData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Non-Fungible Token (NFT) Terms</h6>
                                </div>
                                @if (@$auction->get->nft_description)
                                    @php
                                        $displayNFTDescription = str_replace('"', '', $toStr(@$auction->get->nft_description));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Offered Non-Fungible Token (NFT)" :value="$displayNFTDescription" />
                                @endif

                                @if (@$auction->get->nft_percentage)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Percentage of Purchase Price to be Paid with NFT" :value="$byaPercent(@$auction->get->nft_percentage)" />
                                @endif

                                @if (@$auction->get->cash_percentage_nft)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Percentage of Purchase Price to be Paid with Cash" :value="$byaPercent(@$auction->get->cash_percentage_nft)" />
                                @endif

                                @if (@$auction->get->nft_valuation_method)
                                    @php
                                        $displayNFTValuation = str_replace('"', '', $toStr(@$auction->get->nft_valuation_method));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="NFT Valuation Method" :value="$displayNFTValuation" />
                                @endif

                                @if (@$auction->get->nft_transfer_method)
                                    @php
                                        $displayNFTTransfer = str_replace('"', '', $toStr(@$auction->get->nft_transfer_method));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="NFT Transfer Method" :value="$displayNFTTransfer" />
                                @endif

                                @if (@$auction->get->nft_gas_fees)
                                    @php
                                        $displayNFTGasFees = str_replace('"', '', $toStr(@$auction->get->nft_gas_fees));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Transaction Fees Responsibility (Gas Fees)" :value="$displayNFTGasFees" />
                                @endif
                            @endif

                            <!-- Seller Financing Details -->
                            @if (in_array('Seller Financing', $financingArray) && $hasSellerFinancingData)
                                <div class="col-12 mt-3 mb-1">
                                    <h6 class="financing-subsection-header">Seller Financing Terms</h6>
                                </div>
                                @if (@$auction->get->purchase_price)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Desired Purchase Price" :value="$byaMoney(@$auction->get->purchase_price)" />
                                @endif

                                @php
                                    /*
                                     | These two rows are the only amounts on the page whose UNIT is
                                     | itself an answer: the same number is a dollar figure or a
                                     | percentage depending on a companion `_type` field, so the
                                     | legacy markup composed three echoes — prefix, number, suffix
                                     | — inside one span. That is why neither could go through
                                     | $byaMoney or $byaPercent, and why both are built here instead
                                     | of inline. The three parts and their conditions are carried
                                     | over exactly; only the seam moved.
                                     */
                                    $byaTypedAmount = function ($type, $amount) {
                                        $formatted = number_format((float) str_replace(',', '', $amount));
                                        return $type === '%' ? $formatted . '%' : '$' . $formatted;
                                    };
                                @endphp
                                @if (@$auction->get->down_payment_amount)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Desired Down Payment" :value="$byaTypedAmount(@$auction->get->down_payment_type, @$auction->get->down_payment_amount)" />
                                @endif

                                @if (@$auction->get->seller_financing_amount)
                                    {{-- Half span, to match "Desired Down Payment" directly above it.
                                         Both rows are built by the same $byaTypedAmount closure and
                                         carry the same shape of value — a formatted amount or a
                                         percentage — so one of them rendering full width and the
                                         other half was an inconsistency in this card rather than a
                                         judgement about either field. --}}
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Desired Seller Financing Amount" :value="$byaTypedAmount(@$auction->get->seller_financing_type, @$auction->get->seller_financing_amount)" />
                                @endif

                                @if (@$auction->get->interest_rate)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Desired Interest Rate" :value="$byaPercent(@$auction->get->interest_rate)" />
                                @endif

                                @if (@$auction->get->loan_duration)
                                    @php
                                        $displayLoanDuration = str_replace('"', '', $toStr(@$auction->get->loan_duration));
                                    @endphp
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Desired Loan Duration" :value="$displayLoanDuration" />
                                @endif

                                {{-- Prepayment Penalty (inline with amount) --}}
                                @if (@$auction->get->prepayment_penalty)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Prepayment Penalty" :value="\App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->prepayment_penalty, @$auction->get->prepayment_penalty_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->prepayment_penalty_amount)) : null)" />
                                @endif

                                {{-- Balloon Payment (inline with amount) --}}
                                @if (@$auction->get->balloon_payment)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Balloon Payment" :value="\App\Helpers\ListingDisplayHelper::formatYesParenthetical(@$auction->get->balloon_payment, @$auction->get->balloon_payment_amount ? '$' . number_format((float) str_replace(',', '', @$auction->get->balloon_payment_amount)) : null)" />

                                @if (@$auction->get->balloon_payment === 'Yes')
                                    @if (@$auction->get->balloon_payment_date)
                                        @php
                                            $displayBalloonDate = str_replace('"', '', $toStr(@$auction->get->balloon_payment_date));
                                        @endphp
                                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Balloon Payment Due Date" :value="$displayBalloonDate" />
                                    @endif
                                @endif
                                @endif

                                @php
                                    /* The "Other" collapse once more, for the last two rows that
                                       carried it in markup. Same rule, same output. */
                                    $byaAmortizationType = (@$auction->get->seller_amortization_type === 'Other' && @$auction->get->seller_amortization_other)
                                        ? @$auction->get->seller_amortization_other
                                        : @$auction->get->seller_amortization_type;

                                    $byaPaymentFrequency = (@$auction->get->seller_payment_frequency === 'Other' && @$auction->get->seller_payment_frequency_other)
                                        ? @$auction->get->seller_payment_frequency_other
                                        : @$auction->get->seller_payment_frequency;
                                @endphp
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Amortization Type" :value="$byaAmortizationType" />
                                <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Payment Frequency" :value="$byaPaymentFrequency" />

                                @if (@$auction->get->seller_late_fee_amount)
                                    <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Late Payment Fee" :value="@$auction->get->seller_late_fee_amount" />
                                @endif
                            @endif
{{-- End of the Financing Details section. The nine financing sub-headings above stay h6
     sub-headings INSIDE this one card rather than becoming cards of their own: they are divisions
     within a single subject, and landlord's decomposition kept its eleven compensation sub-headings
     the same way. --}}
</x-hire-agent.detail-section>
@endif








@if (! ($byaDetailRedesign ?? false))                        <hr>
@endif
{{-- M7 Phase 6 — Additional Details becomes a card. Public tier: it is part of the request. --}}
@if (! ($byaDetailRedesign ?? false) || $byaShows('additional-details'))
<x-hire-agent.detail-section :redesign="$byaDetailRedesign ?? false" :legacy-header="false" id="hla-section-additional-details" title="Additional Details:" icon="fa-solid fa-circle-info">
                        @if (@$auction->get->additional_details != null)
@if (! ($byaDetailRedesign ?? false))                            <x-viho.section-header title="Additional Details:" tag="h4" />
@endif

                            {{-- Full width: this is free prose the buyer typed, not a short answer,
                                 so a half-width cell would wrap it into a column two words wide.
                                 The enclosing @if is kept rather than folded into the section
                                 guard — unlike landlord's, it also gates the legacy section-header
                                 above, and dropping it would print that heading over an empty
                                 card whenever the flag is off. --}}
                            <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" span="full" label="Additional Details" :value="$auction->get->additional_details ?? ''" />
                        @endif
</x-hire-agent.detail-section>
@endif

                        {{-- C9: Representation Preferences & Compatibility display (public; parity with
                             tenant hire view). The builder — closures, accumulator and all fourteen
                             repAdd calls — is hoisted to the block at the top of this section so the
                             nav, this section's condition and these rows all read the one $repRows.
                             It moved verbatim; see the note there. --}}

{{-- $repRows is the single source of truth for all three consumers: nav visibility, section
     visibility and the rendered rows. There is no derived boolean beside it to drift from. --}}
@if (! ($byaDetailRedesign ?? false) || $byaShows('representation'))
<x-hire-agent.detail-section :redesign="$byaDetailRedesign ?? false" :legacy-header="false" id="hla-section-representation" title="Representation Preferences & Compatibility:" icon="fa-solid fa-handshake">
                        @if (!empty($repRows))
@if (! ($byaDetailRedesign ?? false))                        <hr />
@endif
                        {{-- Literal & in the prop: Blade escapes it back to &amp; on output, so the
                             rendered text is unchanged. Passing &amp; here would double-escape it. --}}
@if (! ($byaDetailRedesign ?? false))                        <x-viho.section-header title="Representation Preferences & Compatibility:" tag="h4" />
@endif
                        {{-- $repRows is already built as label/value pairs, and every pair in it has
                             a value: the builder drops empties before this loop, which is why
                             !empty($repRows) is a complete guard for the section and why the nav can
                             share it. The rows still route through the adapter rather than emitting
                             their own markup, so a pair that ever did arrive empty disappears here
                             instead of printing a bare label. --}}
                        @foreach ($repRows as $repRow)
                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" :label="$repRow['label']" :value="$repRow['value']" />
                        @endforeach
                        @endif
</x-hire-agent.detail-section>
@endif

{{-- THE STRAY LEGACY CLOSER, KEPT WHEN BROKER COMPENSATION WAS REMOVED.

     This `</div>` does NOT belong to the deleted section. The legacy branch of this page carries
     one more `</div>` than `<div>`, and this is it: it closes a wrapper opened further up, and it
     happened to live inside the compensation block because that block sat at the right depth. It
     is emitted in the flag-off branch only, exactly as before.

     REMOVING IT WITH THE SECTION WAS TRIED AND REVERTED. The wrapper then stayed open through the
     rest of the page and the sidebar rendered one level deeper than its recorded baseline, which
     HireAgentShellStructureTest caught. The right-shaped fix is to close the wrapper where it is
     opened rather than to leave a stray closer in the markup — that is a legacy-DOM repair with
     its own blast radius, and it is not this change. Retained verbatim so the flag-off page is
     byte-comparable outside the two removed sections. --}}
@if (! ($byaDetailRedesign ?? false))                        </div>
@endif

                        {{-- M7 Phase 6 — $referralPct / $referralPctDisplay are hoisted to the
                             preparation block, where the resolver reads them. Moved verbatim,
                             query included; see the note there. --}}
{{-- M7 Phase 6 — REFERRAL & COOPERATION BECOMES AN AGENT-ONLY SECTION.

     A referral fee is agent-to-agent business: it is what one agent pays another for handing over
     a client. It renders to every visitor today, anonymous included, which is the widest it could
     possibly be. Under the redesign the resolver withholds it from the public AND the owner tiers.

     THE OWNER IS EXCLUDED DELIBERATELY, and it is the surprising half. The owner tier is not
     "everyone who is logged in and relevant" — it is the client evaluating proposals, and a
     referral arrangement between two agents is not part of that evaluation. The registry is where
     that decision lives; this file only asks whether the section survived.

     Legacy is untouched: with the redesign off this still renders for everyone, exactly as it
     always has, and its heading stays pinned in the flag-off order. --}}
@if (! ($byaDetailRedesign ?? false) || $byaShows('referral'))
<x-hire-agent.detail-section :redesign="$byaDetailRedesign ?? false" :legacy-header="false" id="hla-section-referral" title="Referral & Cooperation Terms" icon="fa-solid fa-share-nodes">
                        @if ($referralPctDisplay !== '')
@if (! ($byaDetailRedesign ?? false))                        <hr />
@endif
@if (! ($byaDetailRedesign ?? false))                        <x-viho.section-header title="Referral & Cooperation Terms" tag="h4" />
@endif
                        {{-- The section's guard IS this field's emptiness test, and the nav shares it,
                             so the row needs no guard of its own. Landlord's counterpart is the same
                             single line. --}}
                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Referral Fee" :value="$referralPctDisplay" />
                        @endif
</x-hire-agent.detail-section>
@endif
{{-- M7 Phase 6 — ROLE INFO becomes a card.

     Public tier: who posted the request is part of the request. Its heading is the one label that
     is a fact about a LISTING rather than a role — "Buyer's Info" or "Agent's Info" — so the
     resolver receives it as an override rather than reading it from config. --}}
@if (! ($byaDetailRedesign ?? false) || $byaShows('role-info'))
<x-hire-agent.detail-section :redesign="$byaDetailRedesign ?? false" :legacy-header="false" id="hla-section-role-info" :title="$byaRoleInfoHeading" icon="fa-solid fa-id-card">
@if (! ($byaDetailRedesign ?? false))                        <hr />
@endif
@if (! ($byaDetailRedesign ?? false))                        <x-viho.section-header :title="$_ownerInfoHeading" tag="h4" />
@endif
                        {{-- THE ONLY TWO LABEL/VALUE FIELDS IN THIS SECTION. Everything below is
                             media: a video element, an image and a link embed, each in its own
                             col-md-6 cell. Those are deliberately NOT routed through the field
                             adapter — a 5/7 grid positions a short text answer beside its label, and
                             putting a 29vh video in the value column would size the media to 58% of
                             a half-width cell and label it like a data point. They keep their own
                             markup and their own guards, exactly as landlord's do.

                             Buyer carries one field landlord does not: "Buyer's Current Status" has
                             no landlord counterpart, and its presence is content rather than drift.
                             $byaHasOwnerInfo already enumerates it, so the guard and the rows agree. --}}
                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="First Name" :value="@$auction->get->first_name" />
                        <x-hire-agent.field :redesign="$byaDetailRedesign ?? false" label="Buyer's Current Status" :value="@$auction->get->current_status" />

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
</x-hire-agent.detail-section>
@endif

{{-- M7 Phase 6 — AGENT CREDENTIALS & CONTACT INFO. A NEW SECTION, not a migrated one.

     No Hire Agent detail view renders anything like this today, so there is no legacy branch to
     preserve: the whole block sits inside the redesign guard and emits nothing with the flag off.
     That is why it carries none of the `@if (! $byaDetailRedesign)` fallbacks every section above
     it has.

     THE LISTING OWNER'S credentials, and only when that owner is an agent — never the viewer's own
     and never the hired agent's. It is the counterpart to Referral & Cooperation directly above:
     the terms of a referral, and who the agent on the other side of it is. $byaHasAgentCredentials
     carries both halves of that (the owner is an agent, and has at least one field to show), and
     the resolver additionally withholds the section from anyone below the agent tier.

     The fields are read off the User record, where `info()` resolves each from EAV meta with the
     column as a fallback. Each row guards itself, so a partly filled profile shows what it has.
     Nothing here is new data: these are the same values author.blade.php already publishes on the
     public profile page. --}}
@if ($byaDetailRedesign && $byaShows('agent-credentials'))
<x-hire-agent.detail-section :redesign="true" :legacy-header="false" id="hla-section-agent-credentials" title="Agent Credentials & Contact Info" icon="fa-solid fa-address-card">
                        {{-- The redesign is not a branch here, it is the only branch: this section is
                             gated `$byaDetailRedesign &&` above, so :redesign="true" is a statement of
                             fact rather than a flag read. The rows carried legacy markup anyway, which
                             made them the only ones on the page rendering the flag-off shape with no
                             flag-off render to preserve. Landlord's identical section is the reference
                             — same four fields, same order, same literal true. --}}
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
            {{-- M3: this closed `div.card.description`; the card closes here instead. The closer
                 above it still ends the final row, and the inner card-body keeps whatever closer
                 the parser already pairs it with — neither is touched.

                 M7 Phase 4: the closer follows its opener into x-hire-agent.detail-body. The card
                 it closes is emitted by that component in the flag-off branch only; with the
                 redesign on there is no wrapper here to close. --}}
            </x-hire-agent.detail-body>
                {{-- Milestone 5A.2-B: a third </div> closed .leftCol here, before the review card
                     below. That made "card review" a direct child of the .row despite having no
                     col-* class, and left the row's own closer to land before .rightCol — so the
                     sidebar rendered outside the grid row entirely. Dropping this closer keeps
                     .leftCol open through the review card; the existing closer further down then
                     ends .leftCol, and .rightCol becomes its sibling inside the row, matching
                     Seller and Landlord. --}}
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
                     | THIS ROLE IS WHERE THE GUARD CAME FROM. The `@if($auser)` below predates M7
                     | and was the only one of the four; landlord, seller and tenant adopted this
                     | spelling rather than inventing a second. What buyer did NOT have is the name
                     | half of it: a resolvable row with no usable name identifies nobody, and
                     | rendered a bold empty anchor where the name belongs. Added here so all four
                     | now agree.
                     */
                    $hlaOwnerName = trim((string) ($auser->name ?? ''));

                    if ($auser && $hlaOwnerName === '') {
                        $hlaOwnerName = trim(($auser->first_name ?? '') . ' ' . ($auser->last_name ?? ''));
                    }
                @endphp
                <!-- Review  -->
                @if($auser && $hlaOwnerName !== '')
                {{-- Same chrome hook the landlord view carries (its M7.3), added only in the
                     redesigned branch so the flag-off DOM is unchanged. Without it this card is the
                     one node still rendering the theme's Bootstrap chrome beside a column of viho
                     cards, which is what made it read as belonging to a different page. --}}
                <div class="card review{{ $byaDetailRedesign ? ' hla-surface-card' : '' }}">
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
                            <a href="{{ route('auction-chat', ['buyer-agent', $auction->id]) }}"><button class="btn">Message</button></a>
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
        // Hoisted out of the identity block below so this assignment happens in BOTH treatments.
        // Later sidebar code reads $auth_id, and leaving it inside a block that the redesigned hero
        // suppresses would silently change those reads the moment the hero is enabled for buyer.
        // Mirrors the landlord view. The directive emits no output, so hoisting it cannot alter the
        // legacy rendering.
        $auth_id = auth()->id();
    @endphp

    {{--
        THE SIDEBAR SURFACE — chrome parity with the landlord pilot (M7.5 there).

        Everything from here to the proposal console is one card. Before this the buyer sidebar was
        a bare stack — heading, badges, rules and buttons sitting directly on the page background
        beside a main column made entirely of cards.

        WHERE IT CLOSES. Above the proposal console, which stays a SIBLING rather than a child. The
        console brings its own `.card` chrome, so nesting it would render border inside border and
        shadow inside shadow; and its contents are gated by HireAgentProposalAccess, so keeping it
        outside this wrapper means no geometry rule added here has a selector that can reach a
        proposal card. That fence is deliberate and is not crossed by this change.

        AND IT IS WHAT MAKES THE STICKY WORK. A sidebar column carrying a populated console is as
        tall as the main column, and an element that is never shorter than its container never
        sticks. This card is short by construction, because the thing that made the column tall is
        now beside it rather than in it.

        Redesign-only, so with the detail flag off the sidebar emits exactly the bytes it did before.
    --}}
    @if ($byaDetailRedesign)
    <div class="hla-surface-card hla-sidebar-card hla-sidebar-sticky" data-hire-agent-sidebar-card>
    @endif

    {{--
        The sidebar identity block.

        Title, listing id, status and Edit Listing move INTO the hero when the hero redesign is on
        for buyer, so this block renders only when it is off. What is avoided is duplication:
        without this guard the page would carry two <h1> elements, two status pills and two Edit
        controls, which is worse than either treatment alone.

        Gated on the HERO flag, not the detail flag — the two roll out independently by design, and
        this block's counterpart lives in the hero. Reading the detail flag here would suppress the
        identity block for a role whose hero is still off, leaving the page with no title at all.
    --}}
    @unless (\App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('buyer'))
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
                    <a href="{{ route('buyer.edit-auction', ['auctionId' => $auction->id]) }}"
                       class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
                    </a>
                </div>
                @endif
    @endunless
    {{-- This rule only ever separated the identity block from what follows. Under the redesign the
         sidebar is a card, and the card's own edge and padding are the separation the rule stood in
         for — so it is suppressed there and left exactly as-is for the legacy branch. --}}
    @unless ($byaDetailRedesign)
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
        // bidder. Not restored in any form. On this view that line had NO viewer gate at all —
        // every visitor saw it — which is why the rule now lives server-side.
        // $auction->bids is already narrowed to this viewer's authorized proposals by
        // HireAgentProposalAccess in BuyerAgentAuctionController::viewAuctionDetails().
        $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp


        {{-- 📩 Message Button --}}
        <a href="{{ route('auction-chat', ['buyer-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message
        </a>


        {{--
            Milestone 3: the Days / Hrs / Mins / Secs countdown block stood here, along with the
            "Bidding Ended" pill it fell back to. Both are retired. The listing's state is already
            carried by the status pill above (Active / Pending / Expired / Hired Agent) and by the
            expiry notice below, neither of which counts down. No replacement urgency mechanism is
            introduced — that is the point of the retirement, not an omission.
        --}}



        @php
        $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
        // Safe is_sold check: raw DB column is varchar ('0','false','true','1') — never use raw value as bool
        $isSold = in_array($auction->is_sold, [true, 'true', 1, '1'], true);
        @endphp

        {{-- 🔹 Bid Button --}}
        @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
        @if (!$isExpired && !$isSold && $auction->status !== 'Pending' && $auction->status !== 'Hired Agent')
        @if ($userHasBid)
        {{-- User already placed a bid --}}
        <div class="alert alert-info text-center mb-2">
            <i class="fa-solid fa-circle-check"></i> You have already placed a bid
        </div>
        <div class="status-pill status-disabled w-100 d-flex justify-content-between">
            <span>Bid Already Placed</span>
            <span style="font-weight:normal;font-size:.85em;">${{ @$auction->get->budget }}</span>
        </div>

        {{-- Optional: Allow editing their bid --}}
        <!-- <button class="btn w-100 btn-outline-primary mt-2"
                onclick="window.location='{{ route('agent.tenant.agent.auction.bid', @$auction->id) }}';">
                <i class="fa-solid fa-pen-to-square"></i> Edit Your Bid
            </button> -->
        @else
        {{-- User can place a bid --}}
        <button class="btn w-100 bid-btn"
            onclick="window.location='{{ route('agent.buyer.agent.auction.bid', @$auction->id) }}';">
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

        @php
            $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
            $bidsByOrder    = $auction->bids->sortBy('created_at');
            // Key by user_id so the same agent always gets the same number.
            $agentNumberMap     = [];  // bid_id  → agent number
            $agentUserNumberMap = [];  // user_id → agent number
            $agentIdx           = 0;
            foreach ($bidsByOrder as $orderedBid) {
                $uid = data_get($orderedBid, 'user_id');
                if (!isset($agentUserNumberMap[$uid])) {
                    $agentIdx++;
                    $agentUserNumberMap[$uid] = $agentIdx;
                }
                $agentNumberMap[$orderedBid->id] = $agentUserNumberMap[$uid];
            }
        @endphp

    {{-- The sidebar card closes HERE, above the proposal console. The console is its sibling, not
         its child: it brings its own card chrome, and its contents are gated by
         HireAgentProposalAccess. Nesting it would double the border and shadow, and would put a
         geometry rule from this change in reach of a proposal card. See the block that opens the
         wrapper for the full reasoning. --}}
    @if ($byaDetailRedesign)
    </div>
    @endif

        {{--
            Milestone 2 — the "Agent N was the last bidder." line was removed here. It is not
            restored in any form. The empty state it shared an @if with is retained, but gated
            on the server-side owner decision: a bid count is itself a disclosure, so "No agents
            have submitted a bid yet." is owner-only rather than public.
        --}}
        @if (($canReviewAllProposals ?? false) && $auction->bids->isEmpty())
            <p class="mb-3">No agents have submitted a bid yet.</p>
        @endif

        <div class="card higestBider" id="bids-section">
            <div class="card-body card-body-padding">
                <div id="buyerBidsList">
                                @php
                                // Reload meta once before bid loop to guarantee fresh listing baseline from DB.
                                $auction->load('meta');
                                @endphp

                                {{-- Owner-only: this second empty state is a count disclosure too. --}}
                                @if (($canReviewAllProposals ?? false) && $auction->bids->isEmpty())
                                    <p class="mb-3">No agents have submitted a bid yet.</p>
                                @endif

                                @foreach (@$auction->bids as $bid)
                                    @php
                                        $bidId = data_get($bid, 'id');
                                        $bidUser = data_get($bid, 'user_id');
                                        $isBidOwner = ($bidUser == $auth_id);
                                        $_isAgentViewer = $auth_id && auth()->check() && in_array(auth()->user()->user_type ?? '', ['agent']);
                                        // Milestone 2 — competing-agent proposal privacy.
                                        // $auction->bids was narrowed by HireAgentProposalAccess in the
                                        // controller. This guard is defence-in-depth with the opposite
                                        // default to the one it replaced: skip anything that is not the
                                        // owner's to review or the viewer's own.
                                        if (! $isListingOwner && ! $isBidOwner) { continue; }
                                        $agentNumber = $agentNumberMap[$bidId] ?? $loop->iteration;
                                        $bidAccepted = data_get($bid, 'accepted', '0');
                                        $isExpiredBid = $isExpired ?? false;
                                        $canEditWithdraw = $isBidOwner && !$isExpiredBid && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected';
                                        $servicesList  = (array) data_get($bid, 'get.services', []);
                                        $servicesCount = count($servicesList);
                                        $commissionSummary = data_get($bid, 'commission_structure', data_get($bid, 'get.commission_structure', ''));
                                        $headerBg = $bidAccepted === 'accepted' ? '#d4edda' : ($bidAccepted === 'rejected' ? '#f8d7da' : '#f8f9fa');

                                        // === MATCH SCORE — baseline-driven (BuyerBidMatchScoreHelper) ===
                                        // $auction->meta reloaded before loop; $bid->get queries DB directly on each call.
                                        $auctionPropType = data_get($auction, 'get.property_type', '');
                                        $listingBaselineData = $auction->meta->pluck('meta_value', 'meta_key')->toArray();
                                        $currentBidData = (array) $bid->get;

                                        // Check for buyer-countered terms (BuyerCounterTerm) — buyer counters the agent.
                                        // buyer_agent_auction_id = auction ID; parent_counter_id = bid ID (per-bid scope).
                                        $latestBuyerCounter = \App\Models\BuyerCounterTerm::with('meta')
                                            ->where('buyer_agent_auction_id', $auction->id)
                                            ->where('parent_counter_id', $bidId)
                                            ->orderBy('created_at', 'desc')
                                            ->first();

                                        // Check for agent-countered terms (BuyerCounterBidding) — agent counters the buyer
                                        $latestAgentCounter = \App\Models\BuyerCounterBidding::with('meta')
                                            ->where('buyer_agent_auction_bid_id', $bidId)
                                            ->orderBy('created_at', 'desc')
                                            ->first();

                                        // Card score ALWAYS uses original listing baseline to ensure consistent
                                        // denominator across all bids on the same listing.
                                        $baselineData = $listingBaselineData;

                                        // Check if bid is in countered state
                                        $hasCounterBids = $latestBuyerCounter || $latestAgentCounter;

                                        // Direction: true = listing OWNER sent the most recent counter.
                                        // BuyerCounterTerm ($latestBuyerCounter) = owner's counter.
                                        // BuyerCounterBidding ($latestAgentCounter) = bidding agent's counter-back.
                                        $_buyerCardLatestFromOwner = false;
                                        if ($latestBuyerCounter && $latestAgentCounter) {
                                            $_buyerCardLatestFromOwner = $latestBuyerCounter->created_at >= $latestAgentCounter->created_at;
                                        } elseif ($latestBuyerCounter) {
                                            $_buyerCardLatestFromOwner = true; // only owner counter exists
                                        }
                                        // elseif only $latestAgentCounter → agent sent latest → remains false
                                        $bidStatusDisplay = match($bidAccepted) {
                                            'accepted' => 'Accepted',
                                            'rejected' => 'Rejected',
                                            'countered' => 'Countered',
                                            default => $hasCounterBids ? 'Countered' : 'Active',
                                        };
                                        $bidStatusColor = match($bidStatusDisplay) {
                                            'Accepted' => '#28a745',
                                            'Rejected' => '#dc3545',
                                            'Countered' => '#ffc107',
                                            default => '#1a4a6e',
                                        };

                                        $score = \App\Helpers\BuyerBidMatchScoreHelper::calculate($baselineData, $currentBidData, null, $auctionPropType);
                                        $overallScore     = $score['overall_percent'];
                                        $scoreColor       = \App\Helpers\BuyerBidMatchScoreHelper::scoreColor((int)$overallScore);
                                        $brokerMismatches = $score['changed_terms'] ?? [];
                                        $brokerAdded      = $score['added_terms'] ?? [];
                                        $buyerBaselineLabel = $isListingOwner ? 'Your Original Terms' : "Buyer's Original Request";
                                        $servicesExtraCount = $score['services_extra_count'] ?? 0;
                                        $matchedServices  = $score['matched_services'] ?? [];
                                        $missingServices  = $score['missing_services'] ?? [];
                                        $extraServices    = $score['extra_services'] ?? [];
                                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                                        $mismatchBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.7rem; vertical-align: middle;">Mismatch</span>';
                                    @endphp
                                    <!-- Bid Card - Collapsible Accordion Design -->
                                    <div class="card mb-3" style="border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                                    <!-- A) Card Header - Clickable to expand/collapse (using custom JS toggle) -->
                                    <div class="card-header d-flex justify-content-between align-items-center hla-bid-accordion-header"
                                        style="cursor: pointer; background: #fff; border-bottom: 1px solid #e0e0e0; padding: 15px 20px;"
                                        data-target="bidCollapse-{{ $bidId }}"
                                        aria-expanded="false">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-chevron-down bid-chevron" style="transition: transform 0.3s; color: #1a3a5c;"></i>
                                            <h5 class="mb-0" style="font-weight: 700; color: #1a3a5c; font-size: 1.4rem;">Agent {{ $agentNumber }}</h5>
                                        </div>
                                        <span style="font-weight: 600; color: {{ $bidStatusColor }}; font-size: 1.1rem;">{{ $bidStatusDisplay }}</span>
                                    </div>

                                    <!-- Collapsible Content - Default collapsed (custom toggle, no Bootstrap) -->
                                    <div class="bid-collapse-content" id="bidCollapse-{{ $bidId }}" style="display: none;">

                                        @php
                                            // Pre-compute compact card variables (Tenant-parity)
                                            $cardServicesMatched   = $score['services_matched_count'] ?? 0;
                                            $cardServicesTotal     = $score['services_baseline_total'] ?? 0;
                                            $cardServicesExtraCount = $score['services_extra_count'] ?? 0;

                                            // Determine if we have a dual-score situation (latest buyer counter or agent counter exists)
                                            $cardShowDualScore = false;
                                            $cardOriginalScore = null;
                                            $cardLatestCounterScore = null;
                                            $cardCounterLabel = 'vs. Latest Counter Terms';
                                            if ($latestBuyerCounter) {
                                                // $score is already listing-based; compute counter comparison separately
                                                $cardOriginalScore = $score;
                                                $cardLatestCounterScore = \App\Helpers\BuyerBidMatchScoreHelper::calculate($latestBuyerCounter->getAllMeta(), $currentBidData, null, $auctionPropType);
                                                $cardCounterLabel = $isListingOwner ? 'vs. Your Counter Terms' : "vs. Buyer's Counter Terms";
                                                $cardShowDualScore = true;
                                            } elseif ($latestAgentCounter) {
                                                // Listing owner has sent a counter offer via BuyerCounterBidding
                                                // Left: bid vs original listing; Right: bid vs owner's counter offer terms
                                                $cardOriginalScore = $score;
                                                $agentCounterMeta = $latestAgentCounter->getAllMeta();
                                                $cardLatestCounterScore = \App\Helpers\BuyerBidMatchScoreHelper::calculate($agentCounterMeta, $currentBidData, null, $auctionPropType);
                                                $cardCounterLabel = $isListingOwner ? 'vs. Your Counter Offer' : "vs. Owner's Counter Offer";
                                                $cardShowDualScore = true;
                                            }
                                            $cardGetScoreColor = fn($pct) => \App\Helpers\BuyerBidMatchScoreHelper::scoreColor((int)$pct);

                                            // Match score visibility: listing owner OR bid owner.
                                            // Milestone 3: the third disjunct was
                                            // ($isBiddingPeriodListing && $cardIsAgentViewer && $userHasBid) — a
                                            // Bidding Period carve-out letting any agent who had bid see a
                                            // competitor's match score. It retires with the bidding period, which
                                            // also finishes what Milestone 2 started: competing-proposal data is
                                            // now owner-or-own-bid only, with no listing-type exception.
                                            $cardShowMatchScoreOnCard = $isListingOwner || $isBidOwner;
                                            /**
                                             * ZERO-BASELINE / NO-DATA GUARD
                                             *
                                             * If there is no comparable baseline match data, do not display 100%.
                                             * Render "No match data available" instead.
                                             *
                                             * This behavior is locked by QA baseline documentation.
                                             * Reference: qa_reports/QA_LOCK_BidComparison_v1.md
                                             */
                                            $cardHasAnyBaseline = (($score['broker_comp_total'] ?? 0) > 0 || $cardServicesTotal > 0);

                                        @endphp

                                        <div class="card-body" style="padding: 20px;">
                                            <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">

                                            {{-- Counter Offer Notice Banner — visible immediately on accordion expand (owner/agent only) --}}
                                            @if (($latestAgentCounter || $latestBuyerCounter) && ($isListingOwner || $isBidOwner))
                                            <div class="alert d-flex align-items-start gap-2 mb-3 py-2 px-3"
                                                 style="background: #fff8e1; border: 1px solid #ffc107; border-left: 4px solid #ffc107; border-radius: 6px; font-size: 0.9rem;">
                                                <i class="fa-solid fa-right-left mt-1" style="color: #e6a800; flex-shrink: 0;"></i>
                                                <div>
                                                    @if ($_buyerCardLatestFromOwner && $isListingOwner)
                                                        <strong>Counter Offer Sent.</strong>
                                                    @elseif ($_buyerCardLatestFromOwner && $isBidOwner)
                                                        <strong>Counter Offer Received.</strong>
                                                    @elseif (!$_buyerCardLatestFromOwner && $isBidOwner)
                                                        <strong>Counter Offer Sent.</strong>
                                                    @elseif (!$_buyerCardLatestFromOwner && $isListingOwner)
                                                        <strong>Counter Offer Received.</strong>
                                                    @endif
                                                </div>
                                            </div>
                                            @endif

                                            {{-- ── Counter action row — directly on bid card ── --}}
                                            @if (($latestAgentCounter || $latestBuyerCounter) && ($isListingOwner || $isBidOwner) && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected')
                                            @if (($_buyerCardLatestFromOwner && $isListingOwner) || (!$_buyerCardLatestFromOwner && $isBidOwner))
                                            {{-- WAITING: single row — View CT + Edit CT --}}
                                            <div class="d-flex gap-2 align-items-center mb-2">
                                                <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                                </a>
                                                @if ($isListingOwner)
                                                <a href="{{ route('buyer.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                                </a>
                                                @else
                                                <a href="{{ route('agent.buyer.hire.agent.auction.counter-bid', ['id' => $auction->id, 'bid_id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                                </a>
                                                @endif
                                            </div>
                                            @else
                                            {{-- RESPONSE: View CT only — Accept/Counter Back/Reject are on View Counter Terms page --}}
                                            <div class="d-flex align-items-center mb-2">
                                                <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                    <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                                </a>
                                            </div>
                                            @endif
                                            @endif

                                            <!-- Offered Services Summary Line -->
                                            <p class="mb-0" style="font-size: 1.1rem; color: #1a3a5c;">
                                                <span style="font-weight: 600;">Offered Services:</span>
                                                <span style="color: #28a745; font-weight: 600;">{{ $cardServicesTotal > 0 ? $cardServicesMatched.'/'.$cardServicesTotal : 'No services requested' }}</span>{{ $cardServicesTotal > 0 ? ' matched' : '' }}
                                                @if ($cardServicesTotal > 0 && $cardServicesExtraCount > 0)
                                                    <span class="text-muted ms-2">&bull; {{ $cardServicesExtraCount }} extra</span>
                                                @endif
                                                @if ($cardServicesTotal > 0 && count($missingServices) > 0)
                                                    <span class="ms-2" style="color: #dc3545;">&bull; {{ count($missingServices) }} missing</span>
                                                @endif
                                            </p>
                                            @if ($cardServicesExtraCount > 0)
                                            <div class="mt-2 d-flex align-items-center flex-wrap" style="gap: 4px 6px;">
                                                <span style="font-size: 0.9rem; line-height: 1.4;">&#11088;</span>
                                                <span style="font-weight: 500; color: #856404; font-size: 0.95rem;" title="Extra services were included by the Agent beyond the Buyer&#39;s original request. These do not increase the match score but may provide additional value.">Extra Value Added: {{ $cardServicesExtraCount }} {{ $cardServicesExtraCount === 1 ? 'Service' : 'Services' }}</span>
                                                <span class="text-muted" style="font-size: 0.78rem; font-style: italic;">&mdash; does not affect match score</span>
                                            </div>
                                            @endif

                                            <!-- Terms Match Row -->
                                            @php
                                                $cardBrokerTotal = $score['broker_comp_total'] ?? 0;
                                                $cardBrokerMatched = $score['broker_comp_matched'] ?? 0;
                                                $cardTermsChangedCount = $score['terms_changed_count'] ?? 0;
                                                $cardTermsAddedCount = $score['terms_added_count'] ?? 0;
                                            @endphp
                                            @if ($cardHasAnyBaseline && $cardBrokerTotal > 0)
                                            <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                                <span style="font-weight: 600;">Terms Match:</span>
                                                <span style="color: #28a745; font-weight: 600;">{{ $cardBrokerMatched }}/{{ $cardBrokerTotal }} matched</span>
                                                @if ($cardTermsChangedCount > 0)
                                                <span class="ms-2" style="color: #dc3545;">&bull; {{ $cardTermsChangedCount }} changed</span>
                                                @endif
                                                @if ($cardTermsAddedCount > 0)
                                                <span class="text-muted ms-2">&bull; {{ $cardTermsAddedCount }} added</span>
                                                @endif
                                                @php $cardTermsMissingCount = max(0, $cardBrokerTotal - $cardBrokerMatched - $cardTermsChangedCount); @endphp
                                                @if ($cardTermsMissingCount > 0)
                                                <span class="ms-2" style="color: #dc3545;">&bull; {{ $cardTermsMissingCount }} missing</span>
                                                @endif
                                            </p>
                                            <div class="mt-1" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&mdash; affects match score</div>
                                            @elseif ($cardHasAnyBaseline && $cardBrokerTotal === 0)
                                            <p class="mb-0 mt-2" style="font-size: 1.1rem; color: #1a3a5c;">
                                                <span style="font-weight: 600;">Terms Match:</span>
                                                <span class="text-muted">&mdash;</span>
                                            </p>
                                            @endif

                                            <hr style="margin: 15px 0; border-color: #e0e0e0;">

                                            <!-- B2) Match Score Summary (Compact Display on Bid Card) -->
                                            @if ($cardShowMatchScoreOnCard && $cardHasAnyBaseline)
                                            <div class="match-score-summary mb-3 p-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6; font-size: 0.88rem;">
                                                @if ($cardShowDualScore && $cardOriginalScore && $cardLatestCounterScore)
                                                {{-- DUAL SCORE: Original Match + Latest Counter Match --}}
                                                <div class="mb-2">
                                                    <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                                        <i class="fa-solid fa-chart-pie me-2"></i>Match Summary
                                                    </span>
                                                </div>
                                                <div class="row g-2 mb-2">
                                                    @php $osColor = $cardGetScoreColor($cardOriginalScore['overall_percent']); @endphp
                                                    <div class="col-6">
                                                        <div class="p-2 rounded" style="background: #fff; border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                                                                <span class="badge" style="background: {{ $osColor }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $cardOriginalScore['overall_percent'] }}%</span>
                                                            </div>
                                                            <div style="font-size: 0.75rem; color: #6c757d;">vs. Buyer's Original Request</div>
                                                            <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                                <div class="col-6" style="color: {{ $cardGetScoreColor($cardOriginalScore['services_match_percent']) }};">Services {{ $cardOriginalScore['services_match_percent'] }}%</div>
                                                                <div class="col-6" style="color: {{ $cardGetScoreColor($cardOriginalScore['terms_match_percent']) }};">Terms {{ $cardOriginalScore['terms_match_percent'] }}%</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @php $lcColor = $cardGetScoreColor($cardLatestCounterScore['overall_percent']); @endphp
                                                    <div class="col-6">
                                                        <div class="p-2 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColor }};">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                                                                <span class="badge" style="background: {{ $lcColor }}; font-size: 0.8rem; padding: 3px 8px; color: white;">{{ $cardLatestCounterScore['overall_percent'] }}%</span>
                                                            </div>
                                                            <div style="font-size: 0.75rem; color: #6c757d;">{{ $cardCounterLabel }}</div>
                                                            <div class="row g-0 mt-1" style="font-size: 0.75rem;">
                                                                <div class="col-6" style="color: {{ $cardGetScoreColor($cardLatestCounterScore['services_match_percent']) }};">Services {{ $cardLatestCounterScore['services_match_percent'] }}%</div>
                                                                <div class="col-6" style="color: {{ $cardGetScoreColor($cardLatestCounterScore['terms_match_percent']) }};">Terms {{ $cardLatestCounterScore['terms_match_percent'] }}%</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="small" style="color: #6c757d; font-style: italic; font-size: 0.76rem;">
                                                    <i class="fa-solid fa-circle-info me-1"></i>Added services or terms do not increase either score.
                                                </div>
                                                @else
                                                {{-- SINGLE SCORE --}}
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;">
                                                        <i class="fa-solid fa-chart-pie me-2"></i>Match Score
                                                    </span>
                                                    <span class="badge" style="background: {{ $scoreColor }}; font-size: 1rem; padding: 6px 12px; color: white;">
                                                        {{ $overallScore }}%
                                                    </span>
                                                </div>
                                                <div class="row g-2 small">
                                                    <div class="col-6">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="text-muted">Services Match:</span>
                                                            <span style="color: {{ $cardGetScoreColor($score['services_percent'] ?? 100) }}; font-weight: 600;">{{ $score['services_percent'] ?? 100 }}%</span>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.8rem;">
                                                            {{ ($score['services_baseline_total'] ?? 0) > 0 ? 'Matched: '.($score['services_matched_count'] ?? 0).'/'.($score['services_baseline_total'] ?? 0) : 'No services requested' }}
                                                            @if (($score['services_baseline_total'] ?? 0) > 0 && $cardServicesExtraCount > 0) &bull; Extra: {{ $cardServicesExtraCount }}@endif
                                                            @if (($score['services_baseline_total'] ?? 0) > 0 && count($missingServices) > 0) &bull; Missing: {{ count($missingServices) }}@endif
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="text-muted">Terms Match:</span>
                                                            <span style="color: {{ $cardGetScoreColor($score['broker_comp_percent'] ?? 100) }}; font-weight: 600;">{{ $score['broker_comp_percent'] ?? 100 }}%</span>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.8rem;">
                                                            {{ ($score['broker_comp_total'] ?? 0) > 0 ? 'Matched: '.($score['broker_comp_matched'] ?? 0).'/'.($score['broker_comp_total'] ?? 0) : 'No terms provided' }}
                                                            @if (($score['broker_comp_total'] ?? 0) > 0 && ($score['terms_changed_count'] ?? 0) > 0) &bull; Changed: {{ $score['terms_changed_count'] }}@endif
                                                            @if (($score['broker_comp_total'] ?? 0) > 0 && ($score['terms_added_count'] ?? 0) > 0) &bull; Added: {{ $score['terms_added_count'] }}@endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-2 small text-muted">
                                                    <i class="fa-solid fa-circle-info me-1"></i>Compared to: {{ $buyerBaselineLabel }}
                                                </div>
                                                <div class="mt-1 small" style="color: #6c757d; font-style: italic; font-size: 0.78rem;">
                                                    Match Score compares this bid to the Buyer's original request. Added services or terms do not increase the score.
                                                </div>
                                                @endif
                                            </div>
                                            @endif

                                            <!-- D) View Full Bid Link - visibility rules by listing type and user -->
                                            @if ($isListingOwner || $isBidOwner)
                                            {{-- Listing Owner or Bid Owner: Full access --}}
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
                                               style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                                View Full Bid
                                            </a>
                                            {{--
                                                Milestone 2 — the "View Full Services & Broker Compensation Terms"
                                                branch was removed here. It opened the Limited Bid Modal on a
                                                COMPETITOR's proposal for any agent who had bid on a Bidding
                                                Period listing. The modal itself is removed further down.
                                            --}}
                                            @else
                                            <span style="color: #888; font-style: italic; font-size: 0.95rem;">
                                                <i class="fa-solid fa-lock me-1"></i> Private - visible only to listing creator
                                            </span>
                                            @endif

                                            <!-- E) Edit Actions for Bid Owner - Same row, matched sizing -->
                                            @if ($canEditWithdraw)
                                            <div class="d-flex gap-2 mt-3 justify-content-end align-items-center">
                                                <a href="{{ route('agent.buyer.agent.auction.bid', $auction->id) }}?edit={{ $bidId }}" class="btn btn-primary hla-bid-action-btn">
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
                                                    $bidOwnerSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', $bidId)->where('agent_user_id', data_get($bid, 'user_id'))->first();
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
                                        </div>

                                                    <!-- PRIVATE DATA SECTION - Visible to listing owner and bid owner -->
                                                    @if (data_get($auction, 'user_id') == $auth_id || data_get($bid, 'user_id') == $auth_id)

                                                        <!-- Private Data Modal -->
                                                        <div class="modal fade"
                                                            id="privateDataModal{{ data_get($bid, 'id') }}"
                                                            tabindex="-1"
                                                            aria-labelledby="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                                            aria-hidden="true">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content"
                                                                    style="border-radius: 10px; border: none;">
                                                                    <div class="modal-header text-white"
                                                                        style="background: #049399; border-bottom: none; padding: 20px;">
                                                                        <h5 class="modal-title"
                                                                            id="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                                                            style="font-weight: 600;">
                                                                            <i class="fa-solid fa-lock me-2"></i> Private
                                                                            Compensation & Agreement Terms
                                                                        </h5>
                                                                    </div>
                                                                    <div class="modal-body"
                                                                        style="background: #fafafa; padding: 25px;">
                                                                        @include('partials.bid_detail_body.buyer')
                                                                    </div>
                                                                    @php
                                                                        // Compute modal-footer state — available variables: $bidAccepted, $auction, $bid, $auth_id
                                                                        $_mfRawB    = data_get($bid, 'accepted', '0');
                                                                        $_mfTermB   = in_array((string)$_mfRawB, ['accepted', 'rejected'], true);
                                                                        // Use $hasCounterBids (computed in outer bid loop) — catches counters from
                                                                        // either the listing owner (BuyerCounterTerm) or the bidding agent (BuyerCounterBidding).
                                                                        $_mfCounterB = !$_mfTermB && $hasCounterBids;
                                                                        $mfStateB   = $_mfCounterB
                                                                            ? 'countered'
                                                                            : (in_array($_mfRawB, [null, 0, '0', ''], true) ? '0' : (string)$_mfRawB);
                                                                        $mfOwnerIdB    = data_get($auction, 'user_id');
                                                                        $mfOwnerFirstB = data_get($auction, 'user.first_name', '');
                                                                        $mfOwnerLastB  = data_get($auction, 'user.last_name', '');
                                                                        $mfAgentFirstB = data_get($bid, 'user.first_name', '');
                                                                        $mfAgentLastB  = data_get($bid, 'user.last_name', '');
                                                                        $mfIsOwnerB    = ((int)$auth_id === (int)$mfOwnerIdB);
                                                                    @endphp
                                                                    <div class="modal-footer"
                                                                        style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; flex-wrap: wrap; gap: 12px;">

                                                                        {{-- Confidential notice --}}
                                                                        <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                                                            <i class="fa-solid fa-shield-halved me-2"></i>
                                                                            <strong>Confidential:</strong> This information is private and only visible to you.
                                                                        </div>

                                                                        {{-- ── Listing owner: action buttons when bid is undecided ── --}}
                                                                        @if ($mfStateB === '0' && $mfIsOwnerB && !$isSold)
                                                                            {{-- Milestone 3: was ($isTraditionalListing && $isExpired). The
                                                                                 listing-type qualifier is gone with the timer — expiry is
                                                                                 expiry, whatever the old auction_type said. --}}
                                                                            @if ($isExpired)
                                                                            <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                                                                                <i class="fa-solid fa-calendar-xmark me-1"></i> Listing has expired — no further actions available. You can extend the expiration date by editing the listing.
                                                                            </div>
                                                                            @else
                                                                            <div class="d-flex gap-3 justify-content-center align-items-center w-100" style="flex-wrap: nowrap;">
                                                                                <form action="{{ route('buyer.hire.agent.auction.bid.accept') }}" method="POST" style="margin: 0;"
                                                                                      onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
                                                                                    @csrf
                                                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                                    <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                                                                    <button type="submit" class="btn btn-success" style="padding: 10px 20px; font-size: 0.95rem; background-color: #28a745 !important; border-color: #28a745 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                                                        <i class="fa-solid fa-check me-1"></i> Accept Bid
                                                                                    </button>
                                                                                </form>
                                                                                <a href="{{ route('buyer.counter-terms', data_get($bid, 'id')) }}"
                                                                                   class="btn btn-primary" style="padding: 10px 20px; font-size: 0.95rem; background-color: #0d6efd !important; border-color: #0d6efd !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                                                    <i class="fa-solid fa-right-left me-1"></i> Counter Bid
                                                                                </a>
                                                                                <form action="{{ route('buyer.hire.agent.auction.bid.reject') }}" method="POST" style="margin: 0;"
                                                                                      onsubmit="return confirm('Are you sure you want to reject this bid?');">
                                                                                    @csrf
                                                                                    <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                                    <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                                                                    <button type="submit" class="btn btn-danger" style="padding: 10px 20px; font-size: 0.95rem; background-color: #dc3545 !important; border-color: #dc3545 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                                                        <i class="fa-solid fa-xmark me-1"></i> Reject Bid
                                                                                    </button>
                                                                                </form>
                                                                            </div>
                                                                            @endif
                                                                        @endif

                                                                        {{-- ── Accepted state ── --}}
                                                                        @if ($mfStateB === 'accepted')
                                                                        <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
                                                                            <i class="fa-solid fa-circle-check me-1"></i>
                                                                            @if ($mfIsOwnerB) This bid has been accepted.
                                                                            @else {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }} accepted this bid.
                                                                            @endif
                                                                        </div>
                                                                        @php $mfBidSummaryB = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->where('agent_user_id', data_get($bid, 'user_id'))->first(); @endphp
                                                                        @if ($mfBidSummaryB && ($mfIsOwnerB || data_get($bid, 'user_id') == Auth::id()))
                                                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                                                            <a href="{{ route('accepted-bid-summary.view', $mfBidSummaryB->id) }}" class="btn btn-outline-primary btn-sm">
                                                                                <i class="fa-solid fa-file-lines me-1"></i> View Accepted Bid Summary
                                                                            </a>
                                                                            @if (data_get($bid, 'user_id') == Auth::id() && !$mfBidSummaryB->isAgentSigned())
                                                                            <a href="{{ route('accepted-bid-summary.sign-form', $mfBidSummaryB->id) }}" class="btn btn-primary btn-sm">
                                                                                <i class="fa-solid fa-signature me-1"></i> Agent: E-Sign Acknowledgement
                                                                            </a>
                                                                            @endif
                                                                            @if ($mfIsOwnerB && !$mfBidSummaryB->isTenantSigned())
                                                                            <a href="{{ route('accepted-bid-summary.sign-form', $mfBidSummaryB->id) }}" class="btn btn-primary btn-sm">
                                                                                <i class="fa-solid fa-signature me-1"></i> Buyer: E-Sign Acknowledgement
                                                                            </a>
                                                                            @endif
                                                                            @if ($mfBidSummaryB->isFullySigned())
                                                                            <a href="{{ route('accepted-bid-summary.download-pdf', $mfBidSummaryB->id) }}" class="btn btn-success btn-sm">
                                                                                <i class="fa-solid fa-download me-1"></i> Download Signed PDF
                                                                            </a>
                                                                            @endif
                                                                        </div>
                                                                        @endif

                                                                        {{-- ── Rejected state ── --}}
                                                                        @elseif ($mfStateB === 'rejected')
                                                                        <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
                                                                            <i class="fa-solid fa-circle-xmark me-1"></i>
                                                                            @if ($mfIsOwnerB) This bid has been rejected.
                                                                            @else {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }} rejected this bid.
                                                                            @endif
                                                                        </div>

                                                                        {{-- ── Countered state ── --}}
                                                                        @elseif ($mfStateB === 'countered')
                                                                        @php
                                                                            // Viewer sent latest = owner viewer + owner sent latest, OR agent viewer + agent sent latest.
                                                                            $_mfBuyerViewerSentLatest = ($isListingOwner && $_buyerCardLatestFromOwner)
                                                                                                     || ($isBidOwner   && !$_buyerCardLatestFromOwner);
                                                                        @endphp
                                                                        <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                                                            <i class="fa-solid fa-right-left me-1"></i>
                                                                            @if ($_mfBuyerViewerSentLatest)
                                                                                <strong>Counter Offer Sent.</strong>
                                                                            @else
                                                                                <strong>Counter Offer Received.</strong>
                                                                            @endif
                                                                        </div>
                                                                        <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                                                                            @if ($_mfBuyerViewerSentLatest)
                                                                            {{-- Viewer sent latest — waiting: View CT + Edit CT --}}
                                                                            <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                                <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                                                            </a>
                                                                            <a href="{{ route('buyer.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                                                            </a>
                                                                            @else
                                                                            {{-- Other party sent latest: View CT only — actions on View Counter Terms page --}}
                                                                            <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                                <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                                                            </a>
                                                                            @endif
                                                                        </div>

                                                                        {{-- ── Pending state ── --}}
                                                                        @elseif ($mfStateB === '0')
                                                                        @if (data_get($bid, 'user_id') == Auth::id())
                                                                        <div class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                                                            ⏳ Waiting for a response from {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }}...
                                                                        </div>
                                                                        @else
                                                                        <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
                                                                            ⏳ Bid from {{ trim($mfAgentFirstB . ' ' . $mfAgentLastB) }} is pending.
                                                                        </div>
                                                                        @endif
                                                                        @endif

                                                                        {{-- ── Close button ── --}}
                                                                        <div class="w-100 d-flex justify-content-end mt-2">
                                                                            <button type="button" class="btn btn-secondary"
                                                                                data-bs-dismiss="modal"
                                                                                style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">Close</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    {{--
                                                        Milestone 2 — the Limited Bid Modal was removed here. Under a
                                                        Bidding Period listing it showed any agent who had bid the full
                                                        Services and Broker Compensation terms of every COMPETITOR's
                                                        proposal, plus that proposal's match score and a per-field
                                                        mismatch highlight — competing content, amounts and summary in
                                                        one surface. Its trigger link is removed with it.
                                                    --}}

                                                    <!-- Counter Bids -->

                                                    @php
                                                        $counterBids = \App\Models\BuyerCounterBidding::with(
                                                            'meta',
                                                            'user',
                                                        )
                                                            ->where('buyer_agent_auction_bid_id', data_get($bid, 'id'))
                                                            ->orderBy('created_at', 'desc')
                                                            ->get();
                                                    @endphp

                                                    @php
                                                        $rawState = data_get($bid, 'accepted', '0');
                                                        $_isTerminalBuyer = in_array((string)$rawState, ['accepted', 'rejected'], true);
                                                        // Per-bid counter check: buyer_agent_auction_id = auction ID, parent_counter_id = bid ID.
                                                        $_perBidBuyerCounterExists = !$_isTerminalBuyer && \App\Models\BuyerCounterTerm::where('buyer_agent_auction_id', data_get($auction, 'id'))
                                                            ->where('parent_counter_id', data_get($bid, 'id'))
                                                            ->exists();
                                                        $state = $_perBidBuyerCounterExists
                                                            ? 'countered'
                                                            : (in_array($rawState, [null, 0, '0'], true) ? '0' : (string) $rawState);
                                                        $isOwnerRow = data_get($auction, 'user_id') == $auth_id;

                                                        $ownerFirst = data_get($auction, 'user.first_name', '');
                                                        $ownerLast = data_get($auction, 'user.last_name', '');
                                                        $agentFirst = data_get($bid, 'user.first_name', '');
                                                        $agentLast = data_get($bid, 'user.last_name', '');

                                                        $ownerId = data_get($auction, 'user_id');

                                                        // Add access control for counter bids
                                                        $isListingOwner = data_get($auction, 'user_id') == $auth_id;
                                                        $isBidOwner = data_get($bid, 'user_id') == $auth_id;
                                                        $showCounterBids = $isListingOwner || $isBidOwner;
                                                    @endphp

                                                    {{-- Counter Bidding Section - Only visible to listing owner and bidding agent --}}
                                                    @if ($showCounterBids && $counterBids->count() > 0)
                                                    <div class="counter-bids-section mt-4" id="counter-section-{{ $bid->id }}">
                                                        <!-- Counter Bids Accordion Header -->
                                                        <div class="counter-bids-toggle"
                                                            style="cursor: pointer;"
                                                            onclick="event.stopPropagation(); var target = document.getElementById('counterBids{{ data_get($bid, 'id') }}'); var arrow = this.querySelector('.counter-arrow'); if(target.style.display === 'none' || target.style.display === '') { target.style.display = 'block'; arrow.style.transform = 'rotate(180deg)'; } else { target.style.display = 'none'; arrow.style.transform = 'rotate(0deg)'; }">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center flex-wrap p-2 border rounded">
                                                                <h5 class="mb-0" style="color: #2c3e50;">Counter
                                                                    Bidding History</h5>
                                                                <div class="d-flex align-items-center">
                                                                    <span
                                                                        class="badge bg-secondary me-2">{{ $counterBids->count() }}
                                                                        counter offers</span>
                                                                    <span class="counter-arrow" style="transition: transform 0.3s;">↓</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Counter Bids Accordion Content -->
                                                        <div id="counterBids{{ data_get($bid, 'id') }}"
                                                            class="counter-bids-content"
                                                            style="display: none;">
                                                            <div
                                                                class="accordion-body p-3 border border-top-0 rounded-bottom hla-counter-font">
                                                                    @foreach ($counterBids as $counterBid)
                                                                        @php
                                                                            // Roles
                                                                            $isOwner =
                                                                                data_get($auction, 'user_id') ==
                                                                                $auth_id;
                                                                            $isAgent =
                                                                                data_get($bid, 'user_id') == $auth_id;
                                                                            $isCounterFromOwner =
                                                                                $counterBid->user_id ==
                                                                                data_get($auction, 'user_id');
                                                                            $isCounterFromAgent =
                                                                                $counterBid->user_id ==
                                                                                data_get($bid, 'user_id');

                                                                            // States
                                                                            $rawBidState = data_get(
                                                                                $bid,
                                                                                'accepted',
                                                                                '0',
                                                                            );
                                                                            $bidState = in_array(
                                                                                $rawBidState,
                                                                                [null, 0, '0', 'no', 'pending'],
                                                                                true,
                                                                            )
                                                                                ? '0'
                                                                                : (string) $rawBidState;

                                                                            // BuyerCounterBidding has no status/accepted field — derive state from parent bid
                                                                            $counterState = $bidState;

                                                                            // Actions visibility (other party, both pending)
                                                                            $showCounterActions = false;
                                                                            if (
                                                                                $bidState === '0' &&
                                                                                $counterState === '0'
                                                                            ) {
                                                                                if ($isOwner && $isCounterFromAgent) {
                                                                                    $showCounterActions = true;
                                                                                }
                                                                                if ($isAgent && $isCounterFromOwner) {
                                                                                    $showCounterActions = true;
                                                                                }
                                                                            }

                                                                            // Names
                                                                            $ownerFirst = data_get(
                                                                                $auction,
                                                                                'user.first_name',
                                                                                '',
                                                                            );
                                                                            $ownerLast = data_get(
                                                                                $auction,
                                                                                'user.last_name',
                                                                                '',
                                                                            );
                                                                            $agentFirst = data_get(
                                                                                $bid,
                                                                                'user.first_name',
                                                                                '',
                                                                            );
                                                                            $agentLast = data_get(
                                                                                $bid,
                                                                                'user.last_name',
                                                                                '',
                                                                            );

                                                                            // For counter accepted/rejected: actor is ALWAYS the other party (not the creator)
                                                                            $actorUserId = $isCounterFromOwner
                                                                                ? data_get($bid, 'user_id')
                                                                                : data_get($auction, 'user_id');
                                                                            $actorFirst = $isCounterFromOwner
                                                                                ? $agentFirst
                                                                                : $ownerFirst;
                                                                            $actorLast = $isCounterFromOwner
                                                                                ? $agentLast
                                                                                : $ownerLast;

                                                                            // Creator names (for "pending" other-party view)
                                                                            $creatorFirst = data_get(
                                                                                $counterBid,
                                                                                'user.first_name',
                                                                                '',
                                                                            );
                                                                            $creatorLast = data_get(
                                                                                $counterBid,
                                                                                'user.last_name',
                                                                                '',
                                                                            );
                                                                        @endphp

                                                                        <div
                                                                            class="counter-bid-card mb-3 p-3 border rounded mt-2">
                                                                            <div
                                                                                class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                                                                <h6 class="mb-0">
                                                                                    @if ($counterBid->user_id == Auth::id())
                                                                                        Your Counter Offer
                                                                                    @else
                                                                                        Counter Offer from
                                                                                        {{ data_get($counterBid, 'user.first_name') }}
                                                                                        {{ data_get($counterBid, 'user.last_name') }}
                                                                                    @endif
                                                                                </h6>
                                                                                <small
                                                                                    class="text-muted">{{ optional($counterBid->created_at)->format('M j, Y g:i A') }}</small>
                                                                            </div>

                                                                           @php
                                                                            $allMeta = $counterBid->getAllMeta();

                                                                            // === COMPARISON HELPER: Check if counter value differs from original bid ===
                                                                            $isChanged = function($counterVal, $origKey) use ($bid) {
                                                                                $origVal = data_get($bid, 'get.' . $origKey, null);
                                                                                $normalizeVal = function($v) {
                                                                                    if (is_null($v) || $v === '') return '';
                                                                                    if (is_array($v) || is_object($v)) return json_encode($v);
                                                                                    $v = trim((string) $v);
                                                                                    return preg_replace('/[\s$,%]/', '', strtolower($v));
                                                                                };
                                                                                return $normalizeVal($counterVal) !== $normalizeVal($origVal);
                                                                            };

                                                                            // CSS for changed fields
                                                                            $changedStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                                                                            $changedBadge = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; vertical-align: middle;">Changed</span>';

                                                                            // Services diff (counter vs original bid)
                                                                            $ctrSvcsRaw = is_string($allMeta['services'] ?? '') ? json_decode($allMeta['services'] ?? '', true) ?? [] : ($allMeta['services'] ?? []);
                                                                            $ctrSvcsRaw = array_filter((array)$ctrSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other');
                                                                            $origBidSvcsRaw = (array) data_get($bid, 'get.services', []);
                                                                            if (is_string(data_get($bid, 'get.services', []))) $origBidSvcsRaw = json_decode(data_get($bid, 'get.services', '[]'), true) ?: [];
                                                                            $origBidSvcsRaw = array_filter($origBidSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other');
                                                                            $normSvc = fn($s) => strtolower(trim((string)$s));
                                                                            $origBidSvcsNorm = array_map($normSvc, array_values($origBidSvcsRaw));
                                                                            $ctrSvcsNorm = array_map($normSvc, array_values($ctrSvcsRaw));
                                                                            $ctrSvcIsAdded = fn(string $s): bool => !in_array($normSvc($s), $origBidSvcsNorm, true);
                                                                            $ctrRemovedSvcs = array_values(array_filter($origBidSvcsRaw, fn($s) => !in_array($normSvc($s), $ctrSvcsNorm, true)));
                                                                            $ctrOtherRaw = is_string($allMeta['other_services'] ?? '') ? json_decode($allMeta['other_services'] ?? '', true) ?? [] : ($allMeta['other_services'] ?? []);
                                                                            $ctrOtherRaw = array_filter((array)$ctrOtherRaw, fn($s) => is_string($s) && trim($s) !== '');
                                                                            $origBidOtherRaw = (array)data_get($bid, 'get.other_services', []);
                                                                            if (is_string(data_get($bid, 'get.other_services', []))) $origBidOtherRaw = json_decode(data_get($bid, 'get.other_services', '[]'), true) ?: [];
                                                                            $origBidOtherNorm = array_map(fn($s) => strtolower(trim((string)$s)), array_filter((array)$origBidOtherRaw));
                                                                            $ctrOtherIsAdded = fn(string $s): bool => !in_array(strtolower(trim($s)), $origBidOtherNorm, true);
                                                                            // $ctrOtherRemoved superseded below by $ctrOtherRemovedFull (normalized approach)

                                                                            // Normalize function: handles Unicode curly quote variants (matching Tenant approach)
                                                                            $normalizeStr = function($s) {
                                                                                $search = ["\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D"];
                                                                                $replace = ["'", "'", '"', '"'];
                                                                                return str_replace($search, $replace, $s);
                                                                            };

                                                                            // Build normalized selected-services lookup (keyed by normalized text → display value)
                                                                            $ctrSvcsForLookup = array_filter((array)$ctrSvcsRaw, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other');
                                                                            $selectedNormalized = [];
                                                                            foreach ($ctrSvcsForLookup as $svc) {
                                                                                $displaySvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                                                                $selectedNormalized[$normalizeStr($displaySvc)] = $displaySvc;
                                                                                $selectedNormalized[$normalizeStr($svc)] = $displaySvc;
                                                                            }

                                                                            // Redefine ctrSvcIsAdded using normalized approach (matches Tenant $tnSvcIsAdded)
                                                                            $origBidSvcsNormFull = array_values(array_map(
                                                                                fn($s) => $normalizeStr(function_exists('normalize_service_text') ? normalize_service_text((string)$s) : (string)$s),
                                                                                array_values($origBidSvcsRaw)
                                                                            ));
                                                                            $ctrSvcIsAdded = fn(string $svcDisplay): bool =>
                                                                                !in_array($normalizeStr($svcDisplay), $origBidSvcsNormFull, true);

                                                                            // Redefine ctrRemovedSvcs using normalized approach (matches Tenant $tnRemovedDisplay)
                                                                            $ctrSvcsNormFull = array_values(array_map(
                                                                                fn($s) => $normalizeStr(function_exists('normalize_service_text') ? normalize_service_text((string)$s) : (string)$s),
                                                                                array_values($ctrSvcsForLookup)
                                                                            ));
                                                                            $ctrRemovedSvcs = array_values(array_filter(
                                                                                array_values($origBidSvcsRaw),
                                                                                fn($s) => !in_array($normalizeStr(function_exists('normalize_service_text') ? normalize_service_text((string)$s) : (string)$s), $ctrSvcsNormFull, true)
                                                                            ));

                                                                            // Other services for rendering and removed diff
                                                                            $ctrOtherSvcsForRender = array_values(array_filter((array)$ctrOtherRaw, fn($s) => is_string($s) && !empty(trim($s))));
                                                                            $ctrOtherRemovedFull = array_values(array_filter(
                                                                                (array)$origBidOtherRaw,
                                                                                fn($s) => is_string($s) && trim($s) !== '' && !in_array(strtolower(trim($s)), array_map(fn($x) => strtolower(trim((string)$x)), $ctrOtherSvcsForRender), true)
                                                                            ));

                                                                            $hasAnyCounterServices = !empty($selectedNormalized) || !empty($ctrOtherSvcsForRender);

                                                                            // Buyer service categories based on property type (matching Tenant $servicesConfig pattern)
                                                                            $ctrBidPropType = $allMeta['property_type'] ?? $counterBid->property_type ?? data_get($bid, 'get.property_type') ?? 'Residential Property';

                                                                            $ctrBuyerResidentialCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under the \"Real Estate Wanted\" section",
                                                                                    "Share the Buyer's purchase criteria on Nextdoor in Neighborhood or Community Groups",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Real Estate or Housing Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Real Estate or Housing Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send email alerts with new listings from the MLS that match the Buyer's purchase criteria",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm availability, purchase terms, and showing instructions",
                                                                                    "Evaluate properties with the Buyer and provide insights on pricing, terms, potential, and overall fit",
                                                                                ],
                                                                                "🏡 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide factual observations on property layout and condition",
                                                                                ],
                                                                                "📝 Offer & Contract Coordination" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies with the Seller's Agent or Seller (as permitted under the agency agreement)",
                                                                                    "Manage communications with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Professionals, or Lenders (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, appraisals, and lease audits (if applicable)",
                                                                                    "Coordinate with the Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about financing, loan options, property taxes, insurance, and escrow timelines (non-legal guidance)",
                                                                                    "Provide factual information about neighborhood characteristics, school zones, crime data, and local amenities using third-party sources (no personal opinions or steering)",
                                                                                    "Offer general guidance on inspection expectations, common repair requests, and contingency planning during the offer process (non-legal advice)",
                                                                                ],
                                                                            ];

                                                                            $ctrBuyerIncomeCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under the \"Real Estate Wanted\" section",
                                                                                    "Share the Buyer's purchase criteria on Nextdoor in Neighborhood or Community Groups",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Real Estate Investor or Multifamily Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Investment or Property Management Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send email alerts with new listings that match the Buyer's purchase criteria",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Sellers to confirm pricing, rental income, expenses, and showing instructions",
                                                                                    "Evaluate investment properties with the Buyer and provide insights on cash flow, cap rates, and value-add potential",
                                                                                ],
                                                                                "🏘 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide observations on tenant occupancy, building condition, and operating expenses",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies with the Seller's Agent or Seller",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Professionals, Lenders, or 1031 Exchange Intermediaries (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Review and provide due diligence documents such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                                                    "Coordinate with the Seller's Agent, Buyer's Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations, rental comps, and Cap Rate estimates (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about financing options, rent control, property taxes, and Landlord responsibilities",
                                                                                    "Provide factual information on rental demand, turnover rates, and sub market conditions using third-party sources",
                                                                                    "Offer general guidance on due diligence steps, lease audits, and estoppel reviews (non-legal advice)",
                                                                                ],
                                                                            ];

                                                                            $ctrBuyerCommercialCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's criteria on Craigslist under \"Real Estate Wanted – Commercial\"",
                                                                                    "Promote the Buyer's criteria on Facebook in Commercial Real Estate or Investment Groups",
                                                                                    "Share the Buyer's criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's criteria on LinkedIn in Commercial or Investment Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred purchase areas",
                                                                                    "Launch hyperlocal or interest-based digital ad campaigns targeting desired commercial property types",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send listing alerts from real estate platforms that match the Buyer's purchase criteria",
                                                                                    "Send property alerts that match the Buyer's purchase criteria from the MLS or commercial listing platforms",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired listings that meet the Buyer's criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm availability, purchase terms, and showing instructions",
                                                                                    "Analyze building class, property zoning, income potential, and redevelopment opportunities",
                                                                                ],
                                                                                "🏢 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide insights on layout, access, visibility, tenant mix, and surrounding infrastructure",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase agreements or Letters of Intent (LOIs)",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposit structure, timelines, and contingencies with the Seller or Seller's Agent",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with due diligence negotiations, including repair requests or credits",
                                                                                    "Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Officers, Commercial Lenders, or 1031 Exchange Intermediaries (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, appraisals, environmental assessments, and estoppel certificate collection as needed",
                                                                                    "Review and request due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                                                    "Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with recent sales comps, lease comps, and an estimated value range (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about zoning regulations, permitted uses, and rental income potential",
                                                                                    "Provide factual data on traffic counts, commercial market trends, and area demographics using third-party sources (no personal opinions or steering)",
                                                                                    "Offer general guidance on lease types, contingency timelines, due diligence, and environmental risks (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            $ctrBuyerBusinessCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under \"Business for Sale\" or \"Real Estate Wanted – Commercial\"",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Business Opportunity or Franchise Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Business, Commercial, or Startup Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Business Search, Alerts & Matching" => [
                                                                                    "Send alerts for businesses that match the Buyer's acquisition criteria from MLS, BizBuySell, or other listing platforms",
                                                                                    "Send alerts for businesses that match the Buyer's acquisition criteria from available business listing sources",
                                                                                    "Search for off-market, pre-market, distressed, or recently closed businesses that meet the Buyer's criteria",
                                                                                    "Communicate with the Seller's Broker or Seller to confirm pricing, lease terms, licensing status, and showing availability",
                                                                                    "Analyze financials, lease assignments, business licensing requirements, and overall market positioning",
                                                                                ],
                                                                                "🏢 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property or business showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties or business locations on behalf of the Buyer upon request",
                                                                                    "Provide insights on foot traffic, customer base, operational setup, competitive advantages, and location dynamics",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using appropriate business purchase or asset sale forms",
                                                                                    "Provide the Buyer with required disclosures, financial summaries, and documentation made available by the Seller",
                                                                                    "Negotiate terms such as purchase price, deposit structure, inventory inclusions, non-compete agreements, and contingencies",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Manage communication with the Seller's Broker or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with due diligence coordination, Buyer-requested repairs, and adjustment negotiations",
                                                                                    "Monitor contingency periods, financing milestones, and deal approval timelines",
                                                                                    "Provide referrals to Business Attorneys, CPAs, Escrow Officers, or Lenders (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, licensing verifications, lease assignments, and inventory counts",
                                                                                    "Coordinate with Lenders, Attorneys, Escrow Officers, Title Companies, CPAs, and other involved parties to prepare for Closing",
                                                                                    "Review the Settlement Statement or Closing Worksheet for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and business transition materials",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Review based on similar business sales, financial performance, and industry benchmarks (for informational purposes only — not a formal appraisal or valuation)",
                                                                                    "Answer general questions about licensing, zoning, SBA financing, registration steps, and transition timing (non-legal guidance)",
                                                                                    "Offer general guidance on due diligence preparation, key documents to review, and red flags during the acquisition process (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            $ctrBuyerVacantLandCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's criteria on Craigslist under \"Real Estate Wanted – Land\"",
                                                                                    "Share the Buyer's criteria on Nextdoor in Neighborhood or Rural Groups",
                                                                                    "Promote the Buyer's criteria on Facebook in Land Buyers, Developers, or Homesteader Groups",
                                                                                    "Share the Buyer's criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's criteria on LinkedIn in Land Acquisition or Investment Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send property alerts for land listings that match the Buyer's goals from MLS and land-specific platforms",
                                                                                    "Send property alerts for land listings that match the Buyer's goals from relevant real estate and land-specific platforms",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm zoning, access, utilities, and pricing",
                                                                                    "Assess development feasibility, land use restrictions, or agricultural potential (non-legal advice)",
                                                                                ],
                                                                                "🏡 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend land visits with the Buyer",
                                                                                    "Coordinate or conduct virtual walkthroughs using maps, aerials, and site photos",
                                                                                    "Preview parcels on behalf of the Buyer upon request",
                                                                                    "Provide observations on topography, road frontage, and surrounding land uses",
                                                                                ],
                                                                                "📜 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with required state or local disclosure forms",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies (as permitted under the agency agreement)",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed documents to all parties",
                                                                                    "Assist with due diligence coordination, including survey review, soil testing, zoning checks, and permit verification (non-legal guidance only)",
                                                                                    "Monitor contract milestones, contingency deadlines, and financing timelines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Officers, Surveyors, or Land Use Consultants (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate surveys, appraisals, inspections, and environmental assessments",
                                                                                    "Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) based on recent land sales, acreage comps, and price-per-acre benchmarks (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about zoning, utilities, development potential, and environmental constraints (non-legal guidance only)",
                                                                                    "Provide factual data on flood zones, wetlands, and land use maps using third-party sources (no legal or engineering advice)",
                                                                                    "Offer general guidance on feasibility timelines, inspection steps, and rural financing considerations (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            if ($ctrBidPropType === 'Income') {
                                                                                $ctrBuyerCategories = $ctrBuyerIncomeCategories;
                                                                            } elseif ($ctrBidPropType === 'Commercial') {
                                                                                $ctrBuyerCategories = $ctrBuyerCommercialCategories;
                                                                            } elseif ($ctrBidPropType === 'Business') {
                                                                                $ctrBuyerCategories = $ctrBuyerBusinessCategories;
                                                                            } elseif ($ctrBidPropType === 'Vacant Land') {
                                                                                $ctrBuyerCategories = $ctrBuyerVacantLandCategories;
                                                                            } else {
                                                                                $ctrBuyerCategories = $ctrBuyerResidentialCategories;
                                                                            }
                                                                            @endphp
@if (
    !empty($allMeta['commission_structure']) ||
    !empty($allMeta['purchase_fee_type']) ||
    !empty($allMeta['interested_lease_option']) ||
    !empty($allMeta['interested_lease_option_agreement']) ||
    !empty($allMeta['protection_period']) ||
    !empty($allMeta['early_termination_fee_option']) ||
    !empty($allMeta['retainer_fee_option']) ||
    !empty($allMeta['agency_agreement_timeframe']) ||
    !empty($allMeta['brokerage_relationship']) ||
    !empty($allMeta['additional_details_broker']))
    <div class="mb-4">
        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
            <i class="fa-solid fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
        </h6>

        {{-- A) Buyer's Broker Compensation --}}
        @if (!empty($allMeta['commission_structure']) || !empty($allMeta['purchase_fee_type']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">A) Buyer's Broker Compensation</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                @if (!empty($allMeta['commission_structure']))
                <li class="mb-1" style="{{ $isChanged($allMeta['commission_structure'], 'commission_structure') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Buyer's Broker Commission Structure:</span>
                    {{ $allMeta['commission_structure'] }}
                    @if ($isChanged($allMeta['commission_structure'], 'commission_structure')) {!! $changedBadge !!} @endif
                </li>
                @endif
                @if (!empty($allMeta['purchase_fee_type']))
                @php
                    $ctrPurchaseVal = '—';
                    $cpType = $allMeta['purchase_fee_type'];
                    $safeNumber = function($v, $decimals = 2) {
                        if ($v === null || $v === '') return null;
                        $clean = str_replace([',', '$', ' '], '', (string)$v);
                        if ($clean === '' || !is_numeric($clean)) return null;
                        return number_format((float)$clean, $decimals);
                    };
                    if ($cpType === 'Flat Fee' && !empty($allMeta['purchase_fee_flat'])) {
                        $formatted = $safeNumber($allMeta['purchase_fee_flat']);
                        $ctrPurchaseVal = $formatted ? ('$' . $formatted) : '—';
                    } elseif ($cpType === 'Percentage of the Total Purchase Price' && !empty($allMeta['purchase_fee_percentage'])) {
                        $formatted = $safeNumber($allMeta['purchase_fee_percentage']);
                        $ctrPurchaseVal = $formatted ? (rtrim(rtrim($formatted, '0'), '.') . '% of Total Purchase Price') : '—';
                    } elseif ($cpType === 'Percentage of the Total Purchase Price + Flat Fee') {
                        $pctFormatted = $safeNumber($allMeta['purchase_fee_percentage_combo'] ?? null);
                        $pctPart = $pctFormatted ? (rtrim(rtrim($pctFormatted, '0'), '.') . '% of Total Purchase Price') : null;
                        $flatFormatted = $safeNumber($allMeta['purchase_fee_flat_combo'] ?? null);
                        $flatPart = $flatFormatted ? ('$' . $flatFormatted . ' flat') : null;
                        $ctrPurchaseVal = $pctPart && $flatPart ? "$pctPart + $flatPart" : ($pctPart ?? $flatPart ?? '—');
                    } elseif ($cpType === 'other' && !empty($allMeta['purchase_fee_other'])) {
                        $ctrPurchaseVal = $allMeta['purchase_fee_other'];
                    }
                @endphp
                <li class="mb-1" style="{{ $isChanged($allMeta['purchase_fee_type'] ?? '', 'purchase_fee_type') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Buyer's Broker Purchase Fee:</span>
                    {{ $ctrPurchaseVal }}
                    @if ($isChanged($allMeta['purchase_fee_type'] ?? '', 'purchase_fee_type')) {!! $changedBadge !!} @endif
                </li>
                @endif
            </ul>
        </div>
        @endif

        {{-- B) Lease Fee --}}
        @if (!empty($allMeta['interested_lease_option']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">B) Lease Fee</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                <li class="mb-1" style="{{ $isChanged($allMeta['interested_lease_option'], 'interested_lease_option') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Interested in a Lease Agreement:</span>
                    {{ $allMeta['interested_lease_option'] }}
                    @if ($isChanged($allMeta['interested_lease_option'], 'interested_lease_option')) {!! $changedBadge !!} @endif
                </li>
                @if ($allMeta['interested_lease_option'] === 'Yes' && !empty($allMeta['lease_fee_type']))
                @php
                    $ctrLeaseVal = '—';
                    $lfType = $allMeta['lease_fee_type'];
                    $safeNumberLease = function($v, $decimals = 2) {
                        if ($v === null || $v === '') return null;
                        $clean = str_replace([',', '$', ' '], '', (string)$v);
                        if ($clean === '' || !is_numeric($clean)) return null;
                        return number_format((float)$clean, $decimals);
                    };
                    if ($lfType === 'flat' && !empty($allMeta['lease_fee_flat'])) {
                        $formatted = $safeNumberLease($allMeta['lease_fee_flat']);
                        $ctrLeaseVal = $formatted ? ('$' . $formatted) : '—';
                    } elseif ($lfType === 'Percentage of the Gross Lease Value' && !empty($allMeta['lease_fee_percentage'])) {
                        $ctrLeaseVal = $allMeta['lease_fee_percentage'] . '% of Gross Lease Value';
                    } elseif ($lfType === 'Percentage of Monthly Rent' && !empty($allMeta['lease_fee_percentage_monthly_rent'])) {
                        $ctrLeaseVal = $allMeta['lease_fee_percentage_monthly_rent'] . '% of Monthly Rent';
                        if (!empty($allMeta['lease_fee_percentage_monthly_number'])) {
                            $ctrLeaseVal .= ' x ' . $allMeta['lease_fee_percentage_monthly_number'] . ' Months';
                        }
                    } elseif ($lfType === 'Flat Fee + Percentage of the Gross Lease Value') {
                        $flatFormatted = $safeNumberLease($allMeta['lease_fee_flat_combo'] ?? null);
                        $flatPart = $flatFormatted ? ('$' . $flatFormatted) : null;
                        $pctPart = !empty($allMeta['lease_fee_percentage_combo']) ? ($allMeta['lease_fee_percentage_combo'] . '% of Gross Lease Value') : null;
                        $ctrLeaseVal = $flatPart && $pctPart ? "$flatPart + $pctPart" : ($flatPart ?? $pctPart ?? '—');
                    } elseif ($lfType === 'Percentage of the Net Aggregate Rent' && !empty($allMeta['lease_fee_percentage_net'])) {
                        $ctrLeaseVal = $allMeta['lease_fee_percentage_net'] . '% of Net Aggregate Rent';
                    } elseif (strtolower($lfType) === 'other' && !empty($allMeta['lease_fee_other'])) {
                        $ctrLeaseVal = $allMeta['lease_fee_other'];
                    }
                @endphp
                <li class="mb-1" style="{{ $isChanged($allMeta['lease_fee_type'] ?? '', 'lease_fee_type') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Buyer's Broker Lease Fee:</span>
                    {{ $ctrLeaseVal }}
                    @if ($isChanged($allMeta['lease_fee_type'] ?? '', 'lease_fee_type')) {!! $changedBadge !!} @endif
                </li>
                @endif
            </ul>
        </div>
        @endif

        {{-- C) Lease-Option Details --}}
        @if (!empty($allMeta['interested_lease_option_agreement']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">C) Lease-Option Details</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                <li class="mb-1" style="{{ $isChanged($allMeta['interested_lease_option_agreement'], 'interested_lease_option_agreement') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Interested in a Lease-Option Agreement:</span>
                    {{ $allMeta['interested_lease_option_agreement'] }}
                    @if ($isChanged($allMeta['interested_lease_option_agreement'], 'interested_lease_option_agreement')) {!! $changedBadge !!} @endif
                </li>
                @if ($allMeta['interested_lease_option_agreement'] === 'Yes')
                    @if (!empty($allMeta['lease_value']))
                    <li class="mb-1" style="{{ $isChanged($allMeta['lease_value'], 'lease_value') ? $changedStyle : '' }}">
                        <span class="fw-semibold">Compensation for Lease-Option Agreement:</span>
                        @if (($allMeta['lease_type'] ?? '') === 'percent') {{ $allMeta['lease_value'] }}%
                        @else {{ \App\Support\Format::money($allMeta['lease_value']) }}
                        @endif
                        @if ($isChanged($allMeta['lease_value'], 'lease_value')) {!! $changedBadge !!} @endif
                    </li>
                    @endif
                    @if (!empty($allMeta['purchase_value']))
                    <li class="mb-1" style="{{ $isChanged($allMeta['purchase_value'], 'purchase_value') ? $changedStyle : '' }}">
                        <span class="fw-semibold">Compensation if Purchase Option is Exercised:</span>
                        @if (($allMeta['purchase_type'] ?? '') === 'percent') {{ $allMeta['purchase_value'] }}%
                        @else {{ \App\Support\Format::money($allMeta['purchase_value']) }}
                        @endif
                        @if ($isChanged($allMeta['purchase_value'], 'purchase_value')) {!! $changedBadge !!} @endif
                    </li>
                    @endif
                @endif
            </ul>
        </div>
        @endif

        {{-- D) Legal Terms --}}
        @if (!empty($allMeta['protection_period']) || !empty($allMeta['early_termination_fee_option']) || !empty($allMeta['retainer_fee_option']) || !empty($allMeta['agency_agreement_timeframe']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">D) Legal Terms</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                @if (!empty($allMeta['protection_period']))
                <li class="mb-1" style="{{ $isChanged($allMeta['protection_period'], 'protection_period') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Protection Period Timeframe:</span>
                    {{ $allMeta['protection_period'] }} days
                    @if ($isChanged($allMeta['protection_period'], 'protection_period')) {!! $changedBadge !!} @endif
                </li>
                @endif
                @if (!empty($allMeta['early_termination_fee_option']))
                @php $buyerTermOptChg = $isChanged($allMeta['early_termination_fee_option'], 'early_termination_fee_option'); @endphp
                <li class="mb-1" style="{{ $buyerTermOptChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Early Termination Fee:</span>
                    {{ $allMeta['early_termination_fee_option'] }}
                    @if ($buyerTermOptChg) {!! $changedBadge !!} @endif
                </li>
                @if (strtolower($allMeta['early_termination_fee_option']) === 'yes' && !empty($allMeta['early_termination_fee_amount']))
                @php $buyerTermAmtChg = $isChanged($allMeta['early_termination_fee_amount'], 'early_termination_fee_amount'); @endphp
                <li class="mb-1" style="{{ $buyerTermAmtChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Termination Fee Amount:</span>
                    {{ \App\Support\Format::money($allMeta['early_termination_fee_amount']) }}
                    @if ($buyerTermAmtChg) {!! $changedBadge !!} @endif
                </li>
                @endif
                @endif
                @if (!empty($allMeta['retainer_fee_option']))
                @php $buyerRetOptChg = $isChanged($allMeta['retainer_fee_option'], 'retainer_fee_option'); @endphp
                <li class="mb-1" style="{{ $buyerRetOptChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Retainer Fee:</span>
                    {{ $allMeta['retainer_fee_option'] }}
                    @if ($buyerRetOptChg) {!! $changedBadge !!} @endif
                </li>
                @if (strtolower($allMeta['retainer_fee_option']) === 'yes' && !empty($allMeta['retainer_fee_amount']))
                @php $buyerRetAmtChg = $isChanged($allMeta['retainer_fee_amount'], 'retainer_fee_amount'); @endphp
                <li class="mb-1" style="{{ $buyerRetAmtChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Retainer Fee Amount:</span>
                    {{ \App\Support\Format::money($allMeta['retainer_fee_amount']) }}
                    @if ($buyerRetAmtChg) {!! $changedBadge !!} @endif
                </li>
                @endif
                @if (strtolower($allMeta['retainer_fee_option'] ?? '') === 'yes' && !empty($allMeta['retainer_fee_application']))
                @php $buyerRetAppChg = $isChanged($allMeta['retainer_fee_application'], 'retainer_fee_application'); @endphp
                <li class="mb-1" style="{{ $buyerRetAppChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Retainer Fee Application:</span>
                    {{ $allMeta['retainer_fee_application'] === 'applied' ? 'Applied toward final compensation' : ($allMeta['retainer_fee_application'] === 'additional' ? 'Charged in addition to final compensation' : $allMeta['retainer_fee_application']) }}
                    @if ($buyerRetAppChg) {!! $changedBadge !!} @endif
                </li>
                @endif
                @endif
                @if (!empty($allMeta['agency_agreement_timeframe']))
                <li class="mb-1" style="{{ $isChanged($allMeta['agency_agreement_timeframe'], 'agency_agreement_timeframe') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Buyer Agency Agreement Timeframe:</span>
                    {{ ($allMeta['agency_agreement_timeframe'] === 'custom' && !empty($allMeta['agency_agreement_custom'])) ? $allMeta['agency_agreement_custom'] : $allMeta['agency_agreement_timeframe'] }}
                    @if ($isChanged($allMeta['agency_agreement_timeframe'], 'agency_agreement_timeframe')) {!! $changedBadge !!} @endif
                </li>
                @endif
            </ul>
        </div>
        @endif

        {{-- E) Brokerage Relationship --}}
        @if (!empty($allMeta['brokerage_relationship']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">E) Brokerage Relationship</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                <li class="mb-1" style="{{ $isChanged($allMeta['brokerage_relationship'], 'brokerage_relationship') ? $changedStyle : '' }}">
                    <span class="fw-semibold">Acceptable Brokerage Relationship:</span>
                    {{ $allMeta['brokerage_relationship'] }}
                    @if ($isChanged($allMeta['brokerage_relationship'], 'brokerage_relationship')) {!! $changedBadge !!} @endif
                </li>
            </ul>
        </div>
        @endif

        {{-- F) Additional Terms --}}
        @if (!empty($allMeta['additional_details_broker']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">F) Additional Terms</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                @php $addTermsChanged = $isChanged($allMeta['additional_details_broker'], 'additional_details_broker'); @endphp
                <li class="mb-1" style="{{ $addTermsChanged ? $changedStyle : '' }}">
                    <span class="fw-semibold">Additional Terms:</span> {{ $allMeta['additional_details_broker'] }}
                    @if ($addTermsChanged) {!! $changedBadge !!} @endif
                </li>
            </ul>
        </div>
        @endif

        {{-- G) Referral Fee --}}
        @if ($auction->isCreatedByAgent() && !empty($allMeta['referral_fee_percent']))
        <div class="mb-3">
            <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;">G) Referral Fee</div>
            <ul class="list-unstyled ps-3 mb-0" style="font-size: 12px;">
                @php $refFeeChg = $isChanged($allMeta['referral_fee_percent'], 'referral_fee_percent'); @endphp
                <li class="mb-1" style="{{ $refFeeChg ? $changedStyle : '' }}">
                    <span class="fw-semibold">Referral Fee (%):</span> {{ str_ends_with($allMeta['referral_fee_percent'], '%') ? $allMeta['referral_fee_percent'] : $allMeta['referral_fee_percent'] . '%' }}
                    @if ($refFeeChg) {!! $changedBadge !!} @endif
                </li>
            </ul>
        </div>
        @endif
    </div>
@endif

                                                                            {{-- Additional Details --}}
                                                                            @if (!empty($allMeta['additional_details']))
                                                                            <div class="mb-3">
                                                                                <div class="fw-semibold mb-1" style="color: #049399; font-size: 13px;"><i class="fa-solid fa-circle-info me-1"></i>Additional Details</div>
                                                                                @php $addDetailsChanged = $isChanged($allMeta['additional_details'], 'additional_details'); @endphp
                                                                                <div class="ps-3" style="font-size: 12px; {{ $addDetailsChanged ? $changedStyle : '' }}">{{ $allMeta['additional_details'] }}{!! $addDetailsChanged ? $changedBadge : '' !!}</div>
                                                                            </div>
                                                                            @endif

                                                                            <!-- Services Offered -->
                                                                            @if ($hasAnyCounterServices)
                                                                            <div class="mb-5">
                                                                                <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                                    <i class="fa-solid fa-clipboard-list me-2"></i>Offered Services
                                                                                </h6>

                                                                                @foreach ($ctrBuyerCategories as $catName => $catServices)
                                                                                    @php
                                                                                    $selectedInCat = array_filter($catServices, fn($s) => isset($selectedNormalized[$normalizeStr($s)]));
                                                                                    @endphp
                                                                                    @if (count($selectedInCat) > 0)
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $catName }}</div>
                                                                                        <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                                                            @foreach ($catServices as $service)
                                                                                                @php
                                                                                                $serviceNorm = $normalizeStr($service);
                                                                                                $serviceDisplay = $selectedNormalized[$serviceNorm] ?? null;
                                                                                                @endphp
                                                                                                @if ($serviceDisplay !== null)
                                                                                                    @if ($ctrSvcIsAdded($serviceDisplay))
                                                                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                                                                        <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $serviceDisplay }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                                                                    </li>
                                                                                                    @else
                                                                                                    <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $serviceDisplay }}</li>
                                                                                                    @endif
                                                                                                @endif
                                                                                            @endforeach
                                                                                        </ul>
                                                                                    </div>
                                                                                    @endif
                                                                                @endforeach

                                                                                @if (!empty($ctrOtherSvcsForRender))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                                                    <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem; list-style: none;">
                                                                                        @foreach ($ctrOtherSvcsForRender as $otherService)
                                                                                            @if ($ctrOtherIsAdded($otherService))
                                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px; background-color: #fff3cd; padding: 1px 4px; border-radius: 3px;">
                                                                                                <i class="fa-solid fa-plus-circle me-1" style="color: #856404;"></i>{{ $otherService }} <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Added</span>
                                                                                            </li>
                                                                                            @else
                                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $otherService }}</li>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    </ul>
                                                                                </div>
                                                                                @endif

                                                                                @if (!empty($ctrRemovedSvcs) || !empty($ctrOtherRemovedFull))
                                                                                <div class="mb-3 mt-2 p-3" style="background-color: #fff5f5; border-radius: 6px; border: 1px solid #f5c6cb;">
                                                                                    <div class="fw-bold mb-1" style="color: #dc3545; font-size: 0.95rem;">
                                                                                        <i class="fa-solid fa-minus-circle me-1"></i>Removed Services
                                                                                    </div>
                                                                                    <ul class="services mb-0" style="margin-top: 0.5rem; padding-left: 1.2rem; list-style: none;">
                                                                                        @foreach ($ctrRemovedSvcs as $rSvc)
                                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                                                            <i class="fa-solid fa-circle-xmark me-1"></i>{{ $rSvc }}
                                                                                        </li>
                                                                                        @endforeach
                                                                                        @foreach ($ctrOtherRemovedFull as $rSvc)
                                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; color: #dc3545;">
                                                                                            <i class="fa-solid fa-circle-xmark me-1"></i>{{ $rSvc }}
                                                                                        </li>
                                                                                        @endforeach
                                                                                    </ul>
                                                                                </div>
                                                                                @endif
                                                                            </div>
                                                                            @endif


                                                                            <!-- Counter actions (only when both pending & viewer is the other party) -->
                                                                        @inject('carbon', 'Carbon\Carbon')

                                                    @php
                                                        // Milestone 3: this inner block recomputed $expiration from auction_time
                                                        // (created_at + "10 Days"), SHADOWING the page-level value from inside the
                                                        // bid loop — so counter actions were gated by their own synthesised timer
                                                        // even after the page-level one was retired. expiration_date is the only
                                                        // source now, matching the page-level rule exactly.
                                                        $expirationDate = data_get($auction->get, 'expiration_date');
                                                        $expiration = !empty($expirationDate)
                                                            ? $carbon::parse($expirationDate)
                                                            : null;

                                                        $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;
                                                    @endphp

                                                    @if ($showCounterActions)
                                                        <div class="mt-3 pt-3 border-top">
                                                            <div class="d-flex gap-2 flex-wrap justify-content-center w-100">
                                                                <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                    <i class="fa-solid fa-eye me-1"></i> View Counter Terms
                                                                </a>
                                                                @if ($isOwner)
                                                                <a href="{{ route('buyer.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
                                                                </a>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif

                                                                            <!-- Counter footer status -->
                                                                    <div class="mt-3 pt-3 border-top">
                                                                                @if ($counterState === 'accepted')
                                                                                    @if (Auth::id() == $actorUserId)
                                                                                        <div
                                                                                            class="alert alert-success mb-0 py-1 small">
                                                                                            ✅ This counter bid has been
                                                                                            accepted.
                                                                                        </div>
                                                                                    @else
                                                                                        <div
                                                                                            class="alert alert-success mb-0 py-1 small">
                                                                                            ✅
                                                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                                                            accepted the counter bid.
                                                                                        </div>
                                                                                    @endif
                                                                                @elseif ($counterState === 'rejected')
                                                                                    @if (Auth::id() == $actorUserId)
                                                                                        <div
                                                                                            class="alert alert-danger mb-0 py-1 small">
                                                                                            ❌ This counter bid has been
                                                                                            rejected.
                                                                                        </div>
                                                                                    @else
                                                                                        <div
                                                                                            class="alert alert-danger mb-0 py-1 hla-alert-font">
                                                                                            ❌
                                                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                                                            rejected the counter bid.
                                                                                        </div>
                                                                                    @endif
                                                                                @elseif ($counterState === '0')
                                                                                    @if ($counterBid->user_id == Auth::id())
                                                                                        <div
                                                                                            class="alert alert-secondary mb-0 py-1 small">
                                                                                            ⏳ Waiting for response from
                                                                                            {{ $isCounterFromOwner ? trim($agentFirst . ' ' . $agentLast) : trim($ownerFirst . ' ' . $ownerLast) }}...
                                                                                        </div>
                                                                                    @else
                                                                                        <div class="alert alert-light mb-0 py-1 small"
                                                                                            style="font-size:13px;">
                                                                                            ⏳ Counter bid from
                                                                                            {{ trim($creatorFirst . ' ' . $creatorLast) }}
                                                                                            is pending.
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


                                    </div>
                                    {{-- End of bid-collapse-content --}}
                                    </div>
                                    {{-- End of card mb-3 --}}
                                @endforeach

                        </div>
                    </div>
                </div>
        </x-slot>

        {{-- Buyer alone renders content inside the container but after the grid: the share
             block 749ace982 established below the two columns. It stays exactly there. --}}
        <x-slot name="afterGrid">
        <button class="btn w-100 mt-0">
            <span class="bid m-0"><i class="fa-solid fa-user"></i> </span>
        </button>
        <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
                    <div class="qr-code" style="width: 100%; height:200px;">
                        {{ qr_code(route('buyer.view-auction', @$auction->id), 200) }}
                    </div>
                    <div class="card-social">
                        <ul class="icons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('buyer.view-auction', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('buyer.view-auction', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-twitter"></i>
                            </a>
                            <a href="">
                                <i class="fa-brands fa-instagram"></i>
                            </a>
                            <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(route('buyer.view-auction', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-pinterest"></i>
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('buyer.view-auction', $auction->id)) }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-linkedin"></i>
                            </a>
                        </ul>
                        <p class="small opacity-8">Or copy link</p>
                        <div class="field">
                            <i class="fa-solid fa-link"></i>
                            <input type="text" readonly="" id="copylink"
                                value="{{ route('buyer.view-auction', $auction->id) }}">
                            <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                                style="min-width:60px;">Copy</button>
                        </div>
                    </div>
                </div>
    </div>
    <hr>
    <div class="container buyerOfferContentDetails">
        <h3 class="text-600 mb-4">Recommended For You</h3>
        <div class="cardsDetails row  justify-content-start">
            <!-- Card 1 -->
            <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
                <div class="card ">
                    <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
                        class="card-img-top" alt="...">
                    <div class="card-body pb-2 pt-2">
                        <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
                        <div class="houseDetails mb-1">
                            <span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon"
                                        width="15"><b>
                                        4</b></span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon"
                                        width="15"><b>
                                        2</b></span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}"
                                        alt="bed icon" width="15"><b> 1,643 </b>Sq Ft</span>
                            </span>
                            - House for sale
                        </div>
                        <p class="card-text mb-1"><span class="badge bg-secondary">land/lots</span> <span
                                class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></span></p>
                        <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg><b>28d 03:15:29</b></p>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="row">
                            <div class="col-6 left">
                                <!-- Barcode  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                    </path>
                                </svg>
                                <!-- FAvourite  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add Favorites"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                    </path>
                                </svg>
                            </div>
                            <div class="col-6 right text-end">
                                <b>$1,000</b>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
                <div class="card ">
                    <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
                        class="card-img-top" alt="...">
                    <div class="card-body">
                        <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
                        <div class="houseDetails">
                            <span>
                                <span><b>4</b> bds</span>
                                <span><b>2</b> ba</span>
                                <span><b>1,643</b> sqft</span>
                            </span>
                            - House for sale
                        </div>
                        <p class="card-text"><span class="badge bg-secondary">land/lots</span> <span
                                class="float-end"><span><b>MLS
                                        ID</b></span> <span>#12345</span></span></p>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="row">
                            <div class="col-6 left">
                                <!-- Barcode  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top"
                                    data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                                <!-- Message  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top"
                                    data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                    </path>
                                </svg>
                                <!-- FAvourite  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top"
                                    data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                    </path>
                                </svg>
                            </div>
                            <div class="col-6 right text-end">
                                <b>$1,000</b>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </x-slot>

        {{--
            The hero's Edit Listing control — the buyer counterpart of the landlord slot.

            THE SLOT ITSELF IS CONDITIONAL, not just its contents. An always-emitted slot would be
            `isset()` even when empty, and the legacy hero would then render an empty actions
            wrapper — a DOM change on a page the flag is supposed to leave untouched.

            The authorization test is the one this control has always carried in the sidebar,
            unchanged: owner-only, by user id. `auth()->id()` is read directly rather than through
            $auth_id so this does not depend on the sidebar slot having been captured first.

            Route, params, label, icon and classes are identical to the sidebar control it replaces,
            and the sidebar copy is suppressed under the same hero flag — so exactly one Edit
            Listing renders in either flag state, never two and never none.
        --}}
        @if (\App\Support\HireAgent\HireAgentHeroData::redesignEnabledFor('buyer')
            && auth()->id() && auth()->id() == @$auction->user_id)
        <x-slot name="heroActions">
            <a href="{{ route('buyer.edit-auction', ['auctionId' => $auction->id]) }}"
               class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
            </a>
        </x-slot>
        @endif
    </x-hire-agent.detail-shell>
@endsection

@push('scripts')
{{--
    Milestone 3: the timer.jquery CDN tag and the whole countdown initialiser were removed from
    here. Beyond rendering the clock, its onTimerEnd callback replaced the countdown with a
    "Bidding Ended" pill AND faded out the Bid button — a client-side, timer-derived proposal
    restriction layered on top of the server-side one. Proposal availability is now decided
    solely by listing status and expiration_date, server-side. No JavaScript countdown is
    initialised on this page any more, and the library is no longer loaded at all.
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

{{-- M7 Phase 4 — active section highlighting. Flag-gated: with the redesign off this page pushes
     no additional script, so there is no new behaviour to regress.

     THE ROLE-AWARE READER, NOT THE MASTER SWITCH. This script drives the bar emitted under the same
     gate; reading a different flag than the markup it operates on is how a page ends up binding
     behaviour to elements that were never rendered. See the $byaDetailRedesign note.

     The partial is shared with landlord rather than copied — it was extracted from that view as
     groundwork for exactly this adoption, and it carries no gate of its own on purpose, so the
     decision stays here with the markup. --}}
@if (\App\Support\HireAgent\HireAgentDetailRedesign::enabledFor('buyer'))
@include('hire_agent.framework.section-nav-behaviour')

{{-- Binds the Quick Actions Copy Link control emitted in the beforeGrid slot. Gated by the same
     role-aware reader as the markup it operates on: binding behaviour to elements that were never
     rendered is the failure this pairing avoids. Shared with landlord rather than copied. --}}
@include('hire_agent.framework.quick-actions-behaviour')
@endif
@endpush

