<x-app-layout>
    <x-slot name="header_title">Edit Application</x-slot>

    @php
        $existingRows = $application->servers
            ->map(fn ($server) => [
                'server_id' => $server->id,
                'role' => $server->pivot->role,
                'is_primary' => (bool) $server->pivot->is_primary,
                'is_required' => (bool) $server->pivot->is_required,
                'notes' => $server->pivot->notes,
            ])
            ->values()
            ->all();

        $mappingRows = old('mappings', array_merge(
            $existingRows,
            [
                ['server_id' => '', 'role' => '', 'is_primary' => false, 'is_required' => true, 'notes' => ''],
                ['server_id' => '', 'role' => '', 'is_primary' => false, 'is_required' => true, 'notes' => ''],
                ['server_id' => '', 'role' => '', 'is_primary' => false, 'is_required' => true, 'notes' => ''],
                ['server_id' => '', 'role' => '', 'is_primary' => false, 'is_required' => true, 'notes' => ''],
            ]
        ));

        $appMinRequired = old('app_servers_min_required', $application->minRequired(\App\Models\Application::RULE_APP_SERVERS, 'app_server'));
        $databaseMinRequired = old('database_servers_min_required', $application->minRequired(\App\Models\Application::RULE_DATABASE_SERVERS, 'db_server'));
        $urlsText = old('urls', $application->urls->pluck('url')->filter()->implode("\n"));
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Edit {{ $application->name }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $application->code }}{{ $application->environment ? ' / '.$application->environment : '' }}</p>
            </div>
            <a href="{{ route('applications.show', $application) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                Application Detail
            </a>
        </div>

        @include('applications._form', [
            'application' => $application,
            'action' => route('applications.update', $application),
            'method' => 'PATCH',
            'servers' => $servers,
            'roles' => $roles,
            'mappingRows' => $mappingRows,
            'appMinRequired' => $appMinRequired,
            'databaseMinRequired' => $databaseMinRequired,
            'urlsText' => $urlsText,
        ])
    </div>
</x-app-layout>
