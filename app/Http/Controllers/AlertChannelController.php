<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\AlertChannel;
use Illuminate\Support\Facades\Auth;

class AlertChannelController extends Controller
{
    public function index()
    {
        $channels = Auth::user()->alertChannels()->get();
        return view('alert-channels.index', compact('channels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:slack,discord,email',
            'endpoint' => 'required|string|max:255',
        ]);

        Auth::user()->alertChannels()->create($request->only('type', 'endpoint'));

        return back()->with('success', 'Alert channel added successfully.');
    }

    public function destroy(AlertChannel $alertChannel)
    {
        if ($alertChannel->user_id !== Auth::id()) {
            abort(403);
        }

        $alertChannel->delete();

        return back()->with('success', 'Alert channel removed.');
    }
}
