<?php

namespace App\Console\Commands;

use App\Models\CheckResult;
use App\Models\DatabaseCheck;
use App\Models\IisLogSummary;
use App\Models\IisSuspiciousEvent;
use App\Models\MaintenanceReport;
use App\Models\NetworkCheckResult;
use App\Models\ServerMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneMonitoringData extends Command
{
    protected $signature = 'monitoring:prune {--dry-run : Show counts without deleting data}';

    protected $description = 'Prune old monitoring history and report files according to retention settings.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $retention = config('monitoring.retention_days', []);

        $this->pruneModel('metrics', ServerMetric::query(), 'timestamp', (int) ($retention['metrics'] ?? 90), $dryRun);
        $this->pruneModel('check results', CheckResult::query(), 'checked_at', (int) ($retention['check_results'] ?? 180), $dryRun);
        $this->pruneModel('IIS summaries', IisLogSummary::query(), 'window_start', (int) ($retention['iis_summaries'] ?? 90), $dryRun);
        $this->pruneModel('IIS suspicious events', IisSuspiciousEvent::query(), 'created_at', (int) ($retention['iis_suspicious_events'] ?? 180), $dryRun);
        $this->pruneModel('network results', NetworkCheckResult::query(), 'checked_at', (int) ($retention['network_results'] ?? 180), $dryRun);
        $this->pruneModel('database checks', DatabaseCheck::query(), 'checked_at', (int) ($retention['database_checks'] ?? 180), $dryRun);
        $this->pruneReportFiles((int) ($retention['report_files'] ?? 365), $dryRun);

        return self::SUCCESS;
    }

    private function pruneModel(string $label, $query, string $column, int $days, bool $dryRun): void
    {
        $cutoff = now()->subDays($days);
        $query->where($column, '<', $cutoff);
        $count = (clone $query)->count();

        if (! $dryRun) {
            $query->delete();
        }

        $this->line(sprintf('%s: %d %s older than %d days.', ucfirst($label), $count, $dryRun ? 'would be deleted' : 'deleted', $days));
    }

    private function pruneReportFiles(int $days, bool $dryRun): void
    {
        $cutoff = now()->subDays($days);
        $reports = MaintenanceReport::query()
            ->whereNotNull('file_path')
            ->where('created_at', '<', $cutoff)
            ->get();

        if (! $dryRun) {
            foreach ($reports as $report) {
                Storage::disk('local')->delete($report->file_path);
                $report->forceFill(['file_path' => null])->save();
            }
        }

        $this->line(sprintf('Report files: %d %s older than %d days.', $reports->count(), $dryRun ? 'would be removed' : 'removed', $days));
    }
}
