<?php

namespace App\Services;

use App\Models\NetworkCheckResult;
use App\Models\NetworkMonitor;
use App\Models\ServerPortBaseline;
use Throwable;

class NetworkCheckService
{
    public function checkMonitor(NetworkMonitor $monitor): NetworkCheckResult
    {
        $startedAt = microtime(true);
        $payload = match ($monitor->type) {
            NetworkMonitor::TYPE_TCP_PORT => $this->checkTcp($monitor->target_host, (int) $monitor->target_port, (int) $monitor->timeout_ms, $monitor->expected_state),
            NetworkMonitor::TYPE_DNS => $this->checkDns($monitor->target_host, $monitor->dns_record_type ?: 'A', $monitor->expected_value),
            NetworkMonitor::TYPE_PING => $this->unsupported('Ping checks are unsupported on this runtime.'),
            default => $this->unsupported('Unknown network monitor type.'),
        };

        $latencyMs = $payload['latency_ms'] ?? (int) round((microtime(true) - $startedAt) * 1000);

        return $this->recordMonitorResult($monitor, [
            ...$payload,
            'latency_ms' => $latencyMs,
            'checked_at' => now(),
        ]);
    }

    public function recordMonitorResult(NetworkMonitor $monitor, array $payload): NetworkCheckResult
    {
        $payload = $this->applyDnsDriftDetection($monitor, $payload);

        $result = NetworkCheckResult::create([
            'network_monitor_id' => $monitor->id,
            'source_server_id' => $payload['source_server_id'] ?? $monitor->source_server_id,
            'type' => $monitor->type,
            'source_type' => $monitor->source_type,
            'target_host' => $payload['target_host'] ?? $monitor->target_host,
            'target_port' => $payload['target_port'] ?? $monitor->target_port,
            'status' => $payload['status'],
            'is_successful' => (bool) $payload['is_successful'],
            'latency_ms' => $payload['latency_ms'] ?? null,
            'resolved_value' => $this->stringValue($payload['resolved_value'] ?? null),
            'expected_value' => $this->stringValue($payload['expected_value'] ?? $monitor->expected_value ?: $monitor->expected_state),
            'error' => $payload['error'] ?? null,
            'checked_at' => $payload['checked_at'] ?? now(),
        ]);

        $monitor->forceFill([
            'last_status' => $result->status,
            'last_latency_ms' => $result->latency_ms,
            'last_resolved_value' => $result->resolved_value,
            'last_error' => $result->error,
            'last_checked_at' => $result->checked_at,
        ])->save();

        return $result;
    }

    private function applyDnsDriftDetection(NetworkMonitor $monitor, array $payload): array
    {
        if ($monitor->type !== NetworkMonitor::TYPE_DNS || ! ($payload['is_successful'] ?? false)) {
            return $payload;
        }

        if (($payload['expected_value'] ?? $monitor->expected_value) !== null && trim((string) ($payload['expected_value'] ?? $monitor->expected_value)) !== '') {
            return $payload;
        }

        $current = $this->normalizeResolvedValue($payload['resolved_value'] ?? null);
        $previous = $monitor->results()
            ->whereNotNull('resolved_value')
            ->latest('checked_at')
            ->value('resolved_value');

        if ($previous && $current !== '' && $this->normalizeResolvedValue($previous) !== $current) {
            $payload['status'] = 'dns_drift';
            $payload['is_successful'] = false;
            $payload['error'] = 'DNS result changed from previous resolved value.';
            $payload['expected_value'] = $previous;
        }

        return $payload;
    }

    public function checkPortBaseline(ServerPortBaseline $baseline): array
    {
        $target = $baseline->scan_target ?: $baseline->server?->ip_address;

        if (! $target) {
            return $this->updateBaseline($baseline, [
                'status' => 'unsupported',
                'is_successful' => false,
                'latency_ms' => null,
                'error' => 'No scan target configured for this server baseline.',
            ]);
        }

        $result = $this->checkTcp($target, (int) $baseline->port, (int) $baseline->timeout_ms, $baseline->expected_state);

        return $this->updateBaseline($baseline, $result);
    }

    private function updateBaseline(ServerPortBaseline $baseline, array $result): array
    {
        $baseline->forceFill([
            'last_status' => $result['status'],
            'last_latency_ms' => $result['latency_ms'] ?? null,
            'last_error' => $result['error'] ?? null,
            'last_checked_at' => now(),
        ])->save();

        return $result;
    }

    private function checkTcp(string $host, int $port, int $timeoutMs, string $expectedState = 'open'): array
    {
        if ($port < 1 || $port > 65535) {
            return [
                'status' => 'error',
                'is_successful' => false,
                'latency_ms' => null,
                'resolved_value' => null,
                'expected_value' => $expectedState,
                'error' => 'TCP port must be between 1 and 65535.',
            ];
        }

        $startedAt = microtime(true);
        $errno = 0;
        $errstr = '';
        $timeoutSeconds = max(0.2, $timeoutMs / 1000);

        try {
            $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeoutSeconds);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $isOpen = is_resource($socket);

            if ($isOpen) {
                fclose($socket);
            }

            $expectedOpen = $expectedState !== 'closed';
            $isSuccessful = $expectedOpen ? $isOpen : ! $isOpen;

            return [
                'status' => $isSuccessful ? 'up' : ($isOpen ? 'unexpected_open' : 'down'),
                'is_successful' => $isSuccessful,
                'latency_ms' => $latencyMs,
                'resolved_value' => $isOpen ? 'open' : 'closed',
                'expected_value' => $expectedState,
                'error' => $isSuccessful ? null : ($isOpen ? 'Port is open but expected closed.' : ($errstr ?: 'Connection failed.')),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'is_successful' => false,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'resolved_value' => 'error',
                'expected_value' => $expectedState,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkDns(string $host, string $recordType, ?string $expectedValue): array
    {
        $recordType = strtoupper($recordType ?: 'A');
        $records = $this->dnsValues($host, $recordType);
        $expected = $this->expectedValues($expectedValue);

        if ($records === []) {
            return [
                'status' => 'down',
                'is_successful' => false,
                'latency_ms' => null,
                'resolved_value' => null,
                'expected_value' => $expectedValue,
                'error' => "No {$recordType} records resolved.",
            ];
        }

        if ($expected === []) {
            return [
                'status' => 'up',
                'is_successful' => true,
                'latency_ms' => null,
                'resolved_value' => $records,
                'expected_value' => null,
                'error' => null,
            ];
        }

        $normalizedRecords = array_map(fn ($value) => strtolower(trim((string) $value)), $records);
        $matched = collect($expected)->contains(fn ($value) => in_array(strtolower(trim($value)), $normalizedRecords, true));

        return [
            'status' => $matched ? 'up' : 'mismatch',
            'is_successful' => $matched,
            'latency_ms' => null,
            'resolved_value' => $records,
            'expected_value' => $expected,
            'error' => $matched ? null : 'DNS result does not match expected value.',
        ];
    }

    private function unsupported(string $message): array
    {
        return [
            'status' => 'unsupported',
            'is_successful' => false,
            'latency_ms' => null,
            'resolved_value' => null,
            'expected_value' => null,
            'error' => $message,
        ];
    }

    private function dnsValues(string $host, string $recordType): array
    {
        $type = match ($recordType) {
            'AAAA' => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'MX' => DNS_MX,
            'NS' => DNS_NS,
            'TXT' => DNS_TXT,
            default => DNS_A,
        };

        $records = @dns_get_record($host, $type) ?: [];
        $values = collect($records)->map(function (array $record) use ($recordType) {
            return match ($recordType) {
                'AAAA' => $record['ipv6'] ?? null,
                'CNAME' => $record['target'] ?? null,
                'MX' => $record['target'] ?? null,
                'NS' => $record['target'] ?? null,
                'TXT' => $record['txt'] ?? null,
                default => $record['ip'] ?? null,
            };
        })->filter()->values()->all();

        if ($recordType === 'A' && $values === []) {
            $values = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique(array_map('strval', $values)));
    }

    private function expectedValues(?string $expectedValue): array
    {
        if (! $expectedValue) {
            return [];
        }

        return collect(preg_split('/\r?\n|,/', $expectedValue))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string) $value;
    }

    private function normalizeResolvedValue(mixed $value): string
    {
        $string = $this->stringValue($value) ?? '';

        return collect(explode(',', $string))
            ->map(fn ($item) => strtolower(trim($item)))
            ->filter()
            ->sort()
            ->values()
            ->implode(', ');
    }
}
