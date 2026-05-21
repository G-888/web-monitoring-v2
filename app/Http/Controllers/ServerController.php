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
            ->orderBy('group')
            ->orderBy('name')
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
            'server_type' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'os' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
            'alerts_enabled' => ['nullable', 'boolean'],
            'cpu_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'ram_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'disk_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'offline_threshold_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'alert_cooldown_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'maintenance_starts_at' => ['nullable', 'date'],
            'maintenance_ends_at' => ['nullable', 'date', 'after_or_equal:maintenance_starts_at'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['alerts_enabled'] = $request->boolean('alerts_enabled', true);
        $validated['tags'] = $this->parseTags($validated['tags'] ?? null);

        Server::create($validated);

        return redirect()->route('servers.index')
            ->with('success', 'Server inventory entry created successfully.');
    }

    public function edit(Server $server)
    {
        $server->load([
            'applications',
            'windowsServices' => fn ($query) => $query->where('is_monitored', true),
        ]);
        return view('servers.edit', compact('server'));
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'string', 'max:255', Rule::unique('servers', 'server_id')->ignore($server->id)],
            'name' => ['required', 'string', 'max:255'],
            'server_type' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'os' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
            'alerts_enabled' => ['nullable', 'boolean'],
            'cpu_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'ram_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'disk_threshold' => ['nullable', 'numeric', 'between:0,100'],
            'offline_threshold_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'alert_cooldown_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'maintenance_starts_at' => ['nullable', 'date'],
            'maintenance_ends_at' => ['nullable', 'date', 'after_or_equal:maintenance_starts_at'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['alerts_enabled'] = $request->boolean('alerts_enabled', true);
        $validated['tags'] = $this->parseTags($validated['tags'] ?? null);

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

    private function parseTags(?string $tags): array
    {
        return collect(explode(',', (string) $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
