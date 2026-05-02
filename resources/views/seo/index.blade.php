<x-app-layout>
    <x-slot name="header_title">SEO Security & Integrity</x-slot>

    <div class="max-w-7xl mx-auto space-y-10 pb-20">
        
        <!-- Header & Manual Scan -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">SEO Security & Integrity</h1>
                <p class="text-sm text-slate-500 mt-1">Monitor cloaking, suspicious injections, and site structural changes.</p>
            </div>
            
            <div class="w-full md:w-auto">
                <form action="{{ route('seo-security.scan') }}" method="POST" class="flex gap-2">
                    @csrf
                    <input type="url" name="url" placeholder="Quick Scan URL..." value="{{ old('url', $manual_url ?? '') }}" required 
                        class="w-64 rounded-xl border-slate-200 dark:border-white/10 bg-white/5 px-4 py-2 text-xs focus:ring-orange-500 focus:border-orange-500 shadow-sm">
                    <button type="submit" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-orange-600/20 whitespace-nowrap">
                        Scan Now
                    </button>
                </form>
            </div>
        </div>

        @if(session('manual_scan_result'))
            <div class="glass rounded-3xl p-6 border border-orange-200 dark:border-orange-500/20 shadow-xl shadow-orange-600/5 animate-in fade-in slide-in-from-top-4 duration-500">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold flex items-center gap-2 text-sm">
                        <span class="p-1.5 rounded-lg bg-orange-100 dark:bg-orange-500/20 text-orange-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01"></path></svg>
                        </span>
                        Manual Scan: {{ session('manual_url') }}
                    </h3>
                    <span class="text-[9px] font-bold uppercase px-2 py-1 rounded-lg {{ session('manual_scan_result')['status'] === 'clean' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">
                        {{ session('manual_scan_result')['status'] }}
                    </span>
                </div>
                
                @if(!empty(session('manual_scan_result')['findings']))
                    <div class="space-y-2 mb-4">
                        @foreach(session('manual_scan_result')['findings'] as $finding)
                            <div class="flex items-center gap-2 text-[11px] text-red-600 font-medium">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                {{ $finding }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div x-data="{ open: false }" class="border-t border-black/5 dark:border-white/5 pt-4">
                    <button @click="open = !open" type="button" class="text-[9px] font-bold text-slate-400 uppercase hover:text-slate-600 transition-colors flex items-center gap-1">
                        <span x-text="open ? 'Hide Forensic Data' : 'View Forensic Data'"></span>
                        <svg class="h-2.5 w-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <div x-show="open" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4" x-cloak>
                        <div>
                            <div class="text-[9px] font-bold text-slate-500 uppercase mb-2">Security Headers</div>
                            <pre class="p-3 bg-black/5 dark:bg-black/40 rounded-xl text-[9px] overflow-x-auto text-slate-600 dark:text-slate-400 font-mono">{{ json_encode(session('manual_scan_result')['raw_headers'], JSON_PRETTY_PRINT) }}</pre>
                        </div>
                        <div>
                            <div class="text-[9px] font-bold text-slate-500 uppercase mb-2">Raw Content Snippet ({{ session('manual_scan_result')['status_code'] }})</div>
                            <pre class="p-3 bg-black/5 dark:bg-black/40 rounded-xl text-[9px] overflow-x-auto text-slate-600 dark:text-slate-400 font-mono">{{ session('manual_scan_result')['raw_body'] }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="glass rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-2xl bg-red-500/10 text-red-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ $suspiciousScans->count() }}</div>
                        <div class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Active Threats</div>
                    </div>
                </div>
            </div>
            <div class="glass rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-2xl bg-blue-500/10 text-blue-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ $discoveredPages->count() }}</div>
                        <div class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Indexed Pages</div>
                    </div>
                </div>
            </div>
            <div class="glass rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-2xl bg-orange-500/10 text-orange-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ $monitors->count() }}</div>
                        <div class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Active Baselines</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Critical Security Alerts -->
        @if($suspiciousScans->count() > 0)
        <div class="glass rounded-3xl overflow-hidden border border-red-200 dark:border-red-500/20 shadow-lg shadow-red-500/5">
            <div class="p-6 bg-red-500 text-white flex items-center gap-3">
                <svg class="h-6 w-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <h2 class="text-lg font-bold">CRITICAL ACTION REQUIRED</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-bold text-red-700 dark:text-red-300 uppercase tracking-widest bg-red-50 dark:bg-red-950/20">
                            <th class="px-6 py-4">Domain</th>
                            <th class="px-6 py-4">Threat Type</th>
                            <th class="px-6 py-4">Findings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-200 dark:divide-red-950/20">
                        @foreach($suspiciousScans as $scan)
                        <tr class="text-sm">
                            <td class="px-6 py-4 font-bold">{{ $scan->monitor?->name ?? 'Manual Scan' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-lg bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-300 font-bold text-[9px]">
                                    {{ str_contains(implode('', $scan->findings ?? []), 'CLOAKING') ? 'CLOAKING' : 'INJECTION/SPAM' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                {{ implode(', ', $scan->findings ?? []) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Monitored Domains Status -->
        <div class="glass rounded-3xl overflow-hidden border border-slate-200 dark:border-white/10 shadow-sm">
            <div class="p-6 border-b border-slate-200 dark:border-white/10 flex items-center justify-between bg-slate-50/50 dark:bg-white/5">
                <div>
                    <h2 class="text-base font-bold">Monitored Domains Status</h2>
                    <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Infrastructure Health Overview</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-50/50 dark:bg-white/5">
                            <th class="px-6 py-4">Domain</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4">Last Check</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                        @foreach($monitors as $monitor)
                        <tr class="text-sm hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-xs">{{ $monitor->name }}</div>
                                <div class="text-[9px] text-slate-400 font-mono">{{ $monitor->url }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php $latestScan = $recentScans->where('monitor_id', $monitor->id)->first(); @endphp
                                @if($latestScan)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg {{ $latestScan->status === 'clean' ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500' }} text-[9px] font-bold tracking-widest">
                                        {{ strtoupper($latestScan->status) }}
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[9px] font-bold tracking-widest uppercase">Pending...</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-[10px] text-slate-500 uppercase font-bold">
                                {{ $latestScan ? $latestScan->scanned_at->diffForHumans() : 'Never' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('seo-security.scan') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="url" value="{{ $monitor->url }}">
                                    <button type="submit" class="p-1.5 rounded-lg bg-slate-100 dark:bg-white/10 hover:bg-orange-600 hover:text-white transition-all shadow-sm" title="Re-scan Domain">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <!-- Recent Activity -->
            <div class="glass rounded-3xl overflow-hidden border border-slate-200 dark:border-white/10 shadow-sm">
                <div class="p-6 border-b border-slate-200 dark:border-white/10">
                    <h2 class="text-base font-bold">Recent Scan History</h2>
                    <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Last 20 automated events</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                            @foreach($recentScans as $scan)
                            <tr class="text-sm hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-[11px]">{{ $scan->monitor?->name ?? 'Manual' }}</div>
                                    <div class="text-[9px] text-slate-500 truncate max-w-[150px] font-mono">{{ $scan->url }}</div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="text-[9px] font-bold {{ $scan->status === 'clean' ? 'text-green-500' : 'text-red-500' }} flex items-center gap-1 uppercase tracking-widest">
                                            @if($scan->status === 'clean')
                                                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            @else
                                                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            @endif
                                            {{ $scan->status }}
                                        </span>
                                        <span class="text-[9px] text-slate-400 font-bold">{{ $scan->scanned_at->format('H:i:s') }}</span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Internal Page Baseline -->
            <div class="glass rounded-3xl overflow-hidden border border-slate-200 dark:border-white/10 shadow-sm">
                <div class="p-6 border-b border-slate-200 dark:border-white/10">
                    <h2 class="text-base font-bold">Site Structure Baseline</h2>
                    <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Orphaned/Hidden page discovery</p>
                </div>
                <div class="p-6">
                    @if($discoveredPages->count() > 0)
                        <div class="grid grid-cols-1 gap-3">
                            @foreach($discoveredPages as $page)
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50/50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                                <div class="truncate pr-4">
                                    <div class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">{{ $page->monitor->name }}</div>
                                    <div class="text-[11px] font-mono text-slate-600 dark:text-slate-300 truncate">{{ $page->url_path }}</div>
                                </div>
                                <span class="text-[9px] text-slate-400 font-bold whitespace-nowrap">{{ $page->created_at->format('M d, H:i') }}</span>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="p-4 rounded-full bg-slate-100 dark:bg-white/5 text-slate-300 mb-4 border border-dashed border-slate-300 dark:border-white/10">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">No Pages Indexed</h3>
                            <p class="text-[10px] text-slate-500 mt-2 px-6">Crawler is mapping your site structure to detect unauthorized hidden pages.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
