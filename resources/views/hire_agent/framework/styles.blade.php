{{--
    Hire Agent Listing Detail Framework — shared stylesheet.

    Milestone 4. Every one of the four Hire Agent detail views carried its own <style> block of
    ~270-300 lines. Thirty rules were BYTE-IDENTICAL across all four; this file is those thirty,
    plus the new framework-only rules for the shared hero and its mobile stacking.

    The thirty were chosen by rule-level intersection, not by eye, precisely so that moving them
    could not change what any page renders. Rules that merely LOOK shared are deliberately left
    behind in the role views — Buyer's .btn-counter is yellow where the other three are blue, and
    several rules differ only in !important or comment punctuation. Normalising those would be a
    visual change wearing the costume of a refactor, so each view keeps its own residual block.

    ── M5.1: SCOPING, AND A CORRECTION ──────────────────────────────────────────────────────────

    The header here used to claim: "Nothing in this file selects a bare element or a Bootstrap
    class globally." THAT WAS FALSE. It selected `hr`, `ul`, `ul li`, `input[type=number]`,
    `.card-body`, `.form-select` and `.fa-percent` with no scope at all, and this file is pushed
    into the document's style stack, so those rules applied to the WHOLE PAGE — including the site
    header, the off-canvas mobile navigation and the footer, none of which belong to Hire Agent.

    Measured before the fix, on a live landlord page: the bare `ul` and `ul li` rules were styling
    19 list items outside the page containers, all of them in the mobile navigation menu.

    Every rule that is not already `.hla-` prefixed is now scoped under `.hla-detail-page`, which
    the detail shell puts on its wrapper. Two consequences, both intended:

      · The mobile navigation stops inheriting Hire Agent list styling. That is a VISUAL CHANGE,
        not a no-op refactor, and it is the point of the milestone rather than a side effect.
      · Bootstrap and app-wide class NAMES are kept (`.card-body`, `.form-select`, `.field-label`)
        because the markup uses those names. Scoping is what makes them safe; renaming them would
        be a much larger change to no additional benefit.

    ── M5.1: VIHO TOKENS ────────────────────────────────────────────────────────────────────────

    This is the ONE Hire Agent file permitted to read `var(--viho-*)`. That exception is named
    explicitly in VihoDesignTokenFoundationTest and is not a general licence: no other Hire Agent
    file may read a token, and Create Offer may not read one at all before M8.

    Only EXACT value matches were substituted. #e2e8f0 becomes var(--viho-border) because the token
    is #E2E8F0; #34465c, #0f1a24 and #049399 stay literal because no token holds them, and
    inventing near-matches would be a visual change disguised as an alignment. Anything still
    hard-coded below is hard-coded because the shared scale genuinely does not contain it.
--}}
<style>
    /* ── Shared rules, previously duplicated verbatim in all four detail views ─────────
       All scoped to .hla-detail-page as of M5.1. See the header for why. */
    /* Chrome, Safari, Edge, Opera */
        .hla-detail-page input::-webkit-outer-spin-button,
        .hla-detail-page input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    /* Firefox */
        .hla-detail-page input[type=number] {
            -moz-appearance: textfield;
        }
    .hla-detail-page .fa-dollar-sign,
        .hla-detail-page .fa-percent {
            padding: 0 20px;
            background: #facd34;
            color: #fff;
            border: 0;
            font-weight: 700 !important;
            line-height: 39px !important;
            margin-right: -5px;
            z-index: 1;
            border-radius: 3px 0 0 3px;
        }
    .hla-detail-page .form-control,
        .hla-detail-page .form-select {
            border-radius: 0.25rem;
            box-shadow: inset 0 1px 2px 0 rgb(66 71 112 / 12%);
            border-radius: 0.25rem;
            background-color: #fafafb;
            margin-bottom: 15px;
        }
    /* Section Title Hierarchy - Larger, bold, spaced, more prominent */
        .hla-detail-page .card-header h4,
        .hla-detail-page .section-title {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            margin-top: var(--viho-space-2xl);
            margin-bottom: var(--viho-space-md);
            color: #0f1a24;
        }
    /* Services section - extra breathing room before header */
        .hla-detail-page .services-section-header {
            margin-top: var(--viho-space-md) !important;
        }
    .hla-detail-page hr {
            margin-top: var(--viho-space-xl);
            margin-bottom: 0.5rem;
        }
    /* Field row styling - improved line-height for scan-readability */
        .hla-detail-page .col-md-12.col-12.pt-2.fw-bold {
            line-height: 1.6;
            padding-top: 0.6rem !important;
            padding-bottom: 0.2rem;
        }
    .hla-detail-page .field-row {
            padding: 0.5rem 0;
            font-size: 0.95rem;
            line-height: 1.6;
        }
    .hla-detail-page .field-label {
            font-weight: 600;
            color: #34465c;
        }
    .hla-detail-page .field-value {
            font-weight: normal;
            color: #34465c;
        }
    /* Broker Compensation subsection headers - breathing room */
        .hla-detail-page h5.mt-3.mb-2 {
            padding-top: var(--viho-space-md);
            margin-top: 1rem !important;
        }
    /* Fix blank space under section headers - reduce gap to first content */
        .hla-detail-page .card-body {
            padding-top: 12px !important;
        }
    .hla-detail-page .card-body > :first-child {
            margin-top: 0 !important;
        }
    /* Broker Compensation section text - match other section text color */
        .hla-detail-page .broker-compensation-section,
        .hla-detail-page .broker-compensation-section p,
        .hla-detail-page .broker-compensation-section .col-md-12,
        .hla-detail-page .broker-compensation-section .fw-bold {
            color: #34465c !important;
        }
    .hla-detail-page ul {
            --icon-size: 1em;
            --gutter: .5em;
            padding: 0 0 0 calc(var(--icon-size) + 2em);
        }
    .hla-detail-page ul li {
            padding-left: var(--gutter);
            color: #34465c;
        }
    .hla-detail-page ul:not(.services) li::marker {
            content: "\f101";
            /* FontAwesome Unicode */
            font-family: FontAwesome;
            font-size: var(--icon-size);
            /* color: #006e9f; */
            color: #11b7cf;
        }
    /* Services section - Tighter spacing and indentation */
        .hla-detail-page ul.services {
            list-style: none !important;
            padding-left: 1.2em;
            margin-top: var(--viho-space-xs);
            margin-bottom: 0.5rem;
        }
    .hla-detail-page ul.services li {
            padding: 0.15rem 0;
            color: #34465c;
            position: relative;
            padding-left: 0;
            list-style: none !important;
            line-height: 1.4;
        }
    .hla-detail-page ul.services li::marker {
            content: none !important;
        }
    .hla-detail-page ul.services li::before {
            content: "•";
            position: absolute;
            left: -0.9em;
            color: #34465c;
            font-size: 1.1em;
        }
    .hla-detail-page .removeBold {
            font-weight: normal;
        }
    /* M5.1 renamed from .biding-btn. NOTE: no markup in any Hire Agent view carries this class —
       the rule is dead. Renamed rather than deleted so this milestone stays a rename, not a
       removal; deleting it is a separate decision. */
    .hla-detail-page .hla-bid-btn {
            width: 31.5%;
        }
    .hla-detail-page .view-btn {
            padding: 6px !important;
        }
    .hla-detail-page .services-offered {
            padding: 23px !important;
        }
    @media screen and (max-width: 800px) {
            /* M5.1 renamed from .accordion-body-padding. Also dead — no markup carries it. */
            .hla-detail-page .hla-accordion-body-padding {
                padding: 7px !important;
            }

            .hla-detail-page .hla-alert-font {
                font-size: 10px;
            }

            .hla-detail-page .hla-counter-font {
                font-size: 15px;
            }
        }
    /* Bid card accordion chevron rotation (custom JS toggle).
       M5.1: renamed from .bid-accordion-header. The class is also a JAVASCRIPT selector in all
       four role views (`document.querySelectorAll('.bid-accordion-header')`), so the rename was
       applied to the scripts in the same commit — a CSS-only rename would have silently detached
       the toggle from its styling. */
        .hla-detail-page .hla-bid-accordion-header .bid-chevron {
            transition: transform 0.3s ease;
        }
    .hla-detail-page .hla-bid-accordion-header:hover {
            background-color: #f8f9fa !important;
        }
    .hla-detail-page .hla-bid-action-btn:hover {
            opacity: 0.9;
        }
    /* ── Hire Agent shared hero ──────────────────────────────────────────────────────
       New in Milestone 4. There was no hero before: each page opened straight into a
       "Listing Details:" card, and the title/status sat at the top of the right column.
       Deliberately absent: any countdown, any remaining-time element, any competing-proposal
       count. The hero shows what the listing IS, never how long is left to act on it.

       Left unscoped in M5.1: .hla- is already a unique namespace, so these rules cannot collide
       with anything outside Hire Agent. Prefixing them again would add noise without safety. */
    .hla-hero {
        background: linear-gradient(180deg, var(--viho-page-bg) 0%, var(--viho-card-bg) 100%);
        border: 1px solid var(--viho-border);
        border-radius: 10px;
        padding: var(--viho-space-xl) var(--viho-space-2xl);
        margin-bottom: var(--viho-space-xl);
    }
    .hla-hero-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0f1a24;
        line-height: 1.25;
        margin: 0 0 var(--viho-space-xs) 0;
        overflow-wrap: anywhere;
    }
    .hla-hero-subtitle {
        font-size: 0.95rem;
        color: var(--viho-text-soft);
        margin: 0 0 var(--viho-space-md) 0;
        overflow-wrap: anywhere;
    }
    .hla-hero-figure {
        font-size: 1.75rem;
        font-weight: 700;
        color: #049399;
        line-height: 1.2;
        margin: 0;
    }
    .hla-hero-figure-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: var(--viho-label);
        margin-bottom: var(--viho-space-3xs);
    }
    .hla-hero-facts {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem var(--viho-space-2xl);
        margin-top: 0.85rem;
    }
    .hla-hero-fact {
        min-width: 0;
    }
    .hla-hero-fact-label {
        display: block;
        font-size: var(--viho-font-xs);
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: var(--viho-label);
    }
    .hla-hero-fact-value {
        font-size: 0.95rem;
        color: #0f1a24;
        overflow-wrap: anywhere;
    }
    .hla-hero-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.85rem;
    }
    .hla-hero-chip {
        display: inline-flex;
        align-items: center;
        gap: var(--viho-space-xs);
        background: var(--viho-surface-subtle);
        border: 1px solid var(--viho-border);
        border-radius: 999px;
        padding: 0.2rem 0.7rem;
        font-size: 0.8rem;
        color: var(--viho-text);
    }
    .hla-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: var(--viho-space-lg);
    }

    /* ── Mobile stacking ─────────────────────────────────────────────────────────────
       The hero is a single column above the two-column body, so on a narrow viewport the
       natural document order already gives: summary -> details -> sidebar. The only thing
       needed is to stop the hero's own two columns sitting side by side, and to guarantee
       no horizontal overflow from a long address or an un-breakable figure. */
    @media (max-width: 767.98px) {
        .hla-hero {
            padding: 1rem;
        }
        .hla-hero-title {
            font-size: 1.3rem;
        }
        .hla-hero-figure {
            font-size: var(--viho-font-xl);
        }
        .hla-hero-facts {
            gap: 0.5rem 1rem;
        }
        .hla-hero-actions .btn {
            flex: 1 1 100%;
        }
    }
    .hla-hero,
    .hla-hero * {
        max-width: 100%;
    }

    /* ── M5.3: Quick Actions, the product half ───────────────────────────────────────────────
       Two rules the VIHO band cannot own. A share row is a list of THIS product's share targets,
       and the copy confirmation is the visible half of the landlord view's copy handler; neither
       is general enough for the neutral layer.

       THEY LIVE HERE RATHER THAN IN THE ROLE VIEW because this is the one Hire Agent file
       permitted to read `var(--viho-*)`, and expressing them without tokens would mean copying
       spacing and colour values that already have names. The trade is that they ship to all four
       roles and to every viewer regardless of HIRE_AGENT_DETAIL_REDESIGN_ENABLED — inert, because
       no page emits `.hla-quick-share` markup unless the flag is on. That is the same bargain the
       VIHO stylesheet itself already makes: rules ship, markup is what is gated. The flag's real
       guarantee is over markup and behaviour, and the tests assert it there. */
    .hla-detail-page .hla-quick-share {
        display: flex;
        flex-wrap: wrap;
        gap: var(--viho-space-md);
        list-style: none;
        padding: 0;
        margin: 0;
    }
    /* The same ::marker defence x-viho.section-nav needs, against the same rule: this file gives
       every `.hla-detail-page ul:not(.services) li` a FontAwesome chevron, and `list-style: none`
       does not suppress a marker whose content is set explicitly. A share row IS such a list. */
    .hla-detail-page ul.hla-quick-share > li {
        display: block;
    }
    /* `ul.` rather than a bare descendant: the legacy rule is
       `.hla-detail-page ul:not(.services) li::marker`, which is two classes and two elements. A
       selector one element short of that loses the cascade and the "second line of defence" would
       be decorative — display:block would be carrying it alone. Matching the specificity and
       landing later in the same file is what makes this rule actually apply. */
    .hla-detail-page ul.hla-quick-share > li::marker {
        content: none;
    }
    .hla-detail-page .hla-quick-share a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        padding: 0;
        border: 1px solid var(--viho-border-strong);
        border-radius: var(--viho-radius-md);
        color: var(--viho-label);
        text-decoration: none;
        transition: color var(--viho-transition), border-color var(--viho-transition);
    }
    .hla-detail-page .hla-quick-share a:hover,
    .hla-detail-page .hla-quick-share a:focus-visible {
        color: var(--viho-primary);
        border-color: var(--viho-primary);
    }
    /* Reserves its line whether or not it has text, so confirming a copy does not reflow the tile. */
    .hla-detail-page .hla-quick-copy-status {
        display: block;
        margin-top: var(--viho-space-xs);
        min-height: 1em;
        font-size: var(--viho-font-3xs);
        color: var(--viho-primary);
    }
@if ($hlaShellRedesign ?? false)
    /* ── M7.1 — sidebar sticky preparation ─────────────────────────────────────
       EMITTED ONLY FOR A ROLE THE REDESIGN IS ON FOR. $hlaShellRedesign is resolved by
       detail-shell.blade.php, which includes this file, and Blade hands an included view the
       caller's variables. The `?? false` is the fail-closed default for any other includer.

       WHY THE RULE LIVES HERE RATHER THAN IN THE SHELL, and it is a constraint rather than a
       preference. This file is the ONE product file permitted to read a --viho token:
       VihoDesignTokenFoundationTest states that M5.1 lifted the ban for exactly this path and
       that a second consumer is an architectural change requiring its own milestone decision,
       not a test edit. M7.1 briefly put these two token reads in the shell, the guard caught it,
       and the rule moved here rather than the boundary moving.

       WHY IT IS STILL CONDITIONAL. This stylesheet is pushed unconditionally, so an always-on
       rule would change the bytes of every Hire Agent page in BOTH flag states — inert with the
       flag off, since nothing carries the class, but no longer byte-identical. "Flag off changes
       nothing" is the guarantee this milestone keeps, and inert-but-present does not keep it.

       Sticks BELOW the section navigation rather than at the viewport edge: .viho-section-nav is
       itself sticky at --viho-section-nav-offset, and two elements sticking to the same line
       would overlap. Reusing that token keeps the two related if either moves.

       DOES NOTHING UNTIL THE SIDEBAR IS SHORTER THAN THE MAIN COLUMN, which is the honest state
       today: a landlord sidebar carrying a populated proposal console is as tall as main, so this
       is inert there and pays off for a guest, and for the other roles when they adopt. The rail
       that makes it worthwhile is M7.4; this milestone only makes it possible.

       Below lg the columns stack full-width and sticking is suppressed — a sticky element in a
       single-column flow pins content over the page as the reader scrolls past it. */
    @media (min-width: 992px) {
        .hla-detail-page .hla-sidebar-sticky {
            position: sticky;
            top: calc(var(--viho-section-nav-offset, 0px) + var(--viho-space-lg, 1rem));
            /* Never taller than the viewport, so a long sidebar scrolls internally rather than
               pinning its own overflow out of reach. */
            max-height: calc(100vh - var(--viho-section-nav-offset, 0px) - var(--viho-space-lg, 1rem));
            overflow-y: auto;
        }
    }

    /* ── M7.2 — the section cards ARE the scroll anchors, so they carry the offset ──
       EMITTED ONLY FOR A ROLE THE REDESIGN IS ON FOR, and here for the same reason the sidebar
       rule above is: it reads --viho tokens, and this file is the one product file permitted to.

       WHAT WENT WRONG, BECAUSE IT WENT WRONG SILENTLY. Before M7.2 each anchor was a zero-height
       `<span class="viho-section-nav-target">` above its heading, and viho/styles.blade.php gives
       THAT CLASS its scroll-margin-top. M7.2 deleted the spans and moved the ids onto the cards.
       The cards inherited the id and not the class, so the offset stopped applying. Nothing threw,
       every anchor still resolved, and the suite stayed green — the only symptom was visual.

       Measured on the landlord page before this rule: a nav click landed the card at y≈0 with the
       bar's bottom edge at 46.9px on desktop and 150.9px on mobile, hiding the card header under
       the bar in 6 of 7 desktop sections and 7 of 7 on mobile. The nav exists to deliver a reader
       to a section header, so that was the milestone failing at its own purpose.

       TWO TOKENS, NOT ONE, AND THAT IS THE FIX. --viho-section-nav-offset is where the BAR sticks:
       the chrome above it. A scroll target must clear the chrome AND the bar itself, because the
       bar is what it would otherwise land beneath. The page's original note claimed one variable
       could do both jobs; it cannot, and on desktop the offset is 0px so reusing it alone gave no
       clearance at all.

       NOT SOLVED by putting .viho-section-nav-target on the cards. That class is the VIHO layer's
       word for "a thing a nav scrolls to"; a card is the section itself, and borrowing the class
       would re-couple this page's spacing to a primitive that knows nothing about this page's
       chrome — the coupling these tokens exist to avoid.

       Scoped under .hla-detail-page, like every other rule in this file, so it cannot reach Offer
       Listing or any other page that might one day mint a similar id. */
    .hla-detail-page [id^="hla-section-"] {
        scroll-margin-top: calc(
            var(--viho-section-nav-offset, 0px) + var(--viho-section-nav-height, 3.5rem)
        );
    }
@endif
</style>
