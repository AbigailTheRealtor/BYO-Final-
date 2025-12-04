<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PreferredAgentsMail extends Mailable
{
    use Queueable, SerializesModels;
    public $perefered_agent_details;
    public $seller_name;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $perefered_agent_details,$seller_name)
    {
        $this->perefered_agent_details = $perefered_agent_details;
        $this->seller_name = $seller_name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $prefered_agents=$this->perefered_agent_details;
        $sellerName=$this->seller_name;
        return $this->markdown('emails.preferred_agents_mail',compact('prefered_agents','sellerName'));
    }
}
