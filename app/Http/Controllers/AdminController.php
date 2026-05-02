<?php

namespace App\Http\Controllers;

use App\Jobs\CheckWebsiteJob;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
