<?php

namespace App\Http\Controllers;

use App\Jobs\CheckWebsiteJob;
use App\Models\Monitor;
use App\Models\User;
use App\Models\EmailSetting;
use App\Models\TelegramSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;
use App\Services\TelegramService;

class AdminController extends Controller
{
    public function index(): View
    {
        $users = User::withCount('monitors')->latest()->get();
        $monitors = Monitor::with(['user', 'latestResult', 'latestSeoResult'])->latest()->get();

        $stats = [
            'users' => $users->count(),
            'monitors' => $monitors->count(),
            'active' => $monitors->where('is_active', true)->count(),
            'down' => $monitors->filter(fn ($monitor) => $monitor->latestResult && ! $monitor->latestResult->is_up)->count(),
            'seo_alerts' => $monitors->filter(fn ($monitor) => $monitor->latestSeoResult?->is_suspicious)->count(),
        ];

        $modules = \Spatie\Permission\Models\Permission::where('name', 'like', 'module.%')->get();

        return view('admin.dashboard', compact('users', 'monitors', 'stats', 'modules'));
    }

    public function toggleMonitor(Monitor $monitor): RedirectResponse
    {
        $this->authorize('toggle', $monitor);

        $monitor->update(['is_active' => ! $monitor->is_active]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Monitor status updated successfully.');
    }

    public function checkMonitor(Monitor $monitor): RedirectResponse
    {
        $this->authorize('check', $monitor);

        CheckWebsiteJob::dispatch($monitor, true);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Monitor check has been queued.');
    }

    public function assignMonitor(Request $request, Monitor $monitor): RedirectResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $monitor->update(['user_id' => $request->user_id]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Monitor assigned successfully.');
    }

    public function destroyMonitor(Monitor $monitor): RedirectResponse
    {
        $this->authorize('delete', $monitor);

        $monitor->delete();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Monitor deleted successfully.');
    }

    public function toggleUserAdmin(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.dashboard')
                ->with('success', 'You cannot modify your own admin status from the admin panel.');
        }

        if ($user->hasRole('Super Admin')) {
            $user->removeRole('Super Admin');
            $user->assignRole('Viewer');
        } else {
            $user->syncRoles(['Super Admin']);
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'User admin status updated successfully.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.dashboard')
                ->with('success', 'You cannot delete your own account from the admin panel.');
        }

        $user->delete();

        return redirect()->route('admin.dashboard')
            ->with('success', 'User deleted successfully.');
    }

    public function approveUser(User $user): RedirectResponse
    {
        $user->update(['is_approved' => true]);
        
        return back()->with('success', 'User approved successfully.');
    }

    public function editPermissions(User $user): View
    {
        $permissions = \Spatie\Permission\Models\Permission::all()->groupBy(function($perm) {
            if (str_starts_with($perm->name, 'module.')) return 'Modules & Features';
            if (str_contains($perm->name, 'monitor')) return 'Monitors';
            if (str_contains($perm->name, 'log')) return 'Logs & Analysis';
            return 'General & System';
        });

        return view('admin.permissions', compact('user', 'permissions'));
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user->syncPermissions($request->permissions ?? []);

        return back()->with('success', 'User permissions updated successfully.');
    }

    public function emailSettings(): View
    {
        $emailSetting = EmailSetting::first();

        if (!$emailSetting) {
            $emailSetting = new EmailSetting([
                'mailer' => 'smtp',
                'port' => 587,
                'encryption' => 'tls',
                'is_active' => false,
            ]);
        }

        return view('admin.email-settings', compact('emailSetting'));
    }

    public function updateEmailSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'mailer' => 'required|in:smtp,sendmail,log',
            'host' => 'required_if:mailer,smtp|nullable|string',
            'port' => 'required_if:mailer,smtp|nullable|integer|min:1|max:65535',
            'encryption' => 'nullable|in:tls,ssl',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'from_address' => 'required|email',
            'from_name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $emailSetting = EmailSetting::first();

        if (!$emailSetting) {
            $emailSetting = new EmailSetting();
        }

        $emailSetting->fill($request->only([
            'mailer', 'host', 'port', 'encryption', 'username', 'password',
            'from_address', 'from_name', 'is_active'
        ]));

        $emailSetting->save();

        return back()->with('success', 'Email settings updated successfully.');
    }

    public function testEmailSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            $emailSetting = EmailSetting::getActive();

            if (!$emailSetting) {
                return back()->with('error', 'No active email settings found. Please configure and activate email settings first.');
            }

            // Temporarily update mail config
            config(['mail' => array_merge(config('mail'), $emailSetting->toMailConfig())]);

            Mail::raw('This is a test email from WebMonitor. If you received this, your email configuration is working correctly!', function ($message) use ($request) {
                $message->to($request->test_email)
                        ->subject('WebMonitor Email Test');
            });

            return back()->with('success', 'Test email sent successfully! Check your inbox.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }

    public function telegramSettings(): View
    {
        $telegramSetting = TelegramSetting::first();

        if (!$telegramSetting) {
            $telegramSetting = new TelegramSetting([
                'is_active' => false,
            ]);
        }

        return view('admin.telegram-settings', compact('telegramSetting'));
    }

    public function updateTelegramSettings(Request $request): RedirectResponse
    {
        $request->merge([
            'bot_token' => $request->input('bot_token') ? trim($request->input('bot_token')) : null,
            'chat_id' => $request->input('chat_id') ? trim($request->input('chat_id')) : null,
        ]);

        $request->validate([
            'bot_token' => 'nullable|string|max:255',
            'chat_id' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $telegramSetting = TelegramSetting::first();

        if (!$telegramSetting) {
            $telegramSetting = new TelegramSetting();
        }

        $telegramSetting->fill($request->only(['bot_token', 'chat_id', 'is_active']));
        $telegramSetting->save();

        return back()->with('success', 'Telegram settings updated successfully.');
    }
    public function fetchTelegramChatId(Request $request): RedirectResponse
    {
        $request->merge([
            'bot_token' => $request->input('bot_token') ? trim($request->input('bot_token')) : null,
        ]);

        $request->validate([
            'bot_token' => 'nullable|string|max:255',
        ]);

        $telegramSetting = TelegramSetting::first() ?? new TelegramSetting();
        if ($request->bot_token) {
            $telegramSetting->bot_token = $request->bot_token;
        }

        $telegramService = new TelegramService($telegramSetting);
        $result = $telegramService->fetchChatIdFromUpdates();

        if ($result['success']) {
            return back()
                ->with('success', $result['message'])
                ->withInput(['bot_token' => $request->bot_token, 'chat_id' => $result['chat_id']]);
        }

        return back()->with('error', $result['message'])->withInput(['bot_token' => $request->bot_token]);
    }

    public function clearTelegramUpdates(Request $request): RedirectResponse
    {
        $request->merge([
            'bot_token' => $request->input('bot_token') ? trim($request->input('bot_token')) : null,
        ]);

        $request->validate([
            'bot_token' => 'nullable|string|max:255',
        ]);

        $telegramSetting = TelegramSetting::first() ?? new TelegramSetting();
        if ($request->bot_token) {
            $telegramSetting->bot_token = $request->bot_token;
        }

        $telegramService = new TelegramService($telegramSetting);
        $result = $telegramService->clearFetchedUpdates();

        return back()
            ->with($result['success'] ? 'success' : 'error', $result['message'])
            ->withInput(['bot_token' => $request->bot_token, 'chat_id' => old('chat_id')]);
    }

    public function testTelegramSettings(): RedirectResponse
    {
        $telegramService = new TelegramService();
        $result = $telegramService->testConnection();

        if ($result['success']) {
            return back()->with('success', $result['message']);
        } else {
            return back()->with('error', $result['message']);
        }
    }
}
