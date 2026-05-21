<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\FileIntegrityHash;
use App\Services\FileIntegrityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FileIntegrityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public array $backoff = [60, 120, 300];

    public function __construct(protected ?Monitor $monitor = null)
    {
        $this->onQueue('security');
    }

    public function handle(FileIntegrityService $service): void
    {
        // File integrity is a system-level check for the local server.
        $pathsToWatch = [
            storage_path('logs'),
            public_path()
        ];

        $currentHashes = $service->getHashes($pathsToWatch);

        foreach ($currentHashes as $path => $hash) {
            $record = \App\Models\FileIntegrityHash::firstOrNew([
                'monitor_id' => $this->monitor?->id, // Can be null for system-wide checks
                'file_path' => $path
            ]);

            if ($record->exists && $record->hash !== $hash) {
                $this->dispatchAlert($path);
            }

            $record->hash = $hash;
            $record->last_checked_at = now();
            $record->save();
        }
    }

    protected function dispatchAlert(string $path): void
    {
        $message = "[FILE_TAMPERING] File change detected: {$path} on server monitoring node.";

        if ($this->monitor) {
            $user = $this->monitor->user;
            if ($user) {
                $this->sendToUserChannels($user, $message);
                return;
            }
        }

        // Fallback: Alert all Super Admins
        $admins = \App\Models\User::role('Super Admin')->get();
        foreach ($admins as $admin) {
            $this->sendToUserChannels($admin, $message);
        }
    }

    protected function sendToUserChannels($user, $message): void
    {
        $channels = $user->alertChannels()->where('is_active', true)->get();
        foreach ($channels as $channel) {
            try {
                Http::timeout(5)->post($channel->endpoint, ['content' => $message]);
            } catch (\Exception $e) {
                Log::error("Failed to send alert to user {$user->id}: " . $e->getMessage());
            }
        }
    }
}
