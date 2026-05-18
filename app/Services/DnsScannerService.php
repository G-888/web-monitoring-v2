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

    public function detectChanges(int $monitorId, array $records): array
    {
        $changes = [];

        foreach ($records as $record) {
            $host = $record['host'] ?? '';
            $type = $record['type'] ?? 'UNKNOWN';
            $value = match ($type) {
                'A', 'AAAA' => $record['ip'] ?? '',
                'MX', 'NS', 'CNAME', 'PTR' => $record['target'] ?? '',
                'TXT' => is_array($record['txt'] ?? null) ? implode(' ', $record['txt']) : ($record['txt'] ?? ''),
                default => serialize($record),
            };

            $hash = md5($type . $host . $value);
            $exists = \DB::table('dns_records')
                ->where('monitor_id', $monitorId)
                ->where('hash', $hash)
                ->exists();

            if (!$exists && \DB::table('dns_records')->where('monitor_id', $monitorId)->exists()) {
                $changes[] = [
                    'type' => $type,
                    'host' => $host,
                    'value' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * AI Risk Scoring Logic.
     */
    public function calculateSecurityScore($data)
    {
        $score = 100;
        $findings = [];

        // SSL Audit (-15 if missing)
        if (isset($data['ssl_audit']['error'])) {
            $score -= 15;
            $findings[] = 'Missing or invalid SSL certificate.';
        }

        // Security Headers (-10 for each missing critical)
        $headers = $data['fingerprint']['security'] ?? [];
        if (!($headers['hsts'] ?? false)) { $score -= 10; $findings[] = 'HSTS policy not enabled.'; }
        if (!($headers['csp'] ?? false)) { $score -= 10; $findings[] = 'Content Security Policy (CSP) missing.'; }
        if (!($headers['x_frame'] ?? false)) { $score -= 5; $findings[] = 'Missing X-Frame-Options (Clickjacking risk).'; }

        // Port Exposure (-15 if critical ports are open)
        $ports = $data['ports'] ?? [];
        if (count($ports) > 2) { $score -= 15; $findings[] = 'High number of exposed network services.'; }

        // Data Leaks (-25 for any critical leak)
        if (count($data['vulnerabilities'] ?? []) > 0) {
            $score -= 25;
            $findings[] = 'CRITICAL: Potential configuration or credential leaks detected.';
        }

        $score = max(0, $score);
        $grade = 'F';
        if ($score >= 90) $grade = 'A+';
        elseif ($score >= 80) $grade = 'A';
        elseif ($score >= 70) $grade = 'B';
        elseif ($score >= 60) $grade = 'C';
        elseif ($score >= 50) $grade = 'D';

        return ['score' => $score, 'grade' => $grade, 'findings' => $findings];
    }

    public function getIpMetadata($ip)
    {
        try {
            // Updated to include lat/lon for mapping
            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,as,org,lat,lon");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) { return null; }
    }

    public function auditSsl($domain)
    {
        try {
            $context = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
            $client = @stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 2, STREAM_CLIENT_CONNECT, $context);
            if (!$client) return ['error' => 'Connection failed'];
            $params = stream_context_get_params($client);
            $cert = openssl_x509_parse($params["options"]["ssl"]["peer_certificate"]);
            return [
                'issuer' => $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unknown',
                'valid_to' => date('Y-m-d', $cert['validTo_time_t']),
                'algorithm' => $cert['signatureTypeLN'] ?? 'Unknown',
            ];
        } catch (\Exception $e) { return ['error' => 'No SSL info']; }
    }

    public function auditCookies($url)
    {
        if (!str_starts_with($url, 'http')) $url = "https://" . $url;
        try {
            $response = Http::timeout(2)->get($url);
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
        $baselineUrl = $url . '/this-path-not-exist-' . rand(1000, 9999);
        $baselineBody = ''; $baselineStatus = 404;
        try {
            $baselineResponse = Http::timeout(1)->withoutVerifying()->get($baselineUrl);
            $baselineStatus = $baselineResponse->status(); $baselineBody = $baselineResponse->body();
        } catch (\Exception $e) {}

        $paths = ['/.env' => 'Environment Leak', '/.git/config' => 'Git Repository', '/phpinfo.php' => 'Info Leak', '/admin' => 'Admin Panel'];
        $discovered = []; $activity = [];
        foreach ($paths as $path => $desc) {
            try {
                $response = Http::timeout(0.5)->withoutVerifying()->get($url . $path);
                $isFalse = ($response->successful() && $baselineStatus === 200 && $response->body() === $baselineBody);
                $activity[] = ['path' => $path, 'status' => $response->status(), 'result' => $isFalse ? 'Filtered' : ($response->successful() ? 'EXPOSED' : 'Secure'), 'severity' => $isFalse ? 'info' : ($response->successful() ? 'critical' : 'success')];
                if ($response->successful() && !$isFalse) { $discovered[] = ['path' => $path, 'description' => $desc, 'status' => $response->status(), 'severity' => 'CRITICAL']; }
                elseif ($response->status() === 403) { $discovered[] = ['path' => $path, 'description' => $desc, 'status' => $response->status(), 'severity' => 'WARNING']; }
            } catch (\Exception $e) { $activity[] = ['path' => $path, 'status' => 'Timeout', 'result' => 'Skipped', 'severity' => 'info']; }
        }
        return ['discovered' => $discovered, 'activity' => $activity];
    }

    public function getSeoIntelligence($url)
    {
        if (!str_starts_with($url, 'http')) $url = "https://" . $url;
        try {
            $response = Http::timeout(3)->get($url);
            $html = $response->body();
            preg_match("/<title>(.*)<\/title>/i", $html, $matches); $title = $matches[1] ?? 'No Title';
            preg_match('/<meta name="description" content="(.*)"/i', $html, $matches); $desc = $matches[1] ?? 'No Description';
            return ['title' => $title, 'description' => $desc, 'robots' => str_contains($html, 'robots'), 'sitemap' => str_contains($html, 'sitemap')];
        } catch (\Exception $e) { return null; }
    }

    public function scanPorts($ip)
    {
        $ports = [22 => 'SSH', 80 => 'HTTP', 443 => 'HTTPS', 3306 => 'MySQL', 3389 => 'RDP'];
        $results = [];
        foreach ($ports as $port => $name) {
            $connection = @fsockopen($ip, $port, $errno, $errstr, 0.2);
            if (is_resource($connection)) { $results[] = ['port' => $port, 'service' => $name]; fclose($connection); }
        }
        return $results;
    }

    public function fingerprint($url)
    {
        if (!str_starts_with($url, 'http')) $url = "https://" . $url;
        try {
            $response = Http::timeout(2)->withoutVerifying()->get($url);
            $headers = $response->headers();
            return ['server' => $headers['Server'][0] ?? 'Unknown', 'cms' => 'Custom', 'security' => ['hsts' => isset($headers['Strict-Transport-Security']), 'csp' => isset($headers['Content-Security-Policy']), 'x_frame' => isset($headers['X-Frame-Options'])]];
        } catch (\Exception $e) { return ['error' => 'Timeout']; }
    }

    public function discoverSubdomains($domain)
    {
        if (filter_var($domain, FILTER_VALIDATE_IP)) return [];
        try {
            $response = Http::timeout(5)->get("https://crt.sh/?q=%.{$domain}&output=json");
            if ($response->successful()) {
                $subs = []; foreach (array_slice($response->json(), 0, 10) as $cert) {
                    $names = explode("\n", $cert['common_name'] ?? '');
                    foreach ($names as $name) { $name = strtolower(trim($name)); if (str_ends_with($name, $domain) && $name !== $domain) $subs[] = $name; }
                }
                return array_unique($subs);
            }
        } catch (\Exception $e) {}
        return [];
    }

    public function checkReputation($ip)
    {
        try { $response = Http::timeout(1)->get("https://api.hackertarget.com/aslookup/?q={$ip}"); return $response->successful() ? $response->body() : 'Clean'; }
        catch (\Exception $e) { return 'Unknown'; }
    }

    private function cleanDomain($url) { $domain = str_replace(['http://', 'https://'], '', $url); return explode('/', $domain)[0]; }
}
