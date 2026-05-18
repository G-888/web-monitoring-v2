<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Server Inventory</h2>
    </x-slot>

    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-slate-400">Manage the list of monitored servers and review their heartbeat status.</p>
            </div>
            <a href="{{ route('servers.create') }}" class="inline-flex items-center justify-center rounded-xl bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/10 hover:bg-orange-500 transition">
                Add Server
            </a>
            <a href="{{ route('servers.windows-services') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                Windows Services
            </a>
        </div>

        @php
            $geoServers = $servers->filter(fn($server) => $server->latitude !== null && $server->longitude !== null);
            $geoServerMap = $geoServers->map(fn($server) => [
                'name' => $server->name,
                'location' => $server->location,
                'latitude' => $server->latitude,
                'longitude' => $server->longitude,
                'status' => $server->last_heartbeat_at && $server->last_heartbeat_at->gt(now()->subSeconds($server->offline_threshold_seconds ?? 15)) ? 'Online' : 'Offline',
                'last_heartbeat' => $server->last_heartbeat_at ? $server->last_heartbeat_at->diffForHumans() : 'No heartbeat yet',
                'edit_url' => route('servers.edit', $server),
            ])->values();
        @endphp

        @if($geoServers->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div id="server-map" class="h-96 w-full"></div>
                <div id="server-detail" class="hidden border-t border-slate-200/70 bg-slate-50 p-5 dark:border-white/10 dark:bg-slate-950">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Server selected</p>
                            <h3 id="server-detail-name" class="mt-2 text-xl font-semibold text-slate-900 dark:text-white"></h3>
                        </div>
                        <a id="server-detail-edit" href="#" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">Edit Server</a>
                    </div>
                    <div class="grid gap-4 pt-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200/70 bg-white p-4 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Location</p>
                            <p id="server-detail-location" class="mt-2 font-medium"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 bg-white p-4 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Coordinates</p>
                            <p id="server-detail-coords" class="mt-2 font-medium"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 bg-white p-4 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Status</p>
                            <p id="server-detail-status" class="mt-2 font-medium"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 bg-white p-4 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Last heartbeat</p>
                            <p id="server-detail-heartbeat" class="mt-2 font-medium"></p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700 dark:divide-white/10 dark:text-slate-200">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Server ID</th>
                        <th class="px-4 py-3">IP Address</th>
                        <th class="px-4 py-3">OS</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Coords</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Metrics</th>
                        <th class="px-4 py-3">Thresholds</th>
                        <th class="px-4 py-3">Windows Services</th>
                        <th class="px-4 py-3">Last Heartbeat</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                    @forelse($servers as $server)
                        @php
                            $lastMetric = $server->latestMetric;
                            $offlineThreshold = $server->offline_threshold_seconds ?? 15;
                            $isOnline = $server->last_heartbeat_at && $server->last_heartbeat_at->gt(now()->subSeconds($offlineThreshold));
                            $ramPercent = $lastMetric && (float) $lastMetric->ram_total > 0
                                ? ((float) $lastMetric->ram_used / (float) $lastMetric->ram_total) * 100
                                : null;
                            $diskPercent = $lastMetric && (float) $lastMetric->disk_total > 0
                                ? ((float) $lastMetric->disk_used / (float) $lastMetric->disk_total) * 100
                                : null;
                            $stoppedServices = $server->windowsServices->filter(fn($service) => strtolower((string) $service->status) !== 'running');
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                            <td class="px-4 py-4 font-medium text-slate-900 dark:text-white">{{ $server->name }}</td>
                            <td class="px-4 py-4">{{ $server->server_id }}</td>
                            <td class="px-4 py-4">{{ $server->ip_address ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $server->os ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $server->location ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $server->latitude && $server->longitude ? $server->latitude . ', ' . $server->longitude : '—' }}</td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $isOnline ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400' }}">
                                    {{ $isOnline ? 'Online' : 'Offline' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-500 dark:text-slate-400">
                                @if($lastMetric)
                                    <div>CPU {{ number_format((float) $lastMetric->cpu, 1) }}%</div>
                                    <div>RAM {{ $ramPercent !== null ? number_format($ramPercent, 1) . '%' : 'n/a' }}</div>
                                    <div>Disk {{ $diskPercent !== null ? number_format($diskPercent, 1) . '%' : 'n/a' }}</div>
                                @else
                                    No metrics
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-500 dark:text-slate-400">
                                @if($server->alerts_enabled)
                                    <div>CPU {{ $server->cpu_threshold !== null ? number_format((float) $server->cpu_threshold, 1) . '%' : 'off' }}</div>
                                    <div>RAM {{ $server->ram_threshold !== null ? number_format((float) $server->ram_threshold, 1) . '%' : 'off' }}</div>
                                    <div>Disk {{ $server->disk_threshold !== null ? number_format((float) $server->disk_threshold, 1) . '%' : 'off' }}</div>
                                    <div>Offline {{ $offlineThreshold }}s</div>
                                @else
                                    Alerts off
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-500 dark:text-slate-400">
                                @if($server->windowsServices->isEmpty())
                                    Not reported
                                @else
                                    <details>
                                        <summary class="cursor-pointer font-semibold {{ $stoppedServices->isEmpty() ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300' }}">
                                            {{ $server->windowsServices->count() - $stoppedServices->count() }}/{{ $server->windowsServices->count() }} running
                                        </summary>
                                        <div class="mt-3 min-w-80 overflow-hidden rounded-lg border border-slate-200 dark:border-white/10">
                                            <table class="w-full text-left text-[11px]">
                                                <thead class="bg-slate-50 text-slate-500 dark:bg-white/5 dark:text-slate-400">
                                                    <tr>
                                                        <th class="px-2 py-2">Service Name</th>
                                                        <th class="px-2 py-2">Display Name</th>
                                                        <th class="px-2 py-2">Status</th>
                                                        <th class="px-2 py-2">Startup Type</th>
                                                        <th class="px-2 py-2">Last Checked</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                                                    @foreach($server->windowsServices->sortBy('service_name') as $service)
                                                        @php
                                                            $serviceRunning = strtolower((string) $service->status) === 'running';
                                                        @endphp
                                                        <tr>
                                                            <td class="px-2 py-2 font-medium text-slate-700 dark:text-slate-200">{{ $service->service_name }}</td>
                                                            <td class="px-2 py-2">{{ $service->display_name ?? '—' }}</td>
                                                            <td class="px-2 py-2">
                                                                <span class="inline-flex rounded-full px-2 py-0.5 font-semibold {{ $serviceRunning ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200' }}">
                                                                    {{ $service->status ?? 'Unknown' }}
                                                                </span>
                                                            </td>
                                                            <td class="px-2 py-2">{{ $service->startup_type ?? '—' }}</td>
                                                            <td class="px-2 py-2">{{ $service->last_checked_at ? $service->last_checked_at->diffForHumans() : 'Never' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ $server->last_heartbeat_at ? $server->last_heartbeat_at->diffForHumans() : 'No heartbeat yet' }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('servers.edit', $server) }}" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('servers.destroy', $server) }}" onsubmit="return confirm('Delete server inventory entry?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                No servers in inventory yet. Add one to begin tracking heartbeats.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($geoServers->isNotEmpty())
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-Cx0p4QgElz6vfYt9qLR5wD7TLziifD1wnT8jcuG6u7E=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-m4K1nhOl7Ih9ER8KQPm+md4UEULTVUyhO3R8fXh3wH0=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const detailPanel = document.getElementById('server-detail');
                const detailName = document.getElementById('server-detail-name');
                const detailLocation = document.getElementById('server-detail-location');
                const detailCoords = document.getElementById('server-detail-coords');
                const detailStatus = document.getElementById('server-detail-status');
                const detailHeartbeat = document.getElementById('server-detail-heartbeat');
                const detailEdit = document.getElementById('server-detail-edit');

                const servers = @json($geoServerMap);

                const map = L.map('server-map').setView([servers[0].latitude, servers[0].longitude], 2);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                servers.forEach(server => {
                    const marker = L.marker([server.latitude, server.longitude]).addTo(map);

                    marker.on('click', () => {
                        detailName.textContent = server.name;
                        detailLocation.textContent = server.location || 'No location provided';
                        detailCoords.textContent = `${server.latitude}, ${server.longitude}`;
                        detailStatus.textContent = server.status;
                        detailHeartbeat.textContent = server.last_heartbeat;
                        detailEdit.href = server.edit_url;
                        detailPanel.classList.remove('hidden');
                        map.setView([server.latitude, server.longitude], 5, { animate: true });
                    });
                });
            });
        </script>
    @endif
</x-app-layout>
