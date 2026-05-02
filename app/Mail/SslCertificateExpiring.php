<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class SslCertificateExpiring extends Mailable
{
    public $monitor;
    public $daysLeft;

    public function __construct($monitor, $daysLeft)
    {
        $this->monitor = $monitor;
        $this->daysLeft = $daysLeft;
    }

    public function build()
    {
        return $this->subject('⚠️ SSL Certificate Expiring Soon')
            ->view('emails.ssl-expiring');
    }
}
