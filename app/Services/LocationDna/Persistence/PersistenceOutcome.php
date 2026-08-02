<?php

namespace App\Services\LocationDna\Persistence;

/**
 * G1f-1 — the three outcomes a persistence attempt can have.
 *
 * Closed and small on purpose. There is deliberately no `PartiallyApplied`: §6.2 requires a batch
 * to apply wholly or not at all, and giving partial application a name is how it becomes reachable.
 */
enum PersistenceOutcome: string
{
    /** Canonical state and managed mirrors were written. */
    case Changed = 'changed';

    /** Nothing was written. No commands, or commands that changed no canonical meaning. */
    case NoOp = 'no_op';

    /** Refused before any write. Carries an error code from the §6.3 closed set. */
    case Rejected = 'rejected';
}
