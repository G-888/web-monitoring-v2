<x-app-layout>
    <x-slot name="header_title">Log Monitoring & Inspection</x-slot>

    <section class="space-y-6">
        <div class="glass rounded-3xl p-8 shadow-xl">
            <h3 class="text-xl font-bold">Upload Log File</h3>
            <p class="mt-2 text-sm text-slate-500">
                Upload application/system logs for quick severity analysis (max 100MB). Supports IIS, database, ColdFusion, Windows EVTX, and more.
            </p>

            <form method="POST" action="{{ route('log-inspections.store') }}" enctype="multipart/form-data" class="mt-8 space-y-6">
                @csrf

                <div class="space-y-3">
                    <label for="log_file" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Select Log File</label>
                    <div class="relative group">
                        <input
                            id="log_file"
                            name="log_file"
                            type="file"
                            required
                            accept=".txt,.log,.csv,.json,.xml,.evtx,.out,.err,.trace,.trc,.sql,.iis,.w3c,.cfm,.coldfusion"
                            class="block w-full rounded-2xl border-2 border-dashed border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-6 py-10 text-sm text-slate-600 dark:text-slate-400 file:mr-4 file:rounded-xl file:border-0 file:bg-orange-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:file:bg-orange-500 cursor-pointer transition-all group-hover:border-orange-500/50"
                        />
                    </div>
                    <p class="text-xs text-slate-400 ml-1 italic">Security and format validation will be performed automatically.</p>
                    <x-input-error :messages="$errors->get('log_file')" class="mt-1" />
                </div>

                <button type="submit" class="btn-primary shadow-lg shadow-orange-500/20">
                    Upload and Inspect
                </button>
            </form>
        </div>

        <div class="glass rounded-3xl overflow-hidden shadow-xl">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-white/10">
                <h3 class="text-xl font-bold">Recent Inspections</h3>
                <p class="text-sm text-slate-500 mt-1">Review previously analyzed log sets.</p>
            </div>

            @if($inspections->isEmpty())
                <div class="p-12 text-center text-slate-400">
                    <svg class="h-12 w-12 mx-auto mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <p>No uploaded logs found.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                            <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                <th class="px-8 py-4">File</th>
                                <th class="px-8 py-4">Type</th>
                                <th class="px-8 py-4">Lines</th>
                                <th class="px-8 py-4">Critical</th>
                                <th class="px-8 py-4">Errors</th>
                                <th class="px-8 py-4">Warnings</th>
                                <th class="px-8 py-4 text-right">Inspected</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @foreach($inspections as $inspection)
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-8 py-4">
                                        <a href="{{ route('log-inspections.show', $inspection) }}" class="font-bold text-orange-600 dark:text-orange-400 hover:underline">
                                            {{ $inspection->original_filename }}
                                        </a>
                                    </td>
                                    <td class="px-8 py-4 font-medium">{{ $inspection->source_type ?? '-' }}</td>
                                    <td class="px-8 py-4">{{ number_format($inspection->total_lines) }}</td>
                                    <td class="px-8 py-4">
                                        <span class="{{ $inspection->critical_count > 0 ? 'text-red-600 font-bold' : '' }}">{{ $inspection->critical_count }}</span>
                                    </td>
                                    <td class="px-8 py-4">
                                        <span class="{{ $inspection->error_count > 0 ? 'text-rose-500 font-bold' : '' }}">{{ $inspection->error_count }}</span>
                                    </td>
                                    <td class="px-8 py-4">
                                        <span class="{{ $inspection->warning_count > 0 ? 'text-amber-500 font-bold' : '' }}">{{ $inspection->warning_count }}</span>
                                    </td>
                                    <td class="px-8 py-4 text-right text-slate-400">{{ $inspection->inspected_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</x-app-layout>
