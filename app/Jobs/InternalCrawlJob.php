<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\SeoDiscoveredPage;
use App\Services\CrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InternalCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Monitor $monitor)
    {}

    public function handle(CrawlerService $service): void
    {
        $links = $service->discoverLinks($this->monitor->url);

        foreach ($links as $link) {
            $page = \App\Models\SeoDiscoveredPage::firstOrNew([
                'monitor_id' => $this->monitor->id,
                'url' => $link
            ]);

            if (!$page->exists) {
                // New page discovered - alert if it's not the first crawl
                if (\App\Models\SeoDiscoveredPage::where('monitor_id', $this->monitor->id)->exists()) {
                    $this->dispatchAlert($link);
                }
            }

            $page->last_seen_at = now();
            $page->save();
        }
    }

    protected function dispatchAlert(string $url): void
    {
        $message = "[UNKNOWN_PAGE] New internal link discovered: {$url} on monitor {$this->monitor->name}";

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
