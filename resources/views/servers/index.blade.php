<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Server Inventory</h2>
    </x-slot>

    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-slate-400">Manage monitored servers, heartbeat status, resources, and service health.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('servers.create') }}" class="inline-flex items-center justify-center rounded-xl bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/10 transition hover:bg-orange-500">
                    Add Server
                </a>
                <a href="{{ route('servers.windows-services') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                    Windows Services
                </a>
            </div>
        </div>

        @php
            $groupedServers = $servers->groupBy(fn($server) => $server->group ?: 'Ungrouped');
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

        @if($servers->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($groupedServers as $groupName => $groupServers)
                    @php
                        $onlineCount = $groupServers->filter(fn($server) => $server->last_heartbeat_at && $server->last_heartbeat_at->gt(now()->subSeconds($server->offline_threshold_seconds ?? 15)))->count();
                    @endphp
                    <div class="rounded-xl border border-slate-200/70 bg-white/80 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ $groupName }}</h3>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ $groupServers->count() }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $onlineCount }} online / {{ $groupServers->count() - $onlineCount }} offline</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if($geoServers->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div id="server-map" class="h-96 w-full"></div>
                <div id="server-detail" class="hidden border-t border-slate-200/70 bg-slate-50 p-5 dark:border-white/10 dark:bg-slate-950">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Server selected</p>
                            <h3 id="server-detail-name" class="mt-2 text-xl font-semibold text-slate-900 dark:text-white"></h3>
                        </div>
                        <a id="server-detail-edit" href="#" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">Edit Server</a>
                    </div>
                    <div class="grid gap-4 pt-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200/70 bg-white p-4 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Location</p>
                            <p id="server-detail-location" class="mt-2 font-medium"></p>
                        </div>
                        <div class="rounded-lg border border-slate-200/70 bg-white p-4 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Coordinates</p>
                            <p id="server-detail-coords" class="mt-2 font-medium"></p>
                        </div>
                        <div class="rounded-lg border border-slate-200/70 bg-white p-4 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Status</p>
                            <p id="server-detail-status" class="mt-2 font-medium"></p>
                        </div>
                        <div class="rounded-lg border border-slate-200/70 bg-white p-4 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Last heartbeat</p>
                            <p id="server-detail-heartbeat" class="mt-2 font-medium"></p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="space-y-6">
            @forelse($groupedServers as $groupName => $groupServers)
                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ $groupName }}</h3>
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ $groupServers->count() }} {{ \Illuminate\Support\Str::plural('server', $groupServers->count()) }}</span>
                    </div>

                    <div class="space-y-4">
                        @foreach($groupServers as $server)
                            @php
                                $lastMetric = $server->latestMetric;
                                $offlineThreshold = $server->offline_threshold_seconds ?? 15;
                                $isOnline = $server->last_heartbeat_at && $server->last_heartbeat_at->gt(now()->subSeconds($offlineThreshold));
                                $isUnderMaintenance = $server->isUnderMaintenance();
                                $ramPercent = $lastMetric && (float) $lastMetric->ram_total > 0
                                    ? ((float) $lastMetric->ram_used / (float) $lastMetric->ram_total) * 100
                                    : null;
                                $diskPercent = $lastMetric && (float) $lastMetric->disk_total > 0
                                    ? ((float) $lastMetric->disk_used / (float) $lastMetric->disk_total) * 100
                                    : null;
                                $stoppedServices = $server->windowsServices->filter(fn($service) => strtolower((string) $service->status) !== 'running');
                            @endphp

                            <article class="rounded-xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $server->name }}</h4>
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $isUnderMaintenance ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200' : ($isOnline ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400') }}">
                                                {{ $isUnderMaintenance ? 'Maintenance' : ($isOnline ? 'Online' : 'Offline') }}
                                            </span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600 dark:text-slate-300">
                                            <span>ID: <span class="font-medium text-slate-900 dark:text-white">{{ $server->server_id }}</span></span>
                                            <span>IP: <span class="font-medium text-slate-900 dark:text-white">{{ $server->ip_address ?? 'Not set' }}</span></span>
                                            <span>OS: <span class="font-medium text-slate-900 dark:text-white">{{ $server->os ?? 'Not set' }}</span></span>
                                            <span>Agent: <span class="font-medium text-slate-900 dark:text-white">{{ $server->agent_version ?? 'Not reported' }}</span></span>
                                            <span>Location: <span class="font-medium text-slate-900 dark:text-white">{{ $server->location ?? 'Not set' }}</span></span>
                                        </div>
                                        @if(! empty($server->tags))
                                            <div class="mt-3 flex flex-wrap gap-1.5">
                                                @foreach($server->tags as $tag)
                                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-200">{{ $tag }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('servers.edit', $server) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('servers.destroy', $server) }}" onsubmit="return confirm('Delete server inventory entry?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-x-6 gap-y-5 border-t border-slate-200/70 pt-5 dark:border-white/10 lg:grid-cols-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Metrics</p>
                                        @if($lastMetric)
                                            <div class="mt-3 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                                <div>CPU {{ number_format((float) $lastMetric->cpu, 1) }}%</div>
                                                <div>RAM {{ $ramPercent !== null ? number_format($ramPercent, 1) . '%' : 'n/a' }}</div>
                                                <div>Disk {{ $diskPercent !== null ? number_format($diskPercent, 1) . '%' : 'n/a' }}</div>
                                            </div>
                                        @else
                                            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No metrics yet</p>
                                        @endif
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Thresholds</p>
                                        <div class="mt-3 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                            @if($server->alerts_enabled)
                                                <div>CPU {{ $server->cpu_threshold !== null ? number_format((float) $server->cpu_threshold, 1) . '%' : 'off' }}</div>
                                                <div>RAM {{ $server->ram_threshold !== null ? number_format((float) $server->ram_threshold, 1) . '%' : 'off' }}</div>
                                                <div>Disk {{ $server->disk_threshold !== null ? number_format((float) $server->disk_threshold, 1) . '%' : 'off' }}</div>
                                                <div>Offline after {{ $offlineThreshold }}s</div>
                                            @else
                                                <div>Alerts off</div>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Windows Services</p>
                                        @if($server->windowsServices->isEmpty())
                                            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Not reported</p>
                                        @else
                                            <details class="mt-3">
                                                <summary class="cursor-pointer text-sm font-semibold {{ $stoppedServices->isEmpty() ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300' }}">
                                                    {{ $server->windowsServices->count() - $stoppedServices->count() }}/{{ $server->windowsServices->count() }} running
                                                </summary>
                                                <div class="mt-3 space-y-2">
                                                    @foreach($server->windowsServices->sortBy('service_name') as $service)
                                                        @php
                                                            $serviceRunning = strtolower((string) $service->status) === 'running';
                                                        @endphp
                                                        <div class="border-t border-slate-200/70 pt-2 text-xs dark:border-white/10">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <span class="font-semibold text-slate-900 dark:text-white">{{ $service->service_name }}</span>
                                                                <span class="{{ $serviceRunning ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300' }}">{{ $service->status ?? 'Unknown' }}</span>
                                                            </div>
                                                            <div class="mt-1 text-slate-500 dark:text-slate-400">{{ $service->display_name ?? 'No display name' }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Heartbeat</p>
                                        <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">{{ $server->last_heartbeat_at ? $server->last_heartbeat_at->diffForHumans() : 'No heartbeat yet' }}</p>
                                        @if($server->latitude && $server->longitude)
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $server->latitude }}, {{ $server->longitude }}</p>
                                        @endif
                                    </div>
                                </div>

                                <details class="mt-5 border-t border-slate-200/70 pt-4 dark:border-white/10">
                                    <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        Agent install settings
                                    </summary>
                                    <div class="mt-3">
                                        <pre class="overflow-x-auto rounded-lg bg-slate-950 p-4 text-xs leading-relaxed text-slate-100">{
  "serverId": "{{ $server->server_id }}",
  "apiUrl": "{{ url('/api/metrics') }}",
  "apiKey": "&lt;AGENT_API_KEY&gt;",
  "intervalSeconds": 5,
  "autoDiscoverWindowsServices": true
}</pre>
                                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Put this config next to the packaged agent, then run the scheduled task installer from Administrator PowerShell.</p>
                                    </div>
                                </details>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200/70 bg-white/70 p-8 text-center text-slate-500 dark:border-white/10 dark:bg-slate-900/50 dark:text-slate-400">
                    No servers in inventory yet. Add one to begin tracking heartbeats.
                </div>
            @endforelse
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
