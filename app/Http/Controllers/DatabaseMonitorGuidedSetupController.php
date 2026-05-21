<?php

namespace App\Http\Controllers;

use App\Jobs\CheckDatabaseConnection;
use App\Models\DatabaseMonitor;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DatabaseMonitorGuidedSetupController extends Controller
{
    public function edit(Request $request, DatabaseMonitor $databaseMonitor): View
    {
        $databaseMonitor = $this->resolveDatabaseMonitor($request, $databaseMonitor);
        $databaseMonitor->load(['application.client', 'server', 'latestCheck']);

        return view('database-monitors.guided-setup', compact('databaseMonitor'));
    }

    public function update(Request $request, DatabaseMonitor $databaseMonitor, AuditLogger $auditLogger): RedirectResponse
    {
        $databaseMonitor = $this->resolveDatabaseMonitor($request, $databaseMonitor);

        $validated = $request->validate([
            'driver' => ['required', 'in:mysql,pgsql'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2000'],
            'default_query' => ['nullable', 'string', 'max:5000'],
            'db_role' => ['required', Rule::in(['primary', 'secondary', 'cluster_member', 'reporting'])],
        ]);

        if (($validated['password'] ?? '') === '') {
            unset($validated['password']);
        }

        $databaseMonitor->update([
            ...$validated,
            'configured_at' => now(),
        ]);

        $auditLogger->log('db_monitor_configured', $databaseMonitor, [
            'driver' => $databaseMonitor->driver,
            'host' => $databaseMonitor->host,
            'port' => $databaseMonitor->port,
            'application_id' => $databaseMonitor->application_id,
            'server_id' => $databaseMonitor->server_id,
        ], $request);

        return redirect()->route('database-monitors.guided-setup', $databaseMonitor)
            ->with('success', 'Database monitor configuration saved.');
    }

    public function test(Request $request, DatabaseMonitor $databaseMonitor): RedirectResponse
    {
        $databaseMonitor = $this->resolveDatabaseMonitor($request, $databaseMonitor);

        CheckDatabaseConnection::dispatch($databaseMonitor, true);

        return redirect()->route('database-monitors.guided-setup', $databaseMonitor)
            ->with('success', 'Database connection test queued.');
    }

    public function enable(Request $request, DatabaseMonitor $databaseMonitor, AuditLogger $auditLogger): RedirectResponse
    {
        $databaseMonitor = $this->resolveDatabaseMonitor($request, $databaseMonitor);

        $request->validate([
            'confirm_enable' => ['nullable', 'boolean'],
        ]);

        abort_unless(
            $databaseMonitor->last_status === 'up' || $request->boolean('confirm_enable'),
            422,
            'Run a successful test or confirm manual enable first.'
        );

        $databaseMonitor->forceFill([
            'is_active' => true,
            'enabled_at' => now(),
        ])->save();

        $auditLogger->log('db_monitor_enabled', $databaseMonitor, [
            'last_status' => $databaseMonitor->last_status,
            'confirmed' => $request->boolean('confirm_enable'),
        ], $request);

        return redirect()->route('database-monitors.guided-setup', $databaseMonitor)
            ->with('success', 'Database monitor enabled.');
    }

    private function resolveDatabaseMonitor(Request $request, DatabaseMonitor $databaseMonitor): DatabaseMonitor
    {
        if ($databaseMonitor->exists) {
            return $databaseMonitor;
        }

        return DatabaseMonitor::findOrFail($request->route('databaseMonitor'));
    }
}
