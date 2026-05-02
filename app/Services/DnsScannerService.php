<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DnsScannerService
{
    public function scanDns($input)
    {
        if (filter_var($input, FILTER_VALIDATE_IP)) {
            $hostname = @gethostbyaddr($input);
            $geo = $this->getIpMetadata($input);
            $reputation = $this->checkReputation($input);
            
            return [['type' => 'PTR', 'host' => $input, 'target' => $hostname ?: 'No PTR', 'ip' => $input, 'geo' => $geo, 'reputation' => $reputation]];
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

    public function auditSsl($domain)
    {
        try {
            $context = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
            $client = stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
            $params = stream_context_get_params($client);
            $cert = openssl_x509_parse($params["options"]["ssl"]["peer_certificate"]);
            
            return [
                'issuer' => $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unknown',
                'valid_from' => date('Y-m-d', $cert['validFrom_time_t']),
                'valid_to' => date('Y-m-d', $cert['validTo_time_t']),
                'algorithm' => $cert['signatureTypeLN'] ?? 'Unknown',
                'serial' => $cert['serialNumber'] ?? 'Unknown'
            ];
        } catch (\Exception $e) { return ['error' => 'No SSL info']; }
    }

    public function checkReputation($ip)
    {
        try {
            // Using HackerTarget free API for quick reputation
            $response = Http::timeout(3)->get("https://api.hackertarget.com/aslookup/?q={$ip}");
            return $response->successful() ? $response->body() : 'Clean / Unknown';
        } catch (\Exception $e) { return 'Unknown'; }
    }

    public function auditCookies($url)
    {
        if (!str_starts_with($url, 'http')) $url = "http://" . $url;
        try {
            $response = Http::timeout(5)->get($url);
            $cookies = $response->header('Set-Cookie') ?? [];
            if (is_string($cookies)) $cookies = [$cookies];

            $results = [];
            foreach ($cookies as $c) {
                $results[] = [
                    'name' => explode('=', $c)[0],
                    'secure' => str_contains($c, 'Secure'),
                    'httponly' => str_contains($c, 'HttpOnly'),
                    'samesite' => str_contains($c, 'SameSite')
                ];
            }
            return $results;
        } catch (\Exception $e) { return []; }
    }

    public function scanPorts($ip)
    {
        $ports = [21 => 'FTP', 22 => 'SSH', 25 => 'SMTP', 80 => 'HTTP', 443 => 'HTTPS', 3306 => 'MySQL', 3389 => 'RDP'];
        $results = [];
        foreach ($ports as $port => $name) {
            $connection = @fsockopen($ip, $port, $errno, $errstr, 0.3);
            if (is_resource($connection)) {
                $results[] = ['port' => $port, 'service' => $name, 'status' => 'OPEN'];
                fclose($connection);
            }
        }
        return $results;
    }

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
                    'x_content' => isset($headers['X-Content-Type-Options']),
                ]
            ];
        } catch (\Exception $e) { return ['error' => 'No Fingerprint']; }
    }

    public function discoverSubdomains($domain)
    {
        if (filter_var($domain, FILTER_VALIDATE_IP)) return [];
        $domain = $this->cleanDomain($domain);
        $discovered = [];
        try {
            $response = Http::timeout(8)->get("https://crt.sh/?q=%.{$domain}&output=json");
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

    private function cleanDomain($url)
    {
        $domain = str_replace(['http://', 'https://'], '', $url);
        return explode('/', $domain)[0];
    }
}
