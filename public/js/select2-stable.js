(function($) {
    'use strict';

    $.fn.select2.defaults.set('width', '100%');

    function initUninitialized(container) {
        var $root = container ? $(container) : $(document);
        $root.find('select.select2-multiple, select.select2').each(function() {
            var $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) return;
            if ($el.closest('[style*="display: none"], [style*="display:none"], .d-none').length && !container) return;
            $el.select2({ placeholder: $el.data('placeholder') || 'Select', allowClear: true });
            var wireModel = $el.attr('wire:model');
            if (wireModel && !$el.attr('wire:model.defer') && !$el.attr('wire:model.lazy')) {
                $el.off('change.s2stable').on('change.s2stable', function() {
                    var $wire = $(this).closest('[wire\\:id]');
                    if ($wire.length) {
                        var component = Livewire.find($wire.attr('wire:id'));
                        if (component) {
                            component.set(wireModel, $(this).val());
                        }
                    }
                });
            }
        });
    }

    $(document).ready(function() {
        $('a[data-bs-toggle="tab"], button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            var target = $(e.target).attr('data-bs-target') || $(e.target).attr('href');
            if (!target) return;
            setTimeout(function() { initUninitialized(target); }, 50);
        });
    });

    document.addEventListener('livewire:load', function() {
        Livewire.hook('message.processed', function() {
            setTimeout(function() { initUninitialized(); }, 20);
        });
    });

    window.Select2Stable = { initUninitialized: initUninitialized };
})(jQuery);
