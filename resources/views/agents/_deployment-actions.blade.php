@php
    $modalId = 'agent-config-preview-'.$server->id.'-'.$context;
    $featureFlags = config('agent.feature_flags', []);
    $defaultServices = app(\App\Services\AgentDeploymentService::class)->defaultWindowsServices($server);
    $previewConfig = app(\App\Services\AgentDeploymentService::class)->preview($server);
@endphp

<div class="flex flex-wrap gap-2">
    <button type="button" onclick="document.getElementById('{{ $modalId }}').showModal()" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-500">
        Download Config
    </button>
    <a href="{{ route('servers.agent-package', $server) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
        Download Package
    </a>
    <button type="button" data-copy-text="powershell -NoProfile -ExecutionPolicy Bypass -File .\install-service.ps1" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
        Copy Install
    </button>
    <button type="button" data-copy-text="powershell -NoProfile -ExecutionPolicy Bypass -File .\update-agent.ps1" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
        Copy Update
    </button>
    <form method="POST" action="{{ route('servers.agent-key.rotate', $server) }}" onsubmit="return confirm('Rotate this server agent key? Existing per-server configs will stop authenticating until replaced.');">
        @csrf
        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            Rotate Key
        </button>
    </form>
</div>

<dialog id="{{ $modalId }}" class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/70 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100">
    <form method="dialog" class="border-b border-slate-200 p-5 dark:border-white/10">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold">Config Preview</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $server->name }} / {{ $server->server_id }}</p>
            </div>
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Close</button>
        </div>
    </form>

    <form method="GET" action="{{ route('servers.agent-config', $server) }}" class="space-y-5 p-5">
        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Feature Flags</h4>
                <input type="hidden" name="featureFlags[__present]" value="1" />
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach($featureFlags as $flag => $enabled)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-white/10 dark:bg-slate-950/60">
                            <input type="checkbox" name="featureFlags[{{ $flag }}]" value="1" class="rounded border-white/10 bg-white/5 text-orange-500 focus:ring-orange-500" @checked($enabled) />
                            <span>{{ $flag }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="windows-services-{{ $modalId }}" class="text-sm font-semibold text-slate-900 dark:text-white">Windows Services</label>
                    <textarea id="windows-services-{{ $modalId }}" name="windowsServices" rows="7" class="mt-3 block w-full rounded-md border-gray-300 bg-white text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">{{ implode("\n", $defaultServices) }}</textarea>
                </div>
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-white/10 dark:bg-slate-950/60">
                    <input type="hidden" name="autoUpdateEnabled" value="0" />
                    <input type="checkbox" name="autoUpdateEnabled" value="1" class="rounded border-white/10 bg-white/5 text-orange-500 focus:ring-orange-500" @checked(config('agent.auto_update.enabled', false)) />
                    <span>Enable auto update</span>
                </label>
            </div>
        </div>

        <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Preview</h4>
            <pre class="mt-3 max-h-72 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($previewConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">
                Download Config
            </button>
            <button type="submit" formaction="{{ route('servers.agent-package', $server) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                Download Package
            </button>
        </div>
    </form>
</dialog>
