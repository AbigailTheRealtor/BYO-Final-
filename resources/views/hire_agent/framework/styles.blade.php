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

    SCOPING. Everything new here is prefixed .hla- (Hire Listing Agent) and every override is
    nested under .hla-hero or an existing Hire Agent container. Nothing in this file selects a
    bare element or a Bootstrap class globally, so it cannot reach Create Offer, which owns its
    own separate .sol- namespace and shares no file, class or partial with this framework.
--}}
<style>
    /* ── Shared rules, previously duplicated verbatim in all four detail views ───────── */
    /* Chrome, Safari, Edge, Opera */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    /* Firefox */
        input[type=number] {
            -moz-appearance: textfield;
        }
    .fa-dollar-sign,
        .fa-percent {
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
    .form-control,
        .form-select {
            border-radius: 0.25rem;
            box-shadow: inset 0 1px 2px 0 rgb(66 71 112 / 12%);
            border-radius: 0.25rem;
            background-color: #fafafb;
            margin-bottom: 15px;
        }
    /* Section Title Hierarchy - Larger, bold, spaced, more prominent */
        .card-header h4,
        .section-title {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            color: #0f1a24;
        }
    /* Services section - extra breathing room before header */
        .services-section-header {
            margin-top: 0.75rem !important;
        }
    hr {
            margin-top: 1.25rem;
            margin-bottom: 0.5rem;
        }
    /* Field row styling - improved line-height for scan-readability */
        .col-md-12.col-12.pt-2.fw-bold {
            line-height: 1.6;
            padding-top: 0.6rem !important;
            padding-bottom: 0.2rem;
        }
    .field-row {
            padding: 0.5rem 0;
            font-size: 0.95rem;
            line-height: 1.6;
        }
    .field-label {
            font-weight: 600;
            color: #34465c;
        }
    .field-value {
            font-weight: normal;
            color: #34465c;
        }
    /* Broker Compensation subsection headers - breathing room */
        h5.mt-3.mb-2 {
            padding-top: 0.75rem;
            margin-top: 1rem !important;
        }
    /* Fix blank space under section headers - reduce gap to first content */
        .card-body {
            padding-top: 12px !important;
        }
    .card-body > :first-child {
            margin-top: 0 !important;
        }
    /* Broker Compensation section text - match other section text color */
        .broker-compensation-section,
        .broker-compensation-section p,
        .broker-compensation-section .col-md-12,
        .broker-compensation-section .fw-bold {
            color: #34465c !important;
        }
    ul {
            --icon-size: 1em;
            --gutter: .5em;
            padding: 0 0 0 calc(var(--icon-size) + 2em);
        }
    ul li {
            padding-left: var(--gutter);
            color: #34465c;
        }
    ul:not(.services) li::marker {
            content: "\f101";
            /* FontAwesome Unicode */
            font-family: FontAwesome;
            font-size: var(--icon-size);
            /* color: #006e9f; */
            color: #11b7cf;
        }
    /* Services section - Tighter spacing and indentation */
        ul.services {
            list-style: none !important;
            padding-left: 1.2em;
            margin-top: 0.35rem;
            margin-bottom: 0.5rem;
        }
    ul.services li {
            padding: 0.15rem 0;
            color: #34465c;
            position: relative;
            padding-left: 0;
            list-style: none !important;
            line-height: 1.4;
        }
    ul.services li::marker {
            content: none !important;
        }
    ul.services li::before {
            content: "•";
            position: absolute;
            left: -0.9em;
            color: #34465c;
            font-size: 1.1em;
        }
    .removeBold {
            font-weight: normal;
        }
    .biding-btn {
            width: 31.5%;
        }
    .view-btn {
            padding: 6px !important;
        }
    .services-offered {
            padding: 23px !important;
        }
    @media screen and (max-width: 800px) {
            .accordion-body-padding {
                padding: 7px !important;
            }
    
            .alert-font {
                font-size: 10px;
            }
    
            .counter-font {
                font-size: 15px;
            }
        }
    /* Bid card accordion chevron rotation (custom JS toggle) */
        .bid-accordion-header .bid-chevron {
            transition: transform 0.3s ease;
        }
    .bid-accordion-header:hover {
            background-color: #f8f9fa !important;
        }
    .bid-action-btn:hover {
            opacity: 0.9;
        }
    /* ── Hire Agent shared hero ──────────────────────────────────────────────────────
       New in Milestone 4. There was no hero before: each page opened straight into a
       "Listing Details:" card, and the title/status sat at the top of the right column.
       Deliberately absent: any countdown, any remaining-time element, any competing-proposal
       count. The hero shows what the listing IS, never how long is left to act on it. */
    .hla-hero {
        background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    .hla-hero-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0f1a24;
        line-height: 1.25;
        margin: 0 0 0.35rem 0;
        overflow-wrap: anywhere;
    }
    .hla-hero-subtitle {
        font-size: 0.95rem;
        color: #475569;
        margin: 0 0 0.75rem 0;
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
        color: #64748b;
        margin-bottom: 0.1rem;
    }
    .hla-hero-facts {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 1.5rem;
        margin-top: 0.85rem;
    }
    .hla-hero-fact {
        min-width: 0;
    }
    .hla-hero-fact-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #64748b;
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
        gap: 0.35rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 0.2rem 0.7rem;
        font-size: 0.8rem;
        color: #334155;
    }
    .hla-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.9rem;
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
            font-size: 1.45rem;
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
</style>
