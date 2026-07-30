<?php

namespace Tests\Feature\Offers;

use Tests\TestCase;

/**
 * The shared publish gate is the ONLY client-side submission gate.
 *
 * ---------------------------------------------------------------------------
 * WHAT WENT WRONG
 * ---------------------------------------------------------------------------
 * Seller create carried a second capture-phase submit handler. It scanned every
 * DOM [required] input across all eleven tabs, then cleared and rewrote
 * #submit-error-banner from its own results.
 *
 * The two gates disagreed. Measured in a real browser on an empty Seller create
 * page: the shared gate found SIX missing publish-required fields; the legacy
 * scan reported TWO. The banner the user saw therefore belonged to the legacy
 * handler, not the gate — so when Livewire re-rendered (the gate's own
 * `setActiveTab` emit triggers one), the gate had no record of that banner and
 * could not restore it. The message vanished within ~250ms and Submit appeared
 * to do nothing.
 *
 * The six-versus-two discrepancy is the fingerprint: any future change that
 * reintroduces a second scanner will show up as a field list that does not match
 * publishRequiredFieldNames().
 *
 * These are source-shape guards. The behavioural proof — banner still visible
 * after the Livewire cycle, with the gate's own six labels and no publish
 * request — is a browser test, recorded in TIMED_OFFER_RUNTIME_INVESTIGATION.md,
 * because a headless browser is not available on the standard harness.
 */
class UnifiedPublishGateTest extends TestCase
{
    private const VIEW_ROOT = 'resources/views/livewire/offer-listing';
    private const GATE      = 'resources/views/partials/offer-listing/publish-submit-gate.blade.php';

    /** Views that include the shared gate and must therefore have no gate of their own. */
    public static function gatedViewProvider(): array
    {
        return [
            'seller-create'   => ['seller/offer-seller-listing.blade.php'],
            'seller-edit'     => ['seller/offer-seller-listing-edit.blade.php'],
            'landlord-create' => ['landlord/offer-landlord-listing.blade.php'],
            'landlord-edit'   => ['landlord/offer-landlord-listing-edit.blade.php'],
        ];
    }

    private function source(string $relative): string
    {
        $full = base_path(self::VIEW_ROOT . '/' . $relative);
        $this->assertFileExists($full, "Expected view missing: {$relative}");

        return (string) file_get_contents($full);
    }

    private function gateSource(): string
    {
        $full = base_path(self::GATE);
        $this->assertFileExists($full, 'The shared publish gate partial is missing.');

        return (string) file_get_contents($full);
    }

    /**
     * Executable code only.
     *
     * Comments are stripped because this repair documents the pattern it removes
     * and quotes the exact tokens asserted against; matching prose would make the
     * guard fire on its own explanation. initializeLimitedService() is excised
     * too — frozen legacy code per CLAUDE.md, never modified or cleaned up, and
     * Landlord create keeps an old gate copy inside it deliberately.
     */
    private function executableCode(string $src): string
    {
        $needle = 'function initializeLimitedService() {';
        $start  = strpos($src, $needle);

        if ($start !== false) {
            $depth = 0;
            $len   = strlen($src);
            for ($i = $start + strlen($needle) - 1; $i < $len; $i++) {
                if ($src[$i] === '{') {
                    $depth++;
                } elseif ($src[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $src = substr($src, 0, $start) . substr($src, $i + 1);
                        break;
                    }
                }
            }
        }

        $src = preg_replace('/\{\{--.*?--\}\}/s', '', $src);
        $src = preg_replace('!/\*.*?\*/!s', '', $src);
        $src = preg_replace('!^\s*//.*$!m', '', $src);

        return (string) $src;
    }

    // =====================================================================
    // 1-2. Seller no longer runs a competing gate.
    // =====================================================================

    public function test_seller_create_registers_no_submit_listener_of_its_own(): void
    {
        $code = $this->executableCode($this->source('seller/offer-seller-listing.blade.php'));

        $this->assertSame(
            0,
            preg_match_all("/addEventListener\(\s*'submit'/", $code),
            'Seller create must register NO submit listener. The shared publish gate is the '
            . 'single client-side authority; a second handler competes with it, and the banner '
            . 'it renders cannot survive a Livewire morph because the gate never recorded it.'
        );
    }

    public function test_the_legacy_required_scanner_is_gone_from_seller_create(): void
    {
        $code = $this->executableCode($this->source('seller/offer-seller-listing.blade.php'));

        foreach (['getAllRequiredFields', 'isFieldValid'] as $symbol) {
            $this->assertStringNotContainsString(
                $symbol,
                $code,
                "Seller create must not define or call {$symbol}(). It scanned every DOM "
                . '[required] input — a far wider set than the server publish contract — and '
                . 'produced the competing two-item list that masked the real six.'
            );
        }
    }

    /**
     * The fingerprint assertion.
     *
     * A broad `[required]` sweep is what produced a field list disagreeing with
     * publishRequiredFieldNames(). Exactly one such sweep legitimately remains:
     * checkFormValidity(), inside initializeWizardHandlers(), which drives
     * per-tab WIZARD NAVIGATION — not publish gating — and is deliberately
     * preserved. Pinning the count keeps that boundary explicit: a second sweep
     * would mean publish gating had crept back in.
     */
    public function test_seller_create_sweeps_dom_required_fields_only_for_wizard_navigation(): void
    {
        $code = $this->executableCode($this->source('seller/offer-seller-listing.blade.php'));

        $this->assertSame(
            1,
            preg_match_all('/querySelectorAll\(\s*[\'"]\[required\]/', $code),
            'Exactly one [required] sweep may remain in Seller create, and it belongs to '
            . 'wizard tab navigation. Publish completeness comes from the server contract '
            . 'via the shared gate.'
        );

        // And that one sweep must belong to the navigation helper, not a submit path.
        $navStart = strpos($code, 'function checkFormValidity()');
        $this->assertNotFalse($navStart, 'checkFormValidity() drives wizard navigation and must remain.');

        $sweepAt = strpos($code, "querySelectorAll('[required]')");
        $this->assertGreaterThan(
            $navStart,
            $sweepAt,
            'The surviving [required] sweep must sit inside the wizard-navigation helper.'
        );
    }

    // =====================================================================
    // 3-4. Every gated view defers to the one shared authority.
    // =====================================================================

    /**
     * @dataProvider gatedViewProvider
     */
    public function test_gated_view_includes_the_shared_gate(string $view): void
    {
        $this->assertStringContainsString(
            'partials.offer-listing.publish-submit-gate',
            $this->source($view),
            "[{$view}] must include the shared publish gate."
        );
    }

    /**
     * Landlord keeps its own guided-correction handler for now.
     *
     * It is NOT redundant: landlordGetInvalidItems() drives role-specific
     * correction behaviour that the shared gate does not implement, and Landlord
     * is verified working in the browser. Removing it is a separate, separately
     * verifiable change — recorded as follow-up rather than bundled here.
     *
     * This test pins the current, deliberate state so the asymmetry is visible
     * and cannot drift silently: exactly one role-owned submit listener remains,
     * and it is Landlord's.
     */
    public function test_landlord_retains_exactly_one_documented_role_handler(): void
    {
        $landlordCreate = $this->executableCode($this->source('landlord/offer-landlord-listing.blade.php'));

        $this->assertSame(
            1,
            preg_match_all("/addEventListener\(\s*'submit'/", $landlordCreate),
            'Landlord create is expected to retain exactly one role-specific submit handler '
            . '(guided correction). If this changed, update the follow-up note in '
            . 'TIMED_OFFER_RUNTIME_INVESTIGATION.md rather than silently accepting it.'
        );
    }

    public function test_buyer_and_tenant_do_not_use_the_shared_gate_and_are_untouched(): void
    {
        foreach (['buyer/offer-buyer-listing.blade.php', 'tenant/offer-tenant-listing.blade.php'] as $view) {
            $this->assertStringNotContainsString(
                'partials.offer-listing.publish-submit-gate',
                $this->source($view),
                "[{$view}] does not include the shared gate; this repair must not have reached it."
            );
        }
    }

    // =====================================================================
    // 5-8. The shared gate's persistence contract.
    // =====================================================================

    public function test_gate_reapplies_its_banner_after_livewire_processes_a_message(): void
    {
        $gate = $this->gateSource();

        $this->assertStringContainsString(
            "'message.processed'",
            $gate,
            'The gate must re-assert its banner on the Livewire 2 message.processed hook — '
            . 'the banner markup lives inside the component root and is morphed back to its '
            . 'hidden server-rendered shell on every render.'
        );
        $this->assertStringContainsString('gateReapply', $gate);
    }

    public function test_gate_hook_registration_is_idempotent(): void
    {
        $gate = $this->gateSource();

        $this->assertStringContainsString(
            '__offerPublishGate',
            $gate,
            'Hook registration must be tracked on a window-scoped flag so a re-executed script '
            . 'or a second gate instance cannot register the hook twice.'
        );
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*reg\.hooked\s*\)\s*return;/',
            $gate,
            'The gate must bail out when the shared hook is already registered.'
        );
    }

    /**
     * Re-applying must never hide.
     *
     * An earlier attempt cleared the banner when its recompute came back empty
     * mid-morph, which reproduced the very defect it was meant to fix. Hiding
     * belongs to gateHideBanner() alone, which runs only at the start of a fresh
     * submit.
     */
    public function test_reapply_never_hides_the_banner(): void
    {
        $gate  = $this->gateSource();
        $start = strpos($gate, 'function gateReapply()');
        $this->assertNotFalse($start, 'gateReapply() must exist.');

        $end  = strpos($gate, 'window.__offerPublishGate', $start);
        $body = substr($gate, $start, $end - $start);

        $this->assertStringNotContainsString(
            "classList.add('d-none')",
            $body,
            'gateReapply() must never hide the banner. Only gateHideBanner(), at the start of '
            . 'a new submit, may do that.'
        );
    }

    public function test_gate_forgets_state_when_the_banner_is_intentionally_hidden(): void
    {
        $gate  = $this->gateSource();
        $start = strpos($gate, 'function gateHideBanner()');
        $this->assertNotFalse($start, 'gateHideBanner() must exist.');

        $body = substr($gate, $start, 900);

        $this->assertStringContainsString(
            'gateForget()',
            $body,
            'Hiding the banner must clear the remembered message, so a successful validation '
            . 'pass stays clear through every subsequent render.'
        );
    }

    /**
     * The Livewire lifecycle hook proved sufficient once the competing Seller
     * handler was removed, verified in a real browser. A MutationObserver was
     * prototyped and deliberately dropped: it is only justified if the hook
     * fails, and it did not.
     */
    public function test_gate_does_not_resort_to_a_mutation_observer(): void
    {
        $this->assertStringNotContainsString(
            'MutationObserver',
            $this->gateSource(),
            'The message.processed hook is sufficient. Reach for an observer only with browser '
            . 'evidence that the lifecycle hook cannot hold the banner.'
        );
    }

    // =====================================================================
    // 9. The server stays authoritative.
    // =====================================================================

    public function test_gate_remains_advisory_and_defers_to_server_validation(): void
    {
        $gate = $this->gateSource();

        $this->assertStringContainsString(
            'publish-validation-failed',
            $gate,
            'Server rejections must still surface through the gate, so server-side validation '
            . 'remains authoritative and its errors stay visible.'
        );

        // Server-sourced messages are re-applied verbatim; they may name fields
        // outside GATE_REQUIRED, so the client recompute must not second-guess them.
        $this->assertStringContainsString("'server'", $gate);
        $this->assertStringContainsString("gateActive.source === 'client'", $gate);
    }

    public function test_gate_never_auto_submits_or_disables_the_button(): void
    {
        $gate = $this->gateSource();

        $this->assertStringNotContainsString(
            '.submit()',
            $gate,
            'The gate must never submit the form itself — a Livewire update must not trigger a '
            . 'publish, and no duplicate request may be produced.'
        );
        $this->assertStringNotContainsString(
            "setAttribute('disabled'",
            $gate,
            'Completeness is decided on submit, never by disabling the button.'
        );
    }
}
