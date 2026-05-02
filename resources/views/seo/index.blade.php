<x-app-layout>
    <x-slot name="header_title">SEO Security & Integrity</x-slot>

    <div class="space-y-8">
        <!-- Manual Scan Form -->
        <div class="glass rounded-3xl p-6 border border-slate-200 dark:border-white/10">
            <h2 class="text-lg font-bold mb-4">Run Manual SEO Scan</h2>
            <form action="{{ route('seo-security.scan') }}" method="POST" class="flex gap-4">
                @csrf
                <input type="url" name="url" placeholder="https://example.com" value="{{ old('url', $manual_url ?? '') }}" required 
                    class="flex-1 rounded-2xl border-slate-200 dark:border-white/10 bg-white/5 px-4 py-2 text-sm focus:ring-orange-500 focus:border-orange-500">
                <button type="submit" class="px-6 py-2 bg-orange-600 hover:bg-orange-700 text-white font-bold rounded-2xl transition-all shadow-lg shadow-orange-600/20">
                    Scan URL
                </button>
            </form>

            @if(session('manual_scan_result'))
                <div class="mt-6 p-6 rounded-2xl {{ session('manual_scan_result')['status'] === 'clean' ? 'bg-green-50 dark:bg-green-500/10 border-green-200' : 'bg-red-50 dark:bg-red-500/10 border-red-200' }} border">
                    <h3 class="font-bold mb-2">Scan Results for: {{ session('manual_url') }}</h3>
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-xs font-bold uppercase px-2 py-1 rounded-lg {{ session('manual_scan_result')['status'] === 'clean' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">
                            {{ session('manual_scan_result')['status'] }}
                        </span>
                    </div>
                    
                    @if(!empty(session('manual_scan_result')['findings']))
                        <div class="text-sm font-bold text-red-700 dark:text-red-400 mb-2">Findings:</div>
                        <ul class="list-disc list-inside text-xs space-y-1">
                            @foreach(session('manual_scan_result')['findings'] as $finding)
                                <li>{{ $finding }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-green-700 dark:text-green-400">No security threats detected in the live content.</p>
                    @endif
                </div>
            @endif
        </div>
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glass rounded-3xl p-6 border border-slate-200 dark:border-white/10">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 rounded-2xl bg-red-100 dark:bg-red-500/10 flex items-center justify-center text-red-600 dark:text-red-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-500 uppercase tracking-wider">Threats Detected</div>
                        <div class="text-2xl font-bold">{{ $suspiciousScans->count() }}</div>
                    </div>
                </div>
            </div>
            
            <div class="glass rounded-3xl p-6 border border-slate-200 dark:border-white/10">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 rounded-2xl bg-blue-100 dark:bg-blue-500/10 flex items-center justify-center text-blue-600 dark:text-blue-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-500 uppercase tracking-wider">Indexed Pages</div>
                        <div class="text-2xl font-bold">{{ $discoveredPages->count() }}</div>
                    </div>
                </div>
            </div>

            <div class="glass rounded-3xl p-6 border border-slate-200 dark:border-white/10">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 rounded-2xl bg-orange-100 dark:bg-orange-500/10 flex items-center justify-center text-orange-600 dark:text-orange-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-500 uppercase tracking-wider">File Baselines</div>
                        <div class="text-2xl font-bold">{{ $fileChanges->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Threats -->
        @if($suspiciousScans->count() > 0)
        <div class="glass rounded-3xl overflow-hidden border border-red-200 dark:border-red-500/20 bg-red-50/30 dark:bg-red-500/5">
            <div class="p-6 border-b border-red-200 dark:border-red-500/20 flex items-center justify-between">
                <h2 class="text-lg font-bold text-red-800 dark:text-red-400">Critical Security Alerts</h2>
                <span class="px-3 py-1 rounded-full bg-red-500 text-white text-xs font-bold animate-pulse">ACTION REQUIRED</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-xs font-bold text-red-800/50 dark:text-red-400/50 uppercase tracking-widest">
                            <th class="px-6 py-4">Monitor</th>
                            <th class="px-6 py-4">Threat Type</th>
                            <th class="px-6 py-4">Findings</th>
                            <th class="px-6 py-4">Detected At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-200 dark:divide-red-500/10">
                        @foreach($suspiciousScans as $scan)
                        <tr class="text-sm">
                            <td class="px-6 py-4 font-bold">{{ $scan->monitor?->name ?? 'Manual Scan' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-lg bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-300 font-bold text-[10px]">
                                    {{ str_contains(implode('', $scan->findings ?? []), 'CLOAKING') ? 'CLOAKING' : 'INJECTION/SPAM' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-red-600 dark:text-red-300">
                                {{ implode(', ', $scan->findings ?? []) }}
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $scan->scanned_at->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Activity -->
            <div class="glass rounded-3xl overflow-hidden border border-slate-200 dark:border-white/10">
                <div class="p-6 border-b border-slate-200 dark:border-white/10">
                    <h2 class="text-lg font-bold">Recent SEO Scans</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                            @foreach($recentScans as $scan)
                            <tr class="text-sm hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold">{{ $scan->monitor?->name ?? 'Manual Scan' }}</div>
                                    <div class="text-xs text-slate-500 truncate max-w-xs">{{ $scan->url }}</div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($scan->status === 'clean')
                                        <span class="text-green-500 font-bold text-xs flex items-center justify-end gap-1">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Clean
                                        </span>
                                    @else
                                        <span class="text-red-500 font-bold text-xs">Suspicious</span>
                                    @endif
                                    <div class="text-[10px] text-slate-400 mt-1">{{ $scan->scanned_at->format('H:i:s') }}</div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Page Discovery -->
            <div class="glass rounded-3xl overflow-hidden border border-slate-200 dark:border-white/10">
                <div class="p-6 border-b border-slate-200 dark:border-white/10">
                    <h2 class="text-lg font-bold">Internal Page Baseline</h2>
                </div>
                <div class="p-6 space-y-4">
                    @foreach($discoveredPages as $page)
                    <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                        <div class="min-w-0">
                            <div class="text-xs font-bold truncate">{{ $page->url }}</div>
                            <div class="text-[10px] text-slate-500">Seen: {{ $page->last_seen_at->diffForHumans() }}</div>
                        </div>
                        <div class="shrink-0">
                            <span class="px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 text-[9px] font-bold uppercase">Discovered</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
