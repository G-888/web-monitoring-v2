<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DatabaseMonitorController;
use App\Http\Controllers\LogInspectionController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerResourcesController;
use App\Http\Controllers\SslConversionController;
use App\Http\Controllers\WindowsServiceController;
use App\Models\Monitor;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/status', function () {
    $monitors = Monitor::with('latestResult')->get();

    foreach ($monitors as $monitor) {
        $monitor->uptime_24h = $monitor->uptimePercentage(24);
    }

    return view('status', compact('monitors'));
})->name('status');

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

    Route::get('/seo-security', [App\Http\Controllers\SeoSecurityController::class, 'index'])->name('seo-security.index');
    Route::post('/seo-security/scan', [App\Http\Controllers\SeoSecurityController::class, 'scan'])->name('seo-security.scan');

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
});

// API Routes
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::prefix('api')->group(function () {
    Route::post('/metrics', [\App\Http\Controllers\Api\MetricsController::class, 'store'])
        ->withoutMiddleware([VerifyCsrfToken::class]);
});

require __DIR__.'/auth.php';
