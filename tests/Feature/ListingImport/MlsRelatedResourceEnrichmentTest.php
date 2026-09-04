<?php

namespace Tests\Feature\ListingImport;

use App\Services\Bridge\BridgeRelatedResourceService;
use App\Services\ListingImport\Mls\MlsFieldCatalog;
use App\Services\ListingImport\Mls\MlsRelatedResources;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Member / Office / OpenHouse enrichment.
 *
 * A live probe on 2026-09-04 (`mls:probe-resources --force-probe`) established
 * that this dataset exposes Member (79 fields), Office (55) and OpenHouse (36),
 * and does NOT expose Room or Unit (both HTTP 404). These tests pin the three
 * that exist, the two that do not, and — most importantly — that a failure in
 * any of them costs only the enrichment.
 */
class MlsRelatedResourceEnrichmentTest extends TestCase
{
    private const AGENT_KEY  = 'agentkey0000000000000000000000001';
    private const OFFICE_KEY = 'officekey000000000000000000000001';
    private const LISTING    = 'FIXTURE-LISTING-KEY';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'bridge.dataset'                   => 'testset',
            'bridge.token'                     => 'test-token',
            'mls_related_resources.enabled'    => true,
            'mls_related_resources.member'     => true,
            'mls_related_resources.office'     => true,
            'mls_related_resources.open_house' => true,
        ]);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'ListingKey'                     => self::LISTING,
            'ListingId'                      => 'FX-1',
            'ListAgentKey'                   => self::AGENT_KEY,
            'ListAgentMlsId'                 => 'AGENT-0001',
            'ListAgentFullName'              => 'Jordan Blake',
            'ListOfficeKey'                  => self::OFFICE_KEY,
            'ListOfficeMlsId'                => 'OFFICE-0001',
            'ListOfficeName'                 => 'Example Realty Group',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
        ], $overrides);
    }

    private function fakeAllResources(): void
    {
        Http::fake([
            '*/Member*' => Http::response(['value' => [[
                'MemberKey'                 => self::AGENT_KEY,
                'MemberFullName'            => 'Jordan Blake',
                'MemberDirectPhone'         => '555-0101',
                'MemberOfficePhone'         => '555-0102',
                'MemberEmail'               => 'jordan.blake@example.com',
                'MemberStateLicense'        => 'SL3512345',
                'MemberStateLicenseState'   => 'FL',
                'SocialMediaWebsiteUrlOrId' => 'https://jordanblake.example.com',
                'MemberLanguages'           => ['English', 'Spanish'],
                'MemberLoginId'             => 'SECRET_LOGIN',
                'STELLAR_MemberRetsSecurityClass' => 'SECRET_CLASS',
            ]]], 200),

            '*/Office*' => Http::response(['value' => [[
                'OfficeKey'                 => self::OFFICE_KEY,
                'OfficeName'                => 'Example Realty Group',
                'OfficePhone'               => '555-0199',
                'OfficeFax'                 => '555-0198',
                'OfficeEmail'               => 'info@example-realty.com',
                'OfficeAddress1'            => '100 Example Plaza',
                'OfficeCity'                => 'Clearwater',
                'OfficeStateOrProvince'     => 'FL',
                'OfficePostalCode'          => '33760',
                'SocialMediaWebsiteUrlOrId' => 'https://example-realty.com',
                'OfficeManagerKey'          => 'SECRET_MANAGER',
            ]]], 200),

            '*/OpenHouse*' => Http::response(['value' => [[
                'OpenHouseKey'          => 'OH-1',
                'ListingKey'            => self::LISTING,
                'OpenHouseDate'         => '2026-10-11',
                'OpenHouseStartTime'    => '2026-10-11T13:00:00.000Z',
                'OpenHouseEndTime'      => '2026-10-11T15:00:00.000Z',
                'OpenHouseType'         => 'Public',
                'AppointmentRequiredYN' => false,
                'ShowingAgentFirstName' => 'SECRET_SHOWING_AGENT',
            ]]], 200),
        ]);
    }

    private function enrich(array $record): MlsRelatedResources
    {
        return MlsRelatedResources::fetch($record, app(BridgeRelatedResourceService::class));
    }

    private function flatten(MlsSupplementalDetails $d): string
    {
        $out = '';

        foreach ($d->sections as $section) {
            $out .= $section['title'] . ' | ';
            foreach ($section['rows'] as $row) {
                $out .= $row['label'] . '=' . $row['value'] . ' | ';
            }
        }

        return $out;
    }

    // ─── The fields Property does not carry ──────────────────────────────────

    /**
     * @test
     *
     * The whole reason this layer exists. Every field asserted here is ABSENT
     * from the Property resource on all 1,224 cached records.
     */
    public function enrichment_supplies_contact_fields_the_property_record_does_not_have(): void
    {
        $this->fakeAllResources();

        $details  = MlsSupplementalDetails::fromRecord($this->record(), 'seller', $this->enrich($this->record()));
        $rendered = $this->flatten($details);

        $this->assertStringContainsString('Direct Phone=555-0101', $rendered);
        $this->assertStringContainsString('State Licence=SL3512345', $rendered);
        $this->assertStringContainsString('Website=https://jordanblake.example.com', $rendered);
        $this->assertStringContainsString('Languages=English, Spanish', $rendered);

        $this->assertStringContainsString('Brokerage Address=100 Example Plaza', $rendered);
        $this->assertStringContainsString('Brokerage Fax=555-0198', $rendered);
        $this->assertStringContainsString('Brokerage Email=info@example-realty.com', $rendered);
        $this->assertStringContainsString('Brokerage Website=https://example-realty.com', $rendered);
    }

    /** @test */
    public function open_houses_are_rendered_as_readable_events(): void
    {
        $this->fakeAllResources();

        $rendered = $this->flatten(
            MlsSupplementalDetails::fromRecord($this->record(), 'seller', $this->enrich($this->record()))
        );

        $this->assertStringContainsString('Open Houses', $rendered);
        $this->assertStringContainsString('Sun 11 Oct 2026', $rendered);
        $this->assertStringContainsString('1:00 PM – 3:00 PM', $rendered);
        $this->assertStringContainsString('Public', $rendered);
    }

    /**
     * @test
     *
     * The allow-list holds on the related resources too. Member and Office carry
     * MLS internals — login ids, RETS security classes, manager keys — and none
     * of them may reach a page.
     */
    public function related_resource_internals_never_reach_the_output(): void
    {
        $this->fakeAllResources();

        $blob = json_encode(
            MlsSupplementalDetails::fromRecord($this->record(), 'seller', $this->enrich($this->record()))->toArray()
        );

        foreach (['SECRET_LOGIN', 'SECRET_CLASS', 'SECRET_MANAGER', 'SECRET_SHOWING_AGENT'] as $needle) {
            $this->assertStringNotContainsString($needle, (string) $blob, "{$needle} leaked from a related resource");
        }
    }

    /**
     * @test
     *
     * Property is the stronger source and wins ties. The Member row must not
     * restate a name the listing already attributes.
     */
    public function enrichment_does_not_duplicate_what_property_already_said(): void
    {
        $this->fakeAllResources();

        $rendered = $this->flatten(
            MlsSupplementalDetails::fromRecord($this->record(), 'seller', $this->enrich($this->record()))
        );

        $this->assertSame(
            1,
            substr_count($rendered, 'Listing Agent=Jordan Blake'),
            'The agent name was rendered twice — Property and Member both claimed it'
        );
    }

    // ─── Failure posture ─────────────────────────────────────────────────────

    /**
     * @test
     *
     * A dead resource costs that section and nothing else. This is the property
     * the brief names explicitly: the Property import must still succeed.
     */
    public function a_failing_resource_costs_only_its_own_section(): void
    {
        Http::fake([
            '*/Member*'    => Http::response('', 500),
            '*/Office*'    => Http::response([], 200),
            '*/OpenHouse*' => Http::response('', 503),
        ]);

        $details = MlsSupplementalDetails::fromRecord($this->record(), 'seller', $this->enrich($this->record()));

        $this->assertFalse($details->isEmpty(), 'A failed enrichment must not empty the payload');
        $this->assertStringContainsString('Listing Agent=Jordan Blake', $this->flatten($details));
        $this->assertSame([], $details->group('related'));
    }

    /** @test */
    public function a_transport_failure_is_swallowed(): void
    {
        Http::fake(fn () => throw new \RuntimeException('connection refused'));

        $related = $this->enrich($this->record());

        $this->assertTrue($related->isEmpty());
    }

    /** @test */
    public function enrichment_is_skipped_entirely_when_disabled(): void
    {
        config(['mls_related_resources.enabled' => false]);
        Http::fake();

        $this->assertTrue($this->enrich($this->record())->isEmpty());

        Http::assertNothingSent();
    }

    /** @test */
    public function a_listing_with_no_agent_or_office_key_asks_for_nothing(): void
    {
        Http::fake();

        $related = $this->enrich([
            'ListingKey' => '',
            // no ListAgentKey, no ListAgentMlsId, no office identity at all
        ]);

        $this->assertTrue($related->isEmpty());
        Http::assertNothingSent();
    }

    // ─── N+1 avoidance ───────────────────────────────────────────────────────

    /**
     * @test
     *
     * THE N+1 GUARANTEE. The cache is keyed on the office key, not the listing,
     * so a brokerage's second listing costs zero requests. One office listing a
     * hundred properties is the normal case, and it must not be a hundred
     * lookups.
     */
    public function a_second_listing_from_the_same_office_costs_no_extra_requests(): void
    {
        $this->fakeAllResources();

        $service = app(BridgeRelatedResourceService::class);

        $service->office(self::OFFICE_KEY);
        $first = $service->requestsMade();

        $service->office(self::OFFICE_KEY);
        $service->office(self::OFFICE_KEY);

        $this->assertSame($first, $service->requestsMade(), 'The office lookup was not cached');
    }

    /**
     * @test
     *
     * An empty answer is cached too. A listing whose agent has no Member row
     * must not re-ask on every render — that is the same N+1, arrived at from
     * the other direction.
     */
    public function an_empty_answer_is_cached_rather_than_re_asked(): void
    {
        Http::fake(['*/Member*' => Http::response(['value' => []], 200)]);

        $service = app(BridgeRelatedResourceService::class);

        $service->member('missing-key');
        $after = $service->requestsMade();

        $service->member('missing-key');

        $this->assertSame($after, $service->requestsMade());
    }

    /**
     * @test
     *
     * The per-import ceiling is a backstop against a future caller looping, and
     * it is counted after the cache so it cannot be exhausted by cache hits.
     */
    public function the_per_import_request_ceiling_is_enforced(): void
    {
        config(['mls_related_resources.max_requests_per_import' => 2]);
        $this->fakeAllResources();

        $service = app(BridgeRelatedResourceService::class);

        $service->member('k1');
        $service->member('k2');
        $service->member('k3');
        $service->member('k4');

        $this->assertSame(2, $service->requestsMade());
    }

    // ─── Unavailable resources ───────────────────────────────────────────────

    /**
     * @test
     *
     * Room and Unit are 404 on this dataset. The catalog records that, and the
     * point of recording it is that nobody synthesises them from Property
     * counts: `RoomsTotal` is a number, not a list of rooms.
     */
    public function unavailable_resources_are_documented_and_never_synthesised(): void
    {
        $this->assertArrayHasKey('Room', MlsFieldCatalog::UNAVAILABLE_RESOURCES);
        $this->assertArrayHasKey('Unit', MlsFieldCatalog::UNAVAILABLE_RESOURCES);

        foreach (MlsFieldCatalog::UNAVAILABLE_RESOURCES as $resource => $reason) {
            $this->assertStringContainsString('404', $reason, "{$resource} must record what was actually tried");
        }

        $this->fakeAllResources();

        $rendered = $this->flatten(
            MlsSupplementalDetails::fromRecord(
                $this->record(['RoomsTotal' => 7, 'NumberOfUnitsTotal' => 30]),
                'seller',
                $this->enrich($this->record())
            )
        );

        // The COUNTS are legitimate Property facts and do render.
        $this->assertStringContainsString('Total Rooms=7', $rendered);
        $this->assertStringContainsString('Total Units=30', $rendered);

        // No fabricated roster sections.
        $this->assertStringNotContainsString('Rooms |', $rendered);
        $this->assertStringNotContainsString('Units |', $rendered);
    }

    /**
     * @test
     *
     * A withdrawn listing publishes no enrichment either — the related sections
     * extend the contacts, and inherit their gate.
     */
    public function a_withdrawn_listing_publishes_no_enrichment(): void
    {
        $this->fakeAllResources();

        $record  = $this->record(['InternetEntireListingDisplayYN' => false]);
        $details = MlsSupplementalDetails::fromRecord($record, 'seller', $this->enrich($record));

        $this->assertSame([], $details->group('related'));
        $this->assertSame([], $details->group('contacts'));
    }
}
