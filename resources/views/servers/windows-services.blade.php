<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Windows Services</h2>
    </x-slot>

    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white">Service Control</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage monitored Windows services such as MySQL, ColdFusion, IIS, and application services.</p>
            </div>
            <a href="{{ route('servers.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                Server Inventory
            </a>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
            Commands are picked up by the target server agent on its next heartbeat. Start/Stop/Restart requires the Windows agent to run elevated, preferably through `D:\server-monitor-agent\install-agent-task.ps1` from an Administrator PowerShell window.
        </div>

        @foreach($servers as $server)
            <div class="overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div class="flex flex-col gap-4 border-b border-slate-200/70 p-5 dark:border-white/10 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $server->name }}</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $server->server_id }}{{ $server->ip_address ? ' · '.$server->ip_address : '' }}</p>
                    </div>
                    <form method="POST" action="{{ route('servers.windows-services.store', $server) }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        @csrf
                        <x-text-input name="service_name" type="text" placeholder="Service name, e.g. ColdFusion 2023 Application Server" class="w-full" />
                        <x-text-input name="display_name" type="text" placeholder="Display name" class="w-full" />
                        <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">
                            Add Service
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Service Name</th>
                                <th class="px-4 py-3">Display Name</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Startup Type</th>
                                <th class="px-4 py-3">Last Checked</th>
                                <th class="px-4 py-3">Last Command</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                            @forelse($server->windowsServices as $service)
                                @php
                                    $running = strtolower((string) $service->status) === 'running';
                                    $lastCommand = $service->commands()->latest()->first();
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-4 font-medium text-slate-900 dark:text-white">{{ $service->service_name }}</td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $service->display_name ?? '—' }}</td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $running ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200' }}">
                                            {{ $service->status ?? 'Unknown' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $service->startup_type ?? '—' }}</td>
                                    <td class="px-4 py-4 text-slate-500 dark:text-slate-400">{{ $service->last_checked_at ? $service->last_checked_at->diffForHumans() : 'Never' }}</td>
                                    <td class="px-4 py-4 text-xs text-slate-500 dark:text-slate-400">
                                        @if($lastCommand)
                                            <div>{{ ucfirst($lastCommand->action) }} · {{ ucfirst($lastCommand->status) }}</div>
                                            <div>{{ $lastCommand->updated_at->diffForHumans() }}</div>
                                        @else
                                            None
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @can('module.service_control')
                                                @foreach(['start', 'stop', 'restart'] as $action)
                                                    <form method="POST" action="{{ route('windows-services.commands', $service) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="{{ $action }}" />
                                                        <button type="submit" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                                                            {{ ucfirst($action) }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                            @endcan
                                            <form method="POST" action="{{ route('windows-services.destroy', $service) }}" onsubmit="return confirm('Remove this service from monitoring?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                        No Windows services are configured for this server yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
