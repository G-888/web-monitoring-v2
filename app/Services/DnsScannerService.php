<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DnsScannerService
{
    /**
     * Scan for DNS records and IP Intelligence.
     */
    public function scanDns($input)
    {
        if (filter_var($input, FILTER_VALIDATE_IP)) {
            $hostname = @gethostbyaddr($input);
            $geo = $this->getIpMetadata($input);
            
            return [
                [
                    'type' => 'PTR (Reverse DNS)',
                    'host' => $input,
                    'target' => $hostname ?: 'No PTR record found',
                    'ip' => $input,
                    'geo' => $geo
                ]
            ];
        }

        $types = [DNS_A, DNS_AAAA, DNS_MX, DNS_TXT, DNS_NS, DNS_CNAME];
        $results = [];

        foreach ($types as $type) {
            $records = @dns_get_record($input, $type);
            if ($records) {
                foreach($records as $r) {
                    // Enrich A records with Geo
                    if ($r['type'] === 'A') {
                        $r['geo'] = $this->getIpMetadata($r['ip']);
                    }
                    $results[] = $r;
                }
            }
        }

        return $results;
    }

    /**
     * Discover subdomains and fingerprint their technology.
     */
    public function discoverSubdomains($domain)
    {
        if (filter_var($domain, FILTER_VALIDATE_IP)) return [];

        $domain = $this->cleanDomain($domain);
        $discovered = [];

        try {
            $response = Http::timeout(15)->get("https://crt.sh/?q=%.{$domain}&output=json");
            
            if ($response->successful()) {
                $data = $response->json();
                foreach ($data as $cert) {
                    $names = explode("\n", $cert['common_name'] ?? '');
                    foreach ($names as $name) {
                        $name = strtolower(trim($name));
                        if (str_ends_with($name, $domain) && $name !== $domain) {
                            $discovered[] = $name;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Subdomain discovery failed: " . $e->getMessage());
        }

        return array_unique($discovered);
    }

    /**
     * Fingerprint technology and headers.
     */
    public function fingerprint($url)
    {
        if (!str_starts_with($url, 'http')) $url = "http://" . $url;

        try {
            $response = Http::timeout(5)->withoutVerifying()->get($url);
            $headers = $response->headers();

            return [
                'server' => $headers['Server'][0] ?? 'Unknown',
                'powered_by' => $headers['X-Powered-By'][0] ?? 'Unknown',
                'security' => [
                    'hsts' => isset($headers['Strict-Transport-Security']),
                    'csp' => isset($headers['Content-Security-Policy']),
                    'x_frame' => isset($headers['X-Frame-Options']),
                ]
            ];
        } catch (\Exception $e) {
            return ['error' => 'Could not fingerprint'];
        }
    }

    /**
     * Get GeoIP and ASN metadata.
     */
    public function getIpMetadata($ip)
    {
        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,as,org");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }

    private function cleanDomain($url)
    {
        $domain = str_replace(['http://', 'https://'], '', $url);
        return explode('/', $domain)[0];
    }
}
