<?php

namespace App\Jobs;

use App\Models\ServerPortBaseline;
use App\Services\NetworkAlertService;
use App\Services\NetworkCheckService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckServerPortBaseline implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [30, 60, 120];

    public function __construct(public ServerPortBaseline $baseline, public bool $force = false)
    {
        $this->onQueue('checks');
    }

    public function handle(NetworkCheckService $checks, NetworkAlertService $alerts): void
    {
        $baseline = $this->baseline->refresh();

        if (! $this->force && ! $baseline->is_active) {
            return;
        }

        $result = $checks->checkPortBaseline($baseline);
        $alerts->evaluatePortBaseline($baseline, $result);
    }
}
