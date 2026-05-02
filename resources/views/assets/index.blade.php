<x-app-layout>
    <x-slot name="header_title">Ultimate Asset Intelligence</x-slot>

    <div class="max-w-[1600px] mx-auto space-y-8 pb-20 px-6 pt-6">
        
        <!-- Command Header -->
        <div class="bg-slate-950 p-10 rounded-[2.5rem] border border-white/5 shadow-2xl flex flex-col lg:flex-row lg:items-center justify-between gap-8 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative z-10">
                <h1 class="text-4xl font-black text-white tracking-tighter flex items-center gap-4">
                    <span class="p-3 bg-blue-600 rounded-2xl shadow-lg shadow-blue-600/30">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    Asset Intelligence
                </h1>
                <p class="text-slate-500 mt-2 font-bold uppercase tracking-[0.2em] text-[10px]">Digital Footprint & Global Reputation Analysis</p>
            </div>
            
            <form action="{{ route('assets.scan') }}" method="POST" class="relative z-10 flex items-center gap-3">
                @csrf
                <input type="text" name="url" placeholder="Domain or IP..." required 
                    class="w-full lg:w-96 rounded-2xl border-none bg-white/5 px-6 py-4 text-sm text-white focus:ring-2 focus:ring-blue-500 transition-all placeholder:text-slate-700 font-bold">
                <button type="submit" class="px-8 py-4 bg-white text-black hover:bg-blue-500 hover:text-white text-xs font-black rounded-2xl transition-all shadow-xl active:scale-95">
                    SCAN ASSET
                </button>
            </form>
        </div>

        @if(session('manual_asset_result'))
            @php $res = session('manual_asset_result'); @endphp
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-in fade-in zoom-in duration-500">
                
                <!-- 1. Identity & Reputation (Bento) -->
                <div class="lg:col-span-1 bg-slate-900 rounded-[2rem] p-8 border border-white/5 flex flex-col justify-between">
                    <div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Asset Identity</div>
                        <h3 class="text-2xl font-black text-white truncate">{{ $res['domain'] }}</h3>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="px-3 py-1 rounded-lg bg-blue-500/10 text-blue-400 text-[10px] font-black uppercase">{{ $res['fingerprint']['server'] ?? 'Unknown Server' }}</span>
                            <span class="px-3 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 text-[10px] font-black uppercase">{{ $res['fingerprint']['cms'] ?? 'Custom' }}</span>
                        </div>
                    </div>
                    <div class="mt-10 p-4 rounded-2xl bg-white/5 border border-white/5">
                        <div class="text-[9px] font-black text-slate-500 uppercase mb-2">Global Reputation</div>
                        <div class="text-xs font-bold text-emerald-500 flex items-center gap-2">
                            <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></div>
                            CLEAN / NO FLAGS
                        </div>
                    </div>
                </div>

                <!-- 2. SSL/TLS Forensics (Bento) -->
                <div class="lg:col-span-1 bg-slate-900 rounded-[2rem] p-8 border border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">SSL/TLS Forensics</div>
                    @if(isset($res['ssl_audit']['error']))
                        <div class="text-xs text-slate-600 italic">No SSL information available.</div>
                    @else
                        <div class="space-y-4">
                            <div>
                                <div class="text-[9px] font-bold text-slate-600 uppercase">Issuer</div>
                                <div class="text-xs text-white font-black truncate">{{ $res['ssl_audit']['issuer'] }}</div>
                            </div>
                            <div>
                                <div class="text-[9px] font-bold text-slate-600 uppercase">Algorithm</div>
                                <div class="text-xs text-blue-400 font-black">{{ $res['ssl_audit']['algorithm'] }}</div>
                            </div>
                            <div>
                                <div class="text-[9px] font-bold text-slate-600 uppercase">Expires</div>
                                <div class="text-xs text-white font-black">{{ $res['ssl_audit']['valid_to'] }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- 3. Network Infrastructure (Bento) -->
                <div class="lg:col-span-2 bg-slate-900 rounded-[2rem] p-8 border border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Network Infrastructure</div>
                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-3">
                            @foreach($res['dns'] as $record)
                                <div class="flex items-center justify-between p-3 bg-white/5 rounded-xl">
                                    <span class="text-[10px] font-black text-blue-500 uppercase">{{ $record['type'] }}</span>
                                    <span class="text-[10px] text-white font-mono truncate px-2">{{ $record['ip'] ?? $record['target'] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="space-y-3">
                            <div class="text-[9px] font-black text-slate-600 uppercase mb-2">Exposed Services</div>
                            @forelse($res['ports'] as $port)
                                <div class="flex items-center justify-between text-[10px] font-bold">
                                    <span class="text-slate-400">{{ $port['service'] }} ({{ $port['port'] }})</span>
                                    <span class="text-emerald-500">OPEN</span>
                                </div>
                            @empty
                                <div class="text-[10px] text-slate-600 italic">Protected</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- 4. Cookie & Security Hygiene (Bento) -->
                <div class="lg:col-span-2 bg-slate-900 rounded-[2rem] p-8 border border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Cookie & Hygiene Audit</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            @foreach($res['cookies'] as $cookie)
                                <div class="flex items-center justify-between p-3 border-b border-white/5 last:border-0">
                                    <span class="text-xs text-white font-bold truncate max-w-[150px]">{{ $cookie['name'] }}</span>
                                    <div class="flex gap-1">
                                        <span class="h-2 w-2 rounded-full {{ $cookie['httponly'] ? 'bg-emerald-500' : 'bg-red-500' }}" title="HttpOnly"></span>
                                        <span class="h-2 w-2 rounded-full {{ $cookie['secure'] ? 'bg-emerald-500' : 'bg-red-500' }}" title="Secure"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="space-y-4">
                            @foreach(($res['fingerprint']['security'] ?? []) as $key => $passed)
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase">{{ str_replace('_', ' ', $key) }}</span>
                                    <span class="text-[9px] font-black {{ $passed ? 'text-emerald-500' : 'text-red-500' }}">{{ $passed ? 'PASSED' : 'MISSING' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- 5. Subdomain Footprint (Bento) -->
                <div class="lg:col-span-2 bg-slate-900 rounded-[2rem] p-8 border border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Subdomain Inventory ({{ count($res['subdomains']) }})</div>
                    <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto custom-scrollbar">
                        @foreach($res['subdomains'] as $sub)
                            <div class="text-[10px] font-mono text-slate-400 p-2 bg-white/5 rounded-lg truncate hover:text-blue-400 transition-colors cursor-pointer">
                                {{ $sub }}
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 6. Infrastructure Map -->
                <div class="lg:col-span-4 bg-slate-900 rounded-[2rem] p-8 border border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-8 text-center">Infrastructure Relationship Topology</div>
                    <div class="flex justify-center overflow-hidden">
                        <pre class="mermaid">
graph LR
    Main["{{ $res['domain'] }}"]
    Main --> DNS["DNS Infrastructure"]
    @foreach($res['dns'] as $record)
        @if($record['type'] === 'A')
            DNS --> IP_{{ str_replace('.', '_', $record['ip']) }}["IP: {{ $record['ip'] }} ({{ $record['geo']['country'] ?? 'Unknown' }})"]
        @endif
    @endforeach
    @foreach(array_slice($res['subdomains'], 0, 5) as $sub)
        Main --> Sub_{{ md5($sub) }}["{{ $sub }}"]
    @endforeach
                        </pre>
                    </div>
                </div>
            </div>
        @endif

        <!-- Global Records Table -->
        <div class="bg-slate-900 rounded-[2.5rem] border border-white/5 overflow-hidden shadow-2xl mt-12">
            <div class="p-8 border-b border-white/5">
                <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest">Digital Footprint History</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-500 uppercase tracking-widest bg-white/5 border-b border-white/5">
                            <th class="px-8 py-5">Type</th>
                            <th class="px-8 py-5">Host</th>
                            <th class="px-8 py-5">Value</th>
                            <th class="px-8 py-5 text-right">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($dnsRecords as $record)
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-8 py-5">
                                <span class="px-3 py-1 bg-blue-500/10 text-blue-500 rounded-lg text-[9px] font-black">{{ $record->type }}</span>
                            </td>
                            <td class="px-8 py-5 text-xs font-bold text-white group-hover:text-blue-400 transition-colors">{{ $record->host }}</td>
                            <td class="px-8 py-5 text-[10px] text-slate-500 font-mono truncate max-w-md">{{ $record->value }}</td>
                            <td class="px-8 py-5 text-right text-[10px] text-slate-600 font-bold uppercase tracking-tighter">{{ \Carbon\Carbon::parse($record->last_seen_at)->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 10px; }
        .mermaid { background: transparent !important; }
    </style>
</x-app-layout>
