{{--
    Landlord Leasing Terms conditional behaviour — ONE definition, every consumer.

    WHAT THIS COVERS, AND WHAT IT DELIBERATELY DOES NOT
    ---------------------------------------------------
    Only the three controls whose reveal was NOT self-contained in the canonical
    tab, and which therefore worked on the manual screens and nowhere else:

      * Desired Lease Term  → Other   (.lease_term_options → #other_desired_lease_length_wrapper)
      * Owner Pays          → Other   (#owner_pays         → #other_owner_pays_wrapper)   [commercial]
      * Terms of Lease      → Other   (#terms_of_lease     → #otherLeaseContainer)        [commercial]

    All three are Select2 multi-selects sitting inside wire:ignore with no
    wire:model, so nothing carried their value back to the component and nothing
    opened their follow-up. Their wrappers are painted from server state
    ($is_update_lease_term_option_visible, $is_other_owner_pays_visible), and on a
    surface that never set those the wrappers stayed display:none forever.

    Everything else on the tab is left completely alone, because it already works
    on every surface:
      * tenant_pays / rent_includes  → Other   — Alpine x-show, which Livewire DOES
        initialise on newly added DOM, so it survives a wizard step arriving by AJAX.
      * commercial_lease_type        → Other   — a plain @if on a wire:model scalar.
      * storage / ADU / single-room / property-type gates — server-side @if.
    Converting any of those to jQuery for the sake of uniformity would replace
    working code with more code. Do not.

    WHY NOT @this.call('updateOwnerPays', …)
    ----------------------------------------
    The manual pages call it. It exists on LandlordOfferListing and
    LandlordOfferListingEdit and NOT on LandlordMlsQuickImport, so calling it from
    shared code would raise "method not found" on the one surface this fix is for.
    The trait carries syncOwnerPaysSelection() instead, which every consumer
    inherits and which reproduces its semantics — see LandlordLeasingTerms.

    WHY OWNER PAYS IS A call() AND THE OTHER TWO ARE set()
    ------------------------------------------------------
    Only Owner Pays deletes something. Dropping "Other" clears other_owner_pays,
    and that may only follow a gesture: an updated() hook fires on every write to
    the property, so clearing there wiped stored text on a save that merely
    restated the answers. The other two controls delete nothing, so a plain set()
    — which still recomputes the derived flags through updated() — is right for
    them. Do not "make them consistent" by converting all three.

    See _seller-terms-behaviour.blade.php for why this is @push('scripts') rather
    than an inline <script>, and why the Quick Import wrapper includes it directly.
--}}
@once
@push('scripts')
<script>
    (function () {
        'use strict';

        window.applyLandlordLeaseTermVisibility = function () {
            var values = ($('.lease_term_options').val() || []);
            var $wrapper = $('#other_desired_lease_length_wrapper');
            if ($wrapper.length) {
                $wrapper.css('display', values.includes('Other') ? 'block' : 'none');
            }
        };

        window.applyLandlordOwnerPaysVisibility = function () {
            var values = ($('#owner_pays').val() || []);
            var $wrapper = $('#other_owner_pays_wrapper');
            if ($wrapper.length) {
                $wrapper.css('display', values.includes('Other') ? 'block' : 'none');
            }
        };

        window.applyLandlordTermsOfLeaseVisibility = function () {
            var values = ($('#terms_of_lease').val() || []);
            var container = document.getElementById('otherLeaseContainer');
            if (container) {
                container.classList.toggle('d-none', !values.includes('Other'));
            }
        };

        if (window.__landlordLeaseTermsConditionalsBound) {
            return;
        }
        window.__landlordLeaseTermsConditionalsBound = true;

        // Delegated on document for the same reason as the seller behaviour: it must
        // match a control that appears several wizard steps after the page loaded.
        $(document).on('change', '.lease_term_options', function () {
            @this.set('desired_lease_length', $(this).val() || [], false);
            window.applyLandlordLeaseTermVisibility();
        });
        // A GESTURE, not a plain set(): dropping "Other" must also drop the free
        // text that described it, and only a real change may do that. See
        // LandlordLeasingTerms::syncOwnerPaysSelection().
        $(document).on('change', '#owner_pays', function () {
            @this.call('syncOwnerPaysSelection', $(this).val() || []);
            window.applyLandlordOwnerPaysVisibility();
        });
        $(document).on('change', '#terms_of_lease', function () {
            @this.set('terms_of_lease', $(this).val() || [], false);
            window.applyLandlordTermsOfLeaseVisibility();
        });

        function reapply() {
            window.applyLandlordLeaseTermVisibility();
            window.applyLandlordOwnerPaysVisibility();
            window.applyLandlordTermsOfLeaseVisibility();
        }

        document.addEventListener('livewire:load', function () {
            reapply();
            if (window.Livewire && Livewire.hook) {
                Livewire.hook('message.processed', function () {
                    setTimeout(reapply, 250);
                });
            }
        });
    })();
</script>
@endpush
@endonce
