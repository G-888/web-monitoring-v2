<?php

namespace App\Jobs;

use App\Events\ServerMetricUpdated;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\WindowsService;
use App\Models\WindowsServiceCheck;
use App\Models\WindowsServiceCommand;
use App\Services\ServerAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessServerMetric implements ShouldQueue
{
    use Queueable;

    public array $metricData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $metricData)
    {
        $this->metricData = $metricData;
    }

    /**
     * Execute the job.
     */
    public function handle(ServerAlertService $alerts): void
    {
        try {
            $server = Server::where('server_id', $this->metricData['server_id'])->first();

            if (!$server || !$server->is_active) {
                Log::warning('Discarded metric for unknown or inactive server', [
                    'server_id' => $this->metricData['server_id'] ?? 'unknown',
                ]);

                return;
            }

            // Store the agent sample timestamp, but use server time for heartbeat freshness.
            $metricData = collect($this->metricData)->except(['services', 'command_results'])->all();
            $metric = ServerMetric::create($metricData);

            $server->forceFill(['last_heartbeat_at' => now()])->save();
            $server = $server->fresh();

            $this->evaluateThresholds($server, $metric, $alerts);
            $this->processWindowsServices($server, $alerts);
            $this->processCommandResults($server);

            // Broadcast the update
            broadcast(new ServerMetricUpdated($metric))->toOthers();

            Log::info('Server metric processed successfully', [
                'server_id' => $metric->server_id,
                'cpu' => $metric->cpu
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process server metric', [
                'server_id' => $this->metricData['server_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }

    private function evaluateThresholds(Server $server, ServerMetric $metric, ServerAlertService $alerts): void
    {
        if (!$server->is_active || !$server->alerts_enabled) {
            return;
        }

        $updates = [];

        $cpu = (float) $metric->cpu;
        if ($server->cpu_threshold !== null && $cpu >= (float) $server->cpu_threshold && $this->canAlert($server, 'last_cpu_alert_at')) {
            $alerts->sendThresholdAlert($server, 'cpu', $cpu, (float) $server->cpu_threshold);
            $updates['last_cpu_alert_at'] = now();
        }

        $ramTotal = (float) $metric->ram_total;
        $ramPercent = $ramTotal > 0 ? ((float) $metric->ram_used / $ramTotal) * 100 : null;
        if ($ramPercent !== null && $server->ram_threshold !== null && $ramPercent >= (float) $server->ram_threshold && $this->canAlert($server, 'last_ram_alert_at')) {
            $alerts->sendThresholdAlert(
                $server,
                'ram',
                $ramPercent,
                (float) $server->ram_threshold,
                "{$metric->ram_used} GB / {$metric->ram_total} GB"
            );
            $updates['last_ram_alert_at'] = now();
        }

        $diskTotal = (float) $metric->disk_total;
        $diskPercent = $diskTotal > 0 ? ((float) $metric->disk_used / $diskTotal) * 100 : null;
        if ($diskPercent !== null && $server->disk_threshold !== null && $diskPercent >= (float) $server->disk_threshold && $this->canAlert($server, 'last_disk_alert_at')) {
            $alerts->sendThresholdAlert(
                $server,
                'disk',
                $diskPercent,
                (float) $server->disk_threshold,
                "{$metric->disk_used} GB / {$metric->disk_total} GB"
            );
            $updates['last_disk_alert_at'] = now();
        }

        if ($updates !== []) {
            $server->forceFill($updates)->save();
        }
    }

    private function canAlert(Server $server, string $column): bool
    {
        $lastAlertAt = $server->{$column};

        return !$lastAlertAt || $lastAlertAt->lte(now()->subSeconds($server->alert_cooldown_seconds ?? 900));
    }

    private function processWindowsServices(Server $server, ServerAlertService $alerts): void
    {
        $services = $this->metricData['services'] ?? [];

        if (!is_array($services) || $services === []) {
            return;
        }

        foreach ($services as $serviceData) {
            $service = WindowsService::firstOrNew([
                'server_id' => $server->id,
                'service_name' => $serviceData['name'],
            ]);

            $service->fill([
                'display_name' => $serviceData['display_name'] ?? $serviceData['name'],
                'status' => $serviceData['status'],
                'startup_type' => $serviceData['startup_type'] ?? null,
                'last_checked_at' => $this->metricData['timestamp'],
            ]);

            if (!$service->exists) {
                $service->is_monitored = true;
            }

            $service->save();

            WindowsServiceCheck::create([
                'windows_service_id' => $service->id,
                'status' => $serviceData['status'],
                'startup_type' => $serviceData['startup_type'] ?? null,
                'checked_at' => $this->metricData['timestamp'],
            ]);

            if ($this->shouldAlertOnService($server, $service)) {
                $alerts->sendWindowsServiceAlert($server, $service);
                $service->forceFill(['last_alert_at' => now()])->save();
            }
        }
    }

    private function shouldAlertOnService(Server $server, WindowsService $service): bool
    {
        if (!$server->is_active || !$server->alerts_enabled || !$service->is_monitored) {
            return false;
        }

        if (strtolower((string) $service->status) === 'running') {
            return false;
        }

        return !$service->last_alert_at
            || $service->last_alert_at->lte(now()->subSeconds($server->alert_cooldown_seconds ?? 900));
    }

    private function processCommandResults(Server $server): void
    {
        $results = $this->metricData['command_results'] ?? [];

        if (!is_array($results) || $results === []) {
            return;
        }

        foreach ($results as $result) {
            WindowsServiceCommand::query()
                ->where('server_id', $server->id)
                ->where('id', $result['id'])
                ->where('status', WindowsServiceCommand::STATUS_RUNNING)
                ->update([
                    'status' => $result['status'],
                    'output' => $result['output'] ?? null,
                    'error' => $result['error'] ?? null,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Server metric processing job failed', [
            'server_id' => $this->metricData['server_id'] ?? 'unknown',
            'error' => $exception->getMessage()
        ]);
    }
}
