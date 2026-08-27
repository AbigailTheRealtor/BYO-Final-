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
    /* Fix blank space under section headers - reduce gap to first content */
        .hla-detail-page .card-body {
            padding-top: 12px !important;
        }
    .hla-detail-page .card-body > :first-child {
            margin-top: 0 !important;
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
    /* ── M7.5 — the sidebar surface, and the card that carries the sticky ──────
       EMITTED ONLY FOR A ROLE THE REDESIGN IS ON FOR. $hlaShellRedesign is resolved by
       detail-shell.blade.php, which includes this file, and Blade hands an included view the
       caller's variables. The `?? false` is the fail-closed default for any other includer.

       WHY THESE RULES LIVE HERE RATHER THAN IN THE SHELL, and it is a constraint rather than a
       preference. This file is the ONE product file permitted to read a --viho token:
       VihoDesignTokenFoundationTest states that M5.1 lifted the ban for exactly this path and
       that a second consumer is an architectural change requiring its own milestone decision,
       not a test edit. M7.1 briefly put its token reads in the shell, the guard caught it, and
       the rule moved here rather than the boundary moving.

       WHY THEY ARE STILL CONDITIONAL. This stylesheet is pushed unconditionally, so an always-on
       rule would change the bytes of every Hire Agent page in BOTH flag states — inert with the
       flag off, since nothing carries the classes, but no longer byte-identical. "Flag off
       changes nothing" is the guarantee this milestone keeps, and inert-but-present does not
       keep it.

       GEOMETRY AND SURFACE ONLY. Measured against the Offer Listing sidebar card, which is the
       approved visual reference and is NOT modified by this milestone: white surface, 1px border,
       rounded corners, a card shadow, interior padding. Nothing about typography, nothing about
       the controls inside, and no selector that can reach a descendant — the sidebar holds the
       proposal console as a SIBLING of this card precisely so a styling milestone cannot alter
       what a HireAgentProposalAccess-gated element renders. Same fence M7.4 put around
       .hla-surface-card, kept for the same reason.

       RADIUS, BORDER AND SHADOW ARE NOT REPEATED HERE. The card composes .hla-surface-card, which
       M7.4 already defined from tokens; this rule adds only the two things that card deliberately
       omits. .hla-surface-card was written for elements that brought their own background and
       padding — a Bootstrap .card, in both its cases. A bare div brings neither, so a sidebar card
       carrying only that class would render as a bordered transparent box with its contents
       against the edge. */
    .hla-detail-page .hla-sidebar-card {
        background: var(--viho-card-bg);
        padding: var(--viho-space-lg);
    }

    /* THE STICKY ELEMENT IS THE CARD, NOT THE COLUMN. M7.1 put this class on the sidebar column
       and recorded why that could not work: a landlord sidebar carrying a populated proposal
       console is as tall as the main column, and an element that is never shorter than its
       container never sticks. It named the fix — Offer Listing sticks an inner card — and
       deferred it. The class now sits on the status/CTA card, which is short by construction,
       because the console that made the column tall is its sibling rather than its child.

       Sticks BELOW the section navigation rather than at the viewport edge: .viho-section-nav is
       itself sticky at --viho-section-nav-offset, and two elements sticking to the same line
       would overlap. Reusing that token keeps the two related if either moves.

       NO max-height / overflow-y ANY MORE, and their removal is part of the fix rather than a
       tidy-up. They existed because the sticky element was the whole column, which could exceed
       the viewport; a short card cannot, so the pair only stood to put an internal scrollbar on a
       card with no visual affordance that it scrolls. If a future sidebar card does grow past the
       viewport, the answer is to move what made it tall out into a sibling — which is exactly the
       shape this milestone establishes.

       Below lg the columns stack full-width and sticking is suppressed — a sticky element in a
       single-column flow pins content over the page as the reader scrolls past it. */
    @media (min-width: 992px) {
        .hla-detail-page .hla-sidebar-sticky {
            position: sticky;
            top: calc(var(--viho-section-nav-offset, 0px) + var(--viho-space-lg, 1rem));
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

    /* ── M7.3 — the sub-headings were LARGER than the card titles above them ──
       A hierarchy inversion, and it only became one when M7.2 landed. Before decomposition these
       h5s sat inside one card whose single title was 1.5rem, so an h5 beneath it was correctly
       smaller. Now every section is its own card and a viho card title is --viho-font-lg, 1.05rem.
       An unstyled Bootstrap h5 is 1.25rem, so each of the eleven sub-headings inside Broker
       Compensation renders ~19% larger than the heading of the card containing it — the deepest
       level of the outline shouting over the level above it.

       Measured against the reference, which does not have this problem: Create Offer's in-card
       sub-heading is an h6 at 0.9rem under a 1.05rem card header. This matches that RELATIONSHIP
       — sub-heading below card title — using Hire Agent's own markup. The element stays h5: the
       document outline is correct and is not what was wrong. Only its size is.

       The existing margin rule for these headings is left alone. Vertical rhythm was not the
       defect and changing both at once would make it impossible to tell which one moved. */
    .hla-detail-page h5.mt-3.mb-2 {
        font-size: 0.95rem;
        letter-spacing: var(--viho-tracking-tight, -0.01em);
        color: var(--viho-text-strong, #1E293B);
    }

    /* ── M7.3 — two cards were still rendering Bootstrap's chrome beside a page of viho cards ──
       Nothing in this repository styles the sidebar proposal console's own class or
       `.card.review` (the owner summary at the foot of the main column), so both fall through to
       the theme's `.card`: ~0.25rem radius, an rgba(0,0,0,.125) border, no shadow. Everything
       around them has rendered 0.75rem / #E2E8F0 / 0 1px 6px since M7.2. The console sits directly
       beside that column at the lg breakpoint, so the mismatch is visible side by side.

       IT MUST NOT NAME THE CONSOLE'S OWN CLASS, AND A TEST CAUGHT IT TWICE. The first version of
       this rule listed that class in the selector. HireAgentProposalConsoleTest asserts the class
       name is ABSENT from the DOM for a guest, an agent who has not bid, an unrelated user and an
       administrator — it uses that string as the proxy for "the console rendered". A stylesheet is
       part of the DOM, so naming the class there put it on the page for every viewer and the
       assertion failed, correctly. The proxy is legitimate and the rule was wrong, so the rule
       moved rather than the test.

       The SECOND failure was this comment. With the selector fixed, the prose above still spelled
       the class out to explain why it must not — and a CSS comment inside a style block ships to
       the browser just as the selector does. Hence the circumlocution here, which is the same
       convention the section-nav and detail-section notes already use for names a guard scans for.
       Blade comments elsewhere in these files may spell it freely: those are stripped at compile
       time and never reach the page. A CSS comment is not a Blade comment.

       The hook is therefore a class the caller adds ONLY in the redesigned branch, to markup that
       is itself already behind the console's visibility gate. When the console is withheld the
       element does not render, so the hook does not either, and the privacy proxy keeps working
       exactly as written. With the flag off no hook is emitted at all, which is what keeps the
       flag-off DOM identical.

       GEOMETRY ONLY, AND DELIBERATELY SO. Radius, border and shadow — the three properties that
       make a card look like it belongs — and nothing else. Padding is untouched, and so is
       everything inside the console: its proposal cards carry their own class and inline geometry,
       so they are out of this rule's reach by construction. That matters beyond tidiness. The
       console's contents are gated by HireAgentProposalAccess, and a styling milestone that could
       alter what a proposal card renders would be the ideal place for an authorization regression
       to hide inside a visual diff. This cannot reach them.

       Tokens are read rather than repeated, so these two cannot drift if the card geometry is
       retuned in the primitive. */
    .hla-detail-page .hla-surface-card {
        border-radius: var(--viho-radius-lg);
        border: 1px solid var(--viho-border);
        box-shadow: var(--viho-shadow-card);
    }

    /* M7.4 — the flex context every section card's rows sit in.

       Each converted row renders a `col-lg-6` (or `col-12`) cell, and a Bootstrap column needs a
       flex parent or it degrades into a block element at 50% width, stacking one per line with the
       other half blank. Three sections — Leasing Terms, Compensation, Representation — never had a
       section-level `div.row` to inherit: their legacy markup wrapped EACH row in its own, which
       the adapter now emits on the legacy branch only. Rather than add a wrapper to those three
       call sites and not the others, the card supplies one for every section.

       NOT `.row`, deliberately. Bootstrap's row carries negative side margins that assume a
       `.container`/`.col` ancestor, and two of these sections still contain a `div.row` of their
       own for content the adapter does not render. Nesting row-inside-row would pull those against
       the card's padding. This declares only the flex behaviour the columns actually require and
       inherits the card's padding untouched.

       Row spacing is set by `--hla-field-row-gap` below, which M7.9 aligned to the spacing the
       reference page actually renders; see the note there for why it is a gap rather than a
       margin, and why the class name it was originally aimed at was the wrong target. */
    /* M7.8 — the horizontal gap is DECLARED here, because nothing was ever supplying one.

       THE GUTTER WAS PHANTOM. Bootstrap 5 puts a column's horizontal padding on `.row > *`, not on
       the `.col-*` classes themselves — `.col-lg-6` resolves to `flex: 0 0 auto; width: 50%` and
       nothing else. This grid is deliberately not a `.row` (see the note above), so its cells were
       inheriting no padding from anywhere and two half-span fields sat flush against each other:
       `.viho-kv-split` sizes its value to end exactly on the cell's right edge, so the left field's
       value box touched the right field's label box with 0px between them.

       DERIVED FROM THIS GRID, NOT BORROWED FROM BOOTSTRAP'S. The value matches what the reference
       page's rows genuinely apply between their two columns, but it is written down here rather
       than read from `--bs-gutter-x`: this container is not a row, so a Bootstrap variable would be
       describing a mechanism that is not running. A token means the gap and the alignment maths
       below cannot disagree — change it in one place and the label columns follow.

       `column-gap` ALONE IS NOT ENOUGH, AND THE CELL WIDTH BELOW IS THE OTHER HALF. A gap between
       two children that are each `width: 50%` overflows the container and wraps them one per line,
       which would be the exact opposite of the two-up grid this page wants. The lg rule further
       down narrows each half cell by half the gap so the pair plus the gap comes back to 100%. */
    .hla-detail-page .hla-field-grid {
        display: flex;
        flex-wrap: wrap;
        row-gap: var(--hla-field-row-gap);
        column-gap: var(--hla-field-col-gap);
    }

    /* M7.6 — vertical rhythm, and the doubling that had to be removed to get it.

       TWO SPACINGS WERE STACKING. `.viho-kv` carries `margin-bottom: 0.65rem` for the pages that
       use the primitive outside a grid, and `.hla-field-grid` added a `row-gap` on top of it. The
       two are not alternatives — a flex row-gap applies BETWEEN lines and the margin applies below
       every cell, so consecutive field rows were separated by the sum, ~1rem, against the
       reference page's row spacing. Every card on the page was carrying twice the intended
       air, which read as the fields being unrelated to each other.

       (M7.6 recorded that spacing as "`mb-2` (0.5rem)". It is not — see the M7.9 note below. The
       diagnosis above was right and the target it aimed at was 0.15rem short.)

       The margin is zeroed and the gap alone carries the rhythm. Doing it in this order rather
       than the reverse matters: row-gap is the one that understands wrapping, so two fields
       sharing a line are spaced from the line below by the same value whether one of them wrapped
       to two lines or not. A margin cannot express that.

       SCOPED TO `.hla-field`, NOT `.viho-kv`. Today `x-hire-agent.field` is the only thing in the
       application that renders the primitive, and it always wraps it in `.hla-field` — so a
       narrower selector and a global one currently match the same elements. The scope is not there
       to exclude a present-day caller; it is there because the primitive's `margin-bottom` is what
       a caller OUTSIDE a grid depends on for its rhythm, and the next page to adopt `x-viho.kv`
       inherits that default rather than this page's correction. `.hla-field` is the wrapper this
       component emits, so the reset lands only on rows that have a grid to inherit spacing from,
       which is the condition that actually justifies removing the margin. */
    /* M7.8 adds the horizontal companion. Both live on the same element so a reader looking for
       "how far apart are the fields" finds one answer covering both axes. 1.5rem is what the
       reference page's rows put between their columns; see the grid rule above for why it is
       restated here instead of being read off a Bootstrap variable.

       ── M7.9 — THE ROW GAP WAS AIMED AT A NUMBER THE REFERENCE DOES NOT USE ──────────────────

       M7.6 set this to 0.5rem and named the target "the reference page's `mb-2`". `mb-2` IS
       0.5rem in Bootstrap — but the reference overrides it for exactly these rows:

           .lol-view-page .row.mb-2 { margin-bottom: 0.65rem !important; }

       So the class it was named after never governed the spacing it was measured against. The
       page renders 0.65rem, and this page has been 0.15rem tighter on every row since M7.6.

       MEASURED IN A BROWSER, NOT INFERRED FROM THE CASCADE, because the whole defect was a
       cascade read that looked right. On a live landlord page the reference reports a computed
       `margin-bottom: 10.4px` and 38 consecutive inter-row gaps of 10.39px; this page reported a
       `row-gap` of 8px. 0.65rem is 10.4px, so the two now agree at the value the reference
       actually renders rather than the one its class name implies.

       ONE DIFFERENCE IS NOT REPRODUCED, DELIBERATELY. A margin applies below EVERY row including
       the last, so on the reference a card whose final element is a row carries ~10.4px of
       trailing space before the card's own padding. A row-gap applies only BETWEEN lines and adds
       nothing after the last one. Matching that would mean giving the card asymmetric interior
       padding — 1.25rem above the fields and 1.9rem below — which is a property of how the
       reference is built rather than a spacing decision anyone made. The rhythm BETWEEN rows is
       what "row rhythm" means and is what this aligns; the trailing space stays as it is.

       NOTHING ELSE MOVES. This is one token value. Column gap, cell widths, breakpoints, the
       type scale and the label/value split are all untouched. */
    .hla-detail-page {
        --hla-field-row-gap: 0.65rem;
        --hla-field-col-gap: 1.5rem;
    }

    .hla-detail-page .hla-field .viho-kv {
        margin-bottom: 0;
    }

    /* M7.6 — the reference's type scale, which this page had never adopted.

       Create Offer sets these sizes inline on every row it renders: `.875rem` on the label half,
       `.925rem` on the value half. `.viho-kv-label` and `.viho-kv-value` set colour and weight but
       deliberately no size, so this page had been inheriting the ~1rem body size for both halves.
       Larger type in what was already a looser grid is most of why the two pages did not look
       related; the split ratio was identical the whole time.

       The value stays fractionally larger than its label, as it is on the reference — the label is
       supporting text and the answer is the content, and a scale that flattens them makes a card
       read as an undifferentiated block. */
    .hla-detail-page .hla-field .viho-kv-label {
        font-size: 0.875rem;
    }

    .hla-detail-page .hla-field .viho-kv-value {
        font-size: 0.925rem;
    }

    /* M7.7 — the label column on FULL-SPAN rows only.

       `.viho-kv-split` reserves 41.666% for the label, the ratio col-md-5/7 resolves to, and that is
       right for a half-span cell: the cell is half a card, so the column lands near 190px and a short
       label sits close to its answer. A full-span row applies the same percentage to TWICE the width
       — ~382px at 1440 — so the rule that reads correctly at half span opens ~200px of empty floor on
       a row whose label is short. The two spans were never aligned with each other; each was
       proportional to a different container.

       THE NUMBER IS DERIVED, NOT TUNED, and that is the whole point. A half-span cell is 50% of the
       card, so its label column resolves to 41.666% x 50% = 20.833% OF THE CARD. Giving a full-span
       row that same 20.833% makes its label column exactly as wide, in pixels, as the half-span rows
       stacked above and below it — so every label column on the page ends at one x-position and every
       value begins at the next, whatever the row's span. An earlier draft picked 34% by eye to close
       a target-sized gap; it narrowed the corridor without ever making the two spans agree, which is
       the actual defect. Half-span rows are the reference here because they were always correct.

       So this rule adds no new proportion to the page. It restates the one already in `.viho-kv-split`
       against the container the row actually occupies.

       NO FIELD NAMES IN THIS COMMENT, AND THAT IS A RULE RATHER THAN A STYLE CHOICE. This file is
       emitted inline into the document, so every word here is page text. An earlier draft named the
       multi-select rows it was written to improve, and
       HireAgentFieldPresentationTest::an_empty_multi_select_renders_no_row asserts those labels are
       ABSENT when the answer is empty — the prose alone turned that guard red across all four of its
       data sets. Describe rows by shape, never by label.

       DESKTOP ONLY, AT THE STYLESHEET'S EXISTING 992px BREAKPOINT. Below it there is not enough card
       to divide: at 768px the card is ~408px, so a matched label column would be ~85px and would wrap
       the same multi-select rows this change exists to improve. Tablet keeps 41.666% and
       mobile keeps the primitive's 100% stack. The media query is also what protects that stack —
       this selector outscores the primitive's `max-width: 767.98px` rule, and specificity does not
       care which query a rule sits in, so an unscoped version would silently un-stack every row on a
       phone.

       BOTH EXCLUSIONS ARE LOAD-BEARING, AND THE FIRST IS THE TRAP THE COMMENT BELOW ALREADY NAMES.
       A half-span cell is `col-lg-6 col-12`, so it CARRIES `col-12` as well — Bootstrap resolves
       which one wins by breakpoint, but the class is on the element at every width. `.col-12` alone
       therefore selects half-span rows too, and inside a min-width query it would re-halve the very
       rows this alignment is measured FROM — the reference would move with the thing being aligned to
       it, and the two would never meet. `:not(.col-lg-6)` limits this to genuinely full-width rows.

       M7.8 CHANGED WHICH CLASS THAT IS. The exclusion named the md-breakpoint spelling until the
       two-up split moved from md to lg. An exclusion naming a class the cells no longer carry does
       not fail loudly — it simply stops excluding, and this rule would quietly narrow every
       half-span label to a fifth of its own cell. The class here and the one the field component
       emits are one decision written in two files, and neither may move alone.

       (The retired spelling is described rather than written, for the reason the compensation-card
       note below gives at length: a CSS comment is page text, unlike a Blade comment, so a class
       name in prose here is a class name on the page. Leaving a dead one there invites the next
       reader to grep for it and find this sentence.)

       `:not(.hla-field-badges)` because a badge row is also `col-12`, and M7.4 widens both halves to
       100% to stack a pill run under its label. Without the exclusion this rule outscores that one
       and would fold the run back into a narrow column — reopening the corridor M7.4 closed. */
    /* THE GAP CORRECTION, AND WHY IT IS NOW DERIVED RATHER THAN MEASURED.

       M7.7 subtracted a flat 5px here and explained it as the surplus a full-span cell carries
       because it pays the column gutter once where a half-span pair pays it twice. THE ARITHMETIC WAS
       RIGHT AND THE PREMISE WAS NOT: Bootstrap applies that gutter through `.row > *`, this grid is
       not a row, and so no gutter was being paid by anybody. The 5px was correcting for something
       that was not happening — which is why the two spans still did not line up.

       M7.8 declares the gap instead of assuming it, and the same derivation then holds for real.
       With a grid of width W, a gap of G and two half cells of (W - G)/2:

           half-span label = 41.666% x (W - G)/2 = 20.833%·W - 0.20833·G

       So a full-span label matches it at 20.833% minus 0.20833 of the gap. At the declared 1.5rem
       that is 5px — the same number, now falling out of a gap the page actually applies rather than
       standing in for one it never did. Written as the expression instead of the result so that
       retuning `--hla-field-col-gap` moves the alignment with it; a literal would silently go stale.

       Subtracted from the VALUE in the opposite direction, so label + the primitive's own gap + value
       still sums to 100% and the row's right edge stays flush with the card, as on a half-span row. */
    @media (min-width: 992px) {
        /* The other half of the gap, and the reason `column-gap` on the grid is safe. Bootstrap sizes
           `.col-lg-6` at exactly 50%, so a pair of them plus any gap overflows and wraps to one per
           line. Narrowing each cell by half the gap brings the pair back to 100%: two cells at
           (50% - G/2) plus one G between them. Emitted only at lg, because that is the only width at
           which these cells are side by side — below it they are `col-12` and the gap is inert.

           `width` as well as the flex pair because `.col-lg-6` sets `width: 50%` outright. A definite
           flex-basis already wins over it, so this is belt and braces rather than load-bearing. */
        .hla-detail-page .hla-field-grid > .hla-field.col-lg-6 {
            flex: 0 0 calc(50% - var(--hla-field-col-gap) / 2);
            max-width: calc(50% - var(--hla-field-col-gap) / 2);
            width: calc(50% - var(--hla-field-col-gap) / 2);
        }

        .hla-detail-page .hla-field.col-12:not(.col-lg-6):not(.hla-field-badges) .viho-kv-split > .viho-kv-label {
            flex: 0 0 calc(20.833% - var(--hla-field-col-gap) * 0.20833);
            max-width: calc(20.833% - var(--hla-field-col-gap) * 0.20833);
        }

        .hla-detail-page .hla-field.col-12:not(.col-lg-6):not(.hla-field-badges) .viho-kv-split > .viho-kv-value {
            flex: 0 0 calc(79.167% - var(--viho-space-md) + var(--hla-field-col-gap) * 0.20833);
            max-width: calc(79.167% - var(--viho-space-md) + var(--hla-field-col-gap) * 0.20833);
        }
    }

    /* ON THE ONE WIDTH RULE THERE IS, AND THE SHAPE IT MUST KEEP. Until M7.8 this note read "NO
       WIDTH RULES FOR THE CELLS THEMSELVES", and the reasoning behind it still stands even though
       the conclusion has moved: a half-span cell is `col-lg-6 col-12` and a full-span one is
       `col-12`, so a rule targeting `.col-12` matches BOTH and forces every field to full width —
       which is exactly what a first attempt here did, collapsing the two-up grid this page exists
       to produce.

       The lg rule above is the single exception, and it is safe for the reason that trap is not: it
       names `.col-lg-6`, which only half-span cells carry, and it sits inside the min-width query
       where those cells are actually side by side. Below lg it does not apply at all and Bootstrap's
       own cascade resolves the pair unaided — `col-12` below 992, `col-lg-6` at and above it.

       Any future width rule here must clear the same two bars: name the class that distinguishes
       the spans rather than the one they share, and scope itself to the breakpoint where the
       distinction is real. */

    /* M7.4 refinement — a pill run stacks under its label instead of sitting in the value column.

       `.viho-kv-split` reserves 41.6% for the label and starts the value there, which is right for
       "City / Redington Beach" and wrong for a label followed by six pills: the run wrapped inside
       the remaining 58% while the left half of the card stayed empty, opening a corridor down the
       middle. Widening both halves to 100% turns the same primitive into a stacked pair — label on
       its own line, run beginning at the card's left edge and wrapping across the full width.

       DONE HERE RATHER THAN IN THE PRIMITIVE. x-viho.kv still emits `viho-kv-label` and
       `viho-kv-value` exactly as it does everywhere else; only their widths are overridden, and
       only on this page. A pill run is a Hire Agent presentation decision, and VIHO is shared with
       Create Offer — a layout added there for one product's benefit is the kind of thing the
       primitive guard tests exist to keep out.

       THE PILLS ARE NOT STYLED HERE, DELIBERATELY. `badge bg-secondary` comes from Bootstrap and
       from the callers' own markup, unchanged by this milestone. This rule positions the container
       and nothing inside it, so a pill looks the same in both flag states. */
    .hla-detail-page .hla-field-badges .viho-kv-split > .viho-kv-label,
    .hla-detail-page .hla-field-badges .viho-kv-split > .viho-kv-value {
        flex: 0 0 100%;
        max-width: 100%;
    }

    /* The run sits directly under its label rather than a full row-gap away — close enough to read
       as one field, which is the complaint that prompted the mode. */
    .hla-detail-page .hla-field-badges .viho-kv-split > .viho-kv-value {
        margin-top: var(--viho-space-2xs);
    }

    /* Pills wrap as a group with even spacing instead of relying on whitespace between inline
       elements, which collapses unpredictably once a run wraps to a second line. */
    .hla-detail-page .hla-field-badges .viho-kv-value {
        display: flex;
        flex-wrap: wrap;
        gap: var(--viho-space-2xs);
        align-items: center;
    }

    /* ── SECTION NAV DENSITY ──────────────────────────────────────────────────────────────────
       The bar is one nowrap flex row inside an `overflow-x: auto` list whose scrollbar is
       hidden on purpose (viho/styles.blade.php). That combination has no failure state that
       is visible: once the labels are wider than the main column the tail of the row is
       simply not on screen, with no scrollbar, no fade and nothing to suggest it exists.

       The last entry is always the owner-info one — "Agent's Info" when the owner is an
       agent — so the invisible end of the row is always the same entry. Landlord carries six
       entries and fits; seller, tenant and buyer carry seven, because they also have
       Financing (seller, buyer) or Pre-Screening (tenant). Measured against the ~966px main
       column at the xxl container: landlord ~937px, seller ~1070px, tenant ~1106px, buyer
       ~1150px. So the entry was partly cut on seller and tenant and entirely past the edge
       on buyer, whose labels are the longest — which is why buyer read as MISSING the
       section rather than as clipping it. The markup was correct on all four the whole time:
       the anchor, the section and their agreement are what HireAgentSectionNavTest already
       asserts, and it asserts them on landlord, the one role that fits.

       Two rules, and the second is the one that makes the first safe:

       1. DENSITY. Link padding drops to --viho-space-md and the label to --viho-font-xs —
          both existing tokens, so the bar stays on the type and spacing scale rather than
          acquiring magic numbers. Measured that way the four roles come to ~773px
          (landlord), ~882px (seller), ~914px (tenant) and ~955px (buyer) against the ~966px
          column, so all four fit on one row with buyer, the widest, clearing by ~11px.

          THE SIZE MOVED AND THE PADDING DID NOT, and that is the deliberate half. Taking the
          label one further step down the type scale buys more than the equivalent step on
          the padding — seven labels are ~820px of text against ~168px of padding — so
          spending it on the font keeps the gap between adjacent tabs at a readable 24px
          rather than crushing the row to buy the same pixels.

       2. A CEILING THAT CANNOT CLIP. Fitting by ~11px is fitting, but it is not a margin to
          rely on: one longer label in a future section, or a container narrower than the
          xxl one, puts the row back over with the same silent failure. From `lg` up the row
          may WRAP, so overflow becomes a second line that can be read rather than a first
          line that cannot be seen. At the xl container (1140px, a ~831px column) the three
          seven-entry roles do legitimately exceed one row — 831px cannot hold seven of these
          labels at any sane density — and there the ceiling is what renders them instead of
          hiding them.

       BELOW `lg` NOTHING CHANGES. Horizontal scrolling is the intended small-screen
       behaviour and keeps it; the media query starts at 992px, which is where the sidebar
       column appears and where the reported clipping lives. Left alignment is preserved in
       both modes — centring a row that fits would be a visible change to every role for the
       sake of the rare row that does not. */
    .hla-detail-page .viho-section-nav-link {
        padding-left: var(--viho-space-md);
        padding-right: var(--viho-space-md);
        font-size: var(--viho-font-xs);
    }

    /* While the row still scrolls (below `lg`), the final link ends flush against the
       container edge, which reads as a cut-off word rather than as "there is more this way".
       A tail of end padding on the scroller gives it somewhere to stop. Removed once the row
       wraps, where there is no scroll and the padding would only skew the last line. */
    .hla-detail-page .viho-section-nav-list {
        padding-right: var(--viho-space-md);
    }

    @media (min-width: 992px) {
        .hla-detail-page .viho-section-nav-list {
            flex-wrap: wrap;
            overflow-x: visible;
            padding-right: 0;
        }
    }

    /* ── GUEST CTA AMOUNT ─────────────────────────────────────────────────────────────────────
       The companion figure under x-hire-agent.login-cta. It reads as part of the button rather
       than as a paragraph that happens to follow one, so it takes the button's own width and
       sits tight beneath it. Tokens only — the whole point of the shared component is that no
       role hand-writes this treatment. */
    .hla-detail-page .hla-cta-amount {
        margin-top: var(--viho-space-2xs);
        text-align: center;
        font-size: var(--viho-font-sm);
        font-weight: var(--viho-weight-semibold);
        color: var(--viho-label);
    }
@endif
</style>
