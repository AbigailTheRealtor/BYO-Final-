<?php

namespace Tests\Feature\Offers;

use Tests\TestCase;

/**
 * Batch E — #20 Hire Tenant ↔ Create Tenant field parity (additive).
 *
 * Batch E ports three Phase-D Tenant requirement fields from the Create Tenant
 * flow into the Hire Tenant flow (TenantAgentAuction create + edit components and
 * the commission-based property-details partial):
 *
 *   rental_purpose             — <select> (Primary Residence / Vacation / … / Other)
 *   rental_purpose_other       — free text, revealed only while rental_purpose === 'Other'
 *   accessibility_requirements — <textarea>
 *
 * All three persist via EAV meta only (Tenant stores via saveMeta/info — no native
 * columns, no migration). The "Other" branch is a reactive Blade @if reveal, plus an
 * updatedRentalPurpose() hook that reset()s rental_purpose_other when the purpose
 * changes away from "Other" (guarded by isLoadingData so draft/edit hydration does
 * not wipe a loaded value).
 *
 * Following the Batch A / Batch D convention, these assert against component + Blade
 * source (the reveal markup is conditionally rendered and the component requires heavy
 * auth/auction fixtures to mount). Every Batch E item remains
 * "CODE COMPLETE — HUMAN BROWSER QA REQUIRED" until a human browser QA pass runs.
 */
class BatchEHireTenantParityTest extends TestCase
{
    private const CREATE_COMPONENT = 'app/Http/Livewire/TenantAgentAuction.php';
    private const EDIT_COMPONENT   = 'app/Http/Livewire/TenantAgentAuctionEdit.php';
    private const VIEW             = 'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php';

    /** @var string[] */
    private const FIELDS = ['rental_purpose', 'rental_purpose_other', 'accessibility_requirements'];

    private function source(string $relativePath): string
    {
        $full = base_path($relativePath);
        $this->assertFileExists($full, "Expected file missing: {$relativePath}");

        return (string) file_get_contents($full);
    }

    // ─── Component: public props on both create + edit ────────────────────────

    /** @test */
    public function create_component_declares_all_three_props(): void
    {
        $src = $this->source(self::CREATE_COMPONENT);

        foreach (self::FIELDS as $field) {
            $this->assertStringContainsString("public \${$field} = '';", $src,
                "#20: TenantAgentAuction must declare \${$field}.");
        }
    }

    /** @test */
    public function edit_component_declares_all_three_props(): void
    {
        $src = $this->source(self::EDIT_COMPONENT);

        foreach (self::FIELDS as $field) {
            $this->assertStringContainsString("public \${$field} = '';", $src,
                "#20: TenantAgentAuctionEdit must declare \${$field}.");
        }
    }

    // ─── Component: hydrate (load) round-trip ─────────────────────────────────

    /** @test */
    public function create_component_hydrates_all_three_from_draft(): void
    {
        $src = $this->source(self::CREATE_COMPONENT);

        foreach (self::FIELDS as $field) {
            $this->assertStringContainsString(
                "\$this->{$field} = \$auction->get->{$field} ?? '';",
                $src,
                "#20: loadDraft() must hydrate \${$field} from the auction."
            );
        }
    }

    /** @test */
    public function edit_component_hydrates_all_three_from_meta(): void
    {
        $src = $this->source(self::EDIT_COMPONENT);

        foreach (self::FIELDS as $field) {
            $this->assertStringContainsString(
                "\$this->{$field} = \$auction->info('{$field}') ?? '';",
                $src,
                "#20: loadAuctionData() must hydrate \${$field} from meta."
            );
        }
    }

    // ─── Component: persist (saveMeta) round-trip ─────────────────────────────

    /** @test */
    public function create_component_persists_all_three_via_meta(): void
    {
        $src = $this->source(self::CREATE_COMPONENT);

        foreach (self::FIELDS as $field) {
            $this->assertStringContainsString(
                "\$auction->saveMeta('{$field}', \$this->{$field});",
                $src,
                "#20: saveAllMetadata() must persist {$field} via EAV meta."
            );
        }
    }

    /** @test */
    public function edit_component_persists_all_three_via_meta(): void
    {
        $src = $this->source(self::EDIT_COMPONENT);

        foreach (self::FIELDS as $field) {
            $this->assertStringContainsString(
                "\$auction->saveMeta('{$field}', \$this->{$field});",
                $src,
                "#20: update() must persist {$field} via EAV meta."
            );
        }
    }

    // ─── Component: reset-on-change hook (both components) ─────────────────────

    /** @test */
    public function both_components_reset_other_when_purpose_leaves_other(): void
    {
        foreach ([self::CREATE_COMPONENT, self::EDIT_COMPONENT] as $path) {
            $src = $this->source($path);

            $this->assertStringContainsString(
                'public function updatedRentalPurpose($value)',
                $src,
                "#20: {$path} must define the updatedRentalPurpose() reset hook."
            );
            $this->assertMatchesRegularExpression(
                "/updatedRentalPurpose\(\\\$value\).*?\\\$value\s*!==\s*'Other'.*?reset\(\['rental_purpose_other'\]\)/s",
                $src,
                "#20: {$path} updatedRentalPurpose() must reset rental_purpose_other when value !== 'Other'."
            );
        }
    }

    /** @test */
    public function create_component_reset_hook_is_guarded_by_is_loading_data(): void
    {
        // Guard prevents draft hydration from wiping a loaded "Other" free-text value.
        $src = $this->source(self::CREATE_COMPONENT);

        $this->assertMatchesRegularExpression(
            "/updatedRentalPurpose\(\\\$value\)\s*\{\s*if\s*\(\\\$this->isLoadingData\)\s*return;/s",
            $src,
            '#20: TenantAgentAuction updatedRentalPurpose() must short-circuit while isLoadingData.'
        );
    }

    // ─── Blade: markup for all three fields + reactive reveal ─────────────────

    /** @test */
    public function view_binds_rental_purpose_select_with_other_option(): void
    {
        $src = $this->source(self::VIEW);

        $this->assertStringContainsString('wire:model="rental_purpose"', $src,
            '#20: view must bind the Rental Purpose select to rental_purpose.');
        $this->assertStringContainsString('<option value="Other">Other</option>', $src,
            '#20: Rental Purpose select must offer an "Other" option.');
    }

    /** @test */
    public function view_reveals_other_input_reactively_only_when_other_selected(): void
    {
        $src = $this->source(self::VIEW);

        $this->assertStringContainsString("@if (\$rental_purpose === 'Other')", $src,
            '#20: the rental_purpose_other input must be gated by a reactive @if on "Other".');
        $this->assertStringContainsString('wire:model="rental_purpose_other"', $src,
            '#20: view must bind the revealed input to rental_purpose_other.');
    }

    /** @test */
    public function view_binds_accessibility_requirements_textarea(): void
    {
        $src = $this->source(self::VIEW);

        $this->assertStringContainsString('wire:model="accessibility_requirements"', $src,
            '#20: view must bind the Accessibility Requirements textarea to accessibility_requirements.');
    }
}
