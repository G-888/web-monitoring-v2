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

    public function __construct(protected Monitor $monitor)
    {}

    public function handle(FileIntegrityService $service): void
    {
        // Assuming we have a setting for paths to watch in monitor
        // For now, using storage/logs and public/ as example
        $pathsToWatch = [
            storage_path('logs'),
            public_path()
        ];

        $currentHashes = $service->getHashes($pathsToWatch);

        foreach ($currentHashes as $path => $hash) {
            $record = \App\Models\FileIntegrityHash::firstOrNew([
                'monitor_id' => $this->monitor->id,
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

        $user = $this->monitor->user;
        if ($user) {
            $channels = $user->alertChannels()->where('is_active', true)->get();
            foreach ($channels as $channel) {
                try {
                    Http::post($channel->endpoint, ['content' => $message]);
                } catch (\Exception $e) {
                    Log::error("Failed to send alert: " . $e->getMessage());
                }
            }
        }
    }
}
