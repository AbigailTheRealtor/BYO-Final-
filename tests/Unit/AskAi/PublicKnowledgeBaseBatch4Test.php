<?php

namespace Tests\Unit\AskAi;

use App\Services\AskAi\AskAiFaqConfigService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Support\AskAi\PublicAnswerPiiScreen;
use App\Support\OfferListing\PublicProviderTextPolicy;
use Tests\TestCase;

/**
 * Batch 4 — publishing a curated subset of the owner's AI Knowledge Base.
 *
 * The knowledge base is owner-only and stays owner-only. This batch opens ONE narrow,
 * key-by-key admission for ONE surface — the deterministic public question card — and every
 * test here exists to pin a way that admission must refuse.
 *
 * The ordering of the file follows the ordering of the gate chain, because the chain is the
 * design: owner acknowledgement, then the allowlist and its declared group, then property-type
 * gating, then the answer itself, then Fair Housing, then PII, then attribution. A gate that
 * moved earlier or later would change which of these tests fails, and that is intentional.
 */
class PublicKnowledgeBaseBatch4Test extends TestCase
{
    private AskAiPublicPropertyQuestionService $service;

    /** A seller residential key that is on the allowlist. */
    private const SELLER_KEY = 'roof_age_and_condition';

    /** A landlord universal key that is on the allowlist. */
    private const LANDLORD_KEY = 'notice_to_vacate_required';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiPublicPropertyQuestionService();
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    /** A viewer context that states the address IS shown. */
    private function viewerVisible(): array
    {
        return ['address_withheld' => false, 'address' => '123 Oak Lane', 'unit' => null];
    }

    /** A viewer context that states the address is WITHHELD, with something to screen on. */
    private function viewerWithheld(): array
    {
        return ['address_withheld' => true, 'address' => '1234 Gulf Boulevard', 'unit' => null];
    }

    /** Listing meta: confirmed owner, one seller residential answer. */
    private function sellerMeta(string $answer, array $overrides = []): array
    {
        return array_merge([
            'property_type'                                             => 'Residential',
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
            'listing_ai_faq'                                            => [self::SELLER_KEY => $answer],
        ], $overrides);
    }

    private function landlordMeta(string $answer, array $overrides = []): array
    {
        return array_merge([
            'property_type'                                             => 'Residential Property',
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
            'listing_ai_faq'                                            => [self::LANDLORD_KEY => $answer],
        ], $overrides);
    }

    /** The published answer for one KB key, or null when the question is not available. */
    private function kbAnswer(string $role, array $meta, array $viewer, string $key): ?string
    {
        foreach ($this->service->forListing($role, ['listing' => []], $meta, $viewer) as $q) {
            if ($q['id'] === 'kb_' . $role . '_' . $key) {
                return $q['answer'];
            }
        }

        return null;
    }

    /** Every question id a listing produces. */
    private function ids(string $role, array $meta, array $viewer = []): array
    {
        return array_column($this->service->forListing($role, ['listing' => []], $meta, $viewer), 'id');
    }

    // =====================================================================
    // 1. Allowlist integrity — the 38 keys, against the live canonical config
    // =====================================================================

    /** @test */
    public function every_allowlisted_key_exists_in_its_declared_group_with_a_label(): void
    {
        $configFor = ['seller' => 'ai_faq_seller', 'landlord' => 'ai_faq_landlord'];
        $checked   = 0;

        foreach (AskAiPublicPropertyQuestionService::publicSafeKbKeys() as $role => $groups) {
            $this->assertArrayHasKey($role, $configFor, "Allowlist names role '{$role}' with no knowledge base.");

            $config = AskAiFaqConfigService::rawConfig($configFor[$role]);

            foreach ($groups as $declaredGroup => $keys) {
                $this->assertArrayHasKey(
                    $declaredGroup,
                    $config['groups'] ?? [],
                    "{$role}: allowlist declares group '{$declaredGroup}' which the config does not define."
                );

                foreach ($keys as $key) {
                    $checked++;
                    $found = null;

                    foreach ($config['groups'][$declaredGroup] as $questions) {
                        if (is_array($questions) && isset($questions[$key]) && is_array($questions[$key])) {
                            $found = $questions[$key];
                            break;
                        }
                    }

                    $this->assertNotNull(
                        $found,
                        "{$role}/{$key}: not defined in its declared group '{$declaredGroup}'."
                    );
                    $this->assertIsString($found['label'] ?? null, "{$role}/{$key}: label is not a string.");
                    $this->assertNotSame('', trim($found['label']), "{$role}/{$key}: label is empty.");

                    // The `insight` category exists to prompt interpretation rather than state a
                    // fact, and is excluded from the public set by design.
                    $this->assertNotSame(
                        'insight',
                        $found['category_type'] ?? 'common',
                        "{$role}/{$key}: an 'insight' question must never be publicly admitted."
                    );
                }
            }
        }

        $this->assertSame(38, $checked, 'The public knowledge-base allowlist should hold exactly 38 keys.');
    }

    /** @test */
    public function every_allowlisted_key_is_reachable_by_at_least_one_property_type(): void
    {
        $configFor = ['seller' => 'ai_faq_seller', 'landlord' => 'ai_faq_landlord'];

        foreach (AskAiPublicPropertyQuestionService::publicSafeKbKeys() as $role => $groups) {
            $gating = AskAiFaqConfigService::rawConfig($configFor[$role])['gating'] ?? [];

            foreach ($groups as $declaredGroup => $keys) {
                $reachable = false;
                foreach ($gating as $activeGroups) {
                    if (in_array($declaredGroup, $activeGroups, true)) {
                        $reachable = true;
                        break;
                    }
                }

                $this->assertTrue(
                    $reachable,
                    "{$role}: group '{$declaredGroup}' is named by no property type, so "
                    . implode(', ', $keys) . ' could never appear.'
                );
            }
        }
    }

    /** @test */
    public function no_sensitive_concept_entered_the_public_allowlist(): void
    {
        // Motivation, negotiating posture, defect/disclosure history, other people's data and
        // business-sensitive figures are excluded by DECISION, not by the screens downstream —
        // those judge the prose, not whether the question should have been asked publicly.
        $forbidden = [
            'motivation', 'why_selling', 'reason_for_selling', 'leaseback', 'concession',
            'flexib', 'negotia', 'urgency', 'timeline_pressure',
            'disclosure', 'known_issue', 'defect', 'foundation', 'pest', 'mould', 'mold',
            'flood_damage', 'claim', 'deferred_maintenance', 'repair_history',
            'tenant_name', 'current_tenant', 'lease_terms', 'rent_roll', 'payment_history',
            'occupancy_history', 'eviction',
            'ideal_buyer', 'ideal_tenant', 'target_', 'who_would', 'suits', 'demographic',
            'neighborhood_character', 'redevelopment_vision',
        ];

        foreach (['seller', 'landlord'] as $role) {
            foreach (AskAiPublicPropertyQuestionService::publicSafeKbKeysForRole($role) as $key) {
                foreach ($forbidden as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $key,
                        "{$role}/{$key} looks like a sensitive concept and must not be publicly admitted."
                    );
                }

                // `business_*` keys are commercially sensitive as a family.
                $this->assertStringStartsNotWith('business_', $key, "{$role}/{$key}: business keys are excluded.");
            }
        }
    }

    /** @test */
    public function buyer_and_tenant_have_no_public_knowledge_base_allowlist(): void
    {
        foreach (['buyer', 'tenant'] as $role) {
            $this->assertSame([], AskAiPublicPropertyQuestionService::publicSafeKbKeysForRole($role));
            $this->assertArrayNotHasKey($role, AskAiPublicPropertyQuestionService::publicSafeKbKeys());
            $this->assertFalse(AskAiPublicPropertyQuestionService::isPublicSafeKbKey($role, 'roof_age_and_condition'));
        }
    }

    /** @test */
    public function buyer_and_tenant_listings_emit_no_kb_questions_at_all(): void
    {
        foreach (['buyer', 'tenant'] as $role) {
            $meta = [
                'property_type'                                             => 'Residential',
                AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
                'listing_ai_faq'                                            => [
                    self::SELLER_KEY   => 'Roof replaced in 2019.',
                    self::LANDLORD_KEY => 'Sixty days notice.',
                ],
            ];

            // Asserted on the filtered set rather than inside a loop: these roles legitimately
            // produce no questions from this context at all, and a loop that never runs proves
            // nothing.
            $kb = array_filter(
                $this->ids($role, $meta, $this->viewerVisible()),
                static fn (string $id): bool => str_starts_with($id, 'kb_')
            );

            $this->assertSame([], array_values($kb), "{$role} produced a knowledge-base question.");

            // Control: the very same meta DOES publish for seller, so the refusal above is the
            // role being inadmissible rather than the fixture being incapable.
            $this->assertNotNull(
                $this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY),
                'The control fixture should publish for seller.'
            );
        }
    }

    // =====================================================================
    // 2. Owner publication confirmation
    // =====================================================================

    /** @test */
    public function a_legacy_listing_with_no_acknowledgement_publishes_nothing(): void
    {
        // No ack meta row at all — every listing that existed before Batch 4.
        $meta = $this->sellerMeta('Roof replaced in 2019, architectural shingle.');
        unset($meta[AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY]);

        $this->assertFalse(AskAiPublicPropertyQuestionService::kbPublicationConfirmed($meta));
        $this->assertNull($this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY));
    }

    /** @test */
    public function acknowledgement_is_required_and_parses_fail_closed(): void
    {
        $confirmed = ['1', 'true', 'TRUE', 'on', 'yes', 'Yes', true, 1];
        $refused   = ['0', 'false', 'off', 'no', '', ' ', 'maybe', 'null', null, [], ['1'], 2, 0, false];

        foreach ($confirmed as $value) {
            $meta = $this->sellerMeta('Roof replaced in 2019.', [
                AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => $value,
            ]);
            $this->assertTrue(
                AskAiPublicPropertyQuestionService::kbPublicationConfirmed($meta),
                'Expected confirmation for ' . var_export($value, true)
            );
            $this->assertNotNull($this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY));
        }

        foreach ($refused as $value) {
            $meta = $this->sellerMeta('Roof replaced in 2019.', [
                AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => $value,
            ]);
            $this->assertFalse(
                AskAiPublicPropertyQuestionService::kbPublicationConfirmed($meta),
                'Expected refusal for ' . var_export($value, true)
            );
            $this->assertNull($this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY));
        }
    }

    /** @test */
    public function revoking_the_acknowledgement_immediately_stops_public_kb_answers(): void
    {
        $answer = 'Roof replaced in 2019, architectural shingle.';

        $confirmed = $this->sellerMeta($answer);
        $this->assertNotNull($this->kbAnswer('seller', $confirmed, $this->viewerVisible(), self::SELLER_KEY));

        // Same listing, same stored answers, acknowledgement withdrawn.
        $revoked = $this->sellerMeta($answer, [
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '0',
        ]);
        $this->assertNull($this->kbAnswer('seller', $revoked, $this->viewerVisible(), self::SELLER_KEY));
    }

    /** @test */
    public function the_acknowledgement_does_not_make_every_answer_public(): void
    {
        // Confirmed, but the answer is to a key that is NOT on the allowlist.
        $meta = [
            'property_type'                                             => 'Residential',
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
            'listing_ai_faq'                                            => [
                'seller_motivation_timeline' => 'We must sell before September, we are desperate.',
            ],
        ];

        foreach ($this->ids('seller', $meta, $this->viewerVisible()) as $id) {
            $this->assertStringNotContainsString('motivation', $id);
        }
        $this->assertNull($this->kbAnswer('seller', $meta, $this->viewerVisible(), 'seller_motivation_timeline'));
    }

    // =====================================================================
    // 3. Role, group and property-type gating
    // =====================================================================

    /** @test */
    public function a_hand_built_kb_entry_for_a_criteria_role_is_refused(): void
    {
        $entry = [
            'role' => 'buyer', 'question' => 'Injected?', 'source_kind' => 'kb',
            'kb_key' => self::SELLER_KEY, 'kb_group' => 'residential', 'order' => 1,
        ];

        $result = $this->service->evaluate(
            $entry,
            'buyer',
            ['listing' => []],
            $this->sellerMeta('Roof replaced in 2019.'),
            $this->viewerVisible()
        );

        $this->assertFalse($result['available']);
        $this->assertSame('kb_not_available_for_role', $result['reason']);
    }

    /** @test */
    public function a_key_declared_under_the_wrong_group_fails_closed(): void
    {
        $entry = [
            'role' => 'seller', 'question' => 'How old is the roof?', 'source_kind' => 'kb',
            'kb_key' => self::SELLER_KEY,
            'kb_group' => 'universal', // it is actually 'residential'
            'order' => 1,
        ];

        $result = $this->service->evaluate(
            $entry,
            'seller',
            ['listing' => []],
            $this->sellerMeta('Roof replaced in 2019.'),
            $this->viewerVisible()
        );

        $this->assertFalse($result['available']);
        $this->assertSame('kb_key_not_public_safe', $result['reason']);
    }

    /** @test */
    public function property_type_gating_decides_which_keys_can_appear(): void
    {
        // A residential key on a Vacant Land listing: gated off, so no question.
        $land = $this->sellerMeta('Roof replaced in 2019.', ['property_type' => 'Vacant Land']);
        $this->assertNull($this->kbAnswer('seller', $land, $this->viewerVisible(), self::SELLER_KEY));

        // The same key on a Residential listing: present.
        $residential = $this->sellerMeta('Roof replaced in 2019.');
        $this->assertNotNull($this->kbAnswer('seller', $residential, $this->viewerVisible(), self::SELLER_KEY));
    }

    /** @test */
    public function an_absent_or_unrecognised_property_type_falls_back_to_universal_only(): void
    {
        foreach (['', 'Not A Real Type'] as $type) {
            // A residential key cannot appear.
            $residentialKey = $this->sellerMeta('Roof replaced in 2019.', ['property_type' => $type]);
            $this->assertNull($this->kbAnswer('seller', $residentialKey, $this->viewerVisible(), self::SELLER_KEY));

            // A universal landlord key still can.
            $universal = $this->landlordMeta('Sixty days written notice.', ['property_type' => $type]);
            $this->assertNotNull(
                $this->kbAnswer('landlord', $universal, $this->viewerVisible(), self::LANDLORD_KEY)
            );
        }
    }

    // =====================================================================
    // 4. The answer itself
    // =====================================================================

    /** @test */
    public function a_missing_or_blank_answer_produces_no_question(): void
    {
        foreach ([[], [self::SELLER_KEY => ''], [self::SELLER_KEY => '   '], [self::SELLER_KEY => null], [self::SELLER_KEY => ['x']]] as $store) {
            $meta = $this->sellerMeta('unused', ['listing_ai_faq' => $store]);
            $this->assertNull($this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY));
        }
    }

    /** @test */
    public function placeholder_equivalent_answers_are_suppressed(): void
    {
        foreach (['n/a', 'N/A', 'NA', 'unknown', 'TBD', 'not sure', '-', '--', '?', '...', 'idk', 'not provided'] as $junk) {
            $meta = $this->sellerMeta($junk);
            $this->assertNull(
                $this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY),
                "'{$junk}' should not publish as an answer."
            );
        }
    }

    /** @test */
    public function the_configured_example_text_is_never_published_back_as_an_answer(): void
    {
        $placeholder = null;
        foreach (AskAiFaqConfigService::rawConfig('ai_faq_seller')['groups']['residential'] ?? [] as $questions) {
            if (isset($questions[self::SELLER_KEY]['placeholder'])) {
                $placeholder = $questions[self::SELLER_KEY]['placeholder'];
                break;
            }
        }

        if (!is_string($placeholder) || trim($placeholder) === '') {
            $this->markTestSkipped('This key defines no placeholder to leave behind.');
        }

        $meta = $this->sellerMeta($placeholder);
        $this->assertNull($this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY));
    }

    /** @test */
    public function no_and_none_are_meaningful_answers_and_publish(): void
    {
        // The counterpart of the placeholder rule, and the more important half: treating a
        // negative as blank would hide the questions a shopper most wants settled, and would
        // quietly favour listings whose answer happens to be yes.
        foreach (['No', 'no', 'None', 'none', 'No.', 'None at this time'] as $answer) {
            $meta      = $this->landlordMeta($answer);
            $published = $this->kbAnswer('landlord', $meta, $this->viewerVisible(), self::LANDLORD_KEY);

            $this->assertNotNull($published, "'{$answer}' is a real answer and must publish.");
            $this->assertStringContainsString($answer, $published);
        }
    }

    /** @test */
    public function an_over_long_answer_is_withheld_whole_and_never_truncated(): void
    {
        $long = str_repeat('The roof is in good condition. ', 60); // > 1200 chars
        $this->assertGreaterThan(1200, mb_strlen($long));

        $meta = $this->sellerMeta($long);
        $this->assertNull($this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY));

        // And nothing truncated leaked into any other question either.
        foreach ($this->service->forListing('seller', ['listing' => []], $meta, $this->viewerVisible()) as $q) {
            $this->assertStringNotContainsString('…', $q['answer']);
            $this->assertStringNotContainsString('...', $q['answer']);
        }

        // Just under the ceiling still publishes.
        $short = str_repeat('a', 1200);
        $ok    = $this->sellerMeta($short);
        $this->assertNotNull($this->kbAnswer('seller', $ok, $this->viewerVisible(), self::SELLER_KEY));
    }

    // =====================================================================
    // 5. Attribution
    // =====================================================================

    /** @test */
    public function a_published_answer_is_attributed_to_the_owner_by_role(): void
    {
        $seller = $this->kbAnswer(
            'seller',
            $this->sellerMeta('Roof replaced in 2019, architectural shingle.'),
            $this->viewerVisible(),
            self::SELLER_KEY
        );
        $this->assertSame('According to the seller: Roof replaced in 2019, architectural shingle.', $seller);

        $landlord = $this->kbAnswer(
            'landlord',
            $this->landlordMeta('Sixty days written notice.'),
            $this->viewerVisible(),
            self::LANDLORD_KEY
        );
        $this->assertSame('According to the landlord: Sixty days written notice.', $landlord);
    }

    /** @test */
    public function a_role_with_no_attribution_string_is_refused(): void
    {
        // PROPERTY_ROLES and KB_ATTRIBUTION must stay in step; if a role is ever added to the
        // first and not the second, the answer is withheld rather than published unattributed.
        $reflection = new \ReflectionClass(AskAiPublicPropertyQuestionService::class);
        $roles      = $reflection->getConstant('PROPERTY_ROLES');
        $attributed = array_keys($reflection->getConstant('KB_ATTRIBUTION'));

        foreach ($roles as $role) {
            $this->assertContains($role, $attributed, "Role '{$role}' can publish but has no attribution.");
        }
    }

    // =====================================================================
    // 6. Address visibility — fail closed
    // =====================================================================

    /** @test */
    public function an_explicit_address_visible_context_behaves_normally(): void
    {
        $meta = $this->sellerMeta('Roof replaced in 2019, architectural shingle.');
        $this->assertNotNull($this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY));
    }

    /** @test */
    public function an_explicit_withheld_context_blocks_an_answer_restating_the_address(): void
    {
        $viewer = $this->viewerWithheld(); // address = 1234 Gulf Boulevard

        // Restates the withheld street line, without a house number — which the generic
        // street rule alone would miss.
        $leaky = $this->sellerMeta('The entrance is on Gulf Boulevard, behind the gate.');
        $this->assertNull($this->kbAnswer('seller', $leaky, $viewer, self::SELLER_KEY));

        // The same listing publishes an answer that does not restate it.
        $clean = $this->sellerMeta('Roof replaced in 2019, architectural shingle.');
        $this->assertNotNull($this->kbAnswer('seller', $clean, $viewer, self::SELLER_KEY));
    }

    /** @test */
    public function missing_viewer_context_fails_closed(): void
    {
        $meta   = $this->sellerMeta('Roof replaced in 2019, architectural shingle.');
        $entry  = [
            'role' => 'seller', 'question' => 'How old is the roof?', 'source_kind' => 'kb',
            'kb_key' => self::SELLER_KEY, 'kb_group' => 'residential', 'order' => 1,
        ];

        // No viewer at all, a non-boolean flag, and a withheld decision with nothing to
        // screen against: all three are unknowns, and all three refuse.
        $unknowns = [
            [],
            ['address_withheld' => 'false', 'address' => '1234 Gulf Boulevard'],
            ['address_withheld' => 1, 'address' => '1234 Gulf Boulevard'],
            ['address_withheld' => null, 'address' => '1234 Gulf Boulevard'],
            ['address_withheld' => true, 'address' => null, 'unit' => null],
            ['address_withheld' => true, 'address' => '   '],
        ];

        foreach ($unknowns as $i => $viewer) {
            $result = $this->service->evaluate($entry, 'seller', ['listing' => []], $meta, $viewer);

            $this->assertFalse($result['available'], "Unknown viewer context #{$i} must not publish.");
            $this->assertSame('kb_address_visibility_unknown', $result['reason'], "case #{$i}");

            $this->assertNull(
                $this->kbAnswer('seller', $meta, $viewer, self::SELLER_KEY),
                "Unknown viewer context #{$i} must not publish through forListing() either."
            );
        }
    }

    /** @test */
    public function non_kb_questions_are_unaffected_by_a_missing_viewer_context(): void
    {
        // The field-sourced catalog must keep working for callers that pass no viewer at all —
        // Buyer and Tenant controllers do exactly that, and Batch 1-3 questions predate it.
        $context = ['listing' => ['flood_zone_code' => 'AE']];

        $withViewer    = array_column($this->service->forListing('seller', $context, [], $this->viewerVisible()), 'id');
        $withoutViewer = array_column($this->service->forListing('seller', $context, []), 'id');

        $this->assertNotEmpty($withoutViewer);
        $this->assertSame($withViewer, $withoutViewer);
    }

    // =====================================================================
    // 7. Fair Housing and PII screens, through the KB path
    // =====================================================================

    /** @test */
    public function fair_housing_blocked_prose_never_reaches_a_public_kb_answer(): void
    {
        $refused = [
            'Perfect family neighborhood.',
            'Family-friendly neighborhood with parks.',
            'Perfect for families.',
            'Great for young professionals.',
            'No children.',
            'No housing vouchers.',
            'No wheelchair users.',
            'No emotional support animals.',
        ];

        foreach ($refused as $prose) {
            $meta = $this->landlordMeta($prose);
            $this->assertNull(
                $this->kbAnswer('landlord', $meta, $this->viewerVisible(), self::LANDLORD_KEY),
                "Fair Housing: '{$prose}' must not publish."
            );
        }
    }

    /** @test */
    public function factual_property_and_accessibility_language_still_publishes(): void
    {
        $kept = [
            'Property is wheelchair accessible with a zero-step entry.',
            'Two most recent pay stubs or benefit award letter.',
            'Spacious family room with a fireplace.',
            'Pet deposit is 500 dollars, two pets maximum.',
            'All lawful verifiable income is counted toward the requirement.',
        ];

        foreach ($kept as $prose) {
            $meta = $this->landlordMeta($prose);
            $this->assertSame(
                'According to the landlord: ' . $prose,
                $this->kbAnswer('landlord', $meta, $this->viewerVisible(), self::LANDLORD_KEY),
                "'{$prose}' is factual and must publish."
            );
        }
    }

    /** @test */
    public function contact_details_never_reach_a_public_kb_answer(): void
    {
        $refused = [
            'Call me at 727-555-0147 to arrange.',
            'Text 7275550147 anytime.',
            'Email owner@example.com for details.',
            'See www.example.com for the survey.',
            'Visit https://example.com/listing for photos.',
            'The property is at 123 Oak Lane.',
        ];

        foreach ($refused as $prose) {
            $meta = $this->sellerMeta($prose);
            $this->assertNull(
                $this->kbAnswer('seller', $meta, $this->viewerVisible(), self::SELLER_KEY),
                "PII: '{$prose}' must not publish."
            );
        }
    }

    // =====================================================================
    // 8. The PII screen's own keep/refuse table
    // =====================================================================

    /**
     * @test
     * @dataProvider piiCases
     */
    public function the_pii_screen_keeps_property_facts_and_refuses_contact_details(string $text, bool $expectBlocked, string $why): void
    {
        $result = PublicAnswerPiiScreen::screen($text);

        $this->assertSame($expectBlocked, $result['blocked'], "{$why}: \"{$text}\"");

        if ($expectBlocked) {
            // The reason names a category and never the matched text.
            $this->assertNotNull($result['reason']);
            $this->assertStringNotContainsString(' ', $result['reason']);
        }
    }

    public static function piiCases(): array
    {
        return [
            // Refused — genuinely identifying.
            ['Call me at 727-555-0147 to arrange.', true, 'separated phone'],
            ['Phone 727.555.0147.', true, 'dotted phone'],
            ['My cell is 7275550147.', true, 'ten digits with dialling context'],
            ['Reach me on 7275550147.', true, 'ten digits with dialling context'],
            ['Text 7275550147 anytime.', true, 'ten digits with dialling context'],
            ['7275550147', true, 'bare ten digits stay blocked when unlabelled'],
            ['Call the tax office at 7275550147.', true, 'dialling wording outranks an incidental label'],
            ['Parcel 1234567890; also call 7275550147.', true, 'one labelled id does not excuse a second number'],
            ['Email owner@example.com for details.', true, 'email'],
            ['See www.example.com for the survey.', true, 'bare domain'],
            ['Visit https://example.com/listing', true, 'url'],
            ['123 Oak Lane is the property.', true, 'street address'],
            ['Showings at 1234 Gulf Boulevard.', true, 'street address'],
            ['Owner lives at 88 Maple St.', true, 'abbreviated street address'],

            // Kept — the false positives this batch had to fix.
            ['2 blocks from Central Avenue.', false, 'proximity, not an address'],
            ['Located 3 houses from Oak Lane.', false, 'proximity, not an address'],
            ['About 2 miles from Gulf Boulevard.', false, 'proximity, not an address'],
            ['Parcel number 1234567890 on file.', false, 'labelled identifier, not a phone number'],
            ['APN 9876543210 is recorded with the county.', false, 'labelled identifier'],
            ['Folio 1234567890.', false, 'labelled identifier'],
            ['MLS listing 1234567890 for reference.', false, 'labelled identifier'],

            // Kept — ordinary property facts full of numbers.
            ['2 car garage plus a carport.', false, 'not an address'],
            ['1,500 sq ft of living area.', false, 'sq is not a street type'],
            ['50 amp service in the workshop.', false, 'not an address'],
            ['The unit is 4 bedrooms, 3.5 baths.', false, 'not a domain'],
            ['Roof replaced in 2019, architectural shingle.', false, 'a year is not a phone number'],
            ['Walking distance to Main Street shops.', false, 'no house number'],
            ['Utilities average 150 per month.', false, 'ordinary figure'],
            ['', false, 'empty publishes nothing but blocks nothing'],
        ];
    }

    /** @test */
    public function the_withheld_address_rule_only_runs_when_the_page_is_withholding(): void
    {
        $fragments = PublicAnswerPiiScreen::addressFragments(true, '1234 Gulf Boulevard', null);
        $this->assertContains('1234 Gulf Boulevard', $fragments);
        $this->assertContains('Gulf Boulevard', $fragments, 'The house-number-less form is needed too.');

        // Showing the address: nothing to compare against.
        $this->assertSame([], PublicAnswerPiiScreen::addressFragments(false, '1234 Gulf Boulevard', null));

        // A short fragment must not match ordinary prose.
        $this->assertTrue(PublicAnswerPiiScreen::isPublishable('The main water shutoff is in the garage.', ['Elm']));
    }

    /** @test */
    public function a_blocked_verdict_never_carries_the_offending_text(): void
    {
        $pii = PublicAnswerPiiScreen::screen('Call me at 727-555-0147.');
        $this->assertTrue($pii['blocked']);
        $this->assertStringNotContainsString('727', json_encode($pii));

        $fh = PublicProviderTextPolicy::decide('No housing vouchers.');
        $this->assertFalse($fh['allowed']);
        $this->assertStringNotContainsString('voucher', strtolower(json_encode($fh)));
    }

    // =====================================================================
    // 9. Determinism and isolation
    // =====================================================================

    /** @test */
    public function the_kb_path_is_deterministic_and_orders_below_the_field_catalog(): void
    {
        $meta    = $this->sellerMeta('Roof replaced in 2019, architectural shingle.');
        $context = ['listing' => ['flood_zone_code' => 'AE']];

        $first  = $this->service->forListing('seller', $context, $meta, $this->viewerVisible());
        $second = (new AskAiPublicPropertyQuestionService())->forListing('seller', $context, $meta, $this->viewerVisible());
        $this->assertSame($first, $second);

        $ids       = array_column($first, 'id');
        $kbIndex   = array_search('kb_seller_' . self::SELLER_KEY, $ids, true);
        $fieldIds  = array_filter($ids, static fn ($id): bool => !str_starts_with($id, 'kb_'));

        $this->assertNotFalse($kbIndex, 'The KB question should be present.');
        $this->assertNotEmpty($fieldIds);

        foreach (array_keys($fieldIds) as $position) {
            $this->assertLessThan($kbIndex, $position, 'Verified structured facts must lead the card.');
        }
    }

    /** @test */
    public function a_kb_question_uses_the_canonical_question_text_from_the_config(): void
    {
        $meta = $this->sellerMeta('Roof replaced in 2019, architectural shingle.');

        $label = null;
        foreach (AskAiFaqConfigService::rawConfig('ai_faq_seller')['groups']['residential'] ?? [] as $questions) {
            if (isset($questions[self::SELLER_KEY]['label'])) {
                $label = $questions[self::SELLER_KEY]['label'];
                break;
            }
        }

        foreach ($this->service->forListing('seller', ['listing' => []], $meta, $this->viewerVisible()) as $q) {
            if ($q['id'] === 'kb_seller_' . self::SELLER_KEY) {
                $this->assertSame(trim($label), $q['question']);

                return;
            }
        }

        $this->fail('The KB question did not appear.');
    }
}
