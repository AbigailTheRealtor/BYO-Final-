{{--
    Hire Agent detail — the Quick Actions band's behaviour half (Copy Link).

    Extracted from hire_landlord_agent/view.blade.php as groundwork for Buyer adoption, for the
    reason the section-nav partial beside it states: every role adopting the band needs this
    handler, and copying it per role is how the copies diverge.

    It binds [data-hla-copy-link], so any role emitting an x-viho.action-tile with that attribute
    gets a working control. The `hla-` prefix is carried over unchanged rather than renamed: the
    framework stylesheet and the existing tests select on it, and a rename would be a churn commit
    touching every role for no behavioural gain.

    GATE AND WHITESPACE: identical contract to the section-nav partial — the caller owns the
    flag check; this file opens flush against `<script>` and ends with exactly one trailing
    newline, because Blade eats the newline terminating the `@include` line. See that file's
    whitespace note for how the rule was measured.
--}}<script>
/*
    M5.3 — Copy Link.

    The behaviour half of the Quick Actions band. x-viho.quick-actions and x-viho.action-tile ship
    no script by contract, so a caller that wants a control to DO something wires it here.

    This is a new handler rather than a reuse: the sidebar's legacy Copy button carries a hook
    class that nothing in the repository binds to. It is dead markup in this view and in about ten
    others. Fixing all of them is not this milestone's scope, so this control gets its own working
    handler and the legacy one is left exactly as it was.

    Two paths, because the modern one is not always available: navigator.clipboard requires a
    secure context, so it is absent on any environment served over plain HTTP. The textarea +
    execCommand fallback is the same shape dashboard.blade.php already uses.
*/
(function () {
    var buttons = document.querySelectorAll('[data-hla-copy-link]');
    if (!buttons.length) { return; }

    function confirmCopy(button, ok) {
        var tile = button.closest('.viho-action-tile');
        var status = tile ? tile.querySelector('[data-hla-copy-status]') : null;
        if (!status) { return; }
        status.textContent = ok ? 'Link copied' : 'Press Ctrl+C to copy';
        window.setTimeout(function () { status.textContent = ''; }, 2500);
    }

    function legacyCopy(text) {
        var field = document.createElement('textarea');
        field.value = text;
        // Kept in the viewport but visually inert: a field positioned off-screen is not always
        // selectable, and display:none never is.
        field.setAttribute('readonly', 'readonly');
        field.style.position = 'fixed';
        field.style.top = '0';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(field);
        return ok;
    }

    Array.prototype.forEach.call(buttons, function (button) {
        button.addEventListener('click', function () {
            var url = button.getAttribute('data-hla-copy-link');
            if (!url) { return; }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(
                    function () { confirmCopy(button, true); },
                    function () { confirmCopy(button, legacyCopy(url)); }
                );
                return;
            }

            confirmCopy(button, legacyCopy(url));
        });
    });
}());
</script>
