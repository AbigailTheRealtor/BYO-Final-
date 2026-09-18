<?php

namespace Tests\Unit\AskAi;

use App\Support\AskAi\AskAiFieldDisposition as D;
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
