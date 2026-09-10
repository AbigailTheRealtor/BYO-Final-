<?php

namespace Tests\Unit;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Support\Listing\AgentListingIdentity;
use PHPUnit\Framework\TestCase;

/**
 * The grammar of the shared Agent listing page's route identity.
 *
 * This is the whole safety property of the identity fix, so it is asserted
 * directly rather than only through a rendered page: a token either names its
 * table or it names nothing. Every rejection case below is a string that
 * `(int)` would happily turn into a plausible row id — which is exactly how a
 * lookup lands on the wrong record and reads afterwards as success.
 *
 * No database and no application: the parser must be a pure function of the
 * string, and a test that needed a container would not be proving that.
 */
class AgentListingIdentityTest extends TestCase
{
    /** @test */
    public function a_bare_integer_names_an_offer_auction(): void
    {
        $identity = AgentListingIdentity::parse('7');

        $this->assertNotNull($identity);
        $this->assertFalse($identity->isRoleListing());
        $this->assertNull($identity->role);
        $this->assertSame(7, $identity->id);
        $this->assertSame(OfferAuction::class, $identity->modelClass());
        $this->assertSame('7', $identity->token());
    }

    /**
     * @test
     * @dataProvider roleTables
     */
    public function a_role_token_names_that_roles_table(string $role, string $expectedModel): void
    {
        $identity = AgentListingIdentity::parse($role . '-31');

        $this->assertNotNull($identity);
        $this->assertTrue($identity->isRoleListing());
        $this->assertSame($role, $identity->role);
        $this->assertSame(31, $identity->id);
        $this->assertSame($expectedModel, $identity->modelClass());
        $this->assertSame($role . '-31', $identity->token());
    }

    public function roleTables(): array
    {
        return [
            'seller'   => ['seller', SellerAgentAuction::class],
            'landlord' => ['landlord', LandlordAgentAuction::class],
            'buyer'    => ['buyer', BuyerAgentAuction::class],
            'tenant'   => ['tenant', TenantAgentAuction::class],
        ];
    }

    /**
     * @test
     * @dataProvider unparseableTokens
     */
    public function an_unparseable_token_names_nothing(string $token): void
    {
        $this->assertNull(
            AgentListingIdentity::parse($token),
            "'{$token}' must not resolve to a record",
        );
    }

    public function unparseableTokens(): array
    {
        return [
            'empty'                => [''],
            'zero'                 => ['0'],
            'negative'             => ['-4'],
            'leading zero'         => ['007'],
            'a word'               => ['all'],
            'a list'               => ['12,13'],
            'a range'              => ['1-2-3'],
            'an unknown role'      => ['agent-4'],
            'admin'                => ['admin-1'],
            'a role with no id'    => ['seller-'],
            'a role with zero'     => ['seller-0'],
            'a role with a word'   => ['seller-all'],
            'a role, leading zero' => ['seller-07'],
            'a bare role'          => ['seller'],
            'a trailing segment'   => ['seller-4-x'],
            'uppercase role'       => ['Seller-4'],
            'whitespace'           => [' 4'],
            'a float'              => ['4.0'],
            'a path'               => ['seller/4'],
        ];
    }

    /** @test */
    public function a_token_round_trips_through_the_parser(): void
    {
        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            $token = AgentListingIdentity::forRoleListing($role, 908)->token();

            $reparsed = AgentListingIdentity::parse($token);

            $this->assertNotNull($reparsed);
            $this->assertSame($role, $reparsed->role);
            $this->assertSame(908, $reparsed->id);
        }

        $this->assertSame('908', AgentListingIdentity::forOfferAuction(908)->token());
    }

    /** @test */
    public function building_an_identity_for_an_unknown_role_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AgentListingIdentity::forRoleListing('agent', 1);
    }

    /**
     * The route constraint and the parser must accept the same strings. A route
     * that let a token through which the parser then rejected would be harmless;
     * one that refused a token the parser accepts would 404 a real listing, and
     * neither drifting is worth discovering in production.
     *
     * @test
     */
    public function the_route_pattern_agrees_with_the_parser(): void
    {
        $tokens = array_merge(
            ['1', '7', '908', 'seller-1', 'landlord-42', 'buyer-9', 'tenant-1000'],
            array_map(fn ($row) => $row[0], array_values($this->unparseableTokens())),
        );

        foreach ($tokens as $token) {
            $matchesRoute = preg_match('/^(?:' . AgentListingIdentity::ROUTE_PATTERN . ')$/', $token) === 1;
            $parses       = AgentListingIdentity::parse($token) !== null;

            $this->assertSame(
                $parses,
                $matchesRoute,
                "the route constraint and the parser disagree about '{$token}'",
            );
        }
    }
}
