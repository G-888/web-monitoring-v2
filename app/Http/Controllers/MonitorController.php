<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Monitor;
use App\Models\CheckResult;
use App\Jobs\CheckWebsiteJob;

class MonitorController extends Controller
{
    public function index()
    {
        $query = Monitor::with(['latestResult', 'latestSeoResult']);

        if (!auth()->user()->hasRole('Super Admin')) {
            $query->where('user_id', auth()->id());
        }

        $monitors = $query->latest()->get();

        foreach ($monitors as $monitor) {
            $monitor->uptime_24h = $monitor->uptimePercentage(24);
            $monitor->uptime_7d  = $monitor->uptimePercentage(168);

            $monitor->recent_checks = CheckResult::where('monitor_id', $monitor->id)
                ->orderBy('checked_at', 'desc')
                ->take(20)
                ->get()
                ->reverse();
        }

        return view('dashboard', compact('monitors'));
    }

    public function create()
    {
        return view('monitors.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'interval' => 'nullable|integer|min:30|max:86400',
            'user_id' => 'nullable|exists:users,id',
            'alert_emails' => 'nullable|string',
        ]);

        $alertEmails = $request->alert_emails 
            ? array_map('trim', explode(',', $request->alert_emails)) 
            : [];

        $monitor = Monitor::create([
            'user_id' => (auth()->user()->hasRole('Super Admin') && $request->user_id) ? $request->user_id : auth()->id(),
            'name' => $request->name,
            'url' => $request->url,
            'interval' => $request->integer('interval', 60),
            'is_active' => $request->boolean('is_active', true),
            'seo_enabled' => $request->boolean('seo_enabled', true),
            'alert_emails' => $alertEmails,
        ]);

        CheckWebsiteJob::dispatch($monitor, true);

        return redirect()->route('dashboard')
            ->with('success', 'Monitor added successfully');
    }

    public function edit(Monitor $monitor)
    {
        $this->authorize('update', $monitor);

        return view('monitors.edit', compact('monitor'));
    }

    public function update(Request $request, Monitor $monitor)
    {
        $this->authorize('update', $monitor);

        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'interval' => 'required|integer|min:30|max:86400',
            'user_id' => 'nullable|exists:users,id',
            'alert_emails' => 'nullable|string',
        ]);

        $alertEmails = $request->alert_emails 
            ? array_map('trim', explode(',', $request->alert_emails)) 
            : [];

        $monitor->update([
            'name' => $request->name,
            'url' => $request->url,
            'interval' => $request->integer('interval'),
            'is_active' => $request->boolean('is_active'),
            'seo_enabled' => $request->boolean('seo_enabled'),
            'alert_emails' => $alertEmails,
            'user_id' => (auth()->user()->hasRole('Super Admin') && $request->user_id) ? $request->user_id : $monitor->user_id,
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Monitor updated successfully');
    }

    public function destroy(Monitor $monitor)
    {
        $this->authorize('delete', $monitor);

        $monitor->delete();

        return redirect()->route('dashboard')
            ->with('success', 'Monitor deleted successfully');
    }

    public function toggle(Monitor $monitor)
    {
        $this->authorize('toggle', $monitor);

        $monitor->update([
            'is_active' => ! $monitor->is_active,
        ]);

        return redirect()->route('dashboard')
            ->with('success', $monitor->is_active ? 'Monitor resumed' : 'Monitor paused');
    }

    public function check(Monitor $monitor)
    {
        $this->authorize('check', $monitor);

        CheckWebsiteJob::dispatch($monitor, true);

        return redirect()->route('dashboard')
            ->with('success', 'Monitor check started');
    }
}
