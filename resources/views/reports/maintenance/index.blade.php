<x-app-layout>
    <x-slot name="header_title">Maintenance Reports</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Maintenance Reports</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Generate enterprise maintenance reports across applications, infrastructure, uptime, IIS, SSL, and security checks.</p>
            </div>
            <a href="{{ route('reports.maintenance.history') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                Report History
            </a>
        </div>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <form method="POST" action="{{ route('reports.maintenance.generate') }}" class="grid gap-4 lg:grid-cols-4">
                @csrf
                <div>
                    <x-input-label for="report_type" :value="__('Report type')" />
                    <select id="report_type" name="report_type" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                        @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'custom' => 'Custom'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('report_type', 'weekly') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="period_start" :value="__('Start date')" />
                    <x-text-input id="period_start" name="period_start" type="date" class="mt-1 w-full" :value="old('period_start', now()->subWeek()->toDateString())" />
                </div>

                <div>
                    <x-input-label for="period_end" :value="__('End date')" />
                    <x-text-input id="period_end" name="period_end" type="date" class="mt-1 w-full" :value="old('period_end', now()->toDateString())" />
                </div>

                <div>
                    <x-input-label for="output" :value="__('Output')" />
                    <select id="output" name="output" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                        <option value="html">HTML preview</option>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>

                <div>
                    <x-input-label for="client_id" :value="__('Client')" />
                    <select id="client_id" name="client_id" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All clients</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}{{ $client->environment ? ' / '.$client->environment : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="application_id" :value="__('Application')" />
                    <select id="application_id" name="application_id" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All applications</option>
                        @foreach($applications as $application)
                            <option value="{{ $application->id }}" @selected((string) old('application_id') === (string) $application->id)>{{ $application->name }}{{ $application->environment ? ' / '.$application->environment : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="server_group" :value="__('Server group')" />
                    <select id="server_group" name="server_group" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All groups</option>
                        @foreach($serverGroups as $group)
                            <option value="{{ $group }}" @selected(old('server_group') === $group)>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="environment" :value="__('Environment')" />
                    <select id="environment" name="environment" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-slate-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All environments</option>
                        @foreach($environments as $environment)
                            <option value="{{ $environment }}" @selected(old('environment') === $environment)>{{ $environment }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button class="inline-flex w-full items-center justify-center rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/10 transition hover:bg-orange-500">
                        Generate Report
                    </button>
                </div>
            </form>
            <x-input-error :messages="$errors->all()" class="mt-4" />
        </section>

        <section class="rounded-2xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <div class="border-b border-slate-200/70 p-5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Recent Reports</h3>
            </div>
            @include('reports.maintenance._history-table', ['reports' => $reports])
        </section>
    </div>
</x-app-layout>
