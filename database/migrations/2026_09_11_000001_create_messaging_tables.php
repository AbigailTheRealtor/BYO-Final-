<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the five tables the listing messaging system reads and writes, none of
 * which any Laravel migration has ever created.
 *
 * WHY THEY ARE MISSING
 * --------------------
 * Like `agent_counter_terms`, these existed only as hand-made tables in the
 * pre-migration era; their definitions survive solely in `database/byo2.sql`, the
 * September 2024 dump. They were never ported, and `database/schema/pgsql-schema.dump`
 * does not contain them either, so every install built from this repository — SQLite,
 * PostgreSQL from the dump, PostgreSQL from the full chain — has none of them, and
 * every messaging route fails on its first query. ModelTableExistenceTest did not
 * notice: it reads only models that declare `protected $table`, and none of these do.
 *
 * WHAT IS CREATED, AND FROM WHAT
 * ------------------------------
 * Columns come from what the code reads and writes — AuctionChatController,
 * BotQuestionController, CommonBotQuestionController, the models' relations and the
 * messages views — checked against byo2.sql:
 *
 *   auction_chat_tokens   one conversation, attached to a listing through the
 *                         `auction` morph (morphMany 'auction' on nine listing models)
 *   auction_chat_users    its participants — the membership PR #156 authorises on
 *   auction_chats         its messages; `is_bot` separates bot answers from the thread
 *   bot_questions         a listing owner's own Q&A for the bot, on the same morph
 *   common_bot_questions  the admin-maintained Q&A; its model has no timestamps
 *
 * `token` is a unique string(255). Legacy tokens are 13-character uniqid() values and
 * new ones are Str::random(40); both fit, and every thread lookup is by token, so it
 * must identify exactly one thread. `is_bot` is a boolean (byo2.sql had a varchar):
 * the code writes 0 or 1 and compares loosely.
 *
 * DELIBERATELY NOT CREATED
 * ------------------------
 * `auction_chat_unreads` and `unanswered_bot_questions`. Both have models, but nothing
 * reads or writes either: AuctionChatUnread is only imported, its stub controller is
 * not routed, and the unanswered_bot_questions() relations are never called. A table
 * for dead code would make it look supported.
 *
 * No unique (auction_chat_token_id, user_id): AuctionChatController::new() adds the
 * listing owner and the caller without checking they differ, so an owner who opens a
 * chat on their own listing writes the same pair twice. A unique index would turn
 * that into an error — a behaviour change, not a schema repair.
 *
 * IDEMPOTENT, AND ADDITIVE ONLY. Each create is guarded by Schema::hasTable(). An
 * installation that already holds any of these tables keeps it exactly as it is, rows
 * and columns: nothing here alters, backfills or rewrites an existing table.
 *
 * down() leaves the tables in place. This migration cannot tell a table it created
 * from one an older installation already held message history in, and a rollback must
 * not delete conversations. Re-running up() afterwards is a no-op.
 */
return new class extends Migration
{
    public function up()
    {
        // Parent first: participants and messages take a foreign key against it.
        if (! Schema::hasTable('auction_chat_tokens')) {
            Schema::create('auction_chat_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('auction');
                $table->string('token')->unique();
                $table->text('last_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('auction_chat_users')) {
            Schema::create('auction_chat_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('auction_chat_token_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->timestamps();
                $table->foreign('auction_chat_token_id')
                    ->references('id')->on('auction_chat_tokens')->onDelete('cascade');
                $table->foreign('user_id')
                    ->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('auction_chats')) {
            Schema::create('auction_chats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('auction_chat_token_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->text('message')->nullable();
                $table->string('message_type')->nullable();
                $table->text('answer')->nullable();
                $table->boolean('is_bot')->default(false);
                $table->timestamps();
                $table->foreign('auction_chat_token_id')
                    ->references('id')->on('auction_chat_tokens')->onDelete('cascade');
                $table->foreign('user_id')
                    ->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('bot_questions')) {
            Schema::create('bot_questions', function (Blueprint $table) {
                $table->id();
                $table->morphs('auction');
                $table->unsignedBigInteger('user_id')->index();
                $table->text('question');
                $table->text('answer')->nullable();
                $table->timestamps();
                $table->foreign('user_id')
                    ->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('common_bot_questions')) {
            Schema::create('common_bot_questions', function (Blueprint $table) {
                $table->id();
                $table->text('question');
                $table->text('answer');
            });
        }
    }

    public function down()
    {
        // Intentionally empty — see the class docblock.
    }
};
