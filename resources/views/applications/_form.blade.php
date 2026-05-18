@php
    $isEdit = isset($application);
    $urlValue = old('urls', $urlsText ?? '');
    if (is_array($urlValue)) {
        $urlValue = implode("\n", $urlValue);
    }
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-6 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Application</h3>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div class="space-y-2">
                    <x-input-label for="name" :value="__('Application Name')" />
                    <x-text-input id="name" name="name" type="text" class="w-full" value="{{ old('name', $application->name ?? '') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div class="space-y-2">
                    <x-input-label for="code" :value="__('Code')" />
                    <x-text-input id="code" name="code" type="text" class="w-full" value="{{ old('code', $application->code ?? '') }}" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>
                <div class="space-y-2">
                    <x-input-label for="environment" :value="__('Environment')" />
                    <x-text-input id="environment" name="environment" type="text" class="w-full" value="{{ old('environment', $application->environment ?? '') }}" placeholder="production, staging, uat" />
                    <x-input-error :messages="$errors->get('environment')" class="mt-1" />
                </div>
                <div class="space-y-2">
                    <x-input-label for="owner_team" :value="__('Owner Team')" />
                    <x-text-input id="owner_team" name="owner_team" type="text" class="w-full" value="{{ old('owner_team', $application->owner_team ?? '') }}" />
                    <x-input-error :messages="$errors->get('owner_team')" class="mt-1" />
                </div>
                <div class="space-y-2 md:col-span-2">
                    <x-input-label for="description" :value="__('Description')" />
                    <textarea id="description" name="description" rows="3" class="block w-full rounded-md border-gray-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">{{ old('description', $application->description ?? '') }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-6 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Health Rules</h3>
            <div class="mt-5 grid gap-5">
                <div class="space-y-2">
                    <x-input-label for="app_servers_min_required" :value="__('App servers min required')" />
                    <x-text-input id="app_servers_min_required" name="app_servers_min_required" type="number" min="0" max="100" class="w-full" value="{{ old('app_servers_min_required', $appMinRequired ?? 1) }}" required />
                    <x-input-error :messages="$errors->get('app_servers_min_required')" class="mt-1" />
                </div>
                <div class="space-y-2">
                    <x-input-label for="database_servers_min_required" :value="__('Database servers min required')" />
                    <x-text-input id="database_servers_min_required" name="database_servers_min_required" type="number" min="0" max="100" class="w-full" value="{{ old('database_servers_min_required', $databaseMinRequired ?? 1) }}" required />
                    <x-input-error :messages="$errors->get('database_servers_min_required')" class="mt-1" />
                </div>
                <div class="space-y-2">
                    <x-input-label for="urls" :value="__('URLs')" />
                    <textarea id="urls" name="urls" rows="4" class="block w-full rounded-md border-gray-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="https://app.example.com">{{ $urlValue }}</textarea>
                    <x-input-error :messages="$errors->get('urls')" class="mt-1" />
                </div>
            </div>
        </section>
    </div>

    <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-6 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Server Roles</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use multiple rows for the same server when it provides multiple roles.</p>
            </div>
            <span class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ count($mappingRows) }} rows</span>
        </div>

        <div class="mt-5 space-y-3">
            @foreach($mappingRows as $index => $mapping)
                <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60 lg:grid-cols-[1.3fr_1fr_auto_auto_1fr]">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400" for="mapping-server-{{ $index }}">Server</label>
                        <select id="mapping-server-{{ $index }}" name="mappings[{{ $index }}][server_id]" class="block w-full rounded-md border-gray-300 bg-white text-sm text-slate-900 shadow-sm dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                            <option value="">Not assigned</option>
                            @foreach($servers as $server)
                                <option value="{{ $server->id }}" @selected((string) old("mappings.$index.server_id", $mapping['server_id'] ?? '') === (string) $server->id)>
                                    {{ $server->name }} / {{ $server->server_id }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get(\"mappings.$index.server_id\")" class="mt-1" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400" for="mapping-role-{{ $index }}">Role</label>
                        <select id="mapping-role-{{ $index }}" name="mappings[{{ $index }}][role]" class="block w-full rounded-md border-gray-300 bg-white text-sm text-slate-900 shadow-sm dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                            <option value="">Select role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role }}" @selected(old("mappings.$index.role", $mapping['role'] ?? '') === $role)>
                                    {{ str_replace('_', ' ', ucfirst($role)) }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get(\"mappings.$index.role\")" class="mt-1" />
                    </div>

                    <label class="flex items-center gap-2 pt-7 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="mappings[{{ $index }}][is_primary]" value="1" class="rounded border-white/10 bg-white/5 text-orange-500 focus:ring-orange-500" @checked((bool) old("mappings.$index.is_primary", $mapping['is_primary'] ?? false)) />
                        Primary
                    </label>

                    <label class="flex items-center gap-2 pt-7 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="mappings[{{ $index }}][is_required]" value="1" class="rounded border-white/10 bg-white/5 text-orange-500 focus:ring-orange-500" @checked((bool) old("mappings.$index.is_required", $mapping['is_required'] ?? true)) />
                        Required
                    </label>

                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400" for="mapping-notes-{{ $index }}">Notes</label>
                        <x-text-input id="mapping-notes-{{ $index }}" name="mappings[{{ $index }}][notes]" type="text" class="w-full" value="{{ old(\"mappings.$index.notes\", $mapping['notes'] ?? '') }}" />
                        <x-input-error :messages="$errors->get(\"mappings.$index.notes\")" class="mt-1" />
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex items-center justify-between gap-3">
        <a href="{{ $isEdit ? route('applications.show', $application) : route('applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/10 transition hover:bg-orange-500">
            {{ $isEdit ? 'Update Application' : 'Create Application' }}
        </button>
    </div>
</form>
