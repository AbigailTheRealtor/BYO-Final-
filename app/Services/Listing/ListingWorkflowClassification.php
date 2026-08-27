<?php

namespace App\Services\Listing;

use App\Support\Listing\ListingWorkflow;

/**
 * The answer to "which product is this row?", including the cases where there is no answer.
 *
 * Returned by {@see ListingWorkflowResolver::classify()}. The resolver's convenience
 * method `resolve()` collapses this to a nullable string; the inventory command and the
 * guard want the reasons too, which is why the full shape exists.
 *
 * Four statuses, and the distinction between the last three is the point:
 *
 *   RESOLVED     — one workflow, agreed by every signal present.
 *   UNCLASSIFIED — no signal at all. A legacy row nobody stamped.
 *   AMBIGUOUS    — signals exist but none is decisive (e.g. only an unrecognised value).
 *   CONFLICTING  — two or more decisive signals disagree. The corruption signature.
 *
 * Collapsing them into a single "unknown" would lose the operational difference: an
 * unclassified row wants a backfill rule, an ambiguous one wants a human look, and a
 * conflicting one is evidence of a write path that stamped inconsistently.
 */
final class ListingWorkflowClassification
{
    public const RESOLVED     = 'resolved';
    public const UNCLASSIFIED = 'unclassified';
    public const AMBIGUOUS    = 'ambiguous';
    public const CONFLICTING  = 'conflicting';

    /**
     * @param  string        $status    one of the four constants above
     * @param  string|null   $workflow  the resolved workflow, only ever set when RESOLVED
     * @param  array<string,string>  $evidence  signal name => what it said
     * @param  string        $reason    short human-readable explanation
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $workflow,
        public readonly array $evidence,
        public readonly string $reason,
    ) {}

    /** @param array<string,string> $evidence */
    public static function resolved(string $workflow, array $evidence): self
    {
        return new self(self::RESOLVED, $workflow, $evidence, "resolved as {$workflow}");
    }

    /** @param array<string,string> $evidence */
    public static function unclassified(array $evidence = []): self
    {
        return new self(self::UNCLASSIFIED, null, $evidence, 'no workflow evidence present');
    }

    /** @param array<string,string> $evidence */
    public static function ambiguous(array $evidence, string $reason): self
    {
        return new self(self::AMBIGUOUS, null, $evidence, $reason);
    }

    /** @param array<string,string> $evidence */
    public static function conflicting(array $evidence, string $reason): self
    {
        return new self(self::CONFLICTING, null, $evidence, $reason);
    }

    public function isResolved(): bool
    {
        return $this->status === self::RESOLVED;
    }

    public function isConflicting(): bool
    {
        return $this->status === self::CONFLICTING;
    }

    public function isAmbiguous(): bool
    {
        return $this->status === self::AMBIGUOUS;
    }

    public function isUnclassified(): bool
    {
        return $this->status === self::UNCLASSIFIED;
    }

    public function is(string $workflow): bool
    {
        return $this->isResolved() && $this->workflow === $workflow;
    }

    public function isHireAgent(): bool
    {
        return $this->is(ListingWorkflow::HIRE_AGENT);
    }

    public function isOfferListing(): bool
    {
        return $this->is(ListingWorkflow::OFFER_LISTING);
    }

    /**
     * The bucket this row belongs in for reporting purposes.
     *
     * Resolved rows report as their workflow so an inventory reads
     * hire_agent / offer_listing / unclassified / ambiguous / conflicting.
     */
    public function bucket(): string
    {
        return $this->isResolved() ? (string) $this->workflow : $this->status;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status'   => $this->status,
            'workflow' => $this->workflow,
            'bucket'   => $this->bucket(),
            'reason'   => $this->reason,
            'evidence' => $this->evidence,
        ];
    }
}
