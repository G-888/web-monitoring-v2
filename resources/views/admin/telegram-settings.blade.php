<x-app-layout>
    <x-slot name="header_title">Telegram Settings</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="space-y-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Telegram Configuration</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Configure Telegram bot for receiving notifications and alerts.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.email-settings') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.email-settings*') ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
                    Email Settings
                </a>
                <a href="{{ route('admin.telegram-settings') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.telegram-settings*') ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
                    Telegram Settings
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <div class="flex items-center mb-2">
                    <svg class="w-5 h-5 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <strong>Please fix the following errors:</strong>
                </div>
                <ul class="list-disc list-inside ml-8">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Setup Instructions -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <div class="flex items-start">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100 mb-2">Setup Instructions</h3>
                    <ol class="text-sm text-blue-800 dark:text-blue-200 space-y-1 list-decimal list-inside">
                        <li>Message <a href="https://t.me/BotFather" target="_blank" class="underline hover:no-underline">@BotFather</a> on Telegram</li>
                        <li>Send <code class="bg-blue-100 dark:bg-blue-800 px-1 py-0.5 rounded text-xs">/newbot</code> and follow the instructions</li>
                        <li>Copy the bot token and paste it below</li>
                        <li>Start a chat with your bot and send any message</li>
                        <li>Visit <code class="bg-blue-100 dark:bg-blue-800 px-1 py-0.5 rounded text-xs">https://api.telegram.org/bot&lt;YOUR_BOT_TOKEN&gt;/getUpdates</code></li>
                        <li>Find your chat ID in the response and paste it below</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Settings Form -->
        <form method="POST" action="{{ route('admin.telegram-settings.update') }}" class="space-y-8">
            @csrf

            <!-- Bot Configuration -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Bot Configuration</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Configure your Telegram bot settings</p>
                </div>

                <div class="px-6 py-6 space-y-6">
                    <!-- Bot Token -->
                    <div>
                        <label for="bot_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Bot Token
                        </label>
                        <input type="text" name="bot_token" id="bot_token" value="{{ old('bot_token') }}"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                               placeholder="{{ $telegramSetting->bot_token ? 'Configured - leave blank to keep existing token' : '123456789:ABCdefGHIjklMNOpqrsTUVwxyz' }}">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $telegramSetting->bot_token ? 'Bot token is configured. Re-enter a token only when replacing it.' : 'Your Telegram bot token from @BotFather' }}
                        </p>
                    </div>

                    <!-- Chat ID -->
                    <div>
                        <label for="chat_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Chat ID
                        </label>
                        <input type="text" name="chat_id" id="chat_id" value="{{ old('chat_id', $telegramSetting->chat_id ?? '') }}"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                               placeholder="-123456789">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Your chat ID where notifications will be sent. If you see &quot;chat not found&quot;, make sure you have started a chat with the bot or added the bot to the target group/chat.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Activation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Activation</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Enable or disable Telegram notifications</p>
                </div>

                <div class="px-6 py-6">
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $telegramSetting->is_active ?? false) ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-3 block text-sm">
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Enable Telegram notifications</span>
                            <span class="text-gray-500 dark:text-gray-400 block">When enabled, the application will send notifications to your Telegram bot</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Save and Fetch Buttons -->
            <div class="space-y-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Use the buttons below to auto-detect the chat ID from Telegram updates or clear pending updates if you have stale results.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row justify-end">
                    <button type="submit" formaction="{{ route('admin.telegram-settings.fetchChatId') }}" formmethod="POST" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Fetch Chat ID
                    </button>

                    <button type="submit" formaction="{{ route('admin.telegram-settings.clearUpdates') }}" formmethod="POST" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Clear Updates
                    </button>

                    <button type="submit" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Settings
                    </button>
                </div>
            </div>
        </form>

        <!-- Test Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Test Configuration</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Send a test message to verify your bot configuration works correctly</p>
            </div>

            <form method="POST" action="{{ route('admin.telegram-settings.test') }}" class="px-6 py-6">
                @csrf

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Send a test message to your configured Telegram chat</p>
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        Send Test Message
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
