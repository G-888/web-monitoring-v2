@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-200 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li class="rounded border border-red-400/20 bg-red-500/10 px-2 py-1">{{ $message }}</li>
        @endforeach
    </ul>
@endif
