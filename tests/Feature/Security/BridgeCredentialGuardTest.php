<?php

namespace Tests\Feature\Security;

use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgeRelatedResourceService;
use App\Services\ListingImport\Mls\MlsRelatedResources;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The test suite must never reach the live Bridge/Stellar API.
 *
 * WHY THIS TEST EXISTS, IN ONE PARAGRAPH
 * --------------------------------------
 * The Google Places incident (2026-07-05: 38,236 live requests, ~$1,223 in six
 * days, from the test suite) happened because Replit injects provider
 * credentials as system environment variables and PHPUnit will NOT overwrite a
 * variable already present in the process environment unless the entry carries
 * `force="true"`. Bridge credentials are injected the same way. The
 * Member/Office/OpenHouse enrichment added in the 2026-09-04 MLS parity work put
 * outbound calls inside the ordinary import path, which every import-flow test
 * exercises — so the same latent hazard became live, and the ListingImport suite
 * went from 1m47s to over 40 minutes of real API traffic before it was caught.
 *
 * `phpunit.xml` now blanks `BRIDGE_DATASET` and `BRIDGE_SERVER_TOKEN` and
 * switches `MLS_RELATED_RESOURCES_ENABLED` off, all with `force="true"`. This
 * test is what stops those entries being dropped, reordered, or written without
 * `force` — the single detail that decides whether the guard works at all.
 *
 * It does NOT stop a test from exercising the integration deliberately: a test
 * that sets `bridge.*` / `mls_related_resources.*` config and fakes the HTTP
 * client works exactly as before. What it stops is reaching the live provider by
 * accident.
 */
class BridgeCredentialGuardTest extends TestCase
{
    /** @test */
    public function bridge_credentials_are_blank_inside_the_test_suite(): void
    {
        foreach (['BRIDGE_DATASET', 'BRIDGE_SERVER_TOKEN'] as $key) {
            $this->assertSame(
                '',
                (string) env($key),
                "{$key} is populated inside the test suite. phpunit.xml must blank it with "
                . 'force="true" — without force, PHPUnit leaves a variable already present in the '
                . 'process environment untouched, which is exactly how the Google Places incident happened.'
            );
        }

        $this->assertEmpty(config('bridge.dataset'));
        $this->assertEmpty(config('bridge.token'));
    }

    /** @test */
    public function the_related_resource_enrichment_is_off_by_default_in_tests(): void
    {
        $this->assertFalse(
            (bool) config('mls_related_resources.enabled'),
            'Member/Office/OpenHouse enrichment must be off by default under test — it makes real '
            . 'outbound Bridge requests from inside the ordinary import path.'
        );

        $this->assertFalse(app(BridgeRelatedResourceService::class)->enabled());
    }

    /**
     * @test
     *
     * The guard proven at the seam that actually reaches the network, not only
     * at the config values that are supposed to prevent it.
     */
    public function no_request_leaves_the_suite_even_when_a_record_names_an_agent_and_office(): void
    {
        Http::fake();

        $record = [
            'ListingKey'     => 'GUARD-KEY',
            'ListingId'      => 'GUARD-MLS',
            'ListAgentKey'   => 'agent-key',
            'ListAgentMlsId' => 'AGENT-1',
            'ListOfficeKey'  => 'office-key',
            'ListOfficeMlsId' => 'OFFICE-1',
        ];

        $related = MlsRelatedResources::fetch($record, app(BridgeRelatedResourceService::class));

        $this->assertTrue($related->isEmpty());

        Http::assertNothingSent();
    }

    /**
     * @test
     *
     * With no credentials, the primary Property client refuses before it builds
     * a request — so even a test that switched the enrichment back on could not
     * reach Bridge without also supplying credentials deliberately.
     */
    public function the_property_client_refuses_without_credentials(): void
    {
        Http::fake();

        $api = app(BridgeApiService::class);

        $this->assertSame([], $api->fetchProperties(1, "ListingId eq 'X'"));
        $this->assertSame(BridgeApiService::FAILURE_NOT_CONFIGURED, $api->lastFailure());

        Http::assertNothingSent();
    }

    /**
     * @test
     *
     * The phpunit.xml entries must carry force="true". This is the assertion the
     * Google Places guard exists for, applied to Bridge: the entries are useless
     * without it, and their uselessness is invisible at a glance.
     */
    public function the_phpunit_entries_carry_force_true(): void
    {
        $xml = (string) file_get_contents(base_path('phpunit.xml'));

        foreach (['BRIDGE_DATASET', 'BRIDGE_SERVER_TOKEN', 'MLS_RELATED_RESOURCES_ENABLED'] as $key) {
            $this->assertMatchesRegularExpression(
                '/name="' . preg_quote($key, '/') . '"[^>]*force="true"/',
                $xml,
                "phpunit.xml must set {$key} with force=\"true\" — without it the entry does not "
                . 'override a variable already present in the process environment.'
            );
        }
    }
}
