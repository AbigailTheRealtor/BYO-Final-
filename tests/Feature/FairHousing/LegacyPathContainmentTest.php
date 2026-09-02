<?php

namespace Tests\Feature\FairHousing;

use App\Services\AskAi\AskAiContextBuilderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fair Housing Phase 1 — legacy paths cannot reintroduce a retired P0 value.
 *
 * Phase 1 fixes the CURRENT Create Offer flow. This file answers the separate question the
 * scope demands: can something older put one of the five retired values back into a place
 * that matters? The audit found three legacy writers, and the answer for each is recorded
 * here so the reasoning survives, and so a future change to a consuming boundary trips a
 * test rather than silently reopening a path.
 *
 *  1. App\Http\Livewire\OfferAuction — reachable by every authenticated user (the
 *     `offer-playoff` Gate defaults to '*'), fully writable, auto-approving. Its
 *     saveAllMeta() writes a FIXED, named key list containing none of the five retired
 *     values and no demographic field at all. Nothing to contain.
 *
 *  2. LandlordAuctionController and PropertyAuctionController write the FAQ blob as
 *     `json_encode($request->listing_ai_faq ?? [])` — wholesale, unfiltered request input.
 *     A client CAN still store an arbitrary key, including com_tenant_type, through those
 *     two legacy forms. That is a general input-hygiene defect (deferred, P3), and it is
 *     NOT a Fair Housing bypass, because both surfaces that consume the blob are
 *     allowlist-driven:
 *         - Ask AI      — the P0-B admission boundary intersects with the config SSOT.
 *         - Public page — <x-listing-ai-knowledge-base> iterates the CONFIG questions and
 *                         looks each one up, so an unnamed stored key is never asked for.
 *     This is precisely why P0-B was fixed at the admission boundary rather than at the
 *     Create Offer writer: fixing the writer alone would have left these two open.
 *
 *  3. The Hire Agent components still write occupant_types / occupant_types_tenant into
 *     the same *_agent_auction_metas tables. That product is out of Phase 1 scope and was
 *     deliberately not modified. The values are inert: Phase 1 removed every reader —
 *     the landlord public view row, the LandlordFieldMap export columns, and
 *     AgentController::offerListingView()'s payload — and no Hire Agent view renders the
 *     meta key (the `$occupant_types` seen in those Blades is a local option array for
 *     occupant_status). Retiring the Hire Agent writers belongs to a later phase.
 */
class LegacyPathContainmentTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function the_legacy_offer_auction_component_writes_none_of_the_retired_values(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/OfferAuction.php'));

        foreach ([
            'service_animal',
            'support_animal',
            'occupant_types',
            'com_tenant_type',
            'screening_concerns',
            'prior_felony',
            'prior_eviction',
        ] as $key) {
            $this->assertStringNotContainsString($key, $source);
        }
    }

    /** @test */
    public function a_value_stored_by_a_wholesale_legacy_faq_writer_is_inert_for_ask_ai(): void
    {
        // Simulates a row written by LandlordAuctionController's
        // `json_encode($request->listing_ai_faq ?? [])`, which applies no allowlist.
        $listing = new class {
            public int $id = 999997;

            public function info(string $key)
            {
                return $key === 'listing_ai_faq'
                    ? json_encode([
                        'com_tenant_type'              => 'No families with children.',
                        'preferred_tenant_demographic' => 'Adults only.',
                    ])
                    : null;
            }
        };

        $service = app(AskAiContextBuilderService::class);
        $method  = new ReflectionMethod(AskAiContextBuilderService::class, 'buildFaqAnswers');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke($service, $listing, 'landlord'));
    }

    /** @test */
    public function the_public_faq_component_is_the_shared_allowlist_render_for_legacy_views_too(): void
    {
        // Both legacy detail views render the blob through the same config-driven
        // component, which is what makes an arbitrary stored key unrenderable everywhere
        // rather than only on the current flow.
        foreach ([
            'resources/views/landlord_auction/view.blade.php',
            'resources/views/seller_property/view.blade.php',
            'resources/views/buyer_criteria/view.blade.php',
        ] as $view) {
            $this->assertStringContainsString(
                'x-listing-ai-knowledge-base',
                file_get_contents(base_path($view)),
                "{$view} no longer renders the FAQ through the allowlist component."
            );
        }
    }

    /** @test */
    public function no_reader_of_occupant_types_remains_even_though_hire_agent_still_writes_it(): void
    {
        // The write is out of scope; the absence of any reader is what makes it inert.
        $readers = [
            'app/Http/Controllers/AgentController.php',
            'app/Exports/ListingFieldMaps/LandlordFieldMap.php',
            'resources/views/offer-listing/landlord/view.blade.php',
            'resources/views/offer-listing/buyer/view.blade.php',
            'resources/views/agent/offer-listing-view.blade.php',
        ];

        foreach ($readers as $file) {
            // Match the QUOTED form, which is how every read expression spells it
            // ($str('occupant_types'), $meta['occupant_types'], $d['occupant_types'],
            // 'Occupant Types' => 'occupant_types'). Prose in a comment is not a reader.
            $this->assertStringNotContainsString(
                "'occupant_types'",
                file_get_contents(base_path($file)),
                "{$file} still reads occupant_types."
            );
        }
    }

    /** @test */
    public function no_provider_side_assistance_animal_writer_remains_in_any_flow(): void
    {
        // Every surviving writer must belong to a CONSUMER role (buyer or tenant declaring
        // their own accommodation need). A landlord/seller writer would be a provider
        // preference and is what P0-A retired.
        $providerFiles = [];

        foreach (glob(base_path('app/Http/Livewire/**/*.php'), GLOB_BRACE) as $file) {
            $source = file_get_contents($file);
            if (! str_contains($source, "saveMeta('service_animal'") && ! str_contains($source, "saveMeta('support_animal'")) {
                continue;
            }
            $name = basename($file);
            if (str_contains(strtolower($name), 'landlord') || str_contains(strtolower($name), 'seller')) {
                $providerFiles[] = $name;
            }
        }

        $this->assertSame(
            [],
            $providerFiles,
            'A provider-side (landlord/seller) component persists an assistance-animal preference: '
            . implode(', ', $providerFiles)
        );
    }
}
