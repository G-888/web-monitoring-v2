<x-app-layout>
    <x-slot name="header_title">SEO Security & Integrity</x-slot>

    <div class="max-w-7xl mx-auto space-y-8 pb-20 px-4 pt-4">
        
        <!-- Header & Quick Scan -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 bg-slate-900 p-8 rounded-[2rem] border border-white/5 shadow-2xl">
            <div class="space-y-1">
                <h1 class="text-3xl font-black tracking-tight text-white flex items-center gap-3">
                    <span class="text-orange-500">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01"></path></svg>
                    </span>
                    SEO Security
                </h1>
                <p class="text-slate-400 font-medium">Real-time cloaking and injection monitoring.</p>
            </div>
            
            <form action="{{ route('seo-security.scan') }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="url" name="url" placeholder="Forensic URL Check..." value="{{ old('url', $manual_url ?? '') }}" required 
                    class="w-full lg:w-72 rounded-xl border-none bg-white/5 px-4 py-3 text-sm text-white focus:ring-2 focus:ring-orange-500 transition-all placeholder:text-slate-600">
                <button type="submit" class="px-6 py-3 bg-orange-600 hover:bg-orange-500 text-white text-sm font-black rounded-xl transition-all shadow-lg shadow-orange-600/20 active:scale-95">
                    SCAN
                </button>
            </form>
        </div>

        @if(session('manual_scan_result'))
            <div class="bg-slate-900 rounded-[2rem] p-8 border-2 {{ session('manual_scan_result')['status'] === 'clean' ? 'border-emerald-500/30' : 'border-red-500/30' }} shadow-2xl animate-in fade-in zoom-in-95 duration-500">
                <div class="flex items-center justify-between mb-8">
                    <div class="space-y-1">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Forensic Analysis Report</div>
                        <h3 class="text-xl font-bold text-white">{{ session('manual_url') }}</h3>
                    </div>
                    <div class="px-6 py-2 rounded-full font-black text-sm uppercase tracking-widest {{ session('manual_scan_result')['status'] === 'clean' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white' }}">
                        {{ session('manual_scan_result')['status'] }}
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    @forelse(session('manual_scan_result')['findings'] as $finding)
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-xs text-red-400 font-bold">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                            {{ $finding }}
                        </div>
                    @empty
                        <div class="col-span-2 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400 font-bold flex items-center gap-3">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            No malicious patterns found in live response.
                        </div>
                    @endforelse
                </div>

                <div x-data="{ open: false }">
                    <button @click="open = !open" class="text-[10px] font-black text-slate-500 hover:text-white transition-colors uppercase tracking-[0.2em] flex items-center gap-2">
                        <span x-text="open ? '[-] Hide Debug' : '[+] View Headers & Source'"></span>
                    </button>
                    <div x-show="open" class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4" x-cloak>
                        <pre class="p-4 bg-black/40 rounded-xl text-[10px] font-mono text-emerald-500 overflow-x-auto border border-white/5">{{ json_encode(session('manual_scan_result')['raw_headers'], JSON_PRETTY_PRINT) }}</pre>
                        <pre class="p-4 bg-black/40 rounded-xl text-[10px] font-mono text-blue-400 overflow-x-auto border border-white/5">{{ session('manual_scan_result')['raw_body'] }}</pre>
                    </div>
                </div>
            </div>
        @endif

        <!-- Monitoring Grid -->
        <div class="bg-slate-900 rounded-[2rem] border border-white/5 overflow-hidden shadow-2xl">
            <div class="p-8 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    Infrastructure Baseline
                </h2>
                <div class="flex items-center gap-4">
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">{{ $monitors->count() }} ACTIVE NODES</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-500 uppercase tracking-widest bg-white/5 border-y border-white/5">
                            <th class="px-8 py-4">Node Address</th>
                            <th class="px-8 py-4 text-center">Security Status</th>
                            <th class="px-8 py-4">Last Audit</th>
                            <th class="px-8 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($monitors as $monitor)
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-8 py-6">
                                <div class="font-bold text-white group-hover:text-orange-500 transition-colors">{{ $monitor->name }}</div>
                                <div class="text-xs text-slate-500 font-mono mt-1">{{ $monitor->url }}</div>
                            </td>
                            <td class="px-8 py-6 text-center">
                                @php $latestScan = $recentScans->where('monitor_id', $monitor->id)->first(); @endphp
                                @if($latestScan)
                                    <span class="inline-block px-4 py-1.5 rounded-lg text-[10px] font-black tracking-widest {{ $latestScan->status === 'clean' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white' }}">
                                        {{ strtoupper($latestScan->status) }}
                                    </span>
                                @else
                                    <span class="text-[10px] font-bold text-slate-600 uppercase tracking-widest">PENDING</span>
                                @endif
                            </td>
                            <td class="px-8 py-6 text-xs text-slate-400 font-medium">
                                {{ $latestScan ? $latestScan->scanned_at->diffForHumans() : 'No data' }}
                            </td>
                            <td class="px-8 py-6 text-right">
                                <form action="{{ route('seo-security.scan') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="url" value="{{ $monitor->url }}">
                                    <button type="submit" class="p-3 rounded-xl bg-white/5 hover:bg-orange-600 text-slate-400 hover:text-white transition-all">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- History & Baseline -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Scan Log -->
            <div class="lg:col-span-1 bg-slate-900 rounded-[2rem] border border-white/5 overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-white/5">
                    <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest">Audit Event Log</h2>
                </div>
                <div class="max-h-[500px] overflow-y-auto">
                    <div class="divide-y divide-white/5">
                        @foreach($recentScans as $scan)
                        <div class="p-5 flex items-center justify-between group hover:bg-white/[0.02]">
                            <div class="truncate pr-4">
                                <div class="text-xs font-bold text-white truncate">{{ $scan->monitor?->name ?? 'External' }}</div>
                                <div class="text-[10px] text-slate-500 truncate mt-1 font-mono">{{ $scan->url }}</div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-[10px] font-black {{ $scan->status === 'clean' ? 'text-emerald-500' : 'text-red-500' }} uppercase">{{ $scan->status }}</div>
                                <div class="text-[9px] text-slate-600 font-bold mt-1">{{ $scan->scanned_at->format('H:i') }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Discovered Pages -->
            <div class="lg:col-span-2 bg-slate-900 rounded-[2rem] border border-white/5 overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest">Site Map Baseline</h2>
                    <span class="text-[9px] font-bold text-slate-600 uppercase">Detection Active</span>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[500px] overflow-y-auto">
                    @forelse($discoveredPages as $page)
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5 group hover:border-orange-500/30 transition-all">
                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 group-hover:text-orange-500">{{ $page->monitor->name }}</div>
                        <div class="text-xs font-mono text-slate-300 truncate">{{ $page->url_path }}</div>
                    </div>
                    @empty
                    <div class="col-span-2 flex flex-col items-center justify-center py-20 text-slate-600">
                        <svg class="h-12 w-12 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <p class="text-[10px] font-black uppercase mt-4 tracking-widest">Building initial structure map...</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
