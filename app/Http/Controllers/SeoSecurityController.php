<?php

namespace App\Http\Controllers;

use App\Models\SeoScan;
use App\Models\SeoDiscoveredPage;
use App\Models\FileIntegrityHash;
use Illuminate\Http\Request;

class SeoSecurityController extends Controller
{
    public function index()
    {
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

        return view('seo.index', compact('recentScans', 'suspiciousScans', 'discoveredPages', 'fileChanges'));
    }
}
