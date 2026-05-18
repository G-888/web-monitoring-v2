<x-app-layout>
    <x-slot name="header_title">SSL Monitor</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">SSL Monitor</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-400">
                    Track certificate expiry for HTTPS monitors. URLs added through Add Monitor appear here automatically.
                </p>
            </div>
            <a href="{{ route('monitors.create') }}" class="inline-flex items-center justify-center rounded-xl bg-orange-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-orange-600/20 hover:bg-orange-500">
                Add Full Monitor
            </a>
        </div>

        @if($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['label' => 'Tracked', 'value' => $summary['total'], 'class' => 'text-slate-900 dark:text-white'],
                ['label' => 'Valid', 'value' => $summary['valid'], 'class' => 'text-emerald-600 dark:text-emerald-300'],
                ['label' => 'Expiring', 'value' => $summary['expiring'], 'class' => 'text-amber-600 dark:text-amber-300'],
                ['label' => 'Expired', 'value' => $summary['expired'], 'class' => 'text-red-600 dark:text-red-300'],
                ['label' => 'Pending', 'value' => $summary['pending'], 'class' => 'text-sky-600 dark:text-sky-300'],
            ] as $item)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-400">{{ $item['label'] }}</div>
                    <div class="mt-3 text-3xl font-black {{ $item['class'] }}">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <form method="POST" action="{{ route('ssl-monitors.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="urls" class="text-sm font-bold text-slate-900 dark:text-white">Add SSL URLs</label>
                    <textarea
                        id="urls"
                        name="urls"
                        rows="6"
                        class="mt-2 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-white/10 dark:bg-[#020617] dark:text-white"
                        placeholder="https://example.com"
                    >{{ old('urls') }}</textarea>
                    <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">One URL per line or comma separated. Only HTTPS URLs are tracked here.</p>
                </div>
                <button type="submit" class="rounded-xl bg-orange-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-orange-600/20 hover:bg-orange-500">
                    Add SSL Monitor
                </button>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-base font-black text-slate-900 dark:text-white">Certificates</h2>
                @if($monitors->isNotEmpty())
                    <form method="POST" action="{{ route('ssl-monitors.check-all') }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                            Check All
                        </button>
                    </form>
                @endif
            </div>

            @if($monitors->isEmpty())
                <div class="p-8 text-center text-sm font-semibold text-slate-500 dark:text-slate-400">
                    No HTTPS monitors yet.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
                        <thead class="bg-slate-50 dark:bg-white/5">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-widest text-slate-500">Monitor</th>
                                <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-widest text-slate-500">SSL Status</th>
                                <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-widest text-slate-500">Reason</th>
                                <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-widest text-slate-500">Expires</th>
                                <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-widest text-slate-500">Issuer</th>
                                <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-widest text-slate-500">Alert</th>
                                <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-widest text-slate-500">Last Check</th>
                                <th class="px-5 py-3 text-right text-xs font-black uppercase tracking-widest text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                            @foreach($monitors as $monitor)
                                @php
                                    $daysLeft = $monitor->ssl_expires_at ? (int) floor(now()->diffInDays($monitor->ssl_expires_at, false)) : null;
                                    $status = 'Pending';
                                    $statusClass = 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300';

                                    if ($daysLeft !== null && $daysLeft < 0) {
                                        $status = 'Expired';
                                        $statusClass = 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300';
                                    } elseif ($daysLeft !== null && $daysLeft <= 30) {
                                        $status = 'Expiring';
                                        $statusClass = 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
                                    } elseif ($daysLeft !== null) {
                                        $status = 'Valid';
                                        $statusClass = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
                                    }
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <div class="font-black text-slate-900 dark:text-white">{{ $monitor->name }}</div>
                                        <a href="{{ $monitor->url }}" target="_blank" rel="noopener" class="mt-1 block max-w-md truncate text-sm font-medium text-slate-500 hover:text-orange-600 dark:text-slate-400 dark:hover:text-orange-300">
                                            {{ $monitor->url }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ $statusClass }}">{{ $status }}</span>
                                        @if($daysLeft !== null)
                                            <div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $daysLeft >= 0 ? $daysLeft.' days left' : abs($daysLeft).' days overdue' }}</div>
                                        @endif
                                    </td>
                                    <td class="max-w-xs px-5 py-4 text-sm font-semibold text-slate-600 dark:text-slate-300">
                                        @if($monitor->ssl_last_error)
                                            <span class="line-clamp-2" title="{{ $monitor->ssl_last_error }}">{{ $monitor->ssl_last_error }}</span>
                                        @elseif($monitor->ssl_expires_at)
                                            <span class="text-emerald-600 dark:text-emerald-300">Certificate captured</span>
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">Waiting for first scan</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        {{ $monitor->ssl_expires_at?->format('Y-m-d H:i') ?? 'Pending scan' }}
                                    </td>
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-600 dark:text-slate-300">
                                        {{ $monitor->ssl_issuer ?: 'Unknown' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @can('update', $monitor)
                                            <form method="POST" action="{{ route('ssl-monitors.threshold', $monitor) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <input
                                                    type="number"
                                                    name="ssl_alert_threshold_days"
                                                    min="1"
                                                    max="365"
                                                    value="{{ old('ssl_alert_threshold_days', $monitor->ssl_alert_threshold_days ?? 60) }}"
                                                    class="w-20 rounded-xl border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-white/10 dark:bg-[#020617] dark:text-white"
                                                    aria-label="SSL alert threshold days"
                                                >
                                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">days</span>
                                                <button type="submit" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 hover:border-orange-300 hover:text-orange-600 dark:border-white/10 dark:text-slate-200 dark:hover:border-orange-500/50 dark:hover:text-orange-300">
                                                    Save
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $monitor->ssl_alert_threshold_days ?? 60 }} days</span>
                                        @endcan
                                    </td>
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-600 dark:text-slate-300">
                                        {{ $monitor->latestResult?->checked_at?->diffForHumans() ?? 'Never' }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                            <form method="POST" action="{{ route('ssl-monitors.check', $monitor) }}">
                                                @csrf
                                                <button type="submit" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 hover:border-orange-300 hover:text-orange-600 dark:border-white/10 dark:text-slate-200 dark:hover:border-orange-500/50 dark:hover:text-orange-300 sm:w-auto">
                                                    Check Now
                                                </button>
                                            </form>
                                            @can('delete', $monitor)
                                                <form method="POST" action="{{ route('ssl-monitors.destroy', $monitor) }}" onsubmit="return confirm('Remove this SSL monitor URL?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="w-full rounded-xl border border-red-200 px-3 py-2 text-xs font-black text-red-600 hover:border-red-300 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10 sm:w-auto">
                                                        Remove
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
