<?php

namespace Tests\Unit\AskAi;

use App\Support\AskAi\AskAiFieldApplicability;
use App\Support\AskAi\AskAiFieldDisposition as D;
use App\Support\AskAi\AskAiPropertyTypeResolver;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use PHPUnit\Framework\TestCase;

/**
 * The schema-drift contract: every canonical field must have an explicit Ask AI
 * disposition, and SILENCE MUST FAIL.
 *
 * This is the test that protects the work rather than the test that describes it. A
 * developer adding a public listing field next quarter gets a red build naming their
 * field, instead of a field nobody can ask about and nothing that says so.
 */
class AskAiFieldDispositionContractTest extends TestCase
{
    public function test_every_public_canonical_field_has_a_disposition(): void
    {
        $missing = D::undispositioned();

        $message = '';
        foreach ($missing as $role => $fields) {
            $message .= "\n  {$role}:\n    - " . implode("\n    - ", $fields);
        }

        $this->assertSame(
            [],
            $missing,
            "Public Ask-AI-eligible fields with NO disposition. Each must either gain a "
            . "deterministic question (or join a composite's `covers`), or be added to "
            . "AskAiFieldDisposition::DELIBERATELY_NOT_ASKED with a reason:{$message}"
        );
    }

    public function test_every_deliberate_exclusion_names_a_real_public_field(): void
    {
        // A stale exclusion is as bad as a missing one: it makes the contract look
        // satisfied for a field that no longer exists or is no longer public.
        $bad = [];

        foreach (D::DELIBERATELY_NOT_ASKED as $key => $reason) {
            [$role, $field] = array_pad(explode('.', $key, 2), 2, null);

            if (!isset(AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role][$field])) {
                $bad[] = "{$key} is not a canonical field for that role";
                continue;
            }

            if (SnapshotFactVisibility::classify($field, $role) !== SnapshotFactVisibility::PUBLIC_ALLOWED) {
                $bad[] = "{$key} is not PUBLIC_ALLOWED — it is already excluded by visibility, so this entry is redundant";
            }

            if (trim($reason) === '') {
                $bad[] = "{$key} has no reason";
            }
        }

        $this->assertSame([], $bad, implode("\n", $bad));
    }

    public function test_the_owner_only_answerable_category_is_still_empty(): void
    {
        // Audited and deliberately not built — see the class docblock. This test is what
        // stops the category filling up without the second visibility context that would
        // make it safe.
        $this->assertSame(
            [],
            array_filter(
                array_merge(D::forRole('seller'), D::forRole('landlord')),
                static fn (string $d): bool => $d === D::ANSWERABLE_OWNER_ONLY
            ),
            'ANSWERABLE_OWNER_ONLY is reserved. Populating it requires the owner visibility '
            . 'context, which this work did not build.'
        );
    }

    public function test_a_composite_covers_only_fields_it_can_actually_reach(): void
    {
        // `covers` is a contract, not a comment: a field named there is reported as
        // answered, so naming one the entry cannot read would be false coverage.
        $bad = [];

        foreach (\App\Services\AskAi\AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $reachable = array_merge(
                [$entry['source_path'] ?? null],
                (array) ($entry['supporting_paths'] ?? [])
            );

            foreach ((array) ($entry['covers'] ?? []) as $covered) {
                if (!in_array($covered, $reachable, true)) {
                    $bad[] = "{$id} claims to cover {$covered}, which is neither its source nor a supporting path";
                }
            }
        }

        $this->assertSame([], $bad, implode("\n", $bad));
    }

    // ── Role × property type ────────────────────────────────────────────────
    //
    // The role-level checks above let a field count as answered because SOME entry covers it,
    // even one admitted only for Residential — so a Commercial listing could hold a public fact
    // no question could reach. These checks run per property type against
    // AskAiFieldApplicability, which records the wizard's own Blade gating.

    public function test_every_public_property_field_declares_which_types_collect_it(): void
    {
        $missing = [];
        foreach (array_keys(AskAiFieldApplicability::ROLE_TYPES) as $role) {
            foreach (D::forRole($role) as $field => $disposition) {
                if ($disposition === D::ANSWERABLE_PUBLIC && AskAiFieldApplicability::for($role, $field) === null) {
                    $missing[] = "{$role}.{$field}";
                }
            }
        }

        $this->assertSame([], $missing, "Add these to AskAiFieldApplicability::MAP with their Blade evidence:\n" . implode("\n", $missing));
    }

    public function test_no_applicability_row_names_a_field_that_does_not_exist(): void
    {
        $stale = [];
        foreach (AskAiFieldApplicability::MAP as $role => $fields) {
            foreach (array_keys($fields) as $field) {
                if (!isset(AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role][$field])) {
                    $stale[] = "{$role}.{$field}";
                }
            }
        }

        $this->assertSame([], $stale, implode("\n", $stale));
    }

    public function test_every_public_field_is_answerable_for_every_property_type_that_collects_it(): void
    {
        $gaps = [];
        foreach (AskAiFieldApplicability::ROLE_TYPES as $role => $types) {
            foreach ($types as $pt) {
                $covered = $this->coveredFor($role, $pt);
                foreach (D::forRole($role) as $field => $disposition) {
                    if ($disposition === D::ANSWERABLE_PUBLIC
                        && AskAiFieldApplicability::collects($role, $field, $pt)
                        && !isset($covered[$field])) {
                        $gaps[] = "{$role} / {$pt}: {$field}";
                    }
                }
            }
        }

        $this->assertSame([], $gaps, "Public fields the form collects for a type, with no question admitted for that type:\n" . implode("\n", $gaps));
    }

    public function test_every_entry_is_admitted_for_exactly_the_types_whose_form_collects_its_source(): void
    {
        // Both directions in one check. Too narrow is a coverage gap; too wide asks a question
        // the owner's form never asked — the Vacant Land listing with a stray bedrooms row.
        // A role's full set is written PT::ALL_TYPES so a listing whose type cannot be resolved
        // keeps the questions every type shares.
        $wrong = [];
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            $role = $entry['role'] ?? null;
            if (!isset(AskAiFieldApplicability::ROLE_TYPES[$role]) || ($entry['source_kind'] ?? 'listing') !== 'listing') {
                continue;
            }
            if (preg_match('/^listing\.([a-z0-9_]+)$/', (string) ($entry['source_path'] ?? ''), $m) !== 1) {
                continue;
            }
            $expected = AskAiFieldApplicability::for($role, $m[1]);
            if (!is_array($expected)) {
                continue; // LEGACY_NO_INPUT: the form cannot say, so the entry's own declaration stands
            }

            $all      = AskAiFieldApplicability::ROLE_TYPES[$role];
            $declared = (array) ($entry['property_types'] ?? []);
            $want     = array_diff($all, $expected) === [] ? AskAiPropertyTypeResolver::ALL_TYPES : $expected;

            sort($declared);
            sort($want);
            if ($declared !== $want) {
                $wrong[] = "{$id} ({$m[1]}): declared [" . implode(', ', $declared) . '], form collects [' . implode(', ', $expected) . ']';
            }
        }

        $this->assertSame([], $wrong, implode("\n", $wrong));
    }

    /** @return array<string, true> fields covered by entries admitted for this role and type */
    private function coveredFor(string $role, string $pt): array
    {
        $covered = [];
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $entry) {
            if (($entry['role'] ?? null) !== $role || !AskAiPropertyTypeResolver::admits($entry['property_types'] ?? null, [$pt])) {
                continue;
            }
            $paths = array_merge([$entry['source_path'] ?? null], (array) ($entry['supporting_paths'] ?? []), (array) ($entry['covers'] ?? []));
            foreach ($paths as $path) {
                if (is_string($path) && preg_match('/^listing\.([a-z0-9_]+)$/', $path, $m) === 1) {
                    $covered[$m[1]] = true;
                }
            }
        }

        return $covered;
    }

    public function test_dispositions_are_exhaustive_and_use_known_values(): void
    {
        $known = [
            D::ANSWERABLE_PUBLIC, D::ANSWERABLE_OWNER_ONLY,
            D::PRIVATE_FIELD, D::INTERNAL, D::NOT_APPLICABLE,
        ];

        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            $dispositions = D::forRole($role);

            $this->assertNotEmpty($dispositions, "{$role} has no canonical fields");

            foreach ($dispositions as $field => $disposition) {
                $this->assertContains(
                    $disposition,
                    $known,
                    "{$role}.{$field} has disposition '{$disposition}'"
                );
            }
        }
    }
}
