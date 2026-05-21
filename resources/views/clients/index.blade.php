<x-app-layout>
    <x-slot name="header_title">Clients</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Clients</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Client-level onboarding, applications, architecture, and reporting scope.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('client-architecture.setup') }}" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">Architecture Wizard</a>
                <a href="{{ route('clients.create') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Add Client</a>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Environment</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Support Team</th>
                            <th class="px-4 py-3">Applications</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @forelse($clients as $client)
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $client->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $client->code }}</div>
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $client->environment ?? 'Not set' }}</td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                    <div>{{ $client->contact_name ?? 'Not set' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $client->contact_email }}</div>
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $client->support_team ?? 'Not set' }}</td>
                                <td class="px-4 py-4 font-semibold text-slate-900 dark:text-white">{{ $client->applications_count }}</td>
                                <td class="px-4 py-4"><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">{{ ucfirst($client->status) }}</span></td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('clients.show', $client) }}" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-500">View</a>
                                        <a href="{{ route('clients.edit', $client) }}" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No clients yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
