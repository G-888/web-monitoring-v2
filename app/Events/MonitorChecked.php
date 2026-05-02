<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class MonitorChecked implements ShouldBroadcastNow
{
    use SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function broadcastOn()
    {
        return new Channel('monitors');
    }

    public function broadcastAs()
    {
        return 'monitor.checked';
    }

    public function broadcastWith()
    {
        return $this->data;
    }
}