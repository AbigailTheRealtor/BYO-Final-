<?php

namespace Tests\Feature\Listing;

use App\Services\Listing\ListingWorkflowResolver;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * The single classification rule — including the cases where it must refuse to answer.
 *
 * Everything downstream (pickers, deletes, resume guard, backfill, inventory) asks this
 * class, so the fail-closed cases matter as much as the resolving ones: an "unclassified"
 * that quietly resolved to one product would reopen the bug everywhere at once.
 */
class ListingWorkflowResolverTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    private ListingWorkflowResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        // The schema memo is static and this suite migrates per test.
        ListingWorkflow::forgetSchemaMemo();

        $this->resolver = app(ListingWorkflowResolver::class);
    }

    /** @return array<string,array{0:string}> */
    public function roleProvider(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    /** @dataProvider roleProvider */
    public function test_native_column_and_matching_eav_resolve(string $role): void
    {
        $user = $this->makeUser();

        foreach (ListingWorkflow::ALL as $workflow) {
            $listing = $this->makeListing($role, $workflow, $user->id);

            $this->assertSame($workflow, $listing->getAttribute(ListingWorkflow::COLUMN),
                'stamp() must write the native column');
            $this->assertSame($workflow, $listing->info(ListingWorkflow::META_KEY),
                'stamp() must write the legacy EAV key');
            $this->assertSame($workflow, $this->resolver->resolve($listing));
        }
    }

    /** @dataProvider roleProvider */
    public function test_native_only_resolves(string $role): void
    {
        $user    = $this->makeUser();
        $listing = $this->makeUnstamped($role, $user->id, true, [], [
            ListingWorkflow::COLUMN => ListingWorkflow::OFFER_LISTING,
        ]);

        $this->assertFalse($listing->info(ListingWorkflow::META_KEY), 'no EAV stamp expected');
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->resolver->resolve($listing));
    }

    /** @dataProvider roleProvider */
    public function test_eav_only_resolves_when_column_is_null(string $role): void
    {
        $user    = $this->makeUser();
        $listing = $this->makeUnstamped($role, $user->id, true, [
            ListingWorkflow::META_KEY => ListingWorkflow::HIRE_AGENT,
        ]);

        $this->assertNull($listing->getAttribute(ListingWorkflow::COLUMN));
        $this->assertSame(ListingWorkflow::HIRE_AGENT, $this->resolver->resolve($listing));
    }

    // ── Deterministic legacy provenance ────────────────────────────────────────

    /**
     * THE SELLER DRAFT 123 CASE.
     *
     * An MLS Quick Import draft as the old writer left it: quick-import provenance and no
     * workflow identity anywhere. It must classify as an Offer Listing, because that is
     * the only product the quick-import writer can be reached from.
     */
    public function test_legacy_quick_import_seller_draft_resolves_as_offer_listing(): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyQuickImportDraft('seller', $user->id);

        $this->assertNull($draft->getAttribute(ListingWorkflow::COLUMN));
        $this->assertFalse($draft->info(ListingWorkflow::META_KEY));

        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->resolver->resolve($draft));
    }

    /** The equivalent Landlord Quick Import defect row. */
    public function test_legacy_quick_import_landlord_draft_resolves_as_offer_listing(): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyQuickImportDraft('landlord', $user->id);

        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->resolver->resolve($draft));
    }

    /** @dataProvider roleProvider */
    public function test_legacy_service_type_resolves_as_hire_agent(string $role): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyHireDraft($role, $user->id);

        $this->assertSame(ListingWorkflow::HIRE_AGENT, $this->resolver->resolve($draft));
    }

    // ── Fail-closed cases ──────────────────────────────────────────────────────

    public function test_row_with_no_evidence_is_unclassified_and_resolves_to_null(): void
    {
        $user  = $this->makeUser();
        $row   = $this->makeUnstamped('seller', $user->id);

        $c = $this->resolver->classify($row);

        $this->assertTrue($c->isUnclassified());
        $this->assertNull($this->resolver->resolve($row));
        $this->assertSame('unclassified', $c->bucket());
    }

    public function test_native_and_eav_disagreement_fails_closed(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeUnstamped('seller', $user->id, true,
            [ListingWorkflow::META_KEY => ListingWorkflow::OFFER_LISTING],
            [ListingWorkflow::COLUMN   => ListingWorkflow::HIRE_AGENT]);

        $c = $this->resolver->classify($row);

        $this->assertTrue($c->isConflicting(), 'native=hire_agent vs eav=offer_listing must conflict');
        $this->assertNull($this->resolver->resolve($row));
        $this->assertFalse($this->resolver->matches($row, ListingWorkflow::HIRE_AGENT));
        $this->assertFalse($this->resolver->matches($row, ListingWorkflow::OFFER_LISTING));
    }

    /** And the reverse, so neither direction is silently permitted. */
    public function test_reverse_native_and_eav_disagreement_fails_closed(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeUnstamped('seller', $user->id, true,
            [ListingWorkflow::META_KEY => ListingWorkflow::HIRE_AGENT],
            [ListingWorkflow::COLUMN   => ListingWorkflow::OFFER_LISTING]);

        $this->assertTrue($this->resolver->classify($row)->isConflicting());
    }

    /**
     * Conflicting provenance — the corruption signature of the cross-product bug.
     *
     * A quick-imported Offer Listing draft that a Hire wizard resumed and re-saved ends
     * up carrying both fingerprints. The resolver must report it, not pick a winner.
     */
    public function test_conflicting_provenance_is_reported_not_guessed(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeUnstamped('seller', $user->id, true, [
            'mls_quick_import' => '1',
            'service_type'     => 'full_service',
        ]);

        $c = $this->resolver->classify($row);

        $this->assertTrue($c->isConflicting());
        $this->assertStringContainsString('quick-import', $c->reason);
        $this->assertArrayHasKey('provenance_quick_import', $c->evidence);
        $this->assertArrayHasKey('provenance_service_type', $c->evidence);
    }

    /**
     * A STAMPED identity that disagrees with deterministic provenance is a CONFLICT.
     *
     * This is the case precedence must NOT silently resolve. The evidence order exists so
     * a higher signal gets to NAME the row — it does not get to silence a lower one that
     * contradicts it. "Native wins, ignore the rest" would paper over exactly the
     * corruption signature this change exists to detect: an MLS-imported Offer Listing
     * draft that a Hire wizard resumed and re-saved comes back stamped `hire_agent` while
     * still carrying its `mls_quick_import` provenance. The stamp is the newer signal and
     * the wrong one; the provenance is the older signal and the true one. Neither is
     * trusted over the other — the disagreement itself is the finding.
     *
     * Both directions are asserted so the rule cannot be satisfied by a one-way check.
     *
     * @dataProvider identityVsProvenanceProvider
     */
    public function test_identity_disagreeing_with_provenance_fails_closed(
        string $stamped,
        array $provenanceMeta,
        string $expectedProvenance
    ): void {
        $user = $this->makeUser();

        // Stamped one product, fingerprinted as the other.
        $row = $this->makeListing('seller', $stamped, $user->id, true, $provenanceMeta);

        $c = $this->resolver->classify($row);

        $this->assertTrue($c->isConflicting(),
            "a {$stamped} stamp over {$expectedProvenance} provenance must conflict, not resolve");
        $this->assertNull($c->workflow, 'a conflicting row must name no workflow');
        $this->assertNull($this->resolver->resolve($row), 'resolve() must fail closed too');

        // And it must belong to NEITHER product, not merely "not the stamped one".
        foreach (ListingWorkflow::ALL as $workflow) {
            $this->assertFalse($this->resolver->matches($row, $workflow),
                "matches() must refuse {$workflow} on a conflicting row");
        }

        $this->assertStringContainsString('disagree', $c->reason);
    }

    /** @return array<string,array{0:string,1:array<string,mixed>,2:string}> */
    public function identityVsProvenanceProvider(): array
    {
        return [
            // The real-world shape: a quick-imported Offer draft re-saved by a Hire wizard.
            'hire stamp over quick-import provenance' => [
                ListingWorkflow::HIRE_AGENT,
                ['mls_quick_import' => '1'],
                'offer_listing',
            ],
            // The mirror: an Offer stamp on a row carrying a Hire-only service_type.
            'offer stamp over service_type provenance' => [
                ListingWorkflow::OFFER_LISTING,
                ['service_type' => 'full_service'],
                'hire_agent',
            ],
        ];
    }

    /**
     * The same disagreement, reached through the EAV stamp alone.
     *
     * Asserted separately from the test above because that one stamps BOTH the column and
     * the meta. This covers a pre-column row whose only identity is the legacy EAV key,
     * which is the state every historical wizard-saved row is in until the backfill runs —
     * so it is the shape the conflict is most likely to be found in first.
     */
    public function test_eav_only_identity_disagreeing_with_provenance_fails_closed(): void
    {
        $user = $this->makeUser();

        $row = $this->makeUnstamped('seller', $user->id, true, [
            ListingWorkflow::META_KEY => ListingWorkflow::HIRE_AGENT,
            'mls_quick_import'        => '1',
        ]);

        $c = $this->resolver->classify($row);

        $this->assertTrue($c->isConflicting(), 'EAV hire_agent vs quick-import provenance must conflict');
        $this->assertNull($this->resolver->resolve($row));
        $this->assertSame(ListingWorkflow::HIRE_AGENT, $c->evidence['eav'] ?? null);
        $this->assertArrayHasKey('provenance_quick_import', $c->evidence);
    }

    /**
     * The control: agreement is NOT a conflict.
     *
     * Without this, every assertion above could be satisfied by a resolver that called
     * any row carrying provenance conflicting, which would strand every legitimately
     * stamped quick-import draft — the exact rows the Quick Import fix now creates.
     */
    public function test_identity_agreeing_with_provenance_still_resolves(): void
    {
        $user = $this->makeUser();

        $offer = $this->makeListing('seller', ListingWorkflow::OFFER_LISTING, $user->id, true, [
            'mls_quick_import' => '1',
        ]);
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->resolver->resolve($offer),
            'an offer_listing stamp over quick-import provenance agrees and must resolve');

        $hire = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'service_type' => 'full_service',
        ]);
        $this->assertSame(ListingWorkflow::HIRE_AGENT, $this->resolver->resolve($hire),
            'a hire_agent stamp over service_type provenance agrees and must resolve');
    }

    public function test_unknown_native_value_fails_closed(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeUnstamped('seller', $user->id, true, [],
            [ListingWorkflow::COLUMN => 'something_else']);

        $c = $this->resolver->classify($row);

        $this->assertTrue($c->isAmbiguous());
        $this->assertNull($this->resolver->resolve($row));
    }

    public function test_unknown_eav_value_fails_closed(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeUnstamped('seller', $user->id, true,
            [ListingWorkflow::META_KEY => 'offer']);

        $this->assertTrue($this->resolver->classify($row)->isAmbiguous());
    }

    public function test_blank_eav_value_is_absence_not_ambiguity(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeUnstamped('seller', $user->id, true,
            [ListingWorkflow::META_KEY => '   ']);

        $this->assertTrue($this->resolver->classify($row)->isUnclassified(),
            'a blank meta row means "absent", not "an unrecognised value"');
    }

    public function test_stamp_refuses_an_unrecognised_workflow(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeUnstamped('seller', $user->id);

        $this->expectException(\InvalidArgumentException::class);

        ListingWorkflow::stamp($row, 'offer');
    }
}
