<?php

namespace Tests\Feature\AskAi;

use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiContextBuilderService as C;
use App\Services\AskAi\AskAiPublicPropertyQuestionService as S;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService as Scope;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility as V;
use App\Support\AskAi\AskAiFieldApplicability;
use App\Support\AskAi\AskAiPageFactDisposition as D;
use App\Support\AskAi\AskAiPropertyTypeResolver as PT;
use App\Support\OfferListing\ConditionalTerms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\AskAi\PageFactCoverageProbe as P;
use Tests\TestCase;

/**
 * Offer terms (owner decision 2026-09-24: public offer terms are answered) follow the SAME
 * parent rule that controls the listing page — derived from ConditionalTerms itself, not from a
 * hand-picked list, so a follow-up added there is covered here automatically.
 *
 *   - a child value whose parent is NOT selected (a stale answer left behind) is refused;
 *   - the same value with every parent opened is answered;
 *   - lending-trigger terms and deposits are refused even with every parent open.
 */
class AskAiOfferTermsConditionalTest extends TestCase
{
    use DatabaseTransactions;

    private const TYPE_FOR = [
        PT::RESIDENTIAL => 'Residential', PT::INCOME => 'Income', PT::COMMERCIAL => 'Commercial',
        PT::BUSINESS => 'Business', PT::VACANT_LAND => 'Vacant Land',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);
    }

    public function test_every_public_seller_follow_up_obeys_its_parent(): void
    {
        $public   = array_flip(V::publicKeysForRole('seller'));
        $catalog  = S::generatedFieldCatalog('seller');
        $checked  = 0;
        $failures = [];

        foreach (ConditionalTerms::followUps('seller') as $metaKey => $alternatives) {
            $field = $this->fieldFor('seller', $metaKey);
            if ($field === null || !isset($public[$field]) || !isset($catalog["seller_field_{$field}"])) {
                continue;
            }
            $entry = $catalog["seller_field_{$field}"];
            $type  = $this->typeFor('seller', $field);
            $value = $this->valueFor($entry);

            $open = $this->opening($metaKey) + [$metaKey => $value];
            if (($entry['shape'] ?? '') === 'unit_amount:assignment_fee_type') {
                $open['assignment_fee_type'] = '$';
            }

            $stale = P::makeListing('seller', $type, [$metaKey => $value]);
            $live  = P::makeListing('seller', $type, $open);
            $q     = (string) $entry['question'];

            if ($this->answered($stale, $q)) {
                $failures[] = "{$metaKey}: answered with its parent deselected";
            }
            if (!$this->answered($live, $q)) {
                $failures[] = "{$metaKey}: not answered with its parent selected";
            }
            $checked++;
        }

        $this->assertGreaterThan(40, $checked, 'Too few seller follow-ups exercised — the proof would be hollow.');
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_buyer_financing_terms_obey_the_offered_financing_option(): void
    {
        $checked = 0;
        foreach (S::publicCriteriaMetaSources()['buyer'] as $name => $spec) {
            if (!is_string($spec['parent'] ?? null)) {
                continue;
            }
            $key   = $spec['keys'][0];
            $q     = "What does the buyer's listing state for {$spec['label']}?";
            $stale = P::makeListing('buyer', 'Residential', ['offered_financing' => ['Cash'], $key => 'Negotiated in writing']);
            $live  = P::makeListing('buyer', 'Residential', ['offered_financing' => ['Cash', $spec['parent']], $key => 'Negotiated in writing']);

            $this->assertFalse($this->answered($stale, $q, 'buyer'), "{$name}: answered while {$spec['parent']} is not offered");
            $this->assertTrue($this->answered($live, $q, 'buyer'), "{$name}: not answered while {$spec['parent']} is offered");
            $checked++;
        }
        $this->assertGreaterThan(15, $checked);
    }

    public function test_lending_trigger_terms_and_deposits_stay_unanswered_with_every_parent_open(): void
    {
        $open = ['offered_financing' => ['Seller Financing', 'Assumable'], 'balloon_payment' => 'Yes', 'prepayment_penalty' => 'Yes'];
        $probed = 0;
        foreach (['PROHIBITED' => D::PROHIBITED, 'INTERNAL' => D::INTERNAL] as $category) {
            foreach (D::forRole('seller') as $key => $d) {
                if ($d['category'] !== $category || !str_contains($d['reason'], $category === D::PROHIBITED ? 'trigger' : 'Deposit')) {
                    continue;
                }
                $listing = P::makeListing('seller', 'Residential', $open + [$key => '6.75']);
                $probed++;
                foreach ([str_replace('_', ' ', $key), 'what is the ' . str_replace('_', ' ', $key)] as $q) {
                    $r = $this->run_($listing, $q, 'seller');
                    $this->assertStringNotContainsString('6.75', $r['answer'], "{$key} was stated for '{$q}'.");
                }
            }
        }
        $this->assertGreaterThan(35, $probed, 'Every lending-trigger and deposit key must be probed.');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** The public context field whose PRIMARY source is this meta key. */
    private function fieldFor(string $role, string $metaKey): ?string
    {
        foreach (C::CANONICAL_SOURCE_MAP[$role] as $field => $sources) {
            foreach ((array) $sources as $s) {
                if (is_string($s) && !str_starts_with($s, 'native:')) {
                    if ($s === $metaKey) {
                        return $field;
                    }
                    break;
                }
            }
        }

        return null;
    }

    private function typeFor(string $role, string $field): string
    {
        $types = AskAiFieldApplicability::for($role, $field);

        return self::TYPE_FOR[is_array($types) && $types !== [] ? $types[0] : PT::RESIDENTIAL];
    }

    private function valueFor(array $entry): mixed
    {
        return match (true) {
            ($entry['shape'] ?? '') === 'list'  => ['Stated term'],
            in_array($entry['shape'] ?? '', ['money', 'percent'], true) => '12',
            str_starts_with((string) ($entry['shape'] ?? ''), 'unit_amount:') => '12',
            default => 'Negotiable',
        };
    }

    /**
     * Meta that opens every parent of a follow-up, by satisfying the first alternative of each
     * clause (recursively). A clause the test cannot satisfy is left for the assertion to show.
     *
     * @return array<string, mixed>
     */
    private function opening(string $metaKey, array $seen = []): array
    {
        $map = ConditionalTerms::followUps('seller');
        if (!isset($map[$metaKey]) || isset($seen[$metaKey])) {
            return [];
        }
        $seen[$metaKey] = true;
        $out = [];
        foreach ($map[$metaKey][0] as [$parent, $op, $answers]) {
            $value = match ($op) {
                'is'      => $parent === 'offered_financing' || $parent === 'sale_provision' ? [$answers[0]] : $answers[0],
                'other'   => 'Other',
                'accepts' => $answers[0],
                default   => null,
            };
            if ($value !== null) {
                $out[$parent] = $value;
            }
            $out += $this->opening($parent, $seen);
        }

        return $out;
    }

    private function run_(object $listing, string $q, string $role): array
    {
        $r = app(AskAiRunnerV2Service::class)->run($role, $listing->id, $q, ['viewer_scope' => Scope::SCOPE_PUBLIC]);

        return ['status' => (string) ($r['status'] ?? ''), 'answer' => (string) ($r['final_response']['answer'] ?? '')];
    }

    private function answered(object $listing, string $q, string $role = 'seller'): bool
    {
        return $this->run_($listing, $q, $role)['status'] === 'ready';
    }
}
