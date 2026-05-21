<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function index(): View
    {
        $monitors = Monitor::with('latestResult')->get();

        foreach ($monitors as $monitor) {
            $monitor->uptime_24h = $monitor->uptimePercentage(24);
        }

        return view('status', compact('monitors'));
    }
}
