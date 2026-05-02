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
        $url = $request->input('url');
        $domain = parse_url($url, PHP_URL_HOST) ?: $url;
        
        $records = $service->scanDns($domain);
        $subdomains = $service->discoverSubdomains($domain);

        return back()->with([
            'manual_asset_result' => [
                'dns' => $records,
                'subdomains' => $subdomains,
                'domain' => $domain
            ]
        ]);
    }
}
