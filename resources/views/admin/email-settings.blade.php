<x-app-layout>
    <x-slot name="header_title">Email Settings</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="space-y-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Email Configuration</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Configure how the application sends emails for notifications and alerts.</p>
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

        <!-- Settings Form -->
        <form method="POST" action="{{ route('admin.email-settings.update') }}" class="space-y-8">
            @csrf

            <!-- General Settings -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">General Settings</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Basic email configuration</p>
                </div>

                <div class="px-6 py-6 space-y-6">
                    <!-- Mailer Type -->
                    <div>
                        <label for="mailer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Mail Driver
                        </label>
                        <select name="mailer" id="mailer" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm" required>
                            <option value="smtp" {{ ($emailSetting->mailer ?? 'smtp') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                            <option value="sendmail" {{ ($emailSetting->mailer ?? 'smtp') === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                            <option value="log" {{ ($emailSetting->mailer ?? 'smtp') === 'log' ? 'selected' : '' }}>Log (Development)</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose how emails should be sent</p>
                    </div>

                    <!-- From Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="from_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                From Email Address
                            </label>
                            <input type="email" name="from_address" id="from_address" value="{{ old('from_address', $emailSetting->from_address ?? '') }}" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Email address that appears as sender</p>
                        </div>

                        <div>
                            <label for="from_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                From Name
                            </label>
                            <input type="text" name="from_name" id="from_name" value="{{ old('from_name', $emailSetting->from_name ?? '') }}" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Name that appears as sender</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMTP Settings -->
            <div id="smtp-settings" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 {{ ($emailSetting->mailer ?? 'smtp') !== 'smtp' ? 'hidden' : '' }}">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">SMTP Configuration</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Configure your SMTP server settings</p>
                </div>

                <div class="px-6 py-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                SMTP Host
                            </label>
                            <input type="text" name="host" id="host" value="{{ old('host', $emailSetting->host ?? '') }}"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your SMTP server hostname</p>
                        </div>

                        <div>
                            <label for="port" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                SMTP Port
                            </label>
                            <input type="number" name="port" id="port" value="{{ old('port', $emailSetting->port ?? 587) }}"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Common ports: 587 (TLS), 465 (SSL), 25 (no encryption)</p>
                        </div>
                    </div>

                    <div>
                        <label for="encryption" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Encryption
                        </label>
                        <select name="encryption" id="encryption" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                            <option value="" {{ ($emailSetting->encryption ?? '') === '' ? 'selected' : '' }}>None</option>
                            <option value="tls" {{ ($emailSetting->encryption ?? '') === 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ ($emailSetting->encryption ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Encryption method for secure connection</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Username
                            </label>
                            <input type="text" name="username" id="username" value="{{ old('username', $emailSetting->username ?? '') }}"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">SMTP authentication username</p>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Password
                            </label>
                            <input type="password" name="password" id="password"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                                   placeholder="Leave empty to keep current password">
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">SMTP authentication password</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Activation</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Enable or disable custom email configuration</p>
                </div>

                <div class="px-6 py-6">
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $emailSetting->is_active ?? false) ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-3 block text-sm">
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Use these email settings</span>
                            <span class="text-gray-500 dark:text-gray-400 block">When enabled, the application will use these settings instead of environment variables</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Settings
                </button>
            </div>
        </form>

        <!-- Test Email Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Test Configuration</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Send a test email to verify your settings work correctly</p>
            </div>

            <form method="POST" action="{{ route('admin.email-settings.test') }}" class="px-6 py-6">
                @csrf

                <div class="max-w-md">
                    <label for="test_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Test Email Address
                    </label>
                    <div class="flex gap-4">
                        <input type="email" name="test_email" id="test_email" required
                               class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                               placeholder="your-email@example.com">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            Send Test
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">A test email will be sent to verify your configuration</p>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show/hide SMTP settings based on mailer type
        document.getElementById('mailer').addEventListener('change', function() {
            const smtpSettings = document.getElementById('smtp-settings');
            if (this.value === 'smtp') {
                smtpSettings.classList.remove('hidden');
            } else {
                smtpSettings.classList.add('hidden');
            }
        });
    </script>
</x-app-layout>