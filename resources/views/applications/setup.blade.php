<x-app-layout>
    <x-slot name="header_title">Application Setup Wizard</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Application Setup Wizard</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Map servers to roles and generate correctly profiled Windows agent packages.</p>
            </div>
            <a href="{{ route('applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Applications</a>
        </div>

        <form method="POST" action="{{ route('applications.setup.store') }}" class="space-y-6">
            @csrf

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-600 text-sm font-bold text-white">1</span>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Application Info</h3>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Name</label>
                        <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Code</label>
                        <input name="code" value="{{ old('code') }}" required class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Environment</label>
                        <input name="environment" value="{{ old('environment', 'production') }}" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Owner Team</label>
                        <input name="owner_team" value="{{ old('owner_team') }}" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">URLs</label>
                        <textarea name="urls" rows="3" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="https://app.example.com">{{ old('urls') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-600 text-sm font-bold text-white">2</span>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Deployment Type</h3>
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    @foreach($deploymentTypes as $type)
                        <label class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm font-semibold text-slate-700 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-200">
                            <input type="radio" name="deployment_type" value="{{ $type }}" class="mr-2 text-orange-600" @checked(old('deployment_type', 'single-server') === $type) />
                            {{ str_replace('-', ' ', \Illuminate\Support\Str::title($type)) }}
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-600 text-sm font-bold text-white">3</span>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Servers And Roles</h3>
                </div>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Server</th>
                                <th class="px-4 py-3">Assign Roles</th>
                                <th class="px-4 py-3">Current Profile</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                            @forelse($servers as $server)
                                @php $profile = $profiles[$server->id] ?? null; @endphp
                                <tr>
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ $server->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $server->server_id }}{{ $server->ip_address ? ' / '.$server->ip_address : '' }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex max-w-2xl flex-wrap gap-2">
                                            @foreach($roles as $role)
                                                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-200">
                                                    <input type="checkbox" name="server_roles[{{ $server->id }}][]" value="{{ $role }}" class="rounded border-slate-300 text-orange-600" />
                                                    {{ str_replace('_', ' ', $role) }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ $profile['profile_name'] ?? 'Custom' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ filled($profile['enabledModules'] ?? []) ? implode(', ', $profile['enabledModules']) : 'systemMetrics, windowsServices' }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No servers available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-600 text-sm font-bold text-white">4</span>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Profile Rules Preview</h3>
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach($deploymentProfiles as $key => $name)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60">
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $name }}</div>
                            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $key }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">App servers min required</label>
                        <input type="number" min="0" name="app_servers_min_required" value="{{ old('app_servers_min_required', 1) }}" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Database servers min required</label>
                        <input type="number" min="0" name="database_servers_min_required" value="{{ old('database_servers_min_required', 1) }}" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-600 text-sm font-bold text-white">5</span>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Generate Packages</h3>
                </div>
                <label class="mt-5 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-200">
                    <input type="checkbox" name="generate_packages" value="1" class="rounded border-slate-300 text-orange-600" />
                    Download all mapped server packages after setup
                </label>
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Cancel</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/10 transition hover:bg-orange-500">Finish Setup</button>
            </div>
        </form>
    </div>
</x-app-layout>
