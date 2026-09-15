<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuctionChat;
use App\Models\AuctionChatToken;
use App\Models\AuctionChatUser;
use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Conversation membership for the Hire Agent messaging endpoints.
 *
 * THE DEFECT. A conversation is an `auction_chat_tokens` row and its participants are
 * the `auction_chat_users` rows pointing at it. sendMessage() checked that the caller
 * was one of them; load_chat_messages() and chat_bot_reply() did not. They looked the
 * token up and went straight to work, so any signed-in account holding a token —
 * `uniqid()`, a hex timestamp — could read the thread, and could make the bot write a
 * reply into it under the listing owner's user id. chat_bot_reply was also a GET, so
 * that write sat outside CSRF protection entirely.
 *
 * WHAT IS ASSERTED. Membership is the only thing that opens a thread: not the token,
 * not an agent account, not the verified middleware. Refusals are checked for what
 * they did NOT do — no message body, participant name or listing label in the
 * response, no row written — because "a 403 came back" says nothing about whether
 * the damage happened first.
 *
 * THE SCHEMA. No migration creates the three chat tables yet; that work is held
 * until this boundary exists, because creating them is what would make these
 * endpoints live. The tables are therefore created here, inside the
 * DatabaseTransactions wrapper, so they are rolled back after every test. Guarded
 * with hasTable() so the file keeps working unchanged once the migration lands.
 */
class MessagingThreadAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET_MESSAGE = 'PRIVATE-THREAD-BODY-7f3a91';
    private const OWNER_REPLY    = 'OWNER-REPLY-BODY-c02e44';
    private const LISTING_LABEL  = 'Secret Listing Label 4410 Harbor Way';
    private const OWNER_NAME     = 'Oriana';
    private const AGENT_NAME     = 'Agatha';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createChatTablesIfMissing();
        // Outbound HTTP is already refused by the base TestCase; faking it as well lets
        // the bot tests assert nothing was even attempted.
        Http::fake();
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    private function createChatTablesIfMissing(): void
    {
        // Column shapes follow the legacy dump (database/byo2.sql).
        if (! Schema::hasTable('auction_chat_tokens')) {
            Schema::create('auction_chat_tokens', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('auction_id')->index();
                $t->string('auction_type', 50)->index();
                $t->string('token');
                $t->text('last_message');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('auction_chat_users')) {
            Schema::create('auction_chat_users', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('auction_chat_token_id')->index();
                $t->unsignedBigInteger('user_id')->index();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('auction_chats')) {
            Schema::create('auction_chats', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('auction_chat_token_id')->index();
                $t->text('message')->nullable();
                $t->string('message_type')->nullable();
                $t->text('answer')->nullable();
                $t->string('is_bot')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('bot_questions')) {
            Schema::create('bot_questions', function (Blueprint $t) {
                $t->id();
                $t->string('auction_type');
                $t->unsignedBigInteger('auction_id');
                $t->unsignedBigInteger('user_id');
                $t->text('question');
                $t->text('answer')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('common_bot_questions')) {
            Schema::create('common_bot_questions', function (Blueprint $t) {
                $t->id();
                $t->string('question');
                $t->text('answer');
            });
        }
    }

    /**
     * The four Hire Agent listing types, as stored in auction_chat_tokens.auction_type.
     *
     * @return array<string, array{0: string}>
     */
    public static function hireAgentTypes(): array
    {
        return [
            'seller hire agent'   => ['seller-agent'],
            'buyer hire agent'    => ['buyer-agent'],
            'landlord hire agent' => ['landlord-agent'],
            'tenant hire agent'   => ['tenant-agent'],
        ];
    }

    /** @return array<string, array{0: ?string}> */
    public static function productModes(): array
    {
        return [
            'combined'     => [null],
            'bidyouragent' => ['bidyouragent'],
        ];
    }

    private function consumer(string $userType = 'buyer', array $attributes = []): User
    {
        return User::factory()->create(['user_type' => $userType] + $attributes);
    }

    private function agent(array $attributes = []): User
    {
        return User::factory()->asAgent()->create($attributes);
    }

    private function listing(string $type, User $owner): Model
    {
        switch ($type) {
            case 'seller-agent':
                return SellerAgentAuction::forceCreate([
                    'user_id' => $owner->id, 'title' => self::LISTING_LABEL, 'address' => self::LISTING_LABEL,
                    'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
                ]);
            case 'buyer-agent':
                return BuyerAgentAuction::forceCreate([
                    'user_id' => $owner->id, 'title' => self::LISTING_LABEL, 'address' => self::LISTING_LABEL,
                    'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
                ]);
            case 'landlord-agent':
                return LandlordAgentAuction::forceCreate([
                    'user_id' => $owner->id, 'title' => self::LISTING_LABEL, 'is_draft' => false,
                ]);
            case 'tenant-agent':
                return TenantAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => self::LISTING_LABEL]);
        }

        throw new \InvalidArgumentException("Unknown Hire Agent type: {$type}");
    }

    /**
     * A thread between a listing owner and the agent who started it, with one message
     * from each — the shape AuctionChatController::new() produces.
     *
     * @return array{token: AuctionChatToken, owner: User, agent: User, listing: Model}
     */
    private function thread(string $type = 'seller-agent', ?string $tokenValue = null): array
    {
        // users_user_type_check has no 'landlord'; landlords hold seller accounts.
        $ownerType = ['seller-agent' => 'seller', 'buyer-agent' => 'buyer', 'landlord-agent' => 'seller', 'tenant-agent' => 'tenant'][$type];
        $owner     = $this->consumer($ownerType, ['first_name' => self::OWNER_NAME]);
        $agent   = $this->agent(['first_name' => self::AGENT_NAME]);
        $listing = $this->listing($type, $owner);

        $token = new AuctionChatToken();
        $token->auction_id   = $listing->id;
        $token->auction_type = $type;
        $token->token        = $tokenValue ?? ('tok' . bin2hex(random_bytes(8)));
        $token->last_message = self::OWNER_REPLY;
        $token->save();

        foreach ([$agent, $owner] as $participant) {
            $member = new AuctionChatUser();
            $member->auction_chat_token_id = $token->id;
            $member->user_id               = $participant->id;
            $member->save();
        }

        $this->message($token, $agent, self::SECRET_MESSAGE);
        $this->message($token, $owner, self::OWNER_REPLY);

        return ['token' => $token, 'owner' => $owner, 'agent' => $agent, 'listing' => $listing];
    }

    private function message(AuctionChatToken $token, User $author, string $body): void
    {
        $chat = new AuctionChat();
        $chat->auction_chat_token_id = $token->id;
        $chat->user_id               = $author->id;
        $chat->message               = $body;
        $chat->message_type          = 'text';
        $chat->is_bot                = 0;
        $chat->save();
    }

    private function useProduct(?string $product): void
    {
        config($product === null
            ? ['products.active' => null, 'products.hosts' => []]
            : ['products.active' => $product]);
    }

    /** Nothing a thread holds may appear in a refusal. */
    private function assertExposesNothing($response): void
    {
        $body = $response->getContent();

        foreach ([self::SECRET_MESSAGE, self::OWNER_REPLY, self::LISTING_LABEL, self::OWNER_NAME, self::AGENT_NAME] as $private) {
            $this->assertStringNotContainsString($private, $body, "Refusal leaked thread content: {$private}");
        }
    }

    private function chatRowCount(AuctionChatToken $token): int
    {
        return AuctionChat::where('auction_chat_token_id', $token->id)->count();
    }

    // =====================================================================
    // load_chat_messages — read
    // =====================================================================

    /** @dataProvider hireAgentTypes */
    public function test_outsider_cannot_read_a_thread(string $type): void
    {
        ['token' => $token] = $this->thread($type);

        $response = $this->actingAs($this->consumer())
            ->getJson(route('load_chat_messages', $token->token));

        $response->assertForbidden()->assertJson(['success' => false]);
        $this->assertExposesNothing($response);
    }

    /** @dataProvider hireAgentTypes */
    public function test_participating_consumer_can_read_the_thread(string $type): void
    {
        ['token' => $token, 'owner' => $owner] = $this->thread($type);

        $response = $this->actingAs($owner)->getJson(route('load_chat_messages', $token->token));

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString(self::SECRET_MESSAGE, $response->json('data'));
        $this->assertStringContainsString(self::OWNER_REPLY, $response->json('data'));
    }

    /** @dataProvider hireAgentTypes */
    public function test_participating_agent_can_read_the_thread(string $type): void
    {
        ['token' => $token, 'agent' => $agent] = $this->thread($type);

        $response = $this->actingAs($agent)->getJson(route('load_chat_messages', $token->token));

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString(self::SECRET_MESSAGE, $response->json('data'));
        // The agent's own message renders as sent, the owner's as a reply.
        $this->assertStringContainsString('class="sent"', $response->json('data'));
        $this->assertStringContainsString('class="replies"', $response->json('data'));
    }

    public function test_being_an_agent_does_not_open_someone_elses_thread(): void
    {
        ['token' => $token] = $this->thread('seller-agent');

        $response = $this->actingAs($this->agent())->getJson(route('load_chat_messages', $token->token));

        $response->assertForbidden();
        $this->assertExposesNothing($response);
    }

    public function test_owning_the_listing_does_not_open_a_thread_one_is_not_in(): void
    {
        // A second thread on the same listing, between the owner and agent B. Agent A,
        // a participant of the first thread on this very listing, is not in it.
        ['token' => $first, 'owner' => $owner, 'agent' => $agentA, 'listing' => $listing] = $this->thread('seller-agent');

        $agentB = $this->agent();
        $second = new AuctionChatToken();
        $second->auction_id   = $listing->id;
        $second->auction_type = 'seller-agent';
        $second->token        = 'second' . bin2hex(random_bytes(8));
        $second->last_message = 'New Chat';
        $second->save();
        foreach ([$agentB, $owner] as $participant) {
            $member = new AuctionChatUser();
            $member->auction_chat_token_id = $second->id;
            $member->user_id               = $participant->id;
            $member->save();
        }

        $this->actingAs($agentA)->getJson(route('load_chat_messages', $second->token))->assertForbidden();
        $this->actingAs($agentA)->getJson(route('load_chat_messages', $first->token))->assertOk();
    }

    public function test_an_unknown_token_is_refused_exactly_like_a_foreign_one(): void
    {
        ['token' => $token] = $this->thread('seller-agent');
        $outsider = $this->consumer();

        $foreign = $this->actingAs($outsider)->getJson(route('load_chat_messages', $token->token));
        $unknown = $this->actingAs($outsider)->getJson(route('load_chat_messages', 'no-such-token'));

        // Otherwise the endpoint answers "does this token exist?" for anyone who asks.
        $foreign->assertForbidden();
        $unknown->assertForbidden();
        $this->assertSame($foreign->getContent(), $unknown->getContent());
    }

    public function test_unverified_outsider_cannot_read_a_thread(): void
    {
        ['token' => $token] = $this->thread('seller-agent');

        $response = $this->actingAs(User::factory()->unverified()->create())
            ->getJson(route('load_chat_messages', $token->token));

        $response->assertForbidden();
        $this->assertExposesNothing($response);
    }

    public function test_guest_is_sent_to_login_from_every_thread_endpoint(): void
    {
        ['token' => $token] = $this->thread('seller-agent');
        $before = $this->chatRowCount($token);

        $this->get(route('load_chat_messages', $token->token))->assertRedirect(route('login'));
        $this->post(route('chat_bot_reply', $token->token), ['message' => 'hi'])->assertRedirect(route('login'));
        $this->post(route('send-chat-message'), ['token' => $token->token, 'message' => 'hi'])->assertRedirect(route('login'));

        $this->assertSame($before, $this->chatRowCount($token));
    }

    /** @dataProvider productModes */
    public function test_membership_is_enforced_in_each_product(?string $product): void
    {
        $this->useProduct($product);
        ['token' => $token, 'agent' => $agent] = $this->thread('seller-agent');
        $before = $this->chatRowCount($token);

        $this->actingAs($agent)->getJson(route('load_chat_messages', $token->token))->assertOk();

        $outsider = $this->consumer();
        $read = $this->actingAs($outsider)->getJson(route('load_chat_messages', $token->token));
        $read->assertForbidden();
        $this->assertExposesNothing($read);

        $this->actingAs($outsider)->postJson(route('chat_bot_reply', $token->token), ['message' => 'hi'])->assertForbidden();
        $this->assertSame($before, $this->chatRowCount($token));
    }

    // =====================================================================
    // send-chat-message — already protected; pinned so it stays that way
    // =====================================================================

    public function test_outsider_cannot_send_into_a_thread(): void
    {
        ['token' => $token] = $this->thread('seller-agent');
        $before = $this->chatRowCount($token);

        $this->actingAs($this->agent())
            ->postJson(route('send-chat-message'), ['token' => $token->token, 'message' => 'injected'])
            ->assertForbidden();

        $this->assertSame($before, $this->chatRowCount($token));
        $this->assertSame(self::OWNER_REPLY, $token->fresh()->last_message);
    }

    public function test_participant_can_send_into_the_thread(): void
    {
        ['token' => $token, 'agent' => $agent] = $this->thread('tenant-agent');

        $this->actingAs($agent)
            ->postJson(route('send-chat-message'), ['token' => $token->token, 'message' => 'Following up'])
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['user_id' => $agent->id, 'message' => 'Following up']]);

        $this->assertSame('Following up', $token->fresh()->last_message);
    }

    public function test_send_to_an_unknown_token_is_refused_exactly_like_a_foreign_one(): void
    {
        ['token' => $token] = $this->thread('seller-agent');
        $outsider = $this->consumer();

        $foreign = $this->actingAs($outsider)->postJson(route('send-chat-message'), ['token' => $token->token, 'message' => 'x']);
        $unknown = $this->actingAs($outsider)->postJson(route('send-chat-message'), ['token' => 'no-such-token', 'message' => 'x']);

        $foreign->assertForbidden();
        $unknown->assertForbidden();
        $this->assertSame($foreign->getContent(), $unknown->getContent());
    }

    // =====================================================================
    // chat_bot_reply — writes a row, as the listing owner
    // =====================================================================

    /** @dataProvider hireAgentTypes */
    public function test_outsider_cannot_invoke_the_bot_on_a_thread(string $type): void
    {
        ['token' => $token, 'owner' => $owner] = $this->thread($type);
        $before = $this->chatRowCount($token);

        $response = $this->actingAs($this->consumer())
            ->postJson(route('chat_bot_reply', $token->token), ['message' => 'How many bedrooms?']);

        $response->assertForbidden();
        $this->assertExposesNothing($response);
        $this->assertSame($before, $this->chatRowCount($token), 'An outsider must not cause a write');
        $this->assertFalse(
            AuctionChat::where('auction_chat_token_id', $token->id)->where('is_bot', 1)->where('user_id', $owner->id)->exists(),
            'An outsider must not cause a message to be written as the listing owner'
        );
        Http::assertNothingSent();
    }

    public function test_participant_can_invoke_the_bot(): void
    {
        ['token' => $token, 'owner' => $owner, 'agent' => $agent] = $this->thread('seller-agent');

        $this->actingAs($agent)
            ->postJson(route('chat_bot_reply', $token->token), ['message' => 'hello there'])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'I can not understand this question']);

        // Existing behaviour, unchanged: the bot's reply is recorded under the listing owner.
        $reply = AuctionChat::where('auction_chat_token_id', $token->id)->where('is_bot', 1)->sole();
        $this->assertSame($owner->id, (int) $reply->user_id);
        $this->assertSame('hello there', $reply->message);
        Http::assertNothingSent();
    }

    public function test_bot_reply_can_no_longer_be_triggered_by_get(): void
    {
        ['token' => $token, 'agent' => $agent] = $this->thread('seller-agent');
        $before = $this->chatRowCount($token);

        // Handler::render() answers MethodNotAllowed as 404.
        $this->actingAs($agent)
            ->get(route('chat_bot_reply', $token->token) . '?message=hello')
            ->assertNotFound();

        $this->assertSame($before, $this->chatRowCount($token), 'A GET must not write');
    }

    public function test_bot_reply_is_csrf_protected(): void
    {
        // CSRF verification is skipped when running unit tests; restore it for this test.
        $this->app->instance(VerifyCsrfToken::class, new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        ['token' => $token, 'agent' => $agent] = $this->thread('seller-agent');
        $before = $this->chatRowCount($token);

        $this->actingAs($agent)
            ->post(route('chat_bot_reply', $token->token), ['message' => 'hello there'])
            ->assertStatus(419);
        $this->assertSame($before, $this->chatRowCount($token), 'A request without a CSRF token must not write');

        $this->actingAs($agent)
            ->withSession(['_token' => 'csrf-under-test'])
            ->post(route('chat_bot_reply', $token->token), ['_token' => 'csrf-under-test', 'message' => 'hello there'])
            ->assertOk();
        $this->assertSame($before + 1, $this->chatRowCount($token));
    }

    public function test_thread_routes_keep_their_methods_and_middleware(): void
    {
        $bot = Route::getRoutes()->getByName('chat_bot_reply');
        $this->assertSame(['POST'], $bot->methods());

        foreach (['chat_bot_reply', 'load_chat_messages', 'send-chat-message'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            foreach (['web', 'auth', 'verified', 'noAdmin'] as $required) {
                $this->assertContains($required, $middleware, "{$name} lost its {$required} middleware");
            }
        }
    }

    // =====================================================================
    // Tokens
    // =====================================================================

    public function test_a_legacy_uniqid_token_still_opens_the_thread_for_its_participant(): void
    {
        // A value of the shape every existing row holds (13 hex characters).
        ['token' => $token, 'owner' => $owner] = $this->thread('buyer-agent', '641b1a487f269');

        $this->actingAs($owner)->getJson(route('load_chat_messages', '641b1a487f269'))->assertOk();
        $this->actingAs($this->consumer())->getJson(route('load_chat_messages', '641b1a487f269'))->assertForbidden();
    }

    public function test_a_new_thread_gets_an_unpredictable_token(): void
    {
        $owner   = $this->consumer('seller');
        $listing = $this->listing('seller-agent', $owner);
        $agent   = $this->agent();

        $this->actingAs($agent)->get(route('auction-chat', ['seller-agent', $listing->id]))->assertRedirect();

        $token = AuctionChatToken::where('auction_type', 'seller-agent')->where('auction_id', $listing->id)->sole();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $token->token);
        $this->assertEqualsCanonicalizing(
            [$agent->id, $owner->id],
            $token->chat_users()->pluck('user_id')->map(fn ($id) => (int) $id)->all()
        );
    }
}
