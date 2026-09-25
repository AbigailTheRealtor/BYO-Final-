<?php

namespace Tests\Support\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\AskAi\AskAiContextBuilderService as C;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;

/**
 * PAGE → ASK AI coverage probe.
 *
 * The existing parity tests start from Ask AI's own context fields and ask whether the page
 * shows them. This probe runs the other direction: it starts from every meta key the public
 * listing PAGE reads, renders the real page to a guest (and to the owner), and records which
 * of those keys the page actually prints and whether the Ask AI card states them.
 *
 * Every key gets a distinctive sentinel. A key the page cannot render as text (a numeric
 * formatter, a date parser) is found by bisection and re-seeded as a unique number, which is
 * then looked for both raw and thousands-grouped. Gate keys (financing types, listing method)
 * are seeded with the value that OPENS the gate, so the rows behind them are reachable.
 *
 * Test-support only: renders through the ordinary HTTP kernel of the test application
 * (SQLite, no model credentials), never a production path.
 */
final class PageFactCoverageProbe
{
    public const ROUTES = [
        'seller'   => 'offer.listing.seller.view',
        'landlord' => 'offer.listing.landlord.view',
        'buyer'    => 'offer.listing.buyer.view',
        'tenant'   => 'offer.listing.tenant.view',
    ];

    /**
     * Gate keys → the stored value that opens every gate they control. Everything here is a
     * value the create wizard can itself store; nothing is invented to reach a row.
     */
    public const GATES = [
        'seller' => [
            'offered_financing'         => ['Cash', 'Conventional', 'Assumable', 'Cryptocurrency', 'Exchange/Trade', 'Lease Option', 'Lease Purchase', 'Non-Fungible Token (NFT)', 'Seller Financing'],
            'auction_type'              => 'Traditional',
            'sale_provision_assignment' => 'Yes',
            'prepayment_penalty'        => 'Yes',
            'balloon_payment'           => 'Yes',
            'business_location_leased'  => 'Yes',
            'has_hoa'                   => 'Yes',
            'has_cdd'                   => 'Yes',
            'has_option_fee'            => 'Yes',
            'pets_allowed'              => 'Yes',
        ],
        'landlord' => [
            'auction_type' => 'Traditional',
            'has_hoa'      => 'Yes',
            'has_cdd'      => 'Yes',
            'pets_allowed' => 'Yes',
        ],
        'buyer' => [
            'offered_financing'     => ['Cash', 'Conventional', 'Assumable Mortgage', 'Cryptocurrency', 'Exchange/Trade', 'Lease Option', 'Lease Purchase', 'Non-Fungible Token (NFT)', 'Seller Financing'],
            'auction_type'          => 'Traditional',
            'possession_preference' => 'Other',
            'has_option_fee'        => 'Yes',
            'balloon_payment'       => 'Yes',
        ],
        'tenant' => [
            'auction_type' => 'Traditional',
            'pets'         => 'Yes',
        ],
    ];

    /** Every role and every property type its wizard offers (the stored spelling). */
    public const MATRIX = [
        'seller'   => ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land'],
        'landlord' => ['Residential Property', 'Commercial Property'],
        'buyer'    => ['Residential', 'Income', 'Commercial', 'Business', 'Vacant Land'],
        'tenant'   => ['Residential', 'Commercial'],
    ];

    /**
     * Render the role's real public page for one property type with every candidate key seeded,
     * as a guest and as the owner, and report per key where its value appears.
     *
     * A page reads each key in ONE stored shape, so the shape is searched, never assumed —
     * each pass re-seeds only the keys every earlier pass left invisible even to the owner:
     *   1. a word sentinel;   2. a one-element JSON list of it;   3. a unique number (found raw,
     *   grouped, or as money);   4. a date (found as the pages format dates).
     * Keys whose word sentinel breaks the page are found by bisection and start at pass 3.
     *
     * @return array<string, array{guest_page: bool, guest_card: bool, owner_page: ?bool, owner_card: ?bool, ask_ai_reads: bool, shape: string}>
     */
    public static function probe(\Tests\TestCase $t, string $role, string $type, array $keys, array $read): array
    {
        $gates = self::GATES[$role] ?? [];
        $shape = [];   // key => 'word' | 'list' | 'number' | 'date'
        foreach ($keys as $k) {
            if (!array_key_exists($k, $gates)) {
                $shape[$k] = 'word';
            }
        }
        $index = array_flip($keys);

        $value = static function (string $k, string $s) use ($index) {
            $i = $index[$k];
            return match ($s) {
                'word'   => self::sentinel($i),
                'list'   => [self::sentinel($i)],
                'number' => self::number($i),
                'date'   => self::date($i)->format('Y-m-d'),
            };
        };
        $needles = static function (string $k, string $s) use ($index): array {
            $i = $index[$k];
            return match ($s) {
                'word', 'list' => [self::sentinel($i)],
                'number'       => self::numberForms(self::number($i)),
                'date'         => [self::date($i)->format('F j, Y'), self::date($i)->format('M j, Y'), self::date($i)->format('m/d/Y'), self::date($i)->format('Y-m-d')],
            };
        };
        $seed = static function (array $only = null) use ($gates, &$shape, $value): array {
            $meta = $gates;
            foreach ($shape as $k => $s) {
                if ($only === null || isset($only[$k])) {
                    $meta[$k] = $value($k, $s);
                }
            }
            return $meta;
        };
        $render = static function (array $meta, bool $asOwner) use ($t, $role, $type): ?array {
            $listing = self::makeListing($role, $type, $meta);
            $req     = $asOwner ? $t->actingAs(User::find($listing->user_id)) : $t;
            $res     = $req->get(route(self::ROUTES[$role], $listing->id));
            auth()->logout();
            return $res->status() === 200 ? self::split($res->getContent(), $role) : null;
        };
        $in = static fn (string $h, array $ns): bool => array_filter($ns, static fn ($n) => str_contains($h, $n)) !== [];

        // Word sentinels that break the page start as numbers.
        $bisect = static function (array $subset) use (&$bisect, $seed, $render, &$shape): void {
            if ($subset === [] || $render($seed(array_fill_keys($subset, true)), false) !== null) {
                return;
            }
            if (count($subset) === 1) {
                $shape[$subset[0]] = 'number';
                return;
            }
            $half = intdiv(count($subset), 2);
            $bisect(array_slice($subset, 0, $half));
            $bisect(array_slice($subset, $half));
        };
        if ($render($seed(), false) === null) {
            $bisect(array_keys($shape));
        }

        // Shape search, judged on the OWNER's page (it shows the most).
        $next = ['word' => 'list', 'list' => 'number', 'number' => 'date'];
        for ($pass = 0; $pass < 3; $pass++) {
            $owner = $render($seed(), true);
            if ($owner === null) {
                break;
            }
            $before = $shape;
            $moved  = [];
            foreach ($shape as $k => $s) {
                if (!$in($owner[1], $needles($k, $s)) && isset($next[$s])) {
                    $shape[$k] = $next[$s];
                    $moved[]   = $k;
                }
            }
            if ($moved === []) {
                break;
            }
            self::repair($shape, $before, $moved, $seed, $render);
        }

        $guest = $render($seed(), false);
        $owner = $render($seed(), true);
        if ($guest === null) {
            $listing = self::makeListing($role, $type, $seed());
            $res     = $t->get(route(self::ROUTES[$role], $listing->id));
            $body    = (string) $res->getContent();
            preg_match('/<title>(.*?)<\/title>/s', $body, $m);
            $t->fail("{$role}/{$type}: page fails to render ({$res->status()}): " . trim($m[1] ?? '') . ' ' . substr(strip_tags($body), 0, 600)
                . ' shapes=' . json_encode(array_filter($shape, static fn ($s) => $s !== 'word')));
        }

        $rows = [];
        foreach ($shape as $k => $s) {
            $ns = $needles($k, $s);
            $rows[$k] = [
                'guest_page'   => $in($guest[1], $ns),
                'guest_card'   => $in($guest[0], $ns),
                'owner_page'   => $owner ? $in($owner[1], $ns) : null,
                'owner_card'   => $owner ? $in($owner[0], $ns) : null,
                'ask_ai_reads' => isset($read[$k]),
                'shape'        => $s,
            ];
        }

        return $rows;
    }

    /**
     * The keys moved to a new shape this pass whose new shape breaks the page are found in ONE
     * recursive bisection (each probe applies the new shape to a subset of the moved keys only,
     * every other key at its previous shape) and put back to their previous shape. A key the
     * page cannot render in a shape is one it does not show in that shape, so nothing visible
     * is lost. If an interaction still breaks the page, every moved key is put back.
     */
    private static function repair(array &$shape, array $before, array $moved, callable $seed, callable $render): void
    {
        if ($render($seed(), false) !== null) {
            return;
        }
        $after   = $shape;
        $renders = static function (array $subset) use (&$shape, $before, $after, $seed, $render): bool {
            $shape = $before;
            foreach ($subset as $k) {
                $shape[$k] = $after[$k];
            }
            $ok    = $render($seed(), false) !== null;
            $shape = $after;
            return $ok;
        };
        $offenders = [];
        $find = static function (array $subset) use (&$find, &$offenders, $renders): void {
            if ($subset === [] || $renders($subset)) {
                return;
            }
            if (count($subset) === 1) {
                $offenders[] = $subset[0];
                return;
            }
            $half = intdiv(count($subset), 2);
            $find(array_slice($subset, 0, $half));
            $find(array_slice($subset, $half));
        };
        $find($moved);
        foreach ($offenders as $k) {
            $shape[$k] = $before[$k];
        }
        if ($render($seed(), false) === null) {
            foreach ($moved as $k) {
                $shape[$k] = $before[$k];
            }
        }
    }

    public static function date(int $i): \Carbon\Carbon
    {
        return \Carbon\Carbon::create(2031, 1, 1)->addDays($i);
    }

    /** Keys that decide WHICH listing this is, never seeded with a sentinel. */
    private const STRUCTURAL = ['property_type', 'workflow_type', 'linked_offer_auction_id', 'is_draft'];

    /** @return list<string> every meta key the role's public listing VIEW reads */
    public static function viewKeys(string $role): array
    {
        $src = (string) file_get_contents(base_path("resources/views/offer-listing/{$role}/view.blade.php"));
        preg_match_all("/\\$(?:str|arr|val|num|money|yn|get)\\(\\s*'([a-z0-9_]+)'/", $src, $a);
        preg_match_all("/\\\$meta\\[\\s*'([a-z0-9_]+)'\\s*\\]/", $src, $b);
        $keys = array_values(array_diff(array_unique(array_merge($a[1], $b[1])), self::STRUCTURAL));
        sort($keys);

        return $keys;
    }

    /** @return list<string> every meta key the role's public view reads, plus Ask AI's own sources */
    public static function candidateKeys(string $role): array
    {
        $keys = self::viewKeys($role);

        foreach (C::CANONICAL_SOURCE_MAP[$role] ?? [] as $sources) {
            foreach ((array) $sources as $s) {
                if (is_string($s) && !str_starts_with($s, 'native:')) {
                    $keys[] = $s;
                }
            }
        }
        foreach (AskAiPublicPropertyQuestionService::publicCriteriaMetaSources()[$role] ?? [] as $spec) {
            foreach ((array) ($spec['keys'] ?? []) as $s) {
                if (is_string($s)) {
                    $keys[] = $s;
                }
            }
        }

        $keys = array_values(array_diff(array_unique($keys), self::STRUCTURAL));
        sort($keys);

        return $keys;
    }

    /** @return array<string, true> meta keys Ask AI reads for this role, by any route */
    public static function askAiReadKeys(string $role): array
    {
        $out = [];
        foreach (C::CANONICAL_SOURCE_MAP[$role] ?? [] as $sources) {
            foreach ((array) $sources as $s) {
                if (is_string($s) && !str_starts_with($s, 'native:')) {
                    $out[$s] = true;
                }
            }
        }
        foreach (AskAiPublicPropertyQuestionService::publicCriteriaMetaSources()[$role] ?? [] as $spec) {
            foreach ((array) ($spec['keys'] ?? []) as $s) {
                if (is_string($s)) {
                    $out[$s] = true;
                }
            }
        }
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $entry) {
            if (($entry['role'] ?? null) !== $role) {
                continue;
            }
            array_walk_recursive($entry, static function ($v, $k) use (&$out): void {
                if (in_array($k, ['meta_key', 'selected_in'], true) && is_string($v)) {
                    $out[$v] = true;
                }
            });
        }

        return $out;
    }

    public static function sentinel(int $i): string
    {
        $s = '';
        do {
            $s = chr(97 + $i % 26) . $s;
            $i = intdiv($i, 26);
        } while ($i > 0);

        return 'Zq' . $s . 'qZ';
    }

    public static function number(int $i): int
    {
        return 7_300_000 + $i;
    }

    /** @return list<string> the forms a numeric sentinel can take on a page */
    public static function numberForms(int $n): array
    {
        return [(string) $n, number_format($n), number_format($n, 2), '$' . number_format($n)];
    }

    public static function makeListing(string $role, string $type, array $meta): object
    {
        $user = User::factory()->create();
        $listing = match ($role) {
            'seller'   => SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '1 Probe Way']),
            'landlord' => LandlordAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'title' => 'Probe Rental']),
            'buyer'    => BuyerAgentAuction::create(['user_id' => $user->id, 'title' => 'Probe Buyer', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]),
            'tenant'   => TenantAgentAuction::factory()->active()->create(['user_id' => $user->id]),
        };
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, is_array($v) ? json_encode($v) : (string) $v);
        }
        $listing->saveMeta('property_type', $type);
        if (in_array($role, ['seller', 'landlord'], true)) {
            $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);
        }

        return $listing->fresh();
    }

    /**
     * The Ask AI region (card + modal), and the page with both removed.
     *
     * @return array{0: string, 1: string}
     */
    public static function split(string $html, string $role): array
    {
        // Ask AI is selection-based: the card shows the FEATURED subset and the modal lists
        // every answerable question with its answer. Both are the Ask AI region; the page is
        // everything else, so a modal answer can never be mistaken for the page showing a fact.
        $regions = [
            ['data-ask-ai-property-questions="' . $role . '"', 'ask-ai-pq-note'],
            ['data-ask-ai-picker="' . $role . '"', 'ask-ai-picker-disclaimer'],
        ];
        $cuts = [];
        foreach ($regions as [$open, $close]) {
            $start = strpos($html, $open);
            if ($start === false) {
                continue;
            }
            $end    = strpos($html, $close, $start);
            $cuts[] = [$start, $end === false ? strlen($html) : $end];
        }
        if ($cuts === []) {
            return ['', $html];
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
        $page .= substr($html, $at);

        return [html_entity_decode($askAi, ENT_QUOTES), html_entity_decode($page, ENT_QUOTES)];
    }
}
