<?php

namespace App\Events;

use App\Models\ServerMetric;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
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
        $ramTotal = (float) $this->metric->ram_total;
        $diskTotal = (float) $this->metric->disk_total;

        return [
            'server_id' => $this->metric->server_id,
            'cpu' => $this->metric->cpu,
            'ram_used' => $this->metric->ram_used,
            'ram_total' => $this->metric->ram_total,
            'ram_percentage' => $ramTotal > 0
                ? round(((float) $this->metric->ram_used / $ramTotal) * 100, 1)
                : null,
            'disk_used' => $this->metric->disk_used,
            'disk_total' => $this->metric->disk_total,
            'disk_percentage' => $diskTotal > 0
                ? round(((float) $this->metric->disk_used / $diskTotal) * 100, 1)
                : null,
            'timestamp' => $this->metric->timestamp->toISOString(),
        ];
    }
}
