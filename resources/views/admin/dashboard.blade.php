<x-app-layout>
    <x-slot name="header_title">Administration</x-slot>

    <div class="space-y-8">
        <!-- Stats Overview -->
        <section class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="glass rounded-2xl p-6">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">Total Users</div>
                <div class="mt-2 text-3xl font-bold">{{ $stats['users'] }}</div>
            </div>
            <div class="glass rounded-2xl p-6">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">Total Monitors</div>
                <div class="mt-2 text-3xl font-bold">{{ $stats['monitors'] }}</div>
            </div>
            <div class="glass rounded-2xl p-6">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-widest text-green-600 dark:text-green-400">Active</div>
                <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['active'] }}</div>
            </div>
            <div class="glass rounded-2xl p-6">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-widest text-red-600 dark:text-red-400">Alerts</div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['down'] + $stats['seo_alerts'] }}</div>
            </div>
        </section>

        <!-- User Management -->
        <section class="glass rounded-3xl overflow-hidden shadow-xl">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold">User Management</h3>
                    <p class="text-sm text-slate-500 mt-1">Manage system access, roles, and permissions.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <tr>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">User</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Monitors</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Role & Status</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach($users as $user)
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-8 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold uppercase">
                                            {{ substr($user->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-bold">{{ $user->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-4 font-medium">{{ $user->monitors_count }}</td>
                                <td class="px-8 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest {{ $user->hasRole('Super Admin') ? 'bg-orange-100 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-400' }}">
                                            {{ $user->hasRole('Super Admin') ? 'Super Admin' : 'User' }}
                                        </span>
                                        @if(!$user->is_approved)
                                            <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-yellow-100 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400">
                                                Pending
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-8 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if(!$user->is_approved)
                                            <form method="POST" action="{{ route('admin.users.approve', $user) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-green-500 text-white text-xs font-bold hover:bg-green-600 transition-colors shadow-sm" title="Approve User">
                                                    Approve
                                                </button>
                                            </form>
                                        @endif

                                        <a href="{{ route('admin.users.permissions', $user) }}" class="p-2 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10 transition-colors" title="Manage Permissions">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                        </a>

                                        @if($user->id !== auth()->id())
                                            <x-dropdown align="right" width="48">
                                                <x-slot name="trigger">
                                                    <button class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 transition-colors">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg>
                                                    </button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    <form method="POST" action="{{ route('admin.users.toggleAdmin', $user) }}">
                                                        @csrf
                                                        <x-dropdown-link :href="route('admin.users.toggleAdmin', $user)" onclick="event.preventDefault(); this.closest('form').submit();">
                                                            {{ $user->hasRole('Super Admin') ? 'Demote to User' : 'Promote to Admin' }}
                                                        </x-dropdown-link>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-dropdown-link :href="route('admin.users.destroy', $user)" onclick="event.preventDefault(); if(confirm('Delete this user?')) this.closest('form').submit();" class="!text-red-600 hover:!bg-red-50 dark:hover:!bg-red-500/10">
                                                            Delete User
                                                        </x-dropdown-link>
                                                    </form>
                                                </x-slot>
                                            </x-dropdown>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Monitor Management -->
        <section class="glass rounded-3xl overflow-hidden shadow-xl">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold">Platform Monitors</h3>
                    <p class="text-sm text-slate-500 mt-1">Overview of all monitoring agents.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <tr>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Monitor</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Owner</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach($monitors as $monitor)
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-8 py-4">
                                    <div class="font-bold">{{ $monitor->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $monitor->url }}</div>
                                </td>
                                <td class="px-8 py-4">
                                    <form method="POST" action="{{ route('admin.monitors.assign', $monitor) }}" class="inline">
                                        @csrf
                                        <select name="user_id" onchange="this.form.submit()" class="rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-4 py-1.5 text-xs font-medium focus:ring-2 focus:ring-orange-500 transition-all">
                                            <option value="">Unassigned</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ $monitor->user_id == $user->id ? 'selected' : '' }}>{{ $user->email }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-8 py-4">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest {{ $monitor->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-400' }}">
                                        {{ $monitor->is_active ? 'Active' : 'Paused' }}
                                    </span>
                                </td>
                                <td class="px-8 py-4 text-right space-x-2">
                                    <form method="POST" action="{{ route('admin.monitors.toggle', $monitor) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-4 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 text-xs font-bold hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                            {{ $monitor->is_active ? 'Pause' : 'Resume' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.monitors.destroy', $monitor) }}" class="inline" onsubmit="return confirm('Delete this monitor?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-4 py-1.5 rounded-lg border border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400 text-xs font-bold hover:bg-red-50 dark:hover:bg-red-500/5 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
