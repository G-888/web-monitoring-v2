<?php

namespace App\Jobs;

use App\Models\WebshellScan;
use App\Services\WebshellScannerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunWebshellScanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 180;
    public array $backoff = [60, 120, 300];

    public function __construct(public ?string $path = null, public string $source = 'scheduled')
    {
        $this->onQueue('security');
    }

    public function handle(WebshellScannerService $service): void
    {
        try {
            $result = $service->scan($this->path);
            $this->storeResult($result, $this->source);
        } catch (\Throwable $e) {
            WebshellScan::create([
                'source' => $this->source,
                'status' => 'failed',
                'target' => $this->path,
                'scanned_files' => 0,
                'findings' => [],
                'error' => $e->getMessage(),
                'scanned_at' => now(),
            ]);

            Log::warning('Webshell scan failed', [
                'path' => $this->path,
                'source' => $this->source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function storeResult(array $result, string $source = 'manual'): WebshellScan
    {
        return WebshellScan::create([
            'source' => $source,
            'status' => $result['status'] ?? 'failed',
            'target' => $result['target'] ?? null,
            'scanned_files' => (int) ($result['scanned_files'] ?? 0),
            'findings' => $result['findings'] ?? [],
            'error' => $result['error'] ?? null,
            'scanned_at' => isset($result['scanned_at'])
                ? \Carbon\Carbon::parse($result['scanned_at'])
                : now(),
        ]);
    }
}
