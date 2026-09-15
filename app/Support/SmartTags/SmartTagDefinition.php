<?php

namespace App\Support\SmartTags;

/**
 * One canonical Smart Tag, as declared in config/smart_tags.php.
 *
 * Immutable. Built only by {@see SmartTagTaxonomy}.
 */
final class SmartTagDefinition
{
    public const COMPLIANCE_APPROVED       = 'approved';
    public const COMPLIANCE_RESTRICTED     = 'restricted';
    public const COMPLIANCE_PENDING_REVIEW = 'pending_review';

    public const COMPLIANCE_STATUSES = [
        self::COMPLIANCE_APPROVED,
        self::COMPLIANCE_RESTRICTED,
        self::COMPLIANCE_PENDING_REVIEW,
    ];

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_RETIRED = 'retired';

    /**
     * @param SmartTagContext[] $contexts
     * @param string[]          $conflictsWith
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $category,
        public readonly string $description,
        public readonly string $status,
        public readonly array $contexts,
        public readonly bool $mlsDerivable,
        public readonly bool $nativeDerivable,
        public readonly bool $ownerSelectable,
        public readonly bool $seekerSelectable,
        public readonly bool $publicDisplay,
        public readonly bool $negatable,
        public readonly string $complianceStatus,
        public readonly ?string $complianceNote,
        public readonly ?string $complianceNotice,
        public readonly int $displayOrder,
        public readonly array $conflictsWith = [],
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function appliesTo(SmartTagContext $context): bool
    {
        return in_array($context, $this->contexts, true);
    }

    public function isPendingReview(): bool
    {
        return $this->complianceStatus === self::COMPLIANCE_PENDING_REVIEW;
    }

    /** Selectable by a Seller/Landlord at all (context is checked separately). */
    public function isOwnerSelectable(): bool
    {
        return $this->isActive() && $this->ownerSelectable && ! $this->isPendingReview();
    }

    /** Selectable by a Buyer/Tenant at all (context is checked separately). */
    public function isSeekerSelectable(): bool
    {
        return $this->isActive() && $this->seekerSelectable && ! $this->isPendingReview();
    }

    /** Pending-review tags are never shown publicly, whatever the flag says. */
    public function isPubliclyDisplayable(): bool
    {
        return $this->isActive() && $this->publicDisplay && ! $this->isPendingReview();
    }

    public function conflictsWith(string $otherKey): bool
    {
        return in_array($otherKey, $this->conflictsWith, true);
    }

    /** @return string[] */
    public function contextValues(): array
    {
        return array_map(static fn (SmartTagContext $c) => $c->value, $this->contexts);
    }
}
