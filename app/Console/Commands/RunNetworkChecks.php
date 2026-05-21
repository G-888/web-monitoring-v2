<?php

namespace App\Console\Commands;

use App\Jobs\CheckNetworkMonitor;
use App\Jobs\CheckServerPortBaseline;
use App\Models\NetworkMonitor;
use App\Models\ServerPortBaseline;
use Illuminate\Console\Command;

class RunNetworkChecks extends Command
{
    protected $signature = 'app:run-network-checks';

    protected $description = 'Queue lightweight network connectivity checks for configured targets only';

    public function handle(): int
    {
        NetworkMonitor::query()
            ->where('is_active', true)
            ->where('source_type', NetworkMonitor::SOURCE_CENTRAL)
            ->get()
            ->each(fn (NetworkMonitor $monitor) => CheckNetworkMonitor::dispatch($monitor));

        ServerPortBaseline::query()
            ->where('is_active', true)
            ->get()
            ->each(fn (ServerPortBaseline $baseline) => CheckServerPortBaseline::dispatch($baseline));

        $this->info('Network checks queued.');

        return self::SUCCESS;
    }
}
