<?php

namespace App\Http\Controllers;

use App\Models\SeoScan;
use App\Models\SeoDiscoveredPage;
use App\Models\FileIntegrityHash;
use App\Models\WebshellScan;
use App\Jobs\RunWebshellScanJob;
use App\Jobs\SeoScanJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\AuditLogger;
use App\Services\OutboundScanGuard;
use App\Services\WebshellScannerService;

class SeoSecurityController extends Controller
{
    public function index()
    {
        $monitors = \App\Models\Monitor::where('is_active', true)->get();
        $monitorIds = $monitors->pluck('id');

        $latestScanIds = SeoScan::query()
            ->whereIn('monitor_id', $monitorIds)
            ->selectRaw('MAX(id) as id')
            ->groupBy('monitor_id')
            ->pluck('id');

        $latestScansByMonitor = SeoScan::query()
            ->whereIn('id', $latestScanIds)
            ->get()
            ->keyBy('monitor_id');

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

        return view('seo.index', compact('monitors', 'latestScansByMonitor', 'recentScans', 'suspiciousScans', 'discoveredPages', 'fileChanges', 'webshellScans'));
    }

    public function scan(Request $request, \App\Services\SeoScannerService $service, OutboundScanGuard $scanGuard, AuditLogger $auditLogger)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');
        $scanGuard->assertAllowed($url);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Referer' => 'https://www.google.com/'
        ])->withoutVerifying()->get($url);

        $result = $service->scan($url);
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

        $auditLogger->log('security_scan_triggered', $scan, [
            'scan_type' => 'seo_manual',
            'url' => $url,
        ], $request);

        return back()->with('manual_scan_result', $result)->with('manual_url', $url);
    }

    public function scanAll(Request $request, OutboundScanGuard $scanGuard, AuditLogger $auditLogger)
    {
        $monitors = \App\Models\Monitor::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $queued = 0;
        $skipped = 0;

        foreach ($monitors as $monitor) {
            try {
                $scanGuard->assertAllowed($monitor->url);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $skipped++;
                continue;
            }

            SeoScanJob::dispatch($monitor)->onQueue('security');
            $queued++;
        }

        $auditLogger->log('security_scan_triggered', null, [
            'scan_type' => 'seo_scan_all',
            'queued' => $queued,
            'skipped' => $skipped,
        ], $request);

        return redirect()
            ->route('seo-security.index', ['tab' => 'seo'])
            ->with('success', $queued.' URL security scans queued.'.($skipped ? " {$skipped} skipped by outbound scan policy." : ''));
    }

    public function webshellScan(Request $request, WebshellScannerService $service, AuditLogger $auditLogger)
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

        $auditLogger->log('security_scan_triggered', null, [
            'scan_type' => 'webshell_manual',
            'target' => $result['target'] ?? ($validated['path'] ?? null),
        ], $request);

        return redirect()
            ->route('seo-security.index', ['tab' => 'webshell'])
            ->with('webshell_scan_result', $result);
    }
}
