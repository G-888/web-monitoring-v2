<x-app-layout>
    <x-slot name="header_title">Network Monitor Detail</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $networkMonitor->name }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $networkMonitor->sourceLabel() }} to {{ $networkMonitor->destinationLabel() }} on {{ strtoupper($networkMonitor->protocol ?: $networkMonitor->type) }} {{ $networkMonitor->target_port ?: '' }}.</p>
            </div>
            <a href="{{ route('network-monitors.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Back</a>
        </div>

        <section class="grid gap-4 md:grid-cols-4">
            @foreach([
                'Status' => ucfirst(str_replace('_', ' ', $networkMonitor->last_status ?: 'unknown')),
                'Latency' => $networkMonitor->last_latency_ms !== null ? $networkMonitor->last_latency_ms.' ms' : 'n/a',
                'Last Check' => $networkMonitor->last_checked_at?->diffForHumans() ?? 'Never',
                'Expected' => $networkMonitor->type === 'dns' ? ($networkMonitor->expected_value ?: 'Any result') : $networkMonitor->expected_state,
                'Application' => $networkMonitor->application?->name ?? 'Unmapped',
                'Dependency' => str_replace('_', ' ', $networkMonitor->dependency_type ?: 'external_dependency'),
            ] as $label => $value)
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900">
                    <div class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">{{ $value }}</div>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="border-b border-slate-200/70 p-5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Recent Results</h3>
            </div>
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Checked</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Latency</th>
                        <th class="px-4 py-3">Resolved</th>
                        <th class="px-4 py-3">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                    @forelse($results as $result)
                        <tr>
                            <td class="px-4 py-3 text-slate-500">{{ $result->checked_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $result->status)) }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $result->latency_ms !== null ? $result->latency_ms.' ms' : 'n/a' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $result->resolved_value ?: '-' }}</td>
                            <td class="px-4 py-3 text-red-500">{{ $result->error ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No network check history yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-app-layout>
