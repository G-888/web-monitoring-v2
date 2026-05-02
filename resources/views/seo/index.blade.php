<x-app-layout>
    <x-slot name="header_title">SEO Security & Integrity</x-slot>

    <div class="max-w-7xl mx-auto space-y-12 pb-24 px-4 sm:px-6 lg:px-8">
        
        <!-- Header & Manual Scan -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8 pt-6">
            <div class="max-w-2xl">
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">SEO Security & Integrity</h1>
                <p class="text-base text-slate-500 mt-2">Advanced protection against cloaking, malicious injections, and unauthorized site structural changes.</p>
            </div>
            
            <div class="w-full lg:w-auto">
                <form action="{{ route('seo-security.scan') }}" method="POST" class="flex items-center gap-3">
                    @csrf
                    <input type="url" name="url" placeholder="Quick Scan URL (e.g. https://...)" value="{{ old('url', $manual_url ?? '') }}" required 
                        class="w-full lg:w-80 rounded-2xl border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-5 py-3 text-sm focus:ring-2 focus:ring-orange-500 transition-all shadow-sm">
                    <button type="submit" class="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white text-sm font-bold rounded-2xl transition-all shadow-lg shadow-orange-600/20 whitespace-nowrap">
                        Run Forensic Scan
                    </button>
                </form>
            </div>
        </div>

        @if(session('manual_scan_result'))
            <div class="bg-white dark:bg-slate-900/50 rounded-[2.5rem] p-8 border border-orange-200 dark:border-orange-500/20 shadow-2xl shadow-orange-600/5 animate-in fade-in zoom-in-95 duration-500">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="p-3 rounded-2xl bg-orange-100 dark:bg-orange-500/20 text-orange-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white line-clamp-1">Forensic Result: {{ session('manual_url') }}</h3>
                            <p class="text-xs text-slate-500 uppercase font-bold tracking-widest mt-0.5">Manual Inspection Log</p>
                        </div>
                    </div>
                    <span class="text-xs font-black uppercase px-4 py-2 rounded-xl {{ session('manual_scan_result')['status'] === 'clean' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }} shadow-lg shadow-current/20">
                        {{ session('manual_scan_result')['status'] }}
                    </span>
                </div>
                
                @if(!empty(session('manual_scan_result')['findings']))
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-8">
                        @foreach(session('manual_scan_result')['findings'] as $finding)
                            <div class="flex items-center gap-3 p-4 rounded-2xl bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 text-sm text-red-600 font-bold">
                                <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                {{ $finding }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mb-8 p-4 rounded-2xl bg-green-50 dark:bg-green-500/10 border border-green-100 dark:border-green-500/20 text-sm text-green-600 font-bold flex items-center gap-3">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        No active threats or cloaking patterns detected in the current response.
                    </div>
                @endif

                <div x-data="{ open: false }" class="border-t border-slate-100 dark:border-white/5 pt-6">
                    <button @click="open = !open" type="button" class="group text-xs font-black text-slate-400 uppercase tracking-widest hover:text-slate-600 dark:hover:text-slate-200 transition-all flex items-center gap-2">
                        <span x-text="open ? 'Close Forensic Analysis' : 'Expand Forensic Analysis (Raw Data)'"></span>
                        <svg class="h-4 w-4 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <div x-show="open" x-transition class="mt-6 grid grid-cols-1 gap-6" x-cloak>
                        <div class="space-y-2">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">HTTP Headers (Status: {{ session('manual_scan_result')['status_code'] }})</div>
                            <pre class="p-6 bg-slate-900 rounded-[2rem] text-xs overflow-x-auto text-emerald-400 font-mono shadow-inner border border-white/5">{{ json_encode(session('manual_scan_result')['raw_headers'], JSON_PRETTY_PRINT) }}</pre>
                        </div>
                        <div class="space-y-2">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">HTML Response Snippet</div>
                            <pre class="p-6 bg-slate-900 rounded-[2rem] text-xs overflow-x-auto text-blue-400 font-mono shadow-inner border border-white/5">{{ session('manual_scan_result')['raw_body'] }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Main Status Overview -->
        <div class="bg-white dark:bg-slate-900/50 rounded-[2.5rem] overflow-hidden border border-slate-200 dark:border-white/10 shadow-xl shadow-slate-900/5">
            <div class="p-8 border-b border-slate-100 dark:border-white/5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Monitored Domains Security Status</h2>
                    <p class="text-sm text-slate-500 mt-1">Live overview of your infrastructure's SEO health.</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Global Status</div>
                        <div class="text-sm font-bold text-green-500">Infrastructure Protected</div>
                    </div>
                    <button onclick="window.location.reload()" class="p-3 rounded-2xl bg-slate-50 dark:bg-white/5 hover:bg-slate-100 dark:hover:bg-white/10 transition-all text-slate-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-slate-50/50 dark:bg-white/5">
                            <th class="px-8 py-5">Monitor Details</th>
                            <th class="px-8 py-5 text-center">Security Status</th>
                            <th class="px-8 py-5">Last Audit</th>
                            <th class="px-8 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach($monitors as $monitor)
                        <tr class="group hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-8 py-6">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $monitor->name }}</div>
                                <div class="text-xs text-slate-400 mt-1 font-mono tracking-tight">{{ $monitor->url }}</div>
                            </td>
                            <td class="px-8 py-6 text-center">
                                @php $latestScan = $recentScans->where('monitor_id', $monitor->id)->first(); @endphp
                                @if($latestScan)
                                    <span class="inline-flex items-center px-4 py-1.5 rounded-xl {{ $latestScan->status === 'clean' ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500' }} text-[11px] font-black tracking-widest uppercase">
                                        {{ $latestScan->status }}
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[11px] font-bold tracking-widest uppercase">Waiting...</span>
                                @endif
                            </td>
                            <td class="px-8 py-6 text-sm text-slate-500 font-medium">
                                {{ $latestScan ? $latestScan->scanned_at->diffForHumans() : 'No scans yet' }}
                            </td>
                            <td class="px-8 py-6 text-right">
                                <form action="{{ route('seo-security.scan') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="url" value="{{ $monitor->url }}">
                                    <button type="submit" class="p-3 rounded-2xl bg-slate-100 dark:bg-white/10 hover:bg-orange-600 hover:text-white transition-all shadow-sm hover:shadow-orange-600/30" title="Trigger Manual Audit">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Secondary Information Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            
            <!-- Recent Activity Log -->
            <div class="bg-white dark:bg-slate-900/50 rounded-[2.5rem] overflow-hidden border border-slate-200 dark:border-white/10 shadow-lg shadow-slate-900/5">
                <div class="p-8 border-b border-slate-100 dark:border-white/5">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Audit History</h2>
                    <p class="text-xs text-slate-500 uppercase font-bold tracking-widest mt-1">Live Security Log</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @foreach($recentScans as $scan)
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-8 py-5">
                                    <div class="font-bold text-sm text-slate-700 dark:text-slate-200">{{ $scan->monitor?->name ?? 'External Audit' }}</div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-[250px] mt-0.5 font-mono">{{ $scan->url }}</div>
                                </td>
                                <td class="px-8 py-5 text-right">
                                    <div class="flex flex-col items-end gap-1.5">
                                        <span class="text-[11px] font-black {{ $scan->status === 'clean' ? 'text-green-500' : 'text-red-500' }} flex items-center gap-1.5 uppercase tracking-wider">
                                            @if($scan->status === 'clean')
                                                <div class="h-1.5 w-1.5 rounded-full bg-green-500"></div>
                                            @else
                                                <div class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></div>
                                            @endif
                                            {{ $scan->status }}
                                        </span>
                                        <span class="text-xs text-slate-400 font-medium">{{ $scan->scanned_at->format('H:i') }}</span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Site Baseline Discovery -->
            <div class="bg-white dark:bg-slate-900/50 rounded-[2.5rem] overflow-hidden border border-slate-200 dark:border-white/10 shadow-lg shadow-slate-900/5">
                <div class="p-8 border-b border-slate-100 dark:border-white/5">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Site Structure Mapping</h2>
                    <p class="text-xs text-slate-500 uppercase font-bold tracking-widest mt-1">Orphaned & Hidden Page Discovery</p>
                </div>
                <div class="p-8">
                    @if($discoveredPages->count() > 0)
                        <div class="space-y-4">
                            @foreach($discoveredPages as $page)
                            <div class="flex items-center justify-between p-5 rounded-[1.5rem] bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5 group hover:border-orange-500/30 transition-all">
                                <div class="truncate pr-6">
                                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 group-hover:text-orange-500 transition-colors">{{ $page->monitor->name }}</div>
                                    <div class="text-sm font-mono text-slate-700 dark:text-slate-300 truncate">{{ $page->url_path }}</div>
                                </div>
                                <div class="text-xs text-slate-400 font-bold whitespace-nowrap">{{ $page->created_at->format('M d') }}</div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="p-6 rounded-[2rem] bg-slate-50 dark:bg-white/5 text-slate-200 mb-6 border border-dashed border-slate-200 dark:border-white/10">
                                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest">Initializing Map</h3>
                            <p class="text-xs text-slate-500 mt-3 px-12 leading-relaxed">The security crawler is currently discovering your site's structure to create a baseline for future threat detection.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
