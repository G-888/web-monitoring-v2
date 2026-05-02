<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Monitor;
use App\Services\DnsScannerService;
use App\Models\Alert;
use App\Models\DnsRecord;
use App\Models\DiscoveredSubdomain;

class ScanDnsIntelligenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $monitor;

    public function __construct(Monitor $monitor)
    {
        $this->monitor = $monitor;
    }

    public function handle(DnsScannerService $service)
    {
        $domain = parse_url($this->monitor->url, PHP_URL_HOST) ?: $this->monitor->url;
        
        // 1. Scan DNS Records
        $records = $service->scanDns($domain);
        $changes = $service->detectChanges($this->monitor->id, $records);

        foreach ($records as $record) {
            $value = $this->getRecordValue($record);
            
            \DB::table('dns_records')->updateOrInsert(
                [
                    'monitor_id' => $this->monitor->id,
                    'type' => $record['type'],
                    'host' => $record['host'] ?? $domain,
                    'hash' => md5($record['type'] . ($record['host'] ?? $domain) . $value)
                ],
                [
                    'value' => $value,
                    'ttl' => $record['ttl'] ?? null,
                    'last_seen_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // 2. Alert on Changes
        if (!empty($changes)) {
            $this->triggerAlert($changes);
        }

        // 3. Subdomain Discovery (Weekly/Randomized)
        if (rand(1, 100) > 90) { 
            $subdomains = $service->discoverSubdomains($domain);
            foreach ($subdomains as $sub) {
                \DB::table('discovered_subdomains')->updateOrInsert(
                    ['monitor_id' => $this->monitor->id, 'subdomain' => $sub],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    private function getRecordValue($record)
    {
        return match($record['type']) {
            'A', 'AAAA' => $record['ip'],
            'MX', 'NS', 'CNAME' => $record['target'],
            'TXT' => $record['txt'] ?? '',
            default => serialize($record)
        };
    }

    private function triggerAlert($changes)
    {
        $message = "DNS Changes detected for {$this->monitor->name}:\n";
        foreach ($changes as $change) {
            $message .= "- [{$change['type']}] {$change['host']} -> {$change['value']}\n";
        }

        // Integrated with existing Alert system
        \App\Models\Alert::create([
            'monitor_id' => $this->monitor->id,
            'type' => 'DNS_CHANGE',
            'message' => $message,
            'severity' => 'warning'
        ]);
    }
}
