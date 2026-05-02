<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">Welcome back</h1>
        <p class="mt-2 text-sm text-slate-400">Log in to manage monitors, checks, and alerts.</p>
    </div>

    <x-auth-session-status class="mb-4 text-sm text-green-300" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-200">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                class="mt-2 block w-full rounded border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400"
                required autofocus autocomplete="username" placeholder="you@example.com">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="block text-sm font-medium text-slate-200">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-300 hover:text-blue-200">Forgot password?</a>
                @endif
            </div>
            <input id="password" type="password" name="password"
                class="mt-2 block w-full rounded border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400"
                required autocomplete="current-password" placeholder="Your password">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2 text-sm text-slate-300">
            <input id="remember_me" type="checkbox" class="rounded border-white/10 bg-white/5 text-blue-600 focus:ring-blue-500" name="remember">
            Remember me
        </label>

        <button class="w-full rounded bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">
            Log In
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-400">
        New here?
        <a href="{{ route('register') }}" class="font-medium text-blue-300 hover:text-blue-200">Create an account</a>
    </p>
</x-guest-layout>
