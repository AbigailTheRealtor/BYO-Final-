<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Native MLS identity becomes `(provider, listing_key)`.
 *
 * WHAT CHANGES
 * ------------
 *   provider          nullable  →  NOT NULL
 *   UNIQUE(listing_key)         →  UNIQUE(provider, listing_key)
 *
 * WHY THE GLOBAL UNIQUE HAD TO GO
 * -------------------------------
 * `ListingKey` is minted by the issuing MLS and is unique WITHIN that system.
 * Nothing makes it unique across systems. `UNIQUE(listing_key)` therefore
 * encodes an assumption that only one MLS will ever exist: the day a second
 * provider sends a key Stellar has already used, the insert is either rejected
 * outright or — far worse, because the upsert matches on that column — silently
 * overwrites Stellar's row with another provider's property.
 *
 * WHY `provider` MUST BE NOT NULL, AND WHY THAT IS PART OF *THIS* MIGRATION
 * ------------------------------------------------------------------------
 * This is the half that is easy to skip and cannot be skipped. In PostgreSQL a
 * UNIQUE index treats NULLs as distinct from each other, so with a nullable
 * column `(NULL, '12345')` may repeat without limit — the composite constraint
 * would LOOK like it prevents duplicate keys while allowing exactly the
 * duplicates it exists to prevent. A uniqueness rule with a NULL bypass is not a
 * weaker guarantee, it is the appearance of one.
 *
 * So NOT NULL is not a tightening for tidiness; it is what makes the constraint
 * above mean anything at all, and shipping the index without it would be
 * shipping the reassurance rather than the protection.
 *
 * DELIBERATELY NO DATABASE DEFAULT
 * --------------------------------
 * A default of `'stellar_bridge'` would have made this migration land without
 * touching a single fixture — and would have re-created, one layer down, the
 * defect this whole change exists to remove: a future provider's writer that
 * forgets the column would have its rows silently labelled Stellar, and those
 * rows would then collide with real Stellar keys under the very constraint
 * meant to keep them apart. A writer must SAY which provider it is. There is no
 * safe value to guess on its behalf.
 *
 * VERIFY BEFORE CONSTRAINING, AND REFUSE RATHER THAN GUESS
 * -------------------------------------------------------
 * up() proves the two preconditions before it alters anything, and ABORTS with
 * an explanation if either fails:
 *
 *   1. no row has a NULL provider — `…_000003` backfills every pre-existing row
 *      and runs earlier in the same `migrate`, so a NULL here means something
 *      wrote a row without a provider in between. That is a fact worth stopping
 *      for, not one to paper over by inventing a value.
 *   2. no `(provider, listing_key)` pair is already duplicated — otherwise the
 *      index creation fails halfway through a deploy with a driver-level error
 *      that names an index rather than the problem.
 *
 * ORDER: the new constraint is created BEFORE the old one is dropped, so the
 * table is never momentarily unprotected.
 *
 * ROLLBACK IS HONEST ABOUT WHEN IT CANNOT WORK
 * --------------------------------------------
 * down() restores `UNIQUE(listing_key)`, which is only possible while one
 * provider's rows exist. Once a second provider has supplied a colliding key,
 * that constraint is unsatisfiable and the rollback REFUSES with the count
 * rather than failing obscurely or deleting somebody's rows. That is not a
 * defect in the rollback — it is the migration telling the truth about a
 * boundary the data has crossed.
 */
return new class extends Migration
{
    /**
     * `CREATE UNIQUE INDEX CONCURRENTLY` cannot run inside a transaction on
     * PostgreSQL. Same flag, same reason, as `2026_06_16_000002` and
     * `2026_07_05_000001`.
     */
    public $withinTransaction = false;

    private const TABLE       = 'bridge_properties';
    private const COLUMN      = 'provider';
    private const KEY         = 'listing_key';
    private const INDEX       = 'bridge_properties_provider_listing_key_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        $this->assertEveryRowHasAProvider();
        $this->assertNoCompositeDuplicates();

        $this->setProviderNotNull();
        $this->createCompositeUnique();
        $this->dropLegacyGlobalUnique();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        $this->assertLegacyGlobalUniqueIsStillSatisfiable();

        $this->restoreLegacyGlobalUnique();
        $this->dropCompositeUnique();
        $this->setProviderNullable();
    }

    // ── preconditions ────────────────────────────────────────────────────────

    private function assertEveryRowHasAProvider(): void
    {
        $missing = DB::table(self::TABLE)->whereNull(self::COLUMN)->count();

        if ($missing === 0) {
            return;
        }

        throw new RuntimeException(
            "Refusing to make {$this->qualified()} NOT NULL: {$missing} row(s) have no provider. "
            . 'Migration 2026_09_17_000003 backfills every pre-existing row and runs before this one, '
            . 'so these rows were written without a provider afterwards. Identify which MLS issued them '
            . 'and set it deliberately — this migration will not guess, because a wrong guess would '
            . 'label another provider\'s rows as Stellar and then collide them under the new constraint.'
        );
    }

    private function assertNoCompositeDuplicates(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->select(self::COLUMN, self::KEY)
            ->whereNotNull(self::KEY)
            ->groupBy(self::COLUMN, self::KEY)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Refusing to create ' . self::INDEX . ': ' . $duplicates->count()
            . ' (provider, listing_key) pair(s) are already duplicated. Resolve them before migrating; '
            . 'creating the index would otherwise fail part-way through a deploy with a driver error '
            . 'that names an index rather than the rows.'
        );
    }

    /**
     * The legacy constraint is one row per listing_key REGARDLESS of provider.
     * Two providers legitimately sharing a key is exactly what this migration
     * made possible, and it is what makes the old rule unsatisfiable.
     */
    private function assertLegacyGlobalUniqueIsStillSatisfiable(): void
    {
        $collisions = DB::table(self::TABLE)
            ->select(self::KEY)
            ->whereNotNull(self::KEY)
            ->groupBy(self::KEY)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($collisions->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Refusing to restore UNIQUE(listing_key): ' . $collisions->count()
            . ' listing key(s) are now held by more than one provider. That is the state this migration '
            . 'exists to permit, and the old constraint cannot express it. Restoring it would require '
            . 'deleting one provider\'s rows. Resolve deliberately if this rollback is really intended.'
        );
    }

    // ── schema operations, driver-aware ──────────────────────────────────────

    private function setProviderNotNull(): void
    {
        if ($this->isPostgres()) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' ALTER COLUMN ' . self::COLUMN . ' SET NOT NULL');

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string(self::COLUMN, 32)->nullable(false)->change();
        });
    }

    private function setProviderNullable(): void
    {
        if ($this->isPostgres()) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' ALTER COLUMN ' . self::COLUMN . ' DROP NOT NULL');

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string(self::COLUMN, 32)->nullable()->change();
        });
    }

    private function createCompositeUnique(): void
    {
        if ($this->isPostgres()) {
            DB::statement(
                'CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS ' . self::INDEX
                . ' ON ' . self::TABLE . ' (' . self::COLUMN . ', ' . self::KEY . ')'
            );

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique([self::COLUMN, self::KEY], self::INDEX);
        });
    }

    private function dropCompositeUnique(): void
    {
        if ($this->isPostgres()) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
    }

    /**
     * Dropped and restored through the Blueprint's COLUMN form so the grammar
     * computes the name it originally generated. On PostgreSQL `->unique()`
     * created a table CONSTRAINT and on SQLite an INDEX; the grammar knows
     * which, and hard-coding either spelling would break the other driver.
     */
    private function dropLegacyGlobalUnique(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique([self::KEY]);
        });
    }

    private function restoreLegacyGlobalUnique(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique([self::KEY]);
        });
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }

    private function qualified(): string
    {
        return self::TABLE . '.' . self::COLUMN;
    }
};
