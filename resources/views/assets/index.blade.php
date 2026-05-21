<x-app-layout>
    <x-slot name="header_title">Cyber SOC Dashboard</x-slot>

    <!-- Leaflet Map CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="max-w-[1700px] mx-auto space-y-6 pb-20 px-6 pt-6 font-sans">
        
        <!-- Command Header -->
        <div class="bg-[#0b0e14] p-8 rounded-[2rem] border border-white/5 flex flex-col lg:flex-row lg:items-center justify-between gap-6 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-20">
                <div class="flex gap-2">
                    <span class="h-1 w-8 bg-blue-600 rounded-full animate-pulse"></span>
                    <span class="h-1 w-4 bg-slate-800 rounded-full"></span>
                </div>
            </div>
            <div class="flex items-center gap-6">
                <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center shadow-2xl shadow-blue-600/30">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div>
                    <h1 class="text-3xl font-black text-white tracking-tighter">Security Operations Center</h1>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mt-1">Infrastructure Intelligence Matrix</p>
                </div>
            </div>
            <form action="{{ route('assets.scan') }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="text" name="url" placeholder="Domain or IP Address..." required 
                    class="w-full lg:w-96 rounded-2xl border-none bg-white/5 px-6 py-4 text-sm text-white focus:ring-2 focus:ring-blue-500 placeholder:text-slate-700 font-bold transition-all">
                <button type="submit" class="px-8 py-4 bg-white text-black hover:bg-blue-600 hover:text-white text-xs font-black rounded-2xl transition-all shadow-xl active:scale-95">
                    INITIATE SCAN
                </button>
            </form>
        </div>

        @if(session('manual_asset_result'))
            @php
                $res = session('manual_asset_result');
                $edgeGeo = $res['edge_geo'] ?? collect($res['dns'])->first(fn ($record) => isset($record['geo']))['geo'] ?? null;
                $edgePoints = collect($res['dns'])
                    ->filter(fn ($record) => isset($record['geo']['lat'], $record['geo']['lon']))
                    ->map(fn ($record) => [
                        'ip' => $record['ip'] ?? $record['target'] ?? 'Unknown IP',
                        'country' => $record['geo']['country'] ?? 'Unknown',
                        'city' => $record['geo']['city'] ?? 'Unknown',
                        'provider' => $record['geo']['isp'] ?? $record['geo']['org'] ?? 'Unknown provider',
                        'lat' => (float) $record['geo']['lat'],
                        'lon' => (float) $record['geo']['lon'],
                    ])
                    ->values();
            @endphp
            <div class="grid grid-cols-12 gap-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                
                <!-- 1. AI Security Grade (Bento) -->
                <div class="col-span-12 lg:col-span-3">
                    <div class="bg-slate-900 rounded-[2rem] p-8 border border-white/5 h-full relative overflow-hidden group">
                        <div class="absolute -bottom-10 -right-10 h-40 w-40 bg-blue-600/10 rounded-full blur-3xl group-hover:bg-blue-600/20 transition-all"></div>
                        <div class="relative z-10">
                            <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-8">AI Risk Grade</div>
                            <div class="flex flex-col items-center justify-center py-4">
                                <div class="text-7xl font-black {{ $res['security_audit']['grade'] == 'A+' ? 'text-emerald-500' : ($res['security_audit']['grade'] == 'F' ? 'text-red-500' : 'text-blue-500') }} tracking-tighter drop-shadow-2xl">
                                    {{ $res['security_audit']['grade'] }}
                                </div>
                                <div class="text-xs font-black text-white mt-4 uppercase tracking-widest">{{ $res['security_audit']['score'] }} / 100</div>
                            </div>
                            <div class="mt-8 space-y-2">
                                @foreach(array_slice($res['security_audit']['findings'], 0, 3) as $finding)
                                    <div class="text-[9px] text-slate-400 flex items-start gap-2">
                                        <span class="h-1 w-1 mt-1 rounded-full bg-blue-500 shrink-0"></span>
                                        {{ $finding }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Global Threat Map (Bento) -->
                <div class="col-span-12 lg:col-span-6">
                    <div class="bg-slate-900 rounded-[2rem] border border-white/5 h-[450px] overflow-hidden relative shadow-2xl">
                        <div id="map" class="h-full w-full"></div>
                        <div class="absolute bottom-4 left-4 z-[1000] max-w-sm rounded-2xl border border-slate-200 bg-white/95 p-4 text-slate-900 shadow-2xl backdrop-blur dark:border-white/10 dark:bg-slate-950/90 dark:text-white">
                            <div class="mb-2 text-[9px] font-black uppercase tracking-widest text-slate-500">
                                {{ ($res['cdn_detected'] ?? false) ? 'Public Edge Location' : 'Geo-Intelligence' }}
                            </div>
                            <div class="text-[11px] font-black">
                                @if($edgeGeo)
                                    <span class="text-blue-600 dark:text-blue-400">{{ $edgeGeo['country'] ?? 'Unknown country' }}</span>
                                    {{ $edgeGeo['city'] ?? 'Unknown city' }}
                                @else
                                    Location unavailable
                                @endif
                            </div>
                            @if($res['cdn_detected'] ?? false)
                                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[10px] font-bold leading-relaxed text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                                    {{ $res['cdn_provider'] }} CDN/WAF detected. This map shows public edge IP geolocation; origin location is hidden.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 3. Network Identity (Bento) -->
                <div class="col-span-12 lg:col-span-3">
                    <div class="bg-slate-900 rounded-[2rem] p-8 border border-white/5 h-full">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Infras Profile</div>
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-2xl font-black text-white truncate tracking-tighter">{{ $res['domain'] }}</h3>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="px-3 py-1 rounded-lg bg-white/5 text-slate-400 text-[10px] font-black uppercase">{{ $res['fingerprint']['server'] ?? 'Unknown' }}</span>
                                    <span class="px-3 py-1 rounded-lg bg-blue-500/10 text-blue-400 text-[10px] font-black uppercase">{{ $res['fingerprint']['cms'] ?? 'Custom' }}</span>
                                    @if($res['cdn_detected'] ?? false)
                                        <span class="px-3 py-1 rounded-lg bg-amber-500/10 text-amber-300 text-[10px] font-black uppercase">Origin hidden</span>
                                    @endif
                                </div>
                            </div>
                            <div class="space-y-4">
                                @if($res['cdn_detected'] ?? false)
                                    <div class="p-4 rounded-2xl bg-amber-500/5 border border-amber-500/15 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[9px] font-bold text-slate-500 uppercase">CDN / WAF</span>
                                            <span class="text-[10px] text-amber-300 font-black">{{ $res['cdn_provider'] }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-[9px] font-bold text-slate-500 uppercase">Origin Geo</span>
                                            <span class="text-[10px] text-amber-300 font-black">Hidden</span>
                                        </div>
                                        <div class="text-[9px] font-semibold leading-relaxed text-slate-500">Resolved GeoIP belongs to the CDN edge, not necessarily the hosting origin.</div>
                                    </div>
                                @endif
                                @foreach($res['dns'] as $record)
                                    @if(isset($record['geo']))
                                        <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5 space-y-2">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[9px] font-bold text-slate-600 uppercase">{{ ($res['cdn_detected'] ?? false) ? 'Edge Provider' : 'Provider' }}</span>
                                                <span class="text-[10px] text-white font-black truncate max-w-[120px]">{{ $record['geo']['isp'] ?? 'Unknown' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-[9px] font-bold text-slate-600 uppercase">{{ ($res['cdn_detected'] ?? false) ? 'Edge Geo' : 'Geo' }}</span>
                                                <span class="text-[10px] text-white font-black truncate max-w-[120px]">{{ $record['geo']['city'] ?? 'Unknown' }}, {{ $record['geo']['country'] ?? 'Unknown' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-[9px] font-bold text-slate-600 uppercase">AS Number</span>
                                                <span class="text-[10px] text-blue-500 font-black">{{ explode(' ', $record['geo']['as'] ?? 'N/A')[0] }}</span>
                                            </div>
                                        </div>
                                        @break
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Security Hygiene & Vulnerabilities (Full Row) -->
                <div class="col-span-12 lg:col-span-4 bg-slate-900 rounded-[2rem] p-8 border border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Hygiene Audit</div>
                    <div class="space-y-3">
                        @foreach(($res['fingerprint']['security'] ?? []) as $key => $passed)
                            <div class="flex items-center justify-between p-4 rounded-2xl bg-white/5 border border-white/5">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $key }}</span>
                                @if($passed)
                                    <span class="text-[9px] font-black text-emerald-500 uppercase">PASS</span>
                                @else
                                    <span class="text-[9px] font-black text-red-500 uppercase">FAIL</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-8 bg-slate-900 rounded-[2rem] p-8 border border-red-500/10">
                    <div class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-6 flex items-center justify-between">
                        <span>Public Exposure Check</span>
                        <span class="px-2 py-0.5 bg-red-600 text-white text-[8px] rounded font-black">SENSITIVE PATHS</span>
                    </div>
                    <p class="mb-5 text-[11px] font-semibold leading-relaxed text-slate-500">
                        Checks a small set of public paths for exposed environment files, Git config, phpinfo output, and reachable admin surfaces.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse(($res['vulnerabilities'] ?? []) as $vuln)
                            <div class="p-4 rounded-2xl bg-red-500/5 border border-red-500/10 flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-[10px] text-white font-mono">{{ $vuln['path'] }}</div>
                                    <div class="text-[9px] text-slate-500 mt-1 uppercase font-black tracking-widest">{{ $vuln['description'] }}</div>
                                </div>
                                <span class="text-[9px] font-black {{ ($vuln['severity'] ?? 'CRITICAL') === 'CRITICAL' ? 'text-red-500 bg-red-500/10' : (($vuln['severity'] ?? '') === 'WARNING' ? 'text-amber-400 bg-amber-500/10' : 'text-blue-400 bg-blue-500/10') }} px-2 py-1 rounded">
                                    {{ $vuln['severity'] ?? $vuln['status'] }}
                                </span>
                            </div>
                        @empty
                            <div class="col-span-2 text-center py-10 text-[11px] text-slate-600 italic font-medium">No sensitive public exposures detected in the checked paths.</div>
                        @endforelse
                    </div>
                </div>

                <!-- 5. Relationship Topology -->
                <div class="col-span-12 bg-slate-900 rounded-[3rem] p-12 border border-white/5 overflow-hidden">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-[0.4em] mb-12 text-center">Infrastructure Topology Map</div>
                    <div class="flex justify-center">
                        <pre class="mermaid text-white">
graph LR
    Main["{{ $res['domain'] }}"]
    Main --> DNS["DNS INFRA"]
    @foreach($res['dns'] as $record)
        @if($record['type'] === 'A')
            DNS --> IP_{{ str_replace('.', '_', $record['ip']) }}["{{ $record['ip'] }} ({{ $record['geo']['country'] ?? 'Global' }})"]
        @endif
    @endforeach
    @foreach(array_slice($res['subdomains'], 0, 5) as $sub)
        Main --> Sub_{{ md5($sub) }}["{{ $sub }}"]
    @endforeach
                        </pre>
                    </div>
                </div>

                <!-- 6. Forensic Activity (Proof) -->
                <div class="col-span-12 bg-slate-900 rounded-[2rem] p-8 border border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Forensic Activity Log</div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                        @foreach(($res['activity_log'] ?? []) as $log)
                            <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 flex flex-col gap-1">
                                <div class="text-[9px] text-white font-mono truncate">{{ $log['path'] }}</div>
                                <div class="flex items-center justify-between">
                                    <span class="text-[8px] text-slate-600 font-mono">{{ $log['status'] }}</span>
                                    <span class="text-[8px] font-black 
                                        @if($log['severity'] === 'critical') text-red-500
                                        @elseif($log['severity'] === 'success') text-emerald-500
                                        @else text-slate-500 @endif uppercase">
                                        {{ str_replace('Filtered (Matches Error Page)', 'FILTERED', $log['result']) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Leaflet Map JS -->
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const edgePoints = @json($edgePoints);
                    var map = L.map('map', {
                        zoomControl: true,
                        attributionControl: false
                    }).setView([20, 0], 2);

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19
                    }).addTo(map);

                    const bounds = [];
                    edgePoints.forEach((point) => {
                        const marker = L.circleMarker([point.lat, point.lon], {
                            radius: 10,
                            weight: 3,
                            opacity: 1,
                            fillOpacity: 0.88,
                            color: '#ffffff',
                            fillColor: '#2563eb'
                        }).addTo(map);

                        marker.bindPopup(`<div class="text-xs font-bold text-slate-900">IP: ${point.ip}<br>${point.city}, ${point.country}<br>${point.provider}</div>`);
                        bounds.push([point.lat, point.lon]);
                    });

                    if (bounds.length === 1) {
                        map.setView(bounds[0], 5);
                    } else if (bounds.length > 1) {
                        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 5 });
                    }
                });
            </script>
        @endif

        <!-- Global Records Ledger -->
        <div class="bg-slate-950 rounded-[3rem] border border-white/5 overflow-hidden shadow-2xl mt-12">
            <div class="p-8 border-b border-white/5 flex items-center justify-between bg-white/[0.02]">
                <h2 class="text-xs font-black text-slate-500 uppercase tracking-widest">Global Asset Ledger</h2>
                <span class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-500 uppercase tracking-widest bg-white/5 border-b border-white/5">
                            <th class="px-8 py-5">Type</th>
                            <th class="px-8 py-5">Infrastructure Node</th>
                            <th class="px-8 py-5">Resolution</th>
                            <th class="px-8 py-5 text-right">Observation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($dnsRecords as $record)
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-8 py-5">
                                <span class="px-3 py-1 bg-blue-500/10 text-blue-500 rounded-lg text-[9px] font-black uppercase">{{ $record->type }}</span>
                            </td>
                            <td class="px-8 py-5 text-xs font-black text-white group-hover:text-blue-400 transition-colors">{{ $record->host }}</td>
                            <td class="px-8 py-5 text-[10px] text-slate-500 font-mono truncate max-w-sm">{{ $record->value }}</td>
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
        .leaflet-container { background: #dbeafe !important; }
        .leaflet-control-zoom a { color: #0f172a !important; }
    </style>
</x-app-layout>
