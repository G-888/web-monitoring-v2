<?php

namespace App\Jobs;

use App\Events\ServerMetricUpdated;
use App\Models\ServerMetric;
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
    public function handle(): void
    {
        try {
            // Store the metric
            $metric = ServerMetric::create($this->metricData);

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
