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
        // File integrity is a system-level check for the local server.
        // We only need to run it once per interval.
        FileIntegrityJob::dispatch();

        $this->info("Dispatched single file integrity job for the server.");
    }
}
