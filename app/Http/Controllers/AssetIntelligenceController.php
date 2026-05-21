<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\OutboundScanGuard;

class AssetIntelligenceController extends Controller
{
    public function index()
    {
        $monitors = Monitor::where('is_active', true)->get();
        
        $dnsRecords = DB::table('dns_records')
            ->orderBy('last_seen_at', 'desc')
            ->limit(50)
            ->get();

        $subdomains = DB::table('discovered_subdomains')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('assets.index', compact('monitors', 'dnsRecords', 'subdomains'));
    }

    public function scan(Request $request, \App\Services\DnsScannerService $service, OutboundScanGuard $scanGuard, AuditLogger $auditLogger)
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:255'],
        ]);

        $input = trim($validated['url']);
        
        // Clean input (remove http/https)
        $domain = str_replace(['http://', 'https://'], '', $input);
        $domain = explode('/', $domain)[0];
        $domain = trim($domain);

        if (! filter_var($domain, FILTER_VALIDATE_IP) && ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return back()->withErrors(['url' => 'Enter a valid domain name or IP address.']);
        }

        $scanGuard->assertAllowed($domain);

        $records = $service->scanDns($domain);
        $edgeSummary = $this->edgeSummary($records);
        $subdomains = $service->discoverSubdomains($domain);
        $fingerprint = $service->fingerprint($domain);
        $sslAudit = $service->auditSsl($domain);
        $cookies = $service->auditCookies($domain);
        $seoIntel = $service->getSeoIntelligence($domain);
        $vulnData = $service->checkSensitivePaths($domain);
        
        // Find the IP to scan ports
        $ip = filter_var($domain, FILTER_VALIDATE_IP) ? $domain : null;
        if (!$ip) {
            foreach($records as $r) {
                if ($r['type'] === 'A') { $ip = $r['ip']; break; }
            }
        }
        $ports = $ip ? $service->scanPorts($ip) : [];

        $vulnerabilities = $vulnData['discovered'];
        $activityLog = $vulnData['activity'];

        // AI Risk Scoring
        $scoringData = [
            'ssl_audit' => $sslAudit,
            'fingerprint' => $fingerprint,
            'ports' => $ports,
            'vulnerabilities' => $vulnerabilities
        ];
        $securityAudit = $service->calculateSecurityScore($scoringData);

        $auditLogger->log('security_scan_triggered', null, [
            'scan_type' => 'asset_intelligence',
            'domain' => $domain,
            'cdn_detected' => $edgeSummary['cdn_detected'],
        ], $request);

        return back()->with([
            'manual_asset_result' => [
                'dns' => $records,
                'subdomains' => $subdomains,
                'domain' => $domain,
                'cdn_detected' => $edgeSummary['cdn_detected'],
                'cdn_provider' => $edgeSummary['cdn_provider'],
                'edge_geo' => $edgeSummary['edge_geo'],
                'origin_geo_status' => $edgeSummary['origin_geo_status'],
                'fingerprint' => $fingerprint,
                'ssl_audit' => $sslAudit,
                'cookies' => $cookies,
                'seo_intel' => $seoIntel,
                'vulnerabilities' => $vulnerabilities,
                'activity_log' => $activityLog,
                'ports' => $ports,
                'security_audit' => $securityAudit,
                'is_ip' => filter_var($domain, FILTER_VALIDATE_IP)
            ]
        ]);
    }

    private function edgeSummary(array $records): array
    {
        $edgeGeo = null;
        $cdnProvider = null;

        foreach ($records as $record) {
            if (! isset($record['geo']) || ! is_array($record['geo'])) {
                continue;
            }

            $edgeGeo ??= $record['geo'];
            $provider = $record['geo']['isp'] ?? $record['geo']['org'] ?? $record['geo']['as'] ?? '';

            if ($this->isCdnProvider($provider)) {
                $cdnProvider = $this->normalizeCdnProvider($provider);
                break;
            }
        }

        return [
            'cdn_detected' => $cdnProvider !== null,
            'cdn_provider' => $cdnProvider,
            'edge_geo' => $edgeGeo,
            'origin_geo_status' => $cdnProvider ? 'hidden_by_cdn' : 'direct_or_unknown',
        ];
    }

    private function isCdnProvider(string $provider): bool
    {
        $provider = strtolower($provider);

        foreach (['cloudflare', 'akamai', 'fastly', 'cloudfront', 'amazon', 'google cloud', 'azure', 'imperva', 'incapsula', 'sucuri', 'stackpath', 'bunny'] as $cdn) {
            if (str_contains($provider, $cdn)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCdnProvider(string $provider): string
    {
        $provider = strtolower($provider);

        return match (true) {
            str_contains($provider, 'cloudflare') => 'Cloudflare',
            str_contains($provider, 'akamai') => 'Akamai',
            str_contains($provider, 'fastly') => 'Fastly',
            str_contains($provider, 'cloudfront'), str_contains($provider, 'amazon') => 'Amazon CloudFront',
            str_contains($provider, 'google cloud') => 'Google Cloud CDN',
            str_contains($provider, 'azure') => 'Azure CDN',
            str_contains($provider, 'imperva'), str_contains($provider, 'incapsula') => 'Imperva',
            str_contains($provider, 'sucuri') => 'Sucuri',
            str_contains($provider, 'stackpath') => 'StackPath',
            str_contains($provider, 'bunny') => 'Bunny CDN',
            default => 'CDN/WAF',
        };
    }
}
