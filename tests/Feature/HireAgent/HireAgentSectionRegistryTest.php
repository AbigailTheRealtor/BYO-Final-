<?php

namespace Tests\Feature\HireAgent;

use App\Services\HireAgent\HireAgentDetailAudience;
use App\Support\HireAgent\HireAgentDetailSections;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The section registry and its resolver, before anything renders from either.
 *
 * This ships without moving a page on purpose: the registry encodes decisions — which sections
 * exist, and which tier of viewer may read each — and each of those is worth being able to fail on
 * its own, in a file that names it, rather than inside a Blade migration where a failure could just
 * as easily mean the markup.
 *
 * THE DISTINCTION THE WHOLE FILE IS ORGANISED AROUND: public website visibility is not private bid
 * visibility. This page is a listing anyone can open AND the host of a private proposal workflow,
 * so "logged in or not" cannot decide what it shows. Three tiers do:
 *
 *   public      — anyone, including anonymous. The request itself.
 *   owner       — the client evaluating proposals. Adds Services and Broker Compensation, the
 *                 material a bid is measured against.
 *   agent       — an agent with a relationship to the listing. Adds Referral & Cooperation Terms
 *                 and Agent Credentials, which are agent-to-agent business.
 *
 * STRICTLY NESTED — public ⊂ owner ⊂ agent — and the tests below assert that as a RELATIONSHIP
 * rather than as three independent lists, because three lists can drift and a relationship cannot.
 *
 * SERVICES AND BROKER COMPENSATION ARE NOT IN THIS REGISTRY AND MUST NOT COME BACK. They are
 * negotiation terms an agent proposes and the client accepts, rejects or counters — not listing
 * detail — so they belong to the bid workflow and to no audience tier of this page. They render on
 * the per-bid cards and in the "Private Compensation & Agreement Terms" modal, narrowed
 * server-side by HireAgentProposalAccess; that surface is untouched by this registry. Two bodies
 * of data with similar names, and conflating them is how one of them ended up public.
 * HireAgentListingBidSeparationTest holds both halves of that line.
 *
 * THE 'participant' TIER THEREFORE HAS NO MEMBERS. The machinery is kept as framework for a future
 * owner-and-agents section, so the tests below assert the tier's BEHAVIOUR — that it is empty, and
 * that the vocabulary still validates — rather than pretending it has content.
 */
class HireAgentSectionRegistryTest extends TestCase
{
    private const ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    /** Public sections every role shows to every viewer, anonymous included. */
    private const UNIVERSAL_PUBLIC_SECTIONS = [
        'listing-details',
        'property',
        'terms',
        'additional-details',
        'representation',
        'role-info',
    ];

    /**
     * What the owner tier adds — EMPTY, and deliberately still named.
     *
     * Services and Compensation lived here until they were recognised as negotiation terms and
     * removed from the registry outright. The constant survives as the seam a future
     * owner-and-agents section would use, and the tests that consume it now assert emptiness,
     * which is what makes "the owner page equals the public page" a checked property rather than
     * an accident nobody would notice changing.
     */
    private const PARTICIPANT_SECTIONS = [];

    /** What the agent tier adds on top: agent-to-agent business. */
    private const AGENT_ONLY_SECTIONS = ['referral', 'agent-credentials'];

    private function sections(): HireAgentDetailSections
    {
        return app(HireAgentDetailSections::class);
    }

    private function public_(): string
    {
        return HireAgentDetailAudience::AUDIENCE_PUBLIC;
    }

    private function owner(): string
    {
        return HireAgentDetailAudience::AUDIENCE_OWNER;
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

    private function keysFor(string $role, string $audience): array
    {
        return array_keys($this->sections()->inScopeFor($role, $audience));
    }

    // ── 1. The agreed architecture ───────────────────────────────────────────

    /**
     * The PUBLIC page, per role, in document order.
     *
     * Written out rather than derived. This IS the agreed architecture, so the assertion has to
     * state it independently of the file it checks — otherwise a change to the registry would move
     * the expectation with it and the test would agree with anything.
     *
     * Note what is absent: no Services, no Broker Compensation. That is the public-website half of
     * the rule, and it is the half a single auth check never expressed.
     */
    public function test_the_public_section_set_is_the_agreed_architecture(): void
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
                $this->keysFor($role, $this->public_()),
                "{$role}: the public section set or its order has drifted from the agreed architecture."
            );
        }
    }

    /**
     * The owner page is the public page plus Services and Broker Compensation, and nothing else.
     *
     * A SET RELATIONSHIP, NOT A CONCATENATION, and the difference is forced by the page rather than
     * chosen. An earlier draft asserted `owner === public + participant` as an APPENDED list, which
     * required grouping the tiers at the end of the registry. Array order is document order, and
     * with the redesign off these sections render in the order the four views have always rendered
     * them — an order HireAgentSectionCardDomEquivalenceTest pins verbatim per role. Grouping the
     * tiers would have meant physically moving blocks in the views, changing the legacy order and
     * breaking that pin. So Services and Compensation sit interleaved among the public sections,
     * where they have always sat, and the relationship is asserted as a set difference.
     *
     * Document order is asserted separately, below.
     */
    public function test_the_owner_page_is_the_public_page_plus_the_participant_sections(): void
    {
        foreach (self::ROLES as $role) {
            $public = $this->keysFor($role, $this->public_());
            $owner  = $this->keysFor($role, $this->owner());

            $this->assertSame(
                self::PARTICIPANT_SECTIONS,
                array_values(array_diff($owner, $public)),
                "{$role}: the owner tier must add exactly the participant sections."
            );
            $this->assertSame([], array_values(array_diff($public, $owner)), "{$role}: owner lost a public section.");
        }
    }

    /** And the agent page is the owner page plus the two agent-only sections, and nothing else. */
    public function test_the_agent_page_is_the_owner_page_plus_the_agent_sections(): void
    {
        foreach (self::ROLES as $role) {
            $owner = $this->keysFor($role, $this->owner());
            $agent = $this->keysFor($role, $this->agent());

            $this->assertSame(
                self::AGENT_ONLY_SECTIONS,
                array_values(array_diff($agent, $owner)),
                "{$role}: the agent tier must add exactly the agent-only sections."
            );
            $this->assertSame([], array_values(array_diff($owner, $agent)), "{$role}: agent lost an owner section.");
        }
    }

    /**
     * The full document order, per role, written out.
     *
     * The registry's order IS the page's order in both flag states, so it is pinned here rather
     * than left implicit in the tier assertions above. It matches landlord's already-migrated nav
     * exactly — listing-details, property, terms, services, additional-details, representation,
     * compensation, referral, role-info — which is what makes one registry serve four views whose
     * legacy order was fixed long before this registry existed.
     */
    public function test_the_full_document_order_is_the_legacy_order(): void
    {
        $expected = [
            'seller'   => ['listing-details', 'property', 'terms', 'financing', 'additional-details', 'representation', 'referral', 'role-info', 'agent-credentials'],
            'buyer'    => ['listing-details', 'property', 'terms', 'financing', 'additional-details', 'representation', 'referral', 'role-info', 'agent-credentials'],
            'landlord' => ['listing-details', 'property', 'terms', 'additional-details', 'representation', 'referral', 'role-info', 'agent-credentials'],
            'tenant'   => ['listing-details', 'property', 'terms', 'pre-screening', 'additional-details', 'representation', 'referral', 'role-info', 'agent-credentials'],
        ];

        foreach ($expected as $role => $keys) {
            $this->assertSame($keys, $this->keysFor($role, $this->agent()), "{$role}: document order drifted.");
        }
    }

    /**
     * The tiers are strictly nested, asserted directly.
     *
     * The property the whole design rests on: nothing is shown to a narrower audience and withheld
     * from a wider one. A registry that broke it would give some viewer a page with a hole in it,
     * and the two relationship tests above would still pass if the ordering happened to work out.
     */
    public function test_each_tier_contains_the_one_below_it(): void
    {
        foreach (self::ROLES as $role) {
            $public = $this->keysFor($role, $this->public_());
            $owner  = $this->keysFor($role, $this->owner());
            $agent  = $this->keysFor($role, $this->agent());

            $this->assertSame([], array_values(array_diff($public, $owner)), "{$role}: owner lost a public section.");
            $this->assertSame([], array_values(array_diff($owner, $agent)), "{$role}: agent lost an owner section.");
        }
    }

    /** The sections shared by every role. */
    public function test_the_universal_sections_apply_to_all_four_roles(): void
    {
        foreach (array_merge(self::UNIVERSAL_PUBLIC_SECTIONS, self::PARTICIPANT_SECTIONS, self::AGENT_ONLY_SECTIONS) as $key) {
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
            $this->assertNotContains('financing', $this->keysFor($role, $this->agent()), "{$role}: only a sale is financed.");
        }

        foreach (['seller', 'buyer', 'landlord'] as $role) {
            $this->assertNotContains('pre-screening', $this->keysFor($role, $this->agent()), "{$role}: pre-screening is tenant-only.");
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
                $resolved = $this->sections()->resolve($role, $this->agent(), $this->allVisible($role, $this->agent()));

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
        $this->assertSame('Purchasing Terms', $resolved[$this->sections()->anchorId('terms')]['label'], 'Nothing else moved.');
    }

    // ── 2. The public page withholds the private sections ────────────────────

    /**
     * THE ASSERTION THIS FILE EXISTS FOR: no tier of this page carries a negotiation term.
     *
     * Services and Broker Compensation are not withheld from the public — they are ABSENT, from
     * the registry and therefore from every audience. Asserted against the registry itself rather
     * than against one resolved page, because a section that is gone cannot be tested by resolving
     * it, and the failure this guards against is somebody adding it back at any tier at all.
     *
     * The per-audience half of this rule — that no rendered listing page shows either subject to a
     * guest, an owner or a qualifying agent — is HireAgentListingBidSeparationTest's.
     */
    public function test_negotiation_terms_are_absent_from_the_registry_at_every_tier(): void
    {
        foreach (['services', 'compensation'] as $negotiationTerm) {
            $this->assertSame(
                [],
                $this->sections()->rolesFor($negotiationTerm),
                "[{$negotiationTerm}] is a negotiation term and must not be scoped to any role."
            );

            foreach (self::ROLES as $role) {
                foreach ([$this->public_(), $this->owner(), $this->agent()] as $audience) {
                    $this->assertNotContains(
                        $negotiationTerm,
                        $this->keysFor($role, $audience),
                        "{$role}/{$audience}: [{$negotiationTerm}] is in scope and must not be."
                    );
                }
            }
        }
    }

    /** And no agent-only section either. */
    public function test_the_public_audience_never_receives_an_agent_section(): void
    {
        foreach (self::ROLES as $role) {
            $resolved = $this->sections()->resolve($role, $this->public_(), $this->allVisible($role, $this->public_()));

            foreach (self::AGENT_ONLY_SECTIONS as $agentOnly) {
                $this->assertArrayNotHasKey(
                    $this->sections()->anchorId($agentOnly),
                    $resolved,
                    "{$role}: a public viewer received [{$agentOnly}]."
                );
            }
        }
    }

    /**
     * The participant tier is EMPTY, and the owner page therefore equals the public page.
     *
     * The complement of the assertion above, and the reason it is worth stating rather than
     * leaving implicit: the tier's machinery is retained as framework, so "no section declares
     * participant" is a property that could silently stop holding. When a future section does
     * declare it, this test is the one that fails and tells its author to update the expectation
     * deliberately rather than discovering the owner page changed by accident.
     */
    public function test_the_participant_tier_is_empty_so_owner_equals_public(): void
    {
        foreach (self::ROLES as $role) {
            $this->assertSame(
                $this->keysFor($role, $this->public_()),
                $this->keysFor($role, $this->owner()),
                "{$role}: with no participant sections the owner page must equal the public page."
            );
        }

        foreach (self::ROLES as $role) {
            $resolved = $this->sections()->resolve($role, $this->owner(), $this->allVisible($role, $this->owner()));

            foreach (self::PARTICIPANT_SECTIONS as $private) {
                $this->assertArrayHasKey(
                    $this->sections()->anchorId($private),
                    $resolved,
                    "{$role}: the owner evaluates bids against [{$private}] and must receive it."
                );
            }
        }
    }

    /** The owner is NOT given the agent-to-agent appendix. The middle tier is a real tier. */
    public function test_the_owner_does_not_receive_the_agent_only_sections(): void
    {
        foreach (self::ROLES as $role) {
            $resolved = $this->sections()->resolve($role, $this->owner(), $this->allVisible($role, $this->owner()));

            foreach (self::AGENT_ONLY_SECTIONS as $agentOnly) {
                $this->assertArrayNotHasKey(
                    $this->sections()->anchorId($agentOnly),
                    $resolved,
                    "{$role}: the owner received [{$agentOnly}], which is agent-to-agent business."
                );
            }
        }
    }

    /** An agent receives everything: participant sections and the agent-only pair. */
    public function test_the_agent_receives_every_section(): void
    {
        foreach (self::ROLES as $role) {
            $resolved = $this->sections()->resolve($role, $this->agent(), $this->allVisible($role, $this->agent()));

            foreach (array_merge(self::PARTICIPANT_SECTIONS, self::AGENT_ONLY_SECTIONS) as $key) {
                $this->assertArrayHasKey(
                    $this->sections()->anchorId($key),
                    $resolved,
                    "{$role}: the agent audience must receive [{$key}]."
                );
            }
        }
    }

    // ── 3. Scope, audience and content compose ───────────────────────────────

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
     * that neither consumer re-derives anything — the property that makes a nav/section
     * disagreement structurally impossible rather than merely tested for.
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

        $this->assertSame(
            $this->keysFor('tenant', $this->agent()),
            array_column(array_values($resolved), 'key'),
            'Document order must survive array_values(), which is what the bar renders.'
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

    /**
     * A guard for a RETIRED section is refused — as out of scope, not as withheld.
     *
     * This test used to compute Services for a public viewer and assert the refusal named the
     * tier ("may not read"), because the section was real and only the audience stood between it
     * and the page. Both are now gone from the registry entirely, so the same call is refused by
     * the other branch: not in scope, for any audience.
     *
     * WORTH KEEPING RATHER THAN DELETING. A view that still passed a `services` guard after the
     * removal would be a view that had not finished migrating, and this is what makes that a loud
     * failure at the resolver rather than a silently ignored array key.
     */
    public function test_a_guard_for_a_retired_negotiation_section_is_refused(): void
    {
        foreach (['services', 'compensation'] as $retired) {
            foreach ([$this->public_(), $this->owner(), $this->agent()] as $audience) {
                $visible = $this->allVisible('buyer', $audience);
                $visible[$retired] = true;

                try {
                    $this->sections()->resolve('buyer', $audience, $visible);
                    $this->fail("Computing the retired [{$retired}] for [{$audience}] should have been refused.");
                } catch (InvalidArgumentException $e) {
                    $this->assertStringContainsString($retired, $e->getMessage());
                    $this->assertStringContainsString('not in scope', $e->getMessage());
                }
            }
        }
    }

    /** Same for an agent-only section computed for the owner. */
    public function test_computing_an_agent_section_for_the_owner_audience_is_refused(): void
    {
        $visible = $this->allVisible('buyer', $this->owner());
        $visible['referral'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may not read: referral');

        $this->sections()->resolve('buyer', $this->owner(), $visible);
    }

    /** A guard for a section that does not apply to this role at all is the other error. */
    public function test_a_guard_for_a_section_outside_this_role_is_refused(): void
    {
        $visible = $this->allVisible('landlord', $this->agent());
        $visible['financing'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not in scope: financing');

        $this->sections()->resolve('landlord', $this->agent(), $visible);
    }

    /** An unknown audience cannot resolve to "applies to nobody". */
    public function test_an_unknown_viewer_audience_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Hire Agent section audience [broker]');

        $this->sections()->inScopeFor('buyer', 'broker');
    }

    /**
     * 'participant' is a SECTION tier, not a viewer tier, and passing it as a viewer is refused.
     *
     * The two vocabularies overlap in two of three words, which is exactly the shape that invites
     * a mix-up — and did, on the first run of this suite.
     */
    public function test_a_section_tier_is_not_a_valid_viewer_audience(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Hire Agent section audience [participant]');

        $this->sections()->inScopeFor('buyer', 'participant');
    }

    /** A registry entry with no labels applies to no role and can never render. */
    public function test_a_section_with_no_labels_is_refused(): void
    {
        config(['hire_agent_sections.sections' => [
            ['id' => 'orphan', 'audience' => 'public', 'labels' => []],
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
            ['id' => 'fine', 'audience' => 'public', 'labels' => ['buyer' => 'Fine']],
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
     * The same rule HireAgentDetailRedesign carries for the flag, for the same reason: a view or a
     * second class reading the registry would be a second opinion about which sections exist, and
     * the two would drift.
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
     * The set of views resolving sections is KNOWN, and grows one migration at a time.
     *
     * It shipped as an empty list when the registry was structure alone. Buyer is the first
     * consumer, added deliberately with its migration; seller, landlord and tenant follow in their
     * own changes. A fourth entry appearing without that decision is the signal, not a failure to
     * fix by widening this list — the same contract HireAgentDetailRedesignFlagTest holds for the
     * rollout flag.
     */
    public function test_the_resolver_consumers_are_exactly_the_migrated_views(): void
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

        sort($consumers);

        $this->assertSame(
            [
                'resources/views/hire_buyer_agent/view.blade.php',
                'resources/views/hire_landlord_agent/view.blade.php',
            ],
            $consumers,
            'The set of views resolving sections must stay known.'
        );
    }

    /**
     * No view tests the audience itself.
     *
     * The resolver exists so a view never has to. A Blade file comparing `$hlaAudience` to a tier
     * name would be a second opinion about a rule that already has an owner — and a nav bar is
     * where such a drift becomes a disclosure, because the bar names the section it links to.
     * Asserted at source across every view, not just the migrated ones.
     */
    public function test_no_view_tests_the_audience_value(): void
    {
        $offenders = [];

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $src = (string) file_get_contents($item->getPathname());

            // A comparison of the audience variable against anything at all.
            if (preg_match('/\$hlaAudience\s*(===|!==|==|!=)/', $src)) {
                $offenders[] = str_replace(base_path() . '/', '', $item->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A view compared the audience directly. Pass it to the resolver and read the result.'
        );
    }
}
