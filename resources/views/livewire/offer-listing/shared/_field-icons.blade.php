{{--
    The canonical field-icon renderer — ONE definition, every consumer.

    WHAT THIS IS
    ------------
    The canonical terms partials do not author an `<i>` element for their field
    icons. They carry the icon as a `data-icon` attribute on a `.has-icon` input
    inside an `.input-cover` wrapper — 85 of them in
    offer-seller-tabs/…/seller-terms.blade.php and 81 in
    offer-landlord-tabs/…/lease-terms.blade.php. This function is what turns that
    attribute into the visible icon at runtime.

    WHY IT MOVED
    ------------
    It was defined NINETEEN times, once per page wrapper, and MLS Quick Import —
    which renders those very same canonical partials — defined it zero times. So
    every icon on the Quick Import terms step was present in the markup and
    invisible on the page. Nothing was missing from the fields; the renderer that
    materialises them never ran.

    That is the third instance of one architectural defect. PR #139 found it in
    BEHAVIOUR (the conditional reveal logic sat in each page's @push('scripts')),
    the currency fix found it in PRESENTATION (the styling was scoped to each
    page's #wizard-form-container), and this is the same shape again: the
    canonical markup travels to a new surface and the environment it depends on
    does not travel with it. Behaviour now ships in _seller-terms-behaviour /
    _lease-terms-behaviour; presentation ships by being inside the container;
    icons ship here.

    WHO OWNS WHICH PASS
    -------------------
    This partial owns the icon LIFECYCLE that every consumer needs identically:
    the page-load passes (immediate, rAF, then 100 / 300 / 700 / 1500 / 2500 ms)
    and the immediate + 0 ms passes after every Livewire update. The four
    Seller/Landlord Create/Edit wrappers used to repeat those exact passes; their
    copies are removed.

    What the wrappers still call is page orchestration this partial cannot see:
    a tab switch (`shown.bs.tab`), a draft load, an MLS apply, the end of their
    own Select2 re-initialisation, Seller Create's post-`initializeFullService()`
    schedule and the Landlord 200 ms settle pass. Those stay where they are, and
    the function is published on `window` so every one of them keeps working.
    This partial must therefore be included BEFORE those scripts run.

    THE GUARD IS THE LANDLORD'S, AND THAT CHOICE IS LOAD-BEARING
    ------------------------------------------------------------
    The four wrappers did not agree. Seller skipped only when IT had already
    rendered an icon (`.data-icon-rendered`); Landlord skipped when ANY
    `.input-icon` was present. The difference is reachable: landlord
    property-preferences.blade.php has 81 `.input-cover` blocks carrying BOTH a
    hand-authored `<i class="input-icon">` AND a `data-icon` input, so the
    landlord guard is what stops a second icon being stacked on all 81. The
    seller tabs have ZERO such blocks, which makes the two guards equivalent
    there.

    So this takes the landlord guard (the superset, and the only one that is
    correct on both) and keeps the seller's `data-icon-rendered` marker. For
    Landlord the behaviour is unchanged. For Seller the guard becomes stricter in
    a case that does not occur. Nothing reads the marker class — no CSS rule
    targets it anywhere — so it is inert apart from being a useful test hook.

    DO NOT invent icons here. This file chooses nothing: the icon class comes
    from the field's own `data-icon`, authored once in the canonical partial and
    identical for Create, Edit and Quick Import by construction.
--}}
@once
    @push('scripts')
        <script>
            (function () {
                if (typeof window.addIconsToInputs === 'function') {
                    return;
                }

                window.addIconsToInputs = function addIconsToInputs() {
                    document.querySelectorAll('.has-icon[data-icon]').forEach(input => {
                        const iconClass = input.getAttribute('data-icon');
                        if (!iconClass) return;
                        const wrapper = input.closest('.input-cover');
                        if (!wrapper) return;
                        if (input.type === 'file') return;
                        if (wrapper.querySelector('.input-icon')) return;
                        const icon = document.createElement('i');
                        icon.className = `input-icon ${iconClass} data-icon-rendered`;
                        wrapper.insertBefore(icon, wrapper.firstChild);
                    });
                };

                // Re-run after every Livewire render. morphdom replaces the input
                // nodes, taking the injected <i> with them, so a conditional
                // section revealed on the terms step comes back iconless without
                // this. The wrappers each had their own copy of this hook; theirs
                // still run and are harmless, because the function is idempotent.
                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                    window.Livewire.hook('message.processed', () => {
                        window.addIconsToInputs();
                        setTimeout(window.addIconsToInputs, 0);
                    });
                }

                // The initial passes. Select2 wraps a select AFTER first paint, and
                // Livewire hydration on a fresh load can land later still, so the
                // icon pass is repeated across the same window the Seller wrapper
                // established. Idempotent, so extra passes cost a querySelectorAll.
                function initialPasses() {
                    window.addIconsToInputs();
                    requestAnimationFrame(window.addIconsToInputs);
                    [100, 300, 700, 1500, 2500].forEach(
                        ms => setTimeout(window.addIconsToInputs, ms)
                    );
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initialPasses);
                } else {
                    initialPasses();
                }
            }());
        </script>
    @endpush
@endonce
