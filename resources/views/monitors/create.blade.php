<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Add Monitor</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <div class="mt-6 rounded-lg glass p-6">
            <form method="POST" action="{{ route('monitors.store') }}" class="space-y-6">
                @csrf
                @if(! empty($prefillApplicationUrlId))
                    <input type="hidden" name="application_url_id" value="{{ $prefillApplicationUrlId }}">
                @endif

                <div class="space-y-2">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="w-full" value="{{ old('name', $prefillName ?? '') }}" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="space-y-2">
                    <x-input-label for="url" :value="__('URL')" />
                    <x-text-input id="url" name="url" type="url" class="w-full" value="{{ old('url', $prefillUrl ?? '') }}" placeholder="https://example.com" required autocomplete="url" />
                    <x-input-error :messages="$errors->get('url')" class="mt-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <x-input-label for="group" :value="__('Group')" />
                        <x-text-input id="group" name="group" type="text" class="w-full" value="{{ old('group') }}" placeholder="Production, Agency, Project" autocomplete="off" />
                        <x-input-error :messages="$errors->get('group')" class="mt-1" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="tags" :value="__('Tags')" />
                        <x-text-input id="tags" name="tags" type="text" class="w-full" value="{{ old('tags') }}" placeholder="public, critical, cms" autocomplete="off" />
                        <x-input-error :messages="$errors->get('tags')" class="mt-1" />
                    </div>
                </div>

                <div class="space-y-2">
                    <x-input-label for="interval" :value="__('Check interval in seconds')" />
                    <x-text-input id="interval" name="interval" type="number" class="w-full" value="{{ old('interval', 60) }}" min="30" max="86400" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('interval')" class="mt-1" />
                </div>

                <div class="space-y-2">
                    <x-input-label for="alert_emails" :value="__('Alert Emails (comma-separated)')" />
                    <x-text-input id="alert_emails" name="alert_emails" type="text" class="w-full" value="{{ old('alert_emails') }}" placeholder="admin@example.com, alerts@example.com" autocomplete="off" />
                    <x-input-error :messages="$errors->get('alert_emails')" class="mt-1" />
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Maintenance window</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Scheduled maintenance suppresses alert notifications and removes temporary downtime from incident history.</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-input-label for="maintenance_starts_at" :value="__('Starts at')" />
                            <x-text-input id="maintenance_starts_at" name="maintenance_starts_at" type="datetime-local" class="w-full" value="{{ old('maintenance_starts_at') }}" />
                            <x-input-error :messages="$errors->get('maintenance_starts_at')" class="mt-1" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="maintenance_ends_at" :value="__('Ends at')" />
                            <x-text-input id="maintenance_ends_at" name="maintenance_ends_at" type="datetime-local" class="w-full" value="{{ old('maintenance_ends_at') }}" />
                            <x-input-error :messages="$errors->get('maintenance_ends_at')" class="mt-1" />
                        </div>
                    </div>
                </div>

                @if(auth()->user()->hasRole('Super Admin'))
                    <div class="space-y-2">
                        <x-input-label for="user_id" :value="__('Assign to User (optional)')" />
                        <select id="user_id" name="user_id" class="w-full rounded border-slate-300 bg-white px-3 py-2 text-slate-900 focus:border-blue-500 focus:ring-blue-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                            <option value="">Assign to me</option>
                            @foreach(\App\Models\User::all() as $user)
                                <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->email }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('user_id')" class="mt-1" />
                    </div>
                @endif

                <div class="space-y-3">
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                        class="rounded border-slate-300 bg-white text-blue-500 focus:ring-blue-500 dark:border-white/10 dark:bg-white/5"
                            {{ old('is_active', true) ? 'checked' : '' }}
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-200">Start monitoring immediately</span>
                    </label>

                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="seo_enabled"
                            value="1"
                        class="rounded border-slate-300 bg-white text-blue-500 focus:ring-blue-500 dark:border-white/10 dark:bg-white/5"
                            {{ old('seo_enabled', true) ? 'checked' : '' }}
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-200">Enable SEO poisoning detection</span>
                    </label>
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center rounded border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/15 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                    >
                        Cancel
                    </a>

                    <x-primary-button>
                        Save Monitor
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
