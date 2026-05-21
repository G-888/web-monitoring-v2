@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-sm text-red-700 dark:text-red-200']) }}>
        @foreach ((array) $messages as $message)
            <li class="rounded border border-red-200 bg-red-50 px-2 py-1 dark:border-red-400/20 dark:bg-red-500/10">{{ $message }}</li>
        @endforeach
    </ul>
@endif
