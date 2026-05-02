<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Registration Successful! However, your account is currently pending administrator approval. You will not be able to log in until a Super Admin approves your account.') }}
    </div>

    <div class="mt-4 flex items-center justify-between">
        <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
            {{ __('Back to login') }}
        </a>
    </div>
</x-guest-layout>
