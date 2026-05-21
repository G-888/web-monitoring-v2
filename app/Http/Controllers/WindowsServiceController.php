<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\WindowsService;
use App\Models\WindowsServiceCommand;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WindowsServiceController extends Controller
{
    public function index(): View
    {
        $servers = Server::with([
            'windowsServices' => fn ($query) => $query->where('is_monitored', true)->orderBy('service_name'),
            'windowsServiceCommands' => fn ($query) => $query->latest()->limit(10),
        ])->latest('created_at')->get();

        return view('servers.windows-services', compact('servers'));
    }

    public function store(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'service_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $server->windowsServices()->updateOrCreate(
            ['service_name' => $validated['service_name']],
            [
                'display_name' => $validated['display_name'] ?: $validated['service_name'],
                'is_monitored' => true,
            ]
        );

        return back()->with('success', 'Windows service added to monitoring.');
    }

    public function command(Request $request, WindowsService $windowsService, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()?->can('module.service_control'), 403);

        $validated = $request->validate([
            'action' => ['required', 'in:start,stop,restart'],
        ]);

        $command = WindowsServiceCommand::create([
            'server_id' => $windowsService->server_id,
            'windows_service_id' => $windowsService->id,
            'requested_by' => $request->user()?->id,
            'request_ip' => $request->ip(),
            'service_name' => $windowsService->service_name,
            'action' => $validated['action'],
        ]);

        $auditLogger->log('service_control_action', $command, [
            'server_id' => $windowsService->server_id,
            'service_name' => $windowsService->service_name,
            'action' => $validated['action'],
        ], $request);

        return back()->with('success', ucfirst($validated['action']) . ' command queued for ' . $windowsService->service_name . '.');
    }

    public function destroy(WindowsService $windowsService): RedirectResponse
    {
        $windowsService->forceFill(['is_monitored' => false])->save();

        return back()->with('success', 'Windows service removed from monitoring.');
    }
}
