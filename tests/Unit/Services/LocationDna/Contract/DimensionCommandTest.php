<?php

namespace Tests\Unit\Services\LocationDna\Contract;

use App\Services\LocationDna\Contract\ContractViolation;
use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\DimensionCommand;
use App\Services\LocationDna\Contract\DimensionCommandApplier;
use App\Services\LocationDna\Contract\DimensionOperation;
use App\Services\LocationDna\Contract\LocationDnaContractException;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use PHPUnit\Framework\TestCase;

/**
 * G1c — the operation vocabulary (D-G1-2, option 2-A).
 *
 * The central approved claim under test: an unmounted or omitted field produces NO command,
 * absence is not clear, and only an explicit clear withdraws a dimension. That is the
 * structural resolution of the ambiguity G1a proved live in
 * test_unmounted_editor_and_deliberate_clear_are_indistinguishable_to_consumers.
 */
class DimensionCommandTest extends TestCase
{
    public function test_vocabulary_is_exactly_two_operations(): void
    {
        $this->assertSame(['set', 'clear'], array_map(
            static fn (DimensionOperation $o): string => $o->value,
            DimensionOperation::cases(),
        ));
    }

    // ── set ──────────────────────────────────────────────────────────────────

    public function test_set_a_scalar_dimension(): void
    {
        $c = DimensionCommand::set(Dimension::State, 'FL');

        $this->assertTrue($c->isSet());
        $this->assertFalse($c->isClear());
        $this->assertSame('FL', $c->value());
        $this->assertSame('FL', $c->effectiveValue());
    }

    public function test_set_a_collection_dimension(): void
    {
        $c = DimensionCommand::set(Dimension::Cities, ['Tampa', 'Orlando']);

        $this->assertTrue($c->isSet());
        $this->assertSame(['Tampa', 'Orlando'], $c->value());
    }

    // ── clear ────────────────────────────────────────────────────────────────

    public function test_clear_a_collection_dimension_yields_the_canonical_empty(): void
    {
        $c = DimensionCommand::clear(Dimension::Polygons);

        $this->assertTrue($c->isClear());
        $this->assertNull($c->value(), 'a clear carries no value (§6.1)');
        $this->assertSame([], $c->effectiveValue());
    }

    public function test_clear_a_scalar_dimension_yields_its_canonical_empty(): void
    {
        $this->assertSame('', DimensionCommand::clear(Dimension::State)->effectiveValue());
        $this->assertSame('', DimensionCommand::clear(Dimension::LocationNotes)->effectiveValue());
        $this->assertFalse(DimensionCommand::clear(Dimension::FlexibleLocation)->effectiveValue());
    }

    // ── the four things that must be inexpressible ───────────────────────────

    public function test_null_cannot_become_an_authored_set(): void
    {
        try {
            DimensionCommand::set(Dimension::Cities, null);
            $this->fail('set(null) must be rejected');
        } catch (LocationDnaContractException $e) {
            $this->assertSame(ContractViolation::AuthoredNull, $e->violation());
        }
    }

    public function test_empty_string_cannot_silently_become_a_clear(): void
    {
        // '' is the canonical empty for a Text dimension, so setting it is rejected outright
        // rather than quietly reinterpreted as a clear (D-G1-2 approved).
        try {
            DimensionCommand::set(Dimension::State, '');
            $this->fail("set('') must be rejected, not reinterpreted");
        } catch (LocationDnaContractException $e) {
            $this->assertSame(ContractViolation::InvalidDimensionValue, $e->violation());
            $this->assertStringContainsString('use a clear command', $e->getMessage());
        }
    }

    public function test_empty_collection_cannot_silently_become_a_clear(): void
    {
        $this->expectException(LocationDnaContractException::class);
        DimensionCommand::set(Dimension::Cities, []);
    }

    public function test_unsupported_operation_names_are_rejected(): void
    {
        foreach (['preserve', 'replace', 'merge', 'append', 'remove', 'reorder', 'omit', 'migrate-from-legacy', ''] as $name) {
            try {
                DimensionOperation::fromName($name);
                $this->fail("operation `{$name}` must be rejected");
            } catch (LocationDnaContractException $e) {
                $this->assertSame(ContractViolation::InvalidOperation, $e->violation());
            }
        }
    }

    public function test_clear_may_not_carry_a_value(): void
    {
        $this->expectException(LocationDnaContractException::class);
        DimensionCommand::fromOperationName(Dimension::Cities, 'clear', ['Tampa']);
    }

    public function test_operation_names_are_parsed_case_insensitively(): void
    {
        $this->assertSame(DimensionOperation::Set, DimensionOperation::fromName(' SET '));
        $this->assertSame(DimensionOperation::Clear, DimensionOperation::fromName('Clear'));
    }

    // ── omission is preserve ─────────────────────────────────────────────────

    public function test_an_omitted_dimension_creates_no_command_and_changes_nothing(): void
    {
        $before  = LocationDnaDocument::fromCanonical(['cities' => ['Tampa'], 'state' => 'FL']);
        $applier = new DimensionCommandApplier();

        // The editor touched only `cities`; `state` is simply not mentioned.
        $after = $applier->apply($before, [DimensionCommand::set(Dimension::Cities, ['Orlando'])]);

        $this->assertSame(['Orlando'], $after->value(Dimension::Cities));
        $this->assertSame('FL', $after->value(Dimension::State), 'an unmentioned dimension is preserved');
        $this->assertTrue($after->isAuthored(Dimension::State));
    }

    public function test_an_empty_batch_preserves_everything(): void
    {
        $before = LocationDnaDocument::fromCanonical(['cities' => ['Tampa'], 'polygons' => []]);
        $after  = (new DimensionCommandApplier())->apply($before, []);

        $this->assertSame($before->toDimensionArray(), $after->toDimensionArray());
        $this->assertTrue($after->isCleared(Dimension::Polygons), 'a prior clear stays cleared');
    }

    public function test_absence_is_not_clear(): void
    {
        $before = LocationDnaDocument::fromCanonical(['cities' => ['Tampa']]);
        $after  = (new DimensionCommandApplier())->apply($before, []);

        // `state` was absent before and is absent after — it did NOT become cleared.
        $this->assertTrue($after->isAbsent(Dimension::State));
        $this->assertFalse($after->isCleared(Dimension::State));
    }

    public function test_only_an_explicit_clear_withdraws_a_dimension(): void
    {
        $before = LocationDnaDocument::fromCanonical(['cities' => ['Tampa']]);
        $after  = (new DimensionCommandApplier())->apply($before, [DimensionCommand::clear(Dimension::Cities)]);

        $this->assertTrue($after->isCleared(Dimension::Cities));
        $this->assertSame([], $after->value(Dimension::Cities));
        $this->assertSame(['Tampa'], $before->value(Dimension::Cities), 'input document not mutated');
    }

    // ── the command layer is framework-free ──────────────────────────────────

    public function test_command_layer_touches_no_framework_and_writes_nothing(): void
    {
        // No app boot, no request, no container. Construction alone proves the dependency shape.
        $c = DimensionCommand::set(Dimension::Cities, ['Tampa']);

        $this->assertSame(Dimension::Cities, $c->dimension);
        $this->assertSame(DimensionOperation::Set, $c->operation);
    }

    public function test_a_batch_rejects_two_commands_for_one_dimension(): void
    {
        $this->expectException(LocationDnaContractException::class);

        (new DimensionCommandApplier())->apply(LocationDnaDocument::emptyDocument(), [
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
            DimensionCommand::clear(Dimension::Cities),
        ]);
    }
}
