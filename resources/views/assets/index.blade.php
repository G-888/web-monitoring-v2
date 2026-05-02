<x-app-layout>
    <x-slot name="header_title">Asset Intelligence</x-slot>

    <div class="max-w-7xl mx-auto space-y-10 pb-20 px-4 pt-4">
        
        <!-- Header & Quick Discovery -->
        <div class="bg-slate-900 p-8 rounded-[2rem] border border-white/5 shadow-2xl flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-1">
                <h1 class="text-3xl font-black tracking-tight text-white flex items-center gap-3">
                    <span class="text-blue-500">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    Asset Intel
                </h1>
                <p class="text-slate-400 font-medium">Domain footprint & DNS monitoring.</p>
            </div>
            
            <form action="{{ route('assets.scan') }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="text" name="url" placeholder="Domain or IP..." required 
                    class="w-full lg:w-72 rounded-xl border-none bg-white/5 px-4 py-3 text-sm text-white focus:ring-2 focus:ring-blue-500 transition-all placeholder:text-slate-600">
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white text-sm font-black rounded-xl transition-all shadow-lg shadow-blue-600/20">
                    DISCOVER
                </button>
            </form>
        </div>

        @if(session('manual_asset_result'))
            <div class="bg-slate-900 rounded-[2rem] p-8 border border-blue-500/30 shadow-2xl animate-in fade-in slide-in-from-top-4 duration-500">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-xl font-bold text-white">Discovery Results: {{ session('manual_asset_result')['domain'] }}</h3>
                    @if(isset(session('manual_asset_result')['fingerprint']))
                        <div class="flex gap-2">
                            <span class="px-3 py-1 rounded-lg bg-blue-500 text-white text-[10px] font-black uppercase">Server: {{ session('manual_asset_result')['fingerprint']['server'] ?? 'N/A' }}</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-800 text-slate-400 text-[10px] font-black uppercase">Tech: {{ session('manual_asset_result')['fingerprint']['powered_by'] ?? 'N/A' }}</span>
                        </div>
                    @endif
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Network & DNS</div>
                        <div class="space-y-3">
                            @foreach(session('manual_asset_result')['dns'] as $record)
                                <div class="p-4 bg-white/5 rounded-2xl border border-white/5">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[10px] font-black text-blue-400 uppercase tracking-widest">{{ $record['type'] }}</span>
                                        <span class="text-[9px] text-slate-500 font-mono">{{ $record['ttl'] ?? '' }} TTL</span>
                                    </div>
                                    <div class="text-xs text-white font-bold mb-1 truncate">{{ $record['ip'] ?? $record['target'] ?? '' }}</div>
                                    @if(isset($record['geo']))
                                        <div class="text-[9px] text-slate-400 flex items-center gap-2">
                                            <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                            {{ $record['geo']['city'] ?? '' }}, {{ $record['geo']['country'] ?? '' }} ({{ $record['geo']['isp'] ?? '' }})
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Discovered Subdomains</div>
                        <div class="grid grid-cols-1 gap-2">
                            @foreach(session('manual_asset_result')['subdomains'] as $sub)
                                <div class="p-3 bg-white/5 rounded-xl border border-white/5 text-xs text-slate-300 font-mono flex items-center justify-between">
                                    {{ $sub }}
                                    <svg class="h-3 w-3 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Infras / Cyber Hygiene</div>
                        <div class="space-y-3">
                            <!-- Tech Info -->
                            @if(isset(session('manual_asset_result')['fingerprint']))
                                <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                                    <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2">Tech Stack</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span class="px-2 py-0.5 bg-blue-500/20 text-blue-400 rounded text-[9px] font-bold">{{ session('manual_asset_result')['fingerprint']['server'] }}</span>
                                        <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded text-[9px] font-bold">{{ session('manual_asset_result')['fingerprint']['cms'] }}</span>
                                    </div>
                                </div>
                            @endif

                            <!-- Email Security -->
                            <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                                <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2">Email Security</div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] text-slate-300">SPF Policy</span>
                                    <span class="text-[9px] font-black {{ session('manual_asset_result')['email_security']['spf']['status'] ? 'text-emerald-500' : 'text-red-500' }}">
                                        {{ session('manual_asset_result')['email_security']['spf']['status'] ? 'ACTIVE' : 'MISSING' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Open Ports -->
                            <div class="p-4 rounded-2xl bg-slate-950 border border-white/5 shadow-inner">
                                <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-3 flex items-center justify-between">
                                    <span>Active Probing (Ports)</span>
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                </div>
                                @forelse(session('manual_asset_result')['ports'] as $port)
                                    <div class="flex items-center justify-between text-[10px] py-1 border-b border-white/5 last:border-0">
                                        <span class="font-mono text-white">{{ $port['port'] }} / {{ $port['service'] }}</span>
                                        <span class="text-emerald-500 font-black">OPEN</span>
                                    </div>
                                @empty
                                    <div class="text-[10px] text-slate-600 italic">No common critical ports exposed.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visual Asset Map (Mermaid) -->
                <div class="mt-10 pt-10 border-t border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Infrastructure Relationship Map</div>
                    <div class="bg-black/20 rounded-[2rem] p-8 flex justify-center overflow-hidden border border-white/5">
                        <pre class="mermaid text-white">
graph LR
    Main["{{ session('manual_asset_result')['domain'] }}"]
    Main --> DNS["DNS Cluster"]
    @foreach(session('manual_asset_result')['dns'] as $record)
        @if($record['type'] === 'A')
            DNS --> IP_{{ str_replace('.', '_', $record['ip']) }}["IP: {{ $record['ip'] }} ({{ $record['geo']['country'] ?? 'Global' }})"]
        @endif
    @endforeach
    
    @foreach(array_slice(session('manual_asset_result')['subdomains'], 0, 5) as $sub)
        Main --> Sub_{{ md5($sub) }}["{{ $sub }}"]
    @endforeach
                        </pre>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- DNS Records Timeline -->
            <div class="lg:col-span-2 bg-slate-900 rounded-[2rem] border border-white/5 overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-white/5">
                    <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest">Global DNS Baseline</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-black text-slate-500 uppercase tracking-widest bg-white/5 border-b border-white/5">
                                <th class="px-6 py-4">Type</th>
                                <th class="px-6 py-4">Host</th>
                                <th class="px-6 py-4">Value</th>
                                <th class="px-6 py-4 text-right">Last Seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($dnsRecords as $record)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 bg-blue-500/10 text-blue-500 rounded text-[9px] font-black">{{ $record->type }}</span>
                                </td>
                                <td class="px-6 py-4 text-xs font-bold text-white">{{ $record->host }}</td>
                                <td class="px-6 py-4 text-[10px] text-slate-400 font-mono truncate max-w-xs">{{ $record->value }}</td>
                                <td class="px-6 py-4 text-right text-[10px] text-slate-500">{{ \Carbon\Carbon::parse($record->last_seen_at)->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Subdomain Discovery Feed -->
            <div class="lg:col-span-1 bg-slate-900 rounded-[2rem] border border-white/5 overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest">Asset Footprint</h2>
                    <span class="text-[9px] font-bold text-emerald-500 uppercase tracking-widest">Auto-Discovery</span>
                </div>
                <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto custom-scrollbar">
                    @foreach($subdomains as $sub)
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5 group hover:border-blue-500/30 transition-all">
                        <div class="text-[9px] font-black text-slate-600 uppercase tracking-widest mb-1">Subdomain</div>
                        <div class="text-xs font-mono text-white truncate">{{ $sub->subdomain }}</div>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-[9px] text-slate-500">Source: {{ $sub->source }}</span>
                            <span class="text-[9px] text-slate-500">{{ \Carbon\Carbon::parse($sub->created_at)->format('M d') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
