<x-app-layout>
    <x-slot name="header_title">IIS Logs</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">IIS Log Monitoring</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Lightweight W3C summary checks reported by Windows agents.</p>
            </div>
            <a href="{{ route('agents.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                Agent Operations
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="border-b border-slate-200/70 p-5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Server Summary</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">IIS collection is disabled by default and appears here after agents post summaries.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Server</th>
                            <th class="px-4 py-3">Requests</th>
                            <th class="px-4 py-3">404</th>
                            <th class="px-4 py-3">500</th>
                            <th class="px-4 py-3">Suspicious</th>
                            <th class="px-4 py-3">Last Check</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @forelse($servers as $row)
                            @php
                                $server = $row['server'];
                                $latest = $row['latest'];
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $server->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $server->server_id }}</div>
                                </td>
                                <td class="px-4 py-4 font-semibold text-slate-900 dark:text-white">{{ number_format((int) ($latest?->total_requests ?? 0)) }}</td>
                                <td class="px-4 py-4 text-amber-600 dark:text-amber-300">{{ number_format((int) ($latest?->http_404 ?? 0)) }}</td>
                                <td class="px-4 py-4 text-red-600 dark:text-red-300">{{ number_format((int) ($latest?->http_500 ?? 0)) }}</td>
                                <td class="px-4 py-4 text-orange-600 dark:text-orange-300">{{ number_format((int) ($latest?->suspicious_count ?? 0)) }}</td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                    {{ $latest?->window_end?->diffForHumans() ?? $latest?->created_at?->diffForHumans() ?? 'No IIS summary' }}
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('iis-logs.show', $server) }}" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-500">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No active servers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
