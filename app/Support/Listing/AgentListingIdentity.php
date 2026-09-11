<?php

namespace App\Support\Listing;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;

/**
 * Which record the shared Agent listing page was asked for.
 *
 * WHY AN INTEGER WAS NOT ENOUGH
 * -----------------------------
 * `/offer/listing/view/{id}` was written when every listing it could show was a
 * row of `offer_auctions`, and `AgentController::offerListingView()` still
 * resolves `OfferAuction::where('id', $id)`. The Offer Listings hub was later
 * pointed at the four ROLE tables — `seller_agent_auctions`,
 * `landlord_agent_auctions`, `buyer_agent_auctions`, `tenant_agent_auctions` —
 * and kept building the same URL out of the role listing's own primary key.
 *
 * Those are five independent auto-increment sequences. Seller listing #7 and
 * OfferAuction #7 are different records, in different tables, describing
 * different things, and the integer 7 says nothing about which one was meant.
 * Where the numbers collided the page rendered someone else's record; where
 * they did not it returned 404 for a listing the agent was looking at a moment
 * earlier.
 *
 * WHAT THIS FIXES, AND WHAT IT DELIBERATELY DOES NOT DO
 * -----------------------------------------------------
 * The identity now names its own domain. `seller-7` can only ever mean
 * `seller_agent_auctions` row 7; a bare `7` can only ever mean `offer_auctions`
 * row 7. Nothing infers a table from a number, and nothing searches several
 * tables for a matching integer and takes the first hit — either the token says
 * which table it came from or it resolves to nothing at all.
 *
 * PARSING IS STRICT ON PURPOSE. `(int) 'all'` is `0` and `(int) '12,13'` is
 * `12`; both read afterwards as a successful lookup against the wrong record.
 * So the accepted grammar is exactly two shapes and everything else — a leading
 * zero, a negative, a role this application does not have, a trailing segment —
 * is rejected here rather than coerced.
 *
 * This type carries no OfferAuction linkage of its own. Whether a role listing
 * has a linked OfferAuction is {@see \App\Services\Offers\ListingOfferAuctionLinker}'s
 * question, and a hub record is not required to have one — drafts and rows that
 * predate the linker do not.
 */
final class AgentListingIdentity
{
    /** The role listing tables this page can be asked for, by their route token. */
    public const ROLE_MODELS = [
        'seller'   => SellerAgentAuction::class,
        'landlord' => LandlordAgentAuction::class,
        'buyer'    => BuyerAgentAuction::class,
        'tenant'   => TenantAgentAuction::class,
    ];

    /**
     * Route segment pattern, for `Route::where()`.
     *
     * Kept beside the parser so the router and the controller cannot come to
     * disagree about which strings are addresses at all. The router refusing a
     * malformed token is convenience; {@see self::parse()} refusing it is the
     * guarantee, and both are applied.
     */
    public const ROUTE_PATTERN = '(?:seller|landlord|buyer|tenant)-[1-9][0-9]*|[1-9][0-9]*';

    private function __construct(
        /** The role listing table, or null when this names an OfferAuction. */
        public readonly ?string $role,
        public readonly int $id,
    ) {
    }

    /**
     * The identity of a listing in one of the four role tables.
     *
     * @throws \InvalidArgumentException  for a role this application does not have.
     */
    public static function forRoleListing(string $role, int $id): self
    {
        if (! isset(self::ROLE_MODELS[$role])) {
            throw new \InvalidArgumentException("Unknown listing role [{$role}].");
        }

        return new self($role, $id);
    }

    /** The identity of a row of `offer_auctions`. */
    public static function forOfferAuction(int $id): self
    {
        return new self(null, $id);
    }

    /**
     * Read a route segment, or null when it addresses nothing.
     *
     * Null is the only failure mode. A caller that cannot tell a malformed
     * token from a valid one is exactly how a wrong record gets rendered, so
     * there is no lenient branch and no default.
     */
    public static function parse(string $token): ?self
    {
        if (preg_match('/^([a-z]+)-([1-9][0-9]*)$/', $token, $m) === 1) {
            return isset(self::ROLE_MODELS[$m[1]])
                ? new self($m[1], (int) $m[2])
                : null;
        }

        if (preg_match('/^[1-9][0-9]*$/', $token) === 1) {
            return new self(null, (int) $token);
        }

        return null;
    }

    /** The route segment for this identity. */
    public function token(): string
    {
        return $this->role === null ? (string) $this->id : $this->role . '-' . $this->id;
    }

    public function isRoleListing(): bool
    {
        return $this->role !== null;
    }

    /**
     * The Eloquent model this identity addresses. Total by construction — every
     * way of building one has already established the table.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string
    {
        return $this->role === null
            ? OfferAuction::class
            : self::ROLE_MODELS[$this->role];
    }
}
