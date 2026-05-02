<x-app-layout>
    <x-slot name="header_title">Infrastructure Dashboard</x-slot>

    <div class="max-w-7xl mx-auto space-y-10 pb-20 px-4 sm:px-6 lg:px-8 pt-6">
        
        <!-- Command Center Stats -->
        <section class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Monitors -->
            <div class="bg-slate-900 rounded-[2rem] p-8 border border-white/5 shadow-2xl shadow-slate-950/20">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-xs font-black text-slate-500 uppercase tracking-widest">Total Nodes</div>
                    <div class="p-2 rounded-xl bg-blue-500/10 text-blue-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                </div>
                <div class="text-4xl font-black text-white">{{ $monitors->count() }}</div>
            </div>

            <!-- Online Status -->
            <div class="bg-slate-900 rounded-[2rem] p-8 border border-white/5 shadow-2xl shadow-slate-950/20">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-xs font-black text-slate-500 uppercase tracking-widest">Active</div>
                    <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                </div>
                <div class="text-4xl font-black text-emerald-500">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestResult?->is_up)->count() }}
                </div>
            </div>

            <!-- Offline Status -->
            <div class="bg-slate-900 rounded-[2rem] p-8 border border-white/5 shadow-2xl shadow-slate-950/20">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-xs font-black text-slate-500 uppercase tracking-widest">Down</div>
                    <div class="p-2 rounded-xl bg-red-500/10 text-red-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                </div>
                <div class="text-4xl font-black text-red-500">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestResult && ! $monitor->latestResult->is_up)->count() }}
                </div>
            </div>

            <!-- SEO Alerts -->
            <div class="bg-slate-900 rounded-[2rem] p-8 border border-white/5 shadow-2xl shadow-slate-950/20">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-xs font-black text-slate-500 uppercase tracking-widest">SEO Threats</div>
                    <div class="p-2 rounded-xl bg-orange-500/10 text-orange-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                </div>
                <div class="text-4xl font-black text-orange-500">
                    {{ $monitors->filter(fn ($monitor) => $monitor->latestSeoResult?->is_suspicious)->count() }}
                </div>
            </div>
        </section>

        <!-- Monitors Grid -->
        <section class="grid gap-10 lg:grid-cols-2">
            @forelse($monitors as $monitor)
                @php
                    $result = $monitor->latestResult;
                    $isUp = (bool) ($result?->is_up);
                @endphp

                <article class="bg-slate-900 rounded-[2.5rem] p-8 border border-white/5 shadow-2xl hover:border-orange-500/30 transition-all group relative overflow-hidden">
                    <!-- Status Indicator -->
                    <div class="absolute top-0 right-0 p-8">
                        <div class="flex items-center gap-2 px-4 py-1.5 rounded-full text-[10px] font-black tracking-[0.1em] {{ $isUp ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white' }} shadow-lg shadow-current/20">
                            <span class="h-2 w-2 rounded-full bg-white {{ $isUp ? 'animate-pulse' : '' }}"></span>
                            {{ $isUp ? 'ONLINE' : 'OFFLINE' }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <h2 class="text-2xl font-black text-white group-hover:text-orange-500 transition-colors">{{ $monitor->name }}</h2>
                        <a href="{{ $monitor->url }}" target="_blank" class="text-sm text-slate-500 font-mono flex items-center gap-1.5 hover:text-orange-400 transition-colors">
                            {{ $monitor->url }}
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </a>
                    </div>

                    <!-- Metrics -->
                    <div class="mt-10 grid grid-cols-3 gap-8">
                        <div>
                            <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Latency</div>
                            <div class="mt-1 text-xl font-bold text-white">{{ $result?->response_time ? number_format($result->response_time, 3).'s' : '---' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Protocol</div>
                            <div class="mt-1 text-xl font-bold text-white">{{ $result?->status_code ?? 'ERR' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Uptime 24h</div>
                            <div class="mt-1 text-xl font-bold text-orange-500">{{ $monitor->uptime_24h ?? 0 }}%</div>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div class="mt-8 h-32 w-full opacity-60 group-hover:opacity-100 transition-opacity">
                        <canvas id="chart-{{ $monitor->id }}"></canvas>
                    </div>

                    <!-- Footer Tags & Actions -->
                    <div class="mt-8 pt-8 border-t border-white/5 flex flex-wrap items-center justify-between gap-6">
                        <div class="flex items-center gap-2 flex-wrap">
                             <!-- SEO Status -->
                             <span class="px-3 py-1 rounded-lg text-[10px] font-black tracking-widest {{ $monitor->latestSeoResult?->is_suspicious ? 'bg-red-500 text-white' : 'bg-emerald-500 text-white' }}">
                                SEO: {{ $monitor->seo_enabled ? ($monitor->latestSeoResult?->is_suspicious ? 'SUSPICIOUS' : 'CLEAN') : 'DISABLED' }}
                             </span>

                             <!-- SSL Status -->
                             @if($monitor->ssl_expires_at)
                                @php
                                    $daysLeft = now()->diffInDays($monitor->ssl_expires_at, false);
                                    $sslColor = $daysLeft < 7 ? 'bg-red-500 text-white' : ($daysLeft < 30 ? 'bg-orange-500 text-white' : 'bg-slate-800 text-slate-400');
                                @endphp
                                <span class="px-3 py-1 rounded-lg {{ $sslColor }} text-[10px] font-black tracking-widest uppercase">
                                   SSL: {{ $daysLeft > 0 ? $daysLeft . ' Days' : 'Expired' }}
                                </span>
                             @endif

                             <span class="px-3 py-1 rounded-lg bg-slate-800 text-[10px] font-black text-slate-500 tracking-widest uppercase">{{ $monitor->interval }}s POLLING</span>
                        </div>

                        <div class="flex items-center gap-2">
                             <form method="POST" action="{{ route('monitors.check', $monitor) }}">
                                @csrf
                                <button class="p-3 rounded-2xl bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-600/20 active:scale-95 transition-all" title="Force Audit">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </button>
                            </form>
                            <a href="{{ route('monitors.edit', $monitor) }}" class="p-3 rounded-2xl bg-white/5 text-slate-500 hover:text-white hover:bg-white/10 transition-all">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </a>
                        </div>
                    </div>

                    @if($monitor->latestSeoResult?->is_suspicious)
                        <div class="mt-6 p-5 rounded-2xl bg-red-500/10 border border-red-500/20">
                            <div class="text-[10px] font-black text-red-500 uppercase tracking-[0.2em] mb-3 flex items-center gap-2">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                Security Anomalies Detected
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach(($monitor->latestSeoResult->detected_patterns ?? []) as $pattern)
                                    <span class="px-3 py-1 rounded-full bg-red-500 text-white text-[10px] font-black uppercase tracking-wider">{{ $pattern }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
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
                const accentColor = '#f97316';
                window.monitorCharts['{{ $monitor->id }}'] = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: @json($monitor->recent_checks->pluck('checked_at')->map(fn ($date) => optional($date)->format('H:i:s'))),
                        datasets: [{
                            data: @json($monitor->recent_checks->pluck('response_time')),
                            borderColor: accentColor,
                            backgroundColor: accentColor + '10',
                            fill: true,
                            tension: .4,
                            pointRadius: 0,
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
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
