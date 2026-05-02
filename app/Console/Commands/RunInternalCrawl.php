<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Jobs\InternalCrawlJob;
use Illuminate\Console\Command;

class RunInternalCrawl extends Command
{
    protected $signature = 'app:run-internal-crawl';
    protected $description = 'Dispatch internal crawl jobs for all active monitors';

    public function handle()
    {
        $monitors = Monitor::where('is_active', true)->get();

        foreach ($monitors as $monitor) {
            InternalCrawlJob::dispatch($monitor);
        }

        $this->info("Dispatched crawl jobs for {$monitors->count()} monitors.");
    }
}
