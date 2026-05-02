<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Jobs\FileIntegrityJob;
use Illuminate\Console\Command;

class RunFileIntegrityChecks extends Command
{
    protected $signature = 'app:run-file-integrity-checks';
    protected $description = 'Dispatch file integrity check jobs for all monitors';

    public function handle()
    {
        // For file integrity, we might run it on a specific node or all monitors representing nodes
        $monitors = Monitor::where('is_active', true)->get();

        foreach ($monitors as $monitor) {
            FileIntegrityJob::dispatch($monitor);
        }

        $this->info("Dispatched file integrity jobs for {$monitors->count()} monitors.");
    }
}
