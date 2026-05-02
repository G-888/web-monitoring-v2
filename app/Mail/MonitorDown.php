<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class MonitorDown extends Mailable
{
    public $monitor;

    public function __construct($monitor)
    {
        $this->monitor = $monitor;
    }

    public function build()
    {
        return $this->subject('🚨 Website DOWN')
            ->view('emails.monitor-down');
    }
}