<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'DB_DEFAULT=' . config('database.default') . "\n";
print_r(config('database.connections.mysql'));
echo 'DBNAME=' . DB::connection()->getDatabaseName() . "\n";
print_r(DB::select('SHOW TABLES'));
