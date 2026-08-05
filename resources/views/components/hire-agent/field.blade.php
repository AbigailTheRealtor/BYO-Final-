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

    @param string $label          WITHOUT a trailing colon; the legacy branch adds one
    @param mixed  $value          omitted entirely when the display helper counts it as absent
    @param string $width          COMPLETE legacy class list for the row div, order included
    @param bool   $redesign       resolved flag state, passed by the caller — never read from config
    @param string $span           redesign cell width: 'half' (default, two per line) | 'full'
    @param bool   $bareSlot       legacy branch emits the slot unwrapped — for badge/pill runs
    @param bool   $badges         redesign stacks label over a full-width pill run; implies full span
    @param bool   $legacyRow      legacy branch wraps the row in its own div.row
    @param string $legacyRowStyle inline style for that wrapper, verbatim
--}}
@props([
    'label',
    'value'          => null,
    'width'          => 'col-md-12 col-12 pt-2 fw-bold',
    'redesign'       => false,
    'span'           => 'half',
    'bareSlot'       => false,
    'badges'         => false,
    'legacyRow'      => false,
    'legacyRowStyle' => 'flex-wrap: wrap;',
])

@php
    $hlaFieldSlot = trim($slot ?? '');
    $hlaFieldHasSlot = $hlaFieldSlot !== '';

    /*
     | An array reaching a row is not an error — several questionnaire answers are multi-select —
     | but echoing one is. The reference joins the same shape with ", " (its $listRow), so the two
     | pages agree on how a list reads. Filtering first is what makes an all-empty array count as
     | absent rather than rendering as a bare separator.
     */
    $hlaFieldValue = is_array($value)
        ? implode(', ', array_filter($value, fn ($v) => \App\Helpers\ListingDisplayHelper::hasValue($v)))
        : $value;

    $hlaFieldHasValue = $hlaFieldHasSlot || \App\Helpers\ListingDisplayHelper::hasValue($hlaFieldValue);

    /*
     | A pill run always spans the card. Deriving it here rather than asking every badge call site
     | to pass span="full" as well keeps the two from being set inconsistently — a badge row that
     | was half-width would reintroduce the corridor this mode exists to close, just narrower.
     */
    $hlaFieldRedesignWidth = ($badges || $span === 'full') ? 'col-12' : 'col-md-6 col-12';

    if ($badges) {
        $hlaFieldRedesignWidth .= ' hla-field-badges';
    }
@endphp

@if ($hlaFieldHasValue)
    @if ($redesign)
        <div class="{{ $hlaFieldRedesignWidth }} hla-field">
            <x-viho.kv :label="$label" layout="split">{{ $hlaFieldHasSlot ? $slot : $hlaFieldValue }}</x-viho.kv>
        </div>
    @elseif ($bareSlot)
    <div class="{{ $width }}">{{ $label }}:
        {{ $slot }}
    </div>
    @elseif ($legacyRow)
    <div class="row" style="{{ $legacyRowStyle }}">
        <div class="{{ $width }}">{{ $label }}:
            <span class="removeBold">{{ $hlaFieldHasSlot ? $slot : $hlaFieldValue }}</span>
        </div>
    </div>
    @else
    <div class="{{ $width }}">{{ $label }}:
        <span class="removeBold">{{ $hlaFieldHasSlot ? $slot : $hlaFieldValue }}</span>
    </div>
    @endif
@endif
