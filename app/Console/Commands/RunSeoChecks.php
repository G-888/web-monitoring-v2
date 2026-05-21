<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Jobs\SeoScanJob;
use App\Services\OutboundScanGuard;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RunSeoChecks extends Command
{
    protected $signature = 'app:run-seo-checks';
    protected $description = 'Dispatch SEO scan jobs for all active monitors';

    public function handle(OutboundScanGuard $scanGuard)
    {
        $monitors = Monitor::where('is_active', true)->get();
        $queued = 0;
        $skipped = 0;

        foreach ($monitors as $monitor) {
            try {
                $scanGuard->assertAllowed($monitor->url);
            } catch (ValidationException) {
                $skipped++;
                continue;
            }

            SeoScanJob::dispatch($monitor)->onQueue('security');
            $queued++;
        }

        $this->info("Dispatched SEO scan jobs for {$queued} monitors. Skipped {$skipped} by outbound scan policy.");
    }
}
