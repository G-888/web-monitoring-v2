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
                <h3 class="text-xl font-bold text-white mb-6">Discovery Results: {{ session('manual_asset_result')['domain'] }}</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">DNS Records Found</div>
                        <div class="space-y-2">
                            @foreach(session('manual_asset_result')['dns'] as $record)
                                <div class="p-3 bg-white/5 rounded-xl border border-white/5 flex items-center justify-between">
                                    <span class="text-[10px] font-black text-blue-400 uppercase w-12">{{ $record['type'] }}</span>
                                    <span class="text-xs text-white truncate px-4 flex-1">{{ $record['host'] ?? '' }}</span>
                                    <span class="text-[10px] text-slate-500 font-mono">{{ $record['ip'] ?? $record['target'] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Discovered Subdomains</div>
                        <div class="grid grid-cols-1 gap-2">
                            @foreach(session('manual_asset_result')['subdomains'] as $sub)
                                <div class="p-3 bg-white/5 rounded-xl border border-white/5 text-xs text-slate-300 font-mono">
                                    {{ $sub }}
                                </div>
                            @endforeach
                        </div>
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
