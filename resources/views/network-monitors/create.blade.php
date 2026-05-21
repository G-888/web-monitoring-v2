<x-app-layout>
    <x-slot name="header_title">Add Network Monitor</x-slot>

    <div class="mx-auto max-w-4xl rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <form method="POST" action="{{ route('network-monitors.store') }}">
            @include('network-monitors._form', ['submitLabel' => 'Save Network Monitor'])
        </form>
    </div>
</x-app-layout>
