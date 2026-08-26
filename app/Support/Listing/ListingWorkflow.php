<?php

namespace App\Support\Listing;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use Illuminate\Support\Facades\Schema;

/**
 * The two products that share the four `*_agent_auctions` tables, and the vocabulary
 * for telling them apart.
 *
 * WHY THIS EXISTS
 * ---------------
 * `seller_agent_auctions` (and its three siblings) hold rows from BOTH "Hire an Agent"
 * and "Create Offer Listing". Until now the only thing separating them was an optional
 * `workflow_type` row in the matching `*_metas` table — written solely by
 * `saveAllMetadata()`, i.e. only when a wizard saved. Any row created by another path
 * carried no product identity at all, so "which product is this?" had no reliable
 * answer, and every draft picker answered "whichever asked".
 *
 * This class is the vocabulary only. The *decision* lives in
 * {@see \App\Services\Listing\ListingWorkflowResolver}, and the enforcement in
 * {@see ListingResumeGuard}. Keeping the three apart is deliberate: a component that
 * needs a constant must not be able to reach a second opinion about what a row is.
 *
 * NATIVE COLUMN IS THE DURABLE SSOT; EAV IS TRANSITIONAL.
 * The `workflow_type` column added by the 2026_08_27 migrations is the intended
 * long-term answer. The EAV meta key of the same name stays readable — and stays
 * written — for as long as historical rows and unported readers depend on it. New
 * writes set BOTH, consistently, via {@see self::stamp()}. Nothing in this codebase
 * may write one without the other.
 */
final class ListingWorkflow
{
    /** Hire an Agent — the client is looking to hire representation. */
    public const HIRE_AGENT = 'hire_agent';

    /** Create Offer Listing — the client is listing a property or criteria for offers. */
    public const OFFER_LISTING = 'offer_listing';

    /** @var string[] */
    public const ALL = [self::HIRE_AGENT, self::OFFER_LISTING];

    /** @var string[] */
    public const ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    /** The native column. The durable source of truth. */
    public const COLUMN = 'workflow_type';

    /** The legacy EAV key. Same name, deliberately — one concept, two storage eras. */
    public const META_KEY = 'workflow_type';

    /**
     * Meta key proving a row came from MLS Quick Import.
     *
     * Deterministic evidence of OFFER_LISTING: the quick-import writer is reachable
     * only from the Create Offer Listing flow and resolves a model for `seller` and
     * `landlord` only. No Hire path writes it.
     *
     * @see \App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter::META_QUICK_IMPORT
     */
    public const META_QUICK_IMPORT = 'mls_quick_import';

    /**
     * Meta key proving a row came from a Hire wizard.
     *
     * Deterministic evidence of HIRE_AGENT. Verified exhaustively at the time of
     * writing: `saveMeta('service_type', …)` appears in all eight Hire components and
     * in HireAgentDirectController, and in NONE of the eight Offer Listing components —
     * Offer Listing has no service-type concept whatsoever. That absence is what makes
     * the key's *presence* decisive, and it is also the direct cause of the NULL
     * `service_type` fall-through this change defends against separately.
     */
    public const META_SERVICE_TYPE = 'service_type';

    /** @var array<string,bool>|null Memoised per-request answer to "does the column exist yet?" */
    private static ?array $columnMemo = null;

    public static function isValid(?string $workflow): bool
    {
        return $workflow !== null && in_array($workflow, self::ALL, true);
    }

    public static function isValidRole(?string $role): bool
    {
        return $role !== null && in_array($role, self::ROLES, true);
    }

    /**
     * The model class backing a role, or null for anything unrecognised.
     *
     * A match with an explicit null default rather than an array lookup, so an
     * unrecognised role cannot resolve to a model by accident.
     *
     * @return class-string|null
     */
    public static function modelClassForRole(?string $role): ?string
    {
        return match ($role) {
            'seller'   => SellerAgentAuction::class,
            'buyer'    => BuyerAgentAuction::class,
            'landlord' => LandlordAgentAuction::class,
            'tenant'   => TenantAgentAuction::class,
            default    => null,
        };
    }

    /**
     * The role a model class serves, or null when it is not one of the four.
     */
    public static function roleForModelClass(?string $modelClass): ?string
    {
        if ($modelClass === null) {
            return null;
        }

        foreach (self::ROLES as $role) {
            if (self::modelClassForRole($role) === $modelClass) {
                return $role;
            }
        }

        return null;
    }

    /** @return array<string,class-string> role => model class */
    public static function roleModels(): array
    {
        $out = [];

        foreach (self::ROLES as $role) {
            $out[$role] = self::modelClassForRole($role);
        }

        return $out;
    }

    /**
     * Does this model's table carry the native column yet?
     *
     * Asked rather than assumed because the column arrives in a migration, and every
     * reader here must keep working against a schema where it has not run — a fresh
     * test database, a rollback, or the window between deploy steps. When the column
     * is absent the resolver simply has one fewer source of evidence; it does not
     * throw, and it does not report every row as unclassified.
     *
     * @param  class-string  $modelClass
     */
    public static function columnAvailable(string $modelClass): bool
    {
        if (self::$columnMemo === null) {
            self::$columnMemo = [];
        }

        if (array_key_exists($modelClass, self::$columnMemo)) {
            return self::$columnMemo[$modelClass];
        }

        try {
            $model  = new $modelClass();
            $answer = Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), self::COLUMN);
        } catch (\Throwable $e) {
            // No usable connection (or no table). Treat as absent rather than fatal:
            // a resolver that throws here would take down every listing screen.
            $answer = false;
        }

        return self::$columnMemo[$modelClass] = $answer;
    }

    /**
     * Drop the schema memo.
     *
     * Tests migrate between cases within one process, so a memo taken before the
     * column existed would outlive its own truth.
     */
    public static function forgetSchemaMemo(): void
    {
        self::$columnMemo = null;
    }

    /**
     * Write an unambiguous workflow identity onto a row — native column AND legacy EAV.
     *
     * BOTH, ALWAYS, IN ONE CALL. That is the whole point of routing every writer
     * through here. A path that set only the column would be invisible to unported
     * readers still consulting meta; a path that set only the meta would leave the
     * durable SSOT null and the row looking unclassified forever. Worse, either
     * half-write creates exactly the native/EAV disagreement the resolver is required
     * to fail closed on — so a partial stamp does not degrade gracefully, it
     * manufactures a conflict.
     *
     * @param  object  $auction  one of the four auction models
     * @throws \InvalidArgumentException on an unrecognised workflow
     */
    public static function stamp(object $auction, string $workflow): void
    {
        if (! self::isValid($workflow)) {
            throw new \InvalidArgumentException(
                "Refusing to stamp listing with unrecognised workflow [{$workflow}]."
            );
        }

        if (self::columnAvailable(get_class($auction))) {
            // Written directly rather than via save() on the whole model: callers stamp
            // mid-flow, and persisting unrelated dirty attributes as a side effect of
            // recording identity would be a surprise.
            $auction->setAttribute(self::COLUMN, $workflow);

            if ($auction->exists) {
                $auction->newQuery()
                    ->whereKey($auction->getKey())
                    ->update([self::COLUMN => $workflow]);

                $auction->syncOriginalAttribute(self::COLUMN);
            }
        }

        if (method_exists($auction, 'saveMeta')) {
            $auction->saveMeta(self::META_KEY, $workflow);

            // The model caches its `meta` relation, and `info()`/`get` read that cache.
            // Without this, code that stamps and then immediately resolves would read
            // the pre-stamp meta set and conclude the row is still unclassified.
            if ($auction->relationLoaded('meta')) {
                $auction->unsetRelation('meta');
            }
        }
    }
}
