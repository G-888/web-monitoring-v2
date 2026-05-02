<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\LogInspectionController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServerResourcesController;
use App\Http\Controllers\SslConversionController;
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
        Route::get('/server-resources', [ServerResourcesController::class, 'index'])
            ->name('server-resources');

        Route::get('/server-resources/snapshot', [ServerResourcesController::class, 'snapshot'])
            ->name('server-resources.snapshot');
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
