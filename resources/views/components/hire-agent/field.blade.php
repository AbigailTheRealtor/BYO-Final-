{{--
    Hire Agent Listing Detail Framework — one label/value row.

    The four detail views contain 340 rows of this exact shape:

        <div class="col-md-12 col-12 pt-2 fw-bold">Label:
            <span class="removeBold">value</span>
        </div>

    The markup and classes are reproduced verbatim so that adopting the component changes nothing
    about how a row renders — the existing shared CSS already targets
    `.col-md-12.col-12.pt-2.fw-bold` and `.removeBold`, and both still apply.

    The row renders nothing at all when the value is absent, matching the @if (…!= null) guard
    that wraps essentially every one of those 340 rows today.

    ── M7.4 — THE SECOND BRANCH ────────────────────────────────────────────────────────────────

    WHY THE ADAPTER LIVES HERE AND NOT IN THE PRIMITIVE. x-viho.kv already renders the 5/7 grid the
    reference page uses, and it already declines to render a row whose value is absent. What it must
    NOT learn is what "absent" means to this product: VihoPrimitiveGuardControlsTest rejects a VIHO
    component that references a product or carries business logic, and the emptiness rule this page
    needs — arrays, whitespace, the literal string "null", placeholder text — lives in a product
    helper. So the rule stays on this side of the boundary and the primitive stays ignorant. That is
    also why the guard below runs BEFORE the primitive is reached: by the time x-viho.kv sees a
    value, this component has already decided the row is worth rendering.

    THE TWO BRANCHES ARE NOT THE SAME SHAPE, DELIBERATELY. Legacy emits label and value inline on
    one full-width line. The redesign emits a half-width cell containing a 5/7 label/value split, so
    two fields sit side by side and wrap left-to-right. The reference page reaches a similar picture
    by a different route — it splits each section into two col-md-6 halves and fills them
    independently — and that route was rejected here for one reason: it reorders the questionnaire.
    Filling column A then column B means a reader meets the fields in an order the Hire Agent flow
    never asked them in. Flowing left-to-right across a wrapping row preserves the asked order
    exactly, which M7.4 requires and the reference does not have to care about.

    ON THE COLON. Legacy carries it in the label text and must keep carrying it — the flag-off
    headings and row text are asserted verbatim. The redesign drops it, because a label in a
    5/7 grid is a column header rather than a sentence fragment, and the reference has none. The
    caller passes the label WITHOUT a colon and the legacy branch appends one, so the two branches
    cannot drift and no call site has to spell the punctuation twice.

    ON THE WHITESPACE AFTER THE LABEL. The newline between the label and the span is load-bearing.
    DOM equivalence compares `preg_replace('/\s+/', ' ', textContent)`, so "Label:" immediately
    followed by the span would normalise to "Label:value" where the pre-change render gives
    "Label: value". It reads as a formatting nicety and is actually the assertion.

    ON bareSlot. A dozen rows on this page do not wrap their value in `.removeBold` at all: they
    emit a run of `<span class="removeBold badge bg-secondary">` pills straight after the label, one
    per selected option. Wrapping those in this component's own `.removeBold` span would nest a
    removeBold inside a removeBold — harmless to look at, and still a different element tree than
    the one flag-off is supposed to reproduce exactly. `bareSlot` emits the slot with no wrapper so
    those rows keep the shape they have. It affects the LEGACY branch only; the redesign always
    places the value in `.viho-kv-value`, because that span is what the 5/7 grid positions.

    ON $width BEING THE WHOLE CLASS LIST RATHER THAN JUST THE COLUMN. Leasing Terms writes its rows
    as `col-12 fw-bold pt-2` where every other section writes `col-md-12 col-12 pt-2 fw-bold` — the
    same four classes in a different order. Composing the string here as "{$width} pt-2 fw-bold"
    could reproduce one spelling or the other, never both, and class ORDER is part of the attribute
    that flag-off is required not to change. So the caller supplies the complete list and this
    component concatenates nothing. The default is the spelling used by every section that does not
    pass one.

    ON legacyRow. Leasing Terms also wraps EACH row in its own `div.row`, where the other sections
    open one row per section and let the cells wrap inside it. The redesign wants the section-level
    row — that is what lets two fields share a line — but flag-off must keep the per-row wrapper.
    The wrapper therefore belongs to the branch, not to the call site: `legacyRow` emits it in the
    legacy branch only, and the caller stays one line either way. $legacyRowStyle carries the inline
    style verbatim for the same reason $width does — one of these rows is indented by 1rem and the
    indent is meaningful.

    ON badges, AND WHY A PILL RUN IS NOT A VALUE. M7.4 refinement. A multi-select answer renders as
    a run of pills rather than a sentence, and the 5/7 grid was built for a sentence: it reserves
    41.6% of the row for the label and starts the value at that column. A three-word label followed
    by six pills therefore opened a corridor of empty space across the middle of the card, and the
    longer the pill run the worse it read — the pills wrapped inside 58% of the row while the left
    half stayed blank.

    `badges` stacks the pair instead: the label takes its own line and the run begins at the card's
    left edge, wrapping across the full width. THE PILLS THEMSELVES ARE UNTOUCHED — same
    `badge bg-secondary` markup, same styling, emitted by the same caller loop. This changes where
    the run starts, nothing about what a pill looks like.

    IT IS STILL x-viho.kv. The stacking is done by a product-scoped class that widens the two halves
    of the split to 100% each, not by a second layout inside the primitive: a pill run is a Hire
    Agent presentation concern, and teaching VIHO about it would put a product's idea of a value in
    a component both products share. The primitive keeps emitting `viho-kv-label` and
    `viho-kv-value`, and the stylesheet that already scopes this page overrides their widths.

    LEGACY IS UNAFFECTED, as with every other flag on this component. `bareSlot` remains the legacy
    spelling for these same rows and the two are passed together at every badge call site.

    ── M7.6 — listValue, AND WHY A PILL RUN IS NOT ALWAYS THE ANSWER ───────────────────────────

    THE REFERENCE DOES NOT PILL ITS DATA. Create Offer renders every multi-select answer through
    its `$listRow` closure, which joins the items with ", " and hands the result to the same
    label/value row a single-valued field uses. The only `badge bg-secondary` on that page is
    "Bidding Closed" — a STATUS. So the reference's vocabulary is: a pill means state, plain text
    means data. This page had drifted to using pills for both, which spends the reader's strongest
    visual signal on "Dishwasher, Dryer, Microwave".

    `listValue` moves a row back onto the reference's side of that line WITHOUT disturbing the
    legacy branch, which is the constraint that shapes the whole design. Flag-off output is
    asserted verbatim, and flag-off renders pills; so the pills cannot be deleted from the call
    site. The caller therefore passes BOTH — the pill run in the slot, which only the legacy
    branch reads, and the underlying array as `listValue`, which only the redesign branch reads.
    One row, two renderings, and neither branch has to know what the other does.

    PRECEDENCE IS LISTVALUE, THEN SLOT, THEN VALUE, and only in the redesign branch. A call site
    that passes no `listValue` is completely unaffected, which is what keeps this from being a
    change to all 120 rows instead of the nine it is meant for.

    THE THREE ROWS THAT KEEP THEIR PILLS — Acceptable Cities, Counties, Zip Code — are the case
    the reference does not have to answer. They are unbounded enumerations of short tokens, and a
    forty-item comma list is genuinely worse to scan than forty chips. They keep `badges` and pass
    no `listValue`, so they keep rendering exactly as they do today.

    ── S2 — legacyInverted, AND THE ONE ROW SHAPE THIS COMPONENT COULD NOT EMIT ─────────────────

    SELLER WRITES EIGHT ROWS INSIDE OUT. Every shape above puts the bold on the row div and the
    unbolded value in a span; these eight do the opposite — the row div carries `removeBold` and the
    LABEL is wrapped in a `fw-bold` span, with the value left bare beside it:

        <div class="col-md-12 col-12 pt-2 removeBold">
            <span class="fw-bold">Assignment Contract:</span>
            {{ value }}
        </div>

    Buyer, landlord and tenant have ZERO rows of this shape; it is Seller's alone, and it is spread
    across Sale Terms (seven rows, including Desired Sale Price) and Seller Info (one).

    IT RENDERS IDENTICALLY TO THE SHAPE ABOVE IT — bold label, unbolded value, same text, same
    order. The two are the same row written two ways, which is exactly why this is a legacy-branch
    concern and nothing more: with the redesign ON both shapes reach the same x-viho.kv call and
    become the same cell, so there is nothing to preserve on that side and nothing to choose. The
    convergence is asserted rather than assumed — see the inverted-row test.

    SO WHY NOT NORMALISE THE EIGHT TO THE ORDINARY SHAPE AND SKIP THIS FLAG? Because flag-off is
    asserted against the pre-change render, and swapping which element carries which class is a
    changed attribute on two elements per row even though nothing moves and nothing looks different.
    The rule this component has followed since M7.4 is that the legacy branch reproduces what was
    there, verbatim, and the redesign branch is where the page improves. Normalising these rows is a
    defensible change to make; it is not a change to make silently inside a conversion commit.

    THE COLON IS THE CALLER'S TO OMIT, as everywhere else. It sits inside the label span in the
    original markup, so a caller migrating one of these rows must pass "Assignment Contract" and let
    this branch add the colon — passing it with one would render "Assignment Contract::" in legacy
    and put a stray colon in the redesign's `viho-kv-label`, which
    test_a_grid_label_carries_no_trailing_colon exists to catch.

    IT CARRIES ITS OWN DEFAULT WIDTH, and that is the same reasoning `badges` uses for implying a
    full span: all eight rows write `col-md-12 col-12 pt-2 removeBold`, so asking each call site to
    repeat the string is eight chances to typo a class list that flag-off is required not to change.
    An explicit `width` still overrides it, so the escape hatch the other shapes rely on is intact.

    NEW BEHAVIOUR ONLY WHEN EXPLICITLY ASKED FOR. The prop defaults false and no other role passes
    it, so buyer, landlord and tenant reach byte-identical output by construction — asserted at
    source, because "no call site passes this" is a claim about the views rather than about a render.

    @param string $label          WITHOUT a trailing colon; the legacy branch adds one
    @param mixed  $value          omitted entirely when the display helper counts it as absent
    @param mixed  $listValue      REDESIGN ONLY — array/string rendered as ", "-joined text,
                                  overriding the slot so legacy can keep its pill run untouched
    @param string $width          COMPLETE legacy class list for the row div, order included. Null
                                  takes the default for the shape — see $hlaFieldWidth below.
    @param bool   $redesign       resolved flag state, passed by the caller — never read from config
    @param string $span           redesign cell width: 'half' (default, two per line at lg and
                                  above; full width below it) | 'full'
    @param bool   $bareSlot       legacy branch emits the slot unwrapped — for badge/pill runs
    @param bool   $badges         redesign stacks label over a full-width pill run; implies full span
    @param bool   $legacyRow      legacy branch wraps the row in its own div.row
    @param string $legacyRowStyle inline style for that wrapper, verbatim
    @param bool   $legacyInverted LEGACY ONLY — bold label in a span, bare value; implies the
                                  inverted row class list. Seller's eight rows; see above.
--}}
@props([
    'label',
    'value'          => null,
    'listValue'      => null,
    'width'          => null,
    'redesign'       => false,
    'span'           => 'half',
    'bareSlot'       => false,
    'badges'         => false,
    'legacyRow'      => false,
    'legacyRowStyle' => 'flex-wrap: wrap;',
    'legacyInverted' => false,
])

@php
    $hlaFieldSlot = trim($slot ?? '');
    $hlaFieldHasSlot = $hlaFieldSlot !== '';

    /*
     | S2. The legacy row class list, resolved once.
     |
     | `width` used to default to the ordinary spelling directly in the props list. It defaults to
     | null now so that the DEFAULT can depend on the shape — an inverted row's div carries
     | `removeBold` where an ordinary one carries `fw-bold`, and every one of the eight inverted
     | rows writes the same string. Deriving it is the same trade `badges` makes when it implies a
     | full span: one flag at the call site instead of two values that can be set inconsistently.
     |
     | NOTHING CHANGES FOR AN EXISTING CALLER. A call site that passes a width still gets exactly
     | that string, and one that passes none still gets exactly the string that was the prop default
     | before — no caller passes `width=""` or a bound null (verified across the three migrated
     | views), so there is no third case for `??` to resolve differently than the prop default did.
     */
    $hlaFieldWidth = $width ?? ($legacyInverted
        ? 'col-md-12 col-12 pt-2 removeBold'
        : 'col-md-12 col-12 pt-2 fw-bold');

    /*
     | An array reaching a row is not an error — several questionnaire answers are multi-select —
     | but echoing one is. The reference joins the same shape with ", " (its $listRow), so the two
     | pages agree on how a list reads. Filtering first is what makes an all-empty array count as
     | absent rather than rendering as a bare separator.
     */
    $hlaFieldJoin = fn ($v) => is_array($v)
        ? implode(', ', array_map(
            fn ($item) => trim((string) $item),
            array_filter($v, fn ($item) => \App\Helpers\ListingDisplayHelper::hasValue($item))
        ))
        : $v;

    $hlaFieldValue = $hlaFieldJoin($value);

    /*
     | M7.6. Joined with the same separator and filtered by the same rule as $value, because a
     | pill run converted to text must read exactly like a field that was always text — the point
     | of the conversion is that the reader cannot tell which rows used to be which. Trimming is
     | added here rather than above because these arrays come straight from questionnaire answers
     | (`other_appliances` and friends carry the user's own whitespace), where $value's callers
     | have almost always passed through a helper already.
     */
    $hlaFieldListValue = $hlaFieldJoin($listValue);
    $hlaFieldHasListValue = \App\Helpers\ListingDisplayHelper::hasValue($hlaFieldListValue);

    $hlaFieldHasValue = $hlaFieldHasSlot
        || $hlaFieldHasListValue
        || \App\Helpers\ListingDisplayHelper::hasValue($hlaFieldValue);

    /*
     | A pill run always spans the card. Deriving it here rather than asking every badge call site
     | to pass span="full" as well keeps the two from being set inconsistently — a badge row that
     | was half-width would reintroduce the corridor this mode exists to close, just narrower.
     |
     | ── M7.8 — TWO-UP STARTS AT lg, NOT md, AND THE NUMBER IS WHY ──────────────────────────────
     |
     | This read `col-md-6`, so two fields shared a line from 768px up. Measured: at 768 the card's
     | field grid is 408px, a half cell is 204px, and `.viho-kv-split` reserves 41.666% of it for
     | the label — an 85px column. The longest labels on this page run to 47 characters, which is
     | roughly six wrapped lines against a two-line value. The grid was technically two-up and
     | practically unreadable for the whole tablet band.
     |
     | M7.7 already recorded this number from the other direction: it declined to apply its
     | full-span alignment below 992 because "at 768px the card is ~408px, so a matched label
     | column would be ~85px and would wrap". That was correct about the full-span rows and left
     | the half-span rows sitting at exactly the width it rejected.
     |
     | `col-lg-6` moves the split to 992px, where a half cell is 312px and the label column is
     | 130px. Below that every cell is `col-12` and each field gets the whole card — 170px of
     | label at 768 — which is also the width the reference page gives its own rows, since a
     | full-width row and Create Offer's `col-md-5`/`col-md-7` resolve to the same 41.666%.
     |
     | MOBILE IS UNTOUCHED. `col-12` was always the sub-md spelling and still is; below 767.98px
     | the primitive stacks label over value regardless of the cell width.
     |
     | THE STYLESHEET'S FULL-SPAN RULE EXCLUDES THIS CLASS BY NAME and must be changed with it —
     | a half cell carries `col-12` too, so an exclusion still naming the old class would stop
     | matching and the rule would re-halve these labels. See the note on that media query.
     */
    $hlaFieldRedesignWidth = ($badges || $span === 'full') ? 'col-12' : 'col-lg-6 col-12';

    if ($badges) {
        $hlaFieldRedesignWidth .= ' hla-field-badges';
    }
@endphp

@if ($hlaFieldHasValue)
    @if ($redesign)
        <div class="{{ $hlaFieldRedesignWidth }} hla-field">
            <x-viho.kv :label="$label" layout="split">{{ $hlaFieldHasListValue ? $hlaFieldListValue : ($hlaFieldHasSlot ? $slot : $hlaFieldValue) }}</x-viho.kv>
        </div>
    @elseif ($legacyInverted)
    {{-- S2. First in the chain because it is mutually exclusive with the three below rather than
         composable with them: an inverted row is a whole shape, not a modifier. The value is
         emitted bare — no wrapper span — because the row div already carries `removeBold` and
         nesting one inside the other would be a different element tree than the one flag-off
         reproduces. --}}
    <div class="{{ $hlaFieldWidth }}">
        <span class="fw-bold">{{ $label }}:</span>
        {{ $hlaFieldHasSlot ? $slot : $hlaFieldValue }}
    </div>
    @elseif ($bareSlot)
    <div class="{{ $hlaFieldWidth }}">{{ $label }}:
        {{ $slot }}
    </div>
    @elseif ($legacyRow)
    <div class="row" style="{{ $legacyRowStyle }}">
        <div class="{{ $hlaFieldWidth }}">{{ $label }}:
            <span class="removeBold">{{ $hlaFieldHasSlot ? $slot : $hlaFieldValue }}</span>
        </div>
    </div>
    @else
    <div class="{{ $hlaFieldWidth }}">{{ $label }}:
        <span class="removeBold">{{ $hlaFieldHasSlot ? $slot : $hlaFieldValue }}</span>
    </div>
    @endif
@endif
