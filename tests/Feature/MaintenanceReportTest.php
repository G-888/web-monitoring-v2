<?php

use App\Models\Application;
use App\Models\ApplicationUrl;
use App\Models\CheckResult;
use App\Models\DatabaseCheck;
use App\Models\DatabaseMonitor;
use App\Models\IisLogSummary;
use App\Models\MaintenanceReport;
use App\Models\Monitor;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\User;
use App\Models\WebshellScan;
use App\Models\WindowsService;
use App\Models\WindowsServiceCheck;
use App\Jobs\GenerateMaintenanceReportJob;
use App\Services\AuditLogger;
use App\Services\MaintenanceReportExportService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function reportUser(): User
{
    foreach (['module.reports.view', 'module.reports.generate', 'module.reports.download'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['module.reports.view', 'module.reports.generate', 'module.reports.download']);

    return $user;
}

function seedReportData(): array
{
    $server = Server::create([
        'server_id' => 'report-node-01',
        'name' => 'Report Node 01',
        'group' => 'Production',
        'is_active' => true,
        'last_heartbeat_at' => now(),
        'disk_threshold' => 80,
    ]);

    ServerMetric::create([
        'server_id' => $server->server_id,
        'cpu' => 35,
        'ram_used' => 7,
        'ram_total' => 10,
        'disk_used' => 92,
        'disk_total' => 100,
        'timestamp' => now(),
    ]);

    $monitor = Monitor::create([
        'name' => 'Report Site',
        'url' => 'https://report.example.test',
        'is_active' => true,
        'ssl_expires_at' => now()->addDays(5),
        'ssl_alert_threshold_days' => 30,
    ]);

    CheckResult::create([
        'monitor_id' => $monitor->id,
        'status_code' => 200,
        'response_time' => 100,
        'is_up' => true,
        'checked_at' => now()->subMinutes(10),
    ]);
    CheckResult::create([
        'monitor_id' => $monitor->id,
        'status_code' => 500,
        'response_time' => 900,
        'is_up' => false,
        'checked_at' => now()->subMinutes(5),
    ]);

    $application = Application::create([
        'name' => 'Report App',
        'code' => 'report-app',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $application->servers()->attach($server->id, ['role' => 'application', 'is_required' => true]);
    ApplicationUrl::create([
        'application_id' => $application->id,
        'monitor_id' => $monitor->id,
        'url' => $monitor->url,
    ]);

    $databaseMonitor = DatabaseMonitor::create([
        'name' => 'Report DB',
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'database_name' => 'app',
        'username' => 'app',
        'is_active' => true,
        'last_status' => 'down',
    ]);
    DatabaseCheck::create([
        'database_monitor_id' => $databaseMonitor->id,
        'is_up' => false,
        'error' => 'Connection timeout',
        'checked_at' => now(),
    ]);

    $service = WindowsService::create([
        'server_id' => $server->id,
        'service_name' => 'W3SVC',
        'display_name' => 'World Wide Web Publishing Service',
        'status' => 'Stopped',
        'is_monitored' => true,
    ]);
    WindowsServiceCheck::create([
        'windows_service_id' => $service->id,
        'status' => 'Stopped',
        'checked_at' => now(),
    ]);

    IisLogSummary::create([
        'server_id' => $server->id,
        'agent_server_id' => $server->server_id,
        'window_start' => now()->subHour(),
        'window_end' => now(),
        'total_requests' => 100,
        'http_404' => 12,
        'http_500' => 15,
        'suspicious_count' => 2,
        'top_ips' => [['value' => '203.0.113.10', 'count' => 20]],
        'top_urls' => [['value' => '/login', 'count' => 18]],
    ]);

    WebshellScan::create([
        'source' => 'manual',
        'status' => 'completed',
        'target' => 'C:\\inetpub\\wwwroot',
        'scanned_files' => 10,
        'findings' => [['file' => 'shell.php']],
        'scanned_at' => now(),
    ]);

    return compact('application', 'server', 'monitor');
}

test('maintenance report page renders filters for authorized users', function () {
    $this->actingAs(reportUser());
    seedReportData();

    $this->get(route('reports.maintenance.index'))
        ->assertOk()
        ->assertSee('Maintenance Reports')
        ->assertSee('Report App')
        ->assertSee('Production');
});

test('maintenance report html preview stores summary and recommendations', function () {
    $this->actingAs(reportUser());
    $data = seedReportData();

    $this->post(route('reports.maintenance.generate'), [
        'report_type' => 'custom',
        'period_start' => now()->subDay()->toDateString(),
        'period_end' => now()->toDateString(),
        'application_id' => $data['application']->id,
        'server_group' => 'Production',
        'environment' => 'production',
        'output' => 'html',
    ])
        ->assertOk()
        ->assertSee('Executive Summary')
        ->assertSee('Review disk capacity')
        ->assertSee('Suspicious IIS');

    $report = MaintenanceReport::first();

    expect($report)->not->toBeNull()
        ->and($report->summary['iis']['http_500'])->toBe(15)
        ->and($report->summary['database']['failures'])->toBe(1)
        ->and($report->file_path)->toBeNull();
});

test('maintenance report exports excel file and download history link', function () {
    Bus::fake();
    Storage::fake('local');
    $this->actingAs(reportUser());
    seedReportData();

    $this->post(route('reports.maintenance.generate'), [
        'report_type' => 'daily',
        'period_end' => now()->toDateString(),
        'output' => 'excel',
    ])->assertRedirect(route('reports.maintenance.history'));

    Bus::assertDispatched(GenerateMaintenanceReportJob::class);

    $report = MaintenanceReport::first();

    expect($report->status)->toBe('pending')
        ->and($report->file_path)->toBeNull();

    $this->get(route('reports.maintenance.history'))
        ->assertOk()
        ->assertSee('Preparing');

    (new GenerateMaintenanceReportJob($report, 'excel'))->handle(
        app(MaintenanceReportExportService::class),
        app(AuditLogger::class)
    );

    $report->refresh();

    expect($report->status)->toBe('completed');
    expect($report->file_path)->toEndWith('.xls');
    Storage::disk('local')->assertExists($report->file_path);

    $this->get(route('reports.maintenance.history'))
        ->assertOk()
        ->assertSee('Download');

    $this->get(route('reports.maintenance.download', $report))
        ->assertOk();
});

test('maintenance report queues pdf file generation', function () {
    Bus::fake();
    Storage::fake('local');
    $this->actingAs(reportUser());
    seedReportData();

    $this->post(route('reports.maintenance.generate'), [
        'report_type' => 'weekly',
        'period_end' => now()->toDateString(),
        'output' => 'pdf',
    ])->assertRedirect(route('reports.maintenance.history'));

    $report = MaintenanceReport::first();

    Bus::assertDispatched(GenerateMaintenanceReportJob::class);
    expect($report->status)->toBe('pending')
        ->and($report->queued_at)->not->toBeNull()
        ->and($report->file_path)->toBeNull();
});
