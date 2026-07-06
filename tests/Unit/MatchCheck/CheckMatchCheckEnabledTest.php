<?php

namespace Tests\Unit\MatchCheck;

use App\Http\Middleware\CheckMatchCheckEnabled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Phase 4 · Wave 0 / C1 — Match Check feature-gate middleware.
 *
 * The route is invisible (404) unless config('mls_match_check.enabled') is true.
 * Default is OFF, so the feature ships inert.
 */
class CheckMatchCheckEnabledTest extends TestCase
{
    /** @test */
    public function it_blocks_with_404_when_the_flag_is_off(): void
    {
        config()->set('mls_match_check.enabled', false);

        $this->expectException(NotFoundHttpException::class);

        (new CheckMatchCheckEnabled())->handle(
            Request::create('/match-check', 'GET'),
            fn () => new Response('ok')
        );
    }

    /** @test */
    public function it_passes_through_when_the_flag_is_on(): void
    {
        config()->set('mls_match_check.enabled', true);

        $response = (new CheckMatchCheckEnabled())->handle(
            Request::create('/match-check', 'GET'),
            fn () => new Response('ok')
        );

        $this->assertSame('ok', $response->getContent());
    }

    /** @test */
    public function the_shipped_default_is_off(): void
    {
        // Guards against the flag being accidentally enabled in the repo config.
        $this->assertFalse(config('mls_match_check.enabled'));
    }
}
