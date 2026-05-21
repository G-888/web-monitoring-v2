<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function log(
        string $eventType,
        ?Model $auditable = null,
        array $metadata = [],
        ?Request $request = null,
        ?int $userId = null
    ): AuditEvent {
        return AuditEvent::create([
            'user_id' => $userId ?? $request?->user()?->id,
            'event_type' => $eventType,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $this->sanitize($metadata),
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        return collect($metadata)
            ->mapWithKeys(function ($value, $key) {
                if ($this->isSecretKey((string) $key)) {
                    return [$key => '[redacted]'];
                }

                if (is_array($value)) {
                    return [$key => $this->sanitize($value)];
                }

                return [$key => $value];
            })
            ->all();
    }

    private function isSecretKey(string $key): bool
    {
        return Str::of($key)->lower()->contains([
            'password',
            'token',
            'api_key',
            'apikey',
            'secret',
        ]);
    }
}
