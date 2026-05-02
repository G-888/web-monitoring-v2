<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Jobs\SeoScanJob;
use Illuminate\Console\Command;

class RunSeoChecks extends Command
{
    protected $signature = 'app:run-seo-checks';
    protected $description = 'Dispatch SEO scan jobs for all active monitors';

    public function handle()
    {
        $monitors = Monitor::where('is_active', true)->get();

        foreach ($monitors as $monitor) {
            SeoScanJob::dispatch($monitor);
        }

        $this->info("Dispatched SEO scan jobs for {$monitors->count()} monitors.");
    }
}
