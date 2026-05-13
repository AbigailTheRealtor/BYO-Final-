<?php

namespace App\Mail;

use App\Models\SellerListingInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SellerListingInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public SellerListingInquiry $inquiry;
    public string $listingTitle;
    public string $listingUrl;

    public function __construct(SellerListingInquiry $inquiry, string $listingTitle, string $listingUrl)
    {
        $this->inquiry      = $inquiry;
        $this->listingTitle = $listingTitle;
        $this->listingUrl   = $listingUrl;
    }

    public function build(): static
    {
        $subject = $this->inquiry->type === 'showing'
            ? 'Showing Request: ' . $this->listingTitle
            : 'New Question: ' . $this->listingTitle;

        return $this->subject($subject)
                    ->view('emails.seller-listing-inquiry');
    }
}
