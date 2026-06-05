<?php

namespace App\Services\Showing;

use App\Enums\ShowingStatus;
use App\Events\ShowingStatusChanged;
use App\Exceptions\ShowingTransitionException;
use App\Models\Showing;
use App\Models\User;

class ShowingStatusService
{
    /**
     * Approve a showing request.
     * Valid predecessor: requested → approved
     */
    public function approve(Showing $showing, array $data, User $actor): Showing
    {
        if ($showing->status !== ShowingStatus::REQUESTED) {
            throw new ShowingTransitionException($showing->status, ShowingStatus::APPROVED);
        }

        $previous = $showing->status;

        $showing->status              = ShowingStatus::APPROVED;
        $showing->approved_date       = $data['approved_date']        ?? $showing->requested_date;
        $showing->approved_start_time = $data['approved_start_time']  ?? $showing->requested_start_time;
        $showing->approved_end_time   = $data['approved_end_time']    ?? $showing->requested_end_time;
        $showing->owner_message       = $data['owner_message']        ?? null;
        $showing->save();

        // TODO: wire up listeners in the Notifications task
        event(new ShowingStatusChanged($showing, $previous, $actor));

        return $showing;
    }

    /**
     * Decline a showing request.
     * Valid predecessor: requested → declined
     */
    public function decline(Showing $showing, array $data, User $actor): Showing
    {
        if ($showing->status !== ShowingStatus::REQUESTED) {
            throw new ShowingTransitionException($showing->status, ShowingStatus::DECLINED);
        }

        $previous = $showing->status;

        $showing->status        = ShowingStatus::DECLINED;
        $showing->owner_message = $data['owner_message'] ?? null;
        $showing->save();

        // TODO: wire up listeners in the Notifications task
        event(new ShowingStatusChanged($showing, $previous, $actor));

        return $showing;
    }

    /**
     * Cancel a showing.
     * Valid predecessors: requested → canceled, approved → canceled
     */
    public function cancel(Showing $showing, User $actor): Showing
    {
        $allowed = [ShowingStatus::REQUESTED, ShowingStatus::APPROVED];

        if (!in_array($showing->status, $allowed, true)) {
            throw new ShowingTransitionException($showing->status, ShowingStatus::CANCELED);
        }

        $previous = $showing->status;

        $showing->status      = ShowingStatus::CANCELED;
        $showing->canceled_at = now();
        $showing->save();

        // TODO: wire up listeners in the Notifications task
        event(new ShowingStatusChanged($showing, $previous, $actor));

        return $showing;
    }

    /**
     * Mark a showing as completed.
     * Valid predecessor: approved → completed
     */
    public function complete(Showing $showing, User $actor): Showing
    {
        if ($showing->status !== ShowingStatus::APPROVED) {
            throw new ShowingTransitionException($showing->status, ShowingStatus::COMPLETED);
        }

        $previous = $showing->status;

        $showing->status       = ShowingStatus::COMPLETED;
        $showing->completed_at = now();
        $showing->save();

        // TODO: wire up listeners in the Notifications task
        event(new ShowingStatusChanged($showing, $previous, $actor));

        return $showing;
    }
}
