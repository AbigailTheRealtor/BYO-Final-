{{--
    The canonical selection-grid styling — ONE definition, every consumer.

    WHAT THIS STYLES
    ----------------
    Tenant Pays and Rent Includes on the canonical Landlord Leasing Terms partial
    (offer-landlord-tabs/commission-based/lease-terms.blade.php, around the
    `landlord-tenant-pays-checklist` and rent-includes blocks). Both are Alpine
    checklist grids: `$wire.entangle(...).defer` holds the selection, `@click`
    toggles a value, and `:class="{ 'utility-selected': isSelected(...) }"` is the
    ONLY thing that shows a card as chosen.

    WHY IT MOVED
    ------------
    These rules lived in a <style> block inside the landlord page wrappers, and
    the canonical partial that uses them contains no <style> block at all. MLS
    Quick Import renders that partial, so it emitted the full grid markup with
    none of the grid CSS: `.utility-checklist-grid` lost its `display: grid`, the
    cards lost their border, cursor and hover, and `.utility-selected` did
    nothing. The result read as a static vertical list of labels — the control
    was live the whole time and simply could not show it.

    Same architectural defect as the icons and the currency prefix: canonical
    markup travelling to a new surface without the environment it depends on. The
    fix is the same shape — the environment ships with the markup.

    WHAT IS DELIBERATELY NOT HERE
    -----------------------------
    The landlord Edit wrapper's <style> continued past these rules into
    `.status-text`, `.status-icon` and `.user-selected`, which have nothing to do
    with the checklist. Those are left where they are. Only the eight rules the
    two landlord wrappers held IDENTICALLY are shared, verbatim — this is not a
    restyle, and no value below has been changed.
--}}
@once
    @push('styles')
        <style>
    .utility-checklist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 8px;
    }
    .utility-checklist-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 10px 8px;
        border: 2px solid #dee2e6;
        border-radius: 10px;
        cursor: pointer;
        text-align: center;
        transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
        background: #fff;
        min-height: 72px;
        user-select: none;
    }
    .utility-checklist-card:hover {
        border-color: #adb5bd;
        background: #f8f9fa;
    }
    .utility-checklist-card.utility-selected {
        border-color: #0d6efd;
        background: #e8f0fe;
        box-shadow: 0 0 0 1px #0d6efd;
    }
    .utility-checklist-icon {
        font-size: 1.25rem;
        margin-bottom: 5px;
        color: #6c757d;
        transition: color 0.15s ease;
    }
    .utility-checklist-card.utility-selected .utility-checklist-icon {
        color: #0d6efd;
    }
    .utility-checklist-label {
        font-size: 0.72rem;
        font-weight: 500;
        line-height: 1.2;
        color: #495057;
    }
    .utility-checklist-card.utility-selected .utility-checklist-label {
        color: #0d6efd;
        font-weight: 600;
    }
        </style>
    @endpush
@endonce
