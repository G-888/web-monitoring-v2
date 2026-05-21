<x-app-layout>
    <x-slot name="header_title">IIS Log Detail</x-slot>

    @php
        $topIps = collect($latest?->top_ips ?? []);
        $topUrls = collect($latest?->top_urls ?? []);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $server->name }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $server->server_id }} IIS W3C log summary trend.</p>
            </div>
            <a href="{{ route('iis-logs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                IIS Logs
            </a>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Requests</div>
                <div class="mt-3 text-3xl font-bold">{{ number_format((int) ($latest?->total_requests ?? 0)) }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">HTTP 404</div>
                <div class="mt-3 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ number_format((int) ($latest?->http_404 ?? 0)) }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">HTTP 500</div>
                <div class="mt-3 text-3xl font-bold text-red-600 dark:text-red-400">{{ number_format((int) ($latest?->http_500 ?? 0)) }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Suspicious</div>
                <div class="mt-3 text-3xl font-bold text-orange-600 dark:text-orange-400">{{ number_format((int) ($latest?->suspicious_count ?? 0)) }}</div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Collector</div>
                <div class="mt-3 text-sm font-bold {{ $collectorStatus?->enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300' }}">
                    {{ $collectorStatus?->enabled ? 'Enabled' : 'Disabled' }}
                </div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Last Scan</div>
                <div class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ $collectorStatus?->last_scan_at?->diffForHumans() ?? 'No scan' }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Files Read</div>
                <div class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ number_format((int) ($collectorStatus?->files_read ?? 0)) }} / {{ number_format((int) ($collectorStatus?->files_seen ?? 0)) }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Lines Read</div>
                <div class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ number_format((int) ($collectorStatus?->lines_read ?? 0)) }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Last Error</div>
                <div class="mt-3 text-xs font-semibold {{ $collectorStatus?->last_error ? 'text-red-600 dark:text-red-300' : 'text-slate-600 dark:text-slate-300' }}" title="{{ $collectorStatus?->last_error }}">
                    {{ $collectorStatus?->last_error ? Str::limit($collectorStatus->last_error, 90) : 'Clear' }}
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Recent Trend</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Last {{ $summaries->count() }} IIS summary windows.</p>
            </div>
            <div class="h-72">
                <canvas id="iis-log-trend"></canvas>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Top IPs</h3>
                <div class="mt-4 space-y-3">
                    @forelse($topIps as $item)
                        @php
                            $label = is_array($item) ? ($item['value'] ?? $item['ip'] ?? $item['key'] ?? 'unknown') : 'unknown';
                            $count = is_array($item) ? ($item['count'] ?? 0) : 0;
                        @endphp
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-950/60">
                            <span class="font-mono text-sm text-slate-700 dark:text-slate-200">{{ $label }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ number_format((int) $count) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">No top IP data yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Top URLs</h3>
                <div class="mt-4 space-y-3">
                    @forelse($topUrls as $item)
                        @php
                            $label = is_array($item) ? ($item['value'] ?? $item['url'] ?? $item['key'] ?? 'unknown') : 'unknown';
                            $count = is_array($item) ? ($item['count'] ?? 0) : 0;
                        @endphp
                        <div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-950/60">
                            <span class="break-all font-mono text-sm text-slate-700 dark:text-slate-200">{{ $label }}</span>
                            <span class="shrink-0 text-sm font-bold text-slate-900 dark:text-white">{{ number_format((int) $count) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">No top URL data yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="border-b border-slate-200/70 p-5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Suspicious Samples</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">IP</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Pattern</th>
                            <th class="px-4 py-3">URL</th>
                            <th class="px-4 py-3">User Agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @forelse($events as $event)
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $event->event_timestamp?->format('Y-m-d H:i:s') ?? $event->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="px-4 py-4 font-mono text-slate-700 dark:text-slate-200">{{ $event->ip_address ?? '-' }}</td>
                                <td class="px-4 py-4 text-slate-700 dark:text-slate-200">{{ $event->status_code ?? '-' }}</td>
                                <td class="px-4 py-4 text-orange-600 dark:text-orange-300">{{ $event->matched_pattern ?? '-' }}</td>
                                <td class="max-w-xl break-all px-4 py-4 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $event->url ?? '-' }}</td>
                                <td class="max-w-md break-all px-4 py-4 text-xs text-slate-500 dark:text-slate-400">{{ $event->user_agent ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No suspicious samples captured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('iis-log-trend');
            if (!canvas || !window.Chart) {
                return;
            }

            const trend = @js($trend);
            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: trend.labels,
                    datasets: [
                        { label: 'Requests', data: trend.requests, borderColor: '#f97316', backgroundColor: '#f9731620', tension: 0.3, fill: true },
                        { label: '404', data: trend.http_404, borderColor: '#f59e0b', tension: 0.3 },
                        { label: '500', data: trend.http_500, borderColor: '#ef4444', tension: 0.3 },
                        { label: 'Suspicious', data: trend.suspicious, borderColor: '#a855f7', tension: 0.3 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#94a3b8' } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8' } },
                    },
                },
            });
        });
    </script>
</x-app-layout>
