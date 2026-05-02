<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Add Monitor</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <div class="mt-6 rounded-lg glass p-6">
            <form method="POST" action="{{ route('monitors.store') }}" class="space-y-6">
                @csrf

                <div class="space-y-2">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="w-full" value="{{ old('name') }}" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="space-y-2">
                    <x-input-label for="url" :value="__('URL')" />
                    <x-text-input id="url" name="url" type="url" class="w-full" value="{{ old('url') }}" placeholder="https://example.com" required autocomplete="url" />
                    <x-input-error :messages="$errors->get('url')" class="mt-1" />
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

                @if(auth()->user()->hasRole('Super Admin'))
                    <div class="space-y-2">
                        <x-input-label for="user_id" :value="__('Assign to User (optional)')" />
                        <select id="user_id" name="user_id" class="w-full rounded border-white/10 bg-white/5 px-3 py-2 text-slate-200 focus:border-blue-500 focus:ring-blue-500">
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
                            class="rounded border-white/10 bg-white/5 text-blue-500 focus:ring-blue-500"
                            {{ old('is_active', true) ? 'checked' : '' }}
                        />
                        <span class="text-sm text-slate-200">Start monitoring immediately</span>
                    </label>

                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="seo_enabled"
                            value="1"
                            class="rounded border-white/10 bg-white/5 text-blue-500 focus:ring-blue-500"
                            {{ old('seo_enabled', true) ? 'checked' : '' }}
                        />
                        <span class="text-sm text-slate-200">Enable SEO poisoning detection</span>
                    </label>
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center rounded border border-white/15 bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10"
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
