<x-app-layout>
    <x-slot name="header_title">Advanced Asset Intelligence</x-slot>

    <div class="max-w-[1650px] mx-auto space-y-6 pb-20 px-6 pt-6 font-sans">
        
        <!-- Command Header (Sleek) -->
        <div class="bg-[#0b0e14] p-8 rounded-[2rem] border border-white/5 flex flex-col lg:flex-row lg:items-center justify-between gap-6 shadow-2xl">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-600/20">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight">Asset Recon</h1>
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mt-1">Infrastructure, DNS & Content Intelligence</p>
                </div>
            </div>
            <form action="{{ route('assets.scan') }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="text" name="url" placeholder="Domain or IP Address..." required 
                    class="w-full lg:w-96 rounded-xl border-none bg-white/5 px-4 py-3 text-sm text-white focus:ring-1 focus:ring-blue-500 placeholder:text-slate-700">
                <button type="submit" class="px-6 py-3 bg-white text-black hover:bg-blue-600 hover:text-white text-xs font-black rounded-xl transition-all active:scale-95">
                    SCAN
                </button>
            </form>
        </div>

        @if(session('manual_asset_result'))
            @php $res = session('manual_asset_result'); @endphp
            <div class="grid grid-cols-12 gap-6 animate-in fade-in zoom-in duration-300">
                
                <!-- 1. Identity & Network (Column 1-4) -->
                <div class="col-span-12 lg:col-span-4 space-y-6">
                    <div class="bg-[#111418] rounded-[2rem] p-6 border border-white/5 h-full">
                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-6">Network Identity</div>
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-3xl font-black text-white truncate tracking-tighter">{{ $res['domain'] }}</h3>
                                <div class="mt-4 flex flex-wrap gap-1.5">
                                    <span class="px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-400 text-[9px] font-black uppercase">{{ $res['fingerprint']['server'] ?? 'Server N/A' }}</span>
                                    <span class="px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-400 text-[9px] font-black uppercase">{{ $res['fingerprint']['cms'] ?? 'Custom Tech' }}</span>
                                </div>
                            </div>
                            
                            <!-- ASN / Geo Details -->
                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5">
                                @foreach($res['dns'] as $record)
                                    @if(isset($record['geo']))
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[9px] font-bold text-slate-500 uppercase">Provider</span>
                                                <span class="text-[10px] text-white font-black truncate max-w-[150px]">{{ $record['geo']['isp'] ?? 'Unknown' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-[9px] font-bold text-slate-500 uppercase">ASN</span>
                                                <span class="text-[10px] text-blue-400 font-black">{{ $record['geo']['as'] ?? 'N/A' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-[9px] font-bold text-slate-500 uppercase">Locality</span>
                                                <span class="text-[10px] text-white font-black">{{ $record['geo']['city'] ?? '' }}, {{ $record['geo']['country'] ?? '' }}</span>
                                            </div>
                                        </div>
                                        @break
                                    @endif
                                @endforeach
                            </div>

                            <!-- Reputation -->
                            <div class="p-4 rounded-2xl bg-emerald-500/5 border border-emerald-500/10 flex items-center justify-between">
                                <span class="text-[9px] font-black text-emerald-500 uppercase tracking-widest">Global Reputation</span>
                                <span class="text-[9px] font-black text-white px-2 py-0.5 bg-emerald-500 rounded shadow-lg shadow-emerald-500/20">CLEAN</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Security Forensics (Column 5-8) -->
                <div class="col-span-12 lg:col-span-4 space-y-6">
                    <div class="bg-[#111418] rounded-[2rem] p-6 border border-white/5">
                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-6">SSL/TLS & Security Hygiene</div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-4">
                                <div>
                                    <div class="text-[8px] font-black text-slate-600 uppercase">Issuer</div>
                                    <div class="text-[11px] text-white font-bold truncate">{{ $res['ssl_audit']['issuer'] ?? 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-[8px] font-black text-slate-600 uppercase">Expires</div>
                                    <div class="text-[11px] text-orange-500 font-bold">{{ $res['ssl_audit']['valid_to'] ?? 'N/A' }}</div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                @foreach(($res['fingerprint']['security'] ?? []) as $key => $passed)
                                    <div class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-white/5">
                                        <span class="text-[8px] font-bold text-slate-500 uppercase">{{ $key }}</span>
                                        <span class="text-[8px] font-black {{ $passed ? 'text-emerald-500' : 'text-red-500' }}">{{ $passed ? 'OK' : 'MISS' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#111418] rounded-[2rem] p-6 border border-white/5">
                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-4">Exposed Network Services</div>
                        <div class="grid grid-cols-2 gap-2">
                            @forelse($res['ports'] as $port)
                                <div class="p-3 rounded-xl bg-white/5 flex items-center justify-between">
                                    <span class="text-[9px] font-black text-white">{{ $port['service'] }}</span>
                                    <span class="text-[9px] font-black text-emerald-500">{{ $port['port'] }}</span>
                                </div>
                            @empty
                                <div class="col-span-2 text-center py-4 text-[10px] text-slate-600 italic">No critical public ports discovered.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- 3. SEO & Content Intel (Column 9-12) -->
                <div class="col-span-12 lg:col-span-4 space-y-6">
                    <div class="bg-[#111418] rounded-[2rem] p-6 border border-white/5">
                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-6">SEO & Content Intelligence</div>
                        @if($res['seo_intel'])
                            <div class="space-y-4">
                                <div>
                                    <div class="text-[8px] font-black text-slate-600 uppercase mb-1">Page Title</div>
                                    <div class="text-[11px] text-white font-medium italic line-clamp-2">"{{ $res['seo_intel']['title'] }}"</div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="p-3 rounded-xl {{ $res['seo_intel']['robots'] ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' }}">
                                        <div class="text-[8px] font-black uppercase mb-1">Robots.txt</div>
                                        <div class="text-[10px] font-black">{{ $res['seo_intel']['robots'] ? 'FOUND' : 'MISSING' }}</div>
                                    </div>
                                    <div class="p-3 rounded-xl {{ $res['seo_intel']['sitemap'] ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' }}">
                                        <div class="text-[8px] font-black uppercase mb-1">Sitemap</div>
                                        <div class="text-[10px] font-black">{{ $res['seo_intel']['sitemap'] ? 'FOUND' : 'MISSING' }}</div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-10 text-[10px] text-slate-600 italic">SEO intelligence unavailable.</div>
                        @endif
                    </div>

                    <div class="bg-[#111418] rounded-[2rem] p-6 border border-white/5 h-[calc(100%-240px)]">
                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-4">Discovery Inventory ({{ count($res['subdomains']) }})</div>
                        <div class="grid gap-1.5 max-h-[180px] overflow-y-auto custom-scrollbar">
                            @foreach($res['subdomains'] as $sub)
                                <div class="text-[9px] font-mono text-slate-500 p-2 bg-white/5 rounded-lg truncate hover:text-blue-400 transition-colors">
                                    {{ $sub }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- 4. Relationship Map (Full Width) -->
                <div class="col-span-12 bg-[#111418] rounded-[2rem] p-10 border border-white/5">
                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-8 text-center">Infrastructure Relationship Topology</div>
                    <div class="flex justify-center overflow-hidden">
                        <pre class="mermaid">
graph LR
    Main["{{ $res['domain'] }}"]
    Main --> DNS["DNS Cluster"]
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

        <!-- Footer Log -->
        <div class="bg-[#111418] rounded-[2rem] border border-white/5 overflow-hidden">
            <div class="px-8 py-5 border-b border-white/5 flex items-center justify-between">
                <h2 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Global Asset Ledger</h2>
                <span class="text-[9px] font-bold text-blue-500 uppercase tracking-widest">Real-time Feed</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[9px] font-black text-slate-600 uppercase tracking-widest bg-white/5 border-b border-white/5">
                            <th class="px-8 py-4">Type</th>
                            <th class="px-8 py-4">Node</th>
                            <th class="px-8 py-4">Value</th>
                            <th class="px-8 py-4 text-right">Observation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($dnsRecords as $record)
                        <tr class="hover:bg-white/[0.01] transition-colors group">
                            <td class="px-8 py-4">
                                <span class="px-2 py-0.5 bg-blue-500/10 text-blue-500 rounded text-[8px] font-black uppercase">{{ $record->type }}</span>
                            </td>
                            <td class="px-8 py-4 text-[11px] font-black text-white group-hover:text-blue-400 transition-colors">{{ $record->host }}</td>
                            <td class="px-8 py-4 text-[10px] text-slate-500 font-mono truncate max-w-sm">{{ $record->value }}</td>
                            <td class="px-8 py-4 text-right text-[9px] text-slate-600 font-bold uppercase tracking-tighter">{{ \Carbon\Carbon::parse($record->last_seen_at)->diffForHumans() }}</td>
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
