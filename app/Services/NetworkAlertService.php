<?php

namespace App\Services;

use App\Jobs\SendTelegramNotification;
use App\Models\NetworkCheckResult;
use App\Models\NetworkMonitor;
use App\Models\ServerPortBaseline;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NetworkAlertService
{
    public function evaluateMonitor(NetworkMonitor $monitor, NetworkCheckResult $result): void
    {
        if (! $monitor->is_active
            || $monitor->isUnderMaintenance()
            || $monitor->sourceServer?->isUnderMaintenance()
            || $monitor->targetServer?->isUnderMaintenance()) {
            return;
        }

        $latencyExceeded = $monitor->latency_threshold_ms !== null
            && $result->latency_ms !== null
            && $result->latency_ms > $monitor->latency_threshold_ms;

        if ($result->is_successful && ! $latencyExceeded) {
            return;
        }

        if (! $this->canAlert($monitor->last_alert_at, $monitor->alert_cooldown_seconds)) {
            return;
        }

        $subject = match ($result->status) {
            'mismatch' => "Network DNS mismatch: {$monitor->name}",
            'dns_drift' => "Network DNS drift detected: {$monitor->name}",
            'unexpected_open' => "Unexpected open port: {$monitor->name}",
            'unsupported' => "Network check unsupported: {$monitor->name}",
            default => $latencyExceeded ? "Network latency threshold exceeded: {$monitor->name}" : "Network check failed: {$monitor->name}",
        };

        $message = implode("\n", array_filter([
            $subject,
            '',
            "Monitor: {$monitor->name}",
            "Type: {$monitor->type}",
            $monitor->application ? "Affected application: {$monitor->application->name}" : null,
            "Dependency: ".($monitor->dependency_type ?: 'network'),
            "Source: {$monitor->sourceLabel()}",
            "Destination: {$monitor->destinationLabel()} ({$monitor->endpointLabel()})",
            "Status: {$result->status}",
            $result->latency_ms !== null ? "Latency: {$result->latency_ms} ms" : null,
            $monitor->latency_threshold_ms !== null ? "Latency threshold: {$monitor->latency_threshold_ms} ms" : null,
            $result->expected_value ? "Expected: {$result->expected_value}" : null,
            $result->resolved_value ? "Resolved: {$result->resolved_value}" : null,
            $result->error ? "Error: {$result->error}" : null,
            'Time: '.now()->format('Y-m-d H:i:s'),
        ]));

        $this->dispatch($subject, $message);
        $monitor->forceFill(['last_alert_at' => now()])->save();
    }

    public function evaluatePortBaseline(ServerPortBaseline $baseline, array $result): void
    {
        if (! $baseline->is_active || $baseline->server?->isUnderMaintenance() || ($result['is_successful'] ?? false)) {
            return;
        }

        if (! $this->canAlert($baseline->last_alert_at, $baseline->alert_cooldown_seconds)) {
            return;
        }

        $subject = ($baseline->expected_state === 'closed' ? 'Unexpected open server port' : 'Expected server port closed').": {$baseline->server?->name}";
        $message = implode("\n", array_filter([
            $subject,
            '',
            'Server: '.$baseline->server?->name,
            "Port: {$baseline->port}/{$baseline->protocol}",
            "Expected: {$baseline->expected_state}",
            'Observed: '.($result['resolved_value'] ?? $result['status'] ?? 'unknown'),
            $result['error'] ?? null,
            'Time: '.now()->format('Y-m-d H:i:s'),
        ]));

        $this->dispatch($subject, $message);
        $baseline->forceFill(['last_alert_at' => now()])->save();
    }

    private function canAlert($lastAlertAt, int $cooldownSeconds): bool
    {
        return ! $lastAlertAt || $lastAlertAt->lte(now()->subSeconds(max(60, $cooldownSeconds)));
    }

    private function dispatch(string $subject, string $message): void
    {
        try {
            $users = User::role('Super Admin')->get();
        } catch (Throwable $e) {
            Log::warning('Super Admin role is unavailable for network alert dispatch', [
                'error' => $e->getMessage(),
            ]);

            $users = collect();
        }

        if ($users->isEmpty()) {
            $users = User::whereHas('alertChannels', fn ($query) => $query->where('is_active', true))->get();
        }

        foreach ($users as $user) {
            foreach ($user->alertChannels()->where('is_active', true)->get() as $channel) {
                try {
                    match ($channel->type) {
                        'email' => Mail::raw($message, fn ($mail) => $mail->to($channel->endpoint)->subject($subject)),
                        'slack', 'discord' => Http::timeout(5)->post($channel->endpoint, ['content' => $message]),
                        'telegram' => SendTelegramNotification::dispatch($message),
                        default => null,
                    };
                } catch (Throwable $e) {
                    Log::error('Failed to send network alert', [
                        'user_id' => $user->id,
                        'channel_id' => $channel->id,
                        'channel_type' => $channel->type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
