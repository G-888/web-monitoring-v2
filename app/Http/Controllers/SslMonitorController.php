<?php

namespace App\Http\Controllers;

use App\Jobs\CheckWebsiteJob;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SslMonitorController extends Controller
{
    public function index()
    {
        $monitors = $this->sslMonitorQuery()
            ->with(['latestResult', 'user'])
            ->get()
            ->sortBy(fn (Monitor $monitor) => $monitor->ssl_expires_at?->timestamp ?? PHP_INT_MAX)
            ->values();

        $summary = [
            'total' => $monitors->count(),
            'valid' => $monitors->filter(fn (Monitor $monitor) => $this->sslDaysLeft($monitor) > 30)->count(),
            'expiring' => $monitors->filter(fn (Monitor $monitor) => ($days = $this->sslDaysLeft($monitor)) !== null && $days >= 0 && $days <= 30)->count(),
            'expired' => $monitors->filter(fn (Monitor $monitor) => ($days = $this->sslDaysLeft($monitor)) !== null && $days < 0)->count(),
            'pending' => $monitors->whereNull('ssl_expires_at')->count(),
        ];

        return view('ssl-monitors.index', compact('monitors', 'summary'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'urls' => ['required', 'string', 'max:10000'],
        ]);

        $urls = $this->parseUrls($validated['urls']);

        if ($urls->isEmpty()) {
            return back()
                ->withErrors(['urls' => 'Enter at least one valid HTTPS URL.'])
                ->withInput();
        }

        $created = 0;
        $existing = 0;

        foreach ($urls as $url) {
            $monitor = Monitor::where('user_id', auth()->id())
                ->where('url', $url)
                ->first();

            if ($monitor) {
                $existing++;
            } else {
                $monitor = Monitor::create([
                    'user_id' => auth()->id(),
                    'name' => $this->nameFromUrl($url),
                    'url' => $url,
                    'interval' => 86400,
                    'is_active' => true,
                    'seo_enabled' => false,
                    'alert_emails' => [],
                    'ssl_alert_threshold_days' => 60,
                ]);

                $created++;
            }

            CheckWebsiteJob::dispatch($monitor, true);
        }

        return redirect()
            ->route('ssl-monitors.index')
            ->with('success', "SSL monitor URLs queued. Created: {$created}. Existing refreshed: {$existing}.");
    }

    public function check(Monitor $monitor)
    {
        $this->authorize('check', $monitor);

        if (! Str::startsWith(Str::lower($monitor->url), 'https://')) {
            return redirect()
                ->route('ssl-monitors.index')
                ->withErrors(['urls' => 'Only HTTPS monitors can be checked from SSL Monitor.']);
        }

        CheckWebsiteJob::dispatch($monitor, true);

        return redirect()
            ->route('ssl-monitors.index')
            ->with('success', 'SSL check queued for '.$monitor->name.'.');
    }

    public function checkAll()
    {
        $monitors = $this->sslMonitorQuery()->get();

        foreach ($monitors as $monitor) {
            CheckWebsiteJob::dispatch($monitor, true);
        }

        return redirect()
            ->route('ssl-monitors.index')
            ->with('success', 'SSL checks queued for '.$monitors->count().' monitor'.($monitors->count() === 1 ? '' : 's').'.');
    }

    public function destroy(Monitor $monitor)
    {
        $this->authorize('delete', $monitor);

        if (! Str::startsWith(Str::lower($monitor->url), 'https://')) {
            return redirect()
                ->route('ssl-monitors.index')
                ->withErrors(['urls' => 'Only HTTPS monitors can be removed from SSL Monitor.']);
        }

        $name = $monitor->name;
        $monitor->delete();

        return redirect()
            ->route('ssl-monitors.index')
            ->with('success', 'SSL monitor removed: '.$name.'.');
    }

    public function updateThreshold(Request $request, Monitor $monitor)
    {
        $this->authorize('update', $monitor);

        if (! Str::startsWith(Str::lower($monitor->url), 'https://')) {
            return redirect()
                ->route('ssl-monitors.index')
                ->withErrors(['ssl_alert_threshold_days' => 'Only HTTPS monitors can use SSL alert thresholds.']);
        }

        $validated = $request->validate([
            'ssl_alert_threshold_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $monitor->update([
            'ssl_alert_threshold_days' => $validated['ssl_alert_threshold_days'],
        ]);

        return redirect()
            ->route('ssl-monitors.index')
            ->with('success', 'SSL alert threshold updated for '.$monitor->name.'.');
    }

    private function sslMonitorQuery()
    {
        $query = Monitor::query()
            ->whereRaw('LOWER(url) LIKE ?', ['https://%']);

        if (! auth()->user()->hasRole('Super Admin')) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    private function parseUrls(string $input): Collection
    {
        return collect(preg_split('/[\r\n,]+/', $input) ?: [])
            ->map(fn (string $url) => $this->normalizeUrl($url))
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) === null || ($parts['host'] ?? null) === null) {
            return null;
        }

        if (Str::lower($parts['scheme']) !== 'https') {
            return null;
        }

        $normalized = 'https://'.Str::lower($parts['host']);

        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $path = $parts['path'] ?? '';
        $path = $path === '/' ? '' : rtrim($path, '/');
        $normalized .= $path;

        if (isset($parts['query'])) {
            $normalized .= '?'.$parts['query'];
        }

        return $normalized;
    }

    private function nameFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;

        return Str::headline(str_replace(['www.', '.', '-'], ['', ' ', ' '], $host));
    }

    private function sslDaysLeft(Monitor $monitor): ?int
    {
        return $monitor->ssl_expires_at
            ? (int) floor(now()->diffInDays($monitor->ssl_expires_at, false))
            : null;
    }
}
