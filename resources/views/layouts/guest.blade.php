<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Web Monitor') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            background:
                radial-gradient(800px 420px at 12% -8%, rgba(34, 197, 94, .22), transparent 58%),
                radial-gradient(900px 520px at 100% 0%, rgba(37, 99, 235, .24), transparent 58%),
                #020617;
        }
    </style>
</head>
<body class="font-sans text-slate-100 antialiased">
    <main class="grid min-h-screen lg:grid-cols-[1fr_520px]">
        <section class="hidden border-r border-white/10 p-10 lg:flex lg:flex-col lg:justify-between">
            <a href="{{ route('login') }}" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded bg-blue-600 text-lg font-bold">M</span>
                <span class="text-lg font-semibold">Web Monitor</span>
            </a>

            <div class="max-w-xl">
                <p class="text-sm font-medium uppercase tracking-wide text-blue-300">Uptime and SEO watch</p>
                <h1 class="mt-4 text-5xl font-semibold leading-tight">Know when your websites go down before users do.</h1>
                <p class="mt-5 text-base leading-7 text-slate-300">
                    Track uptime, response time, status codes, and suspicious content changes from one dashboard.
                </p>

                <div class="mt-8 grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded border border-white/10 bg-white/5 p-4">
                        <div class="text-2xl font-semibold">24h</div>
                        <div class="mt-1 text-slate-400">uptime view</div>
                    </div>
                    <div class="rounded border border-white/10 bg-white/5 p-4">
                        <div class="text-2xl font-semibold">SEO</div>
                        <div class="mt-1 text-slate-400">alerts</div>
                    </div>
                    <div class="rounded border border-white/10 bg-white/5 p-4">
                        <div class="text-2xl font-semibold">Live</div>
                        <div class="mt-1 text-slate-400">updates</div>
                    </div>
                </div>
            </div>

            <a href="{{ route('status') }}" class="text-sm text-slate-400 hover:text-blue-300">View public status page</a>
        </section>

        <section class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <a href="{{ route('login') }}" class="flex items-center justify-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded bg-blue-600 text-lg font-bold">M</span>
                        <span class="text-lg font-semibold">Web Monitor</span>
                    </a>
                </div>

                <div class="rounded-lg border border-white/10 bg-slate-950/75 p-6 shadow-2xl backdrop-blur">
                    {{ $slot }}
                </div>
            </div>
        </section>
    </main>
</body>
</html>
