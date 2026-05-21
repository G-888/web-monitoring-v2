<?php

namespace App\Http\Controllers;

use App\Jobs\CheckServerPortBaseline;
use App\Models\ServerPortBaseline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerPortBaselineController extends Controller
{
    private const TEMPLATES = [
        'mysql_router' => [
            ['label' => 'MySQL Router read/write', 'port' => 6446, 'expected_state' => 'open'],
            ['label' => 'MySQL Router read-only', 'port' => 6447, 'expected_state' => 'open'],
        ],
        'mysql_db' => [
            ['label' => 'MySQL database', 'port' => 3306, 'expected_state' => 'open'],
        ],
    ];

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['required', 'in:tcp'],
            'expected_state' => ['required', 'in:open,closed'],
            'scan_target' => ['nullable', 'string', 'max:255'],
            'timeout_ms' => ['required', 'integer', 'min:200', 'max:30000'],
            'alert_cooldown_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);

        ServerPortBaseline::updateOrCreate(
            [
                'server_id' => $validated['server_id'],
                'protocol' => $validated['protocol'],
                'port' => $validated['port'],
            ],
            $validated
        );

        return back()->with('success', 'Server port baseline saved.');
    }

    public function check(ServerPortBaseline $serverPortBaseline): RedirectResponse
    {
        CheckServerPortBaseline::dispatch($serverPortBaseline, true);

        return back()->with('success', 'Port baseline check queued.');
    }

    public function applyTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
            'template' => ['required', 'in:mysql_router,mysql_db'],
            'scan_target' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        foreach (self::TEMPLATES[$validated['template']] as $port) {
            ServerPortBaseline::updateOrCreate(
                [
                    'server_id' => $validated['server_id'],
                    'protocol' => 'tcp',
                    'port' => $port['port'],
                ],
                [
                    'label' => $port['label'],
                    'expected_state' => $port['expected_state'],
                    'scan_target' => $validated['scan_target'] ?? 'localhost',
                    'timeout_ms' => 3000,
                    'alert_cooldown_seconds' => 900,
                    'is_active' => $request->boolean('is_active', false),
                ]
            );
        }

        return back()->with('success', 'Network port template applied.');
    }

    public function destroy(ServerPortBaseline $serverPortBaseline): RedirectResponse
    {
        $serverPortBaseline->delete();

        return back()->with('success', 'Server port baseline deleted.');
    }
}
