{{--
    VIHO — the shared presentation foundation.

    Milestone 1. This file is the neutral namespace both products may consume, established by the
    dependency contract in Milestone 0:

        Hire Agent ──► VIHO ◄── Create Offer     permitted
        Hire Agent ──✗──► Create Offer           forbidden
        Create Offer ──✗──► Hire Agent           forbidden
        VIHO ──✗──► either product               forbidden

    NOTHING INCLUDES THIS FILE YET, deliberately. M1 is additive: the foundation lands first so
    that M2's components and M3's migration have somewhere to stand. A page adopts it in M3
    (Hire Agent) and M8 (Create Offer), never before. Until then it is inert by construction, which
    is why this milestone cannot change how any page renders.

    WHERE THE VALUES COME FROM. Every value below already exists in the four Create Offer views.
    Nothing here is designed, rounded, harmonised or improved. The four views each carry a
    byte-identical 13-token `:root` block — verified identical by checksum, not by eye — and those
    thirteen are reproduced verbatim. The remaining families (radius, shadow, spacing, typography)
    were never declared as tokens at all; they exist only as literals repeated through the four
    stylesheets, and a value was promoted to a token here ONLY when it appears in all four views.
    Each such token records where it was observed.

    A NOTE ON WHAT THE EXISTING TOKENS DO TODAY: nothing. The four `:root` blocks are declared and
    then never read — there is not one `var(--viho-…)` anywhere in the repository. The tokens are
    presently documentation of an intent that was never wired up. That is the strongest possible
    guarantee for this milestone: even if a page did include this file today, re-declaring values
    that nothing consumes cannot move a pixel. It is also why the four blocks never drifted — an
    unread value has nothing to drift against.

    DELIBERATELY NOT HERE. No component classes. A section card, a badge, a hero and a sticky rail
    are M2's job, and building them here would mean designing a component API in a file whose
    reviewers were asked to check tokens. No JavaScript. No breakpoint media queries — see the
    Responsive section for why the breakpoint tokens cannot be used in one.

    WHAT IS DEFERRED, AND WHY IT IS NOT HERE. Four measured disagreements between the Create Offer
    views are recorded in the sections below rather than resolved. Picking a winner for any of them
    would change what a page renders, which is out of scope for a foundation milestone and belongs
    to the visual review in M3/M8.
--}}
<style>
:root {
    /* ── Declared tokens ──────────────────────────────────────────────────────
       The existing 13. Present, byte-identical, in all four Create Offer views
       (seller:57, buyer:57, tenant:106, landlord:112). Reproduced exactly —
       including the upper-case hex, which is how they are written today. */
    --viho-primary:       #2563EB;
    --viho-primary-hover: #1D4ED8;
    --viho-page-bg:       #F8FAFC;
    --viho-card-bg:       #FFFFFF;
    --viho-heading:       #0F172A;
    --viho-text:          #334155;
    --viho-label:         #64748B;
    --viho-border:        #E2E8F0;
    --viho-success:       #16A34A;

    /* Role accents. Neutral to own — the shared layer holds the palette, and a
       product decides which entry applies to the page it is rendering. */
    --viho-seller:        #2563EB;
    --viho-buyer:         #7C3AED;
    --viho-landlord:      #0F766E;
    --viho-tenant:        #0891B2;

    /* ── Surfaces ─────────────────────────────────────────────────────────────
       Promoted from literals occurring in all four views. */
    --viho-surface-subtle:       #F1F5F9;  /* background: #f1f5f9 */
    --viho-surface-inverse:      #1E293B;  /* background: #1e293b — hero shell */
    --viho-surface-inverse-deep: #0F172A;  /* background: #0f172a */
    --viho-surface-gradient:     linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);

    /* ── Text ─────────────────────────────────────────────────────────────────
       --viho-heading / --viho-text / --viho-label above are the declared three.
       These three are the undeclared companions used just as consistently. */
    --viho-text-strong: #1E293B;  /* color: #1e293b */
    --viho-text-soft:   #475569;  /* color: #475569 */
    --viho-text-muted:  #94A3B8;  /* color: #94a3b8 */

    /* ── Borders ──────────────────────────────────────────────────────────────
       --viho-border (#E2E8F0) above is the declared default. */
    --viho-border-strong: #CBD5E1;  /* border: 1px solid #cbd5e1 */
    --viho-border-subtle: #F1F5F9;  /* border-bottom: 1px solid #f1f5f9 */

    /* ── Primary tint ─────────────────────────────────────────────────────────
       The tinted surface/border/foreground trio that accompanies the primary. */
    --viho-primary-tint:        #EFF6FF;  /* background: #eff6ff */
    --viho-primary-tint-border: #BFDBFE;  /* border: 1px solid #bfdbfe */
    --viho-primary-text:        #1D4ED8;  /* color: #1d4ed8 */

    /* ── Semantic status tones ────────────────────────────────────────────────
       Five tone triplets, each present with identical values in all four views.

       DEFERRED — ROSE. Seller, Buyer and Tenant define a sixth `rose` tone
       (#fff1f2 / #be123c / #fecdd3); Landlord does not. Three-of-four is not
       common, and inventing Landlord's copy would add a tone to a page that has
       never had one. Recorded, not resolved.

       DEFERRED — THE SUCCESS PILL. Seller, Buyer and Tenant render the hero
       status pill green (#dcfce7 / #166534 / #86efac); Landlord renders it teal
       (#f0fdfa / #0f766e / #99f6e4), matching its own role accent. That reads as
       intent rather than drift, so no neutral "status pill" token is defined —
       it is a per-role choice, and flattening it would recolour a live page. */
    --viho-status-blue-bg:       #EFF6FF;
    --viho-status-blue-fg:       #1D4ED8;
    --viho-status-blue-border:   #BFDBFE;

    --viho-status-green-bg:      #F0FDF4;
    --viho-status-green-fg:      #15803D;
    --viho-status-green-border:  #BBF7D0;

    --viho-status-purple-bg:     #FAF5FF;
    --viho-status-purple-fg:     #7C3AED;
    --viho-status-purple-border: #DDD6FE;

    --viho-status-amber-bg:      #FFFBEB;
    --viho-status-amber-fg:      #B45309;
    --viho-status-amber-border:  #FDE68A;

    --viho-status-teal-bg:       #F0FDFA;
    --viho-status-teal-fg:       #0F766E;
    --viho-status-teal-border:   #99F6E4;

    /* ── Radius ───────────────────────────────────────────────────────────────
       An inventory of the radii in use, not a new scale. Every step below occurs
       in all four views. Landlord writes `border-radius:8px` without the space
       and uses `.8rem`-style leading-dot decimals throughout; that is notation,
       not a different value, and is normalised here without changing anything. */
    --viho-radius-sm:     7px;
    --viho-radius-md:     8px;
    --viho-radius-lg:     0.75rem;
    --viho-radius-xl:     1rem;
    --viho-radius-pill:   20px;
    --viho-radius-circle: 50%;

    /* ── Shadow ───────────────────────────────────────────────────────────────
       Four elevations, each identical in all four views.

       DEFERRED — RESTING ELEVATION. Seller and Landlord use
       `0 2px 8px rgba(0,0,0,.10)` where Buyer and Tenant use the same geometry at
       `.06`. A two-two split is drift with no majority; both are preserved in
       their own views and neither is promoted.

       DEFERRED — ACCENT ELEVATION. The tinted hover shadow is role-coloured:
       rgba(37,99,235,.12) for Seller and Buyer, rgba(15,118,110,.12) for
       Landlord, rgba(13,148,136,.12) for Tenant. Role-specific by construction,
       so it parameterises later rather than flattening now. Note for M2: Tenant's
       #0D9488 does not match its own --viho-tenant (#0891B2); that predates this
       milestone and is left exactly as it renders. */
    --viho-shadow-card:    0 1px 6px rgba(0,0,0,.06);
    --viho-shadow-soft:    0 2px 12px rgba(0,0,0,.06);
    --viho-shadow-raised:  0 4px 16px rgba(0,0,0,.08);
    --viho-shadow-overlay: 0 4px 24px rgba(0,0,0,.10);
    --viho-shadow-lifted:  0 -4px 16px rgba(0,0,0,.10);

    /* ── Spacing ──────────────────────────────────────────────────────────────
       Each step is a value observed in all four views, named by relative size.
       This is a catalogue of what is already used; no step was added to round the
       scale out, which is why the intervals are uneven. */
    --viho-space-3xs: 0.1rem;
    --viho-space-2xs: 0.25rem;
    --viho-space-xs:  0.35rem;
    --viho-space-sm:  0.4rem;
    --viho-space-md:  0.75rem;
    --viho-space-lg:  0.9rem;
    --viho-space-xl:  1.25rem;
    --viho-space-2xl: 1.5rem;

    /* ── Typography ───────────────────────────────────────────────────────────
       Sizes observed in all four views. No family is set here: both products
       inherit the application font, and declaring one would be a visual change. */
    --viho-font-3xs:     0.69rem;
    --viho-font-2xs:     0.72rem;
    --viho-font-xs:      0.75rem;
    --viho-font-sm:      0.78rem;
    --viho-font-md:      0.83rem;
    --viho-font-lg:      1.05rem;
    --viho-font-xl:      1.45rem;
    --viho-font-2xl:     1.85rem;
    --viho-font-display: 3rem;

    --viho-weight-semibold:   600;
    --viho-weight-bold:       700;
    --viho-weight-extrabold:  800;

    --viho-tracking-tighter: -0.03em;
    --viho-tracking-tight:   -0.01em;
    --viho-tracking-wide:     0.07em;

    --viho-leading-none:    1;
    --viho-leading-tight:   1.15;
    --viho-leading-snug:    1.2;
    --viho-leading-normal:  1.35;
    --viho-leading-relaxed: 1.45;

    /* ── Motion ───────────────────────────────────────────────────────────────
       One duration. Every transition in all four views uses .15s. */
    --viho-transition: .15s;

    /* ── Responsive constants ─────────────────────────────────────────────────
       READ THIS BEFORE USING THEM. A CSS custom property CANNOT appear in a media
       query condition: `@media (max-width: var(--viho-bp-lg))` does not work in
       any browser, because media conditions are evaluated before custom
       properties resolve. These two exist so the breakpoints have one written-down
       source and a reviewer can see they agree; the literal must still be typed
       into each `@media` rule. They are reference values, not machinery.

       Both are the values already in use: 767.98px and 991.98px each appear in
       all four views. */
    --viho-bp-md: 767.98px;
    --viho-bp-lg: 991.98px;

    /* ── Semantic tone: danger ────────────────────────────────────────────────
       ADDED IN M2, AND NOT THE RESOLUTION OF THE M1 ROSE DEFERRAL. Read both
       halves of that sentence.

       M2's badge contract requires a `danger` variant. No danger tone is common
       to all four Create Offer views: Seller, Buyer and Tenant define a `rose`
       badge (#fff1f2 / #be123c / #fecdd3) and Landlord does not. M1 therefore
       declined to promote rose, on the stated grounds that inventing Landlord's
       copy "would add a tone to a page that has never had one".

       These tokens take rose's values under a semantic name. That does not
       violate the deferral's concern, because a token in the shared library
       renders nothing: Landlord's view still defines no rose class, still emits
       no danger badge, and is byte-for-byte unaffected. What remains genuinely
       open is the M8 question — when Create Offer migrates, does Landlord gain a
       danger badge or keep going without one? M2 does not answer that, and
       naming these tokens `danger` rather than `rose` is meant to keep the two
       questions apart rather than to slip past the M1 assertion. The M1 test was
       updated deliberately to say so. */
    --viho-status-danger-bg:     #FFF1F2;
    --viho-status-danger-fg:     #BE123C;
    --viho-status-danger-border: #FECDD3;
}

/* ═══════════════════════════════════════════════════════════════════════════
   M2 — neutral presentation primitives.

   Every rule below is consumed by exactly one `<x-viho.*>` component and styles
   only its own `.viho-` class. Values come from the token block above rather
   than from repeated literals, which is the whole reason M1 landed first.

   NOT HERE, DELIBERATELY: page, grid, hero, sidebar, tab and navigation styles.
   Those are composed surfaces whose data and interaction contracts have not been
   mapped yet, and building them from the four current copies would bake in
   decisions that the hero and layout milestones exist to make.
   ═══════════════════════════════════════════════════════════════════════════ */

/* ── Card ────────────────────────────────────────────────────────────────────
   Geometry is byte-identical across all four Create Offer views (43 usages), so
   there was nothing to reconcile. `overflow:hidden` is what lets the header
   gradient meet the rounded corner. */
.viho-card {
    background: var(--viho-card-bg);
    border: 1px solid var(--viho-border);
    border-radius: var(--viho-radius-lg);
    box-shadow: var(--viho-shadow-card);
    margin-bottom: 1.75rem;
    overflow: hidden;
}
.viho-card-accent {
    border-color: var(--viho-accent, var(--viho-primary));
}
.viho-card-head {
    background: var(--viho-surface-gradient);
    border-bottom: 1px solid var(--viho-border);
    padding: var(--viho-space-lg) var(--viho-space-xl);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--viho-space-md);
}
.viho-card-head-main {
    min-width: 0;
}
.viho-card-body {
    padding: var(--viho-space-xl) var(--viho-space-2xl);
}
.viho-card-compact .viho-card-head {
    padding: var(--viho-space-md) var(--viho-space-lg);
}
.viho-card-compact .viho-card-body {
    padding: var(--viho-space-lg);
}
.viho-card-foot {
    padding: var(--viho-space-md) var(--viho-space-2xl);
    border-top: 1px solid var(--viho-border-subtle);
    background: var(--viho-card-bg);
}

/* ── Section header ──────────────────────────────────────────────────────────
   Typography matches the Create Offer card header exactly (700 / 1.05rem /
   -0.01em / #1e293b). The element is chosen by the caller, so this styles a
   class rather than an `h*` tag — a primitive that hard-coded `h4` would dictate
   document outline to every page that used it. */
.viho-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--viho-space-md);
}
.viho-section-header-title {
    font-weight: var(--viho-weight-bold);
    font-size: var(--viho-font-lg);
    letter-spacing: var(--viho-tracking-tight);
    color: var(--viho-text-strong);
    margin: 0;
}
.viho-section-header-desc {
    font-size: var(--viho-font-md);
    color: var(--viho-label);
    margin: var(--viho-space-2xs) 0 0 0;
}
.viho-section-header-icon {
    margin-right: var(--viho-space-xs);
}

/* ── Section navigation ──────────────────────────────────────────────────────
   M5.2. A horizontal bar of in-page links for a long document.

   STICKY IS DECLARED HERE, OFFSET IS NOT. `top` is left to the consumer as
   --viho-section-nav-offset, because the correct offset is the height of
   whatever fixed chrome the host page has above it — a value this layer cannot
   know and must not guess. It defaults to 0, which is correct for a page with no
   fixed header and visibly wrong rather than subtly wrong for one that has.

   ONE variable, used twice: the bar sticks below the chrome, and anchored
   sections clear the same distance. Two variables would inevitably drift apart
   and the symptom — headings landing just under the bar — is easy to miss.

   The list scrolls horizontally rather than wrapping: a wrapped nav changes
   height as sections appear and disappear, and a sticky element that changes
   height shifts the content beneath it on every page. */
.viho-section-nav {
    position: sticky;
    top: var(--viho-section-nav-offset, 0px);
    z-index: 20;
    background: var(--viho-card-bg);
    border-bottom: 1px solid var(--viho-border);
    box-shadow: var(--viho-shadow-card);
    margin-bottom: var(--viho-space-2xl);
}
.viho-section-nav-list {
    display: flex;
    gap: 0;
    list-style: none;
    padding: 0;
    margin: 0;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.viho-section-nav-list::-webkit-scrollbar {
    display: none;
}
.viho-section-nav-item {
    padding: 0;
    margin: 0;
}
/* MARKER SUPPRESSION, TWO WAYS. `list-style: none` on the list above is NOT enough, and this is
   not theoretical — it is what the Hire Agent detail page did to this bar. list-style-type only
   chooses the DEFAULT marker; a host that sets ::marker `content` directly still paints one. The
   landlord page carries exactly such a legacy rule
   (.hla-detail-page ul:not(.services) li::marker { content: "\f101"; font-family: FontAwesome })
   which rendered the bar as "» Property Details » Leasing Terms »  …".

   Order could not fix it: that selector is more specific than a single class, so it wins wherever
   this stylesheet is loaded. The primary fix is therefore `display: block` — a box that is not a
   list-item generates no marker at all, so there is nothing for a host rule to style. The ::marker
   reset stays as a second line of defence for a host that forces display back, and is written with
   enough specificity to actually outrank the rule above rather than merely look like a fix.

   No `!important` anywhere in this file, deliberately, and this rule is not the place to start. */
.viho-section-nav-list .viho-section-nav-item {
    display: block;
}
.viho-section-nav .viho-section-nav-list .viho-section-nav-item::marker {
    content: none;
}
.viho-section-nav-link {
    display: block;
    padding: var(--viho-space-md) var(--viho-space-xl);
    font-size: var(--viho-font-md);
    font-weight: var(--viho-weight-semibold);
    color: var(--viho-label);
    text-decoration: none;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    transition: color .15s, border-color .15s;
}
.viho-section-nav-link:hover,
.viho-section-nav-link:focus-visible,
.viho-section-nav-link[aria-current="true"] {
    color: var(--viho-primary);
    border-bottom-color: var(--viho-primary);
}
/* Anchored sections must not land under the sticky bar. The consumer sets the
   same offset it gave --viho-section-nav-offset. */
.viho-section-nav-target {
    scroll-margin-top: var(--viho-section-nav-offset, 0px);
}

/* ── Key/value row ───────────────────────────────────────────────────────────
   The single most repeated pattern in either product: ~540 `$row()` calls across
   Create Offer and ~340 rows in Hire Agent.

   TWO LAYOUTS, BOTH REAL. Create Offer renders a 5/7 two-column grid; Hire Agent
   renders label and value inline on one full-width line. Neither is a mistake,
   so `layout` selects between them instead of one being converted to the other.
   Grid columns use the same 41.666%/58.333% Bootstrap resolves col-md-5/7 to, so
   the primitive does not require a grid framework to sit inside. */
.viho-kv {
    display: block;
    margin-bottom: 0.65rem;
}
.viho-kv-label {
    color: var(--viho-label);
    font-weight: var(--viho-weight-semibold);
}
.viho-kv-value {
    color: var(--viho-text-strong);
    overflow-wrap: break-word;
    word-break: break-word;
}
.viho-kv-split {
    display: flex;
    flex-wrap: wrap;
    gap: 0 var(--viho-space-md);
}
.viho-kv-split > .viho-kv-label {
    flex: 0 0 41.666%;
    max-width: 41.666%;
}
.viho-kv-split > .viho-kv-value {
    flex: 0 0 calc(58.333% - var(--viho-space-md));
    max-width: calc(58.333% - var(--viho-space-md));
}
@media (max-width: 767.98px) {
    .viho-kv-split > .viho-kv-label,
    .viho-kv-split > .viho-kv-value {
        flex: 0 0 100%;
        max-width: 100%;
    }
}
.viho-kv-inline .viho-kv-label {
    font-weight: var(--viho-weight-bold);
}
.viho-kv-inline .viho-kv-value {
    font-weight: 400;
}
.viho-kv-emphasized .viho-kv-value {
    font-weight: var(--viho-weight-bold);
    color: var(--viho-heading);
}
.viho-kv-muted .viho-kv-value {
    color: var(--viho-text-muted);
}
.viho-kv-empty {
    color: var(--viho-text-muted);
    font-style: italic;
}
.viho-kv-icon {
    margin-right: var(--viho-space-2xs);
    color: var(--viho-label);
}

/* ── Badge / status pill ─────────────────────────────────────────────────────
   Base geometry is common to all four views.

   PARAMETERISED, NOT STANDARDISED. Seller and Buyer clamp a long badge with
   `max-width:100%; overflow:hidden; text-overflow:ellipsis`; Landlord and Tenant
   do not. A two-two split has no majority, so truncation is opt-in via
   `.viho-badge-truncate` and neither behaviour is imposed.

   The `accent` variant reads caller-supplied custom properties and has no colour
   of its own — a shared primitive must not know which role is rendering it. */
.viho-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.73rem;
    font-weight: var(--viho-weight-semibold);
    padding: var(--viho-space-2xs) 0.55rem;
    border-radius: var(--viho-radius-pill);
    border: 1px solid;
    white-space: nowrap;
}
.viho-badge-truncate {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}
.viho-badge-pill {
    font-size: var(--viho-font-sm);
    font-weight: var(--viho-weight-bold);
    padding: 0.28rem 0.7rem;
}
.viho-badge-neutral {
    background: var(--viho-surface-subtle);
    color: var(--viho-text);
    border-color: var(--viho-border);
}
.viho-badge-primary {
    background: var(--viho-status-blue-bg);
    color: var(--viho-status-blue-fg);
    border-color: var(--viho-status-blue-border);
}
.viho-badge-success {
    background: var(--viho-status-green-bg);
    color: var(--viho-status-green-fg);
    border-color: var(--viho-status-green-border);
}
.viho-badge-warning {
    background: var(--viho-status-amber-bg);
    color: var(--viho-status-amber-fg);
    border-color: var(--viho-status-amber-border);
}
.viho-badge-danger {
    background: var(--viho-status-danger-bg);
    color: var(--viho-status-danger-fg);
    border-color: var(--viho-status-danger-border);
}
.viho-badge-info {
    background: var(--viho-status-teal-bg);
    color: var(--viho-status-teal-fg);
    border-color: var(--viho-status-teal-border);
}
.viho-badge-accent {
    background: var(--viho-accent-bg, var(--viho-surface-subtle));
    color: var(--viho-accent-fg, var(--viho-text));
    border-color: var(--viho-accent-border, var(--viho-border));
}

/* ── Button ──────────────────────────────────────────────────────────────────
   Geometry from the Create Offer action button.

   PARAMETERISED, NOT STANDARDISED. Seller, Buyer and Tenant transition
   `background` and `border-color`; Landlord transitions `background` only. The
   three-way form is used here because it is the majority AND is a superset — a
   page that never changes border-colour is unaffected by it being transitionable.
   Recorded so the choice is visible rather than assumed.

   The focus ring is deliberately not `outline:none`. */
.viho-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 0.6rem var(--viho-space-md);
    font-size: var(--viho-font-md);
    font-weight: var(--viho-weight-semibold);
    line-height: var(--viho-leading-normal);
    border-radius: var(--viho-radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: background var(--viho-transition), border-color var(--viho-transition), color var(--viho-transition);
}
.viho-btn:focus-visible {
    outline: 2px solid var(--viho-primary);
    outline-offset: 2px;
}
.viho-btn-block {
    width: 100%;
    text-align: left;
    justify-content: flex-start;
}
.viho-btn-primary {
    background: var(--viho-primary);
    color: #fff;
    border-color: var(--viho-primary);
}
.viho-btn-primary:hover {
    background: var(--viho-primary-hover);
    border-color: var(--viho-primary-hover);
    color: #fff;
}
.viho-btn-secondary {
    background: var(--viho-surface-subtle);
    color: var(--viho-text);
    border-color: var(--viho-border);
}
.viho-btn-secondary:hover {
    background: var(--viho-page-bg);
    color: var(--viho-text-strong);
}
.viho-btn-outline {
    background: var(--viho-card-bg);
    color: var(--viho-text);
    border-color: var(--viho-border-strong);
}
.viho-btn-outline:hover {
    background: var(--viho-page-bg);
    color: var(--viho-text-strong);
}
.viho-btn-subtle {
    background: transparent;
    color: var(--viho-text);
    border-color: transparent;
}
.viho-btn-subtle:hover {
    background: var(--viho-surface-subtle);
}
.viho-btn-success {
    background: var(--viho-success);
    color: #fff;
    border-color: var(--viho-success);
}
.viho-btn-success:hover {
    background: var(--viho-status-green-fg);
    border-color: var(--viho-status-green-fg);
    color: #fff;
}
.viho-btn-danger {
    background: var(--viho-status-danger-fg);
    color: #fff;
    border-color: var(--viho-status-danger-fg);
}
.viho-btn-danger:hover {
    filter: brightness(0.94);
    color: #fff;
}
.viho-btn-icon {
    padding: 0.45rem;
    gap: 0;
    min-width: 2.25rem;
    min-height: 2.25rem;
}
.viho-btn-disabled,
.viho-btn:disabled {
    opacity: 0.6;
    cursor: default;
    pointer-events: none;
}
.viho-btn-loading {
    cursor: progress;
}

/* ── Action tile ─────────────────────────────────────────────────────────────
   From the Create Offer interaction card, present in all four views. */
.viho-action-tile {
    display: flex;
    flex-direction: column;
    gap: var(--viho-space-sm);
    background: var(--viho-card-bg);
    border: 1px solid var(--viho-border-strong);
    border-radius: var(--viho-radius-lg);
    padding: 1rem 0.85rem var(--viho-space-lg);
    min-height: 0;
    text-decoration: none;
    color: inherit;
    transition: box-shadow var(--viho-transition), border-color var(--viho-transition), transform var(--viho-transition);
}
.viho-action-tile:focus-visible {
    outline: 2px solid var(--viho-primary);
    outline-offset: 2px;
}
.viho-action-tile-icon {
    font-size: var(--viho-font-xl);
    color: var(--viho-primary);
}
.viho-action-tile-label {
    font-weight: var(--viho-weight-semibold);
    font-size: var(--viho-font-md);
    color: var(--viho-text-strong);
}
.viho-action-tile-desc {
    font-size: var(--viho-font-2xs);
    color: var(--viho-label);
}
.viho-action-tile-active {
    border-color: var(--viho-primary);
    box-shadow: var(--viho-shadow-raised);
}
.viho-action-tile-disabled {
    opacity: 0.6;
    cursor: default;
    pointer-events: none;
}

/* ── Quick actions ───────────────────────────────────────────────────────────
   M5.3. The labelled band that holds the tiles above — Create Offer's interaction hub.

   AUTO-FIT RATHER THAN A COLUMN COUNT. The track count follows the width the host page gives the
   band, so the same markup is a single row on a wide page and a single column on a phone with no
   breakpoint list to keep in sync. Create Offer hard-codes 6/3/2/1 at three media queries; that
   is four numbers to maintain and they are wrong the moment the band is placed anywhere narrower
   than the page.

   `minmax(0, …)` on the low end, not `minmax(13rem, …)`: a grid track's automatic minimum is its
   content, so a long tile label would otherwise force the track wider than the container and push
   the band into horizontal overflow. The 13rem lives in the max half where it shapes the layout
   without being able to overflow it. */
.viho-quick-actions {
    margin-bottom: var(--viho-space-2xl);
}
.viho-quick-actions-label {
    display: flex;
    align-items: center;
    gap: var(--viho-space-xs);
    margin: 0 0 var(--viho-space-md);
    font-size: var(--viho-font-2xs);
    font-weight: var(--viho-weight-semibold);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--viho-label);
}
.viho-quick-actions-label-icon {
    color: var(--viho-primary);
}
.viho-quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(13rem, 100%), 1fr));
    gap: var(--viho-space-lg);
    align-items: stretch;
}
/* The tiles are grid items here, so they stretch to a shared row height and their own margin
   collapse rules stop applying. Nothing else about the tile changes. */
.viho-quick-actions-grid > .viho-action-tile {
    height: 100%;
    margin: 0;
}
/* Same defence the section nav needs, for the same reason: a host page that sets ::marker content
   directly still paints a marker, and a tile rendered inside a list on such a page would inherit
   it. The band is a section of divs and anchors, never a list, so this is belt-and-braces against
   a caller composing tiles into one. */
.viho-quick-actions-grid > li {
    display: block;
}

/* ── Stat item ───────────────────────────────────────────────────────────────
   From the Create Offer activity row, present in all four views. */
.viho-stat {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--viho-space-md);
    padding: 0.18rem 0;
    border-bottom: 1px solid var(--viho-border-subtle);
}
.viho-stat:last-child {
    border-bottom: none;
}
.viho-stat-label {
    font-size: var(--viho-font-2xs);
    color: var(--viho-label);
}
.viho-stat-value {
    font-weight: var(--viho-weight-bold);
    font-size: var(--viho-font-2xs);
    color: var(--viho-text-muted);
}
.viho-stat-support {
    display: block;
    font-size: var(--viho-font-3xs);
    color: var(--viho-text-muted);
    font-weight: 400;
}
.viho-stat-accent .viho-stat-value {
    color: var(--viho-accent, var(--viho-primary));
}
.viho-stat-icon {
    margin-right: var(--viho-space-2xs);
    color: var(--viho-label);
}

/* ── Empty state ─────────────────────────────────────────────────────────────
   The one primitive here that is a forward-looking contract rather than an
   extraction. Both products already say "No Record Found!", "No listings found"
   and "No bids yet on this listing." — but as bare strings with no shared
   markup, so there was no existing treatment to preserve. Geometry and colour
   are assembled from tokens only, and nothing renders it yet. */
.viho-empty-state {
    text-align: center;
    padding: var(--viho-space-2xl) var(--viho-space-xl);
    color: var(--viho-label);
}
.viho-empty-state-icon {
    font-size: var(--viho-font-xl);
    color: var(--viho-text-muted);
    margin-bottom: var(--viho-space-md);
}
.viho-empty-state-title {
    font-weight: var(--viho-weight-semibold);
    font-size: var(--viho-font-md);
    color: var(--viho-text-strong);
    margin: 0 0 var(--viho-space-2xs) 0;
}
.viho-empty-state-desc {
    font-size: var(--viho-font-sm);
    color: var(--viho-label);
    margin: 0;
}
.viho-empty-state-action {
    margin-top: var(--viho-space-md);
}

/* ── Hero ────────────────────────────────────────────────────────────────────
   The summary panel at the top of a listing detail page.

   WHERE THE GEOMETRY COMES FROM. The radius, the overlay shadow and the internal
   rule between identity and detail are the Create Offer hero summary panel's own
   values, promoted to tokens in M1 and reused here rather than re-measured:
   `--viho-radius-xl` is its 1rem, `--viho-shadow-overlay` its 0 4px 24px. What is
   deliberately NOT carried across is the dark inverse backing, which exists there
   to seat a photograph against. A panel with no media has nothing to seat, and a
   dark band above an empty column reads as a failed image load.

   THE FIGURE OUTWEIGHS THE TITLE. `--viho-font-2xl` on the figure against a
   smaller title is the one place this departs from the old text-block treatment.
   A listing's headline number is what a reader scans for first, and the previous
   hero set it a quarter-step below the title, which buried it.

   NO STATUS COLOUR IS DECIDED HERE. The pill is `.viho-badge`, painted by the
   variant the caller passes. Nothing in this block maps a label to a colour. */
.viho-hero {
    display: block;
    background: var(--viho-card-bg);
    border: 1px solid var(--viho-border);
    border-radius: var(--viho-radius-xl);
    box-shadow: var(--viho-shadow-overlay);
    margin-bottom: var(--viho-space-2xl);
    overflow: hidden;
}
.viho-hero-identity {
    padding: var(--viho-space-xl) var(--viho-space-2xl);
}
.viho-hero-eyebrow {
    font-size: var(--viho-font-2xs);
    font-weight: var(--viho-weight-bold);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--viho-label);
    margin: 0 0 var(--viho-space-2xs) 0;
}
.viho-hero-title {
    font-size: var(--viho-font-xl);
    font-weight: var(--viho-weight-bold);
    color: var(--viho-heading);
    line-height: 1.2;
    letter-spacing: -0.02em;
    margin: 0;
    overflow-wrap: anywhere;
}
.viho-hero-subtitle {
    font-size: var(--viho-font-md);
    color: var(--viho-text-soft);
    margin: var(--viho-space-2xs) 0 0 0;
    overflow-wrap: anywhere;
}
.viho-hero-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--viho-space-sm);
    margin-top: var(--viho-space-md);
}
.viho-hero-identifier {
    font-size: var(--viho-font-2xs);
    color: var(--viho-label);
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}

/* The internal rule, and the figure/actions split below it. */
.viho-hero-detail {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--viho-space-lg);
    padding: var(--viho-space-lg) var(--viho-space-2xl) var(--viho-space-xl);
    border-top: 1px solid var(--viho-border-subtle);
}
.viho-hero-detail-main {
    min-width: 0;
    flex: 1 1 20rem;
}
.viho-hero-figure {
    margin-bottom: var(--viho-space-md);
}
.viho-hero-figure-label {
    display: block;
    font-size: var(--viho-font-2xs);
    font-weight: var(--viho-weight-semibold);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--viho-label);
    margin-bottom: var(--viho-space-3xs);
}
.viho-hero-figure-value {
    font-size: var(--viho-font-2xl);
    font-weight: var(--viho-weight-bold);
    color: var(--viho-accent, var(--viho-heading));
    line-height: 1.1;
    letter-spacing: -0.03em;
    margin: 0;
    overflow-wrap: anywhere;
}
.viho-hero-facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
    gap: var(--viho-space-md) var(--viho-space-2xl);
    margin: 0;
}
.viho-hero-fact {
    min-width: 0;
}
.viho-hero-fact-label {
    font-size: var(--viho-font-3xs);
    font-weight: var(--viho-weight-semibold);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--viho-label);
    margin: 0;
}
.viho-hero-fact-value {
    font-size: var(--viho-font-md);
    color: var(--viho-text-strong);
    margin: var(--viho-space-3xs) 0 0 0;
    overflow-wrap: anywhere;
}
.viho-hero-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--viho-space-sm);
    flex: 0 1 auto;
}

/* Nothing may push the page sideways: a long unbroken address or an outsized
   figure is a content problem, not a licence to introduce a horizontal scrollbar. */
.viho-hero,
.viho-hero * {
    max-width: 100%;
}

/* Mobile. The panel is already one column above the page body, so the only work
   is easing the padding, stepping the type down, and letting the action row span
   the full width instead of crowding against the facts.

   `--viho-bp-md` is a reference value and cannot be used in this condition — the
   literal is required. See the Responsive constants note above. */
@media (max-width: 767.98px) {
    .viho-hero-identity {
        padding: var(--viho-space-lg) var(--viho-space-xl);
    }
    .viho-hero-detail {
        padding: var(--viho-space-md) var(--viho-space-xl) var(--viho-space-lg);
        gap: var(--viho-space-md);
    }
    .viho-hero-title {
        font-size: var(--viho-font-lg);
    }
    .viho-hero-figure-value {
        font-size: var(--viho-font-xl);
    }
    .viho-hero-facts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--viho-space-md) var(--viho-space-lg);
    }
    .viho-hero-actions {
        flex: 1 1 100%;
    }
    .viho-hero-actions > * {
        flex: 1 1 100%;
    }
}
</style>
