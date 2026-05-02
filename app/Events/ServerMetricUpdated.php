<?php

namespace App\Events;

use App\Models\ServerMetric;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerMetricUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ServerMetric $metric;

    /**
     * Create a new event instance.
     */
    public function __construct(ServerMetric $metric)
    {
        $this->metric = $metric;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('servers.' . $this->metric->server_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'server.metric.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->metric->server_id,
            'cpu' => $this->metric->cpu,
            'ram_used' => $this->metric->ram_used,
            'ram_total' => $this->metric->ram_total,
            'ram_percentage' => round(($this->metric->ram_used / $this->metric->ram_total) * 100, 1),
            'disk_used' => $this->metric->disk_used,
            'disk_total' => $this->metric->disk_total,
            'disk_percentage' => round(($this->metric->disk_used / $this->metric->disk_total) * 100, 1),
            'timestamp' => $this->metric->timestamp->toISOString(),
        ];
    }
}
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }

