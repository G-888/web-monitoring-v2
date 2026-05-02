<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class MonitorRecovered extends Mailable
{
    public $monitor;

    public function __construct($monitor)
    {
        $this->monitor = $monitor;
    }

    public function build()
    {
        return $this->subject('✅ Website RECOVERED')
            ->view('emails.monitor-recovered');
    }
}