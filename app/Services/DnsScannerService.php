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
            $client = stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 2, STREAM_CLIENT_CONNECT, $context);
            if (!$client) return ['error' => 'Connection failed'];
            $params = stream_context_get_params($client);
            $cert = openssl_x509_parse($params["options"]["ssl"]["peer_certificate"]);
            return [
                'issuer' => $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unknown',
                'valid_to' => date('Y-m-d', $cert['validTo_time_t']),
                'algorithm' => $cert['signatureTypeLN'] ?? 'Unknown',
                'serial' => $cert['serialNumber'] ?? 'Unknown'
            ];
        } catch (\Exception $e) { return ['error' => 'No SSL info']; }
    }

    public function checkReputation($ip)
    {
        try {
            $response = Http::timeout(2)->get("https://api.hackertarget.com/aslookup/?q={$ip}");
            return $response->successful() ? $response->body() : 'Clean';
        } catch (\Exception $e) { return 'Unknown'; }
    }

    public function auditCookies($url)
    {
        if (!str_starts_with($url, 'http')) $url = "http://" . $url;
        try {
            $response = Http::timeout(3)->get($url);
            $cookies = $response->header('Set-Cookie') ?? [];
            if (is_string($cookies)) $cookies = [$cookies];
            $results = [];
            foreach ($cookies as $c) {
                $results[] = [
                    'name' => explode('=', $c)[0],
                    'secure' => str_contains($c, 'Secure'),
                    'httponly' => str_contains($c, 'HttpOnly'),
                ];
            }
            return $results;
        } catch (\Exception $e) { return []; }
    }

    public function checkSensitivePaths($url)
    {
        if (!str_starts_with($url, 'http')) $url = "https://" . $url;
        $url = rtrim($url, '/');
        
        $paths = [
            '/.env' => 'Environment File (Credentials Leak)',
            '/.git/config' => 'Git Repository (Source Leak)',
            '/.vscode/settings.json' => 'VS Code Config',
            '/phpinfo.php' => 'PHP Information (Info Leak)',
            '/config.php.bak' => 'Config Backup',
            '/wp-config.php.bak' => 'WordPress Config Backup',
            '/phpmyadmin/' => 'Database Management Panel',
            '/admin' => 'Administrative Portal',
            '/.htaccess' => 'Server Config',
        ];

        $discovered = [];
        foreach ($paths as $path => $description) {
            try {
                $response = Http::timeout(0.5)->withoutVerifying()->get($url . $path);
                if ($response->successful() || $response->status() === 403) {
                    $discovered[] = [
                        'path' => $path,
                        'description' => $description,
                        'status' => $response->status(),
                        'severity' => $response->successful() ? 'CRITICAL' : 'WARNING'
                    ];
                }
            } catch (\Exception $e) {}
        }
        return $discovered;
    }

    public function getSeoIntelligence($url)
    {
        if (!str_starts_with($url, 'http')) $url = "https://" . $url;
        try {
            $response = Http::timeout(5)->get($url);
            $html = $response->body();
            
            preg_match("/<title>(.*)<\/title>/i", $html, $matches);
            $title = $matches[1] ?? 'No Title Found';

            preg_match('/<meta name="description" content="(.*)"/i', $html, $matches);
            $desc = $matches[1] ?? 'No Description Found';

            // Check robots.txt
            $domain = parse_url($url, PHP_URL_HOST);
            $robots = Http::timeout(2)->get("https://{$domain}/robots.txt")->successful();
            $sitemap = Http::timeout(2)->get("https://{$domain}/sitemap.xml")->successful();

            return [
                'title' => $title,
                'description' => $desc,
                'robots' => $robots,
                'sitemap' => $sitemap
            ];
        } catch (\Exception $e) { return null; }
    }

    public function scanPorts($ip)
    {
        $ports = [21 => 'FTP', 22 => 'SSH', 80 => 'HTTP', 443 => 'HTTPS', 3306 => 'MySQL', 3389 => 'RDP'];
        $results = [];
        foreach ($ports as $port => $name) {
            $connection = @fsockopen($ip, $port, $errno, $errstr, 0.2);
            if (is_resource($connection)) {
                $results[] = ['port' => $port, 'service' => $name];
                fclose($connection);
            }
        }
        return $results;
    }

    public function fingerprint($url)
    {
        if (!str_starts_with($url, 'http')) $url = "https://" . $url;
        try {
            $response = Http::timeout(3)->withoutVerifying()->get($url);
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
        } catch (\Exception $e) { return ['error' => 'Timeout']; }
    }

    public function discoverSubdomains($domain)
    {
        if (filter_var($domain, FILTER_VALIDATE_IP)) return [];
        try {
            $response = Http::timeout(5)->get("https://crt.sh/?q=%.{$domain}&output=json");
            if ($response->successful()) {
                $subs = [];
                foreach (array_slice($response->json(), 0, 15) as $cert) {
                    $names = explode("\n", $cert['common_name'] ?? '');
                    foreach ($names as $name) {
                        $name = strtolower(trim($name));
                        if (str_ends_with($name, $domain) && $name !== $domain) $subs[] = $name;
                    }
                }
                return array_unique($subs);
            }
        } catch (\Exception $e) {}
        return [];
    }

    public function getIpMetadata($ip)
    {
        try {
            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,as,org");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) { return null; }
    }

    private function cleanDomain($url)
    {
        $domain = str_replace(['http://', 'https://'], '', $url);
        return explode('/', $domain)[0];
    }
}
