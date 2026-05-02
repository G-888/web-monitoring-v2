<x-app-layout>
    <div x-data="{ 
        detailOpen: false, 
        selectedMonitor: null,
        commandBarOpen: false,
        openDetail(monitor) {
            this.selectedMonitor = monitor;
            this.detailOpen = true;
        }
    }" class="min-h-screen bg-[#06080b] text-slate-400 font-sans selection:bg-orange-500/30">
        
        <!-- Command Bar Overlay -->
        <div x-show="commandBarOpen" x-transition.opacity class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-sm px-4 pt-20" x-cloak>
            <div class="max-w-2xl mx-auto bg-[#111418] border border-white/5 rounded-3xl shadow-2xl overflow-hidden shadow-orange-500/5">
                <div class="p-6 flex items-center gap-4">
                    <svg class="h-6 w-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input @keydown.escape="commandBarOpen = false" autofocus type="text" class="w-full bg-transparent border-none text-xl text-white focus:ring-0 placeholder-slate-700 font-bold" placeholder="Run command...">
                </div>
            </div>
        </div>

        <div class="max-w-[1600px] mx-auto p-6 lg:p-10 space-y-12">
            
            <!-- Top Bento Stats -->
            <header class="grid grid-cols-2 lg:grid-cols-5 gap-6">
                <div class="col-span-2 lg:col-span-1 bg-gradient-to-br from-orange-600 to-orange-800 rounded-[2rem] p-8 text-white shadow-lg shadow-orange-600/20 flex flex-col justify-between">
                    <div class="text-[10px] font-black uppercase tracking-widest opacity-60">Control Center</div>
                    <div class="mt-4">
                        <div class="text-4xl font-black">{{ $monitors->count() }}</div>
                        <div class="text-xs font-bold opacity-80">Active Nodes</div>
                    </div>
                </div>

                @php
                    $online = $monitors->filter(fn ($m) => $m->latestResult?->is_up)->count();
                    $offline = $monitors->count() - $online;
                    $alerts = $monitors->filter(fn ($m) => $m->latestSeoResult?->is_suspicious)->count();
                @endphp

                <div class="bg-[#111418] border border-white/5 rounded-[2rem] p-8 flex flex-col justify-between hover:border-emerald-500/30 transition-all group">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 group-hover:text-emerald-500 transition-colors">Infrastructure</div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-white">{{ $online }}</div>
                        <div class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">System Online</div>
                    </div>
                </div>

                <div class="bg-[#111418] border border-white/5 rounded-[2rem] p-8 flex flex-col justify-between hover:border-red-500/30 transition-all group">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 group-hover:text-red-500 transition-colors">Critical</div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-white">{{ $offline }}</div>
                        <div class="text-[10px] font-bold text-red-500 uppercase tracking-widest">Nodes Down</div>
                    </div>
                </div>

                <div class="bg-[#111418] border border-white/5 rounded-[2rem] p-8 flex flex-col justify-between hover:border-orange-500/30 transition-all group">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 group-hover:text-orange-500 transition-colors">Security</div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-white">{{ $alerts }}</div>
                        <div class="text-[10px] font-bold text-orange-500 uppercase tracking-widest">SEO Alerts</div>
                    </div>
                </div>

                <div class="bg-[#111418] border border-white/5 rounded-[2rem] p-8 flex flex-col justify-between">
                    <div class="flex justify-between items-start">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-500">Global Health</div>
                        <div class="text-xs font-black text-emerald-500">99.2%</div>
                    </div>
                    <div class="mt-4 h-8 flex items-end gap-1">
                        @foreach(range(1, 15) as $i)
                            <div class="w-full bg-emerald-500/20 rounded-full" style="height: {{ rand(40, 100) }}%"></div>
                        @endforeach
                    </div>
                </div>
            </header>

            <!-- Main Monitoring Stack -->
            <section class="space-y-4">
                <div class="flex items-center justify-between px-4">
                    <h2 class="text-xs font-black text-slate-500 uppercase tracking-[0.3em]">Operational Nodes</h2>
                    <button @click="commandBarOpen = true" class="text-[10px] font-black text-orange-500 uppercase tracking-widest hover:underline">Launch Command (CTRL+K)</button>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    @foreach($monitors as $monitor)
                        @php
                            $result = $monitor->latestResult;
                            $isUp = (bool)($result?->is_up);
                            $seo = $monitor->latestSeoResult;
                            $health = 100;
                            if(!$isUp) $health -= 50;
                            if($seo?->is_suspicious) $health -= 30;
                        @endphp
                        
                        <div @click="openDetail(@json($monitor))" 
                             class="relative bg-[#111418] hover:bg-[#161a20] border border-white/5 rounded-3xl p-5 cursor-pointer transition-all flex items-center gap-8 group overflow-hidden">
                            
                            <!-- Left Status Bar -->
                            <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $isUp ? 'bg-emerald-500 shadow-[2px_0_10px_rgba(16,185,129,0.3)]' : 'bg-red-500 shadow-[2px_0_10px_rgba(239,68,68,0.3)]' }}"></div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3">
                                    <span class="text-base font-black text-white group-hover:text-orange-500 transition-colors truncate">{{ $monitor->name }}</span>
                                    <span class="px-2 py-0.5 rounded-md bg-white/5 text-[9px] font-bold text-slate-500 uppercase tracking-widest font-mono">{{ $result?->status_code ?? '---' }}</span>
                                </div>
                                <div class="text-[10px] text-slate-600 font-mono mt-1 truncate">{{ $monitor->url }}</div>
                            </div>

                            <!-- Visual Metrics -->
                            <div class="hidden lg:flex items-center gap-12 flex-shrink-0">
                                <div class="flex flex-col items-center">
                                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-tighter mb-1">Latency</div>
                                    <div class="text-sm font-black {{ ($result?->response_time ?? 0) > 1 ? 'text-orange-400' : 'text-white' }}">
                                        {{ $result?->response_time ? round($result->response_time * 1000) . 'ms' : '---' }}
                                    </div>
                                </div>

                                <div class="flex flex-col items-center">
                                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-tighter mb-1 text-center">Uptime</div>
                                    <div class="relative flex items-center justify-center">
                                        <svg class="h-8 w-8 -rotate-90">
                                            <circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="3" fill="transparent" class="text-white/5" />
                                            <circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="3" fill="transparent" 
                                                    stroke-dasharray="88" 
                                                    stroke-dashoffset="{{ 88 - (88 * ($monitor->uptime_24h ?? 0) / 100) }}" 
                                                    class="text-emerald-500" />
                                        </svg>
                                        <span class="absolute text-[8px] font-black text-white">{{ $monitor->uptime_24h ?? 0 }}%</span>
                                    </div>
                                </div>

                                <div class="flex flex-col items-center w-24">
                                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-tighter mb-1">Trend</div>
                                    <div class="h-6 w-full">
                                        <canvas id="spark-{{ $monitor->id }}"></canvas>
                                    </div>
                                </div>

                                <div class="flex flex-col items-center">
                                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-tighter mb-1">Security</div>
                                    <div class="flex items-center gap-1">
                                        @if($monitor->seo_enabled)
                                            <div class="h-2 w-2 rounded-full {{ $seo?->is_suspicious ? 'bg-orange-500 animate-pulse shadow-[0_0_8px_rgba(249,115,22,0.6)]' : 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' }}"></div>
                                            <span class="text-[10px] font-black text-slate-300">{{ $seo?->is_suspicious ? 'ALERT' : 'SAFE' }}</span>
                                        @else
                                            <span class="text-[10px] font-black text-slate-600 italic">OFF</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-col items-center">
                                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-tighter mb-1">Health</div>
                                    <div class="text-sm font-black {{ $health > 80 ? 'text-emerald-500' : ($health > 50 ? 'text-orange-500' : 'text-red-500') }}">
                                        {{ $health }}%
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 flex-shrink-0 ml-auto">
                                <form method="POST" action="{{ route('monitors.check', $monitor) }}" @click.stop>
                                    @csrf
                                    <button class="p-3 rounded-2xl bg-white/5 hover:bg-orange-600 text-slate-500 hover:text-white transition-all">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <!-- Right Side Detail Side-sheet -->
        <div x-show="detailOpen" 
             class="fixed inset-0 z-[110] flex justify-end overflow-hidden" x-cloak>
            
            <div x-show="detailOpen" x-transition.opacity @click="detailOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

            <aside x-show="detailOpen" 
                   x-transition:enter="transition ease-out duration-300 transform"
                   x-transition:enter-start="translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-300 transform"
                   class="relative w-full max-w-lg bg-[#0d1117] border-l border-white/5 flex flex-col h-full shadow-2xl">
                
                <div class="p-8 border-b border-white/5 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-black text-white tracking-tight" x-text="selectedMonitor?.name"></h2>
                        <div class="text-xs font-mono text-slate-500 mt-1" x-text="selectedMonitor?.url"></div>
                    </div>
                    <button @click="detailOpen = false" class="p-2 rounded-xl hover:bg-white/5 text-slate-500 transition-all">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-8 space-y-10 custom-scrollbar">
                    
                    <!-- Performance Matrix -->
                    <div class="space-y-4">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em]">Performance Matrix</div>
                        <div class="h-48 w-full bg-black/40 rounded-3xl border border-white/5 p-6 shadow-inner">
                            <canvas id="detail-chart"></canvas>
                        </div>
                    </div>

                    <!-- Security Insights -->
                    <div class="space-y-4">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em]">Security Insights</div>
                        <template x-if="selectedMonitor?.latest_seo_result?.is_suspicious">
                            <div class="p-6 rounded-3xl bg-red-500/10 border border-red-500/20">
                                <div class="text-xs font-black text-red-500 uppercase tracking-widest mb-4">Anomalous Patterns Detected</div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="p in selectedMonitor?.latest_seo_result?.detected_patterns">
                                        <span class="px-3 py-1 bg-red-500 text-white rounded-lg text-[10px] font-black" x-text="p"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="!selectedMonitor?.latest_seo_result?.is_suspicious">
                            <div class="p-6 rounded-3xl bg-white/5 border border-white/5 flex items-center gap-4 text-slate-500">
                                <svg class="h-6 w-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01"></path></svg>
                                <span class="text-xs font-bold uppercase tracking-widest">No Active Security Threats</span>
                            </div>
                        </template>
                    </div>

                    <!-- Event Timeline (Mock) -->
                    <div class="space-y-4">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em]">Incident Timeline</div>
                        <div class="space-y-4 relative border-l border-white/5 ml-2 pl-6">
                            <div class="relative">
                                <div class="absolute -left-[31px] top-1 h-3 w-3 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></div>
                                <div class="text-xs font-bold text-white">System Verified Online</div>
                                <div class="text-[10px] text-slate-500 mt-1">Today, 00:42 AM - Status 200 OK</div>
                            </div>
                            <div class="relative opacity-60">
                                <div class="absolute -left-[31px] top-1 h-3 w-3 rounded-full bg-slate-700"></div>
                                <div class="text-xs font-bold text-slate-300">Routine SEO Audit</div>
                                <div class="text-[10px] text-slate-500 mt-1">Yesterday, 11:20 PM - No issues found</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-8 bg-black/20 border-t border-white/5 grid grid-cols-2 gap-4">
                    <button class="bg-orange-600 hover:bg-orange-500 text-white text-xs font-black py-4 rounded-2xl transition-all shadow-lg shadow-orange-600/20">MANUAL RE-SCAN</button>
                    <button class="bg-white/5 hover:bg-white/10 text-white text-xs font-black py-4 rounded-2xl transition-all border border-white/5">NODE CONFIG</button>
                </div>
            </aside>
        </div>

    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.1); }
        [x-cloak] { display: none !important; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.sparkCharts = {};
        @foreach($monitors as $monitor)
            (() => {
                const ctx = document.getElementById('spark-{{ $monitor->id }}').getContext('2d');
                window.sparkCharts['{{ $monitor->id }}'] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($monitor->recent_checks->pluck('checked_at')),
                        datasets: [{
                            data: @json($monitor->recent_checks->pluck('response_time')),
                            borderColor: '{{ $isUp ? "#10b981" : "#ef4444" }}',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { enabled: false } },
                        scales: { x: { display: false }, y: { display: false } }
                    }
                });
            })();
        @endforeach

        const detailCtx = document.getElementById('detail-chart').getContext('2d');
        window.detailChart = new Chart(detailCtx, {
            type: 'line',
            data: {
                labels: Array(24).fill(''),
                datasets: [{
                    data: Array(24).fill(0).map(() => Math.random() * 0.5),
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.05)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#f97316',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { display: false } },
                    y: { grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { color: '#475569', font: { size: 9, weight: 'bold' } } }
                }
            }
        });
    </script>
</x-app-layout>
