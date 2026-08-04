<?php

namespace Tests\Feature\HireAgent;

use Tests\TestCase;

/**
 * The architectural half of Milestone 3: Hire Agent timer code must not reference or derive from
 * listing expiration fields — in either direction.
 *
 * HireAgentTimerRetirementTest proves the rendered pages carry no countdown. That is a behavioural
 * check against fixtures, and behaviour tests only cover the states someone thought to fixture.
 * This file reads the SOURCE instead, because the governing rule is structural: a listing
 * expiration date and a bidding timer are separate concepts and must stay unwired. The specific
 * failure this guards against is subtle and easy to reintroduce in good faith — someone deletes a
 * countdown, then "restores" it by counting down to expiration_date, and every behavioural test
 * still passes because a countdown to expiration_date renders perfectly well.
 *
 * The rule has two directions and both are asserted:
 *
 *   expiration -> timer   nothing may convert expiration_date into remaining time (diffIn*,
 *                         countdown initialisers, timer markup)
 *   timer -> expiration   nothing may write expiration_date from elapsed time or bid activity
 *
 * Scope is Hire Agent only. Create Offer has its own, separately-audited canonical bidding window
 * (BiddingWindow / BiddingWindowService) which legitimately computes deadlines; it is explicitly
 * out of scope here and is asserted to still exist, so that a future sweep cannot quietly take it
 * out under cover of this rule.
 */
class HireAgentTimerExpirationIsolationTest extends TestCase
{
    /** Hire Agent production sources in scope for this rule. */
    private function hireAgentSources(): array
    {
        $paths = [
            'resources/views/hire_seller_agent/view.blade.php',
            'resources/views/hire_buyer_agent/view.blade.php',
            'resources/views/hire_landlord_agent/view.blade.php',
            'resources/views/hire_tenant_agent/view.blade.php',
            'resources/views/hire_seller_agent/search.blade.php',
            'resources/views/hire_landlord_agent/search.blade.php',
            'resources/views/hire_tenant_agent/search.blade.php',
            'resources/views/hire_seller_agent/bid_detail.blade.php',
            'resources/views/hire_buyer_agent/bid_detail.blade.php',
            'resources/views/hire_landlord_agent/view-bid.blade.php',
            'resources/views/hire_landlord_agent/partials/bid_action_row.blade.php',
            'resources/views/livewire/tenant/tenant-agent-auction-bid.blade.php',
            'app/Http/Controllers/SellerAgentAuctionController.php',
            'app/Http/Controllers/BuyerAgentAuctionController.php',
            'app/Http/Controllers/LandlordAgentAuctionController.php',
            'app/Http/Controllers/TenantAgentAuctionController.php',
            'app/Http/Controllers/Controller.php',
            'app/Http/Livewire/Tenant/TenantAgentAuctionBid.php',
            'app/Models/TenantAgentAuction.php',
        ];

        $out = [];
        foreach ($paths as $rel) {
            $full = base_path($rel);
            $this->assertFileExists($full, "Hire Agent source {$rel} is missing — update this test's inventory.");
            $out[$rel] = file_get_contents($full);
        }

        return $out;
    }

    /**
     * Strip comments before matching.
     *
     * Every removal in this checkpoint left a comment explaining what went and why, and those
     * comments necessarily name the very tokens being banned ("the countdown read auction_time…").
     * Matching raw text would flag the documentation of the fix as the fix's own violation, and
     * the obvious way to make that green again is to delete the explanation. So: comments out,
     * then match. Code is what is under test.
     */
    private function stripComments(string $source): string
    {
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source);   // Blade
        $source = preg_replace('/\/\*.*?\*\//s', '', $source);        // block
        $source = preg_replace('/^\s*\/\/.*$/m', '', $source);        // whole-line //
        $source = preg_replace('/^\s*\*.*$/m', '', $source);          // docblock body

        return $source;
    }

    // ── Direction 1: expiration must not become a timer ──────────────────────

    /**
     * No Hire Agent source may emit countdown markup or initialise a countdown, whatever it would
     * count down to.
     */
    public function test_no_hire_agent_source_emits_countdown_markup_or_initialisers(): void
    {
        $banned = [
            'timer-d', 'timer-h', 'timer-m', 'timer-s',
            'timer.jquery',
            'countdown: true',
            'onTimerEnd',
            'data-expiration',
            'data-seconds',
        ];

        foreach ($this->hireAgentSources() as $rel => $raw) {
            $code = $this->stripComments($raw);

            foreach ($banned as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $code,
                    "{$rel} reintroduces countdown machinery [{$token}]. The Hire Agent timer is retired; "
                    . 'a countdown to expiration_date is still a countdown and is equally forbidden.'
                );
            }
        }
    }

    /**
     * No Hire Agent source may convert a date into remaining time.
     *
     * diffInDays / diffInSeconds and friends are how a stored date becomes "3d 04:12:07". Banning
     * the conversion — rather than only the markup — is what stops expiration_date from being
     * quietly re-pointed at a new clock.
     */
    public function test_no_hire_agent_source_computes_remaining_time(): void
    {
        foreach ($this->hireAgentSources() as $rel => $raw) {
            $code = $this->stripComments($raw);

            $this->assertSame(
                0,
                preg_match_all('/->diffIn(Seconds|Minutes|Hours|Days|Weeks)\s*\(/', $code),
                "{$rel} computes a time difference. Hire Agent surfaces must not express a date as "
                . 'time remaining — that is the countdown the retirement removed.'
            );

            // Carbon's ->diff(...)->format('%H') was the other half of the retired block.
            $this->assertSame(
                0,
                preg_match_all("/->diff\([^)]*\)->format\(/", $code),
                "{$rel} formats a date difference — same countdown, different Carbon call."
            );
        }
    }

    /**
     * The retired timer's own inputs must not come back.
     *
     * auction_time held the bidding window ("14 Days") and was the seed for every synthesised
     * expiry. Reading it again is the first step of rebuilding the timer.
     */
    public function test_no_hire_agent_source_reads_the_retired_timer_inputs(): void
    {
        foreach ($this->hireAgentSources() as $rel => $raw) {
            $code = $this->stripComments($raw);

            $this->assertStringNotContainsString(
                'auction_time',
                $code,
                "{$rel} reads auction_time — the retired bidding-window length. Nothing may derive "
                . 'a deadline from it.'
            );

            foreach (['isBiddingPeriodType', 'isBiddingPeriodActive', 'autoTransitionBpToPending'] as $symbol) {
                $this->assertStringNotContainsString(
                    $symbol,
                    $code,
                    "{$rel} references retired timer-era symbol {$symbol}."
                );
            }
        }
    }

    // ── Direction 2: elapsed time must not write expiration ──────────────────

    /**
     * Nothing in Hire Agent may WRITE expiration_date.
     *
     * The Seller controller used to push expiration_date forward by a day on every bid, so the
     * listing's deadline moved with bidding activity. Reading expiration_date is fine and
     * expected — it is the listing's lifecycle field. Writing it from anywhere but the owner's
     * own edit form is how the two concepts get re-coupled.
     *
     * The listing create/update paths legitimately persist what the owner typed, so this asserts
     * against the four detail controllers' bid-handling surface via the specific write shapes the
     * retired code used, not against any mention of the field.
     */
    public function test_no_hire_agent_source_derives_expiration_from_elapsed_time_or_bids(): void
    {
        $writeShapes = [
            "->modify('+1 day')",
            'addDays($duration_value)',
            'addHours($duration_value)',
            'addWeeks($duration_value)',
            'addMinutes($duration_value)',
        ];

        foreach ($this->hireAgentSources() as $rel => $raw) {
            $code = $this->stripComments($raw);

            foreach ($writeShapes as $shape) {
                $this->assertStringNotContainsString(
                    $shape,
                    $code,
                    "{$rel} advances a date by a duration [{$shape}]. expiration_date must be owner "
                    . 'input, never a function of elapsed time or bid activity.'
                );
            }

            $this->assertSame(
                0,
                preg_match_all("/meta_key['\"]?\s*,\s*['\"]expiration_date['\"]\s*\)[\s\S]{0,200}?->update\(/", $code),
                "{$rel} updates the expiration_date meta row. Only the owner's listing edit may change it."
            );
        }
    }

    // ── expiration_date survives as a plain lifecycle field ──────────────────

    /**
     * The counterpart assertion, and the reason this file is not simply a ban list: retiring the
     * timer must not have taken normal listing expiration with it. Each of the four detail views
     * must still read expiration_date and still derive $isExpired from it.
     */
    public function test_the_four_detail_views_still_read_expiration_date_for_lifecycle(): void
    {
        foreach ([
            'resources/views/hire_seller_agent/view.blade.php',
            'resources/views/hire_buyer_agent/view.blade.php',
            'resources/views/hire_landlord_agent/view.blade.php',
            'resources/views/hire_tenant_agent/view.blade.php',
        ] as $rel) {
            $code = $this->stripComments(file_get_contents(base_path($rel)));

            $this->assertStringContainsString(
                'expiration_date',
                $code,
                "{$rel} no longer reads expiration_date. Normal listing expiration is retained by "
                . 'this checkpoint, not removed with the timer.'
            );
            $this->assertStringContainsString(
                '$isExpired',
                $code,
                "{$rel} no longer derives \$isExpired — listing expiry must still be evaluated."
            );
        }
    }

    // ── Create Offer is a different feature and keeps its window ─────────────

    /**
     * Create Offer's canonical bidding window is out of scope and must remain. If a later sweep
     * ever applied this rule beyond Hire Agent, these are the files it would delete first.
     */
    public function test_create_offer_bidding_window_is_untouched(): void
    {
        foreach ([
            'app/Services/Offers/BiddingWindow.php',
            'app/Services/Offers/BiddingWindowService.php',
        ] as $rel) {
            $this->assertFileExists(
                base_path($rel),
                "Create Offer's {$rel} must survive — the Hire Agent timer retirement does not reach it."
            );
        }

        $service = file_get_contents(base_path('app/Services/Offers/BiddingWindowService.php'));
        $this->assertStringContainsString(
            'isBiddingPeriod',
            $service,
            "Create Offer's own bidding-period logic must remain intact."
        );
    }
}
