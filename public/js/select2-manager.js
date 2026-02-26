window.Select2Manager = (function($) {
    'use strict';

    function isOpen(el) {
        var $el = $(el);
        if (!$el.hasClass('select2-hidden-accessible')) return false;
        try { return $el.data('select2') && $el.data('select2').isOpen(); } catch(e) { return false; }
    }

    function initField(selector, options) {
        var $el = $(selector);
        if (!$el.length) return;
        if ($el.hasClass('select2-hidden-accessible')) return;
        $el.select2($.extend({ placeholder: 'Select', allowClear: true }, options || {}));
    }

    function safeSync(selector, values) {
        var $el = $(selector);
        if (!$el.length) return;
        if (!$el.hasClass('select2-hidden-accessible')) return;
        if (isOpen($el)) return;
        var current = $el.val() || [];
        if (typeof current === 'string') current = [current];
        if (typeof values === 'string') values = [values];
        values = values || [];
        if (JSON.stringify(current.sort()) !== JSON.stringify(values.slice().sort())) {
            $el.val(values).trigger('change.select2');
        }
    }

    function toggleOther(selector, otherSelector, showClass) {
        var $el = $(selector);
        var vals = $el.val() || [];
        if (typeof vals === 'string') vals = [vals];
        var hasOther = vals.some(function(v) { return v && v.toLowerCase() === 'other'; });
        if (hasOther) {
            $(otherSelector).removeClass(showClass || 'd-none').show();
        } else {
            $(otherSelector).addClass(showClass || 'd-none');
        }
    }

    return {
        init: initField,
        safeSync: safeSync,
        isOpen: isOpen,
        toggleOther: toggleOther
    };
})(jQuery);
