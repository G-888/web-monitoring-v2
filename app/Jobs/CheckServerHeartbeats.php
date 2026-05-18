<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\ServerAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckServerHeartbeats implements ShouldQueue
{
    use Queueable;

    public function handle(ServerAlertService $alerts): void
    {
        Server::query()
            ->where('is_active', true)
            ->where('alerts_enabled', true)
            ->whereNotNull('last_heartbeat_at')
            ->get()
            ->each(function (Server $server) use ($alerts) {
                if (!$this->isOffline($server) || !$this->canAlert($server)) {
                    return;
                }

                $alerts->sendOfflineAlert($server);
                $server->forceFill(['last_offline_alert_at' => now()])->save();
            });
    }

    private function isOffline(Server $server): bool
    {
        return $server->last_heartbeat_at->lte(
            now()->subSeconds($server->offline_threshold_seconds ?? 15)
        );
    }

    private function canAlert(Server $server): bool
    {
        return !$server->last_offline_alert_at
            || $server->last_offline_alert_at->lte(now()->subSeconds($server->alert_cooldown_seconds ?? 900));
    }
}
