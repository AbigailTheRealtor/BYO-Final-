<?php

namespace App\Services\LocationDna\Contract;

/**
 * DimensionCommandApplier — a PURE, INERT applier of commands to a document.
 *
 * G1c contract core. INERT, and deliberately domain-local.
 *
 * WHY THIS EXISTS, AND ITS LIMITS
 * ------------------------------
 * The G1c authorization permits a pure command applier "only if it is necessary to test the
 * contract and remains entirely inert and domain-local". It is necessary: the approved
 * contract's central claim is that no command means preserve while an explicit clear is
 * authoritative, and that claim cannot be exercised without applying commands to a document.
 *
 * It is NOT a persistence service. `LocationDnaPersistenceService` is deliberately NOT
 * created in this increment (D-G1-5). This class:
 *   - touches no database, no Eloquent, no transaction, no meta key
 *   - performs no capability or authorisation check (G1d, not started)
 *   - records no provenance (G1e, not started)
 *   - writes no legacy mirror (LegacyMirrorAdapter, not created)
 *
 * It maps (document, commands) -> document, and nothing else.
 *
 * BATCH SEMANTICS
 * ---------------
 * §6.1: a batch is validated as a whole and applied atomically — "a batch that fails
 * validation on any envelope applies none of them". Because commands are validated at
 * construction, the failure mode here is a duplicate dimension within one batch, which is
 * rejected rather than resolved by last-writer-wins.
 */
final class DimensionCommandApplier
{
    /**
     * Apply a batch, returning a new document. The input document is never mutated.
     *
     * An empty batch returns an equal document — that is the whole point: absence of a
     * command is preserve (D-G1-2).
     *
     * @param  list<DimensionCommand>  $commands
     *
     * @throws LocationDnaContractException when two commands target the same dimension
     */
    public function apply(LocationDnaDocument $document, array $commands): LocationDnaDocument
    {
        $seen = [];

        foreach ($commands as $command) {
            if (! $command instanceof DimensionCommand) {
                throw LocationDnaContractException::invalidOperation(
                    'A command batch may contain only DimensionCommand instances.',
                );
            }

            if (isset($seen[$command->dimension->value])) {
                throw LocationDnaContractException::invalidOperation(
                    "Duplicate command for dimension `{$command->dimension->value}` in one batch; "
                    .'a batch must state each dimension at most once.',
                );
            }

            $seen[$command->dimension->value] = true;
        }

        $next = $document;

        foreach ($commands as $command) {
            $next = $command->isClear()
                ? $next->withClearedDimension($command->dimension)
                : $next->withDimension($command->dimension, $command->value());
        }

        return $next;
    }
}
