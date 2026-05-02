@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-white/10 bg-white/5 text-slate-100 dark:bg-white/5 dark:text-slate-100 dark:border-white/10 focus:border-blue-400/70 focus:ring-blue-400/50 rounded-md shadow-sm']) }} />
