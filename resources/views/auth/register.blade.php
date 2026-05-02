<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">Create your account</h1>
        <p class="mt-2 text-sm text-slate-400">Start monitoring websites and receiving outage alerts.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-200">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}"
                class="mt-2 block w-full rounded border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400"
                required autofocus autocomplete="name" placeholder="Your name">
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-200">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                class="mt-2 block w-full rounded border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400"
                required autocomplete="username" placeholder="you@example.com">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-200">Password</label>
            <input id="password" type="password" name="password"
                class="mt-2 block w-full rounded border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400"
                required autocomplete="new-password" placeholder="At least 8 characters">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-200">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                class="mt-2 block w-full rounded border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400"
                required autocomplete="new-password" placeholder="Repeat password">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <button class="w-full rounded bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">
            Register
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-400">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-blue-300 hover:text-blue-200">Log in</a>
    </p>
</x-guest-layout>
