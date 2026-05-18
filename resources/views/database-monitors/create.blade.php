<x-app-layout>
    <x-slot name="header_title">Add Database Monitor</x-slot>

    <div class="mx-auto max-w-3xl rounded-lg glass p-6">
        <form method="POST" action="{{ route('database-monitors.store') }}">
            @include('database-monitors._form', ['submitLabel' => 'Save Database Monitor'])
        </form>
    </div>
</x-app-layout>
