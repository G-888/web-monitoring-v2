<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white/5 dark:bg-white/5 border border-white/15 rounded-md font-semibold text-xs text-slate-200 uppercase tracking-widest shadow-sm hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
