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

    /** The registry's own word for "every viewer". The agent name belongs to the audience service. */
    public const AUDIENCE_BOTH = 'both';

    /**
     * Sections deliberately removed from the redesigned page.
     *
     * Named here ONLY so that an attempt to reintroduce one fails with an explanation instead of
     * the generic "unknown section" error. They are absent from the registry, which is what makes
     * them impossible to render; this list makes the absence self-documenting at the call site.
     */
    public const REMOVED_SECTIONS = ['services', 'compensation'];

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

            if ($section['audience'] === self::AUDIENCE_BOTH) {
                $out[$section['id']] = $section;
                continue;
            }

            if ($section['audience'] === $audience) {
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
     * draft of this class: a section declares 'both' or 'agent', a viewer resolves to 'consumer' or
     * 'agent'. No section is ever 'consumer' — a consumer-facing section is 'both', because agents
     * see it too — and no viewer is ever 'both'. Checking a viewer against the section list
     * rejected every consumer, which the tests caught immediately.
     *
     * The permitted values come from the audience service rather than from config, because it is
     * the thing that produces them. A config list here would be a second place to add a third
     * audience, and one of the two would be forgotten.
     */
    private function assertViewerAudience(string $audience): void
    {
        $permitted = [
            HireAgentDetailAudience::AUDIENCE_CONSUMER,
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
     * An EXTRA answer is a guard for a section that cannot render on this page — a typo, a section
     * scoped to another role, or a leftover from something that was removed. The last of those is
     * why Services and Broker Compensation are named specifically: passing one is not a typo, it is
     * an attempt to reintroduce a section that was deliberately taken out, and it deserves an error
     * that says so rather than "unknown key".
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

        $removed = array_values(array_intersect($extra, self::REMOVED_SECTIONS));

        if ($removed !== []) {
            throw new InvalidArgumentException(
                'These sections were removed from the redesigned Hire Agent detail page and cannot '
                . 'be reintroduced by passing a visibility answer for them: ' . implode(', ', $removed)
                . '. Compensation belongs to the hire agreement workflow and Representation '
                . 'Preferences & Compatibility supersedes Services; see config/hire_agent_sections.php. '
                . 'Legacy rendering of both is unaffected.'
            );
        }

        throw new InvalidArgumentException(
            "Hire Agent sections for role [{$role}] / audience [{$audience}] were given a visibility "
            . 'answer for sections that are not in scope: ' . implode(', ', $extra) . '.'
        );
    }
}
