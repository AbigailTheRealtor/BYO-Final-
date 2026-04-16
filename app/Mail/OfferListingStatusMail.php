<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\OfferAuction;

class OfferListingStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public OfferAuction $listing;
    public string $status;

    public function __construct(OfferAuction $listing, string $status)
    {
        $this->listing = $listing;
        $this->status  = $status;
    }

    public function build()
    {
        $subject = $this->status === 'approved'
            ? 'Your offer listing has been approved'
            : 'Your offer listing has been rejected';

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($subject)
            ->markdown('emails.offer_listing_status', [
                'listing' => $this->listing,
                'status'  => $this->status,
            ]);
    }
}
