<x-app-layout>
    <x-slot name="header_title">Incident History</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">Incident History</h1>
            <p class="max-w-3xl text-sm font-medium text-slate-600 dark:text-slate-400">A unified timeline of recent website, SSL, webshell, and database issues.</p>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['label' => 'Total', 'value' => $summary['total'], 'class' => 'text-slate-900 dark:text-white'],
                ['label' => 'Critical', 'value' => $summary['critical'], 'class' => 'text-red-600 dark:text-red-300'],
                ['label' => 'Warning', 'value' => $summary['warning'], 'class' => 'text-amber-600 dark:text-amber-300'],
                ['label' => 'Info', 'value' => $summary['info'], 'class' => 'text-sky-600 dark:text-sky-300'],
            ] as $item)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-400">{{ $item['label'] }}</div>
                    <div class="mt-3 text-3xl font-black {{ $item['class'] }}">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-white/10">
                <h2 class="text-base font-black text-slate-900 dark:text-white">Timeline</h2>
            </div>

            @if($incidents->isEmpty())
                <div class="p-10 text-center text-sm font-semibold text-slate-500 dark:text-slate-400">No incidents found.</div>
            @else
                <div class="divide-y divide-slate-200 dark:divide-white/10">
                    @foreach($incidents as $incident)
                        @php
                            $severityClass = match ($incident['severity']) {
                                'critical' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                default => 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                            };
                        @endphp
                        <article class="grid gap-4 px-5 py-5 lg:grid-cols-[160px_minmax(0,1fr)_160px] lg:items-start">
                            <div>
                                <div class="text-xs font-black uppercase tracking-widest text-slate-400">{{ $incident['type'] }}</div>
                                <div class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-black uppercase {{ $severityClass }}">{{ $incident['severity'] }}</div>
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-black text-slate-900 dark:text-white">{{ $incident['title'] }}</h3>
                                    <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-white/5 dark:text-slate-400">{{ $incident['status'] }}</span>
                                </div>
                                <div class="mt-1 break-words text-sm font-bold text-slate-700 dark:text-slate-200">{{ $incident['subject'] }}</div>
                                <p class="mt-2 break-words text-sm text-slate-600 dark:text-slate-400">{{ $incident['detail'] }}</p>
                            </div>
                            <div class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 lg:text-right">
                                {{ $incident['occurred_at']?->diffForHumans() ?? 'Unknown time' }}
                                @if($incident['occurred_at'])
                                    <div class="mt-1">{{ $incident['occurred_at']->format('Y-m-d H:i') }}</div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
