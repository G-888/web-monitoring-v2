<x-app-layout>
    <x-slot name="header_title">Create Application</x-slot>

    @php
        $mappingRows = old('mappings', [
            ['server_id' => '', 'role' => 'application', 'is_primary' => true, 'is_required' => true, 'notes' => ''],
            ['server_id' => '', 'role' => 'database', 'is_primary' => true, 'is_required' => true, 'notes' => ''],
            ['server_id' => '', 'role' => 'web', 'is_primary' => false, 'is_required' => true, 'notes' => ''],
            ['server_id' => '', 'role' => 'worker', 'is_primary' => false, 'is_required' => false, 'notes' => ''],
            ['server_id' => '', 'role' => 'scheduler', 'is_primary' => false, 'is_required' => false, 'notes' => ''],
            ['server_id' => '', 'role' => 'file_storage', 'is_primary' => false, 'is_required' => false, 'notes' => ''],
            ['server_id' => '', 'role' => '', 'is_primary' => false, 'is_required' => true, 'notes' => ''],
            ['server_id' => '', 'role' => '', 'is_primary' => false, 'is_required' => true, 'notes' => ''],
        ]);
        $appMinRequired = old('app_servers_min_required', 1);
        $databaseMinRequired = old('database_servers_min_required', 1);
        $urlsText = old('urls', '');
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Create Application</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Map application tiers, database nodes, workers, schedulers, and file storage to monitored servers.</p>
            </div>
            <a href="{{ route('applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                Applications
            </a>
        </div>

        @include('applications._form', [
            'action' => route('applications.store'),
            'method' => 'POST',
            'servers' => $servers,
            'roles' => $roles,
            'mappingRows' => $mappingRows,
            'appMinRequired' => $appMinRequired,
            'databaseMinRequired' => $databaseMinRequired,
            'urlsText' => $urlsText,
        ])
    </div>
</x-app-layout>
