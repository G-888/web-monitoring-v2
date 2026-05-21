<?php

namespace App\Http\Controllers;

use App\Jobs\CheckNetworkMonitor;
use App\Models\Application;
use App\Models\NetworkMonitor;
use App\Models\Server;
use App\Models\ServerPortBaseline;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NetworkMonitorController extends Controller
{
    public function index(): View
    {
        return view('network-monitors.index', [
            'networkMonitors' => NetworkMonitor::query()
                ->with(['application', 'sourceServer', 'targetServer', 'latestResult'])
                ->latest()
                ->get(),
            'servers' => Server::query()->orderBy('name')->get(['id', 'name', 'server_id', 'ip_address']),
            'portBaselines' => ServerPortBaseline::query()->with('server')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('network-monitors.create', [
            'networkMonitor' => new NetworkMonitor([
                'type' => NetworkMonitor::TYPE_TCP_PORT,
                'source_type' => NetworkMonitor::SOURCE_CENTRAL,
                'expected_state' => 'open',
                'dns_record_type' => 'A',
                'timeout_ms' => 3000,
                'interval_seconds' => 300,
                'alert_cooldown_seconds' => 900,
                'is_active' => false,
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $monitor = NetworkMonitor::create($this->validated($request));

        $auditLogger->log('network_monitor_created', $monitor, [
            'name' => $monitor->name,
            'type' => $monitor->type,
            'target_host' => $monitor->target_host,
            'target_port' => $monitor->target_port,
        ], $request);

        if ($monitor->is_active && $monitor->source_type === NetworkMonitor::SOURCE_CENTRAL) {
            CheckNetworkMonitor::dispatch($monitor, true);
        }

        return redirect()->route('network-monitors.index')
            ->with('success', 'Network monitor created.');
    }

    public function show(NetworkMonitor $networkMonitor): View
    {
        return view('network-monitors.show', [
            'networkMonitor' => $networkMonitor->load(['application', 'sourceServer', 'targetServer']),
            'results' => $networkMonitor->results()->latest('checked_at')->limit(100)->get(),
        ]);
    }

    public function map(): View
    {
        return view('network-monitors.map', [
            'networkMonitors' => NetworkMonitor::query()
                ->with(['application', 'sourceServer', 'targetServer', 'latestResult'])
                ->orderBy('application_id')
                ->orderBy('name')
                ->get()
                ->groupBy(fn (NetworkMonitor $monitor) => $monitor->application?->name ?? 'Unmapped Dependencies'),
        ]);
    }

    public function edit(NetworkMonitor $networkMonitor): View
    {
        return view('network-monitors.edit', [
            'networkMonitor' => $networkMonitor,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, NetworkMonitor $networkMonitor, AuditLogger $auditLogger): RedirectResponse
    {
        $networkMonitor->update($this->validated($request));

        $auditLogger->log('network_monitor_updated', $networkMonitor, [
            'name' => $networkMonitor->name,
            'type' => $networkMonitor->type,
            'target_host' => $networkMonitor->target_host,
            'target_port' => $networkMonitor->target_port,
        ], $request);

        return redirect()->route('network-monitors.index')
            ->with('success', 'Network monitor updated.');
    }

    public function destroy(Request $request, NetworkMonitor $networkMonitor, AuditLogger $auditLogger): RedirectResponse
    {
        $auditLogger->log('network_monitor_deleted', $networkMonitor, [
            'name' => $networkMonitor->name,
            'type' => $networkMonitor->type,
            'target_host' => $networkMonitor->target_host,
            'target_port' => $networkMonitor->target_port,
        ], $request);

        $networkMonitor->delete();

        return redirect()->route('network-monitors.index')
            ->with('success', 'Network monitor deleted.');
    }

    public function check(NetworkMonitor $networkMonitor): RedirectResponse
    {
        abort_unless($networkMonitor->source_type === NetworkMonitor::SOURCE_CENTRAL, 422, 'Agent sourced checks run from the configured server.');

        CheckNetworkMonitor::dispatch($networkMonitor, true);

        return back()->with('success', 'Network check queued.');
    }

    private function formOptions(): array
    {
        return [
            'applications' => Application::query()->orderBy('name')->get(['id', 'name', 'environment']),
            'servers' => Server::query()->orderBy('name')->get(['id', 'name', 'server_id', 'ip_address']),
        ];
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:tcp_port,ping,dns'],
            'source_type' => ['required', 'in:central,agent'],
            'source_server_id' => ['nullable', 'required_if:source_type,agent', 'exists:servers,id'],
            'target_server_id' => ['nullable', 'exists:servers,id'],
            'application_id' => ['nullable', 'exists:applications,id'],
            'dependency_type' => ['nullable', 'in:'.implode(',', NetworkMonitor::DEPENDENCY_TYPES)],
            'protocol' => ['required', 'in:tcp,udp,icmp,dns'],
            'target_host' => ['required', 'string', 'max:255'],
            'target_port' => ['nullable', 'required_if:type,tcp_port', 'integer', 'min:1', 'max:65535'],
            'dns_record_type' => ['nullable', 'in:A,AAAA,CNAME,MX,NS,TXT'],
            'expected_value' => ['nullable', 'string', 'max:2000'],
            'expected_state' => ['required', 'in:open,closed'],
            'timeout_ms' => ['required', 'integer', 'min:200', 'max:30000'],
            'latency_threshold_ms' => ['nullable', 'integer', 'min:1', 'max:300000'],
            'interval_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'alert_cooldown_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'maintenance_starts_at' => ['nullable', 'date'],
            'maintenance_ends_at' => ['nullable', 'date', 'after_or_equal:maintenance_starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if ($validated['type'] !== NetworkMonitor::TYPE_TCP_PORT) {
            $validated['target_port'] = null;
        }

        if ($validated['type'] !== NetworkMonitor::TYPE_DNS) {
            $validated['dns_record_type'] = null;
        } else {
            $validated['dns_record_type'] = $validated['dns_record_type'] ?: 'A';
            $validated['dependency_type'] = $validated['dependency_type'] ?: 'dns';
            $validated['protocol'] = 'dns';
        }

        return $validated;
    }
}
