<?php

namespace App\Http\Controllers;

use App\Models\CheckResult;
use App\Models\DatabaseCheck;
use App\Models\Monitor;
use App\Models\WebshellScan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class IncidentController extends Controller
{
    public function index()
    {
        $incidents = collect()
            ->merge($this->websiteIncidents())
            ->merge($this->sslIncidents())
            ->merge($this->webshellIncidents())
            ->merge($this->databaseIncidents())
            ->sortByDesc('occurred_at')
            ->values()
            ->take(100);

        $summary = [
            'total' => $incidents->count(),
            'critical' => $incidents->where('severity', 'critical')->count(),
            'warning' => $incidents->where('severity', 'warning')->count(),
            'info' => $incidents->where('severity', 'info')->count(),
        ];

        return view('incidents.index', compact('incidents', 'summary'));
    }

    private function websiteIncidents(): Collection
    {
        $query = CheckResult::query()
            ->with('monitor')
            ->where('is_up', false)
            ->whereHas('monitor', fn ($q) => $this->scopeMonitorQuery($q));

        // If the maintenance columns aren't present (migration not run), skip the maintenance filter.
        if (Schema::hasColumn('monitors', 'maintenance_starts_at')) {
            $query->whereHas('monitor', function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('maintenance_starts_at')
                        ->orWhere('maintenance_starts_at', '>', now())
                        ->orWhere(function ($q) {
                            $q->whereNotNull('maintenance_ends_at')
                                ->where('maintenance_ends_at', '<=', now());
                        });
                });
            });
        }

        return $query->latest('checked_at')
            ->limit(50)
            ->get()
            ->map(fn (CheckResult $result) => [
                'type' => 'Website',
                'severity' => 'critical',
                'title' => 'Website check failed',
                'subject' => $result->monitor?->name ?? 'Deleted monitor',
                'detail' => trim(($result->monitor?->url ?? '').' '.($result->status_code ? 'HTTP '.$result->status_code : 'No response')),
                'occurred_at' => $result->checked_at,
                'status' => 'Down',
            ]);
    }

    private function sslIncidents(): Collection
    {
        return Monitor::query()
            ->whereRaw('LOWER(url) LIKE ?', ['https://%'])
            ->tap(fn ($query) => $this->scopeMonitorQuery($query))
            ->get()
            ->flatMap(function (Monitor $monitor) {
                $events = collect();

                if ($monitor->ssl_last_error && !$monitor->ssl_expires_at) {
                    $events->push([
                        'type' => 'SSL',
                        'severity' => 'warning',
                        'title' => 'SSL certificate pending',
                        'subject' => $monitor->name,
                        'detail' => $monitor->ssl_last_error,
                        'occurred_at' => $monitor->updated_at,
                        'status' => 'Pending',
                    ]);
                }

                if ($monitor->ssl_expires_at) {
                    $daysLeft = (int) floor(now()->diffInDays($monitor->ssl_expires_at, false));
                    $threshold = (int) ($monitor->ssl_alert_threshold_days ?? 60);

                    if ($daysLeft < 0 || $daysLeft <= $threshold) {
                        $events->push([
                            'type' => 'SSL',
                            'severity' => $daysLeft < 0 ? 'critical' : 'warning',
                            'title' => $daysLeft < 0 ? 'SSL certificate expired' : 'SSL certificate expiring',
                            'subject' => $monitor->name,
                            'detail' => ($daysLeft < 0 ? abs($daysLeft).' days overdue' : $daysLeft.' days left').' · '.$monitor->url,
                            'occurred_at' => $monitor->ssl_expires_at,
                            'status' => $daysLeft < 0 ? 'Expired' : 'Expiring',
                        ]);
                    }
                }

                return $events;
            });
    }

    private function webshellIncidents(): Collection
    {
        return WebshellScan::query()
            ->whereIn('status', ['suspicious', 'failed'])
            ->latest('scanned_at')
            ->limit(50)
            ->get()
            ->map(fn (WebshellScan $scan) => [
                'type' => 'Webshell',
                'severity' => $scan->status === 'suspicious' ? 'critical' : 'warning',
                'title' => $scan->status === 'suspicious' ? 'Suspicious webshell signatures found' : 'Webshell scan failed',
                'subject' => $scan->target ?? 'Configured scan path',
                'detail' => $scan->error ?: count($scan->findings ?? []).' finding(s) across '.$scan->scanned_files.' file(s)',
                'occurred_at' => $scan->scanned_at,
                'status' => ucfirst($scan->status),
            ]);
    }

    private function databaseIncidents(): Collection
    {
        if (! auth()->user()->can('module.database_monitoring')) {
            return collect();
        }

        return DatabaseCheck::query()
            ->with('databaseMonitor')
            ->where('is_up', false)
            ->latest('checked_at')
            ->limit(50)
            ->get()
            ->map(fn (DatabaseCheck $check) => [
                'type' => 'Database',
                'severity' => 'critical',
                'title' => 'Database check failed',
                'subject' => $check->databaseMonitor?->name ?? 'Deleted database monitor',
                'detail' => $check->error ?: 'Connection failed',
                'occurred_at' => $check->checked_at,
                'status' => 'Down',
            ]);
    }

    private function scopeMonitorQuery($query): void
    {
        if (! auth()->user()->hasRole('Super Admin')) {
            $query->where('user_id', auth()->id());
        }
    }
}
