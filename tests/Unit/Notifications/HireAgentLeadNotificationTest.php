<?php

namespace Tests\Unit\Notifications;

use App\Models\HireAgentLead;
use App\Notifications\HireAgentLeadNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * HireAgentLeadNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class HireAgentLeadNotificationTest extends TestCase
{
    private MockInterface $lead;
    private HireAgentLeadNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = Mockery::mock(HireAgentLead::class)->makePartial();
        $this->lead->id                  = 80;
        $this->lead->target_agent_id     = 5;
        $this->lead->source_listing_type = null;
        $this->lead->source_listing_id   = null;
        $this->lead->source_listing_role = null;
        $this->lead->source_property_type = null;
        $this->lead->lead_source         = 'widget';
        $this->lead->source_listing_title = null;
        $this->lead->source_listing_url   = null;
        $this->lead->representation_type = 'seller';
        $this->lead->selected_property_type = 'single_family';
        $this->lead->requester_name      = 'Alice Smith';
        $this->lead->requester_email     = 'alice@example.com';
        $this->lead->requester_phone     = null;
        $this->lead->preset_match_status = 'none';
        $this->lead->status              = 'new';
        $this->lead->message             = null;
        $this->lead->created_at          = null;

        $this->lead->shouldReceive('representationTypeLabel')->andReturn('Seller Representation');
        $this->lead->shouldReceive('selectedPropertyTypeLabel')->andReturn('Single Family');
        $this->lead->shouldReceive('sourceListingTypeLabel')->andReturn('');
        $this->lead->shouldReceive('presetMatchStatusLabel')->andReturn('None');
        $this->lead->shouldReceive('getAttribute')->with('id')->andReturn(80);
        $this->lead->shouldReceive('getAttribute')->with('target_agent_id')->andReturn(5);
        $this->lead->shouldReceive('getAttribute')->with('source_listing_type')->andReturn(null);
        $this->lead->shouldReceive('getAttribute')->with('source_listing_id')->andReturn(null);
        $this->lead->shouldReceive('getAttribute')->with('source_listing_role')->andReturn(null);
        $this->lead->shouldReceive('getAttribute')->with('source_property_type')->andReturn(null);
        $this->lead->shouldReceive('getAttribute')->with('lead_source')->andReturn('widget');
        $this->lead->shouldReceive('getAttribute')->with('source_listing_title')->andReturn(null);
        $this->lead->shouldReceive('getAttribute')->with('source_listing_url')->andReturn(null);
        $this->lead->shouldReceive('getAttribute')->with('representation_type')->andReturn('seller');
        $this->lead->shouldReceive('getAttribute')->with('selected_property_type')->andReturn('single_family');
        $this->lead->shouldReceive('getAttribute')->with('requester_name')->andReturn('Alice Smith');
        $this->lead->shouldReceive('getAttribute')->with('requester_email')->andReturn('alice@example.com');
        $this->lead->shouldReceive('getAttribute')->with('requester_phone')->andReturn(null);
        $this->lead->shouldReceive('getAttribute')->with('preset_match_status')->andReturn('none');
        $this->lead->shouldReceive('getAttribute')->with('status')->andReturn('new');
        $this->lead->shouldReceive('getAttribute')->with('message')->andReturn(null);
        $this->lead->shouldReceive('getAttribute')->with('created_at')->andReturn(null);

        $this->notification = new HireAgentLeadNotification($this->lead, null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function notifiable(): object
    {
        return new \stdClass();
    }

    public function test_to_database_has_message_key(): void
    {
        $data = $this->notification->toDatabase($this->notifiable());
        $this->assertArrayHasKey('message', $data);
    }

    public function test_to_database_message_is_not_generic(): void
    {
        $data = $this->notification->toDatabase($this->notifiable());
        $this->assertNotEmpty($data['message']);
        $this->assertNotSame('You have a notification', $data['message']);
        $this->assertNotSame('New Notification', $data['message']);
    }

    public function test_to_database_message_is_hire_request(): void
    {
        $data = $this->notification->toDatabase($this->notifiable());
        $this->assertSame('New agent hire request received.', $data['message']);
    }

    public function test_to_database_has_context_line(): void
    {
        $data = $this->notification->toDatabase($this->notifiable());
        $this->assertArrayHasKey('context_line', $data);
        $this->assertNotEmpty($data['context_line']);
    }

    public function test_to_database_context_line_contains_role_and_property(): void
    {
        $data = $this->notification->toDatabase($this->notifiable());
        $this->assertStringContainsString('Seller Representation', $data['context_line']);
        $this->assertStringContainsString('Single Family', $data['context_line']);
    }

    public function test_to_database_type_is_hire_agent_lead(): void
    {
        $data = $this->notification->toDatabase($this->notifiable());
        $this->assertSame('hire_agent_lead', $data['type']);
    }
}
