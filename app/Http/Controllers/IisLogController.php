<?php

namespace App\Http\Controllers;

use App\Models\IisLogSummary;
use App\Models\IisSuspiciousEvent;
use App\Models\Server;
use Illuminate\View\View;

class IisLogController extends Controller
{
    public function index(): View
    {
        $servers = Server::query()
            ->where('is_active', true)
            ->with('latestIisLogSummary')
            ->orderBy('name')
            ->get()
            ->map(function (Server $server) {
                $latest = $server->latestIisLogSummary;

                return [
                    'server' => $server,
                    'latest' => $latest,
                ];
            });

        return view('iis-logs.index', compact('servers'));
    }

    public function show(Server $server): View
    {
        $summaries = IisLogSummary::query()
            ->where('server_id', $server->id)
            ->latest('window_start')
            ->limit(48)
            ->get()
            ->sortBy('window_start')
            ->values();

        $latest = $summaries->last();
        $events = IisSuspiciousEvent::query()
            ->where('server_id', $server->id)
            ->latest('event_timestamp')
            ->latest()
            ->limit(50)
            ->get();

        $trend = [
            'labels' => $summaries->map(fn (IisLogSummary $summary) => ($summary->window_start ?? $summary->created_at)->format('H:i'))->values(),
            'requests' => $summaries->pluck('total_requests')->values(),
            'http_404' => $summaries->pluck('http_404')->values(),
            'http_500' => $summaries->pluck('http_500')->values(),
            'suspicious' => $summaries->pluck('suspicious_count')->values(),
        ];

        return view('iis-logs.show', compact('server', 'summaries', 'latest', 'events', 'trend'));
    }
}
