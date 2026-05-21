<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition duration-150 ease-in-out hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white disabled:opacity-25 dark:border-white/15 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10 dark:focus:ring-offset-slate-900']) }}>
    {{ $slot }}
</button>
