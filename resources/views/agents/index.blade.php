<x-app-layout>
    <x-slot name="header_title">Agent Operations</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Agent Operations</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Track heartbeat health, deployed versions, capabilities, and generated install configs.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('servers.create') }}" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/10 transition hover:bg-orange-500">
                    Add Server
                </a>
                <a href="{{ route('servers.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                    Server Inventory
                </a>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Registered Agents</div>
                <div class="mt-3 text-3xl font-bold">{{ $stats['total'] }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Online</div>
                <div class="mt-3 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['online'] }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Outdated</div>
                <div class="mt-3 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $stats['outdated'] }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Reporting Errors</div>
                <div class="mt-3 text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['errors'] }}</div>
            </div>
        </section>

        <div class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="border-b border-slate-200/70 p-5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Fleet Status</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Download a per-server config after creating an inventory entry, then place it next to the agent package.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Server</th>
                            <th class="px-4 py-3">Heartbeat</th>
                            <th class="px-4 py-3">Version</th>
                            <th class="px-4 py-3">Runtime</th>
                            <th class="px-4 py-3">Applications</th>
                            <th class="px-4 py-3">Profile</th>
                            <th class="px-4 py-3">Modules</th>
                            <th class="px-4 py-3">Capabilities</th>
                            <th class="px-4 py-3">IIS Logs</th>
                            <th class="px-4 py-3">Services</th>
                            <th class="px-4 py-3">Last Error</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @forelse($servers as $server)
                            @php
                                $heartbeatState = $server->agentHeartbeatStatus();
                                $versionState = $server->agentVersionState();
                                $heartbeatClass = match ($heartbeatState) {
                                    'online' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                    'offline' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200',
                                    default => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300',
                                };
                                $versionClass = match ($versionState) {
                                    'current' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                    'outdated' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
                                    'unsupported' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200',
                                    default => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300',
                                };
                                $capabilities = collect($server->capabilities ?? [])->filter()->values();
                                $iisLogsEnabled = (bool) ($server->iisLogCollectorStatus?->enabled ?? $capabilities->contains('iisLogs'));
                                $agentProfile = app(\App\Services\AgentDeploymentService::class)->profile($server);
                                $enabledModules = collect($agentProfile['enabledModules'] ?? [])->values();
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $server->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $server->server_id }}{{ $server->server_type ? ' / '.$server->server_type : '' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $server->agent_hostname ?? $server->ip_address ?? 'No host reported' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $heartbeatClass }}">
                                        {{ ucfirst($heartbeatState) }}
                                    </span>
                                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $server->last_heartbeat_at ? $server->last_heartbeat_at->diffForHumans() : 'No heartbeat' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $versionClass }}">
                                        {{ ucfirst($versionState) }}
                                    </span>
                                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $server->agent_version ?? 'Not reported' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Schema {{ $server->config_schema_version ?? config('agent.default_config_schema_version') }}</div>
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                    <div>{{ $server->agent_os ?? $server->os ?? 'Unknown OS' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $server->agent_runtime ?? 'Runtime not reported' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    @php $mappedApplications = $server->applications->unique('id')->values(); @endphp
                                    @if($mappedApplications->isEmpty())
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Unmapped</span>
                                    @else
                                        <div class="flex max-w-48 flex-wrap gap-1.5">
                                            @foreach($mappedApplications->take(3) as $application)
                                                <span class="rounded-lg bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-200">{{ $application->name }}</span>
                                            @endforeach
                                            @if($mappedApplications->count() > 3)
                                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500 dark:bg-white/5 dark:text-slate-300">+{{ $mappedApplications->count() - 3 }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $agentProfile['profile_name'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ filled($agentProfile['roles']) ? implode(', ', $agentProfile['roles']) : 'No mapped roles' }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex max-w-56 flex-wrap gap-1.5">
                                        @foreach($enabledModules->take(5) as $module)
                                            <span class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">{{ $module }}</span>
                                        @endforeach
                                        @if($enabledModules->count() > 5)
                                            <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500 dark:bg-white/5 dark:text-slate-300">+{{ $enabledModules->count() - 5 }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    @if($capabilities->isEmpty())
                                        <span class="text-xs text-slate-500 dark:text-slate-400">None reported</span>
                                    @else
                                        <div class="flex max-w-56 flex-wrap gap-1.5">
                                            @foreach($capabilities->take(4) as $capability)
                                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $capability }}</span>
                                            @endforeach
                                            @if($capabilities->count() > 4)
                                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500 dark:bg-white/5 dark:text-slate-300">+{{ $capabilities->count() - 4 }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if($iisLogsEnabled)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">Enabled</span>
                                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $server->iisLogCollectorStatus?->last_scan_at?->diffForHumans() ?? 'Awaiting scan' }}</div>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-white/5 dark:text-slate-300">Disabled</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                    {{ $server->windowsServices->count() }} monitored
                                </td>
                                <td class="px-4 py-4">
                                    @if($server->last_agent_error)
                                        <span class="text-xs text-red-600 dark:text-red-300" title="{{ $server->last_agent_error }}">{{ Str::limit($server->last_agent_error, 70) }}</span>
                                    @else
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Clear</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @include('agents._deployment-actions', ['server' => $server, 'context' => 'agents'])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                    No servers are registered yet. Add a server to generate its first agent config.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
