<x-app-layout>
    <div x-data="{ 
        detailOpen: false, 
        selectedMonitor: null,
        commandBarOpen: false,
        openDetail(monitor) {
            this.selectedMonitor = monitor;
            this.detailOpen = true;
        }
    }" class="h-screen flex flex-col bg-[#0b0e14] text-slate-300 overflow-hidden">
        
        <!-- 1. Command Bar (Spotlight Style) -->
        <div x-show="commandBarOpen" 
             @keydown.window.escape="commandBarOpen = false"
             @keydown.window.ctrl.k.prevent="commandBarOpen = true"
             class="fixed inset-0 z-[100] flex items-start justify-center pt-20 px-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div class="w-full max-w-2xl bg-[#161b22] border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-center px-4 py-3 border-b border-white/5">
                    <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" class="w-full bg-transparent border-none text-white focus:ring-0 text-sm placeholder-slate-600" placeholder="Type a command (e.g. scan seo treasury.gov.my)...">
                </div>
                <div class="p-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest flex gap-4 bg-white/5">
                    <span>ESC to close</span>
                    <span>CTRL + K to open</span>
                </div>
            </div>
        </div>

        <!-- 2. Global Status Bar (DevOps Aggregates) -->
        <header class="flex-shrink-0 bg-[#161b22] border-b border-white/5 px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.6)]"></div>
                    <span class="text-xs font-black text-white uppercase tracking-widest">System Live</span>
                </div>
                
                <!-- Aggregated Metrics -->
                <div class="hidden md:flex items-center gap-10">
                    <div class="flex flex-col">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter">Nodes</span>
                        <span class="text-sm font-black text-white leading-none">{{ $monitors->count() }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter">Latency Avg</span>
                        <span class="text-sm font-black text-blue-400 leading-none">0.24s</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter">Uptime Avg</span>
                        <span class="text-sm font-black text-emerald-400 leading-none">99.8%</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter">SEO Alerts</span>
                        <span class="text-sm font-black text-orange-500 leading-none">{{ $monitors->filter(fn($m) => $m->latestSeoResult?->is_suspicious)->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button @click="commandBarOpen = true" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/5 text-[10px] font-bold text-slate-400 transition-all flex items-center gap-2">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    COMMAND (CTRL+K)
                </button>
            </div>
        </header>

        <!-- 3. Main Operational Content -->
        <main class="flex-1 flex overflow-hidden">
            
            <!-- Monitoring Table (High Density) -->
            <div class="flex-1 overflow-y-auto custom-scrollbar bg-[#0b0e14]">
                <table class="w-full text-left border-separate border-spacing-0">
                    <thead class="sticky top-0 z-10 bg-[#0b0e14]/80 backdrop-blur-md">
                        <tr class="text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-white/5">
                            <th class="px-6 py-4 border-b border-white/5">Service / Node</th>
                            <th class="px-4 py-4 border-b border-white/5">Status</th>
                            <th class="px-4 py-4 border-b border-white/5">Latency (ms)</th>
                            <th class="px-4 py-4 border-b border-white/5">Uptime (24h)</th>
                            <th class="px-4 py-4 border-b border-white/5">SSL</th>
                            <th class="px-4 py-4 border-b border-white/5">SEO Status</th>
                            <th class="px-4 py-4 border-b border-white/5">Health</th>
                            <th class="px-6 py-4 border-b border-white/5 text-right">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($monitors as $monitor)
                        @php
                            $result = $monitor->latestResult;
                            $isUp = (bool)($result?->is_up);
                            $seo = $monitor->latestSeoResult;
                            
                            // Mock Health Score Calculation
                            $health = 100;
                            if(!$isUp) $health -= 50;
                            if($seo?->is_suspicious) $health -= 30;
                        @endphp
                        <tr @click="openDetail(@json($monitor))" class="group hover:bg-white/[0.03] cursor-pointer transition-colors border-b border-white/5">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-2 w-2 rounded-full {{ $isUp ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.4)]' }}"></div>
                                    <div>
                                        <div class="text-xs font-black text-white">{{ $monitor->name }}</div>
                                        <div class="text-[9px] text-slate-500 font-mono mt-0.5 truncate max-w-[120px]">{{ $monitor->url }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] font-black px-2 py-0.5 rounded {{ $isUp ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' }}">
                                    {{ $isUp ? 'OK' : 'FAIL' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs font-bold {{ ($result?->response_time ?? 0) > 1 ? 'text-orange-400' : 'text-slate-300' }}">
                                    {{ $result?->response_time ? round($result->response_time * 1000) . 'ms' : '---' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-12 bg-white/5 h-1 rounded-full overflow-hidden">
                                        <div class="bg-emerald-500 h-full" style="width: {{ $monitor->uptime_24h ?? 0 }}%"></div>
                                    </div>
                                    <span class="text-[10px] font-bold text-slate-400">{{ $monitor->uptime_24h ?? 0 }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @php $days = $monitor->ssl_expires_at ? now()->diffInDays($monitor->ssl_expires_at, false) : null; @endphp
                                <span class="text-[10px] font-bold {{ $days < 7 ? 'text-red-500' : 'text-slate-500' }}">
                                    {{ $days !== null ? $days . 'd' : '---' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] font-black {{ $seo?->is_suspicious ? 'text-orange-500' : 'text-slate-500' }} uppercase tracking-wider">
                                    {{ $monitor->seo_enabled ? ($seo?->is_suspicious ? 'SUSPICIOUS' : 'CLEAN') : '---' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs font-black {{ $health > 80 ? 'text-emerald-500' : ($health > 50 ? 'text-orange-500' : 'text-red-500') }}">
                                    {{ $health }}
                                </div>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <div class="h-6 w-24 ml-auto opacity-50 group-hover:opacity-100 transition-opacity">
                                    <canvas id="spark-{{ $monitor->id }}"></canvas>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- 4. Detail Panel (Right Side Slide-out) -->
            <aside x-show="detailOpen" 
                   x-transition:enter="transition ease-out duration-300 transform"
                   x-transition:enter-start="translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-300 transform"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="translate-x-full"
                   class="w-[450px] bg-[#161b22] border-l border-white/5 flex flex-col z-50 shadow-2xl" x-cloak>
                
                <div class="p-6 border-b border-white/5 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black text-white" x-text="selectedMonitor?.name"></h2>
                        <p class="text-[10px] font-mono text-slate-500 mt-1" x-text="selectedMonitor?.url"></p>
                    </div>
                    <button @click="detailOpen = false" class="p-2 rounded-lg hover:bg-white/5 text-slate-500 hover:text-white transition-all">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-8 custom-scrollbar">
                    
                    <!-- Latency Main Graph -->
                    <div class="space-y-4">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Latency Trend (24h)</div>
                        <div class="h-40 w-full bg-black/20 rounded-xl border border-white/5 p-4">
                            <canvas id="detail-chart"></canvas>
                        </div>
                    </div>

                    <!-- SEO Forensics -->
                    <div class="space-y-4">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Security Forensics</div>
                        <div class="grid grid-cols-1 gap-2">
                            <template x-if="selectedMonitor?.latest_seo_result?.is_suspicious">
                                <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-xs text-red-400 font-bold">
                                    <div class="mb-2 uppercase tracking-tighter">Malicious Patterns Detected</div>
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="p in selectedMonitor?.latest_seo_result?.detected_patterns">
                                            <span class="px-2 py-0.5 bg-red-500 text-white rounded text-[9px]" x-text="p"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!selectedMonitor?.latest_seo_result?.is_suspicious">
                                <div class="p-4 rounded-xl bg-white/5 border border-white/5 text-xs text-slate-500 italic">No recent SEO threats identified.</div>
                            </template>
                        </div>
                    </div>

                    <!-- Real-time Log Stream (Mock Tail) -->
                    <div class="space-y-4">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Live Log Stream</div>
                        <div class="bg-black rounded-xl p-4 font-mono text-[10px] h-48 overflow-y-auto border border-white/10 space-y-1">
                            <div class="text-blue-400">[INFO] Fetching headers from 45.124.99.180...</div>
                            <div class="text-slate-500">[DEBUG] TLS Handshake completed in 42ms</div>
                            <div class="text-emerald-500">[SUCCESS] Response 200 OK (0.12s)</div>
                            <div class="text-slate-500 text-opacity-50">---------------------------------</div>
                            <div class="text-slate-400">00:42:15 :: GET /index.php 200</div>
                            <div class="text-slate-400">00:42:18 :: GET /api/v1/health 200</div>
                            <div class="text-orange-400">00:43:01 :: SEO Scanner: No cloaking detected.</div>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white/5 border-t border-white/5 flex gap-2">
                    <button class="flex-1 bg-orange-600 hover:bg-orange-500 text-white text-xs font-black py-3 rounded-xl transition-all shadow-lg shadow-orange-600/20">MANUAL AUDIT</button>
                    <button class="px-4 py-3 bg-white/5 hover:bg-white/10 text-slate-400 rounded-xl transition-all">EDIT</button>
                </div>
            </aside>
        </main>
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
        window.detailChart = null;

        @foreach($monitors as $monitor)
            (() => {
                const ctx = document.getElementById('spark-{{ $monitor->id }}').getContext('2d');
                window.sparkCharts['{{ $monitor->id }}'] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($monitor->recent_checks->pluck('checked_at')),
                        datasets: [{
                            data: @json($monitor->recent_checks->pluck('response_time')),
                            borderColor: '{{ (bool)($monitor->latestResult?->is_up) ? "#10b981" : "#ef4444" }}',
                            borderWidth: 1.5,
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

        // Initialize Detail Chart Placeholder
        const detailCtx = document.getElementById('detail-chart').getContext('2d');
        window.detailChart = new Chart(detailCtx, {
            type: 'line',
            data: {
                labels: Array(24).fill(''),
                datasets: [{
                    label: 'Latency',
                    data: Array(24).fill(0).map(() => Math.random() * 0.5),
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                    pointBackgroundColor: '#f97316'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { display: false } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 9 } } }
                }
            }
        });
    </script>
</x-app-layout>
