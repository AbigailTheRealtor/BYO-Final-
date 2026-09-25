<?php

namespace Tests\Feature\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiContextBuilderService as C;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Ask AI is never MORE permissive than the public listing page.
 *
 * The rule this pins: Ask AI may answer a fact to a public viewer only when the ordinary public
 * listing experience already shows that fact to the same viewer. Ask AI does not decide what is
 * public — it may be stricter than the page (compliance screens, withheld tiers), never looser.
 *
 * Method: for every role and every property type its wizard offers, a listing carries a
 * distinctive sentinel in EVERY context field, in the shape the page reads that field (see
 * realisticListing()), and its real public page is rendered to a guest.
 * The Ask AI card region is then cut out, so "the page shows it" can never be satisfied by Ask
 * AI's own output. Two Ask AI surfaces are held against what remains:
 *
 *   (A) the "Questions About This Property" card — every sentinel it contains;
 *   (B) the free-text runner at PUBLIC scope — every sentinel it returns when asked about each
 *       field by that field's own label (the label-fallback path) and by the field's key.
 *
 * Every sentinel either surface publishes must appear on the page outside the card. MLS Details
 * facts are held to the same rule against the page's own MLS rows.
 *
 * Nothing here enumerates "the public fields". The visibility authority
 * (SnapshotFactVisibility, the criteria allowlists) is what is under test, so a field added to
 * it that the page does not show fails here by construction.
 */
class AskAiPublicPageVisibilityParityTest extends TestCase
{
    use DatabaseTransactions;

    private const ROLES = [
        'seller'   => ['offer.listing.seller.view',   ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land']],
        'landlord' => ['offer.listing.landlord.view', ['Residential Property', 'Commercial Property']],
        'buyer'    => ['offer.listing.buyer.view',    ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land']],
        'tenant'   => ['offer.listing.tenant.view',   ['Residential', 'Commercial']],
    ];

    /**
     * Fields the page shows ONLY in a structured stored shape (a JSON list, a parseable date)
     * while the card also states a bare-string value the page would print nothing for. Not a
     * tier leak — each field IS public and IS on the page — but on a legacy or malformed stored
     * value Ask AI says something the page beside it does not. Pinned so the set cannot grow
     * unnoticed. Every entry is pre-existing at 22aaeee7d (curated formatters) and is reported
     * for a separate decision rather than changed here.
     *
     * @var array<string, string>
     */
    private const LIST_READ = 'A multi-select the page reads as a JSON list; the curated formatter also states a bare string.';

    private const KNOWN_SHAPE_DIVERGENCES = [
        'buyer.cities'                            => self::LIST_READ,
        'buyer.counties'                          => self::LIST_READ,
        'buyer.non_negotiable_amenities'          => self::LIST_READ,
        'buyer.water_view'                        => self::LIST_READ,
        'landlord.appliances'                     => self::LIST_READ,
        'landlord.exterior_construction'          => self::LIST_READ,
        'landlord.foundation'                     => self::LIST_READ,
        'landlord.interior_features'              => self::LIST_READ,
        'landlord.lease_terms'                    => self::LIST_READ,
        'landlord.property_items'                 => self::LIST_READ,
        'landlord.roof_type'                      => self::LIST_READ,
        'seller.air_conditioning'                 => self::LIST_READ,
        'seller.appliances'                       => self::LIST_READ,
        'seller.exterior_construction'            => self::LIST_READ,
        'seller.foundation'                       => self::LIST_READ,
        'seller.furnished'                        => self::LIST_READ,
        'seller.heating_fuel'                     => self::LIST_READ,
        'seller.offered_financing'                => self::LIST_READ,
        'seller.property_items'                   => self::LIST_READ,
        'seller.roof_type'                        => self::LIST_READ,
        'seller.sewer'                            => self::LIST_READ,
        'seller.utilities'                        => self::LIST_READ,
        'seller.water_source'                     => self::LIST_READ,
        'tenant.appliances'                       => self::LIST_READ,
        'tenant.cities'                           => self::LIST_READ,
        'tenant.counties'                         => self::LIST_READ,
        'tenant.desired_lease_length'             => self::LIST_READ,
        'tenant.non_negotiable_amenities'         => self::LIST_READ,
        'tenant.property_items'                   => self::LIST_READ,
        'tenant.water_view'                       => self::LIST_READ,
        'landlord.available_date'                 => 'The page prints a parseable date only; the formatter also states the landlord\'s own words.',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
    }

    public function test_the_card_never_states_a_fact_the_public_page_does_not_show(): void
    {
        $seenDivergences = [];
        $failures = [];
        $checked  = 0;

        foreach (self::ROLES as $role => [$route, $types]) {
            foreach ($types as $type) {
                [, $card, $page, $needles, $leaks, $divergences] = $this->realisticListing($role, $type);

                foreach ($leaks as $field) {
                    $failures[] = "{$role} / {$type}: the card states {$field}; the public page shows it in no stored shape";
                }
                $seenDivergences["{$role}.{$type}"] = $divergences;

                foreach ($needles as $field => $needle) {
                    if (!str_contains($card, $needle)) {
                        continue;
                    }
                    $checked++;
                    if (!str_contains($page, $needle)) {
                        $failures[] = "{$role} / {$type}: the card states {$field}; the public page does not show it";
                    }
                }
            }
        }

        $this->assertGreaterThan(40, $checked, 'Too few card facts were compared — the proof would be hollow.');
        $this->assertSame([], $failures, implode("\n", $failures));

        // Data-shape divergences are pinned, not tolerated silently: a new one fails here.
        $flat = [];
        foreach ($seenDivergences as $where => $fields) {
            foreach ($fields as $f) {
                $flat[] = explode('.', $where)[0] . '.' . $f;
            }
        }
        $flat = array_values(array_unique($flat));
        sort($flat);
        $known = array_keys(self::KNOWN_SHAPE_DIVERGENCES);
        sort($known);
        $this->assertSame($known, $flat, 'The set of stored-shape divergences changed — see KNOWN_SHAPE_DIVERGENCES.');
    }

    public function test_the_free_text_path_never_states_a_fact_the_public_page_does_not_show(): void
    {
        $runner   = app(AskAiRunnerV2Service::class);
        $failures = [];
        $answered = 0;

        foreach (self::ROLES as $role => [$route, $types]) {
            foreach ($types as $type) {
                [$listing, , $page, $needles, $leaks] = $this->realisticListing($role, $type);
                $this->assertSame([], $leaks, "{$role} / {$type}: card leaks — see the card test.");

                foreach (array_keys($needles) as $field) {
                    $askings  = array_unique([
                        AskAiPublicPropertyQuestionService::fieldLabel($role, $field),
                        str_replace('_', ' ', $field),
                    ]);
                    foreach ($askings as $asking) {
                        $result = $runner->run($role, $listing->id, $asking, ['viewer_scope' => AskAiViewerAuthorizationService::SCOPE_PUBLIC]);
                        // The whole envelope — answer, disclosures, follow-ups — not the answer alone.
                        $body   = (string) json_encode($result['final_response'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        // Any field's value at all — a question about one field must not publish another.
                        foreach ($needles as $leaked => $needle) {
                            if (!str_contains($body, $needle)) {
                                continue;
                            }
                            $answered++;
                            if (!str_contains($page, $needle)) {
                                $failures[] = "{$role} / {$type}: asking '{$asking}' published {$leaked}; the public page does not show it";
                            }
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(40, $answered, 'Too few free-text answers were compared — the proof would be hollow.');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * The other half of the rule: PUBLIC + PRESENT + APPLICABLE + SHOWN ON THE PAGE means Ask AI
     * can answer it — and by deterministic wording, not only by clicking the card.
     *
     * Eligible = a field this role may publish, that the realistic listing's public page shows,
     * that is not deliberately withheld (AskAiFieldDisposition::DELIBERATELY_NOT_ASKED) and
     * that the card states (the card applies the property-type gate). Each one must be
     * reachable on the free-text path at PUBLIC scope by its own label ("<label>", "What is the
     * <label>?") — or, where another available fact claims the same label and label matching
     * therefore refuses by design, by its displayed question. NO_PHRASE = neither works.
     *
     * A page-shown public fact that NO question admitted for the property type reads is reported
     * as unreachable too (structurally — see fieldsWithAQuestion()).
     */
    public function test_every_public_fact_the_page_shows_is_reachable_by_deterministic_wording(): void
    {
        $runner      = app(AskAiRunnerV2Service::class);
        $noPhrase    = [];
        $notOnCard   = [];
        $viaQuestion = 0;
        $viaLabel    = 0;
        $undeclared  = [];

        foreach (self::ROLES as $role => [$route, $types]) {
            $publishable = $this->publishableFields($role);
            foreach ($types as $type) {
                [$listing, $card, $page, $needles] = $this->realisticListing($role, $type);
                $cardQuestions = $this->cardQuestions($card);

                foreach ($needles as $field => $needle) {
                    if (!isset($publishable[$field]) || !str_contains($page, $needle)
                        || array_key_exists("{$role}.{$field}", \App\Support\AskAi\AskAiFieldDisposition::DELIBERATELY_NOT_ASKED)) {
                        continue;
                    }
                    // APPLICABLE is part of eligibility, decided by the same authority the card
                    // uses: AskAiFieldApplicability for Seller/Landlord (the form's own @if), and
                    // for the criteria roles the property types each question admits.
                    $applicable = in_array($role, ['seller', 'landlord'], true)
                        ? \App\Support\AskAi\AskAiFieldApplicability::for($role, $field)
                        : true;
                    if ($applicable === null) {
                        // Not in the form-applicability map. An explicitly admitted field (Batch
                        // 2b's admitted_listing) carries its applicability on its own question;
                        // with no question reading it either, nothing decided it — a gap.
                        if (!isset($this->fieldsWithAQuestion($role, $type)[$field])) {
                            $undeclared["{$role}.{$field}"] = true;
                            continue;
                        }
                        $applicable = true;
                    }
                    if (is_array($applicable)
                        && array_intersect(\App\Support\AskAi\AskAiPropertyTypeResolver::tokensFor($role, $type), $applicable) === []) {
                        continue;                                  // not collected for this type
                    }
                    if (!str_contains($card, $needle)) {
                        // A sentinel is not a valid value for a validating formatter ("N
                        // bedrooms" needs a number), so absence from THIS card proves nothing.
                        // Structural question instead: does any question admitted for this
                        // property type read the field at all? (For the criteria roles an
                        // admitted question IS the applicability decision.)
                        // LEGACY_NO_INPUT: no form collects it for ANY type, so there is no
                        // applicability evidence beyond the question that reads it — whose own
                        // admission governs, rather than widening to a type it does not name.
                        if (!isset($this->fieldsWithAQuestion($role, $type)[$field]) && is_array($applicable)) {
                            $notOnCard[] = "{$role} / {$type}: {$field}";
                        }
                        continue;
                    }

                    $label  = AskAiPublicPropertyQuestionService::fieldLabel($role, $field);
                    $ask    = fn (string $q): string => (string) ($runner->run($role, $listing->id, $q, ['viewer_scope' => AskAiViewerAuthorizationService::SCOPE_PUBLIC])['final_response']['answer'] ?? '');
                    if (str_contains($ask($label), $needle) || str_contains($ask("What is the {$label}?"), $needle)) {
                        $viaLabel++;
                        continue;
                    }
                    $reached = false;
                    foreach ($cardQuestions as $question => $answer) {
                        if (str_contains($answer, $needle) && str_contains($ask($question), $needle)) {
                            $reached = true;
                            break;
                        }
                    }
                    if ($reached) {
                        $viaQuestion++;
                    } else {
                        $noPhrase[] = "{$role} / {$type}: {$field} ('{$label}')";
                    }
                }
            }
        }

        fwrite(STDERR, sprintf("\n[coverage] reachable by label: %d, by displayed question only: %d, NO_PHRASE: %d, applicable but no question: %d, undeclared: %d\n",
            $viaLabel, $viaQuestion, count($noPhrase), count($notOnCard), count($undeclared)));
        $this->assertGreaterThan(40, $viaLabel, 'Too few facts were reached by label — the proof would be hollow.');
        $this->assertSame([], $noPhrase, "NO_PHRASE — eligible facts no deterministic wording reaches:\n" . implode("\n", $noPhrase));
        $this->assertSame([], $notOnCard, "Public facts the page shows that Ask AI never states:\n" . implode("\n", $notOnCard));
        $this->assertSame([], array_keys($undeclared), "Public, page-shown fields with no applicability decision:\n" . implode("\n", array_keys($undeclared)));
    }

    public function test_every_mls_fact_answered_to_the_public_is_one_the_page_shows(): void
    {
        $runner   = app(AskAiRunnerV2Service::class);
        $failures = [];
        $answered = 0;

        foreach (['residential' => 'seller', 'commercial_sale' => 'seller', 'residential_lease' => 'landlord'] as $slug => $role) {
            $raw     = json_decode((string) file_get_contents(base_path("tests/fixtures/mls/bridge/{$slug}.json")), true);
            $details = MlsSupplementalDetails::fromRecord($raw, $role);
            $listing = $this->listingWithEverything($role, $role === 'landlord' ? 'Residential Property' : 'Residential');
            $listing->saveMeta(MlsQuickImportDraftWriter::META_PROPERTY_DETAILS, json_encode($details->toArray()));
            $route   = self::ROLES[$role][0];
            [, $page] = $this->split($this->get(route($route, $listing->id))->assertOk()->getContent(), $role);
            $page    = html_entity_decode($page, ENT_QUOTES);

            foreach ($details->group('facts') as $section) {
                foreach ($section['rows'] as $row) {
                    $result = $runner->run($role, $listing->id, $row['label'], ['viewer_scope' => AskAiViewerAuthorizationService::SCOPE_PUBLIC]);
                    if (($result['status'] ?? null) !== 'ready' || ($result['outcome_category'] ?? null) !== 'public_card') {
                        continue;
                    }
                    $answered++;
                    if (!str_contains($page, (string) $row['value'])) {
                        $failures[] = "{$slug}: '{$row['label']}' answered with a value the page does not show";
                    }
                }
            }
        }

        $this->assertGreaterThan(20, $answered, 'Too few MLS facts were answered — the proof would be hollow.');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function sentinel(string $role, string $field): string
    {
        return "Zqx{$role}{$field}Zqx";
    }

    /**
     * Where the public page shows each field, and where the Ask AI card states it.
     *
     * A page reads each field in ONE stored shape: multi-selects through a JSON-list reader that
     * prints nothing for a bare string, dates through a formatter that prints nothing unless the
     * value parses, everything else as a string. The shape is found, never assumed, by rendering
     * the same role and type up to three times and trying each field in the next shape only
     * while the page still hides it:
     *
     *   pass 1  every field a bare string;
     *   pass 2  the fields pass 1 hid, as a one-element JSON list;
     *   pass 3  the fields pass 2 still hid, as a date (looked for as stored and as the pages
     *           format it, 'F j, Y').
     *
     * Returns [final listing, card, page, needles, leaks, divergences] where the final listing
     * holds every field in the shape its page shows (a field no shape shows stays a bare
     * string), and:
     *   - leaks        fields the card states in SOME shape while the page shows them in NONE —
     *                  Ask AI publishing what the listing does not;
     *   - divergences  fields the page shows in one shape while the card ALSO states them in a
     *                  shape the page cannot parse (a legacy / malformed stored value).
     *
     * @return array{0: object, 1: string, 2: string, 3: array<string,string>, 4: list<string>, 5: list<string>}
     */
    private function realisticListing(string $role, string $type): array
    {
        $route  = self::ROLES[$role][0];
        $fields = array_keys(C::CANONICAL_SOURCE_MAP[$role]);
        $render = function (array $listShaped, array $dates, bool $mayFail = false) use ($role, $type, $route): ?array {
            $listing  = $this->listingWithEverything($role, $type, $listShaped, $dates);
            $response = $this->get(route($route, $listing->id));
            if ($mayFail && $response->status() !== 200) {
                return null; // a stored shape this page cannot read at all
            }
            $response->assertOk();
            return [$listing, ...$this->split($response->getContent(), $role)];
        };
        // A trial shape for several fields at once, or — when the page cannot render them
        // together — each field on its own. Returns field => [card, page] for fields that rendered.
        $trial = function (array $candidates, callable $shape) use ($render): array {
            $out = [];
            $all = $render(...$shape($candidates), mayFail: true);
            if ($all !== null) {
                foreach ($candidates as $f) {
                    $out[$f] = [$all[1], $all[2]];
                }
                return $out;
            }
            foreach ($candidates as $f) {
                $one = $render(...$shape([$f]), mayFail: true);
                if ($one !== null) {
                    $out[$f] = [$one[1], $one[2]];
                }
            }
            return $out;
        };
        $inAny = static fn (string $haystack, array $needles): bool => array_filter($needles, static fn ($n) => str_contains($haystack, $n)) !== [];

        // Only fields Ask AI is ALLOWED to publish need a shape search — nothing else can reach
        // the card in any shape — and restricting the search keeps scalar fields the views echo
        // directly from being re-stored as lists.
        $publishable = $this->publishableFields($role);

        $cardStates = [];   // field => true when the card stated it in some shape
        $pageShape  = [];   // field => 'string' | 'list' | 'date'

        // Pass 1.
        [, $card, $page] = $render([], []);
        $bareCard = $card;
        $hidden   = [];
        foreach ($fields as $f) {
            $n = $this->sentinel($role, $f);
            $cardStates[$f] = str_contains($card, $n);
            if (str_contains($page, $n)) {
                $pageShape[$f] = 'string';
            } elseif (isset($publishable[$f])) {
                $hidden[] = $f;
            }
        }

        // Pass 2.
        $stillHidden = [];
        $rendered    = $trial($hidden, static fn (array $fs) => [$fs, []]);
        foreach ($hidden as $f) {
            $n = $this->sentinel($role, $f);
            [$card, $page] = $rendered[$f] ?? ['', ''];
            $cardStates[$f] = $cardStates[$f] || str_contains($card, $n);
            if (str_contains($page, $n)) {
                $pageShape[$f] = 'list';
            } else {
                $stillHidden[] = $f;
            }
        }

        // Pass 3.
        $dates = [];
        foreach ($stillHidden as $i => $f) {
            $dates[$f] = \Carbon\Carbon::create(2031, 1, 1)->addDays($i * 3);
        }
        $listShaped = array_keys(array_filter($pageShape, static fn ($s) => $s === 'list'));
        $rendered   = $trial($stillHidden, static fn (array $fs) => [$listShaped, array_intersect_key($dates, array_flip($fs))]);
        foreach ($stillHidden as $f) {
            $d = [$dates[$f]->format('F j, Y'), $dates[$f]->format('Y-m-d')];
            [$card, $page] = $rendered[$f] ?? ['', ''];
            $cardStates[$f] = $cardStates[$f] || $inAny($card, $d);
            if ($inAny($page, $d)) {
                $pageShape[$f] = 'date';
            }
        }

        $leaks = $divergences = [];
        foreach ($fields as $f) {
            if ($cardStates[$f] && !isset($pageShape[$f])) {
                $leaks[] = $f;
            }
        }

        // Final — every page-visible field in the shape its page shows.
        $finalDates = array_intersect_key($dates, array_filter($pageShape, static fn ($s) => $s === 'date'));
        [$listing, $card, $page] = $render($listShaped, $finalDates);
        $needles = [];
        foreach ($fields as $f) {
            $needles[$f] = isset($finalDates[$f]) ? $finalDates[$f]->format('F j, Y') : $this->sentinel($role, $f);
        }
        foreach ($fields as $f) {
            // Page-visible only in a structured shape, yet the card stated the bare string.
            if (isset($pageShape[$f]) && $pageShape[$f] !== 'string' && str_contains($bareCard, $this->sentinel($role, $f))) {
                $divergences[] = $f;
            }
        }

        return [$listing, $card, $page, $needles, $leaks, array_values(array_unique($divergences))];
    }

    /**
     * @param list<string>                  $listShaped fields stored as a one-element JSON array
     * @param array<string, \Carbon\Carbon> $dates      fields stored as that date (Y-m-d)
     */
    private function listingWithEverything(string $role, string $type, array $listShaped = [], array $dates = []): object
    {
        $user = User::factory()->create();

        $listing = match ($role) {
            'seller'   => SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '1 Parity Way']),
            'landlord' => LandlordAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'title' => 'Parity Rental']),
            'buyer'    => BuyerAgentAuction::create(['user_id' => $user->id, 'title' => 'Parity Buyer', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]),
            'tenant'   => TenantAgentAuction::factory()->active()->create(['user_id' => $user->id]),
        };

        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach (C::CANONICAL_SOURCE_MAP[$role] as $field => $sources) {
            foreach ((array) $sources as $source) {
                if (is_string($source) && !str_starts_with($source, 'native:')) {
                    $value = $this->sentinel($role, $field);
                    $listing->saveMeta($source, match (true) {
                        isset($dates[$field])                  => $dates[$field]->format('Y-m-d'),
                        in_array($field, $listShaped, true)    => json_encode([$value]),
                        default                                => $value,
                    });
                    break;
                }
            }
        }
        $listing->saveMeta('property_type', $type);
        if (in_array($role, ['seller', 'landlord'], true)) {
            $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);
        }

        return $listing->fresh();
    }

    /** @return array<string, true> fields some catalog entry admitted for this role and type reads */
    private function fieldsWithAQuestion(string $role, string $type): array
    {
        $tokens  = \App\Support\AskAi\AskAiPropertyTypeResolver::tokensFor($role, $type);
        $entries = \App\Services\AskAi\AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry()
            + AskAiPublicPropertyQuestionService::generatedFieldCatalog($role);
        $out = [];
        foreach ($entries as $entry) {
            if (($entry['role'] ?? null) !== $role || !\App\Support\AskAi\AskAiPropertyTypeResolver::admits($entry['property_types'] ?? null, $tokens)) {
                continue;
            }
            foreach (array_merge([$entry['source_path'] ?? null], (array) ($entry['supporting_paths'] ?? []), (array) ($entry['covers'] ?? [])) as $path) {
                if (is_string($path) && preg_match('/^(?:listing|criteria_meta)\.([a-z0-9_]+)$/', $path, $m) === 1) {
                    $out[$m[1]] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Displayed question => its answer, for every question Ask AI lists. Selection-based: the
     * card shows the featured subset, the modal lists all of them. Both the consumer display
     * wording and the original question wording are keys (search terms 0 and 1), since a
     * display rewording is cosmetic and the original stays the deterministic phrase.
     *
     * @return array<string, string>
     */
    private function cardQuestions(string $card): array
    {
        $out = [];
        preg_match_all('/<summary class="ask-ai-pq-question">(.*?)<\/summary>\s*<p class="ask-ai-pq-answer"[^>]*>(.*?)<\/p>/s', $card, $m, PREG_SET_ORDER);
        foreach ($m as [, $q, $a]) {
            $out[html_entity_decode(trim(strip_tags($q)), ENT_QUOTES)] = html_entity_decode(trim(strip_tags($a)), ENT_QUOTES);
        }

        preg_match_all('/data-ask-ai-answer-for="([^"]+)"[^>]*>(.*?)<\/p>/s', $card, $a, PREG_SET_ORDER);
        $answers = [];
        foreach ($a as [, $id, $answer]) {
            $answers[$id] = html_entity_decode(trim(strip_tags($answer)), ENT_QUOTES);
        }
        preg_match_all('/data-ask-ai-pick="([^"]+)"[^>]*data-ask-ai-search-terms="([^"]*)"/', $card, $t, PREG_SET_ORDER);
        foreach ($t as [, $id, $terms]) {
            if (!isset($answers[$id])) {
                continue;
            }
            foreach (array_slice(explode('|', html_entity_decode($terms, ENT_QUOTES)), 0, 2) as $wording) {
                $out[$wording] = $answers[$id];
            }
        }

        return $out;
    }

    /**
     * Every field this role's Ask AI surfaces are allowed to read for a public viewer: the
     * public tier (criteria allowlist for Buyer/Tenant) plus explicit admitted_listing entries.
     *
     * @return array<string, true>
     */
    private function publishableFields(string $role): array
    {
        $out = array_fill_keys(in_array($role, ['buyer', 'tenant'], true)
            ? AskAiPublicPropertyQuestionService::publicCriteriaKeys($role)
            : \App\Services\AskAi\Snapshot\SnapshotFactVisibility::publicKeysForRole($role), true);
        foreach (\App\Services\AskAi\AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $entry) {
            if (($entry['role'] ?? null) === $role && ($entry['source_kind'] ?? null) === 'admitted_listing'
                && preg_match('/^listing\.([a-z0-9_]+)$/', (string) ($entry['source_path'] ?? ''), $m) === 1) {
                $out[$m[1]] = true;
            }
        }

        return $out;
    }

    /**
     * The Ask AI region, and the page with it removed. Selection-based Ask AI has two parts:
     * the card (the FEATURED subset, bounded at its closing note as the other card tests bound
     * it) and the modal (EVERY answerable question with its answer, bounded at its
     * disclaimer). Both are Ask AI, never "the page": a modal answer counted as page text
     * would make every fact look shown.
     *
     * @return array{0: string, 1: string}
     */
    private function split(string $html, string $role): array
    {
        $cuts = [];
        foreach ([
            ['data-ask-ai-property-questions="' . $role . '"', 'ask-ai-pq-note'],
            ['data-ask-ai-picker="' . $role . '"', 'ask-ai-picker-disclaimer'],
        ] as [$open, $close]) {
            $start = strpos($html, $open);
            $this->assertNotFalse($start, "No Ask AI region '{$open}'.");
            $end    = strpos($html, $close, $start);
            $cuts[] = [$start, $end === false ? strlen($html) : $end];
        }
        usort($cuts, static fn ($a, $b) => $a[0] <=> $b[0]);

        $askAi = '';
        $page  = '';
        $at    = 0;
        foreach ($cuts as [$start, $end]) {
            $page  .= substr($html, $at, max(0, $start - $at));
            $askAi .= substr($html, $start, $end - $start) . "\n";
            $at     = max($at, $end);
        }

        return [$askAi, $page . substr($html, $at)];
    }
}
