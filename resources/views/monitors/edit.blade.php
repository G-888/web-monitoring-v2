<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Edit Monitor</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl p-6">
        <form method="POST" action="{{ route('monitors.update', $monitor) }}" class="space-y-6 rounded-lg bg-white p-6 shadow">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-medium text-gray-700" for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $monitor->name) }}"
                    class="mt-1 w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" for="url">URL</label>
                <input id="url" type="url" name="url" value="{{ old('url', $monitor->url) }}"
                    class="mt-1 w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required>
                <x-input-error :messages="$errors->get('url')" class="mt-2" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" for="interval">Check interval in seconds</label>
                <input id="interval" type="number" name="interval" value="{{ old('interval', $monitor->interval) }}" min="30" max="86400"
                    class="mt-1 w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required>
                <x-input-error :messages="$errors->get('interval')" class="mt-2" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" for="alert_emails">Alert Emails (comma-separated)</label>
                <input id="alert_emails" type="text" name="alert_emails" value="{{ old('alert_emails', is_array($monitor->alert_emails) ? implode(', ', $monitor->alert_emails) : '') }}" placeholder="admin@example.com, alerts@example.com"
                    class="mt-1 w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <x-input-error :messages="$errors->get('alert_emails')" class="mt-2" />
            </div>

            @if(auth()->user()->hasRole('Super Admin'))
                <div>
                    <label class="block text-sm font-medium text-gray-700" for="user_id">Assign to User</label>
                    <select id="user_id" name="user_id" class="mt-1 w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Unassigned</option>
                        @foreach(\App\Models\User::all() as $user)
                            <option value="{{ $user->id }}" {{ old('user_id', $monitor->user_id) == $user->id ? 'selected' : '' }}>
                                {{ $user->email }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                </div>
            @endif

            <div class="space-y-3">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('is_active', $monitor->is_active))>
                    <span class="text-sm text-gray-700">Monitor is active</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="seo_enabled" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('seo_enabled', $monitor->seo_enabled))>
                    <span class="text-sm text-gray-700">SEO poisoning detection enabled</span>
                </label>
            </div>

            <div class="flex justify-between">
                <a href="{{ route('dashboard') }}" class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-800 hover:bg-gray-300">Cancel</a>
                <button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">Update Monitor</button>
            </div>
        </form>
    </div>
</x-app-layout>
