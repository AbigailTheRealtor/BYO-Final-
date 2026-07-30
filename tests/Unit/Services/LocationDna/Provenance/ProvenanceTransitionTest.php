<?php

namespace Tests\Unit\Services\LocationDna\Provenance;

use App\Services\LocationDna\Provenance\LocationDnaProvenanceException;
use App\Services\LocationDna\Provenance\LocationDnaProvenanceKind as Kind;
use App\Services\LocationDna\Provenance\ProvenanceActor as Actor;
use App\Services\LocationDna\Provenance\ProvenanceTransition as T;
use PHPUnit\Framework\TestCase;

/**
 * G1e — provenance transitions: what may change origin, and who may change it.
 *
 * The rule this suite exists to prove is that an explicit owner clear can never be automatically
 * resurrected, and an authored value can never be automatically overwritten — the provenance-layer
 * counterpart of the resurrection defect G1a proved live in six of eight workflows.
 */
class ProvenanceTransitionTest extends TestCase
{
    private function allowed(Kind $from, Kind $to, Actor $actor): bool
    {
        return T::of($from, $to, $actor)->isAllowed();
    }

    // ── explicit owner: may always state intent ──────────────────────────────

    public function test_all_required_explicit_owner_transitions_are_allowed(): void
    {
        $cases = [
            // absent is modelled as Unknown provenance — no entry yet.
            [Kind::Unknown,          Kind::OwnerAuthored],
            [Kind::Unknown,          Kind::OwnerCleared],
            [Kind::LegacyFallback,   Kind::OwnerAuthored],
            [Kind::LegacyFallback,   Kind::OwnerCleared],
            [Kind::LegacyRepaired,   Kind::OwnerAuthored],
            [Kind::LegacyRepaired,   Kind::OwnerCleared],
            [Kind::Inherited,        Kind::OwnerAuthored],
            [Kind::Inherited,        Kind::OwnerCleared],
            [Kind::Derived,          Kind::OwnerAuthored],
            [Kind::Derived,          Kind::OwnerCleared],
            [Kind::Imported,         Kind::OwnerAuthored],
            [Kind::Imported,         Kind::OwnerCleared],
            [Kind::SnapshotRetained, Kind::OwnerAuthored],  // only through explicit authoring
            [Kind::OwnerAuthored,    Kind::OwnerCleared],
            [Kind::OwnerCleared,     Kind::OwnerAuthored],
        ];

        foreach ($cases as [$from, $to]) {
            $this->assertTrue(
                $this->allowed($from, $to, Actor::ExplicitOwner),
                "explicit owner {$from->value} -> {$to->value} must be allowed",
            );
        }
    }

    public function test_an_owner_action_may_not_establish_a_non_owner_kind(): void
    {
        // An owner action producing `Derived` or `LegacyRepaired` would mislabel its own authorship.
        foreach ([Kind::Derived, Kind::LegacyRepaired, Kind::Inherited, Kind::Imported,
                  Kind::LegacyFallback, Kind::SnapshotRetained, Kind::Unknown] as $to) {
            $this->assertFalse(
                $this->allowed(Kind::OwnerAuthored, $to, Actor::ExplicitOwner),
                "owner action must not establish {$to->value}",
            );
        }
    }

    // ── migration repair: exactly one legal edge ──────────────────────────────

    public function test_lazy_repair_is_allowed_only_from_legacy_fallback(): void
    {
        $this->assertTrue($this->allowed(Kind::LegacyFallback, Kind::LegacyRepaired, Actor::MigrationRepair));
    }

    public function test_repair_may_not_start_from_anything_else(): void
    {
        foreach (Kind::all() as $from) {
            if ($from === Kind::LegacyFallback) {
                continue;
            }

            $this->assertFalse(
                $this->allowed($from, Kind::LegacyRepaired, Actor::MigrationRepair),
                "repair must not start from {$from->value}",
            );
        }
    }

    public function test_repair_may_not_produce_owner_authorship(): void
    {
        // D-G1-6: lazy repair "may not convert inherited values into authored values".
        $this->assertFalse($this->allowed(Kind::LegacyFallback, Kind::OwnerAuthored, Actor::MigrationRepair));
        $this->assertFalse($this->allowed(Kind::LegacyFallback, Kind::OwnerCleared, Actor::MigrationRepair));
    }

    // ── automatic system: the denials that matter ─────────────────────────────

    public function test_automatic_fallback_into_an_owner_cleared_dimension_is_denied(): void
    {
        foreach (Kind::all() as $to) {
            $this->assertFalse(
                $this->allowed(Kind::OwnerCleared, $to, Actor::AutomaticSystem),
                "automatic {$to->value} must not overwrite an explicit owner clear",
            );
        }

        $t = T::of(Kind::OwnerCleared, Kind::LegacyFallback, Actor::AutomaticSystem);
        $this->assertStringContainsString('may not be automatically resurrected', (string) $t->refusalReason());
    }

    public function test_automatic_derived_overwrite_of_owner_authored_is_denied(): void
    {
        $t = T::of(Kind::OwnerAuthored, Kind::Derived, Actor::AutomaticSystem);

        $this->assertFalse($t->isAllowed());
        $this->assertStringContainsString('may not be automatically overwritten', (string) $t->refusalReason());
    }

    public function test_automatic_inherited_overwrite_of_owner_authored_is_denied(): void
    {
        $this->assertFalse($this->allowed(Kind::OwnerAuthored, Kind::Inherited, Actor::AutomaticSystem));
    }

    public function test_automatic_import_may_not_overwrite_owner_authored(): void
    {
        // §8.2 rule 2: an import never overwrites an authored dimension.
        $this->assertFalse($this->allowed(Kind::OwnerAuthored, Kind::Imported, Actor::AutomaticSystem));
    }

    public function test_no_automatic_transition_may_establish_owner_provenance(): void
    {
        foreach (Kind::all() as $from) {
            foreach ([Kind::OwnerAuthored, Kind::OwnerCleared] as $to) {
                $this->assertFalse(
                    $this->allowed($from, $to, Actor::AutomaticSystem),
                    "automatic {$from->value} -> {$to->value} must be denied",
                );
            }
        }

        $t = T::of(Kind::Derived, Kind::OwnerAuthored, Actor::AutomaticSystem);
        $this->assertStringContainsString('only an explicit owner action', (string) $t->refusalReason());
    }

    public function test_snapshot_restoration_is_denied_for_every_non_owner_actor(): void
    {
        foreach ([Actor::AutomaticSystem, Actor::MigrationRepair] as $actor) {
            foreach (Kind::all() as $to) {
                $this->assertFalse(
                    $this->allowed(Kind::SnapshotRetained, $to, $actor),
                    "{$actor->value} must not restore from the retained snapshot",
                );
            }
        }

        $t = T::of(Kind::SnapshotRetained, Kind::Derived, Actor::AutomaticSystem);
        $this->assertStringContainsString('retained snapshot', (string) $t->refusalReason());
    }

    public function test_unknown_provenance_is_never_automatically_promoted(): void
    {
        foreach (Kind::all() as $to) {
            $this->assertFalse(
                $this->allowed(Kind::Unknown, $to, Actor::AutomaticSystem),
                "unknown -> {$to->value} must not be automatic",
            );
        }

        $t = T::of(Kind::Unknown, Kind::Derived, Actor::AutomaticSystem);
        $this->assertStringContainsString('unknown origin', (string) $t->refusalReason());
    }

    public function test_an_automatic_transition_between_non_authoritative_kinds_is_permitted(): void
    {
        // Enrichment replacing a derived label with a fresher derived label is legitimate.
        $this->assertTrue($this->allowed(Kind::Derived, Kind::Derived, Actor::AutomaticSystem));
        $this->assertTrue($this->allowed(Kind::LegacyFallback, Kind::Derived, Actor::AutomaticSystem));
        $this->assertTrue($this->allowed(Kind::Inherited, Kind::Derived, Actor::AutomaticSystem));
    }

    public function test_conditionally_authoritative_imported_is_not_automatically_overwritable(): void
    {
        $this->assertFalse($this->allowed(Kind::Imported, Kind::Derived, Actor::AutomaticSystem));
    }

    // ── no bypass, and refusal is explicit ───────────────────────────────────

    public function test_there_is_no_force_or_bypass_actor(): void
    {
        $this->assertSame(
            ['explicit_owner', 'automatic_system', 'migration_repair'],
            array_map(fn (Actor $a): string => $a->value, Actor::cases()),
        );

        foreach (Actor::cases() as $actor) {
            foreach (['force', 'bypass', 'override', 'admin'] as $banned) {
                $this->assertStringNotContainsString($banned, $actor->value);
            }
        }
    }

    public function test_assert_allowed_throws_with_a_precise_reason(): void
    {
        try {
            T::of(Kind::OwnerCleared, Kind::LegacyFallback, Actor::AutomaticSystem)->assertAllowed();
            $this->fail('a forbidden transition must throw');
        } catch (LocationDnaProvenanceException $e) {
            $this->assertStringContainsString('owner_cleared -> legacy_fallback', $e->getMessage());
            $this->assertStringContainsString('automatic_system', $e->getMessage());
        }
    }

    public function test_an_allowed_transition_has_no_refusal_reason(): void
    {
        $this->assertNull(T::of(Kind::Unknown, Kind::OwnerAuthored, Actor::ExplicitOwner)->refusalReason());
    }

    public function test_transitions_are_total_and_deterministic(): void
    {
        foreach (Kind::all() as $from) {
            foreach (Kind::all() as $to) {
                foreach (Actor::cases() as $actor) {
                    $a = T::of($from, $to, $actor)->isAllowed();
                    $b = T::of($from, $to, $actor)->isAllowed();

                    $this->assertIsBool($a);
                    $this->assertSame($a, $b, "non-deterministic for {$from->value}/{$to->value}/{$actor->value}");
                }
            }
        }
    }
}
