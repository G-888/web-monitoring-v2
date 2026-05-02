<x-app-layout>
    <x-slot name="header_title">Manage Permissions</x-slot>

    <div class="space-y-8 max-w-7xl mx-auto">
        <section class="glass rounded-3xl overflow-hidden shadow-xl">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-white/10 flex items-center justify-between bg-slate-50/50 dark:bg-white/5">
                <div>
                    <h3 class="text-xl font-bold flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold uppercase text-lg shadow-sm">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                        {{ $user->name }}'s Permissions
                    </h3>
                    <p class="text-sm text-slate-500 mt-1 ml-13">Configure modular access and capabilities for this user.</p>
                </div>
                <div>
                    <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
                        Back to Admin Panel
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="px-8 mt-6">
                    <div class="p-4 rounded-xl bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400 font-medium text-sm flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.permissions.update', $user) }}" class="p-8">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($permissions as $category => $perms)
                        <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900/50 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                            <div class="px-6 py-4 border-b border-slate-100 dark:border-white/5 bg-slate-50 dark:bg-white/5 flex items-center gap-2">
                                @if($category === 'Modules & Features')
                                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                @elseif($category === 'Monitors')
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                @elseif($category === 'Logs & Analysis')
                                    <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                @else
                                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                @endif
                                <h4 class="font-bold text-sm tracking-wide uppercase text-slate-700 dark:text-slate-300">{{ $category }}</h4>
                            </div>
                            <div class="p-4 space-y-3">
                                @foreach($perms as $permission)
                                    <label class="flex items-start gap-3 cursor-pointer group">
                                        <div class="relative flex items-center justify-center mt-0.5">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" 
                                                   class="peer sr-only"
                                                   {{ $user->hasDirectPermission($permission->name) ? 'checked' : '' }}>
                                            <div class="w-5 h-5 rounded-md border-2 border-slate-300 dark:border-slate-600 peer-checked:bg-orange-500 peer-checked:border-orange-500 peer-focus:ring-2 peer-focus:ring-orange-500/30 transition-all flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-slate-700 dark:text-slate-200 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">
                                                {{ str_replace('module.', '', str_replace('_', ' ', Str::title($permission->name))) }}
                                            </div>
                                            <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                                                <code>{{ $permission->name }}</code>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 flex justify-end gap-4 border-t border-slate-200 dark:border-white/10 pt-6">
                    <button type="button" onclick="document.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = true)" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                        Select All
                    </button>
                    <button type="button" onclick="document.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false)" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                        Deselect All
                    </button>
                    <button type="submit" class="px-8 py-2.5 rounded-xl bg-orange-500 text-white text-sm font-bold shadow-lg shadow-orange-500/30 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-0.5 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                        Save Permissions
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
