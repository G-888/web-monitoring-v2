<x-app-layout>
    <x-slot name="header_title">Edit Database Monitor</x-slot>

    <div class="mx-auto max-w-3xl rounded-lg glass p-6">
        <form method="POST" action="{{ route('database-monitors.update', $databaseMonitor) }}">
            @method('PATCH')
            @include('database-monitors._form', ['submitLabel' => 'Update Database Monitor'])
        </form>
    </div>
</x-app-layout>
