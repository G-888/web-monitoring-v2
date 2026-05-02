{{-- Archived legacy duplicate of monitor create view.
     Kept for reference only; active create view is resources/views/monitors/create.blade.php. --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Add Monitor</h2>
    </x-slot>

    <div class="p-6 max-w-xl mx-auto">
        <form method="POST" action="{{ route('monitors.store') }}" class="bg-white p-6 rounded shadow">
            @csrf

            <div class="mb-4">
                <label>Name</label>
                <input type="text" name="name" class="w-full border p-2 rounded" required>
            </div>

            <div class="mb-4">
                <label>URL</label>
                <input type="url" name="url" class="w-full border p-2 rounded" required>
            </div>

            <button class="bg-blue-600 text-white px-4 py-2 rounded">
                Save
            </button>
        </form>
    </div>
</x-app-layout>
