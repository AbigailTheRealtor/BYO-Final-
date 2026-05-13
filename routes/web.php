<?php

use Carbon\Carbon;
use App\Models\User;
use GuzzleHttp\Middleware;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Mail\Agent\PropertyAdd;
use App\Mail\NotificationEmail;
use App\Models\PropertyAuction;
use App\Models\BuyerAgentAuction;
use App\Models\PropertyAuctionBid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\PropertyAuctionMeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Models\PropertyAuctionBidMeta;
use App\Models\BuyerCriteriaAuctionBid;
use App\Events\SellerPropertyAuctionBid;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\AuctionController;
use App\Events\SellerPropertyAuctionCreated;
use App\Events\SellerPropertyAuctionUpdated;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\BuyerController;
use App\Http\Controllers\AuctionChatController;
use App\Http\Controllers\BotQuestionController;
use App\Http\Controllers\SearchAgentController;
use App\Http\Controllers\Admin\ACTypeController;
use App\Http\Controllers\Admin\CountyController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ApplianceController;
use App\Http\Controllers\Admin\FinancingController;
use App\Http\Controllers\LandlordAuctionController;
use App\Http\Controllers\PropertyAuctionController;
use App\Http\Controllers\ReferralAuctionController;
use App\Http\Controllers\Admin\AdminAgentController;
use App\Http\Controllers\Admin\FeeIncludeController;
use App\Http\Controllers\Admin\WaterExtraController;
use App\Http\Controllers\Admin\HeatingFuelController;
use App\Http\Controllers\BuyerAgentAuctionController;
use App\Http\Controllers\CommonBotQuestionController;
use App\Http\Controllers\Admin\AgentServiceController;
// use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PropertyTypeController;
use App\Http\Controllers\PropertyAuctionBidController;
use App\Http\Controllers\SellerAgentAuctionController;
use App\Http\Controllers\SellerOfferListingController;
use App\Http\Controllers\AcceptedBidSummaryController;
use App\Http\Controllers\TenantAgentAuctionController;
use App\Http\Controllers\Admin\SellerServiceController;
use App\Http\Controllers\Admin\WaterViewTypeController;
use App\Http\Controllers\AgentCounteredTermsController;
use App\Http\Controllers\AgentServiceAuctionController;
use App\Http\Controllers\BuyerAgentAuctionBidController;
use App\Http\Controllers\BuyerCriteriaAuctionController;
use App\Http\Controllers\LandlordAgentAuctionController;
use App\Http\Controllers\SellerServiceAuctionController;
use App\Http\Controllers\TenantAgentAuctionBidController;
use App\Http\Controllers\TenantCriteriaAuctionController;
use App\Http\Controllers\AgentServiceAuctionBidController;
use App\Http\Controllers\BuyerCriteriaAuctionBidController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CounterBidController;
use App\Http\Controllers\CounteredTerms;
use App\Http\Controllers\LandlordAgentAuctionBidController;
use App\Http\Controllers\LandlordCounteredTermsController;
use App\Http\Controllers\SellerCounterBidController;
use App\Http\Controllers\SellerCounteredTermsController;
use App\Http\Controllers\SellerServiceAuctionBidController;
use App\Http\Controllers\TenantCounteredTermsController;
use App\Http\Controllers\BuyerCounteredTermsController;
use App\Http\Controllers\TenantCriteriaAuctionBidController;
use App\Http\Controllers\SocialAuth;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantCriteriaAuction;
use App\Http\Livewire\TenantAgentAuction as liverTenantAgentAuction;
use App\Http\Livewire\OfferAuction as liverOfferAuction;
use App\Http\Livewire\Tenant\TenantAgentAuctionBid;
use App\Http\Livewire\Tenant\TenantAgentAuctionCounterTerm;
use App\Http\Livewire\Landlord\LandlordAgentAuctionBid;
use App\Http\Livewire\TenantAgentAuctionEdit;
use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as liverBuyerAgentAuction;
use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction as liverLandLordAgentAuction;
use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit;
use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuctionEdit;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuction as liverSellerAuction;
use App\Http\Livewire\Buyer\BuyerAgentAuctionBid;
use App\Http\Livewire\Seller\SellerAgentAuctionBid;

use App\Http\Controllers\NotificationController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
| To add countries data please run this command "php artisan country-data:install"
*/




Route::get('/test-notification', function () {
    $user = Auth::user();
    if (!$user) {
        return "No user logged in";
    }

    // Create a test notification
    $user->notify(new \App\Notifications\BidSubmittedNotification(
        (object) [
            'id' => 1,
            'user' => (object) ['first_name' => 'Test', 'last_name' => 'User']
        ],
        (object) [
            'id' => 1,
            'title' => 'Test Property',
            'user_id' => $user->id
        ]
    ));

    return "Test notification sent to user: " . $user->id;
});

// web.php

Route::middleware(['auth', 'verified'])->group(function () {
    // Seller Agent Auction — Counter Terms (accessible by both seller and agent)
    Route::get('hire/seller/agent/auction/bid/{bid_id}/view-counter', [SellerAgentAuctionController::class, 'view_counter_terms'])->name('hire.seller.agent.auction.bid.view-counter');
    Route::post('hire/seller/agent/auction/counter/accept', [SellerAgentAuctionController::class, 'accept_seller_counter'])->name('hire.seller.agent.auction.counter.accept');
    Route::post('hire/seller/agent/auction/counter/reject', [SellerAgentAuctionController::class, 'reject_seller_counter'])->name('hire.seller.agent.auction.counter.reject');
});

Route::middleware('auth')->group(function () {
    Route::get('/agent/hire-listings', [AgentController::class, 'hireListings'])->name('agent.hire-listings');
    Route::get('/agent/offer-listings', [AgentController::class, 'offerListings'])->name('agent.offer-listings')->middleware('offerPlayoffAccess');

    // Phase-2 Hire-Me direct entry — confirm/create (POST) only; GET preview is public below
    Route::post('/hire/agent/direct/{agentId}/{role}/{propertyType}', [\App\Http\Controllers\HireAgentDirectController::class, 'confirm'])->name('hire.agent.direct.confirm');

    // Phase-2 Hire-Me acknowledgment page (accept flow only)
    Route::get('/hire/agent/direct/{agentId}/{role}/{propertyType}/acknowledge', [\App\Http\Controllers\HireAgentDirectController::class, 'acknowledge'])->name('hire.agent.direct.acknowledge');
    Route::post('/hire/agent/direct/{agentId}/{role}/{propertyType}/acknowledge', [\App\Http\Controllers\HireAgentDirectController::class, 'acknowledgeSubmit'])->name('hire.agent.direct.acknowledge.submit');
    Route::get('/hire/agent/direct/{agentId}/{role}/{propertyType}/submitted', [\App\Http\Controllers\HireAgentDirectController::class, 'submitted'])->name('hire.agent.direct.submitted');

    // Phase-2 Hire-Me counter flow (self-contained, no listing/bid created)
    Route::get('/hire/agent/direct/{agentId}/{role}/{propertyType}/counter', [\App\Http\Controllers\HireAgentDirectController::class, 'counter'])->name('hire.agent.direct.counter');
    Route::post('/hire/agent/direct/{agentId}/{role}/{propertyType}/counter', [\App\Http\Controllers\HireAgentDirectController::class, 'counterSubmit'])->name('hire.agent.direct.counter.submit');

    // Agent Referral Activity page
    Route::get('/agent/my-referrals', [\App\Http\Controllers\AgentReferralPageController::class, 'index'])->name('agent.my-referrals');

    // Phase-3 Agent Preset Management
    Route::get('/agent/presets', [\App\Http\Controllers\AgentPresetController::class, 'index'])->name('agent.presets.index');
    Route::get('/agent/presets/{role}/{propertyType}/edit', [\App\Http\Controllers\AgentPresetController::class, 'edit'])->name('agent.presets.edit');
    Route::post('/agent/presets/{role}/{propertyType}', [\App\Http\Controllers\AgentPresetController::class, 'save'])->name('agent.presets.save');
    Route::post('/agent/avatar', [\App\Http\Controllers\AgentPresetController::class, 'uploadAvatar'])->name('agent.avatar.upload');

    Route::get('/notifications/fetch', [NotificationController::class, 'fetch'])->name('notifications.fetch');
    Route::post('/notifications/mark-read', [NotificationController::class, 'markRead'])->name('notifications.markRead');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::get('/notifications/{notification}/go', [NotificationController::class, 'go'])->name('notifications.go');
    Route::post('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');
    
    Route::get('/accepted-bid-summary/{id}', [AcceptedBidSummaryController::class, 'view'])->name('accepted-bid-summary.view');
    Route::get('/accepted-bid-summary/{id}/sign', [AcceptedBidSummaryController::class, 'showSignForm'])->name('accepted-bid-summary.sign-form');
    Route::post('/accepted-bid-summary/{id}/sign', [AcceptedBidSummaryController::class, 'sign'])->name('accepted-bid-summary.sign');
    Route::post('/accepted-bid-summary/{id}/documents', [AcceptedBidSummaryController::class, 'storeDocuments'])->name('accepted-bid-summary.store-documents');
    Route::get('/accepted-bid-summary/{id}/download-pdf', [AcceptedBidSummaryController::class, 'downloadPdf'])->name('accepted-bid-summary.download-pdf');
    Route::get('/accepted-bid-summary/{id}/document/{type}', [AcceptedBidSummaryController::class, 'downloadDocument'])->name('accepted-bid-summary.download-document');
    Route::get('/bid/{bidId}/summary', [AcceptedBidSummaryController::class, 'getByBid'])->name('accepted-bid-summary.by-bid');
});

// Public agent profile — generic fallback only. No auth required.
// This route is intentionally role/property-type agnostic (defaults to Buyer Residential).
// For preset-specific sharing, use the `hire.agent.public` route instead.
// Preset cards on the index and edit pages must NOT link here.
Route::get('/agent/{agentShortId}/profile', [\App\Http\Controllers\AgentProfileController::class, 'show'])
    ->name('agent.profile.public')
    ->where('agentShortId', '[0-9a-f]+');

// Phase-2 Hire-Me direct entry — preview (GET), public, no auth required.
Route::get('/hire/agent/direct/{agentId}/{role}/{propertyType?}', [\App\Http\Controllers\HireAgentDirectController::class, 'show'])->name('hire.agent.direct.preview');

// Phase-4 Clean public Hire Me URL — resolves agent by short_id, redirects to existing direct flow
// Constraint [0-9a-f]+ ensures no collision with /hire/agent/..., /hire/seller/..., etc.
Route::get('/hire/{agentShortId}/{role}/{propertyType?}', [\App\Http\Controllers\HireAgentDirectController::class, 'showPublic'])
    ->name('hire.agent.public')
    ->where('agentShortId', '[0-9a-f]+');

// Widget — public, no auth. Read-only teaser card embeddable via iframe.
Route::get('/widget/hire/{agentShortId}/{role}/{propertyType?}', [\App\Http\Controllers\WidgetController::class, 'show'])
    ->where('agentShortId', '[0-9a-f]+')
    ->name('hire.agent.widget');


// Route::post('/notification', [NotificationController::class, 'notification']);


//social auth  routes
Route::get('/login/facebook', [SocialAuth::class, 'facebookIndex'])->name('facebook.login');
Route::get('/login/facebook/callback', [SocialAuth::class, 'facebookCallback'])->name('facebook.callback');

Route::get('/login/google', [SocialAuth::class, 'googleIndex'])->name('google.login');
Route::get('/login/google/callback', [SocialAuth::class, 'googleCallback'])->name('google.callback');



Route::post('/prefered_agents', [SellerAgentAuctionController::class, 'prefered_agents']);
Route::post('/option_dynamic', [BuyerAgentAuctionController::class, 'dynamic_option']);
Route::post('/seller_property_partial_view', [PropertyAuctionController::class, 'seller_property_partial_view']);
Route::post('/option_dynamic_city', [BuyerAgentAuctionController::class, 'dynamic_option_city']);
Route::get('/renew_property_sale/{id}', [PropertyAuctionController::class, 'renew']);
Route::get('/renew_buyer_criteria/{id}', [BuyerCriteriaAuctionBidController::class, 'renew']);
Route::get('/renew_landloard_auction/{id}', [LandlordAuctionController::class, 'renew']);
Route::get('/renew_tenant_criteria/{id}', [TenantCriteriaAuctionController::class, 'renew']);
Route::post('renew_sale', [PropertyAuctionController::class, 'renew_save'])->name('renewBID');
Route::post('renew_tenant', [TenantCriteriaAuctionController::class, 'renew_save'])->name('renewTenant');
Route::post('renew_buyer', [BuyerCriteriaAuctionBidController::class, 'renew_save'])->name('renewBuyer');
Route::post('renew_landloard', [LandlordAuctionController::class, 'renew_save'])->name('renewLandlord');


// general routes
Route::post('render_patch', [UserController::class, 'fetchPatches'])->name('fetchPatches');


//End

Route::get('/', function () {
    return view('home');
})->name('home');

// Referral capture — Phase 4. Public, no auth.
Route::get('/invite/{code}', [\App\Http\Controllers\ReferralController::class, 'capture'])
    ->name('referral.capture');

/////////////////////////////////Testing Route ///////////////////////////////

Route::get('/testing', function () {

    $get_agent = User::where('user_type', 'agent')->get();
    dd($get_agent[7]->info('city'));
});

///////////////////////////////End Testing Route/////////////////////////////////

Route::get('/check_username/{username}', function ($username) {
    $count = User::where('user_name', $username)->count();
    return response()->json(['success' => true, 'count' => $count]);
});

Route::get('/check_email/{email}', function ($email) {
    $count = User::where('email', $email)->count();
    return response()->json(['success' => true, 'count' => $count]);
});

/* Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth','verified'])->name('dashboard'); */

Route::get('get-states', [DashboardController::class, 'getStates'])->name('getStates');
Route::get('get-cities', [DashboardController::class, 'getCities'])->name('getCities');

Route::get('/search/properties-auctions', [PropertyAuctionController::class, 'searchListing'])->name('searchListing');
Route::get('/search/agent-service-needed', [AgentServiceAuctionController::class, 'searchListing'])->name('service.searchListing');
Route::get('/search/seller-agent-needed', [SellerAgentAuctionController::class, 'searchListing'])->name('seller.agent.searchListing');
Route::get('/search/seller-listings', [SellerOfferListingController::class, 'searchOfferListings'])->name('offer.listing.seller.searchListing');
Route::get('/search/buyer-agent-needed', [BuyerAgentAuctionController::class, 'searchListing'])->name('buyer.agent.searchListing');
Route::get('/search/buyer-criteria-auctions', [BuyerCriteriaAuctionController::class, 'searchListing'])->name('buyer.criteria.searchListing');
Route::get('/search/agents', [SearchAgentController::class, 'search'])->name('search.agents');


Route::get('/hire/agent/auction/view/{id}', [LandlordAgentAuctionController::class, 'view'])->name('landlord.agent.auction.view');
Route::get('/hire/agent/auction/bid/view/{id}', [LandlordAgentAuctionBidController::class, 'view'])->name('landlord.agent.auction.bid.view');
Route::post('/hire/agent/auction/end/{id}', [LandlordAgentAuctionController::class, 'endAuction'])->name('landlord.agent.auction.end');
Route::get('/search/hire/landlord/agent/auctions', [LandlordAgentAuctionController::class, 'search'])->name('landlord.agent.auctions.search');

Route::get('tenant/hire/agent/auction/view/{id}', [TenantAgentAuctionController::class, 'view'])->name('tenant.agent.auction.view');
Route::get('tenant/agent-bids/{bidId}', [TenantAgentAuctionController::class, 'viewBidPreview'])->name('tenant.agent.bid.preview')->middleware('auth');
Route::get('/search/hire/tenant/agent/auctions', [TenantAgentAuctionController::class, 'search'])->name('tenant.agent.auctions.search');

Route::get('/tenant/criteria/auction/view/{id}', [TenantCriteriaAuctionController::class, 'view'])->name('tenant.criteria.auction.view');
Route::get('/tenant/criteria/auctions/search', [TenantCriteriaAuctionController::class, 'search'])->name('tenant.criteria.auctions.search');


Route::get('/property/listing/view/{id}', [PropertyAuctionController::class, 'viewPropertyListing'])->name('view-pl');
Route::post('/property/auction/end/{id}', [PropertyAuctionController::class, 'endAuction'])->name('property.auction.end');

Route::get('property/counter/bid/{bid_id}/{auction_id}', [CounterBidController::class, 'addCounterBid'])->name('add-counterBiding');
Route::post('/property/counter/bid/{bid_id}', [CounterBidController::class, 'store'])->name('counterBiding');
Route::post('/seller/counter/bid', [SellerCounterBidController::class, 'store'])->name('sellerCounterBid');

// add new route for seller nisar
Route::get('/how-it-works-for-sellers-details', function () {
    $page_data['title'] = 'How it works for sellers details';
    return view('sellerDetails', $page_data);
})->name('sellerDetails');

Route::get('/how-it-works-for-buyers-details', function () {
    $page_data['title'] = 'How it works for buyers details';
    return view('buyerDetails', $page_data);
})->name('buyerDetails');

// add new new route end



Route::get('/how-it-works-for-sellers', function () {
    $page_data['title'] = 'How it works for sellers';
    return view('sellerWorks', $page_data);
})->name('sellerWorks');
Route::get('/how-it-works-for-buyers', function () {
    $page_data['title'] = 'How it works for buyers';
    return view('buyerWorks', $page_data);
})->name('buyerWorks');

Route::get('/how-it-works-for-sellers-agent', function () {
    $page_data['title'] = "How it works for Seller's Agent";
    return view('sellerWorksAgent', $page_data);
})->name('sellerWorksAgent');
Route::get('/how-it-works-for-buyers-agent', function () {
    $page_data['title'] = "How it works for Buyer's Agent";
    return view('buyerWorksAgent', $page_data);
})->name('buyerWorksAgent');

Route::get('/faq', function () {
    $page_data['title'] = "FAQ";
    return view('faqs', $page_data);
})->name('faqs');

Route::get('/author/{id}', [UserController::class, 'author'])->name('author');
Route::get('/u/{uri}', [UserController::class, 'short_uri'])->name('short.uri');
Route::get('get-qr-code', function (Request $request) {
    $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::encoding('UTF-8')->size(150)->generate($request->uri);
    return $qr;
    // return \LaravelQRCode\Facades\QRCode::url($request->uri)->setMargin(0)->svg();
})->name('qr-code');




Route::get('/seller/listings/{id}/download', [\App\Http\Controllers\ListingDownloadController::class, 'seller'])->name('seller.listings.download')->middleware('auth');
Route::get('/buyer/listings/{id}/download', [\App\Http\Controllers\ListingDownloadController::class, 'buyer'])->name('buyer.listings.download')->middleware('auth');
Route::get('/landlord/listings/{id}/download', [\App\Http\Controllers\ListingDownloadController::class, 'landlord'])->name('landlord.listings.download')->middleware('auth');
Route::get('/tenant/listings/{id}/download', [\App\Http\Controllers\ListingDownloadController::class, 'tenant'])->name('tenant.listings.download')->middleware('auth');

Route::get('/seller/agent/auction/view/{id}', [SellerAgentAuctionController::class, 'viewDetail'])->name('seller.agent.auction.detail');
Route::get('/buyer/agent/auction/view/{id}', [BuyerAgentAuctionController::class, 'viewAuctionDetails'])->name('buyer.view-auction');
Route::get('/seller/agent/bid/{bid_id}', [SellerAgentAuctionController::class, 'bidDetail'])->name('seller.agent.bid.detail')->middleware('auth');
Route::get('/buyer/agent/bid/{bid_id}', [BuyerAgentAuctionController::class, 'bidDetail'])->name('buyer.agent.bid.detail')->middleware('auth');
Route::get('/tenant/agent/auction/view/{id}', [TenantAgentAuctionController::class, 'view'])->name('tenant.agent.view.auction.view');
Route::get('/agent/service/auction/{id}', [AgentServiceAuctionController::class, 'view'])->name('agent.service.auction.view');
Route::get('/criteria/view/{id}', [BuyerCriteriaAuctionController::class, 'view'])->name('buyer.criteria.view');
Route::get('seller/service/auction/view/{id}', [SellerServiceAuctionController::class, 'view'])->name('seller.service.auction.view');
Route::get('seller/service/auction/search', [SellerServiceAuctionController::class, 'search'])->name('seller.service.auction.search');
Route::get("/landlord/auctions/search", [LandlordAuctionController::class, 'search_listing'])->name('agent.landlord.auctions.search');
Route::get("/landlord/auction/view/{id}", [LandlordAuctionController::class, 'view'])->name('agent.landlord.auction');
Route::get('/landlord/auction/bid/view/{id}', [LandlordAuctionController::class, 'viewBid'])->name('landlord.auction.bid.view');
Route::post('/landlord/auction/end/{id}', [LandlordAuctionController::class, 'endAuction'])->name('landlord.auction.end');




// Only logged in user can access these routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Route::get('media/manager', [MediaController::class, 'manager'])->name('media.manager');
    // Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');


    Route::get('/password/change', [UserController::class, 'changePasswordForm'])->name('password.change');
    Route::post('/password/change', [UserController::class, 'changePassword']);
    // Route::get('/profile',[UserController::class, 'profileForm'])->name('profile');

    // Admin Can't be access these routes
    Route::middleware('noAdmin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('/my-listings', [DashboardController::class, 'allListings'])->name('my.listings');
        Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
        Route::post('/settings', [DashboardController::class, 'saveSettings']);
        Route::post('/settings/delete-account', [DashboardController::class, 'deleteAccount'])->name('settings.delete-account');
        Route::get('/my-bids/{type?}', [DashboardController::class, 'myBids'])->name('myBids');
        Route::get('/seller-property-auctions', [PropertyAuctionController::class, 'list'])->name('myAuctions');
        Route::get('/start-chat/{type}/{id}', [AuctionChatController::class, 'new'])->name('auction-chat');
        Route::post('/message2', [AuctionChatController::class, 'new1'])->name('auction-chat1');
        Route::post('/send-chat-message', [AuctionChatController::class, 'sendMessage'])->name('send-chat-message');

        // Old route commented by waqas on 26 may 2023
        // Route::get('/messages/{token?}', [AuctionChatController::class, 'messages'])->name('messages');
        // Old route commented by waqas on 26 may 2023
        Route::get('/messages', [AuctionChatController::class, 'messages'])->name('messages');
        Route::get('/chat_bot_reply/{token}', [AuctionChatController::class, 'chat_bot_reply'])->name('chat_bot_reply');
        Route::get('/load_chat_messages/{token}', [AuctionChatController::class, 'load_chat_messages'])->name('load_chat_messages');
        Route::get('/my-friends', [DashboardController::class, 'myFriends'])->name('myFriends');
    });

    Route::get('/manage/bot/questions/{type}/{id}', [BotQuestionController::class, 'index'])->name('manage.bot.questions');
    Route::post('/manage/bot/questions/{type}/{id}', [BotQuestionController::class, 'store']);
    Route::put('/manage/bot/question/update/{id}', [BotQuestionController::class, 'update'])->name('bot.question.update');
    Route::get('/manage/bot/question/delete/{id}', [BotQuestionController::class, 'delete'])->name('bot.question.delete');

    // Only Seller can access these routes
    //    Route::middleware('sellerAuth')->group(function () {


    Route::get('/hire/agent/seller', liverSellerAuction::class)->name('sellerAgentHireAuction');

    // Route::get('/hire/agent/seller', [SellerAgentAuctionController::class, 'sellerAgentHireAuction'])->name('sellerAgentHireAuction');
    Route::post('/hire/agent/seller', [SellerAgentAuctionController::class, 'sellerAgentHireAuctionSave']);
    Route::get('/hire/agent/seller/list', [SellerAgentAuctionController::class, 'hireSellerAgentHireAuctions'])->name('hireSellerAgentHireAuctions');
    Route::get('hire/agent/seller/edit/{id}', [SellerAgentAuctionController::class, 'editSellerAgentHireAuction'])->name('editSellerAgentHireAuction');
    Route::post('hire/agent/seller/update', [SellerAgentAuctionController::class, 'updateSellerAgentHireAuction'])->name('updateSellerAgentHireAuction');
    Route::post('hire/agent/seller/bid/accept', [SellerAgentAuctionController::class, 'acceptSABid'])->name('acceptSABid');
    Route::get('seller/agents/list', [SellerAgentAuctionController::class, 'myAgents'])->name('seller.agents');
    Route::get('seller/service/auction/add', [SellerServiceAuctionController::class, 'add'])->name('seller.service.auction.add');
    Route::post('seller/service/auction/store', [SellerServiceAuctionController::class, 'store'])->name('seller.service.auction.store');
    Route::get('seller/service/auction/edit/{id}', [SellerServiceAuctionController::class, 'edit'])->name('seller.service.auction.edit');
    Route::post('seller/service/auction/update', [SellerServiceAuctionController::class, 'update'])->name('seller.service.auction.update');
    Route::get('seller/service/auction/list', [SellerServiceAuctionController::class, 'list'])->name('seller.service.auction.list');
    Route::post('/seller/service/auction/bid/accept', [SellerServiceAuctionBidController::class, 'acceptSSBid'])->name('seller.service.auction.bid.accept');
    Route::any('seller/counter-terms/{id}', [SellerCounteredTermsController::class, 'add'])->name('seller.counter-terms');
    Route::any('seller/add-counter-terms', [SellerCounteredTermsController::class, 'store'])->name('seller.add-counter-terms');
    Route::any('seller/edit-counter-terms/{id}', [SellerCounteredTermsController::class, 'edit'])->name('seller.edit-counter-terms');
    Route::any('seller/update-counter-terms/{id}', [SellerCounteredTermsController::class, 'update'])->name('seller.update-counter-terms');
    //  });




    // Only Seller's Agent can access these routes
    /* Route::middleware('sellerAgentAuth')->group(function(){
        Route::get('/add-listing', [PropertyAuctionController::class, 'addListing'])->name('add-listing');
        Route::post('/property/listing/step/1',[PropertyAuctionController::class, 'step1'])->name('save-pl-step1');
        Route::post('/property/listing/step/2',[PropertyAuctionController::class, 'step2'])->name('save-pl-step2');
        Route::post('/property/listing/step/3',[PropertyAuctionController::class, 'step3'])->name('save-pl-step3');
        Route::post('/property/listing/step/4',[PropertyAuctionController::class, 'step4'])->name('save-pl-step4');
        Route::post('/property/listing/step/5',[PropertyAuctionController::class, 'step5'])->name('save-pl-step5');
        Route::post('/agent/seller/bid/save', [SellerAgentAuctionController::class, 'saveSABid'])->name('saveSABid');
    }); */

    // Only Buyer's agent can access these routes
    /* Route::prefix('buyer-agent')->middleware('buyerAgentAuth')->name('buyer_agent.')->group(function(){
        Route::post('/bid/ba/store', [BuyerAgentAuctionBidController::class, 'saveBABid'])->name('saveBABid');
        Route::get('auction/add', [BuyerCriteriaAuctionController::class, 'addAuction'])->name('auction.add');
        Route::post('auction/add', [BuyerCriteriaAuctionController::class, 'storeAuction']);
        Route::get('auction/edit/{id}', [BuyerCriteriaAuctionController::class, 'edit'])->name('auction.edit');
        Route::post('auction/update', [BuyerCriteriaAuctionController::class, 'updateAuction'])->name('auction.update');
    }); */

    // Only Buyer and Agent can access these routes
    Route::middleware('buyerBidderAuth')->group(function () {
        Route::get('/property/listing/add/bid/{id}', [PropertyAuctionBidController::class, 'add_bid'])->name('seller_property_add_bid');
        Route::post('/property/listing/save-pa-bid', [PropertyAuctionBidController::class, 'savePABid'])->name('savePABid');
        Route::post('/property/listing/accept-pa-bid', [PropertyAuctionBidController::class, 'acceptPABid'])->name('acceptPABid');
        Route::post('/property/listing/reject-pa-bid', [PropertyAuctionBidController::class, 'rejectPABid'])->name('rejectPABid');
        Route::post('/property/listing/{id}', [PropertyAuctionBidController::class, 'destroy'])->name('destroyCounter');
        Route::get('/criteria/auctions', [BuyerCriteriaAuctionController::class, 'myAuctions'])->name('buyer.criteria.auctions');
        Route::post('', [BuyerCriteriaAuctionBidController::class, 'acceptBCABid'])->name('acceptBCABid');

        Route::any("/landlord/auction/bid/{id}", [LandlordAuctionController::class, 'add_bid'])->name('agent.landlord.auction.bid');
        Route::post("/landlord/auction/bid/{id}", [LandlordAuctionController::class, 'save_bid']);
    });


    // Only Seller and Agent can access these routes
    Route::middleware('sellerBidderAuth')->group(function () {
        Route::get("/criteria/auction/bid/{id}", [BuyerCriteriaAuctionBidController::class, 'add_bid'])->name('criteria.auction.bid');
        Route::post("/criteria/auction/bid/{id}", [BuyerCriteriaAuctionBidController::class, 'save_bid']);
    });


    // Only Landlord and Agent can access these routes
    Route::middleware('landlordBidderAuth')->group(function () {
        Route::get("tenant/criteria/auction/bid/{id}", [TenantCriteriaAuctionBidController::class, 'add_bid'])->name('tenant.criteria.auction.bid');
        Route::post("tenant/criteria/auction/bid/{id}", [TenantCriteriaAuctionBidController::class, 'save_bid']);
    });



    // Only Buyer can access these routes
    // Route::prefix('buyer')->middleware('buyerAuth')->name('buyer.')->group(function () {
    Route::prefix('buyer')->name('buyer.')->group(function () {

        Route::get('/add-auction', liverBuyerAgentAuction::class)->name('add-auction');
        Route::get('/agent/auction/edit/{auctionId}/{user_type?}', TenantAgentAuctionEdit::class)->defaults('user_type', 'buyer')->name('edit-auction');


        // Route::get('/add-auction', [BuyerAgentAuctionController::class, 'addAuction'])->name('add-auction');
        Route::post('/add-auction', [BuyerAgentAuctionController::class, 'storeAuction']);
        Route::get('/hire/agent/auctions', [BuyerAgentAuctionController::class, 'buyerAgentAuctions'])->name('agent.auctions');
        Route::get('/agents', [BuyerAgentAuctionController::class, 'buyerAgents'])->name('agents');
        Route::get('/hire/agent/auctions/list', [BuyerAgentAuctionController::class, 'list'])->name('agent.auctions.list');

        // Route::get('/agent/auction/edit/{id}', [BuyerAgentAuctionController::class, 'editBuyerAgentAction'])->name('edit-auction');
        Route::post('/agent/auction/update', [BuyerAgentAuctionController::class, 'updateBuyerAgentAuction'])->name('update-auction');
        Route::post('', [BuyerAgentAuctionBidController::class, 'acceptBABid'])->name('acceptBABid');
        Route::any('/counter-terms/{id}', [CounteredTerms::class, 'add'])->name('counter-terms');
        Route::any('/add-counter-terms', [CounteredTerms::class, 'store'])->name('add-counter-terms');
        Route::any('/edit-counter-terms/{id}', [CounteredTerms::class, 'edit'])->name('edit-counter-terms');
        Route::any('/update-counter-terms/{id}', [CounteredTerms::class, 'update'])->name('update-counter-terms');


        Route::post('hire/agent/auction/bid/counter', [BuyerAgentAuctionBidController::class, 'counter_bid'])->name('hire.agent.auction.bid.counter');
        Route::post('hire/agent/auction/bid/accept', [BuyerAgentAuctionBidController::class, 'accept_bid'])->name('hire.agent.auction.bid.accept');
        Route::post('hire/agent/auction/bid/reject', [BuyerAgentAuctionBidController::class, 'reject_bid'])->name('hire.agent.auction.bid.reject');
        Route::get('hire/agent/auction/bid/{bid_id}/view-counter', [BuyerAgentAuctionBidController::class, 'view_counter_terms'])->name('hire.agent.auction.bid.view-counter');

        Route::post('hire/agent/auction/counter/bid/accept', [BuyerAgentAuctionBidController::class, 'accept_counter_bid'])->name('hire.agent.auction.counter.bid.accept');
        Route::post('hire/agent/auction/counter/bid/reject', [BuyerAgentAuctionBidController::class, 'reject_counter_bid'])->name('hire.agent.auction.counter.bid.reject');
        Route::post('hire/agent/auction/buyer/counter/term/accept', [BuyerAgentAuctionBidController::class, 'accept_buyer_counter_term'])->name('hire.agent.auction.buyer.counter.term.accept');
        Route::post('hire/agent/auction/buyer/counter/term/reject', [BuyerAgentAuctionBidController::class, 'reject_buyer_counter_term'])->name('hire.agent.auction.buyer.counter.term.reject');
        Route::any('/counter-terms/{id}', [BuyerCounteredTermsController::class, 'add'])->name('counter-terms');

        Route::get('/counter-terms/{id}/edit', [BuyerCounteredTermsController::class, 'edit'])
            ->name('edit-counter-terms');
    });

    // Only Landlords can access these routes
    Route::prefix('landlord')->name('landlord.')->group(function () {
        // Route::prefix('landlord')->middleware('landlordAuth')->name('landlord.')->group(function () {


        Route::get('/hire/agent/auction', liverLandLordAgentAuction::class)->name('hire.agent.auction');

        // Route::get('/hire/agent/auction', [LandlordAgentAuctionController::class, 'index'])->name('hire.agent.auction');
        Route::post('/hire/agent/auction', [LandlordAgentAuctionController::class, 'store']);
        // Route::get('/hire/agent/auction/edit/{id}', [LandlordAgentAuctionController::class, 'edit'])->name('hire.agent.auction.edit');
        Route::get('/hire/agent/auction/edit/{auctionId}', function ($auctionId) {
            return redirect()->route('hire.agent.auction.edit', ['auctionId' => $auctionId, 'user_type' => 'landlord']);
        })->name('hire.agent.auction.edit');


        Route::post('/hire/agent/auction/edit/{id}', [LandlordAgentAuctionController::class, 'update'])->name('hire.agent.auction.update');
        Route::get('/hire/agent/auctions/list', [LandlordAgentAuctionController::class, 'list'])->name('agent.auctions.list');
        Route::post('hire/agent/auction/bid/accept', [LandlordAgentAuctionBidController::class, 'accept_bid'])->name('hire.agent.auction.bid.accept');
        Route::post('hire/agent/auction/bid/reject', [LandlordAgentAuctionBidController::class, 'reject_bid'])->name('hire.agent.auction.bid.reject');

        Route::get('hire/agent/auction/bid/{bid_id}/view-counter', [LandlordAgentAuctionBidController::class, 'view_counter_terms'])->name('hire.agent.auction.bid.view-counter');
        Route::get('agent/auction/counter-bid/{id}/{bid_id}', [LandlordAgentAuctionBidController::class, 'addCounterBid'])->name('agent.auction.counter-bid');

        Route::post('agent/auction/counter/bid', [LandlordAgentAuctionBidController::class, 'counter_bid'])->name('agent.add.counter-bid');

        Route::post('hire/agent/auction/counter/bid/accept', [LandlordAgentAuctionBidController::class, 'accept_counter_bid'])->name('hire.agent.auction.counter.bid.accept');
        Route::post('hire/agent/auction/counter/bid/reject', [LandlordAgentAuctionBidController::class, 'reject_counter_bid'])->name('hire.agent.auction.counter.bid.reject');

        Route::any('/counter-terms/{id}', [LandlordCounteredTermsController::class, 'add'])->name('counter-terms');
        Route::any('/add-counter-terms', [LandlordCounteredTermsController::class, 'store'])->name('add-counter-terms');
        Route::any('/edit-counter-terms/{id}', [LandlordCounteredTermsController::class, 'edit'])->name('edit-counter-terms');
        Route::any('/update-counter-terms/{id}', [LandlordCounteredTermsController::class, 'update'])->name('update-counter-terms');
    });

    Route::get('/hire/agent/auction/{user_type}/{listingId}', liverTenantAgentAuction::class)->name('hire.agent.auction.draft')->where('user_type', 'tenant|landlord|buyer|seller');
    Route::get('/hire/agent/auction/{user_type?}', liverTenantAgentAuction::class)->name('hire.agent.auction');
    Route::get('/hire/agent/auction/edit/{auctionId}/{user_type}', TenantAgentAuctionEdit::class)->name('hire.agent.auction.edit');

    // Offer Listing routes (Workflow Engine — Offer mode)
    Route::get('/offer/listing/view/{id}', [AgentController::class, 'offerListingView'])->name('offer.listing.view')->middleware('offerPlayoffAccess');
    Route::get('/offer/listing/draft/{listingId}', liverOfferAuction::class)->name('offer.listing.draft')->middleware('offerPlayoffAccess');
    Route::get('/offer/listing/{offer_type?}', liverOfferAuction::class)->name('offer.listing.create')->middleware('offerPlayoffAccess');
    // Only Tenants can access these routes
    //    Route::prefix('tenant')->middleware('tenantAuth')->name('tenant.')->group(function () {
    Route::prefix('tenant')->name('tenant.')->group(function () {

        // Route::get('/hire/agent/auction', function () {
        //     return view('hire_tenant_agent.add'); // Render the Blade view
        // })->name('hire.agent.auction');



        // Route::post('/hire/agent/auction/edit', TenantAgentAuctionEdit::class)->name('hire.agent.auction.edit');



        // Route::get('/hire/agent/auction', [TenantAgentAuctionController::class, 'index'])->name('hire.agent.auction');
        // Route::post('/hire/agent/auction', [TenantAgentAuctionController::class, 'store']);
        // Route::get('/hire/agent/auction/edit/{id}', [TenantAgentAuctionController::class, 'edit'])->name('hire.agent.auction.edit');
        // Route::post('/hire/agent/auction/edit/{id}', [TenantAgentAuctionController::class, 'update']);
        Route::get('/hire/agent/auctions/list', [TenantAgentAuctionController::class, 'list'])->name('agent.auctions.list');


        // Route::get('/hire/agent/auction', [LivewireTenantAgentAuctionController::class, 'index'])->name('hire.agent.auction');
        // Route::post('/hire/agent/auction', [LivewireTenantAgentAuctionController::class, 'store']);
        // Route::get('/hire/agent/auction/edit/{id}', [LivewireTenantAgentAuctionController::class, 'edit'])->name('hire.agent.auction.edit');
        // Route::post('/hire/agent/auction/edit/{id}', [LivewireTenantAgentAuctionController::class, 'update']);
        // Route::get('/hire/agent/auctions/list', [LivewireTenantAgentAuctionController::class, 'list'])->name('agent.auctions.list');
        Route::post('hire/agent/auction/bid/accept', [TenantAgentAuctionBidController::class, 'accept_bid'])->name('hire.agent.auction.bid.accept');
        Route::post('hire/agent/auction/bid/reject', [TenantAgentAuctionBidController::class, 'reject_bid'])->name('hire.agent.auction.bid.reject');
        Route::post('hire/agent/auction/bid/withdraw', [TenantAgentAuctionBidController::class, 'withdraw_bid'])->name('hire.agent.auction.bid.withdraw');

        Route::get('hire/agent/auction/counter-bid/{id}/{bid_id}', [TenantAgentAuctionBidController::class, 'add_counter_bid'])->name('hire.agent.auction.counter-bid');
        Route::post('hire/agent/auction/bid/counter', [TenantAgentAuctionBidController::class, 'counter_bid'])->name('hire.agent.auction.bid.counter');
        Route::post('hire/agent/auction/counter/bid/accept', [TenantAgentAuctionBidController::class, 'accept_counter_bid'])->name('hire.agent.auction.counter.bid.accept');
        Route::post('hire/agent/auction/counter/bid/reject', [TenantAgentAuctionBidController::class, 'reject_counter_bid'])->name('hire.agent.auction.counter.bid.reject');
        Route::get('hire/agent/auction/bid/{bid_id}/view-counter', [TenantAgentAuctionBidController::class, 'view_counter_terms'])->name('hire.agent.auction.bid.view-counter');
        Route::any('/counter-terms/{id}', [TenantCounteredTermsController::class, 'add'])->name('counter-terms');

        Route::get('/counter-terms/{id}/edit', [TenantCounteredTermsController::class, 'edit'])
            ->name('edit-counter-terms');


        Route::any('/add-counter-terms', [TenantCounteredTermsController::class, 'store'])->name('add-counter-terms');
        //  Edit function URl Hire Tenant Agent Auction
        // Route::any('/edit-counter-terms/{id}', [TenantCounteredTermsController::class, 'edit'])->name('edit-counter-terms');
        Route::any('/update-counter-terms/{id}', [TenantCounteredTermsController::class, 'update'])->name('update-counter-terms');
    });


    Route::get('/tenant/biding/auctions/list', [AgentController::class, 'tenant_list'])->name('tenant.biding.auctions.list');
    Route::get('/landlord/biding/auctions/list', [AgentController::class, 'landlord_list'])->name('landlord.biding.auctions.list');
    Route::get('/buyer/biding/auctions/list', [AgentController::class, 'buyer_list'])->name('buyer.biding.auctions.list');
    Route::get('/seller/biding/auctions/list', [AgentController::class, 'seller_list'])->name('seller.biding.auctions.list');


    // Only Agents can access these routes
    Route::middleware('agentAuth')->group(function () {
        Route::name('agent.')->group(function () {
            Route::get('/service/auction/add', [AgentServiceAuctionController::class, 'index'])->name('service.auction.add');
            Route::post('/service/auction/save', [AgentServiceAuctionController::class, 'store'])->name('service.auction.save');
            Route::get('/agent/service/auctions', [AgentServiceAuctionController::class, 'list'])->name('service.auctions');
            Route::get('/service/auction/edit/{id}', [AgentServiceAuctionController::class, 'edit'])->name('service.auction.edit');
            Route::post('/service/auction/update', [AgentServiceAuctionController::class, 'update'])->name('service.auction.update');
            //
            Route::get('/agent/service/auction/bid/add/{id}', [AgentServiceAuctionBidController::class, 'add_bid'])->name('service.auction.bid.add');
            Route::post('/agent/service/auction/bid/save/{id}', [AgentServiceAuctionBidController::class, 'save_bid'])->name('service.auction.bid.save');
            Route::post('/agent/service/auction/bid/accept', [AgentServiceAuctionBidController::class, 'acceptASBid'])->name('acceptASBid');
            Route::post('/seller/service/auction/bid/save', [SellerServiceAuctionBidController::class, 'saveSSBid'])->name('saveSSBid');
            Route::get('/seller/service/auction/video/remove/{id}', [AgentServiceAuctionBidController::class, 'removeVideo'])->name('service.auction.video.remove');
            Route::get("/landlord/auction/add", [LandlordAuctionController::class, 'index'])->name('landlord.auction.add');
            Route::post("/landlord/auction/store", [LandlordAuctionController::class, 'store'])->name('landlord.auction.store');
            Route::get("/landlord/auctions", [LandlordAuctionController::class, 'list'])->name('landlord.auctions');
            Route::get("/landlord/auction/edit/{id}", [LandlordAuctionController::class, 'edit'])->name('landlord.auction.edit');
            Route::post("/landlord/auction/update/{id}", [LandlordAuctionController::class, 'update'])->name('landlord.auction.update');
            Route::post("/landlord/auction/bid/accept/{id}", [LandlordAuctionController::class, 'accept_bid'])->name('landlord.auction.bid.accept');
            Route::post('/landlord/auction/bid/reject/{id}', [LandlordAuctionController::class, 'reject_bid'])->name('landlord.auction.bid.reject');

            // Route::get("/buyer/agent/auction/bid/{id}", [BuyerAgentAuctionBidController::class, 'add_bid'])->name('buyer.agent.auction.bid');

            // Route::get("/tenant/agent/auction/bid/{id}", [TenantAgentAuctionBidController::class, 'add_bid'])->name('tenant.agent.auction.bid');


            Route::get('tenant/agent/auction/bid/{auctionId}', TenantAgentAuctionBid::class)->name('tenant.agent.auction.bid');
            Route::get('buyer/agent/auction/bid/{auctionId}', BuyerAgentAuctionBid::class)->name('buyer.agent.auction.bid');
            Route::get('buyer/agent/auction/bid/{bid_id}/view-counter', [BuyerAgentAuctionBidController::class, 'view_counter_terms'])->name('buyer.agent.auction.bid.view-counter');
            Route::get('buyer/hire/agent/auction/counter-bid/{id}/{bid_id}', [BuyerAgentAuctionBidController::class, 'add_counter_bid'])->name('buyer.hire.agent.auction.counter-bid');
            Route::get('landlord/agent/auction/bid/{auctionId}', LandlordAgentAuctionBid::class)->name('landlord.agent.auction.bid');
            
            // Default Bid Profiles
            Route::get('/agent/default-profiles', [\App\Http\Controllers\AgentDefaultProfileController::class, 'index'])->name('default-profiles.index');
            Route::post('/agent/default-profiles', [\App\Http\Controllers\AgentDefaultProfileController::class, 'store'])->name('default-profiles.store');
            Route::delete('/agent/default-profiles/{id}', [\App\Http\Controllers\AgentDefaultProfileController::class, 'destroy'])->name('default-profiles.destroy');
            Route::get('/agent/default-profiles/load', [\App\Http\Controllers\AgentDefaultProfileController::class, 'load'])->name('default-profiles.load');

            Route::get('tenant/agent/auction/{auctionId}/competing-bids', [\App\Http\Controllers\CompetingBidsController::class, 'viewCompetingBids'])->name('tenant.agent.auction.competing-bids');
            Route::get('tenant/agent/auction/{auctionId}/competing-bids/data', [\App\Http\Controllers\CompetingBidsController::class, 'getCompetingBidsData'])->name('tenant.agent.auction.competing-bids.data');

            // Route::post('/tenant/agent/auction/bid/store', [TenantAgentAuctionBidController::class, 'save_bid'])->name('tenant.agent.auction.bid.save');


            Route::get('/tenant/criteria/auction/add', [TenantCriteriaAuctionController::class, 'index'])->name('tenant.criteria.auction.add');
            Route::post('/tenant/criteria/auction/add', [TenantCriteriaAuctionController::class, 'store']);
            Route::get('/tenant/criteria/auction/edit/{id}', [TenantCriteriaAuctionController::class, 'edit'])->name('tenant.criteria.auction.edit');
            Route::post('/tenant/criteria/auction/edit/{id}', [TenantCriteriaAuctionController::class, 'update']);
            Route::get('/tenant/criteria/auctions', [TenantCriteriaAuctionController::class, 'list'])->name('tenant.criteria.auctions.list');
            Route::post('tenant/criteria/auction/accept/bid', [TenantCriteriaAuctionBidController::class, 'accept_bid'])->name('tenant.criteria.auction.bid.accept');
            Route::get('qr/settings', [DashboardController::class, 'qrSettings'])->name('qr.settings');
            Route::post('qr/settings', [DashboardController::class, 'update_qr']);

            // Counter Terms
            Route::any('/counter-terms/{id}', [AgentCounteredTermsController::class, 'add'])->name('counter-terms');
            Route::any('/add-counter-terms', [AgentCounteredTermsController::class, 'store'])->name('add-counter-terms');
            Route::any('/edit-counter-terms/{id}', [AgentCounteredTermsController::class, 'edit'])->name('edit-counter-terms');
            Route::any('/update-counter-terms/{id}', [AgentCounteredTermsController::class, 'update'])->name('update-counter-terms');



            // Route::get('/referral/auction/add', [ReferralAuctionController::class, 'index'])->name('referral.auction.add');
        });
        // Counter Bid Routes
        Route::post('hire/agent/seller/bid/accept', [SellerAgentAuctionController::class, 'acceptSABid'])->name('acceptSABid');
        Route::post('hire/agent/seller/bid/reject', [SellerAgentAuctionController::class, 'rejectSABid'])->name('rejectSABid');
        Route::post('hire/agent/seller/destroy/counter/{id}', [SellerCounterBidController::class, 'destroyCounter'])->name('destroySellerCounter');

        // Route::post('/landlord/agent/auction/counter/bid/{bid_id}', [LandlordAgentAuctionBidController::class, 'saveCounterBid'])->name('landlord.agent.save.counter-bid');

        // Route::get('/landlord/auction/counter/bid/{bid_id}', [LandlordAuctionController::class, 'addCounterBid'])->name('landlord.add.counter-bid');
        Route::post('/landlord/auction/counter/bid/{bid_id}', [LandlordAuctionController::class, 'saveCounterBid'])->name('landlord.save.counter-bid');

        Route::get('tenant/criteria/auction/counter/bid/{bid_id}', [TenantCriteriaAuctionController::class, 'addCounterBid'])->name('tenant.criteria.add.counter-bid');
        Route::post('tenant/criteria/auction/counter/bid/{bid_id}', [TenantCriteriaAuctionController::class, 'saveCounterBid'])->name('tenant.criteria.save.counter-bid');

        Route::get('buyer-criteria/auction/counter/bid/{bid_id}', [BuyerCriteriaAuctionController::class, 'addCounterBid'])->name('buyer-criteria.add.counter-bid');

        // Counter Bid Routes
        // Seller's agent routes
        Route::get('/add-listing', [PropertyAuctionController::class, 'addListing'])->name('add-listing');
        Route::post('/add-listing', [PropertyAuctionController::class, 'store']);
        Route::get('/edit-seller-property-listing/{id}', [PropertyAuctionController::class, 'edit'])->name('edit-seller-property-listing');
        Route::post('/edit-seller-property-listing/{id}', [PropertyAuctionController::class, 'update'])->name('update-seller-property-listing');

        Route::post('property/auction/bids-visibility/{id}/{vis}', [PropertyAuctionController::class, 'bidsVisibility'])->name('property.bids.visibility');
        Route::post('landlord/auction/bids-visibility/{id}/{vis}', [LandlordAuctionController::class, 'bidsVisibility'])->name('landlord.auction.bids.visibility');
        Route::post('tenant/criteria/bids-visibility/{id}/{vis}', [TenantCriteriaAuctionController::class, 'bidsVisibility'])->name('tenant.criteria.bids.visibility');
        Route::post('criteria/auction/bids-visibility/{id}/{vis}', [BuyerCriteriaAuctionController::class, 'bidsVisibility'])->name('criteria.auction.bids.visibility');
        Route::post('landlord/agent/auction/bids-visibility/{id}/{vis}', [LandlordAgentAuctionController::class, 'bidsVisibility'])->name('landlord.agent.auction.bids.visibility');

        Route::post('/property/listing/step/1', [PropertyAuctionController::class, 'step1'])->name('save-pl-step1');
        Route::post('/property/listing/step/2', [PropertyAuctionController::class, 'step2'])->name('save-pl-step2');
        Route::post('/property/listing/step/3', [PropertyAuctionController::class, 'step3'])->name('save-pl-step3');
        Route::post('/property/listing/step/4', [PropertyAuctionController::class, 'step4'])->name('save-pl-step4');
        Route::post('/property/listing/step/5', [PropertyAuctionController::class, 'step5'])->name('save-pl-step5');
        Route::get('/agent/seller/bid/add/{auctionId}', SellerAgentAuctionBid::class)->name('add_seller_agent_bid');

        // Landlord's Agent routes
        Route::get('/agent/landlord/bid/add/{id}', [LandlordAgentAuctionBidController::class, 'add_bid'])->name('landlord.agent.auction.bid.add');
        Route::post('/agent/landlord/bid/save', [LandlordAgentAuctionBidController::class, 'save_bid'])->name('landlord.agent.auction.bid.save');

        // Buyer's Agent routes
        Route::prefix('buyer-agent')->name('buyer_agent.')->group(function () {
            Route::any('/bid/ba/store', [BuyerAgentAuctionBidController::class, 'saveBABid'])->name('saveBABid');
            Route::get('/auction/add', [BuyerCriteriaAuctionController::class, 'addAuction'])->name('auction.add');
            Route::post('/auction/add', [BuyerCriteriaAuctionController::class, 'storeAuction']);
            Route::get('/auction/edit/{id}', [BuyerCriteriaAuctionController::class, 'edit'])->name('auction.edit');
            Route::post('/auction/update', [BuyerCriteriaAuctionController::class, 'updateAuction'])->name('auction.update');
        });

    });


    // Only Admin can access these routes
    Route::prefix('admin')->middleware('adminAuth')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('buyer', [BuyerController::class, 'buyer'])->name('buyer');
        Route::post('buyer', [BuyerController::class, 'store']);
        Route::post('buyer/update', [BuyerController::class, 'update'])->name('buyer.update');
        Route::get('buyer/delete/{id}', [BuyerController::class, 'destroy'])->name('buyer.delete');
        // Route::get('buyer/agent', [AdminController::class, 'buyerAgent'])->name('buyerAgent');
        Route::get('seller', [SellerController::class, 'seller'])->name('seller');
        Route::post('seller', [SellerController::class, 'store']);
        Route::post('seller/update', [SellerController::class, 'update'])->name('seller.update');
        Route::get('seller/delete/{id}', [SellerController::class, 'destroy'])->name('seller.delete');
        // Route::get('seller/agent', [AdminController::class, 'sellerAgent'])->name('sellerAgent');
        Route::get('agent', [AdminAgentController::class, 'Agent'])->name('agent');
        Route::post('agent', [AdminAgentController::class, 'store']);
        Route::post('agent/update', [AdminAgentController::class, 'update'])->name('agent.update');
        Route::get('agent/delete/{id}', [AdminAgentController::class, 'destroy'])->name('agent.delete');
        //
        Route::get('user/request', [AdminController::class, 'userRequest'])->name('userRequest');
        Route::get('user/request/approve/{id}', [AdminController::class, 'userRequestApprove'])->name('user.approve');
        Route::get('add/admin', [AdminController::class, 'addAdmin'])->name('addAdmin');
        Route::post('add/admin', [AdminController::class, 'addAdmin']);
        Route::post('update/admin', [AdminController::class, 'updateAdmin'])->name('updateAdmin');
        Route::get('delete/admin/{id}', [AdminController::class, 'deleteUser'])->name('user.delete');
        Route::get('active/admin/{id}', [AdminController::class, 'activeUser'])->name('user.active');
        Route::get('inactive/admin/{id}', [AdminController::class, 'inactiveUser'])->name('user.inactive');
        Route::get('property/auctions', [AdminController::class, 'propertyAuctions'])->name('propertyAuctions');
        Route::get('property/auction/approve/{id}', [AdminController::class, 'approvePropertyAuction'])->name('approvePropertyAuction');
        Route::get('criteria/auctions', [AdminController::class, 'criteriaAuctions'])->name('criteriaAuctions');
        Route::get('criteria/auction/approve/{id}', [AdminController::class, 'approveCriteriaAuction'])->name('criteria.auction.approve');

        Route::get('tenant/criteria/auctions', [TenantCriteriaAuctionController::class, 'admin_list'])->name('tenant.criteria.list');
        Route::get('tenant/criteria/auction/approve/{id}', [TenantCriteriaAuctionController::class, 'approve'])->name('tenant.criteria.auction.approve');

        Route::get('service/auctions', [AdminController::class, 'serviceAuctions'])->name('serviceAuctions');
        Route::get('service/auction/approve/{id}', [AdminController::class, 'serviceAuctionApprove'])->name('service.auction.approve');
        Route::get('seller/service/auctions', [AdminController::class, 'sellerServiceAuctions'])->name('sellerServiceAuctions');
        Route::get('seller/service/auction/approve/{id}', [AdminController::class, 'sellerServiceAuctionApprove'])->name('seller.service.auction.approve');
        Route::get('seller/agent/auctions', [SellerAgentAuctionController::class, 'sellerAgentAuctions'])->name('sellerAgentAuctions');
        Route::get('seller/agent/auction/approve/{id}', [SellerAgentAuctionController::class, 'approveSellerAgentAuction'])->name('approveSellerAgentAuction');

        Route::get('landlord/agent/auctions', [LandlordAgentAuctionController::class, 'admin_list'])->name('landlord.agent.auctions');
        Route::get('landlord/agent/auction/approve/{id}', [LandlordAgentAuctionController::class, 'approve'])->name('landlord.agent.auction.approve');

        Route::get('tenant/agent/auctions', [TenantAgentAuctionController::class, 'admin_list'])->name('tenant.agent.auctions');
        Route::get('tenant/agent/auction/approve/{id}', [TenantAgentAuctionController::class, 'approve'])->name('tenant.agent.auction.approve');

        Route::get('buyer/agent/auctions', [BuyerAgentAuctionController::class, 'buyerAgentAuctionsAdmin'])->name('buyerAgentAuctions');
        Route::get('buyer/agent/auction/approve/{id}', [BuyerAgentAuctionController::class, 'approveBuyerAgentAuction'])->name('approveBuyerAgentAuction');
        Route::get('offer/listings', [AdminController::class, 'offerListings'])->name('offerListings');
        Route::post('offer/listing/approve/{id}', [AdminController::class, 'approveOfferListing'])->name('offerListing.approve');
        Route::post('offer/listing/reject/{id}', [AdminController::class, 'rejectOfferListing'])->name('offerListing.reject');

        Route::get('settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('settings', [SettingController::class, 'store']);
        Route::resource('commonBotQuestions', CommonBotQuestionController::class);
        Route::resource('cities', CityController::class);
        Route::resource('counties', CountyController::class);
        Route::resource('agentServices', AgentServiceController::class);
        Route::resource('sellerServices', SellerServiceController::class);
        Route::resource('financings', FinancingController::class);
        Route::resource('appliances', ApplianceController::class);
        Route::resource('propertyTypes', PropertyTypeController::class);
        Route::resource('waterViewTypes', WaterViewTypeController::class);
        Route::resource('waterExtras', WaterExtraController::class);
        Route::resource('heatingFuels', HeatingFuelController::class);
        Route::resource('feeIncludes', FeeIncludeController::class);
        Route::resource('airConditioningTypes', ACTypeController::class);

        Route::get('landlord/auctions', [LandlordAuctionController::class, 'admin_list'])->name('landlord.auctions');
        Route::get('landlord/auction/approve/{id}', [LandlordAuctionController::class, 'approve'])->name('landlord.auction.approve');

        // Phase 9 — Referral tracking
        Route::get('referrals', [AdminController::class, 'referrals'])->name('referrals.index');
        Route::post('referrals/{summary}/status', [AdminController::class, 'updateReferralStatus'])->name('referrals.status');
    });
});

Route::get('logout', function () {
    Auth::logout();
    return redirect()->to(route('login'));
})->middleware('auth');

// AI Chat Routes
Route::get('/chat/{id}', [ChatController::class, 'index'])->name('chat');
Route::post('/chat_gpt', [ChatController::class, 'askToChatGpt_integrate_view']);
Route::post('/chat_gpt_reply', [ChatController::class, 'askToChatGpt_integrate']);

// AI Knowledge Base — public read endpoint (no auth required)
Route::get('/ai-knowledge/{token}', [\App\Http\Controllers\AiKnowledgeController::class, 'show'])
    ->name('ai.knowledge.show')
    ->where('token', '[A-Za-z0-9]+');

// AI Knowledge Base — token management (auth required)
Route::middleware('auth')->group(function () {
    Route::post('/ai-knowledge/token/generate', [\App\Http\Controllers\AiKnowledgeController::class, 'generateToken'])
        ->name('ai.knowledge.generate');
    Route::post('/ai-knowledge/token/regenerate', [\App\Http\Controllers\AiKnowledgeController::class, 'regenerateToken'])
        ->name('ai.knowledge.regenerate');
});

require __DIR__ . '/auth.php';


// New_Routes
Route::get('/test', function () {

    $propertyAuctions = PropertyAuction::where('auto_bid', 1)
        ->where('sold', 0)
        ->whereNull('sold_date')
        ->get();
    foreach ($propertyAuctions as $propertyAuction) {

        $propertyauctions_data = collect($propertyAuction->bids)->where('auto_bid_record', '!=', 1)->all();
        $maxBid = collect($propertyauctions_data)->where('price', collect($propertyauctions_data)->pluck('price')->max())->first();
        $maxBidPrice = $maxBid->price;
        // dd($maxBidPrice);
        $seller_price = $propertyAuction->autobid_price;  // minimum
        $seller_price2 = $propertyAuction->autobid_price2; //reserve
        $seller_price3 = $propertyAuction->autobid_price3; //buy now
        $price = null; // Initialize $price to null or any default value as needed
        $totalBoots = User::where('user_type', 'bot')->count();
        // Condition if bot is there then bid happend else not
        if ($totalBoots > 0) {
            // Generate a random number between 1 and the total number of boots
            $randomNumber = rand(1, $totalBoots);
            // Fetch the user with the boot user_type at the random offset
            $randomBoot = User::where('user_type', 'bot')->skip($randomNumber - 1)->take(1)->first();
            // Use the $randomBoot object to access the user's properties
            $randomBootId = $randomBoot->id;
            if ($seller_price > $maxBidPrice) {
                $price = $seller_price;
            } elseif ($seller_price2 > $maxBidPrice) {
                $price = $seller_price2;
            } elseif ($seller_price3 > $maxBidPrice) {
                $price = $seller_price3;
            }
            if ($price) {
                $new_propertyAuction_bid = new PropertyAuctionBid();
                $new_propertyAuction_bid->property_auction_id = $maxBid->property_auction_id;
                $new_propertyAuction_bid->user_id = $randomBootId;
                $new_propertyAuction_bid->price = $price;
                $new_propertyAuction_bid->autobid_maximum_price = "null";
                $new_propertyAuction_bid->auto_bid_record = 0;
                $new_propertyAuction_bid->save();
                // Changes 31 May 2023
                $new_propertyAuction_bid->saveMeta('price', $price);
                $new_propertyAuction_bid->saveMeta('financing', 'Cash');
                $new_propertyAuction_bid->saveMeta('custom_term_financings', "null");
                $new_propertyAuction_bid->saveMeta('escrow_amount', "0");
                $new_propertyAuction_bid->saveMeta('inspection_period', Carbon::now());
                $new_propertyAuction_bid->saveMeta('closing_date', Carbon::now());
                $new_propertyAuction_bid->saveMeta('contingencies', "Home Inspection");
                $new_propertyAuction_bid->saveMeta('custom_contingencies', null);
                $new_propertyAuction_bid->saveMeta('seller_premium', null);
                $new_propertyAuction_bid->saveMeta('buyer_premium', "Buyer");
                $new_propertyAuction_bid->saveMeta('buyer_type', "");
                $new_propertyAuction_bid->saveMeta('video_url', "https://www.koqy.mobi");
            }
        }
    }
})->name('test');

// TEMP DEV ONLY - auto-login by user ID for screenshot testing
if (app()->environment('local', 'development')) {
    Route::get('/dev-login/{id}', function ($id) {
        Auth::loginUsingId($id);
        return redirect('/dashboard');
    })->name('dev.login');

}

// ===========================================================================
// Regular Listing (Offer) — production routes
// These route to the isolated OfferListing Livewire components (Layer 2 copies).
// ===========================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/offer-listing/seller',
        \App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class
    )->name('offer.listing.seller');

    Route::get('/offer-listing/buyer',
        \App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing::class
    )->name('offer.listing.buyer');

    Route::get('/offer-listing/landlord',
        \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing::class
    )->name('offer.listing.landlord');

    // ── Offer Listing View routes (production) ─────────────────────────────
    Route::get('/offer-listing/seller/view/{id}',
        [SellerOfferListingController::class, 'view']
    )->name('offer.listing.seller.view');
    // ── End Offer Listing View routes ──────────────────────────────────────

    // ── Offer Listing Edit routes (production) ─────────────────────────────
    // IMPORTANT: specific /edit/ routes must be declared BEFORE any catch-all
    // {user_type?} routes to prevent Laravel matching 'edit' as a route param.
    Route::get('/offer-listing/seller/edit/{auctionId}',
        \App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit::class
    )->name('offer.listing.seller.edit');

    Route::get('/offer-listing/landlord/edit/{auctionId}',
        \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit::class
    )->name('offer.listing.landlord.edit');

    Route::get('/offer-listing/buyer/edit/{auctionId}',
        \App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit::class
    )->name('offer.listing.buyer.edit');

    Route::get('/offer-listing/tenant/edit/{auctionId}',
        \App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit::class
    )->name('offer.listing.tenant.edit');
    // ── End Offer Listing Edit routes ──────────────────────────────────────

    // Tenant create — catch-all AFTER specific /edit/ route
    Route::get('/offer-listing/tenant/{user_type?}',
        \App\Http\Livewire\OfferListing\Tenant\TenantOfferListing::class
    )->name('offer.listing.tenant');
});
// ===========================================================================
// END Regular Listing (Offer) production routes
// ===========================================================================

// ===========================================================================
// LAYER 2 DEV-ONLY — OfferListing duplication test routes
// These routes are DEVELOPMENT-ONLY. Do NOT use in production.
// Purpose: smoke-test the duplicated OfferListing Livewire components.
// Remove or gate behind feature flag before any production deployment.
// ===========================================================================
if (app()->environment('local', 'development')) {
    Route::middleware(['auth'])->group(function () {
        // Tenant Offer Listing (create)
        Route::get('/dev/offer-listing/tenant/{user_type?}',
            \App\Http\Livewire\OfferListing\Tenant\TenantOfferListing::class
        )->name('dev.offer-listing.tenant');

        // Tenant Offer Listing (edit)
        Route::get('/dev/offer-listing/tenant/edit/{auctionId}/{user_type}',
            \App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit::class
        )->name('dev.offer-listing.tenant.edit');

        // Buyer Offer Listing (create)
        Route::get('/dev/offer-listing/buyer',
            \App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing::class
        )->name('dev.offer-listing.buyer');

        // Buyer Offer Listing (edit)
        Route::get('/dev/offer-listing/buyer/edit',
            \App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit::class
        )->name('dev.offer-listing.buyer.edit');

        // Seller Offer Listing (create)
        Route::get('/dev/offer-listing/seller',
            \App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class
        )->name('dev.offer-listing.seller');

        // Seller Offer Listing (edit)
        Route::get('/dev/offer-listing/seller/edit',
            \App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit::class
        )->name('dev.offer-listing.seller.edit');

        // Landlord Offer Listing (create)
        Route::get('/dev/offer-listing/landlord',
            \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing::class
        )->name('dev.offer-listing.landlord');

        // Landlord Offer Listing (edit)
        Route::get('/dev/offer-listing/landlord/edit',
            \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit::class
        )->name('dev.offer-listing.landlord.edit');
    });
}
// ===========================================================================
// END LAYER 2 DEV-ONLY routes
// ===========================================================================
