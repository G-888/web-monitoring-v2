<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    public function index()
    {
        $servers = Server::with([
            'latestMetric',
            'windowsServices' => fn ($query) => $query->where('is_monitored', true),
        ])
            ->latest('created_at')
            ->get();

        return view('servers.index', compact('servers'));
    }

    public function create()
    {
        return view('servers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'string', 'max:255', 'unique:servers,server_id'],
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'os' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
            'alerts_enabled' => ['nullable', 'boolean'],
            'cpu_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'ram_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'disk_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'offline_threshold_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'alert_cooldown_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['alerts_enabled'] = $request->boolean('alerts_enabled', true);

        Server::create($validated);

        return redirect()->route('servers.index')
            ->with('success', 'Server inventory entry created successfully.');
    }

    public function edit(Server $server)
    {
        return view('servers.edit', compact('server'));
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'string', 'max:255', Rule::unique('servers', 'server_id')->ignore($server->id)],
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'os' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
            'alerts_enabled' => ['nullable', 'boolean'],
            'cpu_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'ram_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'disk_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'offline_threshold_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'alert_cooldown_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['alerts_enabled'] = $request->boolean('alerts_enabled', true);

        $server->update($validated);

        return redirect()->route('servers.index')
            ->with('success', 'Server inventory updated successfully.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();

        return redirect()->route('servers.index')
            ->with('success', 'Server removed from inventory.');
    }
}
