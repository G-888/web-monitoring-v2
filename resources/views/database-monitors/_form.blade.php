@csrf

<div class="grid gap-5 md:grid-cols-2">
    <div class="space-y-2">
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" class="w-full" value="{{ old('name', $databaseMonitor->name) }}" required />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="driver" :value="__('Driver')" />
        <select id="driver" name="driver" class="w-full rounded border-slate-300 dark:border-white/10 dark:bg-slate-950" required>
            <option value="mysql" @selected(old('driver', $databaseMonitor->driver) === 'mysql')>MySQL / MariaDB</option>
            <option value="pgsql" @selected(old('driver', $databaseMonitor->driver) === 'pgsql')>PostgreSQL</option>
        </select>
        <x-input-error :messages="$errors->get('driver')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="host" :value="__('Host')" />
        <x-text-input id="host" name="host" class="w-full" value="{{ old('host', $databaseMonitor->host) }}" required />
        <x-input-error :messages="$errors->get('host')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="port" :value="__('Port')" />
        <x-text-input id="port" name="port" type="number" min="1" max="65535" class="w-full" value="{{ old('port', $databaseMonitor->port) }}" required />
        <x-input-error :messages="$errors->get('port')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="database_name" :value="__('Database')" />
        <x-text-input id="database_name" name="database_name" class="w-full" value="{{ old('database_name', $databaseMonitor->database_name) }}" required />
        <x-input-error :messages="$errors->get('database_name')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="username" :value="__('Username')" />
        <x-text-input id="username" name="username" class="w-full" value="{{ old('username', $databaseMonitor->username) }}" required />
        <x-input-error :messages="$errors->get('username')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="password" :value="__('Password')" />
        <x-text-input id="password" name="password" type="password" class="w-full" :required="! $databaseMonitor->exists" />
        <x-input-error :messages="$errors->get('password')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="alert_cooldown_seconds" :value="__('Alert cooldown seconds')" />
        <x-text-input id="alert_cooldown_seconds" name="alert_cooldown_seconds" type="number" min="60" max="86400" class="w-full" value="{{ old('alert_cooldown_seconds', $databaseMonitor->alert_cooldown_seconds ?? 900) }}" required />
        <x-input-error :messages="$errors->get('alert_cooldown_seconds')" />
    </div>
</div>

<label class="mt-5 flex items-center gap-2">
    <input type="checkbox" name="is_active" value="1" class="rounded border-white/10 bg-white/5 text-blue-500" {{ old('is_active', $databaseMonitor->is_active ?? true) ? 'checked' : '' }}>
    <span class="text-sm text-slate-600 dark:text-slate-300">Active</span>
</label>

<div class="mt-6 flex items-center justify-between">
    <a href="{{ route('database-monitors.index') }}" class="rounded border border-slate-200 px-4 py-2 text-sm dark:border-white/10">Cancel</a>
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
</div>
