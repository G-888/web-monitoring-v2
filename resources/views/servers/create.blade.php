<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Add Server</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <div class="mt-6 rounded-lg glass p-6">
            <form method="POST" action="{{ route('servers.store') }}" class="space-y-6">
                @csrf

                <div class="space-y-2">
                    <x-input-label for="name" :value="__('Server Name')" />
                    <x-text-input id="name" name="name" type="text" class="w-full" value="{{ old('name') }}" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="space-y-2">
                    <x-input-label for="server_id" :value="__('Server ID')" />
                    <x-text-input id="server_id" name="server_id" type="text" class="w-full" value="{{ old('server_id') }}" placeholder="e.g. web-node-01" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('server_id')" class="mt-1" />
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <x-input-label for="ip_address" :value="__('IP Address')" />
                        <x-text-input id="ip_address" name="ip_address" type="text" class="w-full" value="{{ old('ip_address') }}" placeholder="192.168.1.10" autocomplete="off" />
                        <x-input-error :messages="$errors->get('ip_address')" class="mt-1" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="os" :value="__('Operating System')" />
                        <x-text-input id="os" name="os" type="text" class="w-full" value="{{ old('os') }}" placeholder="Linux / Windows" autocomplete="off" />
                        <x-input-error :messages="$errors->get('os')" class="mt-1" />
                    </div>
                </div>

                <div class="space-y-2">
                    <x-input-label for="location" :value="__('Location')" />
                    <x-text-input id="location" name="location" type="text" class="w-full" value="{{ old('location') }}" placeholder="Data center or region" autocomplete="off" />
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <x-input-label for="group" :value="__('Group')" />
                        <x-text-input id="group" name="group" type="text" class="w-full" value="{{ old('group') }}" placeholder="Production / Staging / Client name" autocomplete="off" />
                        <x-input-error :messages="$errors->get('group')" class="mt-1" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="tags" :value="__('Tags')" />
                        <x-text-input id="tags" name="tags" type="text" class="w-full" value="{{ old('tags') }}" placeholder="web, mysql, coldfusion" autocomplete="off" />
                        <x-input-error :messages="$errors->get('tags')" class="mt-1" />
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <x-input-label for="latitude" :value="__('Latitude')" />
                        <x-text-input id="latitude" name="latitude" type="text" class="w-full" value="{{ old('latitude') }}" placeholder="e.g. 37.7749" autocomplete="off" />
                        <x-input-error :messages="$errors->get('latitude')" class="mt-1" />
                    </div>
                    <div class="space-y-2">
                        <x-input-label for="longitude" :value="__('Longitude')" />
                        <x-text-input id="longitude" name="longitude" type="text" class="w-full" value="{{ old('longitude') }}" placeholder="e.g. -122.4194" autocomplete="off" />
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
                            {{ old('is_active', true) ? 'checked' : '' }}
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
                                {{ old('alerts_enabled', true) ? 'checked' : '' }}
                            />
                            <span class="text-sm text-slate-200">Enable alerts</span>
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <x-input-label for="cpu_threshold" :value="__('CPU %')" />
                            <x-text-input id="cpu_threshold" name="cpu_threshold" type="number" min="0" max="100" step="0.1" class="w-full" value="{{ old('cpu_threshold', 90) }}" />
                            <x-input-error :messages="$errors->get('cpu_threshold')" class="mt-1" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="ram_threshold" :value="__('RAM %')" />
                            <x-text-input id="ram_threshold" name="ram_threshold" type="number" min="0" max="100" step="0.1" class="w-full" value="{{ old('ram_threshold', 90) }}" />
                            <x-input-error :messages="$errors->get('ram_threshold')" class="mt-1" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="disk_threshold" :value="__('Disk %')" />
                            <x-text-input id="disk_threshold" name="disk_threshold" type="number" min="0" max="100" step="0.1" class="w-full" value="{{ old('disk_threshold', 90) }}" />
                            <x-input-error :messages="$errors->get('disk_threshold')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-input-label for="offline_threshold_seconds" :value="__('Offline after seconds')" />
                            <x-text-input id="offline_threshold_seconds" name="offline_threshold_seconds" type="number" min="5" max="3600" step="1" class="w-full" value="{{ old('offline_threshold_seconds', 15) }}" required />
                            <x-input-error :messages="$errors->get('offline_threshold_seconds')" class="mt-1" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="alert_cooldown_seconds" :value="__('Alert cooldown seconds')" />
                            <x-text-input id="alert_cooldown_seconds" name="alert_cooldown_seconds" type="number" min="60" max="86400" step="1" class="w-full" value="{{ old('alert_cooldown_seconds', 900) }}" required />
                            <x-input-error :messages="$errors->get('alert_cooldown_seconds')" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                    <h3 class="text-sm font-semibold text-slate-100">Maintenance window</h3>
                    <p class="mt-1 text-xs text-slate-400">When active, alerts are suppressed and website incidents are excluded from history.</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-input-label for="maintenance_starts_at" :value="__('Starts at')" />
                            <x-text-input id="maintenance_starts_at" name="maintenance_starts_at" type="datetime-local" class="w-full" value="{{ old('maintenance_starts_at') }}" />
                            <x-input-error :messages="$errors->get('maintenance_starts_at')" class="mt-1" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="maintenance_ends_at" :value="__('Ends at')" />
                            <x-text-input id="maintenance_ends_at" name="maintenance_ends_at" type="datetime-local" class="w-full" value="{{ old('maintenance_ends_at') }}" />
                            <x-input-error :messages="$errors->get('maintenance_ends_at')" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <a href="{{ route('servers.index') }}" class="inline-flex items-center justify-center rounded border border-white/15 bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">
                        Cancel
                    </a>
                    <x-primary-button>
                        Save Server
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
