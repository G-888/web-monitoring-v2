<?php

require __DIR__ . '/../vendor/autoload.php';

// bootstrap the app
$app = require_once __DIR__ . '/../bootstrap/app.php';

// make the kernel to ensure models are available
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$application = App\Models\Application::where('code', 'test-app')->with('servers','urls')->first();
if (! $application) {
    echo "NOT_FOUND\n";
    exit(1);
}

echo "FOUND: {$application->name} (id={$application->id})\n";
echo "servers: " . $application->servers->count() . " urls: " . $application->urls->count() . "\n";
