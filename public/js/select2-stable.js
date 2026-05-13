(function($) {
    'use strict';

    $.fn.select2.defaults.set('width', '100%');

    function repairBrokenSelects() {
        $('select.select2-hidden-accessible').each(function() {
            var $el = $(this);
            var hasContainer = $el.siblings('.select2-container').length > 0 ||
                               $el.parent().find('> .select2-container').length > 0 ||
                               $el.next('.select2-container').length > 0;
            if (!hasContainer) {
                try { $el.select2('destroy'); } catch(e) {}
                $el.removeClass('select2-hidden-accessible')
                   .removeAttr('data-select2-id')
                   .removeAttr('aria-hidden')
                   .css({
                       position: '',
                       width: '',
                       height: '',
                       padding: '',
                       margin: '',
                       overflow: '',
                       clip: '',
                       'clip-path': '',
                       'white-space': '',
                       border: ''
                   });
            }
        });

        $('.input-cover').each(function() {
            var $cover = $(this);
            var $controls = $cover.find('select.form-control, input.form-control');
            $controls.each(function() {
                var $ctrl = $(this);
                if ($ctrl.hasClass('select2-hidden-accessible')) return;
                if ($ctrl.hasClass('select2-multiple') || $ctrl.hasClass('select2')) return;
                var el = this;
                var style = el.style;
                if (style.height === '0px' || style.height === '0' ||
                    style.display === 'none' ||
                    style.visibility === 'hidden' ||
                    style.opacity === '0' ||
                    style.position === 'absolute' ||
                    (style.clip && style.clip !== 'auto')) {
                    style.height = '';
                    style.display = '';
                    style.visibility = '';
                    style.opacity = '';
                    style.position = '';
                    style.clip = '';
                    style.clipPath = '';
                    style.overflow = '';
                    style.margin = '';
                    style.padding = '';
                    style.border = '';
                    style.whiteSpace = '';
                    style.width = '';
                }
            });

            if (this.style.height === '0px' || this.style.height === '0' ||
                this.style.overflow === 'hidden') {
                this.style.height = '';
                this.style.overflow = '';
            }
        });
    }

    function initUninitialized(container) {
        var $root = container ? $(container) : $(document);
        $root.find('select.select2-multiple, select.select2').each(function() {
            var $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) return;
            if ($el.closest('[style*="display: none"], [style*="display:none"], .d-none').length && !container) return;
            $el.select2({ placeholder: $el.data('placeholder') || 'Select', allowClear: true, width: '100%', closeOnSelect: false });
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

        setTimeout(function() { initUninitialized(); }, 100);
    });

    if (window.Livewire) {
        Livewire.hook('message.processed', function() {
            repairBrokenSelects();
            setTimeout(function() { initUninitialized(); }, 200);
        });
    } else {
        document.addEventListener('livewire:load', function() {
            Livewire.hook('message.processed', function() {
                repairBrokenSelects();
                setTimeout(function() { initUninitialized(); }, 200);
            });
        });
    }

    window.initFullServiceSelect2Multiple = function($el) {
        if (!$el || !$el.length) return;
        if ($el.hasClass('select2-hidden-accessible')) return;
        var placeholder = $el.data('placeholder') || 'Select';
        $el.select2({ placeholder: placeholder, allowClear: true, width: '100%', closeOnSelect: false });
    };

    window.Select2Stable = {
        initUninitialized: initUninitialized,
        repairBrokenSelects: repairBrokenSelects,
        initFullServiceSelect2Multiple: window.initFullServiceSelect2Multiple
    };
})(jQuery);
