<x-app-layout>
    <x-slot name="header_title">Server Resources</x-slot>

    <div class="space-y-8" x-data="serverMonitor(@js($initialServers ?? []))">
        <!-- Header Info -->
        <div class="glass rounded-3xl p-8 shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
            <div>
                <h3 class="text-2xl font-bold">Node Infrastructure</h3>
                <p class="text-slate-500 mt-1 text-sm">Real-time health and performance metrics from distributed nodes.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-xs font-bold uppercase tracking-widest text-slate-500">
                    Auto-refresh: 5s
                </div>
                <div class="px-4 py-2 rounded-xl bg-orange-100 dark:bg-orange-500/10 border border-orange-200 dark:border-orange-500/20 text-xs font-bold uppercase tracking-widest text-orange-600 dark:text-orange-400">
                    <span x-text="lastUpdate ? 'Sync: ' + lastUpdate : 'Waiting for sync...'"></span>
                </div>
            </div>
        </div>

        <div class="glass rounded-3xl p-6 shadow-xl">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-xl font-bold">Metric History</h3>
                    <p class="mt-1 text-sm text-slate-500">CPU, memory, and storage trends from stored agent samples.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <select x-model="selectedServerId" @change="loadHistory()" class="rounded-xl border-slate-200 bg-white text-sm text-slate-700 shadow-sm dark:border-white/10 dark:bg-slate-950 dark:text-slate-200">
                        <template x-for="server in servers" :key="'option-' + server.server_id">
                            <option :value="server.server_id" x-text="server.server_id"></option>
                        </template>
                    </select>
                    <select x-model="historyHours" @change="loadHistory()" class="rounded-xl border-slate-200 bg-white text-sm text-slate-700 shadow-sm dark:border-white/10 dark:bg-slate-950 dark:text-slate-200">
                        <option value="1">1 hour</option>
                        <option value="6">6 hours</option>
                        <option value="24">24 hours</option>
                        <option value="168">7 days</option>
                    </select>
                </div>
            </div>

            <div x-show="selectedServerId" class="mt-6 grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                    <div class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-500">CPU</div>
                    <div class="h-48">
                        <canvas id="server-history-cpu"></canvas>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                    <div class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-500">Memory</div>
                    <div class="h-48">
                        <canvas id="server-history-ram"></canvas>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                    <div class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-500">Storage</div>
                    <div class="h-48">
                        <canvas id="server-history-disk"></canvas>
                    </div>
                </div>
            </div>

            <div x-show="!selectedServerId" x-cloak class="mt-6 rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-white/10">
                Waiting for server metrics.
            </div>
        </div>

        <!-- Grid Container -->
        <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="server in servers" :key="server.server_id">
                <div class="glass rounded-[2.5rem] p-8 shadow-xl relative overflow-hidden group hover:scale-[1.02] transition-all duration-500 border-none">
                    <!-- Status Indicator -->
                    <div class="absolute top-8 right-8 flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full" :class="server.is_online ? 'bg-green-500 shadow-[0_0_12px_rgba(34,197,94,0.6)]' : 'bg-red-500 shadow-[0_0_12px_rgba(239,68,68,0.6)]'"></div>
                        <span class="text-[10px] font-bold uppercase tracking-widest" :class="server.is_online ? 'text-green-500' : 'text-red-500'" x-text="server.is_online ? 'Online' : 'Offline'"></span>
                    </div>

                    <!-- Server Meta -->
                    <div class="mb-8">
                        <div class="h-14 w-14 rounded-2xl bg-orange-500 flex items-center justify-center text-white mb-4 shadow-lg shadow-orange-500/20 group-hover:rotate-6 transition-transform">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v4a2 2 0 00-2-2m-14 0h14"></path></svg>
                        </div>
                        <h4 class="text-xl font-bold" x-text="server.server_id"></h4>
                        <p class="text-xs text-slate-500 font-medium" x-text="'Updated ' + formatTime(server.updated_at)"></p>
                    </div>

                    <!-- Progress Bars -->
                    <div class="space-y-6">
                        <!-- CPU -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold uppercase tracking-widest text-slate-500">
                                <span>Processor Load</span>
                                <span class="text-orange-600 dark:text-orange-400" x-text="server.cpu + '%'"></span>
                            </div>
                            <div class="h-2 w-full bg-slate-100 dark:bg-white/5 rounded-full overflow-hidden">
                                <div class="h-full bg-orange-500 transition-all duration-1000" :style="'width: ' + server.cpu + '%'"></div>
                            </div>
                        </div>

                        <!-- RAM -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold uppercase tracking-widest text-slate-500">
                                <span>Memory</span>
                                <span class="text-emerald-600 dark:text-emerald-400" x-text="server.ram_used + ' / ' + server.ram_total + ' GB'"></span>
                            </div>
                            <div class="h-2 w-full bg-slate-100 dark:bg-white/5 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 transition-all duration-1000" :style="'width: ' + (server.ram_used / server.ram_total * 100) + '%'"></div>
                            </div>
                        </div>

                        <!-- Disk -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold uppercase tracking-widest text-slate-500">
                                <span>Storage</span>
                                <span class="text-blue-600 dark:text-blue-400" x-text="server.disk_used + ' / ' + server.disk_total + ' GB'"></span>
                            </div>
                            <div class="h-2 w-full bg-slate-100 dark:bg-white/5 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 transition-all duration-1000" :style="'width: ' + (server.disk_used / server.disk_total * 100) + '%'"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty State -->
        <div x-show="servers.length === 0" x-cloak class="p-20 text-center glass rounded-[3rem] border-dashed border-2 border-slate-200 dark:border-white/10">
            <svg class="h-16 w-16 mx-auto mb-6 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            <h3 class="text-2xl font-bold">No data available</h3>
            <p class="text-slate-500 mt-2 max-w-md mx-auto">Configure your nodes to push metrics to the monitoring endpoint to start visualizing performance.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function serverMonitor(initialServers = []) {
            return {
                servers: initialServers,
                lastUpdate: null,
                selectedServerId: initialServers.length > 0 ? initialServers[0].server_id : null,
                historyHours: 6,
                charts: {},
                
                async init() {
                    await this.load();
                    await this.loadHistory();
                    setInterval(() => this.load(), 5000);
                },

                async load() {
                    try {
                        const res = await fetch('{{ route("server-resources.snapshot") }}');
                        const data = await res.json();
                        this.servers = data;
                        if (!this.selectedServerId && data.length > 0) {
                            this.selectedServerId = data[0].server_id;
                        }
                        this.lastUpdate = new Date().toLocaleTimeString();
                    } catch (e) {
                        console.error('Failed to sync server metrics');
                    }
                },

                async loadHistory() {
                    if (!this.selectedServerId) {
                        return;
                    }

                    try {
                        const params = new URLSearchParams({
                            server_id: this.selectedServerId,
                            hours: this.historyHours,
                        });
                        const res = await fetch('{{ route("server-resources.history") }}?' + params.toString());
                        const data = await res.json();
                        this.$nextTick(() => this.renderHistory(data));
                    } catch (e) {
                        console.error('Failed to load server metric history');
                    }
                },

                renderHistory(data) {
                    this.renderChart('cpu', 'server-history-cpu', data.labels, data.cpu, '#f97316');
                    this.renderChart('ram', 'server-history-ram', data.labels, data.ram, '#10b981');
                    this.renderChart('disk', 'server-history-disk', data.labels, data.disk, '#3b82f6');
                },

                renderChart(key, canvasId, labels, values, color) {
                    const canvas = document.getElementById(canvasId);
                    if (!canvas || !window.Chart) {
                        return;
                    }

                    if (this.charts[key]) {
                        this.charts[key].data.labels = labels;
                        this.charts[key].data.datasets[0].data = values;
                        this.charts[key].update();
                        return;
                    }

                    this.charts[key] = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                data: values,
                                borderColor: color,
                                backgroundColor: color + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 0,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: context => `${context.parsed.y ?? 0}%`,
                                    },
                                },
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { maxTicksLimit: 6 },
                                },
                                y: {
                                    min: 0,
                                    max: 100,
                                    ticks: {
                                        callback: value => value + '%',
                                    },
                                },
                            },
                        },
                    });
                },

                formatTime(iso) {
                    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
            }
        }
    </script>
</x-app-layout>
