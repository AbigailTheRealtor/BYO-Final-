{{--
    Hire Agent detail — the section navigation's behaviour half.

    Extracted from hire_landlord_agent/view.blade.php as groundwork for Buyer adoption. It was
    inline there because landlord was the only role with a nav; buyer, seller and tenant each
    need the identical script, and three more copies of it is how four pages drift apart.

    NOT FLAG-GATED HERE. The gate stays with the caller, for the reason the landlord block already
    recorded: this script drives markup the role view emits under the detail redesign's role-aware
    check, and a partial that re-read that flag would be a second opinion about whether the nav
    exists. Include it inside the same @if that emits the bar.

    THE READER CLASS IS DELIBERATELY NOT NAMED IN THIS FILE. A guard asserts the exact set of views
    gating on the detail redesign, and it matches the class name as a bare substring anywhere in
    the file — prose included. Spelling it out here would register this partial as a consumer it is
    not, and the honest fix is to describe the reader rather than to widen that list. The same
    convention, for the same reason, is why components/hire-agent/detail-shell declines to spell
    out its config key.

    WHITESPACE IS LOAD-BEARING, AND MEASURED. The file opens with this comment closing flush
    against `<script>` so nothing precedes the tag, and ends with EXACTLY ONE trailing newline.
    That newline is not decoration: Blade consumes the newline that terminates an `@include`
    line, so a partial without one swallows a blank line from the rendered page. Verified by
    byte-diffing the landlord page across the extraction — two blank lines went missing on the
    first attempt, which is how the rule was established. Same constraint as the whitespace note
    in components/hire-agent/detail-shell, and it fails just as silently.
--}}<script>
/*
    The behaviour half of the section navigation, and the reason x-viho.section-nav ships without
    a script of its own: "which section am I reading" is a product question, and a primitive that
    answered it would be answering it for every page that ever adopted the bar.

    SCROLLING IS NOT HERE. The links are real hrefs and `scroll-behavior: smooth` is in the
    stylesheet above, so the browser does the scrolling and respects prefers-reduced-motion. This
    file only decides which link is marked current.

    It reads --viho-section-nav-offset rather than repeating 0/104: the breakpoint lives in CSS,
    where it can be seen next to the rule it belongs to, and a media query is the wrong thing to
    duplicate in JavaScript.
*/
(function () {
    var nav = document.querySelector('[data-viho-section-nav]');
    if (!nav) { return; }

    // Pair each link with the element it points at. A link whose target is missing is dropped
    // rather than tracked — it cannot become current, and the nav is built so it cannot happen.
    var pairs = [];
    Array.prototype.forEach.call(nav.querySelectorAll('[data-viho-section-nav-link]'), function (link) {
        var id = (link.getAttribute('href') || '').slice(1);
        var el = id ? document.getElementById(id) : null;
        if (el) { pairs.push({ link: link, el: el }); }
    });

    if (!pairs.length) { return; }

    // The line a heading has to cross to count as "the section being read": the fixed chrome the
    // page declares, plus the bar itself, which sits directly below it.
    function readingLine() {
        var declared = parseFloat(
            getComputedStyle(document.documentElement).getPropertyValue('--viho-section-nav-offset')
        );
        return (isNaN(declared) ? 0 : declared) + nav.offsetHeight + 1;
    }

    function sync() {
        ticking = false;

        var line = readingLine();
        var currentIndex = 0;

        for (var i = 0; i < pairs.length; i++) {
            if (pairs[i].el.getBoundingClientRect().top <= line) { currentIndex = i; }
        }

        // At the bottom of the document the last sections may be too short to ever reach the
        // line, so the final entry would never light up. Award it explicitly at the end.
        if (window.innerHeight + window.pageYOffset >= document.documentElement.scrollHeight - 2) {
            currentIndex = pairs.length - 1;
        }

        for (var j = 0; j < pairs.length; j++) {
            if (j === currentIndex) {
                pairs[j].link.setAttribute('aria-current', 'true');
            } else {
                pairs[j].link.removeAttribute('aria-current');
            }
        }
    }

    // Scroll fires far more often than the page can repaint; coalesce to one update per frame.
    var ticking = false;
    function request() {
        if (ticking) { return; }
        ticking = true;
        window.requestAnimationFrame(sync);
    }

    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request);
    sync();
}());
</script>
