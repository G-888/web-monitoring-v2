<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Client;
use App\Models\MaintenanceReport;
use App\Models\Server;
use App\Jobs\GenerateMaintenanceReportJob;
use App\Services\AuditLogger;
use App\Services\MaintenanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MaintenanceReportController extends Controller
{
    public function index(): View
    {
        return view('reports.maintenance.index', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'code', 'environment']),
            'applications' => Application::query()->orderBy('name')->get(['id', 'name', 'environment']),
            'serverGroups' => Server::query()->whereNotNull('group')->distinct()->orderBy('group')->pluck('group'),
            'environments' => Application::query()->whereNotNull('environment')->distinct()->orderBy('environment')->pluck('environment'),
            'reports' => MaintenanceReport::query()->with(['application', 'client', 'generatedBy'])->latest()->limit(10)->get(),
        ]);
    }

    public function history(): View
    {
        return view('reports.maintenance.history', [
            'reports' => MaintenanceReport::query()->with(['application', 'client', 'generatedBy'])->latest()->paginate(25),
        ]);
    }

    public function generate(Request $request, MaintenanceReportService $service, AuditLogger $auditLogger)
    {
        $validated = $request->validate([
            'report_type' => ['required', 'in:daily,weekly,monthly,custom'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'application_id' => ['nullable', 'exists:applications,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'server_group' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'max:255'],
            'output' => ['required', 'in:html,pdf,excel'],
        ]);

        [$periodStart, $periodEnd] = $this->period($validated);

        if ($periodStart->diffInDays($periodEnd) > 93) {
            return back()
                ->withErrors(['period_end' => 'Maintenance reports are limited to 93 days for interactive generation.'])
                ->withInput();
        }

        $application = ! empty($validated['application_id'])
            ? Application::find($validated['application_id'])
            : null;
        $client = ! empty($validated['client_id'])
            ? Client::find($validated['client_id'])
            : null;

        $title = $this->title($validated['report_type'], $periodStart, $periodEnd, $application, $client);
        $summary = $service->build([
            ...$validated,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $isQueuedExport = in_array($validated['output'], ['pdf', 'excel'], true);

        $report = MaintenanceReport::create([
            'title' => $title,
            'report_type' => $validated['report_type'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'application_id' => $application?->id,
            'client_id' => $client?->id,
            'generated_by' => $request->user()?->id,
            'status' => $isQueuedExport ? 'pending' : 'completed',
            'summary' => $summary,
            'queued_at' => $isQueuedExport ? now() : null,
            'completed_at' => $isQueuedExport ? null : now(),
        ]);

        if ($isQueuedExport) {
            GenerateMaintenanceReportJob::dispatch($report, $validated['output'])->onQueue('reports');

            $auditLogger->log('report_queued', $report, [
                'output' => $validated['output'],
                'period_start' => $periodStart->toDateTimeString(),
                'period_end' => $periodEnd->toDateTimeString(),
            ], $request);

            return redirect()
                ->route('reports.maintenance.history')
                ->with('success', strtoupper($validated['output']).' report generation queued. Download will appear when completed.');
        }

        $auditLogger->log('report_generated', $report, [
            'output' => 'html',
            'period_start' => $periodStart->toDateTimeString(),
            'period_end' => $periodEnd->toDateTimeString(),
        ], $request);

        return view('reports.maintenance.preview', compact('report', 'summary'));
    }

    public function download(Request $request, MaintenanceReport $maintenanceReport, AuditLogger $auditLogger)
    {
        abort_unless(
            $maintenanceReport->status === 'completed'
            && $maintenanceReport->file_path
            && Storage::disk('local')->exists($maintenanceReport->file_path),
            404
        );

        $auditLogger->log('report_downloaded', $maintenanceReport, [
            'file_path' => $maintenanceReport->file_path,
        ], $request);

        return Storage::disk('local')->download($maintenanceReport->file_path);
    }

    private function period(array $validated): array
    {
        if ($validated['report_type'] === 'custom') {
            return [
                Carbon::parse($validated['period_start'] ?? now()->startOfDay())->startOfDay(),
                Carbon::parse($validated['period_end'] ?? now())->endOfDay(),
            ];
        }

        $anchor = ! empty($validated['period_end']) ? Carbon::parse($validated['period_end']) : now();

        return match ($validated['report_type']) {
            'weekly' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'monthly' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
        };
    }

    private function title(string $type, Carbon $periodStart, Carbon $periodEnd, ?Application $application, ?Client $client = null): string
    {
        $scope = $application ? $application->name : ($client ? $client->name : 'Enterprise Monitoring');

        return Str::headline($type).' Maintenance Report - '.$scope.' - '.$periodStart->format('Y-m-d').' to '.$periodEnd->format('Y-m-d');
    }

}
