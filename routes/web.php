<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DatabaseMonitorController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IisLogController;
use App\Http\Controllers\ApplicationUrlController;
use App\Http\Controllers\ClientArchitectureWizardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ArchitectureReviewController;
use App\Http\Controllers\LogInspectionController;
use App\Http\Controllers\MaintenanceReportController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerResourcesController;
use App\Http\Controllers\DatabaseMonitorGuidedSetupController;
use App\Http\Controllers\SslMonitorController;
use App\Http\Controllers\SslConversionController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\WindowsServiceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/status', [StatusController::class, 'index'])->name('status');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [MonitorController::class, 'index'])
        ->name('dashboard');

    Route::get('/monitors/create', [MonitorController::class, 'create'])
        ->name('monitors.create');

    Route::post('/monitors', [MonitorController::class, 'store'])
        ->name('monitors.store');

    Route::get('/monitors/{monitor}/edit', [MonitorController::class, 'edit'])
        ->name('monitors.edit');

    Route::patch('/monitors/{monitor}', [MonitorController::class, 'update'])
        ->name('monitors.update');

    Route::delete('/monitors/{monitor}', [MonitorController::class, 'destroy'])
        ->name('monitors.destroy');

    Route::post('/monitors/{monitor}/toggle', [MonitorController::class, 'toggle'])
        ->name('monitors.toggle');

    Route::post('/monitors/{monitor}/check', [MonitorController::class, 'check'])
        ->name('monitors.check');

    Route::middleware(['can:module.server_metrics'])->group(function () {
        Route::get('/servers', [ServerController::class, 'index'])
            ->name('servers.index');

        Route::get('/servers/create', [ServerController::class, 'create'])
            ->name('servers.create');

        Route::post('/servers', [ServerController::class, 'store'])
            ->name('servers.store');

        Route::get('/servers/{server}/edit', [ServerController::class, 'edit'])
            ->name('servers.edit');

        Route::patch('/servers/{server}', [ServerController::class, 'update'])
            ->name('servers.update');

        Route::delete('/servers/{server}', [ServerController::class, 'destroy'])
            ->name('servers.destroy');

        Route::get('/servers/windows-services', [WindowsServiceController::class, 'index'])
            ->name('servers.windows-services');

        Route::post('/servers/{server}/windows-services', [WindowsServiceController::class, 'store'])
            ->name('servers.windows-services.store');

        Route::delete('/windows-services/{windowsService}', [WindowsServiceController::class, 'destroy'])
            ->name('windows-services.destroy');

        Route::middleware(['can:module.service_control'])->group(function () {
            Route::post('/windows-services/{windowsService}/commands', [WindowsServiceController::class, 'command'])
                ->name('windows-services.commands');
        });

        Route::get('/server-resources', [ServerResourcesController::class, 'index'])
            ->name('server-resources');

        Route::get('/server-resources/snapshot', [ServerResourcesController::class, 'snapshot'])
            ->name('server-resources.snapshot');

        Route::get('/server-resources/history', [ServerResourcesController::class, 'history'])
            ->name('server-resources.history');
    });

    Route::middleware(['can:module.database_monitoring'])->group(function () {
        Route::get('/database-monitors', [DatabaseMonitorController::class, 'index'])
            ->name('database-monitors.index');
        Route::get('/database-monitors/create', [DatabaseMonitorController::class, 'create'])
            ->name('database-monitors.create');
        Route::post('/database-monitors', [DatabaseMonitorController::class, 'store'])
            ->name('database-monitors.store');
        Route::get('/database-monitors/{databaseMonitor}/edit', [DatabaseMonitorController::class, 'edit'])
            ->name('database-monitors.edit');
        Route::patch('/database-monitors/{databaseMonitor}', [DatabaseMonitorController::class, 'update'])
            ->name('database-monitors.update');
        Route::delete('/database-monitors/{databaseMonitor}', [DatabaseMonitorController::class, 'destroy'])
            ->name('database-monitors.destroy');
        Route::post('/database-monitors/{databaseMonitor}/test', [DatabaseMonitorController::class, 'test'])
            ->name('database-monitors.test');
        Route::get('/database-monitors/{databaseMonitor}/guided-setup', [DatabaseMonitorGuidedSetupController::class, 'edit'])
            ->name('database-monitors.guided-setup');
        Route::patch('/database-monitors/{databaseMonitor}/guided-setup', [DatabaseMonitorGuidedSetupController::class, 'update'])
            ->name('database-monitors.guided-setup.update');
        Route::post('/database-monitors/{databaseMonitor}/guided-setup/test', [DatabaseMonitorGuidedSetupController::class, 'test'])
            ->name('database-monitors.guided-setup.test');
        Route::post('/database-monitors/{databaseMonitor}/guided-setup/enable', [DatabaseMonitorGuidedSetupController::class, 'enable'])
            ->name('database-monitors.guided-setup.enable');
    });

    Route::middleware(['can:module.network_monitoring'])->group(function () {
        Route::get('/network-monitors', [\App\Http\Controllers\NetworkMonitorController::class, 'index'])
            ->name('network-monitors.index');
        Route::get('/network-map', [\App\Http\Controllers\NetworkMonitorController::class, 'map'])
            ->name('network-map.index');
        Route::get('/network-monitors/create', [\App\Http\Controllers\NetworkMonitorController::class, 'create'])
            ->name('network-monitors.create');
        Route::post('/network-monitors', [\App\Http\Controllers\NetworkMonitorController::class, 'store'])
            ->name('network-monitors.store');
        Route::get('/network-monitors/{networkMonitor}', [\App\Http\Controllers\NetworkMonitorController::class, 'show'])
            ->name('network-monitors.show');
        Route::get('/network-monitors/{networkMonitor}/edit', [\App\Http\Controllers\NetworkMonitorController::class, 'edit'])
            ->name('network-monitors.edit');
        Route::patch('/network-monitors/{networkMonitor}', [\App\Http\Controllers\NetworkMonitorController::class, 'update'])
            ->name('network-monitors.update');
        Route::delete('/network-monitors/{networkMonitor}', [\App\Http\Controllers\NetworkMonitorController::class, 'destroy'])
            ->name('network-monitors.destroy');
        Route::post('/network-monitors/{networkMonitor}/check', [\App\Http\Controllers\NetworkMonitorController::class, 'check'])
            ->name('network-monitors.check');
        Route::post('/server-port-baselines', [\App\Http\Controllers\ServerPortBaselineController::class, 'store'])
            ->name('server-port-baselines.store');
        Route::post('/server-port-baselines/{serverPortBaseline}/check', [\App\Http\Controllers\ServerPortBaselineController::class, 'check'])
            ->name('server-port-baselines.check');
        Route::post('/server-port-baselines/apply-template', [\App\Http\Controllers\ServerPortBaselineController::class, 'applyTemplate'])
            ->name('server-port-baselines.apply-template');
        Route::delete('/server-port-baselines/{serverPortBaseline}', [\App\Http\Controllers\ServerPortBaselineController::class, 'destroy'])
            ->name('server-port-baselines.destroy');
    });

    Route::get('/server-logs/scan', [\App\Http\Controllers\ServerLogScannerController::class, 'index'])
        ->name('server-logs.index');

    Route::post('/server-logs/scan', [\App\Http\Controllers\ServerLogScannerController::class, 'scan'])
        ->name('server-logs.scan');

    Route::middleware(['can:module.advanced_alerts'])->group(function () {
        Route::get('/alert-channels', [\App\Http\Controllers\AlertChannelController::class, 'index'])
            ->name('alert-channels.index');
        Route::post('/alert-channels', [\App\Http\Controllers\AlertChannelController::class, 'store'])
            ->name('alert-channels.store');
        Route::delete('/alert-channels/{alertChannel}', [\App\Http\Controllers\AlertChannelController::class, 'destroy'])
            ->name('alert-channels.destroy');
    });

    Route::get('/log-inspections', [LogInspectionController::class, 'index'])
        ->name('log-inspections.index');

    Route::get('/incidents', [IncidentController::class, 'index'])
        ->name('incidents.index');

    Route::middleware(['can:module.log_ingestion'])->group(function () {
        Route::get('/iis-logs', [IisLogController::class, 'index'])
            ->name('iis-logs.index');
        Route::get('/iis-logs/servers/{server}', [IisLogController::class, 'show'])
            ->name('iis-logs.show');
    });

    Route::prefix('reports/maintenance')->name('reports.maintenance.')->group(function () {
        Route::get('/', [MaintenanceReportController::class, 'index'])
            ->middleware('can:module.reports.view')
            ->name('index');
        Route::get('/history', [MaintenanceReportController::class, 'history'])
            ->middleware('can:module.reports.view')
            ->name('history');
        Route::post('/', [MaintenanceReportController::class, 'generate'])
            ->middleware('can:module.reports.generate')
            ->name('generate');
        Route::get('/{maintenanceReport}/download', [MaintenanceReportController::class, 'download'])
            ->middleware('can:module.reports.download')
            ->name('download');
    });

    Route::post('/log-inspections', [LogInspectionController::class, 'store'])
        ->name('log-inspections.store');

    Route::get('/log-inspections/{logInspection}', [LogInspectionController::class, 'show'])
        ->name('log-inspections.show');

    Route::post('/log-inspections/{logInspection}/ai-analyze', [LogInspectionController::class, 'analyzeWithAi'])
        ->name('log-inspections.ai-analyze');

    Route::get('/ssl-conversion', [SslConversionController::class, 'index'])
        ->name('ssl-conversion.index');

    Route::post('/ssl-conversion', [SslConversionController::class, 'convert'])
        ->name('ssl-conversion.convert');

    Route::get('/ssl-monitors', [SslMonitorController::class, 'index'])
        ->name('ssl-monitors.index');

    Route::post('/ssl-monitors', [SslMonitorController::class, 'store'])
        ->name('ssl-monitors.store');

    Route::post('/ssl-monitors/check-all', [SslMonitorController::class, 'checkAll'])
        ->name('ssl-monitors.check-all');

    Route::post('/ssl-monitors/{monitor}/check', [SslMonitorController::class, 'check'])
        ->name('ssl-monitors.check');

    Route::patch('/ssl-monitors/{monitor}/threshold', [SslMonitorController::class, 'updateThreshold'])
        ->name('ssl-monitors.threshold');

    Route::delete('/ssl-monitors/{monitor}', [SslMonitorController::class, 'destroy'])
        ->name('ssl-monitors.destroy');

    Route::get('/seo-security', [App\Http\Controllers\SeoSecurityController::class, 'index'])->name('seo-security.index');
    Route::post('/seo-security/scan', [App\Http\Controllers\SeoSecurityController::class, 'scan'])->name('seo-security.scan');
    Route::post('/seo-security/scan-all', [App\Http\Controllers\SeoSecurityController::class, 'scanAll'])->name('seo-security.scan-all');
    Route::post('/seo-security/webshell-scan', [App\Http\Controllers\SeoSecurityController::class, 'webshellScan'])->name('seo-security.webshell-scan');

    // Asset Intelligence
    Route::get('/assets', [App\Http\Controllers\AssetIntelligenceController::class, 'index'])->name('assets.index');
    Route::post('/assets/scan', [App\Http\Controllers\AssetIntelligenceController::class, 'scan'])->name('assets.scan');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    Route::prefix('admin')
        ->middleware([\App\Http\Middleware\AdminMiddleware::class])
        ->group(function () {
            Route::get('/', [AdminController::class, 'index'])
                ->name('admin.dashboard');

            Route::get('/email-settings', [AdminController::class, 'emailSettings'])
                ->name('admin.email-settings');

            Route::post('/email-settings', [AdminController::class, 'updateEmailSettings'])
                ->name('admin.email-settings.update');

            Route::post('/email-settings/test', [AdminController::class, 'testEmailSettings'])
                ->name('admin.email-settings.test');

            Route::get('/telegram-settings', [AdminController::class, 'telegramSettings'])
                ->name('admin.telegram-settings');

            Route::post('/telegram-settings', [AdminController::class, 'updateTelegramSettings'])
                ->name('admin.telegram-settings.update');

            Route::post('/telegram-settings/fetch-chat-id', [AdminController::class, 'fetchTelegramChatId'])
                ->name('admin.telegram-settings.fetchChatId');

            Route::post('/telegram-settings/clear-updates', [AdminController::class, 'clearTelegramUpdates'])
                ->name('admin.telegram-settings.clearUpdates');

            Route::post('/telegram-settings/test', [AdminController::class, 'testTelegramSettings'])
                ->name('admin.telegram-settings.test');

            Route::post('/monitors/{monitor}/toggle', [AdminController::class, 'toggleMonitor'])
                ->name('admin.monitors.toggle');

            Route::post('/monitors/{monitor}/check', [AdminController::class, 'checkMonitor'])
                ->name('admin.monitors.check');

            Route::patch('/monitors/{monitor}/assign', [AdminController::class, 'assignMonitor'])
                ->name('admin.monitors.assign');

            Route::delete('/monitors/{monitor}', [AdminController::class, 'destroyMonitor'])
                ->name('admin.monitors.destroy');

            Route::post('/users/{user}/approve', [AdminController::class, 'approveUser'])
                ->name('admin.users.approve');

            Route::get('/users/{user}/permissions', [AdminController::class, 'editPermissions'])
                ->name('admin.users.permissions');

            Route::post('/users/{user}/permissions', [AdminController::class, 'updatePermissions'])
                ->name('admin.users.permissions.update');

            Route::post('/users/{user}/toggle-admin', [AdminController::class, 'toggleUserAdmin'])
                ->name('admin.users.toggleAdmin');

            Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])
                ->name('admin.users.destroy');
        });
    
    // Application mapping and dashboards
    Route::middleware(['can:module.application_mapping'])->group(function () {
        Route::resource('clients', ClientController::class);
        Route::get('/client-architecture/setup', [ClientArchitectureWizardController::class, 'create'])->name('client-architecture.setup');
        Route::post('/client-architecture/setup', [ClientArchitectureWizardController::class, 'store'])->name('client-architecture.setup.store');
        Route::get('/applications/{application}/architecture-review', [ArchitectureReviewController::class, 'show'])->name('applications.architecture-review');
        Route::get('/applications', [\App\Http\Controllers\ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/setup', [\App\Http\Controllers\ApplicationSetupWizardController::class, 'create'])->name('applications.setup');
        Route::post('/applications/setup', [\App\Http\Controllers\ApplicationSetupWizardController::class, 'store'])->name('applications.setup.store');
        Route::get('/applications/create', [\App\Http\Controllers\ApplicationController::class, 'create'])->name('applications.create');
        Route::post('/applications', [\App\Http\Controllers\ApplicationController::class, 'store'])->name('applications.store');
        Route::get('/applications/{application}/edit', [\App\Http\Controllers\ApplicationController::class, 'edit'])->name('applications.edit');
        Route::patch('/applications/{application}', [\App\Http\Controllers\ApplicationController::class, 'update'])->name('applications.update');
        Route::get('/applications/{application}/agent-packages', [\App\Http\Controllers\ApplicationSetupWizardController::class, 'downloadApplicationPackages'])
            ->middleware('can:module.agent_deployment')
            ->name('applications.agent-packages');
        Route::get('/applications/{application}', [\App\Http\Controllers\ApplicationController::class, 'show'])->name('applications.show');
        Route::post('/application-urls/{applicationUrl}/link-monitor', [ApplicationUrlController::class, 'linkMonitor'])->name('application-urls.link-monitor');
    });

    // Agent operations
    Route::middleware(['can:module.server_metrics'])->group(function () {
        Route::get('/agents', [\App\Http\Controllers\AgentController::class, 'index'])->name('agents.index');
    });

    Route::middleware(['can:module.agent_deployment'])->group(function () {
        Route::get('/agents/{server}/config', [\App\Http\Controllers\AgentController::class, 'downloadConfig'])->name('agents.config');
        Route::get('/servers/{server}/agent-config', [\App\Http\Controllers\AgentController::class, 'downloadConfig'])->name('servers.agent-config');
        Route::get('/servers/{server}/agent-package', [\App\Http\Controllers\AgentController::class, 'downloadPackage'])->name('servers.agent-package');
        Route::post('/servers/{server}/agent-key/rotate', [\App\Http\Controllers\AgentController::class, 'rotateKey'])->name('servers.agent-key.rotate');
    });

    });

// API Routes
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::prefix('api')->group(function () {
    Route::post('/metrics', [\App\Http\Controllers\Api\MetricsController::class, 'store'])
        ->withoutMiddleware([VerifyCsrfToken::class]);
    Route::post('/iis-logs/summary', [\App\Http\Controllers\Api\IisLogSummaryController::class, 'store'])
        ->withoutMiddleware([VerifyCsrfToken::class]);
    Route::post('/network-checks/results', [\App\Http\Controllers\Api\NetworkCheckResultController::class, 'store'])
        ->withoutMiddleware([VerifyCsrfToken::class]);
});

require __DIR__.'/auth.php';
