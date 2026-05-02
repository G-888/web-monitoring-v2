<x-app-layout>
    <x-slot name="header_title">Server Log Scanner</x-slot>

    <div class="space-y-6" x-data="logScanner()">
        <div class="glass rounded-3xl p-8 shadow-xl">
            <div class="flex flex-col lg:flex-row gap-6 items-end">
                <div class="flex-1 space-y-2 w-full">
                    <label for="pattern" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Regex Pattern</label>
                    <input x-model="pattern" id="pattern" type="text" 
                        class="w-full rounded-2xl border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-6 py-4 font-mono text-sm focus:ring-2 focus:ring-orange-500 transition-all shadow-sm" 
                        placeholder="e.g. error|exception|timeout" 
                        @keydown.enter="scan()" />
                </div>
                <div class="flex-1 space-y-2 w-full">
                    <label for="directory" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Path (Absolute or relative to storage/logs)</label>
                    <input x-model="directory" id="directory" type="text" 
                        class="w-full rounded-2xl border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-6 py-4 text-sm focus:ring-2 focus:ring-orange-500 transition-all shadow-sm" 
                        placeholder="e.g. laravel.log or C:\Logs" />
                </div>
                <div class="w-full lg:w-auto">
                    <button @click="scan()" :disabled="loading" 
                        class="w-full btn-primary h-[58px] flex items-center justify-center gap-2 shadow-lg shadow-orange-500/20">
                        <svg x-show="!loading" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <svg x-show="loading" class="animate-spin h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span x-text="loading ? 'Scanning...' : 'Start Scan'"></span>
                    </button>
                </div>
            </div>

            <div x-show="error" x-cloak class="mt-6 p-4 rounded-2xl border border-red-200 dark:border-red-500/20 bg-red-50 dark:bg-red-500/10 text-sm text-red-600 dark:text-red-400 font-medium" x-text="error"></div>

            <div class="mt-10 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold">Results</h3>
                    <span x-show="results.length > 0" x-cloak class="px-3 py-1 rounded-full bg-orange-100 dark:bg-orange-500/10 text-orange-600 dark:text-orange-400 text-xs font-bold" x-text="results.length + ' matches found'"></span>
                </div>
                
                <div x-show="!loading && results.length === 0 && scanned" x-cloak class="p-12 text-center glass rounded-3xl border-dashed border-2">
                    <div class="text-slate-400 mb-2">
                        <svg class="h-12 w-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div class="text-lg font-bold">No matches found</div>
                    <p class="text-sm text-slate-500 mt-1">Try a different pattern or directory.</p>
                </div>

                <div class="glass rounded-3xl overflow-hidden border-none" x-show="results.length > 0" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                                <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                    <th class="px-6 py-4 w-48">File</th>
                                    <th class="px-6 py-4 w-16">Line</th>
                                    <th class="px-6 py-4">Content Snippet</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5 font-mono text-xs">
                                <template x-for="result in results" :key="result.file + result.line_number">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors group">
                                        <td class="px-6 py-4">
                                            <div class="text-orange-600 dark:text-orange-400 font-bold" x-text="result.file.split(/[\\/]/).pop()"></div>
                                            <div class="text-[10px] text-slate-500 truncate max-w-[200px]" :title="result.file" x-text="result.file"></div>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-slate-400" x-text="result.line_number"></td>
                                        <td class="px-6 py-4 whitespace-pre-wrap break-all text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white transition-colors" x-text="result.content"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function logScanner() {
            return {
                pattern: '',
                directory: '',
                results: [],
                loading: false,
                scanned: false,
                error: null,

                async scan() {
                    if (!this.pattern) return;

                    this.loading = true;
                    this.error = null;
                    this.results = [];
                    this.scanned = false;

                    try {
                        const response = await fetch('{{ route("server-logs.scan") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                pattern: this.pattern,
                                directory: this.directory
                            })
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            this.error = data.error || 'An error occurred during the scan.';
                        } else {
                            this.results = data.results;
                            this.scanned = true;
                        }
                    } catch (e) {
                        this.error = 'Network error or server unavailable.';
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</x-app-layout>
