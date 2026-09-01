<?php

namespace Tests\Feature\Console;

use App\Console\Commands\RetireTenantTypePreference;
use App\Exports\ListingFieldMaps\LandlordFieldMap;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one-time remediation command.
 *
 * The mapping rules are exercised against the pure remediateLandlordBlock() so every case is
 * covered without a listing, a user or a production database. The dry-run, idempotence and backup
 * behaviours need real rows, so those use the SQLite in-memory database the suite already runs on.
 */
class RetireTenantTypePreferenceCommandTest extends TestCase
{
    use RefreshDatabase;

    private RetireTenantTypePreference $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new RetireTenantTypePreference();
    }

    // ── Mapping rules ────────────────────────────────────────────────────────

    /**
     * @test
     * @dataProvider residentialValues
     */
    public function residential_values_are_deleted_and_never_translated(string $value): void
    {
        $out = $this->command->remediateLandlordBlock([
            'tenant_type_preference' => $value,
            'communication_style'    => 'Email Only',
        ], 'Residential Property');

        $this->assertTrue($out['changed']);
        $this->assertArrayNotHasKey('tenant_type_preference', $out['block']);
        $this->assertArrayNotHasKey('preferred_business_use', $out['block'],
            "Residential value '{$value}' must be deleted, never translated into another field.");
        $this->assertSame('Email Only', $out['block']['communication_style']);
    }

    public static function residentialValues(): array
    {
        return [
            ['Individual / Family'], ['Young Professionals'], ['Students'],
            ['Corporate / Relocation'], ['Small Business'], ['Retail Business'],
            ['Office Tenant'], ['No Preference'], ['Some Unrecognised Value'],
        ];
    }

    /**
     * @test
     * @dataProvider commercialMappings
     */
    public function commercial_deterministic_mappings_carry_forward(string $stored, string $expected): void
    {
        $out = $this->command->remediateLandlordBlock(
            ['tenant_type_preference' => $stored],
            'Commercial Property'
        );

        $this->assertSame([$expected], $out['block']['preferred_business_use']);
        $this->assertArrayNotHasKey('tenant_type_preference', $out['block']);
    }

    public static function commercialMappings(): array
    {
        return [
            'office tenant' => ['Office Tenant', 'Office'],
            'retail business' => ['Retail Business', 'Retail'],
        ];
    }

    /**
     * @test
     * @dataProvider commercialDiscards
     */
    public function commercial_values_without_a_deterministic_mapping_are_discarded(string $value): void
    {
        $out = $this->command->remediateLandlordBlock(
            ['tenant_type_preference' => $value],
            'Commercial Property'
        );

        $this->assertArrayNotHasKey('preferred_business_use', $out['block'],
            "'{$value}' has no unambiguous business use and must be discarded, not guessed at.");
        $this->assertArrayNotHasKey('tenant_type_preference', $out['block']);
    }

    public static function commercialDiscards(): array
    {
        return [
            // A SIZE, not a use — it maps equally to Retail, Office, Personal Services or
            // Professional Services, so any mapping would be a value the landlord never chose.
            'small business' => ['Small Business'],
            ['Individual / Family'], ['Young Professionals'], ['Students'],
            ['Corporate / Relocation'], ['No Preference'], ['Unrecognised'],
        ];
    }

    /** @test */
    public function the_other_free_text_is_never_carried_into_the_new_field(): void
    {
        foreach (['Commercial Property', 'Residential Property'] as $type) {
            $out = $this->command->remediateLandlordBlock([
                'tenant_type_preference'       => 'Office Tenant',
                'tenant_type_preference_other' => 'Quiet professional tenants only',
            ], $type);

            $this->assertArrayNotHasKey('preferred_business_use_other', $out['block'],
                'Prose collected under a "preferred tenant type" prompt cannot be assumed to '
                . 'describe a lawful business use.');
            $this->assertArrayNotHasKey('tenant_type_preference_other', $out['block']);
        }
    }

    /**
     * @test
     * @dataProvider unknownPropertyTypes
     */
    public function an_unknown_or_missing_property_type_is_treated_as_residential($type): void
    {
        $out = $this->command->remediateLandlordBlock(
            ['tenant_type_preference' => 'Office Tenant'],
            $type
        );

        $this->assertArrayNotHasKey('preferred_business_use', $out['block'],
            'Anything that is not exactly "Commercial Property" must take the conservative '
            . 'residential treatment: property_type is EAV and can be absent on an older row.');
    }

    public static function unknownPropertyTypes(): array
    {
        return [
            'null' => [null], 'empty' => [''], 'legacy' => ['Residential'],
            'income' => ['Income Property'], 'near miss' => ['Commercial'],
        ];
    }

    /** @test */
    public function leasing_goal_values_are_remapped(): void
    {
        $high = $this->command->remediateLandlordBlock(
            ['primary_leasing_goal' => 'High-Quality Tenant Profile'], 'Residential Property'
        );
        $this->assertSame('Reliable Rent Collection', $high['block']['primary_leasing_goal']);
        $this->assertTrue($high['changed']);

        $stable = $this->command->remediateLandlordBlock(
            ['primary_leasing_goal' => 'Long-Term Stable Tenant'], 'Residential Property'
        );
        $this->assertSame('Long-Term Tenancy', $stable['block']['primary_leasing_goal']);
    }

    /** @test */
    public function a_current_leasing_goal_is_left_alone(): void
    {
        $out = $this->command->remediateLandlordBlock(
            ['primary_leasing_goal' => 'Maximize Monthly Rent'], 'Residential Property'
        );

        $this->assertFalse($out['changed']);
        $this->assertSame('Maximize Monthly Rent', $out['block']['primary_leasing_goal']);
    }

    /** @test */
    public function an_existing_business_use_answer_is_merged_not_clobbered(): void
    {
        $out = $this->command->remediateLandlordBlock([
            'tenant_type_preference' => 'Office Tenant',
            'preferred_business_use' => ['Retail'],
        ], 'Commercial Property');

        $this->assertSame(['Retail', 'Office'], $out['block']['preferred_business_use']);
    }

    /** @test */
    public function a_mapping_already_applied_is_not_duplicated(): void
    {
        $out = $this->command->remediateLandlordBlock([
            'tenant_type_preference' => 'Office Tenant',
            'preferred_business_use' => ['Office'],
        ], 'Commercial Property');

        $this->assertSame(['Office'], $out['block']['preferred_business_use']);
    }

    /** @test */
    public function empty_and_absent_values_are_handled_without_error(): void
    {
        $absent = $this->command->remediateLandlordBlock(['communication_style' => 'Email Only'], null);
        $this->assertFalse($absent['changed'], 'A block with neither retired key needs no write.');

        $empty = $this->command->remediateLandlordBlock(['tenant_type_preference' => ''], null);
        $this->assertTrue($empty['changed'], 'An empty retired key is still a key to remove.');
        $this->assertArrayNotHasKey('tenant_type_preference', $empty['block']);
    }


    // ── Command behaviour: dry run is read-only ──────────────────────────────

    /** @test */
    public function it_defaults_to_a_dry_run_and_writes_nothing(): void
    {
        $listing = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference' => 'Students',
            'primary_leasing_goal'   => 'High-Quality Tenant Profile',
        ]);

        // expectsOutputToContain() is Laravel 9+; on Laravel 8 the only built-in expectation
        // matches a whole line, so the banner is asserted verbatim.
        $this->artisan('hireagent:retire-tenant-type')
            ->expectsOutput('hireagent:retire-tenant-type — DRY RUN (nothing will be written; pass --write to apply)')
            ->expectsOutput('Dry run complete. Nothing was written. Re-run with --write to apply.')
            ->assertExitCode(0);

        $block = $this->storedBlock($listing);
        $this->assertSame('Students', $block['tenant_type_preference'],
            'A dry run must not touch stored data.');
        $this->assertSame('High-Quality Tenant Profile', $block['primary_leasing_goal']);
    }

    /**
     * @test
     *
     * The rollback record is itself application data, so "read-only" has to include not creating
     * one. Phase B is the only thing that writes a backup and it sits entirely behind --write.
     */
    public function a_dry_run_creates_no_durable_backup_row(): void
    {
        $listing = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference' => 'Students',
        ]);

        $this->artisan('hireagent:retire-tenant-type')->assertExitCode(0);

        $this->assertNull($this->backupEnvelope($listing),
            'A dry run must not create a rollback record any more than it modifies a listing.');
        $this->assertDatabaseMissing('landlord_agent_auction_metas', [
            'meta_key' => self::BACKUP_KEY,
        ]);
    }

    /** @test */
    public function a_dry_run_does_not_touch_listing_or_meta_rows(): void
    {
        $listing = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference' => 'Students',
        ]);

        $metaIdsBefore  = $this->metaRowSnapshot($listing);
        $updatedAtBefore = $listing->fresh()->updated_at;

        $this->artisan('hireagent:retire-tenant-type')->assertExitCode(0);

        $this->assertSame($metaIdsBefore, $this->metaRowSnapshot($listing),
            'No meta row may be created, removed or edited by a dry run.');
        $this->assertEquals($updatedAtBefore, $listing->fresh()->updated_at,
            'A dry run must not bump the listing timestamp.');
    }

    /** @test */
    public function listing_backups_is_read_only(): void
    {
        $listing = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference' => 'Students',
        ]);
        $before = $this->metaRowSnapshot($listing);

        $this->artisan('hireagent:retire-tenant-type', ['--list-backups' => true])->assertExitCode(0);

        $this->assertSame($before, $this->metaRowSnapshot($listing));
    }

    // ── Command behaviour: write mode ────────────────────────────────────────

    /** @test */
    public function it_remediates_only_when_write_is_passed(): void
    {
        $listing = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference'       => 'Young Professionals',
            'tenant_type_preference_other' => 'Quiet tenants',
            'primary_leasing_goal'         => 'High-Quality Tenant Profile',
            'communication_style'          => 'Email Only',
        ]);

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);

        $block = $this->storedBlock($listing);
        $this->assertArrayNotHasKey('tenant_type_preference', $block);
        $this->assertArrayNotHasKey('tenant_type_preference_other', $block);
        $this->assertSame('Reliable Rent Collection', $block['primary_leasing_goal']);
        $this->assertSame('Email Only', $block['communication_style'],
            'Untouched keys must survive the rewrite.');
    }

    /**
     * @test
     *
     * The durable rollback record: a row in the same table as the data it protects, holding the
     * exact original bytes and a checksum over them.
     */
    public function the_original_blob_is_backed_up_in_the_database(): void
    {
        $listing  = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference' => 'Students',
        ]);
        $original = $listing->fresh()->info('compatibility_preferences');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);

        $envelope = $this->backupEnvelope($listing);

        $this->assertIsArray($envelope, 'A --write run must leave a rollback record in the database.');
        $this->assertSame('fh-tenant-type-backup-v1', $envelope['schema']);
        $this->assertSame($original, $envelope['original'],
            'The backup must hold the original bytes exactly, not a re-encoding of them.');
        $this->assertSame(hash('sha256', $original), $envelope['sha256']);
        $this->assertNotSame('', $envelope['run_id']);
    }

    /**
     * @test
     *
     * THE ORDERING GUARANTEE, PROVEN BY INTERRUPTION.
     *
     * Two affected listings; backup storage fails on the second. If backups and writes were
     * interleaved, listing one would already be remediated by the time listing two's backup was
     * attempted. Both listings are untouched, so no write can precede the last backup.
     */
    public function no_listing_is_modified_before_every_backup_has_been_written(): void
    {
        $first  = $this->listingWithBlock('Residential Property', ['tenant_type_preference' => 'Students']);
        $second = $this->listingWithBlock('Residential Property', ['tenant_type_preference' => 'Young Professionals']);

        $this->registerCommandFailingOnBackupCall(2);

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(1);

        $this->assertSame('Students', $this->storedBlock($first)['tenant_type_preference'],
            'A listing whose backup succeeded must still not be remediated while a later backup failed.');
        $this->assertSame('Young Professionals', $this->storedBlock($second)['tenant_type_preference']);
    }

    /** @test */
    public function backup_failure_causes_zero_remediation_writes(): void
    {
        $listing = $this->listingWithBlock('Residential Property', ['tenant_type_preference' => 'Students']);

        $this->registerCommandFailingOnBackupCall(1);

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(1);

        $this->assertSame('Students', $this->storedBlock($listing)['tenant_type_preference']);
        $this->assertNull($this->backupEnvelope($listing));
    }

    /**
     * @test
     *
     * A backup that cannot be read back is not a backup. A pre-existing envelope whose checksum
     * does not match its own contents refuses the whole run rather than remediating on top of an
     * unusable rollback path.
     */
    public function backup_verification_failure_causes_zero_remediation_writes(): void
    {
        $listing = $this->listingWithBlock('Residential Property', ['tenant_type_preference' => 'Students']);

        $listing->saveMeta(self::BACKUP_KEY, json_encode([
            'schema'   => 'fh-tenant-type-backup-v1',
            'run_id'   => 'fhrt-corrupt',
            'original' => '{"landlord_specific":{"tenant_type_preference":"Students"}}',
            'sha256'   => hash('sha256', 'something else entirely'),
        ]));

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(1);

        $this->assertSame('Students', $this->storedBlock($listing)['tenant_type_preference'],
            'An unverifiable rollback record must stop remediation, not be remediated around.');
    }

    /** @test */
    public function it_is_idempotent(): void
    {
        $listing = $this->listingWithBlock('Commercial Property', [
            'tenant_type_preference' => 'Office Tenant',
        ]);

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);
        $afterFirst = $this->storedBlock($listing);

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])
            ->expectsOutput('No listing needs remediation. (This is the expected result of a second run.)')
            ->assertExitCode(0);

        $this->assertSame($afterFirst, $this->storedBlock($listing));
        $this->assertSame(['Office'], $afterFirst['preferred_business_use']);
    }

    /**
     * @test
     *
     * The envelope holds the value the landlord actually submitted. A second run that finds a
     * backup already present leaves it alone, so the path back to the true original survives any
     * number of re-dirty / re-remediate cycles.
     */
    public function re_running_remediation_never_overwrites_an_existing_original_backup(): void
    {
        $listing  = $this->listingWithBlock('Residential Property', ['tenant_type_preference' => 'Students']);
        $original = $listing->fresh()->info('compatibility_preferences');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);
        $firstEnvelope = $this->backupEnvelope($listing);

        // The application re-dirties the row (a stale tab, a rolled-back deploy).
        $listing->saveMeta('compatibility_preferences', json_encode([
            'landlord_specific' => ['tenant_type_preference' => 'Corporate / Relocation'],
        ]));

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);
        $secondEnvelope = $this->backupEnvelope($listing);

        $this->assertSame($original, $secondEnvelope['original'],
            'The stored original must remain the FIRST one captured, not the second run\'s input.');
        $this->assertSame($firstEnvelope['run_id'], $secondEnvelope['run_id'],
            'The envelope keeps the run that created it, so a restore addresses the right run.');
        $this->assertSame(1, $this->backupRowCount($listing),
            'One rollback record per listing, never a second.');
    }

    /**
     * @test
     *
     * The backup lives in landlord_agent_auction_metas, and nothing in the application resolves
     * its key: every consumer reads named keys, `$auction->get->namedKey`, or an explicit field
     * whitelist. The detail page is the proof that matters, since that is the surface the
     * retirement exists to clear.
     */
    public function the_backup_row_is_invisible_to_runtime_listing_reads(): void
    {
        $listing = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference' => 'Students',
            'negotiation_style'      => 'ZZPUBLICMARKERZZ',
        ]);
        $listing->saveMeta('address', '100 Test Street');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);

        $this->assertIsArray($this->backupEnvelope($listing), 'Precondition: the backup row exists.');

        $url = route('landlord.agent.auction.view', $listing->id);

        foreach ([null, $listing->user] as $viewer) {
            $response = $viewer ? $this->actingAs($viewer)->get($url) : $this->get($url);
            $response->assertOk();
            $response->assertDontSee('Students', false);
            $response->assertDontSee(self::BACKUP_KEY, false);
            // Positive control: an absence assertion against a page that renders nothing passes
            // for the wrong reason.
            $response->assertSee('ZZPUBLICMARKERZZ', false);
        }

        $this->assertNotContains(
            self::BACKUP_KEY,
            $this->flattenedFieldMapKeys(),
            'The backup key must not be reachable through the listing PDF packet either.'
        );
    }

    // ── Restore ─────────────────────────────────────────────────────────────

    /** @test */
    public function original_blobs_can_be_restored_exactly(): void
    {
        $listing  = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference' => 'Students',
        ]);
        $original = $listing->fresh()->info('compatibility_preferences');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);
        $this->assertArrayNotHasKey('tenant_type_preference', $this->storedBlock($listing));

        $runId = $this->backupEnvelope($listing)['run_id'];

        $this->artisan('hireagent:retire-tenant-type', ['--restore' => $runId])->assertExitCode(0);

        $this->assertSame($original, $listing->fresh()->info('compatibility_preferences'),
            'Deleting JSON keys has no down(); the backup IS the rollback path.');
    }

    /** @test */
    public function restore_is_idempotent(): void
    {
        $listing  = $this->listingWithBlock('Residential Property', ['tenant_type_preference' => 'Students']);
        $original = $listing->fresh()->info('compatibility_preferences');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);
        $runId = $this->backupEnvelope($listing)['run_id'];

        $this->artisan('hireagent:retire-tenant-type', ['--restore' => $runId])->assertExitCode(0);
        $this->artisan('hireagent:retire-tenant-type', ['--restore' => $runId])->assertExitCode(0);

        $this->assertSame($original, $listing->fresh()->info('compatibility_preferences'));
        $this->assertSame(1, $this->backupRowCount($listing),
            'Restore leaves the rollback record in place so it can be repeated or audited.');
    }

    /**
     * @test
     *
     * No default, no "latest", no guess. With several runs stored, silently choosing one is how
     * the wrong rollback gets applied.
     */
    public function restore_refuses_an_unknown_run_id_and_changes_nothing(): void
    {
        $listing = $this->listingWithBlock('Residential Property', ['tenant_type_preference' => 'Students']);

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);
        $remediated = $listing->fresh()->info('compatibility_preferences');

        $this->artisan('hireagent:retire-tenant-type', ['--restore' => 'fhrt-not-a-real-run'])
            ->assertExitCode(1);

        $this->assertSame($remediated, $listing->fresh()->info('compatibility_preferences'));
    }

    /** @test */
    public function restore_with_no_backups_at_all_fails_rather_than_succeeding_quietly(): void
    {
        $this->artisan('hireagent:retire-tenant-type', ['--restore' => 'fhrt-anything'])
            ->assertExitCode(1);
    }

    /**
     * @test
     *
     * Fail closed: an envelope we cannot decode is an envelope whose run we cannot rule out, so
     * one damaged row refuses every restore until someone looks at it.
     */
    public function restore_refuses_while_any_backup_row_is_unreadable(): void
    {
        $good = $this->listingWithBlock('Residential Property', ['tenant_type_preference' => 'Students']);

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);
        $runId      = $this->backupEnvelope($good)['run_id'];
        $remediated = $good->fresh()->info('compatibility_preferences');

        $damaged = $this->listingWithBlock('Residential Property', ['communication_style' => 'Email Only']);
        $damaged->saveMeta(self::BACKUP_KEY, '{not valid json');

        $this->artisan('hireagent:retire-tenant-type', ['--restore' => $runId])->assertExitCode(1);

        $this->assertSame($remediated, $good->fresh()->info('compatibility_preferences'),
            'A refused restore must write nothing at all, including the rows it could have read.');
    }

    // ── Malformed rows ──────────────────────────────────────────────────────

    /** @test */
    public function a_malformed_blob_is_reported_and_skipped_rather_than_repaired(): void
    {
        $listing = $this->bareListing();
        $listing->saveMeta('compatibility_preferences', '{not valid json');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])
            ->expectsOutput("  listing {$listing->id}: compatibility_preferences is not valid JSON — skipped")
            ->expectsOutput('  1 with compatibility_preferences that is not valid JSON')
            ->assertExitCode(0);

        $this->assertSame('{not valid json', $listing->fresh()->info('compatibility_preferences'),
            'A blob we cannot parse must be left exactly as found, not rewritten.');
    }

    /**
     * @test
     *
     * The gap the audit found: `landlord_specific` present but not an object used to hit a bare
     * continue, so a damaged record vanished from the report and the operator read the run as
     * complete. Still skipped — still counted, and now said out loud.
     */
    public function a_landlord_block_that_is_not_an_object_is_skipped_and_counted(): void
    {
        $listing = $this->bareListing();
        $listing->saveMeta('compatibility_preferences', json_encode([
            'landlord_specific' => 'tenant_type_preference=Students',
        ]));
        $before = $listing->fresh()->info('compatibility_preferences');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])
            ->expectsOutput("  listing {$listing->id}: landlord_specific is not an object — skipped")
            ->expectsOutput('REMEDIATION INCOMPLETE: could not process 1 listing. Each was left exactly as found.')
            ->expectsOutput('  1 with a landlord_specific that is not an object')
            ->assertExitCode(0);

        $this->assertSame($before, $listing->fresh()->info('compatibility_preferences'),
            'A record we cannot remediate must be left exactly as found.');
    }

    /**
     * @test
     *
     * A listing with no landlord answers at all is not damaged and must not be reported as such —
     * conflating "absent" with "malformed" is what would make the new counter noise.
     */
    public function an_absent_landlord_block_is_not_counted_as_malformed(): void
    {
        $listing = $this->bareListing();
        $listing->saveMeta('compatibility_preferences', json_encode([
            'tenant_specific' => ['communication_style' => 'Email Only'],
        ]));

        $this->artisan('hireagent:retire-tenant-type')
            ->doesntExpectOutput('  1 with a landlord_specific that is not an object')
            ->assertExitCode(0);
    }

    /** @test */
    public function a_listing_with_no_retired_keys_is_not_rewritten(): void
    {
        $listing = $this->listingWithBlock('Residential Property', [
            'communication_style' => 'Email Only',
        ]);
        $before = $listing->fresh()->info('compatibility_preferences');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])->assertExitCode(0);

        $this->assertSame($before, $listing->fresh()->info('compatibility_preferences'));
        $this->assertNull($this->backupEnvelope($listing),
            'A listing that needs no remediation needs no rollback record either.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Mirrors RetireTenantTypePreference::BACKUP_META_KEY, spelled out so a rename is visible here. */
    private const BACKUP_KEY = 'fair_housing_backup_compatibility_preferences';

    private function listingWithBlock(string $propertyType, array $landlordBlock): LandlordAgentAuction
    {
        $listing = $this->bareListing();
        $listing->saveMeta('property_type', $propertyType);
        $listing->saveMeta('compatibility_preferences', json_encode(['landlord_specific' => $landlordBlock]));

        return $listing;
    }

    private function bareListing(): LandlordAgentAuction
    {
        $owner = User::factory()->create(['user_type' => 'landlord']);

        return LandlordAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'Remediation fixture',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
    }

    private function storedBlock(LandlordAgentAuction $listing): array
    {
        $blob = json_decode((string) $listing->fresh()->info('compatibility_preferences'), true);

        return is_array($blob) ? ($blob['landlord_specific'] ?? []) : [];
    }

    /** The decoded rollback envelope for a listing, or null when there is none. */
    private function backupEnvelope(LandlordAgentAuction $listing): ?array
    {
        $row = LandlordAgentAuctionMeta::where('landlord_agent_auction_id', $listing->id)
            ->where('meta_key', self::BACKUP_KEY)
            ->first();

        if (!$row) {
            return null;
        }

        $decoded = json_decode((string) $row->meta_value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function backupRowCount(LandlordAgentAuction $listing): int
    {
        return LandlordAgentAuctionMeta::where('landlord_agent_auction_id', $listing->id)
            ->where('meta_key', self::BACKUP_KEY)
            ->count();
    }

    /** Every meta row for a listing as id => key|value, so any create/edit/delete shows as a diff. */
    private function metaRowSnapshot(LandlordAgentAuction $listing): array
    {
        return LandlordAgentAuctionMeta::where('landlord_agent_auction_id', $listing->id)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => $row->meta_key . '|' . $row->meta_value])
            ->all();
    }

    /** @return list<string> every meta key the landlord PDF packet can render */
    private function flattenedFieldMapKeys(): array
    {
        $keys = [];
        foreach (LandlordFieldMap::sections() as $fields) {
            foreach ($fields as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Register a command whose backup storage throws on the Nth call.
     *
     * The override point exists for exactly this: proving that a backup failure leaves the table
     * untouched needs a backup that fails, and there is no honest way to arrange one from outside.
     */
    private function registerCommandFailingOnBackupCall(int $failOnCall): void
    {
        $command = new class($failOnCall) extends RetireTenantTypePreference {
            private int $calls = 0;

            public function __construct(private int $failOnCall)
            {
                parent::__construct();
            }

            protected function persistBackupEnvelope(int $listingId, string $json): void
            {
                $this->calls++;

                if ($this->calls === $this->failOnCall) {
                    throw new \RuntimeException('simulated backup storage failure');
                }

                parent::persistBackupEnvelope($listingId, $json);
            }
        };

        $this->app[ConsoleKernel::class]->registerCommand($command);
    }
}
