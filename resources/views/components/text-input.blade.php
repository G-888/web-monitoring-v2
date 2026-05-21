@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-md border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-blue-400/70 dark:focus:ring-blue-400/50']) }} />
