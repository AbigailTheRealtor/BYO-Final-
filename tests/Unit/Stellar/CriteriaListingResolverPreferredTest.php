<?php

namespace Tests\Unit\Stellar;

use App\Models\User;
use App\Services\Stellar\CriteriaListingResolver;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Phase 4 · Wave 1 / C4 — CriteriaListingResolver::resolvePreferred() (F5).
 *
 * Tests only the NEW selection logic (agent short-circuit, intent filtering,
 * newest-first pick, empty→null) via a partial mock feeding resolveAccessible()
 * canned data — no legacy-criteria DB fixtures, and the existing methods are untouched.
 */
class CriteriaListingResolverPreferredTest extends TestCase
{
    private function consumer(): User
    {
        $u = new User();
        $u->user_type = 'buyer';
        return $u;
    }

    private function agent(): User
    {
        $u = new User();
        $u->user_type = 'agent';
        return $u;
    }

    /** @return CriteriaListingResolver&\Mockery\MockInterface */
    private function resolverReturning(array $accessible)
    {
        $resolver = Mockery::mock(CriteriaListingResolver::class)->makePartial();
        $resolver->shouldReceive('resolveAccessible')->andReturn($accessible);
        return $resolver;
    }

    private function accessibleFixture(): array
    {
        // Pre-sorted newest-first, as resolveAccessible() guarantees.
        return [
            ['id' => 10, 'type' => 'buyer_offer',  'label' => 'B',  'created_at' => Carbon::parse('2026-03-01')],
            ['id' => 11, 'type' => 'tenant',       'label' => 'T',  'created_at' => Carbon::parse('2026-02-01')],
            ['id' => 12, 'type' => 'buyer',        'label' => 'B2', 'created_at' => Carbon::parse('2026-01-01')],
        ];
    }

    /** @test */
    public function no_intent_returns_the_newest_accessible_record(): void
    {
        $preferred = $this->resolverReturning($this->accessibleFixture())
            ->resolvePreferred($this->consumer(), null);

        $this->assertSame(10, $preferred['id']);
    }

    /** @test */
    public function tenant_intent_returns_newest_tenant_side_record(): void
    {
        $preferred = $this->resolverReturning($this->accessibleFixture())
            ->resolvePreferred($this->consumer(), 'tenant');

        $this->assertSame(11, $preferred['id']);
        $this->assertSame('tenant', $preferred['type']);
    }

    /** @test */
    public function buyer_intent_returns_newest_buyer_side_record(): void
    {
        // Both id 10 (buyer_offer) and id 12 (buyer) qualify; newest (10) wins.
        $preferred = $this->resolverReturning($this->accessibleFixture())
            ->resolvePreferred($this->consumer(), 'buyer');

        $this->assertSame(10, $preferred['id']);
    }

    /** @test */
    public function agents_never_get_an_auto_default(): void
    {
        $resolver = Mockery::mock(CriteriaListingResolver::class)->makePartial();
        $resolver->shouldNotReceive('resolveAccessible');

        $this->assertNull($resolver->resolvePreferred($this->agent(), null));
    }

    /** @test */
    public function consumer_with_no_accessible_records_gets_null(): void
    {
        $this->assertNull(
            $this->resolverReturning([])->resolvePreferred($this->consumer(), null)
        );
    }

    /** @test */
    public function intent_with_no_matching_side_returns_null(): void
    {
        $buyerOnly = [
            ['id' => 20, 'type' => 'buyer', 'label' => 'B', 'created_at' => Carbon::parse('2026-01-01')],
        ];

        $this->assertNull(
            $this->resolverReturning($buyerOnly)->resolvePreferred($this->consumer(), 'tenant')
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
