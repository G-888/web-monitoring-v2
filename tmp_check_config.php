<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
echo 'LOG_CHANNEL=' . config('logging.default') . "\n";
echo 'APP_DEBUG=' . (config('app.debug') ? 'true' : 'false') . "\n";
echo 'DB_CONNECTION=' . config('database.default') . "\n";
echo 'DB_HOST=' . config('database.connections.' . config('database.default') . '.host') . "\n";
echo 'LOG_PATH=' . config('logging.channels.single.path') . "\n";
