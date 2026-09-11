<?php

namespace Tests\Feature\Security;

use App\Models\AuctionChat;
use App\Models\AuctionChatToken;
use App\Models\AuctionChatUser;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The migration history must build the schema the messaging system uses.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * No migration created auction_chat_tokens, auction_chat_users, auction_chats,
 * bot_questions or common_bot_questions — they lived only in the pre-migration
 * byo2.sql dump — so every install built from this repository had none of them and
 * every messaging route failed on its first query. ModelTableExistenceTest cannot see
 * that class of gap (it reads only models that declare `protected $table`), so this
 * file asserts the outcome directly: the tables, the columns the code reads and
 * writes, the keys and indexes it relies on.
 *
 * It sits beside MessagingThreadAuthorizationTest because these tables ARE that
 * boundary's data: participant membership is an auction_chat_users row.
 *
 * THE UPGRADE CASE
 * ----------------
 * An older installation may already hold these tables, made by hand, with rows in
 * them. The migration must leave those tables — shape and data — exactly as they are,
 * and the application must still serve them. That is reproduced below with the
 * legacy byo2.sql shapes, inside this test's transaction.
 */
class MessagingSchemaMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = 'database/migrations/2026_09_11_000001_create_messaging_tables.php';

    /** The five tables the supported messaging code reads and writes. */
    private const COLUMNS = [
        'auction_chat_tokens'  => ['id', 'auction_type', 'auction_id', 'token', 'last_message', 'created_at', 'updated_at'],
        'auction_chat_users'   => ['id', 'auction_chat_token_id', 'user_id', 'created_at', 'updated_at'],
        'auction_chats'        => ['id', 'auction_chat_token_id', 'user_id', 'message', 'message_type', 'answer', 'is_bot', 'created_at', 'updated_at'],
        'bot_questions'        => ['id', 'auction_type', 'auction_id', 'user_id', 'question', 'answer', 'created_at', 'updated_at'],
        'common_bot_questions' => ['id', 'question', 'answer'],
    ];

    private const LEGACY_TOKEN = '641b1a487f269';

    private function migration()
    {
        return require base_path(self::MIGRATION);
    }

    private function schemaManager()
    {
        return Schema::getConnection()->getDoctrineSchemaManager();
    }

    // =====================================================================
    // Fresh schema
    // =====================================================================

    /** @test */
    public function the_five_messaging_tables_exist_after_migrating(): void
    {
        foreach (array_keys(self::COLUMNS) as $table) {
            $this->assertTrue(Schema::hasTable($table), "`{$table}` does not exist after migrating.");
        }
    }

    /** @test */
    public function each_table_has_the_columns_the_code_reads_and_writes(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->assertEqualsCanonicalizing(
                $columns,
                Schema::getColumnListing($table),
                "`{$table}` does not have exactly the columns the messaging code uses."
            );
        }
    }

    /** @test */
    public function the_token_is_unique_and_holds_both_the_legacy_and_the_current_shape(): void
    {
        $indexes = $this->schemaManager()->listTableIndexes('auction_chat_tokens');
        $tokenIndex = collect($indexes)->first(fn ($index) => $index->getColumns() === ['token']);
        $this->assertNotNull($tokenIndex, 'Every thread lookup is by token; it must be indexed.');
        $this->assertTrue($tokenIndex->isUnique(), 'A token must identify exactly one thread.');

        $current = Str::random(40);
        foreach ([self::LEGACY_TOKEN, $current] as $i => $token) {
            DB::table('auction_chat_tokens')->insert([
                'auction_type' => 'seller-agent', 'auction_id' => 900 + $i, 'token' => $token,
                'last_message' => 'New Chat', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->assertSame($token, DB::table('auction_chat_tokens')->where('token', $token)->value('token'));
        }

        $this->expectException(QueryException::class);
        DB::table('auction_chat_tokens')->insert([
            'auction_type' => 'seller-agent', 'auction_id' => 999, 'token' => $current, 'last_message' => 'dup',
        ]);
    }

    /** @test */
    public function the_listing_morph_is_indexed_on_both_tables_that_carry_it(): void
    {
        foreach (['auction_chat_tokens', 'bot_questions'] as $table) {
            $morph = collect($this->schemaManager()->listTableIndexes($table))
                ->first(fn ($index) => $index->getColumns() === ['auction_type', 'auction_id']);
            $this->assertNotNull($morph, "`{$table}` has no index on the auction morph its relations query.");
        }
    }

    /** @test */
    public function the_foreign_keys_point_at_the_tables_that_actually_exist(): void
    {
        $expected = [
            'auction_chat_users' => ['auction_chat_token_id' => 'auction_chat_tokens', 'user_id' => 'users'],
            'auction_chats'      => ['auction_chat_token_id' => 'auction_chat_tokens', 'user_id' => 'users'],
            'bot_questions'      => ['user_id' => 'users'],
        ];

        foreach ($expected as $table => $keys) {
            $actual = [];
            foreach ($this->schemaManager()->listTableForeignKeys($table) as $fk) {
                $actual[$fk->getLocalColumns()[0]] = $fk->getForeignTableName();
                $this->assertSame(['id'], $fk->getForeignColumns(), "`{$table}` foreign key must reference an id");
            }
            ksort($actual);
            ksort($keys);
            $this->assertSame($keys, $actual, "`{$table}` foreign keys");
        }
    }

    /** @test */
    public function deleting_a_thread_removes_its_participants_and_messages(): void
    {
        $user  = User::factory()->create();
        $token = $this->thread($user, Str::random(40));
        $this->message($token, $user, 'hello');

        $token->delete();

        $this->assertSame(0, AuctionChatUser::where('auction_chat_token_id', $token->id)->count());
        $this->assertSame(0, AuctionChat::where('auction_chat_token_id', $token->id)->count());
    }

    /** @test */
    public function is_bot_behaves_the_way_the_code_writes_and_reads_it(): void
    {
        $user  = User::factory()->create();
        $token = $this->thread($user, Str::random(40));
        $this->message($token, $user, 'a person wrote this', 0);
        $this->message($token, $user, 'the bot answered this', 1);

        // The thread view filters the collection loosely; the bot path queries.
        $this->assertSame(['a person wrote this'], $token->fresh()->chats->where('is_bot', 0)->pluck('message')->all());
        $this->assertSame(1, AuctionChat::where('auction_chat_token_id', $token->id)->where('is_bot', 1)->count());
    }

    /** @test */
    public function the_two_unused_models_do_not_get_tables(): void
    {
        // Nothing reads or writes either; see the migration's docblock.
        $this->assertFalse(Schema::hasTable('auction_chat_unreads'));
        $this->assertFalse(Schema::hasTable('unanswered_bot_questions'));
    }

    // =====================================================================
    // Re-running, rolling back, and upgrading an installation that has data
    // =====================================================================

    /** @test */
    public function running_the_migration_again_changes_nothing(): void
    {
        $before = $this->columnListings();

        $this->migration()->up();

        $this->assertSame($before, $this->columnListings());
    }

    /** @test */
    public function rolling_back_never_drops_a_messaging_table(): void
    {
        $this->migration()->down();

        foreach (array_keys(self::COLUMNS) as $table) {
            $this->assertTrue(Schema::hasTable($table), "down() dropped `{$table}`; it must not delete message history.");
        }
    }

    /** @test */
    public function an_installation_that_already_holds_the_legacy_tables_keeps_them_and_their_rows(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller', 'first_name' => 'Legacyowner']);
        $agent = User::factory()->asAgent()->create();
        $listing = SellerAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'Legacy Listing', 'address' => '12 Legacy Row',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        $this->replaceWithLegacyTables();
        $this->seedLegacyRows($listing->id, $owner->id, $agent->id);

        $columnsBefore = $this->columnListings();
        $rowsBefore    = $this->allRows();

        $this->migration()->up();

        $this->assertSame($columnsBefore, $this->columnListings(), 'An existing table must not be altered.');
        $this->assertEquals($rowsBefore, $this->allRows(), 'Existing rows must survive unchanged.');

        // And the application still serves the legacy thread — to its participant only.
        $this->actingAs($agent)
            ->getJson(route('load_chat_messages', self::LEGACY_TOKEN))
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertSee('Legacy message from the agent');

        $this->actingAs(User::factory()->create())
            ->getJson(route('load_chat_messages', self::LEGACY_TOKEN))
            ->assertForbidden();
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    private function thread(User $user, string $tokenValue): AuctionChatToken
    {
        $token = new AuctionChatToken();
        $token->auction_type = 'seller-agent';
        $token->auction_id   = 1;
        $token->token        = $tokenValue;
        $token->last_message = 'New Chat';
        $token->save();

        $member = new AuctionChatUser();
        $member->auction_chat_token_id = $token->id;
        $member->user_id               = $user->id;
        $member->save();

        return $token;
    }

    private function message(AuctionChatToken $token, User $author, string $body, int $isBot = 0): void
    {
        $chat = new AuctionChat();
        $chat->auction_chat_token_id = $token->id;
        $chat->user_id               = $author->id;
        $chat->message               = $body;
        $chat->message_type          = 'text';
        $chat->is_bot                = $isBot;
        $chat->save();
    }

    /** @return array<string, array<int, string>> */
    private function columnListings(): array
    {
        $out = [];
        foreach (array_keys(self::COLUMNS) as $table) {
            $out[$table] = Schema::getColumnListing($table);
        }

        return $out;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function allRows(): array
    {
        $out = [];
        foreach (array_keys(self::COLUMNS) as $table) {
            $out[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $out;
    }

    /**
     * The byo2.sql shapes: integer ids, a varchar is_bot, a 50-character auction_type,
     * a NOT NULL last_message, a varchar(255) common question, and no foreign keys.
     */
    private function replaceWithLegacyTables(): void
    {
        foreach (['auction_chats', 'auction_chat_users', 'bot_questions', 'common_bot_questions', 'auction_chat_tokens'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('auction_chat_tokens', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('auction_id');
            $t->string('auction_type', 50);
            $t->string('token', 255);
            $t->text('last_message');
            $t->timestamps();
        });
        Schema::create('auction_chat_users', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('auction_chat_token_id');
            $t->integer('user_id');
            $t->timestamps();
        });
        Schema::create('auction_chats', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('auction_chat_token_id');
            $t->text('message')->nullable();
            $t->string('message_type', 250)->nullable();
            $t->text('answer')->nullable();
            $t->string('is_bot', 250)->nullable();
            $t->timestamps();
        });
        Schema::create('bot_questions', function (Blueprint $t) {
            $t->increments('id');
            $t->string('auction_type', 255);
            $t->integer('auction_id');
            $t->integer('user_id');
            $t->text('question');
            $t->text('answer')->nullable();
            $t->timestamps();
        });
        Schema::create('common_bot_questions', function (Blueprint $t) {
            $t->increments('id');
            $t->string('question', 255);
            $t->text('answer');
        });
    }

    private function seedLegacyRows(int $listingId, int $ownerId, int $agentId): void
    {
        $at = '2023-03-22 10:10:00';

        $tokenId = DB::table('auction_chat_tokens')->insertGetId([
            'auction_id' => $listingId, 'auction_type' => 'seller-agent', 'token' => self::LEGACY_TOKEN,
            'last_message' => 'Legacy message from the agent', 'created_at' => $at, 'updated_at' => $at,
        ]);
        DB::table('auction_chat_users')->insert([
            ['auction_chat_token_id' => $tokenId, 'user_id' => $agentId, 'created_at' => $at, 'updated_at' => $at],
            ['auction_chat_token_id' => $tokenId, 'user_id' => $ownerId, 'created_at' => $at, 'updated_at' => $at],
        ]);
        DB::table('auction_chats')->insert([
            ['user_id' => $agentId, 'auction_chat_token_id' => $tokenId, 'message' => 'Legacy message from the agent',
             'message_type' => 'text', 'answer' => null, 'is_bot' => '0', 'created_at' => $at, 'updated_at' => $at],
            ['user_id' => $ownerId, 'auction_chat_token_id' => $tokenId, 'message' => 'How many bedrooms?',
             'message_type' => 'text', 'answer' => 'Three', 'is_bot' => '1', 'created_at' => $at, 'updated_at' => $at],
        ]);
        DB::table('bot_questions')->insert([
            'auction_type' => 'seller-agent', 'auction_id' => $listingId, 'user_id' => $ownerId,
            'question' => 'Is there a pool?', 'answer' => 'Yes', 'created_at' => $at, 'updated_at' => $at,
        ]);
        DB::table('common_bot_questions')->insert(['question' => 'What is BidYourOffer?', 'answer' => 'A marketplace.']);
    }
}
