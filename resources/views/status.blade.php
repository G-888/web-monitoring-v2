<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Status</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-white">
    <main class="mx-auto max-w-5xl px-6 py-10">
        <div class="mb-8">
            <h1 class="text-3xl font-semibold">Service Status</h1>
            <p class="mt-2 text-sm text-slate-400">Current availability for monitored websites.</p>
        </div>

        <div class="overflow-hidden rounded-lg border border-white/10">
            <table class="min-w-full divide-y divide-white/10">
                <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Monitor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Response</th>
                        <th class="px-4 py-3">Uptime 24h</th>
                        <th class="px-4 py-3">Last checked</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-sm">
                    @forelse($monitors as $monitor)
                        @php($latest = $monitor->latestResult)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $monitor->name }}</div>
                                <div class="text-xs text-slate-400">{{ $monitor->url }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ $latest?->is_up ? 'text-green-400' : 'text-red-400' }}">
                                    {{ $latest?->is_up ? 'UP' : 'DOWN' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                {{ $latest?->response_time ? number_format($latest->response_time, 3).'s' : '-' }}
                            </td>
                            <td class="px-4 py-3">{{ $monitor->uptime_24h }}%</td>
                            <td class="px-4 py-3">{{ $latest?->checked_at?->diffForHumans() ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-8 text-center text-slate-400" colspan="5">
                                No monitors have been added yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
