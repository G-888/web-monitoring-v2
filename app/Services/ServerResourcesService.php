<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Server;

class ServerResourcesService
{
    private const CPU_CACHE_KEY = 'server_resources:cpu_prev_v1';
    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array{
     *   fetched_at: string,
     *   cpu_percent: float|null,
     *   ram:{
     *     used_mb: float|null,
     *     total_mb: float|null,
     *     used_percent: float|null
     *   },
     *   storage:{
     *     total_gb: float|null,
     *     free_gb: float|null,
     *     used_percent: float|null
     *   }
     * }
     */
    public function getSnapshot(): array
    {
        return Server::query()
            ->where('is_active', true)
            ->with('latestMetric')
            ->orderBy('name')
            ->get()
            ->map(function (Server $server) {
                $latest = $server->latestMetric;

                if (!$latest) {
                    return null;
                }

                $isOnline = $server->last_heartbeat_at
                    && $server->last_heartbeat_at->gt(now()->subSeconds($server->offline_threshold_seconds ?? 15));

                return [
                    'server_id' => $server->server_id,
                    'name' => $server->name,
                    'cpu' => $latest->cpu,
                    'ram_used' => $latest->ram_used,
                    'ram_total' => $latest->ram_total,
                    'disk_used' => $latest->disk_used,
                    'disk_total' => $latest->disk_total,
                    'updated_at' => $server->last_heartbeat_at?->toISOString() ?? $latest->timestamp->toISOString(),
                    'is_online' => $isOnline,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private function getCpuPercent(): float|null
    {
        $curr = $this->readProcStat();
        if ($curr === null) {
            return null;
        }

        $prev = Cache::get(self::CPU_CACHE_KEY);

        // First read: we don't have a previous sample to compute deltas.
        if (!is_array($prev) || !isset($prev['total'], $prev['idle'])) {
            Cache::put(self::CPU_CACHE_KEY, $curr, self::CACHE_TTL_SECONDS);
            return null;
        }

        $deltaTotal = $curr['total'] - (float) $prev['total'];
        $deltaIdle = $curr['idle'] - (float) $prev['idle'];

        Cache::put(self::CPU_CACHE_KEY, $curr, self::CACHE_TTL_SECONDS);

        if ($deltaTotal <= 0) {
            return null;
        }

        $deltaBusy = $deltaTotal - $deltaIdle;
        $percent = ($deltaBusy / $deltaTotal) * 100;

        // Clamp just in case of odd deltas.
        return max(0.0, min(100.0, $percent));
    }

    /**
     * Reads /proc/stat and returns cumulative counters for the "cpu" line.
     *
     * @return array{total: float, idle: float}|null
     */
    private function readProcStat(): array|null
    {
        $path = '/proc/stat';
        if (!is_readable($path)) {
            return null;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return null;
        }

        $cpuLine = null;
        foreach ($lines as $line) {
            if (Str::startsWith(trim($line), 'cpu ')) {
                $cpuLine = $line;
                break;
            }
        }

        if ($cpuLine === null) {
            return null;
        }

        // Example (values after "cpu"):
        // user nice system idle iowait irq softirq steal guest guest_nice
        $parts = preg_split('/\s+/', trim($cpuLine));
        if (!is_array($parts) || count($parts) < 5) {
            return null;
        }

        $values = array_slice($parts, 1);
        $user = (float) ($values[0] ?? 0);
        $nice = (float) ($values[1] ?? 0);
        $system = (float) ($values[2] ?? 0);
        $idle = (float) ($values[3] ?? 0);
        $iowait = (float) ($values[4] ?? 0);
        $irq = (float) ($values[5] ?? 0);
        $softirq = (float) ($values[6] ?? 0);
        $steal = (float) ($values[7] ?? 0);

        $idleAll = $idle + $iowait;
        $total = $user + $nice + $system + $idle + $iowait + $irq + $softirq + $steal;

        if ($total <= 0) {
            return null;
        }

        return [
            'total' => $total,
            'idle' => $idleAll,
        ];
    }

    /**
     * @return array{used_mb: float|null, total_mb: float|null, used_percent: float|null}
     */
    private function getRamInfo(): array
    {
        $path = '/proc/meminfo';
        if (!is_readable($path)) {
            return [
                'used_mb' => null,
                'total_mb' => null,
                'used_percent' => null,
            ];
        }

        $memTotalKb = null;
        $memAvailableKb = null;

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [
                'used_mb' => null,
                'total_mb' => null,
                'used_percent' => null,
            ];
        }

        foreach ($lines as $line) {
            if (Str::startsWith($line, 'MemTotal:')) {
                $memTotalKb = $this->extractKbValue($line);
            } elseif (Str::startsWith($line, 'MemAvailable:')) {
                $memAvailableKb = $this->extractKbValue($line);
            }

            if ($memTotalKb !== null && $memAvailableKb !== null) {
                break;
            }
        }

        if ($memTotalKb === null || $memAvailableKb === null) {
            return [
                'used_mb' => null,
                'total_mb' => null,
                'used_percent' => null,
            ];
        }

        $usedKb = $memTotalKb - $memAvailableKb;
        if ($usedKb < 0) {
            $usedKb = 0;
        }

        $totalMb = $memTotalKb / 1024;
        $usedMb = $usedKb / 1024;

        $usedPercent = $memTotalKb > 0 ? ($usedKb / $memTotalKb) * 100 : null;
        if ($usedPercent !== null) {
            $usedPercent = max(0.0, min(100.0, $usedPercent));
        }

        return [
            'used_mb' => $usedMb,
            'total_mb' => $totalMb,
            'used_percent' => $usedPercent,
        ];
    }

    private function extractKbValue(string $line): float|null
    {
        // Example: "MemTotal:       16330456 kB"
        if (preg_match('/:\s+([0-9]+)\s+kB/i', $line, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    /**
     * @return array{total_gb: float|null, free_gb: float|null, used_percent: float|null}
     */
    private function getStorageInfo(): array
    {
        // Use a path that should exist in your app.
        $dir = storage_path('app');
        if (!is_dir($dir)) {
            $dir = storage_path();
        }

        if (!is_dir($dir)) {
            return [
                'total_gb' => null,
                'free_gb' => null,
                'used_percent' => null,
            ];
        }

        $totalBytes = @disk_total_space($dir);
        $freeBytes = @disk_free_space($dir);

        if ($totalBytes === false || $freeBytes === false || $totalBytes <= 0) {
            return [
                'total_gb' => null,
                'free_gb' => null,
                'used_percent' => null,
            ];
        }

        $usedBytes = $totalBytes - $freeBytes;
        if ($usedBytes < 0) {
            $usedBytes = 0;
        }

        $totalGb = $totalBytes / (1024 ** 3);
        $freeGb = $freeBytes / (1024 ** 3);

        $usedPercent = ($totalBytes > 0) ? ($usedBytes / $totalBytes) * 100 : null;
        if ($usedPercent !== null) {
            $usedPercent = max(0.0, min(100.0, $usedPercent));
        }

        return [
            'total_gb' => $totalGb,
            'free_gb' => $freeGb,
            'used_percent' => $usedPercent,
        ];
    }
}
