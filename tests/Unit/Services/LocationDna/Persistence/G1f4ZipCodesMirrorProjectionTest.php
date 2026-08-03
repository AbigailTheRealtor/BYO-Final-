<?php

namespace Tests\Unit\Services\LocationDna\Persistence;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\DimensionCommand;
use App\Services\LocationDna\Contract\DimensionCommandApplier;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use App\Services\LocationDna\Persistence\LegacyMirrorProjection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * G1f-4 prerequisite — surface-scoped opt-in management of the legacy `zipCodes` mirror.
 *
 * WHY THIS IS OPT-IN AND NOT A GLOBAL SET
 * ---------------------------------------
 * `zip_codes` is a real canonical dimension and the shared map widget authors it, so the mirror is
 * genuinely derivable. What is NOT safe is managing it globally: the Buyer family carries a PRESENT
 * but empty `zip_codes` key in its canonical blob, and present-empty is CLEARED rather than absent.
 * A global managed set would therefore have made `BuyerAgentAuction` (G1f-1) and
 * `BuyerOfferListing`/`Edit` (G1f-3) emit `zipCodes => '[]'` on their next save — a legacy mirror
 * key appearing for the first time in three already-migrated workflows.
 *
 * So the default set is unchanged and provably so, and the opt-in is an explicit parameter carrying
 * no per-workflow knowledge. The tests below are split accordingly: the DEFAULT half is a
 * compatibility proof for G1f-1/2/3, the OPT-IN half is the new capability G1f-4 consumes.
 */
class G1f4ZipCodesMirrorProjectionTest extends TestCase
{
    private DimensionCommandApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applier = new DimensionCommandApplier();
    }

    /** @param list<DimensionCommand> $commands */
    private function documentWith(array $commands): LocationDnaDocument
    {
        return $this->applier->apply(LocationDnaDocument::emptyDocument(), $commands);
    }

    private function optedIn(): LegacyMirrorProjection
    {
        return new LegacyMirrorProjection(['cities', 'counties', 'state', 'zipCodes']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // DEFAULT BEHAVIOUR · the G1f-1 / G1f-2 / G1f-3 compatibility guarantee
    // ─────────────────────────────────────────────────────────────────────────────────

    /** The default managed set has NOT grown. This is the guarantee, stated once. */
    public function test_the_default_managed_set_is_unchanged(): void
    {
        $this->assertSame(['cities', 'counties', 'state'], LegacyMirrorProjection::MANAGED_KEYS);
    }

    /**
     * The Buyer hazard, stated directly: a blob carrying a PRESENT but empty `zip_codes` must not
     * produce a `zipCodes` mirror under the default projection.
     *
     * If this fails, every already-migrated Buyer workflow has begun writing a brand-new legacy
     * meta key, which is the exact regression the opt-in design exists to prevent.
     */
    public function test_default_projection_emits_no_zipcodes_even_when_canonical_zip_codes_is_cleared(): void
    {
        $mirrors = (new LegacyMirrorProjection())->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
            DimensionCommand::clear(Dimension::ZipCodes),
        ]));

        $this->assertArrayNotHasKey('zipCodes', $mirrors);
        $this->assertSame(['cities'], array_keys($mirrors));
    }

    /** And not when canonical ZIPs are genuinely authored either. */
    public function test_default_projection_emits_no_zipcodes_when_canonical_zip_codes_is_set(): void
    {
        $mirrors = (new LegacyMirrorProjection())->project($this->documentWith([
            DimensionCommand::set(Dimension::ZipCodes, ['33708']),
        ]));

        $this->assertArrayNotHasKey('zipCodes', $mirrors);
        $this->assertSame([], $mirrors);
    }

    /** cities / counties / state are untouched by the change, with and without the opt-in. */
    public function test_cities_counties_and_state_project_identically_with_and_without_opt_in(): void
    {
        $commands = [
            DimensionCommand::set(Dimension::Cities, ['Tampa', 'Orlando']),
            DimensionCommand::set(Dimension::Counties, ['Hillsborough']),
            DimensionCommand::set(Dimension::State, 'FL'),
        ];

        $default = (new LegacyMirrorProjection())->project($this->documentWith($commands));
        $optedIn = $this->optedIn()->project($this->documentWith($commands));

        $this->assertSame('["Tampa","Orlando"]', $default['cities']);
        $this->assertSame('["Hillsborough"]', $default['counties']);
        $this->assertSame('FL', $default['state']);

        foreach (['cities', 'counties', 'state'] as $key) {
            $this->assertSame($default[$key], $optedIn[$key], "{$key} must be byte-identical");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // OPT-IN BEHAVIOUR · projection from canonical state
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Projected from the canonical `zip_codes` dimension — never inferred from city/county/state. */
    public function test_opted_in_zipcodes_project_from_canonical_zip_codes(): void
    {
        $mirrors = $this->optedIn()->project($this->documentWith([
            DimensionCommand::set(Dimension::ZipCodes, ['33708', '33710']),
        ]));

        $this->assertSame('["33708","33710"]', $mirrors['zipCodes']);
    }

    /** The existing serialized format: a JSON-encoded list, exactly as `cities` already is. */
    public function test_opted_in_zipcodes_use_the_existing_serialized_format(): void
    {
        $mirrors = $this->optedIn()->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
            DimensionCommand::set(Dimension::ZipCodes, ['33708']),
        ]));

        // Same encoding family as the mirror the legacy readers already parse.
        $this->assertSame('["33708"]', $mirrors['zipCodes']);
        $this->assertIsArray(json_decode($mirrors['zipCodes'], true));
        $this->assertNotSame('"33708"', $mirrors['zipCodes']);
    }

    /** Present-but-cleared is an explicit clear: `[]`, matching the legacy stored value exactly. */
    public function test_opted_in_cleared_zipcodes_project_as_an_empty_json_list(): void
    {
        $mirrors = $this->optedIn()->project($this->documentWith([
            DimensionCommand::clear(Dimension::ZipCodes),
        ]));

        $this->assertSame('[]', $mirrors['zipCodes']);
    }

    /**
     * Absent invents nothing — no key, so the caller writes nothing and a legacy-only `zipCodes`
     * value survives a save that never mentioned ZIPs. This is what makes the migration lossless.
     */
    public function test_opted_in_absent_zipcodes_emit_no_key_at_all(): void
    {
        $mirrors = $this->optedIn()->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
        ]));

        $this->assertArrayNotHasKey('zipCodes', $mirrors);
    }

    /** Present-empty and absent stay distinct for ZIPs, as they are for every other dimension. */
    public function test_present_empty_and_absent_zipcodes_are_distinguishable(): void
    {
        $cleared = $this->optedIn()->project($this->documentWith([
            DimensionCommand::clear(Dimension::ZipCodes),
        ]));
        $absent = $this->optedIn()->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
        ]));

        $this->assertSame('[]', $cleared['zipCodes']);
        $this->assertArrayNotHasKey('zipCodes', $absent);
    }

    /**
     * Stale-value clearing: the projection is derived from canonical state ONLY.
     *
     * It has no mirror reader at all, so a stale legacy `zipCodes` cannot influence the output —
     * a cleared canonical ZIP set projects as `[]` regardless of what is currently stored.
     */
    public function test_a_cleared_canonical_zip_set_cannot_be_resurrected_by_a_stale_mirror(): void
    {
        // Authored first, then cleared by a LATER save — the real resurrection scenario.
        $authored = $this->documentWith([
            DimensionCommand::set(Dimension::ZipCodes, ['33708']),
        ]);
        $this->assertSame('["33708"]', $this->optedIn()->project($authored)['zipCodes']);

        $document = $this->applier->apply($authored, [
            DimensionCommand::clear(Dimension::ZipCodes),
        ]);

        $this->assertSame('[]', $this->optedIn()->project($document)['zipCodes']);
        $this->assertFalse(
            method_exists(LegacyMirrorProjection::class, 'readMirror'),
            'the projection must have no mirror reader — resurrection is structurally impossible'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    // THE OPT-IN PARAMETER ITSELF
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Deterministic emission order follows the declared set. */
    public function test_managed_keys_are_emitted_in_the_declared_order(): void
    {
        $mirrors = $this->optedIn()->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
            DimensionCommand::set(Dimension::Counties, ['Hillsborough']),
            DimensionCommand::set(Dimension::State, 'FL'),
            DimensionCommand::set(Dimension::ZipCodes, ['33708']),
        ]));

        $this->assertSame(['cities', 'counties', 'state', 'zipCodes'], array_keys($mirrors));
    }

    /** An unsupported key fails loudly at construction rather than silently writing nothing. */
    public function test_an_unsupported_mirror_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LegacyMirrorProjection(['cities', 'states']);
    }

    /** The plural `states` key is still never emitted — §17.5 legacy dead write. */
    public function test_the_plural_states_key_is_not_supported(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LegacyMirrorProjection(['states']);
    }

    /** A surface may narrow as well as widen; the parameter is the FULL set, not an addition. */
    public function test_the_parameter_is_the_full_managed_set(): void
    {
        $mirrors = (new LegacyMirrorProjection(['zipCodes']))->project($this->documentWith([
            DimensionCommand::set(Dimension::Cities, ['Tampa']),
            DimensionCommand::set(Dimension::ZipCodes, ['33708']),
        ]));

        $this->assertSame(['zipCodes'], array_keys($mirrors));
    }
}
