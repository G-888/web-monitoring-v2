<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Monitor;
use App\Jobs\CheckWebsiteJob;

class RunMonitorChecks extends Command
{
    protected $signature = 'app:run-monitor-checks';

    protected $description = 'Run monitoring checks for all active monitors';

    public function handle()
    {
        $monitors = Monitor::with('latestResult')
            ->where('is_active', true)
            ->get();

        $dispatched = 0;

        foreach ($monitors as $monitor) {
            $lastCheckedAt = $monitor->latestResult?->checked_at;

            if ($lastCheckedAt && $lastCheckedAt->gt(now()->subSeconds($monitor->interval))) {
                continue;
            }

            dispatch(new CheckWebsiteJob($monitor));
            $dispatched++;
        }

        $this->info("Monitor checks dispatched: {$dispatched}");
    }
}
