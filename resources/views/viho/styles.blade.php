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
}
</style>
