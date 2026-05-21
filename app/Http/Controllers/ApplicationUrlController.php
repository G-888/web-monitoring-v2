<?php

namespace App\Http\Controllers;

use App\Models\ApplicationUrl;
use App\Models\Monitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationUrlController extends Controller
{
    public function linkMonitor(Request $request, ApplicationUrl $applicationUrl): RedirectResponse
    {
        $validated = $request->validate([
            'monitor_id' => ['required', 'integer', 'exists:monitors,id'],
        ]);

        $monitor = Monitor::findOrFail($validated['monitor_id']);

        $applicationUrl->forceFill([
            'monitor_id' => $validated['monitor_id'],
            'url' => $applicationUrl->url ?: $monitor->url,
            'status' => 'unknown',
        ])->save();

        return redirect()
            ->route('applications.show', $applicationUrl->application_id)
            ->with('success', 'Application URL linked to monitor.');
    }
}
