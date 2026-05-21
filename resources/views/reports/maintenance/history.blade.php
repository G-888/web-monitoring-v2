<x-app-layout>
    <x-slot name="header_title">Report History</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Report History</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Generated maintenance reports and export files.</p>
            </div>
            <a href="{{ route('reports.maintenance.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                New Report
            </a>
        </div>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            @include('reports.maintenance._history-table', ['reports' => $reports])
            <div class="border-t border-slate-200/70 p-5 dark:border-white/10">
                {{ $reports->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
