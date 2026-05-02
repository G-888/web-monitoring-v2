<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\DnsRecord;
use App\Models\DiscoveredSubdomain;
use Illuminate\Support\Facades\Log;

class DnsScannerService
{
    /**
     * Scan for DNS records of a given domain.
     */
    public function scanDns($domain)
    {
        $types = [DNS_A, DNS_AAAA, DNS_MX, DNS_TXT, DNS_NS, DNS_CNAME];
        $results = [];

        foreach ($types as $type) {
            $records = @dns_get_record($domain, $type);
            if ($records) {
                $results = array_merge($results, $records);
            }
        }

        return $results;
    }

    /**
     * Discover subdomains using Certificate Transparency (crt.sh).
     */
    public function discoverSubdomains($domain)
    {
        $domain = $this->cleanDomain($domain);
        $subdomains = [];

        try {
            $response = Http::timeout(15)->get("https://crt.sh/?q=%.{$domain}&output=json");
            
            if ($response->successful()) {
                $data = $response->json();
                foreach ($data as $cert) {
                    $names = explode("\n", $cert['common_name'] ?? '');
                    foreach ($names as $name) {
                        $name = strtolower(trim($name));
                        if (str_ends_with($name, $domain) && $name !== $domain) {
                            $subdomains[] = $name;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Subdomain discovery failed for {$domain}: " . $e->getMessage());
        }

        return array_unique($subdomains);
    }

    /**
     * Check for DNS changes compared to baseline.
     */
    public function detectChanges($monitorId, $newRecords)
    {
        $changes = [];
        
        foreach ($newRecords as $record) {
            $type = $record['type'];
            $host = $record['host'] ?? '';
            $value = $this->getRecordValue($record);
            $hash = md5($type . $host . $value);

            $exists = \DB::table('dns_records')
                ->where('monitor_id', $monitorId)
                ->where('hash', $hash)
                ->exists();

            if (!$exists) {
                $changes[] = [
                    'type' => $type,
                    'host' => $host,
                    'value' => $value,
                    'change_type' => 'NEW_OR_MODIFIED'
                ];
            }
        }

        return $changes;
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

    private function cleanDomain($url)
    {
        $domain = str_replace(['http://', 'https://'], '', $url);
        return explode('/', $domain)[0];
    }
}
