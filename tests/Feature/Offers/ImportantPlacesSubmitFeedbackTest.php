<?php

namespace Tests\Feature\Offers;

use App\Services\Offers\ImportantPlacesService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * The Important Places submit guard had no VOICE, and that is what made it dangerous.
 *
 * THE BUG THIS SUITE PINS
 * -----------------------
 * `assertImportantPlacesValid()` throws a `ValidationException` keyed to `important_places_json`
 * when a STARTED row is left incomplete. Seven Buyer/Tenant components call it. Not one view
 * rendered that key — no `@error`, no `$errors->has`, and the Hire views carry no generic
 * `$errors->any()` summary either. So the exception was raised, Livewire returned it, the submit
 * was refused, and the page said nothing at all.
 *
 * Worse, it did not say nothing. `saveDraft()` flashes "Draft saved successfully (Version N)" and
 * redirects, and a flash survives the next request. A user who saved a draft and then submitted saw
 * that GREEN BANNER above a submit that had not happened. Observed in production behaviour as
 * "the UI says submitted but no listing is created" — five draft rows and three blocked submits,
 * with no error visible on any of them.
 *
 * TWO DEFECTS, TWO FIXES, AND NEITHER IS A VALIDATION CHANGE
 * ---------------------------------------------------------
 *   A. The shared partial now renders the `important_places_json` messages.
 *   B. Every full-submit path clears the stale `success` flash before validating.
 *
 * The GUARD ITSELF IS UNTOUCHED and must stay that way: a started row must be completed, a
 * fully-empty row is dropped, and a partial row survives on a draft. That three-way split is
 * asserted here again — not because it changed, but because these fixes are worthless if the
 * behaviour they report on quietly moves.
 *
 * NO DATABASE AND NO LIVEWIRE MOUNT. The partial is rendered directly against a real
 * `ViewErrorBag`, which is what Livewire and the session middleware both put in the view. That
 * makes this a test of the markup contract rather than of one component's wiring — the point of
 * fixing it in the shared partial is that all seven hosts inherit it.
 */
class ImportantPlacesSubmitFeedbackTest extends TestCase
{
    private const MAP_INPUT   = 'partials.location-dna.map-input';
    private const SUBMIT_PATHS = [
        'app/Http/Livewire/TenantAgentAuction.php',
        'app/Http/Livewire/TenantAgentAuctionEdit.php',
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
        'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
        'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
    ];

    private function service(): ImportantPlacesService
    {
        return app(ImportantPlacesService::class);
    }

    /** A row the user started and left incomplete: a type, no address, no distance. */
    private function partialRow(): string
    {
        return json_encode([[
            'type'           => 'School',
            'type_other'     => '',
            'address'        => '',
            'lat'            => null,
            'lng'            => null,
            'distance_pref'  => 'miles',
            'distance_value' => null,
            'travel_mode'    => 'driving',
        ]]);
    }

    private function completeRow(): string
    {
        return json_encode([[
            'type'           => 'School',
            'type_other'     => '',
            'address'        => '123 Main St, Largo, FL',
            'lat'            => 27.9,
            'lng'            => -82.7,
            'distance_pref'  => 'miles',
            'distance_value' => 5,
            'travel_mode'    => 'driving',
        ]]);
    }

    /** Render the shared partial with the given messages already in the error bag. */
    private function renderWithErrors(array $messages, bool $enableImportantPlaces = true): string
    {
        $bag = new ViewErrorBag();

        if ($messages !== []) {
            $bag->put('default', new MessageBag(['important_places_json' => $messages]));
        }

        return (string) View::make(self::MAP_INPUT, [
            'existingLocationDna'    => [],
            'enableImportantPlaces'  => $enableImportantPlaces,
            'existingImportantPlaces' => [],
            'errors'                 => $bag,
        ])->render();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · AN INCOMPLETE IMPORTANT PLACE STILL BLOCKS SUBMIT (behaviour unchanged)
    // ═════════════════════════════════════════════════════════════════════════

    /** The guard's input: a started-but-incomplete row produces messages. */
    public function test_an_incomplete_important_place_produces_blocking_errors(): void
    {
        $errors = $this->service()->validate($this->partialRow());

        $this->assertNotEmpty($errors, 'A started row missing address and distance must block submit.');
        $this->assertContains('Important Place #1: enter an address.', $errors);
        $this->assertContains('Important Place #1: enter a distance greater than zero.', $errors);
    }

    /** A complete row does not block. The fix must not have made the guard trigger-happy. */
    public function test_a_complete_important_place_does_not_block_submit(): void
    {
        $this->assertSame([], $this->service()->validate($this->completeRow()));
    }

    /** A fully-empty row is dropped before validation and never blocks. */
    public function test_a_fully_empty_row_still_does_not_block_submit(): void
    {
        $empty = json_encode([[
            'type' => '', 'type_other' => '', 'address' => '',
            'lat' => null, 'lng' => null,
            'distance_pref' => 'miles', 'distance_value' => null, 'travel_mode' => 'driving',
        ]]);

        $this->assertSame([], $this->service()->validate($empty));
        $this->assertSame([], $this->service()->normalize($empty), 'Fully-empty rows are dropped.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE ERROR IS VISIBLE IN THE UI  (Fix A)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The messages the guard raises are rendered, verbatim, by the shared partial.
     *
     * Asserted against the SERVICE's own output rather than hard-coded strings, so a reworded
     * validation message keeps this test honest instead of turning it into a second source of
     * truth that has to be edited in lockstep.
     */
    public function test_the_guard_messages_are_rendered_in_the_shared_partial(): void
    {
        $messages = $this->service()->validate($this->partialRow());
        $html     = $this->renderWithErrors($messages);

        foreach ($messages as $message) {
            $this->assertStringContainsString(
                e($message),
                $html,
                "The partial must render the guard message: {$message}"
            );
        }
    }

    /** The block is a real alert, anchored where the Important Places rows are. */
    public function test_the_rendered_errors_are_an_alert_next_to_the_important_places_rows(): void
    {
        $html = $this->renderWithErrors(['Important Place #1: enter an address.']);

        $this->assertStringContainsString('id="ldna-ip-errors"', $html);
        $this->assertStringContainsString('alert alert-danger', $html);
        $this->assertStringContainsString('Please complete your Important Places before submitting.', $html);

        // Anchored ABOVE the rows container, so it is on screen with the fields it describes.
        $this->assertLessThan(
            strpos($html, 'id="ldna-ip-rows"'),
            strpos($html, 'id="ldna-ip-errors"'),
            'The error alert should render above the Important Places rows.'
        );
    }

    /** No errors, no alert. The partial must not shout at a user who has done nothing wrong. */
    public function test_no_alert_renders_when_there_are_no_errors(): void
    {
        $html = $this->renderWithErrors([]);

        $this->assertStringNotContainsString('id="ldna-ip-errors"', $html);
        $this->assertStringContainsString('id="ldna-ip-rows"', $html, 'The section itself still renders.');
    }

    /**
     * SELLER / LANDLORD ARE UNTOUCHED.
     *
     * Those flows never opt into Important Places and never call the guard. The alert lives
     * inside `@if($enableImportantPlaces)`, so with the section off nothing new renders at all —
     * which is the check that this fix did not widen its own scope.
     */
    public function test_nothing_renders_for_hosts_that_do_not_enable_important_places(): void
    {
        $html = $this->renderWithErrors(
            ['Important Place #1: enter an address.'],
            enableImportantPlaces: false
        );

        $this->assertStringNotContainsString('id="ldna-ip-errors"', $html);
        $this->assertStringNotContainsString('id="ldna-ip-rows"', $html);
        $this->assertStringNotContainsString('Important Place #1: enter an address.', $html);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · NO STALE SUCCESS FLASH AFTER A BLOCKED SUBMIT  (Fix B)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Every full-submit path clears the flash before it can refuse.
     *
     * Asserted as source presence across all seven components rather than by driving seven
     * Livewire mounts: the defect was that a path was MISSING the clear, and enumerating the
     * paths is what catches an eighth being added without it.
     */
    public function test_every_full_submit_path_clears_the_stale_success_flash(): void
    {
        foreach (self::SUBMIT_PATHS as $path) {
            $src = (string) file_get_contents(base_path($path));

            $this->assertStringContainsString(
                "session()->forget('success')",
                $src,
                "{$path} must clear the stale success flash before validating a full submit."
            );
        }
    }

    /**
     * The clear happens BEFORE the guard, not after.
     *
     * Order is the whole fix: `assertImportantPlacesValid()` throws, so anything written after it
     * never runs on the failing path — the exact case this is meant to cover.
     */
    public function test_the_flash_is_cleared_before_the_important_places_guard_runs(): void
    {
        foreach (['app/Http/Livewire/TenantAgentAuction.php',
                  'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
                  'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php'] as $path) {
            $src    = (string) file_get_contents(base_path($path));
            $clear  = strpos($src, "session()->forget('success')");
            $guard  = strpos($src, 'assertImportantPlacesValid()');

            // Asserted present FIRST: strpos() returns false on a miss, and false < int is
            // vacuously true — so an ordering assertion alone would pass on a missing clear.
            $this->assertIsInt($clear, "{$path} must clear the stale success flash.");
            $this->assertIsInt($guard, "{$path} must call the Important Places guard.");

            $this->assertLessThan($guard, $clear, "{$path} must clear the flash before the guard throws.");
        }
    }

    /** Behaviourally: a forgotten flash is gone from the session the view would read. */
    public function test_forgetting_the_flash_removes_it_from_the_session(): void
    {
        session()->flash('success', 'Draft saved successfully (Version 3). You can return later to complete your listing.');
        $this->assertTrue(session()->has('success'));

        session()->forget('success');

        $this->assertFalse(
            session()->has('success'),
            'A blocked submit must not leave a draft-save success banner on screen.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · DRAFT BEHAVIOUR IS UNCHANGED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Drafts still keep in-progress work.
     *
     * `normalize()` preserves a partial row — that is what lets a draft round-trip an unfinished
     * Important Place — while `validate()` is what refuses it on a full submit. Neither the
     * service nor `isRowEmpty()` was touched, and this pins that.
     */
    public function test_a_partial_row_is_still_preserved_for_drafts(): void
    {
        $normalized = $this->service()->normalize($this->partialRow());

        $this->assertCount(1, $normalized, 'A started row must survive a draft save.');
        $this->assertSame('School', $normalized[0]['type']);
        $this->assertSame('', $normalized[0]['address']);
        $this->assertNull($normalized[0]['distance_value']);
    }

    /** The draft path never calls the guard, so an incomplete row cannot block a draft save. */
    public function test_the_draft_save_path_does_not_call_the_submit_guard(): void
    {
        $trait = (string) file_get_contents(base_path('app/Http/Livewire/OfferListing/Concerns/HasImportantPlaces.php'));

        // The draft persister normalizes (keeps partial rows); only the guard validates (rejects
        // them). If saveImportantPlaces() ever started validating, drafts would lose in-progress
        // work — so the split is asserted on the method bodies rather than on a comment.
        preg_match('/function saveImportantPlaces\(.*?\n    \}/s', $trait, $save);
        preg_match('/function assertImportantPlacesValid\(.*?\n    \}/s', $trait, $guard);

        $this->assertNotEmpty($save, 'saveImportantPlaces() should exist on the trait.');
        $this->assertNotEmpty($guard, 'assertImportantPlacesValid() should exist on the trait.');

        $this->assertStringContainsString('->normalize(', $save[0]);
        $this->assertStringNotContainsString('->validate(', $save[0], 'The draft path must not validate.');
        $this->assertStringContainsString('->validate(', $guard[0]);

        // TenantAgentAuctionEdit serves draft-save and full-submit from one method, so the guard
        // AND the flash clear both hang off the same `!_isDraftSave` condition.
        $edit = (string) file_get_contents(base_path('app/Http/Livewire/TenantAgentAuctionEdit.php'));
        $this->assertStringContainsString(
            'if (!$this->_isDraftSave && in_array($this->user_type, [\'buyer\', \'tenant\'])) {',
            $edit,
            'The draft-save path must still skip the Important Places guard.'
        );
    }

    /**
     * SELLER AND LANDLORD GAIN NOTHING AND LOSE NOTHING.
     *
     * `TenantAgentAuction` and `TenantAgentAuctionEdit` serve all four roles from one method. The
     * flash clear is scoped by the SAME `buyer`/`tenant` condition as the guard it protects, so a
     * seller or landlord submit behaves exactly as it did — this fix must not become an
     * unrelated behaviour change smuggled into a shared method.
     */
    public function test_the_flash_clear_is_scoped_to_the_roles_the_guard_covers(): void
    {
        foreach (['app/Http/Livewire/TenantAgentAuction.php',
                  'app/Http/Livewire/TenantAgentAuctionEdit.php'] as $path) {
            $src = (string) file_get_contents(base_path($path));

            // Every clear in these shared components sits inside a buyer/tenant block, which is
            // provable by position: the clear must follow the role check that opens that block.
            $roleCheck = strpos($src, "in_array(\$this->user_type, ['buyer', 'tenant'])");
            $clear     = strpos($src, "session()->forget('success')");

            $this->assertIsInt($roleCheck, "{$path} should gate on buyer/tenant.");
            $this->assertIsInt($clear, "{$path} should clear the stale flash.");
            $this->assertGreaterThan(
                $roleCheck,
                $clear,
                "{$path} must clear the flash INSIDE the buyer/tenant block, not for all roles."
            );

            $this->assertSame(
                1,
                substr_count($src, "session()->forget('success')"),
                "{$path} should clear the flash in exactly one place — the guarded submit path."
            );
        }
    }
}
