<x-app-layout>
    <x-slot name="header_title">SSL Certificate Conversion</x-slot>

    <div class="max-w-3xl mx-auto" x-data="{ 
        type: '{{ old('type', 'pem_to_pfx') }}',
        get file1Label() {
            if (this.type === 'pfx_to_pem') return 'PFX Bundle (.pfx, .p12)';
            if (this.type === 'der_to_pem') return 'DER Certificate (.der, .cer)';
            return 'PEM Certificate (.pem, .crt)';
        },
        get file1Accept() {
            if (this.type === 'pfx_to_pem') return '.pfx,.p12';
            if (this.type === 'der_to_pem') return '.der,.cer,.crt';
            return '.pem,.crt,.txt';
        }
    }">
        <div class="glass rounded-3xl p-8 shadow-xl relative overflow-hidden">
            <!-- Background Decoration -->
            <div class="absolute top-0 right-0 -mr-16 -mt-16 h-64 w-64 rounded-full bg-orange-500/5 blur-3xl"></div>
            
            <div class="relative">
                <div class="flex items-center gap-4 mb-8">
                    <div class="h-12 w-12 rounded-2xl bg-orange-100 dark:bg-orange-500/10 flex items-center justify-center text-orange-600 dark:text-orange-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">SSL Multi-Converter</h1>
                        <p class="text-slate-500 text-sm">Convert between PEM, DER, and PFX formats securely.</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mb-8 p-4 rounded-2xl border border-red-200 dark:border-red-500/20 bg-red-50 dark:bg-red-500/10">
                        <div class="flex gap-3">
                            <svg class="h-5 w-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <ul class="text-sm text-red-600 dark:text-red-400 font-medium space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form action="{{ route('ssl-conversion.convert') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                    @csrf

                    <div class="space-y-2">
                        <label for="type" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">
                            Conversion Type
                        </label>
                        <select name="type" id="type" x-model="type"
                            class="w-full rounded-2xl border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-6 py-4 focus:ring-2 focus:ring-orange-500 transition-all shadow-sm">
                            <option value="pem_to_pfx">PEM/CRT + Key to PFX (PKCS#12)</option>
                            <option value="pfx_to_pem">PFX to PEM (Extract Cert & Key)</option>
                            <option value="pem_to_der">PEM to DER (Binary)</option>
                            <option value="der_to_pem">DER to PEM (Text)</option>
                        </select>
                    </div>

                    <div class="grid md:grid-cols-1 gap-6">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1" x-text="file1Label"></label>
                            <div class="relative group">
                                <input type="file" name="file1" id="file1" :accept="file1Accept" required
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                <div class="w-full rounded-2xl border-2 border-dashed border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-6 py-8 text-center group-hover:border-orange-500/50 transition-all">
                                    <svg class="h-8 w-8 mx-auto text-slate-400 mb-2 group-hover:text-orange-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <span class="text-sm font-medium text-slate-600 dark:text-slate-400" id="file1-label">Click or drag file here</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2" x-show="type === 'pem_to_pfx'" x-transition x-cloak>
                            <label for="file2" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">
                                Private Key (.key)
                            </label>
                            <div class="relative group">
                                <input type="file" name="file2" id="file2" accept=".pem,.key,.txt" :required="type === 'pem_to_pfx'"
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                <div class="w-full rounded-2xl border-2 border-dashed border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-6 py-8 text-center group-hover:border-orange-500/50 transition-all">
                                    <svg class="h-8 w-8 mx-auto text-slate-400 mb-2 group-hover:text-orange-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                    </svg>
                                    <span class="text-sm font-medium text-slate-600 dark:text-slate-400" id="file2-label">Select Private Key</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2" x-show="type === 'pem_to_pfx' || type === 'pfx_to_pem'" x-transition x-cloak>
                        <label for="password" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1" x-text="type === 'pem_to_pfx' ? 'New PFX Password' : 'PFX Import Password'"></label>
                        <input type="password" name="password" id="password" :required="type === 'pem_to_pfx' || type === 'pfx_to_pem'"
                            class="w-full rounded-2xl border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-6 py-4 focus:ring-2 focus:ring-orange-500 transition-all shadow-sm"
                            placeholder="Enter password">
                    </div>

                    <div class="p-6 rounded-2xl bg-orange-50 dark:bg-orange-500/5 border border-orange-200 dark:border-orange-500/10">
                        <div class="flex gap-4">
                            <div class="h-10 w-10 shrink-0 rounded-full bg-orange-100 dark:bg-orange-500/20 flex items-center justify-center text-orange-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-orange-900 dark:text-orange-400">Secure Processing</h4>
                                <p class="text-xs text-orange-800/70 dark:text-orange-300/60 leading-relaxed mt-1">
                                    Conversions are performed entirely in-memory. Your private keys and certificates are never written to disk or logged.
                                </p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full btn-primary h-14 text-lg shadow-lg shadow-orange-500/20 flex items-center justify-center gap-3 group">
                        <span>Process Conversion</span>
                        <svg class="h-5 w-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('file1').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Click or drag file here';
            document.getElementById('file1-label').textContent = fileName;
        });
        document.getElementById('file2').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Select Private Key';
            document.getElementById('file2-label').textContent = fileName;
        });
    </script>
</x-app-layout>