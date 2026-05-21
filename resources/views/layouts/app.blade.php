<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'WebMonitor') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>

<body
    x-data="{ sidebarOpen: false }"
    class="min-h-screen font-sans antialiased bg-slate-50 dark:bg-[#020617] text-slate-900 dark:text-slate-100 transition-colors duration-300"
>
    
    <div class="flex min-h-screen">
        <!-- Sidebar Overlay (Mobile) -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm md:hidden" x-cloak></div>

        <aside 
            :class="sidebarOpen ? 'flex' : 'hidden'"
            class="fixed inset-y-0 left-0 z-50 w-72 shrink-0 border-r border-slate-200 dark:border-white/10 bg-white dark:bg-[#020617] md:relative md:flex flex-col transition-all duration-300"
        >
            <div class="p-6 flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-orange-500 flex items-center justify-center text-white shadow-lg shadow-orange-500/20">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <span class="text-xl font-bold tracking-tight">WebMonitor</span>
            </div>

            <nav class="flex-1 px-4 space-y-8 overflow-y-auto pt-4">
                <!-- Monitoring Category -->
                <div class="space-y-1">
                    <div class="px-4 text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Monitoring</div>
                    <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('monitors.create') }}" class="sidebar-link {{ request()->routeIs('monitors.create') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add Monitor
                    </a>
                    <a href="{{ route('status') }}" class="sidebar-link {{ request()->routeIs('status') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Public Status
                    </a>
                </div>

                <!-- Analysis Category -->
                <div class="space-y-1">
                    <div class="px-4 text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Analysis</div>
                    <a href="{{ route('log-inspections.index') }}" class="sidebar-link {{ request()->routeIs('log-inspections.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Log Inspection
                    </a>
                    <a href="{{ route('incidents.index') }}" class="sidebar-link {{ request()->routeIs('incidents.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Incident History
                    </a>
                    @can('module.log_ingestion')
                        <a href="{{ route('iis-logs.index') }}" class="sidebar-link {{ request()->routeIs('iis-logs.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10"></path></svg>
                            IIS Logs
                        </a>
                    @endcan
                    <a href="{{ route('ssl-conversion.index') }}" class="sidebar-link {{ request()->routeIs('ssl-conversion.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        SSL Conversion
                    </a>
                    <a href="{{ route('ssl-monitors.index') }}" class="sidebar-link {{ request()->routeIs('ssl-monitors.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m1-6H8a2 2 0 00-2 2v5c0 5.25 3.438 8.063 6 9 2.563-.938 6-3.75 6-9V6a2 2 0 00-2-2z"></path></svg>
                        SSL Monitor
                    </a>
                    <a href="{{ route('seo-security.index') }}" class="sidebar-link {{ request()->routeIs('seo-security.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01"></path></svg>
                        SEO Security
                    </a>
                    <a href="{{ route('assets.index') }}" class="sidebar-link {{ request()->routeIs('assets.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Asset Intelligence
                    </a>
                    @can('module.application_mapping')
                        <a href="{{ route('clients.index') }}" class="sidebar-link {{ request()->routeIs('clients.*') || request()->routeIs('client-architecture.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8zm6 2a3 3 0 100-6 3 3 0 000 6z"></path></svg>
                            Clients
                        </a>
                        <a href="{{ route('applications.index') }}" class="sidebar-link {{ request()->routeIs('applications.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                            Applications
                        </a>
                    @endcan
                    @can('module.server_metrics')
                        <a href="{{ route('servers.index') }}" class="sidebar-link {{ request()->routeIs('servers.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Server Inventory
                        </a>
                        <a href="{{ route('agents.index') }}" class="sidebar-link {{ request()->routeIs('agents.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9h8m-8 4h6m-9 8h14a2 2 0 002-2V7.414a2 2 0 00-.586-1.414L17 2.586A2 2 0 0015.586 2H5a2 2 0 00-2 2v15a2 2 0 002 2z"></path></svg>
                            Agent Operations
                        </a>
                        <a href="{{ route('server-resources') }}" class="sidebar-link {{ request()->routeIs('server-resources') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            Server Resources
                        </a>
                    @endcan
                    @can('module.database_monitoring')
                        <a href="{{ route('database-monitors.index') }}" class="sidebar-link {{ request()->routeIs('database-monitors.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7c0 1.657 3.582 3 8 3s8-1.343 8-3-3.582-3-8-3-8 1.343-8 3zm0 0v5c0 1.657 3.582 3 8 3s8-1.343 8-3V7m-16 5v5c0 1.657 3.582 3 8 3s8-1.343 8-3v-5"></path></svg>
                            Database Monitors
                        </a>
                    @endcan
                    @can('module.network_monitoring')
                        <a href="{{ route('network-monitors.index') }}" class="sidebar-link {{ request()->routeIs('network-monitors.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9h8M8 15h8m-9 6h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            Network Monitors
                        </a>
                        <a href="{{ route('network-map.index') }}" class="sidebar-link {{ request()->routeIs('network-map.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h6v6H4zM14 4h6v6h-6zM14 14h6v6h-6zM10 10l4-3m-4 3l4 7"></path></svg>
                            Network Map
                        </a>
                    @endcan
                    @if(auth()->user()?->hasRole('Super Admin'))
                        <a href="{{ route('server-logs.index') }}" class="sidebar-link {{ request()->routeIs('server-logs.*') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            Log Scanner (rg)
                        </a>
                    @endif
                </div>

                <!-- Report Category -->
                @can('module.reports.view')
                    <div class="space-y-1">
                        <div class="px-4 text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Report</div>
                        <a href="{{ route('reports.maintenance.index') }}" class="sidebar-link {{ request()->routeIs('reports.maintenance.index') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m4 6V7m4 10v-4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H7L3 7v12a2 2 0 002 2z"></path></svg>
                            Maintenance Reports
                        </a>
                        <a href="{{ route('reports.maintenance.history') }}" class="sidebar-link {{ request()->routeIs('reports.maintenance.history') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Report History
                        </a>
                    </div>
                @endcan

                <!-- System Category -->
                <div class="space-y-1">
                    <div class="px-4 text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">System</div>
                    <a href="{{ route('profile.edit') }}" class="sidebar-link {{ request()->routeIs('profile.edit') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        My Profile
                    </a>
                    @if(auth()->user()?->hasRole('Super Admin'))
                        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Admin Panel
                            @php
                                $pendingUsers = \App\Models\User::where('is_approved', false)->count();
                            @endphp
                            @if($pendingUsers > 0)
                                <span class="ml-auto inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full">
                                    {{ $pendingUsers }}
                                </span>
                            @endif
                        </a>
                    @endif
                </div>
            </nav>

            <!-- Sidebar Footer / Profile -->
            <div class="p-4 border-t border-slate-200 dark:border-white/10">
                <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-50 dark:bg-white/5">
                    <div class="h-10 w-10 rounded-full bg-orange-100 dark:bg-orange-500/20 flex items-center justify-center text-orange-600 dark:text-orange-400 font-bold">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold truncate">{{ Auth::user()->name }}</div>
                        <div class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="p-1.5 text-slate-400 hover:text-red-500 transition-colors">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Header -->
            <header class="h-16 shrink-0 border-b border-slate-200 dark:border-white/10 bg-white dark:bg-[#020617]/50 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="md:hidden p-2 -ml-2 text-slate-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div class="text-sm font-medium text-slate-500 flex items-center">
                        <span class="hover:text-slate-900 dark:hover:text-white cursor-pointer">Pages</span>
                        <svg class="h-4 w-4 mx-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                        <span class="text-slate-900 dark:text-white font-semibold">{{ $header_title ?? 'Dashboard' }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Dark Mode Toggle -->
                    <button type="button" data-theme-toggle class="p-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 text-slate-500 hover:text-orange-500 transition-all duration-200 shadow-sm">
                        <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </button>
                    
                    <div class="h-8 w-px bg-slate-200 dark:bg-white/10 mx-1"></div>

                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 group">
                        <div class="h-9 w-9 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-600 dark:text-slate-400 group-hover:bg-orange-50 dark:group-hover:bg-orange-500/10 group-hover:text-orange-500 transition-all duration-200">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                    </a>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-7xl mx-auto space-y-6">
                    @if(isset($header))
                        {{ $header }}
                    @endif

                    @if(session('success'))
                        <div class="p-4 rounded-xl border border-green-200 dark:border-green-500/20 bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 text-sm font-medium">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script>
        document.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-theme-toggle]');
            if (!toggle) {
                return;
            }

            const isDark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', isDark);
            localStorage.setItem('darkMode', String(isDark));
        });

        mermaid.initialize({ startOnLoad: true, theme: 'dark' });
    </script>
</body>
</html>
