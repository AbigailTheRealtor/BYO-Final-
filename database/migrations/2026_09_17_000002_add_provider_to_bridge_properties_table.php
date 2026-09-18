<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of 2 — add the provider dimension to `bridge_properties`.
 *
 * WHAT THIS IS FOR
 * ----------------
 * `listing_key` is the identifier the ISSUING MLS minted. It is unique within
 * that system and carries no guarantee across systems, so "which system said
 * this?" has to be stored beside it before anything can depend on the answer.
 * This column is that dimension. See {@see \App\Support\Listing\MlsProvider}.
 *
 * NULLABLE, AND NOTHING READS IT YET
 * ----------------------------------
 * The column ships nullable and unread. Step 2 (`…_000003`) populates it for
 * existing rows; the ingestion normalizer populates it for new ones. No lookup,
 * no filter and no uniqueness constraint consults it in this change, so no
 * behaviour moves.
 *
 * THE EXISTING `UNIQUE(listing_key)` IS DELIBERATELY UNTOUCHED
 * -----------------------------------------------------------
 * Making uniqueness composite — `(provider, listing_key)` — is the change that
 * actually prevents a cross-provider collision, and it is a SEPARATE, later
 * migration on purpose. It is the destructive half: it drops a constraint that
 * is currently protecting the table, and it can only be rolled back cleanly
 * while a single provider's rows exist. Shipping it apart from this one keeps
 * the additive half reviewable and revertible on its own.
 *
 * NO INDEX HERE, ON PURPOSE
 * -------------------------
 * An index on `(provider, listing_key)` would be read by nothing in this
 * change, and the later composite UNIQUE index covers exactly those columns —
 * so adding one now means creating an index in this migration and dropping it
 * in the next. The uniqueness migration creates what it needs, once.
 */
return new class extends Migration
{
    private const TABLE  = 'bridge_properties';
    private const COLUMN = 'provider';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            // 32 chars: these are short machine identifiers, never display names.
            $table->string(self::COLUMN, 32)->nullable();
        });
    }

    /**
     * Dropping the column IS the meaningful undo — it takes every value with
     * it, including the ones step 2 wrote and the ones ingestion has written
     * since. That is why step 2's own down() does not need to un-populate.
     */
    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn(self::COLUMN);
        });
    }
};
