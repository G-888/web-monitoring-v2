<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Inspection: {{ $logInspection->original_filename }}</h2>
    </x-slot>

    <section class="space-y-6">
        <div class="glass rounded-lg p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-300">Source type: <span class="text-slate-100">{{ $logInspection->source_type ?? '-' }}</span></p>
                    <p class="text-sm text-slate-300">File size: <span class="text-slate-100">{{ number_format($logInspection->size_bytes / 1024, 2) }} KB</span></p>
                    <p class="text-sm text-slate-300">Inspected: <span class="text-slate-100">{{ $logInspection->inspected_at?->toDayDateTimeString() }}</span></p>
                </div>
                <a href="{{ route('log-inspections.index') }}" class="rounded border border-white/10 px-3 py-2 text-sm hover:bg-white/10">Back to log inspections</a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="glass rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Total lines</div>
                <div class="mt-2 text-2xl font-semibold">{{ number_format($logInspection->total_lines) }}</div>
            </div>
            <div class="glass rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Critical</div>
                <div class="mt-2 text-2xl font-semibold text-red-300">{{ $logInspection->critical_count }}</div>
            </div>
            <div class="glass rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Errors</div>
                <div class="mt-2 text-2xl font-semibold text-rose-300">{{ $logInspection->error_count }}</div>
            </div>
            <div class="glass rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Warnings</div>
                <div class="mt-2 text-2xl font-semibold text-amber-300">{{ $logInspection->warning_count }}</div>
            </div>
            <div class="glass rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Info</div>
                <div class="mt-2 text-2xl font-semibold text-blue-300">{{ $logInspection->info_count }}</div>
            </div>
        </div>

        <div class="glass rounded-lg p-6">
            <h3 class="text-lg font-semibold">Key Findings</h3>

            @php
                $highlights = $logInspection->highlights ?? [];
            @endphp

            @if(empty($highlights))
                <p class="mt-3 text-sm text-slate-300">No warning/error/critical indicators were detected.</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach($highlights as $item)
                        @php
                            $level = $item['level'] ?? 'info';
                            $levelColor = match($level) {
                                'critical' => 'text-red-300 bg-red-500/10 border-red-500/20',
                                'error' => 'text-rose-300 bg-rose-500/10 border-rose-500/20',
                                'warning' => 'text-amber-300 bg-amber-500/10 border-amber-500/20',
                                default => 'text-blue-300 bg-blue-500/10 border-blue-500/20',
                            };
                        @endphp
                        <div class="rounded border {{ $levelColor }} p-3">
                            <div class="text-xs uppercase tracking-wide">{{ $level }}{{ isset($item['line']) ? ' on line '.$item['line'] : '' }}</div>
                            <div class="mt-1 break-words font-mono text-xs text-slate-100">{{ $item['text'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="glass rounded-lg p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-semibold">AI Inspection</h3>
                <form method="POST" action="{{ route('log-inspections.ai-analyze', $logInspection) }}">
                    @csrf
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            name="provider"
                            class="rounded border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 focus:border-violet-500 focus:ring-violet-500"
                        >
                            @foreach($providers as $providerKey => $providerLabel)
                                <option value="{{ $providerKey }}" @selected(($logInspection->ai_provider ?? config('services.log_ai.default_provider')) === $providerKey)>
                                    {{ $providerLabel }}
                                </option>
                            @endforeach
                        </select>
                        <label class="inline-flex items-center gap-2 rounded border border-white/10 px-3 py-2 text-xs text-slate-200">
                            <input
                                type="checkbox"
                                name="auto_fallback"
                                value="1"
                                class="rounded border-white/10 bg-white/5 text-violet-500 focus:ring-violet-500"
                                {{ config('services.log_ai.fallback_enabled', true) ? 'checked' : '' }}
                            />
                            Auto fallback
                        </label>
                        <button class="rounded bg-violet-600 px-3 py-2 text-sm font-medium hover:bg-violet-500">Analyze with AI</button>
                    </div>
                </form>
            </div>

            <div class="mt-3 text-sm text-slate-300">
                <p>Status: <span class="text-slate-100">{{ str_replace('_', ' ', $logInspection->ai_status ?? 'not_requested') }}</span></p>
                @if($logInspection->ai_provider)
                    <p>Provider: <span class="text-slate-100">{{ strtoupper($logInspection->ai_provider) }}</span></p>
                @endif
                @if($logInspection->ai_model)
                    <p>Model: <span class="text-slate-100">{{ $logInspection->ai_model }}</span></p>
                @endif
                @if($logInspection->ai_analyzed_at)
                    <p>Analyzed: <span class="text-slate-100">{{ $logInspection->ai_analyzed_at->toDayDateTimeString() }}</span></p>
                @endif
            </div>

            @if($logInspection->ai_summary)
                <div class="mt-4 rounded border border-violet-500/20 bg-violet-500/10 p-3">
                    <div class="text-xs uppercase tracking-wide text-violet-200">AI Summary</div>
                    <p class="mt-1 text-sm text-slate-100">{{ $logInspection->ai_summary }}</p>
                </div>
            @endif

            @php
                $aiFindings = $logInspection->ai_findings ?? [];
            @endphp

            @if(! empty($aiFindings))
                <div class="mt-4 space-y-3">
                    @foreach($aiFindings as $finding)
                        <div class="rounded border border-white/10 bg-white/5 p-3">
                            <div class="text-xs uppercase tracking-wide text-slate-300">
                                {{ $finding['severity'] ?? 'unknown' }} · {{ $finding['category'] ?? 'general' }}
                            </div>
                            <p class="mt-1 text-sm text-slate-100">{{ $finding['detail'] ?? '' }}</p>
                            @if(! empty($finding['recommendation']))
                                <p class="mt-2 text-xs text-emerald-200">Recommendation: {{ $finding['recommendation'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="glass rounded-lg p-6">
            <h3 class="text-lg font-semibold">Manual Log Content Inspection</h3>
            <p class="mt-2 text-sm text-slate-300">Showing lines {{ $preview['start_line'] }}-{{ $preview['end_line'] }}.</p>

            <div class="mt-4 max-h-[32rem] overflow-auto rounded border border-white/10 bg-slate-950/60">
                @if(empty($preview['lines']))
                    <p class="p-4 text-sm text-slate-300">No preview content available.</p>
                @else
                    <table class="min-w-full text-xs">
                        <tbody>
                            @foreach($preview['lines'] as $line)
                                <tr class="border-b border-white/5 align-top">
                                    <td class="w-20 px-3 py-1 text-right text-slate-500">{{ $line['number'] }}</td>
                                    <td class="px-3 py-1 font-mono text-slate-100 whitespace-pre-wrap break-words">{{ $line['text'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @php
                    $prevStart = max($preview['start_line'] - 400, 1);
                    $nextStart = $preview['end_line'] + 1;
                @endphp

                <a href="{{ route('log-inspections.show', ['logInspection' => $logInspection, 'start_line' => $prevStart]) }}" class="rounded border border-white/10 px-3 py-2 text-sm hover:bg-white/10">
                    Previous chunk
                </a>

                @if($preview['has_next'])
                    <a href="{{ route('log-inspections.show', ['logInspection' => $logInspection, 'start_line' => $nextStart]) }}" class="rounded border border-white/10 px-3 py-2 text-sm hover:bg-white/10">
                        Next chunk
                    </a>
                @endif
            </div>
        </div>
    </section>
</x-app-layout>
