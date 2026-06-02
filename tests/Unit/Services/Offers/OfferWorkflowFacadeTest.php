<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Services\Offers\OfferCounterService;
use App\Services\Offers\OfferDecisionService;
use App\Services\Offers\OfferExpirationService;
use App\Services\Offers\OfferSubmissionService;
use App\Services\Offers\OfferTimelineBuilder;
use App\Services\Offers\OfferWorkflowFacade;
use PHPUnit\Framework\TestCase;

class OfferWorkflowFacadeTest extends TestCase
{
    private OfferSubmissionService $submissionService;
    private OfferCounterService $counterService;
    private OfferDecisionService $decisionService;
    private OfferExpirationService $expirationService;
    private OfferTimelineBuilder $timelineBuilder;
    private OfferWorkflowFacade $facade;
    private Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->submissionService  = $this->createMock(OfferSubmissionService::class);
        $this->counterService     = $this->createMock(OfferCounterService::class);
        $this->decisionService    = $this->createMock(OfferDecisionService::class);
        $this->expirationService  = $this->createMock(OfferExpirationService::class);
        $this->timelineBuilder    = $this->createMock(OfferTimelineBuilder::class);

        $this->facade = new OfferWorkflowFacade(
            $this->submissionService,
            $this->counterService,
            $this->decisionService,
            $this->expirationService,
            $this->timelineBuilder,
        );

        $this->offer = $this->createMock(Offer::class);
    }

    public function test_submit_delegates_to_submission_service(): void
    {
        $expected = ['allowed' => true, 'offer' => $this->offer];

        $this->submissionService
            ->expects($this->once())
            ->method('submit')
            ->with($this->offer, 1, 'buyer', [], null)
            ->willReturn($expected);

        $result = $this->facade->submit($this->offer, 1, 'buyer', [], null);

        $this->assertSame($expected, $result);
    }

    public function test_counter_delegates_to_counter_service(): void
    {
        $expected = ['allowed' => true, 'parent_offer' => $this->offer, 'counter_offer' => null];

        $this->counterService
            ->expects($this->once())
            ->method('counter')
            ->with($this->offer, 2, 'agent', [], [], null)
            ->willReturn($expected);

        $result = $this->facade->counter($this->offer, 2, 'agent', [], [], null);

        $this->assertSame($expected, $result);
    }

    public function test_accept_delegates_to_decision_service(): void
    {
        $expected = ['allowed' => true, 'offer' => $this->offer];

        $this->decisionService
            ->expects($this->once())
            ->method('accept')
            ->with($this->offer, 3, 'seller', [], null)
            ->willReturn($expected);

        $result = $this->facade->accept($this->offer, 3, 'seller', [], null);

        $this->assertSame($expected, $result);
    }

    public function test_reject_delegates_to_decision_service(): void
    {
        $expected = ['allowed' => false, 'offer' => $this->offer, 'reason' => 'final state'];

        $this->decisionService
            ->expects($this->once())
            ->method('reject')
            ->with($this->offer, 4, 'seller', [], null)
            ->willReturn($expected);

        $result = $this->facade->reject($this->offer, 4, 'seller', [], null);

        $this->assertSame($expected, $result);
    }

    public function test_withdraw_delegates_to_decision_service(): void
    {
        $expected = ['allowed' => true, 'offer' => $this->offer];

        $this->decisionService
            ->expects($this->once())
            ->method('withdraw')
            ->with($this->offer, 5, 'buyer', [], null)
            ->willReturn($expected);

        $result = $this->facade->withdraw($this->offer, 5, 'buyer', [], null);

        $this->assertSame($expected, $result);
    }

    public function test_expire_delegates_to_expiration_service(): void
    {
        $expected = ['allowed' => true, 'offer' => $this->offer];

        $this->expirationService
            ->expects($this->once())
            ->method('expire')
            ->with($this->offer, null, 'system', [], null)
            ->willReturn($expected);

        $result = $this->facade->expire($this->offer, null, 'system', [], null);

        $this->assertSame($expected, $result);
    }

    public function test_timeline_delegates_to_timeline_builder(): void
    {
        $expected = [['offer_id' => 1, 'status' => 'submitted']];

        $this->timelineBuilder
            ->expects($this->once())
            ->method('buildForOffer')
            ->with($this->offer)
            ->willReturn($expected);

        $result = $this->facade->timeline($this->offer);

        $this->assertSame($expected, $result);
    }

    public function test_facade_returns_delegated_result_unchanged(): void
    {
        $expected = ['allowed' => true, 'offer' => $this->offer, 'from_status' => 'draft', 'to_status' => 'submitted'];

        $this->submissionService
            ->method('submit')
            ->willReturn($expected);

        $result = $this->facade->submit($this->offer, 1);

        $this->assertSame($expected, $result);
    }

    public function test_metadata_and_ip_address_pass_through_to_underlying_service(): void
    {
        $metadata   = ['source' => 'web', 'note' => 'test'];
        $ipAddress  = '192.168.1.100';
        $expected   = ['allowed' => true, 'offer' => $this->offer];

        $this->submissionService
            ->expects($this->once())
            ->method('submit')
            ->with($this->offer, 7, 'agent', $metadata, $ipAddress)
            ->willReturn($expected);

        $result = $this->facade->submit($this->offer, 7, 'agent', $metadata, $ipAddress);

        $this->assertSame($expected, $result);
    }

    public function test_metadata_and_ip_address_pass_through_to_expiration_service(): void
    {
        $metadata   = ['triggered_by' => 'scheduler'];
        $ipAddress  = null;
        $expected   = ['allowed' => true, 'offer' => $this->offer];

        $this->expirationService
            ->expects($this->once())
            ->method('expire')
            ->with($this->offer, null, 'system', $metadata, $ipAddress)
            ->willReturn($expected);

        $result = $this->facade->expire($this->offer, null, 'system', $metadata, $ipAddress);

        $this->assertSame($expected, $result);
    }

    public function test_facade_class_body_contains_no_forbidden_write_patterns(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Offers/OfferWorkflowFacade.php'
        );

        $forbiddenPatterns = [
            '::create',
            '->save(',
            '->update(',
            '->delete(',
            '->insert(',
            'OfferEventLog::',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $source,
                "OfferWorkflowFacade must not contain '{$pattern}' — it is a pure delegation layer."
            );
        }
    }
}
