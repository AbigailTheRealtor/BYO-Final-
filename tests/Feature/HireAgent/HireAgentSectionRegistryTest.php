<?php

namespace Tests\Feature\HireAgent;

use App\Services\HireAgent\HireAgentDetailAudience;
use App\Support\HireAgent\HireAgentDetailSections;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The section registry and its resolver, before anything renders from either.
 *
 * This is the second step that ships without moving a page, and for the same reason as the first:
 * the registry encodes decisions — which sections exist, which are agent-only, which are gone —
 * and each of those is worth being able to fail on its own, in a file that names it, rather than
 * inside a Blade migration where a failure could mean the markup instead.
 *
 * FOUR CLAIMS, AND THEY FAIL FOR DIFFERENT REASONS:
 *
 *   1. THE FINAL ARCHITECTURE IS WHAT THE REGISTRY SAYS. The consumer set, the agent-only pair,
 *      and the role scoping of financing and pre-screening are asserted against the registry
 *      directly. These are product decisions; a test that derived them from the registry would
 *      assert nothing.
 *
 *   2. REMOVAL IS STRUCTURAL, NOT A SWITCH. Services and Broker Compensation are absent rather
 *      than disabled, so there is no flag to flip back. Asserted as absence from every role and
 *      both audiences, AND as a named refusal when a caller tries to supply one.
 *
 *   3. THE RESOLVER CANNOT PRODUCE A DISAGREEMENT. One visibility answer per section drives both
 *      the bar and the card, so the invariant landlord maintains by hand-copying is structural
 *      here. What is left to test is that scope, audience and content compose in the right order.
 *
 *   4. IT FAILS LOUDLY. A section with no guard, a guard with no section, an unknown audience and
 *      a malformed entry are all programming errors that would otherwise present as "that section
 *      isn't built yet", which is indistinguishable from the truth during a migration.
 */
class HireAgentSectionRegistryTest extends TestCase
{
    private const ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    /** Sections every role shows to every viewer. */
    private const CONSUMER_SECTIONS = [
        'listing-details',
        'property',
        'terms',
        'additional-details',
        'representation',
        'role-info',
    ];

    private const AGENT_ONLY_SECTIONS = ['referral', 'agent-credentials'];

    private function sections(): HireAgentDetailSections
    {
        return app(HireAgentDetailSections::class);
    }

    private function agent(): string
    {
        return HireAgentDetailAudience::AUDIENCE_AGENT;
    }

    /** Every section in scope, answered true — the "fully populated listing" of this layer. */
    private function allVisible(string $role, string $audience): array
    {
        return array_fill_keys(
            array_keys($this->sections()->inScopeFor($role, $audience)),
            true
        );
    }

    // ── 1. The final architecture ────────────────────────────────────────────

    /**
     * The consumer page, per role, in document order.
     *
     * Written out rather than derived. This IS the agreed architecture, so the assertion has to
     * state it independently of the file it is checking — otherwise a change to the registry would
     * change the expectation with it and the test would agree with anything.
     */
    public function test_the_consumer_section_set_is_the_agreed_architecture(): void
    {
        $expected = [
            'seller'   => ['listing-details', 'property', 'terms', 'financing', 'additional-details', 'representation', 'role-info'],
            'buyer'    => ['listing-details', 'property', 'terms', 'financing', 'additional-details', 'representation', 'role-info'],
            'landlord' => ['listing-details', 'property', 'terms', 'additional-details', 'representation', 'role-info'],
            'tenant'   => ['listing-details', 'property', 'terms', 'pre-screening', 'additional-details', 'representation', 'role-info'],
        ];

        foreach ($expected as $role => $keys) {
            $this->assertSame(
                $keys,
                array_keys($this->sections()->inScopeFor($role, HireAgentDetailAudience::AUDIENCE_CONSUMER)),
                "{$role}: the consumer section set or its order has drifted from the agreed architecture."
            );
        }
    }

    /**
     * The agent page is the consumer page plus exactly two sections, appended.
     *
     * Stated as a relationship rather than as a second literal list: the agent audience must never
     * LOSE a consumer section, and expressing that as two independent lists would let one drift
     * from the other without failing.
     */
    public function test_the_agent_page_is_the_consumer_page_plus_the_two_agent_sections(): void
    {
        foreach (self::ROLES as $role) {
            $consumer = array_keys($this->sections()->inScopeFor($role, HireAgentDetailAudience::AUDIENCE_CONSUMER));
            $agent    = array_keys($this->sections()->inScopeFor($role, $this->agent()));

            $this->assertSame(
                array_merge($consumer, self::AGENT_ONLY_SECTIONS),
                $agent,
                "{$role}: the agent set must be the consumer set followed by the two agent-only sections."
            );
        }
    }

    /** The sections shared by every role, for both audiences. */
    public function test_the_shared_consumer_sections_apply_to_all_four_roles(): void
    {
        foreach (self::CONSUMER_SECTIONS as $key) {
            $this->assertSame(
                self::ROLES,
                $this->sections()->rolesFor($key),
                "[{$key}] must apply to all four roles."
            );
        }
    }

    /** Financing is a sale concept; pre-screening is a lease concept. Neither is universal. */
    public function test_the_role_specific_sections_are_scoped_to_their_roles(): void
    {
        $this->assertSame(['seller', 'buyer'], $this->sections()->rolesFor('financing'));
        $this->assertSame(['tenant'], $this->sections()->rolesFor('pre-screening'));

        foreach (['landlord', 'tenant'] as $role) {
            $this->assertArrayNotHasKey(
                'financing',
                $this->sections()->inScopeFor($role, $this->agent()),
                "{$role}: only a sale is financed."
            );
        }

        foreach (['seller', 'buyer', 'landlord'] as $role) {
            $this->assertArrayNotHasKey(
                'pre-screening',
                $this->sections()->inScopeFor($role, $this->agent()),
                "{$role}: pre-screening belongs to the tenant flow."
            );
        }
    }

    /**
     * Role-specific LABELS — the same section calling itself different things.
     *
     * The direction of the request is what splits them: a seller and a landlord describe a property
     * they have, a buyer and a tenant one they want; a sale has Sale Terms and a lease has Leasing
     * Terms. Getting one wrong puts the right content under the wrong heading, which no structural
     * assertion would catch.
     */
    public function test_role_specific_labels_resolve_per_role(): void
    {
        $expected = [
            'property' => [
                'seller' => 'Property Details', 'buyer' => 'Property Preferences',
                'landlord' => 'Property Details', 'tenant' => 'Property Preferences',
            ],
            'terms' => [
                'seller' => 'Sale Terms', 'buyer' => 'Purchasing Terms',
                'landlord' => 'Leasing Terms', 'tenant' => 'Leasing Terms',
            ],
            'role-info' => [
                'seller' => "Seller's Info", 'buyer' => "Buyer's Info",
                'landlord' => "Landlord's Info", 'tenant' => "Tenant's Info",
            ],
        ];

        foreach ($expected as $key => $byRole) {
            foreach ($byRole as $role => $label) {
                $resolved = $this->sections()->resolve(
                    $role,
                    $this->agent(),
                    $this->allVisible($role, $this->agent())
                );

                $this->assertSame(
                    $label,
                    $resolved[$this->sections()->anchorId($key)]['label'],
                    "{$role}/{$key}: wrong label."
                );
            }
        }
    }

    /**
     * A label override wins, for the one heading that is a fact about a listing rather than a role.
     *
     * "Buyer's Info" becomes "Agent's Info" when the request was posted by an agent. That cannot
     * live in config — it varies per row — so the caller resolves it and passes it in.
     */
    public function test_a_label_override_replaces_the_configured_label(): void
    {
        $resolved = $this->sections()->resolve(
            'buyer',
            $this->agent(),
            $this->allVisible('buyer', $this->agent()),
            ['role-info' => "Agent's Info"]
        );

        $this->assertSame("Agent's Info", $resolved[$this->sections()->anchorId('role-info')]['label']);

        // Nothing else moved.
        $this->assertSame('Purchasing Terms', $resolved[$this->sections()->anchorId('terms')]['label']);
    }

    // ── 2. Removal is structural ─────────────────────────────────────────────

    /**
     * Services and Broker Compensation cannot appear for any role, in either audience.
     *
     * The agent audience is checked as well as the consumer one, and that is the half worth having:
     * "removed" here means removed from the page, not moved behind a privileged view.
     */
    public function test_services_and_compensation_are_absent_for_every_role_and_audience(): void
    {
        foreach (self::ROLES as $role) {
            foreach ([HireAgentDetailAudience::AUDIENCE_CONSUMER, $this->agent()] as $audience) {
                $inScope = array_keys($this->sections()->inScopeFor($role, $audience));

                foreach (HireAgentDetailSections::REMOVED_SECTIONS as $removed) {
                    $this->assertNotContains(
                        $removed,
                        $inScope,
                        "{$role}/{$audience}: [{$removed}] was removed from the redesigned page."
                    );
                }
            }
        }
    }

    /** They are absent from the registry itself, not disabled within it — there is no switch. */
    public function test_the_removed_sections_have_no_registry_entry_at_all(): void
    {
        $ids = array_column(config('hire_agent_sections.sections'), 'id');

        foreach (HireAgentDetailSections::REMOVED_SECTIONS as $removed) {
            $this->assertNotContains(
                $removed,
                $ids,
                "[{$removed}] must have no registry entry. A disabled entry is a switch someone can flip."
            );
            $this->assertSame([], $this->sections()->rolesFor($removed));
        }
    }

    /** Reintroducing one by supplying a guard fails with an explanation, not a generic error. */
    public function test_supplying_a_guard_for_a_removed_section_is_refused_by_name(): void
    {
        foreach (HireAgentDetailSections::REMOVED_SECTIONS as $removed) {
            try {
                $this->sections()->resolve(
                    'buyer',
                    $this->agent(),
                    $this->allVisible('buyer', $this->agent()) + [$removed => true]
                );
                $this->fail("Supplying a guard for [{$removed}] should have been refused.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($removed, $e->getMessage());
                $this->assertStringContainsString('removed from the redesigned', $e->getMessage());
                $this->assertStringContainsString('Legacy rendering of both is unaffected', $e->getMessage());
            }
        }
    }

    // ── 3. Scope, audience and content compose ───────────────────────────────

    /**
     * An agent-only section is withheld from a consumer even when it HAS content.
     *
     * The discriminating case for the whole audience feature. A consumer must not be able to reach
     * referral or credentials by the listing simply being well filled in.
     */
    public function test_a_consumer_never_receives_an_agent_section_however_full_the_listing(): void
    {
        foreach (self::ROLES as $role) {
            $consumerScope = $this->allVisible($role, HireAgentDetailAudience::AUDIENCE_CONSUMER);

            $resolved = $this->sections()->resolve($role, HireAgentDetailAudience::AUDIENCE_CONSUMER, $consumerScope);

            foreach (self::AGENT_ONLY_SECTIONS as $agentOnly) {
                $this->assertArrayNotHasKey(
                    $this->sections()->anchorId($agentOnly),
                    $resolved,
                    "{$role}: a consumer received [{$agentOnly}]."
                );
            }
        }
    }

    /** And the complement, so the assertion above cannot pass by the sections being broken. */
    public function test_an_agent_does_receive_both_agent_sections(): void
    {
        foreach (self::ROLES as $role) {
            $resolved = $this->sections()->resolve($role, $this->agent(), $this->allVisible($role, $this->agent()));

            foreach (self::AGENT_ONLY_SECTIONS as $agentOnly) {
                $this->assertArrayHasKey(
                    $this->sections()->anchorId($agentOnly),
                    $resolved,
                    "{$role}: the agent audience must receive [{$agentOnly}]."
                );
            }
        }
    }

    /** Content still decides: a section in scope with nothing in it does not render. */
    public function test_an_empty_section_is_withheld_even_when_in_scope(): void
    {
        $visible = $this->allVisible('buyer', $this->agent());
        $visible['financing'] = false;

        $resolved = $this->sections()->resolve('buyer', $this->agent(), $visible);

        $this->assertArrayNotHasKey($this->sections()->anchorId('financing'), $resolved);
        $this->assertArrayHasKey($this->sections()->anchorId('terms'), $resolved, 'Its neighbours are unaffected.');
    }

    /**
     * The returned array serves the bar and the cards from one value.
     *
     * The nav takes array_values() and each card asks isset(). Asserting the shape is asserting
     * that neither consumer needs to re-derive anything — which is the property that makes a
     * nav/section disagreement structurally impossible rather than merely tested for.
     */
    public function test_the_resolved_shape_serves_both_the_bar_and_the_cards(): void
    {
        $resolved = $this->sections()->resolve('tenant', $this->agent(), $this->allVisible('tenant', $this->agent()));

        foreach ($resolved as $id => $row) {
            $this->assertSame($id, $row['id'], 'The key must be the anchor id the card carries.');
            $this->assertStringStartsWith(HireAgentDetailSections::ID_PREFIX, $id);
            $this->assertNotSame('', trim($row['label']), 'x-viho.section-nav drops a row with no label.');
            $this->assertArrayHasKey('icon', $row);
            $this->assertArrayHasKey('key', $row);
        }

        // Document order survives array_values(), which is what the bar renders.
        $this->assertSame(
            array_keys($this->sections()->inScopeFor('tenant', $this->agent())),
            array_column(array_values($resolved), 'key')
        );
    }

    /** Ids are built in one place and match the pattern the stylesheet and the script select on. */
    public function test_anchor_ids_carry_the_shared_prefix(): void
    {
        $this->assertSame('hla-section-terms', $this->sections()->anchorId('terms'));

        foreach ($this->sections()->resolve('seller', $this->agent(), $this->allVisible('seller', $this->agent())) as $id => $row) {
            $this->assertMatchesRegularExpression('/^hla-section-[a-z-]+$/', $id);
        }
    }

    // ── 4. It fails loudly ───────────────────────────────────────────────────

    /** A section in scope with no visibility answer is an error, not a hidden section. */
    public function test_a_missing_visibility_answer_is_refused(): void
    {
        $visible = $this->allVisible('buyer', $this->agent());
        unset($visible['representation']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing a visibility answer for: representation');

        $this->sections()->resolve('buyer', $this->agent(), $visible);
    }

    /** A guard for a section that is not in scope is an error too — typo, or wrong role. */
    public function test_a_guard_for_an_out_of_scope_section_is_refused(): void
    {
        $visible = $this->allVisible('landlord', $this->agent());
        $visible['financing'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not in scope: financing');

        $this->sections()->resolve('landlord', $this->agent(), $visible);
    }

    /** A consumer passed an agent-only guard is the same error, and it is worth its own case. */
    public function test_a_consumer_passed_an_agent_only_guard_is_refused(): void
    {
        $visible = $this->allVisible('buyer', HireAgentDetailAudience::AUDIENCE_CONSUMER);
        $visible['referral'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not in scope: referral');

        $this->sections()->resolve('buyer', HireAgentDetailAudience::AUDIENCE_CONSUMER, $visible);
    }

    /** An unknown audience cannot resolve to "applies to nobody". */
    public function test_an_unknown_audience_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Hire Agent section audience [broker]');

        $this->sections()->inScopeFor('buyer', 'broker');
    }

    /** A registry entry with no labels applies to no role and can never render. */
    public function test_a_section_with_no_labels_is_refused(): void
    {
        config(['hire_agent_sections.sections' => [
            ['id' => 'orphan', 'audience' => 'both', 'labels' => []],
        ]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no labels, so it applies to no role');

        $this->sections()->inScopeFor('buyer', $this->agent());
    }

    /** A typo'd audience in the registry matches nothing and must not pass silently. */
    public function test_a_registry_entry_with_an_unknown_audience_is_refused(): void
    {
        config(['hire_agent_sections.sections' => [
            ['id' => 'odd', 'audience' => 'agents', 'labels' => ['buyer' => 'Odd']],
        ]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('section [odd]');

        $this->sections()->inScopeFor('buyer', $this->agent());
    }

    /** A registry entry missing a required key is refused by position. */
    public function test_a_malformed_registry_entry_is_refused(): void
    {
        config(['hire_agent_sections.sections' => [
            ['id' => 'fine', 'audience' => 'both', 'labels' => ['buyer' => 'Fine']],
            ['id' => 'broken', 'labels' => ['buyer' => 'Broken']],
        ]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entry #1 is missing [audience]');

        $this->sections()->inScopeFor('buyer', $this->agent());
    }

    // ── The single-reader contract ───────────────────────────────────────────

    /**
     * Only the resolver reads the registry config.
     *
     * The same rule HireAgentDetailRedesign carries for the flag, and it exists for the same
     * reason: a view or a second class reading the registry would be a second opinion about which
     * sections exist, and the two would drift. The test file itself is exempt — it reads the config
     * to assert the removed sections have no entry, which is the one legitimate second read.
     */
    public function test_only_the_resolver_reads_the_section_registry(): void
    {
        $readers = [];

        foreach ([base_path('app'), base_path('resources/views'), base_path('routes')] as $root) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($items as $item) {
                if (! $item->isFile()) {
                    continue;
                }

                $path = str_replace(base_path() . '/', '', $item->getPathname());

                if ($path === 'app/Support/HireAgent/HireAgentDetailSections.php') {
                    continue;
                }

                if (str_contains((string) file_get_contents($item->getPathname()), 'hire_agent_sections')) {
                    $readers[] = $path;
                }
            }
        }

        $this->assertSame(
            [],
            $readers,
            'Only HireAgentDetailSections may read the section registry. Found: ' . implode(', ', $readers)
        );
    }

    /**
     * Nothing renders from the registry yet.
     *
     * Step 2 is structure alone; the Blade migrations are steps 3 to 5. Expected to change
     * deliberately when they land, like the audience consumer list beside it.
     */
    public function test_no_view_consumes_the_resolver_yet(): void
    {
        $consumers = [];

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if ($item->isFile() && str_contains((string) file_get_contents($item->getPathname()), 'HireAgentDetailSections')) {
                $consumers[] = str_replace(base_path() . '/', '', $item->getPathname());
            }
        }

        $this->assertSame([], $consumers, 'The registry ships as structure; the UI migrations consume it.');
    }
}
