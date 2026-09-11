{{--
    Seller Sale Terms conditional behaviour — ONE definition, every consumer.

    WHAT THIS IS
    ------------
    The two parent questions on the Sale Terms tab — Special Sale Provision and
    Offered Financing/Currency — reveal follow-up sections. This file is the code
    that reveals them, and the code that carries the parent answer back to the
    Livewire component.

    It is moved here VERBATIM from the page wrappers that used to each carry an
    identical copy (offer-seller-listing, offer-seller-listing-edit,
    hire-seller-agent). The maps, the show/hide calls and the @this.set() sync are
    unchanged; only their address changed.

    WHY IT MOVED
    ------------
    The markup it operates lives in seller-terms.blade.php, which Create, Edit,
    Hire Seller Agent and MLS Quick Import all render. The behaviour did not: it
    sat in each page's own @push('scripts'), and MLS Quick Import has no such
    block. So Quick Import rendered every conditional section and could never open
    one — the sections emitted style="display: none" and nothing on the page was
    able to change it. The parent answer never reached the server either, because
    these two selects deliberately carry no wire:model (see below), so
    @this.set() was the only sync path and it was not running. A seller who chose
    "Assignment Contract" or "Assumable" on the import path was shown no follow-up
    and had the choice itself discarded on save.

    Behaviour now travels with the markup. A future surface that renders the
    canonical tab gets the conditionals automatically, which is the property that
    was missing.

    WHY THERE IS STILL NO wire:model ON THE TWO PARENTS
    ---------------------------------------------------
    Both selects sit inside wire:ignore and are driven by Select2. Binding them
    would change how Create, Edit and Hire Seller Agent submit — a far wider
    change than the defect requires. @this.set() is the mechanism those three
    screens have always used and it is left exactly as it was.

    WHY @push('scripts') AND NOT AN INLINE <script>
    -----------------------------------------------
    layouts.main yields content BEFORE it loads jQuery, so a script inline in the
    component body would run before $ exists. The scripts stack is emitted near
    the end of that layout — after @livewireScripts and after jQuery — which is
    the ordering this file depends on. Stated as an order rather than as line
    numbers on purpose: the numbers were cited here and were already stale one
    merge later, when an unrelated <head> include shifted the whole file by one.

    WHY THE QUICK IMPORT WRAPPER ALSO INCLUDES THIS FILE
    ----------------------------------------------------
    Livewire 2.12 does not execute <script> nodes it adds during a morph, and
    @push contributes nothing on an AJAX round trip — a stack is assembled while
    the full page is rendering. Quick Import reaches "Your Terms" as step 4 of a
    wizard, long after that page was delivered, so an include that fires only when
    the terms step renders would arrive too late to run at all. The wrapper
    therefore includes this file unconditionally, at initial page load. It is the
    same file with no per-page logic, and @once below makes the second include on
    the terms step a no-op.
--}}
@once
@push('scripts')
<script>
    (function () {
        'use strict';

        // Reachable from the page wrappers, which still call these from their own
        // Select2 re-initialisation and polling code. Those call sites are bare
        // identifiers, so they resolve through the scope chain to window.
        window.applyFinancingVisibility = function () {
            var data = ($('#offered_financing').val() || []);
            var financingMap = {
                'Assumable': '#seller-financing-assumable-section',
                'Cryptocurrency': '#seller-financing-crypto-section',
                'Exchange/Trade': '#seller-financing-exchange-section',
                'Lease Option': '#seller-financing-leaseoption-section',
                'Lease Purchase': '#seller-financing-leasepurchase-section',
                'Non-Fungible Token (NFT)': '#seller-financing-nft-section',
                'Seller Financing': '#seller-financing-sellerfinancing-section',
                'Other': '#seller-financing-other-section',
            };
            Object.keys(financingMap).forEach(function (option) {
                if (data.includes(option)) {
                    $(financingMap[option]).show();
                } else {
                    $(financingMap[option]).hide();
                }
            });
        };

        window.applyProvisionVisibility = function () {
            var data = ($('#sale_provision').val() || []);
            var provisionMap = {
                'Assignment Contract': '#seller-provision-assignment-section',
                'Other': '#seller-provision-other-section',
            };
            Object.keys(provisionMap).forEach(function (option) {
                if (data.includes(option)) {
                    $(provisionMap[option]).show();
                } else {
                    $(provisionMap[option]).hide();
                }
            });
        };

        // ── Select2 — ONE initializer for the tab's three Select2 controls ──────
        //
        // Special Sale Provision, Offered Financing/Currency and Exchange Item are
        // Select2 multi-selects inside wire:ignore. This is where they are
        // initialised for every entry path that renders this tab: Seller Create and
        // Seller Edit call it from initializeFullService(), and MLS Quick Import
        // receives it through the message.processed hook below — its "Your Terms"
        // step arrives by AJAX, three steps after the page loaded, and Quick Import
        // deliberately carries no script of its own.
        //
        // It configures nothing itself. window.initFullServiceSelect2Multiple
        // (public/js/select2-stable.js) is the form's single Select2 definition —
        // placeholder from data-placeholder, allowClear, width 100%, closeOnSelect
        // false — and its select2-hidden-accessible guard makes every call after
        // the first a no-op, so no control is ever initialised twice.
        //
        // BINDINGS. The two parents bind nothing here: their change handling is the
        // delegated pair below, exactly one per page. Exchange Item has no delegated
        // handler — every other surface binds it itself — so it is bound here only
        // when BOTH hold: the shared 'exchange-change-bound' flag is unset (the flag
        // Create, Hire Seller Agent and the tenant screens already use), AND either
        // this call initialised the control or the caller owns the binding
        // ({ownsBinding: true}, which Create and Edit pass now that their own copies
        // are gone). A page that initialised Exchange Item itself therefore never
        // gains a second handler from here.
        window.initSellerTermsSelect2 = function (options) {
            if (typeof window.initFullServiceSelect2Multiple !== 'function') {
                return;
            }
            var ownsBinding = !!(options && options.ownsBinding);

            ['#sale_provision', '#offered_financing'].forEach(function (selector) {
                var $el = $(selector);
                if ($el.length && !$el.hasClass('select2-hidden-accessible')) {
                    window.initFullServiceSelect2Multiple($el);
                }
            });

            var $ex = $('#exchange_item');
            if ($ex.length) {
                var initialisedHere = !$ex.hasClass('select2-hidden-accessible');
                if (initialisedHere) {
                    window.initFullServiceSelect2Multiple($ex);
                }

                // The markup renders the stored answer as `selected` options; this
                // restores it only when a widget comes up empty.
                if (($ex.val() || []).length === 0) {
                    var saved = [];
                    try { saved = JSON.parse($ex.attr('data-selected') || '[]'); } catch (e) {}
                    if (!saved.length) { saved = @this.get('exchange_item') || []; }
                    if (saved.length > 0) {
                        $ex.val(saved).trigger('change.select2');
                    }
                }

                if ((initialisedHere || ownsBinding) && !$ex.data('exchange-change-bound')) {
                    $ex.on('change', function () {
                        var selectedValues = $(this).val() || [];
                        @this.set('exchange_item', selectedValues, false);
                        // Keep data-selected in step so a later re-initialisation
                        // restores the current answer, never a stale one.
                        $ex.attr('data-selected', JSON.stringify(selectedValues));
                        $('#other_exchange_item_wrapper').toggle(selectedValues.includes('Other'));
                    });
                    $ex.data('exchange-change-bound', true);
                }

                $('#other_exchange_item_wrapper').toggle(($ex.val() || []).includes('Other'));
            }

            if ($('#sale_provision').length) { window.applyProvisionVisibility(); }
            if ($('#offered_financing').length) { window.applyFinancingVisibility(); }
        };

        // Bound once per page. The guard matters because more than one surface can
        // include this file in a single render, and because a second binding would
        // mean two @this.set() round trips for one change.
        if (window.__sellerTermsConditionalsBound) {
            return;
        }
        window.__sellerTermsConditionalsBound = true;

        // Document-level event delegation for sale provision and financing visibility.
        // Using $(document).on() means these handlers survive any DOM replacement,
        // morphdom patching, or Select2 re-initialization — they are bound once and
        // never need to be re-registered. It is also what lets one binding serve a
        // wizard step that did not exist when the page loaded.
        // @this.set() syncs the value into the Livewire component so that every
        // subsequent server-side re-render keeps sections visible (mirrors Appliances pattern).
        $(document).on('change', '#sale_provision', function () {
            var selectedValues = $(this).val() || [];
            @this.set('sale_provision', selectedValues, false);
            window.applyProvisionVisibility();
        });
        $(document).on('change', '#offered_financing', function () {
            var selectedValues = $(this).val() || [];
            @this.set('offered_financing', selectedValues, false);
            window.applyFinancingVisibility();
        });

        // A section's open/closed state is rendered by the server from the stored
        // answer, so a step that arrives mid-wizard is already correct. This re-asserts
        // it after a Livewire update for the pages whose Select2 is re-initialised in
        // place, where the widget can repaint before the morph settles.
        function reapply() {
            if ($('#sale_provision').length) { window.applyProvisionVisibility(); }
            if ($('#offered_financing').length) { window.applyFinancingVisibility(); }
        }

        document.addEventListener('livewire:load', function () {
            reapply();
            if (window.Livewire && Livewire.hook) {
                Livewire.hook('message.processed', function () {
                    setTimeout(reapply, 250);
                    // Initialise whatever this update brought into the page that
                    // nobody has initialised yet — Quick Import's terms step. 0 ms,
                    // so it runs after every synchronous page hook for the same
                    // update: a page that initialises its own controls in its hook
                    // always goes first and this finds them done. Deliberately NOT
                    // on livewire:load, which fires before the pages' own
                    // DOMContentLoaded initialisation and would pre-empt it.
                    setTimeout(function () { window.initSellerTermsSelect2(); }, 0);
                });
            }
        });
    })();
</script>
@endpush
@endonce
