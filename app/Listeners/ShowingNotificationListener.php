<?php

namespace App\Listeners;

use App\Enums\ShowingStatus;
use App\Events\ShowingStatusChanged;
use App\Models\User;
use App\Models\UserAgent;
use App\Notifications\Showings\ShowingApprovedNotification;
use App\Notifications\Showings\ShowingCanceledNotification;
use App\Notifications\Showings\ShowingDeclinedNotification;
use App\Notifications\Showings\ShowingRequestedNotification;

class ShowingNotificationListener
{
    public function handle(ShowingStatusChanged $event): void
    {
        $showing = $event->showing;
        $actor   = $event->actor;

        $showing->loadMissing(['offerAuction.metas', 'requester']);

        $auction = $showing->offerAuction;

        if (!$auction) {
            return;
        }

        switch ($showing->status) {
            case ShowingStatus::REQUESTED:
                $this->notifyRequested($showing, $actor);
                break;

            case ShowingStatus::APPROVED:
                $this->notifyApproved($showing, $actor);
                break;

            case ShowingStatus::DECLINED:
                $this->notifyDeclined($showing, $actor);
                break;

            case ShowingStatus::CANCELED:
                $this->notifyCanceled($showing, $actor);
                break;
        }
    }

    /**
     * Notify the listing owner and any agents assigned to the listing owner.
     * Agent assignment uses the user_agents table — the canonical source per ShowingPolicy.
     */
    private function notifyRequested(
        \App\Models\Showing $showing,
        User $actor
    ): void {
        $auction      = $showing->offerAuction;
        $notification = new ShowingRequestedNotification($showing);

        $recipients = $this->ownerAndAgents($auction->user_id, (int) $actor->id);

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }

    private function notifyApproved(
        \App\Models\Showing $showing,
        User $actor
    ): void {
        $requester = $showing->requester;
        if ($requester && (int) $requester->id !== (int) $actor->id) {
            $requester->notify(new ShowingApprovedNotification($showing));
        }
    }

    private function notifyDeclined(
        \App\Models\Showing $showing,
        User $actor
    ): void {
        $requester = $showing->requester;
        if ($requester && (int) $requester->id !== (int) $actor->id) {
            $requester->notify(new ShowingDeclinedNotification($showing));
        }
    }

    private function notifyCanceled(
        \App\Models\Showing $showing,
        User $actor
    ): void {
        $auction      = $showing->offerAuction;
        $notification = new ShowingCanceledNotification($showing);

        $actorIsRequester = (int) $showing->requester_id === (int) $actor->id;

        if ($actorIsRequester) {
            // Requester canceled → notify the listing owner and assigned agents
            $recipients = $this->ownerAndAgents($auction->user_id, (int) $actor->id);
            foreach ($recipients as $recipient) {
                $recipient->notify($notification);
            }
        } else {
            // Owner or agent canceled → notify the requester (if not the actor)
            $requester = $showing->requester;
            if ($requester && (int) $requester->id !== (int) $actor->id) {
                $requester->notify($notification);
            }
        }
    }

    /**
     * Return the listing owner and all agents assigned to that owner
     * (via user_agents table, consistent with ShowingPolicy::isOwnerOrAgent),
     * excluding the given actor ID.
     *
     * @return User[]
     */
    private function ownerAndAgents(int $ownerId, int $actorId): array
    {
        $recipients = [];

        // Listing owner
        if ($ownerId !== $actorId) {
            $owner = User::find($ownerId);
            if ($owner) {
                $recipients[] = $owner;
            }
        }

        // Assigned agents — canonical assignment source per ShowingPolicy
        $agentIds = UserAgent::where('user_id', $ownerId)
            ->where('agent_id', '!=', $actorId)
            ->pluck('agent_id');

        if ($agentIds->isNotEmpty()) {
            $agents = User::whereIn('id', $agentIds)->get();
            foreach ($agents as $agent) {
                $recipients[] = $agent;
            }
        }

        return $recipients;
    }
}
