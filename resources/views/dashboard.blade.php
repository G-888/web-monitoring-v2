<x-app-layout>
    <x-slot name="header_title">Monitoring Dashboard</x-slot>

    <div class="space-y-8">
        <!-- Stats Grid (Sleeker) -->
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="glass rounded-2xl p-5 border border-white/5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Nodes</div>
                    <div class="p-2 rounded-xl bg-orange-100 dark:bg-orange-500/10 text-orange-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black">{{ $monitors->count() }}</div>
            </div>

            <div class="glass rounded-2xl p-5 border border-white/5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Active</div>
                    <div class="p-2 rounded-xl bg-green-100 dark:bg-green-500/10 text-green-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-green-500">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestResult?->is_up)->count() }}
                </div>
            </div>

            <div class="glass rounded-2xl p-5 border border-white/5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Down</div>
                    <div class="p-2 rounded-xl bg-red-100 dark:bg-red-500/10 text-red-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-red-500">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestResult && ! $monitor->latestResult->is_up)->count() }}
                </div>
            </div>

            <div class="glass rounded-2xl p-5 border border-white/5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">SEO Alerts</div>
                    <div class="p-2 rounded-xl bg-amber-100 dark:bg-amber-500/10 text-amber-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-amber-500">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestSeoResult?->is_suspicious)->count() }}
                </div>
            </div>
        </section>

        <!-- Monitors Grid (3 Columns + Compact Cards) -->
        <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse($monitors as $monitor)
                @php
                    $result = $monitor->latestResult;
                    $isUp = (bool) ($result?->is_up);
                @endphp

                <article class="glass rounded-3xl p-5 border border-white/5 shadow-sm transition-all duration-300 hover:shadow-xl group relative overflow-hidden">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <h2 class="text-lg font-black group-hover:text-orange-500 transition-colors truncate">{{ $monitor->name }}</h2>
                            <a href="{{ $monitor->url }}" target="_blank" class="text-[10px] text-slate-500 font-mono hover:text-orange-400 truncate flex items-center gap-1">
                                {{ $monitor->url }}
                                <svg class="h-2 w-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        </div>
                        <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg text-[9px] font-black tracking-widest {{ $isUp ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $isUp ? 'bg-green-500 animate-pulse' : 'bg-red-500' }}"></span>
                            {{ $isUp ? 'ONLINE' : 'OFFLINE' }}
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-3 gap-2 border-y border-white/5 py-4">
                        <div class="text-center">
                            <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">Latency</div>
                            <div class="mt-0.5 text-sm font-black">{{ $result?->response_time ? number_format($result->response_time, 3).'s' : '-' }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">HTTP</div>
                            <div class="mt-0.5 text-sm font-black">{{ $result?->status_code ?? '-' }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">Uptime</div>
                            <div class="mt-0.5 text-sm font-black text-orange-500">{{ $monitor->uptime_24h ?? 0 }}%</div>
                        </div>
                    </div>

                    <!-- Compact Chart -->
                    <div class="mt-4 h-20 w-full opacity-60 group-hover:opacity-100 transition-opacity">
                        <canvas id="chart-{{ $monitor->id }}"></canvas>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 overflow-hidden">
                             <span class="px-2 py-0.5 rounded-md bg-white/5 text-[9px] font-bold text-slate-400 whitespace-nowrap">{{ $monitor->interval }}s</span>
                             
                             <span class="px-2 py-0.5 rounded-md {{ $monitor->latestSeoResult?->is_suspicious ? 'bg-red-500 text-white' : 'bg-green-500/10 text-green-500' }} text-[9px] font-black tracking-widest uppercase whitespace-nowrap">
                                SEO: {{ $monitor->seo_enabled ? ($monitor->latestSeoResult?->is_suspicious ? 'ALERT' : 'CLEAR') : 'OFF' }}
                             </span>
                        </div>
                        <div class="flex gap-1.5 flex-shrink-0">
                             <form method="POST" action="{{ route('monitors.check', $monitor) }}">
                                @csrf
                                <button class="p-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white transition-all shadow-lg shadow-orange-600/20 active:scale-95">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </button>
                            </form>
                            <a href="{{ route('monitors.edit', $monitor) }}" class="p-2 rounded-xl bg-white/5 border border-white/5 text-slate-500 hover:text-white transition-colors">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <!-- Empty State -->
            @endforelse
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.monitorCharts = {};

        @foreach($monitors as $monitor)
            (() => {
                const canvas = document.getElementById('chart-{{ $monitor->id }}');
                if (!canvas) return;

                const isDark = document.documentElement.classList.contains('dark');
                const accentColor = isDark ? '#fb923c' : '#f97316';

                window.monitorCharts['{{ $monitor->id }}'] = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: @json($monitor->recent_checks->pluck('checked_at')->map(fn ($date) => optional($date)->format('H:i:s'))),
                        datasets: [{
                            data: @json($monitor->recent_checks->pluck('response_time')),
                            borderColor: accentColor,
                            backgroundColor: 'transparent',
                            fill: false,
                            stepped: true, // Technical stepped style
                            tension: 0,
                            pointRadius: 0,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { enabled: false } },
                        scales: { 
                            x: { display: false }, 
                            y: { display: false, suggestedMin: 0 } 
                        }
                    }
                });
            })();
        @endforeach
    </script>
</x-app-layout>
