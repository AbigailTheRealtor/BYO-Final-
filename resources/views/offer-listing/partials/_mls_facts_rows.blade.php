{{--
    MLS rows, in the listing page's OWN row markup.

    ONE ROW TEMPLATE, EVERY SURFACE.
    The seller listing page, the landlord listing page and the quick-import
    review screen all reach these rows through this file, and the markup it
    emits is byte-for-byte the markup the `$row()` closure in each listing view
    emits for a hand-written field. That is the whole of the "MLS data must look
    like the rest of the site" requirement at the view layer: there is no second
    row style to keep in step, because there is no second row template.

    EVERY ROW HERE IS POPULATED, BY CONSTRUCTION.
    Nothing in this file tests a value for emptiness and it must stay that way —
    MlsSupplementalDetails drops empty values, empty rows and empty sections at
    build time and again at read time. A blank-row guard here would be a second
    implementation of that rule and the two would eventually disagree.

    Expects:
      $sections       list<array{title,rows}>  sections to render
      $headings       bool    render an <h6> per section (default true)
      $headingPrefix  string  prepended to each heading (default 'MLS ')
      $leadingRule    bool    emit an <hr> before the first section (default false)
      $labelStyle     string  inline style for the label cell
      $valueStyle     string  inline style for the value cell

    The two style arguments exist because the seller and landlord pages already
    size their own rows differently — the landlord `$row()` closure carries
    font-size declarations the seller's does not. Passing the host page's own
    styling in is what keeps these rows indistinguishable from the rows above
    them ON THAT PAGE, which is the requirement; a single hard-coded style would
    make the MLS rows the odd ones out on exactly one of the two.
--}}
@php
    $mlsRowSections      = is_array($sections ?? null) ? $sections : [];
    $mlsRowHeadings      = $headings      ?? true;
    $mlsRowHeadingPrefix = $headingPrefix ?? 'MLS ';
    $mlsRowLeadingRule   = $leadingRule   ?? false;
    $mlsRowLabelStyle    = $labelStyle    ?? '';
    $mlsRowValueStyle    = $valueStyle    ?? 'overflow-wrap:break-word;word-break:break-word;';
@endphp

@if(count($mlsRowSections))
    @if($mlsRowLeadingRule)<hr>@endif

    @foreach($mlsRowSections as $mlsRowSection)
        @if($mlsRowHeadings)
            <h6 class="fw-semibold mt-3 mb-2" style="letter-spacing:0">{{ $mlsRowHeadingPrefix }}{{ $mlsRowSection['title'] }}</h6>
        @endif
        <div class="row">
            @foreach($mlsRowSection['rows'] as $mlsRow)
                @php
                    // One href per row at most. Both are validated in
                    // MlsSupplementalDetails on the way out of storage — anything
                    // that is not an absolute https URL or a real mailto arrives
                    // as null and the value renders as plain text.
                    $mlsRowHref = $mlsRow['url'] ?? $mlsRow['link'] ?? null;
                @endphp
                <div class="col-md-6">
                    <div class="row mb-2">
                        <div class="col-md-5 text-muted fw-semibold" style="{{ $mlsRowLabelStyle }}">{{ $mlsRow['label'] }}</div>
                        <div class="col-md-7" style="{{ $mlsRowValueStyle }}">
                            @if($mlsRowHref)
                                <a href="{{ $mlsRowHref }}"
                                   @if(! \Illuminate\Support\Str::startsWith($mlsRowHref, 'mailto:'))
                                       target="_blank" rel="noopener noreferrer nofollow"
                                   @endif
                                >{{ $mlsRow['value'] }}</a>
                            @else
                                {{ $mlsRow['value'] }}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endif
