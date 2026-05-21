<x-app-layout>
    <x-slot name="header_title">Applications</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Application Mapping</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Application health from URLs, mapped server roles, and minimum component rules.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('applications.setup') }}" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/10 transition hover:bg-orange-500">
                    Setup Wizard
                </a>
                <a href="{{ route('applications.create') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                    Create Application
                </a>
            </div>
        </div>

        @php
            $summaries = $apps->mapWithKeys(fn ($app) => [$app->id => $app->healthSummary()]);
            $healthyCount = $summaries->filter(fn ($summary) => $summary['status'] === 'healthy')->count();
            $warningCount = $summaries->filter(fn ($summary) => $summary['status'] === 'warning')->count();
            $criticalCount = $summaries->filter(fn ($summary) => $summary['status'] === 'critical')->count();
        @endphp

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Applications</div>
                <div class="mt-3 text-3xl font-bold">{{ $apps->count() }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Healthy</div>
                <div class="mt-3 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $healthyCount }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Warning</div>
                <div class="mt-3 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $warningCount }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Critical</div>
                <div class="mt-3 text-3xl font-bold text-red-600 dark:text-red-400">{{ $criticalCount }}</div>
            </div>
        </section>

        <div class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Application</th>
                            <th class="px-4 py-3">Environment</th>
                            <th class="px-4 py-3">Mapped Servers</th>
                            <th class="px-4 py-3">App Servers</th>
                            <th class="px-4 py-3">DB Servers</th>
                            <th class="px-4 py-3">URL Status</th>
                            <th class="px-4 py-3">Overall Health</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @forelse($apps as $app)
                            @php
                                $summary = $summaries[$app->id];
                                $statusClass = match ($summary['status']) {
                                    'healthy' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                    'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
                                    default => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200',
                                };
                                $urlClass = match ($summary['url_status']) {
                                    'up' => 'text-emerald-600 dark:text-emerald-300',
                                    'down' => 'text-red-600 dark:text-red-300',
                                    'unknown' => 'text-amber-600 dark:text-amber-300',
                                    default => 'text-slate-500 dark:text-slate-400',
                                };
                                $serverGroups = $app->servers->groupBy('id');
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $app->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $app->code }}</div>
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $app->environment ?? 'Not set' }}</td>
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $serverGroups->count() }}</div>
                                    <div class="mt-1 flex max-w-72 flex-wrap gap-1.5">
                                        @foreach($serverGroups->take(4) as $serverRows)
                                            @php $server = $serverRows->first(); @endphp
                                            <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $server->name }}</span>
                                        @endforeach
                                        @if($serverGroups->count() > 4)
                                            <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500 dark:bg-white/5 dark:text-slate-300">+{{ $serverGroups->count() - 4 }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                    {{ $summary['app_servers']['healthy'] }}/{{ $summary['app_servers']['total'] }}
                                    <span class="text-xs text-slate-500 dark:text-slate-400">min {{ $summary['app_servers']['min_required'] }}</span>
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                    {{ $summary['database_servers']['healthy'] }}/{{ $summary['database_servers']['total'] }}
                                    <span class="text-xs text-slate-500 dark:text-slate-400">min {{ $summary['database_servers']['min_required'] }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-sm font-semibold {{ $urlClass }}">{{ ucfirst($summary['url_status']) }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst($summary['status']) }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('applications.show', $app) }}" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-500">View</a>
                                        <a href="{{ route('applications.edit', $app) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                    No applications mapped yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
