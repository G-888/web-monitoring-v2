<x-app-layout>
    <x-slot name="header_title">DB Monitor Setup</x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">DB Monitor Guided Setup</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $databaseMonitor->application?->name ?? 'Unmapped application' }} / {{ $databaseMonitor->server?->name ?? $databaseMonitor->host }}</p>
            </div>
            @if($databaseMonitor->application)
                <a href="{{ route('applications.architecture-review', $databaseMonitor->application) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Architecture Review</a>
            @endif
        </div>

        <section class="grid gap-4 md:grid-cols-3">
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Configuration</div>
                <div class="mt-3 text-lg font-bold {{ $databaseMonitor->configured_at ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $databaseMonitor->configured_at ? 'Configured' : 'Pending Configuration' }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Last Test</div>
                <div class="mt-3 text-lg font-bold">{{ ucfirst($databaseMonitor->last_status ?? 'not tested') }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Monitor</div>
                <div class="mt-3 text-lg font-bold {{ $databaseMonitor->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $databaseMonitor->is_active ? 'Enabled' : 'Disabled' }}</div>
            </div>
        </section>

        <form method="POST" action="{{ route('database-monitors.guided-setup.update', $databaseMonitor) }}" class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            @csrf
            @method('PATCH')
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Connection Settings</h3>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div>
                    <x-input-label for="driver" :value="__('DB type')" />
                    <select id="driver" name="driver" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                        <option value="mysql" @selected(old('driver', $databaseMonitor->driver) === 'mysql')>MySQL / MariaDB</option>
                        <option value="pgsql" @selected(old('driver', $databaseMonitor->driver) === 'pgsql')>PostgreSQL</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="db_role" :value="__('DB role')" />
                    <select id="db_role" name="db_role" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                        @foreach(['primary', 'secondary', 'cluster_member', 'reporting'] as $role)
                            <option value="{{ $role }}" @selected(old('db_role', $databaseMonitor->db_role ?? 'cluster_member') === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><x-input-label for="host" :value="__('Host')" /><x-text-input id="host" name="host" class="mt-1 w-full" :value="old('host', $databaseMonitor->host)" required /></div>
                <div><x-input-label for="port" :value="__('Port')" /><x-text-input id="port" name="port" type="number" min="1" max="65535" class="mt-1 w-full" :value="old('port', $databaseMonitor->port)" required /></div>
                <div><x-input-label for="database_name" :value="__('Database name')" /><x-text-input id="database_name" name="database_name" class="mt-1 w-full" :value="old('database_name', $databaseMonitor->database_name)" required /></div>
                <div><x-input-label for="username" :value="__('Username')" /><x-text-input id="username" name="username" class="mt-1 w-full" :value="old('username', $databaseMonitor->username)" required /></div>
                <div><x-input-label for="password" :value="__('Password')" /><x-text-input id="password" name="password" type="password" class="mt-1 w-full" placeholder="Leave blank to keep existing password" /></div>
                <div><x-input-label for="default_query" :value="__('Default query')" /><x-text-input id="default_query" name="default_query" class="mt-1 w-full" :value="old('default_query', $databaseMonitor->default_query ?? 'select 1')" /></div>
            </div>
            @isset($errors)
                <x-input-error :messages="$errors->all()" class="mt-4" />
            @endisset
            <div class="mt-6 flex justify-end">
                <button class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">Save Configuration</button>
            </div>
        </form>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Test And Enable</h3>
            <div class="mt-4 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('database-monitors.guided-setup.test', $databaseMonitor) }}">
                    @csrf
                    <button class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Test Connection</button>
                </form>
                <form method="POST" action="{{ route('database-monitors.guided-setup.enable', $databaseMonitor) }}">
                    @csrf
                    <label class="mr-3 inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="confirm_enable" value="1" class="rounded text-orange-600" />
                        Admin confirmation
                    </label>
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Enable Monitor</button>
                </form>
            </div>
        </section>
    </div>
</x-app-layout>
