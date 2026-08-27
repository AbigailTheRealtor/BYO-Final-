<?php

namespace Tests\Feature\Listing\Concerns;

use App\Models\User;
use App\Support\Listing\ListingWorkflow;

/**
 * Fixture builders for rows in the four shared `*_agent_auctions` tables.
 *
 * The tables are shared by both products, which is the whole subject of these tests, so
 * every builder here takes the workflow explicitly and there is no default. A helper that
 * quietly picked one would be able to hide the bug it is meant to catch.
 *
 * `makeUnstamped()` deliberately bypasses ListingWorkflow::stamp() and writes no identity
 * at all — that is the shape MlsQuickImportDraftWriter used to produce, and the shape
 * every legacy row has.
 */
trait MakesWorkflowListings
{
    protected function makeUser(): User
    {
        return User::factory()->create();
    }

    /**
     * A listing row for $role, stamped as $workflow.
     *
     * @param  array<string,mixed>  $meta       extra EAV rows
     * @param  array<string,mixed>  $columns    extra native columns
     */
    protected function makeListing(
        string $role,
        string $workflow,
        int $userId,
        bool $isDraft = true,
        array $meta = [],
        array $columns = []
    ) {
        $auction = $this->makeUnstamped($role, $userId, $isDraft, $meta, $columns);

        ListingWorkflow::stamp($auction, $workflow);

        return $auction->fresh();
    }

    /**
     * A listing row carrying NO workflow identity — neither column nor meta.
     *
     * @param  array<string,mixed>  $meta
     * @param  array<string,mixed>  $columns
     */
    protected function makeUnstamped(
        string $role,
        int $userId,
        bool $isDraft = true,
        array $meta = [],
        array $columns = []
    ) {
        $modelClass = ListingWorkflow::modelClassForRole($role);

        if ($modelClass === null) {
            throw new \InvalidArgumentException("Unknown role [{$role}]");
        }

        $auction = new $modelClass();
        $auction->user_id  = $userId;
        $auction->is_draft = $isDraft;

        // Seller and Buyer carry NOT NULL native columns that Landlord and Tenant keep in
        // meta — the schema asymmetry documented in CLAUDE.md. Set only where present.
        foreach (['address' => 'Fixture Address', 'title' => 'Fixture Title'] as $col => $value) {
            if ($this->tableHasColumn($auction, $col)) {
                $auction->{$col} = $value;
            }
        }

        foreach ($columns as $col => $value) {
            $auction->{$col} = $value;
        }

        $auction->save();

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return $auction->fresh();
    }

    /**
     * An MLS Quick Import draft as the writer used to leave it: quick-import provenance,
     * no workflow identity. The "seller draft 123" shape.
     */
    protected function makeLegacyQuickImportDraft(string $role, int $userId)
    {
        return $this->makeUnstamped($role, $userId, true, [
            'mls_quick_import'  => '1',
            'mls_listing_key'   => 'TB' . $userId . $role,
            'mls_number'        => 'TB8528949',
            'property_photos'   => json_encode([]),
        ]);
    }

    /** A legacy Hire row: service_type present, no workflow stamp. */
    protected function makeLegacyHireDraft(string $role, int $userId)
    {
        return $this->makeUnstamped($role, $userId, true, [
            'service_type' => 'full_service',
        ]);
    }

    private function tableHasColumn(object $model, string $column): bool
    {
        return \Illuminate\Support\Facades\Schema::connection($model->getConnectionName())
            ->hasColumn($model->getTable(), $column);
    }
}
