<x-app-layout>
    <x-slot name="header_title">Client Architecture Wizard</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Client Architecture Wizard</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Start from client and deployment architecture, then generate profiled agent packages.</p>
            </div>
            <a href="{{ route('clients.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Clients</a>
        </div>

        <form method="POST" action="{{ route('client-architecture.setup.store') }}" class="space-y-6">
            @csrf

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Step 1: Client Details</h3>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="client_id" :value="__('Existing client')" />
                        <select id="client_id" name="client_id" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                            <option value="">Create new client</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><x-input-label for="client_name" :value="__('Client name')" /><x-text-input id="client_name" name="client[name]" class="mt-1 w-full" /></div>
                    <div><x-input-label for="client_code" :value="__('Client code')" /><x-text-input id="client_code" name="client[code]" class="mt-1 w-full" /></div>
                    <div><x-input-label for="client_environment" :value="__('Environment')" /><x-text-input id="client_environment" name="client[environment]" class="mt-1 w-full" value="production" /></div>
                    <div><x-input-label for="contact_name" :value="__('Contact name')" /><x-text-input id="contact_name" name="client[contact_name]" class="mt-1 w-full" /></div>
                    <div><x-input-label for="contact_email" :value="__('Contact email')" /><x-text-input id="contact_email" type="email" name="client[contact_email]" class="mt-1 w-full" /></div>
                    <div><x-input-label for="support_team" :value="__('Support team')" /><x-text-input id="support_team" name="client[support_team]" class="mt-1 w-full" /></div>
                    <input type="hidden" name="client[status]" value="active" />
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Step 2: Application Details</h3>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div><x-input-label for="app_name" :value="__('Application name')" /><x-text-input id="app_name" name="application[name]" class="mt-1 w-full" required /></div>
                    <div><x-input-label for="app_code" :value="__('Application code')" /><x-text-input id="app_code" name="application[code]" class="mt-1 w-full" required /></div>
                    <div><x-input-label for="app_environment" :value="__('Environment')" /><x-text-input id="app_environment" name="application[environment]" class="mt-1 w-full" value="production" /></div>
                    <div><x-input-label for="public_url" :value="__('Public URL')" /><x-text-input id="public_url" name="application[public_url]" class="mt-1 w-full" placeholder="https://app.example.com" /></div>
                    <div class="md:col-span-2">
                        <x-input-label for="technology_stack" :value="__('Technology stack')" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach(['IIS', 'ColdFusion', 'MySQL', 'MySQL Router', 'Laravel', 'Node.js'] as $stack)
                                <label class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-200">
                                    <input type="checkbox" name="technology_stack[]" value="{{ $stack }}" class="mr-2 rounded text-orange-600" />{{ $stack }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Step 3: Architecture Template</h3>
                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($templates as $key => $template)
                        <label class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60">
                            <input type="radio" name="architecture_type" value="{{ $key }}" class="mr-2 text-orange-600" @checked($loop->first) />
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $template['name'] }}</span>
                            <span class="mt-2 block text-xs text-slate-500 dark:text-slate-400">{{ $template['description'] }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Step 4: Select Or Create Servers</h3>
                <div class="mt-5 grid gap-4 xl:grid-cols-2">
                    @foreach(['app_database' => 'App + DB', 'application' => 'Application / Router', 'database' => 'Database', 'web' => 'Web', 'worker' => 'Worker', 'scheduler' => 'Scheduler', 'file_storage' => 'File Storage'] as $slot => $label)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-slate-950/60">
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $label }}</div>
                            <select name="role_servers[{{ $slot }}][]" multiple size="5" class="mt-3 w-full rounded-lg border-slate-200 bg-white text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                                @foreach($servers as $server)
                                    <option value="{{ $server->id }}">{{ $server->name }} / {{ $server->server_id }}</option>
                                @endforeach
                            </select>
                            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                <input type="hidden" name="new_servers[{{ $slot }}][slot]" value="{{ $slot }}" />
                                <input name="new_servers[{{ $slot }}][name]" class="rounded-lg border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="New server name" />
                                <input name="new_servers[{{ $slot }}][server_id]" class="rounded-lg border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="server-id" />
                                <input name="new_servers[{{ $slot }}][ip_address]" class="rounded-lg border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="IP address" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Step 5: Profile Preview</h3>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400"><tr><th class="px-4 py-3">Server</th><th class="px-4 py-3">Current Profile</th><th class="px-4 py-3">Enabled Modules</th></tr></thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                            @foreach($servers as $server)
                                @php $profile = $profiles[$server->id] ?? null; @endphp
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-slate-900 dark:text-white">{{ $server->name }}</td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $profile['profile_name'] ?? 'Custom' }}</td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ filled($profile['enabledModules'] ?? []) ? implode(', ', $profile['enabledModules']) : 'systemMetrics, windowsServices' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Step 6: Generate Agent Packages</h3>
                <label class="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-200">
                    <input type="checkbox" name="generate_packages" value="1" class="rounded text-orange-600" />
                    Download one ZIP containing all per-server packages
                </label>
            </section>

            <x-input-error :messages="$errors->all()" />

            <div class="flex justify-end gap-2">
                <a href="{{ route('clients.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Cancel</a>
                <button class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">Create Architecture</button>
            </div>
        </form>
    </div>
</x-app-layout>
