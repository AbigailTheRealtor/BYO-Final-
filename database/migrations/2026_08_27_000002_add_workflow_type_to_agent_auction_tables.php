<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of 2 — add the product discriminator as a real column, NULLABLE.
 *
 * The four `*_agent_auctions` tables each hold rows from BOTH products: Hire an Agent
 * and Create Offer Listing. Until now the only thing separating them was an optional
 * `workflow_type` row in the matching `*_metas` table, written solely by the wizards'
 * `saveAllMetadata()` — so any row created by another path (MLS Quick Import, most
 * notably) had no product identity at all, and every draft picker defaulted to
 * "whichever screen asked".
 *
 * NULLABLE HERE, DELIBERATELY.
 * Enforcing NOT NULL in the same operation that introduces the column would require
 * every historical row to be classifiable at the instant of deploy, and some are not.
 * The sequence is: add (this file) → backfill what can be proven (step 2) → inventory
 * the remainder read-only (`php artisan listings:workflow-inventory`) → and only then,
 * separately and on evidence of a zero remainder, a third migration to enforce. That
 * third migration is deliberately NOT part of this change.
 *
 * Nothing at runtime depends on the column being NOT NULL: ListingWorkflowResolver
 * treats an absent value as one fewer signal, and ListingResumeGuard refuses an
 * unclassified row for BOTH products rather than guessing.
 *
 * The composite index is `(user_id, is_draft, workflow_type)` because that is the exact
 * shape every draft-picker query takes after this change.
 *
 * @see \App\Support\Listing\ListingWorkflow
 * @see \App\Services\Listing\ListingWorkflowResolver
 */
return new class extends Migration
{
    private const TABLES = [
        'seller_agent_auctions',
        'buyer_agent_auctions',
        'landlord_agent_auctions',
        'tenant_agent_auctions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'workflow_type')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('workflow_type', 20)->nullable();
            });

            // Indexed separately from the column add so that a table missing `is_draft`
            // or `user_id` still gets the column rather than failing the whole step.
            if (Schema::hasColumn($table, 'user_id') && Schema::hasColumn($table, 'is_draft')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->index(['user_id', 'is_draft', 'workflow_type'], $this->indexName($table));
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'workflow_type')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                try {
                    $t->dropIndex($this->indexName($table));
                } catch (\Throwable $e) {
                    // Index may never have been created (see the guard in up()).
                }

                $t->dropColumn('workflow_type');
            });
        }
    }

    /**
     * Explicit, short index names.
     *
     * Laravel's generated name would be `landlord_agent_auctions_user_id_is_draft_workflow_type_index`
     * (58 chars) — under PostgreSQL's 63-byte identifier cap, but only just, and silently
     * truncated identifiers are a miserable thing to debug. Naming them explicitly keeps
     * every one of the four well clear and makes down() able to find them.
     */
    private function indexName(string $table): string
    {
        $prefix = substr(str_replace('_agent_auctions', '', $table), 0, 12);

        return $prefix . '_aa_user_draft_workflow_idx';
    }
};
