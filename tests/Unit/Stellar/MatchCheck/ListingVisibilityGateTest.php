<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Services\Stellar\MatchCheck\ListingVisibilityGate;
use Tests\TestCase;

/**
 * Phase 4 · Wave 1 / C2 — ListingVisibilityGate (F9).
 *
 * Mirrors BuyerMatchService::match() IDX semantics: only an explicit falsey
 * IDXParticipationYN blocks; absent / malformed / unparseable fail OPEN.
 */
class ListingVisibilityGateTest extends TestCase
{
    private function listing(?string $rawJson): BridgeProperty
    {
        // Unsaved in-memory model — no DB needed.
        return new BridgeProperty(['raw_json' => $rawJson]);
    }

    private function gate(): ListingVisibilityGate
    {
        return new ListingVisibilityGate();
    }

    /** @test */
    public function explicit_boolean_true_is_visible(): void
    {
        $this->assertTrue($this->gate()->isConsumerVisible(
            $this->listing(json_encode(['IDXParticipationYN' => true]))
        ));
    }

    /** @test */
    public function explicit_boolean_false_is_blocked(): void
    {
        $decision = $this->gate()->decide(
            $this->listing(json_encode(['IDXParticipationYN' => false]))
        );
        $this->assertFalse($decision->visible);
        $this->assertSame('idx_participation_false', $decision->reason);
    }

    /** @test */
    public function string_false_is_blocked(): void
    {
        $this->assertFalse($this->gate()->isConsumerVisible(
            $this->listing(json_encode(['IDXParticipationYN' => 'false']))
        ));
    }

    /** @test */
    public function string_true_is_visible(): void
    {
        $this->assertTrue($this->gate()->isConsumerVisible(
            $this->listing(json_encode(['IDXParticipationYN' => 'true']))
        ));
    }

    /** @test */
    public function integer_zero_is_blocked_and_one_is_visible(): void
    {
        $this->assertFalse($this->gate()->isConsumerVisible(
            $this->listing(json_encode(['IDXParticipationYN' => 0]))
        ));
        $this->assertTrue($this->gate()->isConsumerVisible(
            $this->listing(json_encode(['IDXParticipationYN' => 1]))
        ));
    }

    /** @test */
    public function absent_key_fails_open(): void
    {
        $decision = $this->gate()->decide($this->listing(json_encode(['ListingId' => 'X'])));
        $this->assertTrue($decision->visible);
        $this->assertSame('idx_absent_default_eligible', $decision->reason);
    }

    /** @test */
    public function malformed_json_fails_open(): void
    {
        $decision = $this->gate()->decide($this->listing('{not valid json'));
        $this->assertTrue($decision->visible);
        $this->assertSame('idx_malformed_json_default_eligible', $decision->reason);
    }

    /** @test */
    public function null_raw_json_fails_open(): void
    {
        $this->assertTrue($this->gate()->isConsumerVisible($this->listing(null)));
    }
}
