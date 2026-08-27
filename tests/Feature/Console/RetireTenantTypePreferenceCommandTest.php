<?php

namespace Tests\Feature\Console;

use App\Console\Commands\RetireTenantTypePreference;
use App\Models\LandlordAgentAuction;
use App\Models\User;
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

    // ── Command behaviour ────────────────────────────────────────────────────

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

    /** @test */
    public function a_malformed_blob_is_reported_and_skipped_rather_than_repaired(): void
    {
        $owner   = User::factory()->create(['user_type' => 'landlord']);
        $listing = LandlordAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'Malformed',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('compatibility_preferences', '{not valid json');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true])
            ->expectsOutput("  listing {$listing->id}: compatibility_preferences is not valid JSON — skipped")
            ->assertExitCode(0);

        $this->assertSame('{not valid json', $listing->fresh()->info('compatibility_preferences'),
            'A blob we cannot parse must be left exactly as found, not rewritten.');
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
    }

    /** @test */
    public function the_backup_can_restore_the_original_blobs(): void
    {
        $dir     = storage_path('app/testing-fair-housing-' . uniqid());
        $listing = $this->listingWithBlock('Residential Property', [
            'tenant_type_preference' => 'Students',
        ]);
        $original = $listing->fresh()->info('compatibility_preferences');

        $this->artisan('hireagent:retire-tenant-type', ['--write' => true, '--backup-dir' => $dir])
            ->assertExitCode(0);

        $this->assertArrayNotHasKey('tenant_type_preference', $this->storedBlock($listing));

        $files = glob($dir . '/tenant-type-preference-backup-*.json');
        $this->assertCount(1, $files, 'A --write run must leave exactly one backup file.');

        $this->artisan('hireagent:retire-tenant-type', ['--restore' => $files[0]])->assertExitCode(0);

        $this->assertSame($original, $listing->fresh()->info('compatibility_preferences'),
            'Deleting JSON keys has no down(); the backup IS the rollback path.');

        array_map('unlink', $files);
        rmdir($dir);
    }

    /** @test */
    public function restoring_from_a_missing_file_fails_rather_than_succeeding_quietly(): void
    {
        $this->artisan('hireagent:retire-tenant-type', ['--restore' => '/nonexistent/backup.json'])
            ->assertExitCode(1);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function listingWithBlock(string $propertyType, array $landlordBlock): LandlordAgentAuction
    {
        $owner   = User::factory()->create(['user_type' => 'landlord']);
        $listing = LandlordAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'Remediation fixture',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('property_type', $propertyType);
        $listing->saveMeta('compatibility_preferences', json_encode(['landlord_specific' => $landlordBlock]));

        return $listing;
    }

    private function storedBlock(LandlordAgentAuction $listing): array
    {
        $blob = json_decode((string) $listing->fresh()->info('compatibility_preferences'), true);

        return is_array($blob) ? ($blob['landlord_specific'] ?? []) : [];
    }
}
