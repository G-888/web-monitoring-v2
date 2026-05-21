<x-app-layout>
    <x-slot name="header_title">Maintenance Report Preview</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">HTML Preview</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $report->title }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.maintenance.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">New Report</a>
                <a href="{{ route('reports.maintenance.history') }}" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">History</a>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
            @include('reports.maintenance.report-body', ['report' => $report, 'summary' => $summary])
        </section>
    </div>
</x-app-layout>
