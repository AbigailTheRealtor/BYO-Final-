<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeLocationDna;
use App\Models\AcceptedBidSummary;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AgentLocationDnaController
 *
 * Handles the Generate/Refresh Location DNA action from agent offer listing pages.
 *
 * Listing type contract:
 *   'seller_agent'   → SellerAgentAuction (seller_agent_auctions table)
 *   'landlord_agent' → LandlordAgentAuction (landlord_agent_auctions table)
 *
 * These types map to the same listing records displayed by the Livewire offer-listing
 * pages, so authorization checks and dispatched job IDs are always consistent with the
 * page that triggered the request. The LocationDnaPipelineRunner resolves addresses
 * from the same models via dedicated 'seller_agent'/'landlord_agent' branches.
 *
 * Buyer and Tenant listing types are explicitly rejected with 404.
 */
class AgentLocationDnaController extends Controller
{
    private const ALLOWED_TYPES = ['seller_agent', 'landlord_agent'];

    public function generate(string $listingType, int $listingId)
    {
        if (! in_array($listingType, self::ALLOWED_TYPES, true)) {
            abort(404);
        }

        if ($listingType === 'seller_agent') {
            return $this->generateForSellerAgent($listingId);
        }

        return $this->generateForLandlordAgent($listingId);
    }

    private function isAuthorized(string $listingType, int $listingId, int $ownerId): bool
    {
        $userId = Auth::id();

        if ($userId === $ownerId) {
            return true;
        }

        return AcceptedBidSummary::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('agent_user_id', $userId)
            ->exists();
    }

    private function generateForSellerAgent(int $listingId)
    {
        $listing = SellerAgentAuction::find($listingId);

        if (! $listing) {
            abort(404);
        }

        if (! $this->isAuthorized('seller_agent', $listingId, (int) $listing->user_id)) {
            abort(403);
        }

        $address = $listing->info('address');
        $city    = $listing->info('property_city');
        $state   = $listing->info('property_state');

        if (empty($address) || empty($city) || empty($state)) {
            return back()->withErrors(['address' => 'Complete the listing address (street, city, and state) before generating Location DNA.']);
        }

        return $this->dispatchAndRespond('seller_agent', $listingId);
    }

    private function generateForLandlordAgent(int $listingId)
    {
        $listing = LandlordAgentAuction::find($listingId);

        if (! $listing) {
            abort(404);
        }

        if (! $this->isAuthorized('landlord_agent', $listingId, (int) $listing->user_id)) {
            abort(403);
        }

        $address = $listing->info('address');
        $city    = $listing->info('property_city');
        $state   = $listing->info('property_state');

        if (empty($address) || empty($city) || empty($state)) {
            return back()->withErrors(['address' => 'Complete the listing address (street, city, and state) before generating Location DNA.']);
        }

        return $this->dispatchAndRespond('landlord_agent', $listingId);
    }

    /**
     * Dispatch ComputeLocationDna and respond with a message that is true under the
     * queue driver actually in use.
     *
     * Under an asynchronous driver the job is only enqueued here, so "generation has
     * started" is accurate and no outcome is knowable yet. Under the `sync` driver
     * dispatch() *is* execution: by the time it returns, the pipeline has already run
     * to completion and its outcome is final, so claiming "started" would be false —
     * and on a failed run it would contradict the "Failed" badge the panel renders
     * from the same record.
     *
     * The pipeline's outcome cannot be returned through the queue: SyncQueue::push()
     * discards handle()'s return value, dispatch() yields a PendingDispatch rather
     * than a result, and the job that executes is a serialized clone of the one we
     * dispatched. Persistence is therefore the only channel back, so the inline
     * outcome is read from the PropertyLocationDna row the pipeline just wrote.
     */
    private function dispatchAndRespond(string $listingType, int $listingId)
    {
        $before = $this->findDnaRecord($listingType, $listingId);

        try {
            ComputeLocationDna::dispatch($listingType, $listingId);
        } catch (\Throwable $e) {
            Log::error('AgentLocationDnaController: failed to dispatch ComputeLocationDna', [
                'listing_type' => $listingType,
                'listing_id'   => $listingId,
                'error'        => $e->getMessage(),
                'exception'    => get_class($e),
            ]);

            return back()->withErrors(['dna' => 'Failed to start Location DNA generation. Please try again.']);
        }

        if (! $this->dispatchRunsInline()) {
            return back()->with('dna_success', 'Location DNA generation has started.');
        }

        return $this->respondToInlineOutcome($listingType, $listingId, $before);
    }

    /**
     * True when the configured queue driver executes jobs inline on dispatch, i.e. the
     * pipeline has already finished by the time dispatch() returns.
     */
    private function dispatchRunsInline(): bool
    {
        return app(QueueFactory::class)->connection() instanceof SyncQueue;
    }

    private function findDnaRecord(string $listingType, int $listingId): ?PropertyLocationDna
    {
        return PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->first();
    }

    /**
     * Translate the now-final PropertyLocationDna row into a user-facing response.
     *
     * The pipeline reports four outcomes (success / partial / failed / skipped) but
     * persists no single terminal status field, so the outcome is reconstructed:
     *
     *   - no row at all      → the geocode step returned 'skipped' before creating a
     *                          record (its missing-address branch writes nothing), so
     *                          nothing ran. Never a success.
     *   - geocode 'failed'   → the run failed outright.
     *   - generated_at moved → the summary step completed on *this* run. Combined with
     *     + lifestyle_json     a populated lifestyle_json this is a full success.
     *   - anything else      → partial: some steps ran, the run did not complete.
     *
     * generated_at is compared against a pre-dispatch snapshot rather than merely
     * checked for non-null, because a failed refresh leaves the *previous* run's
     * generated_at in place — a bare null-check would report that stale success as a
     * fresh one.
     */
    private function respondToInlineOutcome(string $listingType, int $listingId, ?PropertyLocationDna $before)
    {
        $after = $this->findDnaRecord($listingType, $listingId);

        $outcome = $this->classifyInlineOutcome($before, $after);

        if ($outcome === 'success') {
            return back()->with('dna_success', 'Location DNA generated.');
        }

        Log::warning('AgentLocationDnaController: inline Location DNA run did not succeed', [
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'outcome'        => $outcome,
            'geocode_status' => $after?->geocode_status,
            'geocode_error'  => $after?->geocode_error,
        ]);

        if ($outcome === 'failed') {
            return back()->withErrors(['dna' => 'Location DNA generation failed. Verify the listing address is correct, then try again.']);
        }

        if ($outcome === 'skipped') {
            return back()->withErrors(['dna' => 'Location DNA generation could not run. Check that the listing address is complete, then try again.']);
        }

        return back()->withErrors(['dna' => 'Location DNA generation did not finish. Some location data may be missing — please try again.']);
    }

    /** @return 'success'|'failed'|'skipped'|'partial' */
    private function classifyInlineOutcome(?PropertyLocationDna $before, ?PropertyLocationDna $after): string
    {
        if ($after === null) {
            return 'skipped';
        }

        if ($after->geocode_status === 'failed') {
            return 'failed';
        }

        $generatedAtAdvanced = $after->generated_at !== null
            && (string) $after->generated_at !== (string) ($before?->generated_at ?? '');

        if ($generatedAtAdvanced && ! empty($after->lifestyle_json)) {
            return 'success';
        }

        return 'partial';
    }
}
