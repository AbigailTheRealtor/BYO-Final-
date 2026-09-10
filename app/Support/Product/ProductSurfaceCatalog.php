<?php

namespace App\Support\Product;

/**
 * The single classification authority for "which product does this route belong to".
 *
 * Every registered route resolves to EXACTLY ONE disposition below.
 * ProductSurfaceContractTest fails the build, naming the method and URI, when a
 * route resolves to none of them or to more than one — the same contract shape as
 * MlsFieldCatalog / MlsNoFieldDropContractTest, and for the same reason: a surface
 * nobody classified is a surface nobody decided about.
 *
 * WHY URI AND NOT ROUTE NAME
 * --------------------------
 * Twenty-odd registered routes have no name at all (`POST /add-listing`,
 * `POST /criteria/auction/bid/{id}`, `renew_property_sale/{id}`, …) and several
 * names are duplicated by prefix groups ('buyer.', 'agent.'). A name-keyed
 * catalog would silently skip exactly the legacy write endpoints that most need
 * classifying. The URI is the one key every route has.
 *
 * WHY A POSITIVE IDENTIFICATION DENIES, AND AN UNKNOWN ROUTE DOES NOT
 * -------------------------------------------------------------------
 * At runtime the gate refuses only what is POSITIVELY identified as
 * BIDYOUROFFER_ONLY. An unclassified route is a build failure, not a runtime
 * 404 — the decision belongs in this file, where it can be read and reviewed,
 * not in a mystery 404 on some shared endpoint a Hire Agent wizard needed. The
 * catalog is complete on the day it ships and the contract test is what keeps it
 * complete.
 *
 * PATTERNS
 * --------
 * A pattern is the route's registered URI, optionally prefixed with a request
 * method ('POST /'), optionally ending in '*' to match a path prefix.
 * '/' matches the root URI only.
 */
final class ProductSurfaceCatalog
{
    public const BIDYOUROFFER_ONLY = 'bidyouroffer_only';
    public const BIDYOURAGENT      = 'bidyouragent';
    public const SHARED            = 'shared';
    public const INFRASTRUCTURE    = 'infrastructure';
    public const UNCLASSIFIED      = 'unclassified';

    /**
     * BidYourOffer-only user-facing surfaces. Hidden AND refused in BidYourAgent
     * mode.
     *
     * NOTE ON THE LEGACY AUCTION FAMILIES. `property/*`, `criteria/*`,
     * `tenant/criteria/*`, `landlord/auction/*` and the service auctions are the
     * original property/offer marketplace — a consumer lists a property or a
     * criteria set and receives bids. None of them is the Hire Agent flow
     * (`*_agent_auctions`), all of them are property-transaction surfaces, and
     * so they sit on the BidYourOffer side of the product line.
     */
    public const BIDYOUROFFER_PATTERNS = [

        // --- Create Offer Listing, all four roles: create, edit, view, ask,
        //     request a showing, the MLS quick-import entry points, and the
        //     landlord rental-qualification pages published beside them.
        'offer-listing/*',

        // --- Offer Playoff (offer creation + the offer negotiation lifecycle).
        'offer/listing/*',
        'offers',
        'offers/*',
        'agent/offer-listings',

        // --- Browse Offer Listings / property search.
        'search/properties-auctions',      // searchListing — the global "Browse Listings"
        'search/seller-listings',
        'search/buyer-listings',
        'search/rental-properties',
        'search/tenant-listings',
        'search/buyer-criteria-auctions',
        'search/agent-service-needed',

        // --- Legacy property auction: create, edit, the five-step wizard, bids,
        //     counters, visibility, renewal and the seller's property hub.
        'add-listing',
        'edit-seller-property-listing/*',
        'property/*',
        'seller-property-auctions',
        'seller_property_partial_view',
        'renew_property_sale/*',
        'renew_sale',

        // --- Buyer criteria auctions (incl. the unnamed POST / accept endpoint).
        'POST /',
        'criteria/*',
        'buyer-criteria/*',
        'buyer-agent/auction/*',
        'renew_buyer_criteria/*',
        'renew_buyer',

        // --- Tenant criteria auctions.
        'tenant/criteria/*',
        'renew_tenant_criteria/*',
        'renew_tenant',

        // --- Landlord (rental property) auctions.
        'landlord/auction/*',
        'landlord/auctions',
        'landlord/auctions/search',
        'renew_landloard_auction/*',
        'renew_landloard',

        // --- Legacy service auctions (agent service needed / seller service).
        'agent/service/*',
        'service/auction/*',
        'seller/service/*',

        // --- Showing requests. Showing belongs to an Offer Listing
        //     (Showing::offerAuction), so the whole family is BidYourOffer.
        'showings',
        'showings/*',
        'my-showings',
        'my-showings/*',

        // --- MLS Match Check and the Stellar MLS property search surfaces.
        'match-check',
        'match-check/*',
        'stellar/*',
    ];

    /**
     * BidYourAgent surfaces — the product this isolation exists to launch.
     */
    public const BIDYOURAGENT_PATTERNS = [

        // --- Hire Agent listings for all four roles: create, draft, edit, view,
        //     end, plus the Hire Me direct-entry and public/widget entry points.
        'hire/*',
        'hire-agent-leads',
        'hire-agent-leads/*',
        'widget/hire/*',

        // --- Seller Hire Agent (incl. PR #143 accept and PR #145 reject, which
        //     live under hire/* above; these are the rest of the seller surface).
        'seller/agent/*',
        'seller/agents/list',
        'seller/counter/bid',
        'seller/counter-terms/*',
        'seller/edit-counter-terms/*',
        'seller/biding/auctions/list',

        // --- Buyer Hire Agent.
        'buyer/add-auction',
        'buyer/agent/*',
        'buyer/agents',
        'POST buyer',                     // acceptBABid
        'buyer/hire/*',
        'buyer/counter-terms/*',
        'buyer/edit-counter-terms/*',
        'buyer/add-counter-terms',
        'buyer/update-counter-terms/*',
        'buyer/biding/auctions/list',
        'buyer-agent/bid/ba/store',       // agent saves a Buyer Hire Agent bid

        // --- Landlord Hire Agent.
        'landlord/agent/*',
        'landlord/hire/*',
        'landlord/counter-terms/*',
        'landlord/edit-counter-terms/*',
        'landlord/biding/auctions/list',

        // --- Tenant Hire Agent.
        'tenant/agent/*',
        'tenant/agent-bids/*',
        'tenant/hire/*',
        'tenant/counter-terms/*',
        'tenant/add-counter-terms',
        'tenant/update-counter-terms/*',
        'tenant/biding/auctions/list',

        // --- Agent side of Hire Agent: bidding, counter terms, leads, presets,
        //     default profiles, profile/avatar, referral QR, agent discovery.
        'agent/seller/bid/add/*',
        'agent/hire-listings',
        'agent/hire-leads',
        'agent/hire-leads/*',
        'counter-terms/*',
        'add-counter-terms',
        'edit-counter-terms/*',
        'update-counter-terms/*',
        'agent/presets',
        'agent/presets/*',
        'agent/default-profiles',
        'agent/default-profiles/*',
        'agent/avatar',
        'agent/{agentShortId}/profile',
        'agent/my-referrals',
        'qr/settings',
        'prefered_agents',

        // --- Hire Agent marketplace search (agents finding listings to bid on).
        'search/agents',
        'search/seller-agent-needed',
        'search/buyer-agent-needed',
        'search/hire/landlord/agent/auctions',
        'search/hire/tenant/agent/auctions',
    ];

    /**
     * Shared surfaces — required by BidYourAgent, and equally by BidYourOffer.
     *
     * Authentication, the account, the dashboard shell, messaging, notifications,
     * the accepted-bid summary, listing documents, Ask AI / Agent AI, the public
     * marketing pages (already BidYourAgent-branded) and small helpers.
     *
     * Some of these RENDER BidYourOffer content in combined mode — the dashboard,
     * My Listings, the home page. They stay reachable in BidYourAgent mode and
     * filter their own content instead; hiding a user's own dashboard would be a
     * data loss, not an isolation.
     */
    public const SHARED_PATTERNS = [
        // Public marketing.
        'GET /',
        'faq',
        'how-it-works-for-*',
        'data-sources',
        'data-sources/*',

        // Authentication and the account.
        'login',
        'login/*',
        'logout',
        'register',
        'forgot-password',
        'reset-password',
        'reset-password/*',
        'confirm-password',
        'verify-email',
        'verify-email/*',
        'email/verification-notification',
        'password/change',
        'settings',
        'settings/*',
        'check_email/*',
        'check_username/*',

        // Dashboard shell and the owner's own listing/bid hubs (product-filtered).
        'dashboard',
        'my-listings',
        'my-listings/*',
        'my-bids/{type?}',
        'my-friends',

        // Messaging and notifications.
        'messages',
        'message2',
        'send-chat-message',
        'start-chat/*',
        'chat/*',
        'chat_bot_reply/*',
        'load_chat_messages/*',
        'chat_gpt',
        'chat_gpt_reply',
        'notifications/*',

        // Accepted Bid Summary (the Hire Agent contract as well as the offer one).
        'accepted-bid-summary/*',
        'bid/*',

        // Listing documents and downloads (role- and product-neutral by param).
        'listings/*',
        'seller/listings/*',
        'buyer/listings/*',
        'landlord/listings/*',
        'tenant/listings/*',

        // Ask AI / Agent AI / AI knowledge base.
        'ask-ai/*',
        'api/ask-ai/ask',
        'agent-ai/*',
        'agent/ai-inbox',
        'agent/ai-inbox/*',
        'agent/ai-analytics',
        'ai-knowledge/*',

        // Compatibility report betas (kill-switched independently).
        'bya-beta/*',
        'consumer/*',

        // Location DNA / Property DNA surfaces used by Hire Agent listings.
        'agent/location-dna/*',
        'agent/property-dna/*',
        'owner/property-dna/*',

        // Profiles, referrals, small helpers, telemetry.
        'author/*',
        'u/*',
        'get-qr-code',
        'invite/*',
        'get-states',
        'get-cities',
        'render_patch',
        'option_dynamic',
        'option_dynamic_city',
        'manage/bot/*',
        '_telemetry/*',
        'test-notification',
    ];

    /**
     * Framework, admin and local-only infrastructure. Never a product surface.
     *
     * Admin is behind adminAuth and administers BOTH products; refusing half of
     * it by product would leave an operator unable to approve the listings their
     * own deployment is serving.
     */
    public const INFRASTRUCTURE_PATTERNS = [
        'admin',
        'admin/*',
        'livewire/*',
        '{locale}/livewire/*',
        '_ignition/*',
        '_debugbar/*',
        'sanctum/*',
        'api/user',
        'dev/*',
        'dev-login/*',
    ];

    /**
     * The disposition of one route. Exactly one, or UNCLASSIFIED.
     */
    public static function dispositionFor(string $method, string $uri): string
    {
        foreach (self::all() as $disposition => $patterns) {
            if (self::matches($method, $uri, $patterns)) {
                return $disposition;
            }
        }

        return self::UNCLASSIFIED;
    }

    /**
     * Every disposition this route matches — the contract test's overlap check.
     *
     * @return array<int, string>
     */
    public static function dispositionsFor(string $method, string $uri): array
    {
        $hits = [];

        foreach (self::all() as $disposition => $patterns) {
            if (self::matches($method, $uri, $patterns)) {
                $hits[] = $disposition;
            }
        }

        return $hits;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return [
            self::INFRASTRUCTURE    => self::INFRASTRUCTURE_PATTERNS,
            self::BIDYOUROFFER_ONLY => self::BIDYOUROFFER_PATTERNS,
            self::BIDYOURAGENT      => self::BIDYOURAGENT_PATTERNS,
            self::SHARED            => self::SHARED_PATTERNS,
        ];
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private static function matches(string $method, string $uri, array $patterns): bool
    {
        $method = strtoupper($method);

        foreach ($patterns as $pattern) {
            $required = null;

            if (strpos($pattern, ' ') !== false) {
                [$required, $pattern] = explode(' ', $pattern, 2);
                $required = strtoupper($required);
            }

            if ($required !== null && $required !== $method) {
                // HEAD is served by the GET route; treat it as the GET it is.
                if (! ($required === 'GET' && $method === 'HEAD')) {
                    continue;
                }
            }

            if ($pattern === '/') {
                if ($uri === '/') {
                    return true;
                }

                continue;
            }

            if (substr($pattern, -1) === '*') {
                if (strpos($uri, substr($pattern, 0, -1)) === 0) {
                    return true;
                }

                continue;
            }

            if ($uri === $pattern) {
                return true;
            }
        }

        return false;
    }
}
