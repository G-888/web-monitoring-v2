@csrf

<div class="grid gap-5 md:grid-cols-2">
    <div class="space-y-2">
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" class="w-full" value="{{ old('name', $networkMonitor->name) }}" required />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="type" :value="__('Type')" />
        <select id="type" name="type" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
            <option value="tcp_port" @selected(old('type', $networkMonitor->type) === 'tcp_port')>TCP Port</option>
            <option value="dns" @selected(old('type', $networkMonitor->type) === 'dns')>DNS</option>
            <option value="ping" @selected(old('type', $networkMonitor->type) === 'ping')>Ping (unsupported fallback)</option>
        </select>
        <x-input-error :messages="$errors->get('type')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="source_type" :value="__('Source')" />
        <select id="source_type" name="source_type" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
            <option value="central" @selected(old('source_type', $networkMonitor->source_type) === 'central')>Central Laravel server</option>
            <option value="agent" @selected(old('source_type', $networkMonitor->source_type) === 'agent')>Windows agent server</option>
        </select>
        <x-input-error :messages="$errors->get('source_type')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="source_server_id" :value="__('Source server')" />
        <select id="source_server_id" name="source_server_id" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
            <option value="">None / central source</option>
            @foreach($servers as $server)
                <option value="{{ $server->id }}" @selected((string) old('source_server_id', $networkMonitor->source_server_id) === (string) $server->id)>{{ $server->name }} ({{ $server->server_id }})</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('source_server_id')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="target_server_id" :value="__('Destination server')" />
        <select id="target_server_id" name="target_server_id" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
            <option value="">External / DNS target</option>
            @foreach($servers as $server)
                <option value="{{ $server->id }}" @selected((string) old('target_server_id', $networkMonitor->target_server_id) === (string) $server->id)>{{ $server->name }} ({{ $server->server_id }})</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('target_server_id')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="dependency_type" :value="__('Dependency type')" />
        <select id="dependency_type" name="dependency_type" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
            <option value="">External dependency</option>
            @foreach(\App\Models\NetworkMonitor::DEPENDENCY_TYPES as $dependencyType)
                <option value="{{ $dependencyType }}" @selected(old('dependency_type', $networkMonitor->dependency_type) === $dependencyType)>{{ str_replace('_', ' ', ucfirst($dependencyType)) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('dependency_type')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="target_host" :value="__('Destination host')" />
        <x-text-input id="target_host" name="target_host" class="w-full" value="{{ old('target_host', $networkMonitor->target_host) }}" placeholder="10.0.0.10 or db.internal" required />
        <x-input-error :messages="$errors->get('target_host')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="target_port" :value="__('Destination port')" />
        <x-text-input id="target_port" name="target_port" type="number" min="1" max="65535" class="w-full" value="{{ old('target_port', $networkMonitor->target_port) }}" placeholder="443" />
        <x-input-error :messages="$errors->get('target_port')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="protocol" :value="__('Protocol')" />
        <select id="protocol" name="protocol" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
            @foreach(['tcp' => 'TCP', 'dns' => 'DNS', 'icmp' => 'ICMP', 'udp' => 'UDP'] as $value => $label)
                <option value="{{ $value }}" @selected(old('protocol', $networkMonitor->protocol ?: 'tcp') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('protocol')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="dns_record_type" :value="__('DNS record type')" />
        <select id="dns_record_type" name="dns_record_type" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
            @foreach(['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT'] as $type)
                <option value="{{ $type }}" @selected(old('dns_record_type', $networkMonitor->dns_record_type ?: 'A') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('dns_record_type')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="expected_state" :value="__('Expected State')" />
        <select id="expected_state" name="expected_state" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" required>
            <option value="open" @selected(old('expected_state', $networkMonitor->expected_state) === 'open')>Open</option>
            <option value="closed" @selected(old('expected_state', $networkMonitor->expected_state) === 'closed')>Closed</option>
        </select>
        <x-input-error :messages="$errors->get('expected_state')" />
    </div>

    <div class="space-y-2 md:col-span-2">
        <x-input-label for="expected_value" :value="__('Expected DNS value')" />
        <textarea id="expected_value" name="expected_value" rows="3" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100" placeholder="One or more DNS values, separated by commas or new lines">{{ old('expected_value', $networkMonitor->expected_value) }}</textarea>
        <x-input-error :messages="$errors->get('expected_value')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="application_id" :value="__('Application dependency')" />
        <select id="application_id" name="application_id" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
            <option value="">None</option>
            @foreach($applications as $application)
                <option value="{{ $application->id }}" @selected((string) old('application_id', $networkMonitor->application_id) === (string) $application->id)>{{ $application->name }}{{ $application->environment ? ' / '.$application->environment : '' }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('application_id')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="timeout_ms" :value="__('Timeout ms')" />
        <x-text-input id="timeout_ms" name="timeout_ms" type="number" min="200" max="30000" class="w-full" value="{{ old('timeout_ms', $networkMonitor->timeout_ms ?? 3000) }}" required />
        <x-input-error :messages="$errors->get('timeout_ms')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="latency_threshold_ms" :value="__('Latency alert threshold ms')" />
        <x-text-input id="latency_threshold_ms" name="latency_threshold_ms" type="number" min="1" class="w-full" value="{{ old('latency_threshold_ms', $networkMonitor->latency_threshold_ms) }}" />
        <x-input-error :messages="$errors->get('latency_threshold_ms')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="interval_seconds" :value="__('Interval seconds')" />
        <x-text-input id="interval_seconds" name="interval_seconds" type="number" min="30" max="86400" class="w-full" value="{{ old('interval_seconds', $networkMonitor->interval_seconds ?? 300) }}" required />
        <x-input-error :messages="$errors->get('interval_seconds')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="alert_cooldown_seconds" :value="__('Alert cooldown seconds')" />
        <x-text-input id="alert_cooldown_seconds" name="alert_cooldown_seconds" type="number" min="60" max="86400" class="w-full" value="{{ old('alert_cooldown_seconds', $networkMonitor->alert_cooldown_seconds ?? 900) }}" required />
        <x-input-error :messages="$errors->get('alert_cooldown_seconds')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="maintenance_starts_at" :value="__('Maintenance starts at')" />
        <x-text-input id="maintenance_starts_at" name="maintenance_starts_at" type="datetime-local" class="w-full" value="{{ old('maintenance_starts_at', $networkMonitor->maintenance_starts_at?->format('Y-m-d\\TH:i')) }}" />
        <x-input-error :messages="$errors->get('maintenance_starts_at')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="maintenance_ends_at" :value="__('Maintenance ends at')" />
        <x-text-input id="maintenance_ends_at" name="maintenance_ends_at" type="datetime-local" class="w-full" value="{{ old('maintenance_ends_at', $networkMonitor->maintenance_ends_at?->format('Y-m-d\\TH:i')) }}" />
        <x-input-error :messages="$errors->get('maintenance_ends_at')" />
    </div>
</div>

<label class="mt-5 flex items-center gap-2">
    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 bg-white text-orange-600 dark:border-white/10 dark:bg-white/5" {{ old('is_active', $networkMonitor->is_active ?? false) ? 'checked' : '' }}>
    <span class="text-sm text-slate-600 dark:text-slate-300">Active</span>
</label>

<div class="mt-6 flex items-center justify-between">
    <a href="{{ route('network-monitors.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-white/10 dark:text-slate-200">Cancel</a>
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
</div>
