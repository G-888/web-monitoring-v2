<?php

namespace App\Services;

use App\Jobs\SendTelegramNotification;
use App\Models\Server;
use App\Models\User;
use App\Models\WindowsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ServerAlertService
{
    public function sendThresholdAlert(Server $server, string $metric, float $value, float $threshold, ?string $detail = null): void
    {
        $metricLabel = strtoupper($metric);
        $subject = "Server {$metricLabel} threshold alert: {$server->name}";
        $message = $this->formatMessage($server, $subject, [
            'Metric' => $metricLabel,
            'Current' => $this->formatPercent($value),
            'Threshold' => $this->formatPercent($threshold),
            'Detail' => $detail,
            'Time' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->dispatch($subject, $message);
    }

    public function sendOfflineAlert(Server $server): void
    {
        $subject = "Server offline: {$server->name}";
        $message = $this->formatMessage($server, $subject, [
            'Server ID' => $server->server_id,
            'Last heartbeat' => $server->last_heartbeat_at?->diffForHumans() ?? 'Never',
            'Threshold' => $server->offline_threshold_seconds . ' seconds',
            'Time' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->dispatch($subject, $message);
    }

    public function sendWindowsServiceAlert(Server $server, WindowsService $service): void
    {
        $serviceLabel = $service->display_name ?? $service->service_name;
        $subject = "Windows service stopped: {$serviceLabel}";
        $message = $this->formatMessage($server, $subject, [
            'Service' => $service->service_name,
            'Display name' => $service->display_name,
            'Status' => $service->status,
            'Startup type' => $service->startup_type,
            'Checked at' => $service->last_checked_at?->format('Y-m-d H:i:s'),
        ]);

        $this->dispatch($subject, $message);
    }

    public function sendIisLogAlert(Server $server, string $rule, int $value, int $threshold, array $context = []): void
    {
        $labels = [
            'http_500_spike' => 'IIS HTTP 500 spike',
            'http_404_spike' => 'IIS HTTP 404 spike',
            'suspicious_event_spike' => 'IIS suspicious event spike',
        ];

        $subject = ($labels[$rule] ?? 'IIS log alert') . ": {$server->name}";
        $message = $this->formatMessage($server, $subject, [
            'Rule' => $rule,
            'Current window count' => (string) $value,
            'Threshold' => (string) $threshold,
            'Window start' => $context['window_start'] ?? null,
            'Window end' => $context['window_end'] ?? null,
            'Total requests' => isset($context['total_requests']) ? (string) $context['total_requests'] : null,
            'Time' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->dispatch($subject, $message);
    }

    private function dispatch(string $subject, string $message): void
    {
        try {
            $users = User::role('Super Admin')->get();
        } catch (\Throwable $e) {
            Log::warning('Super Admin role is unavailable for server alert dispatch', [
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
                } catch (\Throwable $e) {
                    Log::error('Failed to send server alert', [
                        'user_id' => $user->id,
                        'channel_id' => $channel->id,
                        'channel_type' => $channel->type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * @param array<string, string|null> $fields
     */
    private function formatMessage(Server $server, string $title, array $fields): string
    {
        $lines = [
            $title,
            '',
            "Server: {$server->name}",
            "Server ID: {$server->server_id}",
        ];

        if ($server->ip_address) {
            $lines[] = "IP: {$server->ip_address}";
        }

        foreach ($fields as $label => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 1) . '%';
    }
}
