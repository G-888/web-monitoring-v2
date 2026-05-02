<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Profile</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl">
        <div class="mt-6 rounded-lg glass p-6">
            <div class="space-y-6">
                <div>
                    @include('profile.partials.update-profile-information-form')
                </div>

                <div>
                    @include('profile.partials.update-password-form')
                </div>

                <div>
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
