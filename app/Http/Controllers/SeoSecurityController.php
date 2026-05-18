<?php

namespace App\Http\Controllers;

use App\Models\SeoScan;
use App\Models\SeoDiscoveredPage;
use App\Models\FileIntegrityHash;
use App\Models\WebshellScan;
use App\Jobs\RunWebshellScanJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\WebshellScannerService;

class SeoSecurityController extends Controller
{
    public function index()
    {
        $monitors = \App\Models\Monitor::where('is_active', true)->get();

        $recentScans = SeoScan::with('monitor')
            ->orderBy('scanned_at', 'desc')
            ->limit(20)
            ->get();

        $suspiciousScans = SeoScan::where('status', '!=', 'clean')
            ->with('monitor')
            ->orderBy('scanned_at', 'desc')
            ->get();

        $discoveredPages = SeoDiscoveredPage::with('monitor')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $fileChanges = FileIntegrityHash::with('monitor')
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        $webshellScans = WebshellScan::query()
            ->orderBy('scanned_at', 'desc')
            ->limit(10)
            ->get();

        return view('seo.index', compact('monitors', 'recentScans', 'suspiciousScans', 'discoveredPages', 'fileChanges', 'webshellScans'));
    }

    public function scan(Request $request, \App\Services\SeoScannerService $service)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');
        
        // Fetch with raw response to show headers
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Referer' => 'https://www.google.com/'
        ])->withoutVerifying()->get($url);

        $result = $service->scan($url);
        
        // Include raw debug info
        $result['raw_headers'] = $response->headers();
        $result['raw_body'] = substr($response->body(), 0, 5000); // First 5k chars
        $result['status_code'] = $response->status();

        // We still save it as a "Manual Scan" result for history
        $scan = new SeoScan();
        $scan->monitor_id = null; // null for manual scans
        $scan->url = $url;
        $scan->status = $result['status'];
        $scan->findings = $result['findings'];
        $scan->diffs = $result['hashes'];
        $scan->scanned_at = now();
        $scan->save();

        return back()->with('manual_scan_result', $result)->with('manual_url', $url);
    }

    public function webshellScan(Request $request, WebshellScannerService $service)
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $service->scan($validated['path'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['path' => $e->getMessage()]);
        }

        RunWebshellScanJob::storeResult($result, 'manual');

        return redirect()
            ->route('seo-security.index', ['tab' => 'webshell'])
            ->with('webshell_scan_result', $result);
    }
}
