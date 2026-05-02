@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-slate-200']) }}>
    {{ $value ?? $slot }}
</label>
