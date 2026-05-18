<?php

namespace App\Jobs;

use App\Jobs\SendTelegramNotification;
use App\Models\DatabaseCheck;
use App\Models\DatabaseMonitor;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PDO;
use Throwable;

class CheckDatabaseConnection implements ShouldQueue
{
    use Queueable;

    public function __construct(public DatabaseMonitor $databaseMonitor, public bool $force = false)
    {
    }

    public function handle(): void
    {
        $monitor = $this->databaseMonitor->refresh();

        if (!$this->force && !$monitor->is_active) {
            return;
        }

        $startedAt = microtime(true);
        $isUp = false;
        $error = null;

        try {
            $pdo = new PDO(
                $this->dsn($monitor),
                $monitor->username,
                $monitor->password ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]
            );
            $pdo->query($this->probeQuery($monitor->driver));
            $pdo = null;
            $isUp = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            Log::warning('Database monitor check failed', [
                'database_monitor_id' => $monitor->id,
                'error' => $error,
            ]);
        }

        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        DatabaseCheck::create([
            'database_monitor_id' => $monitor->id,
            'is_up' => $isUp,
            'response_time_ms' => $responseTimeMs,
            'error' => $error,
            'checked_at' => now(),
        ]);

        $monitor->forceFill([
            'last_status' => $isUp ? 'up' : 'down',
            'last_response_time_ms' => $responseTimeMs,
            'last_error' => $error,
            'last_checked_at' => now(),
        ])->save();

        if (!$isUp && $this->canAlert($monitor)) {
            $this->sendFailureAlert($monitor, $error);
            $monitor->forceFill(['last_failure_alert_at' => now()])->save();
        }
    }

    private function dsn(DatabaseMonitor $monitor): string
    {
        return match ($monitor->driver) {
            'pgsql' => "pgsql:host={$monitor->host};port={$monitor->port};dbname={$monitor->database_name}",
            default => "mysql:host={$monitor->host};port={$monitor->port};dbname={$monitor->database_name};charset=utf8mb4",
        };
    }

    private function probeQuery(string $driver): string
    {
        return match ($driver) {
            'pgsql' => 'select 1',
            default => 'select 1',
        };
    }

    private function canAlert(DatabaseMonitor $monitor): bool
    {
        return !$monitor->last_failure_alert_at
            || $monitor->last_failure_alert_at->lte(now()->subSeconds($monitor->alert_cooldown_seconds ?? 900));
    }

    private function sendFailureAlert(DatabaseMonitor $monitor, ?string $error): void
    {
        $subject = "Database connection failed: {$monitor->name}";
        $message = implode("\n", array_filter([
            $subject,
            '',
            "Driver: {$monitor->driver}",
            "Host: {$monitor->host}:{$monitor->port}",
            "Database: {$monitor->database_name}",
            'Time: ' . now()->format('Y-m-d H:i:s'),
            $error ? "Error: {$error}" : null,
        ]));

        try {
            $users = User::role('Super Admin')->get();
        } catch (Throwable $e) {
            Log::warning('Super Admin role is unavailable for database monitor alert dispatch', [
                'database_monitor_id' => $monitor->id,
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
                    Log::error('Failed to send database monitor alert', [
                        'database_monitor_id' => $monitor->id,
                        'channel_id' => $channel->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
