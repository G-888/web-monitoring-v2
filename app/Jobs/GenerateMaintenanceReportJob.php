<?php

namespace App\Jobs;

use App\Models\MaintenanceReport;
use App\Services\AuditLogger;
use App\Services\MaintenanceReportExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateMaintenanceReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public MaintenanceReport $maintenanceReport,
        public string $output
    ) {
        $this->onQueue('reports');
    }

    public function handle(MaintenanceReportExportService $exporter, AuditLogger $auditLogger): void
    {
        $report = $this->maintenanceReport->fresh();

        $report->forceFill([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $path = $exporter->export($report, $this->output);

            $report->forceFill([
                'status' => 'completed',
                'file_path' => $path,
                'completed_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            $auditLogger->log('report_generated', $report, [
                'output' => $this->output,
                'file_path' => $path,
            ], userId: $report->generated_by);
        } catch (Throwable $e) {
            $report->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ])->save();

            $auditLogger->log('report_generation_failed', $report, [
                'output' => $this->output,
                'error' => $e->getMessage(),
            ], userId: $report->generated_by);

            throw $e;
        }
    }
}
