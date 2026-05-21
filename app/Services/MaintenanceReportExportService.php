<?php

namespace App\Services;

use App\Models\MaintenanceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MaintenanceReportExportService
{
    public function export(MaintenanceReport $report, string $output): string
    {
        return match ($output) {
            'pdf' => $this->pdf($report),
            'excel' => $this->excel($report),
            default => throw new InvalidArgumentException('Unsupported report export output.'),
        };
    }

    private function pdf(MaintenanceReport $report): string
    {
        $summary = $report->summary ?? [];
        $pdf = Pdf::loadView('reports.maintenance.pdf', compact('report', 'summary'))
            ->setPaper('a4', 'portrait');
        $path = $this->path($report, 'pdf');

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    private function excel(MaintenanceReport $report): string
    {
        $path = $this->path($report, 'xls');
        Storage::disk('local')->put($path, $this->spreadsheetXml($report));

        return $path;
    }

    private function path(MaintenanceReport $report, string $extension): string
    {
        return 'reports/'.Str::slug($report->title).'-'.$report->id.'.'.$extension;
    }

    private function spreadsheetXml(MaintenanceReport $report): string
    {
        $summary = $report->summary ?? [];
        $rows = [
            ['Section', 'Name', 'Metric', 'Value'],
            ['Executive', 'Overall Status', 'Status', $summary['executive']['overall_status'] ?? 'unknown'],
            ['Scope', 'Servers', 'Count', $summary['scope']['server_count'] ?? 0],
            ['Scope', 'Applications', 'Count', $summary['scope']['application_count'] ?? 0],
            ['Website', 'Average Uptime', 'Percent', $summary['website']['average_uptime'] ?? 'No data'],
            ['Website', 'Downtime', 'Count', $summary['website']['downtime_count'] ?? 0],
            ['Database', 'Failures', 'Count', $summary['database']['failures'] ?? 0],
            ['Network', 'Failures', 'Count', $summary['network']['failures'] ?? 0],
            ['IIS', 'HTTP 404', 'Count', $summary['iis']['http_404'] ?? 0],
            ['IIS', 'HTTP 500', 'Count', $summary['iis']['http_500'] ?? 0],
            ['IIS', 'Suspicious', 'Count', $summary['iis']['suspicious_count'] ?? 0],
        ];

        foreach ($summary['servers']['rows'] ?? [] as $server) {
            $rows[] = ['Server', $server['name'], 'Heartbeat', $server['heartbeat']];
            $rows[] = ['Server', $server['name'], 'CPU Max %', $server['cpu_max'] ?? 'No data'];
            $rows[] = ['Server', $server['name'], 'Disk Max %', $server['disk_max'] ?? 'No data'];
        }

        foreach ($summary['network']['rows'] ?? [] as $monitor) {
            $rows[] = ['Network', $monitor['name'], 'Target', $monitor['target']];
            $rows[] = ['Network', $monitor['name'], 'Status', $monitor['last_status']];
            if (! empty($monitor['application'])) {
                $rows[] = ['Network', $monitor['name'], 'Affected Application', $monitor['application']];
            }
        }

        foreach ($summary['network']['dns_mismatches'] ?? [] as $dns) {
            $rows[] = ['Network DNS', $dns['name'], 'Expected', $dns['expected'] ?? ''];
            $rows[] = ['Network DNS', $dns['name'], 'Resolved', $dns['resolved'] ?? ''];
        }

        foreach ($summary['network']['port_baseline_violations'] ?? [] as $baseline) {
            $rows[] = ['Port Baseline', $baseline['server'], 'Port', ($baseline['port'] ?? '').'/'.($baseline['protocol'] ?? 'tcp')];
            $rows[] = ['Port Baseline', $baseline['server'], 'Status', $baseline['last_status'] ?? 'unknown'];
        }

        foreach ($summary['recommendations'] ?? [] as $recommendation) {
            $rows[] = ['Recommendations', 'Action', 'Text', $recommendation];
        }

        $xmlRows = collect($rows)->map(function (array $row) {
            $cells = collect($row)->map(fn ($cell) => '<Cell><Data ss:Type="String">'.e((string) $cell).'</Data></Cell>')->implode('');

            return "<Row>{$cells}</Row>";
        })->implode('');

        return <<<XML
<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="Maintenance Report">
  <Table>{$xmlRows}</Table>
 </Worksheet>
</Workbook>
XML;
    }
}
