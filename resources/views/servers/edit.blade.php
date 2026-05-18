<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Edit Server</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <div class="mt-6 rounded-lg glass p-6">
            <form method="POST" action="{{ route('servers.update', $server) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="space-y-2">
                    <x-input-label for="name" :value="__('Server Name')" />
                    <x-text-input id="name" name="name" type="text" class="w-full" value="{{ old('name', $server->name) }}" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="space-y-2">
                    <x-input-label for="server_id" :value="__('Server ID')" />
                    <x-text-input id="server_id" name="server_id" type="text" class="w-full" value="{{ old('server_id', $server->server_id) }}" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('server_id')" class="mt-1" />
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div class="space-y-2">
                        <x-input-label for="ip_address" :value="__('IP Address')" />
                        <x-text-input id="ip_address" name="ip_address" type="text" class="w-full" value="{{ old('ip_address', $server->ip_address) }}" placeholder="192.168.1.10" autocomplete="off" />
                        <x-input-error :messages="$errors->get('ip_address')" class="mt-1" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="os" :value="__('Operating System')" />
                        <x-text-input id="os" name="os" type="text" class="w-full" value="{{ old('os', $server->os) }}" placeholder="Linux / Windows" autocomplete="off" />
                        <x-input-error :messages="$errors->get('os')" class="mt-1" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="server_type" :value="__('Server Type')" />
                        <x-text-input id="server_type" name="server_type" type="text" class="w-full" value="{{ old('server_type', $server->server_type) }}" placeholder="application, database, web" autocomplete="off" />
                        <x-input-error :messages="$errors->get('server_type')" class="mt-1" />
                    </div>
                </div>

                <div class="space-y-2">
                    <x-input-label for="location" :value="__('Location')" />
                    <x-text-input id="location" name="location" type="text" class="w-full" value="{{ old('location', $server->location) }}" placeholder="Data center or region" autocomplete="off" />
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <x-input-label for="group" :value="__('Group')" />
                        <x-text-input id="group" name="group" type="text" class="w-full" value="{{ old('group', $server->group) }}" placeholder="Production / Staging / Client name" autocomplete="off" />
                        <x-input-error :messages="$errors->get('group')" class="mt-1" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="tags" :value="__('Tags')" />
                        <x-text-input id="tags" name="tags" type="text" class="w-full" value="{{ old('tags', implode(', ', $server->tags ?? [])) }}" placeholder="web, mysql, coldfusion" autocomplete="off" />
                        <x-input-error :messages="$errors->get('tags')" class="mt-1" />
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <x-input-label for="latitude" :value="__('Latitude')" />
                        <x-text-input id="latitude" name="latitude" type="text" class="w-full" value="{{ old('latitude', $server->latitude) }}" placeholder="e.g. 37.7749" autocomplete="off" />
                        <x-input-error :messages="$errors->get('latitude')" class="mt-1" />
                    </div>
                    <div class="space-y-2">
                        <x-input-label for="longitude" :value="__('Longitude')" />
                        <x-text-input id="longitude" name="longitude" type="text" class="w-full" value="{{ old('longitude', $server->longitude) }}" placeholder="e.g. -122.4194" autocomplete="off" />
                        <x-input-error :messages="$errors->get('longitude')" class="mt-1" />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            class="rounded border-white/10 bg-white/5 text-blue-500 focus:ring-blue-500"
                            {{ old('is_active', $server->is_active) ? 'checked' : '' }}
                        />
                        <span class="text-sm text-slate-200">Enable server in inventory</span>
                    </label>
                </div>

                <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-100">Threshold Alerts</h3>
                            <p class="mt-1 text-xs text-slate-400">Alerts are sent to active Super Admin alert channels.</p>
                        </div>
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                name="alerts_enabled"
                                value="1"
                                class="rounded border-white/10 bg-white/5 text-blue-500 focus:ring-blue-500"
                                {{ old('alerts_enabled', $server->alerts_enabled) ? 'checked' : '' }}
                            />
                            <span class="text-sm text-slate-200">Enable alerts</span>
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <x-input-label for="cpu_threshold" :value="__('CPU %')" />
                            <x-text-input id="cpu_threshold" name="cpu_threshold" type="number" min="0" max="100" step="0.1" class="w-full" value="{{ old('cpu_threshold', $server->cpu_threshold) }}" />
                            <x-input-error :messages="$errors->get('cpu_threshold')" class="mt-1" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="ram_threshold" :value="__('RAM %')" />
                            <x-text-input id="ram_threshold" name="ram_threshold" type="number" min="0" max="100" step="0.1" class="w-full" value="{{ old('ram_threshold', $server->ram_threshold) }}" />
                            <x-input-error :messages="$errors->get('ram_threshold')" class="mt-1" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="disk_threshold" :value="__('Disk %')" />
                            <x-text-input id="disk_threshold" name="disk_threshold" type="number" min="0" max="100" step="0.1" class="w-full" value="{{ old('disk_threshold', $server->disk_threshold) }}" />
                            <x-input-error :messages="$errors->get('disk_threshold')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-input-label for="offline_threshold_seconds" :value="__('Offline after seconds')" />
                            <x-text-input id="offline_threshold_seconds" name="offline_threshold_seconds" type="number" min="5" max="3600" step="1" class="w-full" value="{{ old('offline_threshold_seconds', $server->offline_threshold_seconds ?? 15) }}" required />
                            <x-input-error :messages="$errors->get('offline_threshold_seconds')" class="mt-1" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="alert_cooldown_seconds" :value="__('Alert cooldown seconds')" />
                            <x-text-input id="alert_cooldown_seconds" name="alert_cooldown_seconds" type="number" min="60" max="86400" step="1" class="w-full" value="{{ old('alert_cooldown_seconds', $server->alert_cooldown_seconds ?? 900) }}" required />
                            <x-input-error :messages="$errors->get('alert_cooldown_seconds')" class="mt-1" />
                        </div>
                    </div>
                </div>

                    <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                        <h3 class="text-sm font-semibold text-slate-100">Maintenance window</h3>
                        <p class="mt-1 text-xs text-slate-400">When active, alerts are suppressed and server downtime is treated as expected.</p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <x-input-label for="maintenance_starts_at" :value="__('Starts at')" />
                                <x-text-input id="maintenance_starts_at" name="maintenance_starts_at" type="datetime-local" class="w-full" value="{{ old('maintenance_starts_at', optional($server->maintenance_starts_at)->format('Y-m-d\TH:i')) }}" />
                                <x-input-error :messages="$errors->get('maintenance_starts_at')" class="mt-1" />
                            </div>
                            <div class="space-y-2">
                                <x-input-label for="maintenance_ends_at" :value="__('Ends at')" />
                                <x-text-input id="maintenance_ends_at" name="maintenance_ends_at" type="datetime-local" class="w-full" value="{{ old('maintenance_ends_at', optional($server->maintenance_ends_at)->format('Y-m-d\TH:i')) }}" />
                                <x-input-error :messages="$errors->get('maintenance_ends_at')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-100">Agent Install Settings</h3>
                            <p class="mt-1 text-xs text-slate-400">Download config or copy a ready-to-run install/update command.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('agents.config', $server) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Download config</a>
                            <button type="button" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" onclick="copyText('install-command')">Copy install</button>
                            <button type="button" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" onclick="copyText('update-command')">Copy update</button>
                        </div>
                    </div>

                    <div class="mt-3 overflow-x-auto rounded border border-white/10 bg-slate-950 p-3 text-xs text-slate-200">
<pre id="install-command">powershell -NoProfile -ExecutionPolicy Bypass -Command "Invoke-WebRequest -Uri 'https://example.com/ServerMonitorAgent/installer/install-service.ps1' -OutFile 'install-service.ps1'; .\install-service.ps1"</pre>
                    </div>
                    <div class="mt-3 overflow-x-auto rounded border border-white/10 bg-slate-950 p-3 text-xs text-slate-200">
<pre id="update-command">powershell -NoProfile -ExecutionPolicy Bypass -Command "& { Stop-Service ServerMonitorAgent; Copy-Item -Path '.\\dist\\server-monitor-agent-new.exe' -Destination 'C:\\Program Files\\ServerMonitorAgent\\server-monitor-agent.exe' -Force; Start-Service ServerMonitorAgent }"</pre>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">Copy these PowerShell commands and paste them into an elevated PowerShell window.</p>
                </div>

                    <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                        <h3 class="text-sm font-semibold text-slate-100">Agent Reported Info</h3>
                        <div class="mt-2 text-sm text-slate-200">
                            <div>Version: <span class="font-medium text-slate-900 dark:text-white">{{ $server->agent_version ?? 'Not reported' }}</span></div>
                            <div>Config schema: <span class="font-medium text-slate-900 dark:text-white">{{ $server->config_schema_version ?? '—' }}</span></div>
                            <div>Hostname: <span class="font-medium text-slate-900 dark:text-white">{{ $server->agent_hostname ?? '—' }}</span></div>
                            <div>OS: <span class="font-medium text-slate-900 dark:text-white">{{ $server->agent_os ?? $server->os ?? '—' }}</span></div>
                            <div>Runtime: <span class="font-medium text-slate-900 dark:text-white">{{ $server->agent_runtime ?? '—' }}</span></div>
                            <div>Capabilities: <span class="font-medium text-slate-900 dark:text-white">{{ is_array($server->capabilities) ? implode(', ', $server->capabilities) : ($server->capabilities ?? '—') }}</span></div>
                        </div>
                    </div>

                <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                    <h3 class="text-sm font-semibold text-slate-100">Used by Applications</h3>
                    @if($server->applications->isEmpty())
                        <p class="text-sm text-slate-400">This server is not assigned to any application.</p>
                    @else
                        <ul>
                            @foreach($server->applications as $app)
                                <li>
                                    <a href="{{ route('applications.show', $app->id) }}" class="font-medium">{{ $app->name }}</a>
                                    – role: {{ $app->pivot->role ?? 'n/a' }}
                                    @if($app->pivot->is_primary) <span class="text-xs text-green-400">(primary)</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <a href="{{ route('servers.index') }}" class="inline-flex items-center justify-center rounded border border-white/15 bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">
                        Cancel
                    </a>
                    <x-primary-button>
                        Update Server
                    </x-primary-button>
                </div>
            </form>
        </div>

        <div class="mt-6 rounded-lg glass p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-100">Agent Deployment Generator</h3>
                    <p class="mt-1 text-xs text-slate-400">Preview config options, download a keyed package, or rotate this server's agent key.</p>
                </div>
            </div>
            <div class="mt-4">
                @include('agents._deployment-actions', ['server' => $server, 'context' => 'server-edit'])
            </div>
        </div>
    </div>

<script>
    function copyText(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;
        navigator.clipboard.writeText(element.textContent.trim())
            .then(() => {
                alert('Command copied to clipboard');
            })
            .catch(() => {
                alert('Unable to copy command');
            });
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-copy-text]');
        if (!button) {
            return;
        }

        navigator.clipboard.writeText(button.dataset.copyText || '');
    });
</script>
</x-app-layout>
