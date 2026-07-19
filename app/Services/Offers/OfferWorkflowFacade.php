<?php

namespace App\Services\Offers;

use App\Models\Offer;

class OfferWorkflowFacade
{
    public function __construct(
        private readonly OfferSubmissionService $submissionService,
        private readonly OfferCounterService $counterService,
        private readonly OfferDecisionService $decisionService,
        private readonly OfferExpirationService $expirationService,
        private readonly OfferTimelineBuilder $timelineBuilder,
    ) {}

    public function submit(
        Offer $offer,
        ?int $actorId,
        string $actorRole = 'buyer',
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        return $this->submissionService->submit($offer, $actorId, $actorRole, $metadata, $ipAddress);
    }

    public function counter(
        Offer $parent,
        ?int $actorId,
        string $actorRole,
        array $overrides = [],
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        return $this->counterService->counter($parent, $actorId, $actorRole, $overrides, $metadata, $ipAddress);
    }

    public function accept(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        return $this->decisionService->accept($offer, $actorId, $actorRole, $metadata, $ipAddress);
    }

    public function reject(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        return $this->decisionService->reject($offer, $actorId, $actorRole, $metadata, $ipAddress);
    }

    public function withdraw(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        return $this->decisionService->withdraw($offer, $actorId, $actorRole, $metadata, $ipAddress);
    }

    public function expire(
        Offer $offer,
        ?int $actorId,
        string $actorRole = 'system',
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        return $this->expirationService->expire($offer, $actorId, $actorRole, $metadata, $ipAddress);
    }

    public function cancel(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        return $this->decisionService->cancel($offer, $actorId, $actorRole, $metadata, $ipAddress);
    }

    public function timeline(Offer $offer): array
    {
        return $this->timelineBuilder->buildForOffer($offer);
    }
}
