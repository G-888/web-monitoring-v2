<?php

namespace App\Http\Controllers;

use App\Jobs\CheckDatabaseConnection;
use App\Models\DatabaseMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DatabaseMonitorController extends Controller
{
    public function index(): View
    {
        $databaseMonitors = DatabaseMonitor::with('latestCheck')
            ->latest()
            ->get();

        return view('database-monitors.index', compact('databaseMonitors'));
    }

    public function create(): View
    {
        return view('database-monitors.create', [
            'databaseMonitor' => new DatabaseMonitor([
                'driver' => 'mysql',
                'port' => 3306,
                'is_active' => true,
                'alert_cooldown_seconds' => 900,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateInput($request);

        $monitor = DatabaseMonitor::create($validated);
        CheckDatabaseConnection::dispatch($monitor, true);

        return redirect()->route('database-monitors.index')
            ->with('success', 'Database monitor created and test queued.');
    }

    public function edit(DatabaseMonitor $databaseMonitor): View
    {
        return view('database-monitors.edit', compact('databaseMonitor'));
    }

    public function update(Request $request, DatabaseMonitor $databaseMonitor): RedirectResponse
    {
        $validated = $this->validateInput($request, $databaseMonitor);

        if (($validated['password'] ?? null) === null) {
            unset($validated['password']);
        }

        $databaseMonitor->update($validated);

        return redirect()->route('database-monitors.index')
            ->with('success', 'Database monitor updated.');
    }

    public function destroy(DatabaseMonitor $databaseMonitor): RedirectResponse
    {
        $databaseMonitor->delete();

        return redirect()->route('database-monitors.index')
            ->with('success', 'Database monitor deleted.');
    }

    public function test(DatabaseMonitor $databaseMonitor): RedirectResponse
    {
        CheckDatabaseConnection::dispatch($databaseMonitor, true);

        return back()->with('success', 'Database connection test queued.');
    }

    private function validateInput(Request $request, ?DatabaseMonitor $databaseMonitor = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'in:mysql,pgsql'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$databaseMonitor ? 'nullable' : 'required', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'alert_cooldown_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
        ];

        $validated = $request->validate($rules);
        $validated['is_active'] = $request->boolean('is_active', true);

        if (($validated['password'] ?? '') === '') {
            $validated['password'] = null;
        }

        return $validated;
    }
}
