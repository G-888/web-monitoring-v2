<x-app-layout>
    <x-slot name="header_title">Client Detail</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $client->name }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $client->code }}{{ $client->environment ? ' / '.$client->environment : '' }}{{ $client->support_team ? ' / '.$client->support_team : '' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('client-architecture.setup', ['client_id' => $client->id]) }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">Architecture Wizard</a>
                <a href="{{ route('clients.edit', $client) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Edit</a>
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-3">
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Applications</div>
                <div class="mt-3 text-3xl font-bold">{{ $client->applications->count() }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Contact</div>
                <div class="mt-3 text-lg font-bold">{{ $client->contact_name ?? 'Not set' }}</div>
                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $client->contact_email }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">Status</div>
                <div class="mt-3 text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ ucfirst($client->status) }}</div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="border-b border-slate-200/70 p-5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Applications</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Application</th>
                            <th class="px-4 py-3">Architecture</th>
                            <th class="px-4 py-3">Servers</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @forelse($client->applications as $application)
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $application->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $application->code }}</div>
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ str_replace('_', ' ', $application->architecture_type ?? 'custom') }}</td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $application->servers->unique('id')->count() }}</td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('applications.show', $application) }}" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-500">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No applications yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
