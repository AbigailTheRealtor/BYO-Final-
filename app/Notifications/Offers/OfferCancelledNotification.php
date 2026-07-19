<?php

namespace App\Notifications\Offers;

use App\Models\Offer;
use App\Notifications\Concerns\HasRecipientContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B2.1B — an accepted offer was administratively cancelled.
 *
 * Dispatched to BOTH negotiation parties (listing owner + offer submitter). The
 * message is tailored to the recipient via HasRecipientContext; the controller
 * chooses the recipients. The cancellation reason is carried for display.
 */
final class OfferCancelledNotification extends Notification
{
    use Queueable;
    use HasRecipientContext;

    public function __construct(
        public readonly Offer $offer,
        public readonly string $reason = '',
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        $context = $this->resolveRecipientContext(
            $notifiable,
            $this->offer->user_id,
            $this->offer->relationLoaded('offerAuction')
                ? ($this->offer->offerAuction?->user_id ?? null)
                : null,
        );

        $message = $context === 'submitter'
            ? 'Your accepted offer was cancelled.'
            : 'An accepted offer on your listing was cancelled.';

        return [
            'message'            => $message,
            'reason'             => $this->reason,
            'offer_id'           => $this->offer->id,
            'status'             => $this->offer->status,
            'link'               => route('offers.show', $this->offer),
            'type'               => 'offer_cancelled',
            'recipient_context'  => $context,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $context = $this->resolveRecipientContext(
            $notifiable,
            $this->offer->user_id,
            $this->offer->relationLoaded('offerAuction')
                ? ($this->offer->offerAuction?->user_id ?? null)
                : null,
        );

        $mail = $context === 'submitter'
            ? (new MailMessage)
                ->subject('Accepted Offer #' . $this->offer->id . ' Cancelled')
                ->line('Your accepted offer (ID: ' . $this->offer->id . ') has been cancelled.')
            : (new MailMessage)
                ->subject('An Accepted Offer on Your Listing Was Cancelled')
                ->line('An accepted offer (ID: ' . $this->offer->id . ') on your listing has been cancelled.');

        if ($this->reason !== '') {
            $mail->line('Reason: ' . $this->reason);
        }

        return $mail
            ->line('Current status: ' . $this->offer->status)
            ->action('View Offer', route('offers.show', $this->offer));
    }
}
