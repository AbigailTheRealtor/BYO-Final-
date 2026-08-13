<?php

namespace App\Support\HireAgent;

use App\Services\HireAgent\HireAgentDetailAudience;
use InvalidArgumentException;

/**
 * The single reader of the section registry, and the one place a section's visibility is decided.
 *
 * WHAT PROBLEM THIS SOLVES
 * ------------------------
 * The redesigned detail page has to answer two questions about every section — "does the bar offer
 * it" and "does the card render" — and they must never disagree. A bar entry pointing at a section
 * that did not render is a dead link; a section the bar declines to mention reads as withheld; and
 * an entry naming a section the viewer may not see leaks that section's existence and its name in
 * the most prominent place on the page.
 *
 * Landlord's approach was to compute one boolean per section and hand-copy it into the nav builder
 * character for character, with a test asserting the two agree. That works, and it is what made
 * this class possible to write — but it does not survive being multiplied. Four role views with
 * two audiences is eight lists to keep in agreement, and "roughly the same expression" is how a
 * nav ends up linking to nothing.
 *
 * So the mirroring stops. The caller supplies ONE visibility answer per section; this class folds
 * in role scope and audience and returns the finished set. The bar renders it and each card asks
 * it. There is no second expression to drift from because there is no second expression.
 *
 * IT MAKES NO AUTHORIZATION DECISION AND CANNOT SEE THE VIEWER. `$audience` arrives as a resolved
 * string from HireAgentDetailAudience, the same way x-hire-agent.detail-section receives the
 * redesign flag as a resolved boolean rather than consulting config. A class that could read a
 * user would be a second opinion about a rule that already has an owner, and the M4 hero is the
 * standing proof that a second opinion in a presenter defeats a gate in a view.
 *
 * IT DOES NOT KNOW WHAT ANY SECTION CONTAINS. Whether a section has content is the caller's
 * question — it is a dense read of role-specific meta that could not be generalised without moving
 * half of four views in here. This class is told the answer.
 *
 * WHY IT THROWS RATHER THAN DEFAULTING
 * ------------------------------------
 * A missing visibility answer is a programming error, not a data condition, and it is caught on the
 * FIRST render in any environment: the set of sections in scope depends only on role and audience,
 * never on the listing, so an omission cannot hide behind unusual data and reach production
 * unnoticed. Defaulting a missing answer to false would turn that loud failure into a section that
 * silently never appears — which is precisely the invisible drift this class exists to remove. The
 * config source binding in CriteriaGeographyRepository throws for the same reason and the note
 * there records why: a silent fallthrough "looked exactly like success".
 *
 * @see HireAgentDetailAudience — who counts as an agent, decided server-side.
 * @see config/hire_agent_sections.php — the registry itself, and why Services and Broker
 *      Compensation are absent from it rather than disabled in it.
 */
class HireAgentDetailSections
{
    /**
     * The anchor id prefix, declared once.
     *
     * `hla-` predates the multi-role architecture — it is landlord's initials — and it is kept
     * because the framework stylesheet selects on `[id^="hla-section-"]` for scroll offset, the
     * nav behaviour script resolves these ids, and three suites match the pattern. Renaming it
     * would be a rename across all of that for no reader-visible gain. It is Hire Agent's prefix
     * now, not landlord's.
     */
    public const ID_PREFIX = 'hla-section-';

    /**
     * The audience a SECTION may declare — the lowest viewer tier that may read it.
     *
     * These are the registry's words, not the audience service's. See assertViewerAudience() for
     * why the two vocabularies are separate and what happened when they were not.
     */
    public const AUDIENCE_PUBLIC      = 'public';
    public const AUDIENCE_PARTICIPANT = 'participant';

    /**
     * Which VIEWER tiers each SECTION tier admits.
     *
     * The whole visibility rule, in one table, because a rule spread across three conditionals is
     * a rule with three places to get it wrong. Read it as "a section declaring the row is visible
     * to the viewers in it".
     *
     * THE TIERS ARE NESTED — public ⊂ owner ⊂ agent — so every row contains the rows below it. An
     * agent reads everything an owner reads; an owner reads everything the public reads. That
     * property is what lets each audience's page be a prefix of the next one's, and a table that
     * broke it would produce a page with a hole in the middle for one class of viewer.
     */
    private const VISIBLE_TO = [
        self::AUDIENCE_PUBLIC => [
            HireAgentDetailAudience::AUDIENCE_PUBLIC,
            HireAgentDetailAudience::AUDIENCE_OWNER,
            HireAgentDetailAudience::AUDIENCE_AGENT,
        ],
        self::AUDIENCE_PARTICIPANT => [
            HireAgentDetailAudience::AUDIENCE_OWNER,
            HireAgentDetailAudience::AUDIENCE_AGENT,
        ],
        HireAgentDetailAudience::AUDIENCE_AGENT => [
            HireAgentDetailAudience::AUDIENCE_AGENT,
        ],
    ];

    /**
     * The sections to render, in document order, keyed by anchor id.
     *
     * KEYED BY ID AND ORDERED AT ONCE, because both consumers need one of those properties and
     * neither should get its own copy of the answer:
     *
     *   · the nav takes array_values($sections) — each row already carries the `id` and `label`
     *     that x-viho.section-nav reads, in reading order;
     *   · each section card asks isset($sections['hla-section-financing']).
     *
     * One array, both uses, no possibility of disagreement.
     *
     * @param  string               $role      seller|buyer|landlord|tenant
     * @param  string               $audience  resolved by HireAgentDetailAudience — never read here
     * @param  array<string, bool>  $visible   short section id => does this section have content
     * @param  array<string, string> $labelOverrides short section id => heading for THIS listing,
     *                              for the one label that is a fact about a row rather than a role
     *                              (role-info flips to "Agent's Info" when the owner is an agent)
     * @return array<string, array{id: string, key: string, label: string, icon: ?string}>
     */
    public function resolve(string $role, string $audience, array $visible, array $labelOverrides = []): array
    {
        $this->assertViewerAudience($audience);

        $inScope = $this->inScopeFor($role, $audience);

        $this->assertVisibilityCoversScope($role, $audience, $inScope, $visible);

        $resolved = [];

        foreach ($inScope as $key => $section) {
            if ($visible[$key] !== true) {
                continue;
            }

            $resolved[self::ID_PREFIX . $key] = [
                'id'    => self::ID_PREFIX . $key,
                'key'   => $key,
                'label' => $labelOverrides[$key] ?? $section['labels'][$role],
                'icon'  => $section['icon'] ?? null,
            ];
        }

        return $resolved;
    }

    /**
     * Resolve from a guard map covering every section this ROLE has, whatever the audience.
     *
     * THE ENTRY POINT VIEWS USE, and the reason it exists rather than views calling resolve()
     * directly: resolve() demands guards for exactly the sections in scope for one audience, so a
     * caller would have to know which audience it was serving in order to decide which guards to
     * compute. In a Blade file that is `@if ($audience === 'agent')`, which is audience logic in a
     * view — the one thing this whole design forbids.
     *
     * So the view computes every guard its role has, unconditionally and audience-blind, and this
     * method drops the ones the viewer's tier does not admit. Computing a guard reveals nothing: it
     * is a read of the listing's own meta that emits no markup, and the withheld sections never
     * reach the returned array.
     *
     * THE LOUD FAILURE SURVIVES, and is if anything stricter: the map must cover every section
     * scoped to the role — including ones only an agent will ever see — so a section cannot be
     * added to the registry and left without a guard by a view that happens to be rendering for a
     * narrower audience that day.
     *
     * @param  array<string, bool>   $guards         short section id => has content, for EVERY
     *                                               section this role has
     * @param  array<string, string> $labelOverrides see resolve()
     * @return array<string, array{id: string, key: string, label: string, icon: ?string}>
     */
    public function resolveForRole(string $role, string $audience, array $guards, array $labelOverrides = []): array
    {
        $this->assertViewerAudience($audience);

        $expected = $this->sectionKeysForRole($role);
        $given    = array_keys($guards);

        $missing = array_values(array_diff($expected, $given));
        $extra   = array_values(array_diff($given, $expected));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                "Hire Agent sections for role [{$role}] are missing a visibility answer for: "
                . implode(', ', $missing) . '. Every section this role has needs one, including '
                . 'sections only a wider audience will see — otherwise a section can be added to '
                . 'the registry and go unguarded in a view that happens to render for a narrower '
                . 'audience.'
            );
        }

        if ($extra !== []) {
            throw new InvalidArgumentException(
                "Hire Agent sections for role [{$role}] were given a visibility answer for sections "
                . 'this role does not have: ' . implode(', ', $extra) . '.'
            );
        }

        // Narrow to the tier, then hand the exact map resolve() demands. Going through resolve()
        // rather than duplicating its body is what keeps one implementation of "is it visible".
        $inScope = array_keys($this->inScopeFor($role, $audience));

        return $this->resolve($role, $audience, array_intersect_key($guards, array_flip($inScope)), $labelOverrides);
    }

    /**
     * Every section key scoped to this role, in document order, ignoring audience entirely.
     *
     * The set of guards a role view owes. Distinct from inScopeFor(), which also narrows by tier.
     */
    public function sectionKeysForRole(string $role): array
    {
        $out = [];

        foreach ($this->registry() as $section) {
            if (isset($section['labels'][$role])) {
                $out[] = $section['id'];
            }
        }

        return $out;
    }

    /**
     * The section keys this role and audience may render, in document order, before content is
     * considered.
     *
     * PUBLIC because it is what a test needs to enumerate the contract without inventing a
     * visibility map, and what a caller needs to know which guards it owes. It is NOT a
     * visibility answer — a section in scope may still have nothing in it.
     *
     * @return array<string, array{id: string, audience: string, icon: ?string, labels: array<string, string>}>
     */
    public function inScopeFor(string $role, string $audience): array
    {
        $this->assertViewerAudience($audience);

        $out = [];

        foreach ($this->registry() as $section) {
            // Role scope IS the label map. A section with no label for this role does not apply to
            // it — see the registry's note on why there is no separate role list.
            if (! isset($section['labels'][$role])) {
                continue;
            }

            if (in_array($audience, self::VISIBLE_TO[$section['audience']] ?? [], true)) {
                $out[$section['id']] = $section;
            }
        }

        return $out;
    }

    /** Every role a section is scoped to, in registry order. Used by the contract tests. */
    public function rolesFor(string $sectionKey): array
    {
        foreach ($this->registry() as $section) {
            if ($section['id'] === $sectionKey) {
                return array_keys($section['labels']);
            }
        }

        return [];
    }

    /** The full anchor id for a section key. One place builds these. */
    public function anchorId(string $sectionKey): string
    {
        return self::ID_PREFIX . $sectionKey;
    }

    // ── internals ────────────────────────────────────────────────────────────

    /**
     * The registry, validated on the way out.
     *
     * Validation lives here rather than in a command or a test because a malformed entry is
     * unrenderable in a way that is hard to see: a section with no labels applies to no role and
     * simply never appears, and a typo'd audience matches nothing and does the same. Both look
     * exactly like "that section isn't built yet".
     */
    private function registry(): array
    {
        $sections = config('hire_agent_sections.sections', []);

        if (! is_array($sections)) {
            throw new InvalidArgumentException('The Hire Agent section registry must be an array.');
        }

        foreach ($sections as $i => $section) {
            foreach (['id', 'audience', 'labels'] as $required) {
                if (! isset($section[$required])) {
                    throw new InvalidArgumentException(
                        "Hire Agent section registry entry #{$i} is missing [{$required}]."
                    );
                }
            }

            $this->assertSectionAudience($section['audience'], "section [{$section['id']}]");

            if (! is_array($section['labels']) || $section['labels'] === []) {
                throw new InvalidArgumentException(
                    "Hire Agent section [{$section['id']}] has no labels, so it applies to no role. "
                    . 'Role scope is the label map; a section with none can never render.'
                );
            }
        }

        return $sections;
    }

    /**
     * Validate a VIEWER audience — the value HireAgentDetailAudience produced.
     *
     * NOT THE SAME VOCABULARY AS A SECTION'S, and conflating them was a real bug in the first
     * draft of this class. A section declares the lowest tier that may read it — 'public',
     * 'participant', 'agent'. A viewer resolves to the tier they are in — 'public', 'owner',
     * 'agent'. The two overlap in two words and differ in the middle one, which is exactly the
     * shape that invites a mix-up: no section is ever 'owner' (a section for the owner is
     * 'participant', because qualifying agents read it too) and no viewer is ever 'participant'
     * (it names a pair of tiers, not one). Checking a viewer against the section list rejected
     * every non-agent, which the tests caught on the first run.
     *
     * The permitted values come from the audience service rather than from config, because it is
     * the thing that produces them. A config list here would be a second place to add a fourth
     * tier, and one of the two would be forgotten.
     */
    private function assertViewerAudience(string $audience): void
    {
        $permitted = [
            HireAgentDetailAudience::AUDIENCE_PUBLIC,
            HireAgentDetailAudience::AUDIENCE_OWNER,
            HireAgentDetailAudience::AUDIENCE_AGENT,
        ];

        if (! in_array($audience, $permitted, true)) {
            throw new InvalidArgumentException(
                "Unknown Hire Agent section audience [{$audience}] in resolve(). Permitted: "
                . implode(', ', $permitted) . '.'
            );
        }
    }

    /** Validate the audience a registry entry declares. See the note above for why this differs. */
    private function assertSectionAudience(string $audience, string $context): void
    {
        $permitted = config('hire_agent_sections.section_audiences', []);

        if (! is_array($permitted) || ! in_array($audience, $permitted, true)) {
            throw new InvalidArgumentException(
                "Unknown Hire Agent section audience [{$audience}] in {$context}. Permitted: "
                . implode(', ', is_array($permitted) ? $permitted : []) . '.'
            );
        }
    }

    /**
     * The caller must answer for exactly the sections in scope — no fewer, no more.
     *
     * BOTH DIRECTIONS ARE ERRORS AND THEY ARE DIFFERENT ERRORS.
     *
     * A MISSING answer is a section that was put in the registry and never given a guard. Left to
     * default it would never render, and nothing would say so.
     *
     * An EXTRA answer is a guard for a section that cannot render for THIS role and THIS audience —
     * a typo, a section scoped to another role, or a section the viewer's tier does not admit. The
     * last of those is the one worth naming: passing a `services` guard while resolving for the
     * public audience is not a typo, it is an attempt to compute a participant-only section for a
     * viewer who may not read it, and the message says so rather than "unknown key".
     *
     * AN EARLIER DRAFT NAMED SERVICES AND COMPENSATION AS PERMANENTLY REMOVED and refused them
     * outright. That was wrong about the private page: they are participant sections, not deleted
     * ones. The refusal survives in the narrower and more useful form below, keyed on the tier
     * rather than on a list of section names.
     */
    private function assertVisibilityCoversScope(string $role, string $audience, array $inScope, array $visible): void
    {
        $expected = array_keys($inScope);
        $given    = array_keys($visible);

        $missing = array_values(array_diff($expected, $given));
        $extra   = array_values(array_diff($given, $expected));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                "Hire Agent sections for role [{$role}] / audience [{$audience}] are missing a "
                . 'visibility answer for: ' . implode(', ', $missing) . '. Every section in scope '
                . 'needs one — a defaulted section would silently never render.'
            );
        }

        if ($extra === []) {
            return;
        }

        // Is this section real, but withheld from this viewer's tier? That is a different mistake
        // from a typo, and it is the one with a disclosure behind it, so it gets its own message.
        $withheld = [];

        foreach ($extra as $key) {
            $rolesFor = $this->rolesFor($key);

            if ($rolesFor !== [] && in_array($role, $rolesFor, true)) {
                $withheld[] = $key;
            }
        }

        if ($withheld !== []) {
            throw new InvalidArgumentException(
                "Hire Agent sections for role [{$role}] were given a visibility answer for sections "
                . 'the [' . $audience . '] audience may not read: ' . implode(', ', $withheld)
                . '. These exist for this role but sit at a narrower tier — see the audience notes '
                . 'in config/hire_agent_sections.php. Resolve them for the audience that may read '
                . 'them rather than computing them for one that may not.'
            );
        }

        throw new InvalidArgumentException(
            "Hire Agent sections for role [{$role}] / audience [{$audience}] were given a visibility "
            . 'answer for sections that are not in scope: ' . implode(', ', $extra) . '.'
        );
    }
}
