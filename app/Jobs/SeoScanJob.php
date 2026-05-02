<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\SeoScan;
use App\Services\SeoScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Monitor $monitor)
    {}

    public function handle(SeoScannerService $service): void
    {
        $result = $service->scan($this->monitor->url);

        // Store result
        $scan = new \App\Models\SeoScan();
        $scan->monitor_id = $this->monitor->id;
        $scan->url = $this->monitor->url;
        $scan->status = $result['status'];
        $scan->findings = $result['findings'];
        $scan->diffs = $result['hashes'];
        $scan->scanned_at = now();
        $scan->save();

        // Alert if suspicious
        if ($result['status'] !== 'clean') {
            $this->dispatchAlert($result);
        }
    }

    protected function dispatchAlert(array $result): void
    {
        $type = $result['cloaking'] ? 'SEO_CLOAKING' : 'SEO_SPAM';
        $findings = implode(', ', $result['findings']);
        
        $message = "[$type] Suspicious SEO activity detected on {$this->monitor->name} ({$this->monitor->url}). Findings: {$findings}";

        // Integration with existing alert logic
        $user = $this->monitor->user;
        if ($user) {
            $channels = $user->alertChannels()->where('is_active', true)->get();
            foreach ($channels as $channel) {
                try {
                    Http::post($channel->endpoint, ['content' => $message]);
                } catch (\Exception $e) {
                    Log::error("Failed to send alert: " . $e->getMessage());
                }
            }
        }
    }
}
