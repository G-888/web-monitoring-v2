<x-app-layout>
    <x-slot name="header_title">Database Monitors</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold">Database Connection Tests</h2>
                <p class="mt-1 text-sm text-slate-500">Monitor database reachability and response time.</p>
            </div>
            <a href="{{ route('database-monitors.create') }}" class="inline-flex items-center justify-center rounded-xl bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">
                Add Database
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Endpoint</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Response</th>
                        <th class="px-4 py-3">Last Checked</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                    @forelse($databaseMonitors as $databaseMonitor)
                        @php
                            $isUp = $databaseMonitor->last_status === 'up';
                        @endphp
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900 dark:text-white">
                                {{ $databaseMonitor->name }}
                                <div class="text-xs text-slate-500">{{ strtoupper($databaseMonitor->driver) }} / {{ $databaseMonitor->database_name }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $databaseMonitor->host }}:{{ $databaseMonitor->port }}</td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $isUp ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300' }}">
                                    {{ $databaseMonitor->last_status ? ucfirst($databaseMonitor->last_status) : 'Unknown' }}
                                </span>
                                @if($databaseMonitor->last_error)
                                    <div class="mt-1 max-w-xs truncate text-xs text-red-500" title="{{ $databaseMonitor->last_error }}">{{ $databaseMonitor->last_error }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $databaseMonitor->last_response_time_ms !== null ? $databaseMonitor->last_response_time_ms . ' ms' : 'n/a' }}</td>
                            <td class="px-4 py-4 text-slate-500">{{ $databaseMonitor->last_checked_at ? $databaseMonitor->last_checked_at->diffForHumans() : 'Never' }}</td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('database-monitors.test', $databaseMonitor) }}">
                                        @csrf
                                        <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold dark:border-white/10">Test</button>
                                    </form>
                                    <a href="{{ route('database-monitors.edit', $databaseMonitor) }}" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold dark:border-white/10">Edit</a>
                                    <form method="POST" action="{{ route('database-monitors.destroy', $databaseMonitor) }}" onsubmit="return confirm('Delete this database monitor?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 dark:border-red-500/20">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No database monitors yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
