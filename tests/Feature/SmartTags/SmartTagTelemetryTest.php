<?php

namespace Tests\Feature\SmartTags;

use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\SmartTags\SmartTagTelemetry;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\Feature\SmartTags\Concerns\WiresSmartTags;
use Tests\TestCase;

/**
 * Phase 2 — what the log line may and may not carry.
 *
 * The prohibition that matters is CONTENT: a Smart Tag line records identifiers,
 * counts and outcomes, never the prose it read. These tests put distinctive
 * sentences into a listing's description and assert none of it reaches a log.
 */
class SmartTagTelemetryTest extends TestCase
{
    use DatabaseTransactions;
    use MakesSmartTagListings;
    use WiresSmartTags;

    private const DISTINCTIVE = 'Zorblaxian quartz countertops and a private pool by the marina';

    /** @var array<int, array{level: string, message: string, context: array}> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->enableSmartTags();

        $this->lines = [];
        Log::listen(function ($event) {
            $this->lines[] = [
                'level'   => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });
    }

    /** @return array<int, array> lines this subsystem wrote */
    private function smartTagLines(): array
    {
        return array_values(array_filter(
            $this->lines,
            static fn (array $l) => $l['message'] === SmartTagTelemetry::CHANNEL
        ));
    }

    /** @test */
    public function a_derivation_writes_one_structured_line_with_identifiers_and_counts(): void
    {
        $listing = $this->sellerListing($this->sellerOwner(), [
            'property_type'      => 'Residential',
            'waterfront'         => 'Yes',
            'additional_details' => self::DISTINCTIVE,
        ]);

        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $lines = $this->smartTagLines();
        $this->assertCount(1, $lines, 'Expected exactly one smart_tags line.');

        $context = $lines[0]['context'];

        $this->assertSame('seller_agent', $context['listing_type']);
        $this->assertSame($listing->id, $context['listing_id']);
        $this->assertSame('residential.sale', $context['context']);
        $this->assertSame(SmartTagTelemetry::ENTRY_SELLER_PUBLISH, $context['entry_point']);
        $this->assertTrue($context['structured']);
        $this->assertIsInt($context['tags']);
        $this->assertIsInt($context['conflicts']);
        $this->assertIsFloat($context['duration_ms']);
        $this->assertNull($context['error_class']);
        $this->assertSame(12, strlen((string) $context['tagger_version']),
            'The tagger version must be abbreviated, not the full hash.');
    }

    /** @test */
    public function no_part_of_the_listing_description_ever_reaches_a_log_line(): void
    {
        $listing = $this->sellerListing($this->sellerOwner(), [
            'property_type'      => 'Residential',
            'additional_details' => self::DISTINCTIVE,
        ]);

        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $encoded = json_encode($this->lines);

        foreach (['Zorblaxian', 'quartz countertops', 'private pool', 'marina', self::DISTINCTIVE] as $fragment) {
            $this->assertStringNotContainsString($fragment, (string) $encoded,
                "A log line carried listing prose: {$fragment}");
        }
    }

    /** @test */
    public function a_failure_records_the_exception_class_and_no_message(): void
    {
        $listing = $this->sellerListing($this->sellerOwner(), [
            'property_type'      => 'Residential',
            'additional_details' => self::DISTINCTIVE,
        ]);

        $this->app->bind(\App\Services\SmartTags\SmartTagDerivationService::class,
            fn () => new \Tests\Feature\SmartTags\Doubles\ThrowingDerivationService());

        $this->app->make(SmartTagLifecycle::class)
            ->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $lines = $this->smartTagLines();
        $this->assertCount(1, $lines);

        $this->assertSame('warning', $lines[0]['level'], 'A failure should not be logged at info.');
        $this->assertSame(SmartTagTelemetry::FAILED, $lines[0]['context']['outcome']);
        $this->assertSame(\RuntimeException::class, $lines[0]['context']['error_class']);

        // The exception's own message is not a field on the line.
        $this->assertStringNotContainsString('deriver exploded', (string) json_encode($lines));
    }

    /** @test */
    public function a_suppressed_landlord_description_is_reported_as_suppressed(): void
    {
        $listing = $this->landlordListing($this->landlordOwner(), [
            'property_type'      => 'Residential Property',
            'additional_details' => 'Quartz countertops. No emotional support animals.',
        ]);

        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_LANDLORD_PUBLISH);

        $lines = $this->smartTagLines();
        $this->assertCount(1, $lines);
        $this->assertSame(SmartTagTelemetry::DESCRIPTION_SUPPRESSED, $lines[0]['context']['outcome']);

        // And the suppressed sentence itself is not in the line.
        $this->assertStringNotContainsString('emotional support', (string) json_encode($lines));
    }

    /** @test */
    public function a_listing_with_no_supported_context_is_reported_as_such(): void
    {
        $listing = $this->sellerListing($this->sellerOwner(), ['property_type' => 'Houseboat']);

        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $lines = $this->smartTagLines();
        $this->assertCount(1, $lines);
        $this->assertSame(SmartTagTelemetry::NO_CONTEXT, $lines[0]['context']['outcome']);
    }

    /** @test */
    public function a_hire_agent_row_is_reported_as_not_an_offer_listing(): void
    {
        $listing = $this->sellerListing($this->sellerOwner(), ['property_type' => 'Residential'], workflow: 'hire_agent');

        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $lines = $this->smartTagLines();
        $this->assertCount(1, $lines);
        $this->assertSame(SmartTagTelemetry::NOT_OFFER_LISTING, $lines[0]['context']['outcome']);
    }

    /** @test */
    public function a_closed_gate_writes_no_line_at_all(): void
    {
        $listing = $this->sellerListing($this->sellerOwner(), ['property_type' => 'Residential']);

        $this->disableSmartTags();

        app(SmartTagLifecycle::class)->deriveForNativeSilently($listing, SmartTagTelemetry::ENTRY_SELLER_PUBLISH);

        $this->assertSame([], $this->smartTagLines(),
            'A disabled subsystem should be silent, not chatty.');
    }

    /** @test */
    public function mls_public_remarks_never_appear_in_a_log_line(): void
    {
        $remarks = 'Zorblaxian marina views, quartz countertops and a private pool.';

        (new \App\Services\Bridge\BridgePropertyNormalizer())->upsert([
            'ListingKey'      => 'PHPUNIT-TELEM-1',
            'ListingId'       => 'PHPUNIT-TELEM-1-id',
            'StandardStatus'  => 'Active',
            'PropertyType'    => 'Residential',
            'UnparsedAddress' => '1 Telemetry Way',
            'City'            => 'PhpunitTelemetryCity',
            'StateOrProvince' => 'FL',
            'PostalCode'      => '33601',
            'WaterfrontYN'    => true,
            'PublicRemarks'   => $remarks,
        ]);

        $row = \App\Models\BridgeProperty::query()->where('listing_key', 'PHPUNIT-TELEM-1')->firstOrFail();

        app(SmartTagLifecycle::class)->deriveForBridgeSilently($row);

        $encoded = (string) json_encode($this->lines);

        foreach (['Zorblaxian', 'marina views', 'quartz countertops', $remarks] as $fragment) {
            $this->assertStringNotContainsString($fragment, $encoded,
                "A log line carried MLS PublicRemarks: {$fragment}");
        }
    }
}
