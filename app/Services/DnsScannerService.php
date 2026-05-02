<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DnsScannerService
{
    /**
     * Scan for DNS records and infrastructure intelligence.
     */
    public function scanDns($input)
    {
        if (filter_var($input, FILTER_VALIDATE_IP)) {
            return $this->handleIpScan($input);
        }

        $types = [DNS_A, DNS_AAAA, DNS_MX, DNS_TXT, DNS_NS, DNS_CNAME];
        $results = [];

        foreach ($types as $type) {
            $records = @dns_get_record($input, $type);
            if ($records) {
                foreach($records as $r) {
                    if ($r['type'] === 'A') $r['geo'] = $this->getIpMetadata($r['ip']);
                    $results[] = $r;
                }
            }
        }

        return $results;
    }

    /**
     * Perform active port scan on critical services.
     */
    public function scanPorts($ip)
    {
        $ports = [
            21 => 'FTP', 22 => 'SSH', 23 => 'Telnet', 25 => 'SMTP', 
            53 => 'DNS', 80 => 'HTTP', 443 => 'HTTPS', 3306 => 'MySQL', 
            3389 => 'RDP', 8080 => 'HTTP-ALT'
        ];
        
        $results = [];
        foreach ($ports as $port => $name) {
            $connection = @fsockopen($ip, $port, $errno, $errstr, 0.5);
            if (is_resource($connection)) {
                $results[] = ['port' => $port, 'service' => $name, 'status' => 'OPEN'];
                fclose($connection);
            }
        }
        return $results;
    }

    /**
     * Audit Email Security (SPF, DMARC).
     */
    public function auditEmailSecurity($dnsRecords)
    {
        $audit = [
            'spf' => ['status' => false, 'value' => null],
            'dmarc' => ['status' => false, 'value' => null],
        ];

        foreach ($dnsRecords as $r) {
            if ($r['type'] === 'TXT') {
                $txt = strtolower($r['txt'] ?? '');
                if (str_contains($txt, 'v=spf1')) {
                    $audit['spf'] = ['status' => true, 'value' => $r['txt']];
                }
            }
        }

        return $audit;
    }

    /**
     * Detect CMS and Technology.
     */
    public function fingerprint($url)
    {
        if (!str_starts_with($url, 'http')) $url = "http://" . $url;

        try {
            $response = Http::timeout(5)->withoutVerifying()->get($url);
            $headers = $response->headers();
            $body = $response->body();

            $cms = 'Custom';
            if (str_contains($body, 'wp-content')) $cms = 'WordPress';
            if (str_contains($body, 'Joomla!')) $cms = 'Joomla';
            if (str_contains($body, 'Drupal')) $cms = 'Drupal';

            return [
                'server' => $headers['Server'][0] ?? 'Unknown',
                'cms' => $cms,
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

    public function discoverSubdomains($domain)
    {
        if (filter_var($domain, FILTER_VALIDATE_IP)) return [];
        $domain = $this->cleanDomain($domain);
        $discovered = [];
        try {
            $response = Http::timeout(10)->get("https://crt.sh/?q=%.{$domain}&output=json");
            if ($response->successful()) {
                foreach ($response->json() as $cert) {
                    $names = explode("\n", $cert['common_name'] ?? '');
                    foreach ($names as $name) {
                        $name = strtolower(trim($name));
                        if (str_ends_with($name, $domain) && $name !== $domain) $discovered[] = $name;
                    }
                }
            }
        } catch (\Exception $e) {}
        return array_unique($discovered);
    }

    public function getIpMetadata($ip)
    {
        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,as,org");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) { return null; }
    }

    private function handleIpScan($ip)
    {
        $hostname = @gethostbyaddr($ip);
        return [['type' => 'PTR', 'host' => $ip, 'target' => $hostname ?: 'No PTR', 'ip' => $ip, 'geo' => $this->getIpMetadata($ip)]];
    }

    private function cleanDomain($url)
    {
        $domain = str_replace(['http://', 'https://'], '', $url);
        return explode('/', $domain)[0];
    }
}
