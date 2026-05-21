<?php

namespace App\Jobs;

use App\Models\NetworkMonitor;
use App\Services\NetworkAlertService;
use App\Services\NetworkCheckService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckNetworkMonitor implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [30, 60, 120];

    public function __construct(public NetworkMonitor $networkMonitor, public bool $force = false)
    {
        $this->onQueue('checks');
    }

    public function handle(NetworkCheckService $checks, NetworkAlertService $alerts): void
    {
        $monitor = $this->networkMonitor->refresh();

        if (! $this->force && (! $monitor->is_active || $monitor->source_type !== NetworkMonitor::SOURCE_CENTRAL)) {
            return;
        }

        $result = $checks->checkMonitor($monitor);
        $alerts->evaluateMonitor($monitor, $result);
    }
}
