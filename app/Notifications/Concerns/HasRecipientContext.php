<?php

namespace App\Notifications\Concerns;

trait HasRecipientContext
{
    /**
     * Determine whether the notifiable is the submitter or the listing/auction owner.
     *
     * Pass the submitter's user_id and, where available, the owner's user_id.
     * The notifiable's id is compared against both:
     *   - match submitterId → 'submitter'
     *   - match ownerId     → 'owner'
     *   - neither matches (or notifiable is null) → 'owner' (safe default)
     *
     * @param  mixed    $notifiable
     * @param  int|null $submitterId  e.g. $offer->user_id, $bid->user_id
     * @param  int|null $ownerId      e.g. $auction->user_id, $offer->offerAuction->user_id
     * @return 'owner'|'submitter'
     */
    private function resolveRecipientContext(
        mixed $notifiable,
        int|null $submitterId,
        int|null $ownerId = null,
    ): string {
        $recipientId = is_object($notifiable) ? ($notifiable->id ?? null) : null;

        if ($recipientId !== null && $submitterId !== null && (int) $recipientId === (int) $submitterId) {
            return 'submitter';
        }

        if ($recipientId !== null && $ownerId !== null && (int) $recipientId === (int) $ownerId) {
            return 'owner';
        }

        return 'owner';
    }
}
