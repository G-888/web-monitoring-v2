<x-app-layout>
    <x-slot name="header_title">Network Map</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Application Dependency Map</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Application-level network dependencies with explicit direction, expected state, and latest health.</p>
            </div>
            <a href="{{ route('network-monitors.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Network Monitors</a>
        </div>

        @forelse($networkMonitors as $applicationName => $monitors)
            <section class="rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                <div class="border-b border-slate-200/70 p-5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $applicationName }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Source</th>
                                <th class="px-4 py-3">Destination</th>
                                <th class="px-4 py-3">Dependency</th>
                                <th class="px-4 py-3">Port</th>
                                <th class="px-4 py-3">Protocol</th>
                                <th class="px-4 py-3">Expected</th>
                                <th class="px-4 py-3">Latest Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                            @foreach($monitors as $monitor)
                                @php
                                    $badge = $monitor->healthBadge();
                                    $badgeClass = match ($badge) {
                                        'healthy' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
                                        'critical' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200',
                                        default => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-4 text-slate-700 dark:text-slate-200">{{ $monitor->sourceLabel() }}</td>
                                    <td class="px-4 py-4 text-slate-700 dark:text-slate-200">{{ $monitor->destinationLabel() }}<div class="font-mono text-xs text-slate-500">{{ $monitor->endpointLabel() }}</div></td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ str_replace('_', ' ', $monitor->dependency_type ?: 'external_dependency') }}</td>
                                    <td class="px-4 py-4 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $monitor->target_port ?: '-' }}</td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ strtoupper($monitor->protocol ?: $monitor->type) }}</td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $monitor->type === 'dns' ? ($monitor->expected_value ?: 'No drift') : ucfirst($monitor->expected_state) }}</td>
                                    <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ ucfirst($badge) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="rounded-xl border border-slate-200/70 bg-white/80 p-8 text-center text-slate-500 shadow-sm dark:border-white/10 dark:bg-slate-900/70">No network dependencies mapped yet.</section>
        @endforelse
    </div>
</x-app-layout>
