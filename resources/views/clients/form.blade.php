<x-app-layout>
    <x-slot name="header_title">Client</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $client->exists ? 'Edit Client' : 'Add Client' }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage client ownership and support context.</p>
        </div>

        <form method="POST" action="{{ $action }}" class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            @csrf
            @if($method !== 'POST')
                @method($method)
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-input-label for="name" :value="__('Client name')" />
                    <x-text-input id="name" name="name" class="mt-1 w-full" :value="old('name', $client->name)" required />
                </div>
                <div>
                    <x-input-label for="code" :value="__('Code')" />
                    <x-text-input id="code" name="code" class="mt-1 w-full" :value="old('code', $client->code)" required />
                </div>
                <div>
                    <x-input-label for="environment" :value="__('Environment')" />
                    <x-text-input id="environment" name="environment" class="mt-1 w-full" :value="old('environment', $client->environment)" />
                </div>
                <div>
                    <x-input-label for="support_team" :value="__('Support team')" />
                    <x-text-input id="support_team" name="support_team" class="mt-1 w-full" :value="old('support_team', $client->support_team)" />
                </div>
                <div>
                    <x-input-label for="contact_name" :value="__('Contact name')" />
                    <x-text-input id="contact_name" name="contact_name" class="mt-1 w-full" :value="old('contact_name', $client->contact_name)" />
                </div>
                <div>
                    <x-input-label for="contact_email" :value="__('Contact email')" />
                    <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 w-full" :value="old('contact_email', $client->contact_email)" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <x-text-input id="status" name="status" class="mt-1 w-full" :value="old('status', $client->status ?? 'active')" />
                </div>
            </div>

            <x-input-error :messages="$errors->all()" class="mt-4" />

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('clients.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Cancel</a>
                <button class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">Save Client</button>
            </div>
        </form>
    </div>
</x-app-layout>
