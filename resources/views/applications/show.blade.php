<x-app-layout>
    <x-slot name="header_title">Application Detail</x-slot>

    @php
        $statusClass = match ($summary['status']) {
            'healthy' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
            'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
            default => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200',
        };
        $serverGroups = $application->servers->groupBy('id');
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $application->name }}</h2>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst($summary['status']) }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $application->code }}{{ $application->environment ? ' / '.$application->environment : '' }}{{ $application->owner_team ? ' / '.$application->owner_team : '' }}</p>
                @if($application->description)
                    <p class="mt-3 max-w-3xl text-sm text-slate-600 dark:text-slate-300">{{ $application->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @can('module.agent_deployment')
                    <a href="{{ route('applications.agent-packages', $application) }}" onclick="return confirm('Generate packages for all mapped servers? This rotates each server agent key.');" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-slate-500/10 transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">Generate Agent Packages</a>
                @endcan
                <a href="{{ route('applications.edit', $application) }}" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/10 transition hover:bg-orange-500">Edit Mapping</a>
                <a href="{{ route('applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Applications</a>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Mapped Servers</div>
                <div class="mt-3 text-3xl font-bold">{{ $serverGroups->count() }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">App Servers</div>
                <div class="mt-3 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $summary['app_servers']['healthy'] }}/{{ $summary['app_servers']['total'] }}</div>
                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Min {{ $summary['app_servers']['min_required'] }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">DB Servers</div>
                <div class="mt-3 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $summary['database_servers']['healthy'] }}/{{ $summary['database_servers']['total'] }}</div>
                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Min {{ $summary['database_servers']['min_required'] }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">URL Status</div>
                <div class="mt-3 text-3xl font-bold {{ $summary['url_status'] === 'down' ? 'text-red-600 dark:text-red-400' : ($summary['url_status'] === 'up' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400') }}">{{ ucfirst($summary['url_status']) }}</div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <section class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div class="border-b border-slate-200/70 p-5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Server Role Map</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Server</th>
                                <th class="px-4 py-3">Roles</th>
                                <th class="px-4 py-3">Heartbeat</th>
                                <th class="px-4 py-3">Last Metric</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                            @forelse($serverGroups as $serverRows)
                                @php
                                    $server = $serverRows->first();
                                    $isOnline = $server->agentHeartbeatStatus() === 'online';
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-4">
                                        <a href="{{ route('servers.edit', $server) }}" class="font-semibold text-slate-900 hover:text-orange-600 dark:text-white dark:hover:text-orange-300">{{ $server->name }}</a>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $server->server_id }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($serverRows as $row)
                                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-white/5 dark:text-slate-300">
                                                    {{ str_replace('_', ' ', $row->pivot->role) }}{{ $row->pivot->is_primary ? ' / primary' : '' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $isOnline ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200' }}">{{ $isOnline ? 'Online' : 'Offline' }}</span>
                                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $server->last_heartbeat_at ? $server->last_heartbeat_at->diffForHumans() : 'No heartbeat' }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                        @if($server->latestMetric)
                                            CPU {{ number_format((float) $server->latestMetric->cpu, 1) }}%
                                        @else
                                            No metrics
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No servers mapped.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">URLs</h3>
                <div class="mt-4 space-y-3">
                    @forelse($application->urls as $url)
                        @php
                            $urlStatus = $url->monitor?->latestResult
                                ? ($url->monitor->latestResult->is_up ? 'up' : 'down')
                                : ($url->status ?: 'unknown');
                        @endphp
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="break-all text-sm font-semibold text-slate-900 dark:text-white">{{ $url->url ?? 'Monitor #'.$url->monitor_id }}</div>
                                    <div class="mt-2 text-xs font-bold uppercase tracking-wider {{ $urlStatus === 'down' ? 'text-red-600 dark:text-red-300' : ($urlStatus === 'up' ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300') }}">{{ ucfirst($urlStatus) }}</div>
                                    @if($url->monitor)
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Linked monitor: {{ $url->monitor->name }}</div>
                                    @else
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">No monitor linked.</div>
                                    @endif
                                </div>
                                @if($url->monitor)
                                    <a href="{{ route('monitors.edit', $url->monitor) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                                        Open Monitor
                                    </a>
                                @elseif($url->url)
                                    <div class="flex shrink-0 flex-col gap-2 sm:w-56">
                                        <a href="{{ route('monitors.create', ['url' => $url->url, 'name' => $application->name, 'application_url_id' => $url->id]) }}" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-500">
                                            Create Monitor
                                        </a>
                                        @if($monitors->isNotEmpty())
                                            <form method="POST" action="{{ route('application-urls.link-monitor', $url) }}" class="flex gap-2">
                                                @csrf
                                                <select name="monitor_id" class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs text-slate-700 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200">
                                                    <option value="">Existing monitor</option>
                                                    @foreach($monitors as $monitor)
                                                        <option value="{{ $monitor->id }}">{{ $monitor->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                                                    Link
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">No URLs mapped.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
