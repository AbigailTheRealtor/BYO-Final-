<?php

namespace Tests\Feature\Offers;

use Tests\TestCase;

/**
 * Batch 4 — #26 Agent Credentials placeholders.
 *
 * The requirement has two halves, and the two source documents disagreed on one of them:
 *
 *   - BROWSER-QA-CHECKLIST.md ("#26 — Agent Credentials placeholders drop the '(e.g., …)'
 *     examples") is right about the SUBSTANCE: no example text. Its expected-result sentence
 *     spelled the placeholders in Title Case, which is stale copy.
 *   - BROWSER-QA-REMEDIATION-CHECKPOINT.md asked for sentence case, which matches the
 *     project's established placeholder convention and the partial's own other four fields
 *     ("Enter first name", "Enter last name", "Enter email address", "Enter brokerage name").
 *
 * Owner decision: sentence case, no examples. That is what this test pins.
 *
 * The three placeholders live in ONE shared partial that fans out to 19 include statements
 * across 16 Create + Hire blades. The strings must never be copied into an include site —
 * that duplication is precisely how role variants drift apart in this codebase (see #14/#15).
 */
class BatchFourAgentCredentialsPlaceholderTest extends TestCase
{
    private const PARTIAL = 'resources/views/livewire/partials/agent-credentials.blade.php';

    /** The approved sentence-case wording. */
    private const APPROVED = [
        'Enter phone number',
        'Enter license number',
        'Enter NAR member ID',
    ];

    /** The stale Title Case wording that must not return. */
    private const OBSOLETE = [
        'Enter Phone Number',
        'Enter License Number',
        'Enter NAR Member ID',
    ];

    /** Every Create + Hire blade that renders the shared partial. */
    private const INCLUDE_SITES = [
        // Create (Offer Listing) — all four roles, create + edit
        'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
        'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
        'resources/views/livewire/offer-listing/buyer/offer-buyer-listing.blade.php',
        'resources/views/livewire/offer-listing/buyer/offer-buyer-listing-edit.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
        'resources/views/livewire/offer-listing/tenant/offer-tenant-listing.blade.php',
        'resources/views/livewire/offer-listing/tenant/offer-tenant-listing-edit.blade.php',
        // Hire — dedicated wizards + the live catch-all component
        'resources/views/livewire/hire-seller-agent/hire-seller-agent.blade.php',
        'resources/views/livewire/hire-seller-agent/hire-seller-agent-edit.blade.php',
        'resources/views/livewire/hire-buyer-agent/hire-buyer-agent.blade.php',
        'resources/views/livewire/hire-buyer-agent/hire-buyer-agent-edit.blade.php',
        'resources/views/livewire/hire-landlord-agent/hire-landlord-agent.blade.php',
        'resources/views/livewire/hire-landlord-agent/hire-landlord-agent-edit.blade.php',
        'resources/views/livewire/tenant-agent-auction.blade.php',
        'resources/views/livewire/tenant-agent-auction-edit.blade.php',
    ];

    private function source(string $relativePath): string
    {
        $path = base_path($relativePath);
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    /** @return list<string> every placeholder="…" value in the partial */
    private function placeholders(): array
    {
        preg_match_all('/placeholder="([^"]*)"/', $this->source(self::PARTIAL), $m);

        return $m[1];
    }

    /** #26: the three placeholders carry the approved sentence-case wording. */
    public function test_the_partial_uses_the_approved_sentence_case_placeholders(): void
    {
        $placeholders = $this->placeholders();

        foreach (self::APPROVED as $approved) {
            $this->assertContains(
                $approved,
                $placeholders,
                "the Agent Credentials partial must use the approved wording \"$approved\""
            );
        }
    }

    /** #26: the stale Title Case wording must not come back. */
    public function test_the_obsolete_title_case_placeholders_are_gone(): void
    {
        $markup = $this->source(self::PARTIAL);

        foreach (self::OBSOLETE as $obsolete) {
            $this->assertStringNotContainsString(
                'placeholder="' . $obsolete . '"',
                $markup,
                "\"$obsolete\" is the stale Title Case wording and must not be reintroduced"
            );
        }
    }

    /**
     * #26: none of the three carries "(e.g., …)" example text — the substantive requirement
     * the original checklist was actually written to capture.
     */
    public function test_the_three_placeholders_carry_no_example_text(): void
    {
        foreach (self::APPROVED as $approved) {
            foreach ($this->placeholders() as $placeholder) {
                if (strpos($placeholder, $approved) !== 0) {
                    continue;
                }

                $this->assertSame(
                    $approved,
                    $placeholder,
                    "the \"$approved\" placeholder must be exactly that — no \"(e.g., …)\" example text appended"
                );
                $this->assertStringNotContainsStringIgnoringCase('e.g.', $placeholder);
            }
        }
    }

    /** #26 regression: the other four placeholders were already correct and stay untouched. */
    public function test_the_other_four_placeholders_are_unchanged(): void
    {
        $placeholders = $this->placeholders();

        foreach ([
            'Enter first name',
            'Enter last name',
            'Enter email address',
            'Enter brokerage name',
        ] as $untouched) {
            $this->assertContains($untouched, $placeholders, "\"$untouched\" was already correct and must not change");
        }

        $this->assertCount(7, $placeholders, 'the partial has exactly seven placeholders');
    }

    /** #26: every Create and Hire surface still renders the shared partial. */
    public function test_every_create_and_hire_surface_renders_the_shared_partial(): void
    {
        foreach (self::INCLUDE_SITES as $site) {
            $this->assertStringContainsString(
                'livewire.partials.agent-credentials',
                $this->source($site),
                "$site must render the shared Agent Credentials partial"
            );
        }

        $this->assertCount(16, self::INCLUDE_SITES);
    }

    /**
     * #26: the placeholder strings must live ONLY in the shared partial. If an include site
     * ever hard-codes them, the fan-out breaks and the roles silently drift apart — the exact
     * failure mode behind #14 and #15.
     */
    public function test_no_include_site_hard_codes_the_placeholder_strings(): void
    {
        foreach (self::INCLUDE_SITES as $site) {
            $markup = $this->source($site);

            foreach (array_merge(self::APPROVED, self::OBSOLETE) as $string) {
                $this->assertStringNotContainsString(
                    'placeholder="' . $string . '"',
                    $markup,
                    "$site must not copy the Agent Credentials placeholder \"$string\" — it belongs to the shared partial alone"
                );
            }
        }
    }
}
