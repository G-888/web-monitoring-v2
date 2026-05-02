<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\RipgrepScanner;

class ServerLogScannerController extends Controller
{
    public function index()
    {
        // Must be a Super Admin to access
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403);
        }

        return view('log-scanner.index');
    }

    public function scan(Request $request, RipgrepScanner $scanner)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403);
        }

        $request->validate([
            'pattern' => 'required|string|min:2',
            'directory' => 'nullable|string',
        ]);

        $directory = $request->directory ?: storage_path('logs');
        $pattern = $request->pattern;

        try {
            $results = $scanner->scan($pattern, $directory);
            return response()->json(['success' => true, 'results' => $results]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error("Scanner error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
