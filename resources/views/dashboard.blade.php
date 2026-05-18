<x-app-layout>
    <x-slot name="header_title">Monitoring Dashboard</x-slot>

    <div class="space-y-6">
        <!-- Stats Grid -->
        <section class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="glass rounded-2xl p-6 transition-all duration-300 hover:shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-bold text-slate-500 uppercase tracking-wider">Total Monitors</div>
                    <div class="h-10 w-10 rounded-xl bg-orange-100 dark:bg-orange-500/10 flex items-center justify-center text-orange-600 dark:text-orange-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 text-3xl font-bold">{{ $monitors->count() }}</div>
            </div>

            <div class="glass rounded-2xl p-6 transition-all duration-300 hover:shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-bold text-slate-500 uppercase tracking-wider">Online</div>
                    <div class="h-10 w-10 rounded-xl bg-green-100 dark:bg-green-500/10 flex items-center justify-center text-green-600 dark:text-green-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                </div>
                <div class="mt-4 text-3xl font-bold text-green-600 dark:text-green-400">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestResult?->is_up)->count() }}
                </div>
            </div>

            <div class="glass rounded-2xl p-6 transition-all duration-300 hover:shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-bold text-slate-500 uppercase tracking-wider">Offline</div>
                    <div class="h-10 w-10 rounded-xl bg-red-100 dark:bg-red-500/10 flex items-center justify-center text-red-600 dark:text-red-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                </div>
                <div class="mt-4 text-3xl font-bold text-red-600 dark:text-red-400">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestResult && ! $monitor->latestResult->is_up)->count() }}
                </div>
            </div>

            <div class="glass rounded-2xl p-6 transition-all duration-300 hover:shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-bold text-slate-500 uppercase tracking-wider">SEO Alerts</div>
                    <div class="h-10 w-10 rounded-xl bg-amber-100 dark:bg-amber-500/10 flex items-center justify-center text-amber-600 dark:text-amber-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 text-3xl font-bold text-amber-600 dark:text-amber-400">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestSeoResult?->is_suspicious)->count() }}
                </div>
            </div>
        </section>

        <!-- Monitors Grid -->
        <section class="grid gap-6 lg:grid-cols-2">
            @forelse($monitors as $monitor)
                @php
                    $result = $monitor->latestResult;
                    $isUp = (bool) ($result?->is_up);
                @endphp

                <article class="glass rounded-2xl p-6 transition-all duration-300 hover:shadow-xl relative overflow-hidden group">
                    <div class="absolute top-0 right-0 p-4">
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold {{ $isUp ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400' }}">
                            <span class="h-2 w-2 rounded-full {{ $isUp ? 'bg-green-500' : 'bg-red-500' }} animate-pulse"></span>
                            {{ $isUp ? 'ONLINE' : 'OFFLINE' }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <h2 class="text-xl font-bold group-hover:text-orange-500 transition-colors">{{ $monitor->name }}</h2>
                        <a href="{{ $monitor->url }}" target="_blank" class="text-sm text-slate-500 hover:text-orange-400 truncate max-w-xs">{{ $monitor->url }}</a>
                    </div>

                    <div class="mt-8 grid grid-cols-3 gap-4">
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Response</div>
                            <div class="mt-1 text-lg font-bold">{{ $result?->response_time ? number_format($result->response_time, 3).'s' : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</div>
                            <div class="mt-1 text-lg font-bold">{{ $result?->status_code ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Uptime 24h</div>
                            <div class="mt-1 text-lg font-bold text-orange-600 dark:text-orange-400">{{ $monitor->uptime_24h ?? 0 }}%</div>
                        </div>
                    </div>

                    <!-- Chart Container -->
                    <div class="mt-6 h-32 w-full">
                        <canvas id="chart-{{ $monitor->id }}"></canvas>
                    </div>

                    <div class="mt-6 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2 flex-wrap">
                             <span class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-white/5 text-[10px] font-bold text-slate-500">{{ $monitor->interval }}s interval</span>
                             
                             <span class="px-2 py-1 rounded-lg {{ $monitor->latestSeoResult?->is_suspicious ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-slate-100 dark:bg-white/5 text-slate-500' }} text-[10px] font-bold">
                                SEO: {{ $monitor->seo_enabled ? ($monitor->latestSeoResult?->is_suspicious ? 'ALERT' : 'CLEAR') : 'OFF' }}
                             </span>

                             @if($monitor->ssl_expires_at)
                                @php
                                    $daysLeft = now()->diffInDays($monitor->ssl_expires_at, false);
                                    $sslStatusColor = $daysLeft < 7 ? 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' : ($daysLeft < 30 ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' : 'bg-slate-100 dark:bg-white/5 text-slate-500');
                                @endphp
                                <span class="px-2 py-1 rounded-lg {{ $sslStatusColor }} text-[10px] font-bold" title="Expires: {{ $monitor->ssl_expires_at->format('Y-m-d H:i') }}">
                                   SSL: {{ $daysLeft > 0 ? $daysLeft . 'd' : 'EXPIRED' }}
                                </span>
                             @elseif(str_starts_with($monitor->url, 'https'))
                                <span class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-white/5 text-[10px] font-bold text-slate-400">
                                   SSL: PENDING
                                </span>
                             @endif
                        </div>
                        <div class="flex gap-2">
                             <form method="POST" action="{{ route('monitors.check', $monitor) }}">
                                @csrf
                                <button class="p-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white transition-colors" title="Check Now">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>
                            </form>
                            <a href="{{ route('monitors.edit', $monitor) }}" class="p-2 rounded-xl border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors" title="Edit">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </a>
                        </div>
                    </div>

                    @if($monitor->latestSeoResult?->is_suspicious)
                        <div class="mt-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-500/5 border border-amber-200 dark:border-amber-500/10">
                            <div class="text-xs font-bold text-amber-800 dark:text-amber-400 uppercase tracking-widest mb-2">Detection Findings</div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach(($monitor->latestSeoResult->detected_patterns ?? []) as $pattern)
                                    <span class="px-2 py-0.5 rounded-full bg-amber-200/50 dark:bg-amber-500/20 text-[10px] font-bold text-amber-900 dark:text-amber-300">{{ $pattern }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="lg:col-span-2 glass rounded-3xl p-12 text-center">
                    <div class="h-20 w-20 rounded-2xl bg-slate-100 dark:bg-white/5 mx-auto flex items-center justify-center text-slate-400 mb-6">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h2 class="text-2xl font-bold mb-2">No monitors configured</h2>
                    <p class="text-slate-500 mb-8 max-w-sm mx-auto">Start tracking your infrastructure by adding your first website monitor.</p>
                    <a href="{{ route('monitors.create') }}" class="btn-primary inline-block">Add Your First Monitor</a>
                </div>
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
                            backgroundColor: accentColor + '20',
                            fill: true,
                            tension: .4,
                            pointRadius: 0,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { 
                            x: { display: false }, 
                            y: { 
                                display: false,
                                suggestedMin: 0
                            } 
                        }
                    }
                });
            })();
        @endforeach
        
        // Listen for dark mode toggle to update charts
        window.addEventListener('click', (e) => {
             if (e.target.closest('[data-theme-toggle]')) {
                 setTimeout(() => {
                    const isDark = document.documentElement.classList.contains('dark');
                    const accentColor = isDark ? '#fb923c' : '#f97316';
                    
                    Object.values(window.monitorCharts).forEach(chart => {
                        chart.data.datasets[0].borderColor = accentColor;
                        chart.data.datasets[0].backgroundColor = accentColor + '20';
                        chart.update();
                    });
                 }, 100);
             }
        });
    </script>
</x-app-layout>
