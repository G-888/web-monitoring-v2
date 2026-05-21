<x-app-layout>
    <x-slot name="header_title">Network Monitors</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Network Connectivity</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Lightweight port, DNS, and dependency checks from the central server or a Windows agent.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('network-map.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Network Map</a>
                <a href="{{ route('network-monitors.create') }}" class="inline-flex items-center justify-center rounded-xl bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">
                    Add Network Monitor
                </a>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Destination</th>
                        <th class="px-4 py-3">Port / Protocol</th>
                        <th class="px-4 py-3">Expected</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Latency</th>
                        <th class="px-4 py-3">Last Check</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                    @forelse($networkMonitors as $networkMonitor)
                        @php
                            $status = $networkMonitor->last_status ?: 'unknown';
                            $statusClass = match ($status) {
                                'up' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                'down', 'mismatch', 'unexpected_open', 'error' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200',
                                'unsupported' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
                                default => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-4 font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('network-monitors.show', $networkMonitor) }}" class="hover:text-orange-600">{{ $networkMonitor->name }}</a>
                                @if($networkMonitor->application)
                                    <div class="text-xs font-normal text-slate-500">{{ $networkMonitor->application->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                {{ $networkMonitor->sourceLabel() }}
                                <div class="text-xs text-slate-500">{{ ucfirst($networkMonitor->source_type) }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                {{ $networkMonitor->destinationLabel() }}
                                <div class="font-mono text-xs text-slate-500">{{ $networkMonitor->endpointLabel() }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $networkMonitor->target_port ?: '-' }} / {{ strtoupper($networkMonitor->protocol ?: $networkMonitor->type) }}</td>
                            <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $networkMonitor->type === 'dns' ? ($networkMonitor->expected_value ?: 'No drift') : ucfirst($networkMonitor->expected_state) }}</td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                @if($networkMonitor->application && in_array($status, ['down', 'mismatch', 'dns_drift', 'unexpected_open', 'error'], true))
                                    <div class="mt-1 text-xs font-semibold text-red-500">Affects {{ $networkMonitor->application->name }}</div>
                                @endif
                                @if($networkMonitor->last_error)
                                    <div class="mt-1 max-w-xs truncate text-xs text-red-500" title="{{ $networkMonitor->last_error }}">{{ $networkMonitor->last_error }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $networkMonitor->last_latency_ms !== null ? $networkMonitor->last_latency_ms.' ms' : 'n/a' }}</td>
                            <td class="px-4 py-4 text-slate-500">{{ $networkMonitor->last_checked_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @if($networkMonitor->source_type === 'central')
                                        <form method="POST" action="{{ route('network-monitors.check', $networkMonitor) }}">
                                            @csrf
                                            <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold dark:border-white/10">Check</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('network-monitors.edit', $networkMonitor) }}" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold dark:border-white/10">Edit</a>
                                    <form method="POST" action="{{ route('network-monitors.destroy', $networkMonitor) }}" onsubmit="return confirm('Delete this network monitor?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 dark:border-red-500/20">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-500">No network monitors yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="rounded-xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Server Port Baselines</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure only the ports you expect to be open or closed. The platform never scans a full range by default.</p>
            </div>

            <form method="POST" action="{{ route('server-port-baselines.store') }}" class="grid gap-3 lg:grid-cols-8">
                @csrf
                <select name="server_id" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
                    <option value="">Server</option>
                    @foreach($servers as $server)
                        <option value="{{ $server->id }}">{{ $server->name }}{{ $server->ip_address ? ' / '.$server->ip_address : '' }}</option>
                    @endforeach
                </select>
                <input name="label" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="Label">
                <input name="port" type="number" min="1" max="65535" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="Port" required>
                <select name="expected_state" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
                    <option value="open">Expected open</option>
                    <option value="closed">Expected closed</option>
                </select>
                <input name="scan_target" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="Optional target">
                <input name="timeout_ms" type="number" min="200" max="30000" value="3000" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
                <input type="hidden" name="protocol" value="tcp">
                <input type="hidden" name="alert_cooldown_seconds" value="900">
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 dark:border-white/10 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 bg-white text-orange-600 dark:border-white/10 dark:bg-white/5">
                    Active
                </label>
                <button class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">Save Baseline</button>
            </form>

            <form method="POST" action="{{ route('server-port-baselines.apply-template') }}" class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60 lg:grid-cols-5">
                @csrf
                <select name="server_id" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
                    <option value="">Apply template to server</option>
                    @foreach($servers as $server)
                        <option value="{{ $server->id }}">{{ $server->name }}</option>
                    @endforeach
                </select>
                <select name="template" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
                    <option value="mysql_router">MySQL Router: localhost 6446/6447 open</option>
                    <option value="mysql_db">MySQL DB: 3306 open</option>
                </select>
                <input name="scan_target" class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="Target override, default localhost">
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 dark:border-white/10 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 bg-white text-orange-600 dark:border-white/10 dark:bg-white/5">
                    Active
                </label>
                <button class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-100 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-200">Apply Template</button>
            </form>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Server</th>
                            <th class="px-4 py-3">Port</th>
                            <th class="px-4 py-3">Expected</th>
                            <th class="px-4 py-3">Last Status</th>
                            <th class="px-4 py-3">Last Check</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @forelse($portBaselines as $baseline)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $baseline->server?->name }}<div class="text-xs font-normal text-slate-500">{{ $baseline->label ?: $baseline->scan_target }}</div></td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $baseline->port }}/{{ $baseline->protocol }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ ucfirst($baseline->expected_state) }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $baseline->last_status ? ucfirst(str_replace('_', ' ', $baseline->last_status)) : 'Unknown' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $baseline->last_checked_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('server-port-baselines.check', $baseline) }}">
                                            @csrf
                                            <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold dark:border-white/10">Check</button>
                                        </form>
                                        <form method="POST" action="{{ route('server-port-baselines.destroy', $baseline) }}" onsubmit="return confirm('Delete this port baseline?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 dark:border-red-500/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-slate-500">No port baselines configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
