<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function scan(Request $request, \App\Services\DnsScannerService $service)
    {
        $input = $request->input('url');
        
        // Clean input (remove http/https)
        $domain = str_replace(['http://', 'https://'], '', $input);
        $domain = explode('/', $domain)[0];

        $records = $service->scanDns($domain);
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

        return back()->with([
            'manual_asset_result' => [
                'dns' => $records,
                'subdomains' => $subdomains,
                'domain' => $domain,
                'fingerprint' => $fingerprint,
                'ssl_audit' => $sslAudit,
                'cookies' => $cookies,
                'seo_intel' => $seoIntel,
                'vulnerabilities' => $vulnData['discovered'],
                'activity_log' => $vulnData['activity'],
                'ports' => $ports,
                'is_ip' => filter_var($domain, FILTER_VALIDATE_IP)
            ]
        ]);
    }
}
