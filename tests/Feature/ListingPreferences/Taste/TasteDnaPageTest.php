<?php

namespace Tests\Feature\ListingPreferences\Taste;

use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Services\ListingPreferences\Taste\TasteEvidenceReader;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "Your Home Taste" over HTTP: both gates, authentication, the non-seeker page,
 * the words a customer reads, and the absence of everything internal.
 */
class TasteDnaPageTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/my/listing-preferences/taste';

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listing_preferences.enabled', true);
        config()->set('listing_preferences.taste_dna_enabled', true);
    }

    /** @test */
    public function the_taste_flag_ships_off_and_is_parsed_fail_closed(): void
    {
        $this->assertFalse((require config_path('listing_preferences.php'))['taste_dna_enabled']);

        foreach (['off', 'no', '0', 'false', '', 'enabled', 'TRUE!'] as $value) {
            putenv("LISTING_PREFERENCE_TASTE_DNA_ENABLED={$value}");
            $this->assertFalse((require config_path('listing_preferences.php'))['taste_dna_enabled'], "'{$value}' must read as off");
        }

        foreach (['true', '1', 'on', 'yes'] as $value) {
            putenv("LISTING_PREFERENCE_TASTE_DNA_ENABLED={$value}");
            $this->assertTrue((require config_path('listing_preferences.php'))['taste_dna_enabled'], "'{$value}' must read as on");
        }

        putenv('LISTING_PREFERENCE_TASTE_DNA_ENABLED');
    }

    /** @test */
    public function the_page_404s_when_the_taste_flag_is_off(): void
    {
        config()->set('listing_preferences.taste_dna_enabled', false);

        $this->actingAs($this->buyer())->get(self::URL)->assertNotFound();
    }

    /** @test */
    public function the_page_404s_when_save_maybe_pass_itself_is_off_whatever_the_taste_flag_says(): void
    {
        config()->set('listing_preferences.enabled', false);

        $this->actingAs($this->buyer())->get(self::URL)->assertNotFound();
    }

    /** @test */
    public function base_save_maybe_pass_keeps_working_with_taste_dna_off(): void
    {
        config()->set('listing_preferences.taste_dna_enabled', false);

        $user    = $this->buyer();
        $listing = $this->listings(1)[0];

        $this->actingAs($user)->postJson('/listing-preferences', [
            'listing_type' => 'seller_agent',
            'listing_id'   => $listing->id,
            'state'        => 'save',
            'reasons'      => ['natural_light'],
        ])->assertOk();

        $this->actingAs($user)->get('/my/listing-preferences')
            ->assertOk()
            ->assertDontSee('Your Home Taste');
    }

    /** @test */
    public function the_management_page_links_to_the_taste_page_only_when_it_exists(): void
    {
        $this->actingAs($this->buyer())->get('/my/listing-preferences')
            ->assertOk()
            ->assertSee('Your Home Taste')
            ->assertSee(self::URL, false);
    }

    /** @test */
    public function a_guest_is_sent_to_sign_in(): void
    {
        $this->get(self::URL)->assertRedirect('/login');
    }

    /** @test */
    public function a_non_seeker_gets_an_explanation_not_an_error(): void
    {
        $this->actingAs(User::factory()->create(['user_type' => 'seller']))
            ->get(self::URL)
            ->assertOk()
            ->assertSee('nothing here for this account');
    }

    /** @test */
    public function a_customer_with_too_little_history_sees_no_pattern_yet(): void
    {
        $user = $this->buyer();
        $this->choose($user, $this->listings(1)[0], ListingPreferenceState::Save, ['natural_light']);

        $this->actingAs($user)->get(self::URL)
            ->assertOk()
            ->assertSee('No patterns yet.')
            ->assertDontSee('Natural Light');
    }

    /** @test */
    public function patterns_are_shown_in_words_with_their_evidence(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }
        foreach ($this->listings(2) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Pass, ['needs_complete_update']);
        }

        $this->actingAs($user)->get(self::URL)
            ->assertOk()
            ->assertSee('What you tend to Save')
            ->assertSee('Natural Light')
            ->assertSee('You often Save homes with this.')
            ->assertSee('From 3 homes you chose: 3 Saved.')
            ->assertSee('What you tend to Pass on')
            ->assertSee('Needs Complete Update')
            ->assertSee('You tend to Pass on homes with this.')
            ->assertSee('does not change which homes you are shown', false);
    }

    /** @test */
    public function a_history_too_long_to_read_whole_shows_no_pattern_rather_than_a_partial_one(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }

        // The same three agreeing Saves, read by a reader whose ceiling is two.
        $this->app->instance(TasteEvidenceReader::class, new TasteEvidenceReader(2));

        $this->actingAs($user)->get(self::URL)
            ->assertOk()
            ->assertSee('data-home-taste-incomplete', false)
            ->assertSee("won't show patterns based on only part of them", false)
            ->assertDontSee('data-home-taste-observation', false)
            ->assertDontSee('Natural Light')
            ->assertDontSee('No patterns yet.');
    }

    /** @test */
    public function the_page_never_shows_internal_values(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light', 'too_expensive']);
        }

        $html = (string) $this->actingAs($user)->get(self::URL)->assertOk()->getContent();

        // Only the Taste DNA container: the shared layout's own scripts are not this page's words.
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $node = (new \DOMXPath($dom))->query('//*[@data-home-taste]')->item(0);
        $this->assertNotNull($node);
        $text = $node->textContent . ' ' . $dom->saveHTML($node);

        $this->assertStringContainsString('Natural Light', $text);

        foreach (['natural_light', 'too_expensive', 'smart_tag', 'seller_agent', 'byo:', 'strength', 'agreement',
                  'established', 'emerging', 'positive', 'negative', '%', (string) $user->id . ' '] as $leak) {
            $this->assertStringNotContainsString($leak, $text, "the page must not show {$leak}");
        }
    }

    /** @test */
    public function one_customer_can_never_see_another_customers_taste(): void
    {
        $alice = $this->buyer();
        $bob   = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->choose($alice, $listing, ListingPreferenceState::Save, ['natural_light']);
        }

        // No id in the URL to tamper with; a query parameter is ignored.
        $this->actingAs($bob)->get(self::URL . '?user_id=' . $alice->id)
            ->assertOk()
            ->assertSee('No patterns yet.')
            ->assertDontSee('Natural Light');
    }

    /** @test */
    public function the_page_is_read_only(): void
    {
        $status = $this->actingAs($this->buyer())->post(self::URL)->getStatusCode();

        // No write route exists; this application answers an unrouted method with 404.
        $this->assertContains($status, [404, 405]);
    }

    // ---------------------------------------------------------------- helpers

    private function choose(User $user, SellerAgentAuction $listing, ListingPreferenceState $state, array $reasons): void
    {
        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id,
            SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $listing->id),
            $state,
            $reasons,
        );
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /** @return list<SellerAgentAuction> */
    private function listings(int $count): array
    {
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $this->n++;
            $auction = SellerAgentAuction::create([
                'user_id'     => 903100,
                'address'     => "{$this->n} Taste Page Lane, St. Petersburg, FL 33701",
                'is_approved' => 1,
                'is_draft'    => false,
                'is_archived' => 0,
            ]);
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => 'property_type',
                'meta_value'              => 'Residential',
            ]);
            $out[] = $auction;
        }

        return $out;
    }
}
