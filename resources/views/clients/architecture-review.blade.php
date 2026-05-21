<x-app-layout>
    <x-slot name="header_title">Architecture Review</x-slot>

    @php
        $application = $review['application'];
        $client = $review['client'];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Architecture Onboarding Review</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $client?->name ?? 'No client' }} / {{ $application->name }} / {{ str_replace('_', ' ', $review['architecture_type']) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('module.agent_deployment')
                    <a href="{{ route('applications.agent-packages', $application) }}" onclick="return confirm('Generate packages for all mapped servers? This rotates each server agent key.');" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">Download All Packages</a>
                @endcan
                <a href="{{ route('applications.show', $application) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Application</a>
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Completion</div>
                <div class="mt-3 text-3xl font-bold text-orange-600 dark:text-orange-400">{{ $review['score'] }}%</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">App Servers</div>
                <div class="mt-3 text-3xl font-bold">{{ $review['app_servers']->count() }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">DB Servers</div>
                <div class="mt-3 text-3xl font-bold">{{ $review['db_servers']->count() }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Network Checks</div>
                <div class="mt-3 text-3xl font-bold">{{ $review['network_monitors']->count() + $review['dns_monitors']->count() }}</div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Mapped Servers</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">App Servers</div>
                        <div class="mt-3 space-y-2">
                            @forelse($review['app_servers'] as $server)
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-white/10 dark:bg-slate-950/60 dark:text-white">{{ $server->name }}</div>
                            @empty
                                <div class="text-sm text-slate-500 dark:text-slate-400">No app servers mapped.</div>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">DB Servers</div>
                        <div class="mt-3 space-y-2">
                            @forelse($review['db_servers'] as $server)
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-white/10 dark:bg-slate-950/60 dark:text-white">{{ $server->name }}</div>
                            @empty
                                <div class="text-sm text-slate-500 dark:text-slate-400">No DB servers mapped.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Generated Network Monitors</h3>
                <div class="mt-4 space-y-2">
                    @forelse($review['network_monitors'] as $monitor)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-slate-950/60">
                            <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $monitor->name }}</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $monitor->sourceLabel() }} -> {{ $monitor->destinationLabel() }} / {{ strtoupper($monitor->protocol ?? 'tcp') }} {{ $monitor->target_port ?? '' }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500 dark:text-slate-400">No network monitors generated.</div>
                    @endforelse
                </div>
                <div class="mt-5 text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">DNS Monitors</div>
                <div class="mt-3 space-y-2">
                    @forelse($review['dns_monitors'] as $monitor)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-slate-950/60">
                            <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $monitor->target_host }}</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $monitor->dns_record_type ?? 'A' }} record / {{ $monitor->is_active ? 'Active' : 'Pending' }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500 dark:text-slate-400">No DNS monitors generated.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Onboarding Checklist</h3>
            <div class="mt-4 grid gap-2 md:grid-cols-3">
                @foreach($review['checklist'] as $item)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-slate-950/60">
                        <div class="text-sm font-semibold {{ $item['complete'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">{{ $item['complete'] ? 'Complete' : 'Pending' }}</div>
                        <div class="mt-1 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $item['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Agent Deployment Status</h3>
                <div class="mt-4 space-y-3">
                    @foreach($review['deployment'] as $row)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $row['server']->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $row['expected_profile'] }}</div>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['agent_installed'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200' }}">{{ $row['agent_installed'] ? ucfirst($row['heartbeat_status']) : 'Not installed' }}</span>
                            </div>
                            <div class="mt-3 grid gap-2 text-xs text-slate-500 dark:text-slate-400 sm:grid-cols-3">
                                <div>Package: {{ $row['package_generated'] ? 'Generated' : 'Not generated' }}</div>
                                <div>Version: {{ $row['agent_version'] ?? 'Not reported' }}</div>
                                <div>Heartbeat: {{ $row['last_heartbeat_at']?->diffForHumans() ?? 'Never' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Profile Drift</h3>
                <div class="mt-4 space-y-3">
                    @foreach($review['drift'] as $row)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $row['server']->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $row['expected_profile'] }}</div>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['status'] === 'OK' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200' }}">{{ $row['status'] }}</span>
                            </div>
                            @if($row['missing_modules'] || $row['unexpected_modules'])
                                <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    Missing: {{ implode(', ', $row['missing_modules']) ?: 'none' }} / Unexpected: {{ implode(', ', $row['unexpected_modules']) ?: 'none' }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Database Monitor Guided Setup</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                        <tr><th class="px-4 py-3">Monitor</th><th class="px-4 py-3">Server</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @forelse($review['db_monitors'] as $monitor)
                            <tr>
                                <td class="px-4 py-4 font-semibold text-slate-900 dark:text-white">{{ $monitor->name }}</td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $monitor->server?->name ?? $monitor->host }}</td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ str_replace('_', ' ', $monitor->db_role ?? 'cluster_member') }}</td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $monitor->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200' }}">{{ $monitor->is_active ? 'Enabled' : 'Pending Configuration' }}</span>
                                </td>
                                <td class="px-4 py-4"><a href="{{ route('database-monitors.guided-setup', $monitor) }}" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-500">Configure</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No DB monitor placeholders.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
